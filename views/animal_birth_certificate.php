<?php
// views/animal_birth_certificate.php
$page = "farm";
include '../config/Connection.php';

include '../security/checkAccess.php';
checkAccess('birth_certificate');
include '../common/navbar.php';


// --- 1. INITIALIZE DATA ---
$locations = $conn->query("SELECT * FROM locations ORDER BY LOCATION_NAME")->fetchAll(PDO::FETCH_ASSOC);

// Get Selected IDs from URL (if any)
$selected_loc = $_GET['loc_id'] ?? '';
$selected_bld = $_GET['bld_id'] ?? '';
$selected_pen = $_GET['pen_id'] ?? '';

$animals = [];
$pen_name = "Select a Pen";

// --- 2. FETCH ANIMALS (Only if Pen is selected) ---
if ($selected_pen) {
    // Get Pen Name for Display
    $stmtPen = $conn->prepare("SELECT PEN_NAME FROM pens WHERE PEN_ID = ?");
    $stmtPen->execute([$selected_pen]);
    $pen_name = $stmtPen->fetchColumn();

    // Fetch Animals in this Pen
    $sql = "SELECT 
                a.ANIMAL_ID, a.TAG_NO, a.SEX, a.BIRTH_DATE, 
                b.BREED_NAME, t.ANIMAL_TYPE_NAME,
                m.TAG_NO as MOTHER_TAG, f.TAG_NO as FATHER_TAG
            FROM animal_records a
            LEFT JOIN breeds b ON a.BREED_ID = b.BREED_ID
            LEFT JOIN animal_type t ON a.ANIMAL_TYPE_ID = t.ANIMAL_TYPE_ID
            LEFT JOIN animal_records m ON a.MOTHER_ID = m.ANIMAL_ID
            LEFT JOIN animal_records f ON a.FATHER_ID = f.ANIMAL_ID
            WHERE a.IS_ACTIVE = 1 
            AND a.PEN_ID = ? 
            ORDER BY a.TAG_NO ASC";

    $stmt = $conn->prepare($sql);
    $stmt->execute([$selected_pen]);
    $animals = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Birth Certificates | FarmPro</title>
    
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
            --border-active:  rgba(14,165,233,0.5); /* Sky Blue Accent */
            
            --sky:            #0ea5e9;
            --sky-dim:        rgba(14,165,233,0.12);
            --sky-glow:       rgba(14,165,233,0.25);
            --emerald:        #10b981;
            --pink:           #ec4899;
            --blue:           #3b82f6;
            --amber:          #f59e0b;
            --red:            #f87171;
            
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
            background-image: radial-gradient(ellipse 80% 50% at 50% -20%, rgba(14,165,233,0.06) 0%, transparent 60%);
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
            color: var(--sky); background: var(--sky-dim); border: 1px solid rgba(14,165,233,0.2);
            padding: 6px 12px; border-radius: 99px;
        }

        /* ─── HEADER ─── */
        .page-header { margin-bottom: 2.5rem; }
        .page-header h1 { font-size: clamp(1.8rem, 4vw, 2.5rem); font-weight: 700; margin: 0 0 0.5rem 0; color: #fff; letter-spacing: -0.02em;}
        .page-header h1 span { background: linear-gradient(135deg, var(--sky), #0284c7); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .page-header p { color: var(--text-secondary); font-size: 0.95rem; margin: 0; }

        /* ─── FILTERS ─── */
        .filter-card {
            background: var(--bg-surface); border: 1px solid var(--border);
            border-radius: var(--radius-xl); padding: 1.5rem; margin-bottom: 2rem;
            display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.25rem; align-items: flex-end;
            box-shadow: var(--shadow-md);
        }
        .form-group { display: flex; flex-direction: column; gap: 6px; }
        .form-group label { color: var(--text-secondary); font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; }
        
        .form-select {
            width: 100%; padding: 12px 14px; background: var(--bg-elevated); border: 1px solid var(--border);
            border-radius: var(--radius-md); color: var(--text-primary); font-size: 0.95rem; font-family: var(--font);
            outline: none; transition: all var(--transition); box-sizing: border-box;
            appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2394a3b8' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
            background-repeat: no-repeat; background-position: right 12px center; cursor: pointer;
        }
        .form-select:focus { border-color: var(--sky); box-shadow: 0 0 0 3px var(--sky-glow); background: var(--bg-hover); }
        .form-select:disabled { opacity: 0.5; cursor: not-allowed; background: rgba(255,255,255,0.02); }

        .btn-primary {
            background: var(--sky); color: #000; border: none; padding: 12px 24px; 
            border-radius: var(--radius-md); cursor: pointer; font-weight: 700; font-family: var(--font);
            transition: var(--transition); font-size: 0.95rem; white-space: nowrap; display: inline-flex; align-items: center; justify-content: center; gap: 8px; width: 100%; height: 46px;
        }
        .btn-primary:hover { background: #38bdf8; box-shadow: 0 4px 15px var(--sky-glow); transform: translateY(-1px); }

        /* ─── ANIMAL CARDS GRID ─── */
        .section-title { color: var(--sky); font-size: 1.25rem; font-weight: 700; margin: 0 0 1.5rem 0; display: flex; align-items: center; gap: 10px;}
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 1.5rem; }
        
        .animal-card { 
            background: var(--bg-surface); border: 1px solid var(--border); border-radius: var(--radius-xl); 
            padding: 1.5rem; transition: var(--transition); display: flex; flex-direction: column;
            box-shadow: var(--shadow-md); position: relative; overflow: hidden;
        }
        .animal-card::before {
            content: ''; position: absolute; top: 0; left: 0; width: 4px; height: 100%;
            background: linear-gradient(180deg, var(--sky), #0284c7);
        }
        .animal-card:hover { transform: translateY(-4px); border-color: rgba(14,165,233,0.4); box-shadow: 0 15px 35px -10px rgba(0,0,0,0.5); }
        
        .card-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.25rem; }
        .tag-no { font-size: 1.5rem; font-weight: 700; color: #fff; font-family: var(--font-mono); letter-spacing: -0.02em;}
        
        .sex-badge { padding: 4px 10px; border-radius: 99px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; display: inline-flex; align-items: center; gap: 4px;}
        .sex-M { background: var(--blue-dim); color: var(--blue); border: 1px solid rgba(59,130,246,0.3);}
        .sex-F { background: var(--pink-dim); color: var(--pink); border: 1px solid rgba(236,72,153,0.3);}
        .sex-U { background: rgba(255,255,255,0.05); color: var(--text-muted); border: 1px solid var(--border);}
        
        .info-row { display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 0.9rem; color: var(--text-primary); align-items: center;}
        .info-label { color: var(--text-secondary); font-size: 0.8rem; text-transform: uppercase; font-weight: 600; letter-spacing: 0.05em;}
        .val-mono { font-family: var(--font-mono); font-weight: 600; }
        
        .parent-tag { color: var(--pink); font-weight: 700; font-family: var(--font-mono); }
        .sire-tag { color: var(--blue); font-weight: 700; font-family: var(--font-mono); }

        .btn-print { 
            background: var(--bg-elevated); color: var(--text-primary); text-decoration: none; padding: 12px; 
            border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center; gap: 8px; 
            transition: var(--transition); font-weight: 700; font-size: 0.9rem; border: 1px solid var(--border);
            margin-top: auto;
        }
        .btn-print:hover { background: var(--sky); color: #000; border-color: var(--sky); box-shadow: 0 4px 15px var(--sky-glow);}

        .empty-state { text-align: center; color: var(--text-muted); padding: 4rem; font-style: italic; background: var(--bg-surface); border: 1px dashed var(--border); border-radius: var(--radius-xl); grid-column: 1 / -1;}
        
        /* ─── RESPONSIVE ─── */
        @media (max-width: 768px) {
            .container { padding: 1rem; }
            .filter-card { grid-template-columns: 1fr; gap: 1rem; padding: 1.25rem; }
            .btn-primary { height: auto; }
            .grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<div class="container">
    
    <div class="top-bar">
        <a href="farm_dashboard.php" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back to Farm Dashboard
        </a>
        <span class="page-badge"><i class="fa-solid fa-certificate"></i> Official Records</span>
    </div>

    <header class="page-header">
        <h1>Birth <span>Certificates</span></h1>
        <p>Access and print official registration and pedigree documents for livestock.</p>
    </header>

    <form class="filter-card" method="GET" id="filterForm">
        <div class="form-group">
            <label>1. Location</label>
            <select id="locSelect" name="loc_id" class="form-select" onchange="loadBuildings()">
                <option value="">-- Select Location --</option>
                <?php foreach($locations as $loc): ?>
                    <option value="<?= $loc['LOCATION_ID'] ?>" <?= $selected_loc == $loc['LOCATION_ID'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($loc['LOCATION_NAME']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>2. Building</label>
            <select id="bldSelect" name="bld_id" class="form-select" disabled onchange="loadPens()">
                <option value="">-- Select Location First --</option>
            </select>
        </div>
        <div class="form-group">
            <label>3. Pen</label>
            <select id="penSelect" name="pen_id" class="form-select" disabled>
                <option value="">-- Select Building First --</option>
            </select>
        </div>
        <div class="form-group" style="display: flex; justify-content: flex-end;">
            <button type="button" onclick="applyFilter()" class="btn-primary"><i class="fa-solid fa-magnifying-glass"></i> View Animals</button>
        </div>
    </form>

    <?php if ($selected_pen && empty($animals)): ?>
        <div class="empty-state">
            <i class="fa-solid fa-ghost" style="font-size: 2.5rem; margin-bottom: 1rem; display: block; opacity: 0.5;"></i>
            No active animals found in <strong><?= htmlspecialchars($pen_name) ?></strong>.
        </div>
    <?php elseif (!$selected_pen): ?>
        <div class="empty-state">
            <i class="fa-solid fa-arrow-up" style="font-size: 2.5rem; margin-bottom: 1rem; display: block; opacity: 0.5;"></i>
            Please select a Location, Building, and Pen to view certificates.
        </div>
    <?php else: ?>
        <h3 class="section-title"><i class="fa-solid fa-layer-group"></i> Animals in <?= htmlspecialchars($pen_name) ?> <span style="color:var(--text-muted); font-size:1rem; font-weight:500;">(<?= count($animals) ?>)</span></h3>
        <div class="grid">
         <?php foreach($animals as $a): ?>
            <div class="animal-card">
                <div class="card-header">
                    <div class="tag-no"><?= htmlspecialchars($a['TAG_NO']) ?></div>
                    <?php 
                        // Determine Sex Label and Class
                        $sex = strtoupper($a['SEX']);
                        $sexIcon = '';
                        if ($sex === 'M') {
                            $sexLabel = 'Male';
                            $sexClass = 'sex-M';
                            $sexIcon = '<i class="fa-solid fa-mars"></i> ';
                        } elseif ($sex === 'F') {
                            $sexLabel = 'Female';
                            $sexClass = 'sex-F';
                            $sexIcon = '<i class="fa-solid fa-venus"></i> ';
                        } else {
                            $sexLabel = 'Unknown';
                            $sexClass = 'sex-U';
                            $sexIcon = '<i class="fa-solid fa-circle-question"></i> ';
                        }
                    ?>
                    <span class="sex-badge <?= $sexClass ?>"><?= $sexIcon . $sexLabel ?></span>
                </div>
                
                <div class="info-row">
                    <span class="info-label">Type / Breed:</span>
                    <span><?= htmlspecialchars($a['ANIMAL_TYPE_NAME'] . ' / ' . $a['BREED_NAME']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Birth Date:</span>
                    <span class="val-mono"><?= date('M d, Y', strtotime($a['BIRTH_DATE'])) ?></span>
                </div>
                
                <div style="border-top: 1px dashed var(--border); margin: 10px 0; padding-top: 10px;"></div>
                
                <div class="info-row">
                    <span class="info-label">Dam (Mother):</span>
                    <span class="parent-tag"><?= $a['MOTHER_TAG'] ?: 'N/A' ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Sire (Father):</span>
                    <span class="sire-tag"><?= $a['FATHER_TAG'] ?: 'N/A' ?></span>
                </div>

                <div style="margin-top: 1.5rem; padding-top: 1.25rem; border-top: 1px solid var(--border); display:flex; flex-direction:column; flex-grow:1;">
                    <a href="print_certificate.php?id=<?= $a['ANIMAL_ID'] ?>" target="_blank" class="btn-print">
                        <i class="fa-solid fa-print"></i> Generate Certificate
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
    // Pre-load values for PHP persistence
    const preLoc = "<?= $selected_loc ?>";
    const preBld = "<?= $selected_bld ?>";
    const prePen = "<?= $selected_pen ?>";

    // --- 1. LOAD BUILDINGS ---
    function loadBuildings() {
        const locId = document.getElementById('locSelect').value;
        const bldSelect = document.getElementById('bldSelect');
        const penSelect = document.getElementById('penSelect');

        // Reset
        bldSelect.innerHTML = '<option value="">Loading...</option>';
        bldSelect.disabled = true;
        penSelect.innerHTML = '<option value="">-- Select Building First --</option>';
        penSelect.disabled = true;

        if (!locId) {
            bldSelect.innerHTML = '<option value="">-- Select Location First --</option>';
            return;
        }

        // Fetch using existing API
        fetch(`../process/getCostData.php?action=get_buildings&loc_id=${locId}`)
            .then(res => res.json())
            .then(data => {
                bldSelect.innerHTML = '<option value="">-- Select Building --</option>';
                data.forEach(item => {
                    const isSel = (item.BUILDING_ID == preBld) ? 'selected' : '';
                    bldSelect.innerHTML += `<option value="${item.BUILDING_ID}" ${isSel}>${item.BUILDING_NAME}</option>`;
                });
                bldSelect.disabled = false;
                
                // If checking for pre-selected value, trigger next load
                if(preBld && bldSelect.value == preBld) loadPens();
            });
    }

    // --- 2. LOAD PENS ---
    function loadPens() {
        const bldId = document.getElementById('bldSelect').value;
        const penSelect = document.getElementById('penSelect');

        penSelect.innerHTML = '<option value="">Loading...</option>';
        penSelect.disabled = true;

        if (!bldId) {
            penSelect.innerHTML = '<option value="">-- Select Building First --</option>';
            return;
        }

        fetch(`../process/getCostData.php?action=get_pens&bld_id=${bldId}`)
            .then(res => res.json())
            .then(data => {
                penSelect.innerHTML = '<option value="">-- Select Pen --</option>';
                data.forEach(item => {
                    const isSel = (item.PEN_ID == prePen) ? 'selected' : '';
                    penSelect.innerHTML += `<option value="${item.PEN_ID}" ${isSel}>${item.PEN_NAME}</option>`;
                });
                penSelect.disabled = false;
            });
    }

    // --- 3. APPLY FILTER (Reload Page) ---
    function applyFilter() {
        const loc = document.getElementById('locSelect').value;
        const bld = document.getElementById('bldSelect').value;
        const pen = document.getElementById('penSelect').value;

        if(!pen) {
            alert("Please complete the selection to view animals.");
            return;
        }

        // Reload with GET parameters
        window.location.href = `animal_birth_certificate.php?loc_id=${loc}&bld_id=${bld}&pen_id=${pen}`;
    }

    // Initialize if values exist (Back button support)
    document.addEventListener('DOMContentLoaded', () => {
        if(preLoc) loadBuildings();
    });
</script>

</body>
</html>