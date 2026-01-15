<?php
// getBreedsByAnimalType.php
header('Content-Type: application/json');

include '../config/Connection.php';

// Get animal type ID from query string
$animal_type_id = isset($_GET['animal_type_id']) ? trim($_GET['animal_type_id']) : '';

// Validate input
if (empty($animal_type_id)) {
    echo json_encode([
        'success' => false,
        'message' => 'Animal type ID is required',
        'breeds' => []
    ]);
    exit;
}

try {
    if (!isset($conn)) {
        throw new Exception("Database connection failed.");
    }

    // Query to get breeds by animal type
    $sql = "SELECT BREED_ID, BREED_NAME 
            FROM Breeds 
            WHERE ANIMAL_TYPE_ID = :animal_type_id 
            ORDER BY BREED_NAME ASC";
    
    $stmt = $conn->prepare($sql);
    
    // Bind and Execute
    $stmt->execute([':animal_type_id' => $animal_type_id]);
    
    // Fetch all results
    $breeds = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'breeds' => $breeds,
        'count' => count($breeds)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching breeds: ' . $e->getMessage(),
        'breeds' => []
    ]);
}
?>