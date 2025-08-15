<?php
session_start();
require_once __DIR__ . "/db_connect.php";
require_once __DIR__ . "/functions.php";

// Get dashboard stats with error handling
try {
    $stats = getDashboardStats();
} catch (Exception $e) {
    logError("Dashboard stats error: " . $e->getMessage());
    $stats = [
        "total_products" => 0,
        "total_categories" => 0,
        "visible_products" => 0,
        "low_stock" => 0
    ];
}

try {
    $recentProducts = getRecentProducts(5);
} catch (Exception $e) {
    logError("Recent products error: " . $e->getMessage());
    $recentProducts = [];
}

try {
    $categories = getAllProductCategories();
} catch (Exception $e) {
    logError("Categories error: " . $e->getMessage());
    $categories = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Manager Dashboard -EDAHHPOS</title>
    <link rel="stylesheet" href="improved_styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        .form-container {
            max-width: 600px;
            margin: auto;
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .mode-selector {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        .mode-btn {
            flex: 1;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            cursor: pointer;
            text-align: center;
            background: #f8f8f8;
            transition: background-color 0.2s, color 0.2s;
        }
        .mode-btn.active {
            background: #28a745;
            color: #fff;
            border-color: #28a745;
        }
        .mode-btn:hover {
            background: #e0e0e0;
        }
        .form-section {
            display: none;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        .form-section.active {
            display: block !important;
        }
        .form-section h2 {
            margin-top: 0;
            color: #333;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input[type="text"],
        input[type="number"],
        input[type="date"],
        input[type="file"],
        select {
            width: 100%;
            padding: 8px;
            margin-bottom: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
        }
        input[type="checkbox"] {
            margin-right: 5px;
        }
        button[type="submit"] {
            padding: 10px 20px;
            background-color: #28a745;
            color: #fff;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        button[type="submit"]:hover {
            background-color: #218838;
        }
        .error {
            color: red;
            font-size: 0.9em;
            margin-bottom: 10px;
            display: none;
        }
    </style>
    <script src="improved_script.js"></script>
</head>
<body>
    <!-- Loading Overlay -->
    <div id="loadingOverlay" class="loading-overlay">
        <div class="loading-spinner">
            <i class="fas fa-spinner fa-spin"></i>
            <p>Processing...</p>
        </div>
    </div>

    <!-- Toast Container -->
    <div id="toastContainer" class="toast-container"></div>

    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside id="sidebar" class="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <i class="fas fa-store"></i>
                    <span>EDAHH POS</span>
                </div>
                <button id="sidebarToggle" class="sidebar-toggle">
                    <i class="fas fa-bars"></i>
                </button>
            </div>

            <nav class="sidebar-nav">
                <ul>
                    <li class="active">
                        <a href="#dashboard" class="nav-link" data-section="dashboard">
                            <i class="fas fa-tachometer-alt"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li>
                        <a href="#register-goods" class="nav-link" data-section="register-goods">
                            <i class="fas fa-plus-circle"></i>
                            <span>Register Goods</span>
                        </a>
                    </li>
                    <li>
                        <a href="#inventory" class="nav-link" data-section="inventory">
                            <i class="fas fa-boxes"></i>
                            <span>Inventory</span>
                        </a>
                    </li>
                    <li>
                        <a href="#" class="nav-link" data-section="barcode-generator">
                            <i class="fas fa-barcode"></i>
                            <span>Barcode Generator</span>
                        </a>
                    </li>
                    <li>
                        <a href="#reports" class="nav-link" data-section="reports">
                            <i class="fas fa-chart-bar"></i>
                            <span>Reports</span>
                        </a>
                    </li>
                    <li>
                        <a href="#settings" class="nav-link" data-section="settings">
                            <i class="fas fa-cog"></i>
                            <span>Settings</span>
                        </a>
                    </li>
                </ul>
            </nav>

            <div class="sidebar-footer">
                <div class="user-info">
                    <div class="user-avatar">
                        <i class="fas fa-user"></i>
                    </div>
                    <div class="user-details">
                        <span class="user-name"><?php echo htmlspecialchars($_SESSION["username"] ?? "Manager"); ?></span>
                        <span class="user-role">Inventory Manager</span>
                    </div>
                </div>
                <a href="logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <header class="header">
                <div class="header-left">
                    <h1 class="page-title">Dashboard</h1>
                    <div class="breadcrumb">
                        <span>Home</span>
                        <i class="fas fa-chevron-right"></i>
                        <span>Dashboard</span>
                    </div>
                </div>
                <div class="header-right">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" id="globalSearch" placeholder="Search products, categories...">
                    </div>
                    <div class="header-actions">
                        <button class="header-btn" id="notificationBell">
                            <i class="fas fa-bell"></i>
                            <span class="badge">3</span>
                        </button>
                        <div class="user-profile" id="userProfile">
                            <img src="https://via.placeholder.com/40" alt="User Avatar" class="user-avatar">
                            <span><?php echo htmlspecialchars($_SESSION["username"] ?? "Manager"); ?></span>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Content Sections -->
            <div class="content">
                <!-- Dashboard Section -->
                <section id="dashboard-section" class="content-section active">
                    <div class="dashboard-stats">
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-box"></i>
                            </div>
                            <div class="stat-info">
                                <h3 id="totalProducts"><?php echo $stats["total_products"]; ?></h3>
                                <p>Total Products</p>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-tags"></i>
                            </div>
                            <div class="stat-info">
                                <h3 id="totalCategories"><?php echo $stats["total_categories"]; ?></h3>
                                <p>Categories</p>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-eye"></i>
                            </div>
                            <div class="stat-info">
                                <h3 id="visibleProducts"><?php echo $stats["visible_products"]; ?></h3>
                                <p>Visible Products</p>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                            <div class="stat-info">
                                <h3 id="lowStock"><?php echo $stats["low_stock"]; ?></h3>
                                <p>Low Stock Alerts</p>
                            </div>
                        </div>
                    </div>

                    <div class="section-header">
                        <h2>Recent Products</h2>
                        <button id="viewAllProducts" class="btn btn-primary">
                            <i class="fas fa-boxes"></i> View All Products
                        </button>
                    </div>

                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Image</th>
                                    <th>Name</th>
                                    <th>Category</th>
                                    <th>Price</th>
                                    <th>Stock</th>
                                    <th>Created</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentProducts as $product): ?>
                                    <tr>
                                        <td><img src="<?php echo htmlspecialchars($product["image"] ? 'uploads/' . basename($product["image"]) : 'https://via.placeholder.com/50'); ?>" alt="<?php echo htmlspecialchars($product["name"]); ?>" loading="lazy" class="product-image-thumbnail"></td>
                                        <td><?php echo htmlspecialchars($product['name']); ?></td>
                                        <td><?php echo htmlspecialchars($product['category'] ?? 'N/A'); ?></td>
                                        <td>K<?php echo number_format($product['price'], 2); ?></td>
                                        <td><?php echo $product['stock_quantity']; ?></td>
                                        <td><?php echo date('Y-m-d', strtotime($product['created_at'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>

                <!-- Register Goods Section -->
                <section id="register-goods-section" class="content-section">
                    <div class="section-header">
                        <h2>Register Goods</h2>
                        <p>Add or update products in your inventory</p>
                    </div>
                    <div class="form-container">
                        <div class="mode-selector">
                            <button class="mode-btn active" data-mode="new_product">New Product Registration</button>
                            <button class="mode-btn" data-mode="existing_category">Add to Existing Category</button>
                            <button class="mode-btn" data-mode="update_stock">Update Stock</button>
                        </div>
                        <!-- 1. New Product Registration -->
                        <div class="form-section active" data-mode="new_product">
                            <h2>New Product Registration</h2>
                            <form id="newProductForm" enctype="multipart/form-data">
                                <input type="hidden" name="registration_mode" value="new_product">
                                <label for="newCategory">Create New Category:</label>
                                <input type="text" id="newCategory" name="category" placeholder="Enter new category name">
                                <div id="newCategoryError" class="error"></div>

                                <label for="newProductName">Product Name:</label>
                                <input type="text" id="newProductName" name="name" required placeholder="Enter product name">
                                <div id="newProductNameError" class="error"></div>

                                <label for="newProductPrice">Price:</label>
                                <input type="number" id="newProductPrice" name="price" step="0.01" required placeholder="Enter price">
                                <div id="newProductPriceError" class="error"></div>

                                <label for="newProductExpiry">Expiry Date:</label>
                                <input type="date" id="newProductExpiry" name="expiry_date" required>
                                <div id="newProductExpiryError" class="error"></div>

                                <label for="newProductBarcode">Barcode:</label>
                                <input type="text" id="newProductBarcode" name="barcode" required placeholder="Enter or scan barcode">
                                <div id="newProductBarcodeError" class="error"></div>

                                <label for="stockQuantity">Quantity:</label>
                                <input type="number" id="stockQuantity" name="quantity" required min="1" placeholder="Enter quantity to add">
                                <div id="stockQuantityError" class="error"></div>

                                <label for="newProductImage">Upload Product Image:</label>
                                <input type="file" id="newProductImage" name="image" accept="image/*">
                                <div id="newProductImageError" class="error"></div>

                                <label>
                                    <input type="checkbox" id="newProductPromotion" name="is_promotion" value="1"> Enable Promotion
                                </label>
                                <div id="newProductPromotionError" class="error"></div>

                                <label>
                                    <input type="checkbox" id="newProductShowDashboard" name="customer_visible" value="1" checked> Show on Customer Dashboard
                                </label>
                                <div id="newProductShowDashboardError" class="error"></div>

                                <button type="submit">Register Product</button>
                            </form>
                        </div>
                        <!-- 2. Add to Existing Category -->
                        <div class="form-section" data-mode="existing_category">
                            <h2>Add to Existing Category</h2>
                            <form id="existingCategoryForm" enctype="multipart/form-data">
                                <input type="hidden" name="registration_mode" value="new_product">
                                <label for="existingCategory">Select Category:</label>
                                <select id="existingCategory" name="category" required>
                                    <option value="" disabled selected>Select a category</option>
                                </select>
                                <div id="existingCategoryError" class="error"></div>

                                <label for="existingProductName">Product Name:</label>
                                <input type="text" id="existingProductName" name="name" required placeholder="Enter product name">
                                <div id="existingProductNameError" class="error"></div>

                                <label for="existingProductPrice">Price:</label>
                                <input type="number" id="existingProductPrice" name="price" step="0.01" required placeholder="Enter price">
                                <div id="existingProductPriceError" class="error"></div>

                                <label for="existingProductExpiry">Expiry Date:</label>
                                <input type="date" id="existingProductExpiry" name="expiry_date" required>
                                <div id="existingProductExpiryError" class="error"></div>

                                <label for="existingProductBarcode">Barcode:</label>
                                <input type="text" id="existingProductBarcode" name="barcode" required placeholder="Enter or scan barcode">
                                <div id="existingProductBarcodeError" class="error"></div>

                                 <label for="stockQuantity">Quantity:</label>
                                <input type="number" id="stockQuantity" name="quantity" required min="1" placeholder="Enter quantity to add">
                                <div id="stockQuantityError" class="error"></div>

                                <label for="existingProductImage">Upload Product Image:</label>
                                <input type="file" id="existingProductImage" name="image" accept="image/*">
                                <div id="existingProductImageError" class="error"></div>

                                <label>
                                    <input type="checkbox" id="existingProductPromotion" name="is_promotion" value="1"> Enable Promotion
                                </label>
                                <div id="existingProductPromotionError" class="error"></div>

                                <label>
                                    <input type="checkbox" id="existingProductShowDashboard" name="customer_visible" value="1" checked> Show on Customer Dashboard
                                </label>
                                <div id="existingProductShowDashboardError" class="error"></div>

                                <button type="submit">Add Product</button>
                            </form>
                        </div>
                        <!-- 3. Update Stock -->
                        <div class="form-section" data-mode="update_stock">
                            <h2>Update Stock</h2>
                            <form id="updateStockForm">
                                <input type="hidden" name="registration_mode" value="update_stock">
                                <label for="stockCategory">Select Category:</label>
                                <select id="stockCategory" name="category" required>
                                    <option value="" disabled selected>Select a category</option>
                                </select>
                                <div id="stockCategoryError" class="error"></div>

                                <label for="stockProduct">Select Product:</label>
                                <select id="stockProduct" name="product_id" required>
                                    <option value="" disabled selected>Select a product</option>
                                </select>
                                <div id="stockProductError" class="error"></div>

                                <label for="stockBarcode">Barcode:</label>
                                <input type="text" id="stockBarcode" name="verify barcode" required placeholder="Enter or scan barcode">
                                <div id="stockBarcodeError" class="error"></div>

                                <label for="stockQuantity">Quantity:</label>
                                <input type="number" id="stockQuantity" name="quantity" required min="1" placeholder="Enter quantity to add">
                                <div id="stockQuantityError" class="error"></div>

                                <button type="submit">Update Stock</button>
                            </form>
                        </div>
                    </div>
                </section>

                <!-- Inventory Section -->
                <section id="inventory-section" class="content-section">
                    <div class="section-header">
                        <h2>Inventory Management</h2>
                        <p>Manage your product inventory with advanced filtering and search capabilities</p>
                    </div>

                    <div class="inventory-controls">
                        <div class="filters-container">
                            <div class="filter-row">
                                <div class="form-group">
                                    <label for="categoryFilter">Category</label>
                                    <select id="categoryFilter" class="filter-select">
                                        <option value="">All Categories</option>
                                        <?php foreach ($categories as $category): ?>
                                            <option value="<?php echo htmlspecialchars($category['name']); ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="visibilityFilter">Visibility</label>
                                    <select id="visibilityFilter" class="filter-select">
                                    <option value="">All Products</option>
                                    <option value="visible">Visible</option>
                                    <option value="hidden">Hidden</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="promotionFilter">Promotion</label>
                                    <select id="promotionFilter" class="filter-select">
                                    <option value="">All Products</option>
                                    <option value="on_promotion">On Promotion</option>
                                    <option value="regular_price">Regular Price</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="search-row">
                                <div class="form-group search-group">
                                    <label for="inventorySearch">Search Products</label>
                                    <div class="search-input-container">
                                        <i class="fas fa-search search-icon"></i>
                                        <input type="text" id="inventorySearch" placeholder="Search by name, barcode, or description..." class="search-input">
                                        <button type="button" id="clearSearch" class="clear-search-btn" title="Clear search">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="filter-actions">
                                <button id="applyFilters" class="btn btn-primary">
                                    <i class="fas fa-filter"></i> Apply Filters
                                </button>
                                <button id="clearFilters" class="btn btn-secondary">
                                    <i class="fas fa-undo"></i> Clear All
                                </button>
                                <button id="refreshInventory" class="btn btn-info">
                                    <i class="fas fa-sync-alt"></i> Refresh
                                </button>
                            </div>
                        </div>
                        
                        <div class="inventory-stats">
                            <div class="stat-item">
                                <span class="stat-label">Total Products:</span>
                                <span id="totalProductsCount" class="stat-value">0</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-label">Filtered Results:</span>
                                <span id="filteredProductsCount" class="stat-value">0</span>
                            </div>
                        </div>
                    </div>

                    <div class="table-container">
                        <div id="inventoryLoadingIndicator" class="loading-indicator" style="display: none;">
                            <i class="fas fa-spinner fa-spin"></i>
                            <span>Loading inventory...</span>
                        </div>
                        
                        <table class="data-table" id="inventoryTable">
                            <thead>
                                <tr>
                                    <th class="image-column">Image</th>
                                    <th class="sortable" data-sort="name">
                                        Name <i class="fas fa-sort"></i>
                                    </th>
                                    <th class="sortable" data-sort="category">
                                        Category <i class="fas fa-sort"></i>
                                    </th>
                                    <th>Barcode</th>
                                    <th class="sortable" data-sort="price">
                                        Price <i class="fas fa-sort"></i>
                                    </th>
                                    <th class="sortable" data-sort="stock_quantity">
                                        Stock <i class="fas fa-sort"></i>
                                    </th>
                                    <th>Promotion</th>
                                    <th>Visibility</th>
                                    <th class="actions-column">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="inventoryTableBody">
                                <!-- Data will be loaded via JavaScript -->
                            </tbody>
                        </table>
                        
                        <div id="noResultsMessage" class="no-results" style="display: none;">
                            <i class="fas fa-search"></i>
                            <h3>No products found</h3>
                            <p>Try adjusting your filters or search terms</p>
                        </div>
                    </div>
                    
                    <div class="table-pagination" id="inventoryPagination" style="display: none;">
                        <div class="pagination-info">
                            <span>Showing <span id="paginationStart">1</span> to <span id="paginationEnd">10</span> of <span id="paginationTotal">0</span> products</span>
                        </div>
                        <div class="pagination-controls">
                            <button id="prevPage" class="btn btn-sm btn-secondary" disabled>
                                <i class="fas fa-chevron-left"></i> Previous
                            </button>
                            <span id="pageNumbers" class="page-numbers"></span>
                            <button id="nextPage" class="btn btn-sm btn-secondary" disabled>
                                Next <i class="fas fa-chevron-right"></i>
                            </button>
                        </div>
                    </div>
                </section>

                <!-- Barcode Generator Section -->
                <section id="barcode-generator-section" class="content-section">
                    <div class="section-header">
                        <h2>Barcode Generator</h2>
                        <p>Generate barcodes for your products</p>
                    </div>

                    <form id="barcodeGeneratorForm" class="barcode-form">
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="barcodeQuantity">Quantity</label>
                                <input type="number" id="barcodeQuantity" name="quantity" min="1" max="1000" value="1" required>
                            </div>
                            <div class="form-group">
                                <label for="barcodePrefix">Prefix (Optional)</label>
                                <input type="text" id="barcodePrefix" name="prefix" placeholder="e.g., AE">
                            </div>
                            <div class="form-group">
                                <label for="barcodeLabel">Label Text</label>
                                <input type="text" id="barcodeLabel" name="label" placeholder="Product Barcode" value="Product Barcode">
                            </div>
                            <div class="form-group">
                                <label for="downloadFormat">Download Format</label>
                                <select id="downloadFormat" name="format" required>
                                    <option value="pdf">PDF</option>
                                    <option value="excel">Excel</option>
                                    <option value="word">Word</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="button" id="previewBarcodesBtn" class="btn btn-secondary">
                                <i class="fas fa-eye"></i> Preview
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-download"></i> Generate & Download
                            </button>
                        </div>
                    </form>

                    <div id="barcodePreviewContainer" class="barcode-preview">
                        <!-- Preview will be shown here -->
                    </div>
                </section>

                <!-- Reports Section -->
                <section id="reports-section" class="content-section">
                    <div class="section-header">
                        <h2>Reports</h2>
                        <p>Generate comprehensive reports for your inventory</p>
                    </div>

                    <form id="reportsForm" class="reports-form">
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="reportType">Report Type</label>
                                <select id="reportType" name="report_type" required>
                                    <option value="daily">Daily Report</option>
                                    <option value="weekly">Weekly Report</option>
                                    <option value="monthly">Monthly Report</option>
                                    <option value="custom">Custom Date Range</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="reportFormat">Format</label>
                                <select id="reportFormat" name="format" required>
                                    <option value="pdf">PDF</option>
                                    <option value="csv">CSV</option>
                                    <option value="excel">Excel</option>
                                </select>
                            </div>
                        </div>

                        <div id="customDateRange" class="form-grid" style="display: none;">
                            <div class="form-group">
                                <label for="startDate">Start Date</label>
                                <input type="date" id="startDate" name="start_date">
                            </div>
                            <div class="form-group">
                                <label for="endDate">End Date</label>
                                <input type="date" id="endDate" name="end_date">
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-file-alt"></i> Generate Report
                            </button>
                        </div>
                    </form>
                </section>

                <!-- Settings Section -->
                <section id="settings-section" class="content-section">
                    <div class="section-header">
                        <h2>Settings</h2>
                        <p>Configure your inventory management preferences</p>
                    </div>

                    <form id="settingsForm" class="settings-form">
                        <div class="settings-group">
                            <h3>General Settings</h3>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="lowStockThreshold">Low Stock Threshold</label>
                                    <input type="number" id="lowStockThreshold" name="low_stock_threshold" min="1" value="10">
                                </div>
                                <div class="form-group">
                                    <label for="currency">Currency</label>
                                    <select id="currency" name="currency">
                                        <option value="USD">MWK (K)</option>
                                        <option value="EUR">EUR (€)</option>
                                        <option value="GBP">USD ($)</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="settings-group">
                            <h3>Notification Settings</h3>
                            <div class="checkbox-group">
                                <label class="checkbox-label">
                                    <input type="checkbox" name="notify_low_stock" checked>
                                    <span class="checkmark"></span>
                                    Notify on low stock
                                </label>
                                <label class="checkbox-label">
                                    <input type="checkbox" name="notify_new_products" checked>
                                    <span class="checkmark"></span>
                                    Notify on new products
                                </label>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Settings
                            </button>
                        </div>
                    </form>
                </section>
            </div>
        </main>
    </div>
</body>
</html>