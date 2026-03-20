<?php
// process/getPensByBuilding.php
header('Content-Type: application/json');

include '../config/Connection.php';

$building_id = isset($_GET['building_id']) ? trim($_GET['building_id']) : '';
$with_counts = !empty($_GET['with_counts']); // NEW: pass ?with_counts=1 to include animal counts

if (empty($building_id)) {
    echo json_encode([
        'success' => false,
        'message' => 'Building ID is required',
        'pens'    => []
    ]);
    exit;
}

try {
    if (!isset($conn)) {
        throw new Exception("Database connection failed.");
    }

    if ($with_counts) {
        // Return pens WITH a live count of active animals currently in each pen
        $sql = "SELECT p.PEN_ID, p.PEN_NAME,
                       COUNT(a.ANIMAL_ID) AS ANIMAL_COUNT
                FROM Pens p
                LEFT JOIN Animal_Records a
                       ON a.PEN_ID = p.PEN_ID AND a.IS_ACTIVE = 1
                WHERE p.BUILDING_ID = :building_id
                GROUP BY p.PEN_ID, p.PEN_NAME
                ORDER BY p.PEN_NAME ASC";
    } else {
        // Original query — no count overhead
        $sql = "SELECT PEN_ID, PEN_NAME
                FROM Pens
                WHERE BUILDING_ID = :building_id
                ORDER BY PEN_NAME ASC";
    }

    $stmt = $conn->prepare($sql);
    $stmt->execute([':building_id' => $building_id]);
    $pens = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'pens'    => $pens,
        'count'   => count($pens)
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching pens: ' . $e->getMessage(),
        'pens'    => []
    ]);
}
?>