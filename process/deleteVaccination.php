<?php
session_start(); // 1. Start Session
header('Content-Type: application/json');
error_reporting(0);
ini_set('display_errors', 0);

include '../config/Connection.php';

// Get User Info (Safe Fallback)
$user_id = !empty($_SESSION['user']['USER_ID']) ? $_SESSION['user']['USER_ID'] : null;
$username = $_SESSION['user']['FULL_NAME'] ?? 'System';
$ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';

$response = ['success' => false, 'message' => ''];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    $vaccination_id = $_POST['vaccination_id'] ?? null;

    if (!$vaccination_id) {
        $response['message'] = 'Vaccination ID is missing.';
        echo json_encode($response);
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

        // 1. GET RECORD DETAILS (for Stock Refund and Audit Log)
        // Using FOR UPDATE to lock vaccine record and stock
        $getSql = "SELECT vr.VACCINE_ITEM_ID, vr.QUANTITY, ar.TAG_NO, v.SUPPLY_NAME 
                   FROM VACCINATION_RECORDS vr
                   JOIN ANIMAL_RECORDS ar ON vr.ANIMAL_ID = ar.ANIMAL_ID
                   JOIN VACCINES v ON vr.VACCINE_ITEM_ID = v.SUPPLY_ID
                   WHERE vr.VACCINATION_ID = :id FOR UPDATE";
        
        $getStmt = $conn->prepare($getSql);
        $getStmt->execute([':id' => $vaccination_id]);
        
        $row = $getStmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            // Rollback just in case
            $conn->rollBack();
            throw new Exception("Vaccination record not found or already deleted.");
        }

        $itemId = $row['VACCINE_ITEM_ID']; 
        $qty    = $row['QUANTITY'];
        $animal_tag = $row['TAG_NO'];
        $vaccine_name = $row['SUPPLY_NAME'];

        // 2. RESTORE STOCK TO 'VACCINES' TABLE
        // Note: Replaced SYSDATE with NOW()
        $restoreSql = "UPDATE VACCINES 
                       SET TOTAL_STOCK = TOTAL_STOCK + :qty, 
                           DATE_UPDATED = NOW() 
                       WHERE SUPPLY_ID = :item_id";
        
        $restoreStmt = $conn->prepare($restoreSql);
        $restoreParams = [
            ':qty'     => $qty,
            ':item_id' => $itemId
        ];

        if (!$restoreStmt->execute($restoreParams)) {
            throw new Exception("Failed to restore stock.");
        }

        // 3. DELETE THE RECORD
        $delSql = "DELETE FROM VACCINATION_RECORDS WHERE VACCINATION_ID = :id";
        $delStmt = $conn->prepare($delSql);
        
        if (!$delStmt->execute([':id' => $vaccination_id])) {
            throw new Exception("Delete failed.");
        }

        // 4. INSERT AUDIT LOG
        // Note: Removed RECORD_ID column based on your previous input.
        $logDetails = "Deleted Vaccination Record (ID: $vaccination_id) for Animal $animal_tag. Restored $qty of $vaccine_name.";
        
        $log_sql = "INSERT INTO AUDIT_LOGS 
                    (USER_ID, USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS) 
                    VALUES 
                    (:user_id, :username, 'DELETE_VACCINATION', 'VACCINATION_RECORDS', :details, :ip)";
        
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

        // 5. Commit both the restore, delete, and log
        $conn->commit();
        
        $response['success'] = true;
        $response['message'] = "✅ Record deleted and $vaccine_name stock restored.";
        
    } catch (Exception $e) {
        // 6. Rollback on Error
        if (isset($conn) && $conn->inTransaction()) {
            $conn->rollBack();
        }
        $response['message'] = '❌ Delete Failed: ' . $e->getMessage();
    }
} else {
    $response['message'] = '❌ Invalid request method.';
}

echo json_encode($response);
?>