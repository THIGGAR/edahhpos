// File: payment_gateway.php
<?php
session_start();

require_once 'db_connect.php';
require_once 'functions.php';

// Check authentication
if (!validateUserSession() || !hasRole('cashier')) {
    header("Location: login.php");
    exit;
}

$order_id = filter_input(INPUT_GET, 'order_id', FILTER_VALIDATE_INT);
$amount = filter_input(INPUT_GET, 'amount', FILTER_VALIDATE_FLOAT);
$payment_method = filter_input(INPUT_GET, 'method', FILTER_SANITIZE_STRING);

if (!$order_id || !$amount || !$payment_method || !in_array($payment_method, ['mpamba', 'airtel_money'])) {
    $_SESSION['message'] = "Invalid payment request.";
    $_SESSION['message_type'] = "danger";
    header("Location: scan_barcode.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_payment'])) {
    $csrf_token = filter_input(INPUT_POST, 'csrf_token', FILTER_SANITIZE_STRING);
    
    if (!validateCsrfToken($csrf_token)) {
        $_SESSION['message'] = "Invalid security token.";
        $_SESSION['message_type'] = "danger";
        header("Location: payment_gateway.php?order_id=$order_id&amount=$amount&method=$payment_method");
        exit;
    }
    
    if (processMobilePayment($order_id, $amount, $payment_method)) {
        confirmPayment($order_id);
        logActivity($_SESSION['user_id'], "Completed mobile payment for order #$order_id", 'payment');
        $_SESSION['message'] = "Payment for order #$order_id completed successfully.";
        $_SESSION['message_type'] = "success";
        header("Location: completed_orders.php");
        exit;
    } else {
        $_SESSION['message'] = "Failed to process payment for order #$order_id.";
        $_SESSION['message_type'] = "danger";
        header("Location: payment_gateway.php?order_id=$order_id&amount=$amount&method=$payment_method");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Gateway | Auntie Eddah POS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
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
            <section class="section">
                <h2><i class="fas fa-credit-card"></i> Complete Mobile Payment</h2>
                
                <?php if (isset($_SESSION['message'])): ?>
                    <div class="message <?php echo $_SESSION['message_type'] ?? 'info'; ?>">
                        <?php 
                        echo htmlspecialchars($_SESSION['message']); 
                        unset($_SESSION['message'], $_SESSION['message_type']); 
                        ?>
                    </div>
                <?php endif; ?>

                <p><strong>Order ID:</strong> #<?php echo $order_id; ?></p>
                <p><strong>Amount:</strong> MWK<?php echo number_format($amount, 2); ?></p>
                <p><strong>Payment Method:</strong> <?php echo ucfirst(str_replace('_', ' ', $payment_method)); ?></p>
                
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                    <p>Simulated payment gateway: Click below to complete the payment.</p>
                    <button type="submit" name="complete_payment" class="btn btn-success">
                        <i class="fas fa-check"></i> Complete Payment
                    </button>
                    <a href="scan_barcode.php" class="btn btn-danger">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </form>
            </section>
        </div>

        <footer>
            &copy; <?php echo date('Y'); ?> Auntie Eddah POS
        </footer>
    </main>
</body>
</html>