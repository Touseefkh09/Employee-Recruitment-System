<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}
include '../includes/db.php';

// Handle Status Update
if (isset($_POST['update_status'])) {
    $app_id = $_POST['application_id'];
    $new_status = $_POST['status'];
    $rejection_reason = $_POST['rejection_reason'] ?? null;
    $test_marks = $_POST['test_marks'] ?? null;
    $interview_marks = $_POST['interview_marks'] ?? null;
    
    // Get current application details
    $current_app = $pdo->prepare("SELECT status, test_marks FROM applications WHERE id = ?");
    $current_app->execute([$app_id]);
    $app_data = $current_app->fetch();
    $current_status = $app_data['status'];
    
    $error = '';
    $final_status = $new_status;
    
    // RULE 1: Block direct approval from pending
    if ($current_status === 'pending' && $new_status === 'approved') {
        $error = "Cannot directly approve application. Please verify documents first using the 'Verify Docs' button.";
    }
    
    // RULE 2: Test marks required when moving to interviewing
    elseif ($current_status === 'test' && $new_status === 'interviewing') {
        if (empty($test_marks)) {
            $error = "Test marks are required to move to interviewing phase.";
        } else {
            // Update with test marks
            $update = $pdo->prepare("UPDATE applications SET status = ?, test_marks = ? WHERE id = ?");
            $update->execute([$new_status, $test_marks, $app_id]);
        }
    }
    
    
    // RULE 3: Interview marks + auto-validation for final decision
    // When in interviewing status and form is submitted (status stays interviewing)
    elseif ($current_status === 'interviewing' && $new_status === 'interviewing') {
        if (empty($interview_marks)) {
            $error = "Interview marks are required to complete the evaluation.";
        } else {
            $stored_test_marks = $app_data['test_marks'];
            
            // AUTO-VALIDATION: Both must be >= 50%
            if ($stored_test_marks >= 50 && $interview_marks >= 50) {
                $final_status = 'hired';
                $result_msg = "Congratulations! You passed (Test: {$stored_test_marks}%, Interview: {$interview_marks}%)";
            } else {
                $final_status = 'not_selected';
                $result_msg = "Unfortunately, you did not meet the requirements (Test: {$stored_test_marks}%, Interview: {$interview_marks}%)";
            }
            
            // Update with interview marks and final status
            $update = $pdo->prepare("UPDATE applications SET status = ?, interview_marks = ? WHERE id = ?");
            $update->execute([$final_status, $interview_marks, $app_id]);
        }
    }
    
    // RULE 4: Rejection with reason
    elseif ($new_status == 'rejected') {
        if (!empty($rejection_reason)) {
            $update = $pdo->prepare("UPDATE applications SET status = ?, rejection_reason = ? WHERE id = ?");
            $update->execute([$new_status, $rejection_reason, $app_id]);
        } else {
            $error = "Rejection reason is required.";
        }
    }
    
    // RULE 5: Normal status updates (approved → shortlisted, shortlisted → test, etc.)
    elseif (empty($error)) {
        $update = $pdo->prepare("UPDATE applications SET status = ? WHERE id = ?");
        $update->execute([$new_status, $app_id]);
    }
    
    // Send Notification to Candidate (if no error)
    if (empty($error)) {
        $get_candidate = $pdo->prepare("SELECT users.id, jobs.title FROM users 
                                        JOIN candidates ON users.id = candidates.user_id 
                                        JOIN applications ON candidates.id = applications.candidate_id 
                                        JOIN jobs ON applications.job_id = jobs.id
                                        WHERE applications.id = ?");
        $get_candidate->execute([$app_id]);
        $candidate_info = $get_candidate->fetch();
        
        if ($candidate_info) {
            // Custom messages based on status
            $status_messages = [
                'shortlisted' => "Great news! You have been shortlisted for '{$candidate_info['title']}'",
                'test' => "You have been selected for the test phase for '{$candidate_info['title']}'",
                'interviewing' => "Congratulations! You have been called for an interview for '{$candidate_info['title']}'",
                'hired' => isset($result_msg) ? $result_msg . " for '{$candidate_info['title']}'" : "Congratulations! You have been hired for '{$candidate_info['title']}'",
                'rejected' => "Your application for '{$candidate_info['title']}' has been rejected" . (!empty($rejection_reason) ? ". Reason: " . $rejection_reason : ""),
                'not_selected' => isset($result_msg) ? $result_msg . " for '{$candidate_info['title']}'" : "You were not selected for '{$candidate_info['title']}'"
            ];
            
            $msg = $status_messages[$final_status] ?? "Your application for '{$candidate_info['title']}' has been updated to: " . ucfirst($final_status);
            
            // Check if enhanced notification structure exists (suppress any errors)
            try {
                $check_columns = @$pdo->query("SHOW COLUMNS FROM notifications LIKE 'type'");
                $has_type_column = $check_columns && $check_columns->rowCount() > 0;
            } catch (Exception $e) {
                $has_type_column = false;
            }
            
            if ($has_type_column) {
                $notify = $pdo->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'application_update')");
                $notify->execute([$candidate_info['id'], $msg]);
            } else {
                $notify = $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
                $notify->execute([$candidate_info['id'], $msg]);
            }
        }
        
        $success = "Application status updated successfully!";
    }
}

