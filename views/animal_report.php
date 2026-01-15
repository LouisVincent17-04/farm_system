<?php
// reports/animal_report.php
error_reporting(0);
ini_set('display_errors', 0);
$page = "reports";

// Includes
include '../common/navbar.php';
include '../config/Connection.php';
include '../security/checkRole.php';
checkRole(2);

// --- 1. GET FILTER INPUTS ---
$date_from   = $_GET['date_from'] ?? '';
$date_to     = $_GET['date_to'] ?? '';
$status      = $_GET['status'] ?? ''; 
$animal_type = $_GET['animal_type'] ?? '';
$breed       = $_GET['breed'] ?? '';
$stage       = $_GET['stage'] ?? ''; 
$sex         = $_GET['sex'] ?? '';

try {
    if (!isset($conn)) { throw new Exception("Database connection failed."); }

    // --- 2. BUILD SQL QUERY ---
    $sql = "SELECT 
            ar.ANIMAL_ID,
            ar.TAG_NO,
            at.ANIMAL_TYPE_NAME,
            b.BREED_NAME,
            ac.STAGE_NAME,
            DATE_FORMAT(ar.BIRTH_DATE, '%Y-%m-%d') as BIRTH_DATE,
            CASE ar.SEX WHEN 'M' THEN 'Male' WHEN 'F' THEN 'Female' ELSE 'Unknown' END as SEX,
            ar.CURRENT_STATUS,
            ar.IS_ACTIVE,
            l.LOCATION_NAME,
            bld.BUILDING_NAME,
            p.PEN_NAME,
            ar.WEIGHT_AT_BIRTH,
            ar.CURRENT_ESTIMATED_WEIGHT,
            ar.ACQUISITION_COST,
            m.TAG_NO as MOTHER_TAG,
            DATE_FORMAT(ar.CREATED_AT, '%Y-%m-%d %H:%i') as CREATED_AT
        FROM ANIMAL_RECORDS ar
        LEFT JOIN ANIMAL_TYPE at ON ar.ANIMAL_TYPE_ID = at.ANIMAL_TYPE_ID
        LEFT JOIN BREEDS b ON ar.BREED_ID = b.BREED_ID
        LEFT JOIN ANIMAL_CLASSIFICATIONS ac ON ar.CLASS_ID = ac.CLASS_ID
        LEFT JOIN LOCATIONS l ON ar.LOCATION_ID = l.LOCATION_ID
        LEFT JOIN BUILDINGS bld ON ar.BUILDING_ID = bld.BUILDING_ID
        LEFT JOIN PENS p ON ar.PEN_ID = p.PEN_ID
        LEFT JOIN ANIMAL_RECORDS m ON ar.MOTHER_ID = m.ANIMAL_ID
        WHERE 1=1";

    $params = [];

    // Apply Filters
    if ($date_from && $date_to) {
        $sql .= " AND ar.BIRTH_DATE BETWEEN :date_from AND :date_to";
        $params[':date_from'] = $date_from;
        $params[':date_to']   = $date_to;
    }

    if ($status) {
        if ($status === 'Active') {
            $sql .= " AND ar.IS_ACTIVE = 1";
        } elseif ($status === 'Inactive') {
            $sql .= " AND ar.IS_ACTIVE = 0";
        } else {
            $sql .= " AND ar.CURRENT_STATUS = :status";
            $params[':status'] = $status;
        }
    }

    if ($animal_type) { $sql .= " AND ar.ANIMAL_TYPE_ID = :atype"; $params[':atype'] = $animal_type; }
    if ($breed)       { $sql .= " AND ar.BREED_ID = :breed"; $params[':breed'] = $breed; }
    if ($stage)       { $sql .= " AND ar.CLASS_ID = :stage"; $params[':stage'] = $stage; }
    if ($sex)         { $sql .= " AND ar.SEX = :sex"; $params[':sex'] = $sex; }

    $sql .= " ORDER BY ar.ANIMAL_ID DESC";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $animals = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // --- 3. CALCULATE STATISTICS ---
    $total_heads = count($animals);
    $total_value = 0;
    $total_weight = 0;
    $male_count = 0;
    $female_count = 0;

    foreach ($animals as $row) {
        $total_value += $row['ACQUISITION_COST'];
        $total_weight += $row['CURRENT_ESTIMATED_WEIGHT'];
        if ($row['SEX'] == 'Male') $male_count++;
        if ($row['SEX'] == 'Female') $female_count++;
    }

    // --- 4. FETCH DROPDOWN DATA FOR FILTER UI ---
    $types = $conn->query("SELECT * FROM ANIMAL_TYPE ORDER BY ANIMAL_TYPE_NAME")->fetchAll();
    $breeds_list = $conn->query("SELECT * FROM BREEDS ORDER BY BREED_NAME")->fetchAll();
    $stages_list = $conn->query("SELECT * FROM ANIMAL_CLASSIFICATIONS ORDER BY CLASS_ID")->fetchAll();

} catch (Exception $e) {
    $animals = [];
    error_log($e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Advanced Animal Report</title>
    
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
            background: linear-gradient(135deg, #22c55e, #16a34a); 
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
        .text-green { color: #4ade80; } .text-gold { color: #fbbf24; } .text-blue { color: #60a5fa; }

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
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); 
            gap: 1rem; 
            align-items: end; 
        }
        .form-group label { display: block; font-size: 0.75rem; color: #94a3b8; margin-bottom: 0.4rem; font-weight: 600; text-transform: uppercase; }
        .form-input { 
            width: 100%; padding: 10px; background: #0f172a; 
            border: 1px solid #334155; color: white; border-radius: 8px; 
            font-size: 0.9rem;
            box-sizing: border-box; /* Fix width overflow */
        }
        .form-input:focus { border-color: #22c55e; outline: none; }
        
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
            font-size: 0.9rem; transition: transform 0.1s;
            white-space: nowrap;
        }
        .btn:active { transform: scale(0.98); }
        .btn-primary { background: #22c55e; color: white; }
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
            overflow-x: auto; /* Enable horizontal scroll */
        }
        table { width: 100%; border-collapse: collapse; min-width: 1000px; /* Force min width for scroll */ }
        th { 
            background: rgba(15, 23, 42, 0.9); color: #94a3b8; 
            text-align: left; padding: 1rem; font-size: 0.8rem; 
            text-transform: uppercase; border-bottom: 1px solid #334155; 
            white-space: nowrap; 
        }
        td { 
            padding: 1rem; border-bottom: 1px solid rgba(255,255,255,0.05); 
            font-size: 0.9rem; color: #e2e8f0; 
        }
        tr:last-child td { border-bottom: none; }
        tr:hover { background: rgba(255,255,255,0.02); }

        /* Badges & Values */
        .badge { padding: 4px 10px; border-radius: 12px; font-size: 0.75rem; font-weight: 600; display: inline-block;}
        .b-active { background: rgba(34, 197, 94, 0.15); color: #4ade80; }
        .b-sold { background: rgba(251, 191, 36, 0.15); color: #fbbf24; }
        .b-dec { background: rgba(239, 68, 68, 0.15); color: #f87171; }
        
        .val-money { font-family: monospace; color: #fbbf24; font-weight: bold; }
        .val-weight { font-family: monospace; color: #60a5fa; font-weight: bold; }

        /* --- RESPONSIVE MEDIA QUERIES --- */
        @media (max-width: 768px) {
            .container { padding: 1rem; }
            .title { font-size: 1.8rem; }
            
            /* Stack Stats Cards */
            .stats-grid { 
                grid-template-columns: 1fr; /* 1 Column */
                gap: 1rem; 
            }
            .stat-card {
                padding: 1rem;
                display: flex;
                justify-content: space-between;
                align-items: center;
                text-align: left;
            }
            .stat-val { font-size: 1.5rem; margin: 0; order: 2; }
            .stat-lbl { order: 1; }

            /* Stack Filter Bar */
            .filter-grid { grid-template-columns: 1fr; }
            .btn-group { width: 100%; }
            .btn { flex: 1; justify-content: center; }
            
            .action-bar { 
                flex-direction: column; 
            }
            .action-bar .btn { width: 100%; }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <h1 class="title">Animal Inventory Report</h1>
        <p class="subtitle">Comprehensive livestock analysis and metrics.</p>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-lbl">Total Heads Filtered</div>
            <div class="stat-val"><?= number_format($total_heads) ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-lbl">Total Inventory Value</div>
            <div class="stat-val text-gold">₱<?= number_format($total_value, 2) ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-lbl">Total Est. Weight</div>
            <div class="stat-val text-blue"><?= number_format($total_weight, 2) ?> kg</div>
        </div>
        <div class="stat-card">
            <div class="stat-lbl">Females / Males</div>
            <div class="stat-val text-green"><?= $female_count ?> / <?= $male_count ?></div>
        </div>
    </div>

    <div class="filter-box">
        <form method="GET">
            <div class="filter-grid">
                <div class="form-group">
                    <label>Birth Date Range</label>
                    <div style="display: flex; gap: 5px;">
                        <input type="date" name="date_from" class="form-input" value="<?= htmlspecialchars($date_from) ?>">
                        <input type="date" name="date_to" class="form-input" value="<?= htmlspecialchars($date_to) ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Type & Breed</label>
                    <div style="display: flex; gap: 5px;">
                        <select name="animal_type" class="form-input">
                            <option value="">All Types</option>
                            <?php foreach($types as $t): ?>
                                <option value="<?= $t['ANIMAL_TYPE_ID'] ?>" <?= $animal_type == $t['ANIMAL_TYPE_ID']?'selected':'' ?>>
                                    <?= htmlspecialchars($t['ANIMAL_TYPE_NAME']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <select name="breed" class="form-input">
                            <option value="">All Breeds</option>
                            <?php foreach($breeds_list as $b): ?>
                                <option value="<?= $b['BREED_ID'] ?>" <?= $breed == $b['BREED_ID']?'selected':'' ?>>
                                    <?= htmlspecialchars($b['BREED_NAME']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Stage / Classification</label>
                    <select name="stage" class="form-input">
                        <option value="">All Stages</option>
                        <?php foreach($stages_list as $s): ?>
                            <option value="<?= $s['CLASS_ID'] ?>" <?= $stage == $s['CLASS_ID']?'selected':'' ?>>
                                <?= htmlspecialchars($s['STAGE_NAME']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Status</label>
                    <select name="status" class="form-input">
                        <option value="">All</option>
                        <option value="Active" <?= $status=='Active'?'selected':'' ?>>Active Herd</option>
                        <option value="Sold" <?= $status=='Sold'?'selected':'' ?>>Sold History</option>
                        <option value="Deceased" <?= $status=='Deceased'?'selected':'' ?>>Deceased/Cull</option>
                    </select>
                </div>
                
                <div class="btn-group">
                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                    <a href="animal_report.php" class="btn btn-outline">Reset</a>
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
        <table id="reportTable">
            <thead>
                <tr>
                    <th>Tag No</th>
                    <th>Classification</th>
                    <th>Breed</th>
                    <th>Sex</th>
                    <th>Status</th>
                    <th>Location</th>
                    <th>Mother</th>
                    <th style="text-align:right;">Birth Wt</th>
                    <th style="text-align:right;">Cur. Wt</th>
                    <th style="text-align:right;">Cost Value</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($animals)): ?>
                    <tr><td colspan="10" style="text-align:center; padding:3rem; color:#64748b;">No records found matching filters.</td></tr>
                <?php else: ?>
                    <?php foreach($animals as $row): 
                        $statusClass = 'b-active';
                        if($row['CURRENT_STATUS'] == 'Sold') $statusClass = 'b-sold';
                        if(in_array($row['CURRENT_STATUS'], ['Deceased','Cull','Dead'])) $statusClass = 'b-dec';
                    ?>
                    <tr>
                        <td style="font-weight:bold; color:#fff;"><?= htmlspecialchars($row['TAG_NO']) ?></td>
                        <td>
                            <div><?= htmlspecialchars($row['STAGE_NAME'] ?? 'Unknown') ?></div>
                            <small style="color:#64748b"><?= htmlspecialchars($row['ANIMAL_TYPE_NAME']) ?></small>
                        </td>
                        <td><?= htmlspecialchars($row['BREED_NAME']) ?></td>
                        <td><?= $row['SEX'] ?></td>
                        <td><span class="badge <?= $statusClass ?>"><?= htmlspecialchars($row['CURRENT_STATUS']) ?></span></td>
                        <td>
                            <div><?= htmlspecialchars($row['LOCATION_NAME']) ?></div>
                            <small style="color:#64748b"><?= htmlspecialchars($row['BUILDING_NAME']) ?> - <?= htmlspecialchars($row['PEN_NAME']) ?></small>
                        </td>
                        <td style="color:#f472b6;"><?= htmlspecialchars($row['MOTHER_TAG'] ?? '-') ?></td>
                        <td style="text-align:right;"><?= $row['WEIGHT_AT_BIRTH'] > 0 ? $row['WEIGHT_AT_BIRTH'] : '-' ?></td>
                        <td style="text-align:right;" class="val-weight"><?= $row['CURRENT_ESTIMATED_WEIGHT'] > 0 ? $row['CURRENT_ESTIMATED_WEIGHT'] : '-' ?></td>
                        <td style="text-align:right;" class="val-money">₱<?= number_format($row['ACQUISITION_COST'], 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    const jsPDF = window.jspdf.jsPDF;
    // Pass PHP data to JS safely
    const records = <?php echo json_encode($animals); ?>;
    const stats = {
        heads: "<?= number_format($total_heads) ?>",
        value: "<?= number_format($total_value, 2) ?>",
        weight: "<?= number_format($total_weight, 2) ?>"
    };

    // --- PDF Export ---
    function exportPDF() {
        const doc = new jsPDF('landscape');
        
        doc.setFontSize(18);
        doc.setTextColor(34, 197, 94);
        doc.text("Farm Animal Inventory Report", 14, 15);
        
        doc.setFontSize(10);
        doc.setTextColor(100);
        const dateStr = new Date().toLocaleString();
        doc.text(`Generated: ${dateStr}`, 14, 22);
        doc.text(`Total Value: PHP ${stats.value}`, 200, 22);

        const rows = records.map(r => [
            r.TAG_NO, 
            r.STAGE_NAME || '-', 
            r.BREED_NAME, 
            r.SEX, 
            r.CURRENT_STATUS, 
            r.LOCATION_NAME, 
            r.MOTHER_TAG || '-',
            r.CURRENT_ESTIMATED_WEIGHT,
            r.ACQUISITION_COST
        ]);

        doc.autoTable({
            head: [['Tag', 'Stage', 'Breed', 'Sex', 'Status', 'Location', 'Mother', 'Wt (kg)', 'Cost (P)']],
            body: rows,
            startY: 30,
            styles: { fontSize: 8 },
            headStyles: { fillColor: [34, 197, 94] }
        });

        doc.save('Animal_Report_.pdf');
    }

    // --- Excel Export (Client Side via SheetJS) ---
    function exportExcel() {
        const excelData = records.map(r => ({
            'Tag No': r.TAG_NO,
            'Type': r.ANIMAL_TYPE_NAME,
            'Breed': r.BREED_NAME,
            'Stage': r.STAGE_NAME,
            'Sex': r.SEX,
            'Status': r.CURRENT_STATUS,
            'Location': `${r.LOCATION_NAME} - ${r.PEN_NAME}`,
            'Mother Tag': r.MOTHER_TAG || '-',
            'Birth Date': r.BIRTH_DATE,
            'Birth Wt': r.WEIGHT_AT_BIRTH,
            'Current Wt': r.CURRENT_ESTIMATED_WEIGHT,
            'Cost (PHP)': parseFloat(r.ACQUISITION_COST)
        }));

        const ws = XLSX.utils.json_to_sheet(excelData);
        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, "Inventory");
        XLSX.writeFile(wb, "Animal_Report_" + new Date().toISOString().slice(0,10) + ".xlsx");
    }

    // --- CSV Export (Client Side) ---
    function exportCSV() {
        let csvContent = "data:text/csv;charset=utf-8,";
        // Header
        csvContent += "Tag No,Type,Breed,Stage,Sex,Status,Location,Mother Tag,Birth Date,Current Wt,Cost\n";
        
        // Rows
        records.forEach(r => {
            const row = [
                r.TAG_NO, r.ANIMAL_TYPE_NAME, r.BREED_NAME, r.STAGE_NAME, r.SEX, r.CURRENT_STATUS,
                `${r.LOCATION_NAME} - ${r.PEN_NAME}`, r.MOTHER_TAG || '-', r.BIRTH_DATE,
                r.CURRENT_ESTIMATED_WEIGHT, r.ACQUISITION_COST
            ].map(e => `"${e}"`).join(","); // Quote fields to handle commas
            csvContent += row + "\n";
        });

        const encodedUri = encodeURI(csvContent);
        const link = document.createElement("a");
        link.setAttribute("href", encodedUri);
        link.setAttribute("download", "Animal_Report_" + new Date().toISOString().slice(0,10) + ".csv");
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
</script>

</body>
</html>