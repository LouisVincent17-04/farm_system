<?php
session_start(); // 1. Start Session
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json');
require_once '../config/Connection.php';

// Get Current User Info (The Admin performing the action)
$admin_id = !empty($_SESSION['user']['USER_ID']) ? $_SESSION['user']['USER_ID'] : null;
$admin_name = $_SESSION['user']['FULL_NAME'] ?? 'System';
$ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';

$response = ['success' => false, 'message' => ''];

if ($_SERVER["REQUEST_METHOD"] === 'POST') {
    $target_user_id = $_POST['user_id'] ?? null;
    $new_role = $_POST['new_role'] ?? null;

    // Validate Input
    if (!$target_user_id || !in_array($new_role, ['1', '2', '3', '4'])) {
        $response['message'] = 'Invalid data provided';
        echo json_encode($response);
        exit;
    }

    // Role Mapping for clearer logs
    $role_names = [
        '1' => 'New User',
        '2' => 'Farm Employee',
        '3' => 'Admin',
        '4' => 'Super Admin'
    ];
    $role_label = $role_names[$new_role] ?? "Role $new_role";

    try {
        if (!isset($conn)) {
            throw new Exception("Database connection failed.");
        }

        // ---------------------------------------------------------
        // START TRANSACTION
        // ---------------------------------------------------------
        $conn->beginTransaction();

        // 1. Fetch Target User's Name (For the Log)
        $user_sql = "SELECT FULL_NAME FROM USERS WHERE USER_ID = :id";
        $user_stmt = $conn->prepare($user_sql);
        $user_stmt->execute([':id' => $target_user_id]);
        $user_row = $user_stmt->fetch(PDO::FETCH_ASSOC);
        
        $target_user_name = $user_row['FULL_NAME'] ?? "Unknown User";

        // 2. Update User Role
        // Note: Replaced SYSDATE with NOW()
        $sql = "UPDATE USERS SET USER_TYPE = :role, DATE_UPDATED = NOW() WHERE USER_ID = :id";
        $stmt = $conn->prepare($sql);
        $params = [
            ':role' => $new_role,
            ':id'   => $target_user_id
        ];

        if (!$stmt->execute($params)) {
            throw new Exception("Update failed.");
        }

        // 3. INSERT AUDIT LOG
        $logDetails = "Changed role of user '$target_user_name' (ID $target_user_id) to $role_label";
        
        $log_sql = "INSERT INTO AUDIT_LOGS 
                    (USER_ID, USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS) 
                    VALUES 
                    (:user_id, :username, 'CHANGE_ROLE', 'USERS', :details, :ip)";
        
        $log_stmt = $conn->prepare($log_sql);
        
        $log_params = [
            ':user_id'  => $admin_id,
            ':username' => $admin_name,
            ':details'  => $logDetails,
            ':ip'       => $ip_address
        ];
        
        if (!$log_stmt->execute($log_params)) {
            throw new Exception("Audit Log Failed.");
        }

        // 4. COMMIT EVERYTHING
        $conn->commit();

        $response['success'] = true;
        $response['message'] = "✅ Role for $target_user_name updated to $role_label successfully.";

    } catch (Exception $e) {
        // Rollback on error
        if (isset($conn) && $conn->inTransaction()) {
            $conn->rollBack();
        }
        $response['message'] = '❌ Error: ' . $e->getMessage();
    }
} else {
    $response['message'] = 'Invalid request method.';
}

echo json_encode($response);
?>