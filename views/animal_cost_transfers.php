<?php
// views/animal_cost_transfers.php
$page = "farm";
include '../config/Connection.php';
include '../security/checkAccess.php';
checkAccess('cost_transfer');
include '../common/navbar.php';
include '../common/chat_support.php';
include '../functions/getUsersLocation.php';

if ($USER_LOCATION_ != 1000) {
    $stmt = $conn->prepare("SELECT * FROM locations WHERE LOCATION_ID = ? ORDER BY LOCATION_NAME");
    $stmt->execute([$USER_LOCATION_]);
    $locations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $locations = $conn->query("SELECT * FROM locations ORDER BY LOCATION_NAME")->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Strict Cost Transfer</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #0f172a; color: #e2e8f0; margin: 0; }
        .container { max-width: 1200px; margin: 0 auto; padding: 2rem; }
        .back-link { display: inline-flex; align-items: center; gap: 8px; text-decoration: none; color: #94a3b8; font-weight: 600; font-size: 0.95rem; margin-bottom: 20px; transition: color 0.2s; }
        .back-link:hover { color: white; }
        .header { text-align: center; margin-bottom: 2rem; }
        .transfer-grid { display: grid; grid-template-columns: 1fr 1.5fr; gap: 2rem; align-items: start; }
        .card { background: rgba(30, 41, 59, 0.6); border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; padding: 1.5rem; }
        .form-select { width: 100%; padding: 12px; background: #1e293b; border: 1px solid #475569; color: white; border-radius: 6px; font-size: 1rem; margin-bottom: 10px; }
        .form-select:disabled { opacity: 0.5; cursor: not-allowed; }
        .filters { display: flex; gap: 8px; margin-bottom: 10px; }
        .parent-cost-info { background: rgba(15, 23, 42, 0.5); border: 1px dashed #475569; border-radius: 6px; padding: 12px; margin-bottom: 20px; font-size: 0.85rem; color: #94a3b8; display: none; flex-direction: column; gap: 8px; }
        .cost-row { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 4px; }
        .cost-row strong { color: #e2e8f0; font-family: monospace; }
        .tag-selection-area { min-height: 100px; border: 2px dashed #475569; padding: 10px; display: flex; flex-wrap: wrap; gap: 10px; border-radius: 8px; }
        .tag-pill { background: #3b82f6; padding: 6px 12px; border-radius: 20px; font-size: 0.9rem; display: flex; align-items: center; gap: 8px; }
        .tag-remove { cursor: pointer; background: rgba(0,0,0,0.2); border-radius: 50%; width: 20px; height: 20px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .cost-display { text-align: center; padding: 15px; border: 1px solid #facc15; border-radius: 8px; background: rgba(250, 204, 21, 0.05); }
        .cost-total { font-size: 2rem; font-weight: 800; color: #facc15; }
        .breakdown-box { margin-top: 15px; background: #1e293b; padding: 15px; border-radius: 8px; }
        .breakdown-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; flex-wrap: wrap; gap: 8px; }
        .cost-input { width: 120px; padding: 10px; text-align: right; background: #0f172a; border: 1px solid #475569; color: white; border-radius: 6px; }
        .cost-input.error { border-color: #ef4444; color: #ef4444; }
        .btn-transfer { width: 100%; padding: 15px; background: #facc15; color: black; font-weight: bold; border: none; border-radius: 6px; cursor: pointer; font-size: 1.1rem; transition: filter 0.2s; }
        .btn-transfer:hover { filter: brightness(1.1); }
        .btn-transfer:disabled { background: #475569; color: #94a3b8; cursor: not-allowed; filter: none; }
        .section-label { font-size: 0.95rem; font-weight: bold; margin-bottom: 6px; display: block; }
        .error-msg { color: #f87171; font-size: 0.8rem; margin-top: -6px; margin-bottom: 8px; display: none; }

        @media (max-width: 900px) {
            .container { padding: 1rem; }
            .transfer-grid { grid-template-columns: 1fr; }
            .filters { flex-direction: column; }
            .cost-input { width: 100%; }
            .breakdown-row { flex-direction: column; align-items: flex-start; }
        }
    </style>
</head>
<body>

<div class="container">
    <a href="farm_dashboard.php" class="back-link">
        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
        Back to Farm Dashboard
    </a>

    <div class="header">
        <h1 style="color:#facc15; margin:0;">Strict Cost Transfer</h1>
    </div>

    <div class="transfer-grid">

        <!-- LEFT: Source Parents -->
        <div class="card">
            <h3 style="color:#facc15; border-bottom:1px solid #334155; padding-bottom:10px; margin-top:0;">1. Source Parents</h3>

            <!-- SOW -->
            <span class="section-label" style="color:#f472b6;">Dam (Sow)</span>
            <div class="filters">
                <select id="sowLoc" class="form-select" onchange="loadBuildings('sow')" <?php echo ($USER_LOCATION_ != 1000) ? 'style="pointer-events:none; opacity:0.7;"' : ''; ?>>
                    <?php if ($USER_LOCATION_ == 1000): ?>
                        <option value="">-- Location --</option>
                    <?php endif; ?>
                    <?php foreach($locations as $l): ?>
                        <option value="<?= $l['LOCATION_ID'] ?>" <?php echo ($USER_LOCATION_ != 1000 && $l['LOCATION_ID'] == $USER_LOCATION_) ? 'selected' : ''; ?>>
                            <?= htmlspecialchars($l['LOCATION_NAME']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select id="sowBld" class="form-select" disabled onchange="loadPens('sow')">
                    <option value="">Bldg...</option>
                </select>
                <select id="sowPen" class="form-select" disabled onchange="loadAnimals('sow')">
                    <option value="">Pen...</option>
                </select>
            </div>
            <select id="sowSelect" class="form-select" disabled onchange="handleParentSelection()">
                <option value="">-- Select Sow --</option>
            </select>
            <div id="sowError" class="error-msg">Failed to load sow data. Please try again.</div>

            <div id="sowCostInfo" class="parent-cost-info">
                <div class="cost-row"><span>Acquisition Cost:</span> <strong id="sowAcqCost">₱ 0.00</strong></div>
                <div class="cost-row"><span>Operational Cost:</span> <strong id="sowOpCost">₱ 0.00</strong></div>
                <div class="cost-row"><span style="color:#f87171;">Already Transferred:</span> <strong id="sowTransferredCost" style="color:#f87171;">- ₱ 0.00</strong></div>
            </div>

            <!-- BOAR -->
            <span class="section-label" style="color:#60a5fa;">Sire (Boar)</span>
            <div class="filters">
                <select id="boarLoc" class="form-select" onchange="loadBuildings('boar')" <?php echo ($USER_LOCATION_ != 1000) ? 'style="pointer-events:none; opacity:0.7;"' : ''; ?>>
                    <?php if ($USER_LOCATION_ == 1000): ?>
                        <option value="">-- Location --</option>
                    <?php endif; ?>
                    <?php foreach($locations as $l): ?>
                        <option value="<?= $l['LOCATION_ID'] ?>" <?php echo ($USER_LOCATION_ != 1000 && $l['LOCATION_ID'] == $USER_LOCATION_) ? 'selected' : ''; ?>>
                            <?= htmlspecialchars($l['LOCATION_NAME']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select id="boarBld" class="form-select" disabled onchange="loadPens('boar')">
                    <option value="">Bldg...</option>
                </select>
                <select id="boarPen" class="form-select" disabled onchange="loadAnimals('boar')">
                    <option value="">Pen...</option>
                </select>
            </div>
            <select id="boarSelect" class="form-select" disabled onchange="handleParentSelection()">
                <option value="">-- Select Boar --</option>
            </select>
            <div id="boarError" class="error-msg">Failed to load boar data. Please try again.</div>

            <div id="boarCostInfo" class="parent-cost-info">
                <div class="cost-row"><span>Acquisition Cost:</span> <strong id="boarAcqCost">₱ 0.00</strong></div>
                <div class="cost-row"><span>Operational Cost:</span> <strong id="boarOpCost">₱ 0.00</strong></div>
                <div class="cost-row"><span style="color:#f87171;">Already Transferred:</span> <strong id="boarTransferredCost" style="color:#f87171;">- ₱ 0.00</strong></div>
            </div>

            <!-- Cost Summary -->
            <div id="costDetails" style="margin-top:25px; opacity:0.5; transition: opacity 0.3s ease;">
                <div class="cost-display">
                    <div style="font-size:0.85rem; color:#fef08a; text-transform:uppercase;">Net Transferable Total</div>
                    <div class="cost-total" id="totalDisplay">₱ 0.00</div>
                </div>
                <div class="breakdown-box">
                    <div class="breakdown-row">
                        <span>Sow Cost <small>(Max: <span id="sowMax">0</span>)</small></span>
                        <input type="number" id="sowCostInput" class="cost-input" min="0" step="0.01" value="0" oninput="validateInput('sow')">
                    </div>
                    <div class="breakdown-row">
                        <span>Boar Cost <small>(Max: <span id="boarMax">0</span>)</small></span>
                        <input type="number" id="boarCostInput" class="cost-input" min="0" step="0.01" value="0" oninput="validateInput('boar')">
                    </div>
                </div>
            </div>
        </div>

        <!-- RIGHT: Offspring -->
        <div class="card">
            <h3 style="color:#3b82f6; border-bottom:1px solid #334155; padding-bottom:10px; margin-top:0;">2. Offspring</h3>
            <div id="pigletBox" class="tag-selection-area">
                <div style="width:100%; text-align:center; color:#64748b; padding-top:30px;">Select a Sow first to load offspring.</div>
            </div>
            <div style="margin-top:20px; font-weight:bold; background:#1e293b; padding:15px; border-radius:8px; display:flex; justify-content:space-between; flex-wrap:wrap; gap:10px;">
                <span>Count: <span id="countPiglets">0</span></span>
                <span>Cost/Head: <span id="costPerHead" style="color:#facc15;">₱ 0.00</span></span>
            </div>
            <button id="btnTransfer" class="btn-transfer" style="margin-top:15px;" onclick="submitTransfer()" disabled>
                Confirm Strict Transfer
            </button>
        </div>

    </div>
</div>

<script>
    let selectedPiglets = new Map();
    let limits = { sow: 0, boar: 0 };
    const USER_LOC = <?php echo json_encode($USER_LOCATION_); ?>;
    const API = '../process/getCostData.php';

    document.addEventListener('DOMContentLoaded', () => {
        if (USER_LOC != 1000) {
            loadBuildings('sow');
            loadBuildings('boar');
        }
    });

    // Safe fetch — returns null on failure instead of silently returning []
    async function fetchJSON(url) {
        try {
            const res = await fetch(url);
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            const raw = await res.text();
            return JSON.parse(raw);
        } catch (e) {
            console.error('fetchJSON error:', e, 'URL:', url);
            return null;
        }
    }

    function fmt(n) {
        return '₱ ' + parseFloat(n || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    // --- DROPDOWN LOADERS ---

    async function loadBuildings(t) {
        const locId = document.getElementById(t + 'Loc').value;
        const bldEl = document.getElementById(t + 'Bld');
        const penEl = document.getElementById(t + 'Pen');
        const selEl = document.getElementById(t + 'Select');

        bldEl.innerHTML = '<option value="">Loading...</option>';
        bldEl.disabled = true;
        penEl.innerHTML = '<option value="">Pen...</option>';
        penEl.disabled = true;
        selEl.innerHTML = `<option value="">-- Select ${t === 'sow' ? 'Sow' : 'Boar'} --</option>`;
        selEl.disabled = true;

        if (!locId) {
            bldEl.innerHTML = '<option value="">Bldg...</option>';
            return;
        }

        const data = await fetchJSON(`${API}?action=get_buildings&loc_id=${locId}`);
        bldEl.innerHTML = '<option value="">Bldg...</option>';
        if (data && data.length) {
            data.forEach(i => bldEl.innerHTML += `<option value="${i.BUILDING_ID}">${i.BUILDING_NAME}</option>`);
            bldEl.disabled = false;
        }
    }

    async function loadPens(t) {
        const bldId = document.getElementById(t + 'Bld').value;
        const penEl = document.getElementById(t + 'Pen');
        const selEl = document.getElementById(t + 'Select');

        penEl.innerHTML = '<option value="">Loading...</option>';
        penEl.disabled = true;
        selEl.innerHTML = `<option value="">-- Select ${t === 'sow' ? 'Sow' : 'Boar'} --</option>`;
        selEl.disabled = true;

        if (!bldId) {
            penEl.innerHTML = '<option value="">Pen...</option>';
            return;
        }

        const data = await fetchJSON(`${API}?action=get_pens&bld_id=${bldId}`);
        penEl.innerHTML = '<option value="">Pen...</option>';
        if (data && data.length) {
            data.forEach(i => penEl.innerHTML += `<option value="${i.PEN_ID}">${i.PEN_NAME}</option>`);
            penEl.disabled = false;
        }
    }

    async function loadAnimals(t) {
        const penId = document.getElementById(t + 'Pen').value;
        const selEl = document.getElementById(t + 'Select');

        selEl.innerHTML = '<option value="">Loading...</option>';
        selEl.disabled = true;

        if (!penId) {
            selEl.innerHTML = `<option value="">-- Select ${t === 'sow' ? 'Sow' : 'Boar'} --</option>`;
            return;
        }

        const action = t === 'sow' ? 'get_sows_in_pen' : 'get_boars_in_pen';
        const data = await fetchJSON(`${API}?action=${action}&pen_id=${penId}`);
        selEl.innerHTML = `<option value="">-- Select ${t === 'sow' ? 'Sow' : 'Boar'} --</option>`;
        if (data && data.length) {
            data.forEach(i => selEl.innerHTML += `<option value="${i.ANIMAL_ID}">${i.TAG_NO}</option>`);
        } else if (!data) {
            selEl.innerHTML = `<option value="">-- Error loading --</option>`;
        }
        selEl.disabled = false;
    }

    // --- PARENT SELECTION ---

    async function handleParentSelection() {
        const sowId  = document.getElementById('sowSelect').value;
        const boarId = document.getElementById('boarSelect').value;

        document.getElementById('costDetails').style.opacity = '1';
        document.getElementById('sowError').style.display  = 'none';
        document.getElementById('boarError').style.display = 'none';

        if (sowId) {
            const data = await fetchJSON(`${API}?action=get_sow_net_worth&animal_id=${sowId}`);

            if (!data || !data.success) {
                document.getElementById('sowError').style.display = 'block';
            } else {
                console.group('SOW COST BREAKDOWN');
                console.log('Feeds:    ₱' + data.feed);
                console.log('Meds:     ₱' + data.meds);
                console.log('Vaccines: ₱' + data.vac);
                console.log('Vitamins: ₱' + data.vit);
                console.log('Checkups: ₱' + data.checkup);
                console.log('──────────────────');
                console.log('Total Ops: ₱' + data.operation_cost);
                console.groupEnd();

                limits.sow = data.total;
                document.getElementById('sowMax').innerText             = parseFloat(data.total).toLocaleString();
                document.getElementById('sowCostInput').value          = parseFloat(data.total).toFixed(2);
                document.getElementById('sowAcqCost').innerText        = fmt(data.acquisition_cost);
                document.getElementById('sowOpCost').innerText         = fmt(data.operation_cost);
                document.getElementById('sowTransferredCost').innerText = '- ' + fmt(data.transferred_cost);
                document.getElementById('sowCostInfo').style.display   = 'flex';
            }

            loadOffspring(sowId);
        } else {
            // Reset sow panel
            limits.sow = 0;
            document.getElementById('sowCostInfo').style.display = 'none';
            document.getElementById('sowCostInput').value = '0';
            document.getElementById('sowMax').innerText = '0';
            document.getElementById('pigletBox').innerHTML =
                '<div style="width:100%; text-align:center; color:#64748b; padding-top:30px;">Select a Sow first to load offspring.</div>';
            selectedPiglets.clear();
        }

        if (boarId) {
            const data = await fetchJSON(`${API}?action=get_sow_net_worth&animal_id=${boarId}`);

            if (!data || !data.success) {
                document.getElementById('boarError').style.display = 'block';
            } else {
                console.group('BOAR COST BREAKDOWN');
                console.log('Feeds:    ₱' + data.feed);
                console.log('Meds:     ₱' + data.meds);
                console.log('Vaccines: ₱' + data.vac);
                console.log('Vitamins: ₱' + data.vit);
                console.log('Checkups: ₱' + data.checkup);
                console.log('──────────────────');
                console.log('Total Ops: ₱' + data.operation_cost);
                console.groupEnd();

                limits.boar = data.total;
                document.getElementById('boarMax').innerText              = parseFloat(data.total).toLocaleString();
                document.getElementById('boarCostInput').value           = parseFloat(data.total).toFixed(2);
                document.getElementById('boarAcqCost').innerText         = fmt(data.acquisition_cost);
                document.getElementById('boarOpCost').innerText          = fmt(data.operation_cost);
                document.getElementById('boarTransferredCost').innerText = '- ' + fmt(data.transferred_cost);
                document.getElementById('boarCostInfo').style.display    = 'flex';
            }
        } else {
            // Reset boar panel
            limits.boar = 0;
            document.getElementById('boarCostInfo').style.display = 'none';
            document.getElementById('boarCostInput').value = '0';
            document.getElementById('boarMax').innerText = '0';
        }

        recalc();
    }

    // --- OFFSPRING ---

    async function loadOffspring(sowId) {
        const box = document.getElementById('pigletBox');
        box.innerHTML = '<div style="width:100%; text-align:center; color:#64748b; padding-top:30px;">Loading offspring...</div>';
        selectedPiglets.clear();

        const data = await fetchJSON(`${API}?action=get_piglets_by_mother&mother_id=${sowId}`);

        if (!data) {
            box.innerHTML = '<div style="width:100%; text-align:center; color:#f87171; padding-top:30px;">Failed to load offspring.</div>';
            recalc();
            return;
        }

        box.innerHTML = '';
        if (data.length === 0) {
            box.innerHTML = '<div style="width:100%; text-align:center; color:#64748b; padding-top:30px;">No eligible offspring found for this sow.</div>';
        } else {
            data.forEach(p => {
                selectedPiglets.set(String(p.ANIMAL_ID), p.TAG_NO);
                box.innerHTML += `
                    <div class="tag-pill" id="p_${p.ANIMAL_ID}">
                        ${p.TAG_NO}
                        <span class="tag-remove" onclick="remP('${p.ANIMAL_ID}')">✕</span>
                    </div>`;
            });
        }
        recalc();
    }

    function remP(id) {
        selectedPiglets.delete(String(id));
        document.getElementById('p_' + id)?.remove();
        recalc();
    }

    // --- VALIDATION & RECALC ---

    function validateInput(t) {
        const input = document.getElementById(t + 'CostInput');
        const val   = parseFloat(input.value) || 0;
        if (val > limits[t]) {
            input.classList.add('error');
        } else {
            input.classList.remove('error');
        }
        recalc();
    }

    function recalc() {
        const sowVal  = parseFloat(document.getElementById('sowCostInput').value)  || 0;
        const boarVal = parseFloat(document.getElementById('boarCostInput').value) || 0;
        const total   = sowVal + boarVal;
        const count   = selectedPiglets.size;

        document.getElementById('totalDisplay').innerText  = fmt(total);
        document.getElementById('countPiglets').innerText  = count;
        document.getElementById('costPerHead').innerText   = count > 0 ? fmt(total / count) : '₱ 0.00';

        // Enable transfer only if there's at least one piglet and no input errors
        const hasErrors = document.querySelector('.cost-input.error');
        const hasPiglets = count > 0;
        const hasCost = total > 0;
        document.getElementById('btnTransfer').disabled = !(hasPiglets && hasCost && !hasErrors);
    }

    // --- SUBMIT ---

    async function submitTransfer() {
        const sowId  = document.getElementById('sowSelect').value;
        const boarId = document.getElementById('boarSelect').value;
        const sowCost  = parseFloat(document.getElementById('sowCostInput').value)  || 0;
        const boarCost = parseFloat(document.getElementById('boarCostInput').value) || 0;

        if (!sowId && !boarId) {
            alert('❌ Please select at least one parent (Sow or Boar).');
            return;
        }
        if (selectedPiglets.size === 0) {
            alert('❌ No offspring selected for transfer.');
            return;
        }
        if (sowCost > limits.sow) {
            alert(`❌ Sow cost (₱${sowCost}) exceeds available net worth (₱${limits.sow}).`);
            return;
        }
        if (boarCost > limits.boar) {
            alert(`❌ Boar cost (₱${boarCost}) exceeds available net worth (₱${limits.boar}).`);
            return;
        }
        if (!confirm('Proceed with cost transfer?')) return;

        const btn = document.getElementById('btnTransfer');
        btn.disabled = true;
        btn.innerText = 'Processing...';

        const fd = new FormData();
        fd.append('sow_id',    sowId);
        fd.append('boar_id',   boarId);
        fd.append('sow_cost',  sowCost);
        fd.append('boar_cost', boarCost);
        fd.append('piglet_ids', JSON.stringify(Array.from(selectedPiglets.keys())));

        try {
            const res = await fetch('../process/saveCostTransfer.php', { method: 'POST', body: fd });
            const raw = await res.text();
            let result;
            try {
                result = JSON.parse(raw);
            } catch (e) {
                console.error('Non-JSON response from saveCostTransfer.php:', raw);
                alert('❌ Server error. Check the browser console (F12) for details.');
                return;
            }

            if (result.success) {
                alert('✅ Transfer successful!');
                location.reload();
            } else {
                alert('❌ ' + (result.message || 'Transfer failed.'));
            }
        } catch (err) {
            console.error(err);
            alert('❌ System error occurred.');
        } finally {
            btn.disabled = false;
            btn.innerText = 'Confirm Strict Transfer';
            recalc();
        }
    }
</script>
</body>
</html>