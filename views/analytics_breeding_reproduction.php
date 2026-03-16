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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Breeding & Reproduction Analytics</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
    
    <style>
        /* --- THEME: PINK / FUCHSIA --- */
        body { 
            font-family: system-ui, -apple-system, sans-serif; 
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); 
            color: #e2e8f0; 
            margin: 0; padding-bottom: 40px; 
        }
        .container { max-width: 1400px; margin: 0 auto; padding: 2rem; }
        
        /* Back Link Style */
        .back-link {
            display: inline-flex; align-items: center; gap: 8px; 
            text-decoration: none; color: #94a3b8; font-weight: 600; 
            font-size: 0.95rem; margin-bottom: 20px; transition: color 0.2s;
        }
        .back-link:hover { color: white; }

        .header { text-align: center; margin-bottom: 2rem; }
        .title { 
            font-size: 2.2rem; font-weight: 800; 
            background: linear-gradient(135deg, #db2777, #be185d); /* Pink Gradient */
            -webkit-background-clip: text; -webkit-text-fill-color: transparent; 
            margin-bottom: 0.5rem;
        }
        .subtitle { color: #94a3b8; font-size: 1rem; margin: 0; }

        /* KPI Grid */
        .kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
        .kpi-card { 
            background: rgba(30, 41, 59, 0.6); border: 1px solid rgba(255,255,255,0.05); 
            border-radius: 16px; padding: 1.5rem; backdrop-filter: blur(10px); 
            position: relative; overflow: hidden;
        }
        .kpi-card::after { 
            content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 4px; 
            background: linear-gradient(90deg, #db2777, #9d174d); 
        }
        .kpi-label { color: #94a3b8; font-size: 0.8rem; font-weight: 600; text-transform: uppercase; letter-spacing: 1px; }
        .kpi-value { font-size: 2.2rem; font-weight: 800; color: #fff; margin: 0.5rem 0; }
        .kpi-sub { font-size: 0.85rem; color: #64748b; }

        .text-pink { color: #f472b6; }
        .text-green { color: #4ade80; }
        .text-rose { color: #fb7185; }

        /* Chart Grid */
        .charts-container { 
            display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem; margin-bottom: 2rem; 
        }
        .chart-box { 
            background: rgba(30, 41, 59, 0.6); border: 1px solid rgba(255,255,255,0.05); 
            border-radius: 16px; padding: 1.5rem; min-height: 350px; display: flex; flex-direction: column;
        }
        .chart-title { font-size: 1.1rem; font-weight: 700; color: #e2e8f0; margin-bottom: 1rem; }

        /* Buttons */
        .btn-group { display: flex; justify-content: flex-end; margin-bottom: 1rem; }
        .btn { 
            padding: 10px 20px; background: rgba(219, 39, 119, 0.1); 
            color: #f472b6; border: 1px solid rgba(219, 39, 119, 0.3); 
            border-radius: 8px; text-decoration: none; font-weight: 600; 
            transition: all 0.2s; 
        }
        .btn:hover { background: rgba(219, 39, 119, 0.2); transform: translateY(-2px); }

        @media (max-width: 1024px) { .charts-container { grid-template-columns: 1fr; } }
    </style>
</head>
<body>

<div class="container">
    <a href="analytics_dashboard.php" class="back-link">
        <i class="fa-solid fa-arrow-left"></i>
        Back to Analytics Dashboard
    </a>

    <div class="header">
        <h1 class="title">Breeding & Reproduction Analytics</h1>
        <p class="subtitle">Herd fertility, farrowing performance, and litter health.</p>
    </div>

    <div class="kpi-grid">
        <div class="kpi-card">
            <div class="kpi-label">Total Piglets Born</div>
            <div class="kpi-value text-pink"><?= number_format($kpi['total_piglets']) ?></div>
            <div class="kpi-sub">Across <?= number_format($kpi['total_farrowings']) ?> Farrowings</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">Avg. Litter Size</div>
            <div class="kpi-value"><?= number_format($kpi['avg_litter_size'], 1) ?></div>
            <div class="kpi-sub">Piglets per Sow</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">Survival Rate</div>
            <div class="kpi-value text-green"><?= number_format($survival_rate, 1) ?>%</div>
            <div class="kpi-sub"><?= number_format($kpi['live_born']) ?> Live Births</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">Birth Mortality</div>
            <div class="kpi-value text-rose"><?= number_format($kpi['mortality_count']) ?></div>
            <div class="kpi-sub">Dead / Mummified</div>
        </div>
    </div>

    <div class="btn-group">
        <a href="animal_sow_status.php" class="btn">Manage Sow Status →</a>
    </div>

    <div class="charts-container">
        
        <div class="chart-box">
            <div class="chart-title">📈 Piglet Production Trend (Last 12 Months)</div>
            <div style="flex-grow: 1; position: relative;">
                <canvas id="trendChart"></canvas>
            </div>
        </div>

        <div class="chart-box">
            <div class="chart-title">🐖 Current Herd Reproductive Status</div>
            <div style="flex-grow: 1; position: relative;">
                <canvas id="statusChart"></canvas>
            </div>
        </div>

        <div class="chart-box">
            <div class="chart-title">🏆 Top 5 Productive Sows (Total Born)</div>
            <div style="flex-grow: 1; position: relative;">
                <canvas id="sowChart"></canvas>
            </div>
        </div>

        <div class="chart-box">
            <div class="chart-title">🩺 Litter Health Ratios</div>
            <div style="flex-grow: 1; position: relative;">
                <canvas id="healthChart"></canvas>
            </div>
        </div>

    </div>
</div>

<script>
    Chart.defaults.color = '#94a3b8';
    Chart.defaults.borderColor = 'rgba(255,255,255,0.05)';
    Chart.defaults.font.family = 'system-ui';

    const trendData = <?= json_encode($trend_data) ?>;
    new Chart(document.getElementById('trendChart'), {
        type: 'line',
        data: {
            labels: trendData.map(d => d.month_year),
            datasets: [{
                label: 'Total Born',
                data: trendData.map(d => d.total_born),
                borderColor: '#db2777',
                backgroundColor: 'rgba(219, 39, 119, 0.1)',
                borderWidth: 2,
                fill: true,
                tension: 0.4
            },
            {
                label: 'Live Born',
                data: trendData.map(d => d.live_born),
                borderColor: '#4ade80',
                backgroundColor: 'transparent',
                borderWidth: 2,
                borderDash: [5, 5],
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: true } },
            scales: { y: { beginAtZero: true } }
        }
    });

    const statusData = <?= json_encode($status_data) ?>;
    new Chart(document.getElementById('statusChart'), {
        type: 'doughnut',
        data: {
            labels: statusData.map(d => d.STATUS_NAME),
            datasets: [{
                data: statusData.map(d => d.count),
                backgroundColor: ['#db2777', '#9333ea', '#2563eb', '#e11d48', '#f59e0b', '#10b981'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'right', labels: { boxWidth: 12 } } }
        }
    });

    const sowData = <?= json_encode($top_sows) ?>;
    new Chart(document.getElementById('sowChart'), {
        type: 'bar',
        data: {
            labels: sowData.map(d => d.TAG_NO),
            datasets: [{
                label: 'Total Piglets Produced',
                data: sowData.map(d => d.total_piglets),
                backgroundColor: 'rgba(244, 114, 182, 0.7)',
                borderColor: '#f472b6',
                borderWidth: 1,
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true } }
        }
    });

    const healthData = <?= json_encode($health_data) ?>;
    new Chart(document.getElementById('healthChart'), {
        type: 'pie',
        data: {
            labels: ['Live', 'Dead', 'Mummified'],
            datasets: [{
                data: [healthData.live, healthData.dead, healthData.mummified],
                backgroundColor: ['#4ade80', '#f87171', '#94a3b8'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom' } }
        }
    });
</script>

</body>
</html>