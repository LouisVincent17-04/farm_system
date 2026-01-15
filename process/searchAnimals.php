<?php
// process/searchAnimals.php
require_once '../config/Connection.php';

header('Content-Type: application/json');

$term = isset($_GET['term']) ? trim($_GET['term']) : '';

if (strlen($term) < 2) {
    echo json_encode([]);
    exit;
}

try {
    // Search in both ANIMAL_TYPE and BREEDS tables
    // We use UNION to combine results into a single list
    $sql = "SELECT ANIMAL_TYPE_NAME AS label 
            FROM ANIMAL_TYPE 
            WHERE ANIMAL_TYPE_NAME LIKE :term1
            
            UNION
            
            SELECT BREED_NAME AS label 
            FROM BREEDS 
            WHERE BREED_NAME LIKE :term2
            
            ORDER BY label ASC
            LIMIT 10";

    $stmt = $conn->prepare($sql);
    $searchTerm = "%" . $term . "%";
    
    $stmt->execute([
        ':term1' => $searchTerm,
        ':term2' => $searchTerm
    ]);

    $results = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo json_encode($results);

} catch (PDOException $e) {
    // In production, log the error instead of echoing it
    // error_log($e->getMessage());
    echo json_encode([]);
}
?>