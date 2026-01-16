<?php
// ../process/editCheckup.php
session_start();
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json');

include '../config/Connection.php';

// Get User Info
$user_id = !empty($_SESSION['user']['USER_ID']) ? $_SESSION['user']['USER_ID'] : null;
$username = $_SESSION['user']['FULL_NAME'] ?? 'System';
$ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    $checkup_id = $_POST['checkup_id'] ?? null;
    $animal_id = $_POST['animal_id'] ?? null;
    $vet_name = trim($_POST['vet_name'] ?? '');
    
    // Receives full timestamp (e.g. 2026-01-08T14:30)
    $checkup_date = $_POST['checkup_date'] ?? null;
    
    $remarks = trim($_POST['remarks'] ?? '');
    
    // Capture Cost
    $cost = !empty($_POST['cost']) ? floatval($_POST['cost']) : 0.00;

    try {
        if (empty($checkup_id) || empty($animal_id) || empty($vet_name) || empty($checkup_date)) {
            throw new Exception('Please fill in all required fields.');
        }

        if (!isset($conn)) {
            throw new Exception("Database connection failed.");
        }

        // ---------------------------------------------------------
        // START TRANSACTION
        // ---------------------------------------------------------
        $conn->beginTransaction();

        // 1. Fetch Original Data (For Audit Log) & Lock Row
        $sqlFetch = "SELECT c.ANIMAL_ID, c.VET_NAME, c.REMARKS, c.COST,
                            c.CHECKUP_DATE,
                            a.TAG_NO
                      FROM CHECK_UPS c
                      LEFT JOIN ANIMAL_RECORDS a ON c.ANIMAL_ID = a.ANIMAL_ID
                      WHERE c.CHECK_UP_ID = :id FOR UPDATE";
        
        $fetch_stmt = $conn->prepare($sqlFetch);
        $fetch_stmt->execute([':id' => $checkup_id]);
        
        $original = $fetch_stmt->fetch(PDO::FETCH_ASSOC);

        if (!$original) {
            $conn->rollBack();
            throw new Exception("Check-up record not found.");
        }

        // 2. Compare Changes
        $changes = [];
        
        if ($original['VET_NAME'] !== $vet_name) $changes[] = "Vet: '{$original['VET_NAME']}' -> '$vet_name'";
        
        // Normalize Dates
        $origTime = strtotime($original['CHECKUP_DATE']);
        $newTime = strtotime($checkup_date);
        
        if ($origTime !== $newTime) {
            $origStr = date("M d, Y h:i A", $origTime);
            $newStr = date("M d, Y h:i A", $newTime);
            $changes[] = "Date: '$origStr' -> '$newStr'";
        }
        
        if (abs((float)$original['COST'] - $cost) > 0.01) {
            $changes[] = "Cost: '{$original['COST']}' -> '$cost'";
        }
        
        $old_remarks = $original['REMARKS'] ?? '';
        if ($old_remarks !== $remarks) $changes[] = "Remarks updated";

        if ($original['ANIMAL_ID'] != $animal_id) {
            $sqlTag = "SELECT TAG_NO FROM ANIMAL_RECORDS WHERE ANIMAL_ID = :aid";
            $tag_stmt = $conn->prepare($sqlTag);
            $tag_stmt->execute([':aid' => $animal_id]);
            $new_tag_row = $tag_stmt->fetch(PDO::FETCH_ASSOC);
            $new_tag = $new_tag_row['TAG_NO'] ?? 'Unknown';
            $changes[] = "Animal: '{$original['TAG_NO']}' -> '$new_tag'";
        }

        // If no changes, return success
        if (empty($changes)) {
            $conn->rollBack();
            echo json_encode(['success' => true, 'message' => 'No changes detected.']);
            exit;
        }

        // 3. Update CHECK_UPS Record
        $sqlUpdate = "UPDATE CHECK_UPS SET 
                      ANIMAL_ID = :aid, 
                      VET_NAME = :vet, 
                      CHECKUP_DATE = :cdate, 
                      REMARKS = :rem,
                      COST = :cost,
                      DATE_UPDATED = NOW()
                      WHERE CHECK_UP_ID = :id";
        
        $update_stmt = $conn->prepare($sqlUpdate);
        $update_params = [
            ':aid'   => $animal_id,
            ':vet'   => $vet_name,
            ':cdate' => $checkup_date,
            ':rem'   => $remarks,
            ':cost'  => $cost,
            ':id'    => $checkup_id
        ];

        if (!$update_stmt->execute($update_params)) {
            throw new Exception("Database Update Failed.");
        }

        // ---------------------------------------------------------
        // 4. SYNC OPERATIONAL COST (NEW LOGIC)
        // ---------------------------------------------------------
        // We link via description string. If ID matches, we update it.
        $op_desc_key = "Checkup Cost (ID: " . $checkup_id . ")";

        // Check if an entry already exists for this checkup
        $checkOpStmt = $conn->prepare("SELECT op_cost_id FROM operational_cost WHERE description = ?");
        $checkOpStmt->execute([$op_desc_key]);
        $op_row = $checkOpStmt->fetch(PDO::FETCH_ASSOC);

        if ($cost > 0) {
            if ($op_row) {
                // UPDATE existing operational cost record
                $upOp = $conn->prepare("UPDATE operational_cost SET animal_id = ?, operation_cost = ?, datetime_created = ? WHERE op_cost_id = ?");
                $upOp->execute([$animal_id, $cost, $checkup_date, $op_row['op_cost_id']]);
            } else {
                // INSERT new record (if previously cost was 0 and didn't exist)
                $inOp = $conn->prepare("INSERT INTO operational_cost (animal_id, operation_cost, description, datetime_created) VALUES (?, ?, ?, ?)");
                $inOp->execute([$animal_id, $cost, $op_desc_key, $checkup_date]);
            }
        } else {
            // If new cost is 0, DELETE the existing operational cost record if it exists
            if ($op_row) {
                $delOp = $conn->prepare("DELETE FROM operational_cost WHERE op_cost_id = ?");
                $delOp->execute([$op_row['op_cost_id']]);
            }
        }

        // 5. Audit Log
        $logDetails = "Updated Check-up (ID: $checkup_id). " . implode("; ", $changes);
        
        $sqlLog = "INSERT INTO AUDIT_LOGS 
                   (USER_ID, USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS) 
                   VALUES 
                   (:user_id, :username, 'EDIT_CHECKUP', 'CHECK_UPS', :details, :ip)";
        
        $log_stmt = $conn->prepare($sqlLog);
        $log_params = [
            ':user_id'  => $user_id,
            ':username' => $username,
            ':details'  => $logDetails,
            ':ip'       => $ip_address
        ];
        
        if (!$log_stmt->execute($log_params)) {
            throw new Exception("Audit Log Failed.");
        }

        // 6. Commit
        $conn->commit();
        echo json_encode(['success' => true, 'message' => '✅ Check-up updated successfully!']);

    } catch (Exception $e) {
        if (isset($conn) && $conn->inTransaction()) {
            $conn->rollBack();
        }
        echo json_encode(['success' => false, 'message' => '❌ Error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>