<?php
// views/medication.php
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

    // 1. Fetch Transactions (Get full datetime)
    $transactions_sql = "SELECT tt.*, 
                          tt.TRANSACTION_DATE as DATE_FULL, -- Fetch full datetime
                          a.TAG_NO,
                          a.ANIMAL_ID,
                          a.LOCATION_ID, a.BUILDING_ID, a.PEN_ID,
                          m.SUPPLY_NAME AS ITEM_NAME,
                          m.SUPPLY_ID AS ITEM_ID,
                          u.UNIT_ABBR
                          FROM TREATMENT_TRANSACTIONS tt
                          LEFT JOIN ANIMAL_RECORDS a ON tt.ANIMAL_ID = a.ANIMAL_ID
                          LEFT JOIN MEDICINES m ON tt.ITEM_ID = m.SUPPLY_ID
                          LEFT JOIN UNITS u ON m.UNIT_ID = u.UNIT_ID
                          ORDER BY tt.TRANSACTION_DATE DESC, tt.TT_ID DESC";
    
    $stmt = $conn->prepare($transactions_sql);
    $stmt->execute();
    $transactions_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Fetch Locations
    $locations_sql = "SELECT LOCATION_ID, LOCATION_NAME FROM LOCATIONS ORDER BY LOCATION_NAME ASC";
    $stmt = $conn->prepare($locations_sql);
    $stmt->execute();
    $locations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Fetch Items
    $items_sql = "SELECT 
                    m.SUPPLY_ID AS ITEM_ID, 
                    m.SUPPLY_NAME AS ITEM_NAME, 
                    m.TOTAL_STOCK AS TOTAL_QTY,
                    u.UNIT_ABBR
                FROM MEDICINES m
                JOIN UNITS u ON m.UNIT_ID = u.UNIT_ID
                ORDER BY m.SUPPLY_NAME ASC";
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
    <title>Treatment Transactions</title>
    <link rel="stylesheet" href="../css/purch_housing_facilities.css">
    <style>
        /* [Previous Core Styles - Kept for consistency] */
        .nav-tabs { display: flex; gap: 0; margin-bottom: 30px; background: rgba(15, 23, 42, 0.5); border-radius: 12px; padding: 6px; backdrop-filter: blur(10px); }
        .nav-tab { flex: 1; padding: 14px 28px; background: transparent; border: none; color: #94a3b8; font-weight: 600; cursor: pointer; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); font-size: 15px; border-radius: 8px; display: flex; align-items: center; justify-content: center; gap: 8px; position: relative; }
        .nav-tab:hover { color: #e2e8f0; background: rgba(255, 255, 255, 0.05); }
        .nav-tab.active { color: white; background: linear-gradient(135deg, #2563eb, #1d4ed8); box-shadow: 0 4px 12px rgba(37, 99, 235, 0.4); }
        .nav-tab svg { width: 20px; height: 20px; }
        .stock-info { font-size: 13px; color: #6b7280; margin-top: 8px; padding: 8px 12px; background: rgba(59, 130, 246, 0.1); border-radius: 6px; border-left: 3px solid #3b82f6; }
        .stock-info.low-stock { color: #dc2626; background: rgba(220, 38, 38, 0.1); border-left-color: #dc2626; font-weight: 600; }
        .tag-badge { display: inline-block; padding: 6px 14px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 8px; font-size: 13px; font-weight: 700; letter-spacing: 0.5px; box-shadow: 0 2px 8px rgba(102, 126, 234, 0.3); }
        .item-name { font-weight: 600; color: #f9f9f9ff; font-size: 14px; }
        .quantity-badge { display: inline-block; padding: 6px 12px; background: linear-gradient(135deg, #10b981, #059669); color: white; border-radius: 8px; font-size: 13px; font-weight: 700; box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3); }
        .dosage-badge { display: inline-block; padding: 6px 12px; background: linear-gradient(135deg, #f59e0b, #d97706); color: white; border-radius: 8px; font-size: 13px; font-weight: 700; box-shadow: 0 2px 8px rgba(245, 158, 11, 0.3); }
        
        .alert { padding: 12px 16px; border-radius: 6px; margin-bottom: 1rem; font-size: 0.9rem; font-weight: 500; display: none; }
        .alert.show { display: block; animation: fadeIn 0.3s ease-in; }
        .alert.success { background: rgba(16, 185, 129, 0.1); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.2); }
        .alert.error { background: rgba(239, 68, 68, 0.1); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.2); }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }

        /* --- FIXED MODAL SCROLLING --- */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.75); backdrop-filter: blur(4px); z-index: 1000; align-items: center; justify-content: center; padding: 1rem; }
        .modal.show { display: flex; }
        .modal-content { background: #1e293b; border-radius: 20px; width: 100%; max-width: 700px; max-height: 90vh; display: flex; flex-direction: column; border: 1px solid rgba(148, 163, 184, 0.1); box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5); animation: slideUp 0.3s ease; }
        .modal-header { padding: 1.5rem 2rem; border-bottom: 1px solid rgba(148, 163, 184, 0.1); flex-shrink: 0; }
        .modal-body { padding: 2rem; overflow-y: auto; flex-grow: 1; }
        .modal-footer { padding: 1.5rem 2rem; border-top: 1px solid rgba(148, 163, 184, 0.1); display: flex; justify-content: flex-end; gap: 1rem; flex-shrink: 0; background: #1e293b; border-radius: 0 0 20px 20px; }

        /* --- CASCADING GRID --- */
        .form-row-cascading { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 15px; background: rgba(255, 255, 255, 0.03); padding: 15px; border-radius: 8px; border: 1px dashed rgba(255, 255, 255, 0.1); }
        @media (max-width: 768px) { .form-row-cascading { grid-template-columns: 1fr; } }
        @keyframes slideUp { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="header-info">
                <h1>Treatment Transactions</h1>
                <p>Track animal medication records</p>
            </div>
            <button class="add-btn" onclick="openAddModal()">
                <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                Add Transaction
            </button>
        </div>

        <div class="nav-tabs">
            <button class="nav-tab active" onclick="window.location.href='medication.php'">Medical Transactions</button>
            <button class="nav-tab" onclick="window.location.href='available_medicines.php'">Available Supplies</button>
        </div>

        <div class="search-container">
            <input type="text" class="search-input" id="searchInput" placeholder="Search by tag number..." onkeyup="filterTable()">
        </div>

        <div class="table-container">
            <table class="table">
               <thead>
                    <tr>
                        <th>ID</th>
                        <th>Tag No</th>
                        <th>Treatment</th>
                        <th>Dosage</th>
                        <th>Qty Used</th>
                        <th>Date & Time</th>
                        <th>Remarks</th>
                        <th style="text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody id="transaction-table">
                    <?php foreach($transactions_data as $transaction): 
                        // Convert DB date to ISO for JS (edit) and Nice format for display
                        $dbDate = $transaction['DATE_FULL'];
                        $dateObj = new DateTime($dbDate);
                        $isoDate = $dateObj->format('Y-m-d\TH:i'); // For input type="datetime-local"
                        $displayDate = $dateObj->format('M d, Y h:i A'); // For display
                    ?>
                    <tr data-tt-id="<?= $transaction['TT_ID'] ?>"
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
                        
                        <td><div class="item-id">TT-<?= str_pad($transaction['TT_ID'], 4, '0', STR_PAD_LEFT) ?></div></td>
                        <td><span class="tag-badge"><?= htmlspecialchars($transaction['TAG_NO']) ?></span></td>
                        <td><div class="item-name"><?= htmlspecialchars($transaction['ITEM_NAME']) ?></div></td>
                        <td><span class="dosage-badge"><?= htmlspecialchars($transaction['DOSAGE'] ?? 'N/A') ?></span></td>
                        <td>
                            <span class="quantity-badge">
                                <?= number_format($transaction['QUANTITY_USED'] ?? 0, 2) ?> 
                                <?= htmlspecialchars($transaction['UNIT_ABBR'] ?? '') ?>
                            </span>
                        </td>
                        <td><div class="item-unit"><?= $displayDate ?></div></td>
                        <td><div class="item-unit"><?= htmlspecialchars(substr($transaction['REMARKS'] ?? '', 0, 30)) ?></div></td>
                        <td>
                            <div class="actions">
                                <button class="action-btn edit" onclick="editTransaction(this)" title="Edit">
                                    <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                </button>
                                <button class="action-btn delete" onclick="deleteTransaction(this)" title="Delete">
                                    <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div id="empty-state" class="empty-state"><h3>No transactions found</h3></div>
        </div>
    </div>

    <div id="modal" class="modal">
        <div class="modal-content">
            <div class="modal-header"><h2 id="modal-title">Add Transaction</h2></div>
            <div class="modal-body">
                <div id="modal-alert" class="alert"></div>
                <form id="transaction-form" method="POST">
                    <input type="hidden" id="tt-id" name="tt_id">
                    
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
                            <label for="animal">Animal (Tag No) <span>*</span></label>
                            <select id="animal" name="animal_id" required disabled>
                                <option value="">Select Pen First</option>
                            </select>
                        </div>

                        <h3>2. Treatment Details</h3>
                        <div class="form-group">
                            <label for="item">Medication <span>*</span></label>
                            <select id="item" name="item_id" required onchange="updateStockInfo()">
                                <option value="">Select Medication</option>
                                <?php foreach($items as $item): ?>
                                    <option value="<?= $item['ITEM_ID'] ?>" 
                                            data-quantity="<?= $item['TOTAL_QTY'] ?? 0 ?>"
                                            data-units="<?= $item['UNIT_ABBR'] ?? 'pcs' ?>">
                                            <?= htmlspecialchars($item['ITEM_NAME']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div id="stock-info" class="stock-info"></div>
                        </div>

                        <div class="form-group">
                            <label for="dosage">Dosage Instructions <span>*</span></label>
                            <input type="text" id="dosage" name="dosage" placeholder="e.g., 10ml daily" required>
                        </div>

                        <div class="form-group">
                            <label for="qty-used">Quantity Used <span id="units_used_span">*</span></label>
                            <input type="number" id="qty-used" name="quantity_used" placeholder="0.00" step="0.01" min="0" required>
                        </div>

                        <div class="form-group">
                            <label for="transaction-date">Date & Time <span>*</span></label>
                            <input type="datetime-local" id="transaction-date" name="transaction_date" required>
                        </div>

                        <div class="form-group">
                            <label for="remarks">Remarks</label>
                            <textarea id="remarks" name="remarks" rows="2"></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeModal()">Cancel</button>
                <button type="button" class="btn-save" id="btn-save" onclick="saveTransaction()">Save</button>
            </div>
        </div>
    </div>

    <div id="view-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header"><h2>Transaction Details</h2></div>
            <div class="modal-body" id="view-modal-body"></div>
            <div class="modal-footer"><button type="button" class="btn-cancel" onclick="closeViewModal()">Close</button></div>
        </div>
    </div>

    <form id="deleteTransactionForm" method="POST" action="../process/deleteTreatmentTransaction.php" style="display: none;">
        <input type="hidden" id="delete_tt_id" name="tt_id">
    </form>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Set Default Time to Now (ISO local format)
            const now = new Date();
            now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
            document.getElementById('transaction-date').value = now.toISOString().slice(0,16);
            
            checkEmptyState();
        });

        // --- CASCADING LOGIC (PROMISE BASED) ---
        function loadBuildings(locId) {
            return new Promise((resolve) => {
                const bldgSel = document.getElementById('building_id');
                const penSel = document.getElementById('pen_id');
                const animalSel = document.getElementById('animal');
                
                bldgSel.innerHTML = '<option>Loading...</option>';
                bldgSel.disabled = true;
                penSel.innerHTML = '<option value="">Select Bldg First</option>';
                penSel.disabled = true;
                animalSel.innerHTML = '<option value="">Select Pen First</option>';
                animalSel.disabled = true;

                if(!locId) {
                    bldgSel.innerHTML = '<option value="">Select Loc First</option>';
                    resolve();
                    return;
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
                        } else {
                            bldgSel.innerHTML = '<option value="">No Buildings</option>';
                        }
                        resolve();
                    });
            });
        }

        function loadPens(bldgId) {
            return new Promise((resolve) => {
                const penSel = document.getElementById('pen_id');
                const animalSel = document.getElementById('animal');
                
                penSel.innerHTML = '<option>Loading...</option>';
                penSel.disabled = true;
                animalSel.innerHTML = '<option value="">Select Pen First</option>';
                animalSel.disabled = true;

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
                        } else {
                            penSel.innerHTML = '<option value="">No Pens</option>';
                        }
                        resolve();
                    });
            });
        }

        function loadAnimals(penId) {
            return new Promise((resolve) => {
                const animalSel = document.getElementById('animal');
                animalSel.innerHTML = '<option>Loading...</option>';
                animalSel.disabled = true;

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
                        } else {
                            animalSel.innerHTML = '<option value="">No Animals</option>';
                        }
                        resolve();
                    });
            });
        }

        // --- EDIT LOGIC (ASYNC FOR CASCADE) ---
        async function editTransaction(button) {
            const row = button.closest('tr');
            const data = {
                tt_id: row.dataset.ttId,
                animal_id: row.dataset.animalId,
                location_id: row.dataset.locationId,
                building_id: row.dataset.buildingId,
                pen_id: row.dataset.penId,
                item_id: row.dataset.itemId,
                dosage: row.dataset.dosage,
                qty_used: row.dataset.qtyUsed,
                transaction_date: row.dataset.transactionDate, // ISO Format
                remarks: row.dataset.remarks,
                tag_no: row.dataset.tagNo
            };
            
            document.getElementById('modal-title').textContent = 'Edit Transaction';
            document.getElementById('btn-save').textContent = 'Update';
            document.getElementById('tt-id').value = data.tt_id;
            
            // 1. Set Location
            document.getElementById('location_id').value = data.location_id || "";
            
            // 2. Load Buildings & Set
            if(data.location_id) {
                await loadBuildings(data.location_id);
                document.getElementById('building_id').value = data.building_id || "";
            }

            // 3. Load Pens & Set
            if(data.building_id) {
                await loadPens(data.building_id);
                document.getElementById('pen_id').value = data.pen_id || "";
            }

            // 4. Load Animals & Set
            if(data.pen_id) {
                await loadAnimals(data.pen_id);
                document.getElementById('animal').value = data.animal_id;
            } else {
                // Fallback: If no pen data, manually add the animal option so it shows up
                const animalSel = document.getElementById('animal');
                animalSel.innerHTML = `<option value="${data.animal_id}" selected>${data.tag_no}</option>`;
                animalSel.disabled = false;
            }

            document.getElementById('item').value = data.item_id;
            document.getElementById('dosage').value = data.dosage;
            document.getElementById('qty-used').value = data.qty_used;
            document.getElementById('transaction-date').value = data.transaction_date;
            document.getElementById('remarks').value = data.remarks;
            
            updateStockInfo();
            hideAlert();
            document.getElementById('modal').classList.add('show');
        }

        function openAddModal() {
            document.getElementById('modal-title').textContent = 'Add Transaction';
            document.getElementById('btn-save').textContent = 'Save';
            document.getElementById('transaction-form').reset();
            document.getElementById('tt-id').value = '';
            
            // Reset Cascades
            document.getElementById('location_id').value = "";
            document.getElementById('building_id').innerHTML = '<option value="">Select Loc First</option>';
            document.getElementById('building_id').disabled = true;
            document.getElementById('pen_id').innerHTML = '<option value="">Select Bldg First</option>';
            document.getElementById('pen_id').disabled = true;
            document.getElementById('animal').innerHTML = '<option value="">Select Pen First</option>';
            document.getElementById('animal').disabled = true;

            const now = new Date();
            now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
            document.getElementById('transaction-date').value = now.toISOString().slice(0,16);
            
            hideAlert();
            document.getElementById('stock-info').style.display = 'none';
            document.getElementById('modal').classList.add('show');
        }

        function updateStockInfo() {
            const itemSelect = document.getElementById('item');
            const selectedOption = itemSelect.options[itemSelect.selectedIndex];
            const stockInfo = document.getElementById('stock-info');
            
            if (!selectedOption.value) {
                stockInfo.style.display = 'none';
                document.getElementById("units_used_span").textContent = '*';
                return;
            }
            document.getElementById("units_used_span").textContent = '(' + (selectedOption.dataset.units || 'units') + ') *';
            const quantity = parseFloat(selectedOption.dataset.quantity) || 0;
            stockInfo.textContent = '📦 Available Stock: ' + quantity.toFixed(2) + ' ' + selectedOption.dataset.units;
            stockInfo.className = quantity < 10 ? 'stock-info low-stock' : 'stock-info';
            stockInfo.style.display = 'block';
        }

        function saveTransaction() {
            const form = document.getElementById('transaction-form');
            if (!form.checkValidity()) { form.reportValidity(); return; }

            const formData = new FormData(form);
            const isEdit = document.getElementById('tt-id').value !== '';
            const url = isEdit ? '../process/editTreatmentTransaction.php' : '../process/addTreatmentTransaction.php';
            const saveBtn = document.getElementById('btn-save');
            
            saveBtn.disabled = true;
            saveBtn.innerHTML = 'Saving...';
            
            // Enable disabled fields for submission
            document.getElementById('animal').disabled = false;
            
            fetch(url, { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showAlert(data.message, 'success');
                    setTimeout(() => { closeModal(); window.location.reload(); }, 1000);
                } else {
                    showAlert(data.message, 'error');
                    saveBtn.disabled = false;
                    saveBtn.textContent = isEdit ? 'Update' : 'Save';
                }
            });
        }

        function deleteTransaction(button) {
            const row = button.closest('tr');
            if (confirm(`Delete transaction for "${row.dataset.tagNo}"?`)) {
                document.getElementById('delete_tt_id').value = row.dataset.ttId;
                document.getElementById('deleteTransactionForm').submit();
            }
        }

        function closeModal() { document.getElementById('modal').classList.remove('show'); }
        function closeViewModal() { document.getElementById('view-modal').classList.remove('show'); }
        function showAlert(msg, type) {
            const el = document.getElementById('modal-alert');
            el.textContent = msg; el.className = 'alert show ' + type; el.style.display = 'block';
        }
        function hideAlert() { document.getElementById('modal-alert').style.display = 'none'; }
        
        function checkEmptyState() {
            const rows = document.querySelectorAll('#transaction-table tr');
            document.getElementById('empty-state').style.display = rows.length === 0 ? 'block' : 'none';
        }

        function filterTable() {
            const filter = document.getElementById('searchInput').value.toLowerCase();
            const rows = document.querySelectorAll('#transaction-table tr');
            let visible = 0;
            rows.forEach(r => {
                const txt = r.dataset.tagNo + ' ' + r.dataset.itemName;
                if(txt.toLowerCase().includes(filter)) { r.style.display = ''; visible++; }
                else { r.style.display = 'none'; }
            });
            document.getElementById('empty-state').style.display = visible === 0 ? 'block' : 'none';
        }
    </script>
</body>
</html>