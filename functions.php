<?php
require_once 'db_connect.php';

// Define base path for images
define('IMAGE_BASE_PATH', '/inventory/uploads/');
define('DEFAULT_IMAGE', '/inventory/uploads/default.jpg'); // Default image if none exists

/**
 * Helper function to check if a table exists
 */
function tableExists($conn, $tableName) {
    $result = $conn->query("SHOW TABLES LIKE '$tableName'");
    return $result && $result->num_rows > 0;
}

/**
 * Helper function to format image path
 */
function formatImagePath($image) {
    if (empty($image)) {
        return DEFAULT_IMAGE;
    }
    // Check if image already contains the base path
    if (strpos($image, IMAGE_BASE_PATH) === 0) {
        return $image;
    }
    return IMAGE_BASE_PATH . $image;
}

/**
 * AUTHENTICATION FUNCTIONS
 */

function login($conn, $email, $password) {
    if (!tableExists($conn, 'users')) {
        error_log("Table users does not exist in login");
        return false;
    }
    $stmt = $conn->prepare("SELECT user_id, first_name, last_name, email, password, role FROM users WHERE email = ? AND is_active = 1");
    if (!$stmt) {
        error_log("Prepare failed in login: " . $conn->error);
        return false;
    }
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
            if (!$stmt) {
                error_log("Prepare failed in login (update last_active): " . $conn->error);
                $stmt->close();
                return true;
            }
            $stmt->bind_param("i", $user['user_id']);
            $stmt->execute();
            return true;
        }
    }
    $stmt->close();
    return false;
}

function logout() {
    session_destroy();
    header("Location: login.php");
    exit();
}

/**
 * USER MANAGEMENT FUNCTIONS
 */

function getUsers($conn, $role = null, $active_only = true, $search = '') {
    if (!tableExists($conn, 'users')) {
        error_log("Table users does not exist in getUsers");
        return [];
    }
    $query = "SELECT user_id, first_name, last_name, email, role, last_active, is_active 
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
        $query .= " AND CONCAT(first_name, ' ', last_name) LIKE ?";
        $params[] = "%$search%";
        $types .= 's';
    }
    
    $query .= " ORDER BY last_active DESC";
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        error_log("Prepare failed in getUsers: " . $conn->error);
        return [];
    }
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $users = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $users;
}

function addUser($conn, $first_name, $last_name, $email, $role, $phone = null) {
    if (!tableExists($conn, 'users')) {
        error_log("Table users does not exist in addUser");
        return ['user_id' => null, 'temp_password' => null];
    }
    $temp_password = bin2hex(random_bytes(8));
    $hashed_password = password_hash($temp_password, PASSWORD_DEFAULT);
    
    $stmt = $conn->prepare("INSERT INTO users (first_name, last_name, email, password, role, phone, is_active) VALUES (?, ?, ?, ?, ?, ?, 1)");
    if (!$stmt) {
        error_log("Prepare failed in addUser: " . $conn->error);
        return ['user_id' => null, 'temp_password' => null];
    }
    $stmt->bind_param("ssssss", $first_name, $last_name, $email, $hashed_password, $role, $phone);
    $stmt->execute();
    $user_id = $conn->insert_id;
    $stmt->close();
    
    return ['user_id' => $user_id, 'temp_password' => $temp_password];
}

function updateUser($conn, $user_id, $first_name, $last_name, $email, $role, $is_active, $phone = null) {
    if (!tableExists($conn, 'users')) {
        error_log("Table users does not exist in updateUser");
        return false;
    }
    $stmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, role = ?, is_active = ?, phone = ? WHERE user_id = ?");
    if (!$stmt) {
        error_log("Prepare failed in updateUser: " . $conn->error);
        return false;
    }
    $stmt->bind_param("ssssisi", $first_name, $last_name, $email, $role, $is_active, $phone, $user_id);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();
    return $affected > 0;
}

