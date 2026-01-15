<?php
// reports/feed_analytics.php
error_reporting(0);
ini_set('display_errors', 0);
$page = "analytics";

include '../common/navbar.php';
include '../config/Connection.php';
include '../security/checkRole.php';
checkRole(2); // Farm Admin

try {
    if (!isset($conn)) { throw new Exception("Database connection failed."); }

    // --- 1. KPI: CONSUMPTION & INVENTORY METRICS ---
    
    // Usage Totals (From feed_transactions)
    $usage_sql = "SELECT 
                    COUNT(*) as total_feedings,
                    COALESCE(SUM(TRANSACTION_COST), 0) as total_spent,
                    COALESCE(SUM(QUANTITY_KG), 0) as total_kg_consumed
                  FROM feed_transactions";
    $usage = $conn->query($usage_sql)->fetch(PDO::FETCH_ASSOC);

    // Inventory Totals (From feeds table)
    $inv_sql = "SELECT 
                    COUNT(*) as active_feeds,
                    COALESCE(SUM(TOTAL_COST), 0) as inventory_value,
                    COALESCE(SUM(TOTAL_WEIGHT_KG), 0) as total_stock_kg,
                    SUM(CASE WHEN TOTAL_WEIGHT_KG < 50 THEN 1 ELSE 0 END) as low_stock_count
                FROM feeds";
    $inv = $conn->query($inv_sql)->fetch(PDO::FETCH_ASSOC);

    // --- 2. CHART: SPENDING TREND (Line) ---
    // Feed costs over the last 12 months
    $trend_sql = "SELECT 
                    DATE_FORMAT(TRANSACTION_DATE, '%Y-%m') as month_year,
                    SUM(TRANSACTION_COST) as cost
                  FROM feed_transactions
                  WHERE TRANSACTION_DATE >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                  GROUP BY month_year
                  ORDER BY month_year ASC";
    $trend_data = $conn->query($trend_sql)->fetchAll(PDO::FETCH_ASSOC);

    // --- 3. CHART: TOP FEEDS BY CONSUMPTION (Bar) ---
    // Joins with FEEDS to get the name
    $top_feeds_sql = "SELECT 
                        f.FEED_NAME, 
                        SUM(ft.QUANTITY_KG) as total_kg
                     FROM feed_transactions ft
                     LEFT JOIN feeds f ON ft.FEED_ID = f.FEED_ID
                     GROUP BY f.FEED_NAME
                     ORDER BY total_kg DESC
                     LIMIT 5";
    $top_feeds = $conn->query($top_feeds_sql)->fetchAll(PDO::FETCH_ASSOC);

    // --- 4. CHART: INVENTORY VALUE DISTRIBUTION (Pie) ---
    // Which feeds hold the most value in stock
    $stock_val_sql = "SELECT FEED_NAME, TOTAL_COST 
                      FROM feeds 
                      WHERE TOTAL_COST > 0
                      ORDER BY TOTAL_COST DESC 
                      LIMIT 5";
    $stock_val = $conn->query($stock_val_sql)->fetchAll(PDO::FETCH_ASSOC);

    // --- 5. CHART: TOP CONSUMERS (Horizontal Bar) ---
    // Animals consuming the most feed (by KG)
    $top_animal_sql = "SELECT 
                            ar.TAG_NO, 
                            SUM(ft.QUANTITY_KG) as total_kg
                        FROM feed_transactions ft
                        LEFT JOIN animal_records ar ON ft.ANIMAL_ID = ar.ANIMAL_ID
                        GROUP BY ar.TAG_NO
                        ORDER BY total_kg DESC
                        LIMIT 5";
    $top_animals = $conn->query($top_animal_sql)->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log("Feed Analytics Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feed Analytics</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        /* --- THEME: AMBER / ORANGE --- */
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
            background: linear-gradient(135deg, #f59e0b, #d97706); /* Amber Gradient */
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
            background: linear-gradient(90deg, #f59e0b, #b45309); 
        }
        .kpi-label { color: #94a3b8; font-size: 0.8rem; font-weight: 600; text-transform: uppercase; letter-spacing: 1px; }
        .kpi-value { font-size: 2.2rem; font-weight: 800; color: #fff; margin: 0.5rem 0; }
        .kpi-sub { font-size: 0.85rem; color: #64748b; }

        .text-amber { color: #fbbf24; }
        .text-orange { color: #fb923c; }
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
            padding: 10px 20px; background: rgba(245, 158, 11, 0.1); 
            color: #fbbf24; border: 1px solid rgba(245, 158, 11, 0.3); 
            border-radius: 8px; text-decoration: none; font-weight: 600; 
            transition: all 0.2s; 
        }
        .btn:hover { background: rgba(245, 158, 11, 0.2); transform: translateY(-2px); }

        @media (max-width: 1024px) { .charts-container { grid-template-columns: 1fr; } }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <h1 class="title">Feed & Feeding Analytics</h1>
        <p class="subtitle">Consumption rates, cost tracking, and inventory levels.</p>
    </div>

    <div class="kpi-grid">
        <div class="kpi-card">
            <div class="kpi-label">Total Feed Cost</div>
            <div class="kpi-value text-amber">₱<?= number_format($usage['total_spent'] / 1000, 1) ?>k</div>
            <div class="kpi-sub">Lifetime Consumption Cost</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">Total Consumed</div>
            <div class="kpi-value"><?= number_format($usage['total_kg_consumed'], 1) ?> <span style="font-size:1rem">kg</span></div>
            <div class="kpi-sub">Across <?= number_format($usage['total_feedings']) ?> Feedings</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">Inventory Value</div>
            <div class="kpi-value text-white">₱<?= number_format($inv['inventory_value'] / 1000, 1) ?>k</div>
            <div class="kpi-sub"><?= number_format($inv['total_stock_kg'], 1) ?> kg in Stock</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">Low Stock Alerts</div>
            <div class="kpi-value text-red"><?= number_format($inv['low_stock_count']) ?></div>
            <div class="kpi-sub">Feeds below 50 kg</div>
        </div>
    </div>

    <div class="btn-group">
        <a href="feed_transaction_report.php" class="btn">View Detailed Report →</a>
    </div>

    <div class="charts-container">
        
        <div class="chart-box">
            <div class="chart-title">📉 Feed Cost Trend (Last 12 Months)</div>
            <div style="flex-grow: 1; position: relative;">
                <canvas id="trendChart"></canvas>
            </div>
        </div>

        <div class="chart-box">
            <div class="chart-title">📦 Stock Value by Feed Type</div>
            <div style="flex-grow: 1; position: relative;">
                <canvas id="stockChart"></canvas>
            </div>
        </div>

        <div class="chart-box">
            <div class="chart-title">📊 Top 5 Feeds Consumed (KG)</div>
            <div style="flex-grow: 1; position: relative;">
                <canvas id="topFeedsChart"></canvas>
            </div>
        </div>

        <div class="chart-box">
            <div class="chart-title">🐷 Top Consumers (KG Eaten)</div>
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
                borderColor: '#f59e0b', // Amber
                backgroundColor: 'rgba(245, 158, 11, 0.1)',
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
            labels: stockData.map(d => d.FEED_NAME),
            datasets: [{
                data: stockData.map(d => d.TOTAL_COST),
                backgroundColor: ['#f59e0b', '#d97706', '#fbbf24', '#b45309', '#fcd34d'], // Amber/Orange shades
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'right', labels: { boxWidth: 12 } } }
        }
    });

    // 3. Top Feeds Bar Chart
    const topFeeds = <?= json_encode($top_feeds) ?>;
    new Chart(document.getElementById('topFeedsChart'), {
        type: 'bar',
        data: {
            labels: topFeeds.map(d => d.FEED_NAME),
            datasets: [{
                label: 'Total Consumed (KG)',
                data: topFeeds.map(d => d.total_kg),
                backgroundColor: 'rgba(251, 191, 36, 0.7)', // Light Amber
                borderColor: '#fbbf24',
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
                label: 'Consumed (KG)',
                data: animalData.map(d => d.total_kg),
                backgroundColor: 'rgba(217, 119, 6, 0.7)', // Darker Orange
                borderColor: '#b45309',
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