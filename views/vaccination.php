<?php
// views/vaccination.php
error_reporting(E_ALL);
ini_set('display_errors', 0);

$page = "transactions";
include '../common/navbar.php';
include '../config/Connection.php';

try {
    if (!isset($conn)) { throw new Exception("Database connection failed."); }

    // 1. Retrieve Veterinarians
    $vets_sql = "SELECT FULL_NAME FROM VETERINARIANS WHERE IS_ACTIVE = 1 ORDER BY FULL_NAME ASC";
    $stmt = $conn->prepare($vets_sql);
    $stmt->execute();
    $vets_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Retrieve Locations for Cascading Filter
    $loc_sql = "SELECT LOCATION_ID, LOCATION_NAME FROM LOCATIONS ORDER BY LOCATION_NAME ASC";
    $stmt = $conn->prepare($loc_sql);
    $stmt->execute();
    $locations_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Retrieve Vaccines from Inventory
    $vac_sql = "SELECT SUPPLY_ID, SUPPLY_NAME, TOTAL_STOCK, u.UNIT_ABBR, v.UNIT_ID 
                FROM VACCINES v 
                LEFT JOIN UNITS u ON v.UNIT_ID = u.UNIT_ID 
                ORDER BY SUPPLY_NAME ASC";
    $stmt = $conn->prepare($vac_sql);
    $stmt->execute();
    $vaccines_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $vets_data = [];
    $locations_data = [];
    $vaccines_data = [];
    echo "<script>console.error('Database Error: " . addslashes($e->getMessage()) . "');</script>";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vaccination Management</title>
    <link rel="stylesheet" href="../css/purch_housing_facilities.css">
    <style>
        /* --- CORE STYLES --- */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            min-height: 100vh;
            color: white;
        }
        .container { max-width: 1400px; margin: 0 auto; padding: 2rem; }
        
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; }
        .header-info h1 { font-size: 2.5rem; font-weight: bold; margin-bottom: 0.5rem; }
        .header-info p { color: #cbd5e1; }
        
        .add-btn {
            display: flex; align-items: center; gap: 0.5rem;
            background: linear-gradient(135deg, #2563eb, #9333ea);
            color: white; border: none; padding: 0.75rem 1.5rem;
            border-radius: 0.5rem; font-weight: 600; cursor: pointer;
            transition: all 0.2s; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }
        .add-btn:hover { transform: scale(1.05); }

        /* --- NAV TABS --- */
        .nav-tabs {
            display: flex; gap: 0; margin-bottom: 30px; 
            background: rgba(15, 23, 42, 0.5);
            border-radius: 12px; padding: 6px; 
            backdrop-filter: blur(10px);
        }
        .nav-tab {
            flex: 1; padding: 14px 28px; 
            background: transparent; border: none; 
            color: #94a3b8; font-weight: 600; 
            cursor: pointer; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            font-size: 15px; border-radius: 8px; 
            display: flex; align-items: center; justify-content: center;
            gap: 8px; position: relative;
            text-decoration: none;
        }
        .nav-tab:hover { color: #e2e8f0; background: rgba(255, 255, 255, 0.05); }
        .nav-tab.active { 
            color: white; 
            background: linear-gradient(135deg, #2563eb, #1d4ed8); /* Blue for Vaccination */
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.4); 
        }
        .nav-tab svg { width: 20px; height: 20px; }

        /* --- SEARCH --- */
        .search-container { position: relative; margin-bottom: 2rem; }
        .search-input {
            width: 100%; padding: 1rem 1rem 1rem 3rem;
            background: rgba(30, 41, 59, 0.5); border: 1px solid #475569;
            border-radius: 0.5rem; color: white; font-size: 1rem;
        }
        .search-icon {
            position: absolute; left: 1rem; top: 50%;
            transform: translateY(-50%); color: #94a3b8; width: 20px; height: 20px;
        }

        /* --- TABLE --- */
        .table-container {
            background: rgba(30, 41, 59, 0.5); backdrop-filter: blur(10px);
            border-radius: 0.75rem; border: 1px solid #475569;
            overflow: hidden; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }
        .table { width: 100%; border-collapse: collapse; }
        .table thead { background: linear-gradient(135deg, #475569, #334155); }
        .table th {
            padding: 1rem 1.5rem; text-align: left; font-size: 0.875rem;
            font-weight: 600; color: #e2e8f0; text-transform: uppercase;
        }
        .table tbody tr { border-bottom: 1px solid #475569; transition: background-color 0.2s; }
        .table tbody tr:hover { background: rgba(55, 65, 81, 0.5); }
        .table td { padding: 1rem 1.5rem; vertical-align: middle; }

        .trans-id { font-weight: 600; color: #93c5fd; }
        .tag-badge {
            background: rgba(147, 51, 234, 0.2); color: #c084fc;
            padding: 0.25rem 0.75rem; border-radius: 9999px; font-weight: 600; font-size: 0.875rem;
        }
        
        .location-info { font-size: 0.875rem; }
        .location-name { font-weight: 500; color: #e2e8f0; }
        .location-details { color: #94a3b8; font-size: 0.75rem; margin-top: 0.25rem; }
        
        .vaccine-name { font-weight: 500; color: #fbbf24; }
        .vet-name { font-size: 0.75rem; color: #94a3b8; margin-top: 2px;}

        .quantity-badge {
            display: inline-block; padding: 6px 12px;
            background: linear-gradient(135deg, #10b981, #059669);
            color: white; border-radius: 8px; font-size: 13px; font-weight: 700;
            box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3);
        }
        
        .actions { display: flex; gap: 0.5rem; }
        .action-btn {
            padding: 0.5rem; border: none; border-radius: 0.5rem;
            cursor: pointer; background: transparent; transition: all 0.2s;
        }
        .action-btn.view { color: #60a5fa; }
        .action-btn.edit { color: #a78bfa; }
        .action-btn.delete { color: #f87171; }
        .action-btn:hover { background: rgba(255,255,255,0.1); }

        /* --- MODAL STYLES --- */
        .modal {
            display: none; position: fixed; top: 0; left: 0;
            width: 100%; height: 100%; background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(4px); z-index: 10000; align-items: center; justify-content: center;
        }
        .modal.show { display: flex; }
        .modal-content {
            background: #1e293b; border-radius: 0.75rem; width: 100%;
            max-width: 45rem; max-height: 90vh; overflow-y: auto;
            border: 1px solid #475569; animation: slideUp 0.3s ease;
        }
        .modal-header { padding: 1.5rem; border-bottom: 1px solid #475569; }
        .modal-body { padding: 1.5rem; }
        .modal-footer {
            padding: 1.5rem; border-top: 1px solid #475569;
            display: flex; justify-content: flex-end; gap: 0.75rem;
        }

        @keyframes slideUp { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }

        /* Form Grid */
        .form-row-cascading { 
            display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; 
            margin-bottom: 15px; background: rgba(255, 255, 255, 0.03); 
            padding: 15px; border-radius: 8px; border: 1px dashed rgba(148, 163, 184, 0.2); 
        }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        .form-group { display: flex; flex-direction: column; gap: 0.5rem; margin-bottom: 1rem; }
        .form-group label { color: #cbd5e1; font-size: 0.875rem; }
        .form-group input, .form-group select, .form-group textarea {
            padding: 0.75rem; background: #374151; border: 1px solid #4b5563;
            border-radius: 0.5rem; color: white; font-size: 1rem;
        }
        .form-group select:disabled { opacity: 0.6; cursor: not-allowed; }
        
        .stock-info {
            font-size: 13px; color: #6b7280; margin-top: 8px; padding: 8px 12px;
            background: rgba(59, 130, 246, 0.1); border-radius: 6px; border-left: 3px solid #3b82f6;
        }
        .stock-info.low-stock { color: #dc2626; background: rgba(220, 38, 38, 0.1); border-left-color: #dc2626; font-weight: 600; }
        
        .btn-save { padding: 0.5rem 1.5rem; background: linear-gradient(135deg, #2563eb, #9333ea); border: none; border-radius: 0.5rem; color: white; cursor: pointer; }
        .btn-cancel { padding: 0.5rem 1.5rem; background: transparent; border: none; color: #cbd5e1; cursor: pointer; }
        
        .empty-state { text-align: center; padding: 3rem; display: none; color: #94a3b8; }
        #animal-loading { font-size: 0.75rem; color: #fbbf24; display: none; }
        .info-group h3 { color: #93c5fd; font-size: 1rem; margin-bottom: 1rem; border-bottom: 1px solid #334155; padding-bottom: 0.5rem; }
        
        @media (max-width: 768px) {
            .form-row, .form-row-cascading { grid-template-columns: 1fr; }
            .nav-tabs { flex-direction: column; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="header-info">
                <h1>Vaccination Records</h1>
                <p>Manage immunization records linked to animal inventory</p>
            </div>
            <button type="button" class="add-btn" onclick="openAddModal()">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                Add Record
            </button>
        </div>

        <div class="nav-tabs">
            <a href="vaccination.php" class="nav-tab active">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"></path></svg>
                Vaccination Transactions
            </a>
            <a href="available_vaccines.php" class="nav-tab">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                Available Vaccines
            </a>
        </div>

        <div class="search-container">
            <svg class="search-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m21 21-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
            </svg>
            <input type="text" class="search-input" id="searchInput" placeholder="Search by tag number, vaccine name, or location..." onkeyup="filterTable()">
        </div>

        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Tag No.</th>
                        <th>Location</th>
                        <th>Vaccine Used</th>
                        <th>Quantity</th>
                        <th>Date & Time</th>
                        <th>Cost</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="transaction-table">
                    <?php 
                    // UPDATED QUERY with dual costs and LOCATION/BUILDING/PEN IDs
                    $query = "SELECT 
                                v.VACCINATION_ID,
                                v.VACCINATION_DATE,
                                v.VACCINATION_COST,  -- Service Fee
                                v.VACCINE_COST,      -- Item Cost
                                v.VET_NAME,
                                v.REMARKS,
                                v.ANIMAL_ID,
                                v.VACCINE_ITEM_ID, 
                                v.QUANTITY,
                                v.UNIT_ID,
                                a.TAG_NO,
                                vac.SUPPLY_NAME AS VACCINE_NAME,
                                l.LOCATION_ID, l.LOCATION_NAME,
                                b.BUILDING_ID, b.BUILDING_NAME,
                                p.PEN_ID, p.PEN_NAME,
                                u.UNIT_ABBR
                              FROM VACCINATION_RECORDS v
                              LEFT JOIN ANIMAL_RECORDS a ON v.ANIMAL_ID = a.ANIMAL_ID
                              LEFT JOIN VACCINES vac ON v.VACCINE_ITEM_ID = vac.SUPPLY_ID
                              LEFT JOIN LOCATIONS l ON a.LOCATION_ID = l.LOCATION_ID
                              LEFT JOIN BUILDINGS b ON a.BUILDING_ID = b.BUILDING_ID
                              LEFT JOIN PENS p ON a.PEN_ID = p.PEN_ID
                              LEFT JOIN UNITS u ON v.UNIT_ID = u.UNIT_ID
                              ORDER BY v.VACCINATION_DATE ASC";
                              
                    $stmt = $conn->prepare($query);
                    $stmt->execute();
                    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    foreach ($records as $row):
                        $totalCost = ($row['VACCINATION_COST'] ?? 0) + ($row['VACCINE_COST'] ?? 0);
                        $jsonData = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8');
                        $dateObj = new DateTime($row['VACCINATION_DATE']);
                        $displayDate = $dateObj->format('M d, Y h:i A');
                    ?>
                    <tr data-row-json='<?php echo $jsonData; ?>'>
                        <td><div class="trans-id">#<?php echo $row['VACCINATION_ID']; ?></div></td>
                        <td><span class="tag-badge"><?php echo htmlspecialchars($row['TAG_NO']); ?></span></td>
                        <td>
                            <div class="location-info">
                                <div class="location-name"><?php echo htmlspecialchars($row['LOCATION_NAME'] ?? 'Unknown'); ?></div>
                                <div class="location-details">
                                    <?php echo htmlspecialchars(($row['BUILDING_NAME'] ?? '-') . ' • ' . ($row['PEN_NAME'] ?? '-')); ?>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="vaccine-name"><?php echo htmlspecialchars($row['VACCINE_NAME']); ?></div>
                            <div class="vet-name">Vet: <?php echo htmlspecialchars($row['VET_NAME'] ?: 'N/A'); ?></div>
                        </td>
                        <td>
                            <span class="quantity-badge">
                                <?php echo number_format($row['QUANTITY'] ?? 0, 2); ?> 
                                <?php echo htmlspecialchars($row['UNIT_ABBR'] ?? 'units'); ?>
                            </span>
                        </td>
                        <td>
                            <div style="color: #cbd5e1;">
                                <?php echo $displayDate; ?>
                            </div>
                        </td>
                        <td>
                            <div style="color: #86efac; font-weight: 600;">
                                <?php echo $totalCost > 0 ? '₱' . number_format($totalCost, 2) : 'Free'; ?>
                            </div>
                        </td>
                        <td>
                            <div class="actions">
                                <button class="action-btn view" onclick='viewRecord(this)' title="View">
                                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                </button>
                                <button class="action-btn edit" onclick='editRecord(this)' title="Edit">
                                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                </button>
                                <button class="action-btn delete" onclick="deleteRecord(<?php echo $row['VACCINATION_ID']; ?>)" title="Delete">
                                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div id="empty-state" class="empty-state">
                <h3>No records found</h3>
                <p>Try adjusting your search terms</p>
            </div>
        </div>
    </div>

    <div id="modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modal-title">Add Vaccination Record</h2>
            </div>
            <div class="modal-body">
                <form id="record-form">
                    <input type="hidden" id="vaccination_id" name="vaccination_id">
                    
                    <div style="margin-bottom: 1.5rem;">
                        <h3>Target Animal</h3>
                        <p style="font-size:0.85rem; color:#94a3b8; margin-bottom:10px;">Filter location to find the animal.</p>
                        
                        <div class="form-row-cascading">
                            <div class="form-group">
                                <label>Location</label>
                                <select id="location_id" onchange="loadBuildings(this.value)">
                                    <option value="">Select Location</option>
                                    <?php foreach($locations_data as $loc): ?>
                                        <option value="<?php echo $loc['LOCATION_ID']; ?>"><?php echo htmlspecialchars($loc['LOCATION_NAME']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Building</label>
                                <select id="building_id" onchange="loadPens(this.value)" disabled>
                                    <option value="">Select Loc First</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Pen</label>
                                <select id="pen_id" onchange="loadAnimals(this.value)" disabled>
                                    <option value="">Select Bldg First</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Animal Tag <span style="color:#f87171">*</span> <span id="animal-loading">Loading...</span></label>
                            <select id="animal_id" name="animal_id" required disabled>
                                <option value="">Select Pen First</option>
                            </select>
                        </div>
                    </div>

                    <div style="margin-bottom: 1.5rem;">
                        <h3>Vaccine Details</h3>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Vaccine Used <span style="color:#f87171">*</span></label>
                                <select id="vaccine_item_id" name="vaccine_item_id" required onchange="updateStockInfo()">
                                    <option value="">Select Vaccine from Inventory</option>
                                    <?php foreach($vaccines_data as $vac): ?>
                                        <option value="<?php echo $vac['SUPPLY_ID']; ?>" 
                                            data-quantity="<?php echo $vac['TOTAL_STOCK'] ?? 0; ?>"
                                            data-units="<?php echo $vac['UNIT_ABBR'] ?? 'units'; ?>"
                                            data-unit-id="<?php echo $vac['UNIT_ID']; ?>">
                                            <?php echo htmlspecialchars($vac['SUPPLY_NAME']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div id="stock-info" class="stock-info" style="display: none;"></div>
                            </div>
                            <div class="form-group">
                                <label>Date & Time Administered <span style="color:#f87171">*</span></label>
                                <input type="datetime-local" id="vaccination_date" name="vaccination_date" required>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Quantity Used <span style="color:#f87171">*</span> <span id="units_used_span"></span></label>
                                <input type="number" id="quantity" name="quantity" step="0.01" min="0.01" placeholder="0.00" required>
                                <input type="hidden" id="unit_id" name="unit_id">
                            </div>
                            <div class="form-group">
                                <label>Vaccination Cost (Service Fee) <span style="font-size:0.7rem; color:#94a3b8">(Optional)</span></label>
                                <input type="number" id="cost" name="cost" step="0.01" min="0" placeholder="0.00" value="0">
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Veterinarian Name <span style="color:#f87171">*</span></label>
                            <select id="vet_name" name="vet_name" required>
                                <option value="">Select Veterinarian</option>
                                <?php foreach($vets_data as $vet): ?>
                                    <option value="<?php echo htmlspecialchars($vet['FULL_NAME']); ?>">
                                        <?php echo htmlspecialchars($vet['FULL_NAME']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Remarks</label>
                            <textarea id="remarks" name="remarks" rows="3" placeholder="Additional notes..."></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeModal()">Cancel</button>
                <button type="button" class="btn-save" id="btn-save" onclick="saveRecord()">Save Record</button>
            </div>
        </div>
    </div>

    <div id="view-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Vaccination Record Details</h2>
            </div>
            <div class="modal-body" id="view-modal-body">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeViewModal()">Close</button>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            checkEmptyState();
            const now = new Date();
            now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
            document.getElementById('vaccination_date').value = now.toISOString().slice(0, 16);
        });

        // Simplified Cascading Logic
        function loadBuildings(locId) {
            return new Promise((resolve) => {
                const bldgSel = document.getElementById('building_id');
                const penSel = document.getElementById('pen_id');
                const animalSel = document.getElementById('animal_id');
                
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
                                let opt = document.createElement('option');
                                opt.value = b.BUILDING_ID; opt.text = b.BUILDING_NAME;
                                bldgSel.add(opt);
                            });
                            bldgSel.disabled = false;
                        } else { bldgSel.innerHTML = '<option value="">No Buildings</option>'; }
                        resolve();
                    })
                    .catch(err => { console.error(err); bldgSel.innerHTML = '<option value="">Error</option>'; resolve(); });
            });
        }

        function loadPens(bldgId) {
            return new Promise((resolve) => {
                const penSel = document.getElementById('pen_id');
                const animalSel = document.getElementById('animal_id');
                
                penSel.innerHTML = '<option>Loading...</option>'; penSel.disabled = true;
                animalSel.innerHTML = '<option value="">Select Pen First</option>'; animalSel.disabled = true;

                if(!bldgId) { resolve(); return; }

                fetch(`../process/getPensByBuilding.php?building_id=${bldgId}`)
                    .then(r => r.json())
                    .then(data => {
                        penSel.innerHTML = '<option value="">Select Pen</option>';
                        if(data.pens && data.pens.length > 0) {
                            data.pens.forEach(p => {
                                let opt = document.createElement('option');
                                opt.value = p.PEN_ID; opt.text = p.PEN_NAME;
                                penSel.add(opt);
                            });
                            penSel.disabled = false;
                        } else { penSel.innerHTML = '<option value="">No Pens</option>'; }
                        resolve();
                    })
                    .catch(err => console.error(err));
            });
        }

        function loadAnimals(penId) {
            return new Promise((resolve) => {
                const animalSel = document.getElementById('animal_id');
                animalSel.innerHTML = '<option>Loading...</option>';
                animalSel.disabled = true;

                if(!penId) { resolve(); return; }

                fetch(`../process/getAnimalsByPen.php?pen_id=${penId}`)
                    .then(r => r.json())
                    .then(data => {
                        animalSel.innerHTML = '<option value="">Select Animal Tag</option>';
                        const list = data.animals || data.animal_record || []; 
                        
                        if(list.length > 0) {
                            list.forEach(a => {
                                let opt = document.createElement('option');
                                opt.value = a.ANIMAL_ID;
                                opt.text = a.TAG_NO;
                                animalSel.add(opt);
                            });
                            animalSel.disabled = false;
                        } else {
                            animalSel.innerHTML = '<option value="">No Active Animals</option>';
                        }
                        resolve();
                    })
                    .catch(err => { console.error(err); animalSel.innerHTML = '<option value="">Error</option>'; });
            });
        }

        function updateStockInfo() {
            const itemSelect = document.getElementById('vaccine_item_id'); 
            const selectedOption = itemSelect.options[itemSelect.selectedIndex];
            const stockInfo = document.getElementById('stock-info');
            const unitsSpan = document.getElementById('units_used_span');
            const unitIdInput = document.getElementById('unit_id');
            
            if (!selectedOption.value) {
                stockInfo.style.display = 'none';
                unitsSpan.textContent = '';
                unitIdInput.value = '';
                return;
            }

            const quantity = parseFloat(selectedOption.dataset.quantity) || 0;
            const units = selectedOption.dataset.units || 'units';
            const unitId = selectedOption.dataset.unitId || '';
            
            unitsSpan.textContent = `(${units})`;
            unitIdInput.value = unitId;
            
            let stockText = '📦 Available Stock: ' + quantity.toFixed(2) + ' ' + units;
            let isLowStock = quantity < 10;

            stockInfo.textContent = stockText;
            stockInfo.className = isLowStock ? 'stock-info low-stock' : 'stock-info';
            stockInfo.style.display = 'block';
        }

        function openAddModal() {
            document.getElementById('record-form').reset();
            document.getElementById('modal-title').innerText = "Add Vaccination Record";
            document.getElementById('vaccination_id').value = "";
            const now = new Date();
            now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
            document.getElementById('vaccination_date').value = now.toISOString().slice(0, 16);
            
            document.getElementById('location_id').value = "";
            document.getElementById('building_id').innerHTML = '<option value="">Select Loc First</option>';
            document.getElementById('building_id').disabled = true;
            document.getElementById('pen_id').innerHTML = '<option value="">Select Bldg First</option>';
            document.getElementById('pen_id').disabled = true;
            document.getElementById('animal_id').innerHTML = '<option value="">Select Pen First</option>';
            document.getElementById('animal_id').disabled = true;

            document.getElementById('stock-info').style.display = 'none';
            document.getElementById('modal').classList.add('show');
        }

        async function editRecord(btn) {
            try {
                const tr = btn.closest('tr');
                const data = JSON.parse(tr.getAttribute('data-row-json'));

                document.getElementById('modal-title').innerText = "Edit Vaccination Record";
                document.getElementById('vaccination_id').value = data.VACCINATION_ID;
                
                if(data.VACCINATION_DATE) {
                    const dt = new Date(data.VACCINATION_DATE);
                    dt.setMinutes(dt.getMinutes() - dt.getTimezoneOffset());
                    document.getElementById('vaccination_date').value = dt.toISOString().slice(0, 16);
                }
                
                document.getElementById('vet_name').value = data.VET_NAME || '';
                document.getElementById('cost').value = data.VACCINATION_COST || 0;
                document.getElementById('remarks').value = data.REMARKS || '';
                document.getElementById('quantity').value = data.QUANTITY || '';
                document.getElementById('unit_id').value = data.UNIT_ID || '';
                document.getElementById('vaccine_item_id').value = data.VACCINE_ITEM_ID;
                updateStockInfo();

                document.getElementById('location_id').value = data.LOCATION_ID || "";
                if(data.LOCATION_ID) {
                    await loadBuildings(data.LOCATION_ID);
                    document.getElementById('building_id').value = data.BUILDING_ID || "";
                }
                if(data.BUILDING_ID) {
                    await loadPens(data.BUILDING_ID);
                    document.getElementById('pen_id').value = data.PEN_ID || "";
                }
                if(data.PEN_ID) {
                    await loadAnimals(data.PEN_ID);
                    document.getElementById('animal_id').value = data.ANIMAL_ID;
                } else {
                    const animalSel = document.getElementById('animal_id');
                    animalSel.innerHTML = `<option value="${data.ANIMAL_ID}" selected>${data.TAG_NO}</option>`;
                    animalSel.disabled = false;
                }

                document.getElementById('modal').classList.add('show');
            } catch (e) { console.error("Error editing:", e); }
        }

        function saveRecord() {
            const form = document.getElementById('record-form');
            if (!form.checkValidity()) { form.reportValidity(); return; }

            const id = document.getElementById('vaccination_id').value;
            const url = id ? '../process/updateVaccination.php' : '../process/addVaccination.php';
            const btn = document.getElementById('btn-save');
            
            btn.disabled = true; btn.innerText = 'Saving...';
            
            document.getElementById('building_id').disabled = false;
            document.getElementById('pen_id').disabled = false;
            document.getElementById('animal_id').disabled = false;

            fetch(url, { method: 'POST', body: new FormData(form) })
            .then(r => r.json())
            .then(data => {
                alert(data.message);
                if(data.success) location.reload();
                else btn.disabled = false;
            })
            .catch(err => { console.error(err); alert("System error."); btn.disabled = false; });
        }

        function deleteRecord(id) {
            if(confirm("Are you sure?")) {
                const fd = new FormData(); fd.append('vaccination_id', id);
                fetch('../process/deleteVaccination.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(d => { alert(d.message); if(d.success) location.reload(); });
            }
        }

        function viewRecord(btn) {
            const tr = btn.closest('tr');
            const data = JSON.parse(tr.getAttribute('data-row-json'));
            const total = (parseFloat(data.VACCINATION_COST||0) + parseFloat(data.VACCINE_COST||0)).toFixed(2);
            
            let dateStr = '-';
            if(data.VACCINATION_DATE) {
                const dt = new Date(data.VACCINATION_DATE);
                dateStr = dt.toLocaleString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit', hour12: true });
            }

            const html = `
                <div class="info-group">
                    <h3>Record Info</h3>
                    <p><strong>ID:</strong> #${data.VACCINATION_ID}</p>
                    <p><strong>Tag:</strong> <span class="tag-badge">${data.TAG_NO}</span></p>
                    <p><strong>Date & Time:</strong> ${dateStr}</p>
                </div>
                <div class="info-group">
                    <h3>Vaccine Info</h3>
                    <p><strong>Item:</strong> ${data.VACCINE_NAME}</p>
                    <p><strong>Qty:</strong> ${data.QUANTITY} ${data.UNIT_ABBR}</p>
                    <p><strong>Vet:</strong> ${data.VET_NAME}</p>
                    <div style="margin-top:10px; padding-top:10px; border-top:1px dashed #475569">
                        <p><strong>Item Cost:</strong> ₱${parseFloat(data.VACCINE_COST||0).toFixed(2)}</p>
                        <p><strong>Service Fee:</strong> ₱${parseFloat(data.VACCINATION_COST||0).toFixed(2)}</p>
                        <p style="color:#fbbf24; font-size:1.1em"><strong>Total:</strong> ₱${total}</p>
                    </div>
                </div>`;
            document.getElementById('view-modal-body').innerHTML = html;
            document.getElementById('view-modal').classList.add('show');
        }

        function closeModal() { document.getElementById('modal').classList.remove('show'); }
        function closeViewModal() { document.getElementById('view-modal').classList.remove('show'); }
        
        function filterTable() { 
            const input = document.getElementById('searchInput');
            const filter = input.value.toLowerCase();
            const table = document.getElementById('transaction-table');
            const tr = table.getElementsByTagName('tr');
            let hasVisible = false;

            for (let i = 0; i < tr.length; i++) {
                const text = tr[i].textContent.toLowerCase();
                if (text.includes(filter)) {
                    tr[i].style.display = "";
                    hasVisible = true;
                } else {
                    tr[i].style.display = "none";
                }
            }
            document.getElementById('empty-state').style.display = hasVisible ? 'none' : 'block';
        }
        function checkEmptyState() { 
            const rows = document.querySelectorAll('#transaction-table tr');
            document.getElementById('empty-state').style.display = rows.length === 0 ? 'block' : 'none';
        }
    </script>
</body>
</html>