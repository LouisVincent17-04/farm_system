<?php
// globalxadminportal/farm_page.php
session_start();
if (!isset($_SESSION['is_global']) || $_SESSION['is_global'] !== 1) { header('Location: login.php'); exit; }

require_once '../config/SadminConnection.php';

date_default_timezone_set('Asia/Manila');
// ========================================================================
// INTERNAL AJAX HANDLERS FOR EDIT, DELETE & RESTORE
// ========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    @ob_end_clean();
    header('Content-Type: application/json');
    try {
        if ($_POST['action'] === 'update_farm') {
            $fid     = $_POST['farm_id'];
            $fname   = trim($_POST['farm_name']);
            $fstatus = (int)$_POST['farm_status'];
            
            $stmt = $conn->prepare("UPDATE farms SET farm_name = ?, farm_status = ? WHERE farm_id = ?");
            $stmt->execute([$fname, $fstatus, $fid]);
            
            echo json_encode(['success' => true, 'message' => 'Farm updated successfully!']);
            exit;
        }
        
        if ($_POST['action'] === 'delete_farm') {
            $fid = $_POST['farm_id'];
            
            // Soft Delete: Set status to -1
            $stmt = $conn->prepare("UPDATE farms SET farm_status = -1 WHERE farm_id = ?");
            $stmt->execute([$fid]);
            
            echo json_encode(['success' => true, 'message' => 'Farm has been deleted.']);
            exit;
        }

        if ($_POST['action'] === 'restore_farm') {
            $fid = $_POST['farm_id'];
            
            // Restore: Set status to 0 (Inactive) so the admin can safely review it before fully activating
            $stmt = $conn->prepare("UPDATE farms SET farm_status = 0 WHERE farm_id = ?");
            $stmt->execute([$fid]);
            
            echo json_encode(['success' => true, 'message' => 'Farm restored successfully!']);
            exit;
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}
// ========================================================================

// ── Stats (Excluding Deleted Farms: farm_status != -1) ────────────
$total_farms  = $conn->query("SELECT COUNT(*) FROM farms WHERE farm_status != -1")->fetchColumn();
$active_farms = $conn->query("SELECT COUNT(*) FROM farms WHERE farm_status = 1")->fetchColumn();
$total_users  = $conn->query("SELECT COUNT(*) FROM users")->fetchColumn();
$pending_users= $conn->query("SELECT COUNT(*) FROM users WHERE status = 0")->fetchColumn();
$approved_users=$conn->query("SELECT COUNT(*) FROM users WHERE status = 1")->fetchColumn();
$incharge_users=$conn->query("SELECT COUNT(*) FROM users WHERE is_global = 1 AND status = 1")->fetchColumn();

$my_farms = $conn->prepare("SELECT COUNT(*) FROM farms WHERE owner_id = ? AND farm_status != -1");
$my_farms->execute([$_SESSION['user_id']]);
$my_farms_count = $my_farms->fetchColumn();

$full_name   = $_SESSION['full_name'] ?? 'Admin';
$is_global = $_SESSION['is_global'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | FarmPro Admin</title>
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
            --purple:   #a78bfa;
            --orange:   #f97316;
            --nav-h:    64px;
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        html { scroll-behavior: smooth; }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* ── AMBIENT BG ── */
        body::before {
            content: '';
            position: fixed; inset: 0; z-index: 0; pointer-events: none;
            background:
                radial-gradient(ellipse 70% 50% at 15% 0%,   rgba(61,214,140,.055) 0%, transparent 60%),
                radial-gradient(ellipse 50% 40% at 90% 100%, rgba(79,163,247,.04)  0%, transparent 55%),
                radial-gradient(ellipse 40% 30% at 75% 20%,  rgba(244,197,66,.03)  0%, transparent 50%);
        }

        /* grid texture */
        body::after {
            content: '';
            position: fixed; inset: 0; z-index: 0; pointer-events: none;
            background-image:
                linear-gradient(rgba(61,214,140,.018) 1px, transparent 1px),
                linear-gradient(90deg, rgba(61,214,140,.018) 1px, transparent 1px);
            background-size: 48px 48px;
        }

        /* ── NAVBAR ── */
        .navbar {
            position: fixed; top: 0; left: 0; right: 0; z-index: 100;
            height: var(--nav-h);
            display: flex; align-items: center; justify-content: space-between;
            padding: 0 2rem;
            background: rgba(7,9,15,.85);
            backdrop-filter: blur(16px) saturate(1.4);
            border-bottom: 1px solid var(--border);
            box-shadow: 0 1px 0 rgba(61,214,140,.04);
        }

        .nav-brand {
            display: flex; align-items: center; gap: 10px; text-decoration: none;
        }
        .nav-logo {
            font-family: 'Bebas Neue', sans-serif;
            font-size: 1.55rem; letter-spacing: .06em;
            color: var(--accent); line-height: 1;
        }
        .nav-logo span { color: var(--gold); }
        .nav-divider {
            width: 1px; height: 22px;
            background: var(--border2); margin: 0 .5rem;
        }
        .nav-page-label {
            font-size: .72rem; font-weight: 600;
            letter-spacing: .16em; text-transform: uppercase;
            color: var(--muted);
        }

        .nav-links {
            display: flex; align-items: center; gap: 4px;
        }
        .nav-link {
            display: flex; align-items: center; gap: 7px;
            padding: .45rem .9rem;
            border-radius: 8px;
            font-size: .82rem; font-weight: 500;
            color: var(--muted);
            text-decoration: none;
            transition: color .18s, background .18s;
            position: relative;
        }
        .nav-link svg { width: 15px; height: 15px; flex-shrink: 0; }
        .nav-link:hover { color: var(--text); background: rgba(255,255,255,.04); }
        .nav-link.active { color: var(--accent); background: rgba(61,214,140,.08); }
        .nav-link.active::after {
            content: '';
            position: absolute; bottom: -1px; left: 50%; transform: translateX(-50%);
            width: 20px; height: 2px;
            background: var(--accent); border-radius: 2px;
        }

        .nav-sep { width: 1px; height: 20px; background: var(--border2); margin: 0 4px; }

        .nav-logout {
            display: flex; align-items: center; gap: 7px;
            padding: .45rem .9rem;
            border-radius: 8px;
            font-size: .82rem; font-weight: 600;
            color: #f87171;
            text-decoration: none;
            transition: color .18s, background .18s;
        }
        .nav-logout svg { width: 15px; height: 15px; }
        .nav-logout:hover { color: #fca5a5; background: rgba(248,113,113,.08); }

        .nav-user {
            display: flex; align-items: center; gap: 9px;
            padding: .35rem .75rem .35rem .45rem;
            border-radius: 100px;
            background: rgba(255,255,255,.03);
            border: 1px solid var(--border2);
            margin-left: .5rem;
        }
        .nav-avatar {
            width: 28px; height: 28px; border-radius: 50%;
            background: linear-gradient(135deg, var(--accent), var(--accent2));
            display: flex; align-items: center; justify-content: center;
            font-size: .75rem; font-weight: 700; color: #051a0e;
            flex-shrink: 0;
        }
        .nav-username {
            font-size: .8rem; font-weight: 600; color: var(--text);
            max-width: 120px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
        }
        <?php if ($is_global): ?>
        .nav-badge {
            font-size: .6rem; font-weight: 700; letter-spacing: .1em;
            text-transform: uppercase; padding: 2px 7px; border-radius: 100px;
            background: rgba(244,197,66,.12); color: var(--gold);
            border: 1px solid rgba(244,197,66,.25);
        }
        <?php endif; ?>

        /* ── LAYOUT ── */
        .page-wrap {
            position: relative; z-index: 1;
            padding: calc(var(--nav-h) + 2.5rem) 2rem 3rem;
            max-width: 1280px; margin: 0 auto;
        }

        /* ── PAGE HEADER ── */
        .page-header {
            margin-bottom: 2.5rem;
            animation: fadeUp .55s cubic-bezier(.22,1,.36,1) both;
        }
        .page-header-top {
            display: flex; align-items: flex-end; justify-content: space-between;
            flex-wrap: wrap; gap: 1rem;
        }
        .page-eyebrow {
            font-size: .7rem; font-weight: 600; letter-spacing: .2em;
            text-transform: uppercase; color: var(--accent);
            margin-bottom: .4rem;
        }
        .page-title {
            font-family: 'Bebas Neue', sans-serif;
            font-size: 2.6rem; letter-spacing: .04em; line-height: 1;
            color: #fff;
        }
        .page-title span { color: var(--accent); }
        .page-date {
            font-size: .78rem; color: var(--muted);
            font-family: 'DM Mono', monospace;
        }

        /* ── STAT CARDS ── */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(210px, 1fr));
            gap: 16px;
            margin-bottom: 2.5rem;
        }

        .stat-card {
            background: var(--card);
            border: 1px solid var(--border2);
            border-radius: 16px;
            padding: 1.4rem 1.5rem;
            position: relative; overflow: hidden;
            transition: transform .2s, box-shadow .2s, border-color .2s;
            animation: fadeUp .55s cubic-bezier(.22,1,.36,1) both;
        }
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 32px rgba(0,0,0,.35);
            border-color: var(--border2);
        }
        .stat-card::before {
            content: '';
            position: absolute; top: 0; left: 0; right: 0; height: 2px;
            border-radius: 16px 16px 0 0;
        }
        .stat-card.green::before  { background: linear-gradient(90deg, var(--accent), transparent); }
        .stat-card.blue::before   { background: linear-gradient(90deg, var(--blue), transparent); }
        .stat-card.gold::before   { background: linear-gradient(90deg, var(--gold), transparent); }
        .stat-card.red::before    { background: linear-gradient(90deg, var(--red), transparent); }
        .stat-card.purple::before { background: linear-gradient(90deg, var(--purple), transparent); }
        .stat-card.teal::before   { background: linear-gradient(90deg, #2dd4bf, transparent); }
        .stat-card.orange::before { background: linear-gradient(90deg, var(--orange), transparent); }

        .stat-card::after {
            content: '';
            position: absolute; bottom: -30px; right: -20px;
            width: 100px; height: 100px; border-radius: 50%;
            opacity: .04; pointer-events: none;
        }
        .stat-card.green::after  { background: var(--accent); }
        .stat-card.blue::after   { background: var(--blue); }
        .stat-card.gold::after   { background: var(--gold); }
        .stat-card.red::after    { background: var(--red); }
        .stat-card.purple::after { background: var(--purple); }
        .stat-card.teal::after   { background: #2dd4bf; }
        .stat-card.orange::after { background: var(--orange); }

        .stat-icon {
            width: 38px; height: 38px; border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            margin-bottom: 1rem;
            font-size: 1.1rem;
        }
        .green  .stat-icon { background: rgba(61,214,140,.1);  }
        .blue   .stat-icon { background: rgba(79,163,247,.1);  }
        .gold   .stat-icon { background: rgba(244,197,66,.1);  }
        .red    .stat-icon { background: rgba(240,82,82,.1);   }
        .purple .stat-icon { background: rgba(167,139,250,.1); }
        .teal   .stat-icon { background: rgba(45,212,191,.1);  }
        .orange .stat-icon { background: rgba(249,115,22,.1);  }

        .stat-label {
            font-size: .7rem; font-weight: 600; letter-spacing: .14em;
            text-transform: uppercase; color: var(--muted);
            margin-bottom: .35rem;
        }
        .stat-value {
            font-family: 'Bebas Neue', sans-serif;
            font-size: 2.4rem; letter-spacing: .03em; line-height: 1;
            color: #fff;
            transition: color .2s;
        }
        .green  .stat-value { color: var(--accent); }
        .blue   .stat-value { color: var(--blue);   }
        .gold   .stat-value { color: var(--gold);   }
        .red    .stat-value { color: var(--red);     }
        .purple .stat-value { color: var(--purple);  }
        .teal   .stat-value { color: #2dd4bf;        }
        .orange .stat-value { color: var(--orange);  }

        .stat-sub {
            font-size: .72rem; color: var(--muted);
            margin-top: .3rem;
        }

        /* ── SECTION TITLE ── */
        .section-head {
            display: flex; align-items: center; gap: 10px;
            margin-bottom: 1.2rem;
        }
        .section-title {
            font-family: 'Bebas Neue', sans-serif;
            font-size: 1.25rem; letter-spacing: .06em; color: #fff;
        }
        .section-line {
            flex: 1; height: 1px; background: var(--border);
        }

        /* ── FARM TABLE ── */
        .table-wrap {
            background: var(--card);
            border: 1px solid var(--border2);
            border-radius: 16px;
            overflow: hidden;
            animation: fadeUp .65s cubic-bezier(.22,1,.36,1) .1s both;
        }
        .table-top {
            display: flex; align-items: center; justify-content: space-between;
            padding: 1.2rem 1.5rem;
            border-bottom: 1px solid var(--border);
            flex-wrap: wrap; gap: .75rem;
        }
        .table-title {
            font-size: .85rem; font-weight: 700; color: #fff;
            display: flex; align-items: center; gap: 8px;
        }
        .table-title .dot {
            width: 8px; height: 8px; border-radius: 50%;
            background: var(--accent);
            box-shadow: 0 0 6px var(--accent);
            animation: blink 2s ease-in-out infinite;
        }
        @keyframes blink { 0%,100%{opacity:1;} 50%{opacity:.3;} }

        .btn-new {
            display: inline-flex; align-items: center; gap: 7px;
            padding: .5rem 1.1rem;
            background: linear-gradient(135deg, var(--accent), var(--accent2));
            border: none; border-radius: 8px;
            color: #051a0e; font-family: 'DM Sans', sans-serif;
            font-weight: 700; font-size: .8rem;
            cursor: pointer; text-decoration: none;
            transition: transform .18s, box-shadow .18s;
        }
        .btn-new:hover { transform: translateY(-2px); box-shadow: 0 6px 18px rgba(61,214,140,.28); }
        .btn-new svg { width: 14px; height: 14px; }

        /* ARCHIVE BUTTON */
        .btn-archive {
            display: inline-flex; align-items: center; gap: 7px;
            padding: .5rem 1.1rem;
            background: rgba(255,255,255,.05); border: 1px solid var(--border2);
            color: var(--text); border-radius: 8px; font-weight: 600; font-size: .8rem;
            cursor: pointer; transition: all .2s; text-decoration: none;
        }
        .btn-archive:hover { background: rgba(255,255,255,.1); border-color: var(--muted); }

        table { width: 100%; border-collapse: collapse; }
        thead th {
            padding: .75rem 1.5rem;
            font-size: .68rem; font-weight: 700; letter-spacing: .14em;
            text-transform: uppercase; color: var(--muted);
            text-align: left; border-bottom: 1px solid var(--border);
            background: rgba(255,255,255,.015);
        }
        tbody tr {
            border-bottom: 1px solid var(--border);
            transition: background .15s;
        }
        tbody tr:last-child { border-bottom: none; }
        tbody tr:hover { background: rgba(255,255,255,.025); }
        tbody td {
            padding: .9rem 1.5rem;
            font-size: .84rem; color: var(--text);
        }
        .farm-name { font-weight: 600; color: #fff; }
        .farm-id   { font-family: 'DM Mono', monospace; font-size: .75rem; color: var(--muted); }

        .badge {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 3px 10px; border-radius: 100px;
            font-size: .7rem; font-weight: 700; letter-spacing: .06em;
        }
        .badge-active   { background: rgba(61,214,140,.1);  color: var(--accent); border: 1px solid rgba(61,214,140,.2);  }
        .badge-inactive { background: rgba(69,88,112,.12);   color: var(--muted);  border: 1px solid rgba(69,88,112,.2);  }
        .badge-deleted  { background: rgba(240,82,82,.1);    color: var(--red);    border: 1px solid rgba(240,82,82,.2);  }
        .badge svg { width: 8px; height: 8px; }

        .ts { font-family: 'DM Mono', monospace; font-size: .75rem; color: var(--muted); }

        .empty-row td {
            text-align: center; padding: 3rem;
            color: var(--muted); font-size: .85rem;
        }
        .empty-icon { font-size: 2rem; display: block; margin-bottom: .5rem; opacity: .4; }

        /* ACTION BUTTONS */
        .actions { display: flex; gap: 8px; }
        .btn-act { 
            background: rgba(255,255,255,0.05); border: 1px solid var(--border2); color: var(--muted);
            padding: 5px; border-radius: 6px; cursor: pointer; transition: 0.2s;
            display: flex; align-items: center; justify-content: center;
        }
        .btn-act:hover { background: rgba(255,255,255,0.1); }
        .btn-act.edit:hover { color: var(--blue); border-color: var(--blue); background: rgba(79,163,247,.1); }
        .btn-act.delete:hover { color: var(--red); border-color: var(--red); background: rgba(240,82,82,.1); }
        .btn-act.restore { padding: 5px 12px; font-weight: bold; font-size: 0.75rem; color: var(--accent); border-color: rgba(61,214,140,.3); background: rgba(61,214,140,.05);}
        .btn-act.restore:hover { background: rgba(61,214,140,.15); border-color: var(--accent); }

        /* MODAL */
        .modal { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.7); backdrop-filter:blur(4px); z-index:1000; align-items:center; justify-content:center; padding:1rem; }
        .modal.show { display:flex; }
        .modal-content { background:var(--card); border-radius:16px; width:100%; max-width:400px; border:1px solid var(--border); box-shadow:0 25px 50px -12px rgba(0,0,0,0.5); overflow:hidden; display: flex; flex-direction: column; max-height: 90vh;}
        .modal-content.large { max-width: 700px; }
        .modal-header { padding:1.5rem; border-bottom:1px solid var(--border); display: flex; justify-content: space-between; align-items: center; flex-shrink: 0;}
        .modal-header h2 { margin:0; font-size:1.2rem; color:#fff; }
        .modal-close { background: none; border: none; color: var(--muted); cursor: pointer; font-size: 1.2rem; }
        .modal-close:hover { color: white; }
        .modal-body   { padding:1.5rem; overflow-y: auto; flex-grow: 1; }
        .modal-footer { padding:1.5rem; border-top:1px solid var(--border); display:flex; justify-content:flex-end; gap:10px; background: rgba(0,0,0,0.2); flex-shrink: 0;}
        
        .form-group { margin-bottom: 1.2rem; }
        .form-group label { display: block; font-size: .85rem; color: var(--muted); margin-bottom: .5rem; font-weight: 600;}
        .form-control { width: 100%; padding: 10px 12px; background: var(--bg); border: 1px solid var(--border); border-radius: 8px; color: #fff; font-size: .95rem; outline: none; transition: 0.2s;}
        .form-control:focus { border-color: var(--accent); box-shadow: 0 0 0 2px rgba(61,214,140,.1);}
        
        .btn-cancel { padding: 10px 20px; background: transparent; border: 1px solid var(--border); color: #cbd5e1; border-radius: 8px; cursor: pointer; font-weight: 600; transition: 0.2s;}
        .btn-cancel:hover { background: rgba(255,255,255,0.05); color: white; }
        .btn-save { padding: 10px 20px; background: linear-gradient(135deg, var(--accent), var(--accent2)); border: none; color: #0f172a; border-radius: 8px; cursor: pointer; font-weight: bold; transition: 0.2s;}
        .btn-save:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(61,214,140,.2); }

        /* ── ANIMATIONS ── */
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(18px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .stat-card:nth-child(1) { animation-delay: .05s; }
        .stat-card:nth-child(2) { animation-delay: .10s; }
        .stat-card:nth-child(3) { animation-delay: .15s; }
        .stat-card:nth-child(4) { animation-delay: .20s; }
        .stat-card:nth-child(5) { animation-delay: .25s; }
        .stat-card:nth-child(6) { animation-delay: .30s; }
        .stat-card:nth-child(7) { animation-delay: .35s; }

        /* ── RESPONSIVE ── */
        @media (max-width: 640px) {
            .page-wrap { padding: calc(var(--nav-h) + 1.5rem) 1rem 2rem; }
            .nav-links .nav-link span { display: none; }
            .nav-username { display: none; }
            .page-title { font-size: 2rem; }
            thead { display: none; }
            tbody tr { display: block; padding: .75rem 1rem; }
            tbody td { display: block; padding: .2rem 0; border: none; display: flex; justify-content: space-between; align-items: center;}
            tbody td::before {
                content: attr(data-label);
                display: block; font-size: .65rem; font-weight: 700;
                letter-spacing: .12em; text-transform: uppercase;
                color: var(--muted); margin-bottom: 2px;
            }
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
        <a href="farm_page.php" class="nav-link active">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
            <span>Dashboard</span>
        </a>
        <a href="users.php" class="nav-link">
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
        <div class="page-header-top">
            <div>
                <div class="page-eyebrow">Overview</div>
                <h1 class="page-title">Farm <span>Dashboard</span></h1>
            </div>
            <div class="page-date"><?= date('l, F j Y · H:i') ?></div>
        </div>
    </div>

    <div class="stats-grid">

        <div class="stat-card green">
            <div class="stat-icon">🌾</div>
            <div class="stat-label">Total Farms</div>
            <div class="stat-value"><?= $total_farms ?></div>
            <div class="stat-sub">All registered farms</div>
        </div>

        <div class="stat-card teal">
            <div class="stat-icon">✅</div>
            <div class="stat-label">Active Farms</div>
            <div class="stat-value"><?= $active_farms ?></div>
            <div class="stat-sub">Currently operational</div>
        </div>

        <div class="stat-card blue">
            <div class="stat-icon">👤</div>
            <div class="stat-label">Total Admins</div>
            <div class="stat-value"><?= $total_users ?></div>
            <div class="stat-sub">All registered admins</div>
        </div>

        <div class="stat-card gold">
            <div class="stat-icon">⏳</div>
            <div class="stat-label">Pending Approval</div>
            <div class="stat-value"><?= $pending_users ?></div>
            <div class="stat-sub">Awaiting review</div>
        </div>

        <div class="stat-card purple">
            <div class="stat-icon">🛡️</div>
            <div class="stat-label">Approved Admins</div>
            <div class="stat-value"><?= $approved_users ?></div>
            <div class="stat-sub">Active accounts</div>
        </div>

        <div class="stat-card orange">
            <div class="stat-icon">👑</div>
            <div class="stat-label">In-Charge</div>
            <div class="stat-value"><?= $incharge_users ?></div>
            <div class="stat-sub">Assigned as In-Charge</div>
        </div>

        <div class="stat-card red">
            <div class="stat-icon">📌</div>
            <div class="stat-label">My Farms</div>
            <div class="stat-value"><?= $my_farms_count ?></div>
            <div class="stat-sub">Assigned to you</div>
        </div>

    </div>

    <div class="section-head">
        <div class="section-title">Recent Farms</div>
        <div class="section-line"></div>
    </div>

    <div class="table-wrap">
        <div class="table-top">
            <div class="table-title">
                <span class="dot"></span>
                Farm Registry
            </div>
            
            <div style="display: flex; gap: 10px;">
                <button type="button" class="btn-archive" onclick="openArchiveModal()">
                    <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"></path></svg>
                    View Archive
                </button>
                <a href="create_client_farm.php" class="btn-new">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    New Farm
                </a>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Farm</th>
                    <th>Admin</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th style="text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php
            // Exclude deleted farms (-1)
            $farms = $conn->query("
                SELECT f.farm_id, f.farm_name, f.farm_status, f.created_at,
                       a.full_name AS admin_name
                FROM farms f
                JOIN users a ON a.user_id = f.owner_id
                WHERE f.farm_status != -1
                ORDER BY f.created_at DESC
                LIMIT 50
            ")->fetchAll();

            if (empty($farms)):
            ?>
                <tr class="empty-row">
                    <td colspan="5">
                        <span class="empty-icon">🌾</span>
                        No farms registered yet. <a href="create_client_farm.php" style="color:var(--accent);font-weight:700;">Create one →</a>
                    </td>
                </tr>
            <?php else: foreach ($farms as $f): ?>
                <tr>
                    <td data-label="Farm">
                        <div class="farm-name"><?= htmlspecialchars($f['farm_name']) ?></div>
                        <div class="farm-id">#<?= $f['farm_id'] ?></div>
                    </td>
                    <td data-label="Admin"><?= htmlspecialchars($f['admin_name']) ?></td>
                    <td data-label="Status">
                        <?php if ($f['farm_status'] == 1): ?>
                            <span class="badge badge-active">
                                <svg viewBox="0 0 8 8" fill="currentColor"><circle cx="4" cy="4" r="4"/></svg>
                                Active
                            </span>
                        <?php else: ?>
                            <span class="badge badge-inactive">
                                <svg viewBox="0 0 8 8" fill="currentColor"><circle cx="4" cy="4" r="4"/></svg>
                                Inactive
                            </span>
                        <?php endif; ?>
                    </td>
                    <td data-label="Created">
                        <span class="ts"><?= date('M j, Y', strtotime($f['created_at'])) ?></span>
                    </td>
                    <td data-label="Actions" style="text-align: right;">
                        <div class="actions" style="justify-content: flex-end;">
                            <button class="btn-act edit" onclick="openEditModal(<?= $f['farm_id'] ?>, '<?= addslashes(htmlspecialchars($f['farm_name'])) ?>', <?= $f['farm_status'] ?>)" title="Edit">
                                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                            </button>
                            <button class="btn-act delete" onclick="deleteFarm(<?= $f['farm_id'] ?>)" title="Delete">
                                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                            </button>
                        </div>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

</main>

<div id="editModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Edit Farm Details</h2>
            <button class="modal-close" onclick="closeEditModal()">✕</button>
        </div>
        <form id="editForm" onsubmit="submitEditFarm(event)">
            <div class="modal-body">
                <input type="hidden" id="edit_farm_id" name="farm_id">
                <input type="hidden" name="action" value="update_farm">
                
                <div class="form-group">
                    <label>Farm Name</label>
                    <input type="text" id="edit_farm_name" name="farm_name" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>Operating Status</label>
                    <select id="edit_farm_status" name="farm_status" class="form-control" required>
                        <option value="1">Active (Normal Operations)</option>
                        <option value="0">Inactive (Suspended)</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeEditModal()">Cancel</button>
                <button type="submit" class="btn-save" id="btnUpdate">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<div id="archiveModal" class="modal">
    <div class="modal-content large">
        <div class="modal-header">
            <h2>Archived Farms</h2>
            <button class="modal-close" onclick="closeArchiveModal()">✕</button>
        </div>
        <div class="modal-body" style="padding: 0;">
            <table>
                <thead>
                    <tr>
                        <th>Farm</th>
                        <th>Admin</th>
                        <th>Status</th>
                        <th style="text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                // Fetch ONLY deleted farms (-1)
                $archived_farms = $conn->query("
                    SELECT f.farm_id, f.farm_name, f.created_at,
                           a.full_name AS admin_name
                    FROM farms f
                    JOIN users a ON a.user_id = f.owner_id
                    WHERE f.farm_status = -1
                    ORDER BY f.created_at DESC
                ")->fetchAll();

                if (empty($archived_farms)):
                ?>
                    <tr>
                        <td colspan="4" style="text-align:center; padding: 2rem; color: var(--muted);">No deleted farms found.</td>
                    </tr>
                <?php else: foreach ($archived_farms as $af): ?>
                    <tr>
                        <td data-label="Farm">
                            <div class="farm-name"><?= htmlspecialchars($af['farm_name']) ?></div>
                            <div class="farm-id">#<?= $af['farm_id'] ?></div>
                        </td>
                        <td data-label="Admin"><?= htmlspecialchars($af['admin_name']) ?></td>
                        <td data-label="Status">
                            <span class="badge badge-deleted">Deleted</span>
                        </td>
                        <td data-label="Action" style="text-align: right;">
                            <button class="btn-act restore" onclick="restoreFarm(<?= $af['farm_id'] ?>)">Restore</button>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    // --- EDIT FARM ---
    function openEditModal(id, name, status) {
        document.getElementById('edit_farm_id').value = id;
        document.getElementById('edit_farm_name').value = name;
        document.getElementById('edit_farm_status').value = status;
        document.getElementById('editModal').classList.add('show');
    }

    function closeEditModal() {
        document.getElementById('editModal').classList.remove('show');
    }

    function submitEditFarm(e) {
        e.preventDefault();
        const form = document.getElementById('editForm');
        const fd = new FormData(form);
        const btn = document.getElementById('btnUpdate');
        
        btn.disabled = true;
        btn.textContent = 'Saving...';

        fetch(window.location.href, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if(data.success) {
                    window.location.reload();
                } else {
                    alert("Error: " + data.message);
                    btn.disabled = false;
                    btn.textContent = 'Save Changes';
                }
            }).catch(err => {
                alert("System Error Occurred.");
                btn.disabled = false;
                btn.textContent = 'Save Changes';
            });
    }

    // --- DELETE FARM ---
    function deleteFarm(id) {
        if(!confirm("⚠️ Are you sure you want to delete this farm?\n\nThis will archive the farm and hide it from all views. It cannot be easily undone.")) {
            return;
        }

        const fd = new FormData();
        fd.append('action', 'delete_farm');
        fd.append('farm_id', id);

        fetch(window.location.href, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if(data.success) window.location.reload();
                else alert("Error: " + data.message);
            }).catch(err => alert("System Error Occurred."));
    }

    // --- ARCHIVE / RESTORE FARMS ---
    function openArchiveModal() {
        document.getElementById('archiveModal').classList.add('show');
    }

    function closeArchiveModal() {
        document.getElementById('archiveModal').classList.remove('show');
    }

    function restoreFarm(id) {
        if(!confirm("Restore this farm? It will be returned to the main list as 'Inactive' so you can review it before fully enabling it.")) {
            return;
        }

        const fd = new FormData();
        fd.append('action', 'restore_farm');
        fd.append('farm_id', id);

        fetch(window.location.href, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if(data.success) window.location.reload();
                else alert("Error: " + data.message);
            }).catch(err => alert("System Error Occurred."));
    }

    // Close modals on outside click
    document.getElementById('editModal').addEventListener('click', function(e) {
        if(e.target === this) closeEditModal();
    });
    document.getElementById('archiveModal').addEventListener('click', function(e) {
        if(e.target === this) closeArchiveModal();
    });
</script>

</body>
</html>