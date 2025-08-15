<?php
session_start();

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

// Handle POST requests (add, edit, delete, theme)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    // Add user
    if (isset($_POST['action']) && $_POST['action'] === 'add_user') {
        $first_name = sanitizeInput($_POST['first_name'] ?? '');
        $last_name = sanitizeInput($_POST['last_name'] ?? '');
        $email = sanitizeInput($_POST['email'] ?? '');
        $phone = sanitizeInput($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? '';
        $is_active = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;

        if (!$first_name || !$last_name || !$email || !filter_var($email, FILTER_VALIDATE_EMAIL) || !$password || strlen($password) < 6 || !$role) {
            echo json_encode(['success' => false, 'error' => 'Missing or invalid required fields']);
            exit;
        }

        if (!in_array($role, ['customer', 'shop_manager', 'cashier', 'inventory_manager', 'supplier'])) {
            echo json_encode(['success' => false, 'error' => 'Invalid role']);
            exit;
        }

        $email_check = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
        $email_check->bind_param('s', $email);
        $email_check->execute();
        if ($email_check->get_result()->num_rows > 0) {
            echo json_encode(['success' => false, 'error' => 'Email already exists']);
            $email_check->close();
            exit;
        }
        $email_check->close();

        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (first_name, last_name, email, phone, password, role, is_active, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param('ssssssi', $first_name, $last_name, $email, $phone, $hashed_password, $role, $is_active);

        if ($stmt->execute()) {
            $new_user_id = $stmt->insert_id;
            $stmt->close();

            // Log the action
            $action = "Added new user: $first_name $last_name ($email)";
            $action_type = 'user_add';
            $ip_address = $_SERVER['REMOTE_ADDR'];
            $user_agent = $_SERVER['HTTP_USER_AGENT'];
            $current_user_id = $_SESSION['user_id'];
            $log_stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, action_type, ip_address, user_agent, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $log_stmt->bind_param('issss', $current_user_id, $action, $action_type, $ip_address, $user_agent);
            $log_stmt->execute();
            $log_stmt->close();

            // Insert default settings
            $settings_stmt = $conn->prepare("INSERT INTO settings (user_id, theme, timezone, currency) VALUES (?, 'light', 'Africa/Blantyre', 'MWK')");
            $settings_stmt->bind_param('i', $new_user_id);
            $settings_stmt->execute();
            $settings_stmt->close();

            echo json_encode(['success' => true, 'message' => 'User added successfully']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to add user: ' . $stmt->error]);
        }
        $stmt->close();
        $conn->close();
        exit;
    }

    // Edit user
    if (isset($_POST['action']) && $_POST['action'] === 'edit_user') {
        $user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
        $first_name = sanitizeInput($_POST['first_name'] ?? '');
        $last_name = sanitizeInput($_POST['last_name'] ?? '');
        $email = sanitizeInput($_POST['email'] ?? '');
        $phone = sanitizeInput($_POST['phone'] ?? '');
        $password = isset($_POST['password']) ? $_POST['password'] : '';
        $role = $_POST['role'] ?? '';
        $is_active = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;

        if (!$user_id || !$first_name || !$last_name || !$email || !filter_var($email, FILTER_VALIDATE_EMAIL) || !$role) {
            echo json_encode(['success' => false, 'error' => 'Missing or invalid required fields']);
            exit;
        }

        if ($user_id == $_SESSION['user_id']) {
            echo json_encode(['success' => false, 'error' => 'Cannot edit your own account']);
            exit;
        }

        if (!in_array($role, ['customer', 'shop_manager', 'cashier', 'inventory_manager', 'supplier'])) {
            echo json_encode(['success' => false, 'error' => 'Invalid role']);
            exit;
        }

        $email_check = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
        $email_check->bind_param('si', $email, $user_id);
        $email_check->execute();
        if ($email_check->get_result()->num_rows > 0) {
            echo json_encode(['success' => false, 'error' => 'Email already exists']);
            $email_check->close();
            exit;
        }
        $email_check->close();

        $query = "UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ?, role = ?, is_active = ?";
        $params = [$first_name, $last_name, $email, $phone, $role, $is_active];
        $param_types = 'sssssi';

        if ($password && strlen($password) >= 6) {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $query .= ", password = ?";
            $params[] = $hashed_password;
            $param_types .= 's';
        }

        $query .= " WHERE user_id = ?";
        $params[] = $user_id;
        $param_types .= 'i';

        $stmt = $conn->prepare($query);
        if (!$stmt) {
            echo json_encode(['success' => false, 'error' => 'Failed to prepare query: ' . $conn->error]);
            exit;
        }

        $stmt->bind_param($param_types, ...$params);
        if ($stmt->execute()) {
            // Log the action
            $action = "Updated user: $first_name $last_name ($email)";
            $action_type = 'user_edit';
            $ip_address = $_SERVER['REMOTE_ADDR'];
            $user_agent = $_SERVER['HTTP_USER_AGENT'];
            $current_user_id = $_SESSION['user_id'];
            $log_stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, action_type, ip_address, user_agent, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $log_stmt->bind_param('issss', $current_user_id, $action, $action_type, $ip_address, $user_agent);
            $log_stmt->execute();
            $log_stmt->close();

            echo json_encode(['success' => true, 'message' => 'User updated successfully']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to update user: ' . $stmt->error]);
        }
        $stmt->close();
        $conn->close();
        exit;
    }

    // Delete user
    if (isset($_POST['action']) && $_POST['action'] === 'delete_user') {
        $user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;

        if (!$user_id) {
            echo json_encode(['success' => false, 'error' => 'Invalid user ID']);
            exit;
        }

        if ($user_id == $_SESSION['user_id']) {
            echo json_encode(['success' => false, 'error' => 'Cannot delete your own account']);
            exit;
        }

        $user_query = "SELECT first_name, last_name, email FROM users WHERE user_id = ?";
        $stmt = $conn->prepare($user_query);
        if (!$stmt) {
            echo json_encode(['success' => false, 'error' => 'Failed to prepare query: ' . $conn->error]);
            exit;
        }
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $user_result = $stmt->get_result();
        $user = $user_result->fetch_assoc();
        $stmt->close();

        if (!$user) {
            echo json_encode(['success' => false, 'error' => 'User not found']);
            exit;
        }

        // Start transaction
        $conn->begin_transaction();

        try {
            // Delete from settings first (foreign key constraint)
            $delete_settings = $conn->prepare("DELETE FROM settings WHERE user_id = ?");
            $delete_settings->bind_param('i', $user_id);
            $delete_settings->execute();
            $delete_settings->close();

            // Then delete from users
            $delete_user = $conn->prepare("DELETE FROM users WHERE user_id = ?");
            $delete_user->bind_param('i', $user_id);
            $delete_user->execute();
            $delete_user->close();

            // Log the action
            $action = "Deleted user: {$user['first_name']} {$user['last_name']} ({$user['email']})";
            $action_type = 'user_delete';
            $ip_address = $_SERVER['REMOTE_ADDR'];
            $user_agent = $_SERVER['HTTP_USER_AGENT'];
            $current_user_id = $_SESSION['user_id'];
            $log_stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, action_type, ip_address, user_agent, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $log_stmt->bind_param('issss', $current_user_id, $action, $action_type, $ip_address, $user_agent);
            $log_stmt->execute();
            $log_stmt->close();

            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'User deleted successfully']);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'error' => 'Failed to delete user: ' . $e->getMessage()]);
        }
        $conn->close();
        exit;
    }

    // Update theme
    if (isset($_POST['theme'])) {
        $theme = sanitizeInput($_POST['theme']);
        $user_id = $_SESSION['user_id'];
        $stmt = $conn->prepare("UPDATE settings SET theme = ? WHERE user_id = ?");
        $stmt->bind_param('si', $theme, $user_id);
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

// Load user settings (e.g., theme)
$settings_query = "SELECT theme FROM settings WHERE user_id = ?";
$stmt = $conn->prepare($settings_query);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$settings_result = $stmt->get_result();
$settings = $settings_result->fetch_assoc();
$theme = $settings['theme'] ?? 'light';
$stmt->close();

// Handle search and role filter
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$role = isset($_GET['role']) ? sanitizeInput($_GET['role']) : '';
$where_clause = "WHERE 1=1";
$params = [];
$param_types = '';

if ($search) {
    $where_clause .= " AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= 'sss';
}

if ($role && in_array($role, ['customer', 'shop_manager', 'cashier', 'inventory_manager', 'supplier'])) {
    $where_clause .= " AND role = ?";
    $params[] = $role;
    $param_types .= 's';
}

$users_query = "SELECT user_id, first_name, last_name, email, role, phone, is_active, last_active, created_at 
                FROM users $where_clause ORDER BY created_at DESC";
$stmt = $conn->prepare($users_query);

if ($params) {
    $stmt->bind_param($param_types, ...$params);
}

$stmt->execute();
$users_result = $stmt->get_result();
$users = $users_result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Auntie Eddah POS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
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

        .btn-danger {
            background-color: var(--danger-color);
            color: white;
        }

        .btn-danger:hover {
            background-color: #c6392a;
        }

        .btn-secondary {
            background-color: var(--secondary-color);
            color: white;
        }

        .btn-secondary:hover {
            background-color: #727584;
        }

        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
        }

        .btn.loading::after {
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

        .users-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.875rem;
        }

        .users-table th,
        .users-table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .users-table th {
            background-color: rgba(78, 115, 223, 0.05);
            color: var(--dark-color);
            font-weight: 600;
        }

        .users-table td {
            color: var(--text-color);
            max-width: 150px;
        }

        .users-table tr:hover {
            background-color: rgba(78, 115, 223, 0.05);
        }

        .table-wrapper {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .form-control {
            display: block;
            width: 100%;
            padding: 0.5rem 0.75rem;
            font-size: 0.875rem;
            line-height: 1.5;
            color: var(--text-color);
            background-color: var(--card-bg);
            background-clip: padding-box;
            border: 1px solid var(--border-color);
            border-radius: 0.35rem;
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        }

        .form-control:focus {
            color: var(--text-color);
            background-color: var(--card-bg);
            border-color: var(--primary-color);
            outline: 0;
            box-shadow: 0 0 0 0.2rem rgba(78, 115, 223, 0.25);
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--dark-color);
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            overflow-y: auto;
        }

        .modal-content {
            background-color: var(--card-bg);
            border-radius: 0.35rem;
            width: 90%;
            max-width: 500px;
            padding: 1.5rem;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            position: relative;
            animation: slideIn 0.3s ease-out;
        }

        @keyframes slideIn {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .modal-header h2 {
            font-size: 1.25rem;
            color: var(--dark-color);
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--secondary-color);
        }

        .modal-close:hover {
            color: var(--dark-color);
        }

        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .error {
            color: var(--danger-color);
            font-size: 0.75rem;
            margin-top: 0.25rem;
            display: none;
        }

        .toast {
            position: fixed;
            top: 20px;
            left: 20px;
            background-color: var(--card-bg);
            padding: 1rem 1.5rem;
            border-radius: 0.35rem;
            box-shadow: 0 0.25rem 0.75rem rgba(0, 0, 0, 0.1);
            display: none;
            z-index: 1000;
            max-width: 300px;
            border-left: 4px solid transparent;
        }

        .toast.success {
            border-left-color: var(--success-color);
        }

        .toast.error {
            border-left-color: var(--danger-color);
        }

        .toast.warning {
            border-left-color: var(--warning-color);
        }

        .toast.show {
            display: block;
            animation: slideInLeft 0.3s ease-out;
        }

        @keyframes slideInLeft {
            from { transform: translateX(-100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

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

        .overlay.active {
            display: block;
        }

        .status-active {
            color: var(--success-color);
            font-weight: 500;
        }

        .status-inactive {
            color: var(--danger-color);
            font-weight: 500;
        }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .confirmation-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .confirmation-content {
            background-color: var(--card-bg);
            border-radius: 0.35rem;
            width: 90%;
            max-width: 400px;
            padding: 1.5rem;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }

        .confirmation-message {
            margin-bottom: 1.5rem;
            font-size: 1rem;
            color: var(--text-color);
        }

        .confirmation-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
        }

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
        }

        @media (max-width: 768px) {
            .users-table {
                font-size: 0.75rem;
            }

            .users-table th,
            .users-table td {
                padding: 0.5rem;
                max-width: 100px;
            }

            .users-table th:nth-child(3),
            .users-table td:nth-child(3),
            .users-table th:nth-child(6),
            .users-table td:nth-child(6) {
                display: none;
            }

            .action-buttons {
                flex-direction: column;
                gap: 0.25rem;
            }

            .btn {
                width: 100%;
            }
        }

        @media (max-width: 576px) {
            .header {
                padding: 1rem;
            }

            .header-right {
                gap: 0.75rem;
                flex-wrap: wrap;
            }

            .main-content {
                padding: 1rem;
            }

            .section-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .modal-content {
                width: 95%;
                padding: 1rem;
            }
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
                <span>User Management</span>
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
            <a href="users.php" class="nav-link active">
                <i class="fas fa-users"></i> Users
            </a>
            <a href="send_quotation.php" class="nav-link">
                <i class="fas fa-file-invoice"></i> Send Quotation
            </a>
            <a href="sales-reports.php" class="nav-link">
                <i class="fas fa-chart-line"></i> Sales Reports
            </a>
            <a href="inventory-reports.php" class="nav-link">
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
        <section class="content-section active" id="users">
            <div class="section-header">
                <h1><i class="fas fa-users"></i> Manage Users</h1>
                <div class="section-actions">
                    <button class="btn btn-primary" id="addUserBtn">
                        <i class="fas fa-plus"></i> Add New User
                    </button>
                </div>
            </div>

            <!-- Search and Filter -->
            <div class="section-header" style="margin-bottom: 1rem;">
                <form method="GET" action="" style="display: flex; gap: 1rem; flex-wrap: wrap;">
                    <div class="form-group" style="flex: 1; min-width: 200px;">
                        <input type="text" name="search" class="form-control" placeholder="Search by name or email" value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="form-group" style="min-width: 150px;">
                        <select name="role" class="form-control">
                            <option value="">All Roles</option>
                            <option value="customer" <?php echo $role === 'customer' ? 'selected' : ''; ?>>Customer</option>
                            <option value="shop_manager" <?php echo $role === 'shop_manager' ? 'selected' : ''; ?>>Shop Manager</option>
                            <option value="cashier" <?php echo $role === 'cashier' ? 'selected' : ''; ?>>Cashier</option>
                            <option value="inventory_manager" <?php echo $role === 'inventory_manager' ? 'selected' : ''; ?>>Inventory Manager</option>
                            <option value="supplier" <?php echo $role === 'supplier' ? 'selected' : ''; ?>>Supplier</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Filter
                    </button>
                    <?php if ($search || $role): ?>
                        <a href="users.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Clear
                        </a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Users Table -->
            <div class="table-wrapper">
                <table class="users-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Last Active</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($users)): ?>
                            <tr>
                                <td colspan="7" style="text-align: center;">No users found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td><?php echo htmlspecialchars($user['phone'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $user['role']))); ?></td>
                                    <td class="<?php echo $user['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                        <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($user['last_active'] ? date('Y-m-d H:i', strtotime($user['last_active'])) : 'Never'); ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn btn-primary btn-sm edit-user" 
                                                data-id="<?php echo $user['user_id']; ?>" 
                                                data-firstname="<?php echo htmlspecialchars($user['first_name']); ?>" 
                                                data-lastname="<?php echo htmlspecialchars($user['last_name']); ?>" 
                                                data-email="<?php echo htmlspecialchars($user['email']); ?>" 
                                                data-phone="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" 
                                                data-role="<?php echo htmlspecialchars($user['role']); ?>" 
                                                data-active="<?php echo $user['is_active']; ?>">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                            <button class="btn btn-danger btn-sm delete-user" 
                                                data-id="<?php echo $user['user_id']; ?>" 
                                                data-name="<?php echo htmlspecialchars($user['first_name'] . ' ' . htmlspecialchars($user['last_name'])); ?>">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Add User Modal -->
        <div class="modal" id="addUserModal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2>Add New User</h2>
                    <button class="modal-close" id="closeAddModal">&times;</button>
                </div>
                <form id="addUserForm">
                    <input type="hidden" name="action" value="add_user">
                    <div class="form-group">
                        <label for="addFirstName">First Name</label>
                        <input type="text" id="addFirstName" name="first_name" class="form-control" required>
                        <div class="error" id="addFirstNameError">First name is required</div>
                    </div>
                    <div class="form-group">
                        <label for="addLastName">Last Name</label>
                        <input type="text" id="addLastName" name="last_name" class="form-control" required>
                        <div class="error" id="addLastNameError">Last name is required</div>
                    </div>
                    <div class="form-group">
                        <label for="addEmail">Email</label>
                        <input type="email" id="addEmail" name="email" class="form-control" required>
                        <div class="error" id="addEmailError">Valid email is required</div>
                    </div>
                    <div class="form-group">
                        <label for="addPhone">Phone (Optional)</label>
                        <input type="tel" id="addPhone" name="phone" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="addPassword">Password</label>
                        <input type="password" id="addPassword" name="password" class="form-control" required>
                        <div class="error" id="addPasswordError">Password must be at least 6 characters</div>
                    </div>
                    <div class="form-group">
                        <label for="addRole">Role</label>
                        <select id="addRole" name="role" class="form-control" required>
                            <option value="customer">Customer</option>
                            <option value="shop_manager">Shop Manager</option>
                            <option value="cashier">Cashier</option>
                            <option value="inventory_manager">Inventory Manager</option>
                            <option value="supplier">Supplier</option>
                        </select>
                        <div class="error" id="addRoleError">Role is required</div>
                    </div>
                    <div class="form-group">
                        <label for="addActive">Status</label>
                        <select id="addActive" name="is_active" class="form-control">
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary modal-close">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="addUserSubmit">Add User</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Edit User Modal -->
        <div class="modal" id="editUserModal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2>Edit User</h2>
                    <button class="modal-close" id="closeEditModal">&times;</button>
                </div>
                <form id="editUserForm">
                    <input type="hidden" name="action" value="edit_user">
                    <input type="hidden" id="editUserId" name="user_id">
                    <div class="form-group">
                        <label for="editFirstName">First Name</label>
                        <input type="text" id="editFirstName" name="first_name" class="form-control" required>
                        <div class="error" id="editFirstNameError">First name is required</div>
                    </div>
                    <div class="form-group">
                        <label for="editLastName">Last Name</label>
                        <input type="text" id="editLastName" name="last_name" class="form-control" required>
                        <div class="error" id="editLastNameError">Last name is required</div>
                    </div>
                    <div class="form-group">
                        <label for="editEmail">Email</label>
                        <input type="email" id="editEmail" name="email" class="form-control" required>
                        <div class="error" id="editEmailError">Valid email is required</div>
                    </div>
                    <div class="form-group">
                        <label for="editPhone">Phone (Optional)</label>
                        <input type="tel" id="editPhone" name="phone" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="editPassword">Password (Leave blank to keep unchanged)</label>
                        <input type="password" id="editPassword" name="password" class="form-control">
                        <div class="error" id="editPasswordError">Password must be at least 6 characters</div>
                    </div>
                    <div class="form-group">
                        <label for="editRole">Role</label>
                        <select id="editRole" name="role" class="form-control" required>
                            <option value="customer">Customer</option>
                            <option value="shop_manager">Shop Manager</option>
                            <option value="cashier">Cashier</option>
                            <option value="inventory_manager">Inventory Manager</option>
                            <option value="supplier">Supplier</option>
                        </select>
                        <div class="error" id="editRoleError">Role is required</div>
                    </div>
                    <div class="form-group">
                        <label for="editActive">Status</label>
                        <select id="editActive" name="is_active" class="form-control">
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary modal-close">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="editUserSubmit">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Delete Confirmation Modal -->
        <div class="confirmation-modal" id="deleteConfirmationModal">
            <div class="confirmation-content">
                <div class="confirmation-message" id="deleteConfirmationMessage">
                    Are you sure you want to delete this user?
                </div>
                <div class="confirmation-buttons">
                    <button type="button" class="btn btn-secondary" id="cancelDelete">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmDelete">Delete</button>
                </div>
            </div>
        </div>

        <!-- Toast Notification -->
        <div class="toast" id="toast">
            <span id="toastMessage"></span>
        </div>

      
    </main>
<footer style="text-align: center; padding: 10px 20px; background-color: var(--footer-bg); color: var(--text-color);">
    © <?php echo date('Y'); ?> Auntie Eddah POS - Shop Manager Dashboard
</footer>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Mobile menu toggle
            const mobileMenuToggle = document.getElementById('mobileMenuToggle');
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('overlay');

            mobileMenuToggle.addEventListener('click', function() {
                sidebar.classList.toggle('active');
                overlay.classList.toggle('active');
            });

            overlay.addEventListener('click', function() {
                sidebar.classList.remove('active');
                overlay.classList.remove('active');
            });

            // Theme toggle
            const themeToggle = document.getElementById('themeToggle');
            const themeIcon = themeToggle.querySelector('i');
            const themeText = themeToggle.querySelector('span');

            themeToggle.addEventListener('click', function() {
                const isDark = document.documentElement.classList.toggle('dark-mode');
                themeIcon.className = isDark ? 'fas fa-sun' : 'fas fa-moon';
                themeText.textContent = isDark ? 'Light Mode' : 'Dark Mode';
                
                fetch('', {
                    method: 'POST',
                    headers: { 
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: 'theme=' + (isDark ? 'dark' : 'light')
                })
                .catch(error => {
                    console.error('Error updating theme:', error);
                });
            });

            // Modal handling
            const addUserBtn = document.getElementById('addUserBtn');
            const addUserModal = document.getElementById('addUserModal');
            const editUserModal = document.getElementById('editUserModal');
            const closeAddModal = document.getElementById('closeAddModal');
            const closeEditModal = document.getElementById('closeEditModal');
            const modalCloses = document.querySelectorAll('.modal-close');
            const toast = document.getElementById('toast');
            const toastMessage = document.getElementById('toastMessage');
            const deleteConfirmationModal = document.getElementById('deleteConfirmationModal');
            const cancelDeleteBtn = document.getElementById('cancelDelete');
            const confirmDeleteBtn = document.getElementById('confirmDelete');
            const deleteConfirmationMessage = document.getElementById('deleteConfirmationMessage');

            let currentDeleteUserId = null;
            let currentDeleteUserName = null;

            function showToast(message, type = 'success', duration = 3000) {
                toastMessage.textContent = message;
                toast.className = `toast ${type} show`;
                
                // Clear any existing timeout
                if (toast.timeoutId) {
                    clearTimeout(toast.timeoutId);
                }
                
                toast.timeoutId = setTimeout(() => {
                    toast.className = 'toast';
                }, duration);
            }

            function closeAllModals() {
                addUserModal.style.display = 'none';
                editUserModal.style.display = 'none';
                deleteConfirmationModal.style.display = 'none';
                overlay.style.display = 'none';
            }

            function showModal(modal) {
                modal.style.display = 'flex';
                overlay.style.display = 'block';
            }

            addUserBtn.addEventListener('click', () => {
                showModal(addUserModal);
                document.getElementById('addUserForm').reset();
                document.querySelectorAll('#addUserForm .error').forEach(error => error.style.display = 'none');
            });

            modalCloses.forEach(btn => {
                btn.addEventListener('click', closeAllModals);
            });

            overlay.addEventListener('click', closeAllModals);

            // Edit user
            document.querySelectorAll('.edit-user').forEach(btn => {
                btn.addEventListener('click', () => {
                    const userId = btn.getAttribute('data-id');
                    document.getElementById('editUserId').value = userId;
                    document.getElementById('editFirstName').value = btn.getAttribute('data-firstname');
                    document.getElementById('editLastName').value = btn.getAttribute('data-lastname');
                    document.getElementById('editEmail').value = btn.getAttribute('data-email');
                    document.getElementById('editPhone').value = btn.getAttribute('data-phone') || '';
                    document.getElementById('editRole').value = btn.getAttribute('data-role');
                    document.getElementById('editActive').value = btn.getAttribute('data-active');
                    document.getElementById('editPassword').value = '';
                    document.querySelectorAll('#editUserForm .error').forEach(error => error.style.display = 'none');
                    showModal(editUserModal);
                });
            });

            // Delete user
            document.querySelectorAll('.delete-user').forEach(btn => {
                btn.addEventListener('click', () => {
                    currentDeleteUserId = btn.getAttribute('data-id');
                    currentDeleteUserName = btn.getAttribute('data-name');
                    deleteConfirmationMessage.textContent = `Are you sure you want to delete ${currentDeleteUserName}? This action cannot be undone.`;
                    showModal(deleteConfirmationModal);
                });
            });

            cancelDeleteBtn.addEventListener('click', closeAllModals);

            confirmDeleteBtn.addEventListener('click', function() {
                if (!currentDeleteUserId) return;
                
                this.classList.add('loading');
                this.disabled = true;
                
                fetch('', {
                    method: 'POST',
                    headers: { 
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: `action=delete_user&user_id=${currentDeleteUserId}`
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! Status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    this.classList.remove('loading');
                    this.disabled = false;
                    
                    if (data.success) {
                        showToast(data.message || 'User deleted successfully');
                        closeAllModals();
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showToast(data.error || 'Failed to delete user', 'error');
                    }
                })
                .catch(error => {
                    console.error('Delete error:', error);
                    this.classList.remove('loading');
                    this.disabled = false;
                    showToast(`Error deleting user: ${error.message}`, 'error');
                });
            });

            // Form validation and submission
            function validateForm(formId, isEdit = false) {
                const form = document.getElementById(formId);
                const firstName = form.querySelector('[name="first_name"]').value.trim();
                const lastName = form.querySelector('[name="last_name"]').value.trim();
                const email = form.querySelector('[name="email"]').value.trim();
                const password = form.querySelector('[name="password"]').value.trim();
                const role = form.querySelector('[name="role"]').value;
                let isValid = true;

                const setError = (id, message) => {
                    const errorElement = document.getElementById(id);
                    errorElement.style.display = 'block';
                    errorElement.textContent = message;
                    isValid = false;
                };

                // Reset all errors
                document.querySelectorAll(`#${formId} .error`).forEach(error => error.style.display = 'none');

                // Validate fields
                if (!firstName) setError(`${formId === 'addUserForm' ? 'add' : 'edit'}FirstNameError`, 'First name is required');
                if (!lastName) setError(`${formId === 'addUserForm' ? 'add' : 'edit'}LastNameError`, 'Last name is required');
                
                if (!email) {
                    setError(`${formId === 'addUserForm' ? 'add' : 'edit'}EmailError`, 'Email is required');
                } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                    setError(`${formId === 'addUserForm' ? 'add' : 'edit'}EmailError`, 'Valid email is required');
                }
                
                if (!isEdit && (!password || password.length < 6)) {
                    setError(`${formId === 'addUserForm' ? 'add' : 'edit'}PasswordError`, 'Password must be at least 6 characters');
                }
                
                if (isEdit && password && password.length < 6) {
                    setError(`${formId === 'addUserForm' ? 'add' : 'edit'}PasswordError`, 'Password must be at least 6 characters');
                }
                
                if (!role) setError(`${formId === 'addUserForm' ? 'add' : 'edit'}RoleError`, 'Role is required');

                return isValid;
            }

            // Add user form submission
            document.getElementById('addUserForm').addEventListener('submit', function(e) {
                e.preventDefault();
                if (validateForm('addUserForm')) {
                    const submitBtn = document.getElementById('addUserSubmit');
                    submitBtn.classList.add('loading');
                    submitBtn.disabled = true;
                    
                    const formData = new FormData(this);
                    
                    fetch('', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`HTTP error! Status: ${response.status}`);
                        }
                        return response.json();
                    })
                    .then(data => {
                        submitBtn.classList.remove('loading');
                        submitBtn.disabled = false;
                        
                        if (data.success) {
                            showToast(data.message || 'User added successfully');
                            closeAllModals();
                            setTimeout(() => location.reload(), 1000);
                        } else {
                            showToast(data.error || 'Failed to add user', 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Add user error:', error);
                        submitBtn.classList.remove('loading');
                        submitBtn.disabled = false;
                        showToast(`Error adding user: ${error.message}`, 'error');
                    });
                }
            });

            // Edit user form submission
            document.getElementById('editUserForm').addEventListener('submit', function(e) {
                e.preventDefault();
                if (validateForm('editUserForm', true)) {
                    const submitBtn = document.getElementById('editUserSubmit');
                    submitBtn.classList.add('loading');
                    submitBtn.disabled = true;
                    
                    const formData = new FormData(this);
                    
                    fetch('', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`HTTP error! Status: ${response.status}`);
                        }
                        return response.json();
                    })
                    .then(data => {
                        submitBtn.classList.remove('loading');
                        submitBtn.disabled = false;
                        
                        if (data.success) {
                            showToast(data.message || 'User updated successfully');
                            closeAllModals();
                            setTimeout(() => location.reload(), 1000);
                        } else {
                            showToast(data.error || 'Failed to update user', 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Edit user error:', error);
                        submitBtn.classList.remove('loading');
                        submitBtn.disabled = false;
                        showToast(`Error updating user: ${error.message}`, 'error');
                    });
                }
            });

            // Close modals when pressing Escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    closeAllModals();
                }
            });
        });
    </script>
</body>
</html>