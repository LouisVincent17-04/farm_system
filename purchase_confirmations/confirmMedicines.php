<?php
// purchase_confirmations/confirmMedicines.php
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

        $ITEM_TYPE_ID = 1; // Medicine

        $conn->beginTransaction();

        // 1. Validate item exists & fetch Data
        $check_sql = "SELECT i.ITEM_NAME, i.UNIT_ID, i.QUANTITY, i.ITEM_NET_WEIGHT, i.TOTAL_COST, i.EXPIRATION_DATE, i.LOCATION_ID
                      FROM ITEMS i
                      WHERE i.ITEM_ID = :id AND i.ITEM_TYPE_ID = :type_id AND i.STATUS = 0 FOR UPDATE";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->execute([
            ':id' => $item_id,
            ':type_id' => $ITEM_TYPE_ID
        ]);
        
        $item_row = $check_stmt->fetch(PDO::FETCH_ASSOC);

        if (!$item_row) {
            $conn->rollBack();
            throw new Exception("Item not found, not a Medicine type, or already confirmed.");
        }
        
        $item_name = $item_row['ITEM_NAME'];
        $unit_id = $item_row['UNIT_ID'];
        $location_id = $item_row['LOCATION_ID'];
        $total_cost = $item_row['TOTAL_COST'] ?? 0;
        
        // Handle Expiration: Use specific date or default to +6 months if NULL
        $expiration_date = $item_row['EXPIRATION_DATE'];
        if (empty($expiration_date)) {
            $expiration_date = date('Y-m-d', strtotime('+6 months')); 
        }
        
        // Calculate Stock to Add: Quantity * Net Weight (if exists), default to just Quantity
        $stock_to_add = $item_row['QUANTITY'] * ($item_row['ITEM_NET_WEIGHT'] ?: 1);

        // 2. SYNC INTO MEDICINES (Inventory Logic)
        // UPDATED FIX: Using MySQL NULL-safe equal operator <=>
        $inv_sql = "SELECT SUPPLY_ID FROM MEDICINES 
                    WHERE SUPPLY_NAME = :name 
                    AND UNIT_ID = :unit 
                    AND EXPIRATION_DATE = :expiry
                    AND LOCATION_ID <=> :location
                    FOR UPDATE";
        
        $inv_stmt = $conn->prepare($inv_sql);
        $inv_stmt->execute([
            ':name' => $item_name,
            ':unit' => $unit_id,
            ':expiry' => $expiration_date,
            ':location' => $location_id
        ]);
        $existing_inv = $inv_stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing_inv) {
            // UPDATE existing inventory (Same Batch & Location)
            $update_inv = "UPDATE MEDICINES 
                           SET TOTAL_STOCK = TOTAL_STOCK + :qty, 
                               TOTAL_COST = TOTAL_COST + :cost,
                               DATE_UPDATED = NOW() 
                           WHERE SUPPLY_ID = :id";
            $upd_stmt = $conn->prepare($update_inv);
            $upd_stmt->execute([
                ':qty' => $stock_to_add,
                ':cost' => $total_cost,
                ':id' => $existing_inv['SUPPLY_ID']
            ]);
        } else {
            // INSERT new inventory (New Batch/Expiry or Location)
            $insert_inv = "INSERT INTO MEDICINES (SUPPLY_NAME, TOTAL_STOCK, TOTAL_COST, UNIT_ID, EXPIRATION_DATE, LOCATION_ID, DATE_CREATED, DATE_UPDATED) 
                           VALUES (:name, :qty, :cost, :unit, :expiry, :location, NOW(), NOW())";
            $ins_stmt = $conn->prepare($insert_inv);
            $ins_stmt->execute([
                ':name' => $item_name,
                ':qty' => $stock_to_add,
                ':cost' => $total_cost,
                ':unit' => $unit_id,
                ':expiry' => $expiration_date,
                ':location' => $location_id
            ]);
        }

        // 3. Update Status to Confirmed
        $update_sql = "UPDATE ITEMS SET STATUS = 1, DATE_UPDATED = NOW() WHERE ITEM_ID = :id AND STATUS = 0";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->execute([':id' => $item_id]);
            
        // --- 4. AUDIT LOGGING ---
        $logDetails = "Confirmed Medicine Purchase (ID: $item_id): $item_name. Added Stock: $stock_to_add. Expiry: $expiration_date. Value: $total_cost. Location ID: " . ($location_id ?: 'None');

        $log_sql = "INSERT INTO AUDIT_LOGS 
                    (USER_ID, USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS) 
                    VALUES 
                    (:user_id, :username, 'CONFIRM_MEDICINE', 'ITEMS/MEDICINES', :details, :ip)";
        
        $log_stmt = $conn->prepare($log_sql);
        $log_stmt->execute([
            ':user_id' => $user_id,
            ':username' => $username,
            ':details' => $logDetails,
            ':ip' => $ip_address
        ]);

        // 5. Commit transaction
        $conn->commit();
        
        echo json_encode([
            'success' => true, 
            'message' => "  Purchase confirmed. Inventory updated (Expiry: $expiration_date)."
        ]);

    } catch (Exception $e) {
        if (isset($conn) && $conn->inTransaction()) {
            $conn->rollBack();
        }
        echo json_encode([
            'success' => false, 
            'message' => '  Error: ' . $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false, 
        'message' => 'Invalid request method or Item ID is missing.'
    ]);
}
?>