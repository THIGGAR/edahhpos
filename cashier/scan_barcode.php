<?php
session_start();

require_once 'db_connect.php';
require_once 'functions.php';
require_once 'config.php';

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Check authentication
if (!validateUserSession() || !hasRole('cashier')) {
    error_log("Authentication failed: redirecting to login.php");
    header("Location: login.php");
    exit;
}

// Ensure user_email and user_name are set
if (!isset($_SESSION['user_email'])) {
    $_SESSION['user_email'] = 'cashier@example.com';
    error_log("user_email not set in session, defaulting to cashier@example.com");
}
if (!isset($_SESSION['user_name'])) {
    $_SESSION['user_name'] = 'Cashier';
    error_log("user_name not set in session, defaulting to Cashier");
}

// Initialize cart if not exists
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
    error_log("Cart initialized in session");
}

// Generate CSRF token if not set
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = generateCsrfToken();
    error_log("CSRF token generated: " . $_SESSION['csrf_token']);
}

// Handle AJAX request for product details
if (isset($_GET['ajax']) && $_GET['ajax'] === 'product_details') {
    $barcode = sanitizeInput($_GET['barcode']);
    error_log("AJAX request for barcode: '$barcode'");
    $products = getProductByBarcode($barcode);
    header('Content-Type: application/json');
    echo json_encode([
        'status' => !empty($products) ? 'success' : 'error',
        'data' => !empty($products) ? [
            'product_id' => $products[0]['product_id'],
            'name' => $products[0]['name'],
            'price' => $products[0]['price'],
            'category' => $products[0]['category'],
            'stock_quantity' => $products[0]['stock_quantity']
        ] : [],
        'message' => !empty($products) ? 'Product found' : "Product not found for barcode: $barcode"
    ]);
    exit;
}

