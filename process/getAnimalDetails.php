<?php
// ../process/getAnimalDetails.php
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json');
include '../config/Connection.php';

if (isset($_GET['animal_id'])) {
    $id = $_GET['animal_id'];
    
    try {
        if (!isset($conn)) {
            throw new Exception("Database connection failed.");
        }

        // UPDATED QUERY: Added missing weight columns and ANIMAL_ITEM_ID
        $sql = "SELECT 
                ANIMAL_ID, 
                TAG_NO, 
                SEX, 
                ANIMAL_TYPE_ID, 
                BREED_ID, 
                ACQUISITION_COST,
                DATE_FORMAT(BIRTH_DATE, '%Y-%m-%d') as BIRTH_DATE, 
                CURRENT_STATUS, 
                LOCATION_ID, 
                BUILDING_ID, 
                PEN_ID,
                WEIGHT_AT_BIRTH,            -- Added
                CURRENT_ESTIMATED_WEIGHT,   -- Added
                CURRENT_ACTUAL_WEIGHT,      -- Added
                ANIMAL_ITEM_ID              -- Added (for Purchase link)
                FROM Animal_Records 
                WHERE ANIMAL_ID = :id";
                
        $stmt = $conn->prepare($sql);
        $stmt->execute([':id' => $id]);
        
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($data) {
            echo json_encode(['success' => true, 'data' => $data]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Animal not found']);
        }

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'No ID provided']);
}
?>