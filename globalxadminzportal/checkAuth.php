<?php
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id'])) {
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Session expired.']);
    } else {
        header('Location: login.php');
    }
    exit;
}

function checkRole($allowed): void {
    $role = $_SESSION['role'] ?? '';
    if (!in_array($role, (array)$allowed, true)) {
        header('Location: ' . (in_array($role, ['superadmin','owner']) ? 'farm_page.php' : 'my_farms.php'));
        exit;
    }
}
function isSuperAdmin(): bool { return ($_SESSION['role'] ?? '') === 'superadmin'; }
function isOwner(): bool      { return ($_SESSION['role'] ?? '') === 'owner'; }
function isManager(): bool    { return in_array($_SESSION['role'] ?? '', ['superadmin','owner'], true); }