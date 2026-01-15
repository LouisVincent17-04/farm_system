<?php
// purchase_confirmations/confirmPurchase.php
session_start(); // 1. Start Session
error_reporting(0);
ini_set('display_errors', 0);
include '../config/Connection.php';

header('Content-Type: application/json');

// Get User Info (Safe Fallback)
$user_id = !empty($_SESSION['user']['USER_ID']) ? $_SESSION['user']['USER_ID'] : null;
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

        // 1. GET ITEM NAME (Locking the row for transaction integrity)
        // FOR UPDATE ensures no one else modifies this row while we are processing
        $info_sql = "SELECT ITEM_NAME FROM ITEMS WHERE ITEM_ID = :id FOR UPDATE";
        $info_stmt = $conn->prepare($info_sql);
        $info_stmt->execute([':id' => $item_id]);
        $item_row = $info_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$item_row) {
             $conn->rollBack(); // Release lock
             throw new Exception("Item not found.");
        }
        $item_name = $item_row['ITEM_NAME'];

        // 2. UPDATE STATUS (Added DATE_UPDATED = NOW())
        $update_sql = "UPDATE ITEMS SET STATUS = 1, DATE_UPDATED = NOW() WHERE ITEM_ID = :id AND STATUS = 0";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->execute([':id' => $item_id]);
        
        // Check if any row was actually updated
        if ($update_stmt->rowCount() == 0) {
             $conn->rollBack();
             throw new Exception("Item might already be confirmed or does not exist.");
        }

        // 3. INSERT AUDIT LOG
        $logDetails = "Confirmed generic purchase (ID: $item_id) of: " . $item_name . ". Status set to Confirmed.";
        
        

        $log_sql = "INSERT INTO AUDIT_LOGS 
                    (USER_ID, USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS) 
                    VALUES 
                    (:user_id, :username, 'CONFIRM_PURCHASE', 'ITEMS', :details, :ip)";
        
        $log_stmt = $conn->prepare($log_sql);
        $log_stmt->execute([
            ':user_id' => $user_id,
            ':username' => $username,
            ':details' => $logDetails,
            ':ip' => $ip_address
        ]);

        // 4. COMMIT EVERYTHING
        $conn->commit();

        echo json_encode([
            'success' => true, 
            'message' => '✅ Purchase confirmed. Record is now Confirmed.'
        ]);

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
        'message' => 'Invalid request method or Item ID is missing.'
    ]);
}
?>