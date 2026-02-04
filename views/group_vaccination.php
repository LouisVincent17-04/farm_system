<?php
// views/group_vaccination.php
error_reporting(0);
ini_set('display_errors', 0);
include '../config/Connection.php';

include '../security/checkAccess.php';
checkAccess('group_vaccination');
$page = "transactions";
include '../common/navbar.php';


try {
    if (!isset($conn)) { throw new Exception("Database connection failed."); }

    // Fetch necessary dropdown data
    $vets = $conn->query("SELECT FULL_NAME FROM VETERINARIANS WHERE IS_ACTIVE = 1 ORDER BY FULL_NAME ASC")->fetchAll(PDO::FETCH_ASSOC);
    $locs = $conn->query("SELECT LOCATION_ID, LOCATION_NAME FROM LOCATIONS ORDER BY LOCATION_NAME ASC")->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch Vaccines with stock info
    $vacs = $conn->query("
        SELECT v.SUPPLY_ID, v.SUPPLY_NAME, v.TOTAL_STOCK, u.UNIT_ABBR, v.UNIT_ID 
        FROM VACCINES v 
        LEFT JOIN UNITS u ON v.UNIT_ID = u.UNIT_ID 
        ORDER BY SUPPLY_NAME ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    // Handle error
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Group Vaccination (Custom Dosage)</title>
    <style>
        /* --- GLOBAL STYLES --- */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            min-height: 100vh; color: #e2e8f0;
        }
        .container { max-width: 1600px; margin: 0 auto; padding: 1.5rem; }

        /* --- MAIN GRID LAYOUT --- */
        .main-grid {
            display: grid;
            grid-template-columns: 380px 1fr;
            gap: 1.5rem;
            align-items: start;
        }

        /* --- LEFT PANEL: SETTINGS --- */
        .control-panel {
            background: rgba(30, 41, 59, 0.7);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(148, 163, 184, 0.2);
            border-radius: 16px;
            padding: 1.5rem;
            position: sticky; top: 1.5rem;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3);
        }
        .panel-title { font-size: 1.25rem; font-weight: 700; color: #fff; margin-bottom: 5px; display: flex; align-items: center; gap: 8px; }
        .panel-subtitle { font-size: 0.85rem; color: #94a3b8; margin-bottom: 1.5rem; }

        .form-group { margin-bottom: 1rem; }
        .form-label { display: block; font-size: 0.85rem; color: #cbd5e1; margin-bottom: 0.4rem; font-weight: 500; }
        .form-control {
            width: 100%; padding: 0.75rem;
            background: #0f172a; border: 1px solid #334155;
            border-radius: 8px; color: #fff; font-size: 0.95rem;
            transition: border-color 0.2s;
        }
        .form-control:focus { border-color: #8b5cf6; outline: none; }
        .form-control:disabled { opacity: 0.5; cursor: not-allowed; }

        /* Stock Indicator */
        .stock-indicator { 
            font-size: 0.8rem; margin-top: 5px; padding: 5px 10px; 
            border-radius: 4px; background: rgba(0,0,0,0.2); text-align: center; 
        }
        .stock-ok { color: #4ade80; font-size: 0.85rem; margin-top: 5px; display: block; }
        .stock-low { color: #f87171; font-size: 0.85rem; margin-top: 5px; display: block; }

        /* --- RIGHT PANEL: SELECTION & TABLE --- */
        .workspace-panel { display: flex; flex-direction: column; gap: 1.5rem; }

        /* 1. Animal Picker Grid */
        .picker-section {
            background: rgba(30, 41, 59, 0.4); border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 16px; padding: 1.5rem;
        }
        .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
        .section-title { font-size: 1.1rem; font-weight: 600; color: #fff; }
        
        /* Select All Toggle */
        .select-all-container {
            display: flex; align-items: center; gap: 8px;
            font-size: 0.9rem; color: #a78bfa; cursor: pointer;
            padding: 5px 10px; border-radius: 6px;
            background: rgba(139, 92, 246, 0.1); border: 1px solid rgba(139, 92, 246, 0.2);
        }
        .select-all-container:hover { background: rgba(139, 92, 246, 0.2); }
        .select-all-container input { cursor: pointer; accent-color: #8b5cf6; width: 16px; height: 16px; }

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
        .animal-card.selected { background: rgba(139, 92, 246, 0.2); border-color: #8b5cf6; }
        .animal-card.in-table { opacity: 0.5; pointer-events: none; border-color: #4ade80; } /* Visual cue it's added */

        /* 2. Vaccination List Table */
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
        .custom-table input:focus { border-color: #8b5cf6; outline: none; }
        .btn-remove {
            background: transparent; border: none; color: #f87171;
            cursor: pointer; font-size: 1.1rem; padding: 5px; transition: color 0.2s;
        }
        .btn-remove:hover { color: #ef4444; transform: scale(1.1); }

        /* Summary Box */
        .summary-box {
            margin-top: 1.5rem; background: #0f172a; padding: 1rem;
            border-radius: 12px; border-left: 4px solid #8b5cf6;
        }
        .summary-row { display: flex; justify-content: space-between; margin-bottom: 5px; font-size: 0.9rem; color: #94a3b8; }
        .summary-total { margin-top: 10px; padding-top: 10px; border-top: 1px solid #334155; font-weight: 700; color: #fff; display: flex; justify-content: space-between; }

        .btn-submit {
            width: 100%; margin-top: 1.5rem; padding: 1rem;
            background: linear-gradient(135deg, #8b5cf6, #6d28d9);
            border: none; border-radius: 12px; color: white; font-weight: 700;
            cursor: pointer; transition: all 0.2s;
        }
        .btn-submit:disabled { opacity: 0.5; cursor: not-allowed; filter: grayscale(1); }
        .btn-submit:hover:not(:disabled) { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(139, 92, 246, 0.4); }

        @media (max-width: 1024px) { .main-grid { grid-template-columns: 1fr; } .control-panel { position: relative; top: 0; } }
    </style>
</head>
<body>

<div class="container">
    <div class="main-grid">
        
        <div class="control-panel">
            <div class="panel-title">💉 Group Vaccination</div>
            <div class="panel-subtitle">Configure vaccine batch and default dosage.</div>

            <form id="settingsForm">
                <div style="background:rgba(255,255,255,0.03); padding:10px; border-radius:8px; margin-bottom:1rem; border:1px dashed #475569;">
                    <label class="form-label" style="margin-bottom:8px; color:#a78bfa;">STEP 1: Locate Group</label>
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

                <label class="form-label" style="color:#a78bfa;">STEP 2: Batch Details</label>
                <div class="form-group">
                    <label class="form-label">Vaccine <span style="color:#f87171">*</span></label>
                    <select id="vaccine_id" class="form-control" onchange="updateCalculations()" required>
                        <option value="" data-stock="0">Select Inventory Item</option>
                        <?php foreach($vacs as $v): ?>
                            <option value="<?= $v['SUPPLY_ID'] ?>" 
                                    data-stock="<?= $v['TOTAL_STOCK'] ?>" 
                                    data-unit="<?= $v['UNIT_ABBR'] ?>"
                                    data-unit-id="<?= $v['UNIT_ID'] ?>">
                                <?= htmlspecialchars($v['SUPPLY_NAME']) ?> (<?= $v['TOTAL_STOCK'] ?> <?= $v['UNIT_ABBR'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div id="stock-display"></div>
                </div>

                <div class="form-group">
                    <label class="form-label">Default Dosage <span style="color:#f87171">*</span></label>
                    <div style="display:flex; gap:5px;">
                        <input type="number" id="default_dosage" class="form-control" step="0.01" value="1.00" onchange="updateAllDosages()" placeholder="Qty">
                        <button type="button" onclick="updateAllDosages()" style="background:#334155; border:1px solid #475569; color:#fff; border-radius:8px; padding:0 10px; cursor:pointer;" title="Apply to all rows">Apply All</button>
                    </div>
                    <small style="color:#64748b; font-size:0.75rem;">Can be overridden in table.</small>
                </div>

                <div class="form-group">
                    <label class="form-label">Default Remarks</label>
                    <div style="display:flex; gap:5px;">
                        <input type="text" id="default_remarks" class="form-control" placeholder="e.g. Routine Booster">
                        <button type="button" onclick="updateAllRemarks()" style="background:#334155; border:1px solid #475569; color:#fff; border-radius:8px; padding:0 10px; cursor:pointer;" title="Apply to all rows">Apply All</button>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Veterinarian</label>
                    <select id="vet_name" class="form-control">
                        <?php foreach($vets as $vet): echo "<option value='{$vet['FULL_NAME']}'>{$vet['FULL_NAME']}</option>"; endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Date Administered</label>
                    <input type="datetime-local" id="vaccination_date" class="form-control" step="1">
                </div>

                <div class="summary-box">
                    <div class="summary-row"><span>Animals Selected:</span> <span id="sum-count" style="color:#fff">0</span></div>
                    <div class="summary-total"><span>Total Vol Required:</span> <span id="sum-total" style="color:#a78bfa">0 units</span></div>
                </div>

                <button type="button" class="btn-submit" id="btn-submit" onclick="submitBatch()" disabled>Record Vaccination</button>
            </form>
        </div>

        <div class="workspace-panel">
            
            <div class="picker-section">
                <div class="section-header">
                    <div class="section-title">🐷 Step 3: Click to Add Animals</div>
                    
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
                    <div class="section-title">📋 Step 4: Confirm Dosages</div>
                    <button onclick="clearTable()" style="background:transparent; border:1px solid #f87171; color:#f87171; padding:4px 10px; border-radius:4px; cursor:pointer; font-size:0.8rem;">Clear All</button>
                </div>
                
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th style="width: 15%;">Tag No</th>
                            <th style="width: 25%;">Dosage (Qty)</th>
                            <th>Remarks (Optional)</th>
                            <th style="width: 50px;"></th>
                        </tr>
                    </thead>
                    <tbody id="vaccination-list">
                        <tr id="empty-row"><td colspan="4" style="text-align:center; padding:2rem; color:#64748b;">No animals added yet.</td></tr>
                    </tbody>
                </table>
            </div>

        </div>
    </div>
</div>

<script>
    // --- STATE MANAGEMENT ---
    let selectedAnimals = new Set(); // Stores IDs of animals currently in the table
    let currentPenAnimals = []; // Stores full animal objects for the current pen

    document.addEventListener('DOMContentLoaded', () => {
        const now = new Date();
        now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
        document.getElementById('vaccination_date').value = now.toISOString().slice(0, 19);
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
        selectAllWrapper.style.display = 'none'; // Hide checkbox during load
        
        fetch(`../process/getAnimalsByPen.php?pen_id=${penId}`)
            .then(r=>r.json())
            .then(data => {
                grid.innerHTML = '';
                currentPenAnimals = []; // Reset local cache

                const animals = data.animal_record || [];
                
                // Filter active only
                animals.forEach(a => {
                    if(a.IS_ACTIVE == 1) currentPenAnimals.push(a);
                });

                if(currentPenAnimals.length === 0) {
                    grid.innerHTML = '<div style="grid-column:1/-1; text-align:center; color:#94a3b8;">No animals found in this pen.</div>';
                    return;
                }

                // Show Select All Checkbox
                selectAllWrapper.style.display = 'flex';
                updateSelectAllState(); // Sync checkbox state

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
            // Select All
            currentPenAnimals.forEach(animal => {
                if(!selectedAnimals.has(animal.ANIMAL_ID)) {
                    addAnimalToTable(animal);
                }
            });
        } else {
            // Deselect All (Only those currently in the pen)
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

        // Check if ALL visible animals are selected
        const allSelected = currentPenAnimals.every(a => selectedAnimals.has(a.ANIMAL_ID));
        checkbox.checked = allSelected;
    }

    // --- TABLE OPERATIONS ---
    function addAnimalToTable(animal) {
        if(selectedAnimals.has(animal.ANIMAL_ID)) return; // Prevent duplicates

        // Remove empty state row
        const emptyRow = document.getElementById('empty-row');
        if(emptyRow) emptyRow.remove();

        const tbody = document.getElementById('vaccination-list');
        const defaultDose = document.getElementById('default_dosage').value;
        const defaultRem = document.getElementById('default_remarks').value;

        const tr = document.createElement('tr');
        tr.id = `row-${animal.ANIMAL_ID}`;
        tr.dataset.id = animal.ANIMAL_ID; 
        tr.innerHTML = `
            <td style="font-weight:600; color:#fff;">${animal.TAG_NO}</td>
            <td>
                <input type="number" class="dosage-input" name="dosages[${animal.ANIMAL_ID}]" 
                       value="${defaultDose}" step="0.01" min="0.01" oninput="updateCalculations()">
            </td>
            <td>
                <input type="text" name="remarks[${animal.ANIMAL_ID}]" value="${defaultRem}" placeholder="Notes...">
            </td>
            <td style="text-align:center;">
                <button type="button" class="btn-remove" onclick="removeAnimal(${animal.ANIMAL_ID})">×</button>
            </td>
        `;
        tbody.appendChild(tr);

        // Update State
        selectedAnimals.add(animal.ANIMAL_ID);
        
        // Update Visuals in Grid
        const card = document.getElementById('card-' + animal.ANIMAL_ID);
        if(card) card.classList.add('in-table');

        updateCalculations();
        updateSelectAllState(); // Update checkbox
    }

    function removeAnimal(id) {
        const row = document.getElementById(`row-${id}`);
        if(row) row.remove();

        selectedAnimals.delete(String(id)); 
        selectedAnimals.delete(id); 

        // Re-enable card in grid
        const card = document.getElementById(`card-${id}`);
        if(card) card.classList.remove('in-table');

        // Check if table empty
        if(selectedAnimals.size === 0) {
            document.getElementById('vaccination-list').innerHTML = '<tr id="empty-row"><td colspan="4" style="text-align:center; padding:2rem; color:#64748b;">No animals added yet.</td></tr>';
        }

        updateCalculations();
        updateSelectAllState(); // Update checkbox
    }

    function clearTable() {
        if(!confirm("Clear all rows?")) return;
        Array.from(selectedAnimals).forEach(id => removeAnimal(id));
    }

    function updateAllDosages() {
        const newDose = document.getElementById('default_dosage').value;
        const inputs = document.querySelectorAll('.dosage-input');
        inputs.forEach(inp => inp.value = newDose);
        updateCalculations();
    }

    // NEW FUNCTION: Apply remarks to all rows
    function updateAllRemarks() {
        const newRemark = document.getElementById('default_remarks').value;
        const inputs = document.querySelectorAll('input[name^="remarks"]');
        inputs.forEach(inp => inp.value = newRemark);
    }

    // --- CALCULATIONS & VALIDATION ---
    function updateCalculations() {
        const vaccineSelect = document.getElementById('vaccine_id');
        const selectedOpt = vaccineSelect.options[vaccineSelect.selectedIndex];
        
        const stock = parseFloat(selectedOpt.getAttribute('data-stock')) || 0;
        const unit = selectedOpt.getAttribute('data-unit') || 'units';
        
        // Sum all dosage inputs
        let totalNeeded = 0;
        document.querySelectorAll('.dosage-input').forEach(inp => {
            totalNeeded += parseFloat(inp.value) || 0;
        });

        // Update UI
        document.getElementById('sum-count').innerText = selectedAnimals.size;
        document.getElementById('sum-total').innerText = totalNeeded.toFixed(2) + " " + unit;

        const stockDisplay = document.getElementById('stock-display');
        const submitBtn = document.getElementById('btn-submit');

        if(!vaccineSelect.value) {
            stockDisplay.innerHTML = '';
            submitBtn.disabled = true;
            return;
        }

        if(totalNeeded > stock) {
            stockDisplay.innerHTML = `<div class="stock-low">Stock Low: ${stock} (Need ${totalNeeded.toFixed(2)})</div>`;
            submitBtn.innerText = "Insufficient Stock";
            submitBtn.disabled = true;
        } else {
            stockDisplay.innerHTML = `<div class="stock-ok">Stock Available: ${stock} ${unit}</div>`;
            if(selectedAnimals.size > 0 && totalNeeded > 0) {
                submitBtn.innerText = "Record Vaccination";
                submitBtn.disabled = false;
            } else {
                submitBtn.innerText = "Add Animals / Set Dosage";
                submitBtn.disabled = true;
            }
        }
    }

    // --- SUBMISSION ---
    function submitBatch() {
        if(!confirm("Proceed with vaccination for " + selectedAnimals.size + " animals? Inventory will be deducted.")) return;

        const btn = document.getElementById('btn-submit');
        btn.disabled = true; btn.innerText = "Processing...";

        // Collect Table Data
        const records = [];
        document.querySelectorAll('#vaccination-list tr[id^="row-"]').forEach(tr => {
            const id = tr.dataset.id;
            const dose = tr.querySelector('.dosage-input').value;
            const note = tr.querySelector('input[name^="remarks"]').value;
            records.push({ animal_id: id, quantity: dose, remarks: note });
        });

        // Common Data
        const vaccineOpt = document.getElementById('vaccine_id').selectedOptions[0];
        const data = {
            records: records,
            vaccine_id: vaccineOpt.value,
            unit_id: vaccineOpt.getAttribute('data-unit-id'),
            vet_name: document.getElementById('vet_name').value,
            date: document.getElementById('vaccination_date').value
        };

        fetch('../process/addBatchVaccination.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        })
        .then(r => r.json())
        .then(res => {
            if(res.success) {
                alert("✅ Batch Recorded!");
                location.reload();
            } else {
                alert("❌ Error: " + res.message);
                btn.disabled = false; btn.innerText = "Record Vaccination";
            }
        })
        .catch(err => {
            console.error(err);
            alert("System Error");
            btn.disabled = false; btn.innerText = "Record Vaccination";
        });
    }
</script>

</body>
</html>