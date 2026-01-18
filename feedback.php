<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'recruiter') {
    header("Location: ../login.php");
    exit;
}
include '../includes/db.php';
include '../includes/maintenance_check.php';

$current_user_id = $_SESSION['user_id'];
$success = '';
$error = '';

// Get recruiter info
$stmt = $pdo->prepare("SELECT company_name FROM recruiters WHERE user_id = ?");
$stmt->execute([$current_user_id]);
$recruiter_data = $stmt->fetch();
$recruiter_name = $recruiter_data['company_name'] ?? 'Recruiter';

// Handle feedback submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_feedback'])) {
    $subject = trim($_POST['subject']);
    $message = trim($_POST['message']);
    
    if (empty($subject) || empty($message)) {
        $error = "Subject and message are required.";
    } else {
        // Insert feedback
        $stmt = $pdo->prepare("INSERT INTO feedback (user_id, subject, message) VALUES (?, ?, ?)");
        $stmt->execute([$current_user_id, $subject, $message]);
        
        // Notify all admins
        $admin_stmt = $pdo->query("SELECT id FROM users WHERE role = 'admin'");
        $admins = $admin_stmt->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($admins as $admin_id) {
            $notify = $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
            $notify->execute([$admin_id, "New feedback received from " . $recruiter_name]);
        }
        
        $success = "Feedback submitted successfully! Admin will respond soon.";
    }
}

// Fetch user's feedback
$stmt = $pdo->prepare("SELECT * FROM feedback WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$current_user_id]);
$feedbacks = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="../images/logo.png" type="image/png">
    <title>Feedback - Employee Recruitment System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg fixed-top">
        <div class="container">
            <a class="navbar-brand" href="../index.php"><i class="fas fa-chart-bar me-2"></i>Employee Recruitment System</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-center">
                    <li class="nav-item"><a class="nav-link" href="dashboard.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="post_job.php">Post Job</a></li>
                    <li class="nav-item"><a class="nav-link" href="manage_jobs.php">Manage Jobs</a></li>
                    <li class="nav-item"><a class="nav-link" href="messages.php">Messages</a></li>
                    <li class="nav-item"><a class="nav-link active" href="feedback.php">Feedback</a></li>
                    <li class="nav-item">
                        <span class="nav-link text-muted">
                            <i class="fas fa-building me-1"></i><?php echo htmlspecialchars($recruiter_name); ?>
                        </span>
                    </li>
                    <li class="nav-item ms-3"><a class="btn btn-outline-danger btn-sm" href="../logout.php" onclick="return confirm('Are you sure you want to logout?');">Logout</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container py-5" style="margin-top: 80px;">
        <h2 class="fw-bold mb-4"><i class="fas fa-comment-dots me-2"></i>Feedback & Support</h2>

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

        <div class="row">
            <!-- Submit Feedback Form -->
            <div class="col-md-5">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-primary text-white py-3">
                        <h5 class="mb-0"><i class="fas fa-paper-plane me-2"></i>Submit Feedback</h5>
                    </div>
                    <div class="card-body p-4">
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Subject <span class="text-danger">*</span></label>
                                <input type="text" name="subject" class="form-control" required 
                                       placeholder="Brief description of your feedback">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Message <span class="text-danger">*</span></label>
                                <textarea name="message" class="form-control" rows="6" required 
                                          placeholder="Describe your feedback, suggestion, or issue in detail..."></textarea>
                            </div>
                            <button type="submit" name="submit_feedback" class="btn btn-primary w-100">
                                <i class="fas fa-paper-plane me-2"></i>Submit Feedback
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Info Card -->
                <div class="card border-0 shadow-sm bg-light">
                    <div class="card-body p-4">
                        <h6 class="fw-bold mb-3"><i class="fas fa-info-circle me-2"></i>Feedback Guidelines</h6>
                        <ul class="small mb-0">
                            <li class="mb-2">Be specific and clear in your feedback</li>
                            <li class="mb-2">Include relevant details (job IDs, dates, etc.)</li>
                            <li class="mb-2">Admin will respond within 24-48 hours</li>
                            <li class="mb-2">Check back here for admin replies</li>
                            <li>For urgent matters, use the Messages feature</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Feedback History -->
            <div class="col-md-7">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0 fw-bold"><i class="fas fa-history me-2"></i>Your Feedback History</h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if(count($feedbacks) > 0): ?>
                            <div class="list-group list-group-flush">
                                <?php foreach($feedbacks as $feedback): ?>
                                    <div class="list-group-item p-4">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <h6 class="fw-bold mb-0"><?php echo htmlspecialchars($feedback['subject']); ?></h6>
                                            <span class="badge <?php echo $feedback['reply'] ? 'bg-success' : 'bg-warning'; ?>">
                                                <?php echo $feedback['reply'] ? 'Replied' : 'Pending'; ?>
                                            </span>
                                        </div>
                                        <p class="text-muted small mb-2">
                                            <i class="fas fa-clock me-1"></i>
                                            <?php echo date('M d, Y h:i A', strtotime($feedback['created_at'])); ?>
                                        </p>
                                        
                                        <!-- Your Message -->
                                        <div class="bg-light p-3 rounded mb-3">
                                            <p class="small text-muted mb-1"><strong>Your Feedback:</strong></p>
                                            <p class="mb-0"><?php echo nl2br(htmlspecialchars($feedback['message'])); ?></p>
                                        </div>

                                        <!-- Admin Reply -->
                                        <?php if($feedback['reply']): ?>
                                            <div class="bg-primary bg-opacity-10 p-3 rounded border-start border-primary border-4">
                                                <p class="small text-primary mb-1">
                                                    <i class="fas fa-reply me-1"></i><strong>Admin Reply:</strong>
                                                </p>
                                                <p class="mb-0"><?php echo nl2br(htmlspecialchars($feedback['reply'])); ?></p>
                                            </div>
                                        <?php else: ?>
                                            <div class="alert alert-warning mb-0 py-2">
                                                <i class="fas fa-hourglass-half me-2"></i>
                                                <small>Waiting for admin response...</small>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5 text-muted">
                                <i class="fas fa-comment-slash fa-3x mb-3"></i>
                                <p>No feedback submitted yet.</p>
                                <p class="small">Submit your first feedback using the form on the left!</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/active_link.js"></script>
</body>
</html>
