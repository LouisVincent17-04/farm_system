<?php
// globalxadminportal/checkAdminAuth.php
// Include this at the top of every protected admin page.
// Usage: include 'checkAdminAuth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin'])) {
    // AJAX request — return JSON
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Session expired. Please log in again.']);
        exit;
    }
    // Regular page request — redirect
    header('Location: login.php');
    exit;
}

// Optional: role check
// Usage: checkAdminRole('superadmin');
function checkAdminRole(string $requiredRole): void {
    if (!isset($_SESSION['admin']) || $_SESSION['admin']['role'] !== $requiredRole) {
        header('HTTP/1.1 403 Forbidden');
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Access denied.']);
        } else {
            echo '<h1 style="font-family:sans-serif;color:#ef4444;text-align:center;margin-top:4rem;">403 — Access Denied</h1>';
        }
        exit;
    }
}
?>