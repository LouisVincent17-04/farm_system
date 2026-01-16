<?php
// views/group_medication.php
error_reporting(0);
ini_set('display_errors', 0);

$page = "transactions";
include '../common/navbar.php';
include '../config/Connection.php';
include '../security/checkRole.php';
checkRole(3); // Allow Employee (3) or higher

try {
    if (!isset($conn)) { throw new Exception("Database connection failed."); }

    // 1. Fetch Locations
    $locs = $conn->query("SELECT LOCATION_ID, LOCATION_NAME FROM LOCATIONS ORDER BY LOCATION_NAME ASC")->fetchAll(PDO::FETCH_ASSOC);

    // 2. Fetch Medicines (Inventory)
    $meds = $conn->query("
        SELECT m.SUPPLY_ID, m.SUPPLY_NAME, m.TOTAL_STOCK, u.UNIT_ABBR, m.UNIT_ID 
        FROM MEDICINES m 
        LEFT JOIN UNITS u ON m.UNIT_ID = u.UNIT_ID 
        ORDER BY SUPPLY_NAME ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    // Handle error silently
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Group Medication</title>
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
            z-index: 10;
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

        /* --- RIGHT PANEL: SELECTION & TABLE --- */
        .workspace-panel { display: flex; flex-direction: column; gap: 1.5rem; }

        /* 1. Animal Picker Grid */
        .picker-section {
            background: rgba(30, 41, 59, 0.4); border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 16px; padding: 1.5rem;
        }
        .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
        .section-title { font-size: 1.1rem; font-weight: 600; color: #fff; }
        
        .animal-grid {
            display: grid; grid-template-columns: repeat(auto-fill, minmax(100px, 1fr)); /* Adjusted for mobile */
            gap: 0.75rem; max-height: 250px; overflow-y: auto; padding-right: 5px;
        }
        .animal-card {
            background: #1e293b; border: 1px solid #334155; border-radius: 8px;
            padding: 0.75rem; cursor: pointer; text-align: center; transition: all 0.2s;
            position: relative;
        }
        .animal-card:hover { border-color: #94a3b8; transform: translateY(-2px); }
        .animal-card.selected { background: rgba(139, 92, 246, 0.2); border-color: #8b5cf6; }
        .animal-card.in-table { opacity: 0.5; pointer-events: none; border-color: #4ade80; } 

        /* 2. List Table */
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
        
        /* Table Inputs */
        .custom-table select, .custom-table input {
            background: #0f172a; border: 1px solid #475569; color: #fff;
            padding: 8px 10px; border-radius: 6px; width: 100%; font-size: 0.9rem;
        }
        .custom-table input:focus, .custom-table select:focus { border-color: #8b5cf6; outline: none; }
        
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
        .summary-total { margin-top: 10px; padding-top: 10px; border-top: 1px solid #334155; font-weight: 700; color: #fff; display: block; }
        
        /* Buttons */
        .btn-mini {
            background: #334155; border: 1px solid #475569; color: #fff;
            border-radius: 8px; padding: 8px 12px; cursor: pointer; font-size: 0.8rem;
            white-space: nowrap; transition: 0.2s; flex-shrink: 0;
        }
        .btn-mini:hover { background: #475569; border-color: #94a3b8; }

        .btn-submit {
            width: 100%; margin-top: 1.5rem; padding: 1rem;
            background: linear-gradient(135deg, #8b5cf6, #6d28d9);
            border: none; border-radius: 12px; color: white; font-weight: 700;
            cursor: pointer; transition: all 0.2s; font-size: 1rem;
        }
        .btn-submit:disabled { opacity: 0.5; cursor: not-allowed; filter: grayscale(1); }
        .btn-submit:hover:not(:disabled) { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(139, 92, 246, 0.4); }

        /* --- MOBILE RESPONSIVENESS --- */
        @media (max-width: 1024px) {
            .container { padding: 1rem; }
            .main-grid { grid-template-columns: 1fr; gap: 1rem; }
            .control-panel { position: static; margin-bottom: 1rem; }
            
            /* Table Card Transformation */
            .custom-table, .custom-table tbody, .custom-table tr, .custom-table td {
                display: block; width: 100%;
            }
            .custom-table thead { display: none; } /* Hide Headers */
            
            .custom-table tr {
                background: rgba(30, 41, 59, 0.3);
                margin-bottom: 1rem;
                border: 1px solid #334155;
                border-radius: 12px;
                padding: 1rem;
                position: relative;
            }
            
            .custom-table td {
                padding: 8px 0;
                display: flex; justify-content: space-between; align-items: center;
                border-bottom: 1px solid rgba(255,255,255,0.05);
                text-align: right;
            }
            .custom-table td:last-child { border-bottom: none; justify-content: flex-end; }
            
            /* Data Labels */
            .custom-table td::before {
                content: attr(data-label);
                font-weight: 600; font-size: 0.85rem; color: #94a3b8;
                text-transform: uppercase; margin-right: 1rem;
            }
            
            .custom-table select, .custom-table input { width: 60%; }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="main-grid">
        
        <div class="control-panel">
            <div class="panel-title">💊 Group Medication</div>
            <div class="panel-subtitle">Mass treatment distribution.</div>

            <form id="settingsForm">
                <div style="background:rgba(255,255,255,0.03); padding:15px; border-radius:8px; margin-bottom:1.5rem; border:1px dashed #475569;">
                    <label class="form-label" style="margin-bottom:8px; color:#c4b5fd;">STEP 1: Locate Group</label>
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

                <label class="form-label" style="color:#c4b5fd;">STEP 2: Default Settings</label>
                
                <div class="form-group">
                    <label class="form-label">Default Medication <span style="color:#f87171">*</span></label>
                    <div style="display:flex; gap:8px;">
                        <select id="default_item" class="form-control" onchange="updateAllItems()">
                            <option value="">Select Medicine</option>
                            <?php foreach($meds as $m): ?>
                                <option value="<?= $m['SUPPLY_ID'] ?>">
                                    <?= htmlspecialchars($m['SUPPLY_NAME']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" class="btn-mini" onclick="updateAllItems()">Apply</button>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Default Dosage <span style="color:#94a3b8; font-size:0.8em;">(e.g. 5ml)</span></label>
                    <div style="display:flex; gap:8px;">
                        <input type="text" id="default_dosage" class="form-control" placeholder="e.g. 5ml" onchange="updateAllDosages()">
                        <button type="button" class="btn-mini" onclick="updateAllDosages()">Apply</button>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Default Qty / Head <span style="color:#f87171">*</span></label>
                    <div style="display:flex; gap:8px;">
                        <input type="number" id="default_qty" class="form-control" step="0.01" min="0.01" value="1.00" onchange="updateAllQuantities()" placeholder="Qty">
                        <button type="button" class="btn-mini" onclick="updateAllQuantities()">Apply</button>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Date Administered</label>
                    <input type="datetime-local" id="txn_date" class="form-control">
                </div>

                <div class="summary-box">
                    <div class="summary-row">
                        <span>Animals Selected:</span> 
                        <span id="sum-count" style="color:#fff">0</span>
                    </div>
                    <div class="summary-total">
                        <div id="stock-warning" style="font-size:0.85rem; color:#f472b6; font-weight:normal;"></div>
                    </div>
                </div>

                <button type="button" class="btn-submit" id="btn-submit" onclick="submitBatch()" disabled>Record Treatments</button>
            </form>
        </div>

        <div class="workspace-panel">
            
            <div class="picker-section">
                <div class="section-header">
                    <div class="section-title">🐖 Step 3: Select Animals</div>
                    <div style="font-size:0.85rem; color:#94a3b8;">Tap to select</div>
                </div>
                <div id="animal-grid" class="animal-grid">
                    <div style="grid-column:1/-1; text-align:center; padding:2rem; color:#64748b; border:1px dashed #475569; border-radius:8px;">
                        Select a Pen from the left to load animals.
                    </div>
                </div>
            </div>

            <div class="table-section">
                <div class="section-header" style="padding:1rem; border-bottom:1px solid #334155; margin-bottom:0;">
                    <div class="section-title">📋 Step 4: Confirm Details</div>
                    <button onclick="clearTable()" style="background:transparent; border:1px solid #f87171; color:#f87171; padding:6px 12px; border-radius:6px; cursor:pointer; font-size:0.85rem;">Clear All</button>
                </div>
                
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th style="width: 15%;">Tag No</th>
                            <th style="width: 30%;">Medication</th>
                            <th style="width: 15%;">Dosage</th> 
                            <th style="width: 10%;">Qty</th>
                            <th>Remarks</th>
                            <th style="width: 40px;"></th>
                        </tr>
                    </thead>
                    <tbody id="medication-list">
                        <tr id="empty-row"><td colspan="6" style="text-align:center; padding:2rem; color:#64748b;">No animals added yet.</td></tr>
                    </tbody>
                </table>
            </div>

        </div>
    </div>
</div>

<script>
    // --- DATA STORE ---
    let selectedAnimals = new Set(); 
    const inventory = {};
    <?php foreach($meds as $m): ?>
        inventory[<?= $m['SUPPLY_ID'] ?>] = {
            name: "<?= htmlspecialchars($m['SUPPLY_NAME']) ?>",
            stock: <?= $m['TOTAL_STOCK'] ?>,
            unit: "<?= $m['UNIT_ABBR'] ?>",
            unit_id: <?= $m['UNIT_ID'] ?>
        };
    <?php endforeach; ?>

    document.addEventListener('DOMContentLoaded', () => {
        const now = new Date();
        now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
        document.getElementById('txn_date').value = now.toISOString().slice(0, 16);
    });

    // --- 1. CASCADING DROPDOWNS ---
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

    // --- 2. LOAD GRID ---
    function loadAnimals(penId) {
        const grid = document.getElementById('animal-grid');
        grid.innerHTML = '<div style="grid-column:1/-1; text-align:center; color:#94a3b8;">Loading...</div>';
        
        fetch(`../process/getAnimalsByPen.php?pen_id=${penId}`)
            .then(r=>r.json())
            .then(data => {
                grid.innerHTML = '';
                const animals = data.animal_record || [];
                if(animals.length === 0) {
                    grid.innerHTML = '<div style="grid-column:1/-1; text-align:center; color:#94a3b8;">No animals found.</div>';
                    return;
                }

                animals.forEach(a => {
                    if(a.IS_ACTIVE != 0) {
                        const card = document.createElement('div');
                        card.className = `animal-card ${selectedAnimals.has(a.ANIMAL_ID) ? 'in-table' : ''}`;
                        card.id = `card-${a.ANIMAL_ID}`;
                        card.onclick = () => addAnimalToTable(a);
                        card.innerHTML = `
                            <div style="font-size:1.5rem;">🐖</div>
                            <div style="font-weight:700; color:#fff;">${a.TAG_NO}</div>
                        `;
                        grid.appendChild(card);
                    }
                });
            });
    }

    // --- 3. TABLE OPERATIONS ---
    function addAnimalToTable(animal) {
        if(selectedAnimals.has(animal.ANIMAL_ID)) return;

        const emptyRow = document.getElementById('empty-row');
        if(emptyRow) emptyRow.remove();

        const tbody = document.getElementById('medication-list');
        const defaultQty = document.getElementById('default_qty').value;
        const defaultItem = document.getElementById('default_item').value;
        const defaultDosage = document.getElementById('default_dosage').value;

        let optionsHtml = '<option value="">Select Med</option>';
        for (const [id, item] of Object.entries(inventory)) {
            const isSelected = (id === defaultItem) ? 'selected' : '';
            optionsHtml += `<option value="${id}" ${isSelected}>${item.name} (${item.stock} ${item.unit})</option>`;
        }

        const tr = document.createElement('tr');
        tr.id = `row-${animal.ANIMAL_ID}`;
        tr.dataset.id = animal.ANIMAL_ID;
        
        // Added data-label attributes for mobile view
        tr.innerHTML = `
            <td data-label="Tag No" style="font-weight:600; color:#fff;">${animal.TAG_NO}</td>
            <td data-label="Medication">
                <select class="item-select" name="item[${animal.ANIMAL_ID}]" onchange="updateCalculations()">${optionsHtml}</select>
            </td>
            <td data-label="Dosage">
                <input type="text" class="dosage-input" name="dosage[${animal.ANIMAL_ID}]" 
                       value="${defaultDosage}" placeholder="e.g. 5ml">
            </td>
            <td data-label="Qty">
                <input type="number" class="qty-input" name="qty[${animal.ANIMAL_ID}]" 
                       value="${defaultQty}" step="0.01" min="0.01" oninput="updateCalculations()">
            </td>
            <td data-label="Remarks">
                <input type="text" name="remarks[${animal.ANIMAL_ID}]" placeholder="Notes...">
            </td>
            <td data-label="Remove" style="text-align:right;">
                <button type="button" class="btn-remove" onclick="removeAnimal(${animal.ANIMAL_ID})">×</button>
            </td>
        `;
        tbody.appendChild(tr);
        selectedAnimals.add(animal.ANIMAL_ID);
        
        const card = document.getElementById(`card-${animal.ANIMAL_ID}`);
        if(card) card.classList.add('in-table');

        updateCalculations();
    }

    function removeAnimal(id) {
        document.getElementById(`row-${id}`).remove();
        selectedAnimals.delete(id);
        
        const card = document.getElementById(`card-${id}`);
        if(card) card.classList.remove('in-table');

        if(selectedAnimals.size === 0) {
            document.getElementById('medication-list').innerHTML = '<tr id="empty-row"><td colspan="6" style="text-align:center; padding:2rem; color:#64748b;">No animals added yet.</td></tr>';
        }
        updateCalculations();
    }

    function clearTable() {
        if(!confirm("Clear all rows?")) return;
        selectedAnimals.forEach(id => removeAnimal(id));
    }

    // --- 4. BULK UPDATES ---
    function updateAllQuantities() {
        const newQty = document.getElementById('default_qty').value;
        document.querySelectorAll('.qty-input').forEach(inp => inp.value = newQty);
        updateCalculations();
    }

    function updateAllDosages() {
        const newDosage = document.getElementById('default_dosage').value;
        document.querySelectorAll('.dosage-input').forEach(inp => inp.value = newDosage);
    }

    function updateAllItems() {
        const newItem = document.getElementById('default_item').value;
        document.querySelectorAll('.item-select').forEach(sel => sel.value = newItem);
        updateCalculations();
    }

    // --- 5. VALIDATION ---
    function updateCalculations() {
        const count = selectedAnimals.size;
        document.getElementById('sum-count').innerText = count;

        const totals = {}; 
        let hasError = false;
        
        document.querySelectorAll('#medication-list tr[id^="row-"]').forEach(tr => {
            const itemId = tr.querySelector('.item-select').value;
            const qty = parseFloat(tr.querySelector('.qty-input').value) || 0;

            if(!itemId) { hasError = true; return; } 

            if (!totals[itemId]) totals[itemId] = 0;
            totals[itemId] += qty;
        });

        const warningDiv = document.getElementById('stock-warning');
        const submitBtn = document.getElementById('btn-submit');
        let warnings = [];

        for (const [id, needed] of Object.entries(totals)) {
            const item = inventory[id];
            if (needed > item.stock) {
                warnings.push(`Not enough ${item.name}! Need ${needed.toFixed(2)}, have ${item.stock}.`);
                hasError = true;
            }
        }

        if (warnings.length > 0) {
            warningDiv.innerHTML = warnings.join('<br>');
            warningDiv.style.color = '#f87171';
        } else if (count > 0) {
            warningDiv.innerHTML = `<span style="color:#4ade80">Stock levels sufficient.</span>`;
        } else {
            warningDiv.innerHTML = '';
        }

        if (count > 0 && !hasError) {
            submitBtn.disabled = false;
            submitBtn.innerText = "Record Treatments";
        } else {
            submitBtn.disabled = true;
            submitBtn.innerText = hasError ? "Fix Stock Issues" : "Record Treatments";
        }
    }

    // --- 6. SUBMISSION ---
    function submitBatch() {
        if(!confirm("Record medication for " + selectedAnimals.size + " animals?")) return;

        const btn = document.getElementById('btn-submit');
        btn.disabled = true; btn.innerText = "Processing...";

        const records = [];
        let validationError = false;

        document.querySelectorAll('#medication-list tr[id^="row-"]').forEach(tr => {
            const itemId = tr.querySelector('.item-select').value;
            
            if(!itemId) {
                alert("Please select a medication for all animals.");
                validationError = true;
                return;
            }

            const itemData = inventory[itemId]; 

            records.push({
                animal_id: tr.dataset.id,
                item_id: itemId,
                unit_id: itemData ? itemData.unit_id : null,
                quantity: tr.querySelector('.qty-input').value,
                dosage: tr.querySelector('.dosage-input').value, 
                remarks: tr.querySelector('input[name^="remarks"]').value
            });
        });

        if(validationError) {
            btn.disabled = false; btn.innerText = "Record Treatments";
            return;
        }

        const data = {
            records: records,
            date: document.getElementById('txn_date').value
        };

        fetch('../process/addBatchMedication.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        })
        .then(r => r.json())
        .then(res => {
            if(res.success) {
                alert("✅ Treatments Recorded!");
                location.reload();
            } else {
                alert("❌ Error: " + res.message);
                btn.disabled = false; btn.innerText = "Record Treatments";
            }
        })
        .catch(err => {
            console.error(err);
            alert("System Error");
            btn.disabled = false; btn.innerText = "Record Treatments";
        });
    }
</script>

</body>
</html>