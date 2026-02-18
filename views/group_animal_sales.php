<?php
// views/group_animal_sales.php
ob_start(); 
error_reporting(E_ALL);
ini_set('display_errors', 0);
include '../config/Connection.php';

include '../security/checkAccess.php';
checkAccess('group_sell_animals');
$page = "farm";
include '../common/navbar.php';

// 1. AJAX HANDLER
if (isset($_GET['action'])) {
    ob_end_clean(); 
    header('Content-Type: application/json');
    $action = $_GET['action'];

    try {
        if ($action === 'get_buildings' && isset($_GET['location_id'])) {
            $stmt = $conn->prepare("SELECT BUILDING_ID, BUILDING_NAME FROM BUILDINGS WHERE LOCATION_ID = ? ORDER BY BUILDING_NAME");
            $stmt->execute([$_GET['location_id']]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC)); exit;
        }
        if ($action === 'get_pens' && isset($_GET['building_id'])) {
            $stmt = $conn->prepare("SELECT PEN_ID, PEN_NAME FROM PENS WHERE BUILDING_ID = ? ORDER BY PEN_NAME");
            $stmt->execute([$_GET['building_id']]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC)); exit;
        }
        if ($action === 'get_pen_batch_data' && isset($_GET['pen_id'])) {
            $pen_id = $_GET['pen_id'];
            $sql = "SELECT a.ANIMAL_ID, a.TAG_NO, a.CURRENT_ACTUAL_WEIGHT, a.ACQUISITION_COST,
                    COALESCE((SELECT SUM(TRANSACTION_COST) FROM FEED_TRANSACTIONS WHERE ANIMAL_ID = a.ANIMAL_ID), 0) as cost_feed,
                    COALESCE((SELECT SUM(TOTAL_COST) FROM TREATMENT_TRANSACTIONS WHERE ANIMAL_ID = a.ANIMAL_ID), 0) as cost_med,
                    COALESCE((SELECT SUM(VACCINATION_COST + VACCINE_COST) FROM VACCINATION_RECORDS WHERE ANIMAL_ID = a.ANIMAL_ID), 0) as cost_vac,
                    COALESCE((SELECT SUM(TOTAL_COST) FROM VITAMINS_SUPPLEMENTS_TRANSACTIONS WHERE ANIMAL_ID = a.ANIMAL_ID), 0) as cost_vit,
                    COALESCE((SELECT SUM(COST) FROM CHECK_UPS WHERE ANIMAL_ID = a.ANIMAL_ID), 0) as cost_chk
                FROM ANIMAL_RECORDS a
                WHERE a.PEN_ID = ? AND a.IS_ACTIVE = 1 AND a.CURRENT_STATUS != 'Sold'
                ORDER BY a.TAG_NO";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$pen_id]);
            echo json_encode(['success' => true, 'animals' => $stmt->fetchAll(PDO::FETCH_ASSOC)]); exit;
        }
    } catch (Exception $e) { echo json_encode(['error' => $e->getMessage()]); exit; }
}

