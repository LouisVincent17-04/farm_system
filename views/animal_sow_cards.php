<?php
// views/animal_sow_cards.php
$page = "farm";
include '../config/Connection.php';

include '../security/checkAccess.php';
checkAccess('sow_cards');
include '../common/navbar.php';
include '../common/chat_support.php';

// --- 1. INITIALIZE VARIABLES ---
$locations = [];
$buildings = [];
$pens = [];
$sow_list = [];
$selected_sow_data = null;
$history = [];
$birthing_date = null; // NEW: Variable to hold the active birthing date

$location_id = $_GET['location_id'] ?? '';
$building_id = $_GET['building_id'] ?? '';
$pen_id = $_GET['pen_id'] ?? '';
$selected_animal_id = $_GET['animal_id'] ?? '';

try {
    // --- 2. FETCH DROPDOWNS ---
    $stmt = $conn->prepare("SELECT * FROM locations ORDER BY LOCATION_NAME");
    $stmt->execute();
    $locations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($location_id) {
        $stmt = $conn->prepare("SELECT * FROM buildings WHERE LOCATION_ID = ? ORDER BY BUILDING_NAME");
        $stmt->execute([$location_id]);
        $buildings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    if ($building_id) {
        $stmt = $conn->prepare("SELECT * FROM pens WHERE BUILDING_ID = ? ORDER BY PEN_NAME");
        $stmt->execute([$building_id]);
        $pens = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // --- 3. FETCH FILTERED SOWS ---
    if ($location_id) {
        $query = "
            SELECT 
                ar.ANIMAL_ID, 
                ar.TAG_NO, 
                ac.STAGE_NAME,
                l.LOCATION_NAME,
                b.BUILDING_NAME,
                p.PEN_NAME,
                (SELECT COUNT(*) FROM sow_birthing_records WHERE ANIMAL_ID = ar.ANIMAL_ID) as PARITY_COUNT
            FROM animal_records ar
            JOIN animal_classifications ac ON ar.CLASS_ID = ac.CLASS_ID
            LEFT JOIN locations l ON ar.LOCATION_ID = l.LOCATION_ID
            LEFT JOIN buildings b ON ar.BUILDING_ID = b.BUILDING_ID
            LEFT JOIN pens p ON ar.PEN_ID = p.PEN_ID
            WHERE ar.IS_ACTIVE = 1 
            AND (ac.STAGE_NAME LIKE '%Sow%' OR ac.STAGE_NAME LIKE '%Gilt%')
        ";
        
        $params = [];

        if ($location_id) {
            $query .= " AND ar.LOCATION_ID = ?";
            $params[] = $location_id;
        }
        if ($building_id) {
            $query .= " AND ar.BUILDING_ID = ?";
            $params[] = $building_id;
        }
        if ($pen_id) {
            $query .= " AND ar.PEN_ID = ?";
            $params[] = $pen_id;
        }

        $query .= " ORDER BY ar.TAG_NO ASC";

        $stmt = $conn->prepare($query);
        $stmt->execute($params);
        $sow_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // --- 4. FETCH SELECTED SOW DATA ---
    if ($selected_animal_id) {
        $stmt = $conn->prepare("SELECT * FROM animal_records WHERE ANIMAL_ID = ?");
        $stmt->execute([$selected_animal_id]);
        $selected_sow_data = $stmt->fetch(PDO::FETCH_ASSOC);

        // Fetch the active BIRTHING status date to prepopulate the form
        if ($selected_sow_data) {
            $stmtBirth = $conn->prepare("SELECT STATUS_START_DATE FROM sow_status_history WHERE ANIMAL_ID = ? AND STATUS_NAME = 'BIRTHING' AND IS_ACTIVE = 1");
            $stmtBirth->execute([$selected_animal_id]);
            $birth_row = $stmtBirth->fetch(PDO::FETCH_ASSOC);
            if ($birth_row) {
                // Extract just the Date portion (Y-m-d) for the Flatpickr input
                $birthing_date = date('Y-m-d', strtotime($birth_row['STATUS_START_DATE']));
            }
        }
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Sow Card Management | FarmPro</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=DM+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" type="text/css" href="https://npmcdn.com/flatpickr/dist/themes/dark.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    <style>
        /* ─── CSS VARIABLES ─── */
        :root {
            --bg-base:        #080f1a;
            --bg-surface:     #0d1829;
            --bg-elevated:    #111f35;
            --bg-hover:       #162540;
            --border:         rgba(255,255,255,0.07);
            --border-active:  rgba(236,72,153,0.5); /* Pink Accent */
            
            --pink:           #ec4899;
            --pink-dim:       rgba(236,72,153,0.12);
            --pink-glow:      rgba(236,72,153,0.25);
            --emerald:        #10b981;
            --amber:          #f59e0b;
            --red:            #f87171;
            --blue:           #3b82f6;
            --purple:         #a855f7;
            
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
            background-image: radial-gradient(ellipse 80% 50% at 50% -20%, rgba(236,72,153,0.06) 0%, transparent 60%);
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
            color: var(--pink); background: var(--pink-dim); border: 1px solid rgba(236,72,153,0.2);
            padding: 6px 12px; border-radius: 99px;
        }

        /* ─── HEADER ─── */
        .page-header { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 2.5rem; gap: 1.5rem; flex-wrap: wrap; }
        .header-info h1 { font-size: clamp(1.8rem, 4vw, 2.5rem); font-weight: 700; margin: 0 0 0.5rem 0; color: #fff; letter-spacing: -0.02em;}
        .header-info h1 span { background: linear-gradient(135deg, var(--pink), #be185d); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .header-info p { color: var(--text-secondary); font-size: 0.95rem; margin: 0; }

        .btn-status-return {
            background: var(--bg-elevated); color: var(--text-secondary); border: 1px solid var(--border);
            padding: 10px 20px; border-radius: var(--radius-md); font-weight: 700; font-size: 0.9rem;
            text-decoration: none; transition: var(--transition); display: inline-flex; align-items: center; gap: 8px;
        }
        .btn-status-return:hover { background: var(--bg-hover); color: var(--pink); border-color: var(--pink); box-shadow: 0 4px 12px var(--pink-glow);}

        /* ─── FILTERS ─── */
        .filter-card {
            background: var(--bg-surface); border: 1px solid var(--border);
            border-radius: var(--radius-xl); padding: 1.5rem; margin-bottom: 2rem;
            display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.25rem; align-items: flex-end;
            box-shadow: var(--shadow-md);
        }
        .form-group { display: flex; flex-direction: column; gap: 6px; }
        .form-group label { color: var(--text-secondary); font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; }
        
        .form-select, .form-input {
            width: 100%; padding: 12px 14px; background: var(--bg-elevated); border: 1px solid var(--border);
            border-radius: var(--radius-md); color: var(--text-primary); font-size: 0.95rem; font-family: var(--font);
            outline: none; transition: all var(--transition); box-sizing: border-box;
        }
        .form-select {
            appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2394a3b8' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
            background-repeat: no-repeat; background-position: right 12px center; cursor: pointer;
        }
        .form-input:focus, .form-select:focus { border-color: var(--pink); box-shadow: 0 0 0 3px var(--pink-glow); background: var(--bg-hover); }
        .form-select:disabled, .form-input[readonly] { opacity: 0.5; cursor: not-allowed; background: rgba(255,255,255,0.02); }
        .search-field { grid-column: span 2; }

        /* ─── DATA TABLE ─── */
        .table-container {
            background: var(--bg-surface); border: 1px solid var(--border); border-radius: var(--radius-xl);
            overflow: hidden; margin-bottom: 3rem; box-shadow: var(--shadow-md); max-height: 450px; overflow-y: auto;
        }
        /* Custom Scrollbar */
        .table-container::-webkit-scrollbar { width: 8px; height: 8px; }
        .table-container::-webkit-scrollbar-track { background: var(--bg-surface); }
        .table-container::-webkit-scrollbar-thumb { background: #334155; border-radius: 4px; }
        
        .table-scroll-wrapper { width: 100%; overflow-x: auto; }
        .data-table { width: 100%; border-collapse: collapse; min-width: 900px;}
        .data-table th {
            text-align: left; padding: 16px; background: var(--bg-elevated);
            color: var(--text-muted); font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 1px solid var(--border);
            position: sticky; top: 0; z-index: 10;
        }
        .data-table td { padding: 14px 16px; border-bottom: 1px solid rgba(255,255,255,0.03); color: var(--text-primary); vertical-align: middle;}
        .data-table tr:hover { background: rgba(255,255,255,0.02); }
        .data-table tr.active-row { background: var(--pink-dim); }
        .data-table tr.active-row td:first-child { border-left: 3px solid var(--pink); }

        .tag-no { font-family: var(--font-mono); font-weight: 700; font-size: 1.05rem; color: #fff; }
        .loc-data { font-size: 0.85rem; color: var(--text-secondary); display: flex; align-items: center; gap: 4px;}

        .btn-select {
            background: var(--bg-elevated); color: var(--text-secondary); border: 1px solid var(--border);
            padding: 8px 16px; border-radius: 8px; cursor: pointer; text-decoration: none; display: inline-flex;
            align-items: center; gap: 6px; font-size: 0.85rem; font-weight: 600; font-family: var(--font); transition: var(--transition);
        }
        .btn-select:hover { background: var(--bg-hover); color: var(--pink); border-color: var(--pink); }
        .btn-select.active { background: var(--pink); color: #000; border-color: var(--pink); box-shadow: 0 4px 12px var(--pink-glow); }
        .btn-select.active:hover { background: #f472b6; transform: translateY(-1px); }

        /* ─── SOW CARD DETAIL SECTION ─── */
        .detail-section { display: none; margin-top: 1rem; animation: fadeIn 0.3s ease-out forwards; }
        .detail-section.active { display: block; }
        @keyframes fadeIn { from { opacity:0; transform:translateY(20px); } to { opacity:1; transform:translateY(0); } }

        .sow-card-header {
            background: var(--bg-surface); border: 1px solid var(--border); border-radius: var(--radius-xl) var(--radius-xl) 0 0;
            padding: 2rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;
            position: relative; overflow: hidden;
        }
        .sow-card-header::before {
            content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px;
            background: linear-gradient(90deg, var(--pink), #be185d);
        }
        .sow-card-header h2 { margin: 0; color: #fff; font-size: 1.8rem; display: flex; align-items: center; gap: 10px;}
        .sow-card-header h2 span { font-family: var(--font-mono); color: var(--pink); }

        .btn-add {
            background: var(--pink); color: #000; border: none; padding: 12px 24px;
            border-radius: var(--radius-md); font-weight: 700; font-family: var(--font); font-size: 0.95rem;
            cursor: pointer; transition: var(--transition); display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 4px 15px var(--pink-glow);
        }
        .btn-add:hover { background: #f472b6; transform: translateY(-2px); }

        /* History Table */
        .history-wrapper {
            background: var(--bg-elevated); border: 1px solid var(--border); border-top: none;
            border-radius: 0 0 var(--radius-xl) var(--radius-xl); overflow: hidden;
        }
        .history-table { width: 100%; border-collapse: collapse; min-width: 800px; }
        .history-table th { background: var(--pink-dim); color: var(--pink); padding: 16px; text-align: left; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; font-weight: 700;}
        .history-table td { padding: 16px; border-bottom: 1px solid rgba(255,255,255,0.03); color: var(--text-primary); font-family: var(--font-mono);}
        
        .btn-edit { background: transparent; border: none; color: var(--blue); cursor: pointer; font-size: 1.1rem; transition: var(--transition); padding: 4px 8px; border-radius: 4px;}
        .btn-edit:hover { background: var(--blue-dim); color: #60a5fa; transform: scale(1.1);}

        /* ─── MODAL ─── */
        .modal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.85); backdrop-filter: blur(5px); z-index: 1000; align-items: center; justify-content: center; padding: 1rem; box-sizing: border-box; }
        .modal.show { display: flex; }
        .modal-content { background: var(--bg-surface); border-radius: var(--radius-xl); width: 100%; max-width: 600px; border: 1px solid var(--border); overflow: hidden; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5); animation: modalZoom 0.2s ease-out;}
        @keyframes modalZoom { from { transform: scale(0.95); opacity: 0; } to { transform: scale(1); opacity: 1; } }
        
        .modal-header { padding: 1.5rem 2rem; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; background: var(--bg-elevated);}
        .modal-header h2 { margin: 0; color: #fff; font-size: 1.25rem; display: flex; align-items: center; gap: 10px;}
        .modal-header h2 i { color: var(--pink); }
        
        .modal-body { padding: 2rem; max-height: 60vh; overflow-y: auto; }
        .btn-close { background: transparent; border: none; color: var(--text-muted); font-size: 1.5rem; cursor: pointer; transition: color var(--transition); }
        .btn-close:hover { color: var(--red); }

        .info-box {
            background: var(--emerald-dim); border: 1px solid rgba(16,185,129,0.3); padding: 1rem;
            border-radius: var(--radius-md); margin-bottom: 1.5rem; font-size: 0.9rem; color: #86efac; line-height: 1.5;
        }
        .info-box code { background: rgba(0,0,0,0.3); padding: 2px 6px; border-radius: 4px; font-family: var(--font-mono); color: #fff;}

        .form-grid-modal { display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; margin-bottom: 1.25rem;}
        
        .modal-footer { padding: 1.25rem 2rem; border-top: 1px solid var(--border); display: flex; justify-content: flex-end; gap: 10px; background: var(--bg-elevated); border-radius: 0 0 var(--radius-xl) var(--radius-xl);}
        .btn-cancel { background: transparent; border: 1px solid var(--border); color: var(--text-secondary); padding: 10px 20px; border-radius: var(--radius-md); font-weight: 700; cursor: pointer; font-family: var(--font); transition: var(--transition);}
        .btn-cancel:hover { background: var(--bg-hover); color: #fff; }
        .btn-save-modal { background: var(--pink); border: none; color: #000; padding: 10px 20px; border-radius: var(--radius-md); font-weight: 700; cursor: pointer; font-family: var(--font); transition: var(--transition); display: inline-flex; align-items: center; gap: 8px;}
        .btn-save-modal:hover { background: #f472b6; box-shadow: 0 4px 15px var(--pink-glow); transform: translateY(-1px);}

        .empty-state { text-align: center; padding: 4rem; color: var(--text-muted); font-style: italic; }

        @media (max-width: 768px) {
            .container { padding: 1rem; }
            .filter-card { grid-template-columns: 1fr; gap: 1rem; }
            .search-field { grid-column: span 1; }
            .form-grid-modal { grid-template-columns: 1fr; }
            .page-header { flex-direction: column; align-items: flex-start; }
            
            .data-table thead { display: none; }
            .data-table, .data-table tbody, .data-table tr, .data-table td { display: block; width: 100%; box-sizing: border-box; }
            .data-table tr { background: rgba(30, 41, 59, 0.4); border: 1px solid var(--border); border-radius: var(--radius-xl); margin-bottom: 1rem; padding: 1.25rem; }
            .data-table td { padding: 0.6rem 0; text-align: right; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px dashed rgba(255,255,255,0.05); }
            .data-table td:last-child { border-bottom: none; padding-top: 1rem; justify-content: flex-end;}
            .data-table td::before { content: attr(data-label); font-weight: 700; color: var(--text-muted); font-size: 0.75rem; text-transform: uppercase; margin-right: 1rem; text-align: left;}
        }
    </style>
</head>
<body>

<div class="container">
    
    <div class="top-bar">
        <a href="farm_dashboard.php" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back to Farm Dashboard
        </a>
        <span class="page-badge"><i class="fa-solid fa-clipboard-list"></i> Digital Records</span>
    </div>

    <div class="page-header">
        <div class="header-info">
            <h1>Sow Card <span>Management</span></h1>
            <p>Access individual digital records, monitor parity, and log birthing histories.</p>
        </div>
        <?php if($selected_animal_id): ?>
            <a href="animal_sow_status.php?location_id=<?= $location_id ?>&building_id=<?= $building_id ?>&animal_id=<?= $selected_animal_id ?>" class="btn-status-return">
                <i class="fa-solid fa-rotate-left"></i> Return to Status Manager
            </a>
        <?php endif; ?>
    </div>

    <form class="filter-card" method="GET" id="filterForm">
        <div class="form-group">
            <label>1. Location</label>
            <select name="location_id" class="form-select" onchange="this.form.submit()">
                <option value="">-- Choose Location --</option>
                <?php foreach($locations as $loc): ?>
                    <option value="<?php echo $loc['LOCATION_ID']; ?>" <?php echo $location_id == $loc['LOCATION_ID'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($loc['LOCATION_NAME']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label>2. Building</label>
            <select name="building_id" class="form-select" <?php echo empty($buildings) ? 'disabled' : ''; ?> onchange="this.form.submit()">
                <option value="">-- All Buildings --</option>
                <?php foreach($buildings as $b): ?>
                    <option value="<?php echo $b['BUILDING_ID']; ?>" <?php echo $building_id == $b['BUILDING_ID'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($b['BUILDING_NAME']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label>3. Pen</label>
            <select name="pen_id" class="form-select" <?php echo empty($pens) ? 'disabled' : ''; ?> onchange="this.form.submit()">
                <option value="">-- All Pens --</option>
                <?php foreach($pens as $p): ?>
                    <option value="<?php echo $p['PEN_ID']; ?>" <?php echo $pen_id == $p['PEN_ID'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($p['PEN_NAME']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group search-field">
            <label>4. Search Sow (Tag No)</label>
            <input type="text" id="sowSearch" class="form-input" placeholder="<?php if(!isset($_GET['location_id'])) echo "Choose Location First and "; ?>Type tag to filter list..." onkeyup="filterSowTable()">
        </div>
    </form>

    <?php if($location_id): ?>
        <div class="table-container">
            <div class="table-scroll-wrapper">
                <table class="data-table" id="sowTable">
                    <thead>
                        <tr>
                            <th>Tag No</th>
                            <th>Classification</th>
                            <th>Location</th>
                            <th>Parity</th>
                            <th style="text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($sow_list)): ?>
                            <tr><td colspan="5" class="empty-state"><i class="fa-solid fa-ghost" style="font-size:2rem; display:block; margin-bottom:1rem; opacity:0.5;"></i>No Sows found in this selection.</td></tr>
                        <?php else: ?>
                            <?php foreach($sow_list as $row): 
                                $isActive = ($selected_animal_id == $row['ANIMAL_ID']);
                            ?>
                            <tr class="<?php echo $isActive ? 'active-row' : ''; ?>">
                                <td data-label="Tag No"><div class="tag-no"><?php echo $row['TAG_NO']; ?></div></td>
                                <td data-label="Classification"><?php echo $row['STAGE_NAME']; ?></td>
                                <td data-label="Location"><div class="loc-data"><i class="fa-solid fa-location-dot"></i> <?php echo $row['BUILDING_NAME']; ?> &gt; <?php echo $row['PEN_NAME']; ?></div></td>
                                <td data-label="Parity" style="font-family:var(--font-mono); font-weight:700;"><?php echo $row['PARITY_COUNT']; ?></td>
                                <td data-label="Action" style="text-align: right;">
                                    <a href="?location_id=<?php echo $location_id; ?>&building_id=<?php echo $building_id; ?>&pen_id=<?php echo $pen_id; ?>&animal_id=<?php echo $row['ANIMAL_ID']; ?>" 
                                       class="btn-select <?php echo $isActive ? 'active' : ''; ?>">
                                        <?php echo $isActive ? '<i class="fa-solid fa-check"></i> Selected' : 'Select Card'; ?>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php else: ?>
        <div class="empty-state" style="background: var(--bg-surface); border: 1px dashed var(--border); border-radius: var(--radius-xl);">
            <i class="fa-solid fa-arrow-up" style="font-size: 2.5rem; margin-bottom: 1rem; display: block; opacity: 0.5;"></i>
            <h3 style="margin:0 0 0.5rem 0; color: #fff;">Select a Location to view Sows</h3>
            <p style="margin:0;">Use the cascade filters above to find specific animals.</p>
        </div>
    <?php endif; ?>

    <?php if($selected_sow_data): ?>
        <div id="detailSection" class="detail-section active">
            <div class="sow-card-header">
                <h2><i class="fa-solid fa-clipboard-list"></i> Digital Sow Card: <span><?php echo $selected_sow_data['TAG_NO']; ?></span></h2>
                <button class="btn-add" onclick="openRecordModal()"><i class="fa-solid fa-plus"></i> Add Birth Record</button>
            </div>

            <div class="history-wrapper">
                <div class="table-scroll-wrapper">
                    <table class="history-table">
                        <thead>
                            <tr>
                                <th>Parity</th>
                                <th>Date Farrowed</th>
                                <th>Born</th>
                                <th>Alive</th>
                                <th>Dead</th>
                                <th>Mummified</th>
                                <th style="text-align:right;">Edit</th>
                            </tr>
                        </thead>
                        <tbody id="historyBody">
                            <tr><td colspan="7" class="empty-state"><i class="fa-solid fa-spinner fa-spin me-2"></i> Loading parity history...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>

</div>

<div id="recordModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modalTitle"><i class="fa-solid fa-baby-carriage"></i> Add Birth Record</h2>
            <button class="btn-close" onclick="closeModal()"><i class="fa-solid fa-xmark"></i></button>
        </div>
        
        <div class="modal-body">
            <div class="info-box">
                <div style="font-weight:700; margin-bottom:6px;"><i class="fa-solid fa-wand-magic-sparkles"></i> Auto-Tagging Enabled</div>
                Saving this record will automatically create individual animal records for all <strong>Active (Alive)</strong> piglets.<br>
                <div style="margin-top:8px;"><strong>Tag Format:</strong> <code>[SOW TAG]-P[PARITY]-[#]</code></div>
            </div>

            <form id="recordForm">
                <input type="hidden" id="record_id" name="record_id">
                <input type="hidden" id="animal_id" name="animal_id" value="<?php echo $selected_animal_id; ?>">
                <input type="hidden" id="action_type" name="action_type" value="add">

                <div class="form-group" style="margin-bottom: 1.25rem;">
                    <label>Date Farrowed</label>
                    <input type="date" id="date_farrowed" name="date_farrowed" class="form-input" required>
                </div>

                <div class="form-grid-modal">
                    <div class="form-group" style="margin:0;">
                        <label>Total Born</label>
                        <input type="number" id="total_born" name="total_born" class="form-input" style="font-family:var(--font-mono); font-weight:700;" required min="0" oninput="calcActive()">
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label style="color:var(--emerald);">Active (Alive) <span style="opacity:0.6; text-transform:none; font-weight:400;">(Auto-Calc)</span></label>
                        <input type="number" id="active_count" name="active_count" class="form-input" style="font-family:var(--font-mono); font-weight:700; color:var(--emerald); border-color:var(--emerald);" required min="0" readonly>
                    </div>
                </div>

                <div class="form-grid-modal">
                    <div class="form-group" style="margin:0;">
                        <label style="color:var(--red);">Dead (Stillborn)</label>
                        <input type="number" id="dead_count" name="dead_count" class="form-input" style="font-family:var(--font-mono);" value="0" min="0" oninput="calcActive()">
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label style="color:var(--purple);">Mummified</label>
                        <input type="number" id="mummified_count" name="mummified_count" class="form-input" style="font-family:var(--font-mono);" value="0" min="0" oninput="calcActive()">
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-cancel" onclick="closeModal()">Cancel</button>
            <button type="submit" form="recordForm" class="btn-save-modal" id="btnSave"><i class="fa-solid fa-floppy-disk"></i> Save &amp; Generate</button>
        </div>
    </div>
</div>

<script>
    // Initialize Flatpickr for strictly mm/dd/yyyy visual inputs
    const fpFarrow = flatpickr("#date_farrowed", {
        dateFormat: "Y-m-d", // Value submitted to PHP
        altInput: true,      // Dummy visually formatted input
        altFormat: "M j, Y",  // visual style
        allowInput: true
    });

    // --- SCROLL TO DETAILS ---
    <?php if($selected_sow_data): ?>
        setTimeout(() => {
            document.getElementById('detailSection').scrollIntoView({ behavior: 'smooth' });
            loadHistory('<?php echo $selected_animal_id; ?>');
        }, 300);
    <?php endif; ?>

    // --- AUTO CALCULATE ACTIVE ---
    function calcActive() {
        const total = parseInt(document.getElementById('total_born').value) || 0;
        const dead = parseInt(document.getElementById('dead_count').value) || 0;
        const mummy = parseInt(document.getElementById('mummified_count').value) || 0;
        
        let active = total - (dead + mummy);
        if (active < 0) active = 0;
        
        document.getElementById('active_count').value = active;
    }

    // --- CLIENT SIDE FILTER ---
    function filterSowTable() {
        const input = document.getElementById('sowSearch');
        const filter = input.value.toUpperCase();
        const table = document.getElementById('sowTable');
        const tr = table.getElementsByTagName('tr');

        // Skip header row
        for (let i = 1; i < tr.length; i++) {
            // Search inside the Tag No column
            const td = tr[i].querySelector('.tag-no') || tr[i].getElementsByTagName('td')[0]; 
            if (td) {
                const txtValue = td.textContent || td.innerText;
                tr[i].style.display = txtValue.toUpperCase().indexOf(filter) > -1 ? "" : "none";
            }
        }
    }

    // --- HISTORY LOADER ---
    async function loadHistory(id) {
        const tbody = document.getElementById('historyBody');
        try {
            const res = await fetch(`../process/getSowRecords.php?id=${id}`);
            const data = await res.json();

            tbody.innerHTML = '';
            if(data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" class="empty-state"><i class="fa-solid fa-ghost display-block margin-bottom opacity-50 font-size-2rem"></i> No parity records found.</td></tr>';
                return;
            }

            data.forEach(row => {
                // Render the history date gracefully in mm/dd/yyyy using JS
                let dt = new Date(row.DATE_FARROWED);
                // JS timezone offset handler to ensure accurate local day display
                let tzOffset = dt.getTimezoneOffset() * 60000; 
                let localTime = new Date(dt.getTime() + tzOffset); 
                
                let formattedDate = `${String(localTime.getMonth() + 1).padStart(2, '0')}/${String(localTime.getDate()).padStart(2, '0')}/${localTime.getFullYear()}`;

                const tr = `
                    <tr>
                        <td data-label="Parity" style="font-weight:700; color:var(--pink); font-size:1.1rem;">${row.PARITY}</td>
                        <td data-label="Date Farrowed" style="color:var(--text-primary);">${formattedDate}</td>
                        <td data-label="Born">${row.TOTAL_BORN}</td>
                        <td data-label="Alive" style="color:var(--emerald); font-weight:700;">${row.ACTIVE_COUNT}</td>
                        <td data-label="Dead" style="color:var(--red); font-weight:700;">${row.DEAD_COUNT}</td>
                        <td data-label="Mummified" style="color:var(--purple); font-weight:700;">${row.MUMMIFIED_COUNT}</td>
                        <td data-label="Edit" style="text-align:right;">
                            <button class="btn-edit" onclick='openEditModal(${JSON.stringify(row)})'><i class="fa-solid fa-pen-to-square"></i></button>
                        </td>
                    </tr>
                `;
                tbody.innerHTML += tr;
            });
        } catch (err) {
            tbody.innerHTML = '<tr><td colspan="7" class="empty-state" style="color:var(--red);">Failed to load history.</td></tr>';
        }
    }

    // --- MODAL LOGIC ---
    const modal = document.getElementById('recordModal');
    const form = document.getElementById('recordForm');
    const title = document.getElementById('modalTitle');
    const btnSave = document.getElementById('btnSave');

    function openRecordModal() {
        form.reset();
        document.getElementById('action_type').value = 'add';
        document.getElementById('record_id').value = '';
        document.getElementById('animal_id').value = '<?php echo $selected_animal_id; ?>';
        
        // Fetch the active birthing date from PHP (or default to today if not birthing yet)
        const defaultDate = '<?php echo $birthing_date ?? "today"; ?>';
        fpFarrow.setDate(defaultDate); 
        
        // Reset counts
        document.getElementById('total_born').value = '';
        document.getElementById('dead_count').value = 0;
        document.getElementById('mummified_count').value = 0;
        document.getElementById('active_count').value = '';

        title.innerHTML = '<i class="fa-solid fa-baby-carriage"></i> Add Birth Record';
        btnSave.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Save &amp; Generate';
        modal.classList.add('show');
    }

    function openEditModal(data) {
        document.getElementById('action_type').value = 'edit';
        document.getElementById('record_id').value = data.RECORD_ID;
        document.getElementById('animal_id').value = data.ANIMAL_ID;
        
        fpFarrow.setDate(data.DATE_FARROWED); // Set existing date securely
        
        document.getElementById('total_born').value = data.TOTAL_BORN;
        document.getElementById('dead_count').value = data.DEAD_COUNT;
        document.getElementById('mummified_count').value = data.MUMMIFIED_COUNT;
        
        // Trigger calc to set active count
        calcActive();

        title.innerHTML = '<i class="fa-solid fa-pen-to-square"></i> Edit Birth Record (Parity ' + data.PARITY + ')';
        btnSave.innerHTML = '<i class="fa-solid fa-arrows-rotate"></i> Update Changes';
        modal.classList.add('show');
    }

    function closeModal() { modal.classList.remove('show'); }

    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const action = document.getElementById('action_type').value;

        let msg = "Confirm details?\n\nIf you increased the 'Active (Alive)' count, new animal records will be automatically generated for the extra piglets.";
        if (action === 'add') {
            msg = "Confirm details?\n\nThis will generate new animal records. Ensure 'Active' count is correct.";
        }

        if(!confirm(msg)) return;

        const formData = new FormData(this);
        const endpoint = action === 'add' ? '../process/addBirthingRecord.php' : '../process/editBirthingRecord.php';

        const ogText = btnSave.innerHTML;
        btnSave.disabled = true;
        btnSave.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Processing...';

        fetch(endpoint, { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if(data.success) {
                alert("Success: " + (data.message || "Record Saved"));
                closeModal();
                loadHistory('<?php echo $selected_animal_id; ?>');
                
                // Redirect logic
                const urlParams = new URLSearchParams(window.location.search);
                if (action === 'add' && urlParams.has('location_id')) {
                    setTimeout(() => {
                        window.location.href = `animal_sow_status.php?location_id=${urlParams.get('location_id')}&building_id=${urlParams.get('building_id')}&animal_id=<?php echo $selected_animal_id; ?>`;
                    }, 500);
                }
            } else {
                alert("Error: " + data.message);
            }
            btnSave.disabled = false;
            btnSave.innerHTML = ogText;
        })
        .catch(err => {
            console.error(err);
            alert("System error occurred.");
            btnSave.disabled = false;
            btnSave.innerHTML = ogText;
        });
    });
</script>

</body>
</html>