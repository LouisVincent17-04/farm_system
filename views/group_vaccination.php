<?php
// views/group_vaccination.php
ob_start();
error_reporting(0);
ini_set('display_errors', 0);
include '../config/Connection.php';

include '../security/checkAccess.php';
checkAccess('group_vaccination');
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
        // Fetch specific vaccines for refresh
        if ($action === 'get_vaccines') {
            $locId = $_GET['location_id'] ?? '';
            if (!$locId) { echo json_encode([]); exit; }
            $stmt = $conn->prepare("
                SELECT SUPPLY_ID, SUPPLY_NAME, TOTAL_STOCK, UNIT_ID,
                       (SELECT UNIT_ABBR FROM UNITS WHERE UNITS.UNIT_ID = VACCINES.UNIT_ID LIMIT 1) as UNIT_ABBR
                FROM VACCINES 
                WHERE LOCATION_ID = ? 
                ORDER BY SUPPLY_NAME ASC
            ");
            $stmt->execute([$locId]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Ensure we ALWAYS return an array, even if empty
            echo json_encode($results ?: []);
            exit;
        }

        // --- FETCH VACCINATION HISTORY ---
        if ($action === 'get_vaccination_history') {
            $limit    = 10;
            $page_num = isset($_GET['p']) ? (int)$_GET['p'] : 1;
            $offset   = ($page_num - 1) * $limit;
            $search   = $_GET['search']     ?? '';
            $loc_f    = $_GET['loc_filter'] ?? '';
            $date_from = $_GET['date_from'] ?? '';
            $date_to   = $_GET['date_to']   ?? '';

            $where  = ["1=1"];
            $params = [];

            if ($USER_LOCATION_ != 1000) { $where[] = "l.LOCATION_ID = ?"; $params[] = $USER_LOCATION_; }
            if ($loc_f)     { $where[] = "l.LOCATION_ID = ?"; $params[] = $loc_f; }
            if ($search)    {
                $where[] = "(a.TAG_NO LIKE ? OR vr.ADMINISTERED_BY LIKE ? OR v.SUPPLY_NAME LIKE ?)";
                $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
            }
            if ($date_from) { $where[] = "DATE(vr.VACCINATION_DATE) >= ?"; $params[] = $date_from; }
            if ($date_to)   { $where[] = "DATE(vr.VACCINATION_DATE) <= ?"; $params[] = $date_to; }

            $where_sql = implode(" AND ", $where);

            $count_stmt = $conn->prepare("
                SELECT COUNT(*)
                FROM vaccination_records vr
                JOIN animal_records a ON vr.ANIMAL_ID = a.ANIMAL_ID
                JOIN locations l      ON a.LOCATION_ID = l.LOCATION_ID
                LEFT JOIN VACCINES v  ON vr.ITEM_ID = v.SUPPLY_ID
                WHERE $where_sql
            ");
            $count_stmt->execute($params);
            $total_records = $count_stmt->fetchColumn();

            $sql = "
                SELECT vr.*,
                       DATE_FORMAT(vr.VACCINATION_DATE, '%m/%d/%Y %h:%i %p') AS FORMATTED_DATE,
                       a.TAG_NO,
                       l.LOCATION_NAME,
                       v.SUPPLY_NAME
                FROM vaccination_records vr
                JOIN animal_records a ON vr.ANIMAL_ID = a.ANIMAL_ID
                JOIN locations l      ON a.LOCATION_ID = l.LOCATION_ID
                LEFT JOIN VACCINES v  ON vr.ITEM_ID = v.SUPPLY_ID
                WHERE $where_sql
                ORDER BY vr.VACCINATION_DATE DESC, vr.VACCINATION_ID DESC
                LIMIT $limit OFFSET $offset
            ";
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'data'    => $rows,
                'total'   => $total_records,
                'pages'   => ceil($total_records / $limit),
                'curr'    => $page_num,
            ]);
            exit;
        }

        echo json_encode(['error' => 'Unknown action']);

    } catch (Exception $e) { echo json_encode(['error' => $e->getMessage()]); }
    exit;
}