function resetUserPassword($conn, $user_id) {
    if (!tableExists($conn, 'users')) {
        error_log("Table users does not exist in resetUserPassword");
        return false;
    }
    $temp_password = bin2hex(random_bytes(8));
    $hashed_password = password_hash($temp_password, PASSWORD_DEFAULT);
    
    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
    if (!$stmt) {
        error_log("Prepare failed in resetUserPassword: " . $conn->error);
        return false;
    }
    $stmt->bind_param("si", $hashed_password, $user_id);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();
    
    return $affected > 0 ? $temp_password : false;
}

function updateProfile($conn, $user_id, $first_name, $last_name, $phone, $password = null) {
    $first_name = $conn->real_escape_string($first_name);
    $last_name = $conn->real_escape_string($last_name);
    $phone = $conn->real_escape_string($phone);
    
    if ($password) {
        $password = password_hash($conn->real_escape_string($password), PASSWORD_DEFAULT);
        $sql = "UPDATE users SET first_name = ?, last_name = ?, phone = ?, password = ? WHERE user_id = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Failed to prepare update profile query: " . $conn->error);
        }
        $stmt->bind_param("ssssi", $first_name, $last_name, $phone, $password, $user_id);
    } else {
        $sql = "UPDATE users SET first_name = ?, last_name = ?, phone = ? WHERE user_id = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Failed to prepare update profile query: " . $conn->error);
        }
        $stmt->bind_param("sssi", $first_name, $last_name, $phone, $user_id);
    }
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to update profile: " . $stmt->error);
    }
    $stmt->close();
}

function getProfile($conn, $user_id) {
    $stmt = $conn->prepare("SELECT first_name, last_name, email, phone 
                            FROM users 
                            WHERE user_id = ?");
    if (!$stmt) {
        error_log("Failed to prepare profile query: " . $conn->error);
        return ['first_name' => '', 'last_name' => '', 'email' => '', 'phone' => ''];
    }
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $result ?: ['first_name' => '', 'last_name' => '', 'email' => '', 'phone' => ''];
}

function deleteUser($conn, $user_id) {
    if (!tableExists($conn, 'users')) {
        error_log("Table users does not exist in deleteUser");
        return 0;
    }
    $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ? AND role != 'admin'");
    if (!$stmt) {
        error_log("Prepare failed in deleteUser: " . $conn->error);
        return 0;
    }
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();
    return $affected;
}

/**
 * PRODUCT FUNCTIONS
 */

function getProducts($conn, $search = '', $category = null) {
    if (!tableExists($conn, 'products')) {
        error_log("Table products does not exist in getProducts");
        return [];
    }
    $query = "SELECT product_id, name, price, barcode, description, image, category, is_promotion, customer_visible 
              FROM products WHERE 1=1";
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
    
    $query .= " ORDER BY name ASC";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        error_log("Prepare failed in getProducts: " . $conn->error);
        return [];
    }
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $products = $result->fetch_all(MYSQLI_ASSOC);
    
    // Format image paths
    foreach ($products as &$product) {
        $product['image'] = formatImagePath($product['image']);
    }
    unset($product); // Unset reference to avoid issues
    
    $stmt->close();
    return $products;
}

