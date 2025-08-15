<?php
session_start();
require_once 'db_connect.php';
require_once 'functions.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit();
}

// Validate CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
    exit();
}

// Validate action and product_id
$action = isset($_POST['action']) ? $_POST['action'] : '';
$product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
$quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;

if (!$product_id || $product_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid product ID']);
    exit();
}

// Handle actions
try {
    if ($action === 'add_to_cart' || $action === 'buy_now') {
        // Verify product exists
        $product = getProductById($product_id); // Assumes getProductById from functions.php
        if (!$product) {
            echo json_encode(['success' => false, 'error' => 'Product not found']);
            exit();
        }

        // Add to cart
        addToCart($conn, $_SESSION['user_id'], $product_id, $quantity);

        // For buy_now, prepare payment data
        if ($action === 'buy_now') {
            $cart = getCart($conn, $_SESSION['user_id']);
            if (empty($cart)) {
                echo json_encode(['success' => false, 'error' => 'Cart is empty']);
                exit();
            }

            $total = 0;
            $items = [];
            foreach ($cart as $item) {
                $items[] = [
                    'product_id' => $item['product_id'],
                    'name' => $item['name'],
                    'quantity' => $item['quantity'],
                    'price' => $item['price']
                ];
                $total += $item['price'] * $item['quantity'];
            }

            $profile = getProfile($conn, $_SESSION['user_id']);
            if (empty($profile['first_name']) || empty($profile['last_name']) || empty($profile['email'])) {
                echo json_encode(['success' => false, 'error' => 'Incomplete profile data']);
                exit();
            }

            // Store cart data in session
            $_SESSION['cart_data'] = [
                'email' => $profile['email'],
                'firstname' => $profile['first_name'],
                'surname' => $profile['last_name'],
                'amount' => $total,
                'items' => $items
            ];

            // Generate transaction reference
            $_SESSION['transaction_ref'] = 'EDAHHPOS' . bin2hex(random_bytes(8)) . '-' . time();

            // Insert into payments table with user_id
            $sql = "INSERT INTO payments (transaction_ref, email, firstname, surname, amount, status, created_at, payment_method) VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Failed to prepare payment insertion: " . $conn->error);
            }
            $status = 'pending';
            $payment_method = 'PayChangu';
            $stmt->bind_param("ssssdss", $_SESSION['transaction_ref'], $profile['email'], $profile['first_name'], $profile['last_name'], $total, $status, $payment_method);
            if (!$stmt->execute()) {
                throw new Exception("Failed to insert payment: " . $stmt->error);
            }
            $stmt->close();

            echo json_encode(['success' => true, 'redirect' => 'dashboard.php?page=cart']);
        } else {
            echo json_encode(['success' => true, 'message' => 'Item added to cart']);
        }
    } elseif ($action === 'update_cart') {
        if ($quantity <= 0) {
            throw new Exception("Invalid quantity");
        }
        updateCartItem($conn, $_SESSION['user_id'], $product_id, $quantity);
        echo json_encode(['success' => true, 'message' => 'Cart updated']);
    } elseif ($action === 'remove_from_cart') {
        updateCartItem($conn, $_SESSION['user_id'], $product_id, 0);
        echo json_encode(['success' => true, 'message' => 'Item removed from cart']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
} catch (Exception $e) {
    error_log("Cart action failed for user {$_SESSION['user_id']}: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

$conn->close();

// Add this helper function if not already in functions.php
function getProductById($product_id) {
    global $conn;
    $stmt = $conn->prepare("SELECT product_id, name, price, image FROM products WHERE product_id = ?");
    if (!$stmt) {
        error_log("Prepare failed in getProductById: " . $conn->error);
        return null;
    }
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $product = $result->fetch_assoc();
    $stmt->close();
    return $product;
}
?>

