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

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// ! Query for items eligible for donation
$query = "SELECT i.*, c.Category_Name, l.Location_Name, u.Name as Creator_Name,
                 CASE 
                     WHEN i.Expiration_Date < NOW() THEN 'expired'
                     WHEN i.Reported_Date < DATE_SUB(NOW(), INTERVAL 1 MONTH) THEN 'eligible'
                     ELSE 'not_eligible'
                 END as Donation_Eligibility
          FROM Item i
          LEFT JOIN Category c ON i.Category_ID = c.Category_ID
          LEFT JOIN Location l ON i.Location_ID = l.Location_ID
          LEFT JOIN User u ON i.Creator_ID = u.User_ID
          WHERE i.Status = 'reported' 
          AND (i.Expiration_Date < NOW() OR i.Reported_Date < DATE_SUB(NOW(), INTERVAL 1 MONTH))";

$params = [];

if (!empty($status_filter)) {
    if ($status_filter == 'eligible') {
        $query .= " AND (i.Expiration_Date < NOW() OR i.Reported_Date < DATE_SUB(NOW(), INTERVAL 1 MONTH))";
    } elseif ($status_filter == 'expired') {
        $query .= " AND i.Expiration_Date < NOW()";
    } elseif ($status_filter == 'month_old') {
        $query .= " AND i.Reported_Date < DATE_SUB(NOW(), INTERVAL 1 MONTH) AND i.Expiration_Date >= NOW()";
    }
}

if (!empty($search)) {
    $query .= " AND i.Description LIKE ?";
    $params[] = '%' . $search . '%';
}

$query .= " ORDER BY i.Reported_Date ASC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$items = $stmt->fetchAll();

