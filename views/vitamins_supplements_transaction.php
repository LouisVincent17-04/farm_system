<?php
// views/vitamins_supplements_transaction.php
error_reporting(0);
ini_set('display_errors', 0);

$page="transactions";
include '../common/navbar.php';
include '../config/Connection.php';
include '../security/checkRole.php';    
checkRole(3);

try {
    if (!isset($conn)) {
        throw new Exception("Database connection failed.");
    }

    // 1. Fetch Transaction History
    // UPDATED: Joined Location, Building, Pen for Smart Edit
    $transactions_sql = "SELECT t.*, 
                          t.TRANSACTION_DATE, -- Get full datetime
                          a.TAG_NO,
                          a.ANIMAL_ID,
                          a.LOCATION_ID, a.BUILDING_ID, a.PEN_ID,
                          v.SUPPLY_NAME AS ITEM_NAME,
                          v.SUPPLY_ID AS ITEM_ID,
                          u.UNIT_ABBR
                          FROM VITAMINS_SUPPLEMENTS_TRANSACTIONS t
                          LEFT JOIN ANIMAL_RECORDS a ON t.ANIMAL_ID = a.ANIMAL_ID
                          LEFT JOIN VITAMINS_SUPPLEMENTS v ON t.ITEM_ID = v.SUPPLY_ID
                          LEFT JOIN UNITS u ON v.UNIT_ID = u.UNIT_ID
                          ORDER BY t.TRANSACTION_DATE DESC, t.VST_ID DESC";
    
    $stmt = $conn->prepare($transactions_sql);
    $stmt->execute();
    $transactions_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Fetch Locations (Level 1 Cascade)
    $locations_sql = "SELECT LOCATION_ID, LOCATION_NAME FROM LOCATIONS ORDER BY LOCATION_NAME ASC";
    $stmt = $conn->prepare($locations_sql);
    $stmt->execute();
    $locations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Fetch Available Vitamins
    $items_sql = "SELECT v.SUPPLY_ID AS ITEM_ID, v.SUPPLY_NAME AS ITEM_NAME, v.TOTAL_STOCK AS TOTAL_QTY, u.UNIT_ABBR
                  FROM VITAMINS_SUPPLEMENTS v
                  LEFT JOIN UNITS u ON v.UNIT_ID = u.UNIT_ID
                  ORDER BY v.SUPPLY_NAME ASC";
    $stmt = $conn->prepare($items_sql);
    $stmt->execute();
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $transactions_data = [];
    $locations = [];
    $items = [];
    echo "<script>console.error('Database Error: " . addslashes($e->getMessage()) . "');</script>";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vitamins & Supplements Management</title>
    <link rel="stylesheet" href="../css/purch_housing_facilities.css">
    <style>
        /* [Previous Core CSS - Consistent with Treatment Page] */
        .nav-tabs { display: flex; gap: 0; margin-bottom: 30px; background: rgba(15, 23, 42, 0.5); border-radius: 12px; padding: 6px; backdrop-filter: blur(10px); }
        .nav-tab { flex: 1; padding: 14px 28px; background: transparent; border: none; color: #94a3b8; font-weight: 600; cursor: pointer; transition: all 0.3s; font-size: 15px; border-radius: 8px; display: flex; align-items: center; justify-content: center; gap: 8px; }
        .nav-tab:hover { color: #e2e8f0; background: rgba(255, 255, 255, 0.05); }
        
        .nav-tab.active { color: white; background: linear-gradient(135deg, #db2777, #be185d); box-shadow: 0 4px 12px rgba(219, 39, 119, 0.4); }
        .nav-tab svg { width: 20px; height: 20px; }
        .add-btn { background: linear-gradient(135deg, #db2777, #be185d); }
        .stock-info { font-size: 13px; color: #6b7280; margin-top: 8px; padding: 8px 12px; background: rgba(236, 72, 153, 0.1); border-radius: 6px; border-left: 3px solid #db2777; }
        .stock-info.low-stock { color: #dc2626; background: rgba(220, 38, 38, 0.1); border-left-color: #dc2626; font-weight: 600; }
        .tag-badge { display: inline-block; padding: 6px 14px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 8px; font-size: 13px; font-weight: 700; }
        .item-name { font-weight: 600; color: #f9f9f9ff; font-size: 14px; }
        .quantity-badge { display: inline-block; padding: 6px 12px; background: linear-gradient(135deg, #10b981, #059669); color: white; border-radius: 8px; font-size: 13px; font-weight: 700; }
        .dosage-badge { display: inline-block; padding: 6px 12px; background: linear-gradient(135deg, #f472b6, #db2777); color: white; border-radius: 8px; font-size: 13px; font-weight: 700; }
        
        .alert { padding: 12px 16px; border-radius: 6px; margin-bottom: 1rem; font-size: 0.9rem; font-weight: 500; display: none; }
        .alert.show { display: block; animation: fadeIn 0.3s ease-in; }
        .alert.success { background: rgba(16, 185, 129, 0.1); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.2); }
        .alert.error { background: rgba(239, 68, 68, 0.1); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.2); }
        
        /* --- FIXED MODAL SCROLLING --- */
        .modal {
            display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0, 0, 0, 0.75); backdrop-filter: blur(4px); z-index: 1000;
            align-items: center; justify-content: center; padding: 1rem;
        }
        .modal.show { display: flex; }
        .modal-content {
            background: #1e293b; border-radius: 20px; width: 100%; max-width: 700px;
            max-height: 90vh; display: flex; flex-direction: column;
            border: 1px solid rgba(148, 163, 184, 0.1); box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
            animation: slideUp 0.3s ease;
        }
        .modal-header { padding: 1.5rem 2rem; border-bottom: 1px solid rgba(148, 163, 184, 0.1); flex-shrink: 0; }
        .modal-body { padding: 2rem; overflow-y: auto; flex-grow: 1; }
        .modal-footer { padding: 1.5rem 2rem; border-top: 1px solid rgba(148, 163, 184, 0.1); display: flex; justify-content: flex-end; gap: 1rem; flex-shrink: 0; background: #1e293b; border-radius: 0 0 20px 20px; }

        /* --- CASCADING GRID --- */
        .form-row-cascading {
            display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px;
            margin-bottom: 15px; background: rgba(255, 255, 255, 0.03);
            padding: 15px; border-radius: 8px; border: 1px dashed rgba(255, 255, 255, 0.1);
        }
        @media (max-width: 768px) { .form-row-cascading { grid-template-columns: 1fr; } }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes slideUp { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="header-info">
                <h1>Vitamins & Supplements</h1>
                <p>Track administration of vitamins, minerals, and boosters</p>
            </div>
            <button class="add-btn" onclick="openAddModal()">
                <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                Give Supplement
            </button>
        </div>

        <div class="nav-tabs">
            <button class="nav-tab active" onclick="window.location.href='vitamins_supplements_transaction.php'">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                Vitamin Transactions
            </button>
            <button class="nav-tab" onclick="window.location.href='available_vitamins_supplements.php'">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"></path></svg>
                Available Supplements
            </button>
        </div>

        <div class="search-container">
            <svg class="search-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m21 21-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
            <input type="text" class="search-input" id="searchInput" placeholder="Search by tag number or supplement name..." onkeyup="filterTable()">
        </div>

        <div class="table-container">
            <table class="table">
               <thead>
                    <tr>
                        <th>ID</th>
                        <th>Tag Number</th>
                        <th>Supplement Name</th>
                        <th>Dosage</th>
                        <th>Quantity</th>
                        <th>Date & Time</th>
                        <th>Remarks</th>
                        <th style="text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody id="transaction-table">
                    <?php foreach($transactions_data as $transaction): 
                        // Proper Date Handling
                        $dateObj = new DateTime($transaction['TRANSACTION_DATE']);
                        $displayDate = $dateObj->format('M d, Y h:i A');
                        $isoDate = $dateObj->format('Y-m-d\TH:i'); // For input type="datetime-local"
                    ?>
                    <tr data-vst-id="<?= $transaction['VST_ID'] ?>"
                        data-animal-id="<?= $transaction['ANIMAL_ID'] ?>"
                        
                        data-location-id="<?= $transaction['LOCATION_ID'] ?>"
                        data-building-id="<?= $transaction['BUILDING_ID'] ?>"
                        data-pen-id="<?= $transaction['PEN_ID'] ?>"
                        
                        data-tag-no="<?= htmlspecialchars($transaction['TAG_NO']) ?>"
                        data-item-id="<?= $transaction['ITEM_ID'] ?>"
                        data-item-name="<?= htmlspecialchars($transaction['ITEM_NAME']) ?>"
                        data-dosage="<?= htmlspecialchars($transaction['DOSAGE'] ?? '') ?>"
                        data-qty-used="<?= $transaction['QUANTITY_USED'] ?? '0' ?>"
                        data-transaction-date="<?= $isoDate ?>"
                        data-remarks="<?= htmlspecialchars($transaction['REMARKS'] ?? '') ?>">
                        
                        <td><div class="item-id">#<?= $transaction['VST_ID'] ?></div></td>
                        <td><span class="tag-badge"><?= htmlspecialchars($transaction['TAG_NO']) ?></span></td>
                        <td><div class="item-name"><?= htmlspecialchars($transaction['ITEM_NAME']) ?></div></td>
                        <td><span class="dosage-badge"><?= htmlspecialchars($transaction['DOSAGE'] ?? '-') ?></span></td>
                        <td>
                            <span class="quantity-badge">
                                <?= number_format($transaction['QUANTITY_USED'] ?? 0, 2) ?> 
                                <?= htmlspecialchars($transaction['UNIT_ABBR'] ?? '') ?>
                            </span>
                        </td>
                        <td><div class="item-unit"><?= $displayDate ?></div></td>
                        <td><div class="item-unit"><?= htmlspecialchars(substr($transaction['REMARKS'] ?? '', 0, 25)) ?>...</div></td>
                        <td>
                            <div class="actions">
                                <button class="action-btn view" onclick="viewTransaction(this)"><svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg></button>
                                <button class="action-btn edit" onclick="editTransaction(this)"><svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg></button>
                                <button class="action-btn delete" onclick="deleteTransaction(this)"><svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg></button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div id="empty-state" class="empty-state" style="display: none; text-align: center; padding: 2rem;"><h3>No transactions found</h3></div>
        </div>
    </div>

    <div id="modal" class="modal">
        <div class="modal-content">
            <div class="modal-header"><h2 id="modal-title">Add Vitamin Record</h2></div>
            <div class="modal-body">
                <div id="modal-alert" class="alert"></div>
                <form id="transaction-form" method="POST">
                    <input type="hidden" id="vst-id" name="vst_id">
                    
                    <div class="info-group">
                        <h3>1. Select Animal</h3>
                        <p style="font-size:0.85rem; color:#94a3b8; margin-bottom:10px;">Filter locations to find the animal efficiently.</p>
                        
                        <div class="form-row-cascading">
                            <div class="form-group">
                                <label for="location_id">Location</label>
                                <select id="location_id" onchange="loadBuildings(this.value)">
                                    <option value="">Select...</option>
                                    <?php foreach($locations as $loc): ?>
                                        <option value="<?= $loc['LOCATION_ID'] ?>"><?= $loc['LOCATION_NAME'] ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="building_id">Building</label>
                                <select id="building_id" onchange="loadPens(this.value)" disabled>
                                    <option value="">Select Loc First</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="pen_id">Pen</label>
                                <select id="pen_id" onchange="loadAnimals(this.value)" disabled>
                                    <option value="">Select Bldg First</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Animal (Tag Number) <span>*</span></label>
                            <select id="animal" name="animal_id" required disabled>
                                <option value="">Select Pen First</option>
                            </select>
                        </div>

                        <h3>2. Supplement Details</h3>
                        <div class="form-group">
                            <label>Vitamin/Supplement <span>*</span></label>
                            <select id="item" name="item_id" required onchange="updateStockInfo()">
                                <option value="">Select Supplement</option>
                                <?php foreach($items as $i): 
                                    echo "<option value='".$i['ITEM_ID']."' data-quantity='".($i['TOTAL_QTY']??0)."' data-units='".($i['UNIT_ABBR']??'')."'>".$i['ITEM_NAME']."</option>"; 
                                endforeach; ?>
                            </select>
                            <div id="stock-info" class="stock-info" style="display:none;"></div>
                        </div>
                        <div class="form-group">
                            <label>Dosage</label>
                            <input type="text" id="dosage" name="dosage" placeholder="e.g. 10ml daily">
                        </div>
                        <div class="form-group">
                            <label>Quantity Used <span id="units_used_span"></span> <span>*</span></label>
                            <input type="number" id="qty-used" name="quantity_used" step="0.01" min="0" required>
                        </div>
                        <div class="form-group">
                            <label>Date & Time <span>*</span></label>
                            <input type="datetime-local" id="transaction-date" name="transaction_date" required>
                        </div>
                        <div class="form-group">
                            <label>Remarks</label>
                            <textarea id="remarks" name="remarks" rows="2"></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeModal()">Cancel</button>
                <button type="button" class="btn-save" id="btn-save" onclick="saveTransaction()">Save Record</button>
            </div>
        </div>
    </div>

    <div id="view-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header"><h2>Record Details</h2></div>
            <div class="modal-body" id="view-modal-body"></div>
            <div class="modal-footer"><button class="btn-cancel" onclick="document.getElementById('view-modal').classList.remove('show')">Close</button></div>
        </div>
    </div>

    <form id="deleteTransactionForm" method="POST" action="../process/deleteVitaminsSupplementsTransaction.php" style="display: none;">
        <input type="hidden" id="delete_vst_id" name="vst_id">
    </form>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // Set Default Time to Now (ISO local format)
            const now = new Date();
            now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
            document.getElementById('transaction-date').value = now.toISOString().slice(0,16);
            
            filterTable();
        });

        // --- CASCADING LOGIC (PROMISE BASED) ---
        function loadBuildings(locId) {
            return new Promise((resolve) => {
                const bldgSel = document.getElementById('building_id');
                const penSel = document.getElementById('pen_id');
                const animalSel = document.getElementById('animal');
                
                bldgSel.innerHTML = '<option>Loading...</option>'; bldgSel.disabled = true;
                penSel.innerHTML = '<option value="">Select Bldg First</option>'; penSel.disabled = true;
                animalSel.innerHTML = '<option value="">Select Pen First</option>'; animalSel.disabled = true;

                if(!locId) {
                    bldgSel.innerHTML = '<option value="">Select Loc First</option>';
                    resolve(); return;
                }

                fetch(`../process/getBuildingsByLocation.php?location_id=${locId}`)
                    .then(r => r.json())
                    .then(data => {
                        bldgSel.innerHTML = '<option value="">Select Building</option>';
                        if(data.buildings && data.buildings.length > 0) {
                            data.buildings.forEach(b => {
                                bldgSel.innerHTML += `<option value="${b.BUILDING_ID}">${b.BUILDING_NAME}</option>`;
                            });
                            bldgSel.disabled = false;
                        } else { bldgSel.innerHTML = '<option value="">No Buildings</option>'; }
                        resolve();
                    });
            });
        }

        function loadPens(bldgId) {
            return new Promise((resolve) => {
                const penSel = document.getElementById('pen_id');
                const animalSel = document.getElementById('animal');
                
                penSel.innerHTML = '<option>Loading...</option>'; penSel.disabled = true;
                animalSel.innerHTML = '<option value="">Select Pen First</option>'; animalSel.disabled = true;

                if(!bldgId) { resolve(); return; }

                fetch(`../process/getPensByBuilding.php?building_id=${bldgId}`)
                    .then(r => r.json())
                    .then(data => {
                        penSel.innerHTML = '<option value="">Select Pen</option>';
                        if(data.pens && data.pens.length > 0) {
                            data.pens.forEach(p => {
                                penSel.innerHTML += `<option value="${p.PEN_ID}">${p.PEN_NAME}</option>`;
                            });
                            penSel.disabled = false;
                        } else { penSel.innerHTML = '<option value="">No Pens</option>'; }
                        resolve();
                    });
            });
        }

        function loadAnimals(penId) {
            return new Promise((resolve) => {
                const animalSel = document.getElementById('animal');
                animalSel.innerHTML = '<option>Loading...</option>'; animalSel.disabled = true;

                if(!penId) { resolve(); return; }

                fetch(`../process/getAnimalsByPen.php?pen_id=${penId}`)
                    .then(r => r.json())
                    .then(data => {
                        animalSel.innerHTML = '<option value="">Select Animal</option>';
                        const list = data.animals || data.animal_record || []; 
                        
                        if(list.length > 0) {
                            list.forEach(a => {
                                if(a.IS_ACTIVE == 0)
                                {
                                    return; 
                                } 
                                else
                                {
                                    animalSel.innerHTML += `<option value="${a.ANIMAL_ID}">${a.TAG_NO}</option>`;
                                }
                            });
                            animalSel.disabled = false;
                        } else { animalSel.innerHTML = '<option value="">No Animals</option>'; }
                        resolve();
                    });
            });
        }

        function updateStockInfo() {
            const sel = document.getElementById('item');
            const opt = sel.options[sel.selectedIndex];
            const info = document.getElementById('stock-info');
            
            if(!sel.value) { info.style.display = 'none'; return; }
            
            const qty = parseFloat(opt.dataset.quantity);
            const unit = opt.dataset.units;
            document.getElementById('units_used_span').textContent = `(${unit})`;
            
            info.textContent = `📦 Available Stock: ${qty} ${unit}`;
            info.className = `stock-info ${qty < 10 ? 'low-stock' : ''}`;
            info.style.display = 'block';
        }

        function openAddModal() {
            document.getElementById('transaction-form').reset();
            document.getElementById('vst-id').value = '';
            
            // Reset Cascades
            document.getElementById('location_id').value = "";
            document.getElementById('building_id').innerHTML = '<option value="">Select Loc First</option>';
            document.getElementById('building_id').disabled = true;
            document.getElementById('pen_id').innerHTML = '<option value="">Select Bldg First</option>';
            document.getElementById('pen_id').disabled = true;
            document.getElementById('animal').innerHTML = '<option value="">Select Pen First</option>';
            document.getElementById('animal').disabled = true;

            document.getElementById('modal-title').textContent = 'Add Vitamin Record';
            document.getElementById('btn-save').textContent = 'Save Record';
            
            // Set Default Time
            const now = new Date();
            now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
            document.getElementById('transaction-date').value = now.toISOString().slice(0,16);
            
            document.getElementById('stock-info').style.display = 'none';
            document.querySelector('.alert').className = 'alert'; 
            document.getElementById('modal').classList.add('show');
        }

        async function editTransaction(btn) {
            const row = btn.closest('tr');
            const d = row.dataset;
            
            document.getElementById('vst-id').value = d.vstId;
            document.getElementById('modal-title').textContent = 'Edit Vitamin Record';
            document.getElementById('btn-save').textContent = 'Update Record';

            // 1. Set Location
            document.getElementById('location_id').value = d.locationId || "";
            
            // 2. Load Buildings & Set
            if(d.locationId) {
                await loadBuildings(d.locationId);
                document.getElementById('building_id').value = d.buildingId || "";
            }

            // 3. Load Pens & Set
            if(d.buildingId) {
                await loadPens(d.buildingId);
                document.getElementById('pen_id').value = d.penId || "";
            }

            // 4. Load Animals & Set
            if(d.penId) {
                await loadAnimals(d.penId);
                document.getElementById('animal').value = d.animalId;
            } else {
                // Fallback
                const animalSel = document.getElementById('animal');
                animalSel.innerHTML = `<option value="${d.animalId}" selected>${d.tagNo}</option>`;
                animalSel.disabled = false;
            }

            document.getElementById('item').value = d.itemId;
            document.getElementById('dosage').value = d.dosage;
            document.getElementById('qty-used').value = d.qtyUsed;
            document.getElementById('transaction-date').value = d.transactionDate; // Using ISO value from PHP
            document.getElementById('remarks').value = d.remarks;
            
            updateStockInfo();
            document.getElementById('modal').classList.add('show');
        }

        function closeModal() { document.getElementById('modal').classList.remove('show'); }

        function saveTransaction() {
            const form = document.getElementById('transaction-form');
            if (!form.checkValidity()) { form.reportValidity(); return; }

            const id = document.getElementById('vst-id').value;
            const url = id ? '../process/editVitaminsSupplementsTransaction.php' : '../process/addVitaminsSupplementsTransaction.php';
            const btn = document.getElementById('btn-save');
            const alertBox = document.querySelector('.alert');

            btn.disabled = true;
            btn.innerHTML = '<span class="loading"></span> Processing...';
            
            // Enable disabled fields for submission
            document.getElementById('animal').disabled = false;

            fetch(url, { method: 'POST', body: new FormData(form) })
            .then(r => r.json())
            .then(data => {
                alertBox.textContent = data.message;
                alertBox.className = `alert show ${data.success ? 'success' : 'error'}`;
                if(data.success) {
                    setTimeout(() => { window.location.reload(); }, 1000);
                } else {
                    btn.disabled = false;
                    btn.textContent = id ? 'Update Record' : 'Save Record';
                }
            })
            .catch(e => {
                console.error(e);
                alertBox.textContent = "System Error";
                alertBox.className = "alert show error";
                btn.disabled = false;
            });
        }

        function deleteTransaction(btn) {
            const row = btn.closest('tr');
            if(confirm("Are you sure you want to delete this record? Stock will be restored.")) {
                document.getElementById('delete_vst_id').value = row.dataset.vstId;
                document.getElementById('deleteTransactionForm').submit();
            }
        }

        function viewTransaction(btn) {
            const d = btn.closest('tr').dataset;
            const row = btn.closest('tr');
            const displayDate = row.querySelector('.item-unit').innerText; // Get nice date from table cell

            const html = `
                <div class="info-group">
                    <h3>Record Info</h3>
                    <p><strong>ID:</strong> #${d.vstId}</p>
                    <p><strong>Tag:</strong> <span class="tag-badge">${d.tagNo}</span></p>
                    <p><strong>Date & Time:</strong> ${displayDate}</p>
                </div>
                <div class="info-group">
                    <h3>Supplement Details</h3>
                    <p><strong>Item:</strong> ${d.itemName}</p>
                    <p><strong>Dosage:</strong> <span class="dosage-badge">${d.dosage}</span></p>
                    <p><strong>Qty:</strong> <span class="quantity-badge">${d.qtyUsed}</span></p>
                    <p><strong>Remarks:</strong> ${d.remarks}</p>
                </div>`;
            document.getElementById('view-modal-body').innerHTML = html;
            document.getElementById('view-modal').classList.add('show');
        }

        function filterTable() {
            const term = document.getElementById('searchInput').value.toLowerCase();
            const rows = document.querySelectorAll('#transaction-table tr');
            let visible = 0;
            rows.forEach(r => {
                const txt = r.innerText.toLowerCase();
                r.style.display = txt.includes(term) ? '' : 'none';
                if(r.style.display !== 'none') visible++;
            });
            document.getElementById('empty-state').style.display = visible ? 'none' : 'block';
        }
    </script>
</body>
</html>