<?php
session_start();

require_once '../vendor/autoload.php';
require_once 'config.php';
require_once 'db_connect.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

// Log session data for debugging
error_log("callback.php - Session data: " . print_r($_SESSION, true));

// Validate session data
if (!isset($_SESSION['transaction_ref']) || !isset($_SESSION['cart_data'])) {
    error_log("Session data missing for transaction_ref: " . ($_SESSION['transaction_ref'] ?? 'unknown'));
    header("Location: return.php?status=failed&message=Session%20data%20missing");
    exit();
}

$transaction_ref = $_SESSION['transaction_ref'];
$cart_data = $_SESSION['cart_data'];
$config['paychangu']['secret_key'] = 'SEC-S1l5Jkcc9FSgJao8tlm2kcFwbb9xi13u';
// Validate PayChangu secret key
if (!isset($config['paychangu']['secret_key']) || empty(trim($config['paychangu']['secret_key']))) {
    error_log("PayChangu secret key is missing or empty in config.php");
    $error_message = "Payment verification failed: Missing or invalid secret key. Please contact support.";
    // Update payment status to failed
    $status_failed = 'failed';
    $sql_update = "UPDATE payments SET status = ?, created_at = NOW() WHERE transaction_ref = ?";
    $stmt_update = $conn->prepare($sql_update);
    if ($stmt_update) {
        $stmt_update->bind_param("ss", $status_failed, $transaction_ref);
        $stmt_update->execute();
        $stmt_update->close();
    } else {
        error_log("Failed to prepare payment update: " . $conn->error);
    }
    header("Location: return.php?status=failed&message=" . urlencode($error_message) . "&transaction_ref=" . urlencode($transaction_ref));
    exit();
}

// Log secret key (partially masked for security)
$masked_key = substr($config['paychangu']['secret_key'], 0, 4) . str_repeat('*', strlen($config['paychangu']['secret_key']) - 8) . substr($config['paychangu']['secret_key'], -4);
error_log("PayChangu secret key (masked): $masked_key");

$client = new Client();

