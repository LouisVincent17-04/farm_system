<?php
// views/animal_sales_process.php
ob_start(); 
error_reporting(E_ALL);
ini_set('display_errors', 0); 

$page = "transactions";
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
            $stmt = $conn->prepare("SELECT BUILDING_ID, BUILDING_NAME FROM buildings WHERE LOCATION_ID = ? ORDER BY BUILDING_NAME");
            $stmt->execute([$_GET['location_id']]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC)); exit;
        }
        if ($action === 'get_pens' && isset($_GET['building_id'])) {
            $stmt = $conn->prepare("SELECT PEN_ID, PEN_NAME FROM pens WHERE BUILDING_ID = ? ORDER BY PEN_NAME");
            $stmt->execute([$_GET['building_id']]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC)); exit;
        }
        if ($action === 'get_animals' && isset($_GET['pen_id'])) {
            $stmt = $conn->prepare("SELECT ANIMAL_ID, TAG_NO FROM animal_records WHERE PEN_ID = ? AND IS_ACTIVE = 1 AND CURRENT_STATUS != 'Sold' ORDER BY TAG_NO");
            $stmt->execute([$_GET['pen_id']]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC)); exit;
        }
        if ($action === 'search_animal_by_tag' && isset($_GET['tag'])) {
            $tag = trim($_GET['tag']);
            $stmt = $conn->prepare("SELECT ANIMAL_ID FROM animal_records WHERE TAG_NO = ? AND IS_ACTIVE = 1 AND CURRENT_STATUS != 'Sold'");
            $stmt->execute([$tag]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode(['success' => (bool)$result, 'animal_id' => $result['ANIMAL_ID'] ?? null]); exit;
        }
        if ($action === 'get_animal_costs' && isset($_GET['animal_id'])) {
            $id = $_GET['animal_id'];
            $stmt = $conn->prepare("SELECT * FROM animal_records WHERE ANIMAL_ID = ?");
            $stmt->execute([$id]);
            $animal = $stmt->fetch(PDO::FETCH_ASSOC);

            // Fetch cumulative costs
            $feed = $conn->prepare("SELECT COALESCE(SUM(TRANSACTION_COST), 0) FROM feed_transactions WHERE ANIMAL_ID = ?"); $feed->execute([$id]);
            $med  = $conn->prepare("SELECT COALESCE(SUM(TOTAL_COST), 0) FROM treatment_transactions WHERE ANIMAL_ID = ?"); $med->execute([$id]);
            $vac  = $conn->prepare("SELECT COALESCE(SUM(VACCINATION_COST + VACCINE_COST), 0) FROM vaccination_records WHERE ANIMAL_ID = ?"); $vac->execute([$id]);
            $vit  = $conn->prepare("SELECT COALESCE(SUM(TOTAL_COST), 0) FROM vitamins_supplements_transactions WHERE ANIMAL_ID = ?"); $vit->execute([$id]);
            $chk  = $conn->prepare("SELECT COALESCE(SUM(COST), 0) FROM check_ups WHERE ANIMAL_ID = ?"); $chk->execute([$id]);

            echo json_encode([
                'success' => true,
                'animal' => $animal,
                'costs' => [
                    'acquisition' => $animal['ACQUISITION_COST'] ?? 0,
                    'feed'        => $feed->fetchColumn(),
                    'medication'  => $med->fetchColumn(),
                    'vaccination' => $vac->fetchColumn(),
                    'vitamins'    => $vit->fetchColumn(),
                    'checkup'     => $chk->fetchColumn()
                ]
            ]);
            exit;
        }
    } catch (Exception $e) { echo json_encode(['error' => $e->getMessage()]); exit; }
}

