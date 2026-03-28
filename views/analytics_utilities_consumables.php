<?php
// reports/utilities_analytics.php
error_reporting(0);
ini_set('display_errors', 0);
$page = "analytics";
include '../config/Connection.php';

include '../security/checkAccess.php';
checkAccess('utilities_consumables_analytics');
include '../common/navbar.php';
include '../common/chat_support.php';

try {
    if (!isset($conn)) { throw new Exception("Database connection failed."); }

    // --- 1. KPI: ASSET METRICS ---
    // Counts items under 'Utilities & Consumables' (ITEM_TYPE_ID = 9)
    $kpi_sql = "SELECT 
                    COUNT(*) as distinct_items,
                    COALESCE(SUM(TOTAL_COST), 0) as total_value,
                    COALESCE(SUM(QUANTITY), 0) as total_units
                FROM items 
                WHERE ITEM_TYPE_ID = 9 AND STATUS = 1";
    $kpi = $conn->query($kpi_sql)->fetch(PDO::FETCH_ASSOC);

    // Calculate Average Cost per Unit
    $avg_cost = ($kpi['total_units'] > 0) 
        ? ($kpi['total_value'] / $kpi['total_units']) 
        : 0;

    // --- 2. CHART: COST DISTRIBUTION (Pie) ---
    $dist_sql = "SELECT ITEM_NAME, TOTAL_COST 
                 FROM items 
                 WHERE ITEM_TYPE_ID = 9 AND STATUS = 1
                 ORDER BY TOTAL_COST DESC 
                 LIMIT 5";
    $dist_data = $conn->query($dist_sql)->fetchAll(PDO::FETCH_ASSOC);

    // --- 3. CHART: SPENDING TREND (Line) ---
    $trend_sql = "SELECT 
                    DATE_FORMAT(CREATED_AT, '%Y-%m') as month_year,
                    SUM(TOTAL_COST) as cost
                  FROM items
                  WHERE ITEM_TYPE_ID = 9 AND STATUS = 1
                  AND CREATED_AT >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                  GROUP BY month_year
                  ORDER BY month_year ASC";
    $trend_data = $conn->query($trend_sql)->fetchAll(PDO::FETCH_ASSOC);

    // --- 4. CHART: INVENTORY COUNT (Bar) ---
    $qty_sql = "SELECT ITEM_NAME, QUANTITY 
                FROM items 
                WHERE ITEM_TYPE_ID = 9 AND STATUS = 1
                ORDER BY QUANTITY DESC 
                LIMIT 5";
    $qty_data = $conn->query($qty_sql)->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log("Utilities Analytics Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Utilities Analytics | FarmPro</title>
    
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
            --border-active:  rgba(14,165,233,0.5); /* Cyan/Sky Accent */
            
            --cyan:           #0ea5e9;
            --cyan-dim:       rgba(14,165,233,0.12);
            --cyan-glow:      rgba(14,165,233,0.25);
            --sky:            #38bdf8;
            --blue:           #3b82f6;
            --emerald:        #10b981;
            --slate:          #94a3b8;
            
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
            background-image: radial-gradient(ellipse 80% 50% at 50% -20%, rgba(14,165,233,0.06) 0%, transparent 60%);
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
            color: var(--cyan); background: var(--cyan-dim); border: 1px solid rgba(14,165,233,0.2);
            padding: 6px 12px; border-radius: 99px;
        }

        /* ─── HEADER ─── */
        .page-header { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 2.5rem; gap: 1.5rem; flex-wrap: wrap; }
        .header-info h1 { font-size: clamp(1.8rem, 4vw, 2.5rem); font-weight: 700; margin: 0 0 0.5rem 0; color: #fff; letter-spacing: -0.02em;}
        .header-info h1 span { background: linear-gradient(135deg, var(--cyan), #0369a1); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .header-info p { color: var(--text-secondary); font-size: 0.95rem; margin: 0; }

        .btn-view {
            display: inline-flex; align-items: center; justify-content: center; gap: 8px;
            padding: 12px 24px; background: var(--bg-elevated); border: 1px solid var(--border);
            border-radius: var(--radius-md); color: var(--text-primary); font-weight: 700; font-size: 0.95rem; font-family: var(--font);
            cursor: pointer; transition: var(--transition); text-decoration: none; white-space: nowrap;
        }
        .btn-view:hover { background: var(--cyan-dim); border-color: var(--cyan); color: #fff; transform: translateY(-2px);}

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
        .stat-cyan::before { background: var(--cyan); }
        .stat-blue::before { background: var(--blue); }
        .stat-emerald::before { background: var(--emerald); }
        .stat-slate::before { background: var(--slate); }

        .kpi-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem; }
        .kpi-title { color: var(--text-secondary); font-size: 0.75rem; text-transform: uppercase; font-weight: 700; letter-spacing: 0.05em;}
        .kpi-icon { width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 1rem; color: #fff;}
        .stat-cyan .kpi-icon { background: linear-gradient(135deg, var(--cyan), #0369a1); }
        .stat-blue .kpi-icon { background: linear-gradient(135deg, var(--blue), #1d4ed8); }
        .stat-emerald .kpi-icon { background: linear-gradient(135deg, var(--emerald), #047857); }
        .stat-slate .kpi-icon { background: linear-gradient(135deg, var(--slate), #475569); }

        .kpi-value { font-size: 2.5rem; font-weight: 800; font-family: var(--font-mono); line-height: 1; margin-bottom: 0.5rem;}
        .stat-cyan .kpi-value { color: var(--cyan); }
        .stat-blue .kpi-value { color: var(--blue); }
        .stat-emerald .kpi-value { color: var(--emerald); }
        .stat-slate .kpi-value { color: var(--text-primary); }

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
        .chart-box.full-width { grid-column: 1 / -1; }

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
        <span class="page-badge"><i class="fa-solid fa-chart-line"></i> Operational Data</span>
    </div>

    <header class="page-header">
        <div class="header-info">
            <h1>Utilities & Consumables <span>Analytics</span></h1>
            <p>Operational supplies, utility costs, and consumption tracking.</p>
        </div>
        <a href="utilities_report.php" class="btn-view"><i class="fa-solid fa-file-invoice"></i> View Detailed Report</a>
    </header>

    <div class="kpi-grid">
        <div class="kpi-card stat-cyan">
            <div class="kpi-header">
                <div class="kpi-title">Total Expense Value</div>
                <div class="kpi-icon"><i class="fa-solid fa-receipt"></i></div>
            </div>
            <div class="kpi-value">₱<?= number_format($kpi['total_value'] / 1000, 1) ?>k</div>
            <div class="kpi-sub">Cost of Consumables</div>
        </div>

        <div class="kpi-card stat-blue">
            <div class="kpi-header">
                <div class="kpi-title">Distinct Items</div>
                <div class="kpi-icon"><i class="fa-solid fa-faucet-drip"></i></div>
            </div>
            <div class="kpi-value"><?= number_format($kpi['distinct_items']) ?></div>
            <div class="kpi-sub">Utility Types</div>
        </div>

        <div class="kpi-card stat-emerald">
            <div class="kpi-header">
                <div class="kpi-title">Total Units</div>
                <div class="kpi-icon"><i class="fa-solid fa-boxes-stacked"></i></div>
            </div>
            <div class="kpi-value"><?= number_format($kpi['total_units']) ?></div>
            <div class="kpi-sub">Physical Stock Count</div>
        </div>

        <div class="kpi-card stat-slate">
            <div class="kpi-header">
                <div class="kpi-title">Avg. Cost / Unit</div>
                <div class="kpi-icon"><i class="fa-solid fa-scale-balanced"></i></div>
            </div>
            <div class="kpi-value" style="color: var(--text-secondary);">₱<?= number_format($avg_cost, 0) ?></div>
            <div class="kpi-sub">Per Consumable Item</div>
        </div>
    </div>

    <div class="charts-container">
        <div class="chart-box">
            <div class="chart-title"><i class="fa-solid fa-bolt" style="color:var(--cyan);"></i> Utility Spending Trend (Last 12 Months)</div>
            <div class="chart-canvas-wrapper">
                <canvas id="trendChart"></canvas>
            </div>
        </div>

        <div class="chart-box">
            <div class="chart-title"><i class="fa-solid fa-chart-pie" style="color:var(--blue);"></i> Cost Breakdown by Item</div>
            <div class="chart-canvas-wrapper">
                <canvas id="distChart"></canvas>
            </div>
        </div>

        <div class="chart-box full-width">
            <div class="chart-title"><i class="fa-solid fa-list-check" style="color:var(--emerald);"></i> Top 5 Consumables by Quantity</div>
            <div class="chart-canvas-wrapper" style="height: 350px;"> <canvas id="qtyChart"></canvas>
            </div>
        </div>
    </div>
</div>

<script>
    const trendData = <?= json_encode($trend_data) ?>;
    const distData = <?= json_encode($dist_data) ?>;
    const qtyData = <?= json_encode($qty_data) ?>;

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
                borderColor: '#0ea5e9', // Cyan
                backgroundColor: 'rgba(14, 165, 233, 0.1)',
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

    /* ---- Cost Distribution Doughnut ---- */
    new Chart(document.getElementById('distChart'), {
        type: 'doughnut',
        data: {
            labels: distData.map(d => d.ITEM_NAME),
            datasets: [{
                data: distData.map(d => d.TOTAL_COST),
                backgroundColor: ['#0284c7', '#0369a1', '#0ea5e9', '#38bdf8', '#7dd3fc'], // Blue/Cyan palette
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

    /* ---- Quantity Bar Chart ---- */
    new Chart(document.getElementById('qtyChart'), {
        type: 'bar',
        data: {
            labels: qtyData.map(d => d.ITEM_NAME),
            datasets: [{
                label: 'Units Available',
                data: qtyData.map(d => d.QUANTITY),
                backgroundColor: 'rgba(16, 185, 129, 0.6)', // Emerald
                borderColor: '#10b981',
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

    // Optional: Re-render chart legend position if user rotates phone
    window.addEventListener('resize', () => {
        const doughnutChart = Chart.getChart('distChart');
        if (doughnutChart) {
            doughnutChart.options.plugins.legend.position = legendPos();
            doughnutChart.update();
        }
    });
</script>

</body>
</html>