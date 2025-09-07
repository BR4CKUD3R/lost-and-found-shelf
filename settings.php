<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: auth.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// User info
$stmt = $db->prepare("SELECT * FROM User WHERE User_ID = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Notification columns exist to avoid notices
$user['Push_Notifications'] = isset($user['Push_Notifications']) ? (int)$user['Push_Notifications'] : 1;


$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action == 'update_notifications') {
        $push_notifications = isset($_POST['push_notifications']) ? 1 : 0;

        // For now, we can show a success message
        $success = 'Notification preferences updated successfully!';
    }

    // Account deletion
    if ($action == 'delete_account') {
        // ! Require confirmation checkbox and typed confirmation
        $confirmed = isset($_POST['confirm_delete']) && $_POST['confirm_text'] === 'DELETE';

        if (!$confirmed) {
            $error = 'Please confirm account deletion by checking the box and typing DELETE.';
        } else {
            try {
                // Delete user and related records by cascade in db
                $db->beginTransaction();
                $stmt = $db->prepare("DELETE FROM User WHERE User_ID = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $db->commit();

                // Destroy session and redirect to login/auth
                $_SESSION = array();
                if (ini_get("session.use_cookies")) {
                    $params = session_get_cookie_params();
                    setcookie(
                        session_name(),
                        '',
                        time() - 42000,
                        $params["path"],
                        $params["domain"],
                        $params["secure"],
                        $params["httponly"]
                    );
                }
                session_destroy();

                header('Location: auth.php?account_deleted=1');
                exit();
            } catch (Exception $e) {
                if ($db->inTransaction()) $db->rollBack();
                $error = 'Failed to delete account. Please contact the administrator.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Lost & Found</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>

<body>
    <?php include 'includes/header.php'; ?>

    <div class="container mt-4">
        <div class="row">
            <div class="col-md-8 mx-auto">
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-cog me-2"></i>Settings</h3>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>

                        <?php if ($success): ?>
                            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                        <?php endif; ?>
                        <!-- Privacy Settings -->
                        <div class="mb-4">
                            <h5 class="mb-3">Privacy Settings</h5>

                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" id="push_notifications" name="push_notifications" <?php echo $user['Push_Notifications'] ? 'checked' : ''; ?> />
                                <label class="form-check-label" for="push_notifications">
                                    <strong>Push Notifications</strong>
                                    <br><small class="text-muted">Receive push notifications in your browser</small>
                                </label>
                            </div>

                            <div class="text-muted small">Preferences are saved automatically when you toggle them.</div>
                        </div>

                        <!-- Data Management -->
                        <div class="mb-4">
                            <div class="list-group">
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <div>
                                            <i class="fas fa-trash me-2"></i>
                                            <strong>Delete Account</strong>
                                            <br><small class="text-muted">Permanently delete your account and all data</small>
                                        </div>
                                    </div>

                                    <form method="POST" id="deleteAccountForm">
                                        <input type="hidden" name="action" value="delete_account">
                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="checkbox" id="confirm_delete" name="confirm_delete">
                                            <label class="form-check-label" for="confirm_delete">I understand this will permanently delete my account</label>
                                        </div>
                                        <div class="mb-2">
                                            <input type="text" name="confirm_text" id="confirm_text" class="form-control form-control-sm" placeholder="Type DELETE to confirm">
                                        </div>
                                        <div class="d-flex justify-content-end">
                                            <button type="submit" id="deleteBtn" class="btn btn-outline-danger btn-sm" disabled>
                                                <i class="fas fa-trash me-1"></i>Delete
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- System Information -->
                        <div class="mb-4">
                            <h5 class="mb-3">System Information</h5>
                            <div class="row">

                            </div>

                            <div class="row mt-2">
                                <div class="col-md-6">
                                    <strong>Member Since:</strong>
                                    <?php echo date('M d, Y', strtotime($user['Created_At'])); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Delete button visible only when confirmation is checked and text is DELETE
        (function() {
            const checkbox = document.getElementById('confirm_delete');
            const confirmText = document.getElementById('confirm_text');
            const deleteBtn = document.getElementById('deleteBtn');

            function update() {
                const ok = checkbox && checkbox.checked && confirmText && confirmText.value === 'DELETE';
                if (deleteBtn) deleteBtn.disabled = !ok;
            }

            if (checkbox) checkbox.addEventListener('change', update);
            if (confirmText) confirmText.addEventListener('input', update);
        })();

        // Persist notification preference changes via Fetch() function calling
        (function() {
            const pushEl = document.getElementById('push_notifications');

            async function sendPrefs(payload) {
                try {
                    const res = await fetch('api/update_preferences.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify(payload)
                    });
                    const data = await res.json();
                    if (!data.success) {
                        console.error('Failed to save preferences', data);
                    }
                } catch (err) {
                    console.error('Error saving preferences', err);
                }
            }
            if (pushEl) pushEl.addEventListener('change', function() {
                sendPrefs({ push: this.checked ? 1 : 0 });
            });
        })();
    </script>
</body>

</html>