<?php
// views/cost_transfer.php
$page = "farm";
include '../common/navbar.php';
include '../security/checkRole.php';
include '../config/Connection.php';
checkRole(3);

// Fetch Locations
$locations = $conn->query("SELECT * FROM locations ORDER BY LOCATION_NAME")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Animal Cost Transfer - FarmPro</title>
    <style>
        /* Core Styles */
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #0f172a; color: #e2e8f0; margin: 0; }
        .container { max-width: 1200px; margin: 0 auto; padding: 2rem; }
        
        .header { text-align: center; margin-bottom: 2rem; }
        .header h1 { color: #facc15; margin-bottom: 5px; font-size: 2rem; }

        /* Responsive Grid */
        .transfer-grid { display: grid; grid-template-columns: 1fr 1.5fr; gap: 2rem; }
        @media(max-width: 900px) { .transfer-grid { grid-template-columns: 1fr; } }

        .card { background: rgba(30, 41, 59, 0.6); border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; padding: 1.5rem; }
        .card-header { font-size: 1.1rem; font-weight: bold; margin-bottom: 1rem; border-bottom: 1px solid #334155; padding-bottom: 10px; }
        
        .form-group { margin-bottom: 1rem; }
        .form-label { display: block; color: #94a3b8; font-size: 0.9rem; margin-bottom: 5px; }
        .form-select, .form-input { 
            width: 100%; padding: 12px; background: #1e293b; border: 1px solid #475569; 
            color: white; border-radius: 6px; font-size: 1rem; box-sizing: border-box;
        }
        
        .filters { display: flex; gap: 10px; margin-bottom: 10px; flex-wrap: wrap; }
        .filters select { flex: 1; }

        /* Cost Display */
        .cost-display { text-align: center; margin: 1.5rem 0; padding: 1rem; background: rgba(250, 204, 21, 0.1); border-radius: 8px; border: 1px solid #facc15; }
        .cost-amount { font-size: 2.2rem; font-weight: 800; color: #facc15; display: block; }
        .cost-label { color: #fef08a; font-size: 0.9rem; text-transform: uppercase; }

        .breakdown-list { font-size: 0.9rem; color: #cbd5e1; display: grid; gap: 8px; margin-top: 10px; }
        .breakdown-item { display: flex; justify-content: space-between; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 5px; }
        
        .separator { text-align: center; margin: 15px 0; position: relative; }
        .separator::before { content: ""; position: absolute; left: 0; top: 50%; width: 100%; height: 1px; background: #334155; }
        .separator span { background: #151e32; padding: 0 10px; color: #64748b; font-size: 0.8rem; position: relative; }

        .tag-selection-area {
            min-height: 100px; border: 2px dashed #475569; border-radius: 8px;
            padding: 10px; display: flex; flex-wrap: wrap; gap: 10px; background: #0f172a;
        }
        .tag-pill {
            background: #3b82f6; color: white; padding: 6px 12px; border-radius: 20px;
            display: flex; align-items: center; gap: 8px; font-size: 0.9rem; animation: popIn 0.2s ease;
        }
        .tag-remove { cursor: pointer; background: rgba(0,0,0,0.2); border-radius: 50%; width: 20px; height: 20px; display: flex; align-items: center; justify-content: center; }
        
        .btn-transfer {
            width: 100%; padding: 1rem; background: linear-gradient(135deg, #facc15, #ca8a04);
            color: #000; font-weight: 800; border: none; border-radius: 8px; cursor: pointer;
            font-size: 1.1rem; margin-top: 1rem; transition: transform 0.2s;
        }
        .btn-transfer:hover { transform: translateY(-2px); }
        .empty-msg { width: 100%; text-align: center; color: #64748b; font-style: italic; padding: 20px; }
        @keyframes popIn { from { transform: scale(0.8); opacity: 0; } to { transform: scale(1); opacity: 1; } }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <h1>Animal Cost Transfer</h1>
        <p>Distribute Sow Costs to Offspring</p>
    </div>

    <div class="transfer-grid">
        <div class="card">
            <div class="card-header" style="color: #facc15;">1. Select Source Sow</div>
            <label class="form-label">Method A: Select by Location</label>
            <div class="filters">
                <select id="srcLocation" class="form-select" onchange="loadBuildings('src')">
                    <option value="">Loc...</option>
                    <?php foreach($locations as $loc): ?>
                        <option value="<?= $loc['LOCATION_ID'] ?>"><?= $loc['LOCATION_NAME'] ?></option>
                    <?php endforeach; ?>
                </select>
                <select id="srcBuilding" class="form-select" disabled onchange="loadSowsInBuilding()">
                    <option value="">Bldg...</option>
                </select>
            </div>
            <div class="form-group">
                <select id="srcSowSelect" class="form-select" disabled onchange="handleSowSelection(this.value)">
                    <option value="">-- Select Sow --</option>
                </select>
            </div>
            <div class="separator"><span>OR</span></div>
            <div class="form-group">
                <label class="form-label">Method B: Search Tag</label>
                <input type="text" id="sowSearch" class="form-input" placeholder="Type tag..." onkeyup="searchSow(this.value)">
                <select id="sowSearchResults" class="form-select" size="3" style="display:none; margin-top:5px;" onchange="handleSowSelection(this.value); this.style.display='none';"></select>
            </div>

            <div id="sowDetails" style="display:none;">
                <div class="cost-display">
                    <span class="cost-label">Transferable Net Worth</span>
                    <span class="cost-amount">₱ <span id="totalSowCost">0.00</span></span>
                </div>
                <div class="breakdown-list">
                    <div class="breakdown-item"><span><span style="color:#fbbf24;">●</span> Feed</span><span class="breakdown-val">₱ <span id="costFeed">0.00</span></span></div>
                    <div class="breakdown-item"><span><span style="color:#ec4899;">●</span> Meds</span><span class="breakdown-val">₱ <span id="costMeds">0.00</span></span></div>
                    <div class="breakdown-item"><span><span style="color:#ec4899;">●</span> Vac/Vit</span><span class="breakdown-val">₱ <span id="costVacVit">0.00</span></span></div>
                    <div class="breakdown-item"><span><span style="color:#a78bfa;">●</span> Vet</span><span class="breakdown-val">₱ <span id="costCheckup">0.00</span></span></div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header" style="color: #3b82f6;">2. Linked Offspring (Piglets)</div>
            <p style="color:#94a3b8; font-size:0.9rem; margin-bottom:1rem;">Active piglets from this mother with <strong>no cost</strong> yet.</p>
            <div id="pigletBox" class="tag-selection-area"><div class="empty-msg">Select a Sow...</div></div>
            <div style="margin-top: 1.5rem; border-top: 1px solid #334155; padding-top: 1rem;">
                <div style="display:flex; justify-content:space-between; margin-bottom: 5px;"><span>Count:</span><strong id="countPiglets">0</strong></div>
                <div style="display:flex; justify-content:space-between; font-size:1.1rem; color:#facc15;"><span>Cost/Head:</span><strong>₱ <span id="costPerHead">0.00</span></strong></div>
            </div>
            <button class="btn-transfer" onclick="executeTransfer()">Transfer Cost</button>
        </div>
    </div>
</div>

<script>
    let selectedSowId = null, sowTotalCost = 0, selectedPiglets = new Map();

    async function fetchJSON(url) {
        try { const res = await fetch(url); return await res.json(); } 
        catch (e) { console.error(e); return []; }
    }

    function loadBuildings(prefix) {
        const locId = document.getElementById(prefix + 'Location').value;
        const bldgSel = document.getElementById(prefix + 'Building');
        bldgSel.innerHTML = '<option>Loading...</option>'; bldgSel.disabled = true;
        if(!locId) { bldgSel.innerHTML = '<option value="">Bldg...</option>'; return; }
        
        fetchJSON(`../process/getCostData.php?action=get_buildings&loc_id=${locId}`).then(data => {
            bldgSel.innerHTML = '<option value="">Select Building</option>';
            data.forEach(b => bldgSel.innerHTML += `<option value="${b.BUILDING_ID}">${b.BUILDING_NAME}</option>`);
            bldgSel.disabled = false;
        });
    }

    function loadSowsInBuilding() {
        const bldgId = document.getElementById('srcBuilding').value;
        const sowSel = document.getElementById('srcSowSelect');
        sowSel.disabled = true; sowSel.innerHTML = '<option>Loading...</option>';
        fetchJSON(`../process/getCostData.php?action=get_sows_in_building&bldg_id=${bldgId}`).then(data => {
            sowSel.innerHTML = '<option value="">-- Select Sow --</option>';
            if(data.length) { data.forEach(s => sowSel.innerHTML += `<option value="${s.ANIMAL_ID}">${s.TAG_NO}</option>`); sowSel.disabled = false; }
            else { sowSel.innerHTML = '<option value="">No Sows Found</option>'; }
        });
    }

    function searchSow(term) {
        if(term.length < 2) { document.getElementById('sowSearchResults').style.display = 'none'; return; }
        fetchJSON(`../process/getCostData.php?action=search_sow&term=${term}`).then(data => {
            const sel = document.getElementById('sowSearchResults');
            sel.innerHTML = '';
            if(data.length) { 
                data.forEach(s => sel.innerHTML += `<option value="${s.ANIMAL_ID}">${s.TAG_NO}</option>`); 
                sel.style.display = 'block'; 
            } else { sel.style.display = 'none'; }
        });
    }

    function handleSowSelection(id) {
        if(!id) return;
        selectedSowId = id;
        fetchJSON(`../process/getCostData.php?action=get_sow_net_worth&animal_id=${id}`).then(data => {
            document.getElementById('sowDetails').style.display = 'block';
            
            // FIX: Handle Nulls to prevent NaN
            sowTotalCost = parseFloat(data.total || 0); 
            
            document.getElementById('totalSowCost').innerText = numberFormat(sowTotalCost);
            document.getElementById('costFeed').innerText = numberFormat(data.feed || 0);
            document.getElementById('costMeds').innerText = numberFormat(data.meds || 0);
            document.getElementById('costVacVit').innerText = numberFormat((data.vac || 0) + (data.vit || 0));
            document.getElementById('costCheckup').innerText = numberFormat(data.checkup || 0);
            
            loadOffspring(id);
        });
    }

    function loadOffspring(motherId) {
        const box = document.getElementById('pigletBox');
        box.innerHTML = '<div class="empty-msg">Loading...</div>';
        selectedPiglets.clear();
        fetchJSON(`../process/getCostData.php?action=get_piglets_by_mother&mother_id=${motherId}`).then(data => {
            if(data.length) {
                box.innerHTML = '';
                data.forEach(p => {
                    selectedPiglets.set(p.ANIMAL_ID, p.TAG_NO);
                    box.innerHTML += `<div class="tag-pill">${p.TAG_NO} <div class="tag-remove" onclick="removePiglet('${p.ANIMAL_ID}')">✕</div></div>`;
                });
            } else { box.innerHTML = '<div class="empty-msg" style="color:#ef4444;">No eligible piglets found.</div>'; }
            updateCalculations();
        });
    }

    function removePiglet(id) {
        selectedPiglets.delete(String(id));
        const box = document.getElementById('pigletBox');
        box.innerHTML = '';
        if(selectedPiglets.size === 0) box.innerHTML = '<div class="empty-msg">All removed.</div>';
        selectedPiglets.forEach((tag, pid) => {
            box.innerHTML += `<div class="tag-pill">${tag} <div class="tag-remove" onclick="removePiglet('${pid}')">✕</div></div>`;
        });
        updateCalculations();
    }

    function updateCalculations() {
        const count = selectedPiglets.size;
        document.getElementById('countPiglets').innerText = count;
        const share = count > 0 ? (sowTotalCost / count) : 0;
        document.getElementById('costPerHead').innerText = numberFormat(share);
    }

    function numberFormat(num) {
        return parseFloat(num).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    }

    function executeTransfer() {
        if(!selectedSowId || selectedPiglets.size === 0) return alert("Select Sow and Piglets first.");
        if(sowTotalCost <= 0) return alert("Zero cost to transfer.");
        
        if(!confirm("Transfer cost?")) return;

        const payload = new FormData();
        payload.append('sow_id', selectedSowId);
        payload.append('piglet_ids', JSON.stringify(Array.from(selectedPiglets.keys())));
        payload.append('total_amount', sowTotalCost);

        fetch('../process/saveCostTransfer.php', { method: 'POST', body: payload }).then(r => r.json()).then(res => {
            if(res.success) { alert("Transfer Successful!"); location.reload(); }
            else { alert("Error: " + res.message); }
        });
    }
</script>
</body>
</html>