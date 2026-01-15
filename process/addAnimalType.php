<?php
session_start(); // 1. Start Session
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

if (empty($_POST['animal_type_name'])) {
    header("Location: $redirect_page?status=error&msg=" . urlencode("Animal Type Name is required."));
    exit;
}

$animal_type_name = trim($_POST['animal_type_name']);

try {
    // Ensure connection
    if (!isset($conn)) {
        throw new Exception("Database connection failed.");
    }

    // ---------------------------------------------------------
    // START TRANSACTION
    // ---------------------------------------------------------
    $conn->beginTransaction();

    // 1. INSERT NEW ANIMAL TYPE
    $sqlInsert = "INSERT INTO ANIMAL_TYPE (ANIMAL_TYPE_NAME) VALUES (:animal_type_name)";
    $stmt = $conn->prepare($sqlInsert);
    
    // Execute Insert
    if (!$stmt->execute([':animal_type_name' => $animal_type_name])) {
        throw new Exception("Failed to insert animal type.");
    }

    // 2. Fetch the last inserted ID (MySQL equivalent of returning sequence CURRVAL)
    $new_id = $conn->lastInsertId();
    
    if (!$new_id) {
         throw new Exception("Failed to retrieve new Animal Type ID for logging.");
    }

    // 3. INSERT AUDIT LOG
    $logDetails = "Added new Animal Type: $animal_type_name (ID: $new_id)";
    
    $log_sql = "INSERT INTO AUDIT_LOGS 
                (USER_ID, USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS) 
                VALUES 
                (:user_id, :username, 'ADD_ANIMAL_TYPE', 'ANIMAL_TYPE', :details, :ip)";
    
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
    header("Location: $redirect_page?status=success&msg=" . urlencode("Animal Type '$animal_type_name' added successfully."));
    exit;

} catch (PDOException $e) {
    // Rollback changes
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }

    $errorMsg = $e->getMessage();
    
    // Check for MySQL Duplicate Entry Error (Code 1062)
    // This replaces the check for 'ORA-00001'
    if ($e->errorInfo[1] == 1062) {
        $errorMsg = "Animal Type '$animal_type_name' already exists.";
    }

    // Redirect Error
    header("Location: $redirect_page?status=error&msg=" . urlencode($errorMsg));
    exit;

} catch (Exception $e) {
    // Rollback changes for generic exceptions
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }

    header("Location: $redirect_page?status=error&msg=" . urlencode($e->getMessage()));
    exit;
}
?>