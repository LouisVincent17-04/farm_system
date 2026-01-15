<?php
// process/editBirthingRecord.php
session_start();
require_once '../config/Connection.php';
header('Content-Type: application/json');

if($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['success'=>false]); exit; }

try {
    $record_id = $_POST['record_id'];
    $date = $_POST['date_farrowed'];
    $born = $_POST['total_born'];
    $active = $_POST['active_count'];
    $dead = $_POST['dead_count'];
    $mummy = $_POST['mummified_count'];

    // Basic Validation: Ensure totals might make sense (optional)
    // if(($active + $dead + $mummy) != $born) {
    //     // throw new Exception("Total Born must equal Active + Dead + Mummified");
    // }

    $sql = "UPDATE sow_birthing_records SET 
            DATE_FARROWED = ?, 
            TOTAL_BORN = ?, 
            ACTIVE_COUNT = ?, 
            DEAD_COUNT = ?, 
            MUMMIFIED_COUNT = ? 
            WHERE RECORD_ID = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([$date, $born, $active, $dead, $mummy, $record_id]);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>