// =========================================================
// 2. PROCESS SALE
// =========================================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['confirm_sale'])) {
    try {
        $conn->beginTransaction();

        $animal_id = $_POST['animal_id'];
        $buyer_name = $_POST['customer_name']; // Now this comes from dropdown but holds the NAME (or ID if you prefer)
        
        // Costs
        $net_worth = $_POST['cost_acquisition'] + $_POST['cost_feed'] + $_POST['cost_medication'] + 
                     $_POST['cost_vaccination'] + $_POST['cost_checkup'] + $_POST['cost_vitamins'] + 
                     $_POST['cost_overhead'];
        
        $final_price = floatval($_POST['final_sale_price']);
        $weight = floatval($_POST['weight_at_sale']);
        $profit = $final_price - $net_worth;
        $price_per_kg = ($weight > 0) ? ($final_price / $weight) : 0;
        $current_user = $_SESSION['user_id'] ?? 0; 

        // Insert Sale Record
        $sql = "INSERT INTO animal_sales 
            (animal_id, customer_name, weight_at_sale, price_per_kg, final_sale_price, 
             cost_acquisition, cost_feed_total, cost_medication_total, cost_vaccination_total, 
             cost_checkup_total, cost_vitamins_total, cost_overhead, total_net_worth, gross_profit, notes, created_by) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            $animal_id, $buyer_name, $weight, $price_per_kg, $final_price,
            $_POST['cost_acquisition'], $_POST['cost_feed'], $_POST['cost_medication'],
            $_POST['cost_vaccination'], $_POST['cost_checkup'], $_POST['cost_vitamins'],
            $_POST['cost_overhead'], $net_worth, $profit, $_POST['notes'], 
            $current_user
        ]);

        // Archive Animal
        $updateStmt = $conn->prepare("UPDATE animal_records SET CURRENT_STATUS = 'Sold', IS_ACTIVE = 0, CURRENT_ACTUAL_WEIGHT = ? WHERE ANIMAL_ID = ?");
        $updateStmt->execute([$weight, $animal_id]);

        $conn->commit();
        $_SESSION['flash_message'] = "<div class='alert alert-success'>✅ Sale Confirmed! Profit: ₱" . number_format($profit, 2) . "</div>";
        header("Location: " . $_SERVER['PHP_SELF']); 
        exit();

    } catch (Exception $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        $_SESSION['flash_message'] = "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>";
        header("Location: " . $_SERVER['PHP_SELF']); 
        exit();
    }
}

// 3. FETCH DATA
$message = "";
if (isset($_SESSION['flash_message'])) {
    $message = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']);
}

// Fetch Buyers for Dropdown
$buyers = $conn->query("SELECT FULL_NAME FROM buyers WHERE IS_ACTIVE = 1 ORDER BY FULL_NAME ASC")->fetchAll(PDO::FETCH_ASSOC);

$recentStmt = $conn->query("SELECT s.*, a.TAG_NO FROM animal_sales s JOIN animal_records a ON s.animal_id = a.ANIMAL_ID ORDER BY s.sale_date DESC LIMIT 5");
$recent_sales = $recentStmt->fetchAll(PDO::FETCH_ASSOC);

$statsStmt = $conn->query("SELECT COALESCE(SUM(gross_profit), 0) as total_profit, COALESCE(SUM(final_sale_price), 0) as total_rev FROM animal_sales WHERE DATE(sale_date) = CURDATE()");
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

