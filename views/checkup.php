<?php
// views/checkup.php
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

    // 1. Fetch Checkups
    $checkups_sql = "SELECT c.*, 
                            a.TAG_NO, 
                            a.LOCATION_ID, a.BUILDING_ID, a.PEN_ID,
                            at.ANIMAL_TYPE_NAME 
                      FROM CHECK_UPS c 
                      LEFT JOIN ANIMAL_RECORDS a ON c.ANIMAL_ID = a.ANIMAL_ID 
                      LEFT JOIN ANIMAL_TYPE at ON a.ANIMAL_TYPE_ID = at.ANIMAL_TYPE_ID
                      ORDER BY c.CHECKUP_DATE DESC";
    $stmt = $conn->prepare($checkups_sql);
    $stmt->execute();
    $checkups_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Fetch Locations
    $locations_sql = "SELECT LOCATION_ID, LOCATION_NAME FROM LOCATIONS ORDER BY LOCATION_NAME ASC";
    $stmt = $conn->prepare($locations_sql);
    $stmt->execute();
    $locations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Fetch Animal Types
    $animal_types_sql = "SELECT ANIMAL_TYPE_ID, ANIMAL_TYPE_NAME FROM ANIMAL_TYPE ORDER BY ANIMAL_TYPE_NAME ASC";
    $stmt = $conn->prepare($animal_types_sql);
    $stmt->execute();
    $animal_types_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 4. Retrieve Veterinarians
    $vets_sql = "SELECT FULL_NAME FROM VETERINARIANS WHERE IS_ACTIVE = 1 ORDER BY FULL_NAME ASC";
    $stmt = $conn->prepare($vets_sql);
    $stmt->execute();
    $vets_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $checkups_data = [];
    $locations = [];
    $animal_types_data = [];
    $vets_data = [];
    echo "<script>console.error('Database Error: " . addslashes($e->getMessage()) . "');</script>";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> <title>Animal Check-ups Management</title>
    <link rel="stylesheet" href="../css/purch_housing_facilities.css">
    <style>
        /* Base Styles */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif; background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); min-height: 100vh; color: white; }
        .container { max-width: 1400px; margin: 0 auto; padding: 2rem; }
        
        /* Header */
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; }
        .header-info h1 { font-size: 2.5rem; font-weight: bold; margin-bottom: 0.5rem; }
        .header-info p { color: #cbd5e1; }
        
        .add-btn { display: flex; align-items: center; gap: 0.5rem; background: linear-gradient(135deg, #2563eb, #9333ea); color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 0.5rem; font-weight: 600; cursor: pointer; transition: all 0.2s; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); }
        .add-btn:hover { background: linear-gradient(135deg, #1d4ed8, #7c3aed); transform: scale(1.05); }

        /* Filter Row */
        .filter-container { display: flex; gap: 15px; margin-bottom: 20px; flex-wrap: wrap; }
        .filter-select { padding: 12px 16px; background: rgba(15, 23, 42, 0.5); border: 1px solid rgba(148, 163, 184, 0.2); border-radius: 12px; color: #f9f9f9ff; font-size: 14px; backdrop-filter: blur(10px); cursor: pointer; transition: all 0.3s; min-width: 200px; flex: 1; }
        
        /* Search */
        .search-container { position: relative; margin-bottom: 2rem; }
        .search-input { width: 100%; padding: 1rem 1rem 1rem 3rem; background: rgba(30, 41, 59, 0.5); border: 1px solid #475569; border-radius: 0.5rem; color: white; font-size: 1rem; backdrop-filter: blur(10px); }
        .search-icon { position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: #94a3b8; width: 20px; height: 20px; }

        /* Table Styles (Updated for Horizontal Scrolling) */
        .table-container { 
            background: rgba(30, 41, 59, 0.5); 
            backdrop-filter: blur(10px); 
            border-radius: 0.75rem; 
            border: 1px solid #475569; 
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            /* Enable horizontal scroll */
            overflow-x: auto; 
            -webkit-overflow-scrolling: touch; 
        }
        .table { width: 100%; border-collapse: collapse; white-space: nowrap; /* Prevent wrapping */ }
        .table th { padding: 1rem 1.5rem; text-align: left; font-size: 0.875rem; font-weight: 600; color: #e2e8f0; text-transform: uppercase; letter-spacing: 0.05em; background: linear-gradient(135deg, #475569, #334155); }
        .table tbody tr { border-bottom: 1px solid #475569; transition: background-color 0.2s; }
        .table tbody tr:hover { background: rgba(55, 65, 81, 0.5); }
        .table td { padding: 1rem 1.5rem; vertical-align: middle; }

        /* Content Styles */
        .checkup-info { display: flex; flex-direction: column; gap: 4px; }
        .animal-name { font-weight: 600; color: #f9f9f9ff; font-size: 15px; }
        .animal-type { font-size: 13px; color: #94a3b8; }
        .date-badge { display: inline-block; padding: 6px 12px; background: linear-gradient(135deg, #3b82f6, #2563eb); color: white; border-radius: 8px; font-size: 13px; font-weight: 600; box-shadow: 0 2px 8px rgba(59, 130, 246, 0.3); }
        .vet-name { font-weight: 600; color: #10b981; }
        .remarks-text { color: #cbd5e1; font-size: 14px; max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .cost-text { font-weight: bold; color: #fbbf24; }

        /* Actions */
        .actions { display: flex; align-items: center; justify-content: center; gap: 0.5rem; }
        .action-btn { padding: 0.5rem; border: none; border-radius: 0.5rem; cursor: pointer; transition: all 0.2s; background: transparent; }
        .action-btn.view { color: #a78bfa; } .action-btn.view:hover { background: rgba(139, 92, 246, 0.2); }
        .action-btn.edit { color: #60a5fa; } .action-btn.edit:hover { background: rgba(59, 130, 246, 0.2); }
        .action-btn.delete { color: #f87171; } .action-btn.delete:hover { background: rgba(239, 68, 68, 0.2); }
        .icon { width: 18px; height: 18px; }

        /* Modals */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.75); backdrop-filter: blur(4px); z-index: 1000; align-items: center; justify-content: center; padding: 1rem; }
        .modal.show { display: flex; }
        .modal-content { background: #1e293b; border-radius: 20px; width: 100%; max-width: 650px; max-height: 90vh; display: flex; flex-direction: column; border: 1px solid rgba(148, 163, 184, 0.1); box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); animation: slideUp 0.3s ease; }
        .modal-header { padding: 1.5rem 2rem; border-bottom: 1px solid rgba(148, 163, 184, 0.1); flex-shrink: 0; }
        .modal-body { padding: 2rem; overflow-y: auto; flex-grow: 1; }
        .modal-footer { padding: 1.5rem 2rem; border-top: 1px solid rgba(148, 163, 184, 0.1); display: flex; justify-content: flex-end; gap: 1rem; flex-shrink: 0; background: #1e293b; border-radius: 0 0 20px 20px; }
        
        .form-group { display: flex; flex-direction: column; gap: 0.5rem; margin-bottom: 1rem; }
        .form-group label { color: #cbd5e1; font-size: 0.875rem; font-weight: 500; }
        .form-group input, .form-group select, .form-group textarea { padding: 0.75rem; background: #374151; border: 1px solid #4b5563; border-radius: 0.5rem; color: white; font-size: 1rem; }
        
        .form-row-cascading { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 15px; background: rgba(255, 255, 255, 0.03); padding: 15px; border-radius: 8px; border: 1px dashed rgba(148, 163, 184, 0.2); }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }

        .btn-cancel { padding: 0.5rem 1.5rem; background: transparent; border: none; color: #cbd5e1; cursor: pointer; }
        .btn-save { padding: 0.5rem 1.5rem; background: linear-gradient(135deg, #2563eb, #9333ea); border: none; border-radius: 0.5rem; color: white; font-weight: 600; cursor: pointer; }
        
        .empty-state { text-align: center; padding: 3rem 1rem; display: none; }
        .alert { padding: 1rem; border-radius: 8px; margin-bottom: 1rem; display: none; }
        .alert.show { display: block; }
        .alert.success { background: rgba(16, 185, 129, 0.15); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.3); }
        .alert.error { background: rgba(239, 68, 68, 0.15); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.3); }
        
        @keyframes slideUp { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }

        /* --- MOBILE RESPONSIVE CSS --- */
        @media (max-width: 768px) {
            .container { padding: 1rem; }
            
            /* Header Stack */
            .header { flex-direction: column; align-items: stretch; gap: 1rem; text-align: center; }
            .header-info h1 { font-size: 1.75rem; }
            .add-btn { width: 100%; justify-content: center; }

            /* Filter Stack */
            .filter-container { flex-direction: column; gap: 10px; }
            .filter-select { width: 100%; }

            /* FORCE HORIZONTAL SCROLL ON TABLE */
            .table { 
                min-width: 900px; /* Ensure table is wide enough to trigger scroll */
            }
            .remarks-text { white-space: normal; max-width: 200px; }

            /* Modal Forms Stack */
            .form-row, .form-row-cascading { grid-template-columns: 1fr; }
            .modal-content { width: 95%; margin: 0 auto; max-height: 90vh; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="header-info">
                <h1>Animal Check-ups</h1>
                <p>Track and manage veterinary check-ups for animals</p>
            </div>
            <button class="add-btn" onclick="openAddModal()">
                <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                Add Check-up
            </button>
        </div>

        <div class="filter-container">
            <select class="filter-select" id="animalTypeFilter" onchange="filterTable()">
                <option value="">All Animal Types</option>
                <?php foreach($animal_types_data as $type): ?>
                    <option value="<?php echo htmlspecialchars($type['ANIMAL_TYPE_NAME']); ?>">
                        <?php echo htmlspecialchars($type['ANIMAL_TYPE_NAME']); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select class="filter-select" id="dateFilter" onchange="filterTable()">
                <option value="">All Dates</option>
                <option value="today">Today</option>
                <option value="week">This Week</option>
                <option value="month">This Month</option>
            </select>
        </div>

        <div class="search-container">
            <svg class="search-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m21 21-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
            <input type="text" class="search-input" id="searchInput" placeholder="Search by animal name, vet name, or remarks..." onkeyup="filterTable()">
        </div>

        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Check-up ID</th>
                        <th>Animal</th>
                        <th>Veterinarian</th>
                        <th>Date & Time</th>
                        <th>Cost</th> 
                        <th>Remarks</th>
                        <th style="text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody id="checkup-table">
                    <?php foreach($checkups_data as $checkup): 
                        // Proper Date Handling
                        $dateObj = new DateTime($checkup['CHECKUP_DATE']);
                        $displayDate = $dateObj->format('M d, Y h:i A');
                        $isoDate = $dateObj->format('Y-m-d\TH:i'); 
                    ?>
                    <tr data-checkup-id="<?php echo $checkup['CHECK_UP_ID']; ?>"
                        data-animal-id="<?php echo $checkup['ANIMAL_ID']; ?>"
                        
                        data-location-id="<?php echo $checkup['LOCATION_ID']; ?>"
                        data-building-id="<?php echo $checkup['BUILDING_ID']; ?>"
                        data-pen-id="<?php echo $checkup['PEN_ID']; ?>"
                        
                        data-animal-name="<?php echo htmlspecialchars($checkup['TAG_NO'] ?? ''); ?>"
                        data-animal-type="<?php echo htmlspecialchars($checkup['ANIMAL_TYPE_NAME'] ?? ''); ?>"
                        data-vet-name="<?php echo htmlspecialchars($checkup['VET_NAME']); ?>"
                        data-checkup-date="<?php echo $isoDate; ?>" 
                        data-cost="<?php echo $checkup['COST']; ?>"
                        data-remarks="<?php echo htmlspecialchars($checkup['REMARKS'] ?? ''); ?>">
                        
                        <td><div class="item-id">CU-<?php echo str_pad($checkup['CHECK_UP_ID'], 4, '0', STR_PAD_LEFT); ?></div></td>
                        
                        <td>
                            <div class="checkup-info">
                                <span class="animal-name"><?php echo htmlspecialchars($checkup['TAG_NO'] ?? 'Unknown'); ?></span>
                                <span class="animal-type"><?php echo htmlspecialchars($checkup['ANIMAL_TYPE_NAME'] ?? 'N/A'); ?></span>
                            </div>
                        </td>
                        
                        <td><span class="vet-name"><?php echo htmlspecialchars($checkup['VET_NAME']); ?></span></td>
                        
                        <td><span class="date-badge"><?php echo $displayDate; ?></span></td>
                        
                        <td><span class="cost-text">₱<?php echo number_format($checkup['COST'], 2); ?></span></td>
                        
                        <td><div class="remarks-text"><?php echo htmlspecialchars($checkup['REMARKS'] ?? '-'); ?></div></td>
                        
                        <td>
                            <div class="actions">
                                <button class="action-btn view" onclick="viewCheckup(this)" title="View"><svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg></button>
                                <button class="action-btn edit" onclick="editCheckup(this)" title="Edit"><svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg></button>
                                <button class="action-btn delete" onclick="deleteCheckup(this)" title="Delete"><svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg></button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div id="empty-state" class="empty-state" style="display: <?php echo empty($checkups_data) ? 'block' : 'none'; ?>">
                <h3>No check-ups found</h3>
                <p>Try adjusting your filters or add a new check-up</p>
            </div>
        </div>
    </div>

    <div id="modal" class="modal">
        <div class="modal-content">
            <div class="modal-header"><h2 id="modal-title">Add New Check-up</h2></div>
            <div class="modal-body">
                <div id="modal-alert" class="alert"></div>
                <form id="checkup-form" method="POST">
                    <input type="hidden" id="checkup-id" name="checkup_id">
                    
                    <div class="info-group">
                        <h3>1. Select Animal</h3>
                        <p style="font-size:0.85rem; color:#94a3b8; margin-bottom:10px;">Filter locations to find the animal efficiently.</p>
                        
                        <div class="form-row-cascading">
                            <div class="form-group">
                                <label for="location_id">Location</label>
                                <select id="location_id" onchange="loadBuildings(this.value)">
                                    <option value="">Select...</option>
                                    <?php foreach($locations as $loc): ?>
                                        <option value="<?php echo $loc['LOCATION_ID']; ?>"><?php echo htmlspecialchars($loc['LOCATION_NAME']); ?></option>
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
                            <label for="animal-id">Animal <span>*</span></label>
                            <select id="animal-id" name="animal_id" required disabled>
                                <option value="">Select Pen First</option>
                            </select>
                        </div>

                        <h3>2. Check-up Details</h3>
                        <div class="form-group">
                            <label for="vet-name">Veterinarian Name <span>*</span></label>
                            <select id="vet-name" name="vet_name" required>
                                <option value="">Select Veterinarian</option>
                                <?php foreach($vets_data as $vet): ?>
                                    <option value="<?php echo htmlspecialchars($vet['FULL_NAME']); ?>"><?php echo htmlspecialchars($vet['FULL_NAME']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="checkup-date">Check-up Date & Time <span>*</span></label>
                                <input type="datetime-local" id="checkup-date" name="checkup_date" required>
                            </div>
                            <div class="form-group">
                                <label for="checkup-cost">Cost (Fee) <span>*</span></label>
                                <input type="number" id="checkup-cost" name="cost" step="0.01" min="0" placeholder="0.00" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="remarks">Remarks</label>
                            <textarea id="remarks" name="remarks" rows="3" placeholder="Enter observations..."></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeModal()">Cancel</button>
                <button type="button" class="btn-save" id="btn-save" onclick="saveCheckup()">Save Check-up</button>
            </div>
        </div>
    </div>

    <div id="view-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header"><h2>Check-up Details</h2></div>
            <div class="modal-body" id="view-modal-body"></div>
            <div class="modal-footer"><button type="button" class="btn-cancel" onclick="closeViewModal()">Close</button></div>
        </div>
    </div>

    <form id="deleteCheckupForm" method="POST" action="../process/deleteCheckup.php" style="display: none;">
        <input type="hidden" id="delete_checkup_id" name="checkup_id">
    </form>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            checkEmptyState();
            const now = new Date();
            now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
            document.getElementById('checkup-date').value = now.toISOString().slice(0,16);
        });

        function loadBuildings(locId) {
            return new Promise((resolve) => {
                const bldgSel = document.getElementById('building_id');
                const penSel = document.getElementById('pen_id');
                const animalSel = document.getElementById('animal-id');
                
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
                const animalSel = document.getElementById('animal-id');
                
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
                const animalSel = document.getElementById('animal-id');
                animalSel.innerHTML = '<option>Loading...</option>'; animalSel.disabled = true;

                if(!penId) { resolve(); return; }

                fetch(`../process/getAnimalsByPen.php?pen_id=${penId}`)
                    .then(r => r.json())
                    .then(data => {
                        animalSel.innerHTML = '<option value="">Select Animal</option>';
                        const list = data.animals || data.animal_record || []; 
                        
                        if(list.length > 0) {
                            list.forEach(a => {
                                if(a.IS_ACTIVE != 0) {
                                    animalSel.innerHTML += `<option value="${a.ANIMAL_ID}">${a.TAG_NO}</option>`;
                                }
                            });
                            animalSel.disabled = false;
                        } else { animalSel.innerHTML = '<option value="">No Animals</option>'; }
                        resolve();
                    });
            });
        }

        function openAddModal() {
            document.getElementById('checkup-form').reset();
            document.getElementById('checkup-id').value = '';
            document.getElementById('modal-title').textContent = 'Add New Check-up';
            document.getElementById('btn-save').textContent = 'Save Check-up';
            
            document.getElementById('location_id').value = "";
            document.getElementById('building_id').innerHTML = '<option value="">Select Loc First</option>';
            document.getElementById('building_id').disabled = true;
            document.getElementById('pen_id').innerHTML = '<option value="">Select Bldg First</option>';
            document.getElementById('pen_id').disabled = true;
            document.getElementById('animal-id').innerHTML = '<option value="">Select Pen First</option>';
            document.getElementById('animal-id').disabled = true;

            const now = new Date();
            now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
            document.getElementById('checkup-date').value = now.toISOString().slice(0,16);

            hideAlert();
            document.getElementById('modal').classList.add('show');
        }

        async function editCheckup(button) {
            const row = button.closest('tr');
            const d = row.dataset;
            
            document.getElementById('modal-title').textContent = 'Edit Check-up';
            document.getElementById('btn-save').textContent = 'Update Check-up';
            document.getElementById('checkup-id').value = d.checkupId;

            document.getElementById('location_id').value = d.locationId || "";
            
            if(d.locationId) {
                await loadBuildings(d.locationId);
                document.getElementById('building_id').value = d.buildingId || "";
            }

            if(d.buildingId) {
                await loadPens(d.buildingId);
                document.getElementById('pen_id').value = d.penId || "";
            }

            if(d.penId) {
                await loadAnimals(d.penId);
                document.getElementById('animal-id').value = d.animalId;
            } else {
                const animalSel = document.getElementById('animal-id');
                animalSel.innerHTML = `<option value="${d.animalId}" selected>${d.animalName}</option>`;
                animalSel.disabled = false;
            }

            document.getElementById('vet-name').value = d.vetName;
            document.getElementById('checkup-date').value = d.checkupDate; 
            document.getElementById('checkup-cost').value = d.cost;
            document.getElementById('remarks').value = d.remarks;
            
            hideAlert();
            document.getElementById('modal').classList.add('show');
        }

        function saveCheckup() {
            const form = document.getElementById('checkup-form');
            if (!form.checkValidity()) { form.reportValidity(); return; }

            const id = document.getElementById('checkup-id').value;
            const url = id ? '../process/editCheckup.php' : '../process/addCheckup.php';
            const btn = document.getElementById('btn-save');
            
            btn.disabled = true; btn.innerHTML = 'Saving...';
            
            document.getElementById('animal-id').disabled = false;

            fetch(url, { method: 'POST', body: new FormData(form) })
            .then(r => r.json()).then(data => {
                showAlert(data.message, data.success ? 'success' : 'error');
                if(data.success) { setTimeout(() => window.location.reload(), 1500); }
                else { btn.disabled = false; btn.textContent = id ? 'Update Check-up' : 'Save Check-up'; }
            });
        }

        function deleteCheckup(button) {
            const row = button.closest('tr');
            if(confirm(`Delete check-up for "${row.dataset.animalName}"?`)) {
                document.getElementById('delete_checkup_id').value = row.dataset.checkupId;
                document.getElementById('deleteCheckupForm').submit();
            }
        }

        function closeModal() { document.getElementById('modal').classList.remove('show'); }
        function closeViewModal() { document.getElementById('view-modal').classList.remove('show'); }
        function showAlert(msg, type) {
            const el = document.getElementById('modal-alert');
            el.textContent = msg; el.className = `alert show ${type}`; el.style.display = 'block';
        }
        function hideAlert() { document.getElementById('modal-alert').style.display = 'none'; }

        function viewCheckup(button) {
            const d = button.closest('tr').dataset;
            const row = button.closest('tr');
            const displayDate = row.querySelector('.date-badge').innerText; 

            const html = `
                <div class="info-group">
                    <h3>Basic Information</h3>
                    <p><strong>ID:</strong> CU-${String(d.checkupId).padStart(4, '0')}</p>
                    <p><strong>Animal:</strong> ${d.animalName} (${d.animalType})</p>
                    <p><strong>Vet:</strong> ${d.vetName}</p>
                    <p><strong>Date & Time:</strong> ${displayDate}</p>
                    <p><strong>Cost:</strong> ₱${parseFloat(d.cost).toFixed(2)}</p>
                </div>
                <div class="info-group">
                    <h3>Remarks</h3>
                    <p>${d.remarks || 'None'}</p>
                </div>`;
            document.getElementById('view-modal-body').innerHTML = html;
            document.getElementById('view-modal').classList.add('show');
        }

        function filterTable() {
            const search = document.getElementById('searchInput').value.toLowerCase();
            const typeFilter = document.getElementById('animalTypeFilter').value.toLowerCase();
            const dateFilter = document.getElementById('dateFilter').value;
            const rows = document.querySelectorAll('#checkup-table tr');
            const today = new Date(); today.setHours(0,0,0,0);

            let visible = 0;
            rows.forEach(r => {
                const txt = r.innerText.toLowerCase();
                const rType = r.dataset.animalType.toLowerCase();
                const rDate = new Date(r.dataset.checkupDate); rDate.setHours(0,0,0,0);
                
                let show = true;
                if(search && !txt.includes(search)) show = false;
                if(typeFilter && rType !== typeFilter) show = false;
                
                if(dateFilter) {
                    const diff = Math.floor((today - rDate)/(1000*60*60*24));
                    if(dateFilter === 'today' && diff !== 0) show = false;
                    else if(dateFilter === 'week' && diff > 7) show = false;
                    else if(dateFilter === 'month' && diff > 30) show = false;
                }

                r.style.display = show ? '' : 'none';
                if(show) visible++;
            });
            document.getElementById('empty-state').style.display = visible ? 'none' : 'block';
        }

        function checkEmptyState() {
            const count = document.querySelectorAll('#checkup-table tr').length;
            document.getElementById('empty-state').style.display = count ? 'none' : 'block';
        }
    </script>
</body>
</html>