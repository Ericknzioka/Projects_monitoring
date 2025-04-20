<?php
session_start();
include 'database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Handle user approval/rejection
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action_type']) && $_POST['action_type'] == 'user_approval') {
    $user_id = (int)$_POST['user_id'];
    $action = $_POST['action'];
    
    if ($action === 'approve') {
        // Update BOTH columns to keep them in sync
        $stmt = $pdo->prepare("UPDATE users SET approved = 1, approval_status = 'approved' WHERE id = ?");
        if ($stmt->execute([$user_id])) {
            $message = "User approved successfully!";
            error_log("Approved user $user_id");
        } else {
            $error = "Error: " . implode(" ", $stmt->errorInfo());
        }
    }
    elseif ($action === 'reject') {
        $reason = $_POST['rejection_reason'];
        $stmt = $pdo->prepare("UPDATE users SET approved = 2, approval_status = 'denied', rejection_reason = ? WHERE id = ?");
        $stmt->execute([$reason, $user_id]);
    }
}

// Handle role updates
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action_type']) && $_POST['action_type'] == 'role_update') {
    $user_id = $_POST['user_id'];
    $new_role = $_POST['role'];

    // Update user role in the database
    $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
    if ($stmt->execute([$new_role, $user_id])) {
        $message = "User role updated successfully.";
    } else {
        $error = "Failed to update user role.";
    }
}

// Handle user archiving
if (isset($_GET['delete_id'])) {
    $delete_id = $_GET['delete_id'];

    // Archive user instead of deleting
    $stmt = $pdo->prepare("UPDATE users SET archived = 1 WHERE id = ?");
    if ($stmt->execute([$delete_id])) {
        $message = "User archived successfully.";
    } else {
        $error = "Failed to archive user.";
    }
}

// Handle user unarchiving
if (isset($_GET['unarchive_id'])) {
    $unarchive_id = $_GET['unarchive_id'];

    // Unarchive user
    $stmt = $pdo->prepare("UPDATE users SET archived = 0 WHERE id = ?");
    if ($stmt->execute([$unarchive_id])) {
        $message = "User unarchived successfully.";
    } else {
        $error = "Failed to unarchive user.";
    }
}

// Fetch pending users (approved = 0)
$pending_users = $pdo->query("SELECT * FROM users WHERE approved = 0")->fetchAll();

// Fetch archived users
$archived_users = $pdo->query("SELECT * FROM users WHERE archived = 1")->fetchAll();

// Fetch all users
$users = $pdo->query("SELECT * FROM users")->fetchAll();

// Fetch all projects
$projects = $pdo->query("SELECT p.*, u.first_name, u.last_name FROM projects p JOIN users u ON p.user_id = u.id")->fetchAll();

// Handle project approval/rejection
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action_type']) && $_POST['action_type'] == 'project_action') {
    $project_id = $_POST['project_id'];
    $action = $_POST['action'];

    if ($action === 'approve') {
        $stmt = $pdo->prepare("UPDATE projects SET status = 'approved' WHERE id = ?");
        $stmt->execute([$project_id]);
        $message = "Project approved successfully.";
    } elseif ($action === 'reject') {
        $stmt = $pdo->prepare("UPDATE projects SET status = 'rejected' WHERE id = ?");
        $stmt->execute([$project_id]);
        $message = "Project rejected successfully.";
    } elseif ($action === 'delete') {
        $stmt = $pdo->prepare("DELETE FROM projects WHERE id = ?");
        $stmt->execute([$project_id]);
        $message = "Project deleted successfully.";
    }
}

