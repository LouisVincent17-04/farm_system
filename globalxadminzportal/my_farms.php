<?php
// globalxadminzportal/my_farms.php
session_start();

if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
if ($_SESSION['is_global'] == 1)  { header('Location: farm_page.php'); exit; }

require_once '../config/SadminConnection.php';
require_once '../config/FarmConnection.php';

$user_id   = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];
$is_owner  = $_SESSION['is_owner'] ?? 0;

// ============================================================================
// AJAX: Select active farm — writes farm info into session so the farm app
// knows which tenant DB to connect to.
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'select_farm') {
    @ob_end_clean();
    header('Content-Type: application/json');
    $farm_id = (int)($_POST['farm_id'] ?? 0);

    if (!$farm_id) {
        echo json_encode(['success' => false, 'message' => 'No farm selected.']);
        exit;
    }

    // Security: confirm this user is actually assigned to the farm and get its db_name
    $access = $conn->prepare("
        SELECT f.farm_id, f.farm_name, f.farm_status, dc.db_name
        FROM   assigned_farms af
        JOIN   farms f  ON f.farm_id = af.farm_id
        JOIN   database_connections dc ON dc.db_key = f.db_key
        WHERE  af.user_id = ? AND af.farm_id = ?
        LIMIT  1
    ");
    $access->execute([$user_id, $farm_id]);
    $farm = $access->fetch(PDO::FETCH_ASSOC);

    if (!$farm) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized or farm not found.']);
        exit;
    }

    if ((int)$farm['farm_status'] !== 1) {
        echo json_encode(['success' => false, 'message' => 'This farm is currently inactive.']);
        exit;
    }

    // Store active farm in session — your farm app reads $_SESSION['active_farm']
    $_SESSION['active_farm'] = [
        'farm_id'   => (int)$farm['farm_id'],
        'farm_name' => $farm['farm_name'],
        'db_name'   => $farm['db_name'],
    ];

    echo json_encode([
        'success'   => true,
        'farm_name' => $farm['farm_name'],
        'db_name'   => $farm['db_name'],
    ]);
    exit;
}

