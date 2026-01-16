<?php
// views/transfer_group.php
$page = "farm";
include '../common/navbar.php';
include '../config/Connection.php';
include '../security/checkRole.php';
checkRole(2);

// Pre-fetch Locations for dropdowns
$locations = $conn->query("SELECT * FROM locations ORDER BY LOCATION_NAME")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> <title>Transfer Animal Group</title>
    <style>
        body { background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: #e2e8f0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; min-height: 100vh; margin: 0; }
        
        .container { max-width: 1400px; margin: 0 auto; padding: 2rem; }
        
        /* Header */
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; border-bottom: 1px solid #334155; padding-bottom: 1rem; flex-wrap: wrap; gap: 10px; }
        .page-title { font-size: 1.8rem; font-weight: 800; color: #60a5fa; margin: 0; }
        .back-link { color: #94a3b8; text-decoration: none; font-size: 1rem; }

        /* Transfer Grid */
        .transfer-grid { 
            display: grid; 
            grid-template-columns: 1fr 80px 1fr; 
            gap: 20px; 
            align-items: stretch; /* Stretch height to match */
        }
        
        /* Panels */
        .panel { 
            background: rgba(30, 41, 59, 0.6); 
            border: 1px solid #475569; 
            border-radius: 12px; 
            padding: 1.5rem; 
            display: flex; 
            flex-direction: column; 
            height: 100%; /* Fill grid cell */
            box-sizing: border-box;
        }
        .panel-header { font-size: 1.2rem; font-weight: 700; margin-bottom: 1rem; text-transform: uppercase; letter-spacing: 1px; border-bottom: 1px solid #475569; padding-bottom: 10px; }
        .panel-src { border-color: #f472b6; }
        .panel-dest { border-color: #34d399; }
        .src-title { color: #f472b6; }
        .dest-title { color: #34d399; }

        /* Form Elements */
        .form-group { margin-bottom: 1rem; }
        .form-label { display: block; font-size: 0.85rem; color: #94a3b8; margin-bottom: 5px; }
        .form-select { width: 100%; padding: 12px; background: #0f172a; border: 1px solid #475569; color: white; border-radius: 6px; font-size: 1rem; box-sizing: border-box; }
        
        /* Animal List Box */
        .animal-list-box { 
            flex-grow: 1; 
            background: #0f172a; 
            border: 1px solid #475569; 
            border-radius: 8px; 
            padding: 10px; 
            min-height: 300px; 
            max-height: 500px; 
            overflow-y: auto; 
        }
        
        .animal-item { 
            display: flex; align-items: center; gap: 10px; 
            padding: 12px 8px; /* Larger tap area */
            border-bottom: 1px solid #334155; 
            transition: background 0.2s; 
        }
        .animal-item:hover { background: rgba(255,255,255,0.05); }
        .animal-item label { cursor: pointer; flex-grow: 1; display: flex; justify-content: space-between; align-items: center; }
        .tag { font-weight: bold; color: #e2e8f0; font-size: 1.1rem; }
        .type { font-size: 0.85rem; color: #94a3b8; }

        /* Middle Arrow */
        .middle-action { 
            display: flex; 
            flex-direction: column; 
            align-items: center; 
            justify-content: center; 
            height: 100%; 
        }
        .arrow-icon { font-size: 2.5rem; color: #64748b; }

        /* Footer Action */
        .action-footer { 
            margin-top: 20px; 
            text-align: right; 
            padding: 20px; 
            background: rgba(15, 23, 42, 0.5); 
            border-radius: 12px; 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            flex-wrap: wrap;
            gap: 15px;
        }
        .count-display { color: #94a3b8; font-weight: 600; font-size: 1.1rem; }
        .btn-transfer { 
            background: linear-gradient(135deg, #3b82f6, #2563eb); 
            color: white; border: none; padding: 15px 30px; 
            border-radius: 8px; font-weight: bold; cursor: pointer; 
            font-size: 1.1rem; transition: transform 0.1s; 
            width: auto;
        }
        .btn-transfer:hover { transform: scale(1.02); filter: brightness(1.1); }
        .btn-transfer:disabled { background: #475569; cursor: not-allowed; transform: none; }

        /* Checkbox styling */
        input[type="checkbox"] { width: 20px; height: 20px; accent-color: #3b82f6; cursor: pointer; }

        /* --- MOBILE RESPONSIVENESS --- */
        @media (max-width: 900px) {
            .container { padding: 1rem; }
            
            .page-title { font-size: 1.5rem; }

            .transfer-grid { 
                grid-template-columns: 1fr; /* Stack vertically */
                gap: 10px;
            }
            
            .middle-action { 
                flex-direction: row; 
                padding: 10px; 
                transform: rotate(90deg); /* Point arrow down */
                height: auto;
            }
            
            .panel { min-height: auto; }
            
            .animal-list-box { 
                min-height: 250px; /* Slightly smaller on mobile */
                max-height: 400px;
            }

            .action-footer { 
                flex-direction: column; 
                text-align: center;
                gap: 15px;
            }
            
            .btn-transfer { width: 100%; }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="page-header">
        <h1 class="page-title">⇄ Group Transfer</h1>
        <a href="farm_dashboard.php" class="back-link">&larr; Dashboard</a>
    </div>

    <form id="transferForm" onsubmit="submitTransfer(event)">
        <div class="transfer-grid">
            
            <div class="panel panel-src">
                <div class="panel-header src-title">1. Source (From)</div>
                
                <div class="form-group">
                    <label class="form-label">Location</label>
                    <select id="src_loc" class="form-select" onchange="loadBuildings('src')">
                        <option value="">-- Select --</option>
                        <?php foreach($locations as $l): ?>
                            <option value="<?= $l['LOCATION_ID'] ?>"><?= $l['LOCATION_NAME'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Building</label>
                    <select id="src_bld" class="form-select" disabled onchange="loadPens('src')">
                        <option value="">-- Select --</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Pen</label>
                    <select id="src_pen" class="form-select" disabled onchange="loadAnimals()">
                        <option value="">-- Select --</option>
                    </select>
                </div>

                <div style="display:flex; justify-content:space-between; margin-bottom:10px; align-items:center;">
                    <label class="form-label" style="margin:0;">Select Animals</label>
                    <button type="button" onclick="selectAll(true)" style="background:none; border:none; color:#60a5fa; cursor:pointer; font-size:0.9rem; padding:5px;">Select All</button>
                </div>
                <div id="animalList" class="animal-list-box">
                    <div style="text-align:center; padding:40px 20px; color:#64748b;">
                        Select a Source Pen first.
                    </div>
                </div>
            </div>

            <div class="middle-action">
                <div class="arrow-icon">➔</div>
            </div>

            <div class="panel panel-dest">
                <div class="panel-header dest-title">2. Destination (To)</div>
                
                <div class="form-group">
                    <label class="form-label">Location</label>
                    <select id="dest_loc" name="dest_location_id" class="form-select" required onchange="loadBuildings('dest')">
                        <option value="">-- Select --</option>
                        <?php foreach($locations as $l): ?>
                            <option value="<?= $l['LOCATION_ID'] ?>"><?= $l['LOCATION_NAME'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Building</label>
                    <select id="dest_bld" name="dest_building_id" class="form-select" required disabled onchange="loadPens('dest')">
                        <option value="">-- Select --</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Pen</label>
                    <select id="dest_pen" name="dest_pen_id" class="form-select" required disabled>
                        <option value="">-- Select --</option>
                    </select>
                </div>

                <div style="margin-top:auto; padding: 15px; background: rgba(52, 211, 153, 0.1); border-radius: 8px; border: 1px solid rgba(52, 211, 153, 0.2);">
                    <strong style="color:#34d399">Note:</strong>
                    <p style="font-size:0.9rem; color:#cbd5e1; margin-top:5px; line-height: 1.4;">
                        Selected animals will be officially moved to this new location. Their history log will be updated.
                    </p>
                </div>
            </div>

        </div>

        <div class="action-footer">
            <div class="count-display">Selected: <span id="selectedCount" style="color:#fff;">0</span> animals</div>
            <button type="submit" class="btn-transfer" id="btnTransfer" disabled>Transfer Animals</button>
        </div>
    </form>
</div>

<script>
    // --- DROPDOWN LOADERS ---
    async function loadBuildings(prefix) {
        const locId = document.getElementById(prefix + '_loc').value;
        const bldSelect = document.getElementById(prefix + '_bld');
        const penSelect = document.getElementById(prefix + '_pen');
        
        bldSelect.innerHTML = '<option value="">Loading...</option>';
        penSelect.innerHTML = '<option value="">-- Select --</option>';
        penSelect.disabled = true;

        if(!locId) {
            bldSelect.innerHTML = '<option value="">-- Select --</option>';
            bldSelect.disabled = true;
            return;
        }

        const res = await fetch(`../process/getTransferData.php?action=get_buildings&loc_id=${locId}`);
        const data = await res.json();
        
        bldSelect.innerHTML = '<option value="">-- Select --</option>';
        data.forEach(b => {
            bldSelect.innerHTML += `<option value="${b.BUILDING_ID}">${b.BUILDING_NAME}</option>`;
        });
        bldSelect.disabled = false;
    }

    async function loadPens(prefix) {
        const bldId = document.getElementById(prefix + '_bld').value;
        const penSelect = document.getElementById(prefix + '_pen');
        
        penSelect.innerHTML = '<option value="">Loading...</option>';

        if(!bldId) {
            penSelect.innerHTML = '<option value="">-- Select --</option>';
            penSelect.disabled = true;
            return;
        }

        const res = await fetch(`../process/getTransferData.php?action=get_pens&bld_id=${bldId}`);
        const data = await res.json();
        
        penSelect.innerHTML = '<option value="">-- Select --</option>';
        data.forEach(p => {
            penSelect.innerHTML += `<option value="${p.PEN_ID}">${p.PEN_NAME}</option>`;
        });
        penSelect.disabled = false;
    }

    // --- ANIMAL LOADER ---
    async function loadAnimals() {
        const penId = document.getElementById('src_pen').value;
        const list = document.getElementById('animalList');
        
        if(!penId) {
            list.innerHTML = '<div style="text-align:center; padding:20px; color:#64748b;">Select a Source Pen first.</div>';
            updateCount();
            return;
        }

        list.innerHTML = '<div style="text-align:center; padding:20px;">Loading animals...</div>';

        const res = await fetch(`../process/getTransferData.php?action=get_animals&pen_id=${penId}`);
        const data = await res.json();

        if(data.length === 0) {
            list.innerHTML = '<div style="text-align:center; padding:20px; color:#f472b6;">No active animals in this pen.</div>';
        } else {
            list.innerHTML = '';
            data.forEach(a => {
                const typeLabel = a.ANIMAL_TYPE_NAME ? a.ANIMAL_TYPE_NAME : 'Unknown';
                const breedLabel = a.BREED_NAME ? a.BREED_NAME : '-';
                
                list.innerHTML += `
                    <div class="animal-item">
                        <input type="checkbox" name="animal_ids[]" value="${a.ANIMAL_ID}" id="chk_${a.ANIMAL_ID}" onchange="updateCount()">
                        <label for="chk_${a.ANIMAL_ID}">
                            <span class="tag">${a.TAG_NO}</span>
                            <span class="type">${typeLabel} / ${breedLabel}</span>
                        </label>
                    </div>
                `;
            });
        }
        updateCount();
    }

    // --- UTILITIES ---
    function updateCount() {
        const checkboxes = document.querySelectorAll('input[name="animal_ids[]"]:checked');
        const count = checkboxes.length;
        document.getElementById('selectedCount').innerText = count;
        document.getElementById('btnTransfer').disabled = (count === 0);
    }

    function selectAll(check) {
        const checkboxes = document.querySelectorAll('input[name="animal_ids[]"]');
        checkboxes.forEach(cb => cb.checked = check);
        updateCount();
    }

    async function submitTransfer(e) {
        e.preventDefault();
        
        const srcPen = document.getElementById('src_pen').value;
        const destPen = document.getElementById('dest_pen').value;

        if (srcPen == destPen) {
            alert("❌ Source and Destination Pens cannot be the same.");
            return;
        }

        if(!confirm("Are you sure you want to transfer the selected animals?")) return;

        const form = document.getElementById('transferForm');
        const formData = new FormData(form);

        // UI Feedback
        const btn = document.getElementById('btnTransfer');
        const originalText = btn.innerText;
        btn.innerText = "Transferring...";
        btn.disabled = true;

        try {
            const res = await fetch('../process/transferGroupProcess.php', {
                method: 'POST',
                body: formData
            });
            const result = await res.json();

            if(result.success) {
                alert("✅ Transfer Successful!");
                loadAnimals(); // Refresh source list
                // document.getElementById('dest_pen').value = ''; // Optional reset
            } else {
                alert("❌ Error: " + result.message);
            }
        } catch(err) {
            console.error(err);
            alert("System Error Occurred.");
        } finally {
            btn.innerText = originalText;
            btn.disabled = false;
            updateCount(); // Re-check validation
        }
    }
</script>

</body>
</html>