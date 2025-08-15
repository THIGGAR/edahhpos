<?php
require_once __DIR__ . 
'/db_connect.php';

function sanitizeInput($input, $type = 'string') {
    global $pdo;
    
    if (is_null($input)) {
        return null;
    }
    
    switch ($type) {
        case 'string':
            return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
        case 'int':
            return filter_var($input, FILTER_VALIDATE_INT) !== false ? (int)$input : 0;
        case 'float':
            return filter_var($input, FILTER_VALIDATE_FLOAT) !== false ? (float)$input : 0.0;
        case 'barcode':
            $input = trim($input);
            return ctype_digit($input) ? $input : '';
        case 'date':
            $input = trim($input);
            return preg_match('/^\d{4}-\d{2}-\d{2}$/', $input) ? $input : '';
        default:
            return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
}

function logError($message, $context = []) {
    $logMessage = date('Y-m-d H:i:s') . ' - ERROR: ' . $message;
    if (!empty($context)) {
        $logMessage .= ' - Context: ' . json_encode($context);
    }
    $logDir = __DIR__ . '/logs/';
    if (!file_exists($logDir)) {
        mkdir($logDir, 0777, true);
    }
    error_log($logMessage . PHP_EOL, 3, $logDir . 'error.log');
}

function logStockMovement($productId, $quantity, $movementType, $description) {
    global $pdo;
    
    try {
        // Check if $pdo is an instance of DummyPDO, if so, skip database interaction
        if ($pdo instanceof DummyPDO) {
            logError("Skipping stock movement logging: Dummy PDO in use.", ['product_id' => $productId, 'quantity' => $quantity, 'movement_type' => $movementType]);
            return;
        }

        $stmt = $pdo->prepare("
            INSERT INTO stock_movements (
                product_id, quantity, movement_type, description, created_at, user_id
            ) VALUES (?, ?, ?, ?, NOW(), ?)
        ");
        $stmt->execute([
            $productId,
            $quantity,
            $movementType,
            $description,
            $_SESSION['user_id'] ?? null
        ]);
    } catch (Exception $e) {
        logError("Failed to log stock movement: " . $e->getMessage(), [
            'product_id' => $productId,
            'quantity' => $quantity,
            'movement_type' => $movementType
        ]);
    }
}

function logUserAction($userId, $action, $actionType) {
    global $pdo;
    
    try {
        // Check if $pdo is an instance of DummyPDO, if so, skip database interaction
        if ($pdo instanceof DummyPDO) {
            logError("Skipping user action logging: Dummy PDO in use.", ['user_id' => $userId, 'action' => $action, 'action_type' => $actionType]);
            return;
        }

        $stmt = $pdo->prepare("
            INSERT INTO user_actions (
                user_id, action, action_type, created_at
            ) VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$userId, $action, $actionType]);
    } catch (Exception $e) {
        logError("Failed to log user action: " . $e->getMessage(), [
            'user_id' => $userId,
            'action' => $action,
            'action_type' => $actionType
        ]);
    }
}

function handleImageUpload($file) {
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $maxSize = 5 * 1024 * 1024; // 5MB
    $uploadDir = __DIR__ . '/uploads/';
    
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Image upload failed with error code: ' . $file['error']);
    }
    
    if (!in_array($file['type'], $allowedTypes)) {
        throw new Exception('Invalid image type. Only JPEG, PNG, GIF, and WebP are allowed.');
    }
    
    if ($file['size'] > $maxSize) {
        throw new Exception('Image size exceeds 5MB limit.');
    }
    
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid('img_') . '.' . $ext;
    $destination = $uploadDir . $filename;
    
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new Exception('Failed to move uploaded image.');
    }
    
    return 'uploads/' . $filename;
}

function checkBarcodeExists($barcode) {
    global $pdo;
    // If using DummyPDO, always return false to allow generation of unique barcodes
    if ($pdo instanceof DummyPDO) {
        return false;
    }
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE barcodes = ? AND is_active = 1");
        $stmt->execute([$barcode]);
        return $stmt->fetchColumn() > 0;
    } catch (PDOException $e) {
        logError("Error checking barcode existence: " . $e->getMessage(), [
            'barcode' => $barcode,
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);
        return false;
    }
}

