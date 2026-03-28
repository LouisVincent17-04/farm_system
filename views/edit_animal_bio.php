<?php
// views/edit_animal_bio.php
ob_start();
$page = "farm";
include '../config/Connection.php';
include '../security/checkAccess.php';
checkAccess('edit_bio_info');
include '../security/checkRole.php';    
include '../common/navbar.php';

include '../common/chat_support.php';

// --- AJAX HANDLER ---
if (isset($_GET['action'])) {
    ob_clean(); 
    header('Content-Type: application/json');
    $action = $_GET['action'];

    try {
        if ($action === 'get_buildings') {
            $stmt = $conn->prepare("SELECT BUILDING_ID, BUILDING_NAME FROM buildings WHERE LOCATION_ID = ? ORDER BY BUILDING_NAME");
            $stmt->execute([$_GET['loc_id']]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC)); exit;
        }
        if ($action === 'get_pens') {
            $stmt = $conn->prepare("SELECT PEN_ID, PEN_NAME FROM pens WHERE BUILDING_ID = ? ORDER BY PEN_NAME");
            $stmt->execute([$_GET['bld_id']]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC)); exit;
        }
        if ($action === 'get_animals_editable') {
            $animalsStmt = $conn->prepare("
                SELECT a.ANIMAL_ID, a.TAG_NO, a.SEX, a.BIRTH_DATE, a.BREED_ID, a.ANIMAL_TYPE_ID, a.CLASS_ID,
                       at.ANIMAL_TYPE_NAME, b.BREED_NAME, a.CURRENT_ACTUAL_WEIGHT, a.ACQUISITION_COST,
                       ac.STAGE_NAME as CLASSIFICATION
                FROM animal_records a
                LEFT JOIN animal_type at ON a.ANIMAL_TYPE_ID = at.ANIMAL_TYPE_ID
                LEFT JOIN breeds b ON a.BREED_ID = b.BREED_ID
                LEFT JOIN animal_classifications ac ON a.CLASS_ID = ac.CLASS_ID
                WHERE a.PEN_ID = ? AND a.IS_ACTIVE = 1
                ORDER BY a.TAG_NO
            ");
            $animalsStmt->execute([$_GET['pen_id']]);
            $animals = $animalsStmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['animals' => $animals]); 
            exit;
        }
    } catch (Exception $e) { 
        echo json_encode(['error' => $e->getMessage()]); 
        exit; 
    }
}

