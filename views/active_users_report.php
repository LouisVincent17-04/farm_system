<?php
// reports/active_user_report.php
error_reporting(0);
ini_set('display_errors', 0);
include '../config/Connection.php';
$page = "reports";
include '../security/checkAccess.php';
checkAccess('active_users_report');

include '../common/navbar.php';
include '../common/chat_support.php';

// --- 1. GET FILTER INPUTS ---
$user_type = $_GET['user_type'] ?? '';
$is_active = $_GET['is_active'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to   = $_GET['date_to'] ?? '';

try {
    if (!isset($conn)) { throw new Exception("Database connection failed."); }

    // --- 2. BUILD SQL QUERY ---
    // Updated DATE_FORMAT to output: mm/dd/yyyy hh:mm AM/PM
    $sql = "SELECT 
        u.USER_ID,
        u.FULL_NAME,
        u.EMAIL,
        u.CONTACT_INFO,
        u.IS_ACTIVE,
        ut.USER_TYPE_NAME,
        DATE_FORMAT(u.CREATED_AT, '%m/%d/%Y %h:%i %p') AS CREATED_AT_FMT,
        DATE_FORMAT(u.DATE_UPDATED, '%m/%d/%Y %h:%i %p') AS DATE_UPDATED_FMT
    FROM USERS u
    LEFT JOIN USER_TYPES ut ON u.USER_TYPE = ut.USER_TYPE_ID
    WHERE 1=1";

    $params = [];

    // Apply Filters
    if ($user_type !== '') {
        $sql .= " AND u.USER_TYPE = :user_type";
        $params[':user_type'] = $user_type;
    }

    if ($is_active !== '') {
        $sql .= " AND u.IS_ACTIVE = :is_active";
        $params[':is_active'] = $is_active;
    }

    if ($date_from && $date_to) {
        $sql .= " AND u.CREATED_AT BETWEEN :date_from AND :date_to";
        $params[':date_from'] = $date_from . ' 00:00:00';
        $params[':date_to']   = $date_to . ' 23:59:59';
    }

    $sql .= " ORDER BY u.CREATED_AT DESC";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // --- 3. CALCULATE STATISTICS ---
    $total_users = count($users);
    $total_active = 0;
    $total_inactive = 0;
    
    // Count Roles for Stats
    $role_counts = [];

    foreach ($users as $row) {
        if ($row['IS_ACTIVE'] == 1) $total_active++;
        else $total_inactive++;

        // Track Role Distribution
        $role = $row['USER_TYPE_NAME'] ?? 'Unknown';
        if(!isset($role_counts[$role])) $role_counts[$role] = 0;
        $role_counts[$role]++;
    }

    // --- 4. FETCH DROPDOWNS ---
    $user_types = $conn->query("SELECT USER_TYPE_ID, USER_TYPE_NAME FROM USER_TYPES ORDER BY USER_TYPE_NAME")->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $users = [];
    error_log($e->getMessage());
}

// Count active filters for badge
$active_filters = 0;
if ($date_from || $date_to) $active_filters++;
if ($user_type !== '') $active_filters++;
if ($is_active !== '') $active_filters++;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Active User Report</title>
    
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
            --border-active:  rgba(56,189,248,0.5); /* Blue Accent for Users */
            --green:          #22c55e;
            --green-dim:      rgba(34,197,94,0.12);
            --green-glow:     rgba(34,197,94,0.25);
            --gold:           #f59e0b;
            --blue:           #38bdf8;
            --blue-dim:       rgba(56,189,248,0.12);
            --blue-glow:      rgba(56,189,248,0.25);
            --red:            #f87171;
            --red-dim:        rgba(248,113,113,0.12);
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
            background-image:
                radial-gradient(ellipse 80% 50% at 50% -20%, rgba(56,189,248,0.06) 0%, transparent 60%),
                radial-gradient(ellipse 40% 30% at 85% 10%, rgba(34,197,94,0.04) 0%, transparent 50%);
        }

        .container {
            max-width: 1560px;
            margin: 0 auto;
            padding: 2rem 1.5rem;
        }

        /* ─── TOP BAR ─── */
        .top-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 2rem;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            color: var(--text-secondary);
            font-size: 0.875rem;
            font-weight: 500;
            padding: 8px 14px;
            background: var(--bg-elevated);
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            transition: all var(--transition);
        }
        .back-link:hover { color: var(--text-primary); border-color: var(--border-active); background: var(--bg-hover); }

        .page-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--blue);
            background: var(--blue-dim);
            border: 1px solid rgba(56,189,248,0.2);
            padding: 6px 12px;
            border-radius: 99px;
        }

        /* ─── PAGE HEADER ─── */
        .page-header { margin-bottom: 2rem; }
        .page-title {
            font-size: clamp(1.6rem, 3vw, 2.2rem);
            font-weight: 700;
            color: var(--text-primary);
            letter-spacing: -0.03em;
            line-height: 1.1;
        }
        .page-title span {
            background: linear-gradient(135deg, var(--blue), #0284c7);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .page-subtitle {
            color: var(--text-secondary);
            font-size: 0.9rem;
            margin-top: 6px;
        }

        /* ─── STAT CARDS ─── */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .stat-card {
            background: var(--bg-surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 1.25rem 1.5rem;
            position: relative;
            overflow: hidden;
            transition: border-color var(--transition), transform var(--transition);
        }
        .stat-card::before {
            content: ''; position: absolute; inset: 0; opacity: 0;
            transition: opacity var(--transition);
        }
        .stat-card:hover { transform: translateY(-1px); }
        .stat-card:hover::before { opacity: 1; }

        .stat-card.blue::before  { background: linear-gradient(135deg, rgba(56,189,248,0.04), transparent); }
        .stat-card.green::before { background: linear-gradient(135deg, rgba(34,197,94,0.04), transparent); }
        .stat-card.red::before   { background: linear-gradient(135deg, rgba(248,113,113,0.04), transparent); }

        .stat-card.blue { border-color: rgba(56,189,248,0.15); }
        .stat-card.green{ border-color: rgba(34,197,94,0.15); }
        .stat-card.red  { border-color: rgba(248,113,113,0.15); }

        .stat-icon {
            width: 32px; height: 32px; border-radius: var(--radius-sm);
            display: flex; align-items: center; justify-content: center;
            font-size: 0.85rem; margin-bottom: 0.75rem;
        }
        .stat-icon.blue  { background: var(--blue-dim);  color: var(--blue); }
        .stat-icon.green { background: var(--green-dim); color: var(--green); }
        .stat-icon.red   { background: var(--red-dim);   color: var(--red); }

        .stat-val {
            font-size: 1.6rem; font-weight: 700; letter-spacing: -0.03em;
            line-height: 1; margin-bottom: 4px;
        }
        .stat-val.blue  { color: var(--blue); }
        .stat-val.green { color: var(--green); }
        .stat-val.red   { color: var(--red); }

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
            background: var(--blue); color: #000; border-radius: 99px; padding: 0 6px;
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
            display: grid; grid-template-columns: repeat(3, 1fr);
            gap: 1rem; align-items: start;
        }

        /* ─── FORM CONTROLS ─── */
        .form-group { display: flex; flex-direction: column; gap: 6px; }

        .form-label {
            font-size: 0.72rem; font-weight: 600; text-transform: uppercase;
            letter-spacing: 0.06em; color: var(--text-secondary);
            display: flex; align-items: center; gap: 5px;
        }
        .form-label.accent { color: var(--blue); }
        .form-label i { font-size: 0.65rem; opacity: 0.7; }

        .form-control {
            width: 100%; padding: 0 12px; height: 40px; background: var(--bg-elevated);
            border: 1px solid var(--border); color: var(--text-primary);
            border-radius: var(--radius-md); font-size: 0.875rem; font-family: var(--font);
            outline: none; transition: border-color var(--transition), box-shadow var(--transition), background var(--transition);
            appearance: none; -webkit-appearance: none;
        }
        select.form-control {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2394a3b8' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
            background-repeat: no-repeat; background-position: right 12px center;
            padding-right: 36px; cursor: pointer;
        }
        .form-control:focus {
            border-color: var(--border-active); box-shadow: 0 0 0 3px var(--blue-glow);
            background: var(--bg-hover);
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
        .btn i { font-size: 0.75rem; }

        .btn-primary { background: var(--blue); color: #000; border-color: var(--blue); }
        .btn-primary:hover { background: #0284c7; box-shadow: 0 0 16px var(--blue-glow); color: #fff; }

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
            letter-spacing: 0.07em; padding: 10px 14px; text-align: left;
            border-bottom: 1px solid var(--border); white-space: nowrap;
        }

        tbody tr { border-bottom: 1px solid var(--border); transition: background var(--transition); }
        tbody tr:last-child { border-bottom: none; }
        tbody tr:hover { background: rgba(255,255,255,0.02); }

        td {
            padding: 10px 14px; font-size: 0.825rem; color: var(--text-primary);
            white-space: nowrap; vertical-align: middle;
        }

        /* ─── BADGES ─── */
        .badge {
            display: inline-flex; align-items: center; gap: 4px; padding: 3px 9px;
            border-radius: 99px; font-size: 0.7rem; font-weight: 700; letter-spacing: 0.03em;
        }
        .b-active   { background: var(--green-dim); color: var(--green); border: 1px solid rgba(34,197,94,0.2); }
        .b-inactive { background: var(--red-dim);   color: var(--red);   border: 1px solid rgba(248,113,113,0.2); }

        /* ─── VALUE CELLS ─── */
        .val-id    { font-family: var(--font-mono); color: var(--text-secondary); font-size: 0.8rem; }
        .val-date  { font-family: var(--font-mono); font-size: 0.78rem; color: var(--text-muted); }

        /* ─── EMPTY STATE ─── */
        .empty-state { text-align: center; padding: 4rem 2rem; color: var(--text-muted); }
        .empty-state i { font-size: 2.5rem; margin-bottom: 1rem; opacity: 0.4; display: block; }
        .empty-state p { font-size: 0.875rem; }

        /* ─── UTILITIES ─── */
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .col-name { font-weight: 600; color: var(--text-primary); }

        /* ─── RESPONSIVE ─── */
        @media (max-width: 900px) {
            .filter-grid { grid-template-columns: 1fr; }
        }

        @media (max-width: 768px) {
            .container { padding: 1rem; }
            .stats-grid { grid-template-columns: 1fr; }
            .filter-footer { flex-direction: column; align-items: stretch; }
            .filter-footer-left, .filter-footer-right { justify-content: stretch; }
            .filter-footer .btn { flex: 1; }

            /* Mobile card layout for table */
            .table-card { background: transparent; border: none; overflow: visible; }
            .table-wrap { overflow: visible; }
            table { min-width: 0; display: block; }
            thead { display: none; }
            tbody { display: block; }
            tbody tr {
                display: block; background: var(--bg-surface);
                border: 1px solid var(--border); border-radius: var(--radius-lg);
                margin-bottom: 0.75rem; padding: 1rem; box-shadow: var(--shadow-sm);
            }
            td {
                display: flex; justify-content: space-between; align-items: flex-start;
                gap: 1rem; padding: 7px 0; border-bottom: 1px solid var(--border); white-space: normal;
                text-align: right;
            }
            td:last-child { border-bottom: none; }
            td::before {
                content: attr(data-label); font-size: 0.7rem; font-weight: 700;
                text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted);
                white-space: nowrap; flex-shrink: 0; padding-top: 2px; text-align: left;
            }
        }
    </style>
</head>
<body>

<div class="container">

    <div class="top-bar">
        <a href="reports.php" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back to Reports
        </a>
        <span class="page-badge"><i class="fa-solid fa-users-gear"></i> User Management</span>
    </div>

    <div class="page-header">
        <h1 class="page-title">Active <span>User</span> Report</h1>
        <p class="page-subtitle">System access logs, role distribution, and account status tracking.</p>
    </div>

    <div class="stats-grid">
        <div class="stat-card blue">
            <div class="stat-icon blue"><i class="fa-solid fa-users"></i></div>
            <div class="stat-val blue"><?= number_format($total_users) ?></div>
            <div class="stat-lbl">Total Users</div>
        </div>
        <div class="stat-card green">
            <div class="stat-icon green"><i class="fa-solid fa-user-check"></i></div>
            <div class="stat-val green"><?= number_format($total_active) ?></div>
            <div class="stat-lbl">Active Accounts</div>
        </div>
        <div class="stat-card red">
            <div class="stat-icon red"><i class="fa-solid fa-user-xmark"></i></div>
            <div class="stat-val red"><?= number_format($total_inactive) ?></div>
            <div class="stat-lbl">Inactive Accounts</div>
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
                        <label class="form-label accent"><i class="fa-solid fa-id-badge"></i> User Role</label>
                        <select name="user_type" class="form-control">
                            <option value="">All Roles</option>
                            <?php foreach($user_types as $t): ?>
                                <option value="<?= $t['USER_TYPE_ID'] ?>" <?= $user_type == $t['USER_TYPE_ID'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($t['USER_TYPE_NAME']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label"><i class="fa-solid fa-toggle-on"></i> Account Status</label>
                        <select name="is_active" class="form-control">
                            <option value="">All Statuses</option>
                            <option value="1" <?= $is_active === '1' ? 'selected' : '' ?>>Active</option>
                            <option value="0" <?= $is_active === '0' ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label"><i class="fa-solid fa-calendar-days"></i> Registration Date Range</label>
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
                <a href="active_user_report.php" class="btn btn-ghost btn-sm">
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
            <table id="userTable">
                <thead>
                    <tr>
                        <th>User ID</th>
                        <th>Full Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Contact</th>
                        <th class="text-center">Status</th>
                        <th>Registered Date</th>
                        <th>Last Update</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($users)): ?>
                        <tr>
                            <td colspan="8">
                                <div class="empty-state">
                                    <i class="fa-solid fa-user-slash"></i>
                                    <p>No user records found matching your filters.</p>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach($users as $u): 
                            $statusClass = ($u['IS_ACTIVE'] == 1) ? 'b-active' : 'b-inactive';
                            $statusText  = ($u['IS_ACTIVE'] == 1) ? 'Active' : 'Inactive';
                        ?>
                        <tr>
                            <td data-label="User ID" class="val-id"><?= htmlspecialchars($u['USER_ID']) ?></td>
                            <td data-label="Full Name" class="col-name"><?= htmlspecialchars($u['FULL_NAME']) ?></td>
                            <td data-label="Email"><?= htmlspecialchars($u['EMAIL']) ?></td>
                            <td data-label="Role" style="color:var(--blue);"><?= htmlspecialchars($u['USER_TYPE_NAME'] ?? 'N/A') ?></td>
                            <td data-label="Contact"><?= htmlspecialchars($u['CONTACT_INFO'] ?? '-') ?></td>
                            <td data-label="Status" class="text-center"><span class="badge <?= $statusClass ?>"><?= $statusText ?></span></td>
                            <td data-label="Registered" class="val-date"><?= $u['CREATED_AT_FMT'] ?></td>
                            <td data-label="Last Update" class="val-date"><?= $u['DATE_UPDATED_FMT'] ?? '-' ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
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

    // ─── Exports ───
    const jsPDF = window.jspdf.jsPDF;
    const records = <?php echo json_encode($users); ?>;
    
    function exportPDF() {
        const doc = new jsPDF('landscape');
        doc.setFontSize(16); doc.setTextColor(56, 189, 248); // Blue
        doc.text("Active User Report", 14, 14);
        
        let now = new Date();
        let formattedNow = `${String(now.getMonth() + 1).padStart(2, '0')}/${String(now.getDate()).padStart(2, '0')}/${now.getFullYear()} ${now.toLocaleTimeString()}`;
        
        doc.setFontSize(9); doc.setTextColor(120);
        doc.text(`Generated: ${formattedNow}`, 14, 21);
        doc.text(`Total Users: <?= $total_users ?>`, 250, 21);

        const rows = records.map(r => [
            r.USER_ID,
            r.FULL_NAME,
            r.EMAIL,
            r.USER_TYPE_NAME || 'N/A',
            r.CONTACT_INFO || '-',
            r.IS_ACTIVE == 1 ? 'Active' : 'Inactive',
            r.CREATED_AT_FMT
        ]);

        doc.autoTable({
            head: [['ID', 'Name', 'Email', 'Role', 'Contact', 'Status', 'Registered']],
            body: rows, startY: 26, 
            styles: { fontSize: 8, cellPadding: 1.5 }, 
            headStyles: { fillColor: [2, 132, 199] } // Darker blue for table header
        });

        doc.save('User_Report.pdf');
    }

    function exportExcel() {
        const excelData = records.map(r => ({
            'User ID': r.USER_ID,
            'Full Name': r.FULL_NAME,
            'Email': r.EMAIL,
            'Role': r.USER_TYPE_NAME,
            'Contact': r.CONTACT_INFO,
            'Status': r.IS_ACTIVE == 1 ? 'Active' : 'Inactive',
            'Registered Date': r.CREATED_AT_FMT,
            'Last Update': r.DATE_UPDATED_FMT
        }));

        const ws = XLSX.utils.json_to_sheet(excelData);
        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, "Users");
        XLSX.writeFile(wb, "User_Report_" + new Date().toISOString().slice(0,10) + ".xlsx");
    }

    function exportCSV() {
        let csvContent = "data:text/csv;charset=utf-8,";
        csvContent += "ID,Name,Email,Role,Contact,Status,Registered\n";
        
        records.forEach(r => {
            const status = r.IS_ACTIVE == 1 ? 'Active' : 'Inactive';
            const row = [
                r.USER_ID, r.FULL_NAME, r.EMAIL, r.USER_TYPE_NAME, 
                r.CONTACT_INFO, status, r.CREATED_AT_FMT
            ].map(e => `"${e || ''}"`).join(","); 
            csvContent += row + "\n";
        });

        const encodedUri = encodeURI(csvContent);
        const link = document.createElement("a");
        link.setAttribute("href", encodedUri);
        link.setAttribute("download", "User_Report_" + new Date().toISOString().slice(0,10) + ".csv");
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
</script>

</body>
</html>