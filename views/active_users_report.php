<?php
// reports/active_user_report.php
error_reporting(0);
ini_set('display_errors', 0);
$page = "reports";

include '../common/navbar.php';
include '../config/Connection.php';
include '../security/checkRole.php';
checkRole(1); // Admin Only

// --- 1. GET FILTER INPUTS ---
$user_type = $_GET['user_type'] ?? '';
$is_active = $_GET['is_active'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to   = $_GET['date_to'] ?? '';

try {
    if (!isset($conn)) { throw new Exception("Database connection failed."); }

    // --- 2. BUILD SQL QUERY ---
    $sql = "SELECT 
        u.USER_ID,
        u.FULL_NAME,
        u.EMAIL,
        u.CONTACT_INFO,
        u.IS_ACTIVE,
        ut.USER_TYPE_NAME,
        DATE_FORMAT(u.CREATED_AT, '%Y-%m-%d %H:%i') AS CREATED_AT,
        DATE_FORMAT(u.DATE_UPDATED, '%Y-%m-%d %H:%i') AS DATE_UPDATED
    FROM USERS u
    LEFT JOIN USER_TYPES ut ON u.USER_TYPE = ut.USER_TYPE_ID
    WHERE 1=1";

    $params = [];

    // Apply Filters
    if ($user_type !== '') {
        $sql .= " AND u.USER_TYPE = :user_type";
        $params[':user_type'] = $user_type;
    }

    if ($is_active !== '') {
        $sql .= " AND u.IS_ACTIVE = :is_active";
        $params[':is_active'] = $is_active;
    }

    if ($date_from && $date_to) {
        $sql .= " AND u.CREATED_AT BETWEEN :date_from AND :date_to";
        $params[':date_from'] = $date_from . ' 00:00:00';
        $params[':date_to']   = $date_to . ' 23:59:59';
    }

    $sql .= " ORDER BY u.CREATED_AT DESC";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // --- 3. CALCULATE STATISTICS ---
    $total_users = count($users);
    $total_active = 0;
    $total_inactive = 0;
    
    // Count Roles for Stats
    $role_counts = [];

    foreach ($users as $row) {
        if ($row['IS_ACTIVE'] == 1) $total_active++;
        else $total_inactive++;

        // Track Role Distribution
        $role = $row['USER_TYPE_NAME'] ?? 'Unknown';
        if(!isset($role_counts[$role])) $role_counts[$role] = 0;
        $role_counts[$role]++;
    }

    // --- 4. FETCH DROPDOWNS ---
    $user_types = $conn->query("SELECT USER_TYPE_ID, USER_TYPE_NAME FROM USER_TYPES ORDER BY USER_TYPE_NAME")->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $users = [];
    error_log($e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Active User Report</title>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.31/jspdf.plugin.autotable.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

    <style>
        /* --- GLOBAL VARIABLES & RESET --- */
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
            background: linear-gradient(135deg, #3b82f6, #1d4ed8); 
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
        
        .text-green { color: #4ade80; } 
        .text-red { color: #f87171; }
        .text-blue { color: #60a5fa; }

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
            font-size: 0.9rem;
            box-sizing: border-box; 
        }
        .form-input:focus { border-color: #3b82f6; outline: none; }

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
        .btn-primary { background: #3b82f6; color: white; }
        .btn-outline { background: transparent; border: 1px solid #475569; color: #cbd5e1; }
        .btn-export { background: #10b981; color: white; } /* Green for Excel */
        .btn-pdf { background: #ef4444; color: white; } /* Red for PDF */
        .btn-csv { background: #f59e0b; color: #1e293b; } /* Orange for CSV */

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
        <h1 class="title">Active User Report</h1>
        <p class="subtitle">System access logs, role distribution, and account status.</p>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-lbl">Total Users</div>
            <div class="stat-val"><?= number_format($total_users) ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-lbl">Active Accounts</div>
            <div class="stat-val text-green"><?= number_format($total_active) ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-lbl">Inactive Accounts</div>
            <div class="stat-val text-red"><?= number_format($total_inactive) ?></div>
        </div>
    </div>

    <div class="filter-box">
        <form method="GET">
            <div class="filter-grid">
                <div class="form-group">
                    <label>Registration Date Range</label>
                    <div style="display: flex; gap: 5px;">
                        <input type="date" name="date_from" class="form-input" value="<?= htmlspecialchars($date_from) ?>">
                        <input type="date" name="date_to" class="form-input" value="<?= htmlspecialchars($date_to) ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>User Role</label>
                    <select name="user_type" class="form-input">
                        <option value="">All Roles</option>
                        <?php foreach($user_types as $t): ?>
                            <option value="<?= $t['USER_TYPE_ID'] ?>" <?= $user_type == $t['USER_TYPE_ID']?'selected':'' ?>>
                                <?= htmlspecialchars($t['USER_TYPE_NAME']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Account Status</label>
                    <select name="is_active" class="form-input">
                        <option value="">All Statuses</option>
                        <option value="1" <?= $is_active === '1' ? 'selected' : '' ?>>Active</option>
                        <option value="0" <?= $is_active === '0' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>
                
                <div class="btn-group">
                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                    <a href="active_user_report.php" class="btn btn-outline">Reset</a>
                </div>
            </div>
            
            <div class="action-bar">
                <button type="button" class="btn btn-pdf" onclick="exportPDF()">
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
                    <th>User ID</th>
                    <th>Full Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Contact</th>
                    <th>Status</th>
                    <th>Registered</th>
                    <th>Last Update</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($users)): ?>
                    <tr><td colspan="8" style="text-align:center; padding:3rem; color:#64748b;">No users found matching filters.</td></tr>
                <?php else: ?>
                    <?php foreach($users as $u): 
                        $statusClass = ($u['IS_ACTIVE'] == 1) ? 'b-active' : 'b-inactive';
                        $statusText = ($u['IS_ACTIVE'] == 1) ? 'Active' : 'Inactive';
                    ?>
                    <tr>
                        <td style="font-family:monospace; color:#64748b;"><?= $u['USER_ID'] ?></td>
                        <td style="font-weight:bold; color:#fff;"><?= htmlspecialchars($u['FULL_NAME']) ?></td>
                        <td><?= htmlspecialchars($u['EMAIL']) ?></td>
                        <td style="color:#60a5fa;"><?= htmlspecialchars($u['USER_TYPE_NAME']) ?></td>
                        <td><?= htmlspecialchars($u['CONTACT_INFO']) ?></td>
                        <td><span class="badge <?= $statusClass ?>"><?= $statusText ?></span></td>
                        <td><?= $u['CREATED_AT'] ?></td>
                        <td style="font-size:0.85rem; color:#64748b;"><?= $u['DATE_UPDATED'] ?? '-' ?></td>
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
    const records = <?php echo json_encode($users); ?>;
    
    // --- PDF Export ---
    function exportPDF() {
        const doc = new jsPDF('landscape');
        
        doc.setFontSize(18);
        doc.setTextColor(59, 130, 246); // Blue header
        doc.text("Active User Report", 14, 15);
        
        doc.setFontSize(10);
        doc.setTextColor(100);
        const dateStr = new Date().toLocaleString();
        doc.text(`Generated: ${dateStr}`, 14, 22);
        doc.text(`Total Users: <?= $total_users ?>`, 200, 22);

        const rows = records.map(r => [
            r.USER_ID,
            r.FULL_NAME,
            r.EMAIL,
            r.USER_TYPE_NAME || 'N/A',
            r.CONTACT_INFO || '-',
            r.IS_ACTIVE == 1 ? 'Active' : 'Inactive',
            r.CREATED_AT
        ]);

        doc.autoTable({
            head: [['ID', 'Name', 'Email', 'Role', 'Contact', 'Status', 'Registered']],
            body: rows,
            startY: 30,
            styles: { fontSize: 8 },
            headStyles: { fillColor: [59, 130, 246] }
        });

        doc.save('User_Report.pdf');
    }

    // --- Excel Export (SheetJS) ---
    function exportExcel() {
        const excelData = records.map(r => ({
            'User ID': r.USER_ID,
            'Full Name': r.FULL_NAME,
            'Email': r.EMAIL,
            'Role': r.USER_TYPE_NAME,
            'Contact': r.CONTACT_INFO,
            'Status': r.IS_ACTIVE == 1 ? 'Active' : 'Inactive',
            'Registered Date': r.CREATED_AT,
            'Last Update': r.DATE_UPDATED
        }));

        const ws = XLSX.utils.json_to_sheet(excelData);
        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, "Users");
        XLSX.writeFile(wb, "User_Report_" + new Date().toISOString().slice(0,10) + ".xlsx");
    }

    // --- CSV Export ---
    function exportCSV() {
        let csvContent = "data:text/csv;charset=utf-8,";
        csvContent += "ID,Name,Email,Role,Contact,Status,Registered\n";
        
        records.forEach(r => {
            const status = r.IS_ACTIVE == 1 ? 'Active' : 'Inactive';
            const row = [
                r.USER_ID, r.FULL_NAME, r.EMAIL, r.USER_TYPE_NAME, 
                r.CONTACT_INFO, status, r.CREATED_AT
            ].map(e => `"${e || ''}"`).join(","); 
            csvContent += row + "\n";
        });

        const encodedUri = encodeURI(csvContent);
        const link = document.createElement("a");
        link.setAttribute("href", encodedUri);
        link.setAttribute("download", "User_Report_" + new Date().toISOString().slice(0,10) + ".csv");
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
</script>

</body>
</html>