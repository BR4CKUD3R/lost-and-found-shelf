<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

if (!isset($_SESSION['user_id']) || !isset($_GET['id'])) {
    header("Location: auth.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

$item_id = $_GET['id'];

// Item details
$stmt = $db->prepare("SELECT i.*, c.Category_Name, l.Location_Name, u.Name as Creator_Name
                      FROM Item i
                      LEFT JOIN Category c ON i.Category_ID = c.Category_ID
                      LEFT JOIN Location l ON i.Location_ID = l.Location_ID
                      LEFT JOIN User u ON i.Creator_ID = u.User_ID
                      WHERE i.Item_ID = ? AND i.Creator_ID = ?");
$stmt->execute([$item_id, $_SESSION['user_id']]);
$item = $stmt->fetch();

if (!$item) {
    header("Location: my_items.php");
    exit();
}

// Categories and locations for dropdowns
$stmt = $db->prepare("SELECT * FROM Category ORDER BY Category_Name");
$stmt->execute();
$categories = $stmt->fetchAll();

$stmt = $db->prepare("SELECT * FROM Location ORDER BY Location_Name");
$stmt->execute();
$locations = $stmt->fetchAll();

// Get current attachments
$stmt = $db->prepare("SELECT * FROM Attachment WHERE Item_ID = ?");
$stmt->execute([$item_id]);
$attachments = $stmt->fetchAll();

// Get current reward/bounty
$stmt = $db->prepare("SELECT * FROM Reward WHERE Item_ID = ? AND Status = 'offered'");
$stmt->execute([$item_id]);
$current_reward = $stmt->fetch();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $db->beginTransaction();

        $description = trim($_POST['description']);
        $category_id = $_POST['category_id'];
        $location_id = $_POST['location_id'];
        $priority = $_POST['priority'];
        $item_type = $_POST['item_type'];
        $expiration_date = $_POST['expiration_date'];
        $bounty_amount = isset($_POST['bounty_amount']) ? floatval($_POST['bounty_amount']) : 0;
        $bounty_type = $_POST['bounty_type'] ?? 'money';
        $bounty_description = trim($_POST['bounty_description'] ?? '');

        // Update item
        $stmt = $db->prepare("UPDATE Item SET 
                             Description = ?, 
                             Category_ID = ?, 
                             Location_ID = ?, 
                             Priority = ?, 
                             Item_Type = ?, 
                             Expiration_Date = ?,
                             Updated_At = CURRENT_TIMESTAMP
                             WHERE Item_ID = ? AND Creator_ID = ?");
        $stmt->execute([$description, $category_id, $location_id, $priority, $item_type, $expiration_date, $item_id, $_SESSION['user_id']]);

        // File uploading
        if (isset($_FILES['attachments']) && !empty($_FILES['attachments']['name'][0])) {
            $upload_dir = 'uploads/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            for ($i = 0; $i < count($_FILES['attachments']['name']); $i++) {
                if ($_FILES['attachments']['error'][$i] == 0) {
                    $file_name = $_FILES['attachments']['name'][$i];
                    $file_tmp = $_FILES['attachments']['tmp_name'][$i];
                    $file_type = $_FILES['attachments']['type'][$i];
                    $file_size = $_FILES['attachments']['size'][$i];

                    // unique filename Generation
                    $file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
                    $unique_name = uniqid() . '_' . time() . '.' . $file_extension;
                    $file_path = $upload_dir . $unique_name;

                    if (move_uploaded_file($file_tmp, $file_path)) {
                        // Inserting attachment record
                        $attachment_id = 'att_' . uniqid();
                        $stmt = $db->prepare("INSERT INTO Attachment (Attachment_ID, Item_ID, File_URL, File_Name, File_Type, File_Size) VALUES (?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$attachment_id, $item_id, $file_path, $file_name, $file_type, $file_size]);
                    }
                }
            }
        }

        // bounty/reward update
        if ($bounty_amount > 0) {
            if ($current_reward) {
                // Update existing reward
                $stmt = $db->prepare("UPDATE Reward SET Reward_Type = ?, Description = ?, Amount = ? WHERE Item_ID = ? AND Status = 'offered'");
                $stmt->execute([$bounty_type, $bounty_description, $bounty_amount, $item_id]);
            } else {
                // Set new reward
                $reward_id = uniqid('reward_', true);
                $stmt = $db->prepare("INSERT INTO Reward (Reward_ID, Item_ID, Reward_Type, Description, Amount, Currency, Status) VALUES (?, ?, ?, ?, ?, 'USD', 'offered')");
                $stmt->execute([$reward_id, $item_id, $bounty_type, $bounty_description, $bounty_amount]);
            }
        }

        $db->commit();
        $_SESSION['success_message'] = 'Item updated successfully!';
        header("Location: view_item.php?id=" . $item_id);
        exit();
    } catch (Exception $e) {
        $db->rollback();
        $error_message = 'Error updating item: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Item - Lost & Found</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>

<body>
    <?php include 'includes/header.php'; ?>

    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-edit me-2"></i>Edit Item</h3>
                    </div>
                    <div class="card-body">
                        <?php if (isset($error_message)): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error_message); ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" enctype="multipart/form-data">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="item_type" class="form-label">Item Type *</label>
                                    <select class="form-select" id="item_type" name="item_type" required>
                                        <option value="lost" <?php echo $item['Item_Type'] == 'lost' ? 'selected' : ''; ?>>Lost Item</option>
                                        <option value="found" <?php echo $item['Item_Type'] == 'found' ? 'selected' : ''; ?>>Found Item</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="priority" class="form-label">Priority *</label>
                                    <select class="form-select" id="priority" name="priority" required>
                                        <option value="low" <?php echo $item['Priority'] == 'low' ? 'selected' : ''; ?>>Low</option>
                                        <option value="medium" <?php echo $item['Priority'] == 'medium' ? 'selected' : ''; ?>>Medium</option>
                                        <option value="high" <?php echo $item['Priority'] == 'high' ? 'selected' : ''; ?>>High</option>
                                    </select>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="description" class="form-label">Item Description (Give minimal descripiton about your item so that you can verify during claim process)</label>
                                <textarea class="form-control" id="description" name="description" rows="4" required><?php echo htmlspecialchars($item['Description']); ?></textarea>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="category_id" class="form-label">Category *</label>
                                    <select class="form-select" id="category_id" name="category_id" required>
                                        <option value="">Select Category</option>
                                        <?php foreach ($categories as $category): ?>
                                            <option value="<?php echo $category['Category_ID']; ?>"
                                                <?php echo $category['Category_ID'] == $item['Category_ID'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($category['Category_Name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="location_id" class="form-label">Location *</label>
                                    <select class="form-select" id="location_id" name="location_id" required>
                                        <option value="">Select Location</option>
                                        <?php foreach ($locations as $location): ?>
                                            <option value="<?php echo $location['Location_ID']; ?>"
                                                <?php echo $location['Location_ID'] == $item['Location_ID'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($location['Location_Name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="expiration_date" class="form-label">Expiration Date *</label>
                                <input type="date" class="form-control" id="expiration_date" name="expiration_date"
                                    value="<?php echo date('Y-m-d', strtotime($item['Expiration_Date'])); ?>" required>
                            </div>

                            <!-- Bounty Section -->
                            <div class="card mb-3">
                                <div class="card-header">
                                    <h6 class="mb-0"><i class="fas fa-gift me-2"></i>Bounty/Reward</h6>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="bounty_type" class="form-label">Reward Type</label>
                                            <select class="form-select" id="bounty_type" name="bounty_type">
                                                <option value="money" <?php echo (!$current_reward || $current_reward['Reward_Type'] == 'money') ? 'selected' : ''; ?>>Money</option>
                                                <option value="service" <?php echo ($current_reward && $current_reward['Reward_Type'] == 'service') ? 'selected' : ''; ?>>Service</option>
                                                <option value="other" <?php echo ($current_reward && $current_reward['Reward_Type'] == 'other') ? 'selected' : ''; ?>>Other</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="bounty_amount" class="form-label">🤑 Amount (TAKA)</label>
                                            <input type="number" class="form-control" id="bounty_amount" name="bounty_amount"
                                                min="0" step="0.01" placeholder="0"
                                                value="<?php echo $current_reward ? number_format($current_reward['Amount']) : ''; ?>">
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="bounty_description" class="form-label">Reward Description</label>
                                        <textarea class="form-control" id="bounty_description" name="bounty_description" rows="2"
                                            placeholder="Describe the reward (e.g., 'Gift card to local coffee shop', 'Free dinner', etc.)"><?php echo $current_reward ? htmlspecialchars($current_reward['Description']) : ''; ?></textarea>
                                    </div>
                                </div>
                            </div>

                            <!-- Current Attachments -->
                            <?php if (count($attachments) > 0): ?>
                                <div class="mb-3">
                                    <label class="form-label">Current Images (NB: No Image even after choosing file means not following filetype convention i.e., jpeg,jpg,png)</label>
                                    <div class="row">
                                        <?php foreach ($attachments as $attachment): ?>
                                            <div class="col-md-3 mb-2" id="attachment_<?php echo htmlspecialchars($attachment['Attachment_ID']); ?>">
                                                <div class="position-relative">
                                                    <?php $img = getImageWithFallback($attachment['File_URL']); ?>
                                                    <?php if ($img): ?>
                                                        <img src="<?php echo htmlspecialchars($img); ?>"
                                                            class="img-fluid rounded"
                                                            style="width: 100%; height: 120px; object-fit: cover;">
                                                    <?php else: ?>
                                                        <div class="bg-light rounded" style="width:100%; height:120px;"><!-- no image --></div>
                                                    <?php endif; ?>
                                                    <button type="button" class="btn btn-sm btn-danger position-absolute top-0 end-0 m-1"
                                                        onclick="removeAttachment('<?php echo $attachment['Attachment_ID']; ?>')">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <div class="mb-3">
                                <label for="attachments" class="form-label">Add More Images</label>
                                <input type="file" class="form-control" id="attachments" name="attachments[]"
                                    accept="image/*" multiple>
                                <div class="form-text">You can select multiple images. Supported formats: JPG, PNG, GIF</div>
                            </div>

                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="view_item.php?id=<?php echo $item_id; ?>" class="btn btn-secondary me-md-2">Cancel</a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Update Item
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        (function() {
            const bountyType = document.getElementById('bounty_type');
            const amountContainer = document.getElementById('bounty_amount_container');
            const amountInput = document.getElementById('bounty_amount');

            function updateAmountVisibility() {
                if (!bountyType || !amountContainer || !amountInput) return;
                const isOther = bountyType.value === 'other';
                amountContainer.style.display = isOther ? 'none' : '';
                amountInput.disabled = isOther;
                if (isOther) amountInput.value = '';
            }

            if (bountyType) {
                bountyType.addEventListener('change', updateAmountVisibility);
                updateAmountVisibility();
            }
        })();

        // Removing attachment 
        async function removeAttachment(attachmentId) {
            if (!confirm('Remove this attachment? This action cannot be undone.')) return;
            try {
                const res = await fetch('api/delete_attachment.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ attachment_id: attachmentId })
                });
                const data = await res.json();
                if (data.success) {
                    const el = document.getElementById('attachment_' + attachmentId);
                    if (el) el.remove();
                } else {
                    alert('Failed to delete attachment: ' + (data.message || 'Unknown error'));
                }
            } catch (err) {
                alert('Error deleting attachment: ' + err.message);
            }
        }
    </script>
</body>

</html>