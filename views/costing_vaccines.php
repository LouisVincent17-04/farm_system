<?php
// views/costing_vaccines.php
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Start output buffering
ob_start();

$page = "costing";
include '../common/navbar.php';
include '../security/checkRole.php';
include '../config/Connection.php';

checkRole(3);

// --- AJAX HANDLER ---
if (isset($_GET['action'])) {
    ob_end_clean(); 
    ob_start(); 
    header('Content-Type: application/json');
    
    $action = $_GET['action'];
    
    try {

        // Standard dropdown handlers
        if ($action === 'get_buildings' && isset($_GET['location_id'])) {
            $stmt = $conn->prepare("SELECT BUILDING_ID, BUILDING_NAME FROM BUILDINGS WHERE LOCATION_ID = ?");
            $stmt->execute([$_GET['location_id']]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            exit;
        }

        if ($action === 'get_pens' && isset($_GET['building_id'])) {
            $stmt = $conn->prepare("SELECT PEN_ID, PEN_NAME FROM PENS WHERE BUILDING_ID = ?");
            $stmt->execute([$_GET['building_id']]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            exit;
        }

        if ($action === 'get_animals' && isset($_GET['pen_id'])) {
            $stmt = $conn->prepare("SELECT ANIMAL_ID, TAG_NO FROM ANIMAL_RECORDS WHERE PEN_ID = ? AND IS_ACTIVE = 1");
            $stmt->execute([$_GET['pen_id']]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            exit;
        }

        // --- 1. GET PEN HISTORY (ALL ANIMALS) ---
        if ($action === 'get_pen_history' && isset($_GET['pen_id'])) {
            $query = "SELECT 
                        ar.TAG_NO,
                        vr.VACCINATION_DATE, 
                        v.SUPPLY_NAME as VACCINE_NAME, 
                        vr.QUANTITY, 
                        vr.VACCINATION_COST,
                        vr.VACCINE_COST,
                        vr.REMARKS 
                      FROM VACCINATION_RECORDS vr
                      JOIN VACCINES v ON vr.VACCINE_ITEM_ID = v.SUPPLY_ID
                      JOIN ANIMAL_RECORDS ar ON vr.ANIMAL_ID = ar.ANIMAL_ID
                      WHERE ar.PEN_ID = ?
                      ORDER BY vr.VACCINATION_DATE DESC";
            
            $stmt = $conn->prepare($query);
            $stmt->execute([$_GET['pen_id']]);
            
            $html = "";
            $total_overall_cost = 0;
            
            if ($stmt->rowCount() > 0) {
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $date = date("M d, Y", strtotime($row['VACCINATION_DATE']));
                    
                    // CALCULATE TOTAL: Vaccine Cost + Service Cost
                    $vac_cost = floatval($row['VACCINE_COST']);
                    $svc_cost = floatval($row['VACCINATION_COST']);
                    $row_total = $vac_cost + $svc_cost;
                    
                    $total_overall_cost += $row_total;
                    
                    $html .= "<tr>
                                <td><span style='background: rgba(236, 72, 153, 0.2); padding: 4px 8px; border-radius: 4px; font-weight: 600; color: #ec4899; white-space: nowrap;'>{$row['TAG_NO']}</span></td>
                                <td style='white-space: nowrap;'>{$date}</td>
                                <td>{$row['VACCINE_NAME']}</td>
                                <td style='white-space: nowrap;'>{$row['QUANTITY']} ml</td>
                                <td style='white-space: nowrap;'>₱ " . number_format($vac_cost, 2) . "</td>
                                <td style='white-space: nowrap;'>₱ " . number_format($svc_cost, 2) . "</td>
                                <td style='color: #ec4899; font-weight: bold; white-space: nowrap;'>₱ " . number_format($row_total, 2) . "</td>
                                <td>{$row['REMARKS']}</td>
                              </tr>";
                }
            } else {
                $html = "<tr><td colspan='8' style='text-align:center; padding: 2rem;'>No vaccination history found for this pen.</td></tr>";
            }

            echo json_encode(['html' => $html, 'total' => number_format($total_overall_cost, 2)]);
            exit;
        }

        // --- 2. GET SINGLE ANIMAL HISTORY ---
        if ($action === 'get_history' && isset($_GET['animal_id'])) {
            $query = "SELECT 
                        vr.VACCINATION_DATE, 
                        v.SUPPLY_NAME as VACCINE_NAME, 
                        vr.QUANTITY, 
                        vr.VACCINATION_COST,
                        vr.VACCINE_COST,
                        vr.REMARKS 
                      FROM VACCINATION_RECORDS vr
                      JOIN VACCINES v ON vr.VACCINE_ITEM_ID = v.SUPPLY_ID
                      WHERE vr.ANIMAL_ID = ?
                      ORDER BY vr.VACCINATION_DATE DESC";
            
            $stmt = $conn->prepare($query);
            $stmt->execute([$_GET['animal_id']]);
            
            $html = "";
            $total_overall_cost = 0;
            
            if ($stmt->rowCount() > 0) {
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $date = date("M d, Y", strtotime($row['VACCINATION_DATE']));
                    
                    // CALCULATE TOTAL: Vaccine Cost + Service Cost
                    $vac_cost = floatval($row['VACCINE_COST']);
                    $svc_cost = floatval($row['VACCINATION_COST']);
                    $row_total = $vac_cost + $svc_cost; 
                    
                    $total_overall_cost += $row_total;
                    
                    $html .= "<tr>
                                <td style='white-space: nowrap;'>{$date}</td>
                                <td>{$row['VACCINE_NAME']}</td>
                                <td style='white-space: nowrap;'>{$row['QUANTITY']} ml</td>
                                <td style='white-space: nowrap;'>₱ " . number_format($vac_cost, 2) . "</td>
                                <td style='white-space: nowrap;'>₱ " . number_format($svc_cost, 2) . "</td>
                                <td style='color: #ec4899; font-weight: bold; white-space: nowrap;'>₱ " . number_format($row_total, 2) . "</td>
                                <td>{$row['REMARKS']}</td>
                              </tr>";
                }
            } else {
                $html = "<tr><td colspan='7' style='text-align:center; padding: 2rem;'>No vaccination history found for this animal.</td></tr>";
            }

            echo json_encode(['html' => $html, 'total' => number_format($total_overall_cost, 2)]);
            exit;
        }
    
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
        exit;
    }
}

// Fetch Initial Locations
$locations = $conn->query("SELECT LOCATION_ID, LOCATION_NAME FROM LOCATIONS");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vaccine Cost History - FarmPro</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: #e2e8f0;
            min-height: 100vh;
        }
        .container { max-width: 1400px; margin: 0 auto; padding: 2rem; }
        
        .page-header { text-align: center; margin-bottom: 2rem; }
        .page-title {
            font-size: 2.5rem; font-weight: bold; margin-bottom: 0.5rem;
            background: linear-gradient(135deg, #ec4899, #db2777); /* Pink for Vaccines */
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }

        /* Responsive Grid for Filters */
        .filter-container {
            background: rgba(30, 41, 59, 0.6);
            border: 1px solid rgba(236, 72, 153, 0.2); /* Pink Border */
            border-radius: 16px;
            padding: 2rem;
            backdrop-filter: blur(10px);
            margin-bottom: 2rem;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
        }

        .form-group { display: flex; flex-direction: column; gap: 0.5rem; }
        .form-label { color: #94a3b8; font-size: 0.9rem; font-weight: 600; text-transform: uppercase; }
        
        .form-select {
            padding: 0.8rem;
            background: rgba(15, 23, 42, 0.8);
            border: 1px solid rgba(236, 72, 153, 0.3);
            border-radius: 8px;
            color: #e2e8f0;
            font-size: 1rem;
            cursor: pointer;
            outline: none;
            transition: border-color 0.3s;
            width: 100%;
        }
        .form-select:focus { border-color: #ec4899; }
        .form-select:disabled { opacity: 0.5; cursor: not-allowed; }

        .results-section {
            background: rgba(30, 41, 59, 0.4);
            border-radius: 16px;
            overflow: hidden; /* Important for containing the scroll wrapper */
            display: none;
            animation: fadeIn 0.5s ease;
        }

        .table-header-box {
            padding: 1.5rem;
            background: rgba(236, 72, 153, 0.1);
            border-bottom: 1px solid rgba(236, 72, 153, 0.2);
            display: flex; justify-content: space-between; align-items: center;
            flex-wrap: wrap; 
            gap: 10px;
        }

        /* SCROLL WRAPPER FOR MOBILE */
        .table-scroll-wrapper {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .data-table { 
            width: 100%; 
            border-collapse: collapse; 
            min-width: 900px; /* Ensure wide enough to scroll */
        }
        
        .data-table th {
            text-align: left; padding: 1rem;
            background: rgba(15, 23, 42, 0.6);
            color: #94a3b8; font-weight: 600; text-transform: uppercase; font-size: 0.85rem;
            white-space: nowrap;
        }
        .data-table td {
            padding: 1rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            color: #cbd5e1;
        }
        .data-table tr:hover { background: rgba(255, 255, 255, 0.02); }

        .total-box {
            text-align: right; padding: 1.5rem;
            font-size: 1.2rem; color: #fff;
            border-top: 1px solid rgba(236, 72, 153, 0.2);
            background: rgba(15, 23, 42, 0.3);
        }

        .view-mode-badge {
            background: rgba(236, 72, 153, 0.2);
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 0.9rem;
            color: #ec4899;
            font-weight: 600;
            white-space: nowrap;
        }

        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

        /* Mobile Adjustments */
        @media (max-width: 768px) {
            .container { padding: 1rem; }
            .page-title { font-size: 1.8rem; }
            .filter-container { padding: 1.5rem; gap: 1rem; grid-template-columns: 1fr; }
            .table-header-box { flex-direction: column; align-items: flex-start; }
        }
    </style>
</head>
<body>

<div class="container">
    <header class="page-header">
        <h1 class="page-title">Vaccination Cost History</h1>
        <p style="color: #94a3b8;">Select pen to view all animals, or select specific animal</p>
    </header>

    <div class="filter-container">
        
        <div class="form-group">
            <label class="form-label">1. Select Location</label>
            <select id="selLocation" class="form-select" onchange="loadBuildings()">
                <option value="">-- Choose Location --</option>
                <?php while($row = $locations->fetch(PDO::FETCH_ASSOC)): ?>
                    <option value="<?= $row['LOCATION_ID'] ?>"><?= $row['LOCATION_NAME'] ?></option>
                <?php endwhile; ?>
            </select>
        </div>

        <div class="form-group">
            <label class="form-label">2. Select Building</label>
            <select id="selBuilding" class="form-select" onchange="loadPens()" disabled>
                <option value="">-- Select Location First --</option>
            </select>
        </div>

        <div class="form-group">
            <label class="form-label">3. Select Pen</label>
            <select id="selPen" class="form-select" onchange="loadPenHistory()" disabled>
                <option value="">-- Select Building First --</option>
            </select>
        </div>

        <div class="form-group">
            <label class="form-label">4. Filter by Animal (Optional)</label>
            <select id="selAnimal" class="form-select" onchange="loadHistory()" disabled>
                <option value="">-- All Animals in Pen --</option>
            </select>
        </div>

    </div>

    <div id="resultsArea" class="results-section">
        <div class="table-header-box">
            <h3>Vaccination Transactions</h3>
            <div style="display: flex; gap: 1rem; align-items: center; flex-wrap: wrap;">
                <span id="viewModeBadge" class="view-mode-badge"></span>
                <div id="selectedTagDisplay" style="color: #ec4899; font-weight: bold;"></div>
            </div>
        </div>
        
        <div class="table-scroll-wrapper">
            <table class="data-table">
                <thead id="tableHeader">
                    </thead>
                <tbody id="historyTableBody">
                </tbody>
            </table>
        </div>

        <div class="total-box">
            Total Vaccination Cost: <span style="color: #ec4899; font-weight: bold; margin-left: 10px;">₱ <span id="grandTotal">0.00</span></span>
        </div>
    </div>

</div>

<script>
    async function fetchData(params) {
        try {
            const response = await fetch(`costing_vaccines.php?${params}`);
            const text = await response.text();
            try { return JSON.parse(text); } 
            catch (e) { console.error('Invalid JSON:', text); return []; }
        } catch (error) { console.error('Fetch error:', error); return []; }
    }

    async function loadBuildings() {
        const locationId = document.getElementById('selLocation').value;
        const buildSelect = document.getElementById('selBuilding');
        const penSelect = document.getElementById('selPen');
        const animalSelect = document.getElementById('selAnimal');
        const resultsArea = document.getElementById('resultsArea');

        buildSelect.innerHTML = '<option value="">Loading...</option>';
        penSelect.innerHTML = '<option value="">-- Select Building First --</option>';
        animalSelect.innerHTML = '<option value="">-- All Animals in Pen --</option>';
        buildSelect.disabled = true; penSelect.disabled = true; animalSelect.disabled = true;
        resultsArea.style.display = 'none';

        if (locationId) {
            const data = await fetchData(`action=get_buildings&location_id=${locationId}`);
            buildSelect.innerHTML = '<option value="">-- Choose Building --</option>';
            data.forEach(item => buildSelect.innerHTML += `<option value="${item.BUILDING_ID}">${item.BUILDING_NAME}</option>`);
            buildSelect.disabled = false;
        } else {
            buildSelect.innerHTML = '<option value="">-- Select Location First --</option>';
        }
    }

    async function loadPens() {
        const buildId = document.getElementById('selBuilding').value;
        const penSelect = document.getElementById('selPen');
        const animalSelect = document.getElementById('selAnimal');
        const resultsArea = document.getElementById('resultsArea');

        penSelect.innerHTML = '<option value="">Loading...</option>';
        animalSelect.innerHTML = '<option value="">-- All Animals in Pen --</option>';
        penSelect.disabled = true; animalSelect.disabled = true;
        resultsArea.style.display = 'none';

        if (buildId) {
            const data = await fetchData(`action=get_pens&building_id=${buildId}`);
            penSelect.innerHTML = '<option value="">-- Choose Pen --</option>';
            data.forEach(item => penSelect.innerHTML += `<option value="${item.PEN_ID}">${item.PEN_NAME}</option>`);
            penSelect.disabled = false;
        } else {
            penSelect.innerHTML = '<option value="">-- Select Building First --</option>';
        }
    }

    async function loadPenHistory() {
        const penId = document.getElementById('selPen').value;
        const penSelect = document.getElementById('selPen');
        const animalSelect = document.getElementById('selAnimal');
        const resultsArea = document.getElementById('resultsArea');
        const tableBody = document.getElementById('historyTableBody');
        const grandTotal = document.getElementById('grandTotal');
        const tagDisplay = document.getElementById('selectedTagDisplay');
        const viewModeBadge = document.getElementById('viewModeBadge');
        const tableHeader = document.getElementById('tableHeader');

        if (!penId) {
            resultsArea.style.display = 'none';
            animalSelect.disabled = true;
            animalSelect.innerHTML = '<option value="">-- All Animals in Pen --</option>';
            return;
        }

        // Load Animals
        const animals = await fetchData(`action=get_animals&pen_id=${penId}`);
        animalSelect.innerHTML = '<option value="">-- All Animals in Pen --</option>';
        animals.forEach(item => animalSelect.innerHTML += `<option value="${item.ANIMAL_ID}">Tag: ${item.TAG_NO}</option>`);
        animalSelect.disabled = false;

        document.getElementById('selectedTagDisplay').textContent = penSelect.options[penSelect.selectedIndex].text;
        document.getElementById('viewModeBadge').textContent = '📊 Pen View (All Animals)';

        // UPDATED HEADERS
        tableHeader.innerHTML = `
            <tr>
                <th>Animal Tag</th>
                <th>Date</th>
                <th>Vaccine Name</th>
                <th>Dosage</th>
                <th>Vaccine Cost</th>
                <th>Service Fee</th>
                <th>Total Cost</th>
                <th>Remarks</th>
            </tr>
        `;

        const response = await fetchData(`action=get_pen_history&pen_id=${penId}`);
        tableBody.innerHTML = response.html;
        grandTotal.textContent = response.total;
        resultsArea.style.display = 'block';
    }

    async function loadHistory() {
        const animalId = document.getElementById('selAnimal').value;
        const animalSelect = document.getElementById('selAnimal');
        const resultsArea = document.getElementById('resultsArea');
        const tableBody = document.getElementById('historyTableBody');
        const grandTotal = document.getElementById('grandTotal');
        const tagDisplay = document.getElementById('selectedTagDisplay');
        const viewModeBadge = document.getElementById('viewModeBadge');
        const tableHeader = document.getElementById('tableHeader');

        if (!animalId) { loadPenHistory(); return; }

        document.getElementById('selectedTagDisplay').textContent = animalSelect.options[animalSelect.selectedIndex].text;
        document.getElementById('viewModeBadge').textContent = '💉 Single Animal View';

        // UPDATED HEADERS
        tableHeader.innerHTML = `
            <tr>
                <th>Date</th>
                <th>Vaccine Name</th>
                <th>Dosage</th>
                <th>Vaccine Cost</th>
                <th>Service Fee</th>
                <th>Total Cost</th>
                <th>Remarks</th>
            </tr>
        `;

        const response = await fetchData(`action=get_history&animal_id=${animalId}`);
        tableBody.innerHTML = response.html;
        grandTotal.textContent = response.total;
        resultsArea.style.display = 'block';
    }
</script>

</body>
</html>