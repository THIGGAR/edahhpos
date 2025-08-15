<?php
// Connect to the database
$host = "localhost";
$username = "root"; // Replace with your DB username
$password = ""; // Replace with your DB password
$dbname = "aepos";

$conn = new mysqli($host, $username, $password , $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Collect data (in a real application, these should come from a form via POST and be validated/sanitized)
$first_name = 'Zacharia';
$last_name = 'Mbuwa';
$password_plain = 'Zacharia1234';
$role = 'supplier';
$email = 'zacharia@example.com';
$phone = '+265993952814';
$is_active = 1;
$created_at = date('Y-m-d H:i:s');
$last_active = date('Y-m-d H:i:s');
$remember_token = bin2hex(random_bytes(16)); // 32-character token

// Hash the password
$password_hashed = password_hash($password_plain, PASSWORD_BCRYPT);

// Prepare SQL statement
$sql = "INSERT INTO users (first_name, last_name, password, role, email, phone, is_active, created_at, last_active, remember_token)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = $conn->prepare($sql);

if ($stmt === false) {
    die("Prepare failed: " . $conn->error);
}

// Bind parameters
$stmt->bind_param(
    "ssssssisss", 
    $first_name, 
    $last_name, 
    $password_hashed, 
    $role, 
    $email, 
    $phone, 
    $is_active, 
    $created_at, 
    $last_active, 
    $remember_token
);

// Execute
if ($stmt->execute()) {
    echo "User added successfully.";
} else {
    echo "Error: " . $stmt->error;
}

// Close
$stmt->close();
$conn->close();
?>
