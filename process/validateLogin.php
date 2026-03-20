<?php
// session_start() MUST be the absolute first line — before any
// includes, output, or logic. A late session_start() causes PHP
// to emit a warning that corrupts the JSON response, making
// JSON.parse() fail silently in the browser.
// process/validationLogin.php which is the main login handler of my single sign-on system, is called via AJAX from the login form. If session_start()
// this should be modified and make this point to the sadmin_farms database instead of the farm-specific databases, since login credentials are stored in a central users table in the sadmin_farms database. The Connection.php file should be updated to connect to the sadmin_farms database for this script, and the SQL query should be adjusted to select from the users table in sadmin_farms instead of the USERS table in the farm-specific databases.
session_start();

error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json');

include '../config/Connection.php';

// ── Method guard ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

// ── Inputs ────────────────────────────────────────────────
$email    = trim($_POST['email']    ?? '');
$password =      $_POST['password'] ?? '';

// ── Validation ────────────────────────────────────────────
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

// ── Database ──────────────────────────────────────────────
try {
    if (!isset($conn)) {
        throw new Exception("Database connection failed.");
    }

    $stmt = $conn->prepare(
        "SELECT USER_ID, FULL_NAME, EMAIL, USER_TYPE, PASSWORD
         FROM USERS
         WHERE EMAIL = :email
         LIMIT 1"
    );
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // No account found
    if (!$user) {
        logAuditTrail($conn, $email, 'LOGIN_FAILED', 'USERS',
            'No account found with this email',
            $_SERVER['REMOTE_ADDR'] ?? 'Unknown');
        echo json_encode(['success' => false, 'message' => 'Invalid email or password.']);
        exit;
    }

    // Wrong password
    if (!password_verify($password, $user['PASSWORD'])) {
        logAuditTrail($conn, $email, 'LOGIN_FAILED', 'USERS',
            'Incorrect password',
            $_SERVER['REMOTE_ADDR'] ?? 'Unknown');
        echo json_encode(['success' => false, 'message' => 'Invalid email or password.']);
        exit;
    }

    // ── Success ───────────────────────────────────────────
    $_SESSION['user'] = $user;

    logAuditTrail($conn, $user['FULL_NAME'], 'LOGIN', 'USERS',
        'User logged in successfully',
        $_SERVER['REMOTE_ADDR'] ?? 'Unknown');

    echo json_encode([
        'success' => true,
        'message' => 'Login successful!',
        'user'    => [
            'name'  => $user['FULL_NAME'],
            'email' => $user['EMAIL'],
            'type'  => $user['USER_TYPE']
        ]
    ]);
    exit;

} catch (PDOException $e) {
    error_log('PDO Error in validateLogin: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred.']);
    exit;
} catch (Exception $e) {
    error_log('Error in validateLogin: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}

// ── Audit trail ───────────────────────────────────────────
function logAuditTrail($conn, $username, $actionType, $tableName, $actionDetails, $ipAddress) {
    try {
        $conn->prepare(
            "INSERT INTO AUDIT_LOGS
                (USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS, CREATED_AT)
             VALUES (:usr, :action, :table, :details, :ip, NOW())"
        )->execute([
            ':usr'     => $username,
            ':action'  => $actionType,
            ':table'   => $tableName,
            ':details' => $actionDetails,
            ':ip'      => $ipAddress
        ]);
    } catch (Exception $e) {
        // Silently fail — never block the login response
    }
}
?>