<?php
// purchase_confirmations/confirmAllFeedAndFeedingSupplies.php
session_start();
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

        $ITEM_TYPE_ID = 2; // Feeds ID

        $conn->beginTransaction();

        // 1. Fetch Pending Items
        $check_sql = "SELECT ITEM_ID, ITEM_NAME, QUANTITY, IFNULL(EXPIRATION_DATE, 'No Expiry') as EXP_LABEL 
                      FROM ITEMS WHERE ITEM_TYPE_ID = :type_id AND STATUS = 0 FOR UPDATE";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->execute([':type_id' => $ITEM_TYPE_ID]);
        
        $pending_items = $check_stmt->fetchAll(PDO::FETCH_ASSOC);
        $log_item_names = [];
        
        if (count($pending_items) == 0) {
            $conn->rollBack();
            echo json_encode(['success' => false, 'message' => 'No pending purchases to confirm.']);
            exit;
        }

        foreach ($pending_items as $row) {
            $qty_suffix = ((int)$row['QUANTITY'] > 1) ? " (x{$row['QUANTITY']})" : "";
            $log_item_names[] = $row['ITEM_NAME'] . " [{$row['EXP_LABEL']}]" . $qty_suffix;
        }

        // 2. AGGREGATE DATA
        // We Group By Name, Location, AND Expiration Date to keep batches separate
        $agg_sql = "SELECT 
                        ITEM_NAME, 
                        LOCATION_ID, 
                        -- If expiry is NULL, we set a default '0000-00-00' or similar logic to group them
                        IFNULL(EXPIRATION_DATE, '9999-12-31') as EXP_DATE, 
                        SUM(IFNULL(TOTAL_COST, 0)) AS SUM_COST,
                        SUM(IFNULL(QUANTITY, 0) * IFNULL(ITEM_NET_WEIGHT, 1)) AS SUM_WEIGHT
                    FROM ITEMS 
                    WHERE ITEM_TYPE_ID = :type_id AND STATUS = 0 
                    GROUP BY ITEM_NAME, LOCATION_ID, EXPIRATION_DATE"; // <--- Critical Grouping
        
        $agg_stmt = $conn->prepare($agg_sql);
        $agg_stmt->execute([':type_id' => $ITEM_TYPE_ID]);
        $aggregated_data = $agg_stmt->fetchAll(PDO::FETCH_ASSOC);

        // 3. UPSERT INTO FEEDS
        // This ensures distinct expiration dates create NEW rows, while matching dates merge.
        $upsert_sql = "INSERT INTO FEEDS (FEED_NAME, TOTAL_WEIGHT_KG, TOTAL_COST, EXPIRATION_DATE, LOCATION_ID, DATE_CREATED, DATE_UPDATED)
                       VALUES (:name, :weight, :cost, :expiry, :loc_id, NOW(), NOW())
                       ON DUPLICATE KEY UPDATE
                       TOTAL_WEIGHT_KG = TOTAL_WEIGHT_KG + VALUES(TOTAL_WEIGHT_KG),
                       TOTAL_COST = TOTAL_COST + VALUES(TOTAL_COST),
                       DATE_UPDATED = NOW()";
                        
        $upsert_stmt = $conn->prepare($upsert_sql);

        foreach ($aggregated_data as $row) {
            // Handle the '9999-12-31' placeholder back to NULL or kept as is if your DB requires date
            $expiry = ($row['EXP_DATE'] === '9999-12-31') ? NULL : $row['EXP_DATE'];

            // If your DB `EXPIRATION_DATE` column is NOT NULL, use a default date (e.g., +6 months) instead of NULL
            if($expiry === NULL) $expiry = date('Y-m-d', strtotime('+6 months')); 

            $upsert_stmt->execute([
                ':name' => $row['ITEM_NAME'],
                ':weight' => $row['SUM_WEIGHT'],
                ':cost' => $row['SUM_COST'],
                ':expiry' => $expiry, 
                ':loc_id' => $row['LOCATION_ID']
            ]);
        }

        // 4. Update Status
        $update_sql = "UPDATE ITEMS SET STATUS = 1, DATE_UPDATED = NOW() WHERE ITEM_TYPE_ID = :type_id AND STATUS = 0";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->execute([':type_id' => $ITEM_TYPE_ID]);
        
        $affected_rows = $update_stmt->rowCount();
            
        // 5. Audit Log
        $item_list = implode(", ", $log_item_names);
        if (strlen($item_list) > 3800) $item_list = substr($item_list, 0, 3750) . "... [truncated]";
        
        $logDetails = "Confirmed $affected_rows items into Inventory. Added/Merged Batches: " . $item_list;

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

        $conn->commit();
        
        echo json_encode([
            'success' => true, 
            'message' => "✅ Successfully processed $affected_rows purchase records. Inventory updated with expiration tracking."
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
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}
?>