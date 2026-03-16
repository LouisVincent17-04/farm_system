<?php
// views/animal_transfer_pen.php
ob_start(); // Start output buffering immediately
$page = "farm";
include '../config/Connection.php';

include '../security/checkAccess.php';
checkAccess('animal_transfer');
include '../functions/getUsersLocation.php'; // Include this before AJAX too if it's used

// =========================================================
// 1. AJAX HANDLER (For Dropdowns & Animal Lists)
// =========================================================
// MUST BE BEFORE NAVBAR OR ANY HTML OUTPUT
if (isset($_GET['action'])) {
    ob_end_clean(); // Wipe any accidental spaces/output
    header('Content-Type: application/json');
    $action = $_GET['action'];
    $status = $_GET['status_filter'] ?? 'Active';

    // Build Status Clause
    $statusClause = " AND a.IS_ACTIVE = 1 ";
    if ($status === 'Inactive') $statusClause = " AND a.IS_ACTIVE = 0 ";
    if ($status === 'All') $statusClause = ""; 

    try {
        if ($action === 'get_buildings' && isset($_GET['loc_id'])) {
            $stmt = $conn->prepare("SELECT BUILDING_ID, BUILDING_NAME FROM buildings WHERE LOCATION_ID = ? ORDER BY BUILDING_NAME");
            $stmt->execute([$_GET['loc_id']]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC)); exit;
        }
        if ($action === 'get_pens' && isset($_GET['bldg_id'])) {
            $stmt = $conn->prepare("SELECT PEN_ID, PEN_NAME FROM pens WHERE BUILDING_ID = ? ORDER BY PEN_NAME");
            $stmt->execute([$_GET['bldg_id']]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC)); exit;
        }
        if ($action === 'get_animals' && isset($_GET['pen_id'])) {
            // Updated to fetch Type and Breed names for the display lists
            $sql = "SELECT a.ANIMAL_ID, a.TAG_NO, t.ANIMAL_TYPE_NAME, b.BREED_NAME 
                    FROM animal_records a
                    LEFT JOIN animal_type t ON a.ANIMAL_TYPE_ID = t.ANIMAL_TYPE_ID
                    LEFT JOIN breeds b ON a.BREED_ID = b.BREED_ID
                    WHERE a.PEN_ID = ? $statusClause 
                    ORDER BY a.TAG_NO";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$_GET['pen_id']]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC)); exit;
        }
    } catch (Exception $e) { echo json_encode([]); exit; }
}

// NOW we can safely include HTML UI elements
include '../common/navbar.php';
include '../common/chat_support.php';

