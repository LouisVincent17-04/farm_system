<?php
// views/group_animal_sales.php
ob_start(); 
error_reporting(E_ALL);
ini_set('display_errors', 0); 

$page = "farm";
include '../common/navbar.php';
include '../config/Connection.php';
include '../security/checkRole.php';    
checkRole(2);

// =========================================================
// 1. AJAX HANDLER
// =========================================================
if (isset($_GET['action'])) {
    ob_end_clean(); 
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

        if ($action === 'get_pen_batch_data' && isset($_GET['pen_id'])) {
            $pen_id = $_GET['pen_id'];
            
            $sql = "
                SELECT 
                    a.ANIMAL_ID, a.TAG_NO, a.CURRENT_ACTUAL_WEIGHT, a.ACQUISITION_COST,
                    COALESCE((SELECT SUM(TRANSACTION_COST) FROM FEED_TRANSACTIONS WHERE ANIMAL_ID = a.ANIMAL_ID), 0) as cost_feed,
                    COALESCE((SELECT SUM(TOTAL_COST) FROM TREATMENT_TRANSACTIONS WHERE ANIMAL_ID = a.ANIMAL_ID), 0) as cost_med,
                    COALESCE((SELECT SUM(VACCINATION_COST + VACCINE_COST) FROM VACCINATION_RECORDS WHERE ANIMAL_ID = a.ANIMAL_ID), 0) as cost_vac,
                    COALESCE((SELECT SUM(TOTAL_COST) FROM VITAMINS_SUPPLEMENTS_TRANSACTIONS WHERE ANIMAL_ID = a.ANIMAL_ID), 0) as cost_vit,
                    COALESCE((SELECT SUM(COST) FROM CHECK_UPS WHERE ANIMAL_ID = a.ANIMAL_ID), 0) as cost_chk
                FROM ANIMAL_RECORDS a
                WHERE a.PEN_ID = ? AND a.IS_ACTIVE = 1 AND a.CURRENT_STATUS != 'Sold'
                ORDER BY a.TAG_NO
            ";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute([$pen_id]);
            $animals = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'animals' => $animals]); 
            exit;
        }

    } catch (Exception $e) { 
        echo json_encode(['error' => $e->getMessage()]); 
        exit; 
    }
}

// =========================================================
// 2. PAGE INITIALIZATION
// =========================================================
$locations = $conn->query("SELECT * FROM LOCATIONS ORDER BY LOCATION_NAME")->fetchAll(PDO::FETCH_ASSOC);

