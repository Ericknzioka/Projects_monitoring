<?php
session_start();
include 'database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'supervisor') {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['student_id'])) {
    $student_id = $_POST['student_id'];

    // Unarchive the student
    $stmt = $pdo->prepare("UPDATE users SET archived = 0 WHERE id = ?");
    $stmt->execute([$student_id]);

    header("Location: supervisor.php");
    exit();
}