// ============================================================================
// AJAX: Search users already assigned to a specific farm
// ============================================================================
if (isset($_GET['action']) && $_GET['action'] === 'search_user') {
    @ob_end_clean();
    header('Content-Type: application/json');
    $term    = '%' . trim($_GET['term'] ?? '') . '%';
    $farm_id = (int)($_GET['farm_id'] ?? 0);

    if (!$farm_id) { echo json_encode([]); exit; }

    $accessCheck = $conn->prepare("SELECT 1 FROM assigned_farms WHERE user_id = ? AND farm_id = ?");
    $accessCheck->execute([$user_id, $farm_id]);
    if (!$accessCheck->fetch()) { echo json_encode([]); exit; }

    $stmt = $conn->prepare("
        SELECT u.user_id, u.full_name, u.email, u.status
        FROM   users u
        JOIN   assigned_farms af ON af.user_id = u.user_id
        WHERE  af.farm_id = ?
          AND  (u.full_name LIKE ? OR u.email LIKE ?)
          AND  u.status   IN (0, 1)
          AND  u.user_id != ?
        LIMIT  5
    ");
    $stmt->execute([$farm_id, $term, $term, $user_id]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

// ============================================================================
// AJAX: Assign user to a farm
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'assign_user') {
    @ob_end_clean();
    header('Content-Type: application/json');
    try {
        $target_user_id = (int)$_POST['target_user_id'];
        $target_farm_id = (int)$_POST['farm_id'];

        if (!$target_user_id || !$target_farm_id) throw new Exception("Please select a valid user and farm.");

        $checkAccess = $conn->prepare("SELECT 1 FROM assigned_farms WHERE user_id = ? AND farm_id = ?");
        $checkAccess->execute([$user_id, $target_farm_id]);
        if (!$checkAccess->fetch()) throw new Exception("Unauthorized.");

        // Get user's email and current status for tenant DB update
        $userInfo = $conn->prepare("SELECT email, full_name, status FROM users WHERE user_id = ? LIMIT 1");
        $userInfo->execute([$target_user_id]);
        $targetUser = $userInfo->fetch(PDO::FETCH_ASSOC);
        if (!$targetUser) throw new Exception("User not found.");

        // Insert into assigned_farms (or ignore if already there)
        $stmt = $conn->prepare("INSERT IGNORE INTO assigned_farms (user_id, farm_id) VALUES (?, ?)");
        $stmt->execute([$target_user_id, $target_farm_id]);

        // Approve the user — set status = 1 in central DB
        $conn->prepare("UPDATE users SET status = 1 WHERE user_id = ? AND status = 0")
             ->execute([$target_user_id]);

        // Activate in tenant farm DB + promote to Farm Employee (USER_TYPE = 2)
        try {
            $farmConn = getFarmConnection($target_farm_id);
            $farmConn->prepare("
                UPDATE users
                SET    IS_ACTIVE = 1,
                       USER_TYPE = 2
                WHERE  EMAIL = ?
            ")->execute([$targetUser['email']]);
        } catch (Exception $fe) {
            error_log("assign_user: tenant activation failed — " . $fe->getMessage());
        }

        $wasAlreadyAssigned = $stmt->rowCount() === 0;
        echo json_encode([
            'success' => true,
            'message' => $wasAlreadyAssigned
                ? "{$targetUser['full_name']} already had access — account approved."
                : "{$targetUser['full_name']} has been assigned and approved successfully!"
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}
// ============================================================================

// ============================================================================
// AJAX: Approve or reject a pending employee
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'approve_employee') {
    @ob_end_clean();
    header('Content-Type: application/json');
    try {
        $target_user_id = (int)($_POST['user_id']  ?? 0);
        $farm_id        = (int)($_POST['farm_id']   ?? 0);
        $decision       =      $_POST['decision']   ?? ''; // 'approve' or 'reject'

        if (!$target_user_id || !$farm_id || !in_array($decision, ['approve','reject'])) {
            throw new Exception('Invalid request.');
        }

        // Security: current user must be assigned to that farm
        $check = $conn->prepare("SELECT 1 FROM assigned_farms WHERE user_id = ? AND farm_id = ?");
        $check->execute([$user_id, $farm_id]);
        if (!$check->fetch()) throw new Exception('Unauthorized.');

        // Get the pending user's email and the farm's connection info
        $info = $conn->prepare("
            SELECT u.email, u.full_name
            FROM   users u
            JOIN   assigned_farms af ON af.user_id = u.user_id
            WHERE  u.user_id = ? AND af.farm_id = ?
            LIMIT  1
        ");
        $info->execute([$target_user_id, $farm_id]);
        $row = $info->fetch(PDO::FETCH_ASSOC);
        if (!$row) throw new Exception('User not found.');

        if ($decision === 'approve') {
            // Activate in central DB
            $conn->prepare("UPDATE users SET status = 1 WHERE user_id = ?")
                 ->execute([$target_user_id]);

            // Activate in tenant farm DB + promote to Farm Employee (USER_TYPE = 2)
            try {
                $farmConn = getFarmConnection($farm_id);
                $farmConn->prepare("
                    UPDATE users
                    SET    IS_ACTIVE = 1,
                           USER_TYPE = 2
                    WHERE  EMAIL = ?
                ")->execute([$row['email']]);
            } catch (Exception $fe) {
                error_log("my_farms approve: tenant update failed — " . $fe->getMessage());
            }

            echo json_encode(['success' => true, 'message' => "{$row['full_name']} has been approved and can now log in."]);

        } else {
            // Reject: disable in central, remove assignment, deactivate in tenant
            $conn->prepare("UPDATE users SET status = -1 WHERE user_id = ?")
                 ->execute([$target_user_id]);

            $conn->prepare("DELETE FROM assigned_farms WHERE user_id = ? AND farm_id = ?")
                 ->execute([$target_user_id, $farm_id]);

            try {
                $farmConn = getFarmConnection($farm_id);
                $farmConn->prepare("UPDATE users SET IS_ACTIVE = 0 WHERE EMAIL = ?")
                         ->execute([$row['email']]);
            } catch (Exception $fe) {
                error_log("my_farms reject: tenant update failed — " . $fe->getMessage());
            }

            echo json_encode(['success' => true, 'message' => "{$row['full_name']} has been rejected."]);
        }

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// ============================================================================
// AJAX: Get pending employees for a farm
// ============================================================================
if (isset($_GET['action']) && $_GET['action'] === 'get_pending') {
    @ob_end_clean();
    header('Content-Type: application/json');
    $farm_id = (int)($_GET['farm_id'] ?? 0);
    if (!$farm_id) { echo json_encode([]); exit; }

    $check = $conn->prepare("SELECT 1 FROM assigned_farms WHERE user_id = ? AND farm_id = ?");
    $check->execute([$user_id, $farm_id]);
    if (!$check->fetch()) { echo json_encode([]); exit; }

    $stmt = $conn->prepare("
        SELECT u.user_id, u.full_name, u.email, u.phone_no, u.created_at
        FROM   users u
        JOIN   assigned_farms af ON af.user_id = u.user_id
        WHERE  af.farm_id = ?
          AND  u.status   = 0
          AND  u.role     = 'employee'
        ORDER  BY u.created_at DESC
    ");
    $stmt->execute([$farm_id]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

// Fetch this user's assigned farms including farm_code and db_name
$stmt = $conn->prepare("
    SELECT f.farm_id, f.farm_name, f.farm_status, f.farm_code, f.created_at,
           af.assigned_at,
           dc.db_name
    FROM   assigned_farms af
    JOIN   farms f ON f.farm_id = af.farm_id
    LEFT JOIN database_connections dc ON dc.db_key = f.db_key
    WHERE  af.user_id = ? AND f.farm_status != -1
    ORDER  BY f.farm_name ASC
");
$stmt->execute([$user_id]);
$my_farms = $stmt->fetchAll(PDO::FETCH_ASSOC);

$active_farm_id = $_SESSION['active_farm']['farm_id'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Farms | FarmPro</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg:#07090f; --card:#111720; --border:#1c2535; --border2:#243045;
            --text:#c8d8ec; --muted:#455870; --accent:#3dd68c; --accent2:#07955a;
            --nav-h:64px; --blue:#3b82f6; --gold:#f4c542; --red:#f05252;
        }
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
        body{font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;}
        body::before{content:'';position:fixed;inset:0;z-index:0;pointer-events:none;background:radial-gradient(ellipse 70% 50% at 15% 0%,rgba(61,214,140,.055) 0%,transparent 60%);}
        body::after{content:'';position:fixed;inset:0;z-index:0;pointer-events:none;background-image:linear-gradient(rgba(61,214,140,.018) 1px,transparent 1px),linear-gradient(90deg,rgba(61,214,140,.018) 1px,transparent 1px);background-size:48px 48px;}

        /* Navbar */
        .navbar{position:sticky;top:0;z-index:100;height:var(--nav-h);display:flex;align-items:center;justify-content:space-between;padding:0 2rem;background:rgba(7,9,15,.85);backdrop-filter:blur(16px);border-bottom:1px solid var(--border);}
        .nav-logo{font-family:'Bebas Neue',sans-serif;font-size:1.55rem;color:var(--accent);}
        .nav-right{display:flex;align-items:center;gap:12px;}
        .nav-user{display:flex;align-items:center;gap:9px;padding:.35rem .75rem .35rem .45rem;border-radius:100px;background:rgba(255,255,255,.03);border:1px solid var(--border2);}
        .nav-avatar{width:28px;height:28px;border-radius:50%;background:linear-gradient(135deg,var(--accent),var(--accent2));display:flex;align-items:center;justify-content:center;font-size:.75rem;font-weight:700;color:#051a0e;}
        .nav-username{font-size:.8rem;font-weight:600;color:var(--text);}
        .nav-logout{color:#f87171;text-decoration:none;font-size:.85rem;font-weight:700;transition:.2s;}
        .nav-logout:hover{color:#fca5a5;}

        /* Layout */
        .page-wrap{position:relative;z-index:1;padding:2.5rem 2rem 4rem;max-width:1200px;margin:0 auto;}
        .page-header{display:flex;justify-content:space-between;align-items:flex-end;margin-bottom:2rem;flex-wrap:wrap;gap:15px;}
        .page-title{font-family:'Bebas Neue',sans-serif;font-size:2.6rem;color:#fff;margin-bottom:.2rem;}
        .page-sub{color:var(--muted);font-size:.88rem;}

        /* Active farm banner */
        .active-banner{background:rgba(61,214,140,.06);border:1px solid rgba(61,214,140,.2);border-radius:12px;padding:.9rem 1.25rem;margin-bottom:1.75rem;display:flex;align-items:center;gap:10px;font-size:.85rem;}
        .active-banner strong{color:var(--accent);}

        /* Farm grid */
        .farms-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:20px;}
        .farm-card{background:var(--card);border:1px solid var(--border2);border-radius:16px;padding:1.75rem;cursor:pointer;transition:.25s;position:relative;overflow:hidden;}
        .farm-card:hover{transform:translateY(-4px);border-color:var(--accent);box-shadow:0 12px 30px rgba(0,0,0,.5);}
        .farm-card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:linear-gradient(90deg,var(--accent),var(--accent2));opacity:0;transition:.25s;}
        .farm-card:hover::before,.farm-card.is-active::before{opacity:1;}
        .farm-card.is-active{border-color:var(--accent);}
        .farm-icon{font-size:2rem;margin-bottom:.9rem;display:block;}
        .farm-card-name{font-size:1.2rem;font-weight:700;color:#fff;margin-bottom:.5rem;}
        .badge{display:inline-flex;padding:3px 10px;border-radius:100px;font-size:.68rem;font-weight:700;text-transform:uppercase;margin-bottom:.9rem;}
        .badge-active{background:rgba(61,214,140,.1);color:var(--accent);border:1px solid rgba(61,214,140,.2);}
        .badge-inactive{background:rgba(69,88,112,.12);color:var(--muted);border:1px solid rgba(69,88,112,.2);}
        .badge-curr{background:rgba(61,214,140,.12);color:var(--accent);border:1px solid rgba(61,214,140,.3);font-size:.62rem;margin-left:6px;}
        .farm-meta{font-size:.78rem;color:var(--muted);display:flex;justify-content:space-between;border-top:1px solid var(--border);padding-top:.9rem;margin-top:.9rem;}
        .btn-view{display:block;width:100%;text-align:center;padding:10px;background:rgba(255,255,255,.05);color:#fff;border-radius:8px;font-weight:700;font-size:.88rem;border:none;cursor:pointer;font-family:'DM Sans',sans-serif;margin-top:.9rem;transition:.2s;}
        .farm-card:hover .btn-view{background:var(--accent);color:#000;}

        /* Empty */
        .empty-state{text-align:center;padding:4rem;background:var(--card);border:1px dashed var(--border2);border-radius:16px;color:var(--muted);}

        /* Drawer */
        .drawer-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);backdrop-filter:blur(4px);z-index:200;}
        .drawer-overlay.show{display:block;}
        .drawer{position:fixed;right:0;top:0;bottom:0;z-index:201;width:440px;max-width:100vw;background:var(--card);border-left:1px solid var(--border2);box-shadow:-20px 0 60px rgba(0,0,0,.6);display:flex;flex-direction:column;transform:translateX(100%);transition:transform .3s cubic-bezier(.22,1,.36,1);}
        .drawer.show{transform:translateX(0);}
        .drawer-header{padding:1.5rem;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;flex-shrink:0;}
        .drawer-title{font-family:'Bebas Neue',sans-serif;font-size:1.5rem;letter-spacing:.04em;color:#fff;}
        .drawer-close{background:none;border:none;color:var(--muted);cursor:pointer;font-size:1.3rem;transition:.2s;padding:4px;}
        .drawer-close:hover{color:#fff;}
        .drawer-body{padding:1.5rem;overflow-y:auto;flex:1;}

        .info-row{margin-bottom:1.25rem;}
        .info-label{font-size:.68rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:var(--muted);margin-bottom:.35rem;}
        .info-value{font-size:.95rem;color:#fff;font-weight:600;}
        .info-value.mono{font-family:'DM Mono',monospace;font-size:.88rem;letter-spacing:.04em;}

        .farm-code-box{display:flex;align-items:center;justify-content:space-between;gap:10px;background:rgba(59,130,246,.06);border:1px solid rgba(59,130,246,.25);border-radius:10px;padding:.85rem 1rem;margin-top:.35rem;}
        .farm-code-text{font-family:'DM Mono',monospace;font-size:1.4rem;color:#fff;font-weight:700;letter-spacing:3px;}
        .btn-copy-code{padding:5px 12px;background:rgba(59,130,246,.12);border:1px solid rgba(59,130,246,.3);color:var(--blue);border-radius:7px;font-size:.75rem;font-weight:700;cursor:pointer;transition:.2s;white-space:nowrap;font-family:'DM Sans',sans-serif;}
        .btn-copy-code:hover{background:rgba(59,130,246,.22);}
        .btn-copy-code.copied{background:rgba(61,214,140,.15);color:var(--accent);border-color:rgba(61,214,140,.3);}

        .divider{height:1px;background:var(--border);margin:1.25rem 0;}

        .btn-launch-farm{display:flex;align-items:center;justify-content:center;gap:8px;width:100%;padding:1rem;margin-top:1rem;background:linear-gradient(135deg,var(--accent),var(--accent2));border:none;border-radius:12px;color:#07090f;font-family:'DM Sans',sans-serif;font-weight:800;font-size:.95rem;cursor:pointer;transition:.2s;}
        .btn-launch-farm:hover:not(:disabled){transform:translateY(-2px);box-shadow:0 8px 20px rgba(61,214,140,.3);}
        .btn-launch-farm:disabled{opacity:.5;cursor:not-allowed;transform:none;box-shadow:none;}
        .btn-launch-farm.already-active{background:rgba(61,214,140,.1);color:var(--accent);border:1px solid rgba(61,214,140,.3);}
        .launch-status{margin-top:.75rem;font-size:.8rem;text-align:center;color:var(--muted);min-height:18px;}

        /* Assign button */
        .btn-assign{background:linear-gradient(135deg,var(--blue),#2563eb);border:none;color:#fff;padding:10px 20px;border-radius:8px;font-weight:700;font-family:'DM Sans',sans-serif;cursor:pointer;transition:.2s;display:inline-flex;align-items:center;gap:8px;font-size:.88rem;}
        .btn-assign:hover{transform:translateY(-2px);box-shadow:0 5px 15px rgba(59,130,246,.3);}

        /* Modal */
        .modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);backdrop-filter:blur(4px);z-index:300;align-items:center;justify-content:center;padding:1rem;}
        .modal.show{display:flex;}
        .modal-content{background:var(--card);border-radius:16px;width:100%;max-width:440px;border:1px solid var(--border2);box-shadow:0 25px 50px -12px rgba(0,0,0,.5);}
        .modal-header{padding:1.5rem;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;}
        .modal-header h2{margin:0;font-size:1.1rem;color:#fff;}
        .modal-close{background:none;border:none;color:var(--muted);cursor:pointer;font-size:1.2rem;transition:.2s;}
        .modal-close:hover{color:#fff;}
        .modal-body{padding:1.5rem;}
        .form-group{margin-bottom:1.2rem;position:relative;}
        .form-label{display:block;font-size:.78rem;color:var(--muted);margin-bottom:.4rem;font-weight:600;}
        .form-control{width:100%;padding:11px 13px;background:#0f172a;border:1px solid var(--border);border-radius:8px;color:#fff;font-size:.92rem;outline:none;transition:.2s;font-family:'DM Sans',sans-serif;}
        .form-control:focus{border-color:var(--blue);}
        .ac-dropdown{position:absolute;top:100%;left:0;width:100%;background:#0f172a;border:1px solid var(--blue);border-radius:8px;box-shadow:0 10px 25px rgba(0,0,0,.5);z-index:400;display:none;overflow:hidden;margin-top:4px;max-height:200px;overflow-y:auto;}
        .ac-item{padding:10px 14px;cursor:pointer;border-bottom:1px solid var(--border);transition:.15s;}
        .ac-item:hover{background:rgba(59,130,246,.1);}
        .ac-name{font-weight:700;color:#fff;font-size:.88rem;}
        .ac-email{font-size:.74rem;color:var(--muted);}
        .btn-submit{width:100%;padding:12px;background:linear-gradient(135deg,var(--blue),#2563eb);border:none;color:#fff;border-radius:8px;cursor:pointer;font-weight:700;font-family:'DM Sans',sans-serif;font-size:.95rem;margin-top:10px;transition:.2s;}
        .btn-submit:hover{transform:translateY(-1px);box-shadow:0 4px 15px rgba(59,130,246,.3);}

        @media(max-width:640px){.drawer{width:100vw;}.page-wrap{padding:2rem 1rem;}}

        /* Pending employee rows */
        .pending-item{background:rgba(255,255,255,.03);border:1px solid var(--border);border-radius:10px;padding:.85rem 1rem;margin-bottom:.65rem;}
        .pending-name{font-size:.9rem;font-weight:700;color:#fff;margin-bottom:2px;}
        .pending-email{font-size:.75rem;color:var(--muted);margin-bottom:.65rem;}
        .pending-actions{display:flex;gap:8px;}
        .btn-approve{flex:1;padding:7px;background:rgba(61,214,140,.1);border:1px solid rgba(61,214,140,.3);color:var(--accent);border-radius:7px;font-size:.78rem;font-weight:700;cursor:pointer;transition:.2s;font-family:'DM Sans',sans-serif;}
        .btn-approve:hover{background:var(--accent);color:#051a0e;}
        .btn-reject{flex:1;padding:7px;background:rgba(240,82,82,.08);border:1px solid rgba(240,82,82,.25);color:var(--red);border-radius:7px;font-size:.78rem;font-weight:700;cursor:pointer;transition:.2s;font-family:'DM Sans',sans-serif;}
        .btn-reject:hover{background:var(--red);color:#fff;}
    </style>
</head>
<body>

<nav class="navbar">
    <div class="nav-logo">Farm<span style="color:var(--gold)">Pro</span></div>
    <div class="nav-right">
        <div class="nav-user">
            <div class="nav-avatar"><?= strtoupper(substr($full_name, 0, 1)) ?></div>
            <span class="nav-username"><?= htmlspecialchars($full_name) ?></span>
        </div>
        <a href="logout.php" class="nav-logout">Logout</a>
    </div>
</nav>

<main class="page-wrap">

    <div class="page-header">
        <div>
            <h1 class="page-title">My Farms</h1>
            <p class="page-sub">Click a farm to view its details and launch the dashboard.</p>
        </div>
        <?php if ($is_owner && !empty($my_farms)): ?>
        <button class="btn-assign" onclick="openAssignModal()">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
            Assign User
        </button>
        <?php endif; ?>
    </div>

    <?php if (isset($_SESSION['active_farm'])): ?>
    <div class="active-banner">
        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
        Active farm: <strong><?= htmlspecialchars($_SESSION['active_farm']['farm_name']) ?></strong>
        &nbsp;·&nbsp; <span style="color:var(--muted);font-size:.8rem;">Click a different farm to switch.</span>
    </div>
    <?php endif; ?>

    <?php if (empty($my_farms)): ?>
        <div class="empty-state">
            <div style="font-size:3rem;margin-bottom:1rem;">🌾</div>
            <h3 style="color:#fff;margin-bottom:.5rem;">No Farms Assigned</h3>
            <p>You have not been assigned to any farms yet. Contact your administrator.</p>
        </div>
    <?php else: ?>
        <div class="farms-grid">
            <?php foreach($my_farms as $farm): ?>
            <?php $isActive = $active_farm_id === (int)$farm['farm_id']; ?>
            <div class="farm-card <?= $isActive ? 'is-active' : '' ?>"
                 onclick="openFarmDrawer(<?= htmlspecialchars(json_encode([
                     'farm_id'    => $farm['farm_id'],
                     'farm_name'  => $farm['farm_name'],
                     'farm_status'=> $farm['farm_status'],
                     'farm_code'  => $farm['farm_code'] ?? '',
                     'assigned_at'=> $farm['assigned_at'],
                     'created_at' => $farm['created_at'],
                     'db_name'    => $farm['db_name'] ?? '',
                 ]), ENT_QUOTES) ?>)">

                <span class="farm-icon">🚜</span>

                <div style="display:flex;align-items:center;flex-wrap:wrap;gap:6px;margin-bottom:.5rem;">
                    <div class="farm-card-name"><?= htmlspecialchars($farm['farm_name']) ?></div>
                    <?php if ($isActive): ?>
                        <span class="badge badge-curr">● Active</span>
                    <?php endif; ?>
                </div>

                <?php if ($farm['farm_status'] == 1): ?>
                    <span class="badge badge-active">Operational</span>
                <?php else: ?>
                    <span class="badge badge-inactive">Suspended</span>
                <?php endif; ?>

                <button class="btn-view">View Details & Launch →</button>

                <div class="farm-meta">
                    <span>ID: #<?= $farm['farm_id'] ?></span>
                    <span>Since <?= date('M Y', strtotime($farm['assigned_at'])) ?></span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</main>

<!-- ── Farm Info Drawer ──────────────────────────────────────────────────── -->
<div class="drawer-overlay" id="drawerOverlay" onclick="closeDrawer()"></div>
<div class="drawer" id="farmDrawer">
    <div class="drawer-header">
        <div class="drawer-title" id="dTitle">Farm Details</div>
        <button class="drawer-close" onclick="closeDrawer()">✕</button>
    </div>
    <div class="drawer-body">

        <div class="info-row">
            <div class="info-label">Farm Name</div>
            <div class="info-value" id="dName">—</div>
        </div>

        <div class="info-row">
            <div class="info-label">Status</div>
            <div id="dStatus">—</div>
        </div>

        <div class="info-row">
            <div class="info-label">Farm ID</div>
            <div class="info-value mono" id="dId">—</div>
        </div>

        <div class="info-row">
            <div class="info-label">Assigned Since</div>
            <div class="info-value" id="dAssigned">—</div>
        </div>

        <div class="info-row">
            <div class="info-label">Created</div>
            <div class="info-value" id="dCreated">—</div>
        </div>

        <div class="divider"></div>
        <div class="info-row" id="farmCodeRow" style="display:none;">
            <div class="info-label">Farm Code</div>
            <p style="font-size:.75rem;color:var(--muted);margin-bottom:.6rem;line-height:1.5;">
                Share this code with your employees so they can register and join this specific farm.
            </p>
            <div class="farm-code-box">
                <span class="farm-code-text" id="dCode">—</span>
                <button class="btn-copy-code" id="btnCopyCode" onclick="copyCode()">📋 Copy</button>
            </div>
        </div>

        <div class="divider"></div>

        <button class="btn-launch-farm" id="btnLaunch" onclick="launchFarm()">
            <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
            Launch Farm Dashboard
        </button>
        <div class="launch-status" id="launchStatus"></div>

        <!-- Pending employees section — only for owners -->
        <?php if ($is_owner): ?>
        <div class="divider" style="margin-top:1.5rem;"></div>
        <div id="pendingSection">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;">
                <div style="font-size:.68rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:var(--muted);">
                    Pending Approval
                </div>
                <span id="pendingCount" style="font-size:.72rem;background:rgba(244,197,66,.12);color:var(--gold);border:1px solid rgba(244,197,66,.25);padding:2px 10px;border-radius:100px;font-weight:700;display:none;"></span>
            </div>
            <div id="pendingList">
                <div style="font-size:.82rem;color:var(--muted);text-align:center;padding:1rem 0;">Loading…</div>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>

<!-- ── Assign Modal ──────────────────────────────────────────────────────── -->
<div class="modal" id="assignModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Assign Employee to Farm</h2>
            <button class="modal-close" onclick="closeAssignModal()">✕</button>
        </div>
        <div class="modal-body">
            <form id="assignForm" onsubmit="submitAssignment(event)">
                <input type="hidden" name="action" value="assign_user">
                <input type="hidden" id="target_user_id" name="target_user_id">

                <div class="form-group">
                    <label class="form-label">Select Farm</label>
                    <select name="farm_id" id="assignFarmSelect" class="form-control" required>
                        <option value="" disabled selected>— Choose a Farm —</option>
                        <?php foreach($my_farms as $f): ?>
                            <option value="<?= $f['farm_id'] ?>"><?= htmlspecialchars($f['farm_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Search Approved Employees</label>
                    <input type="text" id="search_input" class="form-control" placeholder="Type name or email…" autocomplete="off" oninput="searchUser(this)">
                    <div id="ac_dropdown" class="ac-dropdown"></div>
                    <div style="font-size:.73rem;color:var(--muted);margin-top:5px;">Shows pending and active employees for the selected farm. Assigning will approve pending accounts.</div>
                </div>

                <button type="submit" class="btn-submit" id="btnAssign">Grant Access</button>
            </form>
        </div>
    </div>
</div>

<script>
    let currentFarm  = null;
    let activeFarmId = <?= $active_farm_id ? (int)$active_farm_id : 'null' ?>;
    let searchTimer  = null;

    // ── Drawer ───────────────────────────────────────────────────────────────
    function openFarmDrawer(farm) {
        currentFarm = farm;

        document.getElementById('dTitle').textContent   = farm.farm_name;
        document.getElementById('dName').textContent    = farm.farm_name;
        document.getElementById('dId').textContent      = '#' + farm.farm_id;
        document.getElementById('dAssigned').textContent = fmtDate(farm.assigned_at);
        document.getElementById('dCreated').textContent  = fmtDate(farm.created_at);

        // Status badge
        const s = document.getElementById('dStatus');
        if (parseInt(farm.farm_status) === 1) {
            s.innerHTML = '<span class="badge badge-active">● Operational</span>';
        } else {
            s.innerHTML = '<span class="badge badge-inactive">● Suspended</span>';
        }

        document.getElementById('dCode').textContent = farm.farm_code || '—';
        document.getElementById('farmCodeRow').style.display = farm.farm_code ? 'block' : 'none';

        // Launch button state
        const btn      = document.getElementById('btnLaunch');
        const isActive = activeFarmId === parseInt(farm.farm_id);
        const inactive = parseInt(farm.farm_status) !== 1;

        btn.disabled = inactive;
        btn.className = 'btn-launch-farm' + (isActive ? ' already-active' : '');
        btn.innerHTML = isActive
            ? '<svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg> Currently Active — Relaunch'
            : '<svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg> Launch Farm Dashboard';

        document.getElementById('launchStatus').textContent = '';

        document.getElementById('drawerOverlay').classList.add('show');
        document.getElementById('farmDrawer').classList.add('show');

        <?php if ($is_owner): ?>
        loadPending(farm.farm_id);
        <?php endif; ?>
    }

    function closeDrawer() {
        document.getElementById('drawerOverlay').classList.remove('show');
        document.getElementById('farmDrawer').classList.remove('show');
    }

    // ── Launch — set active farm in session, then redirect to farm app ───────
    async function launchFarm() {
        if (!currentFarm) return;

        const btn    = document.getElementById('btnLaunch');
        const status = document.getElementById('launchStatus');

        btn.disabled    = true;
        btn.innerHTML   = '⏳ Connecting…';
        status.textContent = '';

        const fd = new FormData();
        fd.append('action',  'select_farm');
        fd.append('farm_id', currentFarm.farm_id);

        try {
            const res  = await fetch(window.location.href, { method:'POST', body:fd });
            const data = await res.json();

            if (data.success) {
                activeFarmId = parseInt(currentFarm.farm_id);
                status.style.color = 'var(--accent)';
                status.textContent = `✅ ${data.farm_name} is now active. Redirecting…`;

                // Update card UI
                document.querySelectorAll('.farm-card').forEach(c => c.classList.remove('is-active'));
                document.querySelectorAll('.farm-card').forEach(c => {
                    if (c.querySelector('.farm-card-name')?.textContent === data.farm_name) {
                        c.classList.add('is-active');
                    }
                });

                // Redirect to the FarmSystem login page
                setTimeout(() => {
                    window.location.href = 'getUserInfoForFarm.php';
                    
                }, 800);

            } else {
                btn.disabled = false;
                btn.innerHTML = '<svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg> Launch Farm Dashboard';
                status.style.color = 'var(--red)';
                status.textContent = '❌ ' + data.message;
            }
        } catch(e) {
            btn.disabled = false;
            btn.innerHTML = 'Launch Farm Dashboard';
            status.style.color = 'var(--red)';
            status.textContent = '❌ System error. Please try again.';
        }
    }

    // ── Copy farm code ───────────────────────────────────────────────────────
    function copyCode() {
        const code = document.getElementById('dCode').textContent;
        if (!code || code === '—') return;
        navigator.clipboard.writeText(code).then(() => {
            const btn = document.getElementById('btnCopyCode');
            btn.textContent = '✅ Copied!';
            btn.classList.add('copied');
            setTimeout(() => { btn.textContent = '📋 Copy'; btn.classList.remove('copied'); }, 2000);
        }).catch(() => {
            const el = document.createElement('textarea');
            el.value = code; document.body.appendChild(el); el.select();
            document.execCommand('copy'); document.body.removeChild(el);
        });
    }

    // ── Assign modal ─────────────────────────────────────────────────────────
    function openAssignModal() {
        document.getElementById('assignForm').reset();
        document.getElementById('target_user_id').value = '';
        document.getElementById('ac_dropdown').style.display = 'none';
        document.getElementById('assignModal').classList.add('show');
    }
    function closeAssignModal() { document.getElementById('assignModal').classList.remove('show'); }

    function searchUser(inputEl) {
        clearTimeout(searchTimer);
        const val    = inputEl.value.trim();
        const farmId = document.getElementById('assignFarmSelect').value;
        const dd     = document.getElementById('ac_dropdown');
        document.getElementById('target_user_id').value = '';
        dd.style.display = 'none';
        if (val.length < 2) return;

        searchTimer = setTimeout(async () => {
            try {
                const res  = await fetch(`?action=search_user&term=${encodeURIComponent(val)}&farm_id=${encodeURIComponent(farmId)}`);
                const data = await res.json();
                if (data.length > 0) {
                    dd.innerHTML = data.map(u => `
                        <div class="ac-item" onclick="selectUser(${u.user_id},'${u.full_name.replace(/'/g,"\\'")}','${u.email.replace(/'/g,"\\'")}')">
                            <div class="ac-name">${u.full_name} ${parseInt(u.status) === 0 ? '<span style="font-size:.65rem;background:rgba(244,197,66,.15);color:#f4c542;border:1px solid rgba(244,197,66,.3);padding:1px 7px;border-radius:100px;margin-left:4px;font-weight:700;">PENDING</span>' : ''}</div>
                            <div class="ac-email">${u.email}</div>
                        </div>`).join('');
                } else {
                    dd.innerHTML = '<div class="ac-item"><div class="ac-email">No matching employees found.</div></div>';
                }
                dd.style.display = 'block';
            } catch(e) {}
        }, 300);
    }

    function selectUser(id, name, email) {
        document.getElementById('target_user_id').value = id;
        document.getElementById('search_input').value   = `${name} (${email})`;
        document.getElementById('ac_dropdown').style.display = 'none';
    }

    document.addEventListener('click', e => {
        if (!e.target.closest('.ac-dropdown') && !e.target.closest('#search_input')) {
            document.getElementById('ac_dropdown').style.display = 'none';
        }
    });

    async function submitAssignment(e) {
        e.preventDefault();
        if (!document.getElementById('target_user_id').value) { alert('Please select a user first.'); return; }
        const fd  = new FormData(document.getElementById('assignForm'));
        const btn = document.getElementById('btnAssign');
        btn.disabled = true; btn.textContent = 'Assigning…';
        try {
            const res  = await fetch(window.location.href, { method:'POST', body:fd });
            const data = await res.json();
            alert(data.success ? data.message : 'Error: ' + data.message);
            if (data.success) closeAssignModal();
        } catch(e) { alert('System Error.'); }
        btn.disabled = false; btn.textContent = 'Grant Access';
    }

    // ── Pending employees ────────────────────────────────────────────────────
    async function loadPending(farmId) {
        const list  = document.getElementById('pendingList');
        const badge = document.getElementById('pendingCount');
        list.innerHTML = '<div style="font-size:.82rem;color:var(--muted);text-align:center;padding:1rem 0;">Loading…</div>';
        badge.style.display = 'none';

        try {
            const res  = await fetch(`?action=get_pending&farm_id=${farmId}`);
            const data = await res.json();

            if (!data.length) {
                list.innerHTML = '<div style="font-size:.82rem;color:var(--muted);text-align:center;padding:.75rem 0;">No pending employees.</div>';
                return;
            }

            badge.textContent    = data.length + ' pending';
            badge.style.display  = 'inline-block';

            list.innerHTML = data.map(u => `
                <div class="pending-item" id="pending-${u.user_id}">
                    <div class="pending-name">${u.full_name}</div>
                    <div class="pending-email">${u.email}</div>
                    <div class="pending-actions">
                        <button class="btn-approve" onclick="decideEmployee(${u.user_id}, ${farmId}, 'approve', this)">✅ Approve</button>
                        <button class="btn-reject"  onclick="decideEmployee(${u.user_id}, ${farmId}, 'reject',  this)">❌ Reject</button>
                    </div>
                </div>`).join('');

        } catch(e) {
            list.innerHTML = '<div style="font-size:.82rem;color:var(--red);text-align:center;padding:.75rem 0;">Failed to load. Try again.</div>';
        }
    }

    async function decideEmployee(userId, farmId, decision, btn) {
        btn.disabled = true;
        btn.textContent = decision === 'approve' ? '⏳ Approving…' : '⏳ Rejecting…';

        const fd = new FormData();
        fd.append('action',   'approve_employee');
        fd.append('user_id',  userId);
        fd.append('farm_id',  farmId);
        fd.append('decision', decision);

        try {
            const res  = await fetch(window.location.href, { method:'POST', body:fd });
            const data = await res.json();

            if (data.success) {
                // Remove the row with a fade
                const row = document.getElementById(`pending-${userId}`);
                if (row) {
                    row.style.transition = 'opacity .3s';
                    row.style.opacity    = '0';
                    setTimeout(() => {
                        row.remove();
                        // Update badge count
                        const remaining = document.querySelectorAll('[id^="pending-"]').length;
                        const badge     = document.getElementById('pendingCount');
                        if (remaining === 0) {
                            badge.style.display = 'none';
                            document.getElementById('pendingList').innerHTML =
                                '<div style="font-size:.82rem;color:var(--muted);text-align:center;padding:.75rem 0;">No pending employees.</div>';
                        } else {
                            badge.textContent = remaining + ' pending';
                        }
                    }, 300);
                }
            } else {
                alert('Error: ' + data.message);
                btn.disabled    = false;
                btn.textContent = decision === 'approve' ? '✅ Approve' : '❌ Reject';
            }
        } catch(e) {
            alert('System error. Please try again.');
            btn.disabled    = false;
            btn.textContent = decision === 'approve' ? '✅ Approve' : '❌ Reject';
        }
    }

    function fmtDate(str) {
        if (!str) return '—';
        return new Date(str).toLocaleDateString('en-US', { year:'numeric', month:'long', day:'numeric' });
    }
</script>
</body>
</html>