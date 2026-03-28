<?php
error_reporting(0);
ini_set('display_errors', 0);
$page="transactions";
include '../config/Connection.php';

include '../security/checkAccess.php';
checkAccess('feeding');
include '../common/navbar.php';
include '../common/chat_support.php';

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Single Feed Management | FarmPro</title>
    
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
            --border-active:  rgba(245,158,11,0.5); /* Amber Accent */
            
            /* Theme Colors */
            --amber:          #f59e0b; --amber-dim: rgba(245,158,11,0.12); --amber-glow: rgba(245,158,11,0.25);
            --orange:         #f97316;
            --emerald:        #10b981;
            --blue:           #3b82f6; --blue-dim: rgba(59,130,246,0.12);
            --cyan:           #06b6d4;
            --purple:         #a855f7;
            --red:            #f87171;
            
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
            background-image: radial-gradient(ellipse 80% 50% at 50% -20%, rgba(245,158,11,0.06) 0%, transparent 60%);
        }
        .container { max-width: 1400px; margin: 0 auto; padding: 2rem 1.5rem; }

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
            color: var(--amber); background: var(--amber-dim); border: 1px solid rgba(245,158,11,0.2);
            padding: 6px 12px; border-radius: 99px;
        }

        /* ─── HEADER ─── */
        .page-header { text-align: center; margin-bottom: 3.5rem; margin-top: 1rem; }
        .page-title {
            font-size: clamp(2rem, 4vw, 3rem); font-weight: 700;
            color: var(--text-primary); letter-spacing: -0.03em; line-height: 1.1; margin-bottom: 0.75rem;
        }
        .page-title span {
            background: linear-gradient(135deg, var(--amber), #b45309);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }
        .page-subtitle { color: var(--text-secondary); font-size: 1.1rem; margin-bottom: 0.5rem; }
        .page-description { color: var(--text-muted); font-size: 0.95rem; max-width: 600px; margin: 0 auto; }

        /* ─── QUICK STATS ─── */
        .quick-stats {
            background: var(--bg-surface); border: 1px solid var(--border);
            border-radius: var(--radius-xl); padding: 2rem; margin-bottom: 3.5rem;
            box-shadow: 0 10px 30px -10px rgba(0,0,0,0.5);
            animation: fadeInUp 0.5s ease-out;
        }
        .stats-title { font-size: 1.2rem; font-weight: 700; color: var(--text-primary); margin-bottom: 1.5rem; text-align: center; text-transform: uppercase; letter-spacing: 0.05em; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; }
        
        .stat-card { 
            text-align: center; padding: 1.5rem 1rem; background: var(--bg-elevated); 
            border: 1px solid var(--border); border-radius: var(--radius-lg); 
            transition: all var(--transition); display: flex; flex-direction: column; align-items: center; gap: 8px;
        }
        .stat-card:hover { transform: translateY(-4px); border-color: rgba(255,255,255,0.15); background: var(--bg-hover); }
        
        .stat-icon {
            width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center;
            font-size: 1.25rem; color: #fff; margin-bottom: 0.5rem;
        }
        .stat-icon.total { background: linear-gradient(135deg, var(--purple), #7c3aed); }
        .stat-icon.today { background: linear-gradient(135deg, var(--cyan), #0891b2); }
        .stat-icon.low { background: linear-gradient(135deg, var(--red), #dc2626); }
        .stat-icon.types { background: linear-gradient(135deg, var(--amber), #d97706); }

        .stat-value { font-size: 2.2rem; font-weight: 700; color: var(--amber); font-family: var(--font-mono); line-height: 1;}
        .stat-desc { color: var(--text-secondary); font-size: 0.85rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; }

        /* ─── MANAGEMENT GRID ─── */
        .management-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 2rem; margin-bottom: 3rem;
        }

        .management-card {
            background: var(--bg-surface); border: 1px solid var(--border);
            border-radius: var(--radius-xl); padding: 2.5rem; position: relative;
            overflow: hidden; display: flex; flex-direction: column;
            text-decoration: none; color: inherit; transition: all var(--transition);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); min-height: 380px;
            animation: fadeInUp 0.6s ease-out 0.1s both;
        }
        .management-card::before {
            content: ''; position: absolute; top: 0; left: -100%; width: 100%; height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.03), transparent);
            transition: left 0.8s ease; pointer-events: none;
        }
        .management-card:hover { transform: translateY(-6px); box-shadow: 0 20px 40px -10px rgba(0,0,0,0.5); }
        .management-card:hover::before { left: 100%; }

        /* Card Specific Hover Borders */
        .management-card.c-add:hover { border-color: rgba(59,130,246,0.4); }
        .management-card.c-stock:hover { border-color: rgba(245,158,11,0.4); }

        .card-icon {
            width: 72px; height: 72px; border-radius: var(--radius-lg);
            display: flex; align-items: center; justify-content: center;
            font-size: 2rem; color: white; box-shadow: 0 8px 16px rgba(0,0,0,0.3); 
            margin-bottom: 2rem; flex-shrink: 0; position: relative;
        }
        .card-icon.blue { background: linear-gradient(135deg, var(--blue), #1d4ed8); }
        .card-icon.amber { background: linear-gradient(135deg, var(--amber), #d97706); }

        .card-title { font-size: 1.5rem; font-weight: 700; color: #fff; margin-bottom: 0.75rem; }
        .card-description { color: var(--text-secondary); font-size: 0.95rem; line-height: 1.6; margin-bottom: 1.5rem; }

        .card-features { list-style: none; margin-bottom: 2.5rem; padding: 0; flex-grow: 1; }
        .card-features li {
            display: flex; align-items: flex-start; gap: 10px;
            color: var(--text-primary); font-size: 0.9rem; margin-bottom: 12px; line-height: 1.4;
        }
        .card-features li i { color: var(--emerald); font-size: 1rem; margin-top: 2px;}

        .card-stats {
            display: flex; justify-content: space-around; align-items: center;
            padding: 1.5rem 0 0 0; border-top: 1px solid var(--border); margin-bottom: 1.5rem;
        }
        .c-stat-group { display: flex; flex-direction: column; gap: 4px; text-align: center;}
        .c-stat-group .num { font-size: 1.5rem; font-weight: 700; color: #fff; font-family: var(--font-mono); line-height: 1; }
        .c-stat-group .lbl { font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700; letter-spacing: 0.05em; }
        
        .card-action {
            font-size: 1rem; font-weight: 700; color: #000; background: var(--amber);
            transition: all var(--transition); display: flex; align-items: center; justify-content: center; gap: 8px;
            padding: 14px; border-radius: var(--radius-md); border: none; font-family: var(--font); width: 100%;
        }
        .management-card.c-add .card-action { background: var(--blue); color: #fff; }
        .management-card.c-add:hover .card-action { background: #60a5fa; box-shadow: 0 4px 15px rgba(59,130,246,0.3);}
        .management-card.c-stock:hover .card-action { background: #fbbf24; box-shadow: 0 4px 15px var(--amber-glow);}

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* ─── RESPONSIVE ─── */
        @media (max-width: 768px) {
            .container { padding: 1rem; }
            .page-header { margin-bottom: 2rem;}
            .management-grid { grid-template-columns: 1fr; gap: 1.5rem;}
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .management-card { padding: 1.5rem; min-height: auto;}
        }
    </style>
</head>
<body>
    
    
    <div class="container">
        
        <div class="top-bar">
            <a href="transactions.php" class="back-link">
                <i class="fa-solid fa-arrow-left"></i> Back to Transactions
            </a>
            <span class="page-badge"><i class="fa-solid fa-bowl-food"></i> Nutrition Control</span>
        </div>

        <header class="page-header">
            <h1 class="page-title">Single Feed <span>Management</span></h1>
            <p class="page-subtitle">Track targeted feeding transactions and monitor inventory.</p>
            <p class="page-description">Record specialized diets or monitored feed consumption for specific high-value livestock.</p>
        </header>

        <div class="quick-stats">
            <h2 class="stats-title">System Inventory Overview</h2>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon total"><i class="fa-solid fa-chart-column"></i></div>
                    <div class="stat-value" data-target="2450">0</div>
                    <div class="stat-desc">Total Trans.</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon today"><i class="fa-solid fa-calendar-day"></i></div>
                    <div class="stat-value" data-target="12">0</div>
                    <div class="stat-desc">Today's Feeds</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon low"><i class="fa-solid fa-triangle-exclamation"></i></div>
                    <div class="stat-value" data-target="3">0</div>
                    <div class="stat-desc">Low Stock Items</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon types"><i class="fa-solid fa-wheat-awn"></i></div>
                    <div class="stat-value" data-target="15">0</div>
                    <div class="stat-desc">Feed Types</div>
                </div>
            </div>
        </div>

        <div class="management-grid">
            
            <a href="single_feed_transaction.php" class="management-card c-add">
                <div class="card-icon blue"><i class="fa-solid fa-pen-to-square"></i></div>
                <h3 class="card-title">Add Single Feeding</h3>
                <p class="card-description">Record new targeted feeding activities, log specific diets, and maintain detailed consumption histories for individual animals.</p>
                
                <ul class="card-features">
                    <li><i class="fa-solid fa-check"></i> Assign precise feed quantities to a single Tag No.</li>
                    <li><i class="fa-solid fa-check"></i> Attach notes, conditions, and dietary remarks.</li>
                    <li><i class="fa-solid fa-check"></i> Automatically deduct from central warehouse inventory.</li>
                </ul>

                <div class="card-stats">
                    <div class="c-stat-group">
                        <span class="num" style="color:var(--blue);">12</span>
                        <span class="lbl">Logs Today</span>
                    </div>
                    <div class="c-stat-group">
                        <span class="num">387</span>
                        <span class="lbl">This Month</span>
                    </div>
                </div>

                <button class="card-action">
                    <i class="fa-solid fa-plus"></i> Record Transaction
                </button>
            </a>

            <a href="available_feeds.php" class="management-card c-stock">
                <div class="card-icon amber"><i class="fa-solid fa-boxes-stacked"></i></div>
                <h3 class="card-title">Check Feeds Availability</h3>
                <p class="card-description">Monitor current silo and warehouse inventory levels, verify stock availability, and preemptively manage shortages.</p>
                
                <ul class="card-features">
                    <li><i class="fa-solid fa-check"></i> View real-time kilogram and sack volumes.</li>
                    <li><i class="fa-solid fa-check"></i> Track specific nutritional types (Starter, Grower, etc).</li>
                    <li><i class="fa-solid fa-check"></i> Identify low stock warnings before depletion.</li>
                </ul>

                <div class="card-stats">
                    <div class="c-stat-group">
                        <span class="num">15</span>
                        <span class="lbl">Feed Types</span>
                    </div>
                    <div class="c-stat-group">
                        <span class="num" style="color:var(--red);">3</span>
                        <span class="lbl">Low Stock</span>
                    </div>
                </div>

                <button class="card-action">
                    <i class="fa-solid fa-magnifying-glass"></i> Check Inventory
                </button>
            </a>

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
                    const finalValue = parseInt(stat.getAttribute('data-target'));
                    let currentValue = 0;
                    
                    // Determine increment based on final number to ensure smooth animation
                    const increment = Math.ceil(finalValue / 30); 
                    
                    const counter = setInterval(() => {
                        currentValue += increment;
                        if (currentValue >= finalValue) {
                            // Ensure final value is exact and formatted with commas
                            stat.textContent = finalValue.toLocaleString('en-US');
                            clearInterval(counter);
                        } else {
                            stat.textContent = currentValue.toLocaleString('en-US');
                        }
                    }, 40);
                });
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            new FeedPage();
        });
    </script>
</body>
</html>