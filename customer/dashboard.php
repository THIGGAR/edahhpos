<?php
session_start();

require_once 'db_connect.php';
require_once '../functions.php';

// Generate CSRF token if not set
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Check if user is logged in and has customer role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header("Location: ../login.php");
    exit;
}

// Determine current page
$page = isset($_GET['page']) ? htmlspecialchars($_GET['page']) : 'home';

// Fetch data with optimized queries
$promotions = getPromotionalItems($conn);
$cart = getCart($conn, $_SESSION['user_id']);
$profile = getProfile($conn, $_SESSION['user_id']);

// Fetch all orders for history
$stmt = $conn->prepare("SELECT order_id, total_amount AS total, status, payment_method, created_at 
                        FROM orders WHERE user_id = ?");
if (!$stmt) {
    error_log("Failed to prepare order query: " . $conn->error);
    $notifications = [];
} else {
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        error_log("Invalid CSRF token for user {$_SESSION['user_id']}");
        $_SESSION['error'] = "Invalid CSRF token";
        header("Location: dashboard.php?page=$page");
        exit;
    }

    try {
        if ($_POST['action'] === 'add_to_cart') {
            addToCart($conn, $_SESSION['user_id'], $_POST['product_id'], 1);
            $_SESSION['message'] = 'Item added to cart!';
            header("Location: dashboard.php?page=products&action=added");
            exit;
        } elseif ($_POST['action'] === 'buy_now') {
            addToCart($conn, $_SESSION['user_id'], $_POST['product_id'], 1);
            header("Location: dashboard.php?page=cart");
            exit;
        } elseif ($_POST['action'] === 'update_cart') {
            updateCartItem($conn, $_SESSION['user_id'], $_POST['product_id'], $_POST['quantity']);
            $_SESSION['message'] = 'Cart updated!';
            header("Location: dashboard.php?page=cart");
            exit;
        } elseif ($_POST['action'] === 'remove_from_cart') {
            updateCartItem($conn, $_SESSION['user_id'], $_POST['product_id'], 0);
            $_SESSION['message'] = 'Item removed from cart!';
            header("Location: dashboard.php?page=cart");
            exit;
        } elseif ($_POST['action'] === 'update_profile') {
            updateProfile($conn, $_SESSION['user_id'], $_POST['first_name'], $_POST['last_name'], $_POST['phone'], $_POST['password'] ?: null);
            $_SESSION['message'] = 'Profile updated successfully!';
            header("Location: dashboard.php?page=profile");
            exit;
        }
    } catch (Exception $e) {
        error_log("Action failed for user {$_SESSION['user_id']}: " . $e->getMessage());
        $_SESSION['error'] = "Action failed: " . htmlspecialchars($e->getMessage());
        header("Location: dashboard.php?page=$page");
        exit;
    }
}