function getPromotionalItems($conn) {
    $stmt = $conn->prepare("SELECT product_id, name, price, image, category, expiry_date, is_promotion 
                            FROM products 
                            WHERE is_promotion = 1");
    if (!$stmt) {
        error_log("Failed to prepare promotional items query: " . $conn->error);
        return [];
    }
    $stmt->execute();
    $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Format image paths
    foreach ($result as &$item) {
        $item['image'] = formatImagePath($item['image']);
    }
    unset($item); // Unset reference to avoid issues
    
    $stmt->close();
    return $result;
}

function getProductByBarcode($conn, $barcode) {
    if (!tableExists($conn, 'products')) {
        error_log("Table products does not exist in getProductByBarcode");
        return null;
    }
    $stmt = $conn->prepare("SELECT product_id, name, price, barcode, description, image, category, is_promotion, customer_visible 
                            FROM products WHERE barcode = ?");
    if (!$stmt) {
        error_log("Prepare failed in getProductByBarcode: " . $conn->error);
        return null;
    }
    $stmt->bind_param("s", $barcode);
    $stmt->execute();
    $result = $stmt->get_result();
    $product = $result->fetch_assoc();
    
    if ($product) {
        $product['image'] = formatImagePath($product['image']);
    }
    
    $stmt->close();
    return $product;
}

function addProduct($conn, $name, $price, $barcode, $description, $image, $category, $is_promotion, $customer_visible) {
    if (!tableExists($conn, 'products')) {
        error_log("Table products does not exist in addProduct");
        return 0;
    }
    $image = formatImagePath($image); // Ensure consistent image path format
    $stmt = $conn->prepare("INSERT INTO products (name, price, barcode, description, image, category, is_promotion, customer_visible) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        error_log("Prepare failed in addProduct: " . $conn->error);
        return 0;
    }
    $stmt->bind_param("sdsssiss", $name, $price, $barcode, $description, $image, $category, $is_promotion, $customer_visible);
    $stmt->execute();
    $product_id = $conn->insert_id;
    $stmt->close();
    return $product_id;
}

function updateProduct($conn, $product_id, $category, $name, $price, $description, $is_promotion, $customer_visible) {
    if (!tableExists($conn, 'products')) {
        error_log("Table products does not exist in updateProduct");
        return false;
    }
    $stmt = $conn->prepare("UPDATE products SET category = ?, name = ?, price = ?, description = ?, is_promotion = ?, customer_visible = ? WHERE product_id = ?");
    if (!$stmt) {
        error_log("Prepare failed in updateProduct: " . $conn->error);
        return false;
    }
    $stmt->bind_param("ssdssii", $category, $name, $price, $description, $is_promotion, $customer_visible, $product_id);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();
    return $affected > 0;
}

function addToCustomerDashboard($conn, $product_id) {
    if (!tableExists($conn, 'customer_products') || !tableExists($conn, 'products')) {
        error_log("Table customer_products or products does not exist in addToCustomerDashboard");
        return false;
    }
    // Fetch product details from products table
    $stmt = $conn->prepare("SELECT name, image FROM products WHERE product_id = ?");
    if (!$stmt) {
        error_log("Prepare failed in addToCustomerDashboard (select product): " . $conn->error);
        return false;
    }
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $product = $result->fetch_assoc();
    $stmt->close();
    
    if (!$product) {
        error_log("Product not found for product_id: $product_id in addToCustomerDashboard");
        return false;
    }
    
    $product_name = $product['name'];
    $image = formatImagePath($product['image']);
    
    // Insert into customer_products with created_at set to NOW()
    $stmt = $conn->prepare("INSERT INTO customer_products (product_id, product_name, image, created_at) VALUES (?, ?, ?, NOW())");
    if (!$stmt) {
        error_log("Prepare failed in addToCustomerDashboard (insert): " . $conn->error);
        return false;
    }
    $stmt->bind_param("iss", $product_id, $product_name, $image);
    $success = $stmt->execute();
    $stmt->close();
    return $success;
}

function getCustomerDashboardItems($conn) {
    if (!tableExists($conn, 'customer_products')) {
        error_log("Table customer_products does not exist in getCustomerDashboardItems");
        return [];
    }
    $stmt = $conn->prepare("SELECT item_id, product_id, product_name, image, created_at 
                            FROM customer_products 
                            ORDER BY created_at DESC");
    if (!$stmt) {
        error_log("Failed to prepare customer dashboard items query: " . $conn->error);
        return [];
    }
    $stmt->execute();
    $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Format image paths
    foreach ($result as &$item) {
        $item['image'] = formatImagePath($item['image']);
    }
    unset($item); // Unset reference to avoid issues
    
    $stmt->close();
    return $result;
}

function getAllProductCategories($conn) {
    $categories = [];
    if (!tableExists($conn, 'products')) {
        error_log("Table products does not exist in getAllProductCategories");
        return $categories;
    }
    $query = "SELECT DISTINCT category FROM products WHERE category IS NOT NULL ORDER BY category";
    $result = $conn->query($query);
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $categories[] = $row['category'];
        }
        $result->free();
    } else {
        error_log("Query failed in getAllProductCategories: " . $conn->error);
    }
    
    return $categories;
}

/**
 * CART & ORDER FUNCTIONS
 */

function addToCart($conn, $user_id, $product_id, $quantity = 1) {
    // Check if product exists
    $stmt = $conn->prepare("SELECT product_id FROM products WHERE product_id = ?");
    if (!$stmt) {
        throw new Exception("Failed to verify product: " . $conn->error);
    }
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        throw new Exception("Product does not exist");
    }
    $stmt->close();

    // Check if item is already in cart
    $stmt = $conn->prepare("SELECT quantity FROM cart WHERE user_id = ? AND product_id = ?");
    if (!$stmt) {
        throw new Exception("Failed to check cart: " . $conn->error);
    }
    $stmt->bind_param("ii", $user_id, $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Update existing cart item
        $row = $result->fetch_assoc();
        $new_quantity = $row['quantity'] + $quantity;
        $stmt = $conn->prepare("UPDATE cart SET quantity = ? WHERE user_id = ? AND product_id = ?");
        if (!$stmt) {
            throw new Exception("Failed to prepare update cart query: " . $conn->error);
        }
        $stmt->bind_param("iii", $new_quantity, $user_id, $product_id);
    } else {
        // Insert new cart item
        $stmt = $conn->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)");
        if (!$stmt) {
            throw new Exception("Failed to prepare insert cart query: " . $conn->error);
        }
        $stmt->bind_param("iii", $user_id, $product_id, $quantity);
    }
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to add to cart: " . $stmt->error);
    }
    $stmt->close();
}

