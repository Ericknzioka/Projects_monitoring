APPENDIX 
<?php
session_start();
include 'database.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Store success or error messages in session to persist through redirects
if (!isset($_SESSION['messages'])) {
    $_SESSION['messages'] = [];
}

// Handle user approval/rejection
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action_type']) && $_POST['action_type'] == 'user_approval') {
    $user_id = (int)$_POST['user_id'];
    $action = $_POST['action'];
    
    if ($action === 'approve') {
        // Update BOTH columns to keep them in sync
        $stmt = $pdo->prepare("UPDATE users SET approved = 1, approval_status = 'approved' WHERE id = ?");
        if ($stmt->execute([$user_id])) {
            $_SESSION['messages']['success'] = "User approved successfully!";
            error_log("Approved user $user_id");
        } else {
            $_SESSION['messages']['error'] = "Error: " . implode(" ", $stmt->errorInfo());
        }
    }
    elseif ($action === 'reject') {
        $reason = $_POST['rejection_reason'];
        $stmt = $pdo->prepare("UPDATE users SET approved = 2, approval_status = 'denied', rejection_reason = ? WHERE id = ?");
        if ($stmt->execute([$reason, $user_id])) {
            $_SESSION['messages']['success'] = "User rejected successfully.";
        } else {
            $_SESSION['messages']['error'] = "Failed to reject user.";
        }
    }
    // Redirect to self to prevent form resubmission
    header("Location: " . $_SERVER['PHP_SELF'] . "#pending-approvals");
    exit();
}

// Handle role updates
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action_type']) && $_POST['action_type'] == 'role_update') {
    $user_id = $_POST['user_id'];
    $new_role = $_POST['role'];

    // Update user role in the database
    $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
    if ($stmt->execute([$new_role, $user_id])) {
        $_SESSION['messages']['success'] = "User role updated successfully.";
    } else {
        $_SESSION['messages']['error'] = "Failed to update user role.";
    }
    // Redirect to self to prevent form resubmission
    header("Location: " . $_SERVER['PHP_SELF'] . "#manage-users");
    exit();
}

// Handle user archiving
if (isset($_GET['delete_id'])) {
    $delete_id = $_GET['delete_id'];

    // Archive user instead of deleting
    $stmt = $pdo->prepare("UPDATE users SET archived = 1 WHERE id = ?");
    if ($stmt->execute([$delete_id])) {
        $_SESSION['messages']['success'] = "User archived successfully.";
    } else {
        $_SESSION['messages']['error'] = "Failed to archive user.";
    }
    // Redirect to self to prevent refresh issues
    header("Location: " . $_SERVER['PHP_SELF'] . "#manage-users");
    exit();
}

// Handle user unarchiving
if (isset($_GET['unarchive_id'])) {
    $unarchive_id = $_GET['unarchive_id'];

    // Unarchive user
    $stmt = $pdo->prepare("UPDATE users SET archived = 0 WHERE id = ?");
    if ($stmt->execute([$unarchive_id])) {
        $_SESSION['messages']['success'] = "User unarchived successfully.";
    } else {
        $_SESSION['messages']['error'] = "Failed to unarchive user.";
    }
    // Redirect to self to prevent refresh issues
    header("Location: " . $_SERVER['PHP_SELF'] . "#archived-users");
    exit();
}

// Handle project approval/rejection
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action_type']) && $_POST['action_type'] == 'project_action') {
    $project_id = $_POST['project_id'];
    $action = $_POST['action'];

    if ($action === 'approve') {
        $stmt = $pdo->prepare("UPDATE projects SET status = 'approved' WHERE id = ?");
        $stmt->execute([$project_id]);
        $_SESSION['messages']['success'] = "Project approved successfully.";
    } elseif ($action === 'reject') {
        $stmt = $pdo->prepare("UPDATE projects SET status = 'rejected' WHERE id = ?");
        $stmt->execute([$project_id]);
        $_SESSION['messages']['success'] = "Project rejected successfully.";
    } elseif ($action === 'delete') {
        $stmt = $pdo->prepare("DELETE FROM projects WHERE id = ?");
        $stmt->execute([$project_id]);
        $_SESSION['messages']['success'] = "Project deleted successfully.";
    }
    // Redirect to self to prevent form resubmission
    header("Location: " . $_SERVER['PHP_SELF'] . "#manage-projects");
    exit();
}

