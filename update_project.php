<?php
session_start();
include 'database.php';

// Check if the user is logged in and has the correct role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $project_id = $_POST['project_id'];
    $status = $_POST['status'];

    // Update the project status in the database
    $stmt = $pdo->prepare("UPDATE projects SET status = ? WHERE id = ? AND user_id = ?");
    $stmt->execute([$status, $project_id, $_SESSION['user_id']]);

    // Add notification for the student
    $notification = "Your project status has been updated to '$status'.";
    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
    $stmt->execute([$_SESSION['user_id'], $notification]);

    // Add notification for the supervisor (assuming user_id 2 is the supervisor)
    $supervisor_id = 2; // Change this to the actual supervisor ID
    $supervisor_notification = "Project ID $project_id status updated to '$status' by student ID " . $_SESSION['user_id'];
    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
    $stmt->execute([$supervisor_id, $supervisor_notification]);

    // Add notification for the admin (assuming user_id 1 is the admin)
    $admin_id = 1; // Change this to the actual admin ID
    $admin_notification = "Project ID $project_id status updated to '$status' by student ID " . $_SESSION['user_id'];
    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
    $stmt->execute([$admin_id, $admin_notification]);

    // Redirect back to the student dashboard
    header("Location: student.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="css/styles.css">
    <title>Update Project Status</title>
</head>
<body>
    <div class="container mt-5">
        <h2>Update Project Status</h2>
        <form method="POST" action="">
            <div class="form-group">
                <label for="project_id">Project ID</label>
                <input type="text" name="project_id" id="project_id" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="status">Status</label>
                <select name="status" id="status" class="form-control" required>
                    <option value="started">Started</option>
                    <option value="on_hold">On Hold</option>
                    <option value="in_progress">In Progress</option>
                    <option value="done">Done</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Update Status</button>
        </form>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.2/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>