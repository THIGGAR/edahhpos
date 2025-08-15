<?php
session_start();

require_once 'db_connect.php';
require_once 'functions.php';

// Ensure user_email and user_name are set
if (!isset($_SESSION['user_email'])) {
    $_SESSION['user_email'] = 'cashier@example.com';
}
if (!isset($_SESSION['user_name'])) {
    $_SESSION['user_name'] = 'Cashier';
}

// Initialize cart if not exists
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Generate CSRF token if not set
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = generateCsrfToken();
}

// Determine which section to display
$section = filter_input(INPUT_GET, 'section', FILTER_SANITIZE_STRING) ?? 'dashboard';

// Handle AJAX requests
if (isset($_POST['ajax'])) {
    header('Content-Type: application/json');

    if ($_POST['ajax'] === 'store_cart_data') {
        $csrf_token = sanitizeInput($_POST['csrf_token'] ?? '');
        $cart_data = json_decode($_POST['cart_data'] ?? '{}', true);
        $transaction_ref = sanitizeInput($_POST['transaction_ref'] ?? '');

        if (!validateCsrfToken($csrf_token)) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Invalid security token'
            ]);
            exit;
        }

        if (empty($cart_data) || empty($transaction_ref)) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Invalid cart data or transaction reference'
            ]);
            exit;
        }

        $_SESSION['cart_data'] = $cart_data;
        $_SESSION['transaction_ref'] = $transaction_ref;

        echo json_encode([
            'status' => 'success',
            'message' => 'Cart data stored in session'
        ]);
        exit;
    } elseif ($_POST['ajax'] === 'add_to_cart') {
        $csrf_token = sanitizeInput($_POST['csrf_token'] ?? '');
        $barcode = sanitizeInput($_POST['barcode'] ?? '');

        if (!validateCsrfToken($csrf_token)) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Invalid security token. Please try again.',
                'message_type' => 'danger'
            ]);
            exit;
        }

        if (empty($barcode)) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Please enter a barcode',
                'message_type' => 'danger'
            ]);
            exit;
        }

        $products = getProductByBarcode($barcode);

        if (!empty($products)) {
            $product = $products[0];

            // Check if product already in cart
            $item_index = -1;
            foreach ($_SESSION['cart'] as $index => $item) {
                if ($item['product_id'] == $product['product_id']) {
                    $item_index = $index;
                    break;
                }
            }

            if ($item_index >= 0) {
                // Update existing item quantity
                $_SESSION['cart'][$item_index]['quantity'] += 1;
            } else {
                // Add new item to cart
                $_SESSION['cart'][] = [
                    'product_id' => $product['product_id'],
                    'name' => $product['name'],
                    'price' => $product['price'],
                    'quantity' => 1,
                    'barcodes' => $barcode
                ];
            }

            // Calculate cart total for response
            $cart_total = 0;
            foreach ($_SESSION['cart'] as $item) {
                $cart_total += $item['price'] * $item['quantity'];
            }

            echo json_encode([
                'status' => 'success',
                'message' => "{$product['name']} (MWK" . number_format($product['price'], 2) . ") added to cart",
                'message_type' => 'success',
                'cart_total' => number_format($cart_total, 2),
                'cart_count' => count($_SESSION['cart'])
            ]);
            exit;
        }

        echo json_encode([
            'status' => 'error',
            'message' => "Product not found for barcode: " . htmlspecialchars($barcode),
            'message_type' => 'danger'
        ]);
        exit;
    } elseif ($_POST['ajax'] === 'cart_operation') {
        $operation = sanitizeInput($_POST['operation'] ?? '');

        if ($operation === 'clear') {
            $_SESSION['cart'] = [];
            unset($_SESSION['cart_data'], $_SESSION['transaction_ref']);
            echo json_encode([
                'status' => 'success',
                'message' => 'Cart cleared successfully',
                'cart_total' => '0.00',
                'cart_count' => 0
            ]);
            exit;
        }

        if ($operation === 'remove_item') {
            $item_index = intval($_POST['item_index'] ?? -1);
            if (isset($_SESSION['cart'][$item_index])) {
                $item_name = $_SESSION['cart'][$item_index]['name'];
                unset($_SESSION['cart'][$item_index]);
                $_SESSION['cart'] = array_values($_SESSION['cart']);

                $cart_total = 0;
                foreach ($_SESSION['cart'] as $item) {
                    $cart_total += $item['price'] * $item['quantity'];
                }

                echo json_encode([
                    'status' => 'success',
                    'message' => "Removed {$item_name} from cart",
                    'cart_total' => number_format($cart_total, 2),
                    'cart_count' => count($_SESSION['cart'])
                ]);
                exit;
            }
        }

        if ($operation === 'update_quantity') {
            $item_index = intval($_POST['item_index'] ?? -1);
            $new_quantity = intval($_POST['quantity'] ?? 0);

            if (isset($_SESSION['cart'][$item_index]) && $new_quantity > 0) {
                $_SESSION['cart'][$item_index]['quantity'] = $new_quantity;

                $cart_total = 0;
                foreach ($_SESSION['cart'] as $item) {
                    $cart_total += $item['price'] * $item['quantity'];
                }

                echo json_encode([
                    'status' => 'success',
                    'message' => 'Quantity updated',
                    'cart_total' => number_format($cart_total, 2)
                ]);
                exit;
            }
        }

        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid operation'
        ]);
        exit;
    } elseif ($_POST['ajax'] === 'process_payment') {
        $payment_method = sanitizeInput($_POST['payment_method'] ?? '');
        $tx_ref = sanitizeInput($_POST['tx_ref'] ?? '');
        $csrf_token = sanitizeInput($_POST['csrf_token'] ?? '');

        if (!validateCsrfToken($csrf_token)) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Invalid security token'
            ]);
            exit;
        }

        if (empty($_SESSION['cart'])) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Cart is empty'
            ]);
            exit;
        }

        if (empty($payment_method)) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Please select a payment method'
            ]);
            exit;
        }

        $total = 0;
        foreach ($_SESSION['cart'] as $item) {
            $total += $item['price'] * $item['quantity'];
        }

        $order_id = createOrder($_SESSION['user_id'], $_SESSION['cart'], $payment_method, $total);

        if ($order_id) {
            if ($payment_method === 'cash') {
                $payment_result = confirmPayment($order_id);
                if ($payment_result['success']) {
                    $_SESSION['cart'] = [];
                    unset($_SESSION['cart_data'], $_SESSION['transaction_ref']);
                    echo json_encode([
                        'status' => 'success',
                        'message' => "Order #$order_id completed successfully. Total: MWK" . number_format($total, 2),
                        'order_id' => $order_id,
                        'total' => number_format($total, 2),
                        'redirect' => 'completed_orders.php'
                    ]);
                    exit;
                } else {
                    echo json_encode([
                        'status' => 'error',
                        'message' => "Failed to process payment: {$payment_result['error']}"
                    ]);
                    exit;
                }
            } elseif ($payment_method === 'Mobile Transfer') {
                if ($tx_ref) {
                    // Verify payment
                    $payment_status = verifyPaychanguPayment($tx_ref);
                    if ($payment_status['status'] === 'success' && $payment_status['data']['status'] === 'success') {
                        // Update orders table
                        updateOrderStatus($order_id, 'completed');

                        // Insert into payments table
                        insertPaymentRecord(
                            $order_id,
                            $tx_ref,
                            $_SESSION['user_email'],
                            $_SESSION['user_name'],
                            '', // Last name not available
                            $total,
                            'success',
                            $payment_method
                        );

                        $_SESSION['cart'] = [];
                        unset($_SESSION['cart_data'], $_SESSION['transaction_ref']);
                        echo json_encode([
                            'status' => 'success',
                            'message' => "Order #$order_id completed successfully. Total: MWK" . number_format($total, 2),
                            'order_id' => $order_id,
                            'total' => number_format($total, 2),
                            'redirect' => 'completed_orders.php'
                        ]);
                        exit;
                    } else {
                        echo json_encode([
                            'status' => 'error',
                            'message' => "Payment verification failed: " . ($payment_status['message'] ?? 'Unknown error')
                        ]);
                        exit;
                    }
                } else {
                    // Initiate mobile payment
                    $tx_ref = 'PA' . $order_id . time();
                    $_SESSION['pending_order_id'] = $order_id;
                    echo json_encode([
                        'status' => 'pending',
                        'message' => "Initiating mobile payment for Order #$order_id",
                        'order_id' => $order_id,
                        'total' => number_format($total, 2),
                        'tx_ref' => $tx_ref,
                        'pending' => true
                    ]);
                    exit;
                }
            }
        } else {
            echo json_encode([
                'status' => 'error',
                'message' => 'Failed to create order. Please try again.'
            ]);
            exit;
        }
    } elseif ($_POST['ajax'] === 'confirm_correction') {
        $order_id = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);
        $csrf_token = sanitizeInput($_POST['csrf_token'] ?? '');

        $response = [
            'status' => 'error',
            'message' => 'Failed to confirm correction.',
            'message_type' => 'danger',
            'order_id' => $order_id,
            'order_total' => 0
        ];

        if (!validateCsrfToken($csrf_token)) {
            $response['message'] = "Invalid security token.";
            echo json_encode($response);
            exit;
        }

        if ($order_id) {
            $order = getOrderById($order_id);
            if ($order && confirmOrderCorrection($order_id)) {
                logActivity($_SESSION['user_id'], "Confirmed correction for order #$order_id", 'order_correction');
                $response = [
                    'status' => 'success',
                    'message' => "Order #$order_id correction confirmed successfully.",
                    'message_type' => 'success',
                    'order_id' => $order_id,
                    'order_total' => $order['total']
                ];
            } else {
                $response['message'] = "Failed to confirm correction for order #$order_id.";
            }
        }
        echo json_encode($response);
        exit;
    }
}

