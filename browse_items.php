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

// Filters parameters
$category_filter = isset($_GET['category']) ? $_GET['category'] : '';
$location_filter = isset($_GET['location']) ? $_GET['location'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// TODO: Validate filter inputs 
$query = "SELECT i.*, c.Category_Name, l.Location_Name, u.Name as Creator_Name
          FROM Item i
          LEFT JOIN Category c ON i.Category_ID = c.Category_ID
          LEFT JOIN Location l ON i.Location_ID = l.Location_ID
          LEFT JOIN User u ON i.Creator_ID = u.User_ID
          WHERE 1=1"; // Base condition for easier appending

$params = [];

if (!empty($category_filter)) {
    $query .= " AND i.Category_ID = ?";
    $params[] = $category_filter;
}

if (!empty($location_filter)) {
    $query .= " AND i.Location_ID = ?";
    $params[] = $location_filter;
}

if (!empty($status_filter)) {
    $query .= " AND i.Status = ?";
    $params[] = $status_filter;
}

if (!empty($search)) {
    $query .= " AND i.Description LIKE ?";
    $params[] = '%' . $search . '%';
}

$query .= " ORDER BY i.Reported_Date DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$items = $stmt->fetchAll();

// ! Categories and locations for filters
$stmt = $db->prepare("SELECT * FROM Category ORDER BY Category_Name");
$stmt->execute();
$categories = $stmt->fetchAll();

$stmt = $db->prepare("SELECT * FROM Location ORDER BY Location_Name");
$stmt->execute();
$locations = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse Items - Lost & Found</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body>
    <?php include 'includes/header.php'; ?>

    <div class="container mt-4">
        <h2>Browse Items</h2>

        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label for="search" class="form-label">Search</label>
                        <input type="text" class="form-control" id="search" name="search"
                            placeholder="" value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-md-2">
                        <label for="category" class="form-label">Category</label>
                        <select class="form-select" id="category" name="category">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['Category_ID']; ?>" <?php echo $category_filter == $category['Category_ID'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category['Category_Name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="location" class="form-label">Location</label>
                        <select class="form-select" id="location" name="location">
                            <option value="">All Locations</option>
                            <?php foreach ($locations as $location): ?>
                                <option value="<?php echo $location['Location_ID']; ?>" <?php echo $location_filter == $location['Location_ID'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($location['Location_Name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="">All Status</option>
                            <option value="reported" <?php echo $status_filter == 'reported' ? 'selected' : ''; ?>>
                                Available</option>
                            <option value="claimed" <?php echo $status_filter == 'claimed' ? 'selected' : ''; ?>>Claimed
                            </option>
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

        <!-- Items Grids  -->
        <div class="row">
            <?php if (count($items) > 0): ?>
                <?php foreach ($items as $item): ?>
                    <div class="col-md-6 col-lg-4 mb-4">
                        <div class="card h-100">
                            <?php
                            $stmt_img = $db->prepare("SELECT File_URL FROM Attachment WHERE Item_ID = ? LIMIT 1");
                            $stmt_img->execute([$item['Item_ID']]);
                            $image = $stmt_img->fetch();

                            // Get reward/bounty information
                            $stmt_reward = $db->prepare("SELECT * FROM Reward WHERE Item_ID = ? AND Status = 'offered'");
                            $stmt_reward->execute([$item['Item_ID']]);
                            $reward = $stmt_reward->fetch();
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
                                    <?php echo htmlspecialchars(substr($item['Description'], 0, 100)) . (strlen($item['Description']) > 100 ? '...' : ''); ?>
                                </h6>

                                <div class="mb-2">
                                    <small class="text-muted">
                                        <strong>Category:</strong> <?php echo htmlspecialchars($item['Category_Name']); ?><br>
                                        <strong>Location:</strong> <?php echo htmlspecialchars($item['Location_Name']); ?><br>
                                        <strong>Date:</strong> <?php echo date('d M, Y', strtotime($item['Reported_Date'])); ?>
                                    </small>
                                </div>

                                <div class="mb-2">
                                    <span
                                        class="badge bg-<?php echo $item['Status'] == 'reported' ? 'warning' : 'success'; ?>"><?php echo ucfirst($item['Status']); ?></span>
                                    <span
                                        class="badge bg-<?php echo $item['Priority'] == 'high' ? 'danger' : ($item['Priority'] == 'medium' ? 'warning' : 'secondary'); ?>"><?php echo ucfirst($item['Priority']); ?>
                                        Priority</span>
                                    <?php if ($reward): ?>
                                        <span class="badge bg-warning text-dark">
                                            <i class="fas fa-gift me-1"></i>TK. <?php echo number_format($reward['Amount'], 0); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <div class="mt-auto">
                                    <div class="d-grid gap-2">
                                        <a href="view_item.php?id=<?php echo $item['Item_ID']; ?>"
                                            class="btn btn-outline-primary btn-sm">View Details</a>
                                        <?php if ($item['Creator_ID'] != $_SESSION['user_id'] && $item['Status'] == 'reported'): ?>
                                            <a href="claim_item.php?id=<?php echo $item['Item_ID']; ?>"
                                                class="btn btn-success btn-sm">Claim Item</a>
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
                        <h5>No items found</h5>
                        <p>Try adjusting your search filters or <a href="report_lost.php">report a lost item</a> or <a
                                href="report_found.php">report a found item</a>.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>