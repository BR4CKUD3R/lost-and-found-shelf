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

// Getting user info
$stmt = $db->prepare("SELECT * FROM User WHERE User_ID = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

$error = '';
$success = '';
$password_changed = false;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action == 'update_profile') {
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);

        if (empty($name) || empty($email) || empty($phone)) {
            $error = 'Please fill in all fields.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif (!preg_match('/^[0-9]{13}$/', $phone)) {
            $error = 'Phone number must be exactly 13 digits (numbers only).';
        } else {
            try {
                // ! Checking if email or phone is already taken by another user
                $stmt = $db->prepare("SELECT User_ID FROM User WHERE (Email = ? OR Phone = ?) AND User_ID != ?");
                $stmt->execute([$email, $phone, $_SESSION['user_id']]);

                if ($stmt->fetch()) {
                    $error = 'Email or phone number is already taken by another user.';
                } else {
                    $stmt = $db->prepare("UPDATE User SET Name = ?, Email = ?, Phone = ?, Updated_At = CURRENT_TIMESTAMP WHERE User_ID = ?");
                    if ($stmt->execute([$name, $email, $phone, $_SESSION['user_id']])) {
                        $_SESSION['user_name'] = $name;
                        $_SESSION['user_email'] = $email;
                        $success = 'Profile updated successfully!';

                        // Refresh user data
                        $stmt = $db->prepare("SELECT * FROM User WHERE User_ID = ?");
                        $stmt->execute([$_SESSION['user_id']]);
                        $user = $stmt->fetch();
                    } else {
                        $error = 'Failed to update profile. Please try again.';
                    }
                }
            } catch (Exception $e) {
                $error = 'An error occurred while updating your profile.';
            }
        }
    } elseif ($action == 'update_password') {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error = 'Please fill in all password fields.';
        } elseif (strlen($new_password) < 6) {
            $error = 'New password must be at least 6 characters long.';
        } elseif ($new_password !== $confirm_password) {
            $error = 'New passwords do not match.';
        } elseif (!password_verify($current_password, $user['Password_Hash'])) {
            $error = 'Current password is incorrect.';
        } else {
            try {
                $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("UPDATE User SET Password_Hash = ?, Updated_At = CURRENT_TIMESTAMP WHERE User_ID = ?");
                if ($stmt->execute([$new_password_hash, $_SESSION['user_id']])) {
                    $success = 'Password updated successfully!';
                    $password_changed = true;
                } else {
                    $error = 'Failed to update password. Please try again.';
                }
            } catch (Exception $e) {
                $error = 'An error occurred while updating your password.';
            }
        }
    } elseif ($action == 'update_profile_picture') {
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == 0) {
            $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
            $file_extension = strtolower(pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION));

            if (in_array($file_extension, $allowed_types)) {
                $upload_dir = 'uploads/profiles/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }

                $new_filename = 'profile_' . $_SESSION['user_id'] . '_' . time() . '.' . $file_extension;
                $upload_path = $upload_dir . $new_filename;

                if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $upload_path)) {
                    // Delete old profile picture if exists
                    if (!empty($user['Profile_Picture']) && file_exists($user['Profile_Picture'])) {
                        unlink($user['Profile_Picture']);
                    }

                    $stmt = $db->prepare("UPDATE User SET Profile_Picture = ?, Updated_At = CURRENT_TIMESTAMP WHERE User_ID = ?");
                    if ($stmt->execute([$upload_path, $_SESSION['user_id']])) {
                        $success = 'Profile picture updated successfully!';

                        // Refresh user data
                        $stmt = $db->prepare("SELECT * FROM User WHERE User_ID = ?");
                        $stmt->execute([$_SESSION['user_id']]);
                        $user = $stmt->fetch();
                    } else {
                        $error = 'Failed to update profile picture. Please try again.';
                    }
                } else {
                    $error = 'Failed to upload profile picture. Please try again.';
                }
            } else {
                $error = 'Please upload a valid image file (JPG, PNG, or GIF).';
            }
        } else {
            $error = 'Please select a profile picture to upload.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - Lost & Found</title>
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
                        <h3><i class="fas fa-user me-2"></i>My Profile</h3>


                        <!-- Profile Information Form -->
                        <form method="POST">
                            <input type="hidden" name="action" value="update_profile">

                            <h5 class="mb-3">Personal Information</h5>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="name" class="form-label">Full Name</label>
                                    <input type="text" class="form-control" id="name" name="name"
                                        value="<?php echo htmlspecialchars($user['Name']); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label">Email Address</label>
                                    <input type="email" class="form-control" id="email" name="email"
                                        value="<?php echo htmlspecialchars($user['Email']); ?>" required>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="phone" class="form-label">Phone Number</label>
                                    <input type="tel" class="form-control" id="phone" name="phone"
                                        value="<?php echo htmlspecialchars($user['Phone']); ?>"
                                        pattern="[0-9]{13}" maxlength="13" required>
                                    <div class="form-text">Enter 13 digits (numbers only incuding 880) </div>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i>Update Profile
                            </button>
                        </form>

                        <hr class="my-4">

                        <!-- Password Change Form -->
                        <form method="POST">
                            <input type="hidden" name="action" value="update_password">

                            <h5 class="mb-3">Change Password</h5>
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="current_password" class="form-label">Current Password</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                                        <button class="btn btn-outline-secondary" type="button" id="toggle_current" tabindex="-1"><i class="fas fa-eye"></i></button>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="new_password" class="form-label">New Password</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="new_password" name="new_password" minlength="6" required>
                                        <button class="btn btn-outline-secondary" type="button" id="toggle_new" tabindex="-1"><i class="fas fa-eye"></i></button>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" minlength="6" required>
                                        <button class="btn btn-outline-secondary" type="button" id="toggle_confirm" tabindex="-1"><i class="fas fa-eye"></i></button>
                                    </div>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-warning">
                                <i class="fas fa-key me-1"></i>Change Password
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>
        <!---------------------- Password changed modal ----------------------->
        <div class="modal fade" id="passwordChangedModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Password Changed</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        Your password has been changed successfully.
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-primary" data-bs-dismiss="modal">OK</button>
                    </div>
                </div>
            </div>
        </div>
    <script>
        // Password confirmation validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = this.value;

            if (newPassword !== confirmPassword) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });

        // Phone number validation
        document.getElementById('phone').addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '');
        });

        // Password reveal toggles
        (function() {
            function toggle(idInput, idBtn) {
                const input = document.getElementById(idInput);
                const btn = document.getElementById(idBtn);
                if (!input || !btn) return;
                btn.addEventListener('click', function() {
                    if (input.type === 'password') {
                        input.type = 'text';
                        btn.innerHTML = '<i class="fas fa-eye-slash"></i>';
                    } else {
                        input.type = 'password';
                        btn.innerHTML = '<i class="fas fa-eye"></i>';
                    }
                });
            }

            toggle('current_password', 'toggle_current');
            toggle('new_password', 'toggle_new');
            toggle('confirm_password', 'toggle_confirm');
        })();

        // Show modal when password changed successfully
        <?php if ($password_changed): ?>
        (function() {
            var modalEl = document.getElementById('passwordChangedModal');
            if (modalEl) {
                var modal = new bootstrap.Modal(modalEl);
                modal.show();
            } else {
                alert('Password has been changed.');
            }
        })();
        <?php endif; ?>
    </script>
</body>

</html>
