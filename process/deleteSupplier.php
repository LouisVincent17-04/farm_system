<?php
// process/deleteSupplier.php
header('Content-Type: application/json');
include '../config/Connection.php';
session_start();

// Security check
if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$user_id = !empty($_SESSION['user']['USER_ID']) ? $_SESSION['user']['USER_ID'] : 1;
$username = $_SESSION['user']['FULL_NAME'] ?? 'System';
$ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';

$supplier_id = $_POST['supplier_id'] ?? '';

if (empty($supplier_id)) {
    echo json_encode(['success' => false, 'message' => 'Supplier ID is required.']);
    exit;
}

try {
    // 1. Get the supplier's name (in case your items table stores the name instead of the ID)
    $stmtName = $conn->prepare("SELECT SUPPLIER_NAME FROM suppliers WHERE SUPPLIER_ID = ?");
    $stmtName->execute([$supplier_id]);
    $supplier = $stmtName->fetch(PDO::FETCH_ASSOC);

    if (!$supplier) {
        echo json_encode(['success' => false, 'message' => 'Supplier not found.']);
        exit;
    }
    
    $supplier_name = $supplier['SUPPLIER_NAME'];

    // 2. CONSTRAINT CHECK: Is this supplier referenced in the items/purchases table?
    // We check both ID and Name just to be safe based on your VARCHAR setup
    $checkStmt = $conn->prepare("SELECT COUNT(*) FROM items WHERE supplier = ? OR supplier = ?");
    $checkStmt->execute([$supplier_id, $supplier_name]);
    $isReferenced = $checkStmt->fetchColumn();

    if ($isReferenced > 0) {
        echo json_encode([
            'success' => false, 
            'message' => 'Cannot delete this supplier because it is currently referenced in existing items or purchases.'
        ]);
        exit;
    }

    // 3. Start Transaction for Deletion
    $conn->beginTransaction();

    // Delete the supplier
    $delStmt = $conn->prepare("DELETE FROM suppliers WHERE SUPPLIER_ID = ?");
    $delStmt->execute([$supplier_id]);

    // 4. Record Audit Log
    $action_type = "DELETE_SUPPLIER";
    $action_details = "Deleted supplier: $supplier_name (ID: $supplier_id).";
    
    $audit_sql = "INSERT INTO audit_logs (USER_ID, USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS) 
                  VALUES (?, ?, ?, 'SUPPLIERS', ?, ?)";
    $audit_stmt = $conn->prepare($audit_sql);
    $audit_stmt->execute([$user_id, $username, $action_type, $action_details, $ip_address]);

    $conn->commit();

    echo json_encode(['success' => true, 'message' => 'Supplier deleted successfully.']);

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("Supplier Delete Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred.']);
}
?>