// Handle file sharing
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['file']) && isset($_POST['user_id_to_share'])) {
    $user_id_to_share = $_POST['user_id_to_share'];
    $file = $_FILES['file'];

    // Handle file upload logic here
    $target_dir = "uploads/";
    
    // Create directory if it doesn't exist
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    
    $target_file = $target_dir . basename($file["name"]);
    
    if (move_uploaded_file($file["tmp_name"], $target_file)) {
        $_SESSION['messages']['success'] = "File shared successfully with user ID: $user_id_to_share.";
    } else {
        $_SESSION['messages']['error'] = "Failed to share file.";
    }
    // Redirect to self to prevent form resubmission
    header("Location: " . $_SERVER['PHP_SELF'] . "#share-files");
    exit();
}

// Fetch all data after processing forms
// Fetch pending users (approved = 0)
$pending_users = $pdo->query("SELECT * FROM users WHERE approved = 0")->fetchAll();

// Fetch archived users
$archived_users = $pdo->query("SELECT * FROM users WHERE archived = 1")->fetchAll();

// Fetch all users
$users = $pdo->query("SELECT * FROM users WHERE archived = 0 ORDER BY id DESC")->fetchAll();

// Fetch all projects
$projects = $pdo->query("SELECT p.*, u.first_name, u.last_name FROM projects p JOIN users u ON p.user_id = u.id ORDER BY p.id DESC")->fetchAll();

