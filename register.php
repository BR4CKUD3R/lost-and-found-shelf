<?php
session_start();
require_once 'config/database.php';

// Redirect if user is already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Validation
    if (empty($name) || empty($email) || empty($phone) || empty($password)) {
        $error = 'Please fill in all fields.';
    } elseif (strlen($name) < 2) {
        $error = 'Name must be at least 2 characters long.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (!preg_match('/^[0-9]{13}$/', $phone)) {
        $error = 'Phone number must be exactly 13 digits (numbers only).';
    } else {
        try {
            $database = new Database();
            $db = $database->getConnection();

            // Checking if email or phone already exists
            $stmt = $db->prepare("SELECT User_ID FROM User WHERE Email = ? OR Phone = ?");
            $stmt->execute([$email, $phone]);

            if ($stmt->fetch()) {
                $error = 'Email or phone number is already registered.';
            } else {
                // Generating unique user ID and hash password
                $user_id = 'user_' . uniqid() . '_' . substr(md5($email), 0, 8);
                $password_hash = password_hash($password, PASSWORD_DEFAULT);

                $stmt = $db->prepare("INSERT INTO User (User_ID, Name, Email, Phone, Password_Hash, Role) VALUES (?, ?, ?, ?, ?, 'user')");

                if ($stmt->execute([$user_id, $name, $email, $phone, $password_hash])) {
                    $success = 'Registration successful! You can now login with your credentials.';

                    // ? Auto-login after registration
                    $_SESSION['user_id'] = $user_id;
                    $_SESSION['user_name'] = $name;
                    $_SESSION['user_email'] = $email;
                    $_SESSION['user_role'] = 'user';
                    $_SESSION['login_time'] = time();

                    // Redirect after 2 seconds
                    header("Refresh: 2; URL=index.php");
                } else {
                    $error = 'Registration failed. Please try again.';
                }
            }
        } catch (Exception $e) {
            $error = 'Registration failed due to a system error. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Lost & Found System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body class="auth-body">
    <div class="auth-container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="auth-card">
                    <div class="text-center mb-4">
                        <div class="auth-logo">
                            <i class="fas fa-user-plus fa-3x text-success"></i>
                        </div>
                        <h2 class="auth-title">Create Account</h2>
                        <p class="auth-subtitle">Join our Lost & Found community</p>
                    </div>

                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                            <div class="mt-2">
                                <small><i class="fas fa-spinner fa-spin me-1"></i>Redirecting to dashboard...</small>
                            </div>
                        </div>
                    <?php endif; ?>

                    <form method="POST" class="auth-form" id="registrationForm">
                        <div class="mb-3">
                            <label for="name" class="form-label">
                                <i class="fas fa-user me-2"></i>Full Name
                            </label>
                            <input type="text" class="form-control form-control-lg" id="name" name="name"
                                value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>"
                                placeholder="Enter your full name" required>
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">
                                <i class="fas fa-envelope me-2"></i>Email Address
                            </label>
                            <input type="email" class="form-control form-control-lg" id="email" name="email"
                                value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                                placeholder="Enter your email" required>
                        </div>

                        <div class="mb-3">
                            <label for="phone" class="form-label">
                                <i class="fas fa-phone me-2"></i>Phone Number
                            </label>
                            <input type="tel" class="form-control form-control-lg" id="phone" name="phone"
                                value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>"
                                placeholder="Enter your phone number" required pattern="[0-9]{13}" maxlength="13" inputmode="numeric">
                            <div class="form-text">Enter exactly 13 digits (numbers only).</div>
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">
                                <i class="fas fa-lock me-2"></i>Password
                            </label>
                            <div class="input-group">
                                <input type="password" class="form-control form-control-lg" id="password" name="password"
                                    placeholder="Create a password (min. 6 characters)" required>
                                <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="password-strength mt-2" id="passwordStrength"></div>
                        </div>

                        <div class="mb-4">
                            <label for="confirm_password" class="form-label">
                                <i class="fas fa-lock me-2"></i>Confirm Password
                            </label>
                            <div class="input-group">
                                <input type="password" class="form-control form-control-lg" id="confirm_password" name="confirm_password"
                                    placeholder="Confirm your password" required>
                                <button class="btn btn-outline-secondary" type="button" id="toggleConfirmPassword">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-success btn-lg w-100 mb-3">
                            <i class="fas fa-user-plus me-2"></i>Create Account
                        </button>
                    </form>

                    <div class="text-center">
                        <p class="mb-0">Already have an account? <a href="auth.php" class="text-decoration-none">Sign in here</a></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Password visibility options for both fields
        function setupPasswordToggle(toggleBtnId, passwordFieldId) {
            document.getElementById(toggleBtnId).addEventListener('click', function() {
                const passwordField = document.getElementById(passwordFieldId);
                const type = passwordField.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordField.setAttribute('type', type);
                this.innerHTML = type === 'password' ? '<i class="fas fa-eye"></i>' : '<i class="fas fa-eye-slash"></i>';
            });
        }
        setupPasswordToggle('togglePassword', 'password');
        setupPasswordToggle('toggleConfirmPassword', 'confirm_password');

        // ! Password strength indicator
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            const strengthDiv = document.getElementById('passwordStrength');
            let strength = 0;

            if (password.length >= 6) strength++;
            if (password.match(/[a-z]/)) strength++;
            if (password.match(/[A-Z]/)) strength++;
            if (password.match(/[0-9]/)) strength++;
            if (password.match(/[^A-Za-z0-9]/)) strength++;

            const strengthLevels = ['Very Weak', 'Weak', 'Fair', 'Good', 'Strong'];
            const strengthColors = ['danger', 'warning', 'info', 'success', 'success'];

            if (password.length > 0) {
                strengthDiv.innerHTML = `<small class="text-${strengthColors[strength-1]}">Password Strength: ${strengthLevels[strength-1] || 'Very Weak'}</small>`;
            } else {
                strengthDiv.innerHTML = '';
            }
        });

        // ! Form validation
        document.getElementById('registrationForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const phone = document.getElementById('phone').value.replace(/\D/g, '');

            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match!');
                return false;
            }

            if (phone.length !== 13) {
                e.preventDefault();
                alert('Phone number must be exactly 13 digits.');
                return false;
            }
        });

        // ! Forcing digits-only and maxlength on phone input
        (function() {
            const phoneInput = document.getElementById('phone');
            if (!phoneInput) return;
            phoneInput.addEventListener('input', function() {
                // remove non-digits
                let v = this.value.replace(/\D/g, '');
                if (v.length > 13) v = v.slice(0,13);
                this.value = v;
            });
        })();
    </script>
</body>

</html>
