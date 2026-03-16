<?php
// views/vaccines_usage_report.php
ob_start();
error_reporting(0);
ini_set('display_errors', 0);
$page = "reports";
include '../config/Connection.php';

include '../security/checkAccess.php';
checkAccess('reports');
include '../common/navbar.php';
include '../common/chat_support.php';
include '../functions/getUsersLocation.php';

// =========================================================
// 1. AJAX HANDLER (For Dropdowns)
// =========================================================
if (isset($_GET['action'])) {
    ob_end_clean();
    header('Content-Type: application/json');
    $action = $_GET['action'];

    try {
        if ($action === 'get_buildings' && isset($_GET['loc_id'])) {
            $stmt = $conn->prepare("SELECT BUILDING_ID, BUILDING_NAME FROM buildings WHERE LOCATION_ID = ? ORDER BY BUILDING_NAME");
            $stmt->execute([$_GET['loc_id']]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC)); exit;
        }
        if ($action === 'get_pens' && isset($_GET['bldg_id'])) {
            $stmt = $conn->prepare("SELECT PEN_ID, PEN_NAME FROM pens WHERE BUILDING_ID = ? ORDER BY PEN_NAME");
            $stmt->execute([$_GET['bldg_id']]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC)); exit;
        }
        if ($action === 'get_animals' && isset($_GET['pen_id'])) {
            $stmt = $conn->prepare("SELECT ANIMAL_ID, TAG_NO FROM animal_records WHERE PEN_ID = ? AND IS_ACTIVE = 1 ORDER BY TAG_NO");
            $stmt->execute([$_GET['pen_id']]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC)); exit;
        }
    } catch (Exception $e) { echo json_encode([]); exit; }
}

// =========================================================
// 2. GET FILTER INPUTS
// =========================================================
$f_loc     = $_GET['f_loc'] ?? '';
$f_bld     = $_GET['f_bld'] ?? '';
$f_pen     = $_GET['f_pen'] ?? '';
$f_ani     = $_GET['f_animal'] ?? '';

// Default internal values for PHP querying
$default_date_from = date('Y-01-01');
$default_date_to   = date('Y-m-d');

$date_from = $_GET['date_from'] ?? '';
$date_to   = $_GET['date_to'] ?? '';

// Use actual filter if present, else use default for queries
$q_date_from = $date_from ?: $default_date_from;
$q_date_to   = $date_to ?: $default_date_to;

$display_date_range = date('m/d/Y', strtotime($q_date_from)) . " - " . date('m/d/Y', strtotime($q_date_to));

if ($USER_LOCATION_ != 1000) {
    $f_loc = $USER_LOCATION_;
}

if ($USER_LOCATION_ != 1000) {
    $stmt = $conn->prepare("SELECT * FROM locations WHERE LOCATION_ID = ? ORDER BY LOCATION_NAME");
    $stmt->execute([$USER_LOCATION_]);
    $locations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $locations = $conn->query("SELECT * FROM locations ORDER BY LOCATION_NAME")->fetchAll(PDO::FETCH_ASSOC);
}

$current_location_name = "All Locations";
if ($f_loc) {
    foreach ($locations as $loc) {
        if ($loc['LOCATION_ID'] == $f_loc) {
            $current_location_name = $loc['LOCATION_NAME'];
            break;
        }
    }
}

// =========================================================
// 3. BUILD SQL QUERIES & FETCH DATA
// =========================================================
$grouped_data = [];
$grand_total_used = 0;
$grand_total_cost = 0;
$unique_items = [];

