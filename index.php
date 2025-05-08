<?php
session_start();
?>

<!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="UTF-8">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
   <link rel="stylesheet" href="css/styles.css">
   <title>Project Monitoring System</title>
</head>
<body>
   <header class="text-center">
       <h1 class="display-4">Welcome to the Projects Progress Monitoring System</h1>
   </header>
   <div class="container mt-5">
       <h2 class="text-center">Login or Register</h2>
       <div class="button-container text-center">
           <a href="register.php" class="btn btn-primary">Register</a>
           <a href="login.php" class="btn btn-primary">Login</a>
           <a href="faq.html" class="btn btn-primary">FaQ's</a>
           <a href="help.php" class="btn btn-primary">Help</a>
       </div>
   </div>

   <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
   <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.2/dist/umd/popper.min.js"></script>
   <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>