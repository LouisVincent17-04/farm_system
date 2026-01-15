<?php
// process/get_hierarchy_data.php
require_once '../config/Connection.php';

header('Content-Type: application/json');

if (!isset($_GET['action'])) {
    echo json_encode([]);
    exit;
}

$action = $_GET['action'];

try {
    if ($action === 'get_buildings' && isset($_GET['location_id'])) {
        $stmt = $conn->prepare("SELECT BUILDING_ID, BUILDING_NAME FROM BUILDINGS WHERE LOCATION_ID = ? ORDER BY BUILDING_NAME ASC");
        $stmt->execute([$_GET['location_id']]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    } 
    
    elseif ($action === 'get_pens' && isset($_GET['building_id'])) {
        $stmt = $conn->prepare("SELECT PEN_ID, PEN_NAME FROM PENS WHERE BUILDING_ID = ? ORDER BY PEN_NAME ASC");
        $stmt->execute([$_GET['building_id']]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    } 
    
    elseif ($action === 'get_pen_details' && isset($_GET['pen_id'])) {
        // 1. Count active animals in the pen
        $countSql = "SELECT COUNT(*) as count FROM ANIMAL_RECORDS WHERE PEN_ID = ? AND IS_ACTIVE = 1 AND CURRENT_STATUS = 'Active'";
        $stmt = $conn->prepare($countSql);
        $stmt->execute([$_GET['pen_id']]);
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

        // 2. Get Pen Name for display
        $penSql = "SELECT PEN_NAME FROM PENS WHERE PEN_ID = ?";
        $stmt2 = $conn->prepare($penSql);
        $stmt2->execute([$_GET['pen_id']]);
        $penName = $stmt2->fetch(PDO::FETCH_ASSOC)['PEN_NAME'];

        echo json_encode(['count' => $count, 'pen_name' => $penName]);
    }

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>