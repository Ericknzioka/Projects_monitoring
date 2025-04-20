<?php
session_start();
include 'db/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['file'])) {
    $file = $_FILES['file'];
    
    // Define the upload directory
    $upload_dir = 'uploads/';
    $file_path = $upload_dir . basename($file['name']);

    // Move the uploaded file to the designated directory
    if (move_uploaded_file($file['tmp_name'], $file_path)) {
        // Save file information to the database
        $stmt = $pdo->prepare("INSERT INTO files (user_id, file_name, file_path) VALUES (?, ?, ?)");
        $stmt->execute([$_SESSION['user_id'], $file['name'], $file_path]);

        header("Location: supervisor.php");
        exit();
    } else {
        echo "File upload failed.";
    }
}

// Fetch files shared with the user
$files = $pdo->query("SELECT * FROM files WHERE user_id = " . $_SESSION['user_id'])->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/styles.css">
    <title>File Sharing</title>
</head>
<body>
    <header>
        <h1>File Sharing</h1>
    </header>
    <div class="container">
        <h2>Upload File</h2>
        <form method="POST" enctype="multipart/form-data">
            <input type="file" name="file" required>
            <input type="submit" value="Upload">
        </form>

        <h2>Shared Files</h2>
        <table>
            <tr>
                <th>ID</th>
                <th>File Name</th>
                <th>Action</th>
            </tr>
            <?php foreach ($files as $file): ?>
            <tr>
                <td><?php echo $file['id']; ?></td>
                <td><?php echo htmlspecialchars($file['file_name']); ?></td>
                <td>
                    <form method="POST" action="download_file.php" style="display:inline;">
                        <input type="hidden" name="file_id" value="<?php echo $file['id']; ?>">
                        <input type="submit" value="Download">
                    </form>
                    <form method="POST" action="delete_file.php" style="display:inline;">
                        <input type="hidden" name="file_id" value="<?php echo $file['id']; ?>">
                        <input type="submit" value="Delete" onclick="return confirm('Are you sure you want to delete this file?');">
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
</body>
</html>