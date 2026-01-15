<?php
// process/undoFeedTransaction.php
session_start();
header('Content-Type: application/json');
require_once '../config/Connection.php';

// Get User Info for Audit Log
$user_id = $_SESSION['user']['USER_ID'] ?? null;
$username = $_SESSION['user']['FULL_NAME'] ?? 'System';
$ip = $_SERVER['REMOTE_ADDR'];

// Ensure it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { 
    echo json_encode(['success' => false, 'message' => 'Invalid Request Method']); 
    exit; 
}

try {
    $conn->beginTransaction();
    
    // Check if the action is "undo_last" (triggered by the global button)
    $action = $_POST['action'] ?? null;
    
    // Specific ID (optional, if we ever add per-row undo back)
    $ft_id = $_POST['ft_id'] ?? null;

    $target_batch_id = null;
    $target_single_id = null;

    // --- 1. DETERMINE WHAT TO UNDO ---
    
    if ($action === 'undo_last') {
        // Find the absolute last created transaction based on ID
        $stmt = $conn->query("SELECT FT_ID, BATCH_ID FROM FEED_TRANSACTIONS ORDER BY FT_ID DESC LIMIT 1");
        $latest = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$latest) {
            throw new Exception("No feeding records found to undo.");
        }

        // If the latest record has a batch ID, we target the whole batch
        if (!empty($latest['BATCH_ID'])) {
            $target_batch_id = $latest['BATCH_ID'];
        } else {
            // Otherwise, it's a single record
            $target_single_id = $latest['FT_ID'];
        }

    } elseif (!empty($ft_id)) {
        // Undo specific ID logic
        $stmt = $conn->prepare("SELECT BATCH_ID FROM FEED_TRANSACTIONS WHERE FT_ID = ?");
        $stmt->execute([$ft_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row && !empty($row['BATCH_ID'])) {
            $target_batch_id = $row['BATCH_ID'];
        } else {
            $target_single_id = $ft_id;
        }
    } else {
        throw new Exception("Invalid parameters.");
    }

    // --- 2. EXECUTE THE UNDO ---

    $total_qty_restored = 0;
    $audit_msg = "";
    $feed_id = null;

    if ($target_batch_id) {
        // --- BULK UNDO ---
        
        // A. Sum up totals for this batch (grouped by Feed ID to be safe)
        $sumSql = "SELECT SUM(QUANTITY_KG) as total_qty, SUM(TRANSACTION_COST) as total_cost, FEED_ID, COUNT(*) as count 
                   FROM FEED_TRANSACTIONS WHERE BATCH_ID = ? GROUP BY FEED_ID";
        $sumStmt = $conn->prepare($sumSql);
        $sumStmt->execute([$target_batch_id]);
        $batchStats = $sumStmt->fetch(PDO::FETCH_ASSOC);

        if (!$batchStats) throw new Exception("Batch data corrupt or not found.");

        $total_qty_restored = $batchStats['total_qty'];
        $total_cost = $batchStats['total_cost'];
        $feed_id = $batchStats['FEED_ID'];
        $record_count = $batchStats['count'];

        // Get Feed Name for log
        $feedName = $conn->query("SELECT FEED_NAME FROM FEEDS WHERE FEED_ID = $feed_id")->fetchColumn();

        // B. Restore Stock
        $upd = $conn->prepare("UPDATE FEEDS 
                               SET TOTAL_WEIGHT_KG = TOTAL_WEIGHT_KG + ?, 
                                   TOTAL_COST = TOTAL_COST + ?, 
                                   DATE_UPDATED = NOW() 
                               WHERE FEED_ID = ?");
        $upd->execute([$total_qty_restored, $total_cost, $feed_id]);

        // C. Delete All Records in Batch
        $del = $conn->prepare("DELETE FROM FEED_TRANSACTIONS WHERE BATCH_ID = ?");
        $del->execute([$target_batch_id]);

        $audit_msg = "Undid Last Bulk Feed (Batch: $target_batch_id). Restored $total_qty_restored kg of $feedName (Removed $record_count records).";

    } else {
        // --- SINGLE ROW UNDO ---

        // A. Get Details
        $stmt = $conn->prepare("SELECT * FROM FEED_TRANSACTIONS WHERE FT_ID = ?");
        $stmt->execute([$target_single_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) throw new Exception("Transaction record not found.");

        $total_qty_restored = $row['QUANTITY_KG'];
        $cost = $row['TRANSACTION_COST'];
        $feed_id = $row['FEED_ID'];

        // Get Feed & Tag Name
        $feedName = $conn->query("SELECT FEED_NAME FROM FEEDS WHERE FEED_ID = $feed_id")->fetchColumn();
        $tagName = $conn->query("SELECT TAG_NO FROM ANIMAL_RECORDS WHERE ANIMAL_ID = {$row['ANIMAL_ID']}")->fetchColumn();

        // B. Restore Stock
        $upd = $conn->prepare("UPDATE FEEDS 
                               SET TOTAL_WEIGHT_KG = TOTAL_WEIGHT_KG + ?, 
                                   TOTAL_COST = TOTAL_COST + ?, 
                                   DATE_UPDATED = NOW() 
                               WHERE FEED_ID = ?");
        $upd->execute([$total_qty_restored, $cost, $feed_id]);

        // C. Delete Record
        $del = $conn->prepare("DELETE FROM FEED_TRANSACTIONS WHERE FT_ID = ?");
        $del->execute([$target_single_id]);

        $audit_msg = "Undid Feed Transaction (ID: $target_single_id) for Tag $tagName. Restored $total_qty_restored kg of $feedName.";
    }

    // --- 3. AUDIT LOG ---
    $audit = $conn->prepare("INSERT INTO AUDIT_LOGS (USER_ID, USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS) 
                             VALUES (?, ?, 'UNDO_FEED', 'FEED_TRANSACTIONS', ?, ?)");
    $audit->execute([$user_id, $username, $audit_msg, $ip]);

    $conn->commit();
    echo json_encode([
        'success' => true, 
        'message' => "✅ Undo Successful. $total_qty_restored kg restored to inventory."
    ]);

} catch (Exception $e) {
    if($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>