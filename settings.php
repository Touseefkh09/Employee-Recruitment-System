<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}
include '../includes/db.php';


$pdo->exec("CREATE TABLE IF NOT EXISTS settings (
    setting_key VARCHAR(50) PRIMARY KEY,
    setting_value TEXT
)");


$defaults = [
    'site_name' => 'Employee Recruitment System',
    'contact_email' => 'helpemployeerecruitmentsystem@gmail.com',
    'maintenance_mode' => '0'
];

foreach ($defaults as $key => $value) {
    $stmt = $pdo->prepare("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES (?, ?)");
    $stmt->execute([$key, $value]);
}

// Handle Save
$error = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Validate contact email
    if (isset($_POST['contact_email'])) {
        $email = $_POST['contact_email'];
        if (!preg_match('/@gmail\.com$/i', $email)) {
            $error = "Contact email must end with @gmail.com";
        }
    }
    
    if (!$error) {
        foreach ($_POST as $key => $value) {
            if (array_key_exists($key, $defaults)) {
                $stmt = $pdo->prepare("REPLACE INTO settings (setting_key, setting_value) VALUES (?, ?)");
                $stmt->execute([$key, $value]);
            }
        }
        // Handle checkbox unchecked
        if (!isset($_POST['maintenance_mode'])) {
            $stmt = $pdo->prepare("REPLACE INTO settings (setting_key, setting_value) VALUES ('maintenance_mode', '0')");
            $stmt->execute();
        }
        $success = "Settings saved successfully.";
    }
}

// Fetch Settings
$settings = [];
$stmt = $pdo->query("SELECT * FROM settings");
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="../images/logo.png" type="image/png">
    <title>System Settings - Employee Recruitment System</title>
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
                    <li class="nav-item"><a class="nav-link" href="dashboard.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="users.php">Users</a></li>
                    <li class="nav-item"><a class="nav-link" href="jobs.php">Jobs</a></li>
                    <li class="nav-item"><a class="nav-link" href="manage_applications.php">Applications</a></li>
                    <li class="nav-item"><a class="nav-link" href="feedback.php">Feedback</a></li>
                    <li class="nav-item"><a class="nav-link active" href="settings.php">Settings</a></li>
                    <li class="nav-item ms-3"><a class="btn btn-outline-danger btn-sm" href="logout.php" onclick="return confirm('Are you sure you want to logout?');">Logout</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container" style="margin-top: 100px;">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <h2 class="fw-bold mb-4">System Settings</h2>

                <?php if(isset($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if(isset($error) && $error): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="card border-0 shadow-sm">
                    <div class="card-body p-4">
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Site Name</label>
                                <input type="text" name="site_name" class="form-control" value="<?php echo htmlspecialchars($settings['site_name']); ?>">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Contact Email <span class="text-danger">*</span></label>
                                <input type="email" 
                                       name="contact_email" 
                                       id="contact_email" 
                                       class="form-control" 
                                       value="<?php echo htmlspecialchars($settings['contact_email']); ?>" 
                                       pattern=".*@gmail\.com$" 
                                       required>
                                <div class="invalid-feedback">
                                    Email must end with @gmail.com
                                </div>
                                <div class="form-text">
                                    Must be a Gmail address (e.g., example@gmail.com)
                                </div>
                            </div>

                            <div class="mb-3 form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="maintenance_mode" value="1" id="maintenanceMode" <?php echo $settings['maintenance_mode'] == '1' ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="maintenanceMode">Maintenance Mode</label>
                                <div class="form-text">If enabled, only admins can access the site.</div>
                            </div>

                            <button type="submit" class="btn btn-primary">Save Changes</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/active_link.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const emailInput = document.getElementById('contact_email');
            const form = emailInput.closest('form');
            
            // Validate on blur
            emailInput.addEventListener('blur', function() {
                validateEmail(this);
            });
            
            // Validate on input (real-time)
            emailInput.addEventListener('input', function() {
                if (this.classList.contains('is-invalid')) {
                    validateEmail(this);
                }
            });
            
            // Validate on form submit
            form.addEventListener('submit', function(e) {
                if (!validateEmail(emailInput)) {
                    e.preventDefault();
                    e.stopPropagation();
                }
            });
            
            function validateEmail(input) {
                const value = input.value.trim();
                const gmailPattern = /@gmail\.com$/i;
                
                if (value === '') {
                    input.classList.add('is-invalid');
                    input.classList.remove('is-valid');
                    const feedback = input.nextElementSibling;
                    if (feedback && feedback.classList.contains('invalid-feedback')) {
                        feedback.textContent = 'Contact email is required.';
                    }
                    return false;
                } else if (!gmailPattern.test(value)) {
                    input.classList.add('is-invalid');
                    input.classList.remove('is-valid');
                    const feedback = input.nextElementSibling;
                    if (feedback && feedback.classList.contains('invalid-feedback')) {
                        feedback.textContent = 'Email must end with @gmail.com';
                    }
                    return false;
                } else {
                    input.classList.remove('is-invalid');
                    input.classList.add('is-valid');
                    return true;
                }
            }
        });
    </script>
</body>
</html>
