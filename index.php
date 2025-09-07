<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: auth.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get user info
$stmt = $db->prepare("SELECT * FROM User WHERE User_ID = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Get recent items with optimized MariaDB query
$stmt = $db->prepare("SELECT i.*, c.Category_Name, l.Location_Name, u.Name as Creator_Name 
                       FROM Item i 
                       LEFT JOIN Category c ON i.Category_ID = c.Category_ID 
                       LEFT JOIN Location l ON i.Location_ID = l.Location_ID 
                       LEFT JOIN User u ON i.Creator_ID = u.User_ID 
                       ORDER BY i.Reported_Date DESC 
                       LIMIT 10");
$stmt->execute();
$recent_items = $stmt->fetchAll();

// Get user's unread notifications
$stmt = $db->prepare("SELECT * FROM Notification WHERE User_ID = ? AND Is_Read = FALSE ORDER BY Created_At DESC LIMIT 5");
$stmt->execute([$_SESSION['user_id']]);
$notifications = $stmt->fetchAll();

//! Getting dashboard Statistics
$stmt = $db->prepare("SELECT 
    (SELECT COUNT(*) FROM Item WHERE Status = 'reported') as total_active_items,
    (SELECT COUNT(*) FROM Item WHERE Creator_ID = ?) as user_items,
    (SELECT COUNT(*) FROM Claim WHERE User_ID = ?) as user_claims,
    (SELECT COUNT(*) FROM Notification WHERE User_ID = ? AND Is_Read = FALSE) as unread_notifications");
$stmt->execute([$_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id']]);
$stats = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lost & Found Dashboard</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="container mt-4">
        <!-- Welcome Section -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="welcome-card bg-gradient-primary text-white p-4 rounded-3">
                    <h2 class="mb-1">Welcome back, <?php echo htmlspecialchars($user['Name']); ?>! 👋</h2>
                    <p class="mb-0 opacity-75">Here's what's happening in the Lost & Found community</p>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stats-card">
                    <div class="stats-icon bg-primary">
                        <i class="fas fa-search"></i>
                    </div>
                    <div class="stats-content">
                        <h4><?php echo $stats['total_active_items']; ?></h4>
                        <p>Active Items</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stats-card">
                    <div class="stats-icon bg-success">
                        <i class="fas fa-box"></i>
                    </div>
                    <div class="stats-content">
                        <h4><?php echo $stats['user_items']; ?></h4>
                        <p>My Items</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stats-card">
                    <div class="stats-icon bg-warning">
                        <i class="fas fa-hand-paper"></i>
                    </div>
                    <div class="stats-content">
                        <h4><?php echo $stats['user_claims']; ?></h4>
                        <p>My Claims</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-8">
                <!-- Recent Items -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4 class="mb-0"><i class="fas fa-clock me-2"></i>Recent Items</h4>
                        <a href="browse_items.php" class="btn btn-outline-primary btn-sm">View All</a>
                    </div>
                    <div class="card-body">
                        <?php if (count($recent_items) > 0): ?>
                            <?php foreach ($recent_items as $item): ?>
                            <div class="item-card mb-3 p-3 border rounded-3">
                                <div class="row align-items-center">
                                    <div class="col-auto">
                                        <!-- === MODIFICATION START === -->
                                        <?php
                                            $stmt_img = $db->prepare("SELECT File_URL FROM Attachment WHERE Item_ID = ? LIMIT 1");
                                            $stmt_img->execute([$item['Item_ID']]);
                                            $image_record = $stmt_img->fetch();
                                            $fallback_image_url = 'assets/images/placeholder.png';
                                            $image_url = $fallback_image_url;
                                            if ($image_record) {
                                                $maybe = getImageWithFallback($image_record['File_URL'] ?? '');
                                                if (!empty($maybe)) $image_url = $maybe;
                                            }
                                        ?>
                                        <img src="<?php echo htmlspecialchars($image_url); ?>" 
                                             class="item-thumbnail" 
                                             alt="Item Image"
                                             loading="lazy"
                                             onerror="this.onerror=null; this.src='<?php echo $fallback_image_url; ?>';">
                                        <!-- === MODIFICATION END === -->
                                    </div>
                                    <div class="col">
                                        <h6 class="mb-1"><?php echo htmlspecialchars(substr($item['Description'], 0, 100)) . (strlen($item['Description']) > 100 ? '...' : ''); ?></h6>
                                        <div class="item-meta">
                                            <span class="badge bg-secondary me-1"><?php echo htmlspecialchars($item['Category_Name']); ?></span>
                                            <span class="badge bg-<?php echo $item['Status'] == 'reported' ? 'warning' : 'success'; ?>"><?php echo ucfirst($item['Status']); ?></span>
                                            <?php
                                            // ? Bounty small emoji inside the card
                                            $stmt_bounty = $db->prepare("SELECT Amount FROM Reward WHERE Item_ID = ? AND Status = 'offered'");
                                            $stmt_bounty->execute([$item['Item_ID']]);
                                            $bounty = $stmt_bounty->fetch();
                                            if ($bounty): ?>
                                                <span class="badge bg-warning text-dark me-1">
                                                    <i class="fas fa-gift me-1"></i>TK. <?php echo number_format($bounty['Amount'], 0); ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <small class="text-muted">
                                            <i class="fas fa-calendar me-1"></i><?php 
                                            // TODO: Retriving time from NTC GMT +6 -----------------------------------------------------               
                                            $dhaka_time = new DateTime($item['Reported_Date'], new DateTimeZone('GMT'));
                                            $dhaka_time->setTimezone(new DateTimeZone('Asia/Dhaka'));
                                            echo $dhaka_time->format('d M, Y g:i A'); 
                                            // TODO: Retriving time from NTC GMT +6 -----------------------------------------------------               
                                            ?>
                                        </small>
                                    </div>
                                    <div class="col-auto">
                                        <div class="btn-group-vertical" role="group">
                                            <a href="view_item.php?id=<?php echo $item['Item_ID']; ?>" class="btn btn-outline-primary btn-sm">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                            <?php if ($item['Creator_ID'] != $_SESSION['user_id'] && $item['Status'] == 'reported'): ?>
                                            <a href="claim_item.php?id=<?php echo $item['Item_ID']; ?>" class="btn btn-success btn-sm">
                                                <i class="fas fa-hand-paper"></i> Claim
                                            </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">No items reported yet</h5>
                                <p class="text-muted">Be the first to report a lost or found item!</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <!-- Quick Actions -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-bolt me-2"></i>Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="report_lost.php" class="btn btn-danger"><i class="fas fa-exclamation-triangle me-2"></i>Report Lost Item</a>
                            <a href="report_found.php" class="btn btn-success"><i class="fas fa-check-circle me-2"></i>Report Found Item</a>
                            <a href="browse_items.php" class="btn btn-info"><i class="fas fa-search me-2"></i>Browse Items</a>
                            <a href="my_items.php" class="btn btn-outline-primary"><i class="fas fa-box me-2"></i>My Items</a>
                            <a href="my_claims.php" class="btn btn-outline-warning"><i class="fas fa-hand-paper me-2"></i>My Claims</a>
                        </div>
                    </div>
                </div>

                <!-- Notifications -->
                <?php if (count($notifications) > 0): ?>
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-bell me-2"></i>Notifications 
                            <span class="badge bg-danger"><?php echo count($notifications); ?></span>
                        </h5>
                        <a href="notifications.php" class="btn btn-outline-primary btn-sm">View All</a>
                    </div>
                    <div class="card-body p-0">
                        <?php foreach ($notifications as $notif): ?>
                        <div class="notification-item p-3 border-bottom">
                            <div class="d-flex">
                                <div class="notification-icon me-3">
                                    <?php 
                                    $icon = 'fas fa-info-circle';
                                    $color = 'text-info';
                                    switch($notif['Type']) {
                                        case 'claim_approved': $icon = 'fas fa-check-circle'; $color = 'text-success'; break;
                                        case 'claim_rejected': $icon = 'fas fa-times-circle'; $color = 'text-danger'; break;
                                        case 'claim_received': $icon = 'fas fa-hand-paper'; $color = 'text-warning'; break;
                                        case 'item_matched': $icon = 'fas fa-search'; $color = 'text-primary'; break;
                                    }
                                    ?>
                                    <i class="<?php echo $icon . ' ' . $color; ?>"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <p class="mb-1 fw-semibold"><?php echo htmlspecialchars($notif['Title'] ?? 'Notification'); ?></p>
                                    <p class="mb-1 small"><?php echo htmlspecialchars($notif['Message']); ?></p>
                                    <small class="text-muted">
                                        <i class="fas fa-clock me-1"></i><?php echo date('M d, g:i A', strtotime($notif['Created_At'])); ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/app.js"></script>
</body>
</html>
