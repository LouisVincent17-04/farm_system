<?php
// reports/utilities_analytics.php
error_reporting(0);
ini_set('display_errors', 0);
$page = "analytics";

include '../common/navbar.php';
include '../config/Connection.php';
include '../security/checkRole.php';
checkRole(2); // Farm Admin

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
    // High-value utility items
    $dist_sql = "SELECT ITEM_NAME, TOTAL_COST 
                 FROM items 
                 WHERE ITEM_TYPE_ID = 9 AND STATUS = 1
                 ORDER BY TOTAL_COST DESC 
                 LIMIT 5";
    $dist_data = $conn->query($dist_sql)->fetchAll(PDO::FETCH_ASSOC);

    // --- 3. CHART: SPENDING TREND (Line) ---
    // Spending on utilities over the last 12 months
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
    // Most abundant consumables
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Utilities & Consumables Analytics</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        /* --- THEME: SKY BLUE / CYAN --- */
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
            background: linear-gradient(135deg, #0ea5e9, #0284c7); /* Sky Blue Gradient */
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
            background: linear-gradient(90deg, #0ea5e9, #0369a1); 
        }
        .kpi-label { color: #94a3b8; font-size: 0.8rem; font-weight: 600; text-transform: uppercase; letter-spacing: 1px; }
        .kpi-value { font-size: 2.2rem; font-weight: 800; color: #fff; margin: 0.5rem 0; }
        .kpi-sub { font-size: 0.85rem; color: #64748b; }

        .text-sky { color: #38bdf8; }
        .text-cyan { color: #22d3ee; }
        .text-white { color: #fff; }

        /* Chart Grid */
        .charts-container { 
            display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 2rem; 
        }
        .chart-box { 
            background: rgba(30, 41, 59, 0.6); border: 1px solid rgba(255,255,255,0.05); 
            border-radius: 16px; padding: 1.5rem; min-height: 350px; display: flex; flex-direction: column;
        }
        .chart-title { font-size: 1.1rem; font-weight: 700; color: #e2e8f0; margin-bottom: 1rem; }

        /* Buttons */
        .btn-group { display: flex; justify-content: flex-end; margin-bottom: 1rem; }
        .btn { 
            padding: 10px 20px; background: rgba(14, 165, 233, 0.1); 
            color: #38bdf8; border: 1px solid rgba(14, 165, 233, 0.3); 
            border-radius: 8px; text-decoration: none; font-weight: 600; 
            transition: all 0.2s; 
        }
        .btn:hover { background: rgba(14, 165, 233, 0.2); transform: translateY(-2px); }

        @media (max-width: 1024px) { .charts-container { grid-template-columns: 1fr; } }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <h1 class="title">Utilities & Consumables Analytics</h1>
        <p class="subtitle">Operational supplies, utility costs, and consumption tracking.</p>
    </div>

    <div class="kpi-grid">
        <div class="kpi-card">
            <div class="kpi-label">Total Expense Value</div>
            <div class="kpi-value text-white">₱<?= number_format($kpi['total_value'] / 1000, 1) ?>k</div>
            <div class="kpi-sub">Cost of Consumables</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">Distinct Items</div>
            <div class="kpi-value text-sky"><?= number_format($kpi['distinct_items']) ?></div>
            <div class="kpi-sub">Utility Types</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">Total Units</div>
            <div class="kpi-value"><?= number_format($kpi['total_units']) ?></div>
            <div class="kpi-sub">Stock Count</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">Avg. Cost / Unit</div>
            <div class="kpi-value text-cyan">₱<?= number_format($avg_cost, 2) ?></div>
            <div class="kpi-sub">Per Consumable</div>
        </div>
    </div>

    <div class="charts-container">
        
        <div class="chart-box">
            <div class="chart-title">⚡ Utility Spending Trend (Last 12 Months)</div>
            <div style="flex-grow: 1; position: relative;">
                <canvas id="trendChart"></canvas>
            </div>
        </div>

        <div class="chart-box">
            <div class="chart-title">💰 Cost Breakdown by Item</div>
            <div style="flex-grow: 1; position: relative;">
                <canvas id="distChart"></canvas>
            </div>
        </div>

        <div class="chart-box" style="grid-column: 1 / -1;">
            <div class="chart-title">📊 Top 5 Consumables by Quantity</div>
            <div style="flex-grow: 1; position: relative; max-height: 300px;">
                <canvas id="qtyChart"></canvas>
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
            labels: trendData.map(d => d.month_year),
            datasets: [{
                label: 'Cost (PHP)',
                data: trendData.map(d => d.cost),
                borderColor: '#0ea5e9', // Sky Blue
                backgroundColor: 'rgba(14, 165, 233, 0.1)',
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

    // 2. Cost Distribution Pie Chart
    const distData = <?= json_encode($dist_data) ?>;
    new Chart(document.getElementById('distChart'), {
        type: 'doughnut',
        data: {
            labels: distData.map(d => d.ITEM_NAME),
            datasets: [{
                data: distData.map(d => d.TOTAL_COST),
                backgroundColor: ['#0284c7', '#0369a1', '#0ea5e9', '#38bdf8', '#7dd3fc'], // Sky Blue shades
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'right', labels: { boxWidth: 12 } } }
        }
    });

    // 3. Quantity Bar Chart
    const qtyData = <?= json_encode($qty_data) ?>;
    new Chart(document.getElementById('qtyChart'), {
        type: 'bar',
        data: {
            labels: qtyData.map(d => d.ITEM_NAME),
            datasets: [{
                label: 'Units Available',
                data: qtyData.map(d => d.QUANTITY),
                backgroundColor: 'rgba(56, 189, 248, 0.7)', // Light Sky
                borderColor: '#0ea5e9',
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
</script>

</body>
</html>