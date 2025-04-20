<?php
// Only start session if one doesn't already exist
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database connection settings
$host = 'localhost';
$db = 'project_monitoring'; // Ensure this database exists
$user = 'root'; // Default XAMPP user
$pass = ''; // Default XAMPP password

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Optional: Set session timeout (30 minutes in this example)
    $session_timeout = 1800; // 30 minutes in seconds
    
    // Check if session is set and not expired
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $session_timeout)) {
        // Session has expired, destroy it
        session_unset();
        session_destroy();
        // Restart the session
        session_start();
    }
    
    // Update last activity time
    $_SESSION['last_activity'] = time();
    
} catch (PDOException $e) {
    error_log("Connection failed: " . $e->getMessage()); // Log the error
    die("Connection failed: " . $e->getMessage());
}
?>