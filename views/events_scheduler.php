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

// 1. Initial Data Load
$locations = $conn->query("SELECT * FROM locations ORDER BY LOCATION_NAME")->fetchAll(PDO::FETCH_ASSOC);

// 2. Capture Filter Inputs
$selected_loc = $_GET['loc_id'] ?? '';
$selected_bldg = $_GET['bldg_id'] ?? '';
$selected_type = $_GET['type'] ?? '';
$search_query = $_GET['search'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$show_history = isset($_GET['show_history']) ? true : false;

// --- PAGINATION INPUTS ---
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50; // Default 50 rows
$page_no = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page_no < 1) $page_no = 1;
$offset = ($page_no - 1) * $limit;

// 3. Build Query Logic
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
    $params[] = $date_to . " 23:59:59";
}

if ($search_query) {
    $term = "%$search_query%";
    $filter_clause .= " AND (a.TAG_NO LIKE ? OR m.SUPPLY_NAME LIKE ? OR vs.SUPPLY_NAME LIKE ? OR v.SUPPLY_NAME LIKE ?) ";
    $params[] = $term; $params[] = $term; $params[] = $term; $params[] = $term;
}

// --- 4. COUNT TOTAL ROWS (For Pagination) ---
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
$total_rows = $count_stmt->fetchColumn();
$total_pages = ceil($total_rows / $limit);

