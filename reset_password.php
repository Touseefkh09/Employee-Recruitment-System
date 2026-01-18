<?php
session_start();
include 'includes/db.php';
include 'includes/security.php';
include 'includes/header.php';

$error = '';
$success = '';
$reset = null;
$submittedToken = '';
$emailSent = isset($_GET['email_sent']) && $_GET['email_sent'] == 'true';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Validate CSRF token
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = "Security validation failed. Please try again.";
    } else {
        $token = trim($_POST['token'] ?? '');
        $submittedToken = $token; // Preserve for form
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
    
    // Verify Token exists and is valid
    if (empty($token)) {
        $error = "Please enter the reset token from your email.";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM password_resets WHERE token = ? AND expires_at > NOW()");
        $stmt->execute([$token]);
        $reset = $stmt->fetch();
        
        if (!$reset) {
            
            $error = "Invalid or expired reset token. Please check your email or request a new password reset.";
            // Check if token exists but is expired
            $stmt2 = $pdo->prepare("SELECT * FROM password_resets WHERE token = ?");
            $stmt2->execute([$token]);
            $expiredToken = $stmt2->fetch();
            if ($expiredToken) {
                $error = "This reset token has expired (tokens expire after 10 minutes). Please request a new password reset.";
            }
        } else {
            // Validate password - same rules as registration
            if (empty($password)) {
                $error = "Please enter a new password.";
            } elseif (strlen($password) < 6) {
                $error = "Password must be at least 6 characters long.";
            } elseif (!preg_match("/^(?=.*[a-zA-Z])(?=.*\d)(?=.*[\W_]).+$/", $password)) {
                $error = "Password must contain at least one letter, one number, and one special character.";
            } elseif ($password !== $confirm) {
                $error = "Passwords do not match.";
            } else {
                try {
                    
                    $hashed = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
                    $stmt->execute([$hashed, $reset['email']]);
                    
                    
                    $stmt = $pdo->prepare("DELETE FROM password_resets WHERE email = ?");
                    $stmt->execute([$reset['email']]);
                    
                    // Regenerate session to prevent session fixation
                    regenerate_session();
                    
                    // Redirect to login with success message
                    header("Location: login.php?reset_success=true");
                    exit;
                } catch (Exception $e) {
                    $error = "An error occurred while resetting your password. Please try again.";
                }
            }
        }
    }
    }
}
?>

<div class="container py-5" style="margin-top: 80px;">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="glass-panel p-4 p-md-5 rounded-4 shadow-sm">
                <div class="text-center mb-4">
                    <h2 class="fw-bold">Reset Password</h2>
                    <p class="text-muted">Enter the token from your email and set a new password</p>
                </div>

                <?php if($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                    </div>
                <?php endif; ?>
                <form method="POST" id="resetForm" novalidate>
                    <?php echo csrf_token_field(); ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <small>
                            <strong>Instructions:</strong><br>
                            1. Check your email for the reset token<br>
                            <br>
                            <strong>Password Requirements:</strong><br>
                            • Minimum 6 characters<br>
                            • At least one letter (A-Z or a-z)<br>
                            • At least one number (0-9)<br>
                            • At least one special character (!@#$%^&*...)
                        </small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Reset Token <span class="text-danger">*</span></label>
                        <input type="text" name="token" id="token" class="form-control" 
                               placeholder="Paste your reset token from email" 
                               value="<?php echo htmlspecialchars($submittedToken); ?>"
                               required
                               style="font-family: monospace;">
                        <small class="text-muted">Copy the token from your email and paste it here</small>
                        <div class="invalid-feedback">Please enter the reset token from your email.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">New Password <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="password" name="password" id="password" class="form-control" required
                                   minlength="6"
                                   pattern="(?=.*[A-Za-z])(?=.*\d)(?=.*[\W_]).{6,}"
                                   title="Must contain at least one letter, one number, and one special character.">
                            <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('password')">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div class="invalid-feedback">Password must be at least 6 characters with 1 letter, 1 number, and 1 special character.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Confirm Password <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="password" name="confirm_password" id="confirm_password" class="form-control" required>
                            <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('confirm_password')">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div class="invalid-feedback">Passwords do not match.</div>
                    </div>

                    <div class="d-grid gap-2 mt-4">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-key me-2"></i>Update Password
                        </button>
                    </div>
                </form>
                
                <div class="text-center mt-3">
                    <a href="login.php" class="btn btn-outline-primary">
                        <i class="fas fa-arrow-left me-1"></i>Back to Login
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function togglePassword(id) {
    const field = document.getElementById(id);
    const icon = field.nextElementSibling.querySelector('i');
    if (field.type === "password") {
        field.type = "text";
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        field.type = "password";
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

// Client-side validation
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('resetForm');
    if (!form) return;
    
    const token = document.getElementById('token');
    const password = document.getElementById('password');
    const confirmPassword = document.getElementById('confirm_password');
    
    const setError = (input, message) => {
        const feedback = input.parentElement.nextElementSibling || input.nextElementSibling;
        if (feedback && feedback.classList.contains('invalid-feedback')) {
            feedback.textContent = message;
        }
        input.classList.add('is-invalid');
        input.classList.remove('is-valid');
    };
    
    const setSuccess = (input) => {
        input.classList.remove('is-invalid');
        input.classList.add('is-valid');
    };
    
    const validateToken = () => {
        const value = token.value.trim();
        
        if (value.length === 0) {
            setError(token, 'Please enter the reset token from your email.');
            return false;
        }
        if (value.length < 20) {
            setError(token, 'Token appears to be invalid. Please check your email.');
            return false;
        }
        
        setSuccess(token);
        return true;
    };
    
    const validatePassword = () => {
        const value = password.value;
        
        if (value.length < 6) {
            setError(password, 'Password must be at least 6 characters.');
            return false;
        }
        if (!/(?=.*[A-Za-z])(?=.*\d)(?=.*[\W_])/.test(value)) {
            setError(password, 'Must contain 1 letter, 1 number, 1 special char.');
            return false;
        }
        
        setSuccess(password);
        return true;
    };
    
    const validateConfirmPassword = () => {
        if (confirmPassword.value !== password.value) {
            setError(confirmPassword, 'Passwords do not match.');
            return false;
        }
        setSuccess(confirmPassword);
        return true;
    };
    
    token.addEventListener('blur', validateToken);
    token.addEventListener('input', () => {
        if (token.classList.contains('is-invalid')) {
            validateToken();
        }
    });
    
    password.addEventListener('blur', validatePassword);
    password.addEventListener('input', () => {
        if (password.classList.contains('is-invalid')) {
            validatePassword();
        }
        // Also revalidate confirm if it has a value
        if (confirmPassword.value) {
            validateConfirmPassword();
        }
    });
    
    confirmPassword.addEventListener('blur', validateConfirmPassword);
    confirmPassword.addEventListener('input', () => {
        if (confirmPassword.classList.contains('is-invalid')) {
            validateConfirmPassword();
        }
    });
    
    form.addEventListener('submit', function(event) {
        const isTokenValid = validateToken();
        const isPasswordValid = validatePassword();
        const isConfirmValid = validateConfirmPassword();
        
        if (!isTokenValid || !isPasswordValid || !isConfirmValid) {
            event.preventDefault();
            event.stopPropagation();
        }
    });
});
</script>

<?php include 'includes/footer.php'; ?>
