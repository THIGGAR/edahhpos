<?php
session_start();
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/functions.php';

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
} else {
    logError('Composer autoloader not found');
    sendError('Server configuration error: Missing dependencies', 500);
}

use Picqer\Barcode\BarcodeGeneratorPNG;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory as WordIOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\IOFactory as ExcelIOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

// Include TCPDF for PDF generation
require_once('vendor/tecnickcom/tcpdf/tcpdf.php');

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

function sendResponse($success, $message, $data = null, $httpCode = 200) {
    http_response_code($httpCode);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('c')
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function sendError($message, $httpCode = 400, $details = null) {
    logError("Barcode Generator Error: $message", $details ? ['details' => $details] : []);
    sendResponse(false, $message, $details, $httpCode);
}

try {
    // For demo purposes, we'll skip authentication
    // if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'inventory_manager') {
    //     sendError('Unauthorized access', 403);
    // }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendError('Only POST requests are allowed', 405);
    }

    $action = sanitizeInput($_POST['action'] ?? '');
    if (!in_array($action, ['generate', 'preview'])) {
        sendError('Invalid action specified', 400);
    }

    if ($action === 'generate') {
        handleBarcodeGeneration();
    } else {
        handleBarcodePreview();
    }
} catch (Exception $e) {
    sendError('Server error: ' . $e->getMessage(), 500, [
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}

function generateUniqueBarcode($prefix) {
    $maxAttempts = 10; // Prevent infinite loops
    for ($i = 0; $i < $maxAttempts; $i++) {
        $code = $prefix . str_pad(mt_rand(0, 9999999999), 10, '0', STR_PAD_LEFT);
        // Assuming checkBarcodeExists is defined in functions.php and interacts with your database
        if (!checkBarcodeExists($code)) {
            return $code;
        }
    }
    throw new Exception("Could not generate a unique barcode after $maxAttempts attempts.");
}

function generateBarcodes($quantity, $prefix, $labelText) {
    $barcodes = [];
    for ($i = 0; $i < $quantity; $i++) {
        try {
            $code = generateUniqueBarcode($prefix);
            $barcodes[] = [
                'barcode' => $code,
                'label' => $labelText ?: 'Product Barcode'
            ];
        } catch (Exception $e) {
            logError("Failed to generate unique barcode: " . $e->getMessage());
            // Optionally, you can stop generation or add a placeholder/error barcode
            sendError("Failed to generate unique barcode for all items. " . $e->getMessage(), 500);
        }
    }
    return $barcodes;
}

function generateBarcodeImageData($code) {
    $generator = new BarcodeGeneratorPNG();
    $barcodeData = $generator->getBarcode($code, $generator::TYPE_CODE_128);
    return 'data:image/png;base64,' . base64_encode($barcodeData);
}

function generateBarcodeImageFile($code, $path) {
    $generator = new BarcodeGeneratorPNG();
    $barcodeData = $generator->getBarcode($code, $generator::TYPE_CODE_128);
    file_put_contents($path, $barcodeData);
    return $path;
}

function generatePDFBarcodes($barcodes, $labelText) {
    $uploadDir = __DIR__ . '/uploads/barcodes/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor('Barcode Generator');
    $pdf->SetTitle('Generated Barcodes');
    $pdf->SetSubject('Barcodes');
    $pdf->SetKeywords('TCPDF, PDF, barcodes, products');

    $pdf->SetPrintHeader(false);
    $pdf->SetPrintFooter(false);
    $pdf->AddPage();
    $pdf->SetFont('helvetica', '', 12);

    $generator = new BarcodeGeneratorPNG();
    $html = '<h1 style="text-align: center;">Generated Barcodes</h1>';
    $html .= '<p style="text-align: center;">Generated on: ' . date('Y-m-d H:i:s') . '</p><br>';
    
    $html .= '<table border="1" cellpadding="10" style="width: 100%;">';
    $html .= '<tr><th style="background-color: #f0f0f0;">Barcode Image</th><th style="background-color: #f0f0f0;">Barcode Number</th><th style="background-color: #f0f0f0;">Label</th></tr>';
    
    foreach ($barcodes as $index => $barcode) {
        $barcodeData = $generator->getBarcode($barcode['barcode'], $generator::TYPE_CODE_128);
        $imgPath = $uploadDir . 'temp_barcode_' . $index . '.png';
        file_put_contents($imgPath, $barcodeData);
        
        $html .= '<tr>';
        $html .= '<td style="text-align: center;"><img src="' . $imgPath . '" width="150" height="50"></td>';
        $html .= '<td style="text-align: center; font-family: monospace; font-size: 14px;">' . $barcode['barcode'] . '</td>';
        $html .= '<td style="text-align: center;">' . htmlspecialchars($barcode['label']) . '</td>';
        $html .= '</tr>';
        
        // Add page break every 10 barcodes
        if (($index + 1) % 10 === 0 && $index < count($barcodes) - 1) {
            $html .= '</table>';
            $pdf->writeHTML($html, true, false, true, false, '');
            $pdf->AddPage();
            $html = '<table border="1" cellpadding="10" style="width: 100%;">';
            $html .= '<tr><th style="background-color: #f0f0f0;">Barcode Image</th><th style="background-color: #f0f0f0;">Barcode Number</th><th style="background-color: #f0f0f0;">Label</th></tr>';
        }
    }
    $html .= '</table>';
    $pdf->writeHTML($html, true, false, true, false, '');

    $filePath = $uploadDir . 'barcodes_' . time() . '.pdf';
    $pdf->Output($filePath, 'F');
    
    // Clean up temporary images
    foreach ($barcodes as $index => $barcode) {
        $tempImg = $uploadDir . 'temp_barcode_' . $index . '.png';
        if (file_exists($tempImg)) {
            unlink($tempImg);
        }
    }
    
    return $filePath;
}

function generateWordBarcodes($barcodes, $labelText) {
    $uploadDir = __DIR__ . '/uploads/barcodes/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $phpWord = new PhpWord();
    $section = $phpWord->addSection();
    
    // Add title
    $section->addTitle('Generated Barcodes', 1);
    $section->addText('Generated on: ' . date('Y-m-d H:i:s'));
    $section->addTextBreak(2);
    
    // Create table
    $table = $section->addTable([
        'borderSize' => 6,
        'borderColor' => '000000',
        'cellMargin' => 80,
        'width' => 100 * 50
    ]);
    
    // Add header row
    $table->addRow();
    $table->addCell(3000)->addText('Barcode Image', ['bold' => true], ['alignment' => 'center']);
    $table->addCell(3000)->addText('Barcode Number', ['bold' => true], ['alignment' => 'center']);
    $table->addCell(3000)->addText('Label', ['bold' => true], ['alignment' => 'center']);
    
    $generator = new BarcodeGeneratorPNG();
    
    foreach ($barcodes as $index => $barcode) {
        $barcodeData = $generator->getBarcode($barcode['barcode'], $generator::TYPE_CODE_128);
        $imgPath = $uploadDir . 'barcode_' . $index . '.png';
        file_put_contents($imgPath, $barcodeData);
        
        $table->addRow();
        
        // Add barcode image
        $imageCell = $table->addCell(3000);
        $imageCell->addImage($imgPath, [
            'width' => 150,
            'height' => 50,
            'alignment' => 'center'
        ]);
        
        // Add barcode number
        $table->addCell(3000)->addText($barcode['barcode'], ['name' => 'Courier New'], ['alignment' => 'center']);
        
        // Add label
        $table->addCell(3000)->addText($barcode['label'], null, ['alignment' => 'center']);
    }
    
    $filePath = $uploadDir . 'barcodes_' . time() . '.docx';
    $writer = WordIOFactory::createWriter($phpWord, 'Word2007');
    $writer->save($filePath);
    
    // Clean up temporary images
    foreach ($barcodes as $index => $barcode) {
        $tempImg = $uploadDir . 'barcode_' . $index . '.png';
        if (file_exists($tempImg)) {
            unlink($tempImg);
        }
    }
    
    return $filePath;
}

function generateExcelBarcodes($barcodes, $labelText) {
    $uploadDir = __DIR__ . '/uploads/barcodes/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $spreadsheet = new Spreadsheet();
    $worksheet = $spreadsheet->getActiveSheet();
    $worksheet->setTitle('Barcodes');
    
    // Set headers
    $worksheet->setCellValue('A1', 'Generated Barcodes');
    $worksheet->setCellValue('A2', 'Generated on: ' . date('Y-m-d H:i:s'));
    
    // Style the title
    $worksheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
    $worksheet->getStyle('A2')->getFont()->setSize(12);
    
    // Set column headers
    $worksheet->setCellValue('A4', 'Barcode Image');
    $worksheet->setCellValue('B4', 'Barcode Number');
    $worksheet->setCellValue('C4', 'Label');
    
    // Style headers
    $worksheet->getStyle('A4:C4')->getFont()->setBold(true);
    $worksheet->getStyle('A4:C4')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
        ->getStartColor()->setRGB('CCCCCC');
    
    $generator = new BarcodeGeneratorPNG();
    $row = 5;
    
    foreach ($barcodes as $index => $barcode) {
        $barcodeData = $generator->getBarcode($barcode['barcode'], $generator::TYPE_CODE_128);
        $imgPath = $uploadDir . 'barcode_' . $index . '.png';
        file_put_contents($imgPath, $barcodeData);
        
        // Add barcode image
        $drawing = new Drawing();
        $drawing->setName('Barcode ' . $index);
        $drawing->setDescription('Barcode ' . $barcode['barcode']);
        $drawing->setPath($imgPath);
        $drawing->setHeight(50);
        $drawing->setCoordinates('A' . $row);
        $drawing->setWorksheet($worksheet);
        
        // Add barcode number
        $worksheet->setCellValue('B' . $row, $barcode['barcode']);
        $worksheet->getStyle('B' . $row)->getFont()->setName('Courier New');
        
        // Add label
        $worksheet->setCellValue('C' . $row, $barcode['label']);
        
        // Set row height to accommodate image
        $worksheet->getRowDimension($row)->setRowHeight(60);
        
        $row++;
    }
    
    // Auto-size columns
    $worksheet->getColumnDimension('A')->setWidth(25);
    $worksheet->getColumnDimension('B')->setWidth(20);
    $worksheet->getColumnDimension('C')->setWidth(25);
    
    $filePath = $uploadDir . 'barcodes_' . time() . '.xlsx';
    $writer = ExcelIOFactory::createWriter($spreadsheet, 'Xlsx');
    $writer->save($filePath);
    
    // Clean up temporary images
    foreach ($barcodes as $index => $barcode) {
        $tempImg = $uploadDir . 'barcode_' . $index . '.png';
        if (file_exists($tempImg)) {
            unlink($tempImg);
        }
    }
    
    return $filePath;
}

function handleBarcodeGeneration() {
    $quantity = min(sanitizeInput($_POST['quantity'] ?? 10, 'int'), 100); // Increased limit
    $prefix = sanitizeInput($_POST['prefix'] ?? '');
    $format = sanitizeInput($_POST['format'] ?? 'pdf');
    $labelText = sanitizeInput($_POST['label'] ?? '');

    if ($quantity < 1) {
        sendError('Quantity must be at least 1', 400);
    }
    if (!in_array($format, ['pdf', 'word', 'excel'])) {
        sendError('Invalid format. Supported formats: pdf, word, excel', 400);
    }

    $barcodes = generateBarcodes($quantity, $prefix, $labelText);
    
    switch ($format) {
        case 'pdf':
            $filePath = generatePDFBarcodes($barcodes, $labelText);
            break;
        case 'word':
            $filePath = generateWordBarcodes($barcodes, $labelText);
            break;
        case 'excel':
            $filePath = generateExcelBarcodes($barcodes, $labelText);
            break;
        default:
            sendError('Unsupported format', 400);
    }

    // Log the action (skip if no session)
    if (isset($_SESSION['user_id'])) {
        logUserAction($_SESSION['user_id'], "Generated $quantity barcodes in $format format", 'barcode_generation');
    }

    sendResponse(true, 'Barcodes generated successfully', [
        'download_url' => 'download_barcode.php?file=' . urlencode(basename($filePath)),
        'quantity' => $quantity,
        'format' => $format,
        'filename' => basename($filePath)
    ]);
}

function handleBarcodePreview() {
    $quantity = min(sanitizeInput($_POST['quantity'] ?? 5, 'int'), 10);
    $prefix = sanitizeInput($_POST['prefix'] ?? '');
    $labelText = sanitizeInput($_POST['label'] ?? '');

    $barcodes = generateBarcodes($quantity, $prefix, $labelText);
    $previewData = [];
    foreach ($barcodes as $barcode) {
        $previewData[] = [
            'barcode' => $barcode['barcode'],
            'image' => generateBarcodeImageData($barcode['barcode']),
            'label' => $barcode['label']
        ];
    }

    sendResponse(true, 'Barcode preview generated', $previewData);
}
?>

