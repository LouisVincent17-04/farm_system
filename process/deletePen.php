<?php
session_start();
error_reporting(0);
ini_set('display_errors', 0);

include '../config/Connection.php';

// Get User Info
$user_id = !empty($_SESSION['user']['USER_ID']) ? $_SESSION['user']['USER_ID'] : null;
$username = $_SESSION['user']['FULL_NAME'] ?? 'System';
$ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';

$redirect_page = '../views/pen.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: $redirect_page?status=error&msg=" . urlencode("Invalid request method."));
    exit;
}

// Validate essential delete field
if (empty($_POST['pen_id'])) {
    header("Location: $redirect_page?status=error&msg=" . urlencode("Missing required field: Pen ID is required for deletion."));
    exit;
}

$pen_id = trim($_POST['pen_id']);

// Numeric Check
if (!is_numeric($pen_id)) {
    header("Location: $redirect_page?status=error&msg=" . urlencode("Pen ID must be a numeric value."));
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

    // 1. FETCH PEN NAME FOR LOGGING AND ERROR MESSAGES
    // We execute this first to ensure the ID exists and to get the name for the audit log
    $name_sql = "SELECT PEN_NAME FROM PENS WHERE PEN_ID = :pid";
    $name_stmt = $conn->prepare($name_sql);
    $name_stmt->execute([':pid' => $pen_id]);
    
    $pen_row = $name_stmt->fetch(PDO::FETCH_ASSOC);
    $pen_name = $pen_row['PEN_NAME'] ?? 'ID ' . $pen_id;

    // 2. HARD DELETE PEN (Permanent removal)
    $sqlDelete = "DELETE FROM PENS WHERE PEN_ID = :pen_id";
    $stmt = $conn->prepare($sqlDelete);
    $stmt->execute([':pen_id' => $pen_id]);

    // Check if any row was affected
    if ($stmt->rowCount() == 0) {
        // Rollback isn't strictly necessary if nothing changed, but good practice
        $conn->rollBack();
        throw new Exception("Deletion failed: Pen with ID $pen_id was not found in the database.");
    }

    // 3. INSERT AUDIT LOG
    $logDetails = "Permanently Deleted Pen: $pen_name (ID: $pen_id)";
    
    $log_sql = "INSERT INTO AUDIT_LOGS 
                (USER_ID, USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS) 
                VALUES 
                (:user_id, :username, 'HARD_DELETE_PEN', 'PENS', :details, :ip)";
    
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
    header("Location: $redirect_page?status=success&msg=" . urlencode("Pen '$pen_name' permanently deleted successfully."));
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
        $errorMsg = "Deletion Failed: Pen '$pen_name' (ID: $pen_id) cannot be deleted because it is still linked to one or more Animals. Please move or delete all Animals currently housed in this pen first.";
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