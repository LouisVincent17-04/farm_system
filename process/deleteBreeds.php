<?php
session_start();
error_reporting(0);
ini_set('display_errors', 0);

include '../config/Connection.php';

// Get User Info
$user_id = !empty($_SESSION['user']['USER_ID']) ? $_SESSION['user']['USER_ID'] : null;
$username = $_SESSION['user']['FULL_NAME'] ?? 'System';
$ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';

$redirect_page = '../views/breed.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: $redirect_page?status=error&msg=" . urlencode("Invalid request method."));
    exit;
}

if (empty($_POST['breed_id']) || !is_numeric($_POST['breed_id'])) {
    header("Location: $redirect_page?status=error&msg=" . urlencode("Missing or invalid Breed ID."));
    exit;
}

$breed_id = trim($_POST['breed_id']);

try {
    if (!isset($conn)) {
        throw new Exception("Database connection failed.");
    }

    // ---------------------------------------------------------
    // START TRANSACTION
    // ---------------------------------------------------------
    $conn->beginTransaction();

    // 1. FETCH NAME FOR LOGGING AND ERROR MESSAGES
    $name_sql = "SELECT BREED_NAME FROM BREEDS WHERE BREED_ID = :bid";
    $name_stmt = $conn->prepare($name_sql);
    $name_stmt->execute([':bid' => $breed_id]);
    
    $breed_row = $name_stmt->fetch(PDO::FETCH_ASSOC);
    $breed_name = $breed_row['BREED_NAME'] ?? 'ID ' . $breed_id;

    // 2. HARD DELETE BREED
    $sqlDelete = "DELETE FROM BREEDS WHERE BREED_ID = :breed_id";
    $stmt = $conn->prepare($sqlDelete);
    $stmt->execute([':breed_id' => $breed_id]);

    if ($stmt->rowCount() == 0) {
        // Rollback isn't strictly necessary if nothing changed, but good practice
        $conn->rollBack();
        throw new Exception("Deletion failed: Breed '$breed_name' was not found in the database.");
    }

    // 3. INSERT AUDIT LOG
    $logDetails = "Permanently Deleted Breed: $breed_name (ID: $breed_id)";
    
    $log_sql = "INSERT INTO AUDIT_LOGS (USER_ID, USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS) 
                VALUES (:user_id, :username, 'HARD_DELETE_BREED', 'BREEDS', :details, :ip)";
    
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

    header("Location: $redirect_page?status=success&msg=" . urlencode("Breed '$breed_name' permanently deleted successfully."));
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
        $errorMsg = "Deletion Failed: Breed '$breed_name' cannot be deleted because it is linked to one or more **Animal Records**. Please move or delete all associated Animals first.";
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