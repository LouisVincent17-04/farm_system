<?php
// views/animal_records.php
error_reporting(0);
ini_set('display_errors', 0);
$page = "admin_dashboard"; // Active Tab

include '../common/navbar.php';
include '../config/Connection.php';
include '../security/checkRole.php';
checkRole(2);

// --- 1. HANDLING FILTERS ---
$filter_loc = $_GET['f_loc'] ?? '';
$filter_bld = $_GET['f_bld'] ?? '';
$filter_pen = $_GET['f_pen'] ?? '';

$animal_data = [];
$animal_types = [];
$locations = [];
$filter_buildings = [];
$filter_pens = [];

try {
    if (!isset($conn)) { throw new Exception("Database connection failed."); }

    // --- 2. FETCH DROPDOWN DATA ---
    $animal_types = $conn->query("SELECT * FROM Animal_Type ORDER BY ANIMAL_TYPE_NAME ASC")->fetchAll(PDO::FETCH_ASSOC);
    $locations = $conn->query("SELECT * FROM Locations ORDER BY LOCATION_NAME ASC")->fetchAll(PDO::FETCH_ASSOC);

    if ($filter_loc) {
        $stmt = $conn->prepare("SELECT * FROM Buildings WHERE LOCATION_ID = ? ORDER BY BUILDING_NAME");
        $stmt->execute([$filter_loc]);
        $filter_buildings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    if ($filter_bld) {
        $stmt = $conn->prepare("SELECT * FROM Pens WHERE BUILDING_ID = ? ORDER BY PEN_NAME");
        $stmt->execute([$filter_bld]);
        $filter_pens = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // --- 3. FETCH ANIMALS ---
    if (!empty($filter_loc) || !empty($filter_bld) || !empty($filter_pen)) {
        
        // UPDATED QUERY: Added FATHER_ID join
        $sql = "SELECT 
                    a.ANIMAL_ID, a.TAG_NO, a.SEX, a.BIRTH_DATE, a.CURRENT_STATUS, 
                    a.LOCATION_ID, a.BUILDING_ID, a.PEN_ID, a.ANIMAL_TYPE_ID, a.BREED_ID, a.ANIMAL_ITEM_ID,
                    a.WEIGHT_AT_BIRTH, a.CURRENT_ESTIMATED_WEIGHT, a.CURRENT_ACTUAL_WEIGHT, a.ACQUISITION_COST,
                    a.MOTHER_ID, a.FATHER_ID,
                    at.ANIMAL_TYPE_NAME, b.BREED_NAME, l.LOCATION_NAME, 
                    bld.BUILDING_NAME, p.PEN_NAME,
                    m.TAG_NO as MOTHER_TAG,
                    f.TAG_NO as FATHER_TAG,
                    DATEDIFF(NOW(), a.BIRTH_DATE) AS DAYS_OLD 
                FROM Animal_Records a
                LEFT JOIN Animal_Type at ON a.ANIMAL_TYPE_ID = at.ANIMAL_TYPE_ID
                LEFT JOIN Breeds b ON a.BREED_ID = b.BREED_ID
                LEFT JOIN Locations l ON a.LOCATION_ID = l.LOCATION_ID
                LEFT JOIN Buildings bld ON a.BUILDING_ID = bld.BUILDING_ID
                LEFT JOIN Pens p ON a.PEN_ID = p.PEN_ID
                LEFT JOIN Animal_Records m ON a.MOTHER_ID = m.ANIMAL_ID 
                LEFT JOIN Animal_Records f ON a.FATHER_ID = f.ANIMAL_ID 
                WHERE a.IS_ACTIVE = 1";

        $params = [];

        if ($filter_loc) { $sql .= " AND a.LOCATION_ID = ?"; $params[] = $filter_loc; }
        if ($filter_bld) { $sql .= " AND a.BUILDING_ID = ?"; $params[] = $filter_bld; }
        if ($filter_pen) { $sql .= " AND a.PEN_ID = ?"; $params[] = $filter_pen; }

        $sql .= " ORDER BY a.ANIMAL_ID DESC";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $animal_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (Exception $e) {
    echo "<script>console.error('Database Error: " . addslashes($e->getMessage()) . "');</script>";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Animal Record Management System</title>
    <style>
        /* --- CORE STYLES --- */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); min-height: 100vh; color: white; }
        .container { max-width: 1400px; margin: 0 auto; padding: 2rem; }
        
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; }
        .header-info h1 { font-size: 2.5rem; font-weight: bold; margin-bottom: 0.5rem; }
        .header-info p { color: #cbd5e1; }
        .header-buttons { display: flex; gap: 10px; }
        
        /* Buttons */
        .add-btn { display: flex; align-items: center; gap: 0.5rem; color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 0.5rem; font-weight: 600; cursor: pointer; transition: all 0.2s; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); }
        .add-btn:hover { transform: scale(1.05); }
        .btn-purchase { background: linear-gradient(135deg, #2563eb, #9333ea); }
        .btn-existing { background: linear-gradient(135deg, #f59e0b, #d97706); } 
        
        /* Filter Bar */
        .filter-bar { background: rgba(30, 41, 59, 0.6); border: 1px solid #475569; padding: 1.5rem; border-radius: 0.75rem; margin-bottom: 1.5rem; display: flex; gap: 1rem; align-items: flex-end; flex-wrap: wrap; }
        .filter-group { display: flex; flex-direction: column; gap: 0.4rem; flex: 1; min-width: 200px; }
        .filter-group label { font-size: 0.75rem; text-transform: uppercase; color: #94a3b8; font-weight: 600; }
        .filter-select { width: 100%; padding: 0.6rem; background: #0f172a; border: 1px solid #334155; color: white; border-radius: 0.5rem; }
        .btn-reset { padding: 0.6rem 1.5rem; background: transparent; border: 1px solid #475569; color: #94a3b8; border-radius: 0.5rem; text-decoration: none; font-weight: 600; display: flex; align-items: center; justify-content: center; }
        .btn-reset:hover { border-color: white; color: white; }

        /* Search */
        .search-container { position: relative; margin-bottom: 2rem; }
        .search-input { width: 100%; padding: 1rem 1rem 1rem 3rem; background: rgba(30, 41, 59, 0.5); border: 1px solid #475569; border-radius: 0.5rem; color: white; font-size: 1rem; }
        
        /* Table */
        .table-container { background: rgba(30, 41, 59, 0.5); border-radius: 0.75rem; border: 1px solid #475569; overflow: hidden; min-height: 200px; }
        .table { width: 100%; border-collapse: collapse; }
        .table thead { background: linear-gradient(135deg, #475569, #334155); }
        .table th { padding: 1rem 1.5rem; text-align: left; color: #e2e8f0; text-transform: uppercase; font-size: 0.875rem; font-weight: 600; }
        .table td { padding: 1rem 1.5rem; vertical-align: middle; border-bottom: 1px solid #475569; }
        
        .animal-details h3 { font-size: 1.125rem; font-weight: 600; margin-bottom: 0.25rem; }
        .animal-type-info { color: #cbd5e1; font-size: 0.875rem; }

        .status-badge { padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; }
        .status-badge.active { background: rgba(34, 197, 94, 0.2); color: #86efac; }
        .status-badge.sold { background: rgba(59, 130, 246, 0.2); color: #93c5fd; }
        .status-badge.deceased { background: rgba(107, 114, 128, 0.2); color: #d1d5db; }
        
        .actions { display: flex; align-items: center; justify-content: center; gap: 0.5rem; }
        .action-btn { padding: 0.5rem; border: none; border-radius: 0.5rem; cursor: pointer; background: transparent; }
        .action-btn.edit { color: #60a5fa; }
        .action-btn.delete { color: #f87171; }
        .action-btn.add-link { color: #86efac; background: rgba(34, 197, 94, 0.1); padding: 5px 10px; font-size: 0.8rem; font-weight: bold; border: 1px solid rgba(34, 197, 94, 0.2); }

        /* Modal Styles */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.6); backdrop-filter: blur(4px); z-index: 1000; padding: 1rem; }
        .modal.show { display: flex; align-items: center; justify-content: center; }
        .modal-content { background: #1e293b; border-radius: 0.75rem; width: 100%; max-width: 36rem; padding: 0; border: 1px solid #475569; }
        .modal-content.large { max-width: 50rem; }
        .modal-header { padding: 1.5rem; border-bottom: 1px solid #475569; }
        .modal-body { padding: 1.5rem; max-height: 70vh; overflow-y: auto; }
        
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem; }
        .form-group { display: flex; flex-direction: column; gap: 0.5rem; margin-bottom: 1rem; }
        .form-group.full-width { grid-column: 1 / -1; }
        
        .form-group label { color: #cbd5e1; font-size: 0.875rem; font-weight: 500; }
        .form-group input, .form-group select { padding: 0.75rem; background: #374151; border: 1px solid #4b5563; border-radius: 0.5rem; color: white; }
        
        .input-group { display: flex; gap: 10px; }
        .btn-select { background: #475569; color: white; border: none; padding: 0 1rem; border-radius: 0.5rem; cursor: pointer; white-space: nowrap; }
        
        .modal-footer { padding: 1.5rem; border-top: 1px solid #475569; display: flex; justify-content: flex-end; gap: 0.75rem; }
        .btn-save { padding: 0.5rem 1.5rem; background: linear-gradient(135deg, #2563eb, #9333ea); border: none; border-radius: 0.5rem; color: white; font-weight: 600; cursor: pointer; }
        .btn-cancel { padding: 0.5rem 1.5rem; background: transparent; color: #cbd5e1; border: none; cursor: pointer; }
        
        .empty-state { text-align: center; padding: 3rem; display: block; color: #94a3b8; }
        .alert { padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem; display: none; }
        .alert.success { background: rgba(34, 197, 94, 0.2); border: 1px solid #22c55e; color: #86efac; }
        .alert.error { background: rgba(239, 68, 68, 0.2); border: 1px solid #ef4444; color: #fca5a5; }
        
        .icon { width: 18px; height: 18px; }
        
        @media (max-width: 768px) {
            .header { flex-direction: column; gap: 1rem; text-align: center; }
            .header-buttons { flex-direction: column; width: 100%; }
            .filter-bar { flex-direction: column; align-items: stretch; }
            .table-container { overflow-x: auto; }
            .table { min-width: 900px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="header-info">
                <h1>Animal Record Management</h1>
                <p>Manage Individual Animal Records</p>
            </div>
            <div class="header-buttons">
                <button class="add-btn btn-purchase" onclick="openAddModal('purchase', 1)">Add Purchased Animal</button>
                <button class="add-btn btn-existing" onclick="openAddModal('existing', 0)">Add Existing Record</button>
            </div>
        </div>

        <form method="GET" class="filter-bar">
            <div class="filter-group">
                <label>1. Location</label>
                <select name="f_loc" class="filter-select" onchange="this.form.submit()">
                    <option value="">-- All Locations --</option>
                    <?php foreach ($locations as $loc): ?>
                        <option value="<?= $loc['LOCATION_ID'] ?>" <?= $filter_loc == $loc['LOCATION_ID'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($loc['LOCATION_NAME']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label>2. Building</label>
                <select name="f_bld" class="filter-select" onchange="this.form.submit()" <?= empty($filter_loc) ? 'disabled' : '' ?>>
                    <option value="">-- All Buildings --</option>
                    <?php foreach ($filter_buildings as $bld): ?>
                        <option value="<?= $bld['BUILDING_ID'] ?>" <?= $filter_bld == $bld['BUILDING_ID'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($bld['BUILDING_NAME']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label>3. Pen</label>
                <select name="f_pen" class="filter-select" onchange="this.form.submit()" <?= empty($filter_bld) ? 'disabled' : '' ?>>
                    <option value="">-- All Pens --</option>
                    <?php foreach ($filter_pens as $pen): ?>
                        <option value="<?= $pen['PEN_ID'] ?>" <?= $filter_pen == $pen['PEN_ID'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($pen['PEN_NAME']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <a href="animal_records.php" class="btn-reset">Reset</a>
        </form>

        <div class="search-container">
            <input type="text" class="search-input" placeholder="Search loaded records by tag number, type, breed..." onkeyup="filterTable()">
        </div>

        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Tag No</th>
                        <th>Type / Breed</th>
                        <th>Sex</th>
                        <th>Age</th> <th>Birth Date</th>
                        <th>Weight (kg)<br><small>Est / Act</small></th>
                        <th>Lineage (M/F)</th> <th>Status</th>
                        <th>Cost</th> <th>Location</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="animal-table">
                    <?php if (empty($animal_data)): ?>
                        <?php else: ?>
                        <?php foreach ($animal_data as $data): ?>
                            <tr data-id="<?php echo $data['ANIMAL_ID']; ?>">
                                <td>
                                    <div class="animal-details">
                                        <h3><?php echo htmlspecialchars($data['TAG_NO']); ?></h3>
                                    </div>
                                </td>
                                <td>
                                    <div class="animal-type-info">
                                        <?php echo htmlspecialchars($data['ANIMAL_TYPE_NAME']); ?><br>
                                        <small style="color:#94a3b8"><?php echo htmlspecialchars($data['BREED_NAME']); ?></small>
                                    </div>
                                </td>
                                <td><?php echo $data['SEX'] == 'M' ? 'Male' : 'Female'; ?></td>
                                <td style="color:#fcd34d; font-weight:600;">
                                    <?php echo $data['DAYS_OLD'] !== null ? $data['DAYS_OLD'] . " days" : "N/A"; ?>
                                </td>
                                <td><?php echo $data['BIRTH_DATE'] ? date('M d, Y', strtotime($data['BIRTH_DATE'])) : 'N/A'; ?></td>
                                <td>
                                    <span style="color:#60a5fa;"><?php echo number_format($data['CURRENT_ESTIMATED_WEIGHT'], 2); ?></span> / 
                                    <span style="color:#34d399;"><?php echo number_format($data['CURRENT_ACTUAL_WEIGHT'], 2); ?></span>
                                </td>
                                <td>
                                    <div style="font-size:0.85rem;">
                                        <span style="color: #f472b6;">SOW: <?php echo $data['MOTHER_TAG'] ? $data['MOTHER_TAG'] : '-'; ?></span><br>
                                        <span style="color: #60a5fa;">BOAR: <?php echo $data['FATHER_TAG'] ? $data['FATHER_TAG'] : '-'; ?></span>
                                    </div>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo strtolower($data['CURRENT_STATUS']); ?>">
                                        <?php echo htmlspecialchars($data['CURRENT_STATUS']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span style="color:#fbbf24;">₱<?php echo number_format($data['ACQUISITION_COST'], 2); ?></span>
                                </td>
                                <td><?php echo htmlspecialchars($data['LOCATION_NAME']); ?> - <?php echo htmlspecialchars($data['PEN_NAME']); ?></td>
                                <td>
                                    <div class="actions">
                                        <button class="action-btn edit" onclick="editAnimal(this)" title="Edit"><svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg></button>
                                        <button class="action-btn delete" onclick="deleteAnimal(this)" title="Delete"><svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg></button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            <div id="empty-state" class="empty-state" style="display: <?php echo empty($animal_data) ? 'block' : 'none'; ?>;">
                <?php if (empty($filter_loc)): ?>
                    <h3 style="color:#94a3b8;">Please select a Location to view records</h3>
                <?php else: ?>
                    <h3>No records found in this location</h3>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div id="addModal" class="modal">
        <div class="modal-content">
            <div class="modal-header"><h2 id="modal-title">Add Record</h2></div>
            <div class="modal-body">
                <div id="add-alert" class="alert"></div>
                <form id="addAnimalForm">
                    <input type="hidden" id="entry_type" name="entry_type" value="existing">
                    <input type="hidden" id="acquisition_type" name="acquisition_type" value="0">

                    <div id="lineage-group" class="form-group full-width" style="display:none; background: rgba(255,255,255,0.03); padding: 10px; border-radius: 8px;">
                        <label style="color: #cbd5e1; margin-bottom:10px; display:block;">Lineage (Optional)</label>
                        <div class="form-row">
                            <div class="form-group">
                                <label style="color: #f472b6;">Mother (Sow)</label>
                                <div class="input-group">
                                    <input type="hidden" id="add_mother_id" name="mother_id">
                                    <input type="text" id="display_mother_tag" placeholder="Select Sow..." readonly style="border-color: #f472b6;">
                                    <button type="button" class="btn-select" onclick="openSelectParentModal('sow')">🔍</button>
                                </div>
                            </div>
                            <div class="form-group">
                                <label style="color: #60a5fa;">Father (Boar)</label>
                                <div class="input-group">
                                    <input type="hidden" id="add_father_id" name="father_id">
                                    <input type="text" id="display_father_tag" placeholder="Select Boar..." readonly style="border-color: #60a5fa;">
                                    <button type="button" class="btn-select" onclick="openSelectParentModal('boar')">🔍</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="purchase-group" class="form-group full-width" style="display: none;">
                        <label>Linked Purchase Record *</label>
                        <div class="input-group">
                            <input type="hidden" id="add_animal_item_id" name="animal_item_id">
                            <input type="text" id="display_purchase_item" placeholder="Select a purchase record..." readonly>
                            <button type="button" class="btn-select" onclick="openSelectPurchaseModal()">Select Source</button>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Tag Number *</label>
                            <input type="text" id="add_tag_no" name="tag_no" required>
                        </div>
                        <div class="form-group">
                            <label>Sex *</label>
                            <select id="add_sex" name="sex" required>
                                <option value="M">Male</option>
                                <option value="F">Female</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Animal Type *</label>
                            <select id="add_animal_type" name="animal_type_id" required onchange="loadBreeds(this.value, 'add')">
                                <option value="">Select Type</option>
                                <?php foreach ($animal_types as $type): ?>
                                    <option value="<?php echo $type['ANIMAL_TYPE_ID']; ?>"><?php echo htmlspecialchars($type['ANIMAL_TYPE_NAME']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Breed *</label>
                            <select id="add_breed" name="breed_id" required disabled>
                                <option value="">Select Type First</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group" id="acquisition-cost-group"> <label style="color:#fbbf24;">Acquisition Cost (PHP)</label>
                            <input type="number" id="add_acquisition_cost" name="acquisition_cost" step="0.01" placeholder="0.00" style="border-color:#fbbf24;">
                        </div>
                        <div id="birth-date-group" class="form-group">
                            <label>Birth Date *</label>
                            <input type="date" id="add_birth_date" name="birth_date">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Status *</label>
                        <select id="add_status" name="current_status" required>
                            <option value="Active">Active</option>
                            <option value="Sold">Sold</option>
                            <option value="Deceased">Deceased</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Location *</label>
                        <select id="add_location" name="location_id" required onchange="loadBuildings(this.value, 'add')">
                            <option value="">Select Location</option>
                            <?php foreach ($locations as $loc): ?>
                                <option value="<?php echo $loc['LOCATION_ID']; ?>"><?php echo htmlspecialchars($loc['LOCATION_NAME']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Building *</label>
                            <select id="add_building" name="building_id" required disabled onchange="loadPens(this.value, 'add')">
                                <option value="">Select Location First</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Pen *</label>
                            <select id="add_pen" name="pen_id" required disabled>
                                <option value="">Select Building First</option>
                            </select>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn-cancel" onclick="closeAddModal()">Cancel</button>
                <button class="btn-save" id="btn-add-save" onclick="submitAddForm()">Save Record</button>
            </div>
        </div>
    </div>

    <div id="selectParentModal" class="modal">
        <div class="modal-content large">
            <div class="modal-header">
                <h2 id="parent-modal-title">Select Parent</h2>
            </div>
            <div class="modal-body">
                <div class="table-container">
                    <table class="table">
                        <thead><tr><th>Tag No</th><th>Breed</th><th>Location</th><th>Action</th></tr></thead>
                        <tbody id="parent-table-body"><tr><td colspan="4" style="text-align:center;">Loading...</td></tr></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer"><button class="btn-cancel" onclick="closeSelectParentModal()">Close</button></div>
        </div>
    </div>

    <div id="selectPurchaseModal" class="modal">
        <div class="modal-content large">
            <div class="modal-header"><h2>Select Purchase Record</h2></div>
            <div class="modal-body">
                <div class="table-container">
                    <table class="table">
                        <thead><tr><th>ID</th><th>Name</th><th>Cost</th><th>Location</th><th>Action</th></tr></thead>
                        <tbody id="add-purchase-table-body"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer"><button class="btn-cancel" onclick="closeSelectPurchaseModal()">Close</button></div>
        </div>
    </div>

    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="edit-modal-title">Edit Animal Record</h2>
            </div>
            <div class="modal-body">
                <div id="edit-alert" class="alert"></div>
                <form id="editAnimalForm">
                    <input type="hidden" id="edit_animal_id" name="animal_id">
                    <input type="hidden" id="edit_has_purchase" name="has_purchase" value="0">

                    <div class="form-group full-width" style="background: rgba(255,255,255,0.03); padding: 10px; border-radius: 8px;">
                        <label style="color: #cbd5e1; margin-bottom:10px; display:block;">Lineage</label>
                        <div class="form-row">
                            <div class="form-group">
                                <label style="color: #f472b6;">Mother (Sow)</label>
                                <div class="input-group">
                                    <input type="hidden" id="edit_mother_id" name="mother_id">
                                    <input type="text" id="edit_display_mother" placeholder="Select Sow..." readonly style="border-color: #f472b6;">
                                    <button type="button" class="btn-select" onclick="openSelectParentModal('sow', 'edit')">🔍</button>
                                </div>
                            </div>
                            <div class="form-group">
                                <label style="color: #60a5fa;">Father (Boar)</label>
                                <div class="input-group">
                                    <input type="hidden" id="edit_father_id" name="father_id">
                                    <input type="text" id="edit_display_father" placeholder="Select Boar..." readonly style="border-color: #60a5fa;">
                                    <button type="button" class="btn-select" onclick="openSelectParentModal('boar', 'edit')">🔍</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="edit-purchase-group" class="form-group full-width" style="display: none;">
                        <label for="edit_animal_item_id">Linked Purchase Record *</label>
                        <div class="input-group">
                            <input type="text" id="edit_animal_item_id" name="animal_item_id" placeholder="Select a purchase record..." readonly>
                            <button type="button" class="btn-select" onclick="openEditSelectPurchaseModal()">Change Source</button>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_tag_no">Tag Number *</label>
                            <input type="text" id="edit_tag_no" name="tag_no" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_sex">Sex *</label>
                            <select id="edit_sex" name="sex" required>
                                <option value="M">Male</option>
                                <option value="F">Female</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_animal_type">Animal Type *</label>
                            <select id="edit_animal_type" name="animal_type_id" required onchange="loadBreeds(this.value, 'edit')">
                                <option value="">Select Type</option>
                                <?php foreach ($animal_types as $type): ?>
                                    <option value="<?php echo $type['ANIMAL_TYPE_ID']; ?>"><?php echo htmlspecialchars($type['ANIMAL_TYPE_NAME']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="edit_breed">Breed *</label>
                            <select id="edit_breed" name="breed_id" required>
                                <option value="">Select Animal Type First</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label style="color:#fbbf24;">Acquisition Cost (PHP)</label>
                            <input type="number" id="edit_acquisition_cost" name="acquisition_cost" step="0.01" value="" style="border-color:#fbbf24; background:#1e293b; color:#94a3b8; cursor:not-allowed;" readonly>
                        </div>
                        <div id="edit-birth-date-group" class="form-group">
                            <label for="edit_birth_date">Birth Date *</label>
                            <input type="date" id="edit_birth_date" name="birth_date">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="edit_status">Current Status *</label>
                        <select id="edit_status" name="current_status" required>
                            <option value="Active">Active</option>
                            <option value="Sold">Sold</option>
                            <option value="Deceased">Deceased</option>
                        </select>
                    </div>

                    <div class="form-row" style="background: rgba(255,255,255,0.05); padding: 10px; border-radius: 8px;">
                        <div class="form-group">
                            <label for="edit_weight_birth">Weight @ Birth (kg)</label>
                            <input type="number" id="edit_weight_birth" name="weight_at_birth" step="0.01">
                        </div>
                        <div class="form-group">
                            <label for="edit_weight_actual">Actual Weight (kg)</label>
                            <input type="number" id="edit_weight_actual" name="current_actual_weight" step="0.01">
                        </div>
                        <div class="form-group">
                            <label for="edit_weight_est">Estimated Weight (kg)</label>
                            <input type="number" id="edit_weight_est" name="current_estimated_weight" step="0.01">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="edit_location">Location *</label>
                        <select id="edit_location" name="location_id" required onchange="loadBuildings(this.value, 'edit')">
                            <option value="">Select Location</option>
                            <?php foreach ($locations as $location): ?>
                                <option value="<?php echo $location['LOCATION_ID']; ?>"><?php echo htmlspecialchars($location['LOCATION_NAME']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_building">Building *</label>
                            <select id="edit_building" name="building_id" required onchange="loadPens(this.value, 'edit')">
                                <option value="">Select Location First</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="edit_pen">Pen *</label>
                            <select id="edit_pen" name="pen_id" required>
                                <option value="">Select Building First</option>
                            </select>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeEditModal()">Cancel</button>
                <button type="button" class="btn-save" id="btn-edit-save" onclick="submitEditForm()">Save Changes</button>
            </div>
        </div>
    </div>

    <div id="editSelectPurchaseModal" class="modal">
        <div class="modal-content large">
            <div class="modal-header">
                <h2>Change Purchase Record</h2>
            </div>
            <div class="modal-body">
                <div class="table-container">
                    <table class="table">
                        <thead><tr><th>ID</th><th>Name</th><th>Cost</th><th>Location</th><th>Action</th></tr></thead>
                        <tbody id="edit-purchase-table-body"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeEditSelectPurchaseModal()">Close</button>
            </div>
        </div>
    </div>

    <script>
        // --- MODAL CONTROLLERS ---
        let acquisition_type = 0;
        // PARENT SELECTION VARIABLES
        let currentParentMode = ''; // 'add' or 'edit'
        let currentParentType = ''; // 'sow' or 'boar'

        function openAddModal(type, acquisition = 0) {
            const form = document.getElementById('addAnimalForm');
            form.reset();
            
            document.getElementById('add_breed').innerHTML = '<option value="">Select Type First</option>';
            document.getElementById('add_breed').disabled = true;
            document.getElementById('add_building').innerHTML = '<option value="">Select Location First</option>';
            document.getElementById('add_building').disabled = true;
            document.getElementById('add_pen').innerHTML = '<option value="">Select Building First</option>';
            document.getElementById('add_pen').disabled = true;

            const modalTitle = document.getElementById('modal-title');
            const purchaseGroup = document.getElementById('purchase-group');
            const lineageGroup = document.getElementById('lineage-group');
            const birthGroup = document.getElementById('birth-date-group');
            const costGroup = document.getElementById('acquisition-cost-group'); 
            const entryType = document.getElementById('entry_type');

            acquisition_type = acquisition;
            if(document.getElementById('acquisition_type')) {
                document.getElementById('acquisition_type').value = acquisition;
            }
            document.getElementById('add_acquisition_cost').value = '';

            if (type === 'purchase') {
                modalTitle.textContent = 'Add Purchased Animal';
                entryType.value = 'purchase';
                purchaseGroup.style.display = 'block';
                birthGroup.style.display = 'none';
                costGroup.style.display = 'block'; 
                lineageGroup.style.display = 'none'; // No parents needed for purchase usually
                
            } else if (type === 'existing') {
                modalTitle.textContent = 'Add Existing Record';
                entryType.value = 'existing';
                purchaseGroup.style.display = 'none';
                birthGroup.style.display = 'flex';
                costGroup.style.display = 'block'; 
                lineageGroup.style.display = 'block'; // Show Parent Selectors
                document.getElementById('add_birth_date').value = '';
            }

            document.getElementById('addModal').classList.add('show');
        }

        function closeAddModal() { document.getElementById('addModal').classList.remove('show'); }

        // --- PARENT SELECTION LOGIC ---
        function openSelectParentModal(type, mode = 'add') {
            currentParentType = type; // 'sow' or 'boar'
            currentParentMode = mode; // 'add' or 'edit'
            
            document.getElementById('selectParentModal').classList.add('show');
            document.getElementById('parent-modal-title').textContent = type === 'sow' ? 'Select Mother' : 'Select Father';
            loadAvailableParents(type);
        }
        function closeSelectParentModal() { document.getElementById('selectParentModal').classList.remove('show'); }

        function loadAvailableParents(type) {
            const tbody = document.getElementById('parent-table-body');
            tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;">Loading...</td></tr>';
            
            // Re-using logic: You need a generic script or two scripts. 
            // I will assume getAvailableSows.php exists and getAvailableBoars.php exists
            // Or simple logic within one script. For now, let's target specific files.
            
            const script = type === 'sow' ? '../process/getAvailableSows.php' : '../process/getAvailableBoars.php';
            
            fetch(script).then(res => res.json()).then(data => {
                const list = data.sows || data.boars || []; // Handle different key names
                if (data.success && list.length > 0) {
                    tbody.innerHTML = list.map(s => `
                        <tr>
                            <td style="font-weight:bold; color:${type==='sow'?'#f472b6':'#60a5fa'};">${s.TAG_NO}</td>
                            <td>${s.BREED_NAME}</td>
                            <td>${s.LOCATION_NAME} - ${s.PEN_NAME}</td>
                            <td><button class="action-btn add-link" onclick="selectParent('${s.ANIMAL_ID}', '${s.TAG_NO}')" style="border:none;">SELECT</button></td>
                        </tr>`).join('');
                } else { tbody.innerHTML = `<tr><td colspan="4">No active ${type}s found.</td></tr>`; }
            })
            .catch(err => {
                console.error(err);
                tbody.innerHTML = '<tr><td colspan="4">Error loading data (ensure getAvailableBoars.php exists).</td></tr>'; 
            });
        }

        function selectParent(id, tag) {
            const prefix = currentParentMode === 'add' ? 'add_' : 'edit_';
            const displayPrefix = currentParentMode === 'add' ? 'display_' : 'edit_display_';
            
            if (currentParentType === 'sow') {
                document.getElementById(prefix + 'mother_id').value = id;
                document.getElementById(displayPrefix + 'mother_tag' + (currentParentMode==='edit'?'':'')).value = tag; // ID fix
                if(currentParentMode === 'edit') document.getElementById('edit_display_mother').value = tag;
                else document.getElementById('display_mother_tag').value = tag;
            } else {
                document.getElementById(prefix + 'father_id').value = id;
                if(currentParentMode === 'edit') document.getElementById('edit_display_father').value = tag;
                else document.getElementById('display_father_tag').value = tag;
            }
            closeSelectParentModal();
        }

        // --- PURCHASE SELECTION LOGIC (unchanged) ---
        function loadAvailablePurchases(targetBodyId) {
            const tbody = document.getElementById(targetBodyId);
            tbody.innerHTML = '<tr><td colspan="5">Loading...</td></tr>';
            fetch('../process/getAvailablePurchasedAnimals.php').then(r=>r.json()).then(data=>{
                if(data.success && data.items.length > 0) {
                    tbody.innerHTML = data.items.map(i => {
                        const funcName = targetBodyId === 'add-purchase-table-body' ? 'selectPurchaseItem' : 'selectEditPurchaseItem';
                        return `<tr>
                            <td>${i.ITEM_ID}</td><td>${i.ITEM_NAME}</td><td>${i.UNIT_COST}</td><td>${i.LOCATION_NAME}</td>
                            <td><button class="action-btn add-link" onclick="${funcName}('${i.ITEM_ID}', '${i.LOCATION_ID}', '${i.BUILDING_ID}', '${i.PEN_ID}', '${i.ITEM_NAME}', '${i.UNIT_COST}')">SELECT</button></td>
                        </tr>`;
                    }).join('');
                } else { tbody.innerHTML = '<tr><td colspan="5">No items found</td></tr>'; }
            });
        }

        function openSelectPurchaseModal() {
            document.getElementById('selectPurchaseModal').classList.add('show');
            loadAvailablePurchases('add-purchase-table-body');
        }
        function closeSelectPurchaseModal() { document.getElementById('selectPurchaseModal').classList.remove('show'); }
        
        function selectPurchaseItem(id, loc, bldg, pen, name, cost) {
            document.getElementById('add_animal_item_id').value = id;
            document.getElementById('display_purchase_item').value = name;
            document.getElementById('add_acquisition_cost').value = cost;

            if(loc) {
                document.getElementById('add_location').value = loc;
                loadBuildings(loc, 'add').then(() => {
                    if(bldg) {
                        document.getElementById('add_building').value = bldg;
                        loadPens(bldg, 'add').then(() => {
                            if(pen) document.getElementById('add_pen').value = pen;
                        });
                    }
                });
            }
            closeSelectPurchaseModal();
        }

        // --- EDIT PURCHASE SELECTION LOGIC ---
        function openEditSelectPurchaseModal() {
            document.getElementById('editSelectPurchaseModal').classList.add('show');
            loadAvailablePurchases('edit-purchase-table-body');
        }
        function closeEditSelectPurchaseModal() { document.getElementById('editSelectPurchaseModal').classList.remove('show'); }
        
        function selectEditPurchaseItem(id, loc, bldg, pen, name, cost) {
            document.getElementById('edit_animal_item_id').value = id;
            document.getElementById('edit_acquisition_cost').value = cost;

            if(loc) {
                document.getElementById('edit_location').value = loc;
                loadBuildings(loc, 'edit').then(() => {
                    if(bldg) {
                        document.getElementById('edit_building').value = bldg;
                        loadPens(bldg, 'edit').then(() => {
                            if(pen) document.getElementById('edit_pen').value = pen;
                        });
                    }
                });
            }
            closeEditSelectPurchaseModal();
        }

        // --- FORM SUBMISSION ---
        function submitAddForm() {
            const form = document.getElementById('addAnimalForm');
            const formData = new FormData(form);
            const btn = document.getElementById('btn-add-save');

            if(document.getElementById('acquisition_type')) {
                document.getElementById('acquisition_type').value = acquisition_type;
                formData.set('acquisition_type', acquisition_type); 
            }

            if (!form.checkValidity()) { form.reportValidity(); return; }
            btn.disabled = true; btn.innerHTML = 'Saving...';
            fetch('../process/addAnimalRecord.php', { method: 'POST', body: formData })
            .then(res => res.json()).then(data => {
                if (data.success) {
                    showAlert('add', data.message, 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showAlert('add', data.message, 'error');
                    btn.disabled = false; btn.innerHTML = 'Save Record';
                }
            });
        }

        function submitEditForm() {
            const form = document.getElementById('editAnimalForm');
            const formData = new FormData(form);
            const btn = document.getElementById('btn-edit-save');
            if (!form.checkValidity()) { form.reportValidity(); return; }
            btn.disabled = true; btn.innerHTML = 'Saving...';
            fetch('../process/editAnimalRecord.php', { method: 'POST', body: formData })
            .then(res => res.json()).then(data => {
                if (data.success) {
                    showAlert('edit', data.message, 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showAlert('edit', data.message, 'error');
                    btn.disabled = false; btn.innerHTML = 'Save Changes';
                }
            });
        }

        // --- EDIT ANIMAL ---
        async function editAnimal(button) {
            const row = button.closest('tr');
            const animalId = row.getAttribute('data-id');
            document.getElementById('editModal').classList.add('show');
            
            try {
                const response = await fetch(`../process/getAnimalDetails.php?animal_id=${animalId}`);
                const data = await response.json();

                if (data.success) {
                    const animal = data.data;
                    document.getElementById('edit_animal_id').value = animal.ANIMAL_ID;
                    document.getElementById('edit_tag_no').value = animal.TAG_NO;
                    document.getElementById('edit_sex').value = animal.SEX;
                    document.getElementById('edit_status').value = animal.CURRENT_STATUS;
                    
                    document.getElementById('edit_weight_birth').value = animal.WEIGHT_AT_BIRTH || '';
                    document.getElementById('edit_weight_actual').value = animal.CURRENT_ACTUAL_WEIGHT || '';
                    document.getElementById('edit_weight_est').value = animal.CURRENT_ESTIMATED_WEIGHT || '';
                    document.getElementById('edit_acquisition_cost').value = animal.ACQUISITION_COST || '';

                    // Lineage
                    document.getElementById('edit_mother_id').value = animal.MOTHER_ID || '';
                    document.getElementById('edit_father_id').value = animal.FATHER_ID || '';
                    // Note: Ideally backend returns Tag No for display, assumes getAnimalDetails provides this or handled separately
                    // For now leaving text empty if not provided in JSON response (requires getAnimalDetails update to return TAG names)
                    
                    document.getElementById('edit_animal_type').value = animal.ANIMAL_TYPE_ID;
                    await loadBreeds(animal.ANIMAL_TYPE_ID, 'edit');
                    
                    setTimeout(() => {
                         document.getElementById('edit_breed').value = animal.BREED_ID;
                    }, 50);

                    if (animal.ANIMAL_ITEM_ID) {
                        document.getElementById('edit-purchase-group').style.display = 'block';
                        document.getElementById('edit_animal_item_id').value = animal.ANIMAL_ITEM_ID; 
                        document.getElementById('edit_has_purchase').value = "1";
                        document.getElementById('edit-birth-date-group').style.display = 'none';
                    } else {
                        document.getElementById('edit-purchase-group').style.display = 'none';
                        document.getElementById('edit_has_purchase').value = "0";
                        document.getElementById('edit-birth-date-group').style.display = 'block';
                        document.getElementById('edit_birth_date').value = animal.BIRTH_DATE || '';
                    }

                    document.getElementById('edit_location').value = animal.LOCATION_ID;
                    
                    if (animal.LOCATION_ID) {
                        await loadBuildings(animal.LOCATION_ID, 'edit');
                        document.getElementById('edit_building').value = animal.BUILDING_ID;
                        
                        if (animal.BUILDING_ID) {
                            await loadPens(animal.BUILDING_ID, 'edit');
                            document.getElementById('edit_pen').value = animal.PEN_ID;
                        }
                    }
                }
            } catch (e) {
                console.error("Error populating edit modal:", e);
                alert("Failed to load animal details.");
            }
        }

        function closeEditModal() { document.getElementById('editModal').classList.remove('show'); }

        // --- DELETE ---
        function deleteAnimal(button) {
            if(!confirm("Permanently delete this animal record?")) return;
            const row = button.closest('tr');
            const id = row.getAttribute('data-id');
            const fd = new FormData(); fd.append('animal_id', id);
            
            fetch('../process/deleteAnimalRecord.php', { method:'POST', body:fd })
            .then(r=>r.json()).then(data => {
                if(data.success) {
                    alert(data.message);
                    row.remove();
                    checkEmptyState();
                } else {
                    alert("Error: " + data.message);
                }
            });
        }

        // --- UTILS (Promisified) ---
        function loadBreeds(id, mode) {
            return new Promise(resolve => {
                fetch('../process/getBreedsByAnimalType.php?animal_type_id='+id)
                .then(r=>r.json()).then(d=>{
                    const sel = document.getElementById(mode+'_breed');
                    sel.innerHTML = '<option value="">Select Breed</option>';
                    if(d.breeds) d.breeds.forEach(b => sel.innerHTML += `<option value="${b.BREED_ID}">${b.BREED_NAME}</option>`);
                    sel.disabled = false;
                    resolve();
                })
                .catch(() => resolve());
            });
        }
        
        function loadBuildings(id, mode) {
            return new Promise(resolve => {
                fetch('../process/getBuildingsByLocation.php?location_id='+id)
                .then(r=>r.json()).then(d=>{
                    const sel = document.getElementById(mode+'_building');
                    sel.innerHTML = '<option value="">Select Building</option>';
                    if(d.buildings) d.buildings.forEach(b => sel.innerHTML += `<option value="${b.BUILDING_ID}">${b.BUILDING_NAME}</option>`);
                    sel.disabled = false;
                    resolve();
                })
                .catch(() => resolve());
            });
        }

        function loadPens(id, mode) {
            return new Promise(resolve => {
                fetch('../process/getPensByBuilding.php?building_id='+id)
                .then(r=>r.json()).then(d=>{
                    const sel = document.getElementById(mode+'_pen');
                    sel.innerHTML = '<option value="">Select Pen</option>';
                    if(d.pens) d.pens.forEach(p => sel.innerHTML += `<option value="${p.PEN_ID}">${p.PEN_NAME}</option>`);
                    sel.disabled = false;
                    resolve();
                })
                .catch(() => resolve());
            });
        }

        function filterTable() {
            const term = document.querySelector('.search-input').value.toLowerCase();
            const rows = document.querySelectorAll('#animal-table tr');
            let visible = 0;
            rows.forEach(r => {
                if(r.innerText.toLowerCase().includes(term)) { r.style.display=''; visible++; }
                else { r.style.display='none'; }
            });
            checkEmptyState(visible);
        }

        function checkEmptyState(count) {
            const el = document.getElementById('empty-state');
            if (count === undefined) { } else {
               el.style.display = (count === 0) ? 'block' : 'none';
            }
        }

        function showAlert(mode, msg, type) {
            const el = document.getElementById(mode+'-alert');
            el.textContent = msg; el.className = 'alert ' + type; el.style.display='block';
        }
        function hideAlert(mode) { document.getElementById(mode+'-alert').style.display='none'; }

        // Click outside
        document.getElementById('addModal').addEventListener('click', function(e) { if(e.target===this) closeAddModal(); });
        document.getElementById('editModal').addEventListener('click', function(e) { if(e.target===this) closeEditModal(); });
        document.getElementById('selectPurchaseModal').addEventListener('click', function(e) { if(e.target===this) closeSelectPurchaseModal(); });
        document.getElementById('editSelectPurchaseModal').addEventListener('click', function(e) { if(e.target===this) closeEditSelectPurchaseModal(); });
        document.getElementById('selectParentModal').addEventListener('click', function(e) { if(e.target===this) closeSelectParentModal(); });

        document.addEventListener('DOMContentLoaded', () => {
            const rows = document.querySelectorAll('#animal-table tr');
            if(rows.length === 0) document.getElementById('empty-state').style.display = 'block';
        });
    </script>
</body>
</html>