<?php
// views/animal_operations.php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);

$page = "farm";
include '../common/navbar.php';
include '../config/Connection.php';
include '../security/checkRole.php';    
checkRole(2); 

// =========================================================
// 1. AJAX HANDLER (For Dropdowns)
// =========================================================
if (isset($_GET['action'])) {
    ob_end_clean();
    header('Content-Type: application/json');
    $action = $_GET['action'];
    $status = $_GET['status_filter'] ?? 'Active'; // Default to Active

    // Build Status Clause
    $statusClause = " AND IS_ACTIVE = 1 "; // Default
    if ($status === 'Inactive') $statusClause = " AND IS_ACTIVE = 0 ";
    if ($status === 'All') $statusClause = ""; 

    try {
        if ($action === 'get_buildings' && isset($_GET['loc_id'])) {
            $stmt = $conn->prepare("SELECT BUILDING_ID, BUILDING_NAME FROM buildings WHERE LOCATION_ID = ? ORDER BY BUILDING_NAME");
            $stmt->execute([$_GET['loc_id']]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC)); exit;
        }
        if ($action === 'get_pens' && isset($_GET['bldg_id'])) {
            $stmt = $conn->prepare("SELECT PEN_ID, PEN_NAME FROM pens WHERE BUILDING_ID = ? ORDER BY PEN_NAME");
            $stmt->execute([$_GET['bldg_id']]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC)); exit;
        }
        if ($action === 'get_animals' && isset($_GET['pen_id'])) {
            $sql = "SELECT ANIMAL_ID, TAG_NO FROM animal_records WHERE PEN_ID = ? $statusClause ORDER BY TAG_NO";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$_GET['pen_id']]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC)); exit;
        }
        if ($action === 'search_tag' && isset($_GET['query'])) {
            // Global search regardless of location, respecting status filter
            $q = "%" . $_GET['query'] . "%";
            $sql = "SELECT ANIMAL_ID, TAG_NO, CURRENT_STATUS FROM animal_records WHERE TAG_NO LIKE ? $statusClause LIMIT 5";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$q]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC)); exit;
        }
    } catch (Exception $e) { echo json_encode([]); exit; }
}

// =========================================================
// 2. MAIN PAGE LOGIC
// =========================================================
$locations = $conn->query("SELECT * FROM locations ORDER BY LOCATION_NAME")->fetchAll(PDO::FETCH_ASSOC);

$animal_id = $_GET['animal_id'] ?? null;
$animal_info = null;
$records = [];
$total_cost = 0;

