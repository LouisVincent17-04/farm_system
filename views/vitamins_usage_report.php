<?php
// views/vitamins_usage_report.php
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

$display_date_range = ($date_from && $date_to) 
    ? date('m/d/Y', strtotime($q_date_from)) . " - " . date('m/d/Y', strtotime($q_date_to)) 
    : "All Time";

// Auto-assign location filter if user is restricted
if ($USER_LOCATION_ != 1000) {
    $f_loc = $USER_LOCATION_;
}

// Fetch Locations for UI
if ($USER_LOCATION_ != 1000) {
    $stmt = $conn->prepare("SELECT * FROM locations WHERE LOCATION_ID = ? ORDER BY LOCATION_NAME");
    $stmt->execute([$USER_LOCATION_]);
    $locations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $locations = $conn->query("SELECT * FROM locations ORDER BY LOCATION_NAME")->fetchAll(PDO::FETCH_ASSOC);
}

// Determine Current Location Name for Header
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

try {
    // --- A. FETCH CONFIRMED PURCHASES (INWARDS) FROM `items` TABLE ---
    $purch_params = [];
    $purch_where = "";
    
    if ($date_from && $date_to) {
        $purch_where .= " AND COALESCE(NULLIF(DATE(i.DATE_OF_PURCHASE), ''), DATE(i.CREATED_AT)) BETWEEN :df AND :dt";
        $purch_params[':df'] = $q_date_from;
        $purch_params[':dt'] = $q_date_to;
    }

    if ($f_loc) {
        $purch_where .= " AND i.LOCATION_ID = :ploc";
        $purch_params[':ploc'] = $f_loc;
    }

    // Specific fetch for Vitamin Item Type (10)
    $purch_sql = "SELECT 
                    SUM(COALESCE(i.TOTAL_QTY, (i.QUANTITY * i.ITEM_NET_WEIGHT), i.QUANTITY)) as purchased_qty, 
                    SUM(i.TOTAL_COST) as purchased_cost 
                  FROM items i
                  WHERE i.STATUS = 1 
                  AND i.ITEM_TYPE_ID = 10
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
                  AND i.ITEM_TYPE_ID = 10
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
        $where .= " AND DATE(vt.TRANSACTION_DATE) BETWEEN :df AND :dt";
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
                COALESCE(v.SUPPLY_NAME, 'Deleted Vitamin') AS ITEM_NAME,
                SUM(vt.QUANTITY_USED) as TOTAL_USED,
                SUM(vt.TOTAL_COST) as TOTAL_COST,
                MIN(DATE(vt.TRANSACTION_DATE)) as MIN_DATE,
                MAX(DATE(vt.TRANSACTION_DATE)) as MAX_DATE
            FROM vitamins_supplements_transactions vt
            LEFT JOIN vitamins_supplements v ON vt.ITEM_ID = v.SUPPLY_ID
            LEFT JOIN animal_records a ON vt.ANIMAL_ID = a.ANIMAL_ID
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
    }

    // --- C. FETCH INVENTORY ADJUSTMENTS ---
    $adj_params = [];
    $adj_where = " WHERE ia.CATEGORY = 'vitamin' ";
    
    if ($date_from && $date_to) {
        $adj_where .= " AND DATE(ia.TRANSACTION_DATE) BETWEEN :df AND :dt ";
        $adj_params[':df'] = $q_date_from;
        $adj_params[':dt'] = $q_date_to;
    }
    
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
                LEFT JOIN vitamins_supplements v ON ia.REF_ID = v.SUPPLY_ID
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
    $stock_sql = "SELECT SUM(TOTAL_STOCK) as current_stock FROM vitamins_supplements $stock_where";
    $stmt = $conn->prepare($stock_sql);
    $stmt->execute($stock_params);
    $stock_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $current_stock = $stock_data['current_stock'] ?? 0;

} catch (Exception $e) {
    error_log($e->getMessage());
}

