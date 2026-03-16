<?php
// views/group_checkup.php
ob_start(); 
error_reporting(0);
ini_set('display_errors', 0);
include '../config/Connection.php';

include '../security/checkAccess.php';
checkAccess('group_checkup');
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
        if ($action === 'get_buildings' && isset($_GET['location_id'])) {
            $stmt = $conn->prepare("SELECT BUILDING_ID, BUILDING_NAME FROM buildings WHERE LOCATION_ID = ? ORDER BY BUILDING_NAME");
            $stmt->execute([$_GET['location_id']]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC)); exit;
        }
        
        if ($action === 'get_bldg_animals_for_sale' && isset($_GET['building_id'])) {
            $bldg_id = $_GET['building_id'];
            $sql = "SELECT p.PEN_ID, p.PEN_NAME, a.ANIMAL_ID, a.TAG_NO, a.CURRENT_ACTUAL_WEIGHT 
                    FROM PENS p 
                    LEFT JOIN animal_records a ON p.PEN_ID = a.PEN_ID AND a.IS_ACTIVE = 1 AND a.CURRENT_STATUS != 'Sold'
                    WHERE p.BUILDING_ID = ? ORDER BY p.PEN_NAME, a.TAG_NO";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$bldg_id]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $data = [];
            foreach($results as $r) {
                $pid = $r['PEN_ID'];
                if(!isset($data[$pid])) $data[$pid] = ['pen_id' => $pid, 'pen_name' => $r['PEN_NAME'], 'animals' => []];
                if($r['ANIMAL_ID']) $data[$pid]['animals'][] = $r;
            }
            echo json_encode(['success' => true, 'pens' => array_values($data)]); exit;
        }

        // --- FETCH CHECKUP HISTORY ---
        if ($action === 'get_checkup_history') {
            $limit = 10;
            $page_num = isset($_GET['p']) ? (int)$_GET['p'] : 1;
            $offset = ($page_num - 1) * $limit;
            $search = $_GET['search'] ?? '';
            $loc_f = $_GET['loc_filter'] ?? '';
            $date_from = $_GET['date_from'] ?? '';
            $date_to = $_GET['date_to'] ?? '';

            $where = ["1=1"];
            $params = [];

            if($USER_LOCATION_ != 1000) { $where[] = "l.LOCATION_ID = ?"; $params[] = $USER_LOCATION_; }
            if($loc_f) { $where[] = "l.LOCATION_ID = ?"; $params[] = $loc_f; }
            if($search) { $where[] = "(a.TAG_NO LIKE ? OR c.VET_NAME LIKE ? OR c.REMARKS LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%"; }
            if($date_from) { $where[] = "DATE(c.CHECKUP_DATE) >= ?"; $params[] = $date_from; }
            if($date_to) { $where[] = "DATE(c.CHECKUP_DATE) <= ?"; $params[] = $date_to; }

            $where_sql = implode(" AND ", $where);

            // Count total
            $count_stmt = $conn->prepare("SELECT COUNT(*) FROM check_ups c JOIN animal_records a ON c.ANIMAL_ID = a.ANIMAL_ID JOIN locations l ON a.LOCATION_ID = l.LOCATION_ID WHERE $where_sql");
            $count_stmt->execute($params);
            $total_records = $count_stmt->fetchColumn();

            // Fetch data
            $sql = "SELECT c.*, DATE_FORMAT(c.CHECKUP_DATE, '%m/%d/%Y %h:%i %p') AS FORMATTED_DATE, a.TAG_NO, l.LOCATION_NAME 
                    FROM check_ups c 
                    JOIN animal_records a ON c.ANIMAL_ID = a.ANIMAL_ID 
                    JOIN locations l ON a.LOCATION_ID = l.LOCATION_ID
                    WHERE $where_sql 
                    ORDER BY c.CHECKUP_DATE DESC, c.CHECK_UP_ID DESC 
                    LIMIT $limit OFFSET $offset";
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'data' => $rows,
                'total' => $total_records,
                'pages' => ceil($total_records / $limit),
                'curr' => $page_num
            ]);
            exit;
        }
    } catch (Exception $e) { echo json_encode(['error' => $e->getMessage()]); exit; }
}

// --- 2. PAGE INIT ---
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
} catch (Exception $e) { $locs = []; $personnel = []; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Group Checkup | FarmPro</title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" type="text/css" href="https://npmcdn.com/flatpickr/dist/themes/dark.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    <style>
        :root { --accent:#0ea5e9; --accent-dark:#0369a1; --bg:#0f172a; --card:#1e293b; --border:#334155; --text:#e2e8f0; --muted:#94a3b8; --success:#22c55e; --danger:#ef4444; --warning:#f59e0b;}
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif; background:linear-gradient(135deg,var(--bg) 0%,#1e293b 100%); min-height:100vh; color:var(--text); }
        .container { max-width:1600px; margin:0 auto; padding:1.5rem; }
        .back-link { display:inline-flex; align-items:center; gap:8px; text-decoration:none; color:var(--muted); font-weight:600; font-size:.95rem; margin-bottom:1.5rem; transition:color .2s; }
        .back-link:hover { color:white; }

        #sync-alert { display:none; padding:1rem 1.5rem; border-radius:12px; margin-bottom:1.5rem; text-align:center; font-weight:600; background:rgba(59,130,246,.1); border:1px solid #3b82f6; color:#60a5fa; }
        #lock-banner { display:none; background:rgba(14,165,233,.12); border:1px solid rgba(14,165,233,.35); border-radius:12px; padding:.9rem 1.25rem; margin-bottom:1.25rem; color:#7dd3fc; font-size:.9rem; gap:10px; align-items:center; }
        #lock-banner.show { display:flex; }

        .main-grid { display:grid; grid-template-columns:360px 1fr; gap:1.5rem; align-items:start; }
        .control-panel { background:rgba(30,41,59,.7); backdrop-filter:blur(12px); border:1px solid rgba(148,163,184,.2); border-radius:16px; padding:1.5rem; position:sticky; top:1.5rem; box-shadow:0 10px 25px -5px rgba(0,0,0,.3); }
        
        .panel-title { font-size:1.25rem; font-weight:700; color:#fff; margin-bottom:5px; display:flex; align-items:center; gap:8px; }
        .panel-subtitle { font-size:.85rem; color:var(--muted); margin-bottom:1.5rem; }

        .form-group { margin-bottom:1rem; }
        .form-label { display:block; font-size:.85rem; color:#cbd5e1; margin-bottom:.4rem; font-weight:500; }
        .form-control { width:100%; padding:.75rem; background:var(--bg); border:1px solid var(--border); border-radius:8px; color:#fff; font-size:.95rem; transition:border-color .2s; outline:none; }
        .form-control:focus { border-color:var(--accent); box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.1); }
        .form-control:disabled { opacity:.5; cursor:not-allowed; background:#0a1020; }

        .btn-mini { background:#334155; border:1px solid #475569; color:#fff; border-radius:8px; padding:8px 12px; cursor:pointer; font-size:.8rem; white-space:nowrap; }
        .btn-submit { width:100%; margin-top:1.5rem; padding:1rem; background:linear-gradient(135deg,var(--accent),var(--accent-dark)); border:none; border-radius:12px; color:white; font-weight:700; font-size:1rem; cursor:pointer; transition:all .2s; }
        .btn-submit:disabled { opacity:.5; filter:grayscale(1); }

        .summary-box { margin-top:1.5rem; background:var(--bg); padding:1rem; border-radius:12px; border-left:4px solid var(--accent); }
        .summary-row { display:flex; justify-content:space-between; font-size:.9rem; color:var(--muted); margin-bottom:4px; }

        .workspace-panel { display:flex; flex-direction:column; gap:1.5rem; }
        .picker-section { background:rgba(30,41,59,.4); border:1px solid rgba(255,255,255,.05); border-radius:16px; padding:1.5rem; }
        .animal-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(100px,1fr)); gap:.75rem; max-height:250px; overflow-y:auto; }
        .animal-card { background:var(--card); border:1px solid var(--border); border-radius:8px; padding:.75rem; cursor:pointer; text-align:center; transition:all .2s; }
        .animal-card:hover { border-color:var(--muted); transform:translateY(-2px); }
        .animal-card.in-table { opacity:.45; pointer-events:none; border-color:#4ade80; }

        .table-section { background:var(--card); border:1px solid var(--border); border-radius:16px; overflow:hidden; }
        .section-header { display:flex; justify-content:space-between; align-items:center; padding:1rem; border-bottom:1px solid var(--border); }
        .section-title { font-size:1.1rem; font-weight:600; color:#fff; }
        
        .custom-table { width:100%; border-collapse:collapse; }
        .custom-table th { background:rgba(15,23,42,0.5); color:var(--muted); font-size:.8rem; text-transform:uppercase; padding:1rem; text-align:left; font-weight:600; border-bottom:1px solid var(--border); }
        .custom-table td { padding:.75rem 1rem; border-bottom:1px solid rgba(255,255,255,.05); vertical-align:middle; color:var(--text); }
        
        /* UPDATED: History Filter Styling with explicit widths */
        .history-filters { display:flex; gap:12px; padding:1rem; background:rgba(15,23,42,0.3); border-bottom:1px solid var(--border); flex-wrap:wrap; align-items:center;}
        .filter-input { width: 180px !important; padding:8px 12px; background:var(--bg); border:1px solid var(--border); border-radius:6px; color:#fff; font-size:0.9rem; outline:none; }
        .filter-input:focus { border-color:var(--accent); }
        
        .pagination { display:flex; justify-content:center; gap:8px; padding:1.5rem; background:rgba(15,23,42,0.2); }
        .pg-btn { background:var(--border); border:none; color:#fff; padding:6px 12px; border-radius:6px; cursor:pointer; font-size:0.9rem; }
        .pg-btn.active { background:var(--accent); }
        .pg-btn:disabled { opacity:0.3; cursor:not-allowed; }

        .badge-status { padding: 4px 8px; border-radius: 6px; font-size: 0.75rem; font-weight: bold; text-transform: uppercase; }
        .status-Healthy { background: rgba(34, 197, 94, 0.15); color: #4ade80; }
        .status-Sick { background: rgba(239, 68, 68, 0.15); color: #f87171; }
        .status-UnderTreatment { background: rgba(14, 165, 233, 0.15); color: #38bdf8; }
        .status-Critical { background: rgba(239, 68, 68, 0.3); color: #ff4d4d; border: 1px solid #ef4444; }

        @media(max-width:1024px) {
            .main-grid { grid-template-columns:1fr; }
            .control-panel { position:static; }
        }
        @media(max-width:768px) {
            .filter-input { width: 100% !important; }
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
    <div id="lock-banner">🔒 <strong>Scheduler Mode:</strong> Animals are pre-loaded from schedule. Add findings and save.</div>

    <div class="main-grid">
        <div class="control-panel">
            <div class="panel-title">🩺 Group Checkup</div>
            <div class="panel-subtitle">Record batch health inspections.</div>

            <form id="settingsForm">
                <div style="background:rgba(255,255,255,.03);padding:12px;border-radius:8px;margin-bottom:1rem;border:1px dashed #475569;">
                    <label class="form-label" style="color:#7dd3fc;margin-bottom:8px;">STEP 1: Locate Group</label>
                    <div class="form-group">
                        <select id="location_id" class="form-control" onchange="handleLocationChange(this.value)" <?php echo ($USER_LOCATION_ != 1000) ? 'style="pointer-events: none; opacity: 0.7;"' : ''; ?>>
                            <?php if($USER_LOCATION_ == 1000): ?><option value="">Select Location</option><?php endif; ?>
                            <?php foreach($locs as $l): ?>
                                <option value="<?= $l['LOCATION_ID'] ?>" <?= ($USER_LOCATION_ != 1000 && $l['LOCATION_ID'] == $USER_LOCATION_) ? 'selected' : '' ?>><?= htmlspecialchars($l['LOCATION_NAME']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <select id="building_id" class="form-control" onchange="loadPens(this.value)" disabled><option value="">Select Building</option></select>
                    </div>
                    <div class="form-group">
                        <select id="pen_id" class="form-control" onchange="loadAnimals(this.value)" disabled><option value="">Select Pen</option></select>
                    </div>
                </div>

                <label class="form-label" style="color:#7dd3fc;">STEP 2: Batch Defaults</label>
                <div class="form-group">
                    <label class="form-label">Health Status</label>
                    <div style="display:flex;gap:8px;">
                        <select id="default_status" class="form-control">
                            <option value="Healthy">✅ Healthy</option>
                            <option value="Sick">🤒 Sick</option>
                            <option value="Under Treatment">💊 Under Treatment</option>
                        </select>
                        <button type="button" class="btn-mini" onclick="updateAllStatus()">Apply</button>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Veterinarian / Personnel</label>
                    <select id="examined_by" class="form-control">
                        <option value="">— Select Person —</option>
                        <?php foreach($personnel as $p): ?>
                            <option value="<?= htmlspecialchars($p['FULL_NAME']) ?>"><?= htmlspecialchars($p['FULL_NAME']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Checkup Date</label>
                    <input type="text" id="checkup_date" class="form-control date-picker" placeholder="Select Date">
                </div>

                <div class="form-group">
                    <label class="form-label">Cost per Head (₱)</label>
                    <input type="number" id="cost_per_head" class="form-control" min="0" step="0.01" value="0" oninput="updateCount()">
                </div>

                <div class="summary-box">
                    <div class="summary-row"><span>Animals:</span><span id="sum-count">0</span></div>
                    <div class="summary-row"><span>Total Cost:</span><span id="sum-cost">₱ 0.00</span></div>
                </div>

                <button type="button" class="btn-submit" id="btn-submit" onclick="submitBatch()" disabled>Record Checkups</button>
            </form>
        </div>

        <div class="workspace-panel">
            <div class="picker-section" id="pickerSection">
                <div class="section-header">
                    <div class="section-title">🐷 Step 3: Select Animals</div>
                    <label class="select-all-container" style="display:none;" id="select-all-wrapper">
                        <input type="checkbox" id="select-all-check" onchange="toggleSelectAll(this)"> Select All
                    </label>
                </div>
                <div id="animal-grid" class="animal-grid">
                    <div style="grid-column:1/-1;text-align:center;padding:2rem;color:#64748b;border:1px dashed #475569;border-radius:8px;">Select a Pen to load animals.</div>
                </div>
            </div>

            <div class="table-section">
                <div class="section-header">
                    <div class="section-title">📋 Step 4: Record Findings</div>
                    <button onclick="clearTable()" style="background:transparent;border:1px solid #f87171;color:#f87171;padding:4px 10px;border-radius:4px;cursor:pointer;">Clear</button>
                </div>
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th style="width:15%;">Tag No</th>
                            <th style="width:20%;">Status</th>
                            <th>Findings / Notes</th>
                            <th style="width:15%;">Weight (kg)</th>
                            <th style="width:40px;"></th>
                        </tr>
                    </thead>
                    <tbody id="checkup-list">
                        <tr id="empty-row"><td colspan="5" style="text-align:center;padding:2rem;color:#64748b;">No animals added yet.</td></tr>
                    </tbody>
                </table>
            </div>

            <div class="table-section">
                <div class="section-header">
                    <div class="section-title">🕒 Recent Checkup Logs</div>
                </div>
                
                <div class="history-filters">
                    <input type="text" id="histSearch" class="filter-input" placeholder="Search Tag or Person..." oninput="loadHistory(1)">
                    <?php if($USER_LOCATION_ == 1000): ?>
                    <select id="histLoc" class="filter-input" onchange="loadHistory(1)">
                        <option value="">All Locations</option>
                        <?php foreach($locs as $l): ?><option value="<?= $l['LOCATION_ID'] ?>"><?= $l['LOCATION_NAME'] ?></option><?php endforeach; ?>
                    </select>
                    <?php endif; ?>
                    
                    <input type="text" id="histFrom" class="filter-input" placeholder="Date From...">
                    <input type="text" id="histTo" class="filter-input" placeholder="Date To...">
                </div>

                <table class="custom-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Tag</th>
                            <th>Status</th>
                            <th>Findings</th>
                            <th>Examiner</th>
                            <th>Cost</th>
                        </tr>
                    </thead>
                    <tbody id="history-list"></tbody>
                </table>
                <div class="pagination" id="pagination"></div>
            </div>
        </div>
    </div>
</div>

<script>
let selectedAnimals = new Set();
let currentPenAnimals = [];
let fpCheckupDate;
const incomingEventIds = "<?= htmlspecialchars($event_ids) ?>";
const USER_LOCATION = <?php echo json_encode($USER_LOCATION_); ?>;

document.addEventListener('DOMContentLoaded', () => {
    fpCheckupDate = flatpickr("#checkup_date", { enableTime: true, dateFormat: "Y-m-d H:i", altInput: true, altFormat: "m/d/Y h:i K" });
    
    flatpickr("#histFrom", { dateFormat: "Y-m-d", altInput: true, altFormat: "m/d/Y", onChange: function() { loadHistory(1); } });
    flatpickr("#histTo", { dateFormat: "Y-m-d", altInput: true, altFormat: "m/d/Y", onChange: function() { loadHistory(1); } });
    
    if (incomingEventIds) handleEventAutoSelect(incomingEventIds);
    else if (USER_LOCATION != 1000) handleLocationChange(USER_LOCATION);

    loadHistory(1); 
});

async function handleLocationChange(locId) { 
    clearTable(); 
    const bldg = document.getElementById('building_id');
    bldg.innerHTML = '<option>Loading…</option>';
    const res = await fetch(`../process/getBuildingsByLocation.php?location_id=${locId}`);
    const data = await res.json();
    bldg.innerHTML = '<option value="">Select Building</option>';
    (data.buildings || []).forEach(b => bldg.add(new Option(b.BUILDING_NAME, b.BUILDING_ID)));
    bldg.disabled = false;
}

async function loadPens(bldgId) {
    const pen = document.getElementById('pen_id');
    pen.innerHTML = '<option>Loading…</option>';
    const res = await fetch(`../process/getPensByBuilding.php?building_id=${bldgId}`);
    const data = await res.json();
    pen.innerHTML = '<option value="">Select Pen</option>';
    (data.pens || []).forEach(p => pen.add(new Option(p.PEN_NAME, p.PEN_ID)));
    pen.disabled = false;
}

async function loadAnimals(penId) {
    const grid = document.getElementById('animal-grid');
    grid.innerHTML = '<div style="grid-column:1/-1;text-align:center;">Loading…</div>';
    const res = await fetch(`../process/getAnimalsByPen.php?pen_id=${penId}`);
    const data = await res.json();
    grid.innerHTML = '';
    currentPenAnimals = (data.animal_record || []).filter(a => a.IS_ACTIVE == 1);
    document.getElementById('select-all-wrapper').style.display = currentPenAnimals.length ? 'flex' : 'none';
    currentPenAnimals.forEach(a => {
        const card = document.createElement('div');
        card.className = `animal-card ${selectedAnimals.has(String(a.ANIMAL_ID)) ? 'in-table' : ''}`;
        card.id = `card-${a.ANIMAL_ID}`;
        card.onclick = () => addAnimalToTable(a);
        card.innerHTML = `🐖<br><b>${a.TAG_NO}</b>`;
        grid.appendChild(card);
    });
}

function addAnimalToTable(animal) {
    if (selectedAnimals.has(String(animal.ANIMAL_ID))) return;
    const list = document.getElementById('checkup-list');
    const empty = document.getElementById('empty-row');
    if (empty) empty.remove();

    const defStatus = document.getElementById('default_status').value;

    const tr = document.createElement('tr');
    tr.id = `row-${animal.ANIMAL_ID}`;
    tr.dataset.id = animal.ANIMAL_ID;
    tr.innerHTML = `
        <td style="font-weight:bold;">${animal.TAG_NO}</td>
        <td>
            <select class="status-sel form-control">
                <option value="Healthy" ${defStatus==='Healthy'?'selected':''}>Healthy</option>
                <option value="Sick" ${defStatus==='Sick'?'selected':''}>Sick</option>
                <option value="Under Treatment" ${defStatus==='Under Treatment'?'selected':''}>Under Treatment</option>
            </select>
        </td>
        <td><textarea class="findings-inp form-control" rows="1" placeholder="Observations..."></textarea></td>
        <td><input type="number" class="weight-inp form-control" placeholder="kg"></td>
        <td><button onclick="removeAnimal(${animal.ANIMAL_ID})" style="color:var(--danger); border:none; background:none; cursor:pointer; font-size:1.2rem;">&times;</button></td>
    `;
    list.appendChild(tr);
    selectedAnimals.add(String(animal.ANIMAL_ID));
    document.getElementById(`card-${animal.ANIMAL_ID}`)?.classList.add('in-table');
    updateCount();
}

function removeAnimal(id) {
    document.getElementById(`row-${id}`)?.remove();
    selectedAnimals.delete(String(id));
    document.getElementById(`card-${id}`)?.classList.remove('in-table');
    
    if (selectedAnimals.size === 0) {
        document.getElementById('checkup-list').innerHTML = '<tr id="empty-row"><td colspan="5" style="text-align:center;padding:2rem;color:#64748b;">No animals added yet.</td></tr>';
    }
    updateCount();
}

function updateCount() {
    const c = selectedAnimals.size;
    const cost = parseFloat(document.getElementById('cost_per_head').value) || 0;
    document.getElementById('sum-count').textContent = c;
    document.getElementById('sum-cost').textContent = '₱ ' + (c * cost).toLocaleString(undefined, {minimumFractionDigits:2});
    document.getElementById('btn-submit').disabled = c === 0;
}

function clearTable() { selectedAnimals.forEach(id => removeAnimal(id)); }
function toggleSelectAll(cb) { if (cb.checked) currentPenAnimals.forEach(a => addAnimalToTable(a)); else clearTable(); }
function updateAllStatus() { const v = document.getElementById('default_status').value; document.querySelectorAll('.status-sel').forEach(s => s.value = v); }

async function loadHistory(page) {
    const list = document.getElementById('history-list');
    const pg = document.getElementById('pagination');
    list.innerHTML = '<tr><td colspan="6" style="text-align:center;">Loading history...</td></tr>';

    const search = document.getElementById('histSearch').value;
    const loc = document.getElementById('histLoc')?.value || '';
    const from = document.getElementById('histFrom').value;
    const to = document.getElementById('histTo').value;

    try {
        const query = `action=get_checkup_history&p=${page}&search=${search}&loc_filter=${loc}&date_from=${from}&date_to=${to}`;
        const res = await fetch(`group_checkup.php?${query}`);
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

        list.innerHTML = result.data.map(row => {
            let raw = row.REMARKS || '';
            let status = 'Unknown';
            let findings = raw;
            let match = raw.match(/\[(.*?)\]/);
            
            if (match) {
                status = match[1]; 
                findings = raw.replace(match[0], '').trim(); 
            }
            let cssClass = status.replace(/\s/g, '');

            return `
                <tr>
                    <td style="font-size:0.85rem; color:var(--muted);">${row.FORMATTED_DATE}</td>
                    <td><span style="color:var(--accent); font-weight:bold;">${row.TAG_NO}</span></td>
                    <td><span class="badge-status status-${cssClass}">${status}</span></td>
                    <td style="font-size:0.9rem;">${findings || '-'}</td>
                    <td>${row.VET_NAME}</td>
                    <td style="color:var(--warning); font-weight:bold;">₱ ${parseFloat(row.COST).toFixed(2)}</td>
                </tr>
            `;
        }).join('');

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
        console.error("LoadHistory Error:", e);
        list.innerHTML = '<tr><td colspan="6" style="text-align:center; color:var(--danger);">System Error: Check Console</td></tr>';
    }
}

async function submitBatch() {
    if (!confirm('Confirm batch record?')) return;
    const btn = document.getElementById('btn-submit');
    btn.disabled = true;

    const records = [];
    document.querySelectorAll('#checkup-list tr[id^="row-"]').forEach(tr => {
        let selStatus = tr.querySelector('.status-sel').value;
        let inpFindings = tr.querySelector('.findings-inp').value;

        records.push({
            animal_id: tr.dataset.id,
            status: selStatus,
            findings: inpFindings,
            weight: tr.querySelector('.weight-inp').value,
            remarks: `[${selStatus}] ${inpFindings}`.trim() 
        });
    });

    try {
        const res = await fetch('../process/addBatchCheckup.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                records,
                date: document.getElementById('checkup_date').value,
                examined_by: document.getElementById('examined_by').value,
                cost: parseFloat(document.getElementById('cost_per_head').value) || 0
            })
        });
        const data = await res.json();
        if (data.success) {
            alert('Checkups Recorded!');
            location.reload();
        } else {
            alert('Error: ' + data.message);
            btn.disabled = false;
        }
    } catch (e) {
        alert('Server connection error.');
        btn.disabled = false;
    }
}
</script>
</body>
</html>