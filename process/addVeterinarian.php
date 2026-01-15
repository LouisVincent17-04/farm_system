<?php
// ../process/addVeterinarian.php
session_start();
error_reporting(0);
ini_set('display_errors', 0);

include '../config/Database.php';
include '../security/checkRole.php';

// Ensure Admin Access (Role 3)
checkRole(3);

// Get Admin Info for Audit Log
$admin_id = $_SESSION['user']['USER_ID'];
$admin_name = $_SESSION['user']['FULL_NAME'] ?? 'Admin';
$ip_address = $_SERVER['REMOTE_ADDR'];

// Updated Redirect URL
$redirect_page = '../views/veterinary.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: $redirect_page?status=error&msg=" . urlencode("Invalid request method."));
    exit;
}

// 1. Validate Input
$fullName = trim($_POST['fullName'] ?? '');
$contactInfo = trim($_POST['contactInfo'] ?? '');

if (empty($fullName)) {
    header("Location: $redirect_page?status=error&msg=" . urlencode("Veterinarian Name is required."));
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

    // 2. Insert New Veterinarian
    // Note: Removed RETURNING clause
    $sqlInsert = "INSERT INTO VETERINARIANS (FULL_NAME, CONTACT_INFO) VALUES (:name, :contact)";
    
    $stmt = $conn->prepare($sqlInsert);
    
    $params = [
        ':name'    => $fullName,
        ':contact' => $contactInfo
    ];

    if (!$stmt->execute($params)) {
        throw new Exception("Database Insert Failed.");
    }

    // Retrieve the new ID
    $new_vet_id = $conn->lastInsertId();

    // 3. Log Audit
    $logDetails = "Added new Veterinarian: $fullName (ID: $new_vet_id)";
    $sqlLog = "INSERT INTO AUDIT_LOGS (USER_ID, USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS) 
               VALUES (:user_id, :username, 'ADD_VET', 'VETERINARIANS', :details, :ip)";
    
    $log_stmt = $conn->prepare($sqlLog);
    
    $log_params = [
        ':user_id'  => $admin_id,
        ':username' => $admin_name,
        ':details'  => $logDetails,
        ':ip'       => $ip_address
    ];
    
    if (!$log_stmt->execute($log_params)) {
        throw new Exception("Audit Log Failed.");
    }

    // 4. Commit Everything
    $conn->commit();

    header("Location: $redirect_page?status=success&msg=" . urlencode("Veterinarian added successfully."));
    exit;

} catch (Exception $e) {
    // Rollback changes
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    
    $msg = $e->getMessage();
    header("Location: $redirect_page?status=error&msg=" . urlencode($msg));
    exit;
}
?>