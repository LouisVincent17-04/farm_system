<?php
// process/deleteFeedTransaction.php
session_start();
header('Content-Type: application/json');
require_once '../config/Connection.php';

$user_id = $_SESSION['user']['USER_ID'] ?? null;
$username = $_SESSION['user']['FULL_NAME'] ?? 'System';
$ip = $_SERVER['REMOTE_ADDR'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['success'=>false]); exit; }

try {
    $ft_id = $_POST['ft_id'];
    $conn->beginTransaction();

    // Get Details
    $stmt = $conn->prepare("SELECT ft.*, f.FEED_NAME, f.TOTAL_WEIGHT_KG, f.TOTAL_COST, a.TAG_NO 
                            FROM FEED_TRANSACTIONS ft 
                            JOIN FEEDS f ON ft.FEED_ID = f.FEED_ID 
                            JOIN ANIMAL_RECORDS a ON ft.ANIMAL_ID = a.ANIMAL_ID
                            WHERE ft.FT_ID = ? FOR UPDATE");
    $stmt->execute([$ft_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if(!$row) throw new Exception("Transaction not found");

    // Restore Stock
    $qty = $row['QUANTITY_KG'];
    $cost = $row['TRANSACTION_COST'];
    
    $upd = $conn->prepare("UPDATE FEEDS SET TOTAL_WEIGHT_KG = TOTAL_WEIGHT_KG + ?, TOTAL_COST = TOTAL_COST + ?, DATE_UPDATED = NOW() WHERE FEED_ID = ?");
    $upd->execute([$qty, $cost, $row['FEED_ID']]);

    // Delete Record
    $del = $conn->prepare("DELETE FROM FEED_TRANSACTIONS WHERE FT_ID = ?");
    $del->execute([$ft_id]);

    // Audit Log
    $msg = "Deleted Feed Transaction for Tag {$row['TAG_NO']}. Restored $qty kg of {$row['FEED_NAME']}.";
    $audit = $conn->prepare("INSERT INTO AUDIT_LOGS (USER_ID, USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS) VALUES (?, ?, 'DELETE_FEED', 'FEED_TRANSACTIONS', ?, ?)");
    $audit->execute([$user_id, $username, $msg, $ip]);

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Record deleted and stock restored.']);

} catch (Exception $e) {
    $conn->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>