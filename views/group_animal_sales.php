<?php
// views/group_animal_sales.php
ob_start(); 
error_reporting(E_ALL);
ini_set('display_errors', 0);
include '../config/Connection.php';

include '../security/checkAccess.php';
checkAccess('group_sell_animals');
$page = "transactions";
include '../common/navbar.php';
include '../common/chat_support.php';
include '../functions/getUsersLocation.php'; // ADDED LOCATION FUNCTION

// 1. AJAX HANDLER
if (isset($_GET['action'])) {
    ob_end_clean(); 
    header('Content-Type: application/json');
    $action = $_GET['action'];

    try {
        if ($action === 'get_buildings' && isset($_GET['location_id'])) {
            $stmt = $conn->prepare("SELECT BUILDING_ID, BUILDING_NAME FROM buildings WHERE LOCATION_ID = ? ORDER BY BUILDING_NAME");
            $stmt->execute([$_GET['location_id']]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC)); exit;
        }
        
        // Fetches ALL Pens and ALL Animals inside a specific building
        if ($action === 'get_bldg_animals_for_sale' && isset($_GET['building_id'])) {
            $bldg_id = $_GET['building_id'];
            
            $sql = "SELECT p.PEN_ID, p.PEN_NAME, 
                           a.ANIMAL_ID, a.TAG_NO, a.CURRENT_ACTUAL_WEIGHT, a.ACQUISITION_COST,
                           COALESCE((SELECT SUM(TRANSACTION_COST) FROM feed_transactions WHERE ANIMAL_ID = a.ANIMAL_ID), 0) as cost_feed,
                           COALESCE((SELECT SUM(TOTAL_COST) FROM treatment_transactions WHERE ANIMAL_ID = a.ANIMAL_ID), 0) as cost_med,
                           COALESCE((SELECT SUM(VACCINATION_COST + VACCINE_COST) FROM vaccination_records WHERE ANIMAL_ID = a.ANIMAL_ID), 0) as cost_vac,
                           COALESCE((SELECT SUM(TOTAL_COST) FROM vitamins_supplements_transactions WHERE ANIMAL_ID = a.ANIMAL_ID), 0) as cost_vit,
                           COALESCE((SELECT SUM(COST) FROM check_ups WHERE ANIMAL_ID = a.ANIMAL_ID), 0) as cost_chk
                    FROM PENS p 
                    LEFT JOIN animal_records a ON p.PEN_ID = a.PEN_ID AND a.IS_ACTIVE = 1 AND a.CURRENT_STATUS != 'Sold'
                    WHERE p.BUILDING_ID = ? 
                    ORDER BY p.PEN_NAME, a.TAG_NO";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute([$bldg_id]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $data = [];
            foreach($results as $r) {
                $pid = $r['PEN_ID'];
                if(!isset($data[$pid])) {
                    $data[$pid] = ['pen_id' => $pid, 'pen_name' => $r['PEN_NAME'], 'animals' => []];
                }
                if($r['ANIMAL_ID']) {
                    $data[$pid]['animals'][] = $r; 
                }
            }
            echo json_encode(['success' => true, 'pens' => array_values($data)]);
            exit;
        }

        // Global Tag Search Override
        if ($action === 'search_animal_for_batch' && isset($_GET['tag'])) {
            $tag = trim($_GET['tag']);
            $sql = "SELECT a.ANIMAL_ID, a.TAG_NO, a.CURRENT_ACTUAL_WEIGHT, a.ACQUISITION_COST,
                    COALESCE((SELECT SUM(TRANSACTION_COST) FROM feed_transactions WHERE ANIMAL_ID = a.ANIMAL_ID), 0) as cost_feed,
                    COALESCE((SELECT SUM(TOTAL_COST) FROM treatment_transactions WHERE ANIMAL_ID = a.ANIMAL_ID), 0) as cost_med,
                    COALESCE((SELECT SUM(VACCINATION_COST + VACCINE_COST) FROM vaccination_records WHERE ANIMAL_ID = a.ANIMAL_ID), 0) as cost_vac,
                    COALESCE((SELECT SUM(TOTAL_COST) FROM vitamins_supplements_transactions WHERE ANIMAL_ID = a.ANIMAL_ID), 0) as cost_vit,
                    COALESCE((SELECT SUM(COST) FROM check_ups WHERE ANIMAL_ID = a.ANIMAL_ID), 0) as cost_chk
                FROM animal_records a
                WHERE a.TAG_NO = ? AND a.IS_ACTIVE = 1 AND a.CURRENT_STATUS != 'Sold'";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$tag]);
            $animal = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($animal) {
                echo json_encode(['success' => true, 'animal' => $animal]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Tag not found or animal already sold.']);
            }
            exit;
        }
    } catch (Exception $e) { echo json_encode(['error' => $e->getMessage()]); exit; }
}

