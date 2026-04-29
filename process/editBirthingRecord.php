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
    // Start a transaction to ensure data integrity
    $conn->beginTransaction();

    $record_id = $_POST['record_id'];
    $date = $_POST['date_farrowed'];
    $born = (int)$_POST['total_born'];
    $active = isset($_POST['active_count']) ? (int)$_POST['active_count'] : 0;
    $dead = isset($_POST['dead_count']) ? (int)$_POST['dead_count'] : 0;
    $mummy = isset($_POST['mummified_count']) ? (int)$_POST['mummified_count'] : 0;

    // 1. Fetch Info for Audit Log & Generation Logic (Get Sow Tag, Parity, Old Active Count, Location Data)
    $stmtInfo = $conn->prepare("
        SELECT b.ANIMAL_ID, a.TAG_NO, b.PARITY, b.ACTIVE_COUNT as OLD_ACTIVE_COUNT,
               a.LOCATION_ID, a.BUILDING_ID, a.PEN_ID
        FROM sow_birthing_records b 
        JOIN animal_records a ON b.ANIMAL_ID = a.ANIMAL_ID 
        WHERE b.RECORD_ID = ?
    ");
    $stmtInfo->execute([$record_id]);
    $info = $stmtInfo->fetch(PDO::FETCH_ASSOC);
    
    if (!$info) {
        throw new Exception("Birthing record not found.");
    }

    $tag = $info['TAG_NO'] ?? 'Unknown';
    $parity = $info['PARITY'] ?? '?';
    $old_active = (int)$info['OLD_ACTIVE_COUNT'];

    // Calculate the difference to see if we need to add more piglets
    $diff = $active - $old_active;

    // 2. Perform Update on Birthing Record
    $sql = "UPDATE sow_birthing_records SET 
            DATE_FARROWED = ?, 
            TOTAL_BORN = ?, 
            ACTIVE_COUNT = ?, 
            DEAD_COUNT = ?, 
            MUMMIFIED_COUNT = ? 
            WHERE RECORD_ID = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([$date, $born, $active, $dead, $mummy, $record_id]);

    $generated_tags = [];

    // 3. Generate New Piglets if the Active count increased
    if ($diff > 0) {
        $base_tag = $tag . '-P' . $parity . '-';
        
        // Find the highest existing sequence number for this specific sow and parity
        $stmtMax = $conn->prepare("SELECT TAG_NO FROM animal_records WHERE MOTHER_ID = ? AND TAG_NO LIKE ?");
        $stmtMax->execute([$info['ANIMAL_ID'], $base_tag . '%']);
        $existing_tags = $stmtMax->fetchAll(PDO::FETCH_COLUMN);
        
        $max_num = 0;
        foreach ($existing_tags as $t) {
            $parts = explode('-', $t);
            $num = (int)end($parts); // Extract the number at the end of the tag
            if ($num > $max_num) {
                $max_num = $num;
            }
        }
        
        // Prepare insert statement for the new piglets
        // Note: ANIMAL_TYPE_ID 1 is typically Hog, CLASS_ID 1 is typically Piglet
        $insertStmt = $conn->prepare("
            INSERT INTO animal_records 
            (TAG_NO, ANIMAL_TYPE_ID, BIRTH_DATE, SEX, ACQUISITION_COST, CURRENT_STATUS, LOCATION_ID, BUILDING_ID, PEN_ID, IS_ACTIVE, MOTHER_ID, CLASS_ID) 
            VALUES (?, 1, ?, 'U', 0.00, 'Active', ?, ?, ?, 1, ?, 1)
        ");

        for ($i = 1; $i <= $diff; $i++) {
            $max_num++;
            $new_tag = $base_tag . str_pad($max_num, 2, '0', STR_PAD_LEFT);
            $generated_tags[] = $new_tag;
            
            $insertStmt->execute([
                $new_tag,
                $date, // Set BIRTH_DATE to DATE_FARROWED
                $info['LOCATION_ID'],
                $info['BUILDING_ID'],
                $info['PEN_ID'],
                $info['ANIMAL_ID']
            ]);
        }
    }

    // 4. Insert Audit Log
    $audit_action = "EDIT_BIRTHING_RECORD";
    $audit_details = "Updated Record #$record_id for Sow $tag (Parity $parity). New Data: Born=$born, Active=$active, Dead=$dead, Mummy=$mummy.";
    
    if (!empty($generated_tags)) {
        $audit_details .= " Auto-generated $diff additional piglet(s): " . implode(', ', $generated_tags);
    }

    $audit_sql = "INSERT INTO audit_logs (USER_ID, USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS) 
                  VALUES (?, ?, ?, 'SOW_BIRTHING_RECORDS', ?, ?)";
    $audit_stmt = $conn->prepare($audit_sql);
    $audit_stmt->execute([$user_id, $username, $audit_action, $audit_details, $ip_address]);

    // Commit changes
    $conn->commit();

    $responseMsg = empty($generated_tags) 
        ? "Record updated successfully." 
        : "Record updated successfully. Generated " . count($generated_tags) . " new piglet records.";

    echo json_encode(['success' => true, 'message' => $responseMsg]);

} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>