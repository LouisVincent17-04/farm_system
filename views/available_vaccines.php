<?php
// views/available_vaccines.php
error_reporting(0);
ini_set('display_errors', 0);

$page = "transactions";
include '../common/navbar.php';
include '../config/Connection.php';

try {
    if (!isset($conn)) { throw new Exception("Database connection failed."); }

    // Fetch Vaccine Inventory Data
    $supplies_sql = "SELECT v.SUPPLY_ID, v.SUPPLY_NAME, v.TOTAL_STOCK, v.DATE_UPDATED, u.UNIT_ABBR 
                     FROM VACCINES v 
                     LEFT JOIN UNITS u ON v.UNIT_ID = u.UNIT_ID 
                     ORDER BY v.SUPPLY_NAME ASC";
    
    $stmt = $conn->prepare($supplies_sql);
    $stmt->execute();
    $supplies_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    echo "<script>console.error('Database Error: " . addslashes($e->getMessage()) . "');</script>";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Available Vaccines</title>
    <link rel="stylesheet" href="../css/purch_housing_facilities.css">
    <style>
        /* --- CORE STYLES --- */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            min-height: 100vh;
            color: white;
        }
        .container { max-width: 1400px; margin: 0 auto; padding: 2rem; }
        
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; }
        .header-info h1 { font-size: 2.5rem; font-weight: bold; margin-bottom: 0.5rem; }
        .header-info p { color: #cbd5e1; }
        
        .add-btn {
            display: flex; align-items: center; gap: 0.5rem;
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: white; border: none; padding: 0.75rem 1.5rem;
            border-radius: 0.5rem; font-weight: 600; cursor: pointer;
            transition: all 0.2s; box-shadow: 0 4px 12px rgba(37, 99, 235, 0.4);
            text-decoration: none;
        }
        .add-btn:hover { transform: translateY(-2px); }
        .add-btn svg { width: 20px; height: 20px; }

        /* --- NAV TABS - Matching vaccination.php --- */
        .nav-tabs {
            display: flex; gap: 0; margin-bottom: 30px; 
            background: rgba(15, 23, 42, 0.5);
            border-radius: 12px; padding: 6px; 
            backdrop-filter: blur(10px);
        }
        .nav-tab {
            flex: 1; padding: 14px 28px; 
            background: transparent; border: none; 
            color: #94a3b8; font-weight: 600; 
            cursor: pointer; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            font-size: 15px; border-radius: 8px; 
            display: flex; align-items: center; justify-content: center;
            gap: 8px; position: relative;
            text-decoration: none;
        }
        .nav-tab:hover { color: #e2e8f0; background: rgba(255, 255, 255, 0.05); }
        .nav-tab.active { 
            color: white; 
            background: linear-gradient(135deg, #2563eb, #1d4ed8); /* Blue for Vaccination */
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.4); 
        }
        .nav-tab svg { width: 20px; height: 20px; }

        /* --- SEARCH --- */
        .search-container { position: relative; margin-bottom: 2rem; }
        .search-input {
            width: 100%; padding: 1rem 1rem 1rem 3rem;
            background: rgba(30, 41, 59, 0.5); border: 1px solid #475569;
            border-radius: 0.5rem; color: white; font-size: 1rem;
        }
        .search-input:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        .search-icon {
            position: absolute; left: 1rem; top: 50%;
            transform: translateY(-50%); color: #94a3b8; width: 20px; height: 20px;
            pointer-events: none;
        }

        /* --- TABLE --- */
        .table-container {
            background: rgba(30, 41, 59, 0.5); backdrop-filter: blur(10px);
            border-radius: 0.75rem; border: 1px solid #475569;
            overflow: hidden; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }
        .table { width: 100%; border-collapse: collapse; }
        .table thead { background: linear-gradient(135deg, #475569, #334155); }
        .table th {
            padding: 1rem 1.5rem; text-align: left; font-size: 0.875rem;
            font-weight: 600; color: #e2e8f0; text-transform: uppercase;
        }
        .table tbody tr { border-bottom: 1px solid #475569; transition: background-color 0.2s; }
        .table tbody tr:hover { background: rgba(55, 65, 81, 0.5); }
        .table td { padding: 1rem 1.5rem; vertical-align: middle; }

        .quantity-badge {
            display: inline-block; padding: 6px 12px;
            background: linear-gradient(135deg, #10b981, #059669);
            color: white; border-radius: 8px; font-size: 13px; font-weight: 700;
            box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3);
        }
        .quantity-badge.low-stock { 
            background: linear-gradient(135deg, #ef4444, #dc2626); 
            box-shadow: 0 2px 8px rgba(239, 68, 68, 0.3);
        }

        /* Ledger Button - Blue Theme to match vaccination */
        .btn-view-ledger {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 8px 16px;
            background: rgba(37, 99, 235, 0.15); 
            border: 1px solid rgba(37, 99, 235, 0.4);
            color: #60a5fa;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s;
        }
        .btn-view-ledger:hover {
            background: rgba(37, 99, 235, 0.3);
            color: #fff;
            border-color: #2563eb;
            transform: translateY(-1px);
        }
        
        .empty-state { 
            text-align: center; 
            padding: 3rem; 
            display: none; 
            color: #94a3b8; 
        }
        .empty-state h3 {
            color: #cbd5e1;
            margin-bottom: 0.5rem;
        }

        @media (max-width: 768px) {
            .nav-tabs { flex-direction: column; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="header-info">
                <h1>Vaccination Records</h1>
                <p>Vaccine Inventory Status and Stock Levels</p>
            </div>
            <a href="purch_vaccines.php" class="add-btn">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                Add New Vaccine
            </a>
        </div>

        <div class="nav-tabs">
            <a href="vaccination.php" class="nav-tab">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"></path>
                </svg>
                Vaccination Transactions
            </a>
            <a href="available_vaccines.php" class="nav-tab active">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                </svg>
                Available Vaccines
            </a>
        </div>

        <div class="search-container">
            <svg class="search-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m21 21-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
            </svg>
            <input type="text" class="search-input" id="searchInput" placeholder="Search by vaccine name..." onkeyup="filterTable()">
        </div>

        <div class="table-container">
            <table class="table" id="supply-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Vaccine Name</th>
                        <th>Total Stock</th>
                        <th>Status</th>
                        <th>Last Updated</th>
                        <th width="150" style="text-align: center;">History</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($supplies_data as $supply): 
                        $stock = $supply['TOTAL_STOCK'] ?? 0;
                        $isLow = $stock < 10;
                        $unitAbbr = $supply['UNIT_ABBR'] ?? 'doses';
                    ?>
                    <tr data-supply-id="<?php echo $supply['SUPPLY_ID']; ?>"
                        data-supply-name="<?php echo htmlspecialchars($supply['SUPPLY_NAME']); ?>"
                        data-total-stock="<?php echo $stock; ?>"
                        data-unit-abbr="<?php echo htmlspecialchars($unitAbbr); ?>"
                        data-date-updated="<?php echo htmlspecialchars($supply['DATE_UPDATED'] ?? ''); ?>">
                        
                        <td style="color:#94a3b8; font-family:monospace;">VAC-<?php echo str_pad($supply['SUPPLY_ID'], 3, '0', STR_PAD_LEFT); ?></td>
                        <td style="font-weight:600; color:#fff;"><?php echo htmlspecialchars($supply['SUPPLY_NAME']); ?></td>
                        <td>
                            <span class="quantity-badge <?php echo $isLow ? 'low-stock' : ''; ?>">
                                <?php echo number_format($stock, 2); ?> <?php echo htmlspecialchars($unitAbbr); ?>
                            </span>
                        </td>
                        <td>
                            <?php if($stock <= 0): ?>
                                <span style="color:#64748b; font-weight:bold;">Out of Stock</span>
                            <?php elseif($isLow): ?>
                                <span style="color:#ef4444; font-weight:bold;">Critical</span>
                            <?php else: ?>
                                <span style="color:#34d399; font-weight:bold;">Good</span>
                            <?php endif; ?>
                        </td>
                        <td style="font-size:0.9rem; color:#94a3b8;">
                            <?php echo $supply['DATE_UPDATED'] ? date('M d, Y', strtotime($supply['DATE_UPDATED'])) : 'N/A'; ?>
                        </td>
                        <td style="text-align:center;">
                            <a href="viewVaccinesLedger.php?id=<?php echo $supply['SUPPLY_ID']; ?>" class="btn-view-ledger">
                                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                                </svg>
                                Ledger
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div id="empty-state" class="empty-state">
                <h3>No vaccines found</h3>
                <p>Try adjusting your search terms</p>
            </div>
        </div>
    </div>

    <script>
        function filterTable() {
            const input = document.getElementById('searchInput');
            const filter = input.value.toLowerCase();
            const rows = document.querySelectorAll('#supply-table tbody tr');
            let visibleCount = 0;

            rows.forEach(row => {
                const text = row.innerText.toLowerCase();
                if(text.includes(filter)){
                    row.style.display = "";
                    visibleCount++;
                } else {
                    row.style.display = "none";
                }
            });
            document.getElementById('empty-state').style.display = visibleCount === 0 ? 'block' : 'none';
        }
    </script>
</body>
</html>