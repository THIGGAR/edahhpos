<?php
require_once 'db_connect.php';
require_once '..\functions.php';

// Cashier details
$first_name = "Grace";
$last_name = "Phiri";
$email = "gracephiri@example.com";
$password = "Cashier2025!";
$role = "cashier";
$phone = "+265999456789";
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
        echo "Cashier created successfully!\n";
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