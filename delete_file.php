<?php
session_start();
include 'database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['file_id'])) {
    $file_id = $_POST['file_id'];

    // Check if the file exists and belongs to the logged-in user
    $stmt = $pdo->prepare("SELECT * FROM files WHERE id = ? AND user_id = ?");
    $stmt->execute([$file_id, $_SESSION['user_id']]);
    $file = $stmt->fetch();

    if ($file) {
        // Check if the user wants to delete the file
        if (isset($_POST['action']) && $_POST['action'] === 'delete') {
            // Delete the file from the database
            $stmt = $pdo->prepare("DELETE FROM files WHERE id = ?");
            $stmt->execute([$file_id]);

            // Delete the file from the server
            $file_path = $file['file_path'];
            if (file_exists($file_path)) {
                if (!unlink($file_path)) {
                    echo "Error deleting file: " . $file_path;
                } else {
                    // Add a notification for successful deletion
                    $notification = "File '{$file['file_name']}' deleted successfully.";
                    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
                    $stmt->execute([$_SESSION['user_id'], $notification]);

                    // Redirect back to the file sharing page with a success message
                    header("Location: file_sharing.php?message=" . urlencode($notification));
                    exit();
                }
            } else {
                echo "File not found on the server.";
            }
        }
    } else {
        // Redirect back to the file sharing page with an error message
        header("Location: file_sharing.php?error=" . urlencode("File not found or you do not have permission to access this file."));
        exit();
    }
} else {
    // Redirect back to the file sharing page with an error message
    header("Location: file_sharing.php?error=" . urlencode("No file specified."));
    exit();
}