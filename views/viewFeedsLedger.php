<?php
// views/viewFeedLedger.php
error_reporting(E_ALL);
ini_set('display_errors', 0);

$page = "transactions";
include '../common/navbar.php';
include '../config/Connection.php';
include '../security/checkRole.php';
checkRole(2); // Farm Admin or higher

$feed_id = $_GET['feed_id'] ?? null;

if (!$feed_id) {
    header("Location: available_feeds.php");
    exit;
}

// --- FILTERS ---
$limit_val  = $_GET['limit'] ?? 10;
$limit_sql  = ($limit_val === 'all') ? "" : "LIMIT " . intval($limit_val);

$search_term = $_GET['search'] ?? '';
$start_date  = $_GET['start_date'] ?? '';
$end_date    = $_GET['end_date'] ?? '';

try {
    // 1. Fetch Feed Info
    $stmt = $conn->prepare("
        SELECT f.*, l.LOCATION_NAME 
        FROM FEEDS f 
        LEFT JOIN LOCATIONS l ON f.LOCATION_ID = l.LOCATION_ID 
        WHERE f.FEED_ID = ?
    ");
    $stmt->execute([$feed_id]);
    $feed = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$feed) throw new Exception("Feed ID not found.");

    // 2. Build Query with Filters
    $conditions = ["ft.FEED_ID = ?"];
    $params = [$feed_id];

    if (!empty($search_term)) {
        $conditions[] = "(ft.REMARKS LIKE ? OR ft.BATCH_ID LIKE ? OR a.TAG_NO LIKE ?)";
        $term = "%$search_term%";
        $params[] = $term; $params[] = $term; $params[] = $term;
    }

    if (!empty($start_date)) {
        $conditions[] = "DATE(ft.TRANSACTION_DATE) >= ?";
        $params[] = $start_date;
    }

    if (!empty($end_date)) {
        $conditions[] = "DATE(ft.TRANSACTION_DATE) <= ?";
        $params[] = $end_date;
    }

    $whereClause = implode(" AND ", $conditions);

    $histSql = "
        SELECT 
            ft.TRANSACTION_DATE, 
            ft.QUANTITY_KG, 
            ft.TRANSACTION_COST,
            ft.REMARKS,
            ft.BATCH_ID,
            a.TAG_NO
        FROM FEED_TRANSACTIONS ft
        LEFT JOIN ANIMAL_RECORDS a ON ft.ANIMAL_ID = a.ANIMAL_ID
        WHERE $whereClause
        ORDER BY ft.TRANSACTION_DATE DESC
        $limit_sql
    ";
    
    $stmt = $conn->prepare($histSql);
    $stmt->execute($params);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $error = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Feed Ledger - <?php echo htmlspecialchars($feed['FEED_NAME'] ?? 'Error'); ?></title>
    <style>
        /* Shared Styles */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            min-height: 100vh; color: #e2e8f0;
        }
        .container { max-width: 1200px; margin: 0 auto; padding: 2rem; }

        /* Header */
        .info-card {
            background: rgba(30, 41, 59, 0.7);
            border: 1px solid #475569;
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            backdrop-filter: blur(12px);
        }
        .info-title h1 { font-size: 2rem; font-weight: 800; color: white; margin-bottom: 5px; }
        .info-meta { color: #94a3b8; font-size: 0.9rem; }
        
        .stock-display {
            text-align: right;
            background: rgba(59, 130, 246, 0.1);
            padding: 15px 25px;
            border-radius: 12px;
            border: 1px solid rgba(59, 130, 246, 0.3);
        }
        .stock-val { font-size: 2.5rem; font-weight: 800; color: white; }

        /* Filter Bar */
        .filter-bar {
            display: flex; gap: 15px; margin-bottom: 15px;
            background: rgba(15, 23, 42, 0.6); padding: 15px; border-radius: 12px;
            border: 1px solid #334155; align-items: center; flex-wrap: wrap;
        }
        .filter-group { display: flex; align-items: center; gap: 8px; }
        .filter-group label { color: #94a3b8; font-size: 0.85rem; font-weight: 600; }
        
        .form-control {
            background: #1e293b; border: 1px solid #475569; color: white;
            padding: 8px 12px; border-radius: 6px; font-size: 0.9rem;
        }
        .form-control:focus { border-color: #3b82f6; outline: none; }

        .btn-filter {
            background: #3b82f6; color: white; border: none; padding: 8px 20px;
            border-radius: 6px; cursor: pointer; font-weight: 600; transition: 0.2s;
        }
        .btn-filter:hover { background: #2563eb; }
        
        .btn-reset {
            background: transparent; color: #94a3b8; border: 1px solid #475569; 
            padding: 8px 15px; border-radius: 6px; cursor: pointer; text-decoration: none; font-size: 0.9rem;
        }
        .btn-reset:hover { color: white; border-color: white; }

        /* Table */
        .table-wrapper {
            background: #1e293b;
            border-radius: 16px;
            border: 1px solid #334155;
            overflow: hidden;
        }
        .ledger-table { width: 100%; border-collapse: collapse; }
        .ledger-table th { 
            background: #0f172a; color: #94a3b8; text-align: left; padding: 15px 20px;
            font-size: 0.85rem; text-transform: uppercase; border-bottom: 2px solid #334155;
        }
        .ledger-table td { padding: 15px 20px; border-bottom: 1px solid rgba(255,255,255,0.05); color: #cbd5e1; font-size: 0.95rem; }
        .ledger-table tr:hover { background: rgba(255,255,255,0.02); }

        .btn-back {
            display: inline-flex; align-items: center; gap: 8px;
            color: #94a3b8; text-decoration: none; font-weight: 600; margin-bottom: 1rem;
        }
        .btn-back:hover { color: #fff; }
        
        .tag-badge { background: rgba(16, 185, 129, 0.15); color: #34d399; padding: 2px 8px; border-radius: 4px; font-size: 0.8rem; font-weight: 600; }
        .batch-badge { background: rgba(139, 92, 246, 0.15); color: #a78bfa; padding: 2px 8px; border-radius: 4px; font-size: 0.75rem; }
    </style>
</head>
<body>

<div class="container">
    <a href="available_feeds.php" class="btn-back">← Back to Inventory</a>

    <?php if(isset($error)): ?>
        <div style="background:rgba(239,68,68,0.2); color:#f87171; padding:20px; border-radius:12px; text-align:center;">
            <h3>Error</h3>
            <p><?= htmlspecialchars($error) ?></p>
        </div>
    <?php else: ?>

    <div class="info-card">
        <div class="info-title">
            <h1><?= htmlspecialchars($feed['FEED_NAME']) ?></h1>
            <div class="info-meta">
                Location: <?= htmlspecialchars($feed['LOCATION_NAME'] ?? 'Unassigned') ?> • 
                Last Update: <?= date('M d, Y', strtotime($feed['DATE_UPDATED'])) ?>
            </div>
        </div>
        <div class="stock-display">
            <div style="font-size:0.8rem; color:#60a5fa; text-transform:uppercase;">Current Stock</div>
            <div class="stock-val"><?= number_format($feed['TOTAL_WEIGHT_KG'], 2) ?> <span style="font-size:1rem;">kg</span></div>
        </div>
    </div>

    <form method="GET" class="filter-bar">
        <input type="hidden" name="feed_id" value="<?= $feed_id ?>">
        
        <div class="filter-group">
            <label>Show:</label>
            <select name="limit" class="form-control" onchange="this.form.submit()">
                <option value="10" <?= $limit_val == '10' ? 'selected' : '' ?>>10 Rows</option>
                <option value="50" <?= $limit_val == '50' ? 'selected' : '' ?>>50 Rows</option>
                <option value="100" <?= $limit_val == '100' ? 'selected' : '' ?>>100 Rows</option>
                <option value="all" <?= $limit_val == 'all' ? 'selected' : '' ?>>All Records</option>
            </select>
        </div>

        <div class="filter-group">
            <label>Date Range:</label>
            <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($start_date) ?>">
            <span style="color:#64748b">-</span>
            <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($end_date) ?>">
        </div>

        <div class="filter-group" style="flex-grow:1;">
            <input type="text" name="search" class="form-control" style="width:100%;" placeholder="Search Remarks, Tag No, or Batch ID..." value="<?= htmlspecialchars($search_term) ?>">
        </div>

        <button type="submit" class="btn-filter">Apply Filters</button>
        <a href="viewFeedLedger.php?feed_id=<?= $feed_id ?>" class="btn-reset">Reset</a>
    </form>

    <div class="table-wrapper">
        <table class="ledger-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Reference / Animal</th>
                    <th>Description</th>
                    <th>Qty Used (kg)</th>
                    <th>Cost Value</th>
                </tr>
            </thead>
            <tbody>
                <?php if(count($transactions) > 0): ?>
                    <?php foreach($transactions as $t): ?>
                    <tr>
                        <td><?= date('M d, Y h:i A', strtotime($t['TRANSACTION_DATE'])) ?></td>
                        <td>
                            <?php if($t['TAG_NO']): ?>
                                <span class="tag-badge"><?= $t['TAG_NO'] ?></span>
                            <?php else: ?>
                                <span style="color:#64748b;">N/A</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= htmlspecialchars($t['REMARKS']) ?>
                            <?php if($t['BATCH_ID']): ?>
                                <br><span class="batch-badge"><?= $t['BATCH_ID'] ?></span>
                            <?php endif; ?>
                        </td>
                        <td style="color:#f87171; font-weight:600;">- <?= number_format($t['QUANTITY_KG'], 3) ?></td>
                        <td style="font-family:monospace;">₱<?= number_format($t['TRANSACTION_COST'], 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="5" style="text-align:center; padding:3rem; color:#64748b;">No consumption history found matching criteria.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php endif; ?>
</div>

</body>
</html>