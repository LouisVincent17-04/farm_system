<?php
session_start();
error_reporting(0);
ini_set('display_errors', 0);

include '../config/Connection.php';

// Get User Info
$user_id = !empty($_SESSION['user']['USER_ID']) ? $_SESSION['user']['USER_ID'] : null;
$username = $_SESSION['user']['FULL_NAME'] ?? 'System';
$ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';

$redirect_page = '../views/animal_type.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: $redirect_page?status=error&msg=" . urlencode("Invalid request method."));
    exit;
}

if (empty($_POST['animal_type_id']) || !is_numeric($_POST['animal_type_id'])) {
    header("Location: $redirect_page?status=error&msg=" . urlencode("Missing or invalid Animal Type ID."));
    exit;
}

$animal_type_id = trim($_POST['animal_type_id']);

try {
    if (!isset($conn)) {
        throw new Exception("Database connection failed.");
    }

    // ---------------------------------------------------------
    // START TRANSACTION
    // ---------------------------------------------------------
    $conn->beginTransaction();

    // 1. FETCH NAME FOR LOGGING AND ERROR MESSAGES
    // We do this first to ensure the ID exists and to get the name for the audit log
    $name_sql = "SELECT ITEM_TYPE_NAME FROM ANIMAL_TYPE WHERE ANIMAL_TYPE_ID = :tid"; 
    $name_stmt = $conn->prepare($name_sql);
    $name_stmt->execute([':tid' => $animal_type_id]);
    
    $type_row = $name_stmt->fetch(PDO::FETCH_ASSOC);
    $type_name = $type_row['ITEM_TYPE_NAME'] ?? 'ID ' . $animal_type_id;

    // 2. HARD DELETE ANIMAL TYPE
    $sqlDelete = "DELETE FROM ANIMAL_TYPE WHERE ANIMAL_TYPE_ID = :animal_type_id";
    $stmt = $conn->prepare($sqlDelete);
    
    // Execute Delete
    $stmt->execute([':animal_type_id' => $animal_type_id]);

    if ($stmt->rowCount() == 0) {
        // Rollback isn't strictly necessary here since nothing changed, but good for consistency
        $conn->rollBack();
        throw new Exception("Deletion failed: Animal Type '$type_name' was not found in the database.");
    }

    // 3. INSERT AUDIT LOG
    $logDetails = "Permanently Deleted Animal Type: $type_name (ID: $animal_type_id)";
    
    $log_sql = "INSERT INTO AUDIT_LOGS (USER_ID, USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS) 
                VALUES (:user_id, :username, 'HARD_DELETE_ANIMAL_TYPE', 'ANIMAL_TYPE', :details, :ip)";
    
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

    header("Location: $redirect_page?status=success&msg=" . urlencode("Animal Type '$type_name' permanently deleted successfully."));
    exit;

} catch (PDOException $e) {
    // Rollback changes
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }

    $errorMsg = $e->getMessage();

    // Check for MySQL Integrity Constraint Violation (Code 1451)
    // This is equivalent to Oracle's ORA-02292 (Child record found)
    if ($e->errorInfo[1] == 1451) {
        $errorMsg = "Deletion Failed: Animal Type '$type_name' cannot be deleted because it is linked to one or more **Breeds** or **Animal Records**. Please delete all associated records first.";
    }

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