<?php
// ../process/getAnimalRecords.php
header('Content-Type: application/json');

include '../config/Connection.php';

$pen_id = isset($_GET['pen_id']) ? trim($_GET['pen_id']) : '';

if (empty($pen_id)) {
    echo json_encode([
        'success' => false,
        'message' => 'Pen ID is required',
        'pens' => []
    ]);
    exit;
}

try {
    if (!isset($conn)) {
        throw new Exception("Database connection failed.");
    }

$sql = "SELECT 
                ar.ANIMAL_ID, 
                ar.IS_ACTIVE,
                ar.TAG_NO, 
                b.BREED_NAME 
            FROM ANIMAL_RECORDS ar
            LEFT JOIN BREEDS b ON ar.BREED_ID = b.BREED_ID
            WHERE ar.PEN_ID = :pen_id 
            ORDER BY ar.ANIMAL_ID ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([':pen_id' => $pen_id]);
    
    $animal_record = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'animal_record' => $animal_record,
        'count' => count($animal_record)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching animal records: ' . $e->getMessage(),
        'animal_record' => []
    ]);
}
?>