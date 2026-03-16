<?php
// reports/vitamins_report.php
error_reporting(0);
ini_set('display_errors', 0);
include '../config/Connection.php';
$page = "reports";
include '../security/checkAccess.php';
checkAccess('vitamins_supplements_report');
include '../common/navbar.php';
include '../common/chat_support.php';


// --- 1. GET FILTER INPUTS ---
$date_from    = $_GET['date_from'] ?? '';
$date_to      = $_GET['date_to'] ?? '';
$stock_status = $_GET['stock_status'] ?? ''; // 'low', 'out', 'good'
$search_term  = trim($_GET['search'] ?? '');

try {
    if (!isset($conn)) { throw new Exception("Database connection failed."); }

    // --- 2. BUILD SQL QUERY ---
    // Fetch items from vitamins_supplements table
    $sql = "SELECT 
            v.SUPPLY_ID,
            v.SUPPLY_NAME,
            v.TOTAL_STOCK,
            v.TOTAL_COST,
            DATE_FORMAT(v.EXPIRATION_DATE, '%m/%d/%Y') as EXPIRATION_DATE_FMT,
            v.DATE_CREATED,
            DATE_FORMAT(v.DATE_UPDATED, '%m/%d/%Y %h:%i %p') as DATE_UPDATED_FMT,
            v.UNIT_ID,
            u.UNIT_NAME
        FROM vitamins_supplements v
        LEFT JOIN units u ON v.UNIT_ID = u.UNIT_ID
        WHERE 1=1";

    $params = [];

    // Filter: Date Updated Range
    if ($date_from && $date_to) {
        $sql .= " AND v.DATE_UPDATED BETWEEN :date_from AND :date_to";
        $params[':date_from'] = $date_from . ' 00:00:00';
        $params[':date_to']   = $date_to . ' 23:59:59';
    }

    // Filter: Search (CASE INSENSITIVE)
    if ($search_term) {
        $search_pattern = "%" . strtolower($search_term) . "%";
        $sql .= " AND LOWER(v.SUPPLY_NAME) LIKE :search";
        $params[':search'] = $search_pattern;
    }

    $sql .= " ORDER BY v.SUPPLY_NAME ASC"; 

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $raw_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // --- 3. PROCESS DATA & STATS ---
    $vitamins = [];
    
    // Statistics
    $total_items = 0;
    $total_value = 0;
    $total_stock = 0;
    $low_stock_count = 0;

    foreach ($raw_data as $row) {
        // Status Logic (Assuming < 20 is Low Stock)
        $is_low = ($row['TOTAL_STOCK'] <= 20 && $row['TOTAL_STOCK'] > 0); 
        $is_out = ($row['TOTAL_STOCK'] <= 0);

        if ($is_out) {
            $status_label = 'Out of Stock';
            $low_stock_count++;
        } elseif ($is_low) {
            $status_label = 'Low Stock';
            $low_stock_count++;
        } else {
            $status_label = 'Good';
        }

        // Apply Stock Status Filter
        if ($stock_status) {
            if ($stock_status === 'low' && !$is_low) continue;
            if ($stock_status === 'out' && !$is_out) continue;
            if ($stock_status === 'good' && ($is_low || $is_out)) continue;
        }

        // Calculate Cost Per Unit
        $avg_cost = 0;
        if ($row['TOTAL_STOCK'] > 0) {
            $avg_cost = $row['TOTAL_COST'] / $row['TOTAL_STOCK'];
        }

        $row['STATUS_LABEL'] = $status_label;
        $row['AVG_COST'] = $avg_cost;
        
        // Aggregates
        $total_items++;
        $total_value += $row['TOTAL_COST'];
        $total_stock += $row['TOTAL_STOCK'];

        $vitamins[] = $row;
    }

} catch (Exception $e) {
    $vitamins = [];
    error_log($e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Vitamin Inventory Report</title>
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" type="text/css" href="https://npmcdn.com/flatpickr/dist/themes/dark.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.31/jspdf.plugin.autotable.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />

    <style>
        /* --- GLOBAL STYLES --- */
        body { 
            font-family: system-ui, -apple-system, sans-serif; 
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); 
            color: #e2e8f0; margin: 0; padding-bottom: 40px;
        }
        .container { max-width: 1600px; margin: 0 auto; padding: 2rem; }
        
        .back-link {
            display: inline-flex; align-items: center; gap: 8px; 
            text-decoration: none; color: #94a3b8; font-weight: 600; 
            font-size: 0.95rem; margin-bottom: 20px; transition: color 0.2s;
        }
        .back-link:hover { color: white; }

        .header { text-align: center; margin-bottom: 2rem; }
        .title { 
            font-size: 2.2rem; font-weight: 800; 
            background: linear-gradient(135deg, #a3e635, #65a30d); /* Lime Green */
            -webkit-background-clip: text; -webkit-text-fill-color: transparent; 
            margin-bottom: 0.5rem;
        }
        .subtitle { color: #94a3b8; font-size: 1rem; margin: 0; }

        /* --- STATS CARDS --- */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
        .stat-card { 
            background: rgba(30, 41, 59, 0.6); border: 1px solid rgba(255,255,255,0.1); 
            border-radius: 16px; padding: 1.5rem; text-align: center; backdrop-filter: blur(10px); 
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        .stat-val { font-size: 2rem; font-weight: 800; margin-bottom: 0.25rem; color: #fff; }
        .stat-lbl { color: #94a3b8; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1px; font-weight: 600; }
        
        .text-lime { color: #bef264; } 
        .text-red { color: #ef4444; } 
        .text-green { color: #4ade80; }

        /* --- FILTER BAR --- */
        .filter-box { background: rgba(15, 23, 42, 0.6); border: 1px solid #334155; padding: 1.5rem; border-radius: 16px; margin-bottom: 2rem; }
        .filter-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; align-items: end; }
        .form-group label { display: block; font-size: 0.75rem; color: #94a3b8; margin-bottom: 0.4rem; font-weight: 600; text-transform: uppercase; }
        .form-input { width: 100%; padding: 10px; background: #0f172a; border: 1px solid #334155; color: white; border-radius: 8px; font-size: 0.9rem; box-sizing: border-box; outline: none;}
        .form-input:focus { border-color: #a3e635; }

        /* Buttons */
        .btn-group { display: flex; gap: 10px; flex-wrap: wrap; }
        .action-bar { margin-top: 1.5rem; display: flex; gap: 10px; justify-content: flex-end; flex-wrap: wrap; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 1rem; }
        .btn { padding: 10px 20px; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; text-decoration: none; font-size: 0.9rem; transition: transform 0.1s; white-space: nowrap; }
        .btn:active { transform: scale(0.98); }
        .btn-primary { background: #65a30d; color: white; }
        .btn-outline { background: transparent; border: 1px solid #475569; color: #cbd5e1; }
        
        /* Export Buttons */
        .btn-pdf { background: #3b82f6; color: white; }
        .btn-excel { background: #10b981; color: white; }
        .btn-csv { background: #f59e0b; color: white; }

        /* Ledger Button */
        .btn-view-ledger {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 8px 16px; background: rgba(163, 230, 53, 0.15); 
            border: 1px solid rgba(163, 230, 53, 0.4); color: #bef264;
            border-radius: 8px; font-size: 0.85rem; font-weight: 600;
            text-decoration: none; transition: all 0.2s; white-space: nowrap;
        }
        .btn-view-ledger:hover { background: rgba(163, 230, 53, 0.3); color: #fff; transform: translateY(-1px); border-color: #a3e635; }

        /* --- TABLE --- */
        .table-wrap { background: rgba(30, 41, 59, 0.5); border-radius: 16px; overflow: hidden; border: 1px solid #334155; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 1000px; }
        th { background: rgba(15, 23, 42, 0.9); color: #a3e635; text-align: left; padding: 1rem; font-size: 0.8rem; text-transform: uppercase; border-bottom: 1px solid #334155; white-space: nowrap; }
        td { padding: 1rem; border-bottom: 1px solid rgba(255,255,255,0.05); font-size: 0.9rem; color: #e2e8f0; vertical-align: middle; }
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
            .date-flex-mobile { flex-direction: column; gap: 10px; }
            .btn { flex: 1; justify-content: center; }
            .action-bar { flex-direction: column; }
            .action-bar .btn { width: 100%; }
        }
    </style>
</head>
<body>

<div class="container">
    
    <a href="reports.php" class="back-link">
        <i class="fa-solid fa-arrow-left"></i> Back to Reports Dashboard
    </a>

    <div class="header">
        <h1 class="title">Vitamins & Supplements Report</h1>
        <p class="subtitle">Monitor supplement supplies, stock levels, and individual history ledgers.</p>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-lbl">Unique Supplements</div>
            <div class="stat-val"><?= number_format($total_items) ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-lbl">Total Stock</div>
            <div class="stat-val text-lime"><?= number_format($total_stock) ?></div>
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
                    <div class="date-flex-mobile" style="display: flex; gap: 5px;">
                        <input type="text" name="date_from" class="form-input date-picker" value="<?= htmlspecialchars($date_from) ?>" placeholder="Start Date">
                        <input type="text" name="date_to" class="form-input date-picker" value="<?= htmlspecialchars($date_to) ?>" placeholder="End Date">
                    </div>
                </div>

                <div class="form-group">
                    <label>Stock Status</label>
                    <select name="stock_status" class="form-input">
                        <option value="">All Statuses</option>
                        <option value="good" <?= $stock_status === 'good' ? 'selected' : '' ?>>Good Stock</option>
                        <option value="low" <?= $stock_status === 'low' ? 'selected' : '' ?>>Low Stock (≤20)</option>
                        <option value="out" <?= $stock_status === 'out' ? 'selected' : '' ?>>Out of Stock</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Search Supplement</label>
                    <input type="text" name="search" class="form-input" placeholder="e.g. Iron, Vitamin B" value="<?= htmlspecialchars($search_term) ?>">
                </div>
                
                <div class="btn-group">
                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                    <a href="vitamins_report.php" class="btn btn-outline">Reset</a>
                </div>
            </div>
            
            <div class="action-bar">
                <button type="button" class="btn btn-pdf" onclick="exportPDF()">
                    <i class="fa-solid fa-file-pdf"></i> PDF
                </button>
                <button type="button" class="btn btn-excel" onclick="exportExcel()">
                    <i class="fa-solid fa-file-excel"></i> Excel
                </button>
                <button type="button" class="btn btn-csv" onclick="exportCSV()">
                    <i class="fa-solid fa-file-csv"></i> CSV
                </button>
            </div>
        </form>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Supplement Name</th>
                    <th>Expiration</th>
                    <th style="text-align:right;">Stock</th>
                    <th style="text-align:right;">Total Cost</th>
                    <th style="text-align:right;">Avg Cost/Unit</th>
                    <th>Last Updated</th>
                    <th>Status</th>
                    <th style="text-align:center;">History</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($vitamins)): ?>
                    <tr><td colspan="8" style="text-align:center; padding:3rem; color:#64748b;">No supplement records found.</td></tr>
                <?php else: ?>
                    <?php foreach($vitamins as $v): 
                        $badgeClass = 'b-good';
                        if ($v['STATUS_LABEL'] == 'Low Stock') $badgeClass = 'b-low';
                        else if ($v['STATUS_LABEL'] == 'Out of Stock') $badgeClass = 'b-out';
                    ?>
                    <tr>
                        <td style="font-weight:bold; color:#fff;">
                            <?= htmlspecialchars($v['SUPPLY_NAME']) ?>
                        </td>
                        <td style="color:#94a3b8;"><?= $v['EXPIRATION_DATE_FMT'] ?: '-' ?></td>
                        <td style="text-align:right; font-weight:bold;">
                            <?= number_format($v['TOTAL_STOCK']) ?> 
                            <small style="color:#64748b; font-weight:normal;">
                                <?= htmlspecialchars($v['UNIT_NAME'] ?? 'Units') ?>
                            </small>
                        </td>
                        <td style="text-align:right; color:#a3e635;">₱<?= number_format($v['TOTAL_COST'], 2) ?></td>
                        <td style="text-align:right; color:#64748b;">₱<?= number_format($v['AVG_COST'], 2) ?></td>
                        <td style="font-size:0.85rem; color:#94a3b8;"><?= $v['DATE_UPDATED_FMT'] ?></td>
                        <td><span class="badge <?= $badgeClass ?>"><?= $v['STATUS_LABEL'] ?></span></td>
                        <td style="text-align:center;">
                            <a href="viewVitaminsLedger.php?id=<?= $v['SUPPLY_ID'] ?>" class="btn-view-ledger">
                                <i class="fa-solid fa-clock-rotate-left"></i> Ledger
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    // Initialize Flatpickr for Date Inputs
    document.addEventListener('DOMContentLoaded', () => {
        flatpickr(".date-picker", {
            dateFormat: "Y-m-d", // Value submitted to PHP
            altInput: true,      // Visual input
            altFormat: "m/d/Y",  // mm/dd/yyyy format
            allowInput: true
        });
    });

    const jsPDF = window.jspdf.jsPDF;
    const records = <?php echo json_encode($vitamins); ?>;
    const stats = {
        totalValue: "<?= number_format($total_value, 2) ?>",
        totalStock: "<?= number_format($total_stock) ?>"
    };
    
    // --- PDF Export ---
    function exportPDF() {
        const doc = new jsPDF('landscape');
        doc.setFontSize(18); doc.setTextColor(163, 230, 53); // Lime
        doc.text("Vitamin Inventory Report", 14, 15);
        
        let now = new Date();
        let formattedNow = `${String(now.getMonth() + 1).padStart(2, '0')}/${String(now.getDate()).padStart(2, '0')}/${now.getFullYear()} ${now.toLocaleTimeString()}`;

        doc.setFontSize(10); doc.setTextColor(100);
        doc.text(`Generated: ${formattedNow}`, 14, 22);
        doc.text(`Total Value: PHP ${stats.totalValue} | Total Stock: ${stats.totalStock}`, 14, 28);

        const rows = records.map(r => [
            r.SUPPLY_NAME, r.EXPIRATION_DATE_FMT || '-',
            r.TOTAL_STOCK + ' ' + (r.UNIT_NAME || 'Units'),
            parseFloat(r.TOTAL_COST).toFixed(2),
            parseFloat(r.AVG_COST).toFixed(2),
            r.DATE_UPDATED_FMT, r.STATUS_LABEL
        ]);

        doc.autoTable({
            head: [['Supplement Name', 'Expiry', 'Stock', 'Total Cost', 'Avg Cost', 'Updated', 'Status']],
            body: rows, startY: 35, styles: { fontSize: 8 }, headStyles: { fillColor: [101, 163, 13] }
        });
        doc.save('Vitamin_Report.pdf');
    }

    // --- Excel Export ---
    function exportExcel() {
        const excelData = records.map(r => ({
            'Supplement Name': r.SUPPLY_NAME,
            'Expiration Date': r.EXPIRATION_DATE_FMT || '-',
            'Stock': parseInt(r.TOTAL_STOCK),
            'Unit': r.UNIT_NAME || 'Units',
            'Total Cost': parseFloat(r.TOTAL_COST),
            'Avg Cost': parseFloat(r.AVG_COST),
            'Last Updated': r.DATE_UPDATED_FMT,
            'Status': r.STATUS_LABEL
        }));
        const ws = XLSX.utils.json_to_sheet(excelData);
        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, "Vitamins");
        XLSX.writeFile(wb, "Vitamin_Report_" + new Date().toISOString().slice(0,10) + ".xlsx");
    }

    // --- CSV Export ---
    function exportCSV() {
        let csvContent = "data:text/csv;charset=utf-8,";
        csvContent += "Supplement Name,Expiration Date,Stock,Unit,Total Cost,Avg Cost,Updated,Status\n";
        records.forEach(r => {
            const row = [
                r.SUPPLY_NAME, r.EXPIRATION_DATE_FMT || '-', r.TOTAL_STOCK, r.UNIT_NAME || 'Units', 
                r.TOTAL_COST, r.AVG_COST, r.DATE_UPDATED_FMT, r.STATUS_LABEL
            ].map(e => `"${e || ''}"`).join(","); 
            csvContent += row + "\n";
        });
        const link = document.createElement("a");
        link.setAttribute("href", encodeURI(csvContent));
        link.setAttribute("download", "Vitamin_Report_" + new Date().toISOString().slice(0,10) + ".csv");
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
</script>

</body>
</html>