<?php
// reports/analytics_vaccines.php
error_reporting(0);
ini_set('display_errors', 0);
$page = "analytics";
include '../config/Connection.php';

include '../security/checkAccess.php';
checkAccess('vaccine_analytics');
include '../common/navbar.php';
include '../common/chat_support.php';

try {
    if (!isset($conn)) { throw new Exception("Database connection failed."); }

    // --- 1. KPI: USAGE & INVENTORY METRICS ---
    
    // Usage Totals (From vaccination_records)
    $usage_sql = "SELECT 
                    COUNT(*) as total_vaccinations,
                    COUNT(DISTINCT ANIMAL_ID) as animals_vaccinated,
                    COALESCE(SUM(VACCINATION_COST + VACCINE_COST), 0) as total_spent
                  FROM vaccination_records";
    $usage = $conn->query($usage_sql)->fetch(PDO::FETCH_ASSOC);

    // Inventory Totals (From vaccines table)
    $inv_sql = "SELECT 
                    COUNT(*) as active_vaccines,
                    COALESCE(SUM(TOTAL_COST), 0) as inventory_value,
                    SUM(CASE WHEN TOTAL_STOCK < 50 THEN 1 ELSE 0 END) as low_stock_count
                FROM vaccines";
    $inv = $conn->query($inv_sql)->fetch(PDO::FETCH_ASSOC);

    // --- 2. CHART: SPENDING TREND (Line) ---
    $trend_sql = "SELECT 
                    DATE_FORMAT(VACCINATION_DATE, '%Y-%m') as month_year,
                    SUM(VACCINATION_COST + VACCINE_COST) as cost
                  FROM vaccination_records
                  WHERE VACCINATION_DATE >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                  GROUP BY month_year
                  ORDER BY month_year ASC";
    $trend_data = $conn->query($trend_sql)->fetchAll(PDO::FETCH_ASSOC);

    // --- 3. CHART: TOP VACCINES BY USAGE (Bar) ---
    $top_vacs_sql = "SELECT 
                        v.SUPPLY_NAME, 
                        COUNT(vr.VACCINATION_ID) as usage_count
                     FROM vaccination_records vr
                     LEFT JOIN vaccines v ON vr.ITEM_ID = v.SUPPLY_ID
                     GROUP BY v.SUPPLY_NAME
                     ORDER BY usage_count DESC
                     LIMIT 5";
    $top_vacs = $conn->query($top_vacs_sql)->fetchAll(PDO::FETCH_ASSOC);

    // --- 4. CHART: INVENTORY VALUE DISTRIBUTION (Pie) ---
    $stock_val_sql = "SELECT SUPPLY_NAME, TOTAL_COST 
                      FROM vaccines 
                      WHERE TOTAL_COST > 0
                      ORDER BY TOTAL_COST DESC 
                      LIMIT 5";
    $stock_val = $conn->query($stock_val_sql)->fetchAll(PDO::FETCH_ASSOC);

    // --- 5. CHART: MOST VACCINATED ANIMALS (Horizontal Bar) ---
    $top_animal_sql = "SELECT 
                            ar.TAG_NO, 
                            COUNT(vr.VACCINATION_ID) as txn_count
                        FROM vaccination_records vr
                        LEFT JOIN animal_records ar ON vr.ANIMAL_ID = ar.ANIMAL_ID
                        GROUP BY ar.TAG_NO
                        ORDER BY txn_count DESC
                        LIMIT 5";
    $top_animals = $conn->query($top_animal_sql)->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log("Vaccination Analytics Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Vaccination Analytics | FarmPro</title>
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            --border-active:  rgba(45,212,191,0.5); /* Teal Accent */
            
            --teal:           #2dd4bf;
            --teal-dim:       rgba(45,212,191,0.12);
            --teal-glow:      rgba(45,212,191,0.25);
            --blue:           #3b82f6;
            --emerald:        #10b981;
            --amber:          #f59e0b;
            --red:            #ef4444;
            --purple:         #a855f7;
            --pink:           #f472b6;
            
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
            background-image: radial-gradient(ellipse 80% 50% at 50% -20%, rgba(45,212,191,0.06) 0%, transparent 60%);
        }
        .container { max-width: 1560px; margin: 0 auto; padding: 2rem 1.5rem; }

        /* ─── TOP BAR ─── */
        .top-bar { display: flex; align-items: center; justify-content: space-between; margin-bottom: 2rem; gap: 1rem; flex-wrap: wrap; }
        .back-link {
            display: inline-flex; align-items: center; gap: 8px; text-decoration: none;
            color: var(--text-secondary); font-size: 0.875rem; font-weight: 500;
            padding: 8px 14px; background: var(--bg-elevated); border: 1px solid var(--border);
            border-radius: var(--radius-md); transition: all var(--transition);
        }
        .back-link:hover { color: var(--text-primary); border-color: var(--border-active); background: var(--bg-hover); }

        .page-badge {
            display: inline-flex; align-items: center; gap: 6px; font-size: 0.75rem;
            font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase;
            color: var(--teal); background: var(--teal-dim); border: 1px solid rgba(45,212,191,0.2);
            padding: 6px 12px; border-radius: 99px;
        }

        /* ─── HEADER ─── */
        .page-header { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 2.5rem; gap: 1.5rem; flex-wrap: wrap; }
        .header-info h1 { font-size: clamp(1.8rem, 4vw, 2.5rem); font-weight: 700; margin: 0 0 0.5rem 0; color: #fff; letter-spacing: -0.02em;}
        .header-info h1 span { background: linear-gradient(135deg, var(--teal), #0f766e); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .header-info p { color: var(--text-secondary); font-size: 0.95rem; margin: 0; }

        .btn-view {
            display: inline-flex; align-items: center; justify-content: center; gap: 8px;
            padding: 12px 24px; background: var(--bg-elevated); border: 1px solid var(--border);
            border-radius: var(--radius-md); color: var(--text-primary); font-weight: 700; font-size: 0.95rem; font-family: var(--font);
            cursor: pointer; transition: var(--transition); text-decoration: none; white-space: nowrap;
        }
        .btn-view:hover { background: var(--teal-dim); border-color: var(--teal); color: var(--teal); transform: translateY(-2px);}

        /* ─── DASHBOARD STATS ─── */
        .kpi-grid { 
            display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); 
            gap: 1.5rem; margin-bottom: 2.5rem; 
        }
        .kpi-card { 
            background: var(--bg-surface); border: 1px solid var(--border); 
            border-radius: var(--radius-xl); padding: 1.5rem; 
            box-shadow: var(--shadow-md); position: relative; overflow: hidden;
            display: flex; flex-direction: column; justify-content: space-between;
        }
        
        .kpi-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px; }
        .stat-teal::before { background: var(--teal); }
        .stat-blue::before { background: var(--blue); }
        .stat-emerald::before { background: var(--emerald); }
        .stat-red::before { background: var(--red); }

        .kpi-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem; }
        .kpi-title { color: var(--text-secondary); font-size: 0.75rem; text-transform: uppercase; font-weight: 700; letter-spacing: 0.05em;}
        .kpi-icon { width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 1rem; color: #fff;}
        .stat-teal .kpi-icon { background: linear-gradient(135deg, var(--teal), #0f766e); }
        .stat-blue .kpi-icon { background: linear-gradient(135deg, var(--blue), #1d4ed8); }
        .stat-emerald .kpi-icon { background: linear-gradient(135deg, var(--emerald), #047857); }
        .stat-red .kpi-icon { background: linear-gradient(135deg, var(--red), #991b1b); }

        .kpi-value { font-size: 2.5rem; font-weight: 800; font-family: var(--font-mono); line-height: 1; margin-bottom: 0.5rem;}
        .stat-teal .kpi-value { color: var(--teal); }
        .stat-blue .kpi-value { color: var(--blue); }
        .stat-emerald .kpi-value { color: var(--emerald); }
        .stat-red .kpi-value { color: var(--red); }

        .kpi-sub { font-size: 0.85rem; color: var(--text-muted); font-weight: 600;}

        /* ─── CHARTS GRID ─── */
        .charts-container { 
            display: grid; grid-template-columns: repeat(auto-fit, minmax(450px, 1fr)); gap: 1.5rem; 
            width: 100%; overflow: hidden; /* Prevent container blowout */
        }

        .chart-box { 
            background: var(--bg-surface); border: 1px solid var(--border); 
            border-radius: var(--radius-xl); padding: 1.5rem; 
            display: flex; flex-direction: column; width: 100%; max-width: 100%; 
            overflow: hidden; box-sizing: border-box; box-shadow: var(--shadow-md);
        }

        .chart-title { font-size: 1.05rem; font-weight: 700; color: #fff; margin: 0 0 1.5rem 0; display: flex; align-items: center; gap: 10px;}

        /* Chart canvas wrapper */
        .chart-canvas-wrapper {
            position: relative; width: 100%; max-width: 100%; height: 300px; margin: 0 auto;
        }

        .chart-canvas-wrapper canvas {
            width: 100% !important; /* Force canvas to respect wrapper */
            height: 100% !important;
        }

        /* ===== MOBILE OVERRIDES ===== */
        @media (max-width: 768px) {
            .container { padding: 1rem; }
            .page-header { flex-direction: column; align-items: flex-start; }
            .btn-view { width: 100%; }
            .charts-container { grid-template-columns: 1fr; }
            .chart-canvas-wrapper { height: 250px; }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="top-bar">
        <a href="analytics_dashboard.php" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back to Analytics Dashboard
        </a>
        <span class="page-badge"><i class="fa-solid fa-chart-line"></i> Performance Data</span>
    </div>

    <header class="page-header">
        <div class="header-info">
            <h1>Vaccination <span>Analytics</span></h1>
            <p>Immunization costs, coverage tracking, and inventory health overview.</p>
        </div>
        <a href="vaccination_report.php" class="btn-view"><i class="fa-solid fa-file-invoice"></i> View Detailed Report</a>
    </header>

    <div class="kpi-grid">
        <div class="kpi-card stat-teal">
            <div class="kpi-header">
                <div class="kpi-title">Total Spent</div>
                <div class="kpi-icon"><i class="fa-solid fa-hand-holding-dollar"></i></div>
            </div>
            <div class="kpi-value">₱<?= number_format($usage['total_spent'] / 1000, 1) ?>k</div>
            <div class="kpi-sub">Vaccines + Services</div>
        </div>

        <div class="kpi-card stat-blue">
            <div class="kpi-header">
                <div class="kpi-title">Total Vaccinations</div>
                <div class="kpi-icon"><i class="fa-solid fa-shield-virus"></i></div>
            </div>
            <div class="kpi-value"><?= number_format($usage['total_vaccinations']) ?></div>
            <div class="kpi-sub">Across <?= number_format($usage['animals_vaccinated']) ?> Animals</div>
        </div>

        <div class="kpi-card stat-emerald">
            <div class="kpi-header">
                <div class="kpi-title">Inventory Value</div>
                <div class="kpi-icon"><i class="fa-solid fa-box-archive"></i></div>
            </div>
            <div class="kpi-value">₱<?= number_format($inv['inventory_value'] / 1000, 1) ?>k</div>
            <div class="kpi-sub"><?= number_format($inv['active_vaccines']) ?> Vaccine Types</div>
        </div>

        <div class="kpi-card stat-red">
            <div class="kpi-header">
                <div class="kpi-title">Low Stock Alerts</div>
                <div class="kpi-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
            </div>
            <div class="kpi-value"><?= number_format($inv['low_stock_count']) ?></div>
            <div class="kpi-sub">Items below 50 units</div>
        </div>
    </div>

    <div class="charts-container">
        <div class="chart-box">
            <div class="chart-title"><i class="fa-solid fa-chart-line" style="color:var(--teal);"></i> Spending Trend (Last 12 Months)</div>
            <div class="chart-canvas-wrapper">
                <canvas id="trendChart"></canvas>
            </div>
        </div>

        <div class="chart-box">
            <div class="chart-title"><i class="fa-solid fa-chart-pie" style="color:var(--emerald);"></i> Stock Value by Vaccine</div>
            <div class="chart-canvas-wrapper">
                <canvas id="stockChart"></canvas>
            </div>
        </div>

        <div class="chart-box">
            <div class="chart-title"><i class="fa-solid fa-syringe" style="color:var(--blue);"></i> Top 5 Vaccines Administered</div>
            <div class="chart-canvas-wrapper">
                <canvas id="topVacsChart"></canvas>
            </div>
        </div>

        <div class="chart-box">
            <div class="chart-title"><i class="fa-solid fa-id-card-clip" style="color:var(--purple);"></i> Animals with Most Vaccinations</div>
            <div class="chart-canvas-wrapper">
                <canvas id="animalChart"></canvas>
            </div>
        </div>
    </div>
</div>

<script>
    const trendData = <?= json_encode($trend_data) ?>;
    const stockData = <?= json_encode($stock_val) ?>;
    const topVacs = <?= json_encode($top_vacs) ?>;
    const animalData = <?= json_encode($top_animals) ?>;

    /* ---- Global Chart.js defaults ---- */
    Chart.defaults.color       = '#94a3b8';
    Chart.defaults.borderColor = 'rgba(255,255,255,0.05)';
    Chart.defaults.font.family = "'DM Sans', system-ui, sans-serif";

    /* Responsive legend helper: bottom on small screens, right on large */
    function legendPos() {
        return window.innerWidth < 640 ? 'bottom' : 'right';
    }

    /* ---- Trend Line Chart ---- */
    new Chart(document.getElementById('trendChart'), {
        type: 'line',
        data: {
            labels: trendData.map(d => d.month_year),
            datasets: [{
                label: 'Cost (PHP)',
                data: trendData.map(d => d.cost),
                borderColor: '#2dd4bf', // Teal
                backgroundColor: 'rgba(45, 212, 191, 0.1)',
                borderWidth: 2,
                fill: true,
                tension: 0.4,
                pointRadius: 4,
                pointHoverRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false, // CRITICAL: Allows wrapper CSS to dictate height/width
            plugins: { legend: { display: false } },
            scales: { 
                y: { beginAtZero: true },
                x: { ticks: { maxRotation: 45, minRotation: 0 } }
            }
        }
    });

    /* ---- Stock Value Doughnut ---- */
    new Chart(document.getElementById('stockChart'), {
        type: 'doughnut',
        data: {
            labels: stockData.map(d => d.SUPPLY_NAME),
            datasets: [{
                data: stockData.map(d => d.TOTAL_COST),
                backgroundColor: ['#2dd4bf', '#0d9488', '#14b8a6', '#0f766e', '#ccfbf1'], // Teal shades
                borderWidth: 0,
                hoverOffset: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false, // CRITICAL
            plugins: { 
                legend: { 
                    position: legendPos(),
                    labels: { boxWidth: 12, padding: 14, font: { size: 12, family: "'DM Sans', sans-serif" } }
                } 
            }
        }
    });

    /* ---- Top Vaccines Bar ---- */
    new Chart(document.getElementById('topVacsChart'), {
        type: 'bar',
        data: {
            labels: topVacs.map(d => d.SUPPLY_NAME),
            datasets: [{
                label: 'Times Administered',
                data: topVacs.map(d => d.usage_count),
                backgroundColor: 'rgba(59, 130, 246, 0.6)', // Blue
                borderColor: '#3b82f6',
                borderWidth: 1,
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false, // CRITICAL
            plugins: { legend: { display: false } },
            scales: { 
                y: { beginAtZero: true },
                x: { ticks: { maxRotation: 45, minRotation: 0 } }
            }
        }
    });

    /* ---- Most Vaccinated Animals Horizontal Bar ---- */
    new Chart(document.getElementById('animalChart'), {
        type: 'bar',
        data: {
            labels: animalData.map(d => d.TAG_NO || 'Unknown'),
            datasets: [{
                label: 'Vaccinations Received',
                data: animalData.map(d => d.txn_count),
                backgroundColor: 'rgba(168, 85, 247, 0.6)', // Purple
                borderColor: '#a855f7',
                borderWidth: 1,
                borderRadius: 4
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false, // CRITICAL
            plugins: { legend: { display: false } },
            scales: { x: { beginAtZero: true } }
        }
    });

    // Optional: Re-render chart legend position if user rotates phone
    window.addEventListener('resize', () => {
        const doughnutChart = Chart.getChart('stockChart');
        if (doughnutChart) {
            doughnutChart.options.plugins.legend.position = legendPos();
            doughnutChart.update();
        }
    });
</script>

</body>
</html>