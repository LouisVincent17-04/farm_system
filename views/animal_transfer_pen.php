<?php
// views/animal_transfer_pen.php
ob_start(); // Start output buffering immediately
$page = "farm";
include '../config/Connection.php';

include '../security/checkAccess.php';
checkAccess('animal_transfer');
include '../functions/getUsersLocation.php';

// =========================================================
// 1. AJAX HANDLER (For Dropdowns & Animal Lists)
// =========================================================
if (isset($_GET['action'])) {
    ob_end_clean(); 
    header('Content-Type: application/json');
    $action = $_GET['action'];
    $status = $_GET['status_filter'] ?? 'Active';

    $statusClause = " AND a.IS_ACTIVE = 1 ";
    if ($status === 'Inactive') $statusClause = " AND a.IS_ACTIVE = 0 ";
    if ($status === 'All') $statusClause = ""; 

    try {
        if ($action === 'get_buildings' && isset($_GET['loc_id'])) {
            $stmt = $conn->prepare("SELECT BUILDING_ID, BUILDING_NAME FROM buildings WHERE LOCATION_ID = ? ORDER BY BUILDING_NAME");
            $stmt->execute([$_GET['loc_id']]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC)); exit;
        }
        
        if ($action === 'get_pens' && isset($_GET['bldg_id'])) {
            $stmt = $conn->prepare("SELECT p.PEN_ID, p.PEN_NAME, 
                                    (SELECT COUNT(*) FROM animal_records a WHERE a.PEN_ID = p.PEN_ID AND a.IS_ACTIVE = 1 AND a.CURRENT_STATUS != 'Sold') as ANIMAL_COUNT 
                                    FROM pens p WHERE p.BUILDING_ID = ? ORDER BY p.PEN_NAME");
            $stmt->execute([$_GET['bldg_id']]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC)); exit;
        }
        
        if ($action === 'get_animals' && isset($_GET['pen_id'])) {
            $sql = "SELECT a.ANIMAL_ID, a.TAG_NO, t.ANIMAL_TYPE_NAME, b.BREED_NAME 
                    FROM animal_records a
                    LEFT JOIN animal_type t ON a.ANIMAL_TYPE_ID = t.ANIMAL_TYPE_ID
                    LEFT JOIN breeds b ON a.BREED_ID = b.BREED_ID
                    WHERE a.PEN_ID = ? $statusClause 
                    ORDER BY a.TAG_NO";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$_GET['pen_id']]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC)); exit;
        }
    } catch (Exception $e) { echo json_encode([]); exit; }
}

include '../common/navbar.php';
include '../common/chat_support.php';

