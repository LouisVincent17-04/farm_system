<?php
session_start(); // 1. Start Session
header('Content-Type: application/json');
error_reporting(0);
ini_set('display_errors', 0);
require_once '../config/Connection.php';

// Get Current User Info (The user performing the action)
$user_id = !empty($_SESSION['user']['USER_ID']) ? $_SESSION['user']['USER_ID'] : null;
$username = $_SESSION['user']['FULL_NAME'] ?? 'System';
$ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['user_id'] ?? null;
    $name = $_POST['full_name'] ?? null;
    $email = $_POST['email'] ?? null;

    if (!$id || !$name || !$email) {
        echo json_encode(['success' => false, 'message' => '❌ Missing fields (ID, Name, or Email).']);
        exit;
    }

    try {
        if (!isset($conn)) {
            throw new Exception("Database connection failed.");
        }

        // ---------------------------------------------------------
        // START TRANSACTION
        // ---------------------------------------------------------
        $conn->beginTransaction();

        // 1. Get Original Data (For Audit Log Comparison)
        // Using FOR UPDATE to lock the row
        $original_sql = "SELECT FULL_NAME, EMAIL FROM USERS WHERE USER_ID = :id FOR UPDATE";
        $original_stmt = $conn->prepare($original_sql);
        $original_stmt->execute([':id' => $id]);
        
        $original_row = $original_stmt->fetch(PDO::FETCH_ASSOC);

        if (!$original_row) {
            throw new Exception('Target user not found.');
        }

        $original_name = $original_row['FULL_NAME'];
        $original_email = $original_row['EMAIL'];

        // 2. Perform UPDATE
        // Note: Replaced SYSDATE with NOW()
        $sql = "UPDATE USERS SET 
                FULL_NAME = :name, 
                EMAIL = :email,
                DATE_UPDATED = NOW() 
                WHERE USER_ID = :id";
        
        $stmt = $conn->prepare($sql);
        $params = [
            ':name'  => $name,
            ':email' => $email,
            ':id'    => $id
        ];

        if (!$stmt->execute($params)) {
            throw new Exception('User update failed.');
        }

        // 3. INSERT AUDIT LOG
        $logDetails = "Edited User (ID: $id). Name: $original_name -> $name. Email: $original_email -> $email.";
        
        $log_sql = "INSERT INTO AUDIT_LOGS 
                    (USER_ID, USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS) 
                    VALUES 
                    (:user_id, :username, 'EDIT_USER', 'USERS', :details, :ip)";
        
        $log_stmt = $conn->prepare($log_sql);
        $log_params = [
            ':user_id'  => $user_id,
            ':username' => $username,
            ':details'  => $logDetails,
            ':ip'       => $ip_address
        ];
        
        if (!$log_stmt->execute($log_params)) {
            throw new Exception("Audit Log Failed.");
        }

        // 4. COMMIT EVERYTHING
        $conn->commit();
        echo json_encode(['success' => true, 'message' => '✅ User updated successfully.']);

    } catch (Exception $e) {
        // Rollback on error
        if (isset($conn) && $conn->inTransaction()) {
            $conn->rollBack();
        }
        echo json_encode(['success' => false, 'message' => '❌ Error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => '❌ Invalid request method.']);
}
?>