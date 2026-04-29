<?php
// process/editMiscFee.php
session_start();
include '../config/Connection.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fee_id = $_POST['fee_id'] ?? '';
    $animal_id = $_POST['animal_id'] ?? '';
    $new_amount = $_POST['amount'] ?? 0;
    $description = trim($_POST['description'] ?? '');

    if (empty($fee_id) || empty($animal_id) || empty($new_amount) || empty($description)) {
        echo json_encode(['success' => false, 'message' => 'All fields are required.']);
        exit;
    }

    try {
        $conn->beginTransaction();

        // 1. Get the original amount so we can calculate the difference
        $stmt = $conn->prepare("SELECT AMOUNT FROM animal_misc_fees WHERE FEE_ID = ? AND ANIMAL_ID = ?");
        $stmt->execute([$fee_id, $animal_id]);
        $old_fee = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$old_fee) {
            throw new Exception("Original fee record not found.");
        }

        $old_amount = $old_fee['AMOUNT'];
        $difference = $new_amount - $old_amount;

        // 2. Update the specific fee record
        $stmtUpdate = $conn->prepare("UPDATE animal_misc_fees SET AMOUNT = ?, FEE_DESCRIPTION = ? WHERE FEE_ID = ?");
        $stmtUpdate->execute([$new_amount, $description, $fee_id]);

        // 3. Apply the difference to the running total in animal_records
        // (e.g., if old was 50 and new is 75, difference is +25. If old was 100 and new is 40, difference is -60)
        $stmtTotal = $conn->prepare("UPDATE animal_records SET TOTAL_MISC_AMT = TOTAL_MISC_AMT + ? WHERE ANIMAL_ID = ?");
        $stmtTotal->execute([$difference, $animal_id]);

        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Fee updated successfully.']);

    } catch(Exception $e) {
        $conn->rollBack();
        echo json_encode(['success' => false, 'message' => 'Database Error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}
?>