// Handle GET AJAX requests
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');

    if ($_GET['ajax'] === 'stats') {
        $stats = getDashboardStats();
        echo json_encode([
            'status' => 'success',
            'stats' => $stats,
            'message' => 'Stats retrieved successfully'
        ]);
        exit;
    } elseif ($_GET['ajax'] === 'product_details') {
        $barcode = sanitizeInput($_GET['barcode'] ?? '');
        $products = getProductByBarcode($barcode);
        echo json_encode([
            'status' => !empty($products) ? 'success' : 'error',
            'data' => !empty($products) ? [
                'product_id' => $products[0]['product_id'],
                'name' => $products[0]['name'],
                'price' => $products[0]['price'],
                'category' => $products[0]['category']
            ] : [],
            'message' => !empty($products) ? 'Product found' : "Product not found for barcode: $barcode"
        ]);
        exit;
    } elseif ($_GET['ajax'] === 'completed_orders_data') {
        $completed_orders = getCompletedOrders();
        echo json_encode(['status' => 'success', 'data' => $completed_orders]);
        exit;
    } elseif ($_GET['ajax'] === 'pending_orders_data') {
        $pending_orders = getCustomerPendingOrders();
        echo json_encode(['status' => 'success', 'data' => $pending_orders]);
        exit;
    } elseif ($_GET['ajax'] === 'sales_report_data') {
        $start_date = filter_input(INPUT_GET, 'start_date', FILTER_SANITIZE_STRING) ?: date('Y-m-d', strtotime('-7 days'));
        $end_date = filter_input(INPUT_GET, 'end_date', FILTER_SANITIZE_STRING) ?: date('Y-m-d');
        $report = getSalesReport($start_date, $end_date);
        echo json_encode(['status' => 'success', 'data' => $report]);
        exit;
    } elseif ($_GET['ajax'] === 'cart_data') {
        $cart_total = 0;
        foreach ($_SESSION['cart'] as $item) {
            $cart_total += $item['price'] * $item['quantity'];
        }
        echo json_encode([
            'status' => 'success',
            'cart' => $_SESSION['cart'],
            'cart_total' => number_format($cart_total, 2),
            'cart_count' => count($_SESSION['cart'])
        ]);
        exit;
    }
}

// Function to verify Paychangu payment
function verifyPaychanguPayment($tx_ref) {
    $secret_key = 'SEC-S1l5Jkcc9FSgJao8tlm2kcFwbb9xi13u';
    $url = "https://api.paychangu.com/verify-payment/{$tx_ref}";

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json',
        'Authorization: Bearer ' . $secret_key
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($http_code === 200 && $response) {
        return json_decode($response, true);
    } else {
        return [
            'status' => 'error',
            'message' => $error ?: 'Failed to verify payment with PayChangu API'
        ];
    }
}

// Function to update order status
function updateOrderStatus($order_id, $status) {
    global $conn;
    $stmt = $conn->prepare("UPDATE orders SET status = ?, updated_at = NOW() WHERE order_id = ?");
    $stmt->bind_param('si', $status, $order_id);
    $success = $stmt->execute();
    $stmt->close();
    return $success;
}

