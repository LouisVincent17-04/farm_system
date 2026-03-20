<?php
// views/animal_records.php
error_reporting(0);
ini_set('display_errors', 0);
$page = "admin_dashboard"; // Active Tab
include '../config/Connection.php';

include '../security/checkAccess.php';
checkAccess('animal_record');

include '../common/navbar.php';
include '../common/chat_support.php';
include '../functions/getUsersLocation.php'; // ADDED LOCATION FUNCTION

// --- 1. HANDLING FILTERS ---
// Auto-assign location filter if user is restricted
$filter_loc = ($USER_LOCATION_ != 1000) ? $USER_LOCATION_ : ($_GET['f_loc'] ?? '');
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
    
    // Filter Locations dropdown based on user access
    if ($USER_LOCATION_ != 1000) {
        $loc_stmt = $conn->prepare("SELECT * FROM Locations WHERE LOCATION_ID = ? ORDER BY LOCATION_NAME ASC");
        $loc_stmt->execute([$USER_LOCATION_]);
        $locations = $loc_stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $locations = $conn->query("SELECT * FROM Locations ORDER BY LOCATION_NAME ASC")->fetchAll(PDO::FETCH_ASSOC);
    }

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
        
        $sql = "SELECT 
                    a.ANIMAL_ID, a.TAG_NO, a.SEX, a.BIRTH_DATE, a.CURRENT_STATUS, 
                    a.LOCATION_ID, a.BUILDING_ID, a.PEN_ID, a.ANIMAL_TYPE_ID, a.BREED_ID, a.ANIMAL_ITEM_ID,
                    a.WEIGHT_AT_BIRTH, a.CURRENT_ESTIMATED_WEIGHT, a.CURRENT_ACTUAL_WEIGHT, a.ACQUISITION_COST,
                    a.MOTHER_ID, a.FATHER_ID,
                    at.ANIMAL_TYPE_NAME, b.BREED_NAME, l.LOCATION_NAME, 
                    ac.STAGE_NAME,  
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
                LEFT JOIN animal_classifications ac ON a.CLASS_ID = ac.CLASS_ID 
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Animal Record Management System</title>
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" type="text/css" href="https://npmcdn.com/flatpickr/dist/themes/dark.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    <style>
        /* --- CORE STYLES --- */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); min-height: 100vh; color: white; padding-bottom: 80px; }
        .container { max-width: 1400px; margin: 0 auto; padding: 2rem; width: 100%; }
        
        /* --- BACK LINK STYLE --- */
        .back-link {
            display: inline-flex; align-items: center; gap: 8px; 
            text-decoration: none; color: #94a3b8; font-weight: 600; 
            font-size: 0.95rem; margin-bottom: 20px; transition: color 0.2s;
        }
        .back-link:hover { color: white; }

        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; gap: 1rem; flex-wrap: wrap; }
        .header-info h1 { font-size: clamp(1.8rem, 4vw, 2.5rem); font-weight: bold; margin-bottom: 0.5rem; line-height: 1.2; }
        .header-info p { color: #cbd5e1; font-size: 0.95rem; }
        .header-buttons { display: flex; gap: 10px; flex-wrap: wrap; }
        
        /* Buttons */
        .add-btn { display: flex; align-items: center; justify-content: center; gap: 0.5rem; color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.2s; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); font-size: 0.95rem; white-space: nowrap; }
        .add-btn:hover { transform: translateY(-2px); }
        .btn-purchase { background: linear-gradient(135deg, #2563eb, #9333ea); }
        .btn-existing { background: linear-gradient(135deg, #f59e0b, #d97706); } 
        
        /* Filter Bar */
        .filter-bar { background: rgba(30, 41, 59, 0.6); border: 1px solid #475569; padding: 1.5rem; border-radius: 12px; margin-bottom: 1.5rem; display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; align-items: end; }
        .filter-group { display: flex; flex-direction: column; gap: 0.4rem; }
        .filter-group label { font-size: 0.85rem; text-transform: uppercase; color: #94a3b8; font-weight: 600; }
        .filter-select { width: 100%; padding: 12px; background: #0f172a; border: 1px solid #334155; color: white; border-radius: 8px; font-size: 0.95rem; outline: none; transition: border-color 0.2s;}
        .filter-select:focus { border-color: #3b82f6; }
        .btn-reset { padding: 12px 24px; background: transparent; border: 1px solid #475569; color: #94a3b8; border-radius: 8px; text-decoration: none; font-weight: 600; display: flex; align-items: center; justify-content: center; white-space: nowrap; transition: 0.2s;}
        .btn-reset:hover { background: rgba(255,255,255,0.05); color: white; border-color: white;}

        /* Search */
        .search-container { position: relative; margin-bottom: 2rem; }
        .search-input { width: 100%; padding: 14px; background: rgba(30, 41, 59, 0.5); border: 1px solid #475569; border-radius: 8px; color: white; font-size: 1rem; outline: none; transition: border-color 0.2s;}
        .search-input:focus { border-color: #3b82f6; }
        
        /* Table */
        .table-container { background: rgba(30, 41, 59, 0.5); border-radius: 12px; border: 1px solid #475569; overflow-x: auto; min-height: 200px; }
        .table { width: 100%; border-collapse: collapse; min-width: 1100px; }
        .table thead { background: rgba(15, 23, 42, 0.5); }
        .table th { padding: 1rem 1.5rem; text-align: left; color: #e2e8f0; text-transform: uppercase; font-size: 0.85rem; font-weight: 600; white-space: nowrap; border-bottom: 1px solid #475569;}
        .table td { padding: 1rem 1.5rem; vertical-align: middle; border-bottom: 1px solid rgba(255,255,255,0.05); font-size: 0.95rem; color: #cbd5e1;}
        .table tbody tr:hover { background: rgba(255,255,255,0.02); }
        
        .animal-details h3 { font-size: 1rem; font-weight: 600; margin-bottom: 0.25rem; color: #fff;}
        .animal-type-info { color: #cbd5e1; font-size: 0.875rem; }

        .status-badge { padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; display: inline-block; white-space: nowrap; }
        .status-badge.active { background: rgba(34, 197, 94, 0.1); color: #34d399; border: 1px solid rgba(34, 197, 94, 0.2);}
        .status-badge.sold { background: rgba(59, 130, 246, 0.1); color: #60a5fa; border: 1px solid rgba(59, 130, 246, 0.2);}
        .status-badge.deceased { background: rgba(107, 114, 128, 0.1); color: #94a3b8; border: 1px solid rgba(107, 114, 128, 0.2);}
        
        .actions { display: flex; align-items: center; justify-content: center; gap: 8px; }
        .action-btn { padding: 8px; border: 1px solid rgba(255,255,255,0.1); border-radius: 6px; cursor: pointer; background: rgba(255,255,255,0.05); display: flex; align-items: center; justify-content: center; transition: 0.2s;}
        .action-btn:hover { background: rgba(255,255,255,0.1); }
        .action-btn.edit { color: #60a5fa; } .action-btn.edit:hover { color: #93c5fd; border-color: #3b82f6; background: rgba(59, 130, 246, 0.2);}
        .action-btn.delete { color: #f87171; } .action-btn.delete:hover { color: #fca5a5; border-color: #ef4444; background: rgba(239, 68, 68, 0.2);}

        /* Modal Styles */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.7); backdrop-filter: blur(4px); z-index: 1000; align-items: center; justify-content: center; padding: 1rem; overflow-y: auto; }
        
        /* Add higher z-index for sub-modals so they stack correctly! */
        #selectParentModal, #selectPurchaseModal, #editSelectPurchaseModal { z-index: 1050; }

        .modal.show { display: flex; }
        .modal-content { background: #1e293b; border-radius: 16px; width: 100%; max-width: 700px; padding: 0; border: 1px solid #475569; margin: auto; max-height: 90vh; display: flex; flex-direction: column; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5);}
        .modal-content.large { max-width: 800px; }
        .modal-header { padding: 1.5rem; border-bottom: 1px solid #334155; flex-shrink: 0; }
        .modal-header h2 { margin: 0; font-size: 1.4rem; color: #fff;}
        .modal-body { padding: 1.5rem; overflow-y: auto; flex: 1; }
        .modal-footer { padding: 1.5rem; border-top: 1px solid #334155; display: flex; justify-content: flex-end; gap: 10px; flex-shrink: 0; }
        
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px; }
        .form-group { display: flex; flex-direction: column; gap: 8px; margin-bottom: 15px; }
        .form-group.full-width { grid-column: 1 / -1; }
        
        .form-group label { color: #94a3b8; font-size: 0.85rem; font-weight: 500; }
        .form-group input, .form-group select { padding: 12px; background: #0f172a; border: 1px solid #334155; border-radius: 8px; color: white; font-size: 0.95rem; transition: 0.2s; outline: none;}
        .form-group input:focus, .form-group select:focus { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1); }
        select[disabled], input[readonly] { opacity: 0.6; cursor: not-allowed; background: #1e293b; }

        .input-group { display: flex; gap: 10px; }
        .input-group input { flex: 1; }
        .btn-select { background: #475569; color: white; border: none; padding: 0 1.5rem; border-radius: 8px; cursor: pointer; white-space: nowrap; font-weight: 600; transition: 0.2s;}
        .btn-select:hover { background: #64748b; }
        
        .lineage-container { background: rgba(15, 23, 42, 0.5); padding: 1.5rem; border-radius: 8px; border: 1px solid #334155; margin-bottom: 15px; }
        .lineage-container > label { color: #cbd5e1; margin-bottom: 1rem; display: block; font-weight: 600; border-bottom: 1px solid #334155; padding-bottom: 5px;}
        .lineage-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        
        .btn-save { padding: 12px 20px; background: #2563eb; border: none; border-radius: 8px; color: white; font-weight: 600; cursor: pointer; transition: 0.2s; }
        .btn-save:hover { background: #1d4ed8; }
        .btn-save:disabled { opacity: 0.6; cursor: not-allowed; }
        .btn-cancel { padding: 12px 20px; background: transparent; color: #cbd5e1; border: 1px solid #475569; border-radius: 8px; cursor: pointer; transition: 0.2s; font-weight: 600;}
        .btn-cancel:hover { background: rgba(255,255,255,0.05); color: white; }
        
        .empty-state { text-align: center; padding: 3rem 1rem; display: block; color: #94a3b8; }
        .alert { padding: 12px; border-radius: 8px; margin-bottom: 15px; display: none; font-size: 0.9rem; text-align: center; font-weight: 600;}
        .alert.success { background: rgba(16, 185, 129, 0.2); border: 1px solid #10b981; color: #34d399; }
        .alert.error { background: rgba(239, 68, 68, 0.2); border: 1px solid #ef4444; color: #f87171; }
        
        .icon { width: 18px; height: 18px; }
        
        /* Mobile Responsive */
        @media (max-width: 900px) {
            .container { padding: 1rem; }
            .header { flex-direction: column; align-items: flex-start; gap: 1rem; }
            .header-buttons { flex-direction: column; width: 100%; }
            .add-btn { width: 100%; justify-content: center; }
            
            .filter-bar { grid-template-columns: 1fr; }
            .btn-reset { width: 100%; }
            
            .form-row, .lineage-row { grid-template-columns: 1fr; gap: 0;}
            .modal-footer { flex-direction: column; }
            .modal-footer button { width: 100%; margin-left: 0; }

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
            .animal-details h3 { font-size: 1.1rem; text-align: right; margin: 0;}
            .animal-type-info { text-align: right; }
            .actions { justify-content: flex-end; width: 100%; }
        }
    </style>
</head>
<body>
    <div class="container">
        
        <a href="admin_dashboard.php" class="back-link">
            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            Back to Admin Dashboard
        </a>

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
                <select name="f_loc" class="filter-select" onchange="this.form.submit()" <?php echo ($USER_LOCATION_ != 1000) ? 'style="pointer-events: none; opacity: 0.7; background-color: #1e293b;"' : ''; ?>>
                    <?php if($USER_LOCATION_ == 1000): ?>
                        <option value="">-- All Locations --</option>
                    <?php endif; ?>
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
                        <th>Class / Breed</th> 
                        <th>Sex</th>
                        <th>Age</th>
                        <th>Birth Date</th>
                        <th>Weight (Est / Act)</th>
                        <th>Lineage (Dam/Sire)</th>
                        <th>Status</th>
                        <th>Cost</th>
                        <th>Location</th>
                        <th style="text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody id="animal-table">
                    <?php if (empty($animal_data)): ?>
                        <?php else: ?>
                        <?php foreach ($animal_data as $data): ?>
                            <tr data-id="<?php echo $data['ANIMAL_ID']; ?>">
                                <td data-label="Tag No">
                                    <div class="animal-details">
                                        <h3><?php echo htmlspecialchars($data['TAG_NO']); ?></h3>
                                    </div>
                                </td>
                                <td data-label="Class / Breed">
                                    <div class="animal-type-info">
                                        <span style="color:#fff; font-weight:600;">
                                            <?php echo htmlspecialchars($data['STAGE_NAME'] ?? 'Unclassified'); ?>
                                        </span><br>
                                        <small style="color:#94a3b8"><?php echo htmlspecialchars($data['BREED_NAME']); ?></small>
                                    </div>
                                </td>
                                <td data-label="Sex"><?php if($data['SEX'] == 'M') echo  'Male'; elseif($data['SEX'] == 'F') echo 'Female';  else echo 'Unknown'; ?></td>
                                <td data-label="Age" style="color:#fcd34d; font-weight:600;">
                                    <?php echo $data['DAYS_OLD'] !== null ? $data['DAYS_OLD'] . " days" : "N/A"; ?>
                                </td>
                                <td data-label="Birth Date"><?php echo $data['BIRTH_DATE'] ? date('m/d/Y', strtotime($data['BIRTH_DATE'])) : 'N/A'; ?></td>
                                <td data-label="Weight (Est/Act)">
                                    <span style="color:#60a5fa;"><?php echo number_format($data['CURRENT_ESTIMATED_WEIGHT'], 2); ?></span> / 
                                    <span style="color:#34d399;"><?php echo number_format($data['CURRENT_ACTUAL_WEIGHT'], 2); ?></span>
                                </td>
                                <td data-label="Lineage">
                                    <div style="font-size:0.85rem;">
                                        <span style="color: #f472b6;">Dam: <?php echo $data['MOTHER_TAG'] ? $data['MOTHER_TAG'] : '-'; ?></span><br>
                                        <span style="color: #60a5fa;">Sire: <?php echo $data['FATHER_TAG'] ? $data['FATHER_TAG'] : '-'; ?></span>
                                    </div>
                                </td>
                                <td data-label="Status">
                                    <span class="status-badge <?php echo strtolower($data['CURRENT_STATUS']); ?>">
                                        <?php echo htmlspecialchars($data['CURRENT_STATUS']); ?>
                                    </span>
                                </td>
                                <td data-label="Cost">
                                    <span style="color:#fbbf24; font-family: monospace; font-size: 1.1rem; font-weight: bold;">₱<?php echo number_format($data['ACQUISITION_COST'], 2); ?></span>
                                </td>
                                <td data-label="Location"><?php echo htmlspecialchars($data['LOCATION_NAME']); ?> - <?php echo htmlspecialchars($data['PEN_NAME']); ?></td>
                                <td data-label="Actions">
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
                    <h3>Please select a Location to view records</h3>
                <?php else: ?>
                    <h3>No records found matching criteria</h3>
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

                    <div id="lineage-group" class="lineage-container" style="display:none;">
                        <label>Lineage (Optional)</label>
                        <div class="lineage-row">
                            <div class="form-group" style="margin-bottom: 0;">
                                <label style="color: #f472b6;">Mother (Sow)</label>
                                <div class="input-group">
                                    <input type="hidden" id="add_mother_id" name="mother_id">
                                    <input type="text" id="display_mother_tag" placeholder="Select Sow..." readonly style="border-color: #f472b6;">
                                    <button type="button" class="btn-select" onclick="openSelectParentModal('sow')">🔍</button>
                                </div>
                            </div>
                            <div class="form-group" style="margin-bottom: 0;">
                                <label style="color: #60a5fa;">Father (Boar)</label>
                                <div class="input-group">
                                    <input type="hidden" id="add_father_id" name="father_id">
                                    <input type="text" id="display_father_tag" placeholder="Select Boar..." readonly style="border-color: #60a5fa;">
                                    <button type="button" class="btn-select" onclick="openSelectParentModal('boar')">🔍</button>
                                </div>
                                <small style="color: #94a3b8; display: block; margin-top: 4px;">Will auto-apply to siblings with the same Sow & Birth Date.</small>
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
                        <div class="form-group" id="acquisition-cost-group">
                            <label style="color:#fbbf24;">Acquisition Cost (PHP)</label>
                            <input type="number" id="add_acquisition_cost" name="acquisition_cost" step="0.01" placeholder="0.00" style="border-color:#fbbf24;">
                        </div>
                        <div id="birth-date-group" class="form-group">
                            <label>Birth Date *</label>
                            <input type="date" id="add_birth_date" name="birth_date" required>
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
                        <select id="add_location" name="location_id" required onchange="loadBuildings(this.value, 'add')" <?php echo ($USER_LOCATION_ != 1000) ? 'style="background-color: #1e293b; pointer-events: none; color: #94a3b8;"' : ''; ?>>
                            <?php if($USER_LOCATION_ == 1000): ?>
                                <option value="">Select Location</option>
                            <?php endif; ?>
                            <?php foreach ($locations as $loc): ?>
                                <option value="<?php echo $loc['LOCATION_ID']; ?>" <?php echo ($USER_LOCATION_ != 1000 && $loc['LOCATION_ID'] == $USER_LOCATION_) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($loc['LOCATION_NAME']); ?>
                                </option>
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
                        <thead><tr><th>Tag No</th><th>Breed</th><th>Location</th><th style="text-align:center;">Action</th></tr></thead>
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
                        <thead><tr><th>ID</th><th>Name</th><th>Cost</th><th>Location</th><th style="text-align:center;">Action</th></tr></thead>
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

                    <div class="lineage-container">
                        <label>Lineage</label>
                        <div class="lineage-row">
                            <div class="form-group" style="margin-bottom: 0;">
                                <label style="color: #f472b6;">Mother (Sow)</label>
                                <div class="input-group">
                                    <input type="hidden" id="edit_mother_id" name="mother_id">
                                    <input type="text" id="edit_display_mother" placeholder="Select Sow..." readonly style="border-color: #f472b6;">
                                    <button type="button" class="btn-select" onclick="openSelectParentModal('sow', 'edit')">🔍</button>
                                </div>
                            </div>
                            <div class="form-group" style="margin-bottom: 0;">
                                <label style="color: #60a5fa;">Father (Boar)</label>
                                <div class="input-group">
                                    <input type="hidden" id="edit_father_id" name="father_id">
                                    <input type="text" id="edit_display_father" placeholder="Select Boar..." readonly style="border-color: #60a5fa;">
                                    <button type="button" class="btn-select" onclick="openSelectParentModal('boar', 'edit')">🔍</button>
                                </div>
                                <small style="color: #94a3b8; display: block; margin-top: 4px;">Will auto-apply to siblings with the same Sow & Birth Date.</small>
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
                            <input type="date" id="edit_birth_date" name="birth_date" required>
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
                        <select id="edit_location" name="location_id" required onchange="loadBuildings(this.value, 'edit')" <?php echo ($USER_LOCATION_ != 1000) ? 'style="background-color: #1e293b; pointer-events: none; color: #94a3b8;"' : ''; ?>>
                            <?php if($USER_LOCATION_ == 1000): ?>
                                <option value="">Select Location</option>
                            <?php endif; ?>
                            <?php foreach ($locations as $location): ?>
                                <option value="<?php echo $location['LOCATION_ID']; ?>" <?php echo ($USER_LOCATION_ != 1000 && $location['LOCATION_ID'] == $USER_LOCATION_) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($location['LOCATION_NAME']); ?>
                                </option>
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
                        <thead><tr><th>ID</th><th>Name</th><th>Cost</th><th>Location</th><th style="text-align:center;">Action</th></tr></thead>
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
        const USER_LOCATION = <?php echo json_encode($USER_LOCATION_); ?>;

        // Initialize Flatpickr for guaranteed mm/dd/yyyy visual inputs while keeping YYYY-MM-DD logic
        const fpAddBirth = flatpickr("#add_birth_date", {
            dateFormat: "Y-m-d", // The actual value submitted to PHP
            altInput: true,      // Create a visually clean dummy input
            altFormat: "m/d/Y",  // Visually format it as mm/dd/yyyy
            allowInput: true
        });

        const fpEditBirth = flatpickr("#edit_birth_date", {
            dateFormat: "Y-m-d",
            altInput: true,
            altFormat: "m/d/Y",
            allowInput: true
        });

        document.addEventListener('DOMContentLoaded', () => {
            const rows = document.querySelectorAll('#animal-table tr');
            if(rows.length === 0) document.getElementById('empty-state').style.display = 'block';
        });

        // --- MODAL CONTROLLERS ---
        let acquisition_type = 0;
        let currentParentMode = '';
        let currentParentType = '';

        function openAddModal(type, acquisition = 0) {
            const form = document.getElementById('addAnimalForm');
            form.reset();
            fpAddBirth.clear(); // Reset the datepicker
            
            document.getElementById('add_breed').innerHTML = '<option value="">Select Type First</option>';
            document.getElementById('add_breed').disabled = true;

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

            // Set default date logic
            const today = new Date().toISOString().split('T')[0];

            if (type === 'purchase') {
                modalTitle.textContent = 'Add Purchased Animal';
                entryType.value = 'purchase';
                purchaseGroup.style.display = 'block';
                birthGroup.style.display = 'flex'; 
                costGroup.style.display = 'block'; 
                lineageGroup.style.display = 'none';
                fpAddBirth.setDate(today); // Use flatpickr api to set value
                
            } else if (type === 'existing') {
                modalTitle.textContent = 'Add Existing Record';
                entryType.value = 'existing';
                purchaseGroup.style.display = 'none';
                birthGroup.style.display = 'flex';
                costGroup.style.display = 'block'; 
                lineageGroup.style.display = 'block';
                fpAddBirth.setDate(today); // Use flatpickr api to set value
            }

            const locSelect = document.getElementById('add_location');
            if (USER_LOCATION != 1000) {
                locSelect.value = USER_LOCATION;
                loadBuildings(USER_LOCATION, 'add');
            } else {
                locSelect.value = "";
                document.getElementById('add_building').innerHTML = '<option value="">Select Location First</option>';
                document.getElementById('add_building').disabled = true;
                document.getElementById('add_pen').innerHTML = '<option value="">Select Building First</option>';
                document.getElementById('add_pen').disabled = true;
            }

            document.getElementById('addModal').classList.add('show');
        }

        function closeAddModal() { document.getElementById('addModal').classList.remove('show'); }

        function openSelectParentModal(type, mode = 'add') {

            // ONLY SET VARIABLES - DO NOT CLOSE THE EDIT MODAL
            currentParentType = type;
            currentParentMode = mode;
            
            document.getElementById('selectParentModal').classList.add('show');
            document.getElementById('parent-modal-title').textContent = type === 'sow' ? 'Select Mother' : 'Select Father';
            loadAvailableParents(type);
            
        }
        function closeSelectParentModal() { document.getElementById('selectParentModal').classList.remove('show'); }

        function loadAvailableParents(type) {
            const tbody = document.getElementById('parent-table-body');
            tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;">Loading...</td></tr>';
            
            const script = type === 'sow' ? '../process/getAvailableSows.php' : '../process/getAvailableBoars.php';
            
            fetch(script).then(res => res.json()).then(data => {
                const list = data.sows || data.boars || [];
                if (data.success && list.length > 0) {
                    tbody.innerHTML = list.map(s => `
                        <tr>
                            <td data-label="Tag No" style="font-weight:bold; color:${type==='sow'?'#f472b6':'#60a5fa'};">${s.TAG_NO}</td>
                            <td data-label="Breed">${s.BREED_NAME}</td>
                            <td data-label="Location">${s.LOCATION_NAME} - ${s.PEN_NAME}</td>
                            <td data-label="Action" style="text-align:center;"><button class="action-btn" style="background:#22c55e20; color:#86efac; border:1px solid #22c55e40; padding:5px 15px; border-radius:5px; font-weight:600;" onclick="selectParent('${s.ANIMAL_ID}', '${s.TAG_NO}')">SELECT</button></td>
                        </tr>`).join('');
                } else { tbody.innerHTML = `<tr><td colspan="4" style="text-align:center;">No active ${type}s found.</td></tr>`; }
            })
            .catch(err => {
                console.error(err);
                tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;">Error loading data.</td></tr>'; 
            });
        }

        function selectParent(id, tag) {
            const prefix = currentParentMode === 'add' ? 'add_' : 'edit_';
            const displayPrefix = currentParentMode === 'add' ? 'display_' : 'edit_display_';
            
            if (currentParentType === 'sow') {
                document.getElementById(prefix + 'mother_id').value = id;
                if(currentParentMode === 'edit') document.getElementById('edit_display_mother').value = tag;
                else document.getElementById('display_mother_tag').value = tag;
            } else {
                document.getElementById(prefix + 'father_id').value = id;
                if(currentParentMode === 'edit') document.getElementById('edit_display_father').value = tag;
                else document.getElementById('display_father_tag').value = tag;
            }
            closeSelectParentModal();
        }

        function loadAvailablePurchases(targetBodyId) {
            const tbody = document.getElementById(targetBodyId);
            tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;">Loading...</td></tr>';
            fetch('../process/getAvailablePurchasedAnimals.php').then(r=>r.json()).then(data=>{
                if(data.success && data.items.length > 0) {
                    tbody.innerHTML = data.items.map(i => {
                        const funcName = targetBodyId === 'add-purchase-table-body' ? 'selectPurchaseItem' : 'selectEditPurchaseItem';
                        return `<tr>
                            <td data-label="ID">${i.ITEM_ID}</td>
                            <td data-label="Name">${i.ITEM_NAME}</td>
                            <td data-label="Cost">${i.UNIT_COST}</td>
                            <td data-label="Location">${i.LOCATION_NAME}</td>
                            <td data-label="Action" style="text-align:center;"><button class="action-btn" style="background:#22c55e20; color:#86efac; border:1px solid #22c55e40; padding:5px 15px; border-radius:5px; font-weight:600;" onclick="${funcName}('${i.ITEM_ID}', '${i.LOCATION_ID}', '${i.BUILDING_ID}', '${i.PEN_ID}', '${i.ITEM_NAME}', '${i.UNIT_COST}')">SELECT</button></td>
                        </tr>`;
                    }).join('');
                } else { tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;">No items found</td></tr>'; }
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

                    document.getElementById('edit_mother_id').value = animal.MOTHER_ID || '';
                    document.getElementById('edit_father_id').value = animal.FATHER_ID || '';
                    
                    // Show parent tags in inputs
                    document.getElementById('edit_display_mother').value = animal.MOTHER_TAG || '';
                    document.getElementById('edit_display_father').value = animal.FATHER_TAG || '';
                    
                    document.getElementById('edit_animal_type').value = animal.ANIMAL_TYPE_ID;
                    await loadBreeds(animal.ANIMAL_TYPE_ID, 'edit');
                    
                    setTimeout(() => {
                         document.getElementById('edit_breed').value = animal.BREED_ID;
                    }, 50);

                    if (animal.ANIMAL_ITEM_ID) {
                        document.getElementById('edit-purchase-group').style.display = 'block';
                        document.getElementById('edit_animal_item_id').value = animal.ANIMAL_ITEM_ID; 
                        document.getElementById('edit_has_purchase').value = "1";
                        document.getElementById('edit-birth-date-group').style.display = 'flex'; 
                        fpEditBirth.setDate(animal.BIRTH_DATE || ''); // Flatpickr set API
                    } else {
                        document.getElementById('edit-purchase-group').style.display = 'none';
                        document.getElementById('edit_has_purchase').value = "0";
                        document.getElementById('edit-birth-date-group').style.display = 'flex';
                        fpEditBirth.setDate(animal.BIRTH_DATE || ''); // Flatpickr set API
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
            if (count === undefined) { 
                const rowCount = document.querySelectorAll('#animal-table tr').length;
                el.style.display = (rowCount === 0) ? 'block' : 'none';
            } else {
                el.style.display = (count === 0) ? 'block' : 'none';
            }
        }

        function showAlert(mode, msg, type) {
            const el = document.getElementById(mode+'-alert');
            el.textContent = msg; el.className = 'alert ' + type; el.style.display='block';
        }

        document.getElementById('addModal').addEventListener('click', function(e) { if(e.target===this) closeAddModal(); });
        
        // RESTORED editModal click listener! It works safely now because of z-index stacking.
        document.getElementById('editModal').addEventListener('click', function(e) { if(e.target===this) closeEditModal(); });
        
        document.getElementById('selectPurchaseModal').addEventListener('click', function(e) { if(e.target===this) closeSelectPurchaseModal(); });
        document.getElementById('editSelectPurchaseModal').addEventListener('click', function(e) { if(e.target===this) closeEditSelectPurchaseModal(); });
        document.getElementById('selectParentModal').addEventListener('click', function(e) { if(e.target===this) closeSelectParentModal(); });

    </script>
</body>
</html>