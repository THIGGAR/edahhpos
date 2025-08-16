<?php
session_start();
require_once 'db/db_connect.php';

// Initialize variables for error messages and success message
$errors = [];
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize inputs
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);

    // Validate inputs
    if (empty($email)) {
        $errors[] = "Email is required.";
    }

    // Proceed if no validation errors
    if (empty($errors)) {
        try {
            // Prepare query to check if user exists
            $stmt = $conn->prepare("SELECT user_id, first_name, last_name, role FROM users WHERE email = ? AND is_active = 1");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();

                // Generate reset token
                $token = bin2hex(random_bytes(32));
                $expires = date("Y-m-d H:i:s", time() + 3600); // Expires in 1 hour

                // Update user with reset token and expiration
                $update_stmt = $conn->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE user_id = ?");
                $update_stmt->bind_param("ssi", $token, $expires, $user['user_id']);
                $update_stmt->execute();

                // Prepare reset link
                $reset_link = "http://yourdomain.com/reset_password.php?token=" . $token . "&email=" . urlencode($email);

                // Send email
                $subject = "Password Reset for Auntie Eddah POS";
                $body = "Dear " . $user['first_name'] . " " . $user['last_name'] . ",\n\n" .
                        "You requested a password reset for your account with role: " . $user['role'] . ".\n" .
                        "Click the following link to reset your password:\n" . $reset_link . "\n\n" .
                        "This link will expire in 1 hour. If you did not request this, please ignore this email.\n\n" .
                        "Best regards,\nAuntie Eddah POS Team";
                $headers = "From: no-reply@yourdomain.com\r\n" .
                           "Reply-To: support@yourdomain.com\r\n" .
                           "X-Mailer: PHP/" . phpversion();

                if (mail($email, $subject, $body, $headers)) {
                    $message = "A password reset link has been sent to your email. Please check your inbox (and spam folder).";
                } else {
                    $errors[] = "Failed to send reset email. Please try again later.";
                }
            } else {
                $errors[] = "No active account found with that email.";
            }
            $stmt->close();
        } catch (Exception $e) {
            $errors[] = "An error occurred: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Auntie Eddah POS - Forgot Password</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css"/>
  <style>
    :root {
      --primary-color: #4a6baf;
      --primary-light: #5c7fc0;
      --secondary-color: #ff6b6b;
      --accent-color: #ffcc00;
      --dark-color: #003366;
      --white: #ffffff;
      --text-color: #333333;
      --success-color: #28a745;
      --error-color: #dc3545;
      --transition: all 0.3s ease;
    }

    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }

    body {
      background: linear-gradient(135deg, #003366 0%, #1a2a4f 100%);
      color: var(--text-color);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px;
    }

    .forgot-container {
      background: var(--white);
      padding: 40px;
      border-radius: 12px;
      box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
      width: 100%;
      max-width: 500px;
      text-align: center;
    }

    .forgot-header h2 {
      font-size: 1.8rem;
      color: var(--dark-color);
      margin-bottom: 10px;
    }

    .forgot-header p {
      color: var(--secondary-color);
      font-size: 1rem;
      margin-bottom: 20px;
    }

    .form-group {
      margin-bottom: 20px;
    }

    .form-group label {
      display: block;
      font-weight: 600;
      color: var(--dark-color);
      margin-bottom: 8px;
      font-size: 1rem;
    }

    .input-group {
      position: relative;
    }

    .input-group input {
      width: 100%;
      padding: 12px 15px 12px 40px;
      border: 2px solid #e1e5eb;
      border-radius: 8px;
      font-size: 1rem;
      transition: var(--transition);
    }

    .input-group input:focus {
      border-color: var(--primary-color);
      box-shadow: 0 0 0 3px rgba(74, 107, 175, 0.2);
      outline: none;
    }

    .input-group i {
      position: absolute;
      left: 12px;
      top: 50%;
      transform: translateY(-50%);
      color: var(--primary-color);
      font-size: 1rem;
    }

    .btn {
      width: 100%;
      padding: 12px;
      font-size: 1.1rem;
      font-weight: 600;
      border: none;
      border-radius: 8px;
      background: var(--primary-color);
      color: var(--white);
      cursor: pointer;
      transition: var(--transition);
    }

    .btn:hover {
      background: var(--primary-light);
      transform: translateY(-2px);
    }

    .btn i {
      margin-right: 8px;
    }

    .back-link {
      margin-top: 20px;
      font-size: 1rem;
    }

    .back-link a {
      color: var(--primary-color);
      text-decoration: none;
      font-weight: 600;
      transition: var(--transition);
    }

    .back-link a:hover {
      color: var(--dark-color);
      text-decoration: underline;
    }

    .error-message, .success-message {
      padding: 12px;
      border-radius: 8px;
      margin-bottom: 20px;
      text-align: left;
    }

    .error-message {
      background: var(--error-color);
      color: var(--white);
    }

    .success-message {
      background: var(--success-color);
      color: var(--white);
    }

    .error-message p, .success-message p {
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 0.9rem;
    }

    .error-message i, .success-message i {
      font-size: 1rem;
    }

    @media (max-width: 576px) {
      .forgot-container {
        padding: 20px;
      }

      .forgot-header h2 {
        font-size: 1.5rem;
      }

      .forgot-header p {
        font-size: 0.9rem;
      }

      .btn {
        font-size: 1rem;
      }
    }
  </style>
</head>
<body>
  <div class="forgot-container">
    <div class="forgot-header">
      <h2>Forgot Your Password?</h2>
      <p>Enter your email to receive a reset link</p>
    </div>

    <?php if (!empty($errors)): ?>
      <div class="error-message">
        <?php foreach ($errors as $error): ?>
          <p><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></p>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if (!empty($message)): ?>
      <div class="success-message">
        <p><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?></p>
      </div>
    <?php endif; ?>

    <form method="POST" action="">
      <div class="form-group">
        <label for="email">Email Address</label>
        <div class="input-group">
          <i class="fas fa-envelope"></i>
          <input type="email" id="email" name="email" placeholder="Enter your email" value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>" required>
        </div>
      </div>
      <button type="submit" class="btn"><i class="fas fa-paper-plane"></i> Send Reset Link</button>
    </form>

    <div class="back-link">
      <p>Remember your password? <a href="login.php">Sign in here</a></p>
    </div>
  </div>
</body>
</html>