<?php
// globalxadminzportal/resetPassword.php
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json');

require_once '../config/SadminConnection.php';

$data = json_decode(file_get_contents('php://input'), true);

$email = trim($data['email'] ?? '');
$otp = trim($data['otp'] ?? '');
$password = $data['password'] ?? '';

if (!$email || !$otp || !$password) {
    echo json_encode(['success' => false, 'message' => 'All fields are required.']);
    exit;
}

if (strlen($password) < 8) {
    echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters long.']);
    exit;
}

try {
    // 1. Verify OTP and check expiration
    $stmt = $conn->prepare("SELECT admin_id, reset_token, reset_expires FROM admin_users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'Invalid request.']);
        exit;
    }

    if ($user['reset_token'] !== $otp) {
        echo json_encode(['success' => false, 'message' => 'Invalid reset code.']);
        exit;
    }

    if (strtotime($user['reset_expires']) < time()) {
        echo json_encode(['success' => false, 'message' => 'Reset code has expired. Please request a new one.']);
        exit;
    }

    // 2. Hash new password and clear the token
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    $update = $conn->prepare("UPDATE admin_users SET password = ?, reset_token = NULL, reset_expires = NULL WHERE admin_id = ?");
    $update->execute([$hashed_password, $user['admin_id']]);

    echo json_encode(['success' => true, 'message' => 'Password has been reset successfully. You can now log in.']);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'A system error occurred.']);
}
?>