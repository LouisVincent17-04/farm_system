<?php
// process/get_animal_fcr_data.php
require_once '../config/Connection.php';
header('Content-Type: application/json');

if (!isset($_GET['animal_id']) || !isset($_GET['pen_id'])) {
    echo json_encode(['success' => false, 'message' => 'Missing IDs']);
    exit;
}

$animal_id = $_GET['animal_id'];
$pen_id = $_GET['pen_id'];

try {
    // 1. Get Animal Details
    // ADDED: ar.CURRENT_ACTUAL_WEIGHT to the SELECT list
    $stmt = $conn->prepare("
        SELECT 
            ar.CLASS_ID,
            ac.FCR as STANDARD_FCR,
            ar.WEIGHT_AT_BIRTH as BIRTH_WEIGHT,
            ar.CURRENT_ACTUAL_WEIGHT 
        FROM animal_records ar
        LEFT JOIN animal_classifications ac ON ar.CLASS_ID = ac.CLASS_ID
        WHERE ar.ANIMAL_ID = ?
    ");
    $stmt->execute([$animal_id]);
    $animal = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$animal) throw new Exception("Animal not found");

    // 2. Calculate Feed Share (Logic unchanged)
    $stmtAnimals = $conn->prepare("SELECT ANIMAL_ID FROM animal_records WHERE PEN_ID = ? AND IS_ACTIVE = 1");
    $stmtAnimals->execute([$pen_id]);
    $animal_ids = $stmtAnimals->fetchAll(PDO::FETCH_COLUMN);
    
    $animal_count = count($animal_ids);
    $total_pen_feed = 0;

    if ($animal_count > 0) {
        $placeholders = implode(',', array_fill(0, $animal_count, '?'));
        $stmtFeed = $conn->prepare("SELECT SUM(QUANTITY_KG) FROM feed_transactions WHERE ANIMAL_ID IN ($placeholders)");
        $stmtFeed->execute($animal_ids);
        $total_pen_feed = $stmtFeed->fetchColumn() ?: 0;
    } else {
        $animal_count = 1; 
    }

    $feed_share = $total_pen_feed / $animal_count;

    echo json_encode([
        'success' => true,
        'birth_weight' => (float)$animal['BIRTH_WEIGHT'],
        // Return the actual weight from DB
        'current_actual_weight' => (float)$animal['CURRENT_ACTUAL_WEIGHT'], 
        'feed_share' => round($feed_share, 2),
        'standard_fcr' => (float)($animal['STANDARD_FCR'] ?? 0.30),
        'class_id' => $animal['CLASS_ID']
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>