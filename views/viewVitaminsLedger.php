<?php
// reports/viewVitaminsLedger.php
error_reporting(0);
ini_set('display_errors', 0);
$page = "reports";
include '../config/Connection.php';

include '../security/checkAccess.php';
checkAccess('vitamins_supplements_report');
include '../common/navbar.php';
include '../common/chat_support.php';

$supply_id = $_GET['id'] ?? 0;

try {
    if (!$supply_id) throw new Exception("No Supplement ID provided.");

    // 1. Get Supplement Details
    $stmt = $conn->prepare("
        SELECT v.*, u.UNIT_NAME, u.UNIT_ABBR 
        FROM vitamins_supplements v 
        LEFT JOIN units u ON v.UNIT_ID = u.UNIT_ID 
        WHERE v.SUPPLY_ID = ?
    ");
    $stmt->execute([$supply_id]);
    $vitamin = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$vitamin) throw new Exception("Supplement not found.");
    $unit_label = $vitamin['UNIT_ABBR'] ?? 'units';

    // 2. Build Combined Ledger
    $ledger = [];

    // --- A. Fetch Usages (from vitamins_supplements_transactions) ---
    try {
        $u_stmt = $conn->prepare("
            SELECT 
                vt.TRANSACTION_DATE AS raw_date,
                DATE_FORMAT(vt.TRANSACTION_DATE, '%m/%d/%Y %h:%i %p') AS txn_date_fmt,
                'Supplement Administered' AS txn_type,
                'Deduct' AS effect,
                vt.QUANTITY_USED AS qty,
                CONCAT('Given to Tag: ', COALESCE(ar.TAG_NO, 'Unknown'), 
                       IF(vt.DOSAGE != '', CONCAT(' (Dosage: ', vt.DOSAGE, ')'), '')) AS remarks
            FROM vitamins_supplements_transactions vt
            LEFT JOIN animal_records ar ON vt.ANIMAL_ID = ar.ANIMAL_ID
            WHERE vt.ITEM_ID = ?
        ");
        $u_stmt->execute([$supply_id]);
        $usages = $u_stmt->fetchAll(PDO::FETCH_ASSOC);
        $ledger = array_merge($ledger, $usages);
    } catch (Exception $e) { }

    // --- B. Fetch Adjustments (from inventory_adjustments) ---
    try {
        $a_stmt = $conn->prepare("
            SELECT 
                TRANSACTION_DATE AS raw_date,
                DATE_FORMAT(TRANSACTION_DATE, '%m/%d/%Y %h:%i %p') AS txn_date_fmt,
                CONCAT('Adjustment (', INPUT_MODE, ')') AS txn_type,
                ADJUSTMENT_TYPE AS effect, 
                QUANTITY AS qty,
                CONCAT(REASON, IF(REMARKS != '', CONCAT(' - ', REMARKS), '')) AS remarks
            FROM inventory_adjustments
            WHERE CATEGORY = 'vitamin' AND REF_ID = ?
        ");
        $a_stmt->execute([$supply_id]);
        $adjustments = $a_stmt->fetchAll(PDO::FETCH_ASSOC);
        $ledger = array_merge($ledger, $adjustments);
    } catch (Exception $e) { }

    // --- C. Fetch Confirmed Purchases (Additions) ---
    try {
        $p_stmt = $conn->prepare("
            SELECT 
                CREATED_AT AS raw_date,
                DATE_FORMAT(DATE_OF_PURCHASE, '%m/%d/%Y') AS txn_date_fmt,
                'Purchase' AS txn_type,
                'Add' AS effect,
                (QUANTITY * COALESCE(ITEM_NET_WEIGHT, 1)) AS qty,
                CONCAT(
                    'Supplier: ', COALESCE(SUPPLIER, 'N/A'), 
                    ' | Ref: ', COALESCE(REFERENCE_NO, 'N/A'),
                    IF(EXPIRATION_DATE IS NOT NULL AND EXPIRATION_DATE != '0000-00-00', CONCAT(' | Exp: ', DATE_FORMAT(EXPIRATION_DATE, '%m/%d/%Y')), '')
                ) AS remarks
            FROM items
            WHERE ITEM_NAME = ? AND STATUS = 1
        ");
        $p_stmt->execute([$vitamin['SUPPLY_NAME']]);
        $purchases = $p_stmt->fetchAll(PDO::FETCH_ASSOC);
        $ledger = array_merge($ledger, $purchases);
    } catch (Exception $e) { }

    // 3. Sort Ledger by Date (Newest First)
    usort($ledger, function($a, $b) {
        return strtotime($b['raw_date']) - strtotime($a['raw_date']);
    });

    // 4. Calculate Summaries
    $total_purchased = 0;
    $total_used = 0;
    $net_adjustments = 0;

    foreach($ledger as $l) {
        if ($l['txn_type'] === 'Purchase') {
            $total_purchased += $l['qty'];
        } elseif ($l['txn_type'] === 'Supplement Administered') {
            $total_used += $l['qty'];
        } else {
            // Adjustments
            if (strtolower($l['effect']) === 'add') {
                $net_adjustments += $l['qty'];
            } else {
                $net_adjustments -= $l['qty'];
            }
        }
    }

} catch (Exception $e) {
    $error = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vitamin Ledger - <?= htmlspecialchars($vitamin['SUPPLY_NAME'] ?? 'Error') ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
    <style>
        body { font-family: system-ui, -apple-system, sans-serif; background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: #e2e8f0; margin: 0; padding-bottom: 40px; }
        .container { max-width: 1400px; margin: 0 auto; padding: 2rem; }
        
        .back-link { display: inline-flex; align-items: center; gap: 8px; text-decoration: none; color: #94a3b8; font-weight: 600; font-size: 0.95rem; margin-bottom: 20px; transition: color 0.2s; }
        .back-link:hover { color: white; }

        .supply-header-wrapper { margin-bottom: 2rem; }
        .supply-title { font-size: 2.2rem; font-weight: 800; color: #bef264; margin: 0 0 5px 0; display: flex; align-items: center; flex-wrap: wrap; gap: 10px; }
        .supply-subtitle { color: #94a3b8; margin: 0; font-size: 1rem; }

        .exp-badge {
            font-size: 1.1rem;
            font-weight: 600;
            color: #f87171;
            background: rgba(239, 68, 68, 0.15);
            padding: 4px 12px;
            border-radius: 8px;
            border: 1px solid rgba(239, 68, 68, 0.3);
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        /* --- STATS GRID --- */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        .stat-card {
            background: rgba(30, 41, 59, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            padding: 1.5rem;
            backdrop-filter: blur(10px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        .stat-val { font-size: 2rem; font-weight: 800; margin-bottom: 0.25rem; }
        .stat-lbl { color: #94a3b8; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 1px; font-weight: 600; }
        
        .c-purchase { color: #c084fc; } /* Purple */
        .c-usage    { color: #bef264; } /* Lime Green */
        .c-adjust   { color: #fcd34d; } /* Yellow */
        .c-stock    { color: #4ade80; } /* Green */

        /* --- TABLE --- */
        .table-wrap { background: rgba(30, 41, 59, 0.5); border-radius: 16px; overflow: hidden; border: 1px solid #334155; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 800px; }
        th { background: rgba(15, 23, 42, 0.9); color: #94a3b8; text-align: left; padding: 1.25rem 1rem; font-size: 0.8rem; text-transform: uppercase; border-bottom: 1px solid #334155; }
        td { padding: 1rem; border-bottom: 1px solid rgba(255,255,255,0.05); font-size: 0.95rem; color: #e2e8f0; vertical-align: middle; }
        tr:hover { background: rgba(255,255,255,0.02); }

        .badge { padding: 6px 12px; border-radius: 6px; font-size: 0.8rem; font-weight: 600; display: inline-block; }
        .type-purchase { background: rgba(168, 85, 247, 0.15); color: #c084fc; border: 1px solid rgba(168, 85, 247, 0.3); }
        .type-add { background: rgba(34, 197, 94, 0.15); color: #4ade80; border: 1px solid rgba(34, 197, 94, 0.3); }
        .type-deduct { background: rgba(239, 68, 68, 0.15); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.3); }
        .type-usage { background: rgba(163, 230, 53, 0.15); color: #bef264; border: 1px solid rgba(163, 230, 53, 0.3); } /* Lime for Vitamin Usage */

        .empty-state { text-align: center; padding: 4rem; color: #64748b; font-style: italic; }
        
        .qty-add { color: #4ade80; font-weight: bold; }
        .qty-deduct { color: #f87171; font-weight: bold; }
        
        @media (max-width: 768px) {
            .container { padding: 1rem; }
            .supply-title { font-size: 1.8rem; }
        }
    </style>
</head>
<body>

<div class="container">
    <a href="vitamins_report.php" class="back-link">
        <i class="fa-solid fa-arrow-left"></i> Back to Vitamin Report
    </a>

    <?php if(isset($error)): ?>
        <div style="background: rgba(239, 68, 68, 0.1); border: 1px solid #ef4444; padding: 1.5rem; border-radius: 12px; color: #ef4444; text-align: center;">
            <i class="fa-solid fa-triangle-exclamation" style="font-size: 2rem; margin-bottom: 1rem;"></i>
            <h3><?= $error ?></h3>
        </div>
    <?php else: ?>

        <div class="supply-header-wrapper">
            <h1 class="supply-title">
                <?= htmlspecialchars($vitamin['SUPPLY_NAME']) ?>
                <?php if(!empty($vitamin['EXPIRATION_DATE']) && $vitamin['EXPIRATION_DATE'] != '0000-00-00'): ?>
                    <span class="exp-badge">
                        <i class="fa-regular fa-calendar-xmark"></i> Exp: <?= date('m/d/Y', strtotime($vitamin['EXPIRATION_DATE'])) ?>
                    </span>
                <?php endif; ?>
            </h1>
            <p class="supply-subtitle">Detailed Volume Lifecycle & Traceability Ledger</p>
        </div>

        <div class="stats-grid">
            <div class="stat-card" style="border-top: 4px solid #a855f7;">
                <div class="stat-lbl">Total Purchased</div>
                <div class="stat-val c-purchase"><?= number_format($total_purchased, 2) ?> <span style="font-size: 1rem; color:#94a3b8;"><?= htmlspecialchars($unit_label) ?></span></div>
            </div>
            
            <div class="stat-card" style="border-top: 4px solid #bef264;">
                <div class="stat-lbl">Total Consumed</div>
                <div class="stat-val c-usage"><?= number_format($total_used, 2) ?> <span style="font-size: 1rem; color:#94a3b8;"><?= htmlspecialchars($unit_label) ?></span></div>
            </div>

            <div class="stat-card" style="border-top: 4px solid #f59e0b;">
                <div class="stat-lbl">Net Adjustments</div>
                <div class="stat-val c-adjust"><?= ($net_adjustments > 0 ? '+' : '') . number_format($net_adjustments, 2) ?> <span style="font-size: 1rem; color:#94a3b8;"><?= htmlspecialchars($unit_label) ?></span></div>
            </div>

            <div class="stat-card" style="border-top: 4px solid #22c55e; background: rgba(34, 197, 94, 0.05);">
                <div class="stat-lbl">Current Available Stock</div>
                <div class="stat-val c-stock"><?= number_format($vitamin['TOTAL_STOCK'], 2) ?> <span style="font-size: 1rem; color:#94a3b8;"><?= htmlspecialchars($unit_label) ?></span></div>
            </div>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Date & Time</th>
                        <th>Transaction Type</th>
                        <th style="text-align:right;">Volume Impact</th>
                        <th>Reason / Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($ledger)): ?>
                        <tr><td colspan="4" class="empty-state">No transaction history found for this supplement.</td></tr>
                    <?php else: ?>
                        <?php foreach($ledger as $row): 
                            // Determine styles based on Add/Deduct
                            $isDeduct = (strtolower($row['effect']) == 'deduct');
                            $qtyClass = $isDeduct ? 'qty-deduct' : 'qty-add';
                            $prefix = $isDeduct ? '-' : '+';
                            
                            // Badge Class Assignment
                            $badgeClass = 'type-add';
                            if ($row['txn_type'] == 'Supplement Administered') $badgeClass = 'type-usage';
                            else if ($row['txn_type'] == 'Purchase') $badgeClass = 'type-purchase';
                            else if ($isDeduct) $badgeClass = 'type-deduct';
                        ?>
                        <tr>
                            <td style="color:#94a3b8; font-weight: 500;"><?= htmlspecialchars($row['txn_date_fmt']) ?></td>
                            <td><span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($row['txn_type']) ?></span></td>
                            <td style="text-align:right;" class="<?= $qtyClass ?>">
                                <?= $prefix ?> <?= number_format($row['qty'], 2) ?> <?= htmlspecialchars($unit_label) ?>
                            </td>
                            <td style="color:#cbd5e1;"><?= htmlspecialchars($row['remarks']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

</body>
</html>