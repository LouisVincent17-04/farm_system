<?php
// purchase_confirmations/confirmAllVaccines.php
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

        $ITEM_TYPE_ID = 11; // Vaccines

        // Start Transaction
        $conn->beginTransaction();

        // 1. Fetch Item Names for the Log (Locking rows being processed)
        $check_sql = "SELECT ITEM_NAME, IFNULL(EXPIRATION_DATE, 'No Expiry') as EXP_LABEL FROM ITEMS WHERE ITEM_TYPE_ID = :type_id AND STATUS = 0 FOR UPDATE";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->execute([':type_id' => $ITEM_TYPE_ID]);
        
        $pending_items = $check_stmt->fetchAll(PDO::FETCH_ASSOC); 
        $count = count($pending_items);

        if ($count == 0) {
            $conn->rollBack(); // Release lock
            echo json_encode(['success' => false, 'message' => 'No pending vaccine purchases to confirm.']);
            exit;
        }
        
        $log_item_names = [];
        foreach ($pending_items as $p) {
            $log_item_names[] = $p['ITEM_NAME'] . " [" . $p['EXP_LABEL'] . "]";
        }

        // 2. MERGE INTO VACCINES (Upsert Logic)
        
        // First, get the aggregated data including COST, EXPIRY, and LOCATION_ID
        $agg_sql = "SELECT 
                        ITEM_NAME, 
                        UNIT_ID,
                        LOCATION_ID,
                        -- Use specific date, or default to 12 months from now if NULL
                        IFNULL(EXPIRATION_DATE, DATE_ADD(NOW(), INTERVAL 12 MONTH)) as EXP_DATE,
                        -- Sum up the Total Cost column from ITEMS table
                        SUM(IFNULL(TOTAL_COST, 0)) AS SUM_COST,
                        -- Logic: If Net Weight exists, multiply by Qty. Else assume Qty is the weight.
                        SUM(IFNULL(QUANTITY, 0) * IFNULL(ITEM_NET_WEIGHT, 1)) AS SUM_STOCK
                    FROM ITEMS 
                    WHERE ITEM_TYPE_ID = :type_id AND STATUS = 0 
                    GROUP BY ITEM_NAME, UNIT_ID, LOCATION_ID, EXPIRATION_DATE";
        
        $agg_stmt = $conn->prepare($agg_sql);
        $agg_stmt->execute([':type_id' => $ITEM_TYPE_ID]);
        $aggregated_data = $agg_stmt->fetchAll(PDO::FETCH_ASSOC);

        // Perform the Upsert
        
        // Prepare statements outside loop for efficiency
        // UPDATED FIX: Using MySQL NULL-safe equal operator <=>
        $check_inv = $conn->prepare("SELECT SUPPLY_ID FROM VACCINES 
                                     WHERE SUPPLY_NAME = :name 
                                     AND UNIT_ID = :unit 
                                     AND EXPIRATION_DATE = :expiry 
                                     AND LOCATION_ID <=> :location
                                     FOR UPDATE");
                                     
        $update_inv = $conn->prepare("UPDATE VACCINES 
                                      SET TOTAL_STOCK = TOTAL_STOCK + :stock, 
                                          TOTAL_COST = TOTAL_COST + :cost,
                                          DATE_UPDATED = NOW() 
                                      WHERE SUPPLY_ID = :id");
                                      
        $insert_inv = $conn->prepare("INSERT INTO VACCINES (SUPPLY_NAME, TOTAL_STOCK, TOTAL_COST, UNIT_ID, EXPIRATION_DATE, LOCATION_ID, DATE_CREATED, DATE_UPDATED)
                                      VALUES (:name, :stock, :cost, :unit, :expiry, :location, NOW(), NOW())");

        foreach ($aggregated_data as $row) {
            $name = $row['ITEM_NAME'];
            $unit = $row['UNIT_ID'];
            $stock = $row['SUM_STOCK'];
            $cost = $row['SUM_COST'];
            $expiry = $row['EXP_DATE'];
            $location = $row['LOCATION_ID'];

            // Check if exact batch & location exists
            $check_inv->execute([
                ':name' => $name, 
                ':unit' => $unit, 
                ':expiry' => $expiry,
                ':location' => $location
            ]);
            $existing = $check_inv->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                // Update
                $update_inv->execute([
                    ':stock' => $stock,
                    ':cost' => $cost,
                    ':id' => $existing['SUPPLY_ID']
                ]);
            } else {
                // Insert
                $insert_inv->execute([
                    ':name' => $name,
                    ':stock' => $stock,
                    ':cost' => $cost,
                    ':unit' => $unit,
                    ':expiry' => $expiry,
                    ':location' => $location
                ]);
            }
        }

        // 3. Update Status in ITEMS table to '1' (Confirmed)
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
        
        $logDetails = "Bulk confirmed $count Vaccine items (Merged into Inventory with Expiry & Location): " . $item_list;
        

        $log_sql = "INSERT INTO AUDIT_LOGS 
                    (USER_ID, USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS) 
                    VALUES 
                    (:user_id, :username, 'BULK_CONFIRM_VACCINE', 'ITEMS/VACCINES', :details, :ip)";
        
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
            'message' => "  Successfully added to vaccine inventory. ($affected_rows items processed)"
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
        'message' => 'Invalid request method.'
    ]);
}
?>