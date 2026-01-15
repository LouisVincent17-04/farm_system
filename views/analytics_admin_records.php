<?php
// reports/admin_analytics.php
error_reporting(0);
ini_set('display_errors', 0);
$page = "analytics";

include '../common/navbar.php';
include '../config/Connection.php';
include '../security/checkRole.php';
checkRole(1); // Administrator Only (Strict)

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
    // User actions over the last 14 days
    $trend_sql = "SELECT 
                    DATE_FORMAT(LOG_DATE, '%Y-%m-%d') as log_day,
                    COUNT(*) as action_count
                  FROM audit_logs
                  WHERE LOG_DATE >= DATE_SUB(NOW(), INTERVAL 14 DAY)
                  GROUP BY log_day
                  ORDER BY log_day ASC";
    $trend_data = $conn->query($trend_sql)->fetchAll(PDO::FETCH_ASSOC);

    // --- 3. CHART: ACTION TYPES DISTRIBUTION (Pie) ---
    // What are users doing most? (Add, Edit, Delete, Login)
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
    // Who is using the system the most?
    $top_user_sql = "SELECT USERNAME, COUNT(*) as activity_count 
                     FROM audit_logs 
                     WHERE USERNAME IS NOT NULL
                     GROUP BY USERNAME 
                     ORDER BY activity_count DESC 
                     LIMIT 5";
    $top_users = $conn->query($top_user_sql)->fetchAll(PDO::FETCH_ASSOC);

    // --- 5. CHART: ACTIVITY BY HOUR (Heatmap style logic / Bar) ---
    // Peak system usage times
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration Analytics</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        /* --- THEME: SLATE BLUE / DARK --- */
        body { 
            font-family: system-ui, -apple-system, sans-serif; 
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); 
            color: #e2e8f0; 
            margin: 0; padding-bottom: 40px; 
        }
        .container { max-width: 1400px; margin: 0 auto; padding: 2rem; }
        
        .header { text-align: center; margin-bottom: 2rem; }
        .title { 
            font-size: 2.2rem; font-weight: 800; 
            background: linear-gradient(135deg, #6366f1, #4f46e5); /* Indigo Gradient */
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
            background: linear-gradient(90deg, #6366f1, #4338ca); 
        }
        .kpi-label { color: #94a3b8; font-size: 0.8rem; font-weight: 600; text-transform: uppercase; letter-spacing: 1px; }
        .kpi-value { font-size: 2.2rem; font-weight: 800; color: #fff; margin: 0.5rem 0; }
        .kpi-sub { font-size: 0.85rem; color: #64748b; }

        .text-indigo { color: #818cf8; }
        .text-blue { color: #60a5fa; }
        .text-red { color: #f87171; }
        .text-white { color: #fff; }

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
            padding: 10px 20px; background: rgba(99, 102, 241, 0.1); 
            color: #818cf8; border: 1px solid rgba(99, 102, 241, 0.3); 
            border-radius: 8px; text-decoration: none; font-weight: 600; 
            transition: all 0.2s; 
        }
        .btn:hover { background: rgba(99, 102, 241, 0.2); transform: translateY(-2px); }

        @media (max-width: 1024px) { .charts-container { grid-template-columns: 1fr; } }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <h1 class="title">Administration & Records Analytics</h1>
        <p class="subtitle">System health, user activity logs, and operational oversight.</p>
    </div>

    <div class="kpi-grid">
        <div class="kpi-card">
            <div class="kpi-label">Active Users</div>
            <div class="kpi-value text-indigo"><?= number_format($active_today) ?></div>
            <div class="kpi-sub">Logged in Today</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">Total System Logs</div>
            <div class="kpi-value"><?= number_format($total_logs) ?></div>
            <div class="kpi-sub">Lifetime Audit Trail</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">Critical Actions</div>
            <div class="kpi-value text-red"><?= number_format($critical_actions) ?></div>
            <div class="kpi-sub">Edits/Deletes Today</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">Registered Accounts</div>
            <div class="kpi-value text-blue"><?= number_format($total_users) ?></div>
            <div class="kpi-sub">Active User Base</div>
        </div>
    </div>

    <div class="btn-group">
        <a href="audit_log_report.php" class="btn">View Full Audit Log →</a>
    </div>

    <div class="charts-container">
        
        <div class="chart-box">
            <div class="chart-title">📉 System Activity (Last 14 Days)</div>
            <div style="flex-grow: 1; position: relative;">
                <canvas id="trendChart"></canvas>
            </div>
        </div>

        <div class="chart-box">
            <div class="chart-title">🔧 Action Breakdown</div>
            <div style="flex-grow: 1; position: relative;">
                <canvas id="actionChart"></canvas>
            </div>
        </div>

        <div class="chart-box">
            <div class="chart-title">👤 Most Active Users (All Time)</div>
            <div style="flex-grow: 1; position: relative;">
                <canvas id="userChart"></canvas>
            </div>
        </div>

        <div class="chart-box">
            <div class="chart-title">🕒 Peak Usage Hours (30 Day Avg)</div>
            <div style="flex-grow: 1; position: relative;">
                <canvas id="hourChart"></canvas>
            </div>
        </div>

    </div>
</div>

<script>
    // --- CHART DEFAULTS ---
    Chart.defaults.color = '#94a3b8';
    Chart.defaults.borderColor = 'rgba(255,255,255,0.05)';
    Chart.defaults.font.family = 'system-ui';

    // 1. Trend Line Chart
    const trendData = <?= json_encode($trend_data) ?>;
    new Chart(document.getElementById('trendChart'), {
        type: 'line',
        data: {
            labels: trendData.map(d => d.log_day),
            datasets: [{
                label: 'Actions Logged',
                data: trendData.map(d => d.action_count),
                borderColor: '#818cf8', // Indigo
                backgroundColor: 'rgba(99, 102, 241, 0.1)',
                borderWidth: 2,
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true } }
        }
    });

    // 2. Action Pie Chart
    const actionData = <?= json_encode($action_data) ?>;
    new Chart(document.getElementById('actionChart'), {
        type: 'doughnut',
        data: {
            labels: actionData.map(d => d.category),
            datasets: [{
                data: actionData.map(d => d.count),
                backgroundColor: ['#10b981', '#f59e0b', '#ef4444', '#3b82f6', '#64748b'], // Green, Amber, Red, Blue, Slate
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'right', labels: { boxWidth: 12 } } }
        }
    });

    // 3. Top Users Bar Chart
    const topUsers = <?= json_encode($top_users) ?>;
    new Chart(document.getElementById('userChart'), {
        type: 'bar',
        data: {
            labels: topUsers.map(d => d.USERNAME || 'System'),
            datasets: [{
                label: 'Total Actions',
                data: topUsers.map(d => d.activity_count),
                backgroundColor: 'rgba(129, 140, 248, 0.7)', // Light Indigo
                borderColor: '#6366f1',
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

    // 4. Hourly Activity Bar Chart
    const hourData = <?= json_encode($hour_data) ?>;
    // Map 0-23 hours ensuring all are present or handled
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
                backgroundColor: 'rgba(56, 189, 248, 0.6)', // Sky Blue
                borderColor: '#38bdf8',
                borderWidth: 1,
                borderRadius: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { 
                y: { beginAtZero: true },
                x: { grid: { display: false } }
            }
        }
    });
</script>

</body>
</html>