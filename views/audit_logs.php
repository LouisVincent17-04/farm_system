<?php
// views/audit_logs.php
$page = "audit_logs"; 
include '../config/Connection.php';

include '../security/checkAccess.php';
checkAccess('audit_logs');
include '../common/navbar.php';
include '../common/chat_support.php';

// Handle empty submissions by defaulting to the 30-day window
$start_date = !empty($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$end_date   = !empty($_GET['end_date'])   ? $_GET['end_date']   : date('Y-m-d');
$search     = $_GET['search']     ?? '';

try {
    if (!isset($conn)) {
        throw new Exception("Database connection failed.");
    }

    $sql = "SELECT 
                LOG_ID, USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS,
                DATE_FORMAT(LOG_DATE, '%m/%d/%Y') as LOG_DATE_FMT,
                DATE_FORMAT(LOG_DATE, '%h:%i:%s %p') as LOG_TIME_FMT,
                DATE_FORMAT(LOG_DATE, '%m/%d/%Y %h:%i %p') as FULL_DATE_FMT 
            FROM AUDIT_LOGS 
            WHERE LOG_DATE BETWEEN :start_dt AND :end_dt";

    $params = [
        ':start_dt' => $start_date . ' 00:00:00',
        ':end_dt'   => $end_date . ' 23:59:59'
    ];

    if (!empty($search)) {
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no"> 
    <title>Audit Logs | System History | FarmPro</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=DM+Mono:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" type="text/css" href="https://npmcdn.com/flatpickr/dist/themes/dark.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    <style>
        /* ─── CSS VARIABLES ─── */
        :root {
            --bg-base:        #080f1a;
            --bg-surface:     #0d1829;
            --bg-elevated:    #111f35;
            --bg-hover:       #162540;
            --border:         rgba(255,255,255,0.07);
            
            /* Red Theme for Audits */
            --red:            #ef4444;
            --red-dim:        rgba(239,68,68,0.12);
            --red-glow:       rgba(239,68,68,0.25);
            --red-border:     rgba(239,68,68,0.5);

            --emerald:        #10b981;
            --blue:           #3b82f6;
            --purple:         #a855f7;
            
            --text-primary:   #f1f5f9;
            --text-secondary: #94a3b8;
            --text-muted:     #475569;
            
            --radius-md:      10px;
            --radius-lg:      14px;
            --radius-xl:      20px;
            --shadow-md:      0 4px 16px rgba(0,0,0,0.4);
            --font:           'DM Sans', system-ui, sans-serif;
            --font-mono:      'DM Mono', monospace;
            --transition:     0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* ─── RESET & BASE ─── */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: var(--font);
            background: var(--bg-base);
            color: var(--text-primary);
            min-height: 100vh;
            padding-bottom: 60px;
            background-image: radial-gradient(ellipse 80% 50% at 50% -20%, rgba(239,68,68,0.06) 0%, transparent 60%);
        }
        .container { max-width: 1400px; margin: 0 auto; padding: 2rem 1.5rem; }

        /* ─── HEADER ─── */
        .page-header { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 2rem; gap: 1.5rem; flex-wrap: wrap; }
        .header-info h1 {
            font-size: clamp(1.8rem, 3vw, 2.5rem); font-weight: 700;
            color: var(--text-primary); letter-spacing: -0.03em; line-height: 1.1; margin-bottom: 0.25rem;
        }
        .header-info h1 span {
            background: linear-gradient(135deg, var(--red), #991b1b);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }
        .header-info p { color: var(--text-secondary); font-size: 0.95rem; }

        /* ─── FILTERS ─── */
        .filters-bar {
            display: flex; gap: 1.25rem; margin-bottom: 2rem; background: var(--bg-surface);
            padding: 1.5rem; border-radius: var(--radius-xl); align-items: flex-end; border: 1px solid var(--border);
            flex-wrap: wrap; box-shadow: var(--shadow-md);
        }
        .filter-group { display: flex; flex-direction: column; gap: 8px; flex: 1; min-width: 200px; }
        .filter-group label { color: var(--text-secondary); font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; }
        .filter-input {
            background: var(--bg-elevated); border: 1px solid var(--border); color: var(--text-primary);
            padding: 12px 16px; border-radius: var(--radius-md); width: 100%; box-sizing: border-box;
            outline: none; font-family: var(--font); font-size: 0.95rem; transition: all var(--transition);
        }
        .filter-input:focus { border-color: var(--red); box-shadow: 0 0 0 3px var(--red-glow); }
        .filter-input::placeholder { color: var(--text-muted); }

        .btn-filter {
            padding: 12px 24px; background: var(--red); color: #fff; border: none; border-radius: var(--radius-md);
            font-weight: 700; cursor: pointer; height: 46px; min-width: 140px; font-family: var(--font); font-size: 0.95rem;
            transition: all var(--transition); display: flex; align-items: center; justify-content: center; gap: 8px;
        }
        .btn-filter:hover { background: #b91c1c; box-shadow: 0 0 16px var(--red-glow); transform: translateY(-1px); }

        /* ─── TABLE ─── */
        .table-card {
            background: var(--bg-surface); border: 1px solid var(--border);
            border-radius: var(--radius-xl); overflow: hidden;
            box-shadow: 0 10px 30px -10px rgba(0,0,0,0.5);
        }
        .table-wrap { overflow-x: auto; }
        .table { width: 100%; border-collapse: collapse; min-width: 1000px; }
        .table thead th {
            background: var(--bg-elevated); color: var(--text-muted);
            font-size: 0.7rem; font-weight: 700; text-transform: uppercase;
            letter-spacing: 0.07em; padding: 14px 16px; text-align: left;
            border-bottom: 1px solid var(--border); white-space: nowrap;
        }
        .table tbody tr { border-bottom: 1px solid var(--border); transition: background var(--transition); }
        .table tbody tr:last-child { border-bottom: none; }
        .table tbody tr:hover { background: rgba(255,255,255,0.02); }
        .table td { padding: 14px 16px; font-size: 0.9rem; color: var(--text-primary); vertical-align: top; }

        /* Typography inside table */
        .log-date { color: #fff; font-weight: 600; font-family: var(--font-mono); }
        .log-meta { font-size: 0.8rem; color: var(--text-muted); margin-top: 4px; font-family: var(--font-mono); }
        .log-user { font-weight: 700; color: var(--text-primary); }
        .log-target { color: var(--red); font-weight: 600; font-family: var(--font-mono); font-size: 0.85rem;}
        .log-details { font-size: 0.85rem; color: var(--text-secondary); line-height: 1.5; max-width: 350px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

        /* Action Badges */
        .action-badge {
            padding: 4px 10px; border-radius: 6px; font-size: 0.7rem; font-weight: 700;
            text-transform: uppercase; display: inline-flex; align-items: center; justify-content: center;
            min-width: 80px; letter-spacing: 0.05em; font-family: var(--font-mono);
        }
        .act-insert { background: rgba(16, 185, 129, 0.1); color: var(--emerald); border: 1px solid rgba(16, 185, 129, 0.25); }
        .act-update { background: rgba(59, 130, 246, 0.1); color: #60a5fa; border: 1px solid rgba(59, 130, 246, 0.25); }
        .act-delete { background: rgba(239, 68, 68, 0.1); color: var(--red); border: 1px solid rgba(239, 68, 68, 0.25); }
        .act-login  { background: rgba(168, 85, 247, 0.1); color: #c084fc; border: 1px solid rgba(168, 85, 247, 0.25); }
        .act-google { background: rgba(66, 133, 244, 0.1); color: #93c5fd; border: 1px solid rgba(66, 133, 244, 0.25); }

        .btn-view {
            background: var(--bg-elevated); border: 1px solid var(--border); color: var(--text-secondary);
            width: 32px; height: 32px; border-radius: 6px; display: inline-flex; align-items: center; justify-content: center;
            cursor: pointer; transition: all var(--transition);
        }
        .btn-view:hover { background: var(--bg-hover); color: var(--red); border-color: var(--red); transform: translateY(-2px); }

        /* ─── MODALS ─── */
        .modal {
            display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.85);
            backdrop-filter: blur(5px); z-index: 1000; align-items: center; justify-content: center;
            padding: 1rem; box-sizing: border-box;
        }
        .modal.show { display: flex; }
        
        .modal-content {
            background: var(--bg-surface); border: 1px solid var(--border);
            border-radius: var(--radius-xl); width: 100%; max-width: 600px;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5); display: flex; flex-direction: column;
            max-height: 90vh; animation: modalZoom 0.2s ease-out;
        }
        @keyframes modalZoom { from { transform: scale(0.95); opacity: 0; } to { transform: scale(1); opacity: 1; } }

        .modal-header { padding: 1.5rem; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
        .modal-header h3 { margin: 0; font-size: 1.25rem; font-weight: 700; color: #fff; }
        .btn-close { background: transparent; border: none; color: var(--text-muted); cursor: pointer; font-size: 1.5rem; transition: color var(--transition); }
        .btn-close:hover { color: var(--red); }
        
        .modal-body { padding: 1.5rem; overflow-y: auto; display: flex; flex-direction: column; gap: 1.25rem;}
        
        .detail-row { display: flex; flex-direction: column; gap: 6px; }
        .detail-label { color: var(--text-muted); font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; }
        .detail-value {
            color: var(--text-primary); font-size: 0.95rem; background: var(--bg-elevated);
            padding: 1rem; border-radius: var(--radius-md); border: 1px solid var(--border);
            word-wrap: break-word; font-family: var(--font-mono); line-height: 1.5;
        }
        
        .modal-footer { padding: 1.25rem 1.5rem; border-top: 1px solid var(--border); text-align: right; background: var(--bg-elevated); border-radius: 0 0 var(--radius-xl) var(--radius-xl);}
        .btn-done {
            padding: 10px 24px; background: var(--red); color: #fff; border: none; border-radius: var(--radius-md);
            font-weight: 700; cursor: pointer; font-family: var(--font); font-size: 0.95rem; transition: all var(--transition);
        }
        .btn-done:hover { background: #b91c1c; }

        /* ─── RESPONSIVE ─── */
        @media (max-width: 768px) {
            .container { padding: 1rem; }
            .filters-bar { flex-direction: column; align-items: stretch; gap: 1rem; padding: 1rem;}
            .btn-filter { width: 100%; }

            /* Table to Cards */
            .table-wrap { border: none; background: transparent; overflow: visible; box-shadow: none; }
            .table thead { display: none; }
            .table tbody, .table tr, .table td { display: block; width: 100%; box-sizing: border-box; }
            
            .table tr {
                background: var(--bg-surface); border: 1px solid var(--border);
                border-radius: var(--radius-xl); margin-bottom: 1rem; padding: 1.25rem;
                box-shadow: var(--shadow-md);
            }
            .table td {
                display: flex; justify-content: space-between; align-items: center; text-align: right;
                padding: 0.6rem 0; border-bottom: 1px solid rgba(255,255,255,0.05);
            }
            .table td:last-child { border-bottom: none; justify-content: flex-end; padding-top: 1rem; }
            
            .table td::before {
                content: attr(data-label); font-weight: 700; color: var(--text-muted);
                font-size: 0.75rem; text-transform: uppercase; margin-right: 1rem; text-align: left;
            }

            .log-details { white-space: normal; max-width: 200px; font-size: 0.85rem; text-align: right;}
        }
    </style>
</head>
<body>

<div class="container">

    <div class="page-header">
        <div class="header-info">
            <h1>Audit <span>Logs</span></h1>
            <p>Immutable system history and user action tracking.</p>
        </div>
    </div>

    <form class="filters-bar" method="GET">
        <div class="filter-group">
            <label>Search Query</label>
            <input type="text" name="search" class="filter-input" placeholder="User, Table, or Event..." value="<?php echo htmlspecialchars($search); ?>">
        </div>
        <div class="filter-group">
            <label>Date Range (Start)</label>
            <input type="text" name="start_date" id="start_date" class="filter-input" value="<?php echo htmlspecialchars($start_date); ?>" placeholder="Select Start">
        </div>
        <div class="filter-group">
            <label>Date Range (End)</label>
            <input type="text" name="end_date" id="end_date" class="filter-input" value="<?php echo htmlspecialchars($end_date); ?>" placeholder="Select End">
        </div>
        <button type="submit" class="btn-filter"><i class="fa-solid fa-filter"></i> Apply Filters</button>
    </form>

    <div class="table-card">
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Timestamp</th>
                        <th>User &amp; IP</th>
                        <th>Event Type</th>
                        <th>Target System</th>
                        <th>Action Summary</th>
                        <th style="text-align: center;">View</th> 
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr><td colspan="6" style="text-align:center; padding: 4rem 2rem; color: var(--text-muted);">No log entries found for this criteria.</td></tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): 
                            // Determine Badge Style
                            $act = strtoupper($log['ACTION_TYPE']);
                            $badgeClass = 'act-insert'; // Default
                            if (strpos($act, 'UPDATE') !== false) $badgeClass = 'act-update';
                            if (strpos($act, 'DELETE') !== false) $badgeClass = 'act-delete';
                            if (strpos($act, 'LOGIN') !== false) $badgeClass = 'act-login';
                            if (strpos($act, 'GOOGLE') !== false) $badgeClass = 'act-google';
                            
                            $logJson = htmlspecialchars(json_encode($log), ENT_QUOTES, 'UTF-8');
                        ?>
                        <tr data-json='<?php echo $logJson; ?>'>
                            <td data-label="Timestamp">
                                <div class="log-date"><?php echo htmlspecialchars($log['LOG_DATE_FMT']); ?></div>
                                <div class="log-meta"><?php echo htmlspecialchars($log['LOG_TIME_FMT']); ?></div>
                            </td>
                            <td data-label="User & IP">
                                <div class="log-user"><?php echo htmlspecialchars($log['USERNAME']); ?></div>
                                <div class="log-meta">IP: <?php echo htmlspecialchars($log['IP_ADDRESS'] ?? 'Unknown'); ?></div>
                            </td>
                            <td data-label="Event Type">
                                <span class="action-badge <?php echo $badgeClass; ?>">
                                    <?php echo htmlspecialchars($log['ACTION_TYPE']); ?>
                                </span>
                            </td>
                            <td data-label="Target System">
                                <div class="log-target"><?php echo htmlspecialchars($log['TABLE_NAME']); ?></div>
                            </td>
                            <td data-label="Action Summary">
                                <div class="log-details">
                                    <?php echo htmlspecialchars($log['ACTION_DETAILS']); ?>
                                </div>
                            </td>
                            <td data-label="View" style="text-align: center;">
                                <button onclick="viewLog(this)" class="btn-view" title="Inspect Record">
                                    <i class="fa-solid fa-magnifying-glass"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<div id="logModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fa-solid fa-file-shield me-2" style="color:var(--red);"></i> Audit Record</h3>
            <button class="btn-close" onclick="closeModal()"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body" id="modalBody">
            </div>
        <div class="modal-footer">
            <button class="btn-done" onclick="closeModal()">Close Inspector</button>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        flatpickr("#start_date", {
            dateFormat: "Y-m-d", 
            altInput: true,      
            altFormat: "M d, Y", 
            allowInput: true
        });
        
        flatpickr("#end_date", {
            dateFormat: "Y-m-d", 
            altInput: true,      
            altFormat: "M d, Y", 
            allowInput: true
        });
    });

    function viewLog(btn) {
        const row = btn.closest('tr');
        const data = JSON.parse(row.getAttribute('data-json'));
        
        // Define color based on action for the modal display
        let actionColor = 'var(--emerald)';
        const act = data.ACTION_TYPE.toUpperCase();
        if (act.includes('UPDATE')) actionColor = '#60a5fa';
        if (act.includes('DELETE')) actionColor = 'var(--red)';
        if (act.includes('LOGIN')) actionColor = '#c084fc';
        if (act.includes('GOOGLE')) actionColor = '#93c5fd';

        const html = `
            <div class="detail-row">
                <span class="detail-label">Origin &amp; Identity</span>
                <div class="detail-value">
                    <span style="color:#fff;">${data.USERNAME}</span> <br>
                    <span style="color:var(--text-muted); font-size:0.85rem;">IP Address: ${data.IP_ADDRESS}</span>
                </div>
            </div>
            <div class="detail-row">
                <span class="detail-label">Event Type</span>
                <div class="detail-value" style="color:${actionColor}; font-weight:700; font-family:var(--font);">${data.ACTION_TYPE}</div>
            </div>
            <div class="detail-row">
                <span class="detail-label">Target System / Table</span>
                <div class="detail-value" style="color:var(--red);">${data.TABLE_NAME}</div>
            </div>
            <div class="detail-row">
                <span class="detail-label">Execution Timestamp</span>
                <div class="detail-value">${data.FULL_DATE_FMT}</div>
            </div>
            <div class="detail-row" style="flex-grow: 1;">
                <span class="detail-label">Payload / Transaction Details</span>
                <div class="detail-value" style="min-height: 100px; white-space: pre-wrap;">${data.ACTION_DETAILS}</div>
            </div>
        `;
        
        document.getElementById('modalBody').innerHTML = html;
        document.getElementById('logModal').classList.add('show');
    }

    function closeModal() {
        document.getElementById('logModal').classList.remove('show');
    }

    // Close modal when clicking outside
    window.onclick = function(e) {
        if (e.target == document.getElementById('logModal')) {
            closeModal();
        }
    }
</script>
</body>
</html>