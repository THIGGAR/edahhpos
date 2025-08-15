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
    logError("Stock Management Error: $message", $details ? ['details' => $details] : []);
    sendResponse(false, $message, $details, $httpCode);
}

try {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'inventory_manager') {
        sendError('Unauthorized access. Please log in as an inventory manager.', 403);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendError('Only POST requests are allowed', 405);
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }

    $action = sanitizeInput($input['action'] ?? '');
    
    switch ($action) {
        case 'adjust_stock':
            handleStockAdjustment($input);
            break;
        case 'bulk_stock_update':
            handleBulkStockUpdate($input);
            break;
        case 'get_stock_movements':
            handleGetStockMovements($input);
            break;
        case 'get_low_stock':
            handleGetLowStock($input);
            break;
        case 'set_stock_alert':
            handleSetStockAlert($input);
            break;
        default:
            sendError('Invalid action specified');
    }

} catch (Exception $e) {
    sendError($e->getMessage(), 500);
}

function handleStockAdjustment($input) {
    $productId = sanitizeInput($input['product_id'] ?? 0, 'int');
    $adjustment = sanitizeInput($input['adjustment'] ?? 0, 'int');
    $reason = sanitizeInput($input['reason'] ?? '');
    $registrationMethod = sanitizeInput($input['registration_method'] ?? 'manual');
    $barcode = sanitizeInput($input['barcode'] ?? '');
    
    // If barcode is provided, find product by barcode
    if (!$productId && !empty($barcode)) {
        $product = getProductByBarcode($barcode);
        if ($product) {
            $productId = $product['product_id'];
        } else {
            sendError('No product found with the provided barcode');
        }
    }
    
    if (!$productId) {
        sendError('Product ID or barcode is required');
    }
    
    if ($adjustment == 0) {
        sendError('Stock adjustment amount is required');
    }
    
    try {
        $existingProduct = getProductById($productId);
        if (!$existingProduct) {
            sendError('Product not found');
        }
        
        $currentStock = (int)($existingProduct['stock_quantity'] ?? 0);
        $newStock = max(0, $currentStock + $adjustment);
        
        // Update stock
        global $pdo;
        $stmt = $pdo->prepare("UPDATE products SET stock_quantity = ?, updated_at = NOW() WHERE product_id = ?");
        $stmt->execute([$newStock, $productId]);
        
        // Log stock movement
        $movementType = $adjustment > 0 ? 'stock_in' : 'stock_out';
        $description = $reason ?: ($adjustment > 0 ? "Stock increased by $adjustment" : "Stock decreased by " . abs($adjustment));
        logStockMovement($productId, abs($adjustment), $movementType, $description);
        
        // Log user action
        if (isset($_SESSION['user_id'])) {
            logUserAction($_SESSION['user_id'], "Adjusted stock for product: {$existingProduct['name']} by $adjustment", 'stock_adjustment');
        }
        
        sendResponse(true, 'Stock adjusted successfully', [
            'product_id' => $productId,
            'previous_stock' => $currentStock,
            'new_stock' => $newStock,
            'adjustment' => $adjustment,
            'registration_method' => $registrationMethod
        ]);
        
    } catch (Exception $e) {
        sendError($e->getMessage());
    }
}