$locations = $conn->query("SELECT * FROM locations ORDER BY LOCATION_NAME")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Terminal - FarmPro</title>
    <style>
        /* [Reusing styles] */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: #e2e8f0; min-height: 100vh; }
        .container { max-width: 1600px; margin: 0 auto; padding: 1.5rem; }
        .page-header { text-align: center; margin-bottom: 2rem; }
        .page-title { font-size: 2rem; font-weight: 800; margin-bottom: 0.5rem; background: linear-gradient(135deg, #fbbf24, #d97706); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .main-grid { display: grid; grid-template-columns: 350px 1fr; gap: 2rem; align-items: start; }
        .glass-card { background: rgba(30, 41, 59, 0.6); border: 1px solid rgba(255,255,255,0.05); border-radius: 16px; padding: 1.5rem; backdrop-filter: blur(12px); box-shadow: 0 10px 30px -10px rgba(0,0,0,0.5); }
        .form-group { margin-bottom: 1.2rem; }
        .form-group label { display: block; color: #94a3b8; font-size: 0.75rem; font-weight: 700; margin-bottom: 0.4rem; text-transform: uppercase; letter-spacing: 0.5px; }
        .form-select, .form-input, .form-textarea { width: 100%; padding: 12px; background: #0f172a; border: 1px solid #334155; color: white; border-radius: 8px; font-size: 0.95rem; transition: all 0.2s; }
        .form-input:focus, .form-select:focus { border-color: #fbbf24; outline: none; box-shadow: 0 0 0 2px rgba(251, 191, 36, 0.2); }
        .form-input[readonly] { cursor: not-allowed; opacity: 0.8; background-color: #0f172a; color: #64748b; }
        .search-divider { text-align: center; margin: 1.5rem 0; position: relative; color: #64748b; font-size: 0.75rem; font-weight: bold; }
        .search-divider::before, .search-divider::after { content: ""; position: absolute; top: 50%; width: 35%; height: 1px; background: #334155; }
        .search-divider::before { left: 0; } .search-divider::after { right: 0; }
        .btn-search { background: #3b82f6; color: white; border: none; padding: 0 16px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: background 0.2s; }
        .btn-search:hover { background: #2563eb; }
        #dashboard_view { display: block; animation: fadeIn 0.5s ease; }
        .stats-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem; }
        .stat-box { background: rgba(15, 23, 42, 0.6); padding: 1.5rem; border-radius: 12px; border: 1px solid rgba(255,255,255,0.05); text-align: center; }
        .stat-num { font-size: 2rem; font-weight: 800; margin-top: 5px; }
        .stat-label { color: #94a3b8; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 1px; }
        .recent-table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        .recent-table th { text-align: left; color: #64748b; font-size: 0.8rem; padding: 10px; border-bottom: 1px solid #334155; }
        .recent-table td { padding: 12px 10px; color: #cbd5e1; border-bottom: 1px solid rgba(255,255,255,0.02); font-size: 0.9rem; }
        .recent-table tr:last-child td { border-bottom: none; }
        #salesFormContainer { display: none; }
        #salesFormContainer.active { display: block; animation: slideUp 0.4s cubic-bezier(0.16, 1, 0.3, 1); }
        .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; border-bottom: 1px dashed #334155; padding-bottom: 1rem; }
        .tag-badge { background: #fbbf24; color: #0f172a; padding: 4px 12px; border-radius: 20px; font-weight: 800; font-size: 1.2rem; }
        .cost-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid rgba(255,255,255,0.02); color: #94a3b8; font-size: 0.9rem; }
        .cost-row span:last-child { font-family: 'Courier New', monospace; font-weight: 600; color: #e2e8f0; }
        .total-row { border-top: 1px solid #475569; margin-top: 10px; padding-top: 10px; color: #e2e8f0; font-weight: 700; font-size: 1rem; }
        .profit-box { background: #0f172a; padding: 1.5rem; border-radius: 12px; text-align: center; margin-top: 2rem; border: 2px solid #334155; }
        .profit-pos { border-color: #10b981; background: rgba(16, 185, 129, 0.1); color: #10b981; }
        .profit-neg { border-color: #ef4444; background: rgba(239, 68, 68, 0.1); color: #ef4444; }
        .btn-confirm { width: 100%; padding: 1rem; background: linear-gradient(135deg, #fbbf24, #d97706); color: #0f172a; font-weight: 800; border: none; border-radius: 8px; cursor: pointer; margin-top: 1rem; font-size: 1rem; text-transform: uppercase; letter-spacing: 1px; transition: transform 0.2s, box-shadow 0.2s; }
        .btn-confirm:hover { transform: translateY(-2px); box-shadow: 0 10px 20px rgba(251, 191, 36, 0.2); }
        .btn-cancel { background: transparent; color: #64748b; border: none; cursor: pointer; font-size: 0.9rem; }
        .btn-cancel:hover { color: #e2e8f0; text-decoration: underline; }
        .alert { padding: 1rem; border-radius: 8px; margin-bottom: 2rem; text-align: center; font-weight: bold; }
        .alert-success { background: rgba(16, 185, 129, 0.2); color: #10b981; border: 1px solid #10b981; }
        .alert-danger { background: rgba(239, 68, 68, 0.2); color: #ef4444; border: 1px solid #ef4444; }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes slideUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        @media (max-width: 900px) { .main-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>

<div class="container">
    <header class="page-header">
        <h1 class="page-title">💰 Animal Sales Processing</h1>
        <p style="color: #64748b;">Point of Sale Terminal</p>
    </header>

    <?= $message ?>

    <div class="main-grid">
        
        <div class="glass-card">
            <h3 style="color: #fbbf24; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 8px;">🔍 Find Animal</h3>
            <div class="form-group"><label>1. Location</label><select id="location_id" class="form-select" onchange="loadBuildings()"><option value="">-- Select Location --</option><?php foreach ($locations as $loc): ?><option value="<?= $loc['LOCATION_ID'] ?>"><?= htmlspecialchars($loc['LOCATION_NAME']) ?></option><?php endforeach; ?></select></div>
            <div class="form-group"><label>2. Building</label><select id="building_id" class="form-select" onchange="loadPens()" disabled><option value="">-- Select Building --</option></select></div>
            <div class="form-group"><label>3. Pen</label><select id="pen_id" class="form-select" onchange="loadAnimals()" disabled><option value="">-- Select Pen --</option></select></div>
            <div class="form-group"><label>4. Select Tag to Sell</label><select id="animal_id_select" class="form-select" onchange="fetchAnimalCosts(this.value)" disabled><option value="">-- Select Animal Tag --</option></select></div>
            <div class="search-divider">OR SEARCH BY TAG</div>
            <div class="form-group" style="display: flex; gap: 8px;"><input type="text" id="search_tag_input" class="form-input" placeholder="Enter Tag No (e.g., A001)"><button type="button" class="btn-search" onclick="searchByTag()">GO</button></div>
            <div id="search_error" style="color: #ef4444; font-size: 0.8rem; text-align: center; margin-top: 5px;"></div>
        </div>

        <div class="right-column-wrapper">
            <div id="dashboard_view">
                <div class="stats-row">
                    <div class="stat-box"><div class="stat-label">Gross Profit Today</div><div class="stat-num" style="color: #34d399;">₱<?= number_format($stats['total_profit'], 2) ?></div></div>
                    <div class="stat-box"><div class="stat-label">Revenue Today</div><div class="stat-num" style="color: #fbbf24;">₱<?= number_format($stats['total_rev'], 2) ?></div></div>
                </div>
                <div class="glass-card">
                    <h3 style="color: #94a3b8; font-size: 0.9rem; text-transform: uppercase; margin-bottom: 1rem;">Recent Transactions</h3>
                    <?php if(empty($recent_sales)): ?> <p style="color: #64748b; text-align: center; padding: 2rem;">No recent sales found.</p> <?php else: ?>
                        <table class="recent-table"><thead><tr><th>Tag</th><th>Buyer</th><th>Date</th><th style="text-align: right;">Amount</th></tr></thead><tbody><?php foreach($recent_sales as $sale): ?><tr><td style="color: #fbbf24; font-weight: bold;"><?= $sale['TAG_NO'] ?></td><td><?= htmlspecialchars($sale['customer_name']) ?></td><td><?= date('M d, H:i', strtotime($sale['sale_date'])) ?></td><td style="text-align: right; font-family: monospace;">₱<?= number_format($sale['final_sale_price'], 2) ?></td></tr><?php endforeach; ?></tbody></table>
                    <?php endif; ?>
                </div>
            </div>

            <div id="salesFormContainer" class="glass-card">
                <form method="POST">
                    <input type="hidden" name="animal_id" id="hidden_animal_id">
                    
                    <div class="section-header">
                        <div><div style="color: #94a3b8; font-size: 0.8rem;">PROCESSING SALE FOR</div><div class="tag-badge" id="disp_tag_header"></div></div>
                        <button type="button" class="btn-cancel" onclick="resetView()">Cancel / Close</button>
                    </div>

                    <div class="form-group">
                        <label>Buyer Name</label>
                        <div style="display:flex; gap:10px;">
                            <select name="customer_name" class="form-select" required>
                                <option value="">-- Select Registered Buyer --</option>
                                <?php foreach($buyers as $b): ?>
                                    <option value="<?= htmlspecialchars($b['FULL_NAME']) ?>"><?= htmlspecialchars($b['FULL_NAME']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <a href="buyers.php" target="_blank" class="btn-search" style="text-decoration:none; display:flex; align-items:center; justify-content:center; width:40px; height:auto; padding:0; background:rgba(59, 130, 246, 0.2); color:#60a5fa;" title="Manage Buyers">
                                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                            </a>
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom: 15px;">
                        <label style="margin-bottom: 8px;">Pricing Mode</label>
                        <div style="display: flex; gap: 15px;">
                            <label style="cursor: pointer; display: flex; align-items: center; gap: 5px; color: #fff;"><input type="radio" name="price_mode" value="lump" checked onchange="togglePriceMode()"> Fixed Total Price</label>
                            <label style="cursor: pointer; display: flex; align-items: center; gap: 5px; color: #fff;"><input type="radio" name="price_mode" value="per_kg" onchange="togglePriceMode()"> Calculate per KG</label>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group"><label>Animal Weight (kg) <small style="color: #64748b;">(Read Only)</small></label><input type="number" step="0.01" name="weight_at_sale" id="weight" class="form-input" readonly></div>

                        <div class="form-group">
                            <div id="div_total_price">
                                <label style="color: #fbbf24;">Final Sale Price (₱)</label>
                                <input type="number" step="0.01" name="final_sale_price" id="final_sale_price" class="form-input" required placeholder="0.00" oninput="updateDisplayAndCalc()" style="border-color: #fbbf24;">
                            </div>
                            <div id="div_price_per_kg" style="display: none;">
                                <label style="color: #3b82f6;">Price per KG (₱)</label>
                                <input type="number" step="0.01" id="price_per_kg_input" class="form-input" placeholder="e.g. 250.00" oninput="calculateFromKg()" style="border-color: #3b82f6;">
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Total Price (Calculated)</label>
                        <input type="text" id="final_sale_display" class="form-input" readonly style="font-weight: bold; color: #fbbf24; font-size: 1.1rem; text-align: right;" value="₱0.00">
                    </div>

                    <div style="background: rgba(15,23,42,0.5); padding: 1rem; border-radius: 8px; margin: 1.5rem 0;">
                        <h4 style="color: #94a3b8; font-size: 0.8rem; text-transform: uppercase; margin-bottom: 10px;">Net Worth Calculation</h4>
                        <input type="hidden" name="cost_acquisition" id="c_acq"><input type="hidden" name="cost_feed" id="c_feed"><input type="hidden" name="cost_medication" id="c_med"><input type="hidden" name="cost_vaccination" id="c_vac"><input type="hidden" name="cost_vitamins" id="c_vit"><input type="hidden" name="cost_checkup" id="c_chk">
                        <div class="cost-row"><span>Acquisition</span> <span id="disp_acq">0.00</span></div>
                        <div class="cost-row"><span>Feeds</span> <span id="disp_feed">0.00</span></div>
                        <div class="cost-row"><span>Medical & Health</span> <span id="disp_health">0.00</span></div>
                        <div class="cost-row" style="align-items: center;"><span>Overhead (Adjustable)</span> <input type="number" name="cost_overhead" id="c_ovr" value="0.00" step="0.01" oninput="calculateProfit()" style="width: 80px; padding: 2px; background: transparent; border: 1px solid #475569; color: #fbbf24; text-align: right;"></div>
                        <div class="cost-row total-row"><span>TOTAL NET WORTH</span> <span id="disp_total_cost" style="color: #f472b6;">₱0.00</span></div>
                    </div>

                    <div class="form-group"><label>Notes</label><textarea name="notes" class="form-textarea" placeholder="Optional details..."></textarea></div>

                    <div id="profitBox" class="profit-box">
                        <div style="color: #94a3b8; font-size: 0.8rem; text-transform: uppercase;">Estimated Profit</div>
                        <div style="font-size: 2.5rem; font-weight: 800; margin-top: 5px;" id="profitDisplay">₱0.00</div>
                    </div>

                    <button type="submit" name="confirm_sale" class="btn-confirm" onclick="return confirm('Confirm sale? This is irreversible.')">Process Transaction</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    async function fetchData(url) { try { const res = await fetch(`${window.location.pathname.split("/").pop()}${url}`); return await res.json(); } catch(e) { return []; } }
    function fmt(v) { return parseFloat(v).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}); }

    function showDashboard() { document.getElementById('dashboard_view').style.display = 'block'; document.getElementById('salesFormContainer').style.display = 'none'; }
    function showForm() { document.getElementById('dashboard_view').style.display = 'none'; document.getElementById('salesFormContainer').style.display = 'block'; setTimeout(() => document.getElementById('salesFormContainer').classList.add('active'), 10); }
    function resetView() { document.getElementById('location_id').value = ""; resetSelects(['building_id', 'pen_id', 'animal_id_select']); document.getElementById('search_tag_input').value = ""; showDashboard(); }

    async function loadBuildings() { const locId = document.getElementById('location_id').value; const bSelect = document.getElementById('building_id'); resetSelects(['building_id', 'pen_id', 'animal_id_select']); if(locId) { const data = await fetchData(`?action=get_buildings&location_id=${locId}`); fillSelect(bSelect, data, 'BUILDING_ID', 'BUILDING_NAME'); bSelect.disabled = false; } }
    async function loadPens() { const bId = document.getElementById('building_id').value; const pSelect = document.getElementById('pen_id'); resetSelects(['pen_id', 'animal_id_select']); if(bId) { const data = await fetchData(`?action=get_pens&building_id=${bId}`); fillSelect(pSelect, data, 'PEN_ID', 'PEN_NAME'); pSelect.disabled = false; } }
    async function loadAnimals() { const pId = document.getElementById('pen_id').value; const aSelect = document.getElementById('animal_id_select'); resetSelects(['animal_id_select']); if(pId) { const data = await fetchData(`?action=get_animals&pen_id=${pId}`); fillSelect(aSelect, data, 'ANIMAL_ID', 'TAG_NO'); aSelect.disabled = false; } }
    function resetSelects(ids) { ids.forEach(id => { const el = document.getElementById(id); el.innerHTML = '<option value="">-- Select --</option>'; el.disabled = true; }); showDashboard(); }
    function fillSelect(el, data, valKey, txtKey) { el.innerHTML = '<option value="">-- Select --</option>'; data.forEach(i => el.innerHTML += `<option value="${i[valKey]}">${i[txtKey]}</option>`); }

    async function searchByTag() { const tag = document.getElementById('search_tag_input').value; const err = document.getElementById('search_error'); err.innerText = ""; if(!tag) return; const res = await fetchData(`?action=search_animal_by_tag&tag=${encodeURIComponent(tag)}`); if(res.success) fetchAnimalCosts(res.animal_id); else err.innerText = "Tag not found or unavailable."; }

    async function fetchAnimalCosts(id) {
        if(!id) { showDashboard(); return; }
        const res = await fetchData(`?action=get_animal_costs&animal_id=${id}`);
        if(res.success) {
            const c = res.costs;
            document.getElementById('hidden_animal_id').value = id;
            document.getElementById('disp_tag_header').innerText = res.animal.TAG_NO;
            document.getElementById('weight').value = res.animal.CURRENT_ACTUAL_WEIGHT || 0;
            document.getElementById('c_acq').value = c.acquisition; 
            document.getElementById('c_feed').value = c.feed;
            document.getElementById('c_med').value = c.medication; 
            document.getElementById('c_vac').value = c.vaccination;
            document.getElementById('c_vit').value = c.vitamins; 
            document.getElementById('c_chk').value = c.checkup;
            document.getElementById('disp_acq').innerText = fmt(c.acquisition);
            document.getElementById('disp_feed').innerText = fmt(c.feed);
            document.getElementById('disp_health').innerText = fmt(parseFloat(c.medication) + parseFloat(c.vaccination) + parseFloat(c.vitamins) + parseFloat(c.checkup));
            
            document.querySelector('input[name="price_mode"][value="lump"]').checked = true;
            togglePriceMode();
            showForm();
            calculateProfit();
        }
    }

    function togglePriceMode() {
        const mode = document.querySelector('input[name="price_mode"]:checked').value;
        const divTotal = document.getElementById('div_total_price');
        const divPerKg = document.getElementById('div_price_per_kg');
        const finalInput = document.getElementById('final_sale_price');
        
        if (mode === 'per_kg') {
            divTotal.style.display = 'none'; divPerKg.style.display = 'block';
            finalInput.readOnly = true; 
            calculateFromKg();
        } else {
            divTotal.style.display = 'block'; divPerKg.style.display = 'none';
            finalInput.readOnly = false; 
            calculateProfit();
        }
    }

    function updateDisplayAndCalc() {
        const val = document.getElementById('final_sale_price').value;
        document.getElementById('final_sale_display').value = "₱" + fmt(val || 0);
        calculateProfit();
    }

    function calculateFromKg() {
        const weight = parseFloat(document.getElementById('weight').value) || 0;
        const pricePerKg = parseFloat(document.getElementById('price_per_kg_input').value) || 0;
        const total = weight * pricePerKg;
        document.getElementById('final_sale_price').value = total.toFixed(2);
        document.getElementById('final_sale_display').value = "₱" + fmt(total);
        calculateProfit();
    }

    function calculateProfit() {
        const acq = parseFloat(document.getElementById('c_acq').value)||0;
        const feed = parseFloat(document.getElementById('c_feed').value)||0;
        const med = parseFloat(document.getElementById('c_med').value)||0;
        const vac = parseFloat(document.getElementById('c_vac').value)||0;
        const vit = parseFloat(document.getElementById('c_vit').value)||0;
        const chk = parseFloat(document.getElementById('c_chk').value)||0; 
        const ovr = parseFloat(document.getElementById('c_ovr').value)||0;
        const currentCost = acq + feed + med + vac + vit + chk + ovr;
        document.getElementById('disp_total_cost').innerText = "₱" + fmt(currentCost);

        const price = parseFloat(document.getElementById('final_sale_price').value)||0;
        const profit = price - currentCost;
        
        const pBox = document.getElementById('profitBox');
        document.getElementById('profitDisplay').innerText = "₱" + fmt(profit);
        pBox.className = profit >= 0 ? "profit-box profit-pos" : "profit-box profit-neg";

        if(document.querySelector('input[name="price_mode"]:checked').value === 'lump') {
             document.getElementById('final_sale_display').value = "₱" + fmt(price);
        }
    }

    document.getElementById('search_tag_input').addEventListener("keypress", function(e) {
        if (e.key === "Enter") { e.preventDefault(); searchByTag(); }
    });
</script>

</body>
</html>