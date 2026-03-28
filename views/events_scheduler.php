<?php
// views/events_scheduler.php
error_reporting(0);
ini_set('display_errors', 0);
include '../config/Connection.php';

$page_title = "Event Scheduler";
$page = "farm";
include '../security/checkAccess.php';
checkAccess('event_scheduler');
include '../common/navbar.php';
include '../common/chat_support.php';

// 1. Initial Data Load
$locations = $conn->query("SELECT * FROM locations ORDER BY LOCATION_NAME")->fetchAll(PDO::FETCH_ASSOC);

// 2. Capture Filter Inputs
$selected_loc  = $_GET['loc_id']   ?? '';
$selected_bldg = $_GET['bldg_id']  ?? '';
$selected_type = $_GET['type']     ?? '';
$search_query  = $_GET['search']   ?? '';

$date_from     = $_GET['date_from'] ?? date('Y-01-01');
$date_to       = $_GET['date_to']   ?? date('Y-12-31');
$show_history  = isset($_GET['show_history']);

// --- PAGINATION ---
$limit   = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
$page_no = isset($_GET['page'])  ? (int)$_GET['page']  : 1;
if ($page_no < 1) $page_no = 1;
$offset  = ($page_no - 1) * $limit;

// 3. Build Query
$filter_clause = " WHERE e.IS_ACTIVE = 1 ";
$params = [];

if (!$show_history) { $filter_clause .= " AND e.STATUS = 'Pending' "; }

if ($selected_bldg) {
    $filter_clause .= " AND a.BUILDING_ID = ? ";
    $params[] = $selected_bldg;
} elseif ($selected_loc) {
    $filter_clause .= " AND b.LOCATION_ID = ? ";
    $params[] = $selected_loc;
}

if ($selected_type) {
    $filter_clause .= " AND e.EVENT_TYPE = ? ";
    $params[] = $selected_type;
}

if ($date_from && $date_to) {
    $filter_clause .= " AND e.START_DATE BETWEEN ? AND ? ";
    $params[] = $date_from . " 00:00:00";
    $params[] = $date_to   . " 23:59:59";
}

if ($search_query) {
    $term = "%$search_query%";
    $filter_clause .= " AND (a.TAG_NO LIKE ? OR m.SUPPLY_NAME LIKE ? OR vs.SUPPLY_NAME LIKE ? OR v.SUPPLY_NAME LIKE ?) ";
    $params[] = $term; $params[] = $term; $params[] = $term; $params[] = $term;
}

// 4. COUNT
$count_sql = "SELECT COUNT(*)
              FROM event_schedules e
              JOIN animal_records a ON e.ANIMAL_ID = a.ANIMAL_ID
              LEFT JOIN buildings b ON a.BUILDING_ID = b.BUILDING_ID
              LEFT JOIN medicines m ON e.ITEM_ID = m.SUPPLY_ID
              LEFT JOIN vitamins_supplements vs ON e.ITEM_ID = vs.SUPPLY_ID
              LEFT JOIN vaccines v ON e.ITEM_ID = v.SUPPLY_ID
              $filter_clause";
$count_stmt = $conn->prepare($count_sql);
$count_stmt->execute($params);
$total_rows  = $count_stmt->fetchColumn();
$total_pages = ceil($total_rows / $limit);

// 5. MAIN QUERY
$sql = "SELECT e.*, a.TAG_NO, p.PEN_NAME, b.BUILDING_NAME,
        CASE
            WHEN e.EVENT_TYPE = 'Medication'  THEN m.SUPPLY_NAME
            WHEN e.EVENT_TYPE = 'Vitamins'    THEN vs.SUPPLY_NAME
            WHEN e.EVENT_TYPE = 'Vaccination' THEN v.SUPPLY_NAME
            ELSE 'N/A'
        END as ITEM_NAME,
        (CASE
            WHEN e.EVENT_TYPE = 'Medication' THEN (
                SELECT COUNT(*) FROM treatment_transactions tt
                WHERE tt.ANIMAL_ID = e.ANIMAL_ID AND tt.ITEM_ID = e.ITEM_ID
                AND tt.TRANSACTION_DATE >= e.START_DATE AND tt.TRANSACTION_DATE >= DATE(e.CREATED_AT))
            WHEN e.EVENT_TYPE = 'Vaccination' THEN (
                SELECT COUNT(*) FROM vaccination_records vr
                WHERE vr.ANIMAL_ID = e.ANIMAL_ID AND vr.ITEM_ID = e.ITEM_ID
                AND vr.VACCINATION_DATE >= e.START_DATE AND vr.VACCINATION_DATE >= DATE(e.CREATED_AT))
            WHEN e.EVENT_TYPE = 'Vitamins' THEN (
                SELECT COUNT(*) FROM vitamins_supplements_transactions vst
                WHERE vst.ANIMAL_ID = e.ANIMAL_ID AND vst.ITEM_ID = e.ITEM_ID
                AND vst.TRANSACTION_DATE >= e.START_DATE AND vst.TRANSACTION_DATE >= DATE(e.CREATED_AT))
            WHEN e.EVENT_TYPE = 'Checkup' THEN (
                SELECT COUNT(*) FROM check_ups cu
                WHERE cu.ANIMAL_ID = e.ANIMAL_ID
                AND cu.CHECKUP_DATE >= e.START_DATE AND cu.CHECKUP_DATE >= DATE(e.CREATED_AT))
            ELSE 0
        END) as RECORD_EXISTS
        FROM event_schedules e
        JOIN animal_records a ON e.ANIMAL_ID = a.ANIMAL_ID
        LEFT JOIN pens p ON a.PEN_ID = p.PEN_ID
        LEFT JOIN buildings b ON a.BUILDING_ID = b.BUILDING_ID
        LEFT JOIN medicines m ON e.ITEM_ID = m.SUPPLY_ID AND e.EVENT_TYPE = 'Medication'
        LEFT JOIN vitamins_supplements vs ON e.ITEM_ID = vs.SUPPLY_ID AND e.EVENT_TYPE = 'Vitamins'
        LEFT JOIN vaccines v ON e.ITEM_ID = v.SUPPLY_ID AND e.EVENT_TYPE = 'Vaccination'
        $filter_clause
        ORDER BY DATE(e.END_DATE) DESC, e.START_DATE ASC
        LIMIT $limit OFFSET $offset";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

$icons  = ['Medication' => 'fa-pills', 'Vitamins' => 'fa-leaf', 'Vaccination' => 'fa-syringe', 'Checkup' => 'fa-stethoscope'];
$links  = ['Medication' => 'group_medication.php', 'Vitamins' => 'group_vitamins.php',
           'Vaccination' => 'group_vaccination.php', 'Checkup' => 'group_checkup.php'];

// Count active filters
$active_filters = 0;
if ($date_from || $date_to) $active_filters++;
if ($selected_loc)  $active_filters++;
if ($selected_bldg) $active_filters++;
if ($selected_type) $active_filters++;
if ($search_query)  $active_filters++;
if ($show_history)  $active_filters++;

