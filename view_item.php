<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

if (!isset($_SESSION['user_id']) || !isset($_GET['id'])) {
    header("Location: login.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

$item_id = $_GET['id'];

// Item details
$stmt = $db->prepare("SELECT i.*, c.Category_Name, l.Location_Name, u.Name as Creator_Name, u.Email as Creator_Email, u.Phone as Creator_Phone
                      FROM Item i
                      LEFT JOIN Category c ON i.Category_ID = c.Category_ID
                      LEFT JOIN Location l ON i.Location_ID = l.Location_ID
                      LEFT JOIN User u ON i.Creator_ID = u.User_ID
                      WHERE i.Item_ID = ?");
$stmt->execute([$item_id]);
$item = $stmt->fetch();

if (!$item) {
    header("Location: browse_items.php");
    exit();
}

// Attachments
$stmt = $db->prepare("SELECT * FROM Attachment WHERE Item_ID = ?");
$stmt->execute([$item_id]);
$attachments = $stmt->fetchAll();

// Reward/bounty information
$stmt = $db->prepare("SELECT * FROM Reward WHERE Item_ID = ? AND Status = 'offered'");
$stmt->execute([$item_id]);
$reward = $stmt->fetch();

// Checking if user has already claimed this item
$stmt = $db->prepare("SELECT * FROM Claim WHERE Item_ID = ? AND User_ID = ?");
$stmt->execute([$item_id, $_SESSION['user_id']]);
$user_claim = $stmt->fetch();

// Get all claims for this item (for owner)
$stmt = $db->prepare("SELECT c.*, u.Name as Claimant_Name, conv.Conversation_ID 
                      FROM Claim c 
                      LEFT JOIN User u ON c.User_ID = u.User_ID 
                      LEFT JOIN Conversation conv ON c.Claim_ID = conv.Claim_ID
                      WHERE c.Item_ID = ?");
$stmt->execute([$item_id]);
$claims = $stmt->fetchAll();

// Load verification questions/answers for all claims so owner can review claimant answers
$verification_by_claim = [];
if (count($claims) > 0) {
    $claim_ids = array_column($claims, 'Claim_ID');
    // prepare an IN clause with placeholders
    $placeholders = implode(',', array_fill(0, count($claim_ids), '?'));
    $q = $db->prepare("SELECT * FROM Verification_Question WHERE Claim_ID IN ($placeholders) ORDER BY Claim_ID, Question_Order");
    if ($q->execute($claim_ids)) {
        $rows = $q->fetchAll();
        foreach ($rows as $r) {
            $verification_by_claim[$r['Claim_ID']][] = $r;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Item Details - Lost & Found</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body>
    <?php include 'includes/header.php'; ?>

    <div class="container mt-4">
        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h3>Item Details</h3>
                    </div>
                    <div class="card-body">
                        <!-- Image Gallery -->
                        <?php if (count($attachments) > 0): ?>
                            <div class="mb-4">
                                <div class="row">
                                    <?php foreach ($attachments as $attachment): ?>
                                        <?php $image_url = getImageWithFallback($attachment['File_URL']); ?>
                                        <?php if ($image_url): ?>
                                            <div class="col-md-4 mb-2">
                                                <img src="<?php echo htmlspecialchars($image_url); ?>" class="img-fluid rounded"
                                                    style="width: 100%; height: 200px; object-fit: cover;" 
                                                    onerror="this.style.display='none';">
                                            </div>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="mb-3">
                            <h5>Description</h5>
                            <p><?php echo nl2br(htmlspecialchars($item['Description'])); ?></p>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <strong>Category:</strong> <?php echo htmlspecialchars($item['Category_Name']); ?>
                            </div>
                            <div class="col-md-6">
                                <strong>Location:</strong> <?php echo htmlspecialchars($item['Location_Name']); ?>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <strong>Status:</strong>
                                <span
                                    class="badge bg-<?php echo $item['Status'] == 'reported' ? 'warning' : 'success'; ?>"><?php echo ucfirst($item['Status']); ?></span>
                            </div>
                            <div class="col-md-6">
                                <strong>Priority:</strong>
                                <span
                                    class="badge bg-<?php echo $item['Priority'] == 'high' ? 'danger' : ($item['Priority'] == 'medium' ? 'warning' : 'secondary'); ?>"><?php echo ucfirst($item['Priority']); ?></span>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <strong>Reported:</strong>
                                <?php echo date('D M, Y g:i A', strtotime($item['Reported_Date'])); ?>
                            </div>
                            <div class="col-md-6">
                                <strong>Bounty Expires:</strong>
                                <?php echo date('d M, Y', strtotime($item['Expiration_Date'])); ?>
                            </div>
                        </div>

                        <?php if ($reward): ?>
                            <div class="mb-3">
                                <div class="alert alert-warning">
                                    <h6><i></i>Bounty Offered!</h6>
                                    <p class="mb-1">
                                        <strong>Reward:</strong> TK <?php echo number_format($reward['Amount']); ?>
                                        (<?php echo ucfirst(str_replace('_', ' ', $reward['Reward_Type'])); ?>)
                                    </p>
                                    <?php if ($reward['Description']): ?>
                                        <p class="mb-0"><strong>Details:</strong> <?php echo htmlspecialchars($reward['Description']); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if ($item['Creator_ID'] == $_SESSION['user_id']): ?>
                            <?php
                            $is_expired = strtotime($item['Expiration_Date']) < time();
                            $is_month_old = strtotime($item['Reported_Date']) < strtotime('-1 month');
                            $is_donation_eligible = $is_expired || $is_month_old;
                            ?>
                            <?php if ($is_donation_eligible): ?>
                                <div class="mb-3">
                                    <div class="alert alert-info">
                                        <h6><i class="fas fa-heart me-2"></i>Eligible for Donation</h6>
                                        <p class="mb-2">This item is eligible for donation because it has
                                            <?php if ($is_expired): ?>
                                                <strong>expired</strong> on <?php echo date('M d, Y', strtotime($item['Expiration_Date'])); ?>
                                            <?php else: ?>
                                                been <strong>reported for over 1 month</strong>
                                            <?php endif; ?>
                                            and has not been claimed.
                                        </p>
                                        <a href="donate_item.php?id=<?php echo $item['Item_ID']; ?>" class="btn btn-success btn-sm">
                                            <i class="fas fa-heart me-1"></i>Donate Item
                                        </a>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="mb-3">
                                    <div class="alert alert-light">
                                        <h6><i></i>Donation Information</h6>
                                        <p class="mb-0">This item will become eligible for donation after
                                            <?php if ($is_expired): ?>
                                                it expires on <?php echo date('M d, Y', strtotime($item['Expiration_Date'])); ?>
                                            <?php else: ?>
                                                <?php
                                                $one_month_date = date('M d, Y', strtotime($item['Reported_Date'] . ' +1 month'));
                                                echo $one_month_date;
                                                ?> (1 month from report date)
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php if ($item['Creator_ID'] != $_SESSION['user_id']): ?>
                            <div class="mb-3">
                                <strong>Reported by:</strong> <?php echo htmlspecialchars($item['Creator_Name']); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5>Actions</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($item['Creator_ID'] == $_SESSION['user_id']): ?>
                            <!-- Owner actions -->
                            <div class="d-grid gap-2">
                                <a href="edit_item.php?id=<?php echo $item['Item_ID']; ?>"
                                    class="btn btn-outline-primary">Edit Item</a>
                                <a href="delete_item.php?id=<?php echo $item['Item_ID']; ?>" class="btn btn-outline-danger"
                                    onclick="return confirm('Are you sure you want to delete this item?')">Delete Item</a>
                            </div>

                            <?php if (count($claims) > 0): ?>
                                <hr>
                                <h6>Claims (<?php echo count($claims); ?>)</h6>
                                <?php foreach ($claims as $claim): ?>
                                    <div class="border p-2 mb-2 rounded">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <strong><?php echo htmlspecialchars($claim['Claimant_Name']); ?></strong><br>
                                                <small
                                                    class="text-muted"><?php echo date('M d, Y g:i A', strtotime($claim['Claim_Date'])); ?></small><br>
                                                <span
                                                    class="badge bg-<?php echo $claim['Claim_Status'] == 'pending' ? 'warning' : ($claim['Claim_Status'] == 'approved' ? 'success' : 'danger'); ?>">
                                                    <?php echo ucfirst($claim['Claim_Status']); ?>
                                                </span>
                                            </div>
                                            <div>
                                                <a href="chat.php?conversation_id=<?php echo $claim['Conversation_ID'] ?? ''; ?>"
                                                    class="btn btn-primary btn">
                                                    <i class="fas fa-comments"></i> Chat
                                                </a>
                                            </div>
                                        </div>
                                        <?php
                                        // verification questions/answers submitted by claimer
                                        $vids = $verification_by_claim[$claim['Claim_ID']] ?? [];
                                        if (!empty($vids)):
                                        ?>
                                            <div class="mt-2">
                                                <h6 class="mb-1">Verification Answers</h6>
                                                <ul class="list-group">
                                                    <?php foreach ($vids as $v): ?>
                                                        <li class="list-group-item"><strong><?php echo htmlspecialchars($v['Question_Text']); ?></strong><br>
                                                            <span><?php echo htmlspecialchars($v['Answer_Text']); ?></span>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($claim['Claim_Status'] == 'pending'): ?>
                                            <div class="mt-2">
                                                <a href="process_claim.php?id=<?php echo $claim['Claim_ID']; ?>&action=approve"
                                                    class="btn btn-sm btn-success">Approve</a>
                                                <a href="process_claim.php?id=<?php echo $claim['Claim_ID']; ?>&action=reject"
                                                    class="btn btn-sm btn-danger">Reject</a>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>

                        <?php else: ?>
                            <!-- Non-owner actions -->
                            <?php if ($item['Status'] == 'reported'): ?>
                                <?php if ($user_claim): ?>
                                    <div class="alert alert-info">
                                        <strong>You have claimed this item</strong><br>
                                        Status: <span
                                            class="badge bg-<?php echo $user_claim['Claim_Status'] == 'pending' ? 'warning' : ($user_claim['Claim_Status'] == 'approved' ? 'success' : 'danger'); ?>">
                                            <?php echo ucfirst($user_claim['Claim_Status']); ?>
                                        </span>
                                    </div>
                                <?php else: ?>
                                    <div class="d-grid gap-2">
                                        <a href="claim_item.php?id=<?php echo $item['Item_ID']; ?>" class="btn btn-success">Claim
                                            This Item</a>
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="alert alert-warning">This item has already been claimed.</div>
                            <?php endif; ?>
                        <?php endif; ?>

                        <hr>
                        <div class="d-grid gap-2">
                            <a href="browse_items.php" class="btn btn-secondary">Back to Browse</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>
    
</body>

</html>