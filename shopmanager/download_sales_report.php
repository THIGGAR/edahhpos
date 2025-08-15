<?php
// download_sales_report.php
require_once __DIR__ . '/db_connect.php'; // Ensure database connection
require_once __DIR__ . '/functions.php';

// Redirect if user is not logged in or not a shop manager
redirectUnlessRole('shop_manager');

// Get filter parameters from POST
$date_from = $_POST['date_from'] ?? '';
$date_to = $_POST['date_to'] ?? '';
$payment_method = $_POST['payment_method'] ?? '';
$product_id = $_POST['product_id'] ?? '';

// Build the sales data query
$sales_data_query = "SELECT o.created_at, p.name as product_name, oi.quantity, oi.price as unit_price, 
                            (oi.quantity * oi.price) as total_revenue, o.payment_method 
                     FROM orders o
                     JOIN order_items oi ON o.order_id = oi.order_id
                     JOIN products p ON oi.product_id = p.product_id
                     WHERE o.status = 'completed'";
$params = [];
$param_types = '';

if ($date_from) {
    $sales_data_query .= " AND DATE(o.created_at) >= ?";
    $params[] = $date_from;
    $param_types .= 's';
}
if ($date_to) {
    $sales_data_query .= " AND DATE(o.created_at) <= ?";
    $params[] = $date_to;
    $param_types .= 's';
}
if ($payment_method) {
    $sales_data_query .= " AND o.payment_method = ?";
    $params[] = $payment_method;
    $param_types .= 's';
}
if ($product_id) {
    $sales_data_query .= " AND oi.product_id = ?";
    $params[] = $product_id;
    $param_types .= 'i';
}
$sales_data_query .= " ORDER BY o.created_at DESC";

// Prepare and execute the query
$stmt = $conn->prepare($sales_data_query);
if ($stmt === false) {
    die("Sales query prepare failed: " . $conn->error);
}
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
if (!$stmt->execute()) {
    die("Sales query execute failed: " . $stmt->error);
}
$sales_data_result = $stmt->get_result();
$sales_data = $sales_data_result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Close database connection
mysqli_close($conn);

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="sales_report_' . date('Ymd_His') . '.csv"');

// Open output stream
$output = fopen('php://output', 'w');

// Write CSV headers
fputcsv($output, ['Date', 'Product', 'Quantity Sold', 'Unit Price (MWK)', 'Total Revenue (MWK)', 'Payment Method']);

// Write CSV rows
if (empty($sales_data)) {
    fputcsv($output, ['No sales data available for the selected filters']);
} else {
    foreach ($sales_data as $sale) {
        fputcsv($output, [
            date('Y-m-d H:i', strtotime($sale['created_at'])),
            $sale['product_name'],
            $sale['quantity'],
            number_format($sale['unit_price'], 2),
            number_format($sale['total_revenue'], 2),
            $sale['payment_method']
        ]);
    }
}

// Close output stream
fclose($output);
exit;
?>