$statsStmt = $conn->query("
    SELECT 
        COUNT(*) as total_sold_today,
        COALESCE(SUM(final_sale_price), 0) as total_rev 
    FROM ANIMAL_SALES 
    WHERE DATE(sale_date) = CURDATE()
");
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Group Sales</title>
    <style>
        /* --- GLOBAL STYLES --- */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            min-height: 100vh; color: #e2e8f0;
        }
        .container { max-width: 1600px; margin: 0 auto; padding: 1.5rem; }

        /* --- LAYOUT GRID --- */
        .main-grid {
            display: grid;
            grid-template-columns: 380px 1fr;
            gap: 1.5rem;
            align-items: start;
        }

        /* --- LEFT PANEL: CONTROL --- */
        .control-panel {
            background: rgba(30, 41, 59, 0.7);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(16, 185, 129, 0.2); 
            border-radius: 16px;
            padding: 1.5rem;
            position: sticky; top: 1.5rem;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3);
        }
        .panel-title { font-size: 1.25rem; font-weight: 700; color: #fff; margin-bottom: 5px; display: flex; align-items: center; gap: 8px; }
        .form-group { margin-bottom: 1rem; }
        .form-label { display: block; font-size: 0.85rem; color: #cbd5e1; margin-bottom: 0.4rem; font-weight: 500; }
        .form-control, .form-select, .form-input, .form-textarea {
            width: 100%; padding: 0.75rem;
            background: #0f172a; border: 1px solid #334155;
            border-radius: 8px; color: #fff; font-size: 0.95rem;
            transition: border-color 0.2s;
        }
        .form-control:focus, .form-select:focus, .form-input:focus { border-color: #10b981; outline: none; }
        .form-control:disabled, .form-select:disabled { opacity: 0.5; cursor: not-allowed; }

        /* --- RIGHT PANEL: WORKSPACE --- */
        .workspace-panel { display: flex; flex-direction: column; gap: 1.5rem; }
        .stats-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        .stat-box { background: rgba(15, 23, 42, 0.6); padding: 1.5rem; border-radius: 12px; border: 1px solid rgba(255,255,255,0.05); text-align: center; }
        .stat-num { font-size: 2rem; font-weight: 800; margin-top: 5px; }
        .stat-label { color: #94a3b8; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 1px; }

        /* Table Section */
        .table-section {
            background: #1e293b; border: 1px solid #334155; border-radius: 16px;
            overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); padding: 1.5rem;
        }
        .batch-table-container {
            max-height: 400px; overflow-y: auto;
            border: 1px solid #334155; border-radius: 8px;
            margin-bottom: 1.5rem;
        }
        .batch-table { width: 100%; border-collapse: collapse; }
        .batch-table th { 
            position: sticky; top: 0; background: #0f172a; z-index: 10;
            text-align: left; color: #10b981; padding: 12px; font-size: 0.85rem; text-transform: uppercase;
            border-bottom: 2px solid #334155;
        }
        .batch-table td { padding: 12px; border-bottom: 1px solid rgba(255,255,255,0.05); font-size: 0.95rem; color: #e2e8f0; }
        .batch-table tr:hover { background: rgba(16, 185, 129, 0.05); }
        .row-checkbox { transform: scale(1.2); cursor: pointer; accent-color: #10b981; }

        /* Summary & Profit Box */
        .summary-box { margin-top: 1.5rem; background: #0f172a; padding: 1rem; border-radius: 12px; border-left: 4px solid #10b981; }
        .summary-row { display: flex; justify-content: space-between; margin-bottom: 5px; font-size: 0.9rem; color: #94a3b8; }
        .profit-box { background: #0f172a; padding: 1.5rem; border-radius: 12px; text-align: center; margin-top: 1rem; border: 2px solid #334155; }
        .profit-pos { border-color: #10b981; background: rgba(16, 185, 129, 0.1); color: #10b981; }
        .profit-neg { border-color: #ef4444; background: rgba(239, 68, 68, 0.1); color: #ef4444; }

        .btn-submit {
            width: 100%; margin-top: 1.5rem; padding: 1rem;
            background: linear-gradient(135deg, #10b981, #059669);
            border: none; border-radius: 12px; color: white; font-weight: 700;
            cursor: pointer; transition: all 0.2s; font-size: 1rem;
        }
        .btn-submit:disabled { opacity: 0.5; cursor: not-allowed; filter: grayscale(1); }
        
        @media (max-width: 1024px) { .main-grid { grid-template-columns: 1fr; } .control-panel { position: relative; top: 0; } }
    </style>
</head>
<body>

<div class="container">
    <div class="main-grid">
        
        <div class="control-panel">
            <div class="panel-title">💰 Group Sales</div>
            <div style="font-size: 0.85rem; color: #94a3b8; margin-bottom: 1.5rem;">Sell multiple animals in one transaction.</div>

            <div style="background:rgba(255,255,255,0.03); padding:10px; border-radius:8px; margin-bottom:1rem; border:1px dashed #475569;">
                <label class="form-label" style="margin-bottom:8px; color:#6ee7b7;">1. Locate Pen</label>
                <div class="form-group">
                    <select id="location_id" class="form-select" onchange="loadBuildings()">
                        <option value="">Select Location</option>
                        <?php foreach($locations as $l): echo "<option value='{$l['LOCATION_ID']}'>{$l['LOCATION_NAME']}</option>"; endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <select id="building_id" class="form-select" onchange="loadPens()" disabled><option>Select Building</option></select>
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <select id="pen_id" class="form-select" onchange="loadPenAnimals()" disabled><option>Select Pen</option></select>
                </div>
            </div>

            <label class="form-label" style="color:#6ee7b7;">2. Batch Details</label>

            <form id="batchForm" onsubmit="submitBatchSale(event)">
                <div id="hidden_inputs_container"></div>

                <div class="form-group">
                    <label class="form-label">Buyer Name <span style="color:#f87171">*</span></label>
                    <input type="text" name="customer_name" class="form-input" required placeholder="Customer Name">
                </div>

                <div class="form-group" style="margin-bottom: 15px;">
                    <label class="form-label" style="margin-bottom: 8px;">Pricing Mode</label>
                    <div style="display: flex; gap: 15px; font-size: 0.9rem;">
                        <label style="cursor: pointer; display: flex; align-items: center; gap: 5px; color: #cbd5e1;">
                            <input type="radio" name="price_mode" value="lump" checked onchange="togglePriceMode()"> 
                            Fixed Total Price
                        </label>
                        <label style="cursor: pointer; display: flex; align-items: center; gap: 5px; color: #cbd5e1;">
                            <input type="radio" name="price_mode" value="per_kg" onchange="togglePriceMode()"> 
                            Price Per KG
                        </label>
                    </div>
                </div>

                <div class="form-group">
                    
                    <div id="div_total_price">
                        <label class="form-label">Total Sale Price (Lump Sum) <span style="color:#f87171">*</span></label>
                        <input type="number" step="0.01" name="total_batch_price" id="total_batch_price" class="form-input" 
                               required placeholder="0.00" oninput="calculateBatchProfit()" style="border-color: #fbbf24; color: #fbbf24; font-weight:bold;">
                    </div>

                    <div id="div_price_per_kg" style="display: none;">
                        <label class="form-label" style="color: #3b82f6;">Price per KG (₱)</label>
                        <input type="number" step="0.01" id="price_per_kg_input" class="form-input" 
                               placeholder="e.g. 250.00" oninput="calculateFromKg()" style="border-color: #3b82f6;">
                    </div>

                </div>

                <div class="form-group">
                    <label class="form-label">Total Overhead (Optional)</label>
                    <input type="number" step="0.01" name="total_overhead" id="total_overhead" class="form-input" placeholder="0.00" oninput="calculateBatchProfit()">
                </div>

                <div class="form-group">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-textarea" placeholder="Batch details..."></textarea>
                </div>

                <div class="summary-box">
                    <div class="summary-row"><span>Selected Count:</span> <span id="summ_count" style="color:#fff">0 Heads</span></div>
                    <div class="summary-row"><span>Total Net Worth:</span> <span id="summ_net_worth" style="color:#f472b6">₱0.00</span></div>
                    <div class="summary-row"><span>Total Weight:</span> <span id="summ_total_weight" style="color:#3b82f6">0.00 kg</span></div>
                </div>

                <div id="profitBox" class="profit-box">
                    <div style="color: #94a3b8; font-size: 0.75rem; text-transform: uppercase;">Estimated Profit</div>
                    <div style="font-size: 1.8rem; font-weight: 800; margin-top: 5px;" id="profitDisplay">₱0.00</div>
                    <div style="font-size: 0.75rem; margin-top:5px; color:#64748b;" id="perHeadProfit"></div>
                </div>

                <button type="submit" id="btn_submit" class="btn-submit" disabled>Process Sale</button>
            </form>
        </div>

        <div class="workspace-panel">
            
            <div class="stats-row">
                <div class="stat-box">
                    <div class="stat-label">Sold Today</div>
                    <div class="stat-num" style="color: #34d399;"><?= number_format($stats['total_sold_today']) ?></div>
                </div>
                <div class="stat-box">
                    <div class="stat-label">Revenue Today</div>
                    <div class="stat-num" style="color: #fbbf24;">₱<?= number_format($stats['total_rev'], 2) ?></div>
                </div>
            </div>

            <div class="table-section">
                <div style="padding-bottom:1rem; border-bottom:1px solid #334155; margin-bottom:1rem; display:flex; justify-content:space-between; align-items:center;">
                    <div style="font-weight:700; color:#fff; font-size:1.1rem;">📋 3. Select Animals</div>
                    <div style="font-size:0.85rem; color:#94a3b8;">Tick checkboxes to include in sale</div>
                </div>
                
                <div class="batch-table-container">
                    <table class="batch-table">
                        <thead>
                            <tr>
                                <th width="50" style="text-align:center;"><input type="checkbox" id="selectAll" onclick="toggleAll(this)"></th>
                                <th>Tag No</th>
                                <th>Weight (kg)</th>
                                <th>Est. Net Worth</th>
                            </tr>
                        </thead>
                        <tbody id="animal_table_body">
                            <tr><td colspan="4" style="text-align:center; padding:2rem; color:#64748b;">Select a Pen from the left to load animals.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
    // FIX: Dynamically determine current filename to avoid 404s
    const CURRENT_PAGE = window.location.pathname.split("/").pop();

    async function fetchData(urlParams) {
        try { 
            const res = await fetch(`${CURRENT_PAGE}${urlParams}`);
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            const text = await res.text();
            try { return JSON.parse(text); } catch (e) { console.error("Invalid JSON:", text); return []; }
        } catch(e) { 
            console.error("Fetch Error:", e); 
            return []; 
        }
    }

    // --- CASCADING DROPDOWNS ---
    async function loadBuildings() {
        const locId = document.getElementById('location_id').value;
        const bSelect = document.getElementById('building_id');
        bSelect.innerHTML = '<option value="">-- Select --</option>';
        document.getElementById('pen_id').innerHTML = '<option value="">-- Select --</option>';
        bSelect.disabled = true; 
        
        if(locId) {
            const data = await fetchData(`?action=get_buildings&location_id=${locId}`);
            if(data.error) { alert(data.error); return; }
            data.forEach(i => bSelect.innerHTML += `<option value="${i.BUILDING_ID}">${i.BUILDING_NAME}</option>`);
            bSelect.disabled = false;
        }
    }

    async function loadPens() {
        const bId = document.getElementById('building_id').value;
        const pSelect = document.getElementById('pen_id');
        pSelect.innerHTML = '<option value="">-- Select --</option>';
        pSelect.disabled = true;
        
        if(bId) {
            const data = await fetchData(`?action=get_pens&building_id=${bId}`);
            if(data.error) { alert(data.error); return; }
            data.forEach(i => pSelect.innerHTML += `<option value="${i.PEN_ID}">${i.PEN_NAME}</option>`);
            pSelect.disabled = false;
        }
    }

    // --- BATCH DATA LOADING ---
    let currentBatchData = []; 

    async function loadPenAnimals() {
        const pId = document.getElementById('pen_id').value;
        const tbody = document.getElementById('animal_table_body');
        tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;">Loading...</td></tr>';
        
        if(!pId) return;

        const res = await fetchData(`?action=get_pen_batch_data&pen_id=${pId}`);
        tbody.innerHTML = '';
        
        if(res.success && res.animals.length > 0) {
            currentBatchData = res.animals;
            res.animals.forEach(a => {
                const totalCost = parseFloat(a.ACQUISITION_COST) + parseFloat(a.cost_feed) + parseFloat(a.cost_med) + parseFloat(a.cost_vac) + parseFloat(a.cost_vit) + parseFloat(a.cost_chk);
                
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td style="text-align:center;">
                        <input type="checkbox" class="row-checkbox" value="${a.ANIMAL_ID}" 
                               data-cost="${totalCost}" 
                               data-weight="${a.CURRENT_ACTUAL_WEIGHT}" 
                               onclick="recalcSelection()">
                    </td>
                    <td style="color:#fff; font-weight:600;">${a.TAG_NO}</td>
                    <td>${a.CURRENT_ACTUAL_WEIGHT} kg</td>
                    <td style="font-family:monospace; color:#f472b6;">₱${fmt(totalCost)}</td>
                `;
                tbody.appendChild(tr);
            });
        } else {
            tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;">No active animals found in this pen.</td></tr>';
        }
        recalcSelection();
    }

    // --- CALCULATION LOGIC ---
    let selectedNetWorth = 0;
    let selectedCount = 0;
    let selectedTotalWeight = 0;

    function toggleAll(source) {
        document.querySelectorAll('.row-checkbox').forEach(cb => cb.checked = source.checked);
        recalcSelection();
    }

    function recalcSelection() {
        const checkboxes = document.querySelectorAll('.row-checkbox:checked');
        selectedCount = checkboxes.length;
        selectedNetWorth = 0;
        selectedTotalWeight = 0;
        
        const hiddenContainer = document.getElementById('hidden_inputs_container');
        hiddenContainer.innerHTML = '';

        checkboxes.forEach(cb => {
            selectedNetWorth += parseFloat(cb.getAttribute('data-cost')) || 0;
            selectedTotalWeight += parseFloat(cb.getAttribute('data-weight')) || 0;
            
            // Generate Hidden Inputs for PHP Submission
            createHidden(hiddenContainer, 'animal_ids[]', cb.value);

            const aData = currentBatchData.find(x => x.ANIMAL_ID == cb.value);
            if(aData) {
                createHidden(hiddenContainer, `costs[${cb.value}][acq]`, aData.ACQUISITION_COST);
                createHidden(hiddenContainer, `costs[${cb.value}][feed]`, aData.cost_feed);
                createHidden(hiddenContainer, `costs[${cb.value}][med]`, aData.cost_med);
                createHidden(hiddenContainer, `costs[${cb.value}][vac]`, aData.cost_vac);
                createHidden(hiddenContainer, `costs[${cb.value}][vit]`, aData.cost_vit);
                createHidden(hiddenContainer, `costs[${cb.value}][chk]`, aData.cost_chk);
                createHidden(hiddenContainer, `costs[${cb.value}][weight]`, aData.CURRENT_ACTUAL_WEIGHT);
            }
        });

        // Update Summary
        document.getElementById('summ_count').innerText = selectedCount + " Heads";
        document.getElementById('summ_net_worth').innerText = "₱" + fmt(selectedNetWorth);
        document.getElementById('summ_total_weight').innerText = selectedTotalWeight.toFixed(2) + " kg";
        
        // Enable/Disable Submit
        document.getElementById('btn_submit').disabled = (selectedCount === 0);
        
        // If currently in 'per_kg' mode, recalculate total based on new weight
        const mode = document.querySelector('input[name="price_mode"]:checked').value;
        if(mode === 'per_kg') {
            calculateFromKg();
        } else {
            calculateBatchProfit();
        }
    }

    function createHidden(container, name, val) {
        const i = document.createElement('input');
        i.type = 'hidden'; i.name = name; i.value = val;
        container.appendChild(i);
    }

    // --- PRICING LOGIC ---
    function togglePriceMode() {
        const mode = document.querySelector('input[name="price_mode"]:checked').value;
        const divTotal = document.getElementById('div_total_price');
        const divPerKg = document.getElementById('div_price_per_kg');
        const finalInput = document.getElementById('total_batch_price');
        
        if (mode === 'per_kg') {
            divTotal.style.display = 'none';
            divPerKg.style.display = 'block';
            finalInput.readOnly = true;
            calculateFromKg();
        } else {
            divTotal.style.display = 'block';
            divPerKg.style.display = 'none';
            finalInput.readOnly = false;
            // finalInput.value = ''; // Optionally clear
            calculateBatchProfit();
        }
    }

    function calculateFromKg() {
        const pricePerKg = parseFloat(document.getElementById('price_per_kg_input').value) || 0;
        const total = selectedTotalWeight * pricePerKg;
        
        document.getElementById('total_batch_price').value = total.toFixed(2);
        calculateBatchProfit();
    }

    function calculateBatchProfit() {
        const salePrice = parseFloat(document.getElementById('total_batch_price').value) || 0;
        const overhead = parseFloat(document.getElementById('total_overhead').value) || 0;
        
        const totalCost = selectedNetWorth + overhead;
        const profit = salePrice - totalCost;

        const pBox = document.getElementById('profitBox');
        document.getElementById('profitDisplay').innerText = "₱" + fmt(profit);
        
        const pHead = document.getElementById('perHeadProfit');
        if(selectedCount > 0) {
            const avgProfit = profit / selectedCount;
            pHead.innerText = "(Avg ₱" + fmt(avgProfit) + " per head)";
        } else {
            pHead.innerText = "";
        }
        
        pBox.className = profit >= 0 ? "profit-box profit-pos" : "profit-box profit-neg";
    }

    // --- FORM SUBMISSION ---
    function submitBatchSale(event) {
        event.preventDefault();
        if(!confirm('Confirm BATCH sale? Selected animals will be marked as SOLD.')) return;

        const formData = new FormData(document.getElementById('batchForm'));
        const btn = document.getElementById('btn_submit');

        if(formData.getAll('animal_ids[]').length === 0) {
            alert("No animals selected."); return;
        }

        btn.disabled = true; btn.innerText = "Processing...";

        fetch('../process/addGroupAnimalSell.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(res => {
            if(res.success) {
                alert("✅ " + res.message);
                window.location.reload();
            } else {
                alert("❌ Error: " + res.message);
                btn.disabled = false; btn.innerText = "Process Sale";
            }
        })
        .catch(err => {
            console.error(err);
            alert("System Error");
            btn.disabled = false; btn.innerText = "Process Sale";
        });
    }

    function fmt(v) { return parseFloat(v).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}); }
</script>

</body>
</html>