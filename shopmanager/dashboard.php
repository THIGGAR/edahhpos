<?php
session_start();
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/functions.php';

// Redirect if user is not logged in or not a shop manager
redirectUnlessRole('shop_manager');

// Fetch user details
$user_id = $_SESSION['user_id'];
$user_query = "SELECT first_name, last_name FROM users WHERE user_id = ?";
$stmt = $conn->prepare($user_query);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$user_result = $stmt->get_result();
$user = $user_result->fetch_assoc();
$full_name = $user['first_name'] . ' ' . $user['last_name'];
$stmt->close();

// Fetch KPI data
// Today's Sales
$today = date('Y-m-d');
$sales_query = "SELECT SUM(total_amount) as total_sales 
                FROM orders 
                WHERE DATE(created_at) = ? AND status = 'completed'";
$stmt = $conn->prepare($sales_query);
$stmt->bind_param('s', $today);
$stmt->execute();
$sales_result = $stmt->get_result();
$total_sales = $sales_result->fetch_assoc()['total_sales'] ?? 0;
$stmt->close();

// Active Users (users with last_active in the last 24 hours or is_active = 1)
$active_users_query = "SELECT COUNT(*) as active_users 
                       FROM users 
                       WHERE is_active = 1 
                       OR last_active >= NOW() - INTERVAL 1 DAY";
$stmt = $conn->prepare($active_users_query);
$stmt->execute();
$active_users_result = $stmt->get_result();
$active_users = $active_users_result->fetch_assoc()['active_users'] ?? 0;
$stmt->close();

// Pending Orders
$pending_orders_query = "SELECT COUNT(*) as pending_orders 
                        FROM orders 
                        WHERE status = 'pending'";
$stmt = $conn->prepare($pending_orders_query);
$stmt->execute();
$pending_orders_result = $stmt->get_result();
$pending_orders = $pending_orders_result->fetch_assoc()['pending_orders'] ?? 0;
$stmt->close();

// Total Products
$total_products_query = "SELECT COUNT(*) as total_products 
                        FROM products 
                        WHERE is_active = 1";
$stmt = $conn->prepare($total_products_query);
$stmt->execute();
$total_products_result = $stmt->get_result();
$total_products = $total_products_result->fetch_assoc()['total_products'] ?? 0;
$stmt->close();

// Calculate sales trend (compare today with yesterday)
$yesterday = date('Y-m-d', strtotime('-1 day'));
$yesterday_sales_query = "SELECT SUM(total_amount) as yesterday_sales 
                         FROM orders 
                         WHERE DATE(created_at) = ? AND status = 'completed'";
$stmt = $conn->prepare($yesterday_sales_query);
$stmt->bind_param('s', $yesterday);
$stmt->execute();
$yesterday_sales_result = $stmt->get_result();
$yesterday_sales = $yesterday_sales_result->fetch_assoc()['yesterday_sales'] ?? 0;
$stmt->close();

$sales_trend = ($yesterday_sales > 0) 
    ? round((($total_sales - $yesterday_sales) / $yesterday_sales) * 100, 2) 
    : ($total_sales > 0 ? 100 : 0);
$sales_trend_class = ($sales_trend >= 0) ? 'positive' : 'negative';
$sales_trend_text = ($sales_trend >= 0) 
    ? "+$sales_trend% from yesterday" 
    : "$sales_trend% from yesterday";

// Load user settings (e.g., theme)
$settings_query = "SELECT theme FROM settings WHERE user_id = ?";
$stmt = $conn->prepare($settings_query);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$settings_result = $stmt->get_result();
$settings = $settings_result->fetch_assoc();
$theme = $settings['theme'] ?? 'light';
$stmt->close();

// Fetch recent users (last 5 active users)
$recent_users_query = "SELECT first_name, last_name, email, role, last_active 
                       FROM users 
                       WHERE is_active = 1 
                       ORDER BY last_active DESC 
                       LIMIT 5";
