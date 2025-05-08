<?php
// Start session with proper configuration - FIXED SESSION HANDLING
// Make sure session starts at the very beginning before any output
ini_set('session.cookie_lifetime', 86400); // 24 hours
ini_set('session.gc_maxlifetime', 86400); // 24 hours

// Start session with proper configuration
session_start();

// Include database connection
include 'database.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit();
}

// Fetch user information
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if ($user['approved'] == 0) {
    $message = "Please wait for supervisor approval.";
} elseif ($user['approved'] == -1) {
    $message = "Sorry, you are not a bonafide student.";
} else {
    // Proceed with normal student functionalities
}

// Fetch all supervisors to share files with
$supervisors = $pdo->query("SELECT * FROM users WHERE role = 'supervisor'")->fetchAll();

// Handle file upload to share with supervisor
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['file'])) {
    $file = $_FILES['file'];
    $fileName = $file['name'];
    $fileTmpName = $file['tmp_name'];
    $fileError = $file['error'];
    $supervisor_id = $_POST['supervisor_id']; // Get the selected supervisor ID

    if ($fileError === 0) {
        // Define the upload directory
        $upload_dir = 'uploads/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        $file_path = $upload_dir . basename($fileName);

        // Move the uploaded file to the designated directory
        if (move_uploaded_file($fileTmpName, $file_path)) {
            // Insert file information into the shared_files table
            $stmt = $pdo->prepare("INSERT INTO shared_files (student_id, supervisor_id, file_path, created_at, shared_by) VALUES (?, ?, ?, NOW(), 'student')");
            $stmt->execute([$_SESSION['user_id'], $supervisor_id, $file_path]);

            // Add notification for the selected supervisor
            $student_name = $user['first_name'] . ' ' . $user['last_name'];
            $notification = "Student $student_name has shared a file: '$fileName' with you.";
            $stmt = $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
            $stmt->execute([$supervisor_id, $notification]);
            
            $success_message = "File successfully shared with supervisor.";
        } else {
            $error_message = "File upload failed.";
        }
    }
}

// Handle message viewing
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['view_messages'])) {
    $file_id = $_POST['file_id'];
    $supervisor_id = $_POST['supervisor_id'];
    
    // Fetch messages related to this file from the supervisor
    $stmt = $pdo->prepare("
        SELECT m.*, u.first_name, u.last_name 
        FROM messages m
        JOIN users u ON m.sender_id = u.id
        WHERE m.sender_id = ? AND m.receiver_id = ? 
        ORDER BY m.created_at DESC
    ");
    $stmt->execute([$supervisor_id, $_SESSION['user_id']]);
    $messages = $stmt->fetchAll();
    
    // Mark messages as read
    $stmt = $pdo->prepare("UPDATE messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ?");
    $stmt->execute([$supervisor_id, $_SESSION['user_id']]);
}

// Handle project submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['project_title'])) {
    $project_title = $_POST['project_title'];
    $status = $_POST['status']; // Get the status from the form
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];

    // Check for duplicate project
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM projects WHERE user_id = ? AND title = ?");
    $stmt->execute([$_SESSION['user_id'], $project_title]);
    $count = $stmt->fetchColumn();

    if ($count == 0) {
        $stmt = $pdo->prepare("INSERT INTO projects (user_id, title, status, start_date, end_date) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$_SESSION['user_id'], $project_title, $status, $start_date, $end_date]);
        $success_message = "Project added successfully.";
    } else {
        $error_message = "Project '$project_title' already exists.";
    }
}