// User's donations
$stmt = $db->prepare("SELECT d.*, i.Description as Item_Description, i.Item_Type, c.Category_Name, l.Location_Name
                      FROM Donation d
                      LEFT JOIN Item i ON d.Item_ID = i.Item_ID
                      LEFT JOIN Category c ON i.Category_ID = c.Category_ID
                      LEFT JOIN Location l ON i.Location_ID = l.Location_ID
                      WHERE d.Donor_ID = ?
                      ORDER BY d.Donation_Date DESC");
$stmt->execute([$_SESSION['user_id']]);
$user_donations = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Donations - Lost & Found</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>

<body>
    <?php include 'includes/header.php'; ?>

    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-heart me-2"></i>Donations</h2>
                    <div>
                        <a href="#eligible-items" class="btn btn-primary me-2">View Eligible Items</a>
                        <a href="#my-donations" class="btn btn-primary me-2">My Donations</a>
                    </div>
                </div>

                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label for="search" class="form-label">Search Items</label>
                                <input type="text" class="form-control" id="search" name="search"
                                    placeholder="Search description..." value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="status" class="form-label">Eligibility Status</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="">All Eligible</option>
                                    <option value="expired" <?php echo $status_filter == 'expired' ? 'selected' : ''; ?>>Expired Items</option>
                                    <option value="month_old" <?php echo $status_filter == 'month_old' ? 'selected' : ''; ?>>1 Month Old</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">&nbsp;</label>
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary">Filter</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Eligible Items for Donation -->
                <div id="eligible-items" class="mb-5">
                    <h4 class="mb-3">Items Eligible for Donation</h4>
                    <div class="row">
                        <?php if (count($items) > 0): ?>
                            <?php foreach ($items as $item): ?>
                                <div class="col-md-6 col-lg-4 mb-4">
                                    <div class="card h-100">
                                        <?php
                                        $stmt_img = $db->prepare("SELECT File_URL FROM Attachment WHERE Item_ID = ? LIMIT 1");
                                        $stmt_img->execute([$item['Item_ID']]);
                                        $image = $stmt_img->fetch();
                                        ?>

                                        <?php if ($image): ?>
                                            <?php $img_url = require_once 'includes/functions.php'; /* ensure functions available */ ?>
                                            <?php $img_url = getImageWithFallback($image['File_URL'] ?? ''); ?>
                                            <?php if ($img_url): ?>
                                                <img src="<?php echo htmlspecialchars($img_url); ?>" class="card-img-top"
                                                    style="height: 200px; object-fit: cover;" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                            <?php else: ?>
                                                <div class="card-img-top bg-light d-flex align-items-center justify-content-center"
                                                    style="height: 200px;">
                                                    <span class="text-muted">No Image</span>
                                                </div>
                                            <?php endif; ?>
                                            <div class="card-img-top bg-light d-flex align-items-center justify-content-center"
                                                style="height: 200px; display: none;">
                                                <span class="text-muted">No Image</span>
                                            </div>
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
                                                    <strong>Reported:</strong> <?php echo date('M d, Y', strtotime($item['Reported_Date'])); ?><br>
                                                    <strong>Expires:</strong> <?php echo date('M d, Y', strtotime($item['Expiration_Date'])); ?>
                                                </small>
                                            </div>

                                            <div class="mb-2">
                                                <span class="badge bg-<?php echo $item['Donation_Eligibility'] == 'expired' ? 'danger' : 'warning'; ?>">
                                                    <?php echo $item['Donation_Eligibility'] == 'expired' ? 'Expired' : '1 Month Old'; ?>
                                                </span>
                                                <span class="badge bg-<?php echo $item['Priority'] == 'high' ? 'danger' : ($item['Priority'] == 'medium' ? 'warning' : 'secondary'); ?>">
                                                    <?php echo ucfirst($item['Priority']); ?> Priority
                                                </span>
                                            </div>

                                            <div class="mt-auto">
                                                <div class="d-grid gap-2">
                                                    <a href="view_item.php?id=<?php echo $item['Item_ID']; ?>" class="btn btn-outline-primary btn-sm">View Details</a>
                                                    <?php if ($item['Creator_ID'] == $_SESSION['user_id']): ?>
                                                        <a href="donate_item.php?id=<?php echo $item['Item_ID']; ?>" class="btn btn-success btn-sm">
                                                            <i class="fas fa-heart me-1"></i>Donate Item
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="col-12">
                                <div class="alert alert-info text-center">
                                    <h5>No items eligible for donation</h5>
                                    <p>Items become eligible for donation after 1 month or when they expire.</p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- My Donations -->
                <div id="my-donations">
                    <h4 class="mb-3">My Donations</h4>
                    <?php if (count($user_donations) > 0): ?>
                        <div class="row">
                            <?php foreach ($user_donations as $donation): ?>
                                <div class="col-md-6 col-lg-4 mb-4">
                                    <div class="card h-100">
                                        <div class="card-body">
                                            <h6 class="card-title">
                                                <?php echo htmlspecialchars(substr($donation['Item_Description'], 0, 60)) . (strlen($donation['Item_Description']) > 60 ? '...' : ''); ?>
                                            </h6>

                                            <div class="mb-2">
                                                <small class="text-muted">
                                                    <strong>Category:</strong> <?php echo htmlspecialchars($donation['Category_Name']); ?><br>
                                                    <strong>Location:</strong> <?php echo htmlspecialchars($donation['Location_Name']); ?><br>
                                                    <strong>Donated:</strong> <?php echo date('M d, Y', strtotime($donation['Donation_Date'])); ?>
                                                </small>
                                            </div>

                                            <?php if ($donation['Recipient_Organization']): ?>
                                                <div class="mb-2">
                                                    <strong>Organization:</strong> <?php echo htmlspecialchars($donation['Recipient_Organization']); ?>
                                                </div>
                                            <?php endif; ?>

                                            <?php if ($donation['Notes']): ?>
                                                <div class="mb-2">
                                                    <strong>Notes:</strong> <?php echo htmlspecialchars($donation['Notes']); ?>
                                                </div>
                                            <?php endif; ?>

                                            <div class="mt-auto">
                                                <span class="badge bg-success">
                                                    <i class="fas fa-heart me-1"></i>Donated
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info text-center">
                            <h5>No donations yet</h5>
                            <p>You haven't donated any items yet. Your Items will become eligible for donation after 1 month or when they expire.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>