<?php
// process/saveAccess.php
session_start();
require_once '../config/Connection.php';
require_once '../config/PageList.php';

// --- AUDIT LOG CONTEXT ---
$admin_id = !empty($_SESSION['user']['USER_ID']) ? $_SESSION['user']['USER_ID'] : 1; // Default to 1 (System)
$admin_name = $_SESSION['user']['FULL_NAME'] ?? 'System';
$ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_POST['user_id'];
    $role_id = $_POST['role_id']; // Value from the Role dropdown
    
    // NEW: Capture Location ID from the Location dropdown. Convert empty string to NULL.
    $location_id = !empty($_POST['location_id']) ? $_POST['location_id'] : null;
    
    $posted_perms = $_POST['perms'] ?? []; 

    try {
        $conn->beginTransaction();

        // ---------------------------------------------------------
        // 1. UPDATE USER ROLE & LOCATION IN `users` TABLE
        // ---------------------------------------------------------
        // UPDATED: Now sets both USER_TYPE and LOCATION_ID
        $userStmt = $conn->prepare("UPDATE users SET USER_TYPE = ?, LOCATION_ID = ? WHERE USER_ID = ?");
        $userStmt->execute([$role_id, $location_id, $user_id]);

        // ---------------------------------------------------------
        // 2. UPDATE PERMISSIONS IN `access_control` TABLE
        // ---------------------------------------------------------
        
        // Get all possible column names from config file
        $all_columns = [];
        foreach ($permission_map as $category => $pages) {
            foreach ($pages as $col => $label) {
                $all_columns[] = $col;
            }
        }

        // Build SQL parts
        $columns_sql = implode(", ", $all_columns);
        $placeholders = implode(", ", array_fill(0, count($all_columns), "?"));
        
        // ON DUPLICATE KEY UPDATE string
        $update_parts = [];
        foreach ($all_columns as $col) {
            $update_parts[] = "$col = VALUES($col)";
        }
        $update_sql = implode(", ", $update_parts);

        // Values Array (Start with user_id)
        $values = [$user_id]; 
        
        // 1 if checked, 0 if not
        foreach ($all_columns as $col) {
            $values[] = isset($posted_perms[$col]) ? 1 : 0;
        }

        // Execute Access Control Update
        $sql = "INSERT INTO access_control (user_id, created_at, $columns_sql) 
                VALUES (?, NOW(), $placeholders) 
                ON DUPLICATE KEY UPDATE $update_sql";

        $stmt = $conn->prepare($sql);
        $stmt->execute($values);

        // ---------------------------------------------------------
        // 3. LOG AUDIT
        // ---------------------------------------------------------
        $role_names = [1 => 'New User', 2 => 'Farm User', 3 => 'Admin', 4 => 'Super Admin'];
        $role_name = $role_names[$role_id] ?? 'Unknown Role';
        $location_text = $location_id ? "Location ID $location_id" : "Global Location";

        $audit_action = "ACCESS_UPDATE";
        $audit_details = "Updated User ID $user_id: Role set to $role_name ($role_id), Assigned to $location_text & permissions saved.";

        $logStmt = $conn->prepare("INSERT INTO audit_logs (USER_ID, USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS) 
                                   VALUES (?, ?, ?, 'ACCESS_CONTROL', ?, ?)");
        $logStmt->execute([$admin_id, $admin_name, $audit_action, $audit_details, $ip_address]);

        $conn->commit();

        header("Location: ../views/manage_access.php?user_id=$user_id&success=1");
        exit();

    } catch (Exception $e) {
        $conn->rollBack();
        die("Error saving permissions: " . $e->getMessage());
    }
}
?>