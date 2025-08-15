<?php
session_start();

// Enable error reporting for development (remove in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
set_time_limit(30); // Prevent hangs with 30-second timeout

// Include PHPMailer classes
require __DIR__ . '/PHPMailer/PHPMailer.php';
require __DIR__ . '/PHPMailer/SMTP.php';
require __DIR__ . '/PHPMailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Database connection
$host = 'localhost';
$dbname = 'aepos';
$username = 'root';
$password = '';
$conn = new mysqli($host, $username, $password, $dbname);

if ($conn->connect_error) {
    die(json_encode(['success' => false, 'error' => 'Database connection failed: ' . $conn->connect_error]));
}

// Utility functions
function sanitizeInput($data) {
    global $conn;
    return htmlspecialchars(trim($conn->real_escape_string($data)), ENT_QUOTES, 'UTF-8');
}

function redirectUnlessRole($role) {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== $role) {
        header('Location: login.php');
        exit;
    }
}

// Initialize PHPMailer for reuse
function sendQuotationEmail($toEmail, $toName, $quotationId, $items, $totalAmount, $currency) {
    $mail = new PHPMailer(true);
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'thomasnedie@gmail.com';
        $mail->Password = 'bzae ihyy acmz befm'; // Gmail App Password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        // Recipients
        $mail->setFrom('thomasnedie@gmail.com', 'Auntie Eddah POS');
        $mail->addAddress($toEmail, $toName);
        $mail->addReplyTo('thomasnedie@gmail.com', 'Auntie Eddah POS');

        // Content
        $mail->isHTML(true);
        $mail->Subject = "Request for Quotation #$quotationId";
        $mailBody = "Dear $toName,<br><br>Please provide a quotation for the following items:<br><br>";
        $mailBody .= "<strong>Quotation ID:</strong> $quotationId<br>";
        $mailBody .= "<strong>Items:</strong><br><br>";
        $mailBody .= "<ul>";
        foreach ($items as $item) {
            $mailBody .= "<li>{$item['name']} (x{$item['quantity']})</li>";
        }
        $mailBody .= "</ul><br><br>";
        $mailBody .= "Best regards,<br>Auntie Eddah POS";
        $mail->Body = $mailBody;
        $mail->AltBody = strip_tags(str_replace('<br>', "\n", $mailBody));

        $mail->send();
        return true;
    } catch (Exception $e) {
        return "Failed to send email: " . $mail->ErrorInfo;
    }
}

