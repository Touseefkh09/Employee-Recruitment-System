<?php
session_start();

// Prevent browser caching - force reload of latest UI
/*header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");*/

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'candidate') {
    header("Location: ../login.php");
    exit;
}
include '../includes/db.php';
include '../includes/security.php';

set_security_headers();

$job_id = $_GET['job_id'] ?? null;
$error = '';
$success = '';

// Fetch existing profile data
$stmt = $pdo->prepare("SELECT * FROM candidates WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$profile = $stmt->fetch();

// Fetch existing education
$edu_stmt = $pdo->prepare("SELECT * FROM candidate_education WHERE candidate_id = ? ORDER BY id");
$edu_stmt->execute([$profile['id']]);
$education_entries = $edu_stmt->fetchAll();

// Initialize variables with DB data or empty
$fullname = $profile['full_name'] ?? '';
$phone = $profile['phone'] ?? '';
$skills = $profile['skills'] ?? '';
$resume_path = $profile['resume_path'] ?? '';
$documents_path = $profile['documents_path'] ?? '';

// If POST, override with submitted data for preservation
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Validate CSRF token
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = "Security validation failed. Please try again.";
    } else {
        $fullname = $_POST['fullname'] ?? $fullname;
        $phone = $_POST['phone'] ?? $phone;
        $skills = $_POST['skills'] ?? $skills;
        
        $action = $_POST['action']; // 'submit_application' or 'done'
    
        // Handle Resume Upload
        if (isset($_FILES['resume']) && $_FILES['resume']['error'] != UPLOAD_ERR_NO_FILE) {
            // Validate file upload
            $validation = validate_file_upload($_FILES['resume'], ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'], 5242880);
            
            if (!$validation['success']) {
                $error = $validation['error'];
            } else {
                $target_dir = "../uploads/resumes/";
                if (!file_exists($target_dir)) mkdir($target_dir, 0777, true);
                $ext = pathinfo($_FILES["resume"]["name"], PATHINFO_EXTENSION);
                $new_filename = "resume_" . $_SESSION['user_id'] . "_" . time() . "." . $ext;
                if (move_uploaded_file($_FILES["resume"]["tmp_name"], $target_dir . $new_filename)) {
                    $resume_path = "uploads/resumes/" . $new_filename;
                } else {
                    $error = "Failed to upload resume";
                }
            }
        }

        // Handle Documents Upload
        if (isset($_FILES['documents']) && $_FILES['documents']['error'] != UPLOAD_ERR_NO_FILE) {
            // Validate file upload (PDF only for documents)
            $validation = validate_file_upload($_FILES['documents'], ['application/pdf'], 5242880);
            
            if (!$validation['success']) {
                $error = $validation['error'];
            } else {
                $target_dir = "../uploads/documents/";
                if (!file_exists($target_dir)) mkdir($target_dir, 0777, true);
                $new_filename = "documents_" . $_SESSION['user_id'] . "_" . time() . ".pdf";
                if (move_uploaded_file($_FILES["documents"]["tmp_name"], $target_dir . $new_filename)) {
                    $documents_path = "uploads/documents/" . $new_filename;
                } else {
                    $error = "Failed to upload documents";
                }
            }
        }

        // Validation
        if (empty($resume_path)) $error = "Resume is required.";
        if (empty($documents_path)) $error = "Documents are required.";

        if (!$error) {
            try {
                $pdo->beginTransaction();

                // Update Candidate Profile
                $update = $pdo->prepare("UPDATE candidates SET full_name=?, phone=?, skills=?, resume_path=?, documents_path=? WHERE user_id=?");
                $update->execute([$fullname, $phone, $skills, $resume_path, $documents_path, $_SESSION['user_id']]);
                $candidate_id = $profile['id'];

                // Update Education
                $pdo->prepare("DELETE FROM candidate_education WHERE candidate_id = ?")->execute([$candidate_id]);
                if (isset($_POST['education_level'])) {
                    $edu_levels = $_POST['education_level'];
                    $edu_percentages = $_POST['education_percentage'];
                    $edu_insert = $pdo->prepare("INSERT INTO candidate_education (candidate_id, education_level, percentage) VALUES (?, ?, ?)");
                    foreach ($edu_levels as $index => $level) {
                        if (!empty($level) && !empty($edu_percentages[$index])) {
                            $edu_insert->execute([$candidate_id, $level, $edu_percentages[$index]]);
                        }
                    }
                }

                // Handle Action
                if ($action === 'submit_application' && $job_id) {
                    // Check if already applied
                    $check = $pdo->prepare("SELECT id FROM applications WHERE job_id = ? AND candidate_id = ?");
                    $check->execute([$job_id, $candidate_id]);
                    if (!$check->fetch()) {
                        $apply = $pdo->prepare("INSERT INTO applications (job_id, candidate_id) VALUES (?, ?)");
                        $apply->execute([$job_id, $candidate_id]);
                    }
                    $pdo->commit();
                    header("Location: my_applications.php?success=applied");
                    exit;
                } elseif ($action === 'done') {
                    $pdo->commit();
                    header("Location: jobs.php?success=profile_complete");
                    exit;
                }
                
                $pdo->commit();

            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Error saving profile: " . $e->getMessage();
            }
        }
    }
}

