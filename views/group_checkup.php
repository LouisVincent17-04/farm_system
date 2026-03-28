<?php
// views/group_checkup.php
ob_start(); 
error_reporting(0);
ini_set('display_errors', 0);
include '../config/Connection.php';

include '../security/checkAccess.php';
checkAccess('group_checkup');
$page = "transactions";

$event_ids = trim($_GET['event_ids'] ?? '');

include '../common/navbar.php';
include '../common/chat_support.php';
include '../functions/getUsersLocation.php';

// --- 1. AJAX HANDLER ---
if (isset($_GET['action'])) {
    ob_end_clean(); 
    header('Content-Type: application/json');
    $action = $_GET['action'];

    try {
        if ($action === 'get_buildings' && isset($_GET['location_id'])) {
            $stmt = $conn->prepare("SELECT BUILDING_ID, BUILDING_NAME FROM buildings WHERE LOCATION_ID = ? ORDER BY BUILDING_NAME");
            $stmt->execute([$_GET['location_id']]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC)); exit;
        }
        
        if ($action === 'get_bldg_animals_for_sale' && isset($_GET['building_id'])) {
            $bldg_id = $_GET['building_id'];
            $sql = "SELECT p.PEN_ID, p.PEN_NAME, a.ANIMAL_ID, a.TAG_NO, a.CURRENT_ACTUAL_WEIGHT 
                    FROM PENS p 
                    LEFT JOIN animal_records a ON p.PEN_ID = a.PEN_ID AND a.IS_ACTIVE = 1 AND a.CURRENT_STATUS != 'Sold'
                    WHERE p.BUILDING_ID = ? ORDER BY p.PEN_NAME, a.TAG_NO";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$bldg_id]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $data = [];
            foreach($results as $r) {
                $pid = $r['PEN_ID'];
                if(!isset($data[$pid])) $data[$pid] = ['pen_id' => $pid, 'pen_name' => $r['PEN_NAME'], 'animals' => []];
                if($r['ANIMAL_ID']) $data[$pid]['animals'][] = $r;
            }
            echo json_encode(['success' => true, 'pens' => array_values($data)]); exit;
        }

        // --- FETCH CHECKUP HISTORY ---
        if ($action === 'get_checkup_history') {
            $limit = 10;
            $page_num = isset($_GET['p']) ? (int)$_GET['p'] : 1;
            $offset = ($page_num - 1) * $limit;
            $search = $_GET['search'] ?? '';
            $loc_f = $_GET['loc_filter'] ?? '';
            $date_from = $_GET['date_from'] ?? '';
            $date_to = $_GET['date_to'] ?? '';

            $where = ["1=1"];
            $params = [];

            if($USER_LOCATION_ != 1000) { $where[] = "l.LOCATION_ID = ?"; $params[] = $USER_LOCATION_; }
            if($loc_f) { $where[] = "l.LOCATION_ID = ?"; $params[] = $loc_f; }
            if($search) { $where[] = "(a.TAG_NO LIKE ? OR c.VET_NAME LIKE ? OR c.REMARKS LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%"; }
            if($date_from) { $where[] = "DATE(c.CHECKUP_DATE) >= ?"; $params[] = $date_from; }
            if($date_to) { $where[] = "DATE(c.CHECKUP_DATE) <= ?"; $params[] = $date_to; }

            $where_sql = implode(" AND ", $where);

            // Count total
            $count_stmt = $conn->prepare("SELECT COUNT(*) FROM check_ups c JOIN animal_records a ON c.ANIMAL_ID = a.ANIMAL_ID JOIN locations l ON a.LOCATION_ID = l.LOCATION_ID WHERE $where_sql");
            $count_stmt->execute($params);
            $total_records = $count_stmt->fetchColumn();

            // Fetch data
            $sql = "SELECT c.*, DATE_FORMAT(c.CHECKUP_DATE, '%m/%d/%Y %h:%i %p') AS FORMATTED_DATE, a.TAG_NO, l.LOCATION_NAME 
                    FROM check_ups c 
                    JOIN animal_records a ON c.ANIMAL_ID = a.ANIMAL_ID 
                    JOIN locations l ON a.LOCATION_ID = l.LOCATION_ID
                    WHERE $where_sql 
                    ORDER BY c.CHECKUP_DATE DESC, c.CHECK_UP_ID DESC 
                    LIMIT $limit OFFSET $offset";
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'data' => $rows,
                'total' => $total_records,
                'pages' => ceil($total_records / $limit),
                'curr' => $page_num
            ]);
            exit;
        }
    } catch (Exception $e) { echo json_encode(['error' => $e->getMessage()]); exit; }
}

