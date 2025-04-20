<?php
session_start();
include 'database.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'];
    $password = $_POST['password'];

    // Prepare and execute the SQL statement to fetch user by email
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    // Verify the password and check user approval status
    if ($user && password_verify($password, $user['password'])) {
        // Check if the user is approved
        if ($user['approval_status'] == 'pending') {
            $error = "Your account is pending approval. Please wait for admin to approve your account.";
        } elseif ($user['approval_status'] == 'denied') {
            // Fetch the denial reason if available
            $stmt = $pdo->prepare("SELECT denial_reason FROM user_approval WHERE user_id = ?");
            $stmt->execute([$user['id']]);
            $approval_info = $stmt->fetch();
            
            $denial_reason = $approval_info && !empty($approval_info['denial_reason']) ? 
                             $approval_info['denial_reason'] : "No reason provided";
            
            $error = "Your account has been denied access. Reason: " . $denial_reason;
        } elseif ($user['approval_status'] == 'approved') {
            // User is approved, proceed with login
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];

            // Redirect based on user role
            if ($user['role'] == 'admin') {
                header("Location: admin.php"); // Redirect to admin dashboard
            } elseif ($user['role'] == 'supervisor') {
                header("Location: supervisor.php"); // Redirect to supervisor dashboard
            } else {
                header("Location: student.php"); // Redirect to student dashboard
            }
            exit();
        } else {
            // Default case for any unexpected approval status
            $error = "Account status issue. Please contact the administrator.";
        }
    } else {
        $error = "Invalid email or password.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <title>Login</title>
    <style>
        body {
            background-color: #f8f9fa; /* Light gray background */
        }
        .container {
            margin-top: 50px;
            max-width: 400px; /* Limit the width of the form */
        }
    </style>
</head>
<body>
    <div class="container ">
        <header class="text-center mb-4">
            <h1>Login</h1>
        </header>
        <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        <form method="POST" action="">
            <div class="form-group">
                <input type="email" name="email" class="form-control" placeholder="Email" required>
            </div>
            <div class="form-group">
                <input type="password" name="password" class="form-control" placeholder="Password" required>
            </div>
            <button type="submit" class="btn btn-primary btn-block">Login</button>
        </form>
        <div class="text-center mt-3">
            <a href="register.php">Don't have an account? Register here</a>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.2/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>