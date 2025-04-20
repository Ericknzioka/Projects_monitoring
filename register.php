<?php
session_start();
include 'database.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $first_name = $_POST['first_name'];
    $last_name = $_POST['last_name'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $programme_of_study = isset($_POST['programme_of_study']) ? $_POST['programme_of_study'] : null;
    $reg_no = isset($_POST['reg_no']) ? $_POST['reg_no'] : null;
    $role = $_POST['role'];

    // Always store form data in session
    $_SESSION['form_data'] = [
        'first_name' => $first_name,
        'last_name' => $last_name,
        'email' => $email,
        'password' => $password,
        'confirm_password' => $confirm_password,
        'programme_of_study' => $programme_of_study,
        'reg_no' => $reg_no,
        'role' => $role
    ];

    // Initialize error flag
    $hasError = false;

    // Password validation
    if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/', $password)) {
        $_SESSION['error_password'] = "Password must be at least 8 characters long, contain at least one uppercase letter, one lowercase letter, one number, and one special character.";
        // Clear password fields when there's a password error
        unset($_SESSION['form_data']['password']);
        unset($_SESSION['form_data']['confirm_password']);
        $hasError = true;
    }

    if ($password !== $confirm_password) {
        $_SESSION['error_confirm_password'] = "Passwords do not match.";
        // Clear password fields when there's a password error
        unset($_SESSION['form_data']['password']);
        unset($_SESSION['form_data']['confirm_password']);
        $hasError = true;
    }

    // Validate registration number format for students
    if ($role === 'student' && $reg_no) {
        $programme_prefix = [
            'BCS' => 'CT202',
            'BCT' => 'CT201',
            'BDS' => 'CT203',
            'BCSF' => 'CT206'
        ];

        // Get the selected program prefix
        $selected_prefix = $programme_prefix[$programme_of_study] ?? null;

        // Validate registration number format
        if ($selected_prefix && !preg_match('/^' . preg_quote($selected_prefix, '/') . '\/\d{6}\/\d{2}$/', $reg_no)) {
            $_SESSION['error_reg_no'] = "Invalid registration number format. Expected format: " . $selected_prefix . "/123456/21";
            // Clear reg_no field when there's an error
            unset($_SESSION['form_data']['reg_no']);
            $hasError = true;
        }
    }

    // Check if email already exists
    if (!$hasError) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->rowCount() > 0) {
                $_SESSION['error_email'] = "Email already exists.";
                // Clear email field when there's an email error
                unset($_SESSION['form_data']['email']);
                $hasError = true;
            }
        } catch (PDOException $e) {
            $_SESSION['error'] = "Database error: " . $e->getMessage();
            $hasError = true;
        }
    }

    // If there are errors, redirect back to the registration page
    if ($hasError) {
        header("Location: register.php");
        exit();
    }

    // Hash the password and set approval status
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Set approval status based on role
    if ($role === 'admin') {
        $approved = 1;
        $approval_status = 'approved';
    } else {
        $approved = 0;
        $approval_status = 'pending';
    }

    try {
        if ($role === 'student' && $reg_no) {
            $stmt = $pdo->prepare("INSERT INTO users (first_name, last_name, email, password, role, reg_no, programme_of_study, approved, approval_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$first_name, $last_name, $email, $hashed_password, $role, $reg_no, $programme_of_study, $approved, $approval_status]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO users (first_name, last_name, email, password, role, approved, approval_status) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$first_name, $last_name, $email, $hashed_password, $role, $approved, $approval_status]);
        }
        
        // Clear form data from session after successful registration
        unset($_SESSION['form_data']);
        
        header("Location: login.php");
        exit();
    } catch (PDOException $e) {
        $_SESSION['error'] = "Database error: " . $e->getMessage();
        header("Location: register.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <title>Register</title>
    <style>
        body {
            background-color: #f8f9fa; /* Light gray background */
        }
        .container {
            margin-top: 50px;
            max-width: 500px; /* Limit the width of the form */
        }
        .password-icon {
            cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="container">
        <header class="text-center mb-4">
            <h1>Register</h1>
        </header>
        <?php if (isset($_SESSION['error_password'])): ?>
            <div class="alert alert-danger">
                <?php echo $_SESSION['error_password']; unset($_SESSION['error_password']); ?>
            </div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error_confirm_password'])): ?>
            <div class="alert alert-danger">
                <?php echo $_SESSION['error_confirm_password']; unset($_SESSION['error_confirm_password']); ?>
            </div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error_reg_no'])): ?>
            <div class="alert alert-danger">
                <?php echo $_SESSION['error_reg_no']; unset($_SESSION['error_reg_no']); ?>
            </div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error_email'])): ?>
            <div class="alert alert-danger">
                <?php echo $_SESSION['error_email']; unset($_SESSION['error_email']); ?>
            </div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger">
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>
        <form method="POST" action="">
            <div class="form-group">
                <input type="text" name="first_name" class="form-control" placeholder="First Name" 
                value="<?php echo isset($_SESSION['form_data']['first_name']) ? htmlspecialchars($_SESSION['form_data']['first_name']) : ''; ?>" required>
            </div>
            <div class="form-group">
                <input type="text" name="last_name" class="form-control" placeholder="Last Name" 
                value="<?php echo isset($_SESSION['form_data']['last_name']) ? htmlspecialchars($_SESSION['form_data']['last_name']) : ''; ?>" required>
            </div>
            <div class="form-group">
                <input type="email" name="email" class="form-control" placeholder="Email" 
                value="<?php echo isset($_SESSION['form_data']['email']) ? htmlspecialchars($_SESSION['form_data']['email']) : ''; ?>" required>
            </div>
            <div class="form-group input-group">
                <input type="password" name="password" id="password" class="form-control" placeholder="Password" 
                value="<?php echo isset($_SESSION['form_data']['password']) ? htmlspecialchars($_SESSION['form_data']['password']) : ''; ?>" required>
                <div class="input-group-append">
                    <span class="input-group-text password-icon" onclick="togglePasswordVisibility('password')">👁️</span>
                </div>
            </div>
            <div class="form-group input-group">
                <input type="password" name="confirm_password" id="confirm_password" class="form-control" placeholder="Confirm Password" 
                value="<?php echo isset($_SESSION['form_data']['confirm_password']) ? htmlspecialchars($_SESSION['form_data']['confirm_password']) : ''; ?>" required>
                <div class="input-group-append">
                    <span class="input-group-text password-icon" onclick="togglePasswordVisibility('confirm_password')">👁️</span>
                </div>
            </div>
            <div class="form-group">
                <select name="role" id="role" class="form-control" required>
                    <option value="student" <?php echo (isset($_SESSION['form_data']['role']) && $_SESSION['form_data']['role'] === 'student') ? 'selected' : ''; ?>>Student</option>
                    <option value="supervisor" <?php echo (isset($_SESSION['form_data']['role']) && $_SESSION['form_data']['role'] === 'supervisor') ? 'selected' : ''; ?>>Supervisor</option>
                    <option value="admin" <?php echo (isset($_SESSION['form_data']['role']) && $_SESSION['form_data']['role'] === 'admin') ? 'selected' : ''; ?>>Admin</option>
                </select>
            </div>
            <div class="form-group" id="programme_of_study_container" style="display:<?php echo (isset($_SESSION['form_data']['role']) && $_SESSION['form_data']['role'] === 'student') ? 'block' : 'none'; ?>;">
                <select name="programme_of_study" id="programme_of_study" class="form-control" onchange="updateRegNoPlaceholder()">
                    <option value="">Select Programme of Study</option>
                    <option value="BCS" <?php echo (isset($_SESSION['form_data']['programme_of_study']) && $_SESSION['form_data']['programme_of_study'] === 'BCS') ? 'selected' : ''; ?>>BCS</option>
                    <option value="BCT" <?php echo (isset($_SESSION['form_data']['programme_of_study']) && $_SESSION['form_data']['programme_of_study'] === 'BCT') ? 'selected' : ''; ?>>BCT</option>
                    <option value="BDS" <?php echo (isset($_SESSION['form_data']['programme_of_study']) && $_SESSION['form_data']['programme_of_study'] === 'BDS') ? 'selected' : ''; ?>>BDS</option>
                    <option value="BCSF" <?php echo (isset($_SESSION['form_data']['programme_of_study']) && $_SESSION['form_data']['programme_of_study'] === 'BCSF') ? 'selected' : ''; ?>>BCSF</option>
                </select>
            </div>
            
            <div class="form-group" id="reg_no_container" style="display:<?php echo (isset($_SESSION['form_data']['role']) && $_SESSION['form_data']['role'] === 'student') ? 'block' : 'none'; ?>;">
                <input type="text" name="reg_no" id="reg_no" class="form-control" placeholder="Registration Number (e.g., CT206/123456/21)" 
                value="<?php echo isset($_SESSION['form_data']['reg_no']) ? htmlspecialchars($_SESSION['form_data']['reg_no']) : ''; ?>">
                <small class="form-text text-muted" id="reg_no_format_help">Format should be: [Program Code]/[6 digits]/[2 digits]</small>
            </div>
            <button type="submit" class="btn btn-primary btn-block">Register</button>
        </form>
        <div class="text-center mt-3">
            <p>Already have an account? <a href="login.php">Login here</a></p>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.2/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        document.getElementById('role').addEventListener('change', function() {
            var regNoField = document.getElementById('reg_no_container');
            var programmeOfStudyField = document.getElementById('programme_of_study_container');
            if (this.value === 'student') {
                regNoField.style.display = 'block';
                programmeOfStudyField.style.display = 'block';
                document.getElementById('reg_no').required = true;
                document.getElementById('programme_of_study').required = true;
            } else {
                regNoField.style.display = 'none';
                programmeOfStudyField.style.display = 'none';
                document.getElementById('reg_no').required = false;
                document.getElementById('programme_of_study').required = false;
            }
        });

        function updateRegNoPlaceholder() {
            var programme = document.getElementById('programme_of_study').value;
            var prefixMap = {
                'BCS': 'CT202',
                'BCT': 'CT201',
                'BDS': 'CT203',
                'BCSF': 'CT206'
            };
            
            var prefix = prefixMap[programme] || '';
            if (prefix) {
                document.getElementById('reg_no').placeholder = "Registration Number (e.g., " + prefix + "/123456/21)";
                document.getElementById('reg_no_format_help').textContent = "Format should be: " + prefix + "/[6 digits]/[2 digits]";
            } else {
                document.getElementById('reg_no').placeholder = "Registration Number (e.g., CT206/123456/21)";
                document.getElementById('reg_no_format_help').textContent = "Format should be: [Program Code]/[6 digits]/[2 digits]";
            }
        }

        function togglePasswordVisibility(id) {
            var passwordField = document.getElementById(id);
            passwordField.type = (passwordField.type === "password") ? "text" : "password";
        }

        // Initialize the reg no placeholder based on initial programme selection
        window.onload = function() {
            if (document.getElementById('role').value === 'student') {
                updateRegNoPlaceholder();
            }
        };
    </script>
</body>
</html>