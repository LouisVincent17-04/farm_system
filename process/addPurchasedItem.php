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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
    exit;
}

// Get and sanitize input data
$item_name = isset($_POST['item_name']) ? trim($_POST['item_name']) : '';
$item_description = isset($_POST['item_description']) ? trim($_POST['item_description']) : null;
$item_type_id = isset($_POST['item_type_id']) ? trim($_POST['item_type_id']) : '';
$unit_id = isset($_POST['unit_id']) ? trim($_POST['unit_id']) : '';
$unit_cost = isset($_POST['unit_cost']) ? trim($_POST['unit_cost']) : '';
$item_category = isset($_POST['item_category']) ? trim($_POST['item_category']) : '';
$item_status = isset($_POST['item_status']) ? trim($_POST['item_status']) : '0';
$status_report = isset($_POST['status_report']) ? trim($_POST['status_report']) : null;

try {
    if (!isset($conn)) {
        throw new Exception("Database connection failed.");
    }

    // Validate required fields
    if (empty($item_name) || empty($item_type_id) || empty($unit_id) || 
        empty($unit_cost) || $item_category === '') {
        throw new Exception('All required fields must be filled.');
    }

    // Validate numeric fields
    if (!is_numeric($unit_cost) || $unit_cost < 0) {
        throw new Exception('Unit cost must be a valid positive number.');
    }

    // Validate category and status
    if (!in_array($item_category, ['0', '1']) || !in_array($item_status, ['0', '1', '2', '3'])) {
        throw new Exception('Invalid category or status value.');
    }

    // Check if item name already exists
    // Note: MySQL is case-insensitive by default, but we can stick to simple selection
    $check_sql = "SELECT COUNT(*) FROM ITEMS WHERE ITEM_NAME = :item_name";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->execute([':item_name' => $item_name]);
    
    if ($check_stmt->fetchColumn() > 0) {
        throw new Exception('An item with this name already exists.');
    }

    // ---------------------------------------------------------
    // START TRANSACTION
    // ---------------------------------------------------------
    $conn->beginTransaction();

    // 1. Insert new item
    // Note: Replaced SYSTIMESTAMP with NOW()
    // Note: Ensure your ITEMS table has columns 'ITEM_STATUS' and 'STATUS_REPORT' 
    // (If using the initial script, these might map to 'STATUS' or need new columns)
    $insert_sql = "INSERT INTO ITEMS 
                    (ITEM_NAME, ITEM_DESCRIPTION, ITEM_TYPE_ID, UNIT_ID, UNIT_COST, 
                     ITEM_CATEGORY, ITEM_STATUS, STATUS_REPORT, CREATED_AT) 
                    VALUES 
                    (:item_name, :item_description, :item_type_id, :unit_id, :unit_cost, 
                     :item_category, :item_status, :status_report, NOW())";
    
    $insert_stmt = $conn->prepare($insert_sql);
    
    $params = [
        ':item_name'        => $item_name,
        ':item_description' => $item_description,
        ':item_type_id'     => $item_type_id,
        ':unit_id'          => $unit_id,
        ':unit_cost'        => $unit_cost,
        ':item_category'    => $item_category,
        ':item_status'      => $item_status,
        ':status_report'    => $status_report
    ];
    
    // Execute insert
    if (!$insert_stmt->execute($params)) {
        throw new Exception('Error adding item.');
    }

    // 2. Fetch the last inserted ID
    $new_id = $conn->lastInsertId();
    
    if (!$new_id) {
         throw new Exception("Failed to retrieve new Item ID for logging.");
    }

    // 3. INSERT AUDIT LOG
    $logDetails = "Added new General Item: $item_name (Type ID: $item_type_id, ID: $new_id)";
    
    // Adjusted to match the standard AUDIT_LOGS table structure (ID inside details)
    $log_sql = "INSERT INTO AUDIT_LOGS 
                (USER_ID, USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS) 
                VALUES 
                (:user_id, :username, 'ADD_ITEM', 'ITEMS', :details, :ip)";
    
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

    echo json_encode([
        'success' => true,
        'message' => '✅ Item added successfully! (ID: ' . $new_id . ')'
    ]);
    
} catch (Exception $e) {
    // Rollback if anything failed
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }

    echo json_encode([
        'success' => false,
        'message' => '❌ An error occurred: ' . $e->getMessage()
    ]);
}
?>