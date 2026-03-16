<?php
// views/fcr_management.php
$page = "farm"; 
include '../config/Connection.php';
include '../security/checkAccess.php';
checkAccess('fcr_management');
include '../common/navbar.php';
include '../common/chat_support.php';
include '../functions/getUsersLocation.php'; // ADDED LOCATION FUNCTION

// Fetch Locations for Dropdowns
$locations = [];
if ($USER_LOCATION_ != 1000) {
    $stmt = $conn->prepare("SELECT * FROM locations WHERE LOCATION_ID = ? ORDER BY LOCATION_NAME");
    $stmt->execute([$USER_LOCATION_]);
} else {
    $stmt = $conn->prepare("SELECT * FROM locations ORDER BY LOCATION_NAME");
    $stmt->execute();
}
$locations = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>FCR Management</title>
    <style>
        :root { --dark: #0f172a; --dark-light: #1e293b; --orange: #f59e0b; --blue: #3b82f6; --green: #22c55e; --purple: #a855f7; --red: #ef4444; --border: rgba(255,255,255,0.1); }
        body { background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: #e2e8f0; font-family: system-ui, sans-serif; min-height: 100vh; margin: 0;}
        .container { max-width: 1400px; margin: 0 auto; padding: 2rem; }
        
        /* Back Link Style - Standard Text Link */
        .back-link { 
            display: inline-flex; align-items: center; gap: 8px; 
            text-decoration: none; color: #94a3b8; font-weight: 600; 
            font-size: 1rem; margin-bottom: 1rem; transition: color 0.2s;
        }
        .back-link:hover { color: white; }

        /* Header */
        .header { text-align: center; margin-bottom: 2rem; border-bottom: 1px solid var(--border); padding-bottom: 1rem; }
        .header h1 { color: var(--orange); margin: 0; font-size: 2.5rem; }
        .header p { color: #94a3b8; margin: 5px 0 0 0; }

        .tabs { display: flex; gap: 10px; margin-bottom: 20px; border-bottom: 1px solid var(--border); padding-bottom: 10px; overflow-x: auto;}
        .tab-btn { background: transparent; border: none; color: #94a3b8; padding: 10px 20px; font-size: 1rem; cursor: pointer; border-bottom: 2px solid transparent; transition: 0.3s; white-space: nowrap;}
        .tab-btn.active { color: var(--orange); border-bottom-color: var(--orange); font-weight: bold; }
        .tab-content { display: none; animation: fadeIn 0.3s; }
        .tab-content.active { display: block; }
        
        .config-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; }
        .card { background: var(--dark-light); border: 1px solid var(--border); border-radius: 12px; padding: 1.5rem; }
        .card h3 { margin-top: 0; border-bottom: 1px solid var(--border); padding-bottom: 10px; font-size: 1.2rem; }
        
        .h-loc h3 { color: var(--blue); } .h-bldg h3 { color: var(--green); } .h-pen h3 { color: var(--orange); } .h-age h3 { color: var(--purple); } .h-ind h3 { color: var(--red); }
        
        .form-group { margin-bottom: 15px; }
        label { display: block; color: #cbd5e1; margin-bottom: 5px; font-size: 0.85rem; font-weight: bold; }
        select, input { width: 100%; padding: 10px; background: #334155; border: 1px solid #475569; color: white; border-radius: 6px; font-size: 1rem; outline: none; }
        select[disabled] { opacity: 0.6; cursor: not-allowed; }
        
        .btn-save { width: 100%; padding: 12px; border: none; color: white; font-weight: bold; border-radius: 6px; cursor: pointer; margin-top: 10px; }
        .btn-loc { background: var(--blue); } .btn-bldg { background: var(--green); } .btn-pen { background: var(--orange); } .btn-age { background: var(--purple); } .btn-ind { background: var(--red); }
        
        .table-responsive { overflow-x: auto; }
        .data-table { width: 100%; border-collapse: collapse; margin-top: 10px; min-width: 1000px;}
        .data-table th, .data-table td { padding: 12px; text-align: left; border-bottom: 1px solid var(--border); }
        .data-table th { color: var(--orange); background: rgba(245, 158, 11, 0.1); white-space: nowrap;}
        .data-table td { white-space: nowrap; }

        /* Pen Header Row */
        .pen-header-row {
            background: rgba(59, 130, 246, 0.15);
            border-top: 2px solid rgba(59, 130, 246, 0.4);
        }
        .pen-header-row td {
            color: #93c5fd;
            font-size: 1.1rem;
            font-weight: bold;
            padding: 15px 12px !important;
        }
        
        .priority-badge { padding: 4px 10px; border-radius: 4px; font-size: 0.8rem; font-weight: bold; }
        .details-row { display: none; background: rgba(15, 23, 42, 0.5); }
        .details-content { padding: 20px; display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; border-left: 4px solid var(--orange); }
        .detail-item label { color: #94a3b8; font-size: 0.8rem; margin-bottom: 5px; }
        .detail-item input { background: #0f172a; border-color: #334155; }
        .detail-item input:read-only { background: #1e293b; color: #64748b; cursor: not-allowed; }
        .btn-update { background: var(--green); color: white; border: none; padding: 10px 15px; border-radius: 6px; cursor: pointer; font-size: 0.9rem; margin-top: 20px; font-weight: bold;}
        .empty-state { text-align: center; padding: 30px; color: #64748b; font-style: italic; }
        
        @keyframes fadeIn { from{opacity:0; transform:translateY(5px);} to{opacity:1; transform:translateY(0);} }
        
        @media (max-width: 768px) {
            .header { text-align: center; }

            /* Mobile Table to Card */
            .data-table { min-width: 0; display: block; }
            .data-table thead { display: none; }
            .data-table tbody { display: block; width: 100%; }
            .data-table tr { 
                display: block; 
                background: rgba(30, 41, 59, 0.6); 
                border: 1px solid #475569; 
                border-radius: 12px; 
                margin-bottom: 1rem; 
                padding: 1rem; 
            }
            .data-table td { 
                display: flex; justify-content: space-between; align-items: center; 
                padding: 0.75rem 0; border-bottom: 1px dashed rgba(255,255,255,0.1); 
                text-align: right; white-space: normal;
            }
            .data-table td:last-child { border-bottom: none; }
            .data-table td::before { 
                content: attr(data-label); font-weight: 700; color: #94a3b8; 
                font-size: 0.8rem; text-transform: uppercase; margin-right: 1rem; text-align: left;
            }

            /* Fix Mobile Headers */
            tr.pen-header-row {
                background: rgba(59, 130, 246, 0.2);
                border: 1px solid rgba(59, 130, 246, 0.4);
                padding: 1rem;
            }
            tr.pen-header-row td {
                display: block; text-align: left; border: none; padding: 0 !important;
            }
            tr.pen-header-row td::before { display: none; }

            /* Fix mobile details row */
            .details-content { grid-template-columns: 1fr; border-left: none; border-top: 4px solid var(--orange); padding: 15px 0;}
        }
    </style>
</head>
<body>

<div class="container">
    
    <a href="farm_dashboard.php" class="back-link">
        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
        Back to Farm Dashboard
    </a>

    <div class="header">
        <h1>FCR Priority Manager</h1>
        <p>Hierarchy: Individual > Pen > Building > Location > Age</p>
    </div>

    <div class="tabs">
        <button class="tab-btn active" onclick="switchTab('config')"> Configuration</button>
        <button class="tab-btn" onclick="switchTab('view')"> View & Analyze</button>
    </div>

    <div id="config" class="tab-content active">
        <div class="config-grid">
            
            <div class="card h-ind">
                <h3> Individual (Highest Priority)</h3>
                <form onsubmit="saveConfig(event, 'Individual')">
                    <div class="form-group">
                        <label>1. Location</label>
                        <select id="i_loc" onchange="loadBuildings(this.value, 'i_bldg')" required <?php echo ($USER_LOCATION_ != 1000) ? 'style="pointer-events: none; opacity: 0.7; background-color: #1e293b;"' : ''; ?>>
                            <?php if($USER_LOCATION_ == 1000): ?>
                                <option value="">Select Location</option>
                            <?php endif; ?>
                            <?php foreach($locations as $l): ?>
                                <option value="<?= $l['LOCATION_ID'] ?>" <?= ($USER_LOCATION_ != 1000 && $l['LOCATION_ID'] == $USER_LOCATION_) ? 'selected' : '' ?>><?= $l['LOCATION_NAME'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>2. Building</label>
                        <select id="i_bldg" onchange="loadPens(this.value, 'i_pen')" disabled required>
                            <option value="">Select Location First</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>3. Pen</label>
                        <select id="i_pen" onchange="loadAnimalOptions(this.value, 'i_animal')" disabled required>
                            <option value="">Select Building First</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>4. Select Animal (Tag No)</label>
                        <select id="i_animal" name="animal_id" disabled required>
                            <option value="">Select Pen First</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Target FCR %</label>
                        <input type="number" name="fcr" step="0.01" placeholder="e.g. 0.25" required>
                    </div>
                    <button class="btn-save btn-ind">Save Individual Rule</button>
                </form>
            </div>

            <div class="card h-loc">
                <h3> Location FCR</h3>
                <form onsubmit="saveConfig(event, 'Location')">
                    <div class="form-group">
                        <label>Target Location</label>
                        <select name="location_id" required <?php echo ($USER_LOCATION_ != 1000) ? 'style="pointer-events: none; opacity: 0.7; background-color: #1e293b;"' : ''; ?>>
                            <?php if($USER_LOCATION_ == 1000): ?>
                                <option value="">Select Location</option>
                            <?php endif; ?>
                            <?php foreach($locations as $l): ?>
                                <option value="<?= $l['LOCATION_ID'] ?>" <?= ($USER_LOCATION_ != 1000 && $l['LOCATION_ID'] == $USER_LOCATION_) ? 'selected' : '' ?>><?= $l['LOCATION_NAME'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Target FCR %</label>
                        <input type="number" name="fcr" step="0.01" required>
                    </div>
                    <button class="btn-save btn-loc">Save Location Rule</button>
                </form>
            </div>

            <div class="card h-bldg">
                <h3> Building FCR</h3>
                <form onsubmit="saveConfig(event, 'Building')">
                    <div class="form-group">
                        <label>Location</label>
                        <select id="b_loc" onchange="loadBuildings(this.value, 'b_bldg')" required <?php echo ($USER_LOCATION_ != 1000) ? 'style="pointer-events: none; opacity: 0.7; background-color: #1e293b;"' : ''; ?>>
                            <?php if($USER_LOCATION_ == 1000): ?>
                                <option value="">Select Location</option>
                            <?php endif; ?>
                            <?php foreach($locations as $l): ?>
                                <option value="<?= $l['LOCATION_ID'] ?>" <?= ($USER_LOCATION_ != 1000 && $l['LOCATION_ID'] == $USER_LOCATION_) ? 'selected' : '' ?>><?= $l['LOCATION_NAME'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Target Building</label>
                        <select id="b_bldg" name="building_id" disabled required>
                            <option value="">Select Location First</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Target FCR %</label>
                        <input type="number" name="fcr" step="0.01" required>
                    </div>
                    <button class="btn-save btn-bldg">Save Building Rule</button>
                </form>
            </div>

            <div class="card h-pen">
                <h3> Pen FCR</h3>
                <form onsubmit="saveConfig(event, 'Pen')">
                    <div class="form-group">
                        <label>Location</label>
                        <select id="p_loc" onchange="loadBuildings(this.value, 'p_bldg')" required <?php echo ($USER_LOCATION_ != 1000) ? 'style="pointer-events: none; opacity: 0.7; background-color: #1e293b;"' : ''; ?>>
                            <?php if($USER_LOCATION_ == 1000): ?>
                                <option value="">Select Location</option>
                            <?php endif; ?>
                            <?php foreach($locations as $l): ?>
                                <option value="<?= $l['LOCATION_ID'] ?>" <?= ($USER_LOCATION_ != 1000 && $l['LOCATION_ID'] == $USER_LOCATION_) ? 'selected' : '' ?>><?= $l['LOCATION_NAME'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Building</label>
                        <select id="p_bldg" onchange="loadPens(this.value, 'p_pen')" disabled required>
                            <option value="">Select Location First</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Target Pen</label>
                        <select id="p_pen" name="pen_id" disabled required>
                            <option value="">Select Building First</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Target FCR %</label>
                        <input type="number" name="fcr" step="0.01" required>
                    </div>
                    <button class="btn-save btn-pen">Save Pen Rule</button>
                </form>
            </div>

            <div class="card h-age">
                <h3> Age Rule (Fallback)</h3>
                <form onsubmit="saveConfig(event, 'Age')">
                    <div style="display:flex; gap:10px;">
                        <div class="form-group" style="flex:1"><label>Min Days</label><input type="number" name="min_age" required></div>
                        <div class="form-group" style="flex:1"><label>Max Days</label><input type="number" name="max_age" required></div>
                    </div>
                    <div class="form-group">
                        <label>Target FCR %</label>
                        <input type="number" name="fcr" step="0.01" required>
                    </div>
                    <button class="btn-save btn-age">Save Age Rule</button>
                </form>
            </div>
        </div>

        <div class="card" style="margin-top: 20px;">
            <h3> Active Rules</h3>
            <div id="configList">Loading...</div>
        </div>
    </div>

    <div id="view" class="tab-content">
        <div class="card">
            <h3>🔍 Filter Animals (Drill Down)</h3>
            <div class="config-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); margin-bottom: 20px;">
                <div class="form-group">
                    <label>1. Location</label>
                    <select id="v_loc" onchange="handleViewLocChange(this.value)" <?php echo ($USER_LOCATION_ != 1000) ? 'style="pointer-events: none; opacity: 0.7; background-color: #1e293b;"' : ''; ?>>
                        <?php if($USER_LOCATION_ == 1000): ?>
                            <option value="">Select Location</option>
                        <?php endif; ?>
                        <?php foreach($locations as $l): ?>
                            <option value="<?= $l['LOCATION_ID'] ?>" <?= ($USER_LOCATION_ != 1000 && $l['LOCATION_ID'] == $USER_LOCATION_) ? 'selected' : '' ?>><?= $l['LOCATION_NAME'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>2. Building</label>
                    <select id="v_bldg" onchange="handleViewBldgChange(this.value)" disabled>
                        <option value="">Select Location First</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>3. Pen</label>
                    <select id="v_pen" onchange="loadAnimals()" disabled>
                        <option value="">All Pens</option>
                    </select>
                </div>
            </div>

            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Tag No</th>
                            <th>Age</th>
                            <th>Birth Wt</th>
                            <th>Total Feed</th>
                            <th>Calc. Est. Wt</th>
                            <th>Applied Rule</th>
                            <th>Target FCR</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="animalTable">
                        <tr><td colspan="8" class="empty-state">Please select a Building to view animals.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    const USER_LOCATION = <?php echo json_encode($USER_LOCATION_); ?>;

    document.addEventListener('DOMContentLoaded', () => {
        if (USER_LOCATION != 1000) {
            // Pre-trigger cascade for all tabs
            loadBuildings(USER_LOCATION, 'i_bldg');
            loadBuildings(USER_LOCATION, 'b_bldg');
            loadBuildings(USER_LOCATION, 'p_bldg');
            loadBuildings(USER_LOCATION, 'v_bldg');
        }
    });

    function switchTab(id) {
        document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.getElementById(id).classList.add('active');
        event.target.classList.add('active');
        if(id === 'config') loadConfigs();
    }

    // --- DROPDOWN HELPERS ---
    function loadBuildings(locId, targetId) {
        const sel = document.getElementById(targetId);
        sel.innerHTML = '<option value="">Loading...</option>'; sel.disabled = true;
        
        // Reset downstream if this is the view tab
        if(targetId === 'v_bldg') {
            resetDropdown('v_pen', 'All Pens');
            clearTable();
        } else if(targetId === 'i_bldg') {
            resetDropdown('i_pen', 'Select Building First');
            resetDropdown('i_animal', 'Select Pen First');
        } else if(targetId === 'p_bldg') {
            resetDropdown('p_pen', 'Select Building First');
        }

        if(!locId) { 
            sel.innerHTML = '<option value="">Select Location First</option>'; 
            return; 
        }
        
        fetch(`../process/getBuildingsByLocation.php?location_id=${locId}`)
            .then(r=>r.json()).then(d => {
                sel.innerHTML = '<option value="">Select Building</option>';
                d.buildings.forEach(b => sel.innerHTML += `<option value="${b.BUILDING_ID}">${b.BUILDING_NAME}</option>`);
                sel.disabled = false;
            });
    }
    
    function loadPens(bldgId, targetId) {
        const sel = document.getElementById(targetId);
        sel.innerHTML = '<option value="">Loading...</option>'; sel.disabled = true;

        // Reset downstream
        if(targetId === 'v_pen') {
            // Do not clear table, we want to load animals for the whole building
        } else if(targetId === 'i_pen') {
            resetDropdown('i_animal', 'Select Pen First');
        }

        if(!bldgId) { 
            sel.innerHTML = targetId === 'v_pen' ? '<option value="">All Pens</option>' : '<option value="">Select Building First</option>'; 
            if(targetId === 'v_pen') clearTable();
            return; 
        }

        fetch(`../process/getPensByBuilding.php?building_id=${bldgId}`)
            .then(r=>r.json()).then(d => {
                sel.innerHTML = targetId === 'v_pen' ? '<option value="">All Pens</option>' : '<option value="">Select Pen</option>';
                d.pens.forEach(p => sel.innerHTML += `<option value="${p.PEN_ID}">${p.PEN_NAME}</option>`);
                sel.disabled = false;
                
                if(targetId === 'v_pen') loadAnimals(); // Automatically load animals for the building
            });
    }

    function loadAnimalOptions(penId, targetId) {
        const sel = document.getElementById(targetId);
        sel.innerHTML = '<option value="">Loading...</option>'; sel.disabled = true;
        if(!penId) { sel.innerHTML = '<option value="">Select Pen First</option>'; return; }

        fetch(`../process/processFcrConfig.php?action=get_pen_animals&pen_id=${penId}`)
            .then(r=>r.json()).then(data => {
                sel.innerHTML = '<option value="">Select Animal</option>';
                if(data.length > 0) {
                    data.forEach(a => sel.innerHTML += `<option value="${a.ANIMAL_ID}">${a.TAG_NO}</option>`);
                    sel.disabled = false;
                } else {
                    sel.innerHTML = '<option value="">No Animals in Pen</option>';
                }
            });
    }

    // --- VIEW TAB HANDLERS ---
    function handleViewLocChange(locId) {
        loadBuildings(locId, 'v_bldg');
    }

    function handleViewBldgChange(bldgId) {
        loadPens(bldgId, 'v_pen');
    }

    function resetDropdown(id, placeholder) {
        const el = document.getElementById(id);
        el.innerHTML = `<option value="">${placeholder}</option>`;
        el.disabled = true;
    }

    function clearTable() {
        document.getElementById('animalTable').innerHTML = '<tr><td colspan="8" class="empty-state">Please select a Building to view animals.</td></tr>';
    }

    // --- SAVE LOGIC ---
    function saveConfig(e, type) {
        e.preventDefault();
        const fd = new FormData(e.target);
        fd.append('action', 'save_config');
        fd.append('type', type);

        fetch('../process/processFcrConfig.php', { method:'POST', body:fd })
            .then(r=>r.json()).then(d => {
                alert(d.message);
                if(d.success) { e.target.reset(); loadConfigs(); }
            });
    }

    function deleteConfig(configId) {
        if(!confirm("Delete this rule?")) return;
        const fd = new FormData();
        fd.append('action', 'delete_config');
        fd.append('config_id', configId);
        fetch('../process/processFcrConfig.php', { method:'POST', body:fd })
            .then(r=>r.json()).then(d => { alert(d.message); if(d.success) loadConfigs(); });
    }

    function loadConfigs() {
        fetch('../process/processFcrConfig.php?action=list')
            .then(r=>r.text()).then(h => document.getElementById('configList').innerHTML = h);
    }

    // --- VIEW ANIMALS LOGIC ---
    function loadAnimals() {
        const loc = document.getElementById('v_loc').value;
        const bldg = document.getElementById('v_bldg').value;
        const pen = document.getElementById('v_pen').value; // Can be empty for "All Pens"

        if (!bldg) {
            clearTable();
            return;
        }

        document.getElementById('animalTable').innerHTML = '<tr><td colspan="8" style="text-align:center; padding: 20px;">Loading data...</td></tr>';
        
        fetch(`../process/processFcrConfig.php?action=view_animals&loc=${loc}&bldg=${bldg}&pen=${pen}`)
            .then(r=>r.json()).then(data => {
                let html = '';
                if(data.length === 0) {
                    document.getElementById('animalTable').innerHTML = '<tr><td colspan="8" class="empty-state">No animals found.</td></tr>';
                    return;
                }
                
                let currentPath = '';

                data.forEach(r => {
                    // Grouping by Pen Path Header
                    if (r.path !== currentPath) {
                        html += `<tr class="pen-header-row"><td colspan="8">📍 ${r.path}</td></tr>`;
                        currentPath = r.path;
                    }

                    let color = '#94a3b8';
                    if(r.source === 'Individual') color = '#ef4444';
                    else if(r.source === 'Pen') color = '#f59e0b';
                    else if(r.source === 'Building') color = '#22c55e';
                    else if(r.source === 'Location') color = '#3b82f6';
                    else if(r.source === 'Age') color = '#a855f7';

                    html += `
                    <tr>
                        <td data-label="Tag No"><strong>${r.tag}</strong></td>
                        <td data-label="Age">${r.age} days</td>
                        <td data-label="Birth Wt">${r.birth_weight} kg</td>
                        <td data-label="Total Feed">${r.feed} kg</td>
                        <td data-label="Calc. Est. Wt" style="color:var(--blue);font-weight:bold;">${r.est_weight} kg</td>
                        <td data-label="Applied Rule"><span class="priority-badge" style="background:${color}20; color:${color}">${r.source} Rule</span></td>
                        <td data-label="Target FCR"><b>${r.fcr}</b></td>
                        <td data-label="Action">
                            <button onclick="toggleDetails(${r.id})" style="background:#3b82f6;color:white;border:none;padding:6px 12px;border-radius:4px;cursor:pointer; font-weight:bold;">Evaluate ▼</button>
                        </td>
                    </tr>
                    <tr id="details-${r.id}" class="details-row">
                        <td colspan="8">
                            <form onsubmit="updateAnimalLog(event, ${r.id})">
                                <input type="hidden" name="animal_id" value="${r.id}">
                                <input type="hidden" name="pen_id" value="${r.pen_id}">
                                <div class="details-content">
                                    <div class="detail-item">
                                        <label>Weight at Birth (kg)</label>
                                        <input type="text" value="${r.birth_weight}" readonly>
                                    </div>
                                    <div class="detail-item">
                                        <label>Total Feed (kg)</label>
                                        <input type="text" id="feed-${r.id}" value="${r.feed}" readonly>
                                    </div>
                                    <div class="detail-item">
                                        <label>FCR (Editable)</label>
                                        <input type="number" step="0.01" id="fcr-${r.id}" name="fcr_used" value="${r.fcr}" oninput="recalc(${r.id})" style="border-color:var(--orange);">
                                    </div>
                                    <div class="detail-item">
                                        <label>Est. Gain (kg)</label>
                                        <input type="text" id="gain-${r.id}" value="${r.gain}" readonly>
                                    </div>
                                    <div class="detail-item">
                                        <label>Calc. Est. Weight (kg)</label>
                                        <input type="text" id="est-${r.id}" name="est_weight" value="${r.est_weight}" readonly style="color:var(--blue);font-weight:bold;">
                                    </div>
                                    <div class="detail-item">
                                        <label>Actual Weight (kg)</label>
                                        <input type="number" step="0.01" name="actual_weight" placeholder="Enter Actual" value="${r.actual_weight || ''}" required oninput="recalc(${r.id})">
                                    </div>
                                    <div class="detail-item">
                                        <label>Date of Weighing</label>
                                        <input type="date" name="weigh_date" value="${new Date().toISOString().split('T')[0]}" required>
                                    </div>
                                    <div class="detail-item" style="display:flex;align-items:flex-end;">
                                        <button type="submit" class="btn-update">Save Update</button>
                                    </div>
                                </div>
                            </form>
                        </td>
                    </tr>`;
                });
                document.getElementById('animalTable').innerHTML = html;
            });
    }

    function toggleDetails(id) {
        const row = document.getElementById(`details-${id}`);
        row.style.display = row.style.display === 'table-row' ? 'none' : 'table-row';
    }

    function recalc(id) {
        const feed = parseFloat(document.getElementById(`feed-${id}`).value) || 0;
        const fcr = parseFloat(document.getElementById(`fcr-${id}`).value) || 0;
        const gain = feed * fcr; 
        
        const row = document.getElementById(`details-${id}`);
        const birth = parseFloat(row.querySelector('input[readonly]').value) || 0; 
        
        const est = birth + gain;

        document.getElementById(`gain-${id}`).value = gain.toFixed(2);
        document.getElementById(`est-${id}`).value = est.toFixed(2);
    }

    function updateAnimalLog(e, id) {
        e.preventDefault();
        if(!confirm("Save this record?")) return;
        
        const fd = new FormData(e.target);
        fd.append('action', 'save_single_log');

        fetch('../process/processFcrConfig.php', { method:'POST', body:fd })
            .then(r=>r.json()).then(d => {
                alert(d.message);
                if(d.success) loadAnimals(); 
            });
    }

    loadConfigs();
</script>
</body>
</html>