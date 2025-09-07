<?php
session_start();
require_once 'config/database.php';

// ! Logout via ?action=logout or GET @param
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    $_SESSION = array();                // Clearing all session variables

    // ! Destroing current session cookie after certain period of time
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

    // * Redirect to login (auth.php) with flags to ensure no back mouse click
    header("Location: auth.php?logged_out=1");
    exit();
}

// TODO: If user is already logged in tehn stay away from login landing page
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Please fill in all fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        try {
            $database = new Database();
            $db = $database->getConnection();

            $stmt = $db->prepare("SELECT User_ID, Name, Email, Password_Hash, Role FROM User WHERE Email = ? AND Role IN ('user', 'admin', 'staff')");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['Password_Hash'])) {
                $_SESSION['user_id'] = $user['User_ID'];
                $_SESSION['user_name'] = $user['Name'];
                $_SESSION['user_email'] = $user['Email'];
                $_SESSION['user_role'] = $user['Role'];
                $_SESSION['login_time'] = time();

                // ? Last login info into database
                $stmt = $db->prepare("UPDATE User SET Updated_At = CURRENT_TIMESTAMP WHERE User_ID = ?");
                $stmt->execute([$user['User_ID']]);

                // TODO: Redirect to home after successful login
                header("Location: index.php");
                exit();
            } else {
                $error = 'Invalid email or password.';
            }
        } catch (Exception $e) {
            $error = 'Login failed. Please try again.';
        }
    }
}


?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Lost & Found System</title>
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
                            <i class="fas fa-search fa-3x text-primary"></i>
                        </div>
                        <h2 class="auth-title">Lost & Found Shelf</h2>
                        <p class="auth-subtitle">A Communty Dependent System</p>
                    </div>
                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show auto-dismiss" role="alert">
                            <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                        <div class="alert alert-success alert-dismissible fade show auto-dismiss" role="alert">
                            <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <form method="POST" class="auth-form">
                        <div class="mb-3">
                            <label for="email" class="form-label">
                                <i class="fas fa-envelope me-2"></i>Email Address
                            </label>

                            <script>
                                document.addEventListener('DOMContentLoaded', function() {
                                    // ! Dismissing alerts after 25 seconds
                                    setTimeout(function() {
                                        document.querySelectorAll('.auto-dismiss').forEach(function(alert) {
                                            try {
                                                alert.classList.remove('show');
                                                alert.classList.add('d-none');
                                            } catch (e) {
                                                if (alert && alert.parentNode) alert.parentNode.removeChild(alert);
                                            }
                                        });
                                    }, 25000);
                                });
                            </script>
                            <input type="email" class="form-control form-control-lg" id="email" name="email"
                                value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                                placeholder="Enter your email" required>
                        </div>

                        <div class="mb-4">
                            <label for="password" class="form-label">
                                <i class="fas fa-lock me-2"></i>Password
                            </label>
                            <div class="input-group">
                                <input type="password" class="form-control form-control-lg" id="password" name="password"
                                    placeholder="Enter your password" required>
                                <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>

                        </div>

                        <button type="submit" class="btn btn-primary btn-lg w-100 mb-3">
                            <i class="fas fa-sign-in-alt me-2"></i>Sign In
                        </button>
                    </form>

                    <div class="text-center">
                        <p class="mb-3">Don't have an account? <a href="register.php" class="text-decoration-none">Register here</a></p>

                        <div class="demo-credentials text-center">
                            <p class="small text-muted mb-2">Demo Credentials:</p>
                            <div class="row justify-content-center">
                                <div class="col-6">
                                    <div class="demo-card">
                                        <strong>User</strong><br>
                                        <small>user@lostandfound.com</small><br>
                                        <small>user123</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle password visibility
        document.getElementById('togglePassword').addEventListener('click', function() {
            const password = document.getElementById('password');
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            this.innerHTML = type === 'password' ? '<i class="fas fa-eye"></i>' : '<i class="fas fa-eye-slash"></i>';
        });
    </script>
</body>

</html>
