<?php
// process/deleteMiscFee.php
session_start();
include '../config/Connection.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fee_id = $_POST['fee_id'] ?? '';
    $animal_id = $_POST['animal_id'] ?? '';

    if (empty($fee_id) || empty($animal_id)) {
        echo json_encode(['success' => false, 'message' => 'Missing identification parameters.']);
        exit;
    }

    try {
        $conn->beginTransaction();

        // 1. Get the amount of the fee we are deleting
        $stmt = $conn->prepare("SELECT AMOUNT FROM animal_misc_fees WHERE FEE_ID = ? AND ANIMAL_ID = ?");
        $stmt->execute([$fee_id, $animal_id]);
        $fee = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$fee) {
            throw new Exception("Fee record not found or already deleted.");
        }

        $amount_to_subtract = $fee['AMOUNT'];

        // 2. Delete the record from the ledger
        $stmtDel = $conn->prepare("DELETE FROM animal_misc_fees WHERE FEE_ID = ?");
        $stmtDel->execute([$fee_id]);

        // 3. Deduct the amount from the animal's running total
        $stmtTotal = $conn->prepare("UPDATE animal_records SET TOTAL_MISC_AMT = TOTAL_MISC_AMT - ? WHERE ANIMAL_ID = ?");
        $stmtTotal->execute([$amount_to_subtract, $animal_id]);

        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Fee deleted successfully.']);

    } catch(Exception $e) {
        $conn->rollBack();
        echo json_encode(['success' => false, 'message' => 'Database Error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}
?>