function getUrl($newPage) {
    $params = $_GET;
    $params['page'] = $newPage;
    return '?' . http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Event Scheduler</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" type="text/css" href="https://npmcdn.com/flatpickr/dist/themes/dark.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />

    <style>
        /* ─── CSS VARIABLES ─── */
        :root {
            --bg-base:        #080f1a;
            --bg-surface:     #0d1829;
            --bg-elevated:    #111f35;
            --bg-hover:       #162540;
            --border:         rgba(255,255,255,0.07);
            --border-active:  rgba(34,197,94,0.5);
            --green:          #22c55e;
            --green-dim:      rgba(34,197,94,0.12);
            --green-glow:     rgba(34,197,94,0.25);
            --gold:           #f59e0b;
            --gold-dim:       rgba(245,158,11,0.12);
            --blue:           #38bdf8;
            --blue-dim:       rgba(56,189,248,0.12);
            --pink:           #f472b6;
            --pink-dim:       rgba(244,114,182,0.1);
            --red:            #f87171;
            --red-dim:        rgba(248,113,113,0.12);
            --purple:         #a78bfa;
            --purple-dim:     rgba(167,139,250,0.12);
            --text-primary:   #f1f5f9;
            --text-secondary: #94a3b8;
            --text-muted:     #475569;
            --radius-sm:      6px;
            --radius-md:      10px;
            --radius-lg:      14px;
            --radius-xl:      20px;
            --shadow-sm:      0 1px 3px rgba(0,0,0,0.4);
            --shadow-md:      0 4px 16px rgba(0,0,0,0.4);
            --shadow-lg:      0 8px 32px rgba(0,0,0,0.5);
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
            padding-bottom: 100px;
            background-image:
                radial-gradient(ellipse 80% 50% at 50% -20%, rgba(34,197,94,0.06) 0%, transparent 60%),
                radial-gradient(ellipse 40% 30% at 85% 10%, rgba(56,189,248,0.04) 0%, transparent 50%);
        }

        .container {
            max-width: 1560px;
            margin: 0 auto;
            padding: 2rem 1.5rem;
        }

        /* ─── TOP BAR ─── */
        .top-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 2rem;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            color: var(--text-secondary);
            font-size: 0.875rem;
            font-weight: 500;
            padding: 8px 14px;
            background: var(--bg-elevated);
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            transition: all var(--transition);
        }
        .back-link:hover { color: var(--text-primary); border-color: var(--border-active); background: var(--bg-hover); }
        .back-link i { font-size: 0.8rem; }

        .page-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--green);
            background: var(--green-dim);
            border: 1px solid rgba(34,197,94,0.2);
            padding: 6px 12px;
            border-radius: 99px;
        }

        /* ─── PAGE HEADER ─── */
        .page-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            margin-bottom: 2rem;
            gap: 1rem;
            flex-wrap: wrap;
        }
        .page-title {
            font-size: clamp(1.6rem, 3vw, 2.2rem);
            font-weight: 700;
            color: var(--text-primary);
            letter-spacing: -0.03em;
            line-height: 1.1;
        }
        .page-title span {
            background: linear-gradient(135deg, var(--green), #16a34a);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .page-subtitle {
            color: var(--text-secondary);
            font-size: 0.9rem;
            margin-top: 6px;
        }
        .header-actions {
            display: flex;
            gap: 8px;
            flex-shrink: 0;
            flex-wrap: wrap;
            align-items: center;
        }

        /* ─── BUTTONS ─── */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            padding: 0 16px;
            height: 38px;
            border-radius: var(--radius-md);
            font-size: 0.8rem;
            font-weight: 600;
            font-family: var(--font);
            border: 1px solid transparent;
            cursor: pointer;
            transition: all var(--transition);
            text-decoration: none;
            white-space: nowrap;
            letter-spacing: 0.01em;
        }
        .btn i { font-size: 0.75rem; }

        .btn-primary {
            background: var(--green);
            color: #000;
            border-color: var(--green);
        }
        .btn-primary:hover { background: #16a34a; box-shadow: 0 0 16px var(--green-glow); }

        .btn-ghost {
            background: transparent;
            color: var(--text-secondary);
            border-color: var(--border);
        }
        .btn-ghost:hover { background: var(--bg-elevated); color: var(--text-primary); border-color: rgba(255,255,255,0.15); }

        .btn-danger-outline {
            background: transparent;
            color: var(--red);
            border-color: rgba(248,113,113,0.3);
        }
        .btn-danger-outline:hover { background: var(--red-dim); border-color: var(--red); }

        .btn-sm { height: 32px; padding: 0 12px; font-size: 0.75rem; }
        .btn:disabled { opacity: 0.5; cursor: not-allowed; pointer-events: none; }

        /* ─── FILTER PANEL ─── */
        .filter-panel {
            background: var(--bg-surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-xl);
            margin-bottom: 2rem;
            overflow: hidden;
        }

        .filter-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--border);
            gap: 1rem;
            flex-wrap: wrap;
            cursor: pointer;
            user-select: none;
        }
        .filter-header-left {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .filter-header-title {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--text-primary);
        }
        .filter-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 20px; height: 20px;
            font-size: 0.7rem;
            font-weight: 700;
            background: var(--green);
            color: #000;
            border-radius: 99px;
            padding: 0 6px;
        }
        .filter-toggle-btn {
            display: flex; align-items: center; gap: 6px;
            font-size: 0.8rem; font-weight: 500;
            color: var(--text-secondary);
            background: none; border: none; cursor: pointer;
            transition: color var(--transition);
        }
        .filter-toggle-btn:hover { color: var(--text-primary); }
        .filter-toggle-btn i { transition: transform 0.25s ease; }
        .filter-toggle-btn.collapsed i { transform: rotate(-90deg); }

        .filter-body {
            padding: 1.5rem;
        }
        .filter-body.hidden { display: none; }

        .filter-section-label {
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--text-muted);
            padding-bottom: 0.5rem;
            border-bottom: 1px solid var(--border);
            margin-bottom: 1rem;
        }

        .filter-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
            align-items: start;
            margin-bottom: 1.25rem;
        }

        /* ─── FORM CONTROLS ─── */
        .form-group { display: flex; flex-direction: column; gap: 6px; }

        .form-label {
            font-size: 0.72rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .form-label i { font-size: 0.65rem; opacity: 0.7; }

        .form-control {
            width: 100%;
            padding: 0 12px;
            height: 40px;
            background: var(--bg-elevated);
            border: 1px solid var(--border);
            color: var(--text-primary);
            border-radius: var(--radius-md);
            font-size: 0.875rem;
            font-family: var(--font);
            outline: none;
            transition: border-color var(--transition), box-shadow var(--transition), background var(--transition);
            appearance: none;
            -webkit-appearance: none;
        }

        select.form-control {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2394a3b8' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            padding-right: 36px;
            cursor: pointer;
        }

        .form-control:focus {
            border-color: var(--border-active);
            box-shadow: 0 0 0 3px var(--green-glow);
            background: var(--bg-hover);
        }

        .form-control:disabled {
            opacity: 0.38;
            cursor: not-allowed;
            pointer-events: none;
        }

        .form-control option {
            background: #1e293b;
            color: var(--text-primary);
        }

        .input-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 6px;
        }

        /* ─── FILTER FOOTER ─── */
        .filter-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem 1.5rem;
            border-top: 1px solid var(--border);
            flex-wrap: wrap;
            gap: 1rem;
        }
        .filter-footer-left { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
        .filter-footer-right { display: flex; gap: 8px; flex-wrap: wrap; }

        /* History toggle */
        .history-toggle {
            display: flex; align-items: center; gap: 8px;
            font-size: 0.8rem; color: var(--text-secondary);
            cursor: pointer; user-select: none;
            padding: 6px 12px;
            border-radius: var(--radius-md);
            border: 1px solid var(--border);
            background: var(--bg-elevated);
            transition: all var(--transition);
        }
        .history-toggle:hover { color: var(--text-primary); border-color: rgba(255,255,255,0.15); }
        .history-toggle input { accent-color: var(--green); width: 14px; height: 14px; cursor: pointer; }

        /* ─── TABLE ─── */
        .table-card {
            background: var(--bg-surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-xl);
            overflow: hidden;
        }

        .table-wrap { overflow-x: auto; }

        table { width: 100%; border-collapse: collapse; min-width: 900px; }

        thead th {
            background: var(--bg-elevated);
            color: var(--text-muted);
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.07em;
            padding: 10px 14px;
            text-align: left;
            border-bottom: 1px solid var(--border);
            white-space: nowrap;
            position: sticky;
            top: 0;
            z-index: 1;
        }

        thead th:first-child { padding-left: 1.25rem; }

        tbody tr {
            border-bottom: 1px solid var(--border);
            transition: background var(--transition);
        }
        tbody tr:last-child { border-bottom: none; }
        tbody tr:hover { background: rgba(255,255,255,0.02); }

        td {
            padding: 10px 14px;
            font-size: 0.825rem;
            color: var(--text-primary);
            white-space: nowrap;
            vertical-align: middle;
        }
        td:first-child { padding-left: 1.25rem; }

        /* Group header rows */
        .group-header-row {
            background: rgba(34,197,94,0.06);
            border-top: 1px solid rgba(34,197,94,0.15);
        }
        .group-header-row td {
            color: var(--green);
            font-weight: 700;
            font-size: 0.8rem;
            padding: 8px 14px;
        }

        /* Locked rows */
        .row-locked td { color: var(--text-muted); }
        .row-locked:hover { background: transparent !important; }

        /* ─── BADGES ─── */
        .event-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 10px;
            border-radius: var(--radius-sm);
            font-size: 0.7rem;
            font-weight: 700;
            letter-spacing: 0.03em;
            text-decoration: none;
            border: 1px solid transparent;
            transition: all var(--transition);
        }
        .event-badge:hover { filter: brightness(1.15); transform: translateY(-1px); }
        .event-badge i { font-size: 0.65rem; }

        .badge-med  { background: var(--blue-dim);   color: var(--blue);   border-color: rgba(56,189,248,0.2); }
        .badge-vit  { background: var(--green-dim);  color: var(--green);  border-color: rgba(34,197,94,0.2); }
        .badge-vac  { background: var(--pink-dim);   color: var(--pink);   border-color: rgba(244,114,182,0.2); }
        .badge-chk  { background: var(--gold-dim);   color: var(--gold);   border-color: rgba(245,158,11,0.2); }

        /* ─── STATUS BADGES ─── */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 9px;
            border-radius: 99px;
            font-size: 0.7rem;
            font-weight: 700;
            letter-spacing: 0.03em;
        }
        .s-pending  { background: var(--gold-dim);   color: var(--gold);   border: 1px solid rgba(245,158,11,0.2); }
        .s-done     { background: var(--green-dim);  color: var(--green);  border: 1px solid rgba(34,197,94,0.2); }
        .s-cancelled{ background: rgba(71,85,105,0.2); color: var(--text-muted); border: 1px solid rgba(71,85,105,0.3); }

        .late-tag   { display: block; color: var(--red);   font-weight: 700; font-size: 0.68rem; margin-top: 3px; font-family: var(--font-mono); }
        .ontime-tag { display: block; color: var(--green); font-weight: 700; font-size: 0.68rem; margin-top: 3px; font-family: var(--font-mono); }
        .status-time { display: block; font-size: 0.68rem; color: var(--text-muted); margin-top: 4px; font-style: italic; }

        /* ─── VALUE CELLS ─── */
        .val-tag    { font-family: var(--font-mono); color: var(--gold); font-size: 0.825rem; font-weight: 600; }
        .col-sub    { font-size: 0.72rem; color: var(--text-muted); margin-top: 2px; }
        .col-name   { font-weight: 600; color: var(--text-primary); }

        /* ─── PAGINATION ─── */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 4px;
            margin-top: 1.5rem;
            flex-wrap: wrap;
        }
        .pag-info {
            text-align: center;
            font-size: 0.8rem;
            color: var(--text-muted);
            margin-top: 0.75rem;
        }
        .page-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 36px;
            height: 36px;
            padding: 0 8px;
            border-radius: var(--radius-md);
            background: var(--bg-surface);
            border: 1px solid var(--border);
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 0.82rem;
            font-weight: 600;
            transition: all var(--transition);
        }
        .page-link:hover { background: var(--bg-elevated); color: var(--text-primary); border-color: rgba(255,255,255,0.15); }
        .page-link.active { background: var(--green); color: #000; border-color: var(--green); cursor: default; }
        .page-link.disabled { opacity: 0.35; pointer-events: none; }

        /* ─── EMPTY STATE ─── */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--text-muted);
        }
        .empty-state i { font-size: 2.5rem; margin-bottom: 1rem; opacity: 0.4; display: block; }
        .empty-state p { font-size: 0.875rem; }

        /* ─── BULK ACTION BAR ─── */
        #bulkActionBar {
            position: fixed;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%) translateY(200%);
            width: 90%;
            max-width: 760px;
            background: rgba(13,24,41,0.97);
            backdrop-filter: blur(16px);
            padding: 1rem 1.5rem;
            border-radius: var(--radius-xl);
            border: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            z-index: 900;
            box-shadow: 0 20px 50px rgba(0,0,0,0.6), 0 0 0 1px rgba(34,197,94,0.08);
            transition: transform 0.3s cubic-bezier(0.4,0,0.2,1);
            gap: 1rem;
        }
        #bulkActionBar.active { transform: translateX(-50%) translateY(0); }

        .bulk-count {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--text-secondary);
            white-space: nowrap;
        }
        .bulk-count strong {
            color: var(--green);
            font-size: 1.3rem;
            font-family: var(--font-mono);
            font-weight: 700;
        }

        .bulk-actions { display: flex; gap: 8px; flex-wrap: wrap; }

        .action-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 0 14px;
            height: 36px;
            border-radius: var(--radius-md);
            font-size: 0.78rem;
            font-weight: 600;
            font-family: var(--font);
            cursor: pointer;
            border: 1px solid transparent;
            transition: all var(--transition);
        }
        .action-btn i { font-size: 0.7rem; }

        .btn-process     { background: var(--green); color: #000; border-color: var(--green); }
        .btn-process:hover { background: #16a34a; box-shadow: 0 0 12px var(--green-glow); }
        .btn-mark-done   { background: var(--bg-elevated); color: var(--green); border-color: rgba(34,197,94,0.3); }
        .btn-mark-done:hover { background: var(--green-dim); }
        .btn-mark-cancel { background: var(--bg-elevated); color: var(--text-secondary); border-color: var(--border); }
        .btn-mark-cancel:hover { background: var(--bg-hover); color: var(--text-primary); }
        .btn-delete-sel  { background: var(--red-dim); color: var(--red); border-color: rgba(248,113,113,0.25); }
        .btn-delete-sel:hover { border-color: var(--red); }

        /* ─── MODALS ─── */
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.75);
            z-index: 1000;
            justify-content: center;
            align-items: center;
            padding: 1rem;
            backdrop-filter: blur(6px);
        }
        .modal-overlay.open { display: flex; }

        .modal-card {
            background: var(--bg-surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-xl);
            width: 100%;
            max-width: 980px;
            max-height: 92vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            box-shadow: var(--shadow-lg);
        }
        .modal-card.modal-sm { max-width: 520px; }

        .modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--border);
            background: var(--bg-elevated);
        }
        .modal-title {
            font-size: 1rem;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .modal-title i { color: var(--green); }
        .modal-close {
            width: 32px; height: 32px;
            background: none;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            color: var(--text-secondary);
            cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            font-size: 1rem;
            transition: all var(--transition);
        }
        .modal-close:hover { background: var(--bg-hover); color: var(--text-primary); border-color: rgba(255,255,255,0.15); }

        .modal-body {
            overflow-y: auto;
            flex: 1;
        }
        .modal-body-split {
            display: grid;
            grid-template-columns: 1fr 1.5fr;
            height: 100%;
            overflow: hidden;
        }
        .modal-left {
            padding: 1.5rem;
            border-right: 1px solid var(--border);
            overflow-y: auto;
            background: rgba(8,15,26,0.4);
        }
        .modal-right {
            padding: 1.5rem;
            overflow-y: auto;
        }

        .modal-footer {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 8px;
            padding: 1rem 1.5rem;
            border-top: 1px solid var(--border);
            background: var(--bg-elevated);
        }

        .modal-section-label {
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--green);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .modal-section-label::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--border);
        }

        /* ─── PEN PICKER ─── */
        .pen-list { display: flex; flex-direction: column; gap: 6px; margin-top: 0.75rem; }

        .pen-pill {
            display: flex; align-items: center; gap: 10px;
            padding: 8px 12px;
            background: var(--bg-elevated);
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            cursor: pointer;
            transition: all var(--transition);
            user-select: none;
        }
        .pen-pill:hover { border-color: var(--border-active); background: var(--bg-hover); }
        .pen-pill.selected { border-color: rgba(34,197,94,0.4); background: var(--green-dim); }
        .pen-pill input[type="checkbox"] { accent-color: var(--green); width: 15px; height: 15px; cursor: pointer; flex-shrink: 0; }
        .pen-pill-name  { font-weight: 600; color: var(--text-primary); flex: 1; font-size: 0.875rem; }
        .pen-pill-count { font-size: 0.75rem; color: var(--text-muted); font-family: var(--font-mono); }
        .pen-pill.selected .pen-pill-count { color: var(--green); }

        .pen-select-all-row {
            display: flex; align-items: center; justify-content: space-between;
            padding: 4px 0; margin-bottom: 4px;
        }
        .pen-select-all-row label {
            font-size: 0.75rem; color: var(--text-secondary); cursor: pointer;
            display: flex; align-items: center; gap: 6px;
        }
        .pen-select-all-row label input { accent-color: var(--green); }

        .pen-selected-badge {
            font-size: 0.72rem; color: var(--green);
            font-weight: 600; display: none;
            padding: 5px 10px;
            background: var(--green-dim);
            border: 1px solid rgba(34,197,94,0.2);
            border-radius: var(--radius-sm);
            margin-top: 8px;
            text-align: center;
        }
        .pen-selected-badge.show { display: block; }

        /* ─── SELECTION TREE ─── */
        .selection-tree { margin-top: 1rem; display: flex; flex-direction: column; gap: 8px; }
        .pen-group { border: 1px solid var(--border); border-radius: var(--radius-md); overflow: hidden; }
        .pen-header {
            padding: 10px 14px;
            background: var(--bg-elevated);
            display: flex; align-items: center; gap: 10px;
            cursor: pointer;
            transition: background var(--transition);
            font-size: 0.875rem;
            font-weight: 600;
        }
        .pen-header:hover { background: var(--bg-hover); }
        .pen-header input[type="checkbox"] { accent-color: var(--green); }
        .pen-body {
            display: none;
            grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            gap: 6px;
            padding: 10px;
            background: rgba(0,0,0,0.15);
        }
        .pen-body.open { display: grid; }
        .chk-label {
            display: flex; align-items: center; gap: 6px;
            font-size: 0.8rem; color: var(--text-secondary);
            padding: 5px 10px;
            background: var(--bg-elevated);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            cursor: pointer;
            transition: all var(--transition);
        }
        .chk-label:hover { border-color: var(--border-active); color: var(--text-primary); }
        .chk-label input { accent-color: var(--green); }

        /* Placeholder in tree */
        .tree-placeholder {
            text-align: center; padding: 1.5rem;
            color: var(--text-muted); font-size: 0.85rem;
            border: 1px dashed var(--border);
            border-radius: var(--radius-md);
        }

        /* ─── ARCHIVE MODAL TABS ─── */
        .tab-row {
            display: flex;
            border-bottom: 1px solid var(--border);
            margin-bottom: 1.5rem;
        }
        .tab-btn {
            padding: 10px 16px;
            background: none;
            border: none;
            border-bottom: 2px solid transparent;
            color: var(--text-muted);
            font-size: 0.82rem;
            font-weight: 600;
            font-family: var(--font);
            cursor: pointer;
            transition: all var(--transition);
            margin-bottom: -1px;
        }
        .tab-btn:hover { color: var(--text-primary); }
        .tab-btn.active { color: var(--green); border-bottom-color: var(--green); }
        .tab-content { display: none; }
        .tab-content.active { display: block; }

        /* ─── TOAST ─── */
        #toastContainer {
            position: fixed; top: 20px; right: 20px;
            z-index: 9999; display: flex; flex-direction: column; gap: 8px;
        }
        .toast {
            background: var(--bg-elevated);
            color: var(--text-primary);
            padding: 0.875rem 1.25rem;
            border-radius: var(--radius-lg);
            border-left: 3px solid var(--green);
            box-shadow: var(--shadow-md);
            font-size: 0.85rem;
            font-weight: 500;
            animation: toastIn 0.25s ease;
        }
        .toast.error { border-left-color: var(--red); }
        @keyframes toastIn { from { opacity: 0; transform: translateX(16px); } to { opacity: 1; transform: translateX(0); } }

        /* ─── RESPONSIVE ─── */
        @media (max-width: 1100px) {
            .filter-grid { grid-template-columns: repeat(3, 1fr); }
        }

        @media (max-width: 768px) {
            .container { padding: 1rem; }
            .filter-grid { grid-template-columns: repeat(2, 1fr); }
            .page-header { flex-direction: column; }
            .header-actions { width: 100%; }
            .header-actions .btn { flex: 1; }
            .filter-footer { flex-direction: column; align-items: stretch; }
            .filter-footer-left, .filter-footer-right { justify-content: stretch; }
            .filter-footer .btn { flex: 1; }
            .modal-body-split { grid-template-columns: 1fr; }
            .modal-left { border-right: none; border-bottom: 1px solid var(--border); }
            #bulkActionBar { width: calc(100% - 2rem); flex-direction: column; }
            .bulk-actions { width: 100%; display: grid; grid-template-columns: 1fr 1fr; }
            .btn-process { grid-column: 1 / -1; }

            /* Mobile card layout */
            .table-card { background: transparent; border: none; }
            .table-wrap { overflow: visible; }
            table { min-width: 0; display: block; }
            thead { display: none; }
            tbody { display: block; }
            tbody tr {
                display: block;
                background: var(--bg-surface);
                border: 1px solid var(--border);
                border-radius: var(--radius-lg);
                margin-bottom: 0.75rem;
                padding: 1rem;
            }
            tbody tr.group-header-row { padding: 0.75rem 1rem; }
            tbody tr.group-header-row td { display: block; border: none; padding: 0; }
            tbody tr.group-header-row td::before { display: none; }
            td {
                display: flex; justify-content: space-between; align-items: flex-start;
                gap: 1rem; padding: 6px 0;
                border-bottom: 1px solid var(--border);
                white-space: normal;
            }
            td:last-child { border-bottom: none; }
            td::before {
                content: attr(data-label);
                font-size: 0.68rem; font-weight: 700;
                text-transform: uppercase; letter-spacing: 0.05em;
                color: var(--text-muted); white-space: nowrap;
                flex-shrink: 0; padding-top: 2px;
            }
        }

        @media (max-width: 520px) {
            .filter-grid { grid-template-columns: 1fr; }
        }

        /* ─── UTILITIES ─── */
        .hidden { display: none !important; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .span-2 { grid-column: span 2; }
    </style>
</head>
<body>

<div id="toastContainer"></div>

<div class="container">

    <!-- Top Bar -->
    <div class="top-bar">
        <a href="farm_dashboard.php" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back to Farm Dashboard
        </a>
        <span class="page-badge"><i class="fa-solid fa-calendar-check"></i> Farm Schedule</span>
    </div>

    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1 class="page-title">Event <span>Scheduler</span></h1>
            <p class="page-subtitle">Manage health schedules, vaccinations, and medication events.</p>
        </div>
        <div class="header-actions">
            <button class="btn btn-ghost" onclick="openArchiveModal()">
                <i class="fa-solid fa-box-archive"></i> Archive (Bulk)
            </button>
            <button class="btn btn-primary" onclick="openAddModal()">
                <i class="fa-solid fa-plus"></i> Schedule Event
            </button>
        </div>
    </div>

    <!-- Filter Panel -->
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

                <!-- Row 1 -->
                <div style="margin-bottom:0.5rem;" class="filter-section-label">
                    <i class="fa-solid fa-magnifying-glass"></i> &nbsp;Search &amp; Scope
                </div>
                <div class="filter-grid" style="margin-bottom:1.25rem;">
                    <div class="form-group span-2">
                        <label class="form-label"><i class="fa-solid fa-tag"></i> Search</label>
                        <div style="position:relative;">
                            <i class="fa-solid fa-magnifying-glass" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--text-muted);font-size:0.75rem;pointer-events:none;"></i>
                            <input type="text" name="search" class="form-control"
                                   placeholder="Tag No or Item Name…"
                                   value="<?= htmlspecialchars($search_query) ?>"
                                   style="padding-left:34px;">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label"><i class="fa-solid fa-map-pin"></i> Location</label>
                        <select name="loc_id" class="form-control" onchange="this.form.submit()">
                            <option value="">All Locations</option>
                            <?php foreach($locations as $l): ?>
                                <option value="<?= $l['LOCATION_ID'] ?>" <?= $selected_loc==$l['LOCATION_ID']?'selected':'' ?>>
                                    <?= htmlspecialchars($l['LOCATION_NAME']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label"><i class="fa-solid fa-building"></i> Building</label>
                        <select name="bldg_id" class="form-control" onchange="this.form.submit()" <?= !$selected_loc?'disabled':'' ?>>
                            <option value="">All Buildings</option>
                            <?php if($selected_loc):
                                $bldgs = $conn->prepare("SELECT * FROM buildings WHERE LOCATION_ID = ?");
                                $bldgs->execute([$selected_loc]);
                                while($b = $bldgs->fetch()): ?>
                                    <option value="<?= $b['BUILDING_ID'] ?>" <?= $selected_bldg==$b['BUILDING_ID']?'selected':'' ?>>
                                        <?= htmlspecialchars($b['BUILDING_NAME']) ?>
                                    </option>
                            <?php endwhile; endif; ?>
                        </select>
                    </div>
                </div>

                <!-- Row 2 -->
                <div style="margin-bottom:0.5rem;" class="filter-section-label">
                    <i class="fa-solid fa-filter"></i> &nbsp;Event &amp; Date
                </div>
                <div class="filter-grid">
                    <div class="form-group">
                        <label class="form-label"><i class="fa-solid fa-tags"></i> Event Type</label>
                        <select name="type" class="form-control" onchange="this.form.submit()">
                            <option value="">All Types</option>
                            <option value="Medication"  <?= $selected_type=='Medication' ?'selected':'' ?>>Medication</option>
                            <option value="Vitamins"    <?= $selected_type=='Vitamins'   ?'selected':'' ?>>Vitamins</option>
                            <option value="Vaccination" <?= $selected_type=='Vaccination'?'selected':'' ?>>Vaccination</option>
                            <option value="Checkup"     <?= $selected_type=='Checkup'    ?'selected':'' ?>>Checkup</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label"><i class="fa-solid fa-list-ol"></i> Show Rows</label>
                        <select name="limit" class="form-control" onchange="this.form.submit()">
                            <option value="50"  <?= $limit==50  ? 'selected':'' ?>>50 rows</option>
                            <option value="100" <?= $limit==100 ? 'selected':'' ?>>100 rows</option>
                            <option value="200" <?= $limit==200 ? 'selected':'' ?>>200 rows</option>
                            <option value="500" <?= $limit==500 ? 'selected':'' ?>>500 rows</option>
                        </select>
                    </div>

                    <div class="form-group span-2">
                        <label class="form-label"><i class="fa-solid fa-calendar-days"></i> Date Range (Start Date)</label>
                        <div class="input-row">
                            <input type="text" name="date_from" class="form-control date-picker"
                                   value="<?= htmlspecialchars($date_from) ?>" placeholder="From">
                            <input type="text" name="date_to" class="form-control date-picker"
                                   value="<?= htmlspecialchars($date_to) ?>" placeholder="To">
                        </div>
                    </div>
                </div>

            </form>
        </div>

        <div class="filter-footer">
            <div class="filter-footer-left">
                <label class="history-toggle">
                    <input type="checkbox" name="show_history" value="1"
                           <?= $show_history?'checked':'' ?>
                           onchange="document.getElementById('filterForm').show_history=this; document.getElementById('filterForm').submit();"
                           form="filterForm">
                    <i class="fa-solid fa-clock-rotate-left" style="font-size:0.7rem;"></i>
                    Show History
                </label>
                <a href="events_scheduler.php" class="btn btn-ghost btn-sm">
                    <i class="fa-solid fa-rotate-left"></i> Reset
                </a>
                <button type="submit" form="filterForm" class="btn btn-primary btn-sm">
                    <i class="fa-solid fa-magnifying-glass"></i> Apply
                </button>
            </div>
        </div>
    </div>

    <!-- Table -->
    <div class="table-card">
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th style="width:46px;">
                            <input type="checkbox" onchange="toggleSelectAll(this)"
                                   style="width:15px;height:15px;accent-color:var(--green);cursor:pointer;">
                        </th>
                        <th>Deadline</th>
                        <th>Location</th>
                        <th>Tag No</th>
                        <th>Type</th>
                        <th>Item / Details</th>
                        <th>Frequency</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($events)): ?>
                        <tr>
                            <td colspan="8">
                                <div class="empty-state">
                                    <i class="fa-solid fa-calendar-xmark"></i>
                                    <p>No events found matching your filters.</p>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php
                        $currentDateGroup = null;

                        // Map type to badge class
                        $badgeClassMap = [
                            'Medication'  => 'badge-med',
                            'Vitamins'    => 'badge-vit',
                            'Vaccination' => 'badge-vac',
                            'Checkup'     => 'badge-chk',
                        ];

                        foreach($events as $ev):
                            $isDone      = ($ev['STATUS'] === 'Done');
                            $isCancelled = ($ev['STATUS'] === 'Cancelled');
                            $canSelect   = ($ev['STATUS'] === 'Pending');
                            $isLocked    = ($isDone || $isCancelled);
                            $hasRecord   = ($ev['RECORD_EXISTS'] ?? 0) > 0;

                            $iconClass  = $icons[$ev['EVENT_TYPE']] ?? 'fa-calendar';
                            $badgeClass = $badgeClassMap[$ev['EVENT_TYPE']] ?? '';
                            $link       = $links[$ev['EVENT_TYPE']] ?? '#';

                            $deadline           = !empty($ev['END_DATE']) ? $ev['END_DATE'] : $ev['START_DATE'];
                            $formattedDateGroup = date('m/d/Y', strtotime($deadline));
                            if ($formattedDateGroup !== $currentDateGroup) {
                                $currentDateGroup = $formattedDateGroup;
                                echo "<tr class='group-header-row'><td colspan='8'><i class='fa-solid fa-calendar-day' style='margin-right:8px;opacity:0.6;'></i>Due: $currentDateGroup</td></tr>";
                            }

                            $actualDate = ($isDone && !empty($ev['COMPLETED_AT'])) ? $ev['COMPLETED_AT'] : date('Y-m-d H:i:s');
                            $isLate = false; $daysLate = 0;
                            if (strtotime($actualDate) > strtotime($deadline)) {
                                $diff = strtotime($actualDate) - strtotime($deadline);
                                $daysLate = floor($diff / 86400);
                                if ($daysLate > 0) $isLate = true;
                            }

                            $statusClass = 's-pending';
                            if($isDone) $statusClass = 's-done';
                            if($isCancelled) $statusClass = 's-cancelled';
                        ?>
                        <tr class="<?= $isLocked ? 'row-locked' : '' ?>">
                            <td data-label="Select" style="padding-left:1.25rem;">
                                <?php if($canSelect): ?>
                                    <input type="checkbox" class="row-chk" value="<?= $ev['EVENT_ID'] ?>"
                                           data-type="<?= $ev['EVENT_TYPE'] ?>"
                                           data-has-record="<?= $hasRecord ? 1 : 0 ?>"
                                           style="width:15px;height:15px;accent-color:var(--green);cursor:pointer;">
                                <?php elseif($isLocked): ?>
                                    <i class="fa-solid fa-lock" style="color:var(--text-muted);font-size:0.75rem;opacity:0.5;"></i>
                                <?php endif; ?>
                            </td>
                            <td data-label="Deadline">
                                <div style="font-weight:700;color:<?= $isLocked?'var(--text-muted)':'var(--text-primary)' ?>;">
                                    <?= date('m/d/Y', strtotime($deadline)) ?>
                                </div>
                                <div class="col-sub"><?= date('h:i A', strtotime($deadline)) ?></div>
                            </td>
                            <td data-label="Location">
                                <div class="col-name"><?= htmlspecialchars($ev['PEN_NAME'] ?? '—') ?></div>
                                <div class="col-sub"><?= htmlspecialchars($ev['BUILDING_NAME'] ?? '') ?></div>
                            </td>
                            <td data-label="Tag No" class="val-tag"><?= htmlspecialchars($ev['TAG_NO']) ?></td>
                            <td data-label="Type">
                                <a href="<?= $link ?>" class="event-badge <?= $badgeClass ?>">
                                    <i class="fa-solid <?= $iconClass ?>"></i>
                                    <?= $ev['EVENT_TYPE'] ?>
                                    <i class="fa-solid fa-arrow-up-right-from-square" style="opacity:0.5;font-size:0.6rem;"></i>
                                </a>
                            </td>
                            <td data-label="Item"><?= htmlspecialchars($ev['ITEM_NAME']) ?></td>
                            <td data-label="Frequency">
                                <?php if($ev['INTERVAL_DAYS']): ?>
                                    <span style="font-family:var(--font-mono);font-size:0.78rem;color:var(--blue);">
                                        Every <?= $ev['INTERVAL_DAYS'] ?>d
                                    </span>
                                <?php else: ?>
                                    <span style="font-size:0.8rem;color:var(--text-muted);">One-time</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Status">
                                <span class="status-badge <?= $statusClass ?>"><?= $ev['STATUS'] ?></span>
                                <?php if($ev['STATUS']==='Pending' && $isLate): ?>
                                    <span class="late-tag"><i class="fa-solid fa-triangle-exclamation" style="font-size:0.6rem;"></i> Late <?= $daysLate ?>d</span>
                                <?php elseif($ev['STATUS']==='Done'): ?>
                                    <?php if($isLate): ?><span class="late-tag">Late (<?= $daysLate ?>d)</span>
                                    <?php else: ?>      <span class="ontime-tag"><i class="fa-solid fa-check" style="font-size:0.6rem;"></i> On Time</span>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <?php if($ev['STATUS']!=='Pending' && !empty($ev['COMPLETED_AT'])): ?>
                                    <div class="status-time"><?= date('m/d/Y h:i A', strtotime($ev['COMPLETED_AT'])) ?></div>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination -->
    <?php if($total_pages > 1): ?>
    <div class="pagination">
        <a href="<?= getUrl($page_no-1) ?>" class="page-link <?= $page_no<=1?'disabled':'' ?>">
            <i class="fa-solid fa-chevron-left"></i>
        </a>
        <?php
        $start_p = max(1, $page_no-2);
        $end_p   = min($total_pages, $page_no+2);
        if($start_p > 1) echo '<span style="color:var(--text-muted);line-height:36px;padding:0 4px;font-size:0.8rem;">…</span>';
        for($i=$start_p; $i<=$end_p; $i++): ?>
            <a href="<?= getUrl($i) ?>" class="page-link <?= $page_no==$i?'active':'' ?>"><?= $i ?></a>
        <?php endfor;
        if($end_p < $total_pages) echo '<span style="color:var(--text-muted);line-height:36px;padding:0 4px;font-size:0.8rem;">…</span>';
        ?>
        <a href="<?= getUrl($page_no+1) ?>" class="page-link <?= $page_no>=$total_pages?'disabled':'' ?>">
            <i class="fa-solid fa-chevron-right"></i>
        </a>
    </div>
    <div class="pag-info">
        Showing <strong><?= ($offset+1) ?></strong>–<strong><?= min($offset+$limit,$total_rows) ?></strong>
        of <strong><?= $total_rows ?></strong> events
    </div>
    <?php endif; ?>

