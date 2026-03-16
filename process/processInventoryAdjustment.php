<?php
// process/process_inventory_adjustment.php
session_start();
include '../config/Connection.php';
header('Content-Type: application/json');

$user_id = $_SESSION['user']['USER_ID'] ?? 0;
$username = $_SESSION['user']['FULL_NAME'] ?? 'System';
$ip = $_SERVER['REMOTE_ADDR'];

$request = $_POST['request_type'] ?? '';

// --- CONFIGURATION ---
$configs = [
    'feed' => [ 'table'=>'feeds', 'pk'=>'FEED_ID', 'name'=>'FEED_NAME', 'stock'=>'TOTAL_WEIGHT_KG', 'unit'=>'kg' ],
    'medicine' => [ 'table'=>'medicines', 'pk'=>'SUPPLY_ID', 'name'=>'SUPPLY_NAME', 'stock'=>'TOTAL_STOCK', 'unit_table'=>true ],
    'vitamin' => [ 'table'=>'vitamins_supplements', 'pk'=>'SUPPLY_ID', 'name'=>'SUPPLY_NAME', 'stock'=>'TOTAL_STOCK', 'unit_table'=>true ],
    'vaccine' => [ 'table'=>'vaccines', 'pk'=>'SUPPLY_ID', 'name'=>'SUPPLY_NAME', 'stock'=>'TOTAL_STOCK', 'unit_table'=>true ]
];

// --- 1. FETCH BATCHES ---
if ($request === 'fetch_batches') {
    $cat = $_POST['category'];
    if (!isset($configs[$cat])) { echo json_encode([]); exit; }

    $conf = $configs[$cat];
    $tbl = $conf['table'];
    $pk = $conf['pk'];
    $nameCol = $conf['name'];
    $stockCol = $conf['stock'];

    try {
        if (isset($conf['unit_table'])) {
            $sql = "SELECT t.$pk as id, t.$nameCol as name, t.$stockCol as stock, t.EXPIRATION_DATE as expiry, u.UNIT_NAME as unit
                    FROM $tbl t LEFT JOIN units u ON t.UNIT_ID = u.UNIT_ID
                    WHERE t.$stockCol > 0 ORDER BY t.$nameCol ASC, t.EXPIRATION_DATE ASC";
        } else {
            $sql = "SELECT $pk as id, $nameCol as name, $stockCol as stock, EXPIRATION_DATE as expiry, 'kg' as unit
                    FROM $tbl WHERE $stockCol > 0 ORDER BY $nameCol ASC, EXPIRATION_DATE ASC";
        }

        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($results as &$row) {
            if (!$row['expiry'] || $row['expiry'] == '0000-00-00') $row['expiry'] = 'No Date';
        }
        echo json_encode($results);
    } catch (Exception $e) { echo json_encode([]); }
    exit;
}

// --- 2. SUBMIT ADJUSTMENT ---
if ($request === 'submit_adjustment') {
    $cat = $_POST['category'];
    $id = $_POST['batch_id'];
    $mode = $_POST['input_mode']; // 'quantity' or 'balance'
    $inputVal = floatval($_POST['input_value']);
    $reason = $_POST['reason'];
    $remarks = trim($_POST['remarks']);
    $date = $_POST['date'];

    if (!isset($configs[$cat])) { echo json_encode(['success'=>false, 'message'=>'Invalid category']); exit; }

    $conf = $configs[$cat];
    $tbl = $conf['table'];
    $pk = $conf['pk'];
    $stockCol = $conf['stock'];
    $nameCol = $conf['name'];

    try {
        $conn->beginTransaction();

        // 1. Get Current Stock (Lock Row)
        $check = $conn->prepare("SELECT $nameCol, $stockCol, EXPIRATION_DATE FROM $tbl WHERE $pk = :id FOR UPDATE");
        $check->execute([':id' => $id]);
        $row = $check->fetch(PDO::FETCH_ASSOC);

        if (!$row) throw new Exception("Item batch not found.");

        $currentStock = floatval($row[$stockCol]);
        $itemName = $row[$nameCol];
        $expiry = $row['EXPIRATION_DATE'];

        // 2. Calculate Actual Deduction based on Mode
        if ($mode === 'quantity') {
            // User entered amount to remove (or add, if negative)
            $qtyToDeduct = $inputVal;
            $newStock = $currentStock - $qtyToDeduct;
        } else {
            // User entered ending balance (Left)
            $newStock = $inputVal;
            $qtyToDeduct = $currentStock - $newStock;
        }

        // 3. Validation & Type Formatting
        if ($newStock < 0) {
            throw new Exception("Invalid adjustment. Stock cannot drop below zero.");
        }
        if ($qtyToDeduct < 0 && $reason !== 'Correction') {
            throw new Exception("You cannot increase stock unless the reason is set to 'Audit Correction'.");
        }
        if ($qtyToDeduct == 0) {
            throw new Exception("No changes made. The inputted value results in the exact same stock amount.");
        }

        // Determine if we are adding or deducting for the logs
        $adjustmentType = ($qtyToDeduct < 0) ? 'Add' : 'Deduct';
        $absoluteQty = abs($qtyToDeduct); // Store positive number in db, type determines Add/Deduct

        // 4. Update Database Stock
        $update = $conn->prepare("UPDATE $tbl SET $stockCol = :newStock, DATE_UPDATED = NOW() WHERE $pk = :id");
        $update->execute([':newStock' => $newStock, ':id' => $id]);

        // 5. Record Log in `inventory_adjustments`
        $logSql = "INSERT INTO inventory_adjustments 
                   (TRANSACTION_DATE, CATEGORY, REF_ID, ITEM_NAME, BATCH_EXPIRY, ADJUSTMENT_TYPE, INPUT_MODE, QUANTITY, PREVIOUS_STOCK, NEW_STOCK, REASON, REMARKS, CREATED_BY) 
                   VALUES 
                   (:date, :cat, :ref, :name, :exp, :adj_type, :mode, :qty, :prev, :new, :reason, :rem, :uid)";
        
        $log = $conn->prepare($logSql);
        $log->execute([
            ':date' => $date, ':cat' => $cat, ':ref' => $id, ':name' => $itemName, ':exp' => $expiry,
            ':adj_type' => $adjustmentType, ':mode' => $mode, ':qty' => $absoluteQty, 
            ':prev' => $currentStock, ':new' => $newStock, ':reason' => $reason, 
            ':rem' => $remarks, ':uid' => $user_id
        ]);

        // 6. Audit Log (General System Log)
        $auditAction = ($adjustmentType === 'Add') ? 'INVENTORY_ADDITION' : 'INVENTORY_DISPOSAL';
        $auditDetails = "Adjustment ($adjustmentType): $itemName (Exp: $expiry). Input Mode: $mode. Modified By: $absoluteQty. Old: $currentStock -> New: $newStock. Reason: $reason";
        
        $conn->prepare("INSERT INTO audit_logs (USER_ID, USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS) 
                        VALUES (:uid, :uname, :action, :tbl, :details, :ip)")
             ->execute([':uid'=>$user_id, ':uname'=>$username, ':action'=>$auditAction, ':tbl'=>strtoupper($tbl), ':details'=>$auditDetails, ':ip'=>$ip]);

        $conn->commit();
        echo json_encode(['success' => true, 'message' => "Successfully adjusted. New Stock is now $newStock"]);

    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}
?>