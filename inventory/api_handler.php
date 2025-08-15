<?php
header('Content-Type: application/json');
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/functions.php';

$action = isset($_GET['action']) ? sanitizeInput($_GET['action']) : '';

function sendResponse($success, $data = [], $message = '') {
    echo json_encode([
        'success' => $success,
        'data' => $data,
        'message' => $message
    ]);
    exit;
}

try {
    switch ($action) {
        case 'get_categories':
            $categories = getAllProductCategories();
            sendResponse(true, $categories, 'Categories fetched successfully');
            break;

        case 'get_products':
            $filters = [];
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $filters['category'] = isset($_POST['category']) ? sanitizeInput($_POST['category']) : '';
                $filters['visibility'] = isset($_POST['visibility']) ? sanitizeInput($_POST['visibility'], 'int') : '';
                $filters['promotion'] = isset($_POST['promotion']) ? sanitizeInput($_POST['promotion'], 'int') : '';
                $filters['search'] = isset($_POST['search']) ? sanitizeInput($_POST['search']) : '';
                $page = isset($_POST['page']) ? sanitizeInput($_POST['page'], 'int') : 1;
                $limit = isset($_POST['limit']) ? sanitizeInput($_POST['limit'], 'int') : 10;
            } else {
                $page = 1;
                $limit = 10;
            }

            $offset = ($page - 1) * $limit;
            $query = "SELECT product_id, name, price, stock_quantity, barcodes, is_promotion, customer_visible AS show_on_customer_dashboard, image, description, created_at, category 
                      FROM products 
                      WHERE is_active = 1";
            $params = [];

            if ($filters['category']) {
                $query .= " AND category = ?";
                $params[] = $filters['category'];
            }
            if ($filters['visibility'] !== '') {
                $query .= " AND customer_visible = ?";
                $params[] = $filters['visibility'];
            }
            if ($filters['promotion'] !== '') {
                $query .= " AND is_promotion = ?";
                $params[] = $filters['promotion'];
            }
            if ($filters['search']) {
                $query .= " AND (name LIKE ? OR barcodes LIKE ? OR description LIKE ?)";
                $searchTerm = "%{$filters['search']}%";
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }

            $countQuery = str_replace("SELECT product_id, name, price, stock_quantity, barcodes, is_promotion, customer_visible AS show_on_customer_dashboard, image, description, created_at, category", "SELECT COUNT(*)", $query);
            $stmt = $pdo->prepare($countQuery);
            $stmt->execute($params);
            $total = $stmt->fetchColumn();

            $query .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;

            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($products as &$product) {
                if (empty($product['image']) || !file_exists(__DIR__ . '/' . $product['image'])) {
                    $product['image'] = 'uploads/placeholder.png';
                }
                if (!str_starts_with($product['image'], 'uploads/')) {
                    $product['image'] = 'uploads/' . basename($product['image']);
                }
            }

            sendResponse(true, ['products' => $products, 'total' => $total], 'Products fetched successfully');
            break;

        case 'get_products_by_category':
            $category = isset($_GET['category']) ? sanitizeInput($_GET['category']) : '';
            if (!$category) {
                sendResponse(false, [], 'Category is required');
            }
            $products = getProductsByCategory($category);
            sendResponse(true, $products, 'Products fetched successfully');
            break;

        default:
            sendResponse(false, [], 'Invalid action');
    }
} catch (Exception $e) {
    logError("API handler error: " . $e->getMessage());
    sendResponse(false, [], 'Server error occurred');
}
?>