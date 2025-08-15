<?php
require_once __DIR__ . '/db_connect.php';

/**
 * AUTHENTICATION FUNCTIONS
 */

function login($email, $password) {
    global $conn;
    $stmt = $conn->prepare("SELECT user_id, first_name, last_name, email, password, role FROM users WHERE email = ? AND is_active = 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['first_name'] = $user['first_name'];
            $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
            $_SESSION['role'] = $user['role'];
            
            // Update last active time
            $stmt = $conn->prepare("UPDATE users SET last_active = NOW() WHERE user_id = ?");
            $stmt->bind_param("i", $user['user_id']);
            $stmt->execute();
            
            // Log login activity
            logActivity($conn, $user['user_id'], 'login', 'User logged in successfully');
            
            return true;
        }
    }
    $stmt->close();
    return false;
}

function logout() {
    if (isset($_SESSION['user_id'])) {
        global $conn;
        logActivity($conn, $_SESSION['user_id'], 'logout', 'User logged out');
    }
    session_destroy();
    header("Location: login.php");
    exit();
}

function redirectUnlessRole($required_role) {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== $required_role) {
        header('Location: login.php');
        exit();
    }
}

/**
 * USER MANAGEMENT FUNCTIONS
 */

function getUsers($conn, $role = null, $active_only = true, $search = '') {
    $query = "SELECT user_id, first_name, last_name, email, role, last_active, is_active, created_at 
              FROM users WHERE 1=1";
    $params = [];
    $types = '';
    
    if ($active_only) {
        $query .= " AND is_active = 1";
    }
    
    if ($role) {
        $query .= " AND role = ?";
        $params[] = $role;
        $types .= 's';
    }
    
    if ($search) {
        $query .= " AND (CONCAT(first_name, ' ', last_name) LIKE ? OR email LIKE ?)";
        $searchTerm = "%$search%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $types .= 'ss';
    }
    
    $query .= " ORDER BY last_active DESC";
    $stmt = $conn->prepare($query);
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $users = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $users;
}

function getUserById($conn, $user_id) {
    $stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    return $user;
}

function addUser($first_name, $last_name, $email, $role, $phone = null) {
    global $conn;
    
    // Check if email already exists
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $stmt->close();
        throw new Exception("Email already exists");
    }
    $stmt->close();
    
    $temp_password = bin2hex(random_bytes(8));
    $hashed_password = password_hash($temp_password, PASSWORD_DEFAULT);
    
    $stmt = $conn->prepare("INSERT INTO users (first_name, last_name, email, password, role, phone, is_active, created_at) VALUES (?, ?, ?, ?, ?, ?, 1, NOW())");
    $stmt->bind_param("ssssss", $first_name, $last_name, $email, $hashed_password, $role, $phone);
    $stmt->execute();
    $user_id = $conn->insert_id;
    $stmt->close();
    
    // Log user creation
    logActivity($conn, $_SESSION['user_id'] ?? 0, 'user_created', "Created user: $first_name $last_name ($email)");
    
    return ['user_id' => $user_id, 'temp_password' => $temp_password];
}

function updateUser($user_id, $first_name, $last_name, $email, $role, $is_active, $phone = null) {
    global $conn;
    
    // Check if email already exists for other users
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
    $stmt->bind_param("si", $email, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $stmt->close();
        throw new Exception("Email already exists");
    }
    $stmt->close();
    
    $stmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, role = ?, is_active = ?, phone = ?, updated_at = NOW() WHERE user_id = ?");
    $stmt->bind_param("ssssisi", $first_name, $last_name, $email, $role, $is_active, $phone, $user_id);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();
    
    if ($affected > 0) {
        logActivity($conn, $_SESSION['user_id'] ?? 0, 'user_updated', "Updated user: $first_name $last_name (ID: $user_id)");
    }
    
    return $affected > 0;
}

function resetUserPassword($user_id) {
    global $conn;
    $temp_password = bin2hex(random_bytes(8));
    $hashed_password = password_hash($temp_password, PASSWORD_DEFAULT);
    
    $stmt = $conn->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE user_id = ?");
    $stmt->bind_param("si", $hashed_password, $user_id);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();
    
    if ($affected > 0) {
        logActivity($conn, $_SESSION['user_id'] ?? 0, 'password_reset', "Reset password for user ID: $user_id");
    }
    
    return $affected > 0 ? $temp_password : false;
}

