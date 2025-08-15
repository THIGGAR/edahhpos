<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'db_connect.php';
$user_id = (int)$_SESSION['user_id'];

// Fetch user's orders with order items
$sql_orders = "
    SELECT 
        o.order_id,
        o.total,
        o.total_amount,
        o.payment_method,
        o.status,
        o.created_at,
        o.updated_at,
        o.transaction_ref,
        p.status as payment_status
    FROM orders o
    LEFT JOIN payments p ON o.transaction_ref = p.transaction_ref
    WHERE o.user_id = ?
    ORDER BY o.created_at DESC
";

$stmt_orders = $conn->prepare($sql_orders);
if (!$stmt_orders) {
    error_log("Failed to prepare orders query: " . $conn->error);
    $orders = [];
} else {
    $stmt_orders->bind_param("i", $user_id);
    $stmt_orders->execute();
    $orders = $stmt_orders->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_orders->close();
}

// Fetch order items for each order
foreach ($orders as &$order) {
    $sql_items = "
        SELECT 
            oi.product_id,
            oi.quantity,
            oi.price,
            p.name as product_name,
            p.image as product_image
        FROM order_items oi
        LEFT JOIN products p ON oi.product_id = p.product_id
        WHERE oi.order_id = ?
    ";
    
    $stmt_items = $conn->prepare($sql_items);
    if ($stmt_items) {
        $stmt_items->bind_param("i", $order['order_id']);
        $stmt_items->execute();
        $order['items'] = $stmt_items->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt_items->close();
    } else {
        $order['items'] = [];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order History - EDAHHPOS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        :root {
            --primary-color: #4a6baf;
            --secondary-color: #ff6b6b;
            --accent-color: #ffcc00;
            --dark-color: #003366;
            --light-color: #f0f4f8;
            --white: #ffffff;
            --text-color: #333333;
            --shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            --shadow-hover: 0 15px 40px rgba(0, 0, 0, 0.15);
            --primary-hover: #3b5490;
            --secondary-hover: #e55555;
            --accent-hover: #e6b800;
            --success-color: #28a745;
            --danger-color: #dc3545;
            --warning-color: #ffc107;
            --info-color: #17a2b8;
            --border-color: #e1e5eb;
            --border-radius: 1rem;
            --spacing-sm: 0.5rem;
            --spacing-md: 1rem;
            --spacing-lg: 1.5rem;
            --spacing-xl: 2rem;
            --font-size-base: clamp(0.875rem, 1.8vw, 1rem);
            --font-size-lg: clamp(1.25rem, 2.5vw, 1.5rem);
            --font-size-xl: clamp(1.5rem, 3.5vw, 1.75rem);
            --font-size-xxl: clamp(2rem, 4vw, 2.5rem);
            --glass-bg: rgba(255, 255, 255, 0.1);
            --glass-border: rgba(255, 255, 255, 0.2);
            --backdrop-blur: blur(20px);
            --transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, var(--light-color) 0%, #e2e8f0 100%);
            color: var(--text-color);
            font-size: var(--font-size-base);
            line-height: 1.6;
            min-height: 100vh;
            padding: var(--spacing-xl);
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .header {
            background: var(--glass-bg);
            backdrop-filter: var(--backdrop-blur);
            border: 1px solid var(--glass-border);
            border-radius: var(--border-radius);
            padding: var(--spacing-xl);
            margin-bottom: var(--spacing-xl);
            box-shadow: var(--shadow);
            text-align: center;
        }

        .header h1 {
            font-size: var(--font-size-xxl);
            font-weight: 700;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: var(--spacing-md);
        }

        .back-btn {
            display: inline-block;
            padding: var(--spacing-sm) var(--spacing-md);
            background: linear-gradient(135deg, var(--primary-color), var(--primary-hover));
            color: var(--white);
            text-decoration: none;
            border-radius: var(--border-radius);
            font-weight: 600;
            transition: var(--transition);
            margin-bottom: var(--spacing-lg);
        }

        .back-btn:hover {
            background: linear-gradient(135deg, var(--secondary-color), var(--secondary-hover));
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }

        .orders-container {
            display: grid;
            gap: var(--spacing-lg);
        }

        .order-card {
            background: var(--glass-bg);
            backdrop-filter: var(--backdrop-blur);
            border: 1px solid var(--glass-border);
            border-radius: var(--border-radius);
            padding: var(--spacing-lg);
            box-shadow: var(--shadow);
            transition: var(--transition);
        }

        .order-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-hover);
        }

        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--spacing-md);
            flex-wrap: wrap;
            gap: var(--spacing-sm);
        }

        .order-id {
            font-size: var(--font-size-lg);
            font-weight: 700;
            color: var(--primary-color);
        }

        .order-status {
            padding: var(--spacing-sm) var(--spacing-md);
            border-radius: var(--border-radius);
            font-weight: 600;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-completed {
            background: rgba(40, 167, 69, 0.1);
            color: var(--success-color);
            border: 1px solid var(--success-color);
        }

        .status-pending {
            background: rgba(255, 193, 7, 0.1);
            color: var(--warning-color);
            border: 1px solid var(--warning-color);
        }

        .status-cancelled {
            background: rgba(220, 53, 69, 0.1);
            color: var(--danger-color);
            border: 1px solid var(--danger-color);
        }

        .order-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: var(--spacing-md);
            margin-bottom: var(--spacing-lg);
        }

        .detail-item {
            display: flex;
            flex-direction: column;
            gap: var(--spacing-sm);
        }

        .detail-label {
            font-weight: 600;
            color: var(--primary-color);
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .detail-value {
            font-weight: 500;
            font-size: var(--font-size-base);
        }

        .order-items {
            border-top: 1px solid var(--border-color);
            padding-top: var(--spacing-lg);
        }

        .items-header {
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: var(--spacing-md);
            font-size: var(--font-size-lg);
        }

        .item-list {
            display: grid;
            gap: var(--spacing-md);
        }

        .order-item {
            display: flex;
            align-items: center;
            gap: var(--spacing-md);
            padding: var(--spacing-md);
            background: rgba(255, 255, 255, 0.5);
            border-radius: var(--border-radius);
            border: 1px solid var(--border-color);
        }

        .item-image {
            width: 60px;
            height: 60px;
            border-radius: var(--border-radius);
            object-fit: cover;
            border: 2px solid var(--border-color);
        }

        .item-details {
            flex: 1;
        }

        .item-name {
            font-weight: 600;
            color: var(--text-color);
            margin-bottom: var(--spacing-sm);
        }

        .item-meta {
            display: flex;
            gap: var(--spacing-lg);
            font-size: 0.875rem;
            color: #666;
        }

        .item-price {
            font-weight: 700;
            color: var(--primary-color);
            font-size: var(--font-size-base);
        }

        .no-orders {
            text-align: center;
            padding: var(--spacing-xl);
            background: var(--glass-bg);
            backdrop-filter: var(--backdrop-blur);
            border: 1px solid var(--glass-border);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
        }

        .no-orders i {
            font-size: 4rem;
            color: var(--primary-color);
            margin-bottom: var(--spacing-lg);
        }

        .no-orders h3 {
            font-size: var(--font-size-xl);
            color: var(--primary-color);
            margin-bottom: var(--spacing-md);
        }

        .no-orders p {
            color: #666;
            margin-bottom: var(--spacing-lg);
        }

        .shop-btn {
            display: inline-block;
            padding: var(--spacing-md) var(--spacing-lg);
            background: linear-gradient(135deg, var(--accent-color), var(--accent-hover));
            color: var(--dark-color);
            text-decoration: none;
            border-radius: var(--border-radius);
            font-weight: 700;
            transition: var(--transition);
        }

        .shop-btn:hover {
            background: linear-gradient(135deg, var(--secondary-color), var(--secondary-hover));
            color: var(--white);
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }

        @media (max-width: 768px) {
            body {
                padding: var(--spacing-md);
            }

            .order-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .order-details {
                grid-template-columns: 1fr;
            }

            .order-item {
                flex-direction: column;
                text-align: center;
            }

            .item-meta {
                justify-content: center;
            }
        }

        @media (prefers-color-scheme: dark) {
            :root {
                --text-color: #e2e8f0;
                --light-color: #1a202c;
                --border-color: #4a5568;
                --glass-bg: rgba(45, 55, 72, 0.1);
                --glass-border: rgba(255, 255, 255, 0.1);
            }
            body {
                background: linear-gradient(135deg, #1a202c 0%, #2d3748 100%);
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <a href="dashboard.php" class="back-btn">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
            <h1><i class="fas fa-history"></i> Order History</h1>
            <p>View all your past orders and their details</p>
        </div>

        <div class="orders-container">
            <?php if (empty($orders)): ?>
                <div class="no-orders">
                    <i class="fas fa-shopping-bag"></i>
                    <h3>No Orders Yet</h3>
                    <p>You haven't placed any orders yet. Start shopping to see your order history here.</p>
                    <a href="dashboard.php?page=home" class="shop-btn">
                        <i class="fas fa-shopping-cart"></i> Start Shopping
                    </a>
                </div>
            <?php else: ?>
                <?php foreach ($orders as $order): ?>
                    <div class="order-card">
                        <div class="order-header">
                            <div class="order-id">
                                <i class="fas fa-receipt"></i> Order #<?php echo htmlspecialchars($order['order_id']); ?>
                            </div>
                            <div class="order-status status-<?php echo strtolower($order['status']); ?>">
                                <?php echo ucfirst($order['status']); ?>
                            </div>
                        </div>

                        <div class="order-details">
                            <div class="detail-item">
                                <div class="detail-label">
                                    <i class="fas fa-calendar"></i> Order Date
                                </div>
                                <div class="detail-value">
                                    <?php echo date('F j, Y g:i A', strtotime($order['created_at'])); ?>
                                </div>
                            </div>

                            <div class="detail-item">
                                <div class="detail-label">
                                    <i class="fas fa-money-bill-wave"></i> Total Amount
                                </div>
                                <div class="detail-value">
                                    MWK <?php echo number_format($order['total_amount'] ?: $order['total'], 2); ?>
                                </div>
                            </div>

                            <div class="detail-item">
                                <div class="detail-label">
                                    <i class="fas fa-credit-card"></i> Payment Method
                                </div>
                                <div class="detail-value">
                                    <?php echo htmlspecialchars($order['payment_method'] ?: 'N/A'); ?>
                                </div>
                            </div>

                            <?php if (!empty($order['transaction_ref'])): ?>
                            <div class="detail-item">
                                <div class="detail-label">
                                    <i class="fas fa-hashtag"></i> Transaction Ref
                                </div>
                                <div class="detail-value">
                                    <?php echo htmlspecialchars($order['transaction_ref']); ?>
                                </div>
                            </div>
                            <?php endif; ?>

                            <?php if (!empty($order['payment_status'])): ?>
                            <div class="detail-item">
                                <div class="detail-label">
                                    <i class="fas fa-check-circle"></i> Payment Status
                                </div>
                                <div class="detail-value">
                                    <span style="color: <?php echo $order['payment_status'] === 'completed' ? 'var(--success-color)' : ($order['payment_status'] === 'failed' ? 'var(--danger-color)' : 'var(--warning-color)'); ?>">
                                        <?php echo ucfirst($order['payment_status']); ?>
                                    </span>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($order['items'])): ?>
                        <div class="order-items">
                            <div class="items-header">
                                <i class="fas fa-box"></i> Order Items (<?php echo count($order['items']); ?>)
                            </div>
                            <div class="item-list">
                                <?php foreach ($order['items'] as $item): ?>
                                    <div class="order-item">
                                        <?php if (!empty($item['product_image'])): ?>
                                            <img src="<?php echo htmlspecialchars($item['product_image']); ?>" 
                                                 alt="<?php echo htmlspecialchars($item['product_name'] ?: 'Product'); ?>" 
                                                 class="item-image">
                                        <?php else: ?>
                                            <div class="item-image" style="background: var(--border-color); display: flex; align-items: center; justify-content: center;">
                                                <i class="fas fa-image" style="color: #999;"></i>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="item-details">
                                            <div class="item-name">
                                                <?php echo htmlspecialchars($item['product_name'] ?: 'Unknown Product'); ?>
                                            </div>
                                            <div class="item-meta">
                                                <span><i class="fas fa-sort-numeric-up"></i> Qty: <?php echo $item['quantity']; ?></span>
                                                <span><i class="fas fa-tag"></i> Unit Price: MWK <?php echo number_format($item['price'], 2); ?></span>
                                            </div>
                                        </div>
                                        
                                        <div class="item-price">
                                            MWK <?php echo number_format($item['price'] * $item['quantity'], 2); ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>

