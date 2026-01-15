<?php
// process/get_transfer_data.php
require_once '../config/Connection.php';
header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

try {
    if ($action === 'get_buildings') {
        $loc_id = $_GET['loc_id'];
        $stmt = $conn->prepare("SELECT BUILDING_ID, BUILDING_NAME FROM buildings WHERE LOCATION_ID = ? ORDER BY BUILDING_NAME");
        $stmt->execute([$loc_id]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    }
    
    elseif ($action === 'get_pens') {
        $bld_id = $_GET['bld_id'];
        $stmt = $conn->prepare("SELECT PEN_ID, PEN_NAME FROM pens WHERE BUILDING_ID = ? ORDER BY PEN_NAME");
        $stmt->execute([$bld_id]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    }
    
    elseif ($action === 'get_animals') {
        $pen_id = $_GET['pen_id'];
        // Join with types and breeds for better display info
        $sql = "SELECT a.ANIMAL_ID, a.TAG_NO, t.ANIMAL_TYPE_NAME, b.BREED_NAME
                FROM animal_records a
                LEFT JOIN animal_type t ON a.ANIMAL_TYPE_ID = t.ANIMAL_TYPE_ID
                LEFT JOIN breeds b ON a.BREED_ID = b.BREED_ID
                WHERE a.PEN_ID = ? AND a.IS_ACTIVE = 1
                ORDER BY a.TAG_NO ASC";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$pen_id]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    }
    
    else {
        echo json_encode([]);
    }

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>