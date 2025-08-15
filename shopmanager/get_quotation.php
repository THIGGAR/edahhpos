<?php
session_start();

// Enable error reporting for development (remove in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Database connection
$host = 'localhost';
$dbname = 'aepos';
$username = 'root';
$password = '';
$conn = new mysqli($host, $username, $password, $dbname);

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection failed: ' . $conn->connect_error]);
    exit;
}

// Check if user is logged in and has shop_manager role
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'shop_manager') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit;
}

// Handle GET request for quotation details
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
    $quotation_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    
    if (!$quotation_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid quotation ID']);
        exit;
    }

    // Fetch quotation details
    $stmt = $conn->prepare("
        SELECT q.quotation_id, q.supplier_id, q.items, q.total_amount, q.status, q.created_at, 
               CONCAT(u.first_name, ' ', u.last_name) AS supplier_name,
               u.email AS supplier_email, u.phone AS supplier_phone
        FROM quotations q 
        JOIN users u ON q.supplier_id = u.user_id 
        WHERE q.quotation_id = ?
    ");
    $stmt->bind_param('i', $quotation_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($quotation = $result->fetch_assoc()) {
        // Decode items JSON
        $quotation['items'] = json_decode($quotation['items'], true);
        echo json_encode(['success' => true, 'quotation' => $quotation]);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Quotation not found']);
    }
    
    $stmt->close();
} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
}

$conn->close();
?>