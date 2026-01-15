<?php
// getBuildingsByLocation.php
header('Content-Type: application/json');

include '../config/Connection.php';

// Get location ID from query string
$location_id = isset($_GET['location_id']) ? trim($_GET['location_id']) : '';

// Validate input
if (empty($location_id)) {
    echo json_encode([
        'success' => false,
        'message' => 'Location ID is required',
        'buildings' => []
    ]);
    exit;
}

try {
    if (!isset($conn)) {
        throw new Exception("Database connection failed.");
    }

    // Query to get buildings by location
    $sql = "SELECT BUILDING_ID, BUILDING_NAME 
            FROM Buildings 
            WHERE LOCATION_ID = :location_id 
            ORDER BY BUILDING_NAME ASC";
    
    $stmt = $conn->prepare($sql);
    
    // Bind and Execute
    $stmt->execute([':location_id' => $location_id]);
    
    // Fetch all results
    $buildings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'buildings' => $buildings,
        'count' => count($buildings)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching buildings: ' . $e->getMessage(),
        'buildings' => []
    ]);
}
?>