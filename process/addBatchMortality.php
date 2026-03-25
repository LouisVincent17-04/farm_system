<?php
// process/addBatchMortality.php
session_start();
include '../config/Connection.php';

// 1. Read the JSON payload (NOT $_POST)
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!$data || empty($data['records'])) {
    echo json_encode(['success' => false, 'message' => 'No records provided.']);
    exit;
}

$date = $data['date'];
$customer = $data['customer_name']; // Optional buyer
$records = $data['records'];

try {
    $conn->beginTransaction();

    foreach ($records as $rec) {
        $animal_id = $rec['animal_id'];
        $remarks = $rec['remarks'];
        $recovered_cost = (float)$rec['recovered_cost'];

        // 1. Update the animal's status to Deceased
        $updateStmt = $conn->prepare("UPDATE animal_records SET CURRENT_STATUS = 'Deceased', IS_ACTIVE = 0 WHERE ANIMAL_ID = ?");
        $updateStmt->execute([$animal_id]);

        // 2. Insert into your mortality/sales log (adjust table name to your schema)
        $insertStmt = $conn->prepare("INSERT INTO mortality_records (ANIMAL_ID, DATE_OF_DEATH, REMARKS, RECOVERED_COST, BUYER_NAME) VALUES (?, ?, ?, ?, ?)");
        $insertStmt->execute([$animal_id, $date, $remarks, $recovered_cost, $customer]);
    }

    $conn->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    $conn->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>