// Handle POST requests (add/delete/send quotation)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    // Add quotation
    if (isset($_POST['action']) && $_POST['action'] === 'add_quotation') {
        $supplier_id = filter_input(INPUT_POST, 'supplier_id', FILTER_VALIDATE_INT);
        $notes = sanitizeInput($_POST['notes'] ?? '');
        $send_email = isset($_POST['send_email']) ? (int)$_POST['send_email'] : 0;
        $product_names = $_POST['product_name'] ?? [];
        $quantities = $_POST['quantity'] ?? [];

        if (!$supplier_id || empty($product_names) || count($product_names) !== count($quantities)) {
            echo json_encode(['success' => false, 'error' => 'Missing or invalid required fields']);
            exit;
        }

        // Validate supplier
        $stmt = $conn->prepare("SELECT user_id, first_name, last_name, email FROM users WHERE user_id = ? AND role = 'supplier' AND is_active = 1");
        $stmt->bind_param('i', $supplier_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if (!$supplier = $result->fetch_assoc()) {
            echo json_encode(['success' => false, 'error' => 'Invalid supplier']);
            exit;
        }
        $stmt->close();

        // Collect items (manual entry, no price or stock check)
        $items = [];
        $total_amount = 0.00; // Set to 0 since no prices
        foreach ($product_names as $index => $product_name) {
            $product_name = sanitizeInput($product_name);
            $quantity = filter_var($quantities[$index], FILTER_VALIDATE_INT);
            if (empty($product_name) || $quantity <= 0) {
                echo json_encode(['success' => false, 'error' => 'Invalid product name or quantity']);
                exit;
            }

            $items[] = [
                'name' => $product_name,
                'quantity' => $quantity
            ];
        }

        // Start transaction
        $conn->begin_transaction();
        try {
            // Insert quotation
            $items_json = json_encode($items);
            $status = $send_email ? 'approved' : 'pending';
            $stmt = $conn->prepare("INSERT INTO quotations (supplier_id, items, total_amount, status, created_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt->bind_param('isds', $supplier_id, $items_json, $total_amount, $status);
            $stmt->execute();
            $quotation_id = $conn->insert_id;
            $stmt->close();

            // Log the action
            $action = "Created quotation #$quotation_id for supplier ID $supplier_id";
            $action_type = 'quotation_add';
            $ip_address = $_SERVER['REMOTE_ADDR'];
            $user_agent = $_SERVER['HTTP_USER_AGENT'];
            $log_stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, action_type, ip_address, user_agent, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $log_stmt->bind_param('issss', $_SESSION['user_id'], $action, $action_type, $ip_address, $user_agent);
            $log_stmt->execute();
            $log_stmt->close();

            // Send email if requested
            if ($send_email) {
                $toName = $supplier['first_name'] . ' ' . $supplier['last_name'];
                $result = sendQuotationEmail($supplier['email'], $toName, $quotation_id, $items, $total_amount, 'MWK');
                if ($result !== true) {
                    throw new Exception($result);
                }
            }

            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Quotation created' . ($send_email ? ' and sent' : '') . ' successfully']);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'error' => 'Failed to create quotation: ' . $e->getMessage()]);
        }
        $conn->close();
        exit;
    }

    // Delete quotation
    if (isset($_POST['action']) && $_POST['action'] === 'delete_quotation') {
        $quotation_id = filter_input(INPUT_POST, 'quotation_id', FILTER_VALIDATE_INT);
        if (!$quotation_id) {
            echo json_encode(['success' => false, 'error' => 'Invalid quotation ID']);
            exit;
        }

        try {
            $stmt = $conn->prepare("DELETE FROM quotations WHERE quotation_id = ?");
            $stmt->bind_param('i', $quotation_id);
            $stmt->execute();
            $affected_rows = $stmt->affected_rows;
            $stmt->close();

            if ($affected_rows > 0) {
                // Log the action
                $action = "Deleted quotation #$quotation_id";
                $action_type = 'quotation_delete';
                $ip_address = $_SERVER['REMOTE_ADDR'];
                $user_agent = $_SERVER['HTTP_USER_AGENT'];
                $log_stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, action_type, ip_address, user_agent, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                $log_stmt->bind_param('issss', $_SESSION['user_id'], $action, $action_type, $ip_address, $user_agent);
                $log_stmt->execute();
                $log_stmt->close();

                echo json_encode(['success' => true, 'message' => 'Quotation deleted successfully']);
            } else {
                echo json_encode(['success' => false, 'error' => 'Quotation not found']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => 'Failed to delete quotation: ' . $e->getMessage()]);
        }
        $conn->close();
        exit;
    }

    // Send quotation
    if (isset($_POST['action']) && $_POST['action'] === 'send_quotation') {
        $quotation_id = filter_input(INPUT_POST, 'quotation_id', FILTER_VALIDATE_INT);
        if (!$quotation_id) {
            echo json_encode(['success' => false, 'error' => 'Invalid quotation ID']);
            exit;
        }

        try {
            // Fetch quotation details for email
            $stmt = $conn->prepare("SELECT q.supplier_id, q.items, q.total_amount, q.status, u.email, u.first_name, u.last_name 
                                   FROM quotations q 
                                   JOIN users u ON q.supplier_id = u.user_id 
                                   WHERE q.quotation_id = ?");
            $stmt->bind_param('i', $quotation_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if (!$quotation = $result->fetch_assoc()) {
                echo json_encode(['success' => false, 'error' => 'Quotation not found']);
                exit;
            }
            $stmt->close();

            // Check if already approved
            if ($quotation['status'] === 'approved') {
                echo json_encode(['success' => false, 'error' => 'Quotation already approved']);
                exit;
            }

            // Start transaction
            $conn->begin_transaction();

            // Update quotation status to approved
            $stmt = $conn->prepare("UPDATE quotations SET status = 'approved' WHERE quotation_id = ? AND (status IN ('pending') OR status IS NULL)");
            $stmt->bind_param('i', $quotation_id);
            $stmt->execute();
            $affected_rows = $stmt->affected_rows;
            $stmt->close();

            if ($affected_rows > 0) {
                // Send email to supplier
                $items = json_decode($quotation['items'], true);
                $toName = $quotation['first_name'] . ' ' . $quotation['last_name'];
                $result = sendQuotationEmail($quotation['email'], $toName, $quotation_id, $items, $quotation['total_amount'], 'MWK');
                if ($result !== true) {
                    $conn->rollback();
                    throw new Exception($result);
                }

                // Log the action
                $action = "Sent quotation #$quotation_id";
                $action_type = 'quotation_send';
                $ip_address = $_SERVER['REMOTE_ADDR'];
                $user_agent = $_SERVER['HTTP_USER_AGENT'];
                $log_stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, action_type, ip_address, user_agent, created_at) 
                                          VALUES (?, ?, ?, ?, ?, NOW())");
                $log_stmt->bind_param('issss', $_SESSION['user_id'], $action, $action_type, $ip_address, $user_agent);
                $log_stmt->execute();
                $log_stmt->close();

                $conn->commit();
                echo json_encode(['success' => true, 'message' => 'Quotation sent successfully']);
            } else {
                $conn->rollback();
                echo json_encode(['success' => false, 'error' => 'Quotation not found or status not eligible for sending']);
            }
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'error' => 'Failed to send quotation: ' . $e->getMessage()]);
        }
        $conn->close();
        exit;
    }

    // Update theme
    if (isset($_POST['theme'])) {
        $theme = sanitizeInput($_POST['theme']);
        $user_id = $_SESSION['user_id'];
        $stmt = $conn->prepare("INSERT INTO settings (user_id, theme) VALUES (?, ?) ON DUPLICATE KEY UPDATE theme = ?");
        $stmt->bind_param('iss', $user_id, $theme, $theme);
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to update theme']);
        }
        $stmt->close();
        $conn->close();
        exit;
    }
}

