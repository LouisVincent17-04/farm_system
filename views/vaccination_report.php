<?php
// reports/vaccination_report.php
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
$search_term = trim($_GET['search'] ?? '');

try {
    if (!isset($conn)) { throw new Exception("Database connection failed."); }

    // --- 2. BUILD SQL QUERY ---
    // Joins:
    // - VACCINES (to get Vaccine Name from VACCINE_ITEM_ID or SUPPLY_ID depending on your schema link)
    //   (Based on your screenshot, VACCINE_ITEM_ID likely links to your vaccines table or items table)
    // - ANIMAL_RECORDS (to get Tag No from ANIMAL_ID)
    // - UNITS (to get Unit Name from UNIT_ID)
    
    // Assuming VACCINE_ITEM_ID links to the 'vaccines' table (SUPPLY_ID) or 'items' table based on your previous messages.
    // I will assume it links to the 'vaccines' table you showed earlier since the column is VACCINE_ITEM_ID.
    
    $sql = "SELECT 
            vr.VACCINATION_ID,
            vr.VET_NAME,
            vr.QUANTITY,
            vr.VACCINATION_COST,
            vr.VACCINE_COST,
            vr.REMARKS,
            DATE_FORMAT(vr.VACCINATION_DATE, '%Y-%m-%d') as VAC_DATE,
            DATE_FORMAT(vr.DATE_UPDATED, '%Y-%m-%d %H:%i') as LOG_DATE,
            v.SUPPLY_NAME as VACCINE_NAME,
            u.UNIT_NAME, 
            ar.TAG_NO
        FROM vaccination_records vr
        LEFT JOIN vaccines v ON vr.VACCINE_ITEM_ID = v.SUPPLY_ID
        LEFT JOIN animal_records ar ON vr.ANIMAL_ID = ar.ANIMAL_ID
        LEFT JOIN units u ON vr.UNIT_ID = u.UNIT_ID
        WHERE 1=1";

    $params = [];

    // Filter: Vaccination Date
    if ($date_from && $date_to) {
        $sql .= " AND vr.VACCINATION_DATE BETWEEN :date_from AND :date_to";
        $params[':date_from'] = $date_from . ' 00:00:00';
        $params[':date_to']   = $date_to . ' 23:59:59';
    }

    // Filter: Search (CASE INSENSITIVE)
    if ($search_term) {
        $search_pattern = "%" . strtolower($search_term) . "%";
        $sql .= " AND (LOWER(ar.TAG_NO) LIKE :search1 OR LOWER(vr.VET_NAME) LIKE :search2 OR LOWER(v.SUPPLY_NAME) LIKE :search3 OR LOWER(vr.REMARKS) LIKE :search4)";
        $params[':search1'] = $search_pattern;
        $params[':search2'] = $search_pattern;
        $params[':search3'] = $search_pattern;
        $params[':search4'] = $search_pattern;
    }

    $sql .= " ORDER BY vr.VACCINATION_DATE DESC"; 

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $vaccinations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // --- 3. PROCESS DATA & STATS ---
    $total_service_cost = 0;
    $total_vaccine_cost = 0;
    $total_records = count($vaccinations);
    $total_doses = 0;

    foreach ($vaccinations as $row) {
        $total_service_cost += $row['VACCINATION_COST'];
        $total_vaccine_cost += $row['VACCINE_COST'];
        $total_doses += $row['QUANTITY'];
    }
    
    $grand_total = $total_service_cost + $total_vaccine_cost;

} catch (Exception $e) {
    $vaccinations = [];
    error_log($e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Vaccination History Report</title>
    
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
            background: linear-gradient(135deg, #2dd4bf, #0d9488); /* Teal */
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
        
        .text-teal { color: #2dd4bf; } 
        .text-cyan { color: #22d3ee; }
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
        .form-input:focus { border-color: #2dd4bf; outline: none; }

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
        .btn-primary { background: #0d9488; color: white; }
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
            background: rgba(15, 23, 42, 0.9); color: #5eead4; 
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
        <h1 class="title">Vaccination History Report</h1>
        <p class="subtitle">Log of vaccination events, veterinarian services, and costs.</p>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-lbl">Total Vaccinations</div>
            <div class="stat-val"><?= number_format($total_records) ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-lbl">Doses Administered</div>
            <div class="stat-val text-teal"><?= number_format($total_doses) ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-lbl">Vaccine Cost</div>
            <div class="stat-val text-cyan">₱<?= number_format($total_vaccine_cost, 2) ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-lbl">Grand Total (Inc. Service)</div>
            <div class="stat-val text-white">₱<?= number_format($grand_total, 2) ?></div>
        </div>
    </div>

    <div class="filter-box">
        <form method="GET">
            <div class="filter-grid">
                <div class="form-group">
                    <label>Vaccination Date Range</label>
                    <div style="display: flex; gap: 5px;">
                        <input type="date" name="date_from" class="form-input" value="<?= htmlspecialchars($date_from) ?>">
                        <input type="date" name="date_to" class="form-input" value="<?= htmlspecialchars($date_to) ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Search (Tag / Vet / Vaccine)</label>
                    <input type="text" name="search" class="form-input" placeholder="e.g. Doc Louis, ASF" value="<?= htmlspecialchars($search_term) ?>">
                </div>
                
                <div class="btn-group">
                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                    <a href="vaccination_report.php" class="btn btn-outline">Reset</a>
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
                    <th>Vaccine Name</th>
                    <th>Veterinarian</th>
                    <th style="text-align:right;">Qty</th>
                    <th style="text-align:right;">Vac. Cost</th>
                    <th style="text-align:right;">Service Cost</th>
                    <th>Remarks</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($vaccinations)): ?>
                    <tr><td colspan="8" style="text-align:center; padding:3rem; color:#64748b;">No vaccination records found.</td></tr>
                <?php else: ?>
                    <?php foreach($vaccinations as $v): ?>
                    <tr>
                        <td style="color:#5eead4; font-weight:bold;"><?= $v['VAC_DATE'] ?></td>
                        <td>
                            <div style="font-weight:bold; color:#fff;"><?= htmlspecialchars($v['TAG_NO'] ?? 'Unknown Tag') ?></div>
                        </td>
                        <td><?= htmlspecialchars($v['VACCINE_NAME'] ?? 'Unknown Vaccine') ?></td>
                        <td><?= htmlspecialchars($v['VET_NAME'] ?? '-') ?></td>
                        <td style="text-align:right; font-weight:bold;">
                            <?= number_format($v['QUANTITY'], 2) ?> 
                            <small style="color:#64748b; font-weight:normal;"><?= htmlspecialchars($v['UNIT_NAME'] ?? '') ?></small>
                        </td>
                        <td style="text-align:right; color:#22d3ee;">₱<?= number_format($v['VACCINE_COST'], 2) ?></td>
                        <td style="text-align:right; color:#94a3b8;">₱<?= number_format($v['VACCINATION_COST'], 2) ?></td>
                        <td style="color:#94a3b8; font-size:0.85rem; max-width: 200px;">
                            <?= htmlspecialchars($v['REMARKS'] ?? '-') ?>
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
    const records = <?php echo json_encode($vaccinations); ?>;
    const stats = {
        grandTotal: "<?= number_format($grand_total, 2) ?>",
        totalCount: "<?= number_format($total_records) ?>"
    };
    
    // --- PDF Export ---
    function exportPDF() {
        const doc = new jsPDF('landscape');
        
        doc.setFontSize(18);
        doc.setTextColor(45, 212, 191); // Teal
        doc.text("Vaccination History Report", 14, 15);
        
        doc.setFontSize(10);
        doc.setTextColor(100);
        const dateStr = new Date().toLocaleString();
        doc.text(`Generated: ${dateStr}`, 14, 22);
        doc.text(`Total Records: ${stats.totalCount} | Grand Total Cost: PHP ${stats.grandTotal}`, 14, 28);

        const rows = records.map(r => [
            r.VAC_DATE,
            r.TAG_NO || 'Unknown',
            r.VACCINE_NAME || 'Unknown',
            r.VET_NAME || '-',
            parseFloat(r.QUANTITY).toFixed(2),
            parseFloat(r.VACCINE_COST).toFixed(2),
            parseFloat(r.VACCINATION_COST).toFixed(2),
            r.REMARKS || '-'
        ]);

        doc.autoTable({
            head: [['Date', 'Tag', 'Vaccine', 'Vet', 'Qty', 'Vac Cost', 'Svc Cost', 'Remarks']],
            body: rows,
            startY: 35,
            styles: { fontSize: 8 },
            headStyles: { fillColor: [13, 148, 136] } // Teal Header
        });

        doc.save('Vaccination_Report.pdf');
    }

    // --- Excel Export ---
    function exportExcel() {
        const excelData = records.map(r => ({
            'Date': r.VAC_DATE,
            'Animal Tag': r.TAG_NO || 'Unknown',
            'Vaccine': r.VACCINE_NAME || 'Unknown',
            'Veterinarian': r.VET_NAME || '-',
            'Quantity': parseFloat(r.QUANTITY),
            'Unit': r.UNIT_NAME || '',
            'Vaccine Cost': parseFloat(r.VACCINE_COST),
            'Service Cost': parseFloat(r.VACCINATION_COST),
            'Remarks': r.REMARKS
        }));

        const ws = XLSX.utils.json_to_sheet(excelData);
        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, "Vaccinations");
        XLSX.writeFile(wb, "Vaccination_Report_" + new Date().toISOString().slice(0,10) + ".xlsx");
    }

    // --- CSV Export ---
    function exportCSV() {
        let csvContent = "data:text/csv;charset=utf-8,";
        csvContent += "Date,Tag,Vaccine,Vet,Qty,Unit,Vac Cost,Svc Cost,Remarks\n";
        
        records.forEach(r => {
            const row = [
                r.VAC_DATE, r.TAG_NO, r.VACCINE_NAME, r.VET_NAME,
                r.QUANTITY, r.UNIT_NAME, r.VACCINE_COST, r.VACCINATION_COST, r.REMARKS
            ].map(e => `"${(e || '').toString().replace(/"/g, '""')}"`).join(","); 
            csvContent += row + "\n";
        });

        const encodedUri = encodeURI(csvContent);
        const link = document.createElement("a");
        link.setAttribute("href", encodedUri);
        link.setAttribute("download", "Vaccination_Report_" + new Date().toISOString().slice(0,10) + ".csv");
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
</script>

</body>
</html>