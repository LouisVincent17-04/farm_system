<?php
// views/group_medication.php

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
        // --- NEW: AJAX HANDLER TO REFRESH MEDICINES LIVE ---
        if ($action === 'get_medicines') {
            $locId = $_GET['location_id'] ?? '';
            if (!$locId) { echo json_encode([]); exit; }
            
            $stmt = $conn->prepare("
                SELECT SUPPLY_ID, SUPPLY_NAME, TOTAL_STOCK, UNIT_ID,
                       (SELECT UNIT_ABBR FROM UNITS WHERE UNITS.UNIT_ID = MEDICINES.UNIT_ID LIMIT 1) as UNIT_ABBR
                FROM MEDICINES 
                WHERE LOCATION_ID = ? AND IS_ACTIVE = 1 
                ORDER BY SUPPLY_NAME ASC
            ");
            $stmt->execute([$locId]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            exit;
        }

        // --- FETCH MEDICATION HISTORY ---
        if ($action === 'get_medication_history') {
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
                $where[] = "(a.TAG_NO LIKE ? OR tt.ADMINISTERED_BY LIKE ? OR m.SUPPLY_NAME LIKE ?)";
                $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
            }
            if ($date_from) { $where[] = "DATE(tt.TRANSACTION_DATE) >= ?"; $params[] = $date_from; }
            if ($date_to)   { $where[] = "DATE(tt.TRANSACTION_DATE) <= ?"; $params[] = $date_to; }

            $where_sql = implode(" AND ", $where);

            $count_stmt = $conn->prepare("
                SELECT COUNT(*)
                FROM TREATMENT_TRANSACTIONS tt
                JOIN animal_records a ON tt.ANIMAL_ID = a.ANIMAL_ID
                JOIN locations l      ON a.LOCATION_ID = l.LOCATION_ID
                LEFT JOIN MEDICINES m ON tt.ITEM_ID = m.SUPPLY_ID
                WHERE $where_sql
            ");
            $count_stmt->execute($params);
            $total_records = $count_stmt->fetchColumn();

            $sql = "
                SELECT tt.*,
                       DATE_FORMAT(tt.TRANSACTION_DATE, '%m/%d/%Y %h:%i %p') AS FORMATTED_DATE,
                       a.TAG_NO,
                       l.LOCATION_NAME,
                       m.SUPPLY_NAME
                FROM TREATMENT_TRANSACTIONS tt
                JOIN animal_records a ON tt.ANIMAL_ID = a.ANIMAL_ID
                JOIN locations l      ON a.LOCATION_ID = l.LOCATION_ID
                LEFT JOIN MEDICINES m ON tt.ITEM_ID = m.SUPPLY_ID
                WHERE $where_sql
                ORDER BY tt.TRANSACTION_DATE DESC, tt.TT_ID DESC
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
// NORMAL PAGE LOAD
// =========================================================
error_reporting(0);
ini_set('display_errors', 0);
include '../config/Connection.php';

include '../security/checkAccess.php';
checkAccess('group_medication');
$page = "transactions";

$event_ids = trim($_GET['event_ids'] ?? '');

include '../common/navbar.php';
include '../common/chat_support.php';
include '../functions/getUsersLocation.php';

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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Group Medication | FarmPro</title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" type="text/css" href="https://npmcdn.com/flatpickr/dist/themes/dark.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    <style>
        :root { --accent:#8b5cf6; --accent-dark:#6d28d9; --bg:#0f172a; --card:#1e293b; --border:#334155; --text:#e2e8f0; --muted:#94a3b8; --success:#22c55e; --danger:#ef4444; }
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif; background:linear-gradient(135deg,var(--bg) 0%,#1e293b 100%); min-height:100vh; color:var(--text); }
        .container { max-width:1600px; margin:0 auto; padding:1.5rem; }
        .back-link { display:inline-flex; align-items:center; gap:8px; text-decoration:none; color:var(--muted); font-weight:600; font-size:.95rem; margin-bottom:1.5rem; transition:color .2s; }
        .back-link:hover { color:white; }

        #sync-alert { display:none; padding:1rem 1.5rem; border-radius:12px; margin-bottom:1.5rem; text-align:center; font-weight:600; font-size:.95rem; background:rgba(59,130,246,.1); border:1px solid #3b82f6; color:#60a5fa; }
        #sync-alert.success { background:rgba(34,197,94,.1); border-color:var(--success); color:#4ade80; }
        #sync-alert.error   { background:rgba(239,68,68,.1); border-color:var(--danger); color:#f87171; }

        #lock-banner { display:none; background:rgba(139,92,246,.12); border:1px solid rgba(139,92,246,.35); border-radius:12px; padding:.9rem 1.25rem; margin-bottom:1.25rem; color:#c4b5fd; font-size:.9rem; gap:10px; align-items:center; }
        #lock-banner.show { display:flex; }

        .main-grid { display:grid; grid-template-columns:380px 1fr; gap:1.5rem; align-items:start; }
        .control-panel { background:rgba(30,41,59,.7); backdrop-filter:blur(12px); border:1px solid rgba(148,163,184,.2); border-radius:16px; padding:1.5rem; position:sticky; top:1.5rem; box-shadow:0 10px 25px -5px rgba(0,0,0,.3); z-index:10; }
        .panel-title { font-size:1.25rem; font-weight:700; color:#fff; margin-bottom:5px; display:flex; align-items:center; gap:8px; }
        .panel-subtitle { font-size:.85rem; color:var(--muted); margin-bottom:1.5rem; }

        .form-group { margin-bottom:1rem; }
        .form-label { display:block; font-size:.85rem; color:#cbd5e1; margin-bottom:.4rem; font-weight:500; }
        .form-control { width:100%; padding:.75rem; background:var(--bg); border:1px solid var(--border); border-radius:8px; color:#fff; font-size:.95rem; transition:border-color .2s; outline:none; }
        .form-control:focus   { border-color:var(--accent); box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1); }
        .form-control:disabled { opacity:.5; cursor:not-allowed; background:#0a1020; }

        /* ── Select wrapper with lock icon ── */
        .select-wrap { position:relative; }
        .select-wrap .form-control { padding-right: 2.2rem; }
        .select-wrap .lock-badge { display:none; position:absolute; right:10px; top:50%; transform:translateY(-50%); font-size:.8rem; pointer-events:none; z-index:2; }
        .select-wrap.locked .lock-badge { display:inline; }
        .select-wrap.locked .form-control { border-color:#be185d; background:#0d0b1e; opacity:.85; cursor:not-allowed; }

        .stock-info { font-size:.8rem; color:#4ade80; margin-top:4px; display:block; text-align:right; }
        .summary-box { margin-top:1.5rem; background:var(--bg); padding:1rem; border-radius:12px; border-left:4px solid var(--accent); }
        .summary-row { display:flex; justify-content:space-between; margin-bottom:5px; font-size:.9rem; color:var(--muted); }

        .btn-mini { background:#334155; border:1px solid #475569; color:#fff; border-radius:8px; padding:8px 12px; cursor:pointer; font-size:.8rem; white-space:nowrap; flex-shrink:0; }
        .btn-mini:hover { background:#475569; }

        .btn-submit { width:100%; margin-top:1.5rem; padding:1rem; background:linear-gradient(135deg,var(--accent),var(--accent-dark)); border:none; border-radius:12px; color:white; font-weight:700; font-size:1rem; cursor:pointer; transition:all .2s; }
        .btn-submit:disabled { opacity:.5; cursor:not-allowed; filter:grayscale(1); }
        .btn-submit:hover:not(:disabled) { transform:translateY(-2px); box-shadow:0 4px 12px rgba(139,92,246,.4); }

        .resource-link { display: inline-flex; align-items: center; gap: 5px; font-size: 0.85rem; color: #a78bfa; text-decoration: none; transition: color 0.2s; font-weight: 600; }
        .resource-link:hover { color: #c4b5fd; text-decoration: underline; }

        .workspace-panel { display:flex; flex-direction:column; gap:1.5rem; }
        .picker-section { background:rgba(30,41,59,.4); border:1px solid rgba(255,255,255,.05); border-radius:16px; padding:1.5rem; }
        .section-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; }
        .section-title { font-size:1.1rem; font-weight:600; color:#fff; }

        .select-all-container { display:flex; align-items:center; gap:8px; font-size:.9rem; color:#a78bfa; cursor:pointer; padding:5px 10px; border-radius:6px; background:rgba(139,92,246,.1); border:1px solid rgba(139,92,246,.2); }
        .select-all-container input { cursor:pointer; accent-color:var(--accent); width:16px; height:16px; }

        .animal-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(100px,1fr)); gap:.75rem; max-height:250px; overflow-y:auto; }
        .animal-card { background:var(--card); border:1px solid var(--border); border-radius:8px; padding:.75rem; cursor:pointer; text-align:center; transition:all .2s; }
        .animal-card:hover { border-color:var(--muted); transform:translateY(-2px); }
        .animal-card.in-table { opacity:.45; pointer-events:none; border-color:#4ade80; }

        .table-section { background:var(--card); border:1px solid var(--border); border-radius:16px; overflow:hidden; }
        .custom-table { width:100%; border-collapse:collapse; }
        .custom-table th { background:var(--bg); color:var(--muted); font-size:.8rem; text-transform:uppercase; padding:1rem; text-align:left; font-weight:600; border-bottom:1px solid var(--border); }
        .custom-table td { padding:.75rem 1rem; border-bottom:1px solid rgba(255,255,255,.05); vertical-align:middle; color:var(--text); }
        .custom-table select, .custom-table input, .custom-table textarea { background:var(--bg); border:1px solid #475569; color:#fff; padding:6px 10px; border-radius:6px; width:100%; font-size:.9rem; font-family:inherit; outline:none; }
        .custom-table textarea { resize:vertical; min-height:38px; }
        .custom-table input:focus, .custom-table select:focus, .custom-table textarea:focus { border-color:var(--accent); box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1); }
        .btn-remove { background:transparent; border:none; color:#f87171; cursor:pointer; font-size:1.1rem; padding:5px; transition:color .2s; }
        .btn-remove:hover { color:var(--danger); }

        .history-filters { display:flex; gap:12px; padding:1rem; background:rgba(15,23,42,0.3); border-bottom:1px solid var(--border); flex-wrap:wrap; align-items:center; }
        .filter-input { width:180px !important; padding:8px 12px; background:var(--bg); border:1px solid var(--border); border-radius:6px; color:#fff; font-size:0.9rem; outline:none; }
        .filter-input:focus { border-color:var(--accent); }

        .pagination { display:flex; justify-content:center; gap:8px; padding:1.5rem; background:rgba(15,23,42,0.2); flex-wrap:wrap; }
        .pg-btn { background:var(--border); border:none; color:#fff; padding:6px 12px; border-radius:6px; cursor:pointer; font-size:0.9rem; transition:background .2s; }
        .pg-btn.active { background:var(--accent); }
        .pg-btn:disabled { opacity:0.3; cursor:not-allowed; }
        .pg-btn:hover:not(.active):not(:disabled) { background:#475569; }

        @media(max-width:1024px) {
            .main-grid { grid-template-columns:1fr; }
            .control-panel { position:static; }
        }
        @media(max-width:768px) {
            .filter-input { width:100% !important; }
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
    <div id="lock-banner">🔒 <strong>Scheduler Mode:</strong> Animals are pre-loaded from the event schedule. Add findings and save.</div>

    <div class="main-grid">

        <div class="control-panel">
            <div class="panel-title">💊 Group Medication</div>
            <div class="panel-subtitle">Mass treatment distribution by location.</div>

            <form id="settingsForm">
                <div style="background:rgba(255,255,255,.03);padding:15px;border-radius:8px;margin-bottom:1.5rem;border:1px dashed #475569;">
                    <label class="form-label" style="color:#c4b5fd;margin-bottom:8px;">STEP 1: Locate Group</label>
                    <div class="form-group" style="margin-bottom:.5rem;">
                        <div class="select-wrap" id="wrap-location">
                            <select id="location_id" class="form-control" onchange="handleLocationChange(this.value)" <?php echo ($USER_LOCATION_ != 1000) ? 'style="background-color:#0a1020; pointer-events:none; color:#94a3b8;"' : ''; ?>>
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
                            <select id="building_id" class="form-control" onchange="loadPens(this.value)" disabled><option value="">Select Building</option></select>
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

                <label class="form-label" style="color:#c4b5fd;">STEP 2: Default Settings</label>

                <div class="form-group">
                    <label class="form-label">Default Medication <span style="color:#f87171">*</span></label>
                    <div style="display:flex;flex-direction:column;gap:4px;">
                        <div style="display:flex;gap:8px;">
                            <select id="default_item" class="form-control" onchange="updateAllItems()" disabled><option value="">Select Location First</option></select>
                            <button type="button" class="btn-mini" onclick="updateAllItems()">Apply</button>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 4px;">
                            <a href="purch_medicines.php" target="_blank" class="resource-link" title="Opens in a new tab">
                                Manage / Purchase Meds ↗
                            </a>
                            <button type="button" id="refresh-meds-btn" class="btn-mini" onclick="refreshMedsList()" style="background: rgba(139, 92, 246, 0.2); color: #c4b5fd; border: 1px solid rgba(139, 92, 246, 0.3);">
                                ↻ Refresh Meds
                            </button>
                        </div>
                        <span id="default-stock-display" class="stock-info" style="margin-top: 8px;"></span>
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
                    <label class="form-label">Default Qty / Head <span style="color:#f87171">*</span></label>
                    <div style="display:flex;gap:8px;">
                        <input type="number" id="default_qty" class="form-control" step="0.01" min="0.01" value="1.00" oninput="updateAllQuantities()">
                        <button type="button" class="btn-mini" onclick="updateAllQuantities()">Apply</button>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Default Remarks</label>
                    <div style="display:flex;gap:8px;">
                        <input type="text" id="default_remarks" class="form-control" placeholder="e.g. Routine Treatment">
                        <button type="button" class="btn-mini" onclick="updateAllRemarks()">Apply</button>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Veterinarian / Personnel</label>
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
                    <label class="form-label">Date Administered</label>
                    <input type="text" id="txn_date" class="form-control" placeholder="Select Date & Time">
                </div>

                <div class="summary-box">
                    <div class="summary-row"><span>Animals Selected:</span><span id="sum-count" style="color:#fff;">0</span></div>
                    <div style="margin-top:8px;"><div id="stock-warning" style="font-size:.85rem;"></div></div>
                </div>

                <button type="button" class="btn-submit" id="btn-submit" onclick="submitBatch()" disabled>Record Treatments</button>
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
                            <th style="width:30%;">Medication</th>
                            <th style="width:15%;">Dosage</th>
                            <th style="width:10%;">Qty</th>
                            <th>Remarks</th>
                            <th style="width:40px;"></th>
                        </tr>
                    </thead>
                    <tbody id="medication-list">
                        <tr id="empty-row"><td colspan="6" style="text-align:center;padding:2rem;color:#64748b;">No animals added.</td></tr>
                    </tbody>
                </table>
            </div>

            <div class="table-section">
                <div class="section-header" style="padding:1rem;border-bottom:1px solid var(--border);">
                    <div class="section-title">🕒 Recent Treatment Logs</div>
                </div>

                <div class="history-filters">
                    <input type="text" id="histSearch" class="filter-input" placeholder="Search Tag, Person, Med..." oninput="loadHistory(1)">
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
                            <th>Medicine</th>
                            <th>Qty Used</th>
                            <th>Dosage</th>
                            <th>Remarks</th>
                            <th>Cost</th>
                        </tr>
                    </thead>
                    <tbody id="history-list">
                        <tr><td colspan="8" style="text-align:center;padding:2rem;color:#64748b;">Loading...</td></tr>
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
let currentInventory  = {};
let schedulerMode     = false;
let fpTxnDate;

const incomingEventIds = "<?= htmlspecialchars($event_ids) ?>";
const USER_LOCATION    = <?php echo json_encode($USER_LOCATION_); ?>;
const PAGE_URL         = window.location.pathname;

document.addEventListener('DOMContentLoaded', () => {
    fpTxnDate = flatpickr("#txn_date", {
        enableTime: true,
        dateFormat: "Y-m-d H:i",
        altInput: true,
        altFormat: "m/d/Y h:i K",
        allowInput: true
    });
    fpTxnDate.clear();

    flatpickr("#histFrom", { dateFormat:"Y-m-d", altInput:true, altFormat:"m/d/Y", onChange: () => loadHistory(1) });
    flatpickr("#histTo",   { dateFormat:"Y-m-d", altInput:true, altFormat:"m/d/Y", onChange: () => loadHistory(1) });

    if (incomingEventIds) {
        handleEventAutoSelect(incomingEventIds);
    } else if (USER_LOCATION != 1000) {
        const locDrop = document.getElementById('location_id');
        locDrop.value = USER_LOCATION;
        handleLocationChange(USER_LOCATION);
        lockField('wrap-location', 'location_id');
    }

    loadHistory(1);
});

// ── SYNC BANNER ───────────────────────────────────────────────────────────
function showSyncBanner(type, msg) {
    const el = document.getElementById('sync-alert');
    el.className = type;
    document.getElementById('sync-msg').textContent = msg;
    el.style.display = 'block';
}
function hideSyncBanner() { document.getElementById('sync-alert').style.display = 'none'; }

// ── REFRESH MEDICATIONS ──────────────────────────────────────────────────────
async function refreshMedsList() {
    const locId = document.getElementById('location_id').value;
    if (!locId) { alert("Please select a Location first."); return; }

    const btn = document.getElementById('refresh-meds-btn');
    btn.innerHTML = '↻ Loading...';
    btn.disabled = true;

    try {
        await loadMedicationsData(locId);
        // If an item was previously selected, try to re-select it
        const currentSelection = document.getElementById('default_item').value;
        if (currentSelection) {
            setSelectValue('default_item', currentSelection);
        }
        updateAllItems(); // Update all rows in the table
        btn.innerHTML = '↻ Refresh Meds';
        btn.disabled = false;
    } catch (e) {
        btn.innerHTML = '❌ Error';
        setTimeout(() => { btn.innerHTML = '↻ Refresh Meds'; btn.disabled = false; }, 2000);
    }
}

// ── SAFE SELECT-VALUE HELPER ──────────────────────────────────────────────────
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

// ── AUTO-SELECT ──────────────────────────────────────────────────────────────
async function handleEventAutoSelect(eventIds) {
    showSyncBanner('loading', '🔄 Auto-Sync Active: Loading scheduled animals and medications…');
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

        await loadPens(bldgId);
        if (!setSelectValue('pen_id', penId)) {
            showSyncBanner('error', `❌ Pen ID not found.`); return;
        }
        lockField('wrap-pen', 'pen_id');

        await loadMedicationsData(locId);
        if (!setSelectValue('default_item', itemId)) {
            showSyncBanner('error', `❌ Supplement ID not found in this location.`); return;
        }
        lockField('wrap-vitamin', 'default_item');
        updateAllItems();

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
        console.error(e);
        showSyncBanner('error', '❌ Auto-sync failed. Select animals manually.');
        setTimeout(hideSyncBanner, 4000);
    }
}

// ── CASCADING DROPDOWNS ───────────────────────────────────────────────────
function handleLocationChange(locId) {
    clearTable();
    loadBuildingsData(locId);
    loadMedicationsData(locId);
}

function loadBuildingsData(locId) {
    return new Promise(resolve => {
        const bldg = document.getElementById('building_id');
        bldg.innerHTML = '<option>Loading…</option>';
        document.getElementById('pen_id').innerHTML = '<option>Select Pen</option>';
        document.getElementById('pen_id').disabled = true;
        if (!locId) { bldg.innerHTML = '<option value="">Select Building</option>'; bldg.disabled = true; resolve(); return; }
        fetch(`../process/getBuildingsByLocation.php?location_id=${locId}`)
            .then(r => r.json())
            .then(data => {
                bldg.innerHTML = '<option value="">Select Building</option>';
                (data.buildings || []).forEach(b => bldg.add(new Option(b.BUILDING_NAME, b.BUILDING_ID)));
                bldg.disabled = false; resolve();
            }).catch(() => { bldg.innerHTML = '<option value="">Error</option>'; resolve(); });
    });
}

function loadMedicationsData(locId) {
    return new Promise(resolve => {
        const sel = document.getElementById('default_item');
        sel.innerHTML = '<option>Loading Meds…</option>';
        sel.disabled  = true;
        currentInventory = {};
        if (!locId) { sel.innerHTML = '<option value="">Select Location First</option>'; resolve(); return; }
        
        fetch(`?action=get_medicines&location_id=${locId}`)
            .then(r => r.json())
            .then(data => {
                sel.innerHTML = '<option value="">Select Medicine</option>';
                data.forEach(m => {
                    currentInventory[m.SUPPLY_ID] = { name: m.SUPPLY_NAME, stock: parseFloat(m.TOTAL_STOCK), unit: m.UNIT_ABBR, unit_id: m.UNIT_ID };
                    sel.add(new Option(`${m.SUPPLY_NAME} (${m.TOTAL_STOCK} ${m.UNIT_ABBR})`, m.SUPPLY_ID));
                });
                sel.disabled = false; resolve();
            }).catch(() => { sel.innerHTML = '<option value="">Error loading meds</option>'; resolve(); });
    });
}

function loadPens(bldgId) {
    const pen = document.getElementById('pen_id');
    pen.innerHTML = '<option>Loading…</option>';
    if (!bldgId) { pen.innerHTML = '<option value="">Select Pen</option>'; pen.disabled = true; return; }
    fetch(`../process/getPensByBuilding.php?building_id=${bldgId}`)
        .then(r => r.json())
        .then(data => {
            pen.innerHTML = '<option value="">Select Pen</option>';
            (data.pens || []).forEach(p => pen.add(new Option(p.PEN_NAME, p.PEN_ID)));
            pen.disabled = false;
        });
}

function loadAnimals(penId) {
    const grid    = document.getElementById('animal-grid');
    const wrapper = document.getElementById('select-all-wrapper');
    grid.innerHTML = '<div style="grid-column:1/-1;text-align:center;color:#94a3b8;">Loading…</div>';
    wrapper.style.display = 'none';
    if (!penId) return;
    fetch(`../process/getAnimalsByPen.php?pen_id=${penId}`)
        .then(r => r.json())
        .then(data => {
            grid.innerHTML    = '';
            currentPenAnimals = (data.animal_record || []).filter(a => a.IS_ACTIVE == 1);
            if (!currentPenAnimals.length) { grid.innerHTML = '<div style="grid-column:1/-1;text-align:center;">No active animals.</div>'; return; }
            wrapper.style.display = 'flex';
            updateSelectAllState();
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

// ── TABLE OPS ─────────────────────────────────────────────────────────────
function addAnimalToTable(animal, preselectedItemId = null) {
    if (selectedAnimals.has(String(animal.ANIMAL_ID))) return;
    const emptyRow = document.getElementById('empty-row');
    if (emptyRow) emptyRow.remove();

    const defQty    = document.getElementById('default_qty').value;
    const defDosage = document.getElementById('default_dosage').value;
    const defRem    = document.getElementById('default_remarks').value;
    const itemToSelect = preselectedItemId || document.getElementById('default_item').value;

    let optionsHtml = '<option value="">Select Med</option>';
    for (const [id, item] of Object.entries(currentInventory)) {
        optionsHtml += `<option value="${id}" ${id == itemToSelect ? 'selected' : ''}>${item.name} (${item.stock} ${item.unit})</option>`;
    }

    const tr = document.createElement('tr');
    tr.id = `row-${animal.ANIMAL_ID}`;
    tr.dataset.id = String(animal.ANIMAL_ID);
    tr.innerHTML = `
        <td style="font-weight:600;">${animal.TAG_NO}</td>
        <td><select class="item-select" onchange="updateCalculations()">${optionsHtml}</select></td>
        <td><input type="text" class="dosage-input" value="${defDosage}" placeholder="e.g. 5ml"></td>
        <td><input type="number" class="qty-input" value="${defQty}" step="0.01" min="0.01" oninput="updateCalculations()"></td>
        <td><input type="text" class="rem-input" value="${defRem}" placeholder="Notes…"></td>
        <td><button class="btn-remove" onclick="removeAnimal(${animal.ANIMAL_ID})">×</button></td>
    `;
    document.getElementById('medication-list').appendChild(tr);
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
        document.getElementById('medication-list').innerHTML = '<tr id="empty-row"><td colspan="6" style="text-align:center;padding:2rem;color:#64748b;">No animals added.</td></tr>';
    }
    updateCalculations();
    updateSelectAllState();
}

function clearTable() { Array.from(selectedAnimals).forEach(id => removeAnimal(id)); }
function toggleSelectAll(cb) {
    if (cb.checked) currentPenAnimals.forEach(a => addAnimalToTable(a));
    else currentPenAnimals.forEach(a => removeAnimal(a.ANIMAL_ID));
}
function updateSelectAllState() {
    const cb = document.getElementById('select-all-check');
    if (!cb) return;
    cb.checked = currentPenAnimals.length > 0 && currentPenAnimals.every(a => selectedAnimals.has(String(a.ANIMAL_ID)));
}

function updateAllQuantities() { document.querySelectorAll('.qty-input').forEach(i => i.value = document.getElementById('default_qty').value); updateCalculations(); }
function updateAllDosages()    { document.querySelectorAll('.dosage-input').forEach(i => i.value = document.getElementById('default_dosage').value); }
function updateAllRemarks()    { document.querySelectorAll('.rem-input').forEach(i => i.value = document.getElementById('default_remarks').value); }
function updateAllItems() {
    const val  = document.getElementById('default_item').value;
    const disp = document.getElementById('default-stock-display');
    
    // Create new HTML based on current inventory to ensure all rows are in sync
    let optionsHtml = '<option value="">Select Med</option>';
    for (const [id, item] of Object.entries(currentInventory)) {
        optionsHtml += `<option value="${id}">${item.name} (${item.stock} ${item.unit})</option>`;
    }

    document.querySelectorAll('.item-select').forEach(sel => {
        sel.innerHTML = optionsHtml;
        sel.value = val;
    });

    disp.textContent = val && currentInventory[val] ? `Available: ${currentInventory[val].stock} ${currentInventory[val].unit}` : '';
    updateCalculations();
}

function updateCalculations() {
    document.getElementById('sum-count').textContent = selectedAnimals.size;
    const totals = {}; let hasError = false;
    document.querySelectorAll('#medication-list tr[id^="row-"]').forEach(tr => {
        const itemId = tr.querySelector('.item-select').value;
        const qty    = parseFloat(tr.querySelector('.qty-input').value) || 0;
        if (!itemId) { hasError = true; return; }
        totals[itemId] = (totals[itemId] || 0) + qty;
    });
    const warnings = [];
    for (const [id, needed] of Object.entries(totals)) {
        if (currentInventory[id] && needed > currentInventory[id].stock) {
            warnings.push(`Not enough ${currentInventory[id].name}! Need ${needed.toFixed(2)}`);
            hasError = true;
        }
    }
    const warn = document.getElementById('stock-warning');
    warn.innerHTML = warnings.join('<br>');
    warn.style.color = warnings.length ? '#f87171' : '#4ade80';
    document.getElementById('btn-submit').disabled = selectedAnimals.size === 0 || hasError;
}

// ── HISTORY ───────────────────────────────────────────────────────────────
async function loadHistory(page) {
    const list = document.getElementById('history-list');
    const pg   = document.getElementById('pagination');
    list.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:1rem;color:#64748b;">Loading...</td></tr>';

    const search = document.getElementById('histSearch').value;
    const loc    = document.getElementById('histLoc')?.value || '';
    const from   = document.getElementById('histFrom').value;
    const to     = document.getElementById('histTo').value;

    try {
        const res = await fetch(`${PAGE_URL}?action=get_medication_history&p=${page}&search=${encodeURIComponent(search)}&loc_filter=${loc}&date_from=${from}&date_to=${to}`);
        const raw = await res.text();
        let result;
        try { result = JSON.parse(raw); } catch(e) { console.error('History non-JSON:', raw); list.innerHTML = '<tr><td colspan="8" style="text-align:center;color:#f87171;">Server error. Check console.</td></tr>'; return; }

        if (!result.success || !result.data) {
            list.innerHTML = `<tr><td colspan="8" style="text-align:center;color:var(--danger);">Error: ${result.error || 'Unknown error'}</td></tr>`;
            if (pg) pg.innerHTML = '';
            return;
        }

        if (!result.data.length) {
            list.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:1.5rem;color:#64748b;">No records found.</td></tr>';
            if (pg) pg.innerHTML = '';
            return;
        }

        list.innerHTML = result.data.map(row => `
            <tr>
                <td style="font-size:.85rem;color:var(--muted);white-space:nowrap;">${row.FORMATTED_DATE}</td>
                <td><span style="color:var(--accent);font-weight:bold;">${row.TAG_NO}</span></td>
                <td style="font-size:.9rem;">${row.ADMINISTERED_BY || '—'}</td>
                <td><span style="color:#a78bfa;font-weight:bold;">${row.SUPPLY_NAME || '—'}</span></td>
                <td style="font-size:.9rem;text-align:center;">${row.QUANTITY_USED ?? '—'}</td>
                <td style="font-size:.9rem;">${row.DOSAGE || '—'}</td>
                <td style="font-size:.9rem;">${row.REMARKS || '—'}</td>
                <td style="color:#facc15;font-weight:bold;white-space:nowrap;">₱ ${parseFloat(row.TOTAL_COST || 0).toFixed(2)}</td>
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
        console.error('loadHistory error:', e);
        list.innerHTML = '<tr><td colspan="8" style="text-align:center;color:var(--danger);">System Error. Check console.</td></tr>';
    }
}

// ── SUBMIT ────────────────────────────────────────────────────────────────
async function submitBatch() {
    if (!confirm(`Record medication for ${selectedAnimals.size} animal(s)?`)) return;

    const dateInput = document.getElementById('txn_date').value;
    if (!dateInput) { alert("Please select a valid Date & Time Administered."); return; }

    const btn = document.getElementById('btn-submit');
    btn.disabled = true; btn.textContent = 'Processing…';

    const records = [];
    document.querySelectorAll('#medication-list tr[id^="row-"]').forEach(tr => {
        const itemId = tr.querySelector('.item-select').value;
        records.push({
            animal_id: tr.dataset.id,
            item_id  : itemId,
            unit_id  : currentInventory[itemId]?.unit_id,
            quantity : tr.querySelector('.qty-input').value,
            dosage   : tr.querySelector('.dosage-input').value,
            remarks  : tr.querySelector('.rem-input').value
        });
    });

    try {
        const res  = await fetch('../process/addBatchMedication.php', {
            method : 'POST',
            headers: { 'Content-Type': 'application/json' },
            body   : JSON.stringify({
                records,
                date           : dateInput,
                administered_by: document.getElementById('administered_by').value,
                event_ids      : incomingEventIds
            })
        });

        const raw = await res.text();
        let data;
        try { data = JSON.parse(raw); } catch(e) { console.error('Submit non-JSON:', raw); alert('❌ Server error. Check console.'); btn.disabled = false; btn.textContent = 'Record Treatments'; return; }

        if (data.success) {
            if (incomingEventIds) {
                try {
                    const fd = new FormData();
                    fd.append('action', 'mark_events_done');
                    fd.append('event_ids', incomingEventIds);
                    await fetch('../process/eventManager.php', { method: 'POST', body: fd });
                } catch (e) {
                    console.warn('mark_events_done (non-fatal):', e);
                }
            }
            alert('✅ Treatments Recorded!');
            loadHistory(1); 
            window.location.href = incomingEventIds ? 'events_scheduler.php' : window.location.pathname;
        } else {
            alert('❌ Error: ' + data.message);
            btn.disabled = false; btn.textContent = 'Record Treatments';
        }
    } catch (e) {
        console.error(e);
        alert('System error.');
        btn.disabled = false; btn.textContent = 'Record Treatments';
    }
}
</script>
</body>
</html>