// Count active filters
$active_filters = 0;
if ($date_from || $date_to) $active_filters++;
if ($f_loc !== '' && $USER_LOCATION_ == 1000) $active_filters++;
if ($f_bld !== '') $active_filters++;
if ($f_pen !== '') $active_filters++;
if ($f_ani !== '') $active_filters++;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Vitamins Usage & Accountancy | FarmPro</title>
    
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
            --cyan:           #06b6d4;
            --cyan-dim:       rgba(6,182,212,0.12);
            --blue:           #3b82f6;
            --blue-dim:       rgba(59,130,246,0.12);
            --purple:         #a855f7;
            --purple-dim:     rgba(168,85,247,0.12);
            --pink:           #ec4899;
            --pink-dim:       rgba(236,72,153,0.12);
            --amber:          #f59e0b;
            --amber-dim:      rgba(245,158,11,0.12);
            --red:            #f87171;
            --red-dim:        rgba(248,113,113,0.12);
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
            background-image: radial-gradient(ellipse 80% 50% at 50% -20%, rgba(16,185,129,0.06) 0%, transparent 60%);
        }
        .container { max-width: 1560px; margin: 0 auto; padding: 2rem 1.5rem; }

        /* ─── TOP BAR & HEADER ─── */
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

        .page-header { margin-bottom: 2.5rem; display: flex; justify-content: space-between; align-items: flex-end; flex-wrap: wrap; gap: 1rem;}
        .header-info h1 {
            font-size: clamp(1.6rem, 3vw, 2.2rem); font-weight: 700;
            color: var(--text-primary); letter-spacing: -0.03em; line-height: 1.1; margin-bottom: 0.25rem;
        }
        .header-info h1 span {
            background: linear-gradient(135deg, var(--emerald), #059669);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }
        .header-info p { color: var(--text-secondary); font-size: 0.95rem; }

        /* ─── ACCOUNTING STAT CARDS ─── */
        .acc-summary-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.25rem; margin-bottom: 2.5rem;
        }
        .acc-card {
            background: var(--bg-surface); border: 1px solid var(--border);
            border-radius: var(--radius-lg); padding: 1.5rem;
            position: relative; overflow: hidden; transition: transform var(--transition);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        .acc-card:hover { transform: translateY(-2px); }
        .acc-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px; }
        
        .c-inwards::before  { background: var(--cyan); }
        .c-outwards::before { background: var(--amber); }
        .c-variance::before { background: var(--blue); }
        .c-adjust::before   { background: var(--purple); }
        .c-adj-net::before  { background: var(--pink); }
        .c-stock::before    { background: var(--emerald); }

        .acc-header { display: flex; align-items: center; gap: 8px; margin-bottom: 0.75rem; }
        .acc-icon { width: 28px; height: 28px; border-radius: 6px; display: flex; align-items: center; justify-content: center; font-size: 0.8rem; }
        
        .c-inwards .acc-icon { background: var(--cyan-dim); color: var(--cyan); }
        .c-outwards .acc-icon { background: var(--amber-dim); color: var(--amber); }
        .c-variance .acc-icon { background: var(--blue-dim); color: var(--blue); }
        .c-adjust .acc-icon { background: var(--purple-dim); color: var(--purple); }
        .c-adj-net .acc-icon { background: var(--pink-dim); color: var(--pink); }
        .c-stock .acc-icon { background: var(--emerald-dim); color: var(--emerald); }

        .acc-title { font-size: 0.75rem; text-transform: uppercase; font-weight: 700; color: var(--text-secondary); letter-spacing: 0.05em;}
        .acc-val { font-family: var(--font-mono); font-size: 1.8rem; font-weight: 700; margin-bottom: 0.25rem; line-height: 1; }
        
        .c-inwards .acc-val { color: var(--cyan); }
        .c-outwards .acc-val { color: var(--amber); }
        .c-variance .acc-val { color: var(--blue); }
        .c-adj-net .acc-val { color: var(--pink); }
        .c-stock .acc-val { color: var(--emerald); }

        .acc-sub { font-size: 0.8rem; font-weight: 600; color: var(--text-muted); font-family: var(--font-mono);}

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

        .filter-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; align-items: end; }
        .form-group { display: flex; flex-direction: column; gap: 6px; }
        .form-label { font-size: 0.72rem; font-weight: 600; text-transform: uppercase; color: var(--text-secondary); letter-spacing: 0.05em; display: flex; align-items: center; gap: 5px; }
        .form-label.accent { color: var(--emerald); }

        .form-control, .form-select {
            width: 100%; padding: 0 12px; height: 40px; background: var(--bg-elevated);
            border: 1px solid var(--border); color: var(--text-primary);
            border-radius: var(--radius-md); font-size: 0.875rem; font-family: var(--font);
            outline: none; transition: border-color var(--transition), box-shadow var(--transition);
        }
        .form-select {
            appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2394a3b8' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
            background-repeat: no-repeat; background-position: right 12px center; cursor: pointer;
        }
        .form-control:focus, .form-select:focus { border-color: var(--emerald); box-shadow: 0 0 0 3px var(--emerald-glow); background: var(--bg-hover); }
        .form-select:disabled, .form-control:disabled { opacity: 0.5; cursor: not-allowed; }
        .input-row { display: grid; grid-template-columns: 1fr 1fr; gap: 6px; }

        /* Filter Actions */
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

        /* ─── TABLES ─── */
        .group-container {
            background: var(--bg-surface); border-radius: var(--radius-xl);
            border: 1px solid var(--border); margin-bottom: 2rem; overflow: hidden;
            box-shadow: var(--shadow-sm);
        }
        .group-header {
            background: var(--bg-elevated); padding: 1.25rem 1.5rem;
            font-weight: 700; color: var(--blue); border-bottom: 1px solid var(--border);
            font-size: 1rem; display: flex; align-items: center; gap: 10px;
        }
        .group-header.warehouse { color: var(--emerald); border-bottom-color: var(--emerald-dim); }
        .group-header.adjustments { color: var(--purple); background: var(--purple-dim); border-bottom-color: var(--border); }
        
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 900px; }
        thead th {
            background: rgba(0,0,0,0.2); color: var(--text-muted);
            font-size: 0.7rem; font-weight: 700; text-transform: uppercase;
            letter-spacing: 0.07em; padding: 12px 16px; text-align: left;
            border-bottom: 1px solid var(--border); white-space: nowrap;
        }
        tbody tr { border-bottom: 1px solid rgba(255,255,255,0.03); transition: background var(--transition); }
        tbody tr:last-child { border-bottom: none; }
        tbody tr:hover { background: rgba(255,255,255,0.02); }
        td { padding: 12px 16px; font-size: 0.85rem; color: var(--text-primary); vertical-align: middle; }

        .row-total { background: var(--bg-elevated); border-top: 2px solid var(--border) !important; }
        .row-total td { font-weight: 700; color: #fff; }

        /* Cell Formatting */
        .t-right { text-align: right; }
        .col-name { font-weight: 600; color: #fff; font-size: 0.95rem;}
        .val-mono { font-family: var(--font-mono); font-weight: 600; font-size: 0.85rem;}
        .val-money { font-family: var(--font-mono); font-weight: 600; color: var(--amber); }
        .text-green { color: var(--emerald); }
        .text-red { color: var(--red); }

        .badge {
            display: inline-flex; align-items: center; gap: 4px; padding: 3px 8px;
            border-radius: 6px; font-size: 0.7rem; font-weight: 700; letter-spacing: 0.03em; text-transform: uppercase;
        }
        .b-add { background: var(--emerald-dim); color: var(--emerald); border: 1px solid rgba(16,185,129,0.2); }
        .b-sub { background: var(--red-dim); color: var(--red); border: 1px solid rgba(248,113,113,0.2); }

        .empty-state { text-align: center; padding: 4rem 2rem; color: var(--text-muted); }
        .empty-state i { font-size: 2.5rem; margin-bottom: 1rem; opacity: 0.3; display: block; }
        .empty-state h3 { font-size: 1rem; color: var(--text-primary); margin-bottom: 0.5rem; font-weight: 600;}

        /* ─── RESPONSIVE ─── */
        @media (max-width: 900px) {
            .filter-grid { grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 768px) {
            .container { padding: 1rem; }
            .page-header { flex-direction: column; align-items: flex-start; }
            .acc-summary-grid { grid-template-columns: 1fr; }
            .filter-grid { grid-template-columns: 1fr; }
            .filter-footer { flex-direction: column; align-items: stretch; }
            .filter-footer-left, .filter-footer-right { justify-content: stretch; }
            .filter-footer .btn { flex: 1; justify-content: center; }

            /* Mobile Table to Cards */
            .table-wrap { border: none; background: transparent; overflow: visible; }
            table { min-width: 0; display: block; }
            thead { display: none; }
            tbody { display: block; }
            tbody tr {
                display: block; background: var(--bg-elevated);
                border: 1px solid var(--border); border-radius: var(--radius-lg);
                margin-bottom: 0.75rem; padding: 1.25rem; box-shadow: var(--shadow-sm);
            }
            td {
                display: flex; justify-content: space-between; align-items: center;
                gap: 1rem; padding: 7px 0; border-bottom: 1px solid rgba(255,255,255,0.03); text-align: right;
            }
            td:last-child { border-bottom: none; }
            td::before {
                content: attr(data-label); font-size: 0.7rem; font-weight: 700;
                text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted);
                white-space: nowrap; flex-shrink: 0; padding-top: 2px; text-align: left;
            }
            .row-total { background: var(--emerald-dim); border-color: rgba(16,185,129,0.3); }
        }
    </style>
</head>
<body>

<div class="container">

    <div class="top-bar">
        <a href="reports.php" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back to Reports
        </a>
        <span class="page-badge"><i class="fa-solid fa-scale-balanced"></i> Accountancy</span>
    </div>

    <div class="page-header">
        <div class="header-info">
            <h1 class="page-title">Vitamins Usage <span>&amp; Ledger</span></h1>
            <p class="page-subtitle">Financial and physical overview of vitamin purchases, administration, adjustments, and live balances.</p>
        </div>
    </div>

    <div class="acc-summary-grid">
        <div class="acc-card c-inwards">
            <div class="acc-header">
                <div class="acc-icon"><i class="fa-solid fa-arrow-down-to-line"></i></div>
                <div class="acc-title">Confirmed Purchases (IN)</div>
            </div>
            <div class="acc-val"><?= number_format($purchased_qty, 2) ?> <span style="font-size:1rem;color:var(--text-muted);">units</span></div>
            <div class="acc-sub">₱ <?= number_format($purchased_cost, 2) ?></div>
        </div>
        
        <div class="acc-card c-outwards">
            <div class="acc-header">
                <div class="acc-icon"><i class="fa-solid fa-arrow-up-from-bracket"></i></div>
                <div class="acc-title">Total Administered (OUT)</div>
            </div>
            <div class="acc-val"><?= number_format($grand_total_used, 2) ?> <span style="font-size:1rem;color:var(--text-muted);">units</span></div>
            <div class="acc-sub">₱ <?= number_format($grand_total_cost, 2) ?></div>
        </div>

        <div class="acc-card c-variance">
            <div class="acc-header">
                <div class="acc-icon"><i class="fa-solid fa-scale-unbalanced"></i></div>
                <div class="acc-title">Raw Period Net</div>
            </div>
            <?php 
                $var_qty = $purchased_qty - $grand_total_used;
                $var_cost = $purchased_cost - $grand_total_cost;
            ?>
            <div class="acc-val"><?= number_format($var_qty, 2) ?> <span style="font-size:1rem;color:var(--text-muted);">units</span></div>
            <div class="acc-sub" style="font-weight:400;font-family:var(--font);">Strictly IN minus OUT</div>
        </div>

        <div class="acc-card c-adjust">
            <div class="acc-header">
                <div class="acc-icon"><i class="fa-solid fa-wrench"></i></div>
                <div class="acc-title">Net Adjustments</div>
            </div>
            <?php 
                $adj_color = $net_adjustments < 0 ? 'var(--red)' : ($net_adjustments > 0 ? 'var(--emerald)' : 'var(--text-primary)');
                $adj_sign = $net_adjustments > 0 ? '+' : '';
            ?>
            <div class="acc-val" style="color:<?= $adj_color ?>;"><?= $adj_sign . number_format($net_adjustments, 2) ?> <span style="font-size:1rem;color:var(--text-muted);">units</span></div>
            <div class="acc-sub">Add: <?= number_format($total_added, 2) ?> | Ded: <?= number_format($total_deducted, 2) ?></div>
        </div>

        <div class="acc-card c-adj-net">
            <div class="acc-header">
                <div class="acc-icon"><i class="fa-solid fa-bullseye"></i></div>
                <div class="acc-title">Adjusted Period Net</div>
            </div>
            <?php $expected_net = $var_qty + $net_adjustments; ?>
            <div class="acc-val"><?= number_format($expected_net, 2) ?> <span style="font-size:1rem;color:var(--text-muted);">units</span></div>
            <div class="acc-sub" style="font-weight:400;font-family:var(--font);">IN - OUT + ADJ</div>
        </div>

        <div class="acc-card c-stock">
            <div class="acc-header">
                <div class="acc-icon"><i class="fa-solid fa-boxes-stacked"></i></div>
                <div class="acc-title">Live Warehouse Stock</div>
            </div>
            <div class="acc-val"><?= number_format($current_stock, 2) ?> <span style="font-size:1rem;color:var(--text-muted);">units</span></div>
            <div class="acc-sub" style="font-weight:400;font-family:var(--font);">All-Time Current Balance</div>
        </div>
    </div>

    <div class="filter-panel">
        <div class="filter-header" onclick="toggleFilters()" id="filterHeader">
            <div class="filter-header-left">
                <i class="fa-solid fa-sliders" style="color:var(--text-secondary); font-size:0.85rem;"></i>
                <span class="filter-header-title">Filters &amp; Scoping</span>
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
                        <label class="form-label accent"><i class="fa-solid fa-calendar-days"></i> Transaction Period</label>
                        <div class="input-row">
                            <input type="text" name="date_from" class="form-control date-picker" value="<?= htmlspecialchars($date_from) ?>" placeholder="Start Date">
                            <input type="text" name="date_to" class="form-control date-picker" value="<?= htmlspecialchars($date_to) ?>" placeholder="End Date">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label"><i class="fa-solid fa-map-pin"></i> Location</label>
                        <select id="f_loc" name="f_loc" class="form-select" onchange="loadBuildings()" <?php echo ($USER_LOCATION_ != 1000) ? 'disabled' : ''; ?>>
                            <?php if($USER_LOCATION_ == 1000): ?>
                                <option value="">All Locations</option>
                            <?php endif; ?>
                            <?php foreach($locations as $loc): ?>
                                <option value="<?= $loc['LOCATION_ID'] ?>" <?= $f_loc == $loc['LOCATION_ID'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($loc['LOCATION_NAME']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($USER_LOCATION_ != 1000): ?>
                            <input type="hidden" name="f_loc" value="<?= $USER_LOCATION_ ?>">
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label class="form-label"><i class="fa-solid fa-building"></i> Building</label>
                        <select id="f_bld" name="f_bld" class="form-select" onchange="loadPens()" <?= empty($f_loc) ? 'disabled' : '' ?>>
                            <option value="">All Buildings</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label"><i class="fa-solid fa-border-all"></i> Pen</label>
                        <select id="f_pen" name="f_pen" class="form-select" onchange="loadAnimals()" <?= empty($f_bld) ? 'disabled' : '' ?>>
                            <option value="">All Pens</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label"><i class="fa-solid fa-tag"></i> Animal Tag</label>
                        <select id="f_animal" name="f_animal" class="form-select" <?= empty($f_pen) ? 'disabled' : '' ?>>
                            <option value="">All Animals</option>
                        </select>
                    </div>

                </div>
            </form>
        </div>

        <div class="filter-footer">
            <div class="filter-footer-left">
                <a href="vitamins_usage_report.php" class="btn btn-ghost btn-sm">
                    <i class="fa-solid fa-rotate-left"></i> Reset
                </a>
                <button type="submit" form="filterForm" class="btn btn-primary btn-sm">
                    <i class="fa-solid fa-filter"></i> Apply Filters
                </button>
            </div>
            
            <?php if(!empty($grouped_data) || $purchased_qty > 0 || !empty($adjustments_data)): ?>
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
            <?php endif; ?>
        </div>
    </div>

    <?php if(empty($grouped_data) && $purchased_qty == 0 && empty($adjustments_data)): ?>
        <div class="empty-state">
            <i class="fa-solid fa-receipt"></i>
            <h3>No Vitamin Activity Found</h3>
            <p>No purchases, usage, or adjustments recorded for the selected criteria.</p>
        </div>
    <?php else: ?>
        
        <?php foreach ($grouped_data as $header => $group): ?>
            <div class="group-container">
                <div class="group-header">
                    <i class="fa-solid fa-diagram-project"></i> <?= htmlspecialchars($header) ?>
                </div>
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Vitamin Name</th>
                                <th class="t-right" style="color:var(--cyan);">Location Purch. (IN)</th>
                                <th class="t-right">Units Used (OUT)</th>
                                <th class="t-right">Used Cost</th>
                                <th class="t-right">Date Range</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($group['items'] as $item): ?>
                                <tr>
                                    <td data-label="Vitamin Name" class="col-name"><?= htmlspecialchars($item['ITEM_NAME']) ?></td>
                                    <td data-label="Purchased (IN)" class="t-right val-mono" style="color:var(--cyan);">
                                        <?= number_format($item['PURCHASED_QTY'], 2) ?> units
                                    </td>
                                    <td data-label="Units Used (OUT)" class="t-right val-mono" style="color:var(--emerald);">
                                        <?= number_format($item['TOTAL_USED'], 2) ?> units
                                    </td>
                                    <td data-label="Used Cost" class="t-right val-money">₱<?= number_format($item['TOTAL_COST'], 2) ?></td>
                                    <td data-label="Date Range" class="t-right" style="font-size:0.8rem; color:var(--text-muted);">
                                        <?= $display_date_range ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <tr class="row-total">
                                <td data-label="Summary">SUBTOTAL</td>
                                <td data-label="Purchased (IN)" class="t-right" style="color:var(--text-muted); font-weight:normal;">-</td>
                                <td data-label="Units Used (OUT)" class="t-right val-mono"><?= number_format($group['sub_used'], 2) ?> units</td>
                                <td data-label="Used Cost" class="t-right val-money">₱<?= number_format($group['sub_cost'], 2) ?></td>
                                <td data-label="Date Range" class="t-right">-</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endforeach; ?>

        <?php if(!empty($adjustments_data)): ?>
            <div class="group-container" style="border-color: var(--purple-dim);">
                <div class="group-header adjustments">
                    <i class="fa-solid fa-wrench"></i> INVENTORY ADJUSTMENTS (VITAMINS)
                </div>
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Location</th>
                                <th>Vitamin Name</th>
                                <th>Type</th>
                                <th class="t-right">Units Adjusted</th>
                                <th>Reason / Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($adjustments_data as $adj): ?>
                                <tr>
                                    <td data-label="Date" class="val-mono" style="font-size:0.8rem;"><?= $adj['TRANSACTION_DATE_FMT'] ?></td>
                                    <td data-label="Location" style="color:var(--blue); font-weight:600;"><?= htmlspecialchars($adj['LOCATION_NAME']) ?></td>
                                    <td data-label="Vitamin Name" class="col-name"><?= htmlspecialchars($adj['ITEM_NAME']) ?></td>
                                    <td data-label="Type">
                                        <?php if(strtolower($adj['ADJUSTMENT_TYPE']) == 'add'): ?>
                                            <span class="badge b-add">Addition</span>
                                        <?php else: ?>
                                            <span class="badge b-sub">Deduction</span>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Quantity" class="t-right val-mono <?= strtolower($adj['ADJUSTMENT_TYPE']) == 'add' ? 'text-green' : 'text-red' ?>">
                                        <?= strtolower($adj['ADJUSTMENT_TYPE']) == 'add' ? '+' : '-' ?><?= number_format($adj['QUANTITY'], 2) ?> units
                                    </td>
                                    <td data-label="Reason">
                                        <div style="color:var(--text-primary); font-weight:500; font-size:0.85rem;"><?= htmlspecialchars($adj['REASON']) ?></div>
                                        <div style="font-size:0.75rem; color:var(--text-muted); font-style:italic;"><?= htmlspecialchars($adj['REMARKS']) ?></div>
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
            <div class="group-container" style="border-color: var(--emerald-dim);">
                <div class="group-header warehouse">
                    <i class="fa-solid fa-warehouse"></i> WAREHOUSE STOCK (Purchased but 0 Administered)
                </div>
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Location</th>
                                <th>Vitamin Name</th>
                                <th class="t-right" style="color:var(--cyan);">Purchased (IN)</th>
                                <th class="t-right">Units Used (OUT)</th>
                                <th class="t-right">Purchased Cost</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($purchases_by_item as $fName => $pData): ?>
                                <?php if(!isset($pData['processed'])): ?>
                                <tr>
                                    <td data-label="Location" style="color:var(--blue); font-weight:600;"><?= htmlspecialchars($pData['location_name']) ?></td>
                                    <td data-label="Vitamin Name" class="col-name"><?= htmlspecialchars($pData['original_name']) ?></td>
                                    <td data-label="Purchased (IN)" class="t-right val-mono" style="color:var(--cyan);">
                                        <?= number_format($pData['qty'], 2) ?> units
                                    </td>
                                    <td data-label="Units Used (OUT)" class="t-right val-mono" style="color:var(--text-muted);">0.00 units</td>
                                    <td data-label="Purchased Cost" class="t-right val-money">₱<?= number_format($pData['cost'], 2) ?></td>
                                </tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <div class="group-container" style="border-color: var(--emerald);">
            <div class="group-header" style="background: rgba(16, 185, 129, 0.15); color: #fff;">
                <i class="fa-solid fa-chart-pie"></i> GRAND TOTAL (Filtered Outwards)
            </div>
            <div class="table-wrap">
                <table class="table">
                    <tbody>
                        <tr class="row-total" style="font-size: 1rem; background: rgba(16, 185, 129, 0.1);">
                            <td data-label="Summary" style="color: var(--emerald);">OVERALL ADMINISTERED</td>
                            <td data-label="Units Used (OUT)" class="t-right val-mono" style="color: var(--emerald);"><?= number_format($grand_total_used, 2) ?> units</td>
                            <td data-label="Used Cost" class="t-right val-money">₱<?= number_format($grand_total_cost, 2) ?></td>
                            <td data-label="Date Range" class="t-right" style="color:var(--text-muted);">-</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

    <?php endif; ?>

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

    // ─── EXPORT LOGIC ───
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
        rows.push(['Confirmed Purchases (IN)', `${accSummary.purchQty} units`, `PHP ${accSummary.purchCost}`, '', '']);
        rows.push(['Total Administered (OUT)', `${grandTotals.used} units`, `PHP ${grandTotals.cost}`, '', '']);
        rows.push(['Raw Period Net (IN - OUT)', `${accSummary.varQty} units`, `PHP ${accSummary.varCost}`, '', '']);
        rows.push(['Net Adjustments (ADJ)', `${accSummary.netAdj} units`, '', '', '']);
        rows.push(['Adjusted Period Net (IN - OUT + ADJ)', `${accSummary.expectedNet} units`, '', '', '']);
        rows.push(['Current Warehouse Stock', `${accSummary.stock} units`, '', '', '']);
        rows.push(['', '', '', '', '']);
        
        // Detailed Usage Breakdown
        rows.push(['--- DETAILED USAGE BREAKDOWN ---', '', '', '', '']);
        for (const [header, group] of Object.entries(rawData)) {
            rows.push([`>>> ${header.toUpperCase()} <<<`, '', '', '', '']); 
            group.items.forEach(i => {
                rows.push([
                    i.ITEM_NAME, 
                    `${parseFloat(i.PURCHASED_QTY).toFixed(2)} units`, 
                    `${parseFloat(i.TOTAL_USED).toFixed(2)} units`, 
                    `PHP ${parseFloat(i.TOTAL_COST).toFixed(2)}`, 
                    reportDateRange
                ]);
            });
            rows.push(['SUBTOTAL', '-', `${parseFloat(group.sub_used).toFixed(2)} units`, `PHP ${parseFloat(group.sub_cost).toFixed(2)}`, '']);
            rows.push(['', '', '', '', '']); // Spacing
        }

        // Adjustments Table
        if(adjustmentsData && adjustmentsData.length > 0) {
            rows.push(['--- INVENTORY ADJUSTMENTS ---', '', '', '', '']);
            rows.push(['Date', 'Location & Vitamin Name', 'Type', 'Units Adjusted', 'Reason']);
            adjustmentsData.forEach(adj => {
                const typeStr = adj.ADJUSTMENT_TYPE.toLowerCase() === 'add' ? 'Addition (+)' : 'Deduction (-)';
                rows.push([
                    adj.TRANSACTION_DATE_FMT,
                    `[${adj.LOCATION_NAME}] ${adj.ITEM_NAME}`,
                    typeStr,
                    `${parseFloat(adj.QUANTITY).toFixed(2)} units`,
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
                    `${parseFloat(purchasesData[key].qty).toFixed(2)} units`,
                    '0.00 units',
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
        doc.setTextColor(16, 185, 129); // Emerald
        doc.text("Vitamins Usage & Ledger Report", 14, 15);
        
        doc.setFontSize(10);
        doc.setTextColor(100);
        let now = new Date();
        let formattedNow = `${String(now.getMonth() + 1).padStart(2, '0')}/${String(now.getDate()).padStart(2, '0')}/${now.getFullYear()} ${now.toLocaleTimeString()}`;
        doc.text(`Generated: ${formattedNow}`, 14, 22);
        doc.text(`Date Range: ${reportDateRange}`, 14, 28);
        doc.text(`Location: ${currentLocationName}`, 14, 34);

        const rows = generateExportData();

        doc.autoTable({
            head: [['Name / Grouping / Date', 'Purchases (IN) / Loc', 'Used (OUT) / Type', 'Cost / Units', 'Date Range / Reason']],
            body: rows,
            startY: 40,
            styles: { fontSize: 8 },
            headStyles: { fillColor: [16, 185, 129] },
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
                        data.cell.styles.textColor = [16, 185, 129]; 
                    }
                }
                if (data.row.raw[0] === 'SUBTOTAL') {
                    data.cell.styles.fontStyle = 'bold';
                    data.cell.styles.textColor = [16, 185, 129];
                }
            }
        });

        doc.save('Vitamins_Ledger_Report.pdf');
    }

    function exportExcel() {
        const rows = generateExportData();
        rows.unshift([`Location: ${currentLocationName}`, '', '', '', '']); 
        rows.unshift(['Name / Grouping', 'Purchased (IN)', 'Used (OUT)', 'Cost', 'Date / Info']); 
        
        const ws = XLSX.utils.aoa_to_sheet(rows);
        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, "Vitamins Ledger");
        
        let now = new Date();
        let filenameDate = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
        XLSX.writeFile(wb, `Vitamins_Ledger_Report_${filenameDate}.xlsx`);
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
        link.setAttribute("download", `Vitamins_Ledger_Report_${filenameDate}.csv`);
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
</script>

</body>
</html>