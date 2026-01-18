<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}
include '../includes/db.php';

$app_id = $_GET['app_id'] ?? null;

if (!$app_id) {
    header("Location: manage_applications.php");
    exit;
}

// Handle verification actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['verify'])) {
        // Mark as verified and approved
        $stmt = $pdo->prepare("UPDATE applications SET status = 'approved', documents_verified = 'verified' WHERE id = ?");
        $stmt->execute([$app_id]);
        
        // Notify candidate
        $get_candidate = $pdo->prepare("SELECT users.id, jobs.title FROM users 
                                        JOIN candidates ON users.id = candidates.user_id 
                                        JOIN applications ON candidates.id = applications.candidate_id 
                                        JOIN jobs ON applications.job_id = jobs.id
                                        WHERE applications.id = ?");
        $get_candidate->execute([$app_id]);
        $candidate_info = $get_candidate->fetch();
        
        if ($candidate_info) {
            $msg = "Your documents have been verified! Your application for '{$candidate_info['title']}' has been approved.";
            $notify = $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
            $notify->execute([$candidate_info['id'], $msg]);
        }
        
        header("Location: manage_applications.php?success=verified");
        exit;
        
    } elseif (isset($_POST['not_match'])) {
        // Mark as not matching - not selected
        $stmt = $pdo->prepare("UPDATE applications SET status = 'not_selected', rejection_reason = 'Documents do not match the entered percentages' WHERE id = ?");
        $stmt->execute([$app_id]);
        
        // Notify candidate
        $get_candidate = $pdo->prepare("SELECT users.id, jobs.title FROM users 
                                        JOIN candidates ON users.id = candidates.user_id 
                                        JOIN applications ON candidates.id = applications.candidate_id 
                                        JOIN jobs ON applications.job_id = jobs.id
                                        WHERE applications.id = ?");
        $get_candidate->execute([$app_id]);
        $candidate_info = $get_candidate->fetch();
        
        if ($candidate_info) {
            $msg = "Your application for '{$candidate_info['title']}' has been rejected. Reason: Documents do not match the entered percentages.";
            $notify = $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
            $notify->execute([$candidate_info['id'], $msg]);
        }
        
        header("Location: manage_applications.php?success=not_match");
        exit;
        
    } elseif (isset($_POST['not_eligible'])) {
        // Mark as not eligible - not selected
        $stmt = $pdo->prepare("UPDATE applications SET status = 'not_selected', rejection_reason = 'Not eligible for this job' WHERE id = ?");
        $stmt->execute([$app_id]);
        
        // Notify candidate
        $get_candidate = $pdo->prepare("SELECT users.id, jobs.title FROM users 
                                        JOIN candidates ON users.id = candidates.user_id 
                                        JOIN applications ON candidates.id = applications.candidate_id 
                                        JOIN jobs ON applications.job_id = jobs.id
                                        WHERE applications.id = ?");
        $get_candidate->execute([$app_id]);
        $candidate_info = $get_candidate->fetch();
        
        if ($candidate_info) {
            $msg = "Your application for '{$candidate_info['title']}' was not selected. Reason: Not eligible for this job. Please review the eligibility criteria before applying.";
            $notify = $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
            $notify->execute([$candidate_info['id'], $msg]);
        }
        
        header("Location: manage_applications.php?success=not_eligible");
        exit;
    }
}

// Fetch application details
$stmt = $pdo->prepare("SELECT applications.*, 
                              candidates.full_name, candidates.documents_path, candidates.id as candidate_id,
                              jobs.title as job_title, jobs.eligibility_criteria,
                              users.email
                       FROM applications 
                       JOIN candidates ON applications.candidate_id = candidates.id 
                       JOIN users ON candidates.user_id = users.id 
                       JOIN jobs ON applications.job_id = jobs.id
                       WHERE applications.id = ?");
$stmt->execute([$app_id]);
$application = $stmt->fetch();

// Fetch education from candidate_education table
$education_entries = [];
if ($application) {
    $edu_stmt = $pdo->prepare("SELECT education_level, percentage FROM candidate_education WHERE candidate_id = ? ORDER BY id");
    $edu_stmt->execute([$application['candidate_id']]);
    $education_entries = $edu_stmt->fetchAll();
}

if (!$application) {
    header("Location: manage_applications.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="../images/logo.png" type="image/png">
    <title>Verify Documents - Employee Recruitment System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .pdf-viewer {
            width: 100%;
            height: 600px;
            border: 2px solid #dee2e6;
            border-radius: 8px;
        }
        .percentage-card {
            background: #f8f9fa;
            border-left: 4px solid #0d6efd;
            padding: 1rem;
            margin-bottom: 0.5rem;
            border-radius: 4px;
        }
        .action-btn {
            min-width: 150px;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg fixed-top bg-white border-bottom">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php"><i class="fas fa-chart-bar me-2"></i>Employee Recruitment System</a>
            <div class="ms-auto">
                <a href="manage_applications.php" class="btn btn-outline-secondary btn-sm">
                    <i class="fas fa-arrow-left me-1"></i>Back to Applications
                </a>
            </div>
        </div>
    </nav>

    <div class="container py-5" style="margin-top: 80px;">
        <div class="row">
            <!-- Left Column: Candidate Info & Percentages -->
            <div class="col-md-4">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body">
                        <h5 class="fw-bold mb-3">
                            <i class="fas fa-user-circle me-2 text-primary"></i>Candidate Information
                        </h5>
                        <p class="mb-2"><strong>Name:</strong> <?php echo htmlspecialchars($application['full_name']); ?></p>
                        <p class="mb-2"><strong>Email:</strong> <?php echo htmlspecialchars($application['email']); ?></p>
                        <p class="mb-0"><strong>Applied For:</strong> <?php echo htmlspecialchars($application['job_title']); ?></p>
                    </div>
                </div>

                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <h5 class="fw-bold mb-3">
                            <i class="fas fa-graduation-cap me-2 text-success"></i>Education Details
                        </h5>
                        
                        <?php if (!empty($education_entries)): ?>
                            <?php foreach($education_entries as $edu): ?>
                            <div class="percentage-card">
                                <strong><?php echo htmlspecialchars($edu['education_level']); ?>:</strong> 
                                <?php echo htmlspecialchars($edu['percentage']); ?>%
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                        <div class="alert alert-warning mb-0">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            No education details provided by candidate.
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Eligibility Criteria Card -->
                <div class="card border-0 shadow-sm mt-4">
                    <div class="card-body">
                        <h5 class="fw-bold mb-3">
                            <i class="fas fa-clipboard-check me-2 text-warning"></i>Job Eligibility Criteria
                        </h5>
                        
                        <?php if (!empty($application['eligibility_criteria'])): ?>
                            <div class="alert alert-light border mb-0">
                                <small><?php echo nl2br(htmlspecialchars($application['eligibility_criteria'])); ?></small>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-secondary mb-0">
                                <small><i class="fas fa-info-circle me-1"></i>No specific eligibility criteria set for this job.</small>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="card border-0 shadow-sm mt-4">
                    <div class="card-body">
                        <h6 class="fw-bold mb-3">Verification Actions</h6>
                        <form method="POST" onsubmit="return confirm('Are you sure? This action cannot be undone.');">
                            <button type="submit" name="verify" class="btn btn-success action-btn w-100 mb-2">
                                <i class="fas fa-check-circle me-2"></i>Verified & Eligible
                            </button>
                            <button type="submit" name="not_eligible" class="btn btn-warning action-btn w-100 mb-2">
                                <i class="fas fa-ban me-2"></i>Not Eligible
                            </button>
                            <button type="submit" name="not_match" class="btn btn-danger action-btn w-100 mb-2">
                                <i class="fas fa-times-circle me-2"></i>Documents Do Not Match
                            </button>
                            <a href="manage_applications.php" class="btn btn-secondary action-btn w-100">
                                <i class="fas fa-arrow-left me-2"></i>Back
                            </a>
                        </form>
                        
                        <div class="alert alert-info mt-3 mb-0">
                            <small>
                                <strong>Verified & Eligible:</strong> Approves application (status: approved)<br>
                                <strong>Not Eligible:</strong> Not selected - doesn't meet job criteria<br>
                                <strong>Do Not Match:</strong> Not selected - documents mismatch
                            </small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: PDF Viewer -->
            <div class="col-md-8">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <h5 class="fw-bold mb-3">
                            <i class="fas fa-file-pdf me-2 text-danger"></i>Uploaded Documents
                        </h5>
                        
                        <?php if ($application['documents_path']): ?>
                            <iframe src="../<?php echo $application['documents_path']; ?>" class="pdf-viewer"></iframe>
                            <div class="mt-3">
                                <a href="../<?php echo $application['documents_path']; ?>" target="_blank" class="btn btn-outline-primary">
                                    <i class="fas fa-external-link-alt me-2"></i>Open in New Tab
                                </a>
                                <a href="../<?php echo $application['documents_path']; ?>" download class="btn btn-outline-success">
                                    <i class="fas fa-download me-2"></i>Download PDF
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong>No documents uploaded!</strong> The candidate has not uploaded any educational documents.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