$stmt = $conn->prepare($recent_users_query);
$stmt->execute();
$recent_users_result = $stmt->get_result();
$recent_users = $recent_users_result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shop Manager Dashboard - KPI, Sales Overview, and Navigation</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        /* Base Styles */
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
            --footer-bg: #ffffff;
        }

        /* Dark Mode Variables */
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
            --footer-bg: #16213e;
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
            <?php if ($theme === 'dark') echo 'class="dark-mode"'; ?>
        }

        /* Header Styles */
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

        .header-right {
            display: flex;
            align-items: center;
            gap: 1.5rem;
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

        .user-info {
            font-size: 1rem;
            color: var(--dark-color);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 0.35rem;
            background-color: rgba(78, 115, 223, 0.05);
        }

        .theme-toggle {
            background: none;
            border: none;
            font-size: 1rem;
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

        /* Sidebar Styles */
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

        /* Main Content Styles */
        .main-content {
            margin-left: 250px;
            margin-top: 70px;
            padding: 2rem;
            flex: 1;
            transition: all 0.3s;
        }

        .content-section {
            display: none;
        }

        .content-section.active {
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

        /* Button Styles */
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
            position: relative;
        }

        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background-color: var(--primary-dark);
        }

        .btn-primary.loading::after {
            content: '';
            display: inline-block;
            width: 1rem;
            height: 1rem;
            border: 2px solid white;
            border-radius: 50%;
            border-top-color: transparent;
            animation: spin 1s linear infinite;
            margin-left: 0.5rem;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* KPI Cards */
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
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--dark-color);
            margin-bottom: 0.5rem;
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

        .card-trend.negative {
            color: var(--danger-color);
        }

        /* Dashboard Grid */
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

        /* Table Styles for Recent Users */
        .recent-users-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.875rem;
        }

        .recent-users-table th,
        .recent-users-table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .recent-users-table th {
            background-color: rgba(78, 115, 223, 0.05);
            color: var(--dark-color);
            font-weight: 600;
        }

        .recent-users-table td {
            color: var(--text-color);
            max-width: 150px; /* Limit width for long emails */
        }

        .recent-users-table tr:hover {
            background-color: rgba(78, 115, 223, 0.05);
        }

        /* Table Wrapper for Horizontal Scrolling on Mobile */
        .table-wrapper {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        /* Footer Styles */
        .footer {
            background-color: var(--footer-bg);
            padding: 1.5rem 2rem;
            border-top: 1px solid var(--border-color);
            color: var(--secondary-color);
            font-size: 0.875rem;
            text-align: center;
            margin-top: auto; /* Push footer to bottom */
        }

        .footer-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
            max-width: 1200px;
            margin: 0 auto;
        }

        .footer-links {
            display: flex;
            gap: 1.5rem;
        }

        .footer-links a {
            color: var(--primary-color);
            text-decoration: none;
            transition: color 0.3s;
        }

        .footer-links a:hover {
            color: var(--primary-dark);
            text-decoration: underline;
        }

        .footer-copyright {
            font-weight: 500;
        }

        /* Form Styles */
        .form-control {
            display: block;
            width: 100%;
            padding: 0.5rem 0.75rem;
            font-size: 0.875rem;
            line-height: 1.5;
            color: var(--text-color);
            background-color: var(--card-bg);
            background-clip: padding-box;
            border: 1px solid var(--border-color);
            border-radius: 0.35rem;
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        }

        .form-control:focus {
            color: var(--text-color);
            background-color: var(--card-bg);
            border-color: var(--primary-color);
            outline: 0;
            box-shadow: 0 0 0 0.2rem rgba(78, 115, 223, 0.25);
        }

        /* Overlay for mobile menu */
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

        /* Responsive Styles */
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
            .kpi-cards {
                grid-template-columns: 1fr;
            }

            .dashboard-grid {
                grid-template-columns: 1fr;
            }

            .section-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .recent-users-table {
                font-size: 0.75rem;
            }

            .recent-users-table th,
            .recent-users-table td {
                padding: 0.5rem;
                max-width: 100px;
            }

            .recent-users-table th:nth-child(2),
            .recent-users-table td:nth-child(2) {
                display: none; /* Hide Email column on small screens */
            }
        }

        @media (max-width: 576px) {
            .header {
                padding: 1rem;
            }

            .header-right {
                gap: 0.75rem;
                flex-wrap: wrap;
            }

            .main-content {
                padding: 1rem;
            }

            .recent-users-table th,
            .recent-users-table td {
                padding: 0.5rem;
                max-width: 80px;
            }

            .footer-content {
                flex-direction: column;
                text-align: center;
            }

            .footer-links {
                flex-direction: column;
                gap: 0.75rem;
            }
        }
    </style>
