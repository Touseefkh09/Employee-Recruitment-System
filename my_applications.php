<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'candidate') {
    header("Location: ../login.php");
    exit;
}
include '../includes/db.php';

// Fetch my applications with all details
$stmt = $pdo->prepare("SELECT applications.*, jobs.title, jobs.location, jobs.salary_range, recruiters.company_name 
                       FROM applications 
                       JOIN jobs ON applications.job_id = jobs.id 
                       JOIN recruiters ON jobs.recruiter_id = recruiters.id 
                       WHERE applications.candidate_id = (SELECT id FROM candidates WHERE user_id = ?)
                       ORDER BY applications.applied_at DESC");
$stmt->execute([$_SESSION['user_id']]);
$applications = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="../images/logo.png" type="image/png">
    <title>My Applications - Employee Recruitment System</title>
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
        <?php if(isset($_GET['success']) && $_GET['success'] == 'applied'): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i> Application submitted successfully!
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <h2 class="fw-bold mb-4">My Applications</h2>

        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">Job Role</th>
                                <th>Company</th>
                                <th>Applied Date</th>
                                <th>Status</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($applications as $app): ?>
                            <tr>
                                <td class="ps-4">
                                    <h6 class="mb-0 fw-bold"><?php echo htmlspecialchars($app['title']); ?></h6>
                                    <small class="text-muted"><?php echo htmlspecialchars($app['location']); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($app['company_name']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($app['applied_at'])); ?></td>
                                <td>
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
                                </td>
                                <td class="text-center">
                                    <button class="btn btn-sm btn-outline-primary" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#trackingModal<?php echo $app['id']; ?>"
                                            title="View Application Timeline">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>

                            <?php if(empty($applications)): ?>
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">
                                    You haven't applied to any jobs yet. <a href="jobs.php">Browse Jobs</a>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Modals (outside table for proper functionality) -->
    <?php foreach($applications as $app): ?>
    <div class="modal fade" id="trackingModal<?php echo $app['id']; ?>" tabindex="-1" aria-labelledby="modalLabel<?php echo $app['id']; ?>" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="modalLabel<?php echo $app['id']; ?>">
                        <i class="fas fa-route me-2"></i>Application Timeline
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <h6 class="fw-bold"><?php echo htmlspecialchars($app['title']); ?></h6>
                        <small class="text-muted">at <?php echo htmlspecialchars($app['company_name']); ?></small>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th width="30%">Phase</th>
                                    <th width="15%" class="text-center">Status</th>
                                    <th width="55%">Details</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $current_status = $app['status'];
                                $rejection_reason = $app['rejection_reason'];
                                $test_marks = $app['test_marks'];
                                $interview_marks = $app['interview_marks'];
                                
                                // Define all possible phases
                                $phases = [
                                    'pending' => ['name' => 'Application Submitted', 'icon' => 'fa-paper-plane'],
                                    'approved' => ['name' => 'Document Verification', 'icon' => 'fa-check-circle'],
                                    'shortlisted' => ['name' => 'Shortlisted', 'icon' => 'fa-list-check'],
                                    'test' => ['name' => 'Test Phase', 'icon' => 'fa-file-pen'],
                                    'interviewing' => ['name' => 'Interview Phase', 'icon' => 'fa-users'],
                                    'hired' => ['name' => 'Final Decision', 'icon' => 'fa-trophy'],
                                    'rejected' => ['name' => 'Application Status', 'icon' => 'fa-times-circle'],
                                    'not_selected' => ['name' => 'Final Decision', 'icon' => 'fa-times-circle']
                                ];
                                
                                // Determine which phases to show based on current status
                                $status_order = ['pending', 'approved', 'shortlisted', 'test', 'interviewing', 'hired'];
                                
                                if ($current_status === 'rejected') {
                                    $show_phases = ['pending', 'rejected'];
                                } elseif ($current_status === 'not_selected') {
                                    $show_phases = ['pending', 'approved', 'shortlisted', 'test', 'interviewing', 'not_selected'];
                                } else {
                                    $current_index = array_search($current_status, $status_order);
                                    $show_phases = array_slice($status_order, 0, $current_index + 1);
                                }
                                
                                foreach ($show_phases as $phase):
                                    $phase_info = $phases[$phase];
                                    $is_current = ($phase === $current_status);
                                    $is_completed = array_search($phase, $show_phases) < array_search($current_status, $show_phases);
                                    
                                    // Determine status icon
                                    if ($phase === 'rejected' || $phase === 'not_selected') {
                                        $status_icon = '<i class="fas fa-times-circle text-danger fs-4"></i>';
                                    } elseif ($is_completed || $is_current) {
                                        $status_icon = '<i class="fas fa-check-circle text-success fs-4"></i>';
                                    } else {
                                        $status_icon = '<i class="far fa-circle text-muted fs-4"></i>';
                                    }
                                    
                                    // Determine details/reason
                                    $details = '';
                                    switch($phase) {
                                        case 'pending':
                                            $details = 'Application submitted on ' . date('M d, Y', strtotime($app['applied_at']));
                                            break;
                                        case 'approved':
                                            $details = 'Documents verified and approved';
                                            break;
                                        case 'shortlisted':
                                            $details = 'Your profile has been shortlisted for further evaluation';
                                            break;
                                        case 'test':
                                            if ($test_marks) {
                                                $details = 'Test completed - Score: ' . $test_marks . '%';
                                            } else {
                                                $details = 'Selected for test phase';
                                            }
                                            break;
                                        case 'interviewing':
                                            if ($interview_marks) {
                                                $details = 'Interview completed - Score: ' . $interview_marks . '%';
                                            } else {
                                                $details = 'Called for interview';
                                            }
                                            break;
                                        case 'hired':
                                            $details = 'Congratulations! You have been selected';
                                            if ($test_marks && $interview_marks) {
                                                $details .= '<br><small class="text-muted">Test: ' . $test_marks . '% | Interview: ' . $interview_marks . '%</small>';
                                            }
                                            break;
                                        case 'rejected':
                                            $details = 'Application rejected';
                                            if ($rejection_reason) {
                                                $details .= '<br><small class="text-danger">Reason: ' . htmlspecialchars($rejection_reason) . '</small>';
                                            }
                                            break;
                                        case 'not_selected':
                                            $details = 'Not selected for the position';
                                            if ($test_marks && $interview_marks) {
                                                $details .= '<br><small class="text-muted">Test: ' . $test_marks . '% | Interview: ' . $interview_marks . '%</small>';
                                                $details .= '<br><small class="text-danger">Minimum 50% required in both</small>';
                                            }
                                            break;
                                    }
                                ?>
                                <tr class="<?php echo $is_current ? 'table-primary' : ''; ?>">
                                    <td>
                                        <i class="fas <?php echo $phase_info['icon']; ?> me-2"></i>
                                        <strong><?php echo $phase_info['name']; ?></strong>
                                    </td>
                                    <td class="text-center">
                                        <?php echo $status_icon; ?>
                                    </td>
                                    <td><?php echo $details; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if ($current_status === 'pending'): ?>
                    <div class="alert alert-info mb-0">
                        <i class="fas fa-info-circle me-2"></i>
                        Your application is under review. You will be notified of any updates.
                    </div>
                    <?php elseif ($current_status === 'hired'): ?>
                    <div class="alert alert-success mb-0">
                        <i class="fas fa-trophy me-2"></i>
                        Congratulations! Please wait for further instructions from the company.
                    </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    </div>
    <?php endforeach; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