// Handle project status update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_project_id'])) {
    $update_project_id = $_POST['update_project_id'];
    $new_status = $_POST['new_status'];

    // Update the project status
    $stmt = $pdo->prepare("UPDATE projects SET status = ? WHERE id = ?");
    $stmt->execute([$new_status, $update_project_id]);

    // Fetch project information
    $stmt = $pdo->prepare("SELECT title FROM projects WHERE id = ?");
    $stmt->execute([$update_project_id]);
    $project_info = $stmt->fetch();

    // Send notification to supervisor
    $supervisor_id = 1; // Replace with actual supervisor ID logic
    $notification_message = "{$user['first_name']} {$user['last_name']} updated project '{$project_info['title']}' to status '$new_status' on " . date('Y-m-d H:i:s');
    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
    $stmt->execute([$supervisor_id, $notification_message]);
    
    $success_message = "Project status updated successfully.";
}

// Fetch projects for the logged-in student
$stmt = $pdo->prepare("SELECT * FROM projects WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$projects = $stmt->fetchAll();

// Fetch supervisor comments
$stmt = $pdo->prepare("SELECT * FROM comments WHERE project_id IN (SELECT id FROM projects WHERE user_id = ?)");
$stmt->execute([$_SESSION['user_id']]);
$comments = $stmt->fetchAll();

// Fetch files shared by the student with supervisors
$stmt = $pdo->prepare("
    SELECT sf.*, u.first_name, u.last_name 
    FROM shared_files sf 
    JOIN users u ON sf.supervisor_id = u.id 
    WHERE sf.student_id = ? AND sf.shared_by = 'student'
");
$stmt->execute([$_SESSION['user_id']]);
$shared_files = $stmt->fetchAll();

// Fetch files received from supervisors
$stmt = $pdo->prepare("
    SELECT sf.*, u.first_name, u.last_name 
    FROM shared_files sf 
    JOIN users u ON sf.supervisor_id = u.id 
    WHERE sf.student_id = ? AND sf.shared_by = 'supervisor'
");
$stmt->execute([$_SESSION['user_id']]);
$files_from_supervisors = $stmt->fetchAll();

// Fetch unread message counts for each supervisor
$stmt = $pdo->prepare("
    SELECT sender_id, COUNT(*) as unread_count 
    FROM messages 
    WHERE receiver_id = ? AND is_read = 0 
    GROUP BY sender_id
");
$stmt->execute([$_SESSION['user_id']]);
$unread_counts = [];
while ($row = $stmt->fetch()) {
    $unread_counts[$row['sender_id']] = $row['unread_count'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <title>Student Dashboard</title>
    <style>
        body {
            display: flex;
        }
        .main-sidebar {
            width: 250px;
            background-color: #343a40;
            color: white;
            min-height: 100vh;
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
        .report-form {
            position: absolute;
            top: 20px;
            right: 20px;
            z-index: 1000;
        }
        .content-section {
            display: none; 
        }
        .project-form {
            background-color: #add8e6; 
            padding: 15px;
            border-radius: 5px;
            color: black;
        }
        .badge-notify {
            background-color: #ff6b6b;
            color: white;
            border-radius: 50%;
            padding: 0.25em 0.6em;
            font-size: 75%;
            position: relative;
            margin-left: 5px;
        }
        .message-container {
            max-height: 400px;
            overflow-y: auto;
            border: 1px solid #ddd;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 15px;
        }
        .message-item {
            border-bottom: 1px solid #eee;
            padding: 10px 0;
        }
        .message-item:last-child {
            border-bottom: none;
        }
        .message-header {
            font-weight: bold;
            margin-bottom: 5px;
        }
        .message-date {
            font-size: 0.85em;
            color: #777;
        }
        .message-body {
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <aside class="main-sidebar sidebar-dark-primary elevation-4">
        <div class="dropdown">
            <a href="#" class="brand-link text-center">
                <h3 class="p-0 m-0"><b>STUDENT</b></h3>
            </a>
        </div>
        <div class="sidebar pb-4 mb-4">
            <nav class="mt-2">
                <ul class="nav nav-pills nav-sidebar flex-column nav-flat" data-widget="treeview" role="menu" data-accordion="false">
                    <li class="nav-item">
                        <a href="#" class="nav-link nav-home" data-target="#dashboard">
                            <i class="nav-icon fas fa-tachometer-alt"></i>
                            <p>Dashboard</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#" class="nav-link nav-projects" data-target="#your-projects">
                            <i class="nav-icon fas fa-briefcase"></i>
                            <p>Your Projects</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#" class="nav-link nav-file-upload" data-target="#upload-file">
                            <i class="nav-icon fas fa-upload"></i>
                            <p>Share Files</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#" class="nav-link nav-your-shared-files" data-target="#your-shared-files">
                            <i class="nav-icon fas fa-share-alt"></i>
                            <p>Files You Shared</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#" class="nav-link nav-supervisor-files" data-target="#supervisor-files">
                            <i class="nav-icon fas fa-download"></i>
                            <p>Files From Supervisors</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#" class="nav-link nav-comments" data-target="#supervisor-comments">
                            <i class="nav-icon fas fa-comments"></i>
                            <p>Supervisor Comments</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#" class="nav-link nav-messages" data-target="#messages">
                            <i class="nav-icon fas fa-envelope"></i>
                            <p>
                                Messages
                                <?php
                                $total_unread = array_sum($unread_counts);
                                if ($total_unread > 0):
                                ?>
                                <span class="badge badge-notify"><?php echo $total_unread; ?></span>
                                <?php endif; ?>
                            </p>
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
        <header class="text-left mb-4">
            <h1>Student Dashboard</h1>
            <div class="text-muted">Welcome, <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></div>
        </header>

        <?php if (isset($message)): ?>
            <div class="alert alert-warning"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <?php if (isset($success_message)): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <div class="report-form">
            <form method="POST" action="generate_report.php" class="form-inline mb-3">
                <div class="form-group mr-2">
                    <label for="report_type" class="mr-2">Select Report Type</label>
                    <select name="report_type" id="report_type" class="form-control" required>
                        <option value="project_status">Project Status</option>
                        <option value="file_uploads">File Uploads</option>
                        <option value="supervisor_comments">Supervisor Comments</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Generate Report</button>
            </form>
        </div>

        <div id="dashboard" class="content-section">
            <h2>Dashboard</h2>
            <p>Welcome to your dashboard. Here you can navigate to different sections.</p>
            <a href="faq.html" class="btn btn-primary">FaQ's</a>
            <a href="help.php" class="btn btn-primary">Help</a>
            
            <div class="row mt-4">
                <div class="col-md-3">
                    <div class="card bg-primary text-white mb-4">
                        <div class="card-body">
                            <h5 class="card-title">Projects</h5>
                            <p class="card-text">Total Projects: <?php echo count($projects); ?></p>
                        </div>
                        <div class="card-footer d-flex align-items-center justify-content-between">
                            <a class="small text-white stretched-link nav-link" href="#" data-target="#your-projects">View Details</a>
                            <div class="small text-white"><i class="fas fa-angle-right"></i></div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="card bg-success text-white mb-4">
                        <div class="card-body">
                            <h5 class="card-title">Shared Files</h5>
                            <p class="card-text">Files Shared: <?php echo count($shared_files); ?></p>
                        </div>
                        <div class="card-footer d-flex align-items-center justify-content-between">
                            <a class="small text-white stretched-link nav-link" href="#" data-target="#your-shared-files">View Details</a>
                            <div class="small text-white"><i class="fas fa-angle-right"></i></div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="card bg-info text-white mb-4">
                        <div class="card-body">
                            <h5 class="card-title">Comments</h5>
                            <p class="card-text">Supervisor Comments: <?php echo count($comments); ?></p>
                        </div>
                        <div class="card-footer d-flex align-items-center justify-content-between">
                            <a class="small text-white stretched-link nav-link" href="#" data-target="#supervisor-comments">View Details</a>
                            <div class="small text-white"><i class="fas fa-angle-right"></i></div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="card bg-warning text-white mb-4">
                        <div class="card-body">
                            <h5 class="card-title">Messages</h5>
                            <p class="card-text">
                                Unread Messages: <?php echo $total_unread; ?>
                            </p>
                        </div>
                        <div class="card-footer d-flex align-items-center justify-content-between">
                            <a class="small text-white stretched-link nav-link" href="#" data-target="#messages">View Details</a>
                            <div class="small text-white"><i class="fas fa-angle-right"></i></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div id="your-projects" class="content-section">
            <h2>Your Projects</h2>
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Status</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                        <th>Update Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($projects)): ?>
                        <tr>
                            <td colspan="5" class="text-center">No projects found. Add your first project below.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($projects as $project): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($project['title']); ?></td>
                            <td><?php echo htmlspecialchars($project['status']); ?></td>
                            <td><?php echo htmlspecialchars($project['start_date']); ?></td>
                            <td><?php echo htmlspecialchars($project['end_date']); ?></td>
                            <td>
                                <form method="POST" action="<?php echo $_SERVER['PHP_SELF']; ?>">
                                    <input type="hidden" name="update_project_id" value="<?php echo $project['id']; ?>">
                                    <select name="new_status" class="form-control" required>
                                        <option value="ongoing" <?php echo ($project['status'] == 'ongoing') ? 'selected' : ''; ?>>Ongoing</option>
                                        <option value="completed" <?php echo ($project['status'] == 'completed') ? 'selected' : ''; ?>>Completed</option>
                                        <option value="on_hold" <?php echo ($project['status'] == 'on_hold') ? 'selected' : ''; ?>>On Hold</option>
                                        <option value="done" <?php echo ($project['status'] == 'done') ? 'selected' : ''; ?>>Done</option>
                                    </select>
                                    <button type="submit" class="btn btn-warning btn-sm mt-2">Update</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            <h3>Add New Project</h3>
            <div class="project-form">
                <form method="POST" action="<?php echo $_SERVER['PHP_SELF']; ?>">
                    <div class="form-row align-items-center">
                        <div class="col-auto">
                            <input type="text" name="project_title" id="project_title" class="form-control mb-2" placeholder="Project Title" required>
                        </div>
                        <div class="col-auto">
                            <label for="start_date" class="mb-2">Start Date</label>
                            <input type="date" name="start_date" id="start_date" class="form-control mb-2" required>
                        </div>
                        <div class="col-auto">
                            <label for="end_date" class="mb-2">End Date</label>
                            <input type="date" name="end_date" id="end_date" class="form-control mb-2" required>
                        </div>
                        <div class="col-auto">
                            <label for="status" class="mb-2">Status</label>
                            <select name="status" id="status" class="form-control mb-2" required>
                                <option value="started">Started</option>
                                <option value="on_progress">On Progress</option>
                                <option value="on_hold">On Hold</option>
                                <option value="done">Done</option>
                            </select>
                        </div>
                        <div class="col-auto">
                            <button type="submit" class="btn btn-success mb-2">Add Project</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div id="upload-file" class="content-section">
            <h2>Share Files with Supervisors</h2>
            <form method="POST" enctype="multipart/form-data" action="<?php echo $_SERVER['PHP_SELF']; ?>" class="mb-4">
                <div class="form-group">
                    <label for="file">Select File</label>
                    <input type="file" name="file" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="supervisor_id">Select Supervisor</label>
                    <select name="supervisor_id" class="form-control" required>
                        <?php foreach ($supervisors as $supervisor): ?>
                            <option value="<?php echo htmlspecialchars($supervisor['id']); ?>">
                                <?php echo htmlspecialchars($supervisor['first_name'] . ' ' . $supervisor['last_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Share File</button>
            </form>
        </div>
        
        <div id="your-shared-files" class="content-section">
            <h2>Files You Shared with Supervisors</h2>
            <?php if (empty($shared_files)): ?>
                <div class="alert alert-info">You haven't shared any files with supervisors yet.</div>
            <?php else: ?>
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>File Name</th>
                            <th>Shared With</th>
                            <th>Date Shared</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($shared_files as $file): ?>
                            <tr>
                                <td><?php echo htmlspecialchars(basename($file['file_path'])); ?></td>
                                <td><?php echo htmlspecialchars($file['first_name'] . ' ' . $file['last_name']); ?></td>
                                <td><?php echo htmlspecialchars($file['created_at']); ?></td>
                                <td>
                                    <a href="<?php echo htmlspecialchars($file['file_path']); ?>" class="btn btn-info btn-sm" download>Download</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        
        <div id="supervisor-files" class="content-section">
            <h2>Files From Supervisors</h2>
            <?php if (empty($files_from_supervisors)): ?>
                <div class="alert alert-info">No files have been shared with you by supervisors yet.</div>
            <?php else: ?>
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>File Name</th>
                            <th>Shared By</th>
                            <th>Date Shared</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($files_from_supervisors as $file): ?>
                            <tr>
                                <td><?php echo htmlspecialchars(basename($file['file_path'])); ?></td>
                                <td><?php echo htmlspecialchars($file['first_name'] . ' ' . $file['last_name']); ?></td>
                                <td><?php echo htmlspecialchars($file['created_at']); ?></td>
                                <td>
                                    <div class="btn-group">
                                        <a href="<?php echo htmlspecialchars($file['file_path']); ?>" class="btn btn-info btn-sm" download>Download</a>
                                        <form method="POST" action="<?php echo $_SERVER['PHP_SELF']; ?>" class="d-inline">
                                            <input type="hidden" name="view_messages" value="1">
                                            <input type="hidden" name="file_id" value="<?php echo $file['id']; ?>">
                                            <input type="hidden" name="supervisor_id" value="<?php echo $file['supervisor_id']; ?>">
                                            <button type="submit" class="btn btn-primary btn-sm">
                                                Messages
                                                <?php if (isset($unread_counts[$file['supervisor_id']]) && $unread_counts[$file['supervisor_id']] > 0): ?>
                                                <span class="badge badge-light"><?php echo $unread_counts[$file['supervisor_id']]; ?></span>
                                                <?php endif; ?>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            
            <?php if (isset($messages) && !empty($messages)): ?>
                <div class="mt-4">
                    <h3>Messages from <?php echo htmlspecialchars($messages[0]['first_name'] . ' ' . $messages[0]['last_name']); ?></h3>
                    <div class="message-container">
                        <?php foreach ($messages as $msg): ?>
                            <div class="message-item">
                                <div class="message-header">
                                    <span class="message-date"><?php echo date('M d, Y H:i', strtotime($msg['created_at'])); ?></span>
                                </div>
                                <div class="message-body">
                                    <?php echo htmlspecialchars($msg['message']); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div id="supervisor-comments" class="content-section">
            <h2>Supervisor Comments</h2>
            <?php if (empty($comments)): ?>
                <div class="alert alert-info">No comments from supervisors yet.</div>
            <?php else: ?>
                <ul class="list-group">
                    <?php foreach ($comments as $comment): ?>
                        <li class="list-group-item">
                            <strong>Date:</strong> <?php echo htmlspecialchars(date('M d, Y', strtotime($comment['created_at']))); ?><br>
                            <strong>Comment:</strong> <?php echo htmlspecialchars($comment['message']); ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
        
        <div id="messages" class="content-section">
            <h2>Messages from Supervisors</h2>
            
            <?php
            // Fetch all messages grouped by sender
            $stmt = $pdo->prepare("
                SELECT m.*, u.first_name, u.last_name 
                FROM messages m
                JOIN users u ON m.sender_id = u.id
                WHERE m.receiver_id = ? AND u.role = 'supervisor'
                ORDER BY m.created_at DESC
            ");
            $stmt->execute([$_SESSION['user_id']]);
            $all_messages = $stmt->fetchAll();
            
            // Group messages by sender
            $messages_by_sender = [];
            foreach ($all_messages as $msg) {
                $sender_id = $msg['sender_id'];
                if (!isset($messages_by_sender[$sender_id])) {
                    $messages_by_sender[$sender_id] = [
                        'name' => $msg['first_name'] . ' ' . $msg['last_name'],
                        'messages' => []
                    ];
                }
                if(!isset($messages_by_sender[$sender_id]['messages'])) {
                    $messages_by_sender[$sender_id]['messages'] = [];
                }
                $messages_by_sender[$sender_id]['messages'][] = $msg;
                }
                
                if (empty($messages_by_sender)): ?>
                    <div class="alert alert-info">No messages from supervisors yet.</div>
                <?php else: ?>
                    <div class="accordion" id="messagesAccordion">
                        <?php foreach ($messages_by_sender as $sender_id => $data): ?>
                            <div class="card">
                                <div class="card-header" id="heading<?php echo $sender_id; ?>">
                                    <h2 class="mb-0">
                                        <button class="btn btn-link btn-block text-left" type="button" data-toggle="collapse" data-target="#collapse<?php echo $sender_id; ?>" aria-expanded="false" aria-controls="collapse<?php echo $sender_id; ?>">
                                            <?php echo htmlspecialchars($data['name']); ?>
                                            <?php if (isset($unread_counts[$sender_id]) && $unread_counts[$sender_id] > 0): ?>
                                                <span class="badge badge-notify"><?php echo $unread_counts[$sender_id]; ?></span>
                                            <?php endif; ?>
                                        </button>
                                    </h2>
                                </div>
    
                                <div id="collapse<?php echo $sender_id; ?>" class="collapse" aria-labelledby="heading<?php echo $sender_id; ?>" data-parent="#messagesAccordion">
                                    <div class="card-body">
                                        <div class="message-container">
                                            <?php foreach ($data['messages'] as $msg): ?>
                                                <div class="message-item <?php echo ($msg['is_read'] == 0) ? 'font-weight-bold' : ''; ?>">
                                                    <div class="message-header">
                                                        <span class="message-date"><?php echo date('M d, Y H:i', strtotime($msg['created_at'])); ?></span>
                                                    </div>
                                                    <div class="message-body">
                                                        <?php echo htmlspecialchars($msg['message']); ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                        
                                        <!-- Mark all messages as read -->
                                        <form method="POST" action="mark_messages_read.php" class="mt-3">
                                            <input type="hidden" name="sender_id" value="<?php echo $sender_id; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-secondary">Mark All as Read</button>
                                        </form>
                                        
                                        <!-- Reply form -->
                                        <form method="POST" action="send_message.php" class="mt-3">
                                            <input type="hidden" name="receiver_id" value="<?php echo $sender_id; ?>">
                                            <div class="form-group">
                                                <textarea name="message" class="form-control" rows="3" placeholder="Type your reply..." required></textarea>
                                            </div>
                                            <button type="submit" class="btn btn-primary">Send Reply</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    
        <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
        <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
        <script>
            $(document).ready(function() {
                // Show the dashboard section by default
                $('#dashboard').show();
                
                // Handle navigation click
                $('.nav-link').click(function(e) {
                    e.preventDefault();
                    
                    // Hide all content sections
                    $('.content-section').hide();
                    
                    // Show the selected section
                    $($(this).data('target')).show();
                    
                    // Update active nav item
                    $('.nav-link').removeClass('active');
                    $(this).addClass('active');
                });
                
                // Set min date for project dates to today
                var today = new Date().toISOString().split('T')[0];
                $('#start_date').attr('min', today);
                $('#end_date').attr('min', today);
                
                // Ensure end date is not before start date
                $('#start_date').on('change', function() {
                    $('#end_date').attr('min', $(this).val());
                });
            });
        </script>
    </body>
    </html>