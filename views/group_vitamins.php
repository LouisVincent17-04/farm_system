<?php
// views/group_vitamins.php

// =========================================================
// AJAX HANDLER — MUST BE FIRST, before any HTML includes
// =========================================================
if (isset($_GET['action'])) {
    include '../config/Connection.php';
    include '../functions/getUsersLocation.php';
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json');

    $action = $_GET['action'];

    try {
        if ($action === 'get_buildings' && isset($_GET['location_id'])) {
            $stmt = $conn->prepare("SELECT BUILDING_ID, BUILDING_NAME FROM buildings WHERE LOCATION_ID = ? ORDER BY BUILDING_NAME");
            $stmt->execute([$_GET['location_id']]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC)); exit;
        }

        if ($action === 'get_pens' && isset($_GET['building_id'])) {
            $stmt = $conn->prepare("SELECT PEN_ID, PEN_NAME FROM pens WHERE BUILDING_ID = ? ORDER BY PEN_NAME");
            $stmt->execute([$_GET['building_id']]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC)); exit;
        }

        if ($action === 'get_animals' && isset($_GET['pen_id'])) {
            $stmt = $conn->prepare("SELECT ANIMAL_ID, TAG_NO FROM animal_records WHERE PEN_ID = ? AND IS_ACTIVE = 1 AND CURRENT_STATUS != 'Sold' ORDER BY TAG_NO");
            $stmt->execute([$_GET['pen_id']]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC)); exit;
        }

        // --- NEW: AJAX HANDLER TO REFRESH SUPPLEMENTS LIVE ---
        if ($action === 'get_vitamins') {
            $locId = $_GET['location_id'] ?? '';
            if (!$locId) { echo json_encode([]); exit; }
            
            $stmt = $conn->prepare("SELECT SUPPLY_ID, SUPPLY_NAME FROM VITAMINS_SUPPLEMENTS WHERE LOCATION_ID = ? ORDER BY SUPPLY_NAME ASC");
            $stmt->execute([$locId]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            exit;
        }

        // --- FETCH VITAMIN HISTORY ---
        // FIX: Join on ITEM_ID (not VITAMIN_ID) to match what addBatchVitamins.php inserts.
        //      Also join VITAMINS_SUPPLEMENTS table (not vitamins) for SUPPLY_NAME.
        if ($action === 'get_vitamin_history') {
            $limit    = 10;
            $page_num = isset($_GET['p']) ? (int)$_GET['p'] : 1;
            $offset   = ($page_num - 1) * $limit;
            $search   = $_GET['search']      ?? '';
            $loc_f    = $_GET['loc_filter']  ?? '';
            $date_from = $_GET['date_from']  ?? '';
            $date_to   = $_GET['date_to']    ?? '';

            $where  = ["1=1"];
            $params = [];

            if ($USER_LOCATION_ != 1000) { $where[] = "l.LOCATION_ID = ?"; $params[] = $USER_LOCATION_; }
            if ($loc_f)     { $where[] = "l.LOCATION_ID = ?"; $params[] = $loc_f; }
            if ($search)    { $where[] = "(a.TAG_NO LIKE ? OR v.ADMINISTERED_BY LIKE ? OR vs.SUPPLY_NAME LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%"; }
            if ($date_from) { $where[] = "DATE(v.TRANSACTION_DATE) >= ?"; $params[] = $date_from; }
            if ($date_to)   { $where[] = "DATE(v.TRANSACTION_DATE) <= ?";  $params[] = $date_to; }

            $where_sql = implode(" AND ", $where);

            $count_stmt = $conn->prepare("
                SELECT COUNT(*)
                FROM VITAMINS_SUPPLEMENTS_TRANSACTIONS v
                JOIN animal_records a  ON v.ANIMAL_ID = a.ANIMAL_ID
                JOIN locations l       ON a.LOCATION_ID = l.LOCATION_ID
                LEFT JOIN VITAMINS_SUPPLEMENTS vs ON v.ITEM_ID = vs.SUPPLY_ID
                WHERE $where_sql
            ");
            $count_stmt->execute($params);
            $total_records = $count_stmt->fetchColumn();

            $sql = "
                SELECT v.*,
                       DATE_FORMAT(v.TRANSACTION_DATE, '%m/%d/%Y %h:%i %p') AS FORMATTED_DATE,
                       a.TAG_NO,
                       l.LOCATION_NAME,
                       vs.SUPPLY_NAME
                FROM VITAMINS_SUPPLEMENTS_TRANSACTIONS v
                JOIN animal_records a  ON v.ANIMAL_ID = a.ANIMAL_ID
                JOIN locations l       ON a.LOCATION_ID = l.LOCATION_ID
                LEFT JOIN VITAMINS_SUPPLEMENTS vs ON v.ITEM_ID = vs.SUPPLY_ID
                WHERE $where_sql
                ORDER BY v.TRANSACTION_DATE DESC, v.VST_ID DESC
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

    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// =========================================================
// NORMAL PAGE LOAD — includes run only here
// =========================================================
ob_start();
error_reporting(0);
ini_set('display_errors', 0);
include '../config/Connection.php';

include '../security/checkAccess.php';
checkAccess('group_vitamins');
$page = "transactions";

$event_ids = trim($_GET['event_ids'] ?? '');

include '../common/navbar.php';
include '../common/chat_support.php';
include '../functions/getUsersLocation.php';

// --- PAGE INIT ---
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

    // Load supplements from VITAMINS_SUPPLEMENTS table (matches addBatchVitamins.php)
    $vitamins = $conn->query("SELECT SUPPLY_ID, SUPPLY_NAME FROM VITAMINS_SUPPLEMENTS ORDER BY SUPPLY_NAME ASC")->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) { $locs = []; $personnel = []; $vitamins = []; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Group Vitamins & Supplements | FarmPro</title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" type="text/css" href="https://npmcdn.com/flatpickr/dist/themes/dark.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    <style>
        :root { --accent:#ec4899; --accent-dark:#be185d; --bg:#0f172a; --card:#1e293b; --border:#334155; --text:#e2e8f0; --muted:#94a3b8; --success:#22c55e; --danger:#ef4444; }
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif; background:linear-gradient(135deg,var(--bg) 0%,#1e293b 100%); min-height:100vh; color:var(--text); }
        .container { max-width:1600px; margin:0 auto; padding:1.5rem; }
        .back-link { display:inline-flex; align-items:center; gap:8px; text-decoration:none; color:var(--muted); font-weight:600; font-size:.95rem; margin-bottom:1.5rem; transition:color .2s; }
        .back-link:hover { color:white; }

        #sync-alert { display:none; padding:1rem 1.5rem; border-radius:12px; margin-bottom:1.5rem; text-align:center; font-weight:600; background:rgba(59,130,246,.1); border:1px solid #3b82f6; color:#60a5fa; }
        #sync-alert.success { background:rgba(34,197,94,.1); border-color:var(--success); color:#4ade80; }
        #sync-alert.error   { background:rgba(239,68,68,.1); border-color:var(--danger); color:#f87171; }

        #lock-banner { display:none; background:rgba(236,72,153,.12); border:1px solid rgba(236,72,153,.35); border-radius:12px; padding:.9rem 1.25rem; margin-bottom:1.25rem; color:#f9a8d4; font-size:.9rem; gap:10px; align-items:center; }
        #lock-banner.show { display:flex; }

        .main-grid { display:grid; grid-template-columns:380px 1fr; gap:1.5rem; align-items:start; }
        .control-panel { background:rgba(30,41,59,.7); backdrop-filter:blur(12px); border:1px solid rgba(148,163,184,.2); border-radius:16px; padding:1.5rem; position:sticky; top:1.5rem; box-shadow:0 10px 25px -5px rgba(0,0,0,.3); z-index:10; }
        .panel-title { font-size:1.25rem; font-weight:700; color:#fff; margin-bottom:5px; display:flex; align-items:center; gap:8px; }
        .panel-subtitle { font-size:.85rem; color:var(--muted); margin-bottom:1.5rem; }

        .form-group { margin-bottom:1rem; }
        .form-label { display:block; font-size:.85rem; color:#cbd5e1; margin-bottom:.4rem; font-weight:500; }
        .form-control { width:100%; padding:.75rem; background:var(--bg); border:1px solid var(--border); border-radius:8px; color:#fff; font-size:.95rem; transition:border-color .2s; outline:none; }
        .form-control:focus   { border-color:var(--accent); box-shadow: 0 0 0 3px rgba(236, 72, 153, 0.1); }
        .form-control:disabled { opacity:.5; cursor:not-allowed; background:#0a1020; }

        .select-wrap { position:relative; }
        .select-wrap .form-control { padding-right: 2.2rem; }
        .select-wrap .lock-badge { display:none; position:absolute; right:10px; top:50%; transform:translateY(-50%); font-size:.8rem; pointer-events:none; z-index:2; }
        .select-wrap.locked .lock-badge { display:inline; }
        .select-wrap.locked .form-control { border-color:#be185d; background:#0d0b1e; opacity:.85; cursor:not-allowed; }

        .summary-box { margin-top:1.5rem; background:var(--bg); padding:1rem; border-radius:12px; border-left:4px solid var(--accent); }
        .summary-row { display:flex; justify-content:space-between; margin-bottom:5px; font-size:.9rem; color:var(--muted); }

        .btn-mini { background:#334155; border:1px solid #475569; color:#fff; border-radius:8px; padding:8px 12px; cursor:pointer; font-size:.8rem; white-space:nowrap; flex-shrink:0; }
        .btn-mini:hover { background:#475569; }

        .btn-submit { width:100%; margin-top:1.5rem; padding:1rem; background:linear-gradient(135deg,var(--accent),var(--accent-dark)); border:none; border-radius:12px; color:white; font-weight:700; font-size:1rem; cursor:pointer; transition:all .2s; }
        .btn-submit:disabled { opacity:.5; cursor:not-allowed; filter:grayscale(1); }
        .btn-submit:hover:not(:disabled) { transform:translateY(-2px); box-shadow:0 4px 12px rgba(236,72,153,.4); }

        .btn-remove { background:transparent; border:none; color:#f87171; cursor:pointer; font-size:1.1rem; padding:5px; }
        .btn-remove:hover { color:var(--danger); }

        .workspace-panel { display:flex; flex-direction:column; gap:1.5rem; }
        .picker-section { background:rgba(30,41,59,.4); border:1px solid rgba(255,255,255,.05); border-radius:16px; padding:1.5rem; }
        .section-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; }
        .section-title { font-size:1.1rem; font-weight:600; color:#fff; }

        .select-all-container { display:flex; align-items:center; gap:8px; font-size:.9rem; color:#f9a8d4; cursor:pointer; padding:5px 10px; border-radius:6px; background:rgba(236,72,153,.1); border:1px solid rgba(236,72,153,.2); }
        .select-all-container input { cursor:pointer; accent-color:#ec4899; width:16px; height:16px; }

        .animal-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(100px,1fr)); gap:.75rem; max-height:250px; overflow-y:auto; }
        .animal-card { background:var(--card); border:1px solid var(--border); border-radius:8px; padding:.75rem; cursor:pointer; text-align:center; transition:all .2s; }
        .animal-card:hover { border-color:var(--muted); transform:translateY(-2px); }
        .animal-card.in-table { opacity:.45; pointer-events:none; border-color:#4ade80; }

        .table-section { background:var(--card); border:1px solid var(--border); border-radius:16px; overflow:hidden; }
        .custom-table { width:100%; border-collapse:collapse; }
        .custom-table th { background:var(--bg); color:var(--muted); font-size:.8rem; text-transform:uppercase; padding:1rem; text-align:left; font-weight:600; border-bottom:1px solid var(--border); }
        .custom-table td { padding:.75rem 1rem; border-bottom:1px solid rgba(255,255,255,.05); vertical-align:middle; color:var(--text); font-size:.95rem; }
        .custom-table select, .custom-table input, .custom-table textarea { background:var(--bg); border:1px solid #475569; color:#fff; padding:6px 10px; border-radius:6px; width:100%; font-size:.9rem; font-family:inherit; outline:none; }
        .custom-table textarea { resize:vertical; min-height:38px; }
        .custom-table input:focus, .custom-table select:focus, .custom-table textarea:focus { border-color:var(--accent); box-shadow: 0 0 0 3px rgba(236, 72, 153, 0.1); }

        .resource-link { display: inline-flex; align-items: center; gap: 5px; font-size: 0.85rem; color: #f9a8d4; text-decoration: none; transition: color 0.2s; font-weight: 600; }
        .resource-link:hover { color: #fbcfe8; text-decoration: underline; }

        /* History Filter Styling with Width Fix */
        .history-filters { display:flex; gap:12px; padding:1rem; background:rgba(15,23,42,0.3); border-bottom:1px solid var(--border); flex-wrap:wrap; align-items:center;}
        .filter-input { width: 180px !important; padding:8px 12px; background:var(--bg); border:1px solid var(--border); border-radius:6px; color:#fff; font-size:0.9rem; outline:none; }
        .filter-input:focus { border-color:var(--accent); }

        .pagination { display:flex; justify-content:center; gap:8px; padding:1.5rem; background:rgba(15,23,42,0.2); }
        .pg-btn { background:var(--border); border:none; color:#fff; padding:6px 12px; border-radius:6px; cursor:pointer; font-size:0.9rem; }
        .pg-btn.active { background:var(--accent); }
        .pg-btn:disabled { opacity:0.3; cursor:not-allowed; }

        @media(max-width:1024px) {
            .main-grid { grid-template-columns:1fr; }
            .control-panel { position:static; }
            .custom-table thead { display:none; }
            .custom-table tr { display:block; background:rgba(30,41,59,.3); margin-bottom:1rem; border:1px solid var(--border); border-radius:12px; padding:1rem; }
            .custom-table td { display:flex; justify-content:space-between; align-items:center; border:none; padding:8px 0; }
            .custom-table td::before { content:attr(data-label); font-weight:600; font-size:.85rem; color:var(--muted); text-transform:uppercase; }
            .custom-table select, .custom-table input { width:60%; }
        }
        @media(max-width:768px) {
            .filter-input { width: 100% !important; }
            .history-filters { flex-direction:column; }
        }
    </style>
</head>
<body>
<div class="container">

    <a href="<?= $event_ids ? 'events_scheduler.php' : 'transactions.php' ?>" class="back-link">
        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
        <?= $event_ids ? 'Back to Event Scheduler' : 'Back to Transactions' ?>
    </a>

    <div id="sync-alert"><span id="sync-msg"></span></div>
    <div id="lock-banner">🔒 <strong>Scheduler Mode:</strong> Animals and supplements are pre-loaded from the event schedule. Review and save to complete.</div>

    <div class="main-grid">

        <div class="control-panel">
            <div class="panel-title">🧴 Group Vitamins & Supplements</div>
            <div class="panel-subtitle">Mass distribution of supplements.</div>

            <form id="settingsForm">
                <div style="background:rgba(255,255,255,.03);padding:15px;border-radius:8px;margin-bottom:1.5rem;border:1px dashed #475569;">
                    <label class="form-label" style="color:#f9a8d4;margin-bottom:8px;">STEP 1: Locate Group</label>
                    <div class="form-group" style="margin-bottom:.5rem;">
                        <div class="select-wrap" id="wrap-location">
                            <select id="location_id" class="form-control" onchange="handleLocationChange(this.value)" <?php echo ($USER_LOCATION_ != 1000) ? 'style="pointer-events: none; opacity: 0.7;"' : ''; ?>>
                                <?php if($USER_LOCATION_ == 1000): ?>
                                    <option value="">Select Location</option>
                                <?php endif; ?>
                                <?php foreach($locs as $l): ?>
                                    <option value="<?= $l['LOCATION_ID'] ?>" <?php echo ($USER_LOCATION_ != 1000 && $l['LOCATION_ID'] == $USER_LOCATION_) ? 'selected' : ''; ?>>
                                        <?= htmlspecialchars($l['LOCATION_NAME']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <span class="lock-badge">🔒</span>
                        </div>
                    </div>
                    <div class="form-group" style="margin-bottom:.5rem;">
                        <div class="select-wrap" id="wrap-building">
                            <select id="building_id" class="form-control" onchange="handleBuildingChange(this.value)" disabled><option value="">Select Building</option></select>
                            <span class="lock-badge">🔒</span>
                        </div>
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <div class="select-wrap" id="wrap-pen">
                            <select id="pen_id" class="form-control" onchange="loadAnimals(this.value)" disabled><option value="">Select Pen</option></select>
                            <span class="lock-badge">🔒</span>
                        </div>
                    </div>
                </div>

                <label class="form-label" style="color:#f9a8d4;">STEP 2: Default Settings</label>

                <div class="form-group">
                    <label class="form-label">Default Supplement <span style="color:#f87171">*</span></label>
                    <div class="select-wrap" id="wrap-vitamin" style="flex:1;">
                        <select id="default_vitamin" class="form-control" onchange="updateAllSupplements()">
                            <option value="">— Select Supplement —</option>
                            <?php foreach($vitamins as $v): ?>
                                <option value="<?= $v['SUPPLY_ID'] ?>"><?= htmlspecialchars($v['SUPPLY_NAME']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <span class="lock-badge">🔒</span>
                    </div>
                    
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 8px;">
                        <a href="purch_vitamins.php" target="_blank" class="resource-link" title="Opens in a new tab">
                            Manage / Purchase Supplements ↗
                        </a>
                        <button type="button" id="refresh-supplements-btn" class="btn-mini" onclick="refreshSupplementsList()" style="background: rgba(236,72,153, 0.2); color: #f9a8d4; border: 1px solid rgba(236,72,153, 0.3);">
                            ↻ Refresh Options
                        </button>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Default Quantity</label>
                    <div style="display:flex;gap:8px;">
                        <input type="number" id="default_qty" class="form-control" placeholder="e.g. 5" min="0" step="0.01">
                        <button type="button" class="btn-mini" onclick="updateAllQty()">Apply</button>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Default Dosage</label>
                    <div style="display:flex;gap:8px;">
                        <input type="text" id="default_dosage" class="form-control" placeholder="e.g. 5ml">
                        <button type="button" class="btn-mini" onclick="updateAllDosages()">Apply</button>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Default Remarks</label>
                    <div style="display:flex;gap:8px;">
                        <input type="text" id="default_remarks" class="form-control" placeholder="e.g. Routine Supplement">
                        <button type="button" class="btn-mini" onclick="updateAllRemarks()">Apply</button>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Administered By</label>
                    <select id="administered_by" class="form-control">
                        <option value="">— Select Person —</option>
                        <?php foreach($personnel as $p): ?>
                            <option value="<?= htmlspecialchars($p['FULL_NAME']) ?>"><?= htmlspecialchars($p['FULL_NAME']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Date Administered</label>
                    <input type="text" id="txn_date" class="form-control date-picker" placeholder="Select Date & Time">
                </div>

                <div class="summary-box">
                    <div class="summary-row"><span>Animals Selected:</span><span id="sum-count" style="color:#fff">0</span></div>
                </div>

                <button type="button" class="btn-submit" id="btn-submit" onclick="submitBatch()" disabled>Record Supplements</button>
            </form>
        </div>

        <div class="workspace-panel">
            <div class="picker-section" id="pickerSection">
                <div class="section-header">
                    <div class="section-title">🐖 Step 3: Select Animals</div>
                    <label class="select-all-container" style="display:none;" id="select-all-wrapper">
                        <input type="checkbox" id="select-all-check" onchange="toggleSelectAll(this)"> Select All
                    </label>
                </div>
                <div id="animal-grid" class="animal-grid">
                    <div style="grid-column:1/-1;text-align:center;padding:2rem;color:#64748b;border:1px dashed #475569;border-radius:8px;">Select a Pen to load animals.</div>
                </div>
            </div>

            <div class="table-section">
                <div class="section-header" style="padding:1rem;border-bottom:1px solid var(--border);">
                    <div class="section-title">📋 Step 4: Confirm Details</div>
                    <button onclick="clearTable()" style="background:transparent;border:1px solid #f87171;color:#f87171;padding:4px 10px;border-radius:4px;cursor:pointer;font-size:.85rem;">Clear All</button>
                </div>
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th style="width:15%;">Tag No</th>
                            <th style="width:28%;">Supplement</th>
                            <th style="width:12%;">Quantity</th>
                            <th style="width:15%;">Dosage</th>
                            <th>Remarks</th>
                            <th style="width:40px;"></th>
                        </tr>
                    </thead>
                    <tbody id="vitamin-list">
                        <tr id="empty-row"><td colspan="6" style="text-align:center;padding:2rem;color:#64748b;">No animals added yet.</td></tr>
                    </tbody>
                </table>
            </div>

            <div class="table-section">
                <div class="section-header" style="padding:1rem;border-bottom:1px solid var(--border);">
                    <div class="section-title">🕒 Recent Vitamin Logs</div>
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
                    
                    <input type="text" id="histFrom" class="filter-input" placeholder="Date From...">
                    <input type="text" id="histTo"   class="filter-input" placeholder="Date To...">
                </div>

                <table class="custom-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Tag</th>
                            <th>Administered By</th>
                            <th>Supplement</th>
                            <th>Qty Used</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>
                    <tbody id="history-list">
                        <tr><td colspan="6" style="text-align:center;padding:2rem;color:#64748b;">Loading...</td></tr>
                    </tbody>
                </table>
                <div class="pagination" id="pagination"></div>
            </div>

        </div>
    </div>
</div>

<script>
let selectedAnimals   = new Set();
let currentPenAnimals = [];
let schedulerMode     = false;
let fpTxnDate; 

// Build options string for JS dynamic rows
let vitOptions = `<?php foreach($vitamins as $v){ echo "<option value='{$v['SUPPLY_ID']}'>".htmlspecialchars($v['SUPPLY_NAME'])."</option>"; } ?>`;

const incomingEventIds = "<?= htmlspecialchars($event_ids) ?>".trim();
const USER_LOCATION = <?php echo json_encode($USER_LOCATION_); ?>;

document.addEventListener('DOMContentLoaded', () => {
    fpTxnDate = flatpickr("#txn_date", { enableTime: true, dateFormat: "Y-m-d H:i", altInput: true, altFormat: "m/d/Y h:i K" });
    
    flatpickr("#histFrom", { dateFormat: "Y-m-d", altInput: true, altFormat: "m/d/Y", onChange: function() { loadHistory(1); } });
    flatpickr("#histTo", { dateFormat: "Y-m-d", altInput: true, altFormat: "m/d/Y", onChange: function() { loadHistory(1); } });

    if (incomingEventIds) {
        schedulerMode = true;
        handleEventAutoSelect(incomingEventIds);
    } 
    else if (USER_LOCATION != 1000) {
        const locDrop = document.getElementById('location_id');
        locDrop.value = USER_LOCATION;
        handleLocationChange(USER_LOCATION);
        lockField('wrap-location', 'location_id');
    }
    
    loadHistory(1);
});

// --- NEW: REFRESH SUPPLEMENTS AJAX ---
async function refreshSupplementsList() {
    const locId = document.getElementById('location_id').value;
    if (!locId) { alert("Please select a Location first to load its specific supplements."); return; }

    const btn = document.getElementById('refresh-supplements-btn');
    btn.innerHTML = '↻ Loading...';
    btn.disabled = true;

    try {
        const res = await fetch(`?action=get_vitamins&location_id=${locId}`);
        const data = await res.json();
        
        const sel = document.getElementById('default_vitamin');
        const currentSelection = sel.value;
        
        sel.innerHTML = '<option value="">— Select Supplement —</option>';
        vitOptions = ''; 

        data.forEach(v => {
            sel.add(new Option(v.SUPPLY_NAME, v.SUPPLY_ID));
            vitOptions += `<option value='${v.SUPPLY_ID}'>${v.SUPPLY_NAME}</option>`;
        });

        // Re-select if it still exists
        if (currentSelection) setSelectValue('default_vitamin', currentSelection);
        updateAllSupplements(); // Push to table rows
        
        btn.innerHTML = '↻ Refresh Options';
        btn.disabled = false;
    } catch (e) {
        btn.innerHTML = '❌ Error';
        setTimeout(() => { btn.innerHTML = '↻ Refresh Options'; btn.disabled = false; }, 2000);
    }
}

function showSyncBanner(type, msg) {
    const el = document.getElementById('sync-alert');
    el.className = type;
    document.getElementById('sync-msg').textContent = msg;
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
    showSyncBanner('loading', '🔄 Auto-Sync Active: Loading scheduled animals and supplements…');
    try {
        const res  = await fetch(`../process/eventManager.php?action=get_events_details&ids=${eventIds}`);
        const data = await res.json();
        
        if (!data.success || !data.events || data.events.length === 0) { 
            showSyncBanner('error', '❌ No event details returned.');
            return; 
        }

        const ev = data.events[0];
        const locId = String(ev.LOCATION_ID);
        const bldgId = String(ev.BUILDING_ID);
        const penId = String(ev.PEN_ID);
        const itemId = String(ev.ITEM_ID);

        if (!setSelectValue('location_id', locId)) {
            showSyncBanner('error', `❌ Location ID not found.`); return;
        }
        lockField('wrap-location', 'location_id');

        await fetchBuildings(locId);
        if (!setSelectValue('building_id', bldgId)) {
            showSyncBanner('error', `❌ Building ID not found.`); return;
        }
        lockField('wrap-building', 'building_id');

        await fetchPens(bldgId);
        if (!setSelectValue('pen_id', penId)) {
            showSyncBanner('error', `❌ Pen ID not found.`); return;
        }
        lockField('wrap-pen', 'pen_id');

        if (!setSelectValue('default_vitamin', itemId)) {
            showSyncBanner('error', `❌ Supplement ID not found in this location.`); return;
        }
        lockField('wrap-vitamin', 'default_vitamin');
        updateAllSupplements();

        data.events.forEach(e => {
            if (!selectedAnimals.has(String(e.ANIMAL_ID))) {
                addAnimalToTable({ ANIMAL_ID: e.ANIMAL_ID, TAG_NO: e.TAG_NO }, e.ITEM_ID);
            }
        });

        document.getElementById('pickerSection').style.display = 'none';
        document.getElementById('lock-banner').classList.add('show');

        showSyncBanner('success', `✅ ${data.events.length} animal(s) loaded from schedule.`);
        setTimeout(hideSyncBanner, 5000);
        document.querySelector('.table-section').scrollIntoView({ behavior: 'smooth' });
    } catch (e) {
        showSyncBanner('error', '❌ Auto-sync failed. Select animals manually.');
        setTimeout(hideSyncBanner, 4000);
    }
}

function handleLocationChange(locId) { 
    clearTable(); 
    document.getElementById('building_id').innerHTML = '<option value="">Select Building</option>';
    document.getElementById('building_id').disabled  = true;
    document.getElementById('pen_id').innerHTML      = '<option value="">Select Pen</option>';
    document.getElementById('pen_id').disabled       = true;

    if(!locId) return;
    fetchBuildings(locId); 
}

function handleBuildingChange(bldgId) {
    document.getElementById('pen_id').innerHTML = '<option value="">Select Pen</option>';
    document.getElementById('pen_id').disabled  = true;
    if (!bldgId) return;
    fetchPens(bldgId);
}

function fetchBuildings(locId) {
    return new Promise(resolve => {
        const bldg = document.getElementById('building_id');
        bldg.innerHTML = '<option>Loading…</option>';
        document.getElementById('pen_id').innerHTML = '<option>Select Pen</option>';
        document.getElementById('pen_id').disabled = true;
        if (!locId) { bldg.innerHTML = '<option value="">Select Building</option>'; bldg.disabled = true; resolve([]); return; }
        
        fetch(`?action=get_buildings&location_id=${locId}`)
            .then(r => r.json())
            .then(data => {
                bldg.innerHTML = '<option value="">Select Building</option>';
                const list = data || [];
                list.forEach(b => bldg.add(new Option(b.BUILDING_NAME, b.BUILDING_ID)));
                bldg.disabled = false; resolve(list);
            }).catch(() => resolve([]));
    });
}

function fetchPens(bldgId) {
    return new Promise(resolve => {
        const pen = document.getElementById('pen_id');
        pen.innerHTML = '<option>Loading…</option>';
        if (!bldgId) { pen.innerHTML = '<option value="">Select Pen</option>'; pen.disabled = true; resolve([]); return; }
        
        fetch(`?action=get_pens&building_id=${bldgId}`)
            .then(r => r.json())
            .then(data => {
                pen.innerHTML = '<option value="">Select Pen</option>';
                const list = data || [];
                list.forEach(p => pen.add(new Option(p.PEN_NAME, p.PEN_ID)));
                pen.disabled = false; resolve(list);
            });
    });
}

function loadAnimals(penId) {
    const grid    = document.getElementById('animal-grid');
    const wrap = document.getElementById('select-all-wrapper');
    grid.innerHTML = '<div style="grid-column:1/-1;text-align:center;color:#94a3b8;">Loading…</div>';
    wrap.style.display = 'none';
    if (!penId) return;
    
    fetch(`?action=get_animals&pen_id=${penId}`)
        .then(r => r.json())
        .then(data => {
            grid.innerHTML    = '';
            currentPenAnimals = data || [];
            if (!currentPenAnimals.length) { grid.innerHTML = '<div style="grid-column:1/-1;text-align:center;">No active animals.</div>'; return; }
            wrap.style.display = 'flex';
            currentPenAnimals.forEach(a => {
                const card = document.createElement('div');
                card.className = `animal-card ${selectedAnimals.has(String(a.ANIMAL_ID)) ? 'in-table' : ''}`;
                card.id = `card-${a.ANIMAL_ID}`;
                card.onclick = () => addAnimalToTable(a);
                card.innerHTML = `<div style="font-size:1.5rem;">🐖</div><div style="font-weight:700;">${a.TAG_NO}</div>`;
                grid.appendChild(card);
            });
        });
}

function addAnimalToTable(animal, preselectedItemId = null) {
    if (selectedAnimals.has(String(animal.ANIMAL_ID))) return;
    const emptyRow = document.getElementById('empty-row');
    if (emptyRow) emptyRow.remove();

    const defQty    = document.getElementById('default_qty').value;
    const defDosage = document.getElementById('default_dosage').value;
    const defRem    = document.getElementById('default_remarks').value;
    const itemToSelect = preselectedItemId || document.getElementById('default_vitamin').value;

    let selectHTML = `<select class="vit-select form-control"><option value="">--Select--</option>${vitOptions}</select>`;
    if (itemToSelect) selectHTML = selectHTML.replace(`value='${itemToSelect}'`, `value='${itemToSelect}' selected`);

    const tr = document.createElement('tr');
    tr.id = `row-${animal.ANIMAL_ID}`;
    tr.dataset.id = String(animal.ANIMAL_ID);
    tr.innerHTML = `
        <td data-label="Tag No" style="font-weight:600;">${animal.TAG_NO}</td>
        <td data-label="Supplement">${selectHTML}</td>
        <td data-label="Qty"><input type="number" class="qty-input form-control" value="${defQty}" step="0.01" min="0.01"></td>
        <td data-label="Dosage"><input type="text" class="dosage-input form-control" value="${defDosage}" placeholder="e.g. 5x a day"></td>
        <td data-label="Remarks"><input type="text" class="rem-input form-control" value="${defRem}" placeholder="Notes…"></td>
        <td style="text-align:right;"><button class="btn-remove" onclick="removeAnimal(${animal.ANIMAL_ID})">×</button></td>
    `;
    document.getElementById('vitamin-list').appendChild(tr);
    selectedAnimals.add(String(animal.ANIMAL_ID));

    const card = document.getElementById(`card-${animal.ANIMAL_ID}`);
    if (card) card.classList.add('in-table');
    document.getElementById('sum-count').textContent = selectedAnimals.size;
    document.getElementById('btn-submit').disabled = false;
}

function removeAnimal(id) {
    const row = document.getElementById(`row-${id}`);
    if (row) row.remove();
    selectedAnimals.delete(String(id));
    const card = document.getElementById(`card-${id}`);
    if (card) card.classList.remove('in-table');
    if (!selectedAnimals.size) {
        document.getElementById('vitamin-list').innerHTML = '<tr id="empty-row"><td colspan="6" style="text-align:center;padding:2rem;color:#64748b;">No animals added yet.</td></tr>';
        document.getElementById('btn-submit').disabled = true;
    }
    document.getElementById('sum-count').textContent = selectedAnimals.size;
}

function clearTable() { if (!selectedAnimals.size) return; if (!confirm('Clear all rows?')) return; Array.from(selectedAnimals).forEach(id => removeAnimal(id)); }
function toggleSelectAll(cb) { if (cb.checked) currentPenAnimals.forEach(a => addAnimalToTable(a)); else currentPenAnimals.forEach(a => removeAnimal(a.ANIMAL_ID)); }
function updateAllQuantities()  { document.querySelectorAll('.qty-input').forEach(i => i.value = document.getElementById('default_qty').value); }
function updateAllDosages()     { document.querySelectorAll('.dosage-input').forEach(i => i.value = document.getElementById('default_dosage').value); }
function updateAllRemarks()     { document.querySelectorAll('.rem-input').forEach(i => i.value = document.getElementById('default_remarks').value); }
function updateAllSupplements() { const val = document.getElementById('default_vitamin').value; document.querySelectorAll('.vit-select').forEach(s => s.value = val); }

async function loadHistory(page) {
    const list = document.getElementById('history-list');
    const pg = document.getElementById('pagination');
    list.innerHTML = '<tr><td colspan="6" style="text-align:center;">Loading history...</td></tr>';

    const search = document.getElementById('histSearch').value;
    const loc = document.getElementById('histLoc')?.value || '';
    const from = document.getElementById('histFrom').value;
    const to = document.getElementById('histTo').value;

    try {
        const query = `action=get_vitamin_history&p=${page}&search=${search}&loc_filter=${loc}&date_from=${from}&date_to=${to}`;
        const res = await fetch(`?${query}`);
        const result = await res.json();

        if (!result.success || !result.data) {
            list.innerHTML = `<tr><td colspan="6" style="text-align:center; color:var(--danger);">Error: ${result.error || 'Failed to load data'}</td></tr>`;
            if(pg) pg.innerHTML = '';
            return;
        }

        if (result.data.length === 0) {
            list.innerHTML = '<tr><td colspan="6" style="text-align:center;">No records found.</td></tr>';
            if(pg) pg.innerHTML = '';
            return;
        }

        list.innerHTML = result.data.map(row => `
            <tr>
                <td style="font-size:0.85rem; color:var(--muted);">${row.FORMATTED_DATE}</td>
                <td><span style="color:var(--accent); font-weight:bold;">${row.TAG_NO}</span></td>
                <td>${row.ADMINISTERED_BY || '—'}</td>
                <td><span style="color:#ec4899; font-weight:bold;">${row.SUPPLY_NAME || '—'}</span></td>
                <td style="font-size:0.9rem;">${row.QUANTITY_USED ?? '—'}</td>
                <td style="font-size:0.9rem;">${row.REMARKS || '—'}</td>
            </tr>
        `).join('');

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
        list.innerHTML = '<tr><td colspan="6" style="text-align:center; color:var(--danger);">System Error: Check Console</td></tr>';
    }
}

async function submitBatch() {
    if (!confirm(`Record medication for ${selectedAnimals.size} animal(s)?`)) return;

    const dateInput = document.getElementById('txn_date').value;
    if (!dateInput) { alert("Please select a valid Date Administered."); return; }

    const btn = document.getElementById('btn-submit');
    btn.disabled = true; btn.textContent = 'Processing…';

    const records = [];
    let validationError = false;
    document.querySelectorAll('#vitamin-list tr[id^="row-"]').forEach(tr => {
        const itemId = tr.querySelector('.vit-select').value;
        if (!itemId) { alert('Please select a supplement for all animals.'); validationError = true; return; }
        records.push({
            animal_id: tr.dataset.id,
            item_id  : itemId,
            quantity : tr.querySelector('.qty-input').value,
            dosage   : tr.querySelector('.dosage-input').value,
            remarks  : tr.querySelector('.rem-input').value
        });
    });

    if (validationError) { btn.disabled = false; btn.textContent = 'Record Supplements'; return; }

    try {
        const res  = await fetch('../process/addBatchVitamins.php', { 
            method : 'POST',
            headers: { 'Content-Type': 'application/json' },
            body   : JSON.stringify({
                records,
                date           : dateInput,
                administered_by: document.getElementById('administered_by').value,
                event_ids      : incomingEventIds
            })
        });

        const data = await res.json();

        if (data.success) {
            if (incomingEventIds) {
                try {
                    const fd = new FormData();
                    fd.append('action', 'mark_events_done');
                    fd.append('event_ids', incomingEventIds);
                    await fetch('../process/eventManager.php', { method: 'POST', body: fd });
                } catch (e) { console.warn('mark_events_done (non-fatal):', e); }
            }
            alert('✅ Batch Recorded!');
            window.location.href = incomingEventIds ? 'events_scheduler.php' : window.location.pathname;
        } else {
            alert('❌ Error: ' + data.message);
            btn.disabled = false; btn.textContent = 'Record Supplements';
        }
    } catch (e) {
        alert('System error.');
        btn.disabled = false; btn.textContent = 'Record Supplements';
    }
}
</script>
</body>
</html>