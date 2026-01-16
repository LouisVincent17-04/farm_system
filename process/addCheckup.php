<?php
// ../process/addCheckup.php
session_start();
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json');

include '../config/Connection.php';

// Get User Info (Safe Fallback)
$user_id = !empty($_SESSION['user']['USER_ID']) ? $_SESSION['user']['USER_ID'] : null;
$username = $_SESSION['user']['FULL_NAME'] ?? 'System';
$ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    try {
        if (!isset($conn)) {
            throw new Exception("Database connection failed.");
        }

        $animal_id = $_POST['animal_id'] ?? null;
        $vet_name = trim($_POST['vet_name'] ?? '');
        
        // This receives the full datetime string (e.g., '2026-01-08T14:30')
        $checkup_date = $_POST['checkup_date'] ?? null;
        
        $remarks = trim($_POST['remarks'] ?? '');
        
        // Capture Cost (Default to 0 if empty)
        $cost = !empty($_POST['cost']) ? floatval($_POST['cost']) : 0.00;
        
        if (empty($animal_id) || empty($vet_name) || empty($checkup_date)) {
            throw new Exception('Please fill in all required fields.');
        }
        
        // 1. Check if animal exists and fetch Tag No for Audit Log
        $check_animal_sql = "SELECT TAG_NO FROM ANIMAL_RECORDS WHERE ANIMAL_ID = :animal_id";
        $check_stmt = $conn->prepare($check_animal_sql);
        $check_stmt->execute([':animal_id' => $animal_id]);
        
        $animal_row = $check_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$animal_row) {
            throw new Exception('Invalid animal selected.');
        }
        $tag_no = $animal_row['TAG_NO'];
        
        // ---------------------------------------------------------
        // START TRANSACTION
        // ---------------------------------------------------------
        $conn->beginTransaction();

        // 2. INSERT CHECK_UP
        // The database column CHECKUP_DATE must be DATETIME to store time info
        $sql = "INSERT INTO CHECK_UPS (ANIMAL_ID, VET_NAME, CHECKUP_DATE, REMARKS, COST) 
                VALUES (:animal_id, :vet_name, :checkup_date, :remarks, :cost)";
        
        $stmt = $conn->prepare($sql);
        
        $params = [
            ':animal_id'    => $animal_id,
            ':vet_name'     => $vet_name,
            ':checkup_date' => $checkup_date, // This saves date AND time
            ':remarks'      => $remarks,
            ':cost'         => $cost
        ];
        
        // Execute Insert
        if (!$stmt->execute($params)) {
            throw new Exception('Database Insert Failed.');
        }

        // Capture the new ID
        $new_checkup_id = $conn->lastInsertId();

        // 3. INSERT INTO OPERATIONAL_COST (NEW) 
        // This ensures the cost is tracked in the general ledger immediately.
        if ($cost > 0) {
            $op_sql = "INSERT INTO operational_cost (animal_id, operation_cost, description, datetime_created) 
                       VALUES (:animal_id, :cost, :desc, :date)";
            $op_stmt = $conn->prepare($op_sql);
            
            $op_desc = "Checkup Cost (ID: $new_checkup_id)"; // Link back to specific checkup
            
            $op_stmt->execute([
                ':animal_id' => $animal_id,
                ':cost'      => $cost,
                ':desc'      => $op_desc,
                ':date'      => $checkup_date // Use same timestamp as checkup
            ]);
        }
        
        // 4. INSERT AUDIT LOG
        // Format date nicely for the log (e.g., Jan 08, 2026 02:30 PM)
        $prettyDate = date("M d, Y h:i A", strtotime($checkup_date));
        
        $logDetails = "Added Check-up (ID: $new_checkup_id) for Animal Tag: $tag_no on $prettyDate (Vet: $vet_name, Cost: $cost)";
        
        $log_sql = "INSERT INTO AUDIT_LOGS 
                    (USER_ID, USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS) 
                    VALUES 
                    (:user_id, :username, 'ADD_CHECKUP', 'CHECK_UPS', :details, :ip)";
        
        $log_stmt = $conn->prepare($log_sql);
        
        $log_params = [
            ':user_id'  => $user_id,
            ':username' => $username,
            ':details'  => $logDetails,
            ':ip'       => $ip_address
        ];
        
        if (!$log_stmt->execute($log_params)) {
            throw new Exception("Audit Log Failed.");
        }

        // 5. COMMIT EVERYTHING
        $conn->commit();
        
        echo json_encode([
            'success' => true, 
            'message' => '✅ Check-up recorded successfully!'
        ]);
        
    } catch (Exception $e) {
        // Rollback if anything failed
        if (isset($conn) && $conn->inTransaction()) {
            $conn->rollBack();
        }

        echo json_encode([
            'success' => false, 
            'message' => '❌ Error: ' . $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false, 
        'message' => 'Invalid request method'
    ]);
}
?>