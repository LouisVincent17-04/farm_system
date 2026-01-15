<?php
// reports/audit_log_report.php
error_reporting(0);
ini_set('display_errors', 0);
$page = "reports";

include '../common/navbar.php';
include '../config/Connection.php';
include '../security/checkRole.php';
checkRole(1); // Administrator Only

// --- 1. CONFIGURATION & INPUTS ---
$limit = 50; // Records per page
$page_num = isset($_GET['page_num']) ? (int)$_GET['page_num'] : 1;
if ($page_num < 1) $page_num = 1;
$offset = ($page_num - 1) * $limit;

$user_filter   = $_GET['user'] ?? '';
$action_filter = $_GET['action'] ?? '';
$date_from     = $_GET['date_from'] ?? '';
$date_to       = $_GET['date_to'] ?? '';
$search_term   = trim($_GET['search'] ?? '');

try {
    if (!isset($conn)) { throw new Exception("Database connection failed."); }

    // --- 2. BUILD QUERY CONDITIONS ---
    $where_sql = "WHERE 1=1";
    $params = [];

    if ($user_filter) {
        $where_sql .= " AND USERNAME = :username";
        $params[':username'] = $user_filter;
    }
    if ($action_filter) {
        $where_sql .= " AND ACTION_TYPE = :action";
        $params[':action'] = $action_filter;
    }
    if ($date_from && $date_to) {
        $where_sql .= " AND LOG_DATE BETWEEN :date_from AND :date_to";
        $params[':date_from'] = $date_from . ' 00:00:00';
        $params[':date_to']   = $date_to . ' 23:59:59';
    }
    
    // Filter: Search (CASE INSENSITIVE)
    if ($search_term) {
        $search_pattern = "%" . strtolower($search_term) . "%";
        $where_sql .= " AND (LOWER(ACTION_DETAILS) LIKE :search1 OR LOWER(TABLE_NAME) LIKE :search2 OR LOWER(IP_ADDRESS) LIKE :search3)";
        $params[':search1'] = $search_pattern;
        $params[':search2'] = $search_pattern;
        $params[':search3'] = $search_pattern;
    }

    // --- 3. GET TOTAL COUNT (For Pagination) ---
    $count_sql = "SELECT COUNT(*) FROM audit_logs $where_sql";
    $count_stmt = $conn->prepare($count_sql);
    $count_stmt->execute($params);
    $total_records = $count_stmt->fetchColumn();
    $total_pages = ceil($total_records / $limit);

    // --- 4. FETCH DATA (With Limit & Offset) ---
    $sql = "SELECT 
            LOG_ID, USER_ID, USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS,
            DATE_FORMAT(LOG_DATE, '%Y-%m-%d %H:%i:%s') as LOG_DATE
        FROM audit_logs
        $where_sql
        ORDER BY LOG_DATE DESC
        LIMIT :limit OFFSET :offset";

    // Bind Limit/Offset as integers (PDO strictness)
    $stmt = $conn->prepare($sql);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // --- 5. STATS (For Summary Cards - Optional: Optimized to Count only current page or separate query) ---
    // For performance on millions of rows, we usually don't calculate "Total Deletions ever" on every page load.
    // We will just show the count of the *current view* or rely on the filtered total.
    
    // --- 6. DROPDOWNS (Cached or Distinct) ---
    // Ideally, these should be cached, but for now we query distinct.
    $users_list = $conn->query("SELECT DISTINCT USERNAME FROM audit_logs WHERE USERNAME IS NOT NULL ORDER BY USERNAME")->fetchAll(PDO::FETCH_COLUMN);
    $actions_list = $conn->query("SELECT DISTINCT ACTION_TYPE FROM audit_logs ORDER BY ACTION_TYPE")->fetchAll(PDO::FETCH_COLUMN);

} catch (Exception $e) {
    $logs = [];
    error_log($e->getMessage());
}

