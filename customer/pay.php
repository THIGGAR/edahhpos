<?php
session_start();

require_once 'db_connect.php';
$config = require_once '../config.php';

// Validate session and CSRF token
if (!isset($_SESSION['transaction_ref']) || !isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token'] || !isset($_SESSION['cart_data'])) {
    error_log("Invalid session or CSRF token in pay.php for user {$_SESSION['user_id']}");
    header("Location: dashboard.php?page=cart&error=Invalid%20session%20or%20CSRF%20token");
    exit();
}

$transaction_ref = $_SESSION['transaction_ref'];

// Fetch payment details
$sql = "SELECT * FROM payments WHERE transaction_ref = ?";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    error_log("Failed to prepare payment query in pay.php: " . $conn->error);
    header("Location: dashboard.php?page=cart&error=Database%20error");
    exit();
}
$stmt->bind_param("s", $transaction_ref);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    error_log("No payment found for transaction_ref: $transaction_ref");
    header("Location: dashboard.php?page=cart&error=No%20payment%20found");
    exit();
}

$payment_data = $result->fetch_assoc();
$stmt->close();

// Validate payment data
if (empty($payment_data['email']) || empty($payment_data['firstname']) || empty($payment_data['surname']) || $payment_data['amount'] <= 0) {
    error_log("Invalid payment data: " . json_encode($payment_data));
    header("Location: dashboard.php?page=cart&error=Invalid%20payment%20data");
    exit();
}

// Validate PayChangu configuration
if (!isset($config['paychangu']['public_key']) || !isset($config['paychangu']['callback_url']) || !isset($config['paychangu']['return_url'])) {
    error_log("PayChangu configuration incomplete: " . json_encode($config['paychangu']));
    header("Location: dashboard.php?page=cart&error=Payment%20configuration%20error");
    exit();
}

