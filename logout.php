<?php
session_start();
session_destroy(); // Destroy the session to log out the user
header("Location: index.php"); // Redirect to the index page
exit(); // Ensure no further code is executed
?>
<!-- The following HTML will not be executed due to the exit() above -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <title>Logging Out</title>
</head>
<body>
    <div class="container mt-5 text-center">
        <h1>Logging Out...</h1>
        <p>You have been successfully logged out. Redirecting to the homepage...</p>
        <div class="spinner-border" role="status">
            <span class="sr-only">Loading...</span>
        </div>
    </div>
    <script>
        // Redirect to index.php after a short delay
        setTimeout(function() {
            window.location.href = "index.php";
        }, 2000); // Redirect after 2 seconds
    </script>
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.2/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>