// Handle AJAX request for adding to cart
if (isset($_POST['ajax']) && $_POST['ajax'] === 'add_to_cart') {
    $csrf_token = sanitizeInput($_POST['csrf_token']);
    $barcode = sanitizeInput($_POST['barcode']);
    
    header('Content-Type: application/json');
    
    if (!validateCsrfToken($csrf_token)) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid security token. Please try again.',
            'message_type' => 'danger'
        ]);
        error_log("CSRF token validation failed for AJAX add_to_cart");
        exit;
    }
    
    if (empty($barcode)) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Please enter a barcode',
            'message_type' => 'danger'
        ]);
        error_log("Empty barcode provided for AJAX add_to_cart");
        exit;
    }
    
    $products = getProductByBarcode($barcode);
    
    if (!empty($products)) {
        $product = $products[0];
        error_log("Product found for AJAX add_to_cart: " . print_r($product, true));
        
        if ($product['stock_quantity'] <= 0) {
            echo json_encode([
                'status' => 'error',
                'message' => "Product '{$product['name']}' is out of stock",
                'message_type' => 'danger'
            ]);
            error_log("Product out of stock: {$product['name']}");
            exit;
        }
        
        // Check if product already in cart
        $item_index = -1;
        foreach ($_SESSION['cart'] as $index => $item) {
            if ($item['product_id'] == $product['product_id']) {
                $item_index = $index;
                break;
            }
        }
        
        $available_stock = $product['stock_quantity'];
        if ($item_index >= 0) {
            $available_stock -= $_SESSION['cart'][$item_index]['quantity'];
        }
        
        if (1 > $available_stock) {
            echo json_encode([
                'status' => 'error',
                'message' => "No more units of '{$product['name']}' available in stock",
                'message_type' => 'danger'
            ]);
            error_log("Insufficient stock for {$product['name']}: requested=1, available=$available_stock");
            exit;
        }
        
        if ($item_index >= 0) {
            // Update existing item quantity
            $_SESSION['cart'][$item_index]['quantity'] += 1;
            error_log("Updated cart item at index $item_index: new quantity=" . $_SESSION['cart'][$item_index]['quantity']);
        } else {
            // Add new item to cart
            $_SESSION['cart'][] = [
                'product_id' => $product['product_id'],
                'name' => $product['name'],
                'price' => $product['price'],
                'quantity' => 1,
                'barcodes' => $barcode
            ];
            error_log("Added new item to cart: " . print_r(end($_SESSION['cart']), true));
        }
        
        // Calculate cart total for response
        $cart_total = 0;
        foreach ($_SESSION['cart'] as $item) {
            $cart_total += $item['price'] * $item['quantity'];
        }
        
        // Prepare cart items HTML
        $cart_html = '';
        if (!empty($_SESSION['cart'])) {
            foreach ($_SESSION['cart'] as $index => $item) {
                $subtotal = $item['price'] * $item['quantity'];
                $cart_html .= '
                    <tr data-item-index="' . $index . '">
                        <td>' . htmlspecialchars($item['name']) . '</td>
                        <td>MWK' . number_format($item['price'], 2) . '</td>
                        <td>
                            <div class="quantity-controls">
                                <button type="button" class="btn btn-sm btn-secondary decrease-quantity" data-item-index="' . $index . '">
                                    <i class="fas fa-minus"></i>
                                </button>
                                <span class="quantity">' . $item['quantity'] . '</span>
                                <button type="button" class="btn btn-sm btn-secondary increase-quantity" data-item-index="' . $index . '">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                        </td>
                        <td class="subtotal">MWK' . number_format($subtotal, 2) . '</td>
                        <td>
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="' . generateCsrfToken() . '">
                                <input type="hidden" name="item_index" value="' . $index . '">
                                <button type="submit" name="remove_item" class="btn btn-danger btn-sm">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>';
            }
        } else {
            $cart_html = '<tr><td colspan="5">Cart is empty</td></tr>';
        }
        
        echo json_encode([
            'status' => 'success',
            'message' => "{$product['name']} (MWK" . number_format($product['price'], 2) . ") added to cart",
            'message_type' => 'success',
            'cart_html' => $cart_html,
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
    error_log("Product not found for barcode: $barcode");
    exit;
}

// Handle AJAX request for quantity updates
if (isset($_GET['ajax']) && $_GET['ajax'] === 'update_quantity') {
    $item_index = sanitizeInput($_GET['item_index'], 'int');
    $change = sanitizeInput($_GET['change'], 'int');
    header('Content-Type: application/json');
    
    if (!isset($_SESSION['cart'][$item_index])) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid cart item'
        ]);
        exit;
    }

    $product = getProductByBarcode($_SESSION['cart'][$item_index]['barcodes']);
    if (empty($product)) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Product not found'
        ]);
        exit;
    }

    $available_stock = $product[0]['stock_quantity'];
    $current_quantity = $_SESSION['cart'][$item_index]['quantity'];
    $new_quantity = $current_quantity + $change;

    if ($new_quantity < 1) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Quantity cannot be less than 1'
        ]);
        exit;
    }

    if ($new_quantity > $available_stock) {
        echo json_encode([
            'status' => 'error',
            'message' => "Only {$available_stock} units available"
        ]);
        exit;
    }

    $_SESSION['cart'][$item_index]['quantity'] = $new_quantity;
    $subtotal = $_SESSION['cart'][$item_index]['price'] * $new_quantity;
    $cart_total = 0;
    foreach ($_SESSION['cart'] as $item) {
        $cart_total += $item['price'] * $item['quantity'];
    }

    // Prepare cart items HTML
    $cart_html = '';
    if (!empty($_SESSION['cart'])) {
        foreach ($_SESSION['cart'] as $index => $item) {
            $subtotal = $item['price'] * $item['quantity'];
            $cart_html .= '
                <tr data-item-index="' . $index . '">
                    <td>' . htmlspecialchars($item['name']) . '</td>
                    <td>MWK' . number_format($item['price'], 2) . '</td>
                    <td>
                        <div class="quantity-controls">
                            <button type="button" class="btn btn-sm btn-secondary decrease-quantity" data-item-index="' . $index . '">
                                <i class="fas fa-minus"></i>
                            </button>
                            <span class="quantity">' . $item['quantity'] . '</span>
                            <button type="button" class="btn btn-sm btn-secondary increase-quantity" data-item-index="' . $index . '">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                    </td>
                    <td class="subtotal">MWK' . number_format($subtotal, 2) . '</td>
                    <td>
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="' . generateCsrfToken() . '">
                            <input type="hidden" name="item_index" value="' . $index . '">
                            <button type="submit" name="remove_item" class="btn btn-danger btn-sm">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                    </td>
                </tr>';
        }
    } else {
        $cart_html = '<tr><td colspan="5">Cart is empty</td></tr>';
    }

    echo json_encode([
        'status' => 'success',
        'new_quantity' => $new_quantity,
        'subtotal' => number_format($subtotal, 2),
        'cart_total' => number_format($cart_total, 2),
        'cart_html' => $cart_html,
        'cart_count' => count($_SESSION['cart']),
        'message' => 'Quantity updated'
    ]);
    exit;
}

