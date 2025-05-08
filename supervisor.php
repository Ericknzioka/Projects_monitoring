<?php
session_start();
include 'database.php';

// Set session timeout
$timeout_duration = 1800; // 30 minutes in seconds

// Update the LAST_ACTIVITY timestamp on every page load
$_SESSION['LAST_ACTIVITY'] = time();

// Check if user is logged in with correct role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'supervisor') {
    header("Location: login.php");
    exit();
}

// Fetch projects assigned to students
$projects = $pdo->query("
    SELECT p.*, u.first_name, u.last_name, u.reg_no 
    FROM projects p 
    JOIN users u ON p.user_id = u.id 
    WHERE u.role = 'student'
")->fetchAll(); 

// Fetch notifications with more details
$notifications = $pdo->query("
    SELECT n.*, u.first_name, u.last_name, u.reg_no 
    FROM notifications n
    LEFT JOIN users u ON n.sender_id = u.id
    WHERE n.user_id = " . $_SESSION['user_id'] . "
    ORDER BY n.created_at DESC
")->fetchAll();

// Handle notification actions: mark as read or delete
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['notification_action'])) {
    $notification_id = $_POST['notification_id'];
    $action = $_POST['notification_action'];
    
    if ($action === 'mark_read') {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
        $stmt->execute([$notification_id, $_SESSION['user_id']]);
    } elseif ($action === 'delete') {
        $stmt = $pdo->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
        $stmt->execute([$notification_id, $_SESSION['user_id']]);
    }
    
    // Redirect to prevent form resubmission
    header("Location: supervisor.php");
    exit();
}

// Handle project approval/rejection
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $project_id = $_POST['project_id'];
    $action = $_POST['action'];

    // First, get the user_id associated with this project
    $stmt = $pdo->prepare("SELECT user_id FROM projects WHERE id = ?");
    $stmt->execute([$project_id]);
    $user_id = $stmt->fetchColumn();

    if ($action === 'approve') {
        $stmt = $pdo->prepare("UPDATE projects SET status = 'approved' WHERE id = ?");
        $stmt->execute([$project_id]);
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, message, sender_id, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$user_id, "Your project has been approved.", $_SESSION['user_id']]);
    } elseif ($action === 'reject') {
        $stmt = $pdo->prepare("UPDATE projects SET status = 'rejected' WHERE id = ?");
        $stmt->execute([$project_id]);
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, message, sender_id, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$user_id, "Your project has been rejected.", $_SESSION['user_id']]);
    }
    // Use POST-redirect-GET pattern to prevent form resubmission on refresh
    header("Location: supervisor.php");
    exit();
}

// Handle remarks submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['remarks'])) {
    $project_id = $_POST['project_id'];
    $remarks = $_POST['remarks'];

    // Get the user_id associated with this project
    $stmt = $pdo->prepare("SELECT user_id FROM projects WHERE id = ?");
    $stmt->execute([$project_id]);
    $user_id = $stmt->fetchColumn();

    $stmt = $pdo->prepare("INSERT INTO comments (project_id, message) VALUES (?, ?)");
    $stmt->execute([$project_id, $remarks]);
    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, message, sender_id, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->execute([$user_id, "You have received new remarks on your project.", $_SESSION['user_id']]);

    // Use POST-redirect-GET pattern to prevent form resubmission on refresh
    header("Location: supervisor.php");
    exit();
}

// Handle project status update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_project_id'])) {
    $update_project_id = $_POST['update_project_id'];
    $new_status = $_POST['new_status'];

    // Get the user_id associated with this project
    $stmt = $pdo->prepare("SELECT user_id FROM projects WHERE id = ?");
    $stmt->execute([$update_project_id]);
    $user_id = $stmt->fetchColumn();

    $stmt = $pdo->prepare("UPDATE projects SET status = ? WHERE id = ?");
    $stmt->execute([$new_status, $update_project_id]);

    // Send notification to student
    $notification_message = "Your project status has been updated to '$new_status' on " . date('Y-m-d H:i:s');
    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, message, sender_id, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->execute([$user_id, $notification_message, $_SESSION['user_id']]);
    
    // Use POST-redirect-GET pattern to prevent form resubmission on refresh
    header("Location: supervisor.php");
    exit();
}

