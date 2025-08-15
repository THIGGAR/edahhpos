<?php
session_start();

require_once __DIR__ . 
'/db_connect.php';
require_once __DIR__ . 
'/functions.php';

// This script is intended for initial setup to create a default admin user.
// It should be removed or secured after first use in a production environment.

// Admin user details - CHANGE THESE VALUES FOR PRODUCTION
$first_name = "Super";
$last_name = "Admin";
$email = "admin@example.com";
$password = "Admin@2025!"; // This password should be changed immediately after first login
$role = "admin";
$phone = null; // Optional
$is_active = 1;

try {
    // Check if user already exists to prevent duplicate entries
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
    if (!$stmt) {
        throw new Exception("Failed to prepare user check: " . $conn->error);
    }
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        echo "Error: A user with the email \'" . htmlspecialchars($email) . "\' already exists.\n";
    } else {
        // Use the addUser function for consistency and proper password hashing
        $new_user = addUser($conn, $first_name, $last_name, $email, $role, $phone);
        
        if ($new_user["user_id"]) {
            echo "Admin user created successfully!\n";
            echo "Login Details:\n";
            echo "Email: " . htmlspecialchars($email) . "\n";
            echo "Temporary Password: " . htmlspecialchars($new_user["temp_password"]) . "\n";
            echo "Role: " . htmlspecialchars($role) . "\n";
            echo "\nIMPORTANT: Please change this temporary password immediately after logging in.\n";
        } else {
            echo "Error creating admin user.\n";
        }
    }
    $stmt->close();
} catch (Exception $e) {
    error_log("Error in create_admin.php: " . $e->getMessage());
    echo "An error occurred during admin creation. Please check the server logs.\n";
}

// It's good practice to close the connection when done.
$conn->close();
?>

