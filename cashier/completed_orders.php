<?php
session_start();

require_once 'db_connect.php';
require_once 'functions.php';

// Check authentication
if (!validateUserSession() || !hasRole('cashier')) {
    header("Location: login.php");
    exit;
}

// Fetch all completed orders system-wide
$completed_orders = getCompletedOrders();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Completed Orders | Auntie Eddah POS</title>
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
            <a href="dashboard.php" class="active">
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
                <h2><i class="fas fa-check-circle"></i> Completed Orders (<span id="completed-count"><?php echo count($completed_orders); ?></span>)</h2>
                
                <?php if (empty($completed_orders)): ?>
                    <div style="text-align: center; padding: 40px;">
                        <i class="fas fa-check-circle" style="font-size: 4rem; color: var(--success-color); margin-bottom: 20px;"></i>
                        <h3>No Completed Orders</h3>
                        <p>No orders have been completed yet.</p>
                        <a href="scan_barcode.php" class="btn btn-info">
                            <i class="fas fa-barcode"></i> Create New Order
                        </a>
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
                                <?php foreach ($completed_orders as $order): ?>
                                    <tr data-order-id="<?php echo $order['order_id']; ?>">
                                        <td><strong>#<?php echo $order['order_id']; ?></strong></td>
                                        <td><?php echo htmlspecialchars(trim(($order['first_name'] ?? '') . ' ' . ($order['last_name'] ?? ''))) ?: 'Anonymous'; ?></td>
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
                            $total_completed = array_sum(array_column($completed_orders, 'total'));
                            echo number_format($total_completed, 2); 
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