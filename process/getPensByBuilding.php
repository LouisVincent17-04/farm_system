<?php
// getPensByBuilding.php
header('Content-Type: application/json');

include '../config/Connection.php';

$building_id = isset($_GET['building_id']) ? trim($_GET['building_id']) : '';

if (empty($building_id)) {
    echo json_encode([
        'success' => false,
        'message' => 'Building ID is required',
        'pens' => []
    ]);
    exit;
}

try {
    if (!isset($conn)) {
        throw new Exception("Database connection failed.");
    }

    // Query to get pens by building
    $sql = "SELECT PEN_ID, PEN_NAME
            FROM Pens 
            WHERE BUILDING_ID = :building_id 
            ORDER BY PEN_NAME ASC";
    
    $stmt = $conn->prepare($sql);
    
    // Bind and Execute
    $stmt->execute([':building_id' => $building_id]);
    
    // Fetch all results
    $pens = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'pens' => $pens,
        'count' => count($pens)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching pens: ' . $e->getMessage(),
        'pens' => []
    ]);
}
?>