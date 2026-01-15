<?php
session_start(); // 1. Start Session
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

// Validate input fields
if (empty($_POST['building_name']) || empty($_POST['location_id'])) {
    header("Location: $redirect_page?status=error&msg=" . urlencode("Building Name and Location are required."));
    exit;
}

$building_name = trim($_POST['building_name']);
$location_id = trim($_POST['location_id']);

try {
    if (!isset($conn)) {
        throw new Exception("Database connection failed.");
    }

    // ---------------------------------------------------------
    // START TRANSACTION
    // ---------------------------------------------------------
    $conn->beginTransaction();

    // 1. INSERT NEW BUILDING
    $sqlInsert = "INSERT INTO BUILDINGS (BUILDING_NAME, LOCATION_ID) VALUES (:building_name, :location_id)";
    $stmt = $conn->prepare($sqlInsert);
    
    // Execute Insert
    if (!$stmt->execute([':building_name' => $building_name, ':location_id' => $location_id])) {
        throw new Exception("Failed to insert building.");
    }

    // 2. Fetch the last inserted ID
    $new_id = $conn->lastInsertId();
    
    if (!$new_id) {
         throw new Exception("Failed to retrieve new Building ID for logging.");
    }

    // 3. Get Location Name for better logging
    $loc_name = "Unknown Location";
    $loc_sql = "SELECT LOCATION_NAME FROM LOCATIONS WHERE LOCATION_ID = :lid";
    $loc_stmt = $conn->prepare($loc_sql);
    $loc_stmt->execute([':lid' => $location_id]);
    
    if ($row = $loc_stmt->fetch(PDO::FETCH_ASSOC)) {
        $loc_name = $row['LOCATION_NAME'];
    }

    // 4. INSERT AUDIT LOG
    $logDetails = "Added new Building: $building_name (Location: $loc_name, ID: $new_id)";
    
    $log_sql = "INSERT INTO AUDIT_LOGS 
                (USER_ID, USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS) 
                VALUES 
                (:user_id, :username, 'ADD_BUILDING', 'BUILDINGS', :details, :ip)";
    
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
    header("Location: $redirect_page?status=success&msg=" . urlencode("Building '$building_name' added successfully."));
    exit;

} catch (PDOException $e) {
    // Rollback changes
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }

    $errorMsg = $e->getMessage();
    
    // Check for MySQL Duplicate Entry Error (Code 1062)
    if ($e->errorInfo[1] == 1062) {
        $errorMsg = "Building '$building_name' already exists at this location.";
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