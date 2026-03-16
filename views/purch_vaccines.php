<?php
// views/purch_vaccines.php
error_reporting(0);
ini_set('display_errors', 0);
include '../config/Connection.php';

include '../security/checkAccess.php';
checkAccess('purchases');
$page="transactions";
include '../common/navbar.php';
include '../common/chat_support.php';
include '../functions/getUsersLocation.php'; // ADDED LOCATION FUNCTION

// --- CONFIGURATION ---
$ITEM_TYPE_ID = 11; // Vaccines
// ---------------------

try {
    if (!isset($conn)) {
        throw new Exception("Database connection failed.");
    }

    $items_sql = "";

    // 1. Fetch Items based on Location Access
    if ($USER_LOCATION_ != 1000) {
        $items_sql = "SELECT i.*, 
                  it.ITEM_TYPE_NAME,
                  u.UNIT_NAME,
                  DATE_FORMAT(i.DATE_OF_PURCHASE, '%m/%d/%Y') as DATE_OF_PURCHASE_FMT,
                  DATE_FORMAT(i.EXPIRATION_DATE, '%m/%d/%Y') as EXPIRATION_DATE_FMT,
                  DATE_FORMAT(i.CREATED_AT, '%m/%d/%Y %h:%i %p') as CREATED_AT_FMT
                  FROM ITEMS i
                  LEFT JOIN ITEM_TYPES it ON i.ITEM_TYPE_ID = it.ITEM_TYPE_ID
                  LEFT JOIN UNITS u ON i.UNIT_ID = u.UNIT_ID
                  WHERE i.ITEM_TYPE_ID = :type_id AND LOCATION_ID = :location_id
                  ORDER BY i.CREATED_AT DESC";

        $stmt = $conn->prepare($items_sql);
        $stmt->execute([':type_id' => $ITEM_TYPE_ID, ':location_id' => $USER_LOCATION_]);
        $items_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 2. Fetch Units
        $units_sql = "SELECT * FROM UNITS ORDER BY UNIT_NAME ASC";
        $stmt = $conn->prepare($units_sql);
        $stmt->execute();
        $units = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 3. Location Hierarchy (Restricted to user's location)
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
        
    } else {
        $items_sql = "SELECT i.*, 
                it.ITEM_TYPE_NAME,
                u.UNIT_NAME,
                DATE_FORMAT(i.DATE_OF_PURCHASE, '%m/%d/%Y') as DATE_OF_PURCHASE_FMT,
                DATE_FORMAT(i.EXPIRATION_DATE, '%m/%d/%Y') as EXPIRATION_DATE_FMT,
                DATE_FORMAT(i.CREATED_AT, '%m/%d/%Y %h:%i %p') as CREATED_AT_FMT
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

        // 3. Location Hierarchy (All locations)
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Vaccine Purchase Management</title>
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" type="text/css" href="https://npmcdn.com/flatpickr/dist/themes/dark.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

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
        body { font-family: system-ui, -apple-system, sans-serif; background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); min-height: 100vh; color: #e2e8f0; padding-bottom: 80px;}
        .container { max-width: 1400px; margin: 0 auto; padding: 2rem; width: 100%;}

        /* HEADER */
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; gap: 1rem; flex-wrap: wrap; }
        .header-info h1 { font-size: clamp(1.8rem, 4vw, 2.5rem); font-weight: 700; background: linear-gradient(135deg, #60a5fa, #a78bfa); -webkit-background-clip: text; -webkit-text-fill-color: transparent; margin-bottom: 0.5rem; line-height: 1.2;}
        .header-info p { color: var(--gray-light); font-size: 0.95rem; }
        
        .back-link { display: inline-flex; align-items: center; gap: 8px; text-decoration: none; color: #94a3b8; font-weight: 600; font-size: 0.95rem; margin-bottom: 10px; transition: color 0.2s; }
        .back-link:hover { color: white; }

        .header-actions { display: flex; gap: 10px; align-items: center; flex-wrap: wrap;}

        /* BUTTONS */
        .add-btn, .confirm-all-btn { display: flex; align-items: center; justify-content: center; gap: 0.5rem; padding: 0.75rem 1.5rem; color: white; border: none; border-radius: 10px; font-weight: 600; cursor: pointer; transition: all 0.2s; white-space: nowrap;}
        .add-btn { background: linear-gradient(135deg, var(--primary), var(--primary-dark)); box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3); }
        .add-btn:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(59, 130, 246, 0.4); }
        .confirm-all-btn { background: linear-gradient(135deg, #f59e0b, #d97706); box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3); }
        .confirm-all-btn:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(245, 158, 11, 0.4); }

        /* SEARCH */
        .search-container { position: relative; margin-bottom: 2rem; }
        .search-input { width: 100%; padding: 1rem 1rem 1rem 3rem; background: rgba(30, 41, 59, 0.5); border: 1px solid var(--border); border-radius: 12px; color: white; font-size: 1rem; outline: none; transition: border-color 0.2s; }
        .search-input:focus { border-color: var(--primary); }
        .search-icon { position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); width: 20px; color: var(--gray-light); }

        /* --- TABLE SCROLL FIX (DARK THEME) --- */
        .table-container {
            width: 100%;
            overflow-x: auto; 
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
            margin-bottom: 2rem;
            border: 1px solid var(--border);
            background: rgba(30, 41, 59, 0.5);
        }
        
        .table-container::-webkit-scrollbar { height: 10px; }
        .table-container::-webkit-scrollbar-track { background: #0f172a; border-radius: 0 0 12px 12px; }
        .table-container::-webkit-scrollbar-thumb { background: #475569; border-radius: 10px; border: 2px solid #0f172a; }
        .table-container::-webkit-scrollbar-thumb:hover { background: #64748b; }

        .table { width: 100%; border-collapse: collapse; min-width: 1600px; }
        .table th { padding: 1.25rem 1rem; text-align: left; font-weight: 600; color: var(--gray-light); text-transform: uppercase; font-size: 0.85rem; background: rgba(15, 23, 42, 0.8); white-space: nowrap;}
        .table td { padding: 1.25rem 1rem; border-bottom: 1px solid var(--border); color: #e2e8f0; font-size: 0.9rem; vertical-align: middle; white-space: nowrap;}
        .table tr:hover { background: rgba(59, 130, 246, 0.05); }

        /* BADGES & TEXT */
        .ref-no { font-weight: 600; color: #60a5fa; font-family: monospace; font-size: 0.95rem; }
        .supplier-name { color: #f1f5f9; font-weight: 500; font-size: 0.95rem; }
        .item-name { font-weight: 600; color: white; font-size: 1rem; margin-bottom: 4px; }
        .item-unit { color: var(--gray-light); font-size: 0.85rem; }
        .amount { font-weight: 700; color: #fbbf24; font-family: monospace; font-size: 1.1rem; }
        
        .category-badge { padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; }
        .category-nonconsumable { background: rgba(59, 130, 246, 0.1); color: #60a5fa; border: 1px solid rgba(59, 130, 246, 0.2); }
        .category-consumable { background: rgba(16, 185, 129, 0.1); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.2); }
        
        .confirm-btn { background: rgba(16, 185, 129, 0.1); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.3); padding: 6px 12px; border-radius: 6px; font-weight: 600; cursor: pointer; transition: all 0.2s; font-size: 0.8rem; text-transform: uppercase; width: 100%; white-space: nowrap;}
        .confirm-btn:hover { background: rgba(16, 185, 129, 0.2); transform: scale(1.05); }
        .confirmed-badge { color: var(--gray); font-size: 0.8rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; opacity: 0.7; width: 100%; text-align: center; display: inline-block;}

        /* ACTIONS */
        .actions { display: flex; gap: 0.5rem; justify-content: center;}
        .action-btn { width: 32px; height: 32px; border-radius: 8px; border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.2s; background: rgba(255,255,255,0.05); }
        .action-btn.view { color: #60a5fa; } .action-btn.view:hover { color: #93c5fd; background: rgba(59, 130, 246, 0.2); border-color: #3b82f6; }
        .action-btn.edit { color: #a78bfa; } .action-btn.edit:hover { color: #c4b5fd; background: rgba(139, 92, 246, 0.2); border-color: #8b5cf6; }
        .action-btn.delete { color: #f87171; } .action-btn.delete:hover { color: #fca5a5; background: rgba(239, 68, 68, 0.2); border-color: #ef4444; }
        .action-btn:hover { transform: translateY(-2px); filter: brightness(1.2); }
        .icon { width: 18px; height: 18px; }

        /* MODAL */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.75); backdrop-filter: blur(4px); z-index: 1000; align-items: center; justify-content: center; padding: 1rem; }
        .modal.show { display: flex; }
        .modal-content { background: var(--dark-light); border-radius: 20px; width: 100%; max-width: 700px; max-height: 90vh; display: flex; flex-direction: column; border: 1px solid var(--border); box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); animation: slideUp 0.3s ease; }
        .modal-header { padding: 1.5rem 2rem; border-bottom: 1px solid var(--border); }
        .modal-header h2 { font-size: 1.25rem; font-weight: 700; color: white; margin: 0; }
        .modal-body { padding: 2rem; overflow-y: auto; flex-grow: 1; }
        .modal-footer { padding: 1.5rem 2rem; border-top: 1px solid var(--border); display: flex; justify-content: flex-end; gap: 1rem; background: rgba(15, 23, 42, 0.3); border-radius: 0 0 20px 20px; }

        /* FORM */
        .info-group h3 { font-size: 1rem; color: white; margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 1px solid var(--border); }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        .form-row-cascading { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 1.5rem; background: rgba(255,255,255,0.02); padding: 15px; border-radius: 8px; border: 1px dashed var(--border); }
        
        .form-group { margin-bottom: 1.25rem; position: relative; }
        .form-group label { display: block; color: var(--gray-light); font-size: 0.85rem; font-weight: 600; margin-bottom: 0.5rem; text-transform: uppercase; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 0.75rem; background: rgba(15, 23, 42, 0.6); border: 1px solid var(--border); border-radius: 8px; color: white; font-size: 0.95rem; outline: none; transition: border-color 0.2s; }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1); }
        .form-group select:disabled, input[readonly] { opacity: 0.5; cursor: not-allowed; background-color: #1f2937;}

        /* AUTOCOMPLETE */
        .autocomplete-wrapper { position: relative; }
        .autocomplete-list { position: absolute; z-index: 100; top: 100%; left: 0; right: 0; background: #1e293b; border: 1px solid var(--border); border-radius: 0 0 8px 8px; max-height: 200px; overflow-y: auto; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.3); display: none; }
        .autocomplete-list.show { display: block; }
        .autocomplete-item { padding: 10px 15px; cursor: pointer; border-bottom: 1px solid var(--border); color: #e2e8f0; }
        .autocomplete-item:hover { background: rgba(59, 130, 246, 0.1); }
        .autocomplete-item strong { color: var(--primary); }
        .autocomplete-loading, .autocomplete-no-results { padding: 12px 15px; text-align: center; color: #666; font-size: 14px; }

        /* ALERTS & EMPTY STATE */
        .alert { padding: 1rem; border-radius: 8px; margin-bottom: 1rem; display: none; font-size: 0.9rem; text-align: center; font-weight: 600;}
        .alert.show { display: block; }
        .alert.success { background: rgba(16, 185, 129, 0.1); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.2); }
        .alert.error { background: rgba(239, 68, 68, 0.1); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.2); }
        
        .empty-state { display: none; text-align: center; padding: 3rem; color: var(--gray); }

        .btn-cancel { background: transparent; color: var(--gray-light); border: 1px solid var(--border); padding: 0.75rem 1.5rem; border-radius: 8px; cursor: pointer; font-weight: 600; transition: all 0.2s; }
        .btn-cancel:hover { color: white; border-color: var(--gray); background: rgba(255,255,255,0.05); }
        .btn-save { background: linear-gradient(135deg, var(--primary), var(--primary-dark)); color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 8px; font-weight: 600; cursor: pointer; transition: background 0.2s; }
        .btn-save:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3); }
        .btn-save:disabled { opacity: 0.5; cursor: not-allowed; }

        /* Modal Specifics */
        .confirm-content { text-align: center; padding: 20px; }
        .confirm-icon { font-size: 4rem; margin-bottom: 15px; display: block; }
        .warning-text { color: #f87171; font-size: 0.9rem; margin: 15px 0 25px 0; background: rgba(239, 68, 68, 0.1); padding: 10px; border-radius: 8px; border: 1px solid rgba(239, 68, 68, 0.2); }

        /* ========================================= */
        /* MOBILE RESPONSIVENESS OVERRIDES           */
        /* ========================================= */
        @media (max-width: 900px) {
            .container { padding: 1rem; }
            .header { flex-direction: column; align-items: flex-start; gap: 1rem; }
            .header-info { text-align: left; }
            .header-actions { flex-direction: column; width: 100%; gap: 10px; }
            .add-btn, .confirm-all-btn { width: 100%; justify-content: center; }

            /* Modal Adjustments */
            .form-row, .form-row-cascading { grid-template-columns: 1fr; gap: 0; }
            .modal-footer { flex-direction: column; }
            .modal-footer button { width: 100%; margin-left: 0; }
            .info-group strong { width: 100%; display: block; margin-bottom: 4px; }
            .info-group p { margin-bottom: 12px; }

            /* Table to Card Layout */
            .table-container { border: none; background: transparent; overflow: visible; box-shadow: none; }
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
                white-space: normal; /* Allow text wrap on mobile */
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
        @keyframes slideUp { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
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
                <h1>Vaccine Purchase</h1>
                <p>Manage and track vaccine purchases</p>
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
                        <th>Ref No</th>
                        <th>Supplier</th>
                        <th>Name</th>
                        <th>Qty</th>
                        <th>Unit</th>
                        <th>Cost</th>
                        <th>Total</th> 
                        <th>Category</th>
                        <th>Date</th>
                        <th>Expiry Date</th> 
                        <th style="text-align: center; width: 120px;">Status</th>
                        <th style="text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody id="item-table">
                    <?php 
                    $categoryLabels = [0 => 'Non-Consumable', 1 => 'Consumable'];
                    $categoryClasses = [0 => 'category-nonconsumable', 1 => 'category-consumable'];

                    if(empty($items_data)): ?>
                        <tr>
                            <td colspan="12" style="text-align:center; padding:3rem; color:#64748b;">No purchases recorded yet.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach($items_data as $item): 
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
                            data-purchase-date-raw="<?php echo htmlspecialchars($item['DATE_OF_PURCHASE'] ?? ''); ?>"
                            data-purchase-date-fmt="<?php echo htmlspecialchars($item['DATE_OF_PURCHASE_FMT'] ?? ''); ?>"
                            data-expiration-date-raw="<?php echo htmlspecialchars($item['EXPIRATION_DATE'] ?? ''); ?>" 
                            data-expiration-date-fmt="<?php echo htmlspecialchars($item['EXPIRATION_DATE_FMT'] ?? ''); ?>" 
                            data-location-id="<?php echo $item['LOCATION_ID'] ?? ''; ?>"
                            data-building-id="<?php echo $item['BUILDING_ID'] ?? ''; ?>"
                            data-pen-id="<?php echo $item['PEN_ID'] ?? ''; ?>"
                            data-supplier="<?php echo htmlspecialchars($item['SUPPLIER'] ?? ''); ?>"
                            data-reference-no="<?php echo htmlspecialchars($item['REFERENCE_NO'] ?? ''); ?>"
                            data-created-at="<?php echo htmlspecialchars($item['CREATED_AT_FMT'] ?? ''); ?>">
                            
                            <td data-label="Ref No"><div class="ref-no"><?php echo !empty($item['REFERENCE_NO']) ? htmlspecialchars($item['REFERENCE_NO']) : '—'; ?></div></td>
                            <td data-label="Supplier"><div class="supplier-name"><?php echo !empty($item['SUPPLIER']) ? htmlspecialchars($item['SUPPLIER']) : 'General Supplier'; ?></div></td>
                            <td data-label="Name"><div class="item-name"><?php echo htmlspecialchars($item['ITEM_NAME']); ?></div></td>
                            <td data-label="Qty"><div class="item-unit" style="color:white; font-weight:600;"><?php echo number_format($item['QUANTITY'] ?? 0, 2); ?></div></td>
                            <td data-label="Unit"><div class="item-unit"><?php echo htmlspecialchars($item['UNIT_NAME']); ?></div></td>
                            <td data-label="Cost"><div class="amount">₱<?php echo number_format($item['UNIT_COST'], 2); ?></div></td>
                            <td data-label="Total"><div class="amount" style="color:#60a5fa;">₱<?php echo number_format($totalCost, 2); ?></div></td>

                            <td data-label="Category">
                                <span class="category-badge <?php echo $categoryClasses[$item['ITEM_CATEGORY']]; ?>">
                                    <?php echo $categoryLabels[$item['ITEM_CATEGORY']]; ?>
                                </span>
                            </td>
                            <td data-label="Date"><div class="item-unit"><?php echo htmlspecialchars($item['DATE_OF_PURCHASE_FMT'] ?? 'N/A'); ?></div></td>
                            <td data-label="Expiry Date">
                                <div class="item-unit" style="color: #fca5a5; font-weight:600;"><?php echo htmlspecialchars($item['EXPIRATION_DATE_FMT'] ?? 'N/A'); ?></div>
                            </td>

                            <td data-label="Status" style="text-align: center;">
                                <?php if(!$isConfirmed): ?>
                                    <button class="confirm-btn" onclick="openConfirmModal(this)">Confirm</button>
                                <?php else: ?>
                                    <div class="confirmed-badge">Locked</div>
                                <?php endif; ?>
                            </td>

                            <td data-label="Actions">
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
                    <?php endif; ?>
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
                <h2 id="modal-title">Add Vaccine Purchase</h2>
            </div>
            <div class="modal-body">
                <div id="modal-alert" class="alert"></div>
                <form id="item-form" method="POST">
                    <input type="hidden" id="item-id" name="item_id">
                    <input type="hidden" name="item_type_id" value="<?php echo $ITEM_TYPE_ID; ?>">
                    
                    <div class="info-group">
                        <h3 style="margin-top:0;">Item Information</h3>
                        <div class="form-group autocomplete-wrapper">
                            <label for="item-name">Name <span>*</span></label>
                            <input type="text" id="item-name" name="item_name" placeholder="e.g., COVID-19 Vaccine" required maxlength="300" autocomplete="off">
                            <div id="autocomplete-list" class="autocomplete-list"></div>
                        </div>

                        <div class="form-row">
                            <div class="form-group autocomplete-wrapper">
                                <label>Supplier</label>
                                <input type="text" id="supplier" name="supplier" placeholder="e.g., Global Pharma" autocomplete="off">
                                <div id="supplier-autocomplete-list" class="autocomplete-list"></div>
                            </div>
                            <div class="form-group">
                                <label>Reference No.</label>
                                <input type="text" id="reference-no" name="reference_no" placeholder="e.g., OR-12345">
                            </div>
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
                                    <option value="1" selected>Consumable</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="purchase-date">Date <span>*</span></label>
                                <input type="text" id="purchase-date" name="date_of_purchase" class="form-input date-picker" placeholder="Date of Purchase" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="expiration-date">Expiration Date <span style="color:#fca5a5;">(Required)</span></label>
                            <input type="text" id="expiration-date" name="expiration_date" class="form-input date-picker" placeholder="Expiration Date" required>
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
            <div class="modal-footer"><button type="button" class="btn-cancel" onclick="closeViewModal()" style="width:100%">Close</button></div>
        </div>
    </div>

    <div id="confirm-modal" class="modal">
        <div class="modal-content" style="max-width: 450px;">
            <div class="modal-body" style="text-align: center;">
                <span class="confirm-icon">💉</span>
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
                <span class="confirm-icon" style="font-size: 3rem;">📋</span>
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

    <form id="deleteItemForm" method="POST" action="../process/deleteVaccines.php" style="display: none;"><input type="hidden" id="delete_item_id" name="item_id"></form>

    <script>
        const allBuildings = <?php echo json_encode($buildings_raw); ?>;
        const allPens = <?php echo json_encode($pens_raw); ?>;
        const USER_LOCATION = <?php echo json_encode($USER_LOCATION_); ?>;

        let fpPurchaseDate;
        let fpExpirationDate;

        document.addEventListener('DOMContentLoaded', () => {
            // Initialize Flatpickr
            fpPurchaseDate = flatpickr("#purchase-date", {
                dateFormat: "Y-m-d", 
                altInput: true,      
                altFormat: "m/d/Y",  
                allowInput: true
            });

            fpExpirationDate = flatpickr("#expiration-date", {
                dateFormat: "Y-m-d", 
                altInput: true,      
                altFormat: "m/d/Y",  
                allowInput: true
            });
        });

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

        // --- Autocomplete logic for Item Name ---
        let autocompleteTimeout = null;
        let currentFocus = -1;
        function initAutocomplete() {
            const input = document.getElementById('item-name');
            const list = document.getElementById('autocomplete-list');
            
            const newInp = input.cloneNode(true);
            input.parentNode.replaceChild(newInp, input);
            
            newInp.addEventListener('input', function() {
                const val = this.value.trim();
                clearTimeout(autocompleteTimeout);
                if(val.length < 2) { list.classList.remove('show'); return; }
                
                list.innerHTML = '<div class="autocomplete-loading">Searching...</div>';
                list.classList.add('show');
                
                autocompleteTimeout = setTimeout(() => {
                    fetch(`../process/searchVaccines.php?term=${encodeURIComponent(val)}`)
                    .then(r => r.json()).then(d => displayAutocomplete(d, val));
                }, 300);
            });

            newInp.addEventListener('keydown', function(e) {
                const items = list.getElementsByClassName('autocomplete-item');
                if (e.keyCode === 40) { e.preventDefault(); currentFocus++; addActive(items); } 
                else if (e.keyCode === 38) { e.preventDefault(); currentFocus--; addActive(items); } 
                else if (e.keyCode === 13) { if (currentFocus > -1 && items[currentFocus]) { e.preventDefault(); items[currentFocus].click(); } }
                else if (e.keyCode === 27) { closeAutocomplete(); }
            });
            
            document.addEventListener('click', e => {
                if(!e.target.closest('.autocomplete-wrapper')) list.classList.remove('show');
            });
        }

        function displayAutocomplete(data, term) {
            const list = document.getElementById('autocomplete-list');
            list.innerHTML = '';
            currentFocus = -1;
            if(data.length === 0) {
                list.innerHTML = '<div class="autocomplete-no-results">No items found</div>';
                list.classList.add('show'); return;
            }
            
            data.forEach(item => {
                const div = document.createElement('div');
                div.className = 'autocomplete-item';
                div.innerHTML = item.replace(new RegExp(`(${escapeRegex(term)})`, 'gi'), '<strong>$1</strong>');
                div.onclick = () => {
                    document.getElementById('item-name').value = item;
                    list.classList.remove('show');
                };
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
        function removeActive(items) { for (let i = 0; i < items.length; i++) { items[i].classList.remove('active'); } }
        function escapeRegex(string) { return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); }
        function closeAutocomplete() {
            const list = document.getElementById('autocomplete-list');
            if (list) { list.classList.remove('show'); list.innerHTML = ''; }
            currentFocus = -1;
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

        function openAddModal() {
            document.getElementById('item-form').reset();
            document.getElementById('item-id').value = '';
            document.getElementById('modal-title').textContent = 'Add Vaccine Purchase';
            document.getElementById('btn-save').textContent = 'Save Purchase';
            
            // Set fields to blank by default (Flatpickr allows this via clear)
            fpPurchaseDate.clear(); 
            fpExpirationDate.clear(); 
            
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
            
            // Re-populate using Flatpickr with raw dates
            fpPurchaseDate.setDate(d.purchaseDateRaw || ''); 
            fpExpirationDate.setDate(d.expirationDateRaw || ''); 
            
            document.getElementById('supplier').value = d.supplier || '';
            document.getElementById('reference-no').value = d.referenceNo || '';

            const loc = document.getElementById('location_id');
            loc.value = d.locationId || "";
            filterBuildings();
            
            const bldg = document.getElementById('building_id');
            if(d.buildingId) {
                bldg.value = d.buildingId;
                filterPens();
                if(d.penId) document.getElementById('pen_id').value = d.penId;
            }

            document.getElementById('modal-title').textContent = 'Edit Vaccine Purchase';
            document.getElementById('btn-save').textContent = 'Update Purchase';
            document.getElementById('modal-alert').style.display = 'none';
            document.getElementById('modal').classList.add('show');
            setTimeout(initAutocomplete, 100);
        }

        function saveItem() {
            const form = document.getElementById('item-form');
            if (!form.checkValidity()) { form.reportValidity(); return; }
            
            const id = document.getElementById('item-id').value;
            const url = id ? '../process/editVaccines.php' : '../process/addVaccines.php';
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
            
            fetch('../process/deleteVaccines.php', { method:'POST', body:fd })
            .then(r => r.json()).then(data => {
                alert(data.message);
                if(data.success) btn.closest('tr').remove();
            });
        }

        function viewItem(btn) {
            const d = btn.closest('tr').dataset;
            const html = `
                <div class="info-group">
                    <h3>Basic Info</h3>
                    <p><strong>Ref No:</strong> ${d.referenceNo || '-'}</p>
                    <p><strong>Supplier:</strong> ${d.supplier || '-'}</p>
                    <p><strong>Item:</strong> ${d.itemName}</p>
                    <p><strong>Desc:</strong> ${d.itemDesc || '-'}</p>
                </div>
                <div class="info-group">
                    <h3>Purchase Info</h3>
                    <p><strong>Qty:</strong> ${d.quantity} ${d.unitName}</p>
                    <p><strong>Cost:</strong> ₱${d.unitCost} / unit</p>
                    <p><strong>Total:</strong> ₱${(d.quantity * d.unitCost).toFixed(2)}</p>
                    <p><strong>Date:</strong> ${d.purchaseDateFmt}</p>
                    <p><strong>Expiration:</strong> <span style="color:#fca5a5;">${d.expirationDateFmt || 'N/A'}</span></p>
                </div>`;
            document.getElementById('view-modal-body').innerHTML = html;
            document.getElementById('view-modal').classList.add('show');
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
            
            fetch('../purchase_confirmations/confirmVaccines.php', { method:'POST', body: new FormData(document.getElementById('confirmForm')) })
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
            
            fetch('../purchase_confirmations/confirmAllVaccines.php', { method:'POST' })
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
            
            // Fix for empty state logic conflict with PHP default row
            if (rows.length === 1 && rows[0].children.length === 1) {
                document.getElementById('empty-state').style.display = 'none';
                return;
            }

            let visible = 0;
            rows.forEach(r => {
                const txt = r.innerText.toLowerCase();
                r.style.display = txt.includes(term) ? '' : 'none';
                if(r.style.display !== 'none') visible++;
            });
            document.getElementById('empty-state').style.display = visible === 0 ? 'block' : 'none';
        }

        document.addEventListener('DOMContentLoaded', () => checkEmptyState());
        function checkEmptyState() {
            filterTable();
        }
    </script>
</body>
</html>