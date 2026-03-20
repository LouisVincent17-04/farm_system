<?php
// process/saveTags.php
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json');

session_start();
include '../config/Connection.php';
include '../security/checkRole.php';

$acting_user_id  = !empty($_SESSION['user']['USER_ID']) ? (int)$_SESSION['user']['USER_ID'] : null;
$acting_username = $_SESSION['user']['FULL_NAME'] ?? 'System';
$ip_address      = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Decode JSON payload
    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data || !is_array($data)) {
        echo json_encode(['success' => false, 'message' => 'Invalid data format received.']);
        exit;
    }

    try {
        if (!isset($conn)) throw new Exception("Database connection failed.");

        $conn->beginTransaction();
        
        $updates = 0;
        $audit_log_entries = [];

        // Prepare statements once outside the loop for speed
        $check_stmt = $conn->prepare("SELECT COUNT(*) FROM animal_records WHERE TAG_NO = ? AND ANIMAL_ID != ?");
        $update_stmt = $conn->prepare("UPDATE animal_records SET TAG_NO = ?, UPDATED_AT = NOW() WHERE ANIMAL_ID = ?");
        
        foreach ($data as $item) {
            $animal_id = $item['animal_id'] ?? null;
            $new_tag = trim(strtoupper($item['tag_no'] ?? ''));

            if (empty($animal_id) || empty($new_tag)) {
                continue; // Skip invalid rows
            }

            // 1. Check for duplicates (Make sure no OTHER animal has this tag)
            $check_stmt->execute([$new_tag, $animal_id]);
            if ($check_stmt->fetchColumn() > 0) {
                throw new Exception("Tag '$new_tag' is already assigned to another animal.");
            }

            // 2. Fetch original tag for audit trail
            $orig_stmt = $conn->prepare("SELECT TAG_NO FROM animal_records WHERE ANIMAL_ID = ?");
            $orig_stmt->execute([$animal_id]);
            $old_tag = $orig_stmt->fetchColumn();

            // 3. Update the tag
            if ($old_tag !== $new_tag) {
                $update_stmt->execute([$new_tag, $animal_id]);
                $updates++;
                $audit_log_entries[] = "ID $animal_id: '$old_tag' -> '$new_tag'";
            }
        }

        // 4. Save to Audit Log if anything actually changed
        if ($updates > 0) {
            $logDetails = "Batch Tag Update ($updates animals). Changes: " . implode(" | ", $audit_log_entries);
            $log_stmt = $conn->prepare("INSERT INTO AUDIT_LOGS (USER_ID, USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS) 
                                        VALUES (?, ?, 'BATCH_EDIT_TAGS', 'ANIMAL_RECORDS', ?, ?)");
            $log_stmt->execute([$acting_user_id, $acting_username, $logDetails, $ip_address]);
        }

        $conn->commit();
        echo json_encode(['success' => true, 'message' => "Successfully updated $updates tags."]);

    } catch (Exception $e) {
        if (isset($conn) && $conn->inTransaction()) {
            $conn->rollBack();
        }
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}
?>