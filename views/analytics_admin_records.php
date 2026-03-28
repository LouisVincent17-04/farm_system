<?php
// reports/admin_analytics.php
error_reporting(0);
ini_set('display_errors', 0);
$page = "analytics";
include '../config/Connection.php';

include '../security/checkAccess.php';
checkAccess('administration_records_analytics');
include '../common/navbar.php';
include '../common/chat_support.php';

try {
    if (!isset($conn)) { throw new Exception("Database connection failed."); }

    // --- 1. KPI: SYSTEM METRICS ---
    
    // Total Users
    $user_sql = "SELECT COUNT(*) as count FROM users WHERE IS_ACTIVE = 1";
    $total_users = $conn->query($user_sql)->fetchColumn();

    // Total Logs (All time)
    $log_sql = "SELECT COUNT(*) as count FROM audit_logs";
    $total_logs = $conn->query($log_sql)->fetchColumn();

    // Active Users Today (Distinct logins in audit_logs for today)
    $active_today_sql = "SELECT COUNT(DISTINCT USERNAME) 
                         FROM audit_logs 
                         WHERE DATE(LOG_DATE) = CURDATE()";
    $active_today = $conn->query($active_today_sql)->fetchColumn();

    // Critical Actions Count (Deletions/Edits today)
    $critical_sql = "SELECT COUNT(*) 
                     FROM audit_logs 
                     WHERE (ACTION_TYPE LIKE '%DELETE%' OR ACTION_TYPE LIKE '%EDIT%') 
                     AND DATE(LOG_DATE) = CURDATE()";
    $critical_actions = $conn->query($critical_sql)->fetchColumn();


    // --- 2. CHART: ACTIVITY TREND (Line) ---
    $trend_sql = "SELECT 
                    DATE_FORMAT(LOG_DATE, '%Y-%m-%d') as log_day,
                    COUNT(*) as action_count
                  FROM audit_logs
                  WHERE LOG_DATE >= DATE_SUB(NOW(), INTERVAL 14 DAY)
                  GROUP BY log_day
                  ORDER BY log_day ASC";
    $trend_data = $conn->query($trend_sql)->fetchAll(PDO::FETCH_ASSOC);

    // --- 3. CHART: ACTION TYPES DISTRIBUTION (Pie) ---
    $action_sql = "SELECT 
                    CASE 
                        WHEN ACTION_TYPE LIKE '%ADD%' THEN 'Creation'
                        WHEN ACTION_TYPE LIKE '%EDIT%' OR ACTION_TYPE LIKE '%UPDATE%' THEN 'Modification'
                        WHEN ACTION_TYPE LIKE '%DELETE%' THEN 'Deletion'
                        WHEN ACTION_TYPE LIKE '%LOGIN%' THEN 'Login'
                        ELSE 'Other'
                    END as category,
                    COUNT(*) as count
                   FROM audit_logs
                   GROUP BY category
                   ORDER BY count DESC";
    $action_data = $conn->query($action_sql)->fetchAll(PDO::FETCH_ASSOC);

    // --- 4. CHART: TOP ACTIVE USERS (Bar) ---
    $top_user_sql = "SELECT USERNAME, COUNT(*) as activity_count 
                     FROM audit_logs 
                     WHERE USERNAME IS NOT NULL
                     GROUP BY USERNAME 
                     ORDER BY activity_count DESC 
                     LIMIT 5";
    $top_users = $conn->query($top_user_sql)->fetchAll(PDO::FETCH_ASSOC);

    // --- 5. CHART: ACTIVITY BY HOUR (Heatmap style logic / Bar) ---
    $hour_sql = "SELECT HOUR(LOG_DATE) as hour_of_day, COUNT(*) as count 
                 FROM audit_logs 
                 WHERE LOG_DATE >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                 GROUP BY hour_of_day 
                 ORDER BY hour_of_day ASC";
    $hour_data = $conn->query($hour_sql)->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log("Admin Analytics Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Administration Analytics | FarmPro</title>
    
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
            --border-active:  rgba(99,102,241,0.5); /* Indigo Accent */
            
            --indigo:         #6366f1;
            --indigo-dim:     rgba(99,102,241,0.12);
            --indigo-glow:    rgba(99,102,241,0.25);
            --blue:           #3b82f6;
            --emerald:        #10b981;
            --amber:          #f59e0b;
            --red:            #ef4444;
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
            background-image: radial-gradient(ellipse 80% 50% at 50% -20%, rgba(99,102,241,0.06) 0%, transparent 60%);
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
            color: var(--indigo); background: var(--indigo-dim); border: 1px solid rgba(99,102,241,0.2);
            padding: 6px 12px; border-radius: 99px;
        }

        /* ─── HEADER ─── */
        .page-header { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 2.5rem; gap: 1.5rem; flex-wrap: wrap; }
        .header-info h1 { font-size: clamp(1.8rem, 4vw, 2.5rem); font-weight: 700; margin: 0 0 0.5rem 0; color: #fff; letter-spacing: -0.02em;}
        .header-info h1 span { background: linear-gradient(135deg, var(--indigo), #4f46e5); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .header-info p { color: var(--text-secondary); font-size: 0.95rem; margin: 0; }

        .btn-view {
            display: inline-flex; align-items: center; justify-content: center; gap: 8px;
            padding: 12px 24px; background: var(--bg-elevated); border: 1px solid var(--border);
            border-radius: var(--radius-md); color: var(--text-primary); font-weight: 700; font-size: 0.95rem; font-family: var(--font);
            cursor: pointer; transition: var(--transition); text-decoration: none; white-space: nowrap;
        }
        .btn-view:hover { background: var(--indigo-dim); border-color: var(--indigo); color: #fff; transform: translateY(-2px);}

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
        .stat-indigo::before { background: var(--indigo); }
        .stat-slate::before { background: var(--slate); }
        .stat-red::before { background: var(--red); }
        .stat-blue::before { background: var(--blue); }

        .kpi-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem; }
        .kpi-title { color: var(--text-secondary); font-size: 0.75rem; text-transform: uppercase; font-weight: 700; letter-spacing: 0.05em;}
        .kpi-icon { width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 1rem; color: #fff;}
        .stat-indigo .kpi-icon { background: linear-gradient(135deg, var(--indigo), #4338ca); }
        .stat-slate .kpi-icon { background: linear-gradient(135deg, var(--slate), #475569); }
        .stat-red .kpi-icon { background: linear-gradient(135deg, var(--red), #991b1b); }
        .stat-blue .kpi-icon { background: linear-gradient(135deg, var(--blue), #1d4ed8); }

        .kpi-value { font-size: 2.5rem; font-weight: 800; font-family: var(--font-mono); line-height: 1; margin-bottom: 0.5rem;}
        .stat-indigo .kpi-value { color: var(--indigo); }
        .stat-slate .kpi-value { color: var(--text-primary); }
        .stat-red .kpi-value { color: var(--red); }
        .stat-blue .kpi-value { color: var(--blue); }

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
        <span class="page-badge"><i class="fa-solid fa-server"></i> System Data</span>
    </div>

    <header class="page-header">
        <div class="header-info">
            <h1>Administration <span>Analytics</span></h1>
            <p>Monitor system health, user activity logs, and operational oversight.</p>
        </div>
        <a href="audit_log_report.php" class="btn-view"><i class="fa-solid fa-file-invoice"></i> View Full Audit Log</a>
    </header>

    <div class="kpi-grid">
        <div class="kpi-card stat-indigo">
            <div class="kpi-header">
                <div class="kpi-title">Active Users</div>
                <div class="kpi-icon"><i class="fa-solid fa-users"></i></div>
            </div>
            <div class="kpi-value"><?= number_format($active_today) ?></div>
            <div class="kpi-sub">Logged in Today</div>
        </div>

        <div class="kpi-card stat-slate">
            <div class="kpi-header">
                <div class="kpi-title">Total System Logs</div>
                <div class="kpi-icon"><i class="fa-solid fa-database"></i></div>
            </div>
            <div class="kpi-value"><?= number_format($total_logs) ?></div>
            <div class="kpi-sub">Lifetime Audit Trail</div>
        </div>

        <div class="kpi-card stat-red">
            <div class="kpi-header">
                <div class="kpi-title">Critical Actions</div>
                <div class="kpi-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
            </div>
            <div class="kpi-value"><?= number_format($critical_actions) ?></div>
            <div class="kpi-sub">Edits/Deletes Today</div>
        </div>

        <div class="kpi-card stat-blue">
            <div class="kpi-header">
                <div class="kpi-title">Registered Accounts</div>
                <div class="kpi-icon"><i class="fa-solid fa-address-card"></i></div>
            </div>
            <div class="kpi-value"><?= number_format($total_users) ?></div>
            <div class="kpi-sub">Active User Base</div>
        </div>
    </div>

    <div class="charts-container">
        <div class="chart-box">
            <div class="chart-title"><i class="fa-solid fa-chart-line" style="color:var(--indigo);"></i> System Activity (Last 14 Days)</div>
            <div class="chart-canvas-wrapper">
                <canvas id="trendChart"></canvas>
            </div>
        </div>

        <div class="chart-box">
            <div class="chart-title"><i class="fa-solid fa-wrench" style="color:var(--emerald);"></i> Action Breakdown</div>
            <div class="chart-canvas-wrapper">
                <canvas id="actionChart"></canvas>
            </div>
        </div>

        <div class="chart-box">
            <div class="chart-title"><i class="fa-solid fa-user-check" style="color:var(--blue);"></i> Most Active Users (All Time)</div>
            <div class="chart-canvas-wrapper">
                <canvas id="userChart"></canvas>
            </div>
        </div>

        <div class="chart-box">
            <div class="chart-title"><i class="fa-solid fa-clock" style="color:var(--amber);"></i> Peak Usage Hours (30 Day Avg)</div>
            <div class="chart-canvas-wrapper">
                <canvas id="hourChart"></canvas>
            </div>
        </div>
    </div>
</div>

<script>
    const trendData = <?= json_encode($trend_data) ?>;
    const actionData = <?= json_encode($action_data) ?>;
    const topUsers = <?= json_encode($top_users) ?>;
    const hourData = <?= json_encode($hour_data) ?>;

    /* ---- Global Chart.js defaults ---- */
    Chart.defaults.color       = '#94a3b8';
    Chart.defaults.borderColor = 'rgba(255,255,255,0.05)';
    Chart.defaults.font.family = "'DM Sans', system-ui, sans-serif";

    /* Responsive legend helper: bottom on small screens, right on large */
    function legendPos() {
        return window.innerWidth < 640 ? 'bottom' : 'right';
    }

    /* ---- Activity Trend Line Chart ---- */
    new Chart(document.getElementById('trendChart'), {
        type: 'line',
        data: {
            labels: trendData.map(d => d.log_day),
            datasets: [{
                label: 'Actions Logged',
                data: trendData.map(d => d.action_count),
                borderColor: '#6366f1', // Indigo
                backgroundColor: 'rgba(99, 102, 241, 0.1)',
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

    /* ---- Action Breakdown Doughnut ---- */
    new Chart(document.getElementById('actionChart'), {
        type: 'doughnut',
        data: {
            labels: actionData.map(d => d.category),
            datasets: [{
                data: actionData.map(d => d.count),
                backgroundColor: ['#10b981', '#f59e0b', '#ef4444', '#3b82f6', '#64748b'],
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

    /* ---- Most Active Users Bar Chart ---- */
    new Chart(document.getElementById('userChart'), {
        type: 'bar',
        data: {
            labels: topUsers.map(d => d.USERNAME || 'System'),
            datasets: [{
                label: 'Total Actions',
                data: topUsers.map(d => d.activity_count),
                backgroundColor: 'rgba(59, 130, 246, 0.7)', // Blue
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

    /* ---- Peak Usage Hours Bar Chart ---- */
    const hours = Array.from({length: 24}, (_, i) => i);
    const hourCounts = hours.map(h => {
        const found = hourData.find(d => d.hour_of_day == h);
        return found ? found.count : 0;
    });

    new Chart(document.getElementById('hourChart'), {
        type: 'bar',
        data: {
            labels: hours.map(h => `${h}:00`),
            datasets: [{
                label: 'Activity Volume',
                data: hourCounts,
                backgroundColor: 'rgba(245, 158, 11, 0.6)', // Amber
                borderColor: '#f59e0b',
                borderWidth: 1,
                borderRadius: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false, // CRITICAL
            plugins: { legend: { display: false } },
            scales: { 
                y: { beginAtZero: true },
                x: { grid: { display: false } }
            }
        }
    });

    // Optional: Re-render chart legend position if user rotates phone
    window.addEventListener('resize', () => {
        const doughnutChart = Chart.getChart('actionChart');
        if (doughnutChart) {
            doughnutChart.options.plugins.legend.position = legendPos();
            doughnutChart.update();
        }
    });
</script>

</body>
</html>