// PRE-FETCH FOR DROPDOWNS
$locations = $conn->query("SELECT * FROM locations ORDER BY LOCATION_NAME")->fetchAll(PDO::FETCH_ASSOC);
$types = $conn->query("SELECT * FROM animal_type ORDER BY ANIMAL_TYPE_NAME")->fetchAll(PDO::FETCH_ASSOC);
$allBreeds = $conn->query("SELECT BREED_ID, BREED_NAME, ANIMAL_TYPE_ID FROM breeds ORDER BY BREED_NAME")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Bulk Edit Bio Info | FarmPro</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" type="text/css" href="https://npmcdn.com/flatpickr/dist/themes/dark.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />

    <style>
        /* ─── CSS VARIABLES ─── */
        :root {
            --bg-base:        #080f1a;
            --bg-surface:     #0d1829;
            --bg-elevated:    #111f35;
            --bg-hover:       #162540;
            --border:         rgba(255,255,255,0.07);
            --border-active:  rgba(250,204,21,0.5); 
            --gold:           #facc15;
            --gold-dim:       rgba(250,204,21,0.12);
            --gold-glow:      rgba(250,204,21,0.25);
            --blue:           #38bdf8;
            --blue-dim:       rgba(56,189,248,0.12);
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
            --transition:     0.18s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* ─── RESET & BASE ─── */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: var(--font);
            background: var(--bg-base);
            color: var(--text-primary);
            min-height: 100vh;
            padding-bottom: 120px;
            background-image: radial-gradient(ellipse 80% 50% at 50% -20%, rgba(250,204,21,0.05) 0%, transparent 60%);
        }
        .container { max-width: 1560px; margin: 0 auto; padding: 2rem 1.5rem; }

        /* ─── TOP BAR ─── */
        .top-bar { display: flex; align-items: center; justify-content: space-between; margin-bottom: 2rem; }
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
            color: var(--gold); background: var(--gold-dim); border: 1px solid rgba(250,204,21,0.2);
            padding: 6px 12px; border-radius: 99px;
        }

        /* ─── HEADER ─── */
        .page-header { margin-bottom: 2rem; }
        .page-title {
            font-size: clamp(1.6rem, 3vw, 2.2rem); font-weight: 700;
            color: var(--text-primary); letter-spacing: -0.03em; line-height: 1.1; margin-bottom: 0.25rem;
        }
        .page-title span {
            background: linear-gradient(135deg, var(--gold), #eab308);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }
        .page-subtitle { color: var(--text-secondary); font-size: 0.95rem; }

        /* ─── FILTER CARD ─── */
        .filter-card {
            background: var(--bg-surface); border: 1px solid var(--border);
            border-radius: var(--radius-xl); padding: 1.5rem; margin-bottom: 2rem;
            box-shadow: 0 10px 30px -10px rgba(0,0,0,0.5);
        }
        .filter-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.25rem; }
        .form-group { display: flex; flex-direction: column; gap: 6px; }
        .form-label { font-size: 0.72rem; font-weight: 600; text-transform: uppercase; color: var(--text-secondary); letter-spacing: 0.05em; }
        
        .form-control, .form-select {
            width: 100%; padding: 0 12px; height: 42px; background: var(--bg-elevated);
            border: 1px solid var(--border); color: var(--text-primary);
            border-radius: var(--radius-md); font-size: 0.9rem; font-family: var(--font);
            outline: none; transition: all var(--transition);
        }
        .form-control:focus, .form-select:focus { border-color: var(--gold); box-shadow: 0 0 0 3px var(--gold-glow); }
        .form-select { appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/xml' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2394a3b8' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 12px center; cursor: pointer; }

        /* ─── STATS BAR ─── */
        .stats-bar { display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
        .stat-card {
            background: var(--gold-dim); border: 1px solid rgba(250,204,21,0.2);
            border-radius: 12px; padding: 1rem; text-align: center;
        }
        .stat-value { font-family: var(--font-mono); font-size: 1.5rem; font-weight: 700; color: var(--gold); }
        .stat-label { font-size: 0.65rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700; margin-top: 2px; }

        /* ─── TABLE ─── */
        .table-card {
            background: var(--bg-surface); border: 1px solid var(--border);
            border-radius: var(--radius-xl); overflow: hidden;
        }
        .table-wrap { overflow-x: auto; }
        .table { width: 100%; border-collapse: collapse; min-width: 1000px; }
        .table thead th {
            background: var(--bg-elevated); color: var(--text-muted);
            font-size: 0.7rem; font-weight: 700; text-transform: uppercase;
            letter-spacing: 0.07em; padding: 14px 16px; text-align: left;
            border-bottom: 1px solid var(--border);
        }
        .table td { padding: 12px 16px; border-bottom: 1px solid rgba(255,255,255,0.03); vertical-align: middle; }
        
        .tbl-input {
            width: 100%; padding: 8px 12px; background: rgba(0,0,0,0.2);
            border: 1px solid var(--border); color: #fff; border-radius: 6px;
            font-size: 0.85rem; font-family: var(--font); outline: none; transition: 0.2s;
        }
        .tbl-input:focus { border-color: var(--gold); background: var(--bg-elevated); }
        .tbl-input.readonly { opacity: 0.5; cursor: default; border-color: transparent; background: transparent; font-family: var(--font-mono); color: var(--text-secondary); }

        /* ─── SAVE BAR ─── */
        .save-bar {
            position: fixed; bottom: 0; left: 0; width: 100%;
            background: rgba(13, 24, 41, 0.95); backdrop-filter: blur(10px);
            border-top: 1px solid var(--gold); padding: 1.25rem 2rem;
            display: flex; justify-content: space-between; align-items: center;
            z-index: 100; transform: translateY(100%); transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 -10px 40px rgba(0,0,0,0.5);
        }
        .save-bar.visible { transform: translateY(0); }
        .save-bar-info { display: flex; align-items: center; gap: 2rem; }
        .info-item { display: flex; flex-direction: column; }
        .info-label { font-size: 0.65rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700; }
        .info-value { font-family: var(--font-mono); font-size: 1.5rem; font-weight: 700; color: var(--gold); }
        
        .save-bar-actions { display: flex; gap: 12px; align-items: center; }

        /* Commit Button */
        .btn-save-all { 
            background: var(--gold); color: #000; border: none; 
            padding: 12px 32px; border-radius: var(--radius-md); 
            font-weight: 700; cursor: pointer; transition: 0.2s; 
        }
        .btn-save-all:hover { background: #eab308; box-shadow: 0 0 20px var(--gold-glow); transform: translateY(-2px); }

        /* Refined Discard Button */
        .btn-discard { 
            background: rgba(248, 113, 113, 0.05); 
            color: #f87171; 
            border: 1px solid rgba(248, 113, 113, 0.2);
            padding: 12px 24px;
            border-radius: var(--radius-md);
            font-weight: 600;
            cursor: pointer;
            transition: all var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-discard:hover { 
            background: var(--red); 
            color: #fff; 
            border-color: var(--red);
            box-shadow: 0 0 15px rgba(239, 68, 68, 0.3);
            transform: translateY(-1px);
        }

        /* ─── ALERTS ─── */
        .alert { padding: 12px 16px; border-radius: var(--radius-md); margin-bottom: 1.5rem; display: none; font-size: 0.9rem; font-weight: 600; text-align: center; }
        .alert.success { background: var(--green-dim); border: 1px solid rgba(34,197,94,0.3); color: var(--green); }
        .alert.error { background: var(--red-dim); border: 1px solid rgba(248,113,113,0.3); color: var(--red); }

        .loading-text { text-align: center; padding: 4rem 2rem; color: var(--text-muted); }

        @media (max-width: 768px) {
            .save-bar { flex-direction: column; gap: 15px; padding: 1.5rem; }
            .save-bar-info { justify-content: space-around; width: 100%; }
            .save-bar-actions { width: 100%; }
            .save-bar-actions .btn-discard, .save-bar-actions .btn-save-all { flex: 1; justify-content: center; }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="top-bar">
        <a href="farm_dashboard.php" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Farm Dashboard
        </a>
        <span class="page-badge"><i class="fa-solid fa-dna"></i> Biology Update</span>
    </div>

    <div class="page-header">
        <div class="header-content">
            <h1>Bulk Edit <span>Bio Info</span></h1>
            <p>Unified management for livestock tag numbers, classifications, and genetics.</p>
        </div>
    </div>

    <div id="alertBox" class="alert"></div>

    <div class="filter-card">
        <div class="filter-grid">
            <div class="form-group">
                <label class="form-label">1. Primary Location</label>
                <select id="filter_location" class="form-select" onchange="loadBuildings()">
                    <option value="">-- Choose Location --</option>
                    <?php foreach($locations as $l): ?>
                        <option value="<?= $l['LOCATION_ID'] ?>"><?= htmlspecialchars($l['LOCATION_NAME']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">2. Structural Building</label>
                <select id="filter_building" class="form-select" onchange="loadPens()" disabled>
                    <option value="">-- Choose Building --</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">3. Specific Pen</label>
                <select id="filter_pen" class="form-select" onchange="loadAnimals()" disabled>
                    <option value="">-- Choose Pen --</option>
                </select>
            </div>
        </div>
    </div>

    <div class="stats-bar" id="statsBar" style="display: none;">
        <div class="stat-card">
            <div class="stat-value" id="totalAnimals">0</div>
            <div class="stat-label">Total Stock</div>
        </div>
        <div class="stat-card">
            <div class="stat-value" id="maleCount" style="color:var(--blue);">0</div>
            <div class="stat-label">Males (Boars)</div>
        </div>
        <div class="stat-card">
            <div class="stat-value" id="femaleCount" style="color:var(--pink);">0</div>
            <div class="stat-label">Females (Sows)</div>
        </div>
    </div>

    <form id="bulkEditForm" onsubmit="submitBulkEdit(event)">
        <div class="table-card">
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width: 15%;">Tag Number</th>
                            <th style="width: 15%;">Life Stage</th> 
                            <th style="width: 15%;">Animal Type</th>
                            <th style="width: 20%;">Breed Profile</th>
                            <th style="width: 10%;">Sex</th>
                            <th style="width: 18%;">Birth Date</th>
                        </tr>
                    </thead>
                    <tbody id="animalTableBody">
                        <tr>
                            <td colspan="6" class="loading-text">
                                <i class="fa-solid fa-filter me-2"></i>
                                Select a pen above to populate animal records
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="save-bar" id="saveBar">
            <div class="save-bar-info">
                <div class="info-item">
                    <span class="info-label">Active Queue</span>
                    <span class="info-value" id="changeCount">0</span>
                </div>
            </div>
            <div class="save-bar-actions">
                <button type="button" class="btn-discard" onclick="resetForm()">
                    <i class="fa-solid fa-trash-can"></i> Discard Changes
                </button>
                <button type="submit" class="btn-save-all">
                    <i class="fa-solid fa-floppy-disk me-2"></i> Commit All Changes
                </button>
            </div>
        </div>
    </form>
</div>

<script>
    const ALL_TYPES = <?= json_encode($types) ?>;
    const ALL_BREEDS = <?= json_encode($allBreeds) ?>;
    const TODAY = "<?= date('Y-m-d') ?>";

    function showAlert(message, type) {
        const alert = document.getElementById('alertBox');
        alert.textContent = message;
        alert.className = `alert ${type}`;
        alert.style.display = 'block';
        window.scrollTo({ top: 0, behavior: 'smooth' });
        setTimeout(() => alert.style.display = 'none', 5000);
    }

    async function fetchData(params) {
        const res = await fetch(`?${params}`);
        return await res.json();
    }

    async function loadBuildings() {
        const id = document.getElementById('filter_location').value;
        const target = document.getElementById('filter_building');
        const penSelect = document.getElementById('filter_pen');
        
        target.innerHTML = '<option value="">Searching...</option>';
        target.disabled = true;
        penSelect.innerHTML = '<option value="">-- Choose Pen --</option>';
        penSelect.disabled = true;
        
        document.getElementById('animalTableBody').innerHTML = '<tr><td colspan="6" class="loading-text">Select a pen to load animals</td></tr>';
        document.getElementById('saveBar').classList.remove('visible');
        document.getElementById('statsBar').style.display = 'none';
        
        if (id) {
            const data = await fetchData(`action=get_buildings&loc_id=${id}`);
            target.innerHTML = '<option value="">-- Choose Building --</option>';
            data.forEach(i => target.innerHTML += `<option value="${i.BUILDING_ID}">${i.BUILDING_NAME}</option>`);
            target.disabled = false;
        }
    }

    async function loadPens() {
        const id = document.getElementById('filter_building').value;
        const target = document.getElementById('filter_pen');
        target.innerHTML = '<option value="">Searching...</option>';
        target.disabled = true;
        if (id) {
            const data = await fetchData(`action=get_pens&bld_id=${id}`);
            target.innerHTML = '<option value="">-- Choose Pen --</option>';
            data.forEach(i => target.innerHTML += `<option value="${i.PEN_ID}">${i.PEN_NAME}</option>`);
            target.disabled = false;
        }
    }

    async function loadAnimals() {
        const id = document.getElementById('filter_pen').value;
        const tbody = document.getElementById('animalTableBody');
        const saveBar = document.getElementById('saveBar');
        
        tbody.innerHTML = '<tr><td colspan="6" class="loading-text"><i class="fa-solid fa-spinner fa-spin me-2"></i>Fetching records...</td></tr>';
        saveBar.classList.remove('visible');

        if (id) {
            try {
                const res = await fetchData(`action=get_animals_editable&pen_id=${id}`);
                const animals = res.animals || [];
                tbody.innerHTML = '';

                if (animals.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="6" class="loading-text">No active records found in this pen.</td></tr>';
                    return;
                }

                let males = 0, females = 0;

                animals.forEach(a => {
                    if(a.SEX === 'M') males++;
                    if(a.SEX === 'F') females++;

                    const row = document.createElement('tr');
                    const weightVal = a.CURRENT_ACTUAL_WEIGHT || 0;
                    const costVal = a.ACQUISITION_COST || 0;

                    let typeOpts = '<option value="">-- Select --</option>';
                    ALL_TYPES.forEach(t => {
                        const sel = t.ANIMAL_TYPE_ID == a.ANIMAL_TYPE_ID ? 'selected' : '';
                        typeOpts += `<option value="${t.ANIMAL_TYPE_ID}" ${sel}>${t.ANIMAL_TYPE_NAME}</option>`;
                    });

                    row.innerHTML = `
                        <td data-label="Tag No">
                            <input type="text" readonly class="tbl-input readonly" name="animals[${a.ANIMAL_ID}][tag]" value="${a.TAG_NO}" required>
                            <input type="hidden" name="animals[${a.ANIMAL_ID}][CURRENT_ACTUAL_WEIGHT]" value="${weightVal}">
                            <input type="hidden" name="animals[${a.ANIMAL_ID}][acquisition_cost]" value="${costVal}">
                        </td>
                        <td data-label="Classification">
                            <input type="text" readonly class="tbl-input readonly" value="${a.CLASSIFICATION || 'Unclassified'}">
                        </td>
                        <td data-label="Type">
                            <select class="tbl-input" name="animals[${a.ANIMAL_ID}][type]" onchange="updateRowBreeds(this, ${a.ANIMAL_ID})" required>
                                ${typeOpts}
                            </select>
                        </td>
                        <td data-label="Breed">
                            <select class="tbl-input" id="breed_select_${a.ANIMAL_ID}" name="animals[${a.ANIMAL_ID}][breed]" required>
                                ${getBreedOptions(a.ANIMAL_TYPE_ID, a.BREED_ID)}
                            </select>
                        </td>
                        <td data-label="Sex">
                            <select class="tbl-input" name="animals[${a.ANIMAL_ID}][sex]">
                                <option value="M" ${a.SEX === 'M' ? 'selected' : ''}>Male</option>
                                <option value="F" ${a.SEX === 'F' ? 'selected' : ''}>Female</option>
                            </select>
                        </td>
                        <td data-label="Birth Date">
                            <input type="date" class="tbl-input date-picker" name="animals[${a.ANIMAL_ID}][dob]" value="${a.BIRTH_DATE}" required>
                        </td>
                    `;
                    tbody.appendChild(row);
                });

                flatpickr(".date-picker", {
                    dateFormat: "Y-m-d",
                    altInput: true,
                    altFormat: "m/d/Y",
                    allowInput: true,
                    maxDate: TODAY,
                    theme: "dark"
                });

                document.getElementById('totalAnimals').innerText = animals.length;
                document.getElementById('changeCount').innerText = animals.length;
                document.getElementById('maleCount').innerText = males;
                document.getElementById('femaleCount').innerText = females;
                
                document.getElementById('statsBar').style.display = 'grid';
                saveBar.classList.add('visible');

            } catch (e) {
                tbody.innerHTML = '<tr><td colspan="6" class="loading-text" style="color: var(--red);">Critical fetch error.</td></tr>';
            }
        }
    }

    function getBreedOptions(typeId, selectedBreedId) {
        const filtered = ALL_BREEDS.filter(b => b.ANIMAL_TYPE_ID == typeId);
        let opts = '<option value="">-- Select Breed --</option>';
        filtered.forEach(b => {
            const sel = b.BREED_ID == selectedBreedId ? 'selected' : '';
            opts += `<option value="${b.BREED_ID}" ${sel}>${b.BREED_NAME}</option>`;
        });
        return opts;
    }

    function updateRowBreeds(selectElem, animalId) {
        document.getElementById(`breed_select_${animalId}`).innerHTML = getBreedOptions(selectElem.value, null);
    }

    function resetForm() { if (confirm("Discard all unsaved changes and reload original data?")) loadAnimals(); }

    async function submitBulkEdit(e) {
        e.preventDefault();
        if (!confirm("💾 Confirm batch update for these records?")) return;

        const btn = document.querySelector('.btn-save-all');
        const originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i>Committing...';

        const formData = new FormData(document.getElementById('bulkEditForm'));

        try {
            const res = await fetch('../process/updateAnimalBio.php', { method: 'POST', body: formData });
            const result = await res.json();
            if (result.success) {
                showAlert("Batch update successful!", "success");
                await loadAnimals();
            } else {
                showAlert("Error: " + result.message, "error");
            }
        } catch (err) {
            showAlert("System connection error.", "error");
        } finally {
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    }
</script>
</body>
</html>