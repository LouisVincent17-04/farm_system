<?php
// ../process/updateAnimalType.php
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

if (empty($_POST['animal_type_id']) || empty($_POST['animal_type_name'])) {
    header("Location: $redirect_page?status=error&msg=" . urlencode("Animal Type Name is required."));
    exit;
}

$animal_type_id = trim($_POST['animal_type_id']);
$animal_type_name = trim($_POST['animal_type_name']);

try {
    if (!isset($conn)) {
        throw new Exception("Database connection failed.");
    }

    // 1. Start Transaction
    $conn->beginTransaction();

    // 2. Fetch Original Data (For Audit Log Comparison) and Lock Row
    $sqlFetch = "SELECT ANIMAL_TYPE_NAME FROM ANIMAL_TYPE WHERE ANIMAL_TYPE_ID = :id FOR UPDATE";
    $fetch_stmt = $conn->prepare($sqlFetch);
    $fetch_stmt->execute([':id' => $animal_type_id]);
    
    $original_row = $fetch_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$original_row) {
        throw new Exception("Animal Type record not found.");
    }

    $original_name = $original_row['ANIMAL_TYPE_NAME'];

    // 3. Check if name actually changed
    if ($original_name === $animal_type_name) {
        $conn->rollBack(); // Release lock
        header("Location: $redirect_page?status=info&msg=" . urlencode("No changes made."));
        exit;
    }

    // 4. Perform Update
    $sqlUpdate = "UPDATE ANIMAL_TYPE SET ANIMAL_TYPE_NAME = :name WHERE ANIMAL_TYPE_ID = :id";
    $update_stmt = $conn->prepare($sqlUpdate);
    
    if (!$update_stmt->execute([':name' => $animal_type_name, ':id' => $animal_type_id])) {
        throw new Exception("Update failed.");
    }

    // 5. Insert Audit Log
    $logDetails = "Updated Animal Type (ID: $animal_type_id). Name changed from '$original_name' to '$animal_type_name'.";
    
    $log_sql = "INSERT INTO AUDIT_LOGS 
                (USER_ID, USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS) 
                VALUES 
                (:user_id, :username, 'EDIT_ANIMAL_TYPE', 'ANIMAL_TYPE', :details, :ip)";
    
    $log_stmt = $conn->prepare($log_sql);
    $log_params = [
        ':user_id' => $user_id,
        ':username' => $username,
        ':details' => $logDetails,
        ':ip' => $ip_address
    ];

    if (!$log_stmt->execute($log_params)) {
        throw new Exception("Audit Log Failed.");
    }

    // 6. Commit Transaction
    $conn->commit();

    // Redirect Success
    header("Location: $redirect_page?status=success&msg=" . urlencode("Animal Type updated successfully."));
    exit;

} catch (PDOException $e) {
    // Rollback on database error
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }

    $errorMsg = $e->getMessage();
    
    // Check for Unique Constraint Violation (MySQL Error 1062 or SQLSTATE 23000)
    if ($e->getCode() == '23000' || strpos($errorMsg, 'Duplicate entry') !== false) {
        $errorMsg = "Animal Type '$animal_type_name' already exists.";
    }

    // Redirect Error
    header("Location: $redirect_page?status=error&msg=" . urlencode($errorMsg));
    exit;

} catch (Exception $e) {
    // Rollback on generic error
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    
    header("Location: $redirect_page?status=error&msg=" . urlencode($e->getMessage()));
    exit;
}
?>