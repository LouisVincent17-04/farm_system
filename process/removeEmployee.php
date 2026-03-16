<?php
// process/removeEmployee.php
session_start();
error_reporting(0);
include '../config/Connection.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['employee_id'];

    if (empty($id)) {
        echo json_encode(['success' => false, 'message' => 'Invalid Employee ID.']);
        exit;
    }

    try {
        // Fetch name for the log before deleting
        $name_stmt = $conn->prepare("SELECT FULL_NAME FROM employees WHERE EMPLOYEE_ID = ?");
        $name_stmt->execute([$id]);
        $empName = $name_stmt->fetchColumn();

        // Delete the record
        $stmt = $conn->prepare("DELETE FROM employees WHERE EMPLOYEE_ID = ?");
        $stmt->execute([$id]);

        // Audit Log
        $user_id = $_SESSION['user']['USER_ID'] ?? 0;
        $username = $_SESSION['user']['FULL_NAME'] ?? 'System';
        $log_sql = "INSERT INTO audit_logs (USER_ID, USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS) VALUES (?, ?, 'DELETE_EMPLOYEE', 'EMPLOYEES', ?, ?)";
        $conn->prepare($log_sql)->execute([$user_id, $username, "Deleted employee: $empName (ID: $id)", $_SERVER['REMOTE_ADDR']]);

        echo json_encode(['success' => true, 'message' => 'Employee removed.']);
    } catch (Exception $e) {
        // If there's a foreign key constraint, catch it here.
        echo json_encode(['success' => false, 'message' => 'Cannot delete employee. They may be linked to existing farm records. Consider setting status to Inactive instead.']);
    }
}
?>