if ($animal_id) {
    // A. Fetch Basic Info
    $stmt = $conn->prepare("
        SELECT a.*, 
               at.ANIMAL_TYPE_NAME, b.BREED_NAME, 
               l.LOCATION_NAME, bu.BUILDING_NAME, p.PEN_NAME
        FROM animal_records a
        LEFT JOIN animal_type at ON a.ANIMAL_TYPE_ID = at.ANIMAL_TYPE_ID
        LEFT JOIN breeds b ON a.BREED_ID = b.BREED_ID
        LEFT JOIN locations l ON a.LOCATION_ID = l.LOCATION_ID
        LEFT JOIN buildings bu ON a.BUILDING_ID = bu.BUILDING_ID
        LEFT JOIN pens p ON a.PEN_ID = p.PEN_ID
        WHERE a.ANIMAL_ID = ?
    ");
    $stmt->execute([$animal_id]);
    $animal_info = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($animal_info) {
        // B. Fetch All Transactions
        $sql = "SELECT * FROM (
            -- 1. FEEDING
            SELECT ft.TRANSACTION_DATE as LOG_DATE, 'Feeding' as LOG_TYPE, f.FEED_NAME as ITEM_NAME, 
                   ft.TRANSACTION_COST as COST, ft.REMARKS, ft.QUANTITY_KG as QTY, 'kg' as UNIT
            FROM FEED_TRANSACTIONS ft
            JOIN FEEDS f ON ft.FEED_ID = f.FEED_ID
            WHERE ft.ANIMAL_ID = ?

            UNION ALL

            -- 2. MEDICATION
            SELECT tt.TRANSACTION_DATE as LOG_DATE, 'Medication' as LOG_TYPE, m.SUPPLY_NAME as ITEM_NAME, 
                   tt.TOTAL_COST as COST, tt.REMARKS, tt.QUANTITY_USED as QTY, 'units' as UNIT
            FROM TREATMENT_TRANSACTIONS tt
            JOIN MEDICINES m ON tt.ITEM_ID = m.SUPPLY_ID
            WHERE tt.ANIMAL_ID = ?

            UNION ALL

            -- 3. VACCINATION
            SELECT vr.VACCINATION_DATE as LOG_DATE, 'Vaccination' as LOG_TYPE, v.SUPPLY_NAME as ITEM_NAME, 
                   (vr.VACCINE_COST + vr.VACCINATION_COST) as COST, vr.REMARKS, vr.QUANTITY as QTY, 'doses' as UNIT
            FROM VACCINATION_RECORDS vr
            JOIN VACCINES v ON vr.VACCINE_ITEM_ID = v.SUPPLY_ID
            WHERE vr.ANIMAL_ID = ?

            UNION ALL

            -- 4. VITAMINS
            SELECT vt.TRANSACTION_DATE as LOG_DATE, 'Vitamins' as LOG_TYPE, vs.SUPPLY_NAME as ITEM_NAME, 
                   vt.TOTAL_COST as COST, vt.REMARKS, vt.QUANTITY_USED as QTY, 'units' as UNIT
            FROM VITAMINS_SUPPLEMENTS_TRANSACTIONS vt
            JOIN VITAMINS_SUPPLEMENTS vs ON vt.ITEM_ID = vs.SUPPLY_ID
            WHERE vt.ANIMAL_ID = ?

            UNION ALL

            -- 5. CHECKUPS
            SELECT c.CHECKUP_DATE as LOG_DATE, 'Checkup' as LOG_TYPE, CONCAT('Vet: ', c.VET_NAME) as ITEM_NAME, 
                   c.COST as COST, c.REMARKS, 1 as QTY, 'visit' as UNIT
            FROM CHECK_UPS c
            WHERE c.ANIMAL_ID = ?

        ) AS MasterLog 
        ORDER BY LOG_DATE DESC";

        $stmt = $conn->prepare($sql);
        $stmt->execute([$animal_id, $animal_id, $animal_id, $animal_id, $animal_id]);
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach($records as $r) $total_cost += $r['COST'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Operational History</title>
    <style>
        /* --- THEME STYLES --- */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: #e2e8f0; min-height: 100vh;
        }
        .container { max-width: 1400px; margin: 0 auto; padding: 2rem; }

        .page-header { margin-bottom: 2rem; border-bottom: 1px solid #334155; padding-bottom: 1rem; }
        .page-title { font-size: 2rem; font-weight: 800; color: white; margin-bottom: 0.5rem; }
        .page-desc { color: #94a3b8; }

        /* Filter Card */
        .filter-card {
            background: rgba(30, 41, 59, 0.6); border: 1px solid #475569;
            border-radius: 12px; padding: 1.5rem; margin-bottom: 2rem;
        }
        .filter-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); 
            gap: 1rem; align-items: end;
        }
        .form-group { display: flex; flex-direction: column; gap: 5px; }
        .form-group label { font-size: 0.75rem; color: #94a3b8; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
        .form-select, .form-input {
            width: 100%; padding: 10px; background: #0f172a; border: 1px solid #334155;
            color: white; border-radius: 6px; font-size: 0.9rem;
        }
        .form-select:disabled { opacity: 0.5; cursor: not-allowed; }
        .form-select:focus, .form-input:focus { border-color: #3b82f6; outline: none; }

        /* Divider */
        .divider-text { text-align: center; color: #64748b; font-size: 0.8rem; font-weight: bold; margin: 1.5rem 0; position: relative; }
        .divider-text::before, .divider-text::after { content: ""; position: absolute; top: 50%; width: 40%; height: 1px; background: #334155; }
        .divider-text::before { left: 0; } .divider-text::after { right: 0; }

        .btn-go {
            background: #3b82f6; color: white; border: none; padding: 10px; 
            border-radius: 6px; cursor: pointer; font-weight: bold; width: 100%;
        }
        .btn-go:hover { background: #2563eb; }

        /* Animal Profile Card */
        .profile-card {
            background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.3);
            border-radius: 12px; padding: 1.5rem; margin-bottom: 2rem;
            display: flex; justify-content: space-between; align-items: center;
        }
        .profile-main h2 { font-size: 2rem; margin: 0; color: #fff; }
        .profile-sub { color: #86efac; font-size: 0.9rem; margin-top: 5px; }
        .profile-stats { text-align: right; }
        .total-cost-label { font-size: 0.8rem; text-transform: uppercase; color: #94a3b8; }
        .total-cost-val { font-size: 1.8rem; font-weight: 800; color: #fbbf24; }

        /* Table */
        .table-wrapper { background: #1e293b; border-radius: 12px; border: 1px solid #334155; overflow: hidden; }
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th {
            text-align: left; padding: 1rem; background: #0f172a;
            color: #94a3b8; font-size: 0.8rem; text-transform: uppercase; border-bottom: 2px solid #334155;
        }
        .data-table td { padding: 1rem; border-bottom: 1px solid rgba(255,255,255,0.05); color: #cbd5e1; }
        .data-table tr:hover { background: rgba(255,255,255,0.02); }

        /* Badges */
        .type-badge { padding: 4px 10px; border-radius: 4px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; }
        .type-feeding { background: rgba(16, 185, 129, 0.15); color: #34d399; }
        .type-medication { background: rgba(239, 68, 68, 0.15); color: #f87171; }
        .type-vaccination { background: rgba(6, 182, 212, 0.15); color: #22d3ee; }
        .type-vitamins { background: rgba(236, 72, 153, 0.15); color: #f472b6; }
        .type-checkup { background: rgba(139, 92, 246, 0.15); color: #a78bfa; }

        .cost-val { font-family: monospace; font-weight: 600; color: #fbbf24; }
        
        .empty-state { text-align: center; padding: 4rem; color: #64748b; }
    </style>
</head>
<body>

<div class="container">
    <header class="page-header">
        <h1 class="page-title">Operational History</h1>
        <p class="page-desc">Comprehensive transaction log per animal.</p>
    </header>

    <div class="filter-card">
        <div class="filter-grid" style="margin-bottom: 1rem;">
            <div class="form-group">
                <label>Status Filter</label>
                <select id="status_filter" class="form-select" onchange="resetCascades()">
                    <option value="Active" selected>Active Only (Default)</option>
                    <option value="Inactive">Inactive / Sold / Deceased</option>
                    <option value="All">All Animals</option>
                </select>
            </div>
        </div>

        <div class="filter-grid">
            <div class="form-group">
                <label>1. Location</label>
                <select id="loc_id" class="form-select" onchange="loadBuildings()">
                    <option value="">-- Select --</option>
                    <?php foreach($locations as $l): ?>
                        <option value="<?= $l['LOCATION_ID'] ?>"><?= htmlspecialchars($l['LOCATION_NAME']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>2. Building</label>
                <select id="bldg_id" class="form-select" onchange="loadPens()" disabled>
                    <option value="">-- Select --</option>
                </select>
            </div>
            <div class="form-group">
                <label>3. Pen</label>
                <select id="pen_id" class="form-select" onchange="loadAnimals()" disabled>
                    <option value="">-- Select --</option>
                </select>
            </div>
            <div class="form-group">
                <label>4. Animal Tag</label>
                <select id="animal_select" class="form-select" onchange="goToAnimal(this.value)" disabled>
                    <option value="">-- Select --</option>
                </select>
            </div>
        </div>

        <div class="divider-text">OR DIRECT SEARCH</div>

        <div style="display: flex; gap: 10px;">
            <input type="text" id="direct_search" class="form-input" placeholder="Enter Tag No (e.g. A001)...">
            <button class="btn-go" style="width: auto; padding: 0 2rem;" onclick="performDirectSearch()">SEARCH</button>
        </div>
    </div>

    <?php if ($animal_info): ?>
    <div class="profile-card">
        <div class="profile-main">
            <h2><?= htmlspecialchars($animal_info['TAG_NO']) ?></h2>
            <div class="profile-sub">
                <?= htmlspecialchars($animal_info['ANIMAL_TYPE_NAME']) ?> • <?= htmlspecialchars($animal_info['BREED_NAME']) ?> • <?= $animal_info['SEX'] ?>
                <br>
                <?= htmlspecialchars($animal_info['LOCATION_NAME']) ?> > <?= htmlspecialchars($animal_info['BUILDING_NAME']) ?> > <?= htmlspecialchars($animal_info['PEN_NAME']) ?>
            </div>
        </div>
        <div class="profile-stats">
            <div class="total-cost-label">Total Operational Cost</div>
            <div class="total-cost-val">₱<?= number_format($total_cost, 2) ?></div>
            <div style="color: #64748b; font-size: 0.9rem;">Status: <?= $animal_info['CURRENT_STATUS'] ?></div>
        </div>
    </div>

    <div class="table-wrapper">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Date & Time</th>
                    <th>Type</th>
                    <th>Item / Description</th>
                    <th>Qty</th>
                    <th>Remarks</th>
                    <th>Cost</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($records)): ?>
                    <tr><td colspan="6" class="empty-state">No operational history found for this animal.</td></tr>
                <?php else: ?>
                    <?php foreach ($records as $row): 
                        $badgeClass = 'type-' . strtolower($row['LOG_TYPE']);
                    ?>
                    <tr>
                        <td><?= date('M d, Y h:i A', strtotime($row['LOG_DATE'])) ?></td>
                        <td><span class="type-badge <?= $badgeClass ?>"><?= $row['LOG_TYPE'] ?></span></td>
                        <td><?= htmlspecialchars($row['ITEM_NAME'] ?? '-') ?></td>
                        <td><?= number_format($row['QTY'], 2) ?> <?= $row['UNIT'] ?></td>
                        <td style="color:#94a3b8; font-size:0.85rem;"><?= htmlspecialchars($row['REMARKS'] ?? '-') ?></td>
                        <td class="cost-val">₱<?= number_format($row['COST'], 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
        <div class="empty-state">
            <h3>Select an animal to view history</h3>
            <p>Use the filters above or search by tag number.</p>
        </div>
    <?php endif; ?>

</div>

<script>
    // --- API & UTILS ---
    const API_URL = window.location.pathname.split("/").pop();

    async function fetchJson(params) {
        try { return await (await fetch(`${API_URL}${params}`)).json(); } 
        catch(e) { return []; }
    }

    function resetSelect(id) {
        const el = document.getElementById(id);
        el.innerHTML = '<option value="">-- Select --</option>';
        el.disabled = true;
    }

    function fillSelect(id, data, valKey, txtKey) {
        const el = document.getElementById(id);
        el.innerHTML = '<option value="">-- Select --</option>';
        data.forEach(item => el.innerHTML += `<option value="${item[valKey]}">${item[txtKey]}</option>`);
        el.disabled = false;
    }

    // --- CASCADING LOGIC ---
    function resetCascades() {
        document.getElementById('loc_id').value = "";
        resetSelect('bldg_id');
        resetSelect('pen_id');
        resetSelect('animal_select');
    }

    async function loadBuildings() {
        const id = document.getElementById('loc_id').value;
        resetSelect('bldg_id'); resetSelect('pen_id'); resetSelect('animal_select');
        if(!id) return;
        const data = await fetchJson(`?action=get_buildings&loc_id=${id}`);
        fillSelect('bldg_id', data, 'BUILDING_ID', 'BUILDING_NAME');
    }

    async function loadPens() {
        const id = document.getElementById('bldg_id').value;
        resetSelect('pen_id'); resetSelect('animal_select');
        if(!id) return;
        const data = await fetchJson(`?action=get_pens&bldg_id=${id}`);
        fillSelect('pen_id', data, 'PEN_ID', 'PEN_NAME');
    }

    async function loadAnimals() {
        const id = document.getElementById('pen_id').value;
        const status = document.getElementById('status_filter').value;
        resetSelect('animal_select');
        if(!id) return;
        
        // Pass status filter to AJAX
        const data = await fetchJson(`?action=get_animals&pen_id=${id}&status_filter=${status}`);
        fillSelect('animal_select', data, 'ANIMAL_ID', 'TAG_NO');
    }

    // --- NAVIGATION ---
    function goToAnimal(id) {
        if(id) window.location.href = `?animal_id=${id}`;
    }

    async function performDirectSearch() {
        const tag = document.getElementById('direct_search').value.trim();
        const status = document.getElementById('status_filter').value;
        if(!tag) return;

        const data = await fetchJson(`?action=search_tag&query=${encodeURIComponent(tag)}&status_filter=${status}`);
        
        if(data.length === 1) {
            // Exact match or single result -> Go directly
            goToAnimal(data[0].ANIMAL_ID);
        } else if (data.length > 1) {
            // Multiple matches (rare for unique tags, but possible with partial search)
            alert("Multiple animals found. Please be more specific.");
        } else {
            alert("Animal not found (Check your Status Filter).");
        }
    }

    // Enter key support for search
    document.getElementById('direct_search').addEventListener('keypress', function (e) {
        if (e.key === 'Enter') performDirectSearch();
    });
</script>

</body>
</html>