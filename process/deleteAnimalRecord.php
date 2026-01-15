<?php
// ../process/deleteAnimalRecord.php
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json');

// Ensure session is started to access user details for the log
session_start();

include '../config/Connection.php';
include '../security/checkRole.php';

// 1. Get User Info (The user performing the deletion)
$acting_user_id = !empty($_SESSION['user']['USER_ID']) ? (int)$_SESSION['user']['USER_ID'] : null;
$acting_username = $_SESSION['user']['FULL_NAME'] ?? 'Admin'; 
$ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['animal_id'])) {
    $animal_id = $_POST['animal_id'];

    if (empty($animal_id)) {
        echo json_encode(['success' => false, 'message' => 'Invalid Animal ID.']);
        exit;
    }

    try {
        if (!isset($conn)) {
             throw new Exception("Database connection failed.");
        }

        // ---------------------------------------------------------
        // START TRANSACTION
        // ---------------------------------------------------------
        $conn->beginTransaction();

        // 2. Fetch Animal Details BEFORE Deleting (For Audit Log)
        // We use FOR UPDATE to lock the row, ensuring no one else modifies it while we process the delete
        $sqlFetch = "SELECT TAG_NO FROM Animal_Records WHERE ANIMAL_ID = :id FOR UPDATE";
        $fetch_stmt = $conn->prepare($sqlFetch);
        $fetch_stmt->execute([':id' => $animal_id]);
        
        $animal_row = $fetch_stmt->fetch(PDO::FETCH_ASSOC);

        if (!$animal_row) {
            $conn->rollBack(); // Release lock if it existed
            echo json_encode(['success' => false, 'message' => 'Record not found or already deleted.']);
            exit;
        }

        // Prepare details for the log
        $TAG_NO = $animal_row['TAG_NO'] ?? 'N/A';

        // 3. Perform DELETE
        $sqlDelete = "DELETE FROM Animal_Records WHERE ANIMAL_ID = :id";
        $delete_stmt = $conn->prepare($sqlDelete);
        
        if (!$delete_stmt->execute([':id' => $animal_id])) {
            throw new Exception("Failed to delete record.");
        }

        // 4. INSERT AUDIT LOG
        // Note: Removed $species from the log string as it wasn't selected in step 2
        $logDetails = "Deleted Animal Record (ID: $animal_id). Tag: $TAG_NO.";
        
        $sqlLog = "INSERT INTO AUDIT_LOGS 
                   (USER_ID, USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS) 
                   VALUES 
                   (:user_id, :username, 'DELETE_ANIMAL', 'ANIMAL_RECORDS', :details, :ip)";
        
        $log_stmt = $conn->prepare($sqlLog);
        
        $log_params = [
            ':user_id'  => $acting_user_id,
            ':username' => $acting_username,
            ':details'  => $logDetails,
            ':ip'       => $ip_address
        ];
        
        if (!$log_stmt->execute($log_params)) {
             throw new Exception("Audit Log Failed.");
        }

        // 5. COMMIT EVERYTHING
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Animal record deleted successfully.']);

    } catch (PDOException $e) {
        // Rollback
        if (isset($conn) && $conn->inTransaction()) {
            $conn->rollBack();
        }

        // Handle MySQL Foreign Key Constraint Error (Code 1451)
        // This is equivalent to Oracle's ORA-02292
        if ($e->errorInfo[1] == 1451) {
            $msg = "Cannot delete: This animal has related records (Vaccinations, Medical History, etc.). Please archive instead.";
            echo json_encode(['success' => false, 'message' => $msg]);
        } else {
            echo json_encode(['success' => false, 'message' => "Database Error: " . $e->getMessage()]);
        }

    } catch (Exception $e) {
        // Rollback generic errors
        if (isset($conn) && $conn->inTransaction()) {
            $conn->rollBack();
        }
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }

} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}
?>