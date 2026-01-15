<?php
session_start();

header('Content-Type: application/json');
error_reporting(0);
ini_set('display_errors', 0);

include '../config/Connection.php';

if (!isset($_SESSION['user']['USER_ID'])) {
    echo json_encode([
        'success' => false,  // ✅ Changed from 'status'
        'message' => 'Unauthorized access.'
    ]);
    exit;
}

$admin_id   = $_SESSION['user']['USER_ID'];
$admin_name = $_SESSION['user']['FULL_NAME'] ?? 'System';
$ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,  // ✅ Changed
        'message' => 'Invalid request method.'
    ]);
    exit;
}

$target_user_id = $_POST['user_id'] ?? null;

if (!$target_user_id) {
    echo json_encode([
        'success' => false,  // ✅ Changed
        'message' => 'User ID is missing.'
    ]);
    exit;
}

if ($target_user_id == $admin_id) {
    echo json_encode([
        'success' => false,  // ✅ Changed
        'message' => 'You cannot deactivate your own account.'
    ]);
    exit;
}

try {
    if (!isset($conn)) {
        throw new Exception('Database connection failed.');
    }

    $conn->beginTransaction();

    $user_sql = "
        SELECT FULL_NAME, IS_ACTIVE 
        FROM USERS 
        WHERE USER_ID = :id 
        FOR UPDATE
    ";
    $user_stmt = $conn->prepare($user_sql);
    $user_stmt->execute([':id' => $target_user_id]);

    $user = $user_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        throw new Exception('Target user not found.');
    }

    if ((int)$user['IS_ACTIVE'] === 0) {
        throw new Exception('Account is already deactivated.');
    }

    $target_user_name = $user['FULL_NAME'];

    $update_sql = "
        UPDATE USERS 
        SET IS_ACTIVE = 0,
            DATE_UPDATED = NOW()
        WHERE USER_ID = :id
    ";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->execute([':id' => $target_user_id]);

    if ($update_stmt->rowCount() === 0) {
        throw new Exception('Deactivation failed.');
    }

    $details = "Soft-deactivated user: {$target_user_name} (ID: {$target_user_id})";

    $log_sql = "
        INSERT INTO AUDIT_LOGS
        (USER_ID, USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS)
        VALUES
        (:user_id, :username, 'SOFT_DELETE_USER', 'USERS', :details, :ip)
    ";

    $log_stmt = $conn->prepare($log_sql);
    $log_stmt->execute([
        ':user_id'  => $admin_id,
        ':username' => $admin_name,
        ':details'  => $details,
        ':ip'       => $ip_address
    ]);

    $conn->commit();

    echo json_encode([
        'success' => true,  // ✅ Changed from 'status' => 'success'
        'message' => "Account for {$target_user_name} has been deactivated."
    ]);
    exit;

} catch (Exception $e) {

    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }

    echo json_encode([
        'success' => false,  // ✅ Changed from 'status' => 'error'
        'message' => $e->getMessage()
    ]);
    exit;
}
?>