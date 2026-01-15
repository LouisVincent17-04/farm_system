<?php
// reports/medicine_report.php
error_reporting(0);
ini_set('display_errors', 0);
$page = "reports";

include '../common/navbar.php';
include '../config/Connection.php';
include '../security/checkRole.php';
checkRole(2); // Farm Admin

// --- 1. GET FILTER INPUTS ---
$date_from    = $_GET['date_from'] ?? '';
$date_to      = $_GET['date_to'] ?? '';
$stock_status = $_GET['stock_status'] ?? ''; // 'low', 'out', 'good'
$search_term  = $_GET['search'] ?? '';

try {
    if (!isset($conn)) { throw new Exception("Database connection failed."); }

    // --- 2. BUILD SQL QUERY ---
    // Based on your screenshot: MEDICINES table
    // We assume there is a UNITS table. If not, remove the LEFT JOIN and u.UNIT_NAME
    $sql = "SELECT 
            s.SUPPLY_ID,
            s.SUPPLY_NAME,
            s.TOTAL_STOCK,
            s.TOTAL_COST,
            s.UNIT_ID,
            u.UNIT_NAME, 
            DATE_FORMAT(s.DATE_UPDATED, '%Y-%m-%d %H:%i') as LAST_UPDATED,
            DATE_FORMAT(s.DATE_CREATED, '%Y-%m-%d') as DATE_CREATED
        FROM MEDICINES s
        LEFT JOIN UNITS u ON s.UNIT_ID = u.UNIT_ID
        WHERE 1=1";

    // Optional: If this table is shared, you might need: AND s.SUPPLY_TYPE = 'Medicine'

    $params = [];

    // Filter: Date Range (Updated Date)
    if ($date_from && $date_to) {
        $sql .= " AND s.DATE_UPDATED BETWEEN :date_from AND :date_to";
        $params[':date_from'] = $date_from . ' 00:00:00';
        $params[':date_to']   = $date_to . ' 23:59:59';
    }

    // Filter: Search Name
    if ($search_term) {
        $sql .= " AND s.SUPPLY_NAME LIKE :search";
        $params[':search'] = "%$search_term%";
    }

    $sql .= " ORDER BY s.TOTAL_STOCK ASC"; // Show lowest stock first

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $raw_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // --- 3. PROCESS DATA & LOGIC (PHP Side) ---
    $medicines = [];
    
    // Stats Counters
    $total_items = 0;
    $total_value = 0;
    $low_stock_count = 0;
    $out_stock_count = 0;

    foreach ($raw_data as $row) {
        // 1. Calculate Status
        $stock = (float)$row['TOTAL_STOCK'];
        $status_label = 'Good';
        
        if ($stock == 0) {
            $status_label = 'Out of Stock';
            $out_stock_count++;
        } elseif ($stock <= 50) { // Example Threshold: 50
            $status_label = 'Low Stock';
            $low_stock_count++;
        }

        // 2. Filter by Status (if selected)
        if ($stock_status) {
            if ($stock_status === 'out' && $stock > 0) continue;
            if ($stock_status === 'low' && ($stock > 50 || $stock == 0)) continue;
            if ($stock_status === 'good' && $stock <= 50) continue;
        }

        // 3. Calculate derived unit cost
        $row['CALC_UNIT_COST'] = ($stock > 0) ? ($row['TOTAL_COST'] / $stock) : 0;
        $row['STATUS_LABEL'] = $status_label;

        // 4. Aggregates
        $total_items++;
        $total_value += $row['TOTAL_COST'];

        $medicines[] = $row;
    }

} catch (Exception $e) {
    $medicines = [];
    error_log($e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Medicine Inventory Report</title>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.31/jspdf.plugin.autotable.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

    <style>
        /* --- GLOBAL STYLES --- */
        body { 
            font-family: system-ui, -apple-system, sans-serif; 
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); 
            color: #e2e8f0; margin: 0; padding-bottom: 40px;
        }
        .container { max-width: 1600px; margin: 0 auto; padding: 2rem; }
        
        .header { text-align: center; margin-bottom: 2rem; }
        .title { 
            font-size: 2.2rem; font-weight: 800; 
            background: linear-gradient(135deg, #f472b6, #db2777); 
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
            border-radius: 16px; padding: 1.5rem; 
            text-align: center; backdrop-filter: blur(10px); 
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        .stat-val { font-size: 2rem; font-weight: 800; margin-bottom: 0.25rem; color: #fff; }
        .stat-lbl { color: #94a3b8; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1px; font-weight: 600; }
        
        .text-pink { color: #f472b6; } 
        .text-red { color: #ef4444; }
        .text-gold { color: #fbbf24; }

        /* --- FILTER BAR --- */
        .filter-box { 
            background: rgba(15, 23, 42, 0.6); 
            border: 1px solid #334155; 
            padding: 1.5rem; border-radius: 16px; margin-bottom: 2rem; 
        }
        .filter-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); 
            gap: 1rem; align-items: end; 
        }
        .form-group label { display: block; font-size: 0.75rem; color: #94a3b8; margin-bottom: 0.4rem; font-weight: 600; text-transform: uppercase; }
        .form-input { 
            width: 100%; padding: 10px; background: #0f172a; 
            border: 1px solid #334155; color: white; border-radius: 8px; 
            font-size: 0.9rem; box-sizing: border-box; 
        }
        .form-input:focus { border-color: #f472b6; outline: none; }

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
        .btn-primary { background: #db2777; color: white; }
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
            background: rgba(15, 23, 42, 0.9); color: #f472b6; 
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

        /* Responsive */
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
        <h1 class="title">Medicine Inventory Report</h1>
        <p class="subtitle">Track stock levels, valuation, and supply status.</p>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-lbl">Total Medicines</div>
            <div class="stat-val"><?= number_format($total_items) ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-lbl">Total Valuation</div>
            <div class="stat-val text-gold">₱<?= number_format($total_value, 2) ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-lbl">Low Stock Items</div>
            <div class="stat-val text-pink"><?= number_format($low_stock_count) ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-lbl">Out of Stock</div>
            <div class="stat-val text-red"><?= number_format($out_stock_count) ?></div>
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
                    <label>Search Name</label>
                    <input type="text" name="search" class="form-input" placeholder="e.g., Vitamin A" value="<?= htmlspecialchars($search_term) ?>">
                </div>

                <div class="form-group">
                    <label>Stock Status</label>
                    <select name="stock_status" class="form-input">
                        <option value="">All Statuses</option>
                        <option value="good" <?= $stock_status === 'good' ? 'selected' : '' ?>>Good Stock</option>
                        <option value="low" <?= $stock_status === 'low' ? 'selected' : '' ?>>Low Stock (≤50)</option>
                        <option value="out" <?= $stock_status === 'out' ? 'selected' : '' ?>>Out of Stock</option>
                    </select>
                </div>
                
                <div class="btn-group">
                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                    <a href="medicine_report.php" class="btn btn-outline">Reset</a>
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
                    <th>ID</th>
                    <th>Supply Name</th>
                    <th style="text-align:right;">Stock</th>
                    <th style="text-align:right;">Total Value</th>
                    <th style="text-align:right;">Avg Unit Cost</th>
                    <th>Last Updated</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($medicines)): ?>
                    <tr><td colspan="7" style="text-align:center; padding:3rem; color:#64748b;">No medicine records found.</td></tr>
                <?php else: ?>
                    <?php foreach($medicines as $m): 
                        $badgeClass = 'b-good';
                        if ($m['STATUS_LABEL'] == 'Low Stock') $badgeClass = 'b-low';
                        else if ($m['STATUS_LABEL'] == 'Out of Stock') $badgeClass = 'b-out';
                    ?>
                    <tr>
                        <td style="font-family:monospace; color:#64748b;"><?= htmlspecialchars($m['SUPPLY_ID']) ?></td>
                        <td style="font-weight:bold; color:#fff;"><?= htmlspecialchars($m['SUPPLY_NAME']) ?></td>
                        <td style="text-align:right; font-weight:bold;">
                            <?= number_format($m['TOTAL_STOCK']) ?> <small style="color:#64748b; font-weight:normal;"><?= htmlspecialchars($m['UNIT_NAME'] ?? '') ?></small>
                        </td>
                        <td style="text-align:right; color:#fbbf24;">₱<?= number_format($m['TOTAL_COST'], 2) ?></td>
                        <td style="text-align:right; color:#94a3b8;">₱<?= number_format($m['CALC_UNIT_COST'], 2) ?></td>
                        <td><?= $m['LAST_UPDATED'] ?></td>
                        <td><span class="badge <?= $badgeClass ?>"><?= $m['STATUS_LABEL'] ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    const jsPDF = window.jspdf.jsPDF;
    const records = <?php echo json_encode($medicines); ?>;
    const stats = {
        total: "<?= number_format($total_items) ?>",
        value: "<?= number_format($total_value, 2) ?>"
    };
    
    // --- PDF Export ---
    function exportPDF() {
        const doc = new jsPDF('landscape');
        
        doc.setFontSize(18);
        doc.setTextColor(219, 39, 119); // Pink
        doc.text("Medicine Inventory Report", 14, 15);
        
        doc.setFontSize(10);
        doc.setTextColor(100);
        const dateStr = new Date().toLocaleString();
        doc.text(`Generated: ${dateStr}`, 14, 22);
        doc.text(`Total Valuation: PHP ${stats.value}`, 200, 22);

        const rows = records.map(r => [
            r.SUPPLY_ID,
            r.SUPPLY_NAME,
            r.TOTAL_STOCK + ' ' + (r.UNIT_NAME || ''),
            parseFloat(r.TOTAL_COST).toFixed(2),
            parseFloat(r.CALC_UNIT_COST).toFixed(2),
            r.LAST_UPDATED,
            r.STATUS_LABEL
        ]);

        doc.autoTable({
            head: [['ID', 'Name', 'Stock', 'Total Value', 'Unit Cost', 'Updated', 'Status']],
            body: rows,
            startY: 30,
            styles: { fontSize: 9 },
            headStyles: { fillColor: [219, 39, 119] } // Pink Header
        });

        doc.save('Medicine_Report.pdf');
    }

    // --- Excel Export ---
    function exportExcel() {
        const excelData = records.map(r => ({
            'ID': r.SUPPLY_ID,
            'Name': r.SUPPLY_NAME,
            'Stock': r.TOTAL_STOCK,
            'Unit': r.UNIT_NAME || '',
            'Total Value': parseFloat(r.TOTAL_COST),
            'Avg Unit Cost': parseFloat(r.CALC_UNIT_COST),
            'Last Updated': r.LAST_UPDATED,
            'Status': r.STATUS_LABEL
        }));

        const ws = XLSX.utils.json_to_sheet(excelData);
        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, "Medicines");
        XLSX.writeFile(wb, "Medicine_Report_" + new Date().toISOString().slice(0,10) + ".xlsx");
    }

    // --- CSV Export ---
    function exportCSV() {
        let csvContent = "data:text/csv;charset=utf-8,";
        csvContent += "ID,Name,Stock,Unit,Total Value,Avg Unit Cost,Updated,Status\n";
        
        records.forEach(r => {
            const row = [
                r.SUPPLY_ID, r.SUPPLY_NAME, r.TOTAL_STOCK, r.UNIT_NAME || '', 
                r.TOTAL_COST, r.CALC_UNIT_COST, r.LAST_UPDATED, r.STATUS_LABEL
            ].map(e => `"${e || ''}"`).join(","); 
            csvContent += row + "\n";
        });

        const encodedUri = encodeURI(csvContent);
        const link = document.createElement("a");
        link.setAttribute("href", encodedUri);
        link.setAttribute("download", "Medicine_Report_" + new Date().toISOString().slice(0,10) + ".csv");
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
</script>

</body>
</html>