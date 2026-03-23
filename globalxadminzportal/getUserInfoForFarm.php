<?php
// globalxadminportal/getUserInfoForFarm.php
session_start();
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json');

set_exception_handler(function($e) {
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
    exit;
});

// Auth check
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Session expired. Please log in again.']);
    exit;
}

// ── THE FIX ───────────────────────────────────────────────────────────────────
// The old version re-queried sadmin_farms with LIMIT 1, which always returned
// the FIRST assigned farm regardless of what the user selected in my_farms.php.
// That overwrote $_SESSION['active_farm'] with the wrong farm every time.
//
// Correct approach: trust $_SESSION['active_farm'] which was set by
// my_farms.php's select_farm action when the user explicitly clicked a farm.
// This file must NEVER re-derive which farm to use from a database query.
// ─────────────────────────────────────────────────────────────────────────────
if (
    !isset($_SESSION['active_farm']['db_name']) ||
    !isset($_SESSION['active_farm']['farm_id']) ||
    !isset($_SESSION['active_farm']['farm_name'])
) {
    echo json_encode(['success' => false, 'message' => 'No farm selected. Please select a farm first.']);
    exit;
}

// Read directly from session — do NOT query the DB to re-derive this
$db_name    = $_SESSION['active_farm']['db_name'];
$user_email = $_SESSION['email'];

// ── Central DB (only needed if you want to do extra validation) ───────────────
$conn = null;
$_sadmin_path = __DIR__ . '/../config/SadminConnection.php';
if (file_exists($_sadmin_path)) {
    require_once $_sadmin_path;
}
if (!isset($conn) || $conn === null) {
    try {
        $conn = new PDO(
            'mysql:host=localhost;dbname=sadmin_farms;charset=utf8mb4',
            'root', 'v1i1n1x1',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Central DB connection failed: ' . $e->getMessage()]);
        exit;
    }
}

// ── Connect to the selected farm's tenant database ────────────────────────────
try {
    $farmConn = new PDO(
        'mysql:host=localhost;dbname=' . $db_name . ';charset=utf8mb4',
        'root', 'v1i1n1x1',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Farm DB connection failed: ' . $e->getMessage()]);
    exit;
}

// ── Fetch user from the tenant DB by email ────────────────────────────────────
// Tenant users table uses UPPERCASE column names (from farm_system.sql).
$newstmt = $farmConn->prepare("
    SELECT USER_ID, FULL_NAME, EMAIL, USER_TYPE, CONTACT_INFO, IS_ACTIVE
    FROM   users
    WHERE  EMAIL = ?
    LIMIT  1
");
$newstmt->execute([$user_email]);
$curruser = $newstmt->fetch(PDO::FETCH_ASSOC);

if (!$curruser) {
    echo json_encode(['success' => false, 'message' => 'Your account was not found in this farm. Contact your farm administrator.']);
    exit;
}

if ((int)$curruser['IS_ACTIVE'] !== 1) {
    echo json_encode(['success' => false, 'message' => 'Your account in this farm is pending activation by the farm administrator.']);
    exit;
}

// ── Store tenant user in session and redirect ─────────────────────────────────
$_SESSION['user'] = $curruser;

header('Location: ../views/admin_dashboard.php');
exit;