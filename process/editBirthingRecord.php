<?php
// process/editBirthingRecord.php
session_start();
require_once '../config/Connection.php';
header('Content-Type: application/json');

if($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['success'=>false]); exit; }

// --- AUDIT LOG CONTEXT ---
$user_id = !empty($_SESSION['user']['USER_ID']) ? $_SESSION['user']['USER_ID'] : 1; // Default to 1 (System)
$username = $_SESSION['user']['FULL_NAME'] ?? 'System';
$ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';

try {
    $record_id = $_POST['record_id'];
    $date = $_POST['date_farrowed'];
    $born = $_POST['total_born'];
    $active = $_POST['active_count'];
    $dead = $_POST['dead_count'];
    $mummy = $_POST['mummified_count'];

    // 1. Fetch Info for Audit Log (Get Sow Tag & Parity before update)
    $stmtInfo = $conn->prepare("
        SELECT b.ANIMAL_ID, a.TAG_NO, b.PARITY 
        FROM sow_birthing_records b 
        JOIN animal_records a ON b.ANIMAL_ID = a.ANIMAL_ID 
        WHERE b.RECORD_ID = ?
    ");
    $stmtInfo->execute([$record_id]);
    $info = $stmtInfo->fetch(PDO::FETCH_ASSOC);
    
    $tag = $info['TAG_NO'] ?? 'Unknown';
    $parity = $info['PARITY'] ?? '?';

    // 2. Perform Update
    $sql = "UPDATE sow_birthing_records SET 
            DATE_FARROWED = ?, 
            TOTAL_BORN = ?, 
            ACTIVE_COUNT = ?, 
            DEAD_COUNT = ?, 
            MUMMIFIED_COUNT = ? 
            WHERE RECORD_ID = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([$date, $born, $active, $dead, $mummy, $record_id]);

    // 3. Insert Audit Log
    if ($stmt->rowCount() > 0) {
        $audit_action = "EDIT_BIRTHING_RECORD";
        $audit_details = "Updated Record #$record_id for Sow $tag (Parity $parity). New Data: Born=$born, Active=$active, Dead=$dead, Mummy=$mummy.";

        $audit_sql = "INSERT INTO audit_logs (USER_ID, USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS) 
                      VALUES (?, ?, ?, 'SOW_BIRTHING_RECORDS', ?, ?)";
        $audit_stmt = $conn->prepare($audit_sql);
        $audit_stmt->execute([$user_id, $username, $audit_action, $audit_details, $ip_address]);
    }

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>