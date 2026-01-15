<?php
// views/animal_cost.php
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Start output buffering
ob_start();

$page = "costing";
include '../common/navbar.php';
include '../config/Connection.php';
include '../security/checkRole.php';    
checkRole(2);

// --- AJAX HANDLER FOR DROPDOWNS ---
if (isset($_GET['action'])) {
    ob_end_clean();
    ob_start();
    header('Content-Type: application/json');
    $action = $_GET['action'];

    try {
        if ($action === 'get_buildings' && isset($_GET['location_id'])) {
            $stmt = $conn->prepare("SELECT BUILDING_ID, BUILDING_NAME FROM BUILDINGS WHERE LOCATION_ID = ? ORDER BY BUILDING_NAME");
            $stmt->execute([$_GET['location_id']]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            exit;
        }
        if ($action === 'get_pens' && isset($_GET['building_id'])) {
            $stmt = $conn->prepare("SELECT PEN_ID, PEN_NAME FROM PENS WHERE BUILDING_ID = ? ORDER BY PEN_NAME");
            $stmt->execute([$_GET['building_id']]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            exit;
        }
        // New: Get Animals for Dropdown
        if ($action === 'get_animals' && isset($_GET['pen_id'])) {
            $stmt = $conn->prepare("SELECT ANIMAL_ID, TAG_NO FROM ANIMAL_RECORDS WHERE PEN_ID = ? AND IS_ACTIVE = 1 ORDER BY TAG_NO");
            $stmt->execute([$_GET['pen_id']]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            exit;
        }
    } catch (Exception $e) {
        echo json_encode([]); exit;
    }
}

// --- GET FILTERS ---
$location_id = $_GET['location_id'] ?? '';
$building_id = $_GET['building_id'] ?? '';
$pen_id      = $_GET['pen_id'] ?? '';
$animal_id   = $_GET['animal_id'] ?? ''; // From Dropdown
$search      = $_GET['search'] ?? '';    // From Search Box

// --- MAIN AGGREGATE QUERY ---
$sql = "
    SELECT 
        ar.ANIMAL_ID,
        ar.TAG_NO,
        at.ANIMAL_TYPE_NAME,
        ar.CURRENT_STATUS,
        l.LOCATION_NAME,
        b.BUILDING_NAME,
        p.PEN_NAME,
        
        -- 1. Acquisition
        COALESCE(ar.ACQUISITION_COST, 0) as COST_ACQUISITION,

        -- 2. Feed (Sum of Transaction Cost)
        (SELECT COALESCE(SUM(TRANSACTION_COST), 0) 
         FROM feed_transactions 
         WHERE ANIMAL_ID = ar.ANIMAL_ID) as COST_FEED,

        -- 3. Medical (Meds)
        (SELECT COALESCE(SUM(TOTAL_COST), 0) 
         FROM treatment_transactions 
         WHERE ANIMAL_ID = ar.ANIMAL_ID) as COST_MEDS,

        -- 4. Vaccine
        (SELECT COALESCE(SUM(VACCINATION_COST + VACCINE_COST), 0) 
         FROM vaccination_records 
         WHERE ANIMAL_ID = ar.ANIMAL_ID) as COST_VACCINE,

        -- 5. Vitamins
        (SELECT COALESCE(SUM(TOTAL_COST), 0)
         FROM vitamins_supplements_transactions 
         WHERE ANIMAL_ID = ar.ANIMAL_ID) as COST_VITAMINS,

        -- 6. Checkup Professional Fees
        (SELECT COALESCE(SUM(COST), 0) 
         FROM check_ups 
         WHERE ANIMAL_ID = ar.ANIMAL_ID) as COST_CHECKUP,

        -- 7. Count of Checkups
        (SELECT COUNT(*) 
         FROM check_ups 
         WHERE ANIMAL_ID = ar.ANIMAL_ID) as COUNT_CHECKUP

    FROM animal_records ar
    LEFT JOIN animal_type at ON ar.ANIMAL_TYPE_ID = at.ANIMAL_TYPE_ID
    LEFT JOIN locations l ON ar.LOCATION_ID = l.LOCATION_ID
    LEFT JOIN buildings b ON ar.BUILDING_ID = b.BUILDING_ID
    LEFT JOIN pens p ON ar.PEN_ID = p.PEN_ID
    WHERE ar.IS_ACTIVE = 1
";

// Apply Filters
$params = [];

if ($location_id) {
    $sql .= " AND ar.LOCATION_ID = ?";
    $params[] = $location_id;
}
if ($building_id) {
    $sql .= " AND ar.BUILDING_ID = ?";
    $params[] = $building_id;
}
if ($pen_id) {
    $sql .= " AND ar.PEN_ID = ?";
    $params[] = $pen_id;
}

// 2-WAY SEARCH LOGIC: Dropdown OR Search Box
if ($animal_id) {
    // If specific animal selected from dropdown
    $sql .= " AND ar.ANIMAL_ID = ?";
    $params[] = $animal_id;
} elseif ($search) {
    // If typing in search box (Search Tag No or Type)
    $sql .= " AND (ar.TAG_NO LIKE ? OR at.ANIMAL_TYPE_NAME LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY ar.ANIMAL_ID DESC";

try {
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch Locations for Initial Dropdown
    $locStmt = $conn->prepare("SELECT * FROM locations ORDER BY LOCATION_NAME");
    $locStmt->execute();
    $locations = $locStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $data = [];
    $error = $e->getMessage();
}

// Calculate Dashboard Totals
$total_operating = 0; // Without Acquisition
$total_net_worth = 0; // With Acquisition
$total_feed = 0;
$total_health = 0; 
$total_acquisition = 0;

foreach ($data as $row) {
    $health_sum = $row['COST_MEDS'] + $row['COST_VACCINE'] + $row['COST_VITAMINS'] + $row['COST_CHECKUP'];
    $operating_sum = $row['COST_FEED'] + $health_sum; // Expenses only
    
    $total_operating += $operating_sum;
    $total_net_worth += ($operating_sum + $row['COST_ACQUISITION']);
    
    $total_feed += $row['COST_FEED'];
    $total_health += $health_sum;
    $total_acquisition += $row['COST_ACQUISITION'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Animal Net Worth</title>
    <style>
        /* --- CORE STYLES --- */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: #e2e8f0; min-height: 100vh; }
        .container { max-width: 1600px; margin: 0 auto; padding: 2rem; }

        /* DASHBOARD */
        .dashboard-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 2rem; }
        .stat-card { background: rgba(30, 41, 59, 0.6); border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; padding: 1.5rem; }
        .stat-title { color: #94a3b8; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 10px; }
        .stat-value { font-size: 1.8rem; font-weight: 800; color: white; }
        
        .text-green { color: #34d399; }
        .text-teal { color: #2dd4bf; }
        .text-yellow { color: #fbbf24; }
        .text-pink { color: #f472b6; }
        .text-purple { color: #a78bfa; }

        /* FILTER BAR */
        .filter-bar { 
            background: rgba(15, 23, 42, 0.6); padding: 1.5rem; border-radius: 12px; 
            margin-bottom: 2rem; border: 1px solid rgba(255,255,255,0.05); 
            display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; align-items: end;
        }
        .form-group label { display: block; color: #94a3b8; font-size: 0.8rem; margin-bottom: 0.4rem; text-transform: uppercase; }
        .form-select, .form-input { width: 100%; padding: 10px; background: #1e293b; border: 1px solid #475569; color: white; border-radius: 6px; }
        .btn-filter { background: #3b82f6; color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-weight: bold; height: 40px; }
        .btn-reset { background: transparent; color: #94a3b8; border: 1px solid #475569; padding: 10px 20px; border-radius: 6px; text-decoration: none; text-align: center; height: 40px; display: inline-block; line-height: 18px; }

        /* TABLE */
        .table-container { background: rgba(30, 41, 59, 0.5); border-radius: 12px; overflow-x: auto; border: 1px solid #475569; }
        .cost-table { width: 100%; border-collapse: collapse; min-width: 1200px; }
        .cost-table th { background: rgba(15, 23, 42, 0.8); padding: 15px; text-align: left; color: #94a3b8; font-size: 0.8rem; text-transform: uppercase; border-bottom: 1px solid #475569; }
        .cost-table td { padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.05); color: #cbd5e1; vertical-align: top; font-size: 0.9rem; }
        .cost-table tr:hover { background: rgba(255,255,255,0.02); }
        
        .cost-col { font-family: 'Courier New', monospace; font-weight: 600; text-align: right; }
        .total-col-op { color: #60a5fa; font-weight: 700; text-align: right; background: rgba(59, 130, 246, 0.05); }
        .total-col-final { color: #34d399; font-weight: 800; text-align: right; background: rgba(52, 211, 153, 0.05); font-size: 1rem; border-left: 2px solid #334155; }
        
        .tag-pill { background: rgba(96, 165, 250, 0.1); color: #60a5fa; padding: 4px 8px; border-radius: 4px; font-weight: bold; }
        .detail-row { font-size: 0.8rem; color: #64748b; margin-top: 4px; }
        
        /* Breakdown Tooltip/Mini-table */
        .breakdown-grid { display: grid; grid-template-columns: 1fr auto; gap: 5px; font-size: 0.8rem; color: #94a3b8; margin-top: 5px; padding-top: 5px; border-top: 1px dashed rgba(255,255,255,0.1); }
        .cost-val { text-align: right; color: #e2e8f0; }
        
        /* OR Divider for search */
        .or-divider { text-align: center; color: #64748b; font-size: 0.8rem; font-weight: bold; padding-top: 25px; }
    </style>
</head>
<body>

<div class="container">
    <div style="text-align: center; margin-bottom: 2rem;">
        <h1 style="font-size: 2.5rem; margin-bottom: 0.5rem; background: linear-gradient(135deg, #34d399, #3b82f6); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">Animal Net Worth</h1>
        <p style="color: #94a3b8;">Financial breakdown of livestock value and operating expenses</p>
    </div>

    <div class="dashboard-grid">
        <div class="stat-card" style="border-top: 4px solid #34d399;">
            <div class="stat-title">Total Net Worth (Incl. Acq)</div>
            <div class="stat-value text-green">₱<?php echo number_format($total_net_worth, 2); ?></div>
        </div>
        <div class="stat-card" style="border-top: 4px solid #60a5fa;">
            <div class="stat-title">Operating Expenses (Excl. Acq)</div>
            <div class="stat-value text-blue">₱<?php echo number_format($total_operating, 2); ?></div>
        </div>
        <div class="stat-card" style="border-top: 4px solid #a78bfa;">
            <div class="stat-title">Total Acquisition</div>
            <div class="stat-value text-purple">₱<?php echo number_format($total_acquisition, 2); ?></div>
        </div>
        <div class="stat-card" style="border-top: 4px solid #fbbf24;">
            <div class="stat-title">Feed Consumed</div>
            <div class="stat-value text-yellow">₱<?php echo number_format($total_feed, 2); ?></div>
        </div>
        <div class="stat-card" style="border-top: 4px solid #f472b6;">
            <div class="stat-title">Medical & Health</div>
            <div class="stat-value text-pink">₱<?php echo number_format($total_health, 2); ?></div>
        </div>
    </div>

    <form class="filter-bar" method="GET">
        <div class="form-group">
            <label>1. Location</label>
            <select name="location_id" id="location_id" class="form-select" onchange="loadBuildings()">
                <option value="">-- All Locations --</option>
                <?php foreach ($locations as $loc): ?>
                    <option value="<?php echo $loc['LOCATION_ID']; ?>" <?php echo $location_id == $loc['LOCATION_ID'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($loc['LOCATION_NAME']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="form-group">
            <label>2. Building</label>
            <select name="building_id" id="building_id" class="form-select" onchange="loadPens()" <?php echo empty($location_id) ? 'disabled' : ''; ?>>
                <option value="">-- All Buildings --</option>
            </select>
        </div>

        <div class="form-group">
            <label>3. Pen</label>
            <select name="pen_id" id="pen_id" class="form-select" onchange="loadAnimals()" <?php echo empty($building_id) ? 'disabled' : ''; ?>>
                <option value="">-- All Pens --</option>
            </select>
        </div>

        <div class="form-group">
            <label>4. Select Animal (Dropdown)</label>
            <select name="animal_id" id="animal_id" class="form-select" <?php echo empty($pen_id) ? 'disabled' : ''; ?>>
                <option value="">-- Select Specific Tag --</option>
            </select>
        </div>

        <div class="or-divider">OR</div>

        <div class="form-group">
            <label>Search (Tag No)</label>
            <input type="text" name="search" class="form-input" placeholder="e.g. A001" value="<?php echo htmlspecialchars($search); ?>">
        </div>

        <div style="display: flex; gap: 10px;">
            <button type="submit" class="btn-filter">Apply Filters</button>
            <a href="animal_cost.php" class="btn-reset">Reset</a>
        </div>
    </form>

    <div class="table-container">
        <table class="cost-table">
            <thead>
                <tr>
                    <th>Animal Details</th>
                    <th style="text-align:right;">Acquisition</th>
                    <th style="text-align:right;">Feed Cost</th>
                    <th style="text-align:right;">Health Breakdown</th>
                    <th style="text-align:right; color:#60a5fa;">Operating Cost<br><span style="font-size:0.7em">(Without Acquisition)</span></th>
                    <th style="text-align:right; color:#34d399;">Total Net Worth<br><span style="font-size:0.7em">(With Acquisition)</span></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($data)): ?>
                    <tr><td colspan="6" style="text-align:center; padding: 3rem;">No animals found matching criteria.</td></tr>
                <?php else: ?>
                    <?php foreach ($data as $row): 
                        $health_subtotal = $row['COST_MEDS'] + $row['COST_VACCINE'] + $row['COST_VITAMINS'] + $row['COST_CHECKUP'];
                        $operating_total = $row['COST_FEED'] + $health_subtotal;
                        $grand_total = $operating_total + $row['COST_ACQUISITION'];
                    ?>
                    <tr>
                        <td>
                            <span class="tag-pill"><?php echo $row['TAG_NO']; ?></span>
                            <span style="font-weight:bold; margin-left:8px;"><?php echo $row['ANIMAL_TYPE_NAME']; ?></span>
                            <div class="detail-row">
                                <?php echo $row['LOCATION_NAME']; ?> &bull; 
                                <?php echo $row['BUILDING_NAME'] ?? '-'; ?> &bull; 
                                <?php echo $row['PEN_NAME'] ?? '-'; ?>
                            </div>
                            <div class="detail-row" style="color:<?php echo $row['CURRENT_STATUS']=='Active'?'#34d399':'#f87171'; ?>">
                                ● <?php echo $row['CURRENT_STATUS']; ?>
                            </div>
                        </td>
                        
                        <td class="cost-col" style="color:#a78bfa;">
                            ₱<?php echo number_format($row['COST_ACQUISITION'], 2); ?>
                        </td>
                        
                        <td class="cost-col" style="color:#fbbf24;">
                            ₱<?php echo number_format($row['COST_FEED'], 2); ?>
                        </td>
                        
                        <td class="cost-col">
                            <div style="font-weight:bold; color:#f472b6;">₱<?php echo number_format($health_subtotal, 2); ?></div>
                            <div class="breakdown-grid">
                                <span>Checkup:</span> <span class="cost-val"><?php echo number_format($row['COST_CHECKUP'], 2); ?></span>
                                <span>Meds:</span> <span class="cost-val"><?php echo number_format($row['COST_MEDS'], 2); ?></span>
                                <span>Vaccine:</span> <span class="cost-val"><?php echo number_format($row['COST_VACCINE'], 2); ?></span>
                                <span>Vitamins:</span> <span class="cost-val"><?php echo number_format($row['COST_VITAMINS'], 2); ?></span>
                            </div>
                        </td>

                        <td class="cost-col total-col-op">
                            ₱<?php echo number_format($operating_total, 2); ?>
                        </td>

                        <td class="total-col-final">
                            ₱<?php echo number_format($grand_total, 2); ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    // Pre-select values if page reloaded with filters
    const selectedLocation = "<?php echo $location_id; ?>";
    const selectedBuilding = "<?php echo $building_id; ?>";
    const selectedPen = "<?php echo $pen_id; ?>";
    const selectedAnimal = "<?php echo $animal_id; ?>";

    document.addEventListener('DOMContentLoaded', async () => {
        if (selectedLocation) {
            await loadBuildings();
            if (selectedBuilding) {
                document.getElementById('building_id').value = selectedBuilding;
                await loadPens();
                if (selectedPen) {
                    document.getElementById('pen_id').value = selectedPen;
                    await loadAnimals();
                    if(selectedAnimal) {
                        document.getElementById('animal_id').value = selectedAnimal;
                    }
                }
            }
        }
    });

    async function fetchData(url) {
        try {
            const res = await fetch(url);
            return await res.json();
        } catch(e) { console.error(e); return []; }
    }

    async function loadBuildings() {
        const locId = document.getElementById('location_id').value;
        const buildSelect = document.getElementById('building_id');
        const penSelect = document.getElementById('pen_id');
        const animalSelect = document.getElementById('animal_id');

        buildSelect.innerHTML = '<option value="">Loading...</option>';
        penSelect.innerHTML = '<option value="">-- All Pens --</option>';
        animalSelect.innerHTML = '<option value="">-- Select Specific Tag --</option>';
        
        buildSelect.disabled = true; 
        penSelect.disabled = true;
        animalSelect.disabled = true;

        if (locId) {
            const data = await fetchData(`animal_cost.php?action=get_buildings&location_id=${locId}`);
            buildSelect.innerHTML = '<option value="">-- All Buildings --</option>';
            data.forEach(b => {
                buildSelect.innerHTML += `<option value="${b.BUILDING_ID}">${b.BUILDING_NAME}</option>`;
            });
            buildSelect.disabled = false;
        } else {
            buildSelect.innerHTML = '<option value="">-- All Buildings --</option>';
        }
    }

    async function loadPens() {
        const buildId = document.getElementById('building_id').value;
        const penSelect = document.getElementById('pen_id');
        const animalSelect = document.getElementById('animal_id');

        penSelect.innerHTML = '<option value="">Loading...</option>';
        animalSelect.innerHTML = '<option value="">-- Select Specific Tag --</option>';
        penSelect.disabled = true;
        animalSelect.disabled = true;

        if (buildId) {
            const data = await fetchData(`animal_cost.php?action=get_pens&building_id=${buildId}`);
            penSelect.innerHTML = '<option value="">-- All Pens --</option>';
            data.forEach(p => {
                penSelect.innerHTML += `<option value="${p.PEN_ID}">${p.PEN_NAME}</option>`;
            });
            penSelect.disabled = false;
        } else {
            penSelect.innerHTML = '<option value="">-- All Pens --</option>';
        }
    }

    async function loadAnimals() {
        const penId = document.getElementById('pen_id').value;
        const animalSelect = document.getElementById('animal_id');

        animalSelect.innerHTML = '<option value="">Loading...</option>';
        animalSelect.disabled = true;

        if (penId) {
            const data = await fetchData(`animal_cost.php?action=get_animals&pen_id=${penId}`);
            animalSelect.innerHTML = '<option value="">-- Select Specific Tag --</option>';
            data.forEach(a => {
                animalSelect.innerHTML += `<option value="${a.ANIMAL_ID}">${a.TAG_NO}</option>`;
            });
            animalSelect.disabled = false;
        } else {
            animalSelect.innerHTML = '<option value="">-- Select Specific Tag --</option>';
        }
    }
</script>

</body>
</html>