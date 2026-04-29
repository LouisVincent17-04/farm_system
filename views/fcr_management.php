<?php
// views/fcr_management.php
$page = "farm"; 
include '../config/Connection.php';
include '../security/checkAccess.php';
checkAccess('fcr_management');
include '../common/navbar.php';
include '../common/chat_support.php';
include '../functions/getUsersLocation.php'; // ADDED LOCATION FUNCTION

// Fetch Locations for Dropdowns
$locations = [];
if ($USER_LOCATION_ != 1000) {
    $stmt = $conn->prepare("SELECT * FROM locations WHERE LOCATION_ID = ? ORDER BY LOCATION_NAME");
    $stmt->execute([$USER_LOCATION_]);
} else {
    $stmt = $conn->prepare("SELECT * FROM locations ORDER BY LOCATION_NAME");
    $stmt->execute();
}
$locations = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>FCR Management | FarmPro</title>
    
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
            
            --teal:           #14b8a6;
            --teal-dim:       rgba(20,184,166,0.12);
            --teal-glow:      rgba(20,184,166,0.25);
            --emerald:        #10b981;
            --emerald-dim:    rgba(16,185,129,0.12);
            --blue:           #3b82f6;
            --blue-dim:       rgba(59,130,246,0.12);
            --amber:          #f59e0b;
            --amber-dim:      rgba(245,158,11,0.12);
            --orange:         #f97316;
            --orange-dim:     rgba(249,115,22,0.12);
            --purple:         #a855f7;
            --purple-dim:     rgba(168,85,247,0.12);
            --red:            #ef4444;
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
            font-family: var(--font);
            background: var(--bg-base);
            color: var(--text-primary);
            min-height: 100vh;
            padding-bottom: 60px;
            background-image: radial-gradient(ellipse 80% 50% at 50% -20%, rgba(20,184,166,0.06) 0%, transparent 60%);
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
        .back-link:hover { color: var(--text-primary); border-color: var(--teal); background: var(--bg-hover); }

        .page-badge {
            display: inline-flex; align-items: center; gap: 6px; font-size: 0.75rem;
            font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase;
            color: var(--teal); background: var(--teal-dim); border: 1px solid rgba(20,184,166,0.2);
            padding: 6px 12px; border-radius: 99px;
        }
        
        .page-header { margin-bottom: 2rem; text-align: center; }
        .page-header h1 { font-size: clamp(1.8rem, 4vw, 2.5rem); font-weight: 700; margin: 0 0 0.5rem 0; color: #fff; letter-spacing: -0.02em;}
        .page-header h1 span { background: linear-gradient(135deg, var(--teal), #0f766e); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .page-header p { color: var(--text-secondary); font-size: 0.95rem; margin: 0; }
        .page-header p i { color: var(--amber); margin-right: 5px;}

        /* ─── TABS ─── */
        .tabs-container {
            display: flex; justify-content: center; margin-bottom: 2.5rem;
        }
        .tabs {
            display: inline-flex; background: var(--bg-surface); border: 1px solid var(--border);
            border-radius: 99px; padding: 6px; gap: 6px; box-shadow: var(--shadow-md);
        }
        .tab-btn {
            background: transparent; border: none; color: var(--text-secondary); padding: 10px 24px;
            font-size: 0.95rem; font-weight: 700; font-family: var(--font); border-radius: 99px;
            cursor: pointer; transition: all var(--transition); white-space: nowrap; display: flex; align-items: center; gap: 8px;
        }
        .tab-btn:hover { color: var(--text-primary); }
        .tab-btn.active { background: var(--teal); color: #000; box-shadow: 0 4px 12px var(--teal-glow); }
        
        .tab-content { display: none; animation: fadeIn 0.3s ease-out; }
        .tab-content.active { display: block; }
        @keyframes fadeIn { from{opacity:0; transform:translateY(10px);} to{opacity:1; transform:translateY(0);} }

        /* ─── CONFIGURATION GRID (TAB 1) ─── */
        .config-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.5rem; }
        
        .config-card {
            background: var(--bg-surface); border: 1px solid var(--border);
            border-radius: var(--radius-xl); padding: 1.5rem; position: relative; overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); display: flex; flex-direction: column;
        }
        .config-card::before {
            content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px;
        }
        .config-card h3 { margin: 0 0 1.25rem 0; font-size: 1.15rem; font-weight: 700; display: flex; align-items: center; gap: 10px; }
        
        /* Card Specific Themes */
        .h-ind::before { background: var(--red); } .h-ind h3 { color: var(--red); } .btn-ind { background: var(--red); color: #fff;} .btn-ind:hover {background: #dc2626; box-shadow: 0 4px 12px var(--red-glow);}
        .h-loc::before { background: var(--blue); } .h-loc h3 { color: var(--blue); } .btn-loc { background: var(--blue); color: #fff;} .btn-loc:hover {background: #2563eb; box-shadow: 0 4px 12px var(--blue-glow);}
        .h-bldg::before { background: var(--emerald); } .h-bldg h3 { color: var(--emerald); } .btn-bldg { background: var(--emerald); color: #000;} .btn-bldg:hover {background: #059669; box-shadow: 0 4px 12px var(--emerald-glow);}
        .h-pen::before { background: var(--amber); } .h-pen h3 { color: var(--amber); } .btn-pen { background: var(--amber); color: #000;} .btn-pen:hover {background: #d97706; box-shadow: 0 4px 12px var(--amber-glow);}
        .h-age::before { background: var(--purple); } .h-age h3 { color: var(--purple); } .btn-age { background: var(--purple); color: #fff;} .btn-age:hover {background: #9333ea; box-shadow: 0 4px 12px var(--purple-glow);}

        .form-group { display: flex; flex-direction: column; gap: 6px; margin-bottom: 1.25rem; }
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
        .form-input:focus, .form-select:focus { border-color: var(--teal); box-shadow: 0 0 0 3px var(--teal-glow); background: var(--bg-hover); }
        .form-select:disabled, .form-input:disabled { opacity: 0.5; cursor: not-allowed; background: rgba(255,255,255,0.02); }

        .btn-save {
            padding: 12px; border: none; border-radius: var(--radius-md); font-weight: 700; font-family: var(--font);
            cursor: pointer; transition: all var(--transition); width: 100%; margin-top: auto; font-size: 0.95rem;
        }

        /* ─── ACTIVE RULES DISPLAY ─── */
        .active-rules-card {
            background: var(--bg-surface); border: 1px solid var(--border); border-radius: var(--radius-xl);
            padding: 2rem; margin-top: 2rem; box-shadow: var(--shadow-md);
        }
        .active-rules-card h3 { color: #fff; margin: 0 0 1.5rem 0; font-size: 1.25rem; font-weight: 700; display: flex; align-items: center; gap: 10px; }

        /* ─── VIEW & ANALYZE TAB (TAB 2) ─── */
        .filter-ribbon {
            background: var(--bg-surface); border: 1px solid var(--border); border-radius: var(--radius-xl);
            padding: 1.5rem; margin-bottom: 2rem; box-shadow: var(--shadow-md);
        }
        .filter-ribbon h3 { color: #fff; margin: 0 0 1.25rem 0; font-size: 1.15rem; font-weight: 700; display: flex; align-items: center; gap: 10px; }
        
        .table-container {
            background: var(--bg-surface); border: 1px solid var(--border); border-radius: var(--radius-xl);
            overflow: hidden; box-shadow: var(--shadow-md); margin-bottom: 2rem;
        }
        .table-responsive { width: 100%; overflow-x: auto; }
        .data-table { width: 100%; border-collapse: collapse; min-width: 1000px; }
        
        .data-table th {
            background: var(--bg-elevated); color: var(--text-muted); font-size: 0.7rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.07em; padding: 14px 16px; text-align: left; border-bottom: 1px solid var(--border);
        }
        .data-table td { padding: 14px 16px; font-size: 0.95rem; color: var(--text-primary); border-bottom: 1px solid rgba(255,255,255,0.03); vertical-align: middle;}
        .data-table tr:hover { background: rgba(255,255,255,0.01); }

        .pen-header-row { background: var(--bg-elevated); border-top: 2px solid var(--border); }
        .pen-header-row td { color: var(--teal); font-size: 1.05rem; font-weight: 700; padding: 12px 16px !important; letter-spacing: 0.02em;}

        .priority-badge { padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: 700; font-family: var(--font-mono); text-transform: uppercase; letter-spacing: 0.05em;}
        .td-mono { font-family: var(--font-mono); font-weight: 600; color: #fff; }

        /* Detail Drilldown Row */
        .details-row { display: none; background: rgba(15, 23, 42, 0.4); border-bottom: 2px solid var(--border); }
        .details-content {
            padding: 1.5rem; display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1.5rem; border-left: 4px solid var(--teal); margin: 0.5rem; background: var(--bg-elevated);
            border-radius: 0 var(--radius-lg) var(--radius-lg) 0; box-shadow: inset 0 2px 10px rgba(0,0,0,0.2);
        }
        .detail-item { display: flex; flex-direction: column; gap: 6px; }
        .detail-item label { color: var(--text-secondary); font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; }
        .detail-item input {
            background: var(--bg-surface); border: 1px solid var(--border); padding: 10px 12px;
            border-radius: 8px; color: #fff; font-family: var(--font-mono); font-size: 1rem; width: 100%; box-sizing: border-box; outline: none; transition: var(--transition);
        }
        .detail-item input:focus { border-color: var(--teal); }
        .detail-item input[readonly] { background: rgba(255,255,255,0.02); color: var(--text-muted); cursor: not-allowed; border-color: transparent;}
        
        .btn-evaluate {
            background: var(--bg-elevated); color: var(--text-primary); border: 1px solid var(--border);
            padding: 6px 12px; border-radius: 6px; cursor: pointer; font-size: 0.85rem; font-weight: 600; font-family: var(--font); transition: var(--transition); display: inline-flex; align-items: center; gap: 6px;
        }
        .btn-evaluate:hover { background: var(--bg-hover); color: var(--teal); border-color: var(--teal); }
        
        .btn-update {
            background: var(--emerald); color: #000; border: none; padding: 10px 20px; border-radius: 8px;
            cursor: pointer; font-size: 0.95rem; font-weight: 700; font-family: var(--font); transition: var(--transition); height: 100%;
        }
        .btn-update:hover { background: #34d399; box-shadow: 0 4px 15px var(--emerald-glow); transform: translateY(-1px); }

        .empty-state { text-align: center; padding: 4rem 2rem; color: var(--text-muted); font-style: italic; }

        /* Toast Notifications */
        #toastContainer { position: fixed; top: 20px; right: 20px; z-index: 9999; display: flex; flex-direction: column; gap: 10px; }
        .toast {
            background: var(--bg-surface); border: 1px solid var(--border); color: #fff;
            padding: 1rem 1.5rem; border-radius: var(--radius-md); box-shadow: 0 10px 25px rgba(0,0,0,0.5);
            font-size: 0.9rem; font-weight: 600; animation: slideIn 0.3s ease-out;
        }
        @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }

        /* ─── RESPONSIVE ─── */
        @media (max-width: 768px) {
            .container { padding: 1rem; }
            .tabs { width: 100%; overflow-x: auto; justify-content: flex-start; padding: 4px; border-radius: var(--radius-md);}
            .tab-btn { flex-shrink: 0; }
            .config-grid { grid-template-columns: 1fr; }
            
            .data-table { min-width: 0; display: block; }
            .data-table thead { display: none; }
            .data-table tbody { display: block; width: 100%; }
            .data-table tr { 
                display: block; background: var(--bg-surface); border: 1px solid var(--border); 
                border-radius: var(--radius-xl); margin-bottom: 1rem; padding: 1rem;
            }
            .data-table td { 
                display: flex; justify-content: space-between; align-items: center; 
                padding: 0.75rem 0; border-bottom: 1px dashed rgba(255,255,255,0.05); text-align: right;
            }
            .data-table td:last-child { border-bottom: none; padding-top: 1rem; }
            .data-table td::before { 
                content: attr(data-label); font-weight: 700; color: var(--text-muted); 
                font-size: 0.75rem; text-transform: uppercase; text-align: left; flex-shrink: 0; margin-right: 1rem;
            }

            .pen-header-row { border: 1px solid var(--border); background: var(--bg-elevated); padding: 1rem; border-radius: var(--radius-md); margin-bottom: 1rem;}
            .pen-header-row td { display: block; text-align: left; border: none; padding: 0 !important; }
            .pen-header-row td::before { display: none; }

            .details-content { grid-template-columns: 1fr; border-left: none; border-top: 4px solid var(--teal); border-radius: 0 0 var(--radius-lg) var(--radius-lg); margin: 0;}
            .detail-item button { margin-top: 1rem; width: 100%;}
        }
    </style>
</head>
<body>

<div id="toastContainer"></div>

<div class="container">
    
    <div class="top-bar">
        <a href="farm_dashboard.php" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back to Farm Dashboard
        </a>
        <span class="page-badge"><i class="fa-solid fa-chart-line"></i> Performance</span>
    </div>

    <div class="page-header">
        <h1>FCR Priority <span>Manager</span></h1>
        <p><i class="fa-solid fa-layer-group"></i> Hierarchy Rule: Individual &gt; Pen &gt; Building &gt; Location &gt; Age</p>
    </div>

    <div class="tabs-container">
        <div class="tabs">
            <button class="tab-btn active" onclick="switchTab('config')"><i class="fa-solid fa-sliders"></i> Configuration</button>
            <button class="tab-btn" onclick="switchTab('view')"><i class="fa-solid fa-magnifying-glass-chart"></i> View &amp; Analyze</button>
        </div>
    </div>

    <div id="config" class="tab-content active">
        <div class="config-grid">
            
            <div class="config-card h-ind">
                <h3><i class="fa-solid fa-bullseye"></i> Individual <span style="font-size:0.7rem; color:var(--text-muted); font-weight:500;">(Highest Priority)</span></h3>
                <form onsubmit="saveConfig(event, 'Individual')" style="display:flex; flex-direction:column; height:100%;">
                    <div class="form-group">
                        <label>1. Location</label>
                        <select id="i_loc" class="form-select" onchange="loadBuildings(this.value, 'i_bldg')" required <?php echo ($USER_LOCATION_ != 1000) ? 'disabled' : ''; ?>>
                            <?php if($USER_LOCATION_ == 1000): ?>
                                <option value="">Select Location</option>
                            <?php endif; ?>
                            <?php foreach($locations as $l): ?>
                                <option value="<?= $l['LOCATION_ID'] ?>" <?= ($USER_LOCATION_ != 1000 && $l['LOCATION_ID'] == $USER_LOCATION_) ? 'selected' : '' ?>><?= htmlspecialchars($l['LOCATION_NAME']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($USER_LOCATION_ != 1000): ?>
                            <input type="hidden" name="location_id" value="<?= $USER_LOCATION_ ?>">
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label>2. Building</label>
                        <select id="i_bldg" class="form-select" onchange="loadPens(this.value, 'i_pen')" disabled required>
                            <option value="">Select Location First</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>3. Pen</label>
                        <select id="i_pen" class="form-select" onchange="loadAnimalOptions(this.value, 'i_animal')" disabled required>
                            <option value="">Select Building First</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>4. Select Animal (Tag No)</label>
                        <select id="i_animal" class="form-select" name="animal_id" disabled required>
                            <option value="">Select Pen First</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Target FCR %</label>
                        <input type="number" class="form-input" name="fcr" step="0.01" placeholder="e.g. 0.25" required>
                    </div>
                    <button class="btn-save btn-ind"><i class="fa-solid fa-floppy-disk"></i> Save Individual Rule</button>
                </form>
            </div>

            <div class="config-card h-loc">
                <h3><i class="fa-solid fa-map-location-dot"></i> Location FCR</h3>
                <form onsubmit="saveConfig(event, 'Location')" style="display:flex; flex-direction:column; height:100%;">
                    <div class="form-group">
                        <label>Target Location</label>
                        <select name="location_id" class="form-select" required <?php echo ($USER_LOCATION_ != 1000) ? 'disabled' : ''; ?>>
                            <?php if($USER_LOCATION_ == 1000): ?>
                                <option value="">Select Location</option>
                            <?php endif; ?>
                            <?php foreach($locations as $l): ?>
                                <option value="<?= $l['LOCATION_ID'] ?>" <?= ($USER_LOCATION_ != 1000 && $l['LOCATION_ID'] == $USER_LOCATION_) ? 'selected' : '' ?>><?= htmlspecialchars($l['LOCATION_NAME']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($USER_LOCATION_ != 1000): ?>
                            <input type="hidden" name="location_id" value="<?= $USER_LOCATION_ ?>">
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label>Target FCR %</label>
                        <input type="number" class="form-input" name="fcr" step="0.01" placeholder="e.g. 2.10" required>
                    </div>
                    <button class="btn-save btn-loc"><i class="fa-solid fa-floppy-disk"></i> Save Location Rule</button>
                </form>
            </div>

            <div class="config-card h-bldg">
                <h3><i class="fa-solid fa-warehouse"></i> Building FCR</h3>
                <form onsubmit="saveConfig(event, 'Building')" style="display:flex; flex-direction:column; height:100%;">
                    <div class="form-group">
                        <label>Location</label>
                        <select id="b_loc" class="form-select" onchange="loadBuildings(this.value, 'b_bldg')" required <?php echo ($USER_LOCATION_ != 1000) ? 'disabled' : ''; ?>>
                            <?php if($USER_LOCATION_ == 1000): ?>
                                <option value="">Select Location</option>
                            <?php endif; ?>
                            <?php foreach($locations as $l): ?>
                                <option value="<?= $l['LOCATION_ID'] ?>" <?= ($USER_LOCATION_ != 1000 && $l['LOCATION_ID'] == $USER_LOCATION_) ? 'selected' : '' ?>><?= htmlspecialchars($l['LOCATION_NAME']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($USER_LOCATION_ != 1000): ?>
                            <input type="hidden" name="location_id" value="<?= $USER_LOCATION_ ?>">
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label>Target Building</label>
                        <select id="b_bldg" class="form-select" name="building_id" disabled required>
                            <option value="">Select Location First</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Target FCR %</label>
                        <input type="number" class="form-input" name="fcr" step="0.01" placeholder="e.g. 2.15" required>
                    </div>
                    <button class="btn-save btn-bldg"><i class="fa-solid fa-floppy-disk"></i> Save Building Rule</button>
                </form>
            </div>

            <div class="config-card h-pen">
                <h3><i class="fa-solid fa-border-all"></i> Pen FCR</h3>
                <form onsubmit="saveConfig(event, 'Pen')" style="display:flex; flex-direction:column; height:100%;">
                    <div class="form-group">
                        <label>Location</label>
                        <select id="p_loc" class="form-select" onchange="loadBuildings(this.value, 'p_bldg')" required <?php echo ($USER_LOCATION_ != 1000) ? 'disabled' : ''; ?>>
                            <?php if($USER_LOCATION_ == 1000): ?>
                                <option value="">Select Location</option>
                            <?php endif; ?>
                            <?php foreach($locations as $l): ?>
                                <option value="<?= $l['LOCATION_ID'] ?>" <?= ($USER_LOCATION_ != 1000 && $l['LOCATION_ID'] == $USER_LOCATION_) ? 'selected' : '' ?>><?= htmlspecialchars($l['LOCATION_NAME']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($USER_LOCATION_ != 1000): ?>
                            <input type="hidden" name="location_id" value="<?= $USER_LOCATION_ ?>">
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label>Building</label>
                        <select id="p_bldg" class="form-select" onchange="loadPens(this.value, 'p_pen')" disabled required>
                            <option value="">Select Location First</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Target Pen</label>
                        <select id="p_pen" class="form-select" name="pen_id" disabled required>
                            <option value="">Select Building First</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Target FCR %</label>
                        <input type="number" class="form-input" name="fcr" step="0.01" placeholder="e.g. 2.20" required>
                    </div>
                    <button class="btn-save btn-pen"><i class="fa-solid fa-floppy-disk"></i> Save Pen Rule</button>
                </form>
            </div>

            <div class="config-card h-age">
                <h3><i class="fa-solid fa-calendar-days"></i> Age Rule <span style="font-size:0.7rem; color:var(--text-muted); font-weight:500;">(Fallback Base)</span></h3>
                <form onsubmit="saveConfig(event, 'Age')" style="display:flex; flex-direction:column; height:100%;">
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                        <div class="form-group">
                            <label>Min Days</label>
                            <input type="number" class="form-input" name="min_age" placeholder="0" required>
                        </div>
                        <div class="form-group">
                            <label>Max Days</label>
                            <input type="number" class="form-input" name="max_age" placeholder="30" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Target FCR %</label>
                        <input type="number" class="form-input" name="fcr" step="0.01" placeholder="e.g. 1.80" required>
                    </div>
                    <button class="btn-save btn-age"><i class="fa-solid fa-floppy-disk"></i> Save Age Rule</button>
                </form>
            </div>
        </div>

        <div class="active-rules-card">
            <h3><i class="fa-solid fa-list-check" style="color:var(--emerald);"></i> Active Configuration Rules</h3>
            <div id="configList" style="color:var(--text-muted);"><i class="fa-solid fa-spinner fa-spin"></i> Loading...</div>
        </div>
    </div>

    <div id="view" class="tab-content">
        
        <div class="filter-ribbon">
            <h3><i class="fa-solid fa-filter" style="color:var(--teal);"></i> Filter Animals (Drill Down)</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                <div class="form-group" style="margin:0;">
                    <label>1. Location</label>
                    <select id="v_loc" class="form-select" onchange="handleViewLocChange(this.value)" <?php echo ($USER_LOCATION_ != 1000) ? 'disabled' : ''; ?>>
                        <?php if($USER_LOCATION_ == 1000): ?>
                            <option value="">Select Location</option>
                        <?php endif; ?>
                        <?php foreach($locations as $l): ?>
                            <option value="<?= $l['LOCATION_ID'] ?>" <?= ($USER_LOCATION_ != 1000 && $l['LOCATION_ID'] == $USER_LOCATION_) ? 'selected' : '' ?>><?= htmlspecialchars($l['LOCATION_NAME']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="margin:0;">
                    <label>2. Building</label>
                    <select id="v_bldg" class="form-select" onchange="handleViewBldgChange(this.value)" disabled>
                        <option value="">Select Location First</option>
                    </select>
                </div>
                <div class="form-group" style="margin:0;">
                    <label>3. Pen</label>
                    <select id="v_pen" class="form-select" onchange="loadAnimals()" disabled>
                        <option value="">All Pens</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="table-container">
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Tag No</th>
                            <th>Age</th>
                            <th>Birth Wt</th>
                            <th>Total Feed</th>
                            <th>Calc. Est. Wt</th>
                            <th>Applied Rule</th>
                            <th>Target FCR</th>
                            <th style="text-align:right;">Action</th>
                        </tr>
                    </thead>
                    <tbody id="animalTable">
                        <tr><td colspan="8" class="empty-state"><i class="fa-solid fa-arrow-up me-2"></i> Please select a Building to view animals.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    const USER_LOCATION = <?php echo json_encode($USER_LOCATION_); ?>;

    document.addEventListener('DOMContentLoaded', () => {
        if (USER_LOCATION != 1000) {
            // Pre-trigger cascade for all tabs
            loadBuildings(USER_LOCATION, 'i_bldg');
            loadBuildings(USER_LOCATION, 'b_bldg');
            loadBuildings(USER_LOCATION, 'p_bldg');
            loadBuildings(USER_LOCATION, 'v_bldg');
        }
    });

    function switchTab(id) {
        document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.getElementById(id).classList.add('active');
        event.currentTarget.classList.add('active');
        if(id === 'config') loadConfigs();
    }

    function showToast(msg, type = 'success') {
        const t = document.createElement('div');
        t.className = 'toast';
        t.style.borderLeft = `4px solid ${type === 'error' ? 'var(--red)' : 'var(--emerald)'}`;
        t.innerHTML = `${type === 'error' ? ' ' : ' '} ${msg}`;
        document.getElementById('toastContainer').appendChild(t);
        setTimeout(() => t.remove(), 3500);
    }

    // --- DROPDOWN HELPERS ---
    function loadBuildings(locId, targetId) {
        const sel = document.getElementById(targetId);
        sel.innerHTML = '<option value="">Loading...</option>'; sel.disabled = true;
        
        // Reset downstream if this is the view tab
        if(targetId === 'v_bldg') {
            resetDropdown('v_pen', 'All Pens');
            clearTable();
        } else if(targetId === 'i_bldg') {
            resetDropdown('i_pen', 'Select Building First');
            resetDropdown('i_animal', 'Select Pen First');
        } else if(targetId === 'p_bldg') {
            resetDropdown('p_pen', 'Select Building First');
        }

        if(!locId) { 
            sel.innerHTML = '<option value="">Select Location First</option>'; 
            return; 
        }
        
        fetch(`../process/getBuildingsByLocation.php?location_id=${locId}`)
            .then(r=>r.json()).then(d => {
                sel.innerHTML = '<option value="">Select Building</option>';
                d.buildings.forEach(b => sel.innerHTML += `<option value="${b.BUILDING_ID}">${b.BUILDING_NAME}</option>`);
                sel.disabled = false;
            });
    }
    
    function loadPens(bldgId, targetId) {
        const sel = document.getElementById(targetId);
        sel.innerHTML = '<option value="">Loading...</option>'; sel.disabled = true;

        // Reset downstream
        if(targetId === 'v_pen') {
            // Do not clear table, we want to load animals for the whole building
        } else if(targetId === 'i_pen') {
            resetDropdown('i_animal', 'Select Pen First');
        }

        if(!bldgId) { 
            sel.innerHTML = targetId === 'v_pen' ? '<option value="">All Pens</option>' : '<option value="">Select Building First</option>'; 
            if(targetId === 'v_pen') clearTable();
            return; 
        }

        fetch(`../process/getPensByBuilding.php?building_id=${bldgId}`)
            .then(r=>r.json()).then(d => {
                sel.innerHTML = targetId === 'v_pen' ? '<option value="">All Pens</option>' : '<option value="">Select Pen</option>';
                d.pens.forEach(p => sel.innerHTML += `<option value="${p.PEN_ID}">${p.PEN_NAME}</option>`);
                sel.disabled = false;
                
                if(targetId === 'v_pen') loadAnimals(); // Automatically load animals for the building
            });
    }

    function loadAnimalOptions(penId, targetId) {
        const sel = document.getElementById(targetId);
        sel.innerHTML = '<option value="">Loading...</option>'; sel.disabled = true;
        if(!penId) { sel.innerHTML = '<option value="">Select Pen First</option>'; return; }

        fetch(`../process/processFcrConfig.php?action=get_pen_animals&pen_id=${penId}`)
            .then(r=>r.json()).then(data => {
                sel.innerHTML = '<option value="">Select Animal</option>';
                if(data.length > 0) {
                    data.forEach(a => sel.innerHTML += `<option value="${a.ANIMAL_ID}">${a.TAG_NO}</option>`);
                    sel.disabled = false;
                } else {
                    sel.innerHTML = '<option value="">No Animals in Pen</option>';
                }
            });
    }

    // --- VIEW TAB HANDLERS ---
    function handleViewLocChange(locId) { loadBuildings(locId, 'v_bldg'); }
    function handleViewBldgChange(bldgId) { loadPens(bldgId, 'v_pen'); }

    function resetDropdown(id, placeholder) {
        const el = document.getElementById(id);
        el.innerHTML = `<option value="">${placeholder}</option>`;
        el.disabled = true;
    }

    function clearTable() {
        document.getElementById('animalTable').innerHTML = '<tr><td colspan="8" class="empty-state"><i class="fa-solid fa-arrow-up me-2"></i> Please select a Building to view animals.</td></tr>';
    }

    // --- SAVE LOGIC ---
    function saveConfig(e, type) {
        e.preventDefault();
        const btn = e.target.querySelector('button');
        const ogText = btn.innerHTML;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';
        btn.disabled = true;

        const fd = new FormData(e.target);
        fd.append('action', 'save_config');
        fd.append('type', type);
        
        // Ensure location is appended if disabled (staff user)
        if(USER_LOCATION != 1000 && !fd.has('location_id')) {
            fd.append('location_id', USER_LOCATION);
        }

        fetch('../process/processFcrConfig.php', { method:'POST', body:fd })
            .then(r=>r.json()).then(d => {
                showToast(d.message, d.success ? 'success' : 'error');
                if(d.success) { e.target.reset(); loadConfigs(); }
                btn.innerHTML = ogText;
                btn.disabled = false;
            }).catch(()=>{
                showToast("System connection error.", "error");
                btn.innerHTML = ogText;
                btn.disabled = false;
            });
    }

    function deleteConfig(configId) {
        if(!confirm("Are you sure you want to delete this rule?")) return;
        const fd = new FormData();
        fd.append('action', 'delete_config');
        fd.append('config_id', configId);
        fetch('../process/processFcrConfig.php', { method:'POST', body:fd })
            .then(r=>r.json()).then(d => { 
                showToast(d.message, d.success ? 'success' : 'error'); 
                if(d.success) loadConfigs(); 
            });
    }

    function loadConfigs() {
        fetch('../process/processFcrConfig.php?action=list')
            .then(r=>r.text()).then(h => document.getElementById('configList').innerHTML = h);
    }

    // --- VIEW ANIMALS LOGIC ---
    function loadAnimals() {
        const loc = document.getElementById('v_loc').value || (USER_LOCATION != 1000 ? USER_LOCATION : '');
        const bldg = document.getElementById('v_bldg').value;
        const pen = document.getElementById('v_pen').value; // Can be empty for "All Pens"

        if (!bldg) {
            clearTable();
            return;
        }

        document.getElementById('animalTable').innerHTML = '<tr><td colspan="8" class="empty-state"><i class="fa-solid fa-spinner fa-spin me-2"></i> Compiling FCR Data...</td></tr>';
        
        fetch(`../process/processFcrConfig.php?action=view_animals&loc=${loc}&bldg=${bldg}&pen=${pen}`)
            .then(r=>r.json()).then(data => {
                let html = '';
                if(data.length === 0) {
                    document.getElementById('animalTable').innerHTML = '<tr><td colspan="8" class="empty-state"><i class="fa-solid fa-ghost me-2"></i> No active animals found for this criteria.</td></tr>';
                    return;
                }
                
                let currentPath = '';

                data.forEach(r => {
                    // Grouping by Pen Path Header
                    if (r.path !== currentPath) {
                        html += `<tr class="pen-header-row"><td colspan="8"><i class="fa-solid fa-location-dot me-2"></i> ${r.path}</td></tr>`;
                        currentPath = r.path;
                    }

                    let color = 'var(--text-muted)';
                    let icon = '';
                    if(r.source === 'Individual') { color = 'var(--red)'; icon = '<i class="fa-solid fa-bullseye"></i> '; }
                    else if(r.source === 'Pen') { color = 'var(--amber)'; icon = '<i class="fa-solid fa-border-all"></i> '; }
                    else if(r.source === 'Building') { color = 'var(--emerald)'; icon = '<i class="fa-solid fa-warehouse"></i> '; }
                    else if(r.source === 'Location') { color = 'var(--blue)'; icon = '<i class="fa-solid fa-map-location-dot"></i> '; }
                    else if(r.source === 'Age') { color = 'var(--purple)'; icon = '<i class="fa-solid fa-calendar-days"></i> '; }

                    html += `
                    <tr>
                        <td data-label="Tag No" class="td-mono">${r.tag}</td>
                        <td data-label="Age">${r.age} days</td>
                        <td data-label="Birth Wt">${r.birth_weight} kg</td>
                        <td data-label="Total Feed">${r.feed} kg</td>
                        <td data-label="Calc. Est. Wt" style="color:var(--teal);font-weight:700;font-family:var(--font-mono);">${r.est_weight} kg</td>
                        <td data-label="Applied Rule"><span class="priority-badge" style="border: 1px solid ${color}40; background:${color}15; color:${color}">${icon}${r.source} Rule</span></td>
                        <td data-label="Target FCR" class="td-mono">${r.fcr}</td>
                        <td data-label="Action" style="text-align:right;">
                            <button onclick="toggleDetails(${r.id})" class="btn-evaluate">Evaluate <i class="fa-solid fa-chevron-down"></i></button>
                        </td>
                    </tr>
                    <tr id="details-${r.id}" class="details-row">
                        <td colspan="8" style="padding:0;">
                            <form onsubmit="updateAnimalLog(event, ${r.id})">
                                <input type="hidden" name="animal_id" value="${r.id}">
                                <input type="hidden" name="pen_id" value="${r.pen_id}">
                                <div class="details-content">
                                    <div class="detail-item">
                                        <label>Weight at Birth (kg)</label>
                                        <input type="text" value="${r.birth_weight}" readonly>
                                    </div>
                                    <div class="detail-item">
                                        <label>Total Feed (kg)</label>
                                        <input type="text" id="feed-${r.id}" value="${r.feed}" readonly>
                                    </div>
                                    <div class="detail-item">
                                        <label style="color:var(--teal);">FCR <i class="fa-solid fa-pen-to-square"></i></label>
                                        <input type="number" step="0.01" id="fcr-${r.id}" name="fcr_used" value="${r.fcr}" oninput="recalc(${r.id})" style="border-color:var(--teal); box-shadow: 0 0 10px var(--teal-glow);">
                                    </div>
                                    <div class="detail-item">
                                        <label>Est. Gain (kg)</label>
                                        <input type="text" id="gain-${r.id}" value="${r.gain}" readonly>
                                    </div>
                                    <div class="detail-item">
                                        <label style="color:var(--teal);">Calc. Est. Weight (kg)</label>
                                        <input type="text" id="est-${r.id}" name="est_weight" value="${r.est_weight}" readonly style="color:var(--teal); border-color:rgba(20,184,166,0.3);">
                                    </div>
                                    <div class="detail-item">
                                        <label style="color:var(--amber);">Actual Weight (kg) <i class="fa-solid fa-pen-to-square"></i></label>
                                        <input type="number" step="0.01" name="actual_weight" placeholder="Input Weight" value="${r.actual_weight || ''}" required oninput="recalc(${r.id})" style="border-color:var(--amber);">
                                    </div>
                                    <div class="detail-item">
                                        <label>Date of Weighing</label>
                                        <input type="date" name="weigh_date" value="${new Date().toISOString().split('T')[0]}" required>
                                    </div>
                                    <div class="detail-item" style="display:flex;align-items:flex-end;">
                                        <button type="submit" class="btn-update"><i class="fa-solid fa-floppy-disk me-2"></i> Save Update</button>
                                    </div>
                                </div>
                            </form>
                        </td>
                    </tr>`;
                });
                document.getElementById('animalTable').innerHTML = html;
            });
    }

    function toggleDetails(id) {
        const row = document.getElementById(`details-${id}`);
        const btn = row.previousElementSibling.querySelector('.btn-evaluate');
        if (row.style.display === 'table-row' || row.style.display === 'block') {
            row.style.display = 'none';
            btn.innerHTML = 'Evaluate <i class="fa-solid fa-chevron-down"></i>';
            btn.style.background = 'var(--bg-elevated)';
            btn.style.color = 'var(--text-primary)';
            btn.style.borderColor = 'var(--border)';
        } else {
            row.style.display = window.innerWidth <= 768 ? 'block' : 'table-row';
            btn.innerHTML = 'Close <i class="fa-solid fa-chevron-up"></i>';
            btn.style.background = 'var(--teal-dim)';
            btn.style.color = 'var(--teal)';
            btn.style.borderColor = 'var(--teal)';
        }
    }

    function recalc(id) {
        const feed = parseFloat(document.getElementById(`feed-${id}`).value) || 0;
        const fcr = parseFloat(document.getElementById(`fcr-${id}`).value) || 0;
        const gain = feed * fcr; 
        
        const row = document.getElementById(`details-${id}`);
        const birth = parseFloat(row.querySelector('input[readonly]').value) || 0; 
        
        const est = birth + gain;

        document.getElementById(`gain-${id}`).value = gain.toFixed(2);
        document.getElementById(`est-${id}`).value = est.toFixed(2);
    }

    function updateAnimalLog(e, id) {
        e.preventDefault();
        
        const btn = e.target.querySelector('.btn-update');
        const ogText = btn.innerHTML;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';
        btn.disabled = true;

        const fd = new FormData(e.target);
        fd.append('action', 'save_single_log');

        fetch('../process/processFcrConfig.php', { method:'POST', body:fd })
            .then(r=>r.json()).then(d => {
                showToast(d.message, d.success ? 'success' : 'error');
                if(d.success) {
                    setTimeout(() => loadAnimals(), 1000); 
                } else {
                    btn.innerHTML = ogText;
                    btn.disabled = false;
                }
            }).catch(()=>{
                showToast("System connection error.", "error");
                btn.innerHTML = ogText;
                btn.disabled = false;
            });
    }

    loadConfigs();
</script>
</body>
</html>