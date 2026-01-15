<?php
// process/sow_status_action.php
session_start();
require_once '../config/Connection.php';

$user_id = $_SESSION['user']['USER_ID'] ?? 1; // Default to 1 if no session

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $animal_id = $_POST['animal_id'];
    $action_type = $_POST['action_type']; // 'undo', 'next_stage', 'repeat_service'
    $current_status = $_POST['current_status'];

    try {
        $conn->beginTransaction();

        // =========================================================
        // LOGIC FOR UNDO
        // =========================================================
        if ($action_type === 'undo') {
            
            // 1. Get Current Active Status ID
            $stmt = $conn->prepare("SELECT STATUS_ID, STATUS_NAME FROM sow_status_history WHERE ANIMAL_ID = ? AND IS_ACTIVE = 1");
            $stmt->execute([$animal_id]);
            $currentStatusRow = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$currentStatusRow) throw new Exception("No active status found.");

            // 2. Validate UNDO Eligibility (Only Service 1-5 allowed)
            if (strpos($currentStatusRow['STATUS_NAME'], 'SERVICE') === false) {
                throw new Exception("Undo is only allowed for SERVICE statuses.");
            }

            // 3. Find Previous Status ID to reactivate
            $stmtPrev = $conn->prepare("SELECT STATUS_ID FROM sow_status_history WHERE ANIMAL_ID = ? AND STATUS_ID < ? ORDER BY STATUS_ID DESC LIMIT 1");
            $stmtPrev->execute([$animal_id, $currentStatusRow['STATUS_ID']]);
            $prevStatusId = $stmtPrev->fetchColumn();

            if (!$prevStatusId) throw new Exception("No previous status found to revert to.");

            // 4. Close Current Status
            $stmtClose = $conn->prepare("UPDATE sow_status_history SET IS_ACTIVE = 0, STATUS_END_DATE = NOW() WHERE STATUS_ID = ?");
            $stmtClose->execute([$currentStatusRow['STATUS_ID']]);

            // 5. Cancel Active Service Record (The Undo Logic)
            $stmtCancel = $conn->prepare("UPDATE sow_service_history SET IS_ACTIVE = 0, IS_CANCELLED = 1, SERVICE_END_DATE = NOW() WHERE ANIMAL_ID = ? AND IS_ACTIVE = 1");
            $stmtCancel->execute([$animal_id]);

            // 6. Reactivate Previous Status
            $stmtReactivate = $conn->prepare("UPDATE sow_status_history SET IS_ACTIVE = 1, STATUS_END_DATE = NULL WHERE STATUS_ID = ?");
            $stmtReactivate->execute([$prevStatusId]);

        } 
        // =========================================================
        // LOGIC FOR NEXT STAGE (Progression)
        // =========================================================
        elseif ($action_type === 'next_stage' || $action_type === 'repeat_service') {
            
            // Determine New Status Name
            $new_status = '';
            if ($current_status === 'DRY') $new_status = 'SERVICE 1';
            elseif ($current_status === 'SERVICE 1' && $action_type == 'repeat_service') $new_status = 'SERVICE 2';
            elseif ($current_status === 'SERVICE 2' && $action_type == 'repeat_service') $new_status = 'SERVICE 3';
            elseif ($current_status === 'SERVICE 3' && $action_type == 'repeat_service') $new_status = 'SERVICE 4';
            elseif ($current_status === 'SERVICE 4' && $action_type == 'repeat_service') $new_status = 'SERVICE 5';
            elseif (strpos($current_status, 'SERVICE') !== false && $action_type == 'next_stage') $new_status = 'PREGNANT';
            elseif ($current_status === 'PREGNANT') $new_status = 'BIRTHING';
            elseif ($current_status === 'BIRTHING') $new_status = 'DRY';

            if (empty($new_status)) throw new Exception("Invalid transition.");

            // 1. Close Current Status
            $stmt = $conn->prepare("UPDATE sow_status_history SET IS_ACTIVE = 0, STATUS_END_DATE = NOW() WHERE ANIMAL_ID = ? AND IS_ACTIVE = 1");
            $stmt->execute([$animal_id]);

            // 2. Close Current Service Record (if any)
            $stmtServ = $conn->prepare("UPDATE sow_service_history SET IS_ACTIVE = 0, SERVICE_END_DATE = NOW() WHERE ANIMAL_ID = ? AND IS_ACTIVE = 1");
            $stmtServ->execute([$animal_id]);

            // 3. Insert New Status
            $stmtNew = $conn->prepare("INSERT INTO sow_status_history (ANIMAL_ID, STATUS_NAME, STATUS_START_DATE, IS_ACTIVE, CREATED_BY) VALUES (?, ?, NOW(), 1, ?)");
            $stmtNew->execute([$animal_id, $new_status, $user_id]);

            // 4. If New Status is a SERVICE, insert a service record
            if (strpos($new_status, 'SERVICE') !== false) {
                // Extract number from string "SERVICE X"
                $service_num = (int) filter_var($new_status, FILTER_SANITIZE_NUMBER_INT);
                
                $stmtServNew = $conn->prepare("INSERT INTO sow_service_history (ANIMAL_ID, SERVICE_NUMBER, SERVICE_START_DATE, IS_ACTIVE, CREATED_BY) VALUES (?, ?, NOW(), 1, ?)");
                $stmtServNew->execute([$animal_id, $service_num, $user_id]);
            }
        }

        $conn->commit();
        // Redirect back with tag number to reload page
        $stmtTag = $conn->prepare("SELECT TAG_NO FROM animal_records WHERE ANIMAL_ID = ?");
        $stmtTag->execute([$animal_id]);
        $tag = $stmtTag->fetchColumn();
        
        header("Location: ../views/animal_sow_status.php?tag_no=$tag&location_id=" . $_GET['location_id'] . "&building_id=" . $_GET['building_id']);
        exit;

    } catch (Exception $e) {
        $conn->rollBack();
        echo "Error: " . $e->getMessage();
    }
}
?>