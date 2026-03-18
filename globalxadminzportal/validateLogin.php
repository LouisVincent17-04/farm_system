<?php
// globalxadminportal/validateLogin.php
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json');

require_once '../config/SadminConnection.php';

$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

// ── Validate input ────────────────────────────────────────────────
$email    = trim($data['email']    ?? '');
$password =      $data['password'] ?? '';

if (!$email || !$password) {
    echo json_encode(['success' => false, 'message' => 'Email and password are required.']);
    exit;
}

try {
    // ── Fetch user ────────────────────────────────────────────────────
    $stmt = $conn->prepare("
        SELECT admin_id, full_name, email, password, status, is_incharge
        FROM admin_users
        WHERE email = ?
        LIMIT 1
    ");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // ── Checks ────────────────────────────────────────────────────────
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'Invalid email or password.']);
        exit;
    }

    if (!password_verify($password, $user['password'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid email or password.']);
        exit;
    }

    // Check if account is Pending (0)
    if ((int)$user['status'] === 0) {
        echo json_encode(['success' => false, 'message' => 'Your account is currently pending approval.']);
        exit;
    }

    // Check if account is Disabled/Rejected (-1)
    if ((int)$user['status'] === -1) {
        echo json_encode(['success' => false, 'message' => 'Your account has been disabled by an administrator.']);
        exit;
    }

    // ── Start session ─────────────────────────────────────────────────
    session_start();
    $_SESSION['admin']        = $user['admin_id'];
    $_SESSION['full_name']    = $user['full_name'];
    $_SESSION['email']        = $user['email'];
    $_SESSION['status']       = $user['status'];
    $_SESSION['is_incharge']  = (int)$user['is_incharge'];

    echo json_encode([
        'success'      => true,
        'message'      => 'Login successful.',
        'is_incharge'  => (int)$user['is_incharge'],
    ]);

} catch (Exception $e) {
    // Return a clean JSON error if the database connection fails
    echo json_encode(['success' => false, 'message' => 'A system error occurred.']);
}
?>