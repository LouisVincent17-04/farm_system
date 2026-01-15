<?php
// views/animal_weights.php
ob_start(); 
error_reporting(E_ALL);
ini_set('display_errors', 0); 

$page = "farm";
include '../common/navbar.php';
include '../config/Connection.php';
include '../security/checkRole.php';    
checkRole(2); // Farm Admin or Higher

// =========================================================
// AJAX HANDLER (Internal API)
// =========================================================
if (isset($_GET['action'])) {
    ob_end_clean(); 
    header('Content-Type: application/json');
    $action = $_GET['action'];

    try {
        // 1. Get Buildings by Location
        if ($action === 'get_buildings' && isset($_GET['loc_id'])) {
            $stmt = $conn->prepare("SELECT BUILDING_ID, BUILDING_NAME FROM buildings WHERE LOCATION_ID = ? ORDER BY BUILDING_NAME");
            $stmt->execute([$_GET['loc_id']]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC)); exit;
        }
        // 2. Get Pens by Building
        if ($action === 'get_pens' && isset($_GET['bldg_id'])) {
            $stmt = $conn->prepare("SELECT PEN_ID, PEN_NAME FROM pens WHERE BUILDING_ID = ? ORDER BY PEN_NAME");
            $stmt->execute([$_GET['bldg_id']]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC)); exit;
        }
        // 3. Get Animals by Pen (For the Table)
        if ($action === 'get_animals' && isset($_GET['pen_id'])) {
            $stmt = $conn->prepare("
                SELECT ANIMAL_ID, TAG_NO, CURRENT_ACTUAL_WEIGHT, SEX 
                FROM animal_records 
                WHERE PEN_ID = ? AND IS_ACTIVE = 1 AND CURRENT_STATUS != 'Sold' 
                ORDER BY TAG_NO
            ");
            $stmt->execute([$_GET['pen_id']]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC)); exit;
        }
    } catch (Exception $e) { echo json_encode([]); exit; }
}