// Determine active step based on error
$active_step = ($error) ? 2 : 1;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Complete Profile - Employee Recruitment System</title>
    <link rel="icon" href="../images/logo.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .step-container { display: none; }
        .step-container.active { display: block; }
        .progress-step { width: 30px; height: 30px; border-radius: 50%; background: #e9ecef; color: #6c757d; display: flex; align-items: center; justify-content: center; font-weight: bold; }
        .progress-step.active { background: #0d6efd; color: white; }
        .progress-line { flex: 1; height: 2px; background: #e9ecef; margin: 0 10px; }
        .progress-line.active { background: #0d6efd; }
        .is-invalid { border-color: #dc3545 !important; }
        .invalid-feedback { display: none; color: #dc3545; font-size: 0.875em; margin-top: 0.25rem; }
        .is-invalid ~ .invalid-feedback { display: block; }
    </style>
</head>
<body class="bg-light">
    <!-- Simple Header -->
    <nav class="navbar navbar-expand-lg fixed-top bg-white border-bottom">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-chart-bar me-2"></i>Employee Recruitment System
            </a>
            <div class="ms-auto">
                <a href="dashboard.php" class="btn btn-outline-secondary btn-sm">
                    <i class=""></i>Dashboard
                </a>
            </div>
        </div>
    </nav>

    <div class="container py-5" style="margin-top: 100px;">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-5">
                        <h3 class="fw-bold text-center mb-4">Complete Your Profile</h3>
                        
                        <!-- Progress Indicator -->
                        <div class="d-flex align-items-center mb-5 px-5">
                            <div class="progress-step <?php echo $active_step >= 1 ? 'active' : ''; ?>" id="step1-indicator">1</div>
                            <div class="progress-line <?php echo $active_step >= 2 ? 'active' : ''; ?>" id="line-indicator"></div>
                            <div class="progress-step <?php echo $active_step >= 2 ? 'active' : ''; ?>" id="step2-indicator">2</div>
                        </div>

                        <?php if($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>

                        <form method="POST" enctype="multipart/form-data" id="profileForm" novalidate>
                            <?php echo csrf_token_field(); ?>
                            <input type="hidden" name="action" id="formAction" value="">
                            
                            <!-- Step 1: Personal Details -->
                            <div id="step1" class="step-container <?php echo $active_step == 1 ? 'active' : ''; ?>">
                                <h5 class="mb-3">Personal Details</h5>
                                <div class="mb-3">
                                    <label class="form-label">Full Name <span class="text-danger">*</span></label>
                                    <input type="text" name="fullname" class="form-control" value="<?php echo htmlspecialchars($fullname); ?>" required>
                                    <div class="invalid-feedback">Full name is required.</div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Phone <span class="text-danger">*</span></label>
                                    <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($phone); ?>" required>
                                    <div class="invalid-feedback">Phone number is required.</div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Skills <span class="text-danger">*</span></label>
                                    <textarea name="skills" class="form-control" rows="3" placeholder="e.g. PHP, Java, Communication" required><?php echo htmlspecialchars($skills); ?></textarea>
                                    <div class="invalid-feedback">Skills are required.</div>
                                </div>
                                <div class="text-end">
                                    <button type="button" class="btn btn-primary" onclick="nextStep()">Next <i class="fas fa-arrow-right ms-2"></i></button>
                                </div>
                            </div>

                            <!-- Step 2: Education & Documents -->
                            <div id="step2" class="step-container <?php echo $active_step == 2 ? 'active' : ''; ?>">
                                <h5 class="mb-3">Education & Documents</h5>
                                
                                <div class="mb-4">
                                    <label class="form-label">Education Details <span class="text-danger">*</span></label>
                                    <div id="education-container">
                                        <?php 
                                        // Use POST data if available, otherwise DB data
                                        $display_education = [];
                                        if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['education_level'])) {
                                            foreach ($_POST['education_level'] as $idx => $lvl) {
                                                $display_education[] = [
                                                    'education_level' => $lvl,
                                                    'percentage' => $_POST['education_percentage'][$idx] ?? ''
                                                ];
                                            }
                                        } elseif (!empty($education_entries)) {
                                            $display_education = $education_entries;
                                        }
                                        ?>

                                        <?php if(empty($display_education)): ?>
                                            <div class="row g-2 mb-2 education-row">
                                                <div class="col-7">
                                                    <input type="text" name="education_level[]" class="form-control" placeholder="Degree/Certificate (e.g. BS CS)" required>
                                                    <div class="invalid-feedback">Degree/Certificate is required.</div>
                                                </div>
                                                <div class="col-4">
                                                    <input type="number" name="education_percentage[]" class="form-control" placeholder="Percentage" min="0" max="100" step="0.01" required>
                                                    <div class="invalid-feedback">Percentage is required.</div>
                                                </div>
                                                <div class="col-1">
                                                    <button type="button" class="btn btn-outline-danger w-100" onclick="removeEducation(this)"><i class="fas fa-times"></i></button>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <?php foreach($display_education as $edu): ?>
                                                <div class="row g-2 mb-2 education-row">
                                                    <div class="col-7">
                                                        <input type="text" name="education_level[]" class="form-control" value="<?php echo htmlspecialchars($edu['education_level']); ?>" required>
                                                        <div class="invalid-feedback">Degree/Certificate is required.</div>
                                                    </div>
                                                    <div class="col-4">
                                                        <input type="number" name="education_percentage[]" class="form-control" value="<?php echo htmlspecialchars($edu['percentage']); ?>" required>
                                                        <div class="invalid-feedback">Percentage is required.</div>
                                                    </div>
                                                    <div class="col-1">
                                                        <button type="button" class="btn btn-outline-danger w-100" onclick="removeEducation(this)"><i class="fas fa-times"></i></button>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                    <button type="button" class="btn btn-outline-secondary btn-sm mt-2" onclick="addEducation()">
                                        <i class="fas fa-plus me-1"></i> Add More
                                    </button>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Resume (PDF/Doc) <span class="text-danger">*</span></label>
                                    <input type="file" name="resume" class="form-control" <?php echo empty($resume_path) ? 'required' : ''; ?>>
                                    <?php if($resume_path): ?>
                                        <small class="text-success"><i class="fas fa-check-circle me-1"></i>Resume uploaded</small>
                                    <?php endif; ?>
                                    <div class="invalid-feedback">Resume is required.</div>
                                </div>

                                <div class="mb-4">
                                    <label class="form-label">Documents (PDF) <span class="text-danger">*</span></label>
                                    <input type="file" name="documents" class="form-control" accept="application/pdf" <?php echo empty($documents_path) ? 'required' : ''; ?>>
                                    <?php if($documents_path): ?>
                                        <small class="text-success"><i class="fas fa-check-circle me-1"></i>Documents uploaded</small>
                                    <?php endif; ?>
                                    <div class="invalid-feedback">Documents are required.</div>
                                </div>

                                <div class="d-flex justify-content-between">
                                    <button type="button" class="btn btn-secondary" onclick="prevStep()"><i class="fas fa-arrow-left me-2"></i>Back</button>
                                    <div class="d-flex gap-2">
                                        <button type="button" class="btn btn-outline-primary" onclick="submitForm('done')">Done</button>
                                        <?php if($job_id): ?>
                                            <button type="button" class="btn btn-primary" onclick="submitForm('submit_application')">Submit Application</button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/active_link.js"></script>
    <script>
        function validateStep(stepId) {
            const inputs = document.querySelectorAll('#' + stepId + ' input[required], #' + stepId + ' textarea[required]');
            let valid = true;
            inputs.forEach(input => {
                if (!input.value) {
                    input.classList.add('is-invalid');
                    valid = false;
                } else {
                    input.classList.remove('is-invalid');
                }
            });
            return valid;
        }

        function nextStep() {
            if (validateStep('step1')) {
                document.getElementById('step1').classList.remove('active');
                document.getElementById('step2').classList.add('active');
                document.getElementById('step2-indicator').classList.add('active');
                document.getElementById('line-indicator').classList.add('active');
            }
        }

        function prevStep() {
            document.getElementById('step2').classList.remove('active');
            document.getElementById('step1').classList.add('active');
            document.getElementById('step2-indicator').classList.remove('active');
            document.getElementById('line-indicator').classList.remove('active');
        }

        function addEducation() {
            const container = document.getElementById('education-container');
            const div = document.createElement('div');
            div.className = 'row g-2 mb-2 education-row';
            div.innerHTML = `
                <div class="col-7">
                    <input type="text" name="education_level[]" class="form-control" placeholder="Degree/Certificate" required>
                    <div class="invalid-feedback">Degree/Certificate is required.</div>
                </div>
                <div class="col-4">
                    <input type="number" name="education_percentage[]" class="form-control" placeholder="Percentage" min="0" max="100" step="0.01" required>
                    <div class="invalid-feedback">Percentage is required.</div>
                </div>
                <div class="col-1">
                    <button type="button" class="btn btn-outline-danger w-100" onclick="removeEducation(this)"><i class="fas fa-times"></i></button>
                </div>
            `;
            container.appendChild(div);
        }

        function removeEducation(btn) {
            const rows = document.querySelectorAll('.education-row');
            if (rows.length > 1) {
                btn.closest('.row').remove();
            } else {
                alert('At least one education entry is required.');
            }
        }

        function submitForm(action) {
            // Validate Step 2 before submitting
            if (validateStep('step2')) {
                document.getElementById('formAction').value = action;
                document.getElementById('profileForm').submit();
            }
        }

        // Remove error when user types in the field
        document.addEventListener('input', function(e) {
            if (e.target.classList.contains('form-control') && e.target.hasAttribute('required')) {
                if (e.target.value.trim()) {
                    e.target.classList.remove('is-invalid');
                }
            }
        });

        // Show error when user leaves field empty (on blur)
        document.addEventListener('blur', function(e) {
            if (e.target.classList.contains('form-control') && e.target.hasAttribute('required')) {
                if (!e.target.value.trim()) {
                    e.target.classList.add('is-invalid');
                }
            }
        }, true);
    </script>
</body>
</html>
