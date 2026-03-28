<?php
// reports/housing_report.php
error_reporting(0);
ini_set('display_errors', 0);
$page = "reports";
include '../config/Connection.php';

include '../security/checkAccess.php';
checkAccess('housing_facilities_report');
include '../common/navbar.php';
include '../common/chat_support.php';

// --- 1. GET FILTER INPUTS ---
$location_id  = $_GET['location'] ?? '';
$date_from    = $_GET['date_from'] ?? '';
$date_to      = $_GET['date_to'] ?? '';
$status       = $_GET['status'] ?? ''; // '1' = Active, '0' = Inactive
$search_term  = trim($_GET['search'] ?? '');

try {
    if (!isset($conn)) { throw new Exception("Database connection failed."); }

    // --- 2. BUILD SQL QUERY ---
    // Fetch items where ITEM_TYPE_ID = 3 (Housing & Facilities)
    $sql = "SELECT 
            i.ITEM_ID,
            i.ITEM_NAME,
            i.ITEM_DESCRIPTION,
            i.ITEM_NET_WEIGHT,
            DATE_FORMAT(i.DATE_OF_PURCHASE, '%m/%d/%Y') as DATE_OF_PURCHASE_FMT,
            i.QUANTITY,
            i.UNIT_COST,
            i.TOTAL_COST,
            i.STATUS,
            i.LOCATION_ID,
            l.LOCATION_NAME,
            DATE_FORMAT(i.CREATED_AT, '%m/%d/%Y') as DATE_ADDED_FMT
        FROM items i
        LEFT JOIN locations l ON i.LOCATION_ID = l.LOCATION_ID
        WHERE i.ITEM_TYPE_ID = 3";

    $params = [];

    // Filter: Location
    if ($location_id) {
        $sql .= " AND i.LOCATION_ID = :loc_id";
        $params[':loc_id'] = $location_id;
    }

    // Filter: Date Added Range
    if ($date_from && $date_to) {
        $sql .= " AND DATE(i.CREATED_AT) BETWEEN :date_from AND :date_to";
        $params[':date_from'] = $date_from;
        $params[':date_to']   = $date_to;
    }

    // Filter: Status
    if ($status !== '') {
        $sql .= " AND i.STATUS = :status";
        $params[':status'] = $status;
    }

    // Filter: Search (CASE INSENSITIVE)
    if ($search_term) {
        $search_pattern = "%" . strtolower($search_term) . "%";
        $sql .= " AND (LOWER(i.ITEM_NAME) LIKE :search1 OR LOWER(i.ITEM_DESCRIPTION) LIKE :search2)";
        $params[':search1'] = $search_pattern;
        $params[':search2'] = $search_pattern;
    }

    $sql .= " ORDER BY i.ITEM_NAME ASC"; 

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $raw_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // --- 3. PROCESS DATA & STATS ---
    $items = [];
    
    // Statistics
    $total_items_count = 0; // Total individual units (sum of quantity)
    $total_asset_value = 0;
    $unique_items = 0;
    $active_assets = 0;

    foreach ($raw_data as $row) {
        // Status Logic
        $row['STATUS_LABEL'] = ($row['STATUS'] == 1) ? 'In Use' : 'Inactive/Written Off';
        
        // Calculate dynamic total if missing in DB
        $calculated_total = ($row['TOTAL_COST'] > 0) ? $row['TOTAL_COST'] : ($row['QUANTITY'] * $row['UNIT_COST']);
        $row['CALCULATED_TOTAL'] = $calculated_total;

        // Aggregates
        $unique_items++;
        $total_items_count += $row['QUANTITY'];
        $total_asset_value += $calculated_total;
        
        if ($row['STATUS'] == 1) {
            $active_assets++;
        }

        $items[] = $row;
    }

    // --- 4. FETCH LOCATIONS FOR DROPDOWN ---
    $locations = $conn->query("SELECT * FROM locations ORDER BY LOCATION_NAME")->fetchAll();

} catch (Exception $e) {
    $items = [];
    error_log($e->getMessage());
}

