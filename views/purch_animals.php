<?php
// views/purch_animals.php
error_reporting(0);
ini_set('display_errors', 0);
include '../config/Connection.php';

include '../security/checkAccess.php';
checkAccess('purchases');
$page="transactions";
include '../common/navbar.php';
include '../common/chat_support.php';
include '../functions/getUsersLocation.php';

// --- CONFIGURATION ---
$ANIMAL_ITEM_TYPE_ID = 13; 
// ---------------------

try {
    if (!isset($conn)) {
        throw new Exception("Database connection failed.");
    }

    $items_sql = "";

    // 1. Fetch Items
    if($USER_LOCATION_ != 1000) {
        $items_sql = "SELECT i.*, 
                  it.ITEM_TYPE_NAME,
                  u.UNIT_NAME,
                  DATE_FORMAT(i.DATE_OF_PURCHASE, '%m/%d/%Y') as DATE_OF_PURCHASE_FMT,
                  DATE_FORMAT(i.CREATED_AT, '%m/%d/%Y %h:%i %p') as CREATED_AT_FMT
                  FROM ITEMS i
                  LEFT JOIN ITEM_TYPES it ON i.ITEM_TYPE_ID = it.ITEM_TYPE_ID
                  LEFT JOIN UNITS u ON i.UNIT_ID = u.UNIT_ID
                  WHERE i.ITEM_TYPE_ID = :type_id AND LOCATION_ID = :location_id
                  ORDER BY i.CREATED_AT DESC";

        $stmt = $conn->prepare($items_sql);
        $stmt->execute([':type_id' => $ANIMAL_ITEM_TYPE_ID, ':location_id' => $USER_LOCATION_]);
        $items_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 2. STRICT UNIT LOGIC
        $unit_query = "SELECT * FROM UNITS 
                       WHERE UPPER(UNIT_NAME) IN ('PCS', 'PIECES', 'PC', 'HEADS', 'HEAD') 
                       LIMIT 1";
        $stmt = $conn->prepare($unit_query);
        $stmt->execute();
        $default_unit_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $default_unit_id = $default_unit_data[0]['UNIT_ID'] ?? ''; 
        $default_unit_name = $default_unit_data[0]['UNIT_NAME'] ?? 'Pcs';

        // 3. Location Hierarchy
        $loc_sql = "SELECT * FROM LOCATIONS WHERE LOCATION_ID = :location_id ORDER BY LOCATION_NAME ASC";
        $stmt = $conn->prepare($loc_sql);
        $stmt->execute([':location_id' => $USER_LOCATION_]);
        $locations = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $bldg_sql = "SELECT * FROM BUILDINGS ORDER BY BUILDING_NAME ASC";
        $stmt = $conn->prepare($bldg_sql);
        $stmt->execute();
        $buildings_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $pens_sql = "SELECT * FROM PENS ORDER BY PEN_NAME ASC";
        $stmt = $conn->prepare($pens_sql);
        $stmt->execute();
        $pens_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    }
    else
    {
        $items_sql = "SELECT i.*, 
                it.ITEM_TYPE_NAME,
                u.UNIT_NAME,
                DATE_FORMAT(i.DATE_OF_PURCHASE, '%m/%d/%Y') as DATE_OF_PURCHASE_FMT,
                DATE_FORMAT(i.CREATED_AT, '%m/%d/%Y %h:%i %p') as CREATED_AT_FMT
                FROM ITEMS i
                LEFT JOIN ITEM_TYPES it ON i.ITEM_TYPE_ID = it.ITEM_TYPE_ID
                LEFT JOIN UNITS u ON i.UNIT_ID = u.UNIT_ID
                WHERE i.ITEM_TYPE_ID = :type_id
                ORDER BY i.CREATED_AT DESC";

        $stmt = $conn->prepare($items_sql);
        $stmt->execute([':type_id' => $ANIMAL_ITEM_TYPE_ID]);
        $items_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 2. STRICT UNIT LOGIC
        $unit_query = "SELECT * FROM UNITS 
                       WHERE UPPER(UNIT_NAME) IN ('PCS', 'PIECES', 'PC', 'HEADS', 'HEAD') 
                       LIMIT 1";
        $stmt = $conn->prepare($unit_query);
        $stmt->execute();
        $default_unit_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $default_unit_id = $default_unit_data[0]['UNIT_ID'] ?? ''; 
        $default_unit_name = $default_unit_data[0]['UNIT_NAME'] ?? 'Pcs';

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
    }

} catch (Exception $e) {
    $items_data = [];
    $locations = [];
    $buildings_raw = [];
    $pens_raw = [];
    $default_unit_id = '';
    $default_unit_name = '';
    echo "<script>console.error('Database Error: " . addslashes($e->getMessage()) . "');</script>";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Animal Purchases Management</title>
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" type="text/css" href="https://npmcdn.com/flatpickr/dist/themes/dark.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    <style>
        /* --- CORE STYLES --- */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); min-height: 100vh; color: white; padding-bottom: 80px; }
        .container { max-width: 1400px; margin: 0 auto; padding: 2rem; width: 100%; }

        /* HEADER & BACK BUTTON */
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1.5rem; }
        .header-info h1 { font-size: clamp(1.8rem, 4vw, 2.5rem); font-weight: bold; margin-bottom: 0.5rem; line-height: 1.2; }
        .header-info p { color: #cbd5e1; font-size: 0.95rem; }

        .back-link { display: inline-flex; align-items: center; gap: 8px; text-decoration: none; color: #94a3b8; font-weight: 600; font-size: 0.95rem; margin-bottom: 10px; transition: color 0.2s; }
        .back-link:hover { color: white; }

        .header-actions { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; }

        /* BUTTONS */
        .btn-base { display: flex; align-items: center; justify-content: center; gap: 8px; border: none; padding: 12px 24px; border-radius: 8px; font-weight: 600; font-size: 0.95rem; cursor: pointer; transition: all 0.2s; box-shadow: 0 4px 6px rgba(0,0,0,0.1); white-space: nowrap; }
        .add-btn { background: linear-gradient(135deg, #2563eb, #9333ea); color: white; }
        .add-btn:hover { background: linear-gradient(135deg, #1d4ed8, #7c3aed); transform: translateY(-2px); }

        .confirm-all-btn { background: linear-gradient(135deg, #f59e0b, #d97706); color: white; border: none; padding: 12px 24px; border-radius: 8px; font-weight: 600; font-size: 0.95rem; cursor: pointer; transition: all 0.2s; box-shadow: 0 4px 6px rgba(245, 158, 11, 0.3); display: flex; align-items: center; gap: 8px; }
        .confirm-all-btn:hover { background: linear-gradient(135deg, #d97706, #b45309); transform: translateY(-1px); box-shadow: 0 6px 8px rgba(245, 158, 11, 0.4); }

        /* SEARCH & TABLE */
        .search-container { position: relative; margin-bottom: 2rem; }
        .search-input { width: 100%; padding: 14px 14px 14px 45px; background: rgba(30, 41, 59, 0.5); border: 1px solid #475569; border-radius: 8px; color: white; font-size: 1rem; backdrop-filter: blur(10px); outline: none; transition: border-color 0.2s; }
        .search-input:focus { border-color: #3b82f6; }
        .search-icon { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #94a3b8; width: 20px; height: 20px; }
        
        .table-container { background: rgba(30, 41, 59, 0.5); border-radius: 12px; border: 1px solid #475569; overflow: hidden; }
        .table { width: 100%; border-collapse: collapse; min-width: 1100px; }
        .table th { padding: 1rem 1.5rem; text-align: left; font-size: 0.85rem; font-weight: 600; color: #e2e8f0; text-transform: uppercase; background: rgba(15, 23, 42, 0.5); border-bottom: 1px solid #475569; white-space: nowrap; }
        .table td { padding: 1rem 1.5rem; vertical-align: middle; color: #cbd5e1; border-bottom: 1px solid rgba(255,255,255,0.05); }
        .table tbody tr:hover { background: rgba(255, 255, 255, 0.02); }

        .ref-no { font-family: monospace; color: #93c5fd; font-size: 0.95rem; font-weight: 600; white-space: nowrap; }
        .supplier-name { color: #f1f5f9; font-size: 0.95rem; font-weight: 500; white-space: nowrap; }
        .item-name { font-weight: 600; color: #fff; font-size: 1rem; margin-bottom: 4px; white-space: nowrap; }
        .amount { color: #34d399; font-weight: 600; font-family: monospace; font-size: 1.1rem; white-space: nowrap; }
        
        .confirmed-badge { display: inline-block; width: 100%; text-align: center; padding: 8px 0; background: rgba(16, 185, 129, 0.1); color: #059669; border: 1px solid rgba(16, 185, 129, 0.2); border-radius: 6px; font-weight: 700; font-size: 0.85rem; text-transform: uppercase; white-space: nowrap;}
        .confirm-btn { background: linear-gradient(135deg, #ef4444, #dc2626); color: white; border: none; padding: 8px 16px; border-radius: 6px; font-weight: 600; font-size: 0.85rem; cursor: pointer; transition: all 0.2s; width: 100%; text-transform: uppercase; letter-spacing: 0.5px; white-space: nowrap;}
        .confirm-btn:hover { background: linear-gradient(135deg, #dc2626, #b91c1c); transform: translateY(-1px); }

        .actions { display: flex; gap: 8px; justify-content: center; }
        .action-btn { background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); color: #cbd5e1; padding: 8px; border-radius: 6px; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; justify-content: center; }
        .action-btn:hover { background: rgba(255,255,255,0.1); color: white; }
        .action-btn.view:hover { background: rgba(59, 130, 246, 0.2); color: #60a5fa; border-color: #3b82f6; }
        .action-btn.edit:hover { background: rgba(16, 185, 129, 0.2); color: #34d399; border-color: #10b981; }
        .action-btn.delete:hover { background: rgba(239, 68, 68, 0.2); color: #f87171; border-color: #ef4444; }
        .icon { width: 18px; height: 18px; }

        /* MODAL */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.7); backdrop-filter: blur(4px); z-index: 1000; align-items: center; justify-content: center; padding: 1rem; }
        .modal.show { display: flex; }
        .modal-content { background: #1e293b; border-radius: 16px; width: 100%; max-width: 700px; border: 1px solid #475569; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5); display: flex; flex-direction: column; max-height: 90vh; }
        .modal-header { padding: 1.5rem; border-bottom: 1px solid #334155; }
        .modal-header h2 { margin: 0; font-size: 1.4rem; color: #fff; }
        .modal-body { padding: 1.5rem; overflow-y: auto; flex-grow: 1; }
        .modal-footer { padding: 1.5rem; border-top: 1px solid #334155; display: flex; justify-content: flex-end; gap: 10px; }

        /* FORM ELEMENTS */
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px; }
        .form-group { margin-bottom: 15px; display: flex; flex-direction: column; }
        .form-group label { display: block; color: #94a3b8; font-size: 0.85rem; margin-bottom: 8px; font-weight: 500; }
        .form-group label span { color: #ef4444; }
        
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 12px; background: #0f172a; border: 1px solid #334155; border-radius: 8px; color: white; font-size: 0.95rem; transition: border-color 0.2s; outline: none; }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1); }
        
        select[disabled], input[disabled], input[readonly] { opacity: 0.6; cursor: not-allowed; background: #1e293b; color: #94a3b8; }

        .info-group h3 { color: #93c5fd; font-size: 1.1rem; margin-bottom: 15px; margin-top: 20px; border-bottom: 1px solid #334155; padding-bottom: 5px; }
        
        .alert { padding: 12px; border-radius: 6px; margin-bottom: 15px; display: none; text-align: center; font-weight: 600; }
        .alert.success { background: rgba(16, 185, 129, 0.2); color: #34d399; border: 1px solid #10b981; }
        .alert.error { background: rgba(239, 68, 68, 0.2); color: #f87171; border: 1px solid #ef4444; }

        .btn-cancel { padding: 12px 20px; background: transparent; border: 1px solid #475569; color: #cbd5e1; border-radius: 8px; cursor: pointer; font-weight: 600; transition: all 0.2s; }
        .btn-cancel:hover { background: rgba(255,255,255,0.05); color: white; }
        .btn-save { padding: 12px 20px; background: #2563eb; border: none; color: white; border-radius: 8px; cursor: pointer; font-weight: 600; transition: background 0.2s; }
        .btn-save:hover { background: #1d4ed8; }

        /* Dynamic Animal Rows */
        .dynamic-row { display: flex; gap: 10px; align-items: flex-start; background: rgba(15, 23, 42, 0.5); padding: 15px; border-radius: 8px; border: 1px solid #334155; margin-bottom: 10px; }
        .dynamic-row .form-group { margin-bottom: 0; flex: 1; }
        .btn-remove-row { background: transparent; color: #f87171; border: 1px solid rgba(239,68,68,0.3); border-radius: 8px; cursor: pointer; padding: 12px; font-weight: bold; margin-top: 25px; transition: 0.2s; }
        .btn-remove-row:hover { background: rgba(239,68,68,0.1); border-color: #ef4444; }

        /* Autocomplete Styles */
        .autocomplete-wrapper { position: relative; }
        .autocomplete-list { position: absolute; z-index: 1000; top: 100%; left: 0; right: 0; background: #1e293b; border: 1px solid #475569; border-top: none; border-radius: 0 0 8px 8px; max-height: 200px; overflow-y: auto; box-shadow: 0 10px 15px rgba(0, 0, 0, 0.5); display: none; }
        .autocomplete-list.show { display: block; }
        .autocomplete-item { padding: 12px 15px; cursor: pointer; transition: background-color 0.2s; border-bottom: 1px solid #334155; color: #e2e8f0; }
        .autocomplete-item:last-child { border-bottom: none; }
        .autocomplete-item:hover, .autocomplete-item.active { background-color: #334155; }
        .autocomplete-item strong { color: #60a5fa; }
        .autocomplete-loading, .autocomplete-no-results { padding: 12px 15px; text-align: center; color: #94a3b8; font-size: 14px; }

        /* Modal Specifics */
        .confirm-content { text-align: center; padding: 20px; }
        .confirm-icon { font-size: 4rem; margin-bottom: 15px; display: block; }
        .warning-text { color: #f87171; font-size: 0.9rem; margin: 15px 0 25px 0; background: rgba(239, 68, 68, 0.1); padding: 12px; border-radius: 8px; border: 1px solid rgba(239, 68, 68, 0.2); }

        /* ========================================= */
        /* MOBILE RESPONSIVENESS OVERRIDES           */
        /* ========================================= */
        @media (max-width: 900px) {
            .container { padding: 1rem; }
            .header { flex-direction: column; align-items: flex-start; gap: 1rem; }
            .header-info { text-align: left; }
            .header-actions { flex-direction: column; width: 100%; gap: 10px; }
            .btn-base, .confirm-all-btn { width: 100%; justify-content: center; }

            /* Modal Adjustments */
            .form-row { grid-template-columns: 1fr; gap: 0; }
            .modal-footer { flex-direction: column; }
            .modal-footer button { width: 100%; margin-left: 0; }
            
            .dynamic-row { flex-direction: column; }
            .btn-remove-row { margin-top: 0; width: 100%; }

            /* Table to Card Layout */
            .table-container { border: none; background: transparent; overflow: visible; }
            .table { min-width: 0; display: block; }
            .table thead { display: none; }
            .table tbody { display: block; width: 100%; }
            .table tr { 
                display: block; 
                background: rgba(30, 41, 59, 0.6); 
                border: 1px solid #475569; 
                border-radius: 12px; 
                margin-bottom: 1rem; 
                padding: 1rem; 
            }
            .table td { 
                display: flex; 
                justify-content: space-between; 
                align-items: center; 
                padding: 0.75rem 0; 
                border-bottom: 1px dashed rgba(255,255,255,0.1); 
                text-align: right; 
                font-size: 0.95rem;
                white-space: normal;
            }
            .table td:last-child { border-bottom: none; }
            
            /* Inject Labels into Cards */
            .table td::before { 
                content: attr(data-label); 
                font-weight: 700; 
                color: #94a3b8; 
                font-size: 0.8rem; 
                text-transform: uppercase; 
                margin-right: 1rem; 
                text-align: left;
                flex-shrink: 0;
            }

            .actions { justify-content: flex-end; width: 100%; }
            .item-name, .supplier-name { margin-bottom: 0; text-align: right; }
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="purchase_dashboard.php" class="back-link">
            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            Back to Purchase Dashboard
        </a>

        <div class="header">
            <div class="header-info">
                <h1>Animal Purchases</h1>
                <p>Manage and track livestock and animal purchases</p>
            </div>
            
            <div class="header-actions">
                <button class="confirm-all-btn" onclick="openConfirmAllModal()">
                    <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width:20px; height:20px;">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Confirm All Pending
                </button>

                <button class="add-btn btn-base" onclick="openAddModal()">
                    <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                    </svg>
                    Add Animal Purchase
                </button>
            </div>
        </div>

        <div class="search-container">
            <svg class="search-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width:20px; height:20px;">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m21 21-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
            </svg>
            <input type="text" class="search-input" id="searchInput" placeholder="Search by animal type or breed..." onkeyup="filterTable()">
        </div>

        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Ref No</th>
                        <th>Supplier</th>
                        <th>Animal</th>
                        <th>Quantity</th>
                        <th>Cost per Head</th>
                        <th>Purchase Date</th>
                        <th style="text-align: center; width: 150px;">Confirmation</th>
                        <th style="text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody id="item-table">
                    <?php if(empty($items_data)): ?>
                        <tr>
                            <td colspan="8" style="text-align:center; padding: 3rem; color:#94a3b8;">No animal purchases recorded yet.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach($items_data as $item): 
                            $status = isset($item['STATUS']) ? (int)$item['STATUS'] : 0;
                            $isConfirmed = ($status === 1);
                        ?>
                        <tr data-item-id="<?php echo $item['ITEM_ID']; ?>"
                            data-item-name="<?php echo htmlspecialchars($item['ITEM_NAME']); ?>"
                            data-item-desc="<?php echo htmlspecialchars($item['ITEM_DESCRIPTION'] ?? ''); ?>"
                            data-unit-id="<?php echo $item['UNIT_ID']; ?>"
                            data-unit-cost="<?php echo $item['UNIT_COST']; ?>"
                            data-unit-name="<?php echo htmlspecialchars($item['UNIT_NAME']); ?>"
                            data-quantity="<?php echo $item['QUANTITY'] ?? '1'; ?>"
                            data-weight="<?php echo $item['ITEM_NET_WEIGHT'] ?? '0'; ?>"
                            data-purchase-date-raw="<?php echo htmlspecialchars($item['DATE_OF_PURCHASE'] ?? ''); ?>"
                            data-purchase-date-fmt="<?php echo htmlspecialchars($item['DATE_OF_PURCHASE_FMT'] ?? ''); ?>"
                            data-location-id="<?php echo $item['LOCATION_ID'] ?? ''; ?>"
                            data-building-id="<?php echo $item['BUILDING_ID'] ?? ''; ?>"
                            data-pen-id="<?php echo $item['PEN_ID'] ?? ''; ?>"
                            data-supplier="<?php echo htmlspecialchars($item['SUPPLIER'] ?? ''); ?>"
                            data-reference-no="<?php echo htmlspecialchars($item['REFERENCE_NO'] ?? ''); ?>"
                            data-created-at="<?php echo htmlspecialchars($item['CREATED_AT_FMT'] ?? ''); ?>">
                            
                            <td data-label="Ref No">
                                <div class="ref-no"><?php echo !empty($item['REFERENCE_NO']) ? htmlspecialchars($item['REFERENCE_NO']) : '—'; ?></div>
                            </td>
                            <td data-label="Supplier">
                                <div class="supplier-name"><?php echo !empty($item['SUPPLIER']) ? htmlspecialchars($item['SUPPLIER']) : 'General Supplier'; ?></div>
                            </td>
                            <td data-label="Animal">
                                <div class="item-name"><?php echo htmlspecialchars($item['ITEM_NAME']); ?></div>
                            </td>
                            <td data-label="Quantity">
                                <div style="white-space: nowrap;">
                                    <?php echo number_format($item['QUANTITY'] ?? 1, 0); ?> 
                                    <small style="color:#94a3b8"><?php echo htmlspecialchars($item['UNIT_NAME']); ?></small>
                                </div>
                            </td>
                            <td data-label="Cost per Head">
                                <div class="amount">₱<?php echo number_format($item['UNIT_COST'], 2); ?></div>
                            </td>
                            <td data-label="Purchase Date">
                                <div style="color:#cbd5e1; white-space: nowrap;"><?php echo htmlspecialchars($item['DATE_OF_PURCHASE_FMT'] ?? 'N/A'); ?></div>
                            </td>
                            <td data-label="Confirmation" style="text-align: center;">
                                <?php if(!$isConfirmed): ?>
                                    <button class="confirm-btn" onclick="openConfirmModal(this)">Confirm</button>
                                <?php else: ?>
                                    <div class="confirmed-badge">Confirmed</div>
                                <?php endif; ?>
                            </td>
                            <td data-label="Actions">
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
                    <?php endif; ?>
                </tbody>
            </table>
            <div id="empty-state" style="text-align:center; padding:3rem; display:none; color:#94a3b8;">
                No purchases found matching your search.
            </div>
        </div>
    </div>

    <div id="modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modal-title">Add Animal Purchase</h2>
            </div>
            <div class="modal-body">
                <div id="modal-alert" class="alert"></div>
                <form id="item-form" method="POST">
                    <input type="hidden" id="item-id" name="item_id">
                    <input type="hidden" name="item_type_id" value="<?php echo $ANIMAL_ITEM_TYPE_ID; ?>">
                    <input type="hidden" name="unit_id" value="<?php echo $default_unit_id; ?>">
                    
                    <div class="info-group" style="margin-top: 0;">
                        <h3 style="margin-top:0;">Batch Details</h3>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Date of Purchase <span>*</span></label>
                                <input type="text" id="purchase-date" name="date_of_purchase" class="form-input date-picker" placeholder="mm/dd/yyyy" required>
                            </div>
                            <div class="form-group">
                                <label>Location</label>
                                <select id="location_id" name="location_id" onchange="filterBuildings()" <?php echo ($USER_LOCATION_ != 1000) ? 'style="background-color: #1e293b; pointer-events: none; color: #94a3b8;"' : ''; ?> required>
                                    <?php if($USER_LOCATION_ == 1000): ?>
                                        <option value="">Select Location</option>
                                    <?php endif; ?>
                                    <?php foreach($locations as $loc): ?>
                                        <option value="<?php echo $loc['LOCATION_ID']; ?>" <?php echo ($USER_LOCATION_ != 1000 && $loc['LOCATION_ID'] == $USER_LOCATION_) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($loc['LOCATION_NAME']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Building</label>
                                <select id="building_id" name="building_id" onchange="filterPens()" disabled>
                                    <option value="">Select Location First</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Pen</label>
                                <select id="pen_id" name="pen_id" disabled>
                                    <option value="">Select Building First</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group autocomplete-wrapper">
                                <label>Supplier</label>
                                <input type="text" id="supplier" name="supplier" placeholder="e.g., ABC Farm" autocomplete="off">
                                <div id="supplier-autocomplete-list" class="autocomplete-list"></div>
                            </div>
                            <div class="form-group">
                                <label>Reference No.</label>
                                <input type="text" id="reference-no" name="reference_no" placeholder="e.g., OR-12345">
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Description / Remarks</label>
                            <textarea id="item-desc" name="item_description" placeholder="Enter batch details..." rows="2" maxlength="500"></textarea>
                        </div>
                    </div>

                    <div class="info-group">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 10px; border-bottom: 1px solid #334155; padding-bottom:5px;">
                            <h3 style="margin:0; border:none; padding:0;">Animals</h3>
                            <button type="button" id="btnAddAnimal" class="btn-base" style="padding: 6px 12px; background: rgba(59, 130, 246, 0.2); color: #60a5fa;" onclick="addAnimalRow()">+ Add Animal</button>
                        </div>
                        
                        <div id="dynamic-animal-container"></div>
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
            <div class="modal-footer"><button type="button" class="btn-cancel" onclick="closeViewModal()" style="width:100%">Close</button></div>
        </div>
    </div>

    <div id="confirm-modal" class="modal">
        <div class="modal-content" style="max-width: 450px;">
            <div class="modal-body confirm-content">
                <span class="confirm-icon">🐖</span>
                <h2 style="color: #fff; margin-bottom: 10px;">Confirm Purchase?</h2>
                <p style="color: #94a3b8; margin-bottom: 5px;">You are about to confirm <strong><span id="confirm-item-qty"></span> <span id="confirm-item-name" style="color:#38bdf8;"></span></strong>.</p>
                <div class="warning-text">⚠️ <strong>Warning:</strong> Once confirmed, this record will be locked and can no longer be edited or deleted.</div>
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
                <h2 style="color: #fff; margin-bottom: 10px;">Confirm All Pending?</h2>
                <p style="color: #94a3b8;">This will confirm and lock <strong>ALL</strong> currently pending animal purchases.</p>
                <div class="warning-text">⚠️ <strong>Warning:</strong> This action cannot be undone. Please review all pending items before proceeding.</div>
            </div>
            <div class="modal-footer" style="justify-content: center; border-top: none; padding-top: 0; padding-bottom: 30px;">
                <button type="button" class="btn-cancel" onclick="closeConfirmAllModal()">Cancel</button>
                <button type="button" class="btn-save" onclick="submitConfirmAll()" style="background: linear-gradient(135deg, #f59e0b, #d97706);">Confirm All</button>
            </div>
        </div>
    </div>

    <form id="deleteItemForm" method="POST" action="../process/deleteAnimalPurchase.php" style="display: none;">
        <input type="hidden" id="delete_item_id" name="item_id">
    </form>

    <script>
        const allBuildings = <?php echo json_encode($buildings_raw); ?>;
        const allPens = <?php echo json_encode($pens_raw); ?>;
        const USER_LOCATION = <?php echo json_encode($USER_LOCATION_); ?>;
        
        let fpPurchaseDate;

        document.addEventListener('DOMContentLoaded', () => {
            // Initialize Flatpickr
            fpPurchaseDate = flatpickr("#purchase-date", {
                dateFormat: "Y-m-d", // Value submitted to PHP
                altInput: true,      // Visual input
                altFormat: "m/d/Y",  // mm/dd/yyyy format
                allowInput: true
            });
        });

        // --- Confirmation Logic ---
        function openConfirmModal(button) {
            const row = button.closest('tr');
            document.getElementById('confirm_item_id').value = row.dataset.itemId;
            document.getElementById('confirm-item-name').textContent = row.dataset.itemName;
            document.getElementById('confirm-item-qty').textContent = row.dataset.quantity;
            document.getElementById('confirm-modal').classList.add('show');
        }
        function closeConfirmModal() { document.getElementById('confirm-modal').classList.remove('show'); }
        function submitConfirmation() {
            const formData = new FormData(document.getElementById('confirmForm'));
            fetch('../purchase_confirmations/confirmAnimalPurchase.php', { method: 'POST', body: formData })
            .then(r => r.json()).then(d => { alert(d.message); if(d.success) window.location.reload(); });
        }
        function openConfirmAllModal() { document.getElementById('confirm-all-modal').classList.add('show'); }
        function closeConfirmAllModal() { document.getElementById('confirm-all-modal').classList.remove('show'); }
        function submitConfirmAll() {
            fetch('../purchase_confirmations/confirmAllAnimalPurchases.php', { method: 'POST' })
            .then(r => r.json()).then(d => { alert(d.message); if(d.success) window.location.reload(); });
        }

        // --- Filtering Logic ---
        function filterBuildings() {
            const bSelect = document.getElementById('building_id');
            const pSelect = document.getElementById('pen_id');
            const locId = document.getElementById('location_id').value;

            bSelect.innerHTML = '<option value="">Select Building</option>';
            pSelect.innerHTML = '<option value="">Select Building First</option>';
            pSelect.disabled = true;

            if (locId) {
                bSelect.disabled = false;
                allBuildings.filter(b => b.LOCATION_ID == locId).forEach(b => {
                    bSelect.add(new Option(b.BUILDING_NAME, b.BUILDING_ID));
                });
            } else {
                bSelect.disabled = true;
            }
        }

        function filterPens() {
            const pSelect = document.getElementById('pen_id');
            const bId = document.getElementById('building_id').value;
            pSelect.innerHTML = '<option value="">Select Pen</option>';
            if (bId) {
                pSelect.disabled = false;
                allPens.filter(p => p.BUILDING_ID == bId).forEach(p => {
                    pSelect.add(new Option(p.PEN_NAME, p.PEN_ID));
                });
            } else {
                pSelect.disabled = true;
            }
        }

        // --- Dynamic Rows & Autocomplete ---
        function addAnimalRow(name = '', weight = '', cost = '') {
            const container = document.getElementById('dynamic-animal-container');
            const rowId = 'row-' + Date.now() + Math.floor(Math.random() * 100);
            
            const html = `
                <div class="dynamic-row" id="${rowId}">
                    <div class="form-group autocomplete-wrapper" style="flex:2;">
                        <label>Animal / Breed <span>*</span></label>
                        <input type="text" name="item_names[]" class="form-input animal-input" value="${name}" required autocomplete="off">
                        <div class="autocomplete-list"></div>
                    </div>
                    <div class="form-group" style="flex:1;">
                        <label>Weight (kg)</label>
                        <input type="number" name="weights[]" class="form-input" value="${weight}" step="0.01" min="0">
                    </div>
                    <div class="form-group" style="flex:1;">
                        <label>Cost (₱) <span>*</span></label>
                        <input type="number" name="unit_costs[]" class="form-input" value="${cost}" step="0.01" min="0" required>
                    </div>
                    <button type="button" class="btn-remove-row" onclick="document.getElementById('${rowId}').remove()">✕</button>
                </div>
            `;
            container.insertAdjacentHTML('beforeend', html);
            
            // Attach Autocomplete to the newly created input
            const newInput = document.getElementById(rowId).querySelector('.animal-input');
            attachAutocomplete(newInput);
        }

        let autocompleteTimeout = null;
        function attachAutocomplete(input) {
            const list = input.nextElementSibling; 
            
            input.addEventListener('input', function() {
                clearTimeout(autocompleteTimeout);
                const val = this.value.trim();
                if(val.length < 2) { list.classList.remove('show'); return; }
                
                list.innerHTML = '<div class="autocomplete-loading">Searching...</div>';
                list.classList.add('show');
                
                autocompleteTimeout = setTimeout(() => {
                    fetch(`../process/searchAnimals.php?term=${encodeURIComponent(val)}`)
                    .then(r => r.json())
                    .then(data => {
                        list.innerHTML = '';
                        if(data.length === 0) {
                            list.innerHTML = '<div class="autocomplete-no-results">No matches</div>';
                            return;
                        }
                        data.forEach(item => {
                            const div = document.createElement('div');
                            div.className = 'autocomplete-item';
                            div.innerHTML = item.replace(new RegExp(`(${val})`, 'gi'), '<strong>$1</strong>');
                            div.addEventListener('click', () => {
                                input.value = item;
                                list.classList.remove('show');
                            });
                            list.appendChild(div);
                        });
                    }).catch(() => list.classList.remove('show'));
                }, 300);
            });

            document.addEventListener('click', e => {
                if(!input.parentElement.contains(e.target)) list.classList.remove('show');
            });
        }

        // --- Supplier Autocomplete Logic ---
        let supplierTimeout = null;
        const supplierInput = document.getElementById('supplier');
        const supplierList = document.getElementById('supplier-autocomplete-list');

        if (supplierInput) {
            supplierInput.addEventListener('input', function() {
                clearTimeout(supplierTimeout);
                const val = this.value.trim();
                
                if (val.length < 2) { 
                    supplierList.classList.remove('show'); 
                    return; 
                }
                
                supplierList.innerHTML = '<div class="autocomplete-loading">Searching...</div>';
                supplierList.classList.add('show');
                
                supplierTimeout = setTimeout(() => {
                    fetch(`../process/searchSuppliers.php?term=${encodeURIComponent(val)}`)
                    .then(r => r.json())
                    .then(data => {
                        supplierList.innerHTML = '';
                        if (data.length === 0) {
                            supplierList.innerHTML = '<div class="autocomplete-no-results">No matches</div>';
                            return;
                        }
                        
                        data.forEach(item => {
                            const div = document.createElement('div');
                            div.className = 'autocomplete-item';
                            const regex = new RegExp(`(${val})`, 'gi');
                            div.innerHTML = item.replace(regex, '<strong>$1</strong>');
                            
                            div.addEventListener('click', () => {
                                supplierInput.value = item;
                                supplierList.classList.remove('show');
                            });
                            supplierList.appendChild(div);
                        });
                    }).catch(() => supplierList.classList.remove('show'));
                }, 300);
            });

            document.addEventListener('click', e => {
                if (!supplierInput.parentElement.contains(e.target)) {
                    supplierList.classList.remove('show');
                }
            });
        }

        // --- Modals ---
        function openAddModal() {
            document.getElementById('modal-title').textContent = 'Add Animal Purchase';
            document.querySelector('#modal .btn-save').textContent = 'Save Purchase';
            document.getElementById('item-form').reset();
            document.getElementById('item-id').value = '';
            
            // Flatpickr handles the default date beautifully
            fpPurchaseDate.setDate(new Date()); 
            
            const locSelect = document.getElementById('location_id');
            
            // Auto-select location logic based on admin vs regular user
            if (USER_LOCATION != 1000) {
                locSelect.value = USER_LOCATION;
                filterBuildings(); 
            } else {
                locSelect.value = "";
                document.getElementById('building_id').innerHTML = '<option value="">Select Location First</option>';
                document.getElementById('building_id').disabled = true;
                document.getElementById('pen_id').innerHTML = '<option value="">Select Building First</option>';
                document.getElementById('pen_id').disabled = true;
            }
            
            // Clear existing rows and add one blank
            document.getElementById('dynamic-animal-container').innerHTML = '';
            document.getElementById('btnAddAnimal').style.display = 'block';
            addAnimalRow(); 

            hideAlert();
            document.getElementById('modal').classList.add('show');
        }

        function populateEditForm(data) {
            document.getElementById('modal-title').textContent = 'Edit Animal Purchase';
            document.querySelector('#modal .btn-save').textContent = 'Update Purchase';
            document.getElementById('item-id').value = data.item_id;
            document.getElementById('item-desc').value = data.item_description || '';
            
            // Use the raw date for flatpickr setting
            fpPurchaseDate.setDate(data.purchase_date_raw || ''); 
            
            // Populate Supplier and Reference No
            document.getElementById('supplier').value = data.supplier || '';
            document.getElementById('reference-no').value = data.reference_no || '';
            
            const locSelect = document.getElementById('location_id');
            locSelect.value = data.location_id || ""; 
            filterBuildings(); 
            const bldgSelect = document.getElementById('building_id');
            if(data.building_id) {
                bldgSelect.value = data.building_id;
                filterPens();
                if(data.pen_id) document.getElementById('pen_id').value = data.pen_id;
            }

            // Clear and add specific row (Disable adding multiple on Edit)
            document.getElementById('dynamic-animal-container').innerHTML = '';
            document.getElementById('btnAddAnimal').style.display = 'none'; // Only edit 1 at a time
            addAnimalRow(data.item_name, data.weight, data.unit_cost);
            
            // Remove the delete button from the single row since we can't have 0 rows
            document.querySelector('.btn-remove-row').style.display = 'none';

            hideAlert();
            document.getElementById('modal').classList.add('show');
        }

        function editItem(button) {
            const row = button.closest('tr');
            populateEditForm({
                item_id: row.dataset.itemId,
                item_name: row.dataset.itemName,
                item_description: row.dataset.itemDesc,
                unit_cost: row.dataset.unitCost,
                weight: row.dataset.weight,
                purchase_date_raw: row.dataset.purchaseDateRaw,
                location_id: row.dataset.locationId,
                building_id: row.dataset.buildingId,
                pen_id: row.dataset.penId,
                supplier: row.dataset.supplier,
                reference_no: row.dataset.referenceNo
            });
        }

        function viewItem(button) {
            const row = button.closest('tr');
            const data = {
                item_id: row.dataset.itemId, 
                item_name: row.dataset.itemName, 
                item_description: row.dataset.itemDesc,
                unit: row.dataset.unitName, 
                unit_cost: row.dataset.unitCost, 
                quantity: row.dataset.quantity,
                weight: row.dataset.weight, 
                purchase_date_fmt: row.dataset.purchaseDateFmt, 
                created_at: row.dataset.createdAt,
                supplier: row.dataset.supplier, 
                reference_no: row.dataset.referenceNo
            };
            const html = `
                <div class="info-group">
                    <h3 style="color:#93c5fd; border-bottom:1px solid #334155; padding-bottom:5px;">Basic Information</h3>
                    <p><strong>Purchase ID:</strong> ANM-${String(data.item_id).padStart(4, '0')}</p>
                    <p><strong>Animal / Breed:</strong> ${data.item_name}</p>
                    <p><strong>Description:</strong> ${data.item_description || 'N/A'}</p>
                    <p><strong>Supplier:</strong> ${data.supplier || 'N/A'}</p>
                    <p><strong>Reference No:</strong> ${data.reference_no || 'N/A'}</p>
                    <p><strong>Recorded On:</strong> ${data.created_at}</p>
                </div>
                <div class="info-group" style="margin-top:20px;">
                    <h3 style="color:#93c5fd; border-bottom:1px solid #334155; padding-bottom:5px;">Purchase Details</h3>
                    <p><strong>Quantity:</strong> ${data.quantity || '1'} ${data.unit}</p>
                    <p><strong>Weight:</strong> ${data.weight || '0'} kg</p>
                    <p><strong>Cost per Head:</strong> ₱${parseFloat(data.unit_cost).toLocaleString('en-PH', {minimumFractionDigits: 2})}</p>
                    <p><strong>Purchase Date:</strong> ${data.purchase_date_fmt || 'N/A'}</p>
                </div>
            `;
            document.getElementById('view-modal-body').innerHTML = html;
            document.getElementById('view-modal').classList.add('show');
        }

        function deleteItem(button) {
            const row = button.closest('tr');
            if (confirm(`Are you sure you want to delete record for "${row.dataset.itemName}"?`)) {
                document.getElementById('delete_item_id').value = row.dataset.itemId;
                document.getElementById('deleteItemForm').submit();
            }
        }

        function saveItem() {
            const form = document.getElementById('item-form');
            if (!form.checkValidity()) { form.reportValidity(); return; }
            
            // Check if there are any animal rows
            if(document.querySelectorAll('.dynamic-row').length === 0) {
                showAlert('You must add at least one animal.', 'error'); return;
            }

            const formData = new FormData(form);
            const isEdit = document.getElementById('item-id').value !== '';
            const url = isEdit ? '../process/editAnimalPurchase.php' : '../process/addAnimalPurchase.php';
            
            const saveBtn = document.getElementById('btn-save');
            saveBtn.disabled = true;
            saveBtn.innerHTML = '<span class="loading"></span> ' + (isEdit ? 'Updating...' : 'Saving...');
            
            // Modify form data for edit mode if backend expects single values
            if (isEdit) {
                const names = formData.getAll('item_names[]');
                const weights = formData.getAll('weights[]');
                const costs = formData.getAll('unit_costs[]');
                formData.delete('item_names[]'); formData.append('item_name', names[0]);
                formData.delete('weights[]'); formData.append('weight', weights[0]);
                formData.delete('unit_costs[]'); formData.append('unit_cost', costs[0]);
            }

            fetch(url, { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showAlert(data.message, 'success');
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    showAlert(data.message, 'error');
                    saveBtn.disabled = false; saveBtn.textContent = isEdit ? 'Update Purchase' : 'Save Purchase';
                }
            }).catch(e => {
                showAlert('Network error occurred.', 'error');
                saveBtn.disabled = false; saveBtn.textContent = isEdit ? 'Update Purchase' : 'Save Purchase';
            });
        }

        function showAlert(msg, type) { const a = document.getElementById('modal-alert'); a.textContent = msg; a.className = 'alert ' + type; a.style.display = 'block'; }
        function hideAlert() { document.getElementById('modal-alert').style.display = 'none'; }
        function closeModal() { document.getElementById('modal').classList.remove('show'); }
        function closeViewModal() { document.getElementById('view-modal').classList.remove('show'); }

        function filterTable() {
            const term = document.getElementById('searchInput').value.toLowerCase();
            const rows = document.querySelectorAll('#item-table tr');
            let count = 0;
            rows.forEach(r => {
                if(r.children.length === 1) return;
                if(r.textContent.toLowerCase().includes(term)) { r.style.display = ''; count++; } 
                else { r.style.display = 'none'; }
            });
            document.getElementById('empty-state').style.display = count === 0 ? 'block' : 'none';
        }
    </script>
</body>
</html>