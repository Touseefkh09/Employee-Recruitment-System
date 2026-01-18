<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'recruiter') {
    header("Location: ../login.php");
    exit;
}
include '../includes/db.php';
include '../includes/maintenance_check.php';


// Get recruiter name
$stmt = $pdo->prepare("SELECT company_name FROM recruiters WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$recruiter_data = $stmt->fetch();
$recruiter_name = $recruiter_data['company_name'] ?? 'Recruiter';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recruiter Dashboard - Employee Recruitment System</title>
    <link rel="icon" href="../images/logo.png" type="image/png">
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
                    <li class="nav-item"><a class="nav-link active" href="dashboard.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="post_job.php">Post Job</a></li>
                    <li class="nav-item"><a class="nav-link" href="manage_jobs.php">Manage Jobs</a></li>
                    <li class="nav-item"><a class="nav-link" href="messages.php">Messages</a></li>
                    <li class="nav-item"><a class="nav-link" href="feedback.php">Feedback</a></li>
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

    <div class="container" style="margin-top: 100px;">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold">Recruiter Dashboard</h2>
                <p class="text-muted">Manage your job postings and applications.</p>
            </div>
            <a href="post_job.php" class="btn btn-primary"><i class="fas fa-plus me-2"></i>Post New Job</a>
        </div>

        <?php
        // Get Recruiter ID
        $stmt = $pdo->prepare("SELECT id FROM recruiters WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $recruiter_id = $stmt->fetchColumn();

        // Fetch Stats
        $active_jobs = $pdo->prepare("SELECT COUNT(*) FROM jobs WHERE recruiter_id = ? AND status = 'active'");
        $active_jobs->execute([$recruiter_id]);
        $active_jobs_count = $active_jobs->fetchColumn();

        $total_applicants = $pdo->prepare("SELECT COUNT(*) FROM applications JOIN jobs ON applications.job_id = jobs.id WHERE jobs.recruiter_id = ?");
        $total_applicants->execute([$recruiter_id]);
        $total_applicants_count = $total_applicants->fetchColumn();

        $hired_candidates = $pdo->prepare("SELECT COUNT(*) FROM applications JOIN jobs ON applications.job_id = jobs.id WHERE jobs.recruiter_id = ? AND applications.status = 'hired'");
        $hired_candidates->execute([$recruiter_id]);
        $hired_candidates_count = $hired_candidates->fetchColumn();
        ?>

        <div class="row g-4 mb-5">
            <div class="col-md-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body p-4 d-flex align-items-center">
                        <div class="bg-primary bg-opacity-10 p-3 rounded-circle text-primary me-3">
                            <i class="fas fa-briefcase fa-2x"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1">Active Jobs</h6>
                            <h3 class="fw-bold mb-0"><?php echo $active_jobs_count; ?></h3>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body p-4 d-flex align-items-center">
                        <div class="bg-info bg-opacity-10 p-3 rounded-circle text-info me-3">
                            <i class="fas fa-users fa-2x"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1">Total Applicants</h6>
                            <h3 class="fw-bold mb-0"><?php echo $total_applicants_count; ?></h3>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body p-4 d-flex align-items-center">
                        <div class="bg-success bg-opacity-10 p-3 rounded-circle text-success me-3">
                            <i class="fas fa-user-check fa-2x"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1">Hired Candidates</h6>
                            <h3 class="fw-bold mb-0"><?php echo $hired_candidates_count; ?></h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-bold">Recent Applications</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Candidate</th>
                                <th>Job Title</th>
                                <th>Applied Date</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $stmt = $pdo->prepare("
                                SELECT a.*, j.title as job_title, c.full_name as candidate_name, u.email as candidate_email
                                FROM applications a
                                JOIN jobs j ON a.job_id = j.id
                                JOIN candidates c ON a.candidate_id = c.id
                                JOIN users u ON c.user_id = u.id
                                WHERE j.recruiter_id = ?
                                ORDER BY a.applied_at DESC
                                LIMIT 5
                            ");
                            $stmt->execute([$recruiter_id]);
                            $applications = $stmt->fetchAll();

                            if (count($applications) > 0):
                                foreach ($applications as $app):
                            ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="bg-light rounded-circle p-2 me-2">
                                            <i class="fas fa-user text-secondary"></i>
                                        </div>
                                        <div>
                                            <div class="fw-bold"><?php echo htmlspecialchars($app['candidate_name']); ?></div>
                                            <div class="small text-muted"><?php echo htmlspecialchars($app['candidate_email']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($app['job_title']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($app['applied_at'])); ?></td>
                                <td>
                                    <span class="badge bg-<?php 
                                        echo match($app['status']) {
                                            'pending' => 'warning',
                                            'reviewed' => 'info',
                                            'interviewed' => 'primary',
                                            'hired' => 'success',
                                            'rejected' => 'danger',
                                            default => 'secondary'
                                        };
                                    ?>"><?php echo ucfirst($app['status']); ?></span>
                                </td>
                                <td>
                                    <a href="view_applications.php?job_id=<?php echo $app['job_id']; ?>" class="btn btn-sm btn-outline-primary">View</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">No applications received yet.</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/active_link.js"></script>
</body>
</html>
