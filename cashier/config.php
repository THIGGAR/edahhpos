<?php
// Database configuration
$host = "localhost";
$user = "root";
$password = "";
$dbname = "aepos";

// Establish database connection
$conn = new mysqli($host, $user, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

return [
    'paychangu' => [
         'public_key' => 'PUB-2Gv1hPxqu1KqhbH3VJ3886NlQxEZeXji',
        'secret_key' => 'SEC-S1l5Jkcc9FSgJao8tlm2kcFwbb9xi13u',
        'callback_url' => 'http://localhost/EdahhPos/cashier/callback.php',
        'return_url' => 'http://localhost/EdahhPos/cashier/return.php'
    ],
     'smtp' => [
        'host' => 'smtp.gmail.com',
        'username' => 'zecaliajmbuwa99@mail.com',
        'password' => 'npvn iuna fmzm oags',
        'secure' => 'tls',
        'port' => 587,
    ],
];
?>