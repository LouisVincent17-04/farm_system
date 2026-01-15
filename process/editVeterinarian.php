<?php
// ../process/editVeterinarian.php
session_start();
error_reporting(0);
ini_set('display_errors', 0);

include '../config/Connection.php';
include '../security/checkRole.php';

checkRole(3); // Admin

$admin_id = $_SESSION['user']['USER_ID'];
$admin_name = $_SESSION['user']['FULL_NAME'] ?? 'Admin';
$ip_address = $_SERVER['REMOTE_ADDR'];

$redirect_page = '../views/veterinary.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: $redirect_page?status=error&msg=" . urlencode("Invalid request method."));
    exit;
}

// Map input 'user_id' to $vet_id based on your previous form structure
$vet_id = trim($_POST['user_id'] ?? ''); 
$fullName = trim($_POST['fullName'] ?? '');
$contactInfo = trim($_POST['contactInfo'] ?? '');

if (empty($vet_id) || empty($fullName)) {
    header("Location: $redirect_page?status=error&msg=" . urlencode("Name and ID are required."));
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

    // 1. Fetch Original Data & Lock Row
    $sqlFetch = "SELECT FULL_NAME, CONTACT_INFO FROM VETERINARIANS WHERE VET_ID = :id FOR UPDATE";
    $fetch_stmt = $conn->prepare($sqlFetch);
    $fetch_stmt->execute([':id' => $vet_id]);
    
    $original = $fetch_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$original) {
        // Rollback just in case
        $conn->rollBack();
        throw new Exception("Veterinarian record not found.");
    }

    // 2. Check for Changes
    $changes = [];
    if ($original['FULL_NAME'] !== $fullName) {
        $changes[] = "Name: '{$original['FULL_NAME']}' -> '$fullName'";
    }
    // Handle null/empty comparisons for contact info
    $oldContact = $original['CONTACT_INFO'] ?? '';
    if ($oldContact !== $contactInfo) {
        $changes[] = "Contact: '$oldContact' -> '$contactInfo'";
    }

    if (empty($changes)) {
        $conn->rollBack();
        header("Location: $redirect_page?status=info&msg=" . urlencode("No changes detected."));
        exit;
    }

    // 3. Perform Update
    // Note: Replaced CURRENT_TIMESTAMP with NOW() for consistency, though MySQL supports both.
    $sqlUpdate = "UPDATE VETERINARIANS SET FULL_NAME = :name, CONTACT_INFO = :contact, UPDATED_AT = NOW() WHERE VET_ID = :id";
    $update_stmt = $conn->prepare($sqlUpdate);
    
    if (!$update_stmt->execute([':name' => $fullName, ':contact' => $contactInfo, ':id' => $vet_id])) {
        throw new Exception("Database Update Failed.");
    }

    // 4. Log Audit
    $logDetails = "Updated Veterinarian (ID: $vet_id). " . implode("; ", $changes);
    $sqlLog = "INSERT INTO AUDIT_LOGS (USER_ID, USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS) 
               VALUES (:user_id, :username, 'EDIT_VET', 'VETERINARIANS', :details, :ip)";
    
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

    // 5. Commit
    $conn->commit();

    header("Location: $redirect_page?status=success&msg=" . urlencode("Veterinarian updated successfully."));
    exit;

} catch (Exception $e) {
    // Rollback on error
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    header("Location: $redirect_page?status=error&msg=" . urlencode($e->getMessage()));
    exit;
}
?>