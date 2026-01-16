<?php
// views/animal_cost_transfers.php
$page = "farm";
include '../common/navbar.php';
include '../security/checkRole.php';
include '../config/Connection.php';
checkRole(2);

$locations = $conn->query("SELECT * FROM locations ORDER BY LOCATION_NAME")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> <title>Strict Cost Transfer</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background: #0f172a; color: #e2e8f0; margin: 0; }
        
        .container { 
            max-width: 1200px; 
            margin: 0 auto; 
            padding: 2rem; 
        }

        .header { text-align: center; margin-bottom: 2rem; }
        
        /* DESKTOP GRID (Default) */
        .transfer-grid { 
            display: grid; 
            grid-template-columns: 1fr 1.5fr; 
            gap: 2rem; 
            align-items: start;
        }

        .card { 
            background: rgba(30, 41, 59, 0.6); 
            border: 1px solid rgba(255,255,255,0.1); 
            border-radius: 12px; 
            padding: 1.5rem; 
        }

        .form-select, .form-input { 
            width: 100%; 
            padding: 12px; /* Larger touch target */
            background: #1e293b; 
            border: 1px solid #475569; 
            color: white; 
            border-radius: 6px; 
            box-sizing: border-box; 
            font-size: 1rem;
        }

        .filters { 
            display: flex; 
            gap: 8px; 
            margin-bottom: 10px; 
        }

        .tag-selection-area { 
            min-height: 100px; 
            border: 2px dashed #475569; 
            padding: 10px; 
            display: flex; 
            flex-wrap: wrap; 
            gap: 10px; 
            border-radius: 8px;
        }

        .tag-pill { 
            background: #3b82f6; 
            padding: 6px 12px; 
            border-radius: 20px; 
            font-size: 0.9rem; 
            display: flex; 
            align-items: center;
            gap: 8px; 
            cursor: default;
        }

        .tag-remove { 
            cursor: pointer; 
            color: white; 
            background: rgba(0,0,0,0.2);
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
        }
        
        .cost-display { 
            text-align: center; 
            padding: 15px; 
            border: 1px solid #facc15; 
            border-radius: 8px; 
            background: rgba(250, 204, 21, 0.05); 
        }
        
        .cost-total { font-size: 2rem; font-weight: 800; color: #facc15; word-break: break-all; }
        
        .breakdown-box { margin-top: 15px; background: #1e293b; padding: 15px; border-radius: 8px; }
        
        .breakdown-row { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-bottom: 15px; 
            font-size: 0.95rem; 
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .cost-input { 
            width: 120px; 
            padding: 10px; 
            text-align: right; 
            background: #0f172a; 
            border: 1px solid #475569; 
            color: white; 
            font-weight: bold; 
            font-size: 1rem;
            border-radius: 6px;
        }
        
        .cost-input.error { border-color: #ef4444; color: #ef4444; }
        
        .btn-transfer { 
            width: 100%; 
            padding: 15px; 
            background: #facc15; 
            color: black; 
            font-weight: bold; 
            border: none; 
            border-radius: 6px; 
            cursor: pointer; 
            margin-top: 15px; 
            font-size: 1.1rem;
        }
        .btn-transfer:hover { background: #eab308; }
        
        .limit-hint { font-size: 0.8rem; color: #94a3b8; margin-left: 5px; }

        /* --- MOBILE RESPONSIVENESS --- */
        @media (max-width: 768px) {
            .container { padding: 1rem; }
            
            .transfer-grid { 
                grid-template-columns: 1fr; /* Stack columns */
                gap: 1.5rem; 
            }

            .filters { 
                flex-direction: column; /* Stack dropdowns vertically */
                gap: 10px;
            }

            .breakdown-row {
                flex-direction: column; /* Stack Label and Input */
                align-items: flex-start;
            }

            .cost-input {
                width: 100%; /* Full width inputs on mobile */
                text-align: left;
            }
            
            .card { padding: 1rem; }

            .page-title { font-size: 1.5rem; }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <h1 style="color:#facc15; margin:0; font-size: clamp(1.5rem, 4vw, 2.5rem);">Strict Cost Transfer</h1>
    </div>

    <div class="transfer-grid">
        <div class="card">
            <h3 style="color:#facc15; border-bottom:1px solid #334155; padding-bottom:10px; margin-top:0;">1. Source Parents</h3>
            
            <label style="color:#f472b6; font-size:0.95rem; display:block; margin-bottom:5px; font-weight:bold;">Dam (Sow)</label>
            <div class="filters">
                <select id="sowLoc" class="form-select" onchange="loadBuildings('sow')">
                    <option value="">Loc...</option>
                    <?php foreach($locations as $l): ?><option value="<?= $l['LOCATION_ID'] ?>"><?= $l['LOCATION_NAME'] ?></option><?php endforeach; ?>
                </select>
                <select id="sowBld" class="form-select" disabled onchange="loadPens('sow')"><option value="">Bldg...</option></select>
                <select id="sowPen" class="form-select" disabled onchange="loadAnimals('sow')"><option value="">Pen...</option></select>
            </div>
            <select id="sowSelect" class="form-select" style="margin-bottom:20px;" disabled onchange="handleParentSelection()"><option value="">-- Select Sow --</option></select>

            <label style="color:#60a5fa; font-size:0.95rem; display:block; margin-bottom:5px; font-weight:bold;">Sire (Boar)</label>
            <div class="filters">
                <select id="boarLoc" class="form-select" onchange="loadBuildings('boar')">
                    <option value="">Loc...</option>
                    <?php foreach($locations as $l): ?><option value="<?= $l['LOCATION_ID'] ?>"><?= $l['LOCATION_NAME'] ?></option><?php endforeach; ?>
                </select>
                <select id="boarBld" class="form-select" disabled onchange="loadPens('boar')"><option value="">Bldg...</option></select>
                <select id="boarPen" class="form-select" disabled onchange="loadAnimals('boar')"><option value="">Pen...</option></select>
            </div>
            <select id="boarSelect" class="form-select" disabled onchange="handleParentSelection()"><option value="">-- Select Boar --</option></select>

            <div id="costDetails" style="margin-top:25px; opacity:0.5; transition: opacity 0.3s ease;">
                <div class="cost-display">
                    <div style="font-size:0.85rem; color:#fef08a; text-transform:uppercase; margin-bottom:5px;">Transferable Total</div>
                    <div class="cost-total" id="totalDisplay">₱ 0.00</div>
                </div>

                <div class="breakdown-box">
                    <div class="breakdown-row">
                        <span style="color:#f472b6; font-weight:500;">Sow Cost <br><span class="limit-hint">(Max: <span id="sowMax">0.00</span>)</span></span>
                        <input type="number" id="sowCostInput" class="cost-input" value="0.00" oninput="validateInput('sow')" placeholder="0.00">
                    </div>
                    <div class="breakdown-row">
                        <span style="color:#60a5fa; font-weight:500;">Boar Cost <br><span class="limit-hint">(Max: <span id="boarMax">0.00</span>)</span></span>
                        <input type="number" id="boarCostInput" class="cost-input" value="0.00" oninput="validateInput('boar')" placeholder="0.00">
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <h3 style="color:#3b82f6; border-bottom:1px solid #334155; padding-bottom:10px; margin-top:0;">2. Piglets</h3>
            <div id="pigletBox" class="tag-selection-area"><div style="width:100%; text-align:center; color:#64748b; padding-top:30px;">Select Sow first</div></div>
            
            <div style="margin-top:20px; display:flex; justify-content:space-between; flex-wrap:wrap; gap:10px; font-weight:bold; background:#1e293b; padding:15px; border-radius:8px;">
                <span>Count: <span id="countPiglets" style="color:white; margin-left:5px;">0</span></span>
                <span>Cost/Head: <span id="costPerHead" style="color:#facc15; margin-left:5px;">₱ 0.00</span></span>
            </div>
            
            <button class="btn-transfer" onclick="submitTransfer()">Transfer Cost</button>
        </div>
    </div>
</div>

<script>
    let selectedPiglets = new Map();
    let limits = { sow: 0, boar: 0 }; // Store strict limits here

    async function fetchJSON(url) {
        try { const r = await fetch(url); return await r.json(); } catch(e) { return []; }
    }

    // --- Loading Logic ---
    function loadBuildings(t) { const l=document.getElementById(t+'Loc').value; const b=document.getElementById(t+'Bld'); const p=document.getElementById(t+'Pen'); b.disabled=true; b.innerHTML='<option>Loading...</option>'; p.disabled=true; p.innerHTML='<option value="">Pen...</option>'; fetchJSON(`../process/getCostData.php?action=get_buildings&loc_id=${l}`).then(d=>{ b.innerHTML='<option value="">Bldg...</option>'; d.forEach(i=>b.innerHTML+=`<option value="${i.BUILDING_ID}">${i.BUILDING_NAME}</option>`); b.disabled=false; }); }
    function loadPens(t) { const b=document.getElementById(t+'Bld').value; const p=document.getElementById(t+'Pen'); p.disabled=true; p.innerHTML='<option>Loading...</option>'; fetchJSON(`../process/getCostData.php?action=get_pens&bld_id=${b}`).then(d=>{ p.innerHTML='<option value="">Pen...</option>'; d.forEach(i=>p.innerHTML+=`<option value="${i.PEN_ID}">${i.PEN_NAME}</option>`); p.disabled=false; }); }
    function loadAnimals(t) { const p=document.getElementById(t+'Pen').value; const s=document.getElementById(t+'Select'); s.disabled=true; s.innerHTML='<option>Loading...</option>'; const act=t==='sow'?'get_sows_in_pen':'get_boars_in_pen'; fetchJSON(`../process/getCostData.php?action=${act}&pen_id=${p}`).then(d=>{ s.innerHTML='<option value="">Select...</option>'; d.forEach(i=>s.innerHTML+=`<option value="${i.ANIMAL_ID}">${i.TAG_NO}</option>`); s.disabled=false; }); }

    // --- STRICT HANDLING ---
    async function handleParentSelection() {
        const sowId = document.getElementById('sowSelect').value;
        const boarId = document.getElementById('boarSelect').value;
        
        document.getElementById('costDetails').style.opacity = '1';

        // Get Sow Data
        if(sowId) {
            const data = await fetchJSON(`../process/getCostData.php?action=get_sow_net_worth&animal_id=${sowId}`);
            limits.sow = parseFloat(data.total || 0); // SET LIMIT
            document.getElementById('sowMax').innerText = limits.sow.toLocaleString('en-US', {minimumFractionDigits: 2});
            document.getElementById('sowCostInput').value = limits.sow.toFixed(2);
            loadOffspring(sowId);
        } else {
            limits.sow = 0;
            document.getElementById('sowCostInput').value = "0.00";
            document.getElementById('sowMax').innerText = "0.00";
            document.getElementById('pigletBox').innerHTML = '<div style="width:100%; text-align:center; color:#64748b; padding-top:30px;">Select Sow first</div>';
            selectedPiglets.clear();
        }

        // Get Boar Data
        if(boarId) {
            const data = await fetchJSON(`../process/getCostData.php?action=get_sow_net_worth&animal_id=${boarId}`);
            limits.boar = parseFloat(data.total || 0); // SET LIMIT
            document.getElementById('boarMax').innerText = limits.boar.toLocaleString('en-US', {minimumFractionDigits: 2});
            document.getElementById('boarCostInput').value = limits.boar.toFixed(2);
        } else {
            limits.boar = 0;
            document.getElementById('boarCostInput').value = "0.00";
            document.getElementById('boarMax').innerText = "0.00";
        }
        recalc();
    }

    function validateInput(type) {
        const el = document.getElementById(type + 'CostInput');
        let val = parseFloat(el.value) || 0;
        const max = limits[type];

        if (val > max) {
            el.classList.add('error');
        } else {
            el.classList.remove('error');
        }
        recalc();
    }

    function recalc() {
        const s = parseFloat(document.getElementById('sowCostInput').value)||0;
        const b = parseFloat(document.getElementById('boarCostInput').value)||0;
        const total = s + b;
        document.getElementById('totalDisplay').innerText = "₱ " + total.toLocaleString('en-US', {minimumFractionDigits: 2});
        
        const count = selectedPiglets.size;
        document.getElementById('countPiglets').innerText = count;
        const perHead = count > 0 ? (total/count) : 0;
        document.getElementById('costPerHead').innerText = "₱ " + perHead.toLocaleString('en-US', {minimumFractionDigits: 2});
    }

    function loadOffspring(id) {
        const box = document.getElementById('pigletBox');
        box.innerHTML = 'Loading...';
        selectedPiglets.clear();
        fetchJSON(`../process/getCostData.php?action=get_piglets_by_mother&mother_id=${id}`).then(d => {
            box.innerHTML = '';
            if(d.length) {
                d.forEach(p => {
                    selectedPiglets.set(p.ANIMAL_ID, p.TAG_NO);
                    box.innerHTML += `<div class="tag-pill" id="p_${p.ANIMAL_ID}">${p.TAG_NO}<span class="tag-remove" onclick="remP('${p.ANIMAL_ID}')">✕</span></div>`;
                });
            } else { box.innerHTML = '<div style="width:100%; text-align:center; color:#94a3b8; padding:20px;">No eligible piglets found.</div>'; }
            recalc();
        });
    }

    function remP(id) {
        selectedPiglets.delete(String(id));
        document.getElementById('p_'+id).remove();
        recalc();
    }

    function submitTransfer() {
        const sCost = parseFloat(document.getElementById('sowCostInput').value)||0;
        const bCost = parseFloat(document.getElementById('boarCostInput').value)||0;
        
        if(sCost > limits.sow) return alert(`Sow cost cannot exceed ₱${limits.sow.toFixed(2)}`);
        if(bCost > limits.boar) return alert(`Boar cost cannot exceed ₱${limits.boar.toFixed(2)}`);
        if((sCost+bCost) <= 0) return alert("Total cost is zero.");
        if(selectedPiglets.size === 0) return alert("No piglets selected.");

        if(!confirm("Proceed with STRICT transfer? This cannot be undone.")) return;

        const fd = new FormData();
        fd.append('sow_id', document.getElementById('sowSelect').value);
        fd.append('boar_id', document.getElementById('boarSelect').value);
        fd.append('sow_cost', sCost);
        fd.append('boar_cost', bCost);
        fd.append('total_amount', sCost + bCost);
        fd.append('piglet_ids', JSON.stringify(Array.from(selectedPiglets.keys())));

        fetch('../process/saveCostTransfer.php', { method:'POST', body:fd })
        .then(r=>r.json())
        .then(res => {
            if(res.success) { alert("Success!"); location.reload(); }
            else { alert("Backend Error: " + res.message); }
        });
    }
</script>
</body>
</html>