// Count active filters for the UI badge
$active_filters = 0;
if ($date_from || $date_to) $active_filters++;
if ($location_id !== '') $active_filters++;
if ($status !== '') $active_filters++;
if ($search_term !== '') $active_filters++;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Housing & Facilities Report</title>
    
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
            --border-active:  rgba(6,182,212,0.5); /* Cyan Accent */
            --cyan:           #06b6d4;
            --cyan-dim:       rgba(6,182,212,0.12);
            --cyan-glow:      rgba(6,182,212,0.25);
            --green:          #22c55e;
            --green-dim:      rgba(34,197,94,0.12);
            --gold:           #f59e0b;
            --gold-dim:       rgba(245,158,11,0.12);
            --blue:           #38bdf8;
            --blue-dim:       rgba(56,189,248,0.12);
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
                radial-gradient(ellipse 80% 50% at 50% -20%, rgba(6,182,212,0.06) 0%, transparent 60%),
                radial-gradient(ellipse 40% 30% at 85% 10%, rgba(34,197,94,0.04) 0%, transparent 50%);
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
            color: var(--cyan); background: var(--cyan-dim); border: 1px solid rgba(6,182,212,0.2);
            padding: 6px 12px; border-radius: 99px;
        }

        /* ─── PAGE HEADER ─── */
        .page-header { margin-bottom: 2rem; }
        .page-title {
            font-size: clamp(1.6rem, 3vw, 2.2rem); font-weight: 700;
            color: var(--text-primary); letter-spacing: -0.03em; line-height: 1.1;
        }
        .page-title span {
            background: linear-gradient(135deg, var(--cyan), #0891b2);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }
        .page-subtitle { color: var(--text-secondary); font-size: 0.9rem; margin-top: 6px; }

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

        .stat-card.cyan::before  { background: linear-gradient(135deg, rgba(6,182,212,0.04), transparent); }
        .stat-card.green::before { background: linear-gradient(135deg, rgba(34,197,94,0.04), transparent); }
        .stat-card.blue::before  { background: linear-gradient(135deg, rgba(56,189,248,0.04), transparent); }
        .stat-card.gold::before  { background: linear-gradient(135deg, rgba(245,158,11,0.04), transparent); }

        .stat-card.cyan { border-color: rgba(6,182,212,0.15); }
        .stat-card.green{ border-color: rgba(34,197,94,0.15); }
        .stat-card.blue { border-color: rgba(56,189,248,0.15); }
        .stat-card.gold { border-color: rgba(245,158,11,0.15); }

        .stat-icon {
            width: 32px; height: 32px; border-radius: var(--radius-sm);
            display: flex; align-items: center; justify-content: center;
            font-size: 0.85rem; margin-bottom: 0.75rem;
        }
        .stat-icon.cyan  { background: var(--cyan-dim);  color: var(--cyan); }
        .stat-icon.green { background: var(--green-dim); color: var(--green); }
        .stat-icon.blue  { background: var(--blue-dim);  color: var(--blue); }
        .stat-icon.gold  { background: var(--gold-dim);  color: var(--gold); }

        .stat-val {
            font-size: 1.6rem; font-weight: 700; letter-spacing: -0.03em;
            line-height: 1; margin-bottom: 4px;
        }
        .stat-val.cyan  { color: var(--cyan); }
        .stat-val.green { color: var(--green); }
        .stat-val.blue  { color: var(--blue); }
        .stat-val.gold  { color: var(--gold); }

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
            background: var(--cyan); color: #000; border-radius: 99px; padding: 0 6px;
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
            display: grid; grid-template-columns: repeat(4, 1fr);
            gap: 1rem; align-items: start;
        }

        /* ─── FORM CONTROLS ─── */
        .form-group { display: flex; flex-direction: column; gap: 6px; }

        .form-label {
            font-size: 0.72rem; font-weight: 600; text-transform: uppercase;
            letter-spacing: 0.06em; color: var(--text-secondary);
            display: flex; align-items: center; gap: 5px;
        }
        .form-label.accent { color: var(--cyan); }
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
            border-color: var(--border-active); box-shadow: 0 0 0 3px var(--cyan-glow);
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

        .btn-primary { background: var(--cyan); color: #000; border-color: var(--cyan); }
        .btn-primary:hover { background: #0891b2; box-shadow: 0 0 16px var(--cyan-glow); color: #fff; }

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
        .val-money { font-family: var(--font-mono); color: var(--green); font-size: 0.8rem; font-weight: 600; }
        .val-cost  { font-family: var(--font-mono); color: var(--text-secondary); font-size: 0.8rem; }
        .val-count { font-family: var(--font-mono); color: var(--cyan); font-size: 0.85rem; font-weight: 600; }

        /* ─── EMPTY STATE ─── */
        .empty-state { text-align: center; padding: 4rem 2rem; color: var(--text-muted); }
        .empty-state i { font-size: 2.5rem; margin-bottom: 1rem; opacity: 0.4; display: block; }
        .empty-state p { font-size: 0.875rem; }

        /* ─── UTILITIES ─── */
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .col-name { font-weight: 600; color: var(--text-primary); }

        /* ─── RESPONSIVE ─── */
        @media (max-width: 1100px) {
            .filter-grid { grid-template-columns: repeat(2, 1fr); }
        }

        @media (max-width: 768px) {
            .container { padding: 1rem; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .filter-grid { grid-template-columns: 1fr; }
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

            /* Fix text overflow for description on mobile */
            td[data-label="Description"] { flex-direction: column; align-items: flex-end; text-align: right;}
            td[data-label="Description"]::before { margin-bottom: 0.25rem; width: 100%; text-align: left;}
        }

        @media (max-width: 520px) {
            .stats-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<div class="container">

    <div class="top-bar">
        <a href="reports.php" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back to Reports
        </a>
        <span class="page-badge"><i class="fa-solid fa-warehouse"></i> Facilities</span>
    </div>

    <div class="page-header">
        <h1 class="page-title">Housing <span>& Facilities</span> Report</h1>
        <p class="page-subtitle">Asset inventory, valuation, and location tracking.</p>
    </div>

    <div class="stats-grid">
        <div class="stat-card cyan">
            <div class="stat-icon cyan"><i class="fa-solid fa-building"></i></div>
            <div class="stat-val cyan"><?= number_format($active_assets) ?></div>
            <div class="stat-lbl">Active Assets</div>
        </div>
        <div class="stat-card green">
            <div class="stat-icon green"><i class="fa-solid fa-peso-sign"></i></div>
            <div class="stat-val green">₱<?= number_format($total_asset_value, 2) ?></div>
            <div class="stat-lbl">Total Asset Value</div>
        </div>
        <div class="stat-card blue">
            <div class="stat-icon blue"><i class="fa-solid fa-boxes-stacked"></i></div>
            <div class="stat-val blue"><?= number_format($total_items_count) ?></div>
            <div class="stat-lbl">Total Quantity</div>
        </div>
        <div class="stat-card gold">
            <div class="stat-icon gold"><i class="fa-solid fa-tags"></i></div>
            <div class="stat-val gold"><?= number_format($unique_items) ?></div>
            <div class="stat-lbl">Unique Item Types</div>
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
                        <label class="form-label accent"><i class="fa-solid fa-map-pin"></i> Location</label>
                        <select name="location" class="form-control">
                            <option value="">All Locations</option>
                            <?php foreach($locations as $loc): ?>
                                <option value="<?= $loc['LOCATION_ID'] ?>" <?= $location_id == $loc['LOCATION_ID'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($loc['LOCATION_NAME']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label"><i class="fa-solid fa-toggle-on"></i> Status</label>
                        <select name="status" class="form-control">
                            <option value="">All Statuses</option>
                            <option value="1" <?= $status === '1' ? 'selected' : '' ?>>In Use / Active</option>
                            <option value="0" <?= $status === '0' ? 'selected' : '' ?>>Inactive / Disposed</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label"><i class="fa-solid fa-magnifying-glass"></i> Search Item</label>
                        <input type="text" name="search" class="form-control" placeholder="e.g. Kennel, Pen" value="<?= htmlspecialchars($search_term) ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label"><i class="fa-solid fa-calendar-days"></i> Date Added Range</label>
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
                <a href="housing_report.php" class="btn btn-ghost btn-sm">
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
            <table id="housingTable">
                <thead>
                    <tr>
                        <th>Item Name</th>
                        <th>Description</th>
                        <th>Location</th>
                        <th>Date Added</th>
                        <th class="text-right">Quantity</th>
                        <th class="text-right">Unit Cost</th>
                        <th class="text-right">Total Value</th>
                        <th class="text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($items)): ?>
                        <tr>
                            <td colspan="8">
                                <div class="empty-state">
                                    <i class="fa-solid fa-warehouse"></i>
                                    <p>No housing or facility records found matching your filters.</p>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach($items as $i): 
                            $badgeClass = ($i['STATUS'] == 1) ? 'b-active' : 'b-inactive';
                        ?>
                        <tr>
                            <td data-label="Item Name" class="col-name"><?= htmlspecialchars($i['ITEM_NAME']) ?></td>
                            <td data-label="Description" style="color:var(--text-secondary); max-width: 250px; overflow:hidden; text-overflow:ellipsis;">
                                <?= htmlspecialchars($i['ITEM_DESCRIPTION'] ?: '-') ?>
                            </td>
                            <td data-label="Location"><?= htmlspecialchars($i['LOCATION_NAME'] ?? 'Unassigned') ?></td>
                            <td data-label="Date Added" style="color:var(--text-muted); font-size:0.85rem;"><?= $i['DATE_ADDED_FMT'] ?></td>
                            <td data-label="Quantity" class="text-right val-count"><?= number_format($i['QUANTITY']) ?></td>
                            <td data-label="Unit Cost" class="text-right val-cost">₱<?= number_format($i['UNIT_COST'], 2) ?></td>
                            <td data-label="Total Value" class="text-right val-money">₱<?= number_format($i['CALCULATED_TOTAL'], 2) ?></td>
                            <td data-label="Status" class="text-center"><span class="badge <?= $badgeClass ?>"><?= $i['STATUS_LABEL'] ?></span></td>
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
    const records = <?php echo json_encode($items); ?>;
    const stats = {
        totalValue: "<?= number_format($total_asset_value, 2) ?>",
        totalItems: "<?= number_format($total_items_count) ?>"
    };
    
    function exportPDF() {
        const doc = new jsPDF('landscape');
        doc.setFontSize(16); doc.setTextColor(6, 182, 212); // Cyan
        doc.text("Housing & Facilities Report", 14, 14);
        
        let now = new Date();
        let formattedNow = `${String(now.getMonth() + 1).padStart(2, '0')}/${String(now.getDate()).padStart(2, '0')}/${now.getFullYear()} ${now.toLocaleTimeString()}`;
        
        doc.setFontSize(9); doc.setTextColor(120);
        doc.text(`Generated: ${formattedNow}`, 14, 21);
        doc.text(`Total Asset Value: PHP ${stats.totalValue}`, 230, 21);

        const rows = records.map(r => [
            r.ITEM_NAME,
            r.ITEM_DESCRIPTION || '-',
            r.LOCATION_NAME || 'Unassigned',
            r.DATE_ADDED_FMT,
            r.QUANTITY,
            parseFloat(r.UNIT_COST).toFixed(2),
            parseFloat(r.CALCULATED_TOTAL).toFixed(2),
            r.STATUS_LABEL
        ]);

        doc.autoTable({
            head: [['Item Name', 'Description', 'Location', 'Date Added', 'Qty', 'Unit Cost (PHP)', 'Total Value (PHP)', 'Status']],
            body: rows, startY: 26, 
            styles: { fontSize: 8, cellPadding: 1.5 }, 
            headStyles: { fillColor: [8, 145, 178] } // Teal Header
        });

        doc.save('Housing_Report.pdf');
    }

    function exportExcel() {
        const excelData = records.map(r => ({
            'Item Name': r.ITEM_NAME,
            'Description': r.ITEM_DESCRIPTION,
            'Location': r.LOCATION_NAME || 'Unassigned',
            'Date Added': r.DATE_ADDED_FMT,
            'Purchase Date': r.DATE_OF_PURCHASE_FMT,
            'Quantity': parseInt(r.QUANTITY),
            'Unit Cost (PHP)': parseFloat(r.UNIT_COST),
            'Total Value (PHP)': parseFloat(r.CALCULATED_TOTAL),
            'Status': r.STATUS_LABEL
        }));

        const ws = XLSX.utils.json_to_sheet(excelData);
        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, "Housing Assets");
        XLSX.writeFile(wb, "Housing_Report_" + new Date().toISOString().slice(0,10) + ".xlsx");
    }

    function exportCSV() {
        let csvContent = "data:text/csv;charset=utf-8,";
        csvContent += "Item Name,Description,Location,Date Added,Qty,Unit Cost,Total Value,Status\n";
        
        records.forEach(r => {
            const row = [
                r.ITEM_NAME, r.ITEM_DESCRIPTION, r.LOCATION_NAME, r.DATE_ADDED_FMT,
                r.QUANTITY, r.UNIT_COST, r.CALCULATED_TOTAL, r.STATUS_LABEL
            ].map(e => `"${e || ''}"`).join(","); 
            csvContent += row + "\n";
        });

        const encodedUri = encodeURI(csvContent);
        const link = document.createElement("a");
        link.setAttribute("href", encodedUri);
        link.setAttribute("download", "Housing_Report_" + new Date().toISOString().slice(0,10) + ".csv");
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
</script>

</body>
</html>