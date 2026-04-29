<?php
// views/purch_feeds_feeding.php
error_reporting(0);
ini_set('display_errors', 0);
include '../config/Connection.php';

include '../security/checkAccess.php';
checkAccess('purchases');
$page = "transactions";
include '../common/navbar.php';
include '../common/chat_support.php';
include '../functions/getUsersLocation.php';

$ITEM_TYPE_ID = 2;

try {
    if (!isset($conn)) { throw new Exception("Database connection failed."); }

    if ($USER_LOCATION_ != 1000) {
        $items_sql = "SELECT i.*, it.ITEM_TYPE_NAME, u.UNIT_NAME,
                      DATE_FORMAT(i.DATE_OF_PURCHASE, '%m/%d/%Y') as DATE_OF_PURCHASE_FMT,
                      DATE_FORMAT(i.EXPIRATION_DATE,  '%m/%d/%Y') as EXPIRATION_DATE_FMT,
                      DATE_FORMAT(i.CREATED_AT, '%m/%d/%Y %h:%i %p') as CREATED_AT_FMT
                      FROM ITEMS i
                      LEFT JOIN ITEM_TYPES it ON i.ITEM_TYPE_ID = it.ITEM_TYPE_ID
                      LEFT JOIN UNITS u       ON i.UNIT_ID      = u.UNIT_ID
                      WHERE i.ITEM_TYPE_ID = :type_id AND LOCATION_ID = :location_id
                      ORDER BY i.CREATED_AT DESC";
        $stmt = $conn->prepare($items_sql);
        $stmt->execute([':type_id' => $ITEM_TYPE_ID, ':location_id' => $USER_LOCATION_]);
    } else {
        $items_sql = "SELECT i.*, it.ITEM_TYPE_NAME, u.UNIT_NAME,
                      DATE_FORMAT(i.DATE_OF_PURCHASE, '%m/%d/%Y') as DATE_OF_PURCHASE_FMT,
                      DATE_FORMAT(i.EXPIRATION_DATE,  '%m/%d/%Y') as EXPIRATION_DATE_FMT,
                      DATE_FORMAT(i.CREATED_AT, '%m/%d/%Y %h:%i %p') as CREATED_AT_FMT
                      FROM ITEMS i
                      LEFT JOIN ITEM_TYPES it ON i.ITEM_TYPE_ID = it.ITEM_TYPE_ID
                      LEFT JOIN UNITS u       ON i.UNIT_ID      = u.UNIT_ID
                      WHERE i.ITEM_TYPE_ID = :type_id
                      ORDER BY i.CREATED_AT DESC";
        $stmt = $conn->prepare($items_sql);
        $stmt->execute([':type_id' => $ITEM_TYPE_ID]);
    }
    $items_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $units_sql = "SELECT * FROM UNITS WHERE UNIT_NAME = 'Kilograms' ORDER BY UNIT_NAME ASC";
    $stmt = $conn->prepare($units_sql); $stmt->execute();
    $units = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($USER_LOCATION_ != 1000) {
        $loc_sql = "SELECT * FROM LOCATIONS WHERE LOCATION_ID = :lid ORDER BY LOCATION_NAME ASC";
        $stmt = $conn->prepare($loc_sql); $stmt->execute([':lid' => $USER_LOCATION_]);
    } else {
        $loc_sql = "SELECT * FROM LOCATIONS ORDER BY LOCATION_NAME ASC";
        $stmt = $conn->prepare($loc_sql); $stmt->execute();
    }
    $locations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $conn->prepare("SELECT * FROM BUILDINGS ORDER BY BUILDING_NAME ASC"); $stmt->execute();
    $buildings_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $conn->prepare("SELECT * FROM PENS ORDER BY PEN_NAME ASC"); $stmt->execute();
    $pens_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $items_data = []; $units = []; $locations = []; $buildings_raw = []; $pens_raw = [];
    echo "<script>console.error('DB Error: " . addslashes($e->getMessage()) . "');</script>";
}

