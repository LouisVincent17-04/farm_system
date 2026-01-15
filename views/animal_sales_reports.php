<?php
// reports/animal_sales_reports.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
$page = "reports";

include '../common/navbar.php';
include '../config/Connection.php';
include '../security/checkRole.php';
checkRole(2); // Farm Admin

// --- 1. CONFIGURATION & INPUTS ---
$limit = 50; // Rows per page
$page_num = isset($_GET['page_num']) ? max(1, (int)$_GET['page_num']) : 1;
$offset = ($page_num - 1) * $limit;

$date_from   = isset($_GET['date_from']) && $_GET['date_from'] !== '' ? $_GET['date_from'] : null;
$date_to     = isset($_GET['date_to']) && $_GET['date_to'] !== '' ? $_GET['date_to'] : null;
$search_term = isset($_GET['search']) && trim($_GET['search']) !== '' ? trim($_GET['search']) : null;

try {
    if (!isset($conn)) { throw new Exception("Database connection failed."); }

    // --- 2. BUILD SEARCH CONDITIONS ---
    $where_conditions = [];
    $params = [];

    // Filter: Sale Date Range
    if ($date_from !== null && $date_to !== null) {
        $where_conditions[] = "s.sale_date BETWEEN :date_from AND :date_to";
        $params[':date_from'] = $date_from . ' 00:00:00';
        $params[':date_to']   = $date_to . ' 23:59:59';
    } 
    // LOGIC ADJUSTMENT: If you want to default to TODAY only when NOT searching:
    elseif ($search_term === null) {
        $where_conditions[] = "s.sale_date BETWEEN CONCAT(CURDATE(), ' 00:00:00') AND CONCAT(CURDATE(), ' 23:59:59')";
    }

    // Filter: Search (CASE INSENSITIVE)
    if ($search_term !== null) {
        $search_pattern = "%" . strtolower($search_term) . "%";
        $where_conditions[] = "(LOWER(ar.TAG_NO) LIKE :search1 OR LOWER(s.customer_name) LIKE :search2 OR LOWER(s.notes) LIKE :search3)";
        $params[':search1'] = $search_pattern;
        $params[':search2'] = $search_pattern;
        $params[':search3'] = $search_pattern;
    }

    // Build WHERE clause
    $where_sql = '';
    if (!empty($where_conditions)) {
        $where_sql = ' AND ' . implode(' AND ', $where_conditions);
    }

    // --- 3. QUERY A: GET AGGREGATE TOTALS ---
    $stats_sql = "SELECT 
                    COUNT(*) AS total_count,
                    COALESCE(SUM(s.final_sale_price), 0) AS total_revenue,
                    COALESCE(SUM(s.total_net_worth), 0) AS total_expenses,
                    COALESCE(SUM(s.gross_profit), 0) AS total_profit,
                    COALESCE(SUM(s.weight_at_sale), 0) AS total_weight
                  FROM animal_sales s
                  LEFT JOIN animal_records ar ON s.animal_id = ar.ANIMAL_ID
                  WHERE 1=1 $where_sql";

    $stats_stmt = $conn->prepare($stats_sql);
    foreach ($params as $key => $val) {
        $stats_stmt->bindValue($key, $val, PDO::PARAM_STR);
    }
    $stats_stmt->execute();
    $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

    $total_records = $stats['total_count'] ?? 0;
    $total_revenue = $stats['total_revenue'] ?? 0;
    $total_expenses = $stats['total_expenses'] ?? 0;
    $total_profit = $stats['total_profit'] ?? 0;
    
    $total_pages = $total_records > 0 ? ceil($total_records / $limit) : 1;

    // --- 4. QUERY B: FETCH PAGINATED DATA ---
    $data_sql = "SELECT 
            s.sale_id,
            DATE_FORMAT(s.sale_date, '%Y-%m-%d %H:%i') as SALE_DATE_FMT,
            s.customer_name,
            s.weight_at_sale,
            s.price_per_kg,
            s.final_sale_price,
            s.total_net_worth as TOTAL_COST,
            s.gross_profit,
            s.notes,
            ar.TAG_NO,
            ar.ANIMAL_ID
        FROM animal_sales s
        LEFT JOIN animal_records ar ON s.animal_id = ar.ANIMAL_ID
        WHERE 1=1 $where_sql
        ORDER BY s.sale_date DESC
        LIMIT :limit OFFSET :offset";

    $stmt = $conn->prepare($data_sql);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val, PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    
    $stmt->execute();
    $sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    // Helpful debugging: This prints the error as an HTML comment so you can 'View Source' to see it
    echo ""; 
    $sales = [];
    $total_records = 0; $total_revenue = 0; $total_expenses = 0; $total_profit = 0; $total_pages = 1;
}
function getQueryUrl($newPage, $currentParams) {
    $params = $currentParams;
    $params['page_num'] = $newPage;
    $params = array_filter($params, function($value) {
        return $value !== '' && $value !== null;
    });
    return http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Animal Sales Report</title>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.31/jspdf.plugin.autotable.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

    <style>
        body { font-family: system-ui, -apple-system, sans-serif; background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: #e2e8f0; margin: 0; padding-bottom: 40px; }
        .container { max-width: 1600px; margin: 0 auto; padding: 2rem; }
        .header { text-align: center; margin-bottom: 2rem; }
        .title { font-size: 2.2rem; font-weight: 800; background: linear-gradient(135deg, #10b981, #047857); -webkit-background-clip: text; -webkit-text-fill-color: transparent; margin-bottom: 0.5rem; }
        .subtitle { color: #94a3b8; font-size: 1rem; margin: 0; }

        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
        .stat-card { background: rgba(30, 41, 59, 0.6); border: 1px solid rgba(255,255,255,0.1); border-radius: 16px; padding: 1.5rem; text-align: center; backdrop-filter: blur(10px); box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); }
        .stat-val { font-size: 2rem; font-weight: 800; margin-bottom: 0.25rem; color: #fff; }
        .stat-lbl { color: #94a3b8; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1px; font-weight: 600; }
        
        .text-emerald { color: #34d399; } .text-green { color: #4ade80; } .text-red { color: #f87171; } .text-white { color: #fff; }

        .filter-box { background: rgba(15, 23, 42, 0.6); border: 1px solid #334155; padding: 1.5rem; border-radius: 16px; margin-bottom: 2rem; }
        .filter-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; align-items: end; }
        .form-group label { display: block; font-size: 0.75rem; color: #94a3b8; margin-bottom: 0.4rem; font-weight: 600; text-transform: uppercase; }
        .form-input { width: 100%; padding: 10px; background: #0f172a; border: 1px solid #334155; color: white; border-radius: 8px; font-size: 0.9rem; box-sizing: border-box; }
        .form-input:focus { border-color: #10b981; outline: none; }

        .active-filters { background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.3); padding: 0.75rem 1rem; border-radius: 8px; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; }
        .active-filters-label { color: #10b981; font-size: 0.85rem; font-weight: 600; }
        .filter-tag { background: rgba(16, 185, 129, 0.2); color: #34d399; padding: 4px 12px; border-radius: 6px; font-size: 0.8rem; display: inline-flex; align-items: center; gap: 6px; }
        .filter-tag .close { cursor: pointer; color: #10b981; font-weight: bold; text-decoration: none; }
        .filter-tag .close:hover { color: #34d399; }

        .btn-group { display: flex; gap: 10px; flex-wrap: wrap; }
        .action-bar { margin-top: 1.5rem; display: flex; gap: 10px; justify-content: flex-end; flex-wrap: wrap; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 1rem; }
        .btn { padding: 10px 20px; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; text-decoration: none; font-size: 0.9rem; transition: transform 0.1s; white-space: nowrap; }
        .btn:active { transform: scale(0.98); }
        .btn-primary { background: #047857; color: white; }
        .btn-outline { background: transparent; border: 1px solid #475569; color: #cbd5e1; }
        .btn-export { background: #3b82f6; color: white; }
        .btn-excel { background: #10b981; color: white; }
        .btn-csv { background: #f59e0b; color: #1e293b; }

        .table-wrap { background: rgba(30, 41, 59, 0.5); border-radius: 16px; overflow: hidden; border: 1px solid #334155; overflow-x: auto; margin-bottom: 1.5rem; }
        table { width: 100%; border-collapse: collapse; min-width: 1200px; }
        th { background: rgba(15, 23, 42, 0.9); color: #34d399; text-align: left; padding: 1rem; font-size: 0.8rem; text-transform: uppercase; border-bottom: 1px solid #334155; white-space: nowrap; }
        td { padding: 1rem; border-bottom: 1px solid rgba(255,255,255,0.05); font-size: 0.9rem; color: #e2e8f0; }
        tr:last-child td { border-bottom: none; }
        tr:hover { background: rgba(255,255,255,0.02); }

        .highlight { background: rgba(251, 191, 36, 0.3); color: #fbbf24; padding: 2px 4px; border-radius: 3px; font-weight: bold; }

        tfoot tr { background: rgba(16, 185, 129, 0.15); font-weight: bold; color: #fff; }
        tfoot td { border-top: 2px solid #10b981; font-size: 1rem; }

        .pagination { display: flex; justify-content: center; align-items: center; gap: 10px; margin-top: 1rem; }
        .page-link { padding: 8px 16px; border-radius: 8px; background: rgba(30, 41, 59, 0.6); border: 1px solid #334155; color: #cbd5e1; text-decoration: none; font-size: 0.9rem; transition: all 0.2s; }
        .page-link:hover:not(.disabled) { background: #334155; color: white; }
        .page-link.active { background: #10b981; border-color: #10b981; color: white; }
        .page-link.disabled { opacity: 0.5; pointer-events: none; }
        .page-info { color: #94a3b8; font-size: 0.9rem; margin: 0 10px; }

        @media (max-width: 768px) {
            .container { padding: 1rem; }
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
        <h1 class="title">Animal Sales Report</h1>
        <p class="subtitle">Financial record of sold livestock, revenue, and profit margins.</p>
    </div>

    <?php if ($search_term !== null || $date_from !== null): ?>
    <div class="active-filters">
        <span class="active-filters-label">🔍 Active Filters:</span>
        <?php if ($search_term !== null): ?>
            <span class="filter-tag">
                Search: "<?= htmlspecialchars($search_term) ?>"
                <a href="?<?= http_build_query(array_diff_key($_GET, ['search' => ''])) ?>" class="close">×</a>
            </span>
        <?php endif; ?>
        <?php if ($date_from !== null && $date_to !== null): ?>
            <span class="filter-tag">
                Date: <?= htmlspecialchars($date_from) ?> to <?= htmlspecialchars($date_to) ?>
                <a href="?<?= http_build_query(array_diff_key($_GET, ['date_from' => '', 'date_to' => ''])) ?>" class="close">×</a>
            </span>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-lbl">Total Heads Sold</div>
            <div class="stat-val"><?= number_format($total_records) ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-lbl">Total Revenue</div>
            <div class="stat-val text-emerald">₱<?= number_format($total_revenue, 2) ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-lbl">Gross Profit</div>
            <div class="stat-val <?= $total_profit >= 0 ? 'text-green':'text-red' ?>">
                ₱<?= number_format($total_profit, 2) ?>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-lbl">Avg. Profit / Head</div>
            <div class="stat-val text-white">
                ₱<?= $total_records > 0 ? number_format($total_profit / $total_records, 2) : '0.00' ?>
            </div>
        </div>
    </div>

    <div class="filter-box">
        <form method="GET" id="filterForm">
            <div class="filter-grid">
                <div class="form-group">
                    <label>Sale Date Range</label>
                    <div style="display: flex; gap: 5px;">
                        <input type="date" name="date_from" class="form-input" value="<?= htmlspecialchars($date_from ?? '') ?>">
                        <input type="date" name="date_to" class="form-input" value="<?= htmlspecialchars($date_to ?? '') ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Search Tag / Customer Name</label>
                    <input 
                        type="text" 
                        name="search" 
                        class="form-input" 
                        placeholder="Try: A00, Lou, Vincent" 
                        value="<?= htmlspecialchars($search_term ?? '') ?>"
                        autocomplete="off">
                </div>
                
                <div class="btn-group">
                    <button type="submit" class="btn btn-primary">🔍 Search</button>
                    <a href="animal_sales_reports.php" class="btn btn-outline">↺ Reset</a>
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
                    <th>Date</th>
                    <th>Animal Tag</th>
                    <th>Customer</th>
                    <th style="text-align:right;">Weight (kg)</th>
                    <th style="text-align:right;">Sale Price</th>
                    <th style="text-align:right;">Total Expenses</th>
                    <th style="text-align:right;">Net Profit</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($sales)): ?>
                    <tr><td colspan="7" style="text-align:center; padding:3rem; color:#64748b;">
                        <?php if ($search_term !== null): ?>
                            ❌ No sales records found matching <strong>"<?= htmlspecialchars($search_term) ?>"</strong>
                            <br><small style="color: #94a3b8; margin-top: 10px; display: block;">
                                Try searching different terms or check the date range
                            </small>
                        <?php else: ?>
                            No sales records found for the selected date range.
                        <?php endif; ?>
                    </td></tr>
                <?php else: ?>
                    <?php 
                    function highlightSearch($text, $search) {
                        if (empty($search) || empty($text)) return htmlspecialchars($text);
                        $text = htmlspecialchars($text);
                        $search = preg_quote($search, '/');
                        return preg_replace('/(' . $search . ')/i', '<span class="highlight">$1</span>', $text);
                    }
                    
                    foreach($sales as $s): 
                    ?>
                    <tr>
                        <td style="color:#94a3b8; font-family:monospace;"><?= $s['SALE_DATE_FMT'] ?></td>
                        <td style="font-weight:bold; color:#fff;"><?= highlightSearch($s['TAG_NO'] ?? 'N/A', $search_term ?? '') ?></td>
                        <td><?= highlightSearch($s['customer_name'] ?? 'N/A', $search_term ?? '') ?></td>
                        <td style="text-align:right;"><?= number_format($s['weight_at_sale'] ?? 0, 2) ?></td>
                        <td style="text-align:right; font-weight:bold; color:#34d399;">₱<?= number_format($s['final_sale_price'] ?? 0, 2) ?></td>
                        <td style="text-align:right; color:#f87171;">₱<?= number_format($s['TOTAL_COST'] ?? 0, 2) ?></td>
                        <td style="text-align:right; font-weight:bold; color: <?= ($s['gross_profit'] > 0) ? '#4ade80' : '#f87171' ?>;">
                            ₱<?= number_format($s['gross_profit'] ?? 0, 2) ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <?php if (!empty($sales)): ?>
            <tfoot>
                <tr>
                    <td colspan="4" style="text-align:right; text-transform:uppercase; letter-spacing:1px; color:#94a3b8;">
                        Grand Total <?= ($search_term !== null) ? '(Filtered)' : '(All Records)' ?>:
                    </td>
                    <td style="text-align:right; color:#34d399;">₱<?= number_format($total_revenue, 2) ?></td>
                    <td style="text-align:right; color:#f87171;">₱<?= number_format($total_expenses, 2) ?></td>
                    <td style="text-align:right; color:#4ade80;">₱<?= number_format($total_profit, 2) ?></td>
                </tr>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>

    <?php if ($total_pages > 1): ?>
    <div class="pagination">
        <a href="?<?= getQueryUrl($page_num - 1, $_GET) ?>" class="page-link <?= ($page_num <= 1) ? 'disabled' : '' ?>">
            ← Prev
        </a>
        <span class="page-info">Page <?= $page_num ?> of <?= $total_pages ?> (<?= number_format($total_records) ?> records)</span>
        <a href="?<?= getQueryUrl($page_num + 1, $_GET) ?>" class="page-link <?= ($page_num >= $total_pages) ? 'disabled' : '' ?>">
            Next →
        </a>
    </div>
    <?php endif; ?>

</div>

<script>
    const jsPDF = window.jspdf.jsPDF;
    const records = <?php echo json_encode($sales); ?>;
    const searchTerm = <?php echo json_encode($search_term); ?>;
    const totals = {
        revenue: <?= $total_revenue ?>,
        expenses: <?= $total_expenses ?>,
        profit: <?= $total_profit ?>,
        count: <?= $total_records ?>
    };
    
    function exportPDF() {
        const doc = new jsPDF('landscape');
        doc.setFontSize(18);
        doc.setTextColor(16, 185, 129); 
        doc.text("Animal Sales Report", 14, 15);
        
        doc.setFontSize(10);
        doc.setTextColor(100);
        const dateStr = new Date().toLocaleString();
        doc.text(`Generated: ${dateStr}`, 14, 22);
        
        let startY = 28;
        if (searchTerm) {
            doc.text(`Search Filter: "${searchTerm}"`, 14, startY);
            startY += 6;
        }

        let tableData = records.map(r => [
            r.SALE_DATE_FMT,
            r.TAG_NO || 'N/A',
            r.customer_name || 'N/A',
            (r.weight_at_sale || 0).toFixed(2),
            parseFloat(r.final_sale_price || 0).toFixed(2),
            parseFloat(r.TOTAL_COST || 0).toFixed(2),
            parseFloat(r.gross_profit || 0).toFixed(2)
        ]);

        tableData.push([
            '', '', '', 'GRAND TOTAL:', 
            totals.revenue.toFixed(2), 
            totals.expenses.toFixed(2), 
            totals.profit.toFixed(2)
        ]);

        doc.autoTable({
            head: [['Date', 'Tag', 'Customer', 'Weight', 'Sale Price', 'Expenses', 'Profit']],
            body: tableData,
            startY: startY,
            styles: { fontSize: 8 },
            headStyles: { fillColor: [4, 120, 87] },
            didParseCell: function (data) {
                if (data.row.index === tableData.length - 1) {
                    data.cell.styles.fontStyle = 'bold';
                    data.cell.styles.fillColor = [220, 252, 231];
                    data.cell.styles.textColor = [20, 83, 45];
                }
            }
        });
        
        const filename = searchTerm ? 
            `Sales_Report_Search_${searchTerm}_<?= date('Y-m-d') ?>.pdf` : 
            `Sales_Report_<?= date('Y-m-d') ?>.pdf`;
        doc.save(filename);
    }

    function exportExcel() {
        const excelData = records.map(r => ({
            'Date': r.SALE_DATE_FMT,
            'Tag No': r.TAG_NO || 'N/A',
            'Customer': r.customer_name || 'N/A',
            'Weight (kg)': parseFloat(r.weight_at_sale || 0),
            'Sale Price': parseFloat(r.final_sale_price || 0),
            'Total Expenses': parseFloat(r.TOTAL_COST || 0),
            'Net Profit': parseFloat(r.gross_profit || 0)
        }));

        excelData.push({
            'Date': '', 'Tag No': '', 'Customer': '',
            'Weight (kg)': 'GRAND TOTAL',
            'Sale Price': totals.revenue,
            'Total Expenses': totals.expenses,
            'Net Profit': totals.profit
        });

        const ws = XLSX.utils.json_to_sheet(excelData);
        ws['!cols'] = [
            { wch: 18 }, { wch: 12 }, { wch: 25 }, { wch: 12 }, { wch: 15 }, { wch: 15 }, { wch: 15 }
        ];
        
        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, "Sales");
        
        const filename = searchTerm ? 
            `Sales_Report_Search_${searchTerm}_<?= date('Y-m-d') ?>.xlsx` : 
            `Sales_Report_<?= date('Y-m-d') ?>.xlsx`;
        XLSX.writeFile(wb, filename);
    }

    function exportCSV() {
        const headers = ['Date', 'Tag', 'Customer', 'Weight', 'Sale Price', 'Expenses', 'Profit'];
        let csvContent = headers.join(',') + '\n';
        
        records.forEach(r => {
            const row = [
                r.SALE_DATE_FMT,
                r.TAG_NO || 'N/A',
                r.customer_name || 'N/A',
                (r.weight_at_sale || 0).toFixed(2),
                (r.final_sale_price || 0).toFixed(2),
                (r.TOTAL_COST || 0).toFixed(2),
                (r.gross_profit || 0).toFixed(2)
            ].map(e => {
                const str = String(e).replace(/"/g, '""');
                return str.includes(',') || str.includes('"') ? `"${str}"` : str;
            }).join(',');
            csvContent += row + '\n';
        });

        csvContent += `,,,"GRAND TOTAL",${totals.revenue.toFixed(2)},${totals.expenses.toFixed(2)},${totals.profit.toFixed(2)}\n`;

        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        const filename = searchTerm ? 
            `Sales_Report_Search_${searchTerm}_<?= date('Y-m-d') ?>.csv` : 
            `Sales_Report_<?= date('Y-m-d') ?>.csv`;
        link.download = filename;
        link.style.display = 'none';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
</script>

</body>
</html>