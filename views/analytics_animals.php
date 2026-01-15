<?php
// reports/animal_analytics.php
error_reporting(0);
ini_set('display_errors', 0);
$page = "analytics";

include '../common/navbar.php';
include '../config/Connection.php';
include '../security/checkRole.php';
checkRole(2); // Farm Admin

try {
    if (!isset($conn)) { throw new Exception("Database connection failed."); }

    // --- 1. KPI: TOP LEVEL COUNTS ---
    // Uses 'CURRENT_STATUS' from your animal_records table
    $kpi_sql = "SELECT 
        COUNT(*) as total_records,
        SUM(CASE WHEN CURRENT_STATUS = 'Active' THEN 1 ELSE 0 END) as active_count,
        SUM(CASE WHEN CURRENT_STATUS = 'Sold' THEN 1 ELSE 0 END) as sold_count,
        SUM(CASE WHEN CURRENT_STATUS = 'Deceased' THEN 1 ELSE 0 END) as deceased_count,
        SUM(CASE WHEN CURRENT_STATUS = 'Quarantine' OR CURRENT_STATUS = 'Sick' THEN 1 ELSE 0 END) as sick_count
    FROM animal_records";
    $kpi = $conn->query($kpi_sql)->fetch(PDO::FETCH_ASSOC);

    // Calculate Mortality Rate (Deceased / Total Records)
    $mortality_rate = ($kpi['total_records'] > 0) 
        ? ($kpi['deceased_count'] / $kpi['total_records']) * 100 
        : 0;

    // --- 2. CHART: STATUS DISTRIBUTION (Pie) ---
    $status_sql = "SELECT CURRENT_STATUS as status_name, COUNT(*) as count 
                   FROM animal_records 
                   GROUP BY CURRENT_STATUS";
    $status_data = $conn->query($status_sql)->fetchAll(PDO::FETCH_ASSOC);

    // --- 3. CHART: ACTIVE POPULATION BY STAGE (Bar) ---
    // Joins with animal_classifications to get names like 'Piglet', 'Sow', etc.
    $stage_sql = "SELECT 
                    ac.STAGE_NAME, 
                    COUNT(ar.ANIMAL_ID) as count 
                  FROM animal_records ar
                  LEFT JOIN animal_classifications ac ON ar.CLASS_ID = ac.CLASS_ID
                  WHERE ar.CURRENT_STATUS = 'Active'
                  GROUP BY ac.STAGE_NAME 
                  ORDER BY count DESC";
    $stage_data = $conn->query($stage_sql)->fetchAll(PDO::FETCH_ASSOC);

    // --- 4. CHART: GENDER RATIO (Doughnut) ---
    $gender_sql = "SELECT SEX, COUNT(*) as count 
                   FROM animal_records 
                   WHERE CURRENT_STATUS = 'Active' 
                   GROUP BY SEX";
    $gender_data = $conn->query($gender_sql)->fetchAll(PDO::FETCH_ASSOC);

    // --- 5. CHART: INTAKE TREND (Line) ---
    // Animals added per month (Last 6 months)
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Animal Analytics - FarmPro</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        /* --- THEME STYLES --- */
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
            background: linear-gradient(135deg, #38bdf8, #2563eb); /* Blue Gradient */
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
            background: linear-gradient(90deg, #38bdf8, #2563eb); 
        }
        .kpi-label { color: #94a3b8; font-size: 0.8rem; font-weight: 600; text-transform: uppercase; letter-spacing: 1px; }
        .kpi-value { font-size: 2.2rem; font-weight: 800; color: #fff; margin: 0.5rem 0; }
        .kpi-sub { font-size: 0.85rem; color: #64748b; }

        .text-blue { color: #60a5fa; }
        .text-green { color: #4ade80; }
        .text-red { color: #f87171; }
        .text-orange { color: #fbbf24; }

        /* Chart Grid */
        .charts-container { 
            display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 2rem; 
        }
        .chart-box { 
            background: rgba(30, 41, 59, 0.6); border: 1px solid rgba(255,255,255,0.05); 
            border-radius: 16px; padding: 1.5rem; min-height: 350px; display: flex; flex-direction: column;
        }
        .full-width { grid-column: 1 / -1; }
        .chart-title { font-size: 1.1rem; font-weight: 700; color: #e2e8f0; margin-bottom: 1rem; display: flex; justify-content: space-between; align-items: center; }

        /* Buttons */
        .btn-group { display: flex; justify-content: flex-end; margin-bottom: 1rem; }
        .btn { 
            padding: 10px 20px; background: rgba(56, 189, 248, 0.1); 
            color: #38bdf8; border: 1px solid rgba(56, 189, 248, 0.3); 
            border-radius: 8px; text-decoration: none; font-weight: 600; 
            transition: all 0.2s; 
        }
        .btn:hover { background: rgba(56, 189, 248, 0.2); transform: translateY(-2px); }

        @media (max-width: 1024px) { .charts-container { grid-template-columns: 1fr; } }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <h1 class="title">Livestock Analytics</h1>
        <p class="subtitle">Population overview, health metrics, and herd demographics.</p>
    </div>

    <div class="kpi-grid">
        <div class="kpi-card">
            <div class="kpi-label">Active Herd</div>
            <div class="kpi-value text-green"><?= number_format($kpi['active_count']) ?></div>
            <div class="kpi-sub">Currently on farm</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">Total Sold</div>
            <div class="kpi-value text-blue"><?= number_format($kpi['sold_count']) ?></div>
            <div class="kpi-sub">Lifetime sales</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">Deceased</div>
            <div class="kpi-value text-red"><?= number_format($kpi['deceased_count']) ?></div>
            <div class="kpi-sub">Mortality Rate: <?= number_format($mortality_rate, 1) ?>%</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">Sick / Quarantine</div>
            <div class="kpi-value text-orange"><?= number_format($kpi['sick_count']) ?></div>
            <div class="kpi-sub">Needs Attention</div>
        </div>
    </div>

    <div class="btn-group">
        <a href="animal_list.php" class="btn">View Animal List →</a>
    </div>

    <div class="charts-container">
        
        <div class="chart-box">
            <div class="chart-title">📊 Overall Status Breakdown</div>
            <div style="flex-grow: 1; position: relative;">
                <canvas id="statusChart"></canvas>
            </div>
        </div>

        <div class="chart-box">
            <div class="chart-title">🐷 Active Population by Stage</div>
            <div style="flex-grow: 1; position: relative;">
                <canvas id="stageChart"></canvas>
            </div>
        </div>

        <div class="chart-box">
            <div class="chart-title">⚧ Gender Distribution (Active)</div>
            <div style="flex-grow: 1; position: relative; max-height:300px; width:100%; display:flex; justify-content:center;">
                <canvas id="genderChart"></canvas>
            </div>
        </div>

        <div class="chart-box">
            <div class="chart-title">📈 New Animal Intake (Last 6 Months)</div>
            <div style="flex-grow: 1; position: relative;">
                <canvas id="intakeChart"></canvas>
            </div>
        </div>

    </div>
</div>

<script>
    // --- PREPARE DATA FROM PHP ---
    
    // 1. Status Data
    const statusRaw = <?= json_encode($status_data) ?>;
    const statusLabels = statusRaw.map(i => i.status_name);
    const statusCounts = statusRaw.map(i => i.count);
    // Dynamic Colors
    const statusColors = statusLabels.map(s => {
        if(s === 'Active') return '#4ade80'; // Green
        if(s === 'Sold') return '#38bdf8';   // Blue
        if(s === 'Deceased') return '#f87171'; // Red
        return '#fbbf24'; // Orange
    });

    // 2. Stage Data (Classification)
    const stageRaw = <?= json_encode($stage_data) ?>;
    const stageLabels = stageRaw.map(i => i.STAGE_NAME || 'Unknown');
    const stageCounts = stageRaw.map(i => i.count);

    // 3. Gender Data
    const genderRaw = <?= json_encode($gender_data) ?>;
    const genderLabels = genderRaw.map(i => i.SEX || 'Unknown');
    const genderCounts = genderRaw.map(i => i.count);

    // 4. Intake Data
    const intakeRaw = <?= json_encode($intake_data) ?>;
    const intakeLabels = intakeRaw.map(i => i.month_year);
    const intakeCounts = intakeRaw.map(i => i.count);

    // --- CHART DEFAULTS ---
    Chart.defaults.color = '#94a3b8';
    Chart.defaults.borderColor = 'rgba(255,255,255,0.05)';
    Chart.defaults.font.family = 'system-ui';

    // --- RENDER CHARTS ---

    // 1. Status Pie Chart
    new Chart(document.getElementById('statusChart'), {
        type: 'doughnut',
        data: {
            labels: statusLabels,
            datasets: [{
                data: statusCounts,
                backgroundColor: statusColors,
                borderWidth: 0,
                hoverOffset: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'right' } }
        }
    });

    // 2. Stage Bar Chart (Piglet, Sow, etc.)
    new Chart(document.getElementById('stageChart'), {
        type: 'bar',
        data: {
            labels: stageLabels,
            datasets: [{
                label: 'Head Count',
                data: stageCounts,
                backgroundColor: 'rgba(96, 165, 250, 0.6)', // Light Blue
                borderColor: '#60a5fa',
                borderWidth: 1,
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: { y: { beginAtZero: true } },
            plugins: { legend: { display: false } }
        }
    });

    // 3. Gender Pie Chart
    new Chart(document.getElementById('genderChart'), {
        type: 'pie',
        data: {
            labels: genderLabels,
            datasets: [{
                data: genderCounts,
                backgroundColor: ['#f472b6', '#60a5fa', '#9ca3af'], // Pink, Blue, Gray
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false
        }
    });

    // 4. Intake Line Chart
    new Chart(document.getElementById('intakeChart'), {
        type: 'line',
        data: {
            labels: intakeLabels,
            datasets: [{
                label: 'New Animals',
                data: intakeCounts,
                borderColor: '#a78bfa', // Purple
                backgroundColor: 'rgba(167, 139, 250, 0.1)',
                borderWidth: 2,
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: { y: { beginAtZero: true, suggestedMax: 5 } }
        }
    });

</script>

</body>
</html>