// Redirect if user is not logged in or not a shop manager
redirectUnlessRole('shop_manager');

// Fetch user details
$user_id = $_SESSION['user_id'];
$user_query = "SELECT first_name, last_name FROM users WHERE user_id = ?";
$stmt = $conn->prepare($user_query);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$user_result = $stmt->get_result();
$user = $user_result->fetch_assoc();
$full_name = $user['first_name'] . ' ' . $user['last_name'];
$stmt->close();

// Load user settings
$settings_query = "SELECT theme, currency FROM settings WHERE user_id = ?";
$stmt = $conn->prepare($settings_query);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$settings_result = $stmt->get_result();
$settings = $settings_result->fetch_assoc();
$theme = $settings['theme'] ?? 'light';
$currency = $settings['currency'] ?? 'MWK';
$stmt->close();

// Fetch suppliers
$suppliers_query = "SELECT user_id, first_name, last_name, email, phone FROM users WHERE role = 'supplier' AND is_active = 1 ORDER BY first_name ASC";
$stmt = $conn->prepare($suppliers_query);
$stmt->execute();
$suppliers_result = $stmt->get_result();
$suppliers = $suppliers_result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch recent quotations
$recent_quotations_query = "SELECT q.quotation_id, q.total_amount, q.status, q.created_at, 
                            CONCAT(u.first_name, ' ', u.last_name) AS supplier_name,
                            u.email AS supplier_email, u.phone AS supplier_phone,
                            JSON_LENGTH(q.items) AS items_count
                            FROM quotations q 
                            JOIN users u ON q.supplier_id = u.user_id 
                            ORDER BY q.created_at DESC 
                            LIMIT 10";
