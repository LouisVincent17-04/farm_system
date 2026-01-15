<?php
// ../process/editUnit.php
error_reporting(0);
ini_set('display_errors', 0); 
session_start(); // 1. Start Session

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Use header redirection for non-JSON responses in user-facing processes
    header("Location: ../views/units.php?status=error&msg=" . urlencode("Invalid request method."));
    exit;
}

include '../config/Connection.php';

// Get User Info (The user performing the action)
$acting_user_id = !empty($_SESSION['user']['USER_ID']) ? $_SESSION['user']['USER_ID'] : null;
$acting_username = $_SESSION['user']['FULL_NAME'] ?? 'System';
$ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';

if (empty($_POST['unit_id']) || empty($_POST['unit_name']) || empty($_POST['unit_abbreviation'])) {
    header("Location: ../views/units.php?status=error&msg=" . urlencode("Missing required fields."));
    exit;
}

$unit_id = (int) $_POST['unit_id'];
$unit_name = trim($_POST['unit_name']);
$unit_abbr = trim($_POST['unit_abbreviation']);

try {
    if (!isset($conn)) {
        throw new Exception("Database connection failed.");
    }

    // Start Transaction
    $conn->beginTransaction();

    // 1. Get Original Data (For Audit Log Comparison) and lock row
    $original_sql = "SELECT UNIT_NAME, UNIT_ABBR FROM UNITS WHERE UNIT_ID = :unit_id FOR UPDATE";
    $original_stmt = $conn->prepare($original_sql);
    $original_stmt->execute([':unit_id' => $unit_id]);
    
    $original_row = $original_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$original_row) {
        $conn->rollBack();
        throw new Exception("Unit record not found.");
    }
    
    $original_name = $original_row['UNIT_NAME'];
    $original_abbr = $original_row['UNIT_ABBR'];

    // 2. Perform UPDATE
    // Note: Replaced SYSDATE with NOW()
    $sqlUpdate = "UPDATE UNITS
                  SET UNIT_NAME = :unit_name,
                      UNIT_ABBR = :unit_abbr,
                      DATE_UPDATED = NOW()
                  WHERE UNIT_ID = :unit_id";

    $update_stmt = $conn->prepare($sqlUpdate);
    $update_params = [
        ':unit_name' => $unit_name,
        ':unit_abbr' => $unit_abbr,
        ':unit_id'   => $unit_id
    ];

    if (!$update_stmt->execute($update_params)) {
        throw new Exception("Database Update Failed.");
    }

    // 3. INSERT AUDIT LOG
    $logDetails = "Updated Unit (ID: $unit_id). Name: $original_name -> $unit_name. Abbreviation: $original_abbr -> $unit_abbr.";
    
    $log_sql = "INSERT INTO AUDIT_LOGS 
                (USER_ID, USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS) 
                VALUES 
                (:user_id, :username, 'EDIT_UNIT', 'UNITS', :details, :ip)";
    
    $log_stmt = $conn->prepare($log_sql);
    $log_params = [
        ':user_id'  => $acting_user_id,
        ':username' => $acting_username,
        ':details'  => $logDetails,
        ':ip'       => $ip_address
    ];
    
    if (!$log_stmt->execute($log_params)) {
        throw new Exception("Audit Log Failed.");
    }

    // 4. COMMIT EVERYTHING
    $conn->commit();
    
    // Redirect on success
    header("Location: ../views/units.php?status=success&msg=" . urlencode("✅ Unit updated successfully."));
    exit;

} catch (PDOException $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    
    $errorMsg = $e->getMessage();
    // Check for duplicate entry (MySQL Error 1062 / SQLSTATE 23000)
    if ($e->getCode() == '23000' || strpos($errorMsg, 'Duplicate entry') !== false) {
        $errorMsg = "A unit with this name or abbreviation already exists.";
    }

    header("Location: ../views/units.php?status=error&msg=" . urlencode("❌ Error updating unit: " . $errorMsg));
    exit;

} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    header("Location: ../views/units.php?status=error&msg=" . urlencode("❌ Error updating unit: " . $e->getMessage()));
    exit;
}
?>