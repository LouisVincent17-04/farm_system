<?php
// process/sowStatusAction.php
session_start();
require_once '../config/Connection.php';

$user_id = $_SESSION['user_id'] ?? 1; 

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $animal_id = $_POST['animal_id'];
    $action_type = $_POST['action_type']; // 'undo', 'next_stage', 'repeat_service', 'abortion'
    $current_status = $_POST['current_status'];
    
    // NEW INPUTS
    $service_type = $_POST['service_type'] ?? 'Natural';
    $boar_id = !empty($_POST['boar_id']) ? $_POST['boar_id'] : null;
    
    // Use provided date or default to NOW
    $service_date = !empty($_POST['service_date']) ? $_POST['service_date'] : date('Y-m-d H:i:s');

    try {
        $conn->beginTransaction();

        // =========================================================
        // LOGIC FOR UNDO
        // =========================================================
        if ($action_type === 'undo') {
            // ... (Undo logic remains exactly the same as your previous code) ...
            
            $stmt = $conn->prepare("SELECT STATUS_ID, STATUS_NAME FROM sow_status_history WHERE ANIMAL_ID = ? AND IS_ACTIVE = 1");
            $stmt->execute([$animal_id]);
            $currentStatusRow = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$currentStatusRow) throw new Exception("No active status found.");

            // Allow undo for ABORTION status too
            if (strpos($currentStatusRow['STATUS_NAME'], 'SERVICE') === false && $currentStatusRow['STATUS_NAME'] !== 'ABORTION') {
                throw new Exception("Undo is only allowed for SERVICE or ABORTION statuses.");
            }

            $stmtPrev = $conn->prepare("SELECT STATUS_ID FROM sow_status_history WHERE ANIMAL_ID = ? AND STATUS_ID < ? ORDER BY STATUS_ID DESC LIMIT 1");
            $stmtPrev->execute([$animal_id, $currentStatusRow['STATUS_ID']]);
            $prevStatusId = $stmtPrev->fetchColumn();

            if (!$prevStatusId) throw new Exception("No previous status found to revert to.");

            $stmtClose = $conn->prepare("UPDATE sow_status_history SET IS_ACTIVE = 0, STATUS_END_DATE = NOW() WHERE STATUS_ID = ?");
            $stmtClose->execute([$currentStatusRow['STATUS_ID']]);

            $stmtCancel = $conn->prepare("UPDATE sow_service_history SET IS_ACTIVE = 0, IS_CANCELLED = 1, SERVICE_END_DATE = NOW() WHERE ANIMAL_ID = ? AND IS_ACTIVE = 1");
            $stmtCancel->execute([$animal_id]);

            $stmtReactivate = $conn->prepare("UPDATE sow_status_history SET IS_ACTIVE = 1, STATUS_END_DATE = NULL WHERE STATUS_ID = ?");
            $stmtReactivate->execute([$prevStatusId]);

        } 
        // =========================================================
        // LOGIC FOR NEXT STAGE / REPEAT SERVICE / ABORTION
        // =========================================================
        elseif ($action_type === 'next_stage' || $action_type === 'repeat_service' || $action_type === 'abortion') {
            
            // Determine New Status Name
            $new_status = '';

            // 1. Handle Explicit Abortion Action
            if ($action_type === 'abortion') {
                $new_status = 'ABORTION';
            }
            // 2. Handle Standard Progressions
            elseif ($current_status === 'DRY') $new_status = 'SERVICE 1';
            elseif ($current_status === 'SERVICE 1' && $action_type == 'repeat_service') $new_status = 'SERVICE 2';
            elseif ($current_status === 'SERVICE 2' && $action_type == 'repeat_service') $new_status = 'SERVICE 3';
            elseif ($current_status === 'SERVICE 3' && $action_type == 'repeat_service') $new_status = 'SERVICE 4';
            elseif ($current_status === 'SERVICE 4' && $action_type == 'repeat_service') $new_status = 'SERVICE 5';
            elseif (strpos($current_status, 'SERVICE') !== false && $action_type == 'next_stage') $new_status = 'PREGNANT';
            elseif ($current_status === 'PREGNANT' && $action_type == 'next_stage') $new_status = 'BIRTHING';
            elseif ($current_status === 'BIRTHING') $new_status = 'DRY';
            // 3. Handle Recovery from Abortion
            elseif ($current_status === 'ABORTION') $new_status = 'DRY';

            if (empty($new_status)) throw new Exception("Invalid transition.");

            // 1. Close Current Status
            $stmt = $conn->prepare("UPDATE sow_status_history SET IS_ACTIVE = 0, STATUS_END_DATE = ? WHERE ANIMAL_ID = ? AND IS_ACTIVE = 1");
            $stmt->execute([$service_date, $animal_id]); 

            // 2. Close Current Service Record (if any)
            $stmtServ = $conn->prepare("UPDATE sow_service_history SET IS_ACTIVE = 0, SERVICE_END_DATE = ? WHERE ANIMAL_ID = ? AND IS_ACTIVE = 1");
            $stmtServ->execute([$service_date, $animal_id]);

            // 3. Insert New Status
            $stmtNew = $conn->prepare("INSERT INTO sow_status_history (ANIMAL_ID, STATUS_NAME, STATUS_START_DATE, IS_ACTIVE, CREATED_BY) VALUES (?, ?, ?, 1, ?)");
            $stmtNew->execute([$animal_id, $new_status, $service_date, $user_id]);

            // 4. IF NEW STATUS IS A SERVICE, RECORD DETAILS
            if (strpos($new_status, 'SERVICE') !== false) {
                $service_num = (int) filter_var($new_status, FILTER_SANITIZE_NUMBER_INT);
                
                $stmtServNew = $conn->prepare("
                    INSERT INTO sow_service_history 
                    (ANIMAL_ID, SERVICE_NUMBER, SERVICE_TYPE, BOAR_ID, SERVICE_START_DATE, IS_ACTIVE, CREATED_BY) 
                    VALUES (?, ?, ?, ?, ?, 1, ?)
                ");
                $stmtServNew->execute([$animal_id, $service_num, $service_type, $boar_id, $service_date, $user_id]);
            }
        }

        $conn->commit();
        
        // Redirect back logic
        $loc_id = $_GET['location_id'] ?? '';
        $bld_id = $_GET['building_id'] ?? '';

        // Note: Using ../views/animal_sow_status.php to ensure it goes back to the UI
        header("Location: ../views/animal_sow_status.php?animal_id=$animal_id&location_id=$loc_id&building_id=$bld_id");
        exit;

    } catch (Exception $e) {
        $conn->rollBack();
        echo "Error: " . $e->getMessage();
    }
}
?>