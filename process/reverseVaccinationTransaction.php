<?php
// process/reverseVaccinationTransaction.php
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

    // 1. Find the Most Recent Vaccination
    $sql = "SELECT v.*, vac.SUPPLY_NAME 
            FROM vaccination_records v
            LEFT JOIN vaccines vac ON v.ITEM_ID = vac.SUPPLY_ID
            ORDER BY v.VACCINATION_ID DESC LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $last_trans = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$last_trans) {
        throw new Exception("No vaccination records found to reverse.");
    }

    $vac_id = $last_trans['VACCINATION_ID'];
    $item_id = $last_trans['ITEM_ID'];
    $animal_id = $last_trans['ANIMAL_ID'];
    $restore_qty = $last_trans['QUANTITY'];
    $restore_val = $last_trans['VACCINE_COST']; // Only restore the value of the vaccine itself
    $trans_date = $last_trans['VACCINATION_DATE']; // Used to match operational cost timing

    // 2. Restore Inventory (Vaccines Table)
    // Increase Stock and Total Value back to what it was
    // Note: VACCINATION_COST (Service Fee) is NOT restored to inventory value, only VACCINE_COST is.
    $updateInv = $conn->prepare("UPDATE vaccines 
                                 SET TOTAL_STOCK = TOTAL_STOCK + ?, 
                                     TOTAL_COST = TOTAL_COST + ?, 
                                     DATE_UPDATED = NOW() 
                                 WHERE SUPPLY_ID = ?");
    $updateInv->execute([$restore_qty, $restore_val, $item_id]);

    // 3. Remove Financial Impact (Operational Cost)
    // Delete the cost record associated with this vaccination
    // This removes the sum of (Item Cost + Service Fee) from the animal's history
    $deleteOp = $conn->prepare("DELETE FROM operational_cost 
                                WHERE animal_id = ? 
                                AND datetime_created = ?");
    $deleteOp->execute([$animal_id, $trans_date]);

    // 4. Delete the Transaction Record
    $deleteTrans = $conn->prepare("DELETE FROM vaccination_records WHERE VACCINATION_ID = ?");
    $deleteTrans->execute([$vac_id]);

    // 5. Audit Log
    $details = "Reversed Vaccination #$vac_id. Restored $restore_qty units of '{$last_trans['SUPPLY_NAME']}'.";
    $audit = $conn->prepare("INSERT INTO audit_logs (USER_ID, USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS) 
                             VALUES (?, ?, 'REVERSE_VACCINE', 'VACCINATION_RECORDS', ?, ?)");
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