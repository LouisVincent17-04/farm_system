<?php
// process/saveCostTransfer.php
session_start();
require_once '../config/Connection.php';
header('Content-Type: application/json');

function getFloat($val) { return floatval($val ?: 0); }

// --- HELPER: Get Total Variable Costs (SINGLE SOURCE OF TRUTH) ---
function getVariableCosts($conn, $animal_id) {
    if (!$animal_id) return 0.00;

    $stmt = $conn->prepare("SELECT LAST_COST_RESET_DATE FROM animal_records WHERE ANIMAL_ID = ?");
    $stmt->execute([$animal_id]);
    $resetDate = $stmt->fetchColumn(); 

    // Query ONLY the operational_cost table
    $sql = "SELECT COALESCE(SUM(operation_cost), 0) FROM operational_cost WHERE animal_id = ?";
    $params = [$animal_id];

    if ($resetDate) {
        $sql .= " AND datetime_created > ?";
        $params[] = $resetDate;
    }

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    return getFloat($stmt->fetchColumn());
}

// --- MAIN PROCESS ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']); exit;
}

try {
    $user_id = $_SESSION['user_id'] ?? 1;
    $sow_id = $_POST['sow_id'] ?? null;
    $boar_id = !empty($_POST['boar_id']) ? $_POST['boar_id'] : null;
    $piglet_ids = json_decode($_POST['piglet_ids'] ?? '[]', true);
    
    $input_sow_cost = getFloat($_POST['sow_cost']);
    $input_boar_cost = getFloat($_POST['boar_cost']);

    // 1. Calculate Available Variable Costs
    $avail_sow_variable = getVariableCosts($conn, $sow_id);
    $avail_boar_variable = getVariableCosts($conn, $boar_id);

    // 2. Strict Check (With tolerance)
    if ($input_sow_cost > ($avail_sow_variable + 0.01)) {
       throw new Exception("STRICT ERROR: Sow Transfer (₱$input_sow_cost) exceeds available operational costs (₱$avail_sow_variable).");
    }
    if ($input_boar_cost > ($avail_boar_variable + 0.01)) {
       throw new Exception("STRICT ERROR: Boar Transfer (₱$input_boar_cost) exceeds available operational costs (₱$avail_boar_variable).");
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
    $transfer_id = $conn->lastInsertId(); // Get ID for reference

    // B. Distribute to Piglets
    $updateStmt = $conn->prepare("UPDATE animal_records SET ACQUISITION_COST = ACQUISITION_COST + ? WHERE ANIMAL_ID = ?");
    foreach ($piglet_ids as $pid) {
        $updateStmt->execute([$cost_per_head, $pid]);
    }

    // --------------------------------------------------------
    // C. LEDGER UPDATE (Save Negative Cost)
    // --------------------------------------------------------
    // We insert a negative entry.
    // Sum = (Existing Positive Costs) + (New Negative Transfer) = Remaining Balance.
    // We DO NOT reset the date anymore.
    
    $opStmt = $conn->prepare("INSERT INTO operational_cost (animal_id, operation_cost, description, datetime_created) VALUES (?, ?, ?, NOW())");
    
    $ref = "Ref: TRF-" . $transfer_id; // Unique reference

    // Deduct from Sow
    if ($input_sow_cost > 0) {
        $neg_sow = $input_sow_cost * -1; // Convert to negative
        $desc_sow = "Transfer: Cost to Piglets ($ref)";
        $opStmt->execute([$sow_id, $neg_sow, $desc_sow]);
    }

    // Deduct from Boar
    if ($input_boar_cost > 0) {
        $neg_boar = $input_boar_cost * -1; // Convert to negative
        $desc_boar = "Transfer: Cost to Piglets ($ref)";
        $opStmt->execute([$boar_id, $neg_boar, $desc_boar]);
    }

    $conn->commit();
    echo json_encode(['success' => true, 'message' => "Transfer successful. Costs deducted from parents."]);

} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) $conn->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>