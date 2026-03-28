<?php
// views/group_mortality.php
ob_start(); 
error_reporting(0);
ini_set('display_errors', 0);
include '../config/Connection.php';

include '../security/checkAccess.php';
// Ensure 'group_mortality' is in access_control, else use a fallback
checkAccess('group_mortality'); 
$page = "transactions";
include '../common/navbar.php';
include '../common/chat_support.php';
include '../functions/getUsersLocation.php'; // ADDED LOCATION FUNCTION


try {
    if (!isset($conn)) { throw new Exception("Database connection failed."); }

    // Fetch Locations based on user restriction
    if ($USER_LOCATION_ != 1000) {
        $stmt = $conn->prepare("SELECT LOCATION_ID, LOCATION_NAME FROM LOCATIONS WHERE LOCATION_ID = ? ORDER BY LOCATION_NAME ASC");
        $stmt->execute([$USER_LOCATION_]);
        $locs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $locs = $conn->query("SELECT LOCATION_ID, LOCATION_NAME FROM LOCATIONS ORDER BY LOCATION_NAME ASC")->fetchAll(PDO::FETCH_ASSOC);
    }

    // Fetch Buyers for Dropdown (NEW)
    $buyers = $conn->query("SELECT FULL_NAME FROM buyers WHERE IS_ACTIVE = 1 ORDER BY FULL_NAME ASC")->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    // Handle error silently
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Group Mortality | FarmPro</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=DM+Mono:wght@400;500;700&display=swap" rel="stylesheet">
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
            --border-active:  rgba(239,68,68,0.5); /* Red Accent */
            
            --red:            #ef4444;
            --red-dim:        rgba(239,68,68,0.12);
            --red-glow:       rgba(239,68,68,0.25);
            --emerald:        #10b981;
            --emerald-dim:    rgba(16,185,129,0.12);
            --emerald-glow:   rgba(16,185,129,0.25);
            --blue:           #3b82f6;
            --blue-dim:       rgba(59,130,246,0.12);
            --amber:          #f59e0b;
            
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
            background-image: radial-gradient(ellipse 80% 50% at 50% -20%, rgba(239,68,68,0.06) 0%, transparent 60%);
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
            color: var(--red); background: var(--red-dim); border: 1px solid rgba(239,68,68,0.2);
            padding: 6px 12px; border-radius: 99px;
        }

        /* ─── HEADER ─── */
        .page-header { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 2.5rem; gap: 1.5rem; flex-wrap: wrap; }
        .header-info h1 { font-size: clamp(1.8rem, 4vw, 2.5rem); font-weight: 700; margin: 0 0 0.5rem 0; color: #fff; letter-spacing: -0.02em;}
        .header-info h1 span { background: linear-gradient(135deg, var(--red), #991b1b); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
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
        .panel-title i { color: var(--red); }
        .panel-subtitle { font-size: 0.9rem; color: var(--text-secondary); margin-bottom: 2rem; }

        .step-label { color: var(--red); font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 1rem; display: block;}
        
        .form-group { margin-bottom: 1.25rem; display: flex; flex-direction: column; gap: 6px;}
        .form-label { color: var(--text-secondary); font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; display: flex; justify-content: space-between; align-items: center; }
        
        .form-control, .form-select {
            width: 100%; padding: 12px 14px; background: var(--bg-elevated); border: 1px solid var(--border);
            border-radius: var(--radius-md); color: var(--text-primary); font-size: 0.95rem; transition: var(--transition); outline: none; box-sizing: border-box; font-family: var(--font);
        }
        .form-select {
            appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2394a3b8' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 12px center; cursor: pointer;
        }
        .form-control:focus, .form-select:focus { border-color: var(--red); box-shadow: 0 0 0 3px var(--red-glow); background: var(--bg-hover); }
        .form-select:disabled, .form-control:disabled { opacity: 0.5; cursor: not-allowed; background: rgba(255,255,255,0.02); border-color: transparent;}

        /* Lock Badge Wrapper */
        .select-wrap { position: relative; display: flex; align-items: center;}
        .select-wrap .form-control, .select-wrap .form-select { flex: 1; }
        .select-wrap .lock-badge { display: none; position: absolute; right: 14px; color: var(--red); font-size: 0.9rem; pointer-events: none;}
        .select-wrap.locked .lock-badge { display: block; }
        .select-wrap.locked .form-select, .select-wrap.locked .form-control { border-color: rgba(239,68,68,0.4); background: var(--red-dim); opacity: 0.9; cursor: not-allowed; padding-right: 35px;}

        .input-with-btn { display: flex; gap: 8px; }
        .input-with-btn .form-control, .input-with-btn .form-select { flex: 1; }
        
        .btn-mini {
            background: var(--bg-elevated); border: 1px solid var(--border); color: var(--text-primary);
            border-radius: var(--radius-md); padding: 0 16px; cursor: pointer; font-size: 0.85rem; font-weight: 700;
            white-space: nowrap; flex-shrink: 0; transition: var(--transition); font-family: var(--font); display: flex; align-items: center; justify-content: center; gap: 6px;
        }
        .btn-mini:hover { background: var(--bg-hover); color: var(--red); border-color: var(--red); }

        /* Summary Box */
        .summary-box { margin-top: 1.5rem; background: var(--bg-base); padding: 1.25rem; border-radius: var(--radius-md); border: 1px solid var(--border);}
        .summary-row { display: flex; justify-content: space-between; align-items: center; font-size: 0.9rem; color: var(--text-secondary); font-weight: 600; margin-bottom: 8px;}
        .summary-row span.val { color: #fff; font-family: var(--font-mono); font-weight: 700; }
        .summary-row span.val-sub { color: var(--text-muted); font-family: var(--font-mono); font-weight: 600; }
        
        .summary-total { margin-top: 10px; padding-top: 10px; border-top: 1px dashed var(--border); font-weight: 700; color: var(--text-primary); display: flex; justify-content: space-between; align-items: center; font-size: 1.05rem;}
        .summary-total span.val { color: #fff; font-size: 1.25rem; font-weight: 800; font-family: var(--font-mono); transition: var(--transition);}
        
        /* Dynamic Profit/Loss Classes */
        .net-loss { color: var(--red) !important; text-shadow: 0 0 10px rgba(239,68,68,0.5); animation: pulseError 2s infinite;}
        .net-gain { color: var(--emerald) !important; text-shadow: 0 0 10px rgba(16,185,129,0.5);}
        @keyframes pulseError { 0%, 100% { text-shadow: 0 0 10px rgba(239,68,68,0.3); } 50% { text-shadow: 0 0 20px rgba(239,68,68,0.8); } }

        .btn-submit {
            width: 100%; margin-top: 1.5rem; padding: 14px; background: var(--red); border: none;
            border-radius: var(--radius-md); color: #fff; font-weight: 800; font-size: 1.05rem; font-family: var(--font);
            cursor: pointer; transition: var(--transition); display: flex; align-items: center; justify-content: center; gap: 8px; text-transform: uppercase; letter-spacing: 0.05em;
        }
        .btn-submit:disabled { opacity: 0.5; cursor: not-allowed; background: var(--bg-elevated); color: var(--text-muted); border: 1px solid var(--border);}
        .btn-submit:hover:not(:disabled) { background: #b91c1c; box-shadow: 0 4px 15px var(--red-glow); transform: translateY(-2px); }

        /* ─── WORKSPACE (RIGHT) ─── */
        .workspace-panel { display: flex; flex-direction: column; gap: 1.5rem; }
        
        .picker-section { background: var(--bg-surface); border: 1px solid var(--border); border-radius: var(--radius-xl); padding: 2rem; box-shadow: var(--shadow-md);}
        .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
        .section-title { font-size: 1.15rem; font-weight: 700; color: #fff; display: flex; align-items: center; gap: 10px;}
        .section-title i { color: var(--blue); }

        .select-all-container {
            display: flex; align-items: center; gap: 8px; font-size: 0.85rem; font-weight: 700; color: var(--blue);
            cursor: pointer; padding: 6px 12px; border-radius: 99px; background: var(--blue-dim); border: 1px solid rgba(59,130,246,0.3); transition: var(--transition);
        }
        .select-all-container:hover { background: rgba(59,130,246,0.2); }
        .select-all-container input { cursor: pointer; accent-color: var(--blue); width: 16px; height: 16px; margin: 0; }

        /* Animal Selection Grid */
        .animal-grid {
            display: grid; grid-template-columns: repeat(auto-fill, minmax(110px, 1fr)); gap: 1rem;
            max-height: 300px; overflow-y: auto; padding-right: 5px;
        }
        /* Custom Scrollbar for list */
        .animal-grid::-webkit-scrollbar { width: 6px; }
        .animal-grid::-webkit-scrollbar-track { background: transparent; }
        .animal-grid::-webkit-scrollbar-thumb { background: #334155; border-radius: 4px; }

        .animal-card {
            background: var(--bg-elevated); border: 1px solid var(--border); border-radius: var(--radius-md);
            padding: 1rem; cursor: pointer; text-align: center; transition: var(--transition); display: flex; flex-direction: column; gap: 6px; align-items: center; justify-content: center;
        }
        .animal-card:hover { border-color: rgba(255,255,255,0.2); background: var(--bg-hover); transform: translateY(-2px); }
        .animal-card i { font-size: 1.5rem; color: var(--text-muted); transition: var(--transition);}
        .animal-card .tag { font-weight: 700; font-family: var(--font-mono); color: var(--text-primary); font-size: 0.95rem; }
        
        .animal-card.in-table { background: var(--red-dim); border-color: rgba(239,68,68,0.4); opacity: 0.6; pointer-events: none; }
        .animal-card.in-table i { color: var(--red); }

        /* Action Table */
        .table-section { background: var(--bg-surface); border: 1px solid var(--border); border-radius: var(--radius-xl); overflow: hidden; box-shadow: var(--shadow-md);}
        .table-header { display: flex; justify-content: space-between; align-items: center; padding: 1.5rem; border-bottom: 1px solid var(--border); background: var(--bg-elevated); flex-wrap: wrap; gap: 1rem;}
        
        .batch-table-container { max-height: calc(100vh - 300px); overflow-y: auto; }
        .batch-table-container::-webkit-scrollbar { width: 8px; }
        .batch-table-container::-webkit-scrollbar-track { background: var(--bg-surface); }
        .batch-table-container::-webkit-scrollbar-thumb { background: #334155; border-radius: 4px; }

        .custom-table { width: 100%; border-collapse: collapse; min-width: 800px; }
        .custom-table th {
            background: var(--bg-base); color: var(--text-muted); font-size: 0.7rem; text-transform: uppercase;
            letter-spacing: 0.05em; padding: 16px; text-align: left; font-weight: 700; border-bottom: 1px solid var(--border);
            position: sticky; top: 0; z-index: 10;
        }
        .custom-table td { padding: 14px 16px; border-bottom: 1px solid rgba(255,255,255,0.03); vertical-align: middle; color: var(--text-primary); }
        .custom-table tbody tr:hover { background: rgba(255,255,255,0.01); }

        .custom-table select, .custom-table input {
            background: var(--bg-base); border: 1px solid var(--border); color: #fff; padding: 10px 12px;
            border-radius: 8px; width: 100%; font-size: 0.95rem; font-family: var(--font); outline: none; transition: var(--transition); box-sizing: border-box;
        }
        .custom-table input.cost-input { font-family: var(--font-mono); font-weight: 600; text-align: right; color: var(--emerald);}
        .custom-table input:focus, .custom-table select:focus { border-color: var(--red); box-shadow: 0 0 0 3px var(--red-glow); }
        
        .td-tag { font-family: var(--font-mono); font-weight: 700; color: #fff; font-size: 1.05rem;}
        
        .btn-remove { background: transparent; border: none; color: var(--text-muted); cursor: pointer; font-size: 1.25rem; transition: color var(--transition); display: flex; align-items: center; justify-content: center; width: 32px; height: 32px; border-radius: 6px;}
        .btn-remove:hover { color: var(--red); background: var(--red-dim); }

        .btn-clear { background: var(--bg-elevated); border: 1px solid var(--border); color: var(--text-primary); padding: 8px 14px; border-radius: 8px; cursor: pointer; font-size: 0.85rem; font-weight: 700; transition: var(--transition); display: inline-flex; align-items: center; gap: 6px; font-family: var(--font);}
        .btn-clear:hover { background: var(--red-dim); color: var(--red); border-color: var(--red);}

        .empty-state { text-align: center; padding: 4rem 2rem; color: var(--text-muted); font-style: italic; }

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
            .input-with-btn { flex-direction: column; }
            .input-with-btn .btn-mini { width: 100%; padding: 12px; justify-content: center;}
            
            .custom-table thead { display: none; }
            .custom-table, .custom-table tbody, .custom-table tr, .custom-table td { display: block; width: 100%; box-sizing: border-box; }
            
            .custom-table tr { background: rgba(255,255,255,0.02); border: 1px solid var(--border); border-radius: var(--radius-lg); margin-bottom: 1rem; padding: 1rem; }
            .custom-table td { display: flex; flex-direction: column; gap: 6px; padding: 0.75rem 0; border-bottom: 1px dashed rgba(255,255,255,0.05); text-align: left; }
            .custom-table td:last-child { border-bottom: none; align-items: flex-end;}
            .custom-table td::before { content: attr(data-label); font-weight: 700; color: var(--text-muted); font-size: 0.75rem; text-transform: uppercase; }
        }
    </style>
</head>
<body>

<div id="toastContainer"></div>

<div class="container">
    
    <div class="top-bar">
        <a href="transactions.php" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back to Transactions
        </a>
        <span class="page-badge"><i class="fa-solid fa-skull-crossbones"></i> Loss Management</span>
    </div>

    <header class="page-header">
        <div class="header-info">
            <h1>Group <span>Mortality</span></h1>
            <p>Record mass mortality events, stolen inventory, and calculate total financial loss.</p>
        </div>
    </header>

    <div class="main-grid">
        
        <div class="control-panel">
            <div class="panel-title"><i class="fa-solid fa-skull"></i> Batch Processing</div>
            <div class="panel-subtitle">Select location and apply default values.</div>

            <form id="settingsForm">
                <span class="step-label">STEP 1: Locate Group</span>
                
                <div style="background:var(--bg-elevated); padding:1rem; border-radius:var(--radius-md); border:1px solid var(--border); margin-bottom:1.5rem;">
                    <div class="form-group">
                        <select id="location_id" class="form-select" onchange="loadBuildings(this.value)" <?php echo ($USER_LOCATION_ != 1000) ? 'disabled' : ''; ?>>
                            <?php if($USER_LOCATION_ == 1000): ?>
                                <option value="">-- Select Location --</option>
                            <?php endif; ?>
                            <?php foreach($locs as $l): ?>
                                <option value="<?php echo $l['LOCATION_ID']; ?>" <?php echo ($USER_LOCATION_ != 1000 && $l['LOCATION_ID'] == $USER_LOCATION_) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($l['LOCATION_NAME']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <select id="building_id" class="form-select" onchange="loadPens(this.value)" disabled><option value="">-- Select Building --</option></select>
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <select id="pen_id" class="form-select" onchange="loadAnimals(this.value)" disabled><option value="">-- Select Pen --</option></select>
                    </div>
                </div>

                <span class="step-label">STEP 2: Batch Details</span>
                
                <div class="form-group">
                    <label class="form-label">Recovered Cost (₱) <span style="font-weight:normal; text-transform:none;">(e.g. Scraps sold)</span></label>
                    <div class="input-with-btn">
                        <input type="number" id="default_cost" class="form-control" placeholder="0.00" step="0.01" value="0.00" style="font-family:var(--font-mono); font-weight:700; color:var(--emerald);">
                        <button type="button" class="btn-mini" onclick="updateAllCosts()"><i class="fa-solid fa-check"></i> Apply All</button>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Reason for Loss <span style="color:var(--red);">*</span></label>
                    <div class="input-with-btn">
                        <select id="default_reason" class="form-select">
                            <option value="Deceased">Deceased</option>
                            <option value="Stolen">Stolen</option>
                        </select>
                        <button type="button" class="btn-mini" onclick="updateAllReasons()"><i class="fa-solid fa-check"></i> Apply All</button>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label"> Notes / Details</label>
                    <div class="input-with-btn">
                        <input type="text" id="default_notes" class="form-control" placeholder="e.g. Disease Outbreak">
                        <button type="button" class="btn-mini" onclick="updateAllNotes()"><i class="fa-solid fa-check"></i> Apply All</button>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Buyer / Recipient <span style="font-weight:normal; text-transform:none;">(If recovered cost > 0)</span></label>
                    <select id="customer_name" class="form-select">
                        <option value="">-- No Buyer (Discarded/Incinerated) --</option>
                        <?php foreach($buyers as $b): ?>
                            <option value="<?= htmlspecialchars($b['FULL_NAME']) ?>"><?= htmlspecialchars($b['FULL_NAME']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Date of Loss</label>
                    <input type="text" id="mortality_date" class="form-control date-picker" placeholder="Select Date & Time">
                </div>

                <div class="summary-box">
                    <div class="summary-row">
                        <span>Total Losses Selected:</span> 
                        <span id="sum-count" class="val">0</span>
                    </div>
                    <div class="summary-row">
                        <span>Original Est. Animal Value:</span> 
                        <span id="sum-animal-cost" class="val-sub">₱0.00</span>
                    </div>
                    <div class="summary-row" style="margin-bottom: 0;">
                        <span>Total Recovered Amount:</span> 
                        <span id="sum-recovered" class="val-sub" style="color:var(--emerald);">₱0.00</span>
                    </div>
                    <div class="summary-total">
                        <span>Financial Impact:</span> 
                        <span id="sum-net" class="val">₱0.00</span>
                    </div>
                </div>

                <button type="button" class="btn-submit" id="btn-submit" onclick="submitBatch()" disabled>
                    <i class="fa-solid fa-skull"></i> Confirm Mortality
                </button>
            </form>
        </div>

        <div class="workspace-panel">
            
            <div class="picker-section" id="pickerSection">
                <div class="section-header">
                    <div class="section-title"><i class="fa-solid fa-paw"></i> Step 3: Select Animals</div>
                    <label class="select-all-container" style="display:none;" id="select-all-wrapper">
                        <input type="checkbox" id="select-all-check" onchange="toggleSelectAll(this)"> Select All
                    </label>
                </div>
                <div id="animal-grid" class="animal-grid">
                    <div style="grid-column:1/-1;text-align:center;padding:3rem 1rem;color:var(--text-muted); font-style:italic; border: 1px dashed var(--border); border-radius: var(--radius-md);">
                        <i class="fa-solid fa-arrow-left" style="font-size: 2rem; display: block; margin-bottom: 1rem; opacity: 0.5;"></i>
                        Select a Pen from the control panel to load animals.
                    </div>
                </div>
            </div>

            <div class="table-section">
                <div class="table-header">
                    <div class="section-title"><i class="fa-solid fa-list-check"></i> Step 4: Confirm List</div>
                    <button class="btn-clear" onclick="clearTable()"><i class="fa-solid fa-trash-can"></i> Clear All</button>
                </div>
                
                <div class="batch-table-container">
                    <table class="custom-table">
                        <thead>
                            <tr>
                                <th style="width: 20%; padding-left: 1.5rem;">Tag No &amp; Value</th>
                                <th style="width: 20%;">Reason</th>
                                <th>Details / Notes</th>
                                <th style="width: 15%; text-align:right;">Rec. Cost (₱)</th>
                                <th style="width: 50px;"></th>
                            </tr>
                        </thead>
                        <tbody id="mortality-list">
                            <tr id="empty-row"><td colspan="5" style="text-align:center;padding:3rem 1rem;color:var(--text-muted); font-style:italic;"><i class="fa-solid fa-arrow-up" style="font-size: 2rem; display: block; margin-bottom: 1rem; opacity: 0.5;"></i>Click on animals above to add them to the loss record.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
    // --- STATE MANAGEMENT ---
    let selectedAnimals = new Set(); 
    let currentPenAnimals = []; 
    let fpMortalityDate;
    const USER_LOCATION = <?php echo json_encode($USER_LOCATION_); ?>;

    document.addEventListener('DOMContentLoaded', () => {
        // Initialize Flatpickr for Date and Time
        fpMortalityDate = flatpickr("#mortality_date", {
            enableTime: true,
            dateFormat: "Y-m-d H:i", // Backend expected format
            altInput: true,
            altFormat: "M j, Y h:i K", // mm/dd/yyyy display format with AM/PM
            allowInput: true
        });
        
        fpMortalityDate.clear(); // Leave the actual input blank by default

        // Auto-load buildings if user is restricted to a location
        if (USER_LOCATION != 1000) {
            document.getElementById('location_id').value = USER_LOCATION;
            loadBuildings(USER_LOCATION);
        }
    });

    function showToast(msg, type = 'success') {
        const t = document.createElement('div');
        t.className = 'toast';
        t.style.borderLeft = `4px solid ${type === 'error' ? 'var(--red)' : (type === 'warning' ? 'var(--amber)' : 'var(--emerald)')}`;
        
        let icon = '<i class="fa-solid fa-check"></i>';
        if(type === 'error') icon = '<i class="fa-solid fa-xmark"></i>';
        if(type === 'warning') icon = '<i class="fa-solid fa-triangle-exclamation"></i>';
        
        t.innerHTML = `${icon} ${msg}`;
        document.getElementById('toastContainer').appendChild(t);
        setTimeout(() => t.remove(), type === 'error' ? 5000 : 3500);
    }

    // --- 1. CASCADING DROPDOWNS ---
    function loadBuildings(locId) {
        document.getElementById('building_id').innerHTML = '<option value="">Loading...</option>';
        document.getElementById('pen_id').innerHTML = '<option value="">-- Select Pen --</option>';
        document.getElementById('pen_id').disabled = true;
        
        if(!locId) { document.getElementById('building_id').innerHTML = '<option value="">-- Select Building --</option>'; return; }

        fetch(`../process/getBuildingsByLocation.php?location_id=${locId}`)
            .then(r=>r.json())
            .then(data => {
                const bldg = document.getElementById('building_id');
                bldg.innerHTML = '<option value="">-- Select Building --</option>';
                data.buildings.forEach(b => bldg.add(new Option(b.BUILDING_NAME, b.BUILDING_ID)));
                bldg.disabled = false;
            }).catch(err => {
                document.getElementById('building_id').innerHTML = '<option value="">Error</option>';
            });
    }

    function loadPens(bldgId) {
        document.getElementById('pen_id').innerHTML = '<option value="">Loading...</option>';
        
        if(!bldgId) { document.getElementById('pen_id').innerHTML = '<option value="">-- Select Pen --</option>'; return; }

        fetch(`../process/getPensByBuilding.php?building_id=${bldgId}`)
            .then(r=>r.json())
            .then(data => {
                const pen = document.getElementById('pen_id');
                pen.innerHTML = '<option value="">-- Select Pen --</option>';
                data.pens.forEach(p => pen.add(new Option(p.PEN_NAME, p.PEN_ID)));
                pen.disabled = false;
            }).catch(err => {
                document.getElementById('pen_id').innerHTML = '<option value="">Error</option>';
            });
    }

    // --- 2. LOAD GRID ---
    function loadAnimals(penId) {
        const grid = document.getElementById('animal-grid');
        const selectAllWrapper = document.getElementById('select-all-wrapper');
        
        grid.innerHTML = '<div style="grid-column:1/-1; text-align:center; color:var(--text-muted);"><i class="fa-solid fa-spinner fa-spin me-2"></i> Loading animals...</div>';
        selectAllWrapper.style.display = 'none';
        
        if(!penId) {
            grid.innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:3rem 1rem;color:var(--text-muted); font-style:italic; border: 1px dashed var(--border); border-radius: var(--radius-md);"><i class="fa-solid fa-arrow-left" style="font-size: 2rem; display: block; margin-bottom: 1rem; opacity: 0.5;"></i>Select a Pen from the control panel to load animals.</div>';
            return;
        }

        fetch(`../process/getAnimalsByPen.php?pen_id=${penId}`)
            .then(r => r.json())
            .then(data => {
                grid.innerHTML = '';
                currentPenAnimals = []; 

                let rawList = [];
                if (Array.isArray(data)) {
                    rawList = data;
                } else if (data.animal_record && Array.isArray(data.animal_record)) {
                    rawList = data.animal_record;
                }
                
                // Filter active animals only
                rawList.forEach(a => {
                    if(a.IS_ACTIVE == 1) currentPenAnimals.push(a);
                });

                if(currentPenAnimals.length === 0) {
                    grid.innerHTML = '<div style="grid-column:1/-1; text-align:center; color:var(--text-muted); padding: 2rem; font-style:italic;">No active animals found in this pen.</div>';
                    return;
                }

                // Show Select All Checkbox
                selectAllWrapper.style.display = 'flex';
                updateSelectAllState();

                currentPenAnimals.forEach(a => {
                    const card = document.createElement('div');
                    card.className = `animal-card ${selectedAnimals.has(String(a.ANIMAL_ID)) ? 'in-table' : ''}`;
                    card.id = `card-${a.ANIMAL_ID}`;
                    card.onclick = () => addAnimalToTable(a);
                    card.innerHTML = `
                        <i class="fa-solid fa-skull"></i>
                        <div class="tag">${a.TAG_NO}</div>
                    `;
                    grid.appendChild(card);
                });
            })
            .catch(err => {
                console.error("Fetch error:", err);
                grid.innerHTML = '<div style="grid-column:1/-1; text-align:center; color:var(--red);">Error loading animals.</div>';
            });
    }

    // --- SELECT ALL LOGIC ---
    function toggleSelectAll(checkbox) {
        if(checkbox.checked) {
            currentPenAnimals.forEach(animal => {
                if(!selectedAnimals.has(String(animal.ANIMAL_ID))) {
                    addAnimalToTable(animal);
                }
            });
        } else {
            currentPenAnimals.forEach(animal => {
                if(selectedAnimals.has(String(animal.ANIMAL_ID))) {
                    removeAnimal(animal.ANIMAL_ID);
                }
            });
        }
    }

    function updateSelectAllState() {
        const checkbox = document.getElementById('select-all-check');
        if(currentPenAnimals.length === 0) {
            checkbox.checked = false;
            return;
        }
        const allSelected = currentPenAnimals.every(a => selectedAnimals.has(String(a.ANIMAL_ID)));
        checkbox.checked = allSelected;
    }

    // --- 3. TABLE OPERATIONS ---
    function addAnimalToTable(animal) {
        if(selectedAnimals.has(String(animal.ANIMAL_ID))) return;

        const emptyRow = document.getElementById('empty-row');
        if(emptyRow) emptyRow.remove();

        const tbody = document.getElementById('mortality-list');
        
        // Default Values
        const defaultReason = document.getElementById('default_reason').value;
        const defaultNotes = document.getElementById('default_notes').value;
        const defaultCost = document.getElementById('default_cost').value;
        
        // Extract original animal cost safely
        const originalCost = parseFloat(animal.ACQUISITION_COST || animal.COST || animal.TOTAL_COST || 0);

        const tr = document.createElement('tr');
        tr.id = `row-${animal.ANIMAL_ID}`;
        tr.dataset.id = animal.ANIMAL_ID;
        tr.dataset.originalCost = originalCost; // Store for calculation
        
        tr.innerHTML = `
            <td data-label="Tag No" style="padding-left:1.5rem;">
                <div class="td-tag">${animal.TAG_NO}</div>
                <div style="font-size: 0.8rem; color:var(--text-muted); font-family:var(--font-mono); margin-top:4px;">Value: ₱${originalCost.toLocaleString('en-PH', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</div>
            </td>
            <td data-label="Reason">
                <select name="reason[${animal.ANIMAL_ID}]" class="form-select reason-select" onchange="updateCalculations()">
                    <option value="Deceased" ${defaultReason == 'Deceased' ? 'selected' : ''}>Deceased</option>
                    <option value="Stolen" ${defaultReason == 'Stolen' ? 'selected' : ''}>Stolen</option>
                </select>
            </td>
            <td data-label="Details / Notes">
                <input type="text" name="notes[${animal.ANIMAL_ID}]" class="form-control rem-input" value="${defaultNotes}" placeholder="Additional info...">
            </td>
            <td data-label="Rec. Cost (₱)" style="text-align:right;">
                <input type="number" class="form-control cost-input" name="cost[${animal.ANIMAL_ID}]" value="${defaultCost}" step="0.01" min="0" oninput="updateCalculations()">
            </td>
            <td data-label="Remove" style="text-align:center;">
                <button type="button" class="btn-remove" onclick="removeAnimal(${animal.ANIMAL_ID})" title="Remove"><i class="fa-solid fa-xmark"></i></button>
            </td>
        `;
        tbody.appendChild(tr);
        selectedAnimals.add(String(animal.ANIMAL_ID));
        
        const card = document.getElementById(`card-${animal.ANIMAL_ID}`);
        if(card) card.classList.add('in-table');

        updateCalculations();
        updateSelectAllState();
    }

    function removeAnimal(id) {
        const row = document.getElementById(`row-${id}`);
        if(row) row.remove();
        
        selectedAnimals.delete(String(id));
        
        const card = document.getElementById(`card-${id}`);
        if(card) card.classList.remove('in-table');

        if(selectedAnimals.size === 0) {
            document.getElementById('mortality-list').innerHTML = '<tr id="empty-row"><td colspan="5" style="text-align:center;padding:3rem 1rem;color:var(--text-muted); font-style:italic;"><i class="fa-solid fa-arrow-up" style="font-size: 2rem; display: block; margin-bottom: 1rem; opacity: 0.5;"></i>Click on animals above to add them to the loss record.</td></tr>';
        }
        updateCalculations();
        updateSelectAllState();
    }

    function clearTable() {
        if(!confirm("Clear all rows?")) return;
        Array.from(selectedAnimals).forEach(id => removeAnimal(id));
    }

    // --- 4. BULK UPDATES ---
    function updateAllReasons() {
        const newReason = document.getElementById('default_reason').value;
        document.querySelectorAll('select.reason-select').forEach(sel => sel.value = newReason);
    }

    function updateAllNotes() {
        const newNote = document.getElementById('default_notes').value;
        document.querySelectorAll('input[name^="notes"]').forEach(inp => inp.value = newNote);
    }

    function updateAllCosts() {
        const newCost = document.getElementById('default_cost').value;
        document.querySelectorAll('.cost-input').forEach(inp => inp.value = newCost);
        updateCalculations();
    }

    // --- 5. CALCULATIONS ---
    function updateCalculations() {
        const count = selectedAnimals.size;
        document.getElementById('sum-count').innerText = count;

        let totalRecovered = 0;
        let totalAnimalCost = 0;
        
        document.querySelectorAll('#mortality-list tr[id^="row-"]').forEach(tr => {
            const recCost = parseFloat(tr.querySelector('.cost-input').value) || 0;
            const animCost = parseFloat(tr.dataset.originalCost) || 0;
            
            totalRecovered += recCost;
            totalAnimalCost += animCost;
        });
        
        document.getElementById('sum-animal-cost').innerText = "₱" + totalAnimalCost.toLocaleString('en-PH', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        document.getElementById('sum-recovered').innerText = "₱" + totalRecovered.toLocaleString('en-PH', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        
        // Calculate Net Impact
        const netValue = totalRecovered - totalAnimalCost;
        const netElement = document.getElementById('sum-net');
        
        if (netValue < 0) {
            netElement.innerText = "-₱" + Math.abs(netValue).toLocaleString('en-PH', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + " (Loss)";
            netElement.className = "val net-loss";
        } else if (netValue > 0) {
            netElement.innerText = "+₱" + netValue.toLocaleString('en-PH', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + " (Gain)";
            netElement.className = "val net-gain";
        } else {
            netElement.innerText = "₱0.00 (Break Even)";
            netElement.className = "val";
            netElement.style.color = "var(--text-muted)";
        }

        const btn = document.getElementById('btn-submit');
        if(count > 0) {
            btn.disabled = false;
        } else {
            btn.disabled = true;
            netElement.innerText = "₱0.00";
            netElement.className = "val";
            netElement.style.color = "#fff";
        }
    }

    // --- 6. SUBMISSION ---
    function submitBatch() {
        if(!confirm("WARNING: This will permanently mark " + selectedAnimals.size + " animals as DECEASED or STOLEN. Continue?")) return;

        const dateInput = document.getElementById('mortality_date').value;
        if(!dateInput) {
            showToast("Please select a valid Date of Loss.", "error");
            return;
        }

        const btn = document.getElementById('btn-submit');
        const ogText = btn.innerHTML;
        btn.disabled = true; 
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Processing...';

        const records = [];
        document.querySelectorAll('#mortality-list tr[id^="row-"]').forEach(tr => {
            // Combine Reason and Notes
            const reason = tr.querySelector('select[name^="reason"]').value;
            const detail = tr.querySelector('input[name^="notes"]').value;
            const fullRemark = reason + (detail ? " - " + detail : "");

            records.push({
                animal_id: tr.dataset.id,
                remarks: fullRemark, // Send combined string as 'remarks'
                recovered_cost: tr.querySelector('.cost-input').value
            });
        });

        const data = {
            records: records,
            date: dateInput,
            customer_name: document.getElementById('customer_name').value 
        };

        fetch('../process/addBatchMortality.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        })
        .then(r => r.json())
        .then(res => {
            if(res.success) {
                showToast("Mortality Batch Recorded Successfully!", "success");
                setTimeout(() => { location.reload(); }, 1500);
            } else {
                showToast(res.message, "error");
                btn.disabled = false;
                btn.innerHTML = ogText;
            }
        })
        .catch(err => {
            console.error(err);
            showToast("System connection error.", "error");
            btn.disabled = false;
            btn.innerHTML = ogText;
        });
    }
</script>

</body>
</html>