<?php
session_start(); // 1. Start Session
error_reporting(0);
ini_set('display_errors', 0);
require_once '../config/Connection.php';

// Get User Info (Safe Fallback)
$user_id = !empty($_SESSION['user']['USER_ID']) ? $_SESSION['user']['USER_ID'] : null;
$username = $_SESSION['user']['FULL_NAME'] ?? 'System';
$ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';

$redirect_page = '../views/medication.php';
$tt_id = null; // Initialize $tt_id here for scope in finally block

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    $tt_id = $_POST['tt_id'] ?? null;

    if (!$tt_id) {
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
        // Using FOR UPDATE to lock the row
        $getSql = "SELECT tt.ITEM_ID, tt.QUANTITY_USED, tt.ANIMAL_ID, m.SUPPLY_NAME, ar.TAG_NO
                   FROM TREATMENT_TRANSACTIONS tt
                   JOIN MEDICINES m ON tt.ITEM_ID = m.SUPPLY_ID
                   JOIN ANIMAL_RECORDS ar ON tt.ANIMAL_ID = ar.ANIMAL_ID
                   WHERE tt.TT_ID = :id FOR UPDATE";
        
        $getStmt = $conn->prepare($getSql);
        $getStmt->execute([':id' => $tt_id]);
        
        $row = $getStmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            // Release lock
            $conn->rollBack();
            throw new Exception("Transaction not found or already deleted.");
        }

        $itemId = $row['ITEM_ID'];
        $qty    = $row['QUANTITY_USED'];
        $medicine_name = $row['SUPPLY_NAME'];
        $animal_tag = $row['TAG_NO'];

        // 2. Restore Stock to MEDICINES Table
        // Note: Replaced SYSDATE with NOW()
        $restoreSql = "UPDATE MEDICINES 
                       SET TOTAL_STOCK = TOTAL_STOCK + :qty, 
                           DATE_UPDATED = NOW() 
                       WHERE SUPPLY_ID = :id";
        
        $restoreStmt = $conn->prepare($restoreSql);
        $restoreParams = [
            ':qty' => $qty,
            ':id'  => $itemId
        ];

        if (!$restoreStmt->execute($restoreParams)) {
            throw new Exception("Failed to restore stock.");
        }

        // 3. Delete the Transaction
        $delSql = "DELETE FROM TREATMENT_TRANSACTIONS WHERE TT_ID = :id";
        $delStmt = $conn->prepare($delSql);
        
        if (!$delStmt->execute([':id' => $tt_id])) {
            throw new Exception("Delete failed.");
        }

        // 4. INSERT AUDIT LOG
        $logDetails = "Deleted Treatment Transaction (ID: $tt_id, Animal: $animal_tag). Restored $qty of $medicine_name.";
        
        $log_sql = "INSERT INTO AUDIT_LOGS 
                    (USER_ID, USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS) 
                    VALUES 
                    (:user_id, :username, 'DELETE_TREATMENT_TXN', 'TREATMENT_TRANSACTIONS', :details, :ip)";
        
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
        
        // SUCCESS: Redirect back
        $successMsg = urlencode("Treatment transaction deleted and $medicine_name stock restored.");
        header("Location: $redirect_page?status=success&msg=$successMsg");
        exit();

    } catch (Exception $e) {
        // 6. FAILURE: Rollback and Redirect with Error
        if (isset($conn) && $conn->inTransaction()) {
            $conn->rollBack();
        }
        $errorMsg = urlencode('Error deleting transaction: ' . $e->getMessage());
        header("Location: $redirect_page?status=error&msg=$errorMsg");
        exit();
    }
} else {
    // Invalid Method: Redirect back
    header("Location: $redirect_page");
    exit();
}
?>