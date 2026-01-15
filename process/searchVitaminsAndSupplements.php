<?php
// process/searchVitaminsAndSupplements.php
error_reporting(0);
ini_set('display_errors', 0);

include '../config/Connection.php';

header('Content-Type: application/json');

$term = isset($_GET['term']) ? trim($_GET['term']) : '';

if (strlen($term) < 1) {
    echo json_encode([]);
    exit;
}

try {
    if (!isset($conn)) {
        throw new Exception("Database connection failed.");
    }

    // ITEM_TYPE_ID 10 = Vitamins & Supplements
    // We select DISTINCT names to suggest previously purchased items
    $sql = "SELECT DISTINCT ITEM_NAME 
            FROM ITEMS 
            WHERE ITEM_TYPE_ID = 10 
            AND ITEM_NAME LIKE :term 
            ORDER BY ITEM_NAME ASC 
            LIMIT 10";

    $stmt = $conn->prepare($sql);
    $stmt->execute([':term' => '%' . $term . '%']);
    
    $results = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo json_encode($results);

} catch (Exception $e) {
    // Return empty array on error to not break the frontend
    echo json_encode([]);
}
?>