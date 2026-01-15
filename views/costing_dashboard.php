<?php
$page = "costing";
include '../common/navbar.php';
include '../security/checkRole.php';    
checkRole(3);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Costing Dashboard - FarmPro</title>
    <style>
        /* --- COPY OF YOUR EXISTING CSS --- */
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
            border: 1px solid rgba(34, 197, 94, 0.2);
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
        /* Specific Colors per Category */
        .category-icon.acquisition { background: linear-gradient(135deg, #3b82f6, #1d4ed8); } /* Blue */
        .category-icon.feed { background: linear-gradient(135deg, #f59e0b, #d97706); } /* Orange */
        .category-icon.medicine { background: linear-gradient(135deg, #ec4899, #db2777); } /* Pink */
        .category-icon.vaccines { background: linear-gradient(135deg, #ef4444, #b91c1c); } /* Red */
        .category-icon.vitamins { background: linear-gradient(135deg, #10b981, #059669); } /* Green */
        .category-icon.checkup { background: linear-gradient(135deg, #8b5cf6, #7c3aed); } /* Purple */

        .category-info { flex: 1; }
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
        .metrics-list li:before { content: "💰"; position: absolute; left: 0; font-size: 0.8rem; }

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
            <div style="font-size: 4rem; margin-bottom: 1rem;">💸</div>
            <h1 class="page-title">Costing & Expenses</h1>
            <p class="page-subtitle">Track Farm Investment Categories</p>
            <p class="page-description">Select a category to manage prices, calculate totals, or view expense records.</p>
        </header>

        <div class="categories-grid">

            <a href="animal_cost.php" class="category-card">
                <div class="category-header">
                    <div class="category-icon acquisition">🐖</div>
                    <div class="category-info">
                        <h3 class="category-title">Animal Cost</h3>
                        <p class="category-subtitle">Initial Investment + All Costs</p>
                    </div>
                </div>
                <div class="analytics-preview">
                    <div class="analytics-preview-title">Category Options</div>
                    <ul class="metrics-list">
                        <li>Purchased Livestock Cost</li>
                        <li>Home Grown / Birthing Records</li>
                        <li>Transport & Delivery Fees</li>
                        <li>Initial Weight Valuation</li>
                    </ul>
                </div>
                <div class="card-action">
                    <span>Manage Acquisition</span>
                    <span>→</span>
                </div>
            </a>

            <a href="costing_feeds.php" class="category-card">
                <div class="category-header">
                    <div class="category-icon feed">🍽️</div>
                    <div class="category-info">
                        <h3 class="category-title">Feed Consumption</h3>
                        <p class="category-subtitle">Daily Nutrition Expenses</p>
                    </div>
                </div>
                <div class="analytics-preview">
                    <div class="analytics-preview-title">Category Options</div>
                    <ul class="metrics-list">
                        <li>Pre-Starter / Booster Cost</li>
                        <li>Starter & Grower Feeds</li>
                        <li>Finisher Ration Cost</li>
                        <li>Sack vs. Kilogram Calculation</li>
                    </ul>
                </div>
                <div class="card-action">
                    <span>Manage Feeding Costs</span>
                    <span>→</span>
                </div>
            </a>

            <a href="costing_medication.php" class="category-card">
                <div class="category-header">
                    <div class="category-icon medicine">💊</div>
                    <div class="category-info">
                        <h3 class="category-title">Medication & Treatments</h3>
                        <p class="category-subtitle">Curative Care Expenses</p>
                    </div>
                </div>
                <div class="analytics-preview">
                    <div class="analytics-preview-title">Category Options</div>
                    <ul class="metrics-list">
                        <li>Antibiotics & Injectables</li>
                        <li>Deworming Costs</li>
                        <li>Wound Sprays & Topicals</li>
                        <li>Treatment Supplies</li>
                    </ul>
                </div>
                <div class="card-action">
                    <span>Manage Medications</span>
                    <span>→</span>
                </div>
            </a>

            <a href="costing_vaccines.php" class="category-card">
                <div class="category-header">
                    <div class="category-icon vaccines">💉</div>
                    <div class="category-info">
                        <h3 class="category-title">Vaccinations</h3>
                        <p class="category-subtitle">Preventive Immunization</p>
                    </div>
                </div>
                <div class="analytics-preview">
                    <div class="analytics-preview-title">Category Options</div>
                    <ul class="metrics-list">
                        <li>Hog Cholera Vaccine</li>
                        <li>Mycoplasma & FMD</li>
                        <li>Parvo / Lepto Shots</li>
                        <li>Syringe & Needle Costs</li>
                    </ul>
                </div>
                <div class="card-action">
                    <span>Manage Vaccines</span>
                    <span>→</span>
                </div>
            </a>

            <a href="costing_vitamins_supplies.php" class="category-card">
                <div class="category-header">
                    <div class="category-icon vitamins">⚡</div>
                    <div class="category-info">
                        <h3 class="category-title">Vitamins & Supplements</h3>
                        <p class="category-subtitle">Growth Boosters</p>
                    </div>
                </div>
                <div class="analytics-preview">
                    <div class="analytics-preview-title">Category Options</div>
                    <ul class="metrics-list">
                        <li>Multivitamins (Injectable/Oral)</li>
                        <li>Iron Supplementation</li>
                        <li>Electrolytes & Probiotics</li>
                        <li>Growth Enhancers</li>
                    </ul>
                </div>
                <div class="card-action">
                    <span>Manage Supplements</span>
                    <span>→</span>
                </div>
            </a>

            <a href="costing_checkups.php" class="category-card">
                <div class="category-header">
                    <div class="category-icon checkup">🩺</div>
                    <div class="category-info">
                        <h3 class="category-title">Veterinary Check-ups</h3>
                        <p class="category-subtitle">Professional Services</p>
                    </div>
                </div>
                <div class="analytics-preview">
                    <div class="analytics-preview-title">Category Options</div>
                    <ul class="metrics-list">
                        <li>Professional Fees</li>
                        <li>Consultation Costs</li>
                        <li>Service Charges</li>
                        <li>Routine Visit Expenses</li>
                    </ul>
                </div>
                <div class="card-action">
                    <span>Manage Check-ups</span>
                    <span>→</span>
                </div>
            </a>

        </div>
    </div>
</body>
</html>