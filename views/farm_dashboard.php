<?php
// views/farm_dashboard.php
$page = "farm"; // Active Tab
include '../common/navbar.php';
include '../config/Connection.php';
include '../security/checkRole.php';    
checkRole(2); 
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Farm Administration - FarmPro</title>
    <style>
        /* --- EXACT STYLES FROM YOUR COSTING DASHBOARD --- */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: #e2e8f0;
            min-height: 100vh;
        }
        .container { max-width: 1400px; margin: 0 auto; padding: 2rem; }
        
        .page-header { text-align: center; margin-bottom: 3rem; }
        
        .page-title {
            font-size: 3rem; font-weight: bold; margin-bottom: 1rem;
            background: linear-gradient(135deg, #22c55e, #16a34a);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .page-subtitle { color: #94a3b8; font-size: 1.2rem; margin-bottom: 0.5rem; }
        .page-description { color: #64748b; font-size: 1rem; }
        
        /* Grid Layout */
        .categories-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        /* Cards */
        .category-card {
            background: rgba(30, 41, 59, 0.6);
            border: 1px solid rgba(34, 197, 94, 0.2); /* Green Border */
            border-radius: 16px;
            padding: 2rem;
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
            display: flex;
            flex-direction: column;
            position: relative;
            overflow: hidden;
        }
        .category-card:hover {
            transform: translateY(-8px) scale(1.02);
            border-color: rgba(34, 197, 94, 0.4);
            box-shadow: 0 20px 40px rgba(34, 197, 94, 0.15);
        }
        
        .category-header { display: flex; align-items: center; gap: 1rem; margin-bottom: 1.5rem; }
        
        /* Icons */
        .category-icon {
            width: 60px; height: 60px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 2rem; color: white;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.3);
        }
        
        /* Colors for Farm Modules */
        .category-icon.blue   { background: linear-gradient(135deg, #3b82f6, #1d4ed8); }
        .category-icon.pink   { background: linear-gradient(135deg, #ec4899, #db2777); }
        .category-icon.orange { background: linear-gradient(135deg, #f97316, #ea580c); }
        .category-icon.green  { background: linear-gradient(135deg, #10b981, #059669); }
        .category-icon.purple { background: linear-gradient(135deg, #8b5cf6, #7c3aed); }
        .category-icon.teal   { background: linear-gradient(135deg, #06b6d4, #0891b2); }
        .category-icon.red    { background: linear-gradient(135deg, #ef4444, #b91c1c); }
        .category-icon.yellow { background: linear-gradient(135deg, #eab308, #ca8a04); } /* NEW YELLOW */

        .category-info { flex: 1; }
        
        /* Exact Green Title Color requested */
        .category-title { font-size: 1.3rem; font-weight: 600; color: #22c55e; margin-bottom: 0.5rem; }
        .category-subtitle { color: #64748b; font-size: 0.9rem; }

        /* Preview Box */
        .analytics-preview {
            margin-bottom: 1rem; padding: 1rem;
            background: rgba(15, 23, 42, 0.5);
            border-radius: 8px;
            border: 1px solid rgba(34, 197, 94, 0.1);
            flex-grow: 1;
        }
        .analytics-preview-title {
            color: #94a3b8; font-size: 0.85rem; margin-bottom: 0.75rem;
            font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px;
        }
        .metrics-list { list-style: none; padding-left: 0; }
        .metrics-list li {
            color: #cbd5e1; font-size: 0.9rem; padding: 0.4rem 0; padding-left: 1.5rem; position: relative;
        }
        /* Changed icon to generic bullet for general management */
        .metrics-list li:before { content: "•"; color: #22c55e; position: absolute; left: 0; font-size: 1.2rem; line-height: 1rem; }

        .card-action {
            display: flex; align-items: center; justify-content: center;
            gap: 0.5rem; padding: 1rem;
            background: rgba(34, 197, 94, 0.1);
            border-radius: 8px;
            color: #22c55e; font-size: 0.95rem; font-weight: 600;
            transition: all 0.3s ease;
            border: 1px solid rgba(34, 197, 94, 0.2);
            margin-top: auto;
        }
        .category-card:hover .card-action {
            background: rgba(34, 197, 94, 0.2);
            transform: translateX(5px);
        }
    </style>
</head>
<body>
    <div class="container">
        <header class="page-header">
            <div style="font-size: 4rem; margin-bottom: 1rem;">🌱</div>
            <h1 class="page-title">Farm Administration</h1>
            <p class="page-subtitle">Centralized Control & Classifications</p>
            <p class="page-description">Manage animal stages, reproductive cycles, maintenance protocols, and transfer costs.</p>
        </header>

        <div class="categories-grid">

            <a href="animal_classification.php" class="category-card">
                <div class="category-header">
                    <div class="category-icon blue">🐷</div>
                    <div class="category-info">
                        <h3 class="category-title">Animal Class</h3>
                        <p class="category-subtitle">Classification Rules</p>
                    </div>
                </div>
                <div class="analytics-preview">
                    <div class="analytics-preview-title">Manage Stages</div>
                    <ul class="metrics-list">
                        <li>Piglet & Started Hog Days</li>
                        <li>Grower & Finisher Ranges</li>
                        <li>Boar & Gilt Transitions</li>
                        <li>Auto-Classification Logic</li>
                    </ul>
                </div>
                <div class="card-action">
                    <span>Manage Classes</span>
                    <span>→</span>
                </div>
            </a>

            <a href="edit_animal_bio.php" class="category-card">
                <div class="category-header">
                    <div class="category-icon yellow">🧬</div>
                    <div class="category-info">
                        <h3 class="category-title">Edit Bio Info</h3>
                        <p class="category-subtitle">Core Data Correction</p>
                    </div>
                </div>
                <div class="analytics-preview">
                    <div class="analytics-preview-title">Update Records</div>
                    <ul class="metrics-list">
                        <li>Correct Tag Numbers</li>
                        <li>Update Birth Dates</li>
                        <li>Modify Sex & Breed</li>
                        <li>Fix Initial Weights</li>
                    </ul>
                </div>
                <div class="card-action">
                    <span>Edit Records</span>
                    <span>→</span>
                </div>
            </a>

            <a href="animal_sow_status.php" class="category-card">
                <div class="category-header">
                    <div class="category-icon pink">🐖</div>
                    <div class="category-info">
                        <h3 class="category-title">Sow Status</h3>
                        <p class="category-subtitle">Reproductive Cycle</p>
                    </div>
                </div>
                <div class="analytics-preview">
                    <div class="analytics-preview-title">Cycle Tracking</div>
                    <ul class="metrics-list">
                        <li>Open & Bred Status</li>
                        <li>Gestating Timeline</li>
                        <li>Lactating & Weaned</li>
                        <li>Status Color Coding</li>
                    </ul>
                </div>
                <div class="card-action">
                    <span>Manage Status</span>
                    <span>→</span>
                </div>
            </a>

            <a href="animal_fcr.php" class="category-card">
                <div class="category-header">
                    <div class="category-icon green">📈</div>
                    <div class="category-info">
                        <h3 class="category-title">FCR Management</h3>
                        <p class="category-subtitle">Feed Efficiency</p>
                    </div>
                </div>
                <div class="analytics-preview">
                    <div class="analytics-preview-title">Performance Metrics</div>
                    <ul class="metrics-list">
                        <li>Set Target FCR per Stage</li>
                        <li>Input vs. Output Weight</li>
                        <li>Growth Rate Analysis</li>
                        <li>Efficiency Benchmarks</li>
                    </ul>
                </div>
                <div class="card-action">
                    <span>Manage FCR</span>
                    <span>→</span>
                </div>
            </a>

            <a href="animal_weights.php" class="category-card">
                <div class="category-header">
                    <div class="category-icon orange">⚖️</div>
                    <div class="category-info">
                        <h3 class="category-title">Animal Weights</h3>
                        <p class="category-subtitle">Growth Tracking</p>
                    </div>
                </div>
                <div class="analytics-preview">
                    <div class="analytics-preview-title">Weight Management</div>
                    <ul class="metrics-list">
                        <li>Bulk Weight Entry</li>
                        <li>Update Actual Weights</li>
                        <li>Monitor Growth Progress</li>
                        <li>Historical Logs</li>
                    </ul>
                </div>
                <div class="card-action">
                    <span>Update Weights</span>
                    <span>→</span>
                </div>
            </a>

            <a href="animal_operations.php" class="category-card">
                <div class="category-header">
                    <div class="category-icon red">⚙️</div>
                    <div class="category-info">
                        <h3 class="category-title">Animal Operations</h3>
                        <p class="category-subtitle">Daily Activities</p>
                    </div>
                </div>
                <div class="analytics-preview">
                    <div class="analytics-preview-title">Farm Tasks</div>
                    <ul class="metrics-list">
                        <li>Schedule Treatments</li>
                        <li>Log Maintenance</li>
                        <li>Vaccination Schedules</li>
                        <li>Operational History</li>
                    </ul>
                </div>
                <div class="card-action">
                    <span>Manage Operations</span>
                    <span>→</span>
                </div>
            </a>

            <a href="animal_sow_cards.php" class="category-card">
                <div class="category-header">
                    <div class="category-icon purple">📋</div>
                    <div class="category-info">
                        <h3 class="category-title">Sow Cards</h3>
                        <p class="category-subtitle">Individual Records</p>
                    </div>
                </div>
                <div class="analytics-preview">
                    <div class="analytics-preview-title">Digital Records</div>
                    <ul class="metrics-list">
                        <li>Litter History & Count</li>
                        <li>Vaccination Logs</li>
                        <li>Breeding Dates</li>
                        <li>Performance History</li>
                    </ul>
                </div>
                <div class="card-action">
                    <span>View Sow Cards</span>
                    <span>→</span>
                </div>
            </a>

            <a href="animal_cost_transfers.php" class="category-card">
                <div class="category-header">
                    <div class="category-icon teal">💸</div>
                    <div class="category-info">
                        <h3 class="category-title">Cost Transfer</h3>
                        <p class="category-subtitle">Value Movement</p>
                    </div>
                </div>
                <div class="analytics-preview">
                    <div class="analytics-preview-title">Accounting</div>
                    <ul class="metrics-list">
                        <li>Transfer Nursery to Fattening</li>
                        <li>Accumulated Feed Costs</li>
                        <li>Medication Cost Allocation</li>
                        <li>Batch Profitability</li>
                    </ul>
                </div>
                <div class="card-action">
                    <span>Transfer Costs</span>
                    <span>→</span>
                </div>
            </a>

        </div>
    </div>
</body>
</html>