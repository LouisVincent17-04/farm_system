<?php
// ../process/updateBreed.php
session_start();
error_reporting(0);
ini_set('display_errors', 0);

include '../config/Connection.php';

// Get User Info
$user_id = !empty($_SESSION['user']['USER_ID']) ? $_SESSION['user']['USER_ID'] : null;
$username = $_SESSION['user']['FULL_NAME'] ?? 'System';
$ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';

$redirect_page = '../views/breed.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: $redirect_page?status=error&msg=" . urlencode("Invalid request method."));
    exit;
}

// Validate input fields
if (empty($_POST['breed_id']) || empty($_POST['breed_name']) || empty($_POST['animal_type_id'])) {
    header("Location: $redirect_page?status=error&msg=" . urlencode("Missing required fields."));
    exit;
}

$breed_id = trim($_POST['breed_id']);
$breed_name = trim($_POST['breed_name']);
$animal_type_id = trim($_POST['animal_type_id']);

try {
    if (!isset($conn)) {
        throw new Exception("Database connection failed.");
    }

    // 1. Start Transaction
    $conn->beginTransaction();

    // 2. Fetch Original Data (For Audit Log Comparison) and Lock Row
    // We join with Animal_Type to get the text name of the *old* type for the log
    $sqlFetch = "SELECT b.BREED_NAME, b.ANIMAL_TYPE_ID, t.ANIMAL_TYPE_NAME
                 FROM BREEDS b
                 LEFT JOIN ANIMAL_TYPE t ON b.ANIMAL_TYPE_ID = t.ANIMAL_TYPE_ID
                 WHERE b.BREED_ID = :id 
                 FOR UPDATE";
    
    $fetch_stmt = $conn->prepare($sqlFetch);
    $fetch_stmt->execute([':id' => $breed_id]);
    
    $original_row = $fetch_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$original_row) {
        throw new Exception("Breed record not found.");
    }

    // 3. Check for Changes
    $original_name = $original_row['BREED_NAME'];
    $original_type_id = $original_row['ANIMAL_TYPE_ID'];
    $original_type_name = $original_row['ANIMAL_TYPE_NAME'];

    $name_changed = ($original_name !== $breed_name);
    $type_changed = ($original_type_id != $animal_type_id);

    // If nothing changed, stop here
    if (!$name_changed && !$type_changed) {
        $conn->rollBack(); // Release lock
        header("Location: $redirect_page?status=info&msg=" . urlencode("No changes were made to the breed."));
        exit;
    }

    // 4. Perform Update
    $sqlUpdate = "UPDATE BREEDS SET BREED_NAME = :name, ANIMAL_TYPE_ID = :type_id WHERE BREED_ID = :id";
    $update_stmt = $conn->prepare($sqlUpdate);
    
    $update_params = [
        ':name'    => $breed_name,
        ':type_id' => $animal_type_id,
        ':id'      => $breed_id
    ];

    if (!$update_stmt->execute($update_params)) {
        throw new Exception("Update failed.");
    }

    // 5. Build Audit Log Details
    $changes = [];
    if ($name_changed) {
        $changes[] = "Name: '$original_name' -> '$breed_name'";
    }
    
    if ($type_changed) {
        // We need the NEW type name for the log
        $new_type_name = "ID: $animal_type_id";
        $type_lookup_sql = "SELECT ANIMAL_TYPE_NAME FROM ANIMAL_TYPE WHERE ANIMAL_TYPE_ID = :id";
        $type_lookup_stmt = $conn->prepare($type_lookup_sql);
        $type_lookup_stmt->execute([':id' => $animal_type_id]);
        
        if ($row = $type_lookup_stmt->fetch(PDO::FETCH_ASSOC)) {
            $new_type_name = $row['ANIMAL_TYPE_NAME'];
        }

        $changes[] = "Type: '$original_type_name' -> '$new_type_name'";
    }

    $logDetails = "Updated Breed (ID: $breed_id). " . implode("; ", $changes);

    // 6. Insert Audit Log
    $log_sql = "INSERT INTO AUDIT_LOGS 
                (USER_ID, USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS) 
                VALUES 
                (:user_id, :username, 'EDIT_BREED', 'BREEDS', :details, :ip)";

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

    // 7. Commit Transaction
    $conn->commit();

    header("Location: $redirect_page?status=success&msg=" . urlencode("Breed updated successfully."));
    exit;

} catch (PDOException $e) {
    // Rollback on database error
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }

    $errorMsg = $e->getMessage();

    // Check for Unique Constraint Violation (MySQL Error 1062 or SQLSTATE 23000)
    if ($e->getCode() == '23000' || strpos($errorMsg, 'Duplicate entry') !== false) {
        $errorMsg = "A breed with this name already exists for the selected animal type.";
    }

    header("Location: $redirect_page?status=error&msg=" . urlencode($errorMsg));
    exit;

} catch (Exception $e) {
    // Rollback on generic error
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    header("Location: $redirect_page?status=error&msg=" . urlencode($e->getMessage()));
    exit;
}
?>