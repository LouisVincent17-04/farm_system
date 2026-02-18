<?php
// process/reverseVitaminTransaction.php
session_start();
header('Content-Type: application/json');
require_once '../config/Connection.php';

// User Context
$user_id = $_SESSION['user']['USER_ID'] ?? 1;
$username = $_SESSION['user']['FULL_NAME'] ?? 'System';
$ip = $_SERVER['REMOTE_ADDR'];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Invalid Request Method.");
    }

    $conn->beginTransaction();

    // 1. Find the Most Recent Vitamin Transaction
    $sql = "SELECT vt.*, v.SUPPLY_NAME 
            FROM vitamins_supplements_transactions vt
            LEFT JOIN vitamins_supplements v ON vt.ITEM_ID = v.SUPPLY_ID
            ORDER BY vt.VST_ID DESC LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $last_trans = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$last_trans) {
        throw new Exception("No vitamin transactions found to reverse.");
    }

    $vst_id = $last_trans['VST_ID'];
    $item_id = $last_trans['ITEM_ID'];
    $animal_id = $last_trans['ANIMAL_ID'];
    $restore_qty = $last_trans['QUANTITY_USED'];
    $restore_cost = $last_trans['TOTAL_COST'];
    $trans_date = $last_trans['TRANSACTION_DATE'];

    // 2. Restore Inventory (Vitamins Table)
    // Increase Stock and Total Value back to what it was
    $updateInv = $conn->prepare("UPDATE vitamins_supplements 
                                 SET TOTAL_STOCK = TOTAL_STOCK + ?, 
                                     TOTAL_COST = TOTAL_COST + ?, 
                                     DATE_UPDATED = NOW() 
                                 WHERE SUPPLY_ID = ?");
    $updateInv->execute([$restore_qty, $restore_cost, $item_id]);

    // 3. Remove Financial Impact (Operational Cost)
    // Match by Animal ID and exact Timestamp
    $deleteOp = $conn->prepare("DELETE FROM operational_cost 
                                WHERE animal_id = ? 
                                AND datetime_created = ?");
    $deleteOp->execute([$animal_id, $trans_date]);

    // 4. Delete the Transaction Record
    $deleteTrans = $conn->prepare("DELETE FROM vitamins_supplements_transactions WHERE VST_ID = ?");
    $deleteTrans->execute([$vst_id]);

    // 5. Audit Log
    $details = "Reversed Vitamin Transaction #$vst_id. Restored $restore_qty units of '{$last_trans['SUPPLY_NAME']}'.";
    $audit = $conn->prepare("INSERT INTO audit_logs (USER_ID, USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS) 
                             VALUES (?, ?, 'REVERSE_VITAMIN', 'VITAMINS_TRANSACTIONS', ?, ?)");
    $audit->execute([$user_id, $username, $details, $ip]);

    $conn->commit();

    echo json_encode([
        'success' => true, 
        'message' => "Reversal Successful! Restored {$last_trans['SUPPLY_NAME']} stock."
    ]);

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}
?>