$stmt = $conn->prepare($recent_quotations_query);
$stmt->execute();
$recent_quotations_result = $stmt->get_result();
$recent_quotations = $recent_quotations_result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Send Quotation to Supplier - Auntie Eddah POS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        /* Base Styles */
        :root {
            --primary-color: #4e73df;
            --primary-dark: #3a5bcc;
            --secondary-color: #858796;
            --success-color: #1cc88a;
            --info-color: #36b9cc;
            --warning-color: #f6c23e;
            --danger-color: #e74a3b;
            --light-color: #f8f9fc;
            --dark-color: #5a5c69;
            --sidebar-bg: #1a3a8f;
            --sidebar-text: rgba(255, 255, 255, 0.9);
            --sidebar-hover: rgba(255, 255, 255, 0.15);
            --sidebar-active: rgba(255, 255, 255, 0.25);
            --body-bg: #f5f7fb;
            --card-bg: #ffffff;
            --text-color: #333;
            --header-bg: #ffffff;
            --border-color: #e3e6f0;
            --footer-bg: #ffffff;
        }

        /* Dark Mode Variables */
        .dark-mode {
            --body-bg: #1a1a2e;
            --card-bg: #16213e;
            --text-color: #e6e6e6;
            --header-bg: #16213e;
            --sidebar-bg: #0f3460;
            --sidebar-text: rgba(255, 255, 255, 0.95);
            --sidebar-hover: rgba(255, 255, 255, 0.2);
            --sidebar-active: rgba(255, 255, 255, 0.3);
            --secondary-color: #a0a4b8;
            --dark-color: #e6e6e6;
            --border-color: #2d3748;
            --footer-bg: #16213e;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            transition: background-color 0.3s, color 0.3s, border-color 0.3s;
        }

        body {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            background-color: var(--body-bg);
            color: var(--text-color);
            line-height: 1.6;
            <?php if ($theme === 'dark') echo 'class="dark-mode"'; ?>
        }

        /* Header Styles */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 2rem;
            background-color: var(--header-bg);
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 100;
            height: 70px;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        .mobile-menu-toggle {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--dark-color);
            display: none;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 600;
            font-size: 1.2rem;
            color: var(--dark-color);
        }

        .logo i {
            color: var(--primary-color);
        }

        .user-info {
            font-size: 1rem;
            color: var(--dark-color);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 0.35rem;
            background-color: rgba(78, 115, 223, 0.05);
        }

        .theme-toggle {
            background: none;
            border: none;
            font-size: 1rem;
            cursor: pointer;
            color: var(--dark-color);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 0.35rem;
            transition: all 0.3s;
        }

        .theme-toggle:hover {
            background-color: rgba(78, 115, 223, 0.1);
        }

        /* Sidebar Styles */
        .sidebar {
            width: 250px;
            background-color: var(--sidebar-bg);
            position: fixed;
            top: 70px;
            bottom: 0;
            left: 0;
            transition: all 0.3s;
            z-index: 99;
            overflow-y: auto;
        }

        .sidebar-nav {
            display: flex;
            flex-direction: column;
            padding: 1rem 0;
        }

        .nav-link {
            display: flex;
            align-items: center;
            padding: 0.75rem 1.5rem;
            color: var(--sidebar-text);
            text-decoration: none;
            transition: all 0.3s;
            gap: 0.75rem;
            font-size: 0.95rem;
        }

        .nav-link:hover {
            color: white;
            background-color: var(--sidebar-hover);
        }

        .nav-link.active {
            color: white;
            background-color: var(--sidebar-active);
            font-weight: 600;
        }

        .nav-link i {
            width: 20px;
            text-align: center;
            font-size: 1.1rem;
        }

        /* Main Content Styles */
        .main-content {
            margin-left: 250px;
            margin-top: 70px;
            padding: 2rem;
            flex: 1;
            transition: all 0.3s;
        }

        .content-section {
            display: none;
        }

        .content-section.active {
            display: block;
            animation: fadeIn 0.5s ease-in-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .section-header h1 {
            font-size: 1.75rem;
            color: var(--dark-color);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        /* Button Styles */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 0.35rem;
            border: none;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
            font-size: 0.875rem;
            position: relative;
        }

        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background-color: var(--primary-dark);
        }

        .btn-primary.loading::after {
            content: '';
            display: inline-block;
            width: 1rem;
            height: 1rem;
            border: 2px solid white;
            border-radius: 50%;
            border-top-color: transparent;
            animation: spin 1s linear infinite;
            margin-left: 0.5rem;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 1rem;
        }

        .form-control {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid var(--border-color);
            border-radius: 0.35rem;
            background-color: var(--card-bg);
            color: var(--text-color);
        }

        .form-control:focus {
            border-color: var(--primary-color);
            outline: none;
        }

        .quotation-item {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .quotation-item input {
            flex: 1;
        }

        .product-name {
            min-width: 200px;
        }

        .quantity-input {
            min-width: 100px;
        }

        .error-message {
            color: var(--danger-color);
            font-size: 0.875rem;
            display: none;
            margin-top: 0.25rem;
        }

        /* Table Styles */
        .table-wrapper {
            overflow-x: auto;
            background-color: var(--card-bg);
            border-radius: 0.35rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 800px;
        }

        th, td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }

        th {
            background-color: var(--light-color);
            font-weight: 600;
            color: var(--dark-color);
        }

        .dark-mode th {
            background-color: #2d3748;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }

        .modal-content {
            background-color: var(--card-bg);
            padding: 2rem;
            border-radius: 0.35rem;
            max-width: 600px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
        }

        .modal-close {
            background-color: var(--danger-color);
            color: white;
        }

        .items-table {
            width: 100%;
            margin-bottom: 1rem;
        }

        .items-table th, .items-table td {
            padding: 0.5rem;
            border: 1px solid var(--border-color);
        }

        /* Toast Styles */
        #toastContainer {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1050;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .toast {
            background-color: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 0.35rem;
            padding: 1rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            animation: slideIn 0.3s ease-out;
        }

        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        .toast.success { border-left: 4px solid var(--success-color); }
        .toast.error { border-left: 4px solid var(--danger-color); }
        .toast.info { border-left: 4px solid var(--info-color); }

        .toast-icon i {
            font-size: 1.2rem;
        }

        .toast-close {
            background: none;
            border: none;
            cursor: pointer;
            color: var(--secondary-color);
            margin-left: auto;
        }

        /* Overlay for mobile menu */
        .overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 98;
            display: none;
        }

        /* Responsive Styles */
        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.active {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
            }

            .mobile-menu-toggle {
                display: block;
            }

            .overlay.active {
                display: block;
            }
        }

        @media (max-width: 768px) {
            .quotation-item {
                flex-direction: column;
            }
        }

        .status-badge {
            padding: 5px 10px;
            border-radius: 5px;
            color: white;
            font-size: 0.9rem;
        }

        .status-badge.pending {
            background-color: var(--warning-color);
        }

        .status-badge.approved {
            background-color: var(--info-color);
        }
    </style>
