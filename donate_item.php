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

// Get item details and verify ownership
$stmt = $db->prepare("SELECT i.*, c.Category_Name, l.Location_Name, u.Name as Creator_Name
                      FROM Item i
                      LEFT JOIN Category c ON i.Category_ID = c.Category_ID
                      LEFT JOIN Location l ON i.Location_ID = l.Location_ID
                      LEFT JOIN User u ON i.Creator_ID = u.User_ID
                      WHERE i.Item_ID = ? AND i.Creator_ID = ? AND i.Status = 'reported'");
$stmt->execute([$item_id, $_SESSION['user_id']]);
$item = $stmt->fetch();

if (!$item) {
    header("Location: donations.php");
    exit();
}

// Checking if item is eligible for donation or not
$is_expired = strtotime($item['Expiration_Date']) < time();
$is_month_old = strtotime($item['Reported_Date']) < strtotime('-1 month');

if (!$is_expired && !$is_month_old) {
    header("Location: donations.php");
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $recipient_organization = trim($_POST['recipient_organization']);
    $notes = trim($_POST['notes']);
    $confirm_donation = isset($_POST['confirm_donation']);

    if (!$confirm_donation) {
        $error = 'Please confirm that you want to donate this item.';
    } else {
        try {
            $db->beginTransaction();

            // Donation record craetion
            $donation_id = 'donation_' . uniqid();
            $donation_date = date('Y-m-d H:i:s');

            $stmt = $db->prepare("INSERT INTO Donation (Donation_ID, Item_ID, Donor_ID, Donation_Date, Recipient_Organization, Notes) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$donation_id, $item_id, $_SESSION['user_id'], $donation_date, $recipient_organization, $notes]);

            // Update item status to donated
            $stmt = $db->prepare("UPDATE Item SET Status = 'donated', Updated_At = CURRENT_TIMESTAMP WHERE Item_ID = ?");
            $stmt->execute([$item_id]);

            // Close any active conversations for this item
            $stmt = $db->prepare("UPDATE Conversation SET Status = 'closed' WHERE Item_ID = ?");
            $stmt->execute([$item_id]);

            // Notification for any claimants
            $stmt = $db->prepare("SELECT DISTINCT User_ID FROM Claim WHERE Item_ID = ? AND Claim_Status = 'pending'");
            $stmt->execute([$item_id]);
            $claimants = $stmt->fetchAll();

            foreach ($claimants as $claimant) {
                $notification_id = 'notif_' . uniqid();
                $stmt = $db->prepare("INSERT INTO Notification (Notification_ID, User_ID, Item_ID, Type, Title, Message) VALUES (?, ?, ?, 'item_donated', 'Item Donated', ?)");
                $message = "The item you were interested in has been donated to charity. Thank you for your interest.";
                $stmt->execute([$notification_id, $claimant['User_ID'], $item_id, $message]);
            }

            $db->commit();
            $success = 'Item donated successfully! Thank you for your generosity.';
        } catch (Exception $e) {
            $db->rollback();
            $error = 'Error donating item: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Donate Item - Lost & Found</title>
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
                    <div class="card-header bg-success text-white">
                        <h3><i class="fas fa-heart me-2"></i>Donate Item</h3>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($success): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
                            </div>
                            <div class="text-center mt-3">
                                <a href="donations.php" class="btn btn-primary">View My Donations</a>
                                <a href="my_items.php" class="btn btn-outline-primary">My Items</a>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <h5><i class="fas fa-info-circle me-2"></i>Donation Information</h5>
                                <p class="mb-0">This item is eligible for donation because it has
                                    <?php if ($is_expired): ?>
                                        <strong>expired</strong> on <?php echo date('M d, Y', strtotime($item['Expiration_Date'])); ?>
                                    <?php else: ?>
                                        been <strong>reported for over 1 month</strong> (since <?php echo date('M d, Y', strtotime($item['Reported_Date'])); ?>)
                                    <?php endif; ?>
                                    and has not been claimed.
                                </p>
                            </div>

                            <!-- Item Preview -->
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5>Item to be donated:</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-8">
                                            <h6><?php echo htmlspecialchars($item['Description']); ?></h6>
                                            <div class="mb-2">
                                                <span class="badge bg-secondary me-1"><?php echo htmlspecialchars($item['Category_Name']); ?></span>
                                                <span class="badge bg-outline-secondary me-1"><?php echo htmlspecialchars($item['Location_Name']); ?></span>
                                                <span class="badge bg-<?php echo $item['Priority'] == 'high' ? 'danger' : ($item['Priority'] == 'medium' ? 'warning' : 'secondary'); ?>"><?php echo ucfirst($item['Priority']); ?></span>
                                            </div>
                                            <small class="text-muted">
                                                <i class="fas fa-calendar me-1"></i>Reported: <?php echo date('M d, Y g:i A', strtotime($item['Reported_Date'])); ?><br>
                                                <i class="fas fa-clock me-1"></i>Expires: <?php echo date('M d, Y', strtotime($item['Expiration_Date'])); ?>
                                            </small>
                                        </div>
                                        <div class="col-md-4">
                                            <?php
                                            $stmt_img = $db->prepare("SELECT File_URL FROM Attachment WHERE Item_ID = ? LIMIT 1");
                                            $stmt_img->execute([$item['Item_ID']]);
                                            $image = $stmt_img->fetch();
                                            ?>
                                            <?php $img = getImageWithFallback($image['File_URL'] ?? ''); ?>
                                            <?php if ($img): ?>
                                                <img src="<?php echo htmlspecialchars($img); ?>"
                                                    class="img-fluid rounded"
                                                    style="width: 100%; height: 120px; object-fit: cover;">
                                            <?php else: ?>
                                                <div class="bg-light d-flex align-items-center justify-content-center rounded"
                                                    style="width: 100%; height: 120px;">
                                                    <span class="text-muted">No Image</span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <form method="POST">
                                <div class="mb-3">
                                    <label for="recipient_organization" class="form-label">Recipient Organization (Optional)</label>
                                    <input type="text" class="form-control" id="recipient_organization" name="recipient_organization"
                                        placeholder="e.g., Local Charity, Red Cross, etc."
                                        value="<?php echo isset($_POST['recipient_organization']) ? htmlspecialchars($_POST['recipient_organization']) : ''; ?>">
                                    <small class="form-text text-muted">Specify which organization will receive this item.</small>
                                </div>

                                <div class="mb-3">
                                    <label for="notes" class="form-label">Donation Notes (Optional)</label>
                                    <textarea class="form-control" id="notes" name="notes" rows="3"
                                        placeholder="Any additional notes about this donation..."><?php echo isset($_POST['notes']) ? htmlspecialchars($_POST['notes']) : ''; ?></textarea>
                                </div>

                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="confirm_donation" name="confirm_donation" required>
                                        <label class="form-check-label" for="confirm_donation">
                                            I confirm that I want to donate this item to charity. This action cannot be undone.
                                        </label>
                                    </div>
                                </div>

                                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                    <a href="donations.php" class="btn btn-secondary me-md-2">Cancel</a>
                                    <button type="submit" class="btn btn-success">
                                        <i class="fas fa-heart me-2"></i>Donate Item
                                    </button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>