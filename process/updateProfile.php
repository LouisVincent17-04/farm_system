<?php
// ../process/updateProfile.php
error_reporting(0);
ini_set('display_errors', 0); 

session_start();
include '../config/Connection.php';

// 1. Check Request Method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../views/profile.php?status=error&msg=" . urlencode("Invalid request method."));
    exit;
}

// 2. Get User Info from Session
$acting_user_id = !empty($_SESSION['user']['USER_ID']) ? $_SESSION['user']['USER_ID'] : null;
$ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';

// 3. Security Check: If no session ID, kick to login
if (!$acting_user_id) {
    header("Location: ../views/login.php?status=error&msg=" . urlencode("Session expired."));
    exit;
}

// 4. Input Validation
$fullName = trim($_POST['fullName'] ?? '');
$contactInfo = trim($_POST['contactInfo'] ?? ''); 
$contactInfo = empty($contactInfo) ? null : $contactInfo;

if ($contactInfo !== null && !is_numeric($contactInfo)) {
     header("Location: ../views/profile.php?status=error&msg=" . urlencode("Contact Info must be a number."));
     exit;
}

if (empty($fullName)) {
    header("Location: ../views/profile.php?status=error&msg=" . urlencode("Full name is required."));
    exit;
}

try {
    if (!isset($conn)) {
        throw new Exception("Database connection failed.");
    }

    // Start Transaction
    $conn->beginTransaction();

    // 5. Fetch Original Data & Lock Row
    $original_sql = "SELECT FULL_NAME, CONTACT_INFO FROM USERS WHERE USER_ID = :user_id FOR UPDATE";
    $original_stmt = $conn->prepare($original_sql);
    $original_stmt->execute([':user_id' => $acting_user_id]);
    
    $original_row = $original_stmt->fetch(PDO::FETCH_ASSOC);

    // --- CRITICAL FIX: Handle Missing User ---
    if (!$original_row) {
        $conn->rollBack();
        // Destroy session because the ID is invalid (Zombie Session)
        session_destroy(); 
        header("Location: ../views/login.php?status=error&msg=" . urlencode("Account not found. Please log in again."));
        exit;
    }
    // -----------------------------------------
    
    $original_name = $original_row['FULL_NAME'];
    $original_contact = $original_row['CONTACT_INFO'] ?? null;

    // 6. Check for Changes
    $name_changed = ($original_name !== $fullName);
    $contact_changed = (string)$original_contact !== (string)$contactInfo;

    if (!$name_changed && !$contact_changed) {
        $conn->rollBack();
        header("Location: ../views/profile.php?status=info&msg=" . urlencode("No changes detected."));
        exit;
    }

    // 7. Perform UPDATE
    // Note: Replaced SYSDATE with NOW()
    $sqlUpdate = "UPDATE USERS SET FULL_NAME = :fullName, CONTACT_INFO = :contactInfo, DATE_UPDATED = NOW() WHERE USER_ID = :user_id";
    $update_stmt = $conn->prepare($sqlUpdate);
    $update_params = [
        ':fullName'    => $fullName,
        ':contactInfo' => $contactInfo,
        ':user_id'     => $acting_user_id
    ];

    if (!$update_stmt->execute($update_params)) {
        throw new Exception("Update Failed.");
    }

    // 8. INSERT AUDIT LOG
    $logChanges = [];
    if ($name_changed) $logChanges[] = "Name: $original_name -> $fullName";
    if ($contact_changed) $logChanges[] = "Contact: " . ($original_contact ?? 'N/A') . " -> " . ($contactInfo ?? 'N/A');

    $logDetails = "Updated own profile (ID: $acting_user_id). " . implode("; ", $logChanges) . ".";
    
    $log_sql = "INSERT INTO AUDIT_LOGS (USER_ID, USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS) 
                VALUES (:user_id, :username, 'EDIT_PROFILE', 'USERS', :details, :ip)";
    
    $log_stmt = $conn->prepare($log_sql);
    $log_params = [
        ':user_id'  => $acting_user_id,
        ':username' => $fullName,
        ':details'  => $logDetails,
        ':ip'       => $ip_address
    ];
    
    if (!$log_stmt->execute($log_params)) {
        throw new Exception("Audit Log Failed.");
    }

    // 9. Commit & Update Session
    $conn->commit();
    $_SESSION['user']['FULL_NAME'] = $fullName;
    $_SESSION['user']['CONTACT_INFO'] = $contactInfo;

    header("Location: ../views/profile.php?status=success&msg=" . urlencode("Profile updated successfully."));
    exit;

} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    header("Location: ../views/profile.php?status=error&msg=" . urlencode("Error: " . $e->getMessage()));
    exit;
}
?>