<?php
// views/purch_others.php
error_reporting(0);
ini_set('display_errors', 0);

$page="transactions";
include '../common/navbar.php';
include '../config/Connection.php';
// include '../config/Queries.php'; // Not needed for direct PDO
include '../security/checkRole.php';    
checkRole(3);

// --- CONFIGURATION ---
$ITEM_TYPE_ID = 12; // Others
// ---------------------

try {
    if (!isset($conn)) {
        throw new Exception("Database connection failed.");
    }

    // 1. Fetch Items
    $items_sql = "SELECT i.*, 
                  it.ITEM_TYPE_NAME,
                  u.UNIT_NAME
                  FROM ITEMS i
                  LEFT JOIN ITEM_TYPES it ON i.ITEM_TYPE_ID = it.ITEM_TYPE_ID
                  LEFT JOIN UNITS u ON i.UNIT_ID = u.UNIT_ID
                  WHERE i.ITEM_TYPE_ID = :type_id
                  ORDER BY i.CREATED_AT DESC";
    
    $stmt = $conn->prepare($items_sql);
    $stmt->execute([':type_id' => $ITEM_TYPE_ID]);
    $items_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Fetch Units
    $units_sql = "SELECT * FROM UNITS ORDER BY UNIT_NAME ASC";
    $stmt = $conn->prepare($units_sql);
    $stmt->execute();
    $units = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Location Hierarchy
    $loc_sql = "SELECT * FROM LOCATIONS ORDER BY LOCATION_NAME ASC";
    $stmt = $conn->prepare($loc_sql);
    $stmt->execute();
    $locations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $bldg_sql = "SELECT * FROM BUILDINGS ORDER BY BUILDING_NAME ASC";
    $stmt = $conn->prepare($bldg_sql);
    $stmt->execute();
    $buildings_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $pens_sql = "SELECT * FROM PENS ORDER BY PEN_NAME ASC";
    $stmt = $conn->prepare($pens_sql);
    $stmt->execute();
    $pens_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $items_data = [];
    $units = [];
    $locations = [];
    $buildings_raw = [];
    $pens_raw = [];
    echo "<script>console.error('Database Error: " . addslashes($e->getMessage()) . "');</script>";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Others Purchase Management</title>
    <link rel="stylesheet" href="../css/purch_others.css">
    <style>
        /* --- Reusing existing styles --- */
        .autocomplete-wrapper { position: relative; }
        .autocomplete-list { position: absolute; z-index: 1000; top: 100%; left: 0; right: 0; background: white; border: 1px solid #ddd; border-top: none; border-radius: 0 0 8px 8px; max-height: 200px; overflow-y: auto; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); display: none; }
        .autocomplete-list.show { display: block; }
        .autocomplete-item { padding: 12px 15px; cursor: pointer; transition: background-color 0.2s; border-bottom: 1px solid #f0f0f0; }
        .autocomplete-item:last-child { border-bottom: none; }
        .autocomplete-item:hover, .autocomplete-item.active { background-color: #f0f7ff; }
        .autocomplete-item strong { color: #2563eb; }
        .autocomplete-loading, .autocomplete-no-results { padding: 12px 15px; text-align: center; color: #666; font-size: 14px; }
        
        input[readonly], select[disabled] {
            background-color: #f1f5f9;
            cursor: not-allowed;
            color: #475569;
        }

        /* --- CONFIRMATION STYLES --- */
        .confirm-btn {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.2s;
            box-shadow: 0 2px 4px rgba(239, 68, 68, 0.3);
            width: 100%;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .confirm-btn:hover {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
            transform: translateY(-1px);
            box-shadow: 0 4px 6px rgba(239, 68, 68, 0.4);
        }

        /* Header Actions */
        .header-actions { display: flex; gap: 10px; align-items: center; }

        .confirm-all-btn {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
            transition: all 0.2s;
            box-shadow: 0 4px 6px rgba(245, 158, 11, 0.3);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .confirm-all-btn:hover {
            background: linear-gradient(135deg, #d97706, #b45309);
            transform: translateY(-1px);
            box-shadow: 0 6px 8px rgba(245, 158, 11, 0.4);
        }

        .confirmed-badge {
            display: inline-block;
            width: 100%;
            text-align: center;
            padding: 8px 0;
            background: rgba(16, 185, 129, 0.1);
            color: #059669;
            border: 1px solid rgba(16, 185, 129, 0.2);
            border-radius: 6px;
            font-weight: 700;
            font-size: 0.85rem;
            text-transform: uppercase;
        }

        /* Badges for Category */
        .category-badge { padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; }
        .category-nonconsumable { background: rgba(59, 130, 246, 0.1); color: #2563eb; border: 1px solid rgba(59, 130, 246, 0.2); }
        .category-consumable { background: rgba(16, 185, 129, 0.1); color: #059669; border: 1px solid rgba(16, 185, 129, 0.2); }

        /* Modal Specifics */
        .confirm-content { text-align: center; padding: 20px; }
        .confirm-icon { font-size: 4rem; margin-bottom: 15px; display: block; }
        .warning-text {
            color: #64748b; font-size: 0.9rem; margin: 15px 0 25px 0;
            background: #f8fafc; padding: 10px; border-radius: 8px; border: 1px solid #e2e8f0;
        }

        /* --- TABLE SCROLL FIX --- */
        .table-container {
            width: 100%;
            overflow-x: auto; 
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
            border: 1px solid #e2e8f0;
        }

        table.table {
            width: 100%;
            min-width: 1600px; 
            border-collapse: collapse;
        }

        table.table th, table.table td {
            white-space: nowrap;
            padding: 1rem;
            vertical-align: middle;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="header-info">
                <h1>Others Purchase</h1>
                <p>Manage and track miscellaneous purchases</p>
            </div>
            
            <div class="header-actions">
                <button class="confirm-all-btn" onclick="openConfirmAllModal()">
                    <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width:20px;height:20px;">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Confirm All Pending
                </button>

                <button class="add-btn" onclick="openAddModal()">
                    <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                    </svg>
                    Add Purchase
                </button>
            </div>
        </div>

        <div class="search-container">
            <svg class="search-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m21 21-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
            </svg>
            <input type="text" class="search-input" id="searchInput" placeholder="Search by item name, unit, or category..." onkeyup="filterTable()">
        </div>

        

        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Item ID</th>
                        <th>Item Name</th>
                        <th>Quantity</th>
                        <th>Unit</th>
                        <th>Net Weight</th>
                        <th>Unit Cost</th>
                        <th>Total Cost</th> 
                        <th>Category</th>
                        <th>Purchase Date</th>
                        <th style="text-align: center; width: 150px;">Confirmation</th>
                        <th style="text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody id="item-table">
                    <?php 
                    $categoryLabels = [0 => 'Non-Consumable', 1 => 'Consumable'];
                    $categoryClasses = [0 => 'category-nonconsumable', 1 => 'category-consumable'];

                    foreach($items_data as $item): 
                        $status = isset($item['STATUS']) ? (int)$item['STATUS'] : 0;
                        $isConfirmed = ($status === 1);
                        
                        // Calculate Total Cost
                        $totalCost = $item['TOTAL_COST'] ?? ($item['QUANTITY'] * $item['UNIT_COST']);
                    ?>
                    <tr data-item-id="<?php echo $item['ITEM_ID']; ?>"
                        data-item-name="<?php echo htmlspecialchars($item['ITEM_NAME']); ?>"
                        data-item-desc="<?php echo htmlspecialchars($item['ITEM_DESCRIPTION'] ?? ''); ?>"
                        data-unit-id="<?php echo $item['UNIT_ID']; ?>"
                        data-unit-cost="<?php echo $item['UNIT_COST']; ?>"
                        data-item-category="<?php echo $item['ITEM_CATEGORY']; ?>"
                        data-unit-name="<?php echo htmlspecialchars($item['UNIT_NAME']); ?>"
                        data-net-weight="<?php echo $item['ITEM_NET_WEIGHT'] ?? '0'; ?>"
                        data-quantity="<?php echo $item['QUANTITY'] ?? '0'; ?>"
                        data-purchase-date="<?php echo htmlspecialchars($item['DATE_OF_PURCHASE'] ?? ''); ?>"
                        data-location-id="<?php echo $item['LOCATION_ID'] ?? ''; ?>"
                        data-building-id="<?php echo $item['BUILDING_ID'] ?? ''; ?>"
                        data-pen-id="<?php echo $item['PEN_ID'] ?? ''; ?>"
                        data-created-at="<?php echo $item['CREATED_AT']; ?>">
                        <td>
                            <div class="item-id">OTH-<?php echo str_pad($item['ITEM_ID'], 4, '0', STR_PAD_LEFT); ?></div>
                        </td>
                        <td>
                            <div class="item-name"><?php echo htmlspecialchars($item['ITEM_NAME']); ?></div>
                        </td>
                        <td>
                            <div class="item-unit"><?php echo number_format($item['QUANTITY'] ?? 0, 2); ?></div>
                        </td>
                        <td>
                            <div class="item-unit"><?php echo htmlspecialchars($item['UNIT_NAME']); ?></div>
                        </td>
                        <td>
                            <div class="item-unit"><?php echo htmlspecialchars($item['ITEM_NET_WEIGHT'] ?? 'N/A'); ?></div>
                        </td>
                        <td>
                            <div class="amount">₱<?php echo number_format($item['UNIT_COST'], 2); ?></div>
                        </td>
                        
                        <td>
                            <div class="amount" style="font-weight:bold; color:#2563eb;">₱<?php echo number_format($totalCost, 2); ?></div>
                        </td>

                        <td>
                            <span class="category-badge <?php echo $categoryClasses[$item['ITEM_CATEGORY']]; ?>">
                                <?php echo $categoryLabels[$item['ITEM_CATEGORY']]; ?>
                            </span>
                        </td>
                        <td>
                            <div class="item-unit"><?php echo htmlspecialchars($item['DATE_OF_PURCHASE'] ?? 'N/A'); ?></div>
                        </td>

                        <td style="text-align: center;">
                            <?php if(!$isConfirmed): ?>
                                <button class="confirm-btn" onclick="openConfirmModal(this)">
                                    Confirm
                                </button>
                            <?php else: ?>
                                <div class="confirmed-badge">
                                    Confirmed
                                </div>
                            <?php endif; ?>
                        </td>

                        <td>
                            <div class="actions">
                                <button class="action-btn view" onclick="viewItem(this)" title="View Details">
                                    <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                    </svg>
                                </button>

                                <?php if(!$isConfirmed): ?>
                                    <button class="action-btn edit" onclick="editItem(this)" title="Edit">
                                        <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                        </svg>
                                    </button>
                                    <button class="action-btn delete" onclick="deleteItem(this)" title="Delete">
                                        <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                        </svg>
                                    </button>
                                <?php else: ?>
                                    <span style="font-size: 1.2em; opacity: 0.3; cursor: not-allowed; margin-left: 10px;">🔒</span>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div id="empty-state" class="empty-state">
                <h3>No items found</h3>
                <p>Try adjusting your search terms</p>
            </div>
        </div>
    </div>

    <div id="modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modal-title">Add Others Purchase</h2>
            </div>
            <div class="modal-body">
                <div id="modal-alert" class="alert"></div>
                <form id="item-form" method="POST">
                    <input type="hidden" id="item-id" name="item_id">
                    <input type="hidden" name="item_type_id" value="<?php echo $ITEM_TYPE_ID; ?>">
                    
                    <div class="info-group">
                        <h3>Item Information</h3>
                        
                        <div class="form-group autocomplete-wrapper">
                            <label for="item-name">Item Name <span>*</span></label>
                            <input type="text" id="item-name" name="item_name" placeholder="e.g., Miscellaneous Item" required maxlength="300" autocomplete="off">
                            <div id="autocomplete-list" class="autocomplete-list"></div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="net-weight">Net Weight</label>
                                <input type="number" id="net-weight" name="item_net_weight" placeholder="e.g., 500" step="0.01" min="0">
                            </div>

                            <div class="form-group">
                                <label for="unit">Unit of Measurement <span>*</span></label>
                                <select id="unit" name="unit_id" required>
                                    <option value="">Select Unit</option>
                                    <?php foreach($units as $unit): ?>
                                        <option value="<?php echo $unit['UNIT_ID']; ?>">
                                            <?php echo htmlspecialchars($unit['UNIT_NAME']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="item-quantity">Quantity <span>*</span></label>
                            <input type="number" id="item-quantity" name="item_quantity" placeholder="e.g., 10" step="0.01" min="0" required>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="unit-cost">Unit Cost (₱) <span>*</span></label>
                                <input type="number" id="unit-cost" name="unit_cost" placeholder="0.00" step="0.01" min="0" required>
                            </div>

                            <div class="form-group">
                                <label for="item-category">Item Category <span>*</span></label>
                                <select id="item-category" name="item_category" required>
                                    <option value="">Select Category</option>
                                    <option value="0">Non-Consumable</option>
                                    <option value="1">Consumable</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="purchase-date">Date of Purchase <span>*</span></label>
                            <input type="date" id="purchase-date" name="date_of_purchase" required>
                        </div>

                        <div class="form-group">
                            <label for="item-desc">Item Description</label>
                            <textarea id="item-desc" name="item_description" placeholder="Enter detailed description, specifications, or notes" rows="3" maxlength="500"></textarea>
                        </div>
                    </div>

                    <div class="info-group">
                        <h3>Initial Location</h3>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="location_id">Location</label>
                                <select id="location_id" name="location_id" onchange="filterBuildings()">
                                    <option value="">Select Location</option>
                                    <?php foreach($locations as $loc): ?>
                                        <option value="<?php echo $loc['LOCATION_ID']; ?>">
                                            <?php echo htmlspecialchars($loc['LOCATION_NAME']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="building_id">Building</label>
                                <select id="building_id" name="building_id" onchange="filterPens()" disabled>
                                    <option value="">Select Location First</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="pen_id">Pen</label>
                                <select id="pen_id" name="pen_id" disabled>
                                    <option value="">Select Building First</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeModal()">Cancel</button>
                <button type="button" class="btn-save" id="btn-save" onclick="saveItem()">Save Purchase</button>
            </div>
        </div>
    </div>

    <div id="view-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Purchase Details</h2>
            </div>
            <div class="modal-body" id="view-modal-body">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeViewModal()">Close</button>
            </div>
        </div>
    </div>

    <div id="confirm-modal" class="modal">
        <div class="modal-content" style="max-width: 450px;">
            <div class="modal-body confirm-content">
                <span class="confirm-icon">📦</span>
                <h2 style="color: #1e293b; margin-bottom: 10px;">Confirm Purchase?</h2>
                <p style="color: #64748b; margin-bottom: 5px;">You are about to confirm <strong><span id="confirm-item-qty"></span> <span id="confirm-item-name"></span></strong>.</p>
                <div class="warning-text">
                    ⚠️ <strong>Warning:</strong> Once confirmed, this record will be locked and can no longer be edited or deleted.
                </div>
                <form id="confirmForm" method="POST">
                    <input type="hidden" id="confirm_item_id" name="item_id">
                </form>
            </div>
            <div class="modal-footer" style="justify-content: center; border-top: none; padding-top: 0; padding-bottom: 30px;">
                <button type="button" class="btn-cancel" onclick="closeConfirmModal()">Cancel</button>
                <button type="button" class="btn-save" onclick="submitConfirmation()" style="background: linear-gradient(135deg, #ef4444, #dc2626);">Yes, Confirm it!</button>
            </div>
        </div>
    </div>

    <div id="confirm-all-modal" class="modal">
        <div class="modal-content" style="max-width: 450px;">
            <div class="modal-body confirm-content">
                <span class="confirm-icon" style="font-size: 3rem;">📋</span>
                <h2 style="color: #1e293b; margin-bottom: 10px;">Confirm All Pending?</h2>
                <p style="color: #64748b;">This will confirm and lock <strong>ALL</strong> currently pending others purchases.</p>
                <div class="warning-text">
                    ⚠️ <strong>Warning:</strong> This action cannot be undone.
                </div>
            </div>
            <div class="modal-footer" style="justify-content: center; border-top: none; padding-top: 0; padding-bottom: 30px;">
                <button type="button" class="btn-cancel" onclick="closeConfirmAllModal()">Cancel</button>
                <button type="button" class="btn-save" onclick="submitConfirmAll()" style="background: linear-gradient(135deg, #f59e0b, #d97706);">Confirm All</button>
            </div>
        </div>
    </div>

    <form id="deleteItemForm" method="POST" action="../process/deleteOtherPurchase.php" style="display: none;">
        <input type="hidden" id="delete_item_id" name="item_id">
    </form>

    <script>
        const allBuildings = <?php echo json_encode($buildings_raw); ?>;
        const allPens = <?php echo json_encode($pens_raw); ?>;

        // --- Confirmation Logic ---
        function openConfirmModal(button) {
            const row = button.closest('tr');
            const itemId = row.dataset.itemId;
            const itemName = row.dataset.itemName;
            const itemQty = row.dataset.quantity;

            document.getElementById('confirm_item_id').value = itemId;
            document.getElementById('confirm-item-name').textContent = itemName;
            document.getElementById('confirm-item-qty').textContent = itemQty;

            document.getElementById('confirm-modal').classList.add('show');
        }

        function closeConfirmModal() {
            document.getElementById('confirm-modal').classList.remove('show');
        }

        function submitConfirmation() {
            const form = document.getElementById('confirmForm');
            const formData = new FormData(form);
            const confirmBtn = document.querySelector('#confirm-modal .btn-save');
            
            confirmBtn.disabled = true;
            confirmBtn.innerHTML = 'Confirming...';

            fetch('../purchase_confirmations/confirmOthers.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    window.location.reload();
                } else {
                    alert(data.message);
                    confirmBtn.disabled = false;
                    confirmBtn.innerHTML = 'Yes, Confirm it!';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while confirming.');
                confirmBtn.disabled = false;
                confirmBtn.innerHTML = 'Yes, Confirm it!';
            });
        }

        function openConfirmAllModal() {
            document.getElementById('confirm-all-modal').classList.add('show');
        }

        function closeConfirmAllModal() {
            document.getElementById('confirm-all-modal').classList.remove('show');
        }

        function submitConfirmAll() {
            const confirmBtn = document.querySelector('#confirm-all-modal .btn-save');
            confirmBtn.disabled = true;
            confirmBtn.innerHTML = 'Processing...';

            fetch('../purchase_confirmations/confirmAllOthers.php', {
                method: 'POST'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    window.location.reload();
                } else {
                    alert(data.message);
                    confirmBtn.disabled = false;
                    confirmBtn.innerHTML = 'Confirm All';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while confirming all items.');
                confirmBtn.disabled = false;
                confirmBtn.innerHTML = 'Confirm All';
            });
        }
        // --------------------------

        function filterBuildings() {
            const locationSelect = document.getElementById('location_id');
            const buildingSelect = document.getElementById('building_id');
            const penSelect = document.getElementById('pen_id');
            const selectedLocId = locationSelect.value;

            buildingSelect.innerHTML = '<option value="">Select Building</option>';
            penSelect.innerHTML = '<option value="">Select Building First</option>';
            penSelect.disabled = true;

            if (selectedLocId) {
                buildingSelect.disabled = false;
                const filteredBuildings = allBuildings.filter(b => b.LOCATION_ID == selectedLocId);
                filteredBuildings.forEach(b => {
                    const option = document.createElement('option');
                    option.value = b.BUILDING_ID;
                    option.textContent = b.BUILDING_NAME;
                    buildingSelect.appendChild(option);
                });
            } else {
                buildingSelect.disabled = true;
            }
        }

        function filterPens() {
            const buildingSelect = document.getElementById('building_id');
            const penSelect = document.getElementById('pen_id');
            const selectedBldgId = buildingSelect.value;

            penSelect.innerHTML = '<option value="">Select Pen</option>';

            if (selectedBldgId) {
                penSelect.disabled = false;
                const filteredPens = allPens.filter(p => p.BUILDING_ID == selectedBldgId);
                filteredPens.forEach(p => {
                    const option = document.createElement('option');
                    option.value = p.PEN_ID;
                    option.textContent = p.PEN_NAME;
                    penSelect.appendChild(option);
                });
            } else {
                penSelect.disabled = true;
            }
        }

        let autocompleteTimeout = null;
        let currentFocus = -1;

        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('purchase-date').value = today;
            checkEmptyState();
        });

        function initAutocomplete() {
            const itemNameInput = document.getElementById('item-name');
            const autocompleteList = document.getElementById('autocomplete-list');
            
            const newInput = itemNameInput.cloneNode(true);
            itemNameInput.parentNode.replaceChild(newInput, itemNameInput);
            const input = document.getElementById('item-name');
            
            input.addEventListener('input', function(e) {
                const value = this.value.trim();
                clearTimeout(autocompleteTimeout);
                if (value.length < 2) { closeAutocomplete(); return; }
                autocompleteList.innerHTML = '<div class="autocomplete-loading">Searching...</div>';
                autocompleteList.classList.add('show');
                autocompleteTimeout = setTimeout(() => { fetchAutocomplete(value); }, 300);
            });
            
            input.addEventListener('keydown', function(e) {
                const items = autocompleteList.getElementsByClassName('autocomplete-item');
                if (e.keyCode === 40) { e.preventDefault(); currentFocus++; addActive(items); } 
                else if (e.keyCode === 38) { e.preventDefault(); currentFocus--; addActive(items); } 
                else if (e.keyCode === 13) { if (currentFocus > -1 && items[currentFocus]) { e.preventDefault(); items[currentFocus].click(); } } 
                else if (e.keyCode === 27) { closeAutocomplete(); }
            });
            
            document.addEventListener('click', function(e) {
                if (!e.target.closest('.autocomplete-wrapper')) { closeAutocomplete(); }
            });
        }

        function fetchAutocomplete(searchTerm) {
            fetch(`../process/searchOthers.php?term=${encodeURIComponent(searchTerm)}`)
                .then(response => response.json())
                .then(data => { displayAutocomplete(data, searchTerm); })
                .catch(error => { console.error('Autocomplete error:', error); closeAutocomplete(); });
        }

        function displayAutocomplete(results, searchTerm) {
            const list = document.getElementById('autocomplete-list');
            list.innerHTML = ''; currentFocus = -1;
            if (results.length === 0) {
                list.innerHTML = '<div class="autocomplete-no-results">No items found</div>';
                list.classList.add('show'); return;
            }
            results.forEach(item => {
                const div = document.createElement('div');
                div.className = 'autocomplete-item';
                const regex = new RegExp(`(${escapeRegex(searchTerm)})`, 'gi');
                const highlighted = item.replace(regex, '<strong>$1</strong>');
                div.innerHTML = highlighted;
                div.addEventListener('click', function() {
                    document.getElementById('item-name').value = item;
                    closeAutocomplete();
                });
                list.appendChild(div);
            });
            list.classList.add('show');
        }

        function addActive(items) {
            if (!items || items.length === 0) return;
            removeActive(items);
            if (currentFocus >= items.length) currentFocus = 0;
            if (currentFocus < 0) currentFocus = items.length - 1;
            items[currentFocus].classList.add('active');
            items[currentFocus].scrollIntoView({ block: 'nearest' });
        }

        function removeActive(items) {
            for (let i = 0; i < items.length; i++) { items[i].classList.remove('active'); }
        }

        function closeAutocomplete() {
            const list = document.getElementById('autocomplete-list');
            if (list) { list.classList.remove('show'); list.innerHTML = ''; }
            currentFocus = -1;
        }

        function escapeRegex(string) { return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); }

        function openAddModal() {
            document.getElementById('modal-title').textContent = 'Add Others Purchase';
            document.querySelector('.btn-save').textContent = 'Save Purchase';
            document.getElementById('item-form').reset();
            document.getElementById('item-id').value = '';
            document.getElementById('item-category').value = '';
            document.getElementById('unit').value = '';
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('purchase-date').value = today;
            
            document.getElementById('location_id').value = "";
            document.getElementById('building_id').innerHTML = '<option value="">Select Location First</option>';
            document.getElementById('building_id').disabled = true;
            document.getElementById('pen_id').innerHTML = '<option value="">Select Building First</option>';
            document.getElementById('pen_id').disabled = true;
            // -----------------------------

            hideAlert();
            document.getElementById('modal').classList.add('show');
            setTimeout(() => { initAutocomplete(); }, 100);
        }

        function viewItem(button) {
            const row = button.closest('tr');
            const data = {
                item_id: row.dataset.itemId,
                item_name: row.dataset.itemName,
                item_description: row.dataset.itemDesc,
                unit: row.dataset.unitName,
                unit_cost: row.dataset.unitCost,
                item_category: row.dataset.itemCategory,
                net_weight: row.dataset.netWeight,
                quantity: row.dataset.quantity,
                purchase_date: row.dataset.purchaseDate,
                created_at: row.dataset.createdAt
            };
            displayItemDetails(data);
        }

        function displayItemDetails(data) {
            const categoryLabels = {0: 'Non-Consumable', 1: 'Consumable'};
            const html = `
                <div class="info-group">
                    <h3>Basic Information</h3>
                    <p><strong>Item ID:</strong> ITEM-${String(data.item_id).padStart(4, '0')}</p>
                    <p><strong>Item Name:</strong> ${data.item_name}</p>
                    <p><strong>Description:</strong> ${data.item_description || 'N/A'}</p>
                    <p><strong>Created:</strong> ${data.created_at}</p>
                </div>
                <div class="info-group">
                    <h3>Purchase Details</h3>
                    <p><strong>Quantity:</strong> ${data.quantity || '0'}</p>
                    <p><strong>Unit:</strong> ${data.unit}</p>
                    <p><strong>Unit Cost:</strong> ₱${parseFloat(data.unit_cost).toLocaleString('en-PH', {minimumFractionDigits: 2})}</p>
                    <p><strong>Net Weight:</strong> ${data.net_weight || 'N/A'}</p>
                    <p><strong>Category:</strong> ${categoryLabels[data.item_category]}</p>
                    <p><strong>Purchase Date:</strong> ${data.purchase_date || 'N/A'}</p>
                </div>
            `;
            document.getElementById('view-modal-body').innerHTML = html;
            document.getElementById('view-modal').classList.add('show');
        }

        function editItem(button) {
            const row = button.closest('tr');
            const data = {
                item_id: row.dataset.itemId,
                item_name: row.dataset.itemName,
                item_description: row.dataset.itemDesc,
                unit_id: row.dataset.unitId,
                unit_cost: row.dataset.unitCost,
                item_category: row.dataset.itemCategory,
                net_weight: row.dataset.netWeight,
                quantity: row.dataset.quantity,
                purchase_date: row.dataset.purchaseDate,
                location_id: row.dataset.locationId,
                building_id: row.dataset.buildingId,
                pen_id: row.dataset.penId
            };
            populateEditForm(data);
        }

        function populateEditForm(data) {
            document.getElementById('modal-title').textContent = 'Edit Others Purchase';
            document.querySelector('.btn-save').textContent = 'Update Purchase';
            document.getElementById('item-id').value = data.item_id;
            document.getElementById('item-name').value = data.item_name;
            document.getElementById('item-desc').value = data.item_description || '';
            document.getElementById('unit').value = data.unit_id;
            document.getElementById('unit-cost').value = data.unit_cost;
            document.getElementById('item-category').value = data.item_category;
            document.getElementById('net-weight').value = data.net_weight || '';
            document.getElementById('item-quantity').value = data.quantity || '0';
            document.getElementById('purchase-date').value = data.purchase_date || '';

            const locSelect = document.getElementById('location_id');
            locSelect.value = data.location_id || ""; 
            filterBuildings(); 

            const bldgSelect = document.getElementById('building_id');
            if(data.building_id) {
                bldgSelect.value = data.building_id;
                filterPens();
                const penSelect = document.getElementById('pen_id');
                if(data.pen_id) {
                    penSelect.value = data.pen_id;
                }
            }
            hideAlert();
            document.getElementById('modal').classList.add('show');
            setTimeout(() => { initAutocomplete(); }, 100);
        }

        function deleteItem(button) {
            const row = button.closest('tr');
            const itemId = row.dataset.itemId;
            const itemName = row.dataset.itemName;
            
            if (confirm(`Are you sure you want to delete "${itemName}"? This action cannot be undone.`)) {
                document.getElementById('delete_item_id').value = itemId;
                document.getElementById('deleteItemForm').submit();
            }
        }

        function saveItem() {
            const form = document.getElementById('item-form');
            if (!form.checkValidity()) { form.reportValidity(); return; }
            const formData = new FormData(form);
            const isEdit = document.getElementById('item-id').value !== '';
            const url = isEdit ? '../process/editOthers.php' : '../process/addOthers.php';
            const saveBtn = document.getElementById('btn-save');
            
            saveBtn.disabled = true;
            saveBtn.innerHTML = '<span class="loading"></span> ' + (isEdit ? 'Updating...' : 'Saving...');

            fetch(url, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert(data.message, 'success');
                    setTimeout(() => { window.location.reload(); }, 1500);
                } else {
                    showAlert(data.message || 'Error saving item', 'error');
                    saveBtn.disabled = false;
                    saveBtn.textContent = isEdit ? 'Update Purchase' : 'Save Purchase';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('An error occurred while saving the item', 'error');
                saveBtn.disabled = false;
                saveBtn.textContent = isEdit ? 'Update Purchase' : 'Save Purchase';
            });
        }

        function showAlert(message, type) {
            const alert = document.getElementById('modal-alert');
            alert.textContent = message;
            alert.className = 'alert ' + type;
            alert.style.display = 'block';
        }

        function hideAlert() {
            const alert = document.getElementById('modal-alert');
            alert.style.display = 'none';
        }

        function closeModal() {
            closeAutocomplete();
            document.getElementById('modal').classList.remove('show');
        }

        function closeViewModal() {
            document.getElementById('view-modal').classList.remove('show');
        }

        // document.getElementById('modal').addEventListener('click', function(e) {
        //     if (e.target === this) closeModal();
        // });

        document.getElementById('view-modal').addEventListener('click', function(e) {
            if (e.target === this) closeViewModal();
        });

        document.getElementById('confirm-modal').addEventListener('click', function(e) {
            if (e.target === this) closeConfirmModal();
        });

        document.getElementById('confirm-all-modal').addEventListener('click', function(e) {
            if (e.target === this) closeConfirmAllModal();
        });

        function filterTable() {
            const searchValue = document.getElementById('searchInput').value.toLowerCase();
            const table = document.getElementById('item-table');
            const rows = table.getElementsByTagName('tr');
            let visibleCount = 0;

            for (let i = 0; i < rows.length; i++) {
                const row = rows[i];
                const text = row.textContent.toLowerCase();
                if (text.includes(searchValue)) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            }
            checkEmptyState();
        }

        function checkEmptyState() {
            const table = document.getElementById('item-table');
            const rows = table.getElementsByTagName('tr');
            let visibleCount = 0;
            for (let i = 0; i < rows.length; i++) {
                if (rows[i].style.display !== 'none') visibleCount++;
            }
            const emptyState = document.getElementById('empty-state');
            if (visibleCount === 0) { emptyState.style.display = 'block'; } 
            else { emptyState.style.display = 'none'; }
        }

        document.getElementById('item-form').addEventListener('submit', function(e) {
            e.preventDefault();
            saveItem();
        });
    </script>
</body>
</html>