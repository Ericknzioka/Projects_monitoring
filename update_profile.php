<?php
session_start();
include 'database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("UPDATE users SET email = ?, password = ? WHERE id = ?");
    $stmt->execute([$email, $password, $_SESSION['user_id']]);

    echo "Profile updated successfully!";
}

// Fetch current user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/styles.css">
    <title>Update Profile</title>
</head>
<body>
    <header>
        <h1>Update Profile</h1>
    </header>
    <div class="container">
        <form method="POST" action="">
            <input type="email" name="email" value="<?php echo $user['email']; ?>" required>
            <input type="password" name="password" placeholder="New Password" required>
            <input type="submit" value="Update">
        </form>
    </div>
</body>
</html>