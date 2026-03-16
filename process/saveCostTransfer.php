<?php
// process/saveCostTransfer.php
session_start();
require_once '../config/Connection.php';
header('Content-Type: application/json');

function getFloat($val) { return floatval($val ?: 0); }

// --- HELPER: Get Total Costs (Base + Ops) ---
function getTotalTransferableCosts($conn, $animal_id) {
    if (!$animal_id) return 0.00;

    // 1. Get Base Cost and Reset Date
    $stmt = $conn->prepare("SELECT LAST_COST_RESET_DATE, ACQUISITION_COST FROM animal_records WHERE ANIMAL_ID = ?");
    $stmt->execute([$animal_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $resetDate = $row['LAST_COST_RESET_DATE'];
    $baseCost = getFloat($row['ACQUISITION_COST']);

    // 2. Query ONLY the operational_cost table
    $sql = "SELECT COALESCE(SUM(operation_cost), 0) FROM operational_cost WHERE animal_id = ?";
    $params = [$animal_id];

    if ($resetDate) {
        $sql .= " AND datetime_created > ?";
        $params[] = $resetDate;
    }

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $variableCost = getFloat($stmt->fetchColumn());
    
    // Return combined total
    return $baseCost + $variableCost;
}

// --- MAIN PROCESS ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']); exit;
}

// --- AUDIT LOG CONTEXT ---
$user_id = !empty($_SESSION['user']['USER_ID']) ? $_SESSION['user']['USER_ID'] : 1; 
$username = $_SESSION['user']['FULL_NAME'] ?? 'System';
$ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';

try {
    $sow_id = $_POST['sow_id'] ?? null;
    $boar_id = !empty($_POST['boar_id']) ? $_POST['boar_id'] : null;
    $piglet_ids = json_decode($_POST['piglet_ids'] ?? '[]', true);
    
    $input_sow_cost = getFloat($_POST['sow_cost']);
    $input_boar_cost = getFloat($_POST['boar_cost']);

    // 1. Calculate Available Total Costs
    $avail_sow_total = getTotalTransferableCosts($conn, $sow_id);
    $avail_boar_total = getTotalTransferableCosts($conn, $boar_id);

    // 2. Strict Check (With tolerance)
    if ($input_sow_cost > ($avail_sow_total + 0.01)) {
       throw new Exception("STRICT ERROR: Sow Transfer (₱$input_sow_cost) exceeds available total costs (₱$avail_sow_total).");
    }
    if ($input_boar_cost > ($avail_boar_total + 0.01)) {
       throw new Exception("STRICT ERROR: Boar Transfer (₱$input_boar_cost) exceeds available total costs (₱$avail_boar_total).");
    }

    $total_amount = $input_sow_cost + $input_boar_cost;

    if (empty($piglet_ids)) throw new Exception("No piglets selected.");
    if ($total_amount <= 0) throw new Exception("Total amount must be greater than zero.");

    $count = count($piglet_ids);
    $cost_per_head = $total_amount / $count;

    $conn->beginTransaction();

    // A. Log Transfer (Historical Record)
    $logStmt = $conn->prepare("INSERT INTO cost_transfers (SOW_ID, BOAR_ID, SOW_COST, BOAR_COST, TOTAL_AMOUNT, PIGLET_COUNT, COST_PER_HEAD, CREATED_BY) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $logStmt->execute([$sow_id, $boar_id, $input_sow_cost, $input_boar_cost, $total_amount, $count, $cost_per_head, $user_id]);
    $transfer_id = $conn->lastInsertId();

    // B. Distribute to Piglets
    $updateStmt = $conn->prepare("UPDATE animal_records SET ACQUISITION_COST = ACQUISITION_COST + ? WHERE ANIMAL_ID = ?");
    foreach ($piglet_ids as $pid) {
        $updateStmt->execute([$cost_per_head, $pid]);
    }

    // --------------------------------------------------------
    // C. LEDGER UPDATE (Save Negative Cost)
    // --------------------------------------------------------
    $opStmt = $conn->prepare("INSERT INTO operational_cost (animal_id, operation_cost, description, datetime_created) VALUES (?, ?, ?, NOW())");
    
    $ref = "Ref: TRF-" . $transfer_id;

    // Deduct from Sow
    if ($input_sow_cost > 0) {
        $neg_sow = $input_sow_cost * -1;
        $desc_sow = "Transfer: Cost to Piglets ($ref)";
        $opStmt->execute([$sow_id, $neg_sow, $desc_sow]);
    }

    // Deduct from Boar
    if ($input_boar_cost > 0) {
        $neg_boar = $input_boar_cost * -1;
        $desc_boar = "Transfer: Cost to Piglets ($ref)";
        $opStmt->execute([$boar_id, $neg_boar, $desc_boar]);
    }

    // D. Insert Audit Log (Inside Transaction)
    $audit_action = "COST_TRANSFER";
    $audit_details = "Transferred Cost: ₱" . number_format($total_amount, 2) . 
                     " (Sow: ₱" . number_format($input_sow_cost, 2) . ", Boar: ₱" . number_format($input_boar_cost, 2) . ") " .
                     "to $count piglets.";

    $audit_sql = "INSERT INTO audit_logs (USER_ID, USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS) 
                  VALUES (?, ?, ?, 'COST_TRANSFERS', ?, ?)";
    $audit_stmt = $conn->prepare($audit_sql);
    $audit_stmt->execute([$user_id, $username, $audit_action, $audit_details, $ip_address]);

    $conn->commit();
    echo json_encode(['success' => true, 'message' => "Transfer successful. Costs deducted from parents."]);

} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) $conn->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>