// Initial Locations
$locs = $conn->query("SELECT * FROM locations ORDER BY LOCATION_NAME")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Update Weights</title>
    <style>
        /* [Standard Styling] */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: sans-serif; background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: #e2e8f0; min-height: 100vh; }
        .container { max-width: 1400px; margin: 0 auto; padding: 2rem; }
        
        .main-grid { display: grid; grid-template-columns: 300px 1fr; gap: 2rem; align-items: start; }
        
        /* Control Panel */
        .panel { background: rgba(30, 41, 59, 0.7); border: 1px solid #475569; border-radius: 16px; padding: 1.5rem; }
        .panel-title { font-size: 1.2rem; font-weight: bold; color: white; margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 1px solid #475569; }
        
        .form-group { margin-bottom: 1rem; }
        .form-label { display: block; color: #94a3b8; font-size: 0.85rem; margin-bottom: 0.5rem; font-weight: 600; }
        .form-select { width: 100%; padding: 10px; background: #0f172a; border: 1px solid #475569; color: white; border-radius: 8px; }
        .form-select:disabled { opacity: 0.5; cursor: not-allowed; }

        /* Table Area */
        .table-area { background: #1e293b; border-radius: 16px; border: 1px solid #475569; overflow: hidden; }
        .w-table { width: 100%; border-collapse: collapse; }
        .w-table th { background: #0f172a; padding: 15px; text-align: left; color: #94a3b8; font-size: 0.85rem; text-transform: uppercase; border-bottom: 2px solid #334155; }
        .w-table td { padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.05); vertical-align: middle;}
        .w-table tr:hover { background: rgba(255,255,255,0.02); }

        /* Inputs */
        .weight-input { 
            background: #0f172a; border: 1px solid #475569; color: #fff; padding: 10px; border-radius: 6px; width: 120px; text-align: right; 
            font-family: monospace; font-size: 1.1rem; font-weight: bold; transition: 0.2s;
        }
        .weight-input:focus { border-color: #3b82f6; outline: none; background: #1e293b; }
        .weight-input::placeholder { color: #475569; font-weight: normal; }

        /* Changes visualizer */
        .weight-input.changed { border-color: #34d399; background: rgba(52, 211, 153, 0.1); }
        .diff-tag { font-size: 0.85rem; margin-left: 10px; font-weight: bold; font-family: monospace; }
        .diff-pos { color: #34d399; } /* Green for gain */
        .diff-neg { color: #f87171; } /* Red for loss */

        .btn-save { width: 100%; padding: 12px; background: linear-gradient(135deg, #3b82f6, #2563eb); color: white; border: none; border-radius: 8px; font-weight: 700; cursor: pointer; margin-top: 1rem; transition: transform 0.1s; }
        .btn-save:disabled { background: #475569; cursor: not-allowed; opacity: 0.7; }
        .btn-save:hover:not(:disabled) { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(37, 99, 235, 0.4); }
    </style>
</head>
<body>

<div class="container">
    <div class="main-grid">
        
        <div class="panel">
            <div class="panel-title">1. Select Pen</div>
            <div class="form-group">
                <label class="form-label">Location</label>
                <select id="loc_id" class="form-select" onchange="loadBuildings()">
                    <option value="">-- Select --</option>
                    <?php foreach($locs as $l): echo "<option value='{$l['LOCATION_ID']}'>{$l['LOCATION_NAME']}</option>"; endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Building</label>
                <select id="bldg_id" class="form-select" onchange="loadPens()" disabled><option value="">-- Select --</option></select>
            </div>
            <div class="form-group">
                <label class="form-label">Pen</label>
                <select id="pen_id" class="form-select" onchange="loadAnimals()" disabled><option value="">-- Select --</option></select>
            </div>
            
            <button class="btn-save" id="btn_save" onclick="saveWeights()" disabled>Save All Weights</button>
        </div>

        <div class="table-area" style="min-height: 500px;">
            <div style="padding: 1.5rem; border-bottom:1px solid #475569; display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h2 style="margin:0; font-size:1.25rem;">Weight Entry Table</h2>
                    <p style="margin:5px 0 0 0; color:#94a3b8; font-size:0.9rem;">Input current actual weights for animals below.</p>
                </div>
                <div id="count_display" style="color: #64748b; font-weight: 600;">0 Animals</div>
            </div>
            
            <div id="table-container" style="max-height: 70vh; overflow-y: auto;">
                <div style="padding: 4rem; text-align: center; color: #64748b;">
                    Select a Pen from the left to load the animal list.
                </div>
            </div>
        </div>

    </div>
</div>

<script>
    // --- Helper for fetching JSON ---
    // Uses current filename dynamically
    const API_URL = window.location.pathname.split("/").pop();

    async function fetchJson(params) {
        try {
            const res = await fetch(`${API_URL}${params}`);
            return await res.json();
        } catch(e) { return []; }
    }

    // --- Dropdown Logic ---
    async function loadBuildings() {
        const id = document.getElementById('loc_id').value;
        const target = document.getElementById('bldg_id');
        resetSelect(target); resetSelect(document.getElementById('pen_id'));
        if(!id) return;

        const data = await fetchJson(`?action=get_buildings&loc_id=${id}`);
        populateSelect(target, data, 'BUILDING_ID', 'BUILDING_NAME');
        target.disabled = false;
    }

    async function loadPens() {
        const id = document.getElementById('bldg_id').value;
        const target = document.getElementById('pen_id');
        resetSelect(target);
        if(!id) return;

        const data = await fetchJson(`?action=get_pens&bldg_id=${id}`);
        populateSelect(target, data, 'PEN_ID', 'PEN_NAME');
        target.disabled = false;
    }

    function resetSelect(el) { el.innerHTML = '<option value="">-- Select --</option>'; el.disabled = true; }
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
        const container = document.getElementById('table-container');
        const saveBtn = document.getElementById('btn_save');
        const countDisplay = document.getElementById('count_display');
        
        if(!id) {
            container.innerHTML = '<div style="padding:4rem; text-align:center; color:#64748b;">Select a Pen.</div>';
            saveBtn.disabled = true;
            return;
        }

        container.innerHTML = '<div style="padding:4rem; text-align:center; color:#94a3b8;">Loading animals...</div>';
        
        const animals = await fetchJson(`?action=get_animals&pen_id=${id}`);
        
        if(animals.length === 0) {
            container.innerHTML = '<div style="padding:4rem; text-align:center; color:#ef4444;">No active animals found in this pen.</div>';
            saveBtn.disabled = true;
            countDisplay.innerText = "0 Animals";
            return;
        }

        countDisplay.innerText = animals.length + " Animals";

        // Build Table
        // Note: Using a form ID inside the container won't work well if container is overwritten. 
        // We will wrap the table in a form tag in the HTML string.
        let html = `
            <form id="weightForm">
            <table class="w-table">
                <thead>
                    <tr>
                        <th style="padding-left: 2rem;">Tag No / Info</th>
                        <th>Current Recorded</th>
                        <th>New Weight (kg)</th>
                    </tr>
                </thead>
                <tbody>
        `;

        animals.forEach(a => {
            const current = parseFloat(a.CURRENT_ACTUAL_WEIGHT) || 0;
            const sexIcon = a.SEX === 'M' ? '♂' : (a.SEX === 'F' ? '♀' : '');
            
            html += `
                <tr>
                    <td style="padding-left: 2rem;">
                        <div style="font-weight:bold; font-size:1.1rem; color:white; margin-bottom:2px;">${a.TAG_NO}</div>
                        <div style="font-size:0.8rem; color:#64748b;">${sexIcon} ID: ${a.ANIMAL_ID}</div>
                    </td>
                    <td style="color:#94a3b8; font-family:monospace; font-size:1rem;">
                        ${current > 0 ? current.toFixed(2) + ' kg' : '<span style="color:#64748b">-</span>'}
                    </td>
                    <td>
                        <div style="display:flex; align-items:center;">
                            <input type="number" 
                                   step="0.01" 
                                   min="0"
                                   class="weight-input" 
                                   name="weights[${a.ANIMAL_ID}]" 
                                   placeholder="${current > 0 ? current.toFixed(2) : '0.00'}" 
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
            // Highlight if different
            if(newVal !== oldVal) {
                input.classList.add('changed');
                
                // Show difference if old value existed
                if(oldVal > 0) {
                    const diff = newVal - oldVal;
                    const sign = diff > 0 ? '▲ +' : (diff < 0 ? '▼ ' : '');
                    const colorClass = diff > 0 ? 'diff-pos' : (diff < 0 ? 'diff-neg' : '');
                    
                    if(diff !== 0) {
                        diffSpan.className = `diff-tag ${colorClass}`;
                        diffSpan.innerText = `${sign}${diff.toFixed(2)}`;
                    } else {
                        diffSpan.innerText = '';
                    }
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

    function saveWeights() {
        const form = document.getElementById('weightForm');
        if(!form) return;

        const formData = new FormData(form);
        const btn = document.getElementById('btn_save');

        // Check if at least one input has value
        let hasData = false;
        for(let pair of formData.entries()) {
            if(pair[1] !== "") hasData = true;
        }

        if(!hasData) {
            alert("Please enter at least one new weight.");
            return;
        }

        if(!confirm("Are you sure you want to update the records with these new weights?")) return;

        btn.disabled = true;
        btn.innerText = "Updating Records...";

        fetch('../process/updateWeights.php', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if(data.success) {
                alert("✅ " + data.message);
                loadAnimals(); // Refresh table
            } else {
                alert("❌ Error: " + data.message);
            }
            btn.disabled = false;
            btn.innerText = "Save All Weights";
        })
        .catch(err => {
            console.error(err);
            alert("System Error: Check console.");
            btn.disabled = false;
            btn.innerText = "Save All Weights";
        });
    }
</script>

</body>
</html>