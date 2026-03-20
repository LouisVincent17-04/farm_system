<?php
// globalxadminportal/verification.php
session_start();
if (!isset($_SESSION['is_global']) || $_SESSION['is_global'] !== 1) { header('Location: login.php'); exit; }

require_once '../config/SadminConnection.php';
date_default_timezone_set('Asia/Manila');

// ========================================================================
// INTERNAL AJAX HANDLERS FOR APPROVE & REJECT
// ========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    @ob_end_clean();
    header('Content-Type: application/json');
    try {
        if ($_POST['action'] === 'approve_user') {
            $uid = (int)$_POST['user_id'];
            
            $stmt = $conn->prepare("UPDATE users SET status = 1 WHERE user_id = ?");
            $stmt->execute([$uid]);
            
            echo json_encode(['success' => true, 'message' => 'User approved successfully!']);
            exit;
        }
        
        if ($_POST['action'] === 'reject_user') {
            $uid = (int)$_POST['user_id'];
            
            // Rejecting the user sets status to -1
            $stmt = $conn->prepare("UPDATE users SET status = -1 WHERE user_id = ?");
            $stmt->execute([$uid]);
            
            echo json_encode(['success' => true, 'message' => 'User registration rejected.']);
            exit;
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}
// ========================================================================

$full_name   = $_SESSION['full_name'] ?? 'Admin';
$is_global = $_SESSION['is_global'] ?? 0;

// Redirect regular clients away from this Super Admin page
if ($is_global == 0) {
    header('Location: my_farms.php');
    exit;
}

// Fetch pending users
$pending_users = $conn->query("
    SELECT user_id, full_name, email, phone_no, created_at 
    FROM users 
    WHERE status = 0 
    ORDER BY created_at ASC
")->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Verification | FarmPro Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg:       #07090f;
            --surface:  #0d1117;
            --card:     #111720;
            --card2:    #141c27;
            --border:   #1c2535;
            --border2:  #243045;
            --text:     #c8d8ec;
            --muted:    #455870;
            --accent:   #3dd68c;
            --accent2:  #07955a;
            --gold:     #f4c542;
            --blue:     #4fa3f7;
            --red:      #f05252;
            --nav-h:    64px;
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html { scroll-behavior: smooth; }
        body { font-family: 'DM Sans', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; overflow-x: hidden; }

        /* ── AMBIENT BG ── */
        body::before {
            content: ''; position: fixed; inset: 0; z-index: 0; pointer-events: none;
            background: radial-gradient(ellipse 70% 50% at 15% 0%, rgba(61,214,140,.055) 0%, transparent 60%),
                        radial-gradient(ellipse 50% 40% at 90% 100%, rgba(79,163,247,.04) 0%, transparent 55%),
                        radial-gradient(ellipse 40% 30% at 75% 20%, rgba(244,197,66,.03) 0%, transparent 50%);
        }
        body::after {
            content: ''; position: fixed; inset: 0; z-index: 0; pointer-events: none;
            background-image: linear-gradient(rgba(61,214,140,.018) 1px, transparent 1px),
                              linear-gradient(90deg, rgba(61,214,140,.018) 1px, transparent 1px);
            background-size: 48px 48px;
        }

        /* ── NAVBAR ── */
        .navbar {
            position: fixed; top: 0; left: 0; right: 0; z-index: 100; height: var(--nav-h);
            display: flex; align-items: center; justify-content: space-between; padding: 0 2rem;
            background: rgba(7,9,15,.85); backdrop-filter: blur(16px) saturate(1.4);
            border-bottom: 1px solid var(--border); box-shadow: 0 1px 0 rgba(61,214,140,.04);
        }
        .nav-brand { display: flex; align-items: center; gap: 10px; text-decoration: none; }
        .nav-logo { font-family: 'Bebas Neue', sans-serif; font-size: 1.55rem; letter-spacing: .06em; color: var(--accent); line-height: 1; }
        .nav-logo span { color: var(--gold); }
        .nav-divider { width: 1px; height: 22px; background: var(--border2); margin: 0 .5rem; }
        .nav-page-label { font-size: .72rem; font-weight: 600; letter-spacing: .16em; text-transform: uppercase; color: var(--muted); }

        .nav-links { display: flex; align-items: center; gap: 4px; }
        .nav-link { display: flex; align-items: center; gap: 7px; padding: .45rem .9rem; border-radius: 8px; font-size: .82rem; font-weight: 500; color: var(--muted); text-decoration: none; transition: color .18s, background .18s; position: relative; }
        .nav-link svg { width: 15px; height: 15px; flex-shrink: 0; }
        .nav-link:hover { color: var(--text); background: rgba(255,255,255,.04); }
        .nav-link.active { color: var(--accent); background: rgba(61,214,140,.08); }
        .nav-link.active::after { content: ''; position: absolute; bottom: -1px; left: 50%; transform: translateX(-50%); width: 20px; height: 2px; background: var(--accent); border-radius: 2px; }

        .nav-sep { width: 1px; height: 20px; background: var(--border2); margin: 0 4px; }
        .nav-logout { display: flex; align-items: center; gap: 7px; padding: .45rem .9rem; border-radius: 8px; font-size: .82rem; font-weight: 600; color: #f87171; text-decoration: none; transition: color .18s, background .18s; }
        .nav-logout:hover { color: #fca5a5; background: rgba(248,113,113,.08); }

        .nav-user { display: flex; align-items: center; gap: 9px; padding: .35rem .75rem .35rem .45rem; border-radius: 100px; background: rgba(255,255,255,.03); border: 1px solid var(--border2); margin-left: .5rem; }
        .nav-avatar { width: 28px; height: 28px; border-radius: 50%; background: linear-gradient(135deg, var(--accent), var(--accent2)); display: flex; align-items: center; justify-content: center; font-size: .75rem; font-weight: 700; color: #051a0e; flex-shrink: 0; }
        .nav-username { font-size: .8rem; font-weight: 600; color: var(--text); max-width: 120px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .nav-badge { font-size: .6rem; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; padding: 2px 7px; border-radius: 100px; background: rgba(244,197,66,.12); color: var(--gold); border: 1px solid rgba(244,197,66,.25); }

        /* ── LAYOUT ── */
        .page-wrap { position: relative; z-index: 1; padding: calc(var(--nav-h) + 2.5rem) 2rem 3rem; max-width: 1280px; margin: 0 auto; }

        .page-header { margin-bottom: 2.5rem; animation: fadeUp .55s cubic-bezier(.22,1,.36,1) both; }
        .page-eyebrow { font-size: .7rem; font-weight: 600; letter-spacing: .2em; text-transform: uppercase; color: var(--accent); margin-bottom: .4rem; }
        .page-title { font-family: 'Bebas Neue', sans-serif; font-size: 2.6rem; letter-spacing: .04em; line-height: 1; color: #fff; }

        /* ── TABLE ── */
        .table-wrap { background: var(--card); border: 1px solid var(--border2); border-radius: 16px; overflow: hidden; animation: fadeUp .65s cubic-bezier(.22,1,.36,1) .1s both; }
        .table-top { display: flex; align-items: center; justify-content: space-between; padding: 1.2rem 1.5rem; border-bottom: 1px solid var(--border); flex-wrap: wrap; gap: .75rem; }
        .table-title { font-size: .85rem; font-weight: 700; color: #fff; display: flex; align-items: center; gap: 8px; }
        .table-title .dot { width: 8px; height: 8px; border-radius: 50%; background: var(--gold); box-shadow: 0 0 6px var(--gold); animation: blink 2s ease-in-out infinite; }

        @keyframes blink { 0%,100%{opacity:1;} 50%{opacity:.3;} }
        @keyframes fadeUp { from { opacity: 0; transform: translateY(18px); } to { opacity: 1; transform: translateY(0); } }

        table { width: 100%; border-collapse: collapse; }
        thead th { padding: .75rem 1.5rem; font-size: .68rem; font-weight: 700; letter-spacing: .14em; text-transform: uppercase; color: var(--muted); text-align: left; border-bottom: 1px solid var(--border); background: rgba(255,255,255,.015); }
        tbody tr { border-bottom: 1px solid var(--border); transition: background .15s; }
        tbody tr:last-child { border-bottom: none; }
        tbody tr:hover { background: rgba(255,255,255,.025); }
        tbody td { padding: .9rem 1.5rem; font-size: .84rem; color: var(--text); }
        
        .user-name { font-weight: 600; color: #fff; }
        .user-email { font-family: 'DM Mono', monospace; font-size: .75rem; color: var(--muted); }
        .ts { font-family: 'DM Mono', monospace; font-size: .75rem; color: var(--muted); }

        .empty-row td { text-align: center; padding: 3rem; color: var(--muted); font-size: .85rem; }
        .empty-icon { font-size: 2rem; display: block; margin-bottom: .5rem; opacity: .4; }

        /* ACTION BUTTONS */
        .actions { display: flex; gap: 8px; }
        .btn-act { 
            background: rgba(255,255,255,0.05); border: 1px solid var(--border2); color: var(--text);
            padding: 6px 12px; border-radius: 6px; cursor: pointer; transition: 0.2s;
            display: flex; align-items: center; justify-content: center; gap: 6px; font-weight: 600; font-size: .75rem;
        }
        .btn-act.approve { color: var(--accent); border-color: rgba(61,214,140,.3); background: rgba(61,214,140,.05); }
        .btn-act.approve:hover { background: rgba(61,214,140,.15); }
        
        .btn-act.reject { color: var(--red); border-color: rgba(240,82,82,.3); background: rgba(240,82,82,.05); }
        .btn-act.reject:hover { background: rgba(240,82,82,.15); }

        @media (max-width: 640px) {
            .page-wrap { padding: calc(var(--nav-h) + 1.5rem) 1rem 2rem; }
            .nav-links .nav-link span { display: none; }
            .nav-username, .nav-badge { display: none; }
            thead { display: none; }
            tbody tr { display: block; padding: .75rem 1rem; }
            tbody td { display: block; padding: .2rem 0; border: none; display: flex; justify-content: space-between; align-items: center;}
            tbody td::before { content: attr(data-label); display: block; font-size: .65rem; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; color: var(--muted); margin-bottom: 2px; }
        }
    </style>
</head>
<body>

<nav class="navbar">
    <a href="farm_page.php" class="nav-brand">
        <div class="nav-logo">Farm<span>Pro</span></div>
        <div class="nav-divider"></div>
        <div class="nav-page-label">Admin Portal</div>
    </a>

    <div class="nav-links">
        <a href="farm_page.php" class="nav-link">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
            <span>Dashboard</span>
        </a>
        <a href="users.php" class="nav-link">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
            <span>Users</span>
        </a>
        <a href="verification.php" class="nav-link active">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span>Verification</span>
        </a>
        <a href="create_client_farm.php" class="nav-link">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M12 4v16m8-8H4"/></svg>
            <span>Create Farm</span>
        </a>
        <a href="profile.php" class="nav-link">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
            <span>Profile</span>
        </a>

        <div class="nav-sep"></div>

        <a href="logout.php" class="nav-logout">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
            <span>Logout</span>
        </a>

        <div class="nav-user">
            <div class="nav-avatar"><?= strtoupper(substr($full_name, 0, 1)) ?></div>
            <span class="nav-username"><?= htmlspecialchars($full_name) ?></span>
            <?php if ($is_global): ?>
            <span class="nav-badge">In-Charge</span>
            <?php endif; ?>
        </div>
    </div>
</nav>

<main class="page-wrap">

    <div class="page-header">
        <div class="page-eyebrow">User Management</div>
        <h1 class="page-title">Pending <span>Verifications</span></h1>
    </div>

    <div class="table-wrap">
        <div class="table-top">
            <div class="table-title">
                <span class="dot"></span>
                Accounts Awaiting Approval
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>User Details</th>
                    <th>Contact No.</th>
                    <th>Registered On</th>
                    <th style="text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($pending_users)): ?>
                <tr class="empty-row">
                    <td colspan="4">
                        <span class="empty-icon">✅</span>
                        All caught up! No pending registrations.
                    </td>
                </tr>
            <?php else: foreach ($pending_users as $u): ?>
                <tr>
                    <td data-label="User Details">
                        <div class="user-name"><?= htmlspecialchars($u['full_name']) ?></div>
                        <div class="user-email"><?= htmlspecialchars($u['email']) ?></div>
                    </td>
                    <td data-label="Contact No.">
                        <span class="ts"><?= htmlspecialchars($u['phone_no'] ?? 'Not provided') ?></span>
                    </td>
                    <td data-label="Registered On">
                        <span class="ts"><?= date('M j, Y h:i A', strtotime($u['created_at'])) ?></span>
                    </td>
                    <td data-label="Actions" style="text-align: right;">
                        <div class="actions" style="justify-content: flex-end;">
                            <button class="btn-act approve" onclick="handleAction(<?= $u['user_id'] ?>, 'approve_user', 'Approve this user account?')" title="Approve">
                                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                Approve
                            </button>
                            <button class="btn-act reject" onclick="handleAction(<?= $u['user_id'] ?>, 'reject_user', 'Reject and archive this registration?')" title="Reject">
                                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                Reject
                            </button>
                        </div>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

</main>

<script>
    function handleAction(adminId, actionType, confirmMessage) {
        if(!confirm(confirmMessage)) return;

        const fd = new FormData();
        fd.append('action', actionType);
        fd.append('user_id', adminId);

        fetch(window.location.href, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if(data.success) {
                    window.location.reload();
                } else {
                    alert("Error: " + data.message);
                }
            }).catch(err => alert("System Error Occurred."));
    }
</script>

</body>
</html>