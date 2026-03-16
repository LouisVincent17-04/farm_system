<?php
session_start();
error_reporting(0);
include '../config/Connection.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['role_id'];
    $name = trim($_POST['role_name']);
    $desc = trim($_POST['description']);

    if (empty($id) || empty($name)) {
        echo json_encode(['success' => false, 'message' => 'Role ID and Name are required.']);
        exit;
    }

    try {
        $stmt = $conn->prepare("UPDATE farm_roles SET ROLE_NAME = ?, DESCRIPTION = ? WHERE ROLE_ID = ?");
        $stmt->execute([$name, $desc, $id]);

        $user_id = $_SESSION['user']['USER_ID'] ?? 0;
        $username = $_SESSION['user']['FULL_NAME'] ?? 'System';
        $log_sql = "INSERT INTO audit_logs (USER_ID, USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS) VALUES (?, ?, 'EDIT_ROLE', 'FARM_ROLES', ?, ?)";
        $conn->prepare($log_sql)->execute([$user_id, $username, "Updated role ID $id to $name", $_SERVER['REMOTE_ADDR']]);

        echo json_encode(['success' => true, 'message' => 'Role updated.']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}
?>