function getProductByBarcode($barcode) {
    global $pdo;
    
    // If using DummyPDO, return false as no real product data is available
    if ($pdo instanceof DummyPDO) {
        return false;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT product_id, name, price, stock_quantity, barcodes, 
                   is_promotion, customer_visible as show_on_customer_dashboard, image, 
                   description, created_at, category
            FROM products
            WHERE barcodes = ? AND is_active = 1
        ");
        $stmt->bindValue(1, $barcode, PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$result) {
            logError("No product found for barcode", ['barcode' => $barcode]);
            return false;
        }
        
        // Process product to ensure proper image path
        if (empty($result['image']) || !file_exists(__DIR__ . '/' . $result['image'])) {
            $result['image'] = 'uploads/placeholder.jpg';
        }
        
        // Ensure image path is relative to web root
        if (!str_starts_with($result['image'], 'uploads/')) {
            $result['image'] = 'uploads/' . basename($result['image']);
        }
        
        return $result;
    } catch (PDOException $e) {
        logError("Error fetching product by barcode: " . $e->getMessage(), [
            'barcode' => $barcode,
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);
        return false;
    }
}

function getDashboardStats() {
    global $pdo;

    // If using DummyPDO, return dummy stats
    if ($pdo instanceof DummyPDO) {
        return [
            'total_products' => 0,
            'total_categories' => 0,
            'visible_products' => 0,
            'low_stock' => 0
        ];
    }
    
    try {
        $stats = [
            'total_products' => 0,
            'total_categories' => 0,
            'visible_products' => 0,
            'low_stock' => 0
        ];
        
        $stmt = $pdo->query("SELECT COUNT(*) FROM products WHERE is_active = 1");
        $stats['total_products'] = $stmt->fetchColumn();
        
        $stmt = $pdo->query("SELECT COUNT(DISTINCT category) FROM products WHERE is_active = 1 AND category IS NOT NULL");
        $stats['total_categories'] = $stmt->fetchColumn();
        
        $stmt = $pdo->query("SELECT COUNT(*) FROM products WHERE is_active = 1 AND customer_visible = 1");
        $stats['visible_products'] = $stmt->fetchColumn();
        
        $stmt = $pdo->query("SELECT COUNT(*) FROM products WHERE is_active = 1 AND stock_quantity <= low_stock_threshold");
        $stats['low_stock'] = $stmt->fetchColumn();
        
        return $stats;
    } catch (PDOException $e) {
        logError("Error fetching dashboard stats: " . $e->getMessage(), [
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);
        throw $e;
    }
}

function getRecentProducts($limit = 5) {
    global $pdo;

    // If using DummyPDO, return empty array
    if ($pdo instanceof DummyPDO) {
        return [];
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT product_id, name, price, stock_quantity, created_at, category, image
            FROM products
            WHERE is_active = 1
            ORDER BY created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Process products to ensure proper image paths
        foreach ($products as &$product) {
            if (empty($product['image']) || !file_exists(__DIR__ . '/' . $product['image'])) {
                $product['image'] = 'uploads/placeholder.jpg';
            }
            
            // Ensure image path is relative to web root
            if (!str_starts_with($product['image'], 'uploads/')) {
                $product['image'] = 'uploads/' . basename($product['image']);
            }
        }
        
        return $products;
    } catch (PDOException $e) {
        logError("Error fetching recent products: " . $e->getMessage(), [
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);
        throw $e;
    }
}

function getAllProductCategories() {
    global $pdo;

    // If using DummyPDO, return empty array
    if ($pdo instanceof DummyPDO) {
        return [];
    }
    
    try {
        $stmt = $pdo->query("SELECT DISTINCT category AS name FROM products WHERE is_active = 1 AND category IS NOT NULL ORDER BY category");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        logError("Error fetching categories: " . $e->getMessage(), [
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);
        throw $e;
    }
}

function getProductsByCategory($category) {
    global $pdo;

    // If using DummyPDO, return empty array
    if ($pdo instanceof DummyPDO) {
        return [];
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT product_id, name, price, stock_quantity, barcodes, 
                   is_promotion, customer_visible as show_on_customer_dashboard, image, 
                   description, created_at, category
            FROM products
            WHERE category = ? AND is_active = 1
            ORDER BY name
        ");
        $stmt->execute([$category]);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Process products to ensure proper image paths
        foreach ($products as &$product) {
            if (empty($product['image']) || !file_exists(__DIR__ . '/' . $product['image'])) {
                $product['image'] = 'uploads/placeholder.jpg';
            }
            
            // Ensure image path is relative to web root
            if (!str_starts_with($product['image'], 'uploads/')) {
                $product['image'] = 'uploads/' . basename($product['image']);
            }
        }
        
        return $products;
    } catch (PDOException $e) {
        logError("Error fetching products by category: " . $e->getMessage(), [
            'category' => $category,
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);
        throw $e;
    }
}

function getProducts() {
    global $pdo;

    // If using DummyPDO, return empty array
    if ($pdo instanceof DummyPDO) {
        return [];
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT product_id, name, price, stock_quantity, barcodes, 
                   is_promotion, customer_visible as show_on_customer_dashboard, image, 
                   description, created_at, category
            FROM products
            WHERE is_active = 1
            ORDER BY created_at DESC
        ");
        $stmt->execute();
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Process products to ensure proper image paths
        foreach ($products as &$product) {
            if (empty($product['image']) || !file_exists(__DIR__ . '/' . $product['image'])) {
                $product['image'] = 'uploads/placeholder.jpg';
            }
            
            // Ensure image path is relative to web root
            if (!str_starts_with($product['image'], 'uploads/')) {
                $product['image'] = 'uploads/' . basename($product['image']);
            }
        }
        
        return $products;
    } catch (PDOException $e) {
        logError("Error fetching products: " . $e->getMessage(), [
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);
        throw $e;
    }
}
?>

