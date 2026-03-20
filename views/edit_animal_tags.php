<?php
// views/edit_animal_tags.php
error_reporting(0);
ini_set('display_errors', 0);
$page = "farm"; // Active Tab
include '../config/Connection.php';

include '../security/checkAccess.php';
checkAccess('farm');

include '../common/navbar.php';
include '../common/chat_support.php';
include '../functions/getUsersLocation.php';

// --- 1. HANDLING FILTERS ---
$filter_loc = ($USER_LOCATION_ != 1000) ? $USER_LOCATION_ : ($_GET['f_loc'] ?? '');
$filter_bld = $_GET['f_bld'] ?? '';
$filter_pen = $_GET['f_pen'] ?? '';

$animal_data      = [];
$animal_types     = [];
$locations        = [];
$filter_buildings = [];
$filter_pens      = [];

try {
    if (!isset($conn)) { throw new Exception("Database connection failed."); }

    $animal_types = $conn->query("SELECT * FROM Animal_Type ORDER BY ANIMAL_TYPE_NAME ASC")->fetchAll(PDO::FETCH_ASSOC);

    if ($USER_LOCATION_ != 1000) {
        $loc_stmt = $conn->prepare("SELECT * FROM Locations WHERE LOCATION_ID = ? ORDER BY LOCATION_NAME ASC");
        $loc_stmt->execute([$USER_LOCATION_]);
        $locations = $loc_stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $locations = $conn->query("SELECT * FROM Locations ORDER BY LOCATION_NAME ASC")->fetchAll(PDO::FETCH_ASSOC);
    }

    if ($filter_loc) {
        $stmt = $conn->prepare("SELECT * FROM Buildings WHERE LOCATION_ID = ? ORDER BY BUILDING_NAME");
        $stmt->execute([$filter_loc]);
        $filter_buildings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    if ($filter_bld) {
        $stmt = $conn->prepare("SELECT * FROM Pens WHERE BUILDING_ID = ? ORDER BY PEN_NAME");
        $stmt->execute([$filter_bld]);
        $filter_pens = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Only fetch animals if at least a location is selected to prevent massive load
    if (!empty($filter_loc)) {
        $sql = "SELECT 
                    a.ANIMAL_ID, a.TAG_NO, a.SEX, a.CURRENT_STATUS,
                    at.ANIMAL_TYPE_NAME, b.BREED_NAME, l.LOCATION_NAME,
                    ac.STAGE_NAME, bld.BUILDING_NAME, p.PEN_NAME
                FROM Animal_Records a
                LEFT JOIN Animal_Type at         ON a.ANIMAL_TYPE_ID = at.ANIMAL_TYPE_ID
                LEFT JOIN Breeds b               ON a.BREED_ID       = b.BREED_ID
                LEFT JOIN Locations l            ON a.LOCATION_ID    = l.LOCATION_ID
                LEFT JOIN Buildings bld          ON a.BUILDING_ID    = bld.BUILDING_ID
                LEFT JOIN Pens p                 ON a.PEN_ID         = p.PEN_ID
                LEFT JOIN animal_classifications ac ON a.CLASS_ID    = ac.CLASS_ID
                WHERE a.IS_ACTIVE = 1";

        $params = [];
        if ($filter_loc) { $sql .= " AND a.LOCATION_ID = ?"; $params[] = $filter_loc; }
        if ($filter_bld) { $sql .= " AND a.BUILDING_ID = ?"; $params[] = $filter_bld; }
        if ($filter_pen) { $sql .= " AND a.PEN_ID = ?";      $params[] = $filter_pen; }
        $sql .= " ORDER BY a.ANIMAL_ID ASC"; // Order by ID so numbering makes sense chronologically

        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $animal_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (Exception $e) {
    echo "<script>console.error('Database Error: " . addslashes($e->getMessage()) . "');</script>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Batch Tag Editor - FarmPro</title>

    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif; background:linear-gradient(135deg,#0f172a 0%,#1e293b 100%); min-height:100vh; color:white; padding-bottom:100px; }
        .container { max-width:1200px; margin:0 auto; padding:2rem; width:100%; }

        .back-link { display:inline-flex; align-items:center; gap:8px; text-decoration:none; color:#94a3b8; font-weight:600; font-size:0.95rem; margin-bottom:20px; transition:color 0.2s; }
        .back-link:hover { color:white; }

        .header { display:flex; justify-content:space-between; align-items:center; margin-bottom:2rem; gap:1rem; flex-wrap:wrap; }
        .header-info h1 { font-size:clamp(1.8rem,4vw,2.5rem); font-weight:bold; margin-bottom:0.5rem; color:#0ea5e9; }
        .header-info p  { color:#cbd5e1; font-size:0.95rem; }

        .filter-bar { background:rgba(30,41,59,0.6); border:1px solid #475569; padding:1.5rem; border-radius:12px; margin-bottom:1.5rem; display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:1rem; align-items:end; }
        .filter-group { display:flex; flex-direction:column; gap:0.4rem; }
        .filter-group label { font-size:0.85rem; text-transform:uppercase; color:#94a3b8; font-weight:600; }
        .filter-select { width:100%; padding:12px; background:#0f172a; border:1px solid #334155; color:white; border-radius:8px; font-size:0.95rem; outline:none; transition:border-color 0.2s; }
        .filter-select:focus { border-color:#0ea5e9; }
        .btn-reset { padding:12px 24px; background:transparent; border:1px solid #475569; color:#94a3b8; border-radius:8px; text-decoration:none; font-weight:600; display:flex; align-items:center; justify-content:center; white-space:nowrap; transition:0.2s; }
        .btn-reset:hover { background:rgba(255,255,255,0.05); color:white; border-color:white; }

        /* Inline Table Filters */
        .inline-filters { display:flex; gap:10px; margin-bottom:1.5rem; flex-wrap:wrap; }
        .search-input { flex:1; min-width:200px; padding:12px 15px; background:rgba(30,41,59,0.5); border:1px solid #475569; border-radius:8px; color:white; font-size:1rem; outline:none; }
        .search-input:focus { border-color:#0ea5e9; }
        .inline-select { width:160px; padding:12px; background:rgba(30,41,59,0.8); border:1px solid #475569; border-radius:8px; color:white; font-size:0.95rem; outline:none; }
        .inline-select:focus { border-color:#0ea5e9; }

        /* Table */
        .table-container { background:rgba(30,41,59,0.5); border-radius:12px; border:1px solid #475569; overflow-x:auto; min-height:200px; margin-bottom: 2rem;}
        .table { width:100%; border-collapse:collapse; min-width:800px; }
        .table thead { background:rgba(15,23,42,0.5); }
        .table th { padding:1rem 1.5rem; text-align:left; color:#e2e8f0; text-transform:uppercase; font-size:0.85rem; font-weight:600; border-bottom:1px solid #475569; }
        .table td { padding:0.75rem 1.5rem; vertical-align:middle; border-bottom:1px solid rgba(255,255,255,0.05); font-size:0.95rem; color:#cbd5e1; }
        .table tbody tr:hover { background:rgba(255,255,255,0.02); }

        /* Tag Input */
        .tag-input { width: 100%; max-width: 250px; padding: 10px 15px; background: #0f172a; border: 2px solid #334155; border-radius: 8px; color: #fff; font-weight: bold; font-size: 1.1rem; font-family: monospace; outline: none; transition: 0.2s; }
        .tag-input:focus { border-color: #38bdf8; background: #1e293b; box-shadow: 0 0 0 3px rgba(14,165,233,0.2); }
        .tag-input.changed { border-color: #22c55e; color: #4ade80; background: rgba(34,197,94,0.1); }

        .animal-type-info  { color:#cbd5e1; font-size:0.875rem; }

        .status-badge { padding:4px 10px; border-radius:6px; font-size:0.75rem; font-weight:700; text-transform:uppercase; }
        .status-badge.active   { background:rgba(34,197,94,0.1);  color:#34d399; border:1px solid rgba(34,197,94,0.2); }
        .status-badge.sold     { background:rgba(59,130,246,0.1); color:#60a5fa; border:1px solid rgba(59,130,246,0.2); }
        .status-badge.deceased { background:rgba(107,114,128,0.1); color:#94a3b8; border:1px solid rgba(107,114,128,0.2); }

        .empty-state { text-align:center; padding:3rem 1rem; display:block; color:#94a3b8; }

        /* Sticky Footer */
        .sticky-footer { position: fixed; bottom: 0; left: 0; width: 100%; background: rgba(15, 23, 42, 0.95); border-top: 1px solid #334155; padding: 1rem 2rem; display: flex; justify-content: space-between; align-items: center; z-index: 100; backdrop-filter: blur(10px); }
        .changes-tracker { color: #34d399; font-weight: bold; font-size: 1.1rem; display: none;}
        .btn-save-all { background: #22c55e; color: white; border: none; padding: 12px 30px; border-radius: 8px; font-weight: bold; font-size: 1.1rem; cursor: pointer; transition: 0.2s; box-shadow: 0 4px 15px rgba(34,197,94,0.3);}
        .btn-save-all:hover { background: #16a34a; transform: translateY(-2px); }
        .btn-save-all:disabled { background: #475569; color: #94a3b8; cursor: not-allowed; box-shadow: none; transform: none; }

        @media (max-width:900px) {
            .container { padding:1rem; }
            .filter-bar { grid-template-columns:1fr; }
            .btn-reset { width:100%; }
            .inline-filters { flex-direction: column; }
            .inline-select { width: 100%; }
            
            .table-container { border:none; background:transparent; }
            .table { min-width:0; display:block; }
            .table thead { display:none; }
            .table tbody { display:block; width:100%; }
            .table tr  { display:block; background:rgba(30,41,59,0.6); border:1px solid #475569; border-radius:12px; margin-bottom:1rem; padding:1rem; }
            .table td  { display:flex; justify-content:space-between; align-items:center; padding:0.75rem 0; border-bottom:1px dashed rgba(255,255,255,0.1); text-align:right; font-size:0.95rem; }
            .table td:last-child { border-bottom:none; flex-direction: column; align-items: flex-end; gap: 10px;}
            .table td::before { content:attr(data-label); font-weight:700; color:#94a3b8; font-size:0.8rem; text-transform:uppercase; margin-right:1rem; text-align:left; flex-shrink:0; }
            
            .sticky-footer { padding: 1rem; flex-direction: column; gap: 10px; text-align: center;}
            .btn-save-all { width: 100%; }
        }
    </style>
</head>
<body>
<div class="container">

    <a href="farm_dashboard.php" class="back-link">
        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
        Back to Farm Dashboard
    </a>

    <div class="header">
        <div class="header-info">
            <h1>Batch Tag Editor</h1>
            <p>Rapidly update tag numbers for animals in a specific location.</p>
        </div>
    </div>

    <form method="GET" class="filter-bar">
        <div class="filter-group">
            <label>1. Location</label>
            <select name="f_loc" class="filter-select" onchange="this.form.submit()" <?php echo ($USER_LOCATION_ != 1000) ? 'style="pointer-events:none;opacity:0.7;background-color:#1e293b;"' : ''; ?>>
                <?php if ($USER_LOCATION_ == 1000): ?><option value="">-- Select Location --</option><?php endif; ?>
                <?php foreach ($locations as $loc): ?>
                    <option value="<?= $loc['LOCATION_ID'] ?>" <?= $filter_loc == $loc['LOCATION_ID'] ? 'selected' : '' ?>><?= htmlspecialchars($loc['LOCATION_NAME']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-group">
            <label>2. Building</label>
            <select name="f_bld" class="filter-select" onchange="this.form.submit()" <?= empty($filter_loc) ? 'disabled' : '' ?>>
                <option value="">-- All Buildings --</option>
                <?php foreach ($filter_buildings as $bld): ?>
                    <option value="<?= $bld['BUILDING_ID'] ?>" <?= $filter_bld == $bld['BUILDING_ID'] ? 'selected' : '' ?>><?= htmlspecialchars($bld['BUILDING_NAME']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-group">
            <label>3. Pen</label>
            <select name="f_pen" class="filter-select" onchange="this.form.submit()" <?= empty($filter_bld) ? 'disabled' : '' ?>>
                <option value="">-- All Pens --</option>
                <?php foreach ($filter_pens as $pen): ?>
                    <option value="<?= $pen['PEN_ID'] ?>" <?= $filter_pen == $pen['PEN_ID'] ? 'selected' : '' ?>><?= htmlspecialchars($pen['PEN_NAME']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <a href="edit_animal_tags.php" class="btn-reset">Reset</a>
    </form>

    <?php if (!empty($animal_data)): ?>
        <div class="inline-filters">
            <input type="text" class="search-input" id="search_term" placeholder="Search rows by tag, breed, or classification..." onkeyup="filterTable()">
            
            <select class="inline-select" id="filter_sex" onchange="filterTable()">
                <option value="">All Sexes</option>
                <option value="male">Male</option>
                <option value="female">Female</option>
                <option value="unknown">Unknown</option>
            </select>

            <select class="inline-select" id="filter_status" onchange="filterTable()">
                <option value="">All Statuses</option>
                <option value="active">Active</option>
                <option value="pregnant">Pregnant</option>
                <option value="service">Service</option>
                <option value="dry">Dry</option>
            </select>
        </div>
    <?php endif; ?>

    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Current Tag No.</th>
                    <th>Class / Breed</th>
                    <th>Sex</th>
                    <th>Status</th>
                    <th>Location</th>
                </tr>
            </thead>
            <tbody id="animal-table">
                <?php foreach ($animal_data as $data): ?>
                    <?php 
                        $sexLabel = 'Unknown';
                        if ($data['SEX'] == 'M') $sexLabel = 'Male';
                        if ($data['SEX'] == 'F') $sexLabel = 'Female';
                    ?>
                    <tr class="animal-row" data-id="<?= $data['ANIMAL_ID'] ?>">
                        <td data-label="ID" style="color: #94a3b8; font-family: monospace;">#<?= $data['ANIMAL_ID'] ?></td>
                        <td data-label="Tag No">
                            <input type="text" class="tag-input" 
                                   value="<?= htmlspecialchars($data['TAG_NO']) ?>" 
                                   data-original="<?= htmlspecialchars($data['TAG_NO']) ?>"
                                   onkeyup="checkInputChanges(this)">
                        </td>
                        <td data-label="Class / Breed">
                            <div class="animal-type-info">
                                <span style="color:#fff;font-weight:600;"><?= htmlspecialchars($data['STAGE_NAME'] ?? 'Unclassified') ?></span><br>
                                <small style="color:#94a3b8"><?= htmlspecialchars($data['BREED_NAME']) ?></small>
                            </div>
                        </td>
                        <td data-label="Sex" class="col-sex"><?= $sexLabel ?></td>
                        <td data-label="Status" class="col-status">
                            <span class="status-badge <?= strtolower($data['CURRENT_STATUS']) ?>"><?= htmlspecialchars($data['CURRENT_STATUS']) ?></span>
                        </td>
                        <td data-label="Location"><?= htmlspecialchars($data['BUILDING_NAME']) ?> - <?= htmlspecialchars($data['PEN_NAME']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <div id="empty-state" class="empty-state" style="display:<?= empty($animal_data) ? 'block' : 'none' ?>;">
            <h3><?= empty($filter_loc) ? 'Please select a Location to load animals' : 'No records found matching criteria' ?></h3>
        </div>
    </div>
</div>

<div class="sticky-footer">
    <div id="changes-tracker" class="changes-tracker">0 tags modified</div>
    <button type="button" class="btn-save-all" id="btnSave" onclick="saveAllTags()" disabled>Save All Changes</button>
</div>

<script>
    let changesCount = 0;

    // Checks individual inputs as the user types
    function checkInputChanges(input) {
        const orig = input.getAttribute('data-original').trim().toUpperCase();
        const current = input.value.trim().toUpperCase();
        
        if (orig !== current) {
            input.classList.add('changed');
        } else {
            input.classList.remove('changed');
        }
        updateGlobalChangeCount();
    }

    function updateGlobalChangeCount() {
        const changedInputs = document.querySelectorAll('.tag-input.changed');
        changesCount = changedInputs.length;

        const tracker = document.getElementById('changes-tracker');
        const btnSave = document.getElementById('btnSave');

        if (changesCount > 0) {
            tracker.style.display = 'block';
            tracker.innerText = `${changesCount} tag(s) modified`;
            btnSave.disabled = false;
        } else {
            tracker.style.display = 'none';
            btnSave.disabled = true;
        }
    }

    // Dynamic Filter Table (Search + Sex + Status)
    function filterTable() {
        const term = document.getElementById('search_term').value.toLowerCase();
        const sexFilter = document.getElementById('filter_sex').value.toLowerCase();
        const statusFilter = document.getElementById('filter_status').value.toLowerCase();
        
        const rows = document.querySelectorAll('#animal-table tr.animal-row');
        let visible = 0;
        
        rows.forEach(r => {
            const textContent = r.innerText.toLowerCase();
            const rowSex = r.querySelector('.col-sex').innerText.toLowerCase();
            const rowStatus = r.querySelector('.col-status').innerText.toLowerCase();
            
            const matchesTerm = textContent.includes(term);
            const matchesSex = sexFilter === "" || rowSex === sexFilter;
            const matchesStatus = statusFilter === "" || rowStatus.includes(statusFilter);
            
            if (matchesTerm && matchesSex && matchesStatus) {
                r.style.display = '';
                visible++;
            } else {
                r.style.display = 'none';
            }
        });
        
        document.getElementById('empty-state').style.display = (visible === 0) ? 'block' : 'none';
    }

    // Save Logic
    async function saveAllTags() {
        const changedInputs = document.querySelectorAll('.tag-input.changed');
        if (changedInputs.length === 0) return;

        if (!confirm(`Are you sure you want to update ${changedInputs.length} tags?`)) return;

        const btnSave = document.getElementById('btnSave');
        btnSave.disabled = true;
        btnSave.innerText = "Saving...";

        const payload = [];
        let hasEmpty = false;

        changedInputs.forEach(input => {
            const val = input.value.trim();
            if (val === '') hasEmpty = true;
            
            payload.push({
                animal_id: input.closest('tr').getAttribute('data-id'),
                tag_no: val
            });
        });

        if (hasEmpty) {
            alert("Error: One or more tags are empty. Please fill them out before saving.");
            btnSave.disabled = false;
            btnSave.innerText = "Save All Changes";
            return;
        }

        try {
            const response = await fetch('../process/saveTags.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });

            const result = await response.json();

            if (result.success) {
                alert(result.message);
                // Update 'original' dataset on inputs so they turn back to normal styling
                changedInputs.forEach(input => {
                    input.setAttribute('data-original', input.value.trim().toUpperCase());
                    input.classList.remove('changed');
                });
                updateGlobalChangeCount();
            } else {
                alert("Error: " + result.message);
            }
        } catch (error) {
            console.error(error);
            alert("A system error occurred while trying to save.");
        } finally {
            btnSave.innerText = "Save All Changes";
            updateGlobalChangeCount();
        }
    }
</script>
</body>
</html>