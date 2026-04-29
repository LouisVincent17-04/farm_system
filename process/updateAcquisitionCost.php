<?php
// process/updateAcquisitionCost.php
session_start();
include '../config/Connection.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Decode the JSON array of IDs sent from the frontend
    $animal_ids_json = $_POST['animal_ids'] ?? '[]';
    $animal_ids = json_decode($animal_ids_json, true);
    
    // Acquisition cost can theoretically be 0 (e.g., born on farm), so just ensure it's numeric and >= 0
    $amount = isset($_POST['amount']) ? floatval($_POST['amount']) : -1;

    if (empty($animal_ids) || !is_array($animal_ids) || $amount < 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid data. Please ensure animals are selected and amount is valid.']);
        exit;
    }

    try {
        $conn->beginTransaction();

        $stmt = $conn->prepare("UPDATE animal_records SET ACQUISITION_COST = ? WHERE ANIMAL_ID = ?");

        foreach ($animal_ids as $id) {
            $stmt->execute([$amount, $id]);
        }

        $conn->commit();
        $count = count($animal_ids);
        echo json_encode(['success' => true, 'message' => "Successfully updated the acquisition cost to ₱" . number_format($amount, 2) . " for $count animal(s)."]);

    } catch(PDOException $e) {
        $conn->rollBack();
        echo json_encode(['success' => false, 'message' => 'Database Error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}
?>