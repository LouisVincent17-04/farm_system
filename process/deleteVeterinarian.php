<?php
// ../process/deleteVeterinarian.php
session_start();
error_reporting(0);
ini_set('display_errors', 0);

include '../config/Connection.php';
include '../security/checkRole.php';

checkRole(3); // Admin

$admin_id = $_SESSION['user']['USER_ID'];
$admin_name = $_SESSION['user']['FULL_NAME'] ?? 'Admin';
$ip_address = $_SERVER['REMOTE_ADDR'];

$redirect_page = '../views/veterinary.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: $redirect_page?status=error&msg=" . urlencode("Invalid request method."));
    exit;
}

$vet_id = trim($_POST['user_id'] ?? '');

if (empty($vet_id)) {
    header("Location: $redirect_page?status=error&msg=" . urlencode("Invalid Veterinarian ID."));
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

    // 1. Fetch Details Before Delete (For Log)
    // MySQL supports FOR UPDATE to lock rows inside a transaction
    $sqlFetch = "SELECT FULL_NAME FROM VETERINARIANS WHERE VET_ID = :id FOR UPDATE";
    $fetch_stmt = $conn->prepare($sqlFetch);
    $fetch_stmt->execute([':id' => $vet_id]);
    
    $row = $fetch_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$row) {
        // Rollback just in case
        $conn->rollBack();
        throw new Exception("Veterinarian not found or already deleted.");
    }
    
    $vet_name = $row['FULL_NAME'];

    // 2. Delete Record
    $sqlDelete = "DELETE FROM VETERINARIANS WHERE VET_ID = :id";
    $del_stmt = $conn->prepare($sqlDelete);
    
    // Execute Delete
    // Note: Exception handling for constraints happens in the catch block
    if (!$del_stmt->execute([':id' => $vet_id])) {
        throw new Exception("Database Delete Failed.");
    }

    // 3. Log Audit
    $logDetails = "Deleted Veterinarian: $vet_name (ID: $vet_id)";
    $sqlLog = "INSERT INTO AUDIT_LOGS (USER_ID, USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS) 
               VALUES (:user_id, :username, 'DELETE_VET', 'VETERINARIANS', :details, :ip)";
    
    $log_stmt = $conn->prepare($sqlLog);
    $log_params = [
        ':user_id'  => $admin_id,
        ':username' => $admin_name,
        ':details'  => $logDetails,
        ':ip'       => $ip_address
    ];
    
    if (!$log_stmt->execute($log_params)) {
        throw new Exception("Audit Log Failed.");
    }

    // 4. Commit
    $conn->commit();

    header("Location: $redirect_page?status=success&msg=" . urlencode("Veterinarian deleted successfully."));
    exit;

} catch (PDOException $e) {
    // Rollback changes
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }

    $errorMsg = "Database Error: " . $e->getMessage();

    // Check for MySQL Integrity Constraint Violation (Code 1451)
    // This corresponds to Oracle's ORA-02292 (Child record found)
    if ($e->errorInfo[1] == 1451) {
        $errorMsg = "Cannot delete: This veterinarian is assigned to medical records.";
    }

    header("Location: $redirect_page?status=error&msg=" . urlencode($errorMsg));
    exit;

} catch (Exception $e) {
    // Rollback generic errors
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    header("Location: $redirect_page?status=error&msg=" . urlencode($e->getMessage()));
    exit;
}
?>