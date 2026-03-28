<?php
// reports/audit_log_report.php
error_reporting(0);
ini_set('display_errors', 0);
$page = "reports";
include '../config/Connection.php';

include '../security/checkAccess.php';
checkAccess('audit_log_report');
include '../common/navbar.php';
include '../common/chat_support.php';

// --- 1. CONFIGURATION & INPUTS ---
$limit = 50; // Records per page
$page_num = isset($_GET['page_num']) ? (int)$_GET['page_num'] : 1;
if ($page_num < 1) $page_num = 1;
$offset = ($page_num - 1) * $limit;

$user_filter   = $_GET['user'] ?? '';
$action_filter = $_GET['action'] ?? '';
$date_from     = $_GET['date_from'] ?? '';
$date_to       = $_GET['date_to'] ?? '';
$search_term   = trim($_GET['search'] ?? '');

try {
    if (!isset($conn)) { throw new Exception("Database connection failed."); }

    // --- 2. BUILD QUERY CONDITIONS ---
    $where_sql = "WHERE 1=1";
    $params = [];

    if ($user_filter) {
        $where_sql .= " AND USERNAME = :username";
        $params[':username'] = $user_filter;
    }
    if ($action_filter) {
        $where_sql .= " AND ACTION_TYPE = :action";
        $params[':action'] = $action_filter;
    }
    if ($date_from && $date_to) {
        $where_sql .= " AND LOG_DATE BETWEEN :date_from AND :date_to";
        $params[':date_from'] = $date_from . ' 00:00:00';
        $params[':date_to']   = $date_to . ' 23:59:59';
    }
    
    // Filter: Search (CASE INSENSITIVE)
    if ($search_term) {
        $search_pattern = "%" . strtolower($search_term) . "%";
        $where_sql .= " AND (LOWER(ACTION_DETAILS) LIKE :search1 OR LOWER(TABLE_NAME) LIKE :search2 OR LOWER(IP_ADDRESS) LIKE :search3)";
        $params[':search1'] = $search_pattern;
        $params[':search2'] = $search_pattern;
        $params[':search3'] = $search_pattern;
    }

    // --- 3. GET TOTAL COUNT (For Pagination) ---
    $count_sql = "SELECT COUNT(*) FROM audit_logs $where_sql";
    $count_stmt = $conn->prepare($count_sql);
    $count_stmt->execute($params);
    $total_records = $count_stmt->fetchColumn();
    $total_pages = ceil($total_records / $limit);

    // --- 4. FETCH DATA (With Limit & Offset) ---
    $sql = "SELECT 
            LOG_ID, USER_ID, USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS,
            DATE_FORMAT(LOG_DATE, '%m/%d/%Y %h:%i %p') as LOG_DATE_FMT
        FROM audit_logs
        $where_sql
        ORDER BY LOG_DATE DESC
        LIMIT :limit OFFSET :offset";

    // Bind Limit/Offset as integers (PDO strictness)
    $stmt = $conn->prepare($sql);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // --- 5. DROPDOWNS (Cached or Distinct) ---
    $users_list = $conn->query("SELECT DISTINCT USERNAME FROM audit_logs WHERE USERNAME IS NOT NULL ORDER BY USERNAME")->fetchAll(PDO::FETCH_COLUMN);
    $actions_list = $conn->query("SELECT DISTINCT ACTION_TYPE FROM audit_logs ORDER BY ACTION_TYPE")->fetchAll(PDO::FETCH_COLUMN);

} catch (Exception $e) {
    $logs = [];
    error_log($e->getMessage());
}

// Helper to keep filters in pagination links
function getQueryUrl($newPage, $currentParams) {
    $currentParams['page_num'] = $newPage;
    return http_build_query($currentParams);
}

