<?php
// process/processDisposal.php
session_start();
include '../config/Connection.php';
header('Content-Type: application/json');

// Helper Info
$user_id = $_SESSION['user']['USER_ID'] ?? 0;
$username = $_SESSION['user']['FULL_NAME'] ?? 'System';
$ip = $_SERVER['REMOTE_ADDR'];

$action = $_POST['action'] ?? '';

// --- CONFIGURATION MAP ---
// Maps categories to your existing Database Tables
$map = [
    'feed'     => ['table' => 'feeds', 'pk' => 'FEED_ID', 'name' => 'FEED_NAME', 'stock' => 'TOTAL_WEIGHT_KG', 'unit' => 'kg'],
    'medicine' => ['table' => 'medicines', 'pk' => 'SUPPLY_ID', 'name' => 'SUPPLY_NAME', 'stock' => 'TOTAL_STOCK', 'has_unit_table' => true],
    'vitamin'  => ['table' => 'vitamins_supplements', 'pk' => 'SUPPLY_ID', 'name' => 'SUPPLY_NAME', 'stock' => 'TOTAL_STOCK', 'has_unit_table' => true],
    'vaccine'  => ['table' => 'vaccines', 'pk' => 'SUPPLY_ID', 'name' => 'SUPPLY_NAME', 'stock' => 'TOTAL_STOCK', 'has_unit_table' => true]
];

// 1. FETCH BATCHES (For Dropdown)
if ($action === 'fetch_batches') {
    $cat = $_POST['category'];
    if (!isset($map[$cat])) { echo json_encode([]); exit; }

    $conf = $map[$cat];
    $tbl = $conf['table'];
    $pk = $conf['pk'];
    $nameCol = $conf['name'];
    $stockCol = $conf['stock'];

    try {
        if (isset($conf['has_unit_table'])) {
            // Join with Units table for Meds/Vits/Vacs
            $sql = "SELECT t.$pk as id, t.$nameCol as name, t.$stockCol as stock, t.EXPIRATION_DATE as expiry, 
                    u.UNIT_NAME as unit, t.LOCATION_ID, l.LOCATION_NAME
                    FROM $tbl t
                    LEFT JOIN units u ON t.UNIT_ID = u.UNIT_ID
                    LEFT JOIN locations l ON t.LOCATION_ID = l.LOCATION_ID
                    WHERE t.$stockCol > 0
                    ORDER BY t.$nameCol ASC, t.EXPIRATION_DATE ASC";
        } else {
            // Feeds (Fixed unit)
            $sql = "SELECT t.$pk as id, t.$nameCol as name, t.$stockCol as stock, t.EXPIRATION_DATE as expiry, 
                    'kg' as unit, t.LOCATION_ID, l.LOCATION_NAME
                    FROM $tbl t
                    LEFT JOIN locations l ON t.LOCATION_ID = l.LOCATION_ID
                    WHERE t.$stockCol > 0
                    ORDER BY t.$nameCol ASC, t.EXPIRATION_DATE ASC";
        }

        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Clean up expiry display
        foreach($rows as &$r) {
            if(!$r['expiry'] || $r['expiry'] == '0000-00-00') $r['expiry'] = 'No Date';
            if(!$r['LOCATION_NAME']) $r['LOCATION_NAME'] = 'Unassigned';
        }
        
        echo json_encode($rows);
    } catch (Exception $e) { echo json_encode([]); }
    exit;
}

// 2. PROCESS DISPOSAL (The Transaction)
if ($action === 'submit_disposal') {
    $cat = $_POST['category'];
    $id = $_POST['batch_id']; // The ID of the specific row (Batch)
    $qty = floatval($_POST['quantity']);
    $type = $_POST['disposal_type']; // Expired, Damaged, Stolen
    $date = $_POST['date'];
    $remarks = $_POST['remarks'];

    if (!isset($map[$cat])) { echo json_encode(['success'=>false, 'message'=>'Invalid Category']); exit; }

    $conf = $map[$cat];
    $tbl = $conf['table'];
    $pk = $conf['pk'];
    $stockCol = $conf['stock'];
    $nameCol = $conf['name'];

    try {
        $conn->beginTransaction();

        // A. LOCK & GET CURRENT DATA
        // We need current stock to validation, and details for the log record
        $stmt = $conn->prepare("SELECT $nameCol, $stockCol, EXPIRATION_DATE, LOCATION_ID, UNIT_ID FROM $tbl WHERE $pk = :id FOR UPDATE");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) throw new Exception("Item batch not found.");
        
        $currentStock = floatval($row[$stockCol]);
        $itemName = $row[$nameCol];
        $expiry = $row['EXPIRATION_DATE'];
        $locId = $row['LOCATION_ID'];
        
        // Get Unit Name
        $unitName = $conf['unit'] ?? 'units'; // Default for feed
        if (isset($conf['has_unit_table']) && $row['UNIT_ID']) {
            $uStmt = $conn->prepare("SELECT UNIT_NAME FROM units WHERE UNIT_ID = ?");
            $uStmt->execute([$row['UNIT_ID']]);
            $unitName = $uStmt->fetchColumn();
        }

        // B. VALIDATE
        if ($qty > $currentStock) throw new Exception("Cannot dispose $qty. Only $currentStock available.");

        // C. MODIFY EXISTING INVENTORY (Deduct)
        $newStock = $currentStock - $qty;
        $updateSql = "UPDATE $tbl SET $stockCol = :newStock, DATE_UPDATED = NOW() WHERE $pk = :id";
        $updateStmt = $conn->prepare($updateSql);
        $updateStmt->execute([':newStock' => $newStock, ':id' => $id]);

        // D. CREATE DISPOSAL RECORD
        $logSql = "INSERT INTO disposal_records 
                   (TRANSACTION_DATE, CATEGORY, REF_ID, ITEM_NAME, BATCH_EXPIRY, QUANTITY, UNIT, DISPOSAL_TYPE, LOCATION_ID, REMARKS, CREATED_BY)
                   VALUES 
                   (:date, :cat, :ref, :name, :exp, :qty, :unit, :type, :loc, :rem, :user)";
        $logStmt = $conn->prepare($logSql);
        $logStmt->execute([
            ':date' => $date,
            ':cat' => $cat,
            ':ref' => $id,
            ':name' => $itemName,
            ':exp' => $expiry,
            ':qty' => $qty,
            ':unit' => $unitName,
            ':type' => $type,
            ':loc' => $locId,
            ':rem' => $remarks,
            ':user' => $user_id
        ]);

        // E. AUDIT LOG (System Level)
        $auditDetails = "Disposed $qty $unitName of $itemName ($type). Batch Exp: $expiry.";
        $auditSql = "INSERT INTO audit_logs (USER_ID, USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS) 
                     VALUES (:uid, :uname, 'DISPOSAL_TRANSACTION', :tbl, :details, :ip)";
        $conn->prepare($auditSql)->execute([
            ':uid' => $user_id, ':uname' => $username, ':tbl' => strtoupper($tbl), ':details' => $auditDetails, ':ip' => $ip
        ]);

        $conn->commit();
        echo json_encode(['success' => true, 'message' => "Successfully disposed $qty $unitName of $itemName."]);

    } catch (Exception $e) {
        $conn->rollBack();
        echo json_encode(['success' => false, 'message' => "Error: " . $e->getMessage()]);
    }
    exit;
}
?>