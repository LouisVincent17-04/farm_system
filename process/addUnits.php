
<?php
// ../process/addUnit.php
session_start();
error_reporting(0);
ini_set('display_errors', 0);

include '../config/Connection.php';

// Get User Info
$user_id = !empty($_SESSION['user']['USER_ID']) ? $_SESSION['user']['USER_ID'] : null;
$username = $_SESSION['user']['FULL_NAME'] ?? 'System';
$ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';

$redirect_page = '../views/units.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: $redirect_page?status=error&msg=" . urlencode("Invalid request method."));
    exit;
}

if (empty($_POST['unit_name']) || empty($_POST['unit_abbreviation'])) {
    header("Location: $redirect_page?status=error&msg=" . urlencode("Unit name and abbreviation are required."));
    exit;
}

$unit_name = trim($_POST['unit_name']);
$unit_abbr = trim($_POST['unit_abbreviation']);

try {
    if (!isset($conn)) {
        throw new Exception("Database connection failed.");
    }

    // ---------------------------------------------------------
    // START TRANSACTION
    // ---------------------------------------------------------
    $conn->beginTransaction();

    // 1. INSERT NEW UNIT
    // Note: Removed RETURNING INTO clause
    $sqlInsert = "INSERT INTO Units (UNIT_NAME, UNIT_ABBR) VALUES (:unit_name, :unit_abbr)";

    $stmt = $conn->prepare($sqlInsert);
    
    $params = [
        ':unit_name' => $unit_name,
        ':unit_abbr' => $unit_abbr
    ];
    
    // Execute Insert
    if (!$stmt->execute($params)) {
        throw new Exception("Failed to insert unit.");
    }

    // Capture the new ID
    $new_id = $conn->lastInsertId();
    
    if (!$new_id) {
         throw new Exception("Failed to retrieve new Unit ID for logging.");
    }

    // 2. INSERT AUDIT LOG
    $logDetails = "Added new Unit: $unit_name ($unit_abbr, ID: $new_id)";
    
    $log_sql = "INSERT INTO AUDIT_LOGS 
                (USER_ID, USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS) 
                VALUES 
                (:user_id, :username, 'ADD_UNIT', 'UNITS', :details, :ip)";
    
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
    header("Location: $redirect_page?status=success&msg=" . urlencode("Unit '$unit_name' added successfully."));
    exit;

} catch (PDOException $e) {
    // Rollback changes
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }

    $errorMsg = $e->getMessage();
    
    // Check for MySQL Duplicate Entry Error (Code 1062)
    if ($e->errorInfo[1] == 1062) {
        $errorMsg = "A Unit with the name '$unit_name' or abbreviation '$unit_abbr' already exists.";
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