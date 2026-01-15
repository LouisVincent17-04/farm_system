<?php
// purchase_confirmations/confirmAnimalPurchase.php
session_start();
error_reporting(0);
ini_set('display_errors', 0);
include '../config/Connection.php';

header('Content-Type: application/json');

// Get User Info
$user_id = !empty($_SESSION['user']['USER_ID']) ? $_SESSION['user']['USER_ID'] : null;
$username = $_SESSION['user']['FULL_NAME'] ?? 'System';
$ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['item_id'])) {
    
    $item_id = $_POST['item_id'];

    try {
        if (!isset($conn)) {
            throw new Exception("Database connection failed.");
        }

        $ITEM_TYPE_ID = 13; // Animal Type ID

        // Start Transaction
        $conn->beginTransaction();

        // 1. GET PENDING ANIMAL PURCHASE (Validation)
        // We check if it exists and is currently pending (Status 0)
        // We lock the row to prevent race conditions
        $get_sql = "SELECT ITEM_ID, ITEM_NAME 
                    FROM ITEMS 
                    WHERE ITEM_ID = :item_id 
                      AND ITEM_TYPE_ID = :type_id 
                      AND STATUS = 0 
                    FOR UPDATE"; 
        
        $get_stmt = $conn->prepare($get_sql);
        $get_stmt->execute([
            ':item_id' => $item_id,
            ':type_id' => $ITEM_TYPE_ID
        ]);
        $item = $get_stmt->fetch(PDO::FETCH_ASSOC);

        if (!$item) {
            $conn->rollBack();
            echo json_encode(['success' => false, 'message' => 'Item not found or already confirmed.']);
            exit;
        }

        // 2. UPDATE ITEM STATUS ONLY (Mark as Confirmed)
        $update_sql = "UPDATE ITEMS SET STATUS = 1, DATE_UPDATED = NOW() WHERE ITEM_ID = :id";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->execute([':id' => $item_id]);
        
        // 3. AUDIT LOGGING
        $name = $item['ITEM_NAME'];
        $logDetails = "Confirmed Animal Purchase: $name (ID: $item_id). Status updated to Confirmed.";

        $log_sql = "INSERT INTO AUDIT_LOGS 
                    (USER_ID, USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS) 
                    VALUES 
                    (:user_id, :username, 'CONFIRM_ANIMAL', 'ITEMS', :details, :ip)";
        
        $log_stmt = $conn->prepare($log_sql);
        $log_stmt->execute([
            ':user_id' => $user_id,
            ':username' => $username,
            ':details' => $logDetails,
            ':ip' => $ip_address
        ]);
        
        // 4. COMMIT
        $conn->commit(); 
        
        echo json_encode([
            'success' => true, 
            'message' => "✅ Purchase confirmed successfully!"
        ]);

    } catch (Exception $e) {
        if (isset($conn) && $conn->inTransaction()) {
            $conn->rollBack();
        }
        echo json_encode(['success' => false, 'message' => '❌ Error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
}
?>