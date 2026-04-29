<?php
// views/animal_cost.php
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Start output buffering
ob_start();

$page = "costing";
include '../config/Connection.php';

include '../security/checkAccess.php';
checkAccess('animal_cost');
include '../common/navbar.php';
include '../common/chat_support.php';

// --- AJAX HANDLER FOR DROPDOWNS ---
if (isset($_GET['action'])) {
    ob_end_clean();
    ob_start();
    header('Content-Type: application/json');
    $action = $_GET['action'];

    try {
        if ($action === 'get_buildings' && isset($_GET['location_id'])) {
            $stmt = $conn->prepare("SELECT BUILDING_ID, BUILDING_NAME FROM BUILDINGS WHERE LOCATION_ID = ? ORDER BY BUILDING_NAME");
            $stmt->execute([$_GET['location_id']]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            exit;
        }
        if ($action === 'get_pens' && isset($_GET['building_id'])) {
            $stmt = $conn->prepare("SELECT PEN_ID, PEN_NAME FROM PENS WHERE BUILDING_ID = ? ORDER BY PEN_NAME");
            $stmt->execute([$_GET['building_id']]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            exit;
        }
        // New: Get Animals for Dropdown
        if ($action === 'get_animals' && isset($_GET['pen_id'])) {
            $stmt = $conn->prepare("SELECT ANIMAL_ID, TAG_NO FROM animal_records WHERE PEN_ID = ? AND IS_ACTIVE = 1 ORDER BY TAG_NO");
            $stmt->execute([$_GET['pen_id']]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            exit;
        }
    } catch (Exception $e) {
        echo json_encode([]); exit;
    }
}

// --- GET FILTERS ---
$location_id = $_GET['location_id'] ?? '';
$building_id = $_GET['building_id'] ?? '';
$pen_id      = $_GET['pen_id'] ?? '';
$animal_id   = $_GET['animal_id'] ?? ''; // From Dropdown
$search      = $_GET['search'] ?? '';    // From Search Box

// --- MAIN AGGREGATE QUERY ---
$sql = "
    SELECT 
        ar.ANIMAL_ID,
        ar.TAG_NO,
        at.ANIMAL_TYPE_NAME,
        ar.CURRENT_STATUS,
        l.LOCATION_NAME,
        b.BUILDING_NAME,
        p.PEN_NAME,
        
        -- 1. Acquisition
        COALESCE(ar.ACQUISITION_COST, 0) as COST_ACQUISITION,

        -- 2. Misc Fees (From animal_records)
        COALESCE(ar.TOTAL_MISC_AMT, 0) as COST_MISC,

        -- 3. Feed (Sum of Transaction Cost)
        (SELECT COALESCE(SUM(TRANSACTION_COST), 0) 
         FROM feed_transactions 
         WHERE ANIMAL_ID = ar.ANIMAL_ID) as COST_FEED,

        -- 4. Medical (Meds)
        (SELECT COALESCE(SUM(TOTAL_COST), 0) 
         FROM treatment_transactions 
         WHERE ANIMAL_ID = ar.ANIMAL_ID) as COST_MEDS,

        -- 5. Vaccine
        (SELECT COALESCE(SUM(VACCINATION_COST + VACCINE_COST), 0) 
         FROM vaccination_records 
         WHERE ANIMAL_ID = ar.ANIMAL_ID) as COST_VACCINE,

        -- 6. Vitamins
        (SELECT COALESCE(SUM(TOTAL_COST), 0)
         FROM vitamins_supplements_transactions 
         WHERE ANIMAL_ID = ar.ANIMAL_ID) as COST_VITAMINS,

        -- 7. Checkup Professional Fees
        (SELECT COALESCE(SUM(COST), 0) 
         FROM check_ups 
         WHERE ANIMAL_ID = ar.ANIMAL_ID) as COST_CHECKUP,

        -- 8. Count of Checkups
        (SELECT COUNT(*) 
         FROM check_ups 
         WHERE ANIMAL_ID = ar.ANIMAL_ID) as COUNT_CHECKUP

    FROM animal_records ar
    LEFT JOIN animal_type at ON ar.ANIMAL_TYPE_ID = at.ANIMAL_TYPE_ID
    LEFT JOIN locations l ON ar.LOCATION_ID = l.LOCATION_ID
    LEFT JOIN buildings b ON ar.BUILDING_ID = b.BUILDING_ID
    LEFT JOIN pens p ON ar.PEN_ID = p.PEN_ID
    WHERE ar.IS_ACTIVE = 1
";

// Apply Filters
$params = [];

if ($location_id) {
    $sql .= " AND ar.LOCATION_ID = ?";
    $params[] = $location_id;
}
if ($building_id) {
    $sql .= " AND ar.BUILDING_ID = ?";
    $params[] = $building_id;
}
if ($pen_id) {
    $sql .= " AND ar.PEN_ID = ?";
    $params[] = $pen_id;
}

// 2-WAY SEARCH LOGIC: Dropdown OR Search Box
if ($animal_id) {
    // If specific animal selected from dropdown
    $sql .= " AND ar.ANIMAL_ID = ?";
    $params[] = $animal_id;
} elseif ($search) {
    // If typing in search box (Search Tag No or Type)
    $sql .= " AND (ar.TAG_NO LIKE ? OR at.ANIMAL_TYPE_NAME LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY ar.ANIMAL_ID DESC";

try {
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch Locations for Initial Dropdown
    $locStmt = $conn->prepare("SELECT * FROM locations ORDER BY LOCATION_NAME");
    $locStmt->execute();
    $locations = $locStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $data = [];
    $error = $e->getMessage();
}

// Calculate Dashboard Totals
$total_operating = 0; // Without Acquisition
$total_net_worth = 0; // With Acquisition
$total_feed = 0;
$total_health = 0; 
$total_acquisition = 0;
$total_misc = 0; // Added for Misc Fees

foreach ($data as $row) {
    $health_sum = $row['COST_MEDS'] + $row['COST_VACCINE'] + $row['COST_VITAMINS'] + $row['COST_CHECKUP'];
    $operating_sum = $row['COST_FEED'] + $health_sum + $row['COST_MISC']; // Factored Misc into Operating
    
    $total_operating += $operating_sum;
    $total_net_worth += ($operating_sum + $row['COST_ACQUISITION']);
    
    $total_feed += $row['COST_FEED'];
    $total_health += $health_sum;
    $total_acquisition += $row['COST_ACQUISITION'];
    $total_misc += $row['COST_MISC'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Animal Net Worth | FarmPro</title>
    
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
            
            --emerald:        #10b981; --emerald-dim: rgba(16,185,129,0.12); --emerald-glow: rgba(16,185,129,0.25);
            --blue:           #3b82f6; --blue-dim: rgba(59,130,246,0.12);
            --amber:          #f59e0b; --amber-dim: rgba(245,158,11,0.12);
            --purple:         #a855f7; --purple-dim: rgba(168,85,247,0.12);
            --pink:           #f472b6; --pink-dim: rgba(244,114,182,0.12);
            --indigo:         #6366f1; --indigo-dim: rgba(99,102,241,0.12);
            --red:            #f87171; --red-dim: rgba(248,113,113,0.12);
            
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
            background-image: radial-gradient(ellipse 80% 50% at 50% -20%, rgba(16,185,129,0.06) 0%, transparent 60%);
        }
        .container { max-width: 1600px; margin: 0 auto; padding: 2rem 1.5rem; }

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
            color: var(--emerald); background: var(--emerald-dim); border: 1px solid rgba(16,185,129,0.2);
            padding: 6px 12px; border-radius: 99px;
        }

        /* ─── HEADER ─── */
        .page-header { text-align: center; margin-bottom: 2.5rem; margin-top: 1rem; }
        .page-title {
            font-size: clamp(2rem, 4vw, 3rem); font-weight: 700;
            color: var(--text-primary); letter-spacing: -0.03em; line-height: 1.1; margin-bottom: 0.5rem;
        }
        .page-title span {
            background: linear-gradient(135deg, var(--emerald), #047857);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }
        .page-subtitle { color: var(--text-secondary); font-size: 1.05rem; margin: 0; }

        /* ─── DASHBOARD STATS ─── */
        .dashboard-grid { 
            display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); 
            gap: 1.5rem; margin-bottom: 2.5rem; 
        }
        .stat-card { 
            background: var(--bg-surface); border: 1px solid var(--border); 
            border-radius: var(--radius-xl); padding: 1.5rem; 
            box-shadow: var(--shadow-md); position: relative; overflow: hidden;
            display: flex; flex-direction: column; justify-content: space-between;
        }
        
        .stat-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px; }
        .stat-green::before { background: var(--emerald); }
        .stat-blue::before { background: var(--blue); }
        .stat-purple::before { background: var(--purple); }
        .stat-yellow::before { background: var(--amber); }
        .stat-pink::before { background: var(--pink); }
        .stat-indigo::before { background: var(--indigo); }

        .stat-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem; }
        .stat-title { color: var(--text-secondary); font-size: 0.75rem; text-transform: uppercase; font-weight: 700; letter-spacing: 0.05em;}
        .stat-icon { width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 1rem; color: #fff;}
        .stat-green .stat-icon { background: linear-gradient(135deg, var(--emerald), #047857); }
        .stat-blue .stat-icon { background: linear-gradient(135deg, var(--blue), #1d4ed8); }
        .stat-purple .stat-icon { background: linear-gradient(135deg, var(--purple), #7e22ce); }
        .stat-yellow .stat-icon { background: linear-gradient(135deg, var(--amber), #b45309); }
        .stat-pink .stat-icon { background: linear-gradient(135deg, var(--pink), #be185d); }
        .stat-indigo .stat-icon { background: linear-gradient(135deg, var(--indigo), #4338ca); }

        .stat-value { font-size: 2.2rem; font-weight: 800; font-family: var(--font-mono); line-height: 1; }
        .stat-green .stat-value { color: var(--emerald); }
        .stat-blue .stat-value { color: var(--blue); }
        .stat-purple .stat-value { color: var(--purple); }
        .stat-yellow .stat-value { color: var(--amber); }
        .stat-pink .stat-value { color: var(--pink); }
        .stat-indigo .stat-value { color: var(--indigo); }

        /* ─── FILTER BAR ─── */
        .filter-bar { 
            background: var(--bg-surface); padding: 1.5rem; border-radius: var(--radius-xl); 
            margin-bottom: 2rem; border: 1px solid var(--border); box-shadow: var(--shadow-md);
            display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.25rem; align-items: flex-end;
        }
        .form-group { display: flex; flex-direction: column; gap: 6px; }
        .form-group label { color: var(--text-secondary); font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; }
        
        .form-select, .form-input { 
            width: 100%; padding: 12px 14px; background: var(--bg-elevated); border: 1px solid var(--border); 
            color: var(--text-primary); border-radius: var(--radius-md); font-size: 0.95rem; font-family: var(--font);
            outline: none; transition: var(--transition); box-sizing: border-box;
        }
        .form-select {
            appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2394a3b8' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
            background-repeat: no-repeat; background-position: right 12px center; cursor: pointer;
        }
        .form-select:disabled { opacity: 0.5; cursor: not-allowed; background: rgba(255,255,255,0.02); border-color: transparent;}
        .form-select:focus, .form-input:focus { border-color: var(--emerald); box-shadow: 0 0 0 3px var(--emerald-glow); background: var(--bg-hover); }
        
        .or-divider { text-align: center; color: var(--text-muted); font-size: 0.75rem; font-weight: 700; padding-top: 25px; text-transform: uppercase; letter-spacing: 0.1em;}

        .btn-group { display: flex; gap: 10px; }
        .btn-filter { 
            background: var(--emerald); color: #000; border: none; padding: 12px 24px; 
            border-radius: var(--radius-md); cursor: pointer; font-weight: 700; font-family: var(--font);
            transition: var(--transition); font-size: 0.95rem; flex: 1; display: inline-flex; align-items: center; justify-content: center; gap: 8px;
        }
        .btn-filter:hover { background: #34d399; box-shadow: 0 4px 15px var(--emerald-glow); transform: translateY(-1px); }
        
        .btn-reset { 
            background: var(--bg-elevated); color: var(--text-secondary); border: 1px solid var(--border); 
            padding: 12px 24px; border-radius: var(--radius-md); text-decoration: none; display: inline-flex;
            align-items: center; justify-content: center; font-weight: 700; font-family: var(--font); transition: var(--transition);
        }
        .btn-reset:hover { background: var(--bg-hover); color: #fff; border-color: var(--text-muted); }

        /* ─── DATA TABLE ─── */
        .table-container { 
            background: var(--bg-surface); border-radius: var(--radius-xl); 
            overflow-x: auto; border: 1px solid var(--border); position: relative; box-shadow: var(--shadow-md);
        }
        /* Custom Scrollbar */
        .table-container::-webkit-scrollbar { height: 8px; }
        .table-container::-webkit-scrollbar-track { background: var(--bg-surface); }
        .table-container::-webkit-scrollbar-thumb { background: #334155; border-radius: 4px; }
        
        .cost-table { width: 100%; border-collapse: collapse; min-width: 1300px; }
        .cost-table th { 
            background: var(--bg-elevated); padding: 16px; text-align: left; color: var(--text-muted); 
            font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 1px solid var(--border); font-weight: 700;
        }
        .cost-table td { padding: 16px; border-bottom: 1px solid rgba(255,255,255,0.03); color: var(--text-primary); vertical-align: top; font-size: 0.95rem; }
        .cost-table tr:hover { background: rgba(255,255,255,0.02); }
        
        /* Table Columns */
        .cost-col { font-family: var(--font-mono); font-weight: 600; text-align: right; }
        .total-col-op { color: var(--blue); font-weight: 700; text-align: right; background: rgba(59, 130, 246, 0.05); }
        .total-col-final { color: var(--emerald); font-weight: 800; text-align: right; background: rgba(16, 185, 129, 0.05); font-size: 1.1rem; border-left: 2px solid var(--border); }
        
        /* Badges & Elements */
        .tag-pill { background: var(--bg-elevated); color: #fff; padding: 4px 10px; border-radius: 6px; font-weight: 700; font-family: var(--font-mono); border: 1px solid var(--border);}
        .detail-row { font-size: 0.85rem; color: var(--text-secondary); margin-top: 6px; display: flex; align-items: center; gap: 6px; white-space: nowrap;}
        
        /* Breakdown Tooltip/Mini-table */
        .breakdown-grid { 
            display: grid; grid-template-columns: 1fr auto; gap: 6px; font-size: 0.8rem; color: var(--text-secondary); 
            margin-top: 8px; padding-top: 8px; border-top: 1px dashed rgba(255,255,255,0.1); 
        }
        .cost-val { text-align: right; color: var(--text-primary); font-family: var(--font-mono); font-weight: 600;}

        /* Empty State */
        .empty-state { text-align: center; padding: 4rem 2rem; color: var(--text-muted); font-style: italic; }

        /* ─── MOBILE SWIPE ANIMATION OVERLAY ─── */
        .scroll-hint-overlay {
            position: fixed; top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(15, 23, 42, 0.85); backdrop-filter: blur(5px);
            display: none; flex-direction: column; align-items: center; justify-content: center;
            z-index: 9999; color: #fff; transition: opacity 0.4s ease; pointer-events: none;
        }
        .scroll-hint-icon {
            font-size: 3rem; display: inline-block; animation: swipeHand 1.8s infinite ease-in-out;
            color: var(--emerald); filter: drop-shadow(0 4px 15px rgba(16,185,129,0.5));
        }
        .scroll-hint-text {
            margin-top: 1.5rem; font-weight: 700; font-size: 1.1rem; letter-spacing: 0.05em; color: #fff;
            background: var(--bg-elevated); padding: 12px 24px; border-radius: 99px;
            border: 1px solid rgba(255,255,255,0.1); box-shadow: 0 10px 25px rgba(0,0,0,0.5); font-family: var(--font);
        }
        
        @keyframes swipeHand {
            0% { transform: translateX(40px) rotate(-15deg); opacity: 0; }
            20% { opacity: 1; }
            80% { opacity: 1; }
            100% { transform: translateX(-40px) rotate(-15deg); opacity: 0; }
        }

        /* ─── RESPONSIVE ─── */
        @media (max-width: 768px) {
            .container { padding: 1rem; }
            .filter-bar { grid-template-columns: 1fr; gap: 1rem; }
            .btn-group { flex-direction: column; }
            .btn-filter, .btn-reset { width: 100%; height: auto;}
            .or-divider { padding-top: 10px; }
            .page-header h1 { font-size: 2rem; }
        }
    </style>
</head>
<body>

<div class="scroll-hint-overlay" id="mobileScrollHint">
    <div class="scroll-hint-icon"><i class="fa-solid fa-hand-pointer"></i></div>
    <div class="scroll-hint-text">Swipe left on the table for financial details</div>
</div>

<div class="container">
    
    <div class="top-bar">
        <a href="costing_dashboard.php" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back to Costing Dashboard
        </a>
        <span class="page-badge"><i class="fa-solid fa-money-bill-trend-up"></i> Financial Overview</span>
    </div>

    <header class="page-header">
        <h1 class="page-title">Animal <span>Net Worth</span></h1>
        <p>Comprehensive financial breakdown of livestock value and operating expenses.</p>
    </header>

    <div class="dashboard-grid">
        <div class="stat-card stat-green">
            <div class="stat-header">
                <div class="stat-title">Total Net Worth</div>
                <div class="stat-icon"><i class="fa-solid fa-sack-dollar"></i></div>
            </div>
            <div class="stat-value">₱<?php echo number_format($total_net_worth, 2); ?></div>
        </div>
        
        <div class="stat-card stat-blue">
            <div class="stat-header">
                <div class="stat-title">Operating Expenses</div>
                <div class="stat-icon"><i class="fa-solid fa-money-bill-wave"></i></div>
            </div>
            <div class="stat-value">₱<?php echo number_format($total_operating, 2); ?></div>
        </div>
        
        <div class="stat-card stat-purple">
            <div class="stat-header">
                <div class="stat-title">Total Acquisition</div>
                <div class="stat-icon"><i class="fa-solid fa-cart-shopping"></i></div>
            </div>
            <div class="stat-value">₱<?php echo number_format($total_acquisition, 2); ?></div>
        </div>
        
        <div class="stat-card stat-yellow">
            <div class="stat-header">
                <div class="stat-title">Feed Consumed</div>
                <div class="stat-icon"><i class="fa-solid fa-wheat-awn"></i></div>
            </div>
            <div class="stat-value">₱<?php echo number_format($total_feed, 2); ?></div>
        </div>
        
        <div class="stat-card stat-pink">
            <div class="stat-header">
                <div class="stat-title">Medical & Health</div>
                <div class="stat-icon"><i class="fa-solid fa-syringe"></i></div>
            </div>
            <div class="stat-value">₱<?php echo number_format($total_health, 2); ?></div>
        </div>

        <div class="stat-card stat-indigo">
            <div class="stat-header">
                <div class="stat-title">Misc. Fees</div>
                <div class="stat-icon"><i class="fa-solid fa-file-invoice-dollar"></i></div>
            </div>
            <div class="stat-value">₱<?php echo number_format($total_misc, 2); ?></div>
        </div>
    </div>

    <form class="filter-bar" method="GET">
        <div class="form-group">
            <label>1. Location</label>
            <select name="location_id" id="location_id" class="form-select" onchange="loadBuildings()">
                <option value="">-- All Locations --</option>
                <?php foreach ($locations as $loc): ?>
                    <option value="<?php echo $loc['LOCATION_ID']; ?>" <?php echo $location_id == $loc['LOCATION_ID'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($loc['LOCATION_NAME']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="form-group">
            <label>2. Building</label>
            <select name="building_id" id="building_id" class="form-select" onchange="loadPens()" <?php echo empty($location_id) ? 'disabled' : ''; ?>>
                <option value="">-- All Buildings --</option>
            </select>
        </div>

        <div class="form-group">
            <label>3. Pen</label>
            <select name="pen_id" id="pen_id" class="form-select" onchange="loadAnimals()" <?php echo empty($building_id) ? 'disabled' : ''; ?>>
                <option value="">-- All Pens --</option>
            </select>
        </div>

        <div class="form-group">
            <label>4. Select Animal (Tag No)</label>
            <select name="animal_id" id="animal_id" class="form-select" <?php echo empty($pen_id) ? 'disabled' : ''; ?>>
                <option value="">-- Select Specific Tag --</option>
            </select>
        </div>

        <div class="or-divider">OR DIRECT SEARCH</div>

        <div class="form-group">
            <label>Search (Tag No or Type)</label>
            <input type="text" name="search" class="form-input" placeholder="e.g. A001" value="<?php echo htmlspecialchars($search); ?>">
        </div>

        <div class="btn-group">
            <button type="submit" class="btn-filter"><i class="fa-solid fa-filter"></i> Filter</button>
            <a href="animal_cost.php" class="btn-reset"><i class="fa-solid fa-rotate-right"></i> Reset</a>
        </div>
    </form>

    <div class="table-container">
        <table class="cost-table">
            <thead>
                <tr>
                    <th>Animal Details</th>
                    <th style="text-align:right;">Acquisition</th>
                    <th style="text-align:right;">Feed Cost</th>
                    <th style="text-align:right;">Health Breakdown</th>
                    <th style="text-align:right;">Misc Fees</th>
                    <th style="text-align:right; color:var(--blue);">Operating Cost<br><span style="font-size:0.8em; font-weight:normal; color:var(--text-muted);">(Without Acquisition)</span></th>
                    <th style="text-align:right; color:var(--emerald);">Total Net Worth<br><span style="font-size:0.8em; font-weight:normal; color:var(--text-muted);">(With Acquisition)</span></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($data)): ?>
                    <tr>
                        <td colspan="7" class="empty-state">
                            <i class="fa-solid fa-ghost" style="font-size: 2.5rem; display: block; margin-bottom: 1rem; opacity: 0.5;"></i>
                            No animals found matching your criteria.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($data as $row): 
                        $health_subtotal = $row['COST_MEDS'] + $row['COST_VACCINE'] + $row['COST_VITAMINS'] + $row['COST_CHECKUP'];
                        $operating_total = $row['COST_FEED'] + $health_subtotal + $row['COST_MISC'];
                        $grand_total = $operating_total + $row['COST_ACQUISITION'];
                    ?>
                    <tr>
                        <td>
                            <div style="display:flex; align-items:center; gap:8px; margin-bottom:6px;">
                                <span class="tag-pill"><i class="fa-solid fa-tag me-1"></i> <?php echo $row['TAG_NO']; ?></span>
                                <span style="font-weight:700; color:#fff;"><?php echo $row['ANIMAL_TYPE_NAME']; ?></span>
                            </div>
                            <div class="detail-row">
                                <i class="fa-solid fa-location-dot"></i> 
                                <?php echo $row['LOCATION_NAME']; ?> &bull; 
                                <?php echo $row['BUILDING_NAME'] ?? '-'; ?> &bull; 
                                <?php echo $row['PEN_NAME'] ?? '-'; ?>
                            </div>
                            <div class="detail-row" style="color:<?php echo $row['CURRENT_STATUS']=='Active'?'var(--emerald)':'var(--red)'; ?>; font-weight:600;">
                                <?php if ($row['CURRENT_STATUS'] == 'Active'): ?>
                                    <i class="fa-solid fa-circle-check"></i>
                                <?php else: ?>
                                    <i class="fa-solid fa-circle-xmark"></i>
                                <?php endif; ?>
                                <?php echo $row['CURRENT_STATUS']; ?>
                            </div>
                        </td>
                        
                        <td class="cost-col" style="color:var(--purple);">
                            ₱<?php echo number_format($row['COST_ACQUISITION'], 2); ?>
                        </td>
                        
                        <td class="cost-col" style="color:var(--amber);">
                            ₱<?php echo number_format($row['COST_FEED'], 2); ?>
                        </td>
                        
                        <td class="cost-col">
                            <div style="font-weight:700; color:var(--pink); font-size:1.05rem;">₱<?php echo number_format($health_subtotal, 2); ?></div>
                            <div class="breakdown-grid">
                                <span>Checkup:</span> <span class="cost-val"><?php echo number_format($row['COST_CHECKUP'], 2); ?></span>
                                <span>Meds:</span> <span class="cost-val"><?php echo number_format($row['COST_MEDS'], 2); ?></span>
                                <span>Vaccine:</span> <span class="cost-val"><?php echo number_format($row['COST_VACCINE'], 2); ?></span>
                                <span>Vitamins:</span> <span class="cost-val"><?php echo number_format($row['COST_VITAMINS'], 2); ?></span>
                            </div>
                        </td>

                        <td class="cost-col" style="color:var(--indigo);">
                            ₱<?php echo number_format($row['COST_MISC'], 2); ?>
                        </td>

                        <td class="cost-col total-col-op">
                            ₱<?php echo number_format($operating_total, 2); ?>
                        </td>

                        <td class="cost-col total-col-final">
                            ₱<?php echo number_format($grand_total, 2); ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    // --- Mobile Swipe Animation Logic ---
    document.addEventListener("DOMContentLoaded", () => {
        const tableContainer = document.querySelector('.table-container');
        const scrollHint = document.getElementById('mobileScrollHint');
        const hasData = <?php echo count($data) > 0 ? 'true' : 'false'; ?>;

        // Only show if width <= 925px AND there is table data to scroll
        if (window.innerWidth <= 925 && hasData) {
            scrollHint.style.display = 'flex';

            const dismissHint = () => {
                scrollHint.style.opacity = '0';
                setTimeout(() => {
                    scrollHint.style.display = 'none';
                }, 400); // Wait for CSS transition
                
                // Cleanup listeners
                tableContainer.removeEventListener('scroll', dismissHint);
                window.removeEventListener('touchstart', dismissHint);
                window.removeEventListener('click', dismissHint);
            };

            // Auto dismiss after 3 seconds
            setTimeout(dismissHint, 3000);

            // Instant dismiss if user starts interacting
            tableContainer.addEventListener('scroll', dismissHint, { once: true });
            window.addEventListener('touchstart', dismissHint, { once: true });
            window.addEventListener('click', dismissHint, { once: true });
        }
    });

    // --- Select Data Logic ---
    const selectedLocation = "<?php echo $location_id; ?>";
    const selectedBuilding = "<?php echo $building_id; ?>";
    const selectedPen = "<?php echo $pen_id; ?>";
    const selectedAnimal = "<?php echo $animal_id; ?>";

    document.addEventListener('DOMContentLoaded', async () => {
        if (selectedLocation) {
            await loadBuildings();
            if (selectedBuilding) {
                document.getElementById('building_id').value = selectedBuilding;
                await loadPens();
                if (selectedPen) {
                    document.getElementById('pen_id').value = selectedPen;
                    await loadAnimals();
                    if(selectedAnimal) {
                        document.getElementById('animal_id').value = selectedAnimal;
                    }
                }
            }
        }
    });

    async function fetchData(url) {
        try {
            const res = await fetch(url);
            return await res.json();
        } catch(e) { console.error(e); return []; }
    }

    async function loadBuildings() {
        const locId = document.getElementById('location_id').value;
        const buildSelect = document.getElementById('building_id');
        const penSelect = document.getElementById('pen_id');
        const animalSelect = document.getElementById('animal_id');

        buildSelect.innerHTML = '<option value="">Loading...</option>';
        penSelect.innerHTML = '<option value="">-- All Pens --</option>';
        animalSelect.innerHTML = '<option value="">-- Select Specific Tag --</option>';
        
        buildSelect.disabled = true; 
        penSelect.disabled = true;
        animalSelect.disabled = true;

        if (locId) {
            const data = await fetchData(`animal_cost.php?action=get_buildings&location_id=${locId}`);
            buildSelect.innerHTML = '<option value="">-- All Buildings --</option>';
            data.forEach(b => {
                buildSelect.innerHTML += `<option value="${b.BUILDING_ID}">${b.BUILDING_NAME}</option>`;
            });
            buildSelect.disabled = false;
        } else {
            buildSelect.innerHTML = '<option value="">-- All Buildings --</option>';
        }
    }

    async function loadPens() {
        const buildId = document.getElementById('building_id').value;
        const penSelect = document.getElementById('pen_id');
        const animalSelect = document.getElementById('animal_id');

        penSelect.innerHTML = '<option value="">Loading...</option>';
        animalSelect.innerHTML = '<option value="">-- Select Specific Tag --</option>';
        penSelect.disabled = true;
        animalSelect.disabled = true;

        if (buildId) {
            const data = await fetchData(`animal_cost.php?action=get_pens&building_id=${buildId}`);
            penSelect.innerHTML = '<option value="">-- All Pens --</option>';
            data.forEach(p => {
                penSelect.innerHTML += `<option value="${p.PEN_ID}">${p.PEN_NAME}</option>`;
            });
            penSelect.disabled = false;
        } else {
            penSelect.innerHTML = '<option value="">-- All Pens --</option>';
        }
    }

    async function loadAnimals() {
        const penId = document.getElementById('pen_id').value;
        const animalSelect = document.getElementById('animal_id');

        animalSelect.innerHTML = '<option value="">Loading...</option>';
        animalSelect.disabled = true;

        if (penId) {
            const data = await fetchData(`animal_cost.php?action=get_animals&pen_id=${penId}`);
            animalSelect.innerHTML = '<option value="">-- Select Specific Tag --</option>';
            data.forEach(a => {
                animalSelect.innerHTML += `<option value="${a.ANIMAL_ID}">${a.TAG_NO}</option>`;
            });
            animalSelect.disabled = false;
        } else {
            animalSelect.innerHTML = '<option value="">-- Select Specific Tag --</option>';
        }
    }
</script>

</body>
</html>