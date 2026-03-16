<?php
// process/getMedicinesByLocation.php
error_reporting(0);
include '../config/Connection.php';

$location_id = $_GET['location_id'] ?? 0;

if (!$location_id) {
    echo json_encode([]);
    exit;
}

try {
    // Fetches medicines associated with the chosen location
    $sql = "SELECT m.SUPPLY_ID, m.SUPPLY_NAME, m.TOTAL_STOCK, u.UNIT_ABBR, m.UNIT_ID 
            FROM MEDICINES m 
            LEFT JOIN UNITS u ON m.UNIT_ID = u.UNIT_ID 
            WHERE m.LOCATION_ID = ? AND m.TOTAL_STOCK > 0
            ORDER BY m.SUPPLY_NAME ASC";
            
    $stmt = $conn->prepare($sql);
    $stmt->execute([$location_id]);
    $medicines = $stmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: application/json');
    echo json_encode($medicines);

} catch (Exception $e) {
    echo json_encode([]);
}