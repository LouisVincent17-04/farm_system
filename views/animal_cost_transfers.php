<?php
// views/animal_cost_transfers.php
$page = "farm";
include '../config/Connection.php';
include '../security/checkAccess.php';
checkAccess('cost_transfer');
include '../common/navbar.php';
include '../common/chat_support.php';
include '../functions/getUsersLocation.php';

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
    <title>Cost Transfers | FarmPro</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=DM+Mono:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />

    <style>
        /* ─── CSS VARIABLES ─── */
        :root {
            --bg-base:        #080f1a;
            --bg-surface:     #0d1829;
            --bg-elevated:    #111f35;
            --bg-hover:       #162540;
            --border:         rgba(255,255,255,0.07);
            --border-active:  rgba(20,184,166,0.5); /* Teal Accent */
            
            --teal:           #14b8a6;
            --teal-dim:       rgba(20,184,166,0.12);
            --teal-glow:      rgba(20,184,166,0.25);
            
            --pink:           #ec4899;
            --pink-dim:       rgba(236,72,153,0.12);
            --blue:           #3b82f6;
            --blue-dim:       rgba(59,130,246,0.12);
            --amber:          #f59e0b;
            --emerald:        #10b981;
            --red:            #f87171;
            
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
            font-family: var(--font); background: var(--bg-base); color: var(--text-primary);
            min-height: 100vh; padding-bottom: 60px;
            background-image: radial-gradient(ellipse 80% 50% at 50% -20%, rgba(20,184,166,0.06) 0%, transparent 60%);
        }
        .container { max-width: 1400px; margin: 0 auto; padding: 2rem 1.5rem; }

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
            color: var(--teal); background: var(--teal-dim); border: 1px solid rgba(20,184,166,0.2);
            padding: 6px 12px; border-radius: 99px;
        }

        /* ─── HEADER ─── */
        .page-header { margin-bottom: 2.5rem; text-align: center; }
        .page-header h1 { font-size: clamp(1.8rem, 4vw, 2.5rem); font-weight: 700; margin: 0 0 0.5rem 0; color: #fff; letter-spacing: -0.02em;}
        .page-header h1 span { background: linear-gradient(135deg, var(--teal), #0f766e); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }

        /* ─── GRID LAYOUT ─── */
        .transfer-grid { display: grid; grid-template-columns: 1fr 1.25fr; gap: 2rem; align-items: start; }
        
        .card { 
            background: var(--bg-surface); border: 1px solid var(--border); 
            border-radius: var(--radius-xl); padding: 2rem; box-shadow: var(--shadow-md);
            position: relative; overflow: hidden;
        }
        .card-left::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px; background: linear-gradient(90deg, var(--teal), #0f766e); }
        .card-right::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px; background: linear-gradient(90deg, var(--blue), #1e3a8a); }

        .card h3 { margin: 0 0 1.5rem 0; font-size: 1.25rem; font-weight: 700; display: flex; align-items: center; gap: 8px;}
        .card-left h3 { color: var(--teal); }
        .card-right h3 { color: var(--blue); }

        /* ─── FORM ELEMENTS ─── */
        .section-label { font-size: 0.85rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; display: flex; align-items: center; gap: 6px; margin-bottom: 8px;}
        .lbl-sow { color: var(--pink); }
        .lbl-boar { color: var(--blue); margin-top: 1.5rem;}
        
        .filters { display: flex; gap: 10px; margin-bottom: 12px; flex-wrap: wrap; }
        
        .form-select, .cost-input {
            width: 100%; padding: 12px 14px; background: var(--bg-elevated); border: 1px solid var(--border);
            border-radius: var(--radius-md); color: var(--text-primary); font-size: 0.95rem; font-family: var(--font);
            outline: none; transition: all var(--transition); box-sizing: border-box;
        }
        .form-select {
            appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2394a3b8' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
            background-repeat: no-repeat; background-position: right 12px center; cursor: pointer; flex: 1; min-width: 120px;
        }
        .form-select:focus { border-color: var(--teal); box-shadow: 0 0 0 3px var(--teal-glow); background: var(--bg-hover); }
        .form-select:disabled { opacity: 0.5; cursor: not-allowed; background: rgba(255,255,255,0.02); }
        
        .parent-select { margin-bottom: 1rem; border-color: rgba(255,255,255,0.2);}

        /* ─── COST INFO BOXES ─── */
        .parent-cost-info {
            background: var(--bg-elevated); border: 1px dashed var(--border); border-radius: var(--radius-md);
            padding: 1rem; margin-bottom: 0; font-size: 0.9rem; color: var(--text-secondary);
            display: none; flex-direction: column; gap: 8px;
        }
        .cost-row { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid rgba(255,255,255,0.03); padding-bottom: 6px; }
        .cost-row:last-child { border-bottom: none; padding-bottom: 0; }
        .cost-row strong { color: var(--text-primary); font-family: var(--font-mono); font-weight: 700;}
        .cost-row .transferred { color: var(--red); }

        .error-msg { color: var(--red); font-size: 0.85rem; margin-top: -8px; margin-bottom: 12px; display: none; background: var(--red-dim); padding: 8px; border-radius: 6px; border: 1px solid rgba(239,68,68,0.2);}

        /* ─── RIGHT SIDE (PIGLETS & CALCS) ─── */
        .tag-selection-area {
            min-height: 120px; border: 1px dashed var(--border); padding: 1.25rem; display: flex; flex-wrap: wrap; gap: 10px;
            border-radius: var(--radius-md); background: var(--bg-elevated); align-content: flex-start;
        }
        .tag-pill {
            background: var(--blue-dim); border: 1px solid rgba(59,130,246,0.3); color: var(--blue);
            padding: 6px 12px 6px 16px; border-radius: 99px; font-size: 0.9rem; display: flex; align-items: center; gap: 10px;
            font-family: var(--font-mono); font-weight: 700; transition: var(--transition);
        }
        .tag-pill:hover { background: rgba(59,130,246,0.2); }
        .tag-remove {
            cursor: pointer; background: rgba(59,130,246,0.2); border-radius: 50%; width: 22px; height: 22px;
            display: flex; align-items: center; justify-content: center; flex-shrink: 0; transition: var(--transition);
        }
        .tag-remove:hover { background: var(--red); color: white; }

        .summary-stats {
            margin-top: 1.5rem; background: var(--bg-elevated); padding: 1rem 1.5rem; border-radius: var(--radius-md);
            display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; border: 1px solid var(--border);
        }
        .stat-group { display: flex; flex-direction: column; gap: 4px; }
        .stat-group .lbl { font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700; letter-spacing: 0.05em; }
        .stat-group .val { font-size: 1.25rem; font-weight: 700; color: #fff; font-family: var(--font-mono); line-height: 1;}

        /* ─── COST DISPLAY & INPUTS ─── */
        .cost-display {
            text-align: center; padding: 1.5rem; border: 1px solid rgba(20,184,166,0.5); border-radius: var(--radius-md);
            background: var(--teal-dim); margin-top: 2rem; box-shadow: inset 0 0 20px rgba(20,184,166,0.1);
        }
        .cost-display .lbl { font-size: 0.85rem; color: var(--teal); text-transform: uppercase; font-weight: 700; letter-spacing: 0.05em; margin-bottom: 0.5rem;}
        .cost-total { font-size: 2.5rem; font-weight: 800; color: #fff; font-family: var(--font-mono); line-height: 1; text-shadow: 0 2px 10px rgba(0,0,0,0.5);}
        
        .breakdown-box { margin-top: 1rem; background: var(--bg-elevated); padding: 1.5rem; border-radius: var(--radius-md); border: 1px solid var(--border);}
        .breakdown-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; flex-wrap: wrap; gap: 10px; }
        .breakdown-row:last-child { margin-bottom: 0; }
        .breakdown-lbl { color: var(--text-secondary); font-weight: 600; font-size: 0.95rem; display: flex; flex-direction: column;}
        .breakdown-lbl small { font-size: 0.75rem; color: var(--text-muted); font-family: var(--font-mono); margin-top: 4px;}
        
        .cost-input { width: 140px; text-align: right; font-family: var(--font-mono); font-weight: 700; color: var(--amber); border-color: rgba(245,158,11,0.3);}
        .cost-input:focus { border-color: var(--amber); box-shadow: 0 0 0 3px var(--amber-glow); }
        .cost-input.error { border-color: var(--red); color: var(--red); box-shadow: 0 0 0 3px var(--red-glow); background: var(--red-dim);}

        .btn-transfer {
            width: 100%; padding: 16px; background: var(--teal); color: #000; font-weight: 700; font-family: var(--font);
            border: none; border-radius: var(--radius-md); cursor: pointer; font-size: 1.05rem; transition: var(--transition);
            margin-top: 1.5rem; display: flex; align-items: center; justify-content: center; gap: 10px;
        }
        .btn-transfer:hover:not(:disabled) { background: #34d399; transform: translateY(-2px); box-shadow: 0 8px 25px var(--teal-glow); }
        .btn-transfer:disabled { background: var(--bg-elevated); color: var(--text-muted); cursor: not-allowed; border: 1px solid var(--border); box-shadow: none; transform: none;}

        /* Toast Notifications */
        #toastContainer { position: fixed; top: 20px; right: 20px; z-index: 9999; display: flex; flex-direction: column; gap: 10px; }
        .toast {
            background: var(--bg-surface); border: 1px solid var(--border); color: #fff;
            padding: 1rem 1.5rem; border-radius: var(--radius-md); box-shadow: 0 10px 25px rgba(0,0,0,0.5);
            font-size: 0.9rem; font-weight: 600; animation: slideIn 0.3s ease-out; display: flex; align-items: center; gap: 8px;
        }
        @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }

        /* ─── RESPONSIVE ─── */
        @media (max-width: 1024px) {
            .transfer-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 768px) {
            .container { padding: 1rem; }
            .card { padding: 1.5rem; }
            .filters { flex-direction: column; }
            .breakdown-row { flex-direction: column; align-items: flex-start; }
            .cost-input { width: 100%; }
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
        <span class="page-badge"><i class="fa-solid fa-money-bill-transfer"></i> Accounting</span>
    </div>

    <div class="page-header">
        <h1>Cost <span>Allocation</span></h1>
    </div>

    <div class="transfer-grid">

        <div class="card card-left">
            <h3><i class="fa-solid fa-people-arrows"></i> 1. Parent Selection</h3>

            <span class="section-label lbl-sow"><i class="fa-solid fa-venus"></i> Dam (Sow)</span>
            <div class="filters">
                <select id="sowLoc" class="form-select" onchange="loadBuildings('sow')" <?php echo ($USER_LOCATION_ != 1000) ? 'disabled' : ''; ?>>
                    <?php if ($USER_LOCATION_ == 1000): ?>
                        <option value="">-- Location --</option>
                    <?php endif; ?>
                    <?php foreach($locations as $l): ?>
                        <option value="<?= $l['LOCATION_ID'] ?>" <?php echo ($USER_LOCATION_ != 1000 && $l['LOCATION_ID'] == $USER_LOCATION_) ? 'selected' : ''; ?>>
                            <?= htmlspecialchars($l['LOCATION_NAME']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select id="sowBld" class="form-select" disabled onchange="loadPens('sow')">
                    <option value="">-- Bldg --</option>
                </select>
                <select id="sowPen" class="form-select" disabled onchange="loadAnimals('sow')">
                    <option value="">-- Pen --</option>
                </select>
            </div>
            <select id="sowSelect" class="form-select parent-select" disabled onchange="handleParentSelection()">
                <option value="">-- Select Sow --</option>
            </select>
            <div id="sowError" class="error-msg"><i class="fa-solid fa-triangle-exclamation"></i> Failed to load sow data.</div>

            <div id="sowCostInfo" class="parent-cost-info">
                <div class="cost-row"><span>Acquisition Cost:</span> <strong id="sowAcqCost">₱ 0.00</strong></div>
                <div class="cost-row"><span>Operational Cost:</span> <strong id="sowOpCost">₱ 0.00</strong></div>
                <div class="cost-row"><span class="transferred">Already Transferred:</span> <strong id="sowTransferredCost" class="transferred">- ₱ 0.00</strong></div>
            </div>

            <span class="section-label lbl-boar"><i class="fa-solid fa-mars"></i> Sire (Boar)</span>
            <div class="filters">
                <select id="boarLoc" class="form-select" onchange="loadBuildings('boar')" <?php echo ($USER_LOCATION_ != 1000) ? 'disabled' : ''; ?>>
                    <?php if ($USER_LOCATION_ == 1000): ?>
                        <option value="">-- Location --</option>
                    <?php endif; ?>
                    <?php foreach($locations as $l): ?>
                        <option value="<?= $l['LOCATION_ID'] ?>" <?php echo ($USER_LOCATION_ != 1000 && $l['LOCATION_ID'] == $USER_LOCATION_) ? 'selected' : ''; ?>>
                            <?= htmlspecialchars($l['LOCATION_NAME']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select id="boarBld" class="form-select" disabled onchange="loadPens('boar')">
                    <option value="">-- Bldg --</option>
                </select>
                <select id="boarPen" class="form-select" disabled onchange="loadAnimals('boar')">
                    <option value="">-- Pen --</option>
                </select>
            </div>
            <select id="boarSelect" class="form-select parent-select" disabled onchange="handleParentSelection()">
                <option value="">-- Select Boar --</option>
            </select>
            <div id="boarError" class="error-msg"><i class="fa-solid fa-triangle-exclamation"></i> Failed to load boar data.</div>

            <div id="boarCostInfo" class="parent-cost-info">
                <div class="cost-row"><span>Acquisition Cost:</span> <strong id="boarAcqCost">₱ 0.00</strong></div>
                <div class="cost-row"><span>Operational Cost:</span> <strong id="boarOpCost">₱ 0.00</strong></div>
                <div class="cost-row"><span class="transferred">Already Transferred:</span> <strong id="boarTransferredCost" class="transferred">- ₱ 0.00</strong></div>
            </div>

            <div id="costDetails" style="opacity:0.3; transition: opacity 0.3s ease; pointer-events:none;">
                <div class="cost-display">
                    <div class="lbl">Net Transferable Value</div>
                    <div class="cost-total" id="totalDisplay">₱ 0.00</div>
                </div>
                <div class="breakdown-box">
                    <div class="breakdown-row">
                        <div class="breakdown-lbl">
                            Dam (Sow) Allocation 
                            <small>Max Value: <span id="sowMax">0</span></small>
                        </div>
                        <input type="number" id="sowCostInput" class="cost-input" min="0" step="0.01" value="0" oninput="validateInput('sow')">
                    </div>
                    <div class="breakdown-row">
                        <div class="breakdown-lbl">
                            Sire (Boar) Allocation 
                            <small>Max Value: <span id="boarMax">0</span></small>
                        </div>
                        <input type="number" id="boarCostInput" class="cost-input" min="0" step="0.01" value="0" oninput="validateInput('boar')">
                    </div>
                </div>
            </div>
        </div>

        <div class="card card-right">
            <h3><i class="fa-solid fa-piggy-bank"></i> 2. Target Offspring</h3>
            
            <div id="pigletBox" class="tag-selection-area">
                <div style="width:100%; text-align:center; color:var(--text-muted); padding-top:30px; font-style:italic;">
                    <i class="fa-solid fa-arrow-left me-2" style="display:block; font-size:2rem; margin-bottom:1rem; opacity:0.5;"></i>
                    Select a Dam (Sow) first to load eligible offspring.
                </div>
            </div>
            
            <div class="summary-stats">
                <div class="stat-group">
                    <span class="lbl">Eligible Piglets</span>
                    <span class="val" id="countPiglets">0</span>
                </div>
                <div class="stat-group" style="text-align: right;">
                    <span class="lbl">Allocated Cost / Head</span>
                    <span class="val" id="costPerHead" style="color:var(--amber);">₱ 0.00</span>
                </div>
            </div>
            
            <button id="btnTransfer" class="btn-transfer" onclick="submitTransfer()" disabled>
                <i class="fa-solid fa-file-invoice-dollar"></i> Execute Transfer
            </button>
        </div>

    </div>
</div>

<script>
    let selectedPiglets = new Map();
    let limits = { sow: 0, boar: 0 };
    const USER_LOC = <?php echo json_encode($USER_LOCATION_); ?>;
    const API = '../process/getCostData.php';

    document.addEventListener('DOMContentLoaded', () => {
        if (USER_LOC != 1000) {
            loadBuildings('sow');
            loadBuildings('boar');
        }
    });

    function showToast(msg, type = 'success') {
        const t = document.createElement('div');
        t.className = 'toast';
        t.style.borderLeft = `4px solid ${type === 'error' ? 'var(--red)' : 'var(--teal)'}`;
        t.innerHTML = `${type === 'error' ? '<i class="fa-solid fa-xmark"></i>' : '<i class="fa-solid fa-check"></i>'} ${msg}`;
        document.getElementById('toastContainer').appendChild(t);
        setTimeout(() => t.remove(), 3500);
    }

    // Safe fetch — returns null on failure instead of silently returning []
    async function fetchJSON(url) {
        try {
            const res = await fetch(url);
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            const raw = await res.text();
            return JSON.parse(raw);
        } catch (e) {
            console.error('fetchJSON error:', e, 'URL:', url);
            return null;
        }
    }

    function fmt(n) {
        return '₱ ' + parseFloat(n || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    // --- DROPDOWN LOADERS ---

    async function loadBuildings(t) {
        const locId = document.getElementById(t + 'Loc').value;
        const bldEl = document.getElementById(t + 'Bld');
        const penEl = document.getElementById(t + 'Pen');
        const selEl = document.getElementById(t + 'Select');

        bldEl.innerHTML = '<option value="">Loading...</option>';
        bldEl.disabled = true;
        penEl.innerHTML = '<option value="">-- Pen --</option>';
        penEl.disabled = true;
        selEl.innerHTML = `<option value="">-- Select ${t === 'sow' ? 'Sow' : 'Boar'} --</option>`;
        selEl.disabled = true;

        if (!locId) {
            bldEl.innerHTML = '<option value="">-- Bldg --</option>';
            return;
        }

        const data = await fetchJSON(`${API}?action=get_buildings&loc_id=${locId}`);
        bldEl.innerHTML = '<option value="">-- Bldg --</option>';
        if (data && data.length) {
            data.forEach(i => bldEl.innerHTML += `<option value="${i.BUILDING_ID}">${i.BUILDING_NAME}</option>`);
            bldEl.disabled = false;
        }
    }

    async function loadPens(t) {
        const bldId = document.getElementById(t + 'Bld').value;
        const penEl = document.getElementById(t + 'Pen');
        const selEl = document.getElementById(t + 'Select');

        penEl.innerHTML = '<option value="">Loading...</option>';
        penEl.disabled = true;
        selEl.innerHTML = `<option value="">-- Select ${t === 'sow' ? 'Sow' : 'Boar'} --</option>`;
        selEl.disabled = true;

        if (!bldId) {
            penEl.innerHTML = '<option value="">-- Pen --</option>';
            return;
        }

        const data = await fetchJSON(`${API}?action=get_pens&bld_id=${bldId}`);
        penEl.innerHTML = '<option value="">-- Pen --</option>';
        if (data && data.length) {
            data.forEach(i => penEl.innerHTML += `<option value="${i.PEN_ID}">${i.PEN_NAME}</option>`);
            penEl.disabled = false;
        }
    }

    async function loadAnimals(t) {
        const penId = document.getElementById(t + 'Pen').value;
        const selEl = document.getElementById(t + 'Select');

        selEl.innerHTML = '<option value="">Loading...</option>';
        selEl.disabled = true;

        if (!penId) {
            selEl.innerHTML = `<option value="">-- Select ${t === 'sow' ? 'Sow' : 'Boar'} --</option>`;
            return;
        }

        const action = t === 'sow' ? 'get_sows_in_pen' : 'get_boars_in_pen';
        const data = await fetchJSON(`${API}?action=${action}&pen_id=${penId}`);
        selEl.innerHTML = `<option value="">-- Select ${t === 'sow' ? 'Sow' : 'Boar'} --</option>`;
        if (data && data.length) {
            data.forEach(i => selEl.innerHTML += `<option value="${i.ANIMAL_ID}">${i.TAG_NO}</option>`);
        } else if (!data) {
            selEl.innerHTML = `<option value="">-- Error loading --</option>`;
        }
        selEl.disabled = false;
    }

    // --- PARENT SELECTION ---

    async function handleParentSelection() {
        const sowId  = document.getElementById('sowSelect').value;
        const boarId = document.getElementById('boarSelect').value;

        const costPanel = document.getElementById('costDetails');
        
        document.getElementById('sowError').style.display  = 'none';
        document.getElementById('boarError').style.display = 'none';

        if (sowId || boarId) {
            costPanel.style.opacity = '1';
            costPanel.style.pointerEvents = 'auto';
        } else {
            costPanel.style.opacity = '0.3';
            costPanel.style.pointerEvents = 'none';
        }

        if (sowId) {
            const data = await fetchJSON(`${API}?action=get_sow_net_worth&animal_id=${sowId}`);

            if (!data || !data.success) {
                document.getElementById('sowError').style.display = 'block';
            } else {
                limits.sow = data.total;
                document.getElementById('sowMax').innerText             = parseFloat(data.total).toLocaleString();
                document.getElementById('sowCostInput').value          = parseFloat(data.total).toFixed(2);
                document.getElementById('sowAcqCost').innerText        = fmt(data.acquisition_cost);
                document.getElementById('sowOpCost').innerText         = fmt(data.operation_cost);
                document.getElementById('sowTransferredCost').innerText = '- ' + fmt(data.transferred_cost);
                document.getElementById('sowCostInfo').style.display   = 'flex';
            }

            loadOffspring(sowId);
        } else {
            // Reset sow panel
            limits.sow = 0;
            document.getElementById('sowCostInfo').style.display = 'none';
            document.getElementById('sowCostInput').value = '0';
            document.getElementById('sowMax').innerText = '0';
            document.getElementById('pigletBox').innerHTML =
                '<div style="width:100%; text-align:center; color:var(--text-muted); padding-top:30px; font-style:italic;"><i class="fa-solid fa-arrow-left me-2" style="display:block; font-size:2rem; margin-bottom:1rem; opacity:0.5;"></i>Select a Dam (Sow) first to load eligible offspring.</div>';
            selectedPiglets.clear();
        }

        if (boarId) {
            const data = await fetchJSON(`${API}?action=get_sow_net_worth&animal_id=${boarId}`);

            if (!data || !data.success) {
                document.getElementById('boarError').style.display = 'block';
            } else {
                limits.boar = data.total;
                document.getElementById('boarMax').innerText              = parseFloat(data.total).toLocaleString();
                document.getElementById('boarCostInput').value           = parseFloat(data.total).toFixed(2);
                document.getElementById('boarAcqCost').innerText         = fmt(data.acquisition_cost);
                document.getElementById('boarOpCost').innerText          = fmt(data.operation_cost);
                document.getElementById('boarTransferredCost').innerText = '- ' + fmt(data.transferred_cost);
                document.getElementById('boarCostInfo').style.display    = 'flex';
            }
        } else {
            // Reset boar panel
            limits.boar = 0;
            document.getElementById('boarCostInfo').style.display = 'none';
            document.getElementById('boarCostInput').value = '0';
            document.getElementById('boarMax').innerText = '0';
        }

        recalc();
    }

    // --- OFFSPRING ---

    async function loadOffspring(sowId) {
        const box = document.getElementById('pigletBox');
        box.innerHTML = '<div style="width:100%; text-align:center; color:var(--text-muted); padding-top:30px;"><i class="fa-solid fa-spinner fa-spin me-2"></i> Loading offspring...</div>';
        selectedPiglets.clear();

        const data = await fetchJSON(`${API}?action=get_piglets_by_mother&mother_id=${sowId}`);

        if (!data) {
            box.innerHTML = '<div style="width:100%; text-align:center; color:var(--red); padding-top:30px;"><i class="fa-solid fa-triangle-exclamation display-block margin-bottom"></i> Failed to load offspring.</div>';
            recalc();
            return;
        }

        box.innerHTML = '';
        if (data.length === 0) {
            box.innerHTML = '<div style="width:100%; text-align:center; color:var(--text-muted); padding-top:30px; font-style:italic;"><i class="fa-solid fa-ghost" style="display:block; font-size:2rem; margin-bottom:1rem; opacity:0.5;"></i>No eligible offspring found for this sow.</div>';
        } else {
            data.forEach(p => {
                selectedPiglets.set(String(p.ANIMAL_ID), p.TAG_NO);
                box.innerHTML += `
                    <div class="tag-pill" id="p_${p.ANIMAL_ID}">
                        ${p.TAG_NO}
                        <span class="tag-remove" onclick="remP('${p.ANIMAL_ID}')"><i class="fa-solid fa-xmark"></i></span>
                    </div>`;
            });
        }
        recalc();
    }

    function remP(id) {
        selectedPiglets.delete(String(id));
        document.getElementById('p_' + id)?.remove();
        recalc();
    }

    // --- VALIDATION & RECALC ---

    function validateInput(t) {
        const input = document.getElementById(t + 'CostInput');
        const val   = parseFloat(input.value) || 0;
        if (val > limits[t]) {
            input.classList.add('error');
        } else {
            input.classList.remove('error');
        }
        recalc();
    }

    function recalc() {
        const sowVal  = parseFloat(document.getElementById('sowCostInput').value)  || 0;
        const boarVal = parseFloat(document.getElementById('boarCostInput').value) || 0;
        const total   = sowVal + boarVal;
        const count   = selectedPiglets.size;

        document.getElementById('totalDisplay').innerText  = fmt(total);
        document.getElementById('countPiglets').innerText  = count;
        document.getElementById('costPerHead').innerText   = count > 0 ? fmt(total / count) : '₱ 0.00';

        // Enable transfer only if there's at least one piglet and no input errors
        const hasErrors = document.querySelector('.cost-input.error');
        const hasPiglets = count > 0;
        const hasCost = total > 0;
        document.getElementById('btnTransfer').disabled = !(hasPiglets && hasCost && !hasErrors);
    }

    // --- SUBMIT ---

    async function submitTransfer() {
        const sowId  = document.getElementById('sowSelect').value;
        const boarId = document.getElementById('boarSelect').value;
        const sowCost  = parseFloat(document.getElementById('sowCostInput').value)  || 0;
        const boarCost = parseFloat(document.getElementById('boarCostInput').value) || 0;

        if (!sowId && !boarId) {
            showToast('Please select at least one parent (Sow or Boar).', 'error');
            return;
        }
        if (selectedPiglets.size === 0) {
            showToast('No offspring selected for transfer.', 'error');
            return;
        }
        if (sowCost > limits.sow) {
            showToast(`Sow cost (₱${sowCost}) exceeds available net worth (₱${limits.sow}).`, 'error');
            return;
        }
        if (boarCost > limits.boar) {
            showToast(`Boar cost (₱${boarCost}) exceeds available net worth (₱${limits.boar}).`, 'error');
            return;
        }
        if (!confirm('Are you sure you want to proceed with this cost transfer? This action will permanently affect the financial ledgers of the selected animals.')) return;

        const btn = document.getElementById('btnTransfer');
        const ogText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Processing Ledger...';

        const fd = new FormData();
        fd.append('sow_id',    sowId);
        fd.append('boar_id',   boarId);
        fd.append('sow_cost',  sowCost);
        fd.append('boar_cost', boarCost);
        fd.append('piglet_ids', JSON.stringify(Array.from(selectedPiglets.keys())));

        try {
            const res = await fetch('../process/saveCostTransfer.php', { method: 'POST', body: fd });
            const raw = await res.text();
            let result;
            try {
                result = JSON.parse(raw);
            } catch (e) {
                console.error('Non-JSON response from saveCostTransfer.php:', raw);
                showToast('Server error. Check the browser console for details.', 'error');
                return;
            }

            if (result.success) {
                showToast('Transfer completed successfully!');
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast(result.message || 'Transfer failed.', 'error');
            }
        } catch (err) {
            console.error(err);
            showToast('System connection error occurred.', 'error');
        } finally {
            btn.disabled = false;
            btn.innerHTML = ogText;
            recalc();
        }
    }
</script>
</body>
</html>