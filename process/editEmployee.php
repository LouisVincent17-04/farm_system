<?php
// process/editEmployee.php
session_start();
error_reporting(0);
include '../config/Connection.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['employee_id'];
    $code = trim($_POST['employee_code']);
    $name = trim($_POST['full_name']);
    $position = trim($_POST['position']);
    $contact = trim($_POST['contact_no']);
    $hire_date = !empty($_POST['hire_date']) ? $_POST['hire_date'] : null;
    $status = $_POST['status'];

    if (empty($id) || empty($code) || empty($name) || empty($position)) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
        exit;
    }

    try {
        $stmt = $conn->prepare("UPDATE employees SET EMPLOYEE_CODE = ?, FULL_NAME = ?, POSITION = ?, CONTACT_NO = ?, HIRE_DATE = ?, STATUS = ? WHERE EMPLOYEE_ID = ?");
        $stmt->execute([$code, $name, $position, $contact, $hire_date, $status, $id]);

        // Audit Log
        $user_id = $_SESSION['user']['USER_ID'] ?? 0;
        $username = $_SESSION['user']['FULL_NAME'] ?? 'System';
        $log_sql = "INSERT INTO audit_logs (USER_ID, USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS) VALUES (?, ?, 'EDIT_EMPLOYEE', 'EMPLOYEES', ?, ?)";
        $conn->prepare($log_sql)->execute([$user_id, $username, "Updated employee ID $id: $name (Code: $code, $status)", $_SERVER['REMOTE_ADDR']]);

        echo json_encode(['success' => true, 'message' => 'Employee updated successfully.']);
    } catch (Exception $e) {
        if ($e->getCode() == 23000) {
             echo json_encode(['success' => false, 'message' => 'This Employee Code already belongs to someone else.']);
        } else {
             echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
    }
}
?>