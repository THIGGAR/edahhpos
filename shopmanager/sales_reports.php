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
if ($stmt === false) {
    die("User query prepare failed: " . $conn->error);
}
$stmt->bind_param('i', $user_id);
if (!$stmt->execute()) {
    die("User query execute failed: " . $stmt->error);
}
$user_result = $stmt->get_result();
$user = $user_result->fetch_assoc();
$full_name = $user['first_name'] . ' ' . $user['last_name'];
$stmt->close();

// Load user settings
$settings_query = "SELECT theme FROM settings WHERE user_id = ?";
$stmt = $conn->prepare($settings_query);
if ($stmt === false) {
    die("Settings query prepare failed: " . $conn->error);
}
$stmt->bind_param('i', $user_id);
if (!$stmt->execute()) {
    die("Settings query execute failed: " . $stmt->error);
}
$settings_result = $stmt->get_result();
$settings = $settings_result->fetch_assoc();
$theme = $settings['theme'] ?? 'light';
$stmt->close();

// Fetch available payment methods
$payment_methods_query = "SELECT DISTINCT payment_method FROM orders WHERE payment_method IS NOT NULL";
$payment_methods_result = $conn->query($payment_methods_query);
$payment_methods = [];
while ($row = $payment_methods_result->fetch_assoc()) {
    $payment_methods[] = $row['payment_method'];
}

// Fetch available products
$products_query = "SELECT product_id, name FROM products";
$products_result = $conn->query($products_query);
$products = [];
while ($row = $products_result->fetch_assoc()) {
    $products[] = $row;
}

// Fetch sales data for table
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$payment_method = $_GET['payment_method'] ?? '';
$product_id = $_GET['product_id'] ?? '';
$sales_data_query = "SELECT o.created_at, p.name as product_name, oi.quantity, oi.price as unit_price, 
                            (oi.quantity * oi.price) as total_revenue, o.payment_method 
                     FROM orders o
                     JOIN order_items oi ON o.order_id = oi.order_id
                     JOIN products p ON oi.product_id = p.product_id
                     WHERE o.status = 'completed'";
$params = [];
$param_types = '';
if ($date_from) {
    $sales_data_query .= " AND DATE(o.created_at) >= ?";
    $params[] = $date_from;
    $param_types .= 's';
}
if ($date_to) {
    $sales_data_query .= " AND DATE(o.created_at) <= ?";
    $params[] = $date_to;
    $param_types .= 's';
}
if ($payment_method) {
    $sales_data_query .= " AND o.payment_method = ?";
    $params[] = $payment_method;
    $param_types .= 's';
}
if ($product_id) {
    $sales_data_query .= " AND oi.product_id = ?";
    $params[] = $product_id;
    $param_types .= 'i';
}
$sales_data_query .= " ORDER BY o.created_at DESC LIMIT 10";
$stmt = $conn->prepare($sales_data_query);
if ($stmt === false) {
    die("Sales query prepare failed: " . $conn->error . "<br>Query: " . htmlspecialchars($sales_data_query));
}
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
if (!$stmt->execute()) {
    die("Sales query execute failed: " . $stmt->error);
}
$sales_data_result = $stmt->get_result();
$sales_data = $sales_data_result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch weekly sales data for chart
$weekly_sales_query = "SELECT CONCAT(YEAR(o.created_at), '-W', LPAD(WEEK(o.created_at, 1), 2, '0')) as week, 
                             SUM(oi.quantity * oi.price) as total_revenue
                      FROM orders o
                      JOIN order_items oi ON o.order_id = oi.order_id
                      JOIN products p ON oi.product_id = p.product_id
                      WHERE o.status = 'completed'";
$weekly_params = [];
$weekly_param_types = '';
if ($date_from) {
    $weekly_sales_query .= " AND DATE(o.created_at) >= ?";
    $weekly_params[] = $date_from;
    $weekly_param_types .= 's';
}
if ($date_to) {
    $weekly_sales_query .= " AND DATE(o.created_at) <= ?";
    $weekly_params[] = $date_to;
    $weekly_param_types .= 's';
}
if ($payment_method) {
    $weekly_sales_query .= " AND o.payment_method = ?";
    $weekly_params[] = $payment_method;
    $weekly_param_types .= 's';
}
if ($product_id) {
    $weekly_sales_query .= " AND oi.product_id = ?";
    $weekly_params[] = $product_id;
    $weekly_param_types .= 'i';
}
$weekly_sales_query .= " GROUP BY YEAR(o.created_at), WEEK(o.created_at, 1) ORDER BY o.created_at";
$stmt = $conn->prepare($weekly_sales_query);
if ($stmt === false) {
    die("Weekly sales query prepare failed: " . $conn->error . "<br>Query: " . htmlspecialchars($weekly_sales_query));
}
if (!empty($weekly_params)) {
    $stmt->bind_param($weekly_param_types, ...$weekly_params);
}
if (!$stmt->execute()) {
    die("Weekly sales query execute failed: " . $stmt->error);
}
$weekly_sales_result = $stmt->get_result();
$weekly_sales = $weekly_sales_result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Prepare data for Chart.js
$weeks = [];
$revenues = [];
foreach ($weekly_sales as $week) {
    $weeks[] = $week['week'];
    $revenues[] = (float)$week['total_revenue'];
}
$weeks_json = json_encode($weeks);
$revenues_json = json_encode($revenues);
?>

