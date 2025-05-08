<?php
// Initialize the session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // Redirect to login page
    header("Location: login.php");
    exit;
}

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Validate inputs
    if (empty($_POST['message']) || empty($_POST['receiver_id'])) {
        $_SESSION['error'] = "Message and recipient are required.";
        header("Location: dashboard.php");
        exit;
    }

    // Database connection
    require_once 'database.php';
    
    // Sanitize inputs
    $message = trim($_POST['message']);
    $receiver_id = (int)$_POST['receiver_id'];
    $sender_id = $_SESSION['user_id'];
    
    try {
        // Insert message into database
        $stmt = $pdo->prepare("
            INSERT INTO messages (sender_id, receiver_id, message, created_at) 
            VALUES (?, ?, ?, NOW())
        ");
        
        $stmt->execute([$sender_id, $receiver_id, $message]);
        
        $_SESSION['success'] = "Message sent successfully.";
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error sending message: " . $e->getMessage();
    }
    
    // Redirect back to dashboard
    header("Location: dashboard.php#messages");
    exit;
} else {
    // If not POST request, redirect to dashboard
    header("Location: dashboard.php");
    exit;
}
?>