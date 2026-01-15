<?php
session_start(); // 1. Start Session
error_reporting(0);
ini_set('display_errors', 0);

include '../config/Connection.php';

// Get User Info (Safe Fallback)
$user_id = !empty($_SESSION['user']['USER_ID']) ? $_SESSION['user']['USER_ID'] : null;
$username = $_SESSION['user']['FULL_NAME'] ?? 'System';
$ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';

$redirect_page = '../views/units.php';
$unit_id = 0; // Initialize for finally scope
$unit_name = 'the Unit'; // Default for error message

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: $redirect_page?status=error&msg=" . urlencode("❌ Invalid request method."));
    exit;
}

$unit_id = isset($_POST['unit_id']) ? (int) $_POST['unit_id'] : 0;

if ($unit_id <= 0) {
    header("Location: $redirect_page?status=error&msg=" . urlencode("❌ Invalid Unit ID."));
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

    // 1. FETCH UNIT NAME FIRST (For the Audit Log)
    // We fetch this first to ensure the ID exists and to get the name for logging
    $info_sql = "SELECT UNIT_NAME FROM Units WHERE UNIT_ID = :id";
    $info_stmt = $conn->prepare($info_sql);
    $info_stmt->execute([':id' => $unit_id]);
    
    $unit_row = $info_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$unit_row) {
        // Rollback just in case
        $conn->rollBack();
        header("Location: $redirect_page?status=error&msg=" . urlencode("❌ Unit not found."));
        exit;
    }

    $unit_name = $unit_row['UNIT_NAME'];

    // 2. DELETE THE UNIT
    $sql = "DELETE FROM Units WHERE UNIT_ID = :id";
    $stmt = $conn->prepare($sql);
    
    // Execute Delete
    if (!$stmt->execute([':id' => $unit_id])) {
        throw new Exception("Delete failed.");
    }

    // 3. INSERT AUDIT LOG
    // Note: Removed RECORD_ID column based on your previous input.
    $logDetails = "Deleted Unit: $unit_name (ID: $unit_id)";
    
    $log_sql = "INSERT INTO AUDIT_LOGS 
                (USER_ID, USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS) 
                VALUES 
                (:user_id, :username, 'DELETE_UNIT', 'UNITS', :details, :ip)";
    
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

    // 4. COMMIT EVERYTHING
    $conn->commit();

    header("Location: $redirect_page?status=success&msg=" . urlencode("✅ Unit deleted successfully."));
    exit;

} catch (PDOException $e) {
    // Rollback on error
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }

    $errorMsg = $e->getMessage();

    // Check for MySQL Integrity Constraint Violation (Code 1451)
    // This is equivalent to Oracle's ORA-02292 (Foreign key constraint fails)
    if ($e->errorInfo[1] == 1451) {
        $errorMsg = "❌ Cannot delete $unit_name because it is currently linked to one or more records (e.g., Items, Feeds, Transactions). Please remove all references first.";
    } 
    // Check for Duplicate Entry (Code 1062) - Though rare on delete, corresponds loosely to Oracle's ORA-00001 unique constraint issues
    elseif ($e->errorInfo[1] == 1062) {
        $errorMsg = "❌ Cannot delete $unit_name due to a database integrity issue.";
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