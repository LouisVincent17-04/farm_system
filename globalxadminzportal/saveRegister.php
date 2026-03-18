<?php
// globalxadminportal/saveRegister.php
header('Content-Type: application/json');

require_once '../config/SadminConnection.php';

$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

// ── Validate input ────────────────────────────────────────────────
$full_name = trim($data['full_name'] ?? '');
$email     = trim($data['email']     ?? '');
$password  =      $data['password']  ?? '';

if (!$full_name || !$email || !$password) {
    echo json_encode(['success' => false, 'message' => 'All fields are required.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email address.']);
    exit;
}

if (strlen($password) < 8) {
    echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters.']);
    exit;
}

// ── Check duplicate email ─────────────────────────────────────────
$stmt = $conn->prepare("SELECT admin_id FROM admin_users WHERE email = ? LIMIT 1");
$stmt->execute([$email]);

if ($stmt->fetch()) {
    echo json_encode(['success' => false, 'message' => 'Email is already registered.']);
    exit;
}

// ── Insert ────────────────────────────────────────────────────────
$hashed = password_hash($password, PASSWORD_BCRYPT);

$insert = $conn->prepare("
    INSERT INTO admin_users (full_name, email, password, status)
    VALUES (?, ?, ?, 0)
");

$insert->execute([$full_name, $email, $hashed]);

echo json_encode([
    'success' => true,
    'status'  => 'pending',
    'message' => 'Your account is pending approval. A Super Admin will review your request.',
]);