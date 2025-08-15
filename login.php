<?php
session_start();
require_once 'db/db_connect.php';

// Initialize variables for error messages
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize inputs
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];

    // Validate inputs
    if (empty($email)) {
        $errors[] = "Email is required.";
    }
    if (empty($password)) {
        $errors[] = "Password is required.";
    }

    // Proceed if no validation errors
    if (empty($errors)) {
        try {
            // Prepare query to check if user exists and fetch role, names
            $stmt = $conn->prepare("SELECT user_id, first_name, last_name, email, password, role FROM users WHERE email = ? AND is_active = 1");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();

                // Verify password
                if (password_verify($password, $user['password'])) {
                    // Set session variables
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
                    $_SESSION['role'] = $user['role'];

                    // Handle "Remember Me" functionality
                    if (isset($_POST['remember']) && $_POST['remember'] === 'on') {
                        $token = bin2hex(random_bytes(16));
                        setcookie('remember_token', $token, time() + (30 * 24 * 60 * 60), "/");
                        $stmt = $conn->prepare("UPDATE users SET remember_token = ? WHERE user_id = ?");
                        $stmt->bind_param("si", $token, $user['user_id']);
                        $stmt->execute();
                    }

                    // Role-based redirection
                    switch ($user['role']) {
                        case 'customer':
                            header("Location: customer/dashboard.php");
                            break;
                        case 'admin':
                            header("Location: admin/dashboard.php");
                            break;
                        case 'shop_manager':
                            header("Location: shopmanager/dashboard.php");
                            break;
                        case 'cashier':
                            header("Location: cashier/dashboard.php");
                            break;
                        case 'inventory_manager':
                            header("Location: inventory/dashboard.php");
                            break;
                        case 'supplier':
                            header("Location: suplier/index.php");
                            break;
                        default:
                            $errors[] = "Invalid user role.";
                            break;
                    }
                    exit();
                } else {
                    $errors[] = "Invalid password.";
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
  <title>Auntie Eddah POS - Login</title>
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

    .login-container {
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

    .login-banner {
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

    .login-banner::before {
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

    .login-form-container {
      flex: 1;
      padding: 60px 50px;
      display: flex;
      flex-direction: column;
      justify-content: center;
      background: var(--white);
    }

    .login-header {
      margin-bottom: 40px;
      text-align: center;
    }

    .login-header h2 {
      font-size: 2.2rem;
      color: var(--dark-color);
      margin-bottom: 10px;
    }

    .login-header p {
      color: var(--secondary-color);
      font-size: 1.1rem;
    }

    .login-form {
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

    .remember-forgot {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 25px;
    }

    .remember-me {
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .remember-me input {
      width: 20px;
      height: 20px;
      cursor: pointer;
    }

    .remember-me label {
      color: var(--dark-color);
      font-weight: 500;
      cursor: pointer;
    }

    .forgot-password a {
      color: var(--primary-color);
      text-decoration: none;
      font-weight: 600;
      transition: var(--transition);
    }

    .forgot-password a:hover {
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

    .register-link {
      text-align: center;
      color: var(--text-color);
      font-size: 1.1rem;
      margin-top: 20px;
    }

    .register-link a {
      color: var(--primary-color);
      text-decoration: none;
      font-weight: 700;
      transition: var(--transition);
    }

    .register-link a:hover {
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
      .login-container {
        flex-direction: column;
        min-height: auto;
      }
      
      .login-banner {
        padding: 40px 30px;
      }
      
      .login-form-container {
        padding: 40px 30px;
      }
      
      .banner-content {
        max-width: 100%;
      }
    }

    @media (max-width: 576px) {
      .remember-forgot {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
      }
      
      .forgot-password {
        align-self: flex-end;
      }
      
      .login-header h2 {
        font-size: 1.8rem;
      }
      
      .banner-content h2 {
        font-size: 2.2rem;
      }
    }
  </style>
</head>
<body>
  <div class="login-container">
    <div class="login-banner">
      <div class="banner-content">
        <div class="logo">
          <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' width='24' height='24'%3E%3Cpath fill='%23ffcc00' d='M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V5h14v14z'/%3E%3Cpath fill='%23ffffff' d='M12 18c3.31 0 6-2.69 6-6s-2.69-6-6-6-6 2.69-6 6 2.69 6 6 6zm-1-6.5v-3c0-.28.22-.5.5-.5s.5.22.5.5v3h1.5c.28 0 .5.22.5.5s-.22.5-.5.5h-4c-.28 0-.5-.22-.5-.5s.22-.5.5-.5H11z'/%3E%3C/svg%3E" alt="Logo">
          <h1>Auntie Eddah POS</h1>
        </div>
        
        <h2>Welcome to Your Retail Management Solution</h2>
        <p>Streamline your operations, manage inventory, and grow your business with our powerful POS system designed for modern retailers.</p>
        
        <ul class="features">
          <li>
            <i class="fas fa-rocket floating"></i>
            <div>
              <h3>Fast & Efficient</h3>
              <p>Process transactions in seconds with our intuitive interface</p>
            </div>
          </li>
          <li>
            <i class="fas fa-shield-alt floating"></i>
            <div>
              <h3>Secure & Reliable</h3>
              <p>Enterprise-grade security to protect your business data</p>
            </div>
          </li>
          <li>
            <i class="fas fa-chart-line floating"></i>
            <div>
              <h3>Powerful Analytics</h3>
              <p>Make data-driven decisions with real-time business insights</p>
            </div>
          </li>
        </ul>
      </div>
    </div>
    
    <div class="login-form-container">
      <div class="login-header">
        <h2>Sign In to Your Account</h2>
        <p>Enter your credentials to access the POS system</p>
      </div>

      <?php if (!empty($errors)): ?>
        <div class="error-message">
          <?php foreach ($errors as $error): ?>
            <p><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></p>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <form class="login-form" method="POST" action="">
        <div class="form-group">
          <label for="email">Email Address</label>
          <div class="input-group">
            <i class="fas fa-envelope"></i>
            <input type="email" id="email" name="email" placeholder="Enter your email" value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>" required>
          </div>
        </div>

        <div class="form-group">
          <label for="password">Password</label>
          <div class="input-group">
            <i class="fas fa-lock"></i>
            <input type="password" id="password" name="password" placeholder="Enter your password" required>
            <button type="button" class="password-toggle" id="passwordToggle">
              <i class="fas fa-eye"></i>
            </button>
          </div>
        </div>

        <div class="remember-forgot">
          <div class="remember-me">
            <input type="checkbox" id="remember" name="remember">
            <label for="remember">Remember me</label>
          </div>
          <div class="forgot-password">
            <a href="forgot_password.php">Forgot password?</a>
          </div>
        </div>

        <button type="submit" class="btn"><i class="fas fa-sign-in-alt"></i> Login to Dashboard</button>
      </form>

      <div class="register-link">
        <p>Don't have an account? <a href="register.php">Create one now</a></p>
      </div>
    </div>
  </div>

  <script>
    // Toggle password visibility
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
    
    // Add focus effect to inputs
    const inputs = document.querySelectorAll('input');
    inputs.forEach(input => {
      input.addEventListener('focus', function() {
        this.parentElement.style.transform = 'translateY(-3px)';
      });
      
      input.addEventListener('blur', function() {
        this.parentElement.style.transform = 'translateY(0)';
      });
    });
    
    // Add animation to button on hover
    const loginBtn = document.querySelector('.btn');
    loginBtn.addEventListener('mouseenter', function() {
      this.style.transform = 'translateY(-5px)';
    });
    
    loginBtn.addEventListener('mouseleave', function() {
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