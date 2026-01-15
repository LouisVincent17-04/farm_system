<?php
session_start();
error_reporting(0);
ini_set('display_errors', 0);

include '../config/Connection.php';

// Get User Info
$user_id = !empty($_SESSION['user']['USER_ID']) ? $_SESSION['user']['USER_ID'] : null;
$username = $_SESSION['user']['FULL_NAME'] ?? 'System';
$ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';

$redirect_page = '../views/building.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: $redirect_page?status=error&msg=" . urlencode("Invalid request method."));
    exit;
}

// Validate essential delete field
if (empty($_POST['building_id']) || !is_numeric($_POST['building_id'])) {
    header("Location: $redirect_page?status=error&msg=" . urlencode("Missing or invalid Building ID is required for deletion."));
    exit;
}

$building_id = trim($_POST['building_id']);

try {
    if (!isset($conn)) {
        throw new Exception("Database connection failed.");
    }

    // ---------------------------------------------------------
    // START TRANSACTION
    // ---------------------------------------------------------
    $conn->beginTransaction();

    // 1. FETCH BUILDING NAME FOR LOGGING AND ERROR MESSAGES
    // We execute this first to ensure the ID exists and to get the name for the audit log
    $name_sql = "SELECT BUILDING_NAME FROM BUILDINGS WHERE BUILDING_ID = :bid";
    $name_stmt = $conn->prepare($name_sql);
    $name_stmt->execute([':bid' => $building_id]);
    
    $building_row = $name_stmt->fetch(PDO::FETCH_ASSOC);
    $building_name = $building_row['BUILDING_NAME'] ?? 'ID ' . $building_id;

    // 2. HARD DELETE BUILDING (Permanent removal)
    $sqlDelete = "DELETE FROM BUILDINGS WHERE BUILDING_ID = :building_id";
    $stmt = $conn->prepare($sqlDelete);
    $stmt->execute([':building_id' => $building_id]);

    // Check if any row was affected
    if ($stmt->rowCount() == 0) {
        // Rollback isn't strictly necessary if nothing changed, but good practice
        $conn->rollBack();
        throw new Exception("Deletion failed: Building '$building_name' was not found in the database.");
    }

    // 3. INSERT AUDIT LOG
    $logDetails = "Permanently Deleted Building: $building_name (ID: $building_id)";
    
    $log_sql = "INSERT INTO AUDIT_LOGS 
                (USER_ID, USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS) 
                VALUES 
                (:user_id, :username, 'HARD_DELETE_BUILDING', 'BUILDINGS', :details, :ip)";
    
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
    header("Location: $redirect_page?status=success&msg=" . urlencode("Building '$building_name' permanently deleted successfully."));
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
        $errorMsg = "Deletion Failed: Building '$building_name' cannot be deleted because it is still linked to one or more **Pens**. Please delete or move all Pens currently housed in this building first.";
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