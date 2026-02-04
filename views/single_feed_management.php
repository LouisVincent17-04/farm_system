<?php
error_reporting(0);
ini_set('display_errors', 0);
$page="transactions";
include '../config/Connection.php';

include '../security/checkAccess.php';
checkAccess('feeding');
include '../common/navbar.php';

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FarmPro Feed Management</title>
    <style>
        /* --- GLOBAL STYLES --- */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: #e2e8f0;
            min-height: 100vh;
        }
        .feed-container { max-width: 1200px; margin: 0 auto; padding: 2rem; }

        /* --- BACK LINK --- */
        .back-link {
            display: inline-flex; align-items: center; gap: 8px; 
            text-decoration: none; color: #94a3b8; font-weight: 600; 
            font-size: 0.95rem; margin-bottom: 20px; transition: color 0.2s;
        }
        .back-link:hover { color: white; }

        /* --- HEADER --- */
        .feed-header { text-align: center; margin-bottom: 3rem; }
        .feed-title {
            font-size: 3rem; font-weight: bold; margin-bottom: 1rem;
            background: linear-gradient(135deg, #22c55e, #16a34a);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
        }
        .feed-subtitle { color: #94a3b8; font-size: 1.2rem; margin-bottom: 0.5rem; }
        .feed-description { color: #64748b; font-size: 1rem; }

        /* --- GRID & CARDS --- */
        .feed-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .feed-card {
            background: rgba(30, 41, 59, 0.6);
            border: 1px solid rgba(34, 197, 94, 0.2);
            border-radius: 16px;
            padding: 2.5rem;
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }

        .feed-card:hover {
            transform: translateY(-8px) scale(1.02);
            border-color: rgba(34, 197, 94, 0.4);
            box-shadow: 0 25px 50px rgba(34, 197, 94, 0.15);
        }

        .card-icon {
            width: 80px; height: 80px; border-radius: 20px;
            display: flex; align-items: center; justify-content: center;
            font-size: 2.5rem; color: white; margin-bottom: 2rem;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
        }
        .card-icon.transaction { background: linear-gradient(135deg, #3b82f6, #1d4ed8); }
        .card-icon.availability { background: linear-gradient(135deg, #f59e0b, #d97706); }

        .card-title { font-size: 1.8rem; font-weight: 600; color: #22c55e; margin-bottom: 1rem; }
        .card-description { color: #94a3b8; font-size: 1rem; line-height: 1.6; margin-bottom: 2rem; }

        .card-features { list-style: none; margin-bottom: 2rem; }
        .card-features li {
            display: flex; align-items: center; gap: 0.75rem;
            color: #cbd5e1; font-size: 0.95rem; margin-bottom: 0.75rem;
        }
        .card-features li::before { content: '✓'; color: #22c55e; font-weight: bold; font-size: 1.1rem; }

        .card-stats {
            display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;
            margin-bottom: 2rem; padding-top: 1.5rem;
            border-top: 1px solid rgba(30, 41, 59, 0.8);
        }

        .stat-item {
            text-align: center; padding: 1rem;
            background: rgba(15, 23, 42, 0.5); border-radius: 12px;
            transition: all 0.3s ease;
        }
        .stat-number { font-size: 1.5rem; font-weight: bold; color: #22c55e; margin-bottom: 0.25rem; }
        .stat-label { font-size: 0.85rem; color: #64748b; }

        .card-action {
            display: flex; align-items: center; justify-content: center; gap: 0.75rem;
            background: linear-gradient(135deg, #22c55e, #16a34a);
            color: white; padding: 1rem 2rem; border-radius: 12px;
            font-weight: 600; font-size: 1rem; border: none;
            cursor: pointer; transition: all 0.3s ease; width: 100%;
        }
        .card-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(34, 197, 94, 0.3);
            background: linear-gradient(135deg, #16a34a, #15803d);
        }

        /* --- QUICK STATS --- */
        .quick-stats {
            background: rgba(30, 41, 59, 0.6); border: 1px solid rgba(34, 197, 94, 0.2);
            border-radius: 16px; padding: 2rem; backdrop-filter: blur(10px); margin-bottom: 2rem;
        }
        .quick-title {
            font-size: 1.5rem; font-weight: 600; color: #22c55e; margin-bottom: 1.5rem; text-align: center;
        }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; }
        .quick-stat-item {
            display: flex; flex-direction: column; align-items: center; gap: 0.5rem;
            padding: 1.5rem; background: rgba(15, 23, 42, 0.5); border-radius: 12px; transition: all 0.3s ease;
        }
        .quick-stat-item:hover { transform: translateY(-2px); background: rgba(15, 23, 42, 0.7); }
        
        .stat-icon {
            width: 50px; height: 50px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.5rem; color: white;
        }
        .stat-icon.total { background: linear-gradient(135deg, #8b5cf6, #7c3aed); }
        .stat-icon.today { background: linear-gradient(135deg, #06b6d4, #0891b2); }
        .stat-icon.low { background: linear-gradient(135deg, #ef4444, #dc2626); }
        .stat-icon.types { background: linear-gradient(135deg, #ec4899, #db2777); }

        .stat-value { font-size: 1.8rem; font-weight: bold; color: #22c55e; }
        .stat-label-text { color: #94a3b8; font-size: 0.9rem; text-align: center; }

        @media (max-width: 768px) {
            .feed-grid { grid-template-columns: 1fr; }
            .feed-title { font-size: 2rem; }
        }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .feed-card, .quick-stats { animation: fadeInUp 0.6s ease-out; }
    </style>
</head>
<body>
    <div class="feed-container">
        
        <a href="transactions.php" class="back-link">
            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            Back to Transactions
        </a>

        <header class="feed-header">
            <h1 class="feed-title">Single Feed Management</h1>
            <p class="feed-subtitle">Track feeding transactions and monitor feed inventory</p>
            <p class="feed-description">Manage all aspects of livestock feeding and feed stock levels</p>
        </header>

        <div class="quick-stats">
            <h2 class="quick-title">Feed Overview</h2>
            <div class="stats-grid">
                <div class="quick-stat-item">
                    <div class="stat-icon total">📊</div>
                    <div class="stat-value">2,450</div>
                    <div class="stat-label-text">Total Transactions</div>
                </div>
                <div class="quick-stat-item">
                    <div class="stat-icon today">📅</div>
                    <div class="stat-value">12</div>
                    <div class="stat-label-text">Today's Feedings</div>
                </div>
                <div class="quick-stat-item">
                    <div class="stat-icon low">⚠️</div>
                    <div class="stat-value">3</div>
                    <div class="stat-label-text">Low Stock Items</div>
                </div>
                <div class="quick-stat-item">
                    <div class="stat-icon types">🌾</div>
                    <div class="stat-value">15</div>
                    <div class="stat-label-text">Feed Types</div>
                </div>
            </div>
        </div>

        <div class="feed-grid">
            <div class="feed-card" onclick="window.location.href='single_feed_transaction.php'">
                <div class="card-icon transaction">📝</div>
                <h3 class="card-title">Add Single Feeding</h3>
                <p class="card-description">Record new feeding activities, track feed consumption, and maintain detailed feeding logs for all livestock.</p>
                
                <ul class="card-features">
                    <li>Record Feed Distribution</li>
                    <li>Track Animal Groups</li>
                    <li>Log Feed Quantities</li>
                    <li>Add Notes & Comments</li>
                    <li>Real-time Stock Updates</li>
                </ul>

                <div class="card-stats">
                    <div class="stat-item">
                        <div class="stat-number">12</div>
                        <div class="stat-label">Today</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number">387</div>
                        <div class="stat-label">This Month</div>
                    </div>
                </div>

                <button class="card-action">
                    ➕ Add Transaction
                </button>
            </div>

            <div class="feed-card" onclick="window.location.href='available_feeds.php'">
                <div class="card-icon availability">📦</div>
                <h3 class="card-title">Check Feeds Availability</h3>
                <p class="card-description">Monitor feed inventory levels, check stock availability, and receive alerts for low stock items.</p>
                
                <ul class="card-features">
                    <li>View Current Stock Levels</li>
                    <li>Check Feed Expiry Dates</li>
                    <li>Low Stock Alerts</li>
                    <li>Feed Type Categories</li>
                    <li>Reorder Recommendations</li>
                </ul>

                <div class="card-stats">
                    <div class="stat-item">
                        <div class="stat-number">15</div>
                        <div class="stat-label">Feed Types</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number">3</div>
                        <div class="stat-label">Low Stock</div>
                    </div>
                </div>

                <button class="card-action">
                    🔍 Check Availability
                </button>
            </div>
        </div>
    </div>

    <script>
        class FeedPage {
            constructor() {
                this.loadRecentStats();
            }

            loadRecentStats() {
                const statValues = document.querySelectorAll('.stat-value');
                statValues.forEach(stat => {
                    const finalValue = parseInt(stat.textContent);
                    let currentValue = 0;
                    const increment = Math.ceil(finalValue / 20);
                    
                    const counter = setInterval(() => {
                        currentValue += increment;
                        if (currentValue >= finalValue) {
                            stat.textContent = finalValue;
                            clearInterval(counter);
                        } else {
                            stat.textContent = currentValue;
                        }
                    }, 50);
                });
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            new FeedPage();
        });
    </script>
</body>
</html>