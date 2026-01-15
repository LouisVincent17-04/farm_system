<?php
// reports/maintenance_analytics.php
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
    // Counts items under 'Maintenance & Parts' (ITEM_TYPE_ID = 8)
    $kpi_sql = "SELECT 
                    COUNT(*) as distinct_parts,
                    COALESCE(SUM(TOTAL_COST), 0) as total_inventory_value,
                    COALESCE(SUM(QUANTITY), 0) as total_units
                FROM items 
                WHERE ITEM_TYPE_ID = 8 AND STATUS = 1";
    $kpi = $conn->query($kpi_sql)->fetch(PDO::FETCH_ASSOC);

    // Calculate Average Cost per Part
    $avg_cost = ($kpi['total_units'] > 0) 
        ? ($kpi['total_inventory_value'] / $kpi['total_units']) 
        : 0;

    // --- 2. CHART: COST DISTRIBUTION (Pie) ---
    // High-value maintenance parts
    $dist_sql = "SELECT ITEM_NAME, TOTAL_COST 
                 FROM items 
                 WHERE ITEM_TYPE_ID = 8 AND STATUS = 1
                 ORDER BY TOTAL_COST DESC 
                 LIMIT 5";
    $dist_data = $conn->query($dist_sql)->fetchAll(PDO::FETCH_ASSOC);

    // --- 3. CHART: SPENDING TREND (Line) ---
    // Acquisition of parts over the last 12 months
    $trend_sql = "SELECT 
                    DATE_FORMAT(CREATED_AT, '%Y-%m') as month_year,
                    SUM(TOTAL_COST) as cost
                  FROM items
                  WHERE ITEM_TYPE_ID = 8 AND STATUS = 1
                  AND CREATED_AT >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                  GROUP BY month_year
                  ORDER BY month_year ASC";
    $trend_data = $conn->query($trend_sql)->fetchAll(PDO::FETCH_ASSOC);

    // --- 4. CHART: INVENTORY COUNT (Bar) ---
    // Parts with highest stock quantity
    $qty_sql = "SELECT ITEM_NAME, QUANTITY 
                FROM items 
                WHERE ITEM_TYPE_ID = 8 AND STATUS = 1
                ORDER BY QUANTITY DESC 
                LIMIT 5";
    $qty_data = $conn->query($qty_sql)->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log("Maintenance Analytics Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance & Parts Analytics</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        /* --- THEME: STONE / GOLD --- */
        body { 
            font-family: system-ui, -apple-system, sans-serif; 
            background: linear-gradient(135deg, #0c0a09 0%, #1c1917 100%); /* Dark Stone */
            color: #e7e5e4; 
            margin: 0; padding-bottom: 40px; 
        }
        .container { max-width: 1400px; margin: 0 auto; padding: 2rem; }
        
        .header { text-align: center; margin-bottom: 2rem; }
        .title { 
            font-size: 2.2rem; font-weight: 800; 
            background: linear-gradient(135deg, #eab308, #ca8a04); /* Gold Gradient */
            -webkit-background-clip: text; -webkit-text-fill-color: transparent; 
            margin-bottom: 0.5rem;
        }
        .subtitle { color: #a8a29e; font-size: 1rem; margin: 0; }

        /* KPI Grid */
        .kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
        .kpi-card { 
            background: rgba(41, 37, 36, 0.6); border: 1px solid rgba(255,255,255,0.05); 
            border-radius: 16px; padding: 1.5rem; backdrop-filter: blur(10px); 
            position: relative; overflow: hidden;
        }
        .kpi-card::after { 
            content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 4px; 
            background: linear-gradient(90deg, #eab308, #854d0e); 
        }
        .kpi-label { color: #a8a29e; font-size: 0.8rem; font-weight: 600; text-transform: uppercase; letter-spacing: 1px; }
        .kpi-value { font-size: 2.2rem; font-weight: 800; color: #fff; margin: 0.5rem 0; }
        .kpi-sub { font-size: 0.85rem; color: #78716c; }

        .text-gold { color: #facc15; }
        .text-stone { color: #d6d3d1; }
        .text-white { color: #fff; }

        /* Chart Grid */
        .charts-container { 
            display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 2rem; 
        }
        .chart-box { 
            background: rgba(41, 37, 36, 0.6); border: 1px solid rgba(255,255,255,0.05); 
            border-radius: 16px; padding: 1.5rem; min-height: 350px; display: flex; flex-direction: column;
        }
        .chart-title { font-size: 1.1rem; font-weight: 700; color: #e7e5e4; margin-bottom: 1rem; }

        /* Buttons */
        .btn-group { display: flex; justify-content: flex-end; margin-bottom: 1rem; }
        .btn { 
            padding: 10px 20px; background: rgba(234, 179, 8, 0.1); 
            color: #facc15; border: 1px solid rgba(234, 179, 8, 0.3); 
            border-radius: 8px; text-decoration: none; font-weight: 600; 
            transition: all 0.2s; 
        }
        .btn:hover { background: rgba(234, 179, 8, 0.2); transform: translateY(-2px); }

        @media (max-width: 1024px) { .charts-container { grid-template-columns: 1fr; } }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <h1 class="title">Maintenance & Parts Analytics</h1>
        <p class="subtitle">Spare parts inventory, repair costs, and stock levels.</p>
    </div>

    <div class="kpi-grid">
        <div class="kpi-card">
            <div class="kpi-label">Inventory Value</div>
            <div class="kpi-value text-gold">₱<?= number_format($kpi['total_inventory_value'] / 1000, 1) ?>k</div>
            <div class="kpi-sub">Total Parts Valuation</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">Distinct Parts</div>
            <div class="kpi-value text-stone"><?= number_format($kpi['distinct_parts']) ?></div>
            <div class="kpi-sub">Unique Item Types</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">Total Units</div>
            <div class="kpi-value"><?= number_format($kpi['total_units']) ?></div>
            <div class="kpi-sub">Stock on Hand</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">Avg. Cost / Part</div>
            <div class="kpi-value text-white">₱<?= number_format($avg_cost, 2) ?></div>
            <div class="kpi-sub">Per Unit Average</div>
        </div>
    </div>

    <div class="charts-container">
        
        <div class="chart-box">
            <div class="chart-title">🏗️ Maintenance Cost Trend (Last 12 Months)</div>
            <div style="flex-grow: 1; position: relative;">
                <canvas id="trendChart"></canvas>
            </div>
        </div>

        <div class="chart-box">
            <div class="chart-title">💰 Value Distribution by Part</div>
            <div style="flex-grow: 1; position: relative;">
                <canvas id="distChart"></canvas>
            </div>
        </div>

        <div class="chart-box" style="grid-column: 1 / -1;">
            <div class="chart-title">📊 Top 5 Parts by Quantity</div>
            <div style="flex-grow: 1; position: relative; max-height: 300px;">
                <canvas id="qtyChart"></canvas>
            </div>
        </div>

    </div>
</div>

<script>
    // --- CHART DEFAULTS ---
    Chart.defaults.color = '#a8a29e';
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
                borderColor: '#eab308', // Gold
                backgroundColor: 'rgba(234, 179, 8, 0.1)',
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
                backgroundColor: ['#ca8a04', '#eab308', '#facc15', '#fde047', '#78350f'], // Gold/Brown shades
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
                backgroundColor: 'rgba(250, 204, 21, 0.7)', // Yellow
                borderColor: '#eab308',
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