<?php
// ../process/updatePen.php
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

// Validate essential update fields
if (empty($_POST['pen_id']) || empty($_POST['pen_name']) || empty($_POST['building_id'])) {
    header("Location: $redirect_page?status=error&msg=" . urlencode("Pen ID, Name, and Building are required for update."));
    exit;
}

$pen_id = trim($_POST['pen_id']); // Essential ID for WHERE clause
$pen_name = trim($_POST['pen_name']);
$building_id = trim($_POST['building_id']);

// Numeric Check for IDs
if (!is_numeric($pen_id) || !is_numeric($building_id)) {
    header("Location: $redirect_page?status=error&msg=" . urlencode("Pen ID and Building ID must be numeric values."));
    exit;
}

try {
    if (!isset($conn)) {
        throw new Exception("Database connection failed.");
    }

    // Start Transaction
    $conn->beginTransaction();

    // 1. UPDATE PEN
    // Note: Replaced SYSTIMESTAMP with NOW()
    $sqlUpdate = "UPDATE PENS SET
                    PEN_NAME = :pen_name,
                    BUILDING_ID = :building_id,
                    UPDATED_AT = NOW() 
                WHERE PEN_ID = :pen_id";

    $stmt = $conn->prepare($sqlUpdate);
    
    // Bind parameters
    $params = [
        ':pen_name'    => $pen_name,
        ':building_id' => $building_id,
        ':pen_id'      => $pen_id
    ];

    // Execute Update
    if (!$stmt->execute($params)) {
        throw new Exception("Update failed.");
    }

    // Check if any row was updated
    // Note: In MySQL, if new values are identical to old values, rowCount() returns 0.
    // We check if the ID exists to distinguish between "no changes" and "ID not found".
    if ($stmt->rowCount() == 0) {
        $checkSql = "SELECT 1 FROM PENS WHERE PEN_ID = :id";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->execute([':id' => $pen_id]);
        
        if ($checkStmt->fetchColumn() === false) {
            throw new Exception("Update failed: Pen with ID $pen_id not found.");
        }
    }

    // 2. Get Building Name for better logging
    $bldg_name = "Unknown Building";
    $bldg_sql = "SELECT BUILDING_NAME FROM BUILDINGS WHERE BUILDING_ID = :bid";
    $bldg_stmt = $conn->prepare($bldg_sql);
    $bldg_stmt->execute([':bid' => $building_id]);
    
    if ($row = $bldg_stmt->fetch(PDO::FETCH_ASSOC)) {
        $bldg_name = $row['BUILDING_NAME'];
    }

    // 3. INSERT AUDIT LOG
    $logDetails = "Updated Pen ID $pen_id: '$pen_name' (New Building: $bldg_name)";
    
    $log_sql = "INSERT INTO AUDIT_LOGS 
                (USER_ID, USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS) 
                VALUES 
                (:user_id, :username, 'UPDATE_PEN', 'PENS', :details, :ip)";
    
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
    header("Location: $redirect_page?status=success&msg=" . urlencode("Pen ID $pen_id updated successfully."));
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
        $errorMsg = "Update failed: Pen name '$pen_name' already exists in this building.";
    } 
    // Check for Foreign Key Constraint Failure (MySQL Error 1452)
    // Equivalent to ORA-02291
    elseif ($errorCode == '23000' && (strpos($errorMsg, 'foreign key constraint fails') !== false || (isset($errorInfo[1]) && $errorInfo[1] == 1452))) {
        $errorMsg = "Update failed: Building ID $building_id does not exist. Please select a valid building.";
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