</head>
<body>
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
        <div class="header-right">
            <div class="user-info">
                <i class="fas fa-user"></i>
                <span><?php echo htmlspecialchars($full_name); ?></span>
            </div>
            <button class="theme-toggle" id="themeToggle">
                <i class="fas <?php echo $theme === 'dark' ? 'fa-sun' : 'fa-moon'; ?>"></i>
                <span><?php echo $theme === 'dark' ? 'Light Mode' : 'Dark Mode'; ?></span>
            </button>
        </div>
    </header>

    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
   <nav class="sidebar">
    <a href="dashboard.php" class="nav-link">
        <i class="fas fa-tachometer-alt"></i> Dashboard
    </a>
    <a href="users.php" class="nav-link">
        <i class="fas fa-users"></i> Users
    </a>
    <a href="send_quotation.php" class="nav-link">
        <i class="fas fa-file-invoice"></i> Send Quotation
    </a>
    <a href="sales_reports.php" class="nav-link">
        <i class="fas fa-chart-line"></i> Sales Reports
    </a>
    <a href="inventory_reports.php" class="nav-link">
        <i class="fas fa-boxes"></i> Inventory Reports
    </a>
    <a href="settings.php" class="nav-link">
        <i class="fas fa-cog"></i> Settings
    </a>
    <a href="logout.php" class="nav-link">
        <i class="fas fa-sign-out-alt"></i> Logout
    </a>
</nav>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Dashboard Section -->
        <section class="content-section active" id="dashboard">
            <div class="section-header">
                <h1><i class="fas fa-tachometer-alt"></i> Dashboard Overview</h1>
                <div class="section-actions">
                    <button class="btn btn-primary" id="refreshDashboard">
                        <i class="fas fa-sync-alt"></i>
                        <span>Refresh Dashboard</span>
                    </button>
                </div>
            </div>

            <!-- KPI Cards -->
            <div class="kpi-cards">
                <div class="card">
                    <div class="card-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="card-content">
                        <h4>Today's Sales</h4>
                        <p id="todaysSales">MWK <?php echo number_format($total_sales, 2); ?></p>
                        <div class="card-trend <?php echo $sales_trend_class; ?>">
                            <?php echo htmlspecialchars($sales_trend_text); ?>
                        </div>
                    </div>
                </div>
                <div class="card">
                    <div class="card-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="card-content">
                        <h4>Active Users</h4>
                        <p id="activeUsers"><?php echo $active_users; ?></p>
                        <div class="card-trend">No change</div>
                    </div>
                </div>
                <div class="card">
                    <div class="card-icon">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <div class="card-content">
                        <h4>Pending Orders</h4>
                        <p id="pendingOrders"><?php echo $pending_orders; ?></p>
                        <div class="card-trend negative">No change</div>
                    </div>
                </div>
                <div class="card">
                    <div class="card-icon">
                        <i class="fas fa-box"></i>
                    </div>
                    <div class="card-content">
                        <h4>Total Products</h4>
                        <p id="totalProducts"><?php echo $total_products; ?></p>
                        <div class="card-trend positive">+<?php echo $total_products; ?> products</div>
                    </div>
                </div>
            </div>

            <!-- Dashboard Grid -->
            <div class="dashboard-grid">
                <div class="dashboard-widget">
                    <div class="widget-header">
                        <h3><i class="fas fa-chart-bar"></i> Sales Overview</h3>
                        <select class="form-control" style="width: auto;">
                            <option>Today</option>
                            <option>This Week</option>
                            <option>This Month</option>
                        </select>
                    </div>
                    <div class="widget-content">
                        <div style="text-align: center; padding: 40px; color: var(--secondary-color);">
                            <i class="fas fa-chart-bar" style="font-size: 3rem; margin-bottom: 15px; opacity: 0.5;"></i>
                            <p>Sales chart will be displayed here</p>
                        </div>
                    </div>
                </div>
                <div class="dashboard-widget">
                    <div class="widget-header">
                        <h3><i class="fas fa-users"></i> Recent Users</h3>
                    </div>
                    <div class="widget-content">
                        <div class="table-wrapper">
                            <table class="recent-users-table">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>Last Active</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($recent_users)): ?>
                                        <tr>
                                            <td colspan="4" style="text-align: center;">No recent users found.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($recent_users as $recent_user): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($recent_user['first_name'] . ' ' . $recent_user['last_name']); ?></td>
                                                <td><?php echo htmlspecialchars($recent_user['email']); ?></td>
                                                <td><?php echo htmlspecialchars($recent_user['role']); ?></td>
                                                <td><?php echo htmlspecialchars($recent_user['last_active'] ? date('Y-m-d H:i', strtotime($recent_user['last_active'])) : 'Never'); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </section>
      
    </main>
