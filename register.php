<?php 
session_start();
include 'includes/db.php';
include 'includes/security.php';
include 'includes/header.php'; 

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = "Security validation failed. Please try again.";
    } else {
        $fullname = trim($_POST['fullname']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        $role = $_POST['role'];
        $company_name = isset($_POST['company_name']) ? trim($_POST['company_name']) : '';

        
        if (empty($fullname) || !preg_match("/^[a-zA-Z\s]+$/", $fullname)) {
            $error = "Full Name must contain only letters and spaces.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Invalid email format.";
        } elseif (strlen($password) < 6) {
            $error = "Password must be at least 6 characters long.";
        } elseif (!preg_match("/^(?=.*[a-zA-Z])(?=.*\d)(?=.*[\W_]).+$/", $password)) {
            $error = "Password must contain at least one letter, one number, and one special character.";
        } elseif ($password !== $confirm_password) {
            $error = "Passwords do not match!";
        } elseif ($role === 'recruiter' && (empty($company_name) || !preg_match("/^[a-zA-Z0-9\s\.,'-]+$/", $company_name))) {
            $error = "Company Name contains invalid characters.";
        } else {
            // Check if email exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->rowCount() > 0) {
                $error = "Email already registered!";
            } else {
                // Hash password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                try {
                    $pdo->beginTransaction();
                    
                    // Insert into users table
                    $stmt = $pdo->prepare("INSERT INTO users (email, password, role) VALUES (?, ?, ?)");
                    $stmt->execute([$email, $hashed_password, $role]);
                    $user_id = $pdo->lastInsertId();

                    // Insert into specific profile table
                    if ($role === 'candidate') {
                        $stmt = $pdo->prepare("INSERT INTO candidates (user_id, full_name) VALUES (?, ?)");
                        $stmt->execute([$user_id, $fullname]);
                    } elseif ($role === 'recruiter') {
                        $stmt = $pdo->prepare("INSERT INTO recruiters (user_id, company_name) VALUES (?, ?)");
                        $stmt->execute([$user_id, $company_name]);
                    }

                    $pdo->commit();
                    
                    // Regenerate session to prevent session fixation
                    regenerate_session();
                    
                    // Redirect to login page with success flag
                    echo "<script>window.location.href='login.php?registered=true';</script>";
                    exit;
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error = "Registration failed. Please try again.";
                }
            }
        }
    }
}
    

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'candidate') {
        header("Location: candidate/dashboard.php");
    } elseif ($_SESSION['role'] === 'recruiter') {
        header("Location: recruiter/dashboard.php");
    } elseif ($_SESSION['role'] === 'admin') {
        header("Location: admin/dashboard.php");
    }
    exit;
}
?>

