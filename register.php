<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start session for potential future use
session_start();

require_once 'db/db_connect.php';

// Initialize variables
$errors = [];
$success_message = '';
$first_name = $last_name = $email = $phone = '';

if (!isset($conn) || $conn->connect_error) {
    $errors[] = "Database connection failed. Please check db_connect.php.";
} else {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Sanitize inputs
        $first_name = filter_var($_POST['first_name'] ?? '', FILTER_SANITIZE_SPECIAL_CHARS);
        $last_name = filter_var($_POST['last_name'] ?? '', FILTER_SANITIZE_SPECIAL_CHARS);
        $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
        $phone = filter_var($_POST['phone'] ?? '', FILTER_SANITIZE_SPECIAL_CHARS);
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $agree_terms = isset($_POST['agree_terms']);

        // Validate inputs
        if (empty($first_name)) {
            $errors[] = "First name is required.";
        }
        if (empty($last_name)) {
            $errors[] = "Last name is required.";
        }
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "A valid email address is required.";
        }
        if (empty($phone)) {
            $errors[] = "Phone number is required.";
        } elseif (!preg_match('/^\+?\d{10,15}$/', $phone)) {
            $errors[] = "Invalid phone number format (e.g., +1234567890).";
        }
        if (empty($password)) {
            $errors[] = "Password is required.";
        } elseif (strlen($password) < 8) {
            $errors[] = "Password must be at least 8 characters long.";
        }
        if ($password !== $confirm_password) {
            $errors[] = "Passwords do not match.";
        }
        if (!$agree_terms) {
            $errors[] = "You must agree to the Terms of Service and Privacy Policy.";
        }

        // Check for duplicate email
        if (empty($errors)) {
            try {
                $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
                if (!$stmt) {
                    $errors[] = "Database query preparation failed: " . $conn->error;
                } else {
                    $stmt->bind_param("s", $email);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    if ($result->num_rows > 0) {
                        $errors[] = "Email already exists.";
                    }
                    $stmt->close();
                }
            } catch (Exception $e) {
                $errors[] = "Database error: " . $e->getMessage();
            }
        }

        // Proceed with registration if no errors
        if (empty($errors)) {
            try {
                // Hash the password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                // Insert user into the database
                $stmt = $conn->prepare("INSERT INTO users (first_name, last_name, email, password, phone) VALUES (?, ?, ?, ?, ?)");
                if (!$stmt) {
                    $errors[] = "Database insert preparation failed: " . $conn->error;
                } else {
                    $stmt->bind_param("sssss", $first_name, $last_name, $email, $hashed_password, $phone);
                    if ($stmt->execute()) {
                        $success_message = "Registration successful! Redirecting to login...";
                        // Redirect to login after 2 seconds
                        header("Refresh: 2; url=login.php");
                        // Ensure no further output
                        exit();
                    } else {
                        $errors[] = "Failed to register: " . $stmt->error;
                    }
                    $stmt->close();
                }
            } catch (Exception $e) {
                $errors[] = "Registration error: " . $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Auntie Eddah POS - Register</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css"/>
  <style>
    :root {
      --primary-color: #4a6baf;
      --primary-light: #5c7fc0;
      --secondary-color: #ff6b6b;
      --accent-color: #ffcc00;
      --dark-color: #003366;
      --light-color: #f0f4f8;
      --white: #ffffff;
      --text-color: #333333;
      --footer-bg: #222222;
      --footer-text: #cccccc;
      --success-color: #28a745;
      --error-color: #dc3545;
      --transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.1);
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
      line-height: 1.6;
      overflow-x: hidden;
      padding: 20px;
    }

    .register-container {
      display: flex;
      width: 100%;
      max-width: 1200px;
      min-height: 700px;
      background: var(--white);
      border-radius: 20px;
      box-shadow: 0 15px 50px rgba(0, 0, 0, 0.3);
      overflow: hidden;
      position: relative;
    }

    .register-banner {
      flex: 1;
      background: linear-gradient(135deg, rgba(0, 51, 102, 0.9), rgba(26, 42, 79, 0.9)), url('https://images.unsplash.com/photo-1607082348824-0a96f2a4b9da?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1740&q=80') center/cover no-repeat;
      color: var(--white);
      padding: 60px 40px;
      display: flex;
      flex-direction: column;
      justify-content: center;
      position: relative;
      overflow: hidden;
    }

    .register-banner::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: radial-gradient(circle at 10% 20%, rgba(255, 204, 0, 0.1) 0%, transparent 20%), radial-gradient(circle at 90% 80%, rgba(255, 107, 107, 0.1) 0%, transparent 20%);
    }

    .banner-content {
      position: relative;
      z-index: 2;
      max-width: 500px;
    }

    .logo {
      display: flex;
      align-items: center;
      gap: 15px;
      margin-bottom: 30px;
    }

    .logo img {
      height: 60px;
      transition: transform 0.3s ease;
    }

    .logo:hover img {
      transform: rotate(5deg);
    }

    .logo h1 {
      font-size: 2rem;
      font-weight: 800;
      background: linear-gradient(to right, var(--white), var(--accent-color));
      -webkit-background-clip: text;
      background-clip: text;
      color: transparent;
    }

    .banner-content h2 {
      font-size: 2.8rem;
      margin-bottom: 20px;
      line-height: 1.2;
    }

    .banner-content p {
      font-size: 1.1rem;
      margin-bottom: 30px;
      opacity: 0.9;
      line-height: 1.8;
    }

    .features {
      list-style: none;
      margin-top: 40px;
    }

    .features li {
      display: flex;
      align-items: flex-start;
      gap: 15px;
      margin-bottom: 20px;
    }

    .features i {
      color: var(--accent-color);
      font-size: 1.5rem;
      min-width: 30px;
    }

    .features h3 {
      font-size: 1.2rem;
      margin-bottom: 5px;
    }

    .features p {
      font-size: 0.95rem;
      opacity: 0.8;
      margin-bottom: 0;
    }

    .register-form-container {
      flex: 1;
      padding: 60px 50px;
      display: flex;
      flex-direction: column;
      justify-content: center;
      background: var(--white);
    }

    .register-header {
      margin-bottom: 40px;
      text-align: center;
    }

    .register-header h2 {
      font-size: 2.2rem;
      color: var(--dark-color);
      margin-bottom: 10px;
    }

    .register-header p {
      color: var(--secondary-color);
      font-size: 1.1rem;
    }

    .register-form {
      width: 100%;
      max-width: 450px;
      margin: 0 auto;
    }

    .form-group {
      margin-bottom: 25px;
    }

    .form-group label {
      display: block;
      margin-bottom: 10px;
      font-weight: 600;
      color: var(--dark-color);
      font-size: 1.1rem;
    }

    .input-group {
      position: relative;
    }

    .input-group input {
      width: 100%;
      padding: 16px 20px 16px 55px;
      border: 2px solid #e1e5eb;
      border-radius: 12px;
      font-size: 1.1rem;
      transition: var(--transition);
      box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
    }

    .input-group input:focus {
      border-color: var(--primary-color);
      box-shadow: 0 0 0 4px rgba(74, 107, 175, 0.2);
      outline: none;
    }

    .input-group i {
      position: absolute;
      left: 20px;
      top: 50%;
      transform: translateY(-50%);
      color: var(--primary-color);
      font-size: 1.2rem;
    }

    .password-toggle {
      position: absolute;
      right: 20px;
      top: 50%;
      transform: translateY(-50%);
      background: none;
      border: none;
      color: var(--secondary-color);
      cursor: pointer;
      font-size: 1.2rem;
    }

    .password-toggle:hover {
      color: var(--primary-color);
    }

    .name-fields {
      display: flex;
      gap: 20px;
      margin-bottom: 25px;
    }

    .name-fields .form-group {
      flex: 1;
    }

    .terms {
      display: flex;
      align-items: flex-start;
      margin-bottom: 25px;
      gap: 10px;
    }

    .terms input {
      margin-top: 5px;
      width: 20px;
      height: 20px;
      cursor: pointer;
    }

    .terms label {
      color: var(--dark-color);
      font-weight: 500;
      cursor: pointer;
      text-align: left;
      font-weight: normal;
    }

    .terms a {
      color: var(--primary-color);
      text-decoration: none;
      font-weight: 600;
      transition: var(--transition);
    }

    .terms a:hover {
      color: var(--dark-color);
      text-decoration: underline;
    }

    .btn {
      display: block;
      width: 100%;
      padding: 18px;
      font-size: 1.2rem;
      font-weight: 700;
      border: none;
      border-radius: 12px;
      background: linear-gradient(to right, var(--primary-color), var(--primary-light));
      color: var(--white);
      text-decoration: none;
      transition: var(--transition);
      box-shadow: 0 8px 20px rgba(74, 107, 175, 0.4);
      cursor: pointer;
      margin: 30px 0 20px;
      position: relative;
      overflow: hidden;
    }

    .btn::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      width: 0;
      height: 100%;
      background: rgba(255, 255, 255, 0.2);
      transition: var(--transition);
    }

    .btn:hover {
      transform: translateY(-5px);
      box-shadow: 0 12px 25px rgba(74, 107, 175, 0.5);
    }

    .btn:hover::before {
      width: 100%;
    }

    .btn i {
      margin-right: 10px;
    }

    .login-link {
      text-align: center;
      color: var(--text-color);
      font-size: 1.1rem;
      margin-top: 20px;
    }

    .login-link a {
      color: var(--primary-color);
      text-decoration: none;
      font-weight: 700;
      transition: var(--transition);
    }

    .login-link a:hover {
      color: var(--dark-color);
      text-decoration: underline;
    }

    .error-message {
      background: var(--error-color);
      color: var(--white);
      padding: 15px 20px;
      border-radius: 10px;
      margin-bottom: 25px;
      text-align: left;
      animation: fadeIn 0.5s ease;
    }

    .error-message p {
      margin-bottom: 5px;
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .error-message p:last-child {
      margin-bottom: 0;
    }

    .error-message i {
      font-size: 1.2rem;
      min-width: 25px;
    }

    .success-message {
      background: var(--success-color);
      color: var(--white);
      padding: 15px 20px;
      border-radius: 10px;
      margin-bottom: 25px;
      text-align: center;
      animation: fadeIn 0.5s ease;
    }

    .success-message p {
      margin: 0;
      font-weight: 500;
    }

    .password-strength {
      height: 8px;
      background: #eee;
      border-radius: 4px;
      margin-top: 8px;
      overflow: hidden;
    }

    .strength-meter {
      height: 100%;
      width: 0;
      transition: width 0.3s ease, background 0.3s ease;
    }

    .weak {
      width: 30%;
      background: #ff5252;
    }

    .medium {
      width: 60%;
      background: #ffb74d;
    }

    .strong {
      width: 100%;
      background: #4caf50;
    }

    .password-rules {
      margin-top: 5px;
      font-size: 0.9rem;
      color: var(--secondary-color);
      text-align: left;
    }

    .password-rules ul {
      padding-left: 20px;
      margin-top: 5px;
    }

    .password-rules li {
      margin-bottom: 3px;
    }

    .rule-valid {
      color: #4caf50;
    }

    .rule-invalid {
      color: #ff5252;
    }

    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(-10px); }
      to { opacity: 1; transform: translateY(0); }
    }

    @keyframes float {
      0%, 100% { transform: translateY(0); }
      50% { transform: translateY(-15px); }
    }

    .floating {
      animation: float 4s ease-in-out infinite;
    }

    /* Responsive Design */
    @media (max-width: 992px) {
      .register-container {
        flex-direction: column;
        min-height: auto;
      }
      
      .register-banner {
        padding: 40px 30px;
      }
      
      .register-form-container {
        padding: 40px 30px;
      }
      
      .banner-content {
        max-width: 100%;
      }
    }

    @media (max-width: 768px) {
      .name-fields {
        flex-direction: column;
        gap: 0;
      }
      
      .register-header h2 {
        font-size: 1.8rem;
      }
      
      .banner-content h2 {
        font-size: 2.2rem;
      }
    }

    @media (max-width: 576px) {
      .register-container {
        min-height: auto;
      }
      
      .register-banner, .register-form-container {
        padding: 30px 20px;
      }
    }
  </style>