<footer style="text-align: center; padding: 10px 20px; background-color: var(--footer-bg); color: var(--text-color);">
    © <?php echo date('Y'); ?> Auntie Eddah POS - Shop Manager Dashboard
</footer>


    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Mobile menu toggle functionality
            const mobileMenuToggle = document.getElementById('mobileMenuToggle');
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('overlay');

            mobileMenuToggle.addEventListener('click', function() {
                sidebar.classList.toggle('active');
                overlay.classList.toggle('active');
            });

            overlay.addEventListener('click', function() {
                sidebar.classList.remove('active');
                overlay.classList.remove('active');
            });

            // Theme toggle functionality
            const themeToggle = document.getElementById('themeToggle');
            const themeIcon = themeToggle.querySelector('i');
            const themeText = themeToggle.querySelector('span');

            themeToggle.addEventListener('click', function() {
                const isDark = document.documentElement.classList.toggle('dark-mode');
                themeIcon.className = isDark ? 'fas fa-sun' : 'fas fa-moon';
                themeText.textContent = isDark ? 'Light Mode' : 'Dark Mode';
                // Update theme in database via AJAX
                fetch('update_theme.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'theme=' + (isDark ? 'dark' : 'light')
                });
                document.cookie = `darkMode=${isDark ? '1' : '0'}; path=/; max-age=${60 * 60 * 24 * 30}`;
            });

            // Section switching
            const navLinks = document.querySelectorAll('.nav-link[data-section]');
            const contentSections = document.querySelectorAll('.content-section');

            navLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    const sectionId = this.getAttribute('data-section');

                    navLinks.forEach(navLink => navLink.classList.remove('active'));
                    this.classList.add('active');

                    contentSections.forEach(section => section.classList.remove('active'));
                    document.getElementById(sectionId)?.classList.add('active');

                    sidebar.classList.remove('active');
                    overlay.classList.remove('active');
                });
            });

            // Refresh dashboard with loading state
            const refreshButton = document.getElementById('refreshDashboard');
            refreshButton.addEventListener('click', function() {
                refreshButton.classList.add('loading');
                refreshButton.disabled = true;
                setTimeout(() => {
                    window.location.reload();
                }, 500); // Small delay for visual feedback
            });
        });
    </script>
</body>
</html>

<?php
// Close database connection
mysqli_close($conn);
?>