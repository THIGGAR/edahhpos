<?php
session_start();
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/functions.php';

// Redirect if user is not logged in or not a shop manager
redirectUnlessRole('shop_manager');

// Fetch current settings
$settings = $conn->prepare("SELECT theme, timezone, currency FROM settings WHERE user_id = ?");
$settings->bind_param("i", $_SESSION['user_id']);
$settings->execute();
$current_settings = $settings->get_result()->fetch_assoc();
$settings->close();

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
    $theme = filter_input(INPUT_POST, 'theme', FILTER_SANITIZE_STRING);
    $timezone = filter_input(INPUT_POST, 'timezone', FILTER_SANITIZE_STRING);
    $currency = filter_input(INPUT_POST, 'currency', FILTER_SANITIZE_STRING);

    $stmt = $conn->prepare("INSERT INTO settings (user_id, theme, timezone, currency) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE theme = ?, timezone = ?, currency = ?");
    $stmt->bind_param("issssss", $_SESSION['user_id'], $theme, $timezone, $currency, $theme, $timezone, $currency);
    if ($stmt->execute()) {
        $_SESSION['message'] = "Settings updated successfully";
        $_SESSION['message_type'] = 'success';
    } else {
        $_SESSION['message'] = "Error updating settings";
        $_SESSION['message_type'] = 'error';
    }
    $stmt->close();
    header("Location: settings.php");
    exit;
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
    $new_password = filter_input(INPUT_POST, 'new_password', FILTER_SANITIZE_STRING);
    $confirm_password = filter_input(INPUT_POST, 'confirm_password', FILTER_SANITIZE_STRING);

    if ($new_password !== $confirm_password) {
        $_SESSION['message'] = "Passwords do not match";
        $_SESSION['message_type'] = 'error';
    } elseif (strlen($new_password) < 6) {
        $_SESSION['message'] = "Password must be at least 6 characters long";
        $_SESSION['message_type'] = 'error';
    } else {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
        $stmt->bind_param("si", $hashed_password, $user_id);
        
        if ($stmt->execute()) {
            $_SESSION['message'] = "Password changed successfully";
            $_SESSION['message_type'] = 'success';
        } else {
            $_SESSION['message'] = "Error changing password";
            $_SESSION['message_type'] = 'error';
        }
        $stmt->close();
    }
    header("Location: settings.php");
    exit;
}

// Fetch all users for password change dropdown
$allUsers = getUsers($conn);

// Display session messages if any
$sessionMessage = displaySessionMessage();

// Check for dark mode preference
$darkMode = $_COOKIE['darkMode'] ?? false;
?>

