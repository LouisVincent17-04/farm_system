<?php
session_start(); // 1. Start Session
error_reporting(0);
ini_set('display_errors', 0);

include '../config/Connection.php';

// Get User Info
$user_id = !empty($_SESSION['user']['USER_ID']) ? $_SESSION['user']['USER_ID'] : null;
$username = $_SESSION['user']['FULL_NAME'] ?? 'System';
$ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';

$redirect_page = '../views/item_type.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: $redirect_page?status=error&msg=" . urlencode("Invalid request method."));
    exit;
}

if (empty($_POST['item_type_name'])) {
    header("Location: $redirect_page?status=error&msg=" . urlencode("Item Type Name is required."));
    exit;
}

$item_type_name = trim($_POST['item_type_name']);

try {
    if (!isset($conn)) {
        throw new Exception("Database connection failed.");
    }

    // ---------------------------------------------------------
    // START TRANSACTION
    // ---------------------------------------------------------
    $conn->beginTransaction();

    // 1. INSERT NEW ITEM TYPE
    $sqlInsert = "INSERT INTO ITEM_TYPES (ITEM_TYPE_NAME) VALUES (:item_type_name)";
    $stmt = $conn->prepare($sqlInsert);
    
    // Execute Insert
    if (!$stmt->execute([':item_type_name' => $item_type_name])) {
        throw new Exception("Failed to insert item type.");
    }

    // 2. Fetch the last inserted ID
    $new_id = $conn->lastInsertId();
    
    if (!$new_id) {
         throw new Exception("Failed to retrieve new Item Type ID for logging.");
    }

    // 3. INSERT AUDIT LOG
    $logDetails = "Added new Item Type: $item_type_name (ID: $new_id)";
    
    $log_sql = "INSERT INTO AUDIT_LOGS 
                (USER_ID, USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS) 
                VALUES 
                (:user_id, :username, 'ADD_ITEM_TYPE', 'ITEM_TYPES', :details, :ip)";
    
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
    header("Location: $redirect_page?status=success&msg=" . urlencode("Item Type '$item_type_name' added successfully."));
    exit;

} catch (PDOException $e) {
    // Rollback changes
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }

    $errorMsg = $e->getMessage();
    
    // Check for MySQL Duplicate Entry Error (Code 1062)
    if ($e->errorInfo[1] == 1062) {
        $errorMsg = "Item Type '$item_type_name' already exists.";
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