// --- 2. PAGE INIT ---
try {
    if ($USER_LOCATION_ != 1000) {
        $stmt = $conn->prepare("SELECT LOCATION_ID, LOCATION_NAME FROM LOCATIONS WHERE LOCATION_ID = ? ORDER BY LOCATION_NAME ASC");
        $stmt->execute([$USER_LOCATION_]);
        $locs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $locs = $conn->query("SELECT LOCATION_ID, LOCATION_NAME FROM LOCATIONS ORDER BY LOCATION_NAME ASC")->fetchAll(PDO::FETCH_ASSOC);
    }

    $personnel = $conn->query("
        SELECT FULL_NAME COLLATE utf8mb4_general_ci AS FULL_NAME, POSITION FROM employees WHERE STATUS = 'Active'
        UNION
        SELECT FULL_NAME COLLATE utf8mb4_general_ci AS FULL_NAME, 'Veterinarian' AS POSITION FROM VETERINARIANS WHERE IS_ACTIVE = 1
        ORDER BY FULL_NAME ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $locs = []; $personnel = []; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Group Checkup | FarmPro</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=DM+Mono:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" type="text/css" href="https://npmcdn.com/flatpickr/dist/themes/dark.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    <style>
        /* ─── CSS VARIABLES ─── */
        :root {
            --bg-base:        #080f1a;
            --bg-surface:     #0d1829;
            --bg-elevated:    #111f35;
            --bg-hover:       #162540;
            --border:         rgba(255,255,255,0.07);
            
            --sky:            #0ea5e9;
            --sky-dim:        rgba(14,165,233,0.12);
            --sky-glow:       rgba(14,165,233,0.25);
            --cyan:           #06b6d4;
            
            --emerald:        #10b981;
            --emerald-dim:    rgba(16,185,129,0.12);
            --red:            #f87171;
            --red-dim:        rgba(239,68,68,0.12);
            --amber:          #f59e0b;
            --blue:           #3b82f6;
            
            --text-primary:   #f1f5f9;
            --text-secondary: #94a3b8;
            --text-muted:     #475569;
            
            --radius-md:      10px;
            --radius-lg:      14px;
            --radius-xl:      20px;
            --shadow-md:      0 4px 16px rgba(0,0,0,0.4);
            --font:           'DM Sans', system-ui, sans-serif;
            --font-mono:      'DM Mono', monospace;
            --transition:     0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* ─── RESET & BASE ─── */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: var(--font); background: var(--bg-base); color: var(--text-primary);
            min-height: 100vh; padding-bottom: 60px;
            background-image: radial-gradient(ellipse 80% 50% at 50% -20%, rgba(14,165,233,0.06) 0%, transparent 60%);
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
        .back-link:hover { color: var(--text-primary); border-color: var(--sky); background: var(--bg-hover); }

        /* ─── ALERTS & BANNERS ─── */
        #sync-alert {
            display: none; padding: 1rem 1.5rem; border-radius: var(--radius-md); margin-bottom: 1.5rem;
            text-align: center; font-weight: 600; font-size: 0.95rem; font-family: var(--font); animation: fadeIn 0.3s ease-out;
        }
        #sync-alert.loading { background: var(--blue-dim); border: 1px solid rgba(59,130,246,0.3); color: #60a5fa; }
        #sync-alert.success { background: var(--emerald-dim); border: 1px solid rgba(16,185,129,0.3); color: #4ade80; }
        #sync-alert.error   { background: var(--red-dim); border: 1px solid rgba(239,68,68,0.3); color: #f87171; }

        #lock-banner {
            display: none; background: var(--sky-dim); border: 1px solid rgba(14,165,233,0.35);
            border-radius: var(--radius-md); padding: 1rem 1.5rem; margin-bottom: 1.5rem; color: #7dd3fc;
            font-size: 0.95rem; gap: 10px; align-items: center; animation: fadeIn 0.3s ease-out; font-weight: 500;
        }
        #lock-banner.show { display: flex; }

        /* ─── LAYOUT GRID ─── */
        .main-grid { display: grid; grid-template-columns: 400px 1fr; gap: 1.5rem; align-items: start; }

        /* ─── CONTROL PANEL (LEFT) ─── */
        .control-panel {
            background: var(--bg-surface); border: 1px solid var(--border);
            border-radius: var(--radius-xl); padding: 2rem; position: sticky; top: 1.5rem;
            box-shadow: var(--shadow-md); z-index: 10; display: flex; flex-direction: column;
        }
        .panel-title { font-size: 1.25rem; font-weight: 700; color: #fff; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 10px;}
        .panel-title i { color: var(--sky); }
        .panel-subtitle { font-size: 0.9rem; color: var(--text-secondary); margin-bottom: 2rem; }

        .step-label { color: var(--sky); font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 1rem; display: block;}
        
        .form-group { margin-bottom: 1.25rem; }
        .form-label { display: flex; justify-content: space-between; font-size: 0.75rem; color: var(--text-secondary); margin-bottom: 6px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;}
        
        .form-control, .form-select {
            width: 100%; padding: 12px 14px; background: var(--bg-elevated); border: 1px solid var(--border);
            border-radius: var(--radius-md); color: var(--text-primary); font-size: 0.95rem; transition: var(--transition); outline: none; box-sizing: border-box; font-family: var(--font);
        }
        .form-select {
            appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2394a3b8' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 12px center; cursor: pointer;
        }
        .form-control:focus, .form-select:focus { border-color: var(--sky); box-shadow: 0 0 0 3px var(--sky-glow); background: var(--bg-hover); }
        .form-control:disabled, .form-select:disabled { opacity: 0.5; cursor: not-allowed; background: rgba(255,255,255,0.02); }

        /* Lock Badge Wrapper */
        .select-wrap { position: relative; display: flex; align-items: center;}
        .select-wrap .form-control, .select-wrap .form-select { flex: 1; }
        .select-wrap .lock-badge { display: none; position: absolute; right: 14px; color: var(--sky); font-size: 0.9rem; pointer-events: none;}
        .select-wrap.locked .lock-badge { display: block; }
        .select-wrap.locked .form-select, .select-wrap.locked .form-control { border-color: rgba(14,165,233,0.4); background: var(--sky-dim); opacity: 0.9; cursor: not-allowed; padding-right: 35px;}

        .input-with-btn { display: flex; gap: 8px; }
        .input-with-btn .form-control, .input-with-btn .form-select { flex: 1; }
        
        .btn-mini {
            background: var(--bg-elevated); border: 1px solid var(--border); color: var(--text-primary);
            border-radius: var(--radius-md); padding: 0 16px; cursor: pointer; font-size: 0.85rem; font-weight: 700;
            white-space: nowrap; flex-shrink: 0; transition: var(--transition); font-family: var(--font);
        }
        .btn-mini:hover { background: var(--bg-hover); color: var(--sky); border-color: var(--sky); }

        /* Summary Box */
        .summary-box { margin-top: 1.5rem; background: var(--bg-elevated); padding: 1.25rem; border-radius: var(--radius-md); border-left: 4px solid var(--sky); border-top: 1px solid var(--border); border-right: 1px solid var(--border); border-bottom: 1px solid var(--border);}
        .summary-row { display: flex; justify-content: space-between; align-items: center; font-size: 0.9rem; color: var(--text-secondary); font-weight: 600;}
        .summary-row span#sum-count { color: #fff; font-size: 1.25rem; font-weight: 800; font-family: var(--font-mono);}
        .summary-row span#sum-cost { color: var(--amber); font-size: 1.25rem; font-weight: 800; font-family: var(--font-mono);}

        .btn-submit {
            width: 100%; margin-top: 1.5rem; padding: 14px; background: var(--sky); border: none;
            border-radius: var(--radius-md); color: #000; font-weight: 700; font-size: 1rem; font-family: var(--font);
            cursor: pointer; transition: var(--transition); display: flex; align-items: center; justify-content: center; gap: 8px;
        }
        .btn-submit:disabled { opacity: 0.5; cursor: not-allowed; background: var(--bg-elevated); color: var(--text-muted); border: 1px solid var(--border);}
        .btn-submit:hover:not(:disabled) { background: #38bdf8; box-shadow: 0 4px 15px var(--sky-glow); transform: translateY(-2px); }

        /* ─── WORKSPACE (RIGHT) ─── */
        .workspace-panel { display: flex; flex-direction: column; gap: 2rem; }
        
        .picker-section { background: var(--bg-surface); border: 1px solid var(--border); border-radius: var(--radius-xl); padding: 2rem; box-shadow: var(--shadow-md);}
        .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
        .section-title { font-size: 1.25rem; font-weight: 700; color: #fff; display: flex; align-items: center; gap: 10px;}
        .section-title i { color: var(--sky); }

        .select-all-container {
            display: flex; align-items: center; gap: 8px; font-size: 0.85rem; font-weight: 700; color: var(--blue);
            cursor: pointer; padding: 6px 12px; border-radius: 99px; background: var(--blue-dim); border: 1px solid rgba(59,130,246,0.3); transition: var(--transition);
        }
        .select-all-container:hover { background: rgba(59,130,246,0.2); }
        .select-all-container input { cursor: pointer; accent-color: var(--blue); width: 16px; height: 16px; margin: 0; }

        /* Animal Selection Grid */
        .animal-grid {
            display: grid; grid-template-columns: repeat(auto-fill, minmax(110px, 1fr)); gap: 1rem;
            max-height: 300px; overflow-y: auto; padding-right: 5px;
        }
        /* Custom Scrollbar for list */
        .animal-grid::-webkit-scrollbar { width: 6px; }
        .animal-grid::-webkit-scrollbar-track { background: transparent; }
        .animal-grid::-webkit-scrollbar-thumb { background: #334155; border-radius: 4px; }

        .animal-card {
            background: var(--bg-elevated); border: 1px solid var(--border); border-radius: var(--radius-md);
            padding: 1rem; cursor: pointer; text-align: center; transition: var(--transition); display: flex; flex-direction: column; gap: 6px; align-items: center; justify-content: center;
        }
        .animal-card:hover { border-color: rgba(255,255,255,0.2); background: var(--bg-hover); transform: translateY(-2px); }
        .animal-card i { font-size: 1.5rem; color: var(--text-muted); transition: var(--transition);}
        .animal-card .tag { font-weight: 700; font-family: var(--font-mono); color: var(--text-primary); font-size: 0.95rem; }
        
        .animal-card.in-table { background: var(--emerald-dim); border-color: rgba(16,185,129,0.4); opacity: 0.6; pointer-events: none; }
        .animal-card.in-table i { color: var(--emerald); }

        /* Action Table */
        .table-section { background: var(--bg-surface); border: 1px solid var(--border); border-radius: var(--radius-xl); overflow: hidden; box-shadow: var(--shadow-md);}
        .custom-table { width: 100%; border-collapse: collapse; min-width: 800px; }
        .custom-table th {
            background: var(--bg-elevated); color: var(--text-muted); font-size: 0.7rem; text-transform: uppercase;
            letter-spacing: 0.05em; padding: 16px; text-align: left; font-weight: 700; border-bottom: 1px solid var(--border);
        }
        .custom-table td { padding: 12px 16px; border-bottom: 1px solid rgba(255,255,255,0.03); vertical-align: middle; color: var(--text-primary); }
        .custom-table tbody tr:hover { background: rgba(255,255,255,0.01); }

        .custom-table select, .custom-table input {
            background: var(--bg-base); border: 1px solid var(--border); color: #fff; padding: 10px 12px;
            border-radius: 8px; width: 100%; font-size: 0.95rem; font-family: var(--font); outline: none; transition: var(--transition); box-sizing: border-box;
        }
        .custom-table input.weight-inp { font-family: var(--font-mono); font-weight: 600;}
        .custom-table input:focus, .custom-table select:focus { border-color: var(--sky); box-shadow: 0 0 0 3px var(--sky-glow); }
        
        .btn-remove { background: transparent; border: none; color: var(--text-muted); cursor: pointer; font-size: 1.25rem; transition: color var(--transition); display: flex; align-items: center; justify-content: center; width: 32px; height: 32px; border-radius: 6px;}
        .btn-remove:hover { color: var(--red); background: var(--red-dim); }

        .btn-clear { background: var(--red-dim); border: 1px solid rgba(239,68,68,0.3); color: var(--red); padding: 6px 12px; border-radius: 6px; cursor: pointer; font-size: 0.85rem; font-weight: 700; transition: var(--transition); display: inline-flex; align-items: center; gap: 6px;}
        .btn-clear:hover { background: rgba(239,68,68,0.2); }

        /* Status Badges */
        .badge-status { padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; display: inline-flex; align-items: center; gap: 6px; white-space: nowrap;}
        .status-Healthy { background: var(--emerald-dim); color: var(--emerald); border: 1px solid rgba(16,185,129,0.3); }
        .status-Sick { background: var(--red-dim); color: var(--red); border: 1px solid rgba(239,68,68,0.3); }
        .status-UnderTreatment { background: var(--cyan-dim); color: var(--cyan); border: 1px solid rgba(6,182,212,0.3); }
        .status-Critical { background: rgba(239,68,68,0.25); color: #fca5a5; border: 1px solid var(--red); }

        /* History Filters */
        .history-filters { display: flex; gap: 1rem; padding: 1.5rem; background: var(--bg-elevated); border-bottom: 1px solid var(--border); flex-wrap: wrap; align-items: center; }
        .filter-input {
            width: auto; min-width: 180px; flex: 1; padding: 12px 14px; background: var(--bg-base); border: 1px solid var(--border);
            border-radius: var(--radius-md); color: #fff; font-size: 0.95rem; font-family: var(--font); outline: none; transition: var(--transition); box-sizing: border-box;
        }
        .filter-input:focus { border-color: var(--sky); box-shadow: 0 0 0 3px var(--sky-glow);}

        .pagination { display: flex; justify-content: center; gap: 8px; padding: 1.5rem; flex-wrap: wrap; background: var(--bg-elevated);}
        .pg-btn {
            background: var(--bg-base); border: 1px solid var(--border); color: var(--text-secondary);
            padding: 8px 14px; border-radius: 8px; cursor: pointer; font-size: 0.95rem; font-weight: 700; font-family: var(--font); transition: var(--transition);
        }
        .pg-btn.active { background: var(--sky); color: #000; border-color: var(--sky); }
        .pg-btn:disabled { opacity: 0.5; cursor: not-allowed; }
        .pg-btn:hover:not(.active):not(:disabled) { background: var(--bg-hover); color: #fff; border-color: var(--text-muted); }

        /* Toast Notifications */
        #toastContainer { position: fixed; top: 20px; right: 20px; z-index: 9999; display: flex; flex-direction: column; gap: 10px; }
        .toast {
            background: var(--bg-surface); border: 1px solid var(--border); color: #fff;
            padding: 1rem 1.5rem; border-radius: var(--radius-md); box-shadow: 0 10px 25px rgba(0,0,0,0.5);
            font-size: 0.9rem; font-weight: 600; animation: slideIn 0.3s ease-out; display: flex; align-items: center; gap: 8px;
        }
        @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

        @media(max-width:1024px) {
            .main-grid { grid-template-columns: 1fr; }
            .control-panel { position: static; }
        }
        @media(max-width:768px) {
            .container { padding: 1rem; }
            .filter-input { width: 100% !important; flex: none;}
            .history-filters { flex-direction: column; align-items: stretch; }
            .input-with-btn { flex-direction: column; }
            .input-with-btn .btn-mini { width: 100%; padding: 12px; }
            
            .custom-table thead { display: none; }
            .custom-table, .custom-table tbody, .custom-table tr, .custom-table td { display: block; width: 100%; box-sizing: border-box; }
            
            .custom-table tr { background: rgba(255,255,255,0.02); border: 1px solid var(--border); border-radius: var(--radius-lg); margin-bottom: 1rem; padding: 1rem; }
            .custom-table td { display: flex; flex-direction: column; gap: 6px; padding: 0.75rem 0; border-bottom: 1px dashed rgba(255,255,255,0.05); text-align: left; }
            .custom-table td:last-child { border-bottom: none; align-items: flex-end;}
            .custom-table td::before { content: attr(data-label); font-weight: 700; color: var(--text-muted); font-size: 0.75rem; text-transform: uppercase; }
            
            .table-section { border: none; background: transparent; box-shadow: none;}
            .section-header { padding: 0 0 1rem 0; border: none;}
        }
    </style>
</head>
<body>

<div id="toastContainer"></div>

<div class="container">

    <div class="top-bar">
        <a href="<?= $event_ids ? 'events_scheduler.php' : 'transactions.php' ?>" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> 
            <?= $event_ids ? 'Back to Event Scheduler' : 'Back to Transactions' ?>
        </a>
        <span class="page-badge"><i class="fa-solid fa-stethoscope"></i> Clinical Center</span>
    </div>

    <div id="sync-alert">
        <span id="sync-msg"></span>
    </div>
    
    <div id="lock-banner">
        <i class="fa-solid fa-lock" style="color:var(--sky); font-size: 1.2rem;"></i> 
        <div><strong>Scheduler Mode Active:</strong> Animals are pre-loaded from the event schedule. Please add clinical findings and save to complete.</div>
    </div>

    <div class="main-grid">

        <div class="control-panel">
            <div class="panel-title"><i class="fa-solid fa-notes-medical"></i> Group Checkup</div>
            <div class="panel-subtitle">Record batch health inspections.</div>

            <form id="settingsForm">
                <span class="step-label">STEP 1: Locate Group</span>
                
                <div class="form-group">
                    <div class="select-wrap" id="wrap-location">
                        <select id="location_id" class="form-control" onchange="handleLocationChange(this.value)" <?php echo ($USER_LOCATION_ != 1000) ? 'disabled' : ''; ?>>
                            <?php if($USER_LOCATION_ == 1000): ?><option value="">-- Select Location --</option><?php endif; ?>
                            <?php foreach($locs as $l): ?>
                                <option value="<?= $l['LOCATION_ID'] ?>" <?= ($USER_LOCATION_ != 1000 && $l['LOCATION_ID'] == $USER_LOCATION_) ? 'selected' : '' ?>><?= htmlspecialchars($l['LOCATION_NAME']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <i class="fa-solid fa-lock lock-badge"></i>
                    </div>
                </div>
                <div class="form-group">
                    <div class="select-wrap" id="wrap-building">
                        <select id="building_id" class="form-control" onchange="loadPens(this.value)" disabled><option value="">-- Select Building --</option></select>
                        <i class="fa-solid fa-lock lock-badge"></i>
                    </div>
                </div>
                <div class="form-group" style="margin-bottom: 2rem;">
                    <div class="select-wrap" id="wrap-pen">
                        <select id="pen_id" class="form-control" onchange="loadAnimals(this.value)" disabled><option value="">-- Select Pen --</option></select>
                        <i class="fa-solid fa-lock lock-badge"></i>
                    </div>
                </div>

                <div style="border-top: 1px dashed var(--border); margin: 1.5rem 0;"></div>

                <span class="step-label">STEP 2: Batch Defaults</span>
                
                <div class="form-group">
                    <label class="form-label">Health Status</label>
                    <div class="input-with-btn">
                        <select id="default_status" class="form-control">
                            <option value="Healthy">Healthy</option>
                            <option value="Sick">Sick</option>
                            <option value="Under Treatment">Under Treatment</option>
                        </select>
                        <button type="button" class="btn-mini" onclick="updateAllStatus()"><i class="fa-solid fa-check"></i> Apply</button>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Veterinarian / Personnel</label>
                    <select id="examined_by" class="form-control">
                        <option value="">— Select Person —</option>
                        <?php foreach($personnel as $p): ?>
                            <option value="<?= htmlspecialchars($p['FULL_NAME']) ?>"><?= htmlspecialchars($p['FULL_NAME']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Checkup Date</label>
                    <input type="text" id="checkup_date" class="form-control date-picker" placeholder="Select Date & Time">
                </div>

                <div class="form-group">
                    <label class="form-label">Cost per Head (₱)</label>
                    <input type="number" id="cost_per_head" class="form-control" style="font-family:var(--font-mono); font-weight:700;" min="0" step="0.01" value="0" oninput="updateCount()">
                </div>

                <div class="summary-box">
                    <div class="summary-row"><span>Animals Selected:</span><span id="sum-count">0</span></div>
                    <div class="summary-row" style="margin-top: 6px;"><span>Total Est. Cost:</span><span id="sum-cost">₱ 0.00</span></div>
                </div>

                <button type="button" class="btn-submit" id="btn-submit" onclick="submitBatch()" disabled>
                    <i class="fa-solid fa-floppy-disk"></i> Record Checkups
                </button>
            </form>
        </div>

        <div class="workspace-panel">
            <div class="picker-section" id="pickerSection">
                <div class="section-header">
                    <div class="section-title"><i class="fa-solid fa-paw"></i> Step 3: Select Animals</div>
                    <label class="select-all-container" style="display:none;" id="select-all-wrapper">
                        <input type="checkbox" id="select-all-check" onchange="toggleSelectAll(this)"> Select All
                    </label>
                </div>
                <div id="animal-grid" class="animal-grid">
                    <div style="grid-column:1/-1;text-align:center;padding:3rem 1rem;color:var(--text-muted); font-style:italic; border: 1px dashed var(--border); border-radius: var(--radius-md);">
                        <i class="fa-solid fa-arrow-left" style="font-size: 2rem; display: block; margin-bottom: 1rem; opacity: 0.5;"></i>
                        Select a Pen from the control panel to load animals.
                    </div>
                </div>
            </div>

            <div class="table-section">
                <div class="section-header" style="padding: 1.5rem 1.5rem 1rem 1.5rem; border-bottom:1px solid var(--border); margin:0;">
                    <div class="section-title"><i class="fa-solid fa-list-check"></i> Step 4: Record Findings</div>
                    <button class="btn-clear" onclick="clearTable()"><i class="fa-solid fa-trash-can"></i> Clear All</button>
                </div>
                <div style="overflow-x: auto; width: 100%;">
                    <table class="custom-table">
                        <thead>
                            <tr>
                                <th style="width:15%; padding-left: 1.5rem;">Tag No</th>
                                <th style="width:20%;">Status</th>
                                <th>Findings / Notes</th>
                                <th style="width:15%;">Weight (kg)</th>
                                <th style="width:50px; text-align:center;"></th>
                            </tr>
                        </thead>
                        <tbody id="checkup-list">
                            <tr id="empty-row">
                                <td colspan="5" style="text-align:center;padding:3rem 1rem;color:var(--text-muted); font-style:italic;">
                                    <i class="fa-solid fa-arrow-up" style="font-size: 2rem; display: block; margin-bottom: 1rem; opacity: 0.5;"></i>
                                    Click on animals above to add them to the inspection list.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="table-section">
                <div class="section-header" style="padding: 1.5rem 1.5rem 1rem 1.5rem; border-bottom:1px solid var(--border); margin:0;">
                    <div class="section-title"><i class="fa-solid fa-clock-rotate-left"></i> Recent Checkup Logs</div>
                </div>
                
                <div class="history-filters">
                    <input type="text" id="histSearch" class="filter-input" placeholder="Search Tag or Person..." oninput="loadHistory(1)">
                    <?php if($USER_LOCATION_ == 1000): ?>
                    <select id="histLoc" class="filter-input" onchange="loadHistory(1)">
                        <option value="">All Locations</option>
                        <?php foreach($locs as $l): ?><option value="<?= $l['LOCATION_ID'] ?>"><?= $l['LOCATION_NAME'] ?></option><?php endforeach; ?>
                    </select>
                    <?php endif; ?>
                    
                    <input type="text" id="histFrom" class="filter-input date-picker" placeholder="Date From...">
                    <input type="text" id="histTo" class="filter-input date-picker" placeholder="Date To...">
                </div>

                <div style="overflow-x: auto; width: 100%;">
                    <table class="custom-table">
                        <thead>
                            <tr>
                                <th style="padding-left: 1.5rem;">Date</th>
                                <th>Tag</th>
                                <th>Status</th>
                                <th>Findings</th>
                                <th>Examiner</th>
                                <th style="text-align:right; padding-right:1.5rem;">Cost</th>
                            </tr>
                        </thead>
                        <tbody id="history-list">
                            <tr><td colspan="6" style="text-align:center;padding:3rem 1rem;color:var(--text-muted);"><i class="fa-solid fa-spinner fa-spin me-2"></i> Loading history...</td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="pagination" id="pagination"></div>
            </div>
        </div>
    </div>
</div>

<script>
let selectedAnimals = new Set();
let currentPenAnimals = [];
let fpCheckupDate;
const incomingEventIds = "<?= htmlspecialchars($event_ids) ?>";
const USER_LOCATION = <?php echo json_encode($USER_LOCATION_); ?>;

document.addEventListener('DOMContentLoaded', () => {
    fpCheckupDate = flatpickr("#checkup_date", { enableTime: true, dateFormat: "Y-m-d H:i", altInput: true, altFormat: "M j, Y h:i K" });
    
    flatpickr("#histFrom", { dateFormat: "Y-m-d", altInput: true, altFormat: "M j, Y", onChange: function() { loadHistory(1); } });
    flatpickr("#histTo", { dateFormat: "Y-m-d", altInput: true, altFormat: "M j, Y", onChange: function() { loadHistory(1); } });
    
    if (incomingEventIds) handleEventAutoSelect(incomingEventIds);
    else if (USER_LOCATION != 1000) {
        const locDrop = document.getElementById('location_id');
        locDrop.value = USER_LOCATION;
        handleLocationChange(USER_LOCATION);
        lockField('wrap-location', 'location_id');
    }

    loadHistory(1); 
});

// ── TOAST NOTIFICATIONS ───────────────────────────────────────────────────
function showToast(msg, type = 'success') {
    const t = document.createElement('div');
    t.className = 'toast';
    t.style.borderLeft = `4px solid ${type === 'error' ? 'var(--red)' : (type === 'loading' ? 'var(--blue)' : 'var(--emerald)')}`;
    
    let icon = '<i class="fa-solid fa-check"></i>';
    if(type === 'error') icon = '<i class="fa-solid fa-xmark"></i>';
    if(type === 'loading') icon = '<i class="fa-solid fa-spinner fa-spin"></i>';
    
    t.innerHTML = `${icon} ${msg}`;
    document.getElementById('toastContainer').appendChild(t);
    setTimeout(() => t.remove(), type === 'error' ? 5000 : 3500);
}

function showSyncBanner(type, msg) {
    const el = document.getElementById('sync-alert');
    el.className = type;
    
    let icon = '<i class="fa-solid fa-circle-check"></i>';
    if(type === 'error') icon = '<i class="fa-solid fa-circle-xmark"></i>';
    if(type === 'loading') icon = '<i class="fa-solid fa-spinner fa-spin"></i>';

    document.getElementById('sync-msg').innerHTML = `${icon} ${msg}`;
    el.style.display = 'block';
}
function hideSyncBanner() { document.getElementById('sync-alert').style.display = 'none'; }

function setSelectValue(selectId, value) {
    const sel    = document.getElementById(selectId);
    const target = String(value);
    for (let i = 0; i < sel.options.length; i++) {
        if (String(sel.options[i].value) === target) {
            sel.selectedIndex = i;
            return true;
        }
    }
    return false;
}

function lockField(wrapId, selectId) {
    const wrap = document.getElementById(wrapId);
    const sel  = document.getElementById(selectId);
    if (wrap) wrap.classList.add('locked');
    if (sel)  sel.disabled = true;
}

async function handleEventAutoSelect(eventIds) {
    showSyncBanner('loading', 'Auto-Sync Active: Loading scheduled animals for checkup…');
    try {
        const res  = await fetch(`../process/eventManager.php?action=get_events_details&ids=${eventIds}`);
        const data = await res.json();
        
        if (!data.success || !data.events || data.events.length === 0) { 
            showSyncBanner('error', 'No event details returned.');
            return; 
        }

        const ev = data.events[0];
        const locId = String(ev.LOCATION_ID);
        const bldgId = String(ev.BUILDING_ID);
        const penId = String(ev.PEN_ID);

        if (!setSelectValue('location_id', locId)) {
            showSyncBanner('error', `Location ID not found.`); return;
        }
        lockField('wrap-location', 'location_id');

        await loadBuildingsData(locId);
        if (!setSelectValue('building_id', bldgId)) {
            showSyncBanner('error', `Building ID not found.`); return;
        }
        lockField('wrap-building', 'building_id');

        await loadPensData(bldgId);
        if (!setSelectValue('pen_id', penId)) {
            showSyncBanner('error', `Pen ID not found.`); return;
        }
        lockField('wrap-pen', 'pen_id');

        data.events.forEach(e => {
            if (!selectedAnimals.has(String(e.ANIMAL_ID))) {
                addAnimalToTable({ ANIMAL_ID: e.ANIMAL_ID, TAG_NO: e.TAG_NO });
            }
        });

        document.getElementById('pickerSection').style.display = 'none';
        document.getElementById('lock-banner').classList.add('show');
        showSyncBanner('success', `${data.events.length} animal(s) loaded from schedule.`);
        setTimeout(hideSyncBanner, 5000);
        document.querySelector('.table-section').scrollIntoView({ behavior: 'smooth' });

    } catch (e) {
        showSyncBanner('error', 'Auto-sync failed. Select animals manually.');
        setTimeout(hideSyncBanner, 4000);
    }
}

async function handleLocationChange(locId) { 
    clearTable(); 
    await loadBuildingsData(locId);
}

function loadBuildingsData(locId) {
    return new Promise(resolve => {
        const bldg = document.getElementById('building_id');
        bldg.innerHTML = '<option>Loading…</option>';
        document.getElementById('pen_id').innerHTML = '<option>-- Select Pen --</option>';
        document.getElementById('pen_id').disabled = true;
        if (!locId) { bldg.innerHTML = '<option value="">-- Select Building --</option>'; bldg.disabled = true; resolve(); return; }
        
        fetch(`?action=get_buildings&location_id=${locId}`)
            .then(r => r.json())
            .then(data => {
                bldg.innerHTML = '<option value="">-- Select Building --</option>';
                (data || []).forEach(b => bldg.add(new Option(b.BUILDING_NAME, b.BUILDING_ID)));
                bldg.disabled = false; resolve();
            }).catch(() => { bldg.innerHTML = '<option value="">Error</option>'; resolve(); });
    });
}

function loadPens(bldgId) {
    loadPensData(bldgId);
}

function loadPensData(bldgId) {
    return new Promise(resolve => {
        const pen = document.getElementById('pen_id');
        pen.innerHTML = '<option>Loading…</option>';
        if (!bldgId) { pen.innerHTML = '<option value="">-- Select Pen --</option>'; pen.disabled = true; resolve(); return; }
        
        fetch(`../process/getPensByBuilding.php?building_id=${bldgId}`)
            .then(r => r.json())
            .then(data => {
                pen.innerHTML = '<option value="">-- Select Pen --</option>';
                (data.pens || []).forEach(p => pen.add(new Option(p.PEN_NAME, p.PEN_ID)));
                pen.disabled = false; resolve();
            });
    });
}

async function loadAnimals(penId) {
    const grid = document.getElementById('animal-grid');
    grid.innerHTML = '<div style="grid-column:1/-1;text-align:center;color:var(--text-muted);"><i class="fa-solid fa-spinner fa-spin me-2"></i> Loading…</div>';
    if (!penId) return;
    
    const res = await fetch(`../process/getAnimalsByPen.php?pen_id=${penId}`);
    const data = await res.json();
    grid.innerHTML = '';
    currentPenAnimals = (data.animal_record || []).filter(a => a.IS_ACTIVE == 1);
    document.getElementById('select-all-wrapper').style.display = currentPenAnimals.length ? 'flex' : 'none';
    
    if (!currentPenAnimals.length) { grid.innerHTML = '<div style="grid-column:1/-1;text-align:center;color:var(--text-muted); padding: 2rem; font-style:italic;">No active animals found in this pen.</div>'; return; }
    
    currentPenAnimals.forEach(a => {
        const card = document.createElement('div');
        card.className = `animal-card ${selectedAnimals.has(String(a.ANIMAL_ID)) ? 'in-table' : ''}`;
        card.id = `card-${a.ANIMAL_ID}`;
        card.onclick = () => addAnimalToTable(a);
        card.innerHTML = `<i class="fa-solid fa-paw"></i><div class="tag">${a.TAG_NO}</div>`;
        grid.appendChild(card);
    });
}

function addAnimalToTable(animal) {
    if (selectedAnimals.has(String(animal.ANIMAL_ID))) return;
    const list = document.getElementById('checkup-list');
    const empty = document.getElementById('empty-row');
    if (empty) empty.remove();

    const defStatus = document.getElementById('default_status').value;

    const tr = document.createElement('tr');
    tr.id = `row-${animal.ANIMAL_ID}`;
    tr.dataset.id = animal.ANIMAL_ID;
    tr.innerHTML = `
        <td data-label="Tag No" style="font-weight:700; font-family:var(--font-mono); padding-left: 1.5rem;">${animal.TAG_NO}</td>
        <td data-label="Status">
            <select class="status-sel form-control">
                <option value="Healthy" ${defStatus==='Healthy'?'selected':''}>Healthy</option>
                <option value="Sick" ${defStatus==='Sick'?'selected':''}>Sick</option>
                <option value="Under Treatment" ${defStatus==='Under Treatment'?'selected':''}>Under Treatment</option>
            </select>
        </td>
        <td data-label="Findings"><textarea class="findings-inp form-control" rows="1" placeholder="Observations..."></textarea></td>
        <td data-label="Weight"><input type="number" class="weight-inp form-control" placeholder="kg"></td>
        <td style="text-align:center;"><button class="btn-remove" onclick="removeAnimal(${animal.ANIMAL_ID})" title="Remove"><i class="fa-solid fa-xmark"></i></button></td>
    `;
    list.appendChild(tr);
    selectedAnimals.add(String(animal.ANIMAL_ID));
    document.getElementById(`card-${animal.ANIMAL_ID}`)?.classList.add('in-table');
    updateCount();
}

function removeAnimal(id) {
    document.getElementById(`row-${id}`)?.remove();
    selectedAnimals.delete(String(id));
    document.getElementById(`card-${id}`)?.classList.remove('in-table');
    
    if (selectedAnimals.size === 0) {
        document.getElementById('checkup-list').innerHTML = '<tr id="empty-row"><td colspan="5" style="text-align:center;padding:3rem 1rem;color:var(--text-muted); font-style:italic;"><i class="fa-solid fa-arrow-up" style="font-size: 2rem; display: block; margin-bottom: 1rem; opacity: 0.5;"></i>Click on animals above to add them to the inspection list.</td></tr>';
    }
    updateCount();
}

function updateCount() {
    const c = selectedAnimals.size;
    const cost = parseFloat(document.getElementById('cost_per_head').value) || 0;
    document.getElementById('sum-count').textContent = c;
    document.getElementById('sum-cost').textContent = '₱ ' + (c * cost).toLocaleString(undefined, {minimumFractionDigits:2});
    document.getElementById('btn-submit').disabled = c === 0;
}

function clearTable() { selectedAnimals.forEach(id => removeAnimal(id)); }
function toggleSelectAll(cb) { if (cb.checked) currentPenAnimals.forEach(a => addAnimalToTable(a)); else clearTable(); }
function updateAllStatus() { const v = document.getElementById('default_status').value; document.querySelectorAll('.status-sel').forEach(s => s.value = v); }

async function loadHistory(page) {
    const list = document.getElementById('history-list');
    const pg = document.getElementById('pagination');
    list.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:3rem 1rem;color:var(--text-muted);"><i class="fa-solid fa-spinner fa-spin me-2"></i> Loading history...</td></tr>';

    const search = document.getElementById('histSearch').value;
    const loc = document.getElementById('histLoc')?.value || '';
    const from = document.getElementById('histFrom').value;
    const to = document.getElementById('histTo').value;

    try {
        const query = `action=get_checkup_history&p=${page}&search=${search}&loc_filter=${loc}&date_from=${from}&date_to=${to}`;
        const res = await fetch(`?${query}`);
        const result = await res.json();

        if (!result.success || !result.data) {
            list.innerHTML = `<tr><td colspan="6" style="text-align:center; color:var(--red);">Error: ${result.error || 'Failed to load data'}</td></tr>`;
            if(pg) pg.innerHTML = '';
            return;
        }

        if (result.data.length === 0) {
            list.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:3rem 1rem;color:var(--text-muted); font-style:italic;"><i class="fa-solid fa-ghost display-block margin-bottom opacity-50 font-size-2rem"></i> No checkup records found.</td></tr>';
            if(pg) pg.innerHTML = '';
            return;
        }

        list.innerHTML = result.data.map(row => {
            let raw = row.REMARKS || '';
            let status = 'Unknown';
            let findings = raw;
            let match = raw.match(/\[(.*?)\]/);
            
            if (match) {
                status = match[1]; 
                findings = raw.replace(match[0], '').trim(); 
            }
            let cssClass = status.replace(/\s/g, '');

            let icon = '<i class="fa-solid fa-circle-info"></i> ';
            if(cssClass === 'Healthy') icon = '<i class="fa-solid fa-check"></i> ';
            if(cssClass === 'Sick' || cssClass === 'Critical') icon = '<i class="fa-solid fa-triangle-exclamation"></i> ';

            return `
                <tr>
                    <td data-label="Date" style="font-size:0.9rem; color:var(--text-secondary); font-family:var(--font-mono); padding-left: 1.5rem;">${row.FORMATTED_DATE}</td>
                    <td data-label="Tag"><span style="background:var(--sky-dim); color:var(--sky); padding:4px 10px; border-radius:6px; font-weight:700; font-family:var(--font-mono); border:1px solid rgba(14,165,233,0.3);"><i class="fa-solid fa-tag"></i> ${row.TAG_NO}</span></td>
                    <td data-label="Status"><span class="badge-status status-${cssClass}">${icon}${status}</span></td>
                    <td data-label="Findings" style="font-size:0.9rem; color:var(--text-primary);">${findings || '-'}</td>
                    <td data-label="Examiner" style="font-size:0.95rem;">${row.VET_NAME}</td>
                    <td data-label="Cost" style="color:var(--amber); font-weight:700; font-family:var(--font-mono); white-space:nowrap; padding-right:1.5rem; text-align:right;">₱ ${parseFloat(row.COST).toFixed(2)}</td>
                </tr>
            `;
        }).join('');

        if (pg) {
            pg.innerHTML = '';
            for(let i=1; i<=result.pages; i++) {
                const btn = document.createElement('button');
                btn.className = `pg-btn ${i === result.curr ? 'active' : ''}`;
                btn.textContent = i;
                btn.onclick = () => loadHistory(i);
                pg.appendChild(btn);
            }
        }
    } catch (e) {
        console.error("LoadHistory Error:", e);
        list.innerHTML = '<tr><td colspan="6" style="text-align:center; color:var(--red);">System Error: Check Console</td></tr>';
    }
}

async function submitBatch() {
    if (!confirm(`Are you sure you want to record findings for ${selectedAnimals.size} animal(s)?`)) return;
    
    const dateInput = document.getElementById('checkup_date').value;
    if (!dateInput) { showToast("Please select a valid Checkup Date.", "error"); return; }

    const btn = document.getElementById('btn-submit');
    const ogText = btn.innerHTML;
    btn.disabled = true; btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Processing…';

    const records = [];
    document.querySelectorAll('#checkup-list tr[id^="row-"]').forEach(tr => {
        let selStatus = tr.querySelector('.status-sel').value;
        let inpFindings = tr.querySelector('.findings-inp').value;

        records.push({
            animal_id: tr.dataset.id,
            status: selStatus,
            findings: inpFindings,
            weight: tr.querySelector('.weight-inp').value,
            remarks: `[${selStatus}] ${inpFindings}`.trim() 
        });
    });

    try {
        const res = await fetch('../process/addBatchCheckup.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                records,
                date: dateInput,
                examined_by: document.getElementById('examined_by').value,
                cost: parseFloat(document.getElementById('cost_per_head').value) || 0,
                event_ids: incomingEventIds
            })
        });
        
        const raw = await res.text();
        let data;
        try { data = JSON.parse(raw); } catch(e) { showToast('Server error. Check console.', 'error'); btn.disabled = false; btn.innerHTML = ogText; return; }

        if (data.success) {
            if (incomingEventIds) {
                try {
                    const fd = new FormData();
                    fd.append('action', 'mark_events_done');
                    fd.append('event_ids', incomingEventIds);
                    await fetch('../process/eventManager.php', { method: 'POST', body: fd });
                } catch (e) { console.warn('mark_events_done (non-fatal):', e); }
            }
            showToast('Checkups Recorded!', "success");
            loadHistory(1);
            setTimeout(() => {
                window.location.href = incomingEventIds ? 'events_scheduler.php' : window.location.pathname;
            }, 1500);
        } else {
            showToast(data.message, "error");
            btn.disabled = false; btn.innerHTML = ogText;
        }
    } catch (e) {
        showToast('System connection error.', "error");
        btn.disabled = false; btn.innerHTML = ogText;
    }
}
</script>
</body>
</html>