function getCart($conn, $user_id) {
    $stmt = $conn->prepare("SELECT c.*, p.name, p.price, p.image 
                            FROM cart c 
                            JOIN products p ON c.product_id = p.product_id 
                            WHERE c.user_id = ?");
    if (!$stmt) {
        error_log("Failed to prepare cart query: " . $conn->error);
        return [];
    }
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Format image paths
    foreach ($result as &$item) {
        $item['image'] = formatImagePath($item['image']);
    }
    unset($item); // Unset reference to avoid issues
    
    $stmt->close();
    return $result;
}

function updateCartItem($conn, $user_id, $product_id, $quantity) {
    if ($quantity <= 0) {
        // Remove item from cart
        $stmt = $conn->prepare("DELETE FROM cart WHERE user_id = ? AND product_id = ?");
        if (!$stmt) {
            throw new Exception("Failed to prepare delete cart query: " . $conn->error);
        }
        $stmt->bind_param("ii", $user_id, $product_id);
    } else {
        // Update item quantity
        $stmt = $conn->prepare("UPDATE cart SET quantity = ? WHERE user_id = ? AND product_id = ?");
        if (!$stmt) {
            throw new Exception("Failed to prepare update cart query: " . $conn->error);
        }
        $stmt->bind_param("iii", $quantity, $user_id, $product_id);
    }
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to update cart: " . $stmt->error);
    }
    $stmt->close();
}

function clearCart($conn, $user_id) {
    if (!tableExists($conn, 'cart')) {
        error_log("Table cart does not exist in clearCart");
        return false;
    }
    $stmt = $conn->prepare("DELETE FROM cart WHERE user_id = ?");
    if (!$stmt) {
        error_log("Prepare failed in clearCart: " . $conn->error);
        return false;
    }
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();
    return true;
}

