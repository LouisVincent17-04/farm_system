<?php
session_start();
error_reporting(0);
include '../config/Connection.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['role_name']);
    $desc = trim($_POST['description']);

    if (empty($name)) {
        echo json_encode(['success' => false, 'message' => 'Role Name is required.']);
        exit;
    }

    try {
        $stmt = $conn->prepare("INSERT INTO farm_roles (ROLE_NAME, DESCRIPTION) VALUES (?, ?)");
        $stmt->execute([$name, $desc]);

        $user_id = $_SESSION['user']['USER_ID'] ?? 0;
        $username = $_SESSION['user']['FULL_NAME'] ?? 'System';
        $log_sql = "INSERT INTO audit_logs (USER_ID, USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS) VALUES (?, ?, 'ADD_ROLE', 'FARM_ROLES', ?, ?)";
        $conn->prepare($log_sql)->execute([$user_id, $username, "Added role: $name", $_SERVER['REMOTE_ADDR']]);

        echo json_encode(['success' => true, 'message' => 'Role added successfully.']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}
?>