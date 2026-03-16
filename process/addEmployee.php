<?php
// process/addEmployee.php
session_start();
error_reporting(0);
include '../config/Connection.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim($_POST['employee_code']);
    $name = trim($_POST['full_name']);
    $position = trim($_POST['position']);
    $contact = trim($_POST['contact_no']);
    $hire_date = !empty($_POST['hire_date']) ? $_POST['hire_date'] : null;

    if (empty($code) || empty($name) || empty($position)) {
        echo json_encode(['success' => false, 'message' => 'Employee Code, Name, and Position are required.']);
        exit;
    }

    try {
        $stmt = $conn->prepare("INSERT INTO employees (EMPLOYEE_CODE, FULL_NAME, POSITION, CONTACT_NO, HIRE_DATE) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$code, $name, $position, $contact, $hire_date]);

        // Audit Log
        $user_id = $_SESSION['user']['USER_ID'] ?? 0;
        $username = $_SESSION['user']['FULL_NAME'] ?? 'System';
        $log_sql = "INSERT INTO audit_logs (USER_ID, USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS) VALUES (?, ?, 'ADD_EMPLOYEE', 'EMPLOYEES', ?, ?)";
        $conn->prepare($log_sql)->execute([$user_id, $username, "Added employee: $name (Code: $code, $position)", $_SERVER['REMOTE_ADDR']]);

        echo json_encode(['success' => true, 'message' => 'Employee added successfully.']);
    } catch (Exception $e) {
        // Handle duplicate code error (if EMPLOYEE_CODE is set to UNIQUE in your DB down the line)
        if ($e->getCode() == 23000) {
             echo json_encode(['success' => false, 'message' => 'This Employee Code already exists.']);
        } else {
             echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
    }
}
?>