function createOrder($conn, $user_id, $payment_method, $cart_items = null) {
    if (!tableExists($conn, 'orders') || !tableExists($conn, 'order_items') || !tableExists($conn, 'cart')) {
        error_log("Table orders, order_items, or cart does not exist in createOrder");
        return 0;
    }
    $conn->begin_transaction();
    
    try {
        if ($cart_items === null) {
            $cart_items = getCart($conn, $user_id);
        }
        
        if (empty($cart_items)) {
            throw new Exception("Cart is empty");
        }
        
        $total = 0;
        foreach ($cart_items as $item) {
            $total += $item['price'] * $item['quantity'];
        }
        
        $stmt = $conn->prepare("INSERT INTO orders (user_id, total, payment_method, status) VALUES (?, ?, ?, 'pending')");
        if (!$stmt) {
            throw new Exception("Prepare failed in createOrder (insert orders): " . $conn->error);
        }
        $stmt->bind_param("ids", $user_id, $total, $payment_method);
        $stmt->execute();
        $order_id = $conn->insert_id;
        
        foreach ($cart_items as $item) {
            $stmt = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
            if (!$stmt) {
                throw new Exception("Prepare failed in createOrder (insert order_items): " . $conn->error);
            }
            $stmt->bind_param("iiid", $order_id, $item['product_id'], $item['quantity'], $item['price']);
            $stmt->execute();
        }
        
        $stmt = $conn->prepare("DELETE FROM cart WHERE user_id = ?");
        if (!$stmt) {
            throw new Exception("Prepare failed in createOrder (delete cart): " . $conn->error);
        }
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        
        $conn->commit();
        return $order_id;
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Error in createOrder: " . $e->getMessage());
        throw $e;
    }
}

