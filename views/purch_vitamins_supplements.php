<?php
// views/purch_vitamins_supplements.php
error_reporting(0);
ini_set('display_errors', 0);

$page="transactions";
include '../common/navbar.php';
include '../config/Connection.php';
include '../security/checkRole.php';    
checkRole(3);

// --- CONFIGURATION ---
$ITEM_TYPE_ID = 10; // Vitamins & Supplements
// ---------------------

try {
    if (!isset($conn)) {
        throw new Exception("Database connection failed.");
    }

    // 1. Fetch Items (Type 10)
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
    <title>Vitamin Purchase Management</title>
    <style>
        /* --- CORE STYLES --- */
        :root {
            --primary: #3b82f6; --primary-dark: #2563eb;
            --success: #10b981; --warning: #f59e0b; --danger: #ef4444;
            --dark: #0f172a; --dark-light: #1e293b;
            --gray: #64748b; --gray-light: #94a3b8;
            --border: rgba(148, 163, 184, 0.1);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: system-ui, -apple-system, sans-serif; background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); min-height: 100vh; color: #e2e8f0; }
        .container { max-width: 1400px; margin: 0 auto; padding: 2rem; }

        /* HEADER */
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; gap: 1rem; flex-wrap: wrap; }
        .header-info h1 { font-size: 2rem; font-weight: 700; background: linear-gradient(135deg, #60a5fa, #a78bfa); -webkit-background-clip: text; -webkit-text-fill-color: transparent; margin-bottom: 0.5rem; }
        .header-info p { color: var(--gray-light); font-size: 0.95rem; }
        .header-actions { display: flex; gap: 10px; align-items: center; }

        /* BUTTONS */
        .add-btn, .confirm-all-btn { display: flex; align-items: center; gap: 0.5rem; padding: 0.75rem 1.5rem; color: white; border: none; border-radius: 10px; font-weight: 600; cursor: pointer; transition: all 0.2s; }
        .add-btn { background: linear-gradient(135deg, var(--primary), var(--primary-dark)); box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3); }
        .add-btn:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(59, 130, 246, 0.4); }
        .confirm-all-btn { background: linear-gradient(135deg, #f59e0b, #d97706); box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3); }
        .confirm-all-btn:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(245, 158, 11, 0.4); }

        /* SEARCH */
        .search-container { position: relative; margin-bottom: 2rem; }
        .search-input { width: 100%; padding: 1rem 1rem 1rem 3rem; background: rgba(30, 41, 59, 0.5); border: 1px solid var(--border); border-radius: 12px; color: white; font-size: 1rem; }
        .search-icon { position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); width: 20px; color: var(--gray-light); }

        /* TABLE */
        .table-container { background: rgba(30, 41, 59, 0.5); border-radius: 16px; border: 1px solid var(--border); overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.2); overflow-x: auto; }
        .table { width: 100%; border-collapse: collapse; min-width: 1000px; }
        .table th { padding: 1.25rem 1rem; text-align: left; font-weight: 600; color: var(--gray-light); text-transform: uppercase; font-size: 0.85rem; background: rgba(15, 23, 42, 0.8); }
        .table td { padding: 1.25rem 1rem; border-bottom: 1px solid var(--border); color: #e2e8f0; font-size: 0.9rem; vertical-align: middle; }
        .table tr:hover { background: rgba(59, 130, 246, 0.05); }

        /* BADGES & TEXT */
        .item-id { font-weight: 600; color: var(--primary); font-size: 0.85rem; }
        .item-name { font-weight: 600; color: white; }
        .item-unit { color: var(--gray-light); font-size: 0.85rem; }
        .amount { font-weight: 700; color: #fbbf24; }
        .category-badge { padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; }
        .category-nonconsumable { background: rgba(59, 130, 246, 0.1); color: #60a5fa; border: 1px solid rgba(59, 130, 246, 0.2); }
        .category-consumable { background: rgba(16, 185, 129, 0.1); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.2); }
        
        .confirm-btn { background: rgba(16, 185, 129, 0.1); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.3); padding: 6px 12px; border-radius: 6px; font-weight: 600; cursor: pointer; transition: all 0.2s; font-size: 0.8rem; text-transform: uppercase; }
        .confirm-btn:hover { background: rgba(16, 185, 129, 0.2); transform: scale(1.05); }
        .confirmed-badge { color: var(--gray); font-size: 0.8rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; opacity: 0.7; }

        /* ACTIONS */
        .actions { display: flex; gap: 0.5rem; }
        .action-btn { width: 32px; height: 32px; border-radius: 8px; border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.2s; }
        .action-btn svg { width: 16px; height: 16px; }
        .action-btn.view { background: rgba(59, 130, 246, 0.1); color: #60a5fa; }
        .action-btn.edit { background: rgba(245, 158, 11, 0.1); color: #fbbf24; }
        .action-btn.delete { background: rgba(239, 68, 68, 0.1); color: #f87171; }
        .action-btn:hover { transform: translateY(-2px); filter: brightness(1.2); }

        /* MODAL */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.75); backdrop-filter: blur(4px); z-index: 1000; align-items: center; justify-content: center; padding: 1rem; }
        .modal.show { display: flex; }
        .modal-content { background: var(--dark-light); border-radius: 20px; width: 100%; max-width: 600px; max-height: 90vh; display: flex; flex-direction: column; border: 1px solid var(--border); box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); animation: slideUp 0.3s ease; }
        .modal-header { padding: 1.5rem 2rem; border-bottom: 1px solid var(--border); }
        .modal-header h2 { font-size: 1.25rem; font-weight: 700; color: white; margin: 0; }
        .modal-body { padding: 2rem; overflow-y: auto; }
        .modal-footer { padding: 1.5rem 2rem; border-top: 1px solid var(--border); display: flex; justify-content: flex-end; gap: 1rem; background: rgba(15, 23, 42, 0.3); border-radius: 0 0 20px 20px; }

        /* FORM */
        .info-group h3 { font-size: 1rem; color: white; margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 1px solid var(--border); }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        .form-row-cascading { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 1.5rem; background: rgba(255,255,255,0.02); padding: 15px; border-radius: 8px; border: 1px dashed var(--border); }
        
        .form-group { margin-bottom: 1.25rem; position: relative; }
        .form-group label { display: block; color: var(--gray-light); font-size: 0.85rem; font-weight: 600; margin-bottom: 0.5rem; text-transform: uppercase; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 0.75rem; background: rgba(15, 23, 42, 0.6); border: 1px solid var(--border); border-radius: 8px; color: white; font-size: 0.95rem; }
        .form-group input:focus, .form-group select:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1); }
        .form-group select:disabled { opacity: 0.5; cursor: not-allowed; }

        /* AUTOCOMPLETE */
        .autocomplete-list { position: absolute; z-index: 100; top: 100%; left: 0; right: 0; background: #1e293b; border: 1px solid var(--border); border-radius: 0 0 8px 8px; max-height: 200px; overflow-y: auto; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.3); display: none; }
        .autocomplete-list.show { display: block; }
        .autocomplete-item { padding: 10px 15px; cursor: pointer; border-bottom: 1px solid var(--border); color: #e2e8f0; }
        .autocomplete-item:hover { background: rgba(59, 130, 246, 0.1); }
        .autocomplete-item strong { color: var(--primary); }

        /* ALERTS & EMPTY STATE */
        .alert { padding: 1rem; border-radius: 8px; margin-bottom: 1rem; display: none; font-size: 0.9rem; }
        .alert.show { display: block; }
        .alert.success { background: rgba(16, 185, 129, 0.1); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.2); }
        .alert.error { background: rgba(239, 68, 68, 0.1); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.2); }
        
        .empty-state { display: none; text-align: center; padding: 3rem; color: var(--gray); }

        .btn-cancel { background: transparent; color: var(--gray-light); border: 1px solid var(--border); padding: 0.75rem 1.5rem; border-radius: 8px; cursor: pointer; }
        .btn-cancel:hover { color: white; border-color: var(--gray); }
        .btn-save { background: linear-gradient(135deg, var(--primary), var(--primary-dark)); color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 8px; font-weight: 600; cursor: pointer; }
        .btn-save:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3); }

        @media (max-width: 768px) { .form-row, .form-row-cascading { grid-template-columns: 1fr; } .header { flex-direction: column; align-items: stretch; } .header-actions { justify-content: space-between; } }
        @keyframes slideUp { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="header-info">
                <h1>Vitamins & Supplements Purchase</h1>
                <p>Manage and track supplement purchases</p>
            </div>
            
            <div class="header-actions">
                <button class="confirm-all-btn" onclick="openConfirmAllModal()">
                    <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width:20px;height:20px;">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Confirm All Pending
                </button>

                <button class="add-btn" onclick="openAddModal()">
                    <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width:20px;height:20px;">
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
            <input type="text" class="search-input" id="searchInput" placeholder="Search by name, unit, or category..." onkeyup="filterTable()">
        </div>

        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Item ID</th>
                        <th>Name</th>
                        <th>Qty</th>
                        <th>Unit</th>
                        <th>Cost</th>
                        <th>Total</th> 
                        <th>Category</th>
                        <th>Date</th>
                        <th style="text-align: center; width: 120px;">Status</th>
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
                        
                        <td><div class="item-id">VIT-<?php echo str_pad($item['ITEM_ID'], 4, '0', STR_PAD_LEFT); ?></div></td>
                        <td><div class="item-name"><?php echo htmlspecialchars($item['ITEM_NAME']); ?></div></td>
                        <td><div class="item-unit"><?php echo number_format($item['QUANTITY'] ?? 0, 2); ?></div></td>
                        <td><div class="item-unit"><?php echo htmlspecialchars($item['UNIT_NAME']); ?></div></td>
                        <td><div class="amount">₱<?php echo number_format($item['UNIT_COST'], 2); ?></div></td>
                        <td><div class="amount" style="color:#60a5fa;">₱<?php echo number_format($totalCost, 2); ?></div></td>

                        <td>
                            <span class="category-badge <?php echo $categoryClasses[$item['ITEM_CATEGORY']]; ?>">
                                <?php echo $categoryLabels[$item['ITEM_CATEGORY']]; ?>
                            </span>
                        </td>
                        <td><div class="item-unit"><?php echo htmlspecialchars($item['DATE_OF_PURCHASE'] ?? 'N/A'); ?></div></td>

                        <td style="text-align: center;">
                            <?php if(!$isConfirmed): ?>
                                <button class="confirm-btn" onclick="openConfirmModal(this)">Confirm</button>
                            <?php else: ?>
                                <div class="confirmed-badge">Locked</div>
                            <?php endif; ?>
                        </td>

                        <td>
                            <div class="actions">
                                <button class="action-btn view" onclick="viewItem(this)" title="View"><svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg></button>

                                <?php if(!$isConfirmed): ?>
                                    <button class="action-btn edit" onclick="editItem(this)" title="Edit"><svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg></button>
                                    <button class="action-btn delete" onclick="deleteItem(this)" title="Delete"><svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg></button>
                                <?php else: ?>
                                    <div style="width:70px;"></div>
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
                <h2 id="modal-title">Add Vitamin Purchase</h2>
            </div>
            <div class="modal-body">
                <div id="modal-alert" class="alert"></div>
                <form id="item-form" method="POST">
                    <input type="hidden" id="item-id" name="item_id">
                    <input type="hidden" name="item_type_id" value="<?php echo $ITEM_TYPE_ID; ?>">
                    
                    <div class="info-group">
                        <h3>Item Information</h3>
                        <div class="form-group autocomplete-wrapper">
                            <label for="item-name">Name <span>*</span></label>
                            <input type="text" id="item-name" name="item_name" placeholder="e.g., Multivitamins" required maxlength="300" autocomplete="off">
                            <div id="autocomplete-list" class="autocomplete-list"></div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="net-weight">Net Weight</label>
                                <input type="number" id="net-weight" name="item_net_weight" placeholder="e.g., 0.5" step="0.01" min="0">
                            </div>
                            <div class="form-group">
                                <label for="unit">Unit <span>*</span></label>
                                <select id="unit" name="unit_id" required>
                                    <option value="">Select Unit</option>
                                    <?php foreach($units as $unit): ?>
                                        <option value="<?php echo $unit['UNIT_ID']; ?>"><?php echo htmlspecialchars($unit['UNIT_NAME']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="item-quantity">Quantity <span>*</span></label>
                                <input type="number" id="item-quantity" name="item_quantity" placeholder="e.g., 100" step="0.01" min="0" required>
                            </div>
                            <div class="form-group">
                                <label for="unit-cost">Unit Cost (₱) <span>*</span></label>
                                <input type="number" id="unit-cost" name="unit_cost" placeholder="0.00" step="0.01" min="0" required>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="item-category">Category <span>*</span></label>
                                <select id="item-category" name="item_category" required>
                                    <option value="">Select Category</option>
                                    <option value="0">Non-Consumable</option>
                                    <option value="1">Consumable</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="purchase-date">Date <span>*</span></label>
                                <input type="date" id="purchase-date" name="date_of_purchase" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="item-desc">Description</label>
                            <textarea id="item-desc" name="item_description" placeholder="Enter detailed description" rows="2" maxlength="500"></textarea>
                        </div>
                    </div>

                    <div class="info-group">
                        <h3>Initial Location</h3>
                        <div class="form-row-cascading">
                            <div class="form-group">
                                <label for="location_id">Location</label>
                                <select id="location_id" name="location_id" onchange="filterBuildings()">
                                    <option value="">Select Location</option>
                                    <?php foreach($locations as $loc): ?>
                                        <option value="<?php echo $loc['LOCATION_ID']; ?>"><?php echo htmlspecialchars($loc['LOCATION_NAME']); ?></option>
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
            <div class="modal-header"><h2>Purchase Details</h2></div>
            <div class="modal-body" id="view-modal-body"></div>
            <div class="modal-footer"><button type="button" class="btn-cancel" onclick="closeViewModal()">Close</button></div>
        </div>
    </div>

    <div id="confirm-modal" class="modal">
        <div class="modal-content" style="max-width: 450px;">
            <div class="modal-body" style="text-align: center;">
                <h2 style="color:white; margin-bottom:10px;">Confirm Purchase?</h2>
                <p style="color:var(--gray-light); margin-bottom:15px;">Confirming <strong><span id="confirm-item-qty" style="color:var(--primary);"></span> <span id="confirm-item-name" style="color:var(--primary);"></span></strong>.</p>
                <p style="font-size:0.85rem; color:#f87171; background:rgba(239,68,68,0.1); padding:10px; border-radius:8px;">⚠️ Warning: This record will be locked.</p>
                <form id="confirmForm" method="POST"><input type="hidden" id="confirm_item_id" name="item_id"></form>
            </div>
            <div class="modal-footer" style="justify-content: center;">
                <button type="button" class="btn-cancel" onclick="closeConfirmModal()">Cancel</button>
                <button type="button" class="btn-save confirm-btn-action" onclick="submitConfirmation()">Yes, Confirm</button>
            </div>
        </div>
    </div>

    <div id="confirm-all-modal" class="modal">
        <div class="modal-content" style="max-width: 450px;">
            <div class="modal-body" style="text-align: center;">
                <h2 style="color:white; margin-bottom:10px;">Confirm All?</h2>
                <p style="color:var(--gray-light);">This will lock <strong>ALL</strong> pending purchases.</p>
                <p style="font-size:0.85rem; color:#f87171; background:rgba(239,68,68,0.1); padding:10px; border-radius:8px; margin-top:15px;">⚠️ Warning: Cannot be undone.</p>
            </div>
            <div class="modal-footer" style="justify-content: center;">
                <button type="button" class="btn-cancel" onclick="closeConfirmAllModal()">Cancel</button>
                <button type="button" class="btn-save confirm-all-action" onclick="submitConfirmAll()">Confirm All</button>
            </div>
        </div>
    </div>

    <form id="deleteItemForm" method="POST" style="display: none;"><input type="hidden" id="delete_item_id" name="item_id"></form>

    <script>
        const allBuildings = <?php echo json_encode($buildings_raw); ?>;
        const allPens = <?php echo json_encode($pens_raw); ?>;

        function filterBuildings() {
            const locId = document.getElementById('location_id').value;
            const bldgSel = document.getElementById('building_id');
            const penSel = document.getElementById('pen_id');
            
            bldgSel.innerHTML = '<option value="">Select Building</option>';
            penSel.innerHTML = '<option value="">Select Building First</option>';
            penSel.disabled = true;

            if (locId) {
                bldgSel.disabled = false;
                const filtered = allBuildings.filter(b => b.LOCATION_ID == locId);
                filtered.forEach(b => {
                    const opt = document.createElement('option');
                    opt.value = b.BUILDING_ID; opt.textContent = b.BUILDING_NAME;
                    bldgSel.appendChild(opt);
                });
            } else { bldgSel.disabled = true; }
        }

        function filterPens() {
            const bldgId = document.getElementById('building_id').value;
            const penSel = document.getElementById('pen_id');
            penSel.innerHTML = '<option value="">Select Pen</option>';

            if (bldgId) {
                penSel.disabled = false;
                const filtered = allPens.filter(p => p.BUILDING_ID == bldgId);
                filtered.forEach(p => {
                    const opt = document.createElement('option');
                    opt.value = p.PEN_ID; opt.textContent = p.PEN_NAME;
                    penSel.appendChild(opt);
                });
            } else { penSel.disabled = true; }
        }

        // --- Autocomplete ---
        let autocompleteTimeout = null;
        function initAutocomplete() {
            const input = document.getElementById('item-name');
            const list = document.getElementById('autocomplete-list');
            
            const newInp = input.cloneNode(true);
            input.parentNode.replaceChild(newInp, input);
            
            newInp.addEventListener('input', function() {
                const val = this.value.trim();
                clearTimeout(autocompleteTimeout);
                if(val.length < 2) { list.classList.remove('show'); return; }
                autocompleteTimeout = setTimeout(() => {
                    fetch(`../process/searchVitaminsAndSupplements.php?term=${encodeURIComponent(val)}`)
                    .then(r => r.json()).then(d => displayAutocomplete(d, val));
                }, 300);
            });
            
            document.addEventListener('click', e => {
                if(!e.target.closest('.autocomplete-wrapper')) list.classList.remove('show');
            });
        }

        function displayAutocomplete(data, term) {
            const list = document.getElementById('autocomplete-list');
            list.innerHTML = '';
            if(data.length === 0) return;
            
            data.forEach(item => {
                const div = document.createElement('div');
                div.className = 'autocomplete-item';
                div.innerHTML = item.replace(new RegExp(`(${term})`, 'gi'), '<strong>$1</strong>');
                div.onclick = () => {
                    document.getElementById('item-name').value = item;
                    list.classList.remove('show');
                };
                list.appendChild(div);
            });
            list.classList.add('show');
        }

        // --- Modals ---
        function openAddModal() {
            document.getElementById('item-form').reset();
            document.getElementById('item-id').value = '';
            document.getElementById('modal-title').textContent = 'Add Vitamin Purchase';
            document.getElementById('btn-save').textContent = 'Save Purchase';
            document.getElementById('purchase-date').value = new Date().toISOString().split('T')[0];
            
            document.getElementById('location_id').value = "";
            document.getElementById('building_id').innerHTML = '<option value="">Select Location First</option>';
            document.getElementById('building_id').disabled = true;
            document.getElementById('pen_id').innerHTML = '<option value="">Select Building First</option>';
            document.getElementById('pen_id').disabled = true;

            document.getElementById('modal-alert').style.display = 'none';
            document.getElementById('modal').classList.add('show');
            setTimeout(initAutocomplete, 100);
        }

        function editItem(btn) {
            const d = btn.closest('tr').dataset;
            document.getElementById('item-id').value = d.itemId;
            document.getElementById('item-name').value = d.itemName;
            document.getElementById('item-desc').value = d.itemDesc;
            document.getElementById('unit').value = d.unitId;
            document.getElementById('unit-cost').value = d.unitCost;
            document.getElementById('item-category').value = d.itemCategory;
            document.getElementById('net-weight').value = d.netWeight;
            document.getElementById('item-quantity').value = d.quantity;
            document.getElementById('purchase-date').value = d.purchaseDate;

            const loc = document.getElementById('location_id');
            loc.value = d.locationId || "";
            filterBuildings();
            
            const bldg = document.getElementById('building_id');
            if(d.buildingId) {
                bldg.value = d.buildingId;
                filterPens();
                if(d.penId) document.getElementById('pen_id').value = d.penId;
            }

            document.getElementById('modal-title').textContent = 'Edit Vitamin Purchase';
            document.getElementById('btn-save').textContent = 'Update Purchase';
            document.getElementById('modal-alert').style.display = 'none';
            document.getElementById('modal').classList.add('show');
            setTimeout(initAutocomplete, 100);
        }

        function saveItem() {
            const form = document.getElementById('item-form');
            if (!form.checkValidity()) { form.reportValidity(); return; }
            
            const id = document.getElementById('item-id').value;
            const url = id ? '../process/editVitaminsAndSupplements.php' : '../process/addVitaminsAndSupplements.php';
            const btn = document.getElementById('btn-save');
            
            btn.disabled = true; btn.innerHTML = 'Saving...';
            
            fetch(url, { method: 'POST', body: new FormData(form) })
            .then(r => r.json()).then(data => {
                const alert = document.getElementById('modal-alert');
                alert.textContent = data.message;
                alert.className = `alert show ${data.success ? 'success' : 'error'}`;
                alert.style.display = 'block';
                
                if(data.success) { setTimeout(() => { location.reload(); }, 1000); }
                else { btn.disabled = false; btn.textContent = id ? 'Update Purchase' : 'Save Purchase'; }
            });
        }

        function deleteItem(btn) {
            if(!confirm("Delete this purchase record?")) return;
            const id = btn.closest('tr').dataset.itemId;
            const fd = new FormData(); fd.append('item_id', id);
            
            fetch('../process/deleteVitaminPurchase.php', { method:'POST', body:fd })
            .then(r => r.json()).then(data => {
                alert(data.message);
                if(data.success) btn.closest('tr').remove();
            });
        }

        // --- Confirmation ---
        function openConfirmModal(btn) {
            const row = btn.closest('tr');
            document.getElementById('confirm_item_id').value = row.dataset.itemId;
            document.getElementById('confirm-item-name').textContent = row.dataset.itemName;
            document.getElementById('confirm-item-qty').textContent = row.dataset.quantity;
            document.getElementById('confirm-modal').classList.add('show');
        }

        function submitConfirmation() {
            const btn = document.querySelector('.confirm-btn-action');
            btn.disabled = true; btn.innerHTML = 'Processing...';
            
            fetch('../purchase_confirmations/confirmVitaminsAndSupplements.php', { method:'POST', body: new FormData(document.getElementById('confirmForm')) })
            .then(r => r.json()).then(d => {
                alert(d.message);
                if(d.success) location.reload();
                else { btn.disabled = false; btn.innerHTML = 'Yes, Confirm'; }
            });
        }

        function openConfirmAllModal() { document.getElementById('confirm-all-modal').classList.add('show'); }
        
        function submitConfirmAll() {
            const btn = document.querySelector('.confirm-all-action');
            btn.disabled = true; btn.innerHTML = 'Processing...';
            
            fetch('../purchase_confirmations/confirmAllVitaminsAndSupplements.php', { method:'POST' })
            .then(r => r.json()).then(d => {
                alert(d.message);
                if(d.success) location.reload();
                else { btn.disabled = false; btn.innerHTML = 'Confirm All'; }
            });
        }

        // --- Utils ---
        function closeModal() { document.getElementById('modal').classList.remove('show'); }
        function closeViewModal() { document.getElementById('view-modal').classList.remove('show'); }
        function closeConfirmModal() { document.getElementById('confirm-modal').classList.remove('show'); }
        function closeConfirmAllModal() { document.getElementById('confirm-all-modal').classList.remove('show'); }

        function filterTable() {
            const term = document.getElementById('searchInput').value.toLowerCase();
            const rows = document.querySelectorAll('#item-table tr');
            let visible = 0;
            rows.forEach(r => {
                const txt = r.innerText.toLowerCase();
                r.style.display = txt.includes(term) ? '' : 'none';
                if(r.style.display !== 'none') visible++;
            });
            document.getElementById('empty-state').style.display = visible ? 'none' : 'block';
        }

        function viewItem(btn) {
            const d = btn.closest('tr').dataset;
            const html = `
                <div class="info-group">
                    <h3>Basic Info</h3>
                    <p><strong>Item:</strong> ${d.itemName}</p>
                    <p><strong>Desc:</strong> ${d.itemDesc || '-'}</p>
                </div>
                <div class="info-group">
                    <h3>Purchase Info</h3>
                    <p><strong>Qty:</strong> ${d.quantity} ${d.unitName}</p>
                    <p><strong>Cost:</strong> ₱${d.unitCost} / unit</p>
                    <p><strong>Total:</strong> ₱${(d.quantity * d.unitCost).toFixed(2)}</p>
                    <p><strong>Date:</strong> ${d.purchaseDate}</p>
                </div>`;
            document.getElementById('view-modal-body').innerHTML = html;
            document.getElementById('view-modal').classList.add('show');
        }

        document.addEventListener('DOMContentLoaded', () => checkEmptyState());
        function checkEmptyState() {
            const count = document.querySelectorAll('#item-table tr').length;
            document.getElementById('empty-state').style.display = count ? 'none' : 'block';
        }
    </script>
</body>
</html>