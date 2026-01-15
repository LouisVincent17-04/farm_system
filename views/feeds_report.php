<?php
// reports/feed_report.php
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
$stock_status = $_GET['stock_status'] ?? ''; // 'low', 'out', 'good'
$search_term  = $_GET['search'] ?? '';

try {
    if (!isset($conn)) { throw new Exception("Database connection failed."); }

    // --- 2. BUILD SQL QUERY ---
    // Joining with LOCATIONS table to get location names
    $sql = "SELECT 
            f.FEED_ID,
            f.FEED_NAME,
            f.TOTAL_WEIGHT_KG,
            f.TOTAL_COST,
            f.DATE_CREATED,
            DATE_FORMAT(f.DATE_UPDATED, '%Y-%m-%d %H:%i') as DATE_UPDATED,
            f.LOCATION_ID,
            l.LOCATION_NAME
        FROM feeds f
        LEFT JOIN locations l ON f.LOCATION_ID = l.LOCATION_ID
        WHERE 1=1";

    $params = [];

    // Filter: Location
    if ($location_id) {
        $sql .= " AND f.LOCATION_ID = :loc_id";
        $params[':loc_id'] = $location_id;
    }

    // Filter: Date Updated Range
    if ($date_from && $date_to) {
        $sql .= " AND f.DATE_UPDATED BETWEEN :date_from AND :date_to";
        $params[':date_from'] = $date_from . ' 00:00:00';
        $params[':date_to']   = $date_to . ' 23:59:59';
    }

    // Filter: Search Name
    if ($search_term) {
        $sql .= " AND f.FEED_NAME LIKE :search";
        $params[':search'] = "%$search_term%";
    }

    $sql .= " ORDER BY f.FEED_NAME ASC"; 

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $raw_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // --- 3. PROCESS DATA & STATS ---
    $feeds = [];
    
    // Statistics
    $total_items = 0;
    $total_value = 0;
    $total_weight = 0;
    $low_stock_count = 0;

    foreach ($raw_data as $row) {
        // Status Logic
        // Threshold: Assuming < 50KG is "Low Stock" for feeds (Adjust as needed)
        $is_low = ($row['TOTAL_WEIGHT_KG'] <= 50 && $row['TOTAL_WEIGHT_KG'] > 0); 
        $is_out = ($row['TOTAL_WEIGHT_KG'] <= 0);

        if ($is_out) {
            $status_label = 'Out of Stock';
            $low_stock_count++;
        } elseif ($is_low) {
            $status_label = 'Low Stock';
            $low_stock_count++;
        } else {
            $status_label = 'Good';
        }

        // Apply Stock Status Filter (PHP Side)
        if ($stock_status) {
            if ($stock_status === 'low' && !$is_low) continue;
            if ($stock_status === 'out' && !$is_out) continue;
            if ($stock_status === 'good' && ($is_low || $is_out)) continue;
        }

        // Calculate Cost Per KG (Avoid division by zero)
        $avg_cost = 0;
        if ($row['TOTAL_WEIGHT_KG'] > 0) {
            $avg_cost = $row['TOTAL_COST'] / $row['TOTAL_WEIGHT_KG'];
        }

        $row['STATUS_LABEL'] = $status_label;
        $row['AVG_COST_PER_KG'] = $avg_cost;
        
        // Aggregates
        $total_items++;
        $total_value += $row['TOTAL_COST'];
        $total_weight += $row['TOTAL_WEIGHT_KG'];

        $feeds[] = $row;
    }

    // --- 4. FETCH LOCATIONS FOR DROPDOWN ---
    $locations = $conn->query("SELECT * FROM locations ORDER BY LOCATION_NAME")->fetchAll();

} catch (Exception $e) {
    $feeds = [];
    error_log($e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Feed Inventory Report</title>
    
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
            background: linear-gradient(135deg, #f59e0b, #d97706); /* Amber for Feeds */
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
        
        .text-amber { color: #f59e0b; } 
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
        .form-input:focus { border-color: #f59e0b; outline: none; }

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
        .btn-primary { background: #d97706; color: white; }
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
        table { width: 100%; border-collapse: collapse; min-width: 900px; }
        th { 
            background: rgba(15, 23, 42, 0.9); color: #f59e0b; 
            text-align: left; padding: 1rem; font-size: 0.8rem; 
            text-transform: uppercase; border-bottom: 1px solid #334155; 
            white-space: nowrap; 
        }
        td { padding: 1rem; border-bottom: 1px solid rgba(255,255,255,0.05); font-size: 0.9rem; color: #e2e8f0; }
        tr:last-child td { border-bottom: none; }
        tr:hover { background: rgba(255,255,255,0.02); }

        /* Badges */
        .badge { padding: 4px 10px; border-radius: 12px; font-size: 0.75rem; font-weight: 600; display: inline-block;}
        .b-good { background: rgba(34, 197, 94, 0.15); color: #4ade80; }
        .b-low { background: rgba(251, 191, 36, 0.15); color: #fbbf24; }
        .b-out { background: rgba(239, 68, 68, 0.15); color: #f87171; }

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
        <h1 class="title">Feed Inventory Report</h1>
        <p class="subtitle">Monitor feed consumption, stock levels, and costs.</p>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-lbl">Feed Types</div>
            <div class="stat-val"><?= number_format($total_items) ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-lbl">Total Stock (KG)</div>
            <div class="stat-val text-amber"><?= number_format($total_weight, 2) ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-lbl">Total Valuation</div>
            <div class="stat-val text-green">₱<?= number_format($total_value, 2) ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-lbl">Low / Out of Stock</div>
            <div class="stat-val text-red"><?= number_format($low_stock_count) ?></div>
        </div>
    </div>

    <div class="filter-box">
        <form method="GET">
            <div class="filter-grid">
                <div class="form-group">
                    <label>Last Updated Range</label>
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
                    <label>Stock Status</label>
                    <select name="stock_status" class="form-input">
                        <option value="">All Statuses</option>
                        <option value="good" <?= $stock_status === 'good' ? 'selected' : '' ?>>Good Stock</option>
                        <option value="low" <?= $stock_status === 'low' ? 'selected' : '' ?>>Low Stock (≤50kg)</option>
                        <option value="out" <?= $stock_status === 'out' ? 'selected' : '' ?>>Out of Stock</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Search Feed Name</label>
                    <input type="text" name="search" class="form-input" placeholder="e.g. Starter" value="<?= htmlspecialchars($search_term) ?>">
                </div>
                
                <div class="btn-group">
                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                    <a href="feed_report.php" class="btn btn-outline">Reset</a>
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
                    <th>Feed Name</th>
                    <th>Location</th>
                    <th style="text-align:right;">Stock (KG)</th>
                    <th style="text-align:right;">Total Cost</th>
                    <th style="text-align:right;">Avg Cost/KG</th>
                    <th>Last Updated</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($feeds)): ?>
                    <tr><td colspan="7" style="text-align:center; padding:3rem; color:#64748b;">No feed records found.</td></tr>
                <?php else: ?>
                    <?php foreach($feeds as $f): 
                        $badgeClass = 'b-good';
                        if ($f['STATUS_LABEL'] == 'Low Stock') $badgeClass = 'b-low';
                        else if ($f['STATUS_LABEL'] == 'Out of Stock') $badgeClass = 'b-out';
                    ?>
                    <tr>
                        <td style="font-weight:bold; color:#fff;">
                            <?= htmlspecialchars($f['FEED_NAME']) ?>
                        </td>
                        <td><?= htmlspecialchars($f['LOCATION_NAME'] ?? '-') ?></td>
                        <td style="text-align:right; font-weight:bold;">
                            <?= number_format($f['TOTAL_WEIGHT_KG'], 2) ?>
                        </td>
                        <td style="text-align:right; color:#f59e0b;">₱<?= number_format($f['TOTAL_COST'], 2) ?></td>
                        <td style="text-align:right; color:#64748b;">₱<?= number_format($f['AVG_COST_PER_KG'], 2) ?></td>
                        <td style="font-size:0.85rem; color:#94a3b8;"><?= $f['DATE_UPDATED'] ?></td>
                        <td><span class="badge <?= $badgeClass ?>"><?= $f['STATUS_LABEL'] ?></span></td>
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
    const records = <?php echo json_encode($feeds); ?>;
    const stats = {
        totalValue: "<?= number_format($total_value, 2) ?>",
        totalWeight: "<?= number_format($total_weight, 2) ?>"
    };
    
    // --- PDF Export ---
    function exportPDF() {
        const doc = new jsPDF('landscape');
        
        doc.setFontSize(18);
        doc.setTextColor(245, 158, 11); // Amber
        doc.text("Feed Inventory Report", 14, 15);
        
        doc.setFontSize(10);
        doc.setTextColor(100);
        const dateStr = new Date().toLocaleString();
        doc.text(`Generated: ${dateStr}`, 14, 22);
        doc.text(`Total Value: PHP ${stats.totalValue} | Total Weight: ${stats.totalWeight} kg`, 14, 28);

        const rows = records.map(r => [
            r.FEED_NAME,
            r.LOCATION_NAME || '-',
            parseFloat(r.TOTAL_WEIGHT_KG).toFixed(2),
            parseFloat(r.TOTAL_COST).toFixed(2),
            parseFloat(r.AVG_COST_PER_KG).toFixed(2),
            r.DATE_UPDATED,
            r.STATUS_LABEL
        ]);

        doc.autoTable({
            head: [['Feed Name', 'Location', 'Stock (KG)', 'Total Cost', 'Avg Cost/KG', 'Updated', 'Status']],
            body: rows,
            startY: 35,
            styles: { fontSize: 8 },
            headStyles: { fillColor: [217, 119, 6] } // Amber Header
        });

        doc.save('Feed_Report.pdf');
    }

    // --- Excel Export ---
    function exportExcel() {
        const excelData = records.map(r => ({
            'Feed Name': r.FEED_NAME,
            'Location': r.LOCATION_NAME || '-',
            'Stock (KG)': parseFloat(r.TOTAL_WEIGHT_KG),
            'Total Cost': parseFloat(r.TOTAL_COST),
            'Avg Cost/KG': parseFloat(r.AVG_COST_PER_KG),
            'Last Updated': r.DATE_UPDATED,
            'Status': r.STATUS_LABEL
        }));

        const ws = XLSX.utils.json_to_sheet(excelData);
        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, "Feeds");
        XLSX.writeFile(wb, "Feed_Report_" + new Date().toISOString().slice(0,10) + ".xlsx");
    }

    // --- CSV Export ---
    function exportCSV() {
        let csvContent = "data:text/csv;charset=utf-8,";
        csvContent += "Feed Name,Location,Stock KG,Total Cost,Avg Cost/KG,Updated,Status\n";
        
        records.forEach(r => {
            const row = [
                r.FEED_NAME, r.LOCATION_NAME, r.TOTAL_WEIGHT_KG, 
                r.TOTAL_COST, r.AVG_COST_PER_KG, r.DATE_UPDATED, r.STATUS_LABEL
            ].map(e => `"${e || ''}"`).join(","); 
            csvContent += row + "\n";
        });

        const encodedUri = encodeURI(csvContent);
        const link = document.createElement("a");
        link.setAttribute("href", encodedUri);
        link.setAttribute("download", "Feed_Report_" + new Date().toISOString().slice(0,10) + ".csv");
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
</script>

</body>
</html>