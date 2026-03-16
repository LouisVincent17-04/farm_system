<?php
// views/group_vaccination.php
ob_start();
error_reporting(0);
ini_set('display_errors', 0);
include '../config/Connection.php';

include '../security/checkAccess.php';
checkAccess('group_vaccination');
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
        // Fetch specific vaccines for refresh
        if ($action === 'get_vaccines') {
            $locId = $_GET['location_id'] ?? '';
            if (!$locId) { echo json_encode([]); exit; }
            $stmt = $conn->prepare("
                SELECT SUPPLY_ID, SUPPLY_NAME, TOTAL_STOCK, UNIT_ID,
                       (SELECT UNIT_ABBR FROM UNITS WHERE UNITS.UNIT_ID = VACCINES.UNIT_ID LIMIT 1) as UNIT_ABBR
                FROM VACCINES 
                WHERE LOCATION_ID = ? AND IS_ACTIVE = 1 
                ORDER BY SUPPLY_NAME ASC
            ");
            $stmt->execute([$locId]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            exit;
        }

        // --- FETCH VACCINATION HISTORY ---
        if ($action === 'get_vaccination_history') {
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
                $where[] = "(a.TAG_NO LIKE ? OR vr.ADMINISTERED_BY LIKE ? OR v.SUPPLY_NAME LIKE ?)";
                $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
            }
            if ($date_from) { $where[] = "DATE(vr.VACCINATION_DATE) >= ?"; $params[] = $date_from; }
            if ($date_to)   { $where[] = "DATE(vr.VACCINATION_DATE) <= ?"; $params[] = $date_to; }

            $where_sql = implode(" AND ", $where);

            $count_stmt = $conn->prepare("
                SELECT COUNT(*)
                FROM vaccination_records vr
                JOIN animal_records a ON vr.ANIMAL_ID = a.ANIMAL_ID
                JOIN locations l      ON a.LOCATION_ID = l.LOCATION_ID
                LEFT JOIN VACCINES v  ON vr.VACCINE_ID = v.SUPPLY_ID
                WHERE $where_sql
            ");
            $count_stmt->execute($params);
            $total_records = $count_stmt->fetchColumn();

            $sql = "
                SELECT vr.*,
                       DATE_FORMAT(vr.VACCINATION_DATE, '%m/%d/%Y %h:%i %p') AS FORMATTED_DATE,
                       a.TAG_NO,
                       l.LOCATION_NAME,
                       v.SUPPLY_NAME
                FROM vaccination_records vr
                JOIN animal_records a ON vr.ANIMAL_ID = a.ANIMAL_ID
                JOIN locations l      ON a.LOCATION_ID = l.LOCATION_ID
                LEFT JOIN VACCINES v  ON vr.VACCINE_ID = v.SUPPLY_ID
                WHERE $where_sql
                ORDER BY vr.VACCINATION_DATE DESC, vr.VACCINATION_ID DESC
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

    } catch (Exception $e) { echo json_encode(['error' => $e->getMessage()]); }
    exit;
}

// =========================================================
// NORMAL PAGE LOAD
// =========================================================
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
        SELECT FULL_NAME COLLATE utf8mb4_general_ci AS FULL_NAME, POSITION
        FROM employees WHERE STATUS = 'Active'
        UNION
        SELECT FULL_NAME COLLATE utf8mb4_general_ci AS FULL_NAME, 'Veterinarian' AS POSITION
        FROM VETERINARIANS WHERE IS_ACTIVE = 1
        ORDER BY FULL_NAME ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) { $locs = []; $personnel = []; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Group Vaccination | FarmPro</title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" type="text/css" href="https://npmcdn.com/flatpickr/dist/themes/dark.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    <style>
        :root {
            --accent: #8b5cf6; --accent-dark: #6d28d9;
            --bg: #0f172a; --card: #1e293b; --border: #334155;
            --text: #e2e8f0; --muted: #94a3b8; --success: #22c55e; --danger: #ef4444; --warning: #facc15;
        }
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif; background:linear-gradient(135deg,var(--bg) 0%,#1e293b 100%); min-height:100vh; color:var(--text); }
        .container { max-width:1600px; margin:0 auto; padding:1.5rem; }

        .back-link { display:inline-flex; align-items:center; gap:8px; text-decoration:none; color:var(--muted); font-weight:600; font-size:.95rem; margin-bottom:1.5rem; transition:color .2s; }
        .back-link:hover { color:white; }

        /* ── Sync Banner ── */
        #sync-alert {
            padding:1rem 1.5rem; border-radius:12px; margin-bottom:1.5rem;
            font-weight:600; font-size:.95rem; display:none;
            align-items:center; justify-content:center; gap:10px;
        }
        #sync-alert.loading { display:flex; background:rgba(139,92,246,.15); border:1px solid var(--accent); color:#c4b5fd; }
        #sync-alert.success { display:flex; background:rgba(34,197,94,.1);  border:1px solid var(--success); color:#4ade80; }
        #sync-alert.error   { display:flex; background:rgba(239,68,68,.1);  border:1px solid var(--danger);  color:#f87171; }

        .spinner { width:18px; height:18px; border:2px solid rgba(196,181,253,.3); border-top-color:#c4b5fd; border-radius:50%; animation:spin .6s linear infinite; flex-shrink:0; }
        @keyframes spin { to { transform:rotate(360deg); } }

        /* ── Lock banner ── */
        #lock-banner {
            display:none; background:rgba(139,92,246,.12); border:1px solid rgba(139,92,246,.35);
            border-radius:12px; padding:.9rem 1.25rem; margin-bottom:1.25rem;
            color:#c4b5fd; font-size:.9rem; gap:10px; align-items:center;
        }
        #lock-banner.show { display:flex; }

        /* ── Context card ── */
        #context-card {
            display:none; margin-top:1rem; background:rgba(0,0,0,.3);
            border:1px solid #334155; border-radius:10px; padding:.9rem 1.25rem; font-size:.85rem;
        }
        #context-card.show { display:block; }
        .ctx-row { display:flex; justify-content:space-between; padding:5px 0; color:var(--muted); border-bottom:1px solid rgba(255,255,255,.05); }
        .ctx-row:last-child { border-bottom:none; }
        .ctx-row strong { color:#e2e8f0; max-width:60%; text-align:right; }

        /* ── Layout ── */
        .main-grid { display:grid; grid-template-columns:380px 1fr; gap:1.5rem; align-items:start; }

        .control-panel {
            background:rgba(30,41,59,.7); backdrop-filter:blur(12px);
            border:1px solid rgba(148,163,184,.2); border-radius:16px; padding:1.5rem;
            position:sticky; top:1.5rem; box-shadow:0 10px 25px -5px rgba(0,0,0,.3);
        }
        .panel-title    { font-size:1.25rem; font-weight:700; color:#fff; margin-bottom:5px; display:flex; align-items:center; gap:8px; }
        .panel-subtitle { font-size:.85rem; color:var(--muted); margin-bottom:1.5rem; }

        .form-group { margin-bottom:1rem; }
        .form-label { display:block; font-size:.85rem; color:#cbd5e1; margin-bottom:.4rem; font-weight:500; }

        /* ── Select wrapper with lock icon ── */
        .select-wrap { position:relative; }
        .select-wrap .form-control { padding-right: 2.2rem; }
        .select-wrap .lock-badge {
            display:none; position:absolute; right:10px; top:50%; transform:translateY(-50%);
            font-size:.8rem; pointer-events:none; z-index:2;
        }
        .select-wrap.locked .lock-badge { display:inline; }
        .select-wrap.locked .form-control {
            border-color:#4c1d95; background:#0d0b1e;
            opacity:.85; cursor:not-allowed;
        }

        .form-control {
            width:100%; padding:.75rem; background:var(--bg); border:1px solid var(--border);
            border-radius:8px; color:#fff; font-size:.95rem; transition:border-color .2s;
            appearance:auto; outline:none;
        }
        .form-control:focus   { border-color:var(--accent); box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1); }
        .form-control:disabled { opacity:.55; cursor:not-allowed; }

        .stock-ok  { color:#4ade80; font-size:.85rem; margin-top:5px; display:block; }
        .stock-low { color:#f87171; font-size:.85rem; margin-top:5px; display:block; }

        .summary-box { margin-top:1.5rem; background:var(--bg); padding:1rem; border-radius:12px; border-left:4px solid var(--accent); }
        .summary-row { display:flex; justify-content:space-between; margin-bottom:5px; font-size:.9rem; color:var(--muted); }
        .summary-total { margin-top:10px; padding-top:10px; border-top:1px solid var(--border); font-weight:700; color:#fff; display:flex; justify-content:space-between; }

        .resource-link { display: inline-flex; align-items: center; gap: 5px; font-size: 0.85rem; color: #a78bfa; text-decoration: none; transition: color 0.2s; font-weight: 600; }
        .resource-link:hover { color: #c4b5fd; text-decoration: underline; }

        .btn-submit {
            width:100%; margin-top:1.5rem; padding:1rem;
            background:linear-gradient(135deg,var(--accent),var(--accent-dark));
            border:none; border-radius:12px; color:white; font-weight:700; font-size:1rem;
            cursor:pointer; transition:all .2s;
        }
        .btn-submit:disabled { opacity:.5; cursor:not-allowed; filter:grayscale(1); }
        .btn-submit:hover:not(:disabled) { transform:translateY(-2px); box-shadow:0 4px 12px rgba(139,92,246,.4); }

        .workspace-panel { display:flex; flex-direction:column; gap:1.5rem; }
        .picker-section { background:rgba(30,41,59,.4); border:1px solid rgba(255,255,255,.05); border-radius:16px; padding:1.5rem; }
        .section-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; }
        .section-title  { font-size:1.1rem; font-weight:600; color:#fff; }

        .select-all-container {
            display:flex; align-items:center; gap:8px; font-size:.9rem; color:#a78bfa;
            cursor:pointer; padding:5px 10px; border-radius:6px;
            background:rgba(139,92,246,.1); border:1px solid rgba(139,92,246,.2);
        }
        .select-all-container input { cursor:pointer; accent-color:var(--accent); width:16px; height:16px; }

        .animal-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(110px,1fr)); gap:.75rem; max-height:250px; overflow-y:auto; padding-right:5px; }
        .animal-card { background:var(--card); border:1px solid var(--border); border-radius:8px; padding:.75rem; cursor:pointer; text-align:center; transition:all .2s; }
        .animal-card:hover  { border-color:var(--muted); transform:translateY(-2px); }
        .animal-card.in-table { opacity:.45; pointer-events:none; border-color:#4ade80; }

        .table-section { background:var(--card); border:1px solid var(--border); border-radius:16px; overflow:hidden; }
        .custom-table  { width:100%; border-collapse:collapse; }
        .custom-table th { background:var(--bg); color:var(--muted); font-size:.8rem; text-transform:uppercase; padding:1rem; text-align:left; font-weight:600; border-bottom:1px solid var(--border); }
        .custom-table td { padding:.75rem 1rem; border-bottom:1px solid rgba(255,255,255,.05); vertical-align:middle; color:var(--text); font-size:.95rem; }
        .custom-table input { background:var(--bg); border:1px solid #475569; color:#fff; padding:6px 10px; border-radius:6px; width:100%; outline:none; }
        .custom-table input:focus { border-color:var(--accent); box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1); }
        .btn-remove { background:transparent; border:none; color:#f87171; cursor:pointer; font-size:1.1rem; padding:5px; transition:color .2s; }
        .btn-remove:hover { color:var(--danger); transform:scale(1.1); }

        /* History Filter Styling with Width Fix */
        .history-filters { display:flex; gap:12px; padding:1rem; background:rgba(15,23,42,0.3); border-bottom:1px solid var(--border); flex-wrap:wrap; align-items:center;}
        .filter-input { width: 180px !important; padding:8px 12px; background:var(--bg); border:1px solid var(--border); border-radius:6px; color:#fff; font-size:0.9rem; outline:none; }
        .filter-input:focus { border-color:var(--accent); }

        .pagination { display:flex; justify-content:center; gap:8px; padding:1.5rem; background:rgba(15,23,42,0.2); flex-wrap:wrap; }
        .pg-btn { background:var(--border); border:none; color:#fff; padding:6px 12px; border-radius:6px; cursor:pointer; font-size:0.9rem; transition:background .2s; }
        .pg-btn.active { background:var(--accent); }
        .pg-btn:disabled { opacity:0.3; cursor:not-allowed; }
        .pg-btn:hover:not(.active):not(:disabled) { background:#475569; }

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
        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
        </svg>
        <?= $event_ids ? 'Back to Event Scheduler' : 'Back to Transactions' ?>
    </a>

    <div id="sync-alert">
        <span class="spinner" id="sync-spinner"></span>
        <span id="sync-msg">Initializing…</span>
    </div>

    <div id="lock-banner">
        🔒 <strong style="margin-right:4px;">Scheduler Mode:</strong>
        Location, building, pen, and vaccine are pre-loaded from the event schedule.
        Review dosages then click <em>Record Vaccination</em>.
    </div>

    <div class="main-grid">

        <div class="control-panel">
            <div class="panel-title">💉 Group Vaccination</div>
            <div class="panel-subtitle">Configure vaccine batch and default dosage.</div>

            <form id="settingsForm">

                <div style="background:rgba(255,255,255,.03);padding:12px;border-radius:8px;margin-bottom:1rem;border:1px dashed #475569;">
                    <label class="form-label" style="color:#a78bfa;margin-bottom:8px;display:block;">
                        STEP 1: Locate Group
                    </label>

                    <div class="form-group" style="margin-bottom:.5rem;">
                        <div class="select-wrap" id="wrap-location">
                            <select id="location_id" class="form-control" onchange="handleLocationChange(this.value)" <?php echo ($USER_LOCATION_ != 1000) ? 'style="background-color: #0d0b1e; pointer-events: none; color: #94a3b8;"' : ''; ?>>
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
                            <select id="building_id" class="form-control" onchange="handleBuildingChange(this.value)" disabled>
                                <option value="">Select Building</option>
                            </select>
                            <span class="lock-badge">🔒</span>
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom:0;">
                        <div class="select-wrap" id="wrap-pen">
                            <select id="pen_id" class="form-control" onchange="loadAnimals(this.value)" disabled>
                                <option value="">Select Pen</option>
                            </select>
                            <span class="lock-badge">🔒</span>
                        </div>
                    </div>

                    <div id="context-card">
                        <div class="ctx-row"><span>📍 Location</span> <strong id="ctx-loc">—</strong></div>
                        <div class="ctx-row"><span>🏠 Building</span> <strong id="ctx-bldg">—</strong></div>
                        <div class="ctx-row"><span>🐷 Pen</span>      <strong id="ctx-pen">—</strong></div>
                        <div class="ctx-row"><span>💉 Vaccine</span>  <strong id="ctx-vax">—</strong></div>
                    </div>
                </div>

                <label class="form-label" style="color:#a78bfa;">STEP 2: Batch Details</label>

                <div class="form-group">
                    <label class="form-label">Vaccine <span style="color:#f87171">*</span></label>
                    <div class="select-wrap" id="wrap-vaccine">
                        <select id="vaccine_id" class="form-control" onchange="updateCalculations()" disabled required>
                            <option value="" data-stock="0">Select Location First</option>
                        </select>
                        <span class="lock-badge">🔒</span>
                    </div>
                    
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 8px;">
                        <a href="purch_vaccines.php" target="_blank" class="resource-link" title="Opens in a new tab">
                            Manage / Purchase Vaccines ↗
                        </a>
                        <button type="button" id="refresh-vax-btn" class="btn-mini" onclick="refreshVaccineList()" style="background: rgba(139, 92, 246, 0.2); color: #c4b5fd; border: 1px solid rgba(139, 92, 246, 0.3);">
                            ↻ Refresh
                        </button>
                    </div>
                    <div id="stock-display" style="margin-top: 5px;"></div>
                </div>

                <div class="form-group">
                    <label class="form-label">Quantity / Head <span style="color:#f87171">*</span></label>
                    <div style="display:flex;gap:5px;">
                        <input type="number" id="default_dosage" class="form-control" step="0.01" value="1.00" oninput="updateAllDosages()" placeholder="Qty">
                        <button type="button" onclick="updateAllDosages()" style="background:#334155;border:1px solid #475569;color:#fff;border-radius:8px;padding:0 10px;cursor:pointer;" title="Apply to all rows">All</button>
                    </div>
                    <small style="color:#64748b;font-size:.75rem;">Can be overridden per-row below.</small>
                </div>

                <div class="form-group">
                    <label class="form-label">Default Remarks</label>
                    <div style="display:flex;gap:5px;">
                        <input type="text" id="default_remarks" class="form-control" placeholder="e.g. Routine Booster">
                        <button type="button" onclick="updateAllRemarks()" style="background:#334155;border:1px solid #475569;color:#fff;border-radius:8px;padding:0 10px;cursor:pointer;">All</button>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Veterinarian / Personnel</label>
                    <select id="administered_by" class="form-control">
                        <option value="">— Select Person —</option>
                        <?php foreach($personnel as $p): ?>
                            <option value="<?= htmlspecialchars($p['FULL_NAME']) ?>">
                                <?= htmlspecialchars($p['FULL_NAME']) ?>
                                (<?= htmlspecialchars($p['POSITION']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Date Administered</label>
                    <input type="text" id="vaccination_date" class="form-control" placeholder="Select Date & Time">
                </div>

                <div class="summary-box">
                    <div class="summary-row">
                        <span>Animals Selected:</span>
                        <span id="sum-count" style="color:#fff">0</span>
                    </div>
                    <div class="summary-total">
                        <span>Total Vol Required:</span>
                        <span id="sum-total" style="color:#a78bfa">0 units</span>
                    </div>
                </div>

                <button type="button" class="btn-submit" id="btn-submit" onclick="submitBatch()" disabled>
                    Record Vaccination
                </button>
            </form>
        </div>

        <div class="workspace-panel">

            <div class="picker-section" id="pickerSection">
                <div class="section-header">
                    <div class="section-title">🐷 Step 3: Click to Add Animals</div>
                    <label class="select-all-container" style="display:none;" id="select-all-wrapper">
                        <input type="checkbox" id="select-all-check" onchange="toggleSelectAll(this)"> Select All
                    </label>
                </div>
                <div id="animal-grid" class="animal-grid">
                    <div style="grid-column:1/-1;text-align:center;padding:2rem;color:#64748b;border:1px dashed #475569;border-radius:8px;">
                        Select a Pen from the left to load animals.
                    </div>
                </div>
            </div>

            <div class="table-section">
                <div class="section-header" style="padding:1rem;border-bottom:1px solid var(--border);margin-bottom:0;">
                    <div class="section-title">📋 Step 4: Confirm Dosages</div>
                    <button onclick="clearTable()" style="background:transparent;border:1px solid #f87171;color:#f87171;padding:4px 10px;border-radius:4px;cursor:pointer;font-size:.8rem;">Clear All</button>
                </div>
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th style="width:15%;">Tag No</th>
                            <th style="width:25%;">Dosage (Qty)</th>
                            <th>Remarks (Optional)</th>
                            <th style="width:50px;"></th>
                        </tr>
                    </thead>
                    <tbody id="vaccination-list">
                        <tr id="empty-row"><td colspan="4" style="text-align:center;padding:2rem;color:#64748b;">No animals added yet.</td></tr>
                    </tbody>
                </table>
            </div>

            <div class="table-section">
                <div class="section-header" style="padding:1rem;border-bottom:1px solid var(--border);margin-bottom:0;">
                    <div class="section-title">🕒 Recent Vaccination Logs</div>
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
                            <th>Vaccine</th>
                            <th>Dosage</th>
                            <th>Remarks</th>
                            <th>Cost</th>
                        </tr>
                    </thead>
                    <tbody id="history-list">
                        <tr><td colspan="7" style="text-align:center;padding:2rem;color:#64748b;">Loading...</td></tr>
                    </tbody>
                </table>
                <div class="pagination" id="pagination"></div>
            </div>

        </div>
    </div>
</div>

<script>
/* ═══════════════════════════════════════════════════════════════
   STATE
═══════════════════════════════════════════════════════════════ */
let selectedAnimals   = new Set();
let currentPenAnimals = [];
let schedulerMode     = false;
let fpVaccineDate; 

const incomingEventIds = "<?= htmlspecialchars($event_ids) ?>".trim();
const USER_LOCATION = <?php echo json_encode($USER_LOCATION_); ?>;

/* ═══════════════════════════════════════════════════════════════
   INIT
═══════════════════════════════════════════════════════════════ */
document.addEventListener('DOMContentLoaded', () => {
    fpVaccineDate = flatpickr("#vaccination_date", {
        enableTime: true,
        dateFormat: "Y-m-d H:i", 
        altInput: true,
        altFormat: "m/d/Y h:i K", 
        allowInput: true
    });
    fpVaccineDate.clear();

    flatpickr("#histFrom", { dateFormat:"Y-m-d", altInput:true, altFormat:"m/d/Y", onChange: () => loadHistory(1) });
    flatpickr("#histTo",   { dateFormat:"Y-m-d", altInput:true, altFormat:"m/d/Y", onChange: () => loadHistory(1) });

    if (incomingEventIds) {
        schedulerMode = true;
        handleEventAutoSelect(incomingEventIds);
    } else if (USER_LOCATION != 1000) {
        const locDrop = document.getElementById('location_id');
        locDrop.value = USER_LOCATION;
        handleLocationChange(USER_LOCATION);
        lockField('wrap-location', 'location_id');
    }

    loadHistory(1);
});

/* ═══════════════════════════════════════════════════════════════
   BANNER HELPERS
═══════════════════════════════════════════════════════════════ */
function showBanner(type, msg) {
    const el      = document.getElementById('sync-alert');
    const spinner = document.getElementById('sync-spinner');
    el.className  = type;
    spinner.style.display = (type === 'loading') ? 'inline-block' : 'none';
    document.getElementById('sync-msg').textContent = msg;
}
function hideBanner() {
    const el = document.getElementById('sync-alert');
    el.className = '';
    el.style.display = 'none';
}

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

/* ═══════════════════════════════════════════════════════════════
   AUTO-SELECT  ── Scheduler / Blocker entry point
═══════════════════════════════════════════════════════════════ */
async function handleEventAutoSelect(eventIds) {
    showBanner('loading', 'Loading scheduled animals and vaccine…');
    try {
        const res  = await fetch(`../process/eventManager.php?action=get_events_details&ids=${eventIds}`);
        const data = await res.json();

        if (!data.success || !data.events || data.events.length === 0) {
            showBanner('error', '❌ No event details returned. Check eventManager.php?action=get_events_details');
            return;
        }

        const ev       = data.events[0];
        const locId    = String(ev.LOCATION_ID);
        const bldgId   = String(ev.BUILDING_ID);
        const penId    = String(ev.PEN_ID);
        const itemId   = String(ev.ITEM_ID);

        if (!setSelectValue('location_id', locId)) {
            showBanner('error', `❌ Location ID "${locId}" not in dropdown.`); return;
        }
        lockField('wrap-location', 'location_id');

        showBanner('loading', 'Loading buildings…');
        await fetchBuildings(locId);
        if (!setSelectValue('building_id', bldgId)) {
            showBanner('error', `❌ Building ID "${bldgId}" not found.`); return;
        }
        lockField('wrap-building', 'building_id');

        showBanner('loading', 'Loading pens…');
        await fetchPens(bldgId);
        if (!setSelectValue('pen_id', penId)) {
            showBanner('error', `❌ Pen ID "${penId}" not found.`); return;
        }
        lockField('wrap-pen', 'pen_id');

        showBanner('loading', 'Loading vaccines…');
        await fetchVaccines(locId);
        if (!setSelectValue('vaccine_id', itemId)) {
            showBanner('error', `❌ Vaccine ID "${itemId}" not found in this location.`); return;
        }
        lockField('wrap-vaccine', 'vaccine_id');
        updateCalculations();

        data.events.forEach(e => {
            if (!selectedAnimals.has(String(e.ANIMAL_ID))) {
                addAnimalToTable({ ANIMAL_ID: e.ANIMAL_ID, TAG_NO: e.TAG_NO });
            }
        });

        document.getElementById('ctx-loc').textContent  = ev.LOCATION_NAME || locId;
        document.getElementById('ctx-bldg').textContent = ev.BUILDING_NAME || bldgId;
        document.getElementById('ctx-pen').textContent  = ev.PEN_NAME      || penId;
        document.getElementById('ctx-vax').textContent  = ev.ITEM_NAME     || itemId;
        document.getElementById('context-card').classList.add('show');

        document.getElementById('pickerSection').style.display = 'none';
        document.getElementById('lock-banner').classList.add('show');

        showBanner('success', `✅ ${data.events.length} animal(s) loaded from schedule — review dosages and save.`);
        setTimeout(hideBanner, 5000);
        document.querySelector('.table-section').scrollIntoView({ behavior: 'smooth', block: 'start' });

    } catch (err) {
        showBanner('error', `❌ Auto-sync failed.`);
    }
}

/* ═══════════════════════════════════════════════════════════════
   LIVE REFRESH (NEW)
═══════════════════════════════════════════════════════════════ */
async function refreshVaccineList() {
    const locId = document.getElementById('location_id').value;
    if (!locId) { alert("Please select a Location first."); return; }

    const btn = document.getElementById('refresh-vax-btn');
    btn.innerHTML = '↻ Loading...';
    btn.disabled = true;

    try {
        const currentSelection = document.getElementById('vaccine_id').value;
        await fetchVaccines(locId);
        if (currentSelection) setSelectValue('vaccine_id', currentSelection);
        updateCalculations(); 
        
        btn.innerHTML = '↻ Refresh';
        btn.disabled = false;
    } catch (e) {
        btn.innerHTML = '❌ Error';
        setTimeout(() => { btn.innerHTML = '↻ Refresh'; btn.disabled = false; }, 2000);
    }
}

/* ═══════════════════════════════════════════════════════════════
   FETCH HELPERS 
═══════════════════════════════════════════════════════════════ */
function fetchBuildings(locId) {
    return new Promise((resolve, reject) => {
        const sel = document.getElementById('building_id');
        sel.innerHTML = '<option value="">Loading buildings…</option>';
        sel.disabled  = true;
        document.getElementById('pen_id').innerHTML = '<option value="">Select Pen</option>';
        document.getElementById('pen_id').disabled  = true;

        if (!locId) { sel.innerHTML = '<option value="">Select Building</option>'; resolve([]); return; }

        fetch(`../process/getBuildingsByLocation.php?location_id=${locId}`)
            .then(r => r.json())
            .then(data => {
                sel.innerHTML = '<option value="">Select Building</option>';
                const list = data.buildings || data || [];
                list.forEach(b => sel.add(new Option(b.BUILDING_NAME, b.BUILDING_ID)));
                sel.disabled = false;
                resolve(list);
            })
            .catch(err => { sel.innerHTML = '<option value="">Error</option>'; reject(err); });
    });
}

function fetchPens(bldgId) {
    return new Promise((resolve, reject) => {
        const sel = document.getElementById('pen_id');
        sel.innerHTML = '<option value="">Loading pens…</option>';
        sel.disabled  = true;

        if (!bldgId) { sel.innerHTML = '<option value="">Select Pen</option>'; resolve([]); return; }

        fetch(`../process/getPensByBuilding.php?building_id=${bldgId}`)
            .then(r => r.json())
            .then(data => {
                sel.innerHTML = '<option value="">Select Pen</option>';
                const list = data.pens || data || [];
                list.forEach(p => sel.add(new Option(p.PEN_NAME, p.PEN_ID)));
                sel.disabled = false;
                resolve(list);
            })
            .catch(err => { sel.innerHTML = '<option value="">Error</option>'; reject(err); });
    });
}

function fetchVaccines(locId) {
    return new Promise((resolve, reject) => {
        const sel = document.getElementById('vaccine_id');
        sel.innerHTML = '<option value="" data-stock="0">Loading vaccines…</option>';
        sel.disabled  = true;
        document.getElementById('stock-display').innerHTML = '';

        if (!locId) { sel.innerHTML = '<option value="" data-stock="0">Select Location First</option>'; resolve([]); return; }

        // Use the internal handler
        fetch(`?action=get_vaccines&location_id=${locId}`)
            .then(r => r.json())
            .then(data => {
                sel.innerHTML = '<option value="" data-stock="0">Select Vaccine</option>';
                data.forEach(v => {
                    const opt          = new Option(`${v.SUPPLY_NAME} (Stock: ${v.TOTAL_STOCK} ${v.UNIT_ABBR})`, v.SUPPLY_ID);
                    opt.dataset.stock  = v.TOTAL_STOCK;
                    opt.dataset.unit   = v.UNIT_ABBR;
                    opt.dataset.unitId = v.UNIT_ID;
                    sel.appendChild(opt);
                });
                sel.disabled = false;
                resolve(data);
            })
            .catch(err => { sel.innerHTML = '<option value="">Error</option>'; reject(err); });
    });
}

/* ═══════════════════════════════════════════════════════════════
   MANUAL CASCADE 
═══════════════════════════════════════════════════════════════ */
function handleLocationChange(locId) {
    Array.from(selectedAnimals).forEach(id => removeAnimal(id));
    document.getElementById('building_id').innerHTML = '<option value="">Select Building</option>';
    document.getElementById('building_id').disabled  = true;
    document.getElementById('pen_id').innerHTML      = '<option value="">Select Pen</option>';
    document.getElementById('pen_id').disabled       = true;
    document.getElementById('vaccine_id').innerHTML  = '<option value="" data-stock="0">Select Location First</option>';
    document.getElementById('vaccine_id').disabled   = true;
    document.getElementById('stock-display').innerHTML = '';

    if (!locId) return;
    fetchBuildings(locId);
    fetchVaccines(locId);
}

function handleBuildingChange(bldgId) {
    document.getElementById('pen_id').innerHTML = '<option value="">Select Pen</option>';
    document.getElementById('pen_id').disabled  = true;
    if (!bldgId) return;
    fetchPens(bldgId);
}

function loadAnimals(penId) {
    const grid    = document.getElementById('animal-grid');
    const wrapper = document.getElementById('select-all-wrapper');
    grid.innerHTML        = '<div style="grid-column:1/-1;text-align:center;color:#94a3b8;">Loading…</div>';
    wrapper.style.display = 'none';
    if (!penId) return;

    fetch(`../process/getAnimalsByPen.php?pen_id=${penId}`)
        .then(r => r.json())
        .then(data => {
            grid.innerHTML    = '';
            currentPenAnimals = (data.animal_record || []).filter(a => a.IS_ACTIVE == 1);

            if (!currentPenAnimals.length) {
                grid.innerHTML = '<div style="grid-column:1/-1;text-align:center;color:#94a3b8;">No animals in this pen.</div>';
                return;
            }
            wrapper.style.display = 'flex';
            updateSelectAllState();

            currentPenAnimals.forEach(a => {
                const card     = document.createElement('div');
                card.className = `animal-card ${selectedAnimals.has(String(a.ANIMAL_ID)) ? 'in-table' : ''}`;
                card.id        = `card-${a.ANIMAL_ID}`;
                card.onclick   = () => addAnimalToTable(a);
                card.innerHTML = `<div style="font-size:1.5rem;">🐖</div>
                                  <div style="font-weight:700;color:#fff;">${a.TAG_NO}</div>`;
                grid.appendChild(card);
            });
        });
}

function toggleSelectAll(cb) {
    if (cb.checked) currentPenAnimals.forEach(a => { if (!selectedAnimals.has(String(a.ANIMAL_ID))) addAnimalToTable(a); });
    else currentPenAnimals.forEach(a => removeAnimal(a.ANIMAL_ID));
}
function updateSelectAllState() {
    const cb = document.getElementById('select-all-check');
    if (!cb || !currentPenAnimals.length) return;
    cb.checked = currentPenAnimals.every(a => selectedAnimals.has(String(a.ANIMAL_ID)));
}

/* ═══════════════════════════════════════════════════════════════
   TABLE OPS
═══════════════════════════════════════════════════════════════ */
function addAnimalToTable(animal) {
    if (selectedAnimals.has(String(animal.ANIMAL_ID))) return;

    const emptyRow = document.getElementById('empty-row');
    if (emptyRow) emptyRow.remove();

    const defDose = document.getElementById('default_dosage').value;
    const defRem  = document.getElementById('default_remarks').value;

    const tr      = document.createElement('tr');
    tr.id         = `row-${animal.ANIMAL_ID}`;
    tr.dataset.id = String(animal.ANIMAL_ID);
    tr.innerHTML  = `
        <td data-label="Tag No" style="font-weight:600;color:#fff;">${animal.TAG_NO}</td>
        <td data-label="Dosage"><input type="number" class="dosage-input" value="${defDose}"
                   step="0.01" min="0.01" oninput="updateCalculations()"></td>
        <td data-label="Remarks"><input type="text" class="rem-input" value="${defRem}" placeholder="Notes…"></td>
        <td style="text-align:right;">
            <button type="button" class="btn-remove" onclick="removeAnimal(${animal.ANIMAL_ID})">×</button>
        </td>`;
    document.getElementById('vaccination-list').appendChild(tr);

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
        document.getElementById('vaccination-list').innerHTML =
            '<tr id="empty-row"><td colspan="4" style="text-align:center;padding:2rem;color:#64748b;">No animals added yet.</td></tr>';
    }
    updateCalculations();
    updateSelectAllState();
}

function clearTable() {
    if (!selectedAnimals.size) return;
    if (!confirm('Clear all rows?')) return;
    Array.from(selectedAnimals).forEach(id => removeAnimal(id));
}

function updateAllDosages() {
    const v = document.getElementById('default_dosage').value;
    document.querySelectorAll('.dosage-input').forEach(i => i.value = v);
    updateCalculations();
}
function updateAllRemarks() {
    const v = document.getElementById('default_remarks').value;
    document.querySelectorAll('.rem-input').forEach(i => i.value = v);
}

/* ═══════════════════════════════════════════════════════════════
   CALCULATIONS
═══════════════════════════════════════════════════════════════ */
function updateCalculations() {
    const sel = document.getElementById('vaccine_id');
    const btn = document.getElementById('btn-submit');

    if (!sel.value) {
        document.getElementById('stock-display').innerHTML = '';
        btn.disabled = true;
        return;
    }

    const opt   = sel.options[sel.selectedIndex];
    const stock = parseFloat(opt.dataset.stock) || 0;
    const unit  = opt.dataset.unit || 'units';

    let total = 0;
    document.querySelectorAll('.dosage-input').forEach(i => total += parseFloat(i.value) || 0);

    document.getElementById('sum-count').textContent = selectedAnimals.size;
    document.getElementById('sum-total').textContent = `${total.toFixed(2)} ${unit}`;

    const display = document.getElementById('stock-display');
    if (total > stock) {
        display.innerHTML = `<span class="stock-low">⚠ Stock Low: ${stock} available, need ${total.toFixed(2)}</span>`;
        btn.disabled      = true;
        btn.textContent   = 'Insufficient Stock';
    } else {
        display.innerHTML = `<span class="stock-ok">✓ Stock OK: ${stock} ${unit} available</span>`;
        if (selectedAnimals.size > 0 && total > 0) {
            btn.disabled    = false;
            btn.textContent = 'Record Vaccination';
        } else {
            btn.disabled    = true;
            btn.textContent = 'Add Animals / Set Dosage';
        }
    }
}

/* ═══════════════════════════════════════════════════════════════
   HISTORY
═══════════════════════════════════════════════════════════════ */
async function loadHistory(page) {
    const list = document.getElementById('history-list');
    const pg   = document.getElementById('pagination');
    list.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:1rem;color:#64748b;">Loading history...</td></tr>';

    const search = document.getElementById('histSearch').value;
    const loc    = document.getElementById('histLoc')?.value || '';
    const from   = document.getElementById('histFrom').value;
    const to     = document.getElementById('histTo').value;

    try {
        const res = await fetch(`?action=get_vaccination_history&p=${page}&search=${encodeURIComponent(search)}&loc_filter=${loc}&date_from=${from}&date_to=${to}`);
        const result = await res.json();

        if (!result.success || !result.data) {
            list.innerHTML = `<tr><td colspan="7" style="text-align:center;color:var(--danger);">Error: ${result.error || 'Unknown error'}</td></tr>`;
            if (pg) pg.innerHTML = '';
            return;
        }

        if (!result.data.length) {
            list.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:1.5rem;color:#64748b;">No records found.</td></tr>';
            if (pg) pg.innerHTML = '';
            return;
        }

        list.innerHTML = result.data.map(row => `
            <tr>
                <td style="font-size:.85rem;color:var(--muted);white-space:nowrap;">${row.FORMATTED_DATE}</td>
                <td><span style="color:var(--accent);font-weight:bold;">${row.TAG_NO}</span></td>
                <td style="font-size:.9rem;">${row.ADMINISTERED_BY || '—'}</td>
                <td><span style="color:#a78bfa;font-weight:bold;">${row.SUPPLY_NAME || '—'}</span></td>
                <td style="font-size:.9rem;text-align:center;">${row.DOSAGE_ML ?? '—'}</td>
                <td style="font-size:.9rem;">${row.REMARKS || '—'}</td>
                <td style="color:var(--warning);font-weight:bold;white-space:nowrap;">₱ ${parseFloat(row.TOTAL_COST || row.VACCINATION_COST || 0).toFixed(2)}</td>
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
        list.innerHTML = '<tr><td colspan="7" style="text-align:center;color:var(--danger);">System Error. Check console.</td></tr>';
    }
}

/* ═══════════════════════════════════════════════════════════════
   SUBMISSION
═══════════════════════════════════════════════════════════════ */
async function submitBatch() {
    if (!confirm(`Proceed with vaccination for ${selectedAnimals.size} animal(s)? Inventory will be deducted.`)) return;

    const dateInput = document.getElementById('vaccination_date').value;
    if(!dateInput) {
        alert("Please select a valid Date Administered.");
        return;
    }

    const btn = document.getElementById('btn-submit');
    btn.disabled    = true;
    btn.textContent = 'Processing…';

    const vacOpt  = document.getElementById('vaccine_id').selectedOptions[0];
    const records = [];

    document.querySelectorAll('#vaccination-list tr[id^="row-"]').forEach(tr => {
        records.push({
            animal_id: tr.dataset.id,
            quantity : tr.querySelector('.dosage-input').value,
            remarks  : tr.querySelector('.rem-input').value
        });
    });

    const payload = {
        records        : records,
        vaccine_id     : vacOpt.value,
        unit_id        : vacOpt.dataset.unitId,
        administered_by: document.getElementById('administered_by').value,
        date           : dateInput,
        event_ids      : incomingEventIds
    };

    try {
        const res  = await fetch('../process/addBatchVaccination.php', {
            method : 'POST',
            headers: { 'Content-Type': 'application/json' },
            body   : JSON.stringify(payload)
        });
        const data = await res.json();

        if (!data.success) {
            alert('❌ Error: ' + (data.message || 'Unknown error'));
            btn.disabled    = false;
            btn.textContent = 'Record Vaccination';
            return;
        }

        if (incomingEventIds) {
            try {
                const fd = new FormData();
                fd.append('action',    'mark_events_done');
                fd.append('event_ids', incomingEventIds);
                await fetch('../process/eventManager.php', { method: 'POST', body: fd });
            } catch (e) {
                console.warn('mark_events_done (non-fatal):', e);
            }
        }

        alert('✅ Batch vaccination recorded successfully!');
        loadHistory(1); // Live refresh history
        window.location.href = incomingEventIds ? 'events_scheduler.php' : window.location.pathname;

    } catch (e) {
        console.error(e);
        alert('System error. Please try again.');
        btn.disabled    = false;
        btn.textContent = 'Record Vaccination';
    }
}
</script>
</body>
</html>