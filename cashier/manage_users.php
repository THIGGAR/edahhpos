<?php
session_start();

require_once 'db_connect.php';
require_once 'functions.php';
require_once 'config.php';

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Check authentication and role (only admins can manage users)
if (!validateUserSession() || !hasRole('admin')) {
    error_log("Authentication failed or insufficient role: redirecting to login.php");
    header("Location: login.php");
    exit;
}

$message = '';
$message_type = '';

// Handle form submissions for user management
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = sanitizeInput($_POST['csrf_token']);
    
    if (!validateCsrfToken($csrf_token)) {
        $message = "Invalid security token. Please try again.";
        $message_type = "danger";
        error_log("CSRF token validation failed for user management");
    } else {
        if (isset($_POST['add_user'])) {
            $first_name = sanitizeInput($_POST['first_name']);
            $last_name = sanitizeInput($_POST['last_name']);
            $email = sanitizeInput($_POST['email']);
            $password = $_POST['password']; // Password will be hashed in the function
            $role = sanitizeInput($_POST['role']);

            if (addUser($first_name, $last_name, $email, $password, $role)) {
                $message = "User added successfully!";
                $message_type = "success";
                logActivity($_SESSION['user_id'], "Added new user: $email", 'user_management');
            } else {
                $message = "Failed to add user. Please try again.";
                $message_type = "danger";
            }
        } elseif (isset($_POST['update_user'])) {
            $user_id = sanitizeInput($_POST['user_id'], 'int');
            $first_name = sanitizeInput($_POST['first_name']);
            $last_name = sanitizeInput($_POST['last_name']);
            $email = sanitizeInput($_POST['email']);
            $role = sanitizeInput($_POST['role']);

            if (updateUser($user_id, $first_name, $last_name, $email, $role)) {
                $message = "User updated successfully!";
                $message_type = "success";
                logActivity($_SESSION['user_id'], "Updated user ID: $user_id", 'user_management');
            } else {
                $message = "Failed to update user. Please try again.";
                $message_type = "danger";
            }
        } elseif (isset($_POST['delete_user'])) {
            $user_id = sanitizeInput($_POST['user_id'], 'int');

            if (deleteUser($user_id)) {
                $message = "User deleted successfully!";
                $message_type = "success";
                logActivity($_SESSION['user_id'], "Deleted user ID: $user_id", 'user_management');
            } else {
                $message = "Failed to delete user. Please try again.";
                $message_type = "danger";
            }
        }
    }
}

$users = getAllUsers();

