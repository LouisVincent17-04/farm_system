<?php
// views/viewFeedLedger.php
error_reporting(0);
ini_set('display_errors', 0);

$redirect_url= "";

$feed_id = $_GET['id'] ?? 0;
$back_button_text = "Back to Feed Report";
$page = "reports";

if($feed_id != 0) {
    $redirect_url = "feeds_report.php";
} else {
    $feed_id = $_GET['feed_id'] ?? 0;
    $redirect_url = "available_feeds.php";
    $back_button_text = "Back to Feed Availability";
    $page = "transactions";
}

include '../config/Connection.php';
include '../security/checkAccess.php';
checkAccess('feeds_feeding_supplies_report');
include '../common/navbar.php';
include '../common/chat_support.php';

try {
    if (!$feed_id) throw new Exception("No Feed ID provided.");

    // 1. Get Feed Details
    $stmt = $conn->prepare("SELECT * FROM feeds WHERE FEED_ID = ?");
    $stmt->execute([$feed_id]);
    $feed = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$feed) throw new Exception("Feed not found.");

    // 2. Build Combined Ledger
    $ledger = [];

    // --- A. Fetch Feedings (Usage) ---
    $f_stmt = $conn->prepare("
        SELECT 
            ft.TRANSACTION_DATE AS raw_date,
            DATE_FORMAT(ft.TRANSACTION_DATE, '%m/%d/%Y %h:%i %p') AS txn_date_fmt,
            'Feeding Usage' AS txn_type,
            'Deduct' AS effect,
            ft.QUANTITY_KG AS qty,
            CONCAT('Fed to: ', COALESCE(ar.TAG_NO, 'Group/Herd')) AS remarks
        FROM feed_transactions ft
        LEFT JOIN animal_records ar ON ft.ANIMAL_ID = ar.ANIMAL_ID
        WHERE ft.FEED_ID = ?
    ");
    $f_stmt->execute([$feed_id]);
    $feedings = $f_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // --- B. Fetch Adjustments (Manual updates, audits, etc.) ---
    $a_stmt = $conn->prepare("
        SELECT 
            TRANSACTION_DATE AS raw_date,
            DATE_FORMAT(TRANSACTION_DATE, '%m/%d/%Y %h:%i %p') AS txn_date_fmt,
            CONCAT('Adjustment (', INPUT_MODE, ')') AS txn_type,
            ADJUSTMENT_TYPE AS effect, 
            QUANTITY AS qty,
            CONCAT(REASON, IF(REMARKS != '', CONCAT(' - ', REMARKS), '')) AS remarks
        FROM inventory_adjustments
        WHERE CATEGORY = 'feed' AND REF_ID = ?
    ");
    $a_stmt->execute([$feed_id]);
    $adjustments = $a_stmt->fetchAll(PDO::FETCH_ASSOC);

    // --- C. Fetch Confirmed Purchases (Additions) ---
    // Matches the confirmed items by name to calculate total KG purchased
    // Also fetches the expiration date for each specific batch
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
    $p_stmt->execute([$feed['FEED_NAME']]);
    $purchases = $p_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Merge everything into a single ledger timeline
    $ledger = array_merge($feedings, $adjustments, $purchases);

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
        } elseif ($l['txn_type'] === 'Feeding Usage') {
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Feed Ledger | <?= htmlspecialchars($feed['FEED_NAME'] ?? 'Details') ?></title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=DM+Mono:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />

    <style>
        /* ─── CSS VARIABLES ─── */
        :root {
            --bg-base:        #080f1a;
            --bg-surface:     #0d1829;
            --bg-elevated:    #111f35;
            --bg-hover:       #162540;
            --border:         rgba(255,255,255,0.07);
            
            --amber:          #f59e0b;
            --amber-dim:      rgba(245,158,11,0.15);
            --emerald:        #10b981;
            --emerald-dim:    rgba(16,185,129,0.15);
            --blue:           #3b82f6;
            --blue-dim:       rgba(59,130,246,0.15);
            --red:            #ef4444;
            --red-dim:        rgba(239,68,68,0.15);
            --purple:         #a855f7;
            --purple-dim:     rgba(168,85,247,0.15);
            --slate:          #94a3b8;
            --slate-dim:      rgba(148,163,184,0.15);
            
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
            font-family: var(--font); background: var(--bg-base); color: var(--text-primary);
            min-height: 100vh; padding-bottom: 60px;
            background-image: radial-gradient(ellipse 80% 50% at 50% -20%, rgba(245,158,11,0.05) 0%, transparent 60%);
        }
        .container { max-width: 1200px; margin: 0 auto; padding: 2rem 1.5rem; }

        /* ─── TOP BAR ─── */
        .top-bar { display: flex; align-items: center; justify-content: space-between; margin-bottom: 2rem; gap: 1rem; flex-wrap: wrap; }
        .back-link {
            display: inline-flex; align-items: center; gap: 8px; text-decoration: none;
            color: var(--text-secondary); font-size: 0.875rem; font-weight: 500;
            padding: 8px 14px; background: var(--bg-elevated); border: 1px solid var(--border);
            border-radius: var(--radius-md); transition: all var(--transition);
        }
        .back-link:hover { color: #fff; border-color: rgba(255,255,255,0.2); background: var(--bg-hover); }

        /* ─── ERROR STATE ─── */
        .error-box { background: var(--red-dim); border: 1px solid var(--red); padding: 2rem; border-radius: var(--radius-lg); color: var(--red); text-align: center; margin-top: 2rem;}
        .error-box h3 { margin-top: 1rem; font-weight: 600;}

        /* ─── HEADER SECTION ─── */
        .feed-header-wrapper { margin-bottom: 2.5rem; }
        .feed-title { font-size: clamp(1.8rem, 4vw, 2.5rem); font-weight: 800; color: #fff; margin: 0 0 0.5rem 0; display: flex; align-items: center; flex-wrap: wrap; gap: 12px; letter-spacing: -0.02em;}
        .feed-title span.text-amber { background: linear-gradient(135deg, var(--amber), #b45309); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .feed-subtitle { color: var(--text-secondary); font-size: 1rem; margin: 0; }
        
        .exp-badge {
            font-size: 0.85rem; font-weight: 700; color: var(--red); background: var(--red-dim);
            padding: 6px 12px; border-radius: 8px; border: 1px solid rgba(239, 68, 68, 0.3);
            display: inline-flex; align-items: center; gap: 6px; text-transform: uppercase; letter-spacing: 0.05em; font-family: var(--font);
        }

        /* ─── STATS GRID ─── */
        .stats-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.5rem; margin-bottom: 2.5rem;
        }
        .stat-card {
            background: var(--bg-surface); border: 1px solid var(--border);
            border-radius: var(--radius-xl); padding: 1.5rem; box-shadow: var(--shadow-md);
            position: relative; overflow: hidden;
        }
        .stat-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px; }
        .stat-purple::before { background: var(--purple); }
        .stat-blue::before { background: var(--blue); }
        .stat-amber::before { background: var(--amber); }
        .stat-emerald::before { background: var(--emerald); }

        .stat-lbl { color: var(--text-secondary); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; font-weight: 700; margin-bottom: 0.5rem; }
        .stat-val { font-size: 2.2rem; font-weight: 800; font-family: var(--font-mono); line-height: 1;}
        .stat-val span { font-size: 1rem; color: var(--text-muted); font-family: var(--font); font-weight: 500;}
        
        .c-purchase { color: var(--purple); } 
        .c-usage    { color: var(--blue); } 
        .c-adjust   { color: var(--amber); } 
        .c-stock    { color: var(--emerald); }

        /* ─── LEDGER TABLE ─── */
        .section-title { font-size: 1.25rem; font-weight: 700; color: #fff; margin-bottom: 1.25rem; display: flex; align-items: center; gap: 10px;}

        .table-section { background: var(--bg-surface); border: 1px solid var(--border); border-radius: var(--radius-xl); overflow: hidden; box-shadow: var(--shadow-md);}
        .table-scroll-wrapper { overflow-x: auto; }
        .table-scroll-wrapper::-webkit-scrollbar { height: 8px; }
        .table-scroll-wrapper::-webkit-scrollbar-track { background: var(--bg-surface); }
        .table-scroll-wrapper::-webkit-scrollbar-thumb { background: #334155; border-radius: 4px; }

        .data-table { width: 100%; border-collapse: collapse; min-width: 900px; }
        .data-table th { background: var(--bg-elevated); color: var(--text-muted); font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.05em; padding: 16px; text-align: left; font-weight: 700; border-bottom: 1px solid var(--border); }
        .data-table td { padding: 16px; border-bottom: 1px solid rgba(255,255,255,0.03); color: var(--text-primary); vertical-align: top; font-size: 0.95rem; }
        .data-table tr:hover { background: rgba(255,255,255,0.01); }

        /* Ledger specific cells */
        .td-date { color: var(--text-secondary); font-family: var(--font-mono); font-size: 0.9rem; white-space: nowrap;}
        .td-details { font-weight: 600; color: #fff; line-height: 1.4;}
        .td-remarks { color: var(--text-secondary); font-size: 0.85rem; font-style: italic; }
        .td-cost { text-align: right; font-family: var(--font-mono); font-weight: 700; font-size: 1.05rem; white-space: nowrap;}
        
        .qty-add { color: var(--emerald); }
        .qty-deduct { color: var(--red); }
        .qty-neutral { color: var(--text-muted); }

        /* Type Badges */
        .badge { padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; display: inline-flex; align-items: center; gap: 6px; white-space: nowrap;}
        .type-purchase { background: var(--purple-dim); color: var(--purple); border: 1px solid rgba(168,85,247,0.3); }
        .type-add { background: var(--emerald-dim); color: var(--emerald); border: 1px solid rgba(16,185,129,0.3); }
        .type-deduct { background: var(--red-dim); color: var(--red); border: 1px solid rgba(239,68,68,0.3); }
        .type-usage { background: var(--blue-dim); color: var(--blue); border: 1px solid rgba(59,130,246,0.3); }
        .type-default { background: var(--slate-dim); color: var(--slate); border: 1px solid rgba(148,163,184,0.3); }

        .empty-state { text-align: center; padding: 4rem 2rem; color: var(--text-muted); font-style: italic; }

        /* ─── RESPONSIVE ─── */
        @media (max-width: 768px) {
            .container { padding: 1rem; }
            
            /* Table to Cards */
            .data-table thead { display: none; }
            .data-table, .data-table tbody, .data-table tr, .data-table td { display: block; width: 100%; box-sizing: border-box; }
            
            .data-table tr { background: var(--bg-surface); border: 1px solid var(--border); border-radius: var(--radius-lg); margin-bottom: 1rem; padding: 1.25rem; box-shadow: var(--shadow-md); }
            .data-table td { display: flex; flex-direction: column; align-items: flex-start; text-align: left; padding: 0.6rem 0; border-bottom: 1px dashed rgba(255,255,255,0.05); gap: 4px; }
            .data-table td:last-child { border-bottom: none; padding-top: 1rem; align-items: flex-end;}
            
            .data-table td::before { content: attr(data-label); font-weight: 700; color: var(--text-muted); font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.05em; }
            .td-cost { text-align: right; width: 100%;}
        }
    </style>
</head>
<body>

<div class="container">
    <div class="top-bar">
        <a href="<?= $redirect_url ?>" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> <?= $back_button_text ?>
        </a>
    </div>

    <?php if(isset($error)): ?>
        <div class="error-box">
            <i class="fa-solid fa-triangle-exclamation" style="font-size: 3rem; margin-bottom: 1rem;"></i>
            <h3><?= htmlspecialchars($error) ?></h3>
            <p style="color: var(--text-muted); margin-top: 0.5rem;">The requested feed profile could not be loaded.</p>
        </div>
    <?php else: ?>

        <div class="feed-header-wrapper">
            <h1 class="feed-title">
                <span class="text-amber"><?= htmlspecialchars($feed['FEED_NAME']) ?></span>
                <?php if(!empty($feed['EXPIRATION_DATE']) && $feed['EXPIRATION_DATE'] != '0000-00-00'): ?>
                    <span class="exp-badge">
                        <i class="fa-regular fa-calendar-xmark"></i> Exp: <?= date('M d, Y', strtotime($feed['EXPIRATION_DATE'])) ?>
                    </span>
                <?php endif; ?>
            </h1>
            <p class="feed-subtitle">Detailed Volume Lifecycle &amp; Traceability Ledger</p>
        </div>

        <div class="stats-grid">
            <div class="stat-card stat-purple">
                <div class="stat-lbl">Total Purchased</div>
                <div class="stat-val c-purchase"><?= number_format($total_purchased, 2) ?> <span>kg</span></div>
            </div>
            
            <div class="stat-card stat-blue">
                <div class="stat-lbl">Total Consumed</div>
                <div class="stat-val c-usage"><?= number_format($total_used, 2) ?> <span>kg</span></div>
            </div>

            <div class="stat-card stat-amber">
                <div class="stat-lbl">Net Adjustments</div>
                <div class="stat-val c-adjust"><?= ($net_adjustments > 0 ? '+' : '') . number_format($net_adjustments, 2) ?> <span>kg</span></div>
            </div>

            <div class="stat-card stat-emerald" style="background: rgba(16,185,129,0.05);">
                <div class="stat-lbl" style="color:var(--emerald);">Current Available Stock</div>
                <div class="stat-val c-stock"><?= number_format($feed['TOTAL_WEIGHT_KG'], 2) ?> <span>kg</span></div>
            </div>
        </div>

        <div class="section-title"><i class="fa-solid fa-book-open"></i> Transaction History</div>

        <div class="table-section">
            <div class="table-scroll-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date & Time</th>
                            <th>Transaction Type</th>
                            <th>Reason / Remarks</th>
                            <th style="text-align:right;">Volume Impact</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($ledger)): ?>
                            <tr>
                                <td colspan="4" class="empty-state">
                                    <i class="fa-solid fa-ghost" style="font-size: 2.5rem; display: block; margin-bottom: 1rem; opacity: 0.5;"></i>
                                    No transaction history found for this feed.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach($ledger as $row): 
                                // Determine styles based on Add/Deduct
                                $isDeduct = (strtolower($row['effect']) == 'deduct');
                                $qtyClass = $isDeduct ? 'qty-deduct' : 'qty-add';
                                $prefix = $isDeduct ? '-' : '+';
                                
                                // Handle Zero impact (e.g. some status updates if they exist)
                                if (floatval($row['qty']) == 0) {
                                    $qtyClass = 'qty-neutral';
                                    $prefix = '';
                                }
                                
                                // Badge Class Assignment
                                $badgeClass = 'type-add'; $icon = 'fa-plus';
                                if ($row['txn_type'] == 'Feeding Usage') { $badgeClass = 'type-usage'; $icon = 'fa-utensils'; }
                                else if ($row['txn_type'] == 'Purchase') { $badgeClass = 'type-purchase'; $icon = 'fa-cart-shopping'; }
                                else if ($isDeduct) { $badgeClass = 'type-deduct'; $icon = 'fa-minus'; }
                                else if (strpos(strtolower($row['txn_type']), 'adjustment') !== false) { $icon = 'fa-sliders'; }
                            ?>
                            <tr>
                                <td data-label="Date & Time" class="td-date"><?= htmlspecialchars($row['txn_date_fmt']) ?></td>
                                <td data-label="Type">
                                    <span class="badge <?= $badgeClass ?>"><i class="fa-solid <?= $icon ?>"></i> <?= htmlspecialchars($row['txn_type']) ?></span>
                                </td>
                                <td data-label="Reason / Remarks" class="td-details">
                                    <?= htmlspecialchars($row['remarks']) ?>
                                </td>
                                <td data-label="Volume Impact" class="td-cost <?= $qtyClass ?>">
                                    <?= $prefix ?> <?= number_format($row['qty'], 2) ?> KG
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

</body>
</html>