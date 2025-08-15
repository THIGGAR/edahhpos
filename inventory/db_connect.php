<?php
try {
    // Use environment variables for database credentials
    $host = "localhost";
    $dbname = "aepos";
    $username = "root";
    $password = "";
    
    // Additional security: validate environment
    if (empty($dbname)) {
        throw new Exception("Database name is required");
    }
    
    $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
    
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false, // Use real prepared statements
        // Set connection collation to utf8mb4_general_ci to match product_categories table
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_general_ci",
        PDO::ATTR_TIMEOUT => 30, // Connection timeout
        PDO::ATTR_PERSISTENT => false // Disable persistent connections for better security
    ];
    
    $pdo = new PDO($dsn, $username, $password, $options);
    
    // Test the connection
    $pdo->query("SELECT 1");
    
} catch (PDOException $e) {
    // Log the error securely (don't expose database details to users)
    error_log("Database connection failed: " . $e->getMessage());
    
    // Show generic error to users
    die("Database connection failed. Please contact the system administrator.");
} catch (Exception $e) {
    error_log("Database configuration error: " . $e->getMessage());
    die("Database configuration error. Please contact the system administrator.");
}
?>

<?php


