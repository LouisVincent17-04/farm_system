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
        
        $pens = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Apply Natural Sorting to the returned pens so the modal is also sorted correctly
        usort($pens, function($a, $b) {
            return strnatcasecmp($a['PEN_NAME'], $b['PEN_NAME']);
        });
        
        echo json_encode(['success' => true, 'pens' => $pens]);
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
            LEFT JOIN LOCATIONS l ON b.LOCATION_ID = l.LOCATION_ID";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $building_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ====================================================================================
    // TRUE NATURAL SORTING (PHP)
    // Sorts alphabetically first, then numerically when it encounters numbers.
    // E.g., Building 1 -> Building 2 -> Building 10 -> Main Office
    // ====================================================================================
    usort($building_data, function($a, $b) {
        return strnatcasecmp($a['BUILDING_NAME'], $b['BUILDING_NAME']);
    });
    // ====================================================================================

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
            --green:          #22c55e;
            --green-dim:      rgba(34,197,94,0.12);
            --text-primary:   #f1f5f9;
            --text-secondary: #94a3b8;
            --text-muted:     #475569;
            --radius-md:      10px;
            --radius-lg:      14px;
            --radius-xl:      20px;
            --font:           'DM Sans', system-ui, sans-serif;
            --font-mono:      'DM Mono', monospace;
            --transition:     0.18s cubic-bezier(0.4,0,0.2,1);
            --shadow-md:      0 10px 15px -3px rgba(0,0,0,0.3);
        }

        /* ─── RESET & BASE ─── */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: var(--font); background: var(--bg-base); color: var(--text-primary);
            min-height: 100vh; padding-bottom: 60px;
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
            font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase;
            color: var(--emerald); background: var(--emerald-dim); border: 1px solid rgba(16,185,129,0.2);
            padding: 6px 12px; border-radius: 99px;
        }

        /* ─── HEADER ─── */
        .page-header { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 2.5rem; gap: 1.5rem; flex-wrap: wrap; }
        .header-info h1 { font-size: clamp(1.8rem, 4vw, 2.5rem); font-weight: 700; margin: 0 0 0.5rem 0; color: #fff; letter-spacing: -0.02em;}
        .header-info h1 span { background: linear-gradient(135deg, var(--emerald), #047857); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .header-info p { color: var(--text-secondary); font-size: 0.95rem; margin: 0; }

        /* ─── LAYOUT GRID ─── */
        .main-grid { display: grid; grid-template-columns: 360px 1fr; gap: 1.5rem; align-items: start; }

        /* ─── CONTROL PANEL (LEFT) ─── */
        .control-panel {
            background: var(--bg-surface); border: 1px solid var(--border);
            border-radius: var(--radius-xl); padding: 2rem; position: sticky; top: 1.5rem;
            box-shadow: var(--shadow-md); z-index: 10; display: flex; flex-direction: column;
        }
        .panel-title { font-size: 1.25rem; font-weight: 700; color: #fff; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 10px;}
        .panel-title i { color: var(--emerald); }
        .panel-subtitle { font-size: 0.9rem; color: var(--text-secondary); margin-bottom: 2rem; }

        .btn-add {
            width: 100%; padding: 14px; background: var(--emerald); border: none;
            border-radius: var(--radius-md); color: #000; font-weight: 700; font-size: 1rem; font-family: var(--font);
            cursor: pointer; transition: var(--transition); display: flex; align-items: center; justify-content: center; gap: 8px; margin-bottom: 2rem;
        }
        .btn-add:hover { background: #34d399; box-shadow: 0 4px 15px var(--emerald-glow); transform: translateY(-2px); }

        .form-group { margin-bottom: 1.25rem; display: flex; flex-direction: column; gap: 6px;}
        .form-label { color: var(--text-secondary); font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; display: flex; justify-content: space-between; align-items: center; }
        
        .form-control, .form-select {
            width: 100%; padding: 12px 14px; background: var(--bg-elevated); border: 1px solid var(--border);
            border-radius: var(--radius-md); color: var(--text-primary); font-size: 0.95rem; transition: var(--transition); outline: none; box-sizing: border-box; font-family: var(--font);
        }
        .form-select {
            appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2394a3b8' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 12px center; cursor: pointer;
        }
        .form-control:focus, .form-select:focus { border-color: var(--emerald); box-shadow: 0 0 0 3px var(--emerald-glow); background: var(--bg-hover); }
        .form-select:disabled, .form-control:disabled { opacity: 0.5; cursor: not-allowed; background: rgba(255,255,255,0.02); border-color: transparent;}

        .search-container { position: relative; margin-bottom: 1.5rem; }
        .search-input { width: 100%; padding: 12px 14px 12px 40px; background: var(--bg-elevated); border: 1px solid var(--border); border-radius: var(--radius-md); color: var(--text-primary); font-size: 0.95rem; outline: none; transition: var(--transition); font-family: var(--font);}
        .search-input:focus { border-color: var(--emerald); box-shadow: 0 0 0 3px var(--emerald-glow); }
        .search-icon { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--text-muted); pointer-events: none;}

        /* Lock Badge Wrapper */
        .select-wrap { position: relative; display: flex; align-items: center;}
        .select-wrap .form-control, .select-wrap .form-select { flex: 1; }
        .select-wrap .lock-badge { display: none; position: absolute; right: 14px; color: var(--emerald); font-size: 0.9rem; pointer-events: none;}
        .select-wrap.locked .lock-badge { display: block; }
        .select-wrap.locked .form-select { border-color: rgba(16,185,129,0.4); background: var(--emerald-dim); opacity: 0.9; cursor: not-allowed; padding-right: 35px;}

        /* ─── WORKSPACE (RIGHT) ─── */
        .workspace-panel { display: flex; flex-direction: column; gap: 1.5rem; }
        
        .table-section { background: var(--bg-surface); border: 1px solid var(--border); border-radius: var(--radius-xl); overflow: hidden; box-shadow: var(--shadow-md);}
        .section-header { display: flex; justify-content: space-between; align-items: center; padding: 1.5rem; border-bottom: 1px solid var(--border); background: var(--bg-elevated); flex-wrap: wrap; gap: 1rem;}
        .section-title { font-size: 1.15rem; font-weight: 700; color: #fff; display: flex; align-items: center; gap: 10px;}
        .section-title i { color: var(--emerald); }

        .table-scroll-wrapper { overflow-x: auto; }
        .table-scroll-wrapper::-webkit-scrollbar { height: 8px; }
        .table-scroll-wrapper::-webkit-scrollbar-track { background: var(--bg-surface); }
        .table-scroll-wrapper::-webkit-scrollbar-thumb { background: #334155; border-radius: 4px; }

        .data-table { width: 100%; border-collapse: collapse; min-width: 800px; }
        .data-table th {
            background: var(--bg-base); color: var(--text-muted); font-size: 0.7rem; text-transform: uppercase;
            letter-spacing: 0.05em; padding: 16px; text-align: left; font-weight: 700; border-bottom: 1px solid var(--border);
        }
        .data-table td { padding: 14px 16px; border-bottom: 1px solid rgba(255,255,255,0.03); color: var(--text-primary); vertical-align: middle;}
        .data-table tr:hover { background: rgba(255,255,255,0.01); }

        .col-id { font-family: var(--font-mono); color: var(--text-muted); font-size: 0.85rem; }
        .col-name { font-weight: 700; color: #fff; font-size: 1.05rem; }
        .path-info { display: flex; align-items: center; gap: 6px; font-size: 0.85rem; color: var(--text-secondary); }
        .path-loc { background: rgba(255,255,255,0.05); padding: 4px 8px; border-radius: 6px; color: var(--text-primary); font-weight: 600; border: 1px solid var(--border);}
        
        .count-badge { font-family: var(--font-mono); font-weight: 700; color: var(--emerald); background: var(--emerald-dim); padding: 4px 12px; border-radius: 6px; border: 1px solid rgba(16,185,129,0.2); display: inline-flex; align-items: center; gap: 6px;}
        .count-badge.empty { color: var(--red); background: var(--red-dim); border-color: rgba(239,68,68,0.3); }

        .actions { display: flex; gap: 8px; justify-content: center;}
        .action-btn {
            width: 34px; height: 34px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg-elevated);
            display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all var(--transition); color: var(--text-secondary);
        }
        .action-btn:hover { background: var(--bg-hover); color: #fff; }
        .action-btn.edit:hover { color: var(--blue); border-color: var(--blue); background: var(--blue-dim);}
        .action-btn.delete:hover { color: var(--red); border-color: var(--red); background: var(--red-dim);}

        .empty-state { text-align: center; padding: 4rem 2rem; color: var(--text-muted); font-style: italic; }

        /* ─── MODALS ─── */
        .modal-overlay {
            position: fixed; inset: 0; background: rgba(0,0,0,0.85); backdrop-filter: blur(5px);
            z-index: 2000; display: none; align-items: center; justify-content: center; padding: 1rem;
            opacity: 0; transition: opacity 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .modal-overlay.show { display: flex; opacity: 1; }
        
        .modal-card {
            background: var(--bg-surface); border: 1px solid var(--border); border-radius: var(--radius-xl);
            width: 100%; max-width: 500px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5); transform: scale(0.95); opacity: 0;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); position: relative; overflow: hidden; display: flex; flex-direction: column;
        }
        .modal-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px; background: linear-gradient(90deg, var(--emerald), #047857); }
        .modal-overlay.show .modal-card { transform: scale(1); opacity: 1; }

        .modal-header { padding: 1.5rem 2rem; border-bottom: 1px solid var(--border); background: var(--bg-elevated); display: flex; justify-content: space-between; align-items: center;}
        .modal-header h2 { margin: 0; font-size: 1.25rem; font-weight: 700; color: #fff; display: flex; align-items: center; gap: 10px;}
        .modal-header h2 i { color: var(--emerald); }
        .btn-close { background: transparent; border: none; color: var(--text-muted); font-size: 1.5rem; cursor: pointer; transition: color var(--transition); }
        .btn-close:hover { color: var(--red); }
        
        .modal-body { padding: 2rem; overflow-y: auto; }
        .modal-footer { padding: 1.25rem 2rem; border-top: 1px solid var(--border); display: flex; justify-content: flex-end; gap: 10px; background: var(--bg-elevated); border-radius: 0 0 var(--radius-xl) var(--radius-xl);}

        .btn-cancel { padding: 12px 24px; background: transparent; border: 1px solid var(--border); color: var(--text-secondary); border-radius: var(--radius-md); cursor: pointer; font-weight: 700; font-family: var(--font); transition: var(--transition);}
        .btn-cancel:hover { background: var(--bg-hover); color: #fff; border-color: var(--text-muted);}
        .btn-confirm { padding: 12px 24px; background: var(--emerald); border: none; color: #000; border-radius: var(--radius-md); font-weight: 700; font-family: var(--font); cursor: pointer; transition: var(--transition); box-shadow: 0 4px 15px rgba(16,185,129,0.2); display: inline-flex; align-items: center; gap: 8px;}
        .btn-confirm:hover { background: #34d399; transform: translateY(-1px); box-shadow: 0 6px 20px rgba(16,185,129,0.4);}

        /* Stats Grid */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
        .stat-card { background: var(--bg-elevated); border: 1px solid var(--border); padding: 1.25rem; border-radius: var(--radius-md); text-align: center; }
        .stat-val { font-size: 1.75rem; font-weight: 700; margin-bottom: 0.25rem; }
        .stat-label { color: var(--text-secondary); font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; font-weight: 700; }

        /* Toast Notifications */
        #toastContainer { position: fixed; top: 20px; right: 20px; z-index: 9999; display: flex; flex-direction: column; gap: 10px; }
        .toast {
            background: var(--bg-surface); border: 1px solid var(--border); color: #fff;
            padding: 1rem 1.5rem; border-radius: var(--radius-md); box-shadow: 0 10px 25px rgba(0,0,0,0.5);
            font-size: 0.9rem; font-weight: 600; animation: slideIn 0.3s ease-out; display: flex; align-items: center; gap: 8px;
        }
        @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }

        /* ─── RESPONSIVE ─── */
        @media (max-width: 1024px) {
            .main-grid { grid-template-columns: 1fr; }
            .control-panel { position: relative; top: 0; }
        }
        @media (max-width: 768px) {
            .container { padding: 1rem; }
            .page-header { flex-direction: column; align-items: flex-start; }
            
            /* UI & Modal Adjustments */
            .modal-header, .modal-footer { padding: 1rem 1.25rem; }
            .modal-body { padding: 1.25rem; }
            .modal-footer { flex-direction: column-reverse; gap: 0.75rem; }
            .btn-cancel, .btn-confirm { width: 100%; justify-content: center; }
            
            /* Table to Cards CSS fixes */
            .table-section { background: transparent; border: none; box-shadow: none; }
            .section-header { background: var(--bg-surface); border-radius: var(--radius-lg); margin-bottom: 1rem; border: 1px solid var(--border); }
            .table-scroll-wrapper { overflow-x: visible; }
            
            /* CRITICAL FIX: Override the 800px min-width */
            .data-table { min-width: 100%; } 
            .data-table thead { display: none; }
            .data-table, .data-table tbody, .data-table tr, .data-table td { display: block; width: 100%; box-sizing: border-box; }
            
            .data-table tr {
                background: var(--bg-surface); border: 1px solid var(--border);
                border-radius: var(--radius-lg); margin-bottom: 1rem; padding: 1rem;
                box-shadow: var(--shadow-md);
            }
            .data-table td {
                display: flex; justify-content: space-between; align-items: center; text-align: right;
                padding: 0.75rem 0; border-bottom: 1px dashed rgba(255,255,255,0.05); white-space: normal;
                gap: 1rem;
            }
            .data-table td:last-child { border-bottom: none; padding-top: 1rem; padding-bottom: 0; justify-content: flex-end;}
            
            .data-table td::before {
                content: attr(data-label); font-weight: 700; color: var(--text-muted);
                font-size: 0.75rem; text-transform: uppercase; text-align: left; flex-shrink: 0; margin-top: 2px;
            }
            .path-info { justify-content: flex-end; }
        }
    </style>
</head>
<body>

<div id="toastContainer"></div>

<div class="container">
    
    <div class="top-bar">
        <a href="admin_dashboard.php" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back to Dashboard
        </a>
        <span class="page-badge"><i class="fa-solid fa-border-all"></i> Inventory Units</span>
    </div>

    <header class="page-header">
        <div class="header-info">
            <h1>Building <span>Management</span></h1>
            <p>Configure structural units and monitor pen allocations.</p>
        </div>
    </header>

    <div class="main-grid">

        <div class="control-panel">
            <button class="btn-add" onclick="openAddModal()">
                <i class="fa-solid fa-plus"></i> Add Building
            </button>

            <div class="panel-title"><i class="fa-solid fa-filter"></i> Refine List</div>
            <div class="panel-subtitle">Filter and search existing buildings.</div>

            <div class="search-container">
                <i class="fa-solid fa-magnifying-glass search-icon"></i>
                <input type="text" id="searchInput" class="search-input" placeholder="Search buildings by name or location..." onkeyup="filterTable()">
            </div>

            <div style="border-top: 1px dashed var(--border); margin: 1.5rem 0;"></div>

            <div class="form-group">
                <label class="form-label">Sort Data</label>
                <select id="sortSelect" class="form-select" onchange="sortDropdown(this.value)">
                    <option value="name_asc">Building Name (A-Z)</option>
                    <option value="name_desc">Building Name (Z-A)</option>
                    <option value="count_desc">Most Pens</option>
                    <option value="count_asc">Least Pens</option>
                </select>
            </div>

        </div>

        <div class="workspace-panel">
            <div class="table-section">
                <div class="section-header">
                    <div class="section-title"><i class="fa-solid fa-list-ul"></i> Active Buildings Directory</div>
                </div>
                
                <div class="table-scroll-wrapper">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th style="width: 120px; padding-left: 1.5rem;">ID</th>
                                <th>Building Details</th>
                                <th>Location</th> 
                                <th>Capacity</th> 
                                <th style="text-align: center; width: 150px; padding-right: 1.5rem;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="building-table">
                            <?php foreach($building_data as $data): ?>
                            <tr data-id="<?php echo $data['BUILDING_ID']; ?>" 
                                data-location-id="<?php echo $data['LOCATION_ID']; ?>"
                                data-name="<?php echo htmlspecialchars($data['BUILDING_NAME']); ?>"
                                data-count="<?php echo $data['PEN_COUNT']; ?>">
                                
                                <td data-label="ID" class="col-id" style="padding-left: 1.5rem;">#<?php echo $data['BUILDING_ID']; ?></td>
                                
                                <td data-label="Building">
                                    <div class="building-info">
                                        <div class="building-details">
                                            <h3 class="building-name-display col-name"><?php echo htmlspecialchars($data['BUILDING_NAME']); ?></h3>
                                        </div>
                                    </div>
                                </td>
                                
                                <td data-label="Location">
                                    <span class="path-loc">
                                        <i class="fa-solid fa-location-dot me-1"></i> <?php echo htmlspecialchars($data['LOCATION_NAME'] ?? 'N/A'); ?>
                                    </span>
                                </td>

                                <td data-label="Capacity">
                                    <span class="count-badge <?php echo ($data['PEN_COUNT'] == 0) ? 'empty' : ''; ?>">
                                        <?php echo $data['PEN_COUNT']; ?> PENS
                                    </span>
                                </td>
                                
                                <td data-label="Actions" style="padding-right: 1.5rem;">
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
    </div>
</div>

<div id="viewModal" class="modal-overlay">
    <div class="modal-card" style="max-width: 600px;">
        <div class="modal-header">
            <h2 id="view_building_name">Building Details</h2>
            <button type="button" class="btn-close" onclick="closeViewModal()"><i class="fa-solid fa-xmark"></i></button>
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
            
            <div class="table-section" style="border-radius: var(--radius-md);">
                <div class="table-scroll-wrapper">
                    <table class="data-table" style="min-width: 100%;">
                        <thead>
                            <tr>
                                <th style="padding-left: 1.5rem;">Pen Name</th>
                                <th>Status</th>
                                <th style="text-align: right; padding-right: 1.5rem;">Animals</th>
                            </tr>
                        </thead>
                        <tbody id="view-pens-list"></tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-cancel" onclick="closeViewModal()" style="width: 100%;">Close Window</button>
        </div>
    </div>
</div>

<div id="addModal" class="modal-overlay">
    <div class="modal-card">
        <div class="modal-header"><h2><i class="fa-solid fa-plus-circle"></i> Add New Building</h2>
            <button type="button" class="btn-close" onclick="closeAddModal()"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body">
            <form id="addBuildingForm" method="POST" action="../process/addBuilding.php">
                <div class="form-group">
                    <label class="form-label">Building Name <span style="color:var(--red);">*</span></label>
                    <input type="text" class="form-control" id="add_building_name" name="building_name" placeholder="e.g. Farrowing House A" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Physical Location <span style="color:var(--red);">*</span></label>
                    <div class="select-wrap" id="wrap-add-loc">
                        <select class="form-select" name="location_id" id="add_building_location_select" onchange="updateAddressField('add')" required <?php echo ($USER_LOCATION_ != 1000) ? 'disabled' : ''; ?>>
                            <option value="">-- Select Location --</option>
                            <?php foreach($location_data as $loc): ?>
                                <option value="<?php echo $loc['LOCATION_ID']; ?>" data-address="<?php echo htmlspecialchars($loc['COMPLETE_ADDRESS']); ?>" <?php echo ($USER_LOCATION_ != 1000 && $loc['LOCATION_ID'] == $USER_LOCATION_) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($loc['LOCATION_NAME']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <i class="fa-solid fa-lock lock-badge"></i>
                        <?php if($USER_LOCATION_ != 1000): ?>
                            <input type="hidden" name="dummy_loc" value="<?php echo $USER_LOCATION_; ?>">
                        <?php endif; ?>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Site Address</label>
                    <input type="text" id="add_location_complete_address" class="form-control" disabled style="opacity:50%;" placeholder="Select location to see address">
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-cancel" onclick="closeAddModal()">Cancel</button>
            <button type="button" class="btn-confirm" onclick="submitAddForm()"><i class="fa-solid fa-floppy-disk"></i> Save Building</button>
        </div>
    </div>
</div>

<div id="editModal" class="modal-overlay">
    <div class="modal-card">
        <div class="modal-header"><h2><i class="fa-solid fa-pen-to-square"></i> Edit Building</h2>
            <button type="button" class="btn-close" onclick="closeEditModal()"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body">
            <form id="editBuildingForm" method="POST" action="../process/updateBuilding.php">
                <input type="hidden" id="edit_building_id" name="building_id">
                <div class="form-group">
                    <label class="form-label">Building Name <span style="color:var(--red);">*</span></label>
                    <input type="text" class="form-control" id="edit_building_name" name="building_name" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Update Location</label>
                    <div class="select-wrap" id="wrap-edit-loc">
                        <select class="form-select" name="location_id" id="edit_building_location_select" onchange="updateAddressField('edit')" required <?php echo ($USER_LOCATION_ != 1000) ? 'disabled' : ''; ?>>
                            <?php foreach($location_data as $loc): ?>
                                <option value="<?php echo $loc['LOCATION_ID']; ?>" data-address="<?php echo htmlspecialchars($loc['COMPLETE_ADDRESS']); ?>">
                                    <?php echo htmlspecialchars($loc['LOCATION_NAME']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <i class="fa-solid fa-lock lock-badge"></i>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Site Address</label>
                    <input type="text" id="edit_location_complete_address" class="form-control" disabled style="opacity:50%;">
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-cancel" onclick="closeEditModal()">Cancel</button>
            <button type="button" class="btn-confirm" onclick="submitEditForm()"><i class="fa-solid fa-arrows-rotate"></i> Update Changes</button>
        </div>
    </div>
</div>

<form id="deleteBuildingForm" method="POST" action="../process/deleteBuilding.php" style="display: none;">
    <input type="hidden" id="delete_building_id" name="building_id">
</form>

<script>
    const USER_LOCATION = <?php echo json_encode($USER_LOCATION_); ?>;

    document.addEventListener('DOMContentLoaded', function() {
        // Initialize Toasts from PHP redirect messages
        <?php if (!empty($msg)): ?>
            showToast("<?= addslashes(urldecode($msg)) ?>", "<?= $status === 'success' ? 'success' : 'error' ?>");
            window.history.replaceState(null, null, window.location.pathname);
        <?php endif; ?>

        // Pre-load location behavior if restricted
        if (USER_LOCATION != 1000) {
            document.getElementById('wrap-add-loc').classList.add('locked');
            document.getElementById('wrap-edit-loc').classList.add('locked');
        }
        updateAddressField('add');
    });

    function showToast(msg, type = 'success') {
        const t = document.createElement('div');
        t.className = 'toast';
        t.style.borderLeft = `4px solid ${type === 'error' ? 'var(--red)' : 'var(--emerald)'}`;
        t.innerHTML = `${type === 'error' ? '<i class="fa-solid fa-xmark"></i>' : '<i class="fa-solid fa-check"></i>'} ${msg}`;
        document.getElementById('toastContainer').appendChild(t);
        setTimeout(() => t.remove(), 3500);
    }

    // --- JS TRUE NATURAL SORTING ---
    function sortDropdown(val) {
        const tbody = document.getElementById('building-table');
        const rows = Array.from(tbody.querySelectorAll('tr:not(.empty-state)'));
        if(rows.length === 0) return;

        // Sort the raw DOM elements using localeCompare with numeric mode enabled
        rows.sort((a, b) => {
            if (val === 'name_asc') {
                return a.dataset.name.localeCompare(b.dataset.name, undefined, { numeric: true, sensitivity: 'base' });
            }
            if (val === 'name_desc') {
                return b.dataset.name.localeCompare(a.dataset.name, undefined, { numeric: true, sensitivity: 'base' });
            }
            
            if (val === 'count_desc') return parseInt(b.dataset.count) - parseInt(a.dataset.count);
            if (val === 'count_asc') return parseInt(a.dataset.count) - parseInt(b.dataset.count);
        });
        
        // Re-append to DOM in sorted order
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
                    ? `<span class="count-badge" style="background:var(--red-dim); color:var(--red); border:1px solid rgba(248,113,113,0.2)">Occupied</span>`
                    : `<span class="count-badge" style="background:var(--green-dim); color:var(--green); border:1px solid rgba(34,197,94,0.2)">Empty</span>`;

                html += `<tr>
                    <td data-label="Pen Name" class="col-name">${p.PEN_NAME}</td>
                    <td data-label="Status">${badge}</td>
                    <td data-label="Animals" style="font-family:var(--font-mono); color:var(--text-secondary);">${count} Heads</td>
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
        document.getElementById('edit_building_name').value = row.querySelector('.col-name').textContent.trim();
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
        const name = button.closest('tr').querySelector('.col-name').textContent.trim();
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

    // Close on outside click
    window.addEventListener('click', (e) => {
        if (e.target.classList.contains('modal-overlay')) { closeAddModal(); closeEditModal(); closeViewModal(); }
    });
</script>
</body>
</html>