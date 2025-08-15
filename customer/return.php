<?php
session_start();

// Log session data for debugging
error_log("return.php - Session data: " . print_r($_SESSION, true));

// Validate session (optional, depending on requirements)
if (!isset($_SESSION['user_id'])) {
    error_log("Unauthorized access attempt to return.php - no user_id in session");
    header("Location: login.php?error=Please%20log%20in%20to%20proceed");
    exit();
}

// Initialize variables with defaults
$status = isset($_GET['status']) ? strtolower(trim($_GET['status'])) : '';
$transaction_ref = isset($_GET['transaction_ref']) ? htmlspecialchars(trim($_GET['transaction_ref']), ENT_QUOTES, 'UTF-8') : (isset($_SESSION['transaction_ref']) ? htmlspecialchars($_SESSION['transaction_ref'], ENT_QUOTES, 'UTF-8') : '');
$message = isset($_GET['message']) && is_string($_GET['message']) && !empty($_GET['message'])
    ? htmlspecialchars(urldecode($_GET['message']), ENT_QUOTES, 'UTF-8')
    : ($status === 'success' ? "Payment successful! Your order has been completed." : "Payment failed. Please try again or contact support.");
$redirect_url = $status === 'success' ? "dashboard.php?page=orders" : "dashboard.php?page=cart";
$redirect_delay = 3000;

// Log query parameters
error_log("return.php - Status: $status, Transaction Ref: $transaction_ref, Message: $message");

// Include database connection for payment details
require_once 'db_connect.php';
$payment_details = null;
if (!empty($transaction_ref)) {
    $sql_payment = "SELECT * FROM payments WHERE transaction_ref = ?";
    $stmt_payment = $conn->prepare($sql_payment);
    if (!$stmt_payment) {
        error_log("Failed to prepare payment query in return.php: " . $conn->error);
        $message = "Database error. Please contact support with reference: " . htmlspecialchars($transaction_ref);
    } else {
        $stmt_payment->bind_param("s", $transaction_ref);
        if (!$stmt_payment->execute()) {
            error_log("Failed to execute payment query in return.php: " . $stmt_payment->error);
            $message = "Database error. Please contact support with reference: " . htmlspecialchars($transaction_ref);
        } else {
            $result = $stmt_payment->get_result();
            if ($result->num_rows > 0) {
                $payment_details = $result->fetch_assoc();
            } else {
                error_log("No payment found for transaction_ref: $transaction_ref");
                $message = "No payment record found for transaction reference: " . htmlspecialchars($transaction_ref) . ". Please contact support.";
            }
            $stmt_payment->close();
        }
    }
}

// Enhance message with order details if available
if ($status === 'success' && $payment_details && $payment_details['status'] === 'completed') {
    $message = "Payment successful! Your order has been completed. Transaction Reference: " . htmlspecialchars($payment_details['transaction_ref']) . ". Amount: MWK " . number_format($payment_details['amount'], 2) . ".";
} elseif ($status === 'success' && (!$payment_details || $payment_details['status'] !== 'completed')) {
    $message = "Payment processing completed, but verification failed. Please contact support with reference: " . htmlspecialchars($transaction_ref);
    error_log("Payment success but database status mismatch for transaction_ref: $transaction_ref");
}

// Update payment status to failed if applicable and no record exists
if ($status === 'failed' && !empty($transaction_ref) && !$payment_details) {
    $sql_insert = "INSERT INTO payments (transaction_ref, status, amount, created_at) VALUES (?, ?, ?, NOW())";
    $stmt_insert = $conn->prepare($sql_insert);
    if ($stmt_insert) {
        $status_failed = 'failed';
        $amount = 0; // Default amount, update if known
        $stmt_insert->bind_param("ssd", $transaction_ref, $status_failed, $amount);
        if (!$stmt_insert->execute()) {
            error_log("Failed to insert failed payment for transaction_ref $transaction_ref: " . $stmt_insert->error);
        }
        $stmt_insert->close();
    } else {
        error_log("Failed to prepare payment insert: " . $conn->error);
    }
}

// Clear session data only on success
if ($status === 'success') {
    unset($_SESSION['transaction_ref']);
    unset($_SESSION['cart_data']);
    unset($_SESSION['csrf_token']);
    $_SESSION['message'] = $message; // Store message for dashboard
}

