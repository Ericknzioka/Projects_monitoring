<?php
session_start();

if (!isset($_SESSION['report_data']) || !isset($_SESSION['report_type'])) {
    echo "No report data available.";
    exit();
}

$reportData = $_SESSION['report_data'];
$reportType = $_SESSION['report_type'];

// Generate CSV
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $reportType . '_report.csv"');

$output = fopen('php://output', 'w');

// Output the report data
foreach ($reportData as $row) {
    fputcsv($output, $row);
}

fclose($output);

// Clear the session data
unset($_SESSION['report_data']);
unset($_SESSION['report_type']);
exit();