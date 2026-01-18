<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'recruiter') {
    header("Location: ../login.php");
    exit;
}
include '../includes/db.php';

// Get Recruiter ID
$stmt = $pdo->prepare("SELECT id FROM recruiters WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$recruiter_id = $stmt->fetchColumn();

// Handle status toggle
if (isset($_POST['toggle_status']) && isset($_POST['job_id'])) {
    $job_id = $_POST['job_id'];
    
    // Verify the job belongs to this recruiter
    $stmt = $pdo->prepare("SELECT status FROM jobs WHERE id = ? AND recruiter_id = ?");
    $stmt->execute([$job_id, $recruiter_id]);
    $job = $stmt->fetch();
    
    if ($job) {
        // Toggle status
        $new_status = ($job['status'] == 'active') ? 'closed' : 'active';
        
        $stmt = $pdo->prepare("UPDATE jobs SET status = ? WHERE id = ?");
        $stmt->execute([$new_status, $job_id]);
        
        $_SESSION['success'] = "Job status updated to " . ucfirst($new_status) . " successfully!";
    } else {
        $_SESSION['error'] = "Job not found or you don't have permission to modify it.";
    }
    header("Location: manage_jobs.php");
    exit;
}

// Get messages from session and clear them
$success = isset($_SESSION['success']) ? $_SESSION['success'] : '';
$error = isset($_SESSION['error']) ? $_SESSION['error'] : '';
unset($_SESSION['success']);
unset($_SESSION['error']);

// Fetch Jobs with applicant count
$stmt = $pdo->prepare("SELECT jobs.*, COUNT(applications.id) as applicant_count 
                       FROM jobs 
                       LEFT JOIN applications ON jobs.id = applications.job_id 
                       WHERE jobs.recruiter_id = ? 
                       GROUP BY jobs.id 
                       ORDER BY jobs.created_at DESC");
$stmt->execute([$recruiter_id]);
$jobs = $stmt->fetchAll();
?>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Jobs - Employee Recruitment System</title>
    <link rel="icon" href="../images/logo.png" type="image/png">
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
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="fw-bold">My Job Postings</h2>
            <a href="post_job.php" class="btn btn-primary"><i class="fas fa-plus me-2"></i>Post New Job</a>
        </div>

        <?php if(!empty($success)): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if(!empty($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if(isset($_GET['error']) && $_GET['error'] == 'already_edited'): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-circle me-2"></i>
                This job has already been edited once and cannot be edited again.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">Job Title</th>
                                <th>Posted Date</th>
                                <th>Status</th>
                                <th>Applicants</th>
                                <th class="text-end pe-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($jobs as $job): ?>
                            <tr>
                                <td class="ps-4">
                                    <h6 class="fw-bold mb-0"><?php echo htmlspecialchars($job['title']); ?></h6>
                                    <small class="text-muted"><?php echo htmlspecialchars($job['location']); ?></small>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($job['created_at'])); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $job['status'] == 'active' ? 'success' : 'secondary'; ?>">
                                        <?php echo ucfirst($job['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-info rounded-pill"><?php echo $job['applicant_count']; ?></span>
                                </td>
                                <td class="text-end pe-4">
                                    <a href="view_applications.php?job_id=<?php echo $job['id']; ?>" class="btn btn-sm btn-outline-primary me-1" title="View Applicants">
                                        <i class="fas fa-users"></i>
                                    </a>
                                    <?php if ($job['edit_count'] >= 1): ?>
                                        <button class="btn btn-sm btn-secondary me-1" disabled title="Already edited once">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <span class="badge bg-warning text-dark">Edited</span>
                                    <?php else: ?>
                                        <a href="edit_job.php?id=<?php echo $job['id']; ?>" class="btn btn-sm btn-outline-secondary me-1" title="Edit Job (One time only)">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                    <?php endif; ?>
                                    
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="job_id" value="<?php echo $job['id']; ?>">
                                        <input type="hidden" name="toggle_status" value="1">
                                        <?php if ($job['status'] == 'active'): ?>
                                            <button type="submit" class="btn btn-sm btn-warning" title="Close this job posting" onclick="return confirm('Are you sure you want to close this job? It will no longer appear in candidate searches.');">
                                                <i class="fas fa-lock me-1"></i>Close
                                            </button>
                                        <?php else: ?>
                                            <button type="submit" class="btn btn-sm btn-success" title="Reopen this job posting" onclick="return confirm('Are you sure you want to reopen this job? It will appear in candidate searches again.');">
                                                <i class="fas fa-lock-open me-1"></i>Reopen
                                            </button>
                                        <?php endif; ?>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/active_link.js"></script>
    <script>
        // Auto-dismiss alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                setTimeout(function() {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }, 5000); // 5 seconds
            });
        });
    </script>
</body>
</html>