function changeUserPassword($user_id, $new_password) {
    global $conn;
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    
    $stmt = $conn->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE user_id = ?");
    $stmt->bind_param("si", $hashed_password, $user_id);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();
    
    if ($affected > 0) {
        logActivity($conn, $_SESSION['user_id'] ?? 0, 'password_changed', "Changed password for user ID: $user_id");
    }
    
    return $affected > 0;
}

function updateProfile($user_id, $first_name, $last_name, $phone, $password = null) {
    global $conn;
    if ($password) {
        $password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, phone = ?, password = ?, updated_at = NOW() WHERE user_id = ?");
        $stmt->bind_param("ssssi", $first_name, $last_name, $phone, $password, $user_id);
    } else {
        $stmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, phone = ?, updated_at = NOW() WHERE user_id = ?");
        $stmt->bind_param("sssi", $first_name, $last_name, $phone, $user_id);
    }
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();
    
    if ($affected > 0) {
        logActivity($conn, $user_id, 'profile_updated', "Updated profile information");
    }
    
    return $affected > 0;
}

function getProfile($user_id) {
    global $conn;
    $stmt = $conn->prepare("SELECT first_name, last_name, email, phone, role, created_at FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $profile = $result->fetch_assoc();
    $stmt->close();
    return $profile;
}

function deleteUser($user_id) {
    global $conn;
    
    // Get user info before deletion for logging
    $user = getUserById($conn, $user_id);
    if (!$user) {
        return 0;
    }
    
    // Prevent deletion of shop managers
    if ($user['role'] === 'shop_manager') {
        throw new Exception("Cannot delete shop manager accounts");
    }
    
    $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ? AND role != 'shop_manager'");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();
    
    if ($affected > 0) {
        logActivity($conn, $_SESSION['user_id'] ?? 0, 'user_deleted', "Deleted user: {$user['first_name']} {$user['last_name']} (ID: $user_id)");
    }
    
    return $affected;
}

function archiveUser($user_id) {
    global $conn;
    $stmt = $conn->prepare("UPDATE users SET is_active = 0, updated_at = NOW() WHERE user_id = ? AND role != 'shop_manager'");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();
    
    if ($affected > 0) {
        logActivity($conn, $_SESSION['user_id'] ?? 0, 'user_archived', "Archived user ID: $user_id");
    }
    
    return $affected > 0;
}

/**
 * PRODUCT FUNCTIONS
 */

function getProducts($conn, $search = '', $category = null, $visible_only = false) {
    $query = "SELECT * FROM products WHERE 1=1";
    $params = [];
    $types = '';
    
    if (!empty($search)) {
        $query .= " AND (name LIKE ? OR description LIKE ? OR barcode = ?)";
        $searchTerm = "%$search%";
        $params = array_merge($params, [$searchTerm, $searchTerm, $search]);
        $types .= 'sss';
    }
    
    if ($category !== null) {
        $query .= " AND category = ?";
        $params[] = $category;
        $types .= 's';
    }
    
    if ($visible_only) {
        $query .= " AND customer_visible = 1";
    }
    
    $query .= " ORDER BY name ASC";
    
    $stmt = $conn->prepare($query);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

function getProductById($conn, $product_id) {
    $stmt = $conn->prepare("SELECT * FROM products WHERE product_id = ?");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $product = $result->fetch_assoc();
    $stmt->close();
    return $product;
}

function getPromotionalItems() {
    global $conn;
    $result = $conn->query("SELECT * FROM products WHERE is_promotion = 1 AND customer_visible = 1 LIMIT 4");
    return $result->fetch_all(MYSQLI_ASSOC);
}

function getProductByBarcode($barcode) {
    global $conn;
    $stmt = $conn->prepare("SELECT * FROM products WHERE barcode = ?");
    $stmt->bind_param("s", $barcode);
    $stmt->execute();
    $result = $stmt->get_result();
    $product = $result->fetch_assoc();
    $stmt->close();
    return $product;
}

function addProduct($name, $price, $barcode, $description, $image, $category, $is_promotion, $customer_visible) {
    global $conn;
    
    // Check if barcode already exists
    if ($barcode && getProductByBarcode($barcode)) {
        throw new Exception("Barcode already exists");
    }
    
    $stmt = $conn->prepare("INSERT INTO products (name, price, barcode, description, image, category, is_promotion, customer_visible, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("sdsssiii", $name, $price, $barcode, $description, $image, $category, $is_promotion, $customer_visible);
    $stmt->execute();
    $product_id = $conn->insert_id;
    $stmt->close();
    
    logActivity($conn, $_SESSION['user_id'] ?? 0, 'product_created', "Created product: $name (ID: $product_id)");
    
    return $product_id;
}

function updateProduct($product_id, $category, $name, $price, $description, $is_promotion, $customer_visible) {
    global $conn;
    $stmt = $conn->prepare("UPDATE products SET category = ?, name = ?, price = ?, description = ?, is_promotion = ?, customer_visible = ?, updated_at = NOW() WHERE product_id = ?");
    $stmt->bind_param("ssdssii", $category, $name, $price, $description, $is_promotion, $customer_visible, $product_id);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();
    
    if ($affected > 0) {
        logActivity($conn, $_SESSION['user_id'] ?? 0, 'product_updated', "Updated product: $name (ID: $product_id)");
    }
    
    return $affected > 0;
}

function deleteProduct($product_id) {
    global $conn;
    
    // Get product info before deletion for logging
    $product = getProductById($conn, $product_id);
    if (!$product) {
        return 0;
    }
    
    $stmt = $conn->prepare("DELETE FROM products WHERE product_id = ?");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();
    
    if ($affected > 0) {
        logActivity($conn, $_SESSION['user_id'] ?? 0, 'product_deleted', "Deleted product: {$product['name']} (ID: $product_id)");
    }
    
    return $affected;
}

function getAllProductCategories($conn) {
    $categories = [];
    $query = "SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND category != '' ORDER BY category";
    $result = $conn->query($query);
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $categories[] = $row['category'];
        }
        $result->free();
    } else {
        error_log("MySQLi query failed in getAllProductCategories: " . $conn->error);
    }
    
    return $categories;
}

/**
 * CART & ORDER FUNCTIONS
 */

function addToCart($user_id, $product_id, $quantity = 1) {
    global $conn;
    $product = getProductById($conn, $product_id);
    if (!$product) {
        return false;
    }
    
    $stmt = $conn->prepare("SELECT * FROM cart WHERE user_id = ? AND product_id = ?");
    $stmt->bind_param("ii", $user_id, $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $stmt = $conn->prepare("UPDATE cart SET quantity = quantity + ?, updated_at = NOW() WHERE user_id = ? AND product_id = ?");
        $stmt->bind_param("iii", $quantity, $user_id, $product_id);
    } else {
        $stmt = $conn->prepare("INSERT INTO cart (user_id, product_id, quantity, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->bind_param("iii", $user_id, $product_id, $quantity);
    }
    
    $stmt->execute();
    $stmt->close();
    return true;
}

function getCart($user_id) {
    global $conn;
    $stmt = $conn->prepare("SELECT c.*, p.name, p.price, p.image, p.barcode 
                          FROM cart c 
                          JOIN products p ON c.product_id = p.product_id
                          WHERE c.user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $cart = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $cart;
}

function updateCartItem($user_id, $product_id, $quantity) {
    global $conn;
    if ($quantity <= 0) {
        $stmt = $conn->prepare("DELETE FROM cart WHERE user_id = ? AND product_id = ?");
        $stmt->bind_param("ii", $user_id, $product_id);
    } else {
        $stmt = $conn->prepare("UPDATE cart SET quantity = ?, updated_at = NOW() WHERE user_id = ? AND product_id = ?");
        $stmt->bind_param("iii", $quantity, $user_id, $product_id);
    }
    $stmt->execute();
    $stmt->close();
    return true;
}

function clearCart($user_id) {
    global $conn;
    $stmt = $conn->prepare("DELETE FROM cart WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();
    return true;
}

function createOrder($user_id, $payment_method, $cart_items = null) {
    global $conn;
    $conn->begin_transaction();
    
    try {
        if ($cart_items === null) {
            $cart_items = getCart($user_id);
        }
        
        if (empty($cart_items)) {
            throw new Exception("Cart is empty");
        }
        
        $total = 0;
        foreach ($cart_items as $item) {
            $total += $item['price'] * $item['quantity'];
        }
        
        $stmt = $conn->prepare("INSERT INTO orders (user_id, total, payment_method, status, created_at) VALUES (?, ?, ?, 'pending', NOW())");
        $stmt->bind_param("ids", $user_id, $total, $payment_method);
        $stmt->execute();
        $order_id = $conn->insert_id;
        
        foreach ($cart_items as $item) {
            $stmt = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("iiid", $order_id, $item['product_id'], $item['quantity'], $item['price']);
            $stmt->execute();
        }
        
        $stmt = $conn->prepare("DELETE FROM cart WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        
        logActivity($conn, $user_id, 'order_created', "Created order #$order_id with total: $total");
        
        $conn->commit();
        return $order_id;
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
}

function getOrderHistory($user_id) {
    global $conn;
    $stmt = $conn->prepare("SELECT o.*, oi.product_id, oi.quantity, oi.price, p.name 
                            FROM orders o 
                            JOIN order_items oi ON o.order_id = oi.order_id 
                            JOIN products p ON oi.product_id = p.product_id 
                            WHERE o.user_id = ?
                            ORDER BY o.created_at DESC");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $orders = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $orders;
}

/**
 * QUOTATION FUNCTIONS
 */

function getSuppliers($conn, $active_only = true) {
    $query = "SELECT * FROM suppliers";
    if ($active_only) {
        $query .= " WHERE is_active = 1";
    }
    $query .= " ORDER BY name ASC";
    $result = $conn->query($query);
    return $result->fetch_all(MYSQLI_ASSOC);
}

function createSupplierOrder($supplier_id, $items, $notes, $created_by) {
    global $conn;
    $conn->begin_transaction();
    
    try {
        $total_amount = 0;
        foreach ($items as $item) {
            $total_amount += $item['quantity'] * $item['price'];
        }

        $stmt = $conn->prepare("INSERT INTO supplier_orders (supplier_id, total_amount, notes, created_by, status, created_at) VALUES (?, ?, ?, ?, 'pending', NOW())");
        $stmt->bind_param("idsi", $supplier_id, $total_amount, $notes, $created_by);
        $stmt->execute();
        $supplier_order_id = $conn->insert_id;

        foreach ($items as $item) {
            $stmt = $conn->prepare("INSERT INTO supplier_order_items (supplier_order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("iiid", $supplier_order_id, $item['product_id'], $item['quantity'], $item['price']);
            $stmt->execute();
        }
        
        logActivity($conn, $created_by, 'supplier_order_created', "Created supplier order #$supplier_order_id with total: $total_amount");
        
        $conn->commit();
        return $supplier_order_id;
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
}

/**
 * REPORTS & DASHBOARD FUNCTIONS
 */

function getSalesReport($period = 'daily', $start_date = null, $end_date = null, $category = null) {
    global $conn;
    $query = "SELECT DATE(o.created_at) as date, 
                     COUNT(o.order_id) as order_count, 
                     SUM(o.total) as total_sales,
                     AVG(o.total) as average_order_value
              FROM orders o 
              WHERE o.status = 'completed'";
    
    $params = [];
    $types = '';
    
    if ($start_date && $end_date) {
        $query .= " AND DATE(o.created_at) BETWEEN ? AND ?";
        $params[] = $start_date;
        $params[] = $end_date;
        $types .= 'ss';
    } else {
        switch ($period) {
            case 'daily':
                $query .= " AND DATE(o.created_at) = CURDATE()";
                break;
            case 'weekly':
                $query .= " AND o.created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
                break;
            case 'monthly':
                $query .= " AND o.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
                break;
            case 'yearly':
                $query .= " AND o.created_at >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)";
                break;
        }
    }
    
    if ($category) {
        $query .= " AND EXISTS (SELECT 1 FROM order_items oi JOIN products p ON oi.product_id = p.product_id WHERE oi.order_id = o.order_id AND p.category = ?)";
        $params[] = $category;
        $types .= 's';
    }
    
    $query .= " GROUP BY DATE(o.created_at) ORDER BY date DESC";
    
    $stmt = $conn->prepare($query);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $report = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $report;
}

function getInventoryReport($category = null, $low_stock_threshold = 5) {
    global $conn;
    $query = "SELECT p.product_id, p.name, p.category, p.price, 
                     COALESCE(p.quantity, 0) as current_stock,
                     p.customer_visible, p.created_at, p.updated_at,
                     CASE 
                         WHEN COALESCE(p.quantity, 0) <= ? THEN 'LOW STOCK'
                         WHEN p.customer_visible = 0 THEN 'HIDDEN' 
                         ELSE 'NORMAL' 
                     END as stock_status
              FROM products p 
              WHERE 1=1";
    
    $params = [$low_stock_threshold];
    $types = 'i';
    
    if ($category) {
        $query .= " AND p.category = ?";
        $params[] = $category;
        $types .= 's';
    }
    
    $query .= " ORDER BY 
                    CASE WHEN COALESCE(p.quantity, 0) <= ? THEN 1 ELSE 2 END,
                    p.name ASC";
    
    $params[] = $low_stock_threshold;
    $types .= 'i';
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $report = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    return $report;
}

function getShopManagerKPIStats($conn) {
    $stats = [
        'todays_sales' => 0,
        'weekly_sales' => 0,
        'monthly_sales' => 0,
        'total_orders' => 0,
        'pending_orders' => 0,
        'completed_orders' => 0,
        'total_products' => 0,
        'low_stock_products' => 0,
        'active_users' => 0
    ];
    
    // Today's sales
    $result = $conn->query("SELECT COALESCE(SUM(total), 0) as total FROM orders WHERE DATE(created_at) = CURDATE() AND status = 'completed'");
    $stats['todays_sales'] = $result->fetch_assoc()['total'] ?? 0;
    
    // Weekly sales
    $result = $conn->query("SELECT COALESCE(SUM(total), 0) as total FROM orders WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND status = 'completed'");
    $stats['weekly_sales'] = $result->fetch_assoc()['total'] ?? 0;
    
    // Monthly sales
    $result = $conn->query("SELECT COALESCE(SUM(total), 0) as total FROM orders WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND status = 'completed'");
    $stats['monthly_sales'] = $result->fetch_assoc()['total'] ?? 0;
    
    // Order counts
    $result = $conn->query("SELECT COUNT(*) as total FROM orders");
    $stats['total_orders'] = $result->fetch_assoc()['total'] ?? 0;
    
    $result = $conn->query("SELECT COUNT(*) as total FROM orders WHERE status = 'pending'");
    $stats['pending_orders'] = $result->fetch_assoc()['total'] ?? 0;
    
    $result = $conn->query("SELECT COUNT(*) as total FROM orders WHERE status = 'completed'");
    $stats['completed_orders'] = $result->fetch_assoc()['total'] ?? 0;
    
    // Product counts
    $result = $conn->query("SELECT COUNT(*) as total FROM products");
    $stats['total_products'] = $result->fetch_assoc()['total'] ?? 0;
    
    $result = $conn->query("SELECT COUNT(*) as total FROM products WHERE COALESCE(stock_quantity, 0) <= 5");
    $stats['low_stock_products'] = $result->fetch_assoc()['total'] ?? 0;
    
    // Active users
    $result = $conn->query("SELECT COUNT(*) as total FROM users WHERE is_active = 1");
    $stats['active_users'] = $result->fetch_assoc()['total'] ?? 0;
    
    return $stats;
}

/**
 * ORDER PROCESSING FUNCTIONS
 */

function getPendingPayments() {
    global $conn;
    $stmt = $conn->prepare("SELECT o.order_id, u.first_name, u.last_name, o.total, o.payment_method, o.created_at
                            FROM orders o 
                            JOIN users u ON o.user_id = u.user_id 
                            WHERE o.status = 'pending' 
                            ORDER BY o.created_at DESC");
    $stmt->execute();
    $result = $stmt->get_result();
    $orders = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $orders;
}

function confirmPayment($order_id) {
    global $conn;
    $stmt = $conn->prepare("UPDATE orders SET status = 'confirmed', updated_at = NOW() WHERE order_id = ? AND status = 'pending'");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();
    
    if ($affected > 0) {
        logActivity($conn, $_SESSION['user_id'] ?? 0, 'payment_confirmed', "Confirmed payment for order #$order_id");
    }
    
    return $affected;
}

function getPendingOrders() {
    global $conn;
    $stmt = $conn->prepare("SELECT o.*, u.first_name, u.last_name 
                            FROM orders o 
                            JOIN users u ON o.user_id = u.user_id 
                            WHERE o.status = 'pending' 
                            ORDER BY o.created_at DESC");
    $stmt->execute();
    $result = $stmt->get_result();
    $orders = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $orders;
}

function getCompletedOrders() {
    global $conn;
    $stmt = $conn->prepare("SELECT o.*, u.first_name, u.last_name 
                            FROM orders o 
                            JOIN users u ON o.user_id = u.user_id 
                            WHERE o.status = 'confirmed' 
                            ORDER BY o.created_at DESC");
    $stmt->execute();
    $result = $stmt->get_result();
    $orders = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $orders;
}

/**
 * ACTIVITY LOGGING FUNCTIONS
 */

function logActivity($conn, $user_id, $action_type, $description, $ip_address = null, $user_agent = null) {
    if ($ip_address === null) {
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    if ($user_agent === null) {
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    }
    
    $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action_type, description, ip_address, user_agent, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("issss", $user_id, $action_type, $description, $ip_address, $user_agent);
    $stmt->execute();
    $stmt->close();
}

function getActivityLogs($conn, $user_id = null, $limit = 50) {
    $query = "SELECT al.*, u.first_name, u.last_name 
              FROM activity_logs al 
              LEFT JOIN users u ON al.user_id = u.user_id 
              WHERE 1=1";
    
    $params = [];
    $types = '';
    
    if ($user_id !== null) {
        $query .= " AND al.user_id = ?";
        $params[] = $user_id;
        $types .= 'i';
    }
    
    $query .= " ORDER BY al.created_at DESC LIMIT ?";
    $params[] = $limit;
    $types .= 'i';
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $logs = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    return $logs;
}

/**
 * NOTIFICATION FUNCTIONS
 */

function getNotifications($user_id) {
    global $conn;
    $stmt = $conn->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $notifications = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $notifications;
}

function createNotification($conn, $user_id, $title, $message, $type = 'info') {
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, type, is_read, created_at) VALUES (?, ?, ?, ?, 0, NOW())");
    $stmt->bind_param("isss", $user_id, $title, $message, $type);
    $stmt->execute();
    $notification_id = $conn->insert_id;
    $stmt->close();
    return $notification_id;
}

function markNotificationAsRead($conn, $notification_id, $user_id) {
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1, updated_at = NOW() WHERE notification_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $notification_id, $user_id);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();
    return $affected > 0;
}

/**
 * INPUT SANITIZATION & VALIDATION
 */

function sanitizeInput($input) {
    if (is_array($input)) {
        return array_map('sanitizeInput', $input);
    }
    $input = trim($input);
    $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
    return $input;
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function validatePhone($phone) {
    // Basic phone validation - adjust regex as needed for your region
    return preg_match('/^[\+]?[0-9\s\-\(\)]{10,15}$/', $phone);
}

function validatePassword($password) {
    // Password must be at least 6 characters
    return strlen($password) >= 6;
}

/**
 * SESSION & MESSAGE FUNCTIONS
 */

function displaySessionMessage() {
    if (isset($_SESSION['message'])) {
        $message = $_SESSION['message'];
        $type = $_SESSION['message_type'] ?? 'info';
        unset($_SESSION['message'], $_SESSION['message_type']);
        return "<div class='alert alert-$type'>$message</div>";
    }
    return '';
}

function setSessionMessage($message, $type = 'info') {
    $_SESSION['message'] = $message;
    $_SESSION['message_type'] = $type;
}

/**
 * UTILITY FUNCTIONS
 */

function formatCurrency($amount, $currency = 'MWK') {
    return $currency . ' ' . number_format($amount, 2);
}

function formatDate($date, $format = 'M d, Y H:i') {
    return date($format, strtotime($date));
}

function generateRandomString($length = 10) {
    return bin2hex(random_bytes($length / 2));
}

function isValidRole($role) {
    $valid_roles = ['shop_manager', 'cashier', 'inventory_manager', 'supplier'];
    return in_array($role, $valid_roles);
}

function getUserRoleDisplayName($role) {
    $role_names = [
        'shop_manager' => 'Shop Manager',
        'cashier' => 'Cashier',
        'inventory_manager' => 'Inventory Manager',
        'supplier' => 'Supplier'
    ];
    return $role_names[$role] ?? ucwords(str_replace('_', ' ', $role));
}

/**
 * ERROR HANDLING
 */

function handleDatabaseError($conn, $operation = 'database operation') {
    if ($conn->error) {
        error_log("Database error during $operation: " . $conn->error);
        throw new Exception("An error occurred during $operation. Please try again.");
    }
}

function logError($message, $context = []) {
    $log_message = $message;
    if (!empty($context)) {
        $log_message .= ' Context: ' . json_encode($context);
    }
    error_log($log_message);
}

?>

