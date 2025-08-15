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
    logError("Get Categories Error: $message", $details ? ['details' => $details] : []);
    sendResponse(false, $message, $details, $httpCode);
}

try {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'inventory_manager') {
        sendError('Unauthorized access. Please log in as an inventory manager.', 403);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        sendError('Only GET requests are allowed', 405);
    }

    $categories = getAllProductCategories();

    if ($categories === false) {
        sendError('Failed to fetch categories from database', 500);
    }

    sendResponse(true, 'Categories retrieved successfully', $categories);
} catch (PDOException $e) {
    sendError('Database error while fetching categories: ' . $e->getMessage(), 500, [
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'code' => $e->getCode()
    ]);
} catch (Exception $e) {
    sendError('Server error while fetching categories: ' . $e->getMessage(), 500, [
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}
?>