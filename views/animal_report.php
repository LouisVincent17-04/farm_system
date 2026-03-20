<?php
// views/animal_report.php
error_reporting(0);
ini_set('display_errors', 0);
$page = "reports";
include '../config/Connection.php';

include '../security/checkAccess.php';
checkAccess('animal_report');
include '../common/navbar.php';
include '../common/chat_support.php';
include '../functions/getUsersLocation.php'; 

// --- 1. GET FILTER INPUTS ---
$view        = $_GET['view'] ?? 'detailed'; 
$date_from   = $_GET['date_from'] ?? '';
$date_to     = $_GET['date_to'] ?? '';
$status      = $_GET['status'] ?? ''; 
$animal_type = $_GET['animal_type'] ?? '';
$breed       = $_GET['breed'] ?? '';
$stage       = $_GET['stage'] ?? ''; 
$sex         = $_GET['sex'] ?? '';
$sow_status  = $_GET['sow_status'] ?? ''; 

// Mapped filters for drill-down (Location/Building/Pen)
$filter_loc  = $_GET['f_loc'] ?? '';
$filter_bld  = $_GET['f_bld'] ?? ''; 
$filter_pen  = $_GET['f_pen'] ?? '';

// Auto-assign location filter if user is restricted
if ($USER_LOCATION_ != 1000) {
    $filter_loc = $USER_LOCATION_;
}

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
        $where_sql .= " AND DATE(ar.BIRTH_DATE) BETWEEN :date_from AND :date_to";
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

    // Apply Sow Status Filter
    if ($sow_status) {
        if ($sow_status === 'SERVICE') {
            $where_sql .= " AND EXISTS (SELECT 1 FROM sow_status_history ssh WHERE ssh.ANIMAL_ID = ar.ANIMAL_ID AND ssh.IS_ACTIVE = 1 AND ssh.STATUS_NAME LIKE 'SERVICE%')";
        } else {
            $where_sql .= " AND EXISTS (SELECT 1 FROM sow_status_history ssh WHERE ssh.ANIMAL_ID = ar.ANIMAL_ID AND ssh.IS_ACTIVE = 1 AND ssh.STATUS_NAME = :sow_status)";
            $params[':sow_status'] = $sow_status;
        }
    }

    // Apply Location/Building Filters 
    if ($filter_loc) { $where_sql .= " AND ar.LOCATION_ID = :floc"; $params[':floc'] = $filter_loc; }
    if ($filter_bld) { $where_sql .= " AND ar.BUILDING_ID = :fbld"; $params[':fbld'] = $filter_bld; }
    if ($filter_pen) { $where_sql .= " AND ar.PEN_ID = :fpen"; $params[':fpen'] = $filter_pen; }

    // --- 3. FETCH GLOBAL STATS ---
    $stats_sql = "SELECT 
                    COUNT(*) as total_heads,
                    SUM(ar.ACQUISITION_COST) as total_value,
                    SUM(ar.CURRENT_ACTUAL_WEIGHT) as total_weight,
                    SUM(CASE WHEN ar.SEX = 'M' THEN 1 ELSE 0 END) as male_count,
                    SUM(CASE WHEN ar.SEX = 'F' THEN 1 ELSE 0 END) as female_count
                  FROM animal_records ar 
                  $where_sql";
    
    $stmt_stats = $conn->prepare($stats_sql);
    $stmt_stats->execute($params);
    $stats = $stmt_stats->fetch(PDO::FETCH_ASSOC);

    // Type Breakdown
    $type_sql = "SELECT at.ANIMAL_TYPE_NAME, COUNT(*) as count
                 FROM animal_records ar
                 LEFT JOIN animal_type at ON ar.ANIMAL_TYPE_ID = at.ANIMAL_TYPE_ID
                 $where_sql
                 GROUP BY at.ANIMAL_TYPE_NAME";
    $stmt_type = $conn->prepare($type_sql);
    $stmt_type->execute($params);
    $type_breakdown = $stmt_type->fetchAll(PDO::FETCH_KEY_PAIR);

    // --- 3.5. FETCH SOW SPECIFIC STATS IF FILTERED BY SOW (CLASS_ID = 8) ---
    $sow_stats = null;
    if ($stage == '8' || $sow_status) {
        $sow_sql = "SELECT 
                        SUM(CASE WHEN ssh.STATUS_NAME = 'DRY' THEN 1 ELSE 0 END) as dry_count,
                        SUM(CASE WHEN ssh.STATUS_NAME LIKE 'SERVICE%' THEN 1 ELSE 0 END) as service_count,
                        SUM(CASE WHEN ssh.STATUS_NAME = 'PREGNANT' THEN 1 ELSE 0 END) as pregnant_count,
                        SUM(CASE WHEN ssh.STATUS_NAME = 'BIRTHING' THEN 1 ELSE 0 END) as birthing_count
                    FROM animal_records ar 
                    LEFT JOIN sow_status_history ssh ON ar.ANIMAL_ID = ssh.ANIMAL_ID AND ssh.IS_ACTIVE = 1
                    $where_sql";
        $stmt_sow = $conn->prepare($sow_sql);
        $stmt_sow->execute($params);
        $sow_stats = $stmt_sow->fetch(PDO::FETCH_ASSOC);
    }

    // --- 4. DATA SELECTION COLUMNS (Reused for Detailed & Summary) ---
    $select_columns = "
        ar.*,
        at.ANIMAL_TYPE_NAME, b.BREED_NAME, ac.STAGE_NAME,
        l.LOCATION_NAME, bld.BUILDING_NAME, p.PEN_NAME,
        m.TAG_NO as MOTHER_TAG,
        DATE_FORMAT(ar.BIRTH_DATE, '%m/%d/%Y') as BIRTH_DATE_FMT,
        
        COALESCE((SELECT SUM(TRANSACTION_COST) FROM feed_transactions WHERE ANIMAL_ID = ar.ANIMAL_ID), 0) as cost_feed,
        COALESCE((SELECT SUM(TOTAL_COST) FROM treatment_transactions WHERE ANIMAL_ID = ar.ANIMAL_ID), 0) as cost_med,
        COALESCE((SELECT SUM(VACCINATION_COST + VACCINE_COST) FROM vaccination_records WHERE ANIMAL_ID = ar.ANIMAL_ID), 0) as cost_vac,
        COALESCE((SELECT SUM(TOTAL_COST) FROM vitamins_supplements_transactions WHERE ANIMAL_ID = ar.ANIMAL_ID), 0) as cost_vit,
        COALESCE((SELECT SUM(COST) FROM check_ups WHERE ANIMAL_ID = ar.ANIMAL_ID), 0) as cost_chk,
        
        COALESCE((SELECT STATUS_NAME FROM sow_status_history WHERE ANIMAL_ID = ar.ANIMAL_ID AND IS_ACTIVE = 1 LIMIT 1), '-') as curr_sow_status,
        (SELECT COUNT(*) FROM sow_status_history WHERE ANIMAL_ID = ar.ANIMAL_ID AND STATUS_NAME = 'DRY') as count_dry,
        (SELECT COUNT(*) FROM sow_status_history WHERE ANIMAL_ID = ar.ANIMAL_ID AND STATUS_NAME LIKE 'SERVICE%') as count_service,
        (SELECT COUNT(*) FROM sow_status_history WHERE ANIMAL_ID = ar.ANIMAL_ID AND STATUS_NAME = 'PREGNANT') as count_pregnant,
        (SELECT COUNT(*) FROM sow_status_history WHERE ANIMAL_ID = ar.ANIMAL_ID AND STATUS_NAME = 'BIRTHING') as count_birthing,
        (SELECT COUNT(*) FROM sow_status_history WHERE ANIMAL_ID = ar.ANIMAL_ID AND STATUS_NAME = 'ABORTION') as count_abortion,
        
        -- NEW SOW SPECIFIC QUERIES BASED ON SCHEMA --
        COALESCE((SELECT SUM(ACTIVE_COUNT) FROM sow_birthing_records WHERE ANIMAL_ID = ar.ANIMAL_ID), 0) as total_alive,
        COALESCE((SELECT SUM(DEAD_COUNT) FROM sow_birthing_records WHERE ANIMAL_ID = ar.ANIMAL_ID), 0) as total_dead,
        COALESCE((SELECT SUM(MUMMIFIED_COUNT) FROM sow_birthing_records WHERE ANIMAL_ID = ar.ANIMAL_ID), 0) as total_mummified,
        
        (SELECT b_ar.TAG_NO FROM sow_service_history sh 
         LEFT JOIN animal_records b_ar ON sh.BOAR_ID = b_ar.ANIMAL_ID 
         WHERE sh.ANIMAL_ID = ar.ANIMAL_ID ORDER BY sh.SERVICE_START_DATE DESC LIMIT 1) as last_boar_tag,
         
        (SELECT DATE_FORMAT(SERVICE_START_DATE, '%m/%d/%Y') FROM sow_service_history 
         WHERE ANIMAL_ID = ar.ANIMAL_ID ORDER BY SERVICE_START_DATE DESC LIMIT 1) as last_service_date
    ";

    // --- 4. FETCH DATA ROWS ---
    if ($view === 'detailed') {
        $sql = "SELECT $select_columns
            FROM animal_records ar
            LEFT JOIN animal_type at ON ar.ANIMAL_TYPE_ID = at.ANIMAL_TYPE_ID
            LEFT JOIN breeds b ON ar.BREED_ID = b.BREED_ID
            LEFT JOIN animal_classifications ac ON ar.CLASS_ID = ac.CLASS_ID
            LEFT JOIN locations l ON ar.LOCATION_ID = l.LOCATION_ID
            LEFT JOIN buildings bld ON ar.BUILDING_ID = bld.BUILDING_ID
            LEFT JOIN pens p ON ar.PEN_ID = p.PEN_ID
            LEFT JOIN animal_records m ON ar.MOTHER_ID = m.ANIMAL_ID
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
        $sql = "SELECT $select_columns
            FROM animal_records ar
            LEFT JOIN animal_type at ON ar.ANIMAL_TYPE_ID = at.ANIMAL_TYPE_ID
            LEFT JOIN breeds b ON ar.BREED_ID = b.BREED_ID
            LEFT JOIN animal_classifications ac ON ar.CLASS_ID = ac.CLASS_ID
            LEFT JOIN locations l ON ar.LOCATION_ID = l.LOCATION_ID
            LEFT JOIN buildings bld ON ar.BUILDING_ID = bld.BUILDING_ID
            LEFT JOIN pens p ON ar.PEN_ID = p.PEN_ID
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
            if ($view === 'building') {
                $group_key = $row['BUILDING_NAME'] ?: 'Unassigned Building';
                $group_id = $row['BUILDING_ID'];
            } else {
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
    $types = $conn->query("SELECT * FROM animal_type ORDER BY ANIMAL_TYPE_NAME")->fetchAll();
    $breeds_list = $conn->query("SELECT * FROM breeds ORDER BY BREED_NAME")->fetchAll();
    $stages_list = $conn->query("SELECT * FROM animal_classifications ORDER BY CLASS_ID")->fetchAll();

    // Fetch Locations based on user access
    if ($USER_LOCATION_ != 1000) {
        $loc_stmt = $conn->prepare("SELECT * FROM locations WHERE LOCATION_ID = ? ORDER BY LOCATION_NAME");
        $loc_stmt->execute([$USER_LOCATION_]);
        $locations = $loc_stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $locations = $conn->query("SELECT * FROM locations ORDER BY LOCATION_NAME")->fetchAll(PDO::FETCH_ASSOC);
    }

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
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" type="text/css" href="https://npmcdn.com/flatpickr/dist/themes/dark.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.31/jspdf.plugin.autotable.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />

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
            font-size: clamp(1.8rem, 4vw, 2.5rem); font-weight: 800; 
            background: linear-gradient(135deg, #22c55e, #16a34a); 
            -webkit-background-clip: text; -webkit-text-fill-color: transparent; 
            margin-bottom: 0.5rem; line-height: 1.2;
        }
        .subtitle { color: #94a3b8; font-size: 1rem; margin: 0; }
        
        /* --- STATS CARDS --- */
        .stats-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); 
            gap: 1.5rem; 
            margin-bottom: 1.5rem; 
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
        .text-green { color: #4ade80; } .text-gold { color: #fbbf24; } .text-blue { color: #60a5fa; } .text-slate { color: #cbd5e1; }

        .type-list { list-style: none; padding: 0; margin: 0; text-align: left; font-size: 0.85rem; }
        .type-list li { display: flex; justify-content: space-between; padding: 2px 0; border-bottom: 1px solid rgba(255,255,255,0.05); }
        .type-list li:last-child { border-bottom: none; }
        .type-name { color: #cbd5e1; }
        .type-count { color: #fff; font-weight: bold; }

        /* --- SOW LIFECYCLE CARD --- */
        .sow-stats-container {
            grid-column: 1 / -1; 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); 
            gap: 1rem; 
            background: rgba(236, 72, 153, 0.05);
            border: 1px solid rgba(236, 72, 153, 0.3);
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        /* --- FILTER BAR FIXES --- */
        .filter-box { 
            background: rgba(15, 23, 42, 0.6); border: 1px solid #334155; padding: 1.5rem; 
            border-radius: 16px; margin-bottom: 2rem; 
        }
        
        .filter-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); 
            gap: 1rem; 
            align-items: end; 
        }
        .form-group label { display: block; font-size: 0.75rem; color: #94a3b8; margin-bottom: 0.4rem; font-weight: 600; text-transform: uppercase; }
        .form-input { 
            width: 100%; padding: 10px; background: #0f172a; border: 1px solid #334155; 
            color: white; border-radius: 8px; font-size: 0.9rem; box-sizing: border-box; outline: none;
        }
        .form-input:focus { border-color: #22c55e; }
        
        .btn-group { display: flex; gap: 10px; flex-wrap: wrap; margin-top:1rem;}
        .action-bar { margin-top: 1.5rem; display: flex; gap: 10px; justify-content: flex-end; flex-wrap: wrap; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 1rem; }
        .btn { padding: 10px 20px; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; text-decoration: none; font-size: 0.9rem; transition: transform 0.1s; white-space: nowrap; }
        .btn:active { transform: scale(0.98); }
        .btn-primary { background: #22c55e; color: white; }
        .btn-outline { background: transparent; border: 1px solid #475569; color: #cbd5e1; }
        
        /* Export Buttons */
        .btn-pdf { background: #3b82f6; color: white; }
        .btn-excel { background: #10b981; color: white; }
        .btn-csv { background: #f59e0b; color: white; }

        /* Row Action Button */
        .btn-view-ledger {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 6px 12px; background: rgba(34, 197, 94, 0.15); 
            border: 1px solid rgba(34, 197, 94, 0.4); color: #4ade80;
            border-radius: 6px; font-size: 0.8rem; font-weight: 600;
            text-decoration: none; transition: all 0.2s; white-space: nowrap;
        }
        .btn-view-ledger:hover { background: rgba(34, 197, 94, 0.3); color: #fff; transform: translateY(-1px); border-color: #22c55e; }


        /* --- TABLE & GROUPING --- */
        .table-wrap { background: rgba(30, 41, 59, 0.5); border-radius: 16px; overflow: hidden; border: 1px solid #334155; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 1400px; }
        th { background: rgba(15, 23, 42, 0.9); color: #94a3b8; text-align: left; padding: 1rem; font-size: 0.75rem; text-transform: uppercase; border-bottom: 1px solid #334155; white-space: nowrap; }
        td { padding: 1rem; border-bottom: 1px solid rgba(255,255,255,0.05); font-size: 0.85rem; color: #e2e8f0; white-space: nowrap; }
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
        .val-cost { font-family: monospace; color: #f87171; }
        .val-total { font-family: monospace; color: #4ade80; font-weight: bold; }
        .val-weight { font-family: monospace; color: #60a5fa; font-weight: bold; }
        
        /* Repro Status Info */
        .repro-badge { color: #f9a8d4; font-weight: bold; font-size: 0.85rem; }
        .repro-stats { display: flex; gap: 6px; font-size: 0.75rem; color: #94a3b8; margin-top: 4px; }
        .repro-stats span { display: inline-block; background: rgba(0,0,0,0.2); padding: 2px 5px; border-radius: 4px; border: 1px solid rgba(255,255,255,0.05); }

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

        /* --- MOBILE RESPONSIVE OVERRIDES --- */
        @media (max-width: 900px) {
            .container { padding: 1rem; }
            .header { flex-direction: column; align-items: flex-start; text-align: left;}
            .filter-grid { grid-template-columns: 1fr; }
            .action-bar { flex-direction: column; }
            .action-bar .btn { width: 100%; justify-content: center; }
            .group-stats-row { grid-template-columns: 1fr; }
            .sow-stats-container { grid-template-columns: 1fr 1fr; } 

            .table-wrap { border: none; background: transparent; overflow: visible; }
            table { min-width: 0; display: block; }
            thead { display: none; }
            tbody { display: block; width: 100%; }
            
            tr { 
                display: block; 
                background: rgba(30, 41, 59, 0.6); 
                border: 1px solid #475569; 
                border-radius: 12px; 
                margin-bottom: 1rem; 
                padding: 1rem; 
                box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            }
            
            tr.group-header-row, tr.sub-group-header-row {
                padding: 1rem; border-radius: 8px; margin-bottom: 0.5rem;
            }
            tr.group-header-row td, tr.sub-group-header-row td {
                display: block; text-align: left; border: none; padding: 0;
            }
            tr.group-header-row td::before, tr.sub-group-header-row td::before {
                display: none; 
            }

            td { 
                display: flex; 
                justify-content: space-between; 
                align-items: center; 
                padding: 0.75rem 0; 
                border-bottom: 1px dashed rgba(255,255,255,0.1); 
                text-align: right; 
            }
            
            td[style*="padding-left: 2rem;"] { padding-left: 0 !important; }
            td:last-child { border-bottom: none; }
            
            td::before { 
                content: attr(data-label); 
                font-weight: 700; 
                color: #94a3b8; 
                font-size: 0.8rem; 
                text-transform: uppercase; 
                margin-right: 1rem; 
                text-align: left;
                flex-shrink: 0;
            }
            
            .btn-view-ledger { width: 100%; justify-content: center; margin-top: 5px; }
        }
    </style>
</head>
<body>

<div class="container">
    
    <a href="reports.php" class="back-link">
        <i class="fa-solid fa-arrow-left"></i> Back to Reports Dashboard
    </a>

    <div class="header">
        <h1 class="title">Animal Inventory Report</h1>
        <p class="subtitle">Comprehensive livestock analysis and financial metrics.</p>
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
            <div class="stat-lbl">Total Acq. Value</div>
            <div class="stat-val text-gold">₱<?= number_format($stats['total_value'], 2) ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-lbl">Females / Males</div>
            <div class="stat-val text-green"><?= $stats['female_count'] ?> / <?= $stats['male_count'] ?></div>
        </div>

        <?php if ($stage == '8' || $sow_status): ?>
            <div class="sow-stats-container">
                <div style="text-align: center;">
                    <div class="stat-lbl" style="color: #f9a8d4;">Dry</div>
                    <div class="stat-val text-slate"><?= (int)$sow_stats['dry_count'] ?></div>
                </div>
                <div style="text-align: center;">
                    <div class="stat-lbl" style="color: #f9a8d4;">In-Service</div>
                    <div class="stat-val text-blue"><?= (int)$sow_stats['service_count'] ?></div>
                </div>
                <div style="text-align: center;">
                    <div class="stat-lbl" style="color: #f9a8d4;">Pregnant</div>
                    <div class="stat-val text-gold"><?= (int)$sow_stats['pregnant_count'] ?></div>
                </div>
                <div style="text-align: center;">
                    <div class="stat-lbl" style="color: #f9a8d4;">Birthing / Farrowing</div>
                    <div class="stat-val text-green"><?= (int)$sow_stats['birthing_count'] ?></div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="filter-box">
        <form method="GET" id="filterForm">
            <?php if($filter_bld): ?><input type="hidden" name="f_bld" id="hidden_f_bld" value="<?= htmlspecialchars($filter_bld) ?>"><?php endif; ?>
            <?php if($filter_pen): ?><input type="hidden" name="f_pen" id="hidden_f_pen" value="<?= htmlspecialchars($filter_pen) ?>"><?php endif; ?>

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
                    <label>Location</label>
                    <select name="f_loc" class="form-input" onchange="handleLocationChange()" <?php echo ($USER_LOCATION_ != 1000) ? 'style="pointer-events: none; opacity: 0.7; background-color: #1e293b;"' : ''; ?>>
                        <?php if($USER_LOCATION_ == 1000): ?>
                            <option value="">All Locations</option>
                        <?php endif; ?>
                        <?php foreach($locations as $loc): ?>
                            <option value="<?= $loc['LOCATION_ID'] ?>" <?= $filter_loc == $loc['LOCATION_ID'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($loc['LOCATION_NAME']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Birth Date Range</label>
                    <div style="display: flex; gap: 5px;">
                        <input type="text" name="date_from" class="form-input date-picker" value="<?= htmlspecialchars($date_from) ?>" placeholder="Start Date">
                        <input type="text" name="date_to" class="form-input date-picker" value="<?= htmlspecialchars($date_to) ?>" placeholder="End Date">
                    </div>
                </div>
            </div> 
            
            <div class="filter-grid" style="margin-top: 15px;">
                <div class="form-group" style="grid-column: span 1;">
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
                    <div style="display: flex; gap: 5px;">
                        <select name="status" class="form-input">
                            <option value="">All Animal Status</option>
                            <option value="Active" <?= $status=='Active'?'selected':'' ?>>Active Herd</option>
                            <option value="Sold" <?= $status=='Sold'?'selected':'' ?>>Sold History</option>
                            <option value="Deceased" <?= $status=='Deceased'?'selected':'' ?>>Deceased/Cull</option>
                        </select>
                        <select name="sow_status" class="form-input">
                            <option value="">All Sow Status</option>
                            <option value="DRY" <?= $sow_status=='DRY'?'selected':'' ?>>Dry</option>
                            <option value="SERVICE" <?= $sow_status=='SERVICE'?'selected':'' ?>>Serviced</option>
                            <option value="PREGNANT" <?= $sow_status=='PREGNANT'?'selected':'' ?>>Pregnant</option>
                            <option value="BIRTHING" <?= $sow_status=='BIRTHING'?'selected':'' ?>>Birthing / Farrowing</option>
                            <option value="ABORTION" <?= $sow_status=='ABORTION'?'selected':'' ?>>Abortion</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="btn-group" style="justify-content: flex-start;">
                <button type="submit" class="btn btn-primary">Apply Filters</button>
                <a href="animal_report.php" class="btn btn-outline">Reset Filters</a>
            </div>
            
            <div class="action-bar">
                <button type="button" class="btn btn-pdf" onclick="exportPDF()"><i class="fa-solid fa-file-pdf"></i> PDF</button>
                <button type="button" class="btn btn-excel" onclick="exportExcel()"><i class="fa-solid fa-file-excel"></i> Excel</button>
                <button type="button" class="btn btn-csv" onclick="exportCSV()"><i class="fa-solid fa-file-csv"></i> CSV</button>
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
                        <a href="?view=pen&f_bld=<?= $gdata['id'] ?>&status=<?= $status ?>&f_loc=<?= $filter_loc ?>&stage=<?= $stage ?>" class="btn-view-pens">
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
                        <div class="mini-lbl">Total Acq. Cost</div>
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
             <a href="?view=building&status=<?= $status ?>&f_loc=<?= $filter_loc ?>&stage=<?= $stage ?>" class="btn-outline" style="padding:6px 12px; border-radius:6px; text-decoration:none;">← Back to Buildings</a>
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
                        <div class="mini-lbl">Total Acq. Cost</div>
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
                                    <th>Tag No</th>
                                    <th>Stage</th>
                                    <th>Status</th>
                                    <th>Repro Info</th>
                                    <th style="text-align:right;">Wt(kg)</th>
                                    <th style="text-align:right;">Acq.Cost</th>
                                    <th style="text-align:right;">Feed</th>
                                    <th style="text-align:right;">Meds</th>
                                    <th style="text-align:right;">Vacs</th>
                                    <th style="text-align:right;">Vits</th>
                                    <th style="text-align:right;">ChkUp</th>
                                    <th style="text-align:right;">Total Cost</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($gdata['items'] as $row): 
                                    $statusClass = 'b-active';
                                    if($row['CURRENT_STATUS'] == 'Sold') $statusClass = 'b-sold';
                                    if(in_array($row['CURRENT_STATUS'], ['Deceased','Cull','Dead'])) $statusClass = 'b-dec';
                                    
                                    // Cost Calculations
                                    $c_feed = $row['cost_feed'];
                                    $c_med  = $row['cost_med'];
                                    $c_vac  = $row['cost_vac'];
                                    $c_vit  = $row['cost_vit'];
                                    $c_chk  = $row['cost_chk'];
                                    $total_cost = $row['ACQUISITION_COST'] + $c_feed + $c_med + $c_vac + $c_vit + $c_chk;
                                ?>
                                    <tr>
                                        <td data-label="Tag No" style="font-weight:bold; color:#fff;"><?= htmlspecialchars($row['TAG_NO']) ?></td>
                                        <td data-label="Stage"><?= htmlspecialchars($row['STAGE_NAME']) ?></td>
                                        <td data-label="Status"><span class="badge <?= $statusClass ?>"><?= htmlspecialchars($row['CURRENT_STATUS']) ?></span></td>
                                        <td data-label="Repro Info">
                                            <?php if($row['curr_sow_status'] === '-' && $row['count_dry'] == 0 && $row['count_service'] == 0): ?>
                                                <span style="color:#64748b;">N/A</span>
                                            <?php else: ?>
                                                <div class="repro-badge"><?= htmlspecialchars($row['curr_sow_status']) ?></div>
                                                <div class="repro-stats">
                                                    <span title="Dry">D:<?= $row['count_dry'] ?></span>
                                                    <span title="Serviced">S:<?= $row['count_service'] ?></span>
                                                    <span title="Pregnant">P:<?= $row['count_pregnant'] ?></span>
                                                    <span title="Birthing">B:<?= $row['count_birthing'] ?></span>
                                                    <span title="Abortion">A:<?= $row['count_abortion'] ?></span>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td data-label="Wt(kg)" style="text-align:right;" class="val-weight"><?= $row['CURRENT_ACTUAL_WEIGHT'] > 0 ? $row['CURRENT_ACTUAL_WEIGHT'] : '-' ?></td>
                                        <td data-label="Acq.Cost" style="text-align:right;" class="val-money"><?= number_format($row['ACQUISITION_COST'], 2) ?></td>
                                        <td data-label="Feed" style="text-align:right;" class="val-cost"><?= number_format($c_feed, 2) ?></td>
                                        <td data-label="Meds" style="text-align:right;" class="val-cost"><?= number_format($c_med, 2) ?></td>
                                        <td data-label="Vacs" style="text-align:right;" class="val-cost"><?= number_format($c_vac, 2) ?></td>
                                        <td data-label="Vits" style="text-align:right;" class="val-cost"><?= number_format($c_vit, 2) ?></td>
                                        <td data-label="ChkUp" style="text-align:right;" class="val-cost"><?= number_format($c_chk, 2) ?></td>
                                        <td data-label="Total Cost" style="text-align:right;" class="val-total">₱<?= number_format($total_cost, 2) ?></td>
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
                        <th>Repro Info</th>
                        <th>Location</th>
                        <th style="text-align:right;">Wt(kg)</th>
                        <th style="text-align:right;">Acq.Cost</th>
                        <th style="text-align:right;">Feed</th>
                        <th style="text-align:right;">Meds</th>
                        <th style="text-align:right;">Vacs</th>
                        <th style="text-align:right;">Vits</th>
                        <th style="text-align:right;">ChkUp</th>
                        <th style="text-align:right;">Total Cost</th>
                        <th style="text-align:center;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($animals)): ?>
                        <tr><td colspan="16" style="text-align:center; padding:3rem; color:#64748b;">No records found matching filters.</td></tr>
                    <?php else: ?>
                        <?php 
                        $last_building = '';
                        $last_pen = '';
                        
                        foreach($animals as $row): 
                            // Building Header
                            $curr_building = $row['BUILDING_NAME'] ?: 'Unassigned Building';
                            if ($curr_building !== $last_building) {
                                echo "<tr class='group-header-row'><td colspan='16'>🏢 Building: " . htmlspecialchars($curr_building) . "</td></tr>";
                                $last_building = $curr_building;
                                $last_pen = ''; 
                            }

                            // Pen Header
                            $curr_pen = $row['PEN_NAME'] ?: 'Unassigned Pen';
                            if ($curr_pen !== $last_pen) {
                                echo "<tr class='sub-group-header-row'><td colspan='16'>↳ Pen: " . htmlspecialchars($curr_pen) . "</td></tr>";
                                $last_pen = $curr_pen;
                            }

                            $statusClass = 'b-active';
                            if($row['CURRENT_STATUS'] == 'Sold') $statusClass = 'b-sold';
                            if(in_array($row['CURRENT_STATUS'], ['Deceased','Cull','Dead'])) $statusClass = 'b-dec';
                            
                            // Cost Calculations
                            $c_feed = $row['cost_feed'];
                            $c_med  = $row['cost_med'];
                            $c_vac  = $row['cost_vac'];
                            $c_vit  = $row['cost_vit'];
                            $c_chk  = $row['cost_chk'];
                            $total_cost = $row['ACQUISITION_COST'] + $c_feed + $c_med + $c_vac + $c_vit + $c_chk;
                        ?>
                        <tr>
                            <td data-label="Tag No" style="font-weight:bold; color:#fff; padding-left: 2rem;"><?= htmlspecialchars($row['TAG_NO']) ?></td>
                            <td data-label="Classification">
                                <div><?= htmlspecialchars($row['STAGE_NAME'] ?? 'Unknown') ?></div>
                                <small style="color:#64748b"><?= htmlspecialchars($row['ANIMAL_TYPE_NAME']) ?></small>
                            </td>
                            <td data-label="Breed"><?= htmlspecialchars($row['BREED_NAME']) ?></td>
                            <td data-label="Sex"><?= $row['SEX'] ?></td>
                            <td data-label="Status"><span class="badge <?= $statusClass ?>"><?= htmlspecialchars($row['CURRENT_STATUS']) ?></span></td>
                            <td data-label="Repro Info">
                                <?php if($row['curr_sow_status'] === '-' && $row['count_dry'] == 0 && $row['count_service'] == 0): ?>
                                    <span style="color:#64748b;">N/A</span>
                                <?php else: ?>
                                    <div class="repro-badge"><?= htmlspecialchars($row['curr_sow_status']) ?></div>
                                    <div class="repro-stats">
                                        <span title="Dry">D:<?= $row['count_dry'] ?></span>
                                        <span title="Serviced">S:<?= $row['count_service'] ?></span>
                                        <span title="Pregnant">P:<?= $row['count_pregnant'] ?></span>
                                        <span title="Birthing">B:<?= $row['count_birthing'] ?></span>
                                        <span title="Abortion">A:<?= $row['count_abortion'] ?></span>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td data-label="Location"><?= htmlspecialchars($row['LOCATION_NAME']) ?></td>
                            <td data-label="Wt(kg)" style="text-align:right;" class="val-weight"><?= $row['CURRENT_ACTUAL_WEIGHT'] > 0 ? $row['CURRENT_ACTUAL_WEIGHT'] : '-' ?></td>
                            <td data-label="Acq.Cost" style="text-align:right;" class="val-money"><?= number_format($row['ACQUISITION_COST'], 2) ?></td>
                            <td data-label="Feed" style="text-align:right;" class="val-cost"><?= number_format($c_feed, 2) ?></td>
                            <td data-label="Meds" style="text-align:right;" class="val-cost"><?= number_format($c_med, 2) ?></td>
                            <td data-label="Vacs" style="text-align:right;" class="val-cost"><?= number_format($c_vac, 2) ?></td>
                            <td data-label="Vits" style="text-align:right;" class="val-cost"><?= number_format($c_vit, 2) ?></td>
                            <td data-label="ChkUp" style="text-align:right;" class="val-cost"><?= number_format($c_chk, 2) ?></td>
                            <td data-label="Total Cost" style="text-align:right;" class="val-total">₱<?= number_format($total_cost, 2) ?></td>
                            <td data-label="Action" style="text-align:center;">
                                <a href="viewAnimalLedger.php?id=<?= $row['ANIMAL_ID'] ?>" class="btn-view-ledger">
                                    <i class="fa-solid fa-clipboard-list"></i> Ledger
                                </a>
                            </td>
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
    // Initialize Flatpickr
    document.addEventListener('DOMContentLoaded', () => {
        flatpickr(".date-picker", {
            dateFormat: "Y-m-d", // Value submitted to PHP
            altInput: true,      // Visual input
            altFormat: "m/d/Y",  // mm/dd/yyyy format
            allowInput: true
        });
    });

    const jsPDF = window.jspdf.jsPDF;
    const viewMode = "<?= $view ?>";
    const records = <?php echo json_encode($animals); ?>;

    function handleLocationChange() {
        const bld = document.getElementById('hidden_f_bld');
        const pen = document.getElementById('hidden_f_pen');
        if (bld) bld.disabled = true; 
        if (pen) pen.disabled = true; 
        document.getElementById('filterForm').submit();
    }

    function exportPDF() {
        const doc = new jsPDF('landscape', 'mm', 'a4'); // specify orientation & format
        doc.setFontSize(18);
        doc.setTextColor(34, 197, 94);
        doc.text("Animal Report (" + viewMode.toUpperCase() + ")", 14, 15);
        
        doc.setFontSize(10);
        doc.setTextColor(100);
        doc.text(`Generated: ${new Date().toLocaleString()}`, 14, 22);

        const rows = records.map(r => {
            const acqCost = parseFloat(r.ACQUISITION_COST || 0);
            const cFeed = parseFloat(r.cost_feed || 0);
            const cMed  = parseFloat(r.cost_med || 0);
            const cVac  = parseFloat(r.cost_vac || 0);
            const cVit  = parseFloat(r.cost_vit || 0);
            const cChk  = parseFloat(r.cost_chk || 0);
            const totalCost = acqCost + cFeed + cMed + cVac + cVit + cChk;
            
            // Format Sow Info to multi-line string for PDF
            const sowCycles = (r.curr_sow_status !== '-' || r.count_dry > 0) 
                ? `${r.curr_sow_status}\nD:${r.count_dry} S:${r.count_service} P:${r.count_pregnant}\nB:${r.count_birthing} A:${r.count_abortion}`
                : 'N/A';
                
            // Combine the new service and birthing data for PDF (to save space)
            const sowDetails = (r.SEX === 'F' && r.curr_sow_status !== '-') 
                ? `Boar: ${r.last_boar_tag || 'N/A'} (${r.last_service_date || 'N/A'})\nBorn: ${r.total_alive}A|${r.total_dead}D|${r.total_mummified}M` 
                : 'N/A';

            return [
                r.TAG_NO, r.STAGE_NAME || '-', r.SEX, r.BIRTH_DATE_FMT || '-', r.CURRENT_STATUS, sowCycles, sowDetails, // ADDED BIRTH_DATE_FMT & SOW DETAILS
                r.LOCATION_NAME, r.CURRENT_ACTUAL_WEIGHT, 
                acqCost.toFixed(2), cFeed.toFixed(2), cMed.toFixed(2), cVac.toFixed(2), cVit.toFixed(2), cChk.toFixed(2), totalCost.toFixed(2)
            ];
        });

        doc.autoTable({
            head: [['Tag', 'Stage', 'Sex', 'Birthday', 'Status', 'Cycles', 'Sow Details', 'Location', 'Wt(kg)', 'Acq(P)', 'Feed(P)', 'Meds(P)', 'Vacs(P)', 'Vits(P)', 'ChkUp(P)', 'Total']], // UPDATED HEADERS
            body: rows,
            startY: 30,
            styles: { fontSize: 6, cellPadding: 1.5, overflow: 'linebreak' },
            headStyles: { fillColor: [34, 197, 94] }
        });

        doc.save('Animal_Report.pdf');
    }

    function exportExcel() {
        const excelData = records.map(r => {
            const acqCost = parseFloat(r.ACQUISITION_COST || 0);
            const cFeed = parseFloat(r.cost_feed || 0);
            const cMed  = parseFloat(r.cost_med || 0);
            const cVac  = parseFloat(r.cost_vac || 0);
            const cVit  = parseFloat(r.cost_vit || 0);
            const cChk  = parseFloat(r.cost_chk || 0);
            const totalCost = acqCost + cFeed + cMed + cVac + cVit + cChk;

            return {
                'Tag No': r.TAG_NO,
                'Type': r.ANIMAL_TYPE_NAME,
                'Breed': r.BREED_NAME,
                'Stage': r.STAGE_NAME,
                'Sex': r.SEX,
                'Birthday': r.BIRTH_DATE_FMT || '-', // ADDED BIRTHDAY
                'Status': r.CURRENT_STATUS,
                'Current Sow Status': r.curr_sow_status,
                'Dry Cycles': r.count_dry,
                'Service Cycles': r.count_service,
                'Pregnancies': r.count_pregnant,
                'Birthings': r.count_birthing,
                'Abortions': r.count_abortion,
                'Last Boar Used': r.last_boar_tag || 'N/A',      // NEW SOW METRIC
                'Last Service Date': r.last_service_date || 'N/A', // NEW SOW METRIC
                'Total Alive Piglets': r.total_alive || 0,       // NEW SOW METRIC
                'Total Dead Piglets': r.total_dead || 0,         // NEW SOW METRIC
                'Total Mummified': r.total_mummified || 0,       // NEW SOW METRIC
                'Location': `${r.LOCATION_NAME} - ${r.PEN_NAME}`,
                'Current Wt': r.CURRENT_ACTUAL_WEIGHT,
                'Acq Cost (PHP)': acqCost,
                'Feed (PHP)': cFeed,
                'Meds (PHP)': cMed,
                'Vacs (PHP)': cVac,
                'Vits (PHP)': cVit,
                'Checkups (PHP)': cChk,
                'Total Cost (PHP)': totalCost
            };
        });
        const ws = XLSX.utils.json_to_sheet(excelData);
        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, "Inventory");
        XLSX.writeFile(wb, "Animal_Report.xlsx");
    }

    function exportCSV() {
        let csvContent = "data:text/csv;charset=utf-8,";
        // UPDATED HEADER
        csvContent += "Tag No,Type,Breed,Stage,Sex,Birthday,Status,Sow Status,Dry Cycles,Service Cycles,Pregnancies,Birthings,Abortions,Last Boar Used,Last Service Date,Total Alive Piglets,Total Dead Piglets,Total Mummified,Location,Current Wt,Acq Cost,Feed,Meds,Vacs,Vits,Checkups,Total Cost\n";
        records.forEach(r => {
            const acqCost = parseFloat(r.ACQUISITION_COST || 0);
            const cFeed = parseFloat(r.cost_feed || 0);
            const cMed  = parseFloat(r.cost_med || 0);
            const cVac  = parseFloat(r.cost_vac || 0);
            const cVit  = parseFloat(r.cost_vit || 0);
            const cChk  = parseFloat(r.cost_chk || 0);
            const totalCost = acqCost + cFeed + cMed + cVac + cVit + cChk;

            const row = [
                r.TAG_NO, r.ANIMAL_TYPE_NAME, r.BREED_NAME, r.STAGE_NAME, r.SEX, (r.BIRTH_DATE_FMT || '-'), r.CURRENT_STATUS, 
                r.curr_sow_status, r.count_dry, r.count_service, r.count_pregnant, r.count_birthing, r.count_abortion,
                (r.last_boar_tag || 'N/A'), (r.last_service_date || 'N/A'), (r.total_alive || 0), (r.total_dead || 0), (r.total_mummified || 0), // NEW SOW METRICS
                `${r.LOCATION_NAME} - ${r.PEN_NAME}`, r.CURRENT_ACTUAL_WEIGHT,
                acqCost.toFixed(2), cFeed.toFixed(2), cMed.toFixed(2), cVac.toFixed(2), cVit.toFixed(2), cChk.toFixed(2), totalCost.toFixed(2)
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