<?php
// ../process/validateRegistration.php
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json');

include '../config/Connection.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$fullname = trim($_POST['fullname'] ?? '');
$email    = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

// --- Validation ---
if (empty($fullname)) {
    echo json_encode(['success' => false, 'message' => 'Full name is required.']);
    exit;
}

if (empty($email)) {
    echo json_encode(['success' => false, 'message' => 'Email is required.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email format.']);
    exit;
}

if (empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Password is required.']);
    exit;
}

if (strlen($password) < 8) {
    echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters.']);
    exit;
}

try {
    if (!isset($conn)) {
        throw new Exception("Database connection failed.");
    }

    // Start Transaction to ensure data integrity
    $conn->beginTransaction();

    // 1. Insert User
    $sqlUser = "INSERT INTO USERS (FULL_NAME, EMAIL, PASSWORD, CREATED_AT) 
                VALUES (:fullname, :email, :password, NOW())";
    
    $stmtUser = $conn->prepare($sqlUser);
    
    $paramsUser = [
        ":fullname" => $fullname,
        ":email"    => $email,
        ":password" => password_hash($password, PASSWORD_BCRYPT)
    ];

    if (!$stmtUser->execute($paramsUser)) {
        throw new Exception("User registration failed.");
    }

    // 2. Get the new User ID
    $newUserId = $conn->lastInsertId();

    // 3. Insert Default Access Controls (All Zeros)
    // Note: Since your DB schema defaults all permission columns to 0, 
    // we only need to insert the user_id and creation date.
    $sqlAccess = "INSERT INTO access_control (user_id, created_at) VALUES (:uid, NOW())";
    
    $stmtAccess = $conn->prepare($sqlAccess);
    
    if (!$stmtAccess->execute([':uid' => $newUserId])) {
        throw new Exception("Failed to initialize user permissions.");
    }

    // Commit Transaction if both inserts succeed
    $conn->commit();

    echo json_encode(['success' => true, 'message' => 'Registration successful!']);
    exit;

} catch (PDOException $e) {
    // Rollback changes if database error occurs
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    $errorMsg = "Database error.";
    
    // Check for Duplicate Email (MySQL Error 1062 / SQLSTATE 23000)
    if ($e->getCode() == '23000' || strpos($e->getMessage(), 'Duplicate entry') !== false) {
        $errorMsg = "This email address is already registered.";
    }

    echo json_encode(['success' => false, 'message' => $errorMsg]);
    exit;

} catch (Exception $e) {
    // Rollback changes for generic errors
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}
?>