// Sanitize redirect URL
$parsed_url = parse_url($redirect_url);
if (!$parsed_url || !isset($parsed_url['scheme']) && !in_array(parse_url($redirect_url, PHP_URL_PATH), ['dashboard.php'])) {
    error_log("Invalid redirect URL detected: " . $redirect_url);
    $redirect_url = "dashboard.php?page=cart";
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Result - EDAHHPOS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        /* Existing styles unchanged (same as provided previously) */
        :root {
            --primary-color: #4a6baf;
            --secondary-color: #ff6b6b;
            --accent-color: #ffcc00;
            --dark-color: #003366;
            --light-color: #f0f4f8;
            --white: #ffffff;
            --text-color: #333333;
            --shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            --shadow-hover: 0 15px 40px rgba(0, 0, 0, 0.15);
            --primary-hover: #3b5490;
            --secondary-hover: #e55555;
            --accent-hover: #e6b800;
            --success-color: #28a745;
            --danger-color: #dc3545;
            --border-color: #e1e5eb;
            --border-radius: 1rem;
            --spacing-sm: 0.5rem;
            --spacing-md: 1rem;
            --spacing-lg: 1.5rem;
            --spacing-xl: 2rem;
            --font-size-base: clamp(0.875rem, 1.8vw, 1rem);
            --font-size-lg: clamp(1.25rem, 2.5vw, 1.5rem);
            --font-size-xl: clamp(1.5rem, 3.5vw, 1.75rem);
            --font-size-xxl: clamp(2rem, 4vw, 2.5rem);
            --glass-bg: rgba(255, 255, 255, 0.1);
            --glass-border: rgba(255, 255, 255, 0.2);
            --backdrop-blur: blur(20px);
            --transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, var(--light-color) 0%, #e2e8f0 100%);
            color: var(--text-color);
            font-size: var(--font-size-base);
            line-height: 1.6;
            min-height: 100vh;
            padding: var(--spacing-xl);
            position: relative;
            overflow-x: hidden;
        }

        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: url('payment_bg.jpg');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            opacity: 0.03;
            z-index: -1;
            pointer-events: none;
        }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        .main-content {
            max-width: 700px;
            margin: 3rem auto;
            background: var(--glass-bg);
            backdrop-filter: var(--backdrop-blur);
            border: 1px solid var(--glass-border);
            padding: var(--spacing-xl);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            animation: fadeInUp 0.8s ease;
            position: relative;
            overflow: hidden;
            text-align: center;
        }

        .main-content::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color), var(--accent-color));
            background-size: 200% 100%;
            animation: gradientShift 3s ease infinite;
        }

        .status-icon {
            font-size: 4rem;
            margin-bottom: var(--spacing-lg);
            animation: pulse 2s infinite;
        }

        .status-icon.success {
            color: var(--success-color);
        }

        .status-icon.failed {
            color: var(--danger-color);
        }

        .section-title {
            font-size: var(--font-size-xxl);
            font-weight: 700;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: var(--spacing-xl);
        }

        .success, .error {
            margin-bottom: var(--spacing-lg);
            padding: var(--spacing-md);
            border-radius: var(--border-radius);
            font-weight: 600;
            font-size: var(--font-size-lg);
        }

        .success {
            color: var(--success-color);
            background: rgba(40, 167, 69, 0.1);
            border: 1px solid var(--success-color);
        }

        .error {
            color: var(--danger-color);
            background: rgba(220, 53, 69, 0.1);
            border: 1px solid var(--danger-color);
            animation: shake 0.5s ease-in-out;
        }

        .error a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600;
            transition: var(--transition);
        }

        .error a:hover {
            color: var(--secondary-color);
            text-decoration: underline;
        }

        .transaction-details {
            background: var(--glass-bg);
            backdrop-filter: var(--backdrop-blur);
            border: 1px solid var(--glass-border);
            border-radius: var(--border-radius);
            padding: var(--spacing-lg);
            margin: var(--spacing-lg) 0;
            text-align: left;
        }

        .transaction-details h3 {
            color: var(--primary-color);
            margin-bottom: var(--spacing-md);
            font-size: var(--font-size-lg);
        }

        .transaction-details p {
            margin-bottom: var(--spacing-sm);
            font-weight: 500;
        }

        .btn {
            display: inline-block;
            padding: var(--spacing-md) var(--spacing-lg);
            background: linear-gradient(135deg, var(--primary-color), var(--primary-hover));
            color: var(--white);
            text-align: center;
            font-size: var(--font-size-lg);
            font-weight: 600;
            border-radius: var(--border-radius);
            text-decoration: none;
            transition: var(--transition);
            box-shadow: var(--shadow);
            margin-top: var(--spacing-md);
        }

        .btn:hover {
            background: linear-gradient(135deg, var(--secondary-color), var(--secondary-hover));
            transform: translateY(-2px);
            box-shadow: var(--shadow-hover);
        }

        .btn.success {
            background: linear-gradient(135deg, var(--success-color), #218838);
        }

        .btn.success:hover {
            background: linear-gradient(135deg, #218838, #1e7e34);
        }

        .loading-spinner {
            display: <?php echo $status ? 'block' : 'none'; ?>;
            width: 40px;
            height: 40px;
            border: 4px solid var(--border-color);
            border-top: 4px solid var(--primary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: var(--spacing-lg) auto;
        }

        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        @media (max-width: 768px) {
            body {
                padding: var(--spacing-md);
            }
            .main-content {
                margin: 1rem auto;
                padding: var(--spacing-lg);
            }
            .section-title {
                font-size: var(--font-size-xl);
            }
            .status-icon {
                font-size: 3rem;
            }
        }

        @media (prefers-color-scheme: dark) {
            :root {
                --text-color: #e2e8f0;
                --light-color: #1a202c;
                --border-color: #4a5568;
                --glass-bg: rgba(45, 55, 72, 0.1);
                --glass-border: rgba(255, 255, 255, 0.1);
            }
            body {
                background: linear-gradient(135deg, #1a202c 0%, #2d3748 100%);
            }
        }

        @media (prefers-reduced-motion: reduce) {
            *, *:before, *:after {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
        }

        *:focus {
            outline: 2px solid var(--accent-color);
            outline-offset: 2px;
        }
    </style>
</head>
<body>
    <div class="main-content">
        <div class="status-icon <?php echo $status === 'success' ? 'success' : 'failed'; ?>">
            <i class="fas <?php echo $status === 'success' ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
        </div>
        <h2 class="section-title">Payment Status</h2>
        <p class="<?php echo $status === 'success' ? 'success' : 'error'; ?>">
            <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
            <?php if ($status !== 'success'): ?>
                <br><small>Contact <a href="mailto:support@edahhpos.ac.mw">support@edahhpos.ac.mw</a> for assistance.</small>
            <?php endif; ?>
        </p>
        <?php if ($payment_details): ?>
        <div class="transaction-details">
            <h3><i class="fas fa-receipt"></i> Transaction Details</h3>
            <p><strong>Transaction Reference:</strong> <?php echo htmlspecialchars($payment_details['transaction_ref']); ?></p>
            <p><strong>Amount:</strong> MWK <?php echo number_format($payment_details['amount'], 2); ?></p>
            <p><strong>Status:</strong>
                <span style="color: <?php echo $payment_details['status'] === 'completed' ? 'var(--success-color)' : ($payment_details['status'] === 'failed' ? 'var(--danger-color)' : 'var(--accent-color)'); ?>">
                    <?php echo ucfirst($payment_details['status']); ?>
                </span>
            </p>
            <p><strong>Date:</strong> <?php echo date('F j, Y g:i A', strtotime($payment_details['created_at'])); ?></p>
            <?php if (isset($payment_details['payment_method']) && $payment_details['payment_method'] !== 'N/A'): ?>
            <p><strong>Payment Method:</strong> <?php echo htmlspecialchars($payment_details['payment_method']); ?></p>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <div class="loading-spinner"></div>
        <a href="<?php echo htmlspecialchars($redirect_url, ENT_QUOTES, 'UTF-8'); ?>" class="btn <?php echo $status === 'success' ? 'success' : ''; ?>" id="returnBtn">
            <i class="fas <?php echo $status === 'success' ? 'fa-list-alt' : 'fa-shopping-cart'; ?>"></i>
            <?php echo $status === 'success' ? 'View Order History' : 'Return to Cart'; ?>
        </a>
        <?php if ($status === 'success'): ?>
            <a href="dashboard.php?page=home" class="btn" id="homeBtn">
                <i class="fas fa-home"></i> Return to Dashboard
            </a>
        <?php endif; ?>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                const returnBtn = document.getElementById('returnBtn');
                if (returnBtn) {
                    window.location.href = returnBtn.href;
                }
            }, <?php echo $redirect_delay; ?>);
            setTimeout(function() {
                const spinner = document.querySelector('.loading-spinner');
                if (spinner) {
                    spinner.style.display = 'none';
                }
            }, 2000);
        });
    </script>
</body>
</html>