function getOrderHistory($conn, $user_id) {
    if (!tableExists($conn, 'orders') || !tableExists($conn, 'order_items') || !tableExists($conn, 'products')) {
        error_log("Table orders, order_items, or products does not exist in getOrderHistory");
        return [];
    }
    $stmt = $conn->prepare("SELECT o.*, oi.product_id, oi.quantity, oi.price, p.name, p.image 
                            FROM orders o 
                            JOIN order_items oi ON o.order_id = oi.order_id 
                            JOIN products p ON oi.product_id = p.product_id 
                            WHERE o.user_id = ?");
    if (!$stmt) {
        error_log("Prepare failed in getOrderHistory: " . $conn->error);
        return [];
    }
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $orders = $result->fetch_all(MYSQLI_ASSOC);
    
    // Format image paths
    foreach ($orders as &$order) {
        $order['image'] = formatImagePath($order['image']);
    }
    unset($order); // Unset reference to avoid issues
    
    $stmt->close();
    return $orders;
}

/**
 * QUOTATION FUNCTIONS
 */

function getSuppliers($conn, $active_only = true) {
    if (!tableExists($conn, 'suppliers')) {
        error_log("Table suppliers does not exist in getSuppliers");
        return [];
    }
    $query = "SELECT * FROM suppliers";
    if ($active_only) {
        $query .= " WHERE is_active = 1";
    }
    $query .= " ORDER BY name ASC";
    $result = $conn->query($query);
    if (!$result) {
        error_log("Query failed in getSuppliers: " . $conn->error);
        return [];
    }
    return $result->fetch_all(MYSQLI_ASSOC);
}

function createSupplierOrder($conn, $supplier_id, $items, $notes, $created_by) {
    if (!tableExists($conn, 'supplier_orders') || !tableExists($conn, 'supplier_order_items')) {
        error_log("Table supplier_orders or supplier_order_items does not exist in createSupplierOrder");
        return 0;
    }
    $conn->begin_transaction();
    
    try {
        $total_amount = 0;
        foreach ($items as $item) {
            $total_amount += $item['quantity'] * $item['price'];
        }

        $stmt = $conn->prepare("INSERT INTO supplier_orders (supplier_id, total_amount, notes, created_by, status) VALUES (?, ?, ?, ?, 'pending')");
        if (!$stmt) {
            throw new Exception("Prepare failed in createSupplierOrder (insert supplier_orders): " . $conn->error);
        }
        $stmt->bind_param("idsi", $supplier_id, $total_amount, $notes, $created_by);
        $stmt->execute();
        $supplier_order_id = $conn->insert_id;

        foreach ($items as $item) {
            $stmt = $conn->prepare("INSERT INTO supplier_order_items (supplier_order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
            if (!$stmt) {
                throw new Exception("Prepare failed in createSupplierOrder (insert supplier_order_items): " . $conn->error);
            }
            $stmt->bind_param("iiid", $supplier_order_id, $item['product_id'], $item['quantity'], $item['price']);
            $stmt->execute();
        }
        
        $conn->commit();
        return $supplier_order_id;
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Error in createSupplierOrder: " . $e->getMessage());
        return 0;
    }
}

/**
 * REPORTS & DASHBOARD FUNCTIONS
 */

function getSalesReport($conn, $period = 'daily', $start_date = null, $end_date = null) {
    if (!tableExists($conn, 'orders')) {
        error_log("Table orders does not exist in getSalesReport");
        return [];
    }
    $query = "SELECT DATE(o.created_at) as date, 
                     COUNT(o.order_id) as order_count, 
                     SUM(o.total) as total_sales,
                     AVG(o.total) as average_order_value
              FROM orders o 
              WHERE o.status = 'completed'";
    
    if ($start_date && $end_date) {
        $query .= " AND DATE(o.created_at) BETWEEN ? AND ?";
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            error_log("Prepare failed in getSalesReport: " . $conn->error);
            return [];
        }
        $stmt->bind_param("ss", $start_date, $end_date);
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
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            error_log("Prepare failed in getSalesReport: " . $conn->error);
            return [];
        }
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $report = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $report;
}

function getInventoryReport($conn, $report_type = 'all', $shop_id = null, $low_stock_threshold = 5) {
    $valid_types = ['daily', 'weekly', 'monthly', 'all'];
    if (!in_array($report_type, $valid_types)) {
        throw new InvalidArgumentException("Invalid report type. Must be one of: " . implode(', ', $valid_types));
    }
    if (!tableExists($conn, 'products')) {
        error_log("Table products does not exist in getInventoryReport");
        return [];
    }
    $query = "SELECT p.product_id, p.name, p.quantity, p.price, 
                     p.customer_visible, p.created_at, p.image,
                     CASE WHEN p.customer_visible = 0 THEN 'HIDDEN' ELSE 'VISIBLE' END as stock_status
              FROM products p 
              WHERE 1=1";
    
    $params = [];
    $types = '';
    
    switch ($report_type) {
        case 'daily':
            $query .= " AND DATE(p.created_at) = CURDATE()";
            break;
        case 'weekly':
            $query .= " AND p.created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
            break;
        case 'monthly':
            $query .= " AND p.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
            break;
    }
    
    if ($shop_id !== null && is_numeric($shop_id)) {
        $shop_id = (int)$shop_id;
        $query .= " AND p.shop_id = ?";
        $params[] = $shop_id;
        $types .= 'i';
    }
    
    $query .= " ORDER BY p.created_at DESC, p.name ASC";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        error_log("Prepare failed in getInventoryReport: " . $conn->error);
        return [];
    }
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $report = $result->fetch_all(MYSQLI_ASSOC);
    
    // Format image paths
    foreach ($report as &$item) {
        $item['image'] = formatImagePath($item['image']);
    }
    unset($item); // Unset reference to avoid issues
    
    $stmt->close();
    return $report;
}

