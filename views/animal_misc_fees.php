<?php
// views/animal_misc_fees.php
error_reporting(0);
ini_set('display_errors', 0);
include '../config/Connection.php';

// =========================================================
// AJAX HANDLERS FOR CASCADING DROPDOWNS & DATA FETCHING
// =========================================================
if (isset($_GET['action'])) {
    @ob_end_clean();
    header('Content-Type: application/json');
    $action = $_GET['action'];

    try {
        if ($action === 'get_buildings' && isset($_GET['loc_id'])) {
            $stmt = $conn->prepare("SELECT BUILDING_ID, BUILDING_NAME FROM BUILDINGS WHERE LOCATION_ID = ? ORDER BY BUILDING_NAME ASC");
            $stmt->execute([$_GET['loc_id']]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            exit;
        }
        if ($action === 'get_pens' && isset($_GET['bld_id'])) {
            $stmt = $conn->prepare("SELECT PEN_ID, PEN_NAME FROM PENS WHERE BUILDING_ID = ? ORDER BY PEN_NAME ASC");
            $stmt->execute([$_GET['bld_id']]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            exit;
        }
        if ($action === 'get_filtered_animals') {
            $loc_id = $_GET['loc_id'] ?? '';
            $bld_id = $_GET['bld_id'] ?? '';
            $pen_id = $_GET['pen_id'] ?? '';

            if (empty($loc_id)) { echo json_encode([]); exit; }

            // FIXED SQL: Joined BUILDINGS to safely check LOCATION_ID
            $sql = "SELECT a.ANIMAL_ID, a.TAG_NO, p.PEN_NAME, a.TOTAL_MISC_AMT 
                    FROM animal_records a 
                    LEFT JOIN PENS p ON a.PEN_ID = p.PEN_ID 
                    LEFT JOIN BUILDINGS b ON a.BUILDING_ID = b.BUILDING_ID
                    WHERE a.IS_ACTIVE = 1 AND a.CURRENT_STATUS != 'Sold' 
                    AND b.LOCATION_ID = ?";
            $params = [$loc_id];

            if (!empty($bld_id)) { $sql .= " AND a.BUILDING_ID = ?"; $params[] = $bld_id; }
            if (!empty($pen_id)) { $sql .= " AND a.PEN_ID = ?"; $params[] = $pen_id; }
            
            $sql .= " ORDER BY a.TAG_NO ASC";

            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            exit;
        }
        if ($action === 'get_ledger' && isset($_GET['animal_id'])) {
            $stmt = $conn->prepare("SELECT FEE_ID, AMOUNT, FEE_DESCRIPTION, DATE_FORMAT(CREATED_AT, '%b %d, %Y %h:%i %p') as DATE_FMT FROM animal_misc_fees WHERE ANIMAL_ID = ? ORDER BY CREATED_AT DESC");
            $stmt->execute([$_GET['animal_id']]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            exit;
        }
    } catch (Exception $e) {
        // Send actual error message back to JS safely
        echo json_encode(['error' => $e->getMessage()]);
        exit;
    }
}
// =========================================================

include '../security/checkAccess.php';
$page = "farm";
include '../common/navbar.php';
include '../common/chat_support.php';
include '../functions/getUsersLocation.php';

// Fetch initial locations
$loc_sql = ($USER_LOCATION_ != 1000) 
    ? "SELECT * FROM LOCATIONS WHERE LOCATION_ID = :loc_id ORDER BY LOCATION_NAME ASC" 
    : "SELECT * FROM LOCATIONS ORDER BY LOCATION_NAME ASC";
$stmtLoc = $conn->prepare($loc_sql);
if ($USER_LOCATION_ != 1000) $stmtLoc->execute([':loc_id' => $USER_LOCATION_]);
else $stmtLoc->execute();
$locations = $stmtLoc->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Bulk Miscellaneous Fees | FarmPro</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=DM+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />

    <style>
        /* ─── CSS VARIABLES ─── */
        :root {
            --bg-base:        #080f1a;
            --bg-surface:     #0d1829;
            --bg-elevated:    #111f35;
            --bg-hover:       #162540;
            --border:         rgba(255,255,255,0.07);
            --border-active:  rgba(168,85,247,0.5); /* Purple Accent */
            
            --purple:         #a855f7;
            --purple-dim:     rgba(168,85,247,0.12);
            --purple-glow:    rgba(168,85,247,0.25);
            --emerald:        #10b981;
            --emerald-dim:    rgba(16,185,129,0.12);
            --emerald-glow:   rgba(16,185,129,0.25);
            --red:            #f87171;
            --red-dim:        rgba(248,113,113,0.12);
            
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
            background-image: radial-gradient(ellipse 80% 50% at 50% -20%, rgba(168,85,247,0.06) 0%, transparent 60%); 
        }
        .container { max-width: 1400px; margin: 0 auto; padding: 2rem 1.5rem; }

        /* ─── TOP BAR ─── */
        .top-bar { display: flex; align-items: center; justify-content: space-between; margin-bottom: 2rem; gap: 1rem; flex-wrap: wrap; }
        .back-link { 
            display: inline-flex; align-items: center; gap: 8px; text-decoration: none; 
            color: var(--text-secondary); font-size: 0.875rem; font-weight: 500; 
            padding: 8px 14px; background: var(--bg-elevated); border: 1px solid var(--border); 
            border-radius: var(--radius-md); transition: var(--transition); 
        }
        .back-link:hover { color: var(--text-primary); background: var(--bg-hover); border-color: var(--border-active); }
        .page-badge { 
            display: inline-flex; align-items: center; gap: 6px; font-size: 0.75rem; 
            font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; 
            color: var(--purple); background: var(--purple-dim); border: 1px solid rgba(168,85,247,0.2); 
            padding: 6px 12px; border-radius: 99px; 
        }

        /* ─── HEADER ─── */
        .page-header { margin-bottom: 2.5rem; }
        .page-header h1 { font-size: clamp(1.8rem, 4vw, 2.5rem); font-weight: 700; margin: 0 0 0.5rem 0; color: #fff; }
        .page-header h1 span { background: linear-gradient(135deg, var(--purple), #c084fc); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .page-header p { color: var(--text-secondary); font-size: 0.95rem; margin: 0; }

        /* ─── MODAL BUTTONS (ADDED CSS) ─── */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            padding: 0 16px;
            height: 38px;
            border-radius: var(--radius-md);
            font-size: 0.85rem;
            font-weight: 600;
            font-family: var(--font);
            border: 1px solid transparent;
            cursor: pointer;
            transition: all var(--transition);
            text-decoration: none;
            white-space: nowrap;
            letter-spacing: 0.01em;
        }
        .btn i { font-size: 0.8rem; }

        .btn-primary { background: var(--purple); color: #fff; border-color: var(--purple); }
        .btn-primary:hover { background: #c084fc; box-shadow: 0 4px 15px var(--purple-glow); transform: translateY(-1px); }

        .btn-ghost { background: transparent; color: var(--text-secondary); border-color: var(--border); }
        .btn-ghost:hover { background: var(--bg-elevated); color: var(--text-primary); border-color: rgba(255,255,255,0.15); }

        /* ─── SPLIT LAYOUT GRID ─── */
        .main-grid { display: grid; grid-template-columns: 320px 1fr; gap: 1.5rem; align-items: start; }

        /* ─── LEFT PANEL (FILTER) ─── */
        .control-panel { 
            background: var(--bg-surface); border: 1px solid var(--border); 
            border-radius: var(--radius-xl); padding: 2rem; box-shadow: var(--shadow-md); 
            position: sticky; top: 1.5rem; display: flex; flex-direction: column;
        }
        .panel-title { font-size: 1.15rem; font-weight: 700; color: #fff; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 8px;}
        .panel-title i { color: var(--purple); }
        
        .form-group { display: flex; flex-direction: column; gap: 6px; margin-bottom: 1.25rem; }
        .form-label { color: var(--text-secondary); font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; display: flex; justify-content: space-between; align-items: center; }
        .form-label span.req { color: var(--red); }
        .form-label span.opt { color: var(--text-muted); font-weight: 400; text-transform: none; letter-spacing: 0; }
        
        .form-select, .form-input { 
            width: 100%; padding: 12px 14px; background: var(--bg-elevated); border: 1px solid var(--border); border-radius: var(--radius-md); color: var(--text-primary); font-size: 0.95rem; transition: var(--transition); outline: none; font-family: var(--font); box-sizing: border-box; 
        }
        .form-select { 
            appearance: none; 
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2394a3b8' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E"); 
            background-repeat: no-repeat; background-position: right 12px center; cursor: pointer; 
        }
        .form-select:focus, .form-input:focus { border-color: var(--purple); box-shadow: 0 0 0 3px var(--purple-glow); background: var(--bg-hover); }
        .form-select:disabled { opacity: 0.5; cursor: not-allowed; }
        textarea.form-input { resize: vertical; min-height: 80px; line-height: 1.5; }

        .btn-search { 
            width: 100%; padding: 14px; background: var(--purple); border: none; 
            border-radius: var(--radius-md); color: #fff; font-weight: 700; font-size: 1rem; 
            font-family: var(--font); cursor: pointer; transition: var(--transition); 
            display: flex; align-items: center; justify-content: center; gap: 8px; margin-top: 1rem;
        }
        .btn-search:hover:not(:disabled) { background: #c084fc; box-shadow: 0 4px 15px var(--purple-glow); transform: translateY(-2px); }
        .btn-search:disabled { opacity: 0.5; cursor: not-allowed; }

        /* ─── RIGHT PANEL (WORKSPACE) ─── */
        .workspace-panel { display: flex; flex-direction: column; gap: 1.5rem; }
        
        .table-section { background: var(--bg-surface); border: 1px solid var(--border); border-radius: var(--radius-xl); overflow: hidden; box-shadow: var(--shadow-md);}
        
        .section-header { 
            padding: 1.5rem; border-bottom: 1px solid var(--border); background: var(--bg-elevated); 
            display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;
        }
        .section-title { font-size: 1.15rem; font-weight: 700; display: flex; align-items: center; gap: 10px; color: #fff; }
        .section-title i { color: var(--purple); }

        .btn-batch { 
            padding: 10px 20px; background: var(--emerald); border: none; 
            color: #000; border-radius: var(--radius-md); font-weight: 700; font-family: var(--font);
            cursor: pointer; transition: var(--transition); display: inline-flex; align-items: center; gap: 8px; 
            font-size: 0.9rem;
        }
        .btn-batch:hover:not(:disabled) { background: #34d399; box-shadow: 0 4px 15px var(--emerald-glow); transform: translateY(-2px); }
        .btn-batch:disabled { opacity: 0.5; cursor: not-allowed; background: var(--bg-elevated); color: var(--text-muted); border: 1px solid var(--border); }

        .table-scroll-wrapper { overflow-x: auto; max-height: 700px; }
        .data-table { width: 100%; border-collapse: collapse; min-width: 600px; }
        .data-table th { background: var(--bg-base); color: var(--text-muted); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; padding: 16px; text-align: left; font-weight: 700; border-bottom: 1px solid var(--border); position: sticky; top: 0; z-index: 10;}
        .data-table td { padding: 14px 16px; border-bottom: 1px solid rgba(255,255,255,0.03); color: var(--text-primary); vertical-align: middle;}
        .data-table tr:hover { background: rgba(255,255,255,0.01); }
        
        .tag-no { font-family: var(--font-mono); font-weight: 700; color: #fff; font-size: 1.05rem; }
        .pen-name { color: var(--text-secondary); font-size: 0.9rem; }
        .td-amt { font-family: var(--font-mono); font-weight: 700; color: var(--purple); font-size: 1rem; }

        /* Custom Checkbox */
        .chk-container { display: flex; align-items: center; justify-content: center; cursor: pointer; }
        .chk-container input { width: 18px; height: 18px; cursor: pointer; accent-color: var(--purple); }

        .btn-sm-view { background: rgba(255,255,255,0.05); color: var(--text-secondary); border: 1px solid var(--border); padding: 6px 12px; border-radius: 6px; cursor: pointer; font-size: 0.8rem; font-weight: 600; font-family: var(--font); transition: var(--transition); }
        .btn-sm-view:hover { background: var(--bg-hover); color: #fff; border-color: var(--text-muted); }
        
        /* Action buttons in Ledger */
        .actions { display: flex; gap: 6px; justify-content: center; }
        .action-btn { width: 30px; height: 30px; border-radius: 6px; border: 1px solid var(--border); background: var(--bg-elevated); display: inline-flex; align-items: center; justify-content: center; cursor: pointer; transition: all var(--transition); color: var(--text-secondary); text-decoration: none; font-size: 0.8rem; }
        .action-btn:hover { background: var(--bg-hover); color: var(--text-primary); }
        .action-btn.edit:hover { color: var(--purple); border-color: var(--purple); background: var(--purple-dim);}
        .action-btn.delete:hover { color: var(--red); border-color: var(--red); background: var(--red-dim);}

        .empty-state { text-align: center; padding: 5rem 2rem; color: var(--text-muted); font-style: italic; display: flex; flex-direction: column; align-items: center; justify-content: center;}
        .empty-state i { font-size: 3rem; color: var(--purple-dim); margin-bottom: 1rem; display: block; }

        /* ─── MODALS ─── */
        .modal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.85); backdrop-filter: blur(5px); z-index: 1100; align-items: flex-start; justify-content: center; padding: 5vh 1rem; overflow-y: hidden;}
        .modal.show { display: flex; }
        .modal-content { background: var(--bg-surface); border: 1px solid var(--border); border-radius: var(--radius-xl); width: 100%; max-width: 500px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.6); display: flex; flex-direction: column; animation: modalZoom 0.2s ease-out; margin: auto; max-height: 90vh;}
        .modal-content.wide { max-width: 700px; }
        @keyframes modalZoom { from { transform: scale(0.95); opacity: 0; } to { transform: scale(1); opacity: 1; } }
        
        .modal-header { padding: 1.5rem; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; flex-shrink: 0; }
        .modal-header h2 { margin: 0; font-size: 1.25rem; color: #fff; }
        .btn-close { background: transparent; border: none; color: var(--text-muted); font-size: 1.5rem; cursor: pointer; transition: var(--transition); display: flex; align-items: center;}
        .btn-close:hover { color: var(--red); }
        
        .modal-body { padding: 1.5rem; overflow-y: auto; flex: 1 1 auto; min-height: 0; }
        .modal-body::-webkit-scrollbar { width: 8px; height: 8px; }
        .modal-body::-webkit-scrollbar-track { background: transparent; }
        .modal-body::-webkit-scrollbar-thumb { background: var(--text-muted); border-radius: 4px; }
        
        .modal-footer { padding: 1.25rem 1.5rem; border-top: 1px solid var(--border); display: flex; justify-content: flex-end; gap: 10px; background: var(--bg-elevated); border-radius: 0 0 var(--radius-xl) var(--radius-xl); flex-shrink: 0;}

        /* Toast */
        #toastContainer { position: fixed; top: 20px; right: 20px; z-index: 9999; display: flex; flex-direction: column; gap: 10px; }
        .toast { background: var(--bg-surface); border: 1px solid var(--border); color: #fff; padding: 1rem 1.5rem; border-radius: var(--radius-md); box-shadow: 0 10px 25px rgba(0,0,0,0.5); font-size: 0.9rem; font-weight: 600; animation: slideIn 0.3s ease-out; }

        @media (max-width: 1024px) { .main-grid { grid-template-columns: 1fr; } .control-panel { position: relative; top: 0; } }
        @media (max-width: 768px) {
            .section-header { flex-direction: column; align-items: stretch; }
            .btn-batch { width: 100%; justify-content: center; }
            .modal-footer { flex-direction: column-reverse; }
            .modal-footer button { width: 100%; margin: 0; }
        }
    </style>
</head>
<body>

<div id="toastContainer"></div>

<div class="container">
    <div class="top-bar">
        <a href="farm_dashboard.php" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back to Dashboard
        </a>
        <span class="page-badge"><i class="fa-solid fa-receipt"></i> Financials</span>
    </div>

    <div class="page-header">
        <div class="header-info">
            <h1>Bulk Misc <span>Fees Ledger</span></h1>
            <p>Filter animals by location and apply custom financial fees to multiple animals simultaneously.</p>
        </div>
    </div>

    <div class="main-grid">
        <div class="control-panel">
            <div class="panel-title"><i class="fa-solid fa-filter"></i> Target Scope</div>
            
            <div class="form-group">
                <label class="form-label">1. Location <span class="req">*</span></label>
                <select id="filter_loc" class="form-select" onchange="loadBuildings()" <?php echo ($USER_LOCATION_ != 1000) ? 'disabled style="opacity:0.6;"' : ''; ?>>
                    <option value="">-- Choose Location --</option>
                    <?php foreach($locations as $loc): ?>
                        <option value="<?= $loc['LOCATION_ID'] ?>" <?= ($USER_LOCATION_ != 1000 && $loc['LOCATION_ID'] == $USER_LOCATION_) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($loc['LOCATION_NAME']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">2. Building <span class="opt">(Optional)</span></label>
                <select id="filter_bld" class="form-select" disabled onchange="loadPens()">
                    <option value="">-- All Buildings --</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">3. Pen <span class="opt">(Optional)</span></label>
                <select id="filter_pen" class="form-select" disabled>
                    <option value="">-- All Pens --</option>
                </select>
            </div>

            <button class="btn-search" id="btnSearch" onclick="fetchFilteredAnimals()">
                <i class="fa-solid fa-magnifying-glass"></i> Load Animals
            </button>
        </div>

        <div class="workspace-panel">
            <div class="table-section">
                <div class="section-header">
                    <div class="section-title"><i class="fa-solid fa-list-check"></i> Select Animals</div>
                    <button class="btn-batch" id="btnBatchAdd" disabled onclick="openBatchModal()">
                        <i class="fa-solid fa-plus"></i> Add Fee to Selected (0)
                    </button>
                </div>
                <div class="table-scroll-wrapper">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th style="width: 50px; text-align: center;">
                                    <div class="chk-container">
                                        <input type="checkbox" id="selectAll" onchange="toggleAll(this)" disabled>
                                    </div>
                                </th>
                                <th>Tag No.</th>
                                <th>Pen</th>
                                <th style="text-align:right;">Total Fees (₱)</th>
                                <th style="text-align:center; width: 120px;">History</th>
                            </tr>
                        </thead>
                        <tbody id="animalTableBody">
                            <tr>
                                <td colspan="5">
                                    <div class="empty-state">
                                        <i class="fa-solid fa-filter"></i>
                                        Select a location on the left and click "Load Animals" to begin.
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="feeModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modal-title">Record Batch Fee</h2>
            <button class="btn-close" onclick="closeModal('feeModal')"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body">
            <div id="modal-alert" class="alert error" style="display:none; padding:10px; margin-bottom:1rem; border-radius:8px; background:var(--red-dim); color:var(--red); border:1px solid rgba(239,68,68,0.3); text-align:center; font-weight:600;"></div>
            
            <p style="color:var(--text-secondary); margin-bottom: 1.5rem; font-size:0.9rem;">
                This fee will be applied to <strong id="target-count" style="color:#fff;">0</strong> selected animals.
            </p>

            <form id="feeForm">
                <input type="hidden" id="batch_animal_ids" name="animal_ids" value="">
                
                <div class="form-group">
                    <label class="form-label">Fee Amount (₱) <span class="req">*</span></label>
                    <input type="number" id="fee-amount" name="amount" class="form-input" style="font-family:var(--font-mono); font-weight:700; font-size:1.1rem; color:var(--purple);" placeholder="0.00" step="0.01" min="0.01" required>
                </div>

                <div class="form-group" style="margin-top: 1rem;">
                    <label class="form-label">Description <span class="req">*</span></label>
                    <textarea id="fee-desc" name="description" class="form-input" placeholder="e.g., Veterinary Checkup, Special Feed" required maxlength="500"></textarea>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-ghost" onclick="closeModal('feeModal')">Cancel</button>
            <button type="button" class="btn btn-primary" id="btn-save" onclick="submitBatchFee()">
                <i class="fa-solid fa-floppy-disk"></i> Apply to All
            </button>
        </div>
    </div>
</div>

<div id="ledgerModal" class="modal">
    <div class="modal-content wide">
        <div class="modal-header">
            <h2>Fee Ledger: <span id="ledger-tag-no" style="color:var(--purple);"></span></h2>
            <button class="btn-close" onclick="closeModal('ledgerModal')"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body" style="padding: 0;">
            <table class="data-table" style="min-width: 100%;">
                <thead>
                    <tr>
                        <th>Description</th>
                        <th style="text-align:right;">Amount (₱)</th>
                        <th style="text-align:right;">Date Recorded</th>
                        <th style="text-align:center; width: 100px;">Actions</th>
                    </tr>
                </thead>
                <tbody id="ledgerTableBody">
                    <tr><td colspan="4" style="text-align:center; padding: 2rem; color:var(--text-muted);">Loading...</td></tr>
                </tbody>
            </table>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-ghost" onclick="closeModal('ledgerModal')" style="width: 100%;">Close Window</button>
        </div>
    </div>
</div>

<div id="editFeeModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Edit Misc Fee</h2>
            <button class="btn-close" onclick="closeModal('editFeeModal')"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body">
            <div id="edit-modal-alert" class="alert error" style="display:none; padding:10px; margin-bottom:1rem; border-radius:8px; background:var(--red-dim); color:var(--red); border:1px solid rgba(239,68,68,0.3); text-align:center; font-weight:600;"></div>
            
            <form id="editFeeForm">
                <input type="hidden" id="edit_fee_id" name="fee_id" value="">
                <input type="hidden" id="edit_animal_id" name="animal_id" value="">
                
                <div class="form-group">
                    <label class="form-label">Fee Amount (₱) <span class="req">*</span></label>
                    <input type="number" id="edit_fee_amount" name="amount" class="form-input" style="font-family:var(--font-mono); font-weight:700; font-size:1.1rem; color:var(--purple);" placeholder="0.00" step="0.01" min="0.01" required>
                </div>

                <div class="form-group" style="margin-top: 1rem;">
                    <label class="form-label">Description <span class="req">*</span></label>
                    <textarea id="edit_fee_desc" name="description" class="form-input" placeholder="e.g., Veterinary Checkup, Special Feed" required maxlength="500"></textarea>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-ghost" onclick="closeModal('editFeeModal')">Cancel</button>
            <button type="button" class="btn btn-primary" id="btn-edit-save" onclick="submitEditFee()">
                <i class="fa-solid fa-arrows-rotate"></i> Update Record
            </button>
        </div>
    </div>
</div>

<script>
    const USER_LOC = '<?= $USER_LOCATION_ ?>';
    let currentViewingAnimalId = null;

    document.addEventListener('DOMContentLoaded', () => {
        if(USER_LOC != '1000') {
            loadBuildings();
        }
    });

    async function fetchJSON(url) {
        try { const r = await fetch(url); return await r.json(); } catch(e) { return []; }
    }

    // --- CASCADING DROPDOWNS ---
    function loadBuildings() {
        const loc = document.getElementById('filter_loc').value;
        const bld = document.getElementById('filter_bld');
        const pen = document.getElementById('filter_pen');

        bld.innerHTML = '<option value="">-- All Buildings --</option>'; bld.disabled = true;
        pen.innerHTML = '<option value="">-- All Pens --</option>'; pen.disabled = true;

        if(!loc) return;
        bld.innerHTML = '<option>Loading...</option>';

        fetchJSON(`?action=get_buildings&loc_id=${loc}`).then(data => {
            bld.innerHTML = '<option value="">-- All Buildings --</option>';
            if(Array.isArray(data) && data.length) {
                data.forEach(i => bld.innerHTML += `<option value="${i.BUILDING_ID}">${i.BUILDING_NAME}</option>`);
                bld.disabled = false;
            }
        });
    }

    function loadPens() {
        const bld = document.getElementById('filter_bld').value;
        const pen = document.getElementById('filter_pen');

        pen.innerHTML = '<option value="">-- All Pens --</option>'; pen.disabled = true;

        if(!bld) return;
        pen.innerHTML = '<option>Loading...</option>';

        fetchJSON(`?action=get_pens&bld_id=${bld}`).then(data => {
            pen.innerHTML = '<option value="">-- All Pens --</option>';
            if(Array.isArray(data) && data.length) {
                data.forEach(i => pen.innerHTML += `<option value="${i.PEN_ID}">${i.PEN_NAME}</option>`);
                pen.disabled = false;
            }
        });
    }

    // --- DATA FETCHING & RENDERING ---
    function fetchFilteredAnimals() {
        const loc = document.getElementById('filter_loc').value;
        const bld = document.getElementById('filter_bld').value;
        const pen = document.getElementById('filter_pen').value;
        const tbody = document.getElementById('animalTableBody');
        const btnSearch = document.getElementById('btnSearch');

        if(!loc) {
            showToast('Please select a Location first.', 'error');
            return;
        }

        btnSearch.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Loading...';
        btnSearch.disabled = true;
        tbody.innerHTML = '<tr><td colspan="5" class="empty-state"><i class="fa-solid fa-spinner fa-spin"></i> Fetching records...</td></tr>';
        
        // Reset controls
        document.getElementById('selectAll').checked = false;
        document.getElementById('selectAll').disabled = true;
        updateSelectionCount();

        fetchJSON(`?action=get_filtered_animals&loc_id=${loc}&bld_id=${bld}&pen_id=${pen}`).then(data => {
            tbody.innerHTML = '';
            btnSearch.innerHTML = '<i class="fa-solid fa-magnifying-glass"></i> Load Animals';
            btnSearch.disabled = false;

            // Safety check for errors
            if (data.error) {
                tbody.innerHTML = `<tr><td colspan="5"><div class="empty-state" style="color:var(--red);"><i class="fa-solid fa-triangle-exclamation"></i> Error: ${data.error}</div></td></tr>`;
                return;
            }

            if(!Array.isArray(data) || data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5"><div class="empty-state"><i class="fa-solid fa-piggy-bank"></i> No active animals found in this scope.</div></td></tr>';
                return;
            }

            document.getElementById('selectAll').disabled = false;

            data.forEach(a => {
                const amt = parseFloat(a.TOTAL_MISC_AMT || 0).toLocaleString('en-PH', {minimumFractionDigits: 2});
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td style="text-align: center;">
                        <div class="chk-container">
                            <input type="checkbox" class="animal-chk" value="${a.ANIMAL_ID}" onchange="updateSelectionCount()">
                        </div>
                    </td>
                    <td><div class="tag-no">${a.TAG_NO}</div></td>
                    <td><div class="pen-name">${a.PEN_NAME || 'Unassigned'}</div></td>
                    <td style="text-align:right;"><div class="td-amt">₱${amt}</div></td>
                    <td style="text-align:center;">
                        <button class="btn-sm-view" onclick="viewLedger(${a.ANIMAL_ID}, '${a.TAG_NO}')">View Ledger</button>
                    </td>
                `;
                tbody.appendChild(tr);
            });
        });
    }

    // --- SELECTION LOGIC ---
    function toggleAll(source) {
        const checkboxes = document.querySelectorAll('.animal-chk');
        checkboxes.forEach(chk => chk.checked = source.checked);
        updateSelectionCount();
    }

    function updateSelectionCount() {
        const selected = document.querySelectorAll('.animal-chk:checked').length;
        const btnBatch = document.getElementById('btnBatchAdd');
        
        btnBatch.innerHTML = `<i class="fa-solid fa-plus"></i> Add Fee to Selected (${selected})`;
        btnBatch.disabled = selected === 0;

        // Manage Select All state
        const total = document.querySelectorAll('.animal-chk').length;
        const selectAll = document.getElementById('selectAll');
        if(total > 0 && selected === total) {
            selectAll.checked = true;
            selectAll.indeterminate = false;
        } else if (selected > 0) {
            selectAll.checked = false;
            selectAll.indeterminate = true;
        } else {
            selectAll.checked = false;
            selectAll.indeterminate = false;
        }
    }

    // --- BATCH MODAL LOGIC ---
    function openBatchModal() {
        const checkboxes = document.querySelectorAll('.animal-chk:checked');
        const ids = Array.from(checkboxes).map(c => c.value);
        
        if(ids.length === 0) return;

        document.getElementById('batch_animal_ids').value = JSON.stringify(ids);
        document.getElementById('target-count').textContent = ids.length;
        document.getElementById('feeForm').reset();
        document.getElementById('modal-alert').style.display = 'none';
        
        document.getElementById('feeModal').classList.add('show');
    }

    function closeModal(id) {
        document.getElementById(id).classList.remove('show');
    }

    async function submitBatchFee() {
        const form = document.getElementById('feeForm');
        if(!form.checkValidity()) { form.reportValidity(); return; }

        const btn = document.getElementById('btn-save');
        const origText = btn.innerHTML;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Processing...';
        btn.disabled = true;

        const formData = new URLSearchParams(new FormData(form));

        try {
            const res = await fetch('../process/addMiscFeeBulk.php', { 
                method: 'POST', 
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, 
                body: formData.toString() 
            });
            const data = await res.json();

            if(data.success) {
                closeModal('feeModal');
                showToast(data.message, 'success');
                // Refresh the table to show updated totals
                fetchFilteredAnimals();
                btn.innerHTML = origText; btn.disabled = false;
            } else {
                const alert = document.getElementById('modal-alert');
                alert.textContent = data.message || 'Error saving fees.';
                alert.style.display = 'block';
                btn.innerHTML = origText; btn.disabled = false;
            }
        } catch(e) {
            const alert = document.getElementById('modal-alert');
            alert.textContent = 'Connection error. Please try again.';
            alert.style.display = 'block';
            btn.innerHTML = origText; btn.disabled = false;
        }
    }

    // --- INDIVIDUAL LEDGER VIEWER & EDIT ---
    function viewLedger(animalId, tagNo) {
        currentViewingAnimalId = animalId; 
        
        if(tagNo) document.getElementById('ledger-tag-no').textContent = tagNo;
        const tbody = document.getElementById('ledgerTableBody');
        
        tbody.innerHTML = '<tr><td colspan="4" style="text-align:center; padding: 2rem;"><i class="fa-solid fa-spinner fa-spin"></i> Loading records...</td></tr>';
        document.getElementById('ledgerModal').classList.add('show');

        fetchJSON(`?action=get_ledger&animal_id=${animalId}`).then(data => {
            tbody.innerHTML = '';
            
            if (data.error) {
                tbody.innerHTML = `<tr><td colspan="4" style="text-align:center; padding: 2rem; color:var(--red);"><i class="fa-solid fa-triangle-exclamation"></i> Error loading ledger.</td></tr>`;
                return;
            }

            if(!Array.isArray(data) || data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="4" style="text-align:center; padding: 2rem; color:var(--text-muted); font-style:italic;">No fees recorded for this animal yet.</td></tr>';
                return;
            }

            data.forEach(fee => {
                const amt = parseFloat(fee.AMOUNT).toLocaleString('en-PH', {minimumFractionDigits: 2});
                const safeDesc = fee.FEE_DESCRIPTION.replace(/'/g, "\\'").replace(/"/g, "&quot;");
                
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td class="td-desc">${fee.FEE_DESCRIPTION}</td>
                    <td class="td-amt" style="text-align:right;">+ ₱${amt}</td>
                    <td class="td-date" style="text-align:right;">${fee.DATE_FMT}</td>
                    <td style="text-align:center;">
                        <div class="actions">
                            <button class="action-btn edit" onclick="editFee(${fee.FEE_ID}, ${fee.AMOUNT}, '${safeDesc}', ${animalId})" title="Edit"><i class="fa-solid fa-pen-to-square"></i></button>
                            <button class="action-btn delete" onclick="deleteFee(${fee.FEE_ID}, ${animalId})" title="Delete"><i class="fa-solid fa-trash-can"></i></button>
                        </div>
                    </td>
                `;
                tbody.appendChild(tr);
            });
        });
    }

    // --- EDIT / DELETE LOGIC ---
    function editFee(feeId, amount, desc, animalId) {
        document.getElementById('edit_fee_id').value = feeId;
        document.getElementById('edit_animal_id').value = animalId;
        document.getElementById('edit_fee_amount').value = amount;
        
        // Decode HTML entities (like &quot;) back to actual characters for the textarea
        const txt = document.createElement("textarea");
        txt.innerHTML = desc;
        document.getElementById('edit_fee_desc').value = txt.value;
        
        document.getElementById('edit-modal-alert').style.display = 'none';
        document.getElementById('editFeeModal').classList.add('show');
    }

    async function submitEditFee() {
        const form = document.getElementById('editFeeForm');
        if(!form.checkValidity()) { form.reportValidity(); return; }

        const btn = document.getElementById('btn-edit-save');
        const origText = btn.innerHTML;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Updating...';
        btn.disabled = true;

        const formData = new URLSearchParams(new FormData(form));

        try {
            const res = await fetch('../process/editMiscFee.php', { 
                method: 'POST', 
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, 
                body: formData.toString() 
            });
            const data = await res.json();

            if(data.success) {
                closeModal('editFeeModal');
                showToast(data.message, 'success');
                // Refresh the open ledger and the main background table
                viewLedger(currentViewingAnimalId);
                fetchFilteredAnimals();
                btn.innerHTML = origText; btn.disabled = false;
            } else {
                const alert = document.getElementById('edit-modal-alert');
                alert.textContent = data.message || 'Error updating fee.';
                alert.style.display = 'block';
                btn.innerHTML = origText; btn.disabled = false;
            }
        } catch(e) {
            const alert = document.getElementById('edit-modal-alert');
            alert.textContent = 'Connection error. Please try again.';
            alert.style.display = 'block';
            btn.innerHTML = origText; btn.disabled = false;
        }
    }

    async function deleteFee(feeId, animalId) {
        if(confirm("Are you sure you want to permanently delete this fee?\n\nThis will instantly adjust the animal's running total.")) {
            try {
                const formData = new URLSearchParams();
                formData.append('fee_id', feeId);
                formData.append('animal_id', animalId);

                const res = await fetch('../process/deleteMiscFee.php', { 
                    method: 'POST', 
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, 
                    body: formData.toString() 
                });
                const data = await res.json();

                if(data.success) {
                    showToast(data.message, 'success');
                    // Refresh the open ledger and the main background table
                    viewLedger(animalId);
                    fetchFilteredAnimals();
                } else {
                    showToast(data.message, 'error');
                }
            } catch(e) {
                showToast('Connection error.', 'error');
            }
        }
    }

    // --- UTILS ---
    function showToast(msg, type = 'success') {
        const t = document.createElement('div');
        t.className = 'toast';
        t.style.borderLeft = `4px solid ${type === 'error' ? 'var(--red)' : 'var(--purple)'}`;
        t.innerHTML = `${type === 'error' ? '❌' : ' '} ${msg}`;
        document.getElementById('toastContainer').appendChild(t);
        setTimeout(() => t.remove(), 3500);
    }
</script>
</body>
</html>