// Count active filters for UI badge
$active_filters = 0;
if ($date_from || $date_to) $active_filters++;
if ($user_filter !== '') $active_filters++;
if ($action_filter !== '') $active_filters++;
if ($search_term !== '') $active_filters++;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>System Audit Logs | FarmPro</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" type="text/css" href="https://npmcdn.com/flatpickr/dist/themes/dark.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.31/jspdf.plugin.autotable.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />

    <style>
        /* ─── CSS VARIABLES ─── */
        :root {
            --bg-base:        #080f1a;
            --bg-surface:     #0d1829;
            --bg-elevated:    #111f35;
            --bg-hover:       #162540;
            --border:         rgba(255,255,255,0.07);
            --border-active:  rgba(148,163,184,0.5); /* Slate Accent */
            --slate:          #94a3b8;
            --slate-dim:      rgba(148,163,184,0.12);
            --slate-glow:     rgba(148,163,184,0.25);
            --blue:           #3b82f6;
            --blue-dim:       rgba(59,130,246,0.15);
            --green:          #22c55e;
            --green-dim:      rgba(34,197,94,0.15);
            --red:            #f87171;
            --red-dim:        rgba(248,113,113,0.15);
            --purple:         #a855f7;
            --purple-dim:     rgba(168,85,247,0.15);
            --text-primary:   #f1f5f9;
            --text-secondary: #94a3b8;
            --text-muted:     #475569;
            --radius-sm:      6px;
            --radius-md:      10px;
            --radius-lg:      14px;
            --radius-xl:      20px;
            --shadow-sm:      0 1px 3px rgba(0,0,0,0.4);
            --font:           'DM Sans', system-ui, sans-serif;
            --font-mono:      'DM Mono', monospace;
            --transition:     0.18s cubic-bezier(0.4,0,0.2,1);
        }

        /* ─── RESET & BASE ─── */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: var(--font);
            background: var(--bg-base);
            color: var(--text-primary);
            min-height: 100vh;
            padding-bottom: 60px;
            background-image: radial-gradient(ellipse 80% 50% at 50% -20%, rgba(148,163,184,0.06) 0%, transparent 60%);
        }

        .container {
            max-width: 1560px;
            margin: 0 auto;
            padding: 2rem 1.5rem;
        }

        /* ─── TOP BAR ─── */
        .top-bar {
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 2rem; gap: 1rem; flex-wrap: wrap;
        }
        .back-link {
            display: inline-flex; align-items: center; gap: 8px; text-decoration: none;
            color: var(--text-secondary); font-size: 0.875rem; font-weight: 500;
            padding: 8px 14px; background: var(--bg-elevated); border: 1px solid var(--border);
            border-radius: var(--radius-md); transition: all var(--transition);
        }
        .back-link:hover { color: var(--text-primary); border-color: var(--border-active); background: var(--bg-hover); }

        .page-badge {
            display: inline-flex; align-items: center; gap: 6px; font-size: 0.75rem;
            font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase;
            color: var(--text-secondary); background: var(--slate-dim); border: 1px solid rgba(148,163,184,0.2);
            padding: 6px 12px; border-radius: 99px;
        }

        /* ─── PAGE HEADER ─── */
        .page-header { margin-bottom: 2rem; }
        .page-title {
            font-size: clamp(1.6rem, 3vw, 2.2rem); font-weight: 700;
            color: var(--text-primary); letter-spacing: -0.03em; line-height: 1.1; margin-bottom: 0.25rem;
        }
        .page-title span {
            background: linear-gradient(135deg, var(--text-primary), var(--text-secondary));
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }
        .page-subtitle { color: var(--text-secondary); font-size: 0.95rem; }

        /* ─── STAT CARDS ─── */
        .stats-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem; margin-bottom: 1.5rem;
        }

        .stat-card {
            background: var(--bg-surface); border: 1px solid var(--border);
            border-radius: var(--radius-lg); padding: 1.25rem 1.5rem;
            position: relative; overflow: hidden; transition: border-color var(--transition), transform var(--transition);
        }
        .stat-card::before {
            content: ''; position: absolute; inset: 0; opacity: 0; transition: opacity var(--transition);
        }
        .stat-card:hover { transform: translateY(-1px); }
        .stat-card:hover::before { opacity: 1; }

        .stat-card.slate::before { background: linear-gradient(135deg, rgba(148,163,184,0.04), transparent); }
        .stat-card.blue::before  { background: linear-gradient(135deg, rgba(59,130,246,0.04), transparent); }

        .stat-card.slate { border-color: rgba(148,163,184,0.15); }
        .stat-card.blue  { border-color: rgba(59,130,246,0.15); }

        .stat-icon {
            width: 32px; height: 32px; border-radius: var(--radius-sm);
            display: flex; align-items: center; justify-content: center;
            font-size: 0.85rem; margin-bottom: 0.75rem;
        }
        .stat-icon.slate { background: var(--slate-dim); color: var(--slate); }
        .stat-icon.blue  { background: var(--blue-dim); color: var(--blue); }

        .stat-val {
            font-size: 1.6rem; font-weight: 700; letter-spacing: -0.03em;
            line-height: 1; margin-bottom: 4px; font-family: var(--font-mono);
        }
        .stat-val.slate { color: var(--text-primary); }
        .stat-val.blue  { color: var(--blue); }
        .stat-val span { font-size: 1rem; color: var(--text-muted); font-weight: 500; }

        .stat-lbl {
            font-size: 0.75rem; color: var(--text-muted); font-weight: 500;
            text-transform: uppercase; letter-spacing: 0.05em;
        }

        /* ─── FILTER PANEL ─── */
        .filter-panel {
            background: var(--bg-surface); border: 1px solid var(--border);
            border-radius: var(--radius-xl); margin-bottom: 2rem; overflow: hidden;
        }

        .filter-header {
            display: flex; align-items: center; justify-content: space-between;
            padding: 1rem 1.5rem; border-bottom: 1px solid var(--border);
            gap: 1rem; flex-wrap: wrap; cursor: pointer; user-select: none;
        }
        .filter-header-left { display: flex; align-items: center; gap: 10px; }
        .filter-header-title { font-size: 0.875rem; font-weight: 600; color: var(--text-primary); }
        .filter-badge {
            display: inline-flex; align-items: center; justify-content: center;
            min-width: 20px; height: 20px; font-size: 0.7rem; font-weight: 700;
            background: var(--slate); color: #000; border-radius: 99px; padding: 0 6px;
        }
        .filter-toggle-btn {
            display: flex; align-items: center; gap: 6px; font-size: 0.8rem;
            font-weight: 500; color: var(--text-secondary); background: none;
            border: none; cursor: pointer; transition: color var(--transition);
        }
        .filter-toggle-btn:hover { color: var(--text-primary); }
        .filter-toggle-btn i { transition: transform 0.25s ease; }
        .filter-toggle-btn.collapsed i { transform: rotate(-90deg); }

        .filter-body { padding: 1.5rem; display: grid; transition: all 0.25s ease; }
        .filter-body.hidden { display: none; }

        .filter-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem; align-items: start;
        }

        /* ─── FORM CONTROLS ─── */
        .form-group { display: flex; flex-direction: column; gap: 6px; }

        .form-label {
            font-size: 0.72rem; font-weight: 600; text-transform: uppercase;
            letter-spacing: 0.06em; color: var(--text-secondary); display: flex; align-items: center; gap: 5px;
        }
        .form-label.accent { color: var(--slate); }

        .form-control {
            width: 100%; padding: 0 12px; height: 40px; background: var(--bg-elevated);
            border: 1px solid var(--border); color: var(--text-primary);
            border-radius: var(--radius-md); font-size: 0.875rem; font-family: var(--font);
            outline: none; transition: border-color var(--transition), box-shadow var(--transition);
            appearance: none; -webkit-appearance: none;
        }
        select.form-control {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2394a3b8' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
            background-repeat: no-repeat; background-position: right 12px center;
            padding-right: 36px; cursor: pointer;
        }
        .form-control:focus {
            border-color: var(--border-active); box-shadow: 0 0 0 3px var(--slate-glow); background: var(--bg-hover);
        }
        .form-control::placeholder { color: var(--text-muted); }
        .form-control option { background: #1e293b; color: var(--text-primary); }

        .input-row { display: grid; grid-template-columns: 1fr 1fr; gap: 6px; }

        /* ─── FILTER ACTIONS ─── */
        .filter-footer {
            display: flex; align-items: center; justify-content: space-between;
            padding: 1rem 1.5rem; border-top: 1px solid var(--border);
            flex-wrap: wrap; gap: 1rem;
        }
        .filter-footer-left, .filter-footer-right { display: flex; gap: 8px; flex-wrap: wrap; }

        /* ─── BUTTONS ─── */
        .btn {
            display: inline-flex; align-items: center; justify-content: center; gap: 7px;
            padding: 0 16px; height: 38px; border-radius: var(--radius-md);
            font-size: 0.8rem; font-weight: 600; font-family: var(--font);
            border: 1px solid transparent; cursor: pointer; transition: all var(--transition);
            text-decoration: none; white-space: nowrap; letter-spacing: 0.01em;
        }
        .btn-primary { background: var(--slate); color: #000; }
        .btn-primary:hover { background: #cbd5e1; box-shadow: 0 0 16px var(--slate-glow); }

        .btn-ghost { background: transparent; color: var(--text-secondary); border-color: var(--border); }
        .btn-ghost:hover { background: var(--bg-elevated); color: var(--text-primary); border-color: rgba(255,255,255,0.15); }

        .btn-pdf   { background: #1d4ed8; color: #fff; border-color: #1d4ed8; }
        .btn-pdf:hover { background: #1e40af; box-shadow: 0 0 12px rgba(29,78,216,0.4); }

        .btn-excel { background: #059669; color: #fff; border-color: #059669; }
        .btn-excel:hover { background: #047857; box-shadow: 0 0 12px rgba(5,150,105,0.4); }

        .btn-csv   { background: #b45309; color: #fff; border-color: #b45309; }
        .btn-csv:hover { background: #92400e; box-shadow: 0 0 12px rgba(180,83,9,0.35); }

        .btn-sm { height: 32px; padding: 0 12px; font-size: 0.75rem; }

        /* ─── TABLE ─── */
        .table-card {
            background: var(--bg-surface); border: 1px solid var(--border);
            border-radius: var(--radius-xl); overflow: hidden;
        }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 1000px; }

        thead th {
            background: var(--bg-elevated); color: var(--text-muted);
            font-size: 0.7rem; font-weight: 700; text-transform: uppercase;
            letter-spacing: 0.07em; padding: 12px 16px; text-align: left;
            border-bottom: 1px solid var(--border); white-space: nowrap;
        }

        tbody tr { border-bottom: 1px solid var(--border); transition: background var(--transition); }
        tbody tr:last-child { border-bottom: none; }
        tbody tr:hover { background: rgba(255,255,255,0.02); }

        td { padding: 12px 16px; font-size: 0.85rem; color: var(--text-primary); vertical-align: top; }

        /* Semantic Badges */
        .badge {
            display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px;
            border-radius: 6px; font-size: 0.7rem; font-weight: 700; letter-spacing: 0.03em;
        }
        .b-add  { background: var(--green-dim); color: var(--green); border: 1px solid rgba(34,197,94,0.2); }
        .b-edit { background: var(--blue-dim);  color: var(--blue);  border: 1px solid rgba(59,130,246,0.2); }
        .b-del  { background: var(--red-dim);   color: var(--red);   border: 1px solid rgba(248,113,113,0.2); }
        .b-auth { background: var(--purple-dim); color: var(--purple); border: 1px solid rgba(168,85,247,0.2); }
        .b-def  { background: var(--slate-dim);  color: var(--slate);  border: 1px solid rgba(148,163,184,0.2); }

        /* Typography Utilities */
        .val-mono { font-family: var(--font-mono); color: var(--text-muted); font-size: 0.8rem; }
        .td-user { font-weight: 600; color: #fff; font-size: 0.95rem; }
        .td-table { font-family: var(--font-mono); color: var(--purple); font-size: 0.85rem; font-weight: 600; }
        .td-details { max-width: 350px; white-space: normal; color: var(--text-secondary); line-height: 1.5; }
        .td-ip { font-family: var(--font-mono); color: var(--text-muted); font-size: 0.75rem; }
        .td-date { font-family: var(--font-mono); color: var(--text-primary); font-size: 0.85rem; white-space: nowrap; }

        /* ─── PAGINATION ─── */
        .pagination {
            display: flex; justify-content: center; align-items: center; gap: 6px;
            padding: 1.5rem; background: var(--bg-surface); border-top: 1px solid var(--border);
            flex-wrap: wrap;
        }
        .page-link {
            display: inline-flex; align-items: center; justify-content: center;
            height: 36px; padding: 0 14px; border-radius: var(--radius-md);
            background: var(--bg-elevated); border: 1px solid var(--border);
            color: var(--text-secondary); text-decoration: none; font-size: 0.85rem;
            font-weight: 600; transition: all var(--transition);
        }
        .page-link:hover { background: var(--bg-hover); color: var(--text-primary); }
        .page-link.disabled { opacity: 0.4; pointer-events: none; }
        .page-info { color: var(--text-muted); font-size: 0.85rem; margin: 0 10px; font-weight: 500; }

        .empty-state { text-align: center; padding: 4rem 2rem; color: var(--text-muted); }

        /* ─── RESPONSIVE ─── */
        @media (max-width: 900px) {
            .filter-grid { grid-template-columns: 1fr; }
        }

        @media (max-width: 768px) {
            .container { padding: 1rem; }
            .stats-grid { grid-template-columns: 1fr; }
            .filter-footer { flex-direction: column; align-items: stretch; }
            .filter-footer-left, .filter-footer-right { justify-content: stretch; }
            .filter-footer .btn { flex: 1; justify-content: center; }

            /* Mobile Table Cards */
            .table-card { background: transparent; border: none; overflow: visible; }
            .table-wrap { overflow: visible; }
            table { min-width: 0; display: block; }
            thead { display: none; }
            tbody { display: block; }
            tbody tr {
                display: block; background: var(--bg-surface);
                border: 1px solid var(--border); border-radius: var(--radius-lg);
                margin-bottom: 0.75rem; padding: 1.25rem; box-shadow: var(--shadow-sm);
            }
            td {
                display: flex; justify-content: space-between; align-items: flex-start;
                gap: 1rem; padding: 7px 0; border-bottom: 1px solid rgba(255,255,255,0.03); white-space: normal;
                text-align: right;
            }
            td:last-child { border-bottom: none; }
            td::before {
                content: attr(data-label); font-size: 0.7rem; font-weight: 700;
                text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted);
                white-space: nowrap; flex-shrink: 0; padding-top: 2px; text-align: left;
            }
            .td-details { text-align: right; }
            .pagination { flex-direction: column; gap: 10px; text-align: center; }
        }
    </style>
</head>
<body>

<div class="container">

    <div class="top-bar">
        <a href="reports.php" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back to Reports
        </a>
        <span class="page-badge"><i class="fa-solid fa-shield-halved"></i> Security</span>
    </div>

    <div class="page-header">
        <h1 class="page-title">System <span>Audit Logs</span></h1>
        <p class="page-subtitle">Security trail of user actions, logins, and critical data modifications.</p>
    </div>

    <div class="stats-grid">
        <div class="stat-card slate">
            <div class="stat-icon slate"><i class="fa-solid fa-server"></i></div>
            <div class="stat-val slate"><?= number_format($total_records) ?></div>
            <div class="stat-lbl">Total Events Logged</div>
        </div>
        <div class="stat-card blue">
            <div class="stat-icon blue"><i class="fa-solid fa-file-lines"></i></div>
            <div class="stat-val blue"><?= number_format($page_num) ?> <span>/ <?= number_format($total_pages) ?></span></div>
            <div class="stat-lbl">Current Page</div>
        </div>
    </div>

    <div class="filter-panel">
        <div class="filter-header" onclick="toggleFilters()" id="filterHeader">
            <div class="filter-header-left">
                <i class="fa-solid fa-sliders" style="color:var(--text-secondary); font-size:0.85rem;"></i>
                <span class="filter-header-title">Filters &amp; Search</span>
                <?php if($active_filters > 0): ?>
                    <span class="filter-badge"><?= $active_filters ?></span>
                <?php endif; ?>
            </div>
            <button class="filter-toggle-btn" id="filterToggleBtn" type="button">
                <span id="filterToggleLabel">Collapse</span>
                <i class="fa-solid fa-chevron-down" id="filterChevron"></i>
            </button>
        </div>

        <div class="filter-body" id="filterBody">
            <form method="GET" id="filterForm">
                <div class="filter-grid">

                    <div class="form-group">
                        <label class="form-label accent"><i class="fa-solid fa-magnifying-glass"></i> Keyword Search</label>
                        <input type="text" name="search" class="form-control" placeholder="e.g. Deleted ID 55, IP Address" value="<?= htmlspecialchars($search_term) ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label"><i class="fa-solid fa-user"></i> Target User</label>
                        <select name="user" class="form-control">
                            <option value="">All Users</option>
                            <?php foreach($users_list as $u): ?>
                                <option value="<?= $u ?>" <?= $user_filter == $u ? 'selected':'' ?>><?= htmlspecialchars($u) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label"><i class="fa-solid fa-bolt"></i> Action Type</label>
                        <select name="action" class="form-control">
                            <option value="">All Actions</option>
                            <?php foreach($actions_list as $a): ?>
                                <option value="<?= $a ?>" <?= $action_filter == $a ? 'selected':'' ?>><?= htmlspecialchars($a) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label"><i class="fa-solid fa-calendar-days"></i> Log Date Range</label>
                        <div class="input-row">
                            <input type="text" name="date_from" class="form-control date-picker" value="<?= htmlspecialchars($date_from) ?>" placeholder="Start Date">
                            <input type="text" name="date_to" class="form-control date-picker" value="<?= htmlspecialchars($date_to) ?>" placeholder="End Date">
                        </div>
                    </div>

                </div>
            </form>
        </div>

        <div class="filter-footer">
            <div class="filter-footer-left">
                <a href="audit_log_report.php" class="btn btn-ghost btn-sm">
                    <i class="fa-solid fa-rotate-left"></i> Reset
                </a>
                <button type="submit" form="filterForm" class="btn btn-primary btn-sm">
                    <i class="fa-solid fa-filter"></i> Apply Filters
                </button>
            </div>
            <div class="filter-footer-right">
                <button type="button" class="btn btn-pdf btn-sm" onclick="exportPDF()">
                    <i class="fa-solid fa-file-pdf"></i> PDF
                </button>
                <button type="button" class="btn btn-excel btn-sm" onclick="exportExcel()">
                    <i class="fa-solid fa-file-excel"></i> Excel
                </button>
                <button type="button" class="btn btn-csv btn-sm" onclick="exportCSV()">
                    <i class="fa-solid fa-file-csv"></i> CSV
                </button>
            </div>
        </div>
    </div>

    <div class="table-card">
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Log ID</th>
                        <th>Date & Time</th>
                        <th>User</th>
                        <th>Action</th>
                        <th>Target Table</th>
                        <th>Details</th>
                        <th>IP Address</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($logs)): ?>
                        <tr>
                            <td colspan="7">
                                <div class="empty-state">
                                    <i class="fa-solid fa-file-shield" style="font-size: 2.5rem; opacity: 0.3; margin-bottom: 1rem; display: block;"></i>
                                    No audit logs found matching your criteria.
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach($logs as $log): 
                            $act = strtoupper($log['ACTION_TYPE']);
                            $badgeClass = 'b-def';
                            if (strpos($act, 'ADD') !== false || strpos($act, 'CREATE') !== false) $badgeClass = 'b-add';
                            if (strpos($act, 'EDIT') !== false || strpos($act, 'UPDATE') !== false || strpos($act, 'CHANGE') !== false) $badgeClass = 'b-edit';
                            if (strpos($act, 'DELETE') !== false || strpos($act, 'REMOVE') !== false) $badgeClass = 'b-del';
                            if (strpos($act, 'LOGIN') !== false || strpos($act, 'AUTH') !== false) $badgeClass = 'b-auth';
                        ?>
                        <tr>
                            <td data-label="Log ID" class="val-mono">#<?= $log['LOG_ID'] ?></td>
                            <td data-label="Date & Time" class="td-date"><?= $log['LOG_DATE_FMT'] ?></td>
                            <td data-label="User">
                                <div class="td-user"><?= htmlspecialchars($log['USERNAME'] ?? 'System/Guest') ?></div>
                                <div style="font-size:0.75rem; color:var(--text-muted); font-family:var(--font-mono);">ID: <?= $log['USER_ID'] ?? '-' ?></div>
                            </td>
                            <td data-label="Action"><span class="badge <?= $badgeClass ?>"><?= $log['ACTION_TYPE'] ?></span></td>
                            <td data-label="Target Table" class="td-table"><?= htmlspecialchars($log['TABLE_NAME']) ?></td>
                            <td data-label="Details" class="td-details"><?= htmlspecialchars($log['ACTION_DETAILS']) ?></td>
                            <td data-label="IP Address" class="td-ip"><?= htmlspecialchars($log['IP_ADDRESS']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <a href="?<?= getQueryUrl($page_num - 1, $_GET) ?>" class="page-link <?= ($page_num <= 1) ? 'disabled' : '' ?>">
                <i class="fa-solid fa-chevron-left"></i> Prev
            </a>

            <span class="page-info">
                Page <strong><?= $page_num ?></strong> of <?= $total_pages ?> 
            </span>

            <a href="?<?= getQueryUrl($page_num + 1, $_GET) ?>" class="page-link <?= ($page_num >= $total_pages) ? 'disabled' : '' ?>">
                Next <i class="fa-solid fa-chevron-right"></i>
            </a>
        </div>
        <?php endif; ?>
    </div>

</div>

<script>
    // ─── Filter Panel Toggle ───
    let filterOpen = true;
    function toggleFilters() {
        filterOpen = !filterOpen;
        const body = document.getElementById('filterBody');
        const btn  = document.getElementById('filterToggleBtn');
        const label = document.getElementById('filterToggleLabel');

        body.classList.toggle('hidden', !filterOpen);
        btn.classList.toggle('collapsed', !filterOpen);
        label.textContent = filterOpen ? 'Collapse' : 'Expand';
    }

    // ─── Date Picker ───
    document.addEventListener('DOMContentLoaded', () => {
        flatpickr(".date-picker", {
            dateFormat: "Y-m-d", 
            altInput: true,      
            altFormat: "m/d/Y",  
            allowInput: true
        });
    });

    const jsPDF = window.jspdf.jsPDF;
    const records = <?php echo json_encode($logs); ?>;
    
    // ─── PDF Export ───
    function exportPDF() {
        const doc = new jsPDF('landscape');
        
        doc.setFontSize(16);
        doc.setTextColor(148, 163, 184); // Slate
        doc.text("System Audit Log Report - Page <?= $page_num ?>", 14, 14);
        
        doc.setFontSize(9);
        doc.setTextColor(100);
        let now = new Date();
        let formattedNow = `${String(now.getMonth() + 1).padStart(2, '0')}/${String(now.getDate()).padStart(2, '0')}/${now.getFullYear()} ${now.toLocaleTimeString()}`;
        doc.text(`Generated: ${formattedNow}`, 14, 21);

        const rows = records.map(r => [
            r.LOG_ID,
            r.LOG_DATE_FMT,
            r.USERNAME || 'Guest',
            r.ACTION_TYPE,
            r.TABLE_NAME,
            r.ACTION_DETAILS,
            r.IP_ADDRESS
        ]);

        doc.autoTable({
            head: [['ID', 'Date', 'User', 'Action', 'Table', 'Details', 'IP']],
            body: rows,
            startY: 28,
            styles: { fontSize: 7, overflow: 'linebreak', cellPadding: 1.5 },
            columnStyles: { 5: { cellWidth: 80 } },
            headStyles: { fillColor: [71, 85, 105] } // Dark Slate Header
        });

        doc.save('Audit_Log_Page_<?= $page_num ?>.pdf');
    }

    // ─── Excel Export ───
    function exportExcel() {
        const excelData = records.map(r => ({
            'Log ID': r.LOG_ID,
            'Date': r.LOG_DATE_FMT,
            'User ID': r.USER_ID,
            'Username': r.USERNAME || 'Guest',
            'Action Type': r.ACTION_TYPE,
            'Table Affected': r.TABLE_NAME,
            'Details': r.ACTION_DETAILS,
            'IP Address': r.IP_ADDRESS
        }));

        const ws = XLSX.utils.json_to_sheet(excelData);
        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, "Audit Logs");
        XLSX.writeFile(wb, "Audit_Log_Page_<?= $page_num ?>_" + new Date().toISOString().slice(0,10) + ".xlsx");
    }

    // ─── CSV Export ───
    function exportCSV() {
        let csvContent = "data:text/csv;charset=utf-8,";
        csvContent += "ID,Date,User,Action,Table,Details,IP\n";
        
        records.forEach(r => {
            const row = [
                r.LOG_ID, r.LOG_DATE_FMT, r.USERNAME || 'Guest', r.ACTION_TYPE, 
                r.TABLE_NAME, r.ACTION_DETAILS, r.IP_ADDRESS
            ].map(e => `"${(e || '').toString().replace(/"/g, '""')}"`).join(","); 
            csvContent += row + "\n";
        });

        const encodedUri = encodeURI(csvContent);
        const link = document.createElement("a");
        link.setAttribute("href", encodedUri);
        link.setAttribute("download", "Audit_Log_Page_<?= $page_num ?>_" + new Date().toISOString().slice(0,10) + ".csv");
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
</script>

</body>
</html>