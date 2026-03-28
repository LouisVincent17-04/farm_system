<?php
// reports/animal_sales_reports.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
$page = "reports";
include '../config/Connection.php';

include '../security/checkAccess.php';
checkAccess('animal_sales_report');
include '../common/navbar.php';
include '../common/chat_support.php';

// --- 1. CONFIGURATION & INPUTS ---
$limit = 50; // Rows per page
$page_num = isset($_GET['page_num']) ? max(1, (int)$_GET['page_num']) : 1;
$offset = ($page_num - 1) * $limit;

$date_from   = isset($_GET['date_from']) && $_GET['date_from'] !== '' ? $_GET['date_from'] : null;
$date_to     = isset($_GET['date_to']) && $_GET['date_to'] !== '' ? $_GET['date_to'] : null;
$search_term = isset($_GET['search']) && trim($_GET['search']) !== '' ? trim($_GET['search']) : null;

try {
    if (!isset($conn)) { throw new Exception("Database connection failed."); }

    // --- 2. BUILD SEARCH CONDITIONS ---
    $where_conditions = [];
    $params = [];

    // Filter: Sale Date Range
    if ($date_from !== null && $date_to !== null) {
        $where_conditions[] = "s.sale_date BETWEEN :date_from AND :date_to";
        $params[':date_from'] = $date_from . ' 00:00:00';
        $params[':date_to']   = $date_to . ' 23:59:59';
    } 
    // LOGIC ADJUSTMENT: If you want to default to TODAY only when NOT searching:
    elseif ($search_term === null) {
        $where_conditions[] = "s.sale_date BETWEEN CONCAT(CURDATE(), ' 00:00:00') AND CONCAT(CURDATE(), ' 23:59:59')";
    }

    // Filter: Search (CASE INSENSITIVE)
    if ($search_term !== null) {
        $search_pattern = "%" . strtolower($search_term) . "%";
        $where_conditions[] = "(LOWER(ar.TAG_NO) LIKE :search1 OR LOWER(s.customer_name) LIKE :search2 OR LOWER(s.notes) LIKE :search3)";
        $params[':search1'] = $search_pattern;
        $params[':search2'] = $search_pattern;
        $params[':search3'] = $search_pattern;
    }

    // Build WHERE clause
    $where_sql = '';
    if (!empty($where_conditions)) {
        $where_sql = ' AND ' . implode(' AND ', $where_conditions);
    }

    // --- 3. QUERY A: GET AGGREGATE TOTALS ---
    $stats_sql = "SELECT 
                    COUNT(*) AS total_count,
                    COALESCE(SUM(s.final_sale_price), 0) AS total_revenue,
                    COALESCE(SUM(s.total_net_worth), 0) AS total_expenses,
                    COALESCE(SUM(s.gross_profit), 0) AS total_profit,
                    COALESCE(SUM(s.weight_at_sale), 0) AS total_weight
                  FROM animal_sales s
                  LEFT JOIN animal_records ar ON s.animal_id = ar.ANIMAL_ID
                  WHERE 1=1 $where_sql";

    $stats_stmt = $conn->prepare($stats_sql);
    foreach ($params as $key => $val) {
        $stats_stmt->bindValue($key, $val, PDO::PARAM_STR);
    }
    $stats_stmt->execute();
    $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

    $total_records = $stats['total_count'] ?? 0;
    $total_revenue = $stats['total_revenue'] ?? 0;
    $total_expenses = $stats['total_expenses'] ?? 0;
    $total_profit = $stats['total_profit'] ?? 0;
    
    $total_pages = $total_records > 0 ? ceil($total_records / $limit) : 1;

    // --- 4. QUERY B: FETCH PAGINATED DATA ---
    $data_sql = "SELECT 
            s.sale_id,
            DATE_FORMAT(s.sale_date, '%m/%d/%Y %h:%i %p') as SALE_DATE_FMT,
            s.customer_name,
            s.weight_at_sale,
            s.price_per_kg,
            s.final_sale_price,
            s.total_net_worth as TOTAL_COST,
            s.gross_profit,
            s.notes,
            ar.TAG_NO,
            ar.ANIMAL_ID
        FROM animal_sales s
        LEFT JOIN animal_records ar ON s.animal_id = ar.ANIMAL_ID
        WHERE 1=1 $where_sql
        ORDER BY s.sale_date DESC
        LIMIT :limit OFFSET :offset";

    $stmt = $conn->prepare($data_sql);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val, PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    
    $stmt->execute();
    $sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    echo ""; 
    $sales = [];
    $total_records = 0; $total_revenue = 0; $total_expenses = 0; $total_profit = 0; $total_pages = 1;
}