// Extract any messages from session and clear them
$message = isset($_SESSION['messages']['success']) ? $_SESSION['messages']['success'] : null;
$error = isset($_SESSION['messages']['error']) ? $_SESSION['messages']['error'] : null;
unset($_SESSION['messages']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <title>Admin Dashboard</title>
    <style>
        body {
            display: flex;
            height: 100vh;
            overflow: hidden;
        }
        .main-sidebar {
            width: 250px;
            background-color: #343a40;
            color: white;
            height: 100vh;
            position: fixed;
            left: 0;
            transition: all 0.3s;
            z-index: 1000;
        }
        .main-sidebar.collapsed {
            width: 60px;
        }
        .main-sidebar .nav-link {
            color: white;
            padding: 10px 15px;
            display: flex;
            align-items: center;
        }
        .main-sidebar .nav-link i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }
        .main-sidebar .nav-link .menu-text {
            transition: opacity 0.3s;
        }
        .main-sidebar.collapsed .nav-link .menu-text {
            opacity: 0;
            display: none;
        }
        .main-sidebar .nav-link:hover {
            background-color: #495057;
        }
        .content {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
            height: 100vh;
            margin-left: 250px;
            transition: all 0.3s;
        }
        .content.expanded {
            margin-left: 60px;
        }
        .toggle-sidebar {
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            position: absolute;
            top: 10px;
            right: 10px;
            cursor: pointer;
        }
        .section {
            display: none;
            padding: 20px 0;
        }
        .section.active {
            display: block;
        }
        .report-form {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 20px;
            align-items: center;
        }
        .report-form h2 {
            margin-right: 20px;
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
        .dashboard-widgets {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 30px;
        }
        .widget {
            background-color: #f8f9fa;
            border-radius: 5px;
            padding: 20px;
            flex: 1 0 250px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            text-align: center;
        }
        .widget-count {
            font-size: 2.5rem;
            font-weight: bold;
            margin: 10px 0;
        }
        .widget-title {
            color: #6c757d;
            font-size: 1.1rem;
        }
        .widget i {
            font-size: 2rem;
            color: #343a40;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <aside class="main-sidebar" id="sidebar">
        <div class="text-center p-3">
            <h3 class="p-0 m-0"><b>ADMIN</b></h3>
            <button class="toggle-sidebar" id="toggle-sidebar">
                <i class="fas fa-bars"></i>
            </button>
        </div>
        <div class="sidebar pb-4 mb-4">
            <nav class="mt-2">
                <ul class="nav nav-pills nav-sidebar flex-column nav-flat" data-widget="treeview" role="menu" data-accordion="false">
                    <li class="nav-item">
                        <a href="#dashboard" class="nav-link nav-dashboard section-link" data-section="dashboard-section">
                            <i class="nav-icon fas fa-tachometer-alt"></i>
                            <span class="menu-text">Dashboard</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#pending-approvals" class="nav-link nav-pending-approvals section-link" data-section="pending-approvals-section">
                            <i class="nav-icon fas fa-user-check"></i>
                            <span class="menu-text">Pending Approvals 
                                <?php if (count($pending_users) > 0): ?>
                                <span class="badge badge-warning"><?php echo count($pending_users); ?></span>
                                <?php endif; ?>
                            </span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#archived-users" class="nav-link nav-archived-users section-link" data-section="archived-users-section">
                            <i class="nav-icon fas fa-archive"></i>
                            <span class="menu-text">Archived Users</span>
                        </a>
                    </li>
                   <li class="nav-item">
                        <a href="#manage-users" class="nav-link nav-manage-users section-link" data-section="manage-users-section">
                            <i class="nav-icon fas fa-users"></i>
                            <span class="menu-text">Manage Users</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#manage-projects" class="nav-link nav-manage-projects section-link" data-section="manage-projects-section">
                            <i class="nav-icon fas fa-briefcase"></i>
                            <span class="menu-text">Manage Projects</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#share-files" class="nav-link nav-share-files section-link" data-section="share-files-section">
                            <i class="nav-icon fas fa-share-alt"></i>
                            <span class="menu-text">Share Files</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#reports" class="nav-link nav-reports section-link" data-section="reports-section">
                            <i class="nav-icon fas fa-chart-bar"></i>
                            <span class="menu-text">Reports</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="logout.php" class="nav-link nav-logout">
                            <i class="nav-icon fas fa-sign-out-alt"></i>
                            <span class="menu-text">Logout</span>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
    </aside>

    <div class="content" id="content">
        <header class="text-center mb-4">
            <h1>Admin Dashboard</h1>
        </header>
        <?php if (isset($message)): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?php echo $message; ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?php echo $error; ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        <?php endif; ?>

        <!-- Dashboard Section -->
        <section id="dashboard-section" class="section active">
            <h2>Dashboard Overview</h2>
            <div class="dashboard-widgets">
                <div class="widget">
                    <i class="fas fa-users"></i>
                    <div class="widget-count"><?php echo count($users); ?></div>
                    <div class="widget-title">Active Users</div>
                </div>
                <div class="widget">
                    <i class="fas fa-user-check"></i>
                    <div class="widget-count"><?php echo count($pending_users); ?></div>
                    <div class="widget-title">Pending Approvals</div>
                </div>
                <div class="widget">
                    <i class="fas fa-briefcase"></i>
                    <div class="widget-count"><?php echo count($projects); ?></div>
                    <div class="widget-title">Total Projects</div>
                </div>
                <div class="widget">
                    <i class="fas fa-archive"></i>
                    <div class="widget-count"><?php echo count($archived_users); ?></div>
                    <div class="widget-title">Archived Users</div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">Recent Pending Approvals</h5>
                        </div>
                        <div class="card-body">
                            <?php if (count($pending_users) > 0): ?>
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Role</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach (array_slice($pending_users, 0, 5) as $user): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></td>
                                            <td><?php echo htmlspecialchars($user['role']); ?></td>
                                            <td>
                                                <a href="#pending-approvals" class="btn btn-sm btn-primary section-link" data-section="pending-approvals-section">View</a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <p class="text-center">No pending approvals</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0">Recent Projects</h5>
                        </div>
                        <div class="card-body">
                            <?php if (count($projects) > 0): ?>
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Title</th>
                                            <th>Student</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach (array_slice($projects, 0, 5) as $project): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($project['title']); ?></td>
                                            <td><?php echo htmlspecialchars($project['first_name'] . ' ' . $project['last_name']); ?></td>
                                            <td><?php echo htmlspecialchars($project['status']); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <p class="text-center">No projects available</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Pending Approvals Section -->
        <section id="pending-approvals-section" class="section">
            <h2>Pending User Approvals</h2>
            <?php if (count($pending_users) > 0): ?>
            <div class="table-responsive">
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
            </div>
            <?php else: ?>
            <div class="alert alert-info">
                No pending user approvals at this time.
            </div>
            <?php endif; ?>
        </section>

        <!-- Archived Users Section -->
        <section id="archived-users-section" class="section">
            <h2>Archived Users</h2>
            <?php if (count($archived_users) > 0): ?>
            <div class="table-responsive">
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
            </div>
            <?php else: ?>
            <div class="alert alert-info">
                No archived users at this time.
            </div>
            <?php endif; ?>
        </section>

      <!-- Manage Users Section -->
      <section id="manage-users-section" class="section">
            <h2>Manage Users</h2>
            
            <!-- Search form for users -->
            <div class="card mb-3">
                <div class="card-body">
                    <form method="GET" action="" class="form-inline">
                        <div class="input-group w-100 mb-2">
                            <input type="text" name="user_search" class="form-control" placeholder="Search by name or email" 
                                value="<?php echo isset($_GET['user_search']) ? htmlspecialchars($_GET['user_search']) : ''; ?>">
                            <div class="input-group-append">
                                <button type="submit" class="btn btn-primary">Search</button>
                                <?php if(isset($_GET['user_search']) && !empty($_GET['user_search'])): ?>
                                    <a href="?<?php echo http_build_query(array_diff_key($_GET, ['user_search' => ''])); ?>" class="btn btn-secondary">Clear</a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="form-group mr-2">
                            <select name="role_filter" class="form-control">
                                <option value="">All Roles</option>
                                <option value="admin" <?php echo (isset($_GET['role_filter']) && $_GET['role_filter'] == 'admin') ? 'selected' : ''; ?>>Admin</option>
                                <option value="supervisor" <?php echo (isset($_GET['role_filter']) && $_GET['role_filter'] == 'supervisor') ? 'selected' : ''; ?>>Supervisor</option>
                                <option value="student" <?php echo (isset($_GET['role_filter']) && $_GET['role_filter'] == 'student') ? 'selected' : ''; ?>>Student</option>
                            </select>
                        </div>
                        <div class="form-group mr-2">
                            <select name="status_filter" class="form-control">
                                <option value="">All Statuses</option>
                                <option value="0" <?php echo (isset($_GET['status_filter']) && $_GET['status_filter'] === '0') ? 'selected' : ''; ?>>Pending</option>
                                <option value="1" <?php echo (isset($_GET['status_filter']) && $_GET['status_filter'] === '1') ? 'selected' : ''; ?>>Approved</option>
                                <option value="2" <?php echo (isset($_GET['status_filter']) && $_GET['status_filter'] === '2') ? 'selected' : ''; ?>>Rejected</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-outline-primary">Apply Filters</button>
                    </form>
                </div>
            </div>
            
            <div class="table-responsive">
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
            </div>
            <?php if (count($users) == 0): ?>
            <div class="alert alert-info">
                No users found matching your search criteria.
            </div>
            <?php endif; ?>
        </section>

        <!-- Manage Projects Section -->
        <section id="manage-projects-section" class="section">
            <h2>Manage Projects</h2>
            
            <!-- Search form for projects -->
            <div class="card mb-3">
                <div class="card-body">
                    <form method="GET" action="" class="form-inline">
                        <div class="input-group w-100 mb-2">
                            <input type="text" name="project_search" class="form-control" placeholder="Search by title or student name" 
                                value="<?php echo isset($_GET['project_search']) ? htmlspecialchars($_GET['project_search']) : ''; ?>">
                            <div class="input-group-append">
                                <button type="submit" class="btn btn-primary">Search</button>
                                <?php if(isset($_GET['project_search']) && !empty($_GET['project_search'])): ?>
                                    <a href="?<?php echo http_build_query(array_diff_key($_GET, ['project_search' => ''])); ?>" class="btn btn-secondary">Clear</a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="form-group mr-2">
                            <select name="project_status" class="form-control">
                                <option value="">All Statuses</option>
                                <option value="pending" <?php echo (isset($_GET['project_status']) && $_GET['project_status'] == 'pending') ? 'selected' : ''; ?>>Pending</option>
                                <option value="approved" <?php echo (isset($_GET['project_status']) && $_GET['project_status'] == 'approved') ? 'selected' : ''; ?>>Approved</option>
                                <option value="rejected" <?php echo (isset($_GET['project_status']) && $_GET['project_status'] == 'rejected') ? 'selected' : ''; ?>>Rejected</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-outline-primary">Apply Filters</button>
                    </form>
                </div>
            </div>
            
            <div class="table-responsive">
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
                            <td>
                                <span class="badge badge-<?php 
                                    echo $project['status'] == 'approved' ? 'success' : 
                                        ($project['status'] == 'rejected' ? 'danger' : 
                                            ($project['status'] == 'pending' ? 'warning' : 'info')); 
                                ?>">
                                    <?php echo htmlspecialchars(ucfirst($project['status'])); ?>
                                </span>
                            </td>
                            <td>
                                <form method="POST" action="">
                                    <input type="hidden" name="action_type" value="project_action">
                                    <input type="hidden" name="project_id" value="<?php echo $project['id']; ?>">
                                    <div class="btn-group btn-group-sm">
                                        <button type="submit" name="action" value="approve" class="btn btn-success">Approve</button>
                                        <button type="submit" name="action" value="reject" class="btn btn-danger">Reject</button>
                                    </div>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if (count($projects) == 0): ?>
            <div class="alert alert-info">
                No projects found matching your search criteria.
            </div>
            <?php endif; ?>
        </section>

        <!-- Share Files Section -->
        <section id="share-files-section" class="section">
            <h2>Share Files with Users</h2>
            <div class="card">
                <div class="card-body">
                    <form method="POST" action="" enctype="multipart/form-data">
                        <div class="form-group">
                            <label for="file">Select File</label>
                            <input type="file" name="file" id="file" class="form-control-file" required>
                        </div>
                        <div class="form-group">
                            <label for="user_id_to_share">Select User</label>
                            <select name="user_id_to_share" id="user_id_to_share" class="form-control" required>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?php echo htmlspecialchars($user['id']); ?>">
                                        <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name'] . ' (' . $user['role'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary">Share File</button>
                    </form>
                </div>
            </div>
        </section>

        <!-- Reports Section -->
        <section id="reports-section" class="section">
            <h2>Generate Reports</h2>
            <div class="card">
                <div class="card-body">
                    <form method="POST" action="generate_report.php" class="mb-4">
                        <div class="form-group">
                            <label for="report_type">Select Report Type</label>
                            <select name="report_type" id="report_type" class="form-control" required>
                                <option value="user_roles">User Roles</option>
                                <option value="projects">Projects</option>
                                <option value="student_approvals">Student Approvals</option>
                                <option value="archived_students">Archived Students</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="report_format">Report Format</label>
                            <select name="report_format" id="report_format" class="form-control">
                                <option value="pdf">PDF</option>
                                <option value="excel">Excel</option>
                                <option value="csv">CSV</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary">Generate Report</button>
                    </form>
                </div>
            </div>
        </section>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.2/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        // Toggle sidebar functionality
        document.getElementById('toggle-sidebar').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('collapsed');
            document.getElementById('content').classList.toggle('expanded');
        });

        // Section navigation
        document.addEventListener('DOMContentLoaded', function() {
            // Get all section links
            const sectionLinks = document.querySelectorAll('.section-link');
            
            // Add click event to each link
            sectionLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    // Get the target section id
                    const targetSectionId = this.getAttribute('data-section');
                    
                    // Hide all sections
                    document.querySelectorAll('.section').forEach(section => {
                        section.classList.remove('active');
                    });
                    
                    // Show the target section
                    document.getElementById(targetSectionId).classList.add('active');
                    
                    // Update active nav link
                    sectionLinks.forEach(navLink => {
                        navLink.classList.remove('active');
                    });
                    this.classList.add('active');
                    
                    // If sidebar is collapsed on mobile, close it after navigation
                    if (window.innerWidth < 768) {
                        document.getElementById('sidebar').classList.add('collapsed');
                        document.getElementById('content').classList.add('expanded');
                    }
                });
            });
            
            // Check for hash in URL and navigate to that section
            if (window.location.hash) {
                const hash = window.location.hash.substring(1);
                const section = document.getElementById(hash + '-section');
                const link = document.querySelector(`.section-link[data-section="${hash}-section"]`);
                
                if (section && link) {
                    // Hide all sections
                    document.querySelectorAll('.section').forEach(s => {
                        s.classList.remove('active');
                    });
                    
                    // Show the target section
                    section.classList.add('active');
                    
                    // Update active nav link
                    document.querySelectorAll('.section-link').forEach(l => {
                        l.classList.remove('active');
                    });
                    link.classList.add('active');
                }
            }
        });

        // Auto-hide alerts after 5 seconds
        window.setTimeout(function() {
            $(".alert").fadeTo(500, 0).slideUp(500, function(){
                $(this).remove(); 
            });
        }, 5000);
        
        // Confirm before form submissions that change data
        document.querySelectorAll('form').forEach(form => {
            if (form.querySelector('input[name="action"]') && 
                (form.querySelector('input[name="action"]').value === 'reject' || 
                 form.querySelector('input[name="action"]').value === 'delete')) {
                form.addEventListener('submit', function(e) {
                    if (!confirm('Are you sure you want to perform this action?')) {
                        e.preventDefault();
                    }
                });
            }
        });
    </script>
</body>
</html>

