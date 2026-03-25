<?php
// globalxadminzportal/validateLogin.php
session_start();
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json');

set_exception_handler(function($e) {
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
    exit;
});

// Central DB connection
$conn = null;
$_sadmin_path = __DIR__ . '/../config/SadminConnection.php';
if (file_exists($_sadmin_path)) {
    require_once $_sadmin_path;
}
if (!isset($conn) || $conn === null) {
    try {
        $conn = new PDO(
            'mysql:host=192.168.1.131;dbname=sadmin_farms;charset=utf8mb4',
            'pisadmin', 'adminpis',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'DB connection failed: ' . $e->getMessage()]);
        exit;
    }
}

// Read input — supports both JSON and form POST
$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) $data = $_POST;

$email    = trim($data['email']    ?? '');
$password =      $data['password'] ?? '';

if (!$email || !$password) {
    echo json_encode(['success' => false, 'message' => 'Email and password are required.']);
    exit;
}

try {
    $stmt = $conn->prepare("
        SELECT user_id, full_name, email, password, role, status, phone_no, is_global, is_owner
        FROM   users
        WHERE  email = ?
        LIMIT  1
    ");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !password_verify($password, $user['password'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid email or password.']);
        exit;
    }

    if ((int)$user['status'] === 0) {
        echo json_encode(['success' => false, 'message' => 'Your account is pending approval.']);
        exit;
    }

    if ((int)$user['status'] === -1) {
        echo json_encode(['success' => false, 'message' => 'Account disabled. Contact your administrator.']);
        exit;
    }

    // Fetch farm IDs for session
    $farm_ids = [];
    if ($user['role'] !== 'superadmin') {
        $farmStmt = $conn->prepare("
            SELECT af.farm_id FROM assigned_farms af
            JOIN   farms f ON f.farm_id = af.farm_id
            WHERE  af.user_id = ? AND f.farm_status = 1
            ORDER  BY af.assigned_at ASC
        ");
        $farmStmt->execute([$user['user_id']]);
        $farm_ids = $farmStmt->fetchAll(PDO::FETCH_COLUMN);
    }

    // Count assigned farms
    $countStmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM assigned_farms WHERE user_id = ?");
    $countStmt->execute([$user['user_id']]);
    $assigned_farm_count = (int)($countStmt->fetchColumn());

    session_regenerate_id(true);

    $_SESSION['user_id']   = $user['user_id'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['email']     = $user['email'];
    $_SESSION['role']      = $user['role'];
    $_SESSION['status']    = (int)$user['status'];
    $_SESSION['is_global'] = (int)$user['is_global'];
    $_SESSION['is_owner']  = (int)$user['is_owner'];
    $_SESSION['farm_ids']  = $farm_ids;

    $redirect = '';

    // ── Route 1: superadmin in-charge → portal dashboard
    if ((int)$user['is_global'] === 1) {
        $redirect = 'farm_page.php';

    // ── Route 2: owner or superadmin with multiple farms → pick a farm
    } elseif (((int)$user['is_owner'] === 1 || $user['role'] === 'superadmin')) {
        $redirect = 'my_farms.php';

    // ── Route 3: single farm user → auto-select farm and go straight to dashboard
    } else {

        // ── Step 1: Get the farm's db_name from sadmin_farms ─────────────────
        $farmStmt = $conn->prepare("
            SELECT f.farm_id, f.farm_name, dc.db_name
            FROM   assigned_farms af
            JOIN   farms f  ON f.farm_id  = af.farm_id
            JOIN   database_connections dc ON dc.db_key = f.db_key
            WHERE  af.user_id = ? AND f.farm_status = 1
            LIMIT  1
        ");
        $farmStmt->execute([$user['user_id']]);
        $farmRow = $farmStmt->fetch(PDO::FETCH_ASSOC);

        if (!$farmRow) {
            echo json_encode(['success' => false, 'message' => 'No active farm found. Contact your administrator.']);
            exit;
        }

        // ── Step 2: Store db_name in session ──────────────────────────────────
        // Connection.php reads $_SESSION['active_farm']['db_name'] to connect.
        // This MUST be set before including Connection.php.
        $_SESSION['active_farm'] = [
            'farm_id'   => (int)$farmRow['farm_id'],
            'farm_name' => $farmRow['farm_name'],
            'db_name'   => $farmRow['db_name'],  // ← e.g. "test_farm"
        ];

        // ── Step 3: Connect to the farm's tenant DB using db_name ─────────────
        // We connect directly here instead of relying on Connection.php's
        // session redirect logic, since we just set the session above.
        try {
            $farmConn = new PDO(
                'mysql:host=192.168.1.131;dbname=' . $farmRow['db_name'] . ';charset=utf8mb4',
                'pisadmin', 'adminpis',
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
            );
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Farm DB connection failed: ' . $e->getMessage()]);
            exit;
        }

        // ── Step 4: Fetch user record from the tenant farm DB ─────────────────
        $newstmt = $farmConn->prepare("
            SELECT USER_ID, FULL_NAME, EMAIL, USER_TYPE, CONTACT_INFO, IS_ACTIVE
            FROM   users
            WHERE  EMAIL = ?
            LIMIT  1
        ");
        $newstmt->execute([$user['email']]);
        $curruser = $newstmt->fetch(PDO::FETCH_ASSOC);

        $_SESSION['user'] = $curruser;

        $redirect = '../views/admin_dashboard.php';
    }

    echo json_encode([
        'success'  => true,
        'message'  => 'Login successful.',
        'role'     => $user['role'],
        'redirect' => $redirect,
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>