</head>
<body>
    <!-- Mobile Overlay -->
    <div class="overlay" id="overlay"></div>

    <!-- Header -->
    <header class="header">
        <div class="header-left">
            <button class="mobile-menu-toggle" id="mobileMenuToggle" aria-label="Toggle Menu">
                <i class="fas fa-bars"></i>
            </button>
            <div class="logo">
                <i class="fas fa-store"></i>
                <span>Shop Manager Panel</span>
            </div>
        </div>
        <div class="header-right">
            <div class="user-info">
                <i class="fas fa-user"></i>
                <span><?php echo htmlspecialchars($full_name); ?></span>
            </div>
            <button class="theme-toggle" id="themeToggle">
                <i class="fas <?php echo $theme === 'dark' ? 'fa-sun' : 'fa-moon'; ?>"></i>
                <span><?php echo $theme === 'dark' ? 'Light Mode' : 'Dark Mode'; ?></span>
            </button>
        </div>
    </header>

    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <nav class="sidebar-nav">
            <a href="dashboard.php" class="nav-link">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
            <a href="users.php" class="nav-link">
                <i class="fas fa-users"></i> Users
            </a>
            <a href="send_quotation.php" class="nav-link active">
                <i class="fas fa-file-invoice"></i> Send Quotation
            </a>
            <a href="sales_reports.php" class="nav-link">
                <i class="fas fa-chart-line"></i> Sales Reports
            </a>
            <a href="inventory_reports.php" class="nav-link">
                <i class="fas fa-boxes"></i> Inventory Reports
            </a>
            <a href="settings.php" class="nav-link">
                <i class="fas fa-cog"></i> Settings
            </a>
            <a href="logout.php" class="nav-link">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <section class="content-section active" id="quotations">
            <div class="section-header">
                <h1><i class="fas fa-file-invoice"></i> Send Quotation to Supplier</h1>
                <div class="section-actions">
                    <button class="btn btn-primary" id="newQuotationBtn">
                        <i class="fas fa-plus"></i>
                        <span>New Quotation</span>
                    </button>
                </div>
            </div>

            <!-- Quotation Form -->
            <div class="card">
                <div class="card-content">
                    <form id="quotationForm">
                        <input type="hidden" name="action" value="add_quotation">
                        <div class="form-group">
                            <label for="supplierSelect">Select Supplier</label>
                            <select id="supplierSelect" name="supplier_id" class="form-control" required>
                                <option value="">Select Supplier</option>
                                <?php foreach ($suppliers as $supplier): ?>
                                    <option value="<?php echo $supplier['user_id']; ?>" 
                                            data-email="<?php echo htmlspecialchars($supplier['email']); ?>"
                                            data-phone="<?php echo htmlspecialchars($supplier['phone']); ?>">
                                        <?php echo htmlspecialchars($supplier['first_name'] . ' ' . $supplier['last_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div id="supplierError" class="error-message">Please select a supplier</div>
                            <div id="supplierDetails" style="margin-top: 0.5rem; font-size: 0.875rem; color: var(--secondary-color);"></div>
                        </div>

                        <div class="form-group">
                            <label>Items</label>
                            <div id="quotationItems">
                                <div class="quotation-item">
                                    <input type="text" name="product_name[]" placeholder="Product Name" required class="product-name">
                                    <input type="number" name="quantity[]" min="1" placeholder="Quantity" required class="quantity-input">
                                    <button type="button" class="btn btn-danger remove-item"><i class="fas fa-minus"></i> Remove</button>
                                </div>
                            </div>
                            <div id="itemsError" class="error-message">Please add at least one item with valid quantity</div>
                            <button type="button" id="addQuotationItem" class="btn btn-primary" style="margin-top: 1rem;">
                                <i class="fas fa-plus"></i> Add Item
                            </button>
                        </div>

                        <div class="form-group">
                            <label for="notes">Notes</label>
                            <textarea id="notes" name="notes" class="form-control" rows="3"></textarea>
                        </div>

                        <div class="form-group">
                            <label>
                                <input type="checkbox" name="send_email" value="1" checked>
                                Send email to supplier
                            </label>
                        </div>

                        <div class="form-actions">
                            <button type="submit" id="submitQuotation" class="btn btn-primary">
                                <i class="fas fa-paper-plane"></i> Send Quotation
                            </button>
                            <button type="button" id="resetQuotationForm" class="btn btn-secondary">
                                <i class="fas fa-undo"></i> Reset Form
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Recent Quotations -->
            <div class="dashboard-widget" style="margin-top: 2rem;">
                <div class="widget-header">
                    <h3><i class="fas fa-history"></i> Recent Quotations</h3>
                </div>
                <div class="widget-content">
                    <div class="table-wrapper">
                        <table id="recentQuotationsTable">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Supplier</th>
                                    <th>Items</th>
                                    <th>Total</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recent_quotations)): ?>
                                    <tr>
                                        <td colspan="6" style="text-align: center;">No recent quotations found.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($recent_quotations as $quotation): ?>
                                        <tr>
                                            <td><?php echo date('M d, Y', strtotime($quotation['created_at'])); ?></td>
                                            <td><?php echo htmlspecialchars($quotation['supplier_name']); ?></td>
                                            <td><?php echo $quotation['items_count']; ?></td>
                                            <td><?php echo htmlspecialchars($currency . ' ' . number_format($quotation['total_amount'], 2)); ?></td>
                                            <td><span class="status-badge <?php echo htmlspecialchars($quotation['status'] ?: 'pending'); ?>"><?php echo ucfirst($quotation['status'] ?: 'Pending'); ?></span></td>
                                            <td>
                                                <button class="btn btn-sm btn-info view-quotation" data-id="<?php echo $quotation['quotation_id']; ?>">
                                                    <i class="fas fa-eye"></i> View
                                                </button>
                                                <button class="btn btn-sm btn-danger delete-quotation" data-id="<?php echo $quotation['quotation_id']; ?>" data-supplier="<?php echo htmlspecialchars($quotation['supplier_name']); ?>">
                                                    <i class="fas fa-trash"></i> Delete
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <!-- View Quotation Modal -->
    <div class="modal" id="viewQuotationModal">
        <div class="modal-content">
            <h2>Quotation Details</h2>
            <div id="quotationDetails"></div>
            <div id="quotationModalFooter" style="margin-top: 1rem; text-align: right;"></div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal confirmation-modal" id="deleteConfirmationModal">
        <div class="modal-content">
            <h2>Confirm Deletion</h2>
            <p id="deleteConfirmationMessage"></p>
            <div style="text-align: right; margin-top: 1rem;">
                <button type="button" id="cancelDelete" class="btn btn-secondary modal-close">Cancel</button>
                <button type="button" id="confirmDelete" class="btn btn-danger">Delete</button>
            </div>
        </div>
    </div>

    <!-- Toast Container -->
    <div id="toastContainer"></div>

    <script>
        function showToast(message, type = 'info') {
            const container = document.getElementById('toastContainer');
            if (!container) return;
            
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            toast.innerHTML = `
                <div class="toast-icon">
                    <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
                </div>
                <div class="toast-content">${message}</div>
                <button class="toast-close" aria-label="Close">
                    <i class="fas fa-times"></i>
                </button>
            `;
            
            container.appendChild(toast);
            
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.parentNode.removeChild(toast);
                }
            }, 5000);
            
            toast.querySelector('.toast-close').addEventListener('click', () => {
                if (toast.parentNode) {
                    toast.parentNode.removeChild(toast);
                }
            });
        }

        document.addEventListener('DOMContentLoaded', () => {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('overlay');
            const mobileMenuToggle = document.getElementById('mobileMenuToggle');
            const quotationItems = document.getElementById('quotationItems');

            // Mobile menu
            mobileMenuToggle.addEventListener('click', () => {
                sidebar.classList.toggle('active');
                overlay.classList.toggle('active');
            });

            overlay.addEventListener('click', () => {
                sidebar.classList.remove('active');
                overlay.classList.remove('active');
            });

            // Theme toggle
            const themeToggle = document.getElementById('themeToggle');
            themeToggle.addEventListener('click', () => {
                const isDark = document.body.classList.toggle('dark-mode');
                themeToggle.querySelector('i').className = isDark ? 'fas fa-sun' : 'fas fa-moon';
                themeToggle.querySelector('span').textContent = isDark ? 'Light Mode' : 'Dark Mode';
                fetch('send_quotation.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `theme=${isDark ? 'dark' : 'light'}`
                });
            });

            // Add quotation item
            document.getElementById('addQuotationItem').addEventListener('click', () => {
                const newItem = document.createElement('div');
                newItem.className = 'quotation-item';
                newItem.innerHTML = `
                    <input type="text" name="product_name[]" placeholder="Product Name" required class="product-name">
                    <input type="number" name="quantity[]" min="1" placeholder="Quantity" required class="quantity-input">
                    <button type="button" class="btn btn-danger remove-item"><i class="fas fa-minus"></i> Remove</button>
                `;
                quotationItems.appendChild(newItem);
            });

            // Remove quotation item
            quotationItems.addEventListener('click', (e) => {
                if (e.target.closest('.remove-item')) {
                    const item = e.target.closest('.quotation-item');
                    if (quotationItems.children.length > 1) {
                        item.remove();
                    }
                }
            });

            // Supplier selection
            const supplierSelect = document.getElementById('supplierSelect');
            const supplierDetails = document.getElementById('supplierDetails');
            supplierSelect.addEventListener('change', () => {
                const selectedOption = supplierSelect.options[supplierSelect.selectedIndex];
                if (selectedOption.value) {
                    const email = selectedOption.dataset.email;
                    const phone = selectedOption.dataset.phone;
                    supplierDetails.innerHTML = `Email: ${email} | Phone: ${phone}`;
                } else {
                    supplierDetails.innerHTML = '';
                }
            });

            // Reset form
            document.getElementById('resetQuotationForm').addEventListener('click', () => {
                const form = document.getElementById('quotationForm');
                form.reset();
                while (quotationItems.children.length > 1) {
                    quotationItems.removeChild(quotationItems.lastChild);
                }
                document.getElementById('itemsError').style.display = 'none';
                document.getElementById('supplierError').style.display = 'none';
                supplierDetails.innerHTML = '';
            });

            // Form submission
            document.getElementById('quotationForm').addEventListener('submit', (e) => {
                e.preventDefault();
                const form = e.target;
                const submitBtn = document.getElementById('submitQuotation');
                submitBtn.classList.add('loading');
                submitBtn.disabled = true;

                const supplierSelect = document.getElementById('supplierSelect');
                const itemsError = document.getElementById('itemsError');
                const supplierError = document.getElementById('supplierError');

                // Validate form
                let isValid = true;
                if (!supplierSelect.value) {
                    supplierError.style.display = 'block';
                    isValid = false;
                } else {
                    supplierError.style.display = 'none';
                }

                const items = quotationItems.querySelectorAll('.quotation-item');
                if (items.length === 0) {
                    itemsError.style.display = 'block';
                    isValid = false;
                } else {
                    itemsError.style.display = 'none';
                    items.forEach(item => {
                        const name = item.querySelector('.product-name');
                        const quantity = item.querySelector('.quantity-input');
                        if (!name.value.trim() || !quantity.value || quantity.value <= 0) {
                            itemsError.style.display = 'block';
                            isValid = false;
                        }
                    });
                }

                if (!isValid) {
                    submitBtn.classList.remove('loading');
                    submitBtn.disabled = false;
                    return;
                }

                const formData = new FormData(form);
                fetch('send_quotation.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    submitBtn.classList.remove('loading');
                    submitBtn.disabled = false;
                    if (data.success) {
                        showToast(data.message, 'success');
                        form.reset();
                        while (quotationItems.children.length > 1) {
                            quotationItems.removeChild(quotationItems.lastChild);
                        }
                        supplierDetails.innerHTML = '';
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showToast(data.error, 'error');
                    }
                })
                .catch(error => {
                    submitBtn.classList.remove('loading');
                    submitBtn.disabled = false;
                    showToast('An error occurred: ' + error.message, 'error');
                });
            });

            // View quotation
            document.getElementById('recentQuotationsTable').addEventListener('click', (e) => {
                const viewBtn = e.target.closest('.view-quotation');
                if (viewBtn) {
                    const quotationId = viewBtn.dataset.id;
                    fetch(`get_quotation.php?id=${quotationId}`)
                        .then(response => {
                            if (!response.ok) {
                                throw new Error('Network response was not ok: ' + response.status);
                            }
                            return response.json();
                        })
                        .then(data => {
                            if (data.success) {
                                const modal = document.getElementById('viewQuotationModal');
                                const details = document.getElementById('quotationDetails');
                                const footer = document.getElementById('quotationModalFooter');
                                let itemsHtml = `
                                    <h3>Items</h3>
                                    <table class="items-table">
                                        <thead>
                                            <tr>
                                                <th>Product</th>
                                                <th>Quantity</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                `;
                                data.quotation.items.forEach(item => {
                                    itemsHtml += `
                                        <tr>
                                            <td>${item.name}</td>
                                            <td>${item.quantity}</td>
                                        </tr>
                                    `;
                                });
                                itemsHtml += '</tbody></table>';
                                details.innerHTML = `
                                    <p><strong>ID:</strong> ${data.quotation.quotation_id}</p>
                                    <p><strong>Supplier:</strong> ${data.quotation.supplier_name}</p>
                                    <p><strong>Email:</strong> ${data.quotation.supplier_email}</p>
                                    <p><strong>Phone:</strong> ${data.quotation.supplier_phone}</p>
                                    ${itemsHtml}
                                    <p><strong>Status:</strong> ${data.quotation.status.charAt(0).toUpperCase() + data.quotation.status.slice(1)}</p>
                                    <p><strong>Created:</strong> ${new Date(data.quotation.created_at).toLocaleString()}</p>
                                `;
                                footer.innerHTML = `
                                    <button type="button" class="btn btn-secondary modal-close">Close</button>
                                `;
                                modal.style.display = 'flex';
                            } else {
                                showToast(data.error, 'error');
                            }
                        })
                        .catch(error => {
                            showToast('Failed to load quotation: ' + error.message, 'error');
                        });
                }
            });

            // Delete quotation
            let quotationToDelete = null;
            document.getElementById('recentQuotationsTable').addEventListener('click', (e) => {
                const deleteBtn = e.target.closest('.delete-quotation');
                if (deleteBtn) {
                    quotationToDelete = deleteBtn.dataset.id;
                    document.getElementById('deleteConfirmationMessage').textContent = 
                        `Are you sure you want to delete quotation #${quotationToDelete} for ${deleteBtn.dataset.supplier}?`;
                    document.getElementById('deleteConfirmationModal').style.display = 'flex';
                }
            });

            document.getElementById('cancelDelete').addEventListener('click', () => {
                document.getElementById('deleteConfirmationModal').style.display = 'none';
                quotationToDelete = null;
            });

            document.getElementById('confirmDelete').addEventListener('click', () => {
                if (quotationToDelete) {
                    const btn = document.getElementById('confirmDelete');
                    btn.classList.add('loading');
                    btn.disabled = true;
                    fetch('send_quotation.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `action=delete_quotation&quotation_id=${quotationToDelete}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        btn.classList.remove('loading');
                        btn.disabled = false;
                        document.getElementById('deleteConfirmationModal').style.display = 'none';
                        if (data.success) {
                            showToast(data.message, 'success');
                            setTimeout(() => location.reload(), 1500);
                        } else {
                            showToast(data.error, 'error');
                        }
                        quotationToDelete = null;
                    })
                    .catch(error => {
                        btn.classList.remove('loading');
                        btn.disabled = false;
                        showToast('An error occurred: ' + error.message, 'error');
                    });
                }
            });

            // Close modals using event delegation
            document.addEventListener('click', (e) => {
                const closeBtn = e.target.closest('.modal-close');
                if (closeBtn) {
                    const modal = closeBtn.closest('.modal');
                    if (modal) {
                        modal.style.display = 'none';
                    }
                }
            });

            // Close modals on overlay click
            document.querySelectorAll('.modal').forEach(modal => {
                modal.addEventListener('click', (e) => {
                    if (e.target === modal) {
                        modal.style.display = 'none';
                    }
                });
            });

            // Keyboard navigation
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    document.querySelectorAll('.modal, .confirmation-modal').forEach(modal => {
                        modal.style.display = 'none';
                    });
                }
            });

            // New Quotation button (scroll to form)
            document.getElementById('newQuotationBtn').addEventListener('click', () => {
                document.getElementById('quotationForm').scrollIntoView({ behavior: 'smooth' });
            });
        });
    </script>
</body>
</html>