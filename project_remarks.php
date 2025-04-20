<?php
session_start();
include 'database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'supervisor') {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['project_id']) && isset($_POST['remarks'])) {
    $project_id = $_POST['project_id'];
    $remarks = $_POST['remarks'];

    // Insert remarks into the database
    $stmt = $pdo->prepare("INSERT INTO project_remarks (project_id, remarks) VALUES (?, ?)");
    $stmt->execute([$project_id, $remarks]);

    header("Location: supervisor.php");
    exit();
}