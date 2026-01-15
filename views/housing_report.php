<?php
// reports/housing_report.php
error_reporting(0);
ini_set('display_errors', 0);
$page = "reports";

include '../common/navbar.php';
include '../config/Connection.php';
include '../security/checkRole.php';
checkRole(2); // Farm Admin

// --- 1. GET FILTER INPUTS ---
$location_id  = $_GET['location'] ?? '';
$date_from    = $_GET['date_from'] ?? '';
$date_to      = $_GET['date_to'] ?? '';
$status       = $_GET['status'] ?? ''; // '1' = Active, '0' = Inactive
$search_term  = $_GET['search'] ?? '';

try {
    if (!isset($conn)) { throw new Exception("Database connection failed."); }

    // --- 2. BUILD SQL QUERY ---
    // Fetch items where ITEM_TYPE_ID = 3 (Housing & Facilities)
    $sql = "SELECT 
            i.ITEM_ID,
            i.ITEM_NAME,
            i.ITEM_DESCRIPTION,
            i.ITEM_NET_WEIGHT,
            i.DATE_OF_PURCHASE,
            i.QUANTITY,
            i.UNIT_COST,
            i.TOTAL_COST,
            i.STATUS,
            i.LOCATION_ID,
            l.LOCATION_NAME,
            DATE_FORMAT(i.CREATED_AT, '%Y-%m-%d') as DATE_ADDED
        FROM items i
        LEFT JOIN locations l ON i.LOCATION_ID = l.LOCATION_ID
        WHERE i.ITEM_TYPE_ID = 3";

    $params = [];

    // Filter: Location
    if ($location_id) {
        $sql .= " AND i.LOCATION_ID = :loc_id";
        $params[':loc_id'] = $location_id;
    }

    // Filter: Date Added Range
    if ($date_from && $date_to) {
        $sql .= " AND DATE(i.CREATED_AT) BETWEEN :date_from AND :date_to";
        $params[':date_from'] = $date_from;
        $params[':date_to']   = $date_to;
    }

    // Filter: Status
    if ($status !== '') {
        $sql .= " AND i.STATUS = :status";
        $params[':status'] = $status;
    }

    // Filter: Search
    if ($search_term) {
        $sql .= " AND (i.ITEM_NAME LIKE :search OR i.ITEM_DESCRIPTION LIKE :search)";
        $params[':search'] = "%$search_term%";
    }

    $sql .= " ORDER BY i.ITEM_NAME ASC"; 

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $raw_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // --- 3. PROCESS DATA & STATS ---
    $items = [];
    
    // Statistics
    $total_items_count = 0; // Total individual units (sum of quantity)
    $total_asset_value = 0;
    $unique_items = 0;
    $active_assets = 0;

    foreach ($raw_data as $row) {
        // Status Logic
        $row['STATUS_LABEL'] = ($row['STATUS'] == 1) ? 'In Use' : 'Inactive/Written Off';
        
        // Calculate dynamic total if missing in DB
        $calculated_total = ($row['TOTAL_COST'] > 0) ? $row['TOTAL_COST'] : ($row['QUANTITY'] * $row['UNIT_COST']);
        $row['CALCULATED_TOTAL'] = $calculated_total;

        // Aggregates
        $unique_items++;
        $total_items_count += $row['QUANTITY'];
        $total_asset_value += $calculated_total;
        
        if ($row['STATUS'] == 1) {
            $active_assets++;
        }

        $items[] = $row;
    }

    // --- 4. FETCH LOCATIONS FOR DROPDOWN ---
    $locations = $conn->query("SELECT * FROM locations ORDER BY LOCATION_NAME")->fetchAll();

} catch (Exception $e) {
    $items = [];
    error_log($e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Housing & Facilities Report</title>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.31/jspdf.plugin.autotable.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

    <style>
        /* --- GLOBAL STYLES --- */
        body { 
            font-family: system-ui, -apple-system, sans-serif; 
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); 
            color: #e2e8f0; 
            margin: 0; 
            padding-bottom: 40px;
        }
        .container { max-width: 1600px; margin: 0 auto; padding: 2rem; }
        
        .header { text-align: center; margin-bottom: 2rem; }
        .title { 
            font-size: 2.2rem; font-weight: 800; 
            background: linear-gradient(135deg, #06b6d4, #0891b2); /* Cyan for Housing */
            -webkit-background-clip: text; -webkit-text-fill-color: transparent; 
            margin-bottom: 0.5rem;
        }
        .subtitle { color: #94a3b8; font-size: 1rem; margin: 0; }

        /* --- STATS CARDS --- */
        .stats-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); 
            gap: 1.5rem; 
            margin-bottom: 2rem; 
        }
        .stat-card { 
            background: rgba(30, 41, 59, 0.6); 
            border: 1px solid rgba(255,255,255,0.1); 
            border-radius: 16px; 
            padding: 1.5rem; 
            text-align: center; 
            backdrop-filter: blur(10px); 
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        .stat-val { font-size: 2rem; font-weight: 800; margin-bottom: 0.25rem; color: #fff; }
        .stat-lbl { color: #94a3b8; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1px; font-weight: 600; }
        
        .text-cyan { color: #06b6d4; } 
        .text-red { color: #ef4444; }
        .text-green { color: #4ade80; }

        /* --- FILTER BAR --- */
        .filter-box { 
            background: rgba(15, 23, 42, 0.6); 
            border: 1px solid #334155; 
            padding: 1.5rem; 
            border-radius: 16px; 
            margin-bottom: 2rem; 
        }
        .filter-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); 
            gap: 1rem; 
            align-items: end; 
        }
        .form-group label { display: block; font-size: 0.75rem; color: #94a3b8; margin-bottom: 0.4rem; font-weight: 600; text-transform: uppercase; }
        .form-input { 
            width: 100%; padding: 10px; background: #0f172a; 
            border: 1px solid #334155; color: white; border-radius: 8px; 
            font-size: 0.9rem; box-sizing: border-box; 
        }
        .form-input:focus { border-color: #06b6d4; outline: none; }

        /* Buttons */
        .btn-group { display: flex; gap: 10px; flex-wrap: wrap; }
        .action-bar { 
            margin-top: 1.5rem; display: flex; gap: 10px; 
            justify-content: flex-end; flex-wrap: wrap;
            border-top: 1px solid rgba(255,255,255,0.1); padding-top: 1rem; 
        }

        .btn { 
            padding: 10px 20px; border: none; border-radius: 8px; 
            font-weight: 600; cursor: pointer; display: inline-flex; 
            align-items: center; gap: 8px; text-decoration: none; 
            font-size: 0.9rem; transition: transform 0.1s; white-space: nowrap;
        }
        .btn:active { transform: scale(0.98); }
        .btn-primary { background: #0891b2; color: white; }
        .btn-outline { background: transparent; border: 1px solid #475569; color: #cbd5e1; }
        .btn-export { background: #3b82f6; color: white; }
        .btn-excel { background: #10b981; color: white; }
        .btn-csv { background: #f59e0b; color: #1e293b; }

        /* --- TABLE --- */
        .table-wrap { 
            background: rgba(30, 41, 59, 0.5); 
            border-radius: 16px; 
            overflow: hidden; 
            border: 1px solid #334155; 
            overflow-x: auto; 
        }
        table { width: 100%; border-collapse: collapse; min-width: 1000px; }
        th { 
            background: rgba(15, 23, 42, 0.9); color: #06b6d4; 
            text-align: left; padding: 1rem; font-size: 0.8rem; 
            text-transform: uppercase; border-bottom: 1px solid #334155; 
            white-space: nowrap; 
        }
        td { padding: 1rem; border-bottom: 1px solid rgba(255,255,255,0.05); font-size: 0.9rem; color: #e2e8f0; }
        tr:last-child td { border-bottom: none; }
        tr:hover { background: rgba(255,255,255,0.02); }

        /* Badges */
        .badge { padding: 4px 10px; border-radius: 12px; font-size: 0.75rem; font-weight: 600; display: inline-block;}
        .b-active { background: rgba(34, 197, 94, 0.15); color: #4ade80; }
        .b-inactive { background: rgba(239, 68, 68, 0.15); color: #f87171; }

        /* --- RESPONSIVE --- */
        @media (max-width: 768px) {
            .container { padding: 1rem; }
            .title { font-size: 1.8rem; }
            .stats-grid { grid-template-columns: 1fr; gap: 1rem; }
            .stat-card { padding: 1rem; display: flex; justify-content: space-between; align-items: center; text-align: left; }
            .stat-val { font-size: 1.5rem; margin: 0; order: 2; }
            .stat-lbl { order: 1; }
            .filter-grid { grid-template-columns: 1fr; }
            .btn { flex: 1; justify-content: center; }
            .action-bar { flex-direction: column; }
            .action-bar .btn { width: 100%; }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <h1 class="title">Housing & Facilities Report</h1>
        <p class="subtitle">Asset inventory, valuation, and location tracking.</p>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-lbl">Active Assets</div>
            <div class="stat-val text-cyan"><?= number_format($active_assets) ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-lbl">Total Asset Value</div>
            <div class="stat-val text-green">₱<?= number_format($total_asset_value, 2) ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-lbl">Total Quantity</div>
            <div class="stat-val"><?= number_format($total_items_count) ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-lbl">Unique Item Types</div>
            <div class="stat-val"><?= number_format($unique_items) ?></div>
        </div>
    </div>

    <div class="filter-box">
        <form method="GET">
            <div class="filter-grid">
                <div class="form-group">
                    <label>Date Added Range</label>
                    <div style="display: flex; gap: 5px;">
                        <input type="date" name="date_from" class="form-input" value="<?= htmlspecialchars($date_from) ?>">
                        <input type="date" name="date_to" class="form-input" value="<?= htmlspecialchars($date_to) ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Location</label>
                    <select name="location" class="form-input">
                        <option value="">All Locations</option>
                        <?php foreach($locations as $loc): ?>
                            <option value="<?= $loc['LOCATION_ID'] ?>" <?= $location_id == $loc['LOCATION_ID']?'selected':'' ?>>
                                <?= htmlspecialchars($loc['LOCATION_NAME']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Status</label>
                    <select name="status" class="form-input">
                        <option value="">All Statuses</option>
                        <option value="1" <?= $status === '1' ? 'selected' : '' ?>>In Use / Active</option>
                        <option value="0" <?= $status === '0' ? 'selected' : '' ?>>Inactive / Disposed</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Search Item</label>
                    <input type="text" name="search" class="form-input" placeholder="e.g. Kennel, Pen" value="<?= htmlspecialchars($search_term) ?>">
                </div>
                
                <div class="btn-group">
                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                    <a href="housing_report.php" class="btn btn-outline">Reset</a>
                </div>
            </div>
            
            <div class="action-bar">
                <button type="button" class="btn btn-export" onclick="exportPDF()">
                    <span>📄</span> PDF
                </button>
                <button type="button" class="btn btn-excel" onclick="exportExcel()">
                    <span>📊</span> Excel
                </button>
                <button type="button" class="btn btn-csv" onclick="exportCSV()">
                    <span>📝</span> CSV
                </button>
            </div>
        </form>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Item Name</th>
                    <th>Description</th>
                    <th>Location</th>
                    <th>Date Added</th>
                    <th style="text-align:right;">Quantity</th>
                    <th style="text-align:right;">Unit Cost</th>
                    <th style="text-align:right;">Total Value</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($items)): ?>
                    <tr><td colspan="8" style="text-align:center; padding:3rem; color:#64748b;">No housing/facility records found.</td></tr>
                <?php else: ?>
                    <?php foreach($items as $i): 
                        $badgeClass = ($i['STATUS'] == 1) ? 'b-active' : 'b-inactive';
                    ?>
                    <tr>
                        <td style="font-weight:bold; color:#fff;">
                            <?= htmlspecialchars($i['ITEM_NAME']) ?>
                        </td>
                        <td style="color:#94a3b8; font-size:0.85rem; max-width: 250px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                            <?= htmlspecialchars($i['ITEM_DESCRIPTION'] ?: '-') ?>
                        </td>
                        <td><?= htmlspecialchars($i['LOCATION_NAME'] ?? 'Unassigned') ?></td>
                        <td><?= $i['DATE_ADDED'] ?></td>
                        <td style="text-align:right; font-weight:bold; color:#06b6d4;">
                            <?= number_format($i['QUANTITY']) ?>
                        </td>
                        <td style="text-align:right;">₱<?= number_format($i['UNIT_COST'], 2) ?></td>
                        <td style="text-align:right; color:#4ade80;">₱<?= number_format($i['CALCULATED_TOTAL'], 2) ?></td>
                        <td><span class="badge <?= $badgeClass ?>"><?= $i['STATUS_LABEL'] ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    const jsPDF = window.jspdf.jsPDF;
    // Pass PHP data to JS
    const records = <?php echo json_encode($items); ?>;
    const stats = {
        totalValue: "<?= number_format($total_asset_value, 2) ?>",
        totalItems: "<?= number_format($total_items_count) ?>"
    };
    
    // --- PDF Export ---
    function exportPDF() {
        const doc = new jsPDF('landscape');
        
        doc.setFontSize(18);
        doc.setTextColor(6, 182, 212); // Cyan
        doc.text("Housing & Facilities Report", 14, 15);
        
        doc.setFontSize(10);
        doc.setTextColor(100);
        const dateStr = new Date().toLocaleString();
        doc.text(`Generated: ${dateStr}`, 14, 22);
        doc.text(`Total Asset Value: PHP ${stats.totalValue}`, 200, 22);

        const rows = records.map(r => [
            r.ITEM_NAME,
            r.ITEM_DESCRIPTION || '-',
            r.LOCATION_NAME || 'Unassigned',
            r.DATE_ADDED,
            r.QUANTITY,
            parseFloat(r.UNIT_COST).toFixed(2),
            parseFloat(r.CALCULATED_TOTAL).toFixed(2),
            r.STATUS_LABEL
        ]);

        doc.autoTable({
            head: [['Item Name', 'Description', 'Location', 'Date Added', 'Qty', 'Unit Cost', 'Total Value', 'Status']],
            body: rows,
            startY: 30,
            styles: { fontSize: 8 },
            headStyles: { fillColor: [8, 145, 178] } // Teal Header
        });

        doc.save('Housing_Report.pdf');
    }

    // --- Excel Export ---
    function exportExcel() {
        const excelData = records.map(r => ({
            'Item Name': r.ITEM_NAME,
            'Description': r.ITEM_DESCRIPTION,
            'Location': r.LOCATION_NAME || 'Unassigned',
            'Date Added': r.DATE_ADDED,
            'Purchase Date': r.DATE_OF_PURCHASE,
            'Quantity': parseInt(r.QUANTITY),
            'Unit Cost': parseFloat(r.UNIT_COST),
            'Total Value': parseFloat(r.CALCULATED_TOTAL),
            'Status': r.STATUS_LABEL
        }));

        const ws = XLSX.utils.json_to_sheet(excelData);
        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, "Housing Assets");
        XLSX.writeFile(wb, "Housing_Report_" + new Date().toISOString().slice(0,10) + ".xlsx");
    }

    // --- CSV Export ---
    function exportCSV() {
        let csvContent = "data:text/csv;charset=utf-8,";
        csvContent += "Item Name,Description,Location,Date Added,Qty,Unit Cost,Total Value,Status\n";
        
        records.forEach(r => {
            const row = [
                r.ITEM_NAME, r.ITEM_DESCRIPTION, r.LOCATION_NAME, r.DATE_ADDED,
                r.QUANTITY, r.UNIT_COST, r.CALCULATED_TOTAL, r.STATUS_LABEL
            ].map(e => `"${e || ''}"`).join(","); 
            csvContent += row + "\n";
        });

        const encodedUri = encodeURI(csvContent);
        const link = document.createElement("a");
        link.setAttribute("href", encodedUri);
        link.setAttribute("download", "Housing_Report_" + new Date().toISOString().slice(0,10) + ".csv");
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
</script>

</body>
</html>