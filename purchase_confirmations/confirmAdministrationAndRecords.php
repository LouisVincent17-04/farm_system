<?php
// purchase_confirmations/confirmAdministrationAndRecords.php
session_start(); // 1. Start Session to get User Info
error_reporting(0);
ini_set('display_errors', 0);
include '../config/Connection.php';

header('Content-Type: application/json');

// Get Current User Details
$acting_user_id = $_SESSION['user']['USER_ID'] ?? null;
$username = $_SESSION['user']['FULL_NAME'] ?? 'System';
$ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['item_id'])) {
    
    $item_id = $_POST['item_id'];

    try {
        if (!isset($conn)) {
            throw new Exception("Database connection failed.");
        }

        // Start Transaction
        $conn->beginTransaction();

        

        // 2. GET ITEM NAME (For a better log description)
        $info_sql = "SELECT ITEM_NAME FROM ITEMS WHERE ITEM_ID = :id";
        $info_stmt = $conn->prepare($info_sql);
        $info_stmt->execute([':id' => $item_id]);
        $item_row = $info_stmt->fetch(PDO::FETCH_ASSOC);
        
        $item_name = $item_row ? $item_row['ITEM_NAME'] : 'Unknown Item';

        // 3. Strict Update: Only update if ID matches AND Status is currently 0 (Pending)
        // Changed SYSDATE to NOW() for MySQL
        $update_sql = "UPDATE ITEMS SET 
                       STATUS = 1, 
                       DATE_UPDATED = NOW() 
                       WHERE ITEM_ID = :id 
                       AND STATUS = 0";
                       
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->execute([':id' => $item_id]);
        
        $rows_affected = $update_stmt->rowCount();

        if ($rows_affected > 0) {
            
            // 4. INSERT AUDIT LOG
            $logDetails = "Confirmed purchase of: " . $item_name . " (ID: $item_id). Status set to Confirmed.";
            
            $log_sql = "INSERT INTO AUDIT_LOGS 
                        (USER_ID, USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS) 
                        VALUES 
                        (:user_id, :username, 'CONFIRM_PURCHASE', 'ITEMS', :details, :ip)";
            
            $log_stmt = $conn->prepare($log_sql);
            $log_stmt->execute([
                ':user_id' => $acting_user_id,
                ':username' => $username,
                ':details' => $logDetails,
                ':ip' => $ip_address
            ]);

            // 5. COMMIT EVERYTHING (Update + Log)
            $conn->commit();

            echo json_encode([
                'success' => true, 
                'message' => '✅ Purchase confirmed. Record is now Confirmed (Status 1).'
            ]);
            
        } else {
            // The item either didn't exist or was not in STATUS = 0
            // Rollback is technically not needed here if no data changed, but good practice if logic gets complex
            $conn->rollBack();
            throw new Exception("Failed to confirm. Item might already be confirmed or does not exist.");
        }

    } catch (Exception $e) {
        if (isset($conn) && $conn->inTransaction()) {
            $conn->rollBack();
        }
        echo json_encode([
            'success' => false, 
            'message' => '❌ Error: ' . $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false, 
        'message' => 'Invalid request. Item ID is missing.'
    ]);
}
?>