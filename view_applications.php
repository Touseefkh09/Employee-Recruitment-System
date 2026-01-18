<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'recruiter') {
    header("Location: ../login.php");
    exit;
}
include '../includes/db.php';

$job_id = $_GET['job_id'] ?? null;
$status_filter = $_GET['status'] ?? '';

if (!$job_id) {
    header("Location: manage_jobs.php");
    exit;
}

// Verify Job Ownership
$stmt = $pdo->prepare("SELECT id, title FROM jobs WHERE id = ? AND recruiter_id = (SELECT id FROM recruiters WHERE user_id = ?)");
$stmt->execute([$job_id, $_SESSION['user_id']]);
$job = $stmt->fetch();

if (!$job) {
    echo "Job not found or access denied.";
    exit;
}

// Fetch Applicants with Filter
$query = "SELECT applications.id as app_id, applications.status, applications.applied_at, 
                 candidates.full_name, candidates.resume_path, candidates.skills, candidates.phone, candidates.user_id as candidate_user_id,
                 users.email 
          FROM applications 
          JOIN candidates ON applications.candidate_id = candidates.id 
          JOIN users ON candidates.user_id = users.id 
          WHERE applications.job_id = ?";

if ($status_filter) {
    $query .= " AND applications.status = ?";
    $params = [$job_id, $status_filter];
} else {
    $params = [$job_id];
}

$query .= " ORDER BY applications.applied_at DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$applicants = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Applicants - Employee Recruitment System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg fixed-top bg-white border-bottom">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php"><i class="fas fa-chart-bar me-2"></i>Employee Recruitment System</a>
            <div class="ms-auto">
                <a href="manage_jobs.php" class="btn btn-outline-secondary btn-sm">Back to Jobs</a>
            </div>
        </div>
    </nav>

    <div class="container py-5" style="margin-top: 60px;">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold mb-1">Applicants for: <?php echo htmlspecialchars($job['title']); ?></h2>
                <p class="text-muted"><?php echo count($applicants); ?> application(s) found</p>
            </div>
            
            <!-- Filter -->
            <form method="GET" class="d-flex">
                <input type="hidden" name="job_id" value="<?php echo $job_id; ?>">
                <select name="status" class="form-select me-2" onchange="this.form.submit()">
                    <option value="">All Statuses</option>
                    <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="shortlisted" <?php echo $status_filter == 'shortlisted' ? 'selected' : ''; ?>>Shortlisted</option>
                    <option value="interviewing" <?php echo $status_filter == 'interviewing' ? 'selected' : ''; ?>>Interviewing</option>
                    <option value="hired" <?php echo $status_filter == 'hired' ? 'selected' : ''; ?>>Hired</option>
                    <option value="rejected" <?php echo $status_filter == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                </select>
            </form>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">Applicant Name</th>
                                <th>Email</th>
                                <th>Applied Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($applicants as $app): ?>
                            <tr>
                                <td class="ps-4">
                                    <h6 class="fw-bold mb-0"><?php echo htmlspecialchars($app['full_name']); ?></h6>
                                </td>
                                <td><?php echo htmlspecialchars($app['email']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($app['applied_at'])); ?></td>
                                <td>
                                    <span class="badge bg-<?php 
                                        echo match($app['status']) {
                                            'pending' => 'warning',
                                            'approved' => 'success',
                                            'shortlisted' => 'info',
                                            'interviewing' => 'primary',
                                            'hired' => 'success',
                                            'rejected' => 'danger',
                                            default => 'secondary'
                                        };
                                    ?>"><?php echo ucfirst($app['status']); ?></span>
                                </td>
                            </tr>
                            <?php endforeach; ?>

                            <?php if(empty($applicants)): ?>
                            <tr>
                                <td colspan="4" class="text-center py-5 text-muted">
                                    <i class="fas fa-inbox fa-3x mb-3 d-block"></i>
                                    No applicants found matching this criteria.
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</body>
</html>