// Helper to keep filters in pagination links
function getQueryUrl($newPage, $currentParams) {
    $currentParams['page_num'] = $newPage;
    return http_build_query($currentParams);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>System Audit Logs</title>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.31/jspdf.plugin.autotable.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

    <style>
        /* SAME STYLES AS BEFORE + PAGINATION CSS */
        body { font-family: 'Segoe UI', system-ui, -apple-system, sans-serif; background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: #e2e8f0; margin: 0; padding-bottom: 40px; }
        .container { max-width: 1600px; margin: 0 auto; padding: 2rem; }
        .header { text-align: center; margin-bottom: 2rem; }
        .title { font-size: 2.2rem; font-weight: 800; background: linear-gradient(135deg, #f8fafc, #94a3b8); -webkit-background-clip: text; -webkit-text-fill-color: transparent; margin-bottom: 0.5rem; }
        .subtitle { color: #64748b; font-size: 1rem; margin: 0; }

        /* Stats Grid */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
        .stat-card { background: rgba(30, 41, 59, 0.6); border: 1px solid rgba(255,255,255,0.1); border-radius: 16px; padding: 1.5rem; text-align: center; backdrop-filter: blur(10px); }
        .stat-val { font-size: 1.8rem; font-weight: 800; margin-bottom: 0.25rem; color: #fff; }
        .stat-lbl { color: #94a3b8; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1px; font-weight: 600; }

        /* Filter Box */
        .filter-box { background: rgba(15, 23, 42, 0.6); border: 1px solid #334155; padding: 1.5rem; border-radius: 16px; margin-bottom: 2rem; }
        .filter-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; align-items: end; }
        .form-group label { display: block; font-size: 0.75rem; color: #94a3b8; margin-bottom: 0.4rem; font-weight: 600; text-transform: uppercase; }
        .form-input { width: 100%; padding: 10px; background: #0f172a; border: 1px solid #334155; color: white; border-radius: 8px; font-size: 0.9rem; box-sizing: border-box; }
        .form-input:focus { border-color: #94a3b8; outline: none; }

        /* Buttons */
        .btn-group { display: flex; gap: 10px; flex-wrap: wrap; }
        .action-bar { margin-top: 1.5rem; display: flex; gap: 10px; justify-content: flex-end; flex-wrap: wrap; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 1rem; }
        .btn { padding: 10px 20px; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; text-decoration: none; font-size: 0.9rem; white-space: nowrap; }
        .btn-primary { background: #475569; color: white; }
        .btn-outline { background: transparent; border: 1px solid #475569; color: #cbd5e1; }
        .btn-export { background: #3b82f6; color: white; }
        .btn-excel { background: #10b981; color: white; }
        .btn-csv { background: #f59e0b; color: #1e293b; }

        /* Table */
        .table-wrap { background: rgba(30, 41, 59, 0.5); border-radius: 16px; overflow: hidden; border: 1px solid #334155; overflow-x: auto; margin-bottom: 1.5rem;}
        table { width: 100%; border-collapse: collapse; min-width: 1200px; }
        th { background: rgba(15, 23, 42, 0.9); color: #cbd5e1; text-align: left; padding: 1rem; font-size: 0.8rem; text-transform: uppercase; border-bottom: 1px solid #334155; white-space: nowrap; }
        td { padding: 0.8rem 1rem; border-bottom: 1px solid rgba(255,255,255,0.05); font-size: 0.85rem; color: #e2e8f0; vertical-align: top; }
        .td-details { max-width: 300px; white-space: normal; color: #94a3b8; }
        .td-table { font-family: monospace; color: #a78bfa; }
        .td-ip { font-family: monospace; color: #64748b; font-size: 0.75rem; }

        /* Badges */
        .badge { padding: 4px 8px; border-radius: 4px; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; display: inline-block; letter-spacing: 0.5px;}
        .b-add { background: rgba(34, 197, 94, 0.1); color: #4ade80; border: 1px solid rgba(34, 197, 94, 0.2); }
        .b-edit { background: rgba(59, 130, 246, 0.1); color: #60a5fa; border: 1px solid rgba(59, 130, 246, 0.2); }
        .b-del { background: rgba(239, 68, 68, 0.1); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.2); }
        .b-auth { background: rgba(168, 85, 247, 0.1); color: #c084fc; border: 1px solid rgba(168, 85, 247, 0.2); }
        .b-def { background: rgba(148, 163, 184, 0.1); color: #cbd5e1; border: 1px solid rgba(148, 163, 184, 0.2); }

        /* Pagination Styles */
        .pagination { display: flex; justify-content: center; align-items: center; gap: 10px; margin-top: 1rem; }
        .page-link { padding: 8px 16px; border-radius: 8px; background: rgba(30, 41, 59, 0.6); border: 1px solid #334155; color: #cbd5e1; text-decoration: none; font-size: 0.9rem; transition: all 0.2s; }
        .page-link:hover { background: #334155; color: white; }
        .page-link.active { background: #3b82f6; border-color: #3b82f6; color: white; }
        .page-link.disabled { opacity: 0.5; pointer-events: none; }
        .page-info { color: #94a3b8; font-size: 0.9rem; margin: 0 10px; }

        @media (max-width: 768px) {
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .filter-grid { grid-template-columns: 1fr; }
            .action-bar { flex-direction: column; }
            .action-bar .btn { width: 100%; }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <h1 class="title">System Audit Logs</h1>
        <p class="subtitle">Security trail of user actions, logins, and data modifications.</p>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-lbl">Total Logs</div>
            <div class="stat-val"><?= number_format($total_records) ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-lbl">Page</div>
            <div class="stat-val text-blue"><?= number_format($page_num) ?> <span style="font-size:1rem; color:#64748b">/ <?= number_format($total_pages) ?></span></div>
        </div>
        </div>

    <div class="filter-box">
        <form method="GET">
            <div class="filter-grid">
                <div class="form-group">
                    <label>Log Date Range</label>
                    <div style="display: flex; gap: 5px;">
                        <input type="date" name="date_from" class="form-input" value="<?= htmlspecialchars($date_from) ?>">
                        <input type="date" name="date_to" class="form-input" value="<?= htmlspecialchars($date_to) ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>User</label>
                    <select name="user" class="form-input">
                        <option value="">All Users</option>
                        <?php foreach($users_list as $u): ?>
                            <option value="<?= $u ?>" <?= $user_filter == $u ? 'selected':'' ?>><?= htmlspecialchars($u) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Action Type</label>
                    <select name="action" class="form-input">
                        <option value="">All Actions</option>
                        <?php foreach($actions_list as $a): ?>
                            <option value="<?= $a ?>" <?= $action_filter == $a ? 'selected':'' ?>><?= htmlspecialchars($a) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Search Details</label>
                    <input type="text" name="search" class="form-input" placeholder="e.g. Deleted ID 55" value="<?= htmlspecialchars($search_term) ?>">
                </div>
                
                <div class="btn-group">
                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                    <a href="audit_log_report.php" class="btn btn-outline">Reset</a>
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
                    <th>Log ID</th>
                    <th>Date & Time</th>
                    <th>User</th>
                    <th>Action</th>
                    <th>Target Table</th>
                    <th>Details</th>
                    <th>IP Address</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($logs)): ?>
                    <tr><td colspan="7" style="text-align:center; padding:3rem; color:#64748b;">No audit logs found matching criteria.</td></tr>
                <?php else: ?>
                    <?php foreach($logs as $log): 
                        $act = strtoupper($log['ACTION_TYPE']);
                        $badgeClass = 'b-def';
                        if (strpos($act, 'ADD') !== false || strpos($act, 'CREATE') !== false) $badgeClass = 'b-add';
                        if (strpos($act, 'EDIT') !== false || strpos($act, 'UPDATE') !== false || strpos($act, 'CHANGE') !== false) $badgeClass = 'b-edit';
                        if (strpos($act, 'DELETE') !== false || strpos($act, 'REMOVE') !== false) $badgeClass = 'b-del';
                        if (strpos($act, 'LOGIN') !== false || strpos($act, 'AUTH') !== false) $badgeClass = 'b-auth';
                    ?>
                    <tr>
                        <td style="color:#64748b; font-family:monospace;"><?= $log['LOG_ID'] ?></td>
                        <td style="white-space:nowrap;"><?= $log['LOG_DATE'] ?></td>
                        <td style="font-weight:bold; color:#fff;">
                            <?= htmlspecialchars($log['USERNAME'] ?? 'System/Guest') ?>
                            <div style="font-weight:normal; font-size:0.75rem; color:#64748b;">ID: <?= $log['USER_ID'] ?? '-' ?></div>
                        </td>
                        <td><span class="badge <?= $badgeClass ?>"><?= $log['ACTION_TYPE'] ?></span></td>
                        <td class="td-table"><?= htmlspecialchars($log['TABLE_NAME']) ?></td>
                        <td class="td-details"><?= htmlspecialchars($log['ACTION_DETAILS']) ?></td>
                        <td class="td-ip"><?= htmlspecialchars($log['IP_ADDRESS']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($total_pages > 1): ?>
    <div class="pagination">
        <a href="?<?= getQueryUrl($page_num - 1, $_GET) ?>" class="page-link <?= ($page_num <= 1) ? 'disabled' : '' ?>">
            ← Prev
        </a>

        <span class="page-info">
            Page <?= $page_num ?> of <?= $total_pages ?> 
            <small>(<?= number_format($total_records) ?> Records)</small>
        </span>

        <a href="?<?= getQueryUrl($page_num + 1, $_GET) ?>" class="page-link <?= ($page_num >= $total_pages) ? 'disabled' : '' ?>">
            Next →
        </a>
    </div>
    <?php endif; ?>

</div>

<script>
    const jsPDF = window.jspdf.jsPDF;
    // Only current page data for client-side export
    const records = <?php echo json_encode($logs); ?>;
    
    // --- PDF Export ---
    function exportPDF() {
        const doc = new jsPDF('landscape');
        
        doc.setFontSize(18);
        doc.setTextColor(148, 163, 184);
        doc.text("System Audit Log Report - Page <?= $page_num ?>", 14, 15);
        
        doc.setFontSize(10);
        doc.setTextColor(100);
        const dateStr = new Date().toLocaleString();
        doc.text(`Generated: ${dateStr}`, 14, 22);

        const rows = records.map(r => [
            r.LOG_ID,
            r.LOG_DATE,
            r.USERNAME || 'Guest',
            r.ACTION_TYPE,
            r.TABLE_NAME,
            r.ACTION_DETAILS,
            r.IP_ADDRESS
        ]);

        doc.autoTable({
            head: [['ID', 'Date', 'User', 'Action', 'Table', 'Details', 'IP']],
            body: rows,
            startY: 30,
            styles: { fontSize: 7, overflow: 'linebreak', cellWidth: 'wrap' },
            columnStyles: { 5: { cellWidth: 80 } },
            headStyles: { fillColor: [71, 85, 105] }
        });

        doc.save('Audit_Log_Page_<?= $page_num ?>.pdf');
    }

    // --- Excel Export ---
    function exportExcel() {
        const excelData = records.map(r => ({
            'Log ID': r.LOG_ID,
            'Date': r.LOG_DATE,
            'User ID': r.USER_ID,
            'Username': r.USERNAME,
            'Action Type': r.ACTION_TYPE,
            'Table Affected': r.TABLE_NAME,
            'Details': r.ACTION_DETAILS,
            'IP Address': r.IP_ADDRESS
        }));

        const ws = XLSX.utils.json_to_sheet(excelData);
        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, "Audit Logs");
        XLSX.writeFile(wb, "Audit_Log_Page_<?= $page_num ?>.xlsx");
    }

    // --- CSV Export ---
    function exportCSV() {
        let csvContent = "data:text/csv;charset=utf-8,";
        csvContent += "ID,Date,User,Action,Table,Details,IP\n";
        
        records.forEach(r => {
            const row = [
                r.LOG_ID, r.LOG_DATE, r.USERNAME, r.ACTION_TYPE, 
                r.TABLE_NAME, r.ACTION_DETAILS, r.IP_ADDRESS
            ].map(e => `"${(e || '').toString().replace(/"/g, '""')}"`).join(","); 
            csvContent += row + "\n";
        });

        const encodedUri = encodeURI(csvContent);
        const link = document.createElement("a");
        link.setAttribute("href", encodedUri);
        link.setAttribute("download", "Audit_Log_Page_<?= $page_num ?>.csv");
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
</script>

</body>
</html>