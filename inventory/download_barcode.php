<?php
session_start();
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/functions.php';

// For demo purposes, skip authentication
// Check authentication
// if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'inventory_manager') {
//     http_response_code(403);
//     die('Unauthorized access. Please log in as an inventory manager.');
// }

// Get the file parameter
$file = sanitizeInput($_GET['file'] ?? '');

if (empty($file)) {
    http_response_code(400);
    die('No file specified.');
}

// Construct the full file path
$uploadsDir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'barcodes' . DIRECTORY_SEPARATOR;
$filePath = $uploadsDir . basename($file); // Use basename to prevent directory traversal

// Check if file exists and is within the allowed directory
if (!file_exists($filePath) || !is_file($filePath)) {
    http_response_code(404);
    die('File not found: ' . htmlspecialchars($file));
}

// Verify the file is in the correct directory (security check)
$realPath = realpath($filePath);
$realUploadsDir = realpath($uploadsDir);

if (!$realPath || !$realUploadsDir || strpos($realPath, $realUploadsDir) !== 0) {
    http_response_code(403);
    die('Access denied.');
}

// Get file info
$fileSize = filesize($filePath);
$fileName = basename($filePath);
$fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

// Set appropriate content type based on file extension
$contentTypes = [
    'pdf' => 'application/pdf',
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'xls' => 'application/vnd.ms-excel',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'doc' => 'application/msword',
    'csv' => 'text/csv',
    'html' => 'text/html',
    'htm' => 'text/html',
    'png' => 'image/png',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg'
];

$contentType = $contentTypes[$fileExtension] ?? 'application/octet-stream';

// Log the download (skip if no session)
if (isset($_SESSION['user_id'])) {
    logUserAction($_SESSION['user_id'], "Downloaded barcode file: {$fileName}", 'file_download');
}

// Set headers for file download
header('Content-Type: ' . $contentType);
header('Content-Disposition: attachment; filename="' . $fileName . '"');
header('Content-Length: ' . $fileSize);
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Prevent any output before file content
if (ob_get_level()) {
    ob_end_clean();
}

// Output the file
readfile($filePath);

// Optional: Delete the file after download (uncomment if you want temporary files)
// unlink($filePath);

exit;
?>

