<?php
// Suppress PHP warnings/errors in output
ini_set('display_errors', '0');
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/functions.php';

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

function sendResponse($success, $message, $data = null, $httpCode = 200) {
    http_response_code($httpCode);
    $response = [
        'success' => $success,
        'message' => $message,
        'timestamp' => date('c')
    ];
    
    if ($data !== null) {
        $response['data'] = $data;
    }
    
    echo json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function sendError($message, $httpCode = 400, $details = null) {
    logError("API Error: $message" . ($details ? ' - Details: ' . json_encode($details) : ''));
    sendResponse(false, $message, $details, $httpCode);
}

function getGlobalDiscountPercentage() {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_name = ?");
        $stmt->execute(['promotion_discount_percentage']);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? (float)$result['setting_value'] : 0;
    } catch (PDOException $e) {
        logError("Error fetching global discount percentage: " . $e->getMessage());
        return 0;
    }
}

try {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'inventory_manager') {
        sendError('Unauthorized access. Please log in as an inventory manager.', 403);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendError('Only POST requests are allowed', 405);
    }

    $mode = sanitizeInput($_POST['registration_mode'] ?? '');
    $validModes = ['new_product', 'update_stock'];
    
    if (!in_array($mode, $validModes)) {
        sendError('Invalid registration mode.', 400, ['mode' => $mode]);
    }

    switch ($mode) {
        case 'new_product':
            handleProductRegistration();
            break;
        case 'update_stock':
            handleUpdateStock();
            break;
    }
} catch (Exception $e) {
    sendError('Server error during registration: ' . $e->getMessage(), 500, [
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}

function handleProductRegistration() {
    global $pdo;
    
    // Sanitize inputs
    $barcode = sanitizeInput($_POST['barcode'] ?? '', 'barcode');
    $name = sanitizeInput($_POST['name'] ?? '');
    $category = sanitizeInput($_POST['category'] ?? '');
    $price = sanitizeInput($_POST['price'] ?? 0, 'float');
    $quantity = sanitizeInput($_POST['quantity'] ?? 0, 'int');
    $expiryDate = sanitizeInput($_POST['expiry_date'] ?? '', 'date');
    $description = sanitizeInput($_POST['description'] ?? '');
    $isPromotion = isset($_POST['is_promotion']) && ($_POST['is_promotion'] === '1' || $_POST['is_promotion'] === 'true');
    $customerVisible = isset($_POST['customer_visible']) && ($_POST['customer_visible'] === '1' || $_POST['customer_visible'] === 'true');
    $lowStockThreshold = sanitizeInput($_POST['low_stock_threshold'] ?? 10, 'int');

    // Validation
    if (empty($barcode) || !ctype_digit($barcode)) {
        sendError('Barcode is required and must be digits only', 400, ['field' => 'barcode']);
    }
    if (empty($name)) {
        sendError('Product name is required', 400, ['field' => 'name']);
    }
    if (empty($category)) {
        sendError('Category is required', 400, ['field' => 'category']);
    }
    if ($price <= 0) {
        sendError('Price must be greater than 0', 400, ['field' => 'price']);
    }
    if ($quantity <= 0) {
        sendError('Quantity must be greater than 0', 400, ['field' => 'quantity']);
    }
    if ($lowStockThreshold < 0) {
        sendError('Low stock threshold cannot be negative', 400, ['field' => 'low_stock_threshold']);
    }
    if (!empty($expiryDate) && !DateTime::createFromFormat('Y-m-d', $expiryDate)) {
        sendError('Invalid expiry date format. Use YYYY-MM-DD.', 400, ['field' => 'expiry_date']);
    }

    // Check barcode uniqueness
    if (checkBarcodeExists($barcode)) {
        sendError('Product with this barcode already exists', 400, ['barcode' => $barcode]);
    }

    try {
        $pdo->beginTransaction();

        // Handle image upload
        $imagePath = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $imagePath = handleImageUpload($_FILES['image']);
        }

        // Get global discount percentage for promotional items
        $discountPercentage = $isPromotion ? getGlobalDiscountPercentage() : 0;

        // Insert product
        $stmt = $pdo->prepare("
            INSERT INTO products (
                name, price, description, image, is_promotion, customer_visible, 
                category, expiry_date, stock_quantity, is_active, barcodes, 
                discount_percentage, show_on_customer_dashboard, low_stock_threshold
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $name,
            $price,
            $description,
            $imagePath,
            $isPromotion ? 1 : 0,
            $customerVisible ? 1 : 0,
            $category,
            $expiryDate ?: null,
            $quantity,
            $barcode,
            $discountPercentage,
            $customerVisible ? 1 : 0,
            $lowStockThreshold
        ]);

        $productId = $pdo->lastInsertId();

        // Log stock movement
        logStockMovement($productId, $quantity, 'in', 'Initial stock entry');

        // Log user action
        logUserAction($_SESSION['user_id'], "Added product: {$name} in category {$category}", 'product_registration');

        $pdo->commit();

        sendResponse(true, 'Product registered successfully', [
            'product_id' => $productId,
            'barcode' => $barcode,
            'name' => $name,
            'category' => $category,
            'price' => $price,
            'quantity' => $quantity,
            'expiry_date' => $expiryDate,
            'is_promotion' => $isPromotion,
            'discount_percentage' => $discountPercentage
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();
        sendError('Failed to register product: ' . $e->getMessage(), 500, [
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);
    }
}

function handleUpdateStock() {
    global $pdo;
    
    $barcode = sanitizeInput($_POST['barcode'] ?? '', 'barcode');
    $quantity = sanitizeInput($_POST['quantity'] ?? 0, 'int');

    if (empty($barcode) || !ctype_digit($barcode)) {
        sendError('Barcode is required and must be digits only', 400, ['field' => 'barcode']);
    }
    if ($quantity <= 0) {
        sendError('Quantity must be greater than 0', 400, ['field' => 'quantity']);
    }

    try {
        $pdo->beginTransaction();

        // Get existing product
        $stmt = $pdo->prepare("SELECT product_id, name, stock_quantity FROM products WHERE barcodes = ? AND is_active = 1");
        $stmt->execute([$barcode]);
        $existingProduct = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$existingProduct) {
            sendError('Product not found', 404, ['barcode' => $barcode]);
        }

        $productId = $existingProduct['product_id'];
        $currentStock = (int)$existingProduct['stock_quantity'];
        $newStock = $currentStock + $quantity;

        // Update stock
        $stmt = $pdo->prepare("UPDATE products SET stock_quantity = ?, updated_at = NOW() WHERE product_id = ?");
        $stmt->execute([$newStock, $productId]);

        // Log stock movement
        logStockMovement($productId, $quantity, 'in', 'Stock increased via update');

        // Log user action
        logUserAction($_SESSION['user_id'], "Updated stock for product: {$existingProduct['name']} (+{$quantity})", 'stock_update');

        $pdo->commit();

        sendResponse(true, 'Product stock updated successfully', [
            'product_id' => $productId,
            'product_name' => $existingProduct['name'],
            'quantity_added' => $quantity,
            'new_stock' => $newStock,
            'previous_stock' => $currentStock
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();
        sendError('Failed to update product stock: ' . $e->getMessage(), 500, [
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);
    }
}
?>