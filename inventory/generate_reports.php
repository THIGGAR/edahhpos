<?php
session_start();
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/functions.php';

// Check if user is logged in and has the right role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'inventory_manager') {
    header("Location: ../login.php");
    exit();
}

function sendError($message, $httpCode = 400) {
    http_response_code($httpCode);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $message]);
    exit();
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendError('Only POST requests are allowed', 405);
    }

    $reportType = sanitizeInput($_POST['report_type'] ?? '');
    $format = sanitizeInput($_POST['format'] ?? 'pdf');
    $startDate = sanitizeInput($_POST['start_date'] ?? '', 'date');
    $endDate = sanitizeInput($_POST['end_date'] ?? '', 'date');
    
    $validTypes = ['daily', 'weekly', 'monthly', 'custom'];
    $validFormats = ['pdf', 'csv', 'excel'];
    
    if (!in_array($reportType, $validTypes)) {
        sendError('Invalid report type');
    }
    
    if (!in_array($format, $validFormats)) {
        sendError('Invalid format');
    }

    // Generate report based on type
    $reportData = generateReportData($reportType, $startDate, $endDate);
    
    if (empty($reportData['products'])) {
        sendError('No data available for the selected report period');
    }

    // Generate file based on format
    $filePath = generateReportFile($reportData, $reportType, $format);
    
    if (!$filePath || !file_exists($filePath)) {
        sendError('Failed to generate report file');
    }

    // Log the action
    logUserAction($_SESSION['user_id'], "Generated {$reportType} report in {$format} format", 'report_generation');

    // Set headers for download
    $filename = basename($filePath);
    $contentType = getContentType($format);
    
    header('Content-Type: ' . $contentType);
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($filePath));
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: 0');

    // Output file
    readfile($filePath);
    
    // Clean up temporary file
    unlink($filePath);
    
} catch (Exception $e) {
    error_log("Report generation error: " . $e->getMessage());
    sendError('An error occurred while generating the report: ' . $e->getMessage(), 500);
}

