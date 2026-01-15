<?php
// ../process/getAvailableAnimals.php
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');

include '../config/Connection.php';
// include '../config/Queries.php'; // Not needed if we use standard PDO below

try {
    if (!isset($conn)) {
        throw new Exception("Database connection failed.");
    }

    // Query for available purchase records (not yet linked to animals)
    // SQL syntax is compatible with MySQL
    $sql = "SELECT 
            i.ITEM_ID,
            i.ITEM_NAME,
            i.UNIT_COST,
            i.ITEM_DESCRIPTION,
            i.ITEM_NET_WEIGHT,
            i.TOTAL_COST,
            i.LOCATION_ID,
            i.BUILDING_ID,
            i.PEN_ID,
            l.LOCATION_NAME,
            b.BUILDING_NAME,
            p.PEN_NAME
        FROM ITEMS i
        LEFT JOIN LOCATIONS l ON i.LOCATION_ID = l.LOCATION_ID
        LEFT JOIN BUILDINGS b ON i.BUILDING_ID = b.BUILDING_ID
        LEFT JOIN PENS p ON i.PEN_ID = p.PEN_ID
        WHERE i.ITEM_TYPE_ID = 13
        AND i.STATUS = 1
        AND NOT EXISTS (
            SELECT 1
            FROM ANIMAL_RECORDS ar
            WHERE ar.ANIMAL_ITEM_ID = i.ITEM_ID
        )
        ORDER BY i.CREATED_AT DESC";

    $stmt = $conn->prepare($sql);
    $stmt->execute();
    
    // Fetch all results as an associative array
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'items' => $result
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch purchase records: ' . $e->getMessage()
    ]);
}
?>