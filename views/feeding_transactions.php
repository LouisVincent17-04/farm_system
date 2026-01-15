<?php
// views/feed_transactions.php
error_reporting(0);
ini_set('display_errors', 0);

$page="transactions";
include '../common/navbar.php';
include '../config/Connection.php';
include '../security/checkRole.php';    
checkRole(2);

try {
    if (!isset($conn)) { throw new Exception("Database connection failed."); }

    // 1. Transaction History (For viewing only)
    $transactions_sql = "
        SELECT 
            ft.FT_ID,
            ft.TRANSACTION_DATE,
            DATE_FORMAT(ft.TRANSACTION_DATE, '%d-%b-%y %h:%i %p') AS FORMATTED_DATE,
            ft.QUANTITY_KG,
            ft.REMARKS,
            a.TAG_NO,
            p.PEN_NAME,
            f.FEED_NAME
        FROM FEED_TRANSACTIONS ft
        LEFT JOIN ANIMAL_RECORDS a ON ft.ANIMAL_ID = a.ANIMAL_ID
        LEFT JOIN PENS p ON a.PEN_ID = p.PEN_ID
        LEFT JOIN FEEDS f ON ft.FEED_ID = f.FEED_ID
        ORDER BY ft.TRANSACTION_DATE DESC, ft.FT_ID DESC
        LIMIT 100
    ";
    $stmt = $conn->prepare($transactions_sql);
    $stmt->execute();
    $transactions_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Locations/Feeds for Modal
    $locations = $conn->query("SELECT LOCATION_ID, LOCATION_NAME FROM LOCATIONS ORDER BY LOCATION_NAME ASC")->fetchAll(PDO::FETCH_ASSOC);
    $feeds = $conn->query("SELECT FEED_ID, FEED_NAME, TOTAL_WEIGHT_KG, LOCATION_ID FROM FEEDS ORDER BY FEED_NAME ASC")->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    echo "<script>console.error('Database Error: " . addslashes($e->getMessage()) . "');</script>";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feeding Transactions</title>
    <style>
        /* --- CORE STYLES --- */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); min-height: 100vh; color: white; }
        .container { max-width: 1400px; margin: 0 auto; padding: 2rem; }

        /* HEADER */
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; }
        .header-info h1 { font-size: 2.5rem; font-weight: bold; margin-bottom: 0.5rem; }
        .header-info p { color: #cbd5e1; }

        .header-actions { display: flex; gap: 12px; align-items: center; }

        /* BUTTONS */
        .btn-base { display: flex; align-items: center; gap: 8px; border: none; padding: 12px 24px; border-radius: 8px; font-weight: 600; font-size: 0.95rem; cursor: pointer; transition: all 0.2s; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        
        .add-btn { background: linear-gradient(135deg, #2563eb, #9333ea); color: white; }
        .add-btn:hover { background: linear-gradient(135deg, #1d4ed8, #7c3aed); transform: translateY(-2px); }

        /* GLOBAL UNDO BUTTON */
        .global-undo-btn {
            background: rgba(245, 158, 11, 0.15); 
            color: #fbbf24;
            border: 1px solid rgba(245, 158, 11, 0.3);
        }
        .global-undo-btn:hover {
            background: rgba(245, 158, 11, 0.25);
            border-color: #fbbf24;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(251, 191, 36, 0.1);
        }

        /* SEARCH & TABLE */
        .search-container { position: relative; margin-bottom: 2rem; }
        .search-input { width: 100%; padding: 14px 14px 14px 45px; background: rgba(30, 41, 59, 0.5); border: 1px solid #475569; border-radius: 8px; color: white; font-size: 1rem; backdrop-filter: blur(10px); }
        .search-icon { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #94a3b8; }
        
        .table-container { background: rgba(30, 41, 59, 0.5); border-radius: 12px; border: 1px solid #475569; overflow-x: auto; }
        .table { width: 100%; border-collapse: collapse; min-width: 1000px; }
        .table th { padding: 1rem 1.5rem; text-align: left; font-size: 0.85rem; font-weight: 600; color: #e2e8f0; text-transform: uppercase; background: rgba(15, 23, 42, 0.5); }
        .table td { padding: 1rem 1.5rem; vertical-align: middle; color: #cbd5e1; border-bottom: 1px solid rgba(255,255,255,0.05); }
        .table tbody tr:hover { background: rgba(255, 255, 255, 0.02); }

        .tag-badge { background: linear-gradient(135deg, #667eea, #764ba2); color: white; padding: 4px 10px; border-radius: 6px; font-size: 0.8rem; font-weight: 600; }
        .pen-badge { background: rgba(16, 185, 129, 0.1); color: #10b981; padding: 4px 8px; border-radius: 6px; font-size: 0.8rem; border: 1px solid rgba(16, 185, 129, 0.2); }
        .amount { color: #34d399; font-weight: 600; font-family: 'Segoe UI', monospace; }

        /* MODAL */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.7); backdrop-filter: blur(4px); z-index: 1000; align-items: center; justify-content: center; }
        .modal.show { display: flex; }
        .modal-content { background: #1e293b; border-radius: 12px; width: 95%; max-width: 600px; border: 1px solid #475569; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5); }
        .modal-header { padding: 20px; border-bottom: 1px solid #334155; }
        .modal-body { padding: 20px; max-height: 70vh; overflow-y: auto; }
        .modal-footer { padding: 20px; border-top: 1px solid #334155; display: flex; justify-content: flex-end; gap: 10px; }

        /* FORM ELEMENTS - FIXED */
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px; }
        .form-group { margin-bottom: 15px; display: flex; flex-direction: column; }
        
        .form-group label { 
            display: block; 
            color: #94a3b8; 
            font-size: 0.85rem; 
            margin-bottom: 8px; 
            font-weight: 500; 
        }

        /* Unified Input Styling for Text, Number, Select, Date */
        .form-group input, 
        .form-group select, 
        .form-group textarea { 
            width: 100%; 
            padding: 12px; 
            background: #0f172a; 
            border: 1px solid #334155; 
            border-radius: 8px; 
            color: white; 
            font-size: 0.95rem; 
            transition: border-color 0.2s;
        }

        .form-group input:focus, 
        .form-group select:focus, 
        .form-group textarea:focus { 
            outline: none; 
            border-color: #3b82f6; 
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        /* Specific fix for Date Inputs to have dark calendar */
        input[type="datetime-local"] {
            color-scheme: dark;
        }

        select[disabled], input[disabled] { opacity: 0.6; cursor: not-allowed; background: #1e293b; }

        /* Summary Box */
        .summary-box { background: rgba(15, 23, 42, 0.6); border: 1px solid #3b82f6; border-radius: 8px; padding: 15px; text-align: center; margin-top: 15px; display: none; }
        .summary-title { color: #94a3b8; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 1px; }
        .summary-value { font-size: 2rem; font-weight: 800; color: white; margin: 5px 0; }
        .stock-warning { color: #ef4444; font-size: 0.85rem; margin-top: 10px; display: none; font-weight: bold; }
        
        .alert { padding: 12px; border-radius: 6px; margin-bottom: 15px; display: none; text-align: center; }
        .alert.success { background: rgba(16, 185, 129, 0.2); color: #34d399; border: 1px solid #10b981; }
        .alert.error { background: rgba(239, 68, 68, 0.2); color: #f87171; border: 1px solid #ef4444; }
        
        .loading { display: inline-block; width: 12px; height: 12px; border: 2px solid #fff; border-radius: 50%; border-top-color: transparent; animation: spin 0.8s linear infinite; margin-left: 5px; }
        @keyframes spin { to { transform: rotate(360deg); } }

        .btn-cancel { padding: 10px 20px; background: transparent; border: 1px solid #475569; color: #cbd5e1; border-radius: 6px; cursor: pointer; }
        .btn-save { padding: 10px 20px; background: #2563eb; border: none; color: white; border-radius: 6px; cursor: pointer; font-weight: 600; }
        .btn-save:disabled { opacity: 0.5; cursor: not-allowed; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="header-info">
                <h1>Feeding Management</h1>
                <p>Record and track bulk animal feeding</p>
            </div>
            
            <div class="header-actions">
                <button class="btn-base global-undo-btn" onclick="undoLastFeed()">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"></path>
                    </svg>
                    Undo Last Feed
                </button>

                <button class="btn-base add-btn" onclick="openAddModal()">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                    </svg>
                    Bulk Feed by Pen
                </button>
            </div>
        </div>

        <div class="search-container">
            <input type="text" class="search-input" id="searchInput" placeholder="Search logs by tag, pen, or feed..." onkeyup="filterTable()">
        </div>

        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Date & Time</th>
                        <th>Pen Name</th>
                        <th>Animal Tag</th>
                        <th>Feed Used</th>
                        <th>Qty (KG)</th>
                        <th>Remarks</th>
                    </tr>
                </thead>
                <tbody id="transaction-table">
                    <?php foreach($transactions_data as $row): ?>
                    <tr>
                        <td style="color:#94a3b8;"><?php echo $row['FORMATTED_DATE']; ?></td>
                        <td><span class="pen-badge"><?php echo htmlspecialchars($row['PEN_NAME']); ?></span></td>
                        <td><span class="tag-badge"><?php echo htmlspecialchars($row['TAG_NO']); ?></span></td>
                        <td style="font-weight: 500; color: #fff;"><?php echo htmlspecialchars($row['FEED_NAME']); ?></td>
                        <td class="amount"><?php echo number_format($row['QUANTITY_KG'], 2); ?></td>
                        <td style="font-size:0.9rem; color:#cbd5e1;"><?php echo htmlspecialchars($row['REMARKS'] ?? '-'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div id="empty-state" style="text-align:center; padding:3rem; display:none; color:#94a3b8;">
                No records found matching your search.
            </div>
        </div>
    </div>

    <div id="modal" class="modal">
        <div class="modal-content">
            <div class="modal-header"><h2>Bulk Feed by Pen</h2></div>
            <div class="modal-body">
                <div id="modal-alert" class="alert"></div>
                <form id="bulk-feed-form">
                    
                    <div class="form-group">
                        <label style="color:#93c5fd; font-size:1rem; margin-bottom:15px;">1. Select Pen Location</label>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Location</label>
                                <select id="location_id" onchange="handleLocationChange()">
                                    <option value="">Select Location</option>
                                    <?php foreach($locations as $loc): ?>
                                        <option value="<?php echo $loc['LOCATION_ID']; ?>"><?php echo htmlspecialchars($loc['LOCATION_NAME']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Building</label>
                                <select id="building_id" onchange="loadPens()" disabled><option value="">Select Location First</option></select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Pen Name <span id="pen-loading" style="display:none;" class="loading"></span></label>
                            <select id="pen_id" onchange="getPenDetails()" disabled><option value="">Select Building First</option></select>
                        </div>
                    </div>

                    <div id="feed-section" style="opacity: 0.5; pointer-events: none;">
                        <label style="color:#93c5fd; font-size:1rem; margin-top:20px; display:block; margin-bottom:15px;">2. Feeding Details</label>
                        <div class="form-group">
                            <label>Feed Selection</label>
                            <select id="feed_id" onchange="calculateTotal()" disabled>
                                <option value="">Select Location First</option>
                                <?php foreach($feeds as $feed): ?>
                                    <option value="<?php echo $feed['FEED_ID']; ?>" 
                                            data-stock="<?php echo $feed['TOTAL_WEIGHT_KG']; ?>"
                                            data-location-id="<?php echo $feed['LOCATION_ID']; ?>">
                                        <?php echo htmlspecialchars($feed['FEED_NAME']); ?> (Stock: <?php echo $feed['TOTAL_WEIGHT_KG']; ?>kg)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-row">
                            <div class="form-group"> <label>Feed per Animal (kg)</label>
                                <input type="number" id="qty_per_head" step="0.01" min="0.01" placeholder="e.g. 0.5" oninput="calculateTotal()">
                            </div>
                            <div class="form-group"> <label>Date & Time</label>
                                <input type="datetime-local" id="transaction_date" required>
                            </div>
                        </div>
                    </div>

                    <div class="summary-box" id="summary-box">
                        <div class="summary-title">Total to Deduct</div>
                        <div class="summary-value"><span id="total-deduction">0.00</span> kg</div>
                        <div style="color:#64748b; font-size:0.9rem; margin-top:5px;">
                            Feeding <span id="animal-count-display" style="color:#34d399; font-weight:bold;">0</span> animals 
                            x <span id="per-head-display">0</span> kg/head
                        </div>
                        <div id="stock-warning" class="stock-warning">⚠️ Insufficient Stock!</div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn-cancel" onclick="closeModal()">Cancel</button>
                <button class="btn-save" id="btn-save" onclick="saveBulkFeed()">Confirm Feeding</button>
            </div>
        </div>
    </div>

    <script>
        let currentAnimalCount = 0;

        // --- LOCATION & HIERARCHY ---
        function handleLocationChange() { loadBuildings(); filterFeedsByLocation(); }

        function filterFeedsByLocation() {
            const locId = document.getElementById('location_id').value;
            const feedSelect = document.getElementById('feed_id');
            const options = feedSelect.querySelectorAll('option');

            feedSelect.value = "";
            if (!locId) { feedSelect.disabled = true; options[0].textContent = "Select Location First"; return; }

            feedSelect.disabled = false;
            options[0].textContent = "Select Feed";
            let visibleCount = 0;
            
            options.forEach(opt => {
                if (opt.value === "") return;
                if (opt.getAttribute('data-location-id') == locId) {
                    opt.style.display = ""; visibleCount++;
                } else {
                    opt.style.display = "none";
                }
            });
            if (visibleCount === 0) { options[0].textContent = "No feeds available here"; feedSelect.disabled = true; }
        }

        async function loadBuildings() {
            const locId = document.getElementById('location_id').value;
            const bldg = document.getElementById('building_id');
            const pen = document.getElementById('pen_id');
            
            bldg.innerHTML = '<option>Loading...</option>'; bldg.disabled = true;
            pen.innerHTML = '<option>Select Building First</option>'; pen.disabled = true;
            if(!locId) return;

            const res = await fetch(`../process/getHierarchyPlaceData.php?action=get_buildings&location_id=${locId}`);
            const data = await res.json();
            bldg.innerHTML = '<option value="">Select Building</option>';
            data.forEach(b => bldg.innerHTML += `<option value="${b.BUILDING_ID}">${b.BUILDING_NAME}</option>`);
            bldg.disabled = false;
        }

        async function loadPens() {
            const bldgId = document.getElementById('building_id').value;
            const pen = document.getElementById('pen_id');
            pen.innerHTML = '<option>Loading...</option>'; pen.disabled = true;
            if(!bldgId) return;

            const res = await fetch(`../process/getHierarchyPlaceData.php?action=get_pens&building_id=${bldgId}`);
            const data = await res.json();
            pen.innerHTML = '<option value="">Select Pen</option>';
            data.forEach(p => pen.innerHTML += `<option value="${p.PEN_ID}">${p.PEN_NAME}</option>`);
            pen.disabled = false;
        }

        async function getPenDetails() {
            const penId = document.getElementById('pen_id').value;
            const sec = document.getElementById('feed-section');
            const sum = document.getElementById('summary-box');
            
            if(!penId) { sec.style.opacity="0.5"; sec.style.pointerEvents="none"; sum.style.display="none"; return; }

            document.getElementById('pen-loading').style.display='inline-block';
            const res = await fetch(`../process/getHierarchyPlaceData.php?action=get_pen_details&pen_id=${penId}`);
            const data = await res.json();
            document.getElementById('pen-loading').style.display='none';
            
            currentAnimalCount = parseInt(data.count);
            if(currentAnimalCount === 0) {
                alert("⚠️ This pen is empty!");
                sec.style.opacity="0.5"; sec.style.pointerEvents="none"; sum.style.display="none";
            } else {
                sec.style.opacity="1"; sec.style.pointerEvents="auto"; sum.style.display="block";
                calculateTotal();
            }
        }

        function calculateTotal() {
            const qty = parseFloat(document.getElementById('qty_per_head').value) || 0;
            const total = currentAnimalCount * qty;
            
            document.getElementById('animal-count-display').textContent = currentAnimalCount;
            document.getElementById('per-head-display').textContent = qty;
            document.getElementById('total-deduction').textContent = total.toFixed(2);

            const feed = document.getElementById('feed_id');
            const opt = feed.options[feed.selectedIndex];
            const warn = document.getElementById('stock-warning');
            const btn = document.getElementById('btn-save');
            
            if(opt && opt.dataset.stock) {
                if(total > parseFloat(opt.dataset.stock)) {
                    warn.style.display = 'block'; btn.disabled = true;
                } else {
                    warn.style.display = 'none'; btn.disabled = false;
                }
            }
        }

        // --- GLOBAL UNDO LOGIC ---
        function undoLastFeed() {
            if(confirm("Are you sure you want to UNDO the very last feeding transaction? \n\nThis will remove the records and restore the stock.")) {
                const btn = document.querySelector('.global-undo-btn');
                const origText = btn.innerHTML;
                btn.disabled = true; btn.innerHTML = 'Restoring...';

                fetch('../process/undoFeedTransaction.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'action=undo_last' 
                })
                .then(r => r.json())
                .then(data => {
                    if(data.success) {
                        alert(data.message);
                        window.location.reload();
                    } else {
                        alert("Error: " + data.message);
                        btn.disabled = false; btn.innerHTML = origText;
                    }
                })
                .catch(e => {
                    alert("System Error");
                    btn.disabled = false; btn.innerHTML = origText;
                });
            }
        }

        // --- SAVE BULK FEED ---
        function saveBulkFeed() {
            const penId = document.getElementById('pen_id').value;
            const feedId = document.getElementById('feed_id').value;
            const qty = document.getElementById('qty_per_head').value;
            const date = document.getElementById('transaction_date').value;

            if(!penId || !feedId || !qty || !date) { alert("Fill all fields"); return; }

            const btn = document.getElementById('btn-save');
            btn.disabled = true; btn.innerHTML = 'Saving...';

            const fd = new FormData();
            fd.append('pen_id', penId); fd.append('feed_id', feedId);
            fd.append('qty_per_head', qty); fd.append('transaction_date', date);

            fetch('../process/addFeedTransaction.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => {
                if(d.success) { alert(d.message); window.location.reload(); }
                else { alert(d.message); btn.disabled=false; btn.innerHTML='Confirm Feeding'; }
            });
        }

        // --- UTILS ---
        function openAddModal() {
            document.getElementById('modal').classList.add('show');
            const now = new Date(); now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
            document.getElementById('transaction_date').value = now.toISOString().slice(0,16);
        }
        function closeModal() { document.getElementById('modal').classList.remove('show'); }
        
        function filterTable() {
            const term = document.getElementById('searchInput').value.toLowerCase();
            const rows = document.querySelectorAll('tbody tr');
            let visible = 0;
            rows.forEach(r => {
                if(r.textContent.toLowerCase().includes(term)) { r.style.display=''; visible++; }
                else { r.style.display='none'; }
            });
            document.getElementById('empty-state').style.display = visible===0 ? 'block':'none';
        }

        // document.getElementById('modal').addEventListener('click', function(e){ if(e.target===this) closeModal() });
        document.addEventListener('DOMContentLoaded', filterTable);
    </script>
</body>
</html>