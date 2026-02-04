<?php
// ../process/validateLogin.php
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json');

include '../config/Connection.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

// --- Validation ---
if (empty($email)) {
    echo json_encode(['success' => false, 'message' => 'Email is required.']);
    exit;
}

if (empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Password is required.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email format.']);
    exit;
}

try {
    if (!isset($conn)) {
        throw new Exception("Database connection failed.");
    }

    // Fetch user by email
    $sql = "SELECT USER_ID, FULL_NAME, EMAIL, USER_TYPE, PASSWORD FROM USERS WHERE EMAIL = :email LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':email' => $email]);
    
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        // Log failed login attempt
        logAuditTrail($conn, $email, 'LOGIN_FAILED', 'USERS', 'No account found with this email', $_SERVER['REMOTE_ADDR'] ?? 'Unknown');
        
        echo json_encode(['success' => false, 'message' => 'Invalid email or password.']);
        exit;
    }

    // Verify password
    if (!password_verify($password, $user['PASSWORD'])) {
        // Log failed login attempt
        logAuditTrail($conn, $email, 'LOGIN_FAILED', 'USERS', 'Incorrect password', $_SERVER['REMOTE_ADDR'] ?? 'Unknown');
        
        echo json_encode(['success' => false, 'message' => 'Invalid email or password.']);
        exit;
    }

    // Login successful - start session
    if (!isset($_SESSION)) {
        session_start();
    }
    
    $_SESSION['user'] = $user;

    // Log successful login
    logAuditTrail($conn, $user['FULL_NAME'], 'LOGIN', 'USERS', 'User logged in successfully', $_SERVER['REMOTE_ADDR'] ?? 'Unknown');

    echo json_encode([
        'success' => true, 
        'message' => 'Login successful!',
        'user' => [
            'name' => $user['FULL_NAME'],
            'email' => $user['EMAIL'],
            'type' => $user['USER_TYPE']
        ]
    ]);
    exit;

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error occurred.']);
    exit;
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'System error occurred.']);
    exit;
}

// Helper function for audit logging
function logAuditTrail($conn, $username, $actionType, $tableName, $actionDetails, $ipAddress) {
    try {
        $logSql = "INSERT INTO AUDIT_LOGS 
                   (USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS, CREATED_AT) 
                   VALUES 
                   (:usr, :action, :table, :details, :ip, NOW())";
        
        $logStmt = $conn->prepare($logSql);
        $logStmt->execute([
            ':usr' => $username,
            ':action' => $actionType,
            ':table' => $tableName,
            ':details' => $actionDetails,
            ':ip' => $ipAddress
        ]);
    } catch (Exception $e) {
        // Silently fail - don't break login flow
    }
}
?>