// Handle file sharing
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['file'])) {
    $student_id = $_POST['student_id'];
    $supervisor_id = $_SESSION['user_id'];
    $file = $_FILES['file'];

    // Define the upload directory
    $upload_dir = 'uploads/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    $file_path = $upload_dir . basename($file['name']);

    // Move the uploaded file to the designated directory
    if (move_uploaded_file($file['tmp_name'], $file_path)) {
        // Insert file information into the database
        $stmt = $pdo->prepare("INSERT INTO shared_files (student_id, supervisor_id, file_path, created_at, shared_by) VALUES (?, ?, ?, NOW(), 'supervisor')");
        $stmt->execute([$student_id, $supervisor_id, $file_path]);
        
        // Send notification to student
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, message, sender_id, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$student_id, "Your supervisor has shared a file with you: " . basename($file['name']), $supervisor_id]);
        
        header("Location: supervisor.php");
        exit();
    } else {
        echo "File upload failed.";
    }
}

// Handle sending message/comment about a shared file
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_message'])) {
    $student_id = $_POST['student_id'];
    $file_id = $_POST['file_id'];
    $message = $_POST['message'];
    $sender_id = $_SESSION['user_id'];
    
    // Insert message into the messages table
    $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message, created_at, is_read) VALUES (?, ?, ?, NOW(), 0)");
    $stmt->execute([$sender_id, $student_id, $message]);
    
    // Send notification to student
    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, message, sender_id, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->execute([$student_id, "Your supervisor has sent feedback about your shared file.", $sender_id]);
    
    // Redirect to prevent form resubmission
    header("Location: supervisor.php?section=student-shared-files&message_sent=1");
    exit();
}

