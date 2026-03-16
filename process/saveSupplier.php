<?php
// process/saveSupplier.php
header('Content-Type: application/json');

// Ensure no output is generated before the JSON response
ob_start();

include '../config/Connection.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Clear any accidental output/whitespace from included files
ob_end_clean();

// 1. Security & Validation
if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access. Please log in.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

// --- AUDIT LOG CONTEXT ---
$user_id = !empty($_SESSION['user']['USER_ID']) ? $_SESSION['user']['USER_ID'] : 1; 
$username = $_SESSION['user']['FULL_NAME'] ?? 'System';
$ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';

// 2. Fetch input data safely
$supplier_id    = $_POST['supplier_id'] ?? '';
$supplier_name  = trim($_POST['supplier_name'] ?? '');
$contact_person = trim($_POST['contact_person'] ?? '');
$contact_number = trim($_POST['contact_number'] ?? '');
$email          = trim($_POST['email'] ?? '');
$address        = trim($_POST['address'] ?? '');
$status         = $_POST['status'] ?? 'Active';

// Basic Validation
if (empty($supplier_name)) {
    echo json_encode(['success' => false, 'message' => 'Company / Supplier Name is required.']);
    exit;
}

try {
    // Start Transaction
    $conn->beginTransaction();

    if (empty($supplier_id)) {
        // --- ADD NEW SUPPLIER ---
        $sql = "INSERT INTO suppliers (SUPPLIER_NAME, CONTACT_PERSON, CONTACT_NUMBER, EMAIL, ADDRESS, STATUS, CREATED_AT) 
                VALUES (?, ?, ?, ?, ?, ?, NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            $supplier_name, 
            $contact_person, 
            $contact_number, 
            $email, 
            $address, 
            $status
        ]);
        
        $action_type = "ADD_SUPPLIER";
        $action_details = "Added new supplier: $supplier_name.";

    } else {
        // --- UPDATE EXISTING SUPPLIER ---
        $sql = "UPDATE suppliers 
                SET SUPPLIER_NAME = ?, 
                    CONTACT_PERSON = ?, 
                    CONTACT_NUMBER = ?, 
                    EMAIL = ?, 
                    ADDRESS = ?, 
                    STATUS = ?,
                    UPDATED_AT = NOW()
                WHERE SUPPLIER_ID = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            $supplier_name, 
            $contact_person, 
            $contact_number, 
            $email, 
            $address, 
            $status, 
            $supplier_id
        ]);

        $action_type = "UPDATE_SUPPLIER";
        $action_details = "Updated details for supplier ID: $supplier_id ($supplier_name).";
    }

    // 3. Record Audit Log
    $audit_sql = "INSERT INTO audit_logs (USER_ID, USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS) 
                  VALUES (?, ?, ?, 'SUPPLIERS', ?, ?)";
    $audit_stmt = $conn->prepare($audit_sql);
    $audit_stmt->execute([$user_id, $username, $action_type, $action_details, $ip_address]);

    // Commit Transaction
    $conn->commit();

    echo json_encode([
        'success' => true, 
        'message' => empty($supplier_id) ? "Supplier added successfully." : "Supplier updated successfully."
    ]);

} catch (Exception $e) {
    // Rollback on Error
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    
    // Log the actual error to your PHP error log for debugging
    error_log("Supplier Save Error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false, 
        'message' => 'Database error occurred: ' . $e->getMessage()
    ]);
}
?>