<!DOCTYPE html>
<html lang="en"<?php if ($theme === 'dark') echo ' class="dark-mode"'; ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Reports - Shop Manager</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    <style>
        :root {
            --primary-color: #4e73df;
            --primary-dark: #3a5bcc;
            --secondary-color: #858796;
            --success-color: #1cc88a;
            --info-color: #36b9cc;
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
            position: relative;
        }

        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background-color: var(--primary-dark);
        }

        .btn-success {
            background-color: var(--success-color);
            color: white;
        }

        .btn-success:hover {
            background-color: #17a673;
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

        .table-container {
            overflow-x: auto;
            margin-top: 2rem;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 0.75rem 1rem;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }

        th {
            background-color: var(--header-bg);
            font-weight: 600;
            color: var(--dark-color);
        }

        tr:hover {
            background-color: var(--sidebar-hover);
        }

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

        .form-group {
            margin-bottom: 1.25rem;
        }

        label {
            display: inline-block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--dark-color);
        }

        .search-filter {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .filter-group {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        .chart-container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 1rem;
            background-color: var(--card-bg);
            border-radius: 0.35rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
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
            .section-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .search-filter {
                flex-direction: column;
                align-items: stretch;
            }

            .filter-group {
                flex-direction: column;
                align-items: stretch;
                gap: 0.75rem;
            }

            .filter-group select,
            .filter-group input,
            .filter-group button {
                width: 100%;
            }

            .chart-container {
                max-width: 100%;
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

            .table-container table {
                font-size: 0.75rem;
            }

            .table-container th,
            .table-container td {
                padding: 0.5rem;
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
            <a href="sales_reports.php" class="nav-link active">
                <i class="fas fa-chart-line"></i>
                <span>Sales Reports</span>
            </a>
            <a href="inventory_reports.php" class="nav-link">
                <i class="fas fa-boxes"></i>
                <span>Inventory Reports</span>
            </a>
            <a href="settings.php" class="nav-link">
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
        <!-- Sales Reports Section -->
        <section class="content-section active">
            <div class="section-header">
                <h1><i class="fas fa-chart-line"></i> Sales Reports</h1>
                <div class="section-actions">
                    <button class="btn btn-primary" id="generateSalesReportBtn">
                        <i class="fas fa-file-export"></i>
                        <span>Generate Report</span>
                    </button>
                    <button class="btn btn-success" id="downloadSalesReportBtn">
                        <i class="fas fa-download"></i>
                        <span>Download CSV</span>
                    </button>
                </div>
            </div>

            <div class="search-filter">
                <div class="filter-group">
                    <label for="salesDateFrom">From Date:</label>
                    <input type="date" class="form-control" id="salesDateFrom" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                    <label for="salesDateTo">To Date:</label>
                    <input type="date" class="form-control" id="salesDateTo" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
                    <label for="paymentMethod">Payment Method:</label>
                    <select class="form-control" id="paymentMethod" name="payment_method">
                        <option value="">All Payment Methods</option>
                        <?php foreach ($payment_methods as $method): ?>
                            <option value="<?php echo htmlspecialchars($method); ?>" <?php echo $payment_method === $method ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($method); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <label for="productId">Product:</label>
                    <select class="form-control" id="productId" name="product_id">
                        <option value="">All Products</option>
                        <?php foreach ($products as $product): ?>
                            <option value="<?php echo htmlspecialchars($product['product_id']); ?>" <?php echo $product_id === $product['product_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($product['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" class="btn btn-primary" onclick="reports.generateSalesReport()">Apply Filters</button>
                </div>
            </div>

            <!-- Weekly Sales Chart -->
            <div class="chart-container">
                <canvas id="weeklySalesChart"></canvas>
            </div>

            <div class="table-container">
                <table id="salesReportTable">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Product</th>
                            <th>Quantity Sold</th>
                            <th>Unit Price</th>
                            <th>Total Revenue</th>
                            <th>Payment Method</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($sales_data)): ?>
                            <tr>
                                <td colspan="6" class="text-center">No sales data available. Use filters to generate report.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($sales_data as $sale): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($sale['created_at']))); ?></td>
                                    <td><?php echo htmlspecialchars($sale['product_name']); ?></td>
                                    <td><?php echo htmlspecialchars($sale['quantity']); ?></td>
                                    <td>MWK <?php echo number_format($sale['unit_price'], 2); ?></td>
                                    <td>MWK <?php echo number_format($sale['total_revenue'], 2); ?></td>
                                    <td><?php echo htmlspecialchars($sale['payment_method']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>

    <!-- Footer -->
   <footer class="footer" style="background: var(--footer-bg); padding: 1rem; text-align: center; color: var(--text-color);">
        <span>© <?php echo date('Y'); ?> Auntie Eddah POS - Quotation Management</span>
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
                fetch('update_theme.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'theme=' + (isDark ? 'dark' : 'light')
                });
                document.cookie = `darkMode=${isDark ? '1' : '0'}; path=/; max-age=${60 * 60 * 24 * 30}`;
                updateChartColors(isDark);
            });

            // Reports functionality
            window.reports = {
                generateSalesReport: function() {
                    showLoading();
                    const dateFrom = document.getElementById('salesDateFrom').value;
                    const dateTo = document.getElementById('salesDateTo').value;
                    const paymentMethod = document.getElementById('paymentMethod').value;
                    const productId = document.getElementById('productId').value;
                    let url = 'sales_reports.php';
                    const params = [];
                    if (dateFrom) params.push(`date_from=${encodeURIComponent(dateFrom)}`);
                    if (dateTo) params.push(`date_to=${encodeURIComponent(dateTo)}`);
                    if (paymentMethod) params.push(`payment_method=${encodeURIComponent(paymentMethod)}`);
                    if (productId) params.push(`product_id=${encodeURIComponent(productId)}`);
                    if (params.length) url += `?${params.join('&')}`;
                    setTimeout(() => {
                        window.location.href = url;
                        hideLoading();
                        showToast('Sales report generated', 'success');
                    }, 1500);
                },

                downloadSalesReport: function() {
                    showLoading();
                    const dateFrom = document.getElementById('salesDateFrom').value;
                    const dateTo = document.getElementById('salesDateTo').value;
                    const paymentMethod = document.getElementById('paymentMethod').value;
                    const productId = document.getElementById('productId').value;
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = 'download_sales_report.php';
                    const inputs = [
                        { name: 'date_from', value: dateFrom },
                        { name: 'date_to', value: dateTo },
                        { name: 'payment_method', value: paymentMethod },
                        { name: 'product_id', value: productId }
                    ];
                    inputs.forEach(input => {
                        if (input.value) {
                            const el = document.createElement('input');
                            el.type = 'hidden';
                            el.name = input.name;
                            el.value = input.value;
                            form.appendChild(el);
                        }
                    });
                    document.body.appendChild(form);
                    form.submit();
                    document.body.removeChild(form);
                    hideLoading();
                }
            };

            // Generate Sales Report button
            document.getElementById('generateSalesReportBtn').addEventListener('click', function() {
                reports.generateSalesReport();
            });

            // Download Sales Report button
            document.getElementById('downloadSalesReportBtn').addEventListener('click', function() {
                reports.downloadSalesReport();
            });

            // Toast notification functions
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

            // Weekly Sales Chart
            const ctx = document.getElementById('weeklySalesChart').getContext('2d');
            const chart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: <?php echo $weeks_json; ?>,
                    datasets: [{
                        label: 'Weekly Revenue (MWK)',
                        data: <?php echo $revenues_json; ?>,
                        backgroundColor: 'rgba(78, 115, 223, 0.5)',
                        borderColor: 'rgba(78, 115, 223, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Revenue (MWK)',
                                color: '<?php echo $theme === 'dark' ? '#e6e6e6' : '#5a5c69'; ?>'
                            },
                            ticks: {
                                color: '<?php echo $theme === 'dark' ? '#e6e6e6' : '#5a5c69'; ?>'
                            },
                            grid: {
                                color: '<?php echo $theme === 'dark' ? 'rgba(255, 255, 255, 0.1)' : 'rgba(0, 0, 0, 0.1)'; ?>'
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Week (Year-Week)',
                                color: '<?php echo $theme === 'dark' ? '#e6e6e6' : '#5a5c69'; ?>'
                            },
                            ticks: {
                                color: '<?php echo $theme === 'dark' ? '#e6e6e6' : '#5a5c69'; ?>'
                            },
                            grid: {
                                display: false
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            labels: {
                                color: '<?php echo $theme === 'dark' ? '#e6e6e6' : '#5a5c69'; ?>'
                            }
                        },
                        title: {
                            display: true,
                            text: 'Weekly Sales Revenue',
                            color: '<?php echo $theme === 'dark' ? '#e6e6e6' : '#5a5c69'; ?>',
                            font: {
                                size: 16
                            }
                        }
                    }
                }
            });

            function updateChartColors(isDark) {
                chart.options.scales.y.title.color = isDark ? '#e6e6e6' : '#5a5c69';
                chart.options.scales.y.ticks.color = isDark ? '#e6e6e6' : '#5a5c69';
                chart.options.scales.y.grid.color = isDark ? 'rgba(255, 255, 255, 0.1)' : 'rgba(0, 0, 0, 0.1)';
                chart.options.scales.x.title.color = isDark ? '#e6e6e6' : '#5a5c69';
                chart.options.scales.x.ticks.color = isDark ? '#e6e6e6' : '#5a5c69';
                chart.options.plugins.legend.labels.color = isDark ? '#e6e6e6' : '#5a5c69';
                chart.options.plugins.title.color = isDark ? '#e6e6e6' : '#5a5c69';
                chart.update();
            }
        });
    </script>
</body>
</html>

<?php
// Close database connection
mysqli_close($conn);
?>