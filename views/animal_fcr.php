<?php
// views/manage_animal_fcr.php
$page = "farm"; 
include '../common/navbar.php';
include '../config/Connection.php';
include '../security/checkRole.php';    
checkRole(2);

// Fetch Initial Locations for Dropdown
$locations = [];
try {
    $stmt = $conn->prepare("SELECT * FROM locations ORDER BY LOCATION_NAME");
    $stmt->execute();
    $locations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Animal FCR Management</title>
    <style>
        /* --- CORE DARK THEME --- */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; 
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); 
            color: #e2e8f0; 
            min-height: 100vh; 
        }
        .container { max-width: 1200px; margin: 0 auto; padding: 2rem; }

        .page-header { text-align: center; margin-bottom: 3rem; }
        .page-title {
            font-size: 2.5rem; font-weight: bold; margin-bottom: 0.5rem;
            background: linear-gradient(135deg, #f59e0b, #d97706); /* Orange for Feed */
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }

        /* FILTERS ROW */
        .filter-card {
            background: rgba(30, 41, 59, 0.6); 
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 12px; 
            padding: 1.5rem; 
            margin-bottom: 2rem;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        .filter-group { display: flex; flex-direction: column; }
        .filter-group label { display: block; color: #94a3b8; margin-bottom: 5px; font-size: 0.9rem; font-weight: 600; }
        .filter-select { 
            width: 100%; padding: 10px; background: #1e293b; border: 1px solid #475569; 
            color: white; border-radius: 6px; font-size: 1rem; 
        }
        .filter-select:disabled { opacity: 0.5; cursor: not-allowed; }

        /* CALCULATION FORM CARD */
        .calc-card {
            background: rgba(15, 23, 42, 0.6); border: 1px solid #f59e0b;
            border-radius: 16px; padding: 2rem; display: none; 
            animation: fadeIn 0.3s ease;
        }
        @keyframes fadeIn { from { opacity:0; transform:translateY(10px); } to { opacity:1; transform:translateY(0); } }

        .section-title { color: #f59e0b; font-size: 1.2rem; margin-bottom: 1.5rem; border-bottom: 1px solid rgba(245, 158, 11, 0.3); padding-bottom: 10px; }

        .form-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); 
            gap: 20px; 
            margin-bottom: 20px; 
        }
        .form-item label { display: block; color: #cbd5e1; margin-bottom: 8px; font-size: 0.9rem; }
        .form-item input { 
            width: 100%; padding: 12px; background: #0f172a; border: 1px solid #334155; 
            border-radius: 8px; color: white; font-size: 1.1rem; font-weight: 500;
        }
        
        .input-system { background: #1e293b; color: #94a3b8; border-color: transparent; cursor: default; }
        .input-user { border-color: #f59e0b; background: rgba(245, 158, 11, 0.05); }
        .input-user:focus { outline: none; border-color: #fbbf24; box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.2); }

        .info-badge { 
            display: inline-block; margin-top: 5px; font-size: 0.75rem; color: #64748b; 
            background: rgba(255,255,255,0.05); padding: 2px 6px; border-radius: 4px;
        }

        .action-row { text-align: right; margin-top: 2rem; border-top: 1px solid #334155; padding-top: 1.5rem; }
        .btn-save {
            background: linear-gradient(135deg, #f59e0b, #d97706); color: white;
            border: none; padding: 12px 30px; border-radius: 8px; font-weight: bold; cursor: pointer;
            font-size: 1rem; transition: transform 0.2s;
        }
        .btn-save:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3); }
        .loader { display: none; color: #f59e0b; font-weight: bold; margin-left: 10px; }

        @media (max-width: 768px) {
            .container { padding: 1rem; }
            .filter-card { grid-template-columns: 1fr; }
            .form-grid { grid-template-columns: 1fr; }
            .btn-save { width: 100%; }
        }
    </style>
</head>
<body>

<div class="container">
    <header class="page-header">
        <h1 class="page-title">FCR Calculator</h1>
        <p style="color:#94a3b8;">Feed Conversion Rate Analysis per Animal</p>
    </header>

    <div class="filter-card">
        <div class="filter-group">
            <label>1. Location</label>
            <select id="sel_location" class="filter-select" onchange="loadBuildings()">
                <option value="">-- Select Location --</option>
                <?php foreach($locations as $loc): ?>
                    <option value="<?php echo $loc['LOCATION_ID']; ?>"><?php echo $loc['LOCATION_NAME']; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-group">
            <label>2. Building</label>
            <select id="sel_building" class="filter-select" onchange="loadPens()" disabled>
                <option value="">-- Select Building --</option>
            </select>
        </div>
        <div class="filter-group">
            <label>3. Pen</label>
            <select id="sel_pen" class="filter-select" onchange="loadAnimals()" disabled>
                <option value="">-- Select Pen --</option>
            </select>
        </div>
        <div class="filter-group">
            <label>4. Animal (Tag No)</label>
            <select id="sel_animal" class="filter-select" onchange="loadAnimalData()" disabled>
                <option value="">-- Select Animal --</option>
            </select>
        </div>
    </div>

    <form id="fcrForm" class="calc-card">
        <div class="section-title">Performance Metrics <span class="loader" id="dataLoader">Loading Data...</span></div>
        
        <input type="hidden" id="in_animal_id" name="animal_id">
        <input type="hidden" id="in_pen_id" name="pen_id">
        <input type="hidden" id="in_class_id" name="class_id">

        <div class="form-grid">
            <div class="form-item">
                <label>Weight at Birth (kg)</label>
                <input type="number" id="in_birth_weight" name="birth_weight" class="input-system" readonly>
                <span class="info-badge">System Record (Birth Weight)</span>
            </div>

            <div class="form-item">
                <label>Total Feed Consumed (kg)</label>
                <input type="number" id="in_feed_share" name="feed_share" class="input-system" readonly>
                <span class="info-badge">Calculated Feed Share</span>
            </div>

            <div class="form-item">
                <label>Current Actual Weight (kg)</label>
                <input type="number" id="in_actual_weight" name="actual_weight" class="input-system" step="0.01" readonly style="color:#f59e0b; font-weight:bold;">
                <span class="info-badge">Database Record</span>
            </div>
        </div>

        <div class="form-grid">
            <div class="form-item">
                <label>Est. Total Gain (kg)</label>
                <input type="number" id="in_gain" class="input-system" readonly>
                <span class="info-badge">Feed × FCR</span>
            </div>

            <div class="form-item">
                <label style="color:#f59e0b;">FCR (Editable)</label>
                <input type="number" id="in_fcr" name="fcr" class="input-user" step="0.01" oninput="calculateFromFCR()">
                <span class="info-badge" style="color:#f59e0b;">Updates Est. Weight</span>
            </div>
            
            <div class="form-item">
                <label>Calculated Estimated Weight (kg)</label>
                <input type="number" id="in_est_weight" name="est_weight" class="input-system" step="0.01" readonly style="color:#60a5fa; font-weight:bold;">
                <span class="info-badge">Birth + (Feed × FCR)</span>
            </div>
            
            <div class="form-item">
                <label>Date of Weighing</label>
                <input type="date" id="in_weigh_date" name="weigh_date" class="input-user" required>
            </div>
        </div>

        <div class="action-row">
            <button type="submit" class="btn-save">Update Record & Class Standard</button>
        </div>
    </form>
</div>

<script>
    // --- 1. DROPDOWNS ---
    function loadBuildings() {
        const locId = document.getElementById('sel_location').value;
        const bldgSel = document.getElementById('sel_building');
        bldgSel.innerHTML = '<option>Loading...</option>'; bldgSel.disabled = true;
        resetForm();
        if(!locId) return;
        fetch(`../process/getBuildingsByLocation.php?location_id=${locId}`)
            .then(r => r.json())
            .then(data => {
                bldgSel.innerHTML = '<option value="">-- Select Building --</option>';
                data.buildings.forEach(b => bldgSel.innerHTML += `<option value="${b.BUILDING_ID}">${b.BUILDING_NAME}</option>`);
                bldgSel.disabled = false;
            });
    }

    function loadPens() {
        const bldgId = document.getElementById('sel_building').value;
        const penSel = document.getElementById('sel_pen');
        penSel.innerHTML = '<option>Loading...</option>'; penSel.disabled = true;
        resetForm();
        if(!bldgId) return;
        fetch(`../process/getPensByBuilding.php?building_id=${bldgId}`)
            .then(r => r.json())
            .then(data => {
                penSel.innerHTML = '<option value="">-- Select Pen --</option>';
                data.pens.forEach(p => penSel.innerHTML += `<option value="${p.PEN_ID}">${p.PEN_NAME}</option>`);
                penSel.disabled = false;
            });
    }

    function loadAnimals() {
        const penId = document.getElementById('sel_pen').value;
        const animSel = document.getElementById('sel_animal');
        animSel.innerHTML = '<option>Loading...</option>'; animSel.disabled = true;
        resetForm();
        if(!penId) return;
        fetch(`../process/getAnimalsByPen.php?pen_id=${penId}`)
            .then(r => r.json())
            .then(data => {
                animSel.innerHTML = '<option value="">-- Select Animal --</option>';
                if(data.animal_record && data.animal_record.length > 0) {
                    data.animal_record.forEach(a => animSel.innerHTML += `<option value="${a.ANIMAL_ID}">${a.TAG_NO}</option>`);
                    animSel.disabled = false;
                } else {
                    animSel.innerHTML = '<option>No Animals in Pen</option>';
                }
            });
    }

    // --- 2. LOGIC ---
    function loadAnimalData() {
        const animalId = document.getElementById('sel_animal').value;
        const penId = document.getElementById('sel_pen').value;
        const formCard = document.getElementById('fcrForm');
        const loader = document.getElementById('dataLoader');

        if(!animalId) { formCard.style.display = 'none'; return; }

        formCard.style.display = 'block';
        loader.style.display = 'inline';

        document.getElementById('in_animal_id').value = animalId;
        document.getElementById('in_pen_id').value = penId;
        document.getElementById('in_weigh_date').value = new Date().toISOString().split('T')[0];

        fetch(`../process/getAnimalFCR.php?animal_id=${animalId}&pen_id=${penId}`)
            .then(r => r.json())
            .then(data => {
                loader.style.display = 'none';
                if(data.success) {
                    document.getElementById('in_birth_weight').value = parseFloat(data.birth_weight) || 0;
                    document.getElementById('in_feed_share').value = parseFloat(data.feed_share) || 0;
                    document.getElementById('in_class_id').value = data.class_id;
                    
                    document.getElementById('in_actual_weight').value = parseFloat(data.current_actual_weight) || 0;
                    document.getElementById('in_fcr').value = parseFloat(data.standard_fcr) || 0;
                    
                    // Trigger calculation
                    calculateFromFCR(); 
                } else {
                    alert("Error: " + data.message);
                }
            });
    }

    // UPDATED LOGIC: Gain = Feed * FCR
    function calculateFromFCR() {
        const birth = parseFloat(document.getElementById('in_birth_weight').value) || 0;
        const feed = parseFloat(document.getElementById('in_feed_share').value) || 0;
        const fcr = parseFloat(document.getElementById('in_fcr').value) || 0;

        // Logic: Gain = Feed * FCR (As per user request)
        const gain = feed * fcr;
        const estWeight = birth + gain;
        
        document.getElementById('in_gain').value = gain.toFixed(2);
        document.getElementById('in_est_weight').value = estWeight.toFixed(2);
    }

    function resetForm() { document.getElementById('fcrForm').style.display = 'none'; }

    // --- 3. SAVE ---
    document.getElementById('fcrForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const fcr = document.getElementById('in_fcr').value;
        if(fcr <= 0) { alert("Please enter a valid FCR."); return; }

        const formData = new FormData(this);
        fetch('../process/saveAnimalFCR.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if(data.success) { alert("✅ FCR Updated & Weight Logged!"); location.reload(); }
            else { alert("❌ Error: " + data.message); }
        });
    });
</script>

</body>
</html>