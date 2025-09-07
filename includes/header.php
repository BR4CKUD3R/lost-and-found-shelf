<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<?php require_once __DIR__ . '/functions.php'; ?>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
<nav class="navbar navbar-expand-lg navbar-light bg-white sticky-top">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center" href="index.php">
            <i class="fas fa-search me-2 text-primary"></i>
            <strong>Lost & Found Shelf</strong>
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page == 'index.php' ? 'active' : ''; ?>" href="index.php">
                        <i class="fas fa-home me-1"></i>Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page == 'browse_items.php' ? 'active' : ''; ?>" href="browse_items.php">
                        <i class="fas fa-search me-1"></i>Browse Items
                    </a>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="reportDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-plus me-1"></i>Report Item
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="report_lost.php">
                                <i class="fas fa-exclamation-triangle me-2 text-danger"></i>Report Lost Item
                            </a></li>
                        <li><a class="dropdown-item" href="report_found.php">
                                <i class="fas fa-check-circle me-2 text-success"></i>Report Found Item
                            </a></li>
                    </ul>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page == 'my_items.php' ? 'active' : ''; ?>" href="my_items.php">
                        <i class="fas fa-box me-1"></i>My Items
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page == 'my_claims.php' ? 'active' : ''; ?>" href="my_claims.php">
                        <i class="fas fa-hand-paper me-1"></i>My Claims
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page == 'chat.php' ? 'active' : ''; ?>" href="chat.php">
                        <i class="fas fa-comments me-1"></i>Chat
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page == 'donations.php' ? 'active' : ''; ?>" href="donations.php">
                        <i class="fas fa-heart me-1"></i>Donations
                    </a>
                </li>
                <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] == 'admin'): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page == 'admin_dashboard.php' ? 'active' : ''; ?>" href="admin_dashboard.php">
                            <i class="fas fa-cog me-1"></i>Admin Panel
                        </a>
                    </li>
                <?php endif; ?>
            </ul>

            <ul class="navbar-nav">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                            <div class="user-avatar me-2">
                                <?php
                                // // !Getting user profile picture
                                // $profile_picture = '';
                                // if (isset($_SESSION['user_id'])) {
                                //     try {
                                //         $database = new Database();
                                //         $db = $database->getConnection();
                                //         $stmt = $db->prepare("SELECT Profile_Picture FROM User WHERE User_ID = ?");
                                //         $stmt->execute([$_SESSION['user_id']]);
                                //         $user_data = $stmt->fetch();
                                //         $profile_picture = $user_data['Profile_Picture'] ?? '';
                                //     } catch (Exception $e) {
                                //         // Ignore errors
                                //     }
                                // }
                                
                                $pp = getImageWithFallback($profile_picture ?? '');
                                if (!empty($pp)): ?>
                                    <img src="<?php echo htmlspecialchars($pp); ?>" 
                                         class="rounded-circle" 
                                         style="width: 32px; height: 32px; object-fit: cover;"
                                         alt="Profile Picture">
                                <?php else: ?>
                                    <i class="fas fa-user-circle fa-lg"></i>
                                <?php endif; ?>
                            </div>
                            <?php echo isset($_SESSION['user_name']) ? htmlspecialchars($_SESSION['user_name']) : 'User'; ?>
                            <?php
                            $unread_count = 0;
                            if (isset($_SESSION['user_id'])) {
                                try {
                                    $database = new Database();
                                    $db = $database->getConnection();
                                    $stmt = $db->prepare("SELECT COUNT(*) as count FROM Notification WHERE User_ID = ? AND Is_Read = FALSE");
                                    $stmt->execute([$_SESSION['user_id']]);
                                    $result = $stmt->fetch();
                                    $unread_count = $result['count'];
                                } catch (Exception $e) {            // Ignore notification count errors
                                }
                            }
                            ?>
                            <?php if ($unread_count > 0): ?>
                                <span class="badge bg-danger ms-1"><?php echo $unread_count; ?></span>
                            <?php endif; ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="profile.php">
                                    <i class="fas fa-user me-2"></i>Profile
                                </a></li>
                            <li><a class="dropdown-item" href="notifications.php">
                                    <i class="fas fa-bell me-2"></i>Notifications
                                    <?php if ($unread_count > 0): ?>
                                        <span class="badge bg-danger ms-1"><?php echo $unread_count; ?></span>
                                    <?php endif; ?>
                                </a></li>
                            <li><a class="dropdown-item" href="settings.php">
                                    <i class="fas fa-cog me-2"></i>Settings
                                </a></li>
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li><a class="dropdown-item text-danger" href="auth.php?action=logout">
                                    <i class="fas fa-sign-out-alt me-2"></i>Logout
                                </a></li>
                        </ul>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>