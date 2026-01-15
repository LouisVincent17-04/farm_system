<?php
// purchase_confirmations/confirmAllAnimalPurchases.php
session_start(); // 1. Start Session
error_reporting(0);
ini_set('display_errors', 0);
include '../config/Connection.php';

header('Content-Type: application/json');

// Get User Info
$user_id = !empty($_SESSION['user']['USER_ID']) ? $_SESSION['user']['USER_ID'] : null;
$username = $_SESSION['user']['FULL_NAME'] ?? 'System';
$ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    try {
        if (!isset($conn)) {
            throw new Exception("Database connection failed.");
        }

        $ANIMAL_ITEM_TYPE_ID = 13; // Generic "Animal" Item Type

        // Start Transaction
        $conn->beginTransaction();

        // 1. BULK UPDATE STATUS
        // We update all pending items directly.
        // Changed SYSDATE to NOW() for MySQL
        $update_sql = "UPDATE ITEMS SET STATUS = 1, DATE_UPDATED = NOW() WHERE ITEM_TYPE_ID = :type_id AND STATUS = 0";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->execute([':type_id' => $ANIMAL_ITEM_TYPE_ID]);
        
        // Check how many rows were updated
        $rows_updated = $update_stmt->rowCount();

        if ($rows_updated == 0) {
            // Nothing to update is not an error, just an info state
            // Rollback is safe here, though not strictly necessary since no data changed
            $conn->rollBack();
            echo json_encode(['success' => false, 'message' => 'No pending animals to confirm.']);
            exit;
        }
            
        // 2. AUDIT LOGGING
        $logDetails = "Bulk confirmed $rows_updated pending animal purchase records.";

        $log_sql = "INSERT INTO AUDIT_LOGS 
                    (USER_ID, USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS) 
                    VALUES 
                    (:user_id, :username, 'BULK_CONFIRM_ANIMAL', 'ITEMS', :details, :ip)";
        
        $log_stmt = $conn->prepare($log_sql);
        $log_stmt->execute([
            ':user_id' => $user_id,
            ':username' => $username,
            ':details' => $logDetails,
            ':ip' => $ip_address
        ]);

        // 3. COMMIT ALL CHANGES
        $conn->commit();
        
        echo json_encode([
            'success' => true, 
            'message' => "✅ Success! $rows_updated purchase records confirmed."
        ]);

    } catch (Exception $e) {
        if (isset($conn) && $conn->inTransaction()) {
            $conn->rollBack();
        }
        echo json_encode(['success' => false, 'message' => '❌ Error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}
?>