<?php
session_start();
include 'database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if (isset($_GET['file_id'])) {
    $file_id = $_GET['file_id']; // Use GET to retrieve the file ID

    // Fetch the file from the database
    $stmt = $pdo->prepare("SELECT * FROM files WHERE id = ? AND user_id = ?");
    $stmt->execute([$file_id, $_SESSION['user_id']]); // Ensure the user owns the file
    $file = $stmt->fetch();

    if ($file) {
        // Set headers for download
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($file['file_name']) . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($file['file_path'])); // Use the actual file path

        // Read the file from the server and output it
        readfile($file['file_path']); // Output the file content directly
        exit();
    } else {
        echo "File not found or you do not have permission to access this file.";
    }
} else {
    echo "No file specified.";
}
?>