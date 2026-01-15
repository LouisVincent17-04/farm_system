<?php
// views/costing_medication.php
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Start output buffering immediately
ob_start();

$page = "costing";
include '../common/navbar.php';
include '../security/checkRole.php';
include '../config/Connection.php';

checkRole(3);

// --- AJAX HANDLER ---
if (isset($_GET['action'])) {
    // Discard any output from included files
    ob_end_clean();
    
    // Start fresh buffer for JSON only
    ob_start();
    header('Content-Type: application/json');
    
    $action = $_GET['action'];
    $response = [];
    
    try {

        if ($action === 'get_buildings' && isset($_GET['location_id'])) {
            $stmt = $conn->prepare("SELECT BUILDING_ID, BUILDING_NAME FROM BUILDINGS WHERE LOCATION_ID = ?");
            $stmt->execute([$_GET['location_id']]);
            $response = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode($response);
            exit;
        }

        if ($action === 'get_pens' && isset($_GET['building_id'])) {
            $stmt = $conn->prepare("SELECT PEN_ID, PEN_NAME FROM PENS WHERE BUILDING_ID = ?");
            $stmt->execute([$_GET['building_id']]);
            $response = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode($response);
            exit;
        }

        if ($action === 'get_animals' && isset($_GET['pen_id'])) {
            $stmt = $conn->prepare("SELECT ANIMAL_ID, TAG_NO FROM ANIMAL_RECORDS WHERE PEN_ID = ? AND IS_ACTIVE = 1");
            $stmt->execute([$_GET['pen_id']]);
            $response = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode($response);
            exit;
        }

        // --- GET PEN HISTORY (ALL ANIMALS IN PEN) ---
        if ($action === 'get_pen_history' && isset($_GET['pen_id'])) {
            // UPDATED QUERY: Use TOTAL_COST directly from the transaction table
            $query = "SELECT 
                        ar.TAG_NO,
                        tt.TRANSACTION_DATE, 
                        m.SUPPLY_NAME as MEDICINE_NAME, 
                        tt.QUANTITY_USED, 
                        tt.TOTAL_COST as CALCULATED_COST, 
                        tt.REMARKS 
                      FROM TREATMENT_TRANSACTIONS tt
                      JOIN MEDICINES m ON tt.ITEM_ID = m.SUPPLY_ID
                      JOIN ANIMAL_RECORDS ar ON tt.ANIMAL_ID = ar.ANIMAL_ID
                      WHERE ar.PEN_ID = ?
                      ORDER BY tt.TRANSACTION_DATE DESC";
            
            $stmt = $conn->prepare($query);
            $stmt->execute([$_GET['pen_id']]);
            
            $html = "";
            $total_cost = 0;
            
            if ($stmt->rowCount() > 0) {
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $date = date("M d, Y h:i A", strtotime($row['TRANSACTION_DATE']));
                    $cost = number_format($row['CALCULATED_COST'], 2);
                    $total_cost += $row['CALCULATED_COST'];
                    
                    $html .= "<tr>
                                <td><span style='background: rgba(59, 130, 246, 0.2); padding: 4px 8px; border-radius: 4px; font-weight: 600; color: #60a5fa; white-space: nowrap;'>{$row['TAG_NO']}</span></td>
                                <td style='white-space: nowrap;'>{$date}</td>
                                <td>{$row['MEDICINE_NAME']}</td>
                                <td style='white-space: nowrap;'>{$row['QUANTITY_USED']} units</td>
                                <td style='color: #60a5fa; font-weight: bold; white-space: nowrap;'>₱ {$cost}</td>
                                <td>{$row['REMARKS']}</td>
                              </tr>";
                }
            } else {
                $html = "<tr><td colspan='6' style='text-align:center; padding: 2rem;'>No medication history found for this pen.</td></tr>";
            }

            echo json_encode(['html' => $html, 'total' => number_format($total_cost, 2)]);
            exit;
        }

        // --- GET HISTORY FOR SINGLE ANIMAL ---
        if ($action === 'get_history' && isset($_GET['animal_id'])) {
            // UPDATED QUERY: Use TOTAL_COST directly
            $query = "SELECT 
                        tt.TRANSACTION_DATE, 
                        m.SUPPLY_NAME as MEDICINE_NAME, 
                        tt.QUANTITY_USED, 
                        tt.TOTAL_COST as CALCULATED_COST, 
                        tt.REMARKS 
                      FROM TREATMENT_TRANSACTIONS tt
                      JOIN MEDICINES m ON tt.ITEM_ID = m.SUPPLY_ID
                      WHERE tt.ANIMAL_ID = ?
                      ORDER BY tt.TRANSACTION_DATE DESC";
            
            $stmt = $conn->prepare($query);
            $stmt->execute([$_GET['animal_id']]);
            
            $html = "";
            $total_cost = 0;
            
            if ($stmt->rowCount() > 0) {
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $date = date("M d, Y h:i A", strtotime($row['TRANSACTION_DATE']));
                    $cost = number_format($row['CALCULATED_COST'], 2);
                    $total_cost += $row['CALCULATED_COST'];
                    
                    $html .= "<tr>
                                <td style='white-space: nowrap;'>{$date}</td>
                                <td>{$row['MEDICINE_NAME']}</td>
                                <td style='white-space: nowrap;'>{$row['QUANTITY_USED']} units</td>
                                <td style='color: #60a5fa; font-weight: bold; white-space: nowrap;'>₱ {$cost}</td>
                                <td>{$row['REMARKS']}</td>
                              </tr>";
                }
            } else {
                $html = "<tr><td colspan='5' style='text-align:center; padding: 2rem;'>No medication history found for this animal.</td></tr>";
            }

            echo json_encode(['html' => $html, 'total' => number_format($total_cost, 2)]);
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
    <title>Medication Cost History - FarmPro</title>
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
            background: linear-gradient(135deg, #3b82f6, #2563eb); /* Blue for Medical */
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }

        .filter-container {
            background: rgba(30, 41, 59, 0.6);
            border: 1px solid rgba(59, 130, 246, 0.2); /* Blue Border */
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
            border: 1px solid rgba(59, 130, 246, 0.3);
            border-radius: 8px;
            color: #e2e8f0;
            font-size: 1rem;
            cursor: pointer;
            outline: none;
            transition: border-color 0.3s;
            width: 100%;
        }
        .form-select:focus { border-color: #3b82f6; }
        .form-select:disabled { opacity: 0.5; cursor: not-allowed; }

        .results-section {
            background: rgba(30, 41, 59, 0.4);
            border-radius: 16px;
            overflow: hidden; /* Important for containing the scroll */
            display: none;
            animation: fadeIn 0.5s ease;
        }

        .table-header-box {
            padding: 1.5rem;
            background: rgba(59, 130, 246, 0.1);
            border-bottom: 1px solid rgba(59, 130, 246, 0.2);
            display: flex; justify-content: space-between; align-items: center;
            flex-wrap: wrap; /* Allow wrapping on small screens */
            gap: 10px;
        }

        /* SCROLL WRAPPER */
        .table-scroll-wrapper {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .data-table { 
            width: 100%; 
            border-collapse: collapse; 
            min-width: 800px; /* Ensure table is wide enough to scroll */
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
            border-top: 1px solid rgba(59, 130, 246, 0.2);
            background: rgba(15, 23, 42, 0.3);
        }

        .view-mode-badge {
            background: rgba(59, 130, 246, 0.2);
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 0.9rem;
            color: #60a5fa;
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
        <h1 class="page-title">Medication Cost History</h1>
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
            <h3>Medication Transactions</h3>
            <div style="display: flex; gap: 1rem; align-items: center; flex-wrap: wrap;">
                <span id="viewModeBadge" class="view-mode-badge"></span>
                <div id="selectedTagDisplay" style="color: #60a5fa; font-weight: bold;"></div>
            </div>
        </div>
        
        <div class="table-scroll-wrapper">
            <table class="data-table">
                <thead id="tableHeader">
                    <tr>
                        <th>Date & Time</th>
                        <th>Medicine Name</th>
                        <th>Qty Used</th>
                        <th>Cost</th>
                        <th>Remarks</th>
                    </tr>
                </thead>
                <tbody id="historyTableBody">
                </tbody>
            </table>
        </div>

        <div class="total-box">
            Total Medical Cost: <span style="color: #60a5fa; font-weight: bold; margin-left: 10px;">₱ <span id="grandTotal">0.00</span></span>
        </div>
    </div>

</div>

<script>
    async function fetchData(params) {
        try {
            const response = await fetch(`costing_medication.php?${params}`);
            const text = await response.text();
            
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error('Invalid JSON received:', text);
                return [];
            }
        } catch (error) {
            console.error('Fetch error:', error);
            return [];
        }
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
            data.forEach(item => {
                buildSelect.innerHTML += `<option value="${item.BUILDING_ID}">${item.BUILDING_NAME}</option>`;
            });
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
            data.forEach(item => {
                penSelect.innerHTML += `<option value="${item.PEN_ID}">${item.PEN_NAME}</option>`;
            });
            penSelect.disabled = false;
        } else {
            penSelect.innerHTML = '<option value="">-- Select Building First --</option>';
        }
    }

    // Load all animals in pen AND show pen history
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

        // Load animals for the dropdown
        animalSelect.innerHTML = '<option value="">Loading...</option>';
        animalSelect.disabled = true;
        
        const animals = await fetchData(`action=get_animals&pen_id=${penId}`);
        animalSelect.innerHTML = '<option value="">-- All Animals in Pen --</option>';
        animals.forEach(item => {
            animalSelect.innerHTML += `<option value="${item.ANIMAL_ID}">Tag: ${item.TAG_NO}</option>`;
        });
        animalSelect.disabled = false;

        // Show pen-level history (all animals)
        const penText = penSelect.options[penSelect.selectedIndex].text;
        tagDisplay.textContent = penText;
        viewModeBadge.textContent = '📊 Pen View (All Animals)';

        // Update table header to include Animal Tag column
        tableHeader.innerHTML = `
            <tr>
                <th>Animal Tag</th>
                <th>Date & Time</th>
                <th>Medicine Name</th>
                <th>Qty Used</th>
                <th>Cost</th>
                <th>Remarks</th>
            </tr>
        `;

        const response = await fetchData(`action=get_pen_history&pen_id=${penId}`);
        
        tableBody.innerHTML = response.html;
        grandTotal.textContent = response.total;
        resultsArea.style.display = 'block';
    }

    // Load history for single animal
    async function loadHistory() {
        const animalId = document.getElementById('selAnimal').value;
        const animalSelect = document.getElementById('selAnimal');
        const resultsArea = document.getElementById('resultsArea');
        const tableBody = document.getElementById('historyTableBody');
        const grandTotal = document.getElementById('grandTotal');
        const tagDisplay = document.getElementById('selectedTagDisplay');
        const viewModeBadge = document.getElementById('viewModeBadge');
        const tableHeader = document.getElementById('tableHeader');

        if (!animalId) {
            // If "All Animals" is selected, reload pen history
            loadPenHistory();
            return;
        }

        // Single animal view
        const tagText = animalSelect.options[animalSelect.selectedIndex].text;
        tagDisplay.textContent = tagText;
        viewModeBadge.textContent = '💉 Single Animal View';

        // Update table header (no Animal Tag column needed)
        tableHeader.innerHTML = `
            <tr>
                <th>Date & Time</th>
                <th>Medicine Name</th>
                <th>Qty Used</th>
                <th>Cost</th>
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