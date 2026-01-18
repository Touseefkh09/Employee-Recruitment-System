<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}
include '../includes/db.php';

$success = '';
$error = '';

// Get current admin details (email from users, username from admin)
$stmt = $pdo->prepare("
    SELECT u.email, a.username 
    FROM users u 
    LEFT JOIN admin a ON u.id = a.user_id 
    WHERE u.id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$admin = $stmt->fetch();
$current_email = $admin['email'];
$current_username = $admin['username'] ?? 'Admin'; // Fallback to 'Admin' if not in admin table


// Handle Profile Update
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_username') {
        $new_username = trim($_POST['new_username']);
        $password = $_POST['password'];
        
        // Validate username
        if (empty($new_username)) {
            $error = "Username cannot be empty.";
        } elseif (strlen($new_username) < 3 || strlen($new_username) > 50) {
            $error = "Username must be between 3 and 50 characters.";
        } elseif (!preg_match('/^[a-zA-Z_]+[0-9]*$/', $new_username)) {
            $error = "Username must contain only letters and underscores, with optional digits at the end.";
        } else {
            // Verify current password
            $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();
            
            if (!password_verify($password, $user['password'])) {
                $error = "Incorrect password.";
            } else {
                // Update username in admin table
                $update = $pdo->prepare("UPDATE admin SET username = ? WHERE user_id = ?");
                $update->execute([$new_username, $_SESSION['user_id']]);
                $success = "Username updated successfully!";
                
                // Refresh admin data
                $current_username = $new_username;
            }
        }
    }
    
    if ($action === 'update_password') {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Validate passwords match
        if ($new_password !== $confirm_password) {
            $error = "New passwords do not match.";
        } elseif (strlen($new_password) < 6) {
            $error = "Password must be at least 6 characters long.";
        } elseif (!preg_match('/[A-Za-z]/', $new_password) || !preg_match('/[0-9]/', $new_password) || !preg_match('/[\W_]/', $new_password)) {
            $error = "Password must contain at least one letter, one number, and one special character.";
        } else {
            // Verify current password
            $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();
            
            if (!password_verify($current_password, $user['password'])) {
                $error = "Current password is incorrect.";
            } else {
                // Update password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $update = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $update->execute([$hashed_password, $_SESSION['user_id']]);
                $success = "Password updated successfully!";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="../images/logo.png" type="image/png">
    <title>My Profile - Employee Recruitment System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg fixed-top bg-white border-bottom">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php"><i class="fas fa-chart-bar me-2"></i>Employee Recruitment System</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-center">
                    <li class="nav-item"><a class="nav-link" href="dashboard.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="users.php">Users</a></li>
                    <li class="nav-item"><a class="nav-link" href="jobs.php">Jobs</a></li>
                    <li class="nav-item"><a class="nav-link" href="manage_applications.php">Applications</a></li>
                    <li class="nav-item"><a class="nav-link" href="feedback.php">Feedback</a></li>
                    <li class="nav-item"><a class="nav-link" href="settings.php">Settings</a></li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle active" href="#" id="profileDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($current_username); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item active" href="profile.php"><i class="fas fa-user me-2"></i>My Profile</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="logout.php" onclick="return confirm('Are you sure you want to logout?');"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container py-5" style="margin-top: 80px;">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <h2 class="fw-bold mb-4"><i class="fas fa-user-cog me-2"></i>My Profile</h2>

                <?php if($success): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Current Username Display -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body p-4">
                        <h5 class="card-title mb-3"><i class="fas fa-info-circle me-2"></i>Account Information</h5>
                        <div class="row">
                            <div class="col-md-2">
                                <p class="text-muted mb-0">Email:</p>
                            </div>
                            <div class="col-md-10">
                                <p class="fw-bold mb-0">
                                    <?php echo htmlspecialchars($current_email); ?> 
                                    <i class="fas fa-lock text-muted ms-2" title="Email cannot be changed"></i>
                                </p>
                            </div>
                        </div>
                        <hr>
                        <div class="row">
                            <div class="col-md-2">
                                <p class="text-muted mb-0">Role:</p>
                            </div>
                            <div class="col-md-10">
                                <p class="mb-0"><span class="badge bg-danger">Administrator</span></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Update Username -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body p-4">
                        <h5 class="card-title mb-3"><i class="fas fa-user-edit me-2"></i>Update Username</h5>
                        <form method="POST" id="usernameForm">
                            <input type="hidden" name="action" value="update_username">
                            <div class="mb-3">
                                <label class="form-label">New Username <span class="text-danger">*</span></label>
                                <input type="text" name="new_username" class="form-control" required 
                                       placeholder="Enter new username"
                                       minlength="3" maxlength="50"
                                       pattern="^[a-zA-Z_]+[0-9]*$"
                                       title="Letters and underscores only, with optional digits at the end">
                                <div class="form-text">Letters,underscores and digits are allowed</div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Current Password <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="password" name="password" id="usernamePassword" class="form-control" required placeholder="Confirm with your password">
                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('usernamePassword', this)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <div class="form-text">Enter your current password to confirm this change.</div>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Update Username
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Update Password -->
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-4">
                        <h5 class="card-title mb-3"><i class="fas fa-lock me-2"></i>Change Password</h5>
                        <form method="POST" id="passwordForm">
                            <input type="hidden" name="action" value="update_password">
                            <div class="mb-3">
                                <label class="form-label">Current Password <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="password" name="current_password" id="currentPassword" class="form-control" required placeholder="Enter current password">
                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('currentPassword', this)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">New Password <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="password" name="new_password" id="newPassword" class="form-control" required 
                                           placeholder="Enter new password"
                                           pattern="^(?=.*[A-Za-z])(?=.*\d)(?=.*[\W_]).{6,}$"
                                           title="Min 6 chars, 1 letter, 1 number, 1 special char">
                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('newPassword', this)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <div class="form-text">Minimum 6 characters with at least one letter, one number, and one special character.</div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Confirm New Password <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="password" name="confirm_password" id="confirmPassword" class="form-control" required placeholder="Re-enter new password">
                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('confirmPassword', this)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-key me-2"></i>Change Password
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/active_link.js"></script>
    <script>
        function togglePassword(inputId, button) {
            const input = document.getElementById(inputId);
            const icon = button.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        // Password confirmation validation
        document.getElementById('passwordForm').addEventListener('submit', function(e) {
            const newPassword = document.getElementById('newPassword').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            
            if (newPassword !== confirmPassword) {
                e.preventDefault();
                alert('New passwords do not match!');
                return false;
            }
        });
    </script>
</body>
</html>
