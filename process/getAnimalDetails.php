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

        // UPDATED QUERY: Added Mother/Father IDs and LEFT JOINED to get their Tag Numbers
        $sql = "SELECT 
                a.ANIMAL_ID, 
                a.TAG_NO, 
                a.SEX, 
                a.ANIMAL_TYPE_ID, 
                a.BREED_ID, 
                a.ACQUISITION_COST,
                DATE_FORMAT(a.BIRTH_DATE, '%Y-%m-%d') as BIRTH_DATE, 
                a.CURRENT_STATUS, 
                a.LOCATION_ID, 
                a.BUILDING_ID, 
                a.PEN_ID,
                a.WEIGHT_AT_BIRTH,
                a.CURRENT_ESTIMATED_WEIGHT,
                a.CURRENT_ACTUAL_WEIGHT,
                a.ANIMAL_ITEM_ID,
                a.MOTHER_ID,
                a.FATHER_ID,
                m.TAG_NO AS MOTHER_TAG,
                f.TAG_NO AS FATHER_TAG
                FROM Animal_Records a
                LEFT JOIN Animal_Records m ON a.MOTHER_ID = m.ANIMAL_ID
                LEFT JOIN Animal_Records f ON a.FATHER_ID = f.ANIMAL_ID
                WHERE a.ANIMAL_ID = :id";
                
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