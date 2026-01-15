<?php
// views/viewVitaminsLedger.php
error_reporting(E_ALL);
ini_set('display_errors', 0);

$page = "transactions";
include '../common/navbar.php';
include '../config/Connection.php';
include '../security/checkRole.php';
checkRole(2); 

$supply_id = $_GET['id'] ?? null;

if (!$supply_id) {
    header("Location: available_vitamins.php");
    exit;
}

// FILTERS
$limit_val  = $_GET['limit'] ?? 10;
$limit_sql  = ($limit_val === 'all') ? "" : "LIMIT " . intval($limit_val);
$search_term = $_GET['search'] ?? '';
$start_date  = $_GET['start_date'] ?? '';
$end_date    = $_GET['end_date'] ?? '';

try {
    // 1. Fetch Vitamin Info
    $stmt = $conn->prepare("SELECT * FROM VITAMINS_SUPPLEMENTS WHERE SUPPLY_ID = ?");
    $stmt->execute([$supply_id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$item) throw new Exception("Vitamin ID not found.");

    // 2. Build Query
    $dateFilter = "";
    $searchFilter = "";
    $params = [];

    // Params for Union: Name (Stock In), ID (Stock Out)
    $params[] = $item['SUPPLY_NAME'];
    $params[] = $supply_id;

    if (!empty($start_date)) {
        $dateFilter .= " AND DATE(T_DATE) >= ?";
        $params[] = $start_date;
    }
    if (!empty($end_date)) {
        $dateFilter .= " AND DATE(T_DATE) <= ?";
        $params[] = $end_date;
    }
    if (!empty($search_term)) {
        $searchFilter = " AND (T_REF LIKE ? OR T_REMARKS LIKE ?)";
        $params[] = "%$search_term%";
        $params[] = "%$search_term%";
    }

    $sql = "
        SELECT * FROM (
            -- STOCK IN (From ITEMS table)
            SELECT 
                i.CREATED_AT as T_DATE,
                'STOCK IN' as T_TYPE,
                CONCAT('Purchase ID #', i.ITEM_ID) as T_REF,
                i.QUANTITY as QTY_CHANGE,
                i.UNIT_COST as COST_PER_UNIT,
                COALESCE(i.ITEM_DESCRIPTION, 'Refill') as T_REMARKS
            FROM ITEMS i
            WHERE i.ITEM_NAME = ? 
            AND i.ITEM_TYPE_ID = 10 -- Type 10 = Vitamins/Supplements

            UNION ALL

            -- STOCK OUT (From VITAMINS_SUPPLEMENTS_TRANSACTIONS)
            SELECT 
                t.TRANSACTION_DATE as T_DATE,
                'USAGE' as T_TYPE,
                CONCAT('Animal Tag: ', COALESCE(a.TAG_NO, 'N/A')) as T_REF,
                (t.QUANTITY_USED * -1) as QTY_CHANGE,
                0 as COST_PER_UNIT,
                COALESCE(t.REMARKS, t.DOSAGE) as T_REMARKS
            FROM VITAMINS_SUPPLEMENTS_TRANSACTIONS t
            LEFT JOIN ANIMAL_RECORDS a ON t.ANIMAL_ID = a.ANIMAL_ID
            WHERE t.ITEM_ID = ?
        ) AS History
        WHERE 1=1 
        $dateFilter
        $searchFilter
        ORDER BY T_DATE DESC
        $limit_sql
    ";

    $stmt = $conn->prepare($sql);
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
    <title>Vitamin Ledger</title>
    <style>
        /* Styles adapted for Pink Theme */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            min-height: 100vh; color: #e2e8f0;
        }
        .container { max-width: 1200px; margin: 0 auto; padding: 2rem; }

        .header-card {
            background: rgba(30, 41, 59, 0.7);
            border: 1px solid #475569;
            border-radius: 16px; padding: 2rem; margin-bottom: 2rem;
            display: flex; justify-content: space-between; align-items: center;
            backdrop-filter: blur(12px);
        }
        .title h1 { font-size: 2rem; font-weight: 800; margin-bottom: 5px; color: white; }
        
        .stock-box { 
            text-align: right; padding: 15px 25px; border-radius: 12px;
            background: rgba(219, 39, 119, 0.1); border: 1px solid rgba(219, 39, 119, 0.3); /* Pink */
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
        .form-control:focus { border-color: #db2777; outline: none; }
        
        .btn-filter {
            background: #db2777; color: white; border: none; padding: 8px 20px;
            border-radius: 6px; cursor: pointer; font-weight: 600; transition: 0.2s;
        }
        .btn-filter:hover { background: #be185d; }
        
        .btn-reset {
            background: transparent; color: #94a3b8; border: 1px solid #475569; 
            padding: 8px 15px; border-radius: 6px; cursor: pointer; text-decoration: none; font-size: 0.9rem;
        }
        .btn-reset:hover { color: white; border-color: white; }

        /* Table */
        .table-wrapper { background: #1e293b; border-radius: 16px; border: 1px solid #334155; overflow: hidden; }
        .ledger-table { width: 100%; border-collapse: collapse; }
        .ledger-table th { background: #0f172a; color: #f472b6; padding: 15px; text-align: left; font-size: 0.85rem; text-transform: uppercase; border-bottom: 2px solid #334155; }
        .ledger-table td { padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.05); color: #cbd5e1; }
        .ledger-table tr:hover { background: rgba(255,255,255,0.02); }
        
        .badge { padding: 4px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: bold; }
        .badge-in { background: rgba(52, 211, 153, 0.15); color: #34d399; }
        .badge-out { background: rgba(248, 113, 113, 0.15); color: #f87171; }
        
        .qty-pos { color: #34d399; font-weight: bold; }
        .qty-neg { color: #f87171; font-weight: bold; }

        .btn-back { display: inline-flex; align-items: center; gap: 8px; color: #94a3b8; text-decoration: none; margin-bottom: 1rem; }
        .btn-back:hover { color: white; }
    </style>
</head>
<body>

<div class="container">
    <a href="available_vitamins.php" class="btn-back">← Back to Vitamins</a>

    <?php if(isset($error)): ?>
        <div style="background:rgba(239,68,68,0.2); color:#f87171; padding:20px; border-radius:12px; text-align:center;">
            <h3>Error</h3>
            <p><?= htmlspecialchars($error) ?></p>
        </div>
    <?php else: ?>

    <div class="header-card">
        <div class="title">
            <h1><?= htmlspecialchars($item['SUPPLY_NAME']) ?></h1>
            <div style="color: #94a3b8;">ID: VIT-<?= str_pad($item['SUPPLY_ID'], 3, '0', STR_PAD_LEFT) ?></div>
        </div>
        <div class="stock-box">
            <div style="color: #f472b6; font-size: 0.8rem; text-transform: uppercase;">Current Stock</div>
            <div class="stock-val"><?= number_format($item['TOTAL_STOCK'], 2) ?></div>
        </div>
    </div>

    <form method="GET" class="filter-bar">
        <input type="hidden" name="id" value="<?= $supply_id ?>">
        
        <div style="display:flex; align-items:center; gap:10px;">
            <label style="color:#94a3b8; font-size:0.9rem;">Show:</label>
            <select name="limit" class="form-control" onchange="this.form.submit()">
                <option value="10" <?= $limit_val == '10' ? 'selected' : '' ?>>10</option>
                <option value="50" <?= $limit_val == '50' ? 'selected' : '' ?>>50</option>
                <option value="all" <?= $limit_val == 'all' ? 'selected' : '' ?>>All</option>
            </select>
        </div>

        <div style="display:flex; align-items:center; gap:10px;">
            <label style="color:#94a3b8;">Date:</label>
            <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($start_date) ?>">
            <span style="color:#64748b">-</span>
            <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($end_date) ?>">
        </div>

        <div style="flex-grow:1;">
            <input type="text" name="search" class="form-control" style="width:100%;" placeholder="Search reference..." value="<?= htmlspecialchars($search_term) ?>">
        </div>

        <button type="submit" class="btn-filter">Apply</button>
        <a href="viewVitaminLedger.php?id=<?= $supply_id ?>" class="btn-reset">Reset</a>
    </form>

    <div class="table-wrapper">
        <table class="ledger-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Reference</th>
                    <th>Description</th>
                    <th>Qty Change</th>
                </tr>
            </thead>
            <tbody>
                <?php if(count($transactions) > 0): ?>
                    <?php foreach($transactions as $t): 
                        $qty = floatval($t['QTY_CHANGE']);
                        $isPos = $qty >= 0;
                    ?>
                    <tr>
                        <td><?= date('M d, Y h:i A', strtotime($t['T_DATE'])) ?></td>
                        <td><span class="badge <?= $isPos ? 'badge-in' : 'badge-out' ?>"><?= $t['T_TYPE'] ?></span></td>
                        <td style="font-weight:600; color:#e2e8f0;"><?= htmlspecialchars($t['T_REF']) ?></td>
                        <td style="color:#94a3b8;"><?= htmlspecialchars($t['T_REMARKS']) ?></td>
                        <td class="<?= $isPos ? 'qty-pos' : 'qty-neg' ?>">
                            <?= $isPos ? '+' : '' ?><?= number_format($qty, 2) ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="5" style="text-align:center; padding:3rem; color:#64748b;">No records found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php endif; ?>
</div>

</body>
</html>