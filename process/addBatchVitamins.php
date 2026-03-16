<?php
// process/addBatchVitamins.php
header('Content-Type: application/json');

include '../config/Connection.php';
include '../security/checkRole.php';
session_start();

$user_id    = !empty($_SESSION['user']['USER_ID'])   ? $_SESSION['user']['USER_ID']   : 1;
$username   = $_SESSION['user']['FULL_NAME']         ?? 'System';
$ip_address = $_SERVER['REMOTE_ADDR']                ?? 'Unknown';

try {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) throw new Exception("Invalid data received.");

    $date            = $input['date']            ?? date('Y-m-d H:i:s');
    $administered_by = $input['administered_by'] ?? 'Unspecified';
    $records         = $input['records']         ?? [];
    $event_ids_str   = $input['event_ids']       ?? '';

    if (empty($records)) throw new Exception("No animals processed.");

    $conn->beginTransaction();

    // 1. Aggregate quantity needed per item
    $item_needs = [];
    foreach ($records as $row) {
        $iid = $row['item_id'];
        $qty = floatval($row['quantity']);
        if (empty($iid)) throw new Exception("One or more animals have no supplement selected.");
        if ($qty <= 0)   throw new Exception("Quantity must be greater than 0.");
        $item_needs[$iid] = ($item_needs[$iid] ?? 0) + $qty;
    }

    // 2. Validate stock & calculate unit costs
    $unit_costs  = [];
    $supply_names = [];
    $stockStmt   = $conn->prepare(
        "SELECT TOTAL_STOCK, TOTAL_COST, SUPPLY_NAME FROM VITAMINS_SUPPLEMENTS WHERE SUPPLY_ID = :id FOR UPDATE"
    );

    foreach ($item_needs as $id => $needed) {
        $stockStmt->execute([':id' => $id]);
        $inv = $stockStmt->fetch(PDO::FETCH_ASSOC);
        if (!$inv) throw new Exception("Item ID $id not found.");
        if ($inv['TOTAL_STOCK'] < $needed) {
            throw new Exception("Insufficient stock for '{$inv['SUPPLY_NAME']}'.");
        }
        $unit_costs[$id]   = ($inv['TOTAL_STOCK'] > 0)
            ? ($inv['TOTAL_COST'] / $inv['TOTAL_STOCK'])
            : 0;
        $supply_names[$id] = $inv['SUPPLY_NAME'];
    }

    // 3. Insert supplement transactions
    $insertStmt = $conn->prepare("
        INSERT INTO VITAMINS_SUPPLEMENTS_TRANSACTIONS
            (ANIMAL_ID, ITEM_ID, TRANSACTION_DATE, QUANTITY_USED,
             REMARKS, TOTAL_COST, DOSAGE, ADMINISTERED_BY)
        VALUES
            (:aid, :iid, :date, :qty, :rem, :cost, :dos, :admin)
    ");

    // 4. Insert operational_cost per animal
    $opCostStmt = $conn->prepare("
        INSERT INTO operational_cost (animal_id, operation_cost, description, datetime_created)
        VALUES (:animal_id, :cost, :description, :date)
    ");

    $inserted_count = 0;
    foreach ($records as $row) {
        $iid  = $row['item_id'];
        $qty  = floatval($row['quantity']);
        $cost = $qty * $unit_costs[$iid];

        // Insert supplement transaction
        $insertStmt->execute([
            ':aid'   => $row['animal_id'],
            ':iid'   => $iid,
            ':date'  => $date,
            ':qty'   => $qty,
            ':rem'   => $row['remarks'] ?? '',
            ':cost'  => $cost,
            ':dos'   => $row['dosage']  ?? '',
            ':admin' => $administered_by,
        ]);

        // Insert operational cost — format: "Vitamin: {Supply Name} (Qty: {qty})"
        $opCostStmt->execute([
            ':animal_id'   => $row['animal_id'],
            ':cost'        => $cost,
            ':description' => 'Vitamin: ' . $supply_names[$iid] . ' (Qty: ' . $qty . ')',
            ':date'        => $date,
        ]);

        $inserted_count++;
    }

    // 5. Deduct inventory per item
    $upStmt = $conn->prepare(
        "UPDATE VITAMINS_SUPPLEMENTS
         SET TOTAL_STOCK = TOTAL_STOCK - :qty,
             TOTAL_COST  = TOTAL_COST  - :cost
         WHERE SUPPLY_ID = :id"
    );
    foreach ($item_needs as $id => $total_qty) {
        $upStmt->execute([
            ':qty'  => $total_qty,
            ':cost' => $total_qty * $unit_costs[$id],
            ':id'   => $id,
        ]);
    }

    // 6. Close out scheduled events
    $closed_count = 0;
    if (!empty($event_ids_str)) {
        $event_ids = array_filter(array_map('intval', explode(',', $event_ids_str)));
        if (!empty($event_ids)) {
            $placeholders = implode(',', array_fill(0, count($event_ids), '?'));
            $closeStmt    = $conn->prepare("
                UPDATE event_schedules
                SET STATUS = 'Done', COMPLETED_AT = NOW()
                WHERE EVENT_ID IN ($placeholders) AND STATUS = 'Pending'
            ");
            $closeStmt->execute($event_ids);
            $closed_count = $closeStmt->rowCount();
        }
    }

    // 7. Audit log
    if ($inserted_count > 0) {
        $audit_details = "Processed $inserted_count vitamin records. "
            . "Administered by: $administered_by. Date: $date. "
            . "Closed $closed_count event(s).";

        $conn->prepare(
            "INSERT INTO audit_logs
                (USER_ID, USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS)
             VALUES (?, ?, 'BATCH_VITAMINS', 'VITAMINS_SUPPLEMENTS_TRANSACTIONS', ?, ?)"
        )->execute([$user_id, $username, $audit_details, $ip_address]);
    }

    $conn->commit();

    echo json_encode([
        'success'       => true,
        'message'       => "Successfully processed $inserted_count records. $closed_count event(s) marked as Done.",
        'closed_events' => $closed_count,
    ]);

} catch (Exception $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>