function generateReportData($reportType, $startDate = null, $endDate = null) {
    global $pdo;
    
    $dateCondition = getDateCondition($reportType, $startDate, $endDate);
    
    // Get products data with proper joins
    $query = "
        SELECT 
            p.product_id,
            p.name,
            p.category,
            p.price,
            p.barcode,
            p.is_promotion,
            p.show_on_customer_dashboard as customer_visible,
            p.created_at,
            p.stock_quantity,
            p.low_stock_threshold,
            p.discount_percentage,
            p.expiry_date,
            pc.name as category_name
        FROM products p
        LEFT JOIN product_categories pc ON p.category = pc.name
        WHERE p.is_active = 1 AND p.created_at >= ?
        ORDER BY p.created_at DESC
    ";
    
    $stmt = $pdo->prepare($query);
    if (!$stmt) {
        throw new Exception('Database error: ' . $pdo->errorInfo()[2]);
    }
    
    $stmt->execute([$dateCondition]);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get stock movements if table exists
    $stockMovements = [];
    try {
        $checkMovementTable = $pdo->query("SHOW TABLES LIKE 'stock_movements'");
        if ($checkMovementTable && $checkMovementTable->rowCount() > 0) {
            $query = "
                SELECT 
                    sm.movement_id,
                    sm.product_id,
                    p.name as product_name,
                    sm.quantity,
                    sm.movement_type,
                    sm.notes as description,
                    sm.created_at
                FROM stock_movements sm
                JOIN products p ON sm.product_id = p.product_id
                WHERE sm.created_at >= ? AND p.is_active = 1
                ORDER BY sm.created_at DESC
            ";
            
            $stmt = $pdo->prepare($query);
            if ($stmt) {
                $stmt->execute([$dateCondition]);
                $stockMovements = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        }
    } catch (Exception $e) {
        // Stock movements table might not exist, continue without it
        logError("Stock movements table not accessible: " . $e->getMessage());
    }
    
    // Calculate summary statistics
    $totalProducts = count($products);
    $totalValue = array_sum(array_map(function($p) { 
        return floatval($p['price']) * intval($p['stock_quantity']); 
    }, $products));
    $lowStockItems = count(array_filter($products, function($p) { 
        return intval($p['stock_quantity']) <= intval($p['low_stock_threshold']); 
    }));
    $promotionalItems = count(array_filter($products, function($p) { 
        return $p['is_promotion'] == 1; 
    }));
    $outOfStockItems = count(array_filter($products, function($p) { 
        return intval($p['stock_quantity']) == 0; 
    }));
    
    // Group by category
    $categoryStats = [];
    foreach ($products as $product) {
        $category = $product['category'] ?: 'Uncategorized';
        if (!isset($categoryStats[$category])) {
            $categoryStats[$category] = [
                'count' => 0,
                'total_value' => 0,
                'total_stock' => 0,
                'low_stock_count' => 0
            ];
        }
        $categoryStats[$category]['count']++;
        $categoryStats[$category]['total_value'] += floatval($product['price']) * intval($product['stock_quantity']);
        $categoryStats[$category]['total_stock'] += intval($product['stock_quantity']);
        
        if (intval($product['stock_quantity']) <= intval($product['low_stock_threshold'])) {
            $categoryStats[$category]['low_stock_count']++;
        }
    }
    
    return [
        'report_type' => $reportType,
        'generated_at' => date('Y-m-d H:i:s'),
        'date_range' => getDateRangeText($reportType, $startDate, $endDate),
        'summary' => [
            'total_products' => $totalProducts,
            'total_value' => $totalValue,
            'low_stock_items' => $lowStockItems,
            'promotional_items' => $promotionalItems,
            'out_of_stock_items' => $outOfStockItems
        ],
        'products' => $products,
        'stock_movements' => $stockMovements,
        'category_stats' => $categoryStats
    ];
}

function getDateCondition($reportType, $startDate = null, $endDate = null) {
    switch ($reportType) {
        case 'daily':
            return date('Y-m-d 00:00:00');
        case 'weekly':
            return date('Y-m-d 00:00:00', strtotime('-7 days'));
        case 'monthly':
            return date('Y-m-d 00:00:00', strtotime('-30 days'));
        case 'custom':
            return $startDate ?: date('Y-m-d 00:00:00', strtotime('-7 days'));
        default:
            return date('Y-m-d 00:00:00');
    }
}

function getDateRangeText($reportType, $startDate = null, $endDate = null) {
    switch ($reportType) {
        case 'daily':
            return 'Today (' . date('Y-m-d') . ')';
        case 'weekly':
            return 'Last 7 days (' . date('Y-m-d', strtotime('-7 days')) . ' to ' . date('Y-m-d') . ')';
        case 'monthly':
            return 'Last 30 days (' . date('Y-m-d', strtotime('-30 days')) . ' to ' . date('Y-m-d') . ')';
        case 'custom':
            $start = $startDate ?: date('Y-m-d', strtotime('-7 days'));
            $end = $endDate ?: date('Y-m-d');
            return "Custom range ({$start} to {$end})";
        default:
            return 'Unknown period';
    }
}

function generateReportFile($data, $reportType, $format) {
    $uploadsDir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'reports' . DIRECTORY_SEPARATOR;
    
    if (!is_dir($uploadsDir)) {
        mkdir($uploadsDir, 0755, true);
    }
    
    $timestamp = date('Y-m-d_H-i-s');
    $filename = "{$reportType}_report_{$timestamp}";
    
    switch ($format) {
        case 'csv':
            return generateCSVReport($data, $uploadsDir . $filename . '.csv');
        case 'excel':
            return generateExcelReport($data, $uploadsDir . $filename . '.xlsx');
        case 'pdf':
            return generatePDFReport($data, $uploadsDir . $filename . '.html');
        default:
            throw new Exception('Unsupported format');
    }
}

function generateCSVReport($data, $filePath) {
    $handle = fopen($filePath, 'w');
    if (!$handle) {
        throw new Exception('Cannot create CSV file');
    }
    
    // Write header
    fputcsv($handle, ['Inventory Report - ' . strtoupper($data['report_type'])]);
    fputcsv($handle, ['Generated:', $data['generated_at']]);
    fputcsv($handle, ['Period:', $data['date_range']]);
    fputcsv($handle, []); // Empty row
    
    // Write summary
    fputcsv($handle, ['SUMMARY']);
    fputcsv($handle, ['Total Products', $data['summary']['total_products']]);
    fputcsv($handle, ['Total Value', 'MWK ' . number_format($data['summary']['total_value'], 2)]);
    fputcsv($handle, ['Low Stock Items', $data['summary']['low_stock_items']]);
    fputcsv($handle, ['Promotional Items', $data['summary']['promotional_items']]);
    fputcsv($handle, ['Out of Stock Items', $data['summary']['out_of_stock_items']]);
    fputcsv($handle, []); // Empty row
    
    // Write products header
    fputcsv($handle, ['PRODUCTS']);
    fputcsv($handle, ['ID', 'Name', 'Category', 'Price (MWK)', 'Stock', 'Barcode', 'Promotion', 'Visible', 'Expiry Date', 'Created']);
    
    // Write products data
    foreach ($data['products'] as $product) {
        fputcsv($handle, [
            $product['product_id'],
            $product['name'],
            $product['category'] ?: 'Uncategorized',
            number_format($product['price'], 2),
            $product['stock_quantity'],
            $product['barcode'],
            $product['is_promotion'] ? 'Yes' : 'No',
            $product['customer_visible'] ? 'Yes' : 'No',
            $product['expiry_date'] ?: 'N/A',
            $product['created_at']
        ]);
    }
    
    // Write category stats if available
    if (!empty($data['category_stats'])) {
        fputcsv($handle, []); // Empty row
        fputcsv($handle, ['CATEGORY STATISTICS']);
        fputcsv($handle, ['Category', 'Product Count', 'Total Stock', 'Total Value (MWK)', 'Low Stock Count']);
        
        foreach ($data['category_stats'] as $category => $stats) {
            fputcsv($handle, [
                $category,
                $stats['count'],
                $stats['total_stock'],
                number_format($stats['total_value'], 2),
                $stats['low_stock_count']
            ]);
        }
    }
    
    fclose($handle);
    return $filePath;
}

function generateExcelReport($data, $filePath) {
    // For simplicity, generate CSV with .xlsx extension
    // In production, use a proper Excel library like PhpSpreadsheet
    return generateCSVReport($data, str_replace('.xlsx', '.csv', $filePath));
}

function generatePDFReport($data, $filePath) {
    // Generate HTML content for PDF
    $html = generateReportHTML($data);
    file_put_contents($filePath, $html);
    return $filePath;
}

function generateReportHTML($data) {
    $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Inventory Report - ' . ucfirst($data['report_type']) . '</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            margin: 20px; 
            line-height: 1.6;
            color: #333;
        }
        .header { 
            text-align: center; 
            margin-bottom: 30px; 
            border-bottom: 3px solid #4a6baf; 
            padding-bottom: 15px; 
        }
        .header h1 {
            color: #4a6baf;
            margin-bottom: 10px;
        }
        .summary { 
            background: #f8fafc; 
            padding: 20px; 
            border-radius: 8px; 
            margin-bottom: 30px;
            border-left: 4px solid #4a6baf;
        }
        .summary-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); 
            gap: 20px; 
        }
        .summary-item { 
            text-align: center; 
            background: white;
            padding: 15px;
            border-radius: 6px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .summary-value { 
            font-size: 2rem; 
            font-weight: bold; 
            color: #4a6baf; 
        }
        .summary-label { 
            color: #666; 
            margin-top: 5px; 
            font-size: 0.9rem;
        }
        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-bottom: 30px; 
            background: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        th, td { 
            padding: 12px; 
            text-align: left; 
            border-bottom: 1px solid #e2e8f0; 
        }
        th { 
            background-color: #4a6baf; 
            color: white; 
            font-weight: 600;
        }
        tr:nth-child(even) { 
            background-color: #f8fafc; 
        }
        tr:hover {
            background-color: #e2e8f0;
        }
        .section-title { 
            font-size: 1.5rem; 
            font-weight: bold; 
            margin: 30px 0 15px 0; 
            color: #4a6baf; 
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 5px;
        }
        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: bold;
        }
        .status-promotion {
            background: #fef3c7;
            color: #92400e;
        }
        .status-low-stock {
            background: #fee2e2;
            color: #991b1b;
        }
        .status-visible {
            background: #d1fae5;
            color: #065f46;
        }
        .footer {
            margin-top: 40px;
            text-align: center;
            font-size: 0.9rem;
            color: #666;
            border-top: 1px solid #e2e8f0;
            padding-top: 20px;
        }
        @media print { 
            body { margin: 0; }
            .summary-grid { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Inventory Report - ' . ucfirst($data['report_type']) . '</h1>
        <p><strong>Generated on:</strong> ' . $data['generated_at'] . '</p>
        <p><strong>Period:</strong> ' . $data['date_range'] . '</p>
    </div>
    
    <div class="summary">
        <h2>Summary Statistics</h2>
        <div class="summary-grid">
            <div class="summary-item">
                <div class="summary-value">' . $data['summary']['total_products'] . '</div>
                <div class="summary-label">Total Products</div>
            </div>
            <div class="summary-item">
                <div class="summary-value">MWK ' . number_format($data['summary']['total_value'], 2) . '</div>
                <div class="summary-label">Total Value</div>
            </div>
            <div class="summary-item">
                <div class="summary-value">' . $data['summary']['low_stock_items'] . '</div>
                <div class="summary-label">Low Stock Items</div>
            </div>
            <div class="summary-item">
                <div class="summary-value">' . $data['summary']['promotional_items'] . '</div>
                <div class="summary-label">Promotional Items</div>
            </div>
            <div class="summary-item">
                <div class="summary-value">' . $data['summary']['out_of_stock_items'] . '</div>
                <div class="summary-label">Out of Stock</div>
            </div>
        </div>
    </div>';
    
    if (!empty($data['products'])) {
        $html .= '<div class="section-title">Product Details</div>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Category</th>
                    <th>Price (MWK)</th>
                    <th>Stock</th>
                    <th>Barcode</th>
                    <th>Status</th>
                    <th>Created</th>
                </tr>
            </thead>
            <tbody>';
        
        foreach ($data['products'] as $product) {
            $status = [];
            if ($product['is_promotion']) $status[] = '<span class="status-badge status-promotion">Promotion</span>';
            if ($product['stock_quantity'] <= $product['low_stock_threshold']) $status[] = '<span class="status-badge status-low-stock">Low Stock</span>';
            if ($product['customer_visible']) $status[] = '<span class="status-badge status-visible">Visible</span>';
            
            $html .= '<tr>
                <td>' . htmlspecialchars($product['product_id']) . '</td>
                <td>' . htmlspecialchars($product['name']) . '</td>
                <td>' . htmlspecialchars($product['category'] ?: 'Uncategorized') . '</td>
                <td>' . number_format($product['price'], 2) . '</td>
                <td>' . $product['stock_quantity'] . '</td>
                <td>' . htmlspecialchars($product['barcode']) . '</td>
                <td>' . implode(' ', $status) . '</td>
                <td>' . date('Y-m-d', strtotime($product['created_at'])) . '</td>
            </tr>';
        }
        
        $html .= '</tbody></table>';
    }
    
    if (!empty($data['category_stats'])) {
        $html .= '<div class="section-title">Category Statistics</div>
        <table>
            <thead>
                <tr>
                    <th>Category</th>
                    <th>Product Count</th>
                    <th>Total Stock</th>
                    <th>Total Value (MWK)</th>
                    <th>Low Stock Count</th>
                </tr>
            </thead>
            <tbody>';
        
        foreach ($data['category_stats'] as $category => $stats) {
            $html .= '<tr>
                <td>' . htmlspecialchars($category) . '</td>
                <td>' . $stats['count'] . '</td>
                <td>' . $stats['total_stock'] . '</td>
                <td>' . number_format($stats['total_value'], 2) . '</td>
                <td>' . $stats['low_stock_count'] . '</td>
            </tr>';
        }
        
        $html .= '</tbody></table>';
    }
    
    $html .= '
    <div class="footer">
        <p><strong>Auntie Eddah POS System - Inventory Management</strong></p>
        <p>Report generated on ' . date('Y-m-d H:i:s') . ' | Total Products: ' . count($data['products']) . '</p>
    </div>
</body>
</html>';

    return $html;
}

function getContentType($format) {
    $types = [
        'pdf' => 'text/html', // We're generating HTML for PDF
        'csv' => 'text/csv',
        'excel' => 'text/csv' // Simplified as CSV
    ];
    
    return $types[$format] ?? 'application/octet-stream';
}
?>

