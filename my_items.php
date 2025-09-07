<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: auth.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// User's items
$stmt = $db->prepare("SELECT i.*, c.Category_Name, l.Location_Name
                      FROM Item i
                      LEFT JOIN Category c ON i.Category_ID = c.Category_ID
                      LEFT JOIN Location l ON i.Location_ID = l.Location_ID
                      WHERE i.Creator_ID = ?
                      ORDER BY i.Reported_Date DESC");
$stmt->execute([$_SESSION['user_id']]);
$items = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Items - Lost & Found</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body>
    <?php include 'includes/header.php'; ?>

    <div class="container mt-4">
        <h2>My Items</h2>

        <?php if (count($items) > 0): ?>
            <div class="row">
                <?php foreach ($items as $item): ?>
                    <div class="col-md-6 col-lg-4 mb-4">
                        <div class="card h-100">
                            <?php
                            $stmt_img = $db->prepare("SELECT File_URL FROM Attachment WHERE Item_ID = ? LIMIT 1");
                            $stmt_img->execute([$item['Item_ID']]);
                            $image = $stmt_img->fetch();

                            // C count
                            $stmt_claims = $db->prepare("SELECT COUNT(*) as claim_count FROM Claim WHERE Item_ID = ?");
                            $stmt_claims->execute([$item['Item_ID']]);
                            $claim_info = $stmt_claims->fetch();
                            ?>

                            <?php
                            $image_url = getImageWithFallback($image['File_URL'] ?? '');
                            if ($image_url): ?>
                                <img src="<?php echo htmlspecialchars($image_url); ?>" class="card-img-top"
                                    style="height: 200px; object-fit: cover;" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                            <?php else: ?>
                                <div class="card-img-top bg-light d-flex align-items-center justify-content-center"
                                    style="height: 200px;">
                                    <span class="text-muted">No Image</span>
                                </div>
                            <?php endif; ?>

                            <div class="card-body d-flex flex-column">
                                <h6 class="card-title">
                                    <?php echo htmlspecialchars(substr($item['Description'], 0, 80)) . (strlen($item['Description']) > 80 ? '...' : ''); ?>
                                </h6>

                                <div class="mb-2">
                                    <small class="text-muted">
                                        <strong>Category:</strong> <?php echo htmlspecialchars($item['Category_Name']); ?><br>
                                        <strong>Location:</strong> <?php echo htmlspecialchars($item['Location_Name']); ?><br>
                                        <strong>Date:</strong> <?php echo date('M d, Y', strtotime($item['Reported_Date'])); ?>
                                    </small>
                                </div>

                                <div class="mb-2">
                                    <span
                                        class="badge bg-<?php echo $item['Status'] == 'reported' ? 'warning' : ($item['Status'] == 'donated' ? 'success' : 'secondary'); ?>"><?php echo ucfirst($item['Status']); ?></span>
                                    <span
                                        class="badge bg-<?php echo $item['Priority'] == 'high' ? 'danger' : ($item['Priority'] == 'medium' ? 'warning' : 'secondary'); ?>"><?php echo ucfirst($item['Priority']); ?></span>
                                    <?php if ($claim_info['claim_count'] > 0): ?>
                                        <span class="badge bg-info"><?php echo $claim_info['claim_count']; ?> Claims</span>
                                    <?php endif; ?>
                                    <?php
                                    $is_expired = strtotime($item['Expiration_Date']) < time();
                                    $is_month_old = strtotime($item['Reported_Date']) < strtotime('-1 month');
                                    $is_donation_eligible = ($is_expired || $is_month_old) && $item['Status'] == 'reported';
                                    ?>
                                    <?php if ($is_donation_eligible): ?>
                                        <span class="badge bg-info">
                                            <i class="fas fa-heart me-1"></i>Eligible for Donation
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <div class="mt-auto">
                                    <div class="d-grid gap-2">
                                        <a href="view_item.php?id=<?php echo $item['Item_ID']; ?>"
                                            class="btn btn-outline-primary btn-sm">View Details</a>
                                        <?php if ($is_donation_eligible): ?>
                                            <a href="donate_item.php?id=<?php echo $item['Item_ID']; ?>"
                                                class="btn btn-success btn-sm">
                                                <i class="fas fa-heart me-1"></i>Donate
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-info text-center">
                <h5>No items reported yet</h5>
                <p>You haven't reported any lost or found items yet.</p>
                <div class="mt-3">
                    <a href="report_lost.php" class="btn btn-danger me-2">Report Lost Item</a>
                    <a href="report_found.php" class="btn btn-success">Report Found Item</a>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <?php include 'includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>