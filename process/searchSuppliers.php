<?php
// process/searchSuppliers.php
require_once '../config/Connection.php';
header('Content-Type: application/json');

$term = $_GET['term'] ?? '';

if (strlen($term) < 1) {
    echo json_encode([]);
    exit;
}

try {
    // Modify 'suppliers' and 'SUPPLIER_NAME' if your database columns are named differently
    $stmt = $conn->prepare("SELECT SUPPLIER_NAME FROM suppliers WHERE SUPPLIER_NAME LIKE :term ORDER BY SUPPLIER_NAME ASC LIMIT 10");
    $stmt->execute([':term' => '%' . $term . '%']);
    
    // Fetch as a 1D array of strings (e.g., ["Farm A", "Farm B"])
    $results = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo json_encode($results);
} catch (Exception $e) {
    // Fail silently on frontend by returning empty array
    echo json_encode([]);
}
?>