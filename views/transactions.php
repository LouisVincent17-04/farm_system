<?php
// views/transactions.php
$page = "transactions"; 
include '../config/Connection.php';

include '../security/checkAccess.php';
checkAccess('transactions');
include '../common/navbar.php';

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
        .admin-container { max-width: 1400px; margin: 0 auto; padding-bottom: 4rem; }
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
        .card-icon.feeding { background: linear-gradient(135deg, #f59e0b, #d97706); }
        .card-icon.medication { background: linear-gradient(135deg, #84cc16, #65a30d); }
        .card-icon.vitamins { background: linear-gradient(135deg, #f472b6, #db2777); } 
        .card-icon.checkup { background: linear-gradient(135deg, #06b6d4, #0891b2); }
        .card-icon.vaccination { background: linear-gradient(135deg, #8b5cf6, #7c3aed); }
        .card-icon.withdrawal { background: linear-gradient(135deg, #ef4444, #dc2626); }
        .card-icon.purchases { background: linear-gradient(135deg, #3b82f6, #1d4ed8); }
        .card-icon.transfer { background: linear-gradient(135deg, #14b8a6, #0d9488); }

        /* SALES COLORS (Emerald Green for Profit) */
        .card-icon.sales { background: linear-gradient(135deg, #10b981, #059669); }
        .card-icon.group-sales { background: linear-gradient(135deg, #059669, #064e3b); }

        /* MORTALITY COLORS (Slate/Dark Gray) */
        .card-icon.mortality { background: linear-gradient(135deg, #64748b, #334155); }
        .card-icon.group-mortality { background: linear-gradient(135deg, #475569, #1e293b); }

        /* REVERSAL COLORS (Deep Red/Danger) */
        .card-icon.revert { background: linear-gradient(135deg, #b91c1c, #7f1d1d); border: 1px solid #f87171; }

        /* GROUP COLORS */
        .card-icon.group-med { background: linear-gradient(135deg, #65a30d, #3f6212); }
        .card-icon.group-vit { background: linear-gradient(135deg, #be185d, #831843); }
        .card-icon.group-chk { background: linear-gradient(135deg, #0891b2, #155e75); }
        .card-icon.group-vac { background: linear-gradient(135deg, #7c3aed, #5b21b6); }
        .card-icon.group-feed { background: linear-gradient(135deg, #ea580c, #c2410c); }

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

        /* Separator */
        .section-separator {
            border-top: 1px solid rgba(34, 197, 94, 0.3);
            margin: 3rem 0 1.5rem 0;
            padding-top: 1rem;
        }

        /* ADMIN ZONE STYLES */
        .admin-zone { border: 2px dashed #b91c1c; border-radius: 20px; padding: 2rem; background: rgba(127, 29, 29, 0.1); margin-top: 4rem; position: relative; }
        .admin-badge { position: absolute; top: -15px; left: 50%; transform: translateX(-50%); background: #b91c1c; color: white; padding: 5px 20px; border-radius: 20px; font-weight: bold; font-size: 0.9rem; box-shadow: 0 4px 10px rgba(0,0,0,0.5); }
        
        .management-card.reversal-card { border-color: #7f1d1d; }
        .management-card.reversal-card .card-title { color: #f87171; }
        .management-card.reversal-card:hover { border-color: #ef4444; box-shadow: 0 20px 40px rgba(239, 68, 68, 0.15); }
        .management-card.reversal-card .card-action { color: #f87171; }

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

        <h2 class="stats-title" style="text-align: left; padding-left: 1rem; border-left: 4px solid #22c55e;">Individual Operations</h2>
        <br>
        <div class="management-grid">
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

            <a href="medication.php" class="management-card">
                <div class="card-icon medication"><span class="main-emoji">💊</span></div>
                <h3 class="card-title">Medication</h3>
                <p class="card-description">Track medical treatments, dosages, and medication administration for individual livestock health management.</p>
                <div class="transaction-fields"><div class="field-list"><div class="field-title">Transaction Fields:</div>Trans. Type • Trans. Date • Tag No. • Remarks • Medicine Item • Fees</div></div>
                <div class="card-stats">
                    <div class="stat-item"><div class="stat-number">23</div><div class="stat-label">Active</div></div>
                    <div class="stat-item"><div class="stat-number">340</div><div class="stat-label">Stock Items</div></div>
                    <div class="card-action">Administer →</div>
                </div>
            </a>

            <a href="vitamins_supplements_transaction.php" class="management-card">
                <div class="card-icon vitamins"><span class="main-emoji">🧴</span></div>
                <h3 class="card-title">Vitamins & Supplements</h3>
                <p class="card-description">Administer daily vitamins, mineral supplements, and growth boosters to specific animals.</p>
                <div class="transaction-fields"><div class="field-list"><div class="field-title">Transaction Fields:</div>Trans. Type • Trans. Date • Tag No. • Supplement Name • Dosage • Quantity • Remarks</div></div>
                <div class="card-stats">
                    <div class="stat-item"><div class="stat-number">89</div><div class="stat-label">Administered</div></div>
                    <div class="stat-item"><div class="stat-number">12</div><div class="stat-label">Types</div></div>
                    <div class="card-action">Give Supplements →</div>
                </div>
            </a>

            <a href="checkup.php" class="management-card">
                <div class="card-icon checkup"><span class="main-emoji">🩺</span></div>
                <h3 class="card-title">Check-Ups</h3>
                <p class="card-description">Schedule and document veterinary examinations and health assessments for individual animals.</p>
                <div class="transaction-fields"><div class="field-list"><div class="field-title">Transaction Fields:</div>Trans. Type • Trans. Date • Tag No. • Location • Building • Pen • Fees (optional) • Remarks</div></div>
                <div class="card-stats">
                    <div class="stat-item"><div class="stat-number">34</div><div class="stat-label">This Month</div></div>
                    <div class="stat-item"><div class="stat-number">12</div><div class="stat-label">Scheduled</div></div>
                    <div class="card-action">Schedule →</div>
                </div>
            </a>

            <a href="vaccination.php" class="management-card">
                <div class="card-icon vaccination"><span class="main-emoji">💉</span></div>
                <h3 class="card-title">Vaccination</h3>
                <p class="card-description">Manage vaccination programs and preventive healthcare protocols for individual animals.</p>
                <div class="transaction-fields"><div class="field-list"><div class="field-title">Transaction Fields:</div>Trans. Type • Trans. Date • Tag No. • Location • Pen • Remarks • Vaccine</div></div>
                <div class="card-stats">
                    <div class="stat-item"><div class="stat-number">156</div><div class="stat-label">Completed</div></div>
                    <div class="stat-item"><div class="stat-number">98%</div><div class="stat-label">Coverage</div></div>
                    <div class="card-action">Vaccinate →</div>
                </div>
            </a>

            <a href="purchase_dashboard.php" class="management-card">
                <div class="card-icon purchases"><span class="main-emoji">🛒</span></div>
                <h3 class="card-title">Purchases</h3>
                <p class="card-description">Record procurement transactions, supplier information, and cost tracking for farm supplies and equipment.</p>
                <div class="transaction-fields"><div class="field-list"><div class="field-title">Transaction Fields:</div>Trans. Type • Trans. Date • Item Name • Description • Qty • Unit • Unit Cost • Total Cost</div></div>
                <div class="card-stats">
                    <div class="stat-item"><div class="stat-number">₱24k</div><div class="stat-label">This Week</div></div>
                    <div class="stat-item"><div class="stat-number">89</div><div class="stat-label">Transactions</div></div>
                    <div class="card-action">Purchase →</div>
                </div>
            </a>

            <a href="animal_sales_process.php" class="management-card">
                <div class="card-icon sales"><span class="main-emoji">💰</span></div>
                <h3 class="card-title">Sell Animals</h3>
                <p class="card-description">Process individual livestock sales, generate invoices, record buyer details, and track revenue per animal.</p>
                <div class="transaction-fields"><div class="field-list"><div class="field-title">Transaction Fields:</div>Trans. Date • Tag No. • Live Weight • Price/kg • Total Price • Customer Name • Status</div></div>
                <div class="card-stats">
                    <div class="stat-item"><div class="stat-number">₱120k</div><div class="stat-label">Revenue</div></div>
                    <div class="stat-item"><div class="stat-number">14</div><div class="stat-label">Sold</div></div>
                    <div class="card-action">Create Sale →</div>
                </div>
            </a>

            <a href="animal_mortality.php" class="management-card">
                <div class="card-icon mortality"><span class="main-emoji">💀</span></div>
                <h3 class="card-title">Mortality Management</h3>
                <p class="card-description">Record individual animal deaths, specific causes, and log any recovered costs (e.g. carcass sales).</p>
                <div class="transaction-fields"><div class="field-list"><div class="field-title">Transaction Fields:</div>Trans. Date • Tag No. • Cause • Recovered Cost • Remarks</div></div>
                <div class="card-stats">
                    <div class="stat-item"><div class="stat-number">2</div><div class="stat-label">This Month</div></div>
                    <div class="stat-item"><div class="stat-number">₱1.2k</div><div class="stat-label">Recovered</div></div>
                    <div class="card-action">Record Death →</div>
                </div>
            </a>
        </div>

        <div class="section-separator"></div>
        <h2 class="stats-title" style="text-align: left; padding-left: 1rem; border-left: 4px solid #f59e0b;">Batch & Group Operations</h2>
        <br>
        
        <div class="management-grid">
            <a href="group_feed_management.php" class="management-card">
                <div class="card-icon group-feed"><span class="main-emoji">🍽️</span><span class="group-badge">👥</span></div>
                <h3 class="card-title">Group Feeding</h3>
                <p class="card-description">Bulk feed recording for entire pens or buildings. Ideal for nursery, growers, and finishers.</p>
                <div class="transaction-fields"><div class="field-list"><div class="field-title">Transaction Fields:</div>Trans. Date • <strong>Select Pen</strong> • Feed Name • Total Bags/Kg • Feed Per Head</div></div>
                <div class="card-stats">
                    <div class="stat-item"><div class="stat-number">112</div><div class="stat-label">Today</div></div>
                    <div class="stat-item"><div class="stat-number">1,200kg</div><div class="stat-label">Consumed</div></div>
                    <div class="card-action">Batch Feed →</div>
                </div>
            </a>

            <a href="group_medication.php" class="management-card">
                <div class="card-icon group-med"><span class="main-emoji">💊</span><span class="group-badge">👥</span></div>
                <h3 class="card-title">Group Medication</h3>
                <p class="card-description">Apply medical treatments to multiple animals simultaneously by Pen or Building.</p>
                <div class="transaction-fields"><div class="field-list"><div class="field-title">Transaction Fields:</div>Trans. Date • <strong>Select Pen/Building</strong> • Medicine Item • Dosage • Total Quantity</div></div>
                <div class="card-stats">
                    <div class="stat-item"><div class="stat-number">5</div><div class="stat-label">Pens</div></div>
                    <div class="stat-item"><div class="stat-number">45</div><div class="stat-label">Animals</div></div>
                    <div class="card-action">Batch Treat →</div>
                </div>
            </a>

            <a href="group_vitamins.php" class="management-card">
                <div class="card-icon group-vit"><span class="main-emoji">🧴</span><span class="group-badge">👥</span></div>
                <h3 class="card-title">Group Vitamins</h3>
                <p class="card-description">Distribute supplements to a whole group via water or feed mixing for an entire pen.</p>
                <div class="transaction-fields"><div class="field-list"><div class="field-title">Transaction Fields:</div>Trans. Date • <strong>Select Pen</strong> • Supplement • Mix Ratio • Remarks</div></div>
                <div class="card-stats">
                    <div class="stat-item"><div class="stat-number">12</div><div class="stat-label">Batches</div></div>
                    <div class="stat-item"><div class="stat-number">All</div><div class="stat-label">Coverage</div></div>
                    <div class="card-action">Batch Supplement →</div>
                </div>
            </a>

            <a href="group_checkup.php" class="management-card">
                <div class="card-icon group-chk"><span class="main-emoji">🩺</span><span class="group-badge">👥</span></div>
                <h3 class="card-title">Group Check-Up</h3>
                <p class="card-description">Perform routine inspections on a pen-by-pen basis. Log general health status for the group.</p>
                <div class="transaction-fields"><div class="field-list"><div class="field-title">Transaction Fields:</div>Trans. Date • <strong>Select Pen</strong> • General Condition • Remarks • Flagged Issues</div></div>
                <div class="card-stats">
                    <div class="stat-item"><div class="stat-number">8</div><div class="stat-label">Pens Checked</div></div>
                    <div class="stat-item"><div class="stat-number">Good</div><div class="stat-label">Avg Status</div></div>
                    <div class="card-action">Batch Inspect →</div>
                </div>
            </a>

            <a href="group_vaccination.php" class="management-card">
                <div class="card-icon group-vac"><span class="main-emoji">💉</span><span class="group-badge">👥</span></div>
                <h3 class="card-title">Group Vaccination</h3>
                <p class="card-description">Execute mass immunization programs for specific pens or entire buildings rapidly.</p>
                <div class="transaction-fields"><div class="field-list"><div class="field-title">Transaction Fields:</div>Trans. Date • <strong>Select Building/Pen</strong> • Vaccine Name • Batch Number • Total Doses</div></div>
                <div class="card-stats">
                    <div class="stat-item"><div class="stat-number">2</div><div class="stat-label">Upcoming</div></div>
                    <div class="stat-item"><div class="stat-number">200</div><div class="stat-label">Doses</div></div>
                    <div class="card-action">Mass Vaccinate →</div>
                </div>
            </a>

            <a href="group_animal_sales.php" class="management-card">
                <div class="card-icon group-sales"><span class="main-emoji">💰</span><span class="group-badge">👥</span></div>
                <h3 class="card-title">Group Sell Animals</h3>
                <p class="card-description">Process bulk sales for entire pens or batches. Ideal for wholesale transactions, culling, or harvest.</p>
                <div class="transaction-fields"><div class="field-list"><div class="field-title">Transaction Fields:</div>Trans. Date • <strong>Select Pen</strong> • Total Heads • Total Weight • Lump Sum/Price per Head • Buyer</div></div>
                <div class="card-stats">
                    <div class="stat-item"><div class="stat-number">3</div><div class="stat-label">Batches</div></div>
                    <div class="stat-item"><div class="stat-number">45</div><div class="stat-label">Heads</div></div>
                    <div class="card-action">Bulk Sale →</div>
                </div>
            </a>

            <a href="group_mortality.php" class="management-card">
                <div class="card-icon group-mortality"><span class="main-emoji">💀</span><span class="group-badge">👥</span></div>
                <h3 class="card-title">Group Mortality</h3>
                <p class="card-description">Log mass mortality events for specific pens. Track causes and total losses for the batch.</p>
                <div class="transaction-fields"><div class="field-list"><div class="field-title">Transaction Fields:</div>Trans. Date • <strong>Select Pen</strong> • Total Heads • Cause • Remarks</div></div>
                <div class="card-stats">
                    <div class="stat-item"><div class="stat-number">0</div><div class="stat-label">Events</div></div>
                    <div class="stat-item"><div class="stat-number">0</div><div class="stat-label">Heads</div></div>
                    <div class="card-action">Batch Log →</div>
                </div>
            </a>
        </div>

        <?php if ($isSuperAdmin): ?>
        <div class="admin-zone">
            <div class="admin-badge">⚠️ SUPER ADMIN ZONE: REVERSALS</div>
            
            <div class="management-grid">
                <a href="history_feeding.php" class="management-card reversal-card">
                    <div class="card-icon revert"><span class="main-emoji">↩️</span></div>
                    <h3 class="card-title">Undo Feeding</h3>
                    <p class="card-description">Review logs and reverse feeding transactions. Restores inventory.</p>
                    <div class="card-action">View Logs →</div>
                </a>
                <a href="history_medication.php" class="management-card reversal-card">
                    <div class="card-icon revert"><span class="main-emoji">↩️</span></div>
                    <h3 class="card-title">Undo Medication</h3>
                    <p class="card-description">Reverse administered medicines. Restores inventory stock.</p>
                    <div class="card-action">View Logs →</div>
                </a>
                <a href="history_vitamins.php" class="management-card reversal-card">
                    <div class="card-icon revert"><span class="main-emoji">↩️</span></div>
                    <h3 class="card-title">Undo Vitamins</h3>
                    <p class="card-description">Reverse vitamin/supplement usage logs.</p>
                    <div class="card-action">View Logs →</div>
                </a>
                <a href="history_checkup.php" class="management-card reversal-card">
                    <div class="card-icon revert"><span class="main-emoji">↩️</span></div>
                    <h3 class="card-title">Undo Checkup</h3>
                    <p class="card-description">Delete incorrect veterinary checkup records.</p>
                    <div class="card-action">View Logs →</div>
                </a>
                <a href="history_vaccination.php" class="management-card reversal-card">
                    <div class="card-icon revert"><span class="main-emoji">↩️</span></div>
                    <h3 class="card-title">Undo Vaccination</h3>
                    <p class="card-description">Reverse vaccination records and inventory deductions.</p>
                    <div class="card-action">View Logs →</div>
                </a>
                <a href="history_purchases.php" class="management-card reversal-card">
                    <div class="card-icon revert"><span class="main-emoji">↩️</span></div>
                    <h3 class="card-title">Undo Purchases</h3>
                    <p class="card-description">Reverse supply purchases. Adjusts current inventory levels.</p>
                    <div class="card-action">View Logs →</div>
                </a>
                <a href="history_sales.php" class="management-card reversal-card">
                    <div class="card-icon revert"><span class="main-emoji">↩️</span></div>
                    <h3 class="card-title">Reverse Sales</h3>
                    <p class="card-description">Cancel sales invoices. Marks animals back to 'Active'.</p>
                    <div class="card-action">View Logs →</div>
                </a>
                <a href="history_mortality.php" class="management-card reversal-card">
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