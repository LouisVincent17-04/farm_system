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
                      DATE_FORMAT(i.EXPIRATION_DATE, '%m/%d/%Y') as EXPIRATION_DATE_FMT,
                      DATE_FORMAT(i.CREATED_AT, '%m/%d/%Y %h:%i %p') as CREATED_AT_FMT
                      FROM ITEMS i
                      LEFT JOIN ITEM_TYPES it ON i.ITEM_TYPE_ID = it.ITEM_TYPE_ID
                      LEFT JOIN UNITS u ON i.UNIT_ID = u.UNIT_ID
                      WHERE i.ITEM_TYPE_ID = :type_id AND LOCATION_ID = :location_id
                      ORDER BY i.CREATED_AT DESC";
        $stmt = $conn->prepare($items_sql);
        $stmt->execute([':type_id' => $ITEM_TYPE_ID, ':location_id' => $USER_LOCATION_]);
        $items_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $conn->prepare("SELECT * FROM UNITS WHERE UNIT_NAME = 'kilograms'");
        $stmt->execute(); $units = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $conn->prepare("SELECT * FROM LOCATIONS WHERE LOCATION_ID = :lid ORDER BY LOCATION_NAME ASC");
        $stmt->execute([':lid' => $USER_LOCATION_]); $locations = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $conn->prepare("SELECT * FROM BUILDINGS ORDER BY BUILDING_NAME ASC");
        $stmt->execute(); $buildings_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $conn->prepare("SELECT * FROM PENS ORDER BY PEN_NAME ASC");
        $stmt->execute(); $pens_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } else {
        $items_sql = "SELECT i.*, it.ITEM_TYPE_NAME, u.UNIT_NAME,
                      DATE_FORMAT(i.DATE_OF_PURCHASE, '%m/%d/%Y') as DATE_OF_PURCHASE_FMT,
                      DATE_FORMAT(i.EXPIRATION_DATE, '%m/%d/%Y') as EXPIRATION_DATE_FMT,
                      DATE_FORMAT(i.CREATED_AT, '%m/%d/%Y %h:%i %p') as CREATED_AT_FMT
                      FROM ITEMS i
                      LEFT JOIN ITEM_TYPES it ON i.ITEM_TYPE_ID = it.ITEM_TYPE_ID
                      LEFT JOIN UNITS u ON i.UNIT_ID = u.UNIT_ID
                      WHERE i.ITEM_TYPE_ID = :type_id
                      ORDER BY i.CREATED_AT DESC";
        $stmt = $conn->prepare($items_sql);
        $stmt->execute([':type_id' => $ITEM_TYPE_ID]);
        $items_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $conn->prepare("SELECT * FROM UNITS WHERE UNIT_NAME = 'kilograms'");
        $stmt->execute(); $units = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $conn->prepare("SELECT * FROM LOCATIONS ORDER BY LOCATION_NAME ASC");
        $stmt->execute(); $locations = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $conn->prepare("SELECT * FROM BUILDINGS ORDER BY BUILDING_NAME ASC");
        $stmt->execute(); $buildings_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $conn->prepare("SELECT * FROM PENS ORDER BY PEN_NAME ASC");
        $stmt->execute(); $pens_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    $total_items     = count($items_data);
    $confirmed_count = 0; $pending_count = 0; $total_value = 0;
    foreach ($items_data as $it) {
        if ((int)($it['STATUS'] ?? 0) === 1) $confirmed_count++;
        else $pending_count++;
        $total_value += $it['TOTAL_COST'] ?? ($it['QUANTITY'] * $it['UNIT_COST']);
    }

} catch (Exception $e) {
    $items_data = []; $units = []; $locations = [];
    $buildings_raw = []; $pens_raw = [];
    $total_items = $confirmed_count = $pending_count = $total_value = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Feed Purchase Management | FarmPro</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/dark.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css"/>

    <style>
        /* ─── CSS VARIABLES — identical token set to audit_log_report ─── */
        :root {
            --bg-base:        #080f1a;
            --bg-surface:     #0d1829;
            --bg-elevated:    #111f35;
            --bg-hover:       #162540;
            --border:         rgba(255,255,255,0.07);
            --border-active:  rgba(148,163,184,0.5);
            --slate:          #94a3b8;
            --slate-dim:      rgba(148,163,184,0.12);
            --slate-glow:     rgba(148,163,184,0.25);
            --blue:           #3b82f6;
            --blue-dim:       rgba(59,130,246,0.15);
            --green:          #22c55e;
            --green-dim:      rgba(34,197,94,0.15);
            --red:            #f87171;
            --red-dim:        rgba(248,113,113,0.15);
            --amber:          #f59e0b;
            --amber-dim:      rgba(245,158,11,0.15);
            --purple:         #a855f7;
            --purple-dim:     rgba(168,85,247,0.15);
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

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: var(--font);
            background: var(--bg-base);
            color: var(--text-primary);
            min-height: 100vh;
            padding-bottom: 80px;
            background-image: radial-gradient(ellipse 80% 50% at 50% -20%, rgba(148,163,184,0.05) 0%, transparent 60%);
        }

        .container { max-width: 1560px; margin: 0 auto; padding: 2rem 1.5rem; }

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
            color: var(--text-secondary); background: var(--slate-dim);
            border: 1px solid rgba(148,163,184,0.2); padding: 6px 12px; border-radius: 99px;
        }

        /* ─── PAGE HEADER ─── */
        .page-header { margin-bottom: 2rem; }
        .page-title {
            font-size: clamp(1.6rem, 3vw, 2.2rem); font-weight: 700;
            color: var(--text-primary); letter-spacing: -0.03em; line-height: 1.1; margin-bottom: 0.25rem;
        }
        .page-title span {
            background: linear-gradient(135deg, var(--text-primary), var(--text-secondary));
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }
        .page-subtitle { color: var(--text-secondary); font-size: 0.95rem; }

        /* ─── STAT CARDS ─── */
        .stats-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
            gap: 1rem; margin-bottom: 1.5rem;
        }
        .stat-card {
            background: var(--bg-surface); border: 1px solid var(--border);
            border-radius: var(--radius-lg); padding: 1.25rem 1.5rem;
            position: relative; overflow: hidden;
            transition: border-color var(--transition), transform var(--transition);
        }
        .stat-card::before { content: ''; position: absolute; inset: 0; opacity: 0; transition: opacity var(--transition); }
        .stat-card:hover { transform: translateY(-1px); }
        .stat-card:hover::before { opacity: 1; }
        .stat-card.slate { border-color: rgba(148,163,184,0.15); }
        .stat-card.slate::before { background: linear-gradient(135deg, rgba(148,163,184,0.04), transparent); }
        .stat-card.blue  { border-color: rgba(59,130,246,0.15); }
        .stat-card.blue::before  { background: linear-gradient(135deg, rgba(59,130,246,0.04), transparent); }
        .stat-card.green { border-color: rgba(34,197,94,0.15); }
        .stat-card.green::before { background: linear-gradient(135deg, rgba(34,197,94,0.04), transparent); }
        .stat-card.amber { border-color: rgba(245,158,11,0.15); }
        .stat-card.amber::before { background: linear-gradient(135deg, rgba(245,158,11,0.04), transparent); }

        .stat-icon {
            width: 32px; height: 32px; border-radius: var(--radius-sm);
            display: flex; align-items: center; justify-content: center;
            font-size: 0.85rem; margin-bottom: 0.75rem;
        }
        .stat-icon.slate { background: var(--slate-dim); color: var(--slate); }
        .stat-icon.blue  { background: var(--blue-dim);  color: var(--blue); }
        .stat-icon.green { background: var(--green-dim); color: var(--green); }
        .stat-icon.amber { background: var(--amber-dim); color: var(--amber); }

        .stat-val {
            font-size: 1.5rem; font-weight: 700; letter-spacing: -0.03em;
            line-height: 1; margin-bottom: 4px; font-family: var(--font-mono);
        }
        .stat-val.slate { color: var(--text-primary); }
        .stat-val.blue  { color: var(--blue); }
        .stat-val.green { color: var(--green); }
        .stat-val.amber { color: var(--amber); }
        .stat-lbl { font-size: 0.72rem; color: var(--text-muted); font-weight: 500; text-transform: uppercase; letter-spacing: 0.05em; }

        /* ─── ACTION BAR ─── */
        .action-bar {
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 1.25rem; flex-wrap: wrap; gap: 10px;
        }
        .action-bar-left { display: flex; gap: 8px; flex-wrap: wrap; }
        .search-wrap { position: relative; flex: 1; min-width: 220px; max-width: 400px; }
        .search-wrap i { position: absolute; left: 13px; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 0.8rem; pointer-events: none; }
        .search-input {
            width: 100%; height: 38px; padding: 0 12px 0 36px;
            background: var(--bg-elevated); border: 1px solid var(--border);
            border-radius: var(--radius-md); color: var(--text-primary);
            font-size: 0.875rem; font-family: var(--font); outline: none;
            transition: border-color var(--transition), box-shadow var(--transition);
        }
        .search-input:focus { border-color: var(--border-active); box-shadow: 0 0 0 3px var(--slate-glow); background: var(--bg-hover); }
        .search-input::placeholder { color: var(--text-muted); }

        /* ─── BUTTONS ─── */
        .btn {
            display: inline-flex; align-items: center; justify-content: center; gap: 7px;
            padding: 0 16px; height: 38px; border-radius: var(--radius-md);
            font-size: 0.8rem; font-weight: 600; font-family: var(--font);
            border: 1px solid transparent; cursor: pointer; transition: all var(--transition);
            text-decoration: none; white-space: nowrap; letter-spacing: 0.01em;
        }
        .btn-primary { background: var(--slate); color: #000; }
        .btn-primary:hover { background: #cbd5e1; box-shadow: 0 0 16px var(--slate-glow); }
        .btn-blue { background: var(--blue); color: #fff; }
        .btn-blue:hover { background: #2563eb; box-shadow: 0 0 14px rgba(59,130,246,0.4); }
        .btn-amber { background: var(--amber); color: #000; }
        .btn-amber:hover { background: #d97706; box-shadow: 0 0 14px rgba(245,158,11,0.35); }
        .btn-ghost { background: transparent; color: var(--text-secondary); border-color: var(--border); }
        .btn-ghost:hover { background: var(--bg-elevated); color: var(--text-primary); border-color: rgba(255,255,255,0.15); }
        .btn-sm { height: 32px; padding: 0 12px; font-size: 0.75rem; }

        /* ─── TABLE CARD ─── */
        .table-card {
            background: var(--bg-surface); border: 1px solid var(--border);
            border-radius: var(--radius-xl); overflow: hidden;
        }
        .table-wrap { overflow-x: auto; }
        .table-wrap::-webkit-scrollbar { height: 6px; }
        .table-wrap::-webkit-scrollbar-track { background: var(--bg-base); }
        .table-wrap::-webkit-scrollbar-thumb { background: var(--bg-hover); border-radius: 3px; }

        table { width: 100%; border-collapse: collapse; min-width: 1060px; }
        thead th {
            background: var(--bg-elevated); color: var(--text-muted);
            font-size: 0.7rem; font-weight: 700; text-transform: uppercase;
            letter-spacing: 0.07em; padding: 12px 16px; text-align: left;
            border-bottom: 1px solid var(--border); white-space: nowrap;
        }
        tbody tr { border-bottom: 1px solid var(--border); transition: background var(--transition); }
        tbody tr:last-child { border-bottom: none; }
        tbody tr:hover { background: rgba(255,255,255,0.02); }
        td { padding: 12px 16px; font-size: 0.85rem; color: var(--text-primary); vertical-align: middle; white-space: nowrap; }

        .td-ref   { font-family: var(--font-mono); font-size: 0.8rem; color: var(--blue); font-weight: 600; }
        .td-name  { font-weight: 600; color: var(--text-primary); }
        .td-muted { color: var(--text-secondary); font-size: 0.82rem; }
        .td-cost  { font-family: var(--font-mono); color: var(--green); font-weight: 600; }
        .td-total { font-family: var(--font-mono); color: #4ade80; font-weight: 700; }
        .td-expiry{ color: var(--red); font-family: var(--font-mono); font-size: 0.82rem; }
        .td-date  { font-family: var(--font-mono); font-size: 0.82rem; color: var(--text-secondary); }

        .badge {
            display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px;
            border-radius: var(--radius-sm); font-size: 0.68rem; font-weight: 700; letter-spacing: 0.03em;
        }
        .b-consumable    { background: var(--green-dim); color: var(--green); border: 1px solid rgba(34,197,94,0.2); }
        .b-nonconsumable { background: var(--blue-dim);  color: var(--blue);  border: 1px solid rgba(59,130,246,0.2); }

        .status-locked {
            display: block; padding: 4px 10px; text-align: center;
            background: var(--green-dim); color: var(--green);
            border: 1px solid rgba(34,197,94,0.2); border-radius: var(--radius-sm);
            font-size: 0.68rem; font-weight: 700; letter-spacing: 0.05em; text-transform: uppercase;
        }
        .confirm-btn {
            display: block; width: 100%; padding: 5px 10px;
            background: var(--red-dim); color: var(--red);
            border: 1px solid rgba(248,113,113,0.25); border-radius: var(--radius-sm);
            font-size: 0.68rem; font-weight: 700; letter-spacing: 0.05em; text-transform: uppercase;
            cursor: pointer; transition: all var(--transition); font-family: var(--font);
        }
        .confirm-btn:hover { background: rgba(248,113,113,0.25); }

        .actions { display: flex; align-items: center; justify-content: center; gap: 5px; }
        .action-btn {
            width: 30px; height: 30px; display: flex; align-items: center; justify-content: center;
            border-radius: var(--radius-sm); border: 1px solid var(--border);
            background: var(--bg-elevated); cursor: pointer; transition: all var(--transition);
            font-size: 0.75rem;
        }
        .action-btn.view   { color: var(--blue); }
        .action-btn.view:hover   { background: var(--blue-dim);   border-color: rgba(59,130,246,0.35); }
        .action-btn.edit   { color: var(--purple); }
        .action-btn.edit:hover   { background: var(--purple-dim); border-color: rgba(168,85,247,0.35); }
        .action-btn.delete { color: var(--red); }
        .action-btn.delete:hover { background: var(--red-dim);    border-color: rgba(248,113,113,0.35); }

        .empty-state { text-align: center; padding: 4rem 2rem; color: var(--text-muted); }
        .empty-state i { font-size: 2.5rem; opacity: 0.3; margin-bottom: 1rem; display: block; }

        /* ─── MODALS ─── */
        .modal {
            display: none; position: fixed; inset: 0;
            background: rgba(0,0,0,0.75); backdrop-filter: blur(6px);
            z-index: 1000; align-items: center; justify-content: center; padding: 1rem;
        }
        .modal.show { display: flex; }
        .modal-content {
            background: var(--bg-surface); border-radius: var(--radius-xl);
            width: 100%; max-width: 920px; max-height: 92vh;
            display: flex; flex-direction: column;
            border: 1px solid rgba(255,255,255,0.1);
            box-shadow: 0 32px 64px rgba(0,0,0,0.6);
            animation: modalIn 0.22s ease;
        }
        @keyframes modalIn { from { transform: translateY(14px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }

        .modal-header {
            padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border);
            display: flex; align-items: center; justify-content: space-between; flex-shrink: 0;
        }
        .modal-header h2 { font-size: 1.1rem; font-weight: 700; color: var(--text-primary); margin: 0; letter-spacing: -0.02em; }
        .modal-close {
            width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;
            background: var(--bg-elevated); border: 1px solid var(--border);
            border-radius: var(--radius-sm); cursor: pointer; color: var(--text-muted);
            transition: all var(--transition); font-size: 0.8rem;
        }
        .modal-close:hover { background: var(--red-dim); color: var(--red); border-color: rgba(248,113,113,0.3); }
        .modal-body { padding: 1.5rem; overflow-y: auto; flex: 1; }
        .modal-footer {
            padding: 1rem 1.5rem; border-top: 1px solid var(--border);
            display: flex; justify-content: flex-end; gap: 8px; flex-shrink: 0;
        }

        /* ─── FORM CONTROLS ─── */
        .section-label {
            font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em;
            color: var(--text-secondary); margin-bottom: 1rem; margin-top: 1.25rem;
            padding-bottom: 8px; border-bottom: 1px solid var(--border);
            display: flex; align-items: center; gap: 7px;
        }
        .section-label:first-child { margin-top: 0; }
        .form-row   { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        .form-row-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem; }
        .form-group { display: flex; flex-direction: column; gap: 6px; margin-bottom: 1rem; }
        .form-group:last-child { margin-bottom: 0; }

        .form-label {
            font-size: 0.72rem; font-weight: 600; text-transform: uppercase;
            letter-spacing: 0.06em; color: var(--text-secondary); display: flex; align-items: center; gap: 5px;
        }
        .form-label .req { color: var(--red); }

        .form-control {
            width: 100%; padding: 0 12px; height: 40px;
            background: var(--bg-elevated); border: 1px solid var(--border);
            color: var(--text-primary); border-radius: var(--radius-md);
            font-size: 0.875rem; font-family: var(--font);
            outline: none; transition: border-color var(--transition), box-shadow var(--transition);
            appearance: none; -webkit-appearance: none;
        }
        select.form-control {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2394a3b8' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
            background-repeat: no-repeat; background-position: right 12px center; padding-right: 36px; cursor: pointer;
        }
        textarea.form-control { height: auto; padding: 10px 12px; resize: vertical; min-height: 70px; line-height: 1.5; }
        .form-control:focus { border-color: var(--border-active); box-shadow: 0 0 0 3px var(--slate-glow); background: var(--bg-hover); }
        .form-control::placeholder { color: var(--text-muted); }
        .form-control option { background: #1e293b; color: var(--text-primary); }
        .form-control:disabled { opacity: 0.45; cursor: not-allowed; }

        /* ─── AUTOCOMPLETE ─── */
        .autocomplete-wrapper { position: relative; }
        .autocomplete-list {
            position: absolute; z-index: 9999; top: calc(100% + 2px); left: 0; right: 0;
            background: var(--bg-elevated); border: 1px solid var(--border-active);
            border-radius: var(--radius-md); max-height: 200px; overflow-y: auto;
            box-shadow: 0 16px 32px rgba(0,0,0,0.5); display: none;
        }
        .autocomplete-list.show { display: block; }
        .autocomplete-item {
            padding: 9px 12px; cursor: pointer; color: var(--text-secondary);
            font-size: 0.875rem; border-bottom: 1px solid var(--border);
            transition: background var(--transition);
        }
        .autocomplete-item:last-child { border-bottom: none; }
        .autocomplete-item:hover { background: var(--bg-hover); color: var(--text-primary); }
        .autocomplete-item strong { color: var(--blue); font-weight: 700; }
        .autocomplete-loading, .autocomplete-no-results { padding: 10px 12px; color: var(--text-muted); font-size: 0.8rem; text-align: center; }

        /* ─── DYNAMIC TABLE ─── */
        .dynamic-table-wrap { overflow-x: auto; border-radius: var(--radius-md); border: 1px solid var(--border); margin-bottom: 10px; }
        .dynamic-table { width: 100%; border-collapse: collapse; min-width: 680px; }
        .dynamic-table th {
            background: var(--bg-elevated); color: var(--text-muted);
            font-size: 0.68rem; text-transform: uppercase; letter-spacing: 0.07em;
            padding: 10px; text-align: left; white-space: nowrap;
            border-bottom: 1px solid var(--border);
        }
        .dynamic-table td { padding: 7px 8px; border-bottom: 1px solid var(--border); vertical-align: top; }
        .dynamic-table tr:last-child td { border-bottom: none; }
        .dynamic-table .form-control { height: 36px; font-size: 0.82rem; }

        .add-row-btn {
            width: 100%; padding: 9px; margin-top: 10px;
            background: var(--green-dim); border: 1px dashed rgba(34,197,94,0.35);
            border-radius: var(--radius-md); color: var(--green);
            font-size: 0.82rem; font-weight: 600; font-family: var(--font);
            cursor: pointer; transition: all var(--transition);
        }
        .add-row-btn:hover { background: rgba(34,197,94,0.22); }

        .del-row-btn {
            width: 28px; height: 28px; display: flex; align-items: center; justify-content: center;
            background: var(--red-dim); border: 1px solid rgba(248,113,113,0.25);
            border-radius: var(--radius-sm); color: var(--red); cursor: pointer;
            transition: all var(--transition); font-size: 0.8rem;
        }
        .del-row-btn:hover { background: rgba(248,113,113,0.25); }

        /* ─── ALERT ─── */
        .alert {
            padding: 0.875rem 1rem; border-radius: var(--radius-md);
            margin-bottom: 1rem; font-size: 0.875rem; font-weight: 600;
            text-align: center; display: none;
        }
        .alert.success { background: var(--green-dim); border: 1px solid rgba(34,197,94,0.3); color: var(--green); }
        .alert.error   { background: var(--red-dim);   border: 1px solid rgba(248,113,113,0.3); color: var(--red); }

        /* ─── CONFIRM MODAL ─── */
        .confirm-body { text-align: center; padding: 2rem 1.5rem 1rem; }
        .confirm-icon { font-size: 2.5rem; display: block; margin-bottom: 1rem; }
        .confirm-body h2 { font-size: 1.15rem; font-weight: 700; color: var(--text-primary); margin-bottom: 0.5rem; letter-spacing: -0.02em; }
        .confirm-body p  { color: var(--text-secondary); font-size: 0.9rem; line-height: 1.5; margin-bottom: 1rem; }
        .confirm-warning {
            display: inline-block; padding: 0.6rem 1rem;
            background: var(--amber-dim); border: 1px solid rgba(245,158,11,0.25);
            border-radius: var(--radius-md); color: var(--amber); font-size: 0.8rem; font-weight: 600;
        }

        /* ─── VIEW MODAL ─── */
        .view-section { margin-bottom: 1.5rem; }
        .view-section-title {
            font-size: 0.7rem; font-weight: 700; text-transform: uppercase;
            letter-spacing: 0.08em; color: var(--text-secondary); margin-bottom: 0.75rem;
            padding-bottom: 7px; border-bottom: 1px solid var(--border);
            display: flex; align-items: center; gap: 7px;
        }
        .view-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; }
        .view-item { display: flex; flex-direction: column; gap: 3px; }
        .view-item .vl { font-size: 0.68rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; }
        .view-item .vv { font-size: 0.875rem; color: var(--text-primary); font-weight: 500; }
        .view-item .vv.mono   { font-family: var(--font-mono); color: var(--green); }
        .view-item .vv.danger { color: var(--red); }

        /* ─── RESPONSIVE ─── */
        @media (max-width: 768px) {
            .container { padding: 1rem; }
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .action-bar { flex-direction: column; align-items: stretch; }
            .action-bar-left .btn { flex: 1; justify-content: center; }
            .search-wrap { max-width: 100%; }
            .form-row, .form-row-3 { grid-template-columns: 1fr; }
            .view-grid { grid-template-columns: 1fr; }

            .table-card { background: transparent; border: none; }
            .table-wrap { overflow: visible; }
            table { min-width: 0; display: block; }
            thead { display: none; }
            tbody { display: block; }
            tbody tr {
                display: block; background: var(--bg-surface); border: 1px solid var(--border);
                border-radius: var(--radius-lg); margin-bottom: 0.75rem; padding: 1.25rem; box-shadow: var(--shadow-sm);
            }
            td {
                display: flex; justify-content: space-between; align-items: flex-start;
                gap: 1rem; padding: 7px 0; border-bottom: 1px solid rgba(255,255,255,0.04);
                white-space: normal; text-align: right;
            }
            td:last-child { border-bottom: none; }
            td::before {
                content: attr(data-label); font-size: 0.68rem; font-weight: 700;
                text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted);
                white-space: nowrap; flex-shrink: 0; padding-top: 2px; text-align: left;
            }
        }
    </style>
</head>
<body>
<div class="container">

    <div class="top-bar">
        <a href="purchase_dashboard.php" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back to Purchase Dashboard
        </a>
        <span class="page-badge"><i class="fa-solid fa-wheat-awn"></i> Purchases</span>
    </div>

    <div class="page-header">
        <h1 class="page-title">Feed <span>Purchase Management</span></h1>
        <p class="page-subtitle">Track and manage feed & feeding supply purchase records.</p>
    </div>

    <div class="stats-grid">
        <div class="stat-card slate">
            <div class="stat-icon slate"><i class="fa-solid fa-boxes-stacked"></i></div>
            <div class="stat-val slate"><?= number_format($total_items) ?></div>
            <div class="stat-lbl">Total Records</div>
        </div>
        <div class="stat-card green">
            <div class="stat-icon green"><i class="fa-solid fa-lock"></i></div>
            <div class="stat-val green"><?= number_format($confirmed_count) ?></div>
            <div class="stat-lbl">Confirmed & Locked</div>
        </div>
        <div class="stat-card amber">
            <div class="stat-icon amber"><i class="fa-solid fa-clock"></i></div>
            <div class="stat-val amber"><?= number_format($pending_count) ?></div>
            <div class="stat-lbl">Pending Confirmation</div>
        </div>
        <div class="stat-card blue">
            <div class="stat-icon blue"><i class="fa-solid fa-peso-sign"></i></div>
            <div class="stat-val blue">₱<?= number_format($total_value, 0) ?></div>
            <div class="stat-lbl">Total Purchase Value</div>
        </div>
    </div>

    <div class="action-bar">
        <div class="action-bar-left">
            <button class="btn btn-amber" onclick="openConfirmAllModal()">
                <i class="fa-solid fa-circle-check"></i> Confirm All Pending
            </button>
            <button class="btn btn-blue" onclick="openAddModal()">
                <i class="fa-solid fa-plus"></i> Add Batch Purchase
            </button>
        </div>
        <div class="search-wrap">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" class="search-input" id="searchInput" placeholder="Search name, supplier, category…" onkeyup="filterTable()">
        </div>
    </div>

    <div class="table-card">
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Ref No</th>
                        <th>Supplier</th>
                        <th>Feed Name</th>
                        <th>Qty</th>
                        <th>Unit</th>
                        <th>Net Wt.</th>
                        <th>Unit Cost</th>
                        <th>Total Cost</th>
                        <th>Category</th>
                        <th>Purchase Date</th>
                        <th>Expiry</th>
                        <th style="text-align:center; min-width:100px;">Status</th>
                        <th style="text-align:center; min-width:95px;">Actions</th>
                    </tr>
                </thead>
                <tbody id="item-table">
                    <?php
                    $categoryLabels = [0 => 'Non-Consumable', 1 => 'Consumable'];
                    $categoryBadges = [0 => 'b-nonconsumable', 1 => 'b-consumable'];

                    if (empty($items_data)): ?>
                        <tr><td colspan="13">
                            <div class="empty-state">
                                <i class="fa-solid fa-wheat-awn"></i>
                                No purchases recorded yet.
                            </div>
                        </td></tr>
                    <?php else: ?>
                        <?php foreach ($items_data as $item):
                            $status      = isset($item['STATUS']) ? (int)$item['STATUS'] : 0;
                            $isConfirmed = ($status === 1);
                            $totalCost   = $item['TOTAL_COST'] ?? ($item['QUANTITY'] * $item['UNIT_COST']);
                        ?>
                        <tr
                            data-item-id="<?= $item['ITEM_ID'] ?>"
                            data-item-name="<?= htmlspecialchars($item['ITEM_NAME']) ?>"
                            data-item-desc="<?= htmlspecialchars($item['ITEM_DESCRIPTION'] ?? '') ?>"
                            data-unit-id="<?= $item['UNIT_ID'] ?>"
                            data-unit-cost="<?= $item['UNIT_COST'] ?>"
                            data-item-category="<?= $item['ITEM_CATEGORY'] ?>"
                            data-unit-name="<?= htmlspecialchars($item['UNIT_NAME']) ?>"
                            data-net-weight="<?= $item['ITEM_NET_WEIGHT'] ?? '0' ?>"
                            data-quantity="<?= $item['QUANTITY'] ?? '0' ?>"
                            data-purchase-date-raw="<?= htmlspecialchars($item['DATE_OF_PURCHASE'] ?? '') ?>"
                            data-purchase-date-fmt="<?= htmlspecialchars($item['DATE_OF_PURCHASE_FMT'] ?? '') ?>"
                            data-expiration-date-raw="<?= htmlspecialchars($item['EXPIRATION_DATE'] ?? '') ?>"
                            data-expiration-date-fmt="<?= htmlspecialchars($item['EXPIRATION_DATE_FMT'] ?? '') ?>"
                            data-location-id="<?= $item['LOCATION_ID'] ?? '' ?>"
                            data-building-id="<?= $item['BUILDING_ID'] ?? '' ?>"
                            data-pen-id="<?= $item['PEN_ID'] ?? '' ?>"
                            data-supplier="<?= htmlspecialchars($item['SUPPLIER'] ?? '') ?>"
                            data-reference-no="<?= htmlspecialchars($item['REFERENCE_NO'] ?? '') ?>"
                            data-created-at="<?= htmlspecialchars($item['CREATED_AT_FMT'] ?? '') ?>">

                            <td data-label="Ref No"><span class="td-ref"><?= !empty($item['REFERENCE_NO']) ? htmlspecialchars($item['REFERENCE_NO']) : '—' ?></span></td>
                            <td data-label="Supplier"><span class="td-muted"><?= !empty($item['SUPPLIER']) ? htmlspecialchars($item['SUPPLIER']) : 'General Supplier' ?></span></td>
                            <td data-label="Feed Name"><span class="td-name"><?= htmlspecialchars($item['ITEM_NAME']) ?></span></td>
                            <td data-label="Qty"><?= number_format($item['QUANTITY'] ?? 0, 2) ?></td>
                            <td data-label="Unit"><span class="td-muted"><?= htmlspecialchars($item['UNIT_NAME']) ?></span></td>
                            <td data-label="Net Wt."><span class="td-muted"><?= htmlspecialchars($item['ITEM_NET_WEIGHT'] ?? 'N/A') ?></span></td>
                            <td data-label="Unit Cost"><span class="td-cost">₱<?= number_format($item['UNIT_COST'], 2) ?></span></td>
                            <td data-label="Total Cost"><span class="td-total">₱<?= number_format($totalCost, 2) ?></span></td>
                            <td data-label="Category"><span class="badge <?= $categoryBadges[$item['ITEM_CATEGORY']] ?>"><?= $categoryLabels[$item['ITEM_CATEGORY']] ?></span></td>
                            <td data-label="Purchase Date"><span class="td-date"><?= htmlspecialchars($item['DATE_OF_PURCHASE_FMT'] ?? 'N/A') ?></span></td>
                            <td data-label="Expiry"><span class="td-expiry"><?= htmlspecialchars($item['EXPIRATION_DATE_FMT'] ?? 'N/A') ?></span></td>
                            <td data-label="Status" style="text-align:center;">
                                <?php if (!$isConfirmed): ?>
                                    <button class="confirm-btn" onclick="openConfirmModal(this)">Confirm</button>
                                <?php else: ?>
                                    <span class="status-locked">Locked</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Actions">
                                <div class="actions">
                                    <button class="action-btn view" onclick="viewItem(this)" title="View">
                                        <i class="fa-solid fa-eye"></i>
                                    </button>
                                    <?php if (!$isConfirmed): ?>
                                        <button class="action-btn edit" onclick="editItem(this)" title="Edit">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </button>
                                        <button class="action-btn delete" onclick="deleteItem(this)" title="Delete">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div id="empty-search-state" style="display:none; text-align:center; padding:2.5rem; color:var(--text-muted); font-size:0.9rem;">
            No records match your search.
        </div>
    </div>

</div>

<!-- ════════════ ADD MODAL ════════════ -->
<div id="addModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fa-solid fa-plus" style="color:var(--blue); margin-right:8px; font-size:0.9rem;"></i>Add Batch Feed Purchase</h2>
            <button class="modal-close" onclick="closeAddModal()"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body">
            <div id="add-modal-alert" class="alert"></div>
            <form id="add-batch-form" onsubmit="saveAddBatch(event)">

                <div class="section-label"><i class="fa-solid fa-file-invoice"></i> Invoice Details (applies to all items)</div>

                <div class="form-row">
                    <div class="form-group autocomplete-wrapper">
                        <label class="form-label"><i class="fa-solid fa-truck"></i> Supplier</label>
                        <input type="text" id="batch-supplier" class="form-control" placeholder="e.g., B-Meg Premium" autocomplete="off">
                        <div id="add-supplier-list" class="autocomplete-list"></div>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><i class="fa-solid fa-hashtag"></i> Reference No.</label>
                        <input type="text" id="batch-reference-no" class="form-control" placeholder="e.g., OR-12345">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label"><i class="fa-solid fa-calendar-days"></i> Date of Purchase <span class="req">*</span></label>
                        <input type="text" id="batch-purchase-date" class="form-control date-picker" placeholder="Select date" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><i class="fa-solid fa-location-dot"></i> Delivery Location <span class="req">*</span></label>
                        <select id="batch-location" class="form-control" onchange="filterBuildings('batch')" required
                            <?php echo ($USER_LOCATION_ != 1000) ? 'style="pointer-events:none;" disabled' : ''; ?>>
                            <?php if ($USER_LOCATION_ == 1000): ?><option value="">Select Location</option><?php endif; ?>
                            <?php foreach ($locations as $loc): ?>
                                <option value="<?= $loc['LOCATION_ID'] ?>"
                                    <?= ($USER_LOCATION_ != 1000 && $loc['LOCATION_ID'] == $USER_LOCATION_) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($loc['LOCATION_NAME']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label"><i class="fa-solid fa-building"></i> Building (Optional)</label>
                        <select id="batch-building" class="form-control" onchange="filterPens('batch')" disabled>
                            <option value="">Select Location First</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><i class="fa-solid fa-border-all"></i> Pen (Optional)</label>
                        <select id="batch-pen" class="form-control" disabled>
                            <option value="">Select Building First</option>
                        </select>
                    </div>
                </div>

                <div class="section-label" style="margin-top:1.5rem;"><i class="fa-solid fa-list-ul"></i> Feed Items</div>

                <div class="dynamic-table-wrap">
                    <table class="dynamic-table">
                        <thead>
                            <tr>
                                <th style="min-width:155px;">Feed Name <span style="color:var(--red);">*</span></th>
                                <th style="min-width:115px;">Category</th>
                                <th style="min-width:85px;">Net Wt (kg)</th>
                                <th style="min-width:105px;">Unit <span style="color:var(--red);">*</span></th>
                                <th style="min-width:75px;">Qty <span style="color:var(--red);">*</span></th>
                                <th style="min-width:95px;">Cost/Unit <span style="color:var(--red);">*</span></th>
                                <th style="min-width:130px;">Expiry Date</th>
                                <th style="width:38px;"></th>
                            </tr>
                        </thead>
                        <tbody id="dynamic-feed-body"></tbody>
                    </table>
                </div>
                <button type="button" class="add-row-btn" onclick="addFeedRow()">
                    <i class="fa-solid fa-plus"></i> Add Feed Item Row
                </button>

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

<!-- ════════════ EDIT MODAL ════════════ -->
<div id="editModal" class="modal">
    <div class="modal-content" style="max-width:700px;">
        <div class="modal-header">
            <h2><i class="fa-solid fa-pen-to-square" style="color:var(--purple); margin-right:8px; font-size:0.9rem;"></i>Edit Feed Purchase</h2>
            <button class="modal-close" onclick="closeEditModal()"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body">
            <div id="edit-modal-alert" class="alert"></div>
            <form id="edit-item-form" onsubmit="saveEditItem(event)">
                <input type="hidden" id="edit-item-id" name="item_id">
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
                        <label class="form-label">Net Weight (kg) per sack</label>
                        <input type="number" id="edit-net-weight" name="item_net_weight" class="form-control" step="0.01" min="0" placeholder="0.00">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Base Unit <span class="req">*</span></label>
                        <select id="edit-unit" name="unit_id" class="form-control" required>
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
                        <select id="edit-item-category" name="item_category" class="form-control" required>
                            <option value="0">Non-Consumable</option>
                            <option value="1">Consumable</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Unit Cost (₱) per Sack <span class="req">*</span></label>
                        <input type="number" id="edit-unit-cost" name="unit_cost" class="form-control" step="0.01" min="0" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Description</label>
                        <textarea id="edit-item-desc" name="item_description" class="form-control" rows="2" maxlength="500" style="margin-bottom: 15px;"></textarea>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label"><i class="fa-solid fa-calendar-days"></i> Date of Purchase <span class="req">*</span></label>
                        <input type="text" id="edit-purchase-date" name="date_of_purchase" class="form-control date-picker" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><i class="fa-solid fa-calendar-xmark"></i> Expiration Date</label>
                        <input type="text" id="edit-expiration-date" name="expiration_date" class="form-control date-picker">
                    </div>
                </div>

                <div class="section-label" style="margin-top:1.25rem;"><i class="fa-solid fa-location-dot"></i> Location</div>
                <div class="form-row-3">
                    <div class="form-group">
                        <label class="form-label">Location <span class="req">*</span></label>
                        <select id="edit-location" name="location_id" class="form-control" onchange="filterBuildings('edit')" required
                            <?php echo ($USER_LOCATION_ != 1000) ? 'style="pointer-events:none;" disabled' : ''; ?>>
                            <?php if ($USER_LOCATION_ == 1000): ?><option value="">Select Location</option><?php endif; ?>
                            <?php foreach ($locations as $loc): ?>
                                <option value="<?= $loc['LOCATION_ID'] ?>"
                                    <?= ($USER_LOCATION_ != 1000 && $loc['LOCATION_ID'] == $USER_LOCATION_) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($loc['LOCATION_NAME']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Building</label>
                        <select id="edit-building" name="building_id" class="form-control" onchange="filterPens('edit')" disabled>
                            <option value="">Select Location First</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Pen</label>
                        <select id="edit-pen" name="pen_id" class="form-control" disabled>
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

<!-- ════════════ VIEW MODAL ════════════ -->
<div id="view-modal" class="modal">
    <div class="modal-content" style="max-width:560px;">
        <div class="modal-header">
            <h2><i class="fa-solid fa-eye" style="color:var(--blue); margin-right:8px; font-size:0.9rem;"></i>Purchase Details</h2>
            <button class="modal-close" onclick="closeViewModal()"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body" id="view-modal-body"></div>
        <div class="modal-footer">
            <button type="button" class="btn btn-ghost" onclick="closeViewModal()" style="width:100%;">Close</button>
        </div>
    </div>
</div>

<!-- ════════════ CONFIRM SINGLE ════════════ -->
<div id="confirm-modal" class="modal">
    <div class="modal-content" style="max-width:420px;">
        <div class="modal-body confirm-body">
            <span class="confirm-icon">🌾</span>
            <h2>Confirm Purchase?</h2>
            <p>You are about to confirm <strong><span id="confirm-item-qty"></span> × <span id="confirm-item-name" style="color:var(--green);"></span></strong>.</p>
            <div class="confirm-warning"><i class="fa-solid fa-triangle-exclamation"></i> Once confirmed, this record will be locked and cannot be edited or deleted.</div>
            <form id="confirmForm" method="POST" style="display:none;">
                <input type="hidden" id="confirm_item_id" name="item_id">
            </form>
        </div>
        <div class="modal-footer" style="justify-content:center; border-top:none; padding-top:0; padding-bottom:1.5rem;">
            <button type="button" class="btn btn-ghost" onclick="closeConfirmModal()">Cancel</button>
            <button type="button" id="btn-do-confirm"
                onclick="submitConfirmation()"
                style="background:var(--red-dim); color:var(--red); border-color:rgba(248,113,113,0.3);"
                class="btn">
                <i class="fa-solid fa-check"></i> Yes, Confirm
            </button>
        </div>
    </div>
</div>

<!-- ════════════ CONFIRM ALL ════════════ -->
<div id="confirm-all-modal" class="modal">
    <div class="modal-content" style="max-width:420px;">
        <div class="modal-body confirm-body">
            <span class="confirm-icon">📋</span>
            <h2>Confirm All Pending?</h2>
            <p>This will confirm and lock <strong>ALL</strong> currently pending feed purchases.</p>
            <div class="confirm-warning"><i class="fa-solid fa-triangle-exclamation"></i> This action cannot be undone.</div>
        </div>
        <div class="modal-footer" style="justify-content:center; border-top:none; padding-top:0; padding-bottom:1.5rem;">
            <button type="button" class="btn btn-ghost" onclick="closeConfirmAllModal()">Cancel</button>
            <button type="button" class="btn btn-amber" onclick="submitConfirmAll()">
                <i class="fa-solid fa-circle-check"></i> Confirm All
            </button>
        </div>
    </div>
</div>

<form id="deleteItemForm" method="POST" action="../process/deleteFeedAndFeedingSupplies.php" style="display:none;">
    <input type="hidden" id="delete_item_id" name="item_id">
</form>

<script>
    const allBuildings   = <?= json_encode($buildings_raw) ?>;
    const allPens        = <?= json_encode($pens_raw) ?>;
    const availableUnits = <?= json_encode($units) ?>;
    const USER_LOCATION  = <?= json_encode($USER_LOCATION_) ?>;

    let fpEditPurchase, fpEditExpiry;

    document.addEventListener('DOMContentLoaded', () => {
        fpEditPurchase = flatpickr("#edit-purchase-date",   { dateFormat: "Y-m-d", altInput: true, altFormat: "m/d/Y" });
        fpEditExpiry   = flatpickr("#edit-expiration-date", { dateFormat: "Y-m-d", altInput: true, altFormat: "m/d/Y" });
        flatpickr("#batch-purchase-date", { dateFormat: "Y-m-d", altInput: true, altFormat: "m/d/Y" });

        attachAutocomplete(document.getElementById('batch-supplier'), '../process/searchSuppliers.php?term=');
        attachAutocomplete(document.getElementById('edit-supplier'),  '../process/searchSuppliers.php?term=');
        attachAutocomplete(document.getElementById('edit-item-name'), '../process/searchFeedsAndFeedingSupplies.php?term=');

        ['addModal','editModal','view-modal','confirm-modal','confirm-all-modal'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.addEventListener('click', e => { if (e.target === el) el.classList.remove('show'); });
        });
    });

    // ── ADD ──
    function openAddModal() {
        document.getElementById('add-batch-form').reset();
        document.getElementById('dynamic-feed-body').innerHTML = '';
        document.getElementById('add-modal-alert').style.display = 'none';
        if (USER_LOCATION != 1000) {
            document.getElementById('batch-location').value = USER_LOCATION;
            filterBuildings('batch');
        }
        addFeedRow();
        document.getElementById('addModal').classList.add('show');
    }
    function closeAddModal() { document.getElementById('addModal').classList.remove('show'); }

    function addFeedRow() {
        const tbody  = document.getElementById('dynamic-feed-body');
        const tr     = document.createElement('tr');
        const unitOpts = availableUnits.map(u => `<option value="${u.UNIT_ID}">${u.UNIT_NAME}</option>`).join('');
        tr.innerHTML = `
            <td><div class="autocomplete-wrapper">
                <input type="text" class="form-control row-item-name" placeholder="Feed name" required autocomplete="off">
                <div class="autocomplete-list"></div>
            </div></td>
            <td><select class="form-control row-category" required>
                <option value="1">Consumable</option><option value="0">Non-Consumable</option>
            </select></td>
            <td><input type="number" class="form-control row-net-weight" placeholder="0" step="0.01" min="0"></td>
            <td><select class="form-control row-unit" required>${unitOpts}</select></td>
            <td><input type="number" class="form-control row-qty" placeholder="0" step="0.01" min="0" required></td>
            <td><input type="number" class="form-control row-cost" placeholder="0.00" step="0.01" min="0" required></td>
            <td><input type="text" class="form-control row-exp" placeholder="Expiry date"></td>
            <td style="vertical-align:middle; text-align:center;">
                <button type="button" class="del-row-btn" onclick="this.closest('tr').remove()" title="Remove row">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </td>`;
        tbody.appendChild(tr);
        flatpickr(tr.querySelector('.row-exp'), { dateFormat: "Y-m-d", altInput: true, altFormat: "m/d/Y" });
        attachAutocomplete(tr.querySelector('.row-item-name'), '../process/searchFeedsAndFeedingSupplies.php?term=');
    }

    function saveAddBatch(e) {
        e.preventDefault();
        const btn      = document.getElementById('btn-save-batch');
        const alertBox = document.getElementById('add-modal-alert');
        const rows     = document.querySelectorAll('#dynamic-feed-body tr');
        if (!rows.length) { showAlert(alertBox, 'error', 'Please add at least one feed item row.'); return; }

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
                expiration_date: tr.querySelector('.row-exp').value
            });
        });

        btn.disabled = true; btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving…';
        fetch('../process/addFeedAndFeedingSupplies.php', {
            method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload)
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showAlert(alertBox, 'success', data.message);
                setTimeout(() => location.reload(), 1400);
            } else {
                showAlert(alertBox, 'error', data.message);
                btn.disabled = false; btn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Save Batch Purchase';
            }
        })
        .catch(() => {
            showAlert(alertBox, 'error', 'System error occurred.');
            btn.disabled = false; btn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Save Batch Purchase';
        });
    }

    // ── EDIT ──
    function editItem(button) {
        const d = button.closest('tr').dataset;
        document.getElementById('edit-item-id').value       = d.itemId;
        document.getElementById('edit-item-name').value     = d.itemName;
        document.getElementById('edit-item-desc').value     = d.itemDesc || '';
        document.getElementById('edit-unit').value          = d.unitId;
        document.getElementById('edit-unit-cost').value     = d.unitCost;
        document.getElementById('edit-item-category').value = d.itemCategory;
        document.getElementById('edit-net-weight').value    = d.netWeight || '';
        document.getElementById('edit-item-quantity').value = d.quantity || '0';
        document.getElementById('edit-supplier').value      = d.supplier || '';
        document.getElementById('edit-reference-no').value  = d.referenceNo || '';
        fpEditPurchase.setDate(d.purchaseDateRaw || '');
        fpEditExpiry.setDate(d.expirationDateRaw || '');
        document.getElementById('edit-location').value = d.locationId || '';
        filterBuildings('edit');
        if (d.buildingId) {
            setTimeout(() => {
                document.getElementById('edit-building').value = d.buildingId;
                filterPens('edit');
                if (d.penId) setTimeout(() => { document.getElementById('edit-pen').value = d.penId; }, 0);
            }, 0);
        }
        document.getElementById('edit-modal-alert').style.display = 'none';
        document.getElementById('editModal').classList.add('show');
    }
    function closeEditModal() { document.getElementById('editModal').classList.remove('show'); }

    function saveEditItem(e) {
        e.preventDefault();
        const btn = document.getElementById('btn-save-edit');
        const alertBox = document.getElementById('edit-modal-alert');
        btn.disabled = true; btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Updating…';
        fetch('../process/editFeedAndFeedingSupplies.php', { method: 'POST', body: new FormData(document.getElementById('edit-item-form')) })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showAlert(alertBox, 'success', data.message);
                setTimeout(() => location.reload(), 1000);
            } else {
                showAlert(alertBox, 'error', data.message || 'Error updating item.');
                btn.disabled = false; btn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Update Purchase';
            }
        })
        .catch(() => {
            showAlert(alertBox, 'error', 'System error.');
            btn.disabled = false; btn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Update Purchase';
        });
    }

    // ── AUTOCOMPLETE ──
    function attachAutocomplete(input, endpoint) {
        if (!input) return;
        const list = input.nextElementSibling;
        let timer = null;
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
                    if (!data.length) { list.innerHTML = '<div class="autocomplete-no-results">No matches</div>'; return; }
                    data.forEach(item => {
                        const div = document.createElement('div');
                        div.className = 'autocomplete-item';
                        const rx = new RegExp(`(${val.replace(/[.*+?^${}()|[\]\\]/g,'\\$&')})`, 'gi');
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

    // ── DROPDOWNS ──
    function filterBuildings(prefix) {
        const locId  = document.getElementById(`${prefix}-location`).value;
        const bldSel = document.getElementById(`${prefix}-building`);
        const penSel = document.getElementById(`${prefix}-pen`);
        bldSel.innerHTML = '<option value="">Select Building</option>';
        penSel.innerHTML = '<option value="">Select Building First</option>';
        penSel.disabled  = true;
        if (locId) { bldSel.disabled = false; allBuildings.filter(b => b.LOCATION_ID == locId).forEach(b => bldSel.appendChild(new Option(b.BUILDING_NAME, b.BUILDING_ID))); }
        else bldSel.disabled = true;
    }
    function filterPens(prefix) {
        const bldId  = document.getElementById(`${prefix}-building`).value;
        const penSel = document.getElementById(`${prefix}-pen`);
        penSel.innerHTML = '<option value="">Select Pen</option>';
        if (bldId) { penSel.disabled = false; allPens.filter(p => p.BUILDING_ID == bldId).forEach(p => penSel.appendChild(new Option(p.PEN_NAME, p.PEN_ID))); }
        else penSel.disabled = true;
    }

    // ── CONFIRM ──
    function openConfirmModal(btn) {
        const d = btn.closest('tr').dataset;
        document.getElementById('confirm_item_id').value = d.itemId;
        document.getElementById('confirm-item-name').textContent = d.itemName;
        document.getElementById('confirm-item-qty').textContent  = d.quantity;
        document.getElementById('confirm-modal').classList.add('show');
    }
    function closeConfirmModal() { document.getElementById('confirm-modal').classList.remove('show'); }
    function submitConfirmation() {
        const btn = document.getElementById('btn-do-confirm');
        btn.disabled = true; btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Confirming…';
        fetch('../purchase_confirmations/confirmFeedAndFeedingSupplies.php', {
            method: 'POST', body: new FormData(document.getElementById('confirmForm'))
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) { alert(data.message); location.reload(); }
            else { alert(data.message); btn.disabled = false; btn.innerHTML = '<i class="fa-solid fa-check"></i> Yes, Confirm'; }
        })
        .catch(() => { alert('Error.'); btn.disabled = false; });
    }

    function openConfirmAllModal()  { document.getElementById('confirm-all-modal').classList.add('show'); }
    function closeConfirmAllModal() { document.getElementById('confirm-all-modal').classList.remove('show'); }
    function submitConfirmAll() {
        const btn = document.querySelector('#confirm-all-modal .btn-amber');
        btn.disabled = true; btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Processing…';
        fetch('../purchase_confirmations/confirmAllFeedAndFeedingSupplies.php', { method: 'POST' })
        .then(r => r.json())
        .then(data => {
            if (data.success) { alert(data.message); location.reload(); }
            else { alert(data.message); btn.disabled = false; btn.innerHTML = '<i class="fa-solid fa-circle-check"></i> Confirm All'; }
        })
        .catch(() => { alert('Error.'); btn.disabled = false; });
    }

    function deleteItem(btn) {
        const d = btn.closest('tr').dataset;
        if (confirm(`Delete "${d.itemName}"? This cannot be undone.`)) {
            document.getElementById('delete_item_id').value = d.itemId;
            document.getElementById('deleteItemForm').submit();
        }
    }

    // ── VIEW ──
    function viewItem(btn) {
        const d = btn.closest('tr').dataset;
        const catMap = { 0: 'Non-Consumable', 1: 'Consumable' };
        const fmt = v => parseFloat(v).toLocaleString('en-PH', { minimumFractionDigits: 2 });
        document.getElementById('view-modal-body').innerHTML = `
            <div class="view-section">
                <div class="view-section-title"><i class="fa-solid fa-circle-info"></i> Basic Information</div>
                <div class="view-grid">
                    <div class="view-item"><span class="vl">Ref No</span><span class="vv">${d.referenceNo || '—'}</span></div>
                    <div class="view-item"><span class="vl">Supplier</span><span class="vv">${d.supplier || 'General Supplier'}</span></div>
                    <div class="view-item"><span class="vl">Feed Name</span><span class="vv">${d.itemName}</span></div>
                    <div class="view-item"><span class="vl">Recorded On</span><span class="vv">${d.createdAt || '—'}</span></div>
                    <div class="view-item" style="grid-column:1/-1;"><span class="vl">Description</span><span class="vv">${d.itemDesc || '—'}</span></div>
                </div>
            </div>
            <div class="view-section">
                <div class="view-section-title"><i class="fa-solid fa-receipt"></i> Purchase Details</div>
                <div class="view-grid">
                    <div class="view-item"><span class="vl">Quantity</span><span class="vv">${d.quantity || '0'}</span></div>
                    <div class="view-item"><span class="vl">Unit</span><span class="vv">${d.unitName}</span></div>
                    <div class="view-item"><span class="vl">Unit Cost</span><span class="vv mono">₱${fmt(d.unitCost)}</span></div>
                    <div class="view-item"><span class="vl">Net Weight</span><span class="vv">${d.netWeight || 'N/A'}</span></div>
                    <div class="view-item"><span class="vl">Category</span><span class="vv">${catMap[d.itemCategory] || '—'}</span></div>
                    <div class="view-item"><span class="vl">Purchase Date</span><span class="vv">${d.purchaseDateFmt || 'N/A'}</span></div>
                    <div class="view-item"><span class="vl">Expiration</span><span class="vv danger">${d.expirationDateFmt || 'N/A'}</span></div>
                </div>
            </div>`;
        document.getElementById('view-modal').classList.add('show');
    }
    function closeViewModal() { document.getElementById('view-modal').classList.remove('show'); }

    // ── SEARCH ──
    function filterTable() {
        const term = document.getElementById('searchInput').value.toLowerCase();
        const rows = document.querySelectorAll('#item-table tr');
        let count  = 0;
        rows.forEach(r => {
            const show = r.textContent.toLowerCase().includes(term);
            r.style.display = show ? '' : 'none';
            if (show) count++;
        });
        document.getElementById('empty-search-state').style.display = (!count && term) ? 'block' : 'none';
    }

    function showAlert(el, type, msg) {
        el.textContent = msg; el.className = `alert ${type}`; el.style.display = 'block';
    }
</script>
</body>
</html>