</head>
<body>
  <div class="register-container">
    <div class="register-banner">
      <div class="banner-content">
        <div class="logo">
          <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' width='24' height='24'%3E%3Cpath fill='%23ffcc00' d='M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V5h14v14z'/%3E%3Cpath fill='%23ffffff' d='M12 18c3.31 0 6-2.69 6-6s-2.69-6-6-6-6 2.69-6 6 2.69 6 6 6zm-1-6.5v-3c0-.28.22-.5.5-.5s.5.22.5.5v3h1.5c.28 0 .5.22.5.5s-.22.5-.5.5h-4c-.28 0-.5-.22-.5-.5s.22-.5.5-.5H11z'/%3E%3C/svg%3E" alt="Logo">
          <h1>Auntie Eddah POS</h1>
        </div>
        
        <h2>Join Our Business Community</h2>
        <p>Register today to access our powerful retail management tools and grow your business with confidence.</p>
        
        <ul class="features">
          <li>
            <i class="fas fa-chart-line floating"></i>
            <div>
              <h3>Business Insights</h3>
              <p>Make data-driven decisions with real-time analytics</p>
            </div>
          </li>
          <li>
            <i class="fas fa-lock floating"></i>
            <div>
              <h3>Secure Platform</h3>
              <p>Enterprise-grade security protects your business data</p>
            </div>
          </li>
          <li>
            <i class="fas fa-headset floating"></i>
            <div>
              <h3>24/7 Support</h3>
              <p>Get help whenever you need it from our expert team</p>
            </div>
          </li>
        </ul>
      </div>
    </div>
    
    <div class="register-form-container">
      <div class="register-header">
        <h2>Create Your Account</h2>
        <p>Join thousands of retailers using our platform</p>
      </div>

      <?php if (!empty($errors)): ?>
        <div class="error-message">
          <?php foreach ($errors as $error): ?>
            <p><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></p>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <?php if (!empty($success_message)): ?>
        <div class="success-message">
          <p><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?></p>
        </div>
      <?php endif; ?>

      <form class="register-form" method="POST" action="">
        <div class="name-fields">
          <div class="form-group">
            <label for="first-name">First Name</label>
            <div class="input-group">
              <i class="fas fa-user"></i>
              <input type="text" id="first-name" name="first_name" placeholder="Enter your first name" value="<?php echo htmlspecialchars($first_name); ?>" required>
            </div>
          </div>
          <div class="form-group">
            <label for="last-name">Last Name</label>
            <div class="input-group">
              <i class="fas fa-user"></i>
              <input type="text" id="last-name" name="last_name" placeholder="Enter your last name" value="<?php echo htmlspecialchars($last_name); ?>" required>
            </div>
          </div>
        </div>

        <div class="form-group">
          <label for="email">Email Address</label>
          <div class="input-group">
            <i class="fas fa-envelope"></i>
            <input type="email" id="email" name="email" placeholder="Enter your email" value="<?php echo htmlspecialchars($email); ?>" required>
          </div>
        </div>

        <div class="form-group">
          <label for="phone">Phone Number</label>
          <div class="input-group">
            <i class="fas fa-phone"></i>
            <input type="tel" id="phone" name="phone" placeholder="Enter your phone number" value="<?php echo htmlspecialchars($phone); ?>" required>
          </div>
        </div>

        <div class="form-group">
          <label for="password">Password</label>
          <div class="input-group">
            <i class="fas fa-lock"></i>
            <input type="password" id="password" name="password" placeholder="Create a password" required>
            <button type="button" class="password-toggle" id="passwordToggle">
              <i class="fas fa-eye"></i>
            </button>
          </div>
          <div class="password-strength">
            <div class="strength-meter" id="strength-meter"></div>
          </div>
          <div class="password-rules">
            <p>Password must contain:</p>
            <ul>
              <li id="length-rule" class="rule-invalid">At least 8 characters</li>
              <li id="uppercase-rule" class="rule-invalid">One uppercase letter</li>
              <li id="number-rule" class="rule-invalid">One number</li>
              <li id="special-rule" class="rule-invalid">One special character</li>
            </ul>
          </div>
        </div>

        <div class="form-group">
          <label for="confirm-password">Confirm Password</label>
          <div class="input-group">
            <i class="fas fa-lock"></i>
            <input type="password" id="confirm-password" name="confirm_password" placeholder="Confirm your password" required>
          </div>
        </div>

        <div class="terms">
          <input type="checkbox" id="agree-terms" name="agree_terms" <?php echo isset($_POST['agree_terms']) ? 'checked' : ''; ?> required>
          <label for="agree-terms">I agree to the <a href="#">Terms of Service</a> and <a href="#">Privacy Policy</a></label>
        </div>

        <button type="submit" class="btn"><i class="fas fa-user-plus"></i> Create Account</button>
      </form>

      <div class="login-link">
        <p>Already have an account? <a href="login.php">Login here</a></p>
      </div>
    </div>
  </div>

  <script>
    // Password visibility toggle
    const passwordToggle = document.getElementById('passwordToggle');
    const passwordInput = document.getElementById('password');
    
    passwordToggle.addEventListener('click', function() {
      const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
      passwordInput.setAttribute('type', type);
      
      // Toggle eye icon
      if (type === 'password') {
        this.innerHTML = '<i class="fas fa-eye"></i>';
      } else {
        this.innerHTML = '<i class="fas fa-eye-slash"></i>';
      }
    });
    
    // Password strength indicator
    const passwordInput = document.getElementById('password');
    const strengthMeter = document.getElementById('strength-meter');
    const lengthRule = document.getElementById('length-rule');
    const uppercaseRule = document.getElementById('uppercase-rule');
    const numberRule = document.getElementById('number-rule');
    const specialRule = document.getElementById('special-rule');

    passwordInput.addEventListener('input', function() {
      const password = this.value;
      let strength = 0;
      
      // Check for length
      if (password.length >= 8) {
        strength += 1;
        lengthRule.classList.remove('rule-invalid');
        lengthRule.classList.add('rule-valid');
      } else {
        lengthRule.classList.remove('rule-valid');
        lengthRule.classList.add('rule-invalid');
      }
      
      // Check for uppercase letters
      if (/[A-Z]/.test(password)) {
        strength += 1;
        uppercaseRule.classList.remove('rule-invalid');
        uppercaseRule.classList.add('rule-valid');
      } else {
        uppercaseRule.classList.remove('rule-valid');
        uppercaseRule.classList.add('rule-invalid');
      }
      
      // Check for numbers
      if (/[0-9]/.test(password)) {
        strength += 1;
        numberRule.classList.remove('rule-invalid');
        numberRule.classList.add('rule-valid');
      } else {
        numberRule.classList.remove('rule-valid');
        numberRule.classList.add('rule-invalid');
      }
      
      // Check for special characters
      if (/[^A-Za-z0-9]/.test(password)) {
        strength += 1;
        specialRule.classList.remove('rule-invalid');
        specialRule.classList.add('rule-valid');
      } else {
        specialRule.classList.remove('rule-valid');
        specialRule.classList.add('rule-invalid');
      }
      
      // Update strength meter
      strengthMeter.className = 'strength-meter';
      if (password.length > 0) {
        if (strength <= 1) {
          strengthMeter.classList.add('weak');
        } else if (strength <= 3) {
          strengthMeter.classList.add('medium');
        } else {
          strengthMeter.classList.add('strong');
        }
      } else {
        // Reset rules if password is empty
        lengthRule.classList.remove('rule-valid');
        lengthRule.classList.add('rule-invalid');
        uppercaseRule.classList.remove('rule-valid');
        uppercaseRule.classList.add('rule-invalid');
        numberRule.classList.remove('rule-valid');
        numberRule.classList.add('rule-invalid');
        specialRule.classList.remove('rule-valid');
        specialRule.classList.add('rule-invalid');
      }
    });

    // Form validation for password match
    const form = document.querySelector('.register-form');
    const confirmPasswordInput = document.getElementById('confirm-password');

    form.addEventListener('submit', function(e) {
      if (passwordInput.value !== confirmPasswordInput.value) {
        e.preventDefault();
        alert('Passwords do not match!');
        confirmPasswordInput.focus();
      }
    });
    
    // Add animation to button on hover
    const registerBtn = document.querySelector('.btn');
    registerBtn.addEventListener('mouseenter', function() {
      this.style.transform = 'translateY(-5px)';
    });
    
    registerBtn.addEventListener('mouseleave', function() {
      this.style.transform = 'translateY(0)';
    });
    
    // Add error animation if there are errors
    if (document.querySelector('.error-message')) {
      const errorMessage = document.querySelector('.error-message');
      errorMessage.style.animation = 'fadeIn 0.5s ease';
    }
  </script>
</body>
</html>