// Auto-assign location filter if user is restricted
if ($USER_LOCATION_ != 1000) {
    $stmt = $conn->prepare("SELECT * FROM locations WHERE LOCATION_ID = ? ORDER BY LOCATION_NAME");
    $stmt->execute([$USER_LOCATION_]);
    $locations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $locations = $conn->query("SELECT * FROM locations ORDER BY LOCATION_NAME")->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Transfer Animal Group | FarmPro</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />

    <style>
        /* ─── CSS VARIABLES ─── */
        :root {
            --bg-base:        #080f1a;
            --bg-surface:     #0d1829;
            --bg-elevated:    #111f35;
            --bg-hover:       #162540;
            --border:         rgba(255,255,255,0.07);
            --sky:            #0ea5e9;
            --sky-dim:        rgba(14,165,233,0.12);
            --emerald:        #10b981;
            --emerald-dim:    rgba(16,185,129,0.12);
            --text-primary:   #f1f5f9;
            --text-secondary: #94a3b8;
            --text-muted:     #475569;
            --radius-md:      10px;
            --radius-lg:      14px;
            --radius-xl:      20px;
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
            padding-bottom: 120px;
            background-image: 
                radial-gradient(circle at 0% 0%, rgba(14,165,233,0.04) 0%, transparent 40%),
                radial-gradient(circle at 100% 100%, rgba(16,185,129,0.04) 0%, transparent 40%);
        }
        .container { max-width: 1400px; margin: 0 auto; padding: 2rem 1.5rem; }

        /* ─── TOP BAR & HEADER ─── */
        .top-bar { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem; }
        .back-link {
            display: inline-flex; align-items: center; gap: 8px; text-decoration: none;
            color: var(--text-secondary); font-size: 0.875rem; font-weight: 500;
            padding: 8px 14px; background: var(--bg-elevated); border: 1px solid var(--border);
            border-radius: var(--radius-md); transition: all var(--transition);
        }
        .back-link:hover { color: var(--text-primary); border-color: rgba(255,255,255,0.2); background: var(--bg-hover); }

        .page-badge {
            display: inline-flex; align-items: center; gap: 6px; font-size: 0.75rem;
            font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase;
            color: var(--sky); background: var(--sky-dim); border: 1px solid rgba(14,165,233,0.2);
            padding: 6px 12px; border-radius: 99px;
        }

        .page-header { margin-bottom: 2rem; }
        .page-title {
            font-size: clamp(1.6rem, 3vw, 2.2rem); font-weight: 700;
            color: var(--text-primary); letter-spacing: -0.03em; line-height: 1.1; margin-bottom: 0.25rem;
        }
        .page-title span {
            background: linear-gradient(135deg, var(--sky), var(--emerald));
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }
        .page-subtitle { color: var(--text-secondary); font-size: 0.95rem; }

        /* ─── TRANSFER GRID ─── */
        .transfer-grid {
            display: grid; grid-template-columns: 1fr 60px 1fr;
            gap: 1.5rem; align-items: stretch; margin-bottom: 2rem;
        }
        
        .panel {
            background: var(--bg-surface); border: 1px solid var(--border);
            border-radius: var(--radius-xl); padding: 1.5rem;
            display: flex; flex-direction: column; box-shadow: 0 10px 30px -10px rgba(0,0,0,0.5);
            position: relative; overflow: hidden;
        }
        
        /* Panel Branding */
        .panel-src::before { content:''; position:absolute; top:0; left:0; width:100%; height:4px; background: var(--sky); }
        .panel-dest::before { content:''; position:absolute; top:0; left:0; width:100%; height:4px; background: var(--emerald); }
        
        .panel-header {
            font-size: 1.1rem; font-weight: 700; margin-bottom: 1.25rem;
            text-transform: uppercase; letter-spacing: 0.05em; display: flex; align-items: center; gap: 8px;
        }
        .src-title { color: var(--sky); }
        .dest-title { color: var(--emerald); }

        /* ─── FORM ELEMENTS ─── */
        .form-group { margin-bottom: 1rem; }
        .form-label { font-size: 0.72rem; font-weight: 600; text-transform: uppercase; color: var(--text-secondary); letter-spacing: 0.05em; margin-bottom: 6px; display: block; }
        
        .form-select {
            width: 100%; padding: 0 12px; height: 42px; background: var(--bg-elevated);
            border: 1px solid var(--border); color: var(--text-primary);
            border-radius: var(--radius-md); font-size: 0.9rem; font-family: var(--font);
            outline: none; transition: all var(--transition); appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2394a3b8' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
            background-repeat: no-repeat; background-position: right 12px center; cursor: pointer;
        }
        .panel-src .form-select:focus { border-color: var(--sky); box-shadow: 0 0 0 3px var(--sky-glow); }
        .panel-dest .form-select:focus { border-color: var(--emerald); box-shadow: 0 0 0 3px var(--emerald-glow); }
        .form-select:disabled { opacity: 0.4; cursor: not-allowed; }

        /* ─── ANIMAL LIST BOX ─── */
        .list-header {
            display: flex; justify-content: space-between; align-items: center;
            margin: 1rem 0 0.5rem 0; padding-top: 1rem; border-top: 1px solid var(--border);
        }
        .btn-select-all {
            background: rgba(255,255,255,0.05); border: 1px solid var(--border); color: var(--text-primary);
            padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: 600; cursor: pointer;
            transition: all var(--transition);
        }
        .btn-select-all:hover { background: var(--bg-hover); border-color: var(--sky); color: var(--sky); }
        
        .dest-count { font-family: var(--font-mono); color: var(--emerald); font-weight: 700; font-size: 0.85rem; background: var(--emerald-dim); padding: 4px 10px; border-radius: 6px; border: 1px solid rgba(16,185,129,0.2); }

        .animal-list-box {
            flex-grow: 1; background: var(--bg-elevated); border: 1px solid var(--border);
            border-radius: var(--radius-md); min-height: 300px; max-height: 500px;
            overflow-y: auto; display: flex; flex-direction: column;
        }
        .readonly-list { background: rgba(0,0,0,0.2); }

        .empty-box-msg { margin: auto; text-align: center; color: var(--text-muted); font-size: 0.9rem; padding: 2rem; }
        .empty-box-msg i { font-size: 2rem; margin-bottom: 0.5rem; opacity: 0.3; display: block; }

        .animal-item {
            display: flex; align-items: center; gap: 12px; padding: 12px 16px;
            border-bottom: 1px solid rgba(255,255,255,0.03); transition: all var(--transition);
        }
        .panel-src .animal-item:hover { background: var(--bg-hover); }
        .readonly-list .animal-item { opacity: 0.7; }
        
        .animal-item label { cursor: pointer; flex-grow: 1; display: flex; justify-content: space-between; align-items: center; margin: 0; }
        .item-checkbox { width: 18px; height: 18px; accent-color: var(--sky); cursor: pointer; }
        
        .tag-block { display: flex; flex-direction: column; gap: 2px; }
        .tag-no { font-family: var(--font-mono); font-weight: 700; color: #fff; font-size: 0.95rem; }
        .dest-list .tag-no { color: var(--emerald); }
        .tag-desc { font-size: 0.75rem; color: var(--text-secondary); }

        /* ─── MIDDLE ARROW ─── */
        .middle-action { display: flex; align-items: center; justify-content: center; height: 100%; }
        .arrow-icon {
            width: 48px; height: 48px; border-radius: 50%; background: var(--bg-elevated);
            border: 1px solid var(--border); display: flex; align-items: center; justify-content: center;
            font-size: 1.2rem; color: var(--text-muted); box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }

        /* ─── STICKY FOOTER ─── */
        .action-footer {
            position: fixed; bottom: 0; left: 0; width: 100%;
            background: rgba(13, 24, 41, 0.95); backdrop-filter: blur(12px);
            border-top: 1px solid var(--border); padding: 1.25rem 2rem;
            display: flex; justify-content: space-between; align-items: center;
            z-index: 100; box-shadow: 0 -10px 40px rgba(0,0,0,0.5);
        }
        
        .count-display { font-size: 0.85rem; color: var(--text-secondary); text-transform: uppercase; font-weight: 600; letter-spacing: 0.05em; display: flex; align-items: center; gap: 10px; }
        #selectedCount { font-family: var(--font-mono); font-size: 1.5rem; color: var(--sky); font-weight: 700; }

        .btn-transfer {
            background: var(--sky); color: #000; border: none; padding: 12px 36px;
            border-radius: var(--radius-md); font-weight: 700; font-size: 1rem;
            font-family: var(--font); cursor: pointer; transition: all var(--transition);
            box-shadow: 0 4px 15px var(--sky-glow); display: inline-flex; align-items: center; gap: 8px;
        }
        .btn-transfer:hover:not(:disabled) { background: #38bdf8; transform: translateY(-2px); box-shadow: 0 8px 25px var(--sky-glow); }
        .btn-transfer:disabled { background: var(--bg-elevated); color: var(--text-muted); box-shadow: none; border: 1px solid var(--border); cursor: not-allowed; }

        /* ─── ALERTS ─── */
        #toastContainer { position: fixed; top: 20px; right: 20px; z-index: 9999; display: flex; flex-direction: column; gap: 10px; }
        .toast {
            background: var(--bg-surface); border: 1px solid var(--border); color: #fff;
            padding: 1rem 1.5rem; border-radius: var(--radius-md); box-shadow: 0 10px 25px rgba(0,0,0,0.5);
            font-size: 0.9rem; font-weight: 600; animation: slideIn 0.3s ease-out;
        }
        @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }

        /* ─── RESPONSIVE ─── */
        @media (max-width: 900px) {
            .container { padding: 1rem; }
            .page-header { flex-direction: column; align-items: flex-start; }
            
            .transfer-grid { grid-template-columns: 1fr; gap: 1rem; }
            
            .middle-action { padding: 0.5rem; }
            .arrow-icon { transform: rotate(90deg); width: 40px; height: 40px; font-size: 1rem; }
            
            .animal-list-box { min-height: 200px; max-height: 350px; }

            .action-footer { flex-direction: column; gap: 1rem; padding: 1.5rem; text-align: center; }
            .btn-transfer { width: 100%; justify-content: center; }
        }
    </style>
</head>
<body>

<div id="toastContainer"></div>

<div class="container">
    
    <div class="top-bar">
        <a href="farm_dashboard.php" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Farm Dashboard
        </a>
        <span class="page-badge"><i class="fa-solid fa-truck-moving"></i> Relocation</span>
    </div>

    <div class="page-header">
        <div class="header-info">
            <h1 class="page-title">Group <span>Transfer</span></h1>
            <p class="page-subtitle">Batch reassign livestock from one structural unit to another.</p>
        </div>
    </div>

    <form id="transferForm" onsubmit="submitTransfer(event)">
        <div class="transfer-grid">
            
            <div class="panel panel-src">
                <div class="panel-header src-title"><i class="fa-solid fa-box-open me-2"></i> 1. Source (Origin)</div>
                
                <div class="form-group">
                    <label class="form-label">Location Site</label>
                    <select id="src_loc" class="form-select" onchange="loadBuildings('src')" <?php echo ($USER_LOCATION_ != 1000) ? 'disabled' : ''; ?>>
                        <option value="">-- Select --</option>
                        <?php foreach($locations as $l): ?>
                            <option value="<?= $l['LOCATION_ID'] ?>" <?php echo ($USER_LOCATION_ != 1000 && $l['LOCATION_ID'] == $USER_LOCATION_) ? 'selected' : ''; ?>>
                                <?= htmlspecialchars($l['LOCATION_NAME']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Building / Structure</label>
                    <select id="src_bld" class="form-select" disabled onchange="loadPens('src')">
                        <option value="">-- Select Location First --</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Specific Pen</label>
                    <select id="src_pen" class="form-select" disabled onchange="loadAnimals('src')">
                        <option value="">-- Select Building First --</option>
                    </select>
                </div>

                <div class="list-header">
                    <label class="form-label" style="margin:0;">Available Records</label>
                    <button type="button" class="btn-select-all" onclick="selectAll(true)">Select All</button>
                </div>
                
                <div id="src_animalList" class="animal-list-box">
                    <div class="empty-box-msg">
                        <i class="fa-solid fa-arrow-pointer"></i>
                        Select a Source Pen to view available livestock.
                    </div>
                </div>
            </div>

            <div class="middle-action">
                <div class="arrow-icon"><i class="fa-solid fa-arrow-right"></i></div>
            </div>

            <div class="panel panel-dest">
                <div class="panel-header dest-title"><i class="fa-solid fa-box-archive me-2"></i> 2. Destination (Target)</div>
                
                <div class="form-group">
                    <label class="form-label">Location Site</label>
                    <select id="dest_loc" name="dest_location_id" class="form-select" required onchange="loadBuildings('dest')" <?php echo ($USER_LOCATION_ != 1000) ? 'disabled' : ''; ?>>
                        <option value="">-- Select --</option>
                        <?php foreach($locations as $l): ?>
                            <option value="<?= $l['LOCATION_ID'] ?>" <?php echo ($USER_LOCATION_ != 1000 && $l['LOCATION_ID'] == $USER_LOCATION_) ? 'selected' : ''; ?>>
                                <?= htmlspecialchars($l['LOCATION_NAME']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($USER_LOCATION_ != 1000): ?>
                        <input type="hidden" name="dest_location_id" value="<?= $USER_LOCATION_ ?>">
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label class="form-label">Building / Structure</label>
                    <select id="dest_bld" name="dest_building_id" class="form-select" required disabled onchange="loadPens('dest')">
                        <option value="">-- Select Location First --</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Specific Pen</label>
                    <select id="dest_pen" name="dest_pen_id" class="form-select" required disabled onchange="loadAnimals('dest')">
                        <option value="">-- Select Building First --</option>
                    </select>
                </div>

                <div class="list-header">
                    <label class="form-label" style="margin:0;">Current Residents</label>
                    <span id="destCount" class="dest-count">0 Heads</span>
                </div>
                
                <div id="dest_animalList" class="animal-list-box readonly-list dest-list">
                    <div class="empty-box-msg">
                        <i class="fa-solid fa-map-pin"></i>
                        Select a Destination Pen to view current occupants.
                    </div>
                </div>

                <div style="margin-top: 15px; padding: 12px; background: rgba(16, 185, 129, 0.05); border-radius: 8px; border: 1px solid rgba(16, 185, 129, 0.2);">
                    <p style="font-size:0.8rem; color:var(--text-secondary); margin:0; line-height: 1.5;">
                        <strong style="color:var(--emerald)"><i class="fa-solid fa-circle-info me-1"></i> Note:</strong> 
                        Selected animals will be officially moved to this location. History logs will be updated automatically.
                    </p>
                </div>
            </div>

        </div>

        <div class="action-footer">
            <div class="count-display">
                Queued for Transfer: <span id="selectedCount">0</span>
            </div>
            <button type="submit" class="btn-transfer" id="btnTransfer" disabled>
                Execute Transfer <i class="fa-solid fa-paper-plane"></i>
            </button>
        </div>
    </form>
</div>

<script>
    const API_URL = window.location.pathname.split("/").pop();

    function showToast(msg, type = 'success') {
        const t = document.createElement('div');
        t.className = 'toast';
        t.style.borderLeft = `4px solid ${type === 'error' ? 'var(--red)' : 'var(--emerald)'}`;
        t.innerHTML = `${type === 'error' ? ' ' : ' '} ${msg}`;
        document.getElementById('toastContainer').appendChild(t);
        setTimeout(() => t.remove(), 3500);
    }

    document.addEventListener('DOMContentLoaded', () => {
        const userLoc = <?php echo json_encode($USER_LOCATION_); ?>;
        if (userLoc != 1000) {
            loadBuildings('src');
            loadBuildings('dest');
        }
    });

    async function loadBuildings(prefix) {
        const locId = document.getElementById(prefix + '_loc').value;
        const bldSelect = document.getElementById(prefix + '_bld');
        const penSelect = document.getElementById(prefix + '_pen');
        const list = document.getElementById(prefix + '_animalList');
        
        bldSelect.innerHTML = '<option value="">Loading...</option>';
        penSelect.innerHTML = '<option value="">-- Select --</option>';
        penSelect.disabled = true;
        list.innerHTML = `<div class="empty-box-msg"><i class="fa-solid fa-arrow-pointer"></i>Select a ${prefix === 'src' ? 'Source' : 'Destination'} Pen first.</div>`;

        if(!locId) {
            bldSelect.innerHTML = '<option value="">-- Select Location First --</option>';
            bldSelect.disabled = true;
            return;
        }

        const res = await fetch(`${API_URL}?action=get_buildings&loc_id=${locId}`);
        const data = await res.json();
        
        bldSelect.innerHTML = '<option value="">-- Choose Building --</option>';
        data.forEach(b => bldSelect.innerHTML += `<option value="${b.BUILDING_ID}">${b.BUILDING_NAME}</option>`);
        bldSelect.disabled = false;
    }

    async function loadPens(prefix) {
        const bldId = document.getElementById(prefix + '_bld').value;
        const penSelect = document.getElementById(prefix + '_pen');
        const list = document.getElementById(prefix + '_animalList');
        
        penSelect.innerHTML = '<option value="">Loading...</option>';
        list.innerHTML = `<div class="empty-box-msg"><i class="fa-solid fa-arrow-pointer"></i>Select a ${prefix === 'src' ? 'Source' : 'Destination'} Pen first.</div>`;

        if(!bldId) {
            penSelect.innerHTML = '<option value="">-- Select Building First --</option>';
            penSelect.disabled = true;
            return;
        }

        const res = await fetch(`${API_URL}?action=get_pens&bldg_id=${bldId}`);
        const data = await res.json();
        
        penSelect.innerHTML = '<option value="">-- Choose Pen --</option>';
        data.forEach(p => {
            const count = p.ANIMAL_COUNT !== null ? parseInt(p.ANIMAL_COUNT) : 0;
            
            // New logic: Do not show empty pens in the Source dropdown
            if (prefix === 'src' && count === 0) return;

            // Existing logic: Prevent multiple animals in a gestating pen (Destination)
            const isGestating = p.PEN_NAME.toLowerCase().includes('gestating');
            if (prefix === 'dest' && isGestating && count >= 1) return;

            penSelect.innerHTML += `<option value="${p.PEN_ID}">${p.PEN_NAME} (${count} heads)</option>`;
        });
        
        // Handle case where building has no valid pens for source
        if (penSelect.options.length === 1 && prefix === 'src') {
            penSelect.innerHTML = '<option value="">-- No Occupied Pens --</option>';
            penSelect.disabled = true;
        } else {
            penSelect.disabled = false;
        }
    }

    async function loadAnimals(prefix) {
        const penId = document.getElementById(prefix + '_pen').value;
        const list = document.getElementById(prefix + '_animalList');
        const destCount = document.getElementById('destCount');
        
        if(!penId) {
            list.innerHTML = `<div class="empty-box-msg"><i class="fa-solid fa-arrow-pointer"></i>Select a ${prefix === 'src' ? 'Source' : 'Destination'} Pen first.</div>`;
            if (prefix === 'src') updateCount();
            if (prefix === 'dest') destCount.innerText = '0 Heads';
            return;
        }

        list.innerHTML = '<div class="empty-box-msg"><i class="fa-solid fa-spinner fa-spin"></i>Loading records...</div>';

        const res = await fetch(`${API_URL}?action=get_animals&pen_id=${penId}`);
        const data = await res.json();

        if(data.length === 0) {
            const msg = prefix === 'src' ? 'No active records available.' : 'This pen is currently empty.';
            list.innerHTML = `<div class="empty-box-msg">${msg}</div>`;
            if (prefix === 'dest') destCount.innerText = '0 Heads';
        } else {
            list.innerHTML = '';
            if (prefix === 'dest') destCount.innerText = data.length + ' Heads';

            data.forEach(a => {
                const typeLabel = a.ANIMAL_TYPE_NAME || 'Unknown';
                const breedLabel = a.BREED_NAME || '-';
                
                if (prefix === 'src') {
                    list.innerHTML += `
                        <div class="animal-item">
                            <input type="checkbox" name="animal_ids[]" value="${a.ANIMAL_ID}" id="chk_${a.ANIMAL_ID}" class="item-checkbox" onchange="updateCount()">
                            <label for="chk_${a.ANIMAL_ID}">
                                <div class="tag-block">
                                    <span class="tag-no">${a.TAG_NO}</span>
                                    <span class="tag-desc">${typeLabel} / ${breedLabel}</span>
                                </div>
                            </label>
                        </div>`;
                } else {
                    list.innerHTML += `
                        <div class="animal-item" style="cursor: default;">
                            <div style="flex-grow: 1; display: flex; justify-content: space-between; align-items: center;">
                                <div class="tag-block">
                                    <span class="tag-no">${a.TAG_NO}</span>
                                    <span class="tag-desc">${typeLabel} / ${breedLabel}</span>
                                </div>
                            </div>
                        </div>`;
                }
            });
        }
        if (prefix === 'src') updateCount();
    }

    function updateCount() {
        const checkboxes = document.querySelectorAll('input[name="animal_ids[]"]:checked');
        const count = checkboxes.length;
        document.getElementById('selectedCount').innerText = count;
        document.getElementById('btnTransfer').disabled = (count === 0);
    }

    function selectAll(check) {
        const checkboxes = document.querySelectorAll('input[name="animal_ids[]"]');
        let allChecked = true;
        checkboxes.forEach(cb => { if(!cb.checked) allChecked = false; });
        
        // Toggle behavior if called from button
        const stateToSet = check !== undefined ? check : !allChecked;
        checkboxes.forEach(cb => cb.checked = stateToSet);
        updateCount();
    }

    async function submitTransfer(e) {
        e.preventDefault();
        
        const srcPen = document.getElementById('src_pen').value;
        const destPen = document.getElementById('dest_pen').value;

        if (!srcPen || !destPen) { showToast("Select both Source and Destination Pens.", 'error'); return; }
        if (srcPen == destPen) { showToast("Source and Destination cannot be identical.", 'error'); return; }
        if(!confirm("Confirm transfer for selected animals?")) return;

        const form = document.getElementById('transferForm');
        const formData = new FormData(form);

        const btn = document.getElementById('btnTransfer');
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Processing...';
        btn.disabled = true;

        try {
            const res = await fetch('../process/transferGroupProcess.php', { method: 'POST', body: formData });
            const result = await res.json();

            if(result.success) {
                showToast("Transfer Executed Successfully!");
                loadAnimals('src'); 
                loadAnimals('dest'); 
            } else {
                showToast(result.message, 'error');
            }
        } catch(err) {
            showToast("System Communication Error.", 'error');
        } finally {
            btn.innerHTML = originalText;
            updateCount(); 
        }
    }
</script>

</body>
</html>