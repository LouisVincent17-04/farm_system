<?php
// ../process/deleteCheckup.php
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
    
    try {
        if (!isset($conn)) {
            throw new Exception("Database connection failed.");
        }

        $checkup_id = $_POST['checkup_id'] ?? null;

        if (empty($checkup_id)) {
            throw new Exception('Invalid Check-up ID.');
        }

        // ---------------------------------------------------------
        // START TRANSACTION
        // ---------------------------------------------------------
        $conn->beginTransaction();

        // 1. Fetch Details Before Delete (For Log) & Lock Row
        // Using FOR UPDATE ensures we hold the row until we delete it
        // Note: Replaced TO_CHAR with standard MySQL date formatting if needed, 
        // but MySQL dates usually come out as strings anyway.
        $sqlFetch = "SELECT c.VET_NAME, c.CHECKUP_DATE as C_DATE, a.TAG_NO
                     FROM CHECK_UPS c
                     LEFT JOIN ANIMAL_RECORDS a ON c.ANIMAL_ID = a.ANIMAL_ID
                     WHERE c.CHECK_UP_ID = :id FOR UPDATE";
        
        $fetch_stmt = $conn->prepare($sqlFetch);
        $fetch_stmt->execute([':id' => $checkup_id]);
        
        $row = $fetch_stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            // Rollback (release lock)
            $conn->rollBack();
            throw new Exception("Check-up record not found or already deleted.");
        }

        $vet_name = $row['VET_NAME'];
        $tag_no = $row['TAG_NO'] ?? 'Unknown Animal';
        $date = $row['C_DATE'];

        // 2. Delete Record
        $sqlDelete = "DELETE FROM CHECK_UPS WHERE CHECK_UP_ID = :id";
        $del_stmt = $conn->prepare($sqlDelete);
        
        if (!$del_stmt->execute([':id' => $checkup_id])) {
            throw new Exception("Database Delete Failed.");
        }

        // 3. Audit Log
        $logDetails = "Deleted Check-up (ID: $checkup_id) for Animal: $tag_no (Vet: $vet_name, Date: $date)";
        
        $sqlLog = "INSERT INTO AUDIT_LOGS 
                   (USER_ID, USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS) 
                   VALUES 
                   (:user_id, :username, 'DELETE_CHECKUP', 'CHECK_UPS', :details, :ip)";
        
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

        // 4. Commit
        $conn->commit();
        
        echo json_encode(['success' => true, 'message' => '✅ Check-up deleted successfully.']);
        
        // Note: You can't redirect with headers after echoing JSON unless buffering is on.
        // It's better to handle the redirect on the client-side (Javascript) upon success.
        // If this is a pure form post without AJAX:
        // header('Location: ../views/checkup.php'); 

    } catch (Exception $e) {
        // Rollback on error
        if (isset($conn) && $conn->inTransaction()) {
            $conn->rollBack();
        }
        echo json_encode(['success' => false, 'message' => '❌ Error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>