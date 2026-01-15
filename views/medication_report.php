<?php
// reports/medication_report.php
error_reporting(0);
ini_set('display_errors', 0);
$page = "reports";

include '../common/navbar.php';
include '../config/Connection.php';
include '../security/checkRole.php';
checkRole(2); // Farm Admin

// --- 1. GET FILTER INPUTS ---
$date_from   = $_GET['date_from'] ?? '';
$date_to     = $_GET['date_to'] ?? '';
$search_term = $_GET['search'] ?? '';

try {
    if (!isset($conn)) { throw new Exception("Database connection failed."); }

    // --- 2. BUILD SQL QUERY ---
    // Joins: 
    // - ITEMS (to get Medicine Name from ITEM_ID)
    // - ANIMAL_RECORDS (to get Tag No from ANIMAL_ID)
    $sql = "SELECT 
            tt.TT_ID,
            tt.DOSAGE,
            tt.QUANTITY_USED,
            tt.REMARKS,
            tt.TOTAL_COST,
            DATE_FORMAT(tt.TRANSACTION_DATE, '%Y-%m-%d') as TREAT_DATE,
            DATE_FORMAT(tt.CREATED_AT, '%Y-%m-%d %H:%i') as LOG_DATE,
            i.ITEM_NAME,
            i.UNIT_ID, 
            ar.TAG_NO
        FROM treatment_transactions tt
        LEFT JOIN items i ON tt.ITEM_ID = i.ITEM_ID
        LEFT JOIN animal_records ar ON tt.ANIMAL_ID = ar.ANIMAL_ID
        WHERE 1=1";

    $params = [];

    // Filter: Transaction Date
    if ($date_from && $date_to) {
        $sql .= " AND tt.TRANSACTION_DATE BETWEEN :date_from AND :date_to";
        $params[':date_from'] = $date_from . ' 00:00:00';
        $params[':date_to']   = $date_to . ' 23:59:59';
    }

    // Filter: Search (Animal Tag, Medicine Name, Remarks)
    if ($search_term) {
        $sql .= " AND (ar.TAG_NO LIKE :search OR i.ITEM_NAME LIKE :search OR tt.REMARKS LIKE :search)";
        $params[':search'] = "%$search_term%";
    }

    $sql .= " ORDER BY tt.TRANSACTION_DATE DESC"; 

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $treatments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // --- 3. PROCESS DATA & STATS ---
    $total_cost = 0;
    $total_treatments = count($treatments);
    $total_qty_used = 0;

    foreach ($treatments as $row) {
        $total_cost += $row['TOTAL_COST'];
        $total_qty_used += $row['QUANTITY_USED'];
    }

} catch (Exception $e) {
    $treatments = [];
    error_log($e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Medication & Treatment Report</title>
    
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
            background: linear-gradient(135deg, #f43f5e, #e11d48); /* Rose/Red */
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
        
        .text-rose { color: #fb7185; } 
        .text-red { color: #f87171; }
        .text-white { color: #fff; }

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
        .form-input:focus { border-color: #f43f5e; outline: none; }

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
        .btn-primary { background: #be123c; color: white; }
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
            background: rgba(15, 23, 42, 0.9); color: #fb7185; 
            text-align: left; padding: 1rem; font-size: 0.8rem; 
            text-transform: uppercase; border-bottom: 1px solid #334155; 
            white-space: nowrap; 
        }
        td { padding: 1rem; border-bottom: 1px solid rgba(255,255,255,0.05); font-size: 0.9rem; color: #e2e8f0; }
        tr:last-child td { border-bottom: none; }
        tr:hover { background: rgba(255,255,255,0.02); }

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
        <h1 class="title">Medication & Treatment Report</h1>
        <p class="subtitle">History of administered medicines, dosages, and treatment costs.</p>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-lbl">Treatments Administered</div>
            <div class="stat-val"><?= number_format($total_treatments) ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-lbl">Total Quantity Used</div>
            <div class="stat-val text-rose"><?= number_format($total_qty_used) ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-lbl">Total Treatment Cost</div>
            <div class="stat-val text-red">₱<?= number_format($total_cost, 2) ?></div>
        </div>
    </div>

    <div class="filter-box">
        <form method="GET">
            <div class="filter-grid">
                <div class="form-group">
                    <label>Treatment Date Range</label>
                    <div style="display: flex; gap: 5px;">
                        <input type="date" name="date_from" class="form-input" value="<?= htmlspecialchars($date_from) ?>">
                        <input type="date" name="date_to" class="form-input" value="<?= htmlspecialchars($date_to) ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Search (Tag / Medicine)</label>
                    <input type="text" name="search" class="form-input" placeholder="e.g. Piglet 01, Penicillin" value="<?= htmlspecialchars($search_term) ?>">
                </div>
                
                <div class="btn-group">
                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                    <a href="medication_report.php" class="btn btn-outline">Reset</a>
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
                    <th>Medicine Used</th>
                    <th>Dosage</th>
                    <th style="text-align:right;">Qty Used</th>
                    <th style="text-align:right;">Cost</th>
                    <th>Remarks</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($treatments)): ?>
                    <tr><td colspan="7" style="text-align:center; padding:3rem; color:#64748b;">No treatment records found.</td></tr>
                <?php else: ?>
                    <?php foreach($treatments as $t): ?>
                    <tr>
                        <td style="color:#fb7185; font-weight:bold;"><?= $t['TREAT_DATE'] ?></td>
                        <td>
                            <div style="font-weight:bold; color:#fff;"><?= htmlspecialchars($t['TAG_NO'] ?? 'Unknown Tag') ?></div>
                        </td>
                        <td><?= htmlspecialchars($t['ITEM_NAME'] ?? 'Unknown Item') ?></td>
                        <td><?= htmlspecialchars($t['DOSAGE']) ?></td>
                        <td style="text-align:right; font-weight:bold;">
                            <?= number_format($t['QUANTITY_USED'], 2) ?>
                        </td>
                        <td style="text-align:right; color:#f43f5e;">₱<?= number_format($t['TOTAL_COST'], 2) ?></td>
                        <td style="color:#94a3b8; font-size:0.85rem; max-width: 250px;">
                            <?= htmlspecialchars($t['REMARKS'] ?? '-') ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    const jsPDF = window.jspdf.jsPDF;
    const records = <?php echo json_encode($treatments); ?>;
    const stats = {
        totalCost: "<?= number_format($total_cost, 2) ?>",
        totalCount: "<?= number_format($total_treatments) ?>"
    };
    
    // --- PDF Export ---
    function exportPDF() {
        const doc = new jsPDF('landscape');
        
        doc.setFontSize(18);
        doc.setTextColor(225, 29, 72); // Rose
        doc.text("Medication & Treatment Report", 14, 15);
        
        doc.setFontSize(10);
        doc.setTextColor(100);
        const dateStr = new Date().toLocaleString();
        doc.text(`Generated: ${dateStr}`, 14, 22);
        doc.text(`Total Treatments: ${stats.totalCount} | Total Cost: PHP ${stats.totalCost}`, 14, 28);

        const rows = records.map(r => [
            r.TREAT_DATE,
            r.TAG_NO || 'Unknown',
            r.ITEM_NAME || 'Unknown',
            r.DOSAGE,
            parseFloat(r.QUANTITY_USED).toFixed(2),
            parseFloat(r.TOTAL_COST).toFixed(2),
            r.REMARKS || '-'
        ]);

        doc.autoTable({
            head: [['Date', 'Animal Tag', 'Medicine', 'Dosage', 'Qty Used', 'Cost', 'Remarks']],
            body: rows,
            startY: 35,
            styles: { fontSize: 8 },
            headStyles: { fillColor: [190, 18, 60] } // Crimson Header
        });

        doc.save('Treatment_Report.pdf');
    }

    // --- Excel Export ---
    function exportExcel() {
        const excelData = records.map(r => ({
            'Date': r.TREAT_DATE,
            'Animal Tag': r.TAG_NO || 'Unknown',
            'Medicine': r.ITEM_NAME || 'Unknown',
            'Dosage': r.DOSAGE,
            'Quantity Used': parseFloat(r.QUANTITY_USED),
            'Cost': parseFloat(r.TOTAL_COST),
            'Remarks': r.REMARKS
        }));

        const ws = XLSX.utils.json_to_sheet(excelData);
        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, "Treatments");
        XLSX.writeFile(wb, "Treatment_Report_" + new Date().toISOString().slice(0,10) + ".xlsx");
    }

    // --- CSV Export ---
    function exportCSV() {
        let csvContent = "data:text/csv;charset=utf-8,";
        csvContent += "Date,Animal Tag,Medicine,Dosage,Qty Used,Cost,Remarks\n";
        
        records.forEach(r => {
            const row = [
                r.TREAT_DATE, r.TAG_NO, r.ITEM_NAME, r.DOSAGE,
                r.QUANTITY_USED, r.TOTAL_COST, r.REMARKS
            ].map(e => `"${(e || '').toString().replace(/"/g, '""')}"`).join(","); 
            csvContent += row + "\n";
        });

        const encodedUri = encodeURI(csvContent);
        const link = document.createElement("a");
        link.setAttribute("href", encodedUri);
        link.setAttribute("download", "Treatment_Report_" + new Date().toISOString().slice(0,10) + ".csv");
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
</script>

</body>
</html>