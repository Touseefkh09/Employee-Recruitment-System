<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'candidate') {
    header("Location: ../login.php");
    exit;
}
include '../includes/db.php';


$c_stmt = $pdo->prepare("SELECT id, full_name, resume_path FROM candidates WHERE user_id = ?");
$c_stmt->execute([$_SESSION['user_id']]);
$candidate = $c_stmt->fetch();


$search = $_GET['search'] ?? '';
$location = $_GET['location'] ?? '';
$type = $_GET['type'] ?? '';
$highlight_job_id = $_GET['highlight'] ?? null; 

$query = "SELECT jobs.*, recruiters.company_name 
          FROM jobs 
          JOIN recruiters ON jobs.recruiter_id = recruiters.id 
          WHERE jobs.status = 'active'";
$params = [];

if ($search) {
    $query .= " AND (jobs.title LIKE ? OR recruiters.company_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($location) {
    $query .= " AND jobs.location LIKE ?";
    $params[] = "%$location%";
}
if ($type) {
    $query .= " AND jobs.type = ?";
    $params[] = $type;
}

$query .= " ORDER BY jobs.created_at DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$jobs = $stmt->fetchAll();


$applied_stmt = $pdo->prepare("SELECT job_id FROM applications WHERE candidate_id = ?");
$applied_stmt->execute([$candidate['id']]);
$applied_jobs = $applied_stmt->fetchAll(PDO::FETCH_COLUMN);


