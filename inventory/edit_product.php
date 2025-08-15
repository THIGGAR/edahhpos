<?php
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
    logError("API Error: $message", $details ? ['details' => $details] : []);
    sendResponse(false, $message, $details, $httpCode);
}

try {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'inventory_manager') {
        sendError('Unauthorized access. Please log in as an inventory manager.', 403);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendError('Only POST requests are allowed', 405);
    }

    $input = [];
    if (strpos($_SERVER['CONTENT_TYPE'] ?? '', 'multipart/form-data') !== false) {
        $input = $_POST;
    } else {
        $input = json_decode(file_get_contents('php://input'), true);
    }

    if (!checkCsrfToken($input['csrf_token'] ?? '')) {
        sendError('Invalid CSRF token', 403);
    }

    $productId = sanitizeInput($input['product_id'] ?? 0, 'int');
    if (!$productId) {
        sendError('Product ID is required');
    }

    $existingProduct = getProductById($productId);
    if (!$existingProduct) {
        sendError('Product not found');
    }

    $updateData = [];
    if (isset($input['name']) && !empty(trim($input['name']))) {
        $updateData['name'] = sanitizeInput($input['name']);
    }
    if (isset($input['category']) && !empty(trim($input['category']))) {
        $updateData['category'] = sanitizeInput($input['category']);
    }
    if (isset($input['price'])) {
        $price = sanitizeInput($input['price'], 'float');
        if ($price >= 0) {
            $updateData['price'] = $price;
        }
    }
    if (isset($input['barcode']) && !empty(trim($input['barcode']))) {
        $barcode = sanitizeInput($input['barcode'], 'barcode');
        if (checkBarcodeExists($barcode) && $barcode !== $existingProduct['barcodes']) {
            sendError('Barcode already in use');
        }
        $updateData['barcodes'] = $barcode;
    }
    if (isset($input['is_promotion'])) {
        $updateData['is_promotion'] = $input['is_promotion'] ? 1 : 0;
    }
    if (isset($input['customer_visible'])) {
        $updateData['customer_visible'] = $input['customer_visible'] ? 1 : 0;
    }
    if (isset($input['expiry_date']) && !empty(trim($input['expiry_date']))) {
        $expiryDate = sanitizeInput($input['expiry_date'], 'date');
        if (!$expiryDate || !strtotime($expiryDate)) {
            sendError('Invalid expiry date format');
        }
        $updateData['expiry_date'] = $expiryDate;
    }

    $adjustStock = false;
    if (isset($input['stock_adjustment'])) {
        $stockAdjustment = sanitizeInput($input['stock_adjustment'], 'int');
        if ($stockAdjustment != 0) {
            $updateData['stock_adjustment'] = $stockAdjustment;
            $adjustStock = true;
        }
    }

    $imageFile = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $imageFile = $_FILES['image'];
    }

    $success = updateProduct($productId, $updateData, $imageFile, $adjustStock);
    if ($success) {
        $updatedProduct = getProductById($productId);
        sendResponse(true, 'Product updated successfully', ['product' => $updatedProduct]);
    } else {
        sendError('Failed to update product');
    }
} catch (Exception $e) {
    sendError($e->getMessage(), 500);
}
?>