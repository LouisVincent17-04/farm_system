<?php
error_reporting(0);
ini_set('display_errors', 0);
$page="transactions";
include '../common/navbar.php';
include '../security/checkRole.php';    
checkRole(3);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FarmPro Feed Management</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: #e2e8f0;
            min-height: 100vh;
        }

        .feed-container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .feed-header {
            text-align: center;
            margin-bottom: 3rem;
        }

        .feed-title {
            font-size: 3rem;
            font-weight: bold;
            margin-bottom: 1rem;
            background: linear-gradient(135deg, #22c55e, #16a34a);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .feed-subtitle {
            color: #94a3b8;
            font-size: 1.2rem;
            margin-bottom: 0.5rem;
        }

        .feed-description {
            color: #64748b;
            font-size: 1rem;
        }

        /* Feed Grid */
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

        .feed-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(34, 197, 94, 0.05), transparent);
            transition: left 0.8s;
        }

        .feed-card:hover::before {
            left: 100%;
        }

        .feed-card:hover {
            transform: translateY(-8px) scale(1.02);
            border-color: rgba(34, 197, 94, 0.4);
            box-shadow: 0 25px 50px rgba(34, 197, 94, 0.15);
        }

        .card-icon {
            width: 80px;
            height: 80px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            color: white;
            margin-bottom: 2rem;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
        }

        .card-icon.transaction { background: linear-gradient(135deg, #3b82f6, #1d4ed8); }
        .card-icon.availability { background: linear-gradient(135deg, #f59e0b, #d97706); }

        .card-title {
            font-size: 1.8rem;
            font-weight: 600;
            color: #22c55e;
            margin-bottom: 1rem;
        }

        .card-description {
            color: #94a3b8;
            font-size: 1rem;
            line-height: 1.6;
            margin-bottom: 2rem;
        }

        .card-features {
            list-style: none;
            margin-bottom: 2rem;
        }

        .card-features li {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            color: #cbd5e1;
            font-size: 0.95rem;
            margin-bottom: 0.75rem;
            padding: 0.5rem 0;
        }

        .card-features li::before {
            content: '✓';
            color: #22c55e;
            font-weight: bold;
            font-size: 1.1rem;
        }

        .card-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid rgba(30, 41, 59, 0.8);
        }

        .stat-item {
            text-align: center;
            padding: 1rem;
            background: rgba(15, 23, 42, 0.5);
            border-radius: 12px;
            transition: all 0.3s ease;
        }

        .stat-item:hover {
            background: rgba(15, 23, 42, 0.7);
            transform: translateY(-2px);
        }

        .stat-number {
            font-size: 1.5rem;
            font-weight: bold;
            color: #22c55e;
            margin-bottom: 0.25rem;
        }

        .stat-label {
            font-size: 0.85rem;
            color: #64748b;
        }

        .card-action {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            background: linear-gradient(135deg, #22c55e, #16a34a);
            color: white;
            padding: 1rem 2rem;
            border-radius: 12px;
            font-weight: 600;
            font-size: 1rem;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
        }

        .card-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(34, 197, 94, 0.3);
            background: linear-gradient(135deg, #16a34a, #15803d);
        }

        /* Quick Stats Section */
        .quick-stats {
            background: rgba(30, 41, 59, 0.6);
            border: 1px solid rgba(34, 197, 94, 0.2);
            border-radius: 16px;
            padding: 2rem;
            backdrop-filter: blur(10px);
            margin-bottom: 2rem;
        }

        .quick-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #22c55e;
            margin-bottom: 1.5rem;
            text-align: center;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .quick-stat-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.5rem;
            padding: 1.5rem;
            background: rgba(15, 23, 42, 0.5);
            border-radius: 12px;
            transition: all 0.3s ease;
        }

        .quick-stat-item:hover {
            background: rgba(15, 23, 42, 0.7);
            transform: translateY(-2px);
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }

        .stat-icon.total { background: linear-gradient(135deg, #8b5cf6, #7c3aed); }
        .stat-icon.today { background: linear-gradient(135deg, #06b6d4, #0891b2); }
        .stat-icon.low { background: linear-gradient(135deg, #ef4444, #dc2626); }
        .stat-icon.types { background: linear-gradient(135deg, #ec4899, #db2777); }

        .stat-value {
            font-size: 1.8rem;
            font-weight: bold;
            color: #22c55e;
        }

        .stat-label-text {
            color: #94a3b8;
            font-size: 0.9rem;
            text-align: center;
        }



        /* Responsive Design */
        @media (max-width: 768px) {
            body {
                padding: 1rem;
            }

            .feed-title {
                font-size: 2rem;
            }

            .feed-subtitle {
                font-size: 1rem;
            }

            .feed-grid {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }

            .feed-card {
                padding: 2rem;
            }

            .card-icon {
                width: 70px;
                height: 70px;
                font-size: 2rem;
            }

            .card-title {
                font-size: 1.5rem;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 480px) {
            .feed-title {
                font-size: 1.5rem;
            }

            .feed-card {
                padding: 1.5rem;
            }

            .card-icon {
                width: 60px;
                height: 60px;
                font-size: 1.5rem;
            }

            .card-stats {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Animation Effects */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .feed-card {
            animation: fadeInUp 0.6s ease-out;
        }

        .feed-card:nth-child(1) { animation-delay: 0.1s; }
        .feed-card:nth-child(2) { animation-delay: 0.3s; }

        .quick-stats {
            animation: fadeInUp 0.8s ease-out;
            animation-delay: 0.5s;
        }

        /* Active state for clicked cards */
        .feed-card.active {
            border-color: #22c55e;
            box-shadow: 0 0 30px rgba(34, 197, 94, 0.3);
        }
    </style>
</head>
<body>
    <div class="feed-container">
        <!-- Header Section -->
        <header class="feed-header">
            <h1 class="feed-title">Feed Management</h1>
            <p class="feed-subtitle">Track feeding transactions and monitor feed inventory</p>
            <p class="feed-description">Manage all aspects of livestock feeding and feed stock levels</p>
        </header>

        <!-- Quick Stats -->
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

        <!-- Main Feed Grid -->
        <div class="feed-grid">
            <!-- Add Feeding Transaction -->
            <div class="feed-card" data-feed="add-transaction">
                <div class="card-icon transaction">📝</div>
                <h3 class="card-title">Add Feeding Transaction</h3>
                <p class="card-description">Record new feeding activities, track feed consumption, and maintain detailed feeding logs for all livestock.</p>
                
                <ul class="card-features">
                    <li>Record Feed Distribution</li>
                    <li>Track Animal Groups</li>
                    <li>Log Feed Quantities</li>
                    <li>Set Feeding Schedules</li>
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

                <button class="card-action" onclick="window.location.href='feeding_transactions.php'">
                    ➕ Add Transaction
                </button>
            </div>

            <!-- Check Feeds Availability -->
            <div class="feed-card" data-feed="check-availability">
                <div class="card-icon availability">📦</div>
                <h3 class="card-title">Check Feeds Availability</h3>
                <p class="card-description">Monitor feed inventory levels, check stock availability, and receive alerts for low stock items to prevent shortages.</p>
                
                <ul class="card-features">
                    <li>View Current Stock Levels</li>
                    <li>Check Feed Expiry Dates</li>
                    <li>Low Stock Alerts</li>
                    <li>Feed Type Categories</li>
                    <li>Consumption Analytics</li>
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

                <button class="card-action" onclick="window.location.href='available_feeds.php'">
                    🔍 Check Availability
                </button>
            </div>
        </div>
    </div>

    <script>
        class FeedPage {
            constructor() {
                this.feedCards = document.querySelectorAll('.feed-card');
                this.statItems = document.querySelectorAll('.quick-stat-item');
                this.init();
            }

            init() {
                this.bindEvents();
                this.animateElements();
                this.loadRecentStats();
            }

            bindEvents() {
                this.feedCards.forEach(card => {
                    card.addEventListener('mouseenter', (e) => {
                        this.handleCardHover(card, true);
                    });

                    card.addEventListener('mouseleave', (e) => {
                        this.handleCardHover(card, false);
                    });
                });

                document.addEventListener('keydown', (e) => {
                    if (e.key === 'Escape') {
                        this.clearActiveStates();
                    }
                });
            }

            handleCardHover(card, isHovering) {
                if (isHovering) {
                    card.style.zIndex = '10';
                } else {
                    card.style.zIndex = '1';
                }
            }

            clearActiveStates() {
                this.feedCards.forEach(card => {
                    card.classList.remove('active');
                });
            }

            animateElements() {
                this.feedCards.forEach((card, index) => {
                    card.style.animationDelay = `${index * 0.2}s`;
                });

                this.statItems.forEach((item, index) => {
                    item.style.opacity = '0';
                    item.style.transform = 'translateY(20px)';
                    item.style.transition = 'all 0.6s ease-out';
                    
                    setTimeout(() => {
                        item.style.opacity = '1';
                        item.style.transform = 'translateY(0)';
                    }, 800 + (index * 100));
                });
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

            updateStats(feed, stats) {
                const card = document.querySelector(`[data-feed="${feed}"]`);
                if (card) {
                    const statNumbers = card.querySelectorAll('.stat-number');
                    stats.forEach((stat, index) => {
                        if (statNumbers[index]) {
                            statNumbers[index].textContent = stat;
                            statNumbers[index].style.transform = 'scale(1.1)';
                            setTimeout(() => {
                                statNumbers[index].style.transform = 'scale(1)';
                            }, 300);
                        }
                    });
                }
            }

            highlightFeed(feed) {
                const card = document.querySelector(`[data-feed="${feed}"]`);
                if (card) {
                    card.classList.add('active');
                    card.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    
                    setTimeout(() => {
                        card.classList.remove('active');
                    }, 3000);
                }
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            window.feedPage = new FeedPage();
        });
    </script>
</body>
</html>