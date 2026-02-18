<?php
// process/reverseCheckupTransaction.php
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

    // 1. Find the Most Recent Check-up
    $sql = "SELECT c.*, a.TAG_NO 
            FROM check_ups c
            LEFT JOIN animal_records a ON c.ANIMAL_ID = a.ANIMAL_ID
            ORDER BY c.CHECK_UP_ID DESC LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $last_trans = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$last_trans) {
        throw new Exception("No check-up records found to reverse.");
    }

    $checkup_id = $last_trans['CHECK_UP_ID'];
    $animal_id = $last_trans['ANIMAL_ID'];
    $trans_date = $last_trans['CHECKUP_DATE'];
    $cost = $last_trans['COST'];

    // 2. Remove Financial Impact (Operational Cost)
    // Delete the cost record associated with this check-up
    // Note: Inventory restoration is not needed for check-ups as they are services.
    $deleteOp = $conn->prepare("DELETE FROM operational_cost 
                                WHERE animal_id = ? 
                                AND datetime_created = ?");
    $deleteOp->execute([$animal_id, $trans_date]);

    // 3. Delete the Check-up Record
    $deleteTrans = $conn->prepare("DELETE FROM check_ups WHERE CHECK_UP_ID = ?");
    $deleteTrans->execute([$checkup_id]);

    // 4. Audit Log
    $details = "Reversed Check-up ID #$checkup_id for Animal {$last_trans['TAG_NO']}. Removed cost: ₱" . number_format($cost, 2);
    $audit = $conn->prepare("INSERT INTO audit_logs (USER_ID, USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS) 
                             VALUES (?, ?, 'REVERSE_CHECKUP', 'CHECK_UPS', ?, ?)");
    $audit->execute([$user_id, $username, $details, $ip]);

    $conn->commit();

    echo json_encode([
        'success' => true, 
        'message' => "Reversal Successful! Check-up record deleted."
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