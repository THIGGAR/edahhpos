```php
<?php
session_start();
require_once 'db_connect.php';
require_once 'functions.php';

// Set JSON response header
header('Content-Type: application/json');

// Ensure only shop managers can access this endpoint
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'shop_manager') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and validate inputs
    $first_name = sanitizeInput(filter_input(INPUT_POST, 'first_name', FILTER_SANITIZE_STRING));
    $last_name = sanitizeInput(filter_input(INPUT_POST, 'last_name', FILTER_SANITIZE_STRING));
    $email = sanitizeInput(filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL));
    $phone = sanitizeInput(filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING)) ?: null;
    $role = sanitizeInput(filter_input(INPUT_POST, 'role', FILTER_SANITIZE_STRING));

    // Validate inputs
    $valid_roles = ['cashier', 'inventory_manager'];
    if (!in_array($role, $valid_roles)) {
        echo json_encode(['success' => false, 'message' => 'Invalid role selected.']);
        exit;
    }

    if (empty($first_name) || empty($last_name) || empty($email) || !isValidEmail($email)) {
        echo json_encode(['success' => false, 'message' => 'First name, last name, and a valid email are required.']);
        exit;
    }

    try {
        // Check if email already exists
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $stmt->close();
            echo json_encode(['success' => false, 'message' => 'Email already exists.']);
            exit;
        }
        $stmt->close();

        // Generate temporary password
        $temp_password = bin2hex(random_bytes(8));
        $hashed_password = password_hash($temp_password, PASSWORD_DEFAULT);
        
        // Insert new user
        $stmt = $conn->prepare("INSERT INTO users (first_name, last_name, email, password, role, phone, is_active, created_at) VALUES (?, ?, ?, ?, ?, ?, 1, NOW())");
        $stmt->bind_param("ssssss", $first_name, $last_name, $email, $hashed_password, $role, $phone);
        
        if ($stmt->execute()) {
            $user_id = $conn->insert_id;
            $stmt->close();
            
            // Log the action
            logAuditAction($conn, $_SESSION['user_id'], 'ADD_USER', 'Added new user: ' . $email . ' with role ' . $role);
            
            echo json_encode([
                'success' => true, 
                'message' => "User {$first_name} {$last_name} created successfully. Temporary password: {$temp_password}. Please inform the user to change it.",
                'user_id' => $user_id,
                'temp_password' => $temp_password
            ]);
        } else {
            $stmt->close();
            echo json_encode(['success' => false, 'message' => 'Failed to create user. Database error.']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error creating user: ' . $e->getMessage()]);
        error_log("Error in add_user.php: " . $e->getMessage());
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}

// Helper function to validate email
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}
// Helper function to log audit actions
function logAuditAction($conn, $user_id, $action, $description) {
    try {
        $stmt = $conn->prepare("INSERT INTO audit_log (user_id, action, description, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->bind_param("iss", $user_id, $action, $description);
        $stmt->execute();
        $stmt->close();
    } catch (Exception $e) {
        error_log("Error logging audit action: " . $e->getMessage());
    }
}
?>
```