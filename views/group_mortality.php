<?php
// views/group_mortality.php
ob_start(); 
error_reporting(0);
ini_set('display_errors', 0);
include '../config/Connection.php';

include '../security/checkAccess.php';
// Ensure 'group_mortality' is in access_control, else use a fallback
checkAccess('group_mortality'); 
$page = "transactions";
include '../common/navbar.php';
include '../common/chat_support.php';
include '../functions/getUsersLocation.php'; // ADDED LOCATION FUNCTION


try {
    if (!isset($conn)) { throw new Exception("Database connection failed."); }

    // Fetch Locations based on user restriction
    if ($USER_LOCATION_ != 1000) {
        $stmt = $conn->prepare("SELECT LOCATION_ID, LOCATION_NAME FROM LOCATIONS WHERE LOCATION_ID = ? ORDER BY LOCATION_NAME ASC");
        $stmt->execute([$USER_LOCATION_]);
        $locs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $locs = $conn->query("SELECT LOCATION_ID, LOCATION_NAME FROM LOCATIONS ORDER BY LOCATION_NAME ASC")->fetchAll(PDO::FETCH_ASSOC);
    }

    // Fetch Buyers for Dropdown (NEW)
    $buyers = $conn->query("SELECT FULL_NAME FROM buyers WHERE IS_ACTIVE = 1 ORDER BY FULL_NAME ASC")->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    // Handle error silently
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Group Mortality</title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" type="text/css" href="https://npmcdn.com/flatpickr/dist/themes/dark.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    <style>
        /* --- GLOBAL STYLES --- */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            min-height: 100vh; color: #e2e8f0;
        }
        .container { max-width: 1600px; margin: 0 auto; padding: 1.5rem; }

        /* --- BACK LINK --- */
        .back-link {
            display: inline-flex; align-items: center; gap: 8px; 
            text-decoration: none; color: #94a3b8; font-weight: 600; 
            font-size: 0.95rem; margin-bottom: 20px; transition: color 0.2s;
        }
        .back-link:hover { color: white; }

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
            transition: border-color 0.2s; outline:none;
        }
        /* Specific color focus for Mortality (Dark Red/Gray) */
        .form-control:focus { border-color: #ef4444; box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1); }
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
        
        /* Select All Toggle */
        .select-all-container {
            display: flex; align-items: center; gap: 8px;
            font-size: 0.9rem; color: #fca5a5; cursor: pointer;
            padding: 5px 10px; border-radius: 6px;
            background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.2);
        }
        .select-all-container:hover { background: rgba(239, 68, 68, 0.2); }
        .select-all-container input { cursor: pointer; accent-color: #ef4444; width: 16px; height: 16px; }

        .animal-grid {
            display: grid; grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            gap: 0.75rem; max-height: 250px; overflow-y: auto; padding-right: 5px;
        }
        .animal-card {
            background: #1e293b; border: 1px solid #334155; border-radius: 8px;
            padding: 0.75rem; cursor: pointer; text-align: center; transition: all 0.2s;
            position: relative;
        }
        .animal-card:hover { border-color: #94a3b8; transform: translateY(-2px); }
        .animal-card.selected { background: rgba(239, 68, 68, 0.2); border-color: #ef4444; }
        .animal-card.in-table { opacity: 0.5; pointer-events: none; border-color: #ef4444; } 

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
            padding: 8px 10px; border-radius: 6px; width: 100%; font-size: 0.9rem; outline:none;
        }
        .custom-table input:focus, .custom-table select:focus { border-color: #ef4444; box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1); }
        
        .btn-remove {
            background: transparent; border: none; color: #f87171;
            cursor: pointer; font-size: 1.1rem; padding: 5px; transition: color 0.2s;
        }
        .btn-remove:hover { color: #ef4444; transform: scale(1.1); }

        /* Summary Box */
        .summary-box {
            margin-top: 1.5rem; background: #0f172a; padding: 1rem;
            border-radius: 12px; border-left: 4px solid #ef4444;
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
            background: linear-gradient(135deg, #ef4444, #b91c1c);
            border: none; border-radius: 12px; color: white; font-weight: 700;
            cursor: pointer; transition: all 0.2s; font-size: 1rem;
        }
        .btn-submit:disabled { opacity: 0.5; cursor: not-allowed; filter: grayscale(1); }
        .btn-submit:hover:not(:disabled) { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4); }

        /* --- MOBILE RESPONSIVENESS --- */
        @media (max-width: 1024px) {
            .container { padding: 1rem; }
            .main-grid { grid-template-columns: 1fr; gap: 1rem; }
            .control-panel { position: static; margin-bottom: 1rem; }
            
            .custom-table, .custom-table tbody, .custom-table tr, .custom-table td {
                display: block; width: 100%;
            }
            .custom-table thead { display: none; }
            
            .custom-table tr {
                background: rgba(30, 41, 59, 0.3);
                margin-bottom: 1rem; border: 1px solid #334155; border-radius: 12px;
                padding: 1rem; position: relative;
            }
            
            .custom-table td {
                padding: 8px 0; display: flex; justify-content: space-between; align-items: center;
                border-bottom: 1px solid rgba(255,255,255,0.05); text-align: right; font-size: 0.95rem;
            }
            .custom-table td:last-child { border-bottom: none; justify-content: flex-end; }
            
            .custom-table td::before {
                content: attr(data-label); font-weight: 600; font-size: 0.85rem; color: #94a3b8;
                text-transform: uppercase; margin-right: 1rem;
            }
            
            .custom-table select, .custom-table input { width: 60%; }
        }
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
            <div class="panel-title">💀 Group Mortality</div>
            <div class="panel-subtitle">Record mass mortality events.</div>

            <form id="settingsForm">
                <div style="background:rgba(255,255,255,0.03); padding:15px; border-radius:8px; margin-bottom:1.5rem; border:1px dashed #475569;">
                    <label class="form-label" style="margin-bottom:8px; color:#fca5a5;">STEP 1: Locate Group</label>
                    <div class="form-group" style="margin-bottom:0.5rem;">
                        <select id="location_id" class="form-control" onchange="loadBuildings(this.value)" <?php echo ($USER_LOCATION_ != 1000) ? 'style="background-color: #0a1020; pointer-events: none; color: #94a3b8;"' : ''; ?>>
                            <?php if($USER_LOCATION_ == 1000): ?>
                                <option value="">Select Location</option>
                            <?php endif; ?>
                            <?php foreach($locs as $l): ?>
                                <option value="<?php echo $l['LOCATION_ID']; ?>" <?php echo ($USER_LOCATION_ != 1000 && $l['LOCATION_ID'] == $USER_LOCATION_) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($l['LOCATION_NAME']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom:0.5rem;">
                        <select id="building_id" class="form-control" onchange="loadPens(this.value)" disabled><option>Select Building</option></select>
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <select id="pen_id" class="form-control" onchange="loadAnimals(this.value)" disabled><option>Select Pen</option></select>
                    </div>
                </div>

                <label class="form-label" style="color:#fca5a5;">STEP 2: Batch Details</label>
                
                <div class="form-group">
                    <label class="form-label">Recovered Cost <span style="color:#94a3b8; font-size:0.8em;">(e.g. 0.00)</span></label>
                    <div style="display:flex; gap:8px;">
                        <input type="number" id="default_cost" class="form-control" placeholder="0.00" step="0.01" value="0.00">
                        <button type="button" class="btn-mini" onclick="updateAllCosts()">Apply All</button>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Reason <span style="color:#f87171">*</span></label>
                    <div style="display:flex; gap:8px;">
                        <select id="default_reason" class="form-control">
                            <option value="Deceased">Deceased</option>
                            <option value="Stolen">Stolen</option>
                        </select>
                        <button type="button" class="btn-mini" onclick="updateAllReasons()">Apply All</button>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label"> Notes (Details)</label>
                    <div style="display:flex; gap:8px;">
                        <input type="text" id="default_notes" class="form-control" placeholder="e.g. Disease Outbreak">
                        <button type="button" class="btn-mini" onclick="updateAllNotes()">Apply All</button>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Buyer / Recipient (Optional)</label>
                    <select id="customer_name" class="form-control">
                        <option value="">-- No Buyer (Discarded) --</option>
                        <?php foreach($buyers as $b): ?>
                            <option value="<?= htmlspecialchars($b['FULL_NAME']) ?>"><?= htmlspecialchars($b['FULL_NAME']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small style="color: #64748b; font-size: 0.75rem;">If recovered costs came from a registered buyer.</small>
                </div>

                <div class="form-group">
                    <label class="form-label">Date of Death</label>
                    <input type="text" id="mortality_date" class="form-control" placeholder="Select Date & Time">
                </div>

                <div class="summary-box">
                    <div class="summary-row">
                        <span>Total Deaths:</span> 
                        <span id="sum-count" style="color:#fff">0</span>
                    </div>
                    <div class="summary-row">
                        <span>Total Recovered:</span> 
                        <span id="sum-cost" style="color:#86efac">₱0.00</span>
                    </div>
                </div>

                <button type="button" class="btn-submit" id="btn-submit" onclick="submitBatch()" disabled>Confirm Mortality</button>
            </form>
        </div>

        <div class="workspace-panel">
            
            <div class="picker-section">
                <div class="section-header">
                    <div class="section-title">🐖 Step 3: Select Animals</div>
                    
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
                    <div class="section-title">📋 Step 4: Confirm List</div>
                    <button onclick="clearTable()" style="background:transparent; border:1px solid #f87171; color:#f87171; padding:6px 12px; border-radius:6px; cursor:pointer; font-size:0.85rem;">Clear All</button>
                </div>
                
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th style="width: 20%;">Tag No</th>
                            <th>Reason</th>
                            <th>Details / Notes</th>
                            <th style="width: 15%;">Rec. Cost</th>
                            <th style="width: 50px;"></th>
                        </tr>
                    </thead>
                    <tbody id="mortality-list">
                        <tr id="empty-row"><td colspan="5" style="text-align:center; padding:2rem; color:#64748b;">No animals added yet.</td></tr>
                    </tbody>
                </table>
            </div>

        </div>
    </div>
</div>

<script>
    // --- STATE MANAGEMENT ---
    let selectedAnimals = new Set(); 
    let currentPenAnimals = []; 
    let fpMortalityDate;
    const USER_LOCATION = <?php echo json_encode($USER_LOCATION_); ?>;

    document.addEventListener('DOMContentLoaded', () => {
        // Initialize Flatpickr for Date and Time
        fpMortalityDate = flatpickr("#mortality_date", {
            enableTime: true,
            dateFormat: "Y-m-d H:i", // Backend expected format
            altInput: true,
            altFormat: "m/d/Y h:i K", // mm/dd/yyyy display format with AM/PM
            allowInput: true
        });
        
        fpMortalityDate.clear(); // Leave the actual input blank by default

        // Auto-load buildings if user is restricted to a location
        if (USER_LOCATION != 1000) {
            document.getElementById('location_id').value = USER_LOCATION;
            loadBuildings(USER_LOCATION);
        }
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
        const selectAllWrapper = document.getElementById('select-all-wrapper');
        
        grid.innerHTML = '<div style="grid-column:1/-1; text-align:center; color:#94a3b8;">Loading...</div>';
        selectAllWrapper.style.display = 'none';
        
        fetch(`../process/getAnimalsByPen.php?pen_id=${penId}`)
            .then(r => r.json())
            .then(data => {
                grid.innerHTML = '';
                currentPenAnimals = []; 

                let rawList = [];
                if (Array.isArray(data)) {
                    rawList = data;
                } else if (data.animal_record && Array.isArray(data.animal_record)) {
                    rawList = data.animal_record;
                } else {
                    console.error("Unexpected data format:", data);
                }
                
                // Filter active animals only
                rawList.forEach(a => {
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
                        <div style="font-size:1.5rem;">💀</div>
                        <div style="font-weight:700; color:#fff;">${a.TAG_NO}</div>
                    `;
                    grid.appendChild(card);
                });
            })
            .catch(err => {
                console.error("Fetch error:", err);
                grid.innerHTML = '<div style="grid-column:1/-1; text-align:center; color:#f87171;">Error loading animals.</div>';
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
        const allSelected = currentPenAnimals.every(a => selectedAnimals.has(a.ANIMAL_ID));
        checkbox.checked = allSelected;
    }

    // --- 3. TABLE OPERATIONS ---
    function addAnimalToTable(animal) {
        if(selectedAnimals.has(animal.ANIMAL_ID)) return;

        const emptyRow = document.getElementById('empty-row');
        if(emptyRow) emptyRow.remove();

        const tbody = document.getElementById('mortality-list');
        
        // Default Values
        const defaultReason = document.getElementById('default_reason').value;
        const defaultNotes = document.getElementById('default_notes').value;
        const defaultCost = document.getElementById('default_cost').value;

        const tr = document.createElement('tr');
        tr.id = `row-${animal.ANIMAL_ID}`;
        tr.dataset.id = animal.ANIMAL_ID;
        
        tr.innerHTML = `
            <td data-label="Tag No" style="font-weight:600; color:#fff;">${animal.TAG_NO}</td>
            <td data-label="Reason">
                <select name="reason[${animal.ANIMAL_ID}]" class="reason-select">
                    <option value="Deceased" ${defaultReason == 'Deceased' ? 'selected' : ''}>Deceased</option>
                    <option value="Stolen" ${defaultReason == 'Stolen' ? 'selected' : ''}>Stolen</option>
                </select>
            </td>
            <td data-label="Details">
                <input type="text" name="notes[${animal.ANIMAL_ID}]" value="${defaultNotes}" placeholder="Additional info...">
            </td>
            <td data-label="Rec. Cost">
                <input type="number" class="cost-input" name="cost[${animal.ANIMAL_ID}]" value="${defaultCost}" step="0.01" min="0" oninput="updateCalculations()">
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
        updateSelectAllState();
    }

    function removeAnimal(id) {
        document.getElementById(`row-${id}`).remove();
        
        selectedAnimals.delete(String(id));
        selectedAnimals.delete(id);
        
        const card = document.getElementById(`card-${id}`);
        if(card) card.classList.remove('in-table');

        if(selectedAnimals.size === 0) {
            document.getElementById('mortality-list').innerHTML = '<tr id="empty-row"><td colspan="5" style="text-align:center; padding:2rem; color:#64748b;">No animals added yet.</td></tr>';
        }
        updateCalculations();
        updateSelectAllState();
    }

    function clearTable() {
        if(!confirm("Clear all rows?")) return;
        Array.from(selectedAnimals).forEach(id => removeAnimal(id));
    }

    // --- 4. BULK UPDATES ---
    function updateAllReasons() {
        const newReason = document.getElementById('default_reason').value;
        document.querySelectorAll('select.reason-select').forEach(sel => sel.value = newReason);
    }

    function updateAllNotes() {
        const newNote = document.getElementById('default_notes').value;
        document.querySelectorAll('input[name^="notes"]').forEach(inp => inp.value = newNote);
    }

    function updateAllCosts() {
        const newCost = document.getElementById('default_cost').value;
        document.querySelectorAll('.cost-input').forEach(inp => inp.value = newCost);
        updateCalculations();
    }

    // --- 5. CALCULATIONS ---
    function updateCalculations() {
        const count = selectedAnimals.size;
        document.getElementById('sum-count').innerText = count;

        let totalCost = 0;
        document.querySelectorAll('.cost-input').forEach(inp => {
            totalCost += parseFloat(inp.value) || 0;
        });
        document.getElementById('sum-cost').innerText = "₱" + totalCost.toFixed(2);

        const btn = document.getElementById('btn-submit');
        if(count > 0) {
            btn.disabled = false;
            btn.innerText = `Confirm Mortality (${count})`;
        } else {
            btn.disabled = true;
            btn.innerText = "Confirm Mortality";
        }
    }

    // --- 6. SUBMISSION ---
    function submitBatch() {
        if(!confirm("WARNING: This will mark " + selectedAnimals.size + " animals as DECEASED/LOST. Continue?")) return;

        const dateInput = document.getElementById('mortality_date').value;
        if(!dateInput) {
            alert("Please select a valid Date of Death.");
            return;
        }

        const btn = document.getElementById('btn-submit');
        btn.disabled = true; btn.innerText = "Processing...";

        const records = [];
        document.querySelectorAll('#mortality-list tr[id^="row-"]').forEach(tr => {
            // Combine Reason and Notes
            const reason = tr.querySelector('select[name^="reason"]').value;
            const detail = tr.querySelector('input[name^="notes"]').value;
            const fullRemark = reason + (detail ? " - " + detail : "");

            records.push({
                animal_id: tr.dataset.id,
                remarks: fullRemark, // Send combined string as 'remarks'
                recovered_cost: tr.querySelector('.cost-input').value
            });
        });

        const data = {
            records: records,
            date: dateInput,
            customer_name: document.getElementById('customer_name').value 
        };

        fetch('../process/addBatchMortality.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        })
        .then(r => r.json())
        .then(res => {
            if(res.success) {
                alert("✅ Mortality Batch Recorded!");
                location.reload();
            } else {
                alert("❌ Error: " + res.message);
                btn.disabled = false;
                btn.innerText = "Confirm Mortality";
            }
        })
        .catch(err => {
            console.error(err);
            alert("System Error");
            btn.disabled = false;
            btn.innerText = "Confirm Mortality";
        });
    }
</script>

</body>
</html>