<?php
// process/addBatchCheckup.php
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

    $vet_name      = $input['examined_by'] ?? $input['vet_name'] ?? 'Unknown';
    $date          = $input['date']        ?? date('Y-m-d H:i:s');
    $records       = $input['records']     ?? [];
    $cost_per_head = isset($input['cost']) ? floatval($input['cost']) : 0;
    $event_ids_str = $input['event_ids']   ?? '';

    if (empty($records))  throw new Exception("No animals selected for inspection.");
    if (empty($vet_name)) throw new Exception("Veterinarian name is required.");

    $conn->beginTransaction();

    // 1. Insert checkup records + operational_cost per animal
    $stmt = $conn->prepare("
        INSERT INTO CHECK_UPS
            (ANIMAL_ID, CHECKUP_DATE, VET_NAME, REMARKS, COST)
        VALUES
            (:animal_id, :date, :vet, :final_remarks, :cost)
    ");

    $opCostStmt = $conn->prepare("
        INSERT INTO operational_cost (animal_id, operation_cost, description, datetime_created)
        VALUES (:animal_id, :cost, :description, :date)
    ");

    $inserted_count = 0;
    foreach ($records as $row) {
        $animal_id     = $row['animal_id'];
        $user_note     = $row['findings'] ?? $row['remarks'] ?? '';
        $health_status = $row['status']   ?? 'Checked';
        $final_remarks = "[$health_status]";
        if (!empty($user_note)) {
            $final_remarks .= " " . $user_note;
        }

        // Insert checkup record
        $stmt->execute([
            ':animal_id'     => $animal_id,
            ':date'          => $date,
            ':vet'           => $vet_name,
            ':final_remarks' => $final_remarks,
            ':cost'          => $cost_per_head,
        ]);

        // Insert operational cost only if there's a cost to record
        // Format: "Checkup Cost (ID: {last insert id})" — matches existing DB pattern
        if ($cost_per_head > 0) {
            $checkup_id = $conn->lastInsertId();
            $opCostStmt->execute([
                ':animal_id'   => $animal_id,
                ':cost'        => $cost_per_head,
                ':description' => 'Checkup Cost (ID: ' . $checkup_id . ')',
                ':date'        => $date,
            ]);
        }

        $inserted_count++;
    }

    // 2. Close out scheduled events
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

    // 3. Audit log
    if ($inserted_count > 0) {
        $audit_details = "Recorded check-ups for $inserted_count animals. "
            . "Vet: $vet_name. Date: $date. Closed $closed_count event(s).";

        $conn->prepare(
            "INSERT INTO audit_logs
                (USER_ID, USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS)
             VALUES (?, ?, 'BATCH_ADD', 'CHECK_UPS', ?, ?)"
        )->execute([$user_id, $username, $audit_details, $ip_address]);
    }

    $conn->commit();

    echo json_encode([
        'success'       => true,
        'message'       => "Successfully recorded check-ups for $inserted_count animals. $closed_count event(s) marked as Done.",
        'closed_events' => $closed_count,
    ]);

} catch (Exception $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>