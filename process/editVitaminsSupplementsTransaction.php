<?php
session_start(); // 1. Start Session (Added)
header('Content-Type: application/json');
error_reporting(0);
ini_set('display_errors', 0);
require_once '../config/Connection.php';

// Get User Info (Safe Fallback)
$user_id = !empty($_SESSION['user']['USER_ID']) ? $_SESSION['user']['USER_ID'] : null;
$username = $_SESSION['user']['FULL_NAME'] ?? 'System';
$ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $vst_id   = $_POST['vst_id'];
    $item_id  = $_POST['item_id']; // New Item ID
    $qty      = floatval($_POST['quantity_used']); // New Quantity
    $animal_id = $_POST['animal_id'];
    $date     = $_POST['transaction_date'];
    $dosage   = $_POST['dosage'];
    $remarks  = $_POST['remarks'];

    try {
        if (!isset($conn)) {
            throw new Exception("Database connection failed.");
        }

        // ---------------------------------------------------------
        // START TRANSACTION
        // ---------------------------------------------------------
        $conn->beginTransaction();

        // 1. Get Old Transaction Data (for Audit Log and Refund)
        // Using FOR UPDATE to lock the row
        $old_trans_sql = "SELECT VST.ITEM_ID, VST.QUANTITY_USED, AR.TAG_NO
                          FROM VITAMINS_SUPPLEMENTS_TRANSACTIONS VST
                          JOIN ANIMAL_RECORDS AR ON VST.ANIMAL_ID = AR.ANIMAL_ID
                          WHERE VST.VST_ID = :id FOR UPDATE";
        
        $old_stmt = $conn->prepare($old_trans_sql);
        $old_stmt->execute([':id' => $vst_id]);
        $oldRow = $old_stmt->fetch(PDO::FETCH_ASSOC);

        if (!$oldRow) {
            throw new Exception("Transaction record not found.");
        }

        $old_item_id = $oldRow['ITEM_ID'];
        $old_qty_used = floatval($oldRow['QUANTITY_USED']);
        $animal_tag = $oldRow['TAG_NO'];

        // 2. Refund Old Stock (to the old item, regardless if the item changed)
        // Note: Replaced SYSDATE with NOW()
        $refund_sql = "UPDATE VITAMINS_SUPPLEMENTS 
                       SET TOTAL_STOCK = TOTAL_STOCK + :qty, 
                           DATE_UPDATED = NOW() 
                       WHERE SUPPLY_ID = :id";
        
        $refund_stmt = $conn->prepare($refund_sql);
        
        if (!$refund_stmt->execute([':qty' => $old_qty_used, ':id' => $old_item_id])) {
            throw new Exception("Failed to refund old stock.");
        }

        // 3. Check New Stock and Get Supply Name (for Audit Log)
        $check_sql = "SELECT TOTAL_STOCK, SUPPLY_NAME FROM VITAMINS_SUPPLEMENTS WHERE SUPPLY_ID = :id FOR UPDATE";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->execute([':id' => $item_id]);
        $stock = $check_stmt->fetch(PDO::FETCH_ASSOC);

        if (!$stock) {
             throw new Exception("New Vitamins/Supplements item not found.");
        }
        
        $current_stock = floatval($stock['TOTAL_STOCK']);
        $supply_name = $stock['SUPPLY_NAME'];

        if ($current_stock < $qty) {
            throw new Exception("Insufficient stock for update. Available: $current_stock");
        }

        // 4. Deduct New Stock
        $deduct_sql = "UPDATE VITAMINS_SUPPLEMENTS 
                       SET TOTAL_STOCK = TOTAL_STOCK - :qty, 
                           DATE_UPDATED = NOW() 
                       WHERE SUPPLY_ID = :id";
        
        $deduct_stmt = $conn->prepare($deduct_sql);
        
        if (!$deduct_stmt->execute([':qty' => $qty, ':id' => $item_id])) {
            throw new Exception("Failed to deduct new stock.");
        }

        // 5. Update Transaction Record
        // Note: Removed TO_DATE, MySQL accepts the string 'YYYY-MM-DD' directly
        $sql = "UPDATE VITAMINS_SUPPLEMENTS_TRANSACTIONS SET 
                ANIMAL_ID=:aid, 
                ITEM_ID=:iid, 
                DOSAGE=:dos, 
                QUANTITY_USED=:qty_new, 
                TRANSACTION_DATE=:dt, 
                REMARKS=:rem,
                DATE_UPDATED = NOW()
                WHERE VST_ID=:vid";
        
        $update_stmt = $conn->prepare($sql);
        $update_params = [
            ':aid'     => $animal_id,
            ':iid'     => $item_id,
            ':dos'     => $dosage,
            ':qty_new' => $qty,
            ':dt'      => $date,
            ':rem'     => $remarks,
            ':vid'     => $vst_id
        ];

        if (!$update_stmt->execute($update_params)) {
            throw new Exception("Failed to update transaction record.");
        }

        // 6. INSERT AUDIT LOG
        $logDetails = "Edited V/S Transaction (ID: $vst_id) for Animal $animal_tag. Item: $supply_name. Qty: $old_qty_used -> $qty.";
        
        $log_sql = "INSERT INTO AUDIT_LOGS 
                    (USER_ID, USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS) 
                    VALUES 
                    (:user_id, :username, 'EDIT_VST_TXN', 'VITAMINS_SUPPLEMENTS_TRANSACTIONS', :details, :ip)";
        
        $log_stmt = $conn->prepare($log_sql);
        $log_params = [
            ':user_id'  => $user_id,
            ':username' => $username,
            ':details'  => $logDetails,
            ':ip'       => $ip_address
        ];
        
        if (!$log_stmt->execute($log_params)) {
            throw new Exception("Audit Log Failed.");
        }

        // 7. COMMIT EVERYTHING
        $conn->commit();
        echo json_encode(['success' => true, 'message' => '✅ Record Updated']);
        
    } catch (Exception $e) {
        // Rollback on error
        if (isset($conn) && $conn->inTransaction()) {
            $conn->rollBack();
        }
        echo json_encode(['success' => false, 'message' => '❌ ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => '❌ Invalid request method.']);
}
?>