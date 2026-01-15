<?php
// purchase_confirmations/confirmAllMedicines.php
session_start(); // 1. Start Session
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

        $ITEM_TYPE_ID = 1; // Medicine ID

        // Start Transaction
        $conn->beginTransaction();

        // 1. Fetch Item Names for the Log (Locking rows)
        $check_sql = "SELECT ITEM_NAME FROM ITEMS WHERE ITEM_TYPE_ID = :type_id AND STATUS = 0 FOR UPDATE";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->execute([':type_id' => $ITEM_TYPE_ID]);
        
        $pending_items = $check_stmt->fetchAll(PDO::FETCH_COLUMN); // Fetch just names
        $count = count($pending_items);

        if ($count == 0) {
            $conn->rollBack(); // Release lock
            echo json_encode(['success' => false, 'message' => 'No pending medicine purchases to confirm.']);
            exit;
        }

        // 2. MERGE INTO MEDICINES (The "Upsert" Logic)
        // MySQL uses INSERT ... ON DUPLICATE KEY UPDATE logic
        
        // First, get the aggregated data
        $agg_sql = "SELECT 
                        ITEM_NAME, 
                        UNIT_ID,
                        SUM(IFNULL(TOTAL_COST, 0)) AS SUM_COST,
                        -- Logic: If Net Weight exists, multiply by Qty. Else assume Qty is the weight.
                        SUM(IFNULL(QUANTITY, 0) * IFNULL(ITEM_NET_WEIGHT, 1)) AS SUM_STOCK
                    FROM ITEMS 
                    WHERE ITEM_TYPE_ID = :type_id AND STATUS = 0 
                    GROUP BY ITEM_NAME, UNIT_ID";
        
        $agg_stmt = $conn->prepare($agg_sql);
        $agg_stmt->execute([':type_id' => $ITEM_TYPE_ID]);
        $aggregated_data = $agg_stmt->fetchAll(PDO::FETCH_ASSOC);

        // Perform the Upsert
        $upsert_sql = "INSERT INTO MEDICINES (SUPPLY_NAME, TOTAL_STOCK, TOTAL_COST, UNIT_ID, DATE_CREATED, DATE_UPDATED)
                       VALUES (:name, :stock, :cost, :unit_id, NOW(), NOW())
                       ON DUPLICATE KEY UPDATE
                       TOTAL_STOCK = TOTAL_STOCK + VALUES(TOTAL_STOCK),
                       TOTAL_COST = TOTAL_COST + VALUES(TOTAL_COST),
                       DATE_UPDATED = NOW()";
                       
        $upsert_stmt = $conn->prepare($upsert_sql);

        foreach ($aggregated_data as $row) {
            $upsert_stmt->execute([
                ':name' => $row['ITEM_NAME'],
                ':stock' => $row['SUM_STOCK'],
                ':cost' => $row['SUM_COST'],
                ':unit_id' => $row['UNIT_ID']
            ]);
        }

        // 3. Update Status in ITEMS table to '1' (Confirmed)
        $update_sql = "UPDATE ITEMS SET STATUS = 1, DATE_UPDATED = NOW() WHERE ITEM_TYPE_ID = :type_id AND STATUS = 0";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->execute([':type_id' => $ITEM_TYPE_ID]);
        
        $affected_rows = $update_stmt->rowCount();
            
        // --- 4. AUDIT LOGGING ---
        
        $item_list = implode(", ", $pending_items);
        // Truncate if too long
        if (strlen($item_list) > 3800) {
            $item_list = substr($item_list, 0, 3750) . "... [truncated]";
        }
        
        $logDetails = "Bulk confirmed $count Medicine items (Merged into Inventory): " . $item_list;
        

        $log_sql = "INSERT INTO AUDIT_LOGS 
                    (USER_ID, USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS) 
                    VALUES 
                    (:user_id, :username, 'BULK_CONFIRM_MED', 'ITEMS/MEDICINES', :details, :ip)";
        
        $log_stmt = $conn->prepare($log_sql);
        $log_stmt->execute([
            ':user_id' => $user_id,
            ':username' => $username,
            ':details' => $logDetails,
            ':ip' => $ip_address
        ]);

        // 5. Commit All Changes
        $conn->commit();
        
        echo json_encode([
            'success' => true, 
            'message' => "✅ Successfully added to medicine inventory. ($affected_rows items processed)"
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