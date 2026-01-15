<?php
session_start();
require_once '../config/Connection.php';
header('Content-Type: application/json');

if($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['success'=>false]); exit; }

try {
    $animal_id = $_POST['animal_id'];
    $date = $_POST['date_farrowed'];
    $born = $_POST['total_born'];
    $active = $_POST['active_count'];
    $dead = $_POST['dead_count'];
    $mummy = $_POST['mummified_count'];

    // 1. Calculate Parity (Count existing + 1)
    $stmt = $conn->prepare("SELECT COUNT(*) FROM sow_birthing_records WHERE ANIMAL_ID = ?");
    $stmt->execute([$animal_id]);
    $current_count = $stmt->fetchColumn();
    $parity = $current_count + 1;

    // 2. Insert
    $sql = "INSERT INTO sow_birthing_records 
            (ANIMAL_ID, PARITY, DATE_FARROWED, TOTAL_BORN, ACTIVE_COUNT, DEAD_COUNT, MUMMIFIED_COUNT) 
            VALUES (?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([$animal_id, $parity, $date, $born, $active, $dead, $mummy]);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>