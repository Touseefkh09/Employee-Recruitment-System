<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'recruiter') {
    header("Location: ../login.php");
    exit;
}
include '../includes/db.php';

$success = '';
$error = '';

// Get recruiter ID
$stmt = $pdo->prepare("SELECT id FROM recruiters WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$recruiter = $stmt->fetch();
$recruiter_id = $recruiter['id'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = $_POST['title'];
    $description = $_POST['description'];
    $eligibility_criteria = $_POST['eligibility_criteria'];
    $location = $_POST['location'];
    $salary = $_POST['salary'];
    $type = $_POST['type'];
    $deadline = $_POST['deadline'];

    try {
        $stmt = $pdo->prepare("INSERT INTO jobs (recruiter_id, title, description, eligibility_criteria, location, salary_range, type, deadline) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$recruiter_id, $title, $description, $eligibility_criteria, $location, $salary, $type, $deadline]);
        
        // Get the newly created job ID
        $job_id = $pdo->lastInsertId();
        
        // Get the company name for notification
        $company_stmt = $pdo->prepare("SELECT company_name FROM recruiters WHERE id = ?");
        $company_stmt->execute([$recruiter_id]);
        $company_name = $company_stmt->fetchColumn();
        
        // Notify all admins about the new job
        $get_admins = $pdo->prepare("SELECT id FROM users WHERE role = 'admin'");
        $get_admins->execute();
        $admins = $get_admins->fetchAll(PDO::FETCH_COLUMN);
        
        if (!empty($admins)) {
            $admin_msg = "New job posted by " . $company_name . ": " . $title;
            
            // Check if notifications table has job_id column
            try {
                $check_col = $pdo->query("SHOW COLUMNS FROM notifications LIKE 'job_id'");
                $has_job_id_col = $check_col && $check_col->rowCount() > 0;
            } catch (Exception $e) {
                $has_job_id_col = false;
            }
            
            if ($has_job_id_col) {
                // Use enhanced notification with job_id
                $notify_admin = $pdo->prepare("INSERT INTO notifications (user_id, message, job_id) VALUES (?, ?, ?)");
                foreach ($admins as $admin_id) {
                    $notify_admin->execute([$admin_id, $admin_msg, $job_id]);
                }
            } else {
                // Fallback to basic notification
                $notify_admin = $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
                foreach ($admins as $admin_id) {
                    $notify_admin->execute([$admin_id, $admin_msg]);
                }
            }
        }
        
        // Notify all candidates about the new job
        $candidates_stmt = $pdo->query("SELECT user_id FROM candidates");
        $candidates = $candidates_stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (!empty($candidates)) {
            $notification_message = "New job posted: " . $title . " at " . $company_name . " in " . $location;
            
            // Check if the enhanced notification table structure exists
            $check_columns = $pdo->query("SHOW COLUMNS FROM notifications LIKE 'type'");
            $has_type_column = $check_columns->rowCount() > 0;
            
            if ($has_type_column) {
                // Use enhanced notification with type and job_id
                $notify_stmt = $pdo->prepare("INSERT INTO notifications (user_id, message, type, job_id) VALUES (?, ?, 'job_post', ?)");
                foreach ($candidates as $candidate_user_id) {
                    $notify_stmt->execute([$candidate_user_id, $notification_message, $job_id]);
                }
            } else {
                // Fallback to basic notification
                $notify_stmt = $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
                foreach ($candidates as $candidate_user_id) {
                    $notify_stmt->execute([$candidate_user_id, $notification_message]);
                }
            }
        }
        
        $success = "Job posted successfully! Admins and all candidates have been notified!";
    } catch (Exception $e) {
        $error = "Error posting job: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Post a Job - Employee Recruitment System</title>
    <link rel="icon" href="../images/logo.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card border-0 shadow-lg rounded-4">
                    <div class="card-body p-5">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h2 class="fw-bold mb-0">Post a New Job</h2>
                            <a href="dashboard.php" class="btn btn-outline-secondary btn-sm">Back to Dashboard</a>
                        </div>

                        <?php if($success): ?>
                            <div class="alert alert-success"><?php echo $success; ?></div>
                        <?php endif; ?>
                        <?php if($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>

                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Job Title<span class="text-danger">*</span></label>
                                <input type="text" name="title" class="form-control" required placeholder="e.g. Senior Software Engineer">
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Location<span class="text-danger">*</span></label>
                                    <input type="text" name="location" class="form-control" required placeholder="e.g. New York, Remote">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Job Type</label>
                                    <select name="type" class="form-select">
                                        <option value="Full-time">Full Time</option>
                                        <option value="Part-time">Part Time</option>
                                        <option value="Contract">Contract</option>
                                        <option value="Internship">Internship</option>
                                        <option value="Remote">Remote</option>
                                    </select>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Salary Range</label>
                                    <input type="text" name="salary" class="form-control" placeholder="e.g. $80k - $100k">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Application Deadline<span class="text-danger">*</span></label>
                                    <input type="date" name="deadline" class="form-control" required min="<?php echo date('Y-m-d'); ?>">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Job Description<span class="text-danger">*</span></label>
                                <textarea name="description" class="form-control" rows="5" required></textarea>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Eligibility Criteria<span class="text-danger">*</span></label>
                                <textarea name="eligibility_criteria" class="form-control" rows="4" required placeholder="List key qualifications, education requirements, experience needed..."></textarea>
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg">Post Job</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/active_link.js"></script>
</body>
</html>