<!DOCTYPE html>
<html lang="en" class="<?php echo $darkMode ? 'dark-mode' : ''; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings | Shop Manager Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4e73df;
            --primary-dark: #3a5bcc;
            --secondary-color: #858796;
            --success-color: #1cc88a;
            --info-color: #36b9cc;
            --warning-color: #f6c23e;
            --danger-color: #e74a3b;
            --light-color: #f8f9fc;
            --dark-color: #5a5c69;
            --sidebar-bg: #1a3a8f;
            --sidebar-text: rgba(255, 255, 255, 0.9);
            --sidebar-hover: rgba(255, 255, 255, 0.15);
            --sidebar-active: rgba(255, 255, 255, 0.25);
            --body-bg: #f5f7fb;
            --card-bg: #ffffff;
            --text-color: #333;
            --header-bg: #ffffff;
            --border-color: #e3e6f0;
        }

        .dark-mode {
            --body-bg: #1a1a2e;
            --card-bg: #16213e;
            --text-color: #e6e6e6;
            --header-bg: #16213e;
            --sidebar-bg: #0f3460;
            --sidebar-text: rgba(255, 255, 255, 0.95);
            --sidebar-hover: rgba(255, 255, 255, 0.2);
            --sidebar-active: rgba(255, 255, 255, 0.3);
            --secondary-color: #a0a4b8;
            --dark-color: #e6e6e6;
            --border-color: #2d3748;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            transition: background-color 0.3s, color 0.3s, border-color 0.3s;
        }

        body {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            background-color: var(--body-bg);
            color: var(--text-color);
            line-height: 1.6;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 2rem;
            background-color: var(--header-bg);
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 100;
            height: 70px;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .mobile-menu-toggle {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--dark-color);
            display: none;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 600;
            font-size: 1.2rem;
            color: var(--dark-color);
        }

        .logo i {
            color: var(--primary-color);
        }

        .theme-toggle {
            background: none;
            border: none;
            font-size: 1.2rem;
            cursor: pointer;
            color: var(--dark-color);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 0.35rem;
            transition: all 0.3s;
        }

        .theme-toggle:hover {
            background-color: rgba(78, 115, 223, 0.1);
        }

        .sidebar {
            width: 250px;
            background-color: var(--sidebar-bg);
            position: fixed;
            top: 70px;
            bottom: 0;
            left: 0;
            transition: all 0.3s;
            z-index: 99;
            overflow-y: auto;
        }

        .sidebar-nav {
            display: flex;
            flex-direction: column;
            padding: 1rem 0;
        }

        .nav-link {
            display: flex;
            align-items: center;
            padding: 0.75rem 1.5rem;
            color: var(--sidebar-text);
            text-decoration: none;
            transition: all 0.3s;
            gap: 0.75rem;
            font-size: 0.95rem;
        }

        .nav-link:hover {
            color: white;
            background-color: var(--sidebar-hover);
        }

        .nav-link.active {
            color: white;
            background-color: var(--sidebar-active);
            font-weight: 600;
        }

        .nav-link i {
            width: 20px;
            text-align: center;
            font-size: 1.1rem;
        }

        .main-content {
            margin-left: 250px;
            margin-top: 70px;
            padding: 2rem;
            flex: 1;
            transition: all 0.3s;
        }

        .content-section {
            display: block;
            animation: fadeIn 0.5s ease-in-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .section-header h1 {
            font-size: 1.75rem;
            color: var(--dark-color);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 0.35rem;
            border: none;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
            font-size: 0.875rem;
        }

        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background-color: var(--primary-dark);
        }

        .btn-warning {
            background-color: var(--warning-color);
            color: white;
        }

        .btn-warning:hover {
            background-color: #e0a800;
        }

        .form-control {
            display: block;
            width: 100%;
            padding: 0.5rem 0.75rem;
            font-size: 0.875rem;
            line-height: 1.5;
            color: var(--text-color);
            background-color: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 0.35rem;
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            outline: 0;
            box-shadow: 0 0 0 0.2rem rgba(78, 115, 223, 0.25);
        }

        .form-group {
            margin-bottom: 1.25rem;
        }

        label {
            display: inline-block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--dark-color);
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .dashboard-widget {
            background-color: var(--card-bg);
            border-radius: 0.35rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
            overflow: hidden;
            border: 1px solid var(--border-color);
        }

        .widget-header {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .widget-header h3 {
            font-size: 1.1rem;
            color: var(--dark-color);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .widget-content {
            padding: 1.5rem;
        }

        .kpi-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .card {
            background-color: var(--card-bg);
            border-radius: 0.35rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
            overflow: hidden;
            display: flex;
            transition: all 0.3s;
            border: 1px solid var(--border-color);
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 0.5rem 1.5rem 0 rgba(58, 59, 69, 0.2);
        }

        .card-icon {
            width: 70px;
            background-color: rgba(78, 115, 223, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: var(--primary-color);
        }

        .card-content {
            flex: 1;
            padding: 1.25rem;
        }

        .card-content h4 {
            font-size: 1rem;
            color: var(--secondary-color);
            margin-bottom: 0.5rem;
        }

        .card-content p {
            font-size: 0.85rem;
            color: var(--secondary-color);
        }

        .card-trend {
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .card-trend.positive {
            color: var(--success-color);
        }

        .toast-container {
            position: fixed;
            top: 1rem;
            right: 1rem;
            z-index: 1090;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .toast {
            padding: 0.75rem 1.25rem;
            border-radius: 0.25rem;
            color: white;
            box-shadow: 0 0.25rem 0.75rem rgba(0, 0, 0, 0.1);
            animation: slideIn 0.3s ease-out;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .toast-success {
            background-color: var(--success-color);
        }

        .toast-error {
            background-color: var(--danger-color);
        }

        .toast-info {
            background-color: var(--info-color);
        }

        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        .overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 98;
            display: none;
        }

        .loading-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            z-index: 1100;
            justify-content: center;
            align-items: center;
        }

        .loading-spinner {
            width: 3rem;
            height: 3rem;
            border: 0.25rem solid rgba(78, 115, 223, 0.2);
            border-radius: 50%;
            border-top-color: var(--primary-color);
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.active {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
            }

            .mobile-menu-toggle {
                display: block;
            }

            .overlay.active {
                display: block;
            }
        }

        @media (max-width: 768px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }

            .kpi-cards {
                grid-template-columns: 1fr;
            }

            .section-header {
                flex-direction: column;
                align-items: flex-start;
            }
        }

        @media (max-width: 576px) {
            .header {
                padding: 1rem;
            }

            .main-content {
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
    </div>

    <!-- Toast Container -->
    <div class="toast-container" id="toastContainer"></div>

    <!-- Mobile Overlay -->
    <div class="overlay" id="overlay"></div>

    <!-- Header -->
    <header class="header">
        <div class="header-left">
            <button class="mobile-menu-toggle" id="mobileMenuToggle" aria-label="Toggle Menu">
                <i class="fas fa-bars"></i>
            </button>
            <div class="logo">
                <i class="fas fa-store"></i>
                <span>Shop Manager Panel</span>
            </div>
        </div>
        <button class="theme-toggle" id="themeToggle">
            <i class="fas <?php echo $darkMode ? 'fa-sun' : 'fa-moon'; ?>"></i>
            <span><?php echo $darkMode ? 'Light Mode' : 'Dark Mode'; ?></span>
        </button>
    </header>

    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <nav class="sidebar-nav">
            <a href="dashboard.php" class="nav-link">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
            <a href="users.php" class="nav-link">
                <i class="fas fa-users"></i>
                <span>Users</span>
            </a>
            <a href="send_quotation.php" class="nav-link">
                <i class="fas fa-file-invoice"></i>
                <span>Send Quotation</span>
            </a>
            <a href="sales_reports.php" class="nav-link">
                <i class="fas fa-chart-line"></i>
                <span>Sales Reports</span>
            </a>
            <a href="inventory_reports.php" class="nav-link">
                <i class="fas fa-boxes"></i>
                <span>Inventory Reports</span>
            </a>
            <a href="settings.php" class="nav-link active">
                <i class="fas fa-cog"></i>
                <span>Settings</span>
            </a>
            <a href="logout.php" class="nav-link">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <section class="content-section active">
            <div class="section-header">
                <h1><i class="fas fa-cog"></i> Settings</h1>
            </div>

            <?php echo $sessionMessage; ?>

            <!-- Settings Grid -->
            <div class="dashboard-grid">
                <!-- General Settings -->
                <div class="dashboard-widget">
                    <div class="widget-header">
                        <h3><i class="fas fa-sliders-h"></i> General Settings</h3>
                    </div>
                    <div class="widget-content">
                        <form method="POST" id="settingsForm">
                            <input type="hidden" name="update_settings" value="1">
                            
                            <div class="form-group">
                                <label for="theme">Theme</label>
                                <select name="theme" id="theme" class="form-control" required>
                                    <option value="light" <?php echo ($current_settings['theme'] ?? 'light') === 'light' ? 'selected' : ''; ?>>Light</option>
                                    <option value="dark" <?php echo ($current_settings['theme'] ?? 'light') === 'dark' ? 'selected' : ''; ?>>Dark</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="timezone">Timezone</label>
                                <select name="timezone" id="timezone" class="form-control" required>
                                    <option value="Africa/Blantyre" <?php echo ($current_settings['timezone'] ?? 'Africa/Blantyre') === 'Africa/Blantyre' ? 'selected' : ''; ?>>Africa/Blantyre</option>
                                    <option value="UTC" <?php echo ($current_settings['timezone'] ?? 'Africa/Blantyre') === 'UTC' ? 'selected' : ''; ?>>UTC</option>
                                    <option value="America/New_York" <?php echo ($current_settings['timezone'] ?? 'Africa/Blantyre') === 'America/New_York' ? 'selected' : ''; ?>>America/New_York</option>
                                    <option value="Europe/London" <?php echo ($current_settings['timezone'] ?? 'Africa/Blantyre') === 'Europe/London' ? 'selected' : ''; ?>>Europe/London</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="currency">Currency</label>
                                <select name="currency" id="currency" class="form-control" required>
                                    <option value="MWK" <?php echo ($current_settings['currency'] ?? 'MWK') === 'MWK' ? 'selected' : ''; ?>>MWK (Malawian Kwacha)</option>
                                    <option value="USD" <?php echo ($current_settings['currency'] ?? 'MWK') === 'USD' ? 'selected' : ''; ?>>USD (US Dollar)</option>
                                    <option value="EUR" <?php echo ($current_settings['currency'] ?? 'MWK') === 'EUR' ? 'selected' : ''; ?>>EUR (Euro)</option>
                                    <option value="GBP" <?php echo ($current_settings['currency'] ?? 'MWK') === 'GBP' ? 'selected' : ''; ?>>GBP (British Pound)</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i>
                                    <span>Save Settings</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- User Password Management -->
                <div class="dashboard-widget">
                    <div class="widget-header">
                        <h3><i class="fas fa-key"></i> User Password Management</h3>
                    </div>
                    <div class="widget-content">
                        <p class="mb-4">Change passwords for any user in the system. This is useful for resetting forgotten passwords or updating security credentials.</p>
                        
                        <form method="POST" id="changePasswordForm">
                            <input type="hidden" name="change_password" value="1">
                            
                            <div class="form-group">
                                <label for="selectUser">Select User</label>
                                <select class="form-control" id="selectUser" name="user_id" required>
                                    <option value="">-- Select User --</option>
                                    <?php foreach ($allUsers as $user): ?>
                                        <option value="<?php echo htmlspecialchars($user['user_id']); ?>">
                                            <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name'] . ' (' . $user['email'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="newPassword">New Password</label>
                                <div style="position: relative;">
                                    <input type="password" class="form-control" id="newPassword" name="new_password" required minlength="6">
                                    <button type="button" class="btn btn-icon" id="toggleNewPassword" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; color: var(--secondary-color);">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <small class="text-muted">Password must be at least 6 characters long</small>
                            </div>

                            <div class="form-group">
                                <label for="confirmPassword">Confirm New Password</label>
                                <div style="position: relative;">
                                    <input type="password" class="form-control" id="confirmPassword" name="confirm_password" required minlength="6">
                                    <button type="button" class="btn btn-icon" id="toggleConfirmPassword" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; color: var(--secondary-color);">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="form-group">
                                <button type="submit" class="btn btn-warning">
                                    <i class="fas fa-key"></i>
                                    <span>Change Password</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- KPI Cards -->
            <div class="kpi-cards">
                <div class="card">
                    <div class="card-icon">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <div class="card-content">
                        <h4>Security</h4>
                        <p>System Security</p>
                        <div class="card-trend positive">All systems secure</div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-icon">
                        <i class="fas fa-database"></i>
                    </div>
                    <div class="card-content">
                        <h4>Database</h4>
                        <p>Connection Active</p>
                        <div class="card-trend positive">Optimal performance</div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-icon">
                        <i class="fas fa-sync-alt"></i>
                    </div>
                    <div class="card-content">
                        <h4>System Status</h4>
                        <p>All Systems Online</p>
                        <div class="card-trend positive">Running smoothly</div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <footer class="footer" style="background: var(--footer-bg); padding: 1rem; text-align: center; color: var(--text-color);">
        <span>© <?php echo date('Y'); ?> Auntie Eddah POS - Quotation Management</span>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const mobileMenuToggle = document.getElementById('mobileMenuToggle');
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('overlay');
            const themeToggle = document.getElementById('themeToggle');
            const themeIcon = themeToggle.querySelector('i');
            const themeText = themeToggle.querySelector('span');

            // Mobile menu toggle
            mobileMenuToggle.addEventListener('click', function() {
                sidebar.classList.toggle('active');
                overlay.classList.toggle('active');
            });

            overlay.addEventListener('click', function() {
                sidebar.classList.remove('active');
                overlay.classList.remove('active');
            });

            // Theme toggle
            themeToggle.addEventListener('click', function() {
                const isDark = document.documentElement.classList.toggle('dark-mode');
                themeIcon.className = isDark ? 'fas fa-sun' : 'fas fa-moon';
                themeText.textContent = isDark ? 'Light Mode' : 'Dark Mode';
                document.cookie = `darkMode=${isDark ? '1' : '0'}; path=/; max-age=${60 * 60 * 24 * 30}`;
                fetch('update_theme.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'theme=' + (isDark ? 'dark' : 'light')
                });
            });

            // Password visibility toggle
            const toggleNewPassword = document.getElementById('toggleNewPassword');
            const toggleConfirmPassword = document.getElementById('toggleConfirmPassword');
            const newPasswordInput = document.getElementById('newPassword');
            const confirmPasswordInput = document.getElementById('confirmPassword');

            toggleNewPassword.addEventListener('click', function() {
                const type = newPasswordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                newPasswordInput.setAttribute('type', type);
                this.querySelector('i').classList.toggle('fa-eye');
                this.querySelector('i').classList.toggle('fa-eye-slash');
            });

            toggleConfirmPassword.addEventListener('click', function() {
                const type = confirmPasswordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                confirmPasswordInput.setAttribute('type', type);
                this.querySelector('i').classList.toggle('fa-eye');
                this.querySelector('i').classList.toggle('fa-eye-slash');
            });

            // Password form validation
            const changePasswordForm = document.getElementById('changePasswordForm');
            changePasswordForm.addEventListener('submit', function(e) {
                const newPassword = newPasswordInput.value;
                const confirmPassword = confirmPasswordInput.value;
                const selectedUser = document.getElementById('selectUser');
                const userName = selectedUser.options[selectedUser.selectedIndex].text;

                if (newPassword !== confirmPassword) {
                    e.preventDefault();
                    showToast('Passwords do not match', 'error');
                    return false;
                }

                if (newPassword.length < 6) {
                    e.preventDefault();
                    showToast('Password must be at least 6 characters long', 'error');
                    return false;
                }

                if (!confirm(`Are you sure you want to change the password for ${userName}?`)) {
                    e.preventDefault();
                    return false;
                }

                showLoading();
            });

            // Settings form submission
            document.getElementById('settingsForm').addEventListener('submit', function(e) {
                e.preventDefault();
                showLoading();
                setTimeout(() => {
                    this.submit();
                    hideLoading();
                    showToast('Settings updated successfully', 'success');
                }, 1000);
            });

            // Real-time password matching feedback
            confirmPasswordInput.addEventListener('input', function() {
                const newPassword = newPasswordInput.value;
                const confirmPassword = this.value;
                
                if (confirmPassword && newPassword !== confirmPassword) {
                    this.style.borderColor = 'var(--danger-color)';
                } else if (confirmPassword && newPassword === confirmPassword) {
                    this.style.borderColor = 'var(--success-color)';
                } else {
                    this.style.borderColor = 'var(--border-color)';
                }
            });

            // Toast notification function
            function showToast(message, type = 'info') {
                const toastContainer = document.getElementById('toastContainer');
                const toast = document.createElement('div');
                toast.className = `toast toast-${type}`;
                toast.innerHTML = `
                    <i class="fas ${type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle'}"></i>
                    ${message}
                `;
                toastContainer.appendChild(toast);
                setTimeout(() => {
                    toast.remove();
                }, 5000);
            }

            // Loading functions
            function showLoading() {
                document.getElementById('loadingOverlay').style.display = 'flex';
            }

            function hideLoading() {
                document.getElementById('loadingOverlay').style.display = 'none';
            }
        });
    </script>
</body>
</html>