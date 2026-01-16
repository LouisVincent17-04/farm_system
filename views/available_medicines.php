<?php
// views/available_medicines.php
error_reporting(0);
ini_set('display_errors', 0);

$page="transactions";
include '../common/navbar.php';
include '../config/Connection.php';

try {
    if (!isset($conn)) { throw new Exception("Database connection failed."); }

    // Fetch Medicines with Unit info
    $supplies_sql = "SELECT m.*, u.UNIT_NAME, u.UNIT_ABBR 
                     FROM MEDICINES m 
                     LEFT JOIN UNITS u ON m.UNIT_ID = u.UNIT_ID 
                     ORDER BY m.SUPPLY_NAME ASC";
    
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> <title>Available Medicines Inventory</title>
    <link rel="stylesheet" href="../css/purch_housing_facilities.css">
    <style>
        /* Base Styles */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif; background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); min-height: 100vh; color: white; }
        .container { max-width: 1400px; margin: 0 auto; padding: 2rem; }
        
        /* Header */
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; }
        .header-info h1 { font-size: 2.5rem; font-weight: bold; margin-bottom: 0.5rem; }
        .header-info p { color: #cbd5e1; }
        
        .add-btn { display: flex; align-items: center; gap: 0.5rem; background: linear-gradient(135deg, #2563eb, #9333ea); color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 0.5rem; font-weight: 600; cursor: pointer; transition: all 0.2s; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); text-decoration: none; }
        .add-btn:hover { background: linear-gradient(135deg, #1d4ed8, #7c3aed); transform: scale(1.05); box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2); }
        
        /* Tabs */
        .nav-tabs { display: flex; gap: 0; margin-bottom: 30px; background: rgba(15, 23, 42, 0.5); border-radius: 12px; padding: 6px; backdrop-filter: blur(10px); flex-wrap: wrap; }
        .nav-tab { flex: 1; padding: 14px 28px; background: transparent; border: none; color: #94a3b8; font-weight: 600; cursor: pointer; transition: all 0.3s; font-size: 15px; border-radius: 8px; display: flex; align-items: center; justify-content: center; gap: 8px; white-space: nowrap; }
        .nav-tab:hover { color: #e2e8f0; background: rgba(255, 255, 255, 0.05); }
        .nav-tab.active { color: white; background: linear-gradient(135deg, #2563eb, #1d4ed8); box-shadow: 0 4px 12px rgba(37, 99, 235, 0.4); }
        
        /* Search */
        .search-container { position: relative; margin-bottom: 2rem; }
        .search-input { width: 100%; padding: 1rem 1rem 1rem 1.5rem; background: rgba(30, 41, 59, 0.5); border: 1px solid #475569; border-radius: 0.5rem; color: white; font-size: 1rem; backdrop-filter: blur(10px); }
        .search-input::placeholder { color: #94a3b8; }
        .search-input:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1); }
        
        /* Quantity Badge */
        .quantity-badge { display: inline-block; padding: 6px 12px; background: linear-gradient(135deg, #10b981, #059669); color: white; border-radius: 8px; font-size: 13px; font-weight: 700; white-space: nowrap; }
        .quantity-badge.low-stock { background: linear-gradient(135deg, #ef4444, #dc2626); }

        /* Ledger Button */
        .btn-view-ledger {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 8px 16px;
            background: rgba(139, 92, 246, 0.15); 
            border: 1px solid rgba(139, 92, 246, 0.4);
            color: #a78bfa;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s;
            white-space: nowrap;
        }
        .btn-view-ledger:hover {
            background: rgba(139, 92, 246, 0.3);
            color: #fff;
            border-color: #8b5cf6;
            transform: translateY(-1px);
        }

        /* Table Styles */
        .table-container { 
            background: rgba(30, 41, 59, 0.5); 
            border-radius: 12px; 
            border: 1px solid #475569; 
            /* ENABLE HORIZONTAL SCROLLING */
            overflow-x: auto; 
            -webkit-overflow-scrolling: touch;
        }
        .table { width: 100%; border-collapse: collapse; }
        .table th { background: linear-gradient(135deg, #475569, #334155); color: #e2e8f0; padding: 1rem; text-align: left; white-space: nowrap; }
        .table td { padding: 1rem; border-bottom: 1px solid #334155; color: #cbd5e1; vertical-align: middle; white-space: nowrap; }
        .table tr:hover { background: rgba(255,255,255,0.02); }

        /* --- MOBILE RESPONSIVE CSS --- */
        @media (max-width: 768px) {
            .container { padding: 1rem; }
            
            /* Stack Header */
            .header { flex-direction: column; align-items: stretch; gap: 1rem; text-align: center; }
            .header-info h1 { font-size: 1.75rem; }
            .add-btn { width: 100%; justify-content: center; }

            /* Stack Tabs */
            .nav-tabs { flex-direction: column; gap: 5px; }
            .nav-tab { width: 100%; }

            /* SCROLLABLE TABLE ADJUSTMENTS */
            /* Force the table to be wide enough to require scrolling */
            .table { min-width: 800px; }
            
            /* Ensure text doesn't wrap awkwardly */
            .table th, .table td { padding: 12px 15px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="header-info">
                <h1>Available Medicines</h1>
                <p>View and Manage Medicine Stock</p>
            </div>
            <button class="add-btn" onclick="window.location.href='purch_medicines.php'">
                + Add New Supply
            </button>
        </div>

        <div class="nav-tabs">
            <button class="nav-tab" onclick="window.location.href='medication.php'">Medical Transactions</button>
            <button class="nav-tab active">Available Medicines</button>
        </div>

        <div class="search-container">
            <input type="text" class="search-input" id="searchInput" placeholder="Search by supply name..." onkeyup="filterTable()">
        </div>

        <div class="table-container">
            <table class="table" id="supply-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Supply Name</th>
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
                        $unitAbbr = $supply['UNIT_ABBR'] ?? 'units';
                    ?>
                    <tr>
                        <td style="color:#94a3b8; font-family:monospace;">MED-<?php echo str_pad($supply['SUPPLY_ID'], 3, '0', STR_PAD_LEFT); ?></td>
                        
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
                            <a href="viewMedicinesLedger.php?id=<?php echo $supply['SUPPLY_ID']; ?>" class="btn-view-ledger">
                                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                                View Ledger
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div id="empty-state" style="display:none; text-align:center; padding:2rem; color:#64748b;">No medicines found.</div>
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