<?php
session_start();
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

try {
    $category = $_POST['category'] ?? '';
    if (empty($category)) {
        throw new Exception('Category is required');
    }
    deleteProductsByCategory($category);
    echo json_encode(['success' => true, 'message' => 'Category deleted successfully']);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    error_log("Error in delete_category.php: " . $e->getMessage());
}
?>