// Fetch all applications with filters
$status_filter = $_GET['status'] ?? '';
$job_filter = $_GET['job_id'] ?? '';

$query = "SELECT applications.id as app_id, applications.status, applications.applied_at, applications.rejection_reason,
                 applications.documents_verified, applications.test_marks, applications.interview_marks,
                 candidates.full_name, candidates.resume_path, candidates.skills, candidates.phone, 
                 candidates.documents_path,
                 candidates.user_id as candidate_user_id, candidates.id as candidate_id,
                 users.email,
                 jobs.title as job_title, jobs.id as job_id,
                 recruiters.company_name
          FROM applications 
          JOIN candidates ON applications.candidate_id = candidates.id 
          JOIN users ON candidates.user_id = users.id 
          JOIN jobs ON applications.job_id = jobs.id
          JOIN recruiters ON jobs.recruiter_id = recruiters.id
          WHERE 1=1";

$params = [];

if ($status_filter) {
    $query .= " AND applications.status = ?";
    $params[] = $status_filter;
}

if ($job_filter) {
    $query .= " AND jobs.id = ?";
    $params[] = $job_filter;
}

$query .= " ORDER BY applications.applied_at DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$applications = $stmt->fetchAll();

// Get all jobs for filter dropdown
$jobs_stmt = $pdo->query("SELECT id, title FROM jobs ORDER BY title");
$all_jobs = $jobs_stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="../images/logo.png" type="image/png">
    <title>Manage Applications - Employee Recruitment System</title>
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
                    <li class="nav-item"><a class="nav-link active" href="manage_applications.php">Applications</a></li>
                    <li class="nav-item"><a class="nav-link" href="feedback.php">Feedback</a></li>
                    <li class="nav-item"><a class="nav-link" href="settings.php">Settings</a></li>
                    <li class="nav-item ms-3"><a class="btn btn-outline-danger btn-sm" href="logout.php" onclick="return confirm('Are you sure you want to logout?');">Logout</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container py-5" style="margin-top: 60px;">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold mb-1">Manage Applications</h2>
                <p class="text-muted"><?php echo count($applications); ?> application(s) found</p>
            </div>
            
            <!-- Filters -->
            <form method="GET" class="d-flex gap-2">
                <select name="job_id" class="form-select" style="width: 180px;" onchange="this.form.submit()">
                    <option value="">All Jobs</option>
                    <?php foreach($all_jobs as $job): ?>
                        <option value="<?php echo $job['id']; ?>" <?php echo $job_filter == $job['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($job['title']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select name="status" class="form-select" style="width: 150px;" onchange="this.form.submit()">
                    <option value="">All Statuses</option>
                    <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="approved" <?php echo $status_filter == 'approved' ? 'selected' : ''; ?>>Approved</option>
                    <option value="shortlisted" <?php echo $status_filter == 'shortlisted' ? 'selected' : ''; ?>>Shortlisted</option>
                    <option value="test" <?php echo $status_filter == 'test' ? 'selected' : ''; ?>>Test</option>
                    <option value="interviewing" <?php echo $status_filter == 'interviewing' ? 'selected' : ''; ?>>Interviewing</option>
                    <option value="hired" <?php echo $status_filter == 'hired' ? 'selected' : ''; ?>>Hired</option>
                    <option value="rejected" <?php echo $status_filter == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                    <option value="not_selected" <?php echo $status_filter == 'not_selected' ? 'selected' : ''; ?>>Not Selected</option>
                </select>
            </form>
        </div>

        <?php if(isset($success)): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if(isset($error) && !empty($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            <?php foreach($applications as $app): ?>
            <div class="col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body p-4">
                        <!-- Header -->
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <h5 class="fw-bold mb-1"><?php echo htmlspecialchars($app['full_name']); ?></h5>
                                <small class="text-muted"><?php echo htmlspecialchars($app['email']); ?></small>
                            </div>
                            <span class="badge bg-<?php 
                                echo match($app['status']) {
                                    'pending' => 'warning',
                                    'approved' => 'success',
                                    'shortlisted' => 'info',
                                    'test' => 'warning',
                                    'interviewing' => 'primary',
                                    'hired' => 'success',
                                    'rejected' => 'danger',
                                    'not_selected' => 'danger',
                                    default => 'secondary'
                                };
                            ?>"><?php echo ucfirst(str_replace('_', ' ', $app['status'])); ?></span>
                        </div>

                        <!-- Job Info -->
                        <div class="bg-light p-2 rounded mb-3">
                            <small class="text-muted">Applied for:</small>
                            <p class="mb-0 fw-bold"><?php echo htmlspecialchars($app['job_title']); ?></p>
                            <small class="text-muted">at <?php echo htmlspecialchars($app['company_name']); ?></small>
                        </div>

                        <!-- Candidate Details -->
                        <div class="mb-3">
                            <p class="mb-1"><i class="fas fa-phone me-2 text-muted"></i><?php echo htmlspecialchars($app['phone'] ?? 'N/A'); ?></p>
                            <p class="mb-1"><i class="fas fa-star me-2 text-muted"></i><?php echo htmlspecialchars($app['skills'] ?? 'N/A'); ?></p>
                            <p class="mb-1"><i class="fas fa-calendar me-2 text-muted"></i>Applied: <?php echo date('M d, Y', strtotime($app['applied_at'])); ?></p>
                        </div>

                        <!-- Education Details -->
                        <?php 
                        // Fetch education from candidate_education table
                        $edu_stmt = $pdo->prepare("SELECT education_level, percentage FROM candidate_education WHERE candidate_id = ? ORDER BY id");
                        $edu_stmt->execute([$app['candidate_id']]);
                        $education_entries = $edu_stmt->fetchAll();
                        
                        if (!empty($education_entries)): 
                        ?>
                        <div class="mb-3">
                            <small class="text-muted fw-bold">Education:</small>
                            <?php foreach($education_entries as $edu): ?>
                                <p class="mb-1 small">
                                    <i class="fas fa-graduation-cap me-2"></i>
                                    <?php echo htmlspecialchars($edu['education_level']); ?>: 
                                    <?php echo htmlspecialchars($edu['percentage']); ?>%
                                </p>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>

                        <!-- Action Buttons -->
                        <div class="d-flex gap-2 mb-3 flex-wrap">
                            <?php if($app['resume_path']): ?>
                                <a href="../<?php echo $app['resume_path']; ?>" target="_blank" class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-file-alt me-1"></i>Resume
                                </a>
                            <?php endif; ?>
                            <?php if($app['documents_path']): ?>
                                <a href="../<?php echo $app['documents_path']; ?>" target="_blank" class="btn btn-outline-success btn-sm">
                                    <i class="fas fa-file-pdf me-1"></i>Documents
                                </a>
                            <?php endif; ?>
                            <?php if($app['status'] == 'pending'): ?>
                                <a href="verify_documents.php?app_id=<?php echo $app['app_id']; ?>" class="btn btn-warning btn-sm">
                                    <i class="fas fa-check-circle me-1"></i>Verify Docs
                                </a>
                                <small class="text-muted d-block mt-1">Documents must be verified before approval</small>
                            <?php endif; ?>
                            <a href="https://mail.google.com/mail/?view=cm&fs=1&to=<?php echo $app['email']; ?>" target="_blank" class="btn btn-outline-dark btn-sm" title="Send Email via Gmail">
                                <i class="fas fa-envelope me-1"></i>Email
                            </a>
                            <a href="../messages.php?user_id=<?php echo $app['candidate_user_id']; ?>" class="btn btn-outline-info btn-sm" title="Message Candidate">
                                <i class="fas fa-comment me-1"></i>Message
                            </a>
                        </div>

                        <!-- Rejection Reason -->
                        <?php if($app['status'] == 'rejected' && $app['rejection_reason']): ?>
                        <div class="alert alert-danger mb-3">
                            <small><strong>Rejection Reason:</strong> <?php echo htmlspecialchars($app['rejection_reason']); ?></small>
                        </div>
                        <?php endif; ?>

                        <!-- Test/Interview Marks Display -->
                        <?php if($app['test_marks'] || $app['interview_marks']): ?>
                        <div class="alert alert-info mb-3 py-2">
                            <?php if($app['test_marks']): ?>
                                <small><strong>Test:</strong> <?php echo $app['test_marks']; ?>%</small><br>
                            <?php endif; ?>
                            <?php if($app['interview_marks']): ?>
                                <small><strong>Interview:</strong> <?php echo $app['interview_marks']; ?>%</small>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>

                        <hr>

                        <!-- Status Update Form -->
                        <form method="POST" id="statusForm<?php echo $app['app_id']; ?>">
                            <input type="hidden" name="application_id" value="<?php echo $app['app_id']; ?>">
                            <div class="mb-2">
                                <label class="form-label small">Change Status:</label>
                                <select name="status" class="form-select form-select-sm" 
                                        onchange="handleStatusChange(<?php echo $app['app_id']; ?>, this.value, '<?php echo $app['status']; ?>')">
                                    <?php
                                    $current_status = $app['status'];
                                    
                                    // Define allowed transitions based on current status
                                    $allowed_statuses = [];
                                    switch($current_status) {
                                        case 'pending':
                                            $allowed_statuses = ['pending', 'rejected'];
                                            break;
                                        case 'approved':
                                            $allowed_statuses = ['approved', 'shortlisted', 'rejected'];
                                            break;
                                        case 'shortlisted':
                                            $allowed_statuses = ['shortlisted', 'test', 'rejected'];
                                            break;
                                        case 'test':
                                            $allowed_statuses = ['test', 'interviewing', 'rejected'];
                                            break;
                                        case 'interviewing':
                                            // Don't show hired/not_selected - these are auto-decided
                                            $allowed_statuses = ['interviewing'];
                                            break;
                                        case 'hired':
                                        case 'rejected':
                                        case 'not_selected':
                                            $allowed_statuses = [$current_status]; // Final states
                                            break;
                                    }
                                    
                                    $all_statuses = [
                                        'pending' => 'Pending',
                                        'approved' => 'Approved',
                                        'shortlisted' => 'Shortlisted',
                                        'test' => 'Test',
                                        'interviewing' => 'Interviewing',
                                        'hired' => 'Hired',
                                        'rejected' => 'Rejected',
                                        'not_selected' => 'Not Selected'
                                    ];
                                    
                                    foreach($allowed_statuses as $status_key):
                                    ?>
                                        <option value="<?php echo $status_key; ?>" <?php echo $current_status == $status_key ? 'selected' : ''; ?>>
                                            <?php echo $all_statuses[$status_key]; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <!-- Test Marks Input (shown when changing test → interviewing) -->
                            <div id="testMarks<?php echo $app['app_id']; ?>" style="display: none;">
                                <label class="form-label small">Test Marks (out of 100) <span class="text-danger">*</span></label>
                                <input type="number" name="test_marks" class="form-control form-control-sm mb-2" 
                                       min="0" max="100" step="0.01" placeholder="Enter test marks">
                            </div>
                            
                            <!-- Interview Marks Input (shown when status IS interviewing) -->
                            <div id="interviewMarks<?php echo $app['app_id']; ?>" style="display: <?php echo $app['status'] === 'interviewing' ? 'block' : 'none'; ?>;">
                                <label class="form-label small">Interview Marks (out of 100) <span class="text-danger">*</span></label>
                                <input type="number" name="interview_marks" class="form-control form-control-sm mb-2" 
                                       min="0" max="100" step="0.01" placeholder="Enter interview marks" 
                                       value="<?php echo $app['interview_marks'] ?? ''; ?>">
                                <small class="text-muted d-block mb-2">
                                    <i class="fas fa-info-circle"></i> Auto-decision: Hired if both test (<?php echo $app['test_marks'] ?? 'N/A'; ?>%) and interview ≥ 50%, otherwise Not Selected.
                                </small>
                            </div>
                            
                            <!-- Rejection Reason -->
                            <div id="rejectionReason<?php echo $app['app_id']; ?>" style="display: <?php echo $app['status'] == 'rejected' ? 'block' : 'none'; ?>;">
                                <textarea name="rejection_reason" class="form-control form-control-sm mb-2" rows="2" 
                                          placeholder="Enter rejection reason..."></textarea>
                            </div>
                            
                            <button type="submit" name="update_status" class="btn btn-primary btn-sm w-100">
                                <?php 
                                if ($app['status'] === 'interviewing') {
                                    echo '<i class="fas fa-check-circle me-1"></i>Complete Evaluation';
                                } else {
                                    echo 'Update Status';
                                }
                                ?>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>

            <?php if(empty($applications)): ?>
                <div class="col-12 text-center py-5">
                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                    <p class="text-muted">No applications found matching the selected criteria.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function handleStatusChange(appId, newStatus, currentStatus) {
            const reasonDiv = document.getElementById('rejectionReason' + appId);
            const testMarksDiv = document.getElementById('testMarks' + appId);
            const interviewMarksDiv = document.getElementById('interviewMarks' + appId);
            
            // Hide all conditional inputs first
            reasonDiv.style.display = 'none';
            testMarksDiv.style.display = 'none';
            interviewMarksDiv.style.display = 'none';
            
            // Show rejection reason if rejected
            if (newStatus === 'rejected') {
                reasonDiv.style.display = 'block';
            }
            
            // Show test marks input when moving from test to interviewing
            if (currentStatus === 'test' && newStatus === 'interviewing') {
                testMarksDiv.style.display = 'block';
            }
            
            // Show interview marks input when in interviewing status
            if (currentStatus === 'interviewing' && (newStatus === 'hired' || newStatus === 'not_selected')) {
                interviewMarksDiv.style.display = 'block';
            }
        }
    </script>
</body>
</html>