// Fetch products with search functionality
$search = isset($_GET['search']) ? "%" . $conn->real_escape_string($_GET['search']) . "%" : null;
if ($search) {
    $stmt = $conn->prepare("SELECT product_id, name, price, image, category, expiry_date, is_promotion 
                            FROM products WHERE name LIKE ? OR category LIKE ?");
    if ($stmt) {
        $stmt->bind_param("ss", $search, $search);
        $stmt->execute();
        $products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } else {
        error_log("Failed to prepare product search query: " . $conn->error);
        $products = [];
    }
} else {
    $stmt = $conn->prepare("SELECT product_id, name, price, image, category, expiry_date, is_promotion FROM products");
    if ($stmt) {
        $stmt->execute();
        $products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } else {
        error_log("Failed to prepare product query: " . $conn->error);
        $products = [];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Dashboard | Auntie Eddah POS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <header>
        <div class="logo">
            <i class="fas fa-shopping-basket"></i>
            <span>Customer Dashboard</span>
        </div>
        <button class="mobile-menu-toggle" aria-label="Toggle Sidebar" onclick="toggleSidebar()">
            <i class="fas fa-bars"></i>
        </button>
        <div class="user-info">
            <i class="fas fa-user-circle"></i>
            <span><?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
        </div>
    </header>

    <aside class="sidebar" id="sidebar">
        <nav class="sidebar-nav">
            <a href="?page=home" class="<?php echo $page === 'home' ? 'active' : ''; ?>" aria-current="<?php echo $page === 'home' ? 'page' : 'false'; ?>">
                <i class="fas fa-home"></i> Home
            </a>
            <a href="?page=products" class="<?php echo $page === 'products' ? 'active' : ''; ?>" aria-current="<?php echo $page === 'products' ? 'page' : 'false'; ?>">
                <i class="fas fa-box-open"></i> Products
            </a>
            <a href="?page=cart" class="<?php echo $page === 'cart' ? 'active' : ''; ?>" aria-current="<?php echo $page === 'cart' ? 'page' : 'false'; ?>">
                <i class="fas fa-shopping-cart"></i> Cart (<?php echo count($cart); ?>)
            </a>
            <a href="?page=history" class="<?php echo $page === 'history' ? 'active' : ''; ?>" aria-current="<?php echo $page === 'history' ? 'page' : 'false'; ?>">
                <i class="fas fa-history"></i> Order History
            </a>
            <a href="?page=profile" class="<?php echo $page === 'profile' ? 'active' : ''; ?>" aria-current="<?php echo $page === 'profile' ? 'page' : 'false'; ?>">
                <i class="fas fa-user"></i> Profile
            </a>
            <a href="logout.php">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </nav>
    </aside>

    <main class="main-content">
        <div class="toast" id="toast" role="alert" aria-live="assertive"></div>

        <?php if (isset($_SESSION['message'])): ?>
            <div class="status-badge badge-success"><?php echo htmlspecialchars($_SESSION['message']); ?></div>
            <?php unset($_SESSION['message']); ?>
        <?php elseif (isset($_SESSION['error'])): ?>
            <div class="status-badge badge-danger"><?php echo htmlspecialchars($_SESSION['error']); ?></div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <div class="search-bar">
            <form action="dashboard.php" method="GET" role="search">
                <input type="text" name="search" placeholder="Search products..." value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>" aria-label="Search products">
                <button type="submit" aria-label="Search"><i class="fas fa-search"></i></button>
            </form>
        </div>

        <?php if ($page === 'home'): ?>
            <section>
                <h1 class="section-title"><i class="fas fa-home"></i> Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?>!</h1>
                <h2 class="section-subtitle">Promotions</h2>
                <?php if (empty($promotions)): ?>
                    <div class="no-products">
                        <i class="fas fa-box-open"></i>
                        <h3>No promotions available</h3>
                        <p>Check back later for exciting offers!</p>
                    </div>
                <?php else: ?>
                    <div class="promotions-grid">
                        <?php foreach ($promotions as $product): ?>
                            <div class="promotion-card">
                                <div class="image-container">
                                    <img src="<?php echo htmlspecialchars($product['image'] ? '/inventory/uploads/' . basename($product['image']) : 'https://via.placeholder.com/150'); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" loading="lazy">
                                    <?php if (!empty($product['is_promotion'])): ?>
                                        <span class="promotion-badge">PROMOTION</span>
                                    <?php endif; ?>
                                </div>
                                <h3><?php echo htmlspecialchars($product['name']); ?></h3>
                                <p>K<?php echo number_format($product['price'], 2); ?></p>
                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                    <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">
                                    <button type="submit" name="action" value="add_to_cart" class="add-to-cart">Add to Cart</button>
                                    <button type="submit" name="action" value="buy_now" class="buy-now"><i class="fas fa-bolt"></i> Buy Now</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <h2 class="section-subtitle">Notifications</h2>
                <?php if (empty($notifications)): ?>
                    <div class="no-orders">
                        <i class="fas fa-bell"></i>
                        <h3>No notifications</h3>
                        <p>Your order updates will appear here.</p>
                    </div>
                <?php else: ?>
                    <ul class="notifications-list">
                        <?php foreach ($notifications as $notification): ?>
                            <li>Order #<?php echo htmlspecialchars($notification['order_id']); ?> - K<?php echo number_format($notification['total'], 2); ?> - <?php echo htmlspecialchars($notification['status']); ?> (<?php echo htmlspecialchars($notification['payment_method']); ?>) - <?php echo date('M d, Y', strtotime($notification['created_at'])); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>

        <?php elseif ($page === 'products'): ?>
            <section>
                <div class="products-header">
                    <h1 class="section-title"><i class="fas fa-box-open"></i> Browse Products</h1>
                    <div class="sort-select">
                        <select id="sortProducts" aria-label="Sort products">
                            <option value="name_asc">Name (A-Z)</option>
                            <option value="name_desc">Name (Z-A)</option>
                            <option value="price_asc">Price (Low to High)</option>
                            <option value="price_desc">Price (High to Low)</option>
                        </select>
                    </div>
                </div>

                <div class="category-filter-container">
                    <button class="category-filter <?php echo !$search ? 'active' : ''; ?>" data-category="all">All Products</button>
                    <?php 
                    $categories = array_unique(array_filter(array_column($products, 'category')));
                    foreach ($categories as $category): ?>
                        <button class="category-filter" data-category="<?php echo htmlspecialchars($category); ?>"><?php echo htmlspecialchars($category); ?></button>
                    <?php endforeach; ?>
                </div>

                <div class="products-grid">
                    <?php if (empty($products)): ?>
                        <div class="no-products">
                            <i class="fas fa-box-open"></i>
                            <h3>No products found</h3>
                            <p>Try adjusting your search or come back later</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($products as $product): ?>
                            <div class="product-card" data-category="<?php echo htmlspecialchars($product['category'] ?? ''); ?>">
                                <div class="image-container">
                                    <img src="<?php echo htmlspecialchars($product['image'] ? '../inventory/' .$product['image'] : 'https://via.placeholder.com/150'); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" loading="lazy">
                                    <?php if ($product['is_promotion']): ?>
                                        <span class="promotion-badge">PROMOTION</span>
                                    <?php endif; ?>
                                </div>
                                <div class="info">
                                    <div class="info-header">
                                        <div>
                                            <h3><?php echo htmlspecialchars($product['name']); ?></h3>
                                            <?php if ($product['category']): ?>
                                                <span class="category"><?php echo htmlspecialchars($product['category']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <span class="price">K<?php echo number_format($product['price'], 2); ?></span>
                                    </div>
                                    <?php if ($product['expiry_date']): ?>
                                        <div class="expiry">
                                            <i class="fas fa-calendar-alt"></i>
                                            Expires: <?php echo date('M d, Y', strtotime($product['expiry_date'])); ?>
                                        </div>
                                    <?php endif; ?>
                                    <div class="actions">
                                        <form method="POST">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                            <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">
                                            <button type="submit" name="action" value="add_to_cart" class="add-to-cart">Add to Cart</button>
                                        </form>
                                        <form method="POST">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                            <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">
                                            <button type="submit" name="action" value="buy_now" class="buy-now"><i class="fas fa-bolt"></i> Buy Now</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>

        <?php elseif ($page === 'profile'): ?>
            <section class="profile-section">
                <h2><i class="fas fa-user-circle"></i> My Profile</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="update_profile">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <div class="form-group">
                        <label for="first_name">First Name</label>
                        <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($profile['first_name']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="last_name">Last Name</label>
                        <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($profile['last_name']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($profile['email']); ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label for="phone">Phone</label>
                        <input type="text" id="phone" name="phone" value="<?php echo htmlspecialchars($profile['phone']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="password">New Password</label>
                        <input type="password" id="password" name="password" placeholder="Leave blank to keep current password">
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Update Profile</button>
                    </div>
                </form>
            </section>

        <?php elseif ($page === 'cart'): ?>
            <section class="cart-section">
                <h2><i class="fas fa-shopping-cart"></i> My Cart</h2>
                <?php if (empty($cart)): ?>
                    <div class="empty-cart">
                        <i class="fas fa-cart-arrow-down"></i>
                        <h3>Your cart is empty</h3>
                        <p>Browse products and add items to your cart.</p>
                        <a href="?page=products" class="btn btn-primary">Shop Now</a>
                    </div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Price</th>
                                <th>Quantity</th>
                                <th>Total</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $grandTotal = 0; 
                            foreach ($cart as $item): 
                                if (!isset($item['product_id'], $item['name'], $item['price'], $item['quantity'])) {
                                    error_log("Invalid cart item for user {$_SESSION['user_id']}: " . json_encode($item));
                                    continue;
                                }
                                $itemTotal = $item['price'] * $item['quantity'];
                                $grandTotal += $itemTotal;
                            ?>
                            <tr>
                                <td class="product-info">
                                    <img src="<?php echo htmlspecialchars($item['image'] ? '/inventory/uploads/' . basename($item['image']) : 'https://via.placeholder.com/50'); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" loading="lazy">
                                    <span><?php echo htmlspecialchars($item['name']); ?></span>
                                </td>
                                <td>K<?php echo number_format($item['price'], 2); ?></td>
                                <td>
                                    <form method="POST">
                                        <input type="hidden" name="action" value="update_cart">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                        <input type="hidden" name="product_id" value="<?php echo $item['product_id']; ?>">
                                        <input type="number" name="quantity" value="<?php echo $item['quantity']; ?>" min="1" aria-label="Quantity">
                                        <button type="submit" class="update-quantity" title="Update Quantity"><i class="fas fa-sync"></i></button>
                                    </form>
                                </td>
                                <td>K<?php echo number_format($itemTotal, 2); ?></td>
                                <td>
                                    <form method="POST">
                                        <input type="hidden" name="action" value="remove_from_cart">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                        <input type="hidden" name="product_id" value="<?php echo $item['product_id']; ?>">
                                        <button type="submit" class="remove-item" title="Remove Item"><i class="fas fa-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div class="cart-footer">
                        <span class="grand-total">Grand Total: K<?php echo number_format($grandTotal, 2); ?></span>
                        <a href="?page=products" class="add-more"><i class="fas fa-plus"></i> Add More Products</a>
                        <form method="POST" action="pay.php">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                            <?php
                            try {
                                if (empty($cart) || empty($profile['first_name']) || empty($profile['last_name']) || empty($profile['email'])) {
                                    throw new Exception("Invalid cart or profile data");
                                }
                                $transaction_ref = 'EDAHHPOS' . uniqid() . '-' . time();
                                $_SESSION['transaction_ref'] = $transaction_ref;
                                $_SESSION['cart_data'] = [
                                    'firstname' => $profile['first_name'],
                                    'surname' => $profile['last_name'],
                                    'email' => $profile['email'],
                                    'amount' => $grandTotal,
                                    'items' => $cart
                                ];

                                $sql = "INSERT INTO payments (transaction_ref, email, firstname, surname, amount, status) VALUES (?, ?, ?, ?, ?, ?)";
                                $stmt = $conn->prepare($sql);
                                if (!$stmt) {
                                    throw new Exception("Failed to prepare payment insertion: " . $conn->error);
                                }
                                $status = 'pending';
                                $stmt->bind_param("ssssds", $transaction_ref, $profile['email'], $profile['first_name'], $profile['last_name'], $grandTotal, $status);
                                if (!$stmt->execute()) {
                                    throw new Exception("Failed to insert payment: " . $stmt->error);
                                }
                                $stmt->close();
                            } catch (Exception $e) {
                                error_log("Payment initiation error for user {$_SESSION['user_id']}: " . $e->getMessage());
                                $error_message = "Payment setup failed: " . htmlspecialchars($e->getMessage());
                            }
                            ?>
                            <?php if (isset($error_message)): ?>
                                <div class="status-badge badge-danger"><?php echo $error_message; ?></div>
                                <button disabled class="btn btn-primary proceed-payment" aria-disabled="true"><i class="fas fa-credit-card"></i> Payment Not Available</button>
                            <?php else: ?>
                                <button type="submit" class="btn btn-primary proceed-payment"><i class="fas fa-credit-card"></i> Proceed to Payment</button>
                            <?php endif; ?>
                        </form>
                    </div>
                <?php endif; ?>
            </section>

        <?php elseif ($page === 'history'): ?>
            <section class="history-section">
                <h2><i class="fas fa-history"></i> Order History</h2>
                <?php if (empty($notifications)): ?>
                    <div class="no-orders">
                        <i class="fas fa-history"></i>
                        <h3>No orders yet</h3>
                        <p>Your order history will appear here.</p>
                    </div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th>Payment Method</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($notifications as $notification): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($notification['order_id']); ?></td>
                                    <td>K<?php echo number_format($notification['total'], 2); ?></td>
                                    <td><?php echo htmlspecialchars($notification['status']); ?></td>
                                    <td><?php echo htmlspecialchars($notification['payment_method'] ?: 'N/A'); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($notification['created_at'])); ?></td>
                                    <td>
                                        <button class="view-details btn btn-primary" data-order-id="<?php echo $notification['order_id']; ?>">View Details</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <div class="modal" id="order-details-modal">
            <div class="modal-content">
                <span class="close">&times;</span>
                <div id="order-details-content"></div>
            </div>
        </div>
    </main>

    <script src="script.js"></script>
</body>
</html>
<?php
$conn->close();
?>