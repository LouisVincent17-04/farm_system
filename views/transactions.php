<?php
// views/transactions.php
$page = "transactions"; 
include '../config/Connection.php';

include '../security/checkAccess.php';
checkAccess('transactions');
include '../common/navbar.php';
include '../common/chat_support.php';

// Check if user is Super Admin
$isSuperAdmin = (isset($_SESSION['user']['USER_TYPE']) && $_SESSION['user']['USER_TYPE'] == 4);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FarmPro Transaction Management</title>
    <style>
        /* --- GLOBAL & EXISTING STYLES --- */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: #e2e8f0;
            min-height: 100vh;
        }
        .admin-container { max-width: 1400px; margin: 0 auto; padding-bottom: 4rem; padding-top: 2rem;}
        .admin-header { text-align: center; margin-bottom: 3rem; }
        .admin-title {
            font-size: 3rem; font-weight: bold; margin-bottom: 1rem;
            background: linear-gradient(135deg, #22c55e, #16a34a);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
        }
        .admin-subtitle { color: #94a3b8; font-size: 1.2rem; margin-bottom: 0.5rem; }
        .admin-description { color: #64748b; font-size: 1rem; }
        
        /* Quick Stats */
        .quick-stats {
            background: rgba(30, 41, 59, 0.6); border: 1px solid rgba(34, 197, 94, 0.2);
            border-radius: 16px; padding: 2rem; backdrop-filter: blur(10px); margin-bottom: 2rem;
        }
        .stats-title { font-size: 1.5rem; font-weight: 600; color: #22c55e; margin-bottom: 1.5rem; text-align: center; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem; }
        .stat-card { text-align: center; padding: 1rem; background: rgba(15, 23, 42, 0.5); border-radius: 12px; transition: all 0.3s ease; }
        .stat-card:hover { transform: translateY(-2px); background: rgba(15, 23, 42, 0.7); }
        .stat-value { font-size: 2rem; font-weight: bold; color: #22c55e; margin-bottom: 0.5rem; }
        .stat-desc { color: #94a3b8; font-size: 0.9rem; }

        /* Management Grid */
        .management-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
        .management-card {
            background: rgba(30, 41, 59, 0.6); border: 1px solid rgba(34, 197, 94, 0.2);
            border-radius: 16px; padding: 2rem; backdrop-filter: blur(10px);
            transition: all 0.3s ease; cursor: pointer; position: relative;
            overflow: hidden; min-height: 280px; display: flex; flex-direction: column;
            text-decoration: none; color: inherit;
        }
        .management-card:hover {
            transform: translateY(-8px) scale(1.02);
            border-color: rgba(34, 197, 94, 0.4);
            box-shadow: 0 20px 40px rgba(34, 197, 94, 0.15);
        }

        /* --- FIXED ICON STYLES --- */
        .card-icon {
            width: 70px; height: 70px; 
            border-radius: 16px; 
            display: flex;
            align-items: center; 
            justify-content: center; 
            color: white; 
            margin-bottom: 1.5rem; 
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.3);
            position: relative; 
        }

        .main-emoji { font-size: 2.5rem; line-height: 1; z-index: 1; }
        .group-badge {
            position: absolute; bottom: 4px; right: 4px; font-size: 1.1rem;
            filter: drop-shadow(0 2px 2px rgba(0,0,0,0.5)); z-index: 2;
        }

        /* Icon Colors */
        .card-icon.purchases { background: linear-gradient(135deg, #3b82f6, #1d4ed8); }
        .card-icon.feeding { background: linear-gradient(135deg, #f59e0b, #d97706); }
        .card-icon.group-feed { background: linear-gradient(135deg, #ea580c, #c2410c); }
        .card-icon.group-med { background: linear-gradient(135deg, #65a30d, #3f6212); }
        .card-icon.group-vit { background: linear-gradient(135deg, #be185d, #831843); }
        .card-icon.group-chk { background: linear-gradient(135deg, #0891b2, #155e75); }
        .card-icon.group-vac { background: linear-gradient(135deg, #7c3aed, #5b21b6); }
        .card-icon.group-sales { background: linear-gradient(135deg, #059669, #064e3b); }
        .card-icon.group-mortality { background: linear-gradient(135deg, #475569, #1e293b); }

        /* REVERSAL COLORS (Standard Warning Amber) */
        .card-icon.revert { background: linear-gradient(135deg, #d97706, #b45309); border: 1px solid #f59e0b; }

        /* Card Content */
        .card-title { font-size: 1.5rem; font-weight: 600; color: #22c55e; margin-bottom: 1rem; }
        .card-description { color: #94a3b8; font-size: 0.95rem; line-height: 1.5; margin-bottom: 1rem; flex-grow: 1; }
        .transaction-fields { background: rgba(15, 23, 42, 0.5); border-radius: 8px; padding: 1rem; margin: 1rem 0; }
        .field-list { color: #64748b; font-size: 0.85rem; line-height: 1.4; }
        .field-list .field-title { color: #22c55e; font-weight: 600; margin-bottom: 0.5rem; }
        .card-stats { display: flex; justify-content: space-between; align-items: center; margin-top: auto; padding-top: 1rem; border-top: 1px solid rgba(30, 41, 59, 0.8); }
        .stat-item { text-align: center; }
        .stat-number { font-size: 1.2rem; font-weight: bold; color: #22c55e; }
        .stat-label { font-size: 0.8rem; color: #64748b; margin-top: 0.25rem; }
        .card-action { display: flex; align-items: center; gap: 0.5rem; color: #64748b; font-size: 0.9rem; transition: color 0.3s ease; }
        .management-card:hover .card-action { color: #22c55e; }

        /* ADMIN ZONE STYLES */
        .admin-zone { 
            border: 1px solid #f59e0b; 
            border-radius: 20px; 
            padding: 2rem; 
            background: rgba(245, 158, 11, 0.05); 
            margin-top: 4rem; 
            position: relative; 
        }
        .admin-badge { 
            position: absolute; top: -15px; left: 50%; transform: translateX(-50%); 
            background: #f59e0b; color: #0f172a; 
            padding: 5px 20px; border-radius: 20px; 
            font-weight: 800; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 1px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.5); 
        }
        
        .management-card.reversal-card { border-color: rgba(245, 158, 11, 0.3); }
        .management-card.reversal-card .card-title { color: #fbbf24; }
        .management-card.reversal-card:hover { 
            border-color: #f59e0b; 
            box-shadow: 0 20px 40px rgba(245, 158, 11, 0.15); 
            transform: translateY(-5px);
        }
        .management-card.reversal-card .card-action { color: #fbbf24; }

        @media (max-width: 768px) {
            body { padding: 1rem; }
            .admin-title { font-size: 2rem; }
            .management-grid { grid-template-columns: 1fr; gap: 1rem; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <header class="admin-header">
            <h1 class="admin-title">Transaction Management Center</h1>
            <p class="admin-subtitle">Comprehensive Farm Transaction System</p>
            <p class="admin-description">Select any transaction module below to manage your farm operations</p>
        </header>

        <div class="quick-stats">
            <h2 class="stats-title">Transaction Overview</h2>
            <div class="stats-grid">
                <div class="stat-card"><div class="stat-value">342</div><div class="stat-desc">Today's Transactions</div></div>
                <div class="stat-card"><div class="stat-value">1,247</div><div class="stat-desc">Active Animals</div></div>
                <div class="stat-card"><div class="stat-value">24</div><div class="stat-desc">Farms</div></div>
                <div class="stat-card"><div class="stat-value">89</div><div class="stat-desc">Buildings</div></div>
                <div class="stat-card"><div class="stat-value">156</div><div class="stat-desc">Pens</div></div>
                <div class="stat-card"><div class="stat-value">₱284,930</div><div class="stat-desc">Monthly Expenses</div></div>
            </div>
        </div>

        <h2 class="stats-title" style="text-align: left; padding-left: 1rem; border-left: 4px solid #f59e0b;">Operations & Records</h2>
        <br>
        
        <div class="management-grid">
            
            <a href="purchase_dashboard.php" class="management-card">
                <div class="card-icon purchases"><span class="main-emoji">🛒</span></div>
                <h3 class="card-title">Purchases</h3>
                <p class="card-description">Record procurement transactions, supplier information, and cost tracking for farm supplies and equipment.</p>
                <div class="transaction-fields"><div class="field-list"><div class="field-title">Transaction Fields:</div>Trans. Type • Trans. Date • Item Name • Qty • Unit Cost • Total Cost</div></div>
                <div class="card-stats">
                    <div class="stat-item"><div class="stat-number">₱24k</div><div class="stat-label">This Week</div></div>
                    <div class="stat-item"><div class="stat-number">89</div><div class="stat-label">Transactions</div></div>
                    <div class="card-action">Purchase →</div>
                </div>
            </a>

            <a href="single_feed_management.php" class="management-card">
                <div class="card-icon feeding"><span class="main-emoji">🍽️</span></div>
                <h3 class="card-title">Individual Feeding</h3>
                <p class="card-description">Record specialized diet or consumption for specific animals (e.g., lactating sows or boars).</p>
                <div class="transaction-fields"><div class="field-list"><div class="field-title">Transaction Fields:</div>Trans. Date • Tag No. • Feed Type • Quantity (kg) • Remarks</div></div>
                <div class="card-stats">
                    <div class="stat-item"><div class="stat-number">15</div><div class="stat-label">Sows</div></div>
                    <div class="stat-item"><div class="stat-number">5</div><div class="stat-label">Boars</div></div>
                    <div class="card-action">Feed One →</div>
                </div>
            </a>

            <a href="group_feed_management.php" class="management-card">
                <div class="card-icon group-feed"><span class="main-emoji">🍽️</span><span class="group-badge">👥</span></div>
                <h3 class="card-title">Group Feeding</h3>
                <p class="card-description">Bulk feed recording for entire pens or buildings. Ideal for nursery, growers, and finishers.</p>
                <div class="transaction-fields"><div class="field-list"><div class="field-title">Transaction Fields:</div>Trans. Date • Select Pen • Feed Name • Total Bags/Kg • Feed Per Head</div></div>
                <div class="card-stats">
                    <div class="stat-item"><div class="stat-number">112</div><div class="stat-label">Today</div></div>
                    <div class="stat-item"><div class="stat-number">1,200kg</div><div class="stat-label">Consumed</div></div>
                    <div class="card-action">Batch Feed →</div>
                </div>
            </a>

            <a href="group_medication.php" class="management-card">
                <div class="card-icon group-med"><span class="main-emoji">💊</span></div>
                <h3 class="card-title">Individual / Batch Medication</h3>
                <p class="card-description">Apply medical treatments to a single animal or multiple animals simultaneously.</p>
                <div class="transaction-fields"><div class="field-list"><div class="field-title">Transaction Fields:</div>Trans. Date • Target (Tag/Pen) • Medicine Item • Dosage • Total Quantity</div></div>
                <div class="card-stats">
                    <div class="stat-item"><div class="stat-number">5</div><div class="stat-label">Pens</div></div>
                    <div class="stat-item"><div class="stat-number">45</div><div class="stat-label">Animals</div></div>
                    <div class="card-action">Administer →</div>
                </div>
            </a>

            <a href="group_vitamins.php" class="management-card">
                <div class="card-icon group-vit"><span class="main-emoji">🧴</span></div>
                <h3 class="card-title">Individual / Batch Vitamins</h3>
                <p class="card-description">Distribute supplements to specific animals or a whole group via water or feed mixing.</p>
                <div class="transaction-fields"><div class="field-list"><div class="field-title">Transaction Fields:</div>Trans. Date • Target (Tag/Pen) • Supplement • Mix Ratio • Remarks</div></div>
                <div class="card-stats">
                    <div class="stat-item"><div class="stat-number">12</div><div class="stat-label">Batches</div></div>
                    <div class="stat-item"><div class="stat-number">All</div><div class="stat-label">Coverage</div></div>
                    <div class="card-action">Give Supplements →</div>
                </div>
            </a>

            <a href="group_checkup.php" class="management-card">
                <div class="card-icon group-chk"><span class="main-emoji">🩺</span></div>
                <h3 class="card-title">Individual / Batch Check-Up</h3>
                <p class="card-description">Perform routine inspections on individual animals or on a pen-by-pen basis.</p>
                <div class="transaction-fields"><div class="field-list"><div class="field-title">Transaction Fields:</div>Trans. Date • Target (Tag/Pen) • General Condition • Remarks • Flagged Issues</div></div>
                <div class="card-stats">
                    <div class="stat-item"><div class="stat-number">8</div><div class="stat-label">Pens Checked</div></div>
                    <div class="stat-item"><div class="stat-number">Good</div><div class="stat-label">Avg Status</div></div>
                    <div class="card-action">Schedule Inspect →</div>
                </div>
            </a>

            <a href="group_vaccination.php" class="management-card">
                <div class="card-icon group-vac"><span class="main-emoji">💉</span></div>
                <h3 class="card-title">Individual / Batch Vaccination</h3>
                <p class="card-description">Execute immunization programs for specific animals, pens, or entire buildings.</p>
                <div class="transaction-fields"><div class="field-list"><div class="field-title">Transaction Fields:</div>Trans. Date • Target (Tag/Pen) • Vaccine Name • Batch Number • Total Doses</div></div>
                <div class="card-stats">
                    <div class="stat-item"><div class="stat-number">2</div><div class="stat-label">Upcoming</div></div>
                    <div class="stat-item"><div class="stat-number">200</div><div class="stat-label">Doses</div></div>
                    <div class="card-action">Vaccinate →</div>
                </div>
            </a>

            <a href="group_animal_sales.php" class="management-card">
                <div class="card-icon group-sales"><span class="main-emoji">💰</span></div>
                <h3 class="card-title">Individual / Batch Sales</h3>
                <p class="card-description">Process sales for a single animal or wholesale batches. Generate invoices and track revenue.</p>
                <div class="transaction-fields"><div class="field-list"><div class="field-title">Transaction Fields:</div>Trans. Date • Target (Tag/Pen) • Total Heads • Total Weight • Price per Head • Buyer</div></div>
                <div class="card-stats">
                    <div class="stat-item"><div class="stat-number">3</div><div class="stat-label">Batches</div></div>
                    <div class="stat-item"><div class="stat-number">45</div><div class="stat-label">Heads</div></div>
                    <div class="card-action">Create Sale →</div>
                </div>
            </a>

            <a href="group_mortality.php" class="management-card">
                <div class="card-icon group-mortality"><span class="main-emoji">💀</span></div>
                <h3 class="card-title">Individual / Batch Mortality</h3>
                <p class="card-description">Log mortality events and track causes for individual animals or mass pen events.</p>
                <div class="transaction-fields"><div class="field-list"><div class="field-title">Transaction Fields:</div>Trans. Date • Target (Tag/Pen) • Total Heads • Cause • Remarks</div></div>
                <div class="card-stats">
                    <div class="stat-item"><div class="stat-number">0</div><div class="stat-label">Events</div></div>
                    <div class="stat-item"><div class="stat-number">0</div><div class="stat-label">Heads</div></div>
                    <div class="card-action">Record Death →</div>
                </div>
            </a>
        </div>

        <?php if ($isSuperAdmin): ?>
        <div class="admin-zone">
            <div class="admin-badge">⚠️ SUPER ADMIN ZONE: REVERSALS</div>
            
            <div class="management-grid">
                <a href="reverse_feeding_transaction.php" class="management-card reversal-card">
                    <div class="card-icon revert"><span class="main-emoji">↩️</span></div>
                    <h3 class="card-title">Undo Feeding</h3>
                    <p class="card-description">Review logs and reverse feeding transactions. Restores inventory.</p>
                    <div class="card-action">View Logs →</div>
                </a>
                <a href="reverse_medication_transaction.php" class="management-card reversal-card">
                    <div class="card-icon revert"><span class="main-emoji">↩️</span></div>
                    <h3 class="card-title">Undo Medication</h3>
                    <p class="card-description">Reverse administered medicines. Restores inventory stock.</p>
                    <div class="card-action">View Logs →</div>
                </a>
                <a href="reverse_vitamin_transaction.php" class="management-card reversal-card">
                    <div class="card-icon revert"><span class="main-emoji">↩️</span></div>
                    <h3 class="card-title">Undo Vitamins</h3>
                    <p class="card-description">Reverse vitamin/supplement usage logs.</p>
                    <div class="card-action">View Logs →</div>
                </a>
                <a href="reverse_checkup_transaction.php" class="management-card reversal-card">
                    <div class="card-icon revert"><span class="main-emoji">↩️</span></div>
                    <h3 class="card-title">Undo Checkup</h3>
                    <p class="card-description">Delete incorrect veterinary checkup records.</p>
                    <div class="card-action">View Logs →</div>
                </a>
                <a href="reverse_vaccination_transaction.php" class="management-card reversal-card">
                    <div class="card-icon revert"><span class="main-emoji">↩️</span></div>
                    <h3 class="card-title">Undo Vaccination</h3>
                    <p class="card-description">Reverse vaccination records and inventory deductions.</p>
                    <div class="card-action">View Logs →</div>
                </a>

                <a href="reverse_sale_transaction.php" class="management-card reversal-card">
                    <div class="card-icon revert"><span class="main-emoji">↩️</span></div>
                    <h3 class="card-title">Reverse Sales</h3>
                    <p class="card-description">Cancel sales invoices. Marks animals back to 'Active'.</p>
                    <div class="card-action">View Logs →</div>
                </a>
                <a href="reverse_mortality_transaction.php" class="management-card reversal-card">
                    <div class="card-icon revert"><span class="main-emoji">↩️</span></div>
                    <h3 class="card-title">Reverse Mortality</h3>
                    <p class="card-description">Revive animals marked as deceased by mistake.</p>
                    <div class="card-action">View Logs →</div>
                </a>
            </div>
        </div>
        <?php endif; ?>

    </div>
</body>
</html>