try {
    // Log transaction verification attempt
    error_log("Verifying payment for transaction_ref: $transaction_ref");
    error_log("Session cart data: " . json_encode($cart_data));

    // Use MZTEC-compatible endpoint (verify with PayChangu documentation)
    $api_endpoint = "https://api.paychangu.com/verify-payment/{$transaction_ref}";
    error_log("Calling PayChangu API: $api_endpoint with Authorization: Bearer [masked]");

    $response = $client->request('GET', $api_endpoint, [
        'headers' => [
            'Authorization' => 'Bearer ' . trim($config['paychangu']['secret_key']),
            'Accept' => 'application/json',
        ],
    ]);

    $data = json_decode($response->getBody(), true);
    error_log("PayChangu API response: " . json_encode($data));

    if ($response->getStatusCode() === 200 && isset($data['status']) && $data['status'] === 'success' && $data['data']['status'] === 'success') {
        $payment_status = 'completed';

        // Begin database transaction
        $conn->begin_transaction();

        try {
            // Update payments table
            $sql_update = "UPDATE payments SET status = ?, created_at = NOW() WHERE transaction_ref = ?";
            $stmt_update = $conn->prepare($sql_update);
            if (!$stmt_update) {
                throw new Exception("Failed to prepare payment update: " . $conn->error);
            }
            $stmt_update->bind_param("ss", $payment_status, $transaction_ref);
            if (!$stmt_update->execute()) {
                throw new Exception("Failed to update payment status: " . $stmt_update->error);
            }
            $stmt_update->close();

            // Calculate total amount
            $total_amount = 0;
            foreach ($cart_data['items'] as $item) {
                $total_amount += $item['price'] * $item['quantity'];
            }

            // Insert order data
            $sql = "INSERT INTO orders (user_id, total, payment_method, status, created_at, amount, total_amount) VALUES (?, ?, ?, ?, NOW(), ?, ? )";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Failed to prepare order insertion: " . $conn->error);
            }
            $order_status = 'completed';
            $payment_method = 'PayChangu';
            $stmt->bind_param(
                "idssdd",
                $_SESSION['user_id'],
                $total_amount,
                $payment_method,
                $order_status,
                $total_amount,
                $total_amount,
            );
            if (!$stmt->execute()) {
                throw new Exception("Failed to save order: " . $stmt->error);
            }
            $order_id = $conn->insert_id;
            $stmt->close();

            // Insert order items
            $sql_items = "INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)";
            $stmt_items = $conn->prepare($sql_items);
            if (!$stmt_items) {
                throw new Exception("Failed to prepare order items insertion: " . $conn->error);
            }
            foreach ($cart_data['items'] as $item) {
                $stmt_items->bind_param("iiid", $order_id, $item['product_id'], $item['quantity'], $item['price']);
                if (!$stmt_items->execute()) {
                    throw new Exception("Failed to save order item: " . $stmt_items->error);
                }
            }
            $stmt_items->close();

            // Clear cart
            $sql_clear_cart = "DELETE FROM cart WHERE user_id = ?";
            $stmt_clear_cart = $conn->prepare($sql_clear_cart);
            if (!$stmt_clear_cart) {
                throw new Exception("Failed to prepare cart clearing query: " . $conn->error);
            }
            $stmt_clear_cart->bind_param("i", $_SESSION['user_id']);
            if (!$stmt_clear_cart->execute()) {
                throw new Exception("Failed to clear cart: " . $stmt_clear_cart->error);
            }
            $stmt_clear_cart->close();

            // Commit transaction
            $conn->commit();

            // Send email notification
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host = $config['smtp']['host'];
                $mail->SMTPAuth = true;
                $mail->Username = $config['smtp']['username'];
                $mail->Password = $config['smtp']['password'];
                $mail->SMTPSecure = $config['smtp']['secure'];
                $mail->Port = $config['smtp']['port'];

                $mail->setFrom($config['smtp']['username'], 'EDAHHPOS Customer Service');
                $mail->addAddress($cart_data['email'], $cart_data['firstname'] . ' ' . $cart_data['surname']);
                $mail->isHTML(true);
                $mail->Subject = 'Order Confirmation - EDAHHPOS';
                $items_list = '<ul>';
                foreach ($cart_data['items'] as $item) {
                    $items_list .= '<li>' . htmlspecialchars($item['name']) . ' (Qty: ' . $item['quantity'] . ') - MWK ' . number_format($item['price'] * $item['quantity'], 2) . '</li>';
                }
                $items_list .= '</ul>';
                $mail->Body = '
                    <h2>Order Confirmation</h2>
                    <p>Dear ' . htmlspecialchars($cart_data['firstname'] . ' ' . $cart_data['surname']) . ',</p>
                    <p>Thank you for your purchase from EDAHHPOS. Your payment has been successfully processed.</p>
                    <p><strong>Order ID:</strong> ' . $order_id . '</p>
                    <p><strong>Transaction Reference:</strong> ' . htmlspecialchars($transaction_ref) . '</p>
                    ' . $items_list . '
                    <p><strong>Total:</strong> MWK ' . number_format($cart_data['amount'], 2) . '</p>
                    <p>Your order is being processed. You will receive further updates on your order status.</p>
                    <p>Contact us at <a href="mailto:support@edahhpos.ac.mw">support@edahhpos.ac.mw</a> for any inquiries.</p>
                    <p>Best regards,<br>EDAHHPOS Team</p>
                ';
                $mail->AltBody = strip_tags($mail->Body);

                $mail->send();
                error_log("Email sent to " . $cart_data['email']);
            } catch (Exception $e) {
                error_log("Failed to send email: " . $mail->ErrorInfo);
            }

            // Clear session data
            unset($_SESSION['transaction_ref']);
            unset($_SESSION['cart_data']);
            unset($_SESSION['csrf_token']);

            // Redirect to return.php
            $redirect_url = "return.php?status=success&message=Order%20completed%20successfully&transaction_ref=" . urlencode($transaction_ref);
            error_log("Redirecting to: $redirect_url");
            header("Location: $redirect_url");
            exit();
        } catch (Exception $e) {
            $conn->rollback();
            throw $e;
        }
    } else {
        // Handle pending or failed payment
        $status_failed = 'failed';
        $error_message = isset($data['message']) ? $data['message'] : "Payment not successful or still pending: " . json_encode($data);
        error_log("Payment verification failed: $error_message");

        // Update payment status to failed
        $sql_update = "UPDATE payments SET status = ?, created_at = NOW() WHERE transaction_ref = ?";
        $stmt_update = $conn->prepare($sql_update);
        if ($stmt_update) {
            $stmt_update->bind_param("ss", $status_failed, $transaction_ref);
            $stmt_update->execute();
            $stmt_update->close();
        } else {
            error_log("Failed to prepare payment update: " . $conn->error);
        }

        header("Location: return.php?status=failed&message=" . urlencode($error_message) . "&transaction_ref=" . urlencode($transaction_ref));
        exit();
    }
} catch (RequestException $e) {
    $status_failed = 'failed';
    $error_message = "Payment verification failed: " . $e->getMessage();
    if ($e->hasResponse()) {
        $response_body = $e->getResponse()->getBody()->getContents();
        error_log("PayChangu API response body: " . $response_body);
        if ($e->getResponse()->getStatusCode() === 404) {
            $error_message = "Transaction not found. Please ensure the payment was initiated correctly or try again.";
        } elseif ($e->getResponse()->getStatusCode() === 403) {
            $error_message = "Payment verification failed: Invalid or missing secret key. Please contact support.";
        } else {
            $error_message .= " - Response: " . $response_body;
        }
    }
    error_log("PayChangu API error: " . $error_message);

    // Update payment status to failed
    $sql_update = "UPDATE payments SET status = ?, created_at = NOW() WHERE transaction_ref = ?";
    $stmt_update = $conn->prepare($sql_update);
    if ($stmt_update) {
        $stmt_update->bind_param("ss", $status_failed, $transaction_ref);
        $stmt_update->execute();
        $stmt_update->close();
    } else {
        error_log("Failed to prepare payment update: " . $conn->error);
    }

    header("Location: return.php?status=failed&message=" . urlencode($error_message) . "&transaction_ref=" . urlencode($transaction_ref));
    exit();
} catch (Exception $e) {
    $status_failed = 'failed';
    error_log("General exception in callback.php: " . $e->getMessage());

    // Update payment status to failed
    $sql_update = "UPDATE payments SET status = ?, created_at = NOW() WHERE transaction_ref = ?";
    $stmt_update = $conn->prepare($sql_update);
    if ($stmt_update) {
        $stmt_update->bind_param("ss", $status_failed, $transaction_ref);
        $stmt_update->execute();
        $stmt_update->close();
    } else {
        error_log("Failed to prepare payment update: " . $conn->error);
    }

    header("Location: return.php?status=failed&message=" . urlencode($e->getMessage()) . "&transaction_ref=" . urlencode($transaction_ref));
    exit();
} finally {
    $conn->close();
}
?>