$categoryLabels  = [0 => 'Non-Consumable', 1 => 'Consumable'];
$categoryClasses = [0 => 'category-nonconsumable', 1 => 'category-consumable'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Feed Purchases | FarmPro</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,wght@0,400;0,500;0,600;0,700;1,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"/>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="https://npmcdn.com/flatpickr/dist/themes/dark.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    <style>
        :root {
            --bg-base:        #080f1a;
            --bg-surface:     #0d1829;
            --bg-elevated:    #111f35;
            --bg-hover:       #162540;
            --border:         rgba(255,255,255,0.07);
            --border-active:  rgba(245,158,11,0.5);
            --amber:          #f59e0b;
            --amber-dim:      rgba(245,158,11,0.12);
            --amber-glow:     rgba(245,158,11,0.25);
            --emerald:        #10b981;
            --emerald-dim:    rgba(16,185,129,0.12);
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

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: var(--font);
            background: var(--bg-base);
            color: var(--text-primary);
            min-height: 100vh;
            padding-bottom: 60px;
            background-image: radial-gradient(ellipse 80% 50% at 50% -20%, rgba(245,158,11,0.06) 0%, transparent 60%);
        }

        .container { max-width: 1560px; margin: 0 auto; padding: 2rem 1.5rem; }

        /* ── TOP BAR ── */
        .top-bar { display: flex; align-items: center; justify-content: space-between; margin-bottom: 2rem; gap: 1rem; flex-wrap: wrap; }
        .back-link { display: inline-flex; align-items: center; gap: 8px; text-decoration: none; color: var(--text-secondary); font-size: 0.875rem; font-weight: 500; padding: 8px 14px; background: var(--bg-elevated); border: 1px solid var(--border); border-radius: var(--radius-md); transition: all var(--transition); }
        .back-link:hover { color: var(--text-primary); border-color: var(--border-active); background: var(--bg-hover); }
        .page-badge { display: inline-flex; align-items: center; gap: 6px; font-size: 0.75rem; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; color: var(--amber); background: var(--amber-dim); border: 1px solid rgba(245,158,11,0.2); padding: 6px 12px; border-radius: 99px; }

        /* ── PAGE HEADER ── */
        .page-header { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 2rem; gap: 1.5rem; flex-wrap: wrap; }
        .header-info h1 { font-size: clamp(1.6rem, 3vw, 2.2rem); font-weight: 700; color: var(--text-primary); letter-spacing: -0.03em; line-height: 1.1; margin-bottom: 0.25rem; }
        .header-info h1 span { background: linear-gradient(135deg, var(--amber), #b45309); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .header-info p { color: var(--text-secondary); font-size: 0.95rem; }
        .header-actions { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }

        /* ── BUTTONS ── */
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 10px 18px; border-radius: var(--radius-md); font-size: 0.875rem; font-weight: 600; font-family: var(--font); border: 1px solid transparent; cursor: pointer; transition: all var(--transition); text-decoration: none; white-space: nowrap; }
        .btn-sm { padding: 6px 12px; font-size: 0.8rem; }
        .btn-primary  { background: var(--amber);   color: #000; }
        .btn-primary:hover  { background: #fbbf24; box-shadow: 0 0 16px var(--amber-glow); transform: translateY(-1px); }
        .btn-success  { background: var(--emerald); color: #fff; }
        .btn-success:hover  { background: #34d399; box-shadow: 0 0 16px rgba(16,185,129,0.3); transform: translateY(-1px); }
        .btn-danger   { background: var(--red-dim); color: var(--red); border-color: rgba(248,113,113,0.3); }
        .btn-danger:hover   { background: var(--red); color: #fff; box-shadow: 0 0 12px rgba(248,113,113,0.3); }
        .btn-ghost    { background: transparent; color: var(--text-secondary); border-color: var(--border); }
        .btn-ghost:hover    { background: var(--bg-elevated); color: var(--text-primary); border-color: rgba(255,255,255,0.15); }

        /* ── SEARCH ── */
        .search-container { position: relative; margin-bottom: 1.5rem; }
        .search-icon { position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--text-muted); pointer-events: none; }
        .search-input { width: 100%; padding: 12px 12px 12px 2.8rem; background: var(--bg-surface); border: 1px solid var(--border); border-radius: var(--radius-lg); color: var(--text-primary); font-size: 0.95rem; font-family: var(--font); outline: none; transition: all var(--transition); }
        .search-input:focus { border-color: var(--amber); box-shadow: 0 0 0 3px var(--amber-glow); }
        .search-input::placeholder { color: var(--text-muted); }

        /* ── TABLE ── */
        .table-card { background: var(--bg-surface); border: 1px solid var(--border); border-radius: var(--radius-xl); overflow: hidden; box-shadow: var(--shadow-md); }
        .table-wrap  { overflow-x: auto; }
        .table { width: 100%; border-collapse: collapse; min-width: 1100px; }
        .table thead th { background: var(--bg-elevated); color: var(--text-muted); font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.07em; padding: 14px 16px; text-align: left; border-bottom: 1px solid var(--border); white-space: nowrap; }
        .table tbody tr { border-bottom: 1px solid var(--border); transition: background var(--transition); }
        .table tbody tr:last-child { border-bottom: none; }
        .table tbody tr:hover { background: rgba(255,255,255,0.02); }
        .table td { padding: 14px 16px; font-size: 0.9rem; color: var(--text-primary); vertical-align: middle; }

        .ref-no       { font-family: var(--font-mono); color: var(--text-muted); font-size: 0.82rem; }
        .supplier-name{ color: var(--text-secondary); font-size: 0.88rem; }
        .item-name    { font-weight: 700; color: #fff; font-size: 0.95rem; }
        .val-mono     { font-family: var(--font-mono); font-weight: 600; font-size: 0.9rem; }
        .val-money    { font-family: var(--font-mono); font-weight: 600; color: var(--emerald); font-size: 0.92rem; }

        /* ── STATUS & CATEGORY BADGES ── */
        .status-badge    { display: inline-flex; align-items: center; justify-content: center; gap: 5px; border-radius: 6px; padding: 6px 12px; font-weight: 700; font-size: 0.72rem; text-transform: uppercase; width: 100%; }
        .badge-confirmed { background: var(--emerald-dim); color: var(--emerald); border: 1px solid rgba(16,185,129,0.25); }
        .btn-confirm-row { background: var(--red-dim); color: var(--red); border: 1px solid rgba(248,113,113,0.3); padding: 6px 12px; border-radius: 6px; font-weight: 700; font-size: 0.72rem; cursor: pointer; transition: all var(--transition); width: 100%; text-transform: uppercase; font-family: var(--font); }
        .btn-confirm-row:hover { background: var(--red); color: #fff; box-shadow: 0 4px 12px rgba(248,113,113,0.3); }

        .category-badge { display: inline-block; padding: 4px 10px; border-radius: 999px; font-size: 0.72rem; font-weight: 600; text-transform: uppercase; white-space: nowrap; }
        .category-consumable    { background: var(--amber-dim);   color: var(--amber);   border: 1px solid rgba(245,158,11,0.2); }
        .category-nonconsumable { background: var(--emerald-dim); color: var(--emerald); border: 1px solid rgba(16,185,129,0.2); }

        /* ── ACTION BUTTONS ── */
        .actions    { display: flex; gap: 6px; justify-content: center; align-items: center; }
        .action-btn { width: 32px; height: 32px; border-radius: 6px; border: 1px solid var(--border); background: var(--bg-elevated); display: inline-flex; align-items: center; justify-content: center; cursor: pointer; transition: all var(--transition); color: var(--text-secondary); font-size: 0.8rem; }
        .action-btn:hover         { background: var(--bg-hover); }
        .action-btn.view:hover   { color: var(--emerald); border-color: var(--emerald); }
        .action-btn.edit:hover   { color: var(--amber);   border-color: var(--amber); }
        .action-btn.delete:hover { color: var(--red);     border-color: var(--red); }
        .locked-icon { opacity: 0.28; font-size: 0.95rem; color: var(--text-muted); display: flex; align-items: center; padding: 0 4px; }

        /* ── MODALS ── */
        .modal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.82); backdrop-filter: blur(5px); z-index: 1100; align-items: center; justify-content: center; padding: 1rem; }
        .modal.show { display: flex; }
        .modal-content { background: var(--bg-surface); border: 1px solid var(--border); border-radius: var(--radius-xl); width: 100%; max-width: 660px; display: flex; flex-direction: column; animation: modalZoom 0.2s ease-out; max-height: 93vh; }
        .modal-content.narrow { max-width: 440px; }
        .modal-content.wide   { max-width: 860px; }
        @keyframes modalZoom { from { transform: scale(0.95); opacity: 0; } to { transform: scale(1); opacity: 1; } }

        .modal-header { padding: 1.2rem 1.5rem; border-bottom: 1px solid var(--border); flex-shrink: 0; display: flex; justify-content: space-between; align-items: center; }
        .modal-header h2 { margin: 0; font-size: 1.1rem; font-weight: 700; color: #fff; display: flex; align-items: center; gap: 8px; }
        .modal-header h2 i { font-size: 0.9rem; }
        .modal-body   { padding: 1.5rem; overflow-y: auto; flex: 1; }
        .modal-footer { padding: 1rem 1.5rem; border-top: 1px solid var(--border); display: flex; justify-content: flex-end; gap: 10px; background: var(--bg-elevated); border-radius: 0 0 var(--radius-xl) var(--radius-xl); flex-shrink: 0; }

        /* ── FORMS ── */
        .form-row   { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem; }
        .form-row-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem; margin-bottom: 1rem; }
        .form-group { display: flex; flex-direction: column; gap: 5px; margin-bottom: 1rem; }
        .form-group:last-child { margin-bottom: 0; }
        .form-label { font-size: 0.7rem; font-weight: 700; text-transform: uppercase; color: var(--text-secondary); letter-spacing: 0.06em; }
        .form-label .req { color: var(--amber); margin-left: 1px; }
        .form-control, .form-select { width: 100%; padding: 10px 12px; background: var(--bg-elevated); border: 1px solid var(--border); color: var(--text-primary); border-radius: 8px; font-size: 0.875rem; font-family: var(--font); outline: none; transition: var(--transition); }
        .form-control:focus, .form-select:focus { border-color: var(--amber); box-shadow: 0 0 0 3px var(--amber-glow); }
        .form-control:disabled, .form-select:disabled { opacity: 0.4; cursor: not-allowed; }
        .form-select { appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2394a3b8' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 12px center; padding-right: 32px; }
        textarea.form-control { resize: vertical; min-height: 70px; }

        .section-label { font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: var(--amber); margin-bottom: 1rem; margin-top: 0.5rem; display: flex; align-items: center; gap: 6px; }
        .section-divider { border: none; border-top: 1px solid var(--border); margin: 1.25rem 0; }

        /* ── INLINE ALERT ── */
        .alert { display: none; padding: 10px 14px; border-radius: 8px; font-size: 0.85rem; font-weight: 500; margin-bottom: 1rem; }
        .alert.error   { background: var(--red-dim);     color: var(--red);     border: 1px solid rgba(248,113,113,0.2); display: block; }
        .alert.success { background: var(--emerald-dim); color: var(--emerald); border: 1px solid rgba(16,185,129,0.2); display: block; }

        /* ── CONFIRM MODAL BODY ── */
        .confirm-content { text-align: center; padding: 0.5rem 0.5rem 0; }
        .confirm-icon    { font-size: 3rem; display: block; margin-bottom: 0.75rem; }
        .confirm-content h2 { color: #fff; font-size: 1.15rem; margin-bottom: 8px; }
        .confirm-content p  { color: var(--text-secondary); font-size: 0.9rem; line-height: 1.6; }
        .warning-text { color: var(--red); font-size: 0.84rem; margin-top: 1rem; background: var(--red-dim); padding: 12px 14px; border-radius: 8px; border: 1px solid rgba(248,113,113,0.2); line-height: 1.5; text-align: left; }

        /* ── DYNAMIC ADD TABLE ── */
        .dynamic-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
        .dynamic-table th { background: var(--bg-elevated); font-size: 0.68rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; padding: 9px 8px; text-align: left; border-bottom: 1px solid var(--border); white-space: nowrap; }
        .dynamic-table td { padding: 6px 6px; border-bottom: 1px solid var(--border); vertical-align: top; }
        .dynamic-table .form-control { padding: 8px 10px; font-size: 0.83rem; min-width: 80px; }
        .dynamic-table .form-select  { padding: 8px 28px 8px 10px; font-size: 0.83rem; }
        .del-row-btn { width: 28px; height: 28px; border-radius: 6px; border: 1px solid rgba(248,113,113,0.3); background: var(--red-dim); color: var(--red); display: inline-flex; align-items: center; justify-content: center; cursor: pointer; transition: all var(--transition); font-size: 0.78rem; }
        .del-row-btn:hover { background: var(--red); color: #fff; }
        .add-row-btn { margin-top: 10px; display: inline-flex; align-items: center; gap: 6px; padding: 7px 14px; background: var(--amber-dim); color: var(--amber); border: 1px dashed rgba(245,158,11,0.4); border-radius: 8px; font-size: 0.82rem; font-weight: 600; cursor: pointer; transition: all var(--transition); font-family: var(--font); }
        .add-row-btn:hover { background: var(--amber-glow); }

        /* ── AUTOCOMPLETE ── */
        .autocomplete-wrapper { position: relative; }
        .autocomplete-list { position: absolute; top: 100%; left: 0; right: 0; background: var(--bg-elevated); border: 1px solid var(--border-active); border-radius: 8px; z-index: 200; max-height: 200px; overflow-y: auto; display: none; box-shadow: 0 8px 24px rgba(0,0,0,0.5); }
        .autocomplete-list.show { display: block; }
        .autocomplete-item { padding: 9px 12px; font-size: 0.875rem; cursor: pointer; color: var(--text-primary); transition: background var(--transition); }
        .autocomplete-item:hover { background: var(--bg-hover); }
        .autocomplete-item strong { color: var(--amber); }
        .autocomplete-loading, .autocomplete-no-results { padding: 10px 12px; font-size: 0.82rem; color: var(--text-muted); }

        /* ── VIEW MODAL SECTIONS ── */
        .view-section { margin-bottom: 1.25rem; }
        .view-section:last-child { margin-bottom: 0; }
        .view-section-title { font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: var(--amber); margin-bottom: 0.75rem; display: flex; align-items: center; gap: 6px; }
        .view-grid  { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        .view-item  { display: flex; flex-direction: column; gap: 3px; }
        .vl { font-size: 0.68rem; font-weight: 700; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.05em; }
        .vv { font-size: 0.9rem; color: var(--text-primary); font-weight: 500; word-break: break-word; }
        .vv.mono   { font-family: var(--font-mono); }
        .vv.money  { font-family: var(--font-mono); color: var(--emerald); font-weight: 600; }
        .vv.danger { color: var(--red); font-family: var(--font-mono); }
        .vv.amber  { color: var(--amber); font-family: var(--font-mono); font-weight: 600; }
        .view-divider { border: none; border-top: 1px solid var(--border); margin: 1rem 0; }

        /* ── TOAST ── */
        #toastContainer { position: fixed; top: 20px; right: 20px; z-index: 9999; display: flex; flex-direction: column; gap: 10px; pointer-events: none; }
        .toast { background: var(--bg-surface); border: 1px solid var(--border); color: #fff; padding: 0.9rem 1.25rem; border-radius: var(--radius-md); box-shadow: 0 10px 30px rgba(0,0,0,0.6); font-size: 0.875rem; font-weight: 600; animation: slideIn 0.3s ease-out; display: flex; align-items: center; gap: 8px; pointer-events: auto; }
        @keyframes slideIn { from { transform: translateX(110%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }

        /* ── SCROLLBAR ── */
        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: var(--text-muted); border-radius: 4px; }

        /* ── EMPTY STATE ── */
        .empty-state { text-align: center; padding: 4rem 2rem; color: var(--text-muted); }
        .empty-state i { font-size: 2.8rem; opacity: 0.25; display: block; margin-bottom: 1rem; }

        /* ── RESPONSIVE ── */
        @media (max-width: 768px) {
            .container { padding: 1.25rem 1rem; }
            .page-header { flex-direction: column; align-items: flex-start; }
            .header-actions { width: 100%; flex-direction: column; }
            .header-actions .btn { width: 100%; justify-content: center; }
            .form-row, .form-row-3 { grid-template-columns: 1fr; gap: 0; }
            .view-grid { grid-template-columns: 1fr; }
            /* Card-style mobile table */
            .table-wrap { overflow: visible; background: transparent; border: none; }
            .table { display: block; min-width: unset; }
            .table thead { display: none; }
            .table tbody, .table tr, .table td { display: block; width: 100%; }
            .table tr { background: var(--bg-surface); border: 1px solid var(--border); border-radius: var(--radius-xl); margin-bottom: 1rem; padding: 1.25rem; box-shadow: var(--shadow-md); }
            .table td { display: flex; justify-content: space-between; align-items: center; padding: 0.55rem 0; border-bottom: 1px dashed rgba(255,255,255,0.05); text-align: right; }
            .table td:last-child { border-bottom: none; justify-content: flex-end; padding-top: 0.9rem; gap: 8px; }
            .table td::before { content: attr(data-label); font-weight: 700; color: var(--text-muted); font-size: 0.72rem; text-transform: uppercase; text-align: left; margin-right: 1rem; flex-shrink: 0; }
            .dynamic-table { display: block; overflow-x: auto; }
        }
    </style>
</head>
<body>

<div id="toastContainer"></div>

<div class="container">
    <!-- TOP BAR -->
    <div class="top-bar">
        <a href="purchase_dashboard.php" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back to Purchases
        </a>
        <span class="page-badge"><i class="fa-solid fa-wheat-awn"></i> Nutrition Inventory</span>
    </div>

    <!-- PAGE HEADER -->
    <div class="page-header">
        <div class="header-info">
            <h1>Feed <span>Purchases</span></h1>
            <p>Log incoming animal feed and track inventory before distribution.</p>
        </div>
        <div class="header-actions">
            <button class="btn btn-ghost" onclick="downloadSampleCSV()">
                <i class="fa-solid fa-download"></i> Sample CSV
            </button>
            <button class="btn btn-ghost" onclick="document.getElementById('csvUpload').click()">
                <i class="fa-solid fa-file-import"></i> Import CSV
            </button>
            <input type="file" id="csvUpload" accept=".csv" style="display:none;" onchange="uploadCSV(event)">
            <button class="btn btn-success" onclick="openConfirmAllModal()">
                <i class="fa-solid fa-check-double"></i> Confirm All Pending
            </button>
            <button class="btn btn-primary" onclick="openAddModal()">
                <i class="fa-solid fa-plus"></i> Log Feed Purchase
            </button>
        </div>
    </div>

    <!-- SEARCH -->
    <div class="search-container">
        <i class="fa-solid fa-magnifying-glass search-icon"></i>
        <input type="text" class="search-input" id="searchInput"
               placeholder="Search by feed type, supplier, reference…" onkeyup="filterTable()">
    </div>

    <!-- TABLE -->
    <div class="table-card">
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Ref No</th>
                        <th>Supplier</th>
                        <th>Feed Type</th>
                        <th>Net Vol/Unit</th>
                        <th>Total Units</th>
                        <th>Unit Measure</th>
                        <th>Cost/Unit</th>
                        <th>Total Cost</th>
                        <th>Category</th>
                        <th>Purchase Date</th>
                        <th>Expiry Date</th>
                        <th style="text-align:center; width:130px;">Status</th>
                        <th style="text-align:center; width:110px;">Actions</th>
                    </tr>
                </thead>
                <tbody id="item-table">
                    <?php if (empty($items_data)): ?>
                        <tr>
                            <td colspan="13">
                                <div class="empty-state">
                                    <i class="fa-solid fa-folder-open"></i>
                                    No feed purchases recorded in this location.
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($items_data as $item):
                            $status      = isset($item['STATUS']) ? (int)$item['STATUS'] : 0;
                            $isConfirmed = ($status === 1);
                            $totalCost   = $item['TOTAL_COST'] ?? ($item['QUANTITY'] * $item['UNIT_COST']);
                        ?>
                        <tr data-item-id="<?= $item['ITEM_ID'] ?>"
                            data-item-name="<?= htmlspecialchars($item['ITEM_NAME']) ?>"
                            data-item-desc="<?= htmlspecialchars($item['ITEM_DESCRIPTION'] ?? '') ?>"
                            data-quantity="<?= $item['QUANTITY'] ?? '0' ?>"
                            data-net-weight="<?= $item['ITEM_NET_WEIGHT'] ?? '0' ?>"
                            data-unit-id="<?= $item['UNIT_ID'] ?>"
                            data-unit-name="<?= htmlspecialchars($item['UNIT_NAME'] ?? '') ?>"
                            data-unit-cost="<?= $item['UNIT_COST'] ?>"
                            data-item-category="<?= $item['ITEM_CATEGORY'] ?>"
                            data-purchase-date="<?= htmlspecialchars($item['DATE_OF_PURCHASE'] ?? '') ?>"
                            data-purchase-date-fmt="<?= htmlspecialchars($item['DATE_OF_PURCHASE_FMT'] ?? '') ?>"
                            data-expiration-date="<?= htmlspecialchars($item['EXPIRATION_DATE'] ?? '') ?>"
                            data-expiration-date-fmt="<?= htmlspecialchars($item['EXPIRATION_DATE_FMT'] ?? '') ?>"
                            data-supplier="<?= htmlspecialchars($item['SUPPLIER'] ?? '') ?>"
                            data-reference-no="<?= htmlspecialchars($item['REFERENCE_NO'] ?? '') ?>"
                            data-location-id="<?= $item['LOCATION_ID'] ?? '' ?>"
                            data-building-id="<?= $item['BUILDING_ID'] ?? '' ?>"
                            data-pen-id="<?= $item['PEN_ID'] ?? '' ?>"
                            data-created-at="<?= htmlspecialchars($item['CREATED_AT_FMT'] ?? '') ?>">

                            <td data-label="Ref No">
                                <div class="ref-no"><?= !empty($item['REFERENCE_NO']) ? htmlspecialchars($item['REFERENCE_NO']) : '—' ?></div>
                            </td>
                            <td data-label="Supplier">
                                <div class="supplier-name"><?= !empty($item['SUPPLIER']) ? htmlspecialchars($item['SUPPLIER']) : 'General Supplier' ?></div>
                            </td>
                            <td data-label="Feed Type">
                                <div class="item-name"><?= htmlspecialchars($item['ITEM_NAME']) ?></div>
                            </td>
                            <td data-label="Net Vol/Unit">
                                <div class="val-mono" style="color:var(--amber);"><?= number_format($item['ITEM_NET_WEIGHT'] ?? 0, 2) ?> kg</div>
                            </td>
                            <td data-label="Total Units">
                                <div class="val-mono"><?= number_format($item['QUANTITY'] ?? 0, 2) ?></div>
                            </td>
                            <td data-label="Unit Measure">
                                <div><?= htmlspecialchars($item['UNIT_NAME']) ?></div>
                            </td>
                            <td data-label="Cost/Unit">
                                <div class="val-mono" style="color:var(--text-primary);">₱<?= number_format($item['UNIT_COST'], 2) ?></div>
                            </td>
                            <td data-label="Total Cost">
                                <div class="val-money">₱<?= number_format($totalCost, 2) ?></div>
                            </td>
                            <td data-label="Category">
                                <span class="category-badge <?= $categoryClasses[$item['ITEM_CATEGORY']] ?>">
                                    <?= $categoryLabels[$item['ITEM_CATEGORY']] ?>
                                </span>
                            </td>
                            <td data-label="Purchase Date">
                                <div class="val-mono"><?= htmlspecialchars($item['DATE_OF_PURCHASE_FMT'] ?? 'N/A') ?></div>
                            </td>
                            <td data-label="Expiry Date">
                                <div class="val-mono" style="color:var(--red);"><?= htmlspecialchars($item['EXPIRATION_DATE_FMT'] ?? 'N/A') ?></div>
                            </td>
                            <td data-label="Status" style="text-align:center;">
                                <?php if (!$isConfirmed): ?>
                                    <button class="btn-confirm-row" onclick="openConfirmModal(this)">
                                        <i class="fa-solid fa-circle-dot"></i> Pending
                                    </button>
                                <?php else: ?>
                                    <div class="status-badge badge-confirmed">
                                        <i class="fa-solid fa-check"></i> Verified
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td data-label="Actions">
                                <div class="actions">
                                    <button class="action-btn view" onclick="viewItem(this)" title="View Details">
                                        <i class="fa-regular fa-eye"></i>
                                    </button>
                                    <?php if (!$isConfirmed): ?>
                                        <button class="action-btn edit" onclick="editItem(this)" title="Edit">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </button>
                                        <button class="action-btn delete" onclick="deleteItem(this)" title="Delete">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </button>
                                    <?php else: ?>
                                        <span class="locked-icon" title="Locked — record is verified">
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
            <div id="empty-search-state" style="display:none;" class="empty-state">
                <i class="fa-solid fa-magnifying-glass"></i>
                No records match your search.
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════
     HIDDEN FORMS
═══════════════════════════════════════ -->
<form id="deleteForm" method="POST" action="../process/deleteFeedAndFeedingSupplies.php" style="display:none;">
    <input type="hidden" id="delete_item_id" name="item_id">
</form>
<form id="confirmForm" method="POST" style="display:none;">
    <input type="hidden" id="confirm_item_id" name="item_id">
</form>

<!-- ═══════════════════════════════════════
     CUSTOM ALERT MODAL
═══════════════════════════════════════ -->
<div id="customAlertModal" class="modal" style="z-index:2000;">
    <div class="modal-content narrow">
        <div class="modal-body confirm-content" style="padding-bottom:1.5rem;">
            <span class="confirm-icon" id="customAlertIcon"><i class="fa-solid fa-circle-xmark"></i></span>
            <h2 id="customAlertTitle">Error</h2>
            <p id="customAlertMessage" style="white-space:pre-line;">Something went wrong.</p>
            <div id="customAlertDetails"
                 style="display:none; text-align:left; background:rgba(0,0,0,0.3); padding:12px; border-radius:8px;
                        border:1px solid var(--border); max-height:220px; overflow-y:auto; color:var(--red);
                        font-size:0.82rem; font-family:var(--font-mono); line-height:1.6; margin-top:12px;"></div>
        </div>
        <div class="modal-footer" style="justify-content:center; border-top:none; background:transparent; padding-top:0; padding-bottom:1.75rem;">
            <button type="button" class="btn btn-ghost" style="min-width:140px;" onclick="closeCustomAlert()">Close</button>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════
     CUSTOM CONFIRM MODAL
═══════════════════════════════════════ -->
<div id="customConfirmModal" class="modal" style="z-index:2000;">
    <div class="modal-content narrow">
        <div class="modal-body confirm-content" style="padding-bottom:1.5rem;">
            <span class="confirm-icon" id="customConfirmIcon" style="color:var(--amber);">
                <i class="fa-solid fa-triangle-exclamation"></i>
            </span>
            <h2 id="customConfirmTitle">Confirm Action</h2>
            <p id="customConfirmMessage">Are you sure?</p>
            <div id="customConfirmWarning" class="warning-text" style="display:none;"></div>
        </div>
        <div class="modal-footer" style="justify-content:center; border-top:none; background:transparent; padding-top:0; padding-bottom:1.75rem; gap:10px;">
            <button type="button" class="btn btn-ghost" onclick="closeCustomConfirm()">Cancel</button>
            <button type="button" class="btn btn-primary" id="customConfirmBtn">Confirm</button>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════
     ADD MODAL
═══════════════════════════════════════ -->
<div id="addModal" class="modal">
    <div class="modal-content wide">
        <div class="modal-header">
            <h2><i class="fa-solid fa-wheat-awn" style="color:var(--amber);"></i> Log Feed Purchase</h2>
            <button class="action-btn" onclick="closeAddModal()"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body">
            <div id="add-modal-alert" class="alert"></div>
            <form id="add-batch-form" onsubmit="saveAddBatch(event)">

                <div class="section-label"><i class="fa-solid fa-file-invoice"></i> Invoice Details</div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Delivery / Purchase Date <span class="req">*</span></label>
                        <input type="text" id="batch-purchase-date" name="date_of_purchase" class="form-control" required>
                    </div>
                    <div class="form-group autocomplete-wrapper">
                        <label class="form-label">Supplier</label>
                        <input type="text" id="batch-supplier" name="supplier" class="form-control" placeholder="e.g., Agrivet Corp" autocomplete="off">
                        <div class="autocomplete-list" id="batch-supplier-list"></div>
                    </div>
                </div>
                <div class="form-row" style="margin-bottom:0;">
                    <div class="form-group">
                        <label class="form-label">Location <span class="req">*</span></label>
                        <select id="batch-location" name="location_id" class="form-select"
                                onchange="filterBuildings('batch')"
                                <?= ($USER_LOCATION_ != 1000) ? 'disabled' : '' ?> required>
                            <?php if ($USER_LOCATION_ == 1000): ?>
                                <option value="">Select Location</option>
                            <?php endif; ?>
                            <?php foreach ($locations as $loc): ?>
                                <option value="<?= $loc['LOCATION_ID'] ?>"
                                    <?= ($USER_LOCATION_ != 1000 && $loc['LOCATION_ID'] == $USER_LOCATION_) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($loc['LOCATION_NAME']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($USER_LOCATION_ != 1000): ?>
                            <input type="hidden" name="location_id" value="<?= $USER_LOCATION_ ?>">
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Reference No. (OR / Invoice)</label>
                        <input type="text" id="batch-reference-no" name="reference_no" class="form-control" placeholder="e.g., OR-12345">
                    </div>
                </div>

                <div class="form-row-3" style="margin-bottom:1rem;">
                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label">Building</label>
                        <select id="batch-building" name="building_id" class="form-select" onchange="filterPens('batch')" disabled>
                            <option value="">Select Location First</option>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label">Pen</label>
                        <select id="batch-pen" name="pen_id" class="form-select" disabled>
                            <option value="">Select Building First</option>
                        </select>
                    </div>
                    <div></div><!-- spacer -->
                </div>

                <hr class="section-divider">
                <div class="section-label" style="justify-content:space-between;">
                    <span><i class="fa-solid fa-list"></i> Feed Items</span>
                    <button type="button" class="add-row-btn" onclick="addFeedRow()">
                        <i class="fa-solid fa-plus"></i> Add Row
                    </button>
                </div>

                <div style="overflow-x:auto;">
                    <table class="dynamic-table">
                        <thead>
                            <tr>
                                <th style="min-width:160px;">Feed Name <span style="color:var(--red);">*</span></th>
                                <th style="min-width:120px;">Category <span style="color:var(--red);">*</span></th>
                                <th style="min-width:90px;">Net Weight(kg) per Sack</th>
                                <th style="min-width:100px;">Unit <span style="color:var(--red);">*</span></th>
                                <th style="min-width:75px;">Qty <span style="color:var(--red);">*</span></th>
                                <th style="min-width:95px;">Cost/Unit <span style="color:var(--red);">*</span></th>
                                <th style="min-width:130px;">Expiry Date</th>
                                <th style="width:38px;"></th>
                            </tr>
                        </thead>
                        <tbody id="dynamic-feed-body"></tbody>
                    </table>
                </div>

            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-ghost" onclick="closeAddModal()">Cancel</button>
            <button type="submit" form="add-batch-form" class="btn btn-primary" id="btn-save-batch">
                <i class="fa-solid fa-floppy-disk"></i> Save Batch Purchase
            </button>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════
     EDIT MODAL
═══════════════════════════════════════ -->
<div id="editModal" class="modal">
    <div class="modal-content" style="max-width:700px;">
        <div class="modal-header">
            <h2><i class="fa-solid fa-pen-to-square" style="color:var(--amber);"></i> Edit Feed Purchase</h2>
            <button class="action-btn" onclick="closeEditModal()"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body">
            <div id="edit-modal-alert" class="alert"></div>
            <form id="edit-item-form" onsubmit="saveEditItem(event)">
                <input type="hidden" id="edit-item-id"   name="item_id">
                <input type="hidden" name="item_type_id" value="<?= $ITEM_TYPE_ID ?>">

                <div class="section-label"><i class="fa-solid fa-wheat-awn"></i> Feed Information</div>

                <div class="form-group autocomplete-wrapper">
                    <label class="form-label">Feed Name <span class="req">*</span></label>
                    <input type="text" id="edit-item-name" name="item_name" class="form-control" required maxlength="300" autocomplete="off">
                    <div class="autocomplete-list" id="edit-item-list"></div>
                </div>

                <div class="form-row">
                    <div class="form-group autocomplete-wrapper">
                        <label class="form-label">Supplier</label>
                        <input type="text" id="edit-supplier" name="supplier" class="form-control" autocomplete="off">
                        <div class="autocomplete-list" id="edit-supplier-list"></div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Reference No.</label>
                        <input type="text" id="edit-reference-no" name="reference_no" class="form-control">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Net Weight(kg) per sack</label>
                        <input type="number" id="edit-net-weight" name="item_net_weight" class="form-control" step="0.01" min="0" placeholder="0.00">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Base Unit <span class="req">*</span></label>
                        <select id="edit-unit" name="unit_id" class="form-select" required>
                            <?php foreach ($units as $unit): ?>
                                <option value="<?= $unit['UNIT_ID'] ?>"><?= htmlspecialchars($unit['UNIT_NAME']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Quantity <span class="req">*</span></label>
                        <input type="number" id="edit-item-quantity" name="item_quantity" class="form-control" step="0.01" min="0" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Item Category <span class="req">*</span></label>
                        <select id="edit-item-category" name="item_category" class="form-select" required>
                            <option value="1">Consumable</option>
                            <option value="0">Non-Consumable</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Cost(₱) Per Sack <span class="req">*</span></label>
                        <input type="number" id="edit-unit-cost" name="unit_cost" class="form-control" step="0.01" min="0" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Description</label>
                        <textarea id="edit-item-desc" name="item_description" class="form-control" rows="2" maxlength="500"></textarea>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Purchase Date <span class="req">*</span></label>
                        <input type="text" id="edit-purchase-date" name="date_of_purchase" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Expiry Date</label>
                        <input type="text" id="edit-expiration-date" name="expiration_date" class="form-control">
                    </div>
                </div>

                <hr class="section-divider">
                <div class="section-label"><i class="fa-solid fa-location-dot"></i> Location</div>
                <div class="form-row-3">
                    <div class="form-group">
                        <label class="form-label">Location <span class="req">*</span></label>
                        <select id="edit-location" name="location_id" class="form-select"
                                onchange="filterBuildings('edit')" required
                                <?= ($USER_LOCATION_ != 1000) ? 'disabled' : '' ?>>
                            <?php if ($USER_LOCATION_ == 1000): ?>
                                <option value="">Select Location</option>
                            <?php endif; ?>
                            <?php foreach ($locations as $loc): ?>
                                <option value="<?= $loc['LOCATION_ID'] ?>"
                                    <?= ($USER_LOCATION_ != 1000 && $loc['LOCATION_ID'] == $USER_LOCATION_) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($loc['LOCATION_NAME']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($USER_LOCATION_ != 1000): ?>
                            <input type="hidden" name="location_id" value="<?= $USER_LOCATION_ ?>">
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Building</label>
                        <select id="edit-building" name="building_id" class="form-select" onchange="filterPens('edit')" disabled>
                            <option value="">Select Location First</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Pen</label>
                        <select id="edit-pen" name="pen_id" class="form-select" disabled>
                            <option value="">Select Building First</option>
                        </select>
                    </div>
                </div>

            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-ghost" onclick="closeEditModal()">Cancel</button>
            <button type="submit" form="edit-item-form" class="btn btn-primary" id="btn-save-edit">
                <i class="fa-solid fa-floppy-disk"></i> Update Purchase
            </button>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════
     VIEW MODAL
═══════════════════════════════════════ -->
<div id="view-modal" class="modal">
    <div class="modal-content" style="max-width:540px;">
        <div class="modal-header">
            <h2><i class="fa-regular fa-eye" style="color:var(--blue);"></i> Purchase Details</h2>
            <button class="action-btn" onclick="closeViewModal()"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body" id="view-modal-body"></div>
        <div class="modal-footer">
            <button type="button" class="btn btn-ghost" onclick="closeViewModal()" style="width:100%;">Close</button>
        </div>
    </div>
</div>

<script>
    const allBuildings   = <?= json_encode($buildings_raw) ?>;
    const allPens        = <?= json_encode($pens_raw) ?>;
    const availableUnits = <?= json_encode($units) ?>;
    const USER_LOCATION  = <?= json_encode($USER_LOCATION_) ?>;

    let fpEditPurchase, fpEditExpiry, fpBatchDate;

    document.addEventListener('DOMContentLoaded', () => {
        fpBatchDate    = flatpickr('#batch-purchase-date',  { dateFormat: 'Y-m-d', altInput: true, altFormat: 'M j, Y', defaultDate: new Date() });
        fpEditPurchase = flatpickr('#edit-purchase-date',   { dateFormat: 'Y-m-d', altInput: true, altFormat: 'M j, Y' });
        fpEditExpiry   = flatpickr('#edit-expiration-date', { dateFormat: 'Y-m-d', altInput: true, altFormat: 'M j, Y' });

        attachAutocomplete(document.getElementById('batch-supplier'), '../process/searchSuppliers.php?term=');
        attachAutocomplete(document.getElementById('edit-supplier'),  '../process/searchSuppliers.php?term=');
        attachAutocomplete(document.getElementById('edit-item-name'), '../process/searchFeedsAndFeedingSupplies.php?term=');

        // Pre-trigger location for fixed-location users in Add modal
        if (USER_LOCATION != 1000) {
            const batchLoc = document.getElementById('batch-location');
            if (batchLoc) { batchLoc.value = USER_LOCATION; filterBuildings('batch'); }
        }

        // Click-outside to close modals
        // ['addModal','editModal','view-modal','customAlertModal','customConfirmModal'].forEach(id => {
        //     const el = document.getElementById(id);
        //     if (el) el.addEventListener('click', e => { if (e.target === el) el.classList.remove('show'); });
        // });

        // Wire confirm button
        document.getElementById('customConfirmBtn').addEventListener('click', () => {
            if (confirmCallback) confirmCallback();
            closeCustomConfirm();
        });
    });

    /* ─── TOAST ─────────────────────────────── */
    function showToast(msg, type = 'success', duration = 3500) {
        const t = document.createElement('div');
        t.className = 'toast';
        const colors = { success: 'var(--emerald)', error: 'var(--red)', loading: 'var(--amber)' };
        t.style.borderLeft = `4px solid ${colors[type] || colors.success}`;
        const icons  = { success: 'fa-check', error: 'fa-xmark', loading: 'fa-spinner fa-spin' };
        const toastId = 'toast_' + Math.random().toString(36).substr(2, 9);
        t.id = toastId;
        t.innerHTML = `<i class="fa-solid ${icons[type] || icons.success}"></i> <span>${msg}</span>`;
        document.getElementById('toastContainer').appendChild(t);
        if (duration > 0) setTimeout(() => t.remove(), duration);
        return toastId;
    }
    function removeToast(id) { const t = document.getElementById(id); if (t) t.remove(); }

    /* ─── CUSTOM ALERT MODAL ─────────────────── */
    function showCustomAlert(title, message, type = 'error', details = []) {
        const iconEl = document.getElementById('customAlertIcon');
        document.getElementById('customAlertTitle').textContent   = title;
        document.getElementById('customAlertMessage').textContent = message;
        const cfg = {
            error:   { icon: 'fa-circle-xmark',  color: 'var(--red)' },
            success: { icon: 'fa-circle-check',  color: 'var(--emerald)' },
            info:    { icon: 'fa-circle-info',   color: 'var(--blue)' }
        };
        const c = cfg[type] || cfg.error;
        iconEl.innerHTML = `<i class="fa-solid ${c.icon}"></i>`;
        iconEl.style.color = c.color;
        const detailsEl = document.getElementById('customAlertDetails');
        if (details && details.length) {
            detailsEl.style.display = 'block';
            detailsEl.innerHTML = details.map(e => `<div>• ${e}</div>`).join('');
        } else {
            detailsEl.style.display = 'none';
            detailsEl.innerHTML = '';
        }
        document.getElementById('customAlertModal').classList.add('show');
    }
    function closeCustomAlert() { document.getElementById('customAlertModal').classList.remove('show'); }

    /* ─── CUSTOM CONFIRM MODAL ───────────────── */
    let confirmCallback = null;
    function showCustomConfirm(title, message, warningHtml, btnText, btnClass, callback) {
        document.getElementById('customConfirmTitle').textContent   = title;
        document.getElementById('customConfirmMessage').textContent = message;
        const warnEl = document.getElementById('customConfirmWarning');
        if (warningHtml) { warnEl.innerHTML = warningHtml; warnEl.style.display = 'block'; }
        else { warnEl.style.display = 'none'; }
        const btn = document.getElementById('customConfirmBtn');
        btn.textContent = btnText;
        btn.className   = `btn ${btnClass}`;
        confirmCallback = callback;
        document.getElementById('customConfirmModal').classList.add('show');
    }
    function closeCustomConfirm() {
        document.getElementById('customConfirmModal').classList.remove('show');
        confirmCallback = null;
    }

    /* ─── INLINE ALERT ───────────────────────── */
    function showAlert(el, type, msg) {
        el.textContent = msg;
        el.className = `alert ${type}`;
    }
    function hideAlert(el) { el.className = 'alert'; }

    /* ─── CSV ────────────────────────────────── */
    function downloadSampleCSV() {
        const csv  = "LOCATION,REF NO,SUPPLIER,DELIVERY DATE,FEED TYPE,NET WEIGHT,QTY,PRICE\nLocation 1,DR125443,RMD Agrivet,2025-06-11,Vienovo Grower,50,10,1940.00";
        const link = document.createElement('a');
        link.href     = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8;' }));
        link.download = 'feed_purchases_sample.csv';
        link.style.display = 'none';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    function uploadCSV(event) {
        const file = event.target.files[0];
        if (!file) return;
        showCustomConfirm(
            'Confirm CSV Import',
            `Import records from "${file.name}"?`,
            null, 'Yes, Import', 'btn-primary',
            () => {
                const fd = new FormData();
                fd.append('csv_file', file);
                const tid = showToast('Uploading and validating CSV…', 'loading', 0);
                fetch('../process/addImportedFeedPurchase.php', { method: 'POST', body: fd })
                    .then(r => r.json())
                    .then(data => {
                        removeToast(tid);
                        if (data.success) {
                            showCustomAlert('Import Successful', data.message, 'success');
                            setTimeout(() => location.reload(), 2000);
                        } else {
                            showCustomAlert('Import Failed', data.message, 'error', data.errors || []);
                        }
                    })
                    .catch(() => { removeToast(tid); showCustomAlert('System Error', 'A network error occurred during import.', 'error'); })
                    .finally(() => { event.target.value = ''; });
            }
        );
    }

    /* ─── CONFIRM SINGLE ─────────────────────── */
    function openConfirmModal(btn) {
        const row = btn.closest('tr');
        document.getElementById('confirm_item_id').value = row.dataset.itemId;
        showCustomConfirm(
            'Verify Acquisition?',
            `Confirm intake of ${parseFloat(row.dataset.quantity).toLocaleString()}× ${row.dataset.itemName}?`,
            `<i class="fa-solid fa-triangle-exclamation"></i> <strong>Heads up:</strong> Once confirmed, this record is locked and cannot be edited or deleted.`,
            'Yes, Lock Record', 'btn-primary',
            () => { submitConfirmation(); }
        );
    }
    function submitConfirmation() {
        const tid = showToast('Confirming…', 'loading', 0);
        fetch('../purchase_confirmations/confirmFeedAndFeedingSupplies.php', {
            method: 'POST', body: new FormData(document.getElementById('confirmForm'))
        })
        .then(r => r.json())
        .then(data => {
            removeToast(tid);
            if (data.success) { showToast(data.message, 'success'); setTimeout(() => location.reload(), 1200); }
            else { showCustomAlert('Confirmation Error', data.message, 'error'); }
        })
        .catch(() => { removeToast(tid); showCustomAlert('System Error', 'An error occurred while confirming.', 'error'); });
    }

    /* ─── CONFIRM ALL ────────────────────────── */
    function openConfirmAllModal() {
        showCustomConfirm(
            'Commit All Pending?',
            'This will verify and lock ALL currently pending feed purchases in this location.',
            `<i class="fa-solid fa-triangle-exclamation"></i> <strong>Irreversible:</strong> Please audit all pending items before proceeding.`,
            'Commit All Now', 'btn-success',
            () => { submitConfirmAll(); }
        );
    }
    function submitConfirmAll() {
        const tid = showToast('Processing all records…', 'loading', 0);
        fetch('../purchase_confirmations/confirmAllFeedAndFeedingSupplies.php', { method: 'POST' })
        .then(r => r.json())
        .then(data => {
            removeToast(tid);
            if (data.success) { showToast(data.message, 'success'); setTimeout(() => location.reload(), 1200); }
            else { showCustomAlert('Error', data.message, 'error'); }
        })
        .catch(() => { removeToast(tid); showCustomAlert('System Error', 'An error occurred.', 'error'); });
    }

    /* ─── DELETE ─────────────────────────────── */
    function deleteItem(btn) {
        const row = btn.closest('tr');
        showCustomConfirm(
            'Delete Record',
            `Delete "${row.dataset.itemName}"? This action cannot be undone.`,
            null, 'Yes, Delete', 'btn-danger',
            () => {
                document.getElementById('delete_item_id').value = row.dataset.itemId;
                document.getElementById('deleteForm').submit();
            }
        );
    }

    /* ─── ADD MODAL ──────────────────────────── */
    function openAddModal() {
        document.getElementById('add-batch-form').reset();
        document.getElementById('dynamic-feed-body').innerHTML = '';
        hideAlert(document.getElementById('add-modal-alert'));
        fpBatchDate.setDate(new Date());
        if (USER_LOCATION != 1000) {
            document.getElementById('batch-location').value = USER_LOCATION;
            filterBuildings('batch');
        }
        addFeedRow();
        document.getElementById('addModal').classList.add('show');
    }
    function closeAddModal() { document.getElementById('addModal').classList.remove('show'); }

    function addFeedRow() {
        const tbody   = document.getElementById('dynamic-feed-body');
        const tr      = document.createElement('tr');
        const unitOpts = availableUnits.map(u => `<option value="${u.UNIT_ID}">${u.UNIT_NAME}</option>`).join('');
        tr.innerHTML = `
            <td data-label="Feed Name">
                <div class="autocomplete-wrapper">
                    <input type="text" class="form-control row-item-name" placeholder="Feed name" required autocomplete="off">
                    <div class="autocomplete-list"></div>
                </div>
            </td>
            <td data-label="Category">
                <select class="form-select row-category" required>
                    <option value="1">Consumable</option>
                    <option value="0">Non-Consumable</option>
                </select>
            </td>
            <td data-label="Net Weight(kg) Per Sack">
                <input type="number" class="form-control row-net-weight" placeholder="0.00" step="0.01" min="0">
            </td>
            <td data-label="Unit">
                <select class="form-select row-unit" required>${unitOpts}</select>
            </td>
            <td data-label="Qty">
                <input type="number" class="form-control row-qty" placeholder="0" step="0.01" min="0" required>
            </td>
            <td data-label="Cost/Unit">
                <input type="number" class="form-control row-cost" placeholder="0.00" step="0.01" min="0" required>
            </td>
            <td data-label="Expiry Date">
                <input type="text" class="form-control row-exp" placeholder="Expiry date">
            </td>
            <td style="vertical-align:middle; text-align:center;">
                <button type="button" class="del-row-btn" onclick="this.closest('tr').remove()" title="Remove row">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </td>`;
        tbody.appendChild(tr);
        flatpickr(tr.querySelector('.row-exp'), { dateFormat: 'Y-m-d', altInput: true, altFormat: 'M j, Y' });
        attachAutocomplete(tr.querySelector('.row-item-name'), '../process/searchFeedsAndFeedingSupplies.php?term=');
    }

    function saveAddBatch(e) {
        e.preventDefault();
        const alertBox = document.getElementById('add-modal-alert');
        const rows     = document.querySelectorAll('#dynamic-feed-body tr');
        if (!rows.length) { showAlert(alertBox, 'error', 'Please add at least one feed item row.'); return; }

        const btn   = document.getElementById('btn-save-batch');
        const ogHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving…';

        const payload = {
            supplier:         document.getElementById('batch-supplier').value,
            reference_no:     document.getElementById('batch-reference-no').value,
            date_of_purchase: document.getElementById('batch-purchase-date').value,
            location_id:      document.getElementById('batch-location').value,
            building_id:      document.getElementById('batch-building').value,
            pen_id:           document.getElementById('batch-pen').value,
            items: []
        };
        rows.forEach(tr => {
            payload.items.push({
                item_name:       tr.querySelector('.row-item-name').value,
                category:        tr.querySelector('.row-category').value,
                net_weight:      tr.querySelector('.row-net-weight').value,
                unit_id:         tr.querySelector('.row-unit').value,
                quantity:        tr.querySelector('.row-qty').value,
                unit_cost:       tr.querySelector('.row-cost').value,
                expiration_date: tr.querySelector('.row-exp')._flatpickr
                                   ? tr.querySelector('.row-exp')._flatpickr.input.value
                                   : tr.querySelector('.row-exp').value
            });
        });

        fetch('../process/addFeedAndFeedingSupplies.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showAlert(alertBox, 'success', data.message);
                setTimeout(() => location.reload(), 1400);
            } else {
                showAlert(alertBox, 'error', data.message);
                btn.disabled = false; btn.innerHTML = ogHtml;
            }
        })
        .catch(() => {
            showAlert(alertBox, 'error', 'Network error. Please try again.');
            btn.disabled = false; btn.innerHTML = ogHtml;
        });
    }

    /* ─── EDIT MODAL ─────────────────────────── */
    function editItem(btn) {
        const d = btn.closest('tr').dataset;
        document.getElementById('edit-item-id').value        = d.itemId;
        document.getElementById('edit-item-name').value      = d.itemName;
        document.getElementById('edit-item-desc').value      = d.itemDesc      || '';
        document.getElementById('edit-unit').value           = d.unitId        || '';
        document.getElementById('edit-unit-cost').value      = d.unitCost      || '';
        document.getElementById('edit-item-category').value  = d.itemCategory  || '1';
        document.getElementById('edit-net-weight').value     = d.netWeight     || '';
        document.getElementById('edit-item-quantity').value  = d.quantity      || '';
        document.getElementById('edit-supplier').value       = d.supplier      || '';
        document.getElementById('edit-reference-no').value   = d.referenceNo   || '';
        fpEditPurchase.setDate(d.purchaseDate     || '');
        fpEditExpiry.setDate(d.expirationDate     || '');
        document.getElementById('edit-location').value       = d.locationId    || '';
        filterBuildings('edit');
        if (d.buildingId) {
            setTimeout(() => {
                document.getElementById('edit-building').value = d.buildingId;
                filterPens('edit');
                if (d.penId) setTimeout(() => { document.getElementById('edit-pen').value = d.penId; }, 0);
            }, 0);
        }
        hideAlert(document.getElementById('edit-modal-alert'));
        document.getElementById('editModal').classList.add('show');
    }
    function closeEditModal() { document.getElementById('editModal').classList.remove('show'); }

    function saveEditItem(e) {
        e.preventDefault();
        const alertBox = document.getElementById('edit-modal-alert');
        const btn      = document.getElementById('btn-save-edit');
        const ogHtml   = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Updating…';
        fetch('../process/editFeedAndFeedingSupplies.php', {
            method: 'POST', body: new FormData(document.getElementById('edit-item-form'))
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showAlert(alertBox, 'success', data.message);
                setTimeout(() => location.reload(), 1200);
            } else {
                showAlert(alertBox, 'error', data.message || 'Failed to update record.');
                btn.disabled = false; btn.innerHTML = ogHtml;
            }
        })
        .catch(() => {
            showAlert(alertBox, 'error', 'Network error. Please try again.');
            btn.disabled = false; btn.innerHTML = ogHtml;
        });
    }

    /* ─── VIEW MODAL ─────────────────────────── */
    function viewItem(btn) {
        const d      = btn.closest('tr').dataset;
        const catMap = { 0: 'Non-Consumable', 1: 'Consumable' };
        const fmtMoney = v => parseFloat(v || 0).toLocaleString('en-PH', { minimumFractionDigits: 2 });
        const totalCost = fmtMoney(parseFloat(d.unitCost || 0) * parseFloat(d.quantity || 0));

        document.getElementById('view-modal-body').innerHTML = `
            <div class="view-section">
                <div class="view-section-title"><i class="fa-solid fa-circle-info"></i> Basic Information</div>
                <div class="view-grid">
                    <div class="view-item">
                        <span class="vl">System ID</span>
                        <span class="vv mono">PCH-${String(d.itemId).padStart(5,'0')}</span>
                    </div>
                    <div class="view-item">
                        <span class="vl">Ref No</span>
                        <span class="vv mono">${d.referenceNo || '—'}</span>
                    </div>
                    <div class="view-item" style="grid-column:1/-1;">
                        <span class="vl">Feed Name</span>
                        <span class="vv" style="font-weight:700;">${d.itemName}</span>
                    </div>
                    <div class="view-item">
                        <span class="vl">Supplier</span>
                        <span class="vv">${d.supplier || 'General Supplier'}</span>
                    </div>
                    <div class="view-item">
                        <span class="vl">Category</span>
                        <span class="vv">${catMap[d.itemCategory] || '—'}</span>
                    </div>
                    <div class="view-item" style="grid-column:1/-1;">
                        <span class="vl">Description</span>
                        <span class="vv">${d.itemDesc || '—'}</span>
                    </div>
                </div>
            </div>
            <hr class="view-divider">
            <div class="view-section">
                <div class="view-section-title"><i class="fa-solid fa-receipt"></i> Purchase Details</div>
                <div class="view-grid">
                    <div class="view-item">
                        <span class="vl">Total Units</span>
                        <span class="vv mono">${parseFloat(d.quantity||0).toLocaleString()}</span>
                    </div>
                    <div class="view-item">
                        <span class="vl">Net Weight (kg) per Sack</span>
                        <span class="vv amber">${d.netWeight || '0'} kg</span>
                    </div>
                    <div class="view-item">
                        <span class="vl">Unit</span>
                        <span class="vv">${d.unitName || '—'}</span>
                    </div>
                    <div class="view-item">
                        <span class="vl">Cost Per Sack</span>
                        <span class="vv money">₱${fmtMoney(d.unitCost)}</span>
                    </div>
                    <div class="view-item">
                        <span class="vl">Total Cost</span>
                        <span class="vv money">₱${totalCost}</span>
                    </div>
                    <div class="view-item">
                        <span class="vl">Recorded On</span>
                        <span class="vv mono">${d.createdAt || '—'}</span>
                    </div>
                    <div class="view-item">
                        <span class="vl">Purchase Date</span>
                        <span class="vv mono">${d.purchaseDateFmt || 'N/A'}</span>
                    </div>
                    <div class="view-item">
                        <span class="vl">Expiry Date</span>
                        <span class="vv danger">${d.expirationDateFmt || 'N/A'}</span>
                    </div>
                </div>
            </div>`;
        document.getElementById('view-modal').classList.add('show');
    }
    function closeViewModal() { document.getElementById('view-modal').classList.remove('show'); }

    /* ─── SEARCH ─────────────────────────────── */
    function filterTable() {
        const term  = document.getElementById('searchInput').value.toLowerCase();
        const rows  = document.querySelectorAll('#item-table tr');
        let visible = 0;
        rows.forEach(r => {
            const show = r.textContent.toLowerCase().includes(term);
            r.style.display = show ? '' : 'none';
            if (show) visible++;
        });
        document.getElementById('empty-search-state').style.display =
            (!visible && term) ? 'block' : 'none';
    }

    /* ─── DROPDOWNS ──────────────────────────── */
    function filterBuildings(prefix) {
        const locId  = document.getElementById(`${prefix}-location`).value;
        const bldSel = document.getElementById(`${prefix}-building`);
        const penSel = document.getElementById(`${prefix}-pen`);
        bldSel.innerHTML = '<option value="">Select Building</option>';
        penSel.innerHTML = '<option value="">Select Building First</option>';
        penSel.disabled  = true;
        if (locId) {
            bldSel.disabled = false;
            allBuildings.filter(b => b.LOCATION_ID == locId)
                        .forEach(b => bldSel.appendChild(new Option(b.BUILDING_NAME, b.BUILDING_ID)));
        } else {
            bldSel.disabled = true;
        }
    }
    function filterPens(prefix) {
        const bldId  = document.getElementById(`${prefix}-building`).value;
        const penSel = document.getElementById(`${prefix}-pen`);
        penSel.innerHTML = '<option value="">Select Pen</option>';
        if (bldId) {
            penSel.disabled = false;
            allPens.filter(p => p.BUILDING_ID == bldId)
                   .forEach(p => penSel.appendChild(new Option(p.PEN_NAME, p.PEN_ID)));
        } else {
            penSel.disabled = true;
        }
    }

    /* ─── AUTOCOMPLETE ───────────────────────── */
    function attachAutocomplete(input, endpoint) {
        if (!input) return;
        const list  = input.nextElementSibling;
        let   timer = null;
        input.addEventListener('input', function () {
            const val = this.value.trim();
            clearTimeout(timer);
            if (val.length < 2) { list.classList.remove('show'); return; }
            list.innerHTML = '<div class="autocomplete-loading">Searching…</div>';
            list.classList.add('show');
            timer = setTimeout(() => {
                fetch(endpoint + encodeURIComponent(val))
                    .then(r => r.json())
                    .then(data => {
                        list.innerHTML = '';
                        if (!data.length) { list.innerHTML = '<div class="autocomplete-no-results">No matches found</div>'; return; }
                        data.forEach(item => {
                            const div = document.createElement('div');
                            div.className = 'autocomplete-item';
                            const rx = new RegExp(`(${val.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'gi');
                            div.innerHTML = item.replace(rx, '<strong>$1</strong>');
                            div.addEventListener('click', () => { input.value = item; list.classList.remove('show'); });
                            list.appendChild(div);
                        });
                    })
                    .catch(() => list.classList.remove('show'));
            }, 300);
        });
        document.addEventListener('click', e => {
            const wrapper = input.closest('.autocomplete-wrapper');
            if (wrapper && !wrapper.contains(e.target)) list.classList.remove('show');
        });
    }
</script>
</body>
</html>