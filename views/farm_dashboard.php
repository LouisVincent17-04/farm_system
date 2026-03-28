<?php
// views/farm_dashboard.php
$page = "farm"; // Active Tab
include '../config/Connection.php';

include '../security/checkAccess.php';
checkAccess('farm');
include '../common/navbar.php';
include '../common/chat_support.php';

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Farm Administration Dashboard | FarmPro</title>
    
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
            --border-active:  rgba(16,185,129,0.5); 
            
            /* Thematic Colors */
            --emerald:        #10b981; --emerald-dim:    rgba(16,185,129,0.12); --emerald-glow:   rgba(16,185,129,0.25);
            --blue:           #3b82f6; --blue-dim:       rgba(59,130,246,0.12);
            --amber:          #f59e0b; --amber-dim:      rgba(245,158,11,0.12);
            --cyan:           #06b6d4; --cyan-dim:       rgba(6,182,212,0.12);
            --rose:           #e11d48; --rose-dim:       rgba(225,29,72,0.12);
            --indigo:         #6366f1; --indigo-dim:     rgba(99,102,241,0.12);
            --purple:         #a855f7; --purple-dim:     rgba(168,85,247,0.12);
            --orange:         #f97316; --orange-dim:     rgba(249,115,22,0.12);
            --red:            #f87171; --red-dim:        rgba(248,113,113,0.12);
            --teal:           #14b8a6; --teal-dim:       rgba(20,184,166,0.12);
            
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
        .page-header { text-align: center; margin-bottom: 3.5rem; }
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
            margin-bottom: 2.5rem; box-shadow: 0 10px 30px -10px rgba(0,0,0,0.5);
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
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        .category-card::before {
            content: ''; position: absolute; top: 0; left: -100%; width: 100%; height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.03), transparent);
            transition: left 0.8s ease; pointer-events: none;
        }
        .category-card:hover { transform: translateY(-4px); box-shadow: 0 15px 35px -10px rgba(0,0,0,0.5); }
        .category-card:hover::before { left: 100%; }

        /* Dynamic Card Borders on Hover */
        .category-card.c-inventory:hover { border-color: rgba(16,185,129,0.4); }
        .category-card.c-class:hover { border-color: rgba(59,130,246,0.4); }
        .category-card.c-bio:hover { border-color: rgba(245,158,11,0.4); }
        .category-card.c-tags:hover { border-color: rgba(6,182,212,0.4); }
        .category-card.c-events:hover { border-color: rgba(225,29,72,0.4); }
        .category-card.c-transfer:hover { border-color: rgba(99,102,241,0.4); }
        .category-card.c-sow:hover { border-color: rgba(168,85,247,0.4); }
        .category-card.c-fcr:hover { border-color: rgba(20,184,166,0.4); }
        .category-card.c-weights:hover { border-color: rgba(249,115,22,0.4); }
        .category-card.c-operations:hover { border-color: rgba(248,113,113,0.4); }
        .category-card.c-sowcards:hover { border-color: rgba(100,116,139,0.4); }
        .category-card.c-birth:hover { border-color: rgba(56,189,248,0.4); }
        .category-card.c-cost:hover { border-color: rgba(13,148,136,0.4); }

        .category-header { display: flex; align-items: center; gap: 1rem; margin-bottom: 1.5rem; }
        
        .category-icon {
            width: 56px; height: 56px; border-radius: var(--radius-lg);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.5rem; color: white; box-shadow: 0 8px 16px rgba(0,0,0,0.3); flex-shrink: 0;
        }
        
        /* Icon Gradients */
        .category-icon.emerald { background: linear-gradient(135deg, var(--emerald), #047857); }
        .category-icon.blue { background: linear-gradient(135deg, var(--blue), #1d4ed8); }
        .category-icon.amber { background: linear-gradient(135deg, var(--amber), #b45309); }
        .category-icon.cyan { background: linear-gradient(135deg, var(--cyan), #0891b2); }
        .category-icon.rose { background: linear-gradient(135deg, var(--rose), #be123c); }
        .category-icon.indigo { background: linear-gradient(135deg, var(--indigo), #4338ca); }
        .category-icon.purple { background: linear-gradient(135deg, var(--purple), #7e22ce); }
        .category-icon.teal { background: linear-gradient(135deg, var(--teal), #0f766e); }
        .category-icon.orange { background: linear-gradient(135deg, var(--orange), #c2410c); }
        .category-icon.red { background: linear-gradient(135deg, var(--red), #b91c1c); }
        .category-icon.slate { background: linear-gradient(135deg, var(--slate), #334155); }
        .category-icon.sky { background: linear-gradient(135deg, #0ea5e9, #0369a1); }

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
            margin-top: auto;
        }
        .category-card:hover .card-action { color: var(--emerald); }

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
        <span class="page-badge"><i class="fa-solid fa-tractor"></i> Farm Operations</span>
    </div>

    <header class="page-header">
        <div class="header-info">
            <h1 class="page-title">Farm <span>Administration</span></h1>
            <p class="page-subtitle">Centralized Control &amp; Classifications</p>
            <p class="page-description">Manage animal stages, reproductive cycles, maintenance protocols, and transfer costs.</p>
        </div>
    </header>

    <div class="search-filter-section">
        <div class="search-bar">
            <i class="fa-solid fa-magnifying-glass search-icon"></i>
            <input type="text" id="searchInput" class="search-input" placeholder="Search modules, features, or keywords...">
        </div>
    </div>

    <div class="categories-grid">

        <a href="inventory_adjustment.php" class="category-card c-inventory">
            <div class="category-header">
                <div class="category-icon emerald"><i class="fa-solid fa-scale-balanced"></i></div>
                <div class="category-info">
                    <h3 class="category-title">Inventory Adjustment</h3>
                    <p class="category-subtitle">Stock Correction</p>
                </div>
            </div>
            <div class="analytics-preview">
                <div class="analytics-preview-title">Manage Discrepancies</div>
                <ul class="metrics-list">
                    <li>Record Spoilage/Damage</li>
                    <li>Correct Audit Errors</li>
                    <li>Track Internal Usage</li>
                    <li>Deduct/Add Stock Manually</li>
                </ul>
            </div>
            <div class="card-action">
                <span>Adjust Stock</span> <i class="fa-solid fa-arrow-right"></i>
            </div>
        </a>

        <a href="animal_classification.php" class="category-card c-class">
            <div class="category-header">
                <div class="category-icon blue"><i class="fa-solid fa-list-ol"></i></div>
                <div class="category-info">
                    <h3 class="category-title">Animal Class</h3>
                    <p class="category-subtitle">Classification Rules</p>
                </div>
            </div>
            <div class="analytics-preview">
                <div class="analytics-preview-title">Manage Stages</div>
                <ul class="metrics-list">
                    <li>Piglet &amp; Started Hog Days</li>
                    <li>Grower &amp; Finisher Ranges</li>
                    <li>Boar &amp; Gilt Transitions</li>
                    <li>Auto-Classification Logic</li>
                </ul>
            </div>
            <div class="card-action">
                <span>Manage Classes</span> <i class="fa-solid fa-arrow-right"></i>
            </div>
        </a>

        <a href="edit_animal_bio.php" class="category-card c-bio">
            <div class="category-header">
                <div class="category-icon amber"><i class="fa-solid fa-dna"></i></div>
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
                    <li>Modify Sex &amp; Breed</li>
                    <li>Fix Initial Weights</li>
                </ul>
            </div>
            <div class="card-action">
                <span>Edit Records</span> <i class="fa-solid fa-arrow-right"></i>
            </div>
        </a>

        <a href="edit_animal_tags.php" class="category-card c-tags">
            <div class="category-header">
                <div class="category-icon cyan"><i class="fa-solid fa-tags"></i></div>
                <div class="category-info">
                    <h3 class="category-title">Batch Tag Editor</h3>
                    <p class="category-subtitle">Fast Ear Tag Assignment</p>
                </div>
            </div>
            <div class="analytics-preview">
                <div class="analytics-preview-title">Tag Management</div>
                <ul class="metrics-list">
                    <li>Edit Tags by Litter</li>
                    <li>Fix Missing or Broken Tags</li>
                    <li>Auto-Increment Sequences</li>
                    <li>Mass Re-tagging Operations</li>
                </ul>
            </div>
            <div class="card-action">
                <span>Edit Tags</span> <i class="fa-solid fa-arrow-right"></i>
            </div>
        </a>

        <a href="events_scheduler.php" class="category-card c-events">
            <div class="category-header">
                <div class="category-icon rose"><i class="fa-regular fa-calendar-check"></i></div>
                <div class="category-info">
                    <h3 class="category-title">Event Scheduler</h3>
                    <p class="category-subtitle">Plan &amp; Automate</p>
                </div>
            </div>
            <div class="analytics-preview">
                <div class="analytics-preview-title">Calendar Management</div>
                <ul class="metrics-list">
                    <li>Schedule Vaccinations</li>
                    <li>Plan Medication Routines</li>
                    <li>Set Checkup Reminders</li>
                    <li>Track Recurring Tasks</li>
                </ul>
            </div>
            <div class="card-action">
                <span>Manage Schedule</span> <i class="fa-solid fa-arrow-right"></i>
            </div>
        </a>

        <a href="animal_transfer_pen.php" class="category-card c-transfer">
            <div class="category-header">
                <div class="category-icon indigo"><i class="fa-solid fa-right-left"></i></div>
                <div class="category-info">
                    <h3 class="category-title">Animal Transfer</h3>
                    <p class="category-subtitle">Move Animals</p>
                </div>
            </div>
            <div class="analytics-preview">
                <div class="analytics-preview-title">Relocation</div>
                <ul class="metrics-list">
                    <li>Pen to Pen Transfer</li>
                    <li>Batch Movement</li>
                    <li>Update Location History</li>
                    <li>Manage Capacities</li>
                </ul>
            </div>
            <div class="card-action">
                <span>Transfer Group</span> <i class="fa-solid fa-arrow-right"></i>
            </div>
        </a>

        <a href="animal_sow_status.php" class="category-card c-sow">
            <div class="category-header">
                <div class="category-icon purple"><i class="fa-solid fa-venus"></i></div>
                <div class="category-info">
                    <h3 class="category-title">Sow Management</h3>
                    <p class="category-subtitle">Reproductive Cycle</p>
                </div>
            </div>
            <div class="analytics-preview">
                <div class="analytics-preview-title">Cycle Tracking</div>
                <ul class="metrics-list">
                    <li>Open &amp; Bred Status</li>
                    <li>Gestating Timeline</li>
                    <li>Lactating &amp; Weaned</li>
                    <li>Status Color Coding</li>
                </ul>
            </div>
            <div class="card-action">
                <span>Manage Sow</span> <i class="fa-solid fa-arrow-right"></i>
            </div>
        </a>

        <a href="fcr_management.php" class="category-card c-fcr">
            <div class="category-header">
                <div class="category-icon teal"><i class="fa-solid fa-chart-line"></i></div>
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
                <span>Manage FCR</span> <i class="fa-solid fa-arrow-right"></i>
            </div>
        </a>

        <a href="animal_weights.php" class="category-card c-weights">
            <div class="category-header">
                <div class="category-icon orange"><i class="fa-solid fa-weight-scale"></i></div>
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
                <span>Update Weights</span> <i class="fa-solid fa-arrow-right"></i>
            </div>
        </a>

        <a href="animal_operations.php" class="category-card c-operations">
            <div class="category-header">
                <div class="category-icon red"><i class="fa-solid fa-gears"></i></div>
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
                <span>Manage Operations</span> <i class="fa-solid fa-arrow-right"></i>
            </div>
        </a>

        <a href="animal_sow_cards.php" class="category-card c-sowcards">
            <div class="category-header">
                <div class="category-icon slate"><i class="fa-solid fa-clipboard-list"></i></div>
                <div class="category-info">
                    <h3 class="category-title">Sow Cards</h3>
                    <p class="category-subtitle">Individual Records</p>
                </div>
            </div>
            <div class="analytics-preview">
                <div class="analytics-preview-title">Digital Records</div>
                <ul class="metrics-list">
                    <li>Litter History &amp; Count</li>
                    <li>Vaccination Logs</li>
                    <li>Breeding Dates</li>
                    <li>Performance History</li>
                </ul>
            </div>
            <div class="card-action">
                <span>View Sow Cards</span> <i class="fa-solid fa-arrow-right"></i>
            </div>
        </a>

        <a href="animal_birth_certificate.php" class="category-card c-birth">
            <div class="category-header">
                <div class="category-icon sky"><i class="fa-solid fa-certificate"></i></div>
                <div class="category-info">
                    <h3 class="category-title">Birth Certificate</h3>
                    <p class="category-subtitle">Registration &amp; Pedigree</p>
                </div>
            </div>
            <div class="analytics-preview">
                <div class="analytics-preview-title">Official Records</div>
                <ul class="metrics-list">
                    <li>Generate Certificates</li>
                    <li>View Lineage / Pedigree</li>
                    <li>Printable Formats</li>
                    <li>Litter Registration</li>
                </ul>
            </div>
            <div class="card-action">
                <span>View Certificates</span> <i class="fa-solid fa-arrow-right"></i>
            </div>
        </a>

        <a href="animal_cost_transfers.php" class="category-card c-cost">
            <div class="category-header">
                <div class="category-icon teal"><i class="fa-solid fa-money-bill-transfer"></i></div>
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
                <span>Transfer Costs</span> <i class="fa-solid fa-arrow-right"></i>
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
                <h3 style="color: #fff; margin-bottom: 0.5rem; font-size: 1.25rem;">No administrative modules found for "<strong>${term}</strong>"</h3>
                <p style="margin:0;">Try searching for: weights, lineage, schedule, adjustment, etc.</p>
            `;
            grid.appendChild(message);
        }
    }
</script>
</body>
</html>