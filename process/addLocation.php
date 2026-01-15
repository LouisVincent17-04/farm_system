<?php
session_start(); // 1. Start Session
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

// Validate input fields
if (empty($_POST['location_name']) || empty($_POST['address'])) {
    header("Location: $redirect_page?status=error&msg=" . urlencode("Missing required fields: Location name and primary address are required."));
    exit;
}

$location_name = trim($_POST['location_name']);
$complete_address = trim($_POST['address']);  
$longitude = isset($_POST['longitude']) ? trim($_POST['longitude']) : null;
$latitude = isset($_POST['latitude']) ? trim($_POST['latitude']) : null;
$city = isset($_POST['city']) ? trim($_POST['city']) : null;
$province = isset($_POST['province']) ? trim($_POST['province']) : null;

// Numeric Checks
if ($longitude !== null && $longitude !== '' && !is_numeric($longitude)) {
    header("Location: $redirect_page?status=error&msg=" . urlencode("Longitude must be a numeric value."));
    exit;
}
if ($latitude !== null && $latitude !== '' && !is_numeric($latitude)) {
    header("Location: $redirect_page?status=error&msg=" . urlencode("Latitude must be a numeric value."));
    exit;
}

// Construct Full Address
$full_address = $complete_address;
if (!empty($city)) {
    $full_address .= ", " . $city;
}
if (!empty($province)) {
    $full_address .= ", " . $province;
}

try {
    if (!isset($conn)) {
        throw new Exception("Database connection failed.");
    }

    // ---------------------------------------------------------
    // START TRANSACTION
    // ---------------------------------------------------------
    $conn->beginTransaction();

    // 1. INSERT NEW LOCATION
    // Note: Replaced SYSTIMESTAMP with NOW()
    $sqlInsert = "INSERT INTO LOCATIONS (
                      LOCATION_NAME, COMPLETE_ADDRESS, LONGITUDE, LATITUDE, CREATED_AT, UPDATED_AT
                  ) VALUES (
                      :location_name, :complete_address, :longitude, :latitude, NOW(), NOW()
                  )";

    $stmt = $conn->prepare($sqlInsert);
    
    $params = [
        ':location_name'    => $location_name,
        ':complete_address' => $full_address,
        ':longitude'        => $longitude,
        ':latitude'         => $latitude
    ];

    // Execute Insert
    if (!$stmt->execute($params)) {
        throw new Exception("Failed to insert location.");
    }

    // 2. Fetch the last inserted ID
    $new_id = $conn->lastInsertId();
    
    if (!$new_id) {
         throw new Exception("Failed to retrieve new Location ID for logging.");
    }

    // 3. INSERT AUDIT LOG
    $logDetails = "Added new Location: $location_name (Address: $full_address, ID: $new_id)";
    
    $log_sql = "INSERT INTO AUDIT_LOGS 
                (USER_ID, USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS) 
                VALUES 
                (:user_id, :username, 'ADD_LOCATION', 'LOCATIONS', :details, :ip)";
    
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
    header("Location: $redirect_page?status=success&msg=" . urlencode("Location '$location_name' added successfully."));
    exit;

} catch (PDOException $e) {
    // Rollback changes
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }

    $errorMsg = $e->getMessage();
    
    // Check for MySQL Duplicate Entry Error (Code 1062)
    if ($e->errorInfo[1] == 1062) {
        $errorMsg = "Location '$location_name' already exists.";
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