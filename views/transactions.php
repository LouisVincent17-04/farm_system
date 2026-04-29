<?php
// views/transactions.php
$page = "transactions"; 
include '../functions/getUsersLocation.php';

include '../security/checkAccess.php';
checkAccess('transactions');
include '../common/navbar.php';
include '../common/chat_support.php';

// Check if user is Super Admin
$isSuperAdmin = (isset($_SESSION['user']['USER_TYPE']) && $_SESSION['user']['USER_TYPE'] == 4);

// --- FETCH DYNAMIC STATS ---
$stats = [
    'todays_trans' => 0,
    'active_animals' => 0,
    'farms' => 0,
    'buildings' => 0,
    'pens' => 0
];

try {
    if ($USER_LOCATION_ != 1000) {
        // Location Restricted Stats
        $loc_id = (int)$USER_LOCATION_;
        $stats['farms'] = 1;
        $stats['buildings'] = $conn->query("SELECT COUNT(*) FROM BUILDINGS WHERE LOCATION_ID = $loc_id")->fetchColumn();
        $stats['pens'] = $conn->query("SELECT COUNT(*) FROM PENS p JOIN BUILDINGS b ON p.BUILDING_ID = b.BUILDING_ID WHERE b.LOCATION_ID = $loc_id")->fetchColumn();
        $stats['active_animals'] = $conn->query("SELECT COUNT(*) FROM animal_records WHERE IS_ACTIVE = 1 AND CURRENT_STATUS = 'Active' AND LOCATION_ID = $loc_id")->fetchColumn();
        
        // Comprehensive Transactions Today (Purchases, Feeds, Meds, Vacs, Vits, Checkups, Misc Fees)
        $t_query = "SELECT 
            (SELECT COUNT(*) FROM ITEMS WHERE DATE(CREATED_AT) = CURDATE() AND LOCATION_ID = $loc_id) +
            (SELECT COUNT(*) FROM feed_transactions t JOIN animal_records a ON t.ANIMAL_ID = a.ANIMAL_ID WHERE DATE(t.TRANSACTION_DATE) = CURDATE() AND a.LOCATION_ID = $loc_id) +
            (SELECT COUNT(*) FROM treatment_transactions t JOIN animal_records a ON t.ANIMAL_ID = a.ANIMAL_ID WHERE DATE(t.TRANSACTION_DATE) = CURDATE() AND a.LOCATION_ID = $loc_id) +
            (SELECT COUNT(*) FROM vaccination_records t JOIN animal_records a ON t.ANIMAL_ID = a.ANIMAL_ID WHERE DATE(t.VACCINATION_DATE) = CURDATE() AND a.LOCATION_ID = $loc_id) +
            (SELECT COUNT(*) FROM vitamins_supplements_transactions t JOIN animal_records a ON t.ANIMAL_ID = a.ANIMAL_ID WHERE DATE(t.TRANSACTION_DATE) = CURDATE() AND a.LOCATION_ID = $loc_id) +
            (SELECT COUNT(*) FROM check_ups t JOIN animal_records a ON t.ANIMAL_ID = a.ANIMAL_ID WHERE DATE(t.CHECKUP_DATE) = CURDATE() AND a.LOCATION_ID = $loc_id) +
            (SELECT COUNT(*) FROM animal_misc_fees t JOIN animal_records a ON t.ANIMAL_ID = a.ANIMAL_ID WHERE DATE(t.CREATED_AT) = CURDATE() AND a.LOCATION_ID = $loc_id)";
        $stats['todays_trans'] = $conn->query($t_query)->fetchColumn();
        
    } else {
        // Super Admin Stats (All Locations)
        $stats['farms'] = $conn->query("SELECT COUNT(*) FROM LOCATIONS")->fetchColumn();
        $stats['buildings'] = $conn->query("SELECT COUNT(*) FROM BUILDINGS")->fetchColumn();
        $stats['pens'] = $conn->query("SELECT COUNT(*) FROM PENS")->fetchColumn();
        $stats['active_animals'] = $conn->query("SELECT COUNT(*) FROM animal_records WHERE IS_ACTIVE = 1 AND CURRENT_STATUS = 'Active'")->fetchColumn();
        
        // Comprehensive Transactions Today (All Locations)
        $t_query = "SELECT 
            (SELECT COUNT(*) FROM ITEMS WHERE DATE(CREATED_AT) = CURDATE()) +
            (SELECT COUNT(*) FROM feed_transactions WHERE DATE(TRANSACTION_DATE) = CURDATE()) +
            (SELECT COUNT(*) FROM treatment_transactions WHERE DATE(TRANSACTION_DATE) = CURDATE()) +
            (SELECT COUNT(*) FROM vaccination_records WHERE DATE(VACCINATION_DATE) = CURDATE()) +
            (SELECT COUNT(*) FROM vitamins_supplements_transactions WHERE DATE(TRANSACTION_DATE) = CURDATE()) +
            (SELECT COUNT(*) FROM check_ups WHERE DATE(CHECKUP_DATE) = CURDATE()) +
            (SELECT COUNT(*) FROM animal_misc_fees WHERE DATE(CREATED_AT) = CURDATE())";
        $stats['todays_trans'] = $conn->query($t_query)->fetchColumn();
    }
} catch (Exception $e) {
    error_log("Dashboard stats error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaction Management Center | FarmPro</title>
    
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
            --border-active:  rgba(16,185,129,0.5); /* Emerald Accent */
            
            /* Theme Colors */
            --emerald:        #10b981; --emerald-dim: rgba(16,185,129,0.12);
            --blue:           #3b82f6; --blue-dim: rgba(59,130,246,0.12);
            --amber:          #f59e0b; --amber-dim: rgba(245,158,11,0.12); --amber-glow: rgba(245,158,11,0.25);
            --orange:         #f97316;
            --rose:           #e11d48;
            --cyan:           #06b6d4;
            --purple:         #a855f7;
            --slate:          #64748b;
            --red:            #f87171;
            
            --text-primary:   #f1f5f9;
            --text-secondary: #94a3b8;
            --text-muted:     #475569;
            
            --radius-md:      10px;
            --radius-lg:      14px;
            --radius-xl:      20px;
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
            background-image: radial-gradient(ellipse 80% 50% at 50% -20%, rgba(16,185,129,0.06) 0%, transparent 60%);
        }
        
        .container { max-width: 1560px; margin: 0 auto; padding: 2rem 1.5rem; }

        /* ─── HEADER ─── */
        .page-header { text-align: center; margin-bottom: 3.5rem; margin-top: 1rem; }
        .page-title {
            font-size: clamp(2rem, 4vw, 3rem); font-weight: 700;
            color: var(--text-primary); letter-spacing: -0.03em; line-height: 1.1; margin-bottom: 0.75rem;
        }
        .page-title span {
            background: linear-gradient(135deg, var(--emerald), #047857);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }
        .page-subtitle { color: var(--text-secondary); font-size: 1.1rem; margin-bottom: 0.5rem; }
        .page-description { color: var(--text-muted); font-size: 0.95rem; max-width: 600px; margin: 0 auto; }

        /* ─── QUICK STATS ─── */
        .quick-stats {
            background: var(--bg-surface); border: 1px solid var(--border);
            border-radius: var(--radius-xl); padding: 2rem; margin-bottom: 3.5rem;
            box-shadow: 0 10px 30px -10px rgba(0,0,0,0.5);
        }
        .stats-title { font-size: 1.2rem; font-weight: 700; color: var(--text-primary); margin-bottom: 1.5rem; text-align: center; text-transform: uppercase; letter-spacing: 0.05em; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 1rem; }
        
        .stat-card { 
            text-align: center; padding: 1.5rem 1rem; background: var(--bg-elevated); 
            border: 1px solid var(--border); border-radius: var(--radius-lg); 
            transition: all var(--transition); 
        }
        .stat-card:hover { transform: translateY(-4px); border-color: rgba(255,255,255,0.15); background: var(--bg-hover); }
        .stat-value { font-size: 2rem; font-weight: 700; color: var(--emerald); margin-bottom: 0.25rem; font-family: var(--font-mono); line-height: 1;}
        .stat-desc { color: var(--text-secondary); font-size: 0.85rem; font-weight: 500; text-transform: uppercase; letter-spacing: 0.05em; }

        /* ─── SECTION HEADERS ─── */
        .section-header {
            font-size: 1.25rem; font-weight: 700; color: var(--text-primary); 
            margin-bottom: 1.5rem; padding-left: 1rem; border-left: 4px solid var(--emerald);
            display: flex; align-items: center; gap: 10px;
        }
        .section-header i { color: var(--emerald); }

        /* ─── MANAGEMENT GRID ─── */
        .management-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(340px, 1fr));
            gap: 1.5rem; margin-bottom: 3rem;
        }

        .management-card {
            background: var(--bg-surface); border: 1px solid var(--border);
            border-radius: var(--radius-xl); padding: 2rem; position: relative;
            overflow: hidden; display: flex; flex-direction: column;
            text-decoration: none; color: inherit; transition: all var(--transition);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); min-height: 320px;
        }
        .management-card::before {
            content: ''; position: absolute; top: 0; left: -100%; width: 100%; height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.03), transparent);
            transition: left 0.8s ease; pointer-events: none;
        }
        .management-card:hover { transform: translateY(-4px); box-shadow: 0 15px 35px -10px rgba(0,0,0,0.5); }
        .management-card:hover::before { left: 100%; }

        /* Card Specific Hover Borders */
        .management-card:hover { border-color: rgba(16,185,129,0.4); } /* Default Emerald Hover */

        .card-icon {
            width: 64px; height: 64px; border-radius: var(--radius-lg);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.6rem; color: white; box-shadow: 0 8px 16px rgba(0,0,0,0.3); 
            margin-bottom: 1.5rem; flex-shrink: 0; position: relative;
        }

        /* Icon Colors */
        .card-icon.purchases { background: linear-gradient(135deg, var(--blue), #1d4ed8); }
        .card-icon.feeding { background: linear-gradient(135deg, var(--amber), #b45309); }
        .card-icon.group-feed { background: linear-gradient(135deg, var(--orange), #c2410c); }
        .card-icon.group-med { background: linear-gradient(135deg, #65a30d, #3f6212); } /* Lime */
        .card-icon.group-vit { background: linear-gradient(135deg, var(--rose), #9f1239); }
        .card-icon.group-chk { background: linear-gradient(135deg, var(--cyan), #0891b2); }
        .card-icon.group-vac { background: linear-gradient(135deg, var(--purple), #5b21b6); }
        .card-icon.group-sales { background: linear-gradient(135deg, var(--emerald), #064e3b); }
        .card-icon.group-mortality { background: linear-gradient(135deg, var(--slate), #1e293b); }
        .card-icon.revert { background: linear-gradient(135deg, var(--amber), #b45309); border: 1px solid rgba(245,158,11,0.5); }

        .card-title { font-size: 1.25rem; font-weight: 700; color: #fff; margin-bottom: 0.5rem; }
        .card-description { color: var(--text-secondary); font-size: 0.9rem; line-height: 1.5; margin-bottom: 1rem; flex-grow: 1; }
        
        .transaction-fields {
            background: var(--bg-elevated); border: 1px dashed var(--border);
            border-radius: var(--radius-md); padding: 1rem; margin-bottom: 1.5rem;
        }
        .field-list { color: var(--text-muted); font-size: 0.8rem; line-height: 1.5; font-family: var(--font-mono); }
        .field-list .field-title { color: var(--emerald); font-weight: 700; font-family: var(--font); margin-bottom: 0.25rem; text-transform: uppercase; letter-spacing: 0.05em; font-size: 0.75rem;}

        .card-stats {
            display: flex; justify-content: space-between; align-items: flex-end;
            padding-top: 1.25rem; border-top: 1px solid var(--border); margin-top: auto;
        }
        
        .card-action {
            font-size: 0.85rem; font-weight: 600; color: var(--text-secondary);
            transition: color var(--transition); display: flex; align-items: center; gap: 6px;
        }
        .management-card:hover .card-action { color: var(--emerald); }

        /* ─── ADMIN ZONE (REVERSALS) ─── */
        .admin-zone {
            border: 1px solid var(--amber); border-radius: var(--radius-xl);
            padding: 2.5rem 2rem 2rem 2rem; background: var(--amber-dim);
            position: relative; margin-top: 4rem; box-shadow: 0 0 30px var(--amber-glow);
        }
        .admin-badge {
            position: absolute; top: -14px; left: 50%; transform: translateX(-50%);
            background: var(--amber); color: #000; padding: 6px 20px; border-radius: 99px;
            font-weight: 800; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.1em;
            box-shadow: 0 4px 12px rgba(0,0,0,0.5); display: flex; align-items: center; gap: 6px;
        }

        .management-card.reversal-card { border-color: rgba(245, 158, 11, 0.3); background: var(--bg-surface); min-height: 220px; }
        .management-card.reversal-card:hover { border-color: var(--amber); box-shadow: 0 15px 35px rgba(245, 158, 11, 0.15); transform: translateY(-4px); }
        .management-card.reversal-card .card-title { color: #fff; }
        .management-card.reversal-card .card-action { color: var(--amber); margin-top: auto; }
        .management-card.reversal-card:hover .card-action { color: #fbbf24; }

        /* ─── RESPONSIVE ─── */
        @media (max-width: 768px) {
            .container { padding: 1rem; }
            .page-header { margin-bottom: 2rem;}
            .management-grid { grid-template-columns: 1fr; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .admin-zone { padding: 2.5rem 1rem 1rem 1rem; }
        }
        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<div class="container">

    <header class="page-header">
        <div class="header-info">
            <h1 class="page-title">Transaction <span>Management Center</span></h1>
            <p class="page-subtitle">Comprehensive Farm Transaction System</p>
            <p class="page-description">Select any transaction module below to execute and log daily farm operations.</p>
        </div>
    </header>

    <div class="quick-stats">
        <h2 class="stats-title">System Overview</h2>
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?= number_format($stats['todays_trans']) ?></div>
                <div class="stat-desc">Today's Trans.</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= number_format($stats['active_animals']) ?></div>
                <div class="stat-desc">Active Animals</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= number_format($stats['farms']) ?></div>
                <div class="stat-desc">Farms</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= number_format($stats['buildings']) ?></div>
                <div class="stat-desc">Buildings</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= number_format($stats['pens']) ?></div>
                <div class="stat-desc">Pens</div>
            </div>
        </div>
    </div>

    <h2 class="section-header"><i class="fa-solid fa-clipboard-list"></i> Core Operations &amp; Records</h2>
    
    <div class="management-grid">
        
        <a href="purchase_dashboard.php" class="management-card">
            <div class="card-icon purchases"><i class="fa-solid fa-cart-shopping"></i></div>
            <h3 class="card-title">Purchases Dashboard</h3>
            <p class="card-description">Record procurement transactions, manage supplier information, and track costs for farm supplies and equipment.</p>
            <div class="transaction-fields">
                <div class="field-list">
                    <div class="field-title">Data Matrix:</div>
                    Date • Category • Item Name • Quantity • Unit Cost • Total Cost
                </div>
            </div>
            <div class="card-stats">
                <div class="card-action">Procurement <i class="fa-solid fa-arrow-right"></i></div>
            </div>
        </a>

        <a href="single_feed_management.php" class="management-card">
            <div class="card-icon feeding"><i class="fa-solid fa-bowl-food"></i></div>
            <h3 class="card-title">Individual Feeding</h3>
            <p class="card-description">Record specialized diets or measured feed consumption for specific high-value animals (e.g., lactating sows or boars).</p>
            <div class="transaction-fields">
                <div class="field-list">
                    <div class="field-title">Data Matrix:</div>
                    Date • Tag No. • Feed Type • Quantity (kg) • Remarks
                </div>
            </div>
            <div class="card-stats">
                <div class="card-action">Feed Animal <i class="fa-solid fa-arrow-right"></i></div>
            </div>
        </a>

        <a href="group_feed_management.php" class="management-card">
            <div class="card-icon group-feed"><i class="fa-solid fa-wheat-awn"></i></div>
            <h3 class="card-title">Group Feeding</h3>
            <p class="card-description">Execute bulk feed logging for entire pens or buildings. Ideal for nursery, grower, and finisher blocks.</p>
            <div class="transaction-fields">
                <div class="field-list">
                    <div class="field-title">Data Matrix:</div>
                    Date • Target Pen • Feed Name • Total Bags/Kg • Distribution
                </div>
            </div>
            <div class="card-stats">
                <div class="card-action">Batch Feed <i class="fa-solid fa-arrow-right"></i></div>
            </div>
        </a>

        <a href="group_medication.php" class="management-card">
            <div class="card-icon group-med"><i class="fa-solid fa-pills"></i></div>
            <h3 class="card-title">Medication (Single/Batch)</h3>
            <p class="card-description">Apply medical treatments and log prescriptions to a single animal or multiple animals simultaneously.</p>
            <div class="transaction-fields">
                <div class="field-list">
                    <div class="field-title">Data Matrix:</div>
                    Date • Target (Tag/Pen) • Medicine • Dosage • Total Qty
                </div>
            </div>
            <div class="card-stats">
                <div class="card-action">Administer <i class="fa-solid fa-arrow-right"></i></div>
            </div>
        </a>

        <a href="group_vitamins.php" class="management-card">
            <div class="card-icon group-vit"><i class="fa-solid fa-flask-vial"></i></div>
            <h3 class="card-title">Vitamins (Single/Batch)</h3>
            <p class="card-description">Distribute nutritional supplements to specific animals or a whole group via water systems or feed mixing.</p>
            <div class="transaction-fields">
                <div class="field-list">
                    <div class="field-title">Data Matrix:</div>
                    Date • Target (Tag/Pen) • Supplement • Mix Ratio • Remarks
                </div>
            </div>
            <div class="card-stats">
                <div class="card-action">Supplement <i class="fa-solid fa-arrow-right"></i></div>
            </div>
        </a>

        <a href="group_checkup.php" class="management-card">
            <div class="card-icon group-chk"><i class="fa-solid fa-stethoscope"></i></div>
            <h3 class="card-title">Health Check-Ups</h3>
            <p class="card-description">Perform routine health inspections and log veterinary notes on individual animals or on a pen-by-pen basis.</p>
            <div class="transaction-fields">
                <div class="field-list">
                    <div class="field-title">Data Matrix:</div>
                    Date • Target (Tag/Pen) • Condition • Remarks • Flagged Issues
                </div>
            </div>
            <div class="card-stats">
                <div class="card-action">Log Checkup <i class="fa-solid fa-arrow-right"></i></div>
            </div>
        </a>

        <a href="group_vaccination.php" class="management-card">
            <div class="card-icon group-vac"><i class="fa-solid fa-syringe"></i></div>
            <h3 class="card-title">Vaccination (Single/Batch)</h3>
            <p class="card-description">Execute scheduled immunization programs for specific animals, pens, or entire building zones.</p>
            <div class="transaction-fields">
                <div class="field-list">
                    <div class="field-title">Data Matrix:</div>
                    Date • Target (Tag/Pen) • Vaccine Name • Batch No • Total Doses
                </div>
            </div>
            <div class="card-stats">
                <div class="card-action">Vaccinate <i class="fa-solid fa-arrow-right"></i></div>
            </div>
        </a>

        <a href="group_animal_sales.php" class="management-card">
            <div class="card-icon group-sales"><i class="fa-solid fa-money-bill-trend-up"></i></div>
            <h3 class="card-title">Livestock Sales</h3>
            <p class="card-description">Process outbound sales for a single animal or wholesale batches. Track buyer info and incoming revenue.</p>
            <div class="transaction-fields">
                <div class="field-list">
                    <div class="field-title">Data Matrix:</div>
                    Date • Target (Tag/Pen) • Total Heads • Total Wt • Price/Head • Buyer
                </div>
            </div>
            <div class="card-stats">
                <div class="card-action">Process Sale <i class="fa-solid fa-arrow-right"></i></div>
            </div>
        </a>

        <a href="group_mortality.php" class="management-card">
            <div class="card-icon group-mortality"><i class="fa-solid fa-skull-crossbones"></i></div>
            <h3 class="card-title">Mortality Records</h3>
            <p class="card-description">Log mortality events and track biological causes for individual animals or mass casualty pen events.</p>
            <div class="transaction-fields">
                <div class="field-list">
                    <div class="field-title">Data Matrix:</div>
                    Date • Target (Tag/Pen) • Total Heads Lost • Cause • Remarks
                </div>
            </div>
            <div class="card-stats">
                <div class="card-action">Log Mortality <i class="fa-solid fa-arrow-right"></i></div>
            </div>
        </a>
    </div>

    <?php if ($isSuperAdmin): ?>
    <div class="admin-zone">
        <div class="admin-badge"><i class="fa-solid fa-triangle-exclamation"></i> Super Admin Zone: Reversals</div>
        
        <div class="management-grid" style="margin-bottom: 0;">
            <a href="reverse_feeding_transaction.php" class="management-card reversal-card">
                <div class="card-icon revert"><i class="fa-solid fa-rotate-left"></i></div>
                <h3 class="card-title">Undo Feeding</h3>
                <p class="card-description">Review access logs and reverse feeding transactions. Automatically restores inventory stock limits.</p>
                <div class="card-action">View Logs <i class="fa-solid fa-arrow-right"></i></div>
            </a>
            
            <a href="reverse_medication_transaction.php" class="management-card reversal-card">
                <div class="card-icon revert"><i class="fa-solid fa-rotate-left"></i></div>
                <h3 class="card-title">Undo Medication</h3>
                <p class="card-description">Reverse administered medicines for individuals or batches. Restores pharmacy inventory.</p>
                <div class="card-action">View Logs <i class="fa-solid fa-arrow-right"></i></div>
            </a>
            
            <a href="reverse_vitamin_transaction.php" class="management-card reversal-card">
                <div class="card-icon revert"><i class="fa-solid fa-rotate-left"></i></div>
                <h3 class="card-title">Undo Vitamins</h3>
                <p class="card-description">Reverse vitamin and supplement usage logs. Restores nutritional inventory.</p>
                <div class="card-action">View Logs <i class="fa-solid fa-arrow-right"></i></div>
            </a>
            
            <a href="reverse_checkup_transaction.php" class="management-card reversal-card">
                <div class="card-icon revert"><i class="fa-solid fa-rotate-left"></i></div>
                <h3 class="card-title">Undo Checkup</h3>
                <p class="card-description">Delete incorrect veterinary checkup records and clear flagged medical statuses.</p>
                <div class="card-action">View Logs <i class="fa-solid fa-arrow-right"></i></div>
            </a>
            
            <a href="reverse_vaccination_transaction.php" class="management-card reversal-card">
                <div class="card-icon revert"><i class="fa-solid fa-rotate-left"></i></div>
                <h3 class="card-title">Undo Vaccination</h3>
                <p class="card-description">Reverse vaccination records and automatically recalculate biological inventory deductions.</p>
                <div class="card-action">View Logs <i class="fa-solid fa-arrow-right"></i></div>
            </a>

            <a href="reverse_sale_transaction.php" class="management-card reversal-card">
                <div class="card-icon revert"><i class="fa-solid fa-rotate-left"></i></div>
                <h3 class="card-title">Reverse Sales</h3>
                <p class="card-description">Cancel outbound sales invoices. Marks affected animals back to 'Active' status.</p>
                <div class="card-action">View Logs <i class="fa-solid fa-arrow-right"></i></div>
            </a>
            
            <a href="reverse_mortality_transaction.php" class="management-card reversal-card">
                <div class="card-icon revert"><i class="fa-solid fa-rotate-left"></i></div>
                <h3 class="card-title">Reverse Mortality</h3>
                <p class="card-description">Revive animals marked as deceased by mistake and restore their active status in the system.</p>
                <div class="card-action">View Logs <i class="fa-solid fa-arrow-right"></i></div>
            </a>
        </div>
    </div>
    <?php endif; ?>

</div>
</body>
</html>