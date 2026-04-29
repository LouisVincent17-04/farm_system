<?php
// views/purch_breeding_reproduction.php
error_reporting(0);
ini_set('display_errors', 0);
include '../config/Connection.php';

include '../security/checkAccess.php';
checkAccess('purchases');
$page="transactions";
include '../common/navbar.php';
include '../common/chat_support.php';
include '../functions/getUsersLocation.php'; // ADDED LOCATION FUNCTION

// --- CONFIGURATION ---
$ITEM_TYPE_ID = 6; // Breeding & Reproduction Supplies
// ---------------------

try {
    if (!isset($conn)) {
        throw new Exception("Database connection failed.");
    }

    $items_sql = "";

    // 1. Fetch Items based on Location Access
    if ($USER_LOCATION_ != 1000) {
        $items_sql = "SELECT i.*, 
                  it.ITEM_TYPE_NAME,
                  u.UNIT_NAME,
                  DATE_FORMAT(i.DATE_OF_PURCHASE, '%m/%d/%Y') as DATE_OF_PURCHASE_FMT,
                  DATE_FORMAT(i.CREATED_AT, '%m/%d/%Y %h:%i %p') as CREATED_AT_FMT
                  FROM ITEMS i
                  LEFT JOIN ITEM_TYPES it ON i.ITEM_TYPE_ID = it.ITEM_TYPE_ID
                  LEFT JOIN UNITS u ON i.UNIT_ID = u.UNIT_ID
                  WHERE i.ITEM_TYPE_ID = :type_id AND LOCATION_ID = :location_id
                  ORDER BY i.CREATED_AT DESC";

        $stmt = $conn->prepare($items_sql);
        $stmt->execute([':type_id' => $ITEM_TYPE_ID, ':location_id' => $USER_LOCATION_]);
        $items_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 2. Fetch Units
        $units_sql = "SELECT * FROM UNITS ORDER BY UNIT_NAME ASC";
        $stmt = $conn->prepare($units_sql);
        $stmt->execute();
        $units = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 3. Location Hierarchy (Restricted to user's location)
        $loc_sql = "SELECT * FROM LOCATIONS WHERE LOCATION_ID = :location_id ORDER BY LOCATION_NAME ASC";
        $stmt = $conn->prepare($loc_sql);
        $stmt->execute([':location_id' => $USER_LOCATION_]);
        $locations = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $bldg_sql = "SELECT * FROM BUILDINGS ORDER BY BUILDING_NAME ASC";
        $stmt = $conn->prepare($bldg_sql);
        $stmt->execute();
        $buildings_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $pens_sql = "SELECT * FROM PENS ORDER BY PEN_NAME ASC";
        $stmt = $conn->prepare($pens_sql);
        $stmt->execute();
        $pens_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } else {
        $items_sql = "SELECT i.*, 
                it.ITEM_TYPE_NAME,
                u.UNIT_NAME,
                DATE_FORMAT(i.DATE_OF_PURCHASE, '%m/%d/%Y') as DATE_OF_PURCHASE_FMT,
                DATE_FORMAT(i.CREATED_AT, '%m/%d/%Y %h:%i %p') as CREATED_AT_FMT
                FROM ITEMS i
                LEFT JOIN ITEM_TYPES it ON i.ITEM_TYPE_ID = it.ITEM_TYPE_ID
                LEFT JOIN UNITS u ON i.UNIT_ID = u.UNIT_ID
                WHERE i.ITEM_TYPE_ID = :type_id
                ORDER BY i.CREATED_AT DESC";

        $stmt = $conn->prepare($items_sql);
        $stmt->execute([':type_id' => $ITEM_TYPE_ID]);
        $items_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 2. Fetch Units
        $units_sql = "SELECT * FROM UNITS ORDER BY UNIT_NAME ASC";
        $stmt = $conn->prepare($units_sql);
        $stmt->execute();
        $units = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 3. Location Hierarchy (All locations)
        $loc_sql = "SELECT * FROM LOCATIONS ORDER BY LOCATION_NAME ASC";
        $stmt = $conn->prepare($loc_sql);
        $stmt->execute();
        $locations = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $bldg_sql = "SELECT * FROM BUILDINGS ORDER BY BUILDING_NAME ASC";
        $stmt = $conn->prepare($bldg_sql);
        $stmt->execute();
        $buildings_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $pens_sql = "SELECT * FROM PENS ORDER BY PEN_NAME ASC";
        $stmt = $conn->prepare($pens_sql);
        $stmt->execute();
        $pens_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (Exception $e) {
    $items_data = [];
    $units = [];
    $locations = [];
    $buildings_raw = [];
    $pens_raw = [];
    echo "<script>console.error('Database Error: " . addslashes($e->getMessage()) . "');</script>";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Breeding & Reproduction Supplies | FarmPro</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />

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
            --border-active:  rgba(249,115,22,0.5); /* Orange Accent */
            
            --orange:         #f97316;
            --orange-dim:     rgba(249,115,22,0.12);
            --orange-glow:    rgba(249,115,22,0.25);
            --emerald:        #10b981;
            --emerald-dim:    rgba(16,185,129,0.12);
            --amber:          #f59e0b;
            --amber-dim:      rgba(245,158,11,0.12);
            --amber-glow:     rgba(245,158,11,0.25);
            --blue:           #3b82f6;
            --blue-dim:       rgba(59,130,246,0.12);
            --red:            #f87171;
            --red-dim:        rgba(248,113,113,0.12);
            
            --text-primary:   #f1f5f9;
            --text-secondary: #94a3b8;
            --text-muted:     #475569;
            
            --radius-md:      10px;
            --radius-lg:      14px;
            --radius-xl:      20px;
            --shadow-md:      0 4px 16px rgba(0,0,0,0.4);
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
            background-image: radial-gradient(ellipse 80% 50% at 50% -20%, rgba(249,115,22,0.06) 0%, transparent 60%);
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
            color: var(--orange); background: var(--orange-dim); border: 1px solid rgba(249,115,22,0.2);
            padding: 6px 12px; border-radius: 99px;
        }

        /* ─── HEADER ─── */
        .page-header { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 2rem; gap: 1.5rem; flex-wrap: wrap; }
        .header-info h1 {
            font-size: clamp(1.6rem, 3vw, 2.2rem); font-weight: 700;
            color: var(--text-primary); letter-spacing: -0.03em; line-height: 1.1; margin-bottom: 0.25rem;
        }
        .header-info h1 span {
            background: linear-gradient(135deg, var(--orange), #ea580c);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }
        .header-info p { color: var(--text-secondary); font-size: 0.95rem; }

        /* ─── BUTTONS ─── */
        .header-actions { display: flex; gap: 12px; flex-wrap: wrap; align-items: center; }
        
        .btn {
            display: inline-flex; align-items: center; justify-content: center; gap: 8px;
            padding: 10px 20px; border-radius: var(--radius-md); font-size: 0.9rem;
            font-weight: 600; font-family: var(--font); border: 1px solid transparent;
            cursor: pointer; transition: all var(--transition); text-decoration: none; white-space: nowrap;
        }
        
        .btn-primary { background: var(--orange); color: #fff; }
        .btn-primary:hover { background: #fdba74; box-shadow: 0 0 16px var(--orange-glow); color: #000; transform: translateY(-1px); }
        
        .btn-amber { background: var(--amber); color: #000; }
        .btn-amber:hover { background: #fbbf24; box-shadow: 0 0 16px var(--amber-glow); transform: translateY(-1px); }

        .btn-ghost { background: transparent; color: var(--text-secondary); border-color: var(--border); }
        .btn-ghost:hover { background: var(--bg-elevated); color: var(--text-primary); border-color: rgba(255,255,255,0.15); }

        /* ─── SEARCH BAR ─── */
        .search-container { position: relative; margin-bottom: 1.5rem; }
        .search-icon { position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--text-muted); width: 18px; height: 18px; pointer-events: none; }
        .search-input {
            width: 100%; padding: 12px 12px 12px 2.8rem; background: var(--bg-surface);
            border: 1px solid var(--border); border-radius: var(--radius-lg); color: var(--text-primary);
            font-size: 0.95rem; font-family: var(--font); outline: none; transition: all var(--transition);
        }
        .search-input:focus { border-color: var(--orange); box-shadow: 0 0 0 3px var(--orange-glow); }

        /* ─── TABLE ─── */
        .table-card {
            background: var(--bg-surface); border: 1px solid var(--border);
            border-radius: var(--radius-xl); overflow: hidden;
            box-shadow: 0 10px 30px -10px rgba(0,0,0,0.5);
        }
        .table-wrap { overflow-x: auto; }
        .table { width: 100%; border-collapse: collapse; min-width: 1100px; }
        .table thead th {
            background: var(--bg-elevated); color: var(--text-muted);
            font-size: 0.7rem; font-weight: 700; text-transform: uppercase;
            letter-spacing: 0.07em; padding: 14px 16px; text-align: left;
            border-bottom: 1px solid var(--border); white-space: nowrap;
        }
        .table tbody tr { border-bottom: 1px solid var(--border); transition: background var(--transition); }
        .table tbody tr:last-child { border-bottom: none; }
        .table tbody tr:hover { background: rgba(255,255,255,0.02); }
        .table td { padding: 14px 16px; font-size: 0.9rem; color: var(--text-primary); vertical-align: middle; }

        .ref-no { font-family: var(--font-mono); color: var(--text-muted); font-size: 0.85rem; }
        .supplier-name { color: var(--text-secondary); font-size: 0.9rem; }
        .item-name { font-weight: 700; color: #fff; font-size: 1rem; margin-bottom: 2px; }
        .item-unit { color: var(--text-secondary); font-size: 0.85rem; }
        .val-mono { font-family: var(--font-mono); font-weight: 600; font-size: 0.9rem; }
        .val-money { font-family: var(--font-mono); font-weight: 600; color: var(--amber); font-size: 0.95rem;}
        
        .confirmed-badge {
            display: inline-flex; align-items: center; justify-content: center; gap: 4px;
            background: var(--emerald-dim); color: var(--emerald); border: 1px solid rgba(16,185,129,0.2);
            border-radius: 6px; padding: 6px 12px; font-weight: 700; font-size: 0.75rem; text-transform: uppercase; width: 100%;
        }
        .confirm-btn {
            background: rgba(239, 68, 68, 0.1); color: var(--red); border: 1px solid rgba(239,68,68,0.3);
            padding: 6px 12px; border-radius: 6px; font-weight: 700; font-size: 0.75rem; cursor: pointer;
            transition: all var(--transition); width: 100%; text-transform: uppercase;
        }
        .confirm-btn:hover { background: var(--red); color: #fff; box-shadow: 0 4px 12px rgba(239,68,68,0.3); }

        /* Categories */
        .category-badge { display: inline-block; padding: 4px 10px; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; white-space: nowrap; text-transform: uppercase; }
        .category-consumable { background: var(--blue-dim); color: var(--blue); border: 1px solid rgba(59,130,246,0.2); }
        .category-nonconsumable { background: var(--emerald-dim); color: var(--emerald); border: 1px solid rgba(16,185,129,0.2); }

        /* Actions */
        .actions { display: flex; gap: 8px; justify-content: center; }
        .action-btn {
            width: 32px; height: 32px; border-radius: 6px; border: 1px solid var(--border);
            background: var(--bg-elevated); display: inline-flex; align-items: center; justify-content: center;
            cursor: pointer; transition: all var(--transition); color: var(--text-secondary); text-decoration: none;
        }
        .action-btn:hover { background: var(--bg-hover); color: var(--text-primary); }
        .action-btn.view:hover { color: var(--emerald); border-color: var(--emerald); }
        .action-btn.edit:hover { color: var(--orange); border-color: var(--orange); }
        .action-btn.delete:hover { color: var(--red); border-color: var(--red); }

        .empty-state { text-align: center; padding: 4rem 2rem; color: var(--text-muted); }
        .empty-state i { font-size: 2.5rem; margin-bottom: 1rem; opacity: 0.3; display: block; }

        /* ═══════════════════════════════════════════════
           MODAL SYSTEM — Overflow-Safe, Zoom-Resistant
        ═══════════════════════════════════════════════ */

        .modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.85);
            backdrop-filter: blur(5px);
            -webkit-backdrop-filter: blur(5px);
            z-index: 1100;
            overflow-x: hidden;
            overflow-y: auto;
            padding: 80px 1rem 2rem;
        }
        .modal.show { display: flex; align-items: flex-start; justify-content: center; }

        .modal-content {
            background: var(--bg-surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-xl);
            width: 100%;
            max-width: 650px;
            margin: 0 auto;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.6);
            display: flex;
            flex-direction: column;
            animation: modalZoom 0.2s ease-out;
            max-height: 96vh;
        }

        @keyframes modalZoom {
            from { transform: scale(0.96); opacity: 0; }
            to   { transform: scale(1);    opacity: 1; }
        }

        .modal-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--border);
            flex-shrink: 0;
        }
        .modal-header h2 { margin: 0; font-size: 1.2rem; font-weight: 700; color: #fff; }

        .modal-body {
            padding: 1.5rem;
            overflow-y: auto;
            flex: 1 1 auto;
            min-height: 0;
        }
        
        .modal-body::-webkit-scrollbar { width: 8px; height: 8px; }
        .modal-body::-webkit-scrollbar-track { background: transparent; }
        .modal-body::-webkit-scrollbar-thumb { background: var(--text-muted); border-radius: 4px; }

        .modal-footer {
            padding: 1.1rem 1.5rem;
            border-top: 1px solid var(--border);
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            background: var(--bg-elevated);
            border-radius: 0 0 var(--radius-xl) var(--radius-xl);
            flex-shrink: 0;
        }

        /* ═══════════════════════════════════════════════
           FORM LAYOUT — Consistent Grid System
        ═══════════════════════════════════════════════ */

        .info-group { margin-bottom: 0; }
        .info-group + .info-group { margin-top: 1.75rem; }

        .info-group h3 {
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.09em;
            color: var(--orange);
            margin: 0 0 1rem 0;
            padding-bottom: 8px;
            border-bottom: 1px solid var(--border);
        }

        .form-stack {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1rem;
        }

        .form-row-3 {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
            min-width: 0;
        }
        .form-group.full-width { grid-column: 1 / -1; }

        .form-label {
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--text-secondary);
            white-space: nowrap;
        }
        .form-label span { color: var(--red); }

        .form-control,
        .form-select {
            width: 100%;
            padding: 10px 12px;
            background: var(--bg-elevated);
            border: 1px solid var(--border);
            color: var(--text-primary);
            border-radius: 8px;
            font-size: 0.9rem;
            font-family: var(--font);
            outline: none;
            transition: border-color var(--transition), box-shadow var(--transition);
            min-width: 0;
            box-sizing: border-box;
        }
        .form-control:focus,
        .form-select:focus,
        textarea.form-control:focus {
            border-color: var(--orange);
            box-shadow: 0 0 0 3px var(--orange-glow);
            background: var(--bg-hover);
        }
        textarea.form-control { resize: vertical; min-height: 72px; line-height: 1.5; }
        
        .form-select {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2394a3b8' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            cursor: pointer;
        }
        .form-select:disabled, input:disabled, input[readonly] { opacity: 0.45; cursor: not-allowed; }

        /* Autocomplete UI */
        .autocomplete-wrapper { position: relative; }
        .autocomplete-list {
            position: absolute; z-index: 1050; top: 100%; left: 0; right: 0;
            background: var(--bg-elevated); border: 1px solid var(--border);
            border-top: none; border-radius: 0 0 8px 8px; max-height: 200px;
            overflow-y: auto; box-shadow: var(--shadow-md); display: none;
        }
        .autocomplete-list.show { display: block; }
        .autocomplete-item {
            padding: 10px 14px; cursor: pointer; transition: background 0.15s;
            border-bottom: 1px solid var(--border); color: var(--text-primary); font-size: 0.9rem;
        }
        .autocomplete-item:last-child { border-bottom: none; }
        .autocomplete-item:hover { background: var(--bg-hover); color: var(--orange); }
        .autocomplete-item strong { color: var(--orange); }
        .autocomplete-loading,
        .autocomplete-no-results { padding: 12px; text-align: center; color: var(--text-muted); font-size: 0.85rem; font-style: italic; }

        /* Toast Notifications */
        #toastContainer { position: fixed; top: 20px; right: 20px; z-index: 9999; display: flex; flex-direction: column; gap: 10px; }
        .toast {
            background: var(--bg-surface); border: 1px solid var(--border); color: #fff;
            padding: 1rem 1.5rem; border-radius: var(--radius-md); box-shadow: 0 10px 25px rgba(0,0,0,0.5);
            font-size: 0.9rem; font-weight: 600; animation: slideIn 0.3s ease-out;
        }
        @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }

        /* Confirm modals */
        .confirm-content { text-align: center; padding: 1rem 1rem 0; }
        .confirm-icon { font-size: 3.5rem; margin-bottom: 1rem; display: block; opacity: 0.8; }
        .warning-text {
            color: var(--red); font-size: 0.85rem; margin: 1.25rem 0 0;
            background: var(--red-dim); padding: 12px 14px; border-radius: 8px;
            border: 1px solid rgba(248,113,113,0.2); line-height: 1.4; text-align: left;
        }

        /* Narrow modals (confirm dialogs) */
        .modal-content.narrow { max-width: 440px; }
        
        /* Alerts inside modal */
        .alert { padding: 12px 16px; border-radius: var(--radius-md); margin-bottom: 1.5rem; display: none; text-align: center; font-weight: 600; font-size: 0.9rem; }
        .alert.success { background: var(--emerald-dim); border: 1px solid rgba(16,185,129,0.3); color: var(--emerald); }
        .alert.error { background: var(--red-dim); border: 1px solid rgba(239,68,68,0.3); color: var(--red); }

        /* ─── RESPONSIVE ─── */
        @media (max-width: 768px) {
            .container { padding: 1rem; }
            .page-header { flex-direction: column; align-items: flex-start; }
            .header-actions { width: 100%; display: grid; grid-template-columns: 1fr; }
            .btn { width: 100%; justify-content: center; }

            .search-container { max-width: none; }
            
            /* Collapse all grids to single column */
            .form-row,
            .form-row-3 { grid-template-columns: 1fr; gap: 1rem;}
            
            .modal-footer { flex-direction: column-reverse; gap: 8px; }
            .modal-footer button { width: 100%; margin: 0 !important; }

            /* Mobile table → cards */
            .table-wrap { border: none; background: transparent; overflow: visible; box-shadow: none; }
            .table, .table thead, .table tbody, .table th, .table td, .table tr { display: block; width: 100%; box-sizing: border-box;}
            .table thead { display: none; }
            .table tbody tr { 
                background: var(--bg-surface); border: 1px solid var(--border); 
                border-radius: var(--radius-xl); margin-bottom: 1rem; padding: 1.25rem;
                box-shadow: var(--shadow-md);
            }
            .table td { 
                display: flex; justify-content: space-between; align-items: center; 
                padding: 0.6rem 0; border-bottom: 1px dashed rgba(255,255,255,0.05); text-align: right;
            }
            .table td:last-child { border-bottom: none; justify-content: flex-end; padding-top: 1rem; gap: 10px; }
            .table td::before { 
                content: attr(data-label); font-weight: 700; color: var(--text-muted); 
                font-size: 0.75rem; text-transform: uppercase; text-align: left; flex-shrink: 0;
            }
            .confirmed-badge, .confirm-btn { width: auto; padding: 4px 12px; }
            .actions { justify-content: flex-end; width: 100%; }
            .item-name { text-align: right; margin-bottom: 0; }
        }

        /* Keep navbar clearance on very small screens */
        @media (max-width: 520px) {
            .modal { padding: 72px 0.5rem 1.5rem; }
            .modal-body { padding: 1.25rem 1rem; }
            .modal-header, .modal-footer { padding-left: 1rem; padding-right: 1rem; }
        }
    </style>
</head>
<body>

<div id="toastContainer"></div>

<div class="container">
    
    <div class="top-bar">
        <a href="purchase_dashboard.php" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back to Purchases
        </a>
        <span class="page-badge"><i class="fa-solid fa-venus-mars"></i> Biological Inventory</span>
    </div>

    <div class="page-header">
        <div class="header-info">
            <h1>Breeding <span>&amp; Reproduction</span></h1>
            <p>Log and manage incoming breeding kits, ID tags, and reproduction supplies.</p>
        </div>
        
        <div class="header-actions">
            <button class="btn btn-amber" onclick="openConfirmAllModal()">
                <i class="fa-solid fa-check-double"></i> Confirm All Pending
            </button>
            <button class="btn btn-primary" onclick="openAddModal()">
                <i class="fa-solid fa-plus"></i> Add New Purchase
            </button>
        </div>
    </div>

    <div class="search-container">
        <i class="fa-solid fa-magnifying-glass search-icon"></i>
        <input type="text" class="search-input" id="searchInput" placeholder="Quick search by supply name, supplier, or reference no..." onkeyup="filterTable()">
    </div>

    <div class="table-card">
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Ref No</th>
                        <th>Supplier</th>
                        <th>Item Name</th>
                        <th>Quantity</th>
                        <th>Unit</th>
                        <th>Net Wt/Content</th>
                        <th>Unit Cost</th>
                        <th>Total Cost</th>
                        <th>Category</th>
                        <th>Purchase Date</th>
                        <th style="text-align: center; width: 140px;">Status</th>
                        <th style="text-align: center; width: 120px;">Actions</th>
                    </tr>
                </thead>
                <tbody id="item-table">
                    <?php 
                    $categoryLabels = [0 => 'Non-Consumable', 1 => 'Consumable'];
                    $categoryClasses = [0 => 'category-nonconsumable', 1 => 'category-consumable'];

                    if(empty($items_data)): ?>
                        <tr>
                            <td colspan="12">
                                <div class="empty-state">
                                    <i class="fa-solid fa-folder-open"></i>
                                    No breeding & reproduction purchases recorded yet in this location.
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach($items_data as $item): 
                            $status = isset($item['STATUS']) ? (int)$item['STATUS'] : 0;
                            $isConfirmed = ($status === 1);
                            $totalCost = $item['TOTAL_COST'] ?? ($item['QUANTITY'] * $item['UNIT_COST']);
                        ?>
                        <tr data-item-id="<?php echo $item['ITEM_ID']; ?>"
                            data-item-name="<?php echo htmlspecialchars($item['ITEM_NAME']); ?>"
                            data-item-desc="<?php echo htmlspecialchars($item['ITEM_DESCRIPTION'] ?? ''); ?>"
                            data-unit-id="<?php echo $item['UNIT_ID']; ?>"
                            data-unit-cost="<?php echo $item['UNIT_COST']; ?>"
                            data-item-category="<?php echo $item['ITEM_CATEGORY']; ?>"
                            data-unit-name="<?php echo htmlspecialchars($item['UNIT_NAME']); ?>"
                            data-net-weight="<?php echo $item['ITEM_NET_WEIGHT'] ?? '0'; ?>"
                            data-quantity="<?php echo $item['QUANTITY'] ?? '0'; ?>"
                            data-purchase-date-raw="<?php echo htmlspecialchars($item['DATE_OF_PURCHASE'] ?? ''); ?>"
                            data-purchase-date-fmt="<?php echo htmlspecialchars($item['DATE_OF_PURCHASE_FMT'] ?? ''); ?>"
                            data-location-id="<?php echo $item['LOCATION_ID'] ?? ''; ?>"
                            data-building-id="<?php echo $item['BUILDING_ID'] ?? ''; ?>"
                            data-pen-id="<?php echo $item['PEN_ID'] ?? ''; ?>"
                            data-supplier="<?php echo htmlspecialchars($item['SUPPLIER'] ?? ''); ?>"
                            data-reference-no="<?php echo htmlspecialchars($item['REFERENCE_NO'] ?? ''); ?>"
                            data-created-at="<?php echo htmlspecialchars($item['CREATED_AT_FMT'] ?? ''); ?>">
                            
                            <td data-label="Ref No">
                                <div class="ref-no"><?php echo !empty($item['REFERENCE_NO']) ? htmlspecialchars($item['REFERENCE_NO']) : '—'; ?></div>
                            </td>
                            <td data-label="Supplier">
                                <div class="supplier-name"><?php echo !empty($item['SUPPLIER']) ? htmlspecialchars($item['SUPPLIER']) : 'General Supplier'; ?></div>
                            </td>
                            <td data-label="Item Name">
                                <div class="item-name"><?php echo htmlspecialchars($item['ITEM_NAME']); ?></div>
                            </td>
                            <td data-label="Quantity">
                                <div class="val-mono" style="color: #fff;">
                                    <?php echo number_format($item['QUANTITY'] ?? 0, 2); ?>
                                </div>
                            </td>
                            <td data-label="Unit">
                                <div class="item-unit"><?php echo htmlspecialchars($item['UNIT_NAME']); ?></div>
                            </td>
                            <td data-label="Net Wt/Content">
                                <div class="item-unit"><?php echo htmlspecialchars($item['ITEM_NET_WEIGHT'] ?? 'N/A'); ?></div>
                            </td>
                            <td data-label="Unit Cost">
                                <div class="val-money" style="color: var(--text-primary);">₱<?php echo number_format($item['UNIT_COST'], 2); ?></div>
                            </td>
                            <td data-label="Total Cost">
                                <div class="val-money">₱<?php echo number_format($totalCost, 2); ?></div>
                            </td>
                            <td data-label="Category">
                                <span class="category-badge <?php echo $categoryClasses[$item['ITEM_CATEGORY']]; ?>">
                                    <?php echo $categoryLabels[$item['ITEM_CATEGORY']]; ?>
                                </span>
                            </td>
                            <td data-label="Purchase Date">
                                <div class="val-mono"><?php echo htmlspecialchars($item['DATE_OF_PURCHASE_FMT'] ?? 'N/A'); ?></div>
                            </td>

                            <td data-label="Status" style="text-align: center;">
                                <?php if(!$isConfirmed): ?>
                                    <button class="confirm-btn" onclick="openConfirmModal(this)">Confirm</button>
                                <?php else: ?>
                                    <div class="confirmed-badge"><i class="fa-solid fa-check me-1"></i> Verified</div>
                                <?php endif; ?>
                            </td>

                            <td data-label="Actions">
                                <div class="actions">
                                    <button class="action-btn view" onclick="viewItem(this)" title="View Details">
                                        <i class="fa-regular fa-eye"></i>
                                    </button>

                                    <?php if(!$isConfirmed): ?>
                                        <button class="action-btn edit" onclick="editItem(this)" title="Edit">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </button>
                                        <button class="action-btn delete" onclick="deleteItem(this)" title="Delete">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </button>
                                    <?php else: ?>
                                        <span style="font-size: 1.1em; opacity: 0.3; cursor: not-allowed; margin-left: 5px; display: flex; align-items: center;"><i class="fa-solid fa-lock"></i></span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            <div id="empty-state-js" class="empty-state" style="display:none;">
                <i class="fa-solid fa-magnifying-glass"></i>
                <p>No purchases found matching your search term.</p>
            </div>
        </div>
    </div>
</div>

<div id="modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modal-title">Add Breeding Supply Purchase</h2>
        </div>
        
        <div class="modal-body">
            <div id="modal-alert" class="alert"></div>
            <form id="item-form" method="POST">
                <input type="hidden" id="item-id" name="item_id">
                <input type="hidden" name="item_type_id" value="<?php echo $ITEM_TYPE_ID; ?>">
                
                <div class="info-group">
                    <h3>Item Information</h3>
                    <div class="form-stack">

                        <div class="form-group full-width autocomplete-wrapper">
                            <label class="form-label" for="item-name">Item Name <span>*</span></label>
                            <input type="text" id="item-name" name="item_name" class="form-control" 
                                   placeholder="e.g., Breeding Kit" required maxlength="300" autocomplete="off">
                            <div id="autocomplete-list" class="autocomplete-list"></div>
                        </div>

                        <div class="form-row">
                            <div class="form-group autocomplete-wrapper">
                                <label class="form-label">Supplier</label>
                                <input type="text" id="supplier" name="supplier" class="form-control" 
                                       placeholder="e.g., ABC Farm Supplies" autocomplete="off">
                                <div id="supplier-autocomplete-list" class="autocomplete-list"></div>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Reference No.</label>
                                <input type="text" id="reference-no" name="reference_no" class="form-control" 
                                       placeholder="e.g., OR-12345">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label" for="item-quantity">Quantity <span>*</span></label>
                                <input type="number" id="item-quantity" name="item_quantity" class="form-control val-mono" 
                                       placeholder="e.g., 10" step="0.01" min="0" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="unit">Unit of Measurement <span>*</span></label>
                                <select id="unit" name="unit_id" class="form-select" required>
                                    <option value="">Select Unit</option>
                                    <?php foreach($units as $unit): ?>
                                        <option value="<?php echo $unit['UNIT_ID']; ?>"><?php echo htmlspecialchars($unit['UNIT_NAME']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label" for="unit-cost">Unit Cost (₱) <span>*</span></label>
                                <input type="number" id="unit-cost" name="unit_cost" class="form-control val-mono" 
                                       placeholder="0.00" step="0.01" min="0" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="item-category">Item Category <span>*</span></label>
                                <select id="item-category" name="item_category" class="form-select" required>
                                    <option value="">Select Category</option>
                                    <option value="0">Non-Consumable</option>
                                    <option value="1">Consumable</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label" for="purchase-date">Date of Purchase <span>*</span></label>
                                <input type="text" id="purchase-date" name="date_of_purchase" class="form-control date-picker" 
                                       placeholder="mm/dd/yyyy" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="net-weight">Net Wt / Content <span style="color:var(--text-muted);">(Optional)</span></label>
                                <input type="number" id="net-weight" name="item_net_weight" class="form-control val-mono" 
                                       placeholder="e.g., 500" step="0.01" min="0">
                            </div>
                        </div>

                        <div class="form-group full-width">
                            <label class="form-label" for="item-desc">Item Description / Specs</label>
                            <textarea id="item-desc" name="item_description" class="form-control" 
                                      placeholder="Enter detailed specifications..." rows="2" maxlength="500"></textarea>
                        </div>
                    </div></div><div class="info-group">
                    <h3>Initial Location</h3>
                    <div class="form-stack">
                        
                        <div class="form-row-3">
                            <div class="form-group">
                                <label class="form-label" for="location_id">Location</label>
                                <select id="location_id" name="location_id" class="form-select" onchange="filterBuildings()" 
                                        <?php echo ($USER_LOCATION_ != 1000) ? 'style="background-color: #1e293b; pointer-events: none; color: #94a3b8;"' : ''; ?> required>
                                    <?php if($USER_LOCATION_ == 1000): ?>
                                        <option value="">Select Location</option>
                                    <?php endif; ?>
                                    <?php foreach($locations as $loc): ?>
                                        <option value="<?php echo $loc['LOCATION_ID']; ?>" <?php echo ($USER_LOCATION_ != 1000 && $loc['LOCATION_ID'] == $USER_LOCATION_) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($loc['LOCATION_NAME']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if ($USER_LOCATION_ != 1000): ?>
                                    <input type="hidden" name="location_id" value="<?= $USER_LOCATION_ ?>">
                                <?php endif; ?>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="building_id">Building</label>
                                <select id="building_id" name="building_id" class="form-select" onchange="filterPens()" disabled>
                                    <option value="">Select Location First</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="pen_id">Pen</label>
                                <select id="pen_id" name="pen_id" class="form-select" disabled>
                                    <option value="">Select Building First</option>
                                </select>
                            </div>
                        </div></div></div></form>
        </div><div class="modal-footer">
            <button type="button" class="btn btn-ghost" onclick="closeModal()">Cancel</button>
            <button type="button" class="btn btn-primary" id="btn-save" onclick="saveItem()">
                <i class="fa-solid fa-floppy-disk me-2"></i> Save Purchase
            </button>
        </div>
    </div>
</div>

<div id="view-modal" class="modal">
    <div class="modal-content narrow">
        <div class="modal-header">
            <h2>Purchase Dossier</h2>
        </div>
        <div class="modal-body" id="view-modal-body"></div>
        <div class="modal-footer">
            <button type="button" class="btn btn-ghost" onclick="closeViewModal()" style="width: 100%;">Close Window</button>
        </div>
    </div>
</div>

<div id="confirm-modal" class="modal">
    <div class="modal-content narrow">
        <div class="modal-body confirm-content">
            <span class="confirm-icon" style="color:var(--orange);">👨‍🌾</span>
            <h2 style="color: #fff; margin-bottom: 10px;">Verify Acquisition?</h2>
            <p style="color: var(--text-secondary); margin-bottom: 5px;">
                You are confirming the intake of <strong><span id="confirm-item-qty"></span> <span id="confirm-item-name" style="color:var(--orange);"></span></strong>.
            </p>
            <div class="warning-text">
                <i class="fa-solid fa-triangle-exclamation me-1"></i> <strong>Critical:</strong> Once confirmed, this financial record is locked and cannot be edited or purged.
            </div>
            <form id="confirmForm" method="POST">
                <input type="hidden" id="confirm_item_id" name="item_id">
            </form>
        </div>
        <div class="modal-footer" style="justify-content: center; border-top: none; padding-top: 0; background: transparent; padding-bottom: 1.75rem;">
            <button type="button" class="btn btn-ghost" onclick="closeConfirmModal()">Cancel</button>
            <button type="button" class="btn btn-primary" onclick="submitConfirmation()" style="background: var(--red); border-color: var(--red); color: white;">Yes, Lock Record</button>
        </div>
    </div>
</div>

<div id="confirm-all-modal" class="modal">
    <div class="modal-content narrow">
        <div class="modal-body confirm-content">
            <span class="confirm-icon" style="color:var(--amber);">📋</span>
            <h2 style="color: #fff; margin-bottom: 10px;">Commit All Pending?</h2>
            <p style="color: var(--text-secondary);">
                This function will verify and lock <strong>ALL</strong> currently pending breeding &amp; reproduction purchases in this location.
            </p>
            <div class="warning-text" style="background: var(--amber-dim); border-color: rgba(245,158,11,0.3); color: var(--amber);">
                <i class="fa-solid fa-triangle-exclamation me-1"></i> <strong>Irreversible Action:</strong> Please audit all pending items before executing this batch commit.
            </div>
        </div>
        <div class="modal-footer" style="justify-content: center; border-top: none; padding-top: 0; background: transparent; padding-bottom: 1.75rem;">
            <button type="button" class="btn btn-ghost" onclick="closeConfirmAllModal()">Cancel</button>
            <button type="button" class="btn btn-amber" onclick="submitConfirmAll()">Commit All Now</button>
        </div>
    </div>
</div>

<form id="deleteItemForm" method="POST" action="../process/deleteBreedingReproduction.php" style="display: none;">
    <input type="hidden" id="delete_item_id" name="item_id">
</form>

<script>
    const allBuildings = <?php echo json_encode($buildings_raw); ?>;
    const allPens      = <?php echo json_encode($pens_raw); ?>;
    const USER_LOCATION = <?php echo json_encode($USER_LOCATION_); ?>;

    let fpPurchaseDate;

    document.addEventListener('DOMContentLoaded', () => {
        fpPurchaseDate = flatpickr("#purchase-date", {
            dateFormat: "Y-m-d", 
            altInput: true,      
            altFormat: "m/d/Y",  
            allowInput: true
        });

        if (USER_LOCATION != 1000) {
            loadBuildings('src');
            loadBuildings('dest');
        }
    });

    // --- CONFIRMATION ---
    function openConfirmModal(button) {
        const row = button.closest('tr');
        document.getElementById('confirm_item_id').value = row.dataset.itemId;
        document.getElementById('confirm-item-name').textContent = row.dataset.itemName;
        document.getElementById('confirm-item-qty').textContent = row.dataset.quantity;
        document.getElementById('confirm-modal').classList.add('show');
    }
    
    function closeConfirmModal() { document.getElementById('confirm-modal').classList.remove('show'); }
    
    function submitConfirmation() {
        const formData = new FormData(document.getElementById('confirmForm'));
        const btn = document.querySelector('#confirm-modal .btn-primary');
        btn.disabled = true; btn.innerHTML = 'Confirming...';
        
        fetch('../purchase_confirmations/confirmBreedingReproductionSupplies.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                if (data.success) { showToast(data.message, 'success'); setTimeout(() => window.location.reload(), 1000); } 
                else { showToast(data.message, 'error'); btn.disabled = false; btn.innerHTML = 'Yes, Lock Record'; }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('An error occurred while confirming.', 'error');
                btn.disabled = false; btn.innerHTML = 'Yes, Lock Record';
            });
    }

    function openConfirmAllModal() { document.getElementById('confirm-all-modal').classList.add('show'); }
    function closeConfirmAllModal() { document.getElementById('confirm-all-modal').classList.remove('show'); }

    function submitConfirmAll() {
        const btn = document.querySelector('#confirm-all-modal .btn-amber');
        btn.disabled = true; btn.innerHTML = 'Processing...';

        fetch('../purchase_confirmations/confirmAllBreedingReproductionSupplies.php', { method: 'POST' })
            .then(r => r.json())
            .then(data => {
                if (data.success) { showToast(data.message, 'success'); setTimeout(() => window.location.reload(), 1000); } 
                else { showToast(data.message, 'error'); btn.disabled = false; btn.innerHTML = 'Commit All Now'; }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('An error occurred while confirming all items.', 'error');
                btn.disabled = false; btn.innerHTML = 'Commit All Now';
            });
    }

    function filterBuildings() {
        const locId = document.getElementById('location_id').value;
        const bldgSel = document.getElementById('building_id');
        const penSel = document.getElementById('pen_id');
        
        bldgSel.innerHTML = '<option value="">Select Building</option>';
        penSel.innerHTML = '<option value="">Select Building First</option>';
        penSel.disabled = true;

        if (locId) {
            bldgSel.disabled = false;
            const filtered = allBuildings.filter(b => b.LOCATION_ID == locId);
            filtered.forEach(b => {
                const opt = document.createElement('option');
                opt.value = b.BUILDING_ID; opt.textContent = b.BUILDING_NAME;
                bldgSel.appendChild(opt);
            });
        } else { bldgSel.disabled = true; }
    }

    function filterPens() {
        const bldgId = document.getElementById('building_id').value;
        const penSel = document.getElementById('pen_id');
        penSel.innerHTML = '<option value="">Select Pen</option>';

        if (bldgId) {
            penSel.disabled = false;
            const filtered = allPens.filter(p => p.BUILDING_ID == bldgId);
            filtered.forEach(p => {
                const opt = document.createElement('option');
                opt.value = p.PEN_ID; opt.textContent = p.PEN_NAME;
                penSel.appendChild(opt);
            });
        } else { penSel.disabled = true; }
    }

    let autocompleteTimeout = null;
    let currentFocus = -1;

    function initAutocomplete() {
        const itemNameInput = document.getElementById('item-name');
        const autocompleteList = document.getElementById('autocomplete-list');
        const newInput = itemNameInput.cloneNode(true);
        itemNameInput.parentNode.replaceChild(newInput, itemNameInput);
        const input = document.getElementById('item-name');
        
        input.addEventListener('input', function(e) {
            const value = this.value.trim();
            clearTimeout(autocompleteTimeout);
            if (value.length < 2) { closeAutocomplete(); return; }
            autocompleteList.innerHTML = '<div class="autocomplete-loading">Searching...</div>';
            autocompleteList.classList.add('show');
            autocompleteTimeout = setTimeout(() => { fetchAutocomplete(value); }, 300);
        });
        input.addEventListener('keydown', function(e) {
            const items = autocompleteList.getElementsByClassName('autocomplete-item');
            if (e.keyCode === 40) { e.preventDefault(); currentFocus++; addActive(items); } 
            else if (e.keyCode === 38) { e.preventDefault(); currentFocus--; addActive(items); } 
            else if (e.keyCode === 13) { if (currentFocus > -1 && items[currentFocus]) { e.preventDefault(); items[currentFocus].click(); } } 
            else if (e.keyCode === 27) { closeAutocomplete(); }
        });
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.autocomplete-wrapper')) { closeAutocomplete(); }
        });
    }

    function fetchAutocomplete(searchTerm) {
        fetch(`../process/searchBreedingReproduction.php?term=${encodeURIComponent(searchTerm)}`)
            .then(response => response.json())
            .then(data => { displayAutocomplete(data, searchTerm); })
            .catch(error => { console.error('Autocomplete error:', error); closeAutocomplete(); });
    }

    function displayAutocomplete(results, searchTerm) {
        const list = document.getElementById('autocomplete-list');
        list.innerHTML = ''; currentFocus = -1;
        if (results.length === 0) {
            list.innerHTML = '<div class="autocomplete-no-results">No items found</div>';
            list.classList.add('show'); return;
        }
        results.forEach(item => {
            const div = document.createElement('div');
            div.className = 'autocomplete-item';
            div.innerHTML = item.replace(new RegExp(`(${escapeRegex(searchTerm)})`, 'gi'), '<strong>$1</strong>');
            div.addEventListener('click', function() {
                document.getElementById('item-name').value = item;
                closeAutocomplete();
            });
            list.appendChild(div);
        });
        list.classList.add('show');
    }

    function addActive(items) {
        if (!items || items.length === 0) return;
        removeActive(items);
        if (currentFocus >= items.length) currentFocus = 0;
        if (currentFocus < 0) currentFocus = items.length - 1;
        items[currentFocus].classList.add('active');
        items[currentFocus].scrollIntoView({ block: 'nearest' });
    }
    function removeActive(items) { for (let i = 0; i < items.length; i++) { items[i].classList.remove('active'); } }
    function closeAutocomplete() {
        const list = document.getElementById('autocomplete-list');
        if (list) { list.classList.remove('show'); list.innerHTML = ''; }
        currentFocus = -1;
    }
    function escapeRegex(string) { return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); }

    // --- Supplier Autocomplete Logic ---
    let supplierTimeout = null;
    const supplierInput = document.getElementById('supplier');
    const supplierList = document.getElementById('supplier-autocomplete-list');

    if (supplierInput) {
        supplierInput.addEventListener('input', function() {
            clearTimeout(supplierTimeout);
            const val = this.value.trim();
            
            if (val.length < 2) { 
                supplierList.classList.remove('show'); 
                return; 
            }
            
            supplierList.innerHTML = '<div class="autocomplete-loading">Searching...</div>';
            supplierList.classList.add('show');
            
            supplierTimeout = setTimeout(() => {
                fetch(`../process/searchSuppliers.php?term=${encodeURIComponent(val)}`)
                .then(r => r.json())
                .then(data => {
                    supplierList.innerHTML = '';
                    if (data.length === 0) {
                        supplierList.innerHTML = '<div class="autocomplete-no-results">No matches</div>';
                        return;
                    }
                    
                    data.forEach(item => {
                        const div = document.createElement('div');
                        div.className = 'autocomplete-item';
                        const regex = new RegExp(`(${val})`, 'gi');
                        div.innerHTML = item.replace(regex, '<strong>$1</strong>');
                        
                        div.addEventListener('click', () => {
                            supplierInput.value = item;
                            supplierList.classList.remove('show');
                        });
                        supplierList.appendChild(div);
                    });
                }).catch(() => supplierList.classList.remove('show'));
            }, 300);
        });

        document.addEventListener('click', e => {
            if (!supplierInput.parentElement.contains(e.target)) {
                supplierList.classList.remove('show');
            }
        });
    }

    function openAddModal() {
        document.getElementById('modal-title').textContent = 'Add Breeding Supply Purchase';
        // TARGET ID INSTEAD OF CLASS
        document.getElementById('btn-save').innerHTML = '<i class="fa-solid fa-floppy-disk me-2"></i> Save Purchase';
        document.getElementById('item-form').reset();
        document.getElementById('item-id').value = '';
        document.getElementById('item-category').value = ''; 
        document.getElementById('unit').value = '';
        
        fpPurchaseDate.setDate(new Date()); 
        
        const locSelect = document.getElementById('location_id');
        
        if (USER_LOCATION != 1000) {
            locSelect.value = USER_LOCATION;
            filterBuildings(); 
        } else {
            locSelect.value = "";
            document.getElementById('building_id').innerHTML = '<option value="">Select Location First</option>';
            document.getElementById('building_id').disabled = true;
            document.getElementById('pen_id').innerHTML = '<option value="">Select Building First</option>';
            document.getElementById('pen_id').disabled = true;
        }

        hideAlert();
        document.getElementById('modal').classList.add('show');
        setTimeout(() => { initAutocomplete(); }, 100);
    }

    function viewItem(button) {
        const row = button.closest('tr');
        const data = {
            item_id: row.dataset.itemId,
            item_name: row.dataset.itemName,
            item_description: row.dataset.itemDesc,
            unit: row.dataset.unitName,
            unit_cost: row.dataset.unitCost,
            item_category: row.dataset.itemCategory,
            net_weight: row.dataset.netWeight,
            quantity: row.dataset.quantity,
            purchase_date_fmt: row.dataset.purchaseDateFmt,
            supplier: row.dataset.supplier,
            reference_no: row.dataset.referenceNo,
            created_at: row.dataset.createdAt
        };
        displayItemDetails(data);
    }

    function displayItemDetails(data) {
        const categoryLabels = {0: 'Non-Consumable', 1: 'Consumable'};
        const html = `
            <div class="info-group" style="margin-top:0;">
                <h3 style="margin-top:0;">Acquisition Overview</h3>
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px;">
                    <div>
                        <p style="color:var(--text-muted); font-size:0.72rem; text-transform:uppercase; font-weight:700; margin-bottom:4px;">System ID</p>
                        <span class="val-mono">PCH-${String(data.item_id).padStart(5, '0')}</span>
                    </div>
                    <div>
                        <p style="color:var(--text-muted); font-size:0.72rem; text-transform:uppercase; font-weight:700; margin-bottom:4px;">Reference No</p>
                        <span class="val-mono">${data.reference_no || 'N/A'}</span>
                    </div>
                </div>
                <div style="margin-top:14px;">
                    <p style="color:var(--text-muted); font-size:0.72rem; text-transform:uppercase; font-weight:700; margin-bottom:4px;">Item Nomenclature</p>
                    <p style="color:var(--text-primary); font-weight:600;">${data.item_name}</p>
                </div>
                <div style="margin-top:12px;">
                    <p style="color:var(--text-muted); font-size:0.72rem; text-transform:uppercase; font-weight:700; margin-bottom:4px;">Supplier Origin</p>
                    <p style="color:var(--text-secondary);">${data.supplier || 'N/A'}</p>
                </div>
                <div style="margin-top:12px;">
                    <p style="color:var(--text-muted); font-size:0.72rem; text-transform:uppercase; font-weight:700; margin-bottom:4px;">Remarks</p>
                    <p style="color:var(--text-secondary);">${data.item_description || 'None'}</p>
                </div>
            </div>
            
            <div class="info-group">
                <h3>Financials & Metrics</h3>
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px;">
                    <div>
                        <p style="color:var(--text-muted); font-size:0.72rem; text-transform:uppercase; font-weight:700; margin-bottom:4px;">Batch Size</p>
                        <span style="color:#fff;">${data.quantity || '0'} ${data.unit}</span>
                    </div>
                    <div>
                        <p style="color:var(--text-muted); font-size:0.72rem; text-transform:uppercase; font-weight:700; margin-bottom:4px;">Net Wt/Content</p>
                        <span class="val-mono">${data.net_weight || '0'}</span>
                    </div>
                    <div>
                        <p style="color:var(--text-muted); font-size:0.72rem; text-transform:uppercase; font-weight:700; margin-bottom:4px;">Unit Cost</p>
                        <span class="val-money">₱${parseFloat(data.unit_cost).toLocaleString('en-PH', {minimumFractionDigits: 2})}</span>
                    </div>
                    <div>
                        <p style="color:var(--text-muted); font-size:0.72rem; text-transform:uppercase; font-weight:700; margin-bottom:4px;">Category</p>
                        <span style="color:#fff;">${categoryLabels[data.item_category]}</span>
                    </div>
                    <div>
                        <p style="color:var(--text-muted); font-size:0.72rem; text-transform:uppercase; font-weight:700; margin-bottom:4px;">Date Logged</p>
                        <span class="val-mono">${data.purchase_date_fmt || 'N/A'}</span>
                    </div>
                </div>
            </div>
        `;
        document.getElementById('view-modal-body').innerHTML = html;
        document.getElementById('view-modal').classList.add('show');
    }

    function editItem(button) {
        const row = button.closest('tr');
        const data = {
            item_id: row.dataset.itemId,
            item_name: row.dataset.itemName,
            item_description: row.dataset.itemDesc,
            unit_id: row.dataset.unitId,
            unit_cost: row.dataset.unitCost,
            item_category: row.dataset.itemCategory,
            net_weight: row.dataset.netWeight,
            quantity: row.dataset.quantity,
            purchase_date_raw: row.dataset.purchaseDateRaw,
            location_id: row.dataset.locationId,
            building_id: row.dataset.buildingId,
            pen_id: row.dataset.penId,
            supplier: row.dataset.supplier,
            reference_no: row.dataset.referenceNo
        };
        populateEditForm(data);
    }

    function populateEditForm(data) {
        document.getElementById('modal-title').textContent = 'Edit Breeding Supply Purchase';
        // TARGET ID INSTEAD OF CLASS
        document.getElementById('btn-save').innerHTML = '<i class="fa-solid fa-arrows-rotate me-2"></i> Update Purchase';
        document.getElementById('item-id').value = data.item_id;
        document.getElementById('item-name').value = data.item_name;
        document.getElementById('item-desc').value = data.item_description || '';
        document.getElementById('unit').value = data.unit_id;
        document.getElementById('unit-cost').value = data.unit_cost;
        document.getElementById('item-category').value = data.item_category;
        document.getElementById('net-weight').value = data.net_weight || '';
        document.getElementById('item-quantity').value = data.quantity || '0';
        document.getElementById('supplier').value = data.supplier || '';
        document.getElementById('reference-no').value = data.reference_no || '';

        fpPurchaseDate.setDate(data.purchase_date_raw || ''); 

        const locSelect = document.getElementById('location_id');
        locSelect.value = data.location_id || ""; 
        filterBuildings(); 

        const bldgSelect = document.getElementById('building_id');
        if(data.building_id) {
            bldgSelect.value = data.building_id;
            filterPens();
            const penSelect = document.getElementById('pen_id');
            if(data.pen_id) {
                penSelect.value = data.pen_id;
            }
        }
        
        hideAlert();
        document.getElementById('modal').classList.add('show');
        
        setTimeout(() => {
            initAutocomplete();
        }, 100);
    }

    function deleteItem(button) {
        const row = button.closest('tr');
        const itemId = row.dataset.itemId;
        const itemName = row.dataset.itemName;
        
        if (confirm(`Are you sure you want to delete "${itemName}"? This action cannot be undone.`)) {
            document.getElementById('delete_item_id').value = itemId;
            document.getElementById('deleteItemForm').submit();
        }
    }

    function saveItem() {
        const form = document.getElementById('item-form');
        if (!form.checkValidity()) { form.reportValidity(); return; }
        const formData = new FormData(form);
        const isEdit = document.getElementById('item-id').value !== '';
        const url = isEdit ? '../process/editBreedingReproduction.php' : '../process/addBreedingReproduction.php';
        
        // TARGET ID INSTEAD OF CLASS
        const saveBtn = document.getElementById('btn-save');
        
        saveBtn.disabled = true;
        saveBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i> ' + (isEdit ? 'Updating...' : 'Saving...');

        fetch(url, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast(data.message, 'success');
                setTimeout(() => { window.location.reload(); }, 1500);
            } else {
                showToast(data.message || 'Error saving item', 'error');
                saveBtn.disabled = false;
                saveBtn.innerHTML = isEdit ? '<i class="fa-solid fa-arrows-rotate me-2"></i> Update Purchase' : '<i class="fa-solid fa-floppy-disk me-2"></i> Save Purchase';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('An error occurred while saving the item', 'error');
            saveBtn.disabled = false;
            saveBtn.innerHTML = isEdit ? '<i class="fa-solid fa-arrows-rotate me-2"></i> Update Purchase' : '<i class="fa-solid fa-floppy-disk me-2"></i> Save Purchase';
        });
    }

    function showToast(msg, type = 'success') {
        const t = document.createElement('div');
        t.className = 'toast';
        t.style.borderLeft = `4px solid ${type === 'error' ? 'var(--red)' : 'var(--orange)'}`;
        t.innerHTML = `${type === 'error' ? ' ' : ' '} ${msg}`;
        document.getElementById('toastContainer').appendChild(t);
        setTimeout(() => t.remove(), 3500);
    }

    function hideAlert() {
        const alert = document.getElementById('modal-alert');
        if(alert) alert.style.display = 'none';
    }

    function closeModal() {
        closeAutocomplete();
        document.getElementById('modal').classList.remove('show');
    }

    function closeViewModal() {
        document.getElementById('view-modal').classList.remove('show');
    }

    function filterTable() {
        const searchValue = document.getElementById('searchInput').value.toLowerCase();
        const table = document.getElementById('item-table');
        const rows = table.getElementsByTagName('tr');
        let visibleCount = 0;

        for (let i = 0; i < rows.length; i++) {
            // skip empty state
            if (rows[i].querySelector('.empty-state')) continue;

            const row = rows[i];
            const text = row.textContent.toLowerCase();
            if (text.includes(searchValue)) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        }
        
        const emptyState = document.getElementById('empty-state-js');
        if (emptyState) {
            emptyState.style.display = (visibleCount === 0 && Array.from(rows).some(r => !r.querySelector('.empty-state'))) ? 'block' : 'none';
        }
    }

    function checkEmptyState() {
        filterTable(); // Run filter table to verify empty states on load
    }

    document.getElementById('view-modal').addEventListener('click', function(e) { if(e.target===this) closeViewModal(); });
    document.getElementById('confirm-modal').addEventListener('click', function(e) { if(e.target===this) closeConfirmModal(); });
    document.getElementById('confirm-all-modal').addEventListener('click', function(e) { if(e.target===this) closeConfirmAllModal(); });
    
    document.getElementById('item-form').addEventListener('submit', function(e) {
        e.preventDefault();
        saveItem();
    });

    document.addEventListener('DOMContentLoaded', function() {
        checkEmptyState();
    });
</script>
</body>
</html>