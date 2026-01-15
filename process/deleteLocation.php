<?php
session_start();
error_reporting(0);
ini_set('display_errors', 0);

include '../config/Connection.php';

// Get User Info
$user_id = !empty($_SESSION['user']['USER_ID']) ? $_SESSION['user']['USER_ID'] : null;
$username = $_SESSION['user']['FULL_NAME'] ?? 'System';
$ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';

$redirect_page = '../views/location.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: $redirect_page?status=error&msg=" . urlencode("Invalid request method."));
    exit;
}

// Validate essential delete field
if (empty($_POST['location_id']) || !is_numeric($_POST['location_id'])) {
    header("Location: $redirect_page?status=error&msg=" . urlencode("Missing or invalid Location ID is required for deletion."));
    exit;
}

$location_id = trim($_POST['location_id']);

try {
    if (!isset($conn)) {
        throw new Exception("Database connection failed.");
    }

    // ---------------------------------------------------------
    // START TRANSACTION
    // ---------------------------------------------------------
    $conn->beginTransaction();

    // 1. FETCH LOCATION NAME FOR LOGGING AND ERROR MESSAGES
    // We execute this first to ensure the ID exists and to get the name for the audit log
    $name_sql = "SELECT LOCATION_NAME FROM LOCATIONS WHERE LOCATION_ID = :lid";
    $name_stmt = $conn->prepare($name_sql);
    $name_stmt->execute([':lid' => $location_id]);
    
    $location_row = $name_stmt->fetch(PDO::FETCH_ASSOC);
    $location_name = $location_row['LOCATION_NAME'] ?? 'ID ' . $location_id;

    // 2. HARD DELETE LOCATION (Permanent removal)
    $sqlDelete = "DELETE FROM LOCATIONS WHERE LOCATION_ID = :location_id";
    $stmt = $conn->prepare($sqlDelete);
    $stmt->execute([':location_id' => $location_id]);

    // Check if any row was affected
    if ($stmt->rowCount() == 0) {
        // Rollback isn't strictly necessary if nothing changed, but good practice
        $conn->rollBack();
        throw new Exception("Deletion failed: Location '$location_name' was not found in the database.");
    }

    // 3. INSERT AUDIT LOG
    $logDetails = "Permanently Deleted Location: $location_name (ID: $location_id)";
    
    $log_sql = "INSERT INTO AUDIT_LOGS 
                (USER_ID, USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS) 
                VALUES 
                (:user_id, :username, 'HARD_DELETE_LOCATION', 'LOCATIONS', :details, :ip)";
    
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

    // Redirect Success
    header("Location: $redirect_page?status=success&msg=" . urlencode("Location '$location_name' permanently deleted successfully."));
    exit;

} catch (PDOException $e) {
    // Rollback changes
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }

    $errorMsg = $e->getMessage();

    // Check for MySQL Integrity Constraint Violation (Code 1451)
    // This corresponds to Oracle's ORA-02292 (Child record found)
    if ($e->errorInfo[1] == 1451) {
        $errorMsg = "Deletion Failed: Location '$location_name' cannot be deleted because it is linked to one or more **Buildings**. Please delete all associated Buildings first.";
    }

    // Redirect Error
    header("Location: $redirect_page?status=error&msg=" . urlencode($errorMsg));
    exit;

} catch (Exception $e) {
    // Rollback changes
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }

    header("Location: $redirect_page?status=error&msg=" . urlencode($e->getMessage()));
    exit;
}
?>