// Get current cashier details for display
$current_cashier = null;
if (isset($_SESSION['user_id'])) {
    $current_cashier = getUserById($_SESSION['user_id']);
    if ($current_cashier) {
        $_SESSION['user_name'] = $current_cashier['first_name'] . ' ' . $current_cashier['last_name'];
        $_SESSION['user_email'] = $current_cashier['email'];
    } else {
        error_log("Current user ID {$_SESSION['user_id']} not found in database.");
        $_SESSION['user_email'] = 'admin@example.com';
        $_SESSION['user_name'] = 'Admin';
    }
} else {
    $_SESSION['user_email'] = 'admin@example.com';
    $_SESSION['user_name'] = 'Admin';
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Auntie Eddah POS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header>
        <div class="logo">
            <i class="fas fa-shopping-cart"></i>
            <span>Auntie Eddah POS</span>
        </div>
        <div class="user-profile">
            <i class="fas fa-user"></i>
            <span><?php echo htmlspecialchars($current_cashier['first_name'] . ' ' . $current_cashier['last_name']); ?> (<?php echo htmlspecialchars($current_cashier['role']); ?>)</span>
            <small><?php echo htmlspecialchars($current_cashier['email']); ?></small>
            <a href="logout.php" class="btn btn-danger">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </header>

    <nav class="sidebar">
        <div class="sidebar-nav">
            <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <a href="scan_barcode.php"><i class="fas fa-barcode"></i> Scan Barcode</a>
            <a href="pending_orders.php"><i class="fas fa-hourglass-half"></i> Pending Orders</a>
            <a href="completed_orders.php"><i class="fas fa-check-circle"></i> Completed Orders</a>
            <a href="sales_report.php"><i class="fas fa-chart-bar"></i> Sales Report</a>
            <a href="manage_users.php" class="active"><i class="fas fa-users"></i> Manage Users</a>
        </div>
    </nav>

    <main class="main-content">
        <div class="content">
            <div class="notification-container">
                <?php if (!empty($message)): ?>
                    <div class="notification <?php echo $message_type; ?>">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>
            </div>

            <section class="section user-management-section active">
                <h2><i class="fas fa-users"></i> Manage Users</h2>

                <div class="card">
                    <div class="card-header">
                        <h3>Add New User</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                            <div class="form-group">
                                <label for="first_name">First Name</label>
                                <input type="text" id="first_name" name="first_name" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label for="last_name">Last Name</label>
                                <input type="text" id="last_name" name="last_name" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label for="email">Email</label>
                                <input type="email" id="email" name="email" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label for="password">Password</label>
                                <input type="password" id="password" name="password" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label for="role">Role</label>
                                <select id="role" name="role" class="form-control" required>
                                    <option value="cashier">Cashier</option>
                                    <option value="admin">Admin</option>
                                </select>
                            </div>
                            <button type="submit" name="add_user" class="btn btn-primary"><i class="fas fa-user-plus"></i> Add User</button>
                        </form>
                    </div>
                </div>

                <div class="card mt-4">
                    <div class="card-header">
                        <h3>Existing Users</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>Created At</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($users)): ?>
                                        <tr><td colspan="6" style="text-align: center;">No users found.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($users as $user): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($user['user_id']); ?></td>
                                                <td><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></td>
                                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                                <td><?php echo htmlspecialchars(ucfirst($user['role'])); ?></td>
                                                <td><?php echo htmlspecialchars($user['created_at']); ?></td>
                                                <td>
                                                    <button class="btn btn-info btn-sm edit-user-btn" 
                                                            data-user-id="<?php echo $user['user_id']; ?>"
                                                            data-first-name="<?php echo htmlspecialchars($user['first_name']); ?>"
                                                            data-last-name="<?php echo htmlspecialchars($user['last_name']); ?>"
                                                            data-email="<?php echo htmlspecialchars($user['email']); ?>"
                                                            data-role="<?php echo htmlspecialchars($user['role']); ?>">
                                                        <i class="fas fa-edit"></i> Edit
                                                    </button>
                                                    <form method="POST" style="display:inline-block;" onsubmit="return confirm('Are you sure you want to delete this user?');">
                                                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                                        <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                                        <button type="submit" name="delete_user" class="btn btn-danger btn-sm">
                                                            <i class="fas fa-trash"></i> Delete
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Edit User Modal -->
            <div id="editUserModal" class="modal">
                <div class="modal-content">
                    <span class="close-modal" onclick="closeEditUserModal()">&times;</span>
                    <h3>Edit User</h3>
                    <form method="POST" id="editUserForm">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                        <input type="hidden" name="user_id" id="edit_user_id">
                        <div class="form-group">
                            <label for="edit_first_name">First Name</label>
                            <input type="text" id="edit_first_name" name="first_name" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_last_name">Last Name</label>
                            <input type="text" id="edit_last_name" name="last_name" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_email">Email</label>
                            <input type="email" id="edit_email" name="email" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_role">Role</label>
                            <select id="edit_role" name="role" class="form-control" required>
                                <option value="cashier">Cashier</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <button type="submit" name="update_user" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
                    </form>
                </div>
            </div>

        </div>

        <footer>
            &copy; <?php echo date('Y'); ?> Auntie Eddah POS
        </footer>
    </main>

    <script>
        // Modal handling for Edit User
        function openEditUserModal(userId, firstName, lastName, email, role) {
            document.getElementById('edit_user_id').value = userId;
            document.getElementById('edit_first_name').value = firstName;
            document.getElementById('edit_last_name').value = lastName;
            document.getElementById('edit_email').value = email;
            document.getElementById('edit_role').value = role;
            document.getElementById('editUserModal').style.display = 'block';
        }

        function closeEditUserModal() {
            document.getElementById('editUserModal').style.display = 'none';
        }

        document.querySelectorAll('.edit-user-btn').forEach(button => {
            button.addEventListener('click', function() {
                const userId = this.dataset.userId;
                const firstName = this.dataset.firstName;
                const lastName = this.dataset.lastName;
                const email = this.dataset.email;
                const role = this.dataset.role;
                openEditUserModal(userId, firstName, lastName, email, role);
            });
        });

        window.onclick = function(event) {
            const modal = document.getElementById('editUserModal');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }
    </script>
</body>
</html>


