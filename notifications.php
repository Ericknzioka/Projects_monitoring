<?php
session_start();
include 'database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Fetch notifications for the logged-in user
$notifications = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC");
$notifications->execute([$_SESSION['user_id']]);
$notifications = $notifications->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="css/styles.css">
    <title>Notifications</title>
</head>
<body>
    <header class="bg-primary text-white text-center py-3">
        <h1>Notifications</h1>
    </header>
    <div class="container mt-4">
        <h2>Your Notifications</h2>
        <ul class="list-group">
            <?php if (empty($notifications)): ?>
                <li class="list-group-item">No notifications available.</li>
            <?php else: ?>
                <?php foreach ($notifications as $notification): ?>
                <li class="list-group-item">
                    <?php echo htmlspecialchars($notification['message']); ?> 
                    <small class="text-muted">(<?php echo htmlspecialchars($notification['created_at']); ?>)</small>
                </li>
                <?php endforeach; ?>
            <?php endif; ?>
        </ul>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.2/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>