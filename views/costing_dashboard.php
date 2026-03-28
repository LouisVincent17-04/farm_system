<?php
$page = "costing";
include '../config/Connection.php';

include '../security/checkAccess.php';
checkAccess('costing');
include '../common/navbar.php';
include '../common/chat_support.php';
if($_SESSION['user']['USER_TYPE'] < 3)
{
    echo "<script>alert('Access denied.'); window.location.href = 'admin_dashboard.php';</script>";
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Costing Dashboard | FarmPro</title>
    
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
            --border-active:  rgba(16,185,129,0.5); /* Emerald Accent */
            
            /* Theme Colors */
            --emerald:        #10b981; --emerald-dim: rgba(16,185,129,0.12); --emerald-glow: rgba(16,185,129,0.25);
            --blue:           #3b82f6; --blue-dim: rgba(59,130,246,0.12);
            --amber:          #f59e0b; --amber-dim: rgba(245,158,11,0.12);
            --orange:         #f97316;
            --rose:           #e11d48;
            --cyan:           #06b6d4;
            --purple:         #a855f7;
            --slate:          #64748b;
            --red:            #f87171;
            
            --text-primary:   #f1f5f9;
            --text-secondary: #94a3b8;
            --text-muted:     #475569;
            
            --radius-md:      10px;
            --radius-lg:      14px;
            --radius-xl:      20px;
            --font:           'DM Sans', system-ui, sans-serif;
            --font-mono:      'DM Mono', monospace;
            --transition:     0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* ─── RESET & BASE ─── */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: var(--font);
            background: var(--bg-base);
            color: var(--text-primary);
            min-height: 100vh;
            padding-bottom: 60px;
            background-image: radial-gradient(ellipse 80% 50% at 50% -20%, rgba(16,185,129,0.06) 0%, transparent 60%);
        }
        
        .container { max-width: 1560px; margin: 0 auto; padding: 2rem 1.5rem; }

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
            font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase;
            color: var(--emerald); background: var(--emerald-dim); border: 1px solid rgba(16,185,129,0.2);
            padding: 6px 12px; border-radius: 99px;
        }

        /* ─── HEADER ─── */
        .page-header { text-align: center; margin-bottom: 3.5rem; margin-top: 1rem; }
        .page-title {
            font-size: clamp(2rem, 4vw, 3rem); font-weight: 700;
            color: var(--text-primary); letter-spacing: -0.03em; line-height: 1.1; margin-bottom: 0.75rem;
        }
        .page-title span {
            background: linear-gradient(135deg, var(--emerald), #047857);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }
        .page-subtitle { color: var(--text-secondary); font-size: 1.1rem; margin-bottom: 0.5rem; }
        .page-description { color: var(--text-muted); font-size: 0.95rem; max-width: 600px; margin: 0 auto; }

        /* ─── SEARCH BAR ─── */
        .search-filter-section {
            background: var(--bg-surface); border: 1px solid var(--border);
            border-radius: var(--radius-xl); padding: 1.5rem;
            margin-bottom: 3rem; box-shadow: 0 10px 30px -10px rgba(0,0,0,0.5);
            max-width: 800px; margin-left: auto; margin-right: auto;
        }

        .search-bar { position: relative; display: flex; gap: 1rem; }
        .search-icon {
            position: absolute; left: 1.25rem; top: 50%; transform: translateY(-50%);
            color: var(--text-muted); font-size: 1.1rem; pointer-events: none;
        }
        .search-input {
            flex: 1; padding: 14px 16px 14px 3rem; background: var(--bg-elevated);
            border: 1px solid var(--border); border-radius: var(--radius-md);
            color: var(--text-primary); font-size: 1rem; font-family: var(--font);
            outline: none; transition: all var(--transition);
        }
        .search-input:focus { border-color: var(--emerald); box-shadow: 0 0 0 3px var(--emerald-glow); background: var(--bg-hover); }
        .search-input::placeholder { color: var(--text-muted); }

        /* ─── CATEGORY GRID ─── */
        .categories-grid {
            display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
            gap: 1.5rem; margin-bottom: 2rem;
        }

        .category-card {
            background: var(--bg-surface); border: 1px solid var(--border);
            border-radius: var(--radius-xl); padding: 2rem; position: relative;
            overflow: hidden; display: flex; flex-direction: column;
            text-decoration: none; color: inherit; transition: all var(--transition);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); min-height: 250px;
        }
        .category-card::before {
            content: ''; position: absolute; top: 0; left: -100%; width: 100%; height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.03), transparent);
            transition: left 0.8s ease; pointer-events: none;
        }
        .category-card:hover { transform: translateY(-4px); box-shadow: 0 15px 35px -10px rgba(0,0,0,0.5); }
        .category-card:hover::before { left: 100%; }

        /* Dynamic Card Borders on Hover */
        .category-card.c-animal:hover { border-color: rgba(59,130,246,0.4); }
        .category-card.c-feed:hover { border-color: rgba(245,158,11,0.4); }
        .category-card.c-meds:hover { border-color: rgba(236,72,153,0.4); }
        .category-card.c-vacs:hover { border-color: rgba(239,68,68,0.4); }
        .category-card.c-vits:hover { border-color: rgba(16,185,129,0.4); }
        .category-card.c-checkup:hover { border-color: rgba(168,85,247,0.4); }

        .category-header { display: flex; align-items: center; gap: 1rem; margin-bottom: 1.5rem; }
        
        .category-icon {
            width: 56px; height: 56px; border-radius: var(--radius-lg);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.5rem; color: white; box-shadow: 0 8px 16px rgba(0,0,0,0.3); flex-shrink: 0;
        }
        
        /* Icon Gradients */
        .category-icon.blue { background: linear-gradient(135deg, var(--blue), #1d4ed8); }
        .category-icon.orange { background: linear-gradient(135deg, var(--orange), #c2410c); }
        .category-icon.pink { background: linear-gradient(135deg, #ec4899, #be185d); }
        .category-icon.red { background: linear-gradient(135deg, var(--red), #b91c1c); }
        .category-icon.emerald { background: linear-gradient(135deg, var(--emerald), #047857); }
        .category-icon.purple { background: linear-gradient(135deg, var(--purple), #7e22ce); }

        .category-info { flex: 1; }
        .category-title { font-size: 1.15rem; font-weight: 700; color: #fff; margin-bottom: 0.25rem; }
        .category-subtitle { color: var(--text-secondary); font-size: 0.85rem; }

        .analytics-preview {
            margin-bottom: 1.5rem; padding: 1rem; background: var(--bg-elevated);
            border-radius: var(--radius-md); border: 1px solid var(--border); flex-grow: 1;
        }
        .analytics-preview-title {
            color: var(--text-muted); font-size: 0.75rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.75rem;
        }
        
        .metrics-list { list-style: none; padding-left: 0; }
        .metrics-list li {
            color: var(--text-secondary); font-size: 0.9rem; padding: 4px 0 4px 1.25rem;
            position: relative; line-height: 1.4;
        }
        .metrics-list li::before {
            content: "\f054"; font-family: "Font Awesome 6 Free"; font-weight: 900;
            position: absolute; left: 0; top: 6px; font-size: 0.6rem; color: var(--text-muted);
        }

        .card-action {
            font-size: 0.85rem; font-weight: 600; color: var(--text-secondary);
            transition: color var(--transition); display: flex; align-items: center; gap: 4px;
            margin-top: auto; justify-content: flex-end;
        }
        .management-card:hover .card-action { color: var(--emerald); }

        .no-results-message {
            grid-column: 1 / -1; text-align: center; padding: 4rem 2rem;
            color: var(--text-muted); border: 1px dashed var(--border); border-radius: var(--radius-xl);
            background: rgba(255,255,255,0.01);
        }
        .no-results-message i { font-size: 3rem; opacity: 0.2; margin-bottom: 1rem; display: block; }
        .no-results-message strong { color: var(--text-primary); }

        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

        /* ─── RESPONSIVE ─── */
        @media (max-width: 768px) {
            .container { padding: 1rem; }
            .page-header { margin-bottom: 2rem;}
            .categories-grid { grid-template-columns: 1fr; }
            .search-bar { flex-direction: column; }
            .category-card { padding: 1.5rem; }
        }
    </style>
</head>
<body>

<div class="container">

    <div class="top-bar">
        <a href="admin_dashboard.php" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back to Dashboard
        </a>
        <span class="page-badge"><i class="fa-solid fa-money-bill-trend-up"></i> Financial Center</span>
    </div>

    <header class="page-header">
        <div class="header-info">
            <h1 class="page-title">Costing <span>&amp; Expenses</span></h1>
            <p class="page-subtitle">Track Farm Investment Categories</p>
            <p class="page-description">Select a category below to manage prices, calculate cumulative totals, or view historical expense records.</p>
        </div>
    </header>

    <div class="search-filter-section">
        <div class="search-bar">
            <i class="fa-solid fa-magnifying-glass search-icon"></i>
            <input type="text" id="searchInput" class="search-input" placeholder="Search costing modules or keywords...">
        </div>
    </div>

    <div class="categories-grid">

        <a href="animal_cost.php" class="category-card c-animal">
            <div class="category-header">
                <div class="category-icon blue"><i class="fa-solid fa-piggy-bank"></i></div>
                <div class="category-info">
                    <h3 class="category-title">Animal Cost</h3>
                    <p class="category-subtitle">Initial Investment + Lifecycle Costs</p>
                </div>
            </div>
            <div class="analytics-preview">
                <div class="analytics-preview-title">Category Options</div>
                <ul class="metrics-list">
                    <li>Purchased Livestock Cost</li>
                    <li>Home Grown / Birthing Records</li>
                    <li>Transport &amp; Delivery Fees</li>
                    <li>Initial Weight Valuation</li>
                </ul>
            </div>
            <div class="card-action">
                Manage Acquisition <i class="fa-solid fa-arrow-right"></i>
            </div>
        </a>

        <a href="costing_feeds.php" class="category-card c-feed">
            <div class="category-header">
                <div class="category-icon orange"><i class="fa-solid fa-wheat-awn"></i></div>
                <div class="category-info">
                    <h3 class="category-title">Feed Consumption</h3>
                    <p class="category-subtitle">Daily Nutrition Expenses</p>
                </div>
            </div>
            <div class="analytics-preview">
                <div class="analytics-preview-title">Category Options</div>
                <ul class="metrics-list">
                    <li>Pre-Starter / Booster Cost</li>
                    <li>Starter &amp; Grower Feeds</li>
                    <li>Finisher Ration Cost</li>
                    <li>Sack vs. Kilogram Calculation</li>
                </ul>
            </div>
            <div class="card-action">
                Manage Feeding Costs <i class="fa-solid fa-arrow-right"></i>
            </div>
        </a>

        <a href="costing_medication.php" class="category-card c-meds">
            <div class="category-header">
                <div class="category-icon pink"><i class="fa-solid fa-pills"></i></div>
                <div class="category-info">
                    <h3 class="category-title">Medication &amp; Treatments</h3>
                    <p class="category-subtitle">Curative Care Expenses</p>
                </div>
            </div>
            <div class="analytics-preview">
                <div class="analytics-preview-title">Category Options</div>
                <ul class="metrics-list">
                    <li>Antibiotics &amp; Injectables</li>
                    <li>Deworming Costs</li>
                    <li>Wound Sprays &amp; Topicals</li>
                    <li>Treatment Supplies</li>
                </ul>
            </div>
            <div class="card-action">
                Manage Medications <i class="fa-solid fa-arrow-right"></i>
            </div>
        </a>

        <a href="costing_vaccines.php" class="category-card c-vacs">
            <div class="category-header">
                <div class="category-icon red"><i class="fa-solid fa-syringe"></i></div>
                <div class="category-info">
                    <h3 class="category-title">Vaccinations</h3>
                    <p class="category-subtitle">Preventive Immunization</p>
                </div>
            </div>
            <div class="analytics-preview">
                <div class="analytics-preview-title">Category Options</div>
                <ul class="metrics-list">
                    <li>Hog Cholera Vaccine</li>
                    <li>Mycoplasma &amp; FMD</li>
                    <li>Parvo / Lepto Shots</li>
                    <li>Syringe &amp; Needle Costs</li>
                </ul>
            </div>
            <div class="card-action">
                Manage Vaccines <i class="fa-solid fa-arrow-right"></i>
            </div>
        </a>

        <a href="costing_vitamins_supplies.php" class="category-card c-vits">
            <div class="category-header">
                <div class="category-icon emerald"><i class="fa-solid fa-flask"></i></div>
                <div class="category-info">
                    <h3 class="category-title">Vitamins &amp; Supplements</h3>
                    <p class="category-subtitle">Growth Boosters</p>
                </div>
            </div>
            <div class="analytics-preview">
                <div class="analytics-preview-title">Category Options</div>
                <ul class="metrics-list">
                    <li>Multivitamins (Injectable/Oral)</li>
                    <li>Iron Supplementation</li>
                    <li>Electrolytes &amp; Probiotics</li>
                    <li>Growth Enhancers</li>
                </ul>
            </div>
            <div class="card-action">
                Manage Supplements <i class="fa-solid fa-arrow-right"></i>
            </div>
        </a>

        <a href="costing_checkups.php" class="category-card c-checkup">
            <div class="category-header">
                <div class="category-icon purple"><i class="fa-solid fa-stethoscope"></i></div>
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
                Manage Check-ups <i class="fa-solid fa-arrow-right"></i>
            </div>
        </a>

    </div>
</div>

<script>
    const searchInput = document.getElementById('searchInput');
    const categoryCards = document.querySelectorAll('.category-card');

    searchInput.addEventListener('input', searchCategories);
    searchInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') searchCategories();
    });

    function searchCategories() {
        const searchTerm = searchInput.value.toLowerCase().trim();
        let visibleCount = 0;

        categoryCards.forEach(card => {
            const title = card.querySelector('.category-title').textContent.toLowerCase();
            const subtitle = card.querySelector('.category-subtitle').textContent.toLowerCase();
            
            // Collect any additional stats or labels for robust searching
            const items = Array.from(card.querySelectorAll('.metrics-list li'))
                .map(li => li.textContent.toLowerCase())
                .join(' ');

            const searchableContent = `${title} ${subtitle} ${items}`;

            if (searchTerm === '' || searchableContent.includes(searchTerm)) {
                card.style.display = 'flex';
                visibleCount++;
                card.style.animation = 'fadeIn 0.3s ease';
            } else {
                card.style.display = 'none';
            }
        });

        showNoResultsMessage(visibleCount, searchTerm);
    }

    function showNoResultsMessage(count, term) {
        const existingMessage = document.querySelector('.no-results-message');
        if (existingMessage) existingMessage.remove();

        if (count === 0 && term !== '') {
            const grid = document.querySelector('.categories-grid');
            const message = document.createElement('div');
            message.className = 'no-results-message';
            message.innerHTML = `
                <i class="fa-solid fa-magnifying-glass"></i>
                <h3 style="color: #fff; margin-bottom: 0.5rem; font-size: 1.25rem;">No costing modules found for "<strong>${term}</strong>"</h3>
                <p style="margin:0;">Try searching for: feeds, medicine, checkups, etc.</p>
            `;
            grid.appendChild(message);
        }
    }
</script>
</body>
</html>