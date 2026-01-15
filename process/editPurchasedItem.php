<?php
session_start(); // 1. Start Session
header('Content-Type: application/json');
error_reporting(0);
ini_set('display_errors', 0);

include '../config/Connection.php';

// Get User Info (Safe Fallback)
$user_id = !empty($_SESSION['user']['USER_ID']) ? $_SESSION['user']['USER_ID'] : null;
$username = $_SESSION['user']['FULL_NAME'] ?? 'System';
$ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
    exit;
}

// Get and sanitize input data
$item_id = isset($_POST['item_id']) ? trim($_POST['item_id']) : '';
$item_name = isset($_POST['item_name']) ? trim($_POST['item_name']) : '';
$item_description = isset($_POST['item_description']) ? trim($_POST['item_description']) : null;
$item_type_id = isset($_POST['item_type_id']) ? trim($_POST['item_type_id']) : '';
$unit_id = isset($_POST['unit_id']) ? trim($_POST['unit_id']) : '';
$unit_cost = isset($_POST['unit_cost']) ? trim($_POST['unit_cost']) : '';
$item_category = isset($_POST['item_category']) ? trim($_POST['item_category']) : '';
$item_status = isset($_POST['item_status']) ? trim($_POST['item_status']) : '0';
$status_report = isset($_POST['status_report']) ? trim($_POST['status_report']) : null;

// Validate required fields
if (empty($item_id) || empty($item_name) || empty($item_type_id) || 
    empty($unit_id) || $unit_cost === '' || $item_category === '') {
    echo json_encode([
        'success' => false,
        'message' => 'All required fields must be filled'
    ]);
    exit;
}

// Validate numeric fields
if (!is_numeric($unit_cost) || $unit_cost < 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Unit cost must be a valid non-negative number'
    ]);
    exit;
}

// Validate category and status
if (!in_array($item_category, ['0', '1']) || !in_array($item_status, ['0', '1', '2', '3'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid category or status value'
    ]);
    exit;
}

try {
    if (!isset($conn)) {
        throw new Exception("Database connection failed.");
    }

    // ---------------------------------------------------------
    // START TRANSACTION
    // ---------------------------------------------------------
    $conn->beginTransaction();

    // 1. Check if item exists
    $check_sql = "SELECT COUNT(*) FROM ITEMS WHERE ITEM_ID = :item_id";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->execute([':item_id' => $item_id]);
    
    if ($check_stmt->fetchColumn() == 0) {
        throw new Exception('Item not found.');
    }

    // 2. Check if item name already exists for another item
    // Note: MySQL is often case-insensitive by default, but UPPER ensures it.
    $check_name_sql = "SELECT COUNT(*) FROM ITEMS 
                       WHERE UPPER(ITEM_NAME) = UPPER(:item_name) 
                       AND ITEM_ID != :item_id";
    $check_name_stmt = $conn->prepare($check_name_sql);
    $check_name_stmt->execute([':item_name' => $item_name, ':item_id' => $item_id]);
    
    if ($check_name_stmt->fetchColumn() > 0) {
        throw new Exception('An item with this name already exists.');
    }
    
    // 3. GET ORIGINAL DATA FOR AUDIT LOG (locking the row for safety)
    $original_sql = "SELECT ITEM_NAME, UNIT_COST, ITEM_STATUS 
                     FROM ITEMS 
                     WHERE ITEM_ID = :item_id FOR UPDATE";
    $original_stmt = $conn->prepare($original_sql);
    $original_stmt->execute([':item_id' => $item_id]);
    
    $original_row = $original_stmt->fetch(PDO::FETCH_ASSOC);
    
    $original_name = $original_row['ITEM_NAME'] ?? 'N/A';
    $original_cost = $original_row['UNIT_COST'] ?? 'N/A';
    $original_status = $original_row['ITEM_STATUS'] ?? 'N/A';

    // 4. Update item
    // Note: Replaced SYSDATE with NOW()
    $update_sql = "UPDATE ITEMS SET 
                   ITEM_NAME = :item_name,
                   ITEM_DESCRIPTION = :item_description,
                   ITEM_TYPE_ID = :item_type_id,
                   UNIT_ID = :unit_id,
                   UNIT_COST = :unit_cost,
                   ITEM_CATEGORY = :item_category,
                   ITEM_STATUS = :item_status,
                   STATUS_REPORT = :status_report,
                   DATE_UPDATED = NOW() 
                   WHERE ITEM_ID = :item_id";
    
    $update_stmt = $conn->prepare($update_sql);
    
    $update_params = [
        ':item_name'        => $item_name,
        ':item_description' => $item_description,
        ':item_type_id'     => $item_type_id,
        ':unit_id'          => $unit_id,
        ':unit_cost'        => $unit_cost,
        ':item_category'    => $item_category,
        ':item_status'      => $item_status,
        ':status_report'    => $status_report,
        ':item_id'          => $item_id
    ];
    
    if (!$update_stmt->execute($update_params)) {
        throw new Exception('Error updating item.');
    }

    // 5. INSERT AUDIT LOG
    $logDetails = "Updated Item (ID: $item_id). Name: $original_name -> $item_name. Cost: $original_cost -> $unit_cost. Status: $original_status -> $item_status.";
    
    $log_sql = "INSERT INTO AUDIT_LOGS 
                (USER_ID, USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS) 
                VALUES 
                (:user_id, :username, 'EDIT_ITEM_MASTER', 'ITEMS', :details, :ip)";
    
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
    
    // 6. COMMIT EVERYTHING
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => '✅ Item updated successfully!'
    ]);
    
} catch (Exception $e) {
    // Rollback on error
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    
    echo json_encode([
        'success' => false,
        'message' => '❌ An error occurred: ' . $e->getMessage()
    ]);
}
?>