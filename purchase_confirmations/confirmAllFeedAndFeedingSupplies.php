<?php
// purchase_confirmations/confirmAllFeedAndFeedingSupplies.php
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

        $ITEM_TYPE_ID = 2; // Feeds ID

        // Start Transaction
        $conn->beginTransaction();

        // 1. Fetch Item Names and IDs for the Log & subsequent UPDATE (Locking rows)
        $check_sql = "SELECT ITEM_ID, ITEM_NAME, QUANTITY FROM ITEMS WHERE ITEM_TYPE_ID = :type_id AND STATUS = 0 FOR UPDATE";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->execute([':type_id' => $ITEM_TYPE_ID]);
        
        $pending_items = $check_stmt->fetchAll(PDO::FETCH_ASSOC);
        $log_item_names = [];
        
        foreach ($pending_items as $row) {
            $qty_suffix = ((int)$row['QUANTITY'] > 1) ? " (x{$row['QUANTITY']})" : "";
            $log_item_names[] = $row['ITEM_NAME'] . $qty_suffix;
        }
        
        $count = count($pending_items);

        if ($count == 0) {
            $conn->rollBack(); // Release lock
            echo json_encode(['success' => false, 'message' => 'No pending purchases to confirm.']);
            exit;
        }

        // 2. MERGE INTO FEEDS (The "Upsert" Logic)
        // MySQL uses INSERT ... ON DUPLICATE KEY UPDATE instead of MERGE
        // Logic: Group by ITEM_NAME and LOCATION_ID to sum up quantities/costs before inserting/updating
        
        // First, get the aggregated data
        $agg_sql = "SELECT 
                        ITEM_NAME, 
                        LOCATION_ID, 
                        SUM(IFNULL(TOTAL_COST, 0)) AS SUM_COST,
                        -- Logic: If Net Weight exists (e.g., 50kg sack), multiply by Qty. 
                        -- If Net Weight is NULL, assume Qty is the weight itself.
                        SUM(IFNULL(QUANTITY, 0) * IFNULL(ITEM_NET_WEIGHT, 1)) AS SUM_WEIGHT
                    FROM ITEMS 
                    WHERE ITEM_TYPE_ID = :type_id AND STATUS = 0 
                    GROUP BY ITEM_NAME, LOCATION_ID";
        
        $agg_stmt = $conn->prepare($agg_sql);
        $agg_stmt->execute([':type_id' => $ITEM_TYPE_ID]);
        $aggregated_data = $agg_stmt->fetchAll(PDO::FETCH_ASSOC);

        // Now perform the Upsert for each aggregated record
        $upsert_sql = "INSERT INTO FEEDS (FEED_NAME, TOTAL_WEIGHT_KG, TOTAL_COST, LOCATION_ID, DATE_CREATED, DATE_UPDATED)
                       VALUES (:name, :weight, :cost, :loc_id, NOW(), NOW())
                       ON DUPLICATE KEY UPDATE
                       TOTAL_WEIGHT_KG = TOTAL_WEIGHT_KG + VALUES(TOTAL_WEIGHT_KG),
                       TOTAL_COST = TOTAL_COST + VALUES(TOTAL_COST),
                       DATE_UPDATED = NOW()";
                       
        $upsert_stmt = $conn->prepare($upsert_sql);

        foreach ($aggregated_data as $row) {
            $upsert_stmt->execute([
                ':name' => $row['ITEM_NAME'],
                ':weight' => $row['SUM_WEIGHT'],
                ':cost' => $row['SUM_COST'],
                ':loc_id' => $row['LOCATION_ID']
            ]);
        }

        // 3. Bulk Update: Mark items as confirmed (Status = 1) (Added DATE_UPDATED = NOW())
        $update_sql = "UPDATE ITEMS SET STATUS = 1, DATE_UPDATED = NOW() WHERE ITEM_TYPE_ID = :type_id AND STATUS = 0";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->execute([':type_id' => $ITEM_TYPE_ID]);
        
        $affected_rows = $update_stmt->rowCount();
            
        // --- 4. AUDIT LOGGING ---
        
        $item_list = implode(", ", $log_item_names);
        // Truncate if too long
        if (strlen($item_list) > 3800) {
            $item_list = substr($item_list, 0, 3750) . "... [truncated]";
        }
        
        $logDetails = "Bulk confirmed $count Feed items (Merged into Inventory): " . $item_list;
        

        $log_sql = "INSERT INTO AUDIT_LOGS 
                    (USER_ID, USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS) 
                    VALUES 
                    (:user_id, :username, 'BULK_CONFIRM_FEED', 'ITEMS/FEEDS', :details, :ip)";
        
        $log_stmt = $conn->prepare($log_sql);
        $log_stmt->execute([
            ':user_id' => $user_id,
            ':username' => $username,
            ':details' => $logDetails,
            ':ip' => $ip_address
        ]);

        // 5. Commit both the Merge, Update, and Log together
        $conn->commit();
        
        echo json_encode([
            'success' => true, 
            'message' => "✅ Successfully added to inventory and confirmed $affected_rows purchase records."
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