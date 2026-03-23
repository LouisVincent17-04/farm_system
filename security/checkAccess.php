<?php
// security/checkAccess.php

function checkAccess($column_name) {
    global $conn;

    // Ensure database connection exists
    if (!isset($conn)) {
        die("System Error: Database connection missing in checkAccess.");
    }

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    

    

    // -----------------------------------------------------------
    // ⚠️ IMPORTANT: MATCH THIS TO YOUR LOGIN SCRIPT
    // Option 1: Direct keys (Common)
    // $user_id = $_SESSION['user_id'] ?? 0;
    // $role_id = $_SESSION['role_id'] ?? 0;
    
    // Option 2: Array keys (As written in your snippet)
    $user_id = $_SESSION['user']['USER_ID'] ?? 0; 
    $role_id = $_SESSION['user']['USER_TYPE'] ?? 0; 

    if($user_id == 0) {
        echo "<script>
                window.location.href = 'globalxadminzportal/login.php';
            </script>";
    }
    // -----------------------------------------------------------

    // 1. Admin (Role 4) always has access (Bypass)
    if ($role_id == 4) {
        return true; 
    }

    // 2. Validate Column Name (Security against SQL Injection)
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $column_name)) {
        die("Invalid permission key: " . htmlspecialchars($column_name));
    }

    try {
        // 3. Check Database
        // We use backticks ` ` around the column name for safety
        $sql = "SELECT `$column_name` FROM access_control WHERE user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$user_id]);
        $result = $stmt->fetchColumn();

        // 4. Access Logic
        // If $result is false (user not found in access table) 
        // OR $result is 0 (column value is 0), DENY ACCESS.
        if (!$result) {
            // JavaScript redirect is cleaner for UI
            echo "<script>
                alert('⛔ ACCESS DENIED: You do not have permission to access the module: $column_name ');
                window.location.href = '../views/admin_dashboard.php';
            </script>";
            exit(); // Stop script execution immediately
        }

    } catch (PDOException $e) {
        // This catches errors if you mistype a column name (e.g., 'animal_reocrd')
        die("<b>Access Control Error:</b> Permission key '$column_name' does not exist in the database. <br>Debug: " . $e->getMessage());
    }
}


function hasAccess($column_name) {
    global $conn;

    // Ensure database connection exists
    if (!isset($conn)) {
        die("System Error: Database connection missing in checkAccess.");
    }

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    

    

    // -----------------------------------------------------------
    // ⚠️ IMPORTANT: MATCH THIS TO YOUR LOGIN SCRIPT
    // Option 1: Direct keys (Common)
    // $user_id = $_SESSION['user_id'] ?? 0;
    // $role_id = $_SESSION['role_id'] ?? 0;
    
    // Option 2: Array keys (As written in your snippet)
    $user_id = $_SESSION['user']['USER_ID'] ?? 0; 
    $role_id = $_SESSION['user']['USER_TYPE'] ?? 0; 

    if($user_id == 0) {
        echo "<script>
                window.location.href = 'globalxadminzportal/login.php';
            </script>";
    }
    // -----------------------------------------------------------

    // 1. Admin (Role 4) always has access (Bypass)
    if ($role_id == 4) {
        return true; 
    }

    // 2. Validate Column Name (Security against SQL Injection)
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $column_name)) {
        die("Invalid permission key: " . htmlspecialchars($column_name));
    }

    try {
        // 3. Check Database
        // We use backticks ` ` around the column name for safety
        $sql = "SELECT `$column_name` FROM access_control WHERE user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$user_id]);
        $result = $stmt->fetchColumn();

        // 4. Access Logic
        // If $result is false (user not found in access table) 
        // OR $result is 0 (column value is 0), DENY ACCESS.
        if (!$result) {
            return 0;
        }
        return 1;

    } catch (PDOException $e) {
        // This catches errors if you mistype a column name (e.g., 'animal_reocrd')
        die("<b>Access Control Error:</b> Permission key '$column_name' does not exist in the database. <br>Debug: " . $e->getMessage());
    }
}
?>