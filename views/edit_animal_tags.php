<?php
// views/edit_animal_tags.php
error_reporting(0);
ini_set('display_errors', 0);
$page = "farm"; 
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
        $sql .= " ORDER BY a.ANIMAL_ID ASC"; 

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
    <title>Batch Tag Editor | FarmPro</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />

    <style>
        /* ─── CSS VARIABLES ─── */
        :root {
            --bg-base:        #080f1a;
            --bg-surface:     #0d1829;
            --bg-elevated:    #111f35;
            --bg-hover:       #162540;
            --border:         rgba(255,255,255,0.07);
            --border-active:  rgba(14,165,233,0.5); /* Blue Accent */
            --sky:            #0ea5e9;
            --sky-dim:        rgba(14,165,233,0.12);
            --sky-glow:       rgba(14,165,233,0.25);
            --emerald:        #10b981;
            --emerald-dim:    rgba(16,185,129,0.12);
            --emerald-glow:   rgba(16,185,129,0.25);
            --red:            #f87171;
            --text-primary:   #f1f5f9;
            --text-secondary: #94a3b8;
            --text-muted:     #475569;
            --radius-md:      10px;
            --radius-lg:      14px;
            --radius-xl:      20px;
            --font:           'DM Sans', system-ui, sans-serif;
            --font-mono:      'DM Mono', monospace;
            --transition:     0.18s cubic-bezier(0.4,0,0.2,1);
        }

        /* ─── RESET & BASE ─── */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: var(--font);
            background: var(--bg-base);
            color: var(--text-primary);
            min-height: 100vh;
            padding-bottom: 120px;
            background-image: radial-gradient(ellipse 80% 50% at 50% -20%, rgba(14,165,233,0.06) 0%, transparent 60%);
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
            color: var(--sky); background: var(--sky-dim); border: 1px solid rgba(14,165,233,0.2);
            padding: 6px 12px; border-radius: 99px;
        }

        /* ─── HEADER ─── */
        .page-header { margin-bottom: 2rem; }
        .page-title {
            font-size: clamp(1.6rem, 3vw, 2.2rem); font-weight: 700;
            color: var(--text-primary); letter-spacing: -0.03em; line-height: 1.1; margin-bottom: 0.25rem;
        }
        .page-title span {
            background: linear-gradient(135deg, var(--sky), #0284c7);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }
        .page-subtitle { color: var(--text-secondary); font-size: 0.95rem; }

        /* ─── FILTER BAR ─── */
        .filter-card {
            background: var(--bg-surface); border: 1px solid var(--border);
            border-radius: var(--radius-xl); padding: 1.5rem; margin-bottom: 2rem;
            display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; align-items: flex-end;
            box-shadow: 0 10px 30px -10px rgba(0,0,0,0.5);
        }
        .filter-group { display: flex; flex-direction: column; gap: 6px; }
        .filter-group label { font-size: 0.72rem; font-weight: 600; text-transform: uppercase; color: var(--text-secondary); letter-spacing: 0.05em; }
        .form-select {
            width: 100%; padding: 0 12px; height: 42px; background: var(--bg-elevated);
            border: 1px solid var(--border); color: var(--text-primary);
            border-radius: var(--radius-md); font-size: 0.9rem; font-family: var(--font);
            outline: none; transition: all var(--transition); appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2394a3b8' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
            background-repeat: no-repeat; background-position: right 12px center;
        }
        .form-select:focus { border-color: var(--sky); box-shadow: 0 0 0 3px var(--sky-glow); }
        .form-select:disabled { opacity: 0.4; cursor: not-allowed; }

        .btn-reset { 
            height: 42px; padding: 0 20px; background: transparent; border: 1px solid var(--border);
            color: var(--text-secondary); border-radius: var(--radius-md); text-decoration: none;
            font-size: 0.85rem; font-weight: 600; display: flex; align-items: center; justify-content: center;
            transition: all var(--transition);
        }
        .btn-reset:hover { background: var(--bg-elevated); color: var(--text-primary); border-color: var(--text-muted); }

        /* ─── INLINE TABLE FILTERS ─── */
        .inline-filters { display: flex; gap: 1rem; margin-bottom: 1.5rem; flex-wrap: wrap; }
        .search-input {
            flex: 1; min-width: 250px; padding: 12px 16px; background: var(--bg-surface);
            border: 1px solid var(--border); border-radius: var(--radius-lg);
            color: var(--text-primary); font-size: 1rem; font-family: var(--font); outline: none;
        }
        .search-input:focus { border-color: var(--sky); box-shadow: 0 0 0 3px var(--sky-glow); }
        .inline-select { width: 180px; }

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
        .table tbody tr { border-bottom: 1px solid var(--border); transition: background var(--transition); }
        .table tbody tr:hover { background: rgba(255,255,255,0.02); }
        .table td { padding: 12px 16px; font-size: 0.9rem; color: var(--text-primary); vertical-align: middle; }

        /* ─── TAG INPUT ─── */
        .tag-input {
            width: 100%; max-width: 220px; padding: 10px 14px; background: var(--bg-base);
            border: 2px solid var(--border); border-radius: 8px; color: #fff;
            font-weight: 700; font-size: 1rem; font-family: var(--font-mono);
            outline: none; transition: all var(--transition);
        }
        .tag-input:focus { border-color: var(--sky); background: var(--bg-elevated); box-shadow: 0 0 12px var(--sky-glow); }
        .tag-input.changed { border-color: var(--emerald); color: var(--emerald); background: var(--emerald-dim); }

        .col-path { color: var(--text-secondary); font-size: 0.8rem; }
        .col-path strong { color: var(--text-primary); font-weight: 600; }

        .status-badge {
            padding: 4px 10px; border-radius: 99px; font-size: 0.7rem;
            font-weight: 700; text-transform: uppercase; letter-spacing: 0.03em;
        }
        .status-badge.active   { background: var(--emerald-dim); color: var(--emerald); border: 1px solid rgba(16,185,129,0.2); }
        .status-badge.sold     { background: var(--sky-dim); color: var(--sky); border: 1px solid rgba(14,165,233,0.2); }
        .status-badge.deceased { background: rgba(255,255,255,0.05); color: var(--text-muted); border: 1px solid var(--border); }

        .empty-state { text-align: center; padding: 4rem 2rem; color: var(--text-muted); }

        /* ─── STICKY FOOTER ─── */
        .sticky-footer {
            position: fixed; bottom: 0; left: 0; width: 100%;
            background: rgba(13, 24, 41, 0.95); backdrop-filter: blur(12px);
            border-top: 1px solid var(--border-active); padding: 1.25rem 2rem;
            display: flex; justify-content: space-between; align-items: center;
            z-index: 100; box-shadow: 0 -10px 40px rgba(0,0,0,0.5);
        }
        .changes-tracker { 
            color: var(--emerald); font-weight: 700; font-family: var(--font-mono); 
            font-size: 1rem; display: none; align-items: center; gap: 8px;
        }
        .changes-tracker::before {
            content: ''; width: 8px; height: 8px; background: var(--emerald); 
            border-radius: 50%; box-shadow: 0 0 10px var(--emerald);
            animation: pulse 1.5s infinite;
        }
        @keyframes pulse { 0% { opacity: 1; } 50% { opacity: 0.4; } 100% { opacity: 1; } }

        .btn-save-all {
            background: var(--sky); color: #000; border: none; padding: 12px 36px;
            border-radius: var(--radius-md); font-weight: 700; font-size: 1rem;
            cursor: pointer; transition: all var(--transition); box-shadow: 0 4px 15px var(--sky-glow);
        }
        .btn-save-all:hover { background: #38bdf8; transform: translateY(-2px); box-shadow: 0 8px 25px var(--sky-glow); }
        .btn-save-all:disabled { opacity: 0.4; transform: none; box-shadow: none; cursor: not-allowed; }

        /* ─── RESPONSIVE ─── */
        @media (max-width: 900px) {
            .container { padding: 1rem; }
            .filter-card { grid-template-columns: 1fr; }
            .inline-filters { flex-direction: column; }
            .inline-select { width: 100%; }

            .table-wrap { border: none; background: transparent; }
            .table, .table thead, .table tbody, .table tr, .table td { display: block; }
            .table thead { display: none; }
            .table tr { 
                background: var(--bg-surface); border: 1px solid var(--border); 
                border-radius: var(--radius-xl); margin-bottom: 1rem; padding: 1.25rem;
            }
            .table td { 
                display: flex; justify-content: space-between; align-items: center; 
                padding: 0.6rem 0; border-bottom: 1px solid rgba(255,255,255,0.05); text-align: right;
            }
            .table td:last-child { border-bottom: none; }
            .table td::before { 
                content: attr(data-label); font-weight: 700; color: var(--text-muted); 
                font-size: 0.75rem; text-transform: uppercase; text-align: left;
            }
            .sticky-footer { flex-direction: column; gap: 12px; padding: 1.5rem; text-align: center; }
            .btn-save-all { width: 100%; }
        }
    </style>
</head>
<body>
<div class="container">

    <div class="top-bar">
        <a href="farm_dashboard.php" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Farm Dashboard
        </a>
        <span class="page-badge"><i class="fa-solid fa-tags"></i> Batch Processing</span>
    </div>

    <div class="page-header">
        <div class="header-info">
            <h1>Batch <span>Tag Editor</span></h1>
            <p>Modify and reconcile identification numbers for the active herd across specific sites.</p>
        </div>
    </div>

    <form method="GET" class="filter-card">
        <div class="filter-group">
            <label>1. Location Registry</label>
            <select name="f_loc" class="form-select" onchange="this.form.submit()" <?php echo ($USER_LOCATION_ != 1000) ? 'disabled' : ''; ?>>
                <?php if ($USER_LOCATION_ == 1000): ?><option value="">-- Choose Location --</option><?php endif; ?>
                <?php foreach ($locations as $loc): ?>
                    <option value="<?= $loc['LOCATION_ID'] ?>" <?= $filter_loc == $loc['LOCATION_ID'] ? 'selected' : '' ?>><?= htmlspecialchars($loc['LOCATION_NAME']) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if ($USER_LOCATION_ != 1000): ?>
                <input type="hidden" name="f_loc" value="<?= $USER_LOCATION_ ?>">
            <?php endif; ?>
        </div>
        <div class="filter-group">
            <label>2. Structural Unit</label>
            <select name="f_bld" class="form-select" onchange="this.form.submit()" <?= empty($filter_loc) ? 'disabled' : '' ?>>
                <option value="">-- All Buildings --</option>
                <?php foreach ($filter_buildings as $bld): ?>
                    <option value="<?= $bld['BUILDING_ID'] ?>" <?= $filter_bld == $bld['BUILDING_ID'] ? 'selected' : '' ?>><?= htmlspecialchars($bld['BUILDING_NAME']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-group">
            <label>3. Target Pen</label>
            <select name="f_pen" class="form-select" onchange="this.form.submit()" <?= empty($filter_bld) ? 'disabled' : '' ?>>
                <option value="">-- All Pens --</option>
                <?php foreach ($filter_pens as $pen): ?>
                    <option value="<?= $pen['PEN_ID'] ?>" <?= $filter_pen == $pen['PEN_ID'] ? 'selected' : '' ?>><?= htmlspecialchars($pen['PEN_NAME']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <a href="edit_animal_tags.php" class="btn-reset">
            <i class="fa-solid fa-rotate-left me-2"></i> Reset View
        </a>
    </form>

    <?php if (!empty($animal_data)): ?>
        <div class="inline-filters">
            <input type="text" class="search-input" id="search_term" placeholder="Search rows by tag, breed, or classification..." onkeyup="filterTable()">
            
            <select class="form-select inline-select" id="filter_sex" onchange="filterTable()">
                <option value="">All Sexes</option>
                <option value="male">Male</option>
                <option value="female">Female</option>
            </select>

            <select class="form-select inline-select" id="filter_status" onchange="filterTable()">
                <option value="">All Statuses</option>
                <option value="active">Active</option>
                <option value="pregnant">Pregnant</option>
                <option value="dry">Dry</option>
            </select>
        </div>
    <?php endif; ?>

    <div class="table-card">
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width: 100px;">System ID</th>
                        <th>Interactive Tag No.</th>
                        <th>Life Stage / Breed</th>
                        <th>Gender</th>
                        <th>Current Status</th>
                        <th>Physical Placement</th>
                    </tr>
                </thead>
                <tbody id="animal-table">
                    <?php foreach ($animal_data as $data): ?>
                        <tr class="animal-row" data-id="<?= $data['ANIMAL_ID'] ?>">
                            <td data-label="ID" style="color: var(--text-muted); font-family: var(--font-mono);">#<?= $data['ANIMAL_ID'] ?></td>
                            <td data-label="Tag Input">
                                <input type="text" class="tag-input" 
                                       value="<?= htmlspecialchars($data['TAG_NO']) ?>" 
                                       data-original="<?= htmlspecialchars($data['TAG_NO']) ?>"
                                       onkeyup="checkInputChanges(this)"
                                       spellcheck="false">
                            </td>
                            <td data-label="Breed">
                                <div class="breed-info">
                                    <span style="font-weight:700; color:#fff;"><?= htmlspecialchars($data['STAGE_NAME'] ?? 'Unclassified') ?></span><br>
                                    <small style="color:var(--text-secondary);"><?= htmlspecialchars($data['BREED_NAME']) ?></small>
                                </div>
                            </td>
                            <td data-label="Gender" class="col-sex"><?= ($data['SEX'] == 'M') ? 'Male' : (($data['SEX'] == 'F') ? 'Female' : 'Unknown') ?></td>
                            <td data-label="Status" class="col-status">
                                <span class="status-badge <?= strtolower($data['CURRENT_STATUS']) ?>"><?= htmlspecialchars($data['CURRENT_STATUS']) ?></span>
                            </td>
                            <td data-label="Location" class="col-path">
                                <strong><?= htmlspecialchars($data['BUILDING_NAME']) ?></strong><br>
                                <?= htmlspecialchars($data['PEN_NAME']) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div id="empty-state" class="empty-state" style="display:<?= empty($animal_data) ? 'block' : 'none' ?>;">
            <i class="fa-solid fa-folder-open" style="font-size: 3rem; opacity: 0.2; margin-bottom: 1.5rem; display:block;"></i>
            <h3><?= empty($filter_loc) ? 'Select a location to initialize the editor' : 'No records found matching these criteria' ?></h3>
        </div>
    </div>
</div>

<div class="sticky-footer" id="stickyFooter">
    <div id="changes-tracker" class="changes-tracker">0 tags modified</div>
    <button type="button" class="btn-save-all" id="btnSave" onclick="saveAllTags()" disabled>
        <i class="fa-solid fa-floppy-disk me-2"></i> Save Changes
    </button>
</div>

<script>
    let changesCount = 0;

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
            tracker.style.display = 'flex';
            tracker.innerText = `${changesCount} tag(s) modified`;
            btnSave.disabled = false;
        } else {
            tracker.style.display = 'none';
            btnSave.disabled = true;
        }
    }

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

    async function saveAllTags() {
        const changedInputs = document.querySelectorAll('.tag-input.changed');
        if (changedInputs.length === 0) return;

        if (!confirm(`Are you sure you want to commit updates for ${changedInputs.length} record(s)?`)) return;

        const btnSave = document.getElementById('btnSave');
        btnSave.disabled = true;
        btnSave.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i>Processing...';

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
            alert("Error: Tag numbers cannot be empty. Please correct the highlighted rows.");
            btnSave.disabled = false;
            btnSave.innerHTML = '<i class="fa-solid fa-floppy-disk me-2"></i> Save Changes';
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
                alert("Success: " + result.message);
                changedInputs.forEach(input => {
                    input.setAttribute('data-original', input.value.trim().toUpperCase());
                    input.classList.remove('changed');
                });
                updateGlobalChangeCount();
            } else {
                alert("Database Error: " + result.message);
            }
        } catch (error) {
            console.error(error);
            alert("Network Error: Could not reach the server.");
        } finally {
            btnSave.innerHTML = '<i class="fa-solid fa-floppy-disk me-2"></i> Save Changes';
            updateGlobalChangeCount();
        }
    }
</script>
</body>
</html>