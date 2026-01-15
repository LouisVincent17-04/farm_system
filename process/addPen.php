<?php
session_start(); // 1. Start Session
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

// Validate input fields
if (empty($_POST['pen_name']) || empty($_POST['building_id'])) {
    header("Location: $redirect_page?status=error&msg=" . urlencode("Pen Name and Building are required."));
    exit;
}

$pen_name = trim($_POST['pen_name']);
$building_id = trim($_POST['building_id']);

try {
    if (!isset($conn)) {
        throw new Exception("Database connection failed.");
    }

    // ---------------------------------------------------------
    // START TRANSACTION
    // ---------------------------------------------------------
    $conn->beginTransaction();

    // 1. INSERT NEW PEN
    $sqlInsert = "INSERT INTO PENS (PEN_NAME, BUILDING_ID) VALUES (:pen_name, :building_id)";
    $stmt = $conn->prepare($sqlInsert);
    
    // Execute Insert
    if (!$stmt->execute([':pen_name' => $pen_name, ':building_id' => $building_id])) {
        throw new Exception("Failed to insert pen.");
    }

    // 2. Fetch the last inserted ID
    $new_id = $conn->lastInsertId();
    
    if (!$new_id) {
         throw new Exception("Failed to retrieve new Pen ID for logging.");
    }

    // 3. Get Building Name for better logging
    $bldg_name = "Unknown Building";
    $bldg_sql = "SELECT BUILDING_NAME FROM BUILDINGS WHERE BUILDING_ID = :bid";
    $bldg_stmt = $conn->prepare($bldg_sql);
    $bldg_stmt->execute([':bid' => $building_id]);
    
    if ($row = $bldg_stmt->fetch(PDO::FETCH_ASSOC)) {
        $bldg_name = $row['BUILDING_NAME'];
    }

    // 4. INSERT AUDIT LOG
    $logDetails = "Added new Pen: $pen_name (Building: $bldg_name, ID: $new_id)";
    
    $log_sql = "INSERT INTO AUDIT_LOGS 
                (USER_ID, USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS) 
                VALUES 
                (:user_id, :username, 'ADD_PEN', 'PENS', :details, :ip)";
    
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
    header("Location: $redirect_page?status=success&msg=" . urlencode("Pen '$pen_name' added successfully to '$bldg_name'."));
    exit;

} catch (PDOException $e) {
    // Rollback changes
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }

    $errorMsg = $e->getMessage();
    
    // Check for MySQL Duplicate Entry Error (Code 1062)
    if ($e->errorInfo[1] == 1062) {
        $errorMsg = "Pen '$pen_name' already exists in this building.";
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