// =========================================================
// NORMAL PAGE LOAD
// =========================================================
try {
    if (!isset($conn)) throw new Exception("Database connection failed.");

    if ($USER_LOCATION_ != 1000) {
        $stmt = $conn->prepare("SELECT LOCATION_ID, LOCATION_NAME FROM LOCATIONS WHERE LOCATION_ID = ? ORDER BY LOCATION_NAME ASC");
        $stmt->execute([$USER_LOCATION_]);
        $locs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $locs = $conn->query("SELECT LOCATION_ID, LOCATION_NAME FROM LOCATIONS ORDER BY LOCATION_NAME ASC")->fetchAll(PDO::FETCH_ASSOC);
    }

    $personnel = $conn->query("
        SELECT FULL_NAME COLLATE utf8mb4_general_ci AS FULL_NAME, POSITION
        FROM employees WHERE STATUS = 'Active'
        UNION
        SELECT FULL_NAME COLLATE utf8mb4_general_ci AS FULL_NAME, 'Veterinarian' AS POSITION
        FROM VETERINARIANS WHERE IS_ACTIVE = 1
        ORDER BY FULL_NAME ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) { $locs = []; $personnel = []; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Group Vaccination | FarmPro</title>

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
            
            --purple:         #a855f7;
            --purple-dim:     rgba(168,85,247,0.12);
            --purple-glow:    rgba(168,85,247,0.25);
            --indigo:         #6366f1;
            
            --emerald:        #10b981;
            --emerald-dim:    rgba(16,185,129,0.12);
            --red:            #f87171;
            --red-dim:        rgba(239,68,68,0.12);
            --blue:           #3b82f6;
            --amber:          #f59e0b;
            
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
            background-image: radial-gradient(ellipse 80% 50% at 50% -20%, rgba(168,85,247,0.06) 0%, transparent 60%);
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
        .back-link:hover { color: var(--text-primary); border-color: var(--purple); background: var(--bg-hover); }

        .page-badge {
            display: inline-flex; align-items: center; gap: 6px; font-size: 0.75rem;
            font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase;
            color: var(--purple); background: var(--purple-dim); border: 1px solid rgba(168,85,247,0.2);
            padding: 6px 12px; border-radius: 99px;
        }

        /* ─── ALERTS & BANNERS ─── */
        #sync-alert {
            display: none; padding: 1rem 1.5rem; border-radius: var(--radius-md); margin-bottom: 1.5rem;
            text-align: center; font-weight: 600; font-size: 0.95rem; font-family: var(--font); animation: fadeIn 0.3s ease-out;
        }
        #sync-alert.loading { background: var(--blue-dim); border: 1px solid rgba(59,130,246,0.3); color: #60a5fa; }
        #sync-alert.success { background: var(--emerald-dim); border: 1px solid rgba(16,185,129,0.3); color: #4ade80; }
        #sync-alert.error   { background: var(--red-dim); border: 1px solid rgba(239,68,68,0.3); color: #f87171; }

        #lock-banner {
            display: none; background: var(--purple-dim); border: 1px solid rgba(168,85,247,0.35);
            border-radius: var(--radius-md); padding: 1rem 1.5rem; margin-bottom: 1.5rem; color: #d8b4fe;
            font-size: 0.95rem; gap: 10px; align-items: center; animation: fadeIn 0.3s ease-out; font-weight: 500;
        }
        #lock-banner.show { display: flex; }

        /* Context Card (For Auto-Sync) */
        #context-card {
            display: none; margin-top: 1rem; background: rgba(0,0,0,0.2);
            border: 1px solid var(--border); border-radius: var(--radius-md); padding: 1rem; font-size: 0.85rem;
        }
        #context-card.show { display: block; }
        .ctx-row { display: flex; justify-content: space-between; padding: 6px 0; color: var(--text-secondary); border-bottom: 1px solid rgba(255,255,255,0.03); }
        .ctx-row:last-child { border-bottom: none; }
        .ctx-row strong { color: #fff; max-width: 60%; text-align: right; font-weight: 600; }

        /* ─── LAYOUT GRID ─── */
        .main-grid { display: grid; grid-template-columns: 400px 1fr; gap: 1.5rem; align-items: start; }

        /* ─── CONTROL PANEL (LEFT) ─── */
        .control-panel {
            background: var(--bg-surface); border: 1px solid var(--border);
            border-radius: var(--radius-xl); padding: 2rem; position: sticky; top: 1.5rem;
            box-shadow: var(--shadow-md); z-index: 10; display: flex; flex-direction: column;
        }
        .panel-title { font-size: 1.25rem; font-weight: 700; color: #fff; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 10px;}
        .panel-title i { color: var(--purple); }
        .panel-subtitle { font-size: 0.9rem; color: var(--text-secondary); margin-bottom: 2rem; }

        .step-label { color: var(--purple); font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 1rem; display: block;}
        
        .form-group { margin-bottom: 1.25rem; }
        .form-label { display: flex; justify-content: space-between; font-size: 0.75rem; color: var(--text-secondary); margin-bottom: 6px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;}
        
        .form-control, .form-select {
            width: 100%; padding: 12px 14px; background: var(--bg-elevated); border: 1px solid var(--border);
            border-radius: var(--radius-md); color: var(--text-primary); font-size: 0.95rem; transition: var(--transition); outline: none; box-sizing: border-box; font-family: var(--font);
        }
        .form-select {
            appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2394a3b8' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 12px center; cursor: pointer;
        }
        .form-control:focus, .form-select:focus { border-color: var(--purple); box-shadow: 0 0 0 3px var(--purple-glow); background: var(--bg-hover); }
        .form-control:disabled, .form-select:disabled { opacity: 0.5; cursor: not-allowed; background: rgba(255,255,255,0.02); }

        /* Lock Badge Wrapper */
        .select-wrap { position: relative; display: flex; align-items: center;}
        .select-wrap .form-control, .select-wrap .form-select { flex: 1; }
        .select-wrap .lock-badge { display: none; position: absolute; right: 14px; color: var(--purple); font-size: 0.9rem; pointer-events: none;}
        .select-wrap.locked .lock-badge { display: block; }
        .select-wrap.locked .form-select, .select-wrap.locked .form-control { border-color: rgba(168,85,247,0.4); background: var(--purple-dim); opacity: 0.9; cursor: not-allowed; padding-right: 35px;}

        .input-with-btn { display: flex; gap: 8px; }
        .input-with-btn .form-control, .input-with-btn .form-select { flex: 1; }
        
        .btn-mini {
            background: var(--bg-elevated); border: 1px solid var(--border); color: var(--text-primary);
            border-radius: var(--radius-md); padding: 0 16px; cursor: pointer; font-size: 0.85rem; font-weight: 700;
            white-space: nowrap; flex-shrink: 0; transition: var(--transition); font-family: var(--font);
        }
        .btn-mini:hover { background: var(--bg-hover); color: var(--purple); border-color: var(--purple); }

        .resource-link { display: inline-flex; align-items: center; gap: 5px; font-size: 0.75rem; color: var(--blue); text-decoration: none; transition: color 0.2s; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;}
        .resource-link:hover { color: #93c5fd; text-decoration: underline; }
        
        .stock-ok  { color: var(--emerald); font-size: 0.85rem; margin-top: 5px; display: block; font-family: var(--font-mono); font-weight: 700;}
        .stock-low { color: var(--red); font-size: 0.85rem; margin-top: 5px; display: block; font-family: var(--font-mono); font-weight: 700; background: var(--red-dim); padding: 4px 8px; border-radius: 4px; border: 1px solid rgba(239,68,68,0.2);}

        /* Summary Box */
        .summary-box { margin-top: 1.5rem; background: var(--bg-elevated); padding: 1.25rem; border-radius: var(--radius-md); border-left: 4px solid var(--purple); border-top: 1px solid var(--border); border-right: 1px solid var(--border); border-bottom: 1px solid var(--border);}
        .summary-row { display: flex; justify-content: space-between; align-items: center; font-size: 0.9rem; color: var(--text-secondary); font-weight: 600;}
        .summary-row span#sum-count { color: #fff; font-size: 1.25rem; font-weight: 800; font-family: var(--font-mono);}
        
        .summary-total { margin-top: 10px; padding-top: 10px; border-top: 1px solid var(--border); font-weight: 700; color: var(--text-primary); display: flex; justify-content: space-between; align-items: center; }
        .summary-total span#sum-total { color: var(--purple); font-size: 1.25rem; font-weight: 800; font-family: var(--font-mono);}

        .btn-submit {
            width: 100%; margin-top: 1.5rem; padding: 14px; background: var(--purple); border: none;
            border-radius: var(--radius-md); color: #fff; font-weight: 700; font-size: 1rem; font-family: var(--font);
            cursor: pointer; transition: var(--transition); display: flex; align-items: center; justify-content: center; gap: 8px;
        }
        .btn-submit:disabled { opacity: 0.5; cursor: not-allowed; background: var(--bg-elevated); color: var(--text-muted); border: 1px solid var(--border);}
        .btn-submit:hover:not(:disabled) { background: #c084fc; box-shadow: 0 4px 15px var(--purple-glow); transform: translateY(-2px); }

        /* ─── WORKSPACE (RIGHT) ─── */
        .workspace-panel { display: flex; flex-direction: column; gap: 2rem; }
        
        .picker-section { background: var(--bg-surface); border: 1px solid var(--border); border-radius: var(--radius-xl); padding: 2rem; box-shadow: var(--shadow-md);}
        .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
        .section-title { font-size: 1.25rem; font-weight: 700; color: #fff; display: flex; align-items: center; gap: 10px;}
        .section-title i { color: var(--blue); }

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
        .custom-table input.qty-input, .custom-table input.dosage-input { font-family: var(--font-mono); font-weight: 600;}
        .custom-table input:focus, .custom-table select:focus { border-color: var(--purple); box-shadow: 0 0 0 3px var(--purple-glow); }
        
        .btn-remove { background: transparent; border: none; color: var(--text-muted); cursor: pointer; font-size: 1.25rem; transition: color var(--transition); display: flex; align-items: center; justify-content: center; width: 32px; height: 32px; border-radius: 6px;}
        .btn-remove:hover { color: var(--red); background: var(--red-dim); }

        .btn-clear { background: var(--red-dim); border: 1px solid rgba(239,68,68,0.3); color: var(--red); padding: 6px 12px; border-radius: 6px; cursor: pointer; font-size: 0.85rem; font-weight: 700; transition: var(--transition); display: inline-flex; align-items: center; gap: 6px;}
        .btn-clear:hover { background: rgba(239,68,68,0.2); }

        /* History Filters */
        .history-filters { display: flex; gap: 1rem; padding: 1.5rem; background: var(--bg-elevated); border-bottom: 1px solid var(--border); flex-wrap: wrap; align-items: center; }
        .filter-input {
            width: auto; min-width: 180px; flex: 1; padding: 12px 14px; background: var(--bg-base); border: 1px solid var(--border);
            border-radius: var(--radius-md); color: #fff; font-size: 0.95rem; font-family: var(--font); outline: none; transition: var(--transition); box-sizing: border-box;
        }
        .filter-input:focus { border-color: var(--purple); box-shadow: 0 0 0 3px var(--purple-glow);}

        .pagination { display: flex; justify-content: center; gap: 8px; padding: 1.5rem; flex-wrap: wrap; background: var(--bg-elevated);}
        .pg-btn {
            background: var(--bg-base); border: 1px solid var(--border); color: var(--text-secondary);
            padding: 8px 14px; border-radius: 8px; cursor: pointer; font-size: 0.95rem; font-weight: 700; font-family: var(--font); transition: var(--transition);
        }
        .pg-btn.active { background: var(--purple); color: #fff; border-color: var(--purple); }
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
        <span class="page-badge"><i class="fa-solid fa-syringe"></i> Immunization Center</span>
    </div>

    <div id="sync-alert">
        <span id="sync-msg"></span>
    </div>

    <div id="lock-banner">
        <i class="fa-solid fa-lock" style="color:var(--purple); font-size: 1.2rem;"></i> 
        <div><strong>Scheduler Mode Active:</strong> Location, building, pen, and vaccine are pre-loaded from the event schedule. Review dosages then click <em>Record Vaccination</em>.</div>
    </div>

    <div class="main-grid">

        <div class="control-panel">
            <div class="panel-title"><i class="fa-solid fa-shield-virus"></i> Group Vaccination</div>
            <div class="panel-subtitle">Configure vaccine batch and default dosage.</div>

            <form id="settingsForm">

                <div style="background:rgba(255,255,255,.03);padding:15px;border-radius:8px;margin-bottom:1.5rem;border:1px dashed #475569;">
                    <label class="step-label">STEP 1: Locate Group</label>

                    <div class="form-group" style="margin-bottom:.5rem;">
                        <div class="select-wrap" id="wrap-location">
                            <select id="location_id" class="form-control" onchange="handleLocationChange(this.value)" <?php echo ($USER_LOCATION_ != 1000) ? 'disabled' : ''; ?>>
                                <?php if($USER_LOCATION_ == 1000): ?>
                                    <option value="">-- Select Location --</option>
                                <?php endif; ?>
                                <?php foreach($locs as $l): ?>
                                    <option value="<?= $l['LOCATION_ID'] ?>" <?php echo ($USER_LOCATION_ != 1000 && $l['LOCATION_ID'] == $USER_LOCATION_) ? 'selected' : ''; ?>>
                                        <?= htmlspecialchars($l['LOCATION_NAME']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <i class="fa-solid fa-lock lock-badge"></i>
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom:.5rem;">
                        <div class="select-wrap" id="wrap-building">
                            <select id="building_id" class="form-control" onchange="handleBuildingChange(this.value)" disabled>
                                <option value="">-- Select Building --</option>
                            </select>
                            <i class="fa-solid fa-lock lock-badge"></i>
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom:0;">
                        <div class="select-wrap" id="wrap-pen">
                            <select id="pen_id" class="form-control" onchange="loadAnimals(this.value)" disabled>
                                <option value="">-- Select Pen --</option>
                            </select>
                            <i class="fa-solid fa-lock lock-badge"></i>
                        </div>
                    </div>

                    <div id="context-card">
                        <div class="ctx-row"><span>📍 Location</span> <strong id="ctx-loc">—</strong></div>
                        <div class="ctx-row"><span>🏠 Building</span> <strong id="ctx-bldg">—</strong></div>
                        <div class="ctx-row"><span>🐷 Pen</span>      <strong id="ctx-pen">—</strong></div>
                        <div class="ctx-row"><span>💉 Vaccine</span>  <strong id="ctx-vax">—</strong></div>
                    </div>
                </div>

                <label class="step-label">STEP 2: Batch Details</label>

                <div class="form-group">
                    <div class="form-label">
                        <span>Vaccine <span style="color:var(--red);">*</span></span>
                        <a href="purch_vaccines.php" target="_blank" class="resource-link" title="Opens in a new tab">Manage Inventory <i class="fa-solid fa-arrow-up-right-from-square"></i></a>
                    </div>
                    <div class="select-wrap" id="wrap-vaccine">
                        <select id="vaccine_id" class="form-control" onchange="updateCalculations()" disabled required>
                            <option value="" data-stock="0">Select Location First</option>
                        </select>
                        <i class="fa-solid fa-lock lock-badge"></i>
                    </div>
                    
                    <div style="display: flex; justify-content: flex-start; align-items: center; margin-top: 8px;">
                        <button type="button" id="refresh-vax-btn" class="btn-mini" onclick="refreshVaccineList()" style="background:transparent; border-color:var(--border); color:var(--text-secondary); padding: 4px 8px;">
                            <i class="fa-solid fa-rotate-right"></i> Sync Options
                        </button>
                    </div>
                    <div id="stock-display"></div>
                </div>

                <div class="form-group">
                    <label class="form-label">Quantity / Head <span style="color:var(--red);">*</span></label>
                    <div class="input-with-btn">
                        <input type="number" id="default_dosage" class="form-control" step="0.01" value="1.00" oninput="updateAllDosages()" placeholder="Qty">
                        <button type="button" class="btn-mini" onclick="updateAllDosages()" title="Apply to all rows"><i class="fa-solid fa-check"></i> Apply</button>
                    </div>
                    <small style="color:var(--text-muted); font-size:0.75rem; display:block; margin-top:6px;">Can be overridden per-row below.</small>
                </div>

                <div class="form-group">
                    <label class="form-label">Default Remarks</label>
                    <div class="input-with-btn">
                        <input type="text" id="default_remarks" class="form-control" placeholder="e.g. Routine Booster">
                        <button type="button" class="btn-mini" onclick="updateAllRemarks()"><i class="fa-solid fa-check"></i> Apply</button>
                    </div>
                </div>

                <div style="border-top: 1px dashed var(--border); margin: 1.5rem 0;"></div>

                <div class="form-group">
                    <label class="form-label">Administered By (Personnel)</label>
                    <select id="administered_by" class="form-control">
                        <option value="">— Select Person —</option>
                        <?php foreach($personnel as $p): ?>
                            <option value="<?= htmlspecialchars($p['FULL_NAME']) ?>">
                                <?= htmlspecialchars($p['FULL_NAME']) ?> (<?= htmlspecialchars($p['POSITION']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Date &amp; Time Administered</label>
                    <input type="text" id="vaccination_date" class="form-control date-picker" placeholder="Select Date & Time">
                </div>

                <div class="summary-box">
                    <div class="summary-row">
                        <span>Animals Selected:</span>
                        <span id="sum-count">0</span>
                    </div>
                    <div class="summary-total">
                        <span>Total Vol Required:</span>
                        <span id="sum-total">0 units</span>
                    </div>
                </div>

                <button type="button" class="btn-submit" id="btn-submit" onclick="submitBatch()" disabled>
                    <i class="fa-solid fa-floppy-disk"></i> Record Vaccination
                </button>
            </form>
        </div>

        <div class="workspace-panel">

            <div class="picker-section" id="pickerSection">
                <div class="section-header">
                    <div class="section-title"><i class="fa-solid fa-paw"></i> Step 3: Click to Add Animals</div>
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
                    <div class="section-title"><i class="fa-solid fa-list-check"></i> Step 4: Confirm Dosages</div>
                    <button class="btn-clear" onclick="clearTable()"><i class="fa-solid fa-trash-can"></i> Clear All</button>
                </div>
                <div style="overflow-x: auto; width: 100%;">
                    <table class="custom-table">
                        <thead>
                            <tr>
                                <th style="width:15%; padding-left: 1.5rem;">Tag No</th>
                                <th style="width:25%;">Dosage (Qty)</th>
                                <th>Remarks (Optional)</th>
                                <th style="width:50px; text-align:center;"></th>
                            </tr>
                        </thead>
                        <tbody id="vaccination-list">
                            <tr id="empty-row">
                                <td colspan="4" style="text-align:center;padding:3rem 1rem;color:var(--text-muted); font-style:italic;">
                                    <i class="fa-solid fa-arrow-up" style="font-size: 2rem; display: block; margin-bottom: 1rem; opacity: 0.5;"></i>
                                    Click on animals above to add them to the vaccination list.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="table-section">
                <div class="section-header" style="padding: 1.5rem 1.5rem 1rem 1.5rem; border-bottom:1px solid var(--border); margin:0;">
                    <div class="section-title"><i class="fa-solid fa-clock-rotate-left"></i> Recent Vaccination Logs</div>
                </div>
                
                <div class="history-filters">
                    <input type="text" id="histSearch" class="filter-input" placeholder="Search Tag or Person..." oninput="loadHistory(1)">
                    <?php if($USER_LOCATION_ == 1000): ?>
                    <select id="histLoc" class="filter-input" onchange="loadHistory(1)">
                        <option value="">All Locations</option>
                        <?php foreach($locs as $l): ?>
                            <option value="<?= $l['LOCATION_ID'] ?>"><?= htmlspecialchars($l['LOCATION_NAME']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php endif; ?>
                    <input type="text" id="histFrom" class="filter-input date-picker" placeholder="Date From...">
                    <input type="text" id="histTo"   class="filter-input date-picker" placeholder="Date To...">
                </div>

                <div style="overflow-x: auto; width: 100%;">
                    <table class="custom-table">
                        <thead>
                            <tr>
                                <th style="padding-left: 1.5rem;">Date</th>
                                <th>Tag</th>
                                <th>Administered By</th>
                                <th>Vaccine</th>
                                <th>Dosage</th>
                                <th>Remarks</th>
                                <th style="text-align:right; padding-right:1.5rem;">Cost</th>
                            </tr>
                        </thead>
                        <tbody id="history-list">
                            <tr><td colspan="7" style="text-align:center;padding:3rem 1rem;color:var(--text-muted);"><i class="fa-solid fa-spinner fa-spin me-2"></i> Loading history...</td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="pagination" id="pagination"></div>
            </div>

        </div>
    </div>
</div>

<script>
/* ═══════════════════════════════════════════════════════════════
   STATE
═══════════════════════════════════════════════════════════════ */
let selectedAnimals   = new Set();
let currentPenAnimals = [];
let schedulerMode     = false;
let fpVaccineDate; 

const incomingEventIds = "<?= htmlspecialchars($event_ids) ?>".trim();
const USER_LOCATION = <?php echo json_encode($USER_LOCATION_); ?>;

/* ═══════════════════════════════════════════════════════════════
   INIT
═══════════════════════════════════════════════════════════════ */
document.addEventListener('DOMContentLoaded', () => {
    fpVaccineDate = flatpickr("#vaccination_date", {
        enableTime: true,
        dateFormat: "Y-m-d H:i", 
        altInput: true,
        altFormat: "M j, Y h:i K", 
        allowInput: true
    });
    fpVaccineDate.clear();

    flatpickr("#histFrom", { dateFormat:"Y-m-d", altInput:true, altFormat:"M j, Y", onChange: () => loadHistory(1) });
    flatpickr("#histTo",   { dateFormat:"Y-m-d", altInput:true, altFormat:"M j, Y", onChange: () => loadHistory(1) });

    if (incomingEventIds) {
        schedulerMode = true;
        handleEventAutoSelect(incomingEventIds);
    } else if (USER_LOCATION != 1000) {
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

/* ═══════════════════════════════════════════════════════════════
   BANNER HELPERS
═══════════════════════════════════════════════════════════════ */
function showBanner(type, msg) {
    const el      = document.getElementById('sync-alert');
    el.className  = type;
    
    let icon = '<i class="fa-solid fa-circle-check"></i>';
    if(type === 'error') icon = '<i class="fa-solid fa-circle-xmark"></i>';
    if(type === 'loading') icon = '<i class="fa-solid fa-spinner fa-spin"></i>';

    document.getElementById('sync-msg').innerHTML = `${icon} ${msg}`;
    el.style.display = 'block';
}
function hideBanner() {
    const el = document.getElementById('sync-alert');
    el.className = '';
    el.style.display = 'none';
}

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

/* ═══════════════════════════════════════════════════════════════
   AUTO-SELECT  ── Scheduler / Blocker entry point
═══════════════════════════════════════════════════════════════ */
async function handleEventAutoSelect(eventIds) {
    showBanner('loading', 'Auto-Sync Active: Loading scheduled animals and vaccine…');
    try {
        const res  = await fetch(`../process/eventManager.php?action=get_events_details&ids=${eventIds}`);
        const data = await res.json();

        if (!data.success || !data.events || data.events.length === 0) {
            showBanner('error', 'No event details returned.');
            return;
        }

        const ev       = data.events[0];
        const locId    = String(ev.LOCATION_ID);
        const bldgId   = String(ev.BUILDING_ID);
        const penId    = String(ev.PEN_ID);
        const itemId   = String(ev.ITEM_ID);

        if (!setSelectValue('location_id', locId)) {
            showBanner('error', `Location ID "${locId}" not in dropdown.`); return;
        }
        lockField('wrap-location', 'location_id');

        await fetchBuildings(locId);
        if (!setSelectValue('building_id', bldgId)) {
            showBanner('error', `Building ID "${bldgId}" not found.`); return;
        }
        lockField('wrap-building', 'building_id');

        await fetchPens(bldgId);
        if (!setSelectValue('pen_id', penId)) {
            showBanner('error', `Pen ID "${penId}" not found.`); return;
        }
        lockField('wrap-pen', 'pen_id');

        await fetchVaccines(locId);
        if (!setSelectValue('vaccine_id', itemId)) {
            showBanner('error', `Vaccine ID "${itemId}" not found in this location.`); return;
        }
        lockField('wrap-vaccine', 'vaccine_id');
        updateCalculations();

        data.events.forEach(e => {
            if (!selectedAnimals.has(String(e.ANIMAL_ID))) {
                addAnimalToTable({ ANIMAL_ID: e.ANIMAL_ID, TAG_NO: e.TAG_NO });
            }
        });

        document.getElementById('ctx-loc').innerHTML  = ev.LOCATION_NAME || locId;
        document.getElementById('ctx-bldg').innerHTML = ev.BUILDING_NAME || bldgId;
        document.getElementById('ctx-pen').innerHTML  = ev.PEN_NAME      || penId;
        document.getElementById('ctx-vax').innerHTML  = ev.ITEM_NAME     || itemId;
        document.getElementById('context-card').classList.add('show');

        document.getElementById('pickerSection').style.display = 'none';
        document.getElementById('lock-banner').classList.add('show');

        showBanner('success', `${data.events.length} animal(s) loaded from schedule — review dosages and save.`);
        setTimeout(hideBanner, 5000);
        document.querySelector('.table-section').scrollIntoView({ behavior: 'smooth', block: 'start' });

    } catch (err) {
        showBanner('error', `Auto-sync failed. Selected animals manually.`);
    }
}

/* ═══════════════════════════════════════════════════════════════
   LIVE REFRESH
═══════════════════════════════════════════════════════════════ */
async function refreshVaccineList() {
    const locId = document.getElementById('location_id').value;
    if (!locId) { showToast("Please select a Location first.", "error"); return; }

    const btn = document.getElementById('refresh-vax-btn');
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
    btn.disabled = true;

    try {
        const currentSelection = document.getElementById('vaccine_id').value;
        await fetchVaccines(locId);
        if (currentSelection) setSelectValue('vaccine_id', currentSelection);
        updateCalculations(); 
        
        btn.innerHTML = '<i class="fa-solid fa-rotate-right"></i> Sync Options';
        btn.disabled = false;
        showToast("Vaccine inventory synced.", "success");
    } catch (e) {
        btn.innerHTML = '<i class="fa-solid fa-xmark"></i> Error';
        showToast("Failed to sync inventory.", "error");
        setTimeout(() => { btn.innerHTML = '<i class="fa-solid fa-rotate-right"></i> Sync Options'; btn.disabled = false; }, 2000);
    }
}

/* ═══════════════════════════════════════════════════════════════
   FETCH HELPERS 
═══════════════════════════════════════════════════════════════ */
function fetchBuildings(locId) {
    return new Promise((resolve, reject) => {
        const sel = document.getElementById('building_id');
        sel.innerHTML = '<option value="">Loading buildings…</option>';
        sel.disabled  = true;
        document.getElementById('pen_id').innerHTML = '<option value="">-- Select Pen --</option>';
        document.getElementById('pen_id').disabled  = true;

        if (!locId) { sel.innerHTML = '<option value="">-- Select Building --</option>'; resolve([]); return; }

        fetch(`../process/getBuildingsByLocation.php?location_id=${locId}`)
            .then(r => r.json())
            .then(data => {
                sel.innerHTML = '<option value="">-- Select Building --</option>';
                const list = data.buildings || data || [];
                list.forEach(b => sel.add(new Option(b.BUILDING_NAME, b.BUILDING_ID)));
                sel.disabled = false;
                resolve(list);
            })
            .catch(err => { sel.innerHTML = '<option value="">Error</option>'; reject(err); });
    });
}

function fetchPens(bldgId) {
    return new Promise((resolve, reject) => {
        const sel = document.getElementById('pen_id');
        sel.innerHTML = '<option value="">Loading pens…</option>';
        sel.disabled  = true;

        if (!bldgId) { sel.innerHTML = '<option value="">-- Select Pen --</option>'; resolve([]); return; }

        fetch(`../process/getPensByBuilding.php?building_id=${bldgId}`)
            .then(r => r.json())
            .then(data => {
                sel.innerHTML = '<option value="">-- Select Pen --</option>';
                const list = data.pens || data || [];
                list.forEach(p => sel.add(new Option(p.PEN_NAME, p.PEN_ID)));
                sel.disabled = false;
                resolve(list);
            })
            .catch(err => { sel.innerHTML = '<option value="">Error</option>'; reject(err); });
    });
}

function fetchVaccines(locId) {
    return new Promise((resolve, reject) => {
        const sel = document.getElementById('vaccine_id');
        sel.innerHTML = '<option value="" data-stock="0">Loading vaccines…</option>';
        sel.disabled  = true;
        document.getElementById('stock-display').innerHTML = '';

        if (!locId) { sel.innerHTML = '<option value="" data-stock="0">Select Location First</option>'; resolve([]); return; }

        fetch(`?action=get_vaccines&location_id=${locId}`)
            .then(r => r.json())
            .then(data => {
                sel.innerHTML = '<option value="" data-stock="0">-- Select Vaccine --</option>';
                // Handle both missing data and empty arrays gracefully
                const list = Array.isArray(data) ? data : [];
                
                list.forEach(v => {
                    const opt          = new Option(`${v.SUPPLY_NAME} (Stock: ${v.TOTAL_STOCK} ${v.UNIT_ABBR})`, v.SUPPLY_ID);
                    opt.dataset.stock  = v.TOTAL_STOCK;
                    opt.dataset.unit   = v.UNIT_ABBR;
                    opt.dataset.unitId = v.UNIT_ID;
                    sel.appendChild(opt);
                });
                sel.disabled = false;
                resolve(list);
            })
            .catch(err => { sel.innerHTML = '<option value="">Error</option>'; reject(err); });
    });
}

/* ═══════════════════════════════════════════════════════════════
   MANUAL CASCADE 
═══════════════════════════════════════════════════════════════ */
function handleLocationChange(locId) {
    Array.from(selectedAnimals).forEach(id => removeAnimal(id));
    document.getElementById('building_id').innerHTML = '<option value="">-- Select Building --</option>';
    document.getElementById('building_id').disabled  = true;
    document.getElementById('pen_id').innerHTML      = '<option value="">-- Select Pen --</option>';
    document.getElementById('pen_id').disabled       = true;
    document.getElementById('vaccine_id').innerHTML  = '<option value="" data-stock="0">Select Location First</option>';
    document.getElementById('vaccine_id').disabled   = true;
    document.getElementById('stock-display').innerHTML = '';

    if (!locId) return;
    fetchBuildings(locId);
    fetchVaccines(locId);
}

function handleBuildingChange(bldgId) {
    document.getElementById('pen_id').innerHTML = '<option value="">-- Select Pen --</option>';
    document.getElementById('pen_id').disabled  = true;
    if (!bldgId) return;
    fetchPens(bldgId);
}

function loadAnimals(penId) {
    const grid    = document.getElementById('animal-grid');
    const wrapper = document.getElementById('select-all-wrapper');
    grid.innerHTML        = '<div style="grid-column:1/-1;text-align:center;color:var(--text-muted);"><i class="fa-solid fa-spinner fa-spin me-2"></i> Loading animals…</div>';
    wrapper.style.display = 'none';
    if (!penId) return;

    fetch(`?action=get_animals&pen_id=${penId}`)
        .then(r => r.json())
        .then(data => {
            grid.innerHTML    = '';
            currentPenAnimals = Array.isArray(data) ? data : [];

            if (!currentPenAnimals.length) {
                grid.innerHTML = '<div style="grid-column:1/-1;text-align:center;color:var(--text-muted); padding: 2rem; font-style:italic;">No active animals found in this pen.</div>';
                return;
            }
            wrapper.style.display = 'flex';
            updateSelectAllState();

            currentPenAnimals.forEach(a => {
                const card     = document.createElement('div');
                card.className = `animal-card ${selectedAnimals.has(String(a.ANIMAL_ID)) ? 'in-table' : ''}`;
                card.id        = `card-${a.ANIMAL_ID}`;
                card.onclick   = () => addAnimalToTable(a);
                card.innerHTML = `<i class="fa-solid fa-paw"></i><div class="tag">${a.TAG_NO}</div>`;
                grid.appendChild(card);
            });
        });
}

function toggleSelectAll(cb) {
    if (cb.checked) currentPenAnimals.forEach(a => { if (!selectedAnimals.has(String(a.ANIMAL_ID))) addAnimalToTable(a); });
    else currentPenAnimals.forEach(a => removeAnimal(a.ANIMAL_ID));
}
function updateSelectAllState() {
    const cb = document.getElementById('select-all-check');
    if (!cb || !currentPenAnimals.length) return;
    cb.checked = currentPenAnimals.every(a => selectedAnimals.has(String(a.ANIMAL_ID)));
}

/* ═══════════════════════════════════════════════════════════════
   TABLE OPS
═══════════════════════════════════════════════════════════════ */
function addAnimalToTable(animal) {
    if (selectedAnimals.has(String(animal.ANIMAL_ID))) return;

    const emptyRow = document.getElementById('empty-row');
    if (emptyRow) emptyRow.remove();

    const defDose = document.getElementById('default_dosage').value;
    const defRem  = document.getElementById('default_remarks').value;

    const tr      = document.createElement('tr');
    tr.id         = `row-${animal.ANIMAL_ID}`;
    tr.dataset.id = String(animal.ANIMAL_ID);
    tr.innerHTML  = `
        <td data-label="Tag No" style="font-weight:700; font-family:var(--font-mono); padding-left: 1.5rem;">${animal.TAG_NO}</td>
        <td data-label="Dosage (Qty)"><input type="number" class="dosage-input form-control" value="${defDose}"
                   step="0.01" min="0.01" oninput="updateCalculations()"></td>
        <td data-label="Remarks (Optional)"><input type="text" class="rem-input form-control" value="${defRem}" placeholder="Notes…"></td>
        <td style="text-align:center;">
            <button type="button" class="btn-remove" onclick="removeAnimal(${animal.ANIMAL_ID})" title="Remove"><i class="fa-solid fa-xmark"></i></button>
        </td>`;
    document.getElementById('vaccination-list').appendChild(tr);

    selectedAnimals.add(String(animal.ANIMAL_ID));
    const card = document.getElementById(`card-${animal.ANIMAL_ID}`);
    if (card) card.classList.add('in-table');

    updateCalculations();
    updateSelectAllState();
}

function removeAnimal(id) {
    const row = document.getElementById(`row-${id}`);
    if (row) row.remove();
    selectedAnimals.delete(String(id));

    const card = document.getElementById(`card-${id}`);
    if (card) card.classList.remove('in-table');

    if (!selectedAnimals.size) {
        document.getElementById('vaccination-list').innerHTML =
            '<tr id="empty-row"><td colspan="4" style="text-align:center;padding:3rem 1rem;color:var(--text-muted); font-style:italic;"><i class="fa-solid fa-arrow-up" style="font-size: 2rem; display: block; margin-bottom: 1rem; opacity: 0.5;"></i>Click on animals above to add them to the vaccination list.</td></tr>';
    }
    updateCalculations();
    updateSelectAllState();
}

function clearTable() {
    if (!selectedAnimals.size) return;
    if (!confirm('Clear all rows?')) return;
    Array.from(selectedAnimals).forEach(id => removeAnimal(id));
}

function updateAllDosages() {
    const v = document.getElementById('default_dosage').value;
    document.querySelectorAll('.dosage-input').forEach(i => i.value = v);
    updateCalculations();
}
function updateAllRemarks() {
    const v = document.getElementById('default_remarks').value;
    document.querySelectorAll('.rem-input').forEach(i => i.value = v);
}

/* ═══════════════════════════════════════════════════════════════
   CALCULATIONS
═══════════════════════════════════════════════════════════════ */
function updateCalculations() {
    const sel = document.getElementById('vaccine_id');
    const btn = document.getElementById('btn-submit');

    if (!sel.value) {
        document.getElementById('stock-display').innerHTML = '';
        btn.disabled = true;
        return;
    }

    const opt   = sel.options[sel.selectedIndex];
    const stock = parseFloat(opt.dataset.stock) || 0;
    const unit  = opt.dataset.unit || 'units';

    let total = 0;
    document.querySelectorAll('.dosage-input').forEach(i => total += parseFloat(i.value) || 0);

    document.getElementById('sum-count').textContent = selectedAnimals.size;
    document.getElementById('sum-total').textContent = `${total.toFixed(2)} ${unit}`;

    const display = document.getElementById('stock-display');
    if (total > stock) {
        display.innerHTML = `<span class="stock-low"><i class="fa-solid fa-triangle-exclamation"></i> Deficit: ${stock} available, need ${total.toFixed(2)}</span>`;
        btn.disabled      = true;
    } else {
        display.innerHTML = `<span class="stock-ok"><i class="fa-solid fa-boxes-stacked"></i> Stock OK: ${stock} ${unit} available</span>`;
        if (selectedAnimals.size > 0 && total > 0) {
            btn.disabled    = false;
        } else {
            btn.disabled    = true;
        }
    }
}

/* ═══════════════════════════════════════════════════════════════
   HISTORY
═══════════════════════════════════════════════════════════════ */
async function loadHistory(page) {
    const list = document.getElementById('history-list');
    const pg   = document.getElementById('pagination');
    list.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:3rem 1rem;color:var(--text-muted);"><i class="fa-solid fa-spinner fa-spin me-2"></i> Loading history...</td></tr>';

    const search = document.getElementById('histSearch').value;
    const loc    = document.getElementById('histLoc')?.value || '';
    const from   = document.getElementById('histFrom').value;
    const to     = document.getElementById('histTo').value;

    try {
        const res = await fetch(`?action=get_vaccination_history&p=${page}&search=${encodeURIComponent(search)}&loc_filter=${loc}&date_from=${from}&date_to=${to}`);
        const result = await res.json();

        if (!result.success || !result.data) {
            list.innerHTML = `<tr><td colspan="7" style="text-align:center;color:var(--red);">Error: ${result.error || 'Unknown error'}</td></tr>`;
            if (pg) pg.innerHTML = '';
            return;
        }

        if (!result.data.length) {
            list.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:3rem 1rem;color:var(--text-muted); font-style:italic;"><i class="fa-solid fa-ghost display-block margin-bottom opacity-50 font-size-2rem"></i> No vaccination records found.</td></tr>';
            if (pg) pg.innerHTML = '';
            return;
        }

        list.innerHTML = result.data.map(row => `
            <tr>
                <td data-label="Date" style="font-size:0.9rem; color:var(--text-secondary); font-family:var(--font-mono); padding-left: 1.5rem;">${row.FORMATTED_DATE}</td>
                <td data-label="Tag"><span style="background:var(--purple-dim); color:var(--purple); padding:4px 10px; border-radius:6px; font-weight:700; font-family:var(--font-mono); border:1px solid rgba(168,85,247,0.3);"><i class="fa-solid fa-tag"></i> ${row.TAG_NO}</span></td>
                <td data-label="Admin By" style="font-size:0.95rem; color:var(--text-primary);">${row.ADMINISTERED_BY || '—'}</td>
                <td data-label="Vaccine" style="font-weight:700; color:var(--text-primary);">${row.SUPPLY_NAME || '—'}</td>
                <td data-label="Dosage" style="font-size:1.05rem; font-family:var(--font-mono); font-weight:700; text-align:center; color:var(--emerald);">${row.DOSAGE_ML ?? '—'}</td>
                <td data-label="Remarks" style="font-size:0.9rem; color:var(--text-muted);">${row.REMARKS || '—'}</td>
                <td data-label="Cost" style="color:var(--amber); font-weight:700; font-family:var(--font-mono); white-space:nowrap; padding-right:1.5rem; text-align:right;">₱ ${parseFloat(row.TOTAL_COST || row.VACCINATION_COST || 0).toFixed(2)}</td>
            </tr>
        `).join('');

        if (pg) {
            pg.innerHTML = '';
            for (let i = 1; i <= result.pages; i++) {
                const btn = document.createElement('button');
                btn.className = `pg-btn ${i === result.curr ? 'active' : ''}`;
                btn.textContent = i;
                btn.onclick = () => loadHistory(i);
                pg.appendChild(btn);
            }
        }
    } catch (e) {
        list.innerHTML = '<tr><td colspan="7" style="text-align:center;color:var(--red);">System Error. Check console.</td></tr>';
    }
}

/* ═══════════════════════════════════════════════════════════════
   SUBMISSION
═══════════════════════════════════════════════════════════════ */
async function submitBatch() {
    if (!confirm(`Proceed with vaccination for ${selectedAnimals.size} animal(s)? Inventory will be deducted.`)) return;

    const dateInput = document.getElementById('vaccination_date').value;
    if(!dateInput) {
        showToast("Please select a valid Date Administered.", "error");
        return;
    }

    const btn = document.getElementById('btn-submit');
    const ogText = btn.innerHTML;
    btn.disabled    = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Processing…';

    const vacOpt  = document.getElementById('vaccine_id').selectedOptions[0];
    const records = [];

    document.querySelectorAll('#vaccination-list tr[id^="row-"]').forEach(tr => {
        records.push({
            animal_id: tr.dataset.id,
            quantity : tr.querySelector('.dosage-input').value,
            remarks  : tr.querySelector('.rem-input').value
        });
    });

    const payload = {
        records        : records,
        vaccine_id     : vacOpt.value,
        unit_id        : vacOpt.dataset.unitId,
        administered_by: document.getElementById('administered_by').value,
        date           : dateInput,
        event_ids      : incomingEventIds
    };

    try {
        const res  = await fetch('../process/addBatchVaccination.php', {
            method : 'POST',
            headers: { 'Content-Type': 'application/json' },
            body   : JSON.stringify(payload)
        });
        const data = await res.json();

        if (!data.success) {
            showToast('Error: ' + (data.message || 'Unknown error'), "error");
            btn.disabled    = false;
            btn.innerHTML = ogText;
            return;
        }

        if (incomingEventIds) {
            try {
                const fd = new FormData();
                fd.append('action',    'mark_events_done');
                fd.append('event_ids', incomingEventIds);
                await fetch('../process/eventManager.php', { method: 'POST', body: fd });
            } catch (e) {
                console.warn('mark_events_done (non-fatal):', e);
            }
        }

        showToast('Batch vaccination recorded successfully!', "success");
        loadHistory(1); 
        setTimeout(() => {
            window.location.href = incomingEventIds ? 'events_scheduler.php' : window.location.pathname;
        }, 1500);

    } catch (e) {
        console.error(e);
        showToast('System connection error. Please try again.', "error");
        btn.disabled    = false;
        btn.innerHTML = ogText;
    }
}
</script>
</body>
</html>