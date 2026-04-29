<?php
session_start();
header('Content-Type: application/json');
require_once '../config/Connection.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("Invalid request.");

    $user_id = $_SESSION['user']['USER_ID'] ?? 1;
    $category = $_POST['category'] ?? 'Other';
    $priority = $_POST['priority'] ?? 'Medium';
    $subject = trim($_POST['subject'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if (empty($subject) || empty($description)) throw new Exception("Subject and Description are required.");

    $stmt = $conn->prepare("INSERT INTO concerns (USER_ID, CATEGORY, PRIORITY, SUBJECT, DESCRIPTION) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, $category, $priority, $subject, $description]);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>