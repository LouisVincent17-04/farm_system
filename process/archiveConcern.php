<?php
session_start();
header('Content-Type: application/json');
require_once '../config/Connection.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("Invalid request.");
    
    $id = $_POST['id'] ?? 0;
    if (!$id) throw new Exception("ID missing.");

    $stmt = $conn->prepare("UPDATE concerns SET STATUS = 'Archived' WHERE CONCERN_ID = ?");
    $stmt->execute([$id]);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>