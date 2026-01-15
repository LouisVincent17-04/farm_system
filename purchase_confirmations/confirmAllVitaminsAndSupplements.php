<?php
// purchase_confirmations/confirmAllVitaminsAndSupplements.php
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

        $ITEM_TYPE_ID = 10; // Vitamin/Supplement Type ID

        // Start Transaction
        $conn->beginTransaction();

        // 1. GET ALL PENDING VITAMIN PURCHASES
        // UPDATED: Added i.TOTAL_COST to the select list
        $get_sql = "SELECT 
                        i.ITEM_ID, i.ITEM_NAME, i.QUANTITY, i.ITEM_NET_WEIGHT, i.UNIT_ID, i.TOTAL_COST,
                        u.UNIT_ABBR
                    FROM ITEMS i
                    LEFT JOIN UNITS u ON i.UNIT_ID = u.UNIT_ID
                    WHERE i.ITEM_TYPE_ID = :type_id AND i.STATUS = 0 FOR UPDATE"; 
        
        $get_stmt = $conn->prepare($get_sql);
        $get_stmt->execute([':type_id' => $ITEM_TYPE_ID]);
        $pending_items = $get_stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($pending_items) == 0) {
            $conn->rollBack(); // Release lock
            echo json_encode(['success' => false, 'message' => 'No pending items to confirm.']);
            exit;
        }

        $log_item_names = [];
        $total_processed_items = 0;

        // 2. PROCESS INVENTORY SYNC FOR EACH PENDING ITEM
        foreach ($pending_items as $item) {
            $name = $item['ITEM_NAME'];
            $qty = (float)$item['QUANTITY'];
            $unit = $item['UNIT_ID'];
            $net_weight = (float)$item['ITEM_NET_WEIGHT'];
            $cost = (float)$item['TOTAL_COST']; // Fetch the cost
            $unit_abbr = strtoupper($item['UNIT_ABBR']);
            
            // --- STOCK CALCULATION LOGIC ---
            $stock_to_add = $qty; 
            if ($unit_abbr === 'ML' || $unit_abbr === 'L') {
                $stock_to_add = $net_weight > 0 ? ($net_weight * $qty) : $qty;
            } 
            // ------------------------------------

            $log_item_names[] = "$name (Qty: $stock_to_add, Cost: $cost)";

            // Check if exists in Inventory
            $check_inv = $conn->prepare("SELECT SUPPLY_ID FROM VITAMINS_SUPPLEMENTS WHERE SUPPLY_NAME = :name AND UNIT_ID = :unit FOR UPDATE");
            $check_inv->execute([':name' => $name, ':unit' => $unit]);
            $inv_row = $check_inv->fetch(PDO::FETCH_ASSOC);

            if ($inv_row) {
                // Update Existing Stock & Add Cost
                // UPDATED: Added TOTAL_COST = TOTAL_COST + :cost
                $update_inv = $conn->prepare("UPDATE VITAMINS_SUPPLEMENTS 
                                                 SET TOTAL_STOCK = TOTAL_STOCK + :qty, 
                                                     TOTAL_COST = TOTAL_COST + :cost,
                                                     DATE_UPDATED = NOW() 
                                                 WHERE SUPPLY_ID = :id");
                $update_inv->execute([
                    ':qty' => $stock_to_add,
                    ':cost' => $cost,
                    ':id' => $inv_row['SUPPLY_ID']
                ]);
            } else {
                // Insert New Inventory Record with Cost
                // UPDATED: Added TOTAL_COST column and value
                $insert_inv = $conn->prepare("INSERT INTO VITAMINS_SUPPLEMENTS 
                                                 (SUPPLY_NAME, TOTAL_STOCK, TOTAL_COST, UNIT_ID, DATE_CREATED, DATE_UPDATED) 
                                                 VALUES (:name, :qty, :cost, :unit, NOW(), NOW())");
                $insert_inv->execute([
                    ':name' => $name,
                    ':qty' => $stock_to_add,
                    ':cost' => $cost,
                    ':unit' => $unit
                ]);
            }
            $total_processed_items++;
        }

        // 3. BULK UPDATE STATUS (Mark all as Confirmed)
        $update_sql = "UPDATE ITEMS SET STATUS = 1, DATE_UPDATED = NOW() WHERE ITEM_TYPE_ID = :type_id AND STATUS = 0";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->execute([':type_id' => $ITEM_TYPE_ID]);
        $count = $update_stmt->rowCount();
            
        // 4. AUDIT LOGGING
        $item_list = implode(", ", $log_item_names);
        if (strlen($item_list) > 3800) {
            $item_list = substr($item_list, 0, 3750) . "... [truncated]";
        }
        
        $logDetails = "Bulk confirmed $count Vitamin items (Synced to Inventory with Cost): " . $item_list;
        
        $log_sql = "INSERT INTO AUDIT_LOGS 
                    (USER_ID, USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS) 
                    VALUES 
                    (:user_id, :username, 'BULK_CONFIRM_VITAMIN', 'ITEMS/VITAMINS', :details, :ip)";
        
        $log_stmt = $conn->prepare($log_sql);
        $log_stmt->execute([
            ':user_id' => $user_id,
            ':username' => $username,
            ':details' => $logDetails,
            ':ip' => $ip_address
        ]);
        
        // 5. COMMIT EVERYTHING
        $conn->commit(); 
        
        echo json_encode([
            'success' => true, 
            'message' => "✅ Successfully confirmed $count items and updated inventory costs."
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