// Function to insert payment record
function insertPaymentRecord($order_id, $transaction_ref, $email, $first_name, $last_name, $amount, $status, $payment_method) {
    global $conn;
    $stmt = $conn->prepare("
        INSERT INTO payments (order_id, transaction_ref, email, first_name, last_name, amount, status, payment_method, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
    ");
    $stmt->bind_param('issssiss', $order_id, $transaction_ref, $email, $first_name, $last_name, $amount, $status, $payment_method);
    $success = $stmt->execute();
    $stmt->close();
    return $success;
}

// Data for initial page load
$stats = getDashboardStats();
$recent_orders = getCompletedOrders(5);
$pending_orders_data = getCustomerPendingOrders();

// Calculate cart total
$cart_total = 0;
foreach ($_SESSION['cart'] as $item) {
    $cart_total += $item['price'] * $item['quantity'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Auntie Eddah POS Dashboard - Manage orders, payments, and sales">
    <meta name="csrf-token" content="<?php echo $_SESSION['csrf_token']; ?>">
    <title>Cashier Dashboard | Auntie Eddah POS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <link rel="stylesheet" href="style.css">
    <script src="https://in.paychangu.com/js/popup.js"></script>
    <script src="script.js"></script>
</head>
<body>

    <header role="banner" class="header">
        <div class="header-content">
            <div class="logo">
                <i class="fas fa-cash-register" aria-hidden="true"></i> 
                <span>Cashier Dashboard</span>
            </div>
            <div class="header-actions">
                <button class="btn btn-primary quick-sale-btn" onclick="dashboard.showSection('new-sale')">
                    <i class="fas fa-plus"></i> New Sale
                </button>
                <div class="user-profile">
                    <i class="fas fa-user-circle"></i>
                    <span>Welcome, <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Cashier'); ?></span>
                </div>
            </div>
        </div>
    </header>

    <aside class="sidebar" role="navigation" aria-label="Main navigation">
        <nav class="sidebar-nav">
            <a href="#dashboard" class="nav-link active" onclick="dashboard.showSection('dashboard')" aria-current="page">
                <i class="fas fa-tachometer-alt" aria-hidden="true"></i>
                <span>Dashboard</span>
            </a>
            <a href="#new-sale" class="nav-link" onclick="dashboard.showSection('new-sale')">
                <i class="fas fa-barcode" aria-hidden="true"></i>
                <span>New Sale</span>
                <?php if (count($_SESSION['cart']) > 0): ?>
                    <span class="badge" id="cart-badge"><?php echo count($_SESSION['cart']); ?></span>
                <?php endif; ?>
            </a>
            <a href="#pending-orders" class="nav-link" onclick="dashboard.showSection('pending-orders')">
                <i class="fas fa-clock" aria-hidden="true"></i>
                <span>Pending Orders</span>
                <?php if (count($pending_orders_data) > 0): ?>
                    <span class="badge" id="pending-orders-badge"><?php echo count($pending_orders_data); ?></span>
                <?php endif; ?>
            </a>
            <a href="#completed-orders" class="nav-link" onclick="dashboard.showSection('completed-orders')">
                <i class="fas fa-check-circle" aria-hidden="true"></i>
                <span>Completed Orders</span>
            </a>
            <a href="#sales-report" class="nav-link" onclick="dashboard.showSection('sales-report')">
                <i class="fas fa-chart-bar" aria-hidden="true"></i>
                <span>Sales Report</span>
            </a>
            <a href="logout.php" class="nav-link logout">
                <i class="fas fa-sign-out-alt" aria-hidden="true"></i>
                <span>Logout</span>
            </a>
        </nav>
    </aside>

    <main class="main-content" role="main">
        <div class="content-wrapper">
            <!-- Notification Container -->
            <div class="notification-container" id="notification-container"></div>

            <!-- Dashboard Section -->
            <section id="dashboard-section" class="content-section active">
                <!-- Dashboard Stats -->
                <section class="dashboard-stats" aria-labelledby="stats-heading">
                    <h1 id="stats-heading" class="section-title">
                        <i class="fas fa-chart-line"></i> Today's Overview
                    </h1>
                    
                    <div class="stats-grid">
                        <div class="stat-card orders-card animate__animated animate__fadeInUp">
                            <div class="stat-icon">
                                <i class="fas fa-shopping-cart"></i>
                            </div>
                            <div class="stat-content">
                                <h3>Orders Today</h3>
                                <p class="stat-number" id="orders-today"><?php echo $stats['orders_today']; ?></p>
                                <span class="stat-label">Total orders processed</span>
                            </div>
                        </div>
                        
                        <div class="stat-card pending-card animate__animated animate__fadeInUp" style="animation-delay: 0.1s">
                            <div class="stat-icon">
                                <i class="fas fa-hourglass-half"></i>
                            </div>
                            <div class="stat-content">
                                <h3>Pending Payments</h3>
                                <p class="stat-number" id="pending-payments"><?php echo $stats['pending_payments']; ?></p>
                                <span class="stat-label">Awaiting confirmation</span>
                            </div>
                        </div>
                        
                        <div class="stat-card transactions-card animate__animated animate__fadeInUp" style="animation-delay: 0.2s">
                            <div class="stat-icon">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div class="stat-content">
                                <h3>Completed</h3>
                                <p class="stat-number" id="transactions-count"><?php echo $stats['transactions_count']; ?></p>
                                <span class="stat-label">Successful transactions</span>
                            </div>
                        </div>
                        
                        <div class="stat-card sales-card animate__animated animate__fadeInUp" style="animation-delay: 0.3s">
                            <div class="stat-icon">
                                <i class="fas fa-money-bill-wave"></i>
                            </div>
                            <div class="stat-content">
                                <h3>Total Sales</h3>
                                <p class="stat-number" id="total-sales">MWK<?php echo number_format($stats['total_sales_today'], 2); ?></p>
                                <span class="stat-label">Revenue today</span>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Quick Actions -->
                <section class="quick-actions-section">
                    <h2 class="section-title">
                        <i class="fas fa-bolt"></i> Quick Actions
                    </h2>
                    
                    <div class="action-grid">
                        <button class="action-btn primary-action" onclick="dashboard.showSection('new-sale')">
                            <i class="fas fa-barcode"></i>
                            <span>Start New Sale</span>
                            <small>Scan products & process payment</small>
                        </button>
                        
                        <button class="action-btn" onclick="dashboard.showSection('pending-orders')">
                            <i class="fas fa-clock"></i>
                            <span>View Pending Orders</span>
                            <small>Review orders awaiting payment</small>
                        </button>
                        
                        <button class="action-btn" onclick="dashboard.showSection('completed-orders')">
                            <i class="fas fa-check-circle"></i>
                            <span>View Completed Orders</span>
                            <small>Browse all successful transactions</small>
                        </button>
                        
                        <button class="action-btn" onclick="dashboard.showSection('sales-report')">
                            <i class="fas fa-chart-bar"></i>
                            <span>Generate Sales Report</span>
                            <small>Analyze sales data & trends</small>
                        </button>
                    </div>
                </section>

                <!-- Recent Orders -->
                <section class="recent-orders-section">
                    <h2 class="section-title">
                        <i class="fas fa-history"></i> Recent Completed Orders
                    </h2>
                    <div class="table-container">
                        <table id="recent-orders-table">
                            <thead>
                                <tr>
                                    <th>Order ID</th>
                                    <th>Customer</th>
                                    <th>Amount</th>
                                    <th>Payment Method</th>
                                    <th>Completed At</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recent_orders)): ?>
                                    <tr>
                                        <td colspan="6" style="text-align: center;">No recent completed orders.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($recent_orders as $order): ?>
                                        <tr data-order-id="<?php echo $order['order_id']; ?>">
                                            <td><strong>#<?php echo $order['order_id']; ?></strong></td>
                                            <td><?php echo htmlspecialchars(($order['first_name'] ?? '') . ' ' . ($order['last_name'] ?? '')); ?></td>
                                            <td><strong class="order-amount">MWK<?php echo number_format($order['total'], 2); ?></strong></td>
                                            <td>
                                                <span class="payment-method <?php echo $order['payment_method']; ?>">
                                                    <?php 
                                                    $icons = [
                                                        'cash' => 'fas fa-money-bill-wave',
                                                        'mobile transfer' => 'fas fa-mobile-alt',
                                                       
                                                    ];
                                                    $icon = $icons[$order['payment_method']] ?? 'fas fa-question';
                                                    ?>
                                                    <i class="<?php echo $icon; ?>"></i>
                                                    <?php echo ucfirst(str_replace('_', ' ', $order['payment_method'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php echo date('M j, Y', strtotime($order['updated_at'])); ?><br>
                                                <small><?php echo date('H:i', strtotime($order['updated_at'])); ?></small>
                                            </td>
                                            <td>
                                                <div style="display: flex; gap: 5px;">
                                                    <a href="order_details.php?id=<?php echo $order['order_id']; ?>" class="btn btn-info btn-sm">
                                                        <i class="fas fa-eye"></i> View
                                                    </a>
                                                    <button class="btn btn-primary btn-sm" data-action="print-receipt" data-order-id="<?php echo $order['order_id']; ?>">
                                                        <i class="fas fa-print"></i> Print
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            </section>

            <!-- New Sale Section -->
            <section id="new-sale-section" class="content-section">
                <h1 class="section-title"><i class="fas fa-barcode"></i> New Sale</h1>
                <div class="sale-container">
                    <div class="product-scan-section">
                        <div class="form-group">
                            <label for="barcode-input">Scan Barcode</label>
                            <input type="text" id="barcode-input" class="form-control" placeholder="Enter barcode or product code" autofocus>
                        </div>
                        <div id="product-preview" class="product-preview" style="display: none;">
                            <p><strong>Product:</strong> <span id="product-name"></span></p>
                            <p><strong>Price:</strong> <span id="product-price"></span></p>
                            <p><strong>Category:</strong> <span id="product-category"></span></p>
                        </div>
                        <button id="add-to-cart-btn" class="btn btn-primary btn-block">
                            <i class="fas fa-cart-plus"></i> Add to Cart
                        </button>
                    </div>

                    <div class="cart-section">
                        <h2><i class="fas fa-shopping-cart"></i> Cart (<span id="cart-count"><?php echo count($_SESSION['cart']); ?></span> items)</h2>
                        <div class="cart-items-container">
                            <ul id="cart-items-list" class="cart-items-list">
                                <?php if (empty($_SESSION['cart'])): ?>
                                    <li class="empty-cart-message">Your cart is empty. Scan a product to add.</li>
                                <?php else: ?>
                                    <?php foreach ($_SESSION['cart'] as $index => $item): ?>
                                        <li class="cart-item" data-index="<?php echo $index; ?>">
                                            <div class="item-details">
                                                <span class="item-name"><?php echo htmlspecialchars($item['name']); ?></span>
                                                <span class="item-price">MWK<?php echo number_format($item['price'], 2); ?></span>
                                            </div>
                                            <div class="item-controls">
                                                <button class="btn btn-sm btn-secondary decrease-qty" data-index="<?php echo $index; ?>">-</button>
                                                <span class="quantity"><?php echo $item['quantity']; ?></span>
                                                <button class="btn btn-sm btn-secondary increase-qty" data-index="<?php echo $index; ?>">+</button>
                                                <button class="btn btn-sm btn-danger remove-item" data-index="<?php echo $index; ?>">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </ul>
                        </div>
                        <div class="cart-summary">
                            <p>Total: <strong id="cart-total">MWK<?php echo number_format($cart_total, 2); ?></strong></p>
                            <div class="form-group">
                                <label for="payment-method">Payment Method</label>
                                <select id="payment-method" class="form-control">
                                    <option value="">Select Payment Method</option>
                                    <option value="cash">Cash</option>
                                    <option value="Mobile Transfer">Mobile Transfer</option>
                                </select>
                            </div>
                            <button id="process-payment-btn" class="btn btn-success btn-block" disabled>
                                <i class="fas fa-money-bill-wave"></i> Process Payment
                            </button>
                            <button id="clear-cart-btn" class="btn btn-warning btn-block">
                                <i class="fas fa-trash-alt"></i> Clear Cart
                            </button>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Pending Orders Section -->
            <section id="pending-orders-section" class="content-section">
                <h2 class="section-title"><i class="fas fa-clock"></i> Pending Orders (<span id="pending-count"><?php echo count($pending_orders_data); ?></span>)</h2>
                
                <?php if (empty($pending_orders_data)): ?>
                    <div style="text-align: center; padding: 40px;">
                        <i class="fas fa-check-circle" style="font-size: 4rem; color: #28a745; margin-bottom: 20px;"></i>
                        <h3>No Pending Orders</h3>
                        <p>All orders have been processed. Great job!</p>
                        <button class="btn btn-info" onclick="dashboard.showSection('new-sale')">
                            <i class="fas fa-barcode"></i> Create New Order
                        </button>
                    </div>
                <?php else: ?>
                    <div class="table-container">
                        <table id="pending-orders-table">
                            <thead>
                                <tr>
                                    <th>Order ID</th>
                                    <th>Customer</th>
                                    <th>Amount</th>
                                    <th>Payment Method</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pending_orders_data as $order): ?>
                                    <tr data-order-id="<?php echo $order['order_id']; ?>">
                                        <td>
                                            <strong>#<?php echo $order['order_id']; ?></strong>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars(($order['first_name'] ?? '') . ' ' . ($order['last_name'] ?? '')); ?>
                                        </td>
                                        <td>
                                            <strong>MWK<?php echo number_format($order['total'], 2); ?></strong>
                                        </td>
                                        <td>
                                            <span class="payment-method <?php echo $order['payment_method']; ?>">
                                                <?php 
                                                $icons = [
                                                    'cash' => 'fas fa-money-bill-wave',
                                                    'mpamba' => 'fas fa-mobile-alt',
                                                    'airtel_money' => 'fas fa-mobile-alt',
                                                    'card' => 'fas fa-credit-card'
                                                ];
                                                $icon = $icons[$order['payment_method']] ?? 'fas fa-question';
                                                ?>
                                                <i class="<?php echo $icon; ?>"></i>
                                                <?php echo ucfirst(str_replace('_', ' ', $order['payment_method'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php echo date('M j, Y', strtotime($order['created_at'])); ?><br>
                                            <small><?php echo date('H:i', strtotime($order['created_at'])); ?></small>
                                        </td>
                                        <td>
                                            <div style="display: flex; gap: 5px;">
                                                <form class="confirm-correction-form" data-order-id="<?php echo $order['order_id']; ?>">
                                                    <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                                                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                                    <input type="hidden" name="ajax" value="confirm_correction">
                                                    <button type="submit" name="confirm_correction" class="btn btn-success btn-sm" data-order-id="<?php echo $order['order_id']; ?>">
                                                        <i class="fas fa-check"></i> Confirm Correction
                                                    </button>
                                                </form>
                                                <a href="order_details.php?id=<?php echo $order['order_id']; ?>" class="btn btn-info btn-sm">
                                                    <i class="fas fa-eye"></i> View
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div style="margin-top: 20px; text-align: center;">
                        <p><strong>Total Pending Amount: <span id="total-pending-amount">MWK<?php 
                            $total_pending = array_sum(array_column($pending_orders_data, 'total'));
                            echo number_format($total_pending, 2); 
                        ?></span></strong></p>
                    </div>
                <?php endif; ?>
            </section>

            <!-- Completed Orders Section -->
            <section id="completed-orders-section" class="content-section">
                <h2 class="section-title"><i class="fas fa-check-circle"></i> Completed Orders (<span id="completed-count"><?php echo count($recent_orders); ?></span>)</h2>
                
                <?php if (empty($recent_orders)): ?>
                    <div style="text-align: center; padding: 40px;">
                        <i class="fas fa-check-circle" style="font-size: 4rem; color: var(--success-color); margin-bottom: 20px;"></i>
                        <h3>No Completed Orders</h3>
                        <p>No orders have been completed yet.</p>
                        <button class="btn btn-info" onclick="dashboard.showSection('new-sale')">
                            <i class="fas fa-barcode"></i> Create New Order
                        </button>
                    </div>
                <?php else: ?>
                    <div class="table-container">
                        <table id="completed-orders-table">
                            <thead>
                                <tr>
                                    <th>Order ID</th>
                                    <th>Customer</th>
                                    <th>Amount</th>
                                    <th>Payment Method</th>
                                    <th>Completed At</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_orders as $order): ?>
                                    <tr data-order-id="<?php echo $order['order_id']; ?>">
                                        <td><strong>#<?php echo $order['order_id']; ?></strong></td>
                                        <td><?php echo htmlspecialchars(($order['first_name'] ?? '') . ' ' . ($order['last_name'] ?? '')); ?></td>
                                        <td><strong class="order-amount">MWK<?php echo number_format($order['total'], 2); ?></strong></td>
                                        <td>
                                            <span class="payment-method <?php echo $order['payment_method']; ?>">
                                                <?php 
                                                $icons = [
                                                    'cash' => 'fas fa-money-bill-wave',
                                                    'mpamba' => 'fas fa-mobile-alt',
                                                    'airtel_money' => 'fas fa-mobile-alt',
                                                    'card' => 'fas fa-credit-card'
                                                ];
                                                $icon = $icons[$order['payment_method']] ?? 'fas fa-question';
                                                ?>
                                                <i class="<?php echo $icon; ?>"></i>
                                                <?php echo ucfirst(str_replace('_', ' ', $order['payment_method'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php echo date('M j, Y', strtotime($order['updated_at'])); ?><br>
                                            <small><?php echo date('H:i', strtotime($order['updated_at'])); ?></small>
                                        </td>
                                        <td>
                                            <div style="display: flex; gap: 5px;">
                                                <a href="order_details.php?id=<?php echo $order['order_id']; ?>" class="btn btn-info btn-sm">
                                                    <i class="fas fa-eye"></i> View
                                                </a>
                                                <button class="btn btn-primary btn-sm" data-action="print-receipt" data-order-id="<?php echo $order['order_id']; ?>">
                                                    <i class="fas fa-print"></i> Print
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div style="margin-top: 20px; text-align: center;">
                        <p><strong>Total Completed Amount: MWK<span id="total-completed-amount"><?php 
                            $total_completed = array_sum(array_column($recent_orders, 'total'));
                            echo number_format($total_completed, 2); 
                        ?></span></strong></p>
                    </div>
                <?php endif; ?>
            </section>

            <!-- Sales Report Section -->
            <section id="sales-report-section" class="content-section">
                <h2 class="section-title"><i class="fas fa-chart-bar"></i> Sales Report</h2>
                
                <form id="sales-report-form" method="GET" style="margin-bottom: 20px;">
                    <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                        <div class="form-group">
                            <label for="start_date">Start Date</label>
                            <input type="date" name="start_date" id="start_date" value="<?php echo htmlspecialchars(date('Y-m-d', strtotime('-7 days'))); ?>" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="end_date">End Date</label>
                            <input type="date" name="end_date" id="end_date" value="<?php echo htmlspecialchars(date('Y-m-d')); ?>" class="form-control">
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter"></i> Filter
                        </button>
                        <a href="#" id="download-csv-btn" class="btn btn-success">
                            <i class="fas fa-download"></i> Download CSV
                        </a>
                    </div>
                </form>

                <div id="sales-report-content">
                    <!-- Sales report data will be loaded here via AJAX -->
                    <div style="text-align: center; padding: 40px;">
                        <i class="fas fa-inbox" style="font-size: 4rem; color: var(--secondary-color); margin-bottom: 20px;"></i>
                        <h3>No Sales Data</h3>
                        <p>Select a date range and click Filter to view sales data.</p>
                    </div>
                </div>
            </section>
        </div>

    </main>

   <script>
class CashierDashboard {
    constructor() {
        this.initEventListeners();
    }

    initEventListeners() {
        // Handle barcode input
        const barcodeInput = document.getElementById('barcode-input');
        if (barcodeInput) {
            barcodeInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    this.scanBarcode();
                }
            });
        }

        // Handle add to cart
        const addToCartBtn = document.getElementById('add-to-cart-btn');
        if (addToCartBtn) {
            addToCartBtn.addEventListener('click', () => this.addToCart());
        }

        // Handle cart operations
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('remove-item')) {
                this.removeFromCart(e.target.dataset.index);
            } else if (e.target.classList.contains('increase-qty')) {
                this.updateQuantity(e.target.dataset.index, 1);
            } else if (e.target.classList.contains('decrease-qty')) {
                this.updateQuantity(e.target.dataset.index, -1);
            }
        });

        // Handle clear cart
        const clearCartBtn = document.getElementById('clear-cart-btn');
        if (clearCartBtn) {
            clearCartBtn.addEventListener('click', () => this.clearCart());
        }

        // Handle payment processing
        const processPaymentBtn = document.getElementById('process-payment-btn');
        if (processPaymentBtn) {
            processPaymentBtn.addEventListener('click', () => this.processPayment());
            // Enable/disable button based on payment method and cart
            document.getElementById('payment-method').addEventListener('change', () => {
                processPaymentBtn.disabled = !this.validatePaymentForm();
            });
        }

        // Handle 'S' keypress for payment
        document.addEventListener('keypress', (e) => {
            if (e.key.toLowerCase() === 's' && this.validatePaymentForm()) {
                this.processPayment();
            }
        });
    }

    async scanBarcode() {
        const barcodeInput = document.getElementById('barcode-input');
        const barcode = barcodeInput.value.trim();
        if (!barcode) return;
        try {
            const response = await fetch(`?ajax=product_details&barcode=${encodeURIComponent(barcode)}`);
            const data = await response.json();
            const preview = document.getElementById('product-preview');
            if (data.status === 'success') {
                document.getElementById('product-name').textContent = data.data.name;
                document.getElementById('product-price').textContent = `MWK${data.data.price}`;
                document.getElementById('product-category').textContent = data.data.category;
                preview.style.display = 'block';
                document.getElementById('add-to-cart-btn').dataset.productId = data.data.product_id;
            } else {
                preview.style.display = 'none';
                this.showToast(data.message, 'error');
            }
        } catch (error) {
            console.error('Error scanning barcode:', error);
            this.showToast('Error scanning barcode', 'error');
        }
    }

    async addToCart() {
        const barcodeInput = document.getElementById('barcode-input');
        const barcode = barcodeInput.value.trim();
        if (!barcode) {
            this.showToast('Please enter a barcode', 'error');
            return;
        }
        const formData = new FormData();
        formData.append('ajax', 'add_to_cart');
        formData.append('barcode', barcode);
        formData.append('csrf_token', document.querySelector('meta[name="csrf-token"]').content);
        try {
            const response = await fetch('', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();
            if (data.status === 'success') {
                this.showToast(data.message, data.message_type);
                this.refreshCart();
                barcodeInput.value = '';
                document.getElementById('product-preview').style.display = 'none';
            } else {
                this.showToast(data.message, data.message_type);
            }
        } catch (error) {
            console.error('Error adding to cart:', error);
            this.showToast('Error adding to cart', 'error');
        }
    }

    async removeFromCart(index) {
        const formData = new FormData();
        formData.append('ajax', 'cart_operation');
        formData.append('operation', 'remove_item');
        formData.append('item_index', index);
        try {
            const response = await fetch('', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();
            this.showToast(data.message, data.status);
            if (data.status === 'success') {
                this.refreshCart();
            }
        } catch (error) {
            console.error('Error removing item:', error);
            this.showToast('Error removing item', 'error');
        }
    }

    async updateQuantity(index, change) {
        const quantitySpan = document.querySelector(`.cart-item[data-index="${index}"] .quantity`);
        const currentQuantity = parseInt(quantitySpan.textContent);
        const newQuantity = currentQuantity + change;
        if (newQuantity < 1) return;
        const formData = new FormData();
        formData.append('ajax', 'cart_operation');
        formData.append('operation', 'update_quantity');
        formData.append('item_index', index);
        formData.append('quantity', newQuantity);
        try {
            const response = await fetch('', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();
            if (data.status === 'success') {
                this.showToast(data.message, 'success');
                this.refreshCart();
            } else {
                this.showToast(data.message, 'error');
            }
        } catch (error) {
            console.error('Error updating quantity:', error);
            this.showToast('Error updating quantity', 'error');
        }
    }

    async clearCart() {
        const formData = new FormData();
        formData.append('ajax', 'cart_operation');
        formData.append('operation', 'clear');
        try {
            const response = await fetch('', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();
            this.showToast(data.message, data.status);
            if (data.status === 'success') {
                this.refreshCart();
            }
        } catch (error) {
            console.error('Error clearing cart:', error);
            this.showToast('Error clearing cart', 'error');
        }
    }

    validatePaymentForm() {
        const paymentMethod = document.getElementById('payment-method').value;
        const cartItems = document.getElementById('cart-items-list').children;
        return paymentMethod !== '' && cartItems.length > 0 && !document.querySelector('.empty-cart-message');
    }

    async processPayment() {
        const paymentMethod = document.getElementById('payment-method').value;
        if (!this.validatePaymentForm()) {
            this.showToast('Please select a payment method and ensure cart is not empty', 'error');
            return;
        }
        const formData = new FormData();
        formData.append('ajax', 'process_payment');
        formData.append('csrf_token', '<?php echo generateCsrfToken(); ?>');
        formData.append('payment_method', paymentMethod);
        try {
            const response = await fetch('', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();
            if (data.status === 'pending' && paymentMethod === 'Mobile Transfer') {
                // Store cart data in session for callback.php
                const cartData = {
                    items: <?php echo json_encode($_SESSION['cart']); ?>,
                    email: '<?php echo addslashes($_SESSION['user_email']); ?>',
                    firstname: '<?php echo addslashes($_SESSION['user_name']); ?>',
                    surname: '',
                    amount: parseFloat(data.total.replace(/,/g, ''))
                };
                // Send cart data to server to store in session
                const sessionFormData = new FormData();
                sessionFormData.append('ajax', 'store_cart_data');
                sessionFormData.append('csrf_token', '<?php echo generateCsrfToken(); ?>');
                sessionFormData.append('cart_data', JSON.stringify(cartData));
                sessionFormData.append('transaction_ref', data.tx_ref);
                await fetch('', {
                    method: 'POST',
                    body: sessionFormData
                });
                this.initiatePaychanguPayment(data);
            } else if (data.status === 'success') {
                this.showToast(data.message, 'success');
                this.refreshCart();
                this.showSection('completed-orders');
                this.refreshStats();
            } else {
                this.showToast(data.message, 'error');
            }
        } catch (error) {
            console.error('Error processing payment:', error);
            this.showToast('Error processing payment', 'error');
        }
    }

    initiatePaychanguPayment(data) {
        const userName = '<?php echo addslashes($_SESSION['user_name']); ?>';
        const userEmail = '<?php echo addslashes($_SESSION['user_email']); ?>';
        const total = parseFloat(data.total.replace(/,/g, ''));
        PaychanguCheckout({
            public_key: "pub-test-HYSBQpa5K91mmXMHrjhkmC6mAjObPJ2u",
            tx_ref: data.tx_ref,
            amount: total,
            currency: "MWK",
            callback_url: "callback.php",
            return_url: "return.php",
            customer: {
                email: userEmail,
                first_name: userName,
                last_name: ""
            },
            customization: {
                title: "Auntie Eddah POS Payment",
                description: `Payment for Order #${data.order_id}`
            },
            meta: {
                uuid: data.order_id,
                response: "Payment for order"
            }
        });

        // Handle payment response
        window.addEventListener('message', async (event) => {
            if (event.origin === 'https://in.paychangu.com' && event.data.status) {
                const response = event.data;
                if (response.status === 'success') {
                    const formData = new FormData();
                    formData.append('ajax', 'process_payment');
                    formData.append('csrf_token', '<?php echo generateCsrfToken(); ?>');
                    formData.append('payment_method', 'Mobile Transfer');
                    formData.append('tx_ref', response.tx_ref);
                    try {
                        const result = await fetch('', {
                            method: 'POST',
                            body: formData
                        });
                        const data = await result.json();
                        if (data.status === 'success') {
                            this.showToast(data.message, 'success');
                            this.refreshCart();
                            this.showSection('completed-orders');
                            this.refreshPendingOrdersData();
                            this.refreshStats();
                        } else {
                            this.showToast(data.message, 'error');
                        }
                    } catch (error) {
                        console.error('Error verifying payment:', error);
                        this.showToast('Error verifying payment', 'error');
                    }
                } else {
                    this.showToast('Payment was not completed', 'error');
                }
            }
        }, { once: true });
    }

    async refreshCart() {
        try {
            const response = await fetch('?ajax=cart_data');
            const data = await response.json();
            if (data.status === 'success') {
                const cartList = document.getElementById('cart-items-list');
                cartList.innerHTML = '';
                if (data.cart_count === 0) {
                    cartList.innerHTML = '<li class="empty-cart-message">Your cart is empty. Scan a product to add.</li>';
                } else {
                    data.cart.forEach((item, index) => {
                        const li = document.createElement('li');
                        li.className = 'cart-item';
                        li.dataset.index = index;
                        li.innerHTML = `
                            <div class="item-details">
                                <span class="item-name">${item.name}</span>
                                <span class="item-price">MWK${Number(item.price).toFixed(2)}</span>
                            </div>
                            <div class="item-controls">
                                <button class="btn btn-sm btn-secondary decrease-qty" data-index="${index}">-</button>
                                <span class="quantity">${item.quantity}</span>
                                <button class="btn btn-sm btn-secondary increase-qty" data-index="${index}">+</button>
                                <button class="btn btn-sm btn-danger remove-item" data-index="${index}">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        `;
                        cartList.appendChild(li);
                    });
                }
                document.getElementById('cart-count').textContent = data.cart_count;
                document.getElementById('cart-total').textContent = `MWK${data.cart_total}`;
                document.getElementById('process-payment-btn').disabled = !this.validatePaymentForm();
            }
        } catch (error) {
            console.error('Error refreshing cart:', error);
            this.showToast('Error refreshing cart', 'error');
        }
    }

    async refreshStats() {
        try {
            const response = await fetch('?ajax=stats');
            const data = await response.json();
            if (data.status === 'success') {
                document.getElementById('orders-today').textContent = data.stats.orders_today;
                document.getElementById('pending-payments').textContent = data.stats.pending_payments;
                document.getElementById('transactions-count').textContent = data.stats.transactions_count;
                document.getElementById('total-sales').textContent = `MWK${Number(data.stats.total_sales_today).toFixed(2)}`;
            } else {
                this.showToast(data.message, 'error');
            }
        } catch (error) {
            console.error('Error refreshing stats:', error);
            this.showToast('Error refreshing stats', 'error');
        }
    }

    async refreshPendingOrdersData() {
        try {
            const response = await fetch('?ajax=pending_orders_data');
            const data = await response.json();
            if (data.status === 'success') {
                const tableBody = document.querySelector('#pending-orders-table tbody');
                tableBody.innerHTML = '';
                if (data.data.length === 0) {
                    tableBody.innerHTML = '<tr><td colspan="6" style="text-align: center;">No pending orders.</td></tr>';
                } else {
                    data.data.forEach(order => {
                        const tr = document.createElement('tr');
                        tr.dataset.orderId = order.order_id;
                        tr.innerHTML = `
                            <td><strong>#${order.order_id}</strong></td>
                            <td>${order.first_name || ''} ${order.last_name || ''}</td>
                            <td><strong>MWK${Number(order.total).toFixed(2)}</strong></td>
                            <td>
                                <span class="payment-method ${order.payment_method}">
                                    <i class="${this.getPaymentIcon(order.payment_method)}"></i>
                                    ${this.capitalizeWords(order.payment_method.replace('_', ' '))}
                                </span>
                            </td>
                            <td>
                                ${new Date(order.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}<br>
                                <small>${new Date(order.created_at).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' })}</small>
                            </td>
                            <td>
                                <div style="display: flex; gap: 5px;">
                                    <form class="confirm-correction-form" data-order-id="${order.order_id}">
                                        <input type="hidden" name="order_id" value="${order.order_id}">
                                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                        <input type="hidden" name="ajax" value="confirm_correction">
                                        <button type="submit" name="confirm_correction" class="btn btn-success btn-sm" data-order-id="${order.order_id}">
                                            <i class="fas fa-check"></i> Confirm Correction
                                        </button>
                                    </form>
                                    <a href="order_details.php?id=${order.order_id}" class="btn btn-info btn-sm">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                </div>
                            </td>
                        `;
                        tableBody.appendChild(tr);
                    });
                }
                document.getElementById('pending-count').textContent = data.data.length;
                const totalPending = data.data.reduce((sum, order) => sum + parseFloat(order.total), 0);
                document.getElementById('total-pending-amount').textContent = `MWK${totalPending.toFixed(2)}`;
            }
        } catch (error) {
            console.error('Error refreshing pending orders:', error);
            this.showToast('Error refreshing pending orders', 'error');
        }
    }

    async loadSalesReport(startDate, endDate) {
        try {
            const response = await fetch(`?ajax=sales_report_data&start_date=${startDate}&end_date=${endDate}`);
            const data = await response.json();
            const content = document.getElementById('sales-report-content');
            if (data.status === 'success' && data.data.length > 0) {
                content.innerHTML = `
                    <div class="table-container">
                        <table id="sales-report-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Order Count</th>
                                    <th>Total Sales</th>
                                    <th>Payment Method</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${data.data.map(report => `
                                    <tr>
                                        <td>${new Date(report.date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}</td>
                                        <td>${report.order_count}</td>
                                        <td>MWK${Number(report.total_sales).toFixed(2)}</td>
                                        <td>${this.capitalizeWords(report.payment_method.replace('_', ' '))}</td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                `;
            } else {
                content.innerHTML = `
                    <div style="text-align: center; padding: 40px;">
                        <i class="fas fa-inbox" style="font-size: 4rem; color: var(--secondary-color); margin-bottom: 20px;"></i>
                        <h3>No Sales Data</h3>
                        <p>No sales data available for the selected date range.</p>
                    </div>
                `;
            }
        } catch (error) {
            console.error('Error loading sales report:', error);
            this.showToast('Error loading sales report', 'error');
        }
    }

    showSection(sectionId) {
        document.querySelectorAll('.content-section').forEach(section => {
            section.classList.remove('active');
        });
        document.querySelectorAll('.nav-link').forEach(link => {
            link.classList.remove('active');
        });
        document.getElementById(`${sectionId}-section`).classList.add('active');
        document.querySelector(`.nav-link[href="#${sectionId}"]`).classList.add('active');
        if (sectionId === 'pending-orders') {
            this.refreshPendingOrdersData();
        } else if (sectionId === 'completed-orders') {
            this.refreshCompletedOrdersData();
        } else if (sectionId === 'sales-report') {
            const startDate = document.getElementById('start_date').value;
            const endDate = document.getElementById('end_date').value;
            this.loadSalesReport(startDate, endDate);
        }
    }

    async refreshCompletedOrdersData() {
        try {
            const response = await fetch('?ajax=completed_orders_data');
            const data = await response.json();
            if (data.status === 'success') {
                const tableBody = document.querySelector('#completed-orders-table tbody');
                tableBody.innerHTML = '';
                if (data.data.length === 0) {
                    tableBody.innerHTML = '<tr><td colspan="6" style="text-align: center;">No completed orders.</td></tr>';
                } else {
                    data.data.forEach(order => {
                        const tr = document.createElement('tr');
                        tr.dataset.orderId = order.order_id;
                        tr.innerHTML = `
                            <td><strong>#${order.order_id}</strong></td>
                            <td>${order.first_name || ''} ${order.last_name || ''}</td>
                            <td><strong class="order-amount">MWK${Number(order.total).toFixed(2)}</strong></td>
                            <td>
                                <span class="payment-method ${order.payment_method}">
                                    <i class="${this.getPaymentIcon(order.payment_method)}"></i>
                                    ${this.capitalizeWords(order.payment_method.replace('_', ' '))}
                                </span>
                            </td>
                            <td>
                                ${new Date(order.updated_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}<br>
                                <small>${new Date(order.updated_at).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' })}</small>
                            </td>
                            <td>
                                <div style="display: flex; gap: 5px;">
                                    <a href="order_details.php?id=${order.order_id}" class="btn btn-info btn-sm">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    <button class="btn btn-primary btn-sm" data-action="print-receipt" data-order-id="${order.order_id}">
                                        <i class="fas fa-print"></i> Print
                                    </button>
                                </div>
                            </td>
                        `;
                        tableBody.appendChild(tr);
                    });
                }
                document.getElementById('completed-count').textContent = data.data.length;
                const totalCompleted = data.data.reduce((sum, order) => sum + parseFloat(order.total), 0);
                document.getElementById('total-completed-amount').textContent = totalCompleted.toFixed(2);
            }
        } catch (error) {
            console.error('Error refreshing completed orders:', error);
            this.showToast('Error refreshing completed orders', 'error');
        }
    }

    getPaymentIcon(paymentMethod) {
        const icons = {
            'cash': 'fas fa-money-bill-wave',
            'mpamba': 'fas fa-mobile-alt',
            'airtel_money': 'fas fa-mobile-alt',
            'card': 'fas fa-credit-card',
            'Mobile Transfer': 'fas fa-mobile-alt'
        };
        return icons[paymentMethod] || 'fas fa-question';
    }

    capitalizeWords(str) {
        return str.replace(/\b\w/g, c => c.toUpperCase());
    }

    showToast(message, type) {
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.textContent = message;
        document.getElementById('notification-container').appendChild(toast);
        setTimeout(() => {
            toast.remove();
        }, 3000);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    window.dashboard = new CashierDashboard();
    const urlParams = new URLSearchParams(window.location.search);
    const section = urlParams.get('section') || 'dashboard';
    dashboard.showSection(section);
    const salesReportForm = document.getElementById('sales-report-form');
    if (salesReportForm) {
        salesReportForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const startDate = document.getElementById('start_date').value;
            const endDate = document.getElementById('end_date').value;
            await dashboard.loadSalesReport(startDate, endDate);
        });
    }
    const downloadCsvBtn = document.getElementById('download-csv-btn');
    if (downloadCsvBtn) {
        downloadCsvBtn.addEventListener('click', (e) => {
            e.preventDefault();
            const startDate = document.getElementById('start_date').value;
            const endDate = document.getElementById('end_date').value;
            window.location.href = `?ajax=sales_report_data&start_date=${startDate}&end_date=${endDate}&download=1`;
        });
    }
    if (section === 'sales-report') {
        const startDate = document.getElementById('start_date').value;
        const endDate = document.getElementById('end_date').value;
        dashboard.loadSalesReport(startDate, endDate);
    }
    document.querySelectorAll('.confirm-correction-form').forEach(form => {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(form);
            formData.append('ajax', 'confirm_correction');
            try {
                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                if (data.status === 'success') {
                    dashboard.showToast(data.message, 'success');
                    const orderRow = form.closest('tr');
                    if (orderRow) {
                        orderRow.remove();
                        const pendingCountEl = document.getElementById('pending-count');
                        if (pendingCountEl) {
                            pendingCountEl.textContent = parseInt(pendingCountEl.textContent) - 1;
                        }
                        dashboard.refreshPendingOrdersData();
                    }
                    dashboard.refreshStats();
                } else {
                    dashboard.showToast(data.message, 'error');
                }
            } catch (error) {
                console.error('Error confirming correction:', error);
                dashboard.showToast('Error confirming correction', 'error');
            }
        });
    });
});
</script>
</body>
</html>
<?php
$conn->close();
?>