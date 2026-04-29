<?php
// views/reports.php
error_reporting(0);
ini_set('display_errors', 0);
$page = "reports";
include '../config/Connection.php';

include '../security/checkAccess.php';
checkAccess('reports');
include '../common/navbar.php';
include '../common/chat_support.php';
include '../functions/getUsersLocation.php';

// --- FETCH DYNAMIC STATS ---
$stats = [
    'report_types' => 13, // Number of modules available on this page
    'today_records' => 0,
    'month_records' => 0
];

try {
    if ($USER_LOCATION_ != 1000) {
        $loc_id = (int)$USER_LOCATION_;
        
        // Count today's data entries for this location
        $today_sql = "SELECT 
            (SELECT COUNT(*) FROM animal_records WHERE DATE(CREATED_AT) = CURDATE() AND LOCATION_ID = $loc_id) +
            (SELECT COUNT(*) FROM ITEMS WHERE DATE(CREATED_AT) = CURDATE() AND LOCATION_ID = $loc_id) +
            (SELECT COUNT(*) FROM feed_transactions t JOIN animal_records a ON t.ANIMAL_ID = a.ANIMAL_ID WHERE DATE(t.TRANSACTION_DATE) = CURDATE() AND a.LOCATION_ID = $loc_id) +
            (SELECT COUNT(*) FROM treatment_transactions t JOIN animal_records a ON t.ANIMAL_ID = a.ANIMAL_ID WHERE DATE(t.TRANSACTION_DATE) = CURDATE() AND a.LOCATION_ID = $loc_id) +
            (SELECT COUNT(*) FROM animal_misc_fees t JOIN animal_records a ON t.ANIMAL_ID = a.ANIMAL_ID WHERE DATE(t.CREATED_AT) = CURDATE() AND a.LOCATION_ID = $loc_id)";
        $stats['today_records'] = $conn->query($today_sql)->fetchColumn();

        // Count this month's data entries for this location
        $month_sql = "SELECT 
            (SELECT COUNT(*) FROM animal_records WHERE MONTH(CREATED_AT) = MONTH(CURDATE()) AND YEAR(CREATED_AT) = YEAR(CURDATE()) AND LOCATION_ID = $loc_id) +
            (SELECT COUNT(*) FROM ITEMS WHERE MONTH(CREATED_AT) = MONTH(CURDATE()) AND YEAR(CREATED_AT) = YEAR(CURDATE()) AND LOCATION_ID = $loc_id) +
            (SELECT COUNT(*) FROM feed_transactions t JOIN animal_records a ON t.ANIMAL_ID = a.ANIMAL_ID WHERE MONTH(t.TRANSACTION_DATE) = MONTH(CURDATE()) AND YEAR(t.TRANSACTION_DATE) = YEAR(CURDATE()) AND a.LOCATION_ID = $loc_id) +
            (SELECT COUNT(*) FROM treatment_transactions t JOIN animal_records a ON t.ANIMAL_ID = a.ANIMAL_ID WHERE MONTH(t.TRANSACTION_DATE) = MONTH(CURDATE()) AND YEAR(t.TRANSACTION_DATE) = YEAR(CURDATE()) AND a.LOCATION_ID = $loc_id) +
            (SELECT COUNT(*) FROM animal_misc_fees t JOIN animal_records a ON t.ANIMAL_ID = a.ANIMAL_ID WHERE MONTH(t.CREATED_AT) = MONTH(CURDATE()) AND YEAR(t.CREATED_AT) = YEAR(CURDATE()) AND a.LOCATION_ID = $loc_id)";
        $stats['month_records'] = $conn->query($month_sql)->fetchColumn();
        
    } else {
        // Super Admin (All Locations)
        $today_sql = "SELECT 
            (SELECT COUNT(*) FROM animal_records WHERE DATE(CREATED_AT) = CURDATE()) +
            (SELECT COUNT(*) FROM ITEMS WHERE DATE(CREATED_AT) = CURDATE()) +
            (SELECT COUNT(*) FROM feed_transactions WHERE DATE(TRANSACTION_DATE) = CURDATE()) +
            (SELECT COUNT(*) FROM treatment_transactions WHERE DATE(TRANSACTION_DATE) = CURDATE()) +
            (SELECT COUNT(*) FROM animal_misc_fees WHERE DATE(CREATED_AT) = CURDATE())";
        $stats['today_records'] = $conn->query($today_sql)->fetchColumn();

        $month_sql = "SELECT 
            (SELECT COUNT(*) FROM animal_records WHERE MONTH(CREATED_AT) = MONTH(CURDATE()) AND YEAR(CREATED_AT) = YEAR(CURDATE())) +
            (SELECT COUNT(*) FROM ITEMS WHERE MONTH(CREATED_AT) = MONTH(CURDATE()) AND YEAR(CREATED_AT) = YEAR(CURDATE())) +
            (SELECT COUNT(*) FROM feed_transactions WHERE MONTH(TRANSACTION_DATE) = MONTH(CURDATE()) AND YEAR(TRANSACTION_DATE) = YEAR(CURDATE())) +
            (SELECT COUNT(*) FROM treatment_transactions WHERE MONTH(TRANSACTION_DATE) = MONTH(CURDATE()) AND YEAR(TRANSACTION_DATE) = YEAR(CURDATE())) +
            (SELECT COUNT(*) FROM animal_misc_fees WHERE MONTH(CREATED_AT) = MONTH(CURDATE()) AND YEAR(CREATED_AT) = YEAR(CURDATE()))";
        $stats['month_records'] = $conn->query($month_sql)->fetchColumn();
    }
} catch (Exception $e) {
    error_log("Report stats error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Farm Reports Dashboard | FarmPro</title>
    
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
            --border-active:  rgba(20,184,166,0.5); /* Teal Accent */
            
            /* Theme Colors */
            --teal:           #14b8a6; --teal-dim: rgba(20,184,166,0.12); --teal-glow: rgba(20,184,166,0.25);
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
            background-image: radial-gradient(ellipse 80% 50% at 50% -20%, rgba(20,184,166,0.06) 0%, transparent 60%);
        }
        
        .container { max-width: 1560px; margin: 0 auto; padding: 2rem 1.5rem; }

        /* ─── HEADER ─── */
        .page-header { text-align: center; margin-bottom: 3.5rem; margin-top: 1rem; }
        .page-title {
            font-size: clamp(2rem, 4vw, 3rem); font-weight: 700;
            color: var(--text-primary); letter-spacing: -0.03em; line-height: 1.1; margin-bottom: 0.75rem;
        }
        .page-title span {
            background: linear-gradient(135deg, var(--teal), #0f766e);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }
        .page-subtitle { color: var(--text-secondary); font-size: 1.1rem; margin-bottom: 0.5rem; }
        .page-description { color: var(--text-muted); font-size: 0.95rem; max-width: 600px; margin: 0 auto; }

        /* ─── SEARCH BAR ─── */
        .search-filter-section {
            background: var(--bg-surface); border: 1px solid var(--border);
            border-radius: var(--radius-xl); padding: 1.5rem;
            margin-bottom: 2.5rem; box-shadow: 0 10px 30px -10px rgba(0,0,0,0.5);
            max-width: 800px; margin-left: auto; margin-right: auto;
        }

        .search-bar { position: relative; display: flex; gap: 1rem; }
        .search-icon {
            position: absolute; left: 1.25rem; top: 50%; transform: translateY(-50%);
            color: var(--text-muted); font-size: 1.1rem; pointer-events: none;
        }
        .search-input {
            flex: 1; padding: 14px 16px 14px 3rem; background: var(--bg-elevated);
            border: 1px solid var(--border); border-radius: var(--radius-md);
            color: var(--text-primary); font-size: 1rem; font-family: var(--font);
            outline: none; transition: all var(--transition);
        }
        .search-input:focus { border-color: var(--teal); box-shadow: 0 0 0 3px var(--teal-glow); background: var(--bg-hover); }
        .search-input::placeholder { color: var(--text-muted); }

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
        .stat-value { font-size: 2rem; font-weight: 700; color: var(--teal); margin-bottom: 0.25rem; font-family: var(--font-mono); line-height: 1;}
        .stat-desc { color: var(--text-secondary); font-size: 0.85rem; font-weight: 500; text-transform: uppercase; letter-spacing: 0.05em; }

        /* ─── SECTION HEADERS ─── */
        .section-header {
            font-size: 1.25rem; font-weight: 700; color: var(--text-primary); 
            margin-bottom: 1.5rem; padding-left: 1rem; border-left: 4px solid var(--teal);
            display: flex; align-items: center; gap: 10px;
        }
        .section-header i { color: var(--teal); }

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
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); min-height: 250px;
        }
        .management-card::before {
            content: ''; position: absolute; top: 0; left: -100%; width: 100%; height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.03), transparent);
            transition: left 0.8s ease; pointer-events: none;
        }
        .management-card:hover { transform: translateY(-4px); box-shadow: 0 15px 35px -10px rgba(0,0,0,0.5); }
        .management-card:hover::before { left: 100%; }

        /* Card Specific Hover Borders */
        .management-card:hover { border-color: rgba(20,184,166,0.4); } /* Default Teal Hover */

        .card-icon {
            width: 64px; height: 64px; border-radius: var(--radius-lg);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.6rem; color: white; box-shadow: 0 8px 16px rgba(0,0,0,0.3); 
            margin-bottom: 1.5rem; flex-shrink: 0; position: relative;
        }

        /* Icon Colors */
        .card-icon.animal { background: linear-gradient(135deg, var(--amber), #b45309); }
        .card-icon.users { background: linear-gradient(135deg, #84cc16, #4d7c0f); } /* Lime */
        .card-icon.medicine { background: linear-gradient(135deg, var(--cyan), #0891b2); }
        .card-icon.feeds { background: linear-gradient(135deg, var(--purple), #7e22ce); }
        .card-icon.housing { background: linear-gradient(135deg, var(--red), #b91c1c); }
        .card-icon.equipment { background: linear-gradient(135deg, var(--blue), #1d4ed8); }
        .card-icon.sanitation { background: linear-gradient(135deg, var(--teal), #0f766e); }
        .card-icon.breeding { background: linear-gradient(135deg, var(--rose), #be123c); }
        .card-icon.admin { background: linear-gradient(135deg, var(--orange), #c2410c); }
        .card-icon.maintenance { background: linear-gradient(135deg, #8b5cf6, #5b21b6); }
        .card-icon.utilities { background: linear-gradient(135deg, var(--cyan), #0369a1); }
        .card-icon.vitamins { background: linear-gradient(135deg, var(--emerald), #047857); }
        .card-icon.vaccine { background: linear-gradient(135deg, var(--amber), #b45309); }
        .card-icon.others { background: linear-gradient(135deg, var(--slate), #1e293b); }
        .card-icon.audit { background: linear-gradient(135deg, var(--red), #991b1b); }
        .card-icon.financial { background: linear-gradient(135deg, var(--emerald), #065f46); }
        .card-icon.usage { background: linear-gradient(135deg, var(--blue), #0369a1); }

        .card-title { font-size: 1.25rem; font-weight: 700; color: #fff; margin-bottom: 0.5rem; }
        .card-description { color: var(--text-secondary); font-size: 0.9rem; line-height: 1.5; margin-bottom: 1rem; flex-grow: 1; }
        
        .card-stats {
            display: flex; justify-content: space-between; align-items: flex-end;
            padding-top: 1.25rem; border-top: 1px solid var(--border); margin-top: auto;
        }
        .stat-group { display: flex; flex-direction: column; gap: 2px; }
        .stat-group .num { font-size: 1.1rem; font-weight: 700; color: #fff; font-family: var(--font-mono); line-height: 1; }
        .stat-group .lbl { font-size: 0.7rem; color: var(--text-muted); text-transform: uppercase; font-weight: 600; letter-spacing: 0.05em; }
        
        .card-action {
            font-size: 0.85rem; font-weight: 600; color: var(--text-secondary);
            transition: color var(--transition); display: flex; align-items: center; gap: 6px;
        }
        .management-card:hover .card-action { color: var(--teal); }

        .no-results-message {
            grid-column: 1 / -1; text-align: center; padding: 4rem 2rem;
            color: var(--text-muted); border: 1px dashed var(--border); border-radius: var(--radius-xl);
            background: rgba(255,255,255,0.01);
        }
        .no-results-message i { font-size: 3rem; opacity: 0.2; margin-bottom: 1rem; display: block; }
        .no-results-message strong { color: var(--text-primary); }

        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

        /* ─── RESPONSIVE ─── */
        @media (max-width: 768px) {
            .container { padding: 1rem; }
            .page-header { margin-bottom: 2rem;}
            .management-grid { grid-template-columns: 1fr; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .search-bar { flex-direction: column; }
            .search-input { width: 100%; }
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
            <h1 class="page-title">Reports <span>Dashboard</span></h1>
            <p class="page-subtitle">Comprehensive Farm Reporting System</p>
            <p class="page-description">Generate detailed reports and insights for all farm operations, inventory, and activities.</p>
        </div>
    </header>

    <div class="quick-stats">
        <h2 class="stats-title">Reporting Overview</h2>
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?= $stats['report_types'] ?></div>
                <div class="stat-desc">Report Modules</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= number_format($stats['today_records']) ?></div>
                <div class="stat-desc">Records Today</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= number_format($stats['month_records']) ?></div>
                <div class="stat-desc">Records This Month</div>
            </div>
        </div>
    </div>

    <div class="search-filter-section">
        <div class="search-bar">
            <i class="fa-solid fa-magnifying-glass search-icon"></i>
            <input type="text" id="searchInput" class="search-input" placeholder="Search reports by name, category, or keywords...">
        </div>
    </div>

    <h2 class="section-header"><i class="fa-solid fa-chart-pie"></i> Available Reports</h2>
    
    <div class="management-grid">
        
        <a href="animal_report.php" class="management-card">
            <div class="card-icon animal"><i class="fa-solid fa-cow"></i></div>
            <h3 class="card-title">Animal Report</h3>
            <p class="card-description">Comprehensive livestock reports including population statistics, health records, and individual tracking data.</p>
            <div class="card-stats">
                <div class="card-action">Generate <i class="fa-solid fa-arrow-right"></i></div>
            </div>
        </a>

        <a href="active_users_report.php" class="management-card">
            <div class="card-icon users"><i class="fa-solid fa-users"></i></div>
            <h3 class="card-title">Active Users Report</h3>
            <p class="card-description">Monitor user activity, access logs, and system usage patterns across all farm management personnel.</p>
            <div class="card-stats">
                <div class="card-action">View <i class="fa-solid fa-arrow-right"></i></div>
            </div>
        </a>

        <a href="medicine_report.php" class="management-card">
            <div class="card-icon medicine"><i class="fa-solid fa-pills"></i></div>
            <h3 class="card-title">Medicine Inventory Report</h3>
            <p class="card-description">Track medicine inventory levels, usage rates, expiration dates, and procurement history.</p>
            <div class="card-stats">
                <div class="card-action">Analyze <i class="fa-solid fa-arrow-right"></i></div>
            </div>
        </a>

        <a href="feeds_report.php" class="management-card">
            <div class="card-icon feeds"><i class="fa-solid fa-wheat-awn"></i></div>
            <h3 class="card-title">Feeds Inventory Report</h3>
            <p class="card-description">Monitor feed inventory, stock thresholds, supplier information, and general nutritional data.</p>
            <div class="card-stats">
                <div class="card-action">Review <i class="fa-solid fa-arrow-right"></i></div>
            </div>
        </a>

        <a href="housing_report.php" class="management-card">
            <div class="card-icon housing"><i class="fa-solid fa-house-chimney-window"></i></div>
            <h3 class="card-title">Housing &amp; Facilities</h3>
            <p class="card-description">Overview of buildings, pens, enclosures, capacity utilization, and infrastructure maintenance status.</p>
            <div class="card-stats">
                <div class="card-action">Inspect <i class="fa-solid fa-arrow-right"></i></div>
            </div>
        </a>

        <a href="equipment_report.php" class="management-card">
            <div class="card-icon equipment"><i class="fa-solid fa-tractor"></i></div>
            <h3 class="card-title">Farm Equipment Report</h3>
            <p class="card-description">Track equipment inventory, usage logs, maintenance schedules, and operational efficiency metrics.</p>
            <div class="card-stats">
                <div class="card-action">Check <i class="fa-solid fa-arrow-right"></i></div>
            </div>
        </a>

        <a href="sanitation_report.php" class="management-card">
            <div class="card-icon sanitation"><i class="fa-solid fa-pump-soap"></i></div>
            <h3 class="card-title">Sanitation &amp; Waste</h3>
            <p class="card-description">Monitor cleaning schedules, waste disposal records, biosecurity measures, and hygiene compliance data.</p>
            <div class="card-stats">
                <div class="card-action">Monitor <i class="fa-solid fa-arrow-right"></i></div>
            </div>
        </a>

        <a href="breeding_report.php" class="management-card">
            <div class="card-icon breeding"><i class="fa-solid fa-dna"></i></div>
            <h3 class="card-title">Breeding &amp; Reproduction</h3>
            <p class="card-description">Track breeding programs, reproductive cycles, genetic lineages, and offspring performance metrics.</p>
            <div class="card-stats">
                <div class="card-action">Track <i class="fa-solid fa-arrow-right"></i></div>
            </div>
        </a>

        <a href="admin_records_report.php" class="management-card">
            <div class="card-icon admin"><i class="fa-solid fa-file-contract"></i></div>
            <h3 class="card-title">Administration Report</h3>
            <p class="card-description">Comprehensive administrative documentation, regulatory compliance records, and official certifications.</p>
            <div class="card-stats">
                <div class="card-action">Access <i class="fa-solid fa-arrow-right"></i></div>
            </div>
        </a>

        <a href="maintenance_report.php" class="management-card">
            <div class="card-icon maintenance"><i class="fa-solid fa-screwdriver-wrench"></i></div>
            <h3 class="card-title">Maintenance Report</h3>
            <p class="card-description">Monitor maintenance activities, spare parts inventory, repair histories, and preventive care schedules.</p>
            <div class="card-stats">
                <div class="card-action">Review <i class="fa-solid fa-arrow-right"></i></div>
            </div>
        </a>

        <a href="utilities_report.php" class="management-card">
            <div class="card-icon utilities"><i class="fa-solid fa-bolt"></i></div>
            <h3 class="card-title">Utilities &amp; Consumables</h3>
            <p class="card-description">Track utility usage, consumable supplies, energy consumption, and resource efficiency metrics.</p>
            <div class="card-stats">
                <div class="card-action">Analyze <i class="fa-solid fa-arrow-right"></i></div>
            </div>
        </a>

        <a href="vitamins_report.php" class="management-card">
            <div class="card-icon vitamins"><i class="fa-solid fa-flask"></i></div>
            <h3 class="card-title">Vitamins Inventory</h3>
            <p class="card-description">Monitor vitamin inventory levels, supplement stock thresholds, and expiration data.</p>
            <div class="card-stats">
                <div class="card-action">Report <i class="fa-solid fa-arrow-right"></i></div>
            </div>
        </a>

        <a href="vaccine_report.php" class="management-card">
            <div class="card-icon vaccine"><i class="fa-solid fa-syringe"></i></div>
            <h3 class="card-title">Vaccine Inventory</h3>
            <p class="card-description">Track vaccine inventory, procurement records, expiration dates, and safe storage requirements.</p>
            <div class="card-stats">
                <div class="card-action">View <i class="fa-solid fa-arrow-right"></i></div>
            </div>
        </a>

        <a href="others_report.php" class="management-card">
            <div class="card-icon others"><i class="fa-solid fa-box-open"></i></div>
            <h3 class="card-title">Others Report</h3>
            <p class="card-description">Miscellaneous reports including custom queries, special requests, and ad-hoc analytical reports.</p>
            <div class="card-stats">
                <div class="card-action">Create <i class="fa-solid fa-arrow-right"></i></div>
            </div>
        </a>

        <a href="audit_log_report.php" class="management-card">
            <div class="card-icon audit"><i class="fa-solid fa-shield-halved"></i></div>
            <h3 class="card-title">Audit Log Report</h3>
            <p class="card-description">Comprehensive system audit trails, user activity logs, and security compliance monitoring reports.</p>
            <div class="card-stats">
                <div class="card-action">Audit <i class="fa-solid fa-arrow-right"></i></div>
            </div>
        </a>
        
        <a href="animal_sales_reports.php" class="management-card">
            <div class="card-icon financial"><i class="fa-solid fa-sack-dollar"></i></div>
            <h3 class="card-title">Animal Sales Reports</h3>
            <p class="card-description">Track animal sales, revenue streams, buyer demographics, and sales performance metrics.</p>
            <div class="card-stats">
                <div class="card-action">Financial <i class="fa-solid fa-arrow-right"></i></div>
            </div>
        </a>

        <a href="feeds_usage_report.php" class="management-card">
            <div class="card-icon usage"><i class="fa-solid fa-chart-area"></i></div>
            <h3 class="card-title">Feeds Usage Report</h3>
            <p class="card-description">Analyze feed consumption rates, conversion efficiency, and overall feed usage across different pens and buildings.</p>
            <div class="card-stats">
                <div class="card-action">Generate <i class="fa-solid fa-arrow-right"></i></div>
            </div>
        </a>

        <a href="vaccines_usage_report.php" class="management-card">
            <div class="card-icon usage"><i class="fa-solid fa-chart-simple"></i></div>
            <h3 class="card-title">Vaccines Usage Report</h3>
            <p class="card-description">Track vaccine administration, monitor batch usage, and analyze vaccination costs over time.</p>
            <div class="card-stats">
                <div class="card-action">Generate <i class="fa-solid fa-arrow-right"></i></div>
            </div>
        </a>

        <a href="vitamins_usage_report.php" class="management-card">
            <div class="card-icon usage"><i class="fa-solid fa-chart-column"></i></div>
            <h3 class="card-title">Vitamins Usage Report</h3>
            <p class="card-description">Review vitamin and supplement administration trends, tracking distribution volumes and overall expenses.</p>
            <div class="card-stats">
                <div class="card-action">Generate <i class="fa-solid fa-arrow-right"></i></div>
            </div>
        </a>

        <a href="medicine_usage_report.php" class="management-card">
            <div class="card-icon usage"><i class="fa-solid fa-chart-pie"></i></div>
            <h3 class="card-title">Medicine Usage Report</h3>
            <p class="card-description">Monitor therapeutic drug usage, track treatment regimens, and evaluate overall medication expenditures.</p>
            <div class="card-stats">
                <div class="card-action">Generate <i class="fa-solid fa-arrow-right"></i></div>
            </div>
        </a>
        
    </div>
</div>

<script>
    const searchInput = document.getElementById('searchInput');
    const categoryCards = document.querySelectorAll('.management-card');

    searchInput.addEventListener('input', searchCategories);
    searchInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') searchCategories();
    });

    function searchCategories() {
        const searchTerm = searchInput.value.toLowerCase().trim();
        let visibleCount = 0;

        categoryCards.forEach(card => {
            const title = card.querySelector('.card-title').textContent.toLowerCase();
            const subtitle = card.querySelector('.card-description').textContent.toLowerCase();
            
            // Collect any additional stats or labels for robust searching
            const stats = Array.from(card.querySelectorAll('.stat-group'))
                .map(stat => stat.textContent.toLowerCase())
                .join(' ');

            const searchableContent = `${title} ${subtitle} ${stats}`;

            if (searchTerm === '' || searchableContent.includes(searchTerm)) {
                card.style.display = 'flex';
                visibleCount++;
                card.style.animation = 'fadeIn 0.3s ease';
            } else {
                card.style.display = 'none';
            }
        });

        showNoResultsMessage(visibleCount, searchTerm);
    }

    function showNoResultsMessage(count, term) {
        const existingMessage = document.querySelector('.no-results-message');
        if (existingMessage) existingMessage.remove();

        if (count === 0 && term !== '') {
            const grid = document.querySelector('.management-grid');
            const message = document.createElement('div');
            message.className = 'no-results-message';
            message.innerHTML = `
                <i class="fa-solid fa-magnifying-glass"></i>
                <h3 style="color: #fff; margin-bottom: 0.5rem; font-size: 1.25rem;">No reports found for "<strong>${term}</strong>"</h3>
                <p style="margin:0;">Try searching for: medicine, sales, usage, audit, etc.</p>
            `;
            grid.appendChild(message);
        }
    }
</script>
</body>
</html>