// --- 5. MAIN DATA QUERY ---
// Logic: Records count only if they happened AFTER the schedule was created
$sql = "SELECT e.*, a.TAG_NO, p.PEN_NAME, b.BUILDING_NAME,
        CASE 
            WHEN e.EVENT_TYPE = 'Medication' THEN m.SUPPLY_NAME
            WHEN e.EVENT_TYPE = 'Vitamins' THEN vs.SUPPLY_NAME
            WHEN e.EVENT_TYPE = 'Vaccination' THEN v.SUPPLY_NAME
            ELSE 'N/A' 
        END as ITEM_NAME,
        
        /* CHECK RECORDS EXISTENCE (Strict Time Check) */
        (CASE 
            WHEN e.EVENT_TYPE = 'Medication' THEN (
                SELECT COUNT(*) FROM treatment_transactions tt 
                WHERE tt.ANIMAL_ID = e.ANIMAL_ID AND tt.ITEM_ID = e.ITEM_ID 
                AND tt.TRANSACTION_DATE >= e.START_DATE
                AND tt.TRANSACTION_DATE >= DATE(e.CREATED_AT) 
            )
            WHEN e.EVENT_TYPE = 'Vaccination' THEN (
                SELECT COUNT(*) FROM vaccination_records vr 
                WHERE vr.ANIMAL_ID = e.ANIMAL_ID AND vr.ITEM_ID = e.ITEM_ID 
                AND vr.VACCINATION_DATE >= e.START_DATE
                AND vr.VACCINATION_DATE >= DATE(e.CREATED_AT)
            )
            WHEN e.EVENT_TYPE = 'Vitamins' THEN (
                SELECT COUNT(*) FROM vitamins_supplements_transactions vst 
                WHERE vst.ANIMAL_ID = e.ANIMAL_ID AND vst.ITEM_ID = e.ITEM_ID 
                AND vst.TRANSACTION_DATE >= e.START_DATE
                AND vst.TRANSACTION_DATE >= DATE(e.CREATED_AT)
            )
            WHEN e.EVENT_TYPE = 'Checkup' THEN (
                SELECT COUNT(*) FROM check_ups cu
                WHERE cu.ANIMAL_ID = e.ANIMAL_ID 
                AND cu.CHECKUP_DATE >= e.START_DATE
                AND cu.CHECKUP_DATE >= DATE(e.CREATED_AT)
            )
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

// Visual Configurations
$icons = ['Medication' => '💊', 'Vitamins' => '🍃', 'Vaccination' => '💉', 'Checkup' => '🩺'];
$badges = ['Medication' => 'badge-med', 'Vitamins' => 'badge-vit', 'Vaccination' => 'badge-vac'];
$links  = ['Medication' => 'group_medication.php', 'Vitamins' => 'group_vitamins.php', 'Vaccination' => 'group_vaccination.php', 'Checkup' => 'group_checkup.php'];

// Helper to keep query params in pagination links
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
    <title>Event Scheduler | FarmPro</title>
    
    <style>
        :root {
            --primary: #f43f5e; --primary-dark: #e11d48; --bg-body: #0f172a; --bg-card: #1e293b; --bg-input: #0f172a;
            --border: #334155; --text-main: #f1f5f9; --text-muted: #94a3b8;
            --success: #22c55e; --warning: #facc15; --danger: #ef4444; --radius: 12px;
        }
        * { box-sizing: border-box; outline: none; -webkit-tap-highlight-color: transparent; }
        body { font-family: 'Inter', system-ui, sans-serif; background: linear-gradient(135deg, var(--bg-body) 0%, #1e293b 100%); color: var(--text-main); margin: 0; padding-bottom: 100px; }
        .container { max-width: 1400px; margin: 0 auto; padding: 1.5rem; width: 100%; }
        .hidden { display: none !important; }
        .flex { display: flex; } .gap-2 { gap: 0.5rem; }

        /* Header & Filters */
        .page-header { margin-bottom: 2rem; display: flex; flex-wrap: wrap; gap: 1rem; justify-content: space-between; align-items: center; }
        .page-title h1 { font-size: 1.75rem; font-weight: 800; margin: 0; color: white; }
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 0.75rem 1.25rem; border-radius: var(--radius); font-weight: 600; font-size: 0.9rem; cursor: pointer; border: 1px solid transparent; transition: all 0.2s; text-decoration: none; }
        .btn:active { transform: scale(0.98); }
        .btn:disabled { opacity: 0.6; cursor: not-allowed; pointer-events: none; filter: grayscale(0.5); }
        .btn-primary { background: var(--primary); color: white; box-shadow: 0 4px 12px rgba(244, 63, 94, 0.25); }
        .btn-outline { background: transparent; border-color: var(--border); color: var(--text-muted); }
        .btn-outline:hover { border-color: var(--text-muted); color: white; background: rgba(255,255,255,0.05); }
        .btn-danger { background: rgba(239, 68, 68, 0.15); color: var(--danger); border-color: rgba(239, 68, 68, 0.2); }
        .btn-group { display: flex; gap: 10px; flex-wrap: wrap; }

        .filter-container { background: rgba(30, 41, 59, 0.6); backdrop-filter: blur(10px); padding: 1.5rem; border-radius: 16px; border: 1px solid var(--border); margin-bottom: 1.5rem; }
        .filter-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; align-items: end; }
        .span-2 { grid-column: span 2; } @media(max-width: 768px) { .span-2 { grid-column: span 1; } }
        .form-group label { display: block; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: var(--text-muted); margin-bottom: 0.5rem; }
        .form-control { width: 100%; padding: 0.75rem 1rem; background: var(--bg-input); border: 1px solid var(--border); border-radius: 8px; color: white; font-size: 0.95rem; height: 46px; }
        .filter-actions { display: flex; gap: 10px; margin-top: 1rem; padding-top: 1rem; border-top: 1px solid rgba(255,255,255,0.05); justify-content: space-between; align-items: center; }
        .history-toggle { display: flex; align-items: center; gap: 8px; cursor: pointer; user-select: none; }

        /* Table */
        .table-container { background: var(--bg-card); border-radius: 16px; border: 1px solid var(--border); overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); }
        .table { width: 100%; border-collapse: collapse; min-width: 900px; }
        .table th { background: rgba(15, 23, 42, 0.8); color: var(--text-muted); padding: 1.25rem 1rem; text-align: left; font-size: 0.75rem; text-transform: uppercase; font-weight: 700; border-bottom: 1px solid var(--border); }
        .table td { padding: 1.25rem 1rem; border-bottom: 1px solid rgba(255,255,255,0.05); color: var(--text-main); vertical-align: middle; }
        
        .group-header td { 
            background: #0f172a; color: var(--primary); font-weight: 800; 
            padding: 1rem 1.5rem; font-size: 0.95rem; border-bottom: 2px solid var(--border); 
            text-transform: uppercase; letter-spacing: 0.5px;
        }

        .badge { padding: 6px 12px; border-radius: 8px; font-size: 0.75rem; font-weight: 700; background: rgba(255,255,255,0.08); white-space: nowrap; display: inline-flex; align-items: center; gap: 6px; text-decoration: none; border: 1px solid rgba(255,255,255,0.1); transition: all 0.2s; }
        .badge:hover { filter: brightness(1.2); border-color: rgba(255,255,255,0.3); transform: translateY(-1px); }
        .badge-med { color: #60a5fa; background: rgba(59, 130, 246, 0.1); border-color: rgba(59, 130, 246, 0.2); }
        .badge-vit { color: #34d399; background: rgba(16, 185, 129, 0.1); border-color: rgba(16, 185, 129, 0.2); }
        .badge-vac { color: #fb7185; background: rgba(244, 63, 94, 0.1); border-color: rgba(244, 63, 94, 0.2); }

        .status-badge { padding: 6px 12px; border-radius: 6px; font-size: 0.8rem; font-weight: 600; text-transform: uppercase; display: inline-block; text-align: center; min-width: 100px; }
        .status-Pending { background: rgba(250, 204, 21, 0.1); color: #facc15; border: 1px solid rgba(250, 204, 21, 0.3); }
        .status-Done { background: rgba(34, 197, 94, 0.1); color: #4ade80; border: 1px solid rgba(34, 197, 94, 0.3); }
        .status-Cancelled { background: rgba(148, 163, 184, 0.1); color: #94a3b8; border: 1px solid rgba(148, 163, 184, 0.3); }
        
        .status-time { display: block; font-size: 0.7rem; color: var(--text-muted); margin-top: 5px; font-style: italic; }
        .late-tag { display:block; color:#f87171; font-weight:800; font-size:0.7rem; margin-top:3px; }
        .ontime-tag { display:block; color:#4ade80; font-weight:800; font-size:0.7rem; margin-top:3px; }

        .row-locked { background-color: rgba(34, 197, 94, 0.03); }
        .row-locked td { color: #64748b; }
        .row-locked input[type="checkbox"] { display: none; }

        /* Pagination & Bulk Action Bar */
        #bulkActionBar { position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%) translateY(200%); width: 90%; max-width: 600px; background: rgba(30, 41, 59, 0.95); backdrop-filter: blur(12px); padding: 1rem 1.5rem; border-radius: 16px; border: 1px solid rgba(255,255,255,0.1); display: flex; justify-content: space-between; align-items: center; z-index: 900; box-shadow: 0 20px 50px rgba(0,0,0,0.5); transition: transform 0.3s ease; }
        #bulkActionBar.active { transform: translateX(-50%) translateY(0); }
        .action-group { display: flex; gap: 8px; }
        .action-btn { padding: 8px 16px; border-radius: 8px; font-weight: 600; cursor: pointer; border: none; font-size: 0.85rem; }
        .btn-mark-done { background: var(--success); color: #052e16; }
        .btn-mark-cancel { background: #64748b; color: white; }
        .btn-delete-sel { background: transparent; color: #ef4444; border: 1px solid #ef4444; }

        /* Pagination Styles */
        .pagination-container { display: flex; justify-content: space-between; align-items: center; margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid var(--border); }
        .page-info { color: var(--text-muted); font-size: 0.9rem; }
        .pagination { display: flex; gap: 5px; list-style: none; margin: 0; padding: 0; }
        .pagination a { display: block; padding: 8px 12px; border-radius: 6px; background: rgba(255,255,255,0.05); color: var(--text-main); text-decoration: none; font-size: 0.85rem; border: 1px solid transparent; transition: all 0.2s; }
        .pagination a:hover { background: rgba(255,255,255,0.1); border-color: var(--border); }
        .pagination .active a { background: var(--primary); color: white; pointer-events: none; }
        .pagination .disabled a { opacity: 0.5; pointer-events: none; }

        /* Mobile */
        @media (max-width: 768px) {
            .container { padding: 1rem; padding-bottom: 120px; }
            .page-header { flex-direction: column; text-align: center; }
            .btn-group, .btn { width: 100%; justify-content: center; }
            .filter-actions { flex-direction: column; align-items: stretch; }
            .filter-actions .btn-group { width: 100%; justify-content: space-between; }
            .filter-actions .btn-group .btn { width: 48%; }
            
            .table-container { background: transparent; border: none; overflow: visible; box-shadow: none; }
            .table { min-width: 0; } 
            .table, .table tbody, .table tr, .table td { display: block; width: 100%; }
            .table thead { display: none; }
            .table tr { background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px; margin-bottom: 1rem; padding: 1rem; position: relative; }
            .table tr.row-locked { background: rgba(34, 197, 94, 0.05); border-color: rgba(34, 197, 94, 0.2); }
            .table td { padding: 0.5rem 0; border: none; display: flex; justify-content: space-between; align-items: center; text-align: right; }
            .table td::before { content: attr(data-label); font-weight: 700; color: var(--text-muted); font-size: 0.75rem; text-transform: uppercase; margin-right: 1rem; }
            .table td:first-child { padding-bottom: 1rem; border-bottom: 1px dashed rgba(255,255,255,0.1); margin-bottom: 0.5rem; height: 40px; }
            .table td:first-child::before { content: "Select"; }
            
            #bulkActionBar { width: calc(100% - 2rem); max-width: none; flex-direction: column; gap: 12px; bottom: 10px; padding: 1rem; }
            .action-group { width: 100%; display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
            .btn-delete-sel { grid-column: 1 / -1; }

            .pagination-container { flex-direction: column; gap: 1rem; text-align: center; }
        }

        /* Modal Styles */
        .event-modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.8); z-index: 1000; justify-content: center; align-items: center; padding: 1rem; backdrop-filter: blur(4px); }
        .event-modal-overlay.open { display: flex; }
        .event-modal-card { background: var(--bg-card); border-radius: 16px; width: 100%; max-width: 900px; max-height: 90vh; border: 1px solid var(--border); overflow: hidden; display: flex; flex-direction: column; }
        .event-modal-body { display: grid; grid-template-columns: 1fr 1.5fr; gap: 0; overflow: hidden; height: 100%; }
        .event-modal-left { padding: 1.5rem; border-right: 1px solid var(--border); background: rgba(15, 23, 42, 0.3); overflow-y: auto; }
        .event-modal-right { padding: 1.5rem; overflow-y: auto; }
        .event-modal-footer { padding: 1.5rem; border-top: 1px solid var(--border); display: flex; justify-content: flex-end; gap: 1rem; background: rgba(0,0,0,0.2); }
        @media(max-width: 768px) { .event-modal-body { display: flex !important; flex-direction: column; } .event-modal-left { border-right: none; border-bottom: 1px solid var(--border); max-height: 40vh; } .event-modal-right { max-height: 50vh; } }
        
        #toastContainer { position: fixed; top: 20px; right: 20px; z-index: 9999; display: flex; flex-direction: column; gap: 10px; }
        .toast { background: var(--bg-card); color: white; padding: 1rem 1.5rem; border-radius: 12px; border-left: 4px solid var(--success); box-shadow: 0 10px 25px rgba(0,0,0,0.3); }
        
        .selection-tree { margin-top: 1rem; display: flex; flex-direction: column; gap: 8px; }
        .pen-group { border: 1px solid var(--border); border-radius: 8px; overflow: hidden; }
        .pen-header { padding: 10px 14px; background: rgba(15, 23, 42, 0.8); display: flex; align-items: center; gap: 10px; cursor: pointer; transition: background 0.2s; }
        .pen-body { padding: 10px; display: none; grid-template-columns: repeat(auto-fill, minmax(100px, 1fr)); gap: 8px; background: rgba(0,0,0,0.2); }
        .pen-body.open { display: grid; }
        .chk-label { display: flex; align-items: center; gap: 6px; font-size: 0.85rem; color: var(--text-muted); padding: 6px 10px; background: rgba(255,255,255,0.03); border-radius: 6px; cursor: pointer; }
    </style>
</head>
<body>

<div id="toastContainer"></div>

<div class="container">
    <header class="page-header">
        <div class="page-title">
            <h1>📅 Event Scheduler</h1>
            <p>Manage health schedules, vaccinations, and medication.</p>
        </div>
        <div class="btn-group">
            <button class="btn btn-outline" onclick="openArchiveModal()"><span>📦</span> Archive (Bulk)</button>
            <button class="btn btn-primary" onclick="openAddModal()"><span>+</span> Schedule Event</button>
        </div>
    </header>

    <form method="GET" class="filter-container">
        <div class="filter-grid">
            <div class="form-group span-2">
                <label>Search (Tag No or Item Name)</label>
                <div class="search-wrapper">
                    <span class="search-icon" style="position:absolute; left:12px; top:50%; transform:translateY(-50%); opacity:0.5;">🔍</span>
                    <input type="text" name="search" class="form-control search-input" placeholder="e.g. 1001 or Vitamin A" value="<?= htmlspecialchars($search_query) ?>" style="padding-left:36px;">
                </div>
            </div>
            
            <div class="form-group">
                <label>Show Rows</label>
                <select name="limit" class="form-control" onchange="this.form.submit()">
                    <option value="50" <?= $limit == 50 ? 'selected' : '' ?>>50 Rows</option>
                    <option value="100" <?= $limit == 100 ? 'selected' : '' ?>>100 Rows</option>
                    <option value="200" <?= $limit == 200 ? 'selected' : '' ?>>200 Rows</option>
                    <option value="500" <?= $limit == 500 ? 'selected' : '' ?>>500 Rows</option>
                </select>
            </div>

            <div class="form-group">
                <label>Location</label>
                <select name="loc_id" class="form-control" onchange="this.form.submit()">
                    <option value="">All Locations</option>
                    <?php foreach($locations as $l): ?>
                        <option value="<?= $l['LOCATION_ID'] ?>" <?= $selected_loc == $l['LOCATION_ID'] ? 'selected' : '' ?>><?= htmlspecialchars($l['LOCATION_NAME']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Building</label>
                <select name="bldg_id" class="form-control" onchange="this.form.submit()" <?= !$selected_loc ? 'disabled' : '' ?>>
                    <option value="">All Buildings</option>
                    <?php if($selected_loc): 
                        $bldgs = $conn->prepare("SELECT * FROM buildings WHERE LOCATION_ID = ?");
                        $bldgs->execute([$selected_loc]);
                        while($b = $bldgs->fetch()): ?>
                            <option value="<?= $b['BUILDING_ID'] ?>" <?= $selected_bldg == $b['BUILDING_ID'] ? 'selected' : '' ?>><?= htmlspecialchars($b['BUILDING_NAME']) ?></option>
                    <?php endwhile; endif; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Event Type</label>
                <select name="type" class="form-control" onchange="this.form.submit()">
                    <option value="">All Types</option>
                    <option value="Medication" <?= $selected_type == 'Medication' ? 'selected' : '' ?>>Medication</option>
                    <option value="Vitamins" <?= $selected_type == 'Vitamins' ? 'selected' : '' ?>>Vitamins</option>
                    <option value="Vaccination" <?= $selected_type == 'Vaccination' ? 'selected' : '' ?>>Vaccination</option>
                    <option value="Checkup" <?= $selected_type == 'Checkup' ? 'selected' : '' ?>>Checkup</option>
                </select>
            </div>
            <div class="form-group span-2">
                <label>Date Range (Start)</label>
                <div class="flex gap-2">
                    <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($date_from) ?>">
                    <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($date_to) ?>">
                </div>
            </div>
        </div>
        <div class="filter-actions">
            <label class="history-toggle">
                <input type="checkbox" name="show_history" value="1" <?= $show_history ? 'checked' : '' ?> onchange="this.form.submit()">
                <span>Show History (Done/Cancelled)</span>
            </label>
            <div class="btn-group">
                <a href="events_scheduler.php" class="btn btn-outline" style="font-weight:400; font-size:0.85rem;">Reset</a>
                <button type="submit" class="btn btn-primary">Apply Filters</button>
            </div>
        </div>
    </form>

    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th style="width: 50px;">
                        <input type="checkbox" onchange="toggleSelectAll(this)" style="width:18px; height:18px; cursor:pointer;">
                    </th>
                    <th>Deadline (End Date)</th>
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
                    <tr class="empty-row">
                        <td colspan="8" style="text-align: center; padding: 4rem;">
                            <div style="font-size: 3rem; opacity: 0.3; margin-bottom: 1rem;">📭</div>
                            <h3 style="color:white; margin:0;">No Events Found</h3>
                            <p style="color:var(--text-muted);">Try adjusting your filters or search query.</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php 
                    $currentDateGroup = null;
                    foreach($events as $ev): 
                        $isDone = ($ev['STATUS'] === 'Done');
                        $isCancelled = ($ev['STATUS'] === 'Cancelled');
                        $canSelect = ($ev['STATUS'] === 'Pending');
                        $isLocked = ($isDone || $isCancelled);
                        $hasRecord = ($ev['RECORD_EXISTS'] ?? 0) > 0;

                        $icon = $icons[$ev['EVENT_TYPE']] ?? '📅';
                        $badgeClass = $badges[$ev['EVENT_TYPE']] ?? '';
                        $link = $links[$ev['EVENT_TYPE']] ?? '#';

                        // --- Date Grouping ---
                        $deadline = !empty($ev['END_DATE']) ? $ev['END_DATE'] : $ev['START_DATE'];
                        $formattedDateGroup = date('M d, Y', strtotime($deadline));

                        if ($formattedDateGroup !== $currentDateGroup) {
                            $currentDateGroup = $formattedDateGroup;
                            echo "<tr class='group-header'><td colspan='8'>📅 Due: $currentDateGroup</td></tr>";
                        }

                        // --- Late Calculation ---
                        $actualDate = ($isDone && !empty($ev['COMPLETED_AT'])) ? $ev['COMPLETED_AT'] : date('Y-m-d H:i:s');
                        $isLate = false;
                        $daysLate = 0;

                        if (strtotime($actualDate) > strtotime($deadline)) {
                            $diff = strtotime($actualDate) - strtotime($deadline);
                            $daysLate = floor($diff / (60 * 60 * 24));
                            if ($daysLate > 0) $isLate = true;
                        }
                    ?>
                    <tr class="<?= $isLocked ? 'row-locked' : '' ?>">
                        <td data-label="Select">
                            <?php if ($canSelect): ?>
                                <input type="checkbox" class="row-chk" value="<?= $ev['EVENT_ID'] ?>" data-has-record="<?= $hasRecord ? 1 : 0 ?>" style="width:20px; height:20px; accent-color:var(--primary); cursor:pointer;">
                            <?php elseif ($isLocked): ?>
                                <span style="font-size: 1.2em; opacity: 0.5;">🔒</span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Deadline">
                            <div class="date-text" style="color: <?= $isLocked ? 'inherit' : 'var(--primary)' ?>; font-weight: 700;">
                                <?= date('M d, Y', strtotime($deadline)) ?>
                                <span style="display:block; font-size:0.8em; color:var(--text-muted); font-weight:400;">
                                    <?= date('h:i A', strtotime($deadline)) ?>
                                </span>
                            </div>
                        </td>
                        <td data-label="Location">
                            <div style="font-weight:600;"><?= htmlspecialchars($ev['PEN_NAME'] ?? '-') ?></div>
                            <div style="font-size:0.8em; color:var(--text-muted);"><?= htmlspecialchars($ev['BUILDING_NAME'] ?? '') ?></div>
                        </td>
                        <td data-label="Tag No" style="font-family: monospace; font-size: 1rem; color:var(--warning);"><?= htmlspecialchars($ev['TAG_NO']) ?></td>
                        <td data-label="Type">
                            <a href="<?= $link ?>" class="badge <?= $badgeClass ?>">
                                <?= $icon . ' ' . $ev['EVENT_TYPE'] ?> ↗
                            </a>
                        </td>
                        <td data-label="Item"><?= htmlspecialchars($ev['ITEM_NAME']) ?></td>
                        <td data-label="Frequency">
                            <?php if($ev['INTERVAL_DAYS']): ?>
                                <span style="font-size:0.85em;">Every <?= $ev['INTERVAL_DAYS'] ?> days</span>
                            <?php else: ?>
                                <span style="font-size:0.85em; opacity:0.6;">One-time</span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Status">
                            <span class="status-badge status-<?= $ev['STATUS'] ?>">
                                <?= $ev['STATUS'] ?>
                            </span>
                            
                            <?php if ($ev['STATUS'] === 'Pending'): ?>
                                <?php if($isLate): ?>
                                    <span class="late-tag">Late: <?= $daysLate ?> days</span>
                                <?php endif; ?>
                            <?php elseif ($ev['STATUS'] === 'Done'): ?>
                                <?php if($isLate): ?>
                                    <span class="late-tag">Late (<?= $daysLate ?> days)</span>
                                <?php else: ?>
                                    <span class="ontime-tag">On Time</span>
                                <?php endif; ?>
                            <?php endif; ?>

                            <?php if($ev['STATUS'] !== 'Pending' && !empty($ev['COMPLETED_AT'])): ?>
                                <div class="status-time" style="text-align:center;">
                                    <?php if($isDone): ?>
                                        ✅ <?= date('M d H:i', strtotime($ev['COMPLETED_AT'])) ?>
                                    <?php elseif($isCancelled): ?>
                                        ⛔ <?= date('M d H:i', strtotime($ev['COMPLETED_AT'])) ?>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="pagination-container">
        <div class="page-info">
            Showing <strong><?= ($offset + 1) ?></strong> to <strong><?= min($offset + $limit, $total_rows) ?></strong> of <strong><?= $total_rows ?></strong> events
        </div>
        <ul class="pagination">
            <li class="<?= $page_no <= 1 ? 'disabled' : '' ?>">
                <a href="<?= getUrl($page_no - 1) ?>">« Previous</a>
            </li>
            
            <?php 
            $start_p = max(1, $page_no - 2);
            $end_p   = min($total_pages, $page_no + 2);
            
            for ($i = $start_p; $i <= $end_p; $i++): ?>
                <li class="<?= $page_no == $i ? 'active' : '' ?>">
                    <a href="<?= getUrl($i) ?>"><?= $i ?></a>
                </li>
            <?php endfor; ?>

            <li class="<?= $page_no >= $total_pages ? 'disabled' : '' ?>">
                <a href="<?= getUrl($page_no + 1) ?>">Next »</a>
            </li>
        </ul>
    </div>

</div>

<div id="bulkActionBar">
    <div style="color:white; font-weight:600; display:flex; align-items:center; gap:8px;">
        <span id="selectedCount" style="color:var(--primary); font-size:1.4rem; font-weight:800;">0</span> Selected
    </div>
    <div class="action-group">
        <button class="action-btn btn-mark-done" onclick="bulkUpdate('Done')">✓ Done</button>
        <button class="action-btn btn-mark-cancel" onclick="bulkUpdate('Cancelled')">⊘ Cancel</button>
        <button class="action-btn btn-delete-sel" onclick="deleteSelected()">🗑️ Archive</button>
    </div>
</div>

<div id="addModal" class="event-modal-overlay">
    <div class="event-modal-card">
        <div class="event-modal-header">
            <h3 style="margin:0; color: white;">📅 Schedule New Event</h3>
            <button onclick="closeModal('addModal')" style="background:none; border:none; color:var(--text-muted); cursor:pointer; font-size:1.5rem;">&times;</button>
        </div>
        <div class="event-modal-body">
            <div class="event-modal-left">
                <h4 style="margin-top:0; color:var(--primary); font-size:0.9rem; text-transform:uppercase;">1. Select Animals</h4>
                <div class="form-group" style="margin-bottom:1rem;">
                    <label>Filter Building</label>
                    <select id="modal_bldg_id" class="form-control" onchange="loadBuildingPopulation(this.value)">
                        <option value="">-- Select Building --</option>
                        <?php 
                        $all_bldgs = $conn->query("SELECT * FROM buildings ORDER BY BUILDING_NAME");
                        while($b = $all_bldgs->fetch()): ?>
                            <option value="<?= $b['BUILDING_ID'] ?>"><?= htmlspecialchars($b['BUILDING_NAME']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div id="selection_tree" class="selection-tree">
                    <div style="text-align:center; padding:2rem; color:var(--text-muted); font-size:0.9rem;">Select a building to load pens.</div>
                </div>
            </div>
            <form id="addEventForm" class="event-modal-right">
                <h4 style="margin-top:0; color:var(--primary); font-size:0.9rem; text-transform:uppercase; margin-bottom:1rem;">2. Event Details</h4>
                <input type="hidden" name="action" value="save_batch_event">
                <input type="hidden" name="animal_ids" id="selected_animal_ids">
                <div class="form-group" style="margin-bottom:1rem;">
                    <label>Operation Type</label>
                    <select name="event_type" class="form-control" onchange="loadItems(this.value)" required>
                        <option value="">-- Select Type --</option>
                        <option value="Medication">💊 Medication</option>
                        <option value="Vitamins">🍃 Vitamins</option>
                        <option value="Vaccination">💉 Vaccination</option>
                        <option value="Checkup">🩺 Checkup</option>
                    </select>
                </div>
                <div class="form-group hidden" id="item_group" style="margin-bottom:1rem;">
                    <label>Item / Supply</label>
                    <select name="item_id" id="item_id" class="form-control"><option value="">-- Select Item --</option></select>
                </div>
                <div class="form-group" style="margin-bottom:1rem;">
                    <label>Start Date</label>
                    <input type="datetime-local" name="start_date" id="start_date" class="form-control" step="1" required>
                </div>
                
                <input type="hidden" name="schedule_mode" id="schedule_mode_input" value="date">
                <div class="form-group" id="group_end_date">
                    <label>End Date (Deadline) <span style="color:#ef4444">*</span></label>
                    <input type="datetime-local" name="end_date" class="form-control" step="1" required>
                </div>
                
                <div class="form-group" style="margin-top:10px;">
                    <label style="color:#94a3b8; font-weight:400; font-size:0.8rem;">
                        <input type="checkbox" onchange="document.getElementById('group_interval').classList.toggle('hidden', !this.checked)">
                        Enable Recurring Interval?
                    </label>
                </div>
                <div class="form-group hidden" id="group_interval">
                    <label>Repeat Every (Days)</label>
                    <input type="number" name="interval_days" class="form-control" placeholder="e.g. 7" min="1">
                </div>
            </form>
        </div>
        <div class="event-modal-footer">
            <button class="btn btn-outline" onclick="closeModal('addModal')">Cancel</button>
            <button class="btn btn-primary" onclick="submitAddEvent()">Save Schedule</button>
        </div>
    </div>
</div>

<div id="archiveModal" class="event-modal-overlay">
    <div class="event-modal-card" style="max-width: 500px;">
        <div class="event-modal-header">
            <h3 style="margin:0; color: var(--danger);">📦 Archive Events</h3>
            <button onclick="closeModal('archiveModal')" style="background:none; border:none; color:var(--text-muted); cursor:pointer; font-size:1.5rem;">&times;</button>
        </div>
        <div class="event-modal-body" style="display: block; padding: 1.5rem;">
            <div style="display:flex; gap:0; margin-bottom:1.5rem; border-bottom:2px solid var(--border);">
                <button class="tab-btn active" id="tab-btn-selection" onclick="switchTab('tab-selection')">By Selection</button>
                <button class="tab-btn" id="tab-btn-filter" onclick="switchTab('tab-filter')">By Criteria</button>
            </div>
            <div id="tab-selection" class="tab-content active">
                <p style="color:var(--text-muted);">You have selected <strong id="modalSelectedCount" style="color:white;">0</strong> events to archive.</p>
                <button class="btn btn-danger w-full" onclick="confirmArchive('selection')">Archive Selected</button>
            </div>
            <div id="tab-filter" class="tab-content" style="display:none;">
                <form id="filterArchiveForm">
                    <input type="hidden" name="action" value="bulk_delete">
                    <div class="form-group" style="margin-bottom:1rem;">
                        <label>Date Range</label>
                        <div class="flex gap-2">
                            <input type="date" name="del_start_date" class="form-control" required>
                            <input type="date" name="del_end_date" class="form-control" required>
                        </div>
                    </div>
                    <div class="form-group" style="margin-bottom:1rem;">
                        <label>Location</label>
                        <select name="del_loc_id" class="form-control" onchange="loadArchiveBuildings(this.value)">
                            <option value="">All Locations</option>
                            <?php foreach($locations as $l): echo "<option value='{$l['LOCATION_ID']}'>{$l['LOCATION_NAME']}</option>"; endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom:1rem;">
                        <label>Building</label>
                        <select name="del_bldg_id" id="del_bldg_id" class="form-control" disabled><option value="">All Buildings</option></select>
                    </div>
                    <button type="button" class="btn btn-danger w-full" onclick="confirmArchive('filter')">Archive Matches</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// --- GLOBAL UTILS ---
function showToast(msg, type = 'success') {
    const toast = document.createElement('div');
    toast.className = 'toast';
    toast.style.borderLeftColor = type === 'error' ? 'var(--danger)' : 'var(--success)';
    toast.innerHTML = type === 'error' ? `❌ ${msg}` : `✅ ${msg}`;
    document.getElementById('toastContainer').appendChild(toast);
    setTimeout(() => toast.remove(), 3000);
}

// --- ANTI-TAMPER PROTECTION ---
document.addEventListener("DOMContentLoaded", () => {
    const doneBtn = document.querySelector('.btn-mark-done');
    if(doneBtn) {
        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                if (mutation.attributeName === "disabled") {
                    const isLocked = doneBtn.getAttribute('data-locked') === "1";
                    if (isLocked && !doneBtn.disabled) {
                        doneBtn.disabled = true;
                        doneBtn.innerText = "⛔ Tampering Blocked";
                    }
                }
            });
        });
        observer.observe(doneBtn, { attributes: true });
    }
});

// --- MODAL UTILS ---
function openModal(id) {
    document.getElementById(id).classList.add('open');
    if(id === 'addModal') {
        const now = new Date();
        now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
        document.getElementById('start_date').value = now.toISOString().slice(0, 19);
    }
}
function closeModal(id) {
    document.getElementById(id).classList.remove('open');
    if(id === 'addModal') {
        document.getElementById('addEventForm').reset();
        document.getElementById('selection_tree').innerHTML = '<div style="text-align:center; padding:2rem; color:var(--text-muted); font-size:0.9rem;">Select a building to load pens.</div>';
        document.getElementById('modal_bldg_id').value = '';
        document.getElementById('item_group').classList.add('hidden');
    }
}
function openAddModal() { openModal('addModal'); }
function openArchiveModal() {
    const count = document.querySelectorAll('.row-chk:checked').length;
    document.getElementById('modalSelectedCount').innerText = count;
    openModal('archiveModal');
}

function switchTab(tabId) {
    document.querySelectorAll('.tab-content').forEach(el => el.style.display = 'none');
    document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
    document.getElementById(tabId).style.display = 'block';
    if(tabId === 'tab-selection') document.getElementById('tab-btn-selection').classList.add('active');
    else document.getElementById('tab-btn-filter').classList.add('active');
}

// --- FORM DYNAMICS ---
function toggleScheduleMode(mode) {
    document.getElementById('schedule_mode_input').value = mode;
    const btnDate = document.getElementById('mode_date');
    const btnInt = document.getElementById('mode_interval');
    if (mode === 'date') {
        btnDate.classList.add('btn-primary'); btnDate.classList.remove('btn-outline');
        btnInt.classList.add('btn-outline'); btnInt.classList.remove('btn-primary');
        document.getElementById('group_end_date').classList.remove('hidden');
        document.getElementById('group_interval').classList.add('hidden');
    } else {
        btnInt.classList.add('btn-primary'); btnInt.classList.remove('btn-outline');
        btnDate.classList.add('btn-outline'); btnDate.classList.remove('btn-primary');
        document.getElementById('group_end_date').classList.add('hidden');
        document.getElementById('group_interval').classList.remove('hidden');
    }
}

async function loadItems(type) {
    const grp = document.getElementById('item_group');
    const sel = document.getElementById('item_id');
    if(type === 'Checkup' || !type) { grp.classList.add('hidden'); return; }
    grp.classList.remove('hidden');
    sel.innerHTML = '<option>Loading...</option>';
    try {
        const res = await fetch(`../process/eventManager.php?action=get_items&type=${type}`);
        const data = await res.json();
        sel.innerHTML = '<option value="">-- Select Item --</option>';
        data.forEach(i => {
            const opt = document.createElement('option');
            opt.value = i.id; opt.textContent = i.name; sel.appendChild(opt);
        });
    } catch(e) { showToast('Failed to load items', 'error'); sel.innerHTML = '<option value="">Error</option>'; }
}

async function loadBuildingPopulation(bldgId) {
    const tree = document.getElementById('selection_tree');
    tree.innerHTML = '<div style="padding:2rem; text-align:center; color:var(--text-muted);">Loading...</div>';
    if(!bldgId) { tree.innerHTML = '<div style="text-align:center; padding:2rem; color:var(--text-muted); font-size:0.9rem;">Select a building to load pens.</div>'; return; }
    try {
        const res = await fetch(`../process/eventManager.php?action=get_building_population&bldg_id=${bldgId}`);
        const pens = await res.json();
        tree.innerHTML = '';
        if(pens.length === 0) { tree.innerHTML = '<div style="padding:1rem; text-align:center; color:var(--danger);">No pens found.</div>'; return; }
        pens.forEach(pen => {
            const animalsHtml = pen.animals.map(a => `
                <label class="chk-label">
                    <input type="checkbox" class="an-chk pen-${pen.PEN_ID}" value="${a.ANIMAL_ID}"> ${a.TAG_NO}
                </label>`).join('');
            const penHtml = `
                <div class="pen-group">
                    <div class="pen-header" onclick="togglePenBody(this)">
                        <input type="checkbox" onchange="togglePenAll(this, ${pen.PEN_ID})" onclick="event.stopPropagation()">
                        <span style="font-weight:700; flex:1;">${pen.PEN_NAME}</span>
                        <small style="opacity:0.6;">${pen.animals.length} animals</small>
                    </div>
                    <div class="pen-body">${animalsHtml}</div>
                </div>`;
            tree.insertAdjacentHTML('beforeend', penHtml);
        });
    } catch(e) { showToast('Failed to load pens', 'error'); tree.innerHTML = '<div style="padding:1rem; text-align:center; color:var(--danger);">Error</div>'; }
}
function togglePenBody(header) { header.nextElementSibling.classList.toggle('open'); }
function togglePenAll(cb, penId) { document.querySelectorAll(`.pen-${penId}`).forEach(el => el.checked = cb.checked); }

// --- ACTIONS ---
async function submitAddEvent() {
    const form = document.getElementById('addEventForm');
    const btn = document.querySelector('#addModal .event-modal-footer .btn-primary'); 
    const originalText = btn.innerText;

    if(!form.checkValidity()) { form.reportValidity(); return; }
    const ids = Array.from(document.querySelectorAll('.an-chk:checked')).map(cb => cb.value);
    if(ids.length === 0) { showToast("Select at least one animal", 'error'); return; }
    document.getElementById('selected_animal_ids').value = ids.join(',');

    btn.disabled = true; btn.innerText = "Saving...";
    const fd = new FormData(form);
    try {
        const res = await fetch('../process/eventManager.php', { method: 'POST', body: fd });
        const data = await res.json();
        if(data.success) { 
            showToast(data.message); setTimeout(() => location.reload(), 1000); 
        } else { 
            showToast(data.message, 'error'); btn.disabled = false; btn.innerText = originalText;
        }
    } catch(e) { 
        showToast('Failed to save event', 'error'); btn.disabled = false; btn.innerText = originalText;
    }
}

// --- BULK OPERATIONS ---
function toggleSelectAll(cb) {
    document.querySelectorAll('.row-chk:not(:disabled)').forEach(el => el.checked = cb.checked);
    updateActionBar();
}

function updateActionBar() {
    const checkedBoxes = document.querySelectorAll('.row-chk:checked');
    const count = checkedBoxes.length;
    document.getElementById('selectedCount').innerText = count;

    const bar = document.getElementById('bulkActionBar');
    const btnDone = document.querySelector('.btn-mark-done');

    if(count > 0) {
        bar.classList.add('active');

        // Check if ANY selected item is missing a record
        let missingRecords = false;
        checkedBoxes.forEach(cb => {
            if(cb.getAttribute('data-has-record') === "0") {
                missingRecords = true;
            }
        });

        if(missingRecords) {
            btnDone.disabled = true;
            btnDone.style.opacity = "0.5";
            btnDone.style.cursor = "not-allowed";
            btnDone.innerText = "⚠️ Record Work First";
            btnDone.setAttribute('data-locked', '1'); 
        } else {
            btnDone.disabled = false;
            btnDone.style.opacity = "1";
            btnDone.style.cursor = "pointer";
            btnDone.innerText = "✓ Done";
            btnDone.setAttribute('data-locked', '0');
        }

    } else {
        bar.classList.remove('active');
        btnDone.setAttribute('data-locked', '0'); 
    }
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.row-chk').forEach(el => { el.addEventListener('change', updateActionBar); });
});

async function bulkUpdate(status) {
    const btn = document.querySelector('.btn-mark-done');
    // Double check state before sending
    if(status === 'Done' && btn.getAttribute('data-locked') === "1") {
        showToast("Security: Cannot verify work records.", "error");
        return;
    }

    const ids = Array.from(document.querySelectorAll('.row-chk:checked')).map(cb => cb.value);
    if(ids.length === 0) { showToast('No events selected', 'error'); return; }
    
    if(!confirm(`Update ${ids.length} event(s) to ${status}?`)) return;
    
    const fd = new FormData();
    fd.append('action', 'bulk_update_status');
    fd.append('ids', ids.join(','));
    fd.append('status', status);
    
    try {
        const res = await fetch('../process/eventManager.php', { method: 'POST', body: fd });
        const data = await res.json();
        if(data.success) { showToast(data.message); setTimeout(() => location.reload(), 1000); } else { showToast(data.message, 'error'); }
    } catch(e) { showToast('Failed to update events', 'error'); }
}

async function deleteSelected() {
    const ids = Array.from(document.querySelectorAll('.row-chk:checked')).map(cb => cb.value);
    if(ids.length === 0) { showToast('No events selected', 'error'); return; }
    if(!confirm(`Archive ${ids.length} event(s)? This action can be undone by database administrators.`)) return;
    const fd = new FormData();
    fd.append('action', 'bulk_delete');
    fd.append('ids_to_delete', ids.join(','));
    try {
        const res = await fetch('../process/eventManager.php', { method: 'POST', body: fd });
        const data = await res.json();
        if(data.success) { showToast(data.message); setTimeout(() => location.reload(), 1000); } else { showToast(data.message, 'error'); }
    } catch(e) { showToast('Failed to archive events', 'error'); }
}

async function confirmArchive(mode) {
    const fd = new FormData();
    fd.append('action', 'bulk_delete');
    if(mode === 'selection') {
        const ids = Array.from(document.querySelectorAll('.row-chk:checked')).map(cb => cb.value);
        if(ids.length === 0) { showToast('No events selected', 'error'); return; }
        fd.append('ids_to_delete', ids.join(','));
        if(!confirm(`Archive ${ids.length} selected event(s)?`)) return;
    } else {
        const form = document.getElementById('filterArchiveForm');
        if(!form.checkValidity()) { form.reportValidity(); return; }
        const formData = new FormData(form);
        for(let pair of formData.entries()) { fd.append(pair[0], pair[1]); }
        if(!confirm("Archive all matching events?")) return;
    }
    try {
        const res = await fetch('../process/eventManager.php', { method: 'POST', body: fd });
        const data = await res.json();
        if(data.success) { showToast(data.message); closeModal('archiveModal'); setTimeout(() => location.reload(), 1000); } else { showToast(data.message, 'error'); }
    } catch(e) { showToast('Failed to archive events', 'error'); }
}

async function loadArchiveBuildings(locId) {
    const sel = document.getElementById('del_bldg_id');
    sel.disabled = true; sel.innerHTML = '<option>Loading...</option>';
    if(!locId) { sel.innerHTML = '<option value="">All Buildings</option>'; sel.disabled = false; return; }
    try {
        const res = await fetch(`../process/eventManager.php?action=get_buildings_filter&loc_id=${locId}`);
        const data = await res.json();
        sel.innerHTML = '<option value="">All Buildings</option>';
        data.forEach(b => {
            const opt = document.createElement('option');
            opt.value = b.BUILDING_ID; opt.textContent = b.BUILDING_NAME; sel.appendChild(opt);
        });
        sel.disabled = false;
    } catch(e) { showToast('Failed to load buildings', 'error'); sel.innerHTML = '<option value="">Error loading</option>'; sel.disabled = true; }
}
</script>

</body>
</html>