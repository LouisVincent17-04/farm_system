<?php
// views/available_feeds.php
error_reporting(E_ALL);
ini_set('display_errors', 0);

$page="transactions";
include '../common/navbar.php';
include '../config/Connection.php';

try {
    if (!isset($conn)) { throw new Exception("Database connection failed."); }

    // 1. Fetch Locations
    $locations_sql = "SELECT LOCATION_ID, LOCATION_NAME FROM LOCATIONS ORDER BY LOCATION_NAME ASC";
    $stmt = $conn->prepare($locations_sql);
    $stmt->execute();
    $locations_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Fetch All Feeds
    $feeds_sql = "SELECT f.FEED_ID, f.FEED_NAME, f.TOTAL_WEIGHT_KG, f.LOCATION_ID, l.LOCATION_NAME
                  FROM FEEDS f 
                  LEFT JOIN LOCATIONS l ON f.LOCATION_ID = l.LOCATION_ID 
                  ORDER BY f.FEED_NAME ASC";
    $stmt = $conn->prepare($feeds_sql);
    $stmt->execute();
    $feeds_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    echo "<script>console.error('Database Error: " . addslashes($e->getMessage()) . "');</script>";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Available Feeds</title>
    <style>
        /* --- GLOBAL STYLES --- */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            min-height: 100vh; color: #e2e8f0;
        }
        .container { max-width: 1400px; margin: 0 auto; padding: 2rem; }
        
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; }
        .header h1 { font-size: 2.5rem; font-weight: bold; margin-bottom: 0.5rem; color: #fff; }
        .header p { color: #94a3b8; }
        
        .add-btn {
            display: flex; align-items: center; gap: 0.5rem;
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: white; border: none; padding: 0.75rem 1.5rem;
            border-radius: 0.5rem; font-weight: 600; cursor: pointer;
            transition: all 0.2s; box-shadow: 0 4px 12px rgba(37, 99, 235, 0.4);
            text-decoration: none;
        }
        .add-btn:hover { transform: translateY(-2px); }

        .nav-tabs { display: flex; gap: 0; margin-bottom: 30px; background: rgba(15, 23, 42, 0.5); border-radius: 12px; padding: 6px; backdrop-filter: blur(10px); }
        .nav-tab { flex: 1; padding: 14px 28px; background: transparent; border: none; color: #94a3b8; font-weight: 600; cursor: pointer; transition: all 0.3s; font-size: 15px; border-radius: 8px; display: flex; align-items: center; justify-content: center; gap: 8px; }
        .nav-tab:hover { color: #e2e8f0; background: rgba(255, 255, 255, 0.05); }
        .nav-tab.active { color: white; background: linear-gradient(135deg, #2563eb, #1d4ed8); box-shadow: 0 4px 12px rgba(37, 99, 235, 0.4); }

        .stats-container { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 28px; border-radius: 16px; color: white; box-shadow: 0 8px 24px rgba(102, 126, 234, 0.3); }
        .stat-card .stat-value { font-size: 36px; font-weight: 800; }
        .stat-card.green { background: linear-gradient(135deg, #10b981 0%, #059669 100%); }
        .stat-card.red { background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); }

        .filter-section { background: rgba(30, 41, 59, 0.7); padding: 20px; border-radius: 12px; margin-bottom: 25px; display: flex; align-items: center; gap: 15px; border: 1px solid rgba(255,255,255,0.1); }
        .filter-select { background: #0f172a; border: 1px solid #334155; color: white; padding: 10px 15px; border-radius: 8px; min-width: 250px; font-size: 14px; }

        .table-container { background: rgba(30, 41, 59, 0.5); border-radius: 12px; border: 1px solid #475569; overflow: hidden; }
        .table { width: 100%; border-collapse: collapse; }
        .table th { padding: 1rem; text-align: left; font-size: 0.875rem; font-weight: 600; color: #e2e8f0; text-transform: uppercase; background: linear-gradient(135deg, #475569, #334155); }
        .table td { padding: 1rem; border-bottom: 1px solid #334155; color: #cbd5e1; }
        .table tbody tr:hover { background: rgba(255,255,255,0.02); }

        .quantity-badge { display: inline-block; padding: 6px 12px; background: linear-gradient(135deg, #10b981, #059669); color: white; border-radius: 8px; font-size: 13px; font-weight: 700; }
        .quantity-badge.low-stock { background: linear-gradient(135deg, #ef4444, #dc2626); }

        /* BUTTON STYLE FOR LEDGER LINK */
        .btn-view-ledger {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 8px 16px;
            background: rgba(59, 130, 246, 0.15);
            border: 1px solid rgba(59, 130, 246, 0.4);
            color: #60a5fa;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s;
        }
        .btn-view-ledger:hover {
            background: rgba(59, 130, 246, 0.3);
            color: #fff;
            border-color: #3b82f6;
            transform: translateY(-1px);
        }

        @media (max-width: 768px) {
            .nav-tabs, .filter-section { flex-direction: column; align-items: stretch; }
            .table-container { overflow-x: auto; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <h1>Available Feeds</h1>
                <p>Manage feed inventory and view consumption history.</p>
            </div>
            <a href="purch_feeds_feeding.php" class="add-btn">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                Add New Feed
            </a>
        </div>

        <div class="nav-tabs">
            <button class="nav-tab" onclick="window.location.href='feeding_transactions.php'">Feeding Logs</button>
            <button class="nav-tab active">Inventory & Ledgers</button>
        </div>

        <?php
        $totalFeeds = count($feeds_data);
        $lowStockCount = 0;
        $totalKg = 0;
        foreach($feeds_data as $feed) {
            $qtyKg = $feed['TOTAL_WEIGHT_KG'] ?? 0;
            $totalKg += $qtyKg;
            if ($qtyKg < 5) $lowStockCount++;
        }
        ?>
        <div class="stats-container">
            <div class="stat-card">
                <h3>Feed Types</h3>
                <div class="stat-value"><?php echo $totalFeeds; ?></div>
            </div>
            <div class="stat-card green">
                <h3>Total Stock (KG)</h3>
                <div class="stat-value"><?php echo number_format($totalKg, 2); ?></div>
            </div>
            <div class="stat-card red">
                <h3>Low Stock Items</h3>
                <div class="stat-value"><?php echo $lowStockCount; ?></div>
            </div>
        </div>

        <div class="filter-section">
            <label>Filter Location:</label>
            <select id="locationFilter" class="filter-select" onchange="filterFeeds()">
                <option value="all">All Locations</option>
                <?php foreach($locations_data as $loc): ?>
                    <option value="<?php echo $loc['LOCATION_ID']; ?>"><?php echo htmlspecialchars($loc['LOCATION_NAME']); ?></option>
                <?php endforeach; ?>
                <option value="unassigned">Unassigned</option>
            </select>
            <div style="flex-grow:1; text-align:right;">
                <input type="text" id="searchInput" class="filter-select" placeholder="Search feed name..." onkeyup="filterFeeds()">
            </div>
        </div>

        <div class="table-container">
            <table class="table" id="feed-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Feed Name</th>
                        <th>Location</th>
                        <th>Current Stock</th>
                        <th>Status</th>
                        <th width="150" style="text-align:center;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($feeds_data as $feed): 
                        $qty = $feed['TOTAL_WEIGHT_KG'] ?? 0;
                        $isLow = $qty < 5;
                        $locId = $feed['LOCATION_ID'] ?? 'unassigned';
                    ?>
                    <tr data-location-id="<?php echo $locId; ?>" data-feed-name="<?php echo htmlspecialchars($feed['FEED_NAME']); ?>">
                        <td style="color:#94a3b8; font-family:monospace;">FD-<?php echo str_pad($feed['FEED_ID'], 3, '0', STR_PAD_LEFT); ?></td>
                        <td style="font-weight:600; color:#fff;"><?php echo htmlspecialchars($feed['FEED_NAME']); ?></td>
                        <td><?php echo htmlspecialchars($feed['LOCATION_NAME'] ?? 'Unassigned'); ?></td>
                        <td>
                            <span class="quantity-badge <?php echo $isLow ? 'low-stock' : ''; ?>">
                                <?php echo number_format($qty, 2); ?> kg
                            </span>
                        </td>
                        <td>
                            <?php if($qty <= 0): ?>
                                <span style="color:#64748b; font-weight:bold;">Out of Stock</span>
                            <?php elseif($isLow): ?>
                                <span style="color:#ef4444; font-weight:bold;">Critical</span>
                            <?php else: ?>
                                <span style="color:#34d399; font-weight:bold;">Good</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align:center;">
                            <a href="viewFeedsLedger.php?feed_id=<?php echo $feed['FEED_ID']; ?>" class="btn-view-ledger">
                                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                View Ledger
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div id="empty-state" style="display:none; text-align:center; padding:2rem; color:#64748b;">No feeds found matching your filters.</div>
        </div>
    </div>

    <script>
        function filterFeeds() {
            const locId = document.getElementById('locationFilter').value;
            const search = document.getElementById('searchInput').value.toLowerCase();
            const rows = document.querySelectorAll('#feed-table tbody tr');
            let visibleCount = 0;

            rows.forEach(row => {
                const rLoc = row.dataset.locationId;
                const rName = row.dataset.feedName.toLowerCase();
                
                const matchLoc = (locId === 'all') || (locId === 'unassigned' && (!rLoc || rLoc === 'unassigned')) || (rLoc == locId);
                const matchText = rName.includes(search);

                if (matchLoc && matchText) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });
            document.getElementById('empty-state').style.display = visibleCount === 0 ? 'block' : 'none';
        }
    </script>
</body>
</html>