// Handle file sharing
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['file']) && isset($_POST['user_id_to_share'])) {
    $user_id_to_share = $_POST['user_id_to_share'];
    $file = $_FILES['file'];

    // Handle file upload logic here
    $target_dir = "uploads/";
    $target_file = $target_dir . basename($file["name"]);
    
    if (move_uploaded_file($file["tmp_name"], $target_file)) {
        $message = "File shared successfully with user ID: $user_id_to_share.";
    } else {
        $error = "Failed to share file.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content ="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <title>Admin Dashboard</title>
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
            overflow-y: auto;
            height: 100vh;
        }
        .report-form {
            display: flex;
            justify-content: flex-end; /* Aligns the form to the right */
            margin-bottom: 20px; /* Space below the form */
        }
        .report-form h2 {
            margin-right: 20px; /* Space between the title and the form */
        }
        .badge-pending {
            background-color: #ffc107;
            color: #212529;
        }
        .badge-approved {
            background-color: #28a745;
        }
        .badge-rejected {
            background-color: #dc3545;
        }
        .modal-header {
            background-color: #343a40;
            color: white;
        }
    </style>
</head>
<body>
    <aside class="main-sidebar sidebar-dark-primary elevation-4">
        <div class="dropdown">
            <a href="./" class="brand-link text-center">
                <h3 class="p-0 m-0"><b>ADMIN</b></h3>
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
                        <a href="#pending-approvals" class="nav-link nav-pending-approvals">
                            <i class="nav-icon fas fa-user-check"></i>
                            <p>Pending Approvals 
                                <?php if (count($pending_users) > 0): ?>
                                <span class="badge badge-warning"><?php echo count($pending_users); ?></span>
                                <?php endif; ?>
                            </p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#archived-users" class="nav-link nav-archived-users">
                            <i class="nav-icon fas fa-archive"></i>
                            <p>Archived Users</p>
                        </a>
                    </li>
                   <li class="nav-item">
                        <a href="#manage-users" class="nav-link nav-manage-users">
                            <i class="nav-icon fas fa-users"></i>
                            <p>Manage Users</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#manage-projects" class="nav-link nav-manage-projects">
                            <i class="nav-icon fas fa-briefcase"></i>
                            <p>Manage Projects</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#share-files" class="nav-link nav-share-files">
                            <i class="nav-icon fas fa-share-alt"></i>
                            <p>Share Files</p>
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
        <header class="text-center mb-4">
            <h1>Admin Dashboard</h1>
        </header>
        <?php if (isset($message)): ?>
            <div class="alert alert-success">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <div class="report-form">
            <h2 id="generate-report">Generate Report</h2>
            <form method="POST" action="generate_report.php" class="form-inline mb-4">
                <div class="form-group mr-2">
                    <label for="report_type" class="mr-2">Select Report Type</label>
                    <select name="report_type" id="report_type" class="form-control" required>
                        <option value="user_roles">User  Roles</option>
                        <option value="projects">Projects</option>
                        <option value="student_approvals">Student Approvals</option>
                        <option value="archived_students">Archived Students</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Generate Report</button>
            </form>
        </div>

        <h2 id="pending-approvals">Pending User Approvals</h2>
        <?php if (count($pending_users) > 0): ?>
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Registration Number</th>
                    <th>Program</th>
                    <th>Date Registered</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pending_users as $user): ?>
                <tr>
                    <td><?php echo htmlspecialchars($user['id']); ?></td>
                    <td><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></td>
                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                    <td><?php echo htmlspecialchars($user['role']); ?></td>
                    <td><?php echo htmlspecialchars($user['reg_no'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($user['programme_of_study'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars(isset($user['created_at']) ? date('M d, Y h:i A', strtotime($user['created_at'])) : 'N/A'); ?></td>
                    <td>
                        <form method="POST" action="">
                            <input type="hidden" name="action_type" value="user_approval">
                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                            <button type="submit" name="action" value="approve" class="btn btn-success btn-sm">Approve</button>
                            <button type="button" class="btn btn-danger btn-sm" data-toggle="modal" data-target="#rejectModal<?php echo $user['id']; ?>">
                                Reject
                            </button>
                        </form>
                    </td>
                </tr>
                
                <div class="modal fade" id="rejectModal<?php echo $user['id']; ?>" tabindex="-1" role="dialog" aria-labelledby="rejectModalLabel<?php echo $user['id']; ?>" aria-hidden="true">
                    <div class="modal-dialog" role="document">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="rejectModalLabel<?php echo $user['id']; ?>">Reject User: <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h5>
                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                            <form method="POST" action="">
                                <div class="modal-body">
                                    <div class="form-group">
                                        <label for="rejection_reason">Reason for Rejection:</label>
                                        <textarea class="form-control" id="rejection_reason" name="rejection_reason" rows="4" required></textarea>
                                    </div>
                                    <input type="hidden" name="action_type" value="user_approval">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <input type="hidden" name="action" value="reject">
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-danger">Confirm Rejection</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="alert alert-info">
            No pending user approvals at this time.
        </div>
        <?php endif; ?>

        <h2 id="archived-users">Archived Users</h2>
        <?php if (count($archived_users) > 0): ?>
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Rejection Reason</th>
                    <th>Date Archived</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($archived_users as $user): ?>
                <tr>
                    <td><?php echo htmlspecialchars($user['id']); ?></td>
                    <td><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></td>
                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                    <td><?php echo htmlspecialchars($user['role']); ?></td>
                    <td><?php echo htmlspecialchars($user['rejection_reason'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars(isset($user['updated_at']) ? date('M d, Y h:i A', strtotime($user['updated_at'])) : 'N/A'); ?></td>
                    <td>
                        <a href="?unarchive_id=<?php echo $user['id']; ?>" class="btn btn-success btn-sm" onclick="return confirm('Are you sure you want to unarchive this user?');">Unarchive</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="alert alert-info">
            No archived users at this time.
        </div>
        <?php endif; ?>

        <h2 id="manage-users">Manage Users</h2>
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                <tr>
                    <td><?php echo htmlspecialchars($user['id']); ?></td>
                    <td><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></td>
                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                    <td>
                        <form method="POST" action="">
                            <div class="input-group">
                                <select name="role" class="form-control form-control-sm">
                                    <option value="admin" <?php echo $user['role'] == 'admin' ? 'selected' : ''; ?>>Admin</option>
                                    <option value="supervisor" <?php echo $user['role'] == 'supervisor' ? 'selected' : ''; ?>>Supervisor</option>
                                    <option value="student" <?php echo $user['role'] == 'student' ? 'selected' : ''; ?>>Student</option>
                                </select>
                                <div class="input-group-append">
                                    <input type="hidden" name="action_type" value="role_update">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <button type="submit" class="btn btn-primary btn-sm">Update</button>
                                </div>
                            </div>
                        </form>
                    </td>
                    <td>
                        <?php 
                        if ($user['approved'] == 0) {
                            echo '<span class="badge badge-pending">Pending</span>';
                        } elseif ($user['approved'] == 1) {
                            echo '<span class="badge badge-approved">Approved</span>';
                        } elseif ($user['approved'] == 2) {
                            echo '<span class="badge badge-rejected">Rejected</span>';
                            if (isset($user['rejection_reason']) && !empty($user['rejection_reason'])) {
                                echo ' <button class="btn btn-sm btn-link" data-toggle="modal" data-target="#reasonModal'.$user['id'].'">View Reason</button>';
                            }
                        }
                        ?>
                    </td>
                    <td>
                        <div class="btn-group" role="group">
                            <?php if ($user['approved'] != 1): ?>
                            <form method="POST" action="" class="d-inline">
                                <input type="hidden" name="action_type" value="user_approval">
                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                <button type="submit" name="action" value="approve" class="btn btn-success btn-sm">Approve</button>
                            </form>
                            <?php endif; ?>
                            
                            <?php if ($user['archived'] == 0): ?>
                            <a href="?delete_id=<?php echo $user['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to archive this user?');">Archive</a>
                            <?php else: ?>
                            <a href="?unarchive_id=<?php echo $user['id']; ?>" class="btn btn-success btn-sm" onclick="return confirm('Are you sure you want to unarchive this user?');">Unarchive</a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                
                <!-- Rejection Reason Modal -->
                <?php if (isset($user['rejection_reason']) && !empty($user['rejection_reason'])): ?>
                <div class="modal fade" id="reasonModal<?php echo $user['id']; ?>" tabindex="-1" role="dialog" aria-labelledby="reasonModalLabel<?php echo $user['id']; ?>" aria-hidden="true">
                    <div class="modal-dialog" role="document">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="reasonModalLabel<?php echo $user['id']; ?>">Rejection Reason</h5>
                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                            <div class="modal-body">
                                <p><?php echo nl2br(htmlspecialchars($user['rejection_reason'])); ?></p>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                <?php endforeach; ?>
            </tbody>
        </table>

        <h2 id="manage-projects">Manage Projects</h2>
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Project ID</th>
                    <th>Title</th>
                    <th>Student Name</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($projects as $project): ?>
                <tr>
                    <td><?php echo htmlspecialchars($project['id']); ?></td>
                    <td><?php echo htmlspecialchars($project['title']); ?></td>
                    <td><?php echo htmlspecialchars($project['first_name'] . ' ' . $project['last_name']); ?></td>
                    <td><?php echo htmlspecialchars($project['status']); ?></td>
                    <td>
                        <form method="POST" action="">
                            <input type="hidden" name="action_type" value="project_action">
                            <input type="hidden" name="project_id" value="<?php echo $project['id']; ?>">
                            <button type="submit" name="action" value="approve" class="btn btn-success btn-sm">Approve</button>
                            <button type="submit" name="action" value="reject" class="btn btn-danger btn-sm">Reject</button>
                            <button type="submit" name="action" value="delete" class="btn btn-warning btn-sm">Delete</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <h2 id="share-files">Share Files with Users</h2>
        <form method="POST" action="" enctype="multipart/form-data">
            <div class="form-group">
                <label for="file">Select File</label>
                <input type="file" name="file" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="user_id_to_share">Select User</label>
                <select name="user_id_to_share" class="form-control" required>
                    <?php foreach ($users as $user): ?>
                        <option value="<?php echo htmlspecialchars($user['id']); ?>"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Share File</button>
        </form>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.2/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                document.querySelector(this.getAttribute('href')).scrollIntoView({
                    behavior: 'smooth'
                });
            });
        });
    </script>
</body>
</html>