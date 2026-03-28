<?php
// views/purch_others.php
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
$ITEM_TYPE_ID = 12; // Others
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

        $units_sql = "SELECT * FROM UNITS ORDER BY UNIT_NAME ASC";
        $stmt = $conn->prepare($units_sql);
        $stmt->execute();
        $units = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

        $units_sql = "SELECT * FROM UNITS ORDER BY UNIT_NAME ASC";
        $stmt = $conn->prepare($units_sql);
        $stmt->execute();
        $units = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    <title>Others Purchase Management | FarmPro</title>
    
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
            --border-active:  rgba(236,72,153,0.5);
            --pink:           #ec4899;
            --pink-dim:       rgba(236,72,153,0.12);
            --pink-glow:      rgba(236,72,153,0.25);
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
            background-image: radial-gradient(ellipse 80% 50% at 50% -20%, rgba(236,72,153,0.06) 0%, transparent 60%);
        }
        .container { max-width: 1560px; margin: 0 auto; padding: 2rem 1.5rem; }

        /* ─── TOP BAR ─── */
        .top-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.75rem;
            gap: 1rem;
            flex-wrap: wrap;
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
            color: var(--pink); background: var(--pink-dim); border: 1px solid rgba(236,72,153,0.2);
            padding: 6px 12px; border-radius: 99px;
        }

        /* ─── HEADER ─── */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;  /* FIX: was flex-end, center looks better */
            margin-bottom: 1.75rem;
            gap: 1.5rem;
            flex-wrap: wrap;
        }
        .header-info h1 {
            font-size: clamp(1.6rem, 3vw, 2.2rem); font-weight: 700;
            color: var(--text-primary); letter-spacing: -0.03em; line-height: 1.1; margin-bottom: 0.3rem;
        }
        .header-info h1 span {
            background: linear-gradient(135deg, var(--pink), #be185d);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }
        .header-info p { color: var(--text-secondary); font-size: 0.9rem; }

        /* ─── BUTTONS ─── */
        .header-actions { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }
        
        .btn {
            display: inline-flex; align-items: center; justify-content: center; gap: 8px;
            padding: 10px 18px; border-radius: var(--radius-md); font-size: 0.875rem;
            font-weight: 600; font-family: var(--font); border: 1px solid transparent;
            cursor: pointer; transition: all var(--transition); text-decoration: none; white-space: nowrap;
        }
        .btn-primary { background: var(--pink); color: #fff; }
        .btn-primary:hover { background: #f472b6; box-shadow: 0 0 16px var(--pink-glow); transform: translateY(-1px); }
        .btn-amber { background: var(--amber); color: #000; }
        .btn-amber:hover { background: #fbbf24; box-shadow: 0 0 16px var(--amber-glow); transform: translateY(-1px); }
        .btn-ghost { background: transparent; color: var(--text-secondary); border-color: var(--border); }
        .btn-ghost:hover { background: var(--bg-elevated); color: var(--text-primary); border-color: rgba(255,255,255,0.15); }

        /* ─── SEARCH BAR ─── */
        .search-container { position: relative; margin-bottom: 1.25rem; }
        .search-icon { position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--text-muted); pointer-events: none; }
        .search-input {
            width: 100%; padding: 11px 12px 11px 2.8rem; background: var(--bg-surface);
            border: 1px solid var(--border); border-radius: var(--radius-lg); color: var(--text-primary);
            font-size: 0.925rem; font-family: var(--font); outline: none; transition: all var(--transition);
        }
        .search-input::placeholder { color: var(--text-muted); }
        .search-input:focus { border-color: var(--pink); box-shadow: 0 0 0 3px var(--pink-glow); }

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
            letter-spacing: 0.07em; padding: 13px 16px; text-align: left;
            border-bottom: 1px solid var(--border); white-space: nowrap;
        }
        .table tbody tr { border-bottom: 1px solid var(--border); transition: background var(--transition); }
        .table tbody tr:last-child { border-bottom: none; }
        .table tbody tr:hover { background: rgba(255,255,255,0.02); }
        .table td { padding: 13px 16px; font-size: 0.875rem; color: var(--text-primary); vertical-align: middle; }

        .ref-no { font-family: var(--font-mono); color: var(--text-muted); font-size: 0.82rem; }
        .supplier-name { color: var(--text-secondary); font-size: 0.875rem; }
        .item-name { font-weight: 700; color: #fff; font-size: 0.95rem; margin-bottom: 2px; }
        .item-unit { color: var(--text-secondary); font-size: 0.85rem; }
        .val-mono { font-family: var(--font-mono); font-weight: 600; font-size: 0.875rem; }
        .val-money { font-family: var(--font-mono); font-weight: 600; color: var(--amber); font-size: 0.9rem; }
        
        .confirmed-badge {
            display: inline-flex; align-items: center; justify-content: center; gap: 5px;
            background: var(--emerald-dim); color: var(--emerald); border: 1px solid rgba(16,185,129,0.2);
            border-radius: 6px; padding: 5px 10px; font-weight: 700; font-size: 0.72rem;
            text-transform: uppercase; width: 100%;
        }
        .confirm-btn {
            background: rgba(239, 68, 68, 0.1); color: var(--red); border: 1px solid rgba(239,68,68,0.3);
            padding: 5px 10px; border-radius: 6px; font-weight: 700; font-size: 0.72rem; cursor: pointer;
            transition: all var(--transition); width: 100%; text-transform: uppercase;
        }
        .confirm-btn:hover { background: var(--red); color: #fff; box-shadow: 0 4px 12px rgba(239,68,68,0.3); }

        .category-badge {
            display: inline-block; padding: 3px 9px; border-radius: 9999px;
            font-size: 0.72rem; font-weight: 600; white-space: nowrap; text-transform: uppercase;
        }
        .category-consumable { background: var(--blue-dim); color: var(--blue); border: 1px solid rgba(59,130,246,0.2); }
        .category-nonconsumable { background: var(--emerald-dim); color: var(--emerald); border: 1px solid rgba(16,185,129,0.2); }

        /* Actions */
        .actions { display: flex; gap: 6px; justify-content: center; }
        .action-btn {
            width: 30px; height: 30px; border-radius: 6px; border: 1px solid var(--border);
            background: var(--bg-elevated); display: inline-flex; align-items: center; justify-content: center;
            cursor: pointer; transition: all var(--transition); color: var(--text-secondary);
            text-decoration: none; font-size: 0.8rem;
        }
        .action-btn:hover { background: var(--bg-hover); color: var(--text-primary); }
        .action-btn.view:hover { color: var(--emerald); border-color: var(--emerald); }
        .action-btn.edit:hover { color: var(--pink); border-color: var(--pink); }
        .action-btn.delete:hover { color: var(--red); border-color: var(--red); }
        .action-btn.locked {
            opacity: 0.25; cursor: not-allowed; pointer-events: none;
        }

        .empty-state { text-align: center; padding: 4rem 2rem; color: var(--text-muted); }
        .empty-state i { font-size: 2.5rem; margin-bottom: 1rem; opacity: 0.3; display: block; }
        .empty-state p { margin-top: 0.5rem; }

        /* ─── MODALS ─── */
        .modal {
            display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.82);
            backdrop-filter: blur(4px); z-index: 1000; align-items: center; justify-content: center;
            padding: 1rem;
        }
        .modal.show { display: flex; }
        
        .modal-content {
            background: var(--bg-surface); border: 1px solid var(--border);
            border-radius: var(--radius-xl); width: 100%; max-width: 660px;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.6); display: flex; flex-direction: column;
            max-height: 90vh; animation: modalZoom 0.2s ease-out;
        }
        @keyframes modalZoom { from { transform: scale(0.96); opacity: 0; } to { transform: scale(1); opacity: 1; } }

        .modal-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .modal-header h2 { margin: 0; font-size: 1.15rem; font-weight: 700; color: #fff; }
        .modal-close {
            width: 28px; height: 28px; border-radius: 6px; border: 1px solid var(--border);
            background: transparent; color: var(--text-muted); cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            transition: all var(--transition); font-size: 0.8rem;
        }
        .modal-close:hover { background: var(--bg-elevated); color: var(--text-primary); }
        .modal-body { padding: 1.5rem; overflow-y: auto; }
        .modal-footer {
            padding: 1rem 1.5rem; border-top: 1px solid var(--border);
            display: flex; justify-content: flex-end; gap: 10px;
            background: var(--bg-elevated); border-radius: 0 0 var(--radius-xl) var(--radius-xl);
        }

        /* ─── FORM ELEMENTS ─── */
        /*
            FIX: Unified form-row / form-group spacing.
            Removed the duplicate margin-bottom from .form-group.full-width;
            gap on .form-row handles vertical rhythm instead.
        */
        .form-section { margin-bottom: 1.5rem; }
        .form-section:last-child { margin-bottom: 0; }

        .form-section-title {
            font-size: 0.72rem; font-weight: 700; text-transform: uppercase;
            color: var(--pink); letter-spacing: 0.08em;
            margin-bottom: 1rem; padding-bottom: 8px;
            border-bottom: 1px solid var(--border);
        }

        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        .form-row.cols-3 { grid-template-columns: 1fr 1fr 1fr; } /* FIX: explicit 3-col grid for location row */
        .form-row + .form-row { margin-top: 1rem; }

        .form-group { display: flex; flex-direction: column; gap: 5px; }
        .form-group.span-2 { grid-column: 1 / -1; } /* FIX: replaced .full-width with span-2 */
        
        .form-label {
            font-size: 0.7rem; font-weight: 600; text-transform: uppercase;
            color: var(--text-secondary); letter-spacing: 0.05em;
        }
        .form-label .req { color: var(--red); }
        .form-label .opt { color: var(--text-muted); font-weight: 400; text-transform: none; letter-spacing: 0; }
        
        .form-control, .form-select {
            width: 100%; padding: 9px 12px; background: var(--bg-elevated);
            border: 1px solid var(--border); color: var(--text-primary);
            border-radius: 8px; font-size: 0.9rem; font-family: var(--font);
            outline: none; transition: all var(--transition);
        }
        .form-control:focus, .form-select:focus { border-color: var(--pink); box-shadow: 0 0 0 3px var(--pink-glow); background: var(--bg-hover); }
        .form-control::placeholder { color: var(--text-muted); }
        textarea.form-control { resize: vertical; min-height: 64px; line-height: 1.5; }
        .form-select {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2394a3b8' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
            background-repeat: no-repeat; background-position: right 12px center; cursor: pointer;
        }
        .form-select:disabled, input:disabled, input[readonly] {
            opacity: 0.45; cursor: not-allowed; background: rgba(255,255,255,0.02); color: var(--text-muted);
        }

        /* Autocomplete */
        .autocomplete-wrapper { position: relative; }
        .autocomplete-list {
            position: absolute; z-index: 1050; top: calc(100% + 2px); left: 0; right: 0;
            background: var(--bg-elevated); border: 1px solid var(--border);
            border-radius: 8px; max-height: 200px; overflow-y: auto;
            box-shadow: var(--shadow-md); display: none;
        }
        .autocomplete-list.show { display: block; }
        .autocomplete-item {
            padding: 9px 14px; cursor: pointer; transition: background 0.15s;
            border-bottom: 1px solid var(--border); color: var(--text-primary); font-size: 0.875rem;
        }
        .autocomplete-item:last-child { border-bottom: none; }
        .autocomplete-item:hover, .autocomplete-item.active { background: var(--bg-hover); color: var(--pink); }
        .autocomplete-item strong { color: var(--pink); }
        .autocomplete-loading, .autocomplete-no-results {
            padding: 12px; text-align: center; color: var(--text-muted); font-size: 0.85rem; font-style: italic;
        }

        /* Toast */
        #toastContainer { position: fixed; top: 20px; right: 20px; z-index: 9999; display: flex; flex-direction: column; gap: 10px; }
        .toast {
            background: var(--bg-surface); border: 1px solid var(--border); color: #fff;
            padding: 0.9rem 1.25rem; border-radius: var(--radius-md); box-shadow: 0 10px 25px rgba(0,0,0,0.5);
            font-size: 0.875rem; font-weight: 600; animation: slideIn 0.25s ease-out;
            max-width: 320px;
        }
        @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }

        /* Confirm modal specifics */
        .confirm-content { text-align: center; padding: 0.5rem 0.5rem 0; }
        .confirm-icon { font-size: 3rem; margin-bottom: 0.75rem; display: block; }
        .confirm-content h2 { color: #fff; margin-bottom: 8px; font-size: 1.2rem; }
        .confirm-content p { color: var(--text-secondary); font-size: 0.9rem; margin-bottom: 0; }
        .warning-text {
            color: var(--red); font-size: 0.82rem; margin: 1.25rem 0 0;
            background: var(--red-dim); padding: 10px 12px; border-radius: 8px;
            border: 1px solid rgba(239,68,68,0.2); line-height: 1.5; text-align: left;
        }
        .warning-amber {
            color: var(--amber); background: var(--amber-dim); border-color: rgba(245,158,11,0.3);
        }

        /* View modal detail rows */
        .detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        .detail-item { }
        .detail-item .label { font-size: 0.72rem; text-transform: uppercase; color: var(--text-muted); font-weight: 600; letter-spacing: 0.05em; margin-bottom: 3px; }
        .detail-item .value { font-size: 0.9rem; color: var(--text-primary); word-break: break-word; }

        /* ─── RESPONSIVE ─── */
        @media (max-width: 900px) {
            .form-row.cols-3 { grid-template-columns: 1fr 1fr; }
            .form-row.cols-3 .form-group:last-child { grid-column: 1 / -1; }
        }

        @media (max-width: 768px) {
            .container { padding: 1rem; }
            .page-header { flex-direction: column; align-items: flex-start; }
            .header-actions { width: 100%; }
            .header-actions .btn { flex: 1; justify-content: center; }
            .form-row, .form-row.cols-3 { grid-template-columns: 1fr; }
            .form-row.cols-3 .form-group:last-child { grid-column: auto; }
            .form-group.span-2 { grid-column: auto; }
            .modal-footer { flex-direction: column; }
            .modal-footer .btn { width: 100%; }

            /* Mobile: table → cards */
            .table-wrap { overflow: visible; }
            .table, .table thead, .table tbody, .table th, .table td, .table tr { display: block; width: 100%; }
            .table thead { display: none; }
            .table tbody tr {
                background: var(--bg-surface); border: 1px solid var(--border);
                border-radius: var(--radius-xl); margin-bottom: 1rem; padding: 1rem 1.25rem;
                box-shadow: var(--shadow-md);
            }
            /* Card header: item name spans full width without label */
            .table td[data-label="Item Name"] {
                display: block; padding: 0 0 0.75rem; border-bottom: 1px solid var(--border);
                margin-bottom: 0.75rem; text-align: left;
            }
            .table td[data-label="Item Name"]::before { display: none; }
            .table td[data-label="Item Name"] .item-name { font-size: 1rem; }

            /* All other cells: label + value side-by-side */
            .table td {
                display: flex; justify-content: space-between; align-items: center;
                padding: 0.5rem 0; border-bottom: 1px solid rgba(255,255,255,0.04); text-align: right;
            }
            .table td:last-child { border-bottom: none; padding-top: 0.75rem; justify-content: flex-end; gap: 8px; }
            .table td::before {
                content: attr(data-label); font-weight: 600; color: var(--text-muted);
                font-size: 0.72rem; text-transform: uppercase; text-align: left; flex-shrink: 0; margin-right: 1rem;
            }
            .confirmed-badge, .confirm-btn { width: auto; }
            .actions { justify-content: flex-end; }
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
        <span class="page-badge"><i class="fa-solid fa-box-open"></i> Miscellaneous</span>
    </div>

    <div class="page-header">
        <div class="header-info">
            <h1>Other <span>Purchases</span></h1>
            <p>Log and manage miscellaneous, uncategorized, or special-order acquisitions.</p>
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
        <input type="text" class="search-input" id="searchInput"
               placeholder="Quick search by item name, supplier, or reference no..."
               oninput="filterTable()">
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
                        <th>Net Weight</th>
                        <th>Unit Cost</th>
                        <th>Total Cost</th>
                        <th>Category</th>
                        <th>Purchase Date</th>
                        <th style="text-align:center; width:140px;">Status</th>
                        <th style="text-align:center; width:110px;">Actions</th>
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
                                    No miscellaneous purchases recorded yet in this location.
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
                                <div class="val-mono" style="color:#fff;"><?php echo number_format($item['QUANTITY'] ?? 0, 2); ?></div>
                            </td>
                            <td data-label="Unit">
                                <div class="item-unit"><?php echo htmlspecialchars($item['UNIT_NAME']); ?></div>
                            </td>
                            <td data-label="Net Weight">
                                <div class="item-unit"><?php echo htmlspecialchars($item['ITEM_NET_WEIGHT'] ?? 'N/A'); ?></div>
                            </td>
                            <td data-label="Unit Cost">
                                <div class="val-money" style="color:var(--text-primary);">₱<?php echo number_format($item['UNIT_COST'], 2); ?></div>
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
                            <td data-label="Status" style="text-align:center;">
                                <?php if(!$isConfirmed): ?>
                                    <button class="confirm-btn" onclick="openConfirmModal(this)">Confirm</button>
                                <?php else: ?>
                                    <div class="confirmed-badge"><i class="fa-solid fa-check"></i> Verified</div>
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
                                        <span class="action-btn locked" title="Locked — record verified">
                                            <i class="fa-solid fa-lock"></i>
                                        </span>
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
                <p>No purchases match your search term.</p>
            </div>
        </div>
    </div>
</div>

<!-- ─── ADD / EDIT MODAL ─── -->
<div id="modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modal-title">Add Miscellaneous Purchase</h2>
            <button class="modal-close" onclick="closeModal()" title="Close"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body">
            <form id="item-form" method="POST">
                <input type="hidden" id="item-id" name="item_id">
                <input type="hidden" name="item_type_id" value="<?php echo $ITEM_TYPE_ID; ?>">

                <!-- Item Information -->
                <div class="form-section">
                    <div class="form-section-title">Item Information</div>

                    <div class="form-group autocomplete-wrapper" style="margin-bottom:1rem;">
                        <label class="form-label" for="item-name">Item Name <span class="req">*</span></label>
                        <input type="text" id="item-name" name="item_name" class="form-control"
                               placeholder="e.g., Seasonal Tarpaulin" required maxlength="300" autocomplete="off">
                        <div id="autocomplete-list" class="autocomplete-list"></div>
                    </div>

                    <div class="form-row" style="margin-bottom:1rem;">
                        <div class="form-group autocomplete-wrapper">
                            <label class="form-label">Supplier</label>
                            <input type="text" id="supplier" name="supplier" class="form-control"
                                   placeholder="e.g., General Store" autocomplete="off">
                            <div id="supplier-autocomplete-list" class="autocomplete-list"></div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Reference No.</label>
                            <input type="text" id="reference-no" name="reference_no" class="form-control" placeholder="e.g., OR-12345">
                        </div>
                    </div>

                    <div class="form-row" style="margin-bottom:1rem;">
                        <div class="form-group">
                            <label class="form-label" for="item-quantity">Quantity <span class="req">*</span></label>
                            <input type="number" id="item-quantity" name="item_quantity" class="form-control val-mono"
                                   placeholder="e.g., 5" step="0.01" min="0" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="unit">Unit of Measurement <span class="req">*</span></label>
                            <select id="unit" name="unit_id" class="form-select" required>
                                <option value="">Select Unit</option>
                                <?php foreach($units as $unit): ?>
                                    <option value="<?php echo $unit['UNIT_ID']; ?>"><?php echo htmlspecialchars($unit['UNIT_NAME']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-row" style="margin-bottom:1rem;">
                        <div class="form-group">
                            <label class="form-label" for="unit-cost">Unit Cost (₱) <span class="req">*</span></label>
                            <input type="number" id="unit-cost" name="unit_cost" class="form-control val-mono"
                                   placeholder="0.00" step="0.01" min="0" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="item-category">Category <span class="req">*</span></label>
                            <select id="item-category" name="item_category" class="form-select" required>
                                <option value="">Select Category</option>
                                <option value="0">Non-Consumable</option>
                                <option value="1">Consumable</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row" style="margin-bottom:1rem;">
                        <div class="form-group">
                            <label class="form-label" for="purchase-date">Date of Purchase <span class="req">*</span></label>
                            <input type="text" id="purchase-date" name="date_of_purchase" class="form-control"
                                   placeholder="mm/dd/yyyy" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="net-weight">Net Weight <span class="opt">(optional)</span></label>
                            <input type="number" id="net-weight" name="item_net_weight" class="form-control val-mono"
                                   placeholder="e.g., 5.5" step="0.01" min="0">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="item-desc">Description / Specs</label>
                        <textarea id="item-desc" name="item_description" class="form-control"
                                  placeholder="Enter detailed specifications..." rows="2" maxlength="500"></textarea>
                    </div>
                </div>

                <!-- Location -->
                <div class="form-section">
                    <div class="form-section-title">Initial Location</div>
                    <!-- FIX: 3-col grid so all 3 selects sit on one row without overflow -->
                    <div class="form-row cols-3">
                        <div class="form-group">
                            <label class="form-label" for="location_id">Location</label>
                            <select id="location_id" name="location_id" class="form-select"
                                    onchange="filterBuildings()"
                                    <?php echo ($USER_LOCATION_ != 1000) ? 'disabled' : ''; ?> required>
                                <?php if($USER_LOCATION_ == 1000): ?>
                                    <option value="">Select Location</option>
                                <?php endif; ?>
                                <?php foreach($locations as $loc): ?>
                                    <option value="<?php echo $loc['LOCATION_ID']; ?>"
                                        <?php echo ($USER_LOCATION_ != 1000 && $loc['LOCATION_ID'] == $USER_LOCATION_) ? 'selected' : ''; ?>>
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
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-ghost" onclick="closeModal()">Cancel</button>
            <button type="button" class="btn btn-primary" id="btn-save" onclick="saveItem()">
                <i class="fa-solid fa-floppy-disk"></i> Save Purchase
            </button>
        </div>
    </div>
</div>

<!-- ─── VIEW MODAL ─── -->
<div id="view-modal" class="modal">
    <div class="modal-content" style="max-width:520px;">
        <div class="modal-header">
            <h2>Purchase Dossier</h2>
            <button class="modal-close" onclick="closeViewModal()"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body" id="view-modal-body"></div>
        <div class="modal-footer">
            <button type="button" class="btn btn-ghost" onclick="closeViewModal()" style="flex:1;">Close</button>
        </div>
    </div>
</div>

<!-- ─── SINGLE CONFIRM MODAL ─── -->
<div id="confirm-modal" class="modal">
    <div class="modal-content" style="max-width:440px;">
        <div class="modal-header">
            <h2>Verify Acquisition</h2>
            <button class="modal-close" onclick="closeConfirmModal()"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body confirm-content">
            <span class="confirm-icon">📦</span>
            <p>You are confirming the intake of<br>
               <strong><span id="confirm-item-qty"></span> × <span id="confirm-item-name" style="color:var(--pink);"></span></strong>.
            </p>
            <div class="warning-text">
                <i class="fa-solid fa-triangle-exclamation"></i> <strong>Critical:</strong>
                Once confirmed, this financial record is locked and cannot be edited or deleted.
            </div>
            <form id="confirmForm" method="POST">
                <input type="hidden" id="confirm_item_id" name="item_id">
            </form>
        </div>
        <div class="modal-footer" style="justify-content:center; background:transparent; border-top:none;">
            <button type="button" class="btn btn-ghost" onclick="closeConfirmModal()">Cancel</button>
            <button type="button" class="btn btn-primary" id="confirm-submit-btn"
                    onclick="submitConfirmation()" style="background:var(--red); border-color:var(--red);">
                <i class="fa-solid fa-lock"></i> Lock Record
            </button>
        </div>
    </div>
</div>

<!-- ─── CONFIRM ALL MODAL ─── -->
<div id="confirm-all-modal" class="modal">
    <div class="modal-content" style="max-width:440px;">
        <div class="modal-header">
            <h2>Commit All Pending</h2>
            <button class="modal-close" onclick="closeConfirmAllModal()"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body confirm-content">
            <span class="confirm-icon">📋</span>
            <p>This will verify and lock <strong>ALL</strong> currently pending miscellaneous purchases in this location.</p>
            <div class="warning-text warning-amber">
                <i class="fa-solid fa-triangle-exclamation"></i> <strong>Irreversible:</strong>
                Please audit all pending items before executing this batch commit.
            </div>
        </div>
        <div class="modal-footer" style="justify-content:center; background:transparent; border-top:none;">
            <button type="button" class="btn btn-ghost" onclick="closeConfirmAllModal()">Cancel</button>
            <button type="button" class="btn btn-amber" id="confirm-all-btn" onclick="submitConfirmAll()">
                <i class="fa-solid fa-check-double"></i> Commit All Now
            </button>
        </div>
    </div>
</div>

<!-- Hidden delete form -->
<form id="deleteItemForm" method="POST" action="../process/deleteOtherPurchase.php" style="display:none;">
    <input type="hidden" id="delete_item_id" name="item_id">
</form>

<script>
    const allBuildings = <?php echo json_encode($buildings_raw); ?>;
    const allPens      = <?php echo json_encode($pens_raw); ?>;
    const USER_LOCATION = <?php echo json_encode($USER_LOCATION_); ?>;

    let fpPurchaseDate;

    // ─── INIT ───
    document.addEventListener('DOMContentLoaded', () => {
        fpPurchaseDate = flatpickr("#purchase-date", {
            dateFormat: "Y-m-d",
            altInput:   true,
            altFormat:  "m/d/Y",
            allowInput: true
        });

        // FIX: was calling loadBuildings('src') / loadBuildings('dest') which don't exist
        if (USER_LOCATION != 1000) {
            filterBuildings();
        }

        filterTable(); // initial empty-state sync
        initSupplierAutocomplete();
    });

    // ─── LOCATION CASCADES ───
    function filterBuildings() {
        const locId  = document.getElementById('location_id').value;
        const bldgSel = document.getElementById('building_id');
        const penSel  = document.getElementById('pen_id');

        bldgSel.innerHTML = '<option value="">Select Building</option>';
        penSel.innerHTML  = '<option value="">Select Building First</option>';
        penSel.disabled   = true;

        if (locId) {
            bldgSel.disabled = false;
            allBuildings.filter(b => b.LOCATION_ID == locId).forEach(b => {
                const o = document.createElement('option');
                o.value = b.BUILDING_ID; o.textContent = b.BUILDING_NAME;
                bldgSel.appendChild(o);
            });
        } else {
            bldgSel.disabled = true;
        }
    }

    function filterPens() {
        const bldgId = document.getElementById('building_id').value;
        const penSel = document.getElementById('pen_id');
        penSel.innerHTML = '<option value="">Select Pen</option>';

        if (bldgId) {
            penSel.disabled = false;
            allPens.filter(p => p.BUILDING_ID == bldgId).forEach(p => {
                const o = document.createElement('option');
                o.value = p.PEN_ID; o.textContent = p.PEN_NAME;
                penSel.appendChild(o);
            });
        } else {
            penSel.disabled = true;
        }
    }

    // ─── ITEM NAME AUTOCOMPLETE ───
    let autocompleteTimeout = null;
    let currentFocus = -1;

    function initAutocomplete() {
        // Clone to strip old listeners
        const old = document.getElementById('item-name');
        const fresh = old.cloneNode(true);
        old.parentNode.replaceChild(fresh, old);
        const input = document.getElementById('item-name');
        const list  = document.getElementById('autocomplete-list');

        input.addEventListener('input', function () {
            const val = this.value.trim();
            clearTimeout(autocompleteTimeout);
            if (val.length < 2) { closeAutocomplete(); return; }
            list.innerHTML = '<div class="autocomplete-loading">Searching…</div>';
            list.classList.add('show');
            autocompleteTimeout = setTimeout(() => fetchItemAutocomplete(val), 300);
        });

        input.addEventListener('keydown', function (e) {
            const items = list.getElementsByClassName('autocomplete-item');
            if      (e.key === 'ArrowDown')  { e.preventDefault(); currentFocus++; addActive(items); }
            else if (e.key === 'ArrowUp')    { e.preventDefault(); currentFocus--; addActive(items); }
            else if (e.key === 'Enter' && currentFocus > -1 && items[currentFocus]) {
                e.preventDefault(); items[currentFocus].click();
            } else if (e.key === 'Escape')   { closeAutocomplete(); }
        });

        document.addEventListener('click', function (e) {
            if (!e.target.closest('.autocomplete-wrapper')) closeAutocomplete();
        });
    }

    function fetchItemAutocomplete(term) {
        fetch(`../process/searchOthers.php?term=${encodeURIComponent(term)}`)
            .then(r => r.json())
            .then(data => displayItemAutocomplete(data, term))
            .catch(() => closeAutocomplete());
    }

    function displayItemAutocomplete(results, term) {
        const list = document.getElementById('autocomplete-list');
        list.innerHTML = ''; currentFocus = -1;
        if (!results.length) {
            list.innerHTML = '<div class="autocomplete-no-results">No items found</div>';
            list.classList.add('show'); return;
        }
        results.forEach(item => {
            const div = document.createElement('div');
            div.className = 'autocomplete-item';
            div.innerHTML = item.replace(new RegExp(`(${escapeRegex(term)})`, 'gi'), '<strong>$1</strong>');
            div.addEventListener('click', () => {
                document.getElementById('item-name').value = item;
                closeAutocomplete();
            });
            list.appendChild(div);
        });
        list.classList.add('show');
    }

    function addActive(items) {
        if (!items.length) return;
        removeActive(items);
        if (currentFocus >= items.length) currentFocus = 0;
        if (currentFocus < 0) currentFocus = items.length - 1;
        items[currentFocus].classList.add('active');
        items[currentFocus].scrollIntoView({ block: 'nearest' });
    }
    function removeActive(items) { [...items].forEach(i => i.classList.remove('active')); }
    function closeAutocomplete() {
        const list = document.getElementById('autocomplete-list');
        if (list) { list.classList.remove('show'); list.innerHTML = ''; }
        currentFocus = -1;
    }
    function escapeRegex(s) { return s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); }

    // ─── SUPPLIER AUTOCOMPLETE ───
    function initSupplierAutocomplete() {
        const input = document.getElementById('supplier');
        const list  = document.getElementById('supplier-autocomplete-list');
        if (!input) return;
        let timer = null;

        input.addEventListener('input', function () {
            clearTimeout(timer);
            const val = this.value.trim();
            if (val.length < 2) { list.classList.remove('show'); return; }
            list.innerHTML = '<div class="autocomplete-loading">Searching…</div>';
            list.classList.add('show');
            timer = setTimeout(() => {
                fetch(`../process/searchSuppliers.php?term=${encodeURIComponent(val)}`)
                    .then(r => r.json())
                    .then(data => {
                        list.innerHTML = '';
                        if (!data.length) { list.innerHTML = '<div class="autocomplete-no-results">No matches</div>'; return; }
                        data.forEach(s => {
                            const div = document.createElement('div');
                            div.className = 'autocomplete-item';
                            div.innerHTML = s.replace(new RegExp(`(${escapeRegex(val)})`, 'gi'), '<strong>$1</strong>');
                            div.addEventListener('click', () => { input.value = s; list.classList.remove('show'); });
                            list.appendChild(div);
                        });
                    }).catch(() => list.classList.remove('show'));
            }, 300);
        });

        document.addEventListener('click', e => {
            if (!input.closest('.autocomplete-wrapper').contains(e.target)) list.classList.remove('show');
        });
    }

    // ─── MODAL OPEN / CLOSE ───
    function openAddModal() {
        document.getElementById('modal-title').textContent = 'Add Miscellaneous Purchase';
        document.getElementById('btn-save').innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Save Purchase';
        document.getElementById('item-form').reset();
        document.getElementById('item-id').value = '';
        document.getElementById('item-category').value = '0';
        document.getElementById('unit').value = '';

        fpPurchaseDate.setDate(new Date());

        const locSelect = document.getElementById('location_id');
        if (USER_LOCATION != 1000) {
            locSelect.value = USER_LOCATION;
            filterBuildings();
        } else {
            locSelect.value = '';
            const bldgSel = document.getElementById('building_id');
            bldgSel.innerHTML = '<option value="">Select Location First</option>';
            bldgSel.disabled  = true;
            const penSel = document.getElementById('pen_id');
            penSel.innerHTML  = '<option value="">Select Building First</option>';
            penSel.disabled   = true;
        }

        document.getElementById('modal').classList.add('show');
        setTimeout(initAutocomplete, 100);
    }

    function closeModal() {
        closeAutocomplete();
        document.getElementById('modal').classList.remove('show');
    }
    function closeViewModal()       { document.getElementById('view-modal').classList.remove('show'); }
    function closeConfirmModal()    { document.getElementById('confirm-modal').classList.remove('show'); }
    function closeConfirmAllModal() { document.getElementById('confirm-all-modal').classList.remove('show'); }

    // Backdrop close for all modals
    document.querySelectorAll('.modal').forEach(m => {
        m.addEventListener('click', function (e) {
            if (e.target === this) {
                this.classList.remove('show');
                closeAutocomplete();
            }
        });
    });

    // ─── VIEW ───
    function viewItem(button) {
        const row = button.closest('tr');
        displayItemDetails({
            item_id:          row.dataset.itemId,
            item_name:        row.dataset.itemName,
            item_description: row.dataset.itemDesc,
            unit:             row.dataset.unitName,
            unit_cost:        row.dataset.unitCost,
            item_category:    row.dataset.itemCategory,
            net_weight:       row.dataset.netWeight,
            quantity:         row.dataset.quantity,
            purchase_date_fmt:row.dataset.purchaseDateFmt,
            supplier:         row.dataset.supplier,
            reference_no:     row.dataset.referenceNo,
            created_at:       row.dataset.createdAt
        });
    }

    function displayItemDetails(data) {
        const catLabels = {0: 'Non-Consumable', 1: 'Consumable'};
        const fmt = v => parseFloat(v).toLocaleString('en-PH', { minimumFractionDigits: 2 });
        const html = `
            <div class="form-section">
                <div class="form-section-title">Acquisition Overview</div>
                <div class="detail-grid">
                    <div class="detail-item"><div class="label">System ID</div><div class="value val-mono">PCH-${String(data.item_id).padStart(5,'0')}</div></div>
                    <div class="detail-item"><div class="label">Reference No</div><div class="value val-mono">${data.reference_no || '—'}</div></div>
                    <div class="detail-item" style="grid-column:1/-1;"><div class="label">Item Name</div><div class="value" style="font-weight:700;font-size:1rem;">${data.item_name}</div></div>
                    <div class="detail-item" style="grid-column:1/-1;"><div class="label">Supplier</div><div class="value">${data.supplier || 'N/A'}</div></div>
                    <div class="detail-item" style="grid-column:1/-1;"><div class="label">Remarks</div><div class="value" style="color:var(--text-secondary);">${data.item_description || 'None'}</div></div>
                </div>
            </div>
            <div class="form-section" style="margin-top:1.25rem;">
                <div class="form-section-title">Financials & Metrics</div>
                <div class="detail-grid">
                    <div class="detail-item"><div class="label">Batch Size</div><div class="value">${data.quantity} ${data.unit}</div></div>
                    <div class="detail-item"><div class="label">Net Weight</div><div class="value val-mono">${data.net_weight || '—'}</div></div>
                    <div class="detail-item"><div class="label">Unit Cost</div><div class="value val-money">₱${fmt(data.unit_cost)}</div></div>
                    <div class="detail-item"><div class="label">Category</div><div class="value">${catLabels[data.item_category]}</div></div>
                    <div class="detail-item"><div class="label">Purchase Date</div><div class="value val-mono">${data.purchase_date_fmt || 'N/A'}</div></div>
                    <div class="detail-item"><div class="label">Logged At</div><div class="value val-mono">${data.created_at || 'N/A'}</div></div>
                </div>
            </div>`;
        document.getElementById('view-modal-body').innerHTML = html;
        document.getElementById('view-modal').classList.add('show');
    }

    // ─── EDIT ───
    function editItem(button) {
        const row = button.closest('tr');
        populateEditForm({
            item_id:          row.dataset.itemId,
            item_name:        row.dataset.itemName,
            item_description: row.dataset.itemDesc,
            unit_id:          row.dataset.unitId,
            unit_cost:        row.dataset.unitCost,
            item_category:    row.dataset.itemCategory,
            net_weight:       row.dataset.netWeight,
            quantity:         row.dataset.quantity,
            purchase_date_raw:row.dataset.purchaseDateRaw,
            location_id:      row.dataset.locationId,
            building_id:      row.dataset.buildingId,
            pen_id:           row.dataset.penId,
            supplier:         row.dataset.supplier,
            reference_no:     row.dataset.referenceNo
        });
    }

    function populateEditForm(data) {
        document.getElementById('modal-title').textContent = 'Edit Miscellaneous Purchase';
        document.getElementById('btn-save').innerHTML = '<i class="fa-solid fa-arrows-rotate"></i> Update Purchase';
        document.getElementById('item-id').value       = data.item_id;
        document.getElementById('item-name').value     = data.item_name;
        document.getElementById('item-desc').value     = data.item_description || '';
        document.getElementById('unit').value          = data.unit_id;
        document.getElementById('unit-cost').value     = data.unit_cost;
        document.getElementById('item-category').value = data.item_category;
        document.getElementById('net-weight').value    = data.net_weight || '';
        document.getElementById('item-quantity').value = data.quantity || '0';
        document.getElementById('supplier').value      = data.supplier || '';
        document.getElementById('reference-no').value  = data.reference_no || '';

        fpPurchaseDate.setDate(data.purchase_date_raw || '');

        document.getElementById('location_id').value = data.location_id || '';
        filterBuildings();

        if (data.building_id) {
            document.getElementById('building_id').value = data.building_id;
            filterPens();
            if (data.pen_id) document.getElementById('pen_id').value = data.pen_id;
        }

        document.getElementById('modal').classList.add('show');
        setTimeout(initAutocomplete, 100);
    }

    // ─── DELETE ───
    function deleteItem(button) {
        const row = button.closest('tr');
        if (confirm(`Delete "${row.dataset.itemName}"? This cannot be undone.`)) {
            document.getElementById('delete_item_id').value = row.dataset.itemId;
            document.getElementById('deleteItemForm').submit();
        }
    }

    // ─── SAVE (ADD / EDIT) ───
    function saveItem() {
        const form = document.getElementById('item-form');
        if (!form.checkValidity()) { form.reportValidity(); return; }

        const isEdit  = document.getElementById('item-id').value !== '';
        const url     = isEdit ? '../process/editOthers.php' : '../process/addOthers.php';
        const saveBtn = document.getElementById('btn-save');

        saveBtn.disabled = true;
        saveBtn.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> ${isEdit ? 'Updating…' : 'Saving…'}`;

        fetch(url, { method: 'POST', body: new FormData(form) })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    setTimeout(() => location.reload(), 1200);
                } else {
                    showToast(data.message || 'Error saving item', 'error');
                    saveBtn.disabled = false;
                    saveBtn.innerHTML = isEdit
                        ? '<i class="fa-solid fa-arrows-rotate"></i> Update Purchase'
                        : '<i class="fa-solid fa-floppy-disk"></i> Save Purchase';
                }
            })
            .catch(() => {
                showToast('An error occurred while saving.', 'error');
                saveBtn.disabled = false;
                saveBtn.innerHTML = isEdit
                    ? '<i class="fa-solid fa-arrows-rotate"></i> Update Purchase'
                    : '<i class="fa-solid fa-floppy-disk"></i> Save Purchase';
            });
    }

    document.getElementById('item-form').addEventListener('submit', e => { e.preventDefault(); saveItem(); });

    // ─── CONFIRM (SINGLE) ───
    function openConfirmModal(button) {
        const row = button.closest('tr');
        document.getElementById('confirm_item_id').value = row.dataset.itemId;
        document.getElementById('confirm-item-name').textContent = row.dataset.itemName;
        document.getElementById('confirm-item-qty').textContent  = row.dataset.quantity;
        document.getElementById('confirm-modal').classList.add('show');
    }

    function submitConfirmation() {
        const btn = document.getElementById('confirm-submit-btn');
        btn.disabled = true; btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Confirming…';

        fetch('../purchase_confirmations/confirmOthers.php', { method: 'POST', body: new FormData(document.getElementById('confirmForm')) })
            .then(r => r.json())
            .then(data => {
                if (data.success) { showToast(data.message, 'success'); setTimeout(() => location.reload(), 1000); }
                else { showToast(data.message, 'error'); btn.disabled = false; btn.innerHTML = '<i class="fa-solid fa-lock"></i> Lock Record'; }
            })
            .catch(() => {
                showToast('Error confirming record.', 'error');
                btn.disabled = false; btn.innerHTML = '<i class="fa-solid fa-lock"></i> Lock Record';
            });
    }

    // ─── CONFIRM ALL ───
    function openConfirmAllModal()  { document.getElementById('confirm-all-modal').classList.add('show'); }

    function submitConfirmAll() {
        const btn = document.getElementById('confirm-all-btn');
        btn.disabled = true; btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Processing…';

        fetch('../purchase_confirmations/confirmAllOthers.php', { method: 'POST' })
            .then(r => r.json())
            .then(data => {
                if (data.success) { showToast(data.message, 'success'); setTimeout(() => location.reload(), 1000); }
                else { showToast(data.message, 'error'); btn.disabled = false; btn.innerHTML = '<i class="fa-solid fa-check-double"></i> Commit All Now'; }
            })
            .catch(() => {
                showToast('Error confirming all items.', 'error');
                btn.disabled = false; btn.innerHTML = '<i class="fa-solid fa-check-double"></i> Commit All Now';
            });
    }

    // ─── SEARCH FILTER ───
    function filterTable() {
        const term  = document.getElementById('searchInput').value.toLowerCase();
        const rows  = document.querySelectorAll('#item-table tr');
        let visible = 0;

        rows.forEach(row => {
            if (row.querySelector('.empty-state')) return;
            const match = row.textContent.toLowerCase().includes(term);
            row.style.display = match ? '' : 'none';
            if (match) visible++;
        });

        const jsEmpty = document.getElementById('empty-state-js');
        const hasData = [...rows].some(r => !r.querySelector('.empty-state'));
        jsEmpty.style.display = (hasData && visible === 0) ? 'block' : 'none';
    }

    // ─── TOAST ───
    function showToast(msg, type = 'success') {
        const t = document.createElement('div');
        t.className = 'toast';
        t.style.borderLeft = `4px solid ${type === 'error' ? 'var(--red)' : 'var(--pink)'}`;
        t.innerHTML = `${type === 'error' ? '❌' : '✅'} ${msg}`;
        document.getElementById('toastContainer').appendChild(t);
        setTimeout(() => t.remove(), 3500);
    }
</script>
</body>
</html>