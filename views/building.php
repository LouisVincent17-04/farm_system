<?php
// views/building.php
error_reporting(0);
ini_set('display_errors', 0);

$page="admin_dashboard";
include '../config/Connection.php'; 

// =========================================================
// AJAX HANDLER: Get Building Pens & Stats
// =========================================================
if (isset($_GET['action']) && $_GET['action'] === 'get_building_pens') {
    @ob_end_clean();
    header('Content-Type: application/json');
    try {
        $bldg_id = $_GET['building_id'] ?? 0;
        
        $sql = "SELECT p.PEN_ID, p.PEN_NAME, 
                       COUNT(a.ANIMAL_ID) as ANIMAL_COUNT
                FROM PENS p
                LEFT JOIN animal_records a ON p.PEN_ID = a.PEN_ID AND a.IS_ACTIVE = 1 AND a.CURRENT_STATUS != 'Sold'
                WHERE p.BUILDING_ID = ?
                GROUP BY p.PEN_ID, p.PEN_NAME
                ORDER BY p.PEN_NAME ASC";
                
        $stmt = $conn->prepare($sql);
        $stmt->execute([$bldg_id]);
        
        echo json_encode(['success' => true, 'pens' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}
// =========================================================

include '../security/checkAccess.php';
checkAccess('building');
include '../common/navbar.php';
include '../common/chat_support.php';

if($_SESSION['user']['USER_TYPE'] < 3) {
    echo "<script>alert('Access denied.'); window.location.href = 'admin_dashboard.php';</script>";
    exit();
}

$status = $_GET['status'] ?? '';
$msg = $_GET['msg'] ?? '';

try {
    if (!isset($conn)) { throw new Exception("Database connection failed."); }

    $sql = "SELECT b.BUILDING_ID, b.BUILDING_NAME, b.LOCATION_ID, l.LOCATION_NAME,
                   (SELECT COUNT(p.PEN_ID) FROM PENS p WHERE p.BUILDING_ID = b.BUILDING_ID) as PEN_COUNT
            FROM BUILDINGS b
            LEFT JOIN LOCATIONS l ON b.LOCATION_ID = l.LOCATION_ID
            ORDER BY b.BUILDING_NAME ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $building_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $sql = "SELECT LOCATION_ID, LOCATION_NAME, COMPLETE_ADDRESS FROM LOCATIONS ORDER BY LOCATION_ID ASC";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $location_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $building_data = [];
    $location_data = [];
    $status = 'error';
    $msg = "Database Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Building Management System</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />

    <style>
        /* ─── CSS VARIABLES ─── */
        :root {
            --bg-base:        #080f1a;
            --bg-surface:     #0d1829;
            --bg-elevated:    #111f35;
            --bg-hover:       #162540;
            --border:         rgba(255,255,255,0.07);
            --border-active:  rgba(16,185,129,0.5); /* Emerald Accent */
            --emerald:        #10b981;
            --emerald-dim:    rgba(16,185,129,0.12);
            --emerald-glow:   rgba(16,185,129,0.25);
            --blue:           #38bdf8;
            --blue-dim:       rgba(56,189,248,0.12);
            --purple:         #a78bfa;
            --purple-dim:     rgba(167,139,250,0.12);
            --red:            #f87171;
            --red-dim:        rgba(248,113,113,0.12);
            --text-primary:   #f1f5f9;
            --text-secondary: #94a3b8;
            --text-muted:     #475569;
            --radius-md:      10px;
            --radius-lg:      14px;
            --radius-xl:      20px;
            --font:           'DM Sans', system-ui, sans-serif;
            --font-mono:      'DM Mono', monospace;
            --transition:     0.18s cubic-bezier(0.4,0,0.2,1);
        }

        /* ─── RESET & BASE ─── */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: var(--font);
            background: var(--bg-base);
            color: var(--text-primary);
            min-height: 100vh;
            background-image: radial-gradient(ellipse 80% 50% at 50% -20%, rgba(16,185,129,0.06) 0%, transparent 60%);
        }
        .container { max-width: 1560px; margin: 0 auto; padding: 2rem 1.5rem; }

        /* ─── TOP BAR ─── */
        .top-bar { display: flex; align-items: center; justify-content: space-between; margin-bottom: 2rem; gap: 1rem; flex-wrap: wrap; }
        .back-link {
            display: inline-flex; align-items: center; gap: 8px; text-decoration: none;
            color: var(--text-secondary); font-size: 0.875rem; font-weight: 500;
            padding: 8px 14px; background: var(--bg-elevated); border: 1px solid var(--border);
            border-radius: var(--radius-md); transition: all var(--transition);
        }
        .back-link:hover { color: var(--text-primary); border-color: var(--border-active); background: var(--bg-hover); }

        .page-badge {
            display: inline-flex; align-items: center; gap: 6px; font-size: 0.75rem;
            font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase;
            color: var(--emerald); background: var(--emerald-dim); border: 1px solid rgba(16,185,129,0.2);
            padding: 6px 12px; border-radius: 99px;
        }

        /* ─── HEADER ─── */
        .page-header {
            display: flex; justify-content: space-between; align-items: flex-end;
            margin-bottom: 2rem; gap: 1rem; flex-wrap: wrap;
        }
        .header-info h1 {
            font-size: clamp(1.6rem, 3vw, 2.2rem); font-weight: 700;
            color: var(--text-primary); letter-spacing: -0.03em; line-height: 1.1; margin-bottom: 0.25rem;
        }
        .header-info h1 span {
            background: linear-gradient(135deg, var(--emerald), #059669);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }
        .header-info p { color: var(--text-secondary); font-size: 0.95rem; }

        /* ─── BUTTONS ─── */
        .btn {
            display: inline-flex; align-items: center; justify-content: center; gap: 8px;
            padding: 10px 20px; border-radius: var(--radius-md); font-size: 0.9rem;
            font-weight: 600; font-family: var(--font); border: 1px solid transparent;
            cursor: pointer; transition: all var(--transition); text-decoration: none; white-space: nowrap;
        }
        .btn-primary { background: var(--emerald); color: #000; }
        .btn-primary:hover { background: #34d399; box-shadow: 0 0 16px var(--emerald-glow); transform: translateY(-1px); }
        .btn-ghost { background: transparent; color: var(--text-secondary); border-color: var(--border); }
        .btn-ghost:hover { background: var(--bg-elevated); color: var(--text-primary); border-color: rgba(255,255,255,0.15); }

        /* ─── FILTERS & SEARCH ─── */
        .filters-wrapper {
            display: flex; gap: 1rem; margin-bottom: 1.5rem; flex-wrap: wrap;
            background: var(--bg-surface); border: 1px solid var(--border);
            padding: 1rem; border-radius: var(--radius-xl); align-items: center;
        }
        .search-container { position: relative; flex: 1; min-width: 250px; display: flex; align-items: center; }
        .search-icon { position: absolute; left: 1rem; color: var(--text-muted); width: 18px; height: 18px; pointer-events: none; }
        .search-input {
            width: 100%; padding: 12px 12px 12px 2.8rem; background: var(--bg-elevated);
            border: 1px solid var(--border); border-radius: var(--radius-md); color: var(--text-primary);
            font-size: 0.9rem; font-family: var(--font); outline: none; transition: all var(--transition);
        }
        .search-input:focus { border-color: var(--emerald); box-shadow: 0 0 0 3px var(--emerald-glow); background: var(--bg-hover); }

        .sort-select {
            width: auto; min-width: 200px; padding: 12px 36px 12px 12px;
            background: var(--bg-elevated); border: 1px solid var(--border);
            color: var(--text-primary); font-size: 0.9rem; font-family: var(--font);
            border-radius: var(--radius-md); outline: none; transition: all var(--transition);
            appearance: none; cursor: pointer;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2394a3b8' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
            background-repeat: no-repeat; background-position: right 12px center;
        }

        /* ─── TABLE ─── */
        .table-card {
            background: var(--bg-surface); border: 1px solid var(--border);
            border-radius: var(--radius-xl); overflow: hidden;
        }
        .table-wrap { overflow-x: auto; }
        .table { width: 100%; border-collapse: collapse; min-width: 900px; }
        .table thead th {
            background: var(--bg-elevated); color: var(--text-muted);
            font-size: 0.7rem; font-weight: 700; text-transform: uppercase;
            letter-spacing: 0.07em; padding: 14px 16px; text-align: left;
            border-bottom: 1px solid var(--border);
        }
        .table tbody tr { border-bottom: 1px solid var(--border); transition: background var(--transition); }
        .table tbody tr:last-child { border-bottom: none; }
        .table tbody tr:hover { background: rgba(255,255,255,0.02); }
        .table td { padding: 14px 16px; font-size: 0.9rem; color: var(--text-primary); vertical-align: middle; }

        .col-id { font-family: var(--font-mono); color: var(--text-muted); font-size: 0.85rem; }
        .col-name { font-weight: 600; color: #fff; font-size: 1.05rem; }
        .location-tag { color: var(--text-secondary); font-size: 0.8rem; background: rgba(255,255,255,0.05); padding: 4px 8px; border-radius: 4px; border: 1px solid var(--border); }
        
        .pen-badge { font-family: var(--font-mono); font-weight: 700; color: var(--emerald); background: var(--emerald-dim); padding: 4px 10px; border-radius: 6px; border: 1px solid rgba(16,185,129,0.2); }
        .pen-badge.empty { color: var(--red); background: var(--red-dim); border-color: rgba(248,113,113,0.2); }

        /* Actions */
        .actions { display: flex; gap: 8px; }
        .action-btn {
            width: 32px; height: 32px; border-radius: 6px;
            border: 1px solid var(--border); background: var(--bg-elevated);
            display: flex; align-items: center; justify-content: center;
            cursor: pointer; transition: all var(--transition); color: var(--text-secondary);
        }
        .action-btn:hover { background: var(--bg-hover); color: var(--text-primary); }
        .action-btn.view:hover { color: var(--purple); border-color: var(--purple); }
        .action-btn.edit:hover { color: var(--blue); border-color: var(--blue); }
        .action-btn.delete:hover { color: var(--red); border-color: var(--red); }

        /* ─── MODALS ─── */
        .modal {
            display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.85);
            backdrop-filter: blur(5px); z-index: 1000; align-items: center; justify-content: center;
            padding: 1rem;
        }
        .modal.show { display: flex; }
        .modal-content {
            background: var(--bg-surface); border: 1px solid var(--border);
            border-radius: var(--radius-xl); width: 100%; max-width: 500px;
            max-height: 90vh; display: flex; flex-direction: column; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5);
            overflow: hidden;
        }
        .modal-header { padding: 1.5rem; border-bottom: 1px solid var(--border); }
        .modal-header h2 { margin: 0; font-size: 1.25rem; font-weight: 700; color: var(--emerald); }
        .modal-body { padding: 1.5rem; overflow-y: auto; }
        .modal-footer { padding: 1.25rem 1.5rem; border-top: 1px solid var(--border); display: flex; justify-content: flex-end; gap: 10px; background: var(--bg-elevated); }

        .form-group { display: flex; flex-direction: column; gap: 6px; margin-bottom: 1.25rem; }
        .form-label { font-size: 0.72rem; font-weight: 600; text-transform: uppercase; color: var(--text-secondary); letter-spacing: 0.05em; }
        .form-control {
            width: 100%; padding: 10px 12px; background: var(--bg-elevated); border: 1px solid var(--border);
            color: var(--text-primary); border-radius: 8px; font-size: 0.95rem; font-family: var(--font);
            outline: none; transition: all var(--transition);
        }
        .form-control:focus { border-color: var(--emerald); box-shadow: 0 0 0 3px var(--emerald-glow); }

        /* Stats Grid in View Modal */
        .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 1.5rem; }
        .stat-card { background: var(--bg-elevated); border: 1px solid var(--border); border-radius: 12px; padding: 12px; text-align: center; }
        .stat-val { font-size: 1.4rem; font-weight: 700; margin-bottom: 2px; font-family: var(--font-mono); }
        .stat-label { font-size: 0.65rem; color: var(--text-muted); text-transform: uppercase; font-weight: 600; letter-spacing: 0.05em; }

        /* ─── ALERTS ─── */
        .alert-box { padding: 12px 16px; border-radius: var(--radius-md); margin-bottom: 1.5rem; text-align: center; font-weight: 600; font-size: 0.9rem; }
        .alert-success { background: var(--emerald-dim); border: 1px solid rgba(16, 185, 129, 0.3); color: var(--emerald); }
        .alert-error { background: var(--red-dim); border: 1px solid rgba(239, 68, 68, 0.3); color: var(--red); }

        /* ─── RESPONSIVE ─── */
        @media (max-width: 768px) {
            .page-header { flex-direction: column; align-items: flex-start; }
            .header-info { width: 100%; text-align: center; }
            .add-btn { width: 100%; justify-content: center; }

            .table-wrap { border: none; background: transparent; }
            .table, .table thead, .table tbody, .table th, .table td, .table tr { display: block; }
            .table thead { display: none; }
            .table tbody tr { 
                background: var(--bg-surface); border: 1px solid var(--border); 
                border-radius: var(--radius-xl); margin-bottom: 1rem; padding: 1.25rem;
            }
            .table td { 
                display: flex; justify-content: space-between; align-items: center; 
                padding: 0.6rem 0; border-bottom: 1px solid rgba(255,255,255,0.05); text-align: right;
            }
            .table td:last-child { border-bottom: none; justify-content: center; padding-top: 1rem; }
            .table td::before { 
                content: attr(data-label); font-weight: 700; color: var(--text-muted); 
                font-size: 0.75rem; text-transform: uppercase; text-align: left;
            }
            .actions { justify-content: flex-end; width: 100%; }
            .building-info { justify-content: flex-end; }
        }
    </style>
</head>
<body>
    <div class="container">
        
        <div class="top-bar">
            <a href="admin_dashboard.php" class="back-link">
                <i class="fa-solid fa-arrow-left"></i> Back to Dashboard
            </a>
            <span class="page-badge"><i class="fa-solid fa-warehouse"></i> Infrastructure</span>
        </div>

        <div class="page-header">
            <div class="header-info">
                <h1>Building <span>Management</span></h1>
                <p>Configure structural units and monitor pen allocations.</p>
            </div>
            <button class="btn btn-primary" onclick="openAddModal()">
                <i class="fa-solid fa-plus"></i> Add Building
            </button>
        </div>

        <?php if (!empty($msg)): ?>
            <div class="alert-box alert-<?php echo htmlspecialchars($status); ?>">
                <i class="fa-solid <?php echo ($status == 'success') ? 'fa-circle-check' : 'fa-circle-exclamation'; ?> me-2"></i>
                <?php echo htmlspecialchars(urldecode($msg)); ?>
            </div>
        <?php endif; ?>

        <div class="filters-wrapper">
            <div class="search-container">
                <i class="fa-solid fa-magnifying-glass search-icon"></i>
                <input type="text" class="search-input" placeholder="Search buildings by name or location..." onkeyup="filterTable()">
            </div>
            
            <select class="sort-select" onchange="sortDropdown(this.value)">
                <option value="name_asc">Sort: Building Name (A-Z)</option>
                <option value="name_desc">Sort: Building Name (Z-A)</option>
                <option value="count_desc">Sort: Most Pens</option>
                <option value="count_asc">Sort: Least Pens</option>
            </select>
        </div>

        <div class="table-card">
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width: 120px;">ID</th>
                            <th>Building Details</th>
                            <th>Location</th> 
                            <th>Capacity</th> 
                            <th style="text-align: center; width: 150px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="building-table">
                        <?php foreach($building_data as $data): ?>
                        <tr data-id="<?php echo $data['BUILDING_ID']; ?>" 
                            data-location-id="<?php echo $data['LOCATION_ID']; ?>"
                            data-name="<?php echo htmlspecialchars(strtolower($data['BUILDING_NAME'])); ?>"
                            data-count="<?php echo $data['PEN_COUNT']; ?>">
                            
                            <td data-label="ID" class="col-id">#<?php echo $data['BUILDING_ID']; ?></td>
                            
                            <td data-label="Building">
                                <div class="building-info">
                                    <div class="building-details">
                                        <h3 class="building-name-display col-name"><?php echo htmlspecialchars($data['BUILDING_NAME']); ?></h3>
                                    </div>
                                </div>
                            </td>
                            
                            <td data-label="Location">
                                <span class="location-tag">
                                    <i class="fa-solid fa-location-dot me-1"></i> <?php echo htmlspecialchars($data['LOCATION_NAME'] ?? 'N/A'); ?>
                                </span>
                            </td>

                            <td data-label="Capacity">
                                <span class="pen-badge <?php echo ($data['PEN_COUNT'] == 0) ? 'empty' : ''; ?>">
                                    <?php echo $data['PEN_COUNT']; ?> PENS
                                </span>
                            </td>
                            
                            <td data-label="Actions">
                                <div class="actions">
                                    <button class="action-btn view" onclick="viewBuilding(<?php echo $data['BUILDING_ID']; ?>, '<?php echo htmlspecialchars(addslashes($data['BUILDING_NAME'])); ?>')" title="View Pens">
                                        <i class="fa-solid fa-layer-group"></i>
                                    </button>
                                    <button class="action-btn edit" onclick="editBuilding(this)" title="Edit">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </button>
                                    <button class="action-btn delete" onclick="deleteBuilding(this)" title="Delete">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div id="empty-state" class="empty-state" style="<?php echo empty($building_data) ? 'display:block' : 'display:none'; ?>">
                    <i class="fa-solid fa-building-circle-exclamation" style="font-size: 2.5rem; opacity:0.2; display:block; margin-bottom:1rem;"></i>
                    <h3>No buildings found</h3>
                    <p>Try adjusting your search terms or add a new structural unit.</p>
                </div>
            </div>
        </div>
    </div>

    <div id="viewModal" class="modal">
        <div class="modal-content" style="max-width: 600px;">
            <div class="modal-header">
                <h2 id="view_building_name">Building Details</h2>
            </div>
            <div class="modal-body">
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-val" id="count-total" style="color: var(--blue);">0</div>
                        <div class="stat-label">Total Pens</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-val" id="count-occupied" style="color: var(--red);">0</div>
                        <div class="stat-label">Occupied</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-val" id="count-empty" style="color: var(--emerald);">0</div>
                        <div class="stat-label">Empty</div>
                    </div>
                </div>
                
                <div class="table-card" style="border-radius: var(--radius-md);">
                    <div class="table-wrap">
                        <table class="table" style="min-width: 100%;">
                            <thead>
                                <tr>
                                    <th>Pen Name</th>
                                    <th>Status</th>
                                    <th style="text-align: right;">Animals</th>
                                </tr>
                            </thead>
                            <tbody id="view-pens-list"></tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="closeViewModal()" style="width: 100%;">Close Window</button>
            </div>
        </div>
    </div>

    <div id="addModal" class="modal">
        <div class="modal-content">
            <div class="modal-header"><h2>Add New Building</h2></div>
            <div class="modal-body">
                <form id="addBuildingForm" method="POST" action="../process/addBuilding.php">
                    <div class="form-group">
                        <label class="form-label">Building Name *</label>
                        <input type="text" class="form-control" id="add_building_name" name="building_name" placeholder="e.g. Farrowing House A" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Physical Location *</label>
                        <select class="form-control" name="location_id" id="add_building_location_select" onchange="updateAddressField('add')">
                            <?php foreach($location_data as $loc): ?>
                                <option value="<?php echo $loc['LOCATION_ID']; ?>" data-address="<?php echo htmlspecialchars($loc['COMPLETE_ADDRESS']); ?>">
                                    <?php echo htmlspecialchars($loc['LOCATION_NAME']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Site Address</label>
                        <input type="text" id="add_location_complete_address" class="form-control" disabled style="opacity:50%;" placeholder="Select location to see address">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="closeAddModal()">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitAddForm()">Save Building</button>
            </div>
        </div>
    </div>

    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header"><h2>Edit Building</h2></div>
            <div class="modal-body">
                <form id="editBuildingForm" method="POST" action="../process/updateBuilding.php">
                    <input type="hidden" id="edit_building_id" name="building_id">
                    <div class="form-group">
                        <label class="form-label">Building Name *</label>
                        <input type="text" class="form-control" id="edit_building_name" name="building_name" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Update Location</label>
                        <select class="form-control" name="location_id" id="edit_building_location_select" onchange="updateAddressField('edit')">
                            <?php foreach($location_data as $loc): ?>
                                <option value="<?php echo $loc['LOCATION_ID']; ?>" data-address="<?php echo htmlspecialchars($loc['COMPLETE_ADDRESS']); ?>">
                                    <?php echo htmlspecialchars($loc['LOCATION_NAME']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Site Address</label>
                        <input type="text" id="edit_location_complete_address" class="form-control" disabled style="opacity:50%;">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="closeEditModal()">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitEditForm()">Update Changes</button>
            </div>
        </div>
    </div>

    <form id="deleteBuildingForm" method="POST" action="../process/deleteBuilding.php" style="display: none;">
        <input type="hidden" id="delete_building_id" name="building_id">
    </form>

    <script>
        function sortDropdown(val) {
            const tbody = document.getElementById('building-table');
            const rows = Array.from(tbody.querySelectorAll('tr'));
            rows.sort((a, b) => {
                if (val === 'name_asc') return a.dataset.name.localeCompare(b.dataset.name);
                if (val === 'name_desc') return b.dataset.name.localeCompare(a.dataset.name);
                if (val === 'count_desc') return parseInt(b.dataset.count) - parseInt(a.dataset.count);
                if (val === 'count_asc') return parseInt(a.dataset.count) - parseInt(b.dataset.count);
            });
            rows.forEach(row => tbody.appendChild(row));
        }

        async function viewBuilding(buildingId, buildingName) {
            document.getElementById('view_building_name').textContent = buildingName;
            document.getElementById('viewModal').classList.add('show');
            const tbody = document.getElementById('view-pens-list');
            tbody.innerHTML = '<tr><td colspan="3" style="text-align:center; padding: 2rem; color: var(--text-secondary);">Loading...</td></tr>';
            
            try {
                const res = await fetch(`?action=get_building_pens&building_id=${buildingId}`);
                const data = await res.json();
                if (!data.success) throw new Error(data.error);
                
                const pens = data.pens || [];
                document.getElementById('count-total').textContent = pens.length;
                let occupied = 0, empty = 0, html = '';

                pens.forEach(p => {
                    const count = parseInt(p.ANIMAL_COUNT);
                    if (count > 0) occupied++; else empty++;
                    const badge = count > 0 
                        ? `<span class="status-badge" style="background:var(--red-dim); color:var(--red); border:1px solid rgba(248,113,113,0.2)">Occupied</span>`
                        : `<span class="status-badge" style="background:var(--green-dim); color:var(--green); border:1px solid rgba(34,197,94,0.2)">Empty</span>`;

                    html += `<tr>
                        <td style="font-weight:600;">${p.PEN_NAME}</td>
                        <td>${badge}</td>
                        <td style="text-align:right; font-family:var(--font-mono); color:var(--text-secondary);">${count} Heads</td>
                    </tr>`;
                });

                document.getElementById('count-occupied').textContent = occupied;
                document.getElementById('count-empty').textContent = empty;
                tbody.innerHTML = html || '<tr><td colspan="3" style="text-align:center; padding:2rem;">No pens configured.</td></tr>';

            } catch (e) { tbody.innerHTML = `<tr><td colspan="3" style="color:var(--red); text-align:center;">Error: ${e.message}</td></tr>`; }
        }
        
        function openAddModal() {
            document.getElementById('addBuildingForm').reset();
            document.getElementById('addModal').classList.add('show');
            updateAddressField('add'); 
        }

        function editBuilding(button) {
            const row = button.closest('tr');
            document.getElementById('edit_building_id').value = row.dataset.id;
            document.getElementById('edit_building_name').value = row.querySelector('.building-name-display').textContent.trim();
            document.getElementById('edit_building_location_select').value = row.dataset.locationId; 
            updateAddressField('edit');
            document.getElementById('editModal').classList.add('show');
        }

        function updateAddressField(mode) {
            const select = document.getElementById(mode === 'add' ? 'add_building_location_select' : 'edit_building_location_select');
            const input = document.getElementById(mode === 'add' ? 'add_location_complete_address' : 'edit_location_complete_address');
            if (select && input && select.selectedIndex !== -1) {
                input.value = select.options[select.selectedIndex].dataset.address || '';
            }
        }

        function filterTable() {
            const term = document.querySelector('.search-input').value.toLowerCase();
            const rows = document.querySelectorAll('#building-table tr');
            let count = 0;
            rows.forEach(r => {
                const match = r.innerText.toLowerCase().includes(term);
                r.style.display = match ? '' : 'none';
                if(match) count++;
            });
            document.getElementById('empty-state').style.display = count ? 'none' : 'block';
        }

        function deleteBuilding(button) {
            const name = button.closest('tr').querySelector('.building-name-display').textContent.trim();
            if (confirm(`Permanently delete building: ${name}?`)) {
                document.getElementById('delete_building_id').value = button.closest('tr').dataset.id;
                document.getElementById('deleteBuildingForm').submit();
            }
        }

        function closeAddModal() { document.getElementById('addModal').classList.remove('show'); }
        function closeEditModal() { document.getElementById('editModal').classList.remove('show'); }
        function closeViewModal() { document.getElementById('viewModal').classList.remove('show'); }
        function submitAddForm() { document.getElementById('addBuildingForm').submit(); }
        function submitEditForm() { document.getElementById('editBuildingForm').submit(); }

        document.addEventListener('DOMContentLoaded', () => {
            updateAddressField('add');
            setTimeout(() => { document.querySelectorAll('.alert-box').forEach(el => el.style.display = 'none'); }, 5000);
        });

        window.addEventListener('click', (e) => {
            if (e.target.classList.contains('modal')) { closeAddModal(); closeEditModal(); closeViewModal(); }
        });
    </script>
</body>
</html>