// 2. PAGE INIT
$locations = $conn->query("SELECT * FROM LOCATIONS ORDER BY LOCATION_NAME")->fetchAll(PDO::FETCH_ASSOC);
$buyers = $conn->query("SELECT FULL_NAME FROM buyers WHERE IS_ACTIVE = 1 ORDER BY FULL_NAME ASC")->fetchAll(PDO::FETCH_ASSOC);
$stats = $conn->query("SELECT COUNT(*) as total_sold_today, COALESCE(SUM(final_sale_price), 0) as total_rev FROM ANIMAL_SALES WHERE DATE(sale_date) = CURDATE()")->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Group Sales</title>
    <style>
        /* Core Styles */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: system-ui, -apple-system, sans-serif; background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); min-height: 100vh; color: #e2e8f0; }
        .container { max-width: 1600px; margin: 0 auto; padding: 1.5rem; }
        .main-grid { display: grid; grid-template-columns: 380px 1fr; gap: 1.5rem; align-items: start; }
        
        /* Back Link - Upper Left */
        .back-link { 
            display: inline-flex; align-items: center; gap: 8px; 
            text-decoration: none; color: #94a3b8; font-weight: 600; 
            font-size: 0.95rem; margin-bottom: 1.5rem; transition: color 0.2s;
            border: none; background: transparent; padding: 0;
        }
        .back-link:hover { color: white; }

        /* Panels */
        .control-panel { background: rgba(30, 41, 59, 0.7); backdrop-filter: blur(12px); border: 1px solid rgba(16, 185, 129, 0.2); border-radius: 16px; padding: 1.5rem; position: sticky; top: 1.5rem; }
        .panel-title { font-size: 1.25rem; font-weight: 700; color: #fff; margin-bottom: 5px; }
        
        /* Forms */
        .form-group { margin-bottom: 1rem; }
        .form-label { display: block; font-size: 0.85rem; color: #cbd5e1; margin-bottom: 0.4rem; font-weight: 500; }
        .form-control, .form-select, .form-input, .form-textarea { width: 100%; padding: 0.75rem; background: #0f172a; border: 1px solid #334155; border-radius: 8px; color: #fff; font-size: 0.95rem; transition: border-color 0.2s; }
        .form-control:focus, .form-select:focus, .form-input:focus { border-color: #10b981; outline: none; }
        .form-control:disabled, .form-select:disabled, .form-input:disabled { opacity: 0.5; cursor: not-allowed; background: #1e293b; color: #64748b; }
        
        /* Inputs */
        .price-input { width: 120px; padding: 8px; background: #0f172a; border: 1px solid #334155; border-radius: 6px; color: #fbbf24; font-weight: bold; text-align: right; }
        .price-input:disabled { opacity: 0.5; cursor: not-allowed; border-color: transparent; color: #64748b; }
        .price-input:focus { border-color: #fbbf24; outline: none; }

        /* Summary & Stats */
        .summary-box { margin-top: 1.5rem; background: #0f172a; padding: 1rem; border-radius: 12px; border-left: 4px solid #10b981; }
        .summary-row { display: flex; justify-content: space-between; margin-bottom: 5px; font-size: 0.9rem; color: #94a3b8; }
        .profit-box { background: #0f172a; padding: 1.5rem; border-radius: 12px; text-align: center; margin-top: 1rem; border: 2px solid #334155; }
        .profit-pos { border-color: #10b981; background: rgba(16, 185, 129, 0.1); color: #10b981; }
        .profit-neg { border-color: #ef4444; background: rgba(239, 68, 68, 0.1); color: #ef4444; }
        
        /* Table */
        .table-section { background: #1e293b; border: 1px solid #334155; border-radius: 16px; overflow: hidden; padding: 1.5rem; }
        .batch-table-container { max-height: 500px; overflow-y: auto; border: 1px solid #334155; border-radius: 8px; margin-bottom: 1.5rem; }
        .batch-table { width: 100%; border-collapse: collapse; }
        .batch-table th { position: sticky; top: 0; background: #0f172a; z-index: 10; text-align: left; color: #10b981; padding: 12px; font-size: 0.85rem; text-transform: uppercase; border-bottom: 2px solid #334155; }
        .batch-table td { padding: 8px 12px; border-bottom: 1px solid rgba(255,255,255,0.05); font-size: 0.95rem; color: #e2e8f0; vertical-align: middle; }
        .row-checkbox { transform: scale(1.2); cursor: pointer; accent-color: #10b981; }

        .btn-submit { width: 100%; margin-top: 1.5rem; padding: 1rem; background: linear-gradient(135deg, #10b981, #059669); border: none; border-radius: 12px; color: white; font-weight: 700; cursor: pointer; transition: all 0.2s; font-size: 1rem; }
        .btn-submit:disabled { opacity: 0.5; cursor: not-allowed; filter: grayscale(1); }
        
        @media (max-width: 1024px) { .main-grid { grid-template-columns: 1fr; } .control-panel { position: relative; top: 0; } }
    </style>
</head>
<body>

<div class="container">
    
    <a href="transactions.php" class="back-link">
        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
        Back to Transactions
    </a>

    <div class="main-grid">
        
        <div class="control-panel">
            <div class="panel-title">💰 Group Sales</div>
            <div style="font-size: 0.85rem; color: #94a3b8; margin-bottom: 1.5rem;">Batch Processing & Pricing</div>

            <div style="background:rgba(255,255,255,0.03); padding:10px; border-radius:8px; margin-bottom:1rem; border:1px dashed #475569;">
                <label class="form-label" style="margin-bottom:8px; color:#6ee7b7;">1. Locate Pen</label>
                <div class="form-group"><select id="location_id" class="form-select" onchange="loadBuildings()"><option value="">Select Location</option><?php foreach($locations as $l): echo "<option value='{$l['LOCATION_ID']}'>{$l['LOCATION_NAME']}</option>"; endforeach; ?></select></div>
                <div class="form-group"><select id="building_id" class="form-select" onchange="loadPens()" disabled><option>Select Building</option></select></div>
                <div class="form-group" style="margin-bottom:0;"><select id="pen_id" class="form-select" onchange="loadPenAnimals()" disabled><option>Select Pen</option></select></div>
            </div>

            <form id="batchForm" onsubmit="submitBatchSale(event)">
                <div id="hidden_inputs_container"></div>

                <label class="form-label" style="color:#6ee7b7;">2. Pricing Strategy</label>
                
                <div class="form-group">
                    <label class="form-label">Buyer Name <span style="color:#f87171">*</span></label>
                    <select name="customer_name" class="form-select" required>
                        <option value="">-- Select Buyer --</option>
                        <?php foreach($buyers as $b): ?><option value="<?= htmlspecialchars($b['FULL_NAME']) ?>"><?= htmlspecialchars($b['FULL_NAME']) ?></option><?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" style="color:#3b82f6;">Uniform Price Per Head</label>
                    <input type="number" step="0.01" id="global_price_per_head" class="form-input" 
                           placeholder="Type to lock individual inputs..." 
                           oninput="handleGlobalPriceInput()"
                           style="border-color: #3b82f6;">
                </div>

                <div class="form-group">
                    <label class="form-label">Total Sale Price (Read Only)</label>
                    <input type="text" id="total_batch_price_display" class="form-input" readonly value="₱0.00" 
                           style="border-color: #fbbf24; color: #fbbf24; font-weight:bold; font-size:1.2rem; text-align:right;">
                </div>

                <div class="form-group">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-textarea" placeholder="Batch details..."></textarea>
                </div>

                <div class="summary-box">
                    <div class="summary-row"><span>Selected:</span> <span id="summ_count" style="color:#fff">0</span></div>
                    <div class="summary-row"><span>Net Worth:</span> <span id="summ_net_worth" style="color:#f472b6">₱0.00</span></div>
                    <div class="summary-row"><span>Total Weight:</span> <span id="summ_total_weight" style="color:#3b82f6">0.00 kg</span></div>
                </div>

                <div id="profitBox" class="profit-box">
                    <div style="color:#94a3b8; font-size:0.75rem;">ESTIMATED PROFIT</div>
                    <div style="font-size:1.8rem; font-weight:800; margin-top:5px;" id="profitDisplay">₱0.00</div>
                </div>

                <button type="submit" id="btn_submit" class="btn-submit" disabled>Process Sale</button>
            </form>
        </div>

        <div class="workspace-panel">
            <div class="table-section">
                <div style="padding-bottom:1rem; border-bottom:1px solid #334155; margin-bottom:1rem;">
                    <div style="font-weight:700; color:#fff; font-size:1.1rem;">📋 3. Select & Price Animals</div>
                    <div style="font-size:0.85rem; color:#94a3b8;">Enter price individually or use the Uniform Price field on the left.</div>
                </div>
                
                <div class="batch-table-container">
                    <table class="batch-table">
                        <thead>
                            <tr>
                                <th width="50" style="text-align:center;"><input type="checkbox" id="selectAll" onclick="toggleAll(this)"></th>
                                <th>Tag No</th>
                                <th>Weight</th>
                                <th>Cost</th>
                                <th style="text-align:right;">Sale Price (₱)</th>
                            </tr>
                        </thead>
                        <tbody id="animal_table_body">
                            <tr><td colspan="5" style="text-align:center; padding:2rem; color:#64748b;">Select a Pen to load.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    const CURRENT_PAGE = window.location.pathname.split("/").pop();
    let currentBatchData = []; 

    async function fetchData(urlParams) {
        try { const res = await fetch(`${CURRENT_PAGE}${urlParams}`); return await res.json(); } catch(e) { return []; }
    }

    async function loadBuildings() {
        const locId = document.getElementById('location_id').value;
        const bSelect = document.getElementById('building_id');
        bSelect.innerHTML = '<option value="">-- Select --</option>'; bSelect.disabled = true;
        document.getElementById('pen_id').innerHTML = '<option value="">-- Select --</option>'; document.getElementById('pen_id').disabled = true;
        if(locId) {
            const data = await fetchData(`?action=get_buildings&location_id=${locId}`);
            data.forEach(i => bSelect.innerHTML += `<option value="${i.BUILDING_ID}">${i.BUILDING_NAME}</option>`);
            bSelect.disabled = false;
        }
    }

    async function loadPens() {
        const bId = document.getElementById('building_id').value;
        const pSelect = document.getElementById('pen_id');
        pSelect.innerHTML = '<option value="">-- Select --</option>'; pSelect.disabled = true;
        if(bId) {
            const data = await fetchData(`?action=get_pens&building_id=${bId}`);
            data.forEach(i => pSelect.innerHTML += `<option value="${i.PEN_ID}">${i.PEN_NAME}</option>`);
            pSelect.disabled = false;
        }
    }

    async function loadPenAnimals() {
        const pId = document.getElementById('pen_id').value;
        const tbody = document.getElementById('animal_table_body');
        tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;">Loading...</td></tr>';
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
                               data-cost="${totalCost}" data-weight="${a.CURRENT_ACTUAL_WEIGHT}" 
                               onclick="toggleRow(this)">
                    </td>
                    <td style="color:#fff; font-weight:600;">${a.TAG_NO}</td>
                    <td>${parseFloat(a.CURRENT_ACTUAL_WEIGHT).toFixed(2)} kg</td>
                    <td style="font-family:monospace; color:#f472b6;">₱${fmt(totalCost)}</td>
                    <td style="text-align:right;">
                        <input type="number" step="0.01" min="0" class="price-input" 
                               id="price_${a.ANIMAL_ID}" placeholder="0.00" disabled
                               oninput="handleIndividualInput()">
                    </td>
                `;
                tbody.appendChild(tr);
            });
        } else { tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;">No animals found.</td></tr>'; }
        recalc();
    }

    // --- CONFLICT LOGIC ---
    
    // 1. Uniform Price Input Handler
    function handleGlobalPriceInput() {
        const globalVal = document.getElementById('global_price_per_head').value;
        const checkboxes = document.querySelectorAll('.row-checkbox:checked');
        
        checkboxes.forEach(cb => {
            const input = document.getElementById(`price_${cb.value}`);
            if(globalVal !== "" && parseFloat(globalVal) > 0) {
                input.value = globalVal; 
                input.disabled = true; // Disable to prevent conflict
            } else {
                input.disabled = false; // Enable if global cleared
            }
        });
        recalc();
    }

    // 2. Individual Input Handler
    function handleIndividualInput() {
        const inputs = document.querySelectorAll('.price-input:not(:disabled)');
        let hasValue = false;
        inputs.forEach(i => { if(i.value > 0) hasValue = true; });

        const globalInput = document.getElementById('global_price_per_head');
        if(hasValue) {
            globalInput.disabled = true;
            globalInput.placeholder = "Clear table inputs first";
            globalInput.value = ""; 
        } else {
            globalInput.disabled = false;
            globalInput.placeholder = "Type to lock individual inputs...";
        }
        recalc();
    }

    function toggleAll(source) {
        document.querySelectorAll('.row-checkbox').forEach(cb => {
            cb.checked = source.checked;
            toggleRow(cb); 
        });
        const globalVal = document.getElementById('global_price_per_head').value;
        if(globalVal > 0 && !document.getElementById('global_price_per_head').disabled) {
            handleGlobalPriceInput();
        }
        recalc();
    }

    function toggleRow(checkbox) {
        const priceInput = document.getElementById(`price_${checkbox.value}`);
        const globalVal = document.getElementById('global_price_per_head').value;
        const isGlobalActive = (globalVal > 0 && !document.getElementById('global_price_per_head').disabled);

        if(priceInput) {
            priceInput.disabled = !checkbox.checked || isGlobalActive;
            if(checkbox.checked && isGlobalActive) {
                priceInput.value = globalVal;
            } else if (!checkbox.checked) {
                priceInput.value = '';
            }
        }
        recalc();
    }

    function recalc() {
        const checkboxes = document.querySelectorAll('.row-checkbox:checked');
        let totalCost = 0, totalRevenue = 0;
        const hiddenContainer = document.getElementById('hidden_inputs_container');
        hiddenContainer.innerHTML = ''; 

        checkboxes.forEach(cb => {
            totalCost += parseFloat(cb.getAttribute('data-cost')) || 0;
            
            // Get price 
            const priceInput = document.getElementById(`price_${cb.value}`);
            const salePrice = parseFloat(priceInput.value) || 0;
            totalRevenue += salePrice;

            createHidden(hiddenContainer, 'animal_ids[]', cb.value);
            createHidden(hiddenContainer, `costs[${cb.value}][sale_price]`, salePrice); 
            
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

        document.getElementById('summ_count').innerText = checkboxes.length + " Heads";
        document.getElementById('summ_net_worth').innerText = "₱" + fmt(totalCost);
        document.getElementById('total_batch_price_display').value = "₱" + fmt(totalRevenue);

        const profit = totalRevenue - totalCost;
        document.getElementById('profitDisplay').innerText = "₱" + fmt(profit);
        const pBox = document.getElementById('profitBox');
        pBox.className = profit >= 0 ? "profit-box profit-pos" : "profit-box profit-neg";

        document.getElementById('btn_submit').disabled = (checkboxes.length === 0 || totalRevenue <= 0);
    }

    function createHidden(container, name, val) {
        const i = document.createElement('input'); i.type = 'hidden'; i.name = name; i.value = val;
        container.appendChild(i);
    }

    function submitBatchSale(e) {
        e.preventDefault();
        if(!confirm('Confirm Sale?')) return;
        const btn = document.getElementById('btn_submit');
        btn.disabled = true; btn.innerText = "Processing...";

        fetch('../process/addGroupAnimalSell.php', { method:'POST', body:new FormData(document.getElementById('batchForm')) })
        .then(r=>r.json()).then(res => {
            if(res.success) {
                if(res.batch_id) window.open('print_batch_sales_receipt.php?batch_id='+res.batch_id, '_blank');
                alert("✅ "+res.message); window.location.reload();
            } else { alert("❌ "+res.message); btn.disabled=false; }
        });
    }

    function fmt(v) { return parseFloat(v).toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2}); }
</script>
</body>
</html>