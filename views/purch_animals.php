<?php
// views/purch_animals.php
error_reporting(0);
ini_set('display_errors', 0);
include '../config/Connection.php';

include '../security/checkAccess.php';
checkAccess('purchases');
$page="transactions";
include '../common/navbar.php';
include '../common/chat_support.php';
include '../functions/getUsersLocation.php';

// --- CONFIGURATION ---
$ANIMAL_ITEM_TYPE_ID = 13; 
// ---------------------

try {
    if (!isset($conn)) {
        throw new Exception("Database connection failed.");
    }

    if($USER_LOCATION_ != 1000) {
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
        $stmt->execute([':type_id' => $ANIMAL_ITEM_TYPE_ID, ':location_id' => $USER_LOCATION_]);
        $items_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $unit_query = "SELECT * FROM UNITS WHERE UPPER(UNIT_NAME) IN ('PCS', 'PIECES', 'PC', 'HEADS', 'HEAD') LIMIT 1";
        $stmt = $conn->prepare($unit_query);
        $stmt->execute();
        $default_unit_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $default_unit_id   = $default_unit_data[0]['UNIT_ID'] ?? '';
        $default_unit_name = $default_unit_data[0]['UNIT_NAME'] ?? 'Pcs';

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
        $stmt->execute([':type_id' => $ANIMAL_ITEM_TYPE_ID]);
        $items_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $unit_query = "SELECT * FROM UNITS WHERE UPPER(UNIT_NAME) IN ('PCS', 'PIECES', 'PC', 'HEADS', 'HEAD') LIMIT 1";
        $stmt = $conn->prepare($unit_query);
        $stmt->execute();
        $default_unit_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $default_unit_id   = $default_unit_data[0]['UNIT_ID'] ?? '';
        $default_unit_name = $default_unit_data[0]['UNIT_NAME'] ?? 'Pcs';

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
    $items_data    = [];
    $locations     = [];
    $buildings_raw = [];
    $pens_raw      = [];
    $default_unit_id   = '';
    $default_unit_name = '';
    echo "<script>console.error('Database Error: " . addslashes($e->getMessage()) . "');</script>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Animal Purchases Management | FarmPro</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" type="text/css" href="https://npmcdn.com/flatpickr/dist/themes/dark.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    <style>
        :root {
            --bg-base:        #080f1a;
            --bg-surface:     #0d1829;
            --bg-elevated:    #111f35;
            --bg-hover:       #162540;
            --border:         rgba(255,255,255,0.07);
            --border-active:  rgba(56,189,248,0.5);
            --blue:           #38bdf8;
            --blue-dim:       rgba(56,189,248,0.12);
            --blue-glow:      rgba(56,189,248,0.25);
            --amber:          #f59e0b;
            --amber-dim:      rgba(245,158,11,0.12);
            --amber-glow:     rgba(245,158,11,0.25);
            --emerald:        #10b981;
            --emerald-dim:    rgba(16,185,129,0.12);
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
            background-image: radial-gradient(ellipse 80% 50% at 50% -20%, rgba(56,189,248,0.05) 0%, transparent 60%);
        }

        .container { max-width: 1560px; margin: 0 auto; padding: 2rem 1.5rem; }

        /* TOP BAR */
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
            color: var(--blue); background: var(--blue-dim); border: 1px solid rgba(56,189,248,0.2);
            padding: 6px 12px; border-radius: 99px;
        }

        /* HEADER */
        .page-header { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 2rem; gap: 1.5rem; flex-wrap: wrap; }
        .header-info h1 {
            font-size: clamp(1.6rem, 3vw, 2.2rem); font-weight: 700;
            color: var(--text-primary); letter-spacing: -0.03em; line-height: 1.1; margin-bottom: 0.25rem;
        }
        .header-info h1 span { background: linear-gradient(135deg, var(--blue), #0ea5e9); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .header-info p { color: var(--text-secondary); font-size: 0.95rem; }
        .header-actions { display: flex; gap: 12px; flex-wrap: wrap; align-items: center; }

        /* BUTTONS */
        .btn {
            display: inline-flex; align-items: center; justify-content: center; gap: 8px;
            padding: 10px 20px; border-radius: var(--radius-md); font-size: 0.9rem;
            font-weight: 600; font-family: var(--font); border: 1px solid transparent;
            cursor: pointer; transition: all var(--transition); text-decoration: none; white-space: nowrap;
        }
        .btn-primary { background: var(--blue); color: #000; }
        .btn-primary:hover { background: #7dd3fc; box-shadow: 0 0 16px var(--blue-glow); transform: translateY(-1px); }
        .btn-amber { background: var(--amber); color: #000; }
        .btn-amber:hover { background: #fbbf24; box-shadow: 0 0 16px var(--amber-glow); transform: translateY(-1px); }
        .btn-ghost { background: transparent; color: var(--text-secondary); border-color: var(--border); }
        .btn-ghost:hover { background: var(--bg-elevated); color: var(--text-primary); border-color: rgba(255,255,255,0.15); }

        /* SEARCH */
        .search-container { position: relative; margin-bottom: 1.5rem; }
        .search-icon { position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--text-muted); pointer-events: none; }
        .search-input {
            width: 100%; padding: 12px 12px 12px 2.8rem; background: var(--bg-surface);
            border: 1px solid var(--border); border-radius: var(--radius-lg); color: var(--text-primary);
            font-size: 0.95rem; font-family: var(--font); outline: none; transition: all var(--transition);
        }
        .search-input:focus { border-color: var(--blue); box-shadow: 0 0 0 3px var(--blue-glow); }

        /* ── DESKTOP TABLE ── */
        .table-card {
            background: var(--bg-surface); border: 1px solid var(--border);
            border-radius: var(--radius-xl); overflow: hidden;
            box-shadow: 0 10px 30px -10px rgba(0,0,0,0.5);
        }
        .table-wrap { overflow-x: auto; }
        .data-table { width: 100%; border-collapse: collapse; min-width: 900px; }
        .data-table thead th {
            background: var(--bg-elevated); color: var(--text-muted);
            font-size: 0.7rem; font-weight: 700; text-transform: uppercase;
            letter-spacing: 0.07em; padding: 14px 16px; text-align: left;
            border-bottom: 1px solid var(--border); white-space: nowrap;
        }
        .data-table tbody tr { border-bottom: 1px solid var(--border); transition: background var(--transition); }
        .data-table tbody tr:last-child { border-bottom: none; }
        .data-table tbody tr:hover { background: rgba(255,255,255,0.02); }
        .data-table td { padding: 14px 16px; font-size: 0.9rem; color: var(--text-primary); vertical-align: middle; }

        .ref-no { font-family: var(--font-mono); color: var(--text-muted); font-size: 0.85rem; }
        .supplier-name { color: var(--text-secondary); font-size: 0.9rem; }
        .item-name { font-weight: 700; color: #fff; font-size: 1rem; }
        .val-mono { font-family: var(--font-mono); font-weight: 600; font-size: 0.9rem; }
        .val-money { font-family: var(--font-mono); font-weight: 600; color: var(--emerald); font-size: 0.95rem; }

        .confirmed-badge {
            display: inline-flex; align-items: center; justify-content: center; gap: 6px;
            background: var(--emerald-dim); color: var(--emerald); border: 1px solid rgba(16,185,129,0.2);
            border-radius: 6px; padding: 6px 12px; font-weight: 700; font-size: 0.75rem;
            text-transform: uppercase; width: 100%;
        }
        .confirm-btn {
            background: var(--red-dim); color: var(--red); border: 1px solid rgba(239,68,68,0.3);
            padding: 6px 12px; border-radius: 6px; font-weight: 700; font-size: 0.75rem;
            cursor: pointer; transition: all var(--transition); width: 100%; text-transform: uppercase;
        }
        .confirm-btn:hover { background: var(--red); color: #fff; box-shadow: 0 4px 12px rgba(239,68,68,0.3); }

        .actions { display: flex; gap: 8px; justify-content: center; }
        .action-btn {
            width: 32px; height: 32px; border-radius: 6px; border: 1px solid var(--border);
            background: var(--bg-elevated); display: inline-flex; align-items: center; justify-content: center;
            cursor: pointer; transition: all var(--transition); color: var(--text-secondary); text-decoration: none;
        }
        .action-btn:hover { background: var(--bg-hover); color: var(--text-primary); }
        .action-btn.view:hover  { color: var(--emerald); border-color: var(--emerald); }
        .action-btn.edit:hover  { color: var(--blue);    border-color: var(--blue); }
        .action-btn.delete:hover{ color: var(--red);     border-color: var(--red); }

        .empty-state { text-align: center; padding: 4rem 2rem; color: var(--text-muted); }
        .empty-state i { font-size: 2.5rem; margin-bottom: 1rem; opacity: 0.3; display: block; }

        /* ── MOBILE CARD LIST ── (replaces the table) */
        .mobile-list { display: none; padding: 1rem; }

        .purchase-card {
            background: var(--bg-elevated);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 1rem 1.1rem;
            margin-bottom: 0.85rem;
        }
        .purchase-card .card-top {
            display: flex; justify-content: space-between; align-items: flex-start;
            margin-bottom: 0.65rem; gap: 0.5rem;
        }
        .purchase-card .card-name  { font-weight: 700; color: #fff; font-size: 1rem; }
        .purchase-card .card-ref   { font-family: var(--font-mono); font-size: 0.78rem; color: var(--text-muted); margin-top: 2px; }
        .purchase-card .card-badge { flex-shrink: 0; }

        .purchase-card .card-grid {
            display: grid; grid-template-columns: 1fr 1fr;
            gap: 0.4rem 0.75rem; margin-bottom: 0.75rem;
        }
        .purchase-card .card-field { display: flex; flex-direction: column; gap: 1px; }
        .purchase-card .card-field .lbl {
            font-size: 0.65rem; font-weight: 700; text-transform: uppercase;
            letter-spacing: 0.06em; color: var(--text-muted);
        }
        .purchase-card .card-field .val {
            font-size: 0.88rem; color: var(--text-primary);
        }
        .purchase-card .card-field .val.money { color: var(--emerald); font-family: var(--font-mono); font-weight: 600; }
        .purchase-card .card-field .val.mono  { font-family: var(--font-mono); }

        .purchase-card .card-footer {
            display: flex; align-items: center; justify-content: flex-end;
            gap: 8px; padding-top: 0.65rem; border-top: 1px solid var(--border);
        }
        .purchase-card .card-confirm-btn {
            flex: 1; background: var(--red-dim); color: var(--red);
            border: 1px solid rgba(239,68,68,0.3); padding: 7px 12px;
            border-radius: 6px; font-weight: 700; font-size: 0.75rem;
            cursor: pointer; transition: all var(--transition); text-transform: uppercase;
        }
        .purchase-card .card-confirm-btn:hover { background: var(--red); color: #fff; }
        .purchase-card .card-confirmed {
            flex: 1; display: inline-flex; align-items: center; justify-content: center; gap: 5px;
            background: var(--emerald-dim); color: var(--emerald);
            border: 1px solid rgba(16,185,129,0.2); border-radius: 6px;
            padding: 7px 12px; font-weight: 700; font-size: 0.75rem; text-transform: uppercase;
        }

        /* MODALS */
        .modal {
            display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.85);
            backdrop-filter: blur(5px); z-index: 1000; align-items: center; justify-content: center; padding: 1rem;
        }
        .modal.show { display: flex; }
        .modal-content {
            background: var(--bg-surface); border: 1px solid var(--border);
            border-radius: var(--radius-xl); width: 100%; max-width: 650px;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5); display: flex; flex-direction: column;
            max-height: 90vh; animation: modalZoom 0.2s ease-out;
        }
        @keyframes modalZoom { from { transform: scale(0.95); opacity: 0; } to { transform: scale(1); opacity: 1; } }
        .modal-header { padding: 1.5rem; border-bottom: 1px solid var(--border); }
        .modal-header h2 { margin: 0; font-size: 1.25rem; font-weight: 700; color: #fff; }
        .modal-body   { padding: 1.5rem; overflow-y: auto; }
        .modal-footer { padding: 1.25rem 1.5rem; border-top: 1px solid var(--border); display: flex; justify-content: flex-end; gap: 10px; background: var(--bg-elevated); border-radius: 0 0 var(--radius-xl) var(--radius-xl); }

        /* Form */
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.25rem; }
        .form-group { display: flex; flex-direction: column; gap: 6px; }
        .form-group.full-width { grid-column: 1 / -1; margin-bottom: 1.25rem; }
        .form-label { font-size: 0.72rem; font-weight: 600; text-transform: uppercase; color: var(--text-secondary); letter-spacing: 0.05em; }
        .form-label span { color: var(--red); }
        .form-control, .form-select {
            width: 100%; padding: 10px 12px; background: var(--bg-elevated);
            border: 1px solid var(--border); color: var(--text-primary);
            border-radius: 8px; font-size: 0.95rem; font-family: var(--font);
            outline: none; transition: all var(--transition);
        }
        .form-control:focus, .form-select:focus, textarea.form-control:focus { border-color: var(--blue); box-shadow: 0 0 0 3px var(--blue-glow); }
        textarea.form-control { resize: vertical; min-height: 60px; line-height: 1.5; }
        .form-select {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2394a3b8' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
            background-repeat: no-repeat; background-position: right 12px center; cursor: pointer;
        }
        .form-select:disabled, input:disabled, input[readonly] { opacity: 0.5; cursor: not-allowed; }

        .info-group h3 {
            font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.05em;
            color: var(--blue); margin: 1.5rem 0 1rem 0;
            border-bottom: 1px solid var(--border); padding-bottom: 8px;
        }

        /* Dynamic Rows */
        .dynamic-row {
            display: grid; grid-template-columns: 2fr 1fr 1fr auto; gap: 10px;
            background: rgba(255,255,255,0.02); padding: 1rem; border-radius: var(--radius-md);
            border: 1px solid var(--border); margin-bottom: 10px; align-items: start;
        }
        .dynamic-row .form-group { margin-bottom: 0; }
        .btn-remove-row {
            background: transparent; color: var(--red); border: 1px solid rgba(239,68,68,0.3);
            border-radius: 6px; cursor: pointer; padding: 10px; font-weight: bold;
            margin-top: 23px; transition: 0.2s; display: flex; align-items: center; justify-content: center;
        }
        .btn-remove-row:hover { background: var(--red-dim); border-color: var(--red); }

        /* Autocomplete */
        .autocomplete-wrapper { position: relative; }
        .autocomplete-list {
            position: absolute; z-index: 1050; top: 100%; left: 0; right: 0;
            background: var(--bg-elevated); border: 1px solid var(--border);
            border-top: none; border-radius: 0 0 8px 8px; max-height: 200px;
            overflow-y: auto; box-shadow: var(--shadow-md); display: none;
        }
        .autocomplete-list.show { display: block; }
        .autocomplete-item { padding: 10px 14px; cursor: pointer; transition: background 0.2s; border-bottom: 1px solid var(--border); color: var(--text-primary); font-size: 0.9rem; }
        .autocomplete-item:last-child { border-bottom: none; }
        .autocomplete-item:hover { background: var(--bg-hover); color: var(--blue); }
        .autocomplete-item strong { color: var(--blue); }
        .autocomplete-loading, .autocomplete-no-results { padding: 12px; text-align: center; color: var(--text-muted); font-size: 0.85rem; font-style: italic; }

        /* Alerts */
        .alert { padding: 12px 16px; border-radius: var(--radius-md); margin-bottom: 1.5rem; display: none; text-align: center; font-weight: 600; font-size: 0.9rem; }
        .alert.success { background: var(--emerald-dim); border: 1px solid rgba(16,185,129,0.3); color: var(--emerald); }
        .alert.error   { background: var(--red-dim);     border: 1px solid rgba(239,68,68,0.3);  color: var(--red); }

        /* Confirm Modals */
        .confirm-content { text-align: center; padding: 1rem; }
        .confirm-icon { font-size: 3.5rem; margin-bottom: 1rem; display: block; opacity: 0.8; }
        .warning-text {
            color: var(--red); font-size: 0.85rem; margin: 1.5rem 0;
            background: var(--red-dim); padding: 12px; border-radius: 8px;
            border: 1px solid rgba(239,68,68,0.2); line-height: 1.4; text-align: left;
        }

        /* ── RESPONSIVE ── */
        @media (max-width: 768px) {
            .container { padding: 1rem; }
            .page-header { flex-direction: column; align-items: flex-start; }
            .header-actions { width: 100%; display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
            .btn { width: 100%; justify-content: center; }

            /* Hide desktop table, show mobile cards */
            .table-card  { display: none; }
            .mobile-list { display: block; }

            .form-row    { grid-template-columns: 1fr; }
            .dynamic-row { grid-template-columns: 1fr; }
            .btn-remove-row { margin-top: 0; width: 100%; }
            .modal-footer { flex-direction: column; gap: 10px; }
            .modal-footer .btn { width: 100%; margin: 0 !important; }
        }
    </style>
</head>
<body>

<div class="container">

    <div class="top-bar">
        <a href="purchase_dashboard.php" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back to Purchases
        </a>
        <span class="page-badge"><i class="fa-solid fa-cow"></i> Core Inventory</span>
    </div>

    <div class="page-header">
        <div class="header-info">
            <h1>Animal <span>Purchases</span></h1>
            <p>Log and manage incoming livestock acquisitions before structural assignment.</p>
        </div>
        <div class="header-actions">
            <button class="btn btn-amber" onclick="openConfirmAllModal()">
                <i class="fa-solid fa-check-double"></i> Confirm All
            </button>
            <button class="btn btn-primary" onclick="openAddModal()">
                <i class="fa-solid fa-plus"></i> Add Purchase
            </button>
        </div>
    </div>

    <div class="search-container">
        <i class="fa-solid fa-magnifying-glass search-icon"></i>
        <input type="text" class="search-input" id="searchInput" placeholder="Search by breed, type, or supplier..." oninput="filterAll()">
    </div>

    <!-- ── DESKTOP TABLE ── -->
    <div class="table-card">
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Ref No</th>
                        <th>Supplier</th>
                        <th>Animal Details</th>
                        <th>Quantity</th>
                        <th>Cost per Head</th>
                        <th>Purchase Date</th>
                        <th style="text-align:center; width:140px;">Status</th>
                        <th style="text-align:center; width:120px;">Actions</th>
                    </tr>
                </thead>
                <tbody id="item-table">
                    <?php if(empty($items_data)): ?>
                        <tr class="empty-row">
                            <td colspan="8">
                                <div class="empty-state">
                                    <i class="fa-solid fa-folder-open"></i>
                                    No animal purchases recorded yet in this location.
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach($items_data as $item):
                            $status      = isset($item['STATUS']) ? (int)$item['STATUS'] : 0;
                            $isConfirmed = ($status === 1);
                        ?>
                        <tr class="data-row"
                            data-item-id="<?php echo $item['ITEM_ID']; ?>"
                            data-item-name="<?php echo htmlspecialchars($item['ITEM_NAME']); ?>"
                            data-item-desc="<?php echo htmlspecialchars($item['ITEM_DESCRIPTION'] ?? ''); ?>"
                            data-unit-id="<?php echo $item['UNIT_ID']; ?>"
                            data-unit-cost="<?php echo $item['UNIT_COST']; ?>"
                            data-unit-name="<?php echo htmlspecialchars($item['UNIT_NAME']); ?>"
                            data-quantity="<?php echo $item['QUANTITY'] ?? '1'; ?>"
                            data-weight="<?php echo $item['ITEM_NET_WEIGHT'] ?? '0'; ?>"
                            data-purchase-date-raw="<?php echo htmlspecialchars($item['DATE_OF_PURCHASE'] ?? ''); ?>"
                            data-purchase-date-fmt="<?php echo htmlspecialchars($item['DATE_OF_PURCHASE_FMT'] ?? ''); ?>"
                            data-location-id="<?php echo $item['LOCATION_ID'] ?? ''; ?>"
                            data-building-id="<?php echo $item['BUILDING_ID'] ?? ''; ?>"
                            data-pen-id="<?php echo $item['PEN_ID'] ?? ''; ?>"
                            data-supplier="<?php echo htmlspecialchars($item['SUPPLIER'] ?? ''); ?>"
                            data-reference-no="<?php echo htmlspecialchars($item['REFERENCE_NO'] ?? ''); ?>"
                            data-created-at="<?php echo htmlspecialchars($item['CREATED_AT_FMT'] ?? ''); ?>"
                            data-confirmed="<?php echo $isConfirmed ? '1' : '0'; ?>">

                            <td><div class="ref-no"><?php echo !empty($item['REFERENCE_NO']) ? htmlspecialchars($item['REFERENCE_NO']) : '—'; ?></div></td>
                            <td><div class="supplier-name"><?php echo !empty($item['SUPPLIER']) ? htmlspecialchars($item['SUPPLIER']) : 'General Supplier'; ?></div></td>
                            <td><div class="item-name"><?php echo htmlspecialchars($item['ITEM_NAME']); ?></div></td>
                            <td>
                                <div class="val-mono" style="color:#fff;">
                                    <?php echo number_format($item['QUANTITY'] ?? 1, 0); ?>
                                    <span style="color:var(--text-muted);font-size:0.8rem;">Heads</span>
                                </div>
                            </td>
                            <td><div class="val-money">₱<?php echo number_format($item['UNIT_COST'], 2); ?></div></td>
                            <td><div class="val-mono"><?php echo htmlspecialchars($item['DATE_OF_PURCHASE_FMT'] ?? 'N/A'); ?></div></td>
                            <td style="text-align:center;">
                                <?php if(!$isConfirmed): ?>
                                    <button class="confirm-btn" onclick="openConfirmModal(this)">Confirm</button>
                                <?php else: ?>
                                    <div class="confirmed-badge"><i class="fa-solid fa-check"></i> Verified</div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="actions">
                                    <button class="action-btn view" onclick="viewItem(this)" title="View Details"><i class="fa-regular fa-eye"></i></button>
                                    <?php if(!$isConfirmed): ?>
                                        <button class="action-btn edit"   onclick="editItem(this)"   title="Edit"><i class="fa-solid fa-pen-to-square"></i></button>
                                        <button class="action-btn delete" onclick="deleteItem(this)" title="Delete"><i class="fa-solid fa-trash-can"></i></button>
                                    <?php else: ?>
                                        <span style="opacity:0.3;cursor:not-allowed;display:flex;align-items:center;margin-left:4px;"><i class="fa-solid fa-lock"></i></span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            <div id="empty-state-desktop" class="empty-state" style="display:none;">
                <i class="fa-solid fa-magnifying-glass"></i>
                <p>No purchases found matching your search.</p>
            </div>
        </div>
    </div>

    <!-- ── MOBILE CARD LIST ── -->
    <div class="mobile-list" id="mobile-list">
        <?php if(empty($items_data)): ?>
            <div class="empty-state">
                <i class="fa-solid fa-folder-open"></i>
                No animal purchases recorded yet in this location.
            </div>
        <?php else: ?>
            <?php foreach($items_data as $item):
                $status      = isset($item['STATUS']) ? (int)$item['STATUS'] : 0;
                $isConfirmed = ($status === 1);
            ?>
            <div class="purchase-card mobile-card"
                data-item-id="<?php echo $item['ITEM_ID']; ?>"
                data-item-name="<?php echo htmlspecialchars($item['ITEM_NAME']); ?>"
                data-item-desc="<?php echo htmlspecialchars($item['ITEM_DESCRIPTION'] ?? ''); ?>"
                data-unit-id="<?php echo $item['UNIT_ID']; ?>"
                data-unit-cost="<?php echo $item['UNIT_COST']; ?>"
                data-unit-name="<?php echo htmlspecialchars($item['UNIT_NAME']); ?>"
                data-quantity="<?php echo $item['QUANTITY'] ?? '1'; ?>"
                data-weight="<?php echo $item['ITEM_NET_WEIGHT'] ?? '0'; ?>"
                data-purchase-date-raw="<?php echo htmlspecialchars($item['DATE_OF_PURCHASE'] ?? ''); ?>"
                data-purchase-date-fmt="<?php echo htmlspecialchars($item['DATE_OF_PURCHASE_FMT'] ?? ''); ?>"
                data-location-id="<?php echo $item['LOCATION_ID'] ?? ''; ?>"
                data-building-id="<?php echo $item['BUILDING_ID'] ?? ''; ?>"
                data-pen-id="<?php echo $item['PEN_ID'] ?? ''; ?>"
                data-supplier="<?php echo htmlspecialchars($item['SUPPLIER'] ?? ''); ?>"
                data-reference-no="<?php echo htmlspecialchars($item['REFERENCE_NO'] ?? ''); ?>"
                data-created-at="<?php echo htmlspecialchars($item['CREATED_AT_FMT'] ?? ''); ?>"
                data-confirmed="<?php echo $isConfirmed ? '1' : '0'; ?>">

                <div class="card-top">
                    <div>
                        <div class="card-name"><?php echo htmlspecialchars($item['ITEM_NAME']); ?></div>
                        <div class="card-ref"><?php echo !empty($item['REFERENCE_NO']) ? htmlspecialchars($item['REFERENCE_NO']) : 'No reference'; ?></div>
                    </div>
                    <div class="card-badge">
                        <?php if($isConfirmed): ?>
                            <div class="confirmed-badge" style="width:auto;"><i class="fa-solid fa-check"></i> Verified</div>
                        <?php else: ?>
                            <div style="background:var(--red-dim);color:var(--red);border:1px solid rgba(239,68,68,0.3);border-radius:6px;padding:4px 10px;font-size:0.7rem;font-weight:700;text-transform:uppercase;">Pending</div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card-grid">
                    <div class="card-field">
                        <span class="lbl">Supplier</span>
                        <span class="val"><?php echo !empty($item['SUPPLIER']) ? htmlspecialchars($item['SUPPLIER']) : 'General Supplier'; ?></span>
                    </div>
                    <div class="card-field">
                        <span class="lbl">Purchase Date</span>
                        <span class="val mono"><?php echo htmlspecialchars($item['DATE_OF_PURCHASE_FMT'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="card-field">
                        <span class="lbl">Quantity</span>
                        <span class="val"><?php echo number_format($item['QUANTITY'] ?? 1, 0); ?> Heads</span>
                    </div>
                    <div class="card-field">
                        <span class="lbl">Cost per Head</span>
                        <span class="val money">₱<?php echo number_format($item['UNIT_COST'], 2); ?></span>
                    </div>
                </div>

                <div class="card-footer">
                    <button class="action-btn view" onclick="viewItemFromCard(this)" title="View"><i class="fa-regular fa-eye"></i></button>
                    <?php if(!$isConfirmed): ?>
                        <button class="action-btn edit"   onclick="editItemFromCard(this)"   title="Edit"><i class="fa-solid fa-pen-to-square"></i></button>
                        <button class="action-btn delete" onclick="deleteItemFromCard(this)" title="Delete"><i class="fa-solid fa-trash-can"></i></button>
                        <button class="card-confirm-btn" onclick="openConfirmModalFromCard(this)">Confirm</button>
                    <?php else: ?>
                        <div class="card-confirmed"><i class="fa-solid fa-lock"></i> Locked</div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
        <div id="empty-state-mobile" class="empty-state" style="display:none;">
            <i class="fa-solid fa-magnifying-glass"></i>
            <p>No purchases found matching your search.</p>
        </div>
    </div>

</div><!-- /container -->

<!-- ── ADD / EDIT MODAL ── -->
<div id="modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modal-title">Add Animal Purchase</h2>
        </div>
        <div class="modal-body">
            <div id="modal-alert" class="alert"></div>
            <form id="item-form" method="POST">
                <input type="hidden" id="item-id" name="item_id">
                <input type="hidden" name="item_type_id" value="<?php echo $ANIMAL_ITEM_TYPE_ID; ?>">
                <input type="hidden" name="unit_id"      value="<?php echo $default_unit_id; ?>">
                <?php if($USER_LOCATION_ != 1000): ?>
                    <input type="hidden" name="location_id" value="<?php echo $USER_LOCATION_; ?>">
                <?php endif; ?>

                <div class="info-group" style="margin-top:0;">
                    <h3 style="margin-top:0;">Batch Details</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Date of Purchase <span>*</span></label>
                            <input type="text" id="purchase-date" name="date_of_purchase" class="form-control" placeholder="mm/dd/yyyy" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Location <span>*</span></label>
                            <select id="location_id" name="location_id" class="form-select" onchange="filterBuildings()"
                                <?php echo ($USER_LOCATION_ != 1000) ? 'disabled' : 'required'; ?>>
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
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Building</label>
                            <select id="building_id" name="building_id" class="form-select" onchange="filterPens()" disabled>
                                <option value="">Select Location First</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Target Pen</label>
                            <select id="pen_id" name="pen_id" class="form-select" disabled>
                                <option value="">Select Building First</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group autocomplete-wrapper">
                            <label class="form-label">Supplier Entity</label>
                            <input type="text" id="supplier" name="supplier" class="form-control" placeholder="e.g., ABC Farms" autocomplete="off">
                            <div id="supplier-autocomplete-list" class="autocomplete-list"></div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Reference No. (Receipt)</label>
                            <input type="text" id="reference-no" name="reference_no" class="form-control" placeholder="e.g., OR-12345">
                        </div>
                    </div>
                    <div class="form-group full-width">
                        <label class="form-label">Description / Remarks</label>
                        <textarea id="item-desc" name="item_description" class="form-control" placeholder="Enter batch details..." maxlength="500"></textarea>
                    </div>
                </div>

                <div class="info-group">
                    <div id="bulk-row-controls" style="display:flex;justify-content:space-between;align-items:flex-end;margin-bottom:15px;border-bottom:1px solid var(--border);padding-bottom:15px;flex-wrap:wrap;gap:10px;">
                        <h3 style="margin:0;border:none;padding:0;width:100%;">Livestock Entries</h3>
                        <div style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
                            <div class="form-group" style="width:70px;margin:0;">
                                <label class="form-label">Qty Rows</label>
                                <input type="number" id="row_qty" value="1" min="1" class="form-control" style="height:38px;">
                            </div>
                            <div class="form-group" style="width:100px;margin:0;">
                                <label class="form-label">Wt (kg)</label>
                                <input type="number" id="default_weight" placeholder="Opt." step="0.01" class="form-control" style="height:38px;">
                            </div>
                            <div class="form-group" style="width:110px;margin:0;">
                                <label class="form-label">Cost (₱)</label>
                                <input type="number" id="default_cost" placeholder="Opt." step="0.01" class="form-control" style="height:38px;">
                            </div>
                            <button type="button" class="btn btn-ghost" style="height:38px;" onclick="generateAnimalRows()">
                                <i class="fa-solid fa-plus"></i> Add
                            </button>
                        </div>
                    </div>
                    <div id="dynamic-animal-container"></div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" id="btnFooterAddAnimal" class="btn btn-ghost" style="margin-right:auto;" onclick="generateAnimalRows()">
                <i class="fa-solid fa-plus"></i> Row
            </button>
            <button type="button" class="btn btn-ghost" onclick="closeModal()">Cancel</button>
            <button type="button" class="btn btn-primary" id="btn-save" onclick="saveItem()">Save Purchase</button>
        </div>
    </div>
</div>

<!-- ── VIEW MODAL ── -->
<div id="view-modal" class="modal">
    <div class="modal-content" style="max-width:500px;">
        <div class="modal-header"><h2>Purchase Dossier</h2></div>
        <div class="modal-body" id="view-modal-body"></div>
        <div class="modal-footer">
            <button type="button" class="btn btn-ghost" onclick="closeViewModal()" style="width:100%;">Close</button>
        </div>
    </div>
</div>

<!-- ── CONFIRM SINGLE ── -->
<div id="confirm-modal" class="modal">
    <div class="modal-content" style="max-width:450px;">
        <div class="modal-body confirm-content">
            <span class="confirm-icon" style="color:var(--red);">🐖</span>
            <h2 style="color:#fff;margin-bottom:10px;">Verify Acquisition?</h2>
            <p style="color:var(--text-secondary);margin-bottom:5px;">
                Confirming intake of <strong><span id="confirm-item-qty"></span> <span id="confirm-item-name" style="color:var(--blue);"></span></strong>.
            </p>
            <div class="warning-text">
                <i class="fa-solid fa-triangle-exclamation" style="margin-right:6px;"></i>
                <strong>Critical:</strong> Once confirmed, this record is locked and cannot be edited or deleted.
            </div>
            <form id="confirmForm" method="POST">
                <input type="hidden" id="confirm_item_id" name="item_id">
            </form>
        </div>
        <div class="modal-footer" style="justify-content:center;border-top:none;padding-top:0;padding-bottom:30px;background:transparent;">
            <button type="button" class="btn btn-ghost" onclick="closeConfirmModal()">Cancel</button>
            <button type="button" class="btn btn-primary" onclick="submitConfirmation()" style="background:var(--red);border-color:var(--red);">Yes, Lock Record</button>
        </div>
    </div>
</div>

<!-- ── CONFIRM ALL ── -->
<div id="confirm-all-modal" class="modal">
    <div class="modal-content" style="max-width:450px;">
        <div class="modal-body confirm-content">
            <span class="confirm-icon" style="color:var(--amber);">📋</span>
            <h2 style="color:#fff;margin-bottom:10px;">Commit All Pending?</h2>
            <p style="color:var(--text-secondary);">This will verify and lock <strong>ALL</strong> pending animal acquisitions in this location.</p>
            <div class="warning-text" style="background:var(--amber-dim);border-color:rgba(245,158,11,0.3);color:var(--amber);">
                <i class="fa-solid fa-triangle-exclamation" style="margin-right:6px;"></i>
                <strong>Irreversible:</strong> Please audit all pending items before executing this batch commit.
            </div>
        </div>
        <div class="modal-footer" style="justify-content:center;border-top:none;padding-top:0;padding-bottom:30px;background:transparent;">
            <button type="button" class="btn btn-ghost" onclick="closeConfirmAllModal()">Cancel</button>
            <button type="button" class="btn btn-amber" onclick="submitConfirmAll()">Commit All Now</button>
        </div>
    </div>
</div>

<!-- Hidden delete form -->
<form id="deleteItemForm" method="POST" action="../process/deleteAnimalPurchase.php" style="display:none;">
    <input type="hidden" id="delete_item_id" name="item_id">
</form>

<script>
const allBuildings  = <?php echo json_encode($buildings_raw); ?>;
const allPens       = <?php echo json_encode($pens_raw); ?>;
const USER_LOCATION = <?php echo json_encode($USER_LOCATION_); ?>;

let fpPurchaseDate;

document.addEventListener('DOMContentLoaded', () => {
    fpPurchaseDate = flatpickr("#purchase-date", {
        dateFormat: "Y-m-d",
        altInput:   true,
        altFormat:  "m/d/Y",
        allowInput: true
    });
    if (USER_LOCATION != 1000) filterBuildings();
});

/* ── helpers to get dataset from either a <tr> or a .purchase-card ── */
function dsFrom(el) {
    return el.closest('tr') ? el.closest('tr').dataset
                            : el.closest('.purchase-card').dataset;
}

/* ── Confirm single ── */
function openConfirmModal(btn)         { _openConfirm(btn.closest('tr').dataset); }
function openConfirmModalFromCard(btn) { _openConfirm(btn.closest('.purchase-card').dataset); }
function _openConfirm(ds) {
    document.getElementById('confirm_item_id').value  = ds.itemId;
    document.getElementById('confirm-item-name').textContent = ds.itemName;
    document.getElementById('confirm-item-qty').textContent  = ds.quantity;
    document.getElementById('confirm-modal').classList.add('show');
}
function closeConfirmModal() { document.getElementById('confirm-modal').classList.remove('show'); }
function submitConfirmation() {
    fetch('../purchase_confirmations/confirmAnimalPurchase.php', {
        method: 'POST', body: new FormData(document.getElementById('confirmForm'))
    }).then(r=>r.json()).then(d=>{ alert(d.message); if(d.success) location.reload(); });
}

/* ── Confirm all ── */
function openConfirmAllModal()  { document.getElementById('confirm-all-modal').classList.add('show'); }
function closeConfirmAllModal() { document.getElementById('confirm-all-modal').classList.remove('show'); }
function submitConfirmAll() {
    fetch('../purchase_confirmations/confirmAllAnimalPurchases.php', { method: 'POST' })
        .then(r=>r.json()).then(d=>{ alert(d.message); if(d.success) location.reload(); });
}

/* ── Location cascades ── */
function filterBuildings() {
    const bSelect = document.getElementById('building_id');
    const pSelect = document.getElementById('pen_id');
    const locId   = document.getElementById('location_id').value || String(USER_LOCATION);

    bSelect.innerHTML = '<option value="">Select Building</option>';
    pSelect.innerHTML = '<option value="">Select Building First</option>';
    pSelect.disabled  = true;

    if (locId && locId !== '0') {
        bSelect.disabled = false;
        allBuildings.filter(b => b.LOCATION_ID == locId)
                    .forEach(b => bSelect.add(new Option(b.BUILDING_NAME, b.BUILDING_ID)));
    } else {
        bSelect.disabled = true;
    }
}

function filterPens() {
    const pSelect = document.getElementById('pen_id');
    const bId     = document.getElementById('building_id').value;
    pSelect.innerHTML = '<option value="">Select Pen</option>';
    if (bId) {
        pSelect.disabled = false;
        allPens.filter(p => p.BUILDING_ID == bId)
               .forEach(p => pSelect.add(new Option(p.PEN_NAME, p.PEN_ID)));
    } else {
        pSelect.disabled = true;
    }
}

/* ── Dynamic rows ── */
function generateAnimalRows() {
    const qty = parseInt(document.getElementById('row_qty').value) || 1;
    const w   = document.getElementById('default_weight').value;
    const c   = document.getElementById('default_cost').value;
    for (let i = 0; i < qty; i++) addAnimalRow('', w, c);
    document.getElementById('row_qty').value = 1;
}

function addAnimalRow(name='', weight='', cost='') {
    const id   = 'row-' + Date.now() + Math.floor(Math.random()*100);
    const html = `
        <div class="dynamic-row" id="${id}">
            <div class="form-group autocomplete-wrapper">
                <label class="form-label">Type / Breed <span>*</span></label>
                <input type="text" name="item_names[]" class="form-control animal-input" value="${name}" required autocomplete="off">
                <div class="autocomplete-list"></div>
            </div>
            <div class="form-group">
                <label class="form-label">Weight (kg)</label>
                <input type="number" name="weights[]" class="form-control val-mono" value="${weight}" step="0.01" min="0">
            </div>
            <div class="form-group">
                <label class="form-label">Cost (₱) <span>*</span></label>
                <input type="number" name="unit_costs[]" class="form-control val-mono" value="${cost}" step="0.01" min="0" required>
            </div>
            <button type="button" class="btn-remove-row" onclick="document.getElementById('${id}').remove()" title="Remove">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>`;
    document.getElementById('dynamic-animal-container').insertAdjacentHTML('beforeend', html);
    attachAutocomplete(document.getElementById(id).querySelector('.animal-input'));
}

/* ── Animal autocomplete ── */
let acTimer = null;
function attachAutocomplete(input) {
    const list = input.nextElementSibling;
    input.addEventListener('input', function() {
        clearTimeout(acTimer);
        const val = this.value.trim();
        if (val.length < 2) { list.classList.remove('show'); return; }
        list.innerHTML = '<div class="autocomplete-loading">Querying registry...</div>';
        list.classList.add('show');
        acTimer = setTimeout(() => {
            fetch(`../process/searchAnimals.php?term=${encodeURIComponent(val)}`)
                .then(r=>r.json()).then(data => {
                    list.innerHTML = '';
                    if (!data.length) { list.innerHTML = '<div class="autocomplete-no-results">No breed match found</div>'; return; }
                    data.forEach(item => {
                        const d = document.createElement('div');
                        d.className = 'autocomplete-item';
                        d.innerHTML = item.replace(new RegExp(`(${val})`,'gi'),'<strong>$1</strong>');
                        d.onclick = () => { input.value = item; list.classList.remove('show'); };
                        list.appendChild(d);
                    });
                }).catch(()=>list.classList.remove('show'));
        }, 300);
    });
    document.addEventListener('click', e => { if(!input.parentElement.contains(e.target)) list.classList.remove('show'); });
}

/* ── Supplier autocomplete ── */
let supTimer = null;
const supInput = document.getElementById('supplier');
const supList  = document.getElementById('supplier-autocomplete-list');
if (supInput) {
    supInput.addEventListener('input', function() {
        clearTimeout(supTimer);
        const val = this.value.trim();
        if (val.length < 2) { supList.classList.remove('show'); return; }
        supList.innerHTML = '<div class="autocomplete-loading">Finding vendor...</div>';
        supList.classList.add('show');
        supTimer = setTimeout(() => {
            fetch(`../process/searchSuppliers.php?term=${encodeURIComponent(val)}`)
                .then(r=>r.json()).then(data => {
                    supList.innerHTML = '';
                    if (!data.length) { supList.innerHTML = '<div class="autocomplete-no-results">No previous supplier matched</div>'; return; }
                    data.forEach(item => {
                        const d = document.createElement('div');
                        d.className = 'autocomplete-item';
                        d.innerHTML = item.replace(new RegExp(`(${val})`,'gi'),'<strong>$1</strong>');
                        d.onclick = () => { supInput.value = item; supList.classList.remove('show'); };
                        supList.appendChild(d);
                    });
                }).catch(()=>supList.classList.remove('show'));
        }, 300);
    });
    document.addEventListener('click', e => { if(!supInput.parentElement.contains(e.target)) supList.classList.remove('show'); });
}

/* ── Open Add modal ── */
function openAddModal() {
    document.getElementById('modal-title').textContent = 'Register New Acquisition';
    document.getElementById('btn-save').innerHTML = '<i class="fa-solid fa-floppy-disk" style="margin-right:6px;"></i> Save Purchase';
    document.getElementById('item-form').reset();
    document.getElementById('item-id').value = '';
    fpPurchaseDate.setDate(new Date());

    const locSelect = document.getElementById('location_id');
    locSelect.value = (USER_LOCATION != 1000) ? USER_LOCATION : '';
    filterBuildings();

    document.getElementById('row_qty').value        = '1';
    document.getElementById('default_weight').value = '';
    document.getElementById('default_cost').value   = '';
    document.getElementById('dynamic-animal-container').innerHTML = '';
    document.getElementById('bulk-row-controls').style.display   = 'flex';
    document.getElementById('btnFooterAddAnimal').style.display   = 'flex';

    addAnimalRow();
    hideAlert();
    document.getElementById('modal').classList.add('show');
}

/* ── Shared edit population ── */
function _populateEdit(ds) {
    document.getElementById('modal-title').textContent = 'Modify Purchase Record';
    document.getElementById('btn-save').innerHTML = '<i class="fa-solid fa-arrows-rotate" style="margin-right:6px;"></i> Update Record';
    document.getElementById('item-id').value   = ds.itemId;
    document.getElementById('item-desc').value = ds.itemDesc || '';
    fpPurchaseDate.setDate(ds.purchaseDateRaw || '');
    document.getElementById('supplier').value     = ds.supplier || '';
    document.getElementById('reference-no').value = ds.referenceNo || '';

    const locSelect = document.getElementById('location_id');
    locSelect.value = ds.locationId || String(USER_LOCATION) || '';
    filterBuildings();
    if (ds.buildingId) {
        document.getElementById('building_id').value = ds.buildingId;
        filterPens();
        if (ds.penId) document.getElementById('pen_id').value = ds.penId;
    }

    document.getElementById('dynamic-animal-container').innerHTML = '';
    document.getElementById('bulk-row-controls').style.display    = 'none';
    document.getElementById('btnFooterAddAnimal').style.display   = 'none';

    addAnimalRow(ds.itemName, ds.weight, ds.unitCost);
    const rmBtn = document.querySelector('.btn-remove-row');
    if (rmBtn) rmBtn.style.display = 'none';

    hideAlert();
    document.getElementById('modal').classList.add('show');
}

function editItem(btn)         { _populateEdit(btn.closest('tr').dataset); }
function editItemFromCard(btn) { _populateEdit(btn.closest('.purchase-card').dataset); }

/* ── View modal ── */
function _buildViewHtml(ds) {
    return `
        <div class="info-group">
            <h3>Acquisition Overview</h3>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                <p style="color:var(--text-secondary);margin:0;"><strong>System ID:</strong><br><span class="val-mono">PCH-${String(ds.itemId).padStart(5,'0')}</span></p>
                <p style="color:var(--text-secondary);margin:0;"><strong>Reference No:</strong><br><span class="val-mono">${ds.referenceNo||'N/A'}</span></p>
            </div>
            <p style="margin-top:15px;color:var(--text-primary);"><strong>Genetic Profile:</strong><br>${ds.itemName}</p>
            <p style="margin-top:10px;color:var(--text-secondary);"><strong>Supplier Origin:</strong><br>${ds.supplier||'N/A'}</p>
            <p style="margin-top:10px;color:var(--text-secondary);"><strong>Remarks:</strong><br>${ds.itemDesc||'None'}</p>
        </div>
        <div class="info-group" style="margin-top:20px;">
            <h3>Financials &amp; Metrics</h3>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                <p style="color:var(--text-secondary);margin:0;"><strong>Batch Size:</strong><br><span style="color:#fff;">${ds.quantity||'1'} ${ds.unitName}</span></p>
                <p style="color:var(--text-secondary);margin:0;"><strong>Avg. Weight:</strong><br><span class="val-mono">${ds.weight||'0'} kg</span></p>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:15px;">
                <p style="color:var(--text-secondary);margin:0;"><strong>Cost per Head:</strong><br><span class="val-money">₱${parseFloat(ds.unitCost).toLocaleString('en-PH',{minimumFractionDigits:2})}</span></p>
                <p style="color:var(--text-secondary);margin:0;"><strong>Date Logged:</strong><br><span class="val-mono">${ds.purchaseDateFmt||'N/A'}</span></p>
            </div>
        </div>`;
}
function viewItem(btn)         { document.getElementById('view-modal-body').innerHTML = _buildViewHtml(btn.closest('tr').dataset); document.getElementById('view-modal').classList.add('show'); }
function viewItemFromCard(btn) { document.getElementById('view-modal-body').innerHTML = _buildViewHtml(btn.closest('.purchase-card').dataset); document.getElementById('view-modal').classList.add('show'); }
function closeViewModal() { document.getElementById('view-modal').classList.remove('show'); }

/* ── Delete ── */
function _doDelete(ds) {
    if (confirm(`Irreversible: Delete purchase record for "${ds.itemName}"?`)) {
        document.getElementById('delete_item_id').value = ds.itemId;
        document.getElementById('deleteItemForm').submit();
    }
}
function deleteItem(btn)         { _doDelete(btn.closest('tr').dataset); }
function deleteItemFromCard(btn) { _doDelete(btn.closest('.purchase-card').dataset); }

/* ── Save ── */
function saveItem() {
    const form = document.getElementById('item-form');
    if (!form.checkValidity()) { form.reportValidity(); return; }
    if (!document.querySelectorAll('.dynamic-row').length) { showAlert('Add at least one animal entry.','error'); return; }

    const formData = new FormData(form);
    const isEdit   = !!document.getElementById('item-id').value;
    const url      = isEdit ? '../process/editAnimalPurchase.php' : '../process/addAnimalPurchase.php';
    const saveBtn  = document.getElementById('btn-save');
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin" style="margin-right:6px;"></i> Processing...';

    if (isEdit) {
        const names   = formData.getAll('item_names[]');
        const weights = formData.getAll('weights[]');
        const costs   = formData.getAll('unit_costs[]');
        formData.delete('item_names[]');  formData.append('item_name',  names[0]);
        formData.delete('weights[]');     formData.append('weight',     weights[0]);
        formData.delete('unit_costs[]');  formData.append('unit_cost',  costs[0]);
    }

    fetch(url, { method:'POST', body: formData }).then(r=>r.json()).then(d => {
        if (d.success) { showAlert(d.message,'success'); setTimeout(()=>location.reload(), 1000); }
        else {
            showAlert(d.message,'error');
            saveBtn.disabled = false;
            saveBtn.innerHTML = isEdit
                ? '<i class="fa-solid fa-arrows-rotate" style="margin-right:6px;"></i> Update Record'
                : '<i class="fa-solid fa-floppy-disk" style="margin-right:6px;"></i> Save Purchase';
        }
    }).catch(()=>{
        showAlert('Network error.','error');
        saveBtn.disabled = false;
        saveBtn.innerHTML = isEdit
            ? '<i class="fa-solid fa-arrows-rotate" style="margin-right:6px;"></i> Update Record'
            : '<i class="fa-solid fa-floppy-disk" style="margin-right:6px;"></i> Save Purchase';
    });
}

function showAlert(msg,type) { const a=document.getElementById('modal-alert'); a.textContent=msg; a.className='alert '+type; a.style.display='block'; }
function hideAlert()  { document.getElementById('modal-alert').style.display='none'; }
function closeModal() { document.getElementById('modal').classList.remove('show'); }

/* ── Unified search / filter (desktop table + mobile cards) ── */
function filterAll() {
    const term = document.getElementById('searchInput').value.toLowerCase();

    // Desktop rows
    const rows = document.querySelectorAll('#item-table .data-row');
    let dCount = 0;
    rows.forEach(r => {
        const match = r.textContent.toLowerCase().includes(term);
        r.style.display = match ? '' : 'none';
        if (match) dCount++;
    });
    document.getElementById('empty-state-desktop').style.display = dCount === 0 ? 'block' : 'none';

    // Mobile cards
    const cards = document.querySelectorAll('#mobile-list .mobile-card');
    let mCount = 0;
    cards.forEach(c => {
        const match = c.textContent.toLowerCase().includes(term);
        c.style.display = match ? '' : 'none';
        if (match) mCount++;
    });
    document.getElementById('empty-state-mobile').style.display = mCount === 0 ? 'block' : 'none';
}

// Close modal on background click
window.onclick = e => { if (e.target.classList.contains('modal')) e.target.classList.remove('show'); };
</script>
</body>
</html>