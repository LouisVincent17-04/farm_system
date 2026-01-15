<?php
// ../process/search_medicines.php
header('Content-Type: application/json');

include '../config/Connection.php';

if (isset($_GET['term'])) {
    $searchTerm = '%' . trim($_GET['term']) . '%';
    
    try {
        if (!isset($conn)) {
            throw new Exception("Database connection failed.");
        }

        // MySQL query
        // Note: ITEM_TYPE_ID = 6 (Breeding/Reproduction based on previous files, 
        // or Medicines depending on your specific DB map. Keeping ID=6 as per your snippet).
        $sql = "SELECT DISTINCT ITEM_NAME 
                FROM ITEMS 
                WHERE ITEM_TYPE_ID = 6 
                AND ITEM_NAME LIKE :search_term
                ORDER BY ITEM_NAME ASC";
        
        $stmt = $conn->prepare($sql);
        
        // Bind and execute
        $stmt->execute([':search_term' => $searchTerm]);
        
        // Fetch all values from the first column (ITEM_NAME) directly into an array
        $results = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        echo json_encode($results);
        
    } catch (Exception $e) {
        // Return empty array on error
        echo json_encode([]);
    }
} else {
    echo json_encode([]);
}
?>