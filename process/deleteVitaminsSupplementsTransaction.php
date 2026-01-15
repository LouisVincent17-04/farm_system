<?php
session_start(); // 1. Start Session
error_reporting(0);
ini_set('display_errors', 0);
require_once '../config/Connection.php';

// Get User Info (Safe Fallback)
$user_id = !empty($_SESSION['user']['USER_ID']) ? $_SESSION['user']['USER_ID'] : null;
$username = $_SESSION['user']['FULL_NAME'] ?? 'System';
$ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';

$redirect_page = '../views/vitamins_supplements_transaction.php';
$vst_id = null; // Initialize for scope in finally block

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: $redirect_page");
    exit;
}

$vst_id = $_POST['vst_id'] ?? null;

if (!$vst_id) {
    $errorMsg = urlencode('Transaction ID is missing.');
    header("Location: $redirect_page?status=error&msg=$errorMsg");
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

    // 1. Get Transaction Details (for Stock Refund and Audit Log)
    // Using FOR UPDATE to lock record and stock
    $getSql = "SELECT vst.ITEM_ID, vst.QUANTITY_USED, ar.TAG_NO, vs.SUPPLY_NAME 
               FROM VITAMINS_SUPPLEMENTS_TRANSACTIONS vst
               JOIN ANIMAL_RECORDS ar ON vst.ANIMAL_ID = ar.ANIMAL_ID
               JOIN VITAMINS_SUPPLEMENTS vs ON vst.ITEM_ID = vs.SUPPLY_ID
               WHERE vst.VST_ID = :id FOR UPDATE";
    
    $getStmt = $conn->prepare($getSql);
    $getStmt->execute([':id' => $vst_id]);
    
    $row = $getStmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        // Rollback just in case
        $conn->rollBack();
        throw new Exception("Transaction record not found or already deleted.");
    }

    $itemId = $row['ITEM_ID']; 
    $qty    = $row['QUANTITY_USED'];
    $animal_tag = $row['TAG_NO'];
    $supply_name = $row['SUPPLY_NAME'];

    // 2. RESTORE STOCK TO 'VITAMINS_SUPPLEMENTS' TABLE
    // Note: Replaced SYSDATE with NOW()
    $restoreSql = "UPDATE VITAMINS_SUPPLEMENTS 
                   SET TOTAL_STOCK = TOTAL_STOCK + :qty, 
                       DATE_UPDATED = NOW() 
                   WHERE SUPPLY_ID = :item_id";
    
    $restoreStmt = $conn->prepare($restoreSql);
    $restoreParams = [
        ':qty'     => $qty,
        ':item_id' => $itemId
    ];

    if (!$restoreStmt->execute($restoreParams)) {
        throw new Exception("Failed to restore stock.");
    }

    // 3. DELETE THE TRANSACTION RECORD
    $delSql = "DELETE FROM VITAMINS_SUPPLEMENTS_TRANSACTIONS WHERE VST_ID = :id";
    $delStmt = $conn->prepare($delSql);
    
    if (!$delStmt->execute([':id' => $vst_id])) {
        throw new Exception("Delete failed.");
    }

    // 4. INSERT AUDIT LOG
    // Note: Removed RECORD_ID column based on your previous input.
    $logDetails = "Deleted Vitamins/Supplements Transaction (ID: $vst_id) for Animal $animal_tag. Restored $qty of $supply_name.";
    
    $log_sql = "INSERT INTO AUDIT_LOGS 
                (USER_ID, USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS) 
                VALUES 
                (:user_id, :username, 'DELETE_VST_TXN', 'VITAMINS_SUPPLEMENTS_TRANSACTIONS', :details, :ip)";
    
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

    // 5. Commit all changes (restore, delete, log)
    $conn->commit(); 
    
    // SUCCESS: Redirect back
    $successMsg = urlencode("✅ Transaction deleted and $supply_name stock restored.");
    header("Location: $redirect_page?status=success&msg=$successMsg");
    exit();

} catch (Exception $e) {
    // 6. FAILURE: Rollback and Redirect with Error
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    $errorMsg = urlencode('❌ Error deleting transaction: ' . $e->getMessage());
    header("Location: $redirect_page?status=error&msg=$errorMsg");
    exit();
}
?>