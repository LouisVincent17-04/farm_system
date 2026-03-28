<?php
// views/animal_weights.php
ob_start(); 
error_reporting(E_ALL);
ini_set('display_errors', 0); 

$page = "farm";
include '../config/Connection.php';

include '../security/checkAccess.php';
checkAccess('animal_weights');
include '../common/navbar.php';
include '../common/chat_support.php';

// =========================================================
// AJAX HANDLER (Internal API)
// =========================================================
if (isset($_GET['action'])) {
    ob_end_clean(); 
    header('Content-Type: application/json');
    $action = $_GET['action'];

    try {
        if ($action === 'get_buildings' && isset($_GET['loc_id'])) {
            $stmt = $conn->prepare("SELECT BUILDING_ID, BUILDING_NAME FROM buildings WHERE LOCATION_ID = ? ORDER BY BUILDING_NAME");
            $stmt->execute([$_GET['loc_id']]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC)); exit;
        }
        if ($action === 'get_pens' && isset($_GET['bldg_id'])) {
            $stmt = $conn->prepare("SELECT PEN_ID, PEN_NAME FROM pens WHERE BUILDING_ID = ? ORDER BY PEN_NAME");
            $stmt->execute([$_GET['bldg_id']]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC)); exit;
        }
        if ($action === 'get_animals' && isset($_GET['pen_id'])) {
            $sql = "SELECT ANIMAL_ID, TAG_NO, CLASS_ID, WEIGHT_AT_BIRTH, WEANING_WEIGHT, CURRENT_ACTUAL_WEIGHT, SEX, BIRTH_DATE 
                    FROM animal_records 
                    WHERE PEN_ID = :pen_id AND IS_ACTIVE = 1 AND CURRENT_STATUS != 'Sold' ";
            
            $params = [':pen_id' => $_GET['pen_id']];

            if (!empty($_GET['date_from']) && !empty($_GET['date_to'])) {
                $sql .= " AND DATE(BIRTH_DATE) BETWEEN :d_from AND :d_to ";
                $params[':d_from'] = $_GET['date_from'];
                $params[':d_to']   = $_GET['date_to'];
            }

            $sql .= " ORDER BY TAG_NO";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Format dates before sending to frontend
            foreach($results as &$r) {
                $r['FMT_BIRTH_DATE'] = $r['BIRTH_DATE'] ? date('M d, Y', strtotime($r['BIRTH_DATE'])) : 'N/A';
            }

            echo json_encode($results); exit;
        }
    } catch (Exception $e) { echo json_encode([]); exit; }
}