if (isset($_POST['apply_job_id'])) {
    $job_id = $_POST['apply_job_id'];
    
    if (empty($candidate['resume_path'])) {
        header("Location: complete_profile.php?job_id=" . $job_id);
        exit;
    } else {
        if (in_array($job_id, $applied_jobs)) {
            $error_msg = "You have already applied for this job.";
        } else {
            $apply = $pdo->prepare("INSERT INTO applications (job_id, candidate_id) VALUES (?, ?)");
            $apply->execute([$job_id, $candidate['id']]);
            
            $application_id = $pdo->lastInsertId();
            
            $get_job = $pdo->prepare("SELECT title FROM jobs WHERE id = ?");
            $get_job->execute([$job_id]);
            $job_info = $get_job->fetch();
            
            $get_admins = $pdo->prepare("SELECT id FROM users WHERE role = 'admin'");
            $get_admins->execute();
            $admins = $get_admins->fetchAll(PDO::FETCH_COLUMN);
            
            if (!empty($admins) && $job_info) {
                $admin_msg = "New application received for job: " . $job_info['title'] . " by " . $candidate['full_name'];
                
                // Check if notifications table has application_id column
                try {
                    $check_col = $pdo->query("SHOW COLUMNS FROM notifications LIKE 'application_id'");
                    $has_app_id_col = $check_col && $check_col->rowCount() > 0;
                } catch (Exception $e) {
                    $has_app_id_col = false;
                }
                
                if ($has_app_id_col) {
                    // Use enhanced notification with application_id
                    $notify_admin = $pdo->prepare("INSERT INTO notifications (user_id, message, application_id) VALUES (?, ?, ?)");
                    foreach ($admins as $admin_id) {
                        $notify_admin->execute([$admin_id, $admin_msg, $application_id]);
                    }
                } else {
                    // Fallback to basic notification
                    $notify_admin = $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
                    foreach ($admins as $admin_id) {
                        $notify_admin->execute([$admin_id, $admin_msg]);
                    }
                }
            }
            
            $success_msg = "Application submitted successfully!";
            $applied_jobs[] = $job_id;
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
    <title>Browse Jobs - Employee Recruitment System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg fixed-top bg-white border-bottom">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php"><i class="fas fa-chart-bar me-2"></i>Employee Recruitment System</a>
            <div class="ms-auto">
                <a href="dashboard.php" class="btn btn-outline-secondary btn-sm">Dashboard</a>
            </div>
        </div>
    </nav>

    <div class="container py-5" style="margin-top: 60px;">
        <?php if(isset($_GET['success']) && $_GET['success'] == 'profile_complete'): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i> Profile completed successfully! You can now apply for jobs.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <h2 class="fw-bold mb-4">Latest Opportunities</h2>

        <!-- Search Form -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-4">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span>
                            <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="Job title or company..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="fas fa-map-marker-alt text-muted"></i></span>
                            <input type="text" name="location" class="form-control border-start-0 ps-0" placeholder="Location..." value="<?php echo htmlspecialchars($location); ?>">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <select name="type" class="form-select">
                            <option value="">All Job Types</option>
                            <option value="Full-time" <?php echo $type == 'Full-time' ? 'selected' : ''; ?>>Full-time</option>
                            <option value="Part-time" <?php echo $type == 'Part-time' ? 'selected' : ''; ?>>Part-time</option>
                            <option value="Contract" <?php echo $type == 'Contract' ? 'selected' : ''; ?>>Contract</option>
                            <option value="Internship" <?php echo $type == 'Internship' ? 'selected' : ''; ?>>Internship</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">Find Jobs</button>
                    </div>
                </form>
            </div>
        </div>

        <?php if(isset($success_msg)): ?>
            <div class="alert alert-success"><?php echo $success_msg; ?></div>
        <?php endif; ?>
        <?php if(isset($error_msg)): ?>
            <div class="alert alert-warning"><?php echo $error_msg; ?></div>
        <?php endif; ?>

        <div class="row g-4">
            <?php foreach($jobs as $job): 
                // Check if this job should be highlighted
                $is_highlighted = ($highlight_job_id && $job['id'] == $highlight_job_id);
                $highlight_class = $is_highlighted ? 'border-primary border-3 shadow-lg' : '';
            ?>
            <div class="col-md-6 col-lg-4" <?php if($is_highlighted) echo 'id="highlighted-job"'; ?>>
                <div class="card h-100 border-0 shadow-sm hover-card <?php echo $highlight_class; ?>">
                    <?php if($is_highlighted): ?>
                        <div class="bg-primary text-white px-3 py-2 small">
                            <i class="fas fa-star me-1"></i>From your notification
                        </div>
                    <?php endif; ?>
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <h5 class="fw-bold mb-1"><?php echo htmlspecialchars($job['title']); ?></h5>
                                <p class="text-muted mb-0 small"><?php echo htmlspecialchars($job['company_name']); ?></p>
                            </div>
                            <span class="badge bg-light text-primary"><?php echo htmlspecialchars($job['type']); ?></span>
                        </div>
                        
                        <div class="mb-3">
                            <small class="text-muted"><i class="fas fa-map-marker-alt me-1"></i> <?php echo htmlspecialchars($job['location']); ?></small>
                            <span class="mx-2 text-muted">•</span>
                            <small class="text-muted"><i class="fas fa-money-bill-wave me-1"></i> <?php echo htmlspecialchars($job['salary_range']); ?></small>
                        </div>
                        
                        <?php if(!empty($job['deadline'])): ?>
                            <div class="mb-3">
                                <small class="text-danger"><i class="fas fa-clock me-1"></i> Deadline: <?php echo date('M d, Y', strtotime($job['deadline'])); ?></small>
                            </div>
                        <?php endif; ?>

                        <p class="text-muted small mb-2">
                            <?php echo substr(htmlspecialchars($job['description']), 0, 100) . '...'; ?>
                        </p>

                        <?php if(!empty($job['eligibility_criteria'])): ?>
                            <div class="alert alert-light border-start border-primary border-3 py-2 px-3 mb-3">
                                <small class="text-primary fw-bold"><i class="fas fa-check-circle me-1"></i> Eligibility:</small>
                                <small class="text-muted d-block mt-1">
                                    <?php echo substr(htmlspecialchars($job['eligibility_criteria']), 0, 100) . (strlen($job['eligibility_criteria']) > 100 ? '...' : ''); ?>
                                </small>
                            </div>
                        <?php endif; ?>

                        <div class="d-flex gap-2 mb-3">
                            <?php 
                            $share_url = "http://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] . "?job_id=" . $job['id'];
                            $share_text = "Check out this job: " . $job['title'] . " at " . $job['company_name'];
                            ?>
                        </div>

                        <?php 
                        $is_applied = in_array($job['id'], $applied_jobs);
                        // Compare dates only - deadline passed if current date is AFTER the deadline date
                        $deadline_passed = !empty($job['deadline']) && strtotime($job['deadline']) < strtotime(date('Y-m-d'));
                        ?>

                        <?php if($is_applied): ?>
                            <button class="btn btn-secondary w-100" disabled>
                                <i class="fas fa-check me-2"></i>Already Applied
                            </button>
                        <?php elseif($deadline_passed): ?>
                            <button class="btn btn-secondary w-100" disabled>
                                <i class="fas fa-ban me-2"></i>Deadline Passed
                            </button>
                        <?php else: ?>
                            <form method="POST">
                                <input type="hidden" name="apply_job_id" value="<?php echo $job['id']; ?>">
                                <button type="submit" class="btn btn-outline-primary w-100">Apply Now</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            
            <?php if(empty($jobs)): ?>
                <div class="col-12 text-center py-5">
                    <p class="text-muted">No active jobs found matching your criteria.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/active_link.js"></script>
    <script>
        // Auto-scroll to highlighted job from notification
        document.addEventListener('DOMContentLoaded', function() {
            const highlightedJob = document.getElementById('highlighted-job');
            if (highlightedJob) {
                // Wait a bit for page to fully render
                setTimeout(function() {
                    highlightedJob.scrollIntoView({ 
                        behavior: 'smooth', 
                        block: 'center' 
                    });
                    
                    // Add a subtle pulse animation
                    const card = highlightedJob.querySelector('.card');
                    if (card) {
                        card.style.animation = 'pulse 1s ease-in-out 2';
                    }
                }, 300);
            }
        });
    </script>
    <style>
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.02); }
        }
        .hover-card {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .hover-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1) !important;
        }
    </style>
</body>
</html>