// 2. PAGE INIT & LOCATION FILTERING
if ($USER_LOCATION_ != 1000) {
    $stmt = $conn->prepare("SELECT * FROM locations WHERE LOCATION_ID = ? ORDER BY LOCATION_NAME");
    $stmt->execute([$USER_LOCATION_]);
    $locations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $locations = $conn->query("SELECT * FROM locations ORDER BY LOCATION_NAME")->fetchAll(PDO::FETCH_ASSOC);
}

$buyers = $conn->query("SELECT FULL_NAME FROM buyers WHERE IS_ACTIVE = 1 ORDER BY FULL_NAME ASC")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Group Sales</title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" type="text/css" href="https://npmcdn.com/flatpickr/dist/themes/dark.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    <style>
        /* Core Styles */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: system-ui, -apple-system, sans-serif; background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); min-height: 100vh; color: #e2e8f0; }
        .container { max-width: 1600px; margin: 0 auto; padding: 1.5rem; }
        .main-grid { display: grid; grid-template-columns: 420px 1fr; gap: 1.5rem; align-items: start; }
        
        .back-link { display: inline-flex; align-items: center; gap: 8px; text-decoration: none; color: #94a3b8; font-weight: 600; font-size: 0.95rem; margin-bottom: 1.5rem; transition: color 0.2s; }
        .back-link:hover { color: white; }

        /* Panels */
        .control-panel { background: rgba(30, 41, 59, 0.7); backdrop-filter: blur(12px); border: 1px solid rgba(251, 191, 36, 0.2); border-radius: 16px; padding: 1.5rem; position: sticky; top: 1.5rem; }
        .panel-title { font-size: 1.5rem; font-weight: 800; color: #fbbf24; margin-bottom: 5px; }
        
        /* Forms */
        .form-group { margin-bottom: 1rem; }
        .form-label { display: block; font-size: 0.85rem; color: #cbd5e1; margin-bottom: 0.4rem; font-weight: 600; text-transform: uppercase;}
        .form-select, .form-input, .form-textarea { width: 100%; padding: 0.75rem; background: #0f172a; border: 1px solid #334155; border-radius: 8px; color: #fff; font-size: 0.95rem; transition: border-color 0.2s; outline:none; }
        .form-select:focus, .form-input:focus, .form-textarea:focus { border-color: #fbbf24; box-shadow: 0 0 0 3px rgba(251, 191, 36, 0.1); }
        .form-select:disabled, .form-input:disabled { opacity: 0.5; cursor: not-allowed; background: #1e293b; color: #64748b; border-color: transparent;}
        .btn-search { background: #3b82f6; color: white; border: none; padding: 0 16px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: background 0.2s; }
        .btn-search:hover { background: #2563eb; }

        /* Inputs */
        .price-input { width: 140px; padding: 8px; background: #0f172a; border: 1px solid #334155; border-radius: 6px; color: #fbbf24; font-weight: bold; text-align: right; }
        .price-input:disabled { opacity: 0.5; cursor: not-allowed; border-color: transparent; color: #64748b; }
        .price-input:focus { border-color: #fbbf24; outline: none; }

        /* Tree Checkboxes */
        .pens-list-container { background: #0f172a; border: 1px solid #334155; border-radius: 8px; padding: 10px; max-height: 250px; overflow-y: auto; margin-top: 5px; }
        .pen-group { margin-bottom: 10px; background: rgba(30, 41, 59, 0.5); padding: 10px; border-radius: 8px; border: 1px solid #334155; }
        .pen-label { font-weight: bold; color: #fff; display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 0.95rem; }
        .animal-list { margin-top: 10px; margin-left: 24px; display: grid; grid-template-columns: repeat(auto-fill, minmax(90px, 1fr)); gap: 8px; }
        .animal-label { font-size: 0.8rem; color: #cbd5e1; display: flex; align-items: center; gap: 6px; cursor: pointer; background: rgba(255,255,255,0.03); padding: 4px 6px; border-radius: 4px; transition: background 0.2s; }
        .animal-label:hover { background: rgba(255,255,255,0.1); }
        .animal-label input[type="checkbox"], .pen-label input[type="checkbox"] { accent-color: #fbbf24; width: 16px; height: 16px; cursor: pointer; }
        .animal-label input:disabled { cursor: not-allowed; }

        /* Summary & Stats */
        .summary-box { margin-top: 1.5rem; background: rgba(15,23,42,0.8); padding: 1.5rem; border-radius: 12px; border: 1px solid #334155; }
        .summary-row { display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 0.95rem; color: #94a3b8; }
        .profit-box { background: #0f172a; padding: 1.5rem; border-radius: 12px; text-align: center; margin-top: 1rem; border: 2px solid #334155; }
        .profit-pos { border-color: #10b981; background: rgba(16, 185, 129, 0.1); color: #10b981; }
        .profit-neg { border-color: #ef4444; background: rgba(239, 68, 68, 0.1); color: #ef4444; }
        
        /* Table */
        .table-section { background: #1e293b; border: 1px solid #334155; border-radius: 16px; overflow: hidden; padding: 1.5rem; }
        .batch-table-container { max-height: calc(100vh - 150px); overflow-y: auto; border: 1px solid #334155; border-radius: 8px; }
        .batch-table { width: 100%; border-collapse: collapse; }
        .batch-table th { position: sticky; top: 0; background: #0f172a; z-index: 10; text-align: left; color: #fbbf24; padding: 15px; font-size: 0.85rem; text-transform: uppercase; border-bottom: 2px solid #334155; }
        .batch-table td { padding: 12px 15px; border-bottom: 1px solid rgba(255,255,255,0.05); font-size: 0.95rem; color: #e2e8f0; vertical-align: middle; }
        .batch-table tr:hover { background: rgba(255,255,255,0.02); }
        .disabled-row { background-color: rgba(239, 68, 68, 0.1); }

        .btn-submit { width: 100%; margin-top: 1.5rem; padding: 1rem; background: linear-gradient(135deg, #fbbf24, #d97706); border: none; border-radius: 12px; color: #0f172a; font-weight: 800; cursor: pointer; transition: transform 0.2s; font-size: 1rem; text-transform: uppercase; letter-spacing: 1px;}
        .btn-submit:disabled { opacity: 0.5; cursor: not-allowed; transform: none; box-shadow: none; }
        .btn-submit:not(:disabled):hover { transform: translateY(-2px); box-shadow: 0 10px 20px rgba(251, 191, 36, 0.2); }
        
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
            <div class="panel-title">💰 Bulk Sales Terminal</div>
            <div style="font-size: 0.85rem; color: #94a3b8; margin-bottom: 1.5rem;">Process Multiple Animal Transactions</div>

            <label class="form-label" style="color:#6ee7b7;">1. Source Target Animals</label>
            <div style="background:rgba(255,255,255,0.02); padding:12px; border-radius:8px; margin-bottom:1rem; border:1px solid #334155;">
                <div class="form-group">
                    <select id="location_id" class="form-select" onchange="loadBuildings()" <?php echo ($USER_LOCATION_ != 1000) ? 'style="background-color: #1e293b; pointer-events: none; color: #94a3b8;"' : ''; ?>>
                        <?php if($USER_LOCATION_ == 1000): ?>
                            <option value="">-- Select Location --</option>
                        <?php endif; ?>
                        <?php foreach($locations as $l): ?>
                            <option value="<?php echo $l['LOCATION_ID']; ?>" <?php echo ($USER_LOCATION_ != 1000 && $l['LOCATION_ID'] == $USER_LOCATION_) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($l['LOCATION_NAME']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <select id="building_id" class="form-select" onchange="loadPensAndAnimals()" disabled>
                        <option>-- Select Building --</option>
                    </select>
                </div>
            </div>

            <div class="form-group" id="animal-selection-group" style="display:none;">
                <label class="form-label">Available Pens in Building <span id="pen-loading" style="display:none;" class="loading">...</span></label>
                <div id="pens-container" class="pens-list-container"></div>
            </div>

            <div style="text-align: center; margin: 1.5rem 0; color: #64748b; font-size: 0.75rem; font-weight: bold; text-transform: uppercase;">OR ADD BY TAG EXPLICITLY</div>
            <div class="form-group" style="display: flex; gap: 8px;">
                <input type="text" id="search_tag_input" class="form-input" placeholder="e.g., A001">
                <button type="button" class="btn-search" onclick="searchAndAddTag()">ADD</button>
            </div>
            <div id="search_error" style="color: #ef4444; font-size: 0.8rem; text-align: center; margin-top: 5px;"></div>

            <label class="form-label" style="color:#6ee7b7; margin-top: 2rem;">2. Pricing Strategy</label>
            <div style="display: flex; flex-direction:column; gap: 12px; background: rgba(15,23,42,0.5); padding: 12px; border-radius: 8px; border: 1px solid #334155;">
                <label style="cursor: pointer; display: flex; align-items: center; gap: 8px; color: #fff; font-size:0.9rem;">
                    <input type="radio" name="price_mode" value="individual" checked onchange="togglePriceMode()" style="accent-color:#fbbf24;"> Individual Price Input
                </label>
                <label style="cursor: pointer; display: flex; align-items: center; gap: 8px; color: #fff; font-size:0.9rem;">
                    <input type="radio" name="price_mode" value="per_head" onchange="togglePriceMode()" style="accent-color:#fbbf24;"> Uniform Price per Head
                </label>
                <label style="cursor: pointer; display: flex; align-items: center; gap: 8px; color: #fff; font-size:0.9rem;">
                    <input type="radio" name="price_mode" value="per_kg" onchange="togglePriceMode()" style="accent-color:#fbbf24;"> Calculate via Price per KG
                </label>
                <label style="cursor: pointer; display: flex; align-items: center; gap: 8px; color: #fff; font-size:0.9rem;">
                    <input type="radio" name="price_mode" value="lump_sum" onchange="togglePriceMode()" style="accent-color:#fbbf24;"> Fixed Batch Price (Lump Sum)
                </label>
            </div>

            <div class="form-group" id="global_input_div" style="display:none; margin-top: 15px;">
                <label class="form-label" id="global_price_label" style="color:#fbbf24;">Input Amount (₱)</label>
                <input type="number" step="0.01" id="global_price_input" class="form-input" placeholder="0.00" oninput="applyPricing()" style="border-color: #fbbf24;">
            </div>

            <label class="form-label" style="color:#6ee7b7; margin-top: 2rem;">3. Finalize & Submit</label>
            <div class="form-group">
                <select id="buyer_name" class="form-select" required>
                    <option value="">-- Select Registered Buyer --</option>
                    <?php foreach($buyers as $b): ?><option value="<?= htmlspecialchars($b['FULL_NAME']) ?>"><?= htmlspecialchars($b['FULL_NAME']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <input type="text" id="sale_date" class="form-input date-picker" placeholder="Date of Sale" required>
            </div>
            <div class="form-group">
                <textarea id="notes" class="form-textarea" placeholder="Batch Details / Optional Remarks" rows="2"></textarea>
            </div>

            <div class="summary-box">
                <h4 style="color: #94a3b8; font-size: 0.8rem; text-transform: uppercase; margin-bottom: 10px; border-bottom: 1px solid #334155; padding-bottom: 5px;">Batch Financial Summary</h4>
                <div class="summary-row"><span>Heads Selected:</span> <span id="summ_count" style="color:#fff; font-weight:bold;">0</span></div>
                <div class="summary-row"><span>Total Weight:</span> <span id="summ_total_weight" style="color:#3b82f6; font-weight:bold;">0.00 kg</span></div>
                <div class="summary-row" style="margin-top: 10px;"><span>Base Animal Costs:</span> <span id="summ_base_cost" style="color:#94a3b8">₱0.00</span></div>
                
                <div class="summary-row" style="align-items: center; margin-top: 5px;">
                    <span style="color:#fbbf24;">+ Batch Overhead Cost:</span> 
                    <input type="number" id="overhead_cost" value="0.00" step="0.01" oninput="recalc()" style="width: 100px; padding: 4px 8px; background: #0f172a; border: 1px solid #fbbf24; border-radius:4px; color: #fbbf24; text-align: right; outline:none;">
                </div>

                <div class="summary-row" style="margin-top: 10px; border-top: 1px dashed #334155; padding-top: 10px; font-size: 1.1rem;">
                    <span style="color:#fff;">Total Net Worth:</span> 
                    <span id="summ_net_worth" style="color:#f472b6; font-weight:bold;">₱0.00</span>
                </div>
                <div class="summary-row" style="font-size: 1.1rem; margin-top: 5px;">
                    <span style="color:#fff;">Total Sale Revenue:</span> 
                    <span id="total_batch_price_display" style="color:#fbbf24; font-weight:bold;">₱0.00</span>
                </div>
            </div>

            <div id="profitBox" class="profit-box">
                <div style="color:#94a3b8; font-size:0.75rem; text-transform:uppercase;">Estimated Gross Profit</div>
                <div style="font-size:2rem; font-weight:800; margin-top:5px;" id="profitDisplay">₱0.00</div>
            </div>

            <button type="button" id="btn_submit" class="btn-submit" onclick="submitBatchSale()" disabled>Confirm Transaction</button>
        </div>

        <div class="workspace-panel">
            <div class="table-section">
                <div style="padding-bottom:1rem; border-bottom:1px solid #334155; margin-bottom:1rem;">
                    <div style="font-weight:700; color:#fff; font-size:1.1rem;">📋 Selected Animals Pricing Table</div>
                    <div style="font-size:0.85rem; color:#94a3b8;">Animals in red are missing a registered weight or acquisition cost and cannot be sold.</div>
                </div>
                
                <div class="batch-table-container">
                    <table class="batch-table">
                        <thead>
                            <tr>
                                <th>Tag No</th>
                                <th>Weight</th>
                                <th>Net Cost</th>
                                <th style="text-align:right;">Sale Price (₱)</th>
                            </tr>
                        </thead>
                        <tbody id="animal_table_body">
                            <tr><td colspan="4" style="text-align:center; padding:3rem; color:#64748b;">Select animals from the left panel.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    const CURRENT_PAGE = window.location.pathname.split("/").pop();
    const USER_LOCATION = <?php echo json_encode($USER_LOCATION_); ?>;
    let currentBatchData = []; 
    let fpSaleDate;

    document.addEventListener('DOMContentLoaded', () => {
        // Initialize Flatpickr
        fpSaleDate = flatpickr("#sale_date", {
            dateFormat: "Y-m-d", // Value submitted to PHP
            altInput: true,      // Visual input
            altFormat: "m/d/Y",  // mm/dd/yyyy format
            allowInput: true
        });
        
        fpSaleDate.clear(); // Leave the actual input blank by default

        // Auto-load buildings if user is restricted to a location
        if (USER_LOCATION != 1000) {
            document.getElementById('location_id').value = USER_LOCATION;
            loadBuildings();
        }
    });

    function fmt(v) { return parseFloat(v).toLocaleString('en-PH', {minimumFractionDigits:2, maximumFractionDigits:2}); }

    async function fetchData(urlParams) {
        try { const res = await fetch(`${CURRENT_PAGE}${urlParams}`); return await res.json(); } catch(e) { return []; }
    }

    // --- LOADER LOGIC ---
    async function loadBuildings() {
        const locId = document.getElementById('location_id').value;
        const bSelect = document.getElementById('building_id');
        bSelect.innerHTML = '<option value="">-- Select --</option>'; bSelect.disabled = true;
        document.getElementById('animal-selection-group').style.display = 'none';
        
        if(locId) {
            const data = await fetchData(`?action=get_buildings&location_id=${locId}`);
            data.forEach(i => bSelect.innerHTML += `<option value="${i.BUILDING_ID}">${i.BUILDING_NAME}</option>`);
            bSelect.disabled = false;
        }
        renderTable();
    }

    async function loadPensAndAnimals() {
        const bldgId = document.getElementById('building_id').value;
        const container = document.getElementById('pens-container');
        const groupWrapper = document.getElementById('animal-selection-group');
        const loader = document.getElementById('pen-loading');
        
        container.innerHTML = '';
        currentBatchData = [];

        if(!bldgId) { groupWrapper.style.display = 'none'; renderTable(); return; }

        groupWrapper.style.display = 'block';
        loader.style.display = 'inline-block';

        const res = await fetchData(`?action=get_bldg_animals_for_sale&building_id=${bldgId}`);
        loader.style.display = 'none';

        if(res.success && res.pens.length > 0) {
            let html = '';
            res.pens.forEach(p => {
                const isPenEmpty = p.animals.length === 0;
                html += `
                    <div class="pen-group">
                        <label class="pen-label">
                            <input type="checkbox" class="pen-cb" onchange="togglePen(this)" ${isPenEmpty ? 'disabled' : ''}> 
                            ${p.pen_name} ${isPenEmpty ? '<span style="color:#64748b; font-size:0.8rem; font-weight:normal;">(Empty)</span>' : ''}
                        </label>
                        <div class="animal-list">
                `;
                p.animals.forEach(a => {
                    currentBatchData.push(a); // Store globally
                    
                    const weight = parseFloat(a.CURRENT_ACTUAL_WEIGHT || 0);
                    const acqCost = parseFloat(a.ACQUISITION_COST || 0);
                    const isMissingData = (weight <= 0 || acqCost <= 0);
                    const disabledAttr = isMissingData ? 'disabled title="Missing Weight or Cost"' : '';
                    const color = isMissingData ? '#f87171' : '#cbd5e1';

                    html += `
                        <label class="animal-label" style="color:${color}">
                            <input type="checkbox" class="animal-cb" value="${a.ANIMAL_ID}" onchange="toggleAnimal(this)" ${disabledAttr}> 
                            ${a.TAG_NO} ${isMissingData ? '⚠️' : ''}
                        </label>
                    `;
                });
                html += `</div></div>`;
            });
            container.innerHTML = html;
        } else {
            container.innerHTML = '<div style="color:#94a3b8; padding:10px;">No available animals in this building.</div>';
        }
        renderTable();
    }

    // --- SEARCH OVERRIDE ---
    async function searchAndAddTag() {
        const tag = document.getElementById('search_tag_input').value.trim();
        const err = document.getElementById('search_error');
        err.innerText = "";
        if(!tag) return;
        
        // check if already loaded
        const existing = currentBatchData.find(a => a.TAG_NO.toLowerCase() === tag.toLowerCase());
        if (existing) {
            const cb = document.querySelector(`.animal-cb[value="${existing.ANIMAL_ID}"]`);
            if(cb && !cb.disabled) {
                cb.checked = true;
                toggleAnimal(cb);
            } else {
                err.innerText = "Animal has missing data and cannot be selected.";
            }
            return;
        }

        const res = await fetchData(`?action=search_animal_for_batch&tag=${encodeURIComponent(tag)}`);
        if(res.success) {
            currentBatchData.push(res.animal);
            
            // Ensure Searched Pen exists
            let searchPen = document.getElementById('searched-pen-group');
            if(!searchPen) {
                const container = document.getElementById('pens-container');
                searchPen = document.createElement('div');
                searchPen.id = 'searched-pen-group';
                searchPen.className = 'pen-group';
                searchPen.innerHTML = `
                    <label class="pen-label" style="color:#fbbf24;">
                        <input type="checkbox" class="pen-cb" onchange="togglePen(this)"> 🔍 Searched Specific Tags
                    </label>
                    <div class="animal-list" id="searched-animal-list"></div>
                `;
                container.appendChild(searchPen);
                document.getElementById('animal-selection-group').style.display = 'block';
            }

            const list = document.getElementById('searched-animal-list');
            const a = res.animal;
            const weight = parseFloat(a.CURRENT_ACTUAL_WEIGHT || 0);
            const acqCost = parseFloat(a.ACQUISITION_COST || 0);
            const isMissingData = (weight <= 0 || acqCost <= 0);
            const disabledAttr = isMissingData ? 'disabled' : 'checked';
            const color = isMissingData ? '#f87171' : '#cbd5e1';

            list.insertAdjacentHTML('beforeend', `
                <label class="animal-label" style="color:${color}">
                    <input type="checkbox" class="animal-cb" value="${a.ANIMAL_ID}" onchange="toggleAnimal(this)" ${disabledAttr}> 
                    ${a.TAG_NO} ${isMissingData ? '⚠️' : ''}
                </label>
            `);

            const pcb = searchPen.querySelector('.pen-cb');
            const total = searchPen.querySelectorAll('.animal-cb:not(:disabled)').length;
            const checked = searchPen.querySelectorAll('.animal-cb:checked').length;
            pcb.checked = (total > 0 && total === checked);
            pcb.indeterminate = (checked > 0 && checked < total);

            document.getElementById('search_tag_input').value = '';
            renderTable();
        } else {
            err.innerText = res.message;
        }
    }

    // --- CHECKBOX LOGIC ---
    function togglePen(penCb) {
        const container = penCb.closest('.pen-group');
        const animalCbs = container.querySelectorAll('.animal-cb:not(:disabled)');
        animalCbs.forEach(cb => cb.checked = penCb.checked);
        renderTable();
    }

    function toggleAnimal(animalCb) {
        const container = animalCb.closest('.pen-group');
        const penCb = container.querySelector('.pen-cb');
        const total = container.querySelectorAll('.animal-cb:not(:disabled)').length;
        const checked = container.querySelectorAll('.animal-cb:checked').length;
        penCb.checked = (total > 0 && total === checked);
        penCb.indeterminate = (checked > 0 && checked < total);
        renderTable();
    }

    // --- TABLE RENDERING ---
    function renderTable() {
        const tbody = document.getElementById('animal_table_body');
        const checkboxes = document.querySelectorAll('.animal-cb:checked');
        
        if (checkboxes.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4" style="text-align:center; padding:3rem; color:#64748b;">Select animals from the left panel.</td></tr>';
            applyPricing(); 
            return;
        }

        // Backup existing inputs
        const existingInputs = {};
        document.querySelectorAll('.price-input').forEach(inp => {
            existingInputs[inp.dataset.id] = inp.value;
        });

        tbody.innerHTML = '';
        
        checkboxes.forEach(cb => {
            const id = cb.value;
            const a = currentBatchData.find(x => x.ANIMAL_ID == id);
            if(!a) return;

            const totalCost = parseFloat(a.ACQUISITION_COST || 0) + parseFloat(a.cost_feed) + parseFloat(a.cost_med) + parseFloat(a.cost_vac) + parseFloat(a.cost_vit) + parseFloat(a.cost_chk);
            const weight = parseFloat(a.CURRENT_ACTUAL_WEIGHT || 0);
            
            const savedValue = existingInputs[id] !== undefined ? existingInputs[id] : '';

            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td style="color:#fff; font-weight:600;">${a.TAG_NO}</td>
                <td data-weight="${weight}">${weight.toFixed(2)} kg</td>
                <td data-cost="${totalCost}" style="font-family:monospace; color:#f472b6;">₱${fmt(totalCost)}</td>
                <td style="text-align:right;">
                    <input type="number" step="0.01" min="0" class="price-input" 
                           id="price_${a.ANIMAL_ID}" data-id="${a.ANIMAL_ID}" value="${savedValue}" placeholder="0.00" 
                           oninput="recalc()">
                </td>
            `;
            tbody.appendChild(tr);
        });

        applyPricing();
    }

    // --- PRICING LOGIC ---
    function togglePriceMode() {
        const mode = document.querySelector('input[name="price_mode"]:checked').value;
        const globalInputDiv = document.getElementById('global_input_div');
        const globalInput = document.getElementById('global_price_input');
        const globalLabel = document.getElementById('global_price_label');

        if (mode === 'individual') {
            globalInputDiv.style.display = 'none';
            globalInput.value = '';
        } else if (mode === 'per_head') {
            globalInputDiv.style.display = 'block';
            globalLabel.innerText = "Uniform Price Per Head (₱)";
        } else if (mode === 'per_kg') {
            globalInputDiv.style.display = 'block';
            globalLabel.innerText = "Price Per KG (₱)";
        } else if (mode === 'lump_sum') {
            globalInputDiv.style.display = 'block';
            globalLabel.innerText = "Total Fixed Batch Price (₱)";
        }
        applyPricing();
    }

    function applyPricing() {
        const mode = document.querySelector('input[name="price_mode"]:checked').value;
        const globalVal = parseFloat(document.getElementById('global_price_input').value) || 0;
        const activeRows = document.querySelectorAll('#animal_table_body tr');
        
        // Note: rows don't exist if empty state is showing
        if(activeRows.length === 1 && activeRows[0].children.length === 1) { recalc(); return; }

        const count = activeRows.length;

        activeRows.forEach(tr => {
            const input = tr.querySelector('.price-input');
            if(!input) return;
            
            const weight = parseFloat(tr.children[1].getAttribute('data-weight')) || 0;
            
            if (mode === 'individual') {
                input.disabled = false;
            } else {
                input.disabled = true; 
                if (mode === 'per_head') {
                    input.value = globalVal > 0 ? globalVal.toFixed(2) : '';
                } else if (mode === 'per_kg') {
                    input.value = globalVal > 0 ? (weight * globalVal).toFixed(2) : '';
                } else if (mode === 'lump_sum') {
                    input.value = (globalVal > 0 && count > 0) ? (globalVal / count).toFixed(2) : '';
                }
            }
        });
        recalc();
    }

    function recalc() {
        const activeRows = document.querySelectorAll('#animal_table_body tr');
        let totalBaseCost = 0, totalRevenue = 0, totalWeight = 0;
        let count = 0;

        if(!(activeRows.length === 1 && activeRows[0].children.length === 1)) {
            count = activeRows.length;
            activeRows.forEach(tr => {
                const cost = parseFloat(tr.children[2].getAttribute('data-cost')) || 0;
                const weight = parseFloat(tr.children[1].getAttribute('data-weight')) || 0;
                const price = parseFloat(tr.querySelector('.price-input').value) || 0;
                
                totalBaseCost += cost;
                totalWeight += weight;
                totalRevenue += price;
            });
        }

        const overhead = parseFloat(document.getElementById('overhead_cost').value) || 0;
        const totalCost = totalBaseCost + overhead;
        
        // Mode specific adjustment to avoid exact division decimals in Lump Sum
        const mode = document.querySelector('input[name="price_mode"]:checked').value;
        const globalVal = parseFloat(document.getElementById('global_price_input').value) || 0;
        if(mode === 'lump_sum' && globalVal > 0 && count > 0) {
            totalRevenue = globalVal; 
        }

        document.getElementById('summ_count').innerText = count;
        document.getElementById('summ_total_weight').innerText = totalWeight.toFixed(2) + " kg";
        document.getElementById('summ_base_cost').innerText = "₱" + fmt(totalBaseCost);
        document.getElementById('summ_net_worth').innerText = "₱" + fmt(totalCost);
        document.getElementById('total_batch_price_display').innerText = "₱" + fmt(totalRevenue);

        const profit = totalRevenue - totalCost;
        document.getElementById('profitDisplay').innerText = "₱" + fmt(profit);
        const pBox = document.getElementById('profitBox');
        pBox.className = profit >= 0 ? "profit-box profit-pos" : "profit-box profit-neg";

        document.getElementById('btn_submit').disabled = (count === 0 || totalRevenue <= 0);
    }

    // --- FORM SUBMISSION ---
    function submitBatchSale() {
        const buyer = document.getElementById('buyer_name').value;
        const saleDate = document.getElementById('sale_date').value;

        if(!buyer) { alert("Please select a buyer."); return; }
        if(!saleDate) { alert("Please select a Date of Sale."); return; }

        const activeRows = document.querySelectorAll('#animal_table_body tr');
        if(activeRows.length === 0 || (activeRows.length===1 && activeRows[0].children.length===1)) return;

        const payload = new URLSearchParams();
        payload.append('customer_name', buyer);
        payload.append('sale_date', saleDate);
        payload.append('notes', document.getElementById('notes').value);
        
        const count = activeRows.length;
        const overheadTotal = parseFloat(document.getElementById('overhead_cost').value) || 0;
        const overheadPerHead = overheadTotal / count;
        let allPricesValid = true;

        activeRows.forEach(tr => {
            const input = tr.querySelector('.price-input');
            const id = input.getAttribute('data-id');
            const price = parseFloat(input.value) || 0;
            if(price <= 0) allPricesValid = false;
            
            const a = currentBatchData.find(x => x.ANIMAL_ID == id);
            
            payload.append('animal_ids[]', id);
            payload.append(`costs[${id}][sale_price]`, price);
            payload.append(`costs[${id}][overhead]`, overheadPerHead);
            payload.append(`costs[${id}][weight]`, a.CURRENT_ACTUAL_WEIGHT);
            payload.append(`costs[${id}][acq]`, a.ACQUISITION_COST);
            payload.append(`costs[${id}][feed]`, a.cost_feed);
            payload.append(`costs[${id}][med]`, a.cost_med);
            payload.append(`costs[${id}][vac]`, a.cost_vac);
            payload.append(`costs[${id}][vit]`, a.cost_vit);
            payload.append(`costs[${id}][chk]`, a.cost_chk);
        });

        // Force exact lump sum if applicable
        const mode = document.querySelector('input[name="price_mode"]:checked').value;
        const globalInput = parseFloat(document.getElementById('global_price_input').value) || 0;
        if(mode === 'lump_sum' && globalInput > 0) {
            payload.append('exact_lump_sum_total', globalInput); 
        }

        if(!allPricesValid) { alert("Please ensure all selected animals have a valid sale price greater than 0."); return; }
        if(!confirm(`Confirm Bulk Sale to ${buyer}? This action is irreversible.`)) return;

        const btn = document.getElementById('btn_submit');
        btn.disabled = true; btn.innerText = "Processing...";

        fetch('../process/addGroupAnimalSell.php', {
            method: 'POST',
            body: payload
        })
        .then(r => r.text())
        .then(text => {
            try {
                const res = JSON.parse(text);
                if(res.success) {
                    if(res.batch_id) window.open('print_batch_sales_receipt.php?batch_id='+res.batch_id, '_blank');
                    alert("✅ "+res.message); window.location.reload();
                } else { alert("❌ "+res.message); btn.disabled=false; btn.innerText = "Confirm Transaction"; }
            } catch(e) {
                alert("❌ System Error. Server returned non-JSON data."); 
                console.error(text);
                btn.disabled=false; btn.innerText = "Confirm Transaction";
            }
        });
    }
</script>
</body>
</html>