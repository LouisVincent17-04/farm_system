<?php
// process/getVitaminsByLocation.php
error_reporting(0);
include '../config/Connection.php';

$location_id = $_GET['location_id'] ?? 0;

if (!$location_id) {
    echo json_encode([]);
    exit;
}

try {
    // Fetch vitamins available at the requested location with stock > 0
    $sql = "SELECT v.SUPPLY_ID, v.SUPPLY_NAME, v.TOTAL_STOCK, u.UNIT_ABBR, v.UNIT_ID 
            FROM VITAMINS_SUPPLEMENTS v 
            LEFT JOIN UNITS u ON v.UNIT_ID = u.UNIT_ID 
            WHERE v.LOCATION_ID = ? AND v.TOTAL_STOCK > 0
            ORDER BY v.SUPPLY_NAME ASC";
            
    $stmt = $conn->prepare($sql);
    $stmt->execute([$location_id]);
    $vitamins = $stmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: application/json');
    echo json_encode($vitamins);

} catch (Exception $e) {
    echo json_encode([]);
}
?>