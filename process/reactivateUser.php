<?php
// ../process/reactivateUser.php
session_start(); // 1. Start Session
error_reporting(0);
ini_set('display_errors', 0); 
header('Content-Type: application/json');

require_once '../config/Connection.php';

// Get Current User Info (The user performing the action)
$acting_user_id = !empty($_SESSION['user']['USER_ID']) ? $_SESSION['user']['USER_ID'] : null;
$acting_username = $_SESSION['user']['FULL_NAME'] ?? 'System';
$ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['user_id'] ?? null;
    $user_name_to_reactivate = 'N/A';

    if (!$id) {
        echo json_encode(['success' => false, 'message' => '❌ User ID is missing.']);
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

        // 1. Fetch the user's name for the Audit Log
        $fetch_sql = "SELECT FULL_NAME FROM USERS WHERE USER_ID = :id";
        $fetch_stmt = $conn->prepare($fetch_sql);
        $fetch_stmt->execute([':id' => $id]);
        
        $user_row = $fetch_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user_row) {
            $user_name_to_reactivate = $user_row['FULL_NAME'];
        } else {
            throw new Exception("User ID not found.");
        }

        // 2. Set IS_ACTIVE back to 1 and update DATE_UPDATED
        // Note: Replaced SYSDATE with NOW()
        $sql = "UPDATE USERS SET IS_ACTIVE = 1, DATE_UPDATED = NOW() WHERE USER_ID = :id";
        $update_stmt = $conn->prepare($sql);
        
        if (!$update_stmt->execute([':id' => $id])) {
            throw new Exception("Database Update Failed.");
        }

        // 3. INSERT AUDIT LOG
        $logDetails = "Reactivated User (ID: $id) $user_name_to_reactivate.";
        
        $log_sql = "INSERT INTO AUDIT_LOGS 
                    (USER_ID, USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS) 
                    VALUES 
                    (:user_id, :username, 'REACTIVATE_USER', 'USERS', :details, :ip)";
        
        $log_stmt = $conn->prepare($log_sql);
        $log_params = [
            ':user_id'  => $acting_user_id,
            ':username' => $acting_username,
            ':details'  => $logDetails,
            ':ip'       => $ip_address
        ];
        
        if (!$log_stmt->execute($log_params)) {
            throw new Exception("Audit Log Failed.");
        }

        // 4. COMMIT EVERYTHING
        $conn->commit();
        
        echo json_encode(['success' => true, 'message' => "✅ User $user_name_to_reactivate reactivated successfully."]);

    } catch (Exception $e) {
        // Rollback on error
        if (isset($conn) && $conn->inTransaction()) {
            $conn->rollBack();
        }
        echo json_encode(['success' => false, 'message' => '❌ Error reactivating user: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => '❌ Invalid request method.']);
}
?>