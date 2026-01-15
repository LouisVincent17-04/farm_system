<?php
// purchase_confirmations/confirmAllUtilitiesAndConsumables.php
session_start(); // 1. Start Session to get User Info
error_reporting(0);
ini_set('display_errors', 0);
include '../config/Connection.php';

header('Content-Type: application/json');

// Get User Info (Safe Fallback)
$user_id = !empty($_SESSION['user']['USER_ID']) ? $_SESSION['user']['USER_ID'] : null;
$username = $_SESSION['user']['FULL_NAME'] ?? 'System';
$ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    try {
        if (!isset($conn)) {
            throw new Exception("Database connection failed.");
        }

        $ITEM_TYPE_ID = 9; // Utilities & Consumables

        // Start Transaction
        $conn->beginTransaction();

        // 1. Fetch Item Names for the Log (Before updating)
        $check_sql = "SELECT ITEM_NAME FROM ITEMS WHERE ITEM_TYPE_ID = :type_id AND STATUS = 0 FOR UPDATE"; // Lock rows
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->execute([':type_id' => $ITEM_TYPE_ID]);
        
        $pending_items = $check_stmt->fetchAll(PDO::FETCH_COLUMN); // Fetch just the names
        $count = count($pending_items);

        if ($count == 0) {
            $conn->rollBack(); // Release lock
            echo json_encode(['success' => false, 'message' => 'No pending purchases to confirm.']);
            exit;
        }

        // 2. Update Status (Added DATE_UPDATED = NOW())
        $update_sql = "UPDATE ITEMS SET STATUS = 1, DATE_UPDATED = NOW() WHERE ITEM_TYPE_ID = :type_id AND STATUS = 0";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->execute([':type_id' => $ITEM_TYPE_ID]);
            
        // --- 3. AUDIT LOGGING ---
        
        $item_list = implode(", ", $pending_items);
        // Truncate if too long (max 3750 chars for safety in typical TEXT fields)
        if (strlen($item_list) > 3800) {
            $item_list = substr($item_list, 0, 3750) . "... [truncated]";
        }
        
        $logDetails = "Bulk confirmed $count Utility items: " . $item_list;
        
        // 

        $log_sql = "INSERT INTO AUDIT_LOGS 
                    (USER_ID, USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS) 
                    VALUES 
                    (:user_id, :username, 'BULK_CONFIRM', 'ITEMS', :details, :ip)";
        
        $log_stmt = $conn->prepare($log_sql);
        $log_stmt->execute([
            ':user_id' => $user_id,
            ':username' => $username,
            ':details' => $logDetails,
            ':ip' => $ip_address
        ]);

        // 4. Commit All Changes
        $conn->commit();
        
        echo json_encode([
            'success' => true, 
            'message' => "✅ Successfully confirmed $count pending purchases."
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
        'message' => 'Invalid request method.'
    ]);
}
?>