</div>

<!-- ═══════════════ BULK ACTION BAR ═══════════════ -->
<div id="bulkActionBar">
    <div class="bulk-count">
        <strong id="selectedCount">0</strong> selected
    </div>
    <div class="bulk-actions">
        <button class="action-btn btn-process" onclick="processSelectedEvents()">
            <i class="fa-solid fa-bolt"></i> Process Tasks
        </button>
        <button class="action-btn btn-mark-done" onclick="bulkUpdate('Done')">
            <i class="fa-solid fa-check"></i> Mark Done
        </button>
        <button class="action-btn btn-mark-cancel" onclick="bulkUpdate('Cancelled')">
            <i class="fa-solid fa-ban"></i> Cancel
        </button>
        <button class="action-btn btn-delete-sel" onclick="deleteSelected()">
            <i class="fa-solid fa-box-archive"></i> Archive
        </button>
    </div>
</div>

<!-- ═══════════════ ADD EVENT MODAL ═══════════════ -->
<div id="addModal" class="modal-overlay">
    <div class="modal-card">
        <div class="modal-header">
            <div class="modal-title">
                <i class="fa-solid fa-calendar-plus"></i>
                Schedule New Event
            </div>
            <button class="modal-close" onclick="closeModal('addModal')">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <div class="modal-body">
            <div class="modal-body-split">

                <div class="modal-left">
                    <div class="modal-section-label">
                        <i class="fa-solid fa-location-dot"></i>
                        1. Select Targets
                    </div>

                    <div class="form-group" style="margin-bottom:1rem;">
                        <label class="form-label"><i class="fa-solid fa-map-pin"></i> Location <span style="color:var(--red);">*</span></label>
                        <select id="modal_global_loc_id" class="form-control"
                                onchange="handleModalLocationChange(this.value)">
                            <option value="">— Choose Location —</option>
                            <?php foreach($locations as $l): ?>
                                <option value="<?= $l['LOCATION_ID'] ?>">
                                    <?= htmlspecialchars($l['LOCATION_NAME']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group" style="margin-bottom:1rem;">
                        <label class="form-label"><i class="fa-solid fa-building"></i> Building <span style="color:var(--red);">*</span></label>
                        <select id="modal_bldg_id" class="form-control"
                                onchange="handleModalBuildingChange(this.value)" disabled>
                            <option value="">— Select Location First —</option>
                        </select>
                    </div>

                    <div id="pen_picker_section" style="display:none;">
                        <label class="form-label" style="margin-bottom:8px;">
                            <i class="fa-solid fa-border-all"></i> Pens <span style="color:var(--red);">*</span>
                            <span id="pen_count_badge" style="color:var(--green);margin-left:4px;font-weight:800;"></span>
                        </label>

                        <div class="pen-select-all-row">
                            <label>
                                <input type="checkbox" id="pen_select_all" onchange="toggleAllPens(this)">
                                Select all pens
                            </label>
                            <span id="pen_selected_label" style="font-size:0.75rem;color:var(--text-muted);">0 selected</span>
                        </div>

                        <div id="pen_pill_list" class="pen-list"></div>
                        <div id="pen_animal_summary" class="pen-selected-badge"></div>
                    </div>

                    <div id="selection_tree" class="selection-tree">
                        <div class="tree-placeholder">
                            <i class="fa-solid fa-building" style="display:block;font-size:1.5rem;margin-bottom:0.5rem;opacity:0.3;"></i>
                            Select a building to load pens.
                        </div>
                    </div>
                </div>

                <form id="addEventForm" class="modal-right">
                    <div class="modal-section-label">
                        <i class="fa-solid fa-clipboard-list"></i>
                        2. Event Details
                    </div>

                    <input type="hidden" name="action"     value="save_batch_event">
                    <input type="hidden" name="animal_ids" id="selected_animal_ids">

                    <div class="form-group" style="margin-bottom:1rem;">
                        <label class="form-label"><i class="fa-solid fa-tags"></i> Operation Type</label>
                        <select name="event_type" id="modal_event_type" class="form-control"
                                onchange="loadItemsByLocation()" required disabled>
                            <option value="">— Select Type —</option>
                            <option value="Medication">Medication</option>
                            <option value="Vitamins">Vitamins</option>
                            <option value="Vaccination">Vaccination</option>
                            <option value="Checkup">Checkup</option>
                        </select>
                    </div>

                    <div class="form-group hidden" id="item_group" style="margin-bottom:1rem;">
                        <label class="form-label" id="item_label"><i class="fa-solid fa-box"></i> Item / Supply</label>
                        <select name="item_id" id="item_id" class="form-control">
                            <option value="">— Select Item —</option>
                        </select>
                    </div>

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem;">
                        <div class="form-group">
                            <label class="form-label"><i class="fa-solid fa-calendar-day"></i> Start Date</label>
                            <input type="text" name="start_date" id="start_date" class="form-control datetime-picker" required>
                        </div>
                        <div class="form-group" id="group_end_date">
                            <label class="form-label"><i class="fa-solid fa-calendar-check"></i> End Date (Deadline) <span style="color:var(--red);">*</span></label>
                            <input type="text" name="end_date" id="end_date" class="form-control datetime-picker" required>
                        </div>
                    </div>

                    <div class="form-group" style="border-top:1px solid var(--border);padding-top:1rem;margin-bottom:0.5rem;">
                        <label style="display:flex;align-items:center;gap:8px;font-size:0.82rem;color:var(--text-secondary);cursor:pointer;">
                            <input type="checkbox" style="accent-color:var(--green);"
                                   onchange="document.getElementById('group_interval').classList.toggle('hidden',!this.checked)">
                            Enable Recurring Interval
                        </label>
                    </div>
                    <div class="form-group hidden" id="group_interval">
                        <label class="form-label"><i class="fa-solid fa-rotate"></i> Repeat Every (Days)</label>
                        <input type="number" name="interval_days" class="form-control" placeholder="e.g. 7" min="1">
                    </div>
                </form>
            </div>
        </div>

        <div class="modal-footer">
            <button class="btn btn-ghost" onclick="closeModal('addModal')">Cancel</button>
            <button class="btn btn-primary" onclick="submitAddEvent()">
                <i class="fa-solid fa-floppy-disk"></i> Save Schedule
            </button>
        </div>
    </div>
</div>

<!-- ═══════════════ ARCHIVE MODAL ═══════════════ -->
<div id="archiveModal" class="modal-overlay">
    <div class="modal-card modal-sm">
        <div class="modal-header">
            <div class="modal-title" style="color:var(--red);">
                <i class="fa-solid fa-box-archive" style="color:var(--red);"></i>
                Archive Events
            </div>
            <button class="modal-close" onclick="closeModal('archiveModal')">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <div class="modal-body" style="padding:1.5rem;">
            <div class="tab-row">
                <button class="tab-btn active" id="tab-btn-selection" onclick="switchTab('tab-selection')">By Selection</button>
                <button class="tab-btn" id="tab-btn-filter" onclick="switchTab('tab-filter')">By Criteria</button>
            </div>

            <div id="tab-selection" class="tab-content active">
                <p style="color:var(--text-secondary);font-size:0.875rem;margin-bottom:1.25rem;">
                    You have selected <strong id="modalSelectedCount" style="color:var(--text-primary);">0</strong> events to archive.
                </p>
                <button class="btn btn-danger-outline" style="width:100%;" onclick="confirmArchive('selection')">
                    <i class="fa-solid fa-box-archive"></i> Archive Selected
                </button>
            </div>

            <div id="tab-filter" class="tab-content">
                <form id="filterArchiveForm">
                    <input type="hidden" name="action" value="bulk_delete">

                    <div class="form-group" style="margin-bottom:1rem;">
                        <label class="form-label"><i class="fa-solid fa-calendar-days"></i> Date Range</label>
                        <div class="input-row">
                            <input type="text" name="del_start_date" class="form-control date-picker" value="<?= date('Y-01-01') ?>" required>
                            <input type="text" name="del_end_date"   class="form-control date-picker" value="<?= date('Y-m-d') ?>" required>
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom:1rem;">
                        <label class="form-label"><i class="fa-solid fa-map-pin"></i> Location</label>
                        <select name="del_loc_id" class="form-control" onchange="loadArchiveBuildings(this.value)">
                            <option value="">All Locations</option>
                            <?php foreach($locations as $l): ?>
                                <option value="<?= $l['LOCATION_ID'] ?>"><?= htmlspecialchars($l['LOCATION_NAME']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group" style="margin-bottom:1.25rem;">
                        <label class="form-label"><i class="fa-solid fa-building"></i> Building</label>
                        <select name="del_bldg_id" id="del_bldg_id" class="form-control" disabled>
                            <option value="">All Buildings</option>
                        </select>
                    </div>

                    <button type="button" class="btn btn-danger-outline" style="width:100%;" onclick="confirmArchive('filter')">
                        <i class="fa-solid fa-box-archive"></i> Archive Matches
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
/* ──────────────────────────────────────────────────
   FLATPICKR
────────────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {
    flatpickr(".date-picker", {
        dateFormat: "Y-m-d",
        altInput: true,
        altFormat: "m/d/Y",
        allowInput: true
    });

    flatpickr(".datetime-picker", {
        enableTime: true,
        enableSeconds: true,
        dateFormat: "Y-m-d H:i:S",
        altInput: true,
        altFormat: "m/d/Y h:i K",
        allowInput: true
    });
});

/* ──────────────────────────────────────────────────
   FILTER TOGGLE
────────────────────────────────────────────────── */
let filterOpen = true;
function toggleFilters() {
    filterOpen = !filterOpen;
    const body    = document.getElementById('filterBody');
    const btn     = document.getElementById('filterToggleBtn');
    const label   = document.getElementById('filterToggleLabel');
    body.classList.toggle('hidden', !filterOpen);
    btn.classList.toggle('collapsed', !filterOpen);
    label.textContent = filterOpen ? 'Collapse' : 'Expand';
}

/* ──────────────────────────────────────────────────
   TOAST
────────────────────────────────────────────────── */
function showToast(msg, type = 'success') {
    const toast = document.createElement('div');
    toast.className = `toast${type==='error'?' error':''}`;
    toast.innerHTML = type === 'error'
        ? `<i class="fa-solid fa-circle-xmark" style="margin-right:6px;"></i>${msg}`
        : `<i class="fa-solid fa-circle-check" style="margin-right:6px;color:var(--green);"></i>${msg}`;
    document.getElementById('toastContainer').appendChild(toast);
    setTimeout(() => toast.remove(), 3500);
}

/* ──────────────────────────────────────────────────
   MODAL HELPERS
────────────────────────────────────────────────── */
function openModal(id) {
    document.getElementById(id).classList.add('open');
    if (id === 'addModal') {
        const sp = document.getElementById('start_date');
        if(sp && sp._flatpickr) sp._flatpickr.setDate(new Date());
    }
}

function closeModal(id) {
    document.getElementById(id).classList.remove('open');
    if (id === 'addModal') resetAddModal();
}

function openAddModal()     { openModal('addModal'); }
function openArchiveModal() {
    document.getElementById('modalSelectedCount').innerText =
        document.querySelectorAll('.row-chk:checked').length;
    openModal('archiveModal');
}

function resetAddModal() {
    document.getElementById('addEventForm').reset();
    document.getElementById('modal_global_loc_id').value = '';

    const bldg = document.getElementById('modal_bldg_id');
    bldg.innerHTML = '<option value="">— Select Location First —</option>';
    bldg.disabled  = true;

    document.getElementById('modal_event_type').disabled = true;
    document.getElementById('item_group').classList.add('hidden');
    document.getElementById('pen_picker_section').style.display = 'none';
    document.getElementById('pen_pill_list').innerHTML = '';
    document.getElementById('selection_tree').innerHTML =
        '<div class="tree-placeholder"><i class="fa-solid fa-building" style="display:block;font-size:1.5rem;margin-bottom:0.5rem;opacity:0.3;"></i>Select a building to load pens.</div>';

    if(document.getElementById('end_date')._flatpickr)
        document.getElementById('end_date')._flatpickr.clear();

    currentBuildingData = [];
    selectedPenIds.clear();
    updatePenSummary();
}

function switchTab(tabId) {
    document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
    document.getElementById(tabId).classList.add('active');
    const btnId = tabId === 'tab-selection' ? 'tab-btn-selection' : 'tab-btn-filter';
    document.getElementById(btnId).classList.add('active');
}

/* ──────────────────────────────────────────────────
   STATE
────────────────────────────────────────────────── */
let currentBuildingData = [];
let selectedPenIds      = new Set();

/* ──────────────────────────────────────────────────
   CASCADE: Location → Buildings
────────────────────────────────────────────────── */
function handleModalLocationChange(locId) {
    const bldg = document.getElementById('modal_bldg_id');
    bldg.innerHTML = '<option value="">— Select Building —</option>';
    bldg.disabled  = !locId;

    document.getElementById('pen_picker_section').style.display = 'none';
    document.getElementById('pen_pill_list').innerHTML = '';
    document.getElementById('selection_tree').innerHTML =
        '<div class="tree-placeholder"><i class="fa-solid fa-building" style="display:block;font-size:1.5rem;margin-bottom:0.5rem;opacity:0.3;"></i>Select a building to load pens.</div>';

    document.getElementById('modal_event_type').disabled = !locId;
    selectedPenIds.clear();
    updatePenSummary();
    currentBuildingData = [];

    if (!locId) return;

    fetch(`../process/eventManager.php?action=get_buildings_filter&loc_id=${locId}`)
        .then(r => r.json())
        .then(data => data.forEach(b => bldg.add(new Option(b.BUILDING_NAME, b.BUILDING_ID))));

    loadItemsByLocation();
}

/* ──────────────────────────────────────────────────
   CASCADE: Building → Pen pills
────────────────────────────────────────────────── */
async function handleModalBuildingChange(bldgId) {
    const pillList      = document.getElementById('pen_pill_list');
    const pickerSection = document.getElementById('pen_picker_section');
    const tree          = document.getElementById('selection_tree');

    pickerSection.style.display = 'none';
    pillList.innerHTML = '';
    tree.innerHTML = '<div class="tree-placeholder">Select pens to load animals.</div>';
    selectedPenIds.clear();
    updatePenSummary();

    if (!bldgId) return;

    pillList.innerHTML = '<div style="color:var(--text-muted);font-size:0.83rem;padding:8px 0;">Loading pens…</div>';
    pickerSection.style.display = 'block';

    try {
        const res = await fetch(`../process/eventManager.php?action=get_building_population&bldg_id=${bldgId}`);
        currentBuildingData = await res.json();
        pillList.innerHTML = '';

        if (!currentBuildingData.length) {
            pillList.innerHTML = '<div style="color:var(--text-muted);font-size:0.83rem;">No pens found.</div>';
            return;
        }

        document.getElementById('pen_count_badge').textContent = `(${currentBuildingData.length})`;

        currentBuildingData.forEach(pen => {
            const pill = document.createElement('label');
            pill.className = 'pen-pill';
            pill.dataset.penId = pen.PEN_ID;
            pill.innerHTML = `
                <input type="checkbox" value="${pen.PEN_ID}" onchange="handlePenToggle(this)">
                <span class="pen-pill-name">${pen.PEN_NAME}</span>
                <span class="pen-pill-count">${pen.animals.length}</span>`;
            pillList.appendChild(pill);
        });

        updateSelectAllPenState();
    } catch(e) {
        showToast('Failed to load pens', 'error');
        pillList.innerHTML = '<div style="color:var(--red);font-size:0.83rem;">Error loading pens.</div>';
    }
}

/* ──────────────────────────────────────────────────
   PEN TOGGLE
────────────────────────────────────────────────── */
function handlePenToggle(checkbox) {
    const penId = String(checkbox.value);
    const pill  = checkbox.closest('.pen-pill');

    if (checkbox.checked) {
        selectedPenIds.add(penId);
        pill.classList.add('selected');
        addPenToTree(penId);
    } else {
        selectedPenIds.delete(penId);
        pill.classList.remove('selected');
        removePenFromTree(penId);
    }
    updateSelectAllPenState();
    updatePenSummary();
}

function toggleAllPens(masterCb) {
    document.querySelectorAll('#pen_pill_list input[type="checkbox"]').forEach(cb => {
        if (cb.checked !== masterCb.checked) { cb.checked = masterCb.checked; handlePenToggle(cb); }
    });
}

function updateSelectAllPenState() {
    const all     = document.querySelectorAll('#pen_pill_list input[type="checkbox"]');
    const checked = document.querySelectorAll('#pen_pill_list input[type="checkbox"]:checked');
    const master  = document.getElementById('pen_select_all');
    if (!master) return;
    master.checked       = all.length > 0 && checked.length === all.length;
    master.indeterminate = checked.length > 0 && checked.length < all.length;
    document.getElementById('pen_selected_label').textContent = `${checked.length} of ${all.length} selected`;
}

function updatePenSummary() {
    const summary      = document.getElementById('pen_animal_summary');
    const totalAnimals = document.querySelectorAll('.an-chk').length;
    const checked      = document.querySelectorAll('.an-chk:checked').length;

    if (selectedPenIds.size === 0) { summary.classList.remove('show'); return; }
    summary.classList.add('show');
    summary.textContent = `${selectedPenIds.size} pen(s) · ${totalAnimals} available · ${checked} selected`;
}

/* ──────────────────────────────────────────────────
   TREE: add / remove pen groups
────────────────────────────────────────────────── */
function addPenToTree(penId) {
    const tree = document.getElementById('selection_tree');
    const placeholder = tree.querySelector('[data-placeholder]');
    if (placeholder) placeholder.remove();
    if (document.getElementById(`tree-pen-${penId}`)) return;

    const pen = currentBuildingData.find(p => String(p.PEN_ID) === penId);
    if (!pen || pen.animals.length === 0) return;

    const animalsHtml = pen.animals.map(a => `
        <label class="chk-label">
            <input type="checkbox" class="an-chk pen-${pen.PEN_ID}" value="${a.ANIMAL_ID}" onchange="updatePenSummary()">
            ${a.TAG_NO}
        </label>`).join('');

    const group = document.createElement('div');
    group.className = 'pen-group';
    group.id        = `tree-pen-${penId}`;
    group.innerHTML = `
        <div class="pen-header" onclick="togglePenBody(this)">
            <input type="checkbox" onchange="togglePenAll(this, ${pen.PEN_ID})" onclick="event.stopPropagation()" style="accent-color:var(--green);">
            <span style="flex:1;">${pen.PEN_NAME}</span>
            <small style="opacity:0.5;font-size:0.72rem;">${pen.animals.length} animals</small>
        </div>
        <div class="pen-body open">${animalsHtml}</div>`;
    tree.appendChild(group);
    updatePenSummary();
}

function removePenFromTree(penId) {
    const group = document.getElementById(`tree-pen-${penId}`);
    if (group) group.remove();

    const tree = document.getElementById('selection_tree');
    if (!tree.querySelector('.pen-group')) {
        tree.innerHTML = '<div class="tree-placeholder" data-placeholder><i class="fa-solid fa-border-all" style="display:block;font-size:1.5rem;margin-bottom:0.5rem;opacity:0.3;"></i>Check pens above to load their animals.</div>';
    }
    updatePenSummary();
}

function togglePenBody(header) { header.nextElementSibling.classList.toggle('open'); }
function togglePenAll(cb, penId) {
    document.querySelectorAll(`.pen-${penId}`).forEach(el => el.checked = cb.checked);
    updatePenSummary();
}

/* ──────────────────────────────────────────────────
   ITEMS BY LOCATION
────────────────────────────────────────────────── */
async function loadItemsByLocation() {
    const locId = document.getElementById('modal_global_loc_id').value;
    const type  = document.getElementById('modal_event_type').value;
    const grp   = document.getElementById('item_group');
    const sel   = document.getElementById('item_id');

    if (!type || type === 'Checkup') { grp.classList.add('hidden'); return; }
    grp.classList.remove('hidden');
    sel.innerHTML = '<option>Loading…</option>';

    try {
        const res  = await fetch(`../process/eventManager.php?action=get_items_by_loc&type=${type}&loc_id=${locId}`);
        const data = await res.json();
        sel.innerHTML = `<option value="">— Select ${type} —</option>`;
        if (!data.length) {
            sel.innerHTML = `<option value="">No ${type} available</option>`;
        } else {
            data.forEach(i => sel.add(new Option(`${i.name} (Stock: ${i.qty || 0})`, i.id)));
        }
    } catch(e) {
        showToast('Failed to load items', 'error');
        sel.innerHTML = '<option value="">Error</option>';
    }
}

/* ──────────────────────────────────────────────────
   SUBMIT
────────────────────────────────────────────────── */
async function submitAddEvent() {
    const form = document.getElementById('addEventForm');
    const btn  = document.querySelector('#addModal .modal-footer .btn-primary');
    const orig = btn.innerHTML;

    if (!form.checkValidity()) { form.reportValidity(); return; }

    const ids = Array.from(document.querySelectorAll('.an-chk:checked')).map(cb => cb.value);
    if (ids.length === 0) { showToast('Select at least one animal', 'error'); return; }

    document.getElementById('selected_animal_ids').value = ids.join(',');
    btn.disabled = true; btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin" style="font-size:0.75rem;"></i> Saving…';

    const fd = new FormData(form);

    try {
        const res  = await fetch('../process/eventManager.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            showToast(data.message);
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast(data.message, 'error');
            btn.disabled = false; btn.innerHTML = orig;
        }
    } catch(e) {
        showToast('Failed to save event', 'error');
        btn.disabled = false; btn.innerHTML = orig;
    }
}

/* ──────────────────────────────────────────────────
   PROCESS SELECTED
────────────────────────────────────────────────── */
function processSelectedEvents() {
    const checked = document.querySelectorAll('.row-chk:checked');
    if (!checked.length) return;

    let type = null, mixed = false, ids = [];
    checked.forEach(cb => {
        ids.push(cb.value);
        const t = cb.getAttribute('data-type');
        if (!type) type = t;
        else if (type !== t) mixed = true;
    });

    if (mixed) { showToast('Select events of the SAME TYPE to process together.', 'error'); return; }

    const pages = { Vaccination:'group_vaccination.php', Medication:'group_medication.php',
                    Vitamins:'group_vitamins.php', Checkup:'group_checkup.php' };
    const page = pages[type];
    if (page) window.location.href = `${page}?event_ids=${ids.join(',')}`;
    else showToast('Routing not configured for this type.', 'error');
}

/* ──────────────────────────────────────────────────
   BULK OPS
────────────────────────────────────────────────── */
function toggleSelectAll(cb) {
    document.querySelectorAll('.row-chk:not(:disabled)').forEach(el => el.checked = cb.checked);
    updateActionBar();
}

function updateActionBar() {
    const checked = document.querySelectorAll('.row-chk:checked');
    const count   = checked.length;
    document.getElementById('selectedCount').innerText = count;

    const bar        = document.getElementById('bulkActionBar');
    const btnDone    = document.querySelector('.btn-mark-done');
    const btnProcess = document.querySelector('.btn-process');

    if (count > 0) {
        bar.classList.add('active');
        const missingRecords = Array.from(checked).some(cb => cb.getAttribute('data-has-record') === '0');
        if (missingRecords) {
            btnDone.disabled  = true;
            btnDone.style.opacity = '0.45';
            btnDone.setAttribute('data-locked', '1');
            btnProcess.style.boxShadow = '0 0 16px var(--green-glow)';
        } else {
            btnDone.disabled  = false;
            btnDone.style.opacity = '1';
            btnDone.setAttribute('data-locked', '0');
            btnProcess.style.boxShadow = '';
        }
    } else {
        bar.classList.remove('active');
        btnDone.setAttribute('data-locked', '0');
    }
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.row-chk').forEach(el => el.addEventListener('change', updateActionBar));

    const btnDone = document.querySelector('.btn-mark-done');
    if (btnDone) {
        new MutationObserver(() => {
            if (btnDone.getAttribute('data-locked') === '1' && !btnDone.disabled) {
                btnDone.disabled = true;
            }
        }).observe(btnDone, { attributes: true });
    }
});

async function bulkUpdate(status) {
    const btn = document.querySelector('.btn-mark-done');
    if (status === 'Done' && btn.getAttribute('data-locked') === '1') {
        showToast('Cannot mark Done — work records required.', 'error'); return;
    }
    const ids = Array.from(document.querySelectorAll('.row-chk:checked')).map(cb => cb.value);
    if (!ids.length) { showToast('No events selected', 'error'); return; }
    if (!confirm(`Update ${ids.length} event(s) to ${status}?`)) return;

    const fd = new FormData();
    fd.append('action', 'bulk_update_status');
    fd.append('ids', ids.join(','));
    fd.append('status', status);

    try {
        const res  = await fetch('../process/eventManager.php', { method:'POST', body:fd });
        const data = await res.json();
        if (data.success) { showToast(data.message); setTimeout(() => location.reload(), 1000); }
        else showToast(data.message, 'error');
    } catch(e) { showToast('Failed to update events', 'error'); }
}

async function deleteSelected() {
    const ids = Array.from(document.querySelectorAll('.row-chk:checked')).map(cb => cb.value);
    if (!ids.length) { showToast('No events selected', 'error'); return; }
    if (!confirm(`Archive ${ids.length} event(s)?`)) return;

    const fd = new FormData();
    fd.append('action', 'bulk_delete');
    fd.append('ids_to_delete', ids.join(','));

    try {
        const res  = await fetch('../process/eventManager.php', { method:'POST', body:fd });
        const data = await res.json();
        if (data.success) { showToast(data.message); setTimeout(() => location.reload(), 1000); }
        else showToast(data.message, 'error');
    } catch(e) { showToast('Failed to archive', 'error'); }
}

async function confirmArchive(mode) {
    const fd = new FormData();
    fd.append('action', 'bulk_delete');
    if (mode === 'selection') {
        const ids = Array.from(document.querySelectorAll('.row-chk:checked')).map(cb => cb.value);
        if (!ids.length) { showToast('No events selected', 'error'); return; }
        fd.append('ids_to_delete', ids.join(','));
        if (!confirm(`Archive ${ids.length} selected event(s)?`)) return;
    } else {
        const form = document.getElementById('filterArchiveForm');
        if (!form.checkValidity()) { form.reportValidity(); return; }
        for (let [k,v] of new FormData(form)) fd.append(k, v);
        if (!confirm('Archive all matching events?')) return;
    }
    try {
        const res  = await fetch('../process/eventManager.php', { method:'POST', body:fd });
        const data = await res.json();
        if (data.success) { showToast(data.message); closeModal('archiveModal'); setTimeout(() => location.reload(), 1000); }
        else showToast(data.message, 'error');
    } catch(e) { showToast('Failed to archive events', 'error'); }
}

async function loadArchiveBuildings(locId) {
    const sel = document.getElementById('del_bldg_id');
    sel.disabled = true; sel.innerHTML = '<option>Loading…</option>';
    if (!locId) { sel.innerHTML = '<option value="">All Buildings</option>'; sel.disabled = false; return; }
    try {
        const res  = await fetch(`../process/eventManager.php?action=get_buildings_filter&loc_id=${locId}`);
        const data = await res.json();
        sel.innerHTML = '<option value="">All Buildings</option>';
        data.forEach(b => sel.add(new Option(b.BUILDING_NAME, b.BUILDING_ID)));
        sel.disabled = false;
    } catch(e) { showToast('Failed to load buildings', 'error'); sel.innerHTML = '<option value="">Error</option>'; }
}
</script>
</body>
</html>