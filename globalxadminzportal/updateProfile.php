<?php
// globalxadminportal/updateProfile.php
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json');

session_start();
require_once '../config/SadminConnection.php';

// Security check
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

$admin_id = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? '';

// ────────────────────────────────────────────────────────
// ACTION 1: UPDATE PERSONAL INFO
// ────────────────────────────────────────────────────────
if ($action === 'update_info') {
    $full_name = trim($data['full_name'] ?? '');
    $email = trim($data['email'] ?? '');

    if (empty($full_name) || empty($email)) {
        echo json_encode(['success' => false, 'message' => 'Name and email are required.']);
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email format.']);
        exit;
    }

    try {
        // Check if the new email is already taken by a different user
        $check = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
        $check->execute([$email, $admin_id]);
        if ($check->fetch()) {
            echo json_encode(['success' => false, 'message' => 'This email is already in use by another account.']);
            exit;
        }

        // Update the database
        $stmt = $conn->prepare("UPDATE users SET full_name = ?, email = ? WHERE user_id = ?");
        $stmt->execute([$full_name, $email, $admin_id]);

        // Update the active session variables so the UI reflects the change immediately
        $_SESSION['full_name'] = $full_name;
        $_SESSION['email'] = $email;

        echo json_encode(['success' => true, 'message' => 'Profile information updated successfully.']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'A database error occurred.']);
    }
    exit;
}

// ────────────────────────────────────────────────────────
// ACTION 2: CHANGE PASSWORD
// ────────────────────────────────────────────────────────
if ($action === 'update_password') {
    $current_password = $data['current_password'] ?? '';
    $new_password = $data['new_password'] ?? '';

    if (empty($current_password) || empty($new_password)) {
        echo json_encode(['success' => false, 'message' => 'Both current and new passwords are required.']);
        exit;
    }

    if (strlen($new_password) < 8) {
        echo json_encode(['success' => false, 'message' => 'New password must be at least 8 characters.']);
        exit;
    }

    try {
        // 1. Fetch the user's current hashed password from the database
        $stmt = $conn->prepare("SELECT password FROM users WHERE user_id = ?");
        $stmt->execute([$admin_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            echo json_encode(['success' => false, 'message' => 'User not found.']);
            exit;
        }

        // 2. Verify that the "Current Password" they typed actually matches the database
        if (!password_verify($current_password, $user['password'])) {
            echo json_encode(['success' => false, 'message' => 'Incorrect current password.']);
            exit;
        }

        // 3. Hash the new password and update the database
        $new_hashed = password_hash($new_password, PASSWORD_DEFAULT);
        
        $update = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
        $update->execute([$new_hashed, $admin_id]);

        echo json_encode(['success' => true, 'message' => 'Password changed successfully!']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'A database error occurred.']);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action request.']);
?>