function getDashboardStats($conn, $role) {
    $stats = [];
    
    switch ($role) {
        case 'admin':
            if (!tableExists($conn, 'users')) {
                error_log("Table users does not exist in getDashboardStats (admin)");
            } else {
                $stats['total_users'] = $conn->query("SELECT COUNT(*) FROM users")->fetch_row()[0];
                $stats['active_users'] = $conn->query("SELECT COUNT(*) FROM users WHERE is_active = 1")->fetch_row()[0];
            }
            if (!tableExists($conn, 'orders')) {
                error_log("Table orders does not exist in getDashboardStats (admin)");
            } else {
                $stats['today_sales'] = $conn->query("SELECT SUM(total) FROM orders WHERE DATE(created_at) = CURDATE() AND status = 'completed'")->fetch_row()[0] ?? 0;
                $stats['pending_orders'] = $conn->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'")->fetch_row()[0];
            }
            break;
            
        case 'manager':
            if (!tableExists($conn, 'products')) {
                error_log("Table products does not exist in getDashboardStats (manager)");
            } else {
                $stats['total_products'] = $conn->query("SELECT COUNT(*) FROM products")->fetch_row()[0];
                $stats['low_stock'] = $conn->query("SELECT COUNT(*) FROM products WHERE customer_visible = 1")->fetch_row()[0];
            }
            if (!tableExists($conn, 'orders')) {
                error_log("Table orders does not exist in getDashboardStats (manager)");
            } else {
                $stats['today_sales'] = $conn->query("SELECT SUM(total) FROM orders WHERE DATE(created_at) = CURDATE() AND status = 'completed'")->fetch_row()[0] ?? 0;
            }
            break;
            
        case 'cashier':
            if (!tableExists($conn, 'orders')) {
                error_log("Table orders does not exist in getDashboardStats (cashier)");
            } else {
                $stats['today_orders'] = $conn->query("SELECT COUNT(*) FROM orders WHERE DATE(created_at) = CURDATE()")->fetch_row()[0];
                $stats['pending_payments'] = $conn->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'")->fetch_row()[0];
                $stats['today_revenue'] = $conn->query("SELECT SUM(total) FROM orders WHERE DATE(created_at) = CURDATE() AND status = 'completed'")->fetch_row()[0] ?? 0;
            }
            break;
            
        case 'inventory':
            if (!tableExists($conn, 'products')) {
                error_log("Table products does not exist in getDashboardStats (inventory)");
            } else {
                $stats['total_products'] = $conn->query("SELECT COUNT(*) FROM products")->fetch_row()[0];
                $stats['out_of_stock'] = $conn->query("SELECT COUNT(*) FROM products WHERE customer_visible = 0")->fetch_row()[0];
                $stats['low_stock'] = $conn->query("SELECT COUNT(*) FROM products WHERE customer_visible = 1")->fetch_row()[0];
            }
            break;
    }
    
    return $stats;
}

function getCashierKPIStats($conn) {
    $stats = [
        'orders_today' => 0,
        'pending_payments' => 0,
        'transactions_count' => 0
    ];
    
    if (!tableExists($conn, 'orders')) {
        error_log("Table orders does not exist in getCashierKPIStats");
        return $stats;
    }
    
    $result = $conn->query("SELECT COUNT(*) as count FROM orders WHERE DATE(created_at) = CURDATE()");
    if ($result) {
        $stats['orders_today'] = $result->fetch_assoc()['count'];
    } else {
        error_log("Query failed in getCashierKPIStats (orders_today): " . $conn->error);
    }
    
    $result = $conn->query("SELECT COUNT(*) as count FROM orders WHERE status = 'pending'");
    if ($result) {
        $stats['pending_payments'] = $result->fetch_assoc()['count'];
    } else {
        error_log("Query failed in getCashierKPIStats (pending_payments): " . $conn->error);
    }
    
    $result = $conn->query("SELECT COUNT(*) as count FROM orders WHERE status = 'confirmed' AND DATE(created_at) = CURDATE()");
    if ($result) {
        $stats['transactions_count'] = $result->fetch_assoc()['count'];
    } else {
        error_log("Query failed in getCashierKPIStats (transactions_count): " . $conn->error);
    }
    
    return $stats;
}

