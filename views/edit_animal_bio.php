<?php
// views/edit_animal_bio.php
ob_start();
$page = "farm";

include '../config/Connection.php';
include '../security/checkRole.php';    
include '../common/navbar.php';

checkRole(2);

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
                SELECT a.ANIMAL_ID, a.TAG_NO, a.SEX, a.BIRTH_DATE, a.BREED_ID, a.ANIMAL_TYPE_ID,
                       at.ANIMAL_TYPE_NAME, b.BREED_NAME, a.INITIAL_WEIGHT, a.ACQUISITION_COST
                FROM animal_records a
                LEFT JOIN animal_type at ON a.ANIMAL_TYPE_ID = at.ANIMAL_TYPE_ID
                LEFT JOIN breeds b ON a.BREED_ID = b.BREED_ID
                WHERE a.PEN_ID = ? AND a.IS_ACTIVE = 1
                ORDER BY a.TAG_NO
            ");
            $animalsStmt->execute([$_GET['pen_id']]);
            $animals = $animalsStmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['animals' => $animals]); 
            exit;
        }
    } catch (Exception $e) { echo json_encode(['error' => $e->getMessage()]); exit; }
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> <title>Bulk Edit Bio Info</title>
    <link rel="stylesheet" href="../css/purch_housing_facilities.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; 
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); 
            color: #e2e8f0; 
            min-height: 100vh; 
            padding-bottom: 100px;
        }
        .container { max-width: 1600px; margin: 0 auto; padding: 2rem; }
        
        /* Header */
        .page-header { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-bottom: 2rem; 
            padding-bottom: 1.5rem;
            border-bottom: 2px solid rgba(250, 204, 21, 0.2);
            flex-wrap: wrap; /* Allow wrapping on small screens */
            gap: 1rem;
        }
        .header-content h1 { 
            font-size: 2.5rem; 
            font-weight: 800; 
            background: linear-gradient(135deg, #facc15, #eab308);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.5rem;
        }
        .header-content p {
            color: #94a3b8;
            font-size: 0.95rem;
        }
        .back-link { 
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            background: rgba(250, 204, 21, 0.1);
            border: 1px solid rgba(250, 204, 21, 0.3);
            color: #facc15; 
            text-decoration: none; 
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.2s;
        }
        .back-link:hover { 
            background: rgba(250, 204, 21, 0.2);
            transform: translateX(-4px);
        }

        /* Filter Card */
        .filter-card {
            background: rgba(30, 41, 59, 0.7); 
            border: 1px solid rgba(250, 204, 21, 0.2);
            border-radius: 16px; 
            padding: 2rem; 
            margin-bottom: 2rem;
            backdrop-filter: blur(10px);
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
        }
        .filter-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: #facc15;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .filter-grid {
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); 
            gap: 1.5rem;
        }
        .form-group { 
            display: flex; 
            flex-direction: column; 
            gap: 8px; 
        }
        .form-label { 
            font-size: 0.85rem; 
            color: #cbd5e1; 
            font-weight: 600; 
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .form-select {
            width: 100%; 
            padding: 12px; 
            background: #0f172a; 
            border: 1px solid #475569;
            color: white; 
            border-radius: 8px; 
            font-size: 0.95rem; 
            transition: all 0.2s;
            cursor: pointer;
        }
        .form-select:focus { 
            border-color: #facc15; 
            outline: none; 
            box-shadow: 0 0 0 3px rgba(250, 204, 21, 0.1);
        }
        .form-select:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* Stats Bar */
        .stats-bar {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }
        .stat-card {
            flex: 1;
            min-width: 150px;
            background: rgba(250, 204, 21, 0.1);
            border: 1px solid rgba(250, 204, 21, 0.3);
            border-radius: 12px;
            padding: 1rem;
            text-align: center;
        }
        .stat-value {
            font-size: 2rem;
            font-weight: 800;
            color: #facc15;
        }
        .stat-label {
            font-size: 0.85rem;
            color: #94a3b8;
            text-transform: uppercase;
        }

        /* Table */
        .table-container { 
            background: rgba(15, 23, 42, 0.8); 
            border-radius: 16px; 
            overflow: hidden; 
            border: 1px solid rgba(250, 204, 21, 0.1);
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
            
            /* Responsive Scroll */
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        .data-table { 
            width: 100%; 
            border-collapse: collapse; 
            min-width: 800px; /* Ensure table doesn't squish */
        }
        .data-table thead {
            background: linear-gradient(135deg, rgba(250, 204, 21, 0.15), rgba(234, 179, 8, 0.15));
        }
        .data-table th { 
            color: #facc15; 
            padding: 1rem; 
            text-align: left; 
            font-size: 0.85rem; 
            text-transform: uppercase;
            font-weight: 700;
            letter-spacing: 0.5px;
            border-bottom: 2px solid rgba(250, 204, 21, 0.3);
            white-space: nowrap;
        }
        .data-table td { 
            padding: 1rem; 
            border-bottom: 1px solid rgba(255,255,255,0.05); 
            vertical-align: middle; 
        }
        .data-table tbody tr {
            transition: background-color 0.2s;
        }
        .data-table tbody tr:hover {
            background: rgba(250, 204, 21, 0.05);
        }
        
        /* Table Inputs */
        .tbl-input { 
            width: 100%; 
            padding: 10px; 
            background: rgba(15, 23, 42, 0.6); 
            border: 1px solid #475569; 
            color: #fff; 
            border-radius: 6px; 
            font-size: 0.9rem;
            transition: all 0.2s;
        }
        .tbl-input:focus { 
            border-color: #facc15; 
            outline: none; 
            background: #0f172a;
            box-shadow: 0 0 0 3px rgba(250, 204, 21, 0.1);
        }
        .tbl-input:hover:not(:disabled) { 
            background: rgba(255,255,255,0.05); 
            border-color: #64748b;
        }

        /* Save Bar */
        .save-bar {
            position: fixed; 
            bottom: 0; 
            left: 0; 
            width: 100%;
            background: linear-gradient(135deg, #1e293b, #0f172a); 
            border-top: 2px solid #facc15;
            padding: 1.5rem 2rem; 
            display: flex; 
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            box-shadow: 0 -10px 40px rgba(0,0,0,0.5); 
            z-index: 100;
            transform: translateY(100%); 
            transition: transform 0.3s ease;
        }
        .save-bar.visible { 
            transform: translateY(0); 
        }
        
        .save-bar-info {
            display: flex;
            align-items: center;
            gap: 2rem;
        }
        .save-bar-info .info-item {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }
        .info-label {
            font-size: 0.75rem;
            color: #94a3b8;
            text-transform: uppercase;
        }
        .info-value {
            font-size: 1.5rem;
            font-weight: 800;
            color: #facc15;
        }
        
        .save-bar-actions {
            display: flex;
            gap: 1rem;
        }
        
        .btn-save-all {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            background: linear-gradient(135deg, #eab308, #ca8a04);
            color: #0f172a; 
            border: none; 
            padding: 14px 32px;
            border-radius: 10px; 
            font-weight: 800; 
            cursor: pointer;
            font-size: 1.05rem; 
            box-shadow: 0 4px 20px rgba(234, 179, 8, 0.4);
            transition: all 0.2s;
        }
        .btn-save-all:hover { 
            filter: brightness(1.1); 
            transform: translateY(-2px);
            box-shadow: 0 6px 25px rgba(234, 179, 8, 0.5);
        }
        .btn-save-all:active {
            transform: translateY(0);
        }
        
        .btn-cancel {
            padding: 14px 32px;
            background: transparent;
            border: 1px solid #475569;
            color: #cbd5e1;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-cancel:hover {
            background: rgba(255, 255, 255, 0.05);
            border-color: #64748b;
        }
        
        .loading-text { 
            text-align: center; 
            padding: 3rem; 
            color: #94a3b8;
            font-size: 1rem;
        }

        /* Alert Messages */
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-weight: 600;
            display: none;
        }
        .alert.show {
            display: block;
            animation: slideDown 0.3s ease;
        }
        .alert.success {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #34d399;
        }
        .alert.error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #f87171;
        }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* --- MOBILE STYLES --- */
        @media (max-width: 768px) {
            .container { padding: 1rem; }
            
            .header-content h1 { font-size: 1.8rem; }
            
            .filter-card { padding: 1.5rem; }
            .filter-grid { grid-template-columns: 1fr; } /* Stack filters */
            
            .save-bar {
                flex-direction: column;
                align-items: stretch;
                padding: 1rem;
            }
            .save-bar-info {
                justify-content: space-around;
                margin-bottom: 10px;
            }
            .save-bar-actions {
                width: 100%;
                gap: 10px;
            }
            .btn-save-all, .btn-cancel {
                flex: 1;
                padding: 12px;
                font-size: 1rem;
            }
            
            .data-table th, .data-table td { padding: 0.8rem; }
        }

        /* Option Colors Fix */
        .form-select option, .tbl-input option {
            background-color: #0f172a !important;
            color: #ffffff !important;
            padding: 10px !important;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="page-header">
        <div class="header-content">
            <h1>🧬 Bulk Edit Bio Info</h1>
            <p>Efficiently update animal records by location, building, and pen</p>
        </div>
        <a href="farm_dashboard.php" class="back-link">
            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
            </svg>
            Back to Dashboard
        </a>
    </div>

    <div id="alertBox" class="alert"></div>

    <div class="filter-card">
        <div class="filter-title">
            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path>
            </svg>
            Filter Animals by Location
        </div>
        <div class="filter-grid">
            <div class="form-group">
                <label class="form-label">1. Select Location</label>
                <select id="filter_location" class="form-select" onchange="loadBuildings()">
                    <option value="">-- Choose Location --</option>
                    <?php foreach($locations as $l): ?>
                        <option value="<?= $l['LOCATION_ID'] ?>"><?= htmlspecialchars($l['LOCATION_NAME']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">2. Select Building</label>
                <select id="filter_building" class="form-select" onchange="loadPens()" disabled>
                    <option value="">-- Choose Building --</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">3. Select Pen</label>
                <select id="filter_pen" class="form-select" onchange="loadAnimals()" disabled>
                    <option value="">-- Choose Pen --</option>
                </select>
            </div>
        </div>
    </div>

    <div class="stats-bar" id="statsBar" style="display: none;">
        <div class="stat-card">
            <div class="stat-value" id="totalAnimals">0</div>
            <div class="stat-label">Animals Loaded</div>
        </div>
        <div class="stat-card">
            <div class="stat-value" id="maleCount">0</div>
            <div class="stat-label">Males</div>
        </div>
        <div class="stat-card">
            <div class="stat-value" id="femaleCount">0</div>
            <div class="stat-label">Females</div>
        </div>
    </div>

    <form id="bulkEditForm" onsubmit="submitBulkEdit(event)">
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th width="15%">Tag Number</th>
                        <th width="20%">Animal Type</th>
                        <th width="25%">Breed</th>
                        <th width="15%">Sex</th>
                        <th width="25%">Birth Date</th>
                    </tr>
                </thead>
                <tbody id="animalTableBody">
                    <tr><td colspan="5" class="loading-text">
                        <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display: inline-block; vertical-align: middle; margin-right: 8px;">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        Select a pen from the filters above to load animals
                    </td></tr>
                </tbody>
            </table>
        </div>

        <div class="save-bar" id="saveBar">
            <div class="save-bar-info">
                <div class="info-item">
                    <span class="info-label">Records Loaded</span>
                    <span class="info-value" id="changeCount">0</span>
                </div>
            </div>
            <div class="save-bar-actions">
                <button type="button" class="btn-cancel" onclick="resetForm()">
                    Cancel
                </button>
                <button type="submit" class="btn-save-all">
                    💾 Save Changes
                </button>
            </div>
        </div>
    </form>
</div>

<script>
    const ALL_TYPES = <?= json_encode($types) ?>;
    const ALL_BREEDS = <?= json_encode($allBreeds) ?>;

    function showAlert(message, type) {
        const alert = document.getElementById('alertBox');
        alert.textContent = message;
        alert.className = `alert ${type} show`;
        setTimeout(() => alert.classList.remove('show'), 5000);
    }

    async function fetchData(params) {
        const res = await fetch(`?${params}`);
        return await res.json();
    }

    async function loadBuildings() {
        const id = document.getElementById('filter_location').value;
        const target = document.getElementById('filter_building');
        const penSelect = document.getElementById('filter_pen');
        
        target.innerHTML = '<option value="">-- Choose Building --</option>';
        target.disabled = true;
        penSelect.innerHTML = '<option value="">-- Choose Pen --</option>';
        penSelect.disabled = true;
        
        document.getElementById('animalTableBody').innerHTML = '<tr><td colspan="5" class="loading-text">Select a pen to load animals</td></tr>';
        document.getElementById('saveBar').classList.remove('visible');
        document.getElementById('statsBar').style.display = 'none';
        
        if (id) {
            const data = await fetchData(`action=get_buildings&loc_id=${id}`);
            data.forEach(i => target.innerHTML += `<option value="${i.BUILDING_ID}">${i.BUILDING_NAME}</option>`);
            target.disabled = false;
        }
    }

    async function loadPens() {
        const id = document.getElementById('filter_building').value;
        const target = document.getElementById('filter_pen');
        
        target.innerHTML = '<option value="">-- Choose Pen --</option>';
        target.disabled = true;
        
        document.getElementById('animalTableBody').innerHTML = '<tr><td colspan="5" class="loading-text">Select a pen to load animals</td></tr>';
        document.getElementById('saveBar').classList.remove('visible');
        document.getElementById('statsBar').style.display = 'none';
        
        if (id) {
            const data = await fetchData(`action=get_pens&bld_id=${id}`);
            data.forEach(i => target.innerHTML += `<option value="${i.PEN_ID}">${i.PEN_NAME}</option>`);
            target.disabled = false;
        }
    }

    async function loadAnimals() {
        const id = document.getElementById('filter_pen').value;
        const tbody = document.getElementById('animalTableBody');
        const saveBar = document.getElementById('saveBar');
        
        tbody.innerHTML = '<tr><td colspan="5" class="loading-text">Loading data...</td></tr>';
        saveBar.classList.remove('visible');

        if (id) {
            const res = await fetchData(`action=get_animals_editable&pen_id=${id}`);
            const animals = res.animals;
            tbody.innerHTML = '';

            if (animals.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" class="loading-text">No animals in this pen.</td></tr>';
                return;
            }

            // Stats
            let males = 0, females = 0;

            animals.forEach(a => {
                if(a.SEX === 'M') males++;
                if(a.SEX === 'F') females++;

                const row = document.createElement('tr');
                const tagVal = a.TAG_NO || ''; 
                const sexVal = a.SEX || 'U';
                const dobVal = a.BIRTH_DATE || '';
                const typeVal = a.ANIMAL_TYPE_ID || '';
                const breedVal = a.BREED_ID || '';
                const weightVal = (a.INITIAL_WEIGHT !== null) ? a.INITIAL_WEIGHT : 0;
                const costVal = (a.ACQUISITION_COST !== null) ? a.ACQUISITION_COST : 0;

                let typeOpts = '<option value="">-- Select --</option>';
                ALL_TYPES.forEach(t => {
                    const sel = t.ANIMAL_TYPE_ID == typeVal ? 'selected' : '';
                    typeOpts += `<option value="${t.ANIMAL_TYPE_ID}" ${sel}>${t.ANIMAL_TYPE_NAME}</option>`;
                });

                let breedOpts = getBreedOptions(typeVal, breedVal);

                row.innerHTML = `
                    <td>
                        <input type="text" readonly class="tbl-input" name="animals[${a.ANIMAL_ID}][tag]" value="${tagVal}" required placeholder="Tag No">
                        <input type="hidden" name="animals[${a.ANIMAL_ID}][initial_weight]" value="${weightVal}">
                        <input type="hidden" name="animals[${a.ANIMAL_ID}][acquisition_cost]" value="${costVal}">
                    </td>
                    <td>
                        <select class="tbl-input type-select" name="animals[${a.ANIMAL_ID}][type]" onchange="updateRowBreeds(this, ${a.ANIMAL_ID})" required>
                            ${typeOpts}
                        </select>
                    </td>
                    <td>
                        <select class="tbl-input" id="breed_select_${a.ANIMAL_ID}" name="animals[${a.ANIMAL_ID}][breed]" required>
                            ${breedOpts}
                        </select>
                    </td>
                    <td>
                        <select class="tbl-input" name="animals[${a.ANIMAL_ID}][sex]">
                            <option value="M" ${sexVal === 'M' ? 'selected' : ''}>Male</option>
                            <option value="F" ${sexVal === 'F' ? 'selected' : ''}>Female</option>
                        </select>
                    </td>
                    <td>
                        <input type="date" class="tbl-input" name="animals[${a.ANIMAL_ID}][dob]" value="${dobVal}" required>
                    </td>
                `;
                tbody.appendChild(row);
            });

            document.getElementById('totalAnimals').innerText = animals.length;
            document.getElementById('changeCount').innerText = animals.length;
            document.getElementById('maleCount').innerText = males;
            document.getElementById('femaleCount').innerText = females;
            
            document.getElementById('statsBar').style.display = 'flex';
            saveBar.classList.add('visible');
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
        const typeId = selectElem.value;
        const breedSelect = document.getElementById(`breed_select_${animalId}`);
        breedSelect.innerHTML = getBreedOptions(typeId, null);
    }

    function resetForm() {
        if (confirm("Discard all changes and reload?")) {
            loadAnimals();
        }
    }

    async function submitBulkEdit(e) {
        e.preventDefault();
        if (!confirm("💾 Save all changes to these animal records?")) return;

        const btn = document.querySelector('.btn-save-all');
        const originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '⏳ Saving...';

        const formData = new FormData(document.getElementById('bulkEditForm'));

        try {
            const res = await fetch('../process/updateAnimalBio.php', {
                method: 'POST',
                body: formData
            });
            const result = await res.json();

            if (result.success) {
                showAlert("✅ All records updated successfully!", "success");
                await loadAnimals();
            } else {
                showAlert("❌ Error: " + result.message, "error");
            }
        } catch (err) {
            console.error(err);
            showAlert("❌ System error occurred. Please try again.", "error");
        } finally {
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    }
</script>

</body>
</html>