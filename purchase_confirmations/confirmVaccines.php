<?php
// purchase_confirmations/confirmVaccine.php
session_start(); // 1. Start Session
error_reporting(0);
ini_set('display_errors', 0);
include '../config/Connection.php';

header('Content-Type: application/json');

// Get User Info (Safe Fallback)
$user_id = !empty($_SESSION['user']['USER_ID']) ? $_SESSION['user']['USER_ID'] : null;
$username = $_SESSION['user']['FULL_NAME'] ?? 'System';
$ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['item_id'])) {
    
    $item_id = $_POST['item_id'];

    try {
        if (!isset($conn)) {
            throw new Exception("Database connection failed.");
        }

        $ITEM_TYPE_ID = 11; // Vaccine

        // Start Transaction
        $conn->beginTransaction();

        // 1. Validate item exists AND fetch Name/Unit/Cost for the Upsert/Log
        // UPDATED: Added TOTAL_COST to the select list
        $check_sql = "SELECT ITEM_NAME, UNIT_ID, QUANTITY, ITEM_NET_WEIGHT, TOTAL_COST 
                      FROM ITEMS 
                      WHERE ITEM_ID = :id AND ITEM_TYPE_ID = :type_id AND STATUS = 0 FOR UPDATE";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->execute([
            ':id' => $item_id,
            ':type_id' => $ITEM_TYPE_ID
        ]);
        
        $item_row = $check_stmt->fetch(PDO::FETCH_ASSOC);

        if (!$item_row) {
            $conn->rollBack(); // Release lock
            throw new Exception("Item not found, not a Vaccine type, or already confirmed.");
        }
        
        $item_name = $item_row['ITEM_NAME'];
        $unit_id = $item_row['UNIT_ID'];
        $total_cost = $item_row['TOTAL_COST'] ?? 0; // Get the cost value
        
        // Calculate Stock to Add: Quantity * Net Weight (if exists), default to just Quantity
        $stock_to_add = $item_row['QUANTITY'] * ($item_row['ITEM_NET_WEIGHT'] ?: 1);

        // 2. MERGE INTO VACCINES (Inventory Logic)
        // UPDATED: Added TOTAL_COST logic to INSERT and ON DUPLICATE KEY UPDATE
        $upsert_sql = "INSERT INTO VACCINES (SUPPLY_NAME, TOTAL_STOCK, TOTAL_COST, UNIT_ID, DATE_CREATED, DATE_UPDATED)
                        VALUES (:name, :stock, :cost, :unit, NOW(), NOW())
                        ON DUPLICATE KEY UPDATE
                        TOTAL_STOCK = TOTAL_STOCK + VALUES(TOTAL_STOCK),
                        TOTAL_COST = TOTAL_COST + VALUES(TOTAL_COST),
                        DATE_UPDATED = NOW()";
                        
        $upsert_stmt = $conn->prepare($upsert_sql);
        $upsert_stmt->execute([
            ':name' => $item_name,
            ':stock' => $stock_to_add,
            ':cost' => $total_cost, // Add the cost value
            ':unit' => $unit_id
        ]);

        // 3. Update Status to Confirmed
        $update_sql = "UPDATE ITEMS SET STATUS = 1, DATE_UPDATED = NOW() WHERE ITEM_ID = :id AND STATUS = 0";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->execute([':id' => $item_id]);
            
        // --- 4. AUDIT LOGGING ---
        $logDetails = "Confirmed Vaccine Purchase (ID: $item_id): $item_name. Added Stock: $stock_to_add. Added Value: $total_cost";

        $log_sql = "INSERT INTO AUDIT_LOGS 
                    (USER_ID, USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS) 
                    VALUES 
                    (:user_id, :username, 'CONFIRM_VACCINE', 'ITEMS/VACCINES', :details, :ip)";
        
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
            'message' => '✅ Purchase confirmed. Inventory updated with cost.'
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
        'message' => 'Invalid request method or Item ID is missing.'
    ]);
}
?>