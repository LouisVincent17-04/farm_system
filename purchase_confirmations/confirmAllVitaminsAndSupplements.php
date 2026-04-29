<?php
// purchase_confirmations/confirmAllVitaminsAndSupplements.php
session_start(); // 1. Start Session
error_reporting(0);
ini_set('display_errors', 0);
include '../config/Connection.php';

header('Content-Type: application/json');

$user_id = !empty($_SESSION['user']['USER_ID']) ? $_SESSION['user']['USER_ID'] : null;
$username = $_SESSION['user']['FULL_NAME'] ?? 'System';
$ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    try {
        if (!isset($conn)) {
            throw new Exception("Database connection failed.");
        }

        $ITEM_TYPE_ID = 10; 

        $conn->beginTransaction();

        $get_sql = "SELECT 
                        i.ITEM_ID, i.ITEM_NAME, i.QUANTITY, i.ITEM_NET_WEIGHT, i.UNIT_ID, i.TOTAL_COST, i.EXPIRATION_DATE, i.LOCATION_ID,
                        u.UNIT_ABBR
                    FROM ITEMS i
                    LEFT JOIN UNITS u ON i.UNIT_ID = u.UNIT_ID
                    WHERE i.ITEM_TYPE_ID = :type_id AND i.STATUS = 0 FOR UPDATE"; 
        
        $get_stmt = $conn->prepare($get_sql);
        $get_stmt->execute([':type_id' => $ITEM_TYPE_ID]);
        $pending_items = $get_stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($pending_items) == 0) {
            $conn->rollBack(); 
            echo json_encode(['success' => false, 'message' => 'No pending items to confirm.']);
            exit;
        }

        $log_item_names = [];
        $total_processed_items = 0;

        // UPDATED FIX: Using MySQL NULL-safe equal operator <=>
        $check_inv = $conn->prepare("SELECT SUPPLY_ID FROM VITAMINS_SUPPLEMENTS 
                                     WHERE SUPPLY_NAME = :name 
                                     AND UNIT_ID = :unit 
                                     AND EXPIRATION_DATE = :expiry 
                                     AND LOCATION_ID <=> :location
                                     FOR UPDATE");
                                     
        $update_inv = $conn->prepare("UPDATE VITAMINS_SUPPLEMENTS 
                                      SET TOTAL_STOCK = TOTAL_STOCK + :qty, 
                                          TOTAL_COST = TOTAL_COST + :cost,
                                          DATE_UPDATED = NOW() 
                                      WHERE SUPPLY_ID = :id");
                                      
        $insert_inv = $conn->prepare("INSERT INTO VITAMINS_SUPPLEMENTS 
                                      (SUPPLY_NAME, TOTAL_STOCK, TOTAL_COST, UNIT_ID, EXPIRATION_DATE, LOCATION_ID, DATE_CREATED, DATE_UPDATED) 
                                      VALUES (:name, :qty, :cost, :unit, :expiry, :location, NOW(), NOW())");

        foreach ($pending_items as $item) {
            $name = $item['ITEM_NAME'];
            $qty = (float)$item['QUANTITY'];
            $unit = $item['UNIT_ID'];
            $net_weight = (float)$item['ITEM_NET_WEIGHT'];
            $cost = (float)$item['TOTAL_COST'];
            $unit_abbr = strtoupper($item['UNIT_ABBR']);
            $location = $item['LOCATION_ID'];
            
            $expiry = $item['EXPIRATION_DATE'];
            if (empty($expiry)) {
                $expiry = date('Y-m-d', strtotime('+3 months'));
            }
            
            $stock_to_add = $qty; 
            if ($unit_abbr === 'ML' || $unit_abbr === 'L') {
                $stock_to_add = $net_weight > 0 ? ($net_weight * $qty) : $qty;
            } 

            $log_item_names[] = "$name (Qty: $stock_to_add, Exp: $expiry)";

            $check_inv->execute([
                ':name' => $name, 
                ':unit' => $unit,
                ':expiry' => $expiry,
                ':location' => $location
            ]);
            $inv_row = $check_inv->fetch(PDO::FETCH_ASSOC);

            if ($inv_row) {
                $update_inv->execute([
                    ':qty' => $stock_to_add,
                    ':cost' => $cost,
                    ':id' => $inv_row['SUPPLY_ID']
                ]);
            } else {
                $insert_inv->execute([
                    ':name' => $name,
                    ':qty' => $stock_to_add,
                    ':cost' => $cost,
                    ':unit' => $unit,
                    ':expiry' => $expiry,
                    ':location' => $location
                ]);
            }
            $total_processed_items++;
        }

        $update_sql = "UPDATE ITEMS SET STATUS = 1, DATE_UPDATED = NOW() WHERE ITEM_TYPE_ID = :type_id AND STATUS = 0";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->execute([':type_id' => $ITEM_TYPE_ID]);
        $count = $update_stmt->rowCount();
            
        $item_list = implode(", ", $log_item_names);
        if (strlen($item_list) > 3800) {
            $item_list = substr($item_list, 0, 3750) . "... [truncated]";
        }
        
        $logDetails = "Bulk confirmed $count Vitamin items (Synced to Inventory with Cost, Expiry & Location): " . $item_list;
        
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
        
        $conn->commit(); 
        
        echo json_encode([
            'success' => true, 
            'message' => "  Successfully confirmed $count items and updated inventory costs."
        ]);

    } catch (Exception $e) {
        if (isset($conn) && $conn->inTransaction()) {
            $conn->rollBack();
        }
        echo json_encode(['success' => false, 'message' => '  Error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}
?>