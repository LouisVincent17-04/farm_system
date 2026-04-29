<?php
// process/addMiscFeeBulk.php
session_start();
include '../config/Connection.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Decode the JSON array of IDs sent from the frontend
    $animal_ids_json = $_POST['animal_ids'] ?? '[]';
    $animal_ids = json_decode($animal_ids_json, true);
    
    $amount = floatval($_POST['amount'] ?? 0);
    $description = trim($_POST['description'] ?? '');

    if (empty($animal_ids) || !is_array($animal_ids) || $amount <= 0 || empty($description)) {
        echo json_encode(['success' => false, 'message' => 'Invalid data. Please ensure animals are selected, amount is > 0, and description is provided.']);
        exit;
    }

    try {
        $conn->beginTransaction();

        // Prepare statements for efficiency in the loop
        $stmtLedger = $conn->prepare("INSERT INTO animal_misc_fees (ANIMAL_ID, AMOUNT, FEE_DESCRIPTION) VALUES (?, ?, ?)");
        $stmtTotal  = $conn->prepare("UPDATE animal_records SET TOTAL_MISC_AMT = TOTAL_MISC_AMT + ? WHERE ANIMAL_ID = ?");

        foreach ($animal_ids as $id) {
            // Insert into the history ledger
            $stmtLedger->execute([$id, $amount, $description]);
            
            // Update the running total in the animal's profile
            $stmtTotal->execute([$amount, $id]);
        }

        $conn->commit();
        $count = count($animal_ids);
        echo json_encode(['success' => true, 'message' => "Successfully applied ₱" . number_format($amount, 2) . " to $count animal(s)."]);

    } catch(PDOException $e) {
        $conn->rollBack();
        echo json_encode(['success' => false, 'message' => 'Database Error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}
?>