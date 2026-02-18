<?php
// reports/animal_report.php
error_reporting(0);
ini_set('display_errors', 0);
$page = "reports";
include '../config/Connection.php';

include '../security/checkAccess.php';
checkAccess('animal_report');
include '../common/navbar.php';


// --- 1. GET FILTER INPUTS ---
$view        = $_GET['view'] ?? 'detailed'; 
$date_from   = $_GET['date_from'] ?? '';
$date_to     = $_GET['date_to'] ?? '';
$status      = $_GET['status'] ?? ''; 
$animal_type = $_GET['animal_type'] ?? '';
$breed       = $_GET['breed'] ?? '';
$stage       = $_GET['stage'] ?? ''; 
$sex         = $_GET['sex'] ?? '';

// Mapped filters for drill-down (Location/Building/Pen)
$filter_loc  = $_GET['f_loc'] ?? '';
$filter_bld  = $_GET['f_bld'] ?? ''; // Used when drilling down from Building -> Pen
$filter_pen  = $_GET['f_pen'] ?? '';

// --- PAGINATION SETTINGS (Only for Detailed View) ---
$limit = 50; 
$page_no = isset($_GET['page_no']) ? (int)$_GET['page_no'] : 1;
$offset = ($page_no - 1) * $limit;

try {
    if (!isset($conn)) { throw new Exception("Database connection failed."); }

    // --- 2. BUILD BASE WHERE CLAUSE ---
    $where_sql = " WHERE ar.IS_ACTIVE IN (0, 1) ";
    $params = [];

    // Apply Standard Filters
    if ($date_from && $date_to) {
        $where_sql .= " AND ar.BIRTH_DATE BETWEEN :date_from AND :date_to";
        $params[':date_from'] = $date_from;
        $params[':date_to']   = $date_to;
    }

    if ($status) {
        if ($status === 'Active') {
            $where_sql .= " AND ar.IS_ACTIVE = 1";
        } elseif ($status === 'Inactive') {
            $where_sql .= " AND ar.IS_ACTIVE = 0";
        } else {
            $where_sql .= " AND ar.CURRENT_STATUS = :status";
            $params[':status'] = $status;
        }
    }

    if ($animal_type) { $where_sql .= " AND ar.ANIMAL_TYPE_ID = :atype"; $params[':atype'] = $animal_type; }
    if ($breed)       { $where_sql .= " AND ar.BREED_ID = :breed"; $params[':breed'] = $breed; }
    if ($stage)       { $where_sql .= " AND ar.CLASS_ID = :stage"; $params[':stage'] = $stage; }
    if ($sex)         { $where_sql .= " AND ar.SEX = :sex"; $params[':sex'] = $sex; }

    // Apply Location/Building Filters (Important for Drill-down)
    if ($filter_loc) { $where_sql .= " AND ar.LOCATION_ID = :floc"; $params[':floc'] = $filter_loc; }
    if ($filter_bld) { $where_sql .= " AND ar.BUILDING_ID = :fbld"; $params[':fbld'] = $filter_bld; }
    if ($filter_pen) { $where_sql .= " AND ar.PEN_ID = :fpen"; $params[':fpen'] = $filter_pen; }

    // --- 3. FETCH GLOBAL STATS ---
    $stats_sql = "SELECT 
                    COUNT(*) as total_heads,
                    SUM(ar.ACQUISITION_COST) as total_value,
                    SUM(ar.CURRENT_ESTIMATED_WEIGHT) as total_weight,
                    SUM(CASE WHEN ar.SEX = 'M' THEN 1 ELSE 0 END) as male_count,
                    SUM(CASE WHEN ar.SEX = 'F' THEN 1 ELSE 0 END) as female_count
                  FROM ANIMAL_RECORDS ar 
                  $where_sql";
    
    $stmt_stats = $conn->prepare($stats_sql);
    $stmt_stats->execute($params);
    $stats = $stmt_stats->fetch(PDO::FETCH_ASSOC);

    // Type Breakdown
    $type_sql = "SELECT at.ANIMAL_TYPE_NAME, COUNT(*) as count
                 FROM ANIMAL_RECORDS ar
                 LEFT JOIN ANIMAL_TYPE at ON ar.ANIMAL_TYPE_ID = at.ANIMAL_TYPE_ID
                 $where_sql
                 GROUP BY at.ANIMAL_TYPE_NAME";
    $stmt_type = $conn->prepare($type_sql);
    $stmt_type->execute($params);
    $type_breakdown = $stmt_type->fetchAll(PDO::FETCH_KEY_PAIR);

    // --- 4. FETCH DATA ROWS ---
    
    if ($view === 'detailed') {
        // DETAILED VIEW (Paginated)
        $sql = "SELECT 
                ar.*,
                at.ANIMAL_TYPE_NAME, b.BREED_NAME, ac.STAGE_NAME,
                l.LOCATION_NAME, bld.BUILDING_NAME, p.PEN_NAME,
                m.TAG_NO as MOTHER_TAG,
                DATE_FORMAT(ar.BIRTH_DATE, '%Y-%m-%d') as BIRTH_DATE_FMT
            FROM ANIMAL_RECORDS ar
            LEFT JOIN ANIMAL_TYPE at ON ar.ANIMAL_TYPE_ID = at.ANIMAL_TYPE_ID
            LEFT JOIN BREEDS b ON ar.BREED_ID = b.BREED_ID
            LEFT JOIN ANIMAL_CLASSIFICATIONS ac ON ar.CLASS_ID = ac.CLASS_ID
            LEFT JOIN LOCATIONS l ON ar.LOCATION_ID = l.LOCATION_ID
            LEFT JOIN BUILDINGS bld ON ar.BUILDING_ID = bld.BUILDING_ID
            LEFT JOIN PENS p ON ar.PEN_ID = p.PEN_ID
            LEFT JOIN ANIMAL_RECORDS m ON ar.MOTHER_ID = m.ANIMAL_ID
            $where_sql
            ORDER BY l.LOCATION_NAME ASC, bld.BUILDING_NAME ASC, p.PEN_NAME ASC, ar.TAG_NO ASC
            LIMIT :limit OFFSET :offset";
        
        $stmt = $conn->prepare($sql);
        foreach($params as $key => $val) { $stmt->bindValue($key, $val); }
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
        $stmt->execute();
        $animals = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } else {
        // SUMMARY VIEWS (Building or Pen) - Fetch All for Aggregation
        $sql = "SELECT 
                ar.*, at.ANIMAL_TYPE_NAME, b.BREED_NAME, ac.STAGE_NAME,
                l.LOCATION_NAME, bld.BUILDING_NAME, p.PEN_NAME
            FROM ANIMAL_RECORDS ar
            LEFT JOIN ANIMAL_TYPE at ON ar.ANIMAL_TYPE_ID = at.ANIMAL_TYPE_ID
            LEFT JOIN BREEDS b ON ar.BREED_ID = b.BREED_ID
            LEFT JOIN ANIMAL_CLASSIFICATIONS ac ON ar.CLASS_ID = ac.CLASS_ID
            LEFT JOIN LOCATIONS l ON ar.LOCATION_ID = l.LOCATION_ID
            LEFT JOIN BUILDINGS bld ON ar.BUILDING_ID = bld.BUILDING_ID
            LEFT JOIN PENS p ON ar.PEN_ID = p.PEN_ID
            $where_sql
            ORDER BY l.LOCATION_NAME, bld.BUILDING_NAME, p.PEN_NAME";
            
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $animals = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // --- 5. PROCESS SUMMARY DATA ---
    $grouped_data = [];
    if ($view !== 'detailed') {
        foreach ($animals as $row) {
            // Determine grouping key and ID for linking
            if ($view === 'building') {
                $group_key = $row['BUILDING_NAME'] ?: 'Unassigned Building';
                $group_id = $row['BUILDING_ID']; // Needed for drill-down link
            } else {
                // View is 'pen'
                $group_key = $row['PEN_NAME'] ?: 'Unassigned Pen';
                $group_id = $row['PEN_ID'];
            }
            
            if (!isset($grouped_data[$group_key])) {
                $grouped_data[$group_key] = [ 
                    'name' => $group_key, 
                    'id' => $group_id,
                    'count' => 0, 
                    'cost' => 0, 
                    'classifications' => [], 
                    'items' => [] 
                ];
            }
            $grouped_data[$group_key]['count']++;
            $grouped_data[$group_key]['cost'] += $row['ACQUISITION_COST'];
            $grouped_data[$group_key]['items'][] = $row;
            
            $c_name = $row['STAGE_NAME'] ?: 'Unclassified';
            if (!isset($grouped_data[$group_key]['classifications'][$c_name])) { $grouped_data[$group_key]['classifications'][$c_name] = 0; }
            $grouped_data[$group_key]['classifications'][$c_name]++;
        }
        ksort($grouped_data);
    }

    // --- 6. DROPDOWNS ---
    $types = $conn->query("SELECT * FROM ANIMAL_TYPE ORDER BY ANIMAL_TYPE_NAME")->fetchAll();
    $breeds_list = $conn->query("SELECT * FROM BREEDS ORDER BY BREED_NAME")->fetchAll();
    $stages_list = $conn->query("SELECT * FROM ANIMAL_CLASSIFICATIONS ORDER BY CLASS_ID")->fetchAll();

    // Calculate Total Pages
    $total_pages = ceil($stats['total_heads'] / $limit);

} catch (Exception $e) {
    $animals = [];
    $stats = [];
    error_log($e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Advanced Animal Report</title>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.31/jspdf.plugin.autotable.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" integrity="sha512-9usAa10IRO0HhonpyAIVpjrylPvoDwiPUiKdWk5t3PyolY1cOd4DSE0Ga+ri4AuTroPR5aQvXU9xC6qOPnzFeg==" crossorigin="anonymous" referrerpolicy="no-referrer" />

    <style>
        /* --- GLOBAL STYLES --- */
        body { 
            font-family: system-ui, -apple-system, sans-serif; 
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); 
            color: #e2e8f0; 
            margin: 0; 
            padding-bottom: 40px;
        }
        .container { max-width: 1600px; margin: 0 auto; padding: 2rem; }
        
        /* Back Link Style */
        .back-link {
            display: inline-flex; align-items: center; gap: 8px; 
            text-decoration: none; color: #94a3b8; font-weight: 600; 
            font-size: 0.95rem; margin-bottom: 20px; transition: color 0.2s;
        }
        .back-link:hover { color: white; }

        .header { text-align: center; margin-bottom: 2rem; }
        .title { 
            font-size: 2.2rem; font-weight: 800; 
            background: linear-gradient(135deg, #22c55e, #16a34a); 
            -webkit-background-clip: text; -webkit-text-fill-color: transparent; 
            margin-bottom: 0.5rem;
        }
        .subtitle { color: #94a3b8; font-size: 1rem; margin: 0; }
        
        /* --- STATS CARDS --- */
        .stats-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); 
            gap: 1.5rem; 
            margin-bottom: 2rem; 
        }
        .stat-card { 
            background: rgba(30, 41, 59, 0.6); 
            border: 1px solid rgba(255,255,255,0.1); 
            border-radius: 16px; 
            padding: 1.5rem; 
            text-align: center; 
            backdrop-filter: blur(10px); 
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        .stat-val { font-size: 1.8rem; font-weight: 800; margin-bottom: 0.25rem; color: #fff; }
        .stat-lbl { color: #94a3b8; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1px; font-weight: 600; }
        .text-green { color: #4ade80; } .text-gold { color: #fbbf24; } .text-blue { color: #60a5fa; }

        .type-list { list-style: none; padding: 0; margin: 0; text-align: left; font-size: 0.85rem; }
        .type-list li { display: flex; justify-content: space-between; padding: 2px 0; border-bottom: 1px solid rgba(255,255,255,0.05); }
        .type-list li:last-child { border-bottom: none; }
        .type-name { color: #cbd5e1; }
        .type-count { color: #fff; font-weight: bold; }

        /* --- FILTER BAR --- */
        .filter-box { 
            background: rgba(15, 23, 42, 0.6); 
            border: 1px solid #334155; 
            padding: 1.5rem; 
            border-radius: 16px; 
            margin-bottom: 2rem; 
        }
        .filter-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); 
            gap: 1rem; 
            align-items: end; 
        }
        .form-group label { display: block; font-size: 0.75rem; color: #94a3b8; margin-bottom: 0.4rem; font-weight: 600; text-transform: uppercase; }
        .form-input { 
            width: 100%; padding: 10px; background: #0f172a; 
            border: 1px solid #334155; color: white; border-radius: 8px; 
            font-size: 0.9rem;
            box-sizing: border-box; 
        }
        .form-input:focus { border-color: #22c55e; outline: none; }
        
        .btn-group { display: flex; gap: 10px; flex-wrap: wrap; }
        .action-bar { 
            margin-top: 1.5rem; display: flex; gap: 10px; 
            justify-content: flex-end; flex-wrap: wrap;
            border-top: 1px solid rgba(255,255,255,0.1); padding-top: 1rem; 
        }
        .btn { 
            padding: 10px 20px; border: none; border-radius: 8px; 
            font-weight: 600; cursor: pointer; display: inline-flex; 
            align-items: center; gap: 8px; text-decoration: none; 
            font-size: 0.9rem; transition: transform 0.1s; white-space: nowrap;
        }
        .btn:active { transform: scale(0.98); }
        .btn-primary { background: #22c55e; color: white; }
        .btn-outline { background: transparent; border: 1px solid #475569; color: #cbd5e1; }
        
        /* Export Buttons (Updated Colors & Icons) */
        .btn-pdf { background: #3b82f6; color: white; } /* Blue */
        .btn-excel { background: #10b981; color: white; } /* Green */
        .btn-csv { background: #f59e0b; color: white; } /* Orange */

        /* --- TABLE & GROUPING --- */
        .table-wrap { background: rgba(30, 41, 59, 0.5); border-radius: 16px; overflow: hidden; border: 1px solid #334155; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 1000px; }
        th { background: rgba(15, 23, 42, 0.9); color: #94a3b8; text-align: left; padding: 1rem; font-size: 0.8rem; text-transform: uppercase; border-bottom: 1px solid #334155; white-space: nowrap; }
        td { padding: 1rem; border-bottom: 1px solid rgba(255,255,255,0.05); font-size: 0.9rem; color: #e2e8f0; }
        tr:last-child td { border-bottom: none; }
        
        .group-header-row { background: rgba(34, 197, 94, 0.15); font-weight: bold; color: #4ade80; border-top: 1px solid #334155; }
        .group-header-row td { padding: 0.75rem 1rem; }
        .sub-group-header-row { background: rgba(30, 41, 59, 0.9); font-weight: 600; color: #94a3b8; border-top: 1px solid #334155; }
        .sub-group-header-row td { padding: 0.5rem 1rem 0.5rem 2rem; font-size: 0.85rem; font-style: italic; }

        .badge { padding: 4px 10px; border-radius: 12px; font-size: 0.75rem; font-weight: 600; display: inline-block;}
        .b-active { background: rgba(34, 197, 94, 0.15); color: #4ade80; }
        .b-sold { background: rgba(251, 191, 36, 0.15); color: #fbbf24; }
        .b-dec { background: rgba(239, 68, 68, 0.15); color: #f87171; }
        .val-money { font-family: monospace; color: #fbbf24; font-weight: bold; }
        .val-weight { font-family: monospace; color: #60a5fa; font-weight: bold; }

        /* --- SUMMARY VIEW CARDS --- */
        .group-card { background: rgba(30, 41, 59, 0.6); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 16px; margin-bottom: 2rem; overflow: hidden; }
        .group-header { padding: 1.5rem; background: rgba(15, 23, 42, 0.8); border-bottom: 1px solid #334155; display: flex; justify-content: space-between; align-items: center; }
        .group-title { font-size: 1.5rem; font-weight: bold; color: #22c55e; }
        .group-stats-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1px; background: #334155; }
        .group-mini-stat { background: rgba(30, 41, 59, 0.9); padding: 1.5rem; text-align: center; }
        .mini-val { font-size: 1.5rem; font-weight: 800; color: #fff; margin-bottom: 5px; }
        .mini-lbl { font-size: 0.75rem; text-transform: uppercase; color: #94a3b8; }
        .class-breakdown { font-size: 0.85rem; color: #cbd5e1; text-align: left; }
        .class-item { display: flex; justify-content: space-between; border-bottom: 1px dashed rgba(255,255,255,0.1); padding: 2px 0; }
        
        .btn-view-pens { 
            background: rgba(34, 197, 94, 0.1); border: 1px solid #22c55e; color: #22c55e;
            padding: 8px 16px; border-radius: 6px; text-decoration: none; font-size: 0.85rem; font-weight: 600;
            transition: all 0.2s;
        }
        .btn-view-pens:hover { background: #22c55e; color: #fff; }

        /* --- PAGINATION --- */
        .pagination { display: flex; justify-content: center; gap: 5px; margin-top: 20px; }
        .page-link { 
            padding: 8px 12px; border-radius: 6px; background: rgba(15, 23, 42, 0.6); 
            border: 1px solid #334155; color: #cbd5e1; text-decoration: none; font-size: 0.9rem;
        }
        .page-link:hover { background: #334155; color: white; }
        .page-link.active { background: #22c55e; color: white; border-color: #22c55e; }
    </style>
</head>
<body>

<div class="container">
    
    <a href="reports.php" class="back-link">
        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
        Back to Reports Dashboard
    </a>

    <div class="header">
        <h1 class="title">Animal Inventory Report</h1>
        <p class="subtitle">Comprehensive livestock analysis and metrics.</p>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-lbl">Total Heads Filtered</div>
            <div class="stat-val"><?= number_format($stats['total_heads']) ?></div>
        </div>
        
        <div class="stat-card">
            <div class="stat-lbl" style="margin-bottom: 10px;">Animals by Type</div>
            <ul class="type-list">
                <?php foreach($type_breakdown as $tname => $tcount): ?>
                <li>
                    <span class="type-name"><?= htmlspecialchars($tname) ?></span>
                    <span class="type-count"><?= number_format($tcount) ?></span>
                </li>
                <?php endforeach; ?>
                <?php if(empty($type_breakdown)): ?>
                    <li style="justify-content: center; color: #64748b;">No data</li>
                <?php endif; ?>
            </ul>
        </div>

        <div class="stat-card">
            <div class="stat-lbl">Total Inventory Value</div>
            <div class="stat-val text-gold">₱<?= number_format($stats['total_value'], 2) ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-lbl">Females / Males</div>
            <div class="stat-val text-green"><?= $stats['female_count'] ?> / <?= $stats['male_count'] ?></div>
        </div>
    </div>

    <div class="filter-box">
        <form method="GET">
            <?php if($filter_loc): ?><input type="hidden" name="f_loc" value="<?= htmlspecialchars($filter_loc) ?>"><?php endif; ?>
            <?php if($filter_bld): ?><input type="hidden" name="f_bld" value="<?= htmlspecialchars($filter_bld) ?>"><?php endif; ?>

            <div class="filter-grid">
                <div class="form-group">
                    <label style="color: #22c55e;">Report View</label>
                    <select name="view" class="form-input" onchange="this.form.submit()" style="border-color: #22c55e;">
                        <option value="detailed" <?= $view == 'detailed' ? 'selected' : '' ?>>Detailed List</option>
                        <option value="building" <?= $view == 'building' ? 'selected' : '' ?>>Summary by Building</option>
                        <?php if($view == 'pen'): // Only show if active ?>
                            <option value="pen" selected>Summary by Pen</option>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Birth Date Range</label>
                    <div style="display: flex; gap: 5px;">
                        <input type="date" name="date_from" class="form-input" value="<?= htmlspecialchars($date_from) ?>">
                        <input type="date" name="date_to" class="form-input" value="<?= htmlspecialchars($date_to) ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Type & Breed</label>
                    <div style="display: flex; gap: 5px;">
                        <select name="animal_type" class="form-input">
                            <option value="">All Types</option>
                            <?php foreach($types as $t): ?>
                                <option value="<?= $t['ANIMAL_TYPE_ID'] ?>" <?= $animal_type == $t['ANIMAL_TYPE_ID']?'selected':'' ?>>
                                    <?= htmlspecialchars($t['ANIMAL_TYPE_NAME']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <select name="breed" class="form-input">
                            <option value="">All Breeds</option>
                            <?php foreach($breeds_list as $b): ?>
                                <option value="<?= $b['BREED_ID'] ?>" <?= $breed == $b['BREED_ID']?'selected':'' ?>>
                                    <?= htmlspecialchars($b['BREED_NAME']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Stage / Classification</label>
                    <select name="stage" class="form-input">
                        <option value="">All Stages</option>
                        <?php foreach($stages_list as $s): ?>
                            <option value="<?= $s['CLASS_ID'] ?>" <?= $stage == $s['CLASS_ID']?'selected':'' ?>>
                                <?= htmlspecialchars($s['STAGE_NAME']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Status</label>
                    <select name="status" class="form-input">
                        <option value="">All</option>
                        <option value="Active" <?= $status=='Active'?'selected':'' ?>>Active Herd</option>
                        <option value="Sold" <?= $status=='Sold'?'selected':'' ?>>Sold History</option>
                        <option value="Deceased" <?= $status=='Deceased'?'selected':'' ?>>Deceased/Cull</option>
                    </select>
                </div>
                
                <div class="btn-group">
                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                    <a href="animal_report.php" class="btn btn-outline">Reset</a>
                </div>
            </div>
            
            <div class="action-bar">
                <button type="button" class="btn btn-pdf" onclick="exportPDF()">
                    <i class="fa-solid fa-file-pdf"></i> PDF
                </button>
                <button type="button" class="btn btn-excel" onclick="exportExcel()">
                    <i class="fa-solid fa-file-excel"></i> Excel
                </button>
                <button type="button" class="btn btn-csv" onclick="exportCSV()">
                    <i class="fa-solid fa-file-csv"></i> CSV
                </button>
            </div>
        </form>
    </div>

    <?php if ($view === 'building'): ?>
        
        <h3 style="color:#94a3b8; margin-bottom:1rem;">Building Overview</h3>
        <?php foreach ($grouped_data as $group_name => $gdata): ?>
            <div class="group-card">
                <div class="group-header">
                    <div class="group-title"><?= htmlspecialchars($group_name) ?></div>
                    
                    <?php if($gdata['id']): ?>
                        <a href="?view=pen&f_bld=<?= $gdata['id'] ?>&status=<?= $status ?>" class="btn-view-pens">
                            View Pens ➔
                        </a>
                    <?php endif; ?>
                </div>
                
                <div class="group-stats-row">
                    <div class="group-mini-stat">
                        <div class="mini-val text-green"><?= number_format($gdata['count']) ?></div>
                        <div class="mini-lbl">Animals Here</div>
                    </div>
                    <div class="group-mini-stat">
                        <div class="mini-val text-gold">₱<?= number_format($gdata['cost'], 2) ?></div>
                        <div class="mini-lbl">Total Cost Value</div>
                    </div>
                    <div class="group-mini-stat" style="text-align: left; padding: 1rem 1.5rem;">
                        <div class="mini-lbl" style="margin-bottom: 5px;">Classifications</div>
                        <div class="class-breakdown">
                            <?php foreach ($gdata['classifications'] as $cname => $ccount): ?>
                                <div class="class-item">
                                    <span><?= htmlspecialchars($cname) ?></span>
                                    <span style="color:#fff; font-weight:bold;"><?= $ccount ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                </div>
        <?php endforeach; ?>

    <?php elseif ($view === 'pen'): ?>

        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
             <h3 style="color:#94a3b8; margin:0;">Pen Breakdown</h3>
             <a href="?view=building&status=<?= $status ?>" class="btn-outline" style="padding:6px 12px; border-radius:6px; text-decoration:none;">← Back to Buildings</a>
        </div>

        <?php foreach ($grouped_data as $group_name => $gdata): ?>
            <div class="group-card">
                <div class="group-header">
                    <div class="group-title"><?= htmlspecialchars($group_name) ?></div>
                    <div style="color: #94a3b8; font-size: 0.9rem;">Pen Summary</div>
                </div>
                
                <div class="group-stats-row">
                    <div class="group-mini-stat">
                        <div class="mini-val text-green"><?= number_format($gdata['count']) ?></div>
                        <div class="mini-lbl">Animals in Pen</div>
                    </div>
                    <div class="group-mini-stat">
                        <div class="mini-val text-gold">₱<?= number_format($gdata['cost'], 2) ?></div>
                        <div class="mini-lbl">Total Cost</div>
                    </div>
                    <div class="group-mini-stat" style="text-align: left; padding: 1rem 1.5rem;">
                        <div class="mini-lbl" style="margin-bottom: 5px;">Classifications</div>
                        <div class="class-breakdown">
                            <?php foreach ($gdata['classifications'] as $cname => $ccount): ?>
                                <div class="class-item">
                                    <span><?= htmlspecialchars($cname) ?></span>
                                    <span style="color:#fff; font-weight:bold;"><?= $ccount ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div style="padding: 1rem; background: rgba(15,23,42,0.4);">
                    <h4 style="margin: 0 0 10px 0; font-size: 0.9rem; color: #94a3b8; text-transform: uppercase;">Animals List</h4>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Tag No</th><th>Stage</th><th>Breed</th><th>Sex</th><th>Status</th><th>Birth Wt</th><th>Cur. Wt</th><th>Cost</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($gdata['items'] as $row): ?>
                                    <tr>
                                        <td style="font-weight:bold; color:#fff;"><?= htmlspecialchars($row['TAG_NO']) ?></td>
                                        <td><?= htmlspecialchars($row['STAGE_NAME']) ?></td>
                                        <td><?= htmlspecialchars($row['BREED_NAME']) ?></td>
                                        <td><?= $row['SEX'] ?></td>
                                        <td><?= htmlspecialchars($row['CURRENT_STATUS']) ?></td>
                                        <td style="text-align:right;"><?= $row['WEIGHT_AT_BIRTH'] ?></td>
                                        <td style="text-align:right;"><?= $row['CURRENT_ESTIMATED_WEIGHT'] ?></td>
                                        <td style="text-align:right;" class="text-gold">₱<?= number_format($row['ACQUISITION_COST'], 2) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

    <?php else: ?>
        
        <div class="table-wrap">
            <table id="reportTable">
                <thead>
                    <tr>
                        <th>Tag No</th>
                        <th>Classification</th>
                        <th>Breed</th>
                        <th>Sex</th>
                        <th>Status</th>
                        <th>Location</th>
                        <th>Mother</th>
                        <th style="text-align:right;">Birth Wt</th>
                        <th style="text-align:right;">Cur. Wt</th>
                        <th style="text-align:right;">Cost Value</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($animals)): ?>
                        <tr><td colspan="10" style="text-align:center; padding:3rem; color:#64748b;">No records found matching filters.</td></tr>
                    <?php else: ?>
                        <?php 
                        $last_building = '';
                        $last_pen = '';
                        
                        foreach($animals as $row): 
                            // Building Header
                            $curr_building = $row['BUILDING_NAME'] ?: 'Unassigned Building';
                            if ($curr_building !== $last_building) {
                                echo "<tr class='group-header-row'><td colspan='10'>🏢 Building: " . htmlspecialchars($curr_building) . "</td></tr>";
                                $last_building = $curr_building;
                                $last_pen = ''; 
                            }

                            // Pen Header
                            $curr_pen = $row['PEN_NAME'] ?: 'Unassigned Pen';
                            if ($curr_pen !== $last_pen) {
                                echo "<tr class='sub-group-header-row'><td colspan='10'>↳ Pen: " . htmlspecialchars($curr_pen) . "</td></tr>";
                                $last_pen = $curr_pen;
                            }

                            $statusClass = 'b-active';
                            if($row['CURRENT_STATUS'] == 'Sold') $statusClass = 'b-sold';
                            if(in_array($row['CURRENT_STATUS'], ['Deceased','Cull','Dead'])) $statusClass = 'b-dec';
                        ?>
                        <tr>
                            <td style="font-weight:bold; color:#fff; padding-left: 2rem;"><?= htmlspecialchars($row['TAG_NO']) ?></td>
                            <td>
                                <div><?= htmlspecialchars($row['STAGE_NAME'] ?? 'Unknown') ?></div>
                                <small style="color:#64748b"><?= htmlspecialchars($row['ANIMAL_TYPE_NAME']) ?></small>
                            </td>
                            <td><?= htmlspecialchars($row['BREED_NAME']) ?></td>
                            <td><?= $row['SEX'] ?></td>
                            <td><span class="badge <?= $statusClass ?>"><?= htmlspecialchars($row['CURRENT_STATUS']) ?></span></td>
                            <td><?= htmlspecialchars($row['LOCATION_NAME']) ?></td>
                            <td style="color:#f472b6;"><?= htmlspecialchars($row['MOTHER_TAG'] ?? '-') ?></td>
                            <td style="text-align:right;"><?= $row['WEIGHT_AT_BIRTH'] > 0 ? $row['WEIGHT_AT_BIRTH'] : '-' ?></td>
                            <td style="text-align:right;" class="val-weight"><?= $row['CURRENT_ESTIMATED_WEIGHT'] > 0 ? $row['CURRENT_ESTIMATED_WEIGHT'] : '-' ?></td>
                            <td style="text-align:right;" class="val-money">₱<?= number_format($row['ACQUISITION_COST'], 2) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if($total_pages > 1): ?>
        <div class="pagination">
            <?php 
                $params = $_GET;
                unset($params['page_no']);
                $query_str = http_build_query($params);
            ?>

            <?php if($page_no > 1): ?>
                <a href="?<?= $query_str ?>&page_no=<?= $page_no - 1 ?>" class="page-link">Previous</a>
            <?php endif; ?>

            <?php for($i=1; $i<=$total_pages; $i++): ?>
                <a href="?<?= $query_str ?>&page_no=<?= $i ?>" class="page-link <?= $i == $page_no ? 'active' : '' ?>">
                    <?= $i ?>
                </a>
            <?php endfor; ?>

            <?php if($page_no < $total_pages): ?>
                <a href="?<?= $query_str ?>&page_no=<?= $page_no + 1 ?>" class="page-link">Next</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

    <?php endif; ?>

</div>

<script>
    const jsPDF = window.jspdf.jsPDF;
    const viewMode = "<?= $view ?>";
    const records = <?php echo json_encode($animals); ?>;

    function exportPDF() {
        const doc = new jsPDF('landscape');
        doc.setFontSize(18);
        doc.setTextColor(34, 197, 94);
        doc.text("Animal Report (" + viewMode.toUpperCase() + ")", 14, 15);
        
        doc.setFontSize(10);
        doc.setTextColor(100);
        doc.text(`Generated: ${new Date().toLocaleString()}`, 14, 22);

        const rows = records.map(r => [
            r.TAG_NO, r.STAGE_NAME || '-', r.BREED_NAME, r.SEX, r.CURRENT_STATUS, 
            r.LOCATION_NAME, r.MOTHER_TAG || '-', r.CURRENT_ESTIMATED_WEIGHT, r.ACQUISITION_COST
        ]);

        doc.autoTable({
            head: [['Tag', 'Stage', 'Breed', 'Sex', 'Status', 'Location', 'Mother', 'Wt (kg)', 'Cost (P)']],
            body: rows,
            startY: 30,
            styles: { fontSize: 8 },
            headStyles: { fillColor: [34, 197, 94] }
        });

        doc.save('Animal_Report.pdf');
    }

    function exportExcel() {
        const excelData = records.map(r => ({
            'Tag No': r.TAG_NO,
            'Type': r.ANIMAL_TYPE_NAME,
            'Breed': r.BREED_NAME,
            'Stage': r.STAGE_NAME,
            'Sex': r.SEX,
            'Status': r.CURRENT_STATUS,
            'Location': `${r.LOCATION_NAME} - ${r.PEN_NAME}`,
            'Mother Tag': r.MOTHER_TAG || '-',
            'Birth Date': r.BIRTH_DATE,
            'Birth Wt': r.WEIGHT_AT_BIRTH,
            'Current Wt': r.CURRENT_ESTIMATED_WEIGHT,
            'Cost (PHP)': parseFloat(r.ACQUISITION_COST)
        }));
        const ws = XLSX.utils.json_to_sheet(excelData);
        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, "Inventory");
        XLSX.writeFile(wb, "Animal_Report.xlsx");
    }

    function exportCSV() {
        let csvContent = "data:text/csv;charset=utf-8,";
        csvContent += "Tag No,Type,Breed,Stage,Sex,Status,Location,Mother Tag,Birth Date,Current Wt,Cost\n";
        records.forEach(r => {
            const row = [
                r.TAG_NO, r.ANIMAL_TYPE_NAME, r.BREED_NAME, r.STAGE_NAME, r.SEX, r.CURRENT_STATUS,
                `${r.LOCATION_NAME} - ${r.PEN_NAME}`, r.MOTHER_TAG || '-', r.BIRTH_DATE,
                r.CURRENT_ESTIMATED_WEIGHT, r.ACQUISITION_COST
            ].map(e => `"${e}"`).join(",");
            csvContent += row + "\n";
        });
        const encodedUri = encodeURI(csvContent);
        const link = document.createElement("a");
        link.setAttribute("href", encodedUri);
        link.setAttribute("download", "Animal_Report.csv");
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
</script>

</body>
</html>