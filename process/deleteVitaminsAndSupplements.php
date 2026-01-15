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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['item_id'])) {
    
    $item_id = $_POST['item_id'];

    try {
        if (!isset($conn)) {
            throw new Exception("Database connection failed.");
        }

        // ---------------------------------------------------------
        // START TRANSACTION
        // ---------------------------------------------------------
        $conn->beginTransaction();

        // 1. FETCH ITEM NAME AND TYPE FIRST (For the Audit Log)
        // We join with ITEM_TYPES to get the type name
        $info_sql = "SELECT i.ITEM_NAME, t.ITEM_TYPE_NAME 
                     FROM ITEMS i JOIN ITEM_TYPES t ON i.ITEM_TYPE_ID = t.ITEM_TYPE_ID 
                     WHERE i.ITEM_ID = :id";
        
        $info_stmt = $conn->prepare($info_sql);
        $info_stmt->execute([':id' => $item_id]);
        
        $item_row = $info_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$item_row) {
            // Rollback just in case
            $conn->rollBack();
            
            echo json_encode([
                'success' => false,
                'message' => '❌ Item not found or already deleted.'
            ]);
            exit;
        }

        $item_name = $item_row['ITEM_NAME'];
        $item_type_name = $item_row['ITEM_TYPE_NAME'];
        
        // 2. DELETE THE ITEM
        $delete_sql = "DELETE FROM ITEMS WHERE ITEM_ID = :id";
        $delete_stmt = $conn->prepare($delete_sql);
        
        if (!$delete_stmt->execute([':id' => $item_id])) {
            throw new Exception("Delete Failed.");
        }

        // 3. INSERT AUDIT LOG
        // Note: Removed RECORD_ID column based on your previous snippet adjustment.
        $logDetails = "Permanently deleted Purchase Item: $item_name (Type: $item_type_name, ID: $item_id)";
        
        $log_sql = "INSERT INTO AUDIT_LOGS 
                    (USER_ID, USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS) 
                    VALUES 
                    (:user_id, :username, 'DELETE_ITEM', 'ITEMS', :details, :ip)";
        
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
            'message' => '✅ Item deleted successfully.'
        ]);

        // Note: Sending a Header Location after JSON output usually doesn't work in standard PHP 
        // unless output buffering is enabled, but we keep it here to match your source logic.
        // header('Location: ../views/purch_vitamins_supplements.php');
        
    } catch (PDOException $e) {
        // 5. ROLLBACK ON ERROR
        if (isset($conn) && $conn->inTransaction()) {
            $conn->rollBack();
        }

        $error_msg = $e->getMessage();

        // Check for MySQL Integrity Constraint Violation (Code 1451)
        // This is equivalent to Oracle's ORA-02292
        if ($e->errorInfo[1] == 1451) {
             $error_msg = "Cannot delete item '$item_name'. It is currently linked to other records (e.g., usage history).";
        }
        
        echo json_encode([
            'success' => false,
            'message' => '❌ Error: ' . $error_msg
        ]);

    } catch (Exception $e) {
        // Rollback generic errors
        if (isset($conn) && $conn->inTransaction()) {
            $conn->rollBack();
        }
        
        echo json_encode([
            'success' => false,
            'message' => '❌ Error: ' . $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => '❌ Invalid request.'
    ]);
}
?>