function getQueryUrl($newPage, $currentParams) {
    $params = $currentParams;
    $params['page_num'] = $newPage;
    $params = array_filter($params, function($value) {
        return $value !== '' && $value !== null;
    });
    return http_build_query($params);
}

// Count active filters for badge
$active_filters = 0;
if ($date_from || $date_to) $active_filters++;
if ($search_term !== null) $active_filters++;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Animal Sales Report | FarmPro</title>
    
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
            --border-active:  rgba(16,185,129,0.5); /* Emerald Accent */
            --emerald:        #10b981;
            --emerald-dim:    rgba(16,185,129,0.12);
            --emerald-glow:   rgba(16,185,129,0.25);
            --blue:           #3b82f6;
            --red:            #f87171;
            --red-dim:        rgba(248,113,113,0.12);
            --amber:          #f59e0b;
            --text-primary:   #f1f5f9;
            --text-secondary: #94a3b8;
            --text-muted:     #475569;
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
                radial-gradient(ellipse 80% 50% at 50% -20%, rgba(16,185,129,0.06) 0%, transparent 60%),
                radial-gradient(ellipse 40% 30% at 85% 10%, rgba(56,189,248,0.03) 0%, transparent 50%);
        }
        .container { max-width: 1560px; margin: 0 auto; padding: 2rem 1.5rem; }

        /* ─── TOP BAR ─── */
        .top-bar { display: flex; align-items: center; justify-content: space-between; margin-bottom: 2rem; gap: 1rem; flex-wrap: wrap; }
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
            color: var(--emerald); background: var(--emerald-dim); border: 1px solid rgba(16,185,129,0.2);
            padding: 6px 12px; border-radius: 99px;
        }

        /* ─── PAGE HEADER ─── */
        .page-header { margin-bottom: 2rem; }
        .page-title {
            font-size: clamp(1.6rem, 3vw, 2.2rem); font-weight: 700;
            color: var(--text-primary); letter-spacing: -0.03em; line-height: 1.1; margin-bottom: 0.25rem;
        }
        .page-title span {
            background: linear-gradient(135deg, var(--emerald), #059669);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }
        .page-subtitle { color: var(--text-secondary); font-size: 0.9rem; }

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
        .stat-card:hover { transform: translateY(-1px); border-color: rgba(16,185,129,0.2); }
        .stat-icon {
            width: 32px; height: 32px; border-radius: var(--radius-sm);
            display: flex; align-items: center; justify-content: center;
            font-size: 0.85rem; margin-bottom: 0.75rem;
        }
        .stat-icon.emerald { background: var(--emerald-dim); color: var(--emerald); }
        .stat-icon.blue { background: rgba(59,130,246,0.12); color: var(--blue); }
        .stat-icon.red { background: var(--red-dim); color: var(--red); }
        .stat-icon.amber { background: rgba(245,158,11,0.12); color: var(--amber); }

        .stat-val {
            font-size: 1.6rem; font-weight: 700; letter-spacing: -0.03em;
            line-height: 1; margin-bottom: 4px; font-family: var(--font-mono);
        }
        .stat-val.emerald { color: var(--emerald); }
        .stat-val.blue { color: var(--blue); }
        .stat-val.red { color: var(--red); }
        .stat-val.amber { color: var(--amber); }

        .stat-lbl {
            font-size: 0.75rem; color: var(--text-muted); font-weight: 500;
            text-transform: uppercase; letter-spacing: 0.05em;
        }

        /* ─── ACTIVE FILTERS ─── */
        .active-filters {
            background: var(--emerald-dim); border: 1px solid rgba(16,185,129,0.2);
            padding: 0.75rem 1.25rem; border-radius: var(--radius-md); margin-bottom: 1.5rem;
            display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;
        }
        .active-filters-label { color: var(--emerald); font-size: 0.85rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; }
        .filter-tag {
            background: var(--bg-elevated); color: var(--text-primary); padding: 4px 10px;
            border-radius: 6px; font-size: 0.8rem; display: inline-flex; align-items: center; gap: 6px; border: 1px solid var(--border);
        }
        .filter-tag .close { cursor: pointer; color: var(--text-muted); text-decoration: none; font-weight: bold; }
        .filter-tag .close:hover { color: var(--red); }

        /* ─── FILTER PANEL ─── */
        .filter-panel {
            background: var(--bg-surface); border: 1px solid var(--border);
            border-radius: var(--radius-xl); margin-bottom: 2rem; overflow: hidden;
        }
        .filter-header {
            display: flex; align-items: center; justify-content: space-between;
            padding: 1rem 1.5rem; border-bottom: 1px solid var(--border);
            cursor: pointer; user-select: none;
        }
        .filter-header-left { display: flex; align-items: center; gap: 10px; }
        .filter-header-title { font-size: 0.875rem; font-weight: 600; color: var(--text-primary); }
        .filter-badge {
            display: inline-flex; align-items: center; justify-content: center;
            min-width: 20px; height: 20px; font-size: 0.7rem; font-weight: 700;
            background: var(--emerald); color: #000; border-radius: 99px; padding: 0 6px;
        }
        .filter-toggle-btn {
            display: flex; align-items: center; gap: 6px; font-size: 0.8rem;
            font-weight: 500; color: var(--text-secondary); background: none; border: none; cursor: pointer;
        }
        .filter-toggle-btn i { transition: transform 0.25s ease; }
        .filter-toggle-btn.collapsed i { transform: rotate(-90deg); }
        .filter-body { padding: 1.5rem; display: grid; transition: all 0.25s ease; }
        .filter-body.hidden { display: none; }

        .filter-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; align-items: start; }
        .form-group { display: flex; flex-direction: column; gap: 6px; }
        .form-label { font-size: 0.72rem; font-weight: 600; text-transform: uppercase; color: var(--text-secondary); letter-spacing: 0.06em; display: flex; align-items: center; gap: 5px; }
        .form-label.accent { color: var(--emerald); }
        .form-control {
            width: 100%; padding: 0 12px; height: 40px; background: var(--bg-elevated);
            border: 1px solid var(--border); color: var(--text-primary);
            border-radius: var(--radius-md); font-size: 0.875rem; font-family: var(--font);
            outline: none; transition: border-color var(--transition), box-shadow var(--transition);
        }
        .form-control:focus { border-color: var(--emerald); box-shadow: 0 0 0 3px var(--emerald-glow); background: var(--bg-hover); }
        .input-row { display: grid; grid-template-columns: 1fr 1fr; gap: 6px; }

        /* Actions Footer */
        .filter-footer {
            display: flex; align-items: center; justify-content: space-between;
            padding: 1rem 1.5rem; border-top: 1px solid var(--border); flex-wrap: wrap; gap: 1rem;
        }
        .filter-footer-left, .filter-footer-right { display: flex; gap: 8px; flex-wrap: wrap; }

        .btn {
            display: inline-flex; align-items: center; justify-content: center; gap: 7px;
            padding: 0 16px; height: 38px; border-radius: var(--radius-md);
            font-size: 0.8rem; font-weight: 600; font-family: var(--font);
            border: 1px solid transparent; cursor: pointer; transition: all var(--transition);
            text-decoration: none; white-space: nowrap; letter-spacing: 0.01em;
        }
        .btn-primary { background: var(--emerald); color: #000; }
        .btn-primary:hover { background: #34d399; box-shadow: 0 0 16px var(--emerald-glow); }
        .btn-ghost { background: transparent; color: var(--text-secondary); border-color: var(--border); }
        .btn-ghost:hover { background: var(--bg-elevated); color: var(--text-primary); border-color: rgba(255,255,255,0.15); }
        .btn-pdf { background: #1d4ed8; color: #fff; border-color: #1d4ed8; }
        .btn-pdf:hover { background: #1e40af; box-shadow: 0 0 12px rgba(29,78,216,0.4); }
        .btn-excel { background: #059669; color: #fff; border-color: #059669; }
        .btn-excel:hover { background: #047857; box-shadow: 0 0 12px rgba(5,150,105,0.4); }
        .btn-csv { background: #b45309; color: #fff; border-color: #b45309; }
        .btn-csv:hover { background: #92400e; box-shadow: 0 0 12px rgba(180,83,9,0.35); }
        .btn-sm { height: 32px; padding: 0 12px; font-size: 0.75rem; }

        /* ─── TABLE ─── */
        .table-card {
            background: var(--bg-surface); border: 1px solid var(--border);
            border-radius: var(--radius-xl); overflow: hidden;
        }
        .table-wrap { overflow-x: auto; }
        .table { width: 100%; border-collapse: collapse; min-width: 1000px; }
        .table thead th {
            background: var(--bg-elevated); color: var(--text-muted);
            font-size: 0.7rem; font-weight: 700; text-transform: uppercase;
            letter-spacing: 0.07em; padding: 12px 16px; text-align: left;
            border-bottom: 1px solid var(--border); white-space: nowrap;
        }
        .table tbody tr { border-bottom: 1px solid var(--border); transition: background var(--transition); }
        .table tbody tr:hover { background: rgba(255,255,255,0.02); }
        .table td { padding: 12px 16px; font-size: 0.85rem; color: var(--text-primary); vertical-align: middle; }

        .table tfoot tr { background: var(--bg-elevated); font-weight: 700; color: #fff; border-top: 2px solid var(--emerald); }
        .table tfoot td { padding: 14px 16px; font-size: 0.95rem; }

        /* Cell Formatting */
        .col-name { font-weight: 600; color: #fff; }
        .val-mono { font-family: var(--font-mono); font-size: 0.85rem; color: var(--text-secondary); }
        .val-money { font-family: var(--font-mono); font-weight: 600; font-size: 0.9rem; }
        .text-green { color: var(--emerald); }
        .text-red { color: var(--red); }
        
        .highlight {
            background: rgba(250, 204, 21, 0.2); color: var(--amber);
            padding: 0 4px; border-radius: 4px; font-weight: 700;
        }

        .empty-state { text-align: center; padding: 4rem 2rem; color: var(--text-muted); }
        .empty-state i { font-size: 2.5rem; margin-bottom: 1rem; opacity: 0.4; display: block; }

        /* ─── PAGINATION ─── */
        .pagination {
            display: flex; justify-content: center; align-items: center; gap: 6px;
            padding: 1.5rem; background: var(--bg-surface); border-top: 1px solid var(--border); flex-wrap: wrap;
        }
        .page-link {
            display: inline-flex; align-items: center; justify-content: center;
            height: 36px; padding: 0 14px; border-radius: var(--radius-md);
            background: var(--bg-elevated); border: 1px solid var(--border);
            color: var(--text-secondary); text-decoration: none; font-size: 0.85rem; font-weight: 600; transition: all var(--transition);
        }
        .page-link:hover:not(.disabled) { background: var(--bg-hover); color: var(--text-primary); }
        .page-link.disabled { opacity: 0.4; pointer-events: none; }
        .page-info { color: var(--text-muted); font-size: 0.85rem; margin: 0 10px; font-weight: 500; }

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

            /* Mobile Table to Cards */
            .table-wrap { border: none; background: transparent; overflow: visible; }
            .table { min-width: 0; display: block; }
            .table thead { display: none; }
            .table tbody, .table tfoot { display: block; }
            .table tbody tr, .table tfoot tr {
                display: block; background: var(--bg-surface);
                border: 1px solid var(--border); border-radius: var(--radius-lg);
                margin-bottom: 0.75rem; padding: 1.25rem; box-shadow: var(--shadow-sm);
            }
            .table td {
                display: flex; justify-content: space-between; align-items: center;
                gap: 1rem; padding: 7px 0; border-bottom: 1px solid rgba(255,255,255,0.05); text-align: right;
            }
            .table td:last-child { border-bottom: none; }
            .table td::before {
                content: attr(data-label); font-size: 0.7rem; font-weight: 700;
                text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted); text-align: left;
            }
            .table tfoot td { font-size: 1rem; }
        }
    </style>
</head>
<body>

<div class="container">

    <div class="top-bar">
        <a href="reports.php" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back to Reports
        </a>
        <span class="page-badge"><i class="fa-solid fa-money-bill-trend-up"></i> Financials</span>
    </div>

    <div class="page-header">
        <h1 class="page-title">Animal <span>Sales</span> Report</h1>
        <p class="page-subtitle">Financial record of sold livestock, revenue, and calculated profit margins.</p>
    </div>

    <?php if ($search_term !== null || $date_from !== null): ?>
    <div class="active-filters">
        <span class="active-filters-label"><i class="fa-solid fa-filter me-1"></i> Active Filters:</span>
        <?php if ($search_term !== null): ?>
            <span class="filter-tag">
                Search: "<?= htmlspecialchars($search_term) ?>"
                <a href="?<?= http_build_query(array_diff_key($_GET, ['search' => ''])) ?>" class="close"><i class="fa-solid fa-xmark"></i></a>
            </span>
        <?php endif; ?>
        <?php if ($date_from !== null && $date_to !== null): ?>
            <span class="filter-tag">
                Date: <?= htmlspecialchars($date_from) ?> to <?= htmlspecialchars($date_to) ?>
                <a href="?<?= http_build_query(array_diff_key($_GET, ['date_from' => '', 'date_to' => ''])) ?>" class="close"><i class="fa-solid fa-xmark"></i></a>
            </span>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon blue"><i class="fa-solid fa-tags"></i></div>
            <div class="stat-val blue"><?= number_format($total_records) ?></div>
            <div class="stat-lbl">Total Heads Sold</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon emerald"><i class="fa-solid fa-sack-dollar"></i></div>
            <div class="stat-val emerald">₱<?= number_format($total_revenue, 2) ?></div>
            <div class="stat-lbl">Gross Revenue</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon red"><i class="fa-solid fa-money-bill-transfer"></i></div>
            <div class="stat-val red">₱<?= number_format($total_expenses, 2) ?></div>
            <div class="stat-lbl">Total Expenses</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon amber"><i class="fa-solid fa-chart-line"></i></div>
            <div class="stat-val <?= $total_profit >= 0 ? 'text-green':'text-red' ?>">
                ₱<?= number_format($total_profit, 2) ?>
            </div>
            <div class="stat-lbl">Net Profit</div>
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
                        <label class="form-label accent"><i class="fa-solid fa-magnifying-glass"></i> Search Ledger</label>
                        <input type="text" name="search" class="form-control" placeholder="Try: A00, Customer Name" value="<?= htmlspecialchars($search_term ?? '') ?>" autocomplete="off">
                    </div>

                    <div class="form-group">
                        <label class="form-label"><i class="fa-solid fa-calendar-days"></i> Sale Date Range</label>
                        <div class="input-row">
                            <input type="text" name="date_from" class="form-control date-picker" value="<?= htmlspecialchars($date_from ?? '') ?>" placeholder="Start Date">
                            <input type="text" name="date_to" class="form-control date-picker" value="<?= htmlspecialchars($date_to ?? '') ?>" placeholder="End Date">
                        </div>
                    </div>

                </div>
            </form>
        </div>

        <div class="filter-footer">
            <div class="filter-footer-left">
                <a href="animal_sales_reports.php" class="btn btn-ghost btn-sm">
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
            <table class="table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Animal Tag</th>
                        <th>Customer</th>
                        <th style="text-align:right;">Weight (kg)</th>
                        <th style="text-align:right;">Sale Price</th>
                        <th style="text-align:right;">Expenses</th>
                        <th style="text-align:right;">Net Profit</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($sales)): ?>
                        <tr>
                            <td colspan="7">
                                <div class="empty-state">
                                    <i class="fa-solid fa-receipt"></i>
                                    <?php if ($search_term !== null): ?>
                                        <p>No sales records found matching <strong>"<?= htmlspecialchars($search_term) ?>"</strong></p>
                                        <small>Try searching different terms or clearing filters.</small>
                                    <?php else: ?>
                                        <p>No sales records found for the selected date range.</p>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php 
                        function highlightSearch($text, $search) {
                            if (empty($search) || empty($text)) return htmlspecialchars($text);
                            $text = htmlspecialchars($text);
                            $search = preg_quote($search, '/');
                            return preg_replace('/(' . $search . ')/i', '<span class="highlight">$1</span>', $text);
                        }
                        
                        foreach($sales as $s): 
                        ?>
                        <tr>
                            <td data-label="Date" class="val-mono"><?= $s['SALE_DATE_FMT'] ?></td>
                            <td data-label="Animal Tag" class="col-name"><?= highlightSearch($s['TAG_NO'] ?? 'N/A', $search_term ?? '') ?></td>
                            <td data-label="Customer" style="color:var(--text-secondary);"><?= highlightSearch($s['customer_name'] ?? 'N/A', $search_term ?? '') ?></td>
                            <td data-label="Weight (kg)" class="val-mono" style="text-align:right; color:var(--text-primary);"><?= number_format($s['weight_at_sale'] ?? 0, 2) ?></td>
                            <td data-label="Sale Price" class="val-money text-green" style="text-align:right;">₱<?= number_format($s['final_sale_price'] ?? 0, 2) ?></td>
                            <td data-label="Expenses" class="val-money text-red" style="text-align:right;">₱<?= number_format($s['TOTAL_COST'] ?? 0, 2) ?></td>
                            <td data-label="Net Profit" class="val-money <?= ($s['gross_profit'] > 0) ? 'text-green' : 'text-red' ?>" style="text-align:right;">
                                ₱<?= number_format($s['gross_profit'] ?? 0, 2) ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <?php if (!empty($sales)): ?>
                <tfoot>
                    <tr>
                        <td colspan="4" style="text-align:right; text-transform:uppercase; letter-spacing:1px; color:var(--text-secondary);">
                            Grand Total <?= ($search_term !== null) ? '(Filtered)' : '(All Records)' ?>:
                        </td>
                        <td data-label="Total Revenue" style="text-align:right; color:var(--emerald); font-family:var(--font-mono);">₱<?= number_format($total_revenue, 2) ?></td>
                        <td data-label="Total Expenses" style="text-align:right; color:var(--red); font-family:var(--font-mono);">₱<?= number_format($total_expenses, 2) ?></td>
                        <td data-label="Total Profit" style="text-align:right; color:var(--emerald); font-family:var(--font-mono);">₱<?= number_format($total_profit, 2) ?></td>
                    </tr>
                </tfoot>
                <?php endif; ?>
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

    // ─── Exports ───
    const jsPDF = window.jspdf.jsPDF;
    const records = <?php echo json_encode($sales); ?>;
    const searchTerm = <?php echo json_encode($search_term); ?>;
    const totals = {
        revenue: <?= $total_revenue ?>,
        expenses: <?= $total_expenses ?>,
        profit: <?= $total_profit ?>,
        count: <?= $total_records ?>
    };
    
    function exportPDF() {
        const doc = new jsPDF('landscape');
        doc.setFontSize(16); doc.setTextColor(16, 185, 129); // Emerald
        doc.text("Animal Sales Report", 14, 14);
        
        doc.setFontSize(9); doc.setTextColor(120);
        const dateStr = new Date().toLocaleString();
        doc.text(`Generated: ${dateStr}`, 14, 21);
        
        let startY = 27;
        if (searchTerm) {
            doc.text(`Search Filter: "${searchTerm}"`, 14, startY);
            startY += 6;
        }

        let tableData = records.map(r => [
            r.SALE_DATE_FMT,
            r.TAG_NO || 'N/A',
            r.customer_name || 'N/A',
            (r.weight_at_sale || 0).toFixed(2),
            parseFloat(r.final_sale_price || 0).toFixed(2),
            parseFloat(r.TOTAL_COST || 0).toFixed(2),
            parseFloat(r.gross_profit || 0).toFixed(2)
        ]);

        tableData.push([
            '', '', '', 'GRAND TOTAL:', 
            totals.revenue.toFixed(2), 
            totals.expenses.toFixed(2), 
            totals.profit.toFixed(2)
        ]);

        doc.autoTable({
            head: [['Date', 'Tag', 'Customer', 'Weight', 'Sale Price (PHP)', 'Expenses (PHP)', 'Profit (PHP)']],
            body: tableData,
            startY: startY,
            styles: { fontSize: 8, cellPadding: 1.5 },
            headStyles: { fillColor: [4, 120, 87] }, // Dark Emerald Header
            didParseCell: function (data) {
                if (data.row.index === tableData.length - 1) {
                    data.cell.styles.fontStyle = 'bold';
                    data.cell.styles.fillColor = [15, 23, 42]; // Dark slate background for total row
                    data.cell.styles.textColor = [52, 211, 153]; // Emerald text
                }
            }
        });
        
        const filename = searchTerm ? 
            `Sales_Report_Search_${searchTerm}_<?= date('Y-m-d') ?>.pdf` : 
            `Sales_Report_<?= date('Y-m-d') ?>.pdf`;
        doc.save(filename);
    }

    function exportExcel() {
        const excelData = records.map(r => ({
            'Date': r.SALE_DATE_FMT,
            'Tag No': r.TAG_NO || 'N/A',
            'Customer': r.customer_name || 'N/A',
            'Weight (kg)': parseFloat(r.weight_at_sale || 0),
            'Sale Price': parseFloat(r.final_sale_price || 0),
            'Total Expenses': parseFloat(r.TOTAL_COST || 0),
            'Net Profit': parseFloat(r.gross_profit || 0)
        }));

        excelData.push({
            'Date': '', 'Tag No': '', 'Customer': '',
            'Weight (kg)': 'GRAND TOTAL',
            'Sale Price': totals.revenue,
            'Total Expenses': totals.expenses,
            'Net Profit': totals.profit
        });

        const ws = XLSX.utils.json_to_sheet(excelData);
        ws['!cols'] = [ { wch: 18 }, { wch: 12 }, { wch: 25 }, { wch: 12 }, { wch: 15 }, { wch: 15 }, { wch: 15 } ];
        
        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, "Sales");
        
        const filename = searchTerm ? 
            `Sales_Report_Search_${searchTerm}_<?= date('Y-m-d') ?>.xlsx` : 
            `Sales_Report_<?= date('Y-m-d') ?>.xlsx`;
        XLSX.writeFile(wb, filename);
    }

    function exportCSV() {
        const headers = ['Date', 'Tag', 'Customer', 'Weight', 'Sale Price', 'Expenses', 'Profit'];
        let csvContent = headers.join(',') + '\n';
        
        records.forEach(r => {
            const row = [
                r.SALE_DATE_FMT,
                r.TAG_NO || 'N/A',
                r.customer_name || 'N/A',
                (r.weight_at_sale || 0).toFixed(2),
                (r.final_sale_price || 0).toFixed(2),
                (r.TOTAL_COST || 0).toFixed(2),
                (r.gross_profit || 0).toFixed(2)
            ].map(e => {
                const str = String(e).replace(/"/g, '""');
                return str.includes(',') || str.includes('"') ? `"${str}"` : str;
            }).join(',');
            csvContent += row + '\n';
        });

        csvContent += `,,,"GRAND TOTAL",${totals.revenue.toFixed(2)},${totals.expenses.toFixed(2)},${totals.profit.toFixed(2)}\n`;

        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        const filename = searchTerm ? 
            `Sales_Report_Search_${searchTerm}_<?= date('Y-m-d') ?>.csv` : 
            `Sales_Report_<?= date('Y-m-d') ?>.csv`;
        link.download = filename;
        link.style.display = 'none';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
</script>

</body>
</html>