<div class="container py-3" style="margin-top: 80px;">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="glass-panel p-3 rounded-4">
                <div class="text-center mb-2">
                    <h2 class="fw-bold">Create Account</h2>
                    <p class="text-muted small">Join Employee Recruitment System today</p>
                </div>

                <?php if($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <form method="POST" action="" id="registerForm" novalidate>
                    <?php echo csrf_token_field(); ?>
                    <div class="mb-1">
                        <label class="form-label">I am a...</label>
                        <div class="d-flex gap-3">
                            <div class="form-check card-radio w-50">
                                <input class="form-check-input d-none" type="radio" name="role" id="roleCandidate" value="candidate" checked onchange="toggleFields(true)">
                                <label class="form-check-label btn btn-outline-primary w-100 py-1 btn-sm" for="roleCandidate">
                                    <i class="fas fa-user me-2"></i> Job Seeker
                                </label>
                            </div>
                            <div class="form-check card-radio w-50">
                                <input class="form-check-input d-none" type="radio" name="role" id="roleRecruiter" value="recruiter" onchange="toggleFields(true)">
                                <label class="form-check-label btn btn-outline-primary w-100 py-1 btn-sm" for="roleRecruiter">
                                    <i class="fas fa-building me-2"></i> Recruiter
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="mb-1">
                        <label for="fullname" class="form-label">Full Name<span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-sm" id="fullname" name="fullname" required 
                               pattern="[A-Za-z\s]+" 
                               title="Name should only contain letters and spaces.">
                        <div class="invalid-feedback">Please enter your full name (letters and spaces only).</div>
                    </div>

                    <div class="mb-1" id="companyField" style="display: none;">
                        <label for="company_name" class="form-label">Company Name<span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-sm" id="company_name" name="company_name"
                               pattern="[A-Za-z0-9\s\.,'-]+"
                               title="Company name allows letters, numbers, spaces, and basic punctuation.">
                        <div class="invalid-feedback">Please enter a valid company name.</div>
                    </div>

                    <div class="mb-1">
                        <label for="email" class="form-label">Email Address<span class="text-danger">*</span></label>
                        <input type="email" class="form-control form-control-sm" id="email" name="email" required>
                        <div class="invalid-feedback">Please enter a valid email address.</div>
                    </div>

                    <div class="row mb-1">
                        <div class="col-md-6">
                            <label for="password" class="form-label">Password<span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="password" class="form-control form-control-sm" id="password" name="password" required 
                                       minlength="6" 
                                       pattern="(?=.*[A-Za-z])(?=.*\d)(?=.*[\W_]).{6,}"
                                       title="Must contain at least one letter, one number, and one special character.">
                                <button class="btn btn-outline-secondary btn-sm" type="button" onclick="togglePassword('password')">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <div class="invalid-feedback">Min 6 chars, 1 letter, 1 number, 1 special char.</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="confirm_password" class="form-label">Confirm Password<span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="password" class="form-control form-control-sm" id="confirm_password" name="confirm_password" required>
                                <button class="btn btn-outline-secondary btn-sm" type="button" onclick="togglePassword('confirm_password')">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <div class="invalid-feedback">Passwords do not match.</div>
                            </div>
                        </div>
                    </div>

                    <div class="d-grid gap-2 mt-2">
                        <button type="submit" class="btn btn-primary">Register</button>
                    </div>

                    <div class="text-center mt-2">
                        <p class="mb-0">Already have an account? <a href="login.php" class="text-primary text-decoration-none">Login here</a></p>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function toggleFields(clearData = false) {
    const role = document.querySelector('input[name="role"]:checked').value;
    const companyField = document.getElementById('companyField');
    const companyInput = document.getElementById('company_name');
    
    // Visual Highlighting
    document.querySelectorAll('input[name="role"]').forEach(input => {
        const label = document.querySelector(`label[for="${input.id}"]`);
        if (input.checked) {
            label.classList.remove('btn-outline-primary');
            label.classList.add('btn-primary');
            label.classList.add('shadow'); 
        } else {
            label.classList.remove('btn-primary');
            label.classList.remove('shadow');
            label.classList.add('btn-outline-primary');
        }
    });

    // Field Toggling
    if (role === 'recruiter') {
        companyField.style.display = 'block';
        companyInput.required = true;
    } else {
        companyField.style.display = 'none';
        companyInput.required = false;
        // Clear error if hidden
        companyInput.classList.remove('is-invalid');
        companyInput.value = '';
    }

    if (clearData) {
        // Clear common fields
        ['fullname', 'email', 'password', 'confirm_password'].forEach(id => {
            const input = document.getElementById(id);
            input.value = '';
            input.classList.remove('is-invalid');
            input.classList.remove('is-valid');
        });
    }
}

function togglePassword(fieldId) {
    const field = document.getElementById(fieldId);
    const icon = field.nextElementSibling.querySelector('i');
    if (field.type === "password") {
        field.type = "text";
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        field.type = "password";
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('registerForm');
    const inputs = form.querySelectorAll('input');

    // Helper to set error message
    const setError = (input, message) => {
        const feedback = input.parentElement.querySelector('.invalid-feedback') || input.nextElementSibling;
        if (feedback) {
            feedback.textContent = message;
        }
        input.classList.add('is-invalid');
        input.classList.remove('is-valid');
    };

    const setSuccess = (input) => {
        input.classList.remove('is-invalid');
        input.classList.add('is-valid');
    };

    // Validate single field
    const validateField = (input) => {
        // Skip hidden fields (like company name when candidate) - return true as they're valid
        if (input.offsetParent === null && input.type !== 'hidden') return true;

        const value = input.value.trim();
        const id = input.id;

        // Reset custom validity
        input.setCustomValidity('');

        if (input.required && value === '') {
            setError(input, 'This field cannot be empty.');
            return false;
        }

        if (id === 'fullname') {
            if (!/^[A-Za-z\s]+$/.test(value)) {
                setError(input, 'Name should only contain letters and spaces.');
                return false;
            }
        }

        if (id === 'email') {
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
                setError(input, 'Please enter a valid email address.');
                return false;
            }
        }

        if (id === 'company_name') {
            if (input.required && !/^[A-Za-z0-9\s\.,'-]+$/.test(value)) {
                setError(input, 'Company name contains invalid characters.');
                return false;
            }
        }

        if (id === 'password') {
            if (value.length < 6) {
                setError(input, 'Password must be at least 6 characters.');
                return false;
            }
            if (!/(?=.*[A-Za-z])(?=.*\d)(?=.*[\W_])/.test(value)) {
                setError(input, 'Must contain 1 letter, 1 number, 1 special char.');
                return false;
            }
        }

        if (id === 'confirm_password') {
            const password = document.getElementById('password').value;
            if (value !== password) {
                setError(input, 'Passwords do not match.');
                return false;
            }
        }

        setSuccess(input);
        return true;
    };

    // Add blur event listeners
    inputs.forEach(input => {
        if (input.type !== 'radio' && input.type !== 'submit') {
            input.addEventListener('blur', () => validateField(input));
            // Also validate on input to clear error immediately when fixed
            input.addEventListener('input', () => {
                if (input.classList.contains('is-invalid')) {
                    validateField(input);
                }
            });
        }
    });

    // Form Submit
    form.addEventListener('submit', function (event) {
        let isValid = true;
        inputs.forEach(input => {
            if (input.type !== 'radio' && input.type !== 'submit') {
                if (!validateField(input)) {
                    isValid = false;
                }
            }
        });

        if (!isValid) {
            event.preventDefault();
            event.stopPropagation();
        }
    });

    toggleFields();
});
</script>

<?php include 'includes/footer.php'; ?>
