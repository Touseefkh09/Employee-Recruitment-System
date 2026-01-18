<?php
session_start();
include 'includes/db.php';
include 'includes/security.php';
include 'includes/header.php';
include 'includes/SimpleSMTP.php';
include 'includes/smtp_config.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = "Security validation failed. Please try again.";
    } else {
        $email = trim($_POST['email']);
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        
        $stmt = $pdo->prepare("SELECT id, email FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user) {
            
            $token = bin2hex(random_bytes(32)); // 64 character token
            $expires_at = date('Y-m-d H:i:s', strtotime('+10 minutes')); 
            
            try {
                
                $stmt = $pdo->prepare("DELETE FROM password_resets WHERE email = ?");
                $stmt->execute([$email]);
                
                
                $stmt = $pdo->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
                $stmt->execute([$email, $token, $expires_at]);
                
                
                $subject = "Password Reset Token - Employee Recruitment System";
                $message = "
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                    <h2 style='color: #0d6efd;'>Password Reset Request</h2>
                    <p>Hello,</p>
                    <p>We received a request to reset your password for your Employee Recruitment System account.</p>
                    
                    <div style='background-color: #f8f9fa; padding: 20px; border-radius: 5px; margin: 20px 0; border: 2px solid #0d6efd;'>
                        <p style='margin: 0; font-size: 14px;'><strong>Your Password Reset Token:</strong></p>
                        <p style='font-size: 20px; color: #0d6efd; font-weight: bold; margin: 15px 0; word-break: break-all; font-family: monospace; background: white; padding: 15px; border-radius: 3px;'>{$token}</p>
                    </div>
                    
                    <div style='background-color: #fff3cd; padding: 15px; border-left: 4px solid #ffc107; margin: 20px 0;'>
                        <p style='margin: 0;'><strong>⚠️ IMPORTANT:</strong></p>
                        <ul style='margin: 10px 0;'>
                            <li><strong style='color: #d9534f;'>This token will expire in 10 MINUTES</strong></li>
                            <li>Copy this token immediately and use it to reset your password</li>
                            <li>If you didn't request this reset, please ignore this email</li>
                        </ul>
                    </div>
                    
                    <p>Thank you,<br>Employee Recruitment System Team</p>
                </div>
                ";
                
                // Send email via SMTP using config
                $smtp = new SimpleSMTP(SMTP_HOST, SMTP_PORT, SMTP_USERNAME, SMTP_PASSWORD);
                
                if ($smtp->send($email, $subject, $message, SMTP_FROM_NAME)) {
                    // Redirect to reset password page
                    header("Location: reset_password.php?email_sent=true");
                    exit;
                } else {
                    $error = "Failed to send email. Please try again later.";
                }
                
            } catch (Exception $e) {
                $error = "An error occurred. Please try again later.";
            }
        } else {
            // Timing attack mitigation: Add delay to match email sending time
            usleep(500000); // 0.5 second delay
            // Don't reveal if email exists or not (security best practice)
            // Still redirect to reset page
            header("Location: reset_password.php?email_sent=true");
            exit;
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
                    <h2 class="fw-bold">Forgot Password</h2>
                    <p class="text-muted">Enter your email to receive password reset instructions</p>
                </div>

                <?php if($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                    </div>
                    <div class="text-center mt-3">
                        <a href="login.php" class="btn btn-primary">Back to Login</a>
                    </div>
                <?php else: ?>
                    <?php if($error): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST">
                        <?php echo csrf_token_field(); ?>
                        <div class="mb-3">
                            <label class="form-label">Email Address</label>
                            <input type="email" name="email" class="form-control" placeholder="your@email.com" required>
                            <small class="text-muted">We'll send password reset instructions to this email</small>
                        </div>

                        <div class="d-grid gap-2 mt-4">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-paper-plane me-2"></i>Send Reset Instructions
                            </button>
                        </div>

                        <div class="text-center mt-3">
                            <a href="login.php" class="text-decoration-none">
                                <i class="fas fa-arrow-left me-1"></i>Back to Login
                            </a>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
