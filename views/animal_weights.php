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
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> 
    <title>Update Weights</title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" type="text/css" href="https://npmcdn.com/flatpickr/dist/themes/dark.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    <style>
        /* [Standard Styling] */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: #e2e8f0; min-height: 100vh; }
        
        .container { 
            max-width: 1400px; 
            margin: 0 auto; 
            padding: 2rem; 
        }
        
        /* Back Link Style */
        .back-link { 
            display: inline-flex; align-items: center; gap: 8px; 
            text-decoration: none; color: #94a3b8; font-weight: 600; 
            font-size: 1rem; margin-bottom: 1rem; transition: color 0.2s;
        }
        .back-link:hover { color: white; }

        /* DESKTOP GRID */
        .main-grid { 
            display: grid; 
            grid-template-columns: 320px 1fr; 
            gap: 2rem; 
            align-items: start; 
        }
        
        /* Control Panel */
        .panel { background: rgba(30, 41, 59, 0.7); border: 1px solid #475569; border-radius: 16px; padding: 1.5rem; }
        .panel-title { font-size: 1.2rem; font-weight: bold; color: white; margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 1px solid #475569; }
        
        .form-group { margin-bottom: 1rem; }
        .form-label { display: block; color: #94a3b8; font-size: 0.85rem; margin-bottom: 0.5rem; font-weight: 600; }
        .form-select, .form-input { width: 100%; padding: 12px; background: #0f172a; border: 1px solid #475569; color: white; border-radius: 8px; font-size: 0.95rem; outline:none; }
        .form-select:disabled, .form-input:disabled { opacity: 0.5; cursor: not-allowed; }
        .form-select:focus, .form-input:focus { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1); }

        /* Table Area */
        .table-area { background: #1e293b; border-radius: 16px; border: 1px solid #475569; overflow: hidden; display: flex; flex-direction: column;}
        
        /* Responsive Table Wrapper */
        #table-container {
            max-height: 70vh; 
            overflow-y: auto;
            overflow-x: auto; 
            -webkit-overflow-scrolling: touch;
        }

        .w-table { width: 100%; border-collapse: collapse; min-width: 900px; } 
        .w-table th { background: #0f172a; padding: 15px; text-align: left; color: #94a3b8; font-size: 0.85rem; text-transform: uppercase; border-bottom: 2px solid #334155; position: sticky; top: 0; z-index: 10; }
        .w-table td { padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.05); vertical-align: middle;}
        .w-table tr:hover { background: rgba(255,255,255,0.02); }

        /* Inputs */
        .weight-input { 
            background: #0f172a; border: 1px solid #475569; color: #fff; padding: 8px; border-radius: 6px; width: 90px; text-align: right; 
            font-family: monospace; font-size: 1rem; font-weight: bold; transition: 0.2s;
        }
        .weight-input:focus { border-color: #3b82f6; outline: none; background: #1e293b; }
        .weight-input::placeholder { color: #475569; font-weight: normal; }
        
        /* Disabled Input Style */
        .weight-input:disabled { background: #1e293b; color: #64748b; cursor: not-allowed; border-color: #334155; opacity: 0.6;}

        /* Changes visualizer */
        .weight-input.changed { border-color: #34d399; background: rgba(52, 211, 153, 0.1); }
        .diff-tag { display: block; font-size: 0.75rem; font-weight: bold; font-family: monospace; white-space: nowrap; margin-top: 4px; text-align: right; width: 90px;}
        .diff-pos { color: #34d399; }
        .diff-neg { color: #f87171; }

        .btn-save { width: 100%; padding: 15px; background: linear-gradient(135deg, #3b82f6, #2563eb); color: white; border: none; border-radius: 8px; font-weight: 700; cursor: pointer; margin-top: 1rem; transition: transform 0.1s; font-size: 1rem; }
        .btn-save:disabled { background: #475569; cursor: not-allowed; opacity: 0.7; }
        .btn-save:hover:not(:disabled) { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(37, 99, 235, 0.4); }

        /* --- MOBILE RESPONSIVENESS --- */
        @media (max-width: 768px) {
            .container { padding: 1rem; }
            .main-grid { grid-template-columns: 1fr; gap: 1.5rem; }
            .panel { padding: 1rem; }
            .w-table th, .w-table td { padding: 10px; }
            .table-area { max-height: none; } 
            #table-container { max-height: 50vh; }
        }
    </style>
</head>
<body>

<div class="container">
    
    <a href="farm_dashboard.php" class="back-link">
        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
        Back to Farm Dashboard
    </a>

    <div class="main-grid">
        
        <div class="panel">
            <div class="panel-title">1. Filter & Select</div>
            
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

            <div style="border-top: 1px solid #475569; margin: 1.5rem 0 1rem 0;"></div>

            <div class="form-group">
                <label class="form-label">Farrowing Date Range</label>
                <div style="display: flex; gap: 5px;">
                    <input type="text" id="date_from" class="form-input date-picker" placeholder="Start Date" onchange="if(document.getElementById('pen_id').value) loadAnimals()">
                    <input type="text" id="date_to" class="form-input date-picker" placeholder="End Date" onchange="if(document.getElementById('pen_id').value) loadAnimals()">
                </div>
            </div>
            
            <button class="btn-save" id="btn_save" onclick="saveWeights()" disabled>Save All Weights</button>
        </div>

        <div class="table-area">
            <div style="padding: 1.5rem; border-bottom:1px solid #475569; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                <div>
                    <h2 style="margin:0; font-size:1.25rem;">Weight Entry Table</h2>
                    <p style="margin:5px 0 0 0; color:#94a3b8; font-size:0.9rem;">Review and edit weights (kg).</p>
                </div>
                <div id="count_display" style="color: #64748b; font-weight: 600; font-size: 0.9rem;">0 Animals</div>
            </div>
            
            <div id="table-container">
                <div style="padding: 4rem; text-align: center; color: #64748b;">
                    Select a Pen to load list.
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
            altFormat: "m/d/Y",  
            allowInput: true
        });
    });

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
        const dateFrom = document.getElementById('date_from').value;
        const dateTo = document.getElementById('date_to').value;

        const container = document.getElementById('table-container');
        const saveBtn = document.getElementById('btn_save');
        const countDisplay = document.getElementById('count_display');
        
        if(!id) {
            container.innerHTML = '<div style="padding:4rem; text-align:center; color:#64748b;">Select a Pen.</div>';
            saveBtn.disabled = true;
            return;
        }

        container.innerHTML = '<div style="padding:4rem; text-align:center; color:#94a3b8;">Loading animals...</div>';
        
        const animals = await fetchJson(`?action=get_animals&pen_id=${id}&date_from=${dateFrom}&date_to=${dateTo}`);
        
        if(animals.length === 0) {
            container.innerHTML = '<div style="padding:4rem; text-align:center; color:#ef4444;">No active animals found matching filters.</div>';
            saveBtn.disabled = true;
            countDisplay.innerText = "0 Animals";
            return;
        }

        countDisplay.innerText = animals.length + " Animals";

        let html = `
            <form id="weightForm">
            <table class="w-table">
                <thead>
                    <tr>
                        <th style="padding-left: 1.5rem;">Tag / Info</th>
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
            const sexIcon = a.SEX === 'M' ? '♂' : (a.SEX === 'F' ? '♀' : '');
            
            // Logic to disable weaning weight
            const isWeaningDisabled = classId <= 1;
            const weaningAttr = isWeaningDisabled ? 'disabled title="Not applicable for Class 1 or below"' : '';
            const weaningPlaceholder = isWeaningDisabled ? 'N/A' : '0.00';
            
            html += `
                <tr>
                    <td style="padding-left: 1.5rem;">
                        <div style="font-weight:bold; font-size:1.1rem; color:white; margin-bottom:2px; white-space:nowrap;">${a.TAG_NO}</div>
                        <div style="font-size:0.8rem; color:#64748b; white-space:nowrap;">${sexIcon} ID: ${a.ANIMAL_ID}</div>
                    </td>
                    <td data-label="Farrowing Date" style="color: #cbd5e1; font-weight: 500;">
                        ${a.FMT_BIRTH_DATE}
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
                        diffSpan.innerText = `${sign}${diff.toFixed(2)}`;
                    } else { diffSpan.innerText = ''; }
                } else {
                    diffSpan.className = `diff-tag diff-pos`;
                    diffSpan.innerText = 'New';
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

    // Live Syncs the current weight on every keystroke
    function syncCurrentWeight(birthInput, oldBirth, oldCurrent, animalId) {
        // Only mirror if the database has the old birth weight as exactly 0
        if (Number(oldBirth) === 0) {
            // Safely locate the current weight field by name 
            const currentInput = document.getElementsByName('current_weights[' + animalId + ']')[0];
            
            if (currentInput) {
                // Copy the value live
                currentInput.value = birthInput.value;
                
                // Trigger the visual styling so it highlights green as a "New" weight
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

        if(!hasChanges) { alert("Please enter at least one weight."); return; }
        if(!confirm("Update records with these weights?")) return;

        btn.disabled = true;
        btn.innerText = "Updating...";

        fetch('../process/updateWeights.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if(data.success) {
                alert("✅ " + data.message);
                loadAnimals(); 
            } else {
                alert("❌ Error: " + data.message);
            }
            btn.disabled = false;
            btn.innerText = "Save All Weights";
        })
        .catch(err => {
            console.error(err);
            alert("System Error.");
            btn.disabled = false;
            btn.innerText = "Save All Weights";
        });
    }
</script>

</body>
</html>