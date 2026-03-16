<?php
// process/addBatchVaccination.php
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

    $vaccine_id      = $input['vaccine_id']      ?? null;
    $unit_id         = $input['unit_id']         ?? null;
    $date            = $input['date']            ?? date('Y-m-d H:i:s');
    $records         = $input['records']         ?? [];
    $administered_by = $input['administered_by'] ?? 'Unspecified';
    $service_fee     = isset($input['service_fee']) ? floatval($input['service_fee']) : 0;
    $event_ids_str   = $input['event_ids']       ?? '';

    if (empty($vaccine_id) || empty($records)) {
        throw new Exception("Missing required vaccine or animal selection.");
    }

    $conn->beginTransaction();

    // 1. Lock inventory row & validate stock
    $stockStmt = $conn->prepare(
        "SELECT TOTAL_STOCK, TOTAL_COST, SUPPLY_NAME FROM VACCINES WHERE SUPPLY_ID = :id FOR UPDATE"
    );
    $stockStmt->execute([':id' => $vaccine_id]);
    $inventory = $stockStmt->fetch(PDO::FETCH_ASSOC);

    if (!$inventory) throw new Exception("Vaccine not found in inventory.");

    $total_qty_needed = 0;
    foreach ($records as $rec) { $total_qty_needed += floatval($rec['quantity']); }

    if ($inventory['TOTAL_STOCK'] < $total_qty_needed) {
        throw new Exception(
            "Insufficient stock! Available: {$inventory['TOTAL_STOCK']}, Needed: {$total_qty_needed}"
        );
    }

    $unit_cost   = ($inventory['TOTAL_STOCK'] > 0)
        ? ($inventory['TOTAL_COST'] / $inventory['TOTAL_STOCK'])
        : 0;
    $supply_name = $inventory['SUPPLY_NAME'];

    // 2. Insert vaccination records + operational_cost per animal
    $insertStmt = $conn->prepare("
        INSERT INTO VACCINATION_RECORDS
            (ANIMAL_ID, ITEM_ID, VACCINATION_DATE, VET_NAME, REMARKS,
             QUANTITY, UNIT_ID, VACCINE_COST, VACCINATION_COST, ADMINISTERED_BY)
        VALUES
            (:animal_id, :vaccine_id, :date, :vet, :remarks,
             :qty, :unit, :item_cost, :service_cost, :admin)
    ");

    $opCostStmt = $conn->prepare("
        INSERT INTO operational_cost (animal_id, operation_cost, description, datetime_created)
        VALUES (:animal_id, :cost, :description, :date)
    ");

    $inserted_count = 0;
    foreach ($records as $row) {
        $qty_used  = floatval($row['quantity']);
        $item_cost = $qty_used * $unit_cost;

        // Insert vaccination record
        $insertStmt->execute([
            ':animal_id'    => $row['animal_id'],
            ':vaccine_id'   => $vaccine_id,
            ':date'         => $date,
            ':vet'          => $administered_by,
            ':remarks'      => $row['remarks'] ?? '',
            ':qty'          => $qty_used,
            ':unit'         => $unit_id,
            ':item_cost'    => $item_cost,
            ':service_cost' => $service_fee,
            ':admin'        => $administered_by,
        ]);

        // Insert operational cost — format: "Vaccine: {Supply Name} (Qty: {qty})"
        $opCostStmt->execute([
            ':animal_id'   => $row['animal_id'],
            ':cost'        => $item_cost,
            ':description' => 'Vaccine: ' . $supply_name . ' (Qty: ' . $qty_used . ')',
            ':date'        => $date,
        ]);

        $inserted_count++;
    }

    // 3. Deduct inventory
    $total_cost_deducted = $total_qty_needed * $unit_cost;
    $conn->prepare(
        "UPDATE VACCINES
         SET TOTAL_STOCK = TOTAL_STOCK - :qty,
             TOTAL_COST  = TOTAL_COST  - :cost
         WHERE SUPPLY_ID = :id"
    )->execute([':qty' => $total_qty_needed, ':cost' => $total_cost_deducted, ':id' => $vaccine_id]);

    // 4. Close out scheduled events
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

    // 5. Audit log
    if ($inserted_count > 0) {
        $audit_details = "Vaccinated $inserted_count animals with $supply_name. "
            . "Total Qty: $total_qty_needed. Administered by: $administered_by. "
            . "Closed $closed_count event(s).";

        $conn->prepare(
            "INSERT INTO audit_logs
                (USER_ID, USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS)
             VALUES (?, ?, 'BATCH_VACCINATION', 'VACCINATION_RECORDS', ?, ?)"
        )->execute([$user_id, $username, $audit_details, $ip_address]);
    }

    $conn->commit();

    echo json_encode([
        'success'       => true,
        'message'       => "Batch processed successfully. $closed_count event(s) marked as Done.",
        'closed_events' => $closed_count,
    ]);

} catch (Exception $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>