<?php
// process/editFeedTransaction.php
session_start();
header('Content-Type: application/json');
require_once '../config/Connection.php';

$user_id = $_SESSION['user']['USER_ID'] ?? null;
$username = $_SESSION['user']['FULL_NAME'] ?? 'System';
$ip = $_SERVER['REMOTE_ADDR'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid Request']); exit;
}

try {
    $ft_id = $_POST['ft_id'];
    $new_qty = floatval($_POST['quantity_kg']);
    $new_remarks = $_POST['remarks'];
    
    if ($new_qty <= 0) throw new Exception("Quantity must be greater than 0");

    $conn->beginTransaction();

    // 1. Get Old Transaction Details (Lock Row)
    $stmt = $conn->prepare("SELECT ft.*, f.FEED_NAME, f.TOTAL_WEIGHT_KG, f.TOTAL_COST, ar.TAG_NO 
                            FROM FEED_TRANSACTIONS ft 
                            JOIN FEEDS f ON ft.FEED_ID = f.FEED_ID 
                            JOIN ANIMAL_RECORDS ar ON ft.ANIMAL_ID = ar.ANIMAL_ID
                            WHERE ft.FT_ID = ? FOR UPDATE");
    $stmt->execute([$ft_id]);
    $old_txn = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$old_txn) throw new Exception("Transaction not found");

    $feed_id = $old_txn['FEED_ID'];
    $animal_id = $old_txn['ANIMAL_ID'];
    $old_qty = floatval($old_txn['QUANTITY_KG']);
    $current_stock = floatval($old_txn['TOTAL_WEIGHT_KG']);
    $current_total_cost = floatval($old_txn['TOTAL_COST']);
    $txn_date = $old_txn['TRANSACTION_DATE']; // Use original date for matching

    // 2. Calculate Stock Difference
    $qty_diff = $new_qty - $old_qty;

    if ($qty_diff > 0 && $current_stock < $qty_diff) {
        throw new Exception("Insufficient stock to increase quantity. Available extra: " . $current_stock);
    }

    // 3. Calculate Cost Adjustment
    $cost_per_kg = ($current_stock > 0) ? ($current_total_cost / $current_stock) : 0;
    
    // Recalculate specific transaction cost
    $new_txn_cost = $new_qty * $cost_per_kg;

    // 4. Update Inventory
    $new_stock_total = $current_stock - $qty_diff;
    $cost_diff = $qty_diff * $cost_per_kg; 
    $new_total_cost_inventory = $current_total_cost - $cost_diff;

    $upd_stock = $conn->prepare("UPDATE FEEDS SET TOTAL_WEIGHT_KG = ?, TOTAL_COST = ?, DATE_UPDATED = NOW() WHERE FEED_ID = ?");
    $upd_stock->execute([$new_stock_total, $new_total_cost_inventory, $feed_id]);

    // 5. Update Feed Transaction
    $upd_txn = $conn->prepare("UPDATE FEED_TRANSACTIONS SET QUANTITY_KG = ?, TRANSACTION_COST = ?, REMARKS = ? WHERE FT_ID = ?");
    $upd_txn->execute([$new_qty, $new_txn_cost, $new_remarks, $ft_id]);

    // ---------------------------------------------------------
    // 6. SYNC OPERATIONAL COST (NEW)
    // ---------------------------------------------------------
    // We link via description string or date+animal match if ID not available.
    // Construct the description key used during insertion.
    // Ideally, we should store OP_COST_ID in FEED_TRANSACTIONS, but assuming loose coupling:
    
    // Note: The original description format was "Feed: [Name] ([Qty]kg)"
    // Since Qty changes, the description changes. We must find the record by Date + Animal + Description 'LIKE' Feed Name
    
    $feed_name = $old_txn['FEED_NAME'];
    $op_search_term = "Feed: " . $feed_name . "%"; 

    // Find the matching operational entry
    $findOp = $conn->prepare("SELECT op_cost_id FROM operational_cost 
                              WHERE animal_id = ? 
                              AND datetime_created = ? 
                              AND description LIKE ? 
                              LIMIT 1");
    $findOp->execute([$animal_id, $txn_date, $op_search_term]);
    $op_row = $findOp->fetch(PDO::FETCH_ASSOC);

    $new_op_desc = "Feed: " . $feed_name . " (" . $new_qty . "kg)";

    if ($op_row) {
        // Update existing
        $upOp = $conn->prepare("UPDATE operational_cost SET operation_cost = ?, description = ? WHERE op_cost_id = ?");
        $upOp->execute([$new_txn_cost, $new_op_desc, $op_row['op_cost_id']]);
    } else {
        // Insert if missing (e.g. data correction)
        $inOp = $conn->prepare("INSERT INTO operational_cost (animal_id, operation_cost, description, datetime_created) VALUES (?, ?, ?, ?)");
        $inOp->execute([$animal_id, $new_txn_cost, $new_op_desc, $txn_date]);
    }

    // 7. Audit Log
    $log_msg = "Edited Feed Txn (Tag: {$old_txn['TAG_NO']}). Qty changed from $old_qty kg to $new_qty kg.";
    $audit = $conn->prepare("INSERT INTO AUDIT_LOGS (USER_ID, USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS) VALUES (?, ?, 'EDIT_FEED', 'FEED_TRANSACTIONS', ?, ?)");
    $audit->execute([$user_id, $username, $log_msg, $ip]);

    $conn->commit();
    echo json_encode(['success' => true, 'message' => "Transaction updated successfully."]);

} catch (Exception $e) {
    if($conn->inTransaction()) $conn->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>