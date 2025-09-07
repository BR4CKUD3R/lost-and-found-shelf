<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: auth.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get user's claims
$stmt = $db->prepare("SELECT c.*, i.Description, i.Status as Item_Status, u.Name as Owner_Name
                      FROM Claim c
                      LEFT JOIN Item i ON c.Item_ID = i.Item_ID
                      LEFT JOIN User u ON i.Creator_ID = u.User_ID
                      WHERE c.User_ID = ?
                      ORDER BY c.Claim_Date DESC");
$stmt->execute([$_SESSION['user_id']]);
$claims = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Claims - Lost & Found</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body>
    <?php include 'includes/header.php'; ?>

    <div class="container mt-4">
        <h2>My Claims</h2>

        <?php if (count($claims) > 0): ?>
            <div class="row">
                <?php foreach ($claims as $claim): ?>
                    <div class="col-md-12 mb-3">
                        <div class="card">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-8">
                                        <h6><?php echo htmlspecialchars(substr($claim['Description'], 0, 100)) . (strlen($claim['Description']) > 100 ? '...' : ''); ?>
                                        </h6>
                                        <p class="text-muted mb-1">
                                            <strong>Owner:</strong> <?php echo htmlspecialchars($claim['Owner_Name']); ?><br>
                                            <strong>Claimed on:</strong>
                                            <?php echo date('M d, Y g:i A', strtotime($claim['Claim_Date'])); ?>
                                        </p>
                                    </div>
                                    <div class="col-md-4 text-md-end">
                                        <span
                                            class="badge bg-<?php echo $claim['Claim_Status'] == 'pending' ? 'warning' : ($claim['Claim_Status'] == 'approved' ? 'success' : 'danger'); ?> mb-2">
                                            <?php echo ucfirst($claim['Claim_Status']); ?>
                                        </span><br>
                                        <a href="view_item.php?id=<?php echo $claim['Item_ID']; ?>"
                                            class="btn btn-sm btn-outline-primary">View Item</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-info text-center">
                <h5>No claims made yet</h5>
                <p>You haven't claimed any items yet. <a href="browse_items.php">Browse items</a> to find your lost
                    belongings.</p>
            </div>
        <?php endif; ?>
    </div>

    <?php include 'includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>