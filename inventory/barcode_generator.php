<?php
require __DIR__ . '/vendor/autoload.php';

use Picqer\Barcode\BarcodeGeneratorPNG;
use TCPDF;

header('Content-Type: application/json');

$action = $_POST['action'] ?? 'generate';
$quantity = max(1, intval($_POST['quantity'] ?? 1));
$format   = strtolower($_POST['format'] ?? 'pdf');

try {
    $generator = new BarcodeGeneratorPNG();
    $barcodes = [];

    // Generate random barcodes
    for ($i = 0; $i < $quantity; $i++) {
        $randomCode = str_pad((string)random_int(10000000, 99999999), 12, '0', STR_PAD_LEFT); // 12-digit code
        $barcodePng = $generator->getBarcode($randomCode, $generator::TYPE_CODE_128, 2, 50);

        $barcodes[] = [
            'barcode' => $randomCode,
            'image'   => 'data:image/png;base64,' . base64_encode($barcodePng)
        ];
    }

    if ($action === 'preview') {
        echo json_encode([
            'success' => true,
            'message' => 'Preview generated successfully',
            'data'    => ['previews' => $barcodes]
        ]);
        exit;
    }

    // Otherwise: generate a file for download
    $filename = 'barcodes_' . time() . '.' . $format;
    $filepath = __DIR__ . '/generated/' . $filename;
    if (!is_dir(__DIR__ . '/generated')) {
        mkdir(__DIR__ . '/generated', 0777, true);
    }

    if ($format === 'pdf') {
        $pdf = new TCPDF();
        $pdf->AddPage();
        foreach ($barcodes as $b) {
            $pdf->Image('@' . base64_decode(str_replace('data:image/png;base64,', '', $b['image'])), '', '', 60, 20);
            $pdf->Ln(25);
            $pdf->Cell(0, 10, $b['barcode'], 0, 1, 'C');
        }
        $pdf->Output($filepath, 'F');
    } else {
        // Zip all PNGs
        $zip = new ZipArchive();
        $zip->open($filepath, ZipArchive::CREATE);
        foreach ($barcodes as $idx => $b) {
            $zip->addFromString("barcode_$idx.png", base64_decode(str_replace('data:image/png;base64,', '', $b['image'])));
        }
        $zip->close();
    }

    echo json_encode([
        'success' => true,
        'message' => 'Barcodes generated successfully',
        'data'    => [
            'quantity'     => $quantity,
            'format'       => $format,
            'filename'     => $filename,
            'file_size'    => filesize($filepath) . ' bytes',
            'download_url' => 'download_barcode.php?file=' . urlencode($filename)
        ]
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
