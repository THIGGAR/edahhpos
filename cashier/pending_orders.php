<?php
session_start();

require_once 'db_connect.php';
require_once 'functions.php';

// Check authentication
if (!validateUserSession() || !hasRole('cashier')) {
    header("Location: login.php");
    exit;
}

// Handle order confirmation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_payment'])) {
    $order_id = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);
    $csrf_token = filter_input(INPUT_POST, 'csrf_token', FILTER_SANITIZE_STRING);
    $transaction_ref = sanitizeInput($_POST['transaction_ref'] ?? '');
    
    $response = [
        'status' => 'error',
        'message' => 'Failed to confirm order.',
        'message_type' => 'danger',
        'order_id' => $order_id,
        'order_total' => 0
    ];
    
    if (!validateCsrfToken($csrf_token)) {
        $response['message'] = "Invalid security token.";
        if (isset($_POST['ajax'])) {
            header('Content-Type: application/json');
            echo json_encode($response);
            exit;
        }
        $_SESSION['message'] = $response['message'];
        $_SESSION['message_type'] = $response['message_type'];
        header("Location: pending_orders.php");
        exit;
    }
    
    if ($order_id) {
        $order = getOrderById($order_id);
        if ($order) {
            $success = false;
            if ($order['payment_method'] === 'mobile_transfer') {
                if (empty($transaction_ref)) {
                    $response['message'] = 'Transaction reference required for mobile payments.';
                } else {
                    $success = processMobilePayment($order_id, $order['total'], $order['payment_method'], $transaction_ref);
                }
            } else {
                $payment_result = confirmPayment($order_id);
                $success = $payment_result['success'];
                if (!$success) {
                    $response['message'] = $payment_result['error'] ?? 'Unknown error.';
                }
            }
            
            if ($success) {
                logActivity($_SESSION['user_id'], "Confirmed payment for order #$order_id", 'order_confirmation');
                $response = [
                    'status' => 'success',
                    'message' => "Order #$order_id confirmed successfully.",
                    'message_type' => 'success',
                    'order_id' => $order_id,
                    'order_total' => $order['total'],
                    'redirect' => 'completed_orders.php'
                ];
            }
        } else {
            $response['message'] = "Order not found or not pending.";
        }
    }
    
    if (isset($_POST['ajax'])) {
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
    
    $_SESSION['message'] = $response['message'];
    $_SESSION['message_type'] = $response['message_type'];
    if ($response['status'] === 'success') {
        header("Location: completed_orders.php");
    } else {
        header("Location: pending_orders.php");
    }
    exit;
}

// Fetch pending orders from customer orders (system-wide)
$pending_orders = getCustomerPendingOrders();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pending Orders | Auntie Eddah POS</title>
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
        .notification.info { background-color: #17a2b8; }
        .notification.fade-out { opacity: 0; }
    </style>
    <script src="script.js"></script>
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
            <a href="pending_orders.php" class="active">
                <i class="fas fa-clock"></i>
                <span>Pending Orders</span>
            </a>
            <a href="completed_orders.php">
                <i class="fas fa-check-circle"></i>
                <span>Completed Orders</span>
            </a>
            <a href="scan_barcode.php">
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
                <h2><i class="fas fa-clock"></i> Pending Orders (<span id="pending-count"><?php echo count($pending_orders); ?></span>)</h2>
                
                <?php if (empty($pending_orders)): ?>
                    <div style="text-align: center; padding: 40px;">
                        <i class="fas fa-clock" style="font-size: 4rem; color: var(--warning-color); margin-bottom: 20px;"></i>
                        <h3>No Pending Orders</h3>
                        <p>No orders are pending confirmation.</p>
                        <a href="scan_barcode.php" class="btn btn-info">
                            <i class="fas fa-barcode"></i> Create New Order
                        </a>
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
                                    <th>Created At</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pending_orders as $order): ?>
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
                                                    'mobile_transfer' => 'fas fa-mobile-alt',
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
                                                <form method="POST" class="confirm-payment-form">
                                                    <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                                                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                                    <?php if ($order['payment_method'] === 'mobile_transfer'): ?>
                                                        <input type="text" name="transaction_ref" placeholder="Transaction Ref" required style="width: 120px; margin-right: 5px;">
                                                    <?php endif; ?>
                                                    <button type="submit" name="confirm_payment" class="btn btn-success btn-sm">
                                                        <i class="fas fa-check"></i> Confirm
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
                        <p><strong>Total Pending Amount: MWK<span id="total-pending-amount"><?php 
                            $total_pending = array_sum(array_column($pending_orders, 'total'));
                            echo number_format($total_pending, 2); 
                        ?></span></strong></p>
                    </div>
                <?php endif; ?>
            </section>
        </div>

        <footer>
            &copy; <?php echo date('Y'); ?> Auntie Eddah POS
        </footer>
    </main>
</body>
</html>
<?php
$conn->close();
?>