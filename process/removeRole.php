<?php
session_start();
error_reporting(0);
include '../config/Connection.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['role_id'];

    if (empty($id)) {
        echo json_encode(['success' => false, 'message' => 'Invalid Role ID.']);
        exit;
    }

    try {
        $stmt = $conn->prepare("DELETE FROM farm_roles WHERE ROLE_ID = ?");
        $stmt->execute([$id]);

        $user_id = $_SESSION['user']['USER_ID'] ?? 0;
        $username = $_SESSION['user']['FULL_NAME'] ?? 'System';
        $log_sql = "INSERT INTO audit_logs (USER_ID, USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS) VALUES (?, ?, 'DELETE_ROLE', 'FARM_ROLES', ?, ?)";
        $conn->prepare($log_sql)->execute([$user_id, $username, "Deleted role ID: $id", $_SERVER['REMOTE_ADDR']]);

        echo json_encode(['success' => true, 'message' => 'Role removed.']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to remove role. It may be assigned to active employees.']);
    }
}
?>