// Debug session and payment data
error_log("pay.php session data: " . json_encode($_SESSION['cart_data']));
error_log("pay.php payment data: " . json_encode($payment_data));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Payment - EDAHHPOS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://in.paychangu.com/js/popup.js"></script>
    <style>
        /* === CSS Variables - Consistent with main POS system === */
        :root {
            /* User-specified color scheme */
            --primary-color: #4a6baf;
            --secondary-color: #ff6b6b;
            --accent-color: #ffcc00;
            --dark-color: #003366;
            --light-color: #f0f4f8;
            --white: #ffffff;
            --text-color: #333333;
            --footer-bg: #1a2a4f;
            --card-bg: #ffffff;
            --transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.1);
            --shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            --shadow-hover: 0 15px 40px rgba(0, 0, 0, 0.15);
            
            /* Additional modern design variables */
            --primary-hover: #3b5490;
            --secondary-hover: #e55555;
            --accent-hover: #e6b800;
            --success-color: #28a745;
            --danger-color: #dc3545;
            --warning-color: #ffc107;
            --info-color: #17a2b8;
            --border-color: #e1e5eb;
            --border-radius: 1rem;
            --border-radius-sm: 0.5rem;
            --border-radius-lg: 1.5rem;
            --spacing-xs: 0.25rem;
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
        }

        /* === Base Styles === */
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

        /* === Background Image === */
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

        /* === Animations === */
        @keyframes fadeInUp {
            from { 
                opacity: 0; 
                transform: translateY(30px); 
            }
            to { 
                opacity: 1; 
                transform: translateY(0); 
            }
        }

        @keyframes fadeInDown {
            from { 
                opacity: 0; 
                transform: translateY(-30px); 
            }
            to { 
                opacity: 1; 
                transform: translateY(0); 
            }
        }

        @keyframes scaleIn {
            from { 
                opacity: 0; 
                transform: scale(0.9); 
            }
            to { 
                opacity: 1; 
                transform: scale(1); 
            }
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        @keyframes ripple {
            to {
                transform: scale(4);
                opacity: 0;
            }
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }

        /* === Payment Container === */
        .payment-container {
            max-width: 700px;
            margin: 3rem auto;
            background: var(--glass-bg);
            backdrop-filter: var(--backdrop-blur);
            border: 1px solid var(--glass-border);
            padding: var(--spacing-xl);
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow);
            animation: fadeInUp 0.8s ease;
            position: relative;
            overflow: hidden;
        }

        .payment-container::before {
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

        /* === Payment Title === */
        .payment-title {
            text-align: center;
            font-size: var(--font-size-xxl);
            font-weight: 700;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: var(--spacing-xl);
            animation: fadeInDown 0.6s ease;
            letter-spacing: -0.025em;
        }

        /* === Fee Structure === */
        .fee-structure {
            margin-bottom: var(--spacing-xl);
            animation: scaleIn 0.8s ease;
        }

        .fee-structure table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            background: var(--glass-bg);
            backdrop-filter: var(--backdrop-blur);
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--shadow);
            border: 1px solid var(--glass-border);
        }

        .fee-structure th,
        .fee-structure td {
            padding: var(--spacing-lg);
            text-align: left;
            border-bottom: 1px solid var(--border-color);
            transition: var(--transition);
        }

        .fee-structure th {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-hover));
            color: var(--white);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-size: var(--font-size-base);
        }

        .fee-structure td {
            background: var(--glass-bg);
            backdrop-filter: var(--backdrop-blur);
            font-weight: 600;
            font-size: var(--font-size-lg);
        }

        .fee-structure tr:hover td {
            background: rgba(74, 107, 175, 0.1);
            transform: scale(1.02);
        }

        /* === Terms Section === */
        .terms {
            margin-top: var(--spacing-xl);
            font-size: var(--font-size-base);
            line-height: 1.6;
            animation: fadeInUp 1s ease;
        }

        .terms label {
            display: flex;
            align-items: flex-start;
            gap: var(--spacing-md);
            cursor: pointer;
            padding: var(--spacing-lg);
            background: var(--glass-bg);
            backdrop-filter: var(--backdrop-blur);
            border: 2px solid var(--border-color);
            border-radius: var(--border-radius);
            transition: var(--transition);
            margin-bottom: var(--spacing-lg);
            position: relative;
            overflow: hidden;
        }

        .terms label::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.1), transparent);
            transition: left 0.5s;
        }

        .terms label:hover {
            border-color: var(--primary-color);
            box-shadow: var(--shadow);
            transform: translateY(-2px);
        }

        .terms label:hover::before {
            left: 100%;
        }

        .terms input[type="checkbox"] {
            width: 20px;
            height: 20px;
            accent-color: var(--primary-color);
            cursor: pointer;
            margin-top: 2px;
            flex-shrink: 0;
        }

        .terms a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600;
            transition: var(--transition);
            position: relative;
        }

        .terms a::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 0;
            height: 2px;
            background: var(--primary-color);
            transition: width 0.3s ease;
        }

        .terms a:hover {
            color: var(--secondary-color);
        }

        .terms a:hover::after {
            width: 100%;
        }

        /* === Pay Button === */
        .pay-btn {
            display: block;
            width: 100%;
            background: linear-gradient(135deg, var(--accent-color), var(--accent-hover));
            background-size: 200% 200%;
            color: var(--dark-color);
            text-align: center;
            padding: var(--spacing-lg) var(--spacing-xl);
            font-size: var(--font-size-lg);
            font-weight: 700;
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
            box-shadow: var(--shadow);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: var(--spacing-lg);
        }

        .pay-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            transition: left 0.5s;
        }

        .pay-btn:hover:not(.disabled) {
            background: linear-gradient(135deg, var(--secondary-color), var(--secondary-hover));
            color: var(--white);
            transform: translateY(-3px);
            box-shadow: var(--shadow-hover);
            animation: gradientShift 2s ease infinite;
        }

        .pay-btn:hover:not(.disabled)::before {
            left: 100%;
        }

        .pay-btn:active:not(.disabled) {
            transform: translateY(-1px);
        }

        .pay-btn.disabled {
            opacity: 0.6;
            pointer-events: none;
            background: linear-gradient(135deg, #ccc, #999);
            color: #666;
            cursor: not-allowed;
        }

        /* === Error Styles === */
        .error {
            color: var(--danger-color);
            text-align: center;
            margin-bottom: var(--spacing-lg);
            padding: var(--spacing-md);
            background: rgba(220, 53, 69, 0.1);
            border: 1px solid var(--danger-color);
            border-radius: var(--border-radius);
            font-weight: 600;
            animation: shake 0.5s ease-in-out;
        }

        /* === Note Styles === */
        .note {
            margin-top: var(--spacing-lg);
            font-size: var(--font-size-base);
            color: var(--text-color);
            text-align: center;
            padding: var(--spacing-md);
            background: var(--glass-bg);
            backdrop-filter: var(--backdrop-blur);
            border-radius: var(--border-radius);
            border: 1px solid var(--glass-border);
            opacity: 0.8;
            animation: fadeInUp 1.2s ease;
        }

        /* === Security Badge === */
        .security-badge {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: var(--spacing-sm);
            margin-top: var(--spacing-lg);
            padding: var(--spacing-sm);
            background: var(--glass-bg);
            backdrop-filter: var(--backdrop-blur);
            border-radius: var(--border-radius);
            border: 1px solid var(--glass-border);
            color: var(--success-color);
            font-size: 0.9rem;
            font-weight: 600;
            animation: fadeInUp 1.4s ease;
        }

        .security-badge i {
            color: var(--success-color);
            animation: pulse 2s infinite;
        }

        /* === Loading Spinner === */
        .loading-spinner {
            display: none;
            width: 40px;
            height: 40px;
            border: 4px solid var(--border-color);
            border-top: 4px solid var(--primary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto;
        }

        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        /* === Responsive Design === */
        @media (max-width: 768px) {
            body {
                padding: var(--spacing-md);
            }
            
            .payment-container {
                margin: 1rem auto;
                padding: var(--spacing-lg);
            }
            
            .payment-title {
                font-size: var(--font-size-xl);
            }
            
            .fee-structure th,
            .fee-structure td {
                padding: var(--spacing-md);
                font-size: var(--font-size-base);
            }
            
            .terms label {
                padding: var(--spacing-md);
                flex-direction: column;
                gap: var(--spacing-sm);
            }
            
            .pay-btn {
                padding: var(--spacing-md);
                font-size: var(--font-size-base);
            }
        }

        @media (max-width: 480px) {
            .payment-container {
                padding: var(--spacing-md);
            }
            
            .fee-structure th,
            .fee-structure td {
                padding: var(--spacing-sm);
                font-size: 0.9rem;
            }
            
            .terms {
                font-size: 0.9rem;
            }
        }

        /* === Dark Mode Support === */
        @media (prefers-color-scheme: dark) {
            :root {
                --text-color: #e2e8f0;
                --light-color: #1a202c;
                --card-bg: #2d3748;
                --border-color: #4a5568;
                --glass-bg: rgba(45, 55, 72, 0.1);
                --glass-border: rgba(255, 255, 255, 0.1);
            }
            
            body {
                background: linear-gradient(135deg, #1a202c 0%, #2d3748 100%);
            }
        }

        /* === Accessibility === */
        @media (prefers-reduced-motion: reduce) {
            *, *:before, *:after {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
        }

        /* === Focus Styles === */
        *:focus {
            outline: 2px solid var(--accent-color);
            outline-offset: 2px;
        }

        /* === Print Styles === */
        @media print {
            body::before {
                display: none;
            }
            
            .payment-container {
                box-shadow: none;
                border: 1px solid #ccc;
            }
            
            .pay-btn {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="payment-container">
        <h1 class="payment-title">Product Fee Payment</h1>
        <?php if (isset($_GET['error'])): ?>
            <p class="error"><?php echo htmlspecialchars($_GET['error']); ?></p>
        <?php endif; ?>
        <div class="fee-structure">
            <table>
                <thead>
                    <tr>
                        <th>Description</th>
                        <th>Amount (MWK)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Product Fees</td>
                        <td><?php echo number_format($payment_data['amount'], 2); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="terms" id="wrapper">
            <form method="POST" id="paymentForm">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <label>
                    <input type="checkbox" id="agreeTerms" required />
                    I agree to the <a href="terms.html" target="_blank">Terms and Conditions</a> of EDAHHPOS. I understand that fees paid are non-refundable once the transaction is processed.
                </label>
                <button type="button" class="pay-btn disabled" id="payNowBtn">
                    <i class="fas fa-credit-card"></i> Pay Now
                </button>
                <div class="security-badge">
                    <i class="fas fa-shield-alt"></i>
                    <span>Secure Payment Gateway</span>
                </div>
                <p class="note">You will be redirected to our secure payment gateway to complete the transaction.</p>
            </form>
        </div>
    </div>
    <script>
        // Wait for DOM and scripts to load
        document.addEventListener('DOMContentLoaded', function() {
            // Check if PayChangu SDK is loaded
            if (typeof PaychanguCheckout === 'undefined') {
                console.error("PayChangu SDK failed to load from https://in.paychangu.com/js/popup.js");
                alert("Payment system is unavailable. Please try again later or contact support.");
                document.getElementById('payNowBtn').disabled = true;
                document.getElementById('payNowBtn').classList.add('disabled');
                return;
            }
            console.log("PayChangu SDK loaded successfully");

            // Customer info for PayChangu
            const customerInfo = {
                email: <?php echo json_encode($payment_data['email'], JSON_HEX_QUOT | JSON_HEX_APOS); ?>,
                first_name: <?php echo json_encode($payment_data['firstname'], JSON_HEX_QUOT | JSON_HEX_APOS); ?>,
                last_name: <?php echo json_encode($payment_data['surname'], JSON_HEX_QUOT | JSON_HEX_APOS); ?>,
                tx_ref: <?php echo json_encode($transaction_ref, JSON_HEX_QUOT | JSON_HEX_APOS); ?>
            };

            // Validate customer info
            if (!customerInfo.email || !customerInfo.first_name || !customerInfo.last_name || !customerInfo.tx_ref) {
                console.error("Invalid customer info:", customerInfo);
                alert("Payment data is incomplete. Please return to the cart and try again.");
                document.getElementById('payNowBtn').disabled = true;
                document.getElementById('payNowBtn').classList.add('disabled');
                return;
            }

            // Enable Pay Now button when terms are checked
            const payNowBtn = document.getElementById('payNowBtn');
            const agreeTerms = document.getElementById('agreeTerms');
            agreeTerms.addEventListener('change', function() {
                payNowBtn.disabled = !this.checked;
                payNowBtn.classList.toggle('disabled', !this.checked);
                console.log("Terms checkbox state:", this.checked);
            });

            // Initialize button state
            payNowBtn.disabled = !agreeTerms.checked;
            payNowBtn.classList.toggle('disabled', !agreeTerms.checked);

            // Pay Now button click handler
            payNowBtn.addEventListener('click', function() {
                if (!agreeTerms.checked) {
                    console.error("Terms not agreed");
                    alert("Please agree to the terms and conditions.");
                    return;
                }

                console.log("Initiating PayChangu payment with customerInfo:", customerInfo);
                try {
                    PaychanguCheckout({
                        public_key: <?php echo json_encode($config['paychangu']['public_key'], JSON_HEX_QUOT | JSON_HEX_APOS); ?>,
                        tx_ref: customerInfo.tx_ref,
                        amount: <?php echo json_encode($payment_data['amount']); ?>,
                        currency: "MWK",
                        callback_url: <?php echo json_encode($config['paychangu']['callback_url'] ?? 'http://localhost/EDAHHPOS/customer/callback.php', JSON_HEX_QUOT | JSON_HEX_APOS); ?>,
                        return_url: <?php echo json_encode($config['paychangu']['return_url'] ?? 'http://localhost/EDAHHPOS/customer/return.php', JSON_HEX_QUOT | JSON_HEX_APOS); ?>,
                        customer: {
                            email: customerInfo.email,
                            first_name: customerInfo.first_name,
                            last_name: customerInfo.last_name
                        },
                        customization: {
                            title: "EDDAHPOS Product Payment",
                            description: "Payment for products in cart"
                        },
                        meta: {
                            session_id: <?php echo json_encode(session_id(), JSON_HEX_QUOT | JSON_HEX_APOS); ?>
                        }
                    });
                    console.log("PayChangu popup initiated");
                } catch (error) {
                    console.error("PayChangu error:", error);
                    alert("Failed to initiate payment: " + error.message);
                }
            });
        });
    </script>
</body>
</html>
<?php
$conn->close();
?>