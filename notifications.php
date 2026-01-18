<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'candidate') {
    header("Location: ../login.php");
    exit;
}
include '../includes/db.php';

// Fetch Notifications
$stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$_SESSION['user_id']]);
$notifications = $stmt->fetchAll();

// Mark all as read
$update = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
$update->execute([$_SESSION['user_id']]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="../images/logo.png" type="image/png">
    <title>Notifications - Employee Recruitment System</title>
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
        <div class="row justify-content-center">
            <div class="col-md-8">
                <h2 class="fw-bold mb-4">
                    <i class="fas fa-bell me-2"></i>Notifications
                    <span class="badge bg-primary ms-2"><?php echo count($notifications); ?></span>
                </h2>

                <div class="list-group shadow-sm">
                    <?php foreach($notifications as $notif): 
                        // Check if type column exists
                        $notif_type = isset($notif['type']) ? $notif['type'] : 'general';
                        $job_id = isset($notif['job_id']) ? $notif['job_id'] : null;
                        
                        // Determine icon and color based on type
                        $icon = 'fa-bell';
                        $icon_color = 'text-primary';
                        $title = 'Notification';
                        
                        switch($notif_type) {
                            case 'job_post':
                                $icon = 'fa-briefcase';
                                $icon_color = 'text-success';
                                $title = 'New Job Posted';
                                break;
                            case 'application_update':
                                $icon = 'fa-file-alt';
                                $icon_color = 'text-info';
                                $title = 'Application Update';
                                break;
                            case 'message':
                                $icon = 'fa-envelope';
                                $icon_color = 'text-warning';
                                $title = 'New Message';
                                break;
                            default:
                                // Try to detect type from message content
                                if (stripos($notif['message'], 'New job posted') !== false) {
                                    $icon = 'fa-briefcase';
                                    $icon_color = 'text-success';
                                    $title = 'New Job Posted';
                                } elseif (stripos($notif['message'], 'application') !== false) {
                                    $icon = 'fa-file-alt';
                                    $icon_color = 'text-info';
                                    $title = 'Application Update';
                                } elseif (stripos($notif['message'], 'message') !== false) {
                                    $icon = 'fa-envelope';
                                    $icon_color = 'text-warning';
                                    $title = 'New Message';
                                }
                        }
                    ?>
                        <div class="list-group-item list-group-item-action p-4 <?php echo !$notif['is_read'] ? 'bg-light border-start border-4 border-primary' : ''; ?>">
                            <div class="d-flex align-items-start">
                                <div class="me-3">
                                    <i class="fas <?php echo $icon; ?> fa-2x <?php echo $icon_color; ?>"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="d-flex w-100 justify-content-between mb-2">
                                        <h6 class="mb-0 fw-bold"><?php echo $title; ?></h6>
                                        <small class="text-muted">
                                            <i class="far fa-clock me-1"></i>
                                            <?php echo date('M d, Y H:i', strtotime($notif['created_at'])); ?>
                                        </small>
                                    </div>
                                    <p class="mb-2"><?php echo htmlspecialchars($notif['message']); ?></p>
                                    
                                    <?php 
                                    // Get application_id and job_id from notification
                                    $application_id = isset($notif['application_id']) ? $notif['application_id'] : null;
                                    $job_id = isset($notif['job_id']) ? $notif['job_id'] : null;
                                    
                                    // Show appropriate action button based on notification type
                                    ?>
                                    
                                    <div class="mt-2">
                                        <?php if ($notif_type === 'application_update' && $application_id): ?>
                                            <!-- View Application Button for application status updates -->
                                            <a href="my_applications.php?highlight=<?php echo $application_id; ?>" class="btn btn-sm btn-primary">
                                                <i class="fas fa-eye me-1"></i>View Application
                                            </a>
                                        <?php elseif ($notif_type === 'job_post' && $job_id): ?>
                                            <!-- View Job Button for new job notifications -->
                                            <a href="jobs.php?job_id=<?php echo $job_id; ?>" class="btn btn-sm btn-success">
                                                <i class="fas fa-briefcase me-1"></i>View Job
                                            </a>
                                        <?php elseif (stripos($notif['message'], 'application') !== false): ?>
                                            <!-- Fallback: View All Applications if application_id not available -->
                                            <a href="my_applications.php" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-list me-1"></i>View My Applications
                                            </a>
                                        <?php elseif (stripos($notif['message'], 'job') !== false || stripos($notif['message'], 'Job') !== false): ?>
                                            <!-- Fallback: View All Jobs if job_id not available -->
                                            <a href="jobs.php" class="btn btn-sm btn-outline-success">
                                                <i class="fas fa-briefcase me-1"></i>Browse Jobs
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <?php if(empty($notifications)): ?>
                        <div class="list-group-item p-5 text-center text-muted">
                            <i class="fas fa-bell-slash fa-3x mb-3"></i>
                            <p>No notifications yet.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
