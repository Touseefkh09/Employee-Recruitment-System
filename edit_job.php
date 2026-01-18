<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'recruiter') {
    header("Location: ../login.php");
    exit;
}
include '../includes/db.php';

$job_id = $_GET['id'] ?? null;
if (!$job_id) {
    header("Location: manage_jobs.php");
    exit;
}


$stmt = $pdo->prepare("SELECT id FROM recruiters WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$recruiter_id = $stmt->fetchColumn();


$stmt = $pdo->prepare("SELECT * FROM jobs WHERE id = ? AND recruiter_id = ?");
$stmt->execute([$job_id, $recruiter_id]);
$job = $stmt->fetch();

if (!$job) {
    echo "Job not found or access denied.";
    exit;
}


if ($job['edit_count'] >= 1) {
    header("Location: manage_jobs.php?error=already_edited");
    exit;
}


if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = $_POST['title'];
    $description = $_POST['description'];
    $eligibility_criteria = $_POST['eligibility_criteria'];
    $location = $_POST['location'];
    $salary_range = $_POST['salary_range'];
    $type = $_POST['type'];
    $status = $_POST['status'];
    $deadline = $_POST['deadline'];

    $update = $pdo->prepare("UPDATE jobs SET title=?, description=?, eligibility_criteria=?, location=?, salary_range=?, type=?, status=?, deadline=?, edit_count = edit_count + 1 WHERE id=?");
    $update->execute([$title, $description, $eligibility_criteria, $location, $salary_range, $type, $status, $deadline, $job_id]);
    
    $success = "Job updated successfully! Note: You cannot edit this job again.";
    
    // Refresh data
    $stmt->execute([$job_id, $recruiter_id]);
    $job = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Job - Employee Recruitment System</title>
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
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-5">
                        <h3 class="fw-bold mb-4">Edit Job Posting</h3>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Important:</strong> You can only edit this job once. Please review all changes carefully before submitting.
                        </div>
                        
                        <?php if(isset($success)): ?>
                            <div class="alert alert-success"><?php echo $success; ?></div>
                        <?php endif; ?>

                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Job Title</label>
                                <input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars($job['title']); ?>" required>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Job Type</label>
                                    <select name="type" class="form-select" required>
                                        <option value="Full-time" <?php echo $job['type'] == 'Full-time' ? 'selected' : ''; ?>>Full-time</option>
                                        <option value="Part-time" <?php echo $job['type'] == 'Part-time' ? 'selected' : ''; ?>>Part-time</option>
                                        <option value="Contract" <?php echo $job['type'] == 'Contract' ? 'selected' : ''; ?>>Contract</option>
                                        <option value="Internship" <?php echo $job['type'] == 'Internship' ? 'selected' : ''; ?>>Internship</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Status</label>
                                    <select name="status" class="form-select" required>
                                        <option value="active" <?php echo $job['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                                        <option value="closed" <?php echo $job['status'] == 'closed' ? 'selected' : ''; ?>>Closed</option>
                                    </select>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Location</label>
                                    <input type="text" name="location" class="form-control" value="<?php echo htmlspecialchars($job['location']); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Salary Range</label>
                                    <input type="text" name="salary_range" class="form-control" value="<?php echo htmlspecialchars($job['salary_range']); ?>" placeholder="e.g. $50k - $70k">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Application Deadline</label>
                                <input type="date" name="deadline" class="form-control" value="<?php echo htmlspecialchars($job['deadline'] ?? ''); ?>" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Job Description</label>
                                <textarea name="description" class="form-control" rows="5" required><?php echo htmlspecialchars($job['description']); ?></textarea>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Eligibility Criteria</label>
                                <textarea name="eligibility_criteria" class="form-control" rows="5" required><?php echo htmlspecialchars($job['eligibility_criteria']); ?></textarea>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-lg">Update Job</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
