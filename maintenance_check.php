<?php
// Maintenance Mode Check
// This file should be included at the top of candidate and recruiter pages
// Admins are exempt from maintenance mode

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Only check maintenance mode for non-admin users
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    // Determine the correct path to db.php
    $db_path = '';
    if (strpos($_SERVER['PHP_SELF'], '/candidate/') !== false || 
        strpos($_SERVER['PHP_SELF'], '/recruiter/') !== false) {
        $db_path = '../includes/db.php';
    } else {
        $db_path = 'includes/db.php';
    }
    
    // Include database connection if not already included
    if (!isset($pdo)) {
        include_once $db_path;
    }
    
    // Check if maintenance mode is enabled
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'maintenance_mode'");
        $stmt->execute();
        $maintenance = $stmt->fetchColumn();
        
        if ($maintenance == '1') {
            // Maintenance mode is ON - block access for non-admins
            session_destroy();
            ?>
            <!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <link rel="icon" href="<?php echo strpos($_SERVER['PHP_SELF'], '/candidate/') !== false || strpos($_SERVER['PHP_SELF'], '/recruiter/') !== false ? '../' : ''; ?>images/logo.png" type="image/png">
                <title>Maintenance Mode - Employee Recruitment System</title>
                <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
                <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
                <style>
                    body {
                        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                        min-height: 100vh;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        font-family: 'Inter', sans-serif;
                    }
                    .maintenance-card {
                        background: white;
                        border-radius: 20px;
                        padding: 3rem;
                        box-shadow: 0 20px 60px rgba(0,0,0,0.3);
                        text-align: center;
                        max-width: 500px;
                    }
                    .maintenance-icon {
                        font-size: 5rem;
                        color: #667eea;
                        margin-bottom: 1.5rem;
                        animation: pulse 2s infinite;
                    }
                    @keyframes pulse {
                        0%, 100% { transform: scale(1); }
                        50% { transform: scale(1.1); }
                    }
                </style>
            </head>
            <body>
                <div class="maintenance-card">
                    <i class="fas fa-tools maintenance-icon"></i>
                    <h1 class="fw-bold mb-3">We'll Be Right Back!</h1>
                    <p class="text-muted mb-4">
                        Our site is currently undergoing scheduled maintenance. 
                        We should be back online shortly. Thank you for your patience!
                    </p>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Please check back in a few minutes.
                    </div>
                </div>
            </body>
            </html>
            <?php
            exit;
        }
    } catch (Exception $e) {
        // If there's an error checking maintenance mode, continue normally
        // This prevents the site from breaking if the settings table doesn't exist
    }
}
?>
