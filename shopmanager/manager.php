<?php
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/functions.php';

// Cashier details
$first_name = "Eddah";
$last_name = "Msiska";
$email = "eddahmsiska@example.com";
$password = "Manager2025!";
$role = "shop_manager";
$phone = "+265999455689";
$is_active = 1;

$hashed_password = password_hash($password, PASSWORD_DEFAULT);

try {
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        echo "Error: User with email '$email' already exists.\n";
    } else {
        $stmt = $conn->prepare("INSERT INTO users (first_name, last_name, password, role, email, phone, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssssi", $first_name, $last_name, $hashed_password, $role, $email, $phone, $is_active);
        $stmt->execute();
        echo "created successfully!\n";
        echo "Login Details:\n";
        echo "Email: $email\n";
        echo "Password: $password\n";
        echo "Role: $role\n";
    }
    $stmt->close();
} catch (Exception $e) {
    echo "Error creating cashier: " . $e->getMessage() . "\n";
}

$conn->close();
?>