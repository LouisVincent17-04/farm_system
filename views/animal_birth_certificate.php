<?php
// views/animal_birth_certificate.php
$page = "farm";
include '../common/navbar.php';
include '../config/Connection.php';
include '../security/checkRole.php';
checkRole(2);

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
    <title>Birth Certificates - FarmPro</title>
    <style>
        body { background: #0f172a; color: #e2e8f0; font-family: sans-serif; }
        .container { max-width: 1200px; margin: 0 auto; padding: 2rem; }
        
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; }
        .title { font-size: 2rem; font-weight: 800; color: #0ea5e9; margin: 0; display:flex; align-items:center; gap:10px; }
        
        /* Filter Bar Styles */
        .filter-bar { 
            background: rgba(30, 41, 59, 0.6); 
            padding: 1.5rem; 
            border-radius: 12px; 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); 
            gap: 1rem; 
            margin-bottom: 2rem; 
            border: 1px solid #334155; 
            align-items: end;
        }
        .filter-group label { display: block; color: #94a3b8; font-size: 0.85rem; margin-bottom: 5px; font-weight: bold; }
        .form-select { 
            width: 100%; padding: 12px; background: #1e293b; border: 1px solid #475569; 
            color: white; border-radius: 6px; font-size: 1rem; cursor: pointer;
        }
        .form-select:disabled { opacity: 0.5; cursor: not-allowed; }
        .btn-primary { 
            background: #0ea5e9; color: white; padding: 12px; border: none; border-radius: 6px; 
            cursor: pointer; font-weight: bold; width: 100%; transition: background 0.2s;
        }
        .btn-primary:hover { background: #0284c7; }

        /* Grid Layout for Cards */
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1.5rem; }
        
        .animal-card { 
            background: #1e293b; border: 1px solid #334155; border-radius: 12px; padding: 1.5rem; 
            transition: transform 0.2s; display: flex; flex-direction: column;
        }
        .animal-card:hover { transform: translateY(-5px); border-color: #0ea5e9; }
        
        .card-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem; }
        .tag-no { font-size: 1.5rem; font-weight: bold; color: #fff; }
        .sex-badge { padding: 4px 10px; border-radius: 20px; font-size: 0.8rem; font-weight: bold; }
        .sex-M { background: rgba(96, 165, 250, 0.2); color: #60a5fa; }
        .sex-F { background: rgba(244, 114, 182, 0.2); color: #f472b6; }

        .info-row { display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 0.9rem; color: #cbd5e1; }
        .info-label { color: #94a3b8; }

        .btn-print { 
            background: #334155; color: #e2e8f0; text-decoration: none; padding: 10px; 
            border-radius: 6px; display: flex; align-items: center; justify-content: center; gap: 8px; 
            transition: all 0.2s; font-weight: 600;
        }
        .btn-print:hover { background: #0ea5e9; color: white; }

        .empty-state { text-align: center; color: #64748b; padding: 3rem; font-size: 1.2rem; }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <h1 class="title">📜 Birth Certificates</h1>
        <a href="farm_dashboard.php" style="color:#94a3b8; text-decoration:none;">&larr; Dashboard</a>
    </div>

    <div class="filter-bar">
        <div class="filter-group">
            <label>1. Location</label>
            <select id="locSelect" class="form-select" onchange="loadBuildings()">
                <option value="">-- Select Location --</option>
                <?php foreach($locations as $loc): ?>
                    <option value="<?= $loc['LOCATION_ID'] ?>" <?= $selected_loc == $loc['LOCATION_ID'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($loc['LOCATION_NAME']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-group">
            <label>2. Building</label>
            <select id="bldSelect" class="form-select" disabled onchange="loadPens()">
                <option value="">-- Select Location First --</option>
            </select>
        </div>
        <div class="filter-group">
            <label>3. Pen</label>
            <select id="penSelect" class="form-select" disabled>
                <option value="">-- Select Building First --</option>
            </select>
        </div>
        <div class="filter-group">
            <button onclick="applyFilter()" class="btn-primary">View Animals</button>
        </div>
    </div>

    <?php if ($selected_pen && empty($animals)): ?>
        <div class="empty-state">No active animals found in <strong><?= htmlspecialchars($pen_name) ?></strong>.</div>
    <?php elseif (!$selected_pen): ?>
        <div class="empty-state">Please select a Pen to view certificates.</div>
    <?php else: ?>
        <h3 style="color:#0ea5e9; margin-bottom:1rem;">Animals in <?= htmlspecialchars($pen_name) ?> (<?= count($animals) ?>)</h3>
        <div class="grid">
            <?php foreach($animals as $a): ?>
                <div class="animal-card">
                    <div class="card-header">
                        <div class="tag-no"><?= htmlspecialchars($a['TAG_NO']) ?></div>
                        <span class="sex-badge sex-<?= $a['SEX'] ?>"><?= $a['SEX'] === 'M' ? 'Male' : 'Female' ?></span>
                    </div>
                    
                    <div class="info-row">
                        <span class="info-label">Type/Breed:</span>
                        <span><?= htmlspecialchars($a['ANIMAL_TYPE_NAME'] . ' / ' . $a['BREED_NAME']) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Birth Date:</span>
                        <span><?= date('M d, Y', strtotime($a['BIRTH_DATE'])) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Dam (Mother):</span>
                        <span style="color: #f472b6; font-weight:bold;"><?= $a['MOTHER_TAG'] ?: 'N/A' ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Sire (Father):</span>
                        <span style="color: #60a5fa; font-weight:bold;"><?= $a['FATHER_TAG'] ?: 'N/A' ?></span>
                    </div>

                    <div style="margin-top: auto; padding-top: 1rem; border-top: 1px solid #334155;">
                        <a href="print_certificate.php?id=<?= $a['ANIMAL_ID'] ?>" target="_blank" class="btn-print">
                            🖨️ Print Certificate
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
            alert("Please select a Pen.");
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