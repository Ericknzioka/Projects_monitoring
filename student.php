<?php
// Start session with proper configuration
ini_set('session.cookie_lifetime', 86400); // 24 hours
ini_set('session.gc_maxlifetime', 86400); // 24 hours
session_start();
include 'database.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit();
}

// Rest of your code remains the same
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
    } else {
        $notification = "Project '$project_title' already exists.";
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
        $stmt->execute([$supervisor_id, $notification]);
    }
}

// Handle project status update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_project_id'])) {
    $update_project_id = $_POST['update_project_id'];
    $new_status = $_POST['new_status'];

    // Update the project status
    $stmt = $pdo->prepare("UPDATE projects SET status = ? WHERE id = ?");
    $stmt->execute([$new_status, $update_project_id]);

    // Fetch student information
    $stmt = $pdo->prepare("SELECT u.first_name, u.last_name, p.title FROM projects p JOIN users u ON p.user_id = u.id WHERE p.id = ?");
    $stmt->execute([$update_project_id]);
    $project_info = $stmt->fetch();

    // Send notification to supervisor
    $supervisor_id = 1; // Replace with actual supervisor ID
    $notification_message = "{$project_info['first_name']} {$project_info['last_name']} updated project '{$project_info['title']}' to status '$new_status' on " . date('Y-m-d H:i:s');
    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
    $stmt->execute([$supervisor_id, $notification_message]);
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
            <form method="POST" action="generate_report.php" class ="form-inline mb-3">
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
                    <?php foreach ($projects as $project): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($project['title']); ?></td>
                        <td><?php echo htmlspecialchars($project['status']); ?></td>
                        <td><?php echo htmlspecialchars($project['start_date']); ?></td>
                        <td><?php echo htmlspecialchars($project['end_date']); ?></td>
                        <td>
                            <form method="POST" action="">
                                <input type="hidden" name="update_project_id" value="<?php echo $project['id']; ?>">
                                <select name="new_status" class="form-control" required>
                                    <option value="ongoing">Ongoing</option>
                                    <option value="completed">Completed</option>
                                    <option value="on_hold">On Hold</option>
                                    <option value="done">Done</option>
                                </select>
                                <button type="submit" class="btn btn-warning btn-sm">Update</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <h3>Add New Project</h3>
            <div class="project-form">
                <form method="POST" action="">
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
            <form method="POST" enctype="multipart/form-data" class="mb-4">
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
                                    <a href="<?php echo htmlspecialchars($file['file_path']); ?>" class="btn btn-info btn-sm" download>Download</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <div id="supervisor-comments" class="content-section">
            <h2>Supervisor Comments</h2>
            <ul class="list-group">
                <?php foreach ($comments as $comment): ?>
                    <li class="list-group-item"><?php echo htmlspecialchars($comment['message']); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.2/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        $(document).ready(function() {
            // Store active tab in localStorage
            var activeTab = localStorage.getItem('activeTab') || '#dashboard';
            $(activeTab).show();
            
            // Mark the corresponding nav item as active
            $('.nav-link[data-target="' + activeTab + '"]').addClass('active');
            
            $('.nav-link').on('click', function(e) {
                e.preventDefault();
                var target = $(this).data('target');
                $('.content-section').hide();
                $(target).show();
                
                // Save the active tab
                localStorage.setItem('activeTab', target);
                
                // Update active class
                $('.nav-link').removeClass('active');
                $(this).addClass('active');
            });
        });
    </script>
</body>
</html>