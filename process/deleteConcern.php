<?php
session_start();
header('Content-Type: application/json');
require_once '../config/Connection.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("Invalid request.");
    
    $id = $_POST['id'] ?? 0;
    if (!$id) throw new Exception("ID missing.");

    // SECURITY CHECK: Verify the current status in the database before proceeding
    $stmt_check = $conn->prepare("SELECT STATUS FROM concerns WHERE CONCERN_ID = ?");
    $stmt_check->execute([$id]);
    $current_status = $stmt_check->fetchColumn();

    if ($current_status !== 'Pending') {
        throw new Exception("Security Error: This concern has already been reviewed (marked as Read/Archived) and can no longer be deleted.");
    }

    // Proceed with Deletion
    $stmt = $conn->prepare("DELETE FROM concerns WHERE CONCERN_ID = ?");
    $stmt->execute([$id]);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>