function getShopManagerKPIStats($conn) {
    $stats = [
        'todays_sales' => 0
    ];
    
    if (tableExists($conn, 'orders')) {
        $result = $conn->query("SELECT SUM(total) as total FROM orders WHERE status = 'confirmed' AND DATE(created_at) = CURDATE()");
        if ($result) {
            $stats['todays_sales'] = $result->fetch_assoc()['total'] ?? 0;
        } else {
            error_log("Query failed in getShopManagerKPIStats (todays_sales): " . $conn->error);
        }
    } else {
        error_log("Table orders does not exist in getShopManagerKPIStats");
    }
    
    return $stats;
}

/**
 * ORDER PROCESSING FUNCTIONS
 */

function getPendingPayments($conn) {
    if (!tableExists($conn, 'orders') || !tableExists($conn, 'users')) {
        error_log("Table orders or users does not exist in getPendingPayments");
        return [];
    }
    $stmt = $conn->prepare("SELECT o.order_id, u.first_name, u.last_name, o.total, o.payment_method 
                            FROM orders o 
                            JOIN users u ON o.user_id = u.user_id 
                            WHERE o.status = 'pending' 
                            ORDER BY o.created_at DESC");
    if (!$stmt) {
        error_log("Prepare failed in getPendingPayments: " . $conn->error);
        return [];
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $orders = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $orders;
}

function confirmPayment($conn, $order_id) {
    if (!tableExists($conn, 'orders')) {
        error_log("Table orders does not exist in confirmPayment");
        return 0;
    }
    $stmt = $conn->prepare("UPDATE orders SET status = 'confirmed' WHERE order_id = ? AND status = 'pending'");
    if (!$stmt) {
        error_log("Prepare failed in confirmPayment: " . $conn->error);
        return 0;
    }
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();
    return $affected;
}

function getPendingOrders($conn) {
    if (!tableExists($conn, 'orders') || !tableExists($conn, 'users')) {
        error_log("Table orders or users does not exist in getPendingOrders");
        return [];
    }
    $stmt = $conn->prepare("SELECT o.*, u.first_name, u.last_name 
                            FROM orders o 
                            JOIN users u ON o.user_id = u.user_id 
                            WHERE o.status = 'pending' 
                            ORDER BY o.created_at DESC");
    if (!$stmt) {
        error_log("Prepare failed in getPendingOrders: " . $conn->error);
        return [];
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $orders = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $orders;
}

function getCompletedOrders($conn) {
    if (!tableExists($conn, 'orders') || !tableExists($conn, 'users')) {
        error_log("Table orders or users does not exist in getCompletedOrders");
        return [];
    }
    $stmt = $conn->prepare("SELECT o.*, u.first_name, u.last_name 
                            FROM orders o 
                            JOIN users u ON o.user_id = u.user_id 
                            WHERE o.status = 'confirmed' 
                            ORDER BY o.created_at DESC");
    if (!$stmt) {
        error_log("Prepare failed in getCompletedOrders: " . $conn->error);
        return [];
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $orders = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $orders;
}

/**
 * NOTIFICATION & LOGGING FUNCTIONS
 */

function getNotifications($conn, $user_id) {
    if (!tableExists($conn, 'orders')) {
        error_log("Table orders does not exist in getNotifications");
        return [];
    }
    $stmt = $conn->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
    if (!$stmt) {
        error_log("Prepare failed in getNotifications: " . $conn->error);
        return [];
    }
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $notifications = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $notifications;
}

function logAction($conn, $user_id, $action, $action_type) {
    if (!tableExists($conn, 'activity_logs')) {
        error_log("Table activity_logs does not exist in logAction");
        return;
    }
    $query = "INSERT INTO activity_logs (user_id, action, action_type, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        error_log("Prepare failed in logAction: " . $conn->error);
        return;
    }
    
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    
    $stmt->bind_param("issss", $user_id, $action, $action_type, $ip, $agent);
    $stmt->execute();
    $stmt->close();
}
?>