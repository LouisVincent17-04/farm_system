<?php
// views/pen.php
error_reporting(0);
ini_set('display_errors', 0);

$page="admin_dashboard";
include '../config/Connection.php';

include '../security/checkAccess.php';
checkAccess('pen');

include '../common/navbar.php';
include '../common/chat_support.php';
include '../functions/getUsersLocation.php';

if($_SESSION['user']['USER_TYPE'] < 3)
{
    echo "<script>alert('Access denied.'); window.location.href = 'admin_dashboard.php';</script>";
    exit();
}

// Check for status messages
$status = $_GET['status'] ?? '';
$msg = $_GET['msg'] ?? '';

try {
    if (!isset($conn)) {
        throw new Exception("Database connection failed.");
    }

    // 1. Fetch Pens with Building, Location Names, and Animal Counts
    $sql = "
        SELECT 
            p.PEN_ID, 
            p.PEN_NAME, 
            p.BUILDING_ID, 
            b.BUILDING_NAME,
            l.LOCATION_ID,
            l.LOCATION_NAME,
            (SELECT COUNT(a.ANIMAL_ID) FROM animal_records a WHERE a.PEN_ID = p.PEN_ID AND a.IS_ACTIVE = 1 AND a.CURRENT_STATUS != 'Sold') as ANIMAL_COUNT
        FROM PENS p
        LEFT JOIN BUILDINGS b ON p.BUILDING_ID = b.BUILDING_ID
        LEFT JOIN LOCATIONS l ON b.LOCATION_ID = l.LOCATION_ID
    ";

    // Apply location restriction if user is not Super Admin
    if ($USER_LOCATION_ != 1000) {
        $sql .= " WHERE l.LOCATION_ID = :loc_id ";
    }

    $sql .= " ORDER BY p.PEN_ID ASC";
    
    $stmt = $conn->prepare($sql);
    if ($USER_LOCATION_ != 1000) {
        $stmt->execute([':loc_id' => $USER_LOCATION_]);
    } else {
        $stmt->execute();
    }
    $pen_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Fetch Locations for Dropdown
    if ($USER_LOCATION_ != 1000) {
        $loc_stmt = $conn->prepare("SELECT LOCATION_ID, LOCATION_NAME FROM LOCATIONS WHERE LOCATION_ID = ? ORDER BY LOCATION_NAME ASC");
        $loc_stmt->execute([$USER_LOCATION_]);
        $locations_data = $loc_stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $locations_data = $conn->query("SELECT LOCATION_ID, LOCATION_NAME FROM LOCATIONS ORDER BY LOCATION_NAME ASC")->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (Exception $e) {
    $pen_data = [];
    $locations_data = [];
    $status = 'error';
    $msg = "Database Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Pen Management | FarmPro</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=DM+Mono:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />

    <style>
        /* ─── CSS VARIABLES ─── */
        :root {
            --bg-base:        #080f1a;
            --bg-surface:     #0d1829;
            --bg-elevated:    #111f35;
            --bg-hover:       #162540;
            --border:         rgba(255,255,255,0.07);
            --border-active:  rgba(16,185,129,0.5); 
            
            --emerald:        #10b981;
            --emerald-dim:    rgba(16,185,129,0.12);
            --emerald-glow:   rgba(16,185,129,0.25);
            --blue:           #3b82f6;
            --amber:          #f59e0b;
            --red:            #f87171;
            --red-dim:        rgba(239,68,68,0.12);
            
            --text-primary:   #f1f5f9;
            --text-secondary: #94a3b8;
            --text-muted:     #475569;
            
            --radius-md:      10px;
            --radius-lg:      14px;
            --radius-xl:      20px;
            --shadow-md:      0 4px 16px rgba(0,0,0,0.4);
            --font:           'DM Sans', system-ui, sans-serif;
            --font-mono:      'DM Mono', monospace;
            --transition:     0.2s cubic-bezier(0.4, 0, 0.2, 1);
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

        /* ─── PAGINATION ─── */
        .pagination { display: flex; justify-content: center; gap: 8px; padding: 1.5rem; flex-wrap: wrap; background: var(--bg-surface); border-top: 1px solid var(--border); }
        .pg-btn {
            background: var(--bg-elevated); border: 1px solid var(--border); color: var(--text-secondary);
            padding: 8px 14px; border-radius: 8px; cursor: pointer; font-size: 0.95rem; font-weight: 700; transition: var(--transition); font-family: var(--font);
        }
        .pg-btn.active { background: var(--emerald); color: #000; border-color: var(--emerald); }
        .pg-btn:disabled { opacity: 0.5; cursor: not-allowed; }
        .pg-btn:hover:not(.active):not(:disabled) { background: var(--bg-hover); color: #fff; }

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
            
            /* Table to Cards */
            .table-wrap { border: none; background: transparent; overflow: visible; box-shadow: none; }
            .data-table thead { display: none; }
            .data-table, .data-table tbody, .data-table tr, .data-table td { display: block; width: 100%; box-sizing: border-box; }
            
            .data-table tr {
                background: var(--bg-surface); border: 1px solid var(--border);
                border-radius: var(--radius-lg); margin-bottom: 1rem; padding: 1.25rem;
                box-shadow: var(--shadow-md);
            }
            .data-table td {
                display: flex; justify-content: space-between; align-items: center; text-align: right;
                padding: 0.6rem 0; border-bottom: 1px dashed rgba(255,255,255,0.05); white-space: normal;
            }
            .data-table td:last-child { border-bottom: none; padding-top: 1rem; justify-content: flex-end;}
            
            .data-table td::before {
                content: attr(data-label); font-weight: 700; color: var(--text-muted);
                font-size: 0.75rem; text-transform: uppercase; margin-right: 1rem; text-align: left; flex-shrink: 0;
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
            <h1>Pen <span>Management</span></h1>
            <p>Organize, monitor, and manage specific containment units within buildings.</p>
        </div>
    </header>

    <div class="main-grid">

        <div class="control-panel">
            <button class="btn-add" onclick="openAddModal()">
                <i class="fa-solid fa-plus"></i> Register New Pen
            </button>

            <div class="panel-title"><i class="fa-solid fa-filter"></i> Refine List</div>
            <div class="panel-subtitle">Filter and search existing containment units.</div>

            <div class="search-container">
                <i class="fa-solid fa-magnifying-glass search-icon"></i>
                <input type="text" id="searchInput" class="search-input" placeholder="Search by name, building..." onkeyup="applyFiltersAndPagination()">
            </div>

            <div style="border-top: 1px dashed var(--border); margin: 1.5rem 0;"></div>

            <div class="form-group">
                <label class="form-label">Filter by Location</label>
                <div class="select-wrap" id="wrap-filter-location">
                    <select id="filterLocation" class="form-select" onchange="loadBuildingsFilter(this.value)" <?php echo ($USER_LOCATION_ != 1000) ? 'disabled' : ''; ?>>
                        <option value="">-- All Locations --</option>
                        <?php foreach($locations_data as $loc): ?>
                            <option value="<?php echo $loc['LOCATION_ID']; ?>" <?php echo ($USER_LOCATION_ != 1000 && $loc['LOCATION_ID'] == $USER_LOCATION_) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($loc['LOCATION_NAME']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <i class="fa-solid fa-lock lock-badge"></i>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Filter by Building</label>
                <select id="filterBuilding" class="form-select" onchange="applyFiltersAndPagination()">
                    <option value="">-- All Buildings --</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Sort Data</label>
                <select id="sortSelect" class="form-select" onchange="sortData(this.value)">
                    <option value="name_asc">Pen Name (A-Z)</option>
                    <option value="name_desc">Pen Name (Z-A)</option>
                    <option value="count_desc">Highest Occupancy</option>
                    <option value="count_asc">Lowest Occupancy</option>
                </select>
            </div>

        </div>

        <div class="workspace-panel">
            <div class="table-section">
                <div class="section-header">
                    <div class="section-title"><i class="fa-solid fa-list-ul"></i> Active Pens Directory</div>
                </div>
                
                <div class="table-scroll-wrapper">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th style="padding-left: 1.5rem;">Pen Designation</th>
                                <th>Placement Path</th>
                                <th>Current Occupancy</th>
                                <th style="text-align: center; width: 120px; padding-right: 1.5rem;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="pen-table">
                            <?php foreach($pen_data as $data): ?>
                            <tr data-id="<?php echo $data['PEN_ID']; ?>" 
                                data-bldg="<?php echo $data['BUILDING_ID']; ?>"
                                data-loc="<?php echo $data['LOCATION_ID']; ?>"
                                data-name="<?php echo htmlspecialchars(strtolower($data['PEN_NAME'])); ?>"
                                data-count="<?php echo $data['ANIMAL_COUNT']; ?>">
                                
                                <td data-label="Pen Designation" class="col-name" style="padding-left: 1.5rem;"><?php echo htmlspecialchars($data['PEN_NAME']); ?></td>
                                
                                <td data-label="Placement Path">
                                    <div class="path-info">
                                        <span class="path-loc"><i class="fa-solid fa-location-dot"></i> <?php echo htmlspecialchars($data['LOCATION_NAME'] ?? 'N/A'); ?></span>
                                        <i class="fa-solid fa-chevron-right" style="font-size:0.7rem; color:var(--text-muted);"></i>
                                        <span><?php echo htmlspecialchars($data['BUILDING_NAME'] ?? 'N/A'); ?></span>
                                    </div>
                                </td>

                                <td data-label="Current Occupancy">
                                    <span class="count-badge <?php echo ($data['ANIMAL_COUNT'] == 0) ? 'empty' : ''; ?>">
                                        <?php if ($data['ANIMAL_COUNT'] == 0): ?>
                                            <i class="fa-solid fa-xmark"></i> Empty
                                        <?php else: ?>
                                            <i class="fa-solid fa-paw"></i> <?php echo $data['ANIMAL_COUNT']; ?> Active
                                        <?php endif; ?>
                                    </span>
                                </td>
                                
                                <td data-label="Actions" style="padding-right: 1.5rem;">
                                    <div class="actions">
                                        <button class="action-btn edit" onclick="editPen(this)" title="Edit Pen"><i class="fa-solid fa-pen-to-square"></i></button>
                                        <button class="action-btn delete" onclick="deletePen(this)" title="Delete Pen"><i class="fa-solid fa-trash-can"></i></button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div id="empty-state" class="empty-state" style="display:none;">
                        <i class="fa-solid fa-magnifying-glass" style="font-size: 2.5rem; display: block; margin-bottom: 1rem; opacity: 0.5;"></i>
                        No containment units match your search filters.
                    </div>
                </div>

                <div class="pagination" id="paginationControls"></div>
            </div>
        </div>
    </div>
</div>

<div id="addModal" class="modal-overlay">
    <div class="modal-card">
        <div class="modal-header">
            <h2><i class="fa-solid fa-plus-circle"></i> Register New Pen</h2>
            <button type="button" class="btn-close" onclick="closeAddModal()"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body">
            <form id="addPenForm" method="POST" action="../process/addPen.php">
                <div class="form-group">
                    <label class="form-label">Pen Designation <span style="color:var(--red);">*</span></label>
                    <input type="text" class="form-control" id="add_pen_name" name="pen_name" placeholder="e.g. Pen A-101" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Parent Location <span style="color:var(--red);">*</span></label>
                    <div class="select-wrap" id="wrap-add-loc">
                        <select class="form-select" id="add_location_id" onchange="loadBuildingsModal(this.value, 'add_building_id')" required <?php echo ($USER_LOCATION_ != 1000) ? 'disabled' : ''; ?>>
                            <option value="">-- Select Location --</option>
                            <?php foreach($locations_data as $loc): ?>
                                <option value="<?php echo $loc['LOCATION_ID']; ?>" <?php echo ($USER_LOCATION_ != 1000 && $loc['LOCATION_ID'] == $USER_LOCATION_) ? 'selected' : ''; ?>>
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
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label">Assigned Building <span style="color:var(--red);">*</span></label>
                    <select class="form-select" name="building_id" id="add_building_id" required disabled>
                        <option value="">-- Select Location First --</option>
                    </select>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-cancel" onclick="closeAddModal()">Cancel</button>
            <button type="button" class="btn-confirm" onclick="submitAddForm()"><i class="fa-solid fa-floppy-disk"></i> Save Pen</button>
        </div>
    </div>
</div>

<div id="editModal" class="modal-overlay">
    <div class="modal-card">
        <div class="modal-header">
            <h2><i class="fa-solid fa-pen-to-square"></i> Edit Pen Details</h2>
            <button type="button" class="btn-close" onclick="closeEditModal()"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body">
            <form id="editPenForm" method="POST" action="../process/updatePen.php">
                <input type="hidden" id="edit_pen_id" name="pen_id">
                <div class="form-group">
                    <label class="form-label">Pen Designation <span style="color:var(--red);">*</span></label>
                    <input type="text" class="form-control" id="edit_pen_name" name="pen_name" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Parent Location <span style="color:var(--red);">*</span></label>
                    <div class="select-wrap" id="wrap-edit-loc">
                        <select class="form-select" id="edit_location_id" onchange="loadBuildingsModal(this.value, 'edit_building_id')" required <?php echo ($USER_LOCATION_ != 1000) ? 'disabled' : ''; ?>>
                            <option value="">-- Select Location --</option>
                            <?php foreach($locations_data as $loc): ?>
                                <option value="<?php echo $loc['LOCATION_ID']; ?>">
                                    <?php echo htmlspecialchars($loc['LOCATION_NAME']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <i class="fa-solid fa-lock lock-badge"></i>
                    </div>
                </div>
                <div class="form-group" style="margin-bottom:0;"> 
                    <label class="form-label">Assigned Building <span style="color:var(--red);">*</span></label>
                    <select class="form-select" name="building_id" id="edit_building_id" required>
                        <option value="">-- Select Building --</option>
                    </select>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-cancel" onclick="closeEditModal()">Cancel</button>
            <button type="button" class="btn-confirm" onclick="submitEditForm()"><i class="fa-solid fa-arrows-rotate"></i> Update Changes</button>
        </div>
    </div>
</div>

<form id="deletePenForm" method="POST" action="../process/deletePen.php" style="display: none;">
    <input type="hidden" id="delete_pen_id" name="pen_id">
</form>

<script>
    const USER_LOCATION = <?php echo json_encode($USER_LOCATION_); ?>;

    // --- CLIENT SIDE PAGINATION VARIABLES ---
    let currentPage = 1;
    const rowsPerPage = 10; // Change this if you want more rows per page

    document.addEventListener('DOMContentLoaded', function() {
        // Initialize Toasts from PHP redirect messages
        <?php if (!empty($msg)): ?>
            showToast("<?= addslashes(urldecode($msg)) ?>", "<?= $status === 'success' ? 'success' : 'error' ?>");
            window.history.replaceState(null, null, window.location.pathname);
        <?php endif; ?>

        // Pre-load buildings for filters and add modal if user is restricted
        if (USER_LOCATION != 1000) {
            loadBuildingsFilter(USER_LOCATION);
            loadBuildingsModal(USER_LOCATION, 'add_building_id');
            document.getElementById('wrap-filter-location').classList.add('locked');
            document.getElementById('wrap-add-loc').classList.add('locked');
            document.getElementById('wrap-edit-loc').classList.add('locked');
        } else {
            // Apply initial pagination if not restricted
            applyFiltersAndPagination();
        }
    });

    function showToast(msg, type = 'success') {
        const t = document.createElement('div');
        t.className = 'toast';
        t.style.borderLeft = `4px solid ${type === 'error' ? 'var(--red)' : 'var(--emerald)'}`;
        t.innerHTML = `${type === 'error' ? '<i class="fa-solid fa-xmark"></i>' : '<i class="fa-solid fa-check"></i>'} ${msg}`;
        document.getElementById('toastContainer').appendChild(t);
        setTimeout(() => t.remove(), 3500);
    }

    // --- SORTING ---
    function sortData(val) {
        const tbody = document.getElementById('pen-table');
        const rows = Array.from(tbody.querySelectorAll('tr:not(.empty-state)'));
        if(rows.length === 0) return;

        // Sort the raw DOM elements
        rows.sort((a, b) => {
            if (val === 'name_asc') return a.dataset.name.localeCompare(b.dataset.name);
            if (val === 'name_desc') return b.dataset.name.localeCompare(a.dataset.name);
            if (val === 'count_desc') return parseInt(b.dataset.count) - parseInt(a.dataset.count);
            if (val === 'count_asc') return parseInt(a.dataset.count) - parseInt(b.dataset.count);
        });
        
        // Re-append to DOM in sorted order
        rows.forEach(row => tbody.appendChild(row));
        
        // Reset to page 1 and re-paginate
        currentPage = 1;
        applyFiltersAndPagination();
    }

    // --- FILTER & PAGINATION LOGIC ---
    function applyFiltersAndPagination() {
        const term = document.getElementById('searchInput').value.toLowerCase();
        const fLoc = document.getElementById('filterLocation').value;
        const fBldg = document.getElementById('filterBuilding').value;
        
        const tbody = document.getElementById('pen-table');
        const rows = Array.from(tbody.querySelectorAll('tr:not(.empty-state)'));
        
        // 1. Determine which rows match the filters
        let filteredRows = [];
        rows.forEach(r => {
            const text = r.innerText.toLowerCase();
            const rLoc = r.dataset.loc;
            const rBldg = r.dataset.bldg;

            const matchTerm = text.includes(term);
            const matchLoc = fLoc === "" || rLoc === fLoc;
            const matchBldg = fBldg === "" || rBldg === fBldg;

            if(matchTerm && matchLoc && matchBldg) {
                filteredRows.push(r);
            } else {
                r.style.display = 'none'; // Hide non-matching immediately
            }
        });

        // 2. Calculate Pagination
        const totalPages = Math.ceil(filteredRows.length / rowsPerPage);
        
        // Safety bounds for current page
        if (currentPage > totalPages && totalPages > 0) currentPage = totalPages;
        if (currentPage < 1) currentPage = 1;

        const startIndex = (currentPage - 1) * rowsPerPage;
        const endIndex = startIndex + rowsPerPage;

        // 3. Show only the slice for the current page
        filteredRows.forEach((r, index) => {
            if (index >= startIndex && index < endIndex) {
                r.style.display = ''; // Let CSS rules take over
            } else {
                r.style.display = 'none';
            }
        });

        // 4. Update UI (Empty State & Buttons)
        document.getElementById('empty-state').style.display = filteredRows.length === 0 ? 'block' : 'none';
        renderPaginationControls(totalPages);
    }

    function renderPaginationControls(totalPages) {
        const container = document.getElementById('paginationControls');
        container.innerHTML = '';
        
        if (totalPages <= 1) return; // Hide pagination if only 1 page

        // Previous Button
        const prev = document.createElement('button');
        prev.className = 'pg-btn';
        prev.innerHTML = '<i class="fa-solid fa-chevron-left"></i> Prev';
        prev.disabled = currentPage === 1;
        prev.onclick = () => { currentPage--; applyFiltersAndPagination(); };
        container.appendChild(prev);

        // Page Numbers
        for(let i = 1; i <= totalPages; i++) {
            const btn = document.createElement('button');
            btn.className = `pg-btn ${i === currentPage ? 'active' : ''}`;
            btn.innerText = i;
            btn.onclick = () => { currentPage = i; applyFiltersAndPagination(); };
            container.appendChild(btn);
        }

        // Next Button
        const next = document.createElement('button');
        next.className = 'pg-btn';
        next.innerHTML = 'Next <i class="fa-solid fa-chevron-right"></i>';
        next.disabled = currentPage === totalPages;
        next.onclick = () => { currentPage++; applyFiltersAndPagination(); };
        container.appendChild(next);
    }

    // --- AJAX DROPDOWN LOADERS ---
    function loadBuildingsFilter(locationId) {
        const targetSelect = document.getElementById('filterBuilding');
        targetSelect.innerHTML = '<option value="">Loading...</option>';
        targetSelect.disabled = true;
        
        if (!locationId) { 
            targetSelect.innerHTML = '<option value="">-- All Buildings --</option>'; 
            currentPage = 1;
            applyFiltersAndPagination();
            return; 
        }

        fetch(`../process/getBuildingsByLocation.php?location_id=${locationId}`)
            .then(r => r.json())
            .then(data => {
                targetSelect.innerHTML = '<option value="">-- All Buildings --</option>';
                if (data.buildings && data.buildings.length > 0) {
                    data.buildings.forEach(b => targetSelect.add(new Option(b.BUILDING_NAME, b.BUILDING_ID)));
                    targetSelect.disabled = false;
                }
                currentPage = 1;
                applyFiltersAndPagination(); 
            })
            .catch(() => { targetSelect.innerHTML = '<option value="">Error Loading</option>'; });
    }

    function loadBuildingsModal(locationId, targetSelectId, preselectBldgId = null) {
        const targetSelect = document.getElementById(targetSelectId);
        targetSelect.innerHTML = '<option value="">Loading...</option>';
        targetSelect.disabled = true;
        
        if (!locationId) { 
            targetSelect.innerHTML = '<option value="">-- Select Location First --</option>'; 
            return; 
        }

        fetch(`../process/getBuildingsByLocation.php?location_id=${locationId}`)
            .then(r => r.json())
            .then(data => {
                targetSelect.innerHTML = '<option value="">-- Select Building --</option>';
                if (data.buildings && data.buildings.length > 0) {
                    data.buildings.forEach(b => {
                        const opt = new Option(b.BUILDING_NAME, b.BUILDING_ID);
                        if(preselectBldgId && b.BUILDING_ID == preselectBldgId) opt.selected = true;
                        targetSelect.add(opt);
                    });
                    targetSelect.disabled = false;
                }
            })
            .catch(() => { targetSelect.innerHTML = '<option value="">Error Loading</option>'; });
    }

    // --- MODAL TRIGGERS ---
    function openAddModal() {
        document.getElementById('addPenForm').reset();
        
        if (USER_LOCATION == 1000) {
            const bldgSelect = document.getElementById('add_building_id');
            bldgSelect.innerHTML = '<option value="">-- Select Location First --</option>';
            bldgSelect.disabled = true;
        }
        
        document.getElementById('addModal').classList.add('show');
    }

    function editPen(button) {
        const row = button.closest('tr');
        const penId = row.dataset.id;
        const buildingId = row.dataset.bldg;
        const locationId = row.dataset.loc;
        
        document.getElementById('edit_pen_id').value = penId;
        document.getElementById('edit_pen_name').value = row.querySelector('.col-name').textContent.trim();
        document.getElementById('edit_location_id').value = locationId;

        // Preload buildings and set selected
        loadBuildingsModal(locationId, 'edit_building_id', buildingId);
        
        document.getElementById('editModal').classList.add('show');
    }

    function deletePen(button) {
        const name = button.closest('tr').querySelector('.col-name').textContent.trim();
        if (confirm(`Permanently delete pen: ${name}?`)) {
            document.getElementById('delete_pen_id').value = button.closest('tr').dataset.id;
            document.getElementById('deletePenForm').submit();
        }
    }

    function closeAddModal() { document.getElementById('addModal').classList.remove('show'); }
    function closeEditModal() { document.getElementById('editModal').classList.remove('show'); }
    
    function submitAddForm() { 
        const form = document.getElementById('addPenForm');
        if(form.checkValidity()) form.submit();
        else form.reportValidity();
    }
    
    function submitEditForm() { 
        const form = document.getElementById('editPenForm');
        if(form.checkValidity()) form.submit();
        else form.reportValidity();
    }

    // Close on outside click
    window.addEventListener('click', (e) => {
        if (e.target.classList.contains('modal-overlay')) { 
            closeAddModal(); 
            closeEditModal(); 
        }
    });
</script>
</body>
</html>