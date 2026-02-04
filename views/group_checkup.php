<?php
// views/group_checkup.php
error_reporting(0);
ini_set('display_errors', 0);
include '../config/Connection.php';

include '../security/checkAccess.php';
checkAccess('group_checkup');
$page = "transactions";
include '../common/navbar.php';


try {
    if (!isset($conn)) { throw new Exception("Database connection failed."); }

    $vets = $conn->query("SELECT FULL_NAME FROM VETERINARIANS WHERE IS_ACTIVE = 1 ORDER BY FULL_NAME ASC")->fetchAll(PDO::FETCH_ASSOC);
    $locs = $conn->query("SELECT LOCATION_ID, LOCATION_NAME FROM LOCATIONS ORDER BY LOCATION_NAME ASC")->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    // Handle error
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Group Check-up (Log Only)</title>
    <style>
        /* --- CORE STYLES --- */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            min-height: 100vh; color: #e2e8f0;
        }
        .container { max-width: 1600px; margin: 0 auto; padding: 1.5rem; }

        /* --- BACK LINK STYLE --- */
        .back-link {
            display: inline-flex; align-items: center; gap: 8px; 
            text-decoration: none; color: #94a3b8; font-weight: 600; 
            font-size: 0.95rem; margin-bottom: 20px; transition: color 0.2s;
        }
        .back-link:hover { color: white; }

        .main-grid { display: grid; grid-template-columns: 380px 1fr; gap: 1.5rem; align-items: start; }

        /* --- LEFT PANEL --- */
        .control-panel {
            background: rgba(30, 41, 59, 0.7); backdrop-filter: blur(12px);
            border: 1px solid rgba(148, 163, 184, 0.2); border-radius: 16px;
            padding: 1.5rem; position: sticky; top: 1.5rem;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3);
        }
        .panel-title { font-size: 1.25rem; font-weight: 700; color: #fff; margin-bottom: 5px; }
        .panel-subtitle { font-size: 0.85rem; color: #94a3b8; margin-bottom: 1.5rem; }

        .form-group { margin-bottom: 1rem; }
        .form-label { display: block; font-size: 0.85rem; color: #cbd5e1; margin-bottom: 0.4rem; font-weight: 500; }
        .form-control {
            width: 100%; padding: 0.75rem; background: #0f172a;
            border: 1px solid #334155; border-radius: 8px; color: #fff;
            font-size: 0.95rem; transition: border-color 0.2s;
        }
        .form-control:focus { border-color: #06b6d4; outline: none; }
        .form-control:disabled { opacity: 0.5; cursor: not-allowed; }

        /* --- RIGHT PANEL --- */
        .workspace-panel { display: flex; flex-direction: column; gap: 1.5rem; }

        .picker-section {
            background: rgba(30, 41, 59, 0.4); border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 16px; padding: 1.5rem;
        }
        .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
        .section-title { font-size: 1.1rem; font-weight: 600; color: #fff; }

        /* Select All Toggle */
        .select-all-container {
            display: flex; align-items: center; gap: 8px;
            font-size: 0.9rem; color: #22d3ee; cursor: pointer;
            padding: 5px 10px; border-radius: 6px;
            background: rgba(6, 182, 212, 0.1); border: 1px solid rgba(6, 182, 212, 0.2);
        }
        .select-all-container:hover { background: rgba(6, 182, 212, 0.2); }
        .select-all-container input { cursor: pointer; accent-color: #06b6d4; width: 16px; height: 16px; }
        
        .animal-grid {
            display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 0.75rem; max-height: 250px; overflow-y: auto; padding-right: 5px;
        }
        .animal-card {
            background: #1e293b; border: 1px solid #334155; border-radius: 8px;
            padding: 0.75rem; cursor: pointer; text-align: center; transition: all 0.2s;
            position: relative;
        }
        .animal-card:hover { border-color: #94a3b8; transform: translateY(-2px); }
        .animal-card.selected { background: rgba(6, 182, 212, 0.2); border-color: #06b6d4; }
        .animal-card.in-table { opacity: 0.5; pointer-events: none; border-color: #4ade80; } 

        .table-section {
            background: #1e293b; border: 1px solid #334155; border-radius: 16px;
            overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        .custom-table { width: 100%; border-collapse: collapse; }
        .custom-table th {
            background: #0f172a; color: #94a3b8; font-size: 0.8rem; text-transform: uppercase;
            padding: 1rem; text-align: left; font-weight: 600; border-bottom: 1px solid #334155;
        }
        .custom-table td {
            padding: 0.75rem 1rem; border-bottom: 1px solid rgba(255,255,255,0.05);
            vertical-align: middle; color: #e2e8f0; font-size: 0.95rem;
        }
        .custom-table input {
            background: #0f172a; border: 1px solid #475569; color: #fff;
            padding: 6px 10px; border-radius: 6px; width: 100%;
        }
        .custom-table input:focus { border-color: #06b6d4; outline: none; }
        
        .btn-remove {
            background: transparent; border: none; color: #f87171;
            cursor: pointer; font-size: 1.1rem; padding: 5px; transition: color 0.2s;
        }
        .btn-remove:hover { color: #ef4444; transform: scale(1.1); }

        .summary-box {
            margin-top: 1.5rem; background: #0f172a; padding: 1rem;
            border-radius: 12px; border-left: 4px solid #06b6d4;
        }
        .summary-row { display: flex; justify-content: space-between; margin-bottom: 5px; font-size: 0.9rem; color: #94a3b8; }
        
        /* Buttons */
        .btn-mini {
            background: #334155; border: 1px solid #475569; color: #fff;
            border-radius: 8px; padding: 8px 12px; cursor: pointer; font-size: 0.8rem;
            white-space: nowrap; transition: 0.2s; flex-shrink: 0;
        }
        .btn-mini:hover { background: #475569; border-color: #94a3b8; }

        .btn-submit {
            width: 100%; margin-top: 1.5rem; padding: 1rem;
            background: linear-gradient(135deg, #06b6d4, #0891b2);
            border: none; border-radius: 12px; color: white; font-weight: 700;
            cursor: pointer; transition: all 0.2s;
        }
        .btn-submit:disabled { opacity: 0.5; cursor: not-allowed; filter: grayscale(1); }
        .btn-submit:hover:not(:disabled) { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(6, 182, 212, 0.4); }

        @media (max-width: 1024px) { .main-grid { grid-template-columns: 1fr; } .control-panel { position: relative; top: 0; } }
    </style>
</head>
<body>

<div class="container">
    
    <a href="transactions.php" class="back-link">
        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
        Back to Transactions
    </a>

    <div class="main-grid">
        
        <div class="control-panel">
            <div class="panel-title">🩺 Group Check-up</div>
            <div class="panel-subtitle">Log inspections for multiple animals at once.</div>

            <form id="settingsForm">
                <div style="background:rgba(255,255,255,0.03); padding:10px; border-radius:8px; margin-bottom:1rem; border:1px dashed #475569;">
                    <label class="form-label" style="margin-bottom:8px; color:#22d3ee;">STEP 1: Locate Group</label>
                    <div class="form-group" style="margin-bottom:0.5rem;">
                        <select id="location_id" class="form-control" onchange="loadBuildings(this.value)">
                            <option value="">Select Location</option>
                            <?php foreach($locs as $l): echo "<option value='{$l['LOCATION_ID']}'>{$l['LOCATION_NAME']}</option>"; endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom:0.5rem;">
                        <select id="building_id" class="form-control" onchange="loadPens(this.value)" disabled><option>Select Building</option></select>
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <select id="pen_id" class="form-control" onchange="loadAnimals(this.value)" disabled><option>Select Pen</option></select>
                    </div>
                </div>

                <label class="form-label" style="color:#22d3ee;">STEP 2: Inspection Details</label>
                
                <div class="form-group">
                    <label class="form-label">Default Remarks</label>
                    <div style="display:flex; gap:8px;">
                        <input type="text" id="default_remarks" class="form-control" placeholder="e.g. Routine Inspection">
                        <button type="button" class="btn-mini" onclick="updateAllRemarks()">Apply All</button>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Veterinarian <span style="color:#f87171">*</span></label>
                    <select id="vet_name" class="form-control" onchange="updateCalculations()" required>
                        <option value="">Select Veterinarian</option>
                        <?php foreach($vets as $vet): echo "<option value='{$vet['FULL_NAME']}'>{$vet['FULL_NAME']}</option>"; endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Inspect Fee / Head (Optional)</label>
                    <input type="number" id="service_fee" class="form-control" step="0.01" min="0" placeholder="0.00" value="0">
                </div>

                <div class="form-group">
                    <label class="form-label">Date of Inspection</label>
                    <input type="datetime-local" id="checkup_date" class="form-control" step="1">
                </div>

                <div class="summary-box">
                    <div class="summary-row">
                        <span style="color:#fff; font-weight:600;">Total Animals:</span> 
                        <span id="sum-count" style="color:#22d3ee; font-weight:700;">0</span>
                    </div>
                </div>

                <button type="button" class="btn-submit" id="btn-submit" onclick="submitBatch()" disabled>Save Log</button>
            </form>
        </div>

        <div class="workspace-panel">
            
            <div class="picker-section">
                <div class="section-header">
                    <div class="section-title">🩺 Step 3: Select Animals</div>
                    
                    <label class="select-all-container" style="display:none;" id="select-all-wrapper">
                        <input type="checkbox" id="select-all-check" onchange="toggleSelectAll(this)"> Select All
                    </label>
                </div>
                <div id="animal-grid" class="animal-grid">
                    <div style="grid-column:1/-1; text-align:center; padding:2rem; color:#64748b; border:1px dashed #475569; border-radius:8px;">
                        Select a Pen from the left to load animals.
                    </div>
                </div>
            </div>

            <div class="table-section">
                <div class="section-header" style="padding:1rem; border-bottom:1px solid #334155; margin-bottom:0;">
                    <div class="section-title">📋 Step 4: Add Remarks (Optional)</div>
                    <button onclick="clearTable()" style="background:transparent; border:1px solid #f87171; color:#f87171; padding:4px 10px; border-radius:4px; cursor:pointer; font-size:0.8rem;">Clear All</button>
                </div>
                
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th style="width: 20%;">Tag No</th>
                            <th>Remarks / Observations</th>
                            <th style="width: 50px;"></th>
                        </tr>
                    </thead>
                    <tbody id="checkup-list">
                        <tr id="empty-row"><td colspan="3" style="text-align:center; padding:2rem; color:#64748b;">No animals added yet.</td></tr>
                    </tbody>
                </table>
            </div>

        </div>
    </div>
</div>

<script>
    // --- STATE MANAGEMENT ---
    let selectedAnimals = new Set(); 
    let currentPenAnimals = []; // Stores full animal objects for current pen

    document.addEventListener('DOMContentLoaded', () => {
        const now = new Date();
        now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
        document.getElementById('checkup_date').value = now.toISOString().slice(0, 19);
    });

    // --- CASCADING DROPDOWNS ---
    function loadBuildings(locId) {
        document.getElementById('building_id').innerHTML = '<option>Loading...</option>';
        document.getElementById('pen_id').innerHTML = '<option>Select Pen</option>';
        document.getElementById('pen_id').disabled = true;
        
        if(!locId) { document.getElementById('building_id').innerHTML = '<option>Select Building</option>'; return; }

        fetch(`../process/getBuildingsByLocation.php?location_id=${locId}`)
            .then(r=>r.json())
            .then(data => {
                const bldg = document.getElementById('building_id');
                bldg.innerHTML = '<option value="">Select Building</option>';
                data.buildings.forEach(b => bldg.add(new Option(b.BUILDING_NAME, b.BUILDING_ID)));
                bldg.disabled = false;
            });
    }

    function loadPens(bldgId) {
        document.getElementById('pen_id').innerHTML = '<option>Loading...</option>';
        fetch(`../process/getPensByBuilding.php?building_id=${bldgId}`)
            .then(r=>r.json())
            .then(data => {
                const pen = document.getElementById('pen_id');
                pen.innerHTML = '<option value="">Select Pen</option>';
                data.pens.forEach(p => pen.add(new Option(p.PEN_NAME, p.PEN_ID)));
                pen.disabled = false;
            });
    }

    // --- LOAD GRID ---
    function loadAnimals(penId) {
        const grid = document.getElementById('animal-grid');
        const selectAllWrapper = document.getElementById('select-all-wrapper');

        grid.innerHTML = '<div style="grid-column:1/-1; text-align:center; color:#94a3b8;">Loading...</div>';
        selectAllWrapper.style.display = 'none';

        fetch(`../process/getAnimalsByPen.php?pen_id=${penId}`)
            .then(r=>r.json())
            .then(data => {
                grid.innerHTML = '';
                currentPenAnimals = []; // Reset list

                const animals = data.animal_record || [];
                
                // Filter active animals
                animals.forEach(a => {
                    if(a.IS_ACTIVE == 1) currentPenAnimals.push(a);
                });

                if(currentPenAnimals.length === 0) {
                    grid.innerHTML = '<div style="grid-column:1/-1; text-align:center; color:#94a3b8;">No animals found.</div>';
                    return;
                }

                // Show Select All Checkbox
                selectAllWrapper.style.display = 'flex';
                updateSelectAllState();

                currentPenAnimals.forEach(a => {
                    const card = document.createElement('div');
                    card.className = `animal-card ${selectedAnimals.has(a.ANIMAL_ID) ? 'in-table' : ''}`;
                    card.id = `card-${a.ANIMAL_ID}`;
                    card.onclick = () => addAnimalToTable(a);
                    card.innerHTML = `
                        <div style="font-size:1.5rem;">🐖</div>
                        <div style="font-weight:700; color:#fff;">${a.TAG_NO}</div>
                    `;
                    grid.appendChild(card);
                });
            });
    }

    // --- SELECT ALL LOGIC ---
    function toggleSelectAll(checkbox) {
        if(checkbox.checked) {
            currentPenAnimals.forEach(animal => {
                if(!selectedAnimals.has(animal.ANIMAL_ID)) {
                    addAnimalToTable(animal);
                }
            });
        } else {
            currentPenAnimals.forEach(animal => {
                if(selectedAnimals.has(animal.ANIMAL_ID)) {
                    removeAnimal(animal.ANIMAL_ID);
                }
            });
        }
    }

    function updateSelectAllState() {
        const checkbox = document.getElementById('select-all-check');
        if(currentPenAnimals.length === 0) {
            checkbox.checked = false;
            return;
        }
        // Check if all displayed animals are in selected set
        const allSelected = currentPenAnimals.every(a => selectedAnimals.has(a.ANIMAL_ID));
        checkbox.checked = allSelected;
    }

    // --- TABLE OPERATIONS ---
    function addAnimalToTable(animal) {
        if(selectedAnimals.has(animal.ANIMAL_ID)) return;

        const emptyRow = document.getElementById('empty-row');
        if(emptyRow) emptyRow.remove();

        const tbody = document.getElementById('checkup-list');
        const defaultRem = document.getElementById('default_remarks').value;

        const tr = document.createElement('tr');
        tr.id = `row-${animal.ANIMAL_ID}`;
        tr.dataset.id = animal.ANIMAL_ID;
        
        tr.innerHTML = `
            <td style="font-weight:600; color:#fff;">${animal.TAG_NO}</td>
            <td>
                <input type="text" name="remarks[${animal.ANIMAL_ID}]" value="${defaultRem}" placeholder="Routine check (Optional)...">
            </td>
            <td style="text-align:center;">
                <button type="button" class="btn-remove" onclick="removeAnimal(${animal.ANIMAL_ID})">×</button>
            </td>
        `;
        tbody.appendChild(tr);
        selectedAnimals.add(animal.ANIMAL_ID);
        
        // Update Grid Visuals
        const card = document.getElementById(`card-${animal.ANIMAL_ID}`);
        if(card) card.classList.add('in-table');

        updateCalculations();
        updateSelectAllState();
    }

    function removeAnimal(id) {
        document.getElementById(`row-${id}`).remove();
        
        selectedAnimals.delete(String(id));
        selectedAnimals.delete(id);
        
        const card = document.getElementById(`card-${id}`);
        if(card) card.classList.remove('in-table');

        if(selectedAnimals.size === 0) {
            document.getElementById('checkup-list').innerHTML = '<tr id="empty-row"><td colspan="3" style="text-align:center; padding:2rem; color:#64748b;">No animals added yet.</td></tr>';
        }
        updateCalculations();
        updateSelectAllState();
    }

    function clearTable() {
        if(!confirm("Clear all rows?")) return;
        Array.from(selectedAnimals).forEach(id => removeAnimal(id));
    }

    // --- BULK UPDATES ---
    function updateAllRemarks() {
        const newRemark = document.getElementById('default_remarks').value;
        document.querySelectorAll('input[name^="remarks"]').forEach(inp => inp.value = newRemark);
    }

    // --- CALCULATIONS ---
    function updateCalculations() {
        const count = selectedAnimals.size;
        document.getElementById('sum-count').innerText = count;

        const btn = document.getElementById('btn-submit');
        const vet = document.getElementById('vet_name').value;

        // ENABLE button only if animals are selected AND vet is chosen
        if(count > 0 && vet) {
            btn.disabled = false;
            btn.innerText = `Save Log (${count})`;
        } else {
            btn.disabled = true;
            btn.innerText = "Select Animals & Vet";
        }
    }

    // --- SUBMISSION ---
    function submitBatch() {
        if(!confirm("Save check-up records for " + selectedAnimals.size + " animals?")) return;

        const btn = document.getElementById('btn-submit');
        btn.disabled = true; btn.innerText = "Processing...";

        const records = [];
        document.querySelectorAll('#checkup-list tr[id^="row-"]').forEach(tr => {
            records.push({
                animal_id: tr.dataset.id,
                remarks: tr.querySelector('input[name^="remarks"]').value
            });
        });

        const data = {
            records: records,
            vet_name: document.getElementById('vet_name').value,
            cost: document.getElementById('service_fee').value,
            date: document.getElementById('checkup_date').value
        };

        fetch('../process/addBatchCheckup.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        })
        .then(r => r.json())
        .then(res => {
            if(res.success) {
                alert("✅ Logs Recorded!");
                location.reload();
            } else {
                alert("❌ Error: " + res.message);
                btn.disabled = false;
            }
        })
        .catch(err => {
            console.error(err);
            alert("System Error");
            btn.disabled = false;
        });
    }
</script>

</body>
</html>