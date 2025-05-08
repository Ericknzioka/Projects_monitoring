<?php
// This file displays messages and provides reply functionality
// It should be included in your dashboard or message view

// Make sure user is logged in and session is started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'database.php';

// Get user role (assuming you have a user_roles column or similar)
$stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
$user_role = $user['role'] ?? 'student'; // Default to student if role not set

// Get received messages
$stmt = $pdo->prepare("
    SELECT m.*, 
           u_sender.name AS sender_name,
           u_receiver.name AS receiver_name,
           r.message AS reply_message,
           r.created_at AS reply_date
    FROM messages m
    LEFT JOIN users u_sender ON m.sender_id = u_sender.id
    LEFT JOIN users u_receiver ON m.receiver_id = u_receiver.id
    LEFT JOIN messages r ON m.id = r.reply_to_message_id
    WHERE m.receiver_id = ?
    ORDER BY m.created_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$received_messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get sent messages
$stmt = $pdo->prepare("
    SELECT m.*, 
           u_sender.name AS sender_name,
           u_receiver.name AS receiver_name,
           r.message AS reply_message,
           r.created_at AS reply_date
    FROM messages m
    LEFT JOIN users u_sender ON m.sender_id = u_sender.id
    LEFT JOIN users u_receiver ON m.receiver_id = u_receiver.id
    LEFT JOIN messages r ON m.id = r.reply_to_message_id
    WHERE m.sender_id = ?
    ORDER BY m.created_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$sent_messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="message-container">
    <h2>Messages</h2>
    
    <!-- Tab navigation -->
    <ul class="nav nav-tabs" id="messageTabs" role="tablist">
        <li class="nav-item">
            <a class="nav-link active" id="received-tab" data-toggle="tab" href="#received" role="tab">Received Messages</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="sent-tab" data-toggle="tab" href="#sent" role="tab">Sent Messages</a>
        </li>
    </ul>
    
    <!-- Tab content -->
    <div class="tab-content" id="messageTabContent">
        <!-- Received Messages Tab -->
        <div class="tab-pane fade show active" id="received" role="tabpanel">
            <?php if (empty($received_messages)): ?>
                <p>No messages received.</p>
            <?php else: ?>
                <div class="list-group">
                    <?php foreach ($received_messages as $message): ?>
                        <div class="list-group-item message-item">
                            <div class="message-header">
                                <strong>From:</strong> <?= htmlspecialchars($message['sender_name']) ?> 
                                <span class="float-right text-muted"><?= date('M j, Y g:i A', strtotime($message['created_at'])) ?></span>
                            </div>
                            <div class="message-body">
                                <?= nl2br(htmlspecialchars($message['message'])) ?>
                            </div>
                            
                            <?php if ($user_role == 'student' && empty($message['reply_message'])): ?>
                                <!-- Reply Form for Students -->
                                <button class="btn btn-sm btn-primary mt-2" 
                                        onclick="toggleReplyForm('reply-form-<?= $message['id'] ?>')">Reply</button>
                                
                                <div id="reply-form-<?= $message['id'] ?>" class="reply-form mt-2" style="display: none;">
                                    <form action="student_reply.php" method="post">
                                        <input type="hidden" name="original_message_id" value="<?= $message['id'] ?>">
                                        <div class="form-group">
                                            <textarea name="reply" class="form-control" rows="3" required 
                                                      placeholder="Write your reply here..."></textarea>
                                        </div>
                                        <button type="submit" class="btn btn-success">Send Reply</button>
                                    </form>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($message['reply_message'])): ?>
                                <!-- Display Reply -->
                                <div class="message-reply mt-3 ml-4 p-2 bg-light">
                                    <div class="reply-header">
                                        <strong>Reply:</strong>
                                        <span class="text-muted"><?= date('M j, Y g:i A', strtotime($message['reply_date'])) ?></span>
                                    </div>
                                    <div class="reply-body">
                                        <?= nl2br(htmlspecialchars($message['reply_message'])) ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Sent Messages Tab -->
        <div class="tab-pane fade" id="sent" role="tabpanel">
            <?php if (empty($sent_messages)): ?>
                <p>No messages sent.</p>
            <?php else: ?>
                <div class="list-group">
                    <?php foreach ($sent_messages as $message): ?>
                        <div class="list-group-item message-item">
                            <div class="message-header">
                                <strong>To:</strong> <?= htmlspecialchars($message['receiver_name']) ?> 
                                <span class="float-right text-muted"><?= date('M j, Y g:i A', strtotime($message['created_at'])) ?></span>
                            </div>
                            <div class="message-body">
                                <?= nl2br(htmlspecialchars($message['message'])) ?>
                            </div>
                            
                            <?php if (!empty($message['reply_message'])): ?>
                                <!-- Display Reply to supervisors -->
                                <div class="message-reply mt-3 ml-4 p-2 bg-light">
                                    <div class="reply-header">
                                        <strong>Student Reply:</strong>
                                        <span class="text-muted"><?= date('M j, Y g:i A', strtotime($message['reply_date'])) ?></span>
                                    </div>
                                    <div class="reply-body">
                                        <?= nl2br(htmlspecialchars($message['reply_message'])) ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function toggleReplyForm(formId) {
    var form = document.getElementById(formId);
    if (form.style.display === "none") {
        form.style.display = "block";
    } else {
        form.style.display = "none";
    }
}
</script>

<style>
.message-item {
    margin-bottom: 15px;
    border-left: 3px solid #007bff;
}

.message-header {
    margin-bottom: 8px;
    font-size: 0.9rem;
}

.message-body {
    margin-bottom: 10px;
}

.message-reply {
    border-left: 3px solid #28a745;
    border-radius: 4px;
}

.reply-header {
    font-size: 0.85rem;
    margin-bottom: 5px;
}

.reply-form {
    background-color: #f8f9fa;
    padding: 10px;
    border-radius: 4px;
}
</style>