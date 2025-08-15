<?php
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
    logError("Check Barcode Error: $message", $details ? ['details' => $details] : []);
    sendResponse(false, $message, $details, $httpCode);
}

try {
    if (!session_id()) {
        sendError('Session failed to start', 500, ['error' => 'Session initialization error']);
    }

    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'inventory_manager') {
        sendError('Unauthorized access. Please log in as an inventory manager.', 403);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendError('Only POST requests are allowed', 405);
    }

    $barcode = sanitizeInput($_POST['barcode'] ?? '', 'barcode');

    if (empty($barcode)) {
        sendError('Barcode is required for this operation.', 400, ['field' => 'barcode']);
    }
    
    if (!ctype_digit($barcode)) {
        sendError('Barcode must be a valid integer (digits only).', 400, ['field' => 'barcode']);
    }

    global $pdo;
    $stmt = $pdo->query("SHOW TABLES LIKE 'products'");
    if ($stmt->rowCount() === 0) {
        sendError('Products table not found in database', 500, ['table' => 'products']);
    }

    $product = getProductByBarcode($barcode);

    if ($product === false) {
        sendError('Failed to query product by barcode', 500, ['barcode' => $barcode, 'error' => 'Database query failed']);
    }

    if ($product) {
        sendResponse(true, 'Product found', [
            'product_id' => (int)$product['product_id'],
            'name' => $product['name'],
            'category' => $product['category'] ?? 'Unknown',
            'price' => (float)$product['price'],
            'stock_quantity' => (int)$product['stock_quantity'],
            'barcode' => $product['barcodes'],
            'is_promotion' => (bool)$product['is_promotion'],
            'customer_visible' => (bool)$product['show_on_customer_dashboard']
        ]);
    } else {
        sendResponse(false, 'No product found with this barcode. Please register the product.', ['barcode' => $barcode], 404);
    }
} catch (PDOException $e) {
    sendError('Database error while checking barcode: ' . $e->getMessage(), 500, [
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'code' => $e->getCode()
    ]);
} catch (Exception $e) {
    sendError('Server error while checking barcode: ' . $e->getMessage(), 500, [
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}
?>