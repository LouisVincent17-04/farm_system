<?php
// reports/breeding_analytics.php
error_reporting(0);
ini_set('display_errors', 0);
$page = "analytics";
include '../config/Connection.php';

include '../security/checkAccess.php';
checkAccess('breeding_reproduction_analaytics');
include '../common/navbar.php';
include '../common/chat_support.php';

try {
    if (!isset($conn)) { throw new Exception("Database connection failed."); }

    // --- 1. KPI: FARROWING & PIGLET METRICS ---
    
    // Aggregates from sow_birthing_records
    $kpi_sql = "SELECT 
                    COUNT(*) as total_farrowings,
                    COALESCE(SUM(TOTAL_BORN), 0) as total_piglets,
                    COALESCE(SUM(ACTIVE_COUNT), 0) as live_born,
                    COALESCE(SUM(DEAD_COUNT + MUMMIFIED_COUNT), 0) as mortality_count,
                    AVG(TOTAL_BORN) as avg_litter_size
                FROM sow_birthing_records";
    $kpi = $conn->query($kpi_sql)->fetch(PDO::FETCH_ASSOC);

    // Calculate Survival Rate
    $survival_rate = ($kpi['total_piglets'] > 0) 
        ? ($kpi['live_born'] / $kpi['total_piglets']) * 100 
        : 0;

    // --- 2. CHART: CURRENT REPRODUCTIVE STATUS (Doughnut) ---
    $status_sql = "SELECT STATUS_NAME, COUNT(DISTINCT ANIMAL_ID) as count 
                   FROM sow_status_history 
                   WHERE IS_ACTIVE = 1 
                   GROUP BY STATUS_NAME";
    $status_data = $conn->query($status_sql)->fetchAll(PDO::FETCH_ASSOC);

    // --- 3. CHART: PIGLET PRODUCTION TREND (Line) ---
    $trend_sql = "SELECT 
                    DATE_FORMAT(DATE_FARROWED, '%Y-%m') as month_year,
                    SUM(TOTAL_BORN) as total_born,
                    SUM(ACTIVE_COUNT) as live_born
                  FROM sow_birthing_records
                  WHERE DATE_FARROWED >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                  GROUP BY month_year
                  ORDER BY month_year ASC";
    $trend_data = $conn->query($trend_sql)->fetchAll(PDO::FETCH_ASSOC);

    // --- 4. CHART: TOP PRODUCING SOWS (Bar) ---
    $top_sows_sql = "SELECT 
                        ar.TAG_NO, 
                        SUM(sbr.TOTAL_BORN) as total_piglets
                     FROM sow_birthing_records sbr
                     LEFT JOIN animal_records ar ON sbr.ANIMAL_ID = ar.ANIMAL_ID
                     GROUP BY ar.TAG_NO
                     ORDER BY total_piglets DESC
                     LIMIT 5";
    $top_sows = $conn->query($top_sows_sql)->fetchAll(PDO::FETCH_ASSOC);

    // --- 5. CHART: LITTER HEALTH BREAKDOWN (Pie/Polar) ---
    $health_sql = "SELECT 
                    SUM(ACTIVE_COUNT) as live,
                    SUM(DEAD_COUNT) as dead,
                    SUM(MUMMIFIED_COUNT) as mummified
                   FROM sow_birthing_records";
    $health_data = $conn->query($health_sql)->fetch(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log("Breeding Analytics Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Breeding Analytics | FarmPro</title>
    
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
            --border-active:  rgba(236,72,153,0.5); /* Pink Accent */
            
            --pink:           #ec4899;
            --pink-dim:       rgba(236,72,153,0.12);
            --pink-glow:      rgba(236,72,153,0.25);
            --fuchsia:        #d946ef;
            --emerald:        #10b981;
            --rose:           #f43f5e;
            --purple:         #a855f7;
            
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
            background-image: radial-gradient(ellipse 80% 50% at 50% -20%, rgba(236,72,153,0.06) 0%, transparent 60%);
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
            color: var(--pink); background: var(--pink-dim); border: 1px solid rgba(236,72,153,0.2);
            padding: 6px 12px; border-radius: 99px;
        }

        /* ─── HEADER ─── */
        .page-header { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 2.5rem; gap: 1.5rem; flex-wrap: wrap; }
        .header-info h1 { font-size: clamp(1.8rem, 4vw, 2.5rem); font-weight: 700; margin: 0 0 0.5rem 0; color: #fff; letter-spacing: -0.02em;}
        .header-info h1 span { background: linear-gradient(135deg, var(--pink), #be185d); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .header-info p { color: var(--text-secondary); font-size: 0.95rem; margin: 0; }

        .btn-view {
            display: inline-flex; align-items: center; justify-content: center; gap: 8px;
            padding: 12px 24px; background: var(--bg-elevated); border: 1px solid var(--border);
            border-radius: var(--radius-md); color: var(--text-primary); font-weight: 700; font-size: 0.95rem; font-family: var(--font);
            cursor: pointer; transition: var(--transition); text-decoration: none; white-space: nowrap;
        }
        .btn-view:hover { background: var(--pink-dim); border-color: var(--pink); color: #fff; transform: translateY(-2px);}

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
        .stat-pink::before { background: var(--pink); }
        .stat-fuchsia::before { background: var(--fuchsia); }
        .stat-emerald::before { background: var(--emerald); }
        .stat-rose::before { background: var(--rose); }

        .kpi-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem; }
        .kpi-title { color: var(--text-secondary); font-size: 0.75rem; text-transform: uppercase; font-weight: 700; letter-spacing: 0.05em;}
        .kpi-icon { width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 1rem; color: #fff;}
        .stat-pink .kpi-icon { background: linear-gradient(135deg, var(--pink), #be185d); }
        .stat-fuchsia .kpi-icon { background: linear-gradient(135deg, var(--fuchsia), #a21caf); }
        .stat-emerald .kpi-icon { background: linear-gradient(135deg, var(--emerald), #047857); }
        .stat-rose .kpi-icon { background: linear-gradient(135deg, var(--rose), #be123c); }

        .kpi-value { font-size: 2.5rem; font-weight: 800; font-family: var(--font-mono); line-height: 1; margin-bottom: 0.5rem;}
        .stat-pink .kpi-value { color: var(--pink); }
        .stat-fuchsia .kpi-value { color: var(--fuchsia); }
        .stat-emerald .kpi-value { color: var(--emerald); }
        .stat-rose .kpi-value { color: var(--rose); }

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
            <h1>Breeding <span>Analytics</span></h1>
            <p>Herd fertility, farrowing performance, and litter health statistics.</p>
        </div>
        <a href="animal_sow_status.php" class="btn-view"><i class="fa-solid fa-clipboard-list"></i> Manage Sow Status</a>
    </header>

    <div class="kpi-grid">
        <div class="kpi-card stat-pink">
            <div class="kpi-header">
                <div class="kpi-title">Total Piglets Born</div>
                <div class="kpi-icon"><i class="fa-solid fa-piggy-bank"></i></div>
            </div>
            <div class="kpi-value"><?= number_format($kpi['total_piglets']) ?></div>
            <div class="kpi-sub">Across <?= number_format($kpi['total_farrowings']) ?> Farrowings</div>
        </div>

        <div class="kpi-card stat-fuchsia">
            <div class="kpi-header">
                <div class="kpi-title">Avg. Litter Size</div>
                <div class="kpi-icon"><i class="fa-solid fa-chart-pie"></i></div>
            </div>
            <div class="kpi-value"><?= number_format($kpi['avg_litter_size'], 1) ?></div>
            <div class="kpi-sub">Piglets per Sow</div>
        </div>

        <div class="kpi-card stat-emerald">
            <div class="kpi-header">
                <div class="kpi-title">Survival Rate</div>
                <div class="kpi-icon"><i class="fa-solid fa-heart-pulse"></i></div>
            </div>
            <div class="kpi-value"><?= number_format($survival_rate, 1) ?>%</div>
            <div class="kpi-sub"><?= number_format($kpi['live_born']) ?> Live Births</div>
        </div>

        <div class="kpi-card stat-rose">
            <div class="kpi-header">
                <div class="kpi-title">Birth Mortality</div>
                <div class="kpi-icon"><i class="fa-solid fa-skull-crossbones"></i></div>
            </div>
            <div class="kpi-value"><?= number_format($kpi['mortality_count']) ?></div>
            <div class="kpi-sub">Dead / Mummified</div>
        </div>
    </div>

    <div class="charts-container">
        <div class="chart-box">
            <div class="chart-title"><i class="fa-solid fa-chart-line" style="color:var(--pink);"></i> Piglet Production Trend (Last 12 Months)</div>
            <div class="chart-canvas-wrapper">
                <canvas id="trendChart"></canvas>
            </div>
        </div>

        <div class="chart-box">
            <div class="chart-title"><i class="fa-solid fa-venus-mars" style="color:var(--fuchsia);"></i> Current Herd Reproductive Status</div>
            <div class="chart-canvas-wrapper">
                <canvas id="statusChart"></canvas>
            </div>
        </div>

        <div class="chart-box">
            <div class="chart-title"><i class="fa-solid fa-ranking-star" style="color:var(--purple);"></i> Top 5 Productive Sows (Total Born)</div>
            <div class="chart-canvas-wrapper">
                <canvas id="sowChart"></canvas>
            </div>
        </div>

        <div class="chart-box">
            <div class="chart-title"><i class="fa-solid fa-kit-medical" style="color:var(--emerald);"></i> Litter Health Ratios</div>
            <div class="chart-canvas-wrapper">
                <canvas id="healthChart"></canvas>
            </div>
        </div>
    </div>
</div>

<script>
    const trendData = <?= json_encode($trend_data) ?>;
    const statusData = <?= json_encode($status_data) ?>;
    const sowData = <?= json_encode($top_sows) ?>;
    const healthData = <?= json_encode($health_data) ?>;

    /* ---- Global Chart.js defaults ---- */
    Chart.defaults.color       = '#94a3b8';
    Chart.defaults.borderColor = 'rgba(255,255,255,0.05)';
    Chart.defaults.font.family = "'DM Sans', system-ui, sans-serif";

    /* Responsive legend helper: bottom on small screens, right on large */
    function legendPos() {
        return window.innerWidth < 640 ? 'bottom' : 'right';
    }

    /* ---- Production Trend Line Chart ---- */
    new Chart(document.getElementById('trendChart'), {
        type: 'line',
        data: {
            labels: trendData.map(d => d.month_year),
            datasets: [{
                label: 'Total Born',
                data: trendData.map(d => d.total_born),
                borderColor: '#ec4899', // Pink
                backgroundColor: 'rgba(236, 72, 153, 0.1)',
                borderWidth: 2,
                fill: true,
                tension: 0.4,
                pointRadius: 4,
                pointHoverRadius: 6
            },
            {
                label: 'Live Born',
                data: trendData.map(d => d.live_born),
                borderColor: '#10b981', // Emerald
                backgroundColor: 'transparent',
                borderWidth: 2,
                borderDash: [5, 5],
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false, // CRITICAL: Allows wrapper CSS to dictate height/width
            plugins: { 
                legend: { 
                    display: true, 
                    position: 'top',
                    labels: { font: { family: "'DM Sans', sans-serif" } }
                } 
            },
            scales: { 
                y: { beginAtZero: true },
                x: { ticks: { maxRotation: 45, minRotation: 0 } }
            }
        }
    });

    /* ---- Reproductive Status Doughnut ---- */
    new Chart(document.getElementById('statusChart'), {
        type: 'doughnut',
        data: {
            labels: statusData.map(d => d.STATUS_NAME),
            datasets: [{
                data: statusData.map(d => d.count),
                backgroundColor: ['#ec4899', '#d946ef', '#a855f7', '#f43f5e', '#f59e0b', '#10b981'],
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

    /* ---- Top Sows Bar Chart ---- */
    new Chart(document.getElementById('sowChart'), {
        type: 'bar',
        data: {
            labels: sowData.map(d => d.TAG_NO),
            datasets: [{
                label: 'Total Piglets Produced',
                data: sowData.map(d => d.total_piglets),
                backgroundColor: 'rgba(168, 85, 247, 0.6)', // Purple
                borderColor: '#a855f7',
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

    /* ---- Health Ratios Pie Chart ---- */
    new Chart(document.getElementById('healthChart'), {
        type: 'pie',
        data: {
            labels: ['Live', 'Dead', 'Mummified'],
            datasets: [{
                data: [healthData.live, healthData.dead, healthData.mummified],
                backgroundColor: ['#10b981', '#ef4444', '#64748b'], // Emerald, Red, Slate
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false, // CRITICAL
            plugins: { 
                legend: { 
                    position: 'bottom',
                    labels: { boxWidth: 12, padding: 14, font: { size: 12, family: "'DM Sans', sans-serif" } }
                } 
            }
        }
    });

    // Optional: Re-render chart legend position if user rotates phone
    window.addEventListener('resize', () => {
        const doughnutChart = Chart.getChart('statusChart');
        if (doughnutChart) {
            doughnutChart.options.plugins.legend.position = legendPos();
            doughnutChart.update();
        }
    });
</script>

</body>
</html>