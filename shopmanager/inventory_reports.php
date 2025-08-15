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

// Load user settings
$settings_query = "SELECT theme FROM settings WHERE user_id = ?";
$stmt = $conn->prepare($settings_query);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$settings_result = $stmt->get_result();
$settings = $settings_result->fetch_assoc();
$theme = $settings['theme'] ?? 'light';
$stmt->close();

// Fetch product categories
$categories_query = "SELECT DISTINCT category FROM products WHERE is_active = 1";
$stmt = $conn->prepare($categories_query);
$stmt->execute();
$categories_result = $stmt->get_result();
$categories = [];
while ($row = $categories_result->fetch_assoc()) {
    $categories[] = $row['category'];
}
$stmt->close();

// Fetch inventory data
$category_filter = $_GET['category'] ?? '';
$low_stock_threshold = $_GET['low_stock_threshold'] ?? '';
$inventory_data_query = "SELECT product_id, name, category, price, stock_quantity, 
                               updated_at 
                        FROM products 
                        WHERE is_active = 1";
if ($category_filter) {
    $inventory_data_query .= " AND category = ?";
}
if ($low_stock_threshold) {
    $inventory_data_query .= " AND stock_quantity <= ?";
}
$inventory_data_query .= " ORDER BY stock_quantity ASC LIMIT 10";
$stmt = $conn->prepare($inventory_data_query);
if ($category_filter && $low_stock_threshold) {
    $stmt->bind_param('si', $category_filter, $low_stock_threshold);
} elseif ($category_filter) {
    $stmt->bind_param('s', $category_filter);
} elseif ($low_stock_threshold) {
    $stmt->bind_param('i', $low_stock_threshold);
}
$stmt->execute();
$inventory_data_result = $stmt->get_result();
$inventory_data = $inventory_data_result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en"<?php if ($theme === 'dark') echo ' class="dark-mode"'; ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Reports - Shop Manager</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
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
            <a href="sales_reports.php" class="nav-link">
                <i class="fas fa-chart-line"></i>
                <span>Sales Reports</span>
            </a>
            <a href="inventory_reports.php" class="nav-link active">
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
        <!-- Inventory Reports Section -->
        <section class="content-section active">
            <div class="section-header">
                <h1><i class="fas fa-boxes"></i> Inventory Reports</h1>
                <div class="section-actions">
                    <button class="btn btn-primary" id="generateInventoryReportBtn">
                        <i class="fas fa-file-export"></i>
                        <span>Generate Report</span>
                    </button>
                    <button class="btn btn-success" id="downloadInventoryReportBtn">
                        <i class="fas fa-download"></i>
                        <span>Download CSV</span>
                    </button>
                </div>
            </div>

            <div class="search-filter">
                <div class="filter-group">
                    <label for="inventoryCategoryFilter">Category:</label>
                    <select class="form-control" id="inventoryCategoryFilter" name="category">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $category_filter === $cat ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <label for="lowStockThreshold">Low Stock Threshold:</label>
                    <input type="number" class="form-control" id="lowStockThreshold" name="low_stock_threshold" placeholder="e.g., 10" value="<?php echo htmlspecialchars($low_stock_threshold); ?>">
                    <button type="button" class="btn btn-primary" onclick="reports.generateInventoryReport()">Apply Filters</button>
                </div>
            </div>

            <div class="table-container">
                <table id="inventoryReportTable">
                    <thead>
                        <tr>
                            <th>Product ID</th>
                            <th>Name</th>
                            <th>Category</th>
                            <th>Price</th>
                            <th>Current Stock</th>
                            <th>Stock Status</th>
                            <th>Last Updated</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($inventory_data)): ?>
                            <tr>
                                <td colspan="7" class="text-center">No inventory data available. Use filters to generate report.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($inventory_data as $item): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($item['product_id']); ?></td>
                                    <td><?php echo htmlspecialchars($item['name']); ?></td>
                                    <td><?php echo htmlspecialchars($item['category']); ?></td>
                                    <td>MWK <?php echo number_format($item['price'], 2); ?></td>
                                    <td><?php echo htmlspecialchars($item['stock_quantity']); ?></td>
                                    <td><?php echo $item['stock_quantity'] <= 10 ? 'Low Stock' : 'In Stock'; ?></td>
                                    <td><?php echo htmlspecialchars($item['updated_at'] ? date('Y-m-d H:i', strtotime($item['updated_at'])) : 'N/A'); ?></td>
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
            });

            // Reports functionality
            window.reports = {
                generateInventoryReport: function() {
                    showLoading();
                    const category = document.getElementById('inventoryCategoryFilter').value;
                    const threshold = document.getElementById('lowStockThreshold').value;
                    let url = 'inventory_reports.php';
                    const params = [];
                    if (category) params.push(`category=${encodeURIComponent(category)}`);
                    if (threshold) params.push(`low_stock_threshold=${encodeURIComponent(threshold)}`);
                    if (params.length) url += `?${params.join('&')}`;
                    setTimeout(() => {
                        window.location.href = url;
                        hideLoading();
                        showToast('Inventory report generated', 'success');
                    }, 1500);
                },

                downloadInventoryReport: function() {
                    showLoading();
                    const category = document.getElementById('inventoryCategoryFilter').value;
                    const threshold = document.getElementById('lowStockThreshold').value;
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = 'download_inventory_report.php';
                    if (category) {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'category';
                        input.value = category;
                        form.appendChild(input);
                    }
                    if (threshold) {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'low_stock_threshold';
                        input.value = threshold;
                        form.appendChild(input);
                    }
                    document.body.appendChild(form);
                    form.submit();
                    document.body.removeChild(form);
                    hideLoading();
                }
            };

            // Generate Inventory Report button
            document.getElementById('generateInventoryReportBtn').addEventListener('click', function() {
                reports.generateInventoryReport();
            });

            // Download Inventory Report button
            document.getElementById('downloadInventoryReportBtn').addEventListener('click', function() {
                reports.downloadInventoryReport();
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
        });
    </script>
</body>
</html>

<?php
// Close database connection
mysqli_close($conn);
?>