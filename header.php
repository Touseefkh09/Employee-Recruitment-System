<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Determine relative path to root
// A more robust way: check if we are in a subfolder like 'admin', 'recruiter', 'candidate'
$in_subfolder = strpos($_SERVER['PHP_SELF'], '/admin/') !== false || 
                strpos($_SERVER['PHP_SELF'], '/recruiter/') !== false || 
                strpos($_SERVER['PHP_SELF'], '/candidate/') !== false;
$base_path = $in_subfolder ? '../' : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="<?php echo $base_path; ?>images/logo.png" type="image/png">
    <title>Employee Recruitment System</title>
    
    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo $base_path; ?>assets/css/style.css">
</head>
<body>

<!-- Navigation -->
<nav class="navbar navbar-expand-lg fixed-top">
    <div class="container">
        <a class="navbar-brand" href="<?php echo $base_path; ?>index.php">
            <i class="fas fa-chart-bar me-2"></i>Employee Recruitment System
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto align-items-center">
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo $base_path; ?>index.php#home">Home</a>
                </li>
                
                <?php if(isset($_SESSION['user_id'])): ?>
                    <?php
                    // Get unread notifications count
                    $unread_count = 0;
                    if(isset($pdo)) {
                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
                        $stmt->execute([$_SESSION['user_id']]);
                        $unread_count = $stmt->fetchColumn();
                    }
                    ?>
                    <!-- Logged In Links -->
                    <?php if($_SESSION['role'] === 'candidate'): ?>
                        <li class="nav-item"><a class="nav-link" href="<?php echo $base_path; ?>candidate/dashboard.php">Dashboard</a></li>
                        <li class="nav-item"><a class="nav-link" href="<?php echo $base_path; ?>candidate/jobs.php">Browse Jobs</a></li>
                        <li class="nav-item"><a class="nav-link" href="<?php echo $base_path; ?>candidate/my_applications.php">My Applications</a></li>
                    <?php elseif($_SESSION['role'] === 'recruiter'): ?>
                        <li class="nav-item"><a class="nav-link" href="<?php echo $base_path; ?>recruiter/dashboard.php">Dashboard</a></li>
                        <li class="nav-item"><a class="nav-link" href="<?php echo $base_path; ?>recruiter/post_job.php">Post Job</a></li>
                        <li class="nav-item"><a class="nav-link" href="<?php echo $base_path; ?>recruiter/manage_jobs.php">My Jobs</a></li>
                    <?php elseif($_SESSION['role'] === 'admin'): ?>
                        <li class="nav-item"><a class="nav-link" href="<?php echo $base_path; ?>admin/dashboard.php">Dashboard</a></li>
                    <?php endif; ?>

                    <li class="nav-item"><a class="nav-link" href="<?php echo $base_path; ?>messages.php">Messages</a></li>
                    
                    <li class="nav-item">
                        <a class="nav-link position-relative" href="<?php echo $base_path; ?>notifications.php">
                            Notifications
                            <?php if($unread_count > 0): ?>
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.6rem;">
                                    <?php echo $unread_count; ?>
                                </span>
                            <?php endif; ?>
                        </a>
                    </li>

                    <li class="nav-item ms-lg-3">
                        <a class="btn btn-outline-danger btn-sm" href="<?php echo $base_path; ?>logout.php" onclick="return confirm('Are you sure you want to logout?');">Logout</a>
                    </li>
                <?php else: ?>
                    <!-- Guest Links -->
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo $base_path; ?>index.php#jobs">Browse Jobs</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo $base_path; ?>index.php#features">Features</a>
                    </li>
                    <li class="nav-item ms-lg-3">
                        <a class="btn btn-outline-primary me-2" href="<?php echo $base_path; ?>login.php">Login</a>
                    </li>
                    <li class="nav-item">
                        <a class="btn btn-primary" href="<?php echo $base_path; ?>register.php">Get Started</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