// Fetch notifications again to ensure the latest ones are displayed
$notifications = $pdo->query("
    SELECT n.*, u.first_name, u.last_name, u.reg_no 
    FROM notifications n
    LEFT JOIN users u ON n.sender_id = u.id
    WHERE n.user_id = " . $_SESSION['user_id'] . "
    ORDER BY n.created_at DESC
")->fetchAll();

// Fetch files shared BY supervisor TO students
$shared_files_by_supervisor = $pdo->query("
    SELECT sf.*, u.first_name, u.last_name, sf.created_at 
    FROM shared_files sf 
    JOIN users u ON sf.student_id = u.id 
    WHERE sf.supervisor_id = " . $_SESSION['user_id'] . " AND sf.shared_by = 'supervisor'"
)->fetchAll();

// Fetch files shared BY students TO supervisor
$shared_files_from_students = $pdo->query("
    SELECT sf.*, u.first_name, u.last_name, u.reg_no, u.programme_of_study, sf.created_at 
    FROM shared_files sf 
    JOIN users u ON sf.student_id = u.id 
    WHERE sf.supervisor_id = " . $_SESSION['user_id'] . " AND sf.shared_by = 'student'"
)->fetchAll();

// Fetch previous messages sent to students about their files
$previous_messages = $pdo->query("
    SELECT m.*, u_sender.first_name as sender_first_name, u_sender.last_name as sender_last_name,
    u_receiver.first_name as receiver_first_name, u_receiver.last_name as receiver_last_name,
    u_receiver.reg_no
    FROM messages m
    JOIN users u_sender ON m.sender_id = u_sender.id
    JOIN users u_receiver ON m.receiver_id = u_receiver.id
    WHERE m.sender_id = " . $_SESSION['user_id'] . "
    ORDER BY m.created_at DESC
")->fetchAll();

// Count unread notifications
$unread_count = $pdo->query("SELECT COUNT(*) FROM notifications WHERE user_id = " . $_SESSION['user_id'] . " AND is_read = 0")->fetchColumn();

// Get the active section from URL parameter if available
$active_section = isset($_GET['section']) ? $_GET['section'] : '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <title>Supervisor Dashboard</title>
    <style>
        body {
            display: flex;
        }
        .main-sidebar {
            width: 250px;
            background-color: #343a40;
            color: white;
        }
        .main-sidebar .nav-link {
            color: white; 
        }
        .main-sidebar .nav-link:hover {
            background-color: #495057;
        }
        .content {
            flex: 1;
            padding: 20px;
        }
        .content-section {
            display: none; 
        }
        .notification-item {
            position: relative;
            margin-bottom: 10px;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        .notification-item.unread {
            background-color: #f8f9fa;
            border-left: 4px solid #007bff;
        }
        .notification-time {
            font-size: 0.8rem;
            color: #6c757d;
        }
        .notification-actions {
            margin-top: 10px;
        }
        .notification-badge {
            position: absolute;
            display: inline-block;
            background-color: #dc3545;
            color: white;
            font-size: 0.7rem;
            padding: 2px 6px;
            border-radius: 10px;
            margin-left: 5px;
        }
        .file-notification {
            border-left: 4px solid #28a745;
        }
        .notification-sender {
            font-weight: bold;
            margin-bottom: 5px;
        }
        .message-history {
            margin-top: 20px;
        }
        .message-item {
            background-color: #f8f9fa;
            border-left: 4px solid #17a2b8;
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 5px;
        }
        .success-message {
            background-color: #d4edda;
            color: #155724;
            border-color: #c3e6cb;
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <aside class="main-sidebar sidebar-dark-primary elevation-4">
        <div class="dropdown">
            <a href="./" class="brand-link text-center">
                <h3 class="p-0 m-0"><b>SUPERVISOR</b></h3>
            </a>
        </div>
        <div class="sidebar pb-4 mb-4">
            <nav class="mt-2">
                <ul class="nav nav-pills nav-sidebar flex-column nav-flat" data-widget="treeview" role="menu" data-accordion="false">
                    <li class="nav-item">
                        <a href="#" class="nav-link nav-home">
                            <i class="nav-icon fas fa-tachometer-alt"></i>
                            <p>Dashboard</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#students-projects" class="nav-link nav-projects section-link" data-section="students-projects">
                            <i class="nav-icon fas fa-briefcase"></i>
                            <p>Students' Projects</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#notifications" class="nav-link nav-notifications section-link" data-section="notifications">
                            <i class="nav-icon fas fa-bell"></i>
                            <p>Notifications
                                <?php if ($unread_count > 0): ?>
                                <span class="notification-badge"><?php echo $unread_count; ?></span>
                                <?php endif; ?>
                            </p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#share-files" class="nav-link nav-share-files section-link" data-section="share-files">
                            <i class="nav-icon fas fa-share-alt"></i>
                            <p>Share Files</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#shared-files" class="nav-link nav-shared-files section-link" data-section="shared-files">
                            <i class="nav-icon fas fa-file-upload"></i>
                            <p>Files You Shared</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#student-shared-files" class="nav-link nav-student-shared-files section-link" data-section="student-shared-files">
                            <i class="nav-icon fas fa-file-download"></i>
                            <p>Files From Students</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#sent-messages" class="nav-link nav-sent-messages section-link" data-section="sent-messages">
                            <i class="nav-icon fas fa-comment"></i>
                            <p>Sent Messages</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="logout.php" class="nav-link nav-logout">
                            <i class="nav-icon fas fa-sign-out-alt"></i>
                            <p>Logout</p>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
    </aside>

    <div class="content">
        <header class="d-flex justify-content-between align-items-center mb-4">
            <h1>Supervisor Dashboard</h1>
            <form method="POST" action="generate_report.php" class="form-inline">
                <div class="form-group mr-2">
                    <label for="report_type" class="mr-2">Select Report Type</label>
                    <select name="report_type" id="report_type" class="form-control" required>
                        <option value="projects">Projects</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Generate Report</button>
            </form>
        </header>

        <div id="students-projects" class="content-section">
            <h2>Students' Projects</h2>
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Student Name</th>
                        <th>Registration Number</th>
                        <th>Project Title</th>
                        <th>Status</th>
                        <th>Remarks</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($projects as $project): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($project['first_name'] . ' ' . $project['last_name']); ?></td>
                        <td><?php echo htmlspecialchars($project['reg_no']); ?></td>
                        <td><?php echo htmlspecialchars($project['title']); ?></td>
                        <td><?php echo htmlspecialchars($project['status']); ?></td>
                        <td>
                            <form method="POST" action="">
                                <input type="hidden" name="project_id" value="<?php echo htmlspecialchars($project['id']); ?>">
                                <input type="text" name="remarks" placeholder="Add remarks" class="form-control">
                                <input type="submit" value="Submit" class="btn btn-secondary btn-sm mt-1">
                            </form>
                        </td>
                        <td>
                            <form method="POST" action="">
                                <input type="hidden" name="project_id" value="<?php echo htmlspecialchars($project['id']); ?>">
                                <button type="submit" name="action" value="approve" class="btn btn-success btn-sm">Approve</button>
                                <button type="submit" name="action" value="reject" class="btn btn-danger btn-sm">Reject</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div id="notifications" class="content-section">
            <h2>Notifications</h2>
            <?php if (empty($notifications)): ?>
                <div class="alert alert-info">No notifications at this time.</div>
            <?php else: ?>
                <div class="notifications-container">
                    <?php foreach ($notifications as $notification): 
                        // Check if this is a file upload notification
                        $is_file_notification = strpos($notification['message'], 'uploaded a file') !== false;
                        $is_read = isset($notification['is_read']) && $notification['is_read'] == 1;
                    ?>
                        <div class="notification-item <?php echo !$is_read ? 'unread' : ''; ?> <?php echo $is_file_notification ? 'file-notification' : ''; ?>">
                            <?php if (!empty($notification['first_name'])): ?>
                                <div class="notification-sender">
                                    From: <?php echo htmlspecialchars($notification['first_name'] . ' ' . $notification['last_name']); ?>
                                    <?php if (!empty($notification['reg_no'])): ?>
                                        (<?php echo htmlspecialchars($notification['reg_no']); ?>)
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="notification-content">
                                <?php echo htmlspecialchars($notification['message']); ?>
                            </div>
                            
                            <?php if (isset($notification['created_at'])): ?>
                                <div class="notification-time">
                                    <?php echo htmlspecialchars(date('M d, Y h:i A', strtotime($notification['created_at']))); ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="notification-actions">
                                <form method="POST" action="" class="d-inline-block">
                                    <input type="hidden" name="notification_id" value="<?php echo htmlspecialchars($notification['id']); ?>">
                                    <?php if (!$is_read): ?>
                                        <button type="submit" name="notification_action" value="mark_read" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-check"></i> Mark as Read
                                        </button>
                                    <?php endif; ?>
                                    <button type="submit" name="notification_action" value="delete" class="btn btn-sm btn-outline-danger">
                                        <i class="fas fa-trash"></i> Archive
                                    </button>
                                </form>
                                
                                <?php if ($is_file_notification): ?>
                                    <a href="#student-shared-files" class="btn btn-sm btn-outline-success section-link" data-section="student-shared-files">
                                        <i class="fas fa-eye"></i> View Files
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div id="share-files" class="content-section">
            <h2>Share Files with Students</h2>
            <form method="POST" action="" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="file">Select File</label>
                    <input type="file" name="file" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="student_id">Select Student</label>
                    <select name="student_id" class="form-control" required>
                        <?php
                        $students_for_file_sharing = $pdo->query("SELECT * FROM users WHERE role = 'student'")->fetchAll();
                        foreach ($students_for_file_sharing as $student): ?>
                            <option value="<?php echo htmlspecialchars($student['id']); ?>"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Share File</button>
            </form>
        </div>

        <div id="shared-files" class="content-section">
            <h2>Files You Shared with Students</h2>
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Student Name</th>
                        <th>File Name</th>
                        <th>Upload Date</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($shared_files_by_supervisor as $file): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($file['first_name'] . ' ' . $file['last_name']); ?></td>
                            <td><?php echo htmlspecialchars(basename($file['file_path'])); ?></td>
                            <td><?php echo htmlspecialchars($file['created_at']); ?></td>
                            <td>
                                <a href="<?php echo htmlspecialchars($file['file_path']); ?>" class="btn btn-primary btn-sm" download>Download</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div id="student-shared-files" class="content-section">
            <h2>Files Shared by Students</h2>
            
            <?php if (isset($_GET['message_sent']) && $_GET['message_sent'] == 1): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i> Your message has been sent successfully!
                </div>
            <?php endif; ?>
            
            <?php if (empty($shared_files_from_students)): ?>
                <div class="alert alert-info">No files have been shared by students yet.</div>
            <?php else: ?>
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Student Name</th>
                            <th>Registration Number</th>
                            <th>Program of Study</th>
                            <th>File Name</th>
                            <th>Upload Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($shared_files_from_students as $file): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($file['first_name'] . ' ' . $file['last_name']); ?></td>
                                <td><?php echo htmlspecialchars($file['reg_no']); ?></td>
                                <td><?php echo htmlspecialchars($file['programme_of_study']); ?></td>
                                <td><?php echo htmlspecialchars(basename($file['file_path'])); ?></td>
                                <td><?php echo htmlspecialchars($file['created_at']); ?></td>
                                <td>
                                    <a href="<?php echo htmlspecialchars($file['file_path']); ?>" class="btn btn-primary btn-sm" download>Download</a>
                                    <button type="button" class="btn btn-info btn-sm" 
                                        data-toggle="modal" 
                                        data-target="#messageModal" 
                                        data-student-id="<?php echo htmlspecialchars($file['student_id']); ?>"
                                        data-file-id="<?php echo htmlspecialchars($file['id']); ?>"
                                        data-student-name="<?php echo htmlspecialchars($file['first_name'] . ' ' . $file['last_name']); ?>"
                                        data-file-name="<?php echo htmlspecialchars(basename($file['file_path'])); ?>">
                                        <i class="fas fa-comment"></i> Send Feedback
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        
        <div id="sent-messages" class="content-section">
            <h2>Sent Messages</h2>
            <?php if (empty($previous_messages)): ?>
                <div class="alert alert-info">You haven't sent any messages yet.</div>
            <?php else: ?>
                <div class="message-history">
                    <?php foreach ($previous_messages as $message): ?>
                        <div class="message-item">
                            <div class="message-header">
                                <strong>To: </strong> <?php echo htmlspecialchars($message['receiver_first_name'] . ' ' . $message['receiver_last_name']); ?>
                                <?php if (!empty($message['reg_no'])): ?>
                                    (<?php echo htmlspecialchars($message['reg_no']); ?>)
                                <?php endif; ?>
                                <span class="float-right text-muted">
                                    <?php echo htmlspecialchars(date('M d, Y h:i A', strtotime($message['created_at']))); ?>
                                </span>
                            </div>
                            <div class="message-content mt-2">
                                <?php echo htmlspecialchars($message['message']); ?>
                            </div>
                            <div class="message-status mt-2">
                                <span class="badge <?php echo $message['is_read'] ? 'badge-success' : 'badge-secondary'; ?>">
                                    <?php echo $message['is_read'] ? 'Read' : 'Unread'; ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal for sending messages about files -->
    <div class="modal fade" id="messageModal" tabindex="-1" role="dialog" aria-labelledby="messageModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="messageModalLabel">Send Feedback</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="student_id" id="student_id_input">
                        <input type="hidden" name="file_id" id="file_id_input">
                        
                        <div class="form-group">
                            <label>Student:</label>
                            <p id="student_name_display"></p>
                        </div>
                        
                        <div class="form-group">
                            <label>File:</label>
                            <p id="file_name_display"></p>
                        </div>
                        
                        <div class="form-group">
                            <label for="message">Your Feedback:</label>
                            <textarea class="form-control" name="message" id="message" rows="5" required></textarea>
                        </div>
                    </div>
<div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" name="send_message" class="btn btn-primary">Send Feedback</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.2/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        // Store the active section in localStorage
        function toggleSection(sectionId) {
            $('.content-section').hide();
            $('#' + sectionId).show();
            // Save the active section ID to localStorage
            localStorage.setItem('activeSectionId', sectionId);
        }

        // When the page loads, check if there's a saved section
        $(document).ready(function() {
            // Get the active section from localStorage, default to showing the projects section
            var activeSectionId = localStorage.getItem('activeSectionId') || 'students-projects';
            toggleSection(activeSectionId);
            
            // Add click handlers for section links
            $('.section-link').on('click', function(e) {
                e.preventDefault();
                var sectionId = $(this).data('section');
                toggleSection(sectionId);
            });

            // Home link should show the first section by default
            $('.nav-home').on('click', function(e) {
                e.preventDefault();
                toggleSection('students-projects');
            });
            
            // If notification section is clicked, process any "view files" links
            $('#notifications').on('click', '.section-link', function(e) {
                e.preventDefault();
                var sectionId = $(this).data('section');
                toggleSection(sectionId);
            });
            
            // Handle the message modal
            $('#messageModal').on('show.bs.modal', function (event) {
                var button = $(event.relatedTarget);
                var studentId = button.data('student-id');
                var fileId = button.data('file-id');
                var studentName = button.data('student-name');
                var fileName = button.data('file-name');
                
                var modal = $(this);
                modal.find('#student_id_input').val(studentId);
                modal.find('#file_id_input').val(fileId);
                modal.find('#student_name_display').text(studentName);
                modal.find('#file_name_display').text(fileName);
            });
            
            // Clear modal form when closed
            $('#messageModal').on('hidden.bs.modal', function () {
                $(this).find('form')[0].reset();
            });
            
            // Add a success message fadeout
            setTimeout(function() {
                $('.success-message').fadeOut('slow');
            }, 3000);
        });
    </script>
</body>
</html>