$locs = $conn->query("SELECT * FROM locations ORDER BY LOCATION_NAME")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no"> 
    <title>Update Animal Weights | FarmPro</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=DM+Mono:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" type="text/css" href="https://npmcdn.com/flatpickr/dist/themes/dark.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    <style>
        /* ─── CSS VARIABLES ─── */
        :root {
            --bg-base:        #080f1a;
            --bg-surface:     #0d1829;
            --bg-elevated:    #111f35;
            --bg-hover:       #162540;
            --border:         rgba(255,255,255,0.07);
            --border-active:  rgba(249,115,22,0.5); /* Orange Accent */
            
            --orange:         #f97316;
            --orange-dim:     rgba(249,115,22,0.12);
            --orange-glow:    rgba(249,115,22,0.25);
            --emerald:        #10b981;
            --emerald-dim:    rgba(16,185,129,0.12);
            --red:            #f87171;
            --red-dim:        rgba(248,113,113,0.12);
            --blue:           #3b82f6;
            
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

        /* ─── RESET & BASE ─── */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: var(--font);
            background: var(--bg-base);
            color: var(--text-primary);
            min-height: 100vh;
            padding-bottom: 60px;
            background-image: radial-gradient(ellipse 80% 50% at 50% -20%, rgba(249,115,22,0.06) 0%, transparent 60%);
        }
        .container { max-width: 1560px; margin: 0 auto; padding: 2rem 1.5rem; }

        /* ─── TOP BAR ─── */
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
            font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase;
            color: var(--orange); background: var(--orange-dim); border: 1px solid rgba(249,115,22,0.2);
            padding: 6px 12px; border-radius: 99px;
        }

        /* ─── HEADER ─── */
        .page-header { margin-bottom: 2.5rem; }
        .page-header h1 { font-size: clamp(1.8rem, 4vw, 2.5rem); font-weight: 700; margin: 0 0 0.5rem 0; color: #fff; letter-spacing: -0.02em;}
        .page-header h1 span { background: linear-gradient(135deg, var(--orange), #c2410c); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .page-header p { color: var(--text-secondary); font-size: 0.95rem; margin: 0; }

        /* ─── DESKTOP GRID ─── */
        .main-grid { 
            display: grid; 
            grid-template-columns: 340px 1fr; 
            gap: 1.5rem; 
            align-items: start; 
        }
        
        /* ─── CONTROL PANEL ─── */
        .panel { 
            background: var(--bg-surface); border: 1px solid var(--border); 
            border-radius: var(--radius-xl); padding: 1.5rem; 
            box-shadow: var(--shadow-md); position: sticky; top: 2rem;
        }
        .panel-title { 
            font-size: 1.15rem; font-weight: 700; color: white; margin-bottom: 1.5rem; 
            display: flex; align-items: center; gap: 8px;
        }
        .panel-title i { color: var(--orange); }
        
        .form-group { margin-bottom: 1.25rem; display: flex; flex-direction: column; gap: 6px;}
        .form-label { color: var(--text-secondary); font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; }
        .form-select, .form-input { 
            width: 100%; padding: 12px 14px; background: var(--bg-elevated); 
            border: 1px solid var(--border); color: var(--text-primary); 
            border-radius: var(--radius-md); font-size: 0.95rem; font-family: var(--font); 
            outline: none; transition: var(--transition); box-sizing: border-box;
        }
        .form-select {
            appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2394a3b8' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
            background-repeat: no-repeat; background-position: right 12px center; cursor: pointer;
        }
        .form-select:disabled, .form-input:disabled { opacity: 0.5; cursor: not-allowed; background: rgba(255,255,255,0.02); }
        .form-select:focus, .form-input:focus { border-color: var(--orange); box-shadow: 0 0 0 3px var(--orange-glow); background: var(--bg-hover); }

        .btn-save { 
            width: 100%; padding: 14px; background: var(--orange); color: #000; 
            border: none; border-radius: var(--radius-md); font-weight: 700; font-family: var(--font);
            cursor: pointer; margin-top: 1.5rem; transition: var(--transition); font-size: 1rem; 
            display: flex; align-items: center; justify-content: center; gap: 8px;
        }
        .btn-save:disabled { background: var(--bg-elevated); color: var(--text-muted); cursor: not-allowed; border: 1px solid var(--border);}
        .btn-save:hover:not(:disabled) { transform: translateY(-2px); box-shadow: 0 8px 20px var(--orange-glow); background: #fb923c;}

        /* ─── TABLE AREA ─── */
        .table-area { 
            background: var(--bg-surface); border-radius: var(--radius-xl); 
            border: 1px solid var(--border); overflow: hidden; display: flex; flex-direction: column;
            box-shadow: var(--shadow-md);
        }
        .table-header-block {
            padding: 1.5rem; border-bottom: 1px solid var(--border); display: flex; 
            justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; background: rgba(15,23,42,0.4);
        }
        .table-header-block h2 { margin: 0; font-size: 1.25rem; font-weight: 700; color: #fff; }
        .table-header-block p { margin: 4px 0 0 0; color: var(--text-secondary); font-size: 0.9rem; }
        .count-badge { background: var(--orange-dim); color: var(--orange); padding: 6px 12px; border-radius: 99px; font-weight: 700; font-size: 0.85rem; border: 1px solid rgba(249,115,22,0.2);}

        #table-container {
            max-height: calc(100vh - 250px); 
            overflow-y: auto; overflow-x: auto; 
            -webkit-overflow-scrolling: touch;
        }
        /* Custom Scrollbar */
        #table-container::-webkit-scrollbar { width: 8px; height: 8px; }
        #table-container::-webkit-scrollbar-track { background: var(--bg-surface); }
        #table-container::-webkit-scrollbar-thumb { background: #334155; border-radius: 4px; }
        #table-container::-webkit-scrollbar-thumb:hover { background: #475569; }

        .w-table { width: 100%; border-collapse: collapse; min-width: 900px; } 
        .w-table th { 
            background: var(--bg-elevated); padding: 16px; text-align: left; color: var(--text-muted); 
            font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 1px solid var(--border); 
            position: sticky; top: 0; z-index: 10; font-weight: 700;
        }
        .w-table td { padding: 16px; border-bottom: 1px solid rgba(255,255,255,0.03); vertical-align: middle;}
        .w-table tr:hover { background: rgba(255,255,255,0.02); }

        .tag-info { display: flex; flex-direction: column; gap: 4px; }
        .tag-no { font-family: var(--font-mono); font-weight: 700; font-size: 1.1rem; color: #fff; white-space: nowrap; }
        .animal-meta { font-size: 0.8rem; color: var(--text-muted); white-space: nowrap; display: flex; gap: 6px; align-items: center;}
        .sex-icon { color: var(--blue); }
        .sex-icon.female { color: var(--pink); }

        .date-val { font-family: var(--font-mono); color: var(--text-primary); font-size: 0.95rem; }

        /* Weight Inputs */
        .weight-input { 
            background: var(--bg-elevated); border: 1px solid var(--border); color: #fff; 
            padding: 10px 12px; border-radius: 8px; width: 110px; text-align: right; 
            font-family: var(--font-mono); font-size: 1rem; font-weight: 700; transition: var(--transition);
        }
        .weight-input:focus { border-color: var(--orange); outline: none; background: var(--bg-hover); box-shadow: 0 0 0 3px var(--orange-glow);}
        .weight-input::placeholder { color: var(--text-muted); font-weight: 400; }
        .weight-input:disabled { background: rgba(255,255,255,0.02); color: var(--text-muted); cursor: not-allowed; border-color: transparent; opacity: 1;}

        /* Changes visualizer */
        .weight-input.changed { border-color: var(--emerald); background: var(--emerald-dim); }
        .diff-tag { display: block; font-size: 0.75rem; font-weight: 700; font-family: var(--font-mono); white-space: nowrap; margin-top: 6px; text-align: right; width: 110px;}
        .diff-pos { color: var(--emerald); }
        .diff-neg { color: var(--red); }
        
        .empty-state-msg { text-align: center; padding: 4rem 2rem; color: var(--text-muted); font-style: italic; }

        /* Toast Notifications */
        #toastContainer { position: fixed; top: 20px; right: 20px; z-index: 9999; display: flex; flex-direction: column; gap: 10px; }
        .toast {
            background: var(--bg-surface); border: 1px solid var(--border); color: #fff;
            padding: 1rem 1.5rem; border-radius: var(--radius-md); box-shadow: 0 10px 25px rgba(0,0,0,0.5);
            font-size: 0.9rem; font-weight: 600; animation: slideIn 0.3s ease-out; display: flex; align-items: center; gap: 8px;
        }
        @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }

        /* ─── MOBILE RESPONSIVENESS ─── */
        @media (max-width: 1024px) {
            .main-grid { grid-template-columns: 1fr; gap: 1.5rem; }
            .panel { position: relative; top: 0; }
        }
        @media (max-width: 768px) {
            .container { padding: 1rem; }
            .w-table th, .w-table td { padding: 12px; }
            #table-container { max-height: none; }
        }
    </style>
</head>
<body>

<div id="toastContainer"></div>

<div class="container">
    
    <div class="top-bar">
        <a href="farm_dashboard.php" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back to Farm Dashboard
        </a>
        <span class="page-badge"><i class="fa-solid fa-weight-scale"></i> Growth Metrics</span>
    </div>

    <div class="page-header">
        <h1>Animal <span>Weights</span></h1>
        <p>Record and track growth milestones (Birth, Weaning, Current Weight) for active animals.</p>
    </div>

    <div class="main-grid">
        
        <div class="panel">
            <div class="panel-title"><i class="fa-solid fa-filter"></i> Target Selection</div>
            
            <div class="form-group">
                <label class="form-label">Location</label>
                <select id="loc_id" class="form-select" onchange="loadBuildings()">
                    <option value="">-- Select --</option>
                    <?php foreach($locs as $l): echo "<option value='{$l['LOCATION_ID']}'>".htmlspecialchars($l['LOCATION_NAME'])."</option>"; endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label class="form-label">Building</label>
                <select id="bldg_id" class="form-select" onchange="loadPens()" disabled><option value="">-- Select Location First --</option></select>
            </div>
            
            <div class="form-group">
                <label class="form-label">Pen</label>
                <select id="pen_id" class="form-select" onchange="loadAnimals()" disabled><option value="">-- Select Building First --</option></select>
            </div>

            <div style="border-top: 1px solid var(--border); margin: 1.5rem 0 1.25rem 0;"></div>

            <div class="form-group">
                <label class="form-label">Farrowing Date Range (Optional)</label>
                <div style="display: flex; gap: 8px;">
                    <input type="text" id="date_from" class="form-input date-picker" placeholder="Start Date" onchange="if(document.getElementById('pen_id').value) loadAnimals()">
                    <input type="text" id="date_to" class="form-input date-picker" placeholder="End Date" onchange="if(document.getElementById('pen_id').value) loadAnimals()">
                </div>
            </div>
            
            <button class="btn-save" id="btn_save" onclick="saveWeights()" disabled>
                <i class="fa-solid fa-floppy-disk"></i> Commit Weights
            </button>
        </div>

        <div class="table-area">
            <div class="table-header-block">
                <div>
                    <h2>Weight Entry Ledger</h2>
                    <p>Review and input actual weights in kilograms (kg).</p>
                </div>
                <div id="count_display" class="count-badge"><i class="fa-solid fa-paw"></i> 0 Animals</div>
            </div>
            
            <div id="table-container">
                <div class="empty-state-msg">
                    <i class="fa-solid fa-arrow-left" style="font-size: 2rem; display: block; margin-bottom: 1rem; opacity: 0.5;"></i>
                    Select a Location, Building, and Pen to load the animal list.
                </div>
            </div>
        </div>

    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        flatpickr(".date-picker", {
            dateFormat: "Y-m-d", 
            altInput: true,      
            altFormat: "M j, Y",  
            allowInput: true
        });
        
        // Auto-select location if staff
        const USER_LOCATION = <?php echo json_encode($USER_LOCATION_); ?>;
        if(USER_LOCATION && USER_LOCATION != 1000) {
            const locSelect = document.getElementById('loc_id');
            locSelect.value = USER_LOCATION;
            locSelect.style.pointerEvents = 'none';
            locSelect.style.opacity = '0.7';
            loadBuildings();
        }
    });

    const API_URL = window.location.pathname.split("/").pop();

    async function fetchJson(params) {
        try {
            const res = await fetch(`${API_URL}${params}`);
            return await res.json();
        } catch(e) { return []; }
    }

    function showToast(msg, type = 'success') {
        const t = document.createElement('div');
        t.className = 'toast';
        t.style.borderLeft = `4px solid ${type === 'error' ? 'var(--red)' : 'var(--emerald)'}`;
        t.innerHTML = `${type === 'error' ? '<i class="fa-solid fa-xmark"></i>' : '<i class="fa-solid fa-check"></i>'} ${msg}`;
        document.getElementById('toastContainer').appendChild(t);
        setTimeout(() => t.remove(), 3500);
    }

    // --- Dropdown Logic ---
    async function loadBuildings() {
        const id = document.getElementById('loc_id').value;
        const target = document.getElementById('bldg_id');
        resetSelect(target, '-- Select Location First --'); 
        resetSelect(document.getElementById('pen_id'), '-- Select Building First --');
        if(!id) return;

        target.innerHTML = '<option value="">Loading...</option>';
        const data = await fetchJson(`?action=get_buildings&loc_id=${id}`);
        target.innerHTML = '<option value="">-- Choose Building --</option>';
        populateSelect(target, data, 'BUILDING_ID', 'BUILDING_NAME');
        target.disabled = false;
    }

    async function loadPens() {
        const id = document.getElementById('bldg_id').value;
        const target = document.getElementById('pen_id');
        resetSelect(target, '-- Select Building First --');
        if(!id) return;

        target.innerHTML = '<option value="">Loading...</option>';
        const data = await fetchJson(`?action=get_pens&bldg_id=${id}`);
        target.innerHTML = '<option value="">-- Choose Pen --</option>';
        populateSelect(target, data, 'PEN_ID', 'PEN_NAME');
        target.disabled = false;
    }

    function resetSelect(el, msg) { el.innerHTML = `<option value="">${msg}</option>`; el.disabled = true; }
    function populateSelect(el, data, valKey, txtKey) {
        data.forEach(item => {
            const opt = document.createElement('option');
            opt.value = item[valKey];
            opt.text = item[txtKey];
            el.appendChild(opt);
        });
    }

    // --- Table Logic ---
    async function loadAnimals() {
        const id = document.getElementById('pen_id').value;
        const dateFrom = document.getElementById('date_from').value;
        const dateTo = document.getElementById('date_to').value;

        const container = document.getElementById('table-container');
        const saveBtn = document.getElementById('btn_save');
        const countDisplay = document.getElementById('count_display');
        
        if(!id) {
            container.innerHTML = '<div class="empty-state-msg"><i class="fa-solid fa-arrow-left" style="font-size: 2rem; display: block; margin-bottom: 1rem; opacity: 0.5;"></i>Select a Pen to load animals.</div>';
            saveBtn.disabled = true;
            countDisplay.innerHTML = '<i class="fa-solid fa-paw"></i> 0 Animals';
            return;
        }

        container.innerHTML = '<div class="empty-state-msg"><i class="fa-solid fa-spinner fa-spin me-2"></i> Loading animals...</div>';
        
        const animals = await fetchJson(`?action=get_animals&pen_id=${id}&date_from=${dateFrom}&date_to=${dateTo}`);
        
        if(animals.length === 0) {
            container.innerHTML = '<div class="empty-state-msg" style="color:var(--red);"><i class="fa-solid fa-ghost" style="font-size: 2rem; display: block; margin-bottom: 1rem; opacity: 0.5;"></i>No active animals found matching filters.</div>';
            saveBtn.disabled = true;
            countDisplay.innerHTML = '<i class="fa-solid fa-paw"></i> 0 Animals';
            return;
        }

        countDisplay.innerHTML = `<i class="fa-solid fa-paw"></i> ${animals.length} Animals`;

        let html = `
            <form id="weightForm">
            <table class="w-table">
                <thead>
                    <tr>
                        <th style="padding-left: 2rem;">Tag / Info</th>
                        <th>Farrowing Date</th>
                        <th>Birth Wt</th>
                        <th>Weaning Wt</th>
                        <th>Current Wt</th>
                    </tr>
                </thead>
                <tbody>
        `;

        animals.forEach(a => {
            const current = parseFloat(a.CURRENT_ACTUAL_WEIGHT) || 0;
            const birth = parseFloat(a.WEIGHT_AT_BIRTH) || 0;
            const weaning = parseFloat(a.WEANING_WEIGHT) || 0;
            const classId = parseInt(a.CLASS_ID) || 0; 
            
            const sexIcon = a.SEX === 'M' ? '<i class="fa-solid fa-mars sex-icon"></i>' : (a.SEX === 'F' ? '<i class="fa-solid fa-venus sex-icon female"></i>' : '');
            
            // Logic to disable weaning weight
            const isWeaningDisabled = classId <= 1;
            const weaningAttr = isWeaningDisabled ? 'disabled title="Not applicable for Class 1 or below"' : '';
            const weaningPlaceholder = isWeaningDisabled ? 'N/A' : '0.00';
            
            html += `
                <tr>
                    <td style="padding-left: 2rem;">
                        <div class="tag-info">
                            <span class="tag-no">${a.TAG_NO}</span>
                            <span class="animal-meta">${sexIcon} SysID: ${a.ANIMAL_ID}</span>
                        </div>
                    </td>
                    <td>
                        <span class="date-val">${a.FMT_BIRTH_DATE}</span>
                    </td>
                    <td>
                        <div>
                            <input type="number" step="0.01" min="0" class="weight-input" 
                                   name="birth_weights[${a.ANIMAL_ID}]" 
                                   value="${birth > 0 ? birth.toFixed(2) : ''}" placeholder="0.00"
                                   oninput="handleInput(this, ${birth}); syncCurrentWeight(this, ${birth}, ${current}, '${a.ANIMAL_ID}')">
                            <span class="diff-tag"></span>
                        </div>
                    </td>
                    <td>
                        <div>
                            <input type="number" step="0.01" min="0" class="weight-input" 
                                   name="weaning_weights[${a.ANIMAL_ID}]" 
                                   value="${weaning > 0 ? weaning.toFixed(2) : ''}" placeholder="${weaningPlaceholder}"
                                   oninput="handleInput(this, ${weaning})" ${weaningAttr}>
                            <span class="diff-tag"></span>
                        </div>
                    </td>
                    <td>
                        <div>
                            <input type="number" step="0.01" min="0" class="weight-input" 
                                   name="current_weights[${a.ANIMAL_ID}]" 
                                   value="${current > 0 ? current.toFixed(2) : ''}" placeholder="0.00"
                                   oninput="handleInput(this, ${current})">
                            <span class="diff-tag"></span>
                        </div>
                    </td>
                </tr>
            `;
        });

        html += `</tbody></table></form>`;
        container.innerHTML = html;
        saveBtn.disabled = false;
    }

    // --- Interaction Logic ---
    function handleInput(input, oldVal) {
        const newVal = parseFloat(input.value);
        const diffSpan = input.nextElementSibling;

        if (!isNaN(newVal) && newVal > 0) {
            if(newVal !== oldVal) {
                input.classList.add('changed');
                if(oldVal > 0) {
                    const diff = newVal - oldVal;
                    const sign = diff > 0 ? '+' : '';
                    const colorClass = diff > 0 ? 'diff-pos' : (diff < 0 ? 'diff-neg' : '');
                    if(diff !== 0) {
                        diffSpan.className = `diff-tag ${colorClass}`;
                        diffSpan.innerHTML = `${sign}${diff.toFixed(2)} <i class="fa-solid ${diff > 0 ? 'fa-arrow-trend-up' : 'fa-arrow-trend-down'}"></i>`;
                    } else { diffSpan.innerText = ''; }
                } else {
                    diffSpan.className = `diff-tag diff-pos`;
                    diffSpan.innerHTML = 'New <i class="fa-solid fa-sparkles"></i>';
                }
            } else {
                input.classList.remove('changed');
                diffSpan.innerText = '';
            }
        } else {
            input.classList.remove('changed');
            diffSpan.innerText = '';
        }
    }

    // Live Syncs the current weight on every keystroke if birth weight is being initialized
    function syncCurrentWeight(birthInput, oldBirth, oldCurrent, animalId) {
        if (Number(oldBirth) === 0) {
            const currentInput = document.getElementsByName('current_weights[' + animalId + ']')[0];
            if (currentInput) {
                currentInput.value = birthInput.value;
                handleInput(currentInput, Number(oldCurrent));
            }
        }
    }

    function saveWeights() {
        const form = document.getElementById('weightForm');
        if(!form) return;

        const formData = new FormData(form);
        const btn = document.getElementById('btn_save');

        let hasChanges = false;
        for(let pair of formData.entries()) { if(pair[1] !== "") hasChanges = true; }

        if(!hasChanges) { showToast("Please enter at least one weight.", "error"); return; }
        if(!confirm("Update records with these weights?")) return;

        const ogText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i> Committing...';

        fetch('../process/updateWeights.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if(data.success) {
                showToast(data.message, "success");
                loadAnimals(); // Refresh data
            } else {
                showToast(data.message, "error");
                btn.disabled = false;
                btn.innerHTML = ogText;
            }
        })
        .catch(err => {
            console.error(err);
            showToast("System Connection Error.", "error");
            btn.disabled = false;
            btn.innerHTML = ogText;
        });
    }
</script>

</body>
</html>