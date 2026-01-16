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
    $vst_id       = $_POST['vst_id'];
    $item_id      = $_POST['item_id']; // New Item ID
    $qty          = floatval($_POST['quantity_used']); // New Quantity
    $animal_id    = $_POST['animal_id'];
    $date         = $_POST['transaction_date'];
    $dosage       = $_POST['dosage'];
    $remarks      = $_POST['remarks'];

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
        $old_trans_sql = "SELECT VST.ITEM_ID, VST.QUANTITY_USED, VST.TOTAL_COST, VST.TRANSACTION_DATE, AR.TAG_NO
                          FROM VITAMINS_SUPPLEMENTS_TRANSACTIONS VST
                          JOIN ANIMAL_RECORDS AR ON VST.ANIMAL_ID = AR.ANIMAL_ID
                          WHERE VST.VST_ID = :id FOR UPDATE";
        
        $old_stmt = $conn->prepare($old_trans_sql);
        $old_stmt->execute([':id' => $vst_id]);
        $oldRow = $old_stmt->fetch(PDO::FETCH_ASSOC);

        if (!$oldRow) {
            throw new Exception("Transaction record not found.");
        }

        $old_item_id  = $oldRow['ITEM_ID'];
        $old_qty_used = floatval($oldRow['QUANTITY_USED']);
        $old_txn_cost = floatval($oldRow['TOTAL_COST']);
        $old_txn_date = $oldRow['TRANSACTION_DATE'];
        $animal_tag   = $oldRow['TAG_NO'];

        // 2. Refund Old Stock & Cost (to the old item)
        $refund_sql = "UPDATE VITAMINS_SUPPLEMENTS 
                       SET TOTAL_STOCK = TOTAL_STOCK + :qty, 
                           TOTAL_COST = TOTAL_COST + :cost,
                           DATE_UPDATED = NOW() 
                       WHERE SUPPLY_ID = :id";
        
        $refund_stmt = $conn->prepare($refund_sql);
        
        if (!$refund_stmt->execute([
            ':qty' => $old_qty_used, 
            ':cost' => $old_txn_cost,
            ':id' => $old_item_id
        ])) {
            throw new Exception("Failed to refund old stock.");
        }

        // 3. Check New Stock and Get Supply Name (for Audit Log)
        $check_sql = "SELECT TOTAL_STOCK, TOTAL_COST, SUPPLY_NAME FROM VITAMINS_SUPPLEMENTS WHERE SUPPLY_ID = :id FOR UPDATE";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->execute([':id' => $item_id]);
        $stock = $check_stmt->fetch(PDO::FETCH_ASSOC);

        if (!$stock) {
             throw new Exception("New Vitamins/Supplements item not found.");
        }
        
        $current_stock = floatval($stock['TOTAL_STOCK']);
        $current_value = floatval($stock['TOTAL_COST']);
        $supply_name   = $stock['SUPPLY_NAME'];

        if ($current_stock < $qty) {
            throw new Exception("Insufficient stock for update. Available: $current_stock");
        }

        // 4. Calculate New Cost
        // Unit Price = Total Value / Total Stock
        $unit_price   = ($current_stock > 0) ? ($current_value / $current_stock) : 0;
        $new_txn_cost = $unit_price * $qty;

        // 5. Deduct New Stock & Cost
        $deduct_sql = "UPDATE VITAMINS_SUPPLEMENTS 
                       SET TOTAL_STOCK = TOTAL_STOCK - :qty, 
                           TOTAL_COST = TOTAL_COST - :cost,
                           DATE_UPDATED = NOW() 
                       WHERE SUPPLY_ID = :id";
        
        $deduct_stmt = $conn->prepare($deduct_sql);
        
        if (!$deduct_stmt->execute([
            ':qty' => $qty, 
            ':cost' => $new_txn_cost,
            ':id' => $item_id
        ])) {
            throw new Exception("Failed to deduct new stock.");
        }

        // 6. Update Transaction Record
        $sql = "UPDATE VITAMINS_SUPPLEMENTS_TRANSACTIONS SET 
                ANIMAL_ID=:aid, 
                ITEM_ID=:iid, 
                DOSAGE=:dos, 
                QUANTITY_USED=:qty_new, 
                TOTAL_COST=:cost_new, 
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
            ':cost_new'=> $new_txn_cost,
            ':dt'      => $date,
            ':rem'     => $remarks,
            ':vid'     => $vst_id
        ];

        if (!$update_stmt->execute($update_params)) {
            throw new Exception("Failed to update transaction record.");
        }

        // ---------------------------------------------------------
        // 7. SYNC OPERATIONAL COST (NEW LOGIC)
        // ---------------------------------------------------------
        // Strategy: Link via description key or insert new if loose match not found
        // Desc Format: "Vitamin: [Name] (Qty: [Qty])"
        
        $op_desc_key = "Vitamin: %"; 
        
        // Find existing record by matching date/animal loosely
        // Or if we stored REF ID in description
        $checkOp = $conn->prepare("SELECT op_cost_id FROM operational_cost 
                                   WHERE animal_id = ? 
                                   AND datetime_created = ? 
                                   AND description LIKE ? 
                                   LIMIT 1");
        // Use OLD date/animal to find the record to update? 
        // For simplicity, let's assume loose match on date/animal is risky if multiple vit txns happen same day.
        // Best approach: If we didn't store ID, insert new and let old be (or delete if found).
        
        // Let's rely on constructing a unique enough description for future:
        $new_op_desc = "Vitamin: " . $supply_name . " (Qty: " . $qty . ") Ref: VST-" . $vst_id;
        
        // Check if we already updated it previously with this ref format?
        $checkRef = $conn->prepare("SELECT op_cost_id FROM operational_cost WHERE description LIKE ?");
        $checkRef->execute(["%Ref: VST-" . $vst_id . "%"]);
        $op_row = $checkRef->fetch(PDO::FETCH_ASSOC);

        if ($op_row) {
            // Update existing linked record
            $upOp = $conn->prepare("UPDATE operational_cost SET animal_id = ?, operation_cost = ?, description = ?, datetime_created = ? WHERE op_cost_id = ?");
            $upOp->execute([$animal_id, $new_txn_cost, $new_op_desc, $date, $op_row['op_cost_id']]);
        } else {
            // Insert new record if no link found
            if ($new_txn_cost > 0) {
                $inOp = $conn->prepare("INSERT INTO operational_cost (animal_id, operation_cost, description, datetime_created) VALUES (?, ?, ?, ?)");
                $inOp->execute([$animal_id, $new_txn_cost, $new_op_desc, $date]);
            }
        }

        // 8. INSERT AUDIT LOG
        $cost_display = number_format($new_txn_cost, 2);
        $logDetails = "Edited V/S Transaction (ID: $vst_id) for Animal $animal_tag. Item: $supply_name. Qty: $old_qty_used -> $qty. Cost: ₱$cost_display";
        
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

        // 9. COMMIT EVERYTHING
        $conn->commit();
        echo json_encode(['success' => true, 'message' => "✅ Record Updated. Cost: ₱$cost_display"]);
        
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