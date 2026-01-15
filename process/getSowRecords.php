<?php
require_once '../config/Connection.php';
header('Content-Type: application/json');

if(!isset($_GET['id'])) { echo json_encode([]); exit; }

$stmt = $conn->prepare("SELECT * FROM sow_birthing_records WHERE ANIMAL_ID = ? ORDER BY PARITY DESC");
$stmt->execute([$_GET['id']]);
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
?>