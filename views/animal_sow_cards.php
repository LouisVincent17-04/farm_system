<?php
// views/manage_sow_cards.php
$page = "farm";
include '../common/navbar.php';
include '../config/Connection.php';
include '../security/checkRole.php';    
checkRole(2);

// --- 1. INITIALIZE VARIABLES ---
$locations = [];
$buildings = [];
$pens = [];
$sow_list = [];
$selected_sow_data = null;
$history = [];

$location_id = $_GET['location_id'] ?? '';
$building_id = $_GET['building_id'] ?? '';
$pen_id = $_GET['pen_id'] ?? '';
$selected_animal_id = $_GET['animal_id'] ?? '';

try {
    // --- 2. FETCH DROPDOWNS ---
    $stmt = $conn->prepare("SELECT * FROM locations ORDER BY LOCATION_NAME");
    $stmt->execute();
    $locations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($location_id) {
        $stmt = $conn->prepare("SELECT * FROM buildings WHERE LOCATION_ID = ? ORDER BY BUILDING_NAME");
        $stmt->execute([$location_id]);
        $buildings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    if ($building_id) {
        $stmt = $conn->prepare("SELECT * FROM pens WHERE BUILDING_ID = ? ORDER BY PEN_NAME");
        $stmt->execute([$building_id]);
        $pens = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // --- 3. FETCH FILTERED SOWS ---
    // Only execute if at least Location is selected
    if ($location_id) {
        $query = "
            SELECT 
                ar.ANIMAL_ID, 
                ar.TAG_NO, 
                ac.STAGE_NAME,
                l.LOCATION_NAME,
                b.BUILDING_NAME,
                p.PEN_NAME,
                (SELECT COUNT(*) FROM sow_birthing_records WHERE ANIMAL_ID = ar.ANIMAL_ID) as PARITY_COUNT
            FROM animal_records ar
            JOIN animal_classifications ac ON ar.CLASS_ID = ac.CLASS_ID
            LEFT JOIN locations l ON ar.LOCATION_ID = l.LOCATION_ID
            LEFT JOIN buildings b ON ar.BUILDING_ID = b.BUILDING_ID
            LEFT JOIN pens p ON ar.PEN_ID = p.PEN_ID
            WHERE ar.IS_ACTIVE = 1 
            AND (ac.STAGE_NAME LIKE '%Sow%' OR ac.STAGE_NAME LIKE '%Gilt%')
        ";
        
        $params = [];

        if ($location_id) {
            $query .= " AND ar.LOCATION_ID = ?";
            $params[] = $location_id;
        }
        if ($building_id) {
            $query .= " AND ar.BUILDING_ID = ?";
            $params[] = $building_id;
        }
        if ($pen_id) {
            $query .= " AND ar.PEN_ID = ?";
            $params[] = $pen_id;
        }

        $query .= " ORDER BY ar.TAG_NO ASC";

        $stmt = $conn->prepare($query);
        $stmt->execute($params);
        $sow_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // --- 4. FETCH SELECTED SOW DATA ---
    if ($selected_animal_id) {
        $stmt = $conn->prepare("SELECT * FROM animal_records WHERE ANIMAL_ID = ?");
        $stmt->execute([$selected_animal_id]);
        $selected_sow_data = $stmt->fetch(PDO::FETCH_ASSOC);
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sow Card Management</title>
    <style>
        /* --- CORE STYLES --- */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: #e2e8f0; min-height: 100vh; }
        .container { max-width: 1400px; margin: 0 auto; padding: 2rem; }

        /* Filter Bar - Responsive Grid */
        .filter-card {
            background: rgba(30, 41, 59, 0.6); border: 1px solid rgba(255,255,255,0.1);
            border-radius: 12px; padding: 1.5rem; margin-bottom: 2rem;
            display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;
            align-items: flex-end;
        }
        .form-group { display: flex; flex-direction: column; gap: 5px; }
        .form-group label { font-size: 0.9rem; color: #94a3b8; font-weight: 600; }
        .form-select, .form-input {
            padding: 10px; background: #1e293b; border: 1px solid #475569; 
            color: white; border-radius: 6px; width: 100%; font-size: 1rem;
        }

        /* Sow List Table - Scrollable */
        .table-container {
            background: rgba(15, 23, 42, 0.6); border-radius: 12px; overflow: hidden; margin-bottom: 3rem;
            border: 1px solid rgba(255,255,255,0.05); max-height: 400px; overflow-y: auto;
        }
        
        /* SCROLL WRAPPER FOR MOBILE */
        .table-scroll-wrapper {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .data-table { width: 100%; border-collapse: collapse; min-width: 800px; /* Force width for scroll */ }
        .data-table th { background: rgba(30, 41, 59, 0.8); padding: 15px; text-align: left; color: #94a3b8; font-size: 0.85rem; text-transform: uppercase; position: sticky; top: 0; z-index: 10; white-space: nowrap; }
        .data-table td { padding: 12px 15px; border-bottom: 1px solid rgba(255,255,255,0.05); color: #cbd5e1; }
        .data-table tr:hover { background: rgba(255,255,255,0.02); }
        .btn-select {
            background: rgba(236, 72, 153, 0.1); color: #f472b6; border: 1px solid rgba(236, 72, 153, 0.3);
            padding: 6px 12px; border-radius: 6px; cursor: pointer; text-decoration: none; display: inline-block; font-size: 0.85rem; white-space: nowrap;
        }
        .btn-select.active { background: #ec4899; color: white; }

        /* Sow Card Detail Area */
        .detail-section { display: none; margin-top: 30px; animation: fadeIn 0.3s ease; }
        .detail-section.active { display: block; }
        @keyframes fadeIn { from { opacity:0; transform:translateY(10px); } to { opacity:1; transform:translateY(0); } }

        .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid #334155; padding-bottom: 10px; flex-wrap: wrap; gap: 10px; }
        .sow-title { font-size: 2rem; font-weight: 800; color: white; margin: 0; }
        .sow-tag { color: #ec4899; }

        .btn-add { background: linear-gradient(135deg, #ec4899, #db2777); color: white; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; white-space: nowrap; }

        /* History Table */
        .history-table { width: 100%; border-collapse: collapse; margin-top: 15px; min-width: 600px; }
        .history-table th { background: rgba(236, 72, 153, 0.1); color: #f472b6; padding: 12px; text-align: left; white-space: nowrap; }
        .history-table td { padding: 12px; border-bottom: 1px solid rgba(255,255,255,0.05); }
        .btn-edit { background: transparent; border: none; color: #60a5fa; cursor: pointer; font-size: 1.2rem; }

        /* Modal */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.8); z-index: 1000; align-items: center; justify-content: center; backdrop-filter: blur(4px); padding: 1rem; }
        .modal.show { display: flex; }
        .modal-content { background: #1e293b; width: 100%; max-width: 500px; border-radius: 12px; border: 1px solid #475569; padding: 2rem; max-height: 90vh; overflow-y: auto; }
        
        .form-grid-modal { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .form-row-modal { margin-bottom: 15px; }
        .form-row-modal label { display: block; color: #94a3b8; margin-bottom: 5px; font-size: 0.9rem; }
        .form-row-modal input { width: 100%; padding: 10px; background: #0f172a; border: 1px solid #334155; border-radius: 6px; color: white; }
        
        .modal-footer { display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px; }
        .btn-cancel { background: transparent; border: 1px solid #475569; color: #cbd5e1; padding: 8px 16px; border-radius: 6px; cursor: pointer; }
        .btn-save { background: #ec4899; border: none; color: white; padding: 8px 16px; border-radius: 6px; font-weight: 600; cursor: pointer; }

        /* Responsive Breakpoints */
        @media (max-width: 768px) {
            .container { padding: 1rem; }
            .filter-card { grid-template-columns: 1fr; gap: 1rem; }
            .card-header { flex-direction: column; align-items: flex-start; }
            .sow-title { font-size: 1.5rem; }
            .form-grid-modal { grid-template-columns: 1fr; } /* Stack modal inputs */
            .modal-content { padding: 1.5rem; }
            .btn-add, .btn-save, .btn-cancel { width: 100%; margin-top: 5px; }
            .modal-footer { flex-direction: column-reverse; }
        }
    </style>
</head>
<body>

<div class="container">
    <h1 style="margin-bottom: 2rem;">Sow Card Management</h1>

    <form class="filter-card" method="GET" id="filterForm">
        <div class="form-group">
            <label>1. Location</label>
            <select name="location_id" class="form-select" onchange="this.form.submit()">
                <option value="">-- Choose Location --</option>
                <?php foreach($locations as $loc): ?>
                    <option value="<?php echo $loc['LOCATION_ID']; ?>" <?php echo $location_id == $loc['LOCATION_ID'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($loc['LOCATION_NAME']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label>2. Building</label>
            <select name="building_id" class="form-select" <?php echo empty($buildings) ? 'disabled' : ''; ?> onchange="this.form.submit()">
                <option value="">-- All Buildings --</option>
                <?php foreach($buildings as $b): ?>
                    <option value="<?php echo $b['BUILDING_ID']; ?>" <?php echo $building_id == $b['BUILDING_ID'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($b['BUILDING_NAME']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label>3. Pen</label>
            <select name="pen_id" class="form-select" <?php echo empty($pens) ? 'disabled' : ''; ?> onchange="this.form.submit()">
                <option value="">-- All Pens --</option>
                <?php foreach($pens as $p): ?>
                    <option value="<?php echo $p['PEN_ID']; ?>" <?php echo $pen_id == $p['PEN_ID'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($p['PEN_NAME']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group" style="flex: 2;">
            <label>4. Search Sow (Tag No)</label>
            <input type="text" id="sowSearch" class="form-input" placeholder="<?php if(!isset($_GET['location_id'])) echo "Choose Location First and "; ?>Type tag to filter list..." onkeyup="filterSowTable()">
        </div>
    </form>

    <?php if($location_id): ?>
        <div class="table-container">
            <div class="table-scroll-wrapper">
                <table class="data-table" id="sowTable">
                    <thead>
                        <tr>
                            <th>Tag No</th>
                            <th>Classification</th>
                            <th>Location</th>
                            <th>Parity</th>
                            <th style="text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($sow_list)): ?>
                            <tr><td colspan="5" style="text-align:center; padding:20px;">No Sows found in this selection.</td></tr>
                        <?php else: ?>
                            <?php foreach($sow_list as $row): 
                                $isActive = ($selected_animal_id == $row['ANIMAL_ID']);
                            ?>
                            <tr>
                                <td style="font-weight:bold; color:white;"><?php echo $row['TAG_NO']; ?></td>
                                <td><?php echo $row['STAGE_NAME']; ?></td>
                                <td><?php echo $row['BUILDING_NAME']; ?> - <?php echo $row['PEN_NAME']; ?></td>
                                <td><?php echo $row['PARITY_COUNT']; ?></td>
                                <td style="text-align: right;">
                                    <a href="?location_id=<?php echo $location_id; ?>&building_id=<?php echo $building_id; ?>&pen_id=<?php echo $pen_id; ?>&animal_id=<?php echo $row['ANIMAL_ID']; ?>" 
                                       class="btn-select <?php echo $isActive ? 'active' : ''; ?>">
                                        <?php echo $isActive ? 'Selected' : 'Select'; ?>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php else: ?>
        <div style="text-align:center; padding:40px; color:#64748b; border:1px dashed #475569; border-radius:12px;">
            Please select a <strong>Location</strong> to view Sows.
        </div>
    <?php endif; ?>

    <?php if($selected_sow_data): ?>
        <div id="detailSection" class="detail-section active">
            <div class="card-header">
                <h2 class="sow-title">Sow Card: <span class="sow-tag"><?php echo $selected_sow_data['TAG_NO']; ?></span></h2>
                <button class="btn-add" onclick="openRecordModal()">+ Add Birth Record</button>
            </div>

            <div class="table-container" style="max-height:none; background:transparent; border:none;">
                <div class="table-scroll-wrapper">
                    <table class="history-table">
                        <thead>
                            <tr>
                                <th>Parity</th>
                                <th>Date Farrowed</th>
                                <th>Born</th>
                                <th>Active</th>
                                <th>Dead</th>
                                <th>Mummified</th>
                                <th style="text-align:right;">Edit</th>
                            </tr>
                        </thead>
                        <tbody id="historyBody">
                            <tr><td colspan="7" style="text-align:center;">Loading history...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>

</div>

<div id="recordModal" class="modal">
    <div class="modal-content">
        <h2 id="modalTitle" style="margin-bottom:20px; color:white;">Add Record</h2>
        <form id="recordForm">
            <input type="hidden" id="record_id" name="record_id">
            <input type="hidden" id="animal_id" name="animal_id" value="<?php echo $selected_animal_id; ?>">
            <input type="hidden" id="action_type" name="action_type" value="add">

            <div class="form-row-modal">
                <label>Date Farrowed</label>
                <input type="date" id="date_farrowed" name="date_farrowed" required>
            </div>

            <div class="form-grid-modal">
                <div class="form-row-modal">
                    <label>Total Born</label>
                    <input type="number" id="total_born" name="total_born" required>
                </div>
                <div class="form-row-modal">
                    <label>Active (Survived)</label>
                    <input type="number" id="active_count" name="active_count" required>
                </div>
            </div>

            <div class="form-grid-modal">
                <div class="form-row-modal">
                    <label>Dead</label>
                    <input type="number" id="dead_count" name="dead_count" value="0">
                </div>
                <div class="form-row-modal">
                    <label>Mummified</label>
                    <input type="number" id="mummified_count" name="mummified_count" value="0">
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeModal()">Cancel</button>
                <button type="submit" class="btn-save" id="btnSave">Save Record</button>
            </div>
        </form>
    </div>
</div>

<script>
    // --- SCROLL TO DETAILS ---
    <?php if($selected_sow_data): ?>
        setTimeout(() => {
            document.getElementById('detailSection').scrollIntoView({ behavior: 'smooth' });
            loadHistory('<?php echo $selected_animal_id; ?>');
        }, 300);
    <?php endif; ?>

    // --- CLIENT SIDE FILTER ---
    function filterSowTable() {
        const input = document.getElementById('sowSearch');
        const filter = input.value.toUpperCase();
        const table = document.getElementById('sowTable');
        const tr = table.getElementsByTagName('tr');

        for (let i = 1; i < tr.length; i++) {
            const td = tr[i].getElementsByTagName('td')[0]; // Tag No Column
            if (td) {
                const txtValue = td.textContent || td.innerText;
                tr[i].style.display = txtValue.toUpperCase().indexOf(filter) > -1 ? "" : "none";
            }
        }
    }

    // --- HISTORY LOADER ---
    async function loadHistory(id) {
        const tbody = document.getElementById('historyBody');
        const res = await fetch(`../process/getSowRecords.php?id=${id}`);
        const data = await res.json();

        tbody.innerHTML = '';
        if(data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;">No records found.</td></tr>';
            return;
        }

        data.forEach(row => {
            const tr = `
                <tr>
                    <td style="font-weight:bold; color:#f472b6;">${row.PARITY}</td>
                    <td>${row.DATE_FARROWED}</td>
                    <td>${row.TOTAL_BORN}</td>
                    <td style="color:#34d399;">${row.ACTIVE_COUNT}</td>
                    <td style="color:#f87171;">${row.DEAD_COUNT}</td>
                    <td style="color:#a78bfa;">${row.MUMMIFIED_COUNT}</td>
                    <td style="text-align:right;">
                        <button class="btn-edit" onclick='openEditModal(${JSON.stringify(row)})'>✎</button>
                    </td>
                </tr>
            `;
            tbody.innerHTML += tr;
        });
    }

    // --- MODAL LOGIC ---
    const modal = document.getElementById('recordModal');
    const form = document.getElementById('recordForm');
    const title = document.getElementById('modalTitle');
    const btnSave = document.getElementById('btnSave');

    function openRecordModal() {
        form.reset();
        document.getElementById('action_type').value = 'add';
        document.getElementById('record_id').value = '';
        document.getElementById('animal_id').value = '<?php echo $selected_animal_id; ?>';
        title.textContent = 'Add Birth Record';
        btnSave.textContent = 'Save Record';
        modal.classList.add('show');
    }

    function openEditModal(data) {
        document.getElementById('action_type').value = 'edit';
        document.getElementById('record_id').value = data.RECORD_ID;
        document.getElementById('animal_id').value = data.ANIMAL_ID;
        
        document.getElementById('date_farrowed').value = data.DATE_FARROWED;
        document.getElementById('total_born').value = data.TOTAL_BORN;
        document.getElementById('active_count').value = data.ACTIVE_COUNT;
        document.getElementById('dead_count').value = data.DEAD_COUNT;
        document.getElementById('mummified_count').value = data.MUMMIFIED_COUNT;

        title.textContent = 'Edit Birth Record (Parity ' + data.PARITY + ')';
        btnSave.textContent = 'Update Changes';
        modal.classList.add('show');
    }

    function closeModal() { modal.classList.remove('show'); }

    form.addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        const action = document.getElementById('action_type').value;
        const endpoint = action === 'add' ? '../process/addBirthingRecord.php' : '../process/editBirthingRecord.php';

        fetch(endpoint, { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if(data.success) {
                alert("Success!");
                closeModal();
                loadHistory('<?php echo $selected_animal_id; ?>');
            } else {
                alert("Error: " + data.message);
            }
        });
    });

    modal.addEventListener('click', (e) => { if(e.target===modal) closeModal(); });
</script>

</body>
</html>