try {
    // --- A. FETCH CONFIRMED PURCHASES (INWARDS) FROM `items` TABLE ---
    $purch_params = [':df' => $q_date_from, ':dt' => $q_date_to];
    $purch_where = "";
    if ($f_loc) {
        $purch_where = " AND i.LOCATION_ID = :ploc";
        $purch_params[':ploc'] = $f_loc;
    }

    // ITEM_TYPE_ID = 11 is Vaccines
    $purch_sql = "SELECT 
                    SUM(COALESCE(i.TOTAL_QTY, (i.QUANTITY * i.ITEM_NET_WEIGHT), i.QUANTITY)) as purchased_qty, 
                    SUM(i.TOTAL_COST) as purchased_cost 
                  FROM items i
                  WHERE i.STATUS = 1 
                  AND i.ITEM_TYPE_ID = 11 
                  AND COALESCE(NULLIF(DATE(i.DATE_OF_PURCHASE), ''), DATE(i.CREATED_AT)) BETWEEN :df AND :dt
                  $purch_where";
    $stmt = $conn->prepare($purch_sql);
    $stmt->execute($purch_params);
    $purch_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $purchased_qty = $purch_data['purchased_qty'] ?? 0;
    $purchased_cost = $purch_data['purchased_cost'] ?? 0;

    $purch_item_sql = "SELECT 
                        l.LOCATION_NAME,
                        i.ITEM_NAME, 
                        SUM(COALESCE(i.TOTAL_QTY, (i.QUANTITY * i.ITEM_NET_WEIGHT), i.QUANTITY)) as qty, 
                        SUM(i.TOTAL_COST) as cost 
                  FROM items i
                  LEFT JOIN locations l ON i.LOCATION_ID = l.LOCATION_ID
                  WHERE i.STATUS = 1 
                  AND i.ITEM_TYPE_ID = 11
                  AND COALESCE(NULLIF(DATE(i.DATE_OF_PURCHASE), ''), DATE(i.CREATED_AT)) BETWEEN :df AND :dt
                  $purch_where
                  GROUP BY l.LOCATION_NAME, i.ITEM_NAME";
    $stmt = $conn->prepare($purch_item_sql);
    $stmt->execute($purch_params);
    $purch_item_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $purchases_by_item = [];
    foreach($purch_item_data as $p) {
        $loc_name = $p['LOCATION_NAME'] ?? 'Unassigned Location';
        $key = strtolower(trim($loc_name)) . '_' . strtolower(trim($p['ITEM_NAME']));
        $purchases_by_item[$key] = [
            'qty' => $p['qty'],
            'cost' => $p['cost'],
            'original_name' => $p['ITEM_NAME'],
            'location_name' => $loc_name
        ];
    }

    // --- B. FETCH USAGE (OUTWARDS) ---
    $where = " WHERE 1=1 ";
    $params = [];

    if ($f_loc) { $where .= " AND loc.LOCATION_ID = :loc"; $params[':loc'] = $f_loc; }
    if ($f_bld) { $where .= " AND bld.BUILDING_ID = :bld"; $params[':bld'] = $f_bld; }
    if ($f_pen) { $where .= " AND p.PEN_ID = :pen"; $params[':pen'] = $f_pen; }
    if ($f_ani) { $where .= " AND a.ANIMAL_ID = :ani"; $params[':ani'] = $f_ani; }
    if ($q_date_from && $q_date_to) { 
        $where .= " AND DATE(vr.VACCINATION_DATE) BETWEEN :df AND :dt";
        $params[':df'] = $q_date_from; $params[':dt'] = $q_date_to;
    }

    $group_by_clause = "loc.LOCATION_NAME, bld.BUILDING_NAME, p.PEN_NAME";
    $select_tag = "'' AS TAG_NO";
    if ($f_ani) {
        $group_by_clause .= ", a.TAG_NO";
        $select_tag = "a.TAG_NO";
    }

    $sql = "SELECT 
                COALESCE(loc.LOCATION_NAME, 'Unassigned Location') AS LOCATION_NAME, 
                COALESCE(bld.BUILDING_NAME, 'Unassigned Building') AS BUILDING_NAME, 
                COALESCE(p.PEN_NAME, 'Unassigned Pen') AS PEN_NAME, 
                $select_tag,
                COALESCE(v.SUPPLY_NAME, 'Deleted Vaccine') AS ITEM_NAME,
                SUM(vr.QUANTITY) as TOTAL_USED,
                SUM(vr.VACCINE_COST) as TOTAL_COST,
                MIN(DATE(vr.VACCINATION_DATE)) as MIN_DATE,
                MAX(DATE(vr.VACCINATION_DATE)) as MAX_DATE
            FROM vaccination_records vr
            LEFT JOIN vaccines v ON vr.ITEM_ID = v.SUPPLY_ID
            LEFT JOIN animal_records a ON vr.ANIMAL_ID = a.ANIMAL_ID
            LEFT JOIN pens p ON a.PEN_ID = p.PEN_ID
            LEFT JOIN buildings bld ON p.BUILDING_ID = bld.BUILDING_ID
            LEFT JOIN locations loc ON bld.LOCATION_ID = loc.LOCATION_ID
            $where
            GROUP BY $group_by_clause, v.SUPPLY_NAME
            ORDER BY loc.LOCATION_NAME, bld.BUILDING_NAME, p.PEN_NAME, v.SUPPLY_NAME";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($results as $row) {
        $header = $row['LOCATION_NAME'] . " ➔ " . $row['BUILDING_NAME'] . " ➔ " . $row['PEN_NAME'];
        if ($f_ani) { $header .= " ➔ Tag: " . $row['TAG_NO']; }

        if(!isset($grouped_data[$header])) {
            $grouped_data[$header] = [ 'items' => [], 'sub_used' => 0, 'sub_cost' => 0 ];
        }

        $item_key = strtolower(trim($row['LOCATION_NAME'])) . '_' . strtolower(trim($row['ITEM_NAME']));
        $row['PURCHASED_QTY'] = $purchases_by_item[$item_key]['qty'] ?? 0;
        $row['PURCHASED_COST'] = $purchases_by_item[$item_key]['cost'] ?? 0;

        if(isset($purchases_by_item[$item_key])) {
            $purchases_by_item[$item_key]['processed'] = true;
        }

        $grouped_data[$header]['items'][] = $row;
        $grouped_data[$header]['sub_used'] += $row['TOTAL_USED'];
        $grouped_data[$header]['sub_cost'] += $row['TOTAL_COST'];

        $grand_total_used += $row['TOTAL_USED'];
        $grand_total_cost += $row['TOTAL_COST'];
        $unique_items[$row['ITEM_NAME']] = true;
    }

    // --- C. FETCH INVENTORY ADJUSTMENTS ---
    $adj_params = [':df' => $q_date_from, ':dt' => $q_date_to];
    $adj_where = " WHERE ia.CATEGORY = 'vaccine' AND DATE(ia.TRANSACTION_DATE) BETWEEN :df AND :dt ";
    
    if ($f_loc) {
        $adj_where .= " AND v.LOCATION_ID = :aloc";
        $adj_params[':aloc'] = $f_loc;
    }

    $adj_sql = "SELECT 
                    ia.TRANSACTION_DATE,
                    DATE_FORMAT(ia.TRANSACTION_DATE, '%m/%d/%Y %h:%i %p') as TRANSACTION_DATE_FMT,
                    ia.ITEM_NAME,
                    ia.ADJUSTMENT_TYPE,
                    ia.QUANTITY,
                    ia.REASON,
                    ia.REMARKS,
                    COALESCE(l.LOCATION_NAME, 'Unassigned') as LOCATION_NAME
                FROM inventory_adjustments ia
                LEFT JOIN vaccines v ON ia.REF_ID = v.SUPPLY_ID
                LEFT JOIN locations l ON v.LOCATION_ID = l.LOCATION_ID
                $adj_where
                ORDER BY ia.TRANSACTION_DATE DESC, ia.ADJUSTMENT_ID DESC";
    $stmt = $conn->prepare($adj_sql);
    $stmt->execute($adj_params);
    $adjustments_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $total_added = 0;
    $total_deducted = 0;
    foreach($adjustments_data as $adj) {
        if(strtolower($adj['ADJUSTMENT_TYPE']) == 'add') {
            $total_added += $adj['QUANTITY'];
        } else {
            $total_deducted += $adj['QUANTITY'];
        }
    }
    $net_adjustments = $total_added - $total_deducted;

    // --- D. FETCH CURRENT WAREHOUSE STOCK ---
    $stock_params = [];
    $stock_where = "";
    if ($f_loc) {
        $stock_where = " WHERE LOCATION_ID = :sloc";
        $stock_params[':sloc'] = $f_loc;
    }
    $stock_sql = "SELECT SUM(TOTAL_STOCK) as current_stock FROM vaccines $stock_where";
    $stmt = $conn->prepare($stock_sql);
    $stmt->execute($stock_params);
    $stock_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $current_stock = $stock_data['current_stock'] ?? 0;

} catch (Exception $e) {
    error_log($e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Vaccines Usage & Accountancy Report</title>
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" type="text/css" href="https://npmcdn.com/flatpickr/dist/themes/dark.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.31/jspdf.plugin.autotable.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />

    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: system-ui, -apple-system, sans-serif; background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: #e2e8f0; min-height: 100vh; padding-bottom: 40px; }
        .container { max-width: 1500px; margin: 0 auto; padding: 2rem; }
        
        .back-link { display: inline-flex; align-items: center; gap: 8px; text-decoration: none; color: #94a3b8; font-weight: 600; font-size: 0.95rem; margin-bottom: 20px; transition: color 0.2s; }
        .back-link:hover { color: white; }

        .header { text-align: center; margin-bottom: 2rem; }
        .title { font-size: clamp(1.8rem, 4vw, 2.5rem); font-weight: 800; background: linear-gradient(135deg, #06b6d4, #0891b2); -webkit-background-clip: text; -webkit-text-fill-color: transparent; margin-bottom: 0.5rem; }
        .subtitle { color: #94a3b8; font-size: 1rem; margin-bottom: 0.5rem;}
        
        .location-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-top: 10px;
            padding: 6px 16px;
            background: rgba(6, 182, 212, 0.15);
            border: 1px solid rgba(6, 182, 212, 0.3);
            color: #22d3ee;
            border-radius: 20px;
            font-weight: 700;
            font-size: 0.9rem;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        /* --- ACCOUNTANT SUMMARY CARDS --- */
        .acc-summary-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; margin-bottom: 2rem; }
        .acc-card { background: rgba(30, 41, 59, 0.6); border: 1px solid rgba(255,255,255,0.1); border-radius: 16px; padding: 1.5rem; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); position: relative; overflow: hidden;}
        .acc-card h4 { font-size: 0.8rem; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 10px; color: #cbd5e1; }
        .acc-val { font-size: 1.6rem; font-weight: 800; margin-bottom: 0.25rem; color: #fff; }
        .acc-sub { font-size: 0.8rem; font-weight: 600; text-transform: uppercase; color: #94a3b8;}
        
        .c-inwards { border-top: 4px solid #10b981; } .c-inwards .acc-val { color: #10b981; }
        .c-outwards { border-top: 4px solid #f59e0b; } .c-outwards .acc-val { color: #f59e0b; }
        .c-variance { border-top: 4px solid #3b82f6; } .c-variance .acc-val { color: #3b82f6; }
        .c-adjust { border-top: 4px solid #a855f7; } 
        .c-adj-net { border-top: 4px solid #ec4899; } .c-adj-net .acc-val { color: #ec4899; }
        .c-stock { border-top: 4px solid #06b6d4; } .c-stock .acc-val { color: #06b6d4; }

        /* --- FILTER BAR --- */
        .filter-box { background: rgba(15, 23, 42, 0.6); border: 1px solid #334155; padding: 1.5rem; border-radius: 16px; margin-bottom: 2rem; }
        .filter-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; align-items: end; }
        .form-group label { display: block; font-size: 0.75rem; color: #94a3b8; margin-bottom: 0.4rem; font-weight: 600; text-transform: uppercase; }
        .form-input { width: 100%; padding: 10px; background: #0f172a; border: 1px solid #334155; color: white; border-radius: 8px; font-size: 0.9rem; outline: none; }
        .form-input:focus { border-color: #06b6d4; }
        .form-input:disabled { opacity: 0.6; cursor: not-allowed; }
        
        .btn-group { display: flex; gap: 10px; flex-wrap: wrap; }
        .action-bar { margin-top: 1.5rem; display: flex; gap: 10px; justify-content: flex-end; flex-wrap: wrap; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 1rem; }
        .btn { padding: 10px 20px; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; text-decoration: none; font-size: 0.9rem; transition: transform 0.1s; white-space: nowrap; }
        .btn:active { transform: scale(0.98); }
        .btn-primary { background: #0891b2; color: white; }
        .btn-outline { background: transparent; border: 1px solid #475569; color: #cbd5e1; }
        .btn-pdf { background: #ef4444; color: white; } .btn-excel { background: #10b981; color: white; } .btn-csv { background: #f59e0b; color: white; }

        /* --- TABLES --- */
        .group-container { background: rgba(30, 41, 59, 0.4); border-radius: 12px; border: 1px solid #334155; margin-bottom: 1.5rem; overflow: hidden; }
        .group-header { background: rgba(15, 23, 42, 0.8); padding: 1rem 1.5rem; font-weight: 700; color: #67e8f9; border-bottom: 1px solid #334155; font-size: 1.1rem; display: flex; align-items: center; gap: 10px; }
        .group-header.warehouse { color: #10b981; }
        .group-header.adjustments { color: #c084fc; background: rgba(168, 85, 247, 0.15); }
        
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 800px; }
        th { background: rgba(15, 23, 42, 0.4); color: #94a3b8; text-align: left; padding: 0.8rem 1.5rem; font-size: 0.8rem; text-transform: uppercase; border-bottom: 1px solid #334155; }
        td { padding: 0.8rem 1.5rem; border-bottom: 1px solid rgba(255,255,255,0.05); font-size: 0.95rem; color: #e2e8f0; }
        tr:last-child td { border-bottom: none; }
        .t-right { text-align: right; }
        
        .row-total { background: rgba(6, 182, 212, 0.1); font-weight: bold; border-top: 1px solid #334155 !important; }
        .row-total td { color: #fff; }
        
        .empty-state { text-align: center; padding: 4rem; color: #64748b; background: rgba(30, 41, 59, 0.4); border-radius: 12px; border: 1px solid #334155; }
        
        .badge-add { background: rgba(16, 185, 129, 0.2); color: #34d399; padding: 2px 8px; border-radius: 4px; font-size: 0.8rem; font-weight: bold;}
        .badge-deduct { background: rgba(239, 68, 68, 0.2); color: #f87171; padding: 2px 8px; border-radius: 4px; font-size: 0.8rem; font-weight: bold;}

        @media (max-width: 900px) {
            .container { padding: 1rem; }
            .header { text-align: left; }
            .acc-summary-grid { grid-template-columns: 1fr 1fr; }
            .filter-grid { grid-template-columns: 1fr; }
            .action-bar { flex-direction: column; } .action-bar .btn { width: 100%; justify-content: center; }

            /* Mobile Table */
            .table-wrap { border: none; overflow: visible; }
            table, thead, tbody, th, td, tr { display: block; width: 100%; }
            thead { display: none; }
            tr { background: rgba(15, 23, 42, 0.4); border: 1px solid #475569; border-radius: 8px; margin-bottom: 1rem; padding: 1rem; }
            td { display: flex; justify-content: space-between; padding: 0.5rem 0; border-bottom: 1px dashed rgba(255,255,255,0.1); text-align: right; }
            td:last-child { border-bottom: none; }
            td::before { content: attr(data-label); font-weight: 700; color: #94a3b8; font-size: 0.8rem; text-transform: uppercase; margin-right: 1rem; text-align: left; }
            .row-total { background: rgba(59, 130, 246, 0.15); border-color: #3b82f6; }
        }
        @media (max-width: 600px) {
            .acc-summary-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<div class="container">
    
    <a href="reports.php" class="back-link">
        <i class="fa-solid fa-arrow-left"></i> Back to Reports Dashboard
    </a>

    <div class="header">
        <h1 class="title">Vaccines Usage & Ledger Report</h1>
        <p class="subtitle">Accountancy overview of vaccine purchases, administration, adjustments, and live balances.</p>
        <div class="location-badge">
            <i class="fa-solid fa-location-dot"></i> <?= htmlspecialchars($current_location_name) ?>
        </div>
    </div>

    <div class="acc-summary-grid">
        <div class="acc-card c-inwards">
            <h4>📥 Confirmed Purchases (IN)</h4>
            <div class="acc-val"><?= number_format($purchased_qty, 2) ?> doses</div>
            <div class="acc-sub">₱ <?= number_format($purchased_cost, 2) ?></div>
        </div>
        
        <div class="acc-card c-outwards">
            <h4>📤 Total Administered (OUT)</h4>
            <div class="acc-val"><?= number_format($grand_total_used, 2) ?> doses</div>
            <div class="acc-sub">₱ <?= number_format($grand_total_cost, 2) ?></div>
        </div>

        <div class="acc-card c-variance">
            <h4>⚖️ Raw Period Net</h4>
            <?php 
                $var_qty = $purchased_qty - $grand_total_used;
                $var_cost = $purchased_cost - $grand_total_cost;
            ?>
            <div class="acc-val"><?= number_format($var_qty, 2) ?> doses</div>
            <div class="acc-sub">Strictly IN minus OUT</div>
        </div>

        <div class="acc-card c-adjust">
            <h4>🔧 Net Adjustments</h4>
            <?php 
                $adj_color = $net_adjustments < 0 ? '#ef4444' : ($net_adjustments > 0 ? '#10b981' : '#cbd5e1');
                $adj_sign = $net_adjustments > 0 ? '+' : '';
            ?>
            <div class="acc-val" style="color:<?= $adj_color ?>;"><?= $adj_sign . number_format($net_adjustments, 2) ?> doses</div>
            <div class="acc-sub">Add: <?= number_format($total_added, 2) ?> | Ded: <?= number_format($total_deducted, 2) ?></div>
        </div>

        <div class="acc-card c-adj-net">
            <h4>🎯 Adjusted Period Net</h4>
            <?php 
                // Formula: Purchases - Consumed + Net Adjustments
                $expected_net = $var_qty + $net_adjustments;
            ?>
            <div class="acc-val"><?= number_format($expected_net, 2) ?> doses</div>
            <div class="acc-sub">IN - OUT + ADJ</div>
        </div>

        <div class="acc-card c-stock">
            <h4>📦 Live Warehouse Stock</h4>
            <div class="acc-val"><?= number_format($current_stock, 2) ?> doses</div>
            <div class="acc-sub">All-Time Current Balance</div>
        </div>
    </div>

    <div class="filter-box">
        <form method="GET" id="filterForm">
            <div class="filter-grid">
                
                <div class="form-group">
                    <label>Date From</label>
                    <input type="text" name="date_from" class="form-input date-picker" value="<?= htmlspecialchars($date_from) ?>" placeholder="Start Date">
                </div>
                
                <div class="form-group">
                    <label>Date To</label>
                    <input type="text" name="date_to" class="form-input date-picker" value="<?= htmlspecialchars($date_to) ?>" placeholder="End Date">
                </div>

                <div class="form-group">
                    <label>Location</label>
                    <select id="f_loc" name="f_loc" class="form-input" onchange="loadBuildings()" <?php echo ($USER_LOCATION_ != 1000) ? 'style="pointer-events: none; opacity: 0.7; background-color: #1e293b;"' : ''; ?>>
                        <?php if($USER_LOCATION_ == 1000): ?>
                            <option value="">All Locations</option>
                        <?php endif; ?>
                        <?php foreach($locations as $loc): ?>
                            <option value="<?= $loc['LOCATION_ID'] ?>" <?= $f_loc == $loc['LOCATION_ID'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($loc['LOCATION_NAME']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Building</label>
                    <select id="f_bld" name="f_bld" class="form-input" onchange="loadPens()" <?= empty($f_loc) ? 'disabled' : '' ?>>
                        <option value="">All Buildings</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Pen</label>
                    <select id="f_pen" name="f_pen" class="form-input" onchange="loadAnimals()" <?= empty($f_bld) ? 'disabled' : '' ?>>
                        <option value="">All Pens</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Animal Tag (Optional)</label>
                    <select id="f_animal" name="f_animal" class="form-input" <?= empty($f_pen) ? 'disabled' : '' ?>>
                        <option value="">All Animals</option>
                    </select>
                </div>
                
                <div class="form-group" style="grid-column: 1 / -1;">
                    <div class="btn-group">
                        <button type="submit" class="btn btn-primary">Apply Filters</button>
                        <a href="vaccines_usage_report.php" class="btn btn-outline">Reset</a>
                    </div>
                </div>
            </div>
            
            <?php if(!empty($grouped_data) || $purchased_qty > 0 || !empty($adjustments_data)): ?>
            <div class="action-bar">
                <button type="button" class="btn btn-pdf" onclick="exportPDF()"><i class="fa-solid fa-file-pdf"></i> Export PDF</button>
                <button type="button" class="btn btn-excel" onclick="exportExcel()"><i class="fa-solid fa-file-excel"></i> Export Excel</button>
                <button type="button" class="btn btn-csv" onclick="exportCSV()"><i class="fa-solid fa-file-csv"></i> Export CSV</button>
            </div>
            <?php endif; ?>
        </form>
    </div>

    <?php if(empty($grouped_data) && $purchased_qty == 0 && empty($adjustments_data)): ?>
        <div class="empty-state">
            <h2 style="margin-bottom:10px;">No Vaccine Activity Found</h2>
            <p>No purchases, usage, or adjustments recorded for this date range and location.</p>
        </div>
    <?php else: ?>
        
        <?php foreach ($grouped_data as $header => $group): ?>
            <div class="group-container">
                <div class="group-header">
                    <i class="fa-solid fa-syringe"></i> <?= htmlspecialchars($header) ?>
                </div>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Vaccine Name</th>
                                <th class="t-right" style="color:#10b981;">Location Purch. (IN)</th>
                                <th class="t-right">Doses Used (OUT)</th>
                                <th class="t-right">Used Cost</th>
                                <th class="t-right">Date Range</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($group['items'] as $item): ?>
                                <tr>
                                    <td data-label="Vaccine Name" style="font-weight:600; color:#fff;"><?= htmlspecialchars($item['ITEM_NAME']) ?></td>
                                    
                                    <td data-label="Purchased (IN)" class="t-right" style="color:#10b981;">
                                        <?= number_format($item['PURCHASED_QTY'], 2) ?> doses
                                    </td>
                                    
                                    <td data-label="Doses Used (OUT)" class="t-right" style="color:#0891b2; font-weight:bold;"><?= number_format($item['TOTAL_USED'], 2) ?> doses</td>
                                    <td data-label="Used Cost" class="t-right" style="color:#fbbf24; font-family:monospace;">₱<?= number_format($item['TOTAL_COST'], 2) ?></td>
                                    <td data-label="Date Range" class="t-right" style="font-size:0.85rem; color:#94a3b8;">
                                        <?= $display_date_range ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <tr class="row-total">
                                <td data-label="Summary">SUBTOTAL</td>
                                <td data-label="Purchased (IN)" class="t-right" style="color:#94a3b8; font-weight:normal;">-</td>
                                <td data-label="Doses Used (OUT)" class="t-right"><?= number_format($group['sub_used'], 2) ?> doses</td>
                                <td data-label="Used Cost" class="t-right">₱<?= number_format($group['sub_cost'], 2) ?></td>
                                <td data-label="Date Range" class="t-right">-</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endforeach; ?>

        <?php if(!empty($adjustments_data)): ?>
            <div class="group-container" style="border-color: #a855f7;">
                <div class="group-header adjustments">
                    <i class="fa-solid fa-wrench"></i> INVENTORY ADJUSTMENTS (VACCINES)
                </div>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Location</th>
                                <th>Vaccine Name</th>
                                <th>Type</th>
                                <th class="t-right">Doses Adjusted</th>
                                <th>Reason / Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($adjustments_data as $adj): ?>
                                <tr>
                                    <td data-label="Date" style="color:#94a3b8; font-size:0.85rem;">
                                        <?= $adj['TRANSACTION_DATE_FMT'] ?>
                                    </td>
                                    <td data-label="Location" style="color:#93c5fd; font-weight:600;"><?= htmlspecialchars($adj['LOCATION_NAME']) ?></td>
                                    <td data-label="Vaccine Name" style="font-weight:600; color:#fff;"><?= htmlspecialchars($adj['ITEM_NAME']) ?></td>
                                    <td data-label="Type">
                                        <?php if(strtolower($adj['ADJUSTMENT_TYPE']) == 'add'): ?>
                                            <span class="badge-add">Addition</span>
                                        <?php else: ?>
                                            <span class="badge-deduct">Deduction</span>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Doses Adjusted" class="t-right" style="font-weight:bold; color: <?= strtolower($adj['ADJUSTMENT_TYPE']) == 'add' ? '#34d399' : '#f87171' ?>;">
                                        <?= strtolower($adj['ADJUSTMENT_TYPE']) == 'add' ? '+' : '-' ?><?= number_format($adj['QUANTITY'], 2) ?> doses
                                    </td>
                                    <td data-label="Reason">
                                        <div style="color:#e2e8f0;"><?= htmlspecialchars($adj['REASON']) ?></div>
                                        <div style="font-size:0.8rem; color:#64748b; font-style:italic;"><?= htmlspecialchars($adj['REMARKS']) ?></div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <?php 
        $has_unused = false;
        foreach($purchases_by_item as $fName => $pData) {
            if(!isset($pData['processed'])) { $has_unused = true; break; }
        }
        if($has_unused):
        ?>
            <div class="group-container" style="border-color: #10b981;">
                <div class="group-header warehouse">
                    <i class="fa-solid fa-warehouse"></i> WAREHOUSE STOCK (Purchased but 0 Administered)
                </div>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Location</th>
                                <th>Vaccine Name</th>
                                <th class="t-right" style="color:#10b981;">Purchased (IN)</th>
                                <th class="t-right">Doses Used (OUT)</th>
                                <th class="t-right">Purchased Cost</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($purchases_by_item as $fName => $pData): ?>
                                <?php if(!isset($pData['processed'])): ?>
                                <tr>
                                    <td data-label="Location" style="color:#93c5fd; font-weight:600;"><?= htmlspecialchars($pData['location_name']) ?></td>
                                    <td data-label="Vaccine Name" style="font-weight:600; color:#fff;"><?= htmlspecialchars($pData['original_name']) ?></td>
                                    <td data-label="Purchased (IN)" class="t-right" style="color:#10b981; font-weight:bold;">
                                        <?= number_format($pData['qty'], 2) ?> doses
                                    </td>
                                    <td data-label="Doses Used (OUT)" class="t-right" style="color:#64748b;">0.00 doses</td>
                                    <td data-label="Purchased Cost" class="t-right" style="color:#fbbf24; font-family:monospace;">₱<?= number_format($pData['cost'], 2) ?></td>
                                </tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <div class="group-container" style="border-color: #0891b2;">
            <div class="group-header" style="background: rgba(8, 145, 178, 0.2); color: #fff;">
                <i class="fa-solid fa-chart-pie"></i> GRAND TOTAL (Filtered Outwards)
            </div>
            <div class="table-wrap">
                <table>
                    <tbody>
                        <tr class="row-total" style="font-size: 1.1rem; background: rgba(8, 145, 178, 0.1);">
                            <td data-label="Summary" style="color: #22d3ee;">OVERALL ADMINISTERED</td>
                            <td data-label="Used Doses (OUT)" class="t-right" style="color: #22d3ee;"><?= number_format($grand_total_used, 2) ?> doses</td>
                            <td data-label="Used Cost" class="t-right" style="color: #fbbf24;">₱<?= number_format($grand_total_cost, 2) ?></td>
                            <td data-label="Date Range" class="t-right">-</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

    <?php endif; ?>

</div>

<script>
    // Initialize Flatpickr for Date Inputs
    document.addEventListener('DOMContentLoaded', () => {
        flatpickr(".date-picker", {
            dateFormat: "Y-m-d", // Value submitted to PHP
            altInput: true,      // Visual input
            altFormat: "m/d/Y",  // mm/dd/yyyy format
            allowInput: true
        });
    });

    const API_URL = window.location.pathname.split("/").pop();

    // Auto-load dropdowns based on PHP state
    document.addEventListener('DOMContentLoaded', async () => {
        const loc = document.getElementById('f_loc').value;
        const bld = "<?= $f_bld ?>";
        const pen = "<?= $f_pen ?>";
        const ani = "<?= $f_ani ?>";

        if (loc) {
            await loadBuildings(loc, bld);
            if (bld) {
                await loadPens(bld, pen);
                if (pen) {
                    await loadAnimals(pen, ani);
                }
            }
        }
    });

    async function fetchJson(params) {
        try { return await (await fetch(`${API_URL}${params}`)).json(); } 
        catch(e) { return []; }
    }

    function resetSelect(id, defaultText) {
        const el = document.getElementById(id);
        el.innerHTML = `<option value="">${defaultText}</option>`;
        el.disabled = true;
    }

    async function loadBuildings(preLoc = null, preBld = null) {
        const id = preLoc || document.getElementById('f_loc').value;
        resetSelect('f_bld', 'All Buildings'); 
        resetSelect('f_pen', 'All Pens'); 
        resetSelect('f_animal', 'All Animals');
        if(!id) return;

        const data = await fetchJson(`?action=get_buildings&loc_id=${id}`);
        const el = document.getElementById('f_bld');
        data.forEach(item => {
            const isSelected = (preBld == item.BUILDING_ID) ? 'selected' : '';
            el.innerHTML += `<option value="${item.BUILDING_ID}" ${isSelected}>${item.BUILDING_NAME}</option>`;
        });
        el.disabled = false;
    }

    async function loadPens(preBld = null, prePen = null) {
        const id = preBld || document.getElementById('f_bld').value;
        resetSelect('f_pen', 'All Pens'); 
        resetSelect('f_animal', 'All Animals');
        if(!id) return;

        const data = await fetchJson(`?action=get_pens&bldg_id=${id}`);
        const el = document.getElementById('f_pen');
        data.forEach(item => {
            const isSelected = (prePen == item.PEN_ID) ? 'selected' : '';
            el.innerHTML += `<option value="${item.PEN_ID}" ${isSelected}>${item.PEN_NAME}</option>`;
        });
        el.disabled = false;
    }

    async function loadAnimals(prePen = null, preAni = null) {
        const id = prePen || document.getElementById('f_pen').value;
        resetSelect('f_animal', 'All Animals');
        if(!id) return;
        
        const data = await fetchJson(`?action=get_animals&pen_id=${id}`);
        const el = document.getElementById('f_animal');
        data.forEach(item => {
            const isSelected = (preAni == item.ANIMAL_ID) ? 'selected' : '';
            el.innerHTML += `<option value="${item.ANIMAL_ID}" ${isSelected}>${item.TAG_NO}</option>`;
        });
        el.disabled = false;
    }

    // --- EXPORT LOGIC ---
    const rawData = <?php echo json_encode($grouped_data); ?>;
    const purchasesData = <?php echo json_encode($purchases_by_item); ?>;
    const adjustmentsData = <?php echo json_encode($adjustments_data); ?>;
    
    const grandTotals = { used: "<?= number_format($grand_total_used, 2) ?>", cost: "<?= number_format($grand_total_cost, 2) ?>" };
    const accSummary = {
        purchQty: "<?= number_format($purchased_qty, 2) ?>", purchCost: "<?= number_format($purchased_cost, 2) ?>",
        varQty: "<?= number_format($var_qty, 2) ?>", varCost: "<?= number_format($var_cost, 2) ?>",
        netAdj: "<?= number_format($net_adjustments, 2) ?>", expectedNet: "<?= number_format($expected_net, 2) ?>",
        stock: "<?= number_format($current_stock, 2) ?>"
    };
    const currentLocationName = "<?= htmlspecialchars($current_location_name) ?>";
    const reportDateRange = "<?= $display_date_range ?>";

    function generateExportData() {
        const rows = [];
        
        // Add Accounting Header
        rows.push(['--- ACCOUNTANCY SUMMARY ---', '', '', '', '']);
        rows.push(['Confirmed Purchases (IN)', `${accSummary.purchQty} doses`, `PHP ${accSummary.purchCost}`, '', '']);
        rows.push(['Total Administered (OUT)', `${grandTotals.used} doses`, `PHP ${grandTotals.cost}`, '', '']);
        rows.push(['Raw Period Net (IN - OUT)', `${accSummary.varQty} doses`, `PHP ${accSummary.varCost}`, '', '']);
        rows.push(['Net Adjustments (ADJ)', `${accSummary.netAdj} doses`, '', '', '']);
        rows.push(['Adjusted Period Net (IN - OUT + ADJ)', `${accSummary.expectedNet} doses`, '', '', '']);
        rows.push(['Current Warehouse Stock', `${accSummary.stock} doses`, '', '', '']);
        rows.push(['', '', '', '', '']);
        
        // Detailed Usage Breakdown
        rows.push(['--- DETAILED USAGE BREAKDOWN ---', '', '', '', '']);
        for (const [header, group] of Object.entries(rawData)) {
            rows.push([`>>> ${header.toUpperCase()} <<<`, '', '', '', '']); 
            group.items.forEach(i => {
                rows.push([
                    i.ITEM_NAME, 
                    `${parseFloat(i.PURCHASED_QTY).toFixed(2)} doses`, 
                    `${parseFloat(i.TOTAL_USED).toFixed(2)} doses`, 
                    `PHP ${parseFloat(i.TOTAL_COST).toFixed(2)}`, 
                    reportDateRange
                ]);
            });
            rows.push(['SUBTOTAL', '-', `${parseFloat(group.sub_used).toFixed(2)} doses`, `PHP ${parseFloat(group.sub_cost).toFixed(2)}`, '']);
            rows.push(['', '', '', '', '']); // Spacing
        }

        // Adjustments Table
        if(adjustmentsData && adjustmentsData.length > 0) {
            rows.push(['--- INVENTORY ADJUSTMENTS ---', '', '', '', '']);
            rows.push(['Date', 'Location & Vaccine Name', 'Type', 'Doses Adjusted', 'Reason']);
            adjustmentsData.forEach(adj => {
                const typeStr = adj.ADJUSTMENT_TYPE.toLowerCase() === 'add' ? 'Addition (+)' : 'Deduction (-)';
                rows.push([
                    adj.TRANSACTION_DATE_FMT,
                    `[${adj.LOCATION_NAME}] ${adj.ITEM_NAME}`,
                    typeStr,
                    `${parseFloat(adj.QUANTITY).toFixed(2)} doses`,
                    adj.REASON
                ]);
            });
            rows.push(['', '', '', '', '']); // Spacing
        }

        // Add unused purchases
        let hasUnused = false;
        for(let key in purchasesData) {
            if(!purchasesData[key].processed) {
                if(!hasUnused) {
                    rows.push(['--- WAREHOUSE STOCK (Purchased but 0 Administered) ---', '', '', '', '']);
                    hasUnused = true;
                }
                rows.push([
                    `[${purchasesData[key].location_name}] ${purchasesData[key].original_name}`,
                    `${parseFloat(purchasesData[key].qty).toFixed(2)} doses`,
                    '0.00 doses',
                    `PHP ${parseFloat(purchasesData[key].cost).toFixed(2)}`,
                    reportDateRange
                ]);
            }
        }

        return rows;
    }

    function exportPDF() {
        const doc = new window.jspdf.jsPDF();
        doc.setFontSize(16);
        doc.setTextColor(8, 145, 178);
        doc.text("Vaccines Usage & Ledger Report", 14, 15);
        
        doc.setFontSize(10);
        doc.setTextColor(100);
        let now = new Date();
        let formattedNow = `${String(now.getMonth() + 1).padStart(2, '0')}/${String(now.getDate()).padStart(2, '0')}/${now.getFullYear()} ${now.toLocaleTimeString()}`;
        doc.text(`Generated: ${formattedNow}`, 14, 22);
        doc.text(`Date Range: ${reportDateRange}`, 14, 28);
        doc.text(`Location: ${currentLocationName}`, 14, 34);

        const rows = generateExportData();

        doc.autoTable({
            head: [['Name / Grouping / Date', 'Purchases (IN) / Loc', 'Used (OUT) / Type', 'Cost / Doses', 'Date Range / Reason']],
            body: rows,
            startY: 40,
            styles: { fontSize: 8 },
            headStyles: { fillColor: [8, 145, 178] },
            didParseCell: function (data) {
                if (data.row.raw[0].startsWith('---')) {
                    data.cell.styles.fontStyle = 'bold';
                    data.cell.styles.fillColor = [51, 65, 85];
                    data.cell.styles.textColor = [255, 255, 255];
                }
                if (data.row.raw[0].startsWith('>>>')) {
                    data.cell.styles.fontStyle = 'bold';
                    data.cell.styles.fillColor = [240, 240, 240];
                    data.cell.styles.textColor = [0, 0, 0];
                    if(data.row.raw[0].includes('WAREHOUSE')) {
                        data.cell.styles.textColor = [16, 185, 129]; // Green text for warehouse
                    }
                }
                if (data.row.raw[0] === 'SUBTOTAL') {
                    data.cell.styles.fontStyle = 'bold';
                    data.cell.styles.textColor = [8, 145, 178];
                }
            }
        });

        doc.save('Vaccines_Ledger_Report.pdf');
    }

    function exportExcel() {
        const rows = generateExportData();
        rows.unshift([`Location: ${currentLocationName}`, '', '', '', '']); 
        rows.unshift(['Name / Grouping', 'Purchased (IN)', 'Used (OUT)', 'Cost', 'Date / Info']); 
        
        const ws = XLSX.utils.aoa_to_sheet(rows);
        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, "Vaccines Ledger");
        
        let now = new Date();
        let filenameDate = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
        XLSX.writeFile(wb, `Vaccines_Ledger_Report_${filenameDate}.xlsx`);
    }

    function exportCSV() {
        let csvContent = "data:text/csv;charset=utf-8,";
        csvContent += `Location: ${currentLocationName}\n\n`;
        
        const rows = generateExportData();
        rows.forEach(r => {
            const rowStr = r.map(e => `"${e}"`).join(",");
            csvContent += rowStr + "\n";
        });

        let now = new Date();
        let filenameDate = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
        const encodedUri = encodeURI(csvContent);
        const link = document.createElement("a");
        link.setAttribute("href", encodedUri);
        link.setAttribute("download", `Vaccines_Ledger_Report_${filenameDate}.csv`);
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
</script>

</body>
</html>