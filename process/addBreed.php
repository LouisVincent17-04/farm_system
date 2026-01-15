<?php
session_start(); // 1. Start Session
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

// Validate input fields
if (empty($_POST['breed_name']) || empty($_POST['animal_type_id'])) {
    header("Location: $redirect_page?status=error&msg=" . urlencode("Missing required fields."));
    exit;
}

$breed_name = trim($_POST['breed_name']);
$animal_type_id = trim($_POST['animal_type_id']);

try {
    if (!isset($conn)) {
        throw new Exception("Database connection failed.");
    }

    // ---------------------------------------------------------
    // START TRANSACTION
    // ---------------------------------------------------------
    $conn->beginTransaction();

    // 1. INSERT NEW BREED
    $sqlInsert = "INSERT INTO BREEDS (BREED_NAME, ANIMAL_TYPE_ID) VALUES (:breed_name, :animal_type_id)";
    $stmt = $conn->prepare($sqlInsert);
    
    // Execute Insert
    if (!$stmt->execute([':breed_name' => $breed_name, ':animal_type_id' => $animal_type_id])) {
        throw new Exception("Failed to insert breed.");
    }

    // 2. Fetch the last inserted ID
    $new_id = $conn->lastInsertId();
    
    if (!$new_id) {
         throw new Exception("Failed to retrieve new Breed ID for logging.");
    }

    // 3. Get Animal Type Name for better logging
    $type_name = "Unknown Type";
    $type_sql = "SELECT ANIMAL_TYPE_NAME FROM ANIMAL_TYPE WHERE ANIMAL_TYPE_ID = :id";
    $type_stmt = $conn->prepare($type_sql);
    $type_stmt->execute([':id' => $animal_type_id]);
    
    if ($row = $type_stmt->fetch(PDO::FETCH_ASSOC)) {
        $type_name = $row['ANIMAL_TYPE_NAME'];
    }

    // 4. INSERT AUDIT LOG
    $logDetails = "Added new Breed: $breed_name (Type: $type_name, ID: $new_id)";
    
    $log_sql = "INSERT INTO AUDIT_LOGS 
                (USER_ID, USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS) 
                VALUES 
                (:user_id, :username, 'ADD_BREED', 'BREEDS', :details, :ip)";
    
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

    // 5. COMMIT EVERYTHING
    $conn->commit();

    // Redirect Success
    header("Location: $redirect_page?status=success&msg=" . urlencode("Breed '$breed_name' added successfully."));
    exit;

} catch (PDOException $e) {
    // Rollback changes
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }

    $errorMsg = $e->getMessage();
    
    // Check for MySQL Duplicate Entry Error (Code 1062)
    if ($e->errorInfo[1] == 1062) {
        $errorMsg = "Breed '$breed_name' already exists for this animal type.";
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