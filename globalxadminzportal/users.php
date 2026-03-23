<?php
// globalxadminzportal/users.php
session_start();

if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

require_once '../config/SadminConnection.php';
date_default_timezone_set('Asia/Manila');

// ========================================================================
// INTERNAL AJAX HANDLER FOR DISABLE/ENABLE USERS
// ========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    @ob_end_clean();
    header('Content-Type: application/json');
    try {
        if ($_POST['action'] === 'toggle_status') {
            $uid = (int)$_POST['user_id'];
            $new_status = (int)$_POST['new_status'];
            
            // Prevent the admin from disabling themselves
            if ($uid === $_SESSION['user_id'] && $new_status !== 1) {
                echo json_encode(['success' => false, 'message' => 'You cannot disable your own account.']);
                exit;
            }

            $stmt = $conn->prepare("UPDATE users SET status = ? WHERE user_id = ?");
            $stmt->execute([$new_status, $uid]);
            
            $msg = $new_status === 1 ? 'User account has been enabled.' : 'User account has been disabled.';
            echo json_encode(['success' => true, 'message' => $msg]);
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

// Security: Redirect regular clients away from this Super Admin page
if ($is_global == 0) {
    header('Location: my_farms.php');
    exit;
}

// --- SEARCH & SORT LOGIC ---
$search = $_GET['search'] ?? '';
$sort = $_GET['sort'] ?? 'newest';

$sql = "SELECT user_id, full_name, email, phone_no, status, is_global, created_at FROM users";
$params = [];

// --- SEARCH & SORT LOGIC ---
$search = $_GET['search'] ?? '';
$sort = $_GET['sort'] ?? 'newest';

$sql = "SELECT user_id, full_name, email, phone_no, status, is_global, created_at FROM users";
$params = [];

// Apply Search Filter (FIXED: Using unique placeholders)
if (!empty($search)) {
    $sql .= " WHERE full_name LIKE :search1 OR email LIKE :search2";
    $params[':search1'] = "%$search%";
    $params[':search2'] = "%$search%";
}

// Apply Sorting
switch ($sort) {
    case 'oldest': 
        $sql .= " ORDER BY created_at ASC"; break;
    case 'name_asc': 
        $sql .= " ORDER BY full_name ASC"; break;
    case 'name_desc': 
        $sql .= " ORDER BY full_name DESC"; break;
    case 'status': 
        $sql .= " ORDER BY status DESC, created_at DESC"; break;
    case 'newest':
    default: 
        $sql .= " ORDER BY created_at DESC"; break;
}

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$all_users = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users | FarmPro Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg:       #07090f;
            --surface:  #0d1117;
            --card:     #111720;
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
                        radial-gradient(ellipse 50% 40% at 90% 100%, rgba(79,163,247,.04) 0%, transparent 55%);
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

        /* ── SEARCH / FILTER BAR ── */
        .filter-form { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
        .search-input {
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid var(--border2);
            color: var(--text);
            padding: 8px 12px 8px 34px;
            border-radius: 8px;
            font-size: 0.85rem;
            outline: none;
            transition: all 0.2s;
            width: 260px;
        }
        .search-input:focus { border-color: var(--accent); box-shadow: 0 0 0 2px rgba(61,214,140,0.1); }
        .search-icon { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); width: 16px; height: 16px; color: var(--muted); }
        .select-input { padding-left: 12px; width: auto; cursor: pointer; }

        /* ── TABLE ── */
        .table-wrap { background: var(--card); border: 1px solid var(--border2); border-radius: 16px; overflow: hidden; animation: fadeUp .65s cubic-bezier(.22,1,.36,1) .1s both; }
        .table-top { display: flex; align-items: center; justify-content: space-between; padding: 1.2rem 1.5rem; border-bottom: 1px solid var(--border); flex-wrap: wrap; gap: .75rem; }
        .table-title { font-size: .85rem; font-weight: 700; color: #fff; display: flex; align-items: center; gap: 8px; }
        .table-title .dot { width: 8px; height: 8px; border-radius: 50%; background: var(--blue); box-shadow: 0 0 6px var(--blue); }

        @keyframes fadeUp { from { opacity: 0; transform: translateY(18px); } to { opacity: 1; transform: translateY(0); } }

        table { width: 100%; border-collapse: collapse; }
        thead th { padding: .75rem 1.5rem; font-size: .68rem; font-weight: 700; letter-spacing: .14em; text-transform: uppercase; color: var(--muted); text-align: left; border-bottom: 1px solid var(--border); background: rgba(255,255,255,.015); }
        tbody tr { border-bottom: 1px solid var(--border); transition: background .15s; }
        tbody tr:last-child { border-bottom: none; }
        tbody tr:hover { background: rgba(255,255,255,.025); }
        tbody td { padding: .9rem 1.5rem; font-size: .84rem; color: var(--text); }
        
        .user-name { font-weight: 600; color: #fff; display: flex; align-items: center; gap: 8px;}
        .user-email { font-family: 'DM Mono', monospace; font-size: .75rem; color: var(--muted); margin-top: 4px; }
        .ts { font-family: 'DM Mono', monospace; font-size: .75rem; color: var(--muted); }

        /* BADGES */
        .badge { display: inline-flex; align-items: center; gap: 5px; padding: 3px 10px; border-radius: 100px; font-size: .7rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase;}
        
        /* Roles */
        .badge-admin { background: rgba(244,197,66,.12); color: var(--gold); border: 1px solid rgba(244,197,66,.25); font-size: .65rem;}
        .badge-client { background: rgba(79,163,247,.12); color: var(--blue); border: 1px solid rgba(79,163,247,.25); font-size: .65rem;}
        
        /* Status */
        .badge-active   { background: rgba(61,214,140,.1);  color: var(--accent); border: 1px solid rgba(61,214,140,.2);  }
        .badge-pending  { background: rgba(244,197,66,.1);  color: var(--gold);   border: 1px solid rgba(244,197,66,.2);  }
        .badge-disabled { background: rgba(240,82,82,.1);   color: var(--red);    border: 1px solid rgba(240,82,82,.2);  }
        .badge svg { width: 8px; height: 8px; }

        .empty-row td { text-align: center; padding: 3rem; color: var(--muted); font-size: .85rem; }

        /* ACTION BUTTONS */
        .actions { display: flex; gap: 8px; }
        .btn-act { 
            background: rgba(255,255,255,0.05); border: 1px solid var(--border2); color: var(--text);
            padding: 6px 12px; border-radius: 6px; cursor: pointer; transition: 0.2s;
            display: flex; align-items: center; justify-content: center; gap: 6px; font-weight: 600; font-size: .75rem;
        }
        
        .btn-act.disable { color: var(--red); border-color: rgba(240,82,82,.3); background: rgba(240,82,82,.05); }
        .btn-act.disable:hover { background: rgba(240,82,82,.15); }
        
        .btn-act.enable { color: var(--accent); border-color: rgba(61,214,140,.3); background: rgba(61,214,140,.05); }
        .btn-act.enable:hover { background: rgba(61,214,140,.15); }

        .btn-act:disabled { opacity: 0.3; cursor: not-allowed; filter: grayscale(1); }

        @media (max-width: 640px) {
            .page-wrap { padding: calc(var(--nav-h) + 1.5rem) 1rem 2rem; }
            .nav-links .nav-link span { display: none; }
            .nav-username, .nav-badge { display: none; }
            
            .table-top { flex-direction: column; align-items: stretch; }
            .filter-form { flex-direction: column; align-items: stretch; width: 100%; }
            .search-input { width: 100%; }

            thead { display: none; }
            tbody tr { display: block; padding: .75rem 1rem; }
            tbody td { display: block; padding: .2rem 0; border: none; display: flex; justify-content: space-between; align-items: center;}
            tbody td::before { content: attr(data-label); display: block; font-size: .65rem; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; color: var(--muted); margin-bottom: 2px; }
            .user-name { flex-direction: column; align-items: flex-start; gap: 4px; }
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
        <a href="users.php" class="nav-link active">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
            <span>Users</span>
        </a>
        <a href="verification.php" class="nav-link">
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
        <div class="page-eyebrow">Access Control</div>
        <h1 class="page-title">User <span>Directory</span></h1>
    </div>

    <div class="table-wrap">
        <div class="table-top">
            <div class="table-title">
                <span class="dot"></span>
                All Registered Accounts
            </div>
            
            <form method="GET" id="filterForm" class="filter-form">
                <div style="position: relative;">
                    <svg class="search-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search name or email..." class="search-input" oninput="debounceSearch()">
                </div>
                
                <select name="sort" class="search-input select-input" onchange="document.getElementById('filterForm').submit();">
                    <option value="newest" <?= $sort == 'newest' ? 'selected' : '' ?>>Sort: Newest First</option>
                    <option value="oldest" <?= $sort == 'oldest' ? 'selected' : '' ?>>Sort: Oldest First</option>
                    <option value="name_asc" <?= $sort == 'name_asc' ? 'selected' : '' ?>>Sort: Name (A-Z)</option>
                    <option value="name_desc" <?= $sort == 'name_desc' ? 'selected' : '' ?>>Sort: Name (Z-A)</option>
                    <option value="status" <?= $sort == 'status' ? 'selected' : '' ?>>Sort: Active First</option>
                </select>
            </form>
            
        </div>

        <table>
            <thead>
                <tr>
                    <th>User Details</th>
                    <th>Phone No.</th>
                    <th>Status</th>
                    <th>Joined</th>
                    <th style="text-align: right;">Access Control</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($all_users)): ?>
                <tr class="empty-row">
                    <td colspan="5">No users found.</td>
                </tr>
            <?php else: foreach ($all_users as $u): ?>
                <tr>
                    <td data-label="User Details">
                        <div class="user-name">
                            <?= htmlspecialchars($u['full_name']) ?>
                            <?php if ($u['is_global'] == 1): ?>
                                <span class="badge badge-admin">Super Admin</span>
                            <?php else: ?>
                                <span class="badge badge-client">Client</span>
                            <?php endif; ?>
                        </div>
                        <div class="user-email"><?= htmlspecialchars($u['email']) ?></div>
                    </td>
                    <td data-label="Phone No.">
                        <span class="ts"><?= htmlspecialchars($u['phone_no'] ?? 'N/A') ?></span>
                    </td>
                    <td data-label="Status">
                        <?php if ($u['status'] == 1): ?>
                            <span class="badge badge-active"><svg viewBox="0 0 8 8" fill="currentColor"><circle cx="4" cy="4" r="4"/></svg> Active</span>
                        <?php elseif ($u['status'] == 0): ?>
                            <span class="badge badge-pending"><svg viewBox="0 0 8 8" fill="currentColor"><circle cx="4" cy="4" r="4"/></svg> Pending</span>
                        <?php else: ?>
                            <span class="badge badge-disabled"><svg viewBox="0 0 8 8" fill="currentColor"><circle cx="4" cy="4" r="4"/></svg> Disabled</span>
                        <?php endif; ?>
                    </td>
                    <td data-label="Joined">
                        <span class="ts"><?= date('M j, Y', strtotime($u['created_at'])) ?></span>
                    </td>
                    <td data-label="Access Control" style="text-align: right;">
                        <div class="actions" style="justify-content: flex-end;">
                            <?php 
                            // Disable the action button if it's the current logged-in user
                            $is_self = ($u['user_id'] == $_SESSION['user_id']); 
                            ?>

                            <?php if ($u['status'] == 1): // If Active, show Disable button ?>
                                <button class="btn-act disable" <?= $is_self ? 'disabled title="You cannot disable yourself"' : '' ?> onclick="toggleStatus(<?= $u['user_id'] ?>, -1, 'Disable this user? They will immediately lose access to the portal.')">
                                    <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"></path></svg>
                                    Disable
                                </button>

                            <?php elseif ($u['status'] == -1): // If Disabled, show Enable button ?>
                                <button class="btn-act enable" onclick="toggleStatus(<?= $u['user_id'] ?>, 1, 'Enable this user? They will regain access to the portal.')">
                                    <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    Enable
                                </button>
                                
                            <?php else: // If Pending (0), direct them to the verification page ?>
                                <a href="verification.php" class="btn-act" style="text-decoration: none;">
                                    Review in Verification →
                                </a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

</main>

<script>
    // Debounce function to wait until user stops typing before searching
    let searchTimeout;
    function debounceSearch() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            document.getElementById('filterForm').submit();
        }, 500); // Waits 500ms after last keystroke
    }

    function toggleStatus(adminId, newStatus, confirmMessage) {
        if(!confirm(confirmMessage)) return;

        const fd = new FormData();
        fd.append('action', 'toggle_status');
        fd.append('user_id', adminId);
        fd.append('new_status', newStatus);

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