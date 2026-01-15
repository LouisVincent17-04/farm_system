<?php
// views/audit_logs.php
$page = "audit_logs"; 
include '../common/navbar.php';
include '../config/Connection.php';
// include '../config/Queries.php'; // Not needed for direct PDO

include '../security/checkRole.php';    
checkRole(3);

$start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$end_date   = $_GET['end_date']   ?? date('Y-m-d');
$search     = $_GET['search']     ?? '';

try {
    if (!isset($conn)) {
        throw new Exception("Database connection failed.");
    }

    // 1. Build Query
    // MySQL: Use DATE_FORMAT for date string, LIMIT for row restriction
    $sql = "SELECT 
                LOG_ID, USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS,
                DATE_FORMAT(LOG_DATE, '%Y-%m-%d %H:%i:%s') as LOG_DATE_FMT 
            FROM AUDIT_LOGS 
            WHERE LOG_DATE BETWEEN :start_dt AND :end_dt";

    $params = [
        ':start_dt' => $start_date . ' 00:00:00',
        ':end_dt'   => $end_date . ' 23:59:59' // Include the full end day
    ];

    if (!empty($search)) {
        // MySQL: Use standard parameter binding for LIKE
        $sql .= " AND (USERNAME LIKE :search 
                  OR ACTION_TYPE LIKE :search 
                  OR TABLE_NAME LIKE :search
                  OR ACTION_DETAILS LIKE :search)";
        $params[':search'] = '%' . $search . '%';
    }

    $sql .= " ORDER BY LOG_DATE DESC LIMIT 100";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $logs = [];
    echo "<script>console.error('Database Error: " . addslashes($e->getMessage()) . "');</script>";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Audit Logs | System History</title>
    <link rel="stylesheet" href="../css/purch_housing_facilities.css">
    <style>
        /* Styles remain identical */
        .filters-bar {
            display: flex; gap: 1rem; margin-bottom: 2rem; background: rgba(30, 41, 59, 0.6);
            padding: 1rem; border-radius: 12px; align-items: end; border: 1px solid #475569;
        }
        .filter-group { display: flex; flex-direction: column; gap: 0.5rem; flex: 1; }
        .filter-group label { color: #94a3b8; font-size: 0.85rem; font-weight: 600; }
        .filter-input { background: #1e293b; border: 1px solid #475569; color: white; padding: 0.7rem; border-radius: 8px; width: 100%; }
        .btn-filter { padding: 0.7rem 1.5rem; background: linear-gradient(135deg, #3b82f6, #2563eb); color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; height: 42px; }

        /* Action Badges */
        .action-badge { padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 700; text-transform: uppercase; display: inline-block; min-width: 80px; text-align: center; }
        
        .act-insert { background: rgba(16, 185, 129, 0.2); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.3); }
        .act-update { background: rgba(59, 130, 246, 0.2); color: #60a5fa; border: 1px solid rgba(59, 130, 246, 0.3); }
        .act-delete { background: rgba(239, 68, 68, 0.2); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.3); }
        
        .act-login  { background: rgba(168, 85, 247, 0.2); color: #c084fc; border: 1px solid rgba(168, 85, 247, 0.3); }
        .act-google { background: rgba(66, 133, 244, 0.2); color: #93c5fd; border: 1px solid rgba(66, 133, 244, 0.3); }

        .log-details { font-size: 0.9rem; color: #cbd5e1; line-height: 1.4; max-width: 350px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .log-meta { font-size: 0.8rem; color: #64748b; margin-top: 2px; }

        /* Modal Styles */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); backdrop-filter: blur(4px); z-index: 1000; align-items: center; justify-content: center; }
        .modal.show { display: flex; }
        .modal-content { background: #1e293b; border-radius: 12px; width: 100%; max-width: 500px; padding: 0; border: 1px solid #475569; }
        .modal-header { padding: 1.5rem; border-bottom: 1px solid #475569; display: flex; justify-content: space-between; align-items: center; }
        .modal-body { padding: 1.5rem; }
        .modal-footer { padding: 1rem; border-top: 1px solid #475569; text-align: right; }
        .detail-row { margin-bottom: 1rem; }
        .detail-label { color: #94a3b8; font-size: 0.85rem; display: block; margin-bottom: 0.25rem; }
        .detail-value { color: #e2e8f0; font-size: 1rem; background: rgba(0,0,0,0.2); padding: 0.75rem; border-radius: 6px; border: 1px solid #334155; }
        .btn-close { background: transparent; border: none; color: #cbd5e1; cursor: pointer; }
        .btn-done { background: #3b82f6; color: white; border: none; padding: 0.5rem 1rem; border-radius: 6px; cursor: pointer; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="header-info">
                <h1>Audit Logs</h1>
                <p>Track system activity and user actions</p>
            </div>
        </div>

        <form class="filters-bar" method="GET">
            <div class="filter-group">
                <label>Search Keyword</label>
                <input type="text" name="search" class="filter-input" placeholder="User, Table, or ID..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="filter-group">
                <label>Start Date</label>
                <input type="date" name="start_date" class="filter-input" value="<?php echo $start_date; ?>">
            </div>
            <div class="filter-group">
                <label>End Date</label>
                <input type="date" name="end_date" class="filter-input" value="<?php echo $end_date; ?>">
            </div>
            <button type="submit" class="btn-filter">Filter Logs</button>
        </form>

        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Date & Time</th>
                        <th>User</th>
                        <th>Action</th>
                        <th>Target</th>
                        <th>Details</th>
                        <th style="text-align: center;">View</th> </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr><td colspan="6" style="text-align:center; padding: 3rem; color: #64748b;">No logs found for this period.</td></tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): 
                            // Determine Badge Style
                            $act = strtoupper($log['ACTION_TYPE']);
                            $badgeClass = 'act-insert'; // Default
                            if (strpos($act, 'UPDATE') !== false) $badgeClass = 'act-update';
                            if (strpos($act, 'DELETE') !== false) $badgeClass = 'act-delete';
                            if (strpos($act, 'LOGIN') !== false) $badgeClass = 'act-login';
                            if (strpos($act, 'GOOGLE') !== false) $badgeClass = 'act-google';
                            
                            // FIX: Escape JSON for HTML attribute safely
                            $logJson = htmlspecialchars(json_encode($log), ENT_QUOTES, 'UTF-8');
                        ?>
                        <tr data-json='<?php echo $logJson; ?>'>
                            <td>
                                <div style="color:white; font-weight:500;"><?php echo date('M d, Y', strtotime($log['LOG_DATE_FMT'])); ?></div>
                                <div class="log-meta"><?php echo date('h:i:s A', strtotime($log['LOG_DATE_FMT'])); ?></div>
                            </td>
                            <td>
                                <div style="font-weight:600; color:#e2e8f0;"><?php echo htmlspecialchars($log['USERNAME']); ?></div>
                                <div class="log-meta">IP: <?php echo htmlspecialchars($log['IP_ADDRESS'] ?? 'Unknown'); ?></div>
                            </td>
                            <td>
                                <span class="action-badge <?php echo $badgeClass; ?>">
                                    <?php echo htmlspecialchars($log['ACTION_TYPE']); ?>
                                </span>
                            </td>
                            <td>
                                <div style="color:#93c5fd; font-weight:600;"><?php echo htmlspecialchars($log['TABLE_NAME']); ?></div>
                            </td>
                            <td>
                                <div class="log-details">
                                    <?php echo htmlspecialchars($log['ACTION_DETAILS']); ?>
                                </div>
                            </td>
                            <td style="text-align: center;">
                                <button onclick="viewLog(this)" style="background: transparent; border: none; color: #cbd5e1; cursor: pointer;">
                                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div id="logModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 style="margin:0; color:#fff;">Log Details</h3>
                <button class="btn-close" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body" id="modalBody">
                </div>
            <div class="modal-footer">
                <button class="btn-done" onclick="closeModal()">Close</button>
            </div>
        </div>
    </div>

    <script>
        function viewLog(btn) {
            const row = btn.closest('tr');
            // This safely parses the escaped JSON
            const data = JSON.parse(row.getAttribute('data-json'));
            
            const html = `
                <div class="detail-row">
                    <span class="detail-label">User / IP</span>
                    <div class="detail-value">${data.USERNAME} <small style="color:#94a3b8">(${data.IP_ADDRESS})</small></div>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Action</span>
                    <div class="detail-value" style="color:#93c5fd">${data.ACTION_TYPE}</div>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Target</span>
                    <div class="detail-value">${data.TABLE_NAME}</div>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Full Details</span>
                    <div class="detail-value" style="min-height: 80px;">${data.ACTION_DETAILS}</div>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Timestamp</span>
                    <div class="detail-value">${data.LOG_DATE_FMT}</div>
                </div>
            `;
            
            document.getElementById('modalBody').innerHTML = html;
            document.getElementById('logModal').classList.add('show');
        }

        function closeModal() {
            document.getElementById('logModal').classList.remove('show');
        }

        window.onclick = function(e) {
            if (e.target == document.getElementById('logModal')) {
                closeModal();
            }
        }
    </script>
</body>
</html>