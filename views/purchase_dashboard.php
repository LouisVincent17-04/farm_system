<?php
// purchase_dashboard.php
$page = "transactions";
include '../config/Connection.php';

include '../security/checkAccess.php';
checkAccess('purchases');
include '../common/navbar.php';
include '../common/chat_support.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Purchased Items Dashboard | FarmPro</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=DM+Mono:wght@400;500;700&display=swap" rel="stylesheet">
    <!-- Upgraded to 6.7.2 — adds wheat-awn, mars-and-venus, boxes-stacked, cow, house-chimney-window -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" />

    <style>
        /* ─── CSS VARIABLES ─── */
        :root {
            --bg-base:        #080f1a;
            --bg-surface:     #0d1829;
            --bg-elevated:    #111f35;
            --bg-hover:       #162540;
            --border:         rgba(255,255,255,0.07);
            --border-active:  rgba(16,185,129,0.5);
            --emerald:        #10b981;
            --emerald-dim:    rgba(16,185,129,0.12);
            --emerald-glow:   rgba(16,185,129,0.25);
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
        .page-header { text-align: center; margin-bottom: 3.5rem; }
        .page-title {
            font-size: clamp(2rem, 4vw, 3rem); font-weight: 700;
            color: var(--text-primary); letter-spacing: -0.03em; line-height: 1.1; margin-bottom: 0.75rem;
        }
        .page-title span {
            background: linear-gradient(135deg, var(--emerald), #047857);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }
        .page-subtitle { color: var(--text-secondary); font-size: 1.1rem; }

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

        .btn-primary {
            padding: 0 24px; background: var(--emerald); color: #000;
            border: none; border-radius: var(--radius-md); font-weight: 700;
            font-size: 0.95rem; font-family: var(--font); cursor: pointer;
            transition: all var(--transition); white-space: nowrap;
        }
        .btn-primary:hover { background: #34d399; box-shadow: 0 0 16px var(--emerald-glow); transform: translateY(-1px); }

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

        .category-card.c-animals:hover   { border-color: rgba(59,130,246,0.4); }
        .category-card.c-medicines:hover { border-color: rgba(225,29,72,0.4); }
        .category-card.c-vitamins:hover  { border-color: rgba(16,185,129,0.4); }
        .category-card.c-vaccines:hover  { border-color: rgba(6,182,212,0.4); }
        .category-card.c-feeds:hover     { border-color: rgba(245,158,11,0.4); }
        .category-card.c-housing:hover   { border-color: rgba(168,85,247,0.4); }
        .category-card.c-equipment:hover { border-color: rgba(99,102,241,0.4); }
        .category-card.c-sanitation:hover{ border-color: rgba(20,184,166,0.4); }
        .category-card.c-breeding:hover  { border-color: rgba(249,115,22,0.4); }
        .category-card.c-admin:hover     { border-color: rgba(100,116,139,0.4); }
        .category-card.c-maintenance:hover{ border-color: rgba(107,114,128,0.4); }
        .category-card.c-utilities:hover { border-color: rgba(234,179,8,0.4); }
        .category-card.c-others:hover    { border-color: rgba(236,72,153,0.4); }

        .category-header { display: flex; align-items: center; gap: 1rem; margin-bottom: 1.5rem; }
        
        .category-icon {
            width: 56px; height: 56px; border-radius: var(--radius-lg);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.5rem; color: white; box-shadow: 0 8px 16px rgba(0,0,0,0.3); flex-shrink: 0;
        }

        .category-icon.animals     { background: linear-gradient(135deg, #3b82f6, #1d4ed8); }
        .category-icon.medicines   { background: linear-gradient(135deg, #e11d48, #be123c); }
        .category-icon.vitamins    { background: linear-gradient(135deg, #10b981, #047857); }
        .category-icon.vaccines    { background: linear-gradient(135deg, #06b6d4, #0891b2); }
        .category-icon.feeds       { background: linear-gradient(135deg, #f59e0b, #d97706); }
        .category-icon.housing     { background: linear-gradient(135deg, #a855f7, #7e22ce); }
        .category-icon.equipment   { background: linear-gradient(135deg, #6366f1, #4338ca); }
        .category-icon.sanitation  { background: linear-gradient(135deg, #14b8a6, #0f766e); }
        .category-icon.breeding    { background: linear-gradient(135deg, #f97316, #c2410c); }
        .category-icon.admin       { background: linear-gradient(135deg, #64748b, #334155); }
        .category-icon.maintenance { background: linear-gradient(135deg, #6b7280, #374151); }
        .category-icon.utilities   { background: linear-gradient(135deg, #eab308, #a16207); }
        .category-icon.others      { background: linear-gradient(135deg, #ec4899, #be185d); }

        .category-info { flex: 1; }
        .category-title { font-size: 1.15rem; font-weight: 700; color: #fff; margin-bottom: 0.25rem; }
        .category-subtitle { color: var(--text-secondary); font-size: 0.85rem; }

        .category-items { margin-bottom: 1.5rem; flex-grow: 1; }
        .item-list { list-style: none; padding-left: 0; }
        .item-list li {
            color: var(--text-muted); font-size: 0.9rem; padding: 4px 0 4px 1.25rem;
            position: relative; line-height: 1.4;
        }
        .item-list li::before {
            content: "\f054"; font-family: "Font Awesome 6 Free"; font-weight: 900;
            position: absolute; left: 0; top: 6px; font-size: 0.6rem; color: var(--text-secondary);
            opacity: 0.5;
        }

        .category-stats {
            display: flex; justify-content: space-between; align-items: flex-end;
            padding-top: 1.25rem; border-top: 1px solid var(--border); margin-top: auto;
        }
        .stat-item { display: flex; flex-direction: column; gap: 2px; }
        .stat-number { font-size: 1.2rem; font-weight: 700; color: #fff; font-family: var(--font-mono); line-height: 1; }
        .stat-label { font-size: 0.7rem; color: var(--text-muted); text-transform: uppercase; font-weight: 600; letter-spacing: 0.05em; }

        .card-action { font-size: 0.85rem; font-weight: 600; color: var(--text-secondary); transition: color var(--transition); display: flex; align-items: center; gap: 4px; }
        .category-card:hover .card-action { color: var(--emerald); }

        .no-results-message {
            grid-column: 1 / -1; text-align: center; padding: 4rem 2rem;
            color: var(--text-muted); border: 1px dashed var(--border); border-radius: var(--radius-xl);
            background: rgba(255,255,255,0.01);
        }
        .no-results-message i { font-size: 3rem; opacity: 0.2; margin-bottom: 1rem; display: block; }
        .no-results-message strong { color: var(--text-primary); }

        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

        @media (max-width: 768px) {
            .container { padding: 1rem; }
            .page-header { text-align: center; margin-bottom: 2rem;}
            .categories-grid { grid-template-columns: 1fr; }
            .search-bar { flex-direction: column; }
            .btn-primary { padding: 12px; }
            .category-card { padding: 1.5rem; }
            .category-icon { width: 48px; height: 48px; font-size: 1.25rem; }
        }
    </style>
</head>
<body>

<div class="container">

    <div class="top-bar">
        <a href="transactions.php" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back to Transactions
        </a>
        <span class="page-badge"><i class="fa-solid fa-boxes-stacked"></i> Procurement</span>
    </div>

    <header class="page-header">
        <div class="header-info">
            <h1 class="page-title">Purchased <span>Inventory</span></h1>
            <p class="page-subtitle">Central hub for tracking all farm acquisitions and operational supplies.</p>
        </div>
    </header>

    <div class="search-filter-section">
        <div class="search-bar">
            <i class="fa-solid fa-magnifying-glass search-icon"></i>
            <input type="text" id="searchInput" class="search-input" placeholder="Search categories, items, or keywords...">
            <button class="btn-primary" onclick="searchCategories()">Search Directory</button>
        </div>
    </div>

    <div class="categories-grid">

        <!-- Animals — fa-cow added in FA 6.1, safe on 6.7.2 -->
        <a href="purch_animals.php" class="category-card c-animals">
            <div class="category-header">
                <div class="category-icon animals"><i class="fa-solid fa-cow"></i></div>
                <div class="category-info">
                    <h3 class="category-title">Animals / Livestock</h3>
                    <p class="category-subtitle">Live stock and breeders</p>
                </div>
            </div>
            <div class="category-items">
                <ul class="item-list">
                    <li>Piglets / Weaners</li>
                    <li>Gilts &amp; Boars</li>
                    <li>Chicks / Broilers</li>
                    <li>Layers</li>
                </ul>
            </div>
            <div class="category-stats">
                <div class="stat-item">
                    <div class="stat-number">45</div>
                    <div class="stat-label">Heads</div>
                </div>
                <div class="stat-item" style="text-align:right;">
                    <div class="stat-number" style="color:var(--text-secondary);">₱185,000</div>
                    <div class="stat-label">Total Value</div>
                </div>
                <div class="card-action">View <i class="fa-solid fa-arrow-right"></i></div>
            </div>
        </a>

        <!-- Medicines -->
        <a href="purch_medicines.php" class="category-card c-medicines">
            <div class="category-header">
                <div class="category-icon medicines"><i class="fa-solid fa-capsules"></i></div>
                <div class="category-info">
                    <h3 class="category-title">Medicines</h3>
                    <p class="category-subtitle">Disease Treatments</p>
                </div>
            </div>
            <div class="category-items">
                <ul class="item-list">
                    <li>Antibiotics</li>
                    <li>Antiparasitics</li>
                    <li>Anti-inflammatories</li>
                    <li>Pain Relievers</li>
                </ul>
            </div>
            <div class="category-stats">
                <div class="stat-item">
                    <div class="stat-number">124</div>
                    <div class="stat-label">Entries</div>
                </div>
                <div class="stat-item" style="text-align:right;">
                    <div class="stat-number" style="color:var(--text-secondary);">₱45,200</div>
                    <div class="stat-label">Total Value</div>
                </div>
                <div class="card-action">View <i class="fa-solid fa-arrow-right"></i></div>
            </div>
        </a>

        <!-- Vitamins -->
        <a href="purch_vitamins_supplements.php" class="category-card c-vitamins">
            <div class="category-header">
                <div class="category-icon vitamins"><i class="fa-solid fa-seedling"></i></div>
                <div class="category-info">
                    <h3 class="category-title">Vitamins &amp; Supplements</h3>
                    <p class="category-subtitle">Nutritional additives &amp; boosters</p>
                </div>
            </div>
            <div class="category-items">
                <ul class="item-list">
                    <li>Multivitamins (A, D, E, K)</li>
                    <li>B-Complex</li>
                    <li>Probiotics &amp; Prebiotics</li>
                    <li>Mineral supplements</li>
                </ul>
            </div>
            <div class="category-stats">
                <div class="stat-item">
                    <div class="stat-number">35</div>
                    <div class="stat-label">Entries</div>
                </div>
                <div class="stat-item" style="text-align:right;">
                    <div class="stat-number" style="color:var(--text-secondary);">₱18,700</div>
                    <div class="stat-label">Total Value</div>
                </div>
                <div class="card-action">View <i class="fa-solid fa-arrow-right"></i></div>
            </div>
        </a>

        <!-- Vaccines -->
        <a href="purch_vaccines.php" class="category-card c-vaccines">
            <div class="category-header">
                <div class="category-icon vaccines"><i class="fa-solid fa-syringe"></i></div>
                <div class="category-info">
                    <h3 class="category-title">Vaccines</h3>
                    <p class="category-subtitle">Preventive health &amp; biosecurity</p>
                </div>
            </div>
            <div class="category-items">
                <ul class="item-list">
                    <li>Swine Fever Vaccine</li>
                    <li>FMD (Foot-and-Mouth)</li>
                    <li>Avian Influenza Vaccine</li>
                    <li>Dewormers</li>
                </ul>
            </div>
            <div class="category-stats">
                <div class="stat-item">
                    <div class="stat-number">22</div>
                    <div class="stat-label">Entries</div>
                </div>
                <div class="stat-item" style="text-align:right;">
                    <div class="stat-number" style="color:var(--text-secondary);">₱75,000</div>
                    <div class="stat-label">Total Value</div>
                </div>
                <div class="card-action">View <i class="fa-solid fa-arrow-right"></i></div>
            </div>
        </a>

        <!-- Feeds — fa-wheat-awn added FA 6.1; safe on 6.7.2 -->
        <a href="purch_feeds_feeding.php" class="category-card c-feeds">
            <div class="category-header">
                <div class="category-icon feeds"><i class="fa-solid fa-wheat-awn"></i></div>
                <div class="category-info">
                    <h3 class="category-title">Feeds &amp; Feeding Supplies</h3>
                    <p class="category-subtitle">Food and feeding tools</p>
                </div>
            </div>
            <div class="category-items">
                <ul class="item-list">
                    <li>Starter, Grower, Finisher</li>
                    <li>Feed additives</li>
                    <li>Feeders / Waterers</li>
                    <li>Storage containers</li>
                </ul>
            </div>
            <div class="category-stats">
                <div class="stat-item">
                    <div class="stat-number">89</div>
                    <div class="stat-label">Entries</div>
                </div>
                <div class="stat-item" style="text-align:right;">
                    <div class="stat-number" style="color:var(--text-secondary);">₱128,500</div>
                    <div class="stat-label">Total Value</div>
                </div>
                <div class="card-action">View <i class="fa-solid fa-arrow-right"></i></div>
            </div>
        </a>

        <!-- Housing — fa-house-chimney-window added FA 6.1; safe on 6.7.2 -->
        <a href="purch_housing_facilities.php" class="category-card c-housing">
            <div class="category-header">
                <div class="category-icon housing"><i class="fa-solid fa-house-chimney-window"></i></div>
                <div class="category-info">
                    <h3 class="category-title">Housing &amp; Facilities</h3>
                    <p class="category-subtitle">Animal shelter and comfort</p>
                </div>
            </div>
            <div class="category-items">
                <ul class="item-list">
                    <li>Pens / Pig Houses</li>
                    <li>Chicken Coops</li>
                    <li>Brooder boxes</li>
                    <li>Ventilation fans</li>
                </ul>
            </div>
            <div class="category-stats">
                <div class="stat-item">
                    <div class="stat-number">56</div>
                    <div class="stat-label">Entries</div>
                </div>
                <div class="stat-item" style="text-align:right;">
                    <div class="stat-number" style="color:var(--text-secondary);">₱234,800</div>
                    <div class="stat-label">Total Value</div>
                </div>
                <div class="card-action">View <i class="fa-solid fa-arrow-right"></i></div>
            </div>
        </a>

        <!-- Equipment -->
        <a href="purch_farm_equipment_tools.php" class="category-card c-equipment">
            <div class="category-header">
                <div class="category-icon equipment"><i class="fa-solid fa-toolbox"></i></div>
                <div class="category-info">
                    <h3 class="category-title">Farm Equipment</h3>
                    <p class="category-subtitle">General tools and machinery</p>
                </div>
            </div>
            <div class="category-items">
                <ul class="item-list">
                    <li>Cleaning equipment</li>
                    <li>Feed mixers / grinders</li>
                    <li>Water pumps</li>
                    <li>Power generators</li>
                </ul>
            </div>
            <div class="category-stats">
                <div class="stat-item">
                    <div class="stat-number">73</div>
                    <div class="stat-label">Entries</div>
                </div>
                <div class="stat-item" style="text-align:right;">
                    <div class="stat-number" style="color:var(--text-secondary);">₱156,300</div>
                    <div class="stat-label">Total Value</div>
                </div>
                <div class="card-action">View <i class="fa-solid fa-arrow-right"></i></div>
            </div>
        </a>

        <!-- Sanitation -->
        <a href="purch_sanitation_waste_m.php" class="category-card c-sanitation">
            <div class="category-header">
                <div class="category-icon sanitation"><i class="fa-solid fa-broom"></i></div>
                <div class="category-info">
                    <h3 class="category-title">Sanitation &amp; Waste</h3>
                    <p class="category-subtitle">Hygiene and biosecurity</p>
                </div>
            </div>
            <div class="category-items">
                <ul class="item-list">
                    <li>Waste bins</li>
                    <li>Manure scrapers</li>
                    <li>Sanitizing agents</li>
                    <li>Incinerators</li>
                </ul>
            </div>
            <div class="category-stats">
                <div class="stat-item">
                    <div class="stat-number">42</div>
                    <div class="stat-label">Entries</div>
                </div>
                <div class="stat-item" style="text-align:right;">
                    <div class="stat-number" style="color:var(--text-secondary);">₱38,900</div>
                    <div class="stat-label">Total Value</div>
                </div>
                <div class="card-action">View <i class="fa-solid fa-arrow-right"></i></div>
            </div>
        </a>

        <!-- Breeding — fa-mars-and-venus added FA 6.1; safe on 6.7.2 -->
        <a href="purch_breeding_reproduction.php" class="category-card c-breeding">
            <div class="category-header">
                <div class="category-icon breeding"><i class="fa-solid fa-mars-and-venus"></i></div>
                <div class="category-info">
                    <h3 class="category-title">Breeding &amp; Repro</h3>
                    <p class="category-subtitle">Controlled breeding and care</p>
                </div>
            </div>
            <div class="category-items">
                <ul class="item-list">
                    <li>AI kits</li>
                    <li>Heat detectors</li>
                    <li>Record tags / ID tags</li>
                    <li>Farrowing crates</li>
                </ul>
            </div>
            <div class="category-stats">
                <div class="stat-item">
                    <div class="stat-number">28</div>
                    <div class="stat-label">Entries</div>
                </div>
                <div class="stat-item" style="text-align:right;">
                    <div class="stat-number" style="color:var(--text-secondary);">₱67,400</div>
                    <div class="stat-label">Total Value</div>
                </div>
                <div class="card-action">View <i class="fa-solid fa-arrow-right"></i></div>
            </div>
        </a>

        <!-- Admin -->
        <a href="purch_admin_records.php" class="category-card c-admin">
            <div class="category-header">
                <div class="category-icon admin"><i class="fa-solid fa-folder-closed"></i></div>
                <div class="category-info">
                    <h3 class="category-title">Admin &amp; Records</h3>
                    <p class="category-subtitle">Management and data tracking</p>
                </div>
            </div>
            <div class="category-items">
                <ul class="item-list">
                    <li>Record books / Tags</li>
                    <li>RFID scanners</li>
                    <li>Software licenses</li>
                    <li>Office supplies</li>
                </ul>
            </div>
            <div class="category-stats">
                <div class="stat-item">
                    <div class="stat-number">34</div>
                    <div class="stat-label">Entries</div>
                </div>
                <div class="stat-item" style="text-align:right;">
                    <div class="stat-number" style="color:var(--text-secondary);">₱22,600</div>
                    <div class="stat-label">Total Value</div>
                </div>
                <div class="card-action">View <i class="fa-solid fa-arrow-right"></i></div>
            </div>
        </a>

        <!-- Maintenance -->
        <a href="purch_maintenance_parts.php" class="category-card c-maintenance">
            <div class="category-header">
                <div class="category-icon maintenance"><i class="fa-solid fa-screwdriver-wrench"></i></div>
                <div class="category-info">
                    <h3 class="category-title">Maintenance Parts</h3>
                    <p class="category-subtitle">Farm infrastructure upkeep</p>
                </div>
            </div>
            <div class="category-items">
                <ul class="item-list">
                    <li>Spare motors / blades</li>
                    <li>Lubricants and oils</li>
                    <li>Repair tools and kits</li>
                </ul>
            </div>
            <div class="category-stats">
                <div class="stat-item">
                    <div class="stat-number">61</div>
                    <div class="stat-label">Entries</div>
                </div>
                <div class="stat-item" style="text-align:right;">
                    <div class="stat-number" style="color:var(--text-secondary);">₱54,700</div>
                    <div class="stat-label">Total Value</div>
                </div>
                <div class="card-action">View <i class="fa-solid fa-arrow-right"></i></div>
            </div>
        </a>

        <!-- Utilities -->
        <a href="purch_utilities_consumables.php" class="category-card c-utilities">
            <div class="category-header">
                <div class="category-icon utilities"><i class="fa-solid fa-bolt"></i></div>
                <div class="category-info">
                    <h3 class="category-title">Utilities &amp; Consumable</h3>
                    <p class="category-subtitle">Daily operational needs</p>
                </div>
            </div>
            <div class="category-items">
                <ul class="item-list">
                    <li>Fuel / Diesel</li>
                    <li>Electricity / Batteries</li>
                    <li>Water filters</li>
                </ul>
            </div>
            <div class="category-stats">
                <div class="stat-item">
                    <div class="stat-number">47</div>
                    <div class="stat-label">Entries</div>
                </div>
                <div class="stat-item" style="text-align:right;">
                    <div class="stat-number" style="color:var(--text-secondary);">₱89,200</div>
                    <div class="stat-label">Total Value</div>
                </div>
                <div class="card-action">View <i class="fa-solid fa-arrow-right"></i></div>
            </div>
        </a>

        <!-- Others -->
        <a href="purch_others.php" class="category-card c-others">
            <div class="category-header">
                <div class="category-icon others"><i class="fa-solid fa-box-open"></i></div>
                <div class="category-info">
                    <h3 class="category-title">Others</h3>
                    <p class="category-subtitle">Miscellaneous items</p>
                </div>
            </div>
            <div class="category-items">
                <ul class="item-list">
                    <li>Uncategorized items</li>
                    <li>Special orders</li>
                    <li>Seasonal items</li>
                </ul>
            </div>
            <div class="category-stats">
                <div class="stat-item">
                    <div class="stat-number">19</div>
                    <div class="stat-label">Entries</div>
                </div>
                <div class="stat-item" style="text-align:right;">
                    <div class="stat-number" style="color:var(--text-secondary);">₱15,800</div>
                    <div class="stat-label">Total Value</div>
                </div>
                <div class="card-action">View <i class="fa-solid fa-arrow-right"></i></div>
            </div>
        </a>

    </div><!-- /.categories-grid -->
</div><!-- /.container -->

<script>
    const searchInput   = document.getElementById('searchInput');
    const categoryCards = document.querySelectorAll('.category-card');

    searchInput.addEventListener('input', searchCategories);
    searchInput.addEventListener('keypress', e => { if (e.key === 'Enter') searchCategories(); });

    function searchCategories() {
        const term = searchInput.value.toLowerCase().trim();
        let visible = 0;

        categoryCards.forEach(card => {
            const text = [
                card.querySelector('.category-title').textContent,
                card.querySelector('.category-subtitle').textContent,
                ...Array.from(card.querySelectorAll('.item-list li')).map(li => li.textContent)
            ].join(' ').toLowerCase();

            const show = term === '' || text.includes(term);
            card.style.display = show ? 'flex' : 'none';
            if (show) { visible++; card.style.animation = 'fadeIn 0.3s ease'; }
        });

        const existing = document.querySelector('.no-results-message');
        if (existing) existing.remove();

        if (visible === 0 && term) {
            const msg = document.createElement('div');
            msg.className = 'no-results-message';
            msg.innerHTML = `
                <i class="fa-solid fa-magnifying-glass"></i>
                <h3 style="color:#fff;margin-bottom:.5rem;font-size:1.25rem;">
                    No categories found for "<strong>${term}</strong>"
                </h3>
                <p style="margin:0;">Try: medicines, feeds, equipment, housing…</p>`;
            document.querySelector('.categories-grid').appendChild(msg);
        }
    }
</script>
</body>
</html>