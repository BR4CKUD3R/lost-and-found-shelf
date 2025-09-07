<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: auth.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get categories and locations
$stmt = $db->prepare("SELECT * FROM Category ORDER BY Category_Name");
$stmt->execute();
$categories = $stmt->fetchAll();

$stmt = $db->prepare("SELECT * FROM Location ORDER BY Location_Name");
$stmt->execute();
$locations = $stmt->fetchAll();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $description = trim($_POST['description']);
    $category_id = $_POST['category_id'];
    $location_id = $_POST['location_id'];
    $priority = $_POST['priority'];
    $expiration_date = $_POST['expiration_date'];
    $bounty_amount = isset($_POST['bounty_amount']) ? floatval($_POST['bounty_amount']) : 0;
    $bounty_type = $_POST['bounty_type'] ?? 'money';
    $bounty_description = trim($_POST['bounty_description'] ?? '');

    if (empty($description) || empty($category_id) || empty($location_id) || empty($expiration_date)) {
        $error = 'Please fill in all required fields.';
    } else {
        // Create uploads directory if it doesn't exist
        $upload_dir = 'uploads/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $item_id = uniqid('item_', true);
        $reported_date = date('Y-m-d H:i:s');
        $expiration_date = $expiration_date . ' 23:59:59'; // Set to end of day

        $stmt = $db->prepare("INSERT INTO Item (Item_ID, Creator_ID, Category_ID, Location_ID, Description, Priority, Status, Item_Type, Reported_Date, Expiration_Date) VALUES (?, ?, ?, ?, ?, ?, 'reported', 'lost', ?, ?)");

        if ($stmt->execute([$item_id, $_SESSION['user_id'], $category_id, $location_id, $description, $priority, $reported_date, $expiration_date])) {
            // Handle image upload
            if (isset($_FILES['item_image']) && $_FILES['item_image']['error'] == 0) {
                $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
                $file_extension = strtolower(pathinfo($_FILES['item_image']['name'], PATHINFO_EXTENSION));

                if (in_array($file_extension, $allowed_types)) {
                    $new_filename = uniqid() . '.' . $file_extension;
                    $upload_path = $upload_dir . $new_filename;

                    if (move_uploaded_file($_FILES['item_image']['tmp_name'], $upload_path)) {
                        $attachment_id = uniqid('att_', true);
                        $stmt = $db->prepare("INSERT INTO Attachment (Attachment_ID, Item_ID, File_URL, File_Name, File_Type, File_Size, Uploaded_At) VALUES (?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$attachment_id, $item_id, $upload_path, $_FILES['item_image']['name'], $file_extension, $_FILES['item_image']['size'], $reported_date]);
                    }
                }
            }

            // ! Bounty/reward Section
            if ($bounty_amount > 0) {
                $reward_id = uniqid('reward_', true);
                $stmt = $db->prepare("INSERT INTO Reward (Reward_ID, Item_ID, Reward_Type, Description, Amount, Currency, Status) VALUES (?, ?, ?, ?, ?, 'BDT', 'offered')");
                $stmt->execute([$reward_id, $item_id, $bounty_type, $bounty_description, $bounty_amount]);
            }

            $success = 'Lost item reported successfully!' . ($bounty_amount > 0 ? ' A bounty of ' . number_format($bounty_amount) . ' TK has been offered for this item.' : '');
        } else {
            $error = 'Failed to report item. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Lost Item - Lost & Found</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body>
    <?php include 'includes/header.php'; ?>

    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h3>Report Lost Item</h3>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>

                        <?php if ($success): ?>
                            <div class="alert alert-success"><?php echo $success; ?></div>
                        <?php endif; ?>

                        <form method="POST" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label for="description" class="form-label">Item Description (Give minimal descripiton about your item so that you can verify during claim process)</label>
                                <textarea class="form-control" id="description" name="description" rows="4"
                                    placeholder="Describe your lost item in detail..."
                                    required><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="category_id" class="form-label">Category *</label>
                                        <select class="form-select" id="category_id" name="category_id" required>
                                            <option value="">Select Category</option>
                                            <?php foreach ($categories as $category): ?>
                                                <option value="<?php echo $category['Category_ID']; ?>" <?php echo (isset($_POST['category_id']) && $_POST['category_id'] == $category['Category_ID']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($category['Category_Name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="location_id" class="form-label">Location: item was lost.. *</label>
                                        <select class="form-select" id="location_id" name="location_id" required>
                                            <option value="">Select Location</option>
                                            <?php foreach ($locations as $location): ?>
                                                <option value="<?php echo $location['Location_ID']; ?>" <?php echo (isset($_POST['location_id']) && $_POST['location_id'] == $location['Location_ID']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($location['Location_Name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="priority" class="form-label">Priority</label>
                                    <select class="form-select" id="priority" name="priority">
                                        <option value="low" <?php echo (isset($_POST['priority']) && $_POST['priority'] == 'low') ? 'selected' : ''; ?>>Low - Kindly look for this in future</option>
                                        <option value="medium" <?php echo (!isset($_POST['priority']) || $_POST['priority'] == 'medium') ? 'selected' : ''; ?>>Medium - </option>
                                        <option value="high" <?php echo (isset($_POST['priority']) && $_POST['priority'] == 'high') ? 'selected' : ''; ?>>High - I need this URGENT </option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="expiration_date" class="form-label">Expiration Date *</label>
                                    <input type="date" class="form-control" id="expiration_date" name="expiration_date"
                                        value="<?php echo isset($_POST['expiration_date']) ? $_POST['expiration_date'] : date('Y-m-d', strtotime('+30 days')); ?>"
                                        min="<?php echo date('Y-m-d'); ?>" required>
                                    <small class="form-text text-muted">After this date, the item will be donated if not claimed.</small>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="item_image" class="form-label">Item Photo (Optional)</label>
                                <input type="file" class="form-control" id="item_image" name="item_image"
                                    accept="image/*">
                                <small class="form-text text-muted">Upload a photo to help others identify your
                                    item.</small>
                            </div>

                            <!-- Bounty Section -->
                            <div class="card mb-3">
                                <div class="card-header">
                                    <h6 class="mb-0"><i class="fas fa-gift me-2"></i>Bounty/Reward (Leave Empty if you don't want to give any)</h6>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="bounty_type" class="form-label">Reward Type</label>
                                            <select class="form-select" id="bounty_type" name="bounty_type">
                                                <option value="money" <?php echo (!isset($_POST['bounty_type']) || $_POST['bounty_type'] == 'money') ? 'selected' : ''; ?>>Money</option>
                                                <option value="service" <?php echo (isset($_POST['bounty_type']) && $_POST['bounty_type'] == 'service') ? 'selected' : ''; ?>>Service</option>
                                                <option value="other" <?php echo (isset($_POST['bounty_type']) && $_POST['bounty_type'] == 'other') ? 'selected' : ''; ?>>Other</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6 mb-3" id="bounty_amount_container">
                                            <label for="bounty_amount" class="form-label">🤑 Amount (TAKA)</label>
                                            <input type="number" class="form-control" id="bounty_amount" name="bounty_amount"
                                                min="0" step="0.01" placeholder="0"
                                                value="<?php echo isset($_POST['bounty_amount']) ? htmlspecialchars($_POST['bounty_amount']) : ''; ?>">
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="bounty_description" class="form-label">Reward Description</label>
                                        <textarea class="form-control" id="bounty_description" name="bounty_description" rows="2"
                                            placeholder="Describe the reward in details (e.g., 'Free dinner', 'Section Swap' etc.)"><?php echo isset($_POST['bounty_description']) ? htmlspecialchars($_POST['bounty_description']) : ''; ?></textarea>
                                    </div>
                                </div>
                            </div>

                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="index.php" class="btn btn-secondary me-md-2">Cancel</a>
                                <button type="submit" class="btn btn-danger">Report Lost Item</button>
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
                // initialize on load
                updateAmountVisibility();
            }
        })();
    </script>
</body>

</html>