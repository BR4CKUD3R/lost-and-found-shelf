<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: auth.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Mark all notifications as read
if (isset($_GET['mark_all_read'])) {
    $stmt = $db->prepare("UPDATE Notification SET Is_Read = TRUE, Read_At = CURRENT_TIMESTAMP WHERE User_ID = ?");
    $stmt->execute([$_SESSION['user_id']]);
    header("Location: notifications.php");
    exit();
}

// Mark specific notification as read
if (isset($_GET['mark_read'])) {
    $notification_id = $_GET['mark_read'];
    $stmt = $db->prepare("UPDATE Notification SET Is_Read = TRUE, Read_At = CURRENT_TIMESTAMP WHERE Notification_ID = ? AND User_ID = ?");
    $stmt->execute([$notification_id, $_SESSION['user_id']]);
    header("Location: notifications.php");
    exit();
}

// Get all notifications for user
$stmt = $db->prepare("SELECT n.*, i.Description as Item_Description, i.Item_Type
                      FROM Notification n
                      LEFT JOIN Item i ON n.Item_ID = i.Item_ID
                      WHERE n.User_ID = ?
                      ORDER BY n.Created_At DESC");
$stmt->execute([$_SESSION['user_id']]);
$notifications = $stmt->fetchAll();

// Get unread count
$stmt = $db->prepare("SELECT COUNT(*) as count FROM Notification WHERE User_ID = ? AND Is_Read = FALSE");
$stmt->execute([$_SESSION['user_id']]);
$unread_count = $stmt->fetch()['count'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - Lost & Found</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>

<body>
    <?php include 'includes/header.php'; ?>

    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h3><i class="fas fa-bell me-2"></i>Notifications</h3>
                        <?php if ($unread_count > 0): ?>
                            <a href="notifications.php?mark_all_read=1" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-check-double me-1"></i>Mark All as Read
                            </a>
                        <?php endif; ?>
                    </div>
                    <div class="card-body p-0">
                        <?php if (count($notifications) > 0): ?>
                            <?php foreach ($notifications as $notif): ?>
                                <div class="notification-item p-3 border-bottom <?php echo !$notif['Is_Read'] ? 'bg-light' : ''; ?>">
                                    <div class="d-flex">
                                        <div class="notification-icon me-3">
                                            <?php
                                            $icon = 'fas fa-info-circle';
                                            $color = 'text-info';
                                            switch ($notif['Type']) {
                                                case 'claim_approved':
                                                    $icon = 'fas fa-check-circle';
                                                    $color = 'text-success';
                                                    break;
                                                case 'claim_rejected':
                                                    $icon = 'fas fa-times-circle';
                                                    $color = 'text-danger';
                                                    break;
                                                case 'claim_received':
                                                    $icon = 'fas fa-hand-paper';
                                                    $color = 'text-warning';
                                                    break;
                                                case 'item_matched':
                                                    $icon = 'fas fa-search';
                                                    $color = 'text-primary';
                                                    break;
                                                case 'message_received':
                                                    $icon = 'fas fa-comments';
                                                    $color = 'text-info';
                                                    break;
                                                case 'item_expired':
                                                    $icon = 'fas fa-clock';
                                                    $color = 'text-warning';
                                                    break;
                                                case 'system_update':
                                                    $icon = 'fas fa-cog';
                                                    $color = 'text-secondary';
                                                    break;
                                            }
                                            ?>
                                            <i class="<?php echo $icon . ' ' . $color; ?>"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div>
                                                    <h6 class="mb-1 <?php echo !$notif['Is_Read'] ? 'fw-bold' : ''; ?>">
                                                        <?php echo htmlspecialchars($notif['Title'] ?? 'Notification'); ?>
                                                        <?php if (!$notif['Is_Read']): ?>
                                                            <span class="badge bg-primary ms-1">New</span>
                                                        <?php endif; ?>
                                                    </h6>
                                                    <p class="mb-1"><?php echo htmlspecialchars($notif['Message']); ?></p>
                                                    <?php if ($notif['Item_Description']): ?>
                                                        <small class="text-muted">
                                                            <i class="fas fa-box me-1"></i>
                                                            <?php echo htmlspecialchars(substr($notif['Item_Description'], 0, 60)) . (strlen($notif['Item_Description']) > 60 ? '...' : ''); ?>
                                                        </small>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="text-end">
                                                    <small class="text-muted">
                                                        <?php echo date('M d, g:i A', strtotime($notif['Created_At'])); ?>
                                                    </small>
                                                    <?php if (!$notif['Is_Read']): ?>
                                                        <div class="mt-1">
                                                            <a href="notifications.php?mark_read=<?php echo $notif['Notification_ID']; ?>"
                                                                class="btn btn-sm btn-outline-primary">
                                                                <i class="fas fa-check"></i> Mark Read
                                                            </a>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-bell-slash fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">No notifications yet</h5>
                                <p class="text-muted">You'll receive notifications when someone claims your items or when there are updates to your claims.</p>
                            </div>
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