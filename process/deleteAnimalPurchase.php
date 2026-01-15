<?php
session_start(); // 1. Start Session
header('Content-Type: application/json');

// Turn off error reporting to ensure clean JSON output
error_reporting(0);
ini_set('display_errors', 0);

include '../config/Connection.php';

// Get User Info
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

        // 1. FETCH ITEM INFO FIRST (For the Audit Log)
        $info_sql = "SELECT ITEM_NAME, ITEM_NET_WEIGHT FROM ITEMS WHERE ITEM_ID = :id";
        $info_stmt = $conn->prepare($info_sql);
        $info_stmt->execute([':id' => $item_id]);
        
        $item_row = $info_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$item_row) {
            // Rollback technically not needed here, but good practice
            $conn->rollBack();

            echo json_encode([
                'success' => false,
                'message' => '❌ Item not found or already deleted.'
            ]);
            exit;
        }

        $item_name = $item_row['ITEM_NAME'];
        $item_weight = $item_row['ITEM_NET_WEIGHT'] ?? '0'; // Capture weight
        
        // 2. DELETE THE ITEM
        $delete_sql = "DELETE FROM ITEMS WHERE ITEM_ID = :id";
        $delete_stmt = $conn->prepare($delete_sql);
        
        // Execute Delete
        if (!$delete_stmt->execute([':id' => $item_id])) {
            throw new Exception("Delete Failed.");
        }

        // 3. INSERT AUDIT LOG
        $logDetails = "Permanently deleted Item: $item_name (Weight: $item_weight kg, ID: $item_id)";
        
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

        // Note: You cannot output JSON and then redirect via Header in PHP if output buffering isn't on.
        // If this is an AJAX request, the JSON below is sufficient.
        echo json_encode([
            'success' => true,
            'message' => '✅ Item deleted successfully.'
        ]);

        // If you strictly need the redirect for form submissions:
        // header('Location: ../views/purch_animals.php');
        // exit();
        
    } catch (PDOException $e) {
        // 5. ROLLBACK ON ERROR
        if (isset($conn) && $conn->inTransaction()) {
            $conn->rollBack();
        }

        $error_msg = $e->getMessage();
        
        // Check for MySQL Integrity Constraint Violation (Code 1451)
        // This equates to Oracle's ORA-02292 (Child record found)
        if ($e->errorInfo[1] == 1451) {
             $error_msg = "Cannot delete item '$item_name'. It is currently linked to other records (e.g., animal records).";
        }
        
        echo json_encode([
            'success' => false,
            'message' => '❌ Error: ' . $error_msg
        ]);

    } catch (Exception $e) {
        // Rollback generic exceptions
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