function handleBulkStockUpdate($input) {
    $updates = $input['updates'] ?? [];
    
    if (empty($updates) || !is_array($updates)) {
        sendError('No updates provided');
    }
    
    try {
        global $pdo;
        $pdo->beginTransaction();
        
        $results = [];
        
        foreach ($updates as $update) {
            $productId = sanitizeInput($update['product_id'] ?? 0, 'int');
            $adjustment = sanitizeInput($update['adjustment'] ?? 0, 'int');
            $reason = sanitizeInput($update['reason'] ?? '');
            
            if (!$productId || $adjustment == 0) {
                continue;
            }
            
            $existingProduct = getProductById($productId);
            if (!$existingProduct) {
                continue;
            }
            
            $currentStock = (int)($existingProduct['stock_quantity'] ?? 0);
            $newStock = max(0, $currentStock + $adjustment);
            
            // Update stock
            $stmt = $pdo->prepare("UPDATE products SET stock_quantity = ?, updated_at = NOW() WHERE product_id = ?");
            $stmt->execute([$newStock, $productId]);
            
            // Log stock movement
            $movementType = $adjustment > 0 ? 'stock_in' : 'stock_out';
            $description = $reason ?: ($adjustment > 0 ? "Bulk stock increase by $adjustment" : "Bulk stock decrease by " . abs($adjustment));
            logStockMovement($productId, abs($adjustment), $movementType, $description);
            
            $results[] = [
                'product_id' => $productId,
                'product_name' => $existingProduct['name'],
                'previous_stock' => $currentStock,
                'new_stock' => $newStock,
                'adjustment' => $adjustment
            ];
        }
        
        $pdo->commit();
        
        // Log user action
        if (isset($_SESSION['user_id'])) {
            $count = count($results);
            logUserAction($_SESSION['user_id'], "Bulk updated stock for $count products", 'bulk_stock_update');
        }
        
        sendResponse(true, 'Bulk stock update completed successfully', [
            'updated_products' => $results,
            'total_updated' => count($results)
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        sendError($e->getMessage());
    }
}

function handleGetStockMovements($input) {
    $productId = sanitizeInput($input['product_id'] ?? null, 'int');
    $limit = sanitizeInput($input['limit'] ?? 50, 'int');
    $offset = sanitizeInput($input['offset'] ?? 0, 'int');
    
    try {
        global $pdo;
        
        $sql = "SELECT sm.*, p.name as product_name, p.barcode 
                FROM stock_movements sm 
                LEFT JOIN products p ON sm.product_id = p.product_id";
        $params = [];
        
        if ($productId) {
            $sql .= " WHERE sm.product_id = ?";
            $params[] = $productId;
        }
        
        $sql .= " ORDER BY sm.created_at DESC";
        
        if ($limit > 0) {
            $sql .= " LIMIT " . (int)$limit;
            if ($offset > 0) {
                $sql .= " OFFSET " . (int)$offset;
            }
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $movements = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get total count
        $countSql = "SELECT COUNT(*) FROM stock_movements sm";
        if ($productId) {
            $countSql .= " WHERE sm.product_id = ?";
            $countStmt = $pdo->prepare($countSql);
            $countStmt->execute($productId ? [$productId] : []);
        } else {
            $countStmt = $pdo->query($countSql);
        }
        $totalCount = $countStmt->fetchColumn();
        
        sendResponse(true, 'Stock movements retrieved successfully', [
            'movements' => $movements,
            'total_count' => $totalCount,
            'limit' => $limit,
            'offset' => $offset
        ]);
        
    } catch (Exception $e) {
        sendError($e->getMessage());
    }
}

function handleGetLowStock($input) {
    $threshold = sanitizeInput($input['threshold'] ?? 5, 'int');
    
    try {
        $lowStockProducts = getLowStockProducts($threshold);
        
        sendResponse(true, 'Low stock products retrieved successfully', [
            'products' => $lowStockProducts,
            'threshold' => $threshold,
            'count' => count($lowStockProducts)
        ]);
        
    } catch (Exception $e) {
        sendError($e->getMessage());
    }
}

function handleSetStockAlert($input) {
    $productId = sanitizeInput($input['product_id'] ?? 0, 'int');
    $threshold = sanitizeInput($input['threshold'] ?? 5, 'int');
    
    if (!$productId) {
        sendError('Product ID is required');
    }
    
    if ($threshold < 0) {
        sendError('Threshold must be a positive number');
    }
    
    try {
        global $pdo;
        
        // Check if alert already exists
        $stmt = $pdo->prepare("SELECT id FROM stock_alerts WHERE product_id = ?");
        $stmt->execute([$productId]);
        $existingAlert = $stmt->fetch();
        
        if ($existingAlert) {
            // Update existing alert
            $stmt = $pdo->prepare("UPDATE stock_alerts SET threshold = ?, updated_at = NOW() WHERE product_id = ?");
            $stmt->execute([$threshold, $productId]);
        } else {
            // Create new alert
            $stmt = $pdo->prepare("INSERT INTO stock_alerts (product_id, threshold, created_at, updated_at) VALUES (?, ?, NOW(), NOW())");
            $stmt->execute([$productId, $threshold]);
        }
        
        // Log user action
        if (isset($_SESSION['user_id'])) {
            $product = getProductById($productId);
            $productName = $product ? $product['name'] : "Product ID $productId";
            logUserAction($_SESSION['user_id'], "Set stock alert for $productName (threshold: $threshold)", 'stock_alert');
        }
        
        sendResponse(true, 'Stock alert set successfully', [
            'product_id' => $productId,
            'threshold' => $threshold
        ]);
        
    } catch (Exception $e) {
        sendError($e->getMessage());
    }
}
?>

