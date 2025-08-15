<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once 'db_connect.php';
require_once 'functions.php';

// Error handling function
function sendError($message, $code = 400) {
    http_response_code($code);
    echo json_encode(['error' => $message, 'success' => false]);
    exit;
}

// Success response function
function sendSuccess($data = [], $message = '') {
    echo json_encode([
        'success' => true,
        'data' => $data,
        'message' => $message
    ]);
    exit;
}

// Get the action from request
$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'lookup_barcode':
            handleBarcodeLookup();
            break;
        case 'get_dashboard_stats':
            handleDashboardStats();
            break;
        case 'get_pending_orders':
            handlePendingOrders();
            break;
        case 'get_completed_orders':
            handleCompletedOrders();
            break;
        case 'confirm_payment':
            handleConfirmPayment();
            break;
        case 'create_order':
            handleCreateOrder();
            break;
        case 'get_sales_report':
            handleSalesReport();
            break;
        case 'get_order_details':
            handleOrderDetails();
            break;
        case 'confirm_correction':
            handleConfirmCorrection();
            break;
        default:
            sendError('Invalid action specified');
    }
} catch (Exception $e) {
    error_log("API Error: " . $e->getMessage());
    sendError('An internal error occurred');
}

function handleBarcodeLookup() {
    $barcode = filter_input(INPUT_POST, 'barcode', FILTER_SANITIZE_STRING);
    
    if (empty($barcode)) {
        sendError('Barcode is required');
    }
    
    $products = getProductByBarcode($barcode);
    
    if (!empty($products)) {
        sendSuccess($products[0], 'Product found');
    } else {
        sendError("Product not found for barcode: $barcode", 404);
    }
}

function handleDashboardStats() {
    $stats = getDashboardStats();
    sendSuccess($stats, 'Dashboard stats retrieved');
}

function handlePendingOrders() {
    $orders = getPendingOrders();
    sendSuccess($orders, 'Pending orders retrieved');
}

function handleCompletedOrders() {
    $orders = getCompletedOrders();
    sendSuccess($orders, 'Completed orders retrieved');
}

function handleConfirmPayment() {
    $order_id = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);
    
    if (!$order_id) {
        sendError('Valid order ID is required');
    }
    
    if (confirmPayment($order_id)) {
        sendSuccess([
            'order_id' => $order_id,
            'status' => 'completed',
            'confirmed_at' => date('Y-m-d H:i:s')
        ], "Payment confirmed for order #$order_id");
    } else {
        sendError("Failed to confirm payment for order #$order_id");
    }
}

function handleCreateOrder() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendError('POST method required');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        $items = $_POST['items'] ?? '';
        $payment_method = $_POST['payment_method'] ?? '';
        $total = $_POST['total'] ?? 0;
        
        if (is_string($items)) {
            $items = json_decode($items, true);
        }
    } else {
        $items = $input['items'] ?? [];
        $payment_method = $input['payment_method'] ?? '';
        $total = $input['total'] ?? 0;
    }
    
    if (empty($items) || empty($payment_method) || $total <= 0) {
        sendError('Items, payment method, and total are required');
    }
    
    $order_id = createOrder($_SESSION['user_id'], $items, $payment_method, $total);
    
    if ($order_id) {
        sendSuccess([
            'order_id' => $order_id,
            'total' => $total,
            'payment_method' => $payment_method,
            'status' => 'pending',
            'created_at' => date('Y-m-d H:i:s')
        ], "Order #$order_id created successfully");
    } else {
        sendError('Failed to create order');
    }
}

function handleSalesReport() {
    $start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-7 days'));
    $end_date = $_GET['end_date'] ?? date('Y-m-d');
    
    $report = getSalesReport($start_date, $end_date);
    
    $summary = [
        'total_orders' => array_sum(array_column($report, 'orders_count')),
        'total_sales' => array_sum(array_column($report, 'total_sales')),
    ];
    
    sendSuccess([
        'summary' => $summary,
        'daily_data' => $report,
        'period' => [
            'start_date' => $start_date,
            'end_date' => $end_date
        ]
    ], 'Sales report generated');
}

function handleOrderDetails() {
    $order_id = filter_input(INPUT_GET, 'order_id', FILTER_VALIDATE_INT);
    
    if (!$order_id) {
        sendError('Valid order ID is required');
    }
    
    $order = getOrderById($order_id);
    $items = getOrderItems($order_id);
    
    if ($order) {
        sendSuccess([
            'order' => $order,
            'items' => $items
        ], 'Order details retrieved');
    } else {
        sendError('Order not found', 404);
    }
}

function handleConfirmCorrection() {
    $order_id = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);
    
    if (!$order_id) {
        sendError('Valid order ID is required');
    }
    
    if (confirmOrderCorrection($order_id)) {
        sendSuccess([
            'order_id' => $order_id,
            'status' => 'completed',
            'corrected_at' => date('Y-m-d H:i:s')
        ], "Correction confirmed for order #$order_id");
    } else {
        sendError("Failed to confirm correction for order #$order_id");
    }
}

function getOrderById($order_id) {
    global $conn;
    
    try {
        $stmt = $conn->prepare("
            SELECT o.order_id, o.total_amount as total, o.payment_method, o.created_at, o.status, o.payment_status,
                   u.first_name, u.last_name, u.username
            FROM orders o 
            LEFT JOIN users u ON o.user_id = u.user_id 
            WHERE o.order_id = ?
        ");
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $order = $result->fetch_assoc();
        $stmt->close();
        return $order;
    } catch (Exception $e) {
        error_log("Error fetching order #$order_id: " . $e->getMessage());
        return false;
    }
}
?>