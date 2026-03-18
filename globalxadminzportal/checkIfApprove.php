<?php
// globalxadminportal/checkIfApprove.php
// Checks whether a registered user is an approved Super Admin or a verified farm client.
//
// Returns JSON — call via fetch or include directly.
//
// AJAX usage:  GET checkIfApprove.php?email=user@example.com
// Middleware:  include 'checkIfApprove.php';  (uses $_SESSION['admin']['email'])

header('Content-Type: application/json');
session_start();

include '../config/SadminConnection.php';

// Resolve email: query param > POST body > session
$email = trim($_GET['email'] ?? $_POST['email'] ?? '');
if (empty($email) && isset($_SESSION['admin']['email'])) {
    $email = $_SESSION['admin']['email'];
}

if (empty($email)) {
    echo json_encode(['approved' => false, 'status' => 'no_email', 'message' => 'No email provided.']);
    exit;
}

try {
    $stmt = $conn->prepare("SELECT * FROM admin_users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$admin) {
        echo json_encode(['approved' => false, 'status' => 'not_found', 'message' => 'Account not found.']);
        exit;
    }

    // ── ACTIVE account ────────────────────────────────────────────────────────
    if ($admin['is_active'] == 1) {

        // ── Super Admin / Support ─────────────────────────────────────────────
        if (in_array($admin['role'], ['superadmin', 'support'])) {
            echo json_encode([
                'approved'  => true,
                'status'    => 'active',
                'identity'  => 'superadmin',
                'role'      => $admin['role'],
                'full_name' => $admin['full_name'],
                'email'     => $admin['email'],
                'message'   => 'Certified Super Admin. Access granted.',
            ]);
            exit;
        }

        // ── Farm Client ───────────────────────────────────────────────────────
        if ($admin['role'] === 'client') {

            // Find the associated farm via registration log
            $farmStmt = $conn->prepare("
                SELECT f.farm_id, f.farm_name, f.db_name, f.status AS farm_status
                FROM farm_activity_log l
                JOIN farms f ON l.farm_id = f.farm_id
                WHERE l.admin_id = ? AND l.action = 'CLIENT_REGISTERED'
                ORDER BY l.created_at DESC LIMIT 1
            ");
            $farmStmt->execute([$admin['admin_id']]);
            $farm = $farmStmt->fetch(PDO::FETCH_ASSOC);

            if (!$farm) {
                echo json_encode(['approved' => false, 'status' => 'no_farm', 'identity' => 'client', 'message' => 'No farm association found for this client account.']);
                exit;
            }

            if (in_array($farm['farm_status'], ['Suspended', 'Cancelled'])) {
                echo json_encode([
                    'approved'  => false,
                    'status'    => 'farm_' . strtolower($farm['farm_status']),
                    'identity'  => 'client',
                    'farm_name' => $farm['farm_name'],
                    'message'   => "Your farm ({$farm['farm_name']}) is {$farm['farm_status']}. Contact support.",
                ]);
                exit;
            }

            // Verify activation in the farm's own users table
            $farmUserActive = true; // default true in case farm DB is unreachable
            try {
                $farmConn = new PDO(
                    "mysql:host=" . SADMIN_DB_HOST . ";dbname={$farm['db_name']};charset=utf8mb4",
                    SADMIN_DB_ROOT_USER, SADMIN_DB_ROOT_PASS,
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                );
                $fu = $farmConn->prepare("SELECT IS_ACTIVE FROM users WHERE EMAIL = ? LIMIT 1");
                $fu->execute([$email]);
                $farmUser = $fu->fetch(PDO::FETCH_ASSOC);
                $farmUserActive = ($farmUser && $farmUser['IS_ACTIVE'] == 1);
            } catch (Exception $e) {
                error_log("checkIfApprove: Could not connect to farm DB [{$farm['db_name']}]: " . $e->getMessage());
            }

            if (!$farmUserActive) {
                echo json_encode([
                    'approved'  => false,
                    'status'    => 'farm_pending',
                    'identity'  => 'client',
                    'farm_name' => $farm['farm_name'],
                    'message'   => "Your account in {$farm['farm_name']} is pending activation by the farm administrator.",
                ]);
                exit;
            }

            echo json_encode([
                'approved'  => true,
                'status'    => 'active',
                'identity'  => 'client',
                'role'      => 'client',
                'farm_id'   => $farm['farm_id'],
                'farm_name' => $farm['farm_name'],
                'db_name'   => $farm['db_name'],
                'full_name' => $admin['full_name'],
                'email'     => $admin['email'],
                'message'   => "Certified client of {$farm['farm_name']}. Access granted.",
            ]);
            exit;
        }
    }

    // ── INACTIVE / PENDING account ────────────────────────────────────────────
    $msg = match($admin['role']) {
        'superadmin', 'support' => 'Your Super Admin account is pending approval by an existing Super Admin.',
        'client'                => 'Your farm client account is pending approval by the farm administrator.',
        default                 => 'Your account is inactive. Please contact support.',
    };

    echo json_encode([
        'approved' => false,
        'status'   => 'pending',
        'identity' => in_array($admin['role'], ['superadmin','support']) ? 'superadmin' : 'client',
        'role'     => $admin['role'],
        'message'  => $msg,
    ]);

} catch (Exception $e) {
    echo json_encode(['approved' => false, 'status' => 'error', 'message' => 'Verification error: ' . $e->getMessage()]);
}
?>