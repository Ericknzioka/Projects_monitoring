<?php
session_start();
include 'database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['report_type'])) {
    $reportType = $_POST['report_type'];
    $reportData = [];

    // Check user role to determine report type
    if ($_SESSION['role'] === 'admin') {
        switch ($reportType) {
            case 'user_roles':
                // Fetch user roles data
                $stmt = $pdo->query("SELECT * FROM users");
                $users = $stmt->fetchAll();

                // Store report data
                if ($users) {
                    $reportData[] = ['ID', 'Name', 'Email', 'Role']; // Header row
                    foreach ($users as $user) {
                        $reportData[] = [
                            $user['id'],
                            $user['first_name'] . ' ' . $user['last_name'],
                            $user['email'],
                            $user['role']
                        ];
                    }
                }
                break;

            case 'projects':
                // Fetch project data
                $stmt = $pdo->query("
                    SELECT p.*, u.first_name, u.last_name 
                    FROM projects p 
                    JOIN users u ON p.user_id = u.id
                ");
                $projects = $stmt->fetchAll();

                // Store report data
                if ($projects) {
                    $reportData[] = ['Project ID', 'Title', 'Status', 'Start Date', 'End Date', 'Student Name']; // Header row
                    foreach ($projects as $project) {
                        $reportData[] = [
                            $project['id'],
                            $project['title'],
                            $project['status'],
                            $project['start_date'],
                            $project['end_date'],
                            $project['first_name'] . ' ' . $project['last_name']
                        ];
                    }
                }
                break;

            case 'student_approvals':
                // Fetch student approval data
                $stmt = $pdo->query("SELECT * FROM users WHERE role = 'student' AND approved = 0");
                $students = $stmt->fetchAll();

                // Store report data
                if ($students) {
                    $reportData[] = ['ID', 'Name', 'Email']; // Header row
                    foreach ($students as $student) {
                        $reportData[] = [
                            $student['id'],
                            $student['first_name'] . ' ' . $student['last_name'],
                            $student['email']
                        ];
                    }
                }
                break;

            case 'archived_students':
                // Fetch archived student data
                $stmt = $pdo->query("SELECT * FROM users WHERE role = 'student' AND approved = 1 AND archived = 1");
                $archived_students = $stmt->fetchAll();

                // Store report data
                if ($archived_students) {
                    $reportData[] = ['ID', 'Name', 'Email']; // Header row
                    foreach ($archived_students as $archived_student) {
                        $reportData[] = [
                            $archived_student['id'],
                            $archived_student['first_name'] . ' ' . $archived_student['last_name'],
                            $archived_student['email']
                        ];
                    }
                }
                break;

            default:
                echo "Invalid report type.";
                exit();
        }
    } elseif ($_SESSION['role'] === 'student') {
        switch ($reportType) {
            case 'project_status':
                // Fetch project status data
                $stmt = $pdo->prepare("SELECT * FROM projects WHERE user_id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $projects = $stmt->fetchAll();

                // Store report data
                if ($projects) {
                    $reportData[] = ['ID', 'Title', 'Status', 'Start Date', 'End Date']; // Header row
                    foreach ($projects as $project) {
                        $reportData[] = $project;
                    }
                }
                break;

            // Other cases for student role...
        }
    } elseif ($_SESSION['role'] === 'supervisor') {
        switch ($reportType) {
            case 'projects':
                // Fetch project data
                $stmt = $pdo->prepare("
                    SELECT p.*, u.first_name, u.last_name 
                    FROM projects p 
                    JOIN users u ON p.user_id = u.id
                ");
                $stmt->execute();
                $projects = $stmt->fetchAll();

                // Store report data
                if ($projects) {
                    $reportData[] = ['Project ID', 'Title', 'Status', 'Start Date', 'End Date', 'Student Name']; foreach ($projects as $project) {
                        $reportData[] = [
                            $project['id'],
                            $project['title'],
                            $project['status'],
                            $project['start_date'],
                            $project['end_date'],
                            $project['first_name'] . ' ' . $project['last_name']
                        ];
                    }
                }
                break;

            // Other cases for supervisor role...
        }
    }

    // Check if reportData is populated
    if (empty($reportData)) {
        echo "<p>No data available for the selected report type.</p>";
        exit();
    }

    // Generate HTML output for printing
    ob_start(); // Start output buffering
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Report</title>
        <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    </head>
    <body>
        <div class="container">
            <h1 class="text-center">Report: <?php echo htmlspecialchars($reportType); ?></h1>
            <p class="text-center">Generated on: <?php echo date("F d, Y"); ?></p>
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <?php foreach ($reportData[0] as $header): ?>
                            <th><?php echo htmlspecialchars($header); ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reportData as $row): ?>
                        <tr>
                            <?php foreach ($row as $cell): ?>
                                <td><?php echo htmlspecialchars($cell); ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <script>
            window.onload = function() {
                window.print();
                window.onafterprint = function() {
                    window.close();
                };
            };
        </script>
    </body>
    </html>
    <?php
    $htmlOutput = ob_get_clean(); // Get the buffered output
    echo $htmlOutput; // Output the HTML for printing
    exit();
} else {
    echo "No report type selected.";
    exit();
}