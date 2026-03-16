<?php
// reports/vitamins_transaction_report.php
error_reporting(0);
ini_set('display_errors', 0);
$page = "reports";
include '../config/Connection.php';

include '../security/checkAccess.php';
checkAccess('vitamins_supplements_transaction_report');
include '../common/navbar.php';
include '../common/chat_support.php';

// --- 1. GET FILTER INPUTS ---
$date_from   = $_GET['date_from']   ?? '';
$date_to     = $_GET['date_to']     ?? '';
$search_term = trim($_GET['search'] ?? '');

$transactions   = [];
$total_cost     = 0;
$total_txns     = 0;
$total_qty_used = 0;

try {
    if (!isset($conn)) {
        throw new Exception("Database connection failed.");
    }

    // --- 2. BUILD SQL QUERY ---
    $sql = "SELECT 
                vt.VST_ID,
                vt.DOSAGE,
                vt.QUANTITY_USED,
                vt.TOTAL_COST,
                vt.REMARKS,
                DATE_FORMAT(vt.TRANSACTION_DATE, '%Y-%m-%d') AS TRANS_DATE,
                DATE_FORMAT(vt.CREATED_AT, '%Y-%m-%d %H:%i') AS LOG_DATE,
                vs.SUPPLY_NAME AS ITEM_NAME,
                ar.TAG_NO
            FROM vitamins_supplements_transactions vt
            LEFT JOIN vitamins_supplements vs ON vt.ITEM_ID = vs.SUPPLY_ID
            LEFT JOIN animal_records ar ON vt.ANIMAL_ID = ar.ANIMAL_ID
            WHERE 1=1";

    $params = [];

    if ($date_from && $date_to) {
        $sql .= " AND vt.TRANSACTION_DATE BETWEEN :date_from AND :date_to";
        $params[':date_from'] = $date_from . ' 00:00:00';
        $params[':date_to']   = $date_to   . ' 23:59:59';
    }

    if ($search_term) {
        $like = '%' . strtolower($search_term) . '%';
        $sql .= " AND (LOWER(ar.TAG_NO) LIKE :s1 OR LOWER(vs.SUPPLY_NAME) LIKE :s2 OR LOWER(vt.REMARKS) LIKE :s3)";
        $params[':s1'] = $like;
        $params[':s2'] = $like;
        $params[':s3'] = $like;
    }

    $sql .= " ORDER BY vt.TRANSACTION_DATE DESC";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // --- 3. AGGREGATE STATS ---
    $total_txns = count($transactions);
    foreach ($transactions as $row) {
        $total_cost     += $row['TOTAL_COST'];
        $total_qty_used += $row['QUANTITY_USED'];
    }

} catch (Exception $e) {
    error_log($e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Vitamin Usage History</title>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.31/jspdf.plugin.autotable.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <style>
        /* ── RESET & BASE ───────────────────────────────── */
        *, *::before, *::after { box-sizing: border-box; }

        body {
            font-family: system-ui, -apple-system, sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: #e2e8f0;
            margin: 0;
            padding-bottom: 40px;
            min-height: 100vh;
        }

        .container {
            max-width: 1600px;
            margin: 0 auto;
            padding: 2rem;
        }

        /* ── BACK LINK ──────────────────────────────────── */
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            color: #94a3b8;
            font-weight: 600;
            font-size: 0.95rem;
            margin-bottom: 20px;
            transition: color 0.2s;
        }
        .back-link:hover { color: #fff; }

        /* ── PAGE HEADER ────────────────────────────────── */
        .header { text-align: center; margin-bottom: 2rem; }

        .title {
            font-size: 2.2rem;
            font-weight: 800;
            background: linear-gradient(135deg, #a3e635, #65a30d);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin: 0 0 0.5rem;
        }

        .subtitle { color: #94a3b8; font-size: 1rem; margin: 0; }

        /* ── STATS GRID ─────────────────────────────────── */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: rgba(30, 41, 59, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            padding: 1.5rem;
            text-align: center;
            backdrop-filter: blur(10px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .stat-val {
            font-size: 2rem;
            font-weight: 800;
            color: #fff;
            margin-bottom: 0.25rem;
        }

        .stat-lbl {
            color: #94a3b8;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 600;
        }

        .text-lime  { color: #bef264; }
        .text-green { color: #4ade80; }

        /* ── FILTER BOX ─────────────────────────────────── */
        .filter-box {
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid #334155;
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            align-items: end;
        }

        .form-group label {
            display: block;
            font-size: 0.75rem;
            color: #94a3b8;
            margin-bottom: 0.4rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .form-input {
            width: 100%;
            padding: 10px;
            background: #0f172a;
            border: 1px solid #334155;
            color: #fff;
            border-radius: 8px;
            font-size: 0.9rem;
        }
        .form-input:focus { border-color: #a3e635; outline: none; }

        /* ── BUTTONS ────────────────────────────────────── */
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            font-size: 0.9rem;
            transition: transform 0.1s, opacity 0.2s;
            white-space: nowrap;
        }
        .btn:active { transform: scale(0.98); }

        .btn-primary { background: #65a30d; color: #fff; }
        .btn-outline { background: transparent; border: 1px solid #475569; color: #cbd5e1; }
        .btn-pdf     { background: #3b82f6; color: #fff; }
        .btn-excel   { background: #10b981; color: #fff; }
        .btn-csv     { background: #f59e0b; color: #fff; }

        .btn-group { display: flex; gap: 10px; flex-wrap: wrap; }

        .action-bar {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            flex-wrap: wrap;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            margin-top: 1.5rem;
            padding-top: 1rem;
        }

        /* ── TABLE ──────────────────────────────────────── */
        .table-wrap {
            background: rgba(30, 41, 59, 0.5);
            border: 1px solid #334155;
            border-radius: 16px;
            overflow: hidden;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1000px;
        }

        th {
            background: rgba(15, 23, 42, 0.9);
            color: #d9f99d;
            text-align: left;
            padding: 1rem;
            font-size: 0.8rem;
            text-transform: uppercase;
            border-bottom: 1px solid #334155;
            white-space: nowrap;
        }

        td {
            padding: 1rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            font-size: 0.9rem;
            color: #e2e8f0;
        }

        tr:last-child td { border-bottom: none; }
        tr:hover { background: rgba(255, 255, 255, 0.02); }

        .td-date    { color: #d9f99d; font-weight: bold; }
        .td-tag     { font-weight: bold; color: #fff; }
        .td-cost    { color: #4ade80; text-align: right; }
        .td-qty     { text-align: right; font-weight: bold; }
        .td-remarks { color: #94a3b8; font-size: 0.85rem; max-width: 250px; }
        .td-right   { text-align: right; }

        .empty-row { text-align: center; padding: 3rem; color: #64748b; }

        /* ── RESPONSIVE ─────────────────────────────────── */
        @media (max-width: 768px) {
            .container { padding: 1rem; }
            .title { font-size: 1.8rem; }

            .stats-grid { grid-template-columns: 1fr; gap: 1rem; }
            .stat-card {
                padding: 1rem;
                display: flex;
                justify-content: space-between;
                align-items: center;
                text-align: left;
            }
            .stat-val { font-size: 1.5rem; margin: 0; }

            .filter-grid { grid-template-columns: 1fr; }

            .btn { flex: 1; justify-content: center; }
            .action-bar { flex-direction: column; }
            .action-bar .btn { width: 100%; }
        }
    </style>
</head>
<body>
<div class="container">

    <a href="reports.php" class="back-link">
        <i class="fa-solid fa-arrow-left"></i>
        Back to Reports Dashboard
    </a>

    <!-- Header -->
    <div class="header">
        <h1 class="title">Vitamin Usage History</h1>
        <p class="subtitle">Log of administered supplements, dosages, and costs.</p>
    </div>

    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-lbl">Transactions</div>
            <div class="stat-val"><?= number_format($total_txns) ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-lbl">Total Quantity Used</div>
            <div class="stat-val text-lime"><?= number_format($total_qty_used, 2) ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-lbl">Total Cost</div>
            <div class="stat-val text-green">₱<?= number_format($total_cost, 2) ?></div>
        </div>
    </div>

    <!-- Filters -->
    <div class="filter-box">
        <form method="GET">
            <div class="filter-grid">

                <div class="form-group">
                    <label>Transaction Date Range</label>
                    <div style="display:flex; gap:5px;">
                        <input type="date" name="date_from" class="form-input"
                               value="<?= htmlspecialchars($date_from) ?>">
                        <input type="date" name="date_to" class="form-input"
                               value="<?= htmlspecialchars($date_to) ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>Search (Tag / Item / Remarks)</label>
                    <input type="text" name="search" class="form-input"
                           placeholder="e.g. Pig 01, Iron"
                           value="<?= htmlspecialchars($search_term) ?>">
                </div>

                <div class="btn-group">
                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-filter"></i> Apply Filters
                    </button>
                    <a href="vitamins_transaction_report.php" class="btn btn-outline">
                        <i class="fa-solid fa-rotate-left"></i> Reset
                    </a>
                </div>
            </div>

            <!-- Export Buttons -->
            <div class="action-bar">
                <button type="button" class="btn btn-pdf"   onclick="exportPDF()">
                    <i class="fa-solid fa-file-pdf"></i> PDF
                </button>
                <button type="button" class="btn btn-excel" onclick="exportExcel()">
                    <i class="fa-solid fa-file-excel"></i> Excel
                </button>
                <button type="button" class="btn btn-csv"   onclick="exportCSV()">
                    <i class="fa-solid fa-file-csv"></i> CSV
                </button>
            </div>
        </form>
    </div>

    <!-- Table -->
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Animal Tag</th>
                    <th>Vitamin / Supplement</th>
                    <th>Dosage</th>
                    <th class="td-right">Qty Used</th>
                    <th class="td-right">Cost</th>
                    <th>Remarks</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($transactions)): ?>
                    <tr>
                        <td colspan="7" class="empty-row">No usage records found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($transactions as $t): ?>
                    <tr>
                        <td class="td-date"><?= htmlspecialchars($t['TRANS_DATE']) ?></td>
                        <td class="td-tag"><?= htmlspecialchars($t['TAG_NO'] ?? 'Unknown Tag') ?></td>
                        <td><?= htmlspecialchars($t['ITEM_NAME'] ?? 'Unknown Item') ?></td>
                        <td><?= htmlspecialchars($t['DOSAGE']) ?></td>
                        <td class="td-qty"><?= number_format($t['QUANTITY_USED'], 2) ?></td>
                        <td class="td-cost">₱<?= number_format($t['TOTAL_COST'], 2) ?></td>
                        <td class="td-remarks"><?= htmlspecialchars($t['REMARKS'] ?? '-') ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div><!-- /.container -->

<script>
    const jsPDF  = window.jspdf.jsPDF;
    const records = <?= json_encode($transactions) ?>;
    const stats   = {
        totalCost:  "<?= number_format($total_cost,  2) ?>",
        totalQty:   "<?= number_format($total_qty_used, 2) ?>",
        totalCount: "<?= number_format($total_txns) ?>"
    };

    // ── PDF Export ──────────────────────────────────────────
    function exportPDF() {
        const doc = new jsPDF('landscape');

        doc.setFontSize(18);
        doc.setTextColor(132, 204, 22);
        doc.text("Vitamin Usage Report", 14, 15);

        doc.setFontSize(10);
        doc.setTextColor(100);
        doc.text(`Generated: ${new Date().toLocaleString()}`, 14, 22);
        doc.text(`Total Records: ${stats.totalCount} | Total Qty: ${stats.totalQty} | Total Cost: PHP ${stats.totalCost}`, 14, 28);

        const rows = records.map(r => [
            r.TRANS_DATE,
            r.TAG_NO     || 'Unknown',
            r.ITEM_NAME  || 'Unknown',
            r.DOSAGE,
            parseFloat(r.QUANTITY_USED).toFixed(2),
            parseFloat(r.TOTAL_COST).toFixed(2),
            r.REMARKS    || '-'
        ]);

        doc.autoTable({
            head: [['Date', 'Tag', 'Vitamin', 'Dosage', 'Qty Used', 'Cost', 'Remarks']],
            body: rows,
            startY: 35,
            styles:     { fontSize: 8 },
            headStyles: { fillColor: [101, 163, 13] }
        });

        doc.save('Vitamin_Usage_Report.pdf');
    }

    // ── Excel Export ────────────────────────────────────────
    function exportExcel() {
        const data = records.map(r => ({
            'Date':          r.TRANS_DATE,
            'Animal Tag':    r.TAG_NO    || 'Unknown',
            'Vitamin':       r.ITEM_NAME || 'Unknown',
            'Dosage':        r.DOSAGE,
            'Quantity Used': parseFloat(r.QUANTITY_USED),
            'Cost':          parseFloat(r.TOTAL_COST),
            'Remarks':       r.REMARKS   || '-'
        }));

        const ws = XLSX.utils.json_to_sheet(data);
        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, "Vitamin Usage");
        XLSX.writeFile(wb, `Vitamin_Usage_Report_${today()}.xlsx`);
    }

    // ── CSV Export ──────────────────────────────────────────
    function exportCSV() {
        const headers = ['Date', 'Animal Tag', 'Vitamin', 'Dosage', 'Qty Used', 'Cost', 'Remarks'];
        const rows    = records.map(r => [
            r.TRANS_DATE, r.TAG_NO, r.ITEM_NAME,
            r.DOSAGE, r.QUANTITY_USED, r.TOTAL_COST, r.REMARKS
        ]);

        const csv = [headers, ...rows]
            .map(row => row.map(v => `"${(v ?? '').toString().replace(/"/g, '""')}"`).join(','))
            .join('\n');

        const link = document.createElement('a');
        link.href     = 'data:text/csv;charset=utf-8,' + encodeURI(csv);
        link.download = `Vitamin_Usage_Report_${today()}.csv`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    // ── Helper ──────────────────────────────────────────────
    function today() {
        return new Date().toISOString().slice(0, 10);
    }
</script>
</body>
</html>
<?php // No ob_end_flush needed — buffering not started in this file ?>