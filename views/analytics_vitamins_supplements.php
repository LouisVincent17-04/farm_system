<?php
// reports/vitamins_analytics.php
error_reporting(0);
ini_set('display_errors', 0);
$page = "analytics";

include '../common/navbar.php';
include '../config/Connection.php';
include '../security/checkRole.php';
checkRole(2); // Farm Admin

try {
    if (!isset($conn)) { throw new Exception("Database connection failed."); }

    // --- 1. KPI: USAGE & INVENTORY METRICS ---
    
    // Usage Totals (From vitamins_supplements_transactions)
    $usage_sql = "SELECT 
                    COUNT(*) as total_txns,
                    COALESCE(SUM(TOTAL_COST), 0) as total_spent,
                    COALESCE(SUM(QUANTITY_USED), 0) as total_qty_used
                  FROM vitamins_supplements_transactions";
    $usage = $conn->query($usage_sql)->fetch(PDO::FETCH_ASSOC);

    // Inventory Totals (From vitamins_supplements table)
    $inv_sql = "SELECT 
                    COUNT(*) as active_items,
                    COALESCE(SUM(TOTAL_COST), 0) as inventory_value,
                    SUM(CASE WHEN TOTAL_STOCK < 10 THEN 1 ELSE 0 END) as low_stock_count
                FROM vitamins_supplements";
    $inv = $conn->query($inv_sql)->fetch(PDO::FETCH_ASSOC);

    // --- 2. CHART: SPENDING TREND (Line) ---
    // Costs over the last 12 months
    $trend_sql = "SELECT 
                    DATE_FORMAT(TRANSACTION_DATE, '%Y-%m') as month_year,
                    SUM(TOTAL_COST) as cost
                  FROM vitamins_supplements_transactions
                  WHERE TRANSACTION_DATE >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                  GROUP BY month_year
                  ORDER BY month_year ASC";
    $trend_data = $conn->query($trend_sql)->fetchAll(PDO::FETCH_ASSOC);

    // --- 3. CHART: TOP SUPPLEMENTS BY USAGE (Bar) ---
    // Joins with ITEMS to get the name
    $top_vits_sql = "SELECT 
                        i.ITEM_NAME, 
                        COUNT(vt.VST_ID) as usage_count
                     FROM vitamins_supplements_transactions vt
                     LEFT JOIN items i ON vt.ITEM_ID = i.ITEM_ID
                     GROUP BY i.ITEM_NAME
                     ORDER BY usage_count DESC
                     LIMIT 5";
    $top_vits = $conn->query($top_vits_sql)->fetchAll(PDO::FETCH_ASSOC);

    // --- 4. CHART: INVENTORY VALUE DISTRIBUTION (Pie) ---
    // Which supplements hold the most value in stock
    $stock_val_sql = "SELECT SUPPLY_NAME, TOTAL_COST 
                      FROM vitamins_supplements 
                      WHERE TOTAL_COST > 0
                      ORDER BY TOTAL_COST DESC 
                      LIMIT 5";
    $stock_val = $conn->query($stock_val_sql)->fetchAll(PDO::FETCH_ASSOC);

    // --- 5. CHART: MOST SUPPLEMENTED ANIMALS (Horizontal Bar) ---
    // Animals receiving the most vitamins
    $top_animal_sql = "SELECT 
                            ar.TAG_NO, 
                            COUNT(vt.VST_ID) as txn_count
                        FROM vitamins_supplements_transactions vt
                        LEFT JOIN animal_records ar ON vt.ANIMAL_ID = ar.ANIMAL_ID
                        GROUP BY ar.TAG_NO
                        ORDER BY txn_count DESC
                        LIMIT 5";
    $top_animals = $conn->query($top_animal_sql)->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log("Vitamins Analytics Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vitamins & Supplements Analytics</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        /* --- THEME: LIME / GREEN --- */
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
            background: linear-gradient(135deg, #a3e635, #65a30d); /* Lime Gradient */
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
            background: linear-gradient(90deg, #a3e635, #4d7c0f); 
        }
        .kpi-label { color: #94a3b8; font-size: 0.8rem; font-weight: 600; text-transform: uppercase; letter-spacing: 1px; }
        .kpi-value { font-size: 2.2rem; font-weight: 800; color: #fff; margin: 0.5rem 0; }
        .kpi-sub { font-size: 0.85rem; color: #64748b; }

        .text-lime { color: #bef264; }
        .text-green { color: #4ade80; }
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
            padding: 10px 20px; background: rgba(163, 230, 53, 0.1); 
            color: #bef264; border: 1px solid rgba(163, 230, 53, 0.3); 
            border-radius: 8px; text-decoration: none; font-weight: 600; 
            transition: all 0.2s; 
        }
        .btn:hover { background: rgba(163, 230, 53, 0.2); transform: translateY(-2px); }

        @media (max-width: 1024px) { .charts-container { grid-template-columns: 1fr; } }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <h1 class="title">Vitamins & Supplements Analytics</h1>
        <p class="subtitle">Usage trends, cost analysis, and inventory valuation.</p>
    </div>

    <div class="kpi-grid">
        <div class="kpi-card">
            <div class="kpi-label">Total Cost Incurred</div>
            <div class="kpi-value text-lime">₱<?= number_format($usage['total_spent'] / 1000, 1) ?>k</div>
            <div class="kpi-sub">Lifetime Usage Expenses</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">Applications</div>
            <div class="kpi-value"><?= number_format($usage['total_txns']) ?></div>
            <div class="kpi-sub">Individual Doses Given</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">Inventory Value</div>
            <div class="kpi-value text-white">₱<?= number_format($inv['inventory_value'] / 1000, 1) ?>k</div>
            <div class="kpi-sub"><?= number_format($inv['active_items']) ?> Items Available</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">Low Stock Alerts</div>
            <div class="kpi-value text-red"><?= number_format($inv['low_stock_count']) ?></div>
            <div class="kpi-sub">Items below 10 units</div>
        </div>
    </div>

    <div class="btn-group">
        <a href="vitamins_transaction_report.php" class="btn">View Detailed Report →</a>
    </div>

    <div class="charts-container">
        
        <div class="chart-box">
            <div class="chart-title">📉 Spending Trend (Last 12 Months)</div>
            <div style="flex-grow: 1; position: relative;">
                <canvas id="trendChart"></canvas>
            </div>
        </div>

        <div class="chart-box">
            <div class="chart-title">📦 Stock Value by Supplement</div>
            <div style="flex-grow: 1; position: relative;">
                <canvas id="stockChart"></canvas>
            </div>
        </div>

        <div class="chart-box">
            <div class="chart-title">📊 Top 5 Supplements Used (Frequency)</div>
            <div style="flex-grow: 1; position: relative;">
                <canvas id="topVitsChart"></canvas>
            </div>
        </div>

        <div class="chart-box">
            <div class="chart-title">🐷 Animals Receiving Most Supplements</div>
            <div style="flex-grow: 1; position: relative;">
                <canvas id="animalChart"></canvas>
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
                borderColor: '#a3e635', // Lime
                backgroundColor: 'rgba(163, 230, 53, 0.1)',
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

    // 2. Stock Value Pie Chart
    const stockData = <?= json_encode($stock_val) ?>;
    new Chart(document.getElementById('stockChart'), {
        type: 'doughnut',
        data: {
            labels: stockData.map(d => d.SUPPLY_NAME),
            datasets: [{
                data: stockData.map(d => d.TOTAL_COST),
                backgroundColor: ['#a3e635', '#4d7c0f', '#84cc16', '#3f6212', '#ecfccb'], // Lime shades
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'right', labels: { boxWidth: 12 } } }
        }
    });

    // 3. Top Supplements Bar Chart
    const topVits = <?= json_encode($top_vits) ?>;
    new Chart(document.getElementById('topVitsChart'), {
        type: 'bar',
        data: {
            labels: topVits.map(d => d.ITEM_NAME),
            datasets: [{
                label: 'Times Used',
                data: topVits.map(d => d.usage_count),
                backgroundColor: 'rgba(190, 242, 100, 0.7)', // Light Lime
                borderColor: '#bef264',
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

    // 4. Top Animal Consumers Horizontal Bar
    const animalData = <?= json_encode($top_animals) ?>;
    new Chart(document.getElementById('animalChart'), {
        type: 'bar',
        data: {
            labels: animalData.map(d => d.TAG_NO || 'Unknown'),
            datasets: [{
                label: 'Applications Received',
                data: animalData.map(d => d.txn_count),
                backgroundColor: 'rgba(101, 163, 13, 0.7)', // Darker Lime
                borderColor: '#4d7c0f',
                borderWidth: 1,
                borderRadius: 4
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { x: { beginAtZero: true } }
        }
    });
</script>

</body>
</html>