// Pre-fetch Locations for dropdowns
// Auto-assign location filter if user is restricted
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> <title>Transfer Animal Group</title>
    <style>
        body { background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: #e2e8f0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; min-height: 100vh; margin: 0; }
        
        .container { max-width: 1400px; margin: 0 auto; padding: 2rem; }
        
        /* Header */
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; border-bottom: 1px solid #334155; padding-bottom: 1rem; flex-wrap: wrap; gap: 10px; }
        .page-title { font-size: 1.8rem; font-weight: 800; color: #60a5fa; margin: 0; }
        
        /* Back Link Style */
        .back-link {
            display: inline-flex; align-items: center; gap: 8px; 
            text-decoration: none; color: #94a3b8; font-weight: 600; 
            font-size: 1rem; transition: color 0.2s;
        }
        .back-link:hover { color: white; }

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
        .form-select:disabled { opacity: 0.5; cursor: not-allowed; }

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
        
        /* Read-only List Box for Destination */
        .readonly-list { background: rgba(15, 23, 42, 0.5); }
        
        .animal-item { 
            display: flex; align-items: center; gap: 10px; 
            padding: 12px 8px; /* Larger tap area */
            border-bottom: 1px solid #334155; 
            transition: background 0.2s; 
        }
        .animal-item:hover { background: rgba(255,255,255,0.05); }
        .readonly-list .animal-item:hover { background: transparent; cursor: default; }

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
        
        <a href="farm_dashboard.php" class="back-link">
            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            Back to Farm Dashboard
        </a>
    </div>

    <form id="transferForm" onsubmit="submitTransfer(event)">
        <div class="transfer-grid">
            
            <div class="panel panel-src">
                <div class="panel-header src-title">1. Source (From)</div>
                
                <div class="form-group">
                    <label class="form-label">Location</label>
                    <select id="src_loc" class="form-select" onchange="loadBuildings('src')" <?php echo ($USER_LOCATION_ != 1000) ? 'style="pointer-events: none; opacity: 0.7; background-color: #1e293b;"' : ''; ?>>
                        <option value="">-- Select --</option>
                        <?php foreach($locations as $l): ?>
                            <option value="<?= $l['LOCATION_ID'] ?>" <?php echo ($USER_LOCATION_ != 1000 && $l['LOCATION_ID'] == $USER_LOCATION_) ? 'selected' : ''; ?>>
                                <?= $l['LOCATION_NAME'] ?>
                            </option>
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
                    <select id="src_pen" class="form-select" disabled onchange="loadAnimals('src')">
                        <option value="">-- Select --</option>
                    </select>
                </div>

                <div style="display:flex; justify-content:space-between; margin-bottom:10px; align-items:center;">
                    <label class="form-label" style="margin:0;">Select Animals to Move</label>
                    <button type="button" onclick="selectAll(true)" style="background:none; border:none; color:#60a5fa; cursor:pointer; font-size:0.9rem; padding:5px;">Select All</button>
                </div>
                <div id="src_animalList" class="animal-list-box">
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
                    <select id="dest_loc" name="dest_location_id" class="form-select" required onchange="loadBuildings('dest')" <?php echo ($USER_LOCATION_ != 1000) ? 'style="pointer-events: none; opacity: 0.7; background-color: #1e293b;"' : ''; ?>>
                        <option value="">-- Select --</option>
                        <?php foreach($locations as $l): ?>
                            <option value="<?= $l['LOCATION_ID'] ?>" <?php echo ($USER_LOCATION_ != 1000 && $l['LOCATION_ID'] == $USER_LOCATION_) ? 'selected' : ''; ?>>
                                <?= $l['LOCATION_NAME'] ?>
                            </option>
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
                    <select id="dest_pen" name="dest_pen_id" class="form-select" required disabled onchange="loadAnimals('dest')">
                        <option value="">-- Select --</option>
                    </select>
                </div>

                <div style="display:flex; justify-content:space-between; margin-bottom:10px; align-items:center; margin-top: 10px;">
                    <label class="form-label" style="margin:0;">Current Residents</label>
                    <span id="destCount" style="color:#34d399; font-size:0.85rem; font-weight:bold;">0 Heads</span>
                </div>
                <div id="dest_animalList" class="animal-list-box readonly-list">
                    <div style="text-align:center; padding:40px 20px; color:#64748b;">
                        Select a Destination Pen first.
                    </div>
                </div>

                <div style="margin-top: 15px; padding: 15px; background: rgba(52, 211, 153, 0.1); border-radius: 8px; border: 1px solid rgba(52, 211, 153, 0.2);">
                    <strong style="color:#34d399">Note:</strong>
                    <p style="font-size:0.9rem; color:#cbd5e1; margin-top:5px; line-height: 1.4;">
                        Selected animals will be officially moved to this new location. Their history log will be updated.
                    </p>
                </div>
            </div>

        </div>

        <div class="action-footer">
            <div class="count-display">Selected to Transfer: <span id="selectedCount" style="color:#fff;">0</span> animals</div>
            <button type="submit" class="btn-transfer" id="btnTransfer" disabled>Transfer Animals</button>
        </div>
    </form>
</div>

<script>
    const API_URL = window.location.pathname.split("/").pop();

    // Auto-load buildings if user is restricted
    document.addEventListener('DOMContentLoaded', () => {
        const userLoc = <?php echo json_encode($USER_LOCATION_); ?>;
        if (userLoc != 1000) {
            loadBuildings('src');
            loadBuildings('dest');
        }
    });

    // --- DROPDOWN LOADERS ---
    async function loadBuildings(prefix) {
        const locId = document.getElementById(prefix + '_loc').value;
        const bldSelect = document.getElementById(prefix + '_bld');
        const penSelect = document.getElementById(prefix + '_pen');
        
        bldSelect.innerHTML = '<option value="">Loading...</option>';
        penSelect.innerHTML = '<option value="">-- Select --</option>';
        penSelect.disabled = true;
        
        // Reset animal list
        const list = document.getElementById(prefix + '_animalList');
        list.innerHTML = `<div style="text-align:center; padding:40px 20px; color:#64748b;">Select a ${prefix === 'src' ? 'Source' : 'Destination'} Pen first.</div>`;

        if(!locId) {
            bldSelect.innerHTML = '<option value="">-- Select --</option>';
            bldSelect.disabled = true;
            return;
        }

        const res = await fetch(`${API_URL}?action=get_buildings&loc_id=${locId}`);
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

        // Reset animal list
        const list = document.getElementById(prefix + '_animalList');
        list.innerHTML = `<div style="text-align:center; padding:40px 20px; color:#64748b;">Select a ${prefix === 'src' ? 'Source' : 'Destination'} Pen first.</div>`;

        if(!bldId) {
            penSelect.innerHTML = '<option value="">-- Select --</option>';
            penSelect.disabled = true;
            return;
        }

        const res = await fetch(`${API_URL}?action=get_pens&bldg_id=${bldId}`);
        const data = await res.json();
        
        penSelect.innerHTML = '<option value="">-- Select --</option>';
        data.forEach(p => {
            penSelect.innerHTML += `<option value="${p.PEN_ID}">${p.PEN_NAME}</option>`;
        });
        penSelect.disabled = false;
    }

    // --- ANIMAL LOADER ---
    async function loadAnimals(prefix) {
        const penId = document.getElementById(prefix + '_pen').value;
        const list = document.getElementById(prefix + '_animalList');
        const destCount = document.getElementById('destCount');
        
        if(!penId) {
            list.innerHTML = `<div style="text-align:center; padding:40px 20px; color:#64748b;">Select a ${prefix === 'src' ? 'Source' : 'Destination'} Pen first.</div>`;
            if (prefix === 'src') updateCount();
            if (prefix === 'dest') destCount.innerText = '0 Heads';
            return;
        }

        list.innerHTML = '<div style="text-align:center; padding:20px;">Loading animals...</div>';

        const res = await fetch(`${API_URL}?action=get_animals&pen_id=${penId}`);
        const data = await res.json();

        if(data.length === 0) {
            const emptyMsg = prefix === 'src' ? 'No active animals to transfer from this pen.' : 'This destination pen is currently empty.';
            const msgColor = prefix === 'src' ? '#f472b6' : '#34d399';
            list.innerHTML = `<div style="text-align:center; padding:20px; color:${msgColor};">${emptyMsg}</div>`;
            if (prefix === 'dest') destCount.innerText = '0 Heads';
        } else {
            list.innerHTML = '';
            if (prefix === 'dest') destCount.innerText = data.length + ' Heads';

            data.forEach(a => {
                const typeLabel = a.ANIMAL_TYPE_NAME ? a.ANIMAL_TYPE_NAME : 'Unknown';
                const breedLabel = a.BREED_NAME ? a.BREED_NAME : '-';
                
                if (prefix === 'src') {
                    // Source List (With Checkboxes)
                    list.innerHTML += `
                        <div class="animal-item">
                            <input type="checkbox" name="animal_ids[]" value="${a.ANIMAL_ID}" id="chk_${a.ANIMAL_ID}" onchange="updateCount()">
                            <label for="chk_${a.ANIMAL_ID}">
                                <span class="tag">${a.TAG_NO}</span>
                                <span class="type">${typeLabel} / ${breedLabel}</span>
                            </label>
                        </div>
                    `;
                } else {
                    // Destination List (Read-only visual)
                    list.innerHTML += `
                        <div class="animal-item" style="cursor: default;">
                            <div style="flex-grow: 1; display: flex; justify-content: space-between; align-items: center;">
                                <span class="tag" style="color: #34d399;">${a.TAG_NO}</span>
                                <span class="type">${typeLabel} / ${breedLabel}</span>
                            </div>
                        </div>
                    `;
                }
            });
        }
        
        if (prefix === 'src') updateCount();
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

        if (!srcPen || !destPen) {
            alert("❌ Please select both a Source Pen and a Destination Pen.");
            return;
        }

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
                // Refresh BOTH lists to instantly show the animals moved
                loadAnimals('src'); 
                loadAnimals('dest'); 
            } else {
                alert("❌ Error: " + result.message);
            }
        } catch(err) {
            console.error(err);
            alert("System Error Occurred.");
        } finally {
            btn.innerText = originalText;
            updateCount(); // Re-check validation
        }
    }
</script>

</body>
</html>