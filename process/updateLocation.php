<?php
// ../process/updateLocation.php
session_start();
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

// Validate essential update fields
if (empty($_POST['location_id']) || empty($_POST['location_name']) || empty($_POST['address'])) {
    header("Location: $redirect_page?status=error&msg=" . urlencode("Missing required fields: Location ID, name, and primary address are required for update."));
    exit;
}

$location_id = trim($_POST['location_id']); // Essential ID for WHERE clause
$location_name = trim($_POST['location_name']);
$complete_address = trim($_POST['address']);  
$longitude = isset($_POST['longitude']) ? trim($_POST['longitude']) : null;
$latitude = isset($_POST['latitude']) ? trim($_POST['latitude']) : null;
$city = isset($_POST['city']) ? trim($_POST['city']) : null;
$province = isset($_POST['province']) ? trim($_POST['province']) : null;

// Numeric Checks
if (!is_numeric($location_id)) {
    header("Location: $redirect_page?status=error&msg=" . urlencode("Location ID must be a numeric value."));
    exit;
}
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

    // Start Transaction
    $conn->beginTransaction();

    // 1. UPDATE LOCATION
    // Note: Replaced SYSTIMESTAMP with NOW()
    $sqlUpdate = "UPDATE LOCATIONS SET
                    LOCATION_NAME = :location_name,
                    COMPLETE_ADDRESS = :complete_address,
                    LONGITUDE = :longitude,
                    LATITUDE = :latitude,
                    UPDATED_AT = NOW()
                WHERE LOCATION_ID = :location_id";

    $stmt = $conn->prepare($sqlUpdate);
    
    // Bind parameters
    $params = [
        ':location_name'    => $location_name,
        ':complete_address' => $full_address,
        ':longitude'        => $longitude,
        ':latitude'         => $latitude,
        ':location_id'      => $location_id
    ];

    // Execute Update
    if (!$stmt->execute($params)) {
        throw new Exception("Update failed.");
    }

    // Check if any row was updated
    // Note: In MySQL, if new values are identical to old values, rowCount() returns 0.
    // We explicitly check if the ID exists to distinguish "no changes" from "ID not found".
    if ($stmt->rowCount() == 0) {
        $checkSql = "SELECT 1 FROM LOCATIONS WHERE LOCATION_ID = :id";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->execute([':id' => $location_id]);
        
        if ($checkStmt->fetchColumn() === false) {
            throw new Exception("Update failed: Location with ID $location_id not found.");
        }
    }

    // 2. INSERT AUDIT LOG
    $logDetails = "Updated Location ID $location_id: '$location_name' (Address: $full_address)";
    
    $log_sql = "INSERT INTO AUDIT_LOGS 
                (USER_ID, USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS) 
                VALUES 
                (:user_id, :username, 'UPDATE_LOCATION', 'LOCATIONS', :details, :ip)";
    
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

    // 3. COMMIT EVERYTHING
    $conn->commit();

    // Redirect Success
    header("Location: $redirect_page?status=success&msg=" . urlencode("Location ID $location_id updated successfully."));
    exit;

} catch (PDOException $e) {
    // Rollback
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }

    $errorMsg = $e->getMessage();
    
    // Check for Duplicate Entry (MySQL Error 1062 / SQLSTATE 23000)
    // Equivalent to ORA-00001
    if ($e->getCode() == '23000' || strpos($errorMsg, 'Duplicate entry') !== false) {
        $errorMsg = "Update failed: Location name '$location_name' already exists for another record.";
    }

    // Redirect Error
    header("Location: $redirect_page?status=error&msg=" . urlencode($errorMsg));
    exit;

} catch (Exception $e) {
    // Rollback generic error
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    header("Location: $redirect_page?status=error&msg=" . urlencode($e->getMessage()));
    exit;
}
?>