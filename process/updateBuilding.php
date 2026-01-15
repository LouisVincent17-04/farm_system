<?php
// ../process/updateBuilding.php
session_start();
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

// Validate essential update fields
if (empty($_POST['building_id']) || empty($_POST['building_name']) || empty($_POST['location_id'])) {
    header("Location: $redirect_page?status=error&msg=" . urlencode("Building ID, Name, and Location are required for update."));
    exit;
}

$building_id = trim($_POST['building_id']); // Essential ID for WHERE clause
$building_name = trim($_POST['building_name']);
$location_id = trim($_POST['location_id']);

// Numeric Check for IDs
if (!is_numeric($building_id) || !is_numeric($location_id)) {
    header("Location: $redirect_page?status=error&msg=" . urlencode("Building ID and Location ID must be numeric values."));
    exit;
}

try {
    if (!isset($conn)) {
        throw new Exception("Database connection failed.");
    }

    // Start Transaction
    $conn->beginTransaction();

    // 1. UPDATE BUILDING
    // Note: Replaced SYSTIMESTAMP with NOW()
    $sqlUpdate = "UPDATE BUILDINGS SET
                    BUILDING_NAME = :building_name,
                    LOCATION_ID = :location_id,
                    UPDATED_AT = NOW() 
                WHERE BUILDING_ID = :building_id";

    $stmt = $conn->prepare($sqlUpdate);
    
    // Bind parameters
    $params = [
        ':building_name' => $building_name,
        ':location_id'   => $location_id,
        ':building_id'   => $building_id
    ];

    // Execute Update
    if (!$stmt->execute($params)) {
        throw new Exception("Update failed.");
    }

    // Check if any row was updated
    // Note: In MySQL, if new values are identical to old values, rowCount() returns 0.
    // We check if the ID exists to distinguish between "no changes" and "ID not found".
    if ($stmt->rowCount() == 0) {
        $checkSql = "SELECT 1 FROM BUILDINGS WHERE BUILDING_ID = :id";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->execute([':id' => $building_id]);
        
        if ($checkStmt->fetchColumn() === false) {
            throw new Exception("Update failed: Building with ID $building_id not found.");
        }
        // If ID exists but rowCount is 0, it means no changes were needed. We proceed.
    }

    // 2. Get Location Name for better logging
    $loc_name = "Unknown Location";
    $loc_sql = "SELECT LOCATION_NAME FROM LOCATIONS WHERE LOCATION_ID = :lid";
    $loc_stmt = $conn->prepare($loc_sql);
    $loc_stmt->execute([':lid' => $location_id]);
    
    if ($row = $loc_stmt->fetch(PDO::FETCH_ASSOC)) {
        $loc_name = $row['LOCATION_NAME'];
    }

    // 3. INSERT AUDIT LOG
    $logDetails = "Updated Building ID $building_id: '$building_name' (New Location: $loc_name)";
    
    $log_sql = "INSERT INTO AUDIT_LOGS 
                (USER_ID, USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS) 
                VALUES 
                (:user_id, :username, 'UPDATE_BUILDING', 'BUILDINGS', :details, :ip)";
    
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
    header("Location: $redirect_page?status=success&msg=" . urlencode("Building ID $building_id updated successfully."));
    exit;

} catch (PDOException $e) {
    // Rollback
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }

    $errorMsg = $e->getMessage();
    $errorCode = $e->getCode(); // SQLSTATE
    $errorInfo = $e->errorInfo; // [SQLSTATE, DriverCode, Message]

    // Check for Duplicate Entry (MySQL Error 1062)
    // Equivalent to ORA-00001
    if ($errorCode == '23000' && strpos($errorMsg, 'Duplicate entry') !== false) {
        $errorMsg = "Update failed: Building name '$building_name' already exists at this location.";
    } 
    // Check for Foreign Key Constraint Failure (MySQL Error 1452)
    // Equivalent to ORA-02291
    elseif ($errorCode == '23000' && (strpos($errorMsg, 'foreign key constraint fails') !== false || (isset($errorInfo[1]) && $errorInfo[1] == 1452))) {
        $errorMsg = "Update failed: Location ID $location_id does not exist. Please select a valid location.";
    }

    // Redirect Error
    header("Location: $redirect_page?status=error&msg=" . urlencode($errorMsg));
    exit;

} catch (Exception $e) {
    // Rollback generic errors
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    header("Location: $redirect_page?status=error&msg=" . urlencode($e->getMessage()));
    exit;
}
?>