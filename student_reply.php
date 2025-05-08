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
    if (empty($_POST['reply']) || empty($_POST['original_message_id'])) {
        $_SESSION['error'] = "Reply content and original message ID are required.";
        header("Location: dashboard.php");
        exit;
    }

    // Database connection
    require_once 'database.php';
    
    // Sanitize inputs
    $reply = trim($_POST['reply']);
    $original_message_id = (int)$_POST['original_message_id'];
    $student_id = $_SESSION['user_id'];
    
    try {
        // First, get the original message sender (supervisor) to determine the receiver
        $stmt = $pdo->prepare("
            SELECT sender_id, receiver_id 
            FROM messages 
            WHERE id = ?
        ");
        $stmt->execute([$original_message_id]);
        $original_message = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$original_message) {
            throw new Exception("Original message not found.");
        }
        
        // Set supervisor as the receiver (original sender)
        $supervisor_id = $original_message['sender_id'];
        
        // Insert student's reply into database
        $stmt = $pdo->prepare("
            INSERT INTO messages (sender_id, receiver_id, message, reply_to_message_id, created_at) 
            VALUES (?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([$student_id, $supervisor_id, $reply, $original_message_id]);
        
        // Update original message to mark it as replied to (optional)
        $stmt = $pdo->prepare("
            UPDATE messages 
            SET has_reply = 1 
            WHERE id = ?
        ");
        $stmt->execute([$original_message_id]);
        
        $_SESSION['success'] = "Reply sent successfully to supervisor.";
    } catch (Exception $e) {
        $_SESSION['error'] = "Error sending reply: " . $e->getMessage();
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