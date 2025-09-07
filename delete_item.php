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

// Get item details to verify ownership
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

// Handle deletion
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirm_delete'])) {
    try {
        $db->beginTransaction();

        // Get attachments to delete files
        $stmt = $db->prepare("SELECT File_URL FROM Attachment WHERE Item_ID = ?");
        $stmt->execute([$item_id]);
        $attachments = $stmt->fetchAll();

        // Delete physical files (map stored relative paths to filesystem paths)
        foreach ($attachments as $attachment) {
            $rel = ltrim($attachment['File_URL'], '/');
            if (strpos($rel, 'uploads/') !== 0) {
                $rel = 'uploads/' . $rel;
            }
            $script_dir = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/');
            if ($script_dir === '/') $script_dir = '';
            $fs_base = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', DIRECTORY_SEPARATOR);
            if ($script_dir !== '') {
                $fs_base .= DIRECTORY_SEPARATOR . ltrim(str_replace('/', DIRECTORY_SEPARATOR, $script_dir), DIRECTORY_SEPARATOR);
            }
            $fs_path = $fs_base . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
            if (file_exists($fs_path)) {
                @unlink($fs_path);
            }
        }

        // Delete from database (cascading deletes will handle related records)
        $stmt = $db->prepare("DELETE FROM Item WHERE Item_ID = ? AND Creator_ID = ?");
        $stmt->execute([$item_id, $_SESSION['user_id']]);

        $db->commit();
        $_SESSION['success_message'] = 'Item deleted successfully!';
        header("Location: my_items.php");
        exit();
    } catch (Exception $e) {
        $db->rollback();
        $error_message = 'Error deleting item: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete Item - Lost & Found</title>
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
                    <div class="card-header bg-danger text-white">
                        <h3><i class="fas fa-exclamation-triangle me-2"></i>Delete Item</h3>
                    </div>
                    <div class="card-body">
                        <?php if (isset($error_message)): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error_message); ?>
                            </div>
                        <?php endif; ?>

                        <div class="alert alert-warning">
                            <h5><i class="fas fa-warning me-2"></i>Warning!</h5>
                            <p class="mb-0">You are about to permanently delete this item. This action cannot be undone and will also delete:</p>
                            <ul class="mb-0 mt-2">
                                <li>All associated images and attachments</li>
                                <li>All claims made for this item</li>
                                <li>All chat conversations related to this item</li>
                                <li>All notifications related to this item</li>
                            </ul>
                        </div>

                        <!-- Item Preview -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5>Item to be deleted:</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-8">
                                        <h6><?php echo htmlspecialchars($item['Description']); ?></h6>
                                        <div class="mb-2">
                                            <span class="badge bg-secondary me-1"><?php echo htmlspecialchars($item['Category_Name']); ?></span>
                                            <span class="badge bg-outline-secondary me-1"><?php echo htmlspecialchars($item['Location_Name']); ?></span>
                                            <span class="badge bg-<?php echo $item['Status'] == 'reported' ? 'warning' : 'success'; ?>"><?php echo ucfirst($item['Status']); ?></span>
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
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="confirm_checkbox" required>
                                    <label class="form-check-label" for="confirm_checkbox">
                                        I understand that this action is permanent and cannot be undone
                                    </label>
                                </div>
                            </div>

                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="view_item.php?id=<?php echo $item_id; ?>" class="btn btn-secondary me-md-2">
                                    <i class="fas fa-arrow-left me-2"></i>Cancel
                                </a>
                                <button type="submit" name="confirm_delete" class="btn btn-danger" id="delete_btn" disabled>
                                    <i class="fas fa-trash me-2"></i>Delete Item Permanently
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('confirm_checkbox').addEventListener('change', function() {
            document.getElementById('delete_btn').disabled = !this.checked;
        });
    </script>
</body>

</html>