// Handle non-AJAX POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['ajax'])) {
    $csrf_token = sanitizeInput($_POST['csrf_token']);
    
    if (!validateCsrfToken($csrf_token)) {
        $_SESSION['message'] = "Invalid security token. Please try again.";
        $_SESSION['message_type'] = "danger";
        error_log("CSRF token validation failed");
        header("Location: scan_barcode.php");
        exit;
    }
    
    // Handle clear cart
    if (isset($_POST['clear_cart'])) {
        $_SESSION['cart'] = [];
        $_SESSION['message'] = "Cart cleared successfully";
        $_SESSION['message_type'] = "success";
        error_log("Cart cleared by user #{$_SESSION['user_id']}");
        header("Location: scan_barcode.php");
        exit;
    }
    
    // Handle remove item
    if (isset($_POST['remove_item']) && isset($_POST['item_index'])) {
        $item_index = sanitizeInput($_POST['item_index'], 'int');
        if (isset($_SESSION['cart'][$item_index])) {
            $item_name = $_SESSION['cart'][$item_index]['name'];
            unset($_SESSION['cart'][$item_index]);
            $_SESSION['cart'] = array_values($_SESSION['cart']);
            $_SESSION['message'] = "Removed {$item_name} from cart";
            $_SESSION['message_type'] = "success";
            error_log("Removed item from cart: index=$item_index, name=$item_name");
            header("Location: scan_barcode.php");
            exit;
        }
    }
    
    // Handle payment initiation
    if (isset($_POST['initiate_payment']) && !empty($_SESSION['cart'])) {
        $payment_method = sanitizeInput($_POST['payment_method']);
        $total = 0;
        foreach ($_SESSION['cart'] as $item) {
            $total += $item['price'] * $item['quantity'];
        }
        
        if (empty($payment_method)) {
            $_SESSION['message'] = "Please select a payment method";
            $_SESSION['message_type'] = "danger";
            error_log("No payment method selected for checkout");
            header("Location: scan_barcode.php");
            exit;
        }
        
        if ($payment_method === 'cash') {
            $order_id = createOrder($_SESSION['user_id'], $_SESSION['cart'], $payment_method, $total);
            if ($order_id) {
                logActivity($_SESSION['user_id'], "Created order #$order_id", 'order_creation');
                error_log("Attempting to confirm cash payment for order #$order_id");
                $payment_result = confirmPayment($order_id);
                if ($payment_result['success']) {
                    $_SESSION['cart'] = [];
                    $_SESSION['message'] = "Order #$order_id paid in cash and completed. Total: MWK" . number_format($total, 2);
                    $_SESSION['message_type'] = "success";
                    error_log("Cash payment completed for order #$order_id");
                    header("Location: completed_orders.php");
                    exit;
                } else {
                    $_SESSION['message'] = "Failed to process cash payment for order #$order_id: {$payment_result['error']}. Please check order status or contact support.";
                    $_SESSION['message_type'] = "danger";
                    error_log("Cash payment failed for order #$order_id: {$payment_result['error']}");
                    header("Location: scan_barcode.php");
                    exit;
                }
            } else {
                $_SESSION['message'] = "Failed to create order. Please try again.";
                $_SESSION['message_type'] = "danger";
                error_log("Failed to create order for user #{$_SESSION['user_id']}");
                header("Location: scan_barcode.php");
                exit;
            }
        } elseif ($payment_method === 'mobile_transfer') {
            $order_id = createOrder($_SESSION['user_id'], $_SESSION['cart'], $payment_method, $total);
            if ($order_id) {
                $_SESSION['order_id'] = $order_id;
                $_SESSION['payment_amount'] = $total;
                $_SESSION['payment_method'] = $payment_method;
                logActivity($_SESSION['user_id'], "Initiated mobile payment for order #$order_id", 'payment_initiation');
                error_log("Redirecting to PayChangu for order #$order_id");
                // Placeholder for PayChangu payment link
                $paychangu_url = "https://pay.changu.com/pay?amount={$total}&reference={$order_id}&callback_url=https://yourdomain.com/callback.php&email={$_SESSION['user_email']}";
                header("Location: " . $paychangu_url);
                exit;
            } else {
                $_SESSION['message'] = "Failed to create order. Please try again.";
                $_SESSION['message_type'] = "danger";
                error_log("Failed to create order for user #{$_SESSION['user_id']}");
                header("Location: scan_barcode.php");
                exit;
            }
        } else {
            $_SESSION['message'] = "Invalid payment method selected";
            $_SESSION['message_type'] = "danger";
            error_log("Invalid payment method: $payment_method");
            header("Location: scan_barcode.php");
            exit;
        }
    }
    
    // Handle barcode scan
    if (isset($_POST['scan_barcode'])) {
        $barcode = sanitizeInput($_POST['barcode']);
        error_log("Processing scan_barcode: barcode='$barcode', quantity=1");
        
        if (empty($barcode)) {
            $_SESSION['message'] = "Please enter a barcode";
            $_SESSION['message_type'] = "danger";
            error_log("Empty barcode provided for scan_barcode");
            header("Location: scan_barcode.php");
            exit;
        }
        
        $products = getProductByBarcode($barcode);
        if (!empty($products)) {
            $product = $products[0];
            
            if ($product['stock_quantity'] <= 0) {
                $_SESSION['message'] = "Product '{$product['name']}' is out of stock";
                $_SESSION['message_type'] = "danger";
                error_log("Product out of stock: {$product['name']}");
                header("Location: scan_barcode.php");
                exit;
            }
            
            // Check if product already in cart
            $item_index = -1;
            foreach ($_SESSION['cart'] as $index => $item) {
                if ($item['product_id'] == $product['product_id']) {
                    $item_index = $index;
                    break;
                }
            }
            
            $available_stock = $product['stock_quantity'];
            if ($item_index >= 0) {
                $available_stock -= $_SESSION['cart'][$item_index]['quantity'];
            }
            
            if (1 > $available_stock) {
                $_SESSION['message'] = "No more units of '{$product['name']}' available in stock";
                $_SESSION['message_type'] = "danger";
                error_log("Insufficient stock for {$product['name']}: requested=1, available=$available_stock");
                header("Location: scan_barcode.php");
                exit;
            }
            
            if ($item_index >= 0) {
                $_SESSION['cart'][$item_index]['quantity'] += 1;
                error_log("Updated cart item at index $item_index: new quantity=" . $_SESSION['cart'][$item_index]['quantity']);
            } else {
                $_SESSION['cart'][] = [
                    'product_id' => $product['product_id'],
                    'name' => $product['name'],
                    'price' => $product['price'],
                    'quantity' => 1,
                    'barcodes' => $barcode
                ];
                error_log("Added new item to cart: " . print_r(end($_SESSION['cart']), true));
            }
            
            $_SESSION['message'] = "{$product['name']} (MWK" . number_format($product['price'], 2) . ") added to cart";
            $_SESSION['message_type'] = "success";
            header("Location: scan_barcode.php");
            exit;
        }
        
        $_SESSION['message'] = "Product not found for barcode: " . htmlspecialchars($barcode);
        $_SESSION['message_type'] = "danger";
        error_log("Product not found for barcode: $barcode");
        header("Location: scan_barcode.php");
        exit;
    }
}

// Calculate cart total for display
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
    <title>Scan Barcode | Auntie Eddah POS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .notification-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
        }
        .notification {
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 5px;
            color: white;
            max-width: 300px;
            opacity: 1;
            transition: opacity 0.5s ease-out;
        }
        .notification.success { background-color: #28a745; }
        .notification.error { background-color: #dc3545; }
        .notification.danger { background-color: #dc3545; }
        .notification.fade-out { opacity: 0; }
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            z-index: 1000;
        }
        .modal-content {
            background-color: white;
            margin: 15% auto;
            padding: 20px;
            border-radius: 5px;
            width: 90%;
            max-width: 500px;
        }
        .close-modal {
            float: right;
            font-size: 1.5rem;
            cursor: pointer;
        }
        .quantity-controls {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .cart-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 20px;
        }
        .cart-buttons {
            display: flex;
            gap: 10px;
        }
        #cart-total {
            font-weight: bold;
        }
    </style>
</head>
<body>
    <header>
        <div class="logo">
            <i class="fas fa-cash-register"></i> 
            Cashier Panel
        </div>
        <div class="user-profile">
            <span>Welcome, <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Cashier'); ?></span>
        </div>
    </header>

    <aside class="sidebar">
        <nav class="sidebar-nav">
            <a href="dashboard.php">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
            <a href="pending_orders.php">
                <i class="fas fa-clock"></i>
                <span>Pending Orders</span>
            </a>
            <a href="completed_orders.php">
                <i class="fas fa-check-circle"></i>
                <span>Completed Orders</span>
            </a>
            <a href="scan_barcode.php" class="active">
                <i class="fas fa-barcode"></i>
                <span>Scan Barcode</span>
            </a>
            <a href="sales_report.php">
                <i class="fas fa-chart-bar"></i>
                <span>Sales Report</span>
            </a>
            <a href="logout.php">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </nav>
    </aside>

    <main class="main-content">
        <div class="content">
            <?php if (isset($_SESSION['message'])): ?>
                <div class="notification-container">
                    <div class="notification <?php echo $_SESSION['message_type'] ?? 'info'; ?>">
                        <?php 
                        echo htmlspecialchars($_SESSION['message']); 
                        unset($_SESSION['message'], $_SESSION['message_type']); 
                        ?>
                    </div>
                </div>
            <?php endif; ?>

            <section class="section">
                <h2><i class="fas fa-barcode"></i> Scan Barcode</h2>
                
                <div class="barcode-input">
                    <form id="barcodeForm" method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                        <div class="form-group">
                            <label for="barcode">Enter Barcode:</label>
                            <input type="text" id="barcode" name="barcode" class="form-control" 
                                   placeholder="Scan or enter barcode" autofocus>
                            <button type="submit" name="scan_barcode" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Add to Cart
                            </button>
                        </div>
                    </form>
                </div>

                <div id="product-details" style="display: none; margin-top: 20px;">
                    <h3>Product Details</h3>
                    <p><strong>Name:</strong> <span id="product-name"></span></p>
                    <p><strong>Price:</strong> MWK<span id="product-price"></span></p>
                    <p><strong>Category:</strong> <span id="product-category"></span></p>
                </div>

                <div class="cart-table-container">
                    <table class="cart-table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Price</th>
                                <th>Quantity</th>
                                <th>Subtotal</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="cart-items">
                            <?php if (empty($_SESSION['cart'])): ?>
                                <tr><td colspan="5">Cart is empty</td></tr>
                            <?php else: ?>
                                <?php foreach ($_SESSION['cart'] as $index => $item): ?>
                                    <?php $subtotal = $item['price'] * $item['quantity']; ?>
                                    <tr data-item-index="<?php echo $index; ?>">
                                        <td><?php echo htmlspecialchars($item['name']); ?></td>
                                        <td>MWK<?php echo number_format($item['price'], 2); ?></td>
                                        <td>
                                            <div class="quantity-controls">
                                                <button type="button" class="btn btn-sm btn-secondary decrease-quantity" data-item-index="<?php echo $index; ?>">
                                                    <i class="fas fa-minus"></i>
                                                </button>
                                                <span class="quantity"><?php echo $item['quantity']; ?></span>
                                                <button type="button" class="btn btn-sm btn-secondary increase-quantity" data-item-index="<?php echo $index; ?>">
                                                    <i class="fas fa-plus"></i>
                                                </button>
                                            </div>
                                        </td>
                                        <td class="subtotal">MWK<?php echo number_format($subtotal, 2); ?></td>
                                        <td>
                                            <form method="POST">
                                                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                                <input type="hidden" name="item_index" value="<?php echo $index; ?>">
                                                <button type="submit" name="remove_item" class="btn btn-danger btn-sm">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php if (!empty($_SESSION['cart'])): ?>
                    <div class="cart-actions">
                        <div id="cart-total">Total: MWK<?php echo number_format($cart_total, 2); ?></div>
                        <div class="cart-buttons">
                            <button type="button" class="btn btn-primary" onclick="openPaymentModal()" id="proceed-payment-btn">
                                <i class="fas fa-credit-card"></i> Proceed to Payment
                            </button>
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                <button type="submit" name="clear_cart" class="btn btn-warning" 
                                        onclick="return confirm('Are you sure you want to clear the cart?')">
                                    <i class="fas fa-trash-alt"></i> Clear Cart
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Payment Modal -->
                <div id="paymentModal" class="modal">
                    <div class="modal-content">
                        <span class="close-modal" onclick="closePaymentModal()">&times;</span>
                        <h3>Select Payment Method</h3>
                        <form method="POST" id="paymentForm">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                            <div class="form-group">
                                <label for="payment_method">Payment Method:</label>
                                <select name="payment_method" id="payment_method" class="form-control" required>
                                    <option value="">Select Payment Method</option>
                                    <option value="cash">Cash</option>
                                    <option value="mobile_transfer">Mobile Transfer</option>
                                </select>
                            </div>
                            <button type="submit" name="initiate_payment" class="btn btn-primary" id="confirm-payment-btn">
                                <i class="fas fa-check"></i> Confirm Payment
                            </button>
                        </form>
                    </div>
                </div>
            </section>
        </div>

        <footer>
            &copy; <?php echo date('Y'); ?> Auntie Eddah POS
        </footer>
    </main>

    <script>
        // Modal handling
        function openPaymentModal() {
            const cartItems = document.querySelectorAll('#cart-items tr[data-item-index]');
            if (cartItems.length === 0) {
                showNotification('Cart is empty. Please add items before proceeding to payment.', 'danger');
                return;
            }
            document.getElementById('paymentModal').style.display = 'block';
            document.getElementById('payment_method').focus();
        }

        function closePaymentModal() {
            document.getElementById('paymentModal').style.display = 'none';
            document.getElementById('payment_method').value = '';
        }

        window.onclick = function(event) {
            const modal = document.getElementById('paymentModal');
            if (event.target == modal) {
                closePaymentModal();
            }
        }

        // Notification function
        function showNotification(message, type) {
            const notificationContainer = document.querySelector('.notification-container');
            notificationContainer.innerHTML = `
                <div class="notification ${type}">
                    ${message}
                </div>
            `;
            setTimeout(() => {
                const notification = notificationContainer.querySelector('.notification');
                if (notification) {
                    notification.classList.add('fade-out');
                    setTimeout(() => notification.remove(), 500);
                }
            }, 3000);
        }

        // Fetch product details on barcode input
        let debounceTimeout;
        document.getElementById('barcode').addEventListener('input', function() {
            clearTimeout(debounceTimeout);
            const barcode = this.value.trim();
            if (barcode.length < 3) {
                document.getElementById('product-details').style.display = 'none';
                document.getElementById('product-name').textContent = '';
                document.getElementById('product-price').textContent = '';
                document.getElementById('product-category').textContent = '';
                return;
            }

            debounceTimeout = setTimeout(() => {
                fetch(`scan_barcode.php?ajax=product_details&barcode=${encodeURIComponent(barcode)}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'success') {
                            document.getElementById('product-name').textContent = data.data.name;
                            document.getElementById('product-price').textContent = Number(data.data.price).toFixed(2);
                            document.getElementById('product-category').textContent = data.data.category;
                            document.getElementById('product-details').style.display = 'block';
                        } else {
                            document.getElementById('product-details').style.display = 'none';
                            document.getElementById('product-name').textContent = '';
                            document.getElementById('product-price').textContent = '';
                            document.getElementById('product-category').textContent = '';
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching product details:', error);
                        document.getElementById('product-details').style.display = 'none';
                        showNotification('Error fetching product details. Please try again.', 'danger');
                    });
            }, 300);
        });

        // Handle barcode form submission via AJAX
        document.getElementById('barcodeForm').addEventListener('submit', function(event) {
            event.preventDefault();
            
            const form = this;
            const formData = new FormData(form);
            formData.append('ajax', 'add_to_cart');
            
            fetch('scan_barcode.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                // Clear product details
                document.getElementById('product-details').style.display = 'none';
                document.getElementById('product-name').textContent = '';
                document.getElementById('product-price').textContent = '';
                document.getElementById('product-category').textContent = '';
                
                // Show notification
                showNotification(data.message, data.message_type);
                
                if (data.status === 'success') {
                    // Clear barcode input and focus
                    const barcodeInput = document.getElementById('barcode');
                    barcodeInput.value = '';
                    barcodeInput.focus();
                    
                    // Update cart table
                    document.getElementById('cart-items').innerHTML = data.cart_html;
                    const cartTotalDiv = document.getElementById('cart-total');
                    const actionButtons = document.querySelector('.cart-table-container')?.nextElementSibling;
                    if (data.cart_total !== '0.00') {
                        cartTotalDiv.style.display = 'block';
                        cartTotalDiv.textContent = `Total: MWK${data.cart_total}`;
                        if (actionButtons) actionButtons.style.display = 'flex';
                    } else {
                        cartTotalDiv.style.display = 'none';
                        if (actionButtons) actionButtons.style.display = 'none';
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Error adding product to cart. Please try again.', 'danger');
            });
        });

        // Handle quantity controls
        document.addEventListener('click', function(event) {
            const button = event.target.closest('.increase-quantity, .decrease-quantity');
            if (!button) return;
            
            const itemIndex = button.getAttribute('data-item-index');
            const change = button.classList.contains('increase-quantity') ? 1 : -1;
            
            fetch(`scan_barcode.php?ajax=update_quantity&item_index=${itemIndex}&change=${change}`)
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        const row = document.querySelector(`tr[data-item-index="${itemIndex}"]`);
                        row.querySelector('.quantity').textContent = data.new_quantity;
                        row.querySelector('.subtotal').textContent = `MWK${data.subtotal}`;
                        document.getElementById('cart-total').textContent = `Total: MWK${data.cart_total}`;
                        document.getElementById('cart-items').innerHTML = data.cart_html;
                        
                        showNotification(data.message, 'success');
                    } else {
                        showNotification(data.message, 'danger');
                    }
                })
                .catch(error => {
                    console.error('Error updating quantity:', error);
                    showNotification('Error updating quantity. Please try again.', 'danger');
                });
        });

        // Handle payment form submission
        document.getElementById('paymentForm').addEventListener('submit', function(event) {
            event.preventDefault();
            
            const paymentMethod = document.getElementById('payment_method').value;
            if (!paymentMethod) {
                showNotification('Please select a payment method.', 'danger');
                return;
            }
            
            const form = this;
            const formData = new FormData(form);
            
            fetch('scan_barcode.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (response.redirected) {
                    window.location.href = response.url;
                } else {
                    return response.json().then(data => ({ status: response.status, data }));
                }
            })
            .then(result => {
                if (result.data && result.data.message) {
                    showNotification(result.data.message, result.data.message_type || 'danger');
                }
            })
            .catch(error => {
                console.error('Error processing payment:', error);
                showNotification('Error processing payment. Please try again.', 'danger');
            });
        });
    </script>
</body>
</html>
<?php
$conn->close();
?>