<?php
// process/confirmFeedAndFeedingSupplies.php
session_start(); // 1. Start Session
error_reporting(0);
ini_set('display_errors', 0);
include '../config/Connection.php';

header('Content-Type: application/json');

// Get User Info (Safe Fallback)
$user_id = !empty($_SESSION['user']['USER_ID']) ? $_SESSION['user']['USER_ID'] : null;
$username = $_SESSION['user']['FULL_NAME'] ?? 'System';
$ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';

// Helper to normalize weight to KG
function getWeightInKg($conn, $unit_id, $qty, $net_weight) {
    // Default to base calculation
    $base_weight = ($net_weight > 0) ? ($qty * $net_weight) : $qty;

    $unit_sql = "SELECT UNIT_NAME FROM UNITS WHERE UNIT_ID = :id";
    $stmt = $conn->prepare($unit_sql);
    $stmt->execute([':id' => $unit_id]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $name = strtolower($u['UNIT_NAME'] ?? '');
    
    // Check if unit is grams, convert to KG
    // Logic: contains 'gram' but not 'kilo' (to avoid kilogram)
    if (strpos($name, 'gram') !== false && strpos($name, 'kilo') === false) {
        return $base_weight / 1000;
    }
    
    return $base_weight;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['item_id'])) {
    
    $item_id = $_POST['item_id'];

    try {
        if (!isset($conn)) {
            throw new Exception("Database connection failed.");
        }

        // Start Transaction
        $conn->beginTransaction();

        // 1. Fetch and Lock the Pending Item
        $item_sql = "SELECT ITEM_NAME, UNIT_ID, QUANTITY, ITEM_NET_WEIGHT, TOTAL_COST, LOCATION_ID, ITEM_TYPE_ID 
                     FROM ITEMS 
                     WHERE ITEM_ID = :id AND STATUS = 0 FOR UPDATE";
        $item_stmt = $conn->prepare($item_sql);
        $item_stmt->execute([':id' => $item_id]);
        $row = $item_stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            $conn->rollBack(); // Release lock
            throw new Exception("Item not found or already confirmed.");
        }
        
        if ((int)$row['ITEM_TYPE_ID'] !== 2) { // Item Type ID for Feeds is 2
             $conn->rollBack();
             throw new Exception("Item is not a Feed item.");
        }

        // 2. Calculate Total Weight in KG (using helper)
        $qty = floatval($row['QUANTITY']);
        $net_weight = floatval($row['ITEM_NET_WEIGHT'] ?? 0);
        $total_weight_kg = getWeightInKg($conn, $row['UNIT_ID'], $qty, $net_weight);
        $total_cost_add = floatval($row['TOTAL_COST']);
        $item_name = $row['ITEM_NAME'];
        $location_id = $row['LOCATION_ID'];

        // --- Execute Upsert Logic ---
        
        // Check if Feed exists in Inventory for this location
        $check_feed = "SELECT FEED_ID FROM FEEDS WHERE FEED_NAME = :name AND LOCATION_ID = :loc_id FOR UPDATE";
        $check_stmt = $conn->prepare($check_feed);
        $check_stmt->execute([
            ':name' => $item_name,
            ':loc_id' => $location_id
        ]);
        $existing = $check_stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            // UPDATE existing record
            $feed_id = $existing['FEED_ID'];
            
            $update_feed = "UPDATE FEEDS 
                            SET TOTAL_WEIGHT_KG = TOTAL_WEIGHT_KG + :w, 
                                TOTAL_COST = TOTAL_COST + :c, 
                                DATE_UPDATED = NOW() 
                            WHERE FEED_ID = :fid";
            
            $upd_stmt = $conn->prepare($update_feed);
            $upd_stmt->execute([
                ':w' => $total_weight_kg,
                ':c' => $total_cost_add,
                ':fid' => $feed_id
            ]);

        } else {
            // INSERT new record
            $insert_feed = "INSERT INTO FEEDS (FEED_NAME, TOTAL_WEIGHT_KG, TOTAL_COST, LOCATION_ID, DATE_CREATED, DATE_UPDATED) 
                            VALUES (:name, :w, :c, :loc, NOW(), NOW())";
            
            $ins_stmt = $conn->prepare($insert_feed);
            $ins_stmt->execute([
                ':name' => $item_name,
                ':w' => $total_weight_kg,
                ':c' => $total_cost_add,
                ':loc' => $location_id
            ]);
        }

        // 3. Lock the Purchase Record (Status = 1) (Added DATE_UPDATED = NOW())
        $update_status = "UPDATE ITEMS SET STATUS = 1, DATE_UPDATED = NOW() WHERE ITEM_ID = :id AND STATUS = 0";
        $status_stmt = $conn->prepare($update_status);
        $status_stmt->execute([':id' => $item_id]);
            
        // --- 4. AUDIT LOGGING ---
        
        $logDetails = "Confirmed Feed Purchase (ID: $item_id): " . $item_name . " (Added $total_weight_kg kg to Inventory)";
        

        $log_sql = "INSERT INTO AUDIT_LOGS 
                    (USER_ID, USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS) 
                    VALUES 
                    (:user_id, :username, 'CONFIRM_FEED', 'ITEMS/FEEDS', :details, :ip)";
        
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
            'message' => '✅ Purchase confirmed. Inventory updated successfully.'
        ]);

    } catch (Exception $e) {
        if (isset($conn) && $conn->inTransaction()) {
            $conn->rollBack();
        }
        echo json_encode(['success' => false, 'message' => '❌ Error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method or Item ID is missing.']);
}
?>