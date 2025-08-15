<?php
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/functions.php';

header('Content-Type: application/json');

$category = isset($_POST['category']) ? sanitizeInput($_POST['category']) : '';
$visibility = isset($_POST['visibility']) ? sanitizeInput($_POST['visibility'], 'int') : '';
$promotion = isset($_POST['promotion']) ? sanitizeInput($_POST['promotion'], 'int') : '';
$search = isset($_POST['search']) ? sanitizeInput($_POST['search']) : '';
$page = isset($_POST['page']) ? max(1, sanitizeInput($_POST['page'], 'int')) : 1;
$itemsPerPage = isset($_POST['items_per_page']) ? sanitizeInput($_POST['items_per_page'], 'int') : 10;

try {
    $offset = ($page - 1) * $itemsPerPage;
    
    $query = "SELECT product_id, name, price, stock_quantity, barcodes, 
                     is_promotion, customer_visible as show_on_customer_dashboard, 
                     image, description, category, created_at
              FROM products
              WHERE is_active = 1";
    
    $params = [];
    $conditions = [];
    
    if ($category) {
        $conditions[] = "category = ?";
        $params[] = $category;
    }
    if ($visibility !== '') {
        $conditions[] = "customer_visible = ?";
        $params[] = $visibility;
    }
    if ($promotion !== '') {
        $conditions[] = "is_promotion = ?";
        $params[] = $promotion;
    }
    if ($search) {
        $conditions[] = "(name LIKE ? OR barcodes LIKE ? OR description LIKE ?)";
        $searchTerm = "%$search%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    if ($conditions) {
        $query .= " AND " . implode(" AND ", $conditions);
    }
    
    // Count total items
    $countStmt = $pdo->prepare(str_replace('SELECT *', 'SELECT COUNT(*)', $query));
    $countStmt->execute($params);
    $totalItems = $countStmt->fetchColumn();
    
    // Fetch paginated items
    $query .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
    $params[] = $itemsPerPage;
    $params[] = $offset;
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($products as &$product) {
        if (empty($product['image']) || !file_exists(__DIR__ . '/' . $product['image'])) {
            $product['image'] = 'images/placeholder.jpg';
        }
        if (!str_starts_with($product['image'], 'images/')) {
            $product['image'] = 'images/' . basename($product['image']);
        }
    }
    
    $response = [
        'success' => true,
        'data' => [
            'products' => $products,
            'total' => $totalItems
        ]
    ];
} catch (Exception $e) {
    logError("Error fetching products: " . $e->getMessage());
    $response = [
        'success' => false,
        'message' => 'Error fetching products'
    ];
}

echo json_encode($response);
?>