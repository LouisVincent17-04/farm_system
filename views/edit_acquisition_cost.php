<?php
// views/edit_acquisition_cost.php
error_reporting(0);
ini_set('display_errors', 0);
include '../config/Connection.php';

// =========================================================
// AJAX HANDLERS FOR CASCADING DROPDOWNS & DATA FETCHING
// =========================================================
if (isset($_GET['action'])) {
    @ob_end_clean();
    header('Content-Type: application/json');
    $action = $_GET['action'];

    try {
        if ($action === 'get_buildings' && isset($_GET['loc_id'])) {
            $stmt = $conn->prepare("SELECT BUILDING_ID, BUILDING_NAME FROM BUILDINGS WHERE LOCATION_ID = ? ORDER BY BUILDING_NAME ASC");
            $stmt->execute([$_GET['loc_id']]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            exit;
        }
        if ($action === 'get_pens' && isset($_GET['bld_id'])) {
            // UPDATED SQL: Now actively counts the animals inside each pen
            $stmt = $conn->prepare("
                SELECT p.PEN_ID, p.PEN_NAME,
                       (SELECT COUNT(*) FROM animal_records a WHERE a.PEN_ID = p.PEN_ID AND a.IS_ACTIVE = 1 AND a.CURRENT_STATUS != 'Sold') as ANIMAL_COUNT
                FROM PENS p 
                WHERE p.BUILDING_ID = ? 
                ORDER BY p.PEN_NAME ASC
            ");
            $stmt->execute([$_GET['bld_id']]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            exit;
        }
        if ($action === 'get_filtered_animals') {
            $loc_id = $_GET['loc_id'] ?? '';
            $bld_id = $_GET['bld_id'] ?? '';
            $pen_id = $_GET['pen_id'] ?? '';

            if (empty($loc_id)) { echo json_encode([]); exit; }

            $sql = "SELECT a.ANIMAL_ID, a.TAG_NO, p.PEN_NAME, a.ACQUISITION_COST 
                    FROM animal_records a 
                    LEFT JOIN PENS p ON a.PEN_ID = p.PEN_ID 
                    LEFT JOIN BUILDINGS b ON a.BUILDING_ID = b.BUILDING_ID
                    WHERE a.IS_ACTIVE = 1 AND a.CURRENT_STATUS != 'Sold' 
                    AND b.LOCATION_ID = ?";
            $params = [$loc_id];

            if (!empty($bld_id)) { $sql .= " AND a.BUILDING_ID = ?"; $params[] = $bld_id; }
            if (!empty($pen_id)) { $sql .= " AND a.PEN_ID = ?"; $params[] = $pen_id; }
            
            $sql .= " ORDER BY a.TAG_NO ASC";

            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            exit;
        }
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
        exit;
    }
}
// =========================================================

include '../security/checkRole.php';
$page = "farm";
include '../common/navbar.php';
include '../common/chat_support.php';
include '../functions/getUsersLocation.php';

checkRole(4);
// Fetch initial locations
$loc_sql = ($USER_LOCATION_ != 1000) 
    ? "SELECT * FROM LOCATIONS WHERE LOCATION_ID = :loc_id ORDER BY LOCATION_NAME ASC" 
    : "SELECT * FROM LOCATIONS ORDER BY LOCATION_NAME ASC";
$stmtLoc = $conn->prepare($loc_sql);
if ($USER_LOCATION_ != 1000) $stmtLoc->execute([':loc_id' => $USER_LOCATION_]);
else $stmtLoc->execute();
$locations = $stmtLoc->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Acquisition Cost Edit | FarmPro</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=DM+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />

    <style>
        /* ─── CSS VARIABLES ─── */
        :root {
            --bg-base:        #080f1a;
            --bg-surface:     #0d1829;
            --bg-elevated:    #111f35;
            --bg-hover:       #162540;
            --border:         rgba(255,255,255,0.07);
            --border-active:  rgba(245,158,11,0.5); /* Amber Accent */
            
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
            --transition:     0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { 
            font-family: var(--font); background: var(--bg-base); color: var(--text-primary); 
            min-height: 100vh; padding-bottom: 60px; 
            background-image: radial-gradient(ellipse 80% 50% at 50% -20%, rgba(245,158,11,0.06) 0%, transparent 60%); 
        }
        .container { max-width: 1400px; margin: 0 auto; padding: 2rem 1.5rem; }

        .top-bar { display: flex; align-items: center; justify-content: space-between; margin-bottom: 2rem; gap: 1rem; flex-wrap: wrap; }
        .back-link { 
            display: inline-flex; align-items: center; gap: 8px; text-decoration: none; 
            color: var(--text-secondary); font-size: 0.875rem; font-weight: 500; 
            padding: 8px 14px; background: var(--bg-elevated); border: 1px solid var(--border); 
            border-radius: var(--radius-md); transition: var(--transition); 
        }
        .back-link:hover { color: var(--text-primary); background: var(--bg-hover); border-color: var(--border-active); }
        .page-badge { 
            display: inline-flex; align-items: center; gap: 6px; font-size: 0.75rem; 
            font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; 
            color: var(--amber); background: var(--amber-dim); border: 1px solid rgba(245,158,11,0.2); 
            padding: 6px 12px; border-radius: 99px; 
        }

        .page-header { margin-bottom: 2.5rem; }
        .page-header h1 { font-size: clamp(1.8rem, 4vw, 2.5rem); font-weight: 700; margin: 0 0 0.5rem 0; color: #fff; }
        .page-header h1 span { background: linear-gradient(135deg, var(--amber), #d97706); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .page-header p { color: var(--text-secondary); font-size: 0.95rem; margin: 0; }

        .btn {
            display: inline-flex; align-items: center; justify-content: center; gap: 7px;
            padding: 0 16px; height: 38px; border-radius: var(--radius-md); font-size: 0.85rem;
            font-weight: 600; font-family: var(--font); border: 1px solid transparent; cursor: pointer;
            transition: all var(--transition); text-decoration: none; white-space: nowrap; letter-spacing: 0.01em;
        }
        .btn-primary { background: var(--amber); color: #000; border-color: var(--amber); }
        .btn-primary:hover:not(:disabled) { background: #fbbf24; box-shadow: 0 4px 15px var(--amber-glow); transform: translateY(-1px); }
        .btn-primary:disabled { opacity: 0.5; cursor: not-allowed; }
        .btn-ghost { background: transparent; color: var(--text-secondary); border-color: var(--border); }
        .btn-ghost:hover { background: var(--bg-elevated); color: var(--text-primary); border-color: rgba(255,255,255,0.15); }

        .main-grid { display: grid; grid-template-columns: 320px 1fr; gap: 1.5rem; align-items: start; }

        .control-panel { 
            background: var(--bg-surface); border: 1px solid var(--border); 
            border-radius: var(--radius-xl); padding: 2rem; box-shadow: var(--shadow-md); 
            position: sticky; top: 1.5rem; display: flex; flex-direction: column;
        }
        .panel-title { font-size: 1.15rem; font-weight: 700; color: #fff; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 8px;}
        .panel-title i { color: var(--amber); }
        
        .form-group { display: flex; flex-direction: column; gap: 6px; margin-bottom: 1.25rem; }
        .form-label { color: var(--text-secondary); font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; display: flex; justify-content: space-between; align-items: center; }
        .form-label span.req { color: var(--red); }
        
        .form-select, .form-input { 
            width: 100%; padding: 12px 14px; background: var(--bg-elevated); border: 1px solid var(--border); border-radius: var(--radius-md); color: var(--text-primary); font-size: 0.95rem; transition: var(--transition); outline: none; font-family: var(--font); box-sizing: border-box; 
        }
        .form-select { 
            appearance: none; 
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2394a3b8' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E"); 
            background-repeat: no-repeat; background-position: right 12px center; cursor: pointer; 
        }
        .form-select:focus, .form-input:focus { border-color: var(--amber); box-shadow: 0 0 0 3px var(--amber-glow); background: var(--bg-hover); }
        .form-select:disabled { opacity: 0.5; cursor: not-allowed; }

        .btn-search { 
            width: 100%; padding: 14px; background: var(--amber); border: none; 
            border-radius: var(--radius-md); color: #000; font-weight: 700; font-size: 1rem; 
            font-family: var(--font); cursor: pointer; transition: var(--transition); 
            display: flex; align-items: center; justify-content: center; gap: 8px; margin-top: 1rem;
        }
        .btn-search:hover:not(:disabled) { background: #fbbf24; box-shadow: 0 4px 15px var(--amber-glow); transform: translateY(-2px); }
        .btn-search:disabled { opacity: 0.5; cursor: not-allowed; }

        .workspace-panel { display: flex; flex-direction: column; gap: 1.5rem; }
        
        .table-section { background: var(--bg-surface); border: 1px solid var(--border); border-radius: var(--radius-xl); overflow: hidden; box-shadow: var(--shadow-md);}
        
        .section-header { 
            padding: 1.5rem; border-bottom: 1px solid var(--border); background: var(--bg-elevated); 
            display: flex; flex-direction: column; gap: 10px;
        }
        .section-title { font-size: 1.15rem; font-weight: 700; display: flex; align-items: center; gap: 10px; color: #fff; }
        .section-title i { color: var(--amber); }

        /* ─── NEW BATCH BAR ─── */
        .quick-batch-bar {
            width: 100%; padding: 1.25rem; background: rgba(0,0,0,0.2); 
            border: 1px dashed rgba(245,158,11,0.3); border-radius: var(--radius-md); 
            margin-top: 0.5rem; display: flex; align-items: center; gap: 15px; flex-wrap: wrap;
        }
        .quick-batch-label {
            font-size:0.85rem; color:var(--amber); font-weight:700; 
            text-transform:uppercase; letter-spacing: 0.05em;
        }

        .table-scroll-wrapper { overflow-x: auto; max-height: 700px; }
        .data-table { width: 100%; border-collapse: collapse; min-width: 600px; }
        .data-table th { background: var(--bg-base); color: var(--text-muted); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; padding: 16px; text-align: left; font-weight: 700; border-bottom: 1px solid var(--border); position: sticky; top: 0; z-index: 10;}
        .data-table td { padding: 14px 16px; border-bottom: 1px solid rgba(255,255,255,0.03); color: var(--text-primary); vertical-align: middle;}
        .data-table tr:hover { background: rgba(255,255,255,0.01); }
        
        .tag-no { font-family: var(--font-mono); font-weight: 700; color: #fff; font-size: 1.05rem; }
        .pen-name { color: var(--text-secondary); font-size: 0.9rem; }
        .td-amt { font-family: var(--font-mono); font-weight: 700; color: var(--amber); font-size: 1rem; }

        .chk-container { display: flex; align-items: center; justify-content: center; cursor: pointer; }
        .chk-container input { width: 18px; height: 18px; cursor: pointer; accent-color: var(--amber); }

        .action-btn { width: 34px; height: 34px; border-radius: 6px; border: 1px solid var(--border); background: var(--bg-elevated); display: inline-flex; align-items: center; justify-content: center; cursor: pointer; transition: all var(--transition); color: var(--text-secondary); text-decoration: none; font-size: 0.85rem; }
        .action-btn:hover { background: var(--bg-hover); color: var(--text-primary); }
        .action-btn.edit:hover { color: var(--amber); border-color: var(--amber); background: var(--amber-dim);}

        .empty-state { text-align: center; padding: 5rem 2rem; color: var(--text-muted); font-style: italic; display: flex; flex-direction: column; align-items: center; justify-content: center;}
        .empty-state i { font-size: 3rem; color: var(--amber-dim); margin-bottom: 1rem; display: block; }

        .modal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.85); backdrop-filter: blur(5px); z-index: 1100; align-items: flex-start; justify-content: center; padding: 5vh 1rem; overflow-y: hidden;}
        .modal.show { display: flex; }
        .modal-content { background: var(--bg-surface); border: 1px solid var(--border); border-radius: var(--radius-xl); width: 100%; max-width: 450px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.6); display: flex; flex-direction: column; animation: modalZoom 0.2s ease-out; margin: auto;}
        @keyframes modalZoom { from { transform: scale(0.95); opacity: 0; } to { transform: scale(1); opacity: 1; } }
        
        .modal-header { padding: 1.5rem; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; flex-shrink: 0; }
        .modal-header h2 { margin: 0; font-size: 1.25rem; color: #fff; }
        .btn-close { background: transparent; border: none; color: var(--text-muted); font-size: 1.5rem; cursor: pointer; transition: var(--transition); display: flex; align-items: center;}
        .btn-close:hover { color: var(--red); }
        
        .modal-body { padding: 1.5rem; overflow-y: auto; flex: 1 1 auto; }
        .modal-footer { padding: 1.25rem 1.5rem; border-top: 1px solid var(--border); display: flex; justify-content: flex-end; gap: 10px; background: var(--bg-elevated); border-radius: 0 0 var(--radius-xl) var(--radius-xl); flex-shrink: 0;}

        #toastContainer { position: fixed; top: 20px; right: 20px; z-index: 9999; display: flex; flex-direction: column; gap: 10px; }
        .toast { background: var(--bg-surface); border: 1px solid var(--border); color: #fff; padding: 1rem 1.5rem; border-radius: var(--radius-md); box-shadow: 0 10px 25px rgba(0,0,0,0.5); font-size: 0.9rem; font-weight: 600; animation: slideIn 0.3s ease-out; }

        @media (max-width: 1024px) { .main-grid { grid-template-columns: 1fr; } .control-panel { position: relative; top: 0; } }
        @media (max-width: 768px) {
            .btn-primary { width: 100%; justify-content: center; }
            .modal-footer { flex-direction: column-reverse; }
            .modal-footer button { width: 100%; margin: 0; }
            .quick-batch-bar { flex-direction: column; align-items: stretch; }
            .quick-batch-bar input { width: 100% !important; }
        }
    </style>
</head>
<body>

<div id="toastContainer"></div>

<div class="container">
    <div class="top-bar">
        <a href="farm_dashboard.php" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back to Dashboard
        </a>
        <span class="page-badge"><i class="fa-solid fa-money-bill-transfer"></i> Valuations</span>
    </div>

    <div class="page-header">
        <div class="header-info">
            <h1>Acquisition <span>Cost Editor</span></h1>
            <p>Select a location to modify the base acquisition costs of active animals in bulk.</p>
        </div>
    </div>

    <div class="main-grid">
        <div class="control-panel">
            <div class="panel-title"><i class="fa-solid fa-filter"></i> Target Scope</div>
            
            <div class="form-group">
                <label class="form-label">1. Location <span class="req">*</span></label>
                <select id="filter_loc" class="form-select" onchange="loadBuildings()" <?php echo ($USER_LOCATION_ != 1000) ? 'disabled style="opacity:0.6;"' : ''; ?>>
                    <option value="">-- Choose Location --</option>
                    <?php foreach($locations as $loc): ?>
                        <option value="<?= $loc['LOCATION_ID'] ?>" <?= ($USER_LOCATION_ != 1000 && $loc['LOCATION_ID'] == $USER_LOCATION_) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($loc['LOCATION_NAME']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">2. Building <span style="color:var(--text-muted); font-weight:400; text-transform:none;">(Optional)</span></label>
                <select id="filter_bld" class="form-select" disabled onchange="loadPens()">
                    <option value="">-- All Buildings --</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">3. Pen <span style="color:var(--text-muted); font-weight:400; text-transform:none;">(Optional)</span></label>
                <select id="filter_pen" class="form-select" disabled>
                    <option value="">-- All Pens --</option>
                </select>
            </div>

            <button class="btn-search" id="btnSearch" onclick="fetchFilteredAnimals()">
                <i class="fa-solid fa-magnifying-glass"></i> Load Animals
            </button>
        </div>

        <div class="workspace-panel">
            <div class="table-section">
                <div class="section-header">
                    <div style="display:flex; justify-content:space-between; align-items:center; width:100%; flex-wrap:wrap; gap:10px;">
                        <div class="section-title"><i class="fa-solid fa-list-check"></i> Select Animals</div>
                    </div>
                    
                    <div class="quick-batch-bar">
                        <div class="quick-batch-label">
                            Batch Update Checked:
                        </div>
                        <input type="number" id="quick_batch_amount" class="form-input" placeholder="New Cost (₱)" step="0.01" min="0" style="width:180px; border-color: var(--amber);">
                        <button class="btn-primary" id="btnBatchUpdate" disabled onclick="submitQuickBatchUpdate()" style="height:42px; padding: 0 24px;">
                            <i class="fa-solid fa-floppy-disk"></i> Apply to Checked (0)
                        </button>
                    </div>
                </div>
                <div class="table-scroll-wrapper">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th style="width: 50px; text-align: center;">
                                    <div class="chk-container">
                                        <input type="checkbox" id="selectAll" onchange="toggleAll(this)" disabled>
                                    </div>
                                </th>
                                <th>Tag No.</th>
                                <th>Pen</th>
                                <th style="text-align:right;">Current Acq. Cost (₱)</th>
                                <th style="text-align:center; width: 80px;">Edit</th>
                            </tr>
                        </thead>
                        <tbody id="animalTableBody">
                            <tr>
                                <td colspan="5">
                                    <div class="empty-state">
                                        <i class="fa-solid fa-filter"></i>
                                        Select a location on the left and click "Load Animals" to begin.
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="costModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modal-title">Update Acquisition Cost</h2>
            <button class="btn-close" onclick="closeModal()"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body">
            <div id="modal-alert" style="display:none; padding:10px; margin-bottom:1rem; border-radius:8px; background:var(--red-dim); color:var(--red); border:1px solid rgba(239,68,68,0.3); text-align:center; font-weight:600;"></div>
            
            <form id="costForm">
                <input type="hidden" id="single_animal_id" name="animal_ids" value="">
                
                <div class="form-group">
                    <label class="form-label">New Acquisition Cost (₱) <span style="color:var(--red);">*</span></label>
                    <input type="number" id="cost-amount" name="amount" class="form-input" style="font-family:var(--font-mono); font-weight:700; font-size:1.1rem; color:var(--amber);" placeholder="0.00" step="0.01" min="0" required>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-ghost" onclick="closeModal()">Cancel</button>
            <button type="button" class="btn btn-primary" id="btn-save" onclick="submitSingleUpdate()">
                <i class="fa-solid fa-floppy-disk"></i> Update Record
            </button>
        </div>
    </div>
</div>

<script>
    const USER_LOC = '<?= $USER_LOCATION_ ?>';

    document.addEventListener('DOMContentLoaded', () => {
        if(USER_LOC != '1000') {
            loadBuildings();
        }
    });

    async function fetchJSON(url) {
        try { const r = await fetch(url); return await r.json(); } catch(e) { return []; }
    }

    function loadBuildings() {
        const loc = document.getElementById('filter_loc').value;
        const bld = document.getElementById('filter_bld');
        const pen = document.getElementById('filter_pen');

        bld.innerHTML = '<option value="">-- All Buildings --</option>'; bld.disabled = true;
        pen.innerHTML = '<option value="">-- All Pens --</option>'; pen.disabled = true;

        if(!loc) return;
        bld.innerHTML = '<option>Loading...</option>';

        fetchJSON(`?action=get_buildings&loc_id=${loc}`).then(data => {
            bld.innerHTML = '<option value="">-- All Buildings --</option>';
            if(Array.isArray(data) && data.length) {
                data.forEach(i => bld.innerHTML += `<option value="${i.BUILDING_ID}">${i.BUILDING_NAME}</option>`);
                bld.disabled = false;
            }
        });
    }

    function loadPens() {
        const bld = document.getElementById('filter_bld').value;
        const pen = document.getElementById('filter_pen');

        pen.innerHTML = '<option value="">-- All Pens --</option>'; pen.disabled = true;

        if(!bld) return;
        pen.innerHTML = '<option>Loading...</option>';

        fetchJSON(`?action=get_pens&bld_id=${bld}`).then(data => {
            pen.innerHTML = '<option value="">-- All Pens --</option>';
            if(Array.isArray(data) && data.length) {
                data.forEach(i => pen.innerHTML += `<option value="${i.PEN_ID}">${i.PEN_NAME} (${i.ANIMAL_COUNT})</option>`);
                pen.disabled = false;
            }
        });
    }

    function fetchFilteredAnimals() {
        const loc = document.getElementById('filter_loc').value;
        const bld = document.getElementById('filter_bld').value;
        const pen = document.getElementById('filter_pen').value;
        const tbody = document.getElementById('animalTableBody');
        const btnSearch = document.getElementById('btnSearch');

        if(!loc) {
            showToast('Please select a Location first.', 'error');
            return;
        }

        btnSearch.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Loading...';
        btnSearch.disabled = true;
        tbody.innerHTML = '<tr><td colspan="5" class="empty-state"><i class="fa-solid fa-spinner fa-spin"></i> Fetching records...</td></tr>';
        
        document.getElementById('selectAll').checked = false;
        document.getElementById('selectAll').disabled = true;
        updateSelectionCount();

        fetchJSON(`?action=get_filtered_animals&loc_id=${loc}&bld_id=${bld}&pen_id=${pen}`).then(data => {
            tbody.innerHTML = '';
            btnSearch.innerHTML = '<i class="fa-solid fa-magnifying-glass"></i> Load Animals';
            btnSearch.disabled = false;

            if (data.error) {
                tbody.innerHTML = `<tr><td colspan="5"><div class="empty-state" style="color:var(--red);"><i class="fa-solid fa-triangle-exclamation"></i> Error: ${data.error}</div></td></tr>`;
                return;
            }

            if(!Array.isArray(data) || data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5"><div class="empty-state"><i class="fa-solid fa-piggy-bank"></i> No active animals found in this scope.</div></td></tr>';
                return;
            }

            document.getElementById('selectAll').disabled = false;

            data.forEach(a => {
                const cost = parseFloat(a.ACQUISITION_COST || 0).toLocaleString('en-PH', {minimumFractionDigits: 2});
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td style="text-align: center;">
                        <div class="chk-container">
                            <input type="checkbox" class="animal-chk" value="${a.ANIMAL_ID}" onchange="updateSelectionCount()">
                        </div>
                    </td>
                    <td><div class="tag-no">${a.TAG_NO}</div></td>
                    <td><div class="pen-name">${a.PEN_NAME || 'Unassigned'}</div></td>
                    <td style="text-align:right;"><div class="td-amt">₱${cost}</div></td>
                    <td style="text-align:center;">
                        <button class="action-btn edit" onclick="singleEdit(${a.ANIMAL_ID}, ${a.ACQUISITION_COST || 0})" title="Edit Cost">
                            <i class="fa-solid fa-pen-to-square"></i>
                        </button>
                    </td>
                `;
                tbody.appendChild(tr);
            });
        });
    }

    function toggleAll(source) {
        const checkboxes = document.querySelectorAll('.animal-chk');
        checkboxes.forEach(chk => chk.checked = source.checked);
        updateSelectionCount();
    }

    function updateSelectionCount() {
        const selected = document.querySelectorAll('.animal-chk:checked').length;
        const btnBatch = document.getElementById('btnBatchUpdate');
        
        btnBatch.innerHTML = `<i class="fa-solid fa-floppy-disk"></i> Apply to Checked (${selected})`;
        btnBatch.disabled = selected === 0;

        const total = document.querySelectorAll('.animal-chk').length;
        const selectAll = document.getElementById('selectAll');
        if(total > 0 && selected === total) {
            selectAll.checked = true;
            selectAll.indeterminate = false;
        } else if (selected > 0) {
            selectAll.checked = false;
            selectAll.indeterminate = true;
        } else {
            selectAll.checked = false;
            selectAll.indeterminate = false;
        }
    }

    // --- QUICK BATCH UPDATE (NO MODAL) ---
    async function submitQuickBatchUpdate() {
        const amountInput = document.getElementById('quick_batch_amount');
        const amount = amountInput.value.trim();
        
        if(amount === '') {
            showToast('Please enter a new acquisition cost amount.', 'error');
            amountInput.focus();
            return;
        }

        const checkboxes = document.querySelectorAll('.animal-chk:checked');
        const ids = Array.from(checkboxes).map(c => c.value);
        
        if(ids.length === 0) return;

        const btn = document.getElementById('btnBatchUpdate');
        const origText = btn.innerHTML;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Processing...';
        btn.disabled = true;

        const formData = new URLSearchParams();
        formData.append('animal_ids', JSON.stringify(ids));
        formData.append('amount', amount);

        try {
            const res = await fetch('../process/updateAcquisitionCost.php', { 
                method: 'POST', 
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, 
                body: formData.toString() 
            });
            const data = await res.json();

            if(data.success) {
                showToast(data.message, 'success');
                amountInput.value = ''; // clear input
                fetchFilteredAnimals();
                btn.innerHTML = origText; btn.disabled = false;
            } else {
                showToast(data.message || 'Error updating costs.', 'error');
                btn.innerHTML = origText; btn.disabled = false;
            }
        } catch(e) {
            showToast('Connection error. Please try again.', 'error');
            btn.innerHTML = origText; btn.disabled = false;
        }
    }

    // --- SINGLE EDIT MODAL ---
    function singleEdit(animalId, currentCost) {
        document.getElementById('single_animal_id').value = JSON.stringify([animalId.toString()]);
        document.getElementById('costForm').reset();
        document.getElementById('cost-amount').value = currentCost;
        document.getElementById('modal-alert').style.display = 'none';
        
        document.getElementById('costModal').classList.add('show');
    }

    function closeModal() {
        document.getElementById('costModal').classList.remove('show');
    }

    async function submitSingleUpdate() {
        const form = document.getElementById('costForm');
        if(!form.checkValidity()) { form.reportValidity(); return; }

        const btn = document.getElementById('btn-save');
        const origText = btn.innerHTML;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Processing...';
        btn.disabled = true;

        const formData = new URLSearchParams(new FormData(form));

        try {
            const res = await fetch('../process/updateAcquisitionCost.php', { 
                method: 'POST', 
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, 
                body: formData.toString() 
            });
            const data = await res.json();

            if(data.success) {
                closeModal();
                showToast(data.message, 'success');
                fetchFilteredAnimals();
                btn.innerHTML = origText; btn.disabled = false;
            } else {
                const alert = document.getElementById('modal-alert');
                alert.textContent = data.message || 'Error updating costs.';
                alert.style.display = 'block';
                btn.innerHTML = origText; btn.disabled = false;
            }
        } catch(e) {
            const alert = document.getElementById('modal-alert');
            alert.textContent = 'Connection error. Please try again.';
            alert.style.display = 'block';
            btn.innerHTML = origText; btn.disabled = false;
        }
    }

    function showToast(msg, type = 'success') {
        const t = document.createElement('div');
        t.className = 'toast';
        t.style.borderLeft = `4px solid ${type === 'error' ? 'var(--red)' : 'var(--emerald)'}`;
        t.innerHTML = `${type === 'error' ? '❌' : '✅'} ${msg}`;
        document.getElementById('toastContainer').appendChild(t);
        setTimeout(() => t.remove(), 3500);
    }
</script>
</body>
</html>