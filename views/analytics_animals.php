<?php
// reports/animal_analytics.php
error_reporting(0);
ini_set('display_errors', 0);
$page = "analytics";
include '../config/Connection.php';

include '../security/checkAccess.php';
checkAccess('animals_livestock_analytics');
include '../common/navbar.php';
include '../common/chat_support.php';

try {
    if (!isset($conn)) { throw new Exception("Database connection failed."); }

    // --- 1. KPI: TOP LEVEL COUNTS ---
    $kpi_sql = "SELECT 
        COUNT(*) as total_records,
        SUM(CASE WHEN CURRENT_STATUS = 'Active' THEN 1 ELSE 0 END) as active_count,
        SUM(CASE WHEN CURRENT_STATUS = 'Sold' THEN 1 ELSE 0 END) as sold_count,
        SUM(CASE WHEN CURRENT_STATUS = 'Deceased' THEN 1 ELSE 0 END) as deceased_count,
        SUM(CASE WHEN CURRENT_STATUS = 'Quarantine' OR CURRENT_STATUS = 'Sick' THEN 1 ELSE 0 END) as sick_count
    FROM animal_records";
    $kpi = $conn->query($kpi_sql)->fetch(PDO::FETCH_ASSOC);

    // Calculate Mortality Rate
    $mortality_rate = ($kpi['total_records'] > 0) 
        ? ($kpi['deceased_count'] / $kpi['total_records']) * 100 
        : 0;

    // --- 2. CHART: STATUS DISTRIBUTION ---
    $status_sql = "SELECT CURRENT_STATUS as status_name, COUNT(*) as count 
                   FROM animal_records 
                   GROUP BY CURRENT_STATUS";
    $status_data = $conn->query($status_sql)->fetchAll(PDO::FETCH_ASSOC);

    // --- 3. CHART: ACTIVE POPULATION BY STAGE ---
    $stage_sql = "SELECT 
                    ac.STAGE_NAME, 
                    COUNT(ar.ANIMAL_ID) as count 
                  FROM animal_records ar
                  LEFT JOIN animal_classifications ac ON ar.CLASS_ID = ac.CLASS_ID
                  WHERE ar.CURRENT_STATUS = 'Active'
                  GROUP BY ac.STAGE_NAME 
                  ORDER BY count DESC";
    $stage_data = $conn->query($stage_sql)->fetchAll(PDO::FETCH_ASSOC);

    // --- 4. CHART: GENDER RATIO ---
    $gender_sql = "SELECT SEX, COUNT(*) as count 
                   FROM animal_records 
                   WHERE CURRENT_STATUS = 'Active' 
                   GROUP BY SEX";
    $gender_data = $conn->query($gender_sql)->fetchAll(PDO::FETCH_ASSOC);

    // --- 5. CHART: INTAKE TREND ---
    $intake_sql = "SELECT 
                    DATE_FORMAT(CREATED_AT, '%Y-%m') as month_year, 
                    COUNT(*) as count 
                   FROM animal_records 
                   WHERE CREATED_AT >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                   GROUP BY month_year 
                   ORDER BY month_year ASC";
    $intake_data = $conn->query($intake_sql)->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log("Animal Analytics Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Animal Analytics | FarmPro</title>
    
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
            --border-active:  rgba(59,130,246,0.5); /* Blue Accent */
            
            --blue:           #3b82f6;
            --blue-dim:       rgba(59,130,246,0.12);
            --blue-glow:      rgba(59,130,246,0.25);
            --emerald:        #10b981;
            --amber:          #f59e0b;
            --red:            #f87171;
            --pink:           #f472b6;
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
            background-image: radial-gradient(ellipse 80% 50% at 50% -20%, rgba(59,130,246,0.06) 0%, transparent 60%);
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
            color: var(--blue); background: var(--blue-dim); border: 1px solid rgba(59,130,246,0.2);
            padding: 6px 12px; border-radius: 99px;
        }

        /* ─── HEADER ─── */
        .page-header { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 2.5rem; gap: 1.5rem; flex-wrap: wrap; }
        .header-info h1 { font-size: clamp(1.8rem, 4vw, 2.5rem); font-weight: 700; margin: 0 0 0.5rem 0; color: #fff; letter-spacing: -0.02em;}
        .header-info h1 span { background: linear-gradient(135deg, var(--blue), #1d4ed8); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .header-info p { color: var(--text-secondary); font-size: 0.95rem; margin: 0; }

        .btn-view {
            display: inline-flex; align-items: center; justify-content: center; gap: 8px;
            padding: 12px 24px; background: var(--bg-elevated); border: 1px solid var(--border);
            border-radius: var(--radius-md); color: var(--text-primary); font-weight: 700; font-size: 0.95rem; font-family: var(--font);
            cursor: pointer; transition: var(--transition); text-decoration: none; white-space: nowrap;
        }
        .btn-view:hover { background: var(--blue-dim); border-color: var(--blue); color: var(--blue); transform: translateY(-2px);}

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
        .stat-green::before { background: var(--emerald); }
        .stat-blue::before { background: var(--blue); }
        .stat-red::before { background: var(--red); }
        .stat-yellow::before { background: var(--amber); }

        .kpi-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem; }
        .kpi-title { color: var(--text-secondary); font-size: 0.75rem; text-transform: uppercase; font-weight: 700; letter-spacing: 0.05em;}
        .kpi-icon { width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 1rem; color: #fff;}
        .stat-green .kpi-icon { background: linear-gradient(135deg, var(--emerald), #047857); }
        .stat-blue .kpi-icon { background: linear-gradient(135deg, var(--blue), #1d4ed8); }
        .stat-red .kpi-icon { background: linear-gradient(135deg, var(--red), #991b1b); }
        .stat-yellow .kpi-icon { background: linear-gradient(135deg, var(--amber), #b45309); }

        .kpi-value { font-size: 2.5rem; font-weight: 800; font-family: var(--font-mono); line-height: 1; margin-bottom: 0.5rem;}
        .stat-green .kpi-value { color: var(--emerald); }
        .stat-blue .kpi-value { color: var(--blue); }
        .stat-red .kpi-value { color: var(--red); }
        .stat-yellow .kpi-value { color: var(--amber); }

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
            <h1>Livestock <span>Analytics</span></h1>
            <p>Comprehensive overview of population health, demographics, and intake trends.</p>
        </div>
        <a href="animal_list.php" class="btn-view"><i class="fa-solid fa-list-ul"></i> View Master List</a>
    </header>

    <div class="kpi-grid">
        <div class="kpi-card stat-green">
            <div class="kpi-header">
                <div class="kpi-title">Active Herd</div>
                <div class="kpi-icon"><i class="fa-solid fa-hippo"></i></div>
            </div>
            <div class="kpi-value"><?= number_format($kpi['active_count']) ?></div>
            <div class="kpi-sub">Currently on farm</div>
        </div>

        <div class="kpi-card stat-blue">
            <div class="kpi-header">
                <div class="kpi-title">Total Sold</div>
                <div class="kpi-icon"><i class="fa-solid fa-hand-holding-dollar"></i></div>
            </div>
            <div class="kpi-value"><?= number_format($kpi['sold_count']) ?></div>
            <div class="kpi-sub">Lifetime sales</div>
        </div>

        <div class="kpi-card stat-red">
            <div class="kpi-header">
                <div class="kpi-title">Deceased</div>
                <div class="kpi-icon"><i class="fa-solid fa-skull"></i></div>
            </div>
            <div class="kpi-value"><?= number_format($kpi['deceased_count']) ?></div>
            <div class="kpi-sub">Mortality Rate: <?= number_format($mortality_rate, 1) ?>%</div>
        </div>

        <div class="kpi-card stat-yellow">
            <div class="kpi-header">
                <div class="kpi-title">Sick / Quarantine</div>
                <div class="kpi-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
            </div>
            <div class="kpi-value"><?= number_format($kpi['sick_count']) ?></div>
            <div class="kpi-sub">Needs Attention</div>
        </div>
    </div>

    <div class="charts-container">
        <div class="chart-box">
            <div class="chart-title"><i class="fa-solid fa-chart-pie" style="color:var(--blue);"></i> Overall Status Breakdown</div>
            <div class="chart-canvas-wrapper">
                <canvas id="statusChart"></canvas>
            </div>
        </div>

        <div class="chart-box">
            <div class="chart-title"><i class="fa-solid fa-chart-column" style="color:var(--emerald);"></i> Active Population by Stage</div>
            <div class="chart-canvas-wrapper">
                <canvas id="stageChart"></canvas>
            </div>
        </div>

        <div class="chart-box">
            <div class="chart-title"><i class="fa-solid fa-venus-mars" style="color:var(--pink);"></i> Gender Distribution (Active)</div>
            <div class="chart-canvas-wrapper">
                <canvas id="genderChart"></canvas>
            </div>
        </div>

        <div class="chart-box">
            <div class="chart-title"><i class="fa-solid fa-arrow-trend-up" style="color:var(--purple);"></i> New Animal Intake (Last 6 Months)</div>
            <div class="chart-canvas-wrapper">
                <canvas id="intakeChart"></canvas>
            </div>
        </div>
    </div>
</div>

<script>
    const statusRaw = <?= json_encode($status_data) ?>;
    const statusLabels = statusRaw.map(i => i.status_name);
    const statusCounts = statusRaw.map(i => i.count);
    const statusColors = statusLabels.map(s => {
        if (s === 'Active')   return '#10b981';
        if (s === 'Sold')     return '#3b82f6';
        if (s === 'Deceased') return '#ef4444';
        return '#f59e0b';
    });

    const stageRaw  = <?= json_encode($stage_data) ?>;
    const stageLabels = stageRaw.map(i => i.STAGE_NAME || 'Unknown');
    const stageCounts = stageRaw.map(i => i.count);

    const genderRaw   = <?= json_encode($gender_data) ?>;
    const genderLabels = genderRaw.map(i => i.SEX || 'Unknown');
    const genderCounts = genderRaw.map(i => i.count);

    const intakeRaw   = <?= json_encode($intake_data) ?>;
    const intakeLabels = intakeRaw.map(i => i.month_year);
    const intakeCounts = intakeRaw.map(i => i.count);

    /* ---- Global Chart.js defaults ---- */
    Chart.defaults.color       = '#94a3b8';
    Chart.defaults.borderColor = 'rgba(255,255,255,0.05)';
    Chart.defaults.font.family = "'DM Sans', system-ui, sans-serif";

    /* Responsive legend helper: bottom on small screens, right on large */
    function legendPos() {
        return window.innerWidth < 640 ? 'bottom' : 'right';
    }

    /* ---- Status Doughnut ---- */
    new Chart(document.getElementById('statusChart'), {
        type: 'doughnut',
        data: {
            labels: statusLabels,
            datasets: [{
                data: statusCounts,
                backgroundColor: statusColors,
                borderWidth: 0,
                hoverOffset: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false, // CRITICAL: Allows wrapper CSS to dictate height/width
            plugins: { 
                legend: { 
                    position: legendPos(),
                    labels: { boxWidth: 12, padding: 14, font: { size: 12, family: "'DM Sans', sans-serif" } }
                } 
            }
        }
    });

    /* ---- Stage Bar ---- */
    new Chart(document.getElementById('stageChart'), {
        type: 'bar',
        data: {
            labels: stageLabels,
            datasets: [{
                label: 'Head Count',
                data: stageCounts,
                backgroundColor: 'rgba(16, 185, 129, 0.6)',
                borderColor: '#10b981',
                borderWidth: 1,
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false, // CRITICAL
            scales: { 
                y: { beginAtZero: true },
                x: { ticks: { maxRotation: 45, minRotation: 0 } }
            },
            plugins: { legend: { display: false } }
        }
    });

    /* ---- Gender Pie ---- */
    new Chart(document.getElementById('genderChart'), {
        type: 'pie',
        data: {
            labels: genderLabels,
            datasets: [{
                data: genderCounts,
                backgroundColor: ['#f472b6', '#3b82f6', '#9ca3af'],
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

    /* ---- Intake Line ---- */
    new Chart(document.getElementById('intakeChart'), {
        type: 'line',
        data: {
            labels: intakeLabels,
            datasets: [{
                label: 'New Animals',
                data: intakeCounts,
                borderColor: '#a855f7',
                backgroundColor: 'rgba(168, 85, 247, 0.1)',
                borderWidth: 2,
                fill: true,
                tension: 0.4,
                pointRadius: 4,
                pointHoverRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false, // CRITICAL
            scales: { 
                y: { beginAtZero: true, suggestedMax: 5 },
                x: { ticks: { maxRotation: 45, minRotation: 0 } }
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