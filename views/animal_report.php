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

$filter_loc  = $_GET['f_loc'] ?? '';
$filter_bld  = $_GET['f_bld'] ?? ''; 
$filter_pen  = $_GET['f_pen'] ?? '';

if ($USER_LOCATION_ != 1000) {
    $filter_loc = $USER_LOCATION_;
}

$limit = 50; 
$page_no = isset($_GET['page_no']) ? (int)$_GET['page_no'] : 1;
$offset = ($page_no - 1) * $limit;

try {
    if (!isset($conn)) { throw new Exception("Database connection failed."); }

    $where_sql = " WHERE ar.IS_ACTIVE IN (0, 1) ";
    $params = [];

    if ($date_from && $date_to) {
        $where_sql .= " AND DATE(ar.BIRTH_DATE) BETWEEN :date_from AND :date_to";
        $params[':date_from'] = $date_from;
        $params[':date_to']   = $date_to;
    }

    if ($status) {
        if ($status === 'Active') { $where_sql .= " AND ar.IS_ACTIVE = 1"; }
        elseif ($status === 'Inactive') { $where_sql .= " AND ar.IS_ACTIVE = 0"; }
        else { $where_sql .= " AND ar.CURRENT_STATUS = :status"; $params[':status'] = $status; }
    }

    if ($animal_type) { $where_sql .= " AND ar.ANIMAL_TYPE_ID = :atype"; $params[':atype'] = $animal_type; }
    if ($breed)       { $where_sql .= " AND ar.BREED_ID = :breed"; $params[':breed'] = $breed; }
    if ($stage)       { $where_sql .= " AND ar.CLASS_ID = :stage"; $params[':stage'] = $stage; }
    if ($sex)         { $where_sql .= " AND ar.SEX = :sex"; $params[':sex'] = $sex; }

    if ($sow_status) {
        if ($sow_status === 'SERVICE') {
            $where_sql .= " AND EXISTS (SELECT 1 FROM sow_status_history ssh WHERE ssh.ANIMAL_ID = ar.ANIMAL_ID AND ssh.IS_ACTIVE = 1 AND ssh.STATUS_NAME LIKE 'SERVICE%')";
        } else {
            $where_sql .= " AND EXISTS (SELECT 1 FROM sow_status_history ssh WHERE ssh.ANIMAL_ID = ar.ANIMAL_ID AND ssh.IS_ACTIVE = 1 AND ssh.STATUS_NAME = :sow_status)";
            $params[':sow_status'] = $sow_status;
        }
    }

    if ($filter_loc) { $where_sql .= " AND ar.LOCATION_ID = :floc"; $params[':floc'] = $filter_loc; }
    if ($filter_bld) { $where_sql .= " AND ar.BUILDING_ID = :fbld"; $params[':fbld'] = $filter_bld; }
    if ($filter_pen) { $where_sql .= " AND ar.PEN_ID = :fpen"; $params[':fpen'] = $filter_pen; }

    $stats_sql = "SELECT COUNT(*) as total_heads, SUM(ar.ACQUISITION_COST) as total_value, SUM(ar.CURRENT_ACTUAL_WEIGHT) as total_weight, SUM(CASE WHEN ar.SEX = 'M' THEN 1 ELSE 0 END) as male_count, SUM(CASE WHEN ar.SEX = 'F' THEN 1 ELSE 0 END) as female_count FROM animal_records ar $where_sql";
    $stmt_stats = $conn->prepare($stats_sql);
    $stmt_stats->execute($params);
    $stats = $stmt_stats->fetch(PDO::FETCH_ASSOC);

    $type_sql = "SELECT at.ANIMAL_TYPE_NAME, COUNT(*) as count FROM animal_records ar LEFT JOIN animal_type at ON ar.ANIMAL_TYPE_ID = at.ANIMAL_TYPE_ID $where_sql GROUP BY at.ANIMAL_TYPE_NAME";
    $stmt_type = $conn->prepare($type_sql);
    $stmt_type->execute($params);
    $type_breakdown = $stmt_type->fetchAll(PDO::FETCH_KEY_PAIR);

    $sow_stats = null;
    if ($stage == '8' || $sow_status) {
        $sow_sql = "SELECT SUM(CASE WHEN ssh.STATUS_NAME = 'DRY' THEN 1 ELSE 0 END) as dry_count, SUM(CASE WHEN ssh.STATUS_NAME LIKE 'SERVICE%' THEN 1 ELSE 0 END) as service_count, SUM(CASE WHEN ssh.STATUS_NAME = 'PREGNANT' THEN 1 ELSE 0 END) as pregnant_count, SUM(CASE WHEN ssh.STATUS_NAME = 'BIRTHING' THEN 1 ELSE 0 END) as birthing_count FROM animal_records ar LEFT JOIN sow_status_history ssh ON ar.ANIMAL_ID = ssh.ANIMAL_ID AND ssh.IS_ACTIVE = 1 $where_sql";
        $stmt_sow = $conn->prepare($sow_sql);
        $stmt_sow->execute($params);
        $sow_stats = $stmt_sow->fetch(PDO::FETCH_ASSOC);
    }

    $select_columns = "ar.*, at.ANIMAL_TYPE_NAME, b.BREED_NAME, ac.STAGE_NAME, l.LOCATION_NAME, bld.BUILDING_NAME, p.PEN_NAME, m.TAG_NO as MOTHER_TAG, DATE_FORMAT(ar.BIRTH_DATE, '%m/%d/%Y') as BIRTH_DATE_FMT, COALESCE((SELECT SUM(TRANSACTION_COST) FROM feed_transactions WHERE ANIMAL_ID = ar.ANIMAL_ID), 0) as cost_feed, COALESCE((SELECT SUM(TOTAL_COST) FROM treatment_transactions WHERE ANIMAL_ID = ar.ANIMAL_ID), 0) as cost_med, COALESCE((SELECT SUM(VACCINATION_COST + VACCINE_COST) FROM vaccination_records WHERE ANIMAL_ID = ar.ANIMAL_ID), 0) as cost_vac, COALESCE((SELECT SUM(TOTAL_COST) FROM vitamins_supplements_transactions WHERE ANIMAL_ID = ar.ANIMAL_ID), 0) as cost_vit, COALESCE((SELECT SUM(COST) FROM check_ups WHERE ANIMAL_ID = ar.ANIMAL_ID), 0) as cost_chk, COALESCE((SELECT STATUS_NAME FROM sow_status_history WHERE ANIMAL_ID = ar.ANIMAL_ID AND IS_ACTIVE = 1 LIMIT 1), '-') as curr_sow_status, (SELECT COUNT(*) FROM sow_status_history WHERE ANIMAL_ID = ar.ANIMAL_ID AND STATUS_NAME = 'DRY') as count_dry, (SELECT COUNT(*) FROM sow_status_history WHERE ANIMAL_ID = ar.ANIMAL_ID AND STATUS_NAME LIKE 'SERVICE%') as count_service, (SELECT COUNT(*) FROM sow_status_history WHERE ANIMAL_ID = ar.ANIMAL_ID AND STATUS_NAME = 'PREGNANT') as count_pregnant, (SELECT COUNT(*) FROM sow_status_history WHERE ANIMAL_ID = ar.ANIMAL_ID AND STATUS_NAME = 'BIRTHING') as count_birthing, (SELECT COUNT(*) FROM sow_status_history WHERE ANIMAL_ID = ar.ANIMAL_ID AND STATUS_NAME = 'ABORTION') as count_abortion, COALESCE((SELECT SUM(ACTIVE_COUNT) FROM sow_birthing_records WHERE ANIMAL_ID = ar.ANIMAL_ID), 0) as total_alive, COALESCE((SELECT SUM(DEAD_COUNT) FROM sow_birthing_records WHERE ANIMAL_ID = ar.ANIMAL_ID), 0) as total_dead, COALESCE((SELECT SUM(MUMMIFIED_COUNT) FROM sow_birthing_records WHERE ANIMAL_ID = ar.ANIMAL_ID), 0) as total_mummified, (SELECT b_ar.TAG_NO FROM sow_service_history sh LEFT JOIN animal_records b_ar ON sh.BOAR_ID = b_ar.ANIMAL_ID WHERE sh.ANIMAL_ID = ar.ANIMAL_ID ORDER BY sh.SERVICE_START_DATE DESC LIMIT 1) as last_boar_tag, (SELECT DATE_FORMAT(SERVICE_START_DATE, '%m/%d/%Y') FROM sow_service_history WHERE ANIMAL_ID = ar.ANIMAL_ID ORDER BY SERVICE_START_DATE DESC LIMIT 1) as last_service_date";

    if ($view === 'detailed') {
        $sql = "SELECT $select_columns FROM animal_records ar LEFT JOIN animal_type at ON ar.ANIMAL_TYPE_ID = at.ANIMAL_TYPE_ID LEFT JOIN breeds b ON ar.BREED_ID = b.BREED_ID LEFT JOIN animal_classifications ac ON ar.CLASS_ID = ac.CLASS_ID LEFT JOIN locations l ON ar.LOCATION_ID = l.LOCATION_ID LEFT JOIN buildings bld ON ar.BUILDING_ID = bld.BUILDING_ID LEFT JOIN pens p ON ar.PEN_ID = p.PEN_ID LEFT JOIN animal_records m ON ar.MOTHER_ID = m.ANIMAL_ID $where_sql ORDER BY l.LOCATION_NAME ASC, bld.BUILDING_NAME ASC, p.PEN_NAME ASC, ar.ANIMAL_ID ASC LIMIT :limit OFFSET :offset";
        $stmt = $conn->prepare($sql);
        foreach($params as $key => $val) { $stmt->bindValue($key, $val); }
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
        $stmt->execute();
        $animals = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $sql = "SELECT $select_columns FROM animal_records ar LEFT JOIN animal_type at ON ar.ANIMAL_TYPE_ID = at.ANIMAL_TYPE_ID LEFT JOIN breeds b ON ar.BREED_ID = b.BREED_ID LEFT JOIN animal_classifications ac ON ar.CLASS_ID = ac.CLASS_ID LEFT JOIN locations l ON ar.LOCATION_ID = l.LOCATION_ID LEFT JOIN buildings bld ON ar.BUILDING_ID = bld.BUILDING_ID LEFT JOIN pens p ON ar.PEN_ID = p.PEN_ID $where_sql ORDER BY l.LOCATION_NAME, bld.BUILDING_NAME, p.PEN_NAME";
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $animals = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    $grouped_data = [];
    if ($view !== 'detailed') {
        foreach ($animals as $row) {
            if ($view === 'building') { $group_key = $row['BUILDING_NAME'] ?: 'Unassigned Building'; $group_id = $row['BUILDING_ID']; }
            else { $group_key = $row['PEN_NAME'] ?: 'Unassigned Pen'; $group_id = $row['PEN_ID']; }
            if (!isset($grouped_data[$group_key])) { $grouped_data[$group_key] = ['name' => $group_key, 'id' => $group_id, 'count' => 0, 'cost' => 0, 'classifications' => [], 'items' => []]; }
            $grouped_data[$group_key]['count']++;
            $grouped_data[$group_key]['cost'] += $row['ACQUISITION_COST'];
            $grouped_data[$group_key]['items'][] = $row;
            $c_name = $row['STAGE_NAME'] ?: 'Unclassified';
            if (!isset($grouped_data[$group_key]['classifications'][$c_name])) { $grouped_data[$group_key]['classifications'][$c_name] = 0; }
            $grouped_data[$group_key]['classifications'][$c_name]++;
        }
        ksort($grouped_data);
    }

    $types = $conn->query("SELECT * FROM animal_type ORDER BY ANIMAL_TYPE_NAME")->fetchAll();
    $breeds_list = [];
    if ($filter_type) { $b_stmt = $conn->prepare("SELECT * FROM breeds WHERE ANIMAL_TYPE_ID = ? ORDER BY BREED_NAME"); $b_stmt->execute([$filter_type]); $breeds_list = $b_stmt->fetchAll(); }
    else { $breeds_list = $conn->query("SELECT * FROM breeds ORDER BY BREED_NAME")->fetchAll(); }
    $stages_list = $conn->query("SELECT * FROM animal_classifications ORDER BY CLASS_ID")->fetchAll();

    if ($USER_LOCATION_ != 1000) { $loc_stmt = $conn->prepare("SELECT * FROM locations WHERE LOCATION_ID = ? ORDER BY LOCATION_NAME"); $loc_stmt->execute([$USER_LOCATION_]); $locations = $loc_stmt->fetchAll(PDO::FETCH_ASSOC); }
    else { $locations = $conn->query("SELECT * FROM locations ORDER BY LOCATION_NAME")->fetchAll(PDO::FETCH_ASSOC); }

    $filter_buildings = [];
    $filter_pens = [];
    if ($filter_loc) { $bld_stmt = $conn->prepare("SELECT * FROM buildings WHERE LOCATION_ID = ? ORDER BY BUILDING_NAME"); $bld_stmt->execute([$filter_loc]); $filter_buildings = $bld_stmt->fetchAll(PDO::FETCH_ASSOC); }
    if ($filter_bld) { $pen_stmt = $conn->prepare("SELECT * FROM pens WHERE BUILDING_ID = ? ORDER BY PEN_NAME"); $pen_stmt->execute([$filter_bld]); $filter_pens = $pen_stmt->fetchAll(PDO::FETCH_ASSOC); }

    $total_pages = ceil($stats['total_heads'] / $limit);

} catch (Exception $e) {
    $animals = [];
    $stats = [];
    error_log($e->getMessage());
}

// Count active filters
$active_filters = 0;
if ($date_from || $date_to) $active_filters++;
if ($status) $active_filters++;
if ($animal_type) $active_filters++;
if ($breed) $active_filters++;
if ($stage) $active_filters++;
if ($sex) $active_filters++;
if ($sow_status) $active_filters++;
if ($filter_loc) $active_filters++;
if ($filter_bld) $active_filters++;
if ($filter_pen) $active_filters++;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Animal Inventory Report</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" type="text/css" href="https://npmcdn.com/flatpickr/dist/themes/dark.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.31/jspdf.plugin.autotable.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />

    <style>
        /* ─── CSS VARIABLES ─── */
        :root {
            --bg-base:        #080f1a;
            --bg-surface:     #0d1829;
            --bg-elevated:    #111f35;
            --bg-hover:       #162540;
            --border:         rgba(255,255,255,0.07);
            --border-active:  rgba(34,197,94,0.5);
            --green:          #22c55e;
            --green-dim:      rgba(34,197,94,0.12);
            --green-glow:     rgba(34,197,94,0.25);
            --gold:           #f59e0b;
            --gold-dim:       rgba(245,158,11,0.12);
            --blue:           #38bdf8;
            --blue-dim:       rgba(56,189,248,0.12);
            --pink:           #f472b6;
            --pink-dim:       rgba(244,114,182,0.1);
            --red:            #f87171;
            --red-dim:        rgba(248,113,113,0.12);
            --text-primary:   #f1f5f9;
            --text-secondary: #94a3b8;
            --text-muted:     #475569;
            --radius-sm:      6px;
            --radius-md:      10px;
            --radius-lg:      14px;
            --radius-xl:      20px;
            --shadow-sm:      0 1px 3px rgba(0,0,0,0.4);
            --shadow-md:      0 4px 16px rgba(0,0,0,0.4);
            --shadow-lg:      0 8px 32px rgba(0,0,0,0.5);
            --font:           'DM Sans', system-ui, sans-serif;
            --font-mono:      'DM Mono', monospace;
            --transition:     0.18s cubic-bezier(0.4,0,0.2,1);
        }

        /* ─── RESET & BASE ─── */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: var(--font);
            background: var(--bg-base);
            color: var(--text-primary);
            min-height: 100vh;
            padding-bottom: 60px;
            background-image:
                radial-gradient(ellipse 80% 50% at 50% -20%, rgba(34,197,94,0.06) 0%, transparent 60%),
                radial-gradient(ellipse 40% 30% at 85% 10%, rgba(56,189,248,0.04) 0%, transparent 50%);
        }

        .container {
            max-width: 1560px;
            margin: 0 auto;
            padding: 2rem 1.5rem;
        }

        /* ─── TOP BAR ─── */
        .top-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 2rem;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            color: var(--text-secondary);
            font-size: 0.875rem;
            font-weight: 500;
            padding: 8px 14px;
            background: var(--bg-elevated);
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            transition: all var(--transition);
        }
        .back-link:hover { color: var(--text-primary); border-color: var(--border-active); background: var(--bg-hover); }
        .back-link i { font-size: 0.8rem; }

        .page-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--green);
            background: var(--green-dim);
            border: 1px solid rgba(34,197,94,0.2);
            padding: 6px 12px;
            border-radius: 99px;
        }

        /* ─── PAGE HEADER ─── */
        .page-header {
            margin-bottom: 2rem;
        }
        .page-title {
            font-size: clamp(1.6rem, 3vw, 2.2rem);
            font-weight: 700;
            color: var(--text-primary);
            letter-spacing: -0.03em;
            line-height: 1.1;
        }
        .page-title span {
            background: linear-gradient(135deg, var(--green), #16a34a);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .page-subtitle {
            color: var(--text-secondary);
            font-size: 0.9rem;
            margin-top: 6px;
        }

        /* ─── STAT CARDS ─── */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .stat-card {
            background: var(--bg-surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 1.25rem 1.5rem;
            position: relative;
            overflow: hidden;
            transition: border-color var(--transition), transform var(--transition);
        }
        .stat-card::before {
            content: '';
            position: absolute;
            inset: 0;
            opacity: 0;
            transition: opacity var(--transition);
        }
        .stat-card:hover { transform: translateY(-1px); }
        .stat-card:hover::before { opacity: 1; }

        .stat-card.green::before  { background: linear-gradient(135deg, rgba(34,197,94,0.04), transparent); }
        .stat-card.gold::before   { background: linear-gradient(135deg, rgba(245,158,11,0.04), transparent); }
        .stat-card.blue::before   { background: linear-gradient(135deg, rgba(56,189,248,0.04), transparent); }

        .stat-card.green { border-color: rgba(34,197,94,0.15); }
        .stat-card.gold  { border-color: rgba(245,158,11,0.15); }
        .stat-card.blue  { border-color: rgba(56,189,248,0.15); }

        .stat-icon {
            width: 32px; height: 32px;
            border-radius: var(--radius-sm);
            display: flex; align-items: center; justify-content: center;
            font-size: 0.85rem;
            margin-bottom: 0.75rem;
        }
        .stat-icon.green { background: var(--green-dim); color: var(--green); }
        .stat-icon.gold  { background: var(--gold-dim);  color: var(--gold); }
        .stat-icon.blue  { background: var(--blue-dim);  color: var(--blue); }

        .stat-val {
            font-size: 1.6rem;
            font-weight: 700;
            letter-spacing: -0.03em;
            line-height: 1;
            margin-bottom: 4px;
        }
        .stat-val.green { color: var(--green); }
        .stat-val.gold  { color: var(--gold); }
        .stat-val.blue  { color: var(--blue); }

        .stat-lbl {
            font-size: 0.75rem;
            color: var(--text-muted);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .type-list { list-style: none; text-align: left; }
        .type-list li {
            display: flex; justify-content: space-between; align-items: center;
            padding: 4px 0;
            border-bottom: 1px solid var(--border);
            font-size: 0.82rem;
        }
        .type-list li:last-child { border-bottom: none; }
        .type-name { color: var(--text-secondary); }
        .type-count { font-weight: 600; color: var(--text-primary); font-family: var(--font-mono); }

        /* ─── SOW STATS ─── */
        .sow-panel {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 1px;
            background: rgba(244,114,182,0.2);
            border: 1px solid rgba(244,114,182,0.25);
            border-radius: var(--radius-lg);
            overflow: hidden;
            margin-bottom: 1.5rem;
        }
        .sow-cell {
            background: var(--bg-surface);
            padding: 1.25rem;
            text-align: center;
        }
        .sow-cell-val { font-size: 1.5rem; font-weight: 700; letter-spacing: -0.03em; color: var(--text-primary); }
        .sow-cell-lbl { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.06em; color: var(--pink); margin-top: 4px; font-weight: 600; }

        /* ─── FILTER PANEL ─── */
        .filter-panel {
            background: var(--bg-surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-xl);
            margin-bottom: 2rem;
            overflow: hidden;
        }

        .filter-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--border);
            gap: 1rem;
            flex-wrap: wrap;
            cursor: pointer;
            user-select: none;
        }
        .filter-header-left {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .filter-header-title {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--text-primary);
        }
        .filter-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 20px; height: 20px;
            font-size: 0.7rem;
            font-weight: 700;
            background: var(--green);
            color: #000;
            border-radius: 99px;
            padding: 0 6px;
        }
        .filter-toggle-btn {
            display: flex; align-items: center; gap: 6px;
            font-size: 0.8rem; font-weight: 500;
            color: var(--text-secondary);
            background: none; border: none; cursor: pointer;
            transition: color var(--transition);
        }
        .filter-toggle-btn:hover { color: var(--text-primary); }
        .filter-toggle-btn i { transition: transform 0.25s ease; }
        .filter-toggle-btn.collapsed i { transform: rotate(-90deg); }

        .filter-body {
            padding: 1.5rem;
            display: grid;
            transition: all 0.25s ease;
        }
        .filter-body.hidden { display: none; }

        /* Filter section labels */
        .filter-section-label {
            grid-column: 1 / -1;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--text-muted);
            padding-bottom: 0.5rem;
            border-bottom: 1px solid var(--border);
            margin-bottom: 0.25rem;
            margin-top: 0.5rem;
        }
        .filter-section-label:first-child { margin-top: 0; }

        .filter-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
            align-items: start;
        }

        /* ─── FORM CONTROLS ─── */
        .form-group { display: flex; flex-direction: column; gap: 6px; }

        .form-label {
            font-size: 0.72rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .form-label.accent { color: var(--green); }
        .form-label i { font-size: 0.65rem; opacity: 0.7; }

        .form-control {
            width: 100%;
            padding: 0 12px;
            height: 40px;
            background: var(--bg-elevated);
            border: 1px solid var(--border);
            color: var(--text-primary);
            border-radius: var(--radius-md);
            font-size: 0.875rem;
            font-family: var(--font);
            outline: none;
            transition: border-color var(--transition), box-shadow var(--transition), background var(--transition);
            appearance: none;
            -webkit-appearance: none;
        }

        select.form-control {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2394a3b8' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            padding-right: 36px;
            cursor: pointer;
        }

        .form-control:focus {
            border-color: var(--border-active);
            box-shadow: 0 0 0 3px var(--green-glow);
            background: var(--bg-hover);
        }

        .form-control:disabled {
            opacity: 0.38;
            cursor: not-allowed;
            pointer-events: none;
        }

        .form-control.locked {
            border-color: rgba(34,197,94,0.3);
            background: rgba(34,197,94,0.04);
            pointer-events: none;
            opacity: 0.7;
        }

        .form-control option {
            background: #1e293b;
            color: var(--text-primary);
        }

        /* Input group for date pickers / dual selects */
        .input-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 6px;
        }

        /* ─── FILTER ACTIONS ─── */
        .filter-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem 1.5rem;
            border-top: 1px solid var(--border);
            flex-wrap: wrap;
            gap: 1rem;
        }
        .filter-footer-left { display: flex; gap: 8px; flex-wrap: wrap; }
        .filter-footer-right { display: flex; gap: 8px; flex-wrap: wrap; }

        /* ─── BUTTONS ─── */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            padding: 0 16px;
            height: 38px;
            border-radius: var(--radius-md);
            font-size: 0.8rem;
            font-weight: 600;
            font-family: var(--font);
            border: 1px solid transparent;
            cursor: pointer;
            transition: all var(--transition);
            text-decoration: none;
            white-space: nowrap;
            letter-spacing: 0.01em;
        }
        .btn i { font-size: 0.75rem; }

        .btn-primary {
            background: var(--green);
            color: #000;
            border-color: var(--green);
        }
        .btn-primary:hover { background: #16a34a; box-shadow: 0 0 16px var(--green-glow); }

        .btn-ghost {
            background: transparent;
            color: var(--text-secondary);
            border-color: var(--border);
        }
        .btn-ghost:hover { background: var(--bg-elevated); color: var(--text-primary); border-color: rgba(255,255,255,0.15); }

        .btn-pdf   { background: #1d4ed8; color: #fff; border-color: #1d4ed8; }
        .btn-pdf:hover { background: #1e40af; box-shadow: 0 0 12px rgba(29,78,216,0.4); }

        .btn-excel { background: #059669; color: #fff; border-color: #059669; }
        .btn-excel:hover { background: #047857; box-shadow: 0 0 12px rgba(5,150,105,0.4); }

        .btn-csv   { background: #b45309; color: #fff; border-color: #b45309; }
        .btn-csv:hover { background: #92400e; box-shadow: 0 0 12px rgba(180,83,9,0.35); }

        .btn-sm { height: 32px; padding: 0 12px; font-size: 0.75rem; }

        /* ─── TABLE ─── */
        .table-card {
            background: var(--bg-surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-xl);
            overflow: hidden;
        }

        .table-wrap { overflow-x: auto; }

        table { width: 100%; border-collapse: collapse; min-width: 1300px; }

        thead th {
            background: var(--bg-elevated);
            color: var(--text-muted);
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.07em;
            padding: 10px 14px;
            text-align: left;
            border-bottom: 1px solid var(--border);
            white-space: nowrap;
            position: sticky;
            top: 0;
            z-index: 1;
        }

        tbody tr {
            border-bottom: 1px solid var(--border);
            transition: background var(--transition);
        }
        tbody tr:last-child { border-bottom: none; }
        tbody tr:hover { background: rgba(255,255,255,0.02); }

        td {
            padding: 10px 14px;
            font-size: 0.825rem;
            color: var(--text-primary);
            white-space: nowrap;
            vertical-align: middle;
        }

        .group-header-row {
            background: rgba(34,197,94,0.06);
            border-top: 1px solid rgba(34,197,94,0.15);
        }
        .group-header-row td {
            color: var(--green);
            font-weight: 700;
            font-size: 0.8rem;
            padding: 8px 14px;
        }

        .sub-group-header-row {
            background: rgba(255,255,255,0.015);
        }
        .sub-group-header-row td {
            color: var(--text-muted);
            font-weight: 500;
            font-size: 0.78rem;
            padding: 6px 14px 6px 28px;
            font-style: italic;
        }

        /* ─── BADGES ─── */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 9px;
            border-radius: 99px;
            font-size: 0.7rem;
            font-weight: 700;
            letter-spacing: 0.03em;
        }
        .b-active  { background: var(--green-dim); color: var(--green); border: 1px solid rgba(34,197,94,0.2); }
        .b-sold    { background: var(--gold-dim);  color: var(--gold);  border: 1px solid rgba(245,158,11,0.2); }
        .b-dec     { background: var(--red-dim);   color: var(--red);   border: 1px solid rgba(248,113,113,0.2); }

        /* ─── VALUE CELLS ─── */
        .val-money  { font-family: var(--font-mono); color: var(--gold);  font-size: 0.8rem; }
        .val-cost   { font-family: var(--font-mono); color: var(--red);   font-size: 0.8rem; }
        .val-total  { font-family: var(--font-mono); color: var(--green); font-size: 0.8rem; font-weight: 600; }
        .val-weight { font-family: var(--font-mono); color: var(--blue);  font-size: 0.8rem; }

        /* ─── REPRO INFO ─── */
        .repro-status {
            font-size: 0.78rem;
            font-weight: 600;
            color: var(--pink);
        }
        .repro-chips {
            display: flex;
            gap: 4px;
            margin-top: 4px;
            flex-wrap: wrap;
        }
        .repro-chip {
            font-size: 0.65rem;
            font-weight: 600;
            padding: 1px 6px;
            border-radius: 4px;
            background: rgba(255,255,255,0.05);
            border: 1px solid var(--border);
            color: var(--text-muted);
            font-family: var(--font-mono);
        }

        /* ─── LEDGER BUTTON ─── */
        .btn-ledger {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 10px;
            background: var(--green-dim);
            border: 1px solid rgba(34,197,94,0.25);
            color: var(--green);
            border-radius: var(--radius-sm);
            font-size: 0.72rem;
            font-weight: 600;
            text-decoration: none;
            transition: all var(--transition);
            white-space: nowrap;
        }
        .btn-ledger:hover {
            background: rgba(34,197,94,0.22);
            color: #fff;
            border-color: var(--green);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px var(--green-glow);
        }

        /* ─── GROUP CARDS (Building/Pen View) ─── */
        .group-card {
            background: var(--bg-surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-xl);
            margin-bottom: 1.5rem;
            overflow: hidden;
            transition: border-color var(--transition);
        }
        .group-card:hover { border-color: rgba(255,255,255,0.12); }

        .group-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1.25rem 1.5rem;
            background: var(--bg-elevated);
            border-bottom: 1px solid var(--border);
            gap: 1rem;
            flex-wrap: wrap;
        }
        .group-card-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--green);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .group-card-title i { opacity: 0.6; font-size: 0.9rem; }

        .group-stats-row {
            display: grid;
            grid-template-columns: 160px 200px 1fr;
        }
        .group-stat-cell {
            padding: 1.25rem 1.5rem;
            border-right: 1px solid var(--border);
        }
        .group-stat-cell:last-child { border-right: none; }
        .group-stat-val { font-size: 1.4rem; font-weight: 700; letter-spacing: -0.02em; margin-bottom: 4px; }
        .group-stat-lbl { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.06em; color: var(--text-muted); font-weight: 600; }

        .class-list { display: flex; flex-direction: column; gap: 3px; }
        .class-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.8rem;
            padding: 3px 0;
            border-bottom: 1px solid var(--border);
        }
        .class-row:last-child { border-bottom: none; }
        .class-row-name { color: var(--text-secondary); }
        .class-row-count { font-weight: 700; color: var(--text-primary); font-family: var(--font-mono); }

        .btn-view-pens {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 14px;
            background: var(--green-dim);
            border: 1px solid rgba(34,197,94,0.3);
            color: var(--green);
            border-radius: var(--radius-md);
            font-size: 0.8rem;
            font-weight: 600;
            text-decoration: none;
            transition: all var(--transition);
        }
        .btn-view-pens:hover { background: var(--green); color: #000; box-shadow: 0 0 16px var(--green-glow); }

        /* ─── SECTION HEADING ─── */
        .section-heading {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.25rem;
            gap: 1rem;
            flex-wrap: wrap;
        }
        .section-heading h3 {
            font-size: 0.875rem;
            font-weight: 700;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }

        /* ─── PAGINATION ─── */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 4px;
            margin-top: 1.5rem;
            flex-wrap: wrap;
        }
        .page-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 36px;
            height: 36px;
            padding: 0 8px;
            border-radius: var(--radius-md);
            background: var(--bg-surface);
            border: 1px solid var(--border);
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 0.82rem;
            font-weight: 600;
            transition: all var(--transition);
        }
        .page-link:hover { background: var(--bg-elevated); color: var(--text-primary); border-color: rgba(255,255,255,0.15); }
        .page-link.active { background: var(--green); color: #000; border-color: var(--green); cursor: default; }
        .page-ellipsis { color: var(--text-muted); padding: 0 4px; font-size: 0.82rem; line-height: 36px; }

        /* ─── EMPTY STATE ─── */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--text-muted);
        }
        .empty-state i { font-size: 2.5rem; margin-bottom: 1rem; opacity: 0.4; display: block; }
        .empty-state p { font-size: 0.875rem; }

        /* ─── RESPONSIVE ─── */
        @media (max-width: 1100px) {
            .filter-grid { grid-template-columns: repeat(3, 1fr); }
            .group-stats-row { grid-template-columns: 1fr 1fr; }
            .group-stats-row .group-stat-cell:last-child { grid-column: 1 / -1; }
        }

        @media (max-width: 768px) {
            .container { padding: 1rem; }
            .filter-grid { grid-template-columns: repeat(2, 1fr); }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .filter-footer { flex-direction: column; align-items: stretch; }
            .filter-footer-left, .filter-footer-right { justify-content: stretch; }
            .filter-footer .btn { flex: 1; }
            .group-stats-row { grid-template-columns: 1fr; }
            .group-stat-cell { border-right: none; border-bottom: 1px solid var(--border); }
            .group-stat-cell:last-child { border-bottom: none; }

            /* Mobile card layout for table */
            .table-card { background: transparent; border: none; overflow: visible; }
            .table-wrap { overflow: visible; }
            table { min-width: 0; display: block; }
            thead { display: none; }
            tbody { display: block; }
            tbody tr {
                display: block;
                background: var(--bg-surface);
                border: 1px solid var(--border);
                border-radius: var(--radius-lg);
                margin-bottom: 0.75rem;
                padding: 1rem;
                box-shadow: var(--shadow-sm);
            }
            tbody tr.group-header-row,
            tbody tr.sub-group-header-row {
                padding: 0.75rem 1rem;
                border-radius: var(--radius-md);
            }
            tbody tr.group-header-row td,
            tbody tr.sub-group-header-row td {
                display: block;
                border: none;
                padding: 0;
            }
            tbody tr.group-header-row td::before,
            tbody tr.sub-group-header-row td::before { display: none; }

            td {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                gap: 1rem;
                padding: 7px 0;
                border-bottom: 1px solid var(--border);
                white-space: normal;
            }
            td:last-child { border-bottom: none; }
            td::before {
                content: attr(data-label);
                font-size: 0.7rem;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: 0.05em;
                color: var(--text-muted);
                white-space: nowrap;
                flex-shrink: 0;
                padding-top: 2px;
            }
            .btn-ledger { width: 100%; justify-content: center; margin-top: 4px; }
        }

        @media (max-width: 520px) {
            .filter-grid { grid-template-columns: 1fr; }
            .stats-grid { grid-template-columns: 1fr 1fr; }
        }

        /* ─── UTILITIES ─── */
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .col-name { font-weight: 600; color: var(--text-primary); }
        .col-sub { font-size: 0.72rem; color: var(--text-muted); margin-top: 2px; }
    </style>
</head>
<body>

<div class="container">

    <!-- Top Bar -->
    <div class="top-bar">
        <a href="reports.php" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back to Reports
        </a>
        <span class="page-badge"><i class="fa-solid fa-chart-bar"></i> Livestock Reports</span>
    </div>

    <!-- Page Header -->
    <div class="page-header">
        <h1 class="page-title">Animal <span>Inventory</span> Report</h1>
        <p class="page-subtitle">Comprehensive livestock analysis with financial metrics and reproductive data.</p>
    </div>

    <!-- Stats Cards -->
    <div class="stats-grid">
        <div class="stat-card green">
            <div class="stat-icon green"><i class="fa-solid fa-paw"></i></div>
            <div class="stat-val green"><?= number_format($stats['total_heads'] ?? 0) ?></div>
            <div class="stat-lbl">Total Heads</div>
        </div>

        <div class="stat-card" style="border-color: rgba(255,255,255,0.1);">
            <div class="stat-icon" style="background:rgba(255,255,255,0.05);color:var(--text-secondary);">
                <i class="fa-solid fa-list"></i>
            </div>
            <div style="margin-bottom: 8px;">
                <div class="stat-lbl" style="margin-bottom: 6px;">By Animal Type</div>
            </div>
            <ul class="type-list">
                <?php foreach($type_breakdown as $tname => $tcount): ?>
                <li>
                    <span class="type-name"><?= htmlspecialchars($tname) ?></span>
                    <span class="type-count"><?= number_format($tcount) ?></span>
                </li>
                <?php endforeach; ?>
                <?php if(empty($type_breakdown)): ?>
                    <li style="justify-content:center; color:var(--text-muted);">No data</li>
                <?php endif; ?>
            </ul>
        </div>

        <div class="stat-card gold">
            <div class="stat-icon gold"><i class="fa-solid fa-peso-sign"></i></div>
            <div class="stat-val gold">₱<?= number_format($stats['total_value'] ?? 0, 0) ?></div>
            <div class="stat-lbl">Total Acq. Value</div>
        </div>

        <div class="stat-card blue">
            <div class="stat-icon blue"><i class="fa-solid fa-venus-mars"></i></div>
            <div class="stat-val blue">
                <?= $stats['female_count'] ?? 0 ?><span style="color:var(--text-muted);font-size:1rem;"> / </span><?= $stats['male_count'] ?? 0 ?>
            </div>
            <div class="stat-lbl">Female / Male</div>
        </div>
    </div>

    <!-- Sow Stats Panel -->
    <?php if ($stage == '8' || $sow_status): ?>
    <div class="sow-panel">
        <div class="sow-cell">
            <div class="sow-cell-val"><?= (int)($sow_stats['dry_count'] ?? 0) ?></div>
            <div class="sow-cell-lbl">Dry</div>
        </div>
        <div class="sow-cell">
            <div class="sow-cell-val" style="color:var(--blue);"><?= (int)($sow_stats['service_count'] ?? 0) ?></div>
            <div class="sow-cell-lbl">In Service</div>
        </div>
        <div class="sow-cell">
            <div class="sow-cell-val" style="color:var(--gold);"><?= (int)($sow_stats['pregnant_count'] ?? 0) ?></div>
            <div class="sow-cell-lbl">Pregnant</div>
        </div>
        <div class="sow-cell">
            <div class="sow-cell-val" style="color:var(--green);"><?= (int)($sow_stats['birthing_count'] ?? 0) ?></div>
            <div class="sow-cell-lbl">Birthing / Farrowing</div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Filter Panel -->
    <div class="filter-panel">
        <div class="filter-header" onclick="toggleFilters()" id="filterHeader">
            <div class="filter-header-left">
                <i class="fa-solid fa-sliders" style="color:var(--text-secondary); font-size:0.85rem;"></i>
                <span class="filter-header-title">Filters &amp; Search</span>
                <?php if($active_filters > 0): ?>
                    <span class="filter-badge"><?= $active_filters ?></span>
                <?php endif; ?>
            </div>
            <button class="filter-toggle-btn" id="filterToggleBtn" type="button">
                <span id="filterToggleLabel">Collapse</span>
                <i class="fa-solid fa-chevron-down" id="filterChevron"></i>
            </button>
        </div>

        <div class="filter-body" id="filterBody">
            <form method="GET" id="filterForm">
                <input type="hidden" name="view" value="<?= htmlspecialchars($view) ?>">

                <!-- Section: Location Drill-down -->
                <div class="filter-grid" style="margin-bottom: 1.25rem;">
                    <div class="filter-section-label"><i class="fa-solid fa-location-dot"></i> &nbsp;Location</div>

                    <div class="form-group">
                        <label class="form-label accent"><i class="fa-solid fa-map-pin"></i> Location</label>
                        <select name="f_loc" id="f_loc" class="form-control <?= ($USER_LOCATION_ != 1000) ? 'locked' : '' ?>"
                            onchange="handleLocationChange()"
                            <?= ($USER_LOCATION_ != 1000) ? 'aria-readonly="true"' : '' ?>>
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
                        <label class="form-label"><i class="fa-solid fa-building"></i> Building</label>
                        <select name="f_bld" id="f_bld" class="form-control"
                            onchange="handleBuildingChange()"
                            <?= empty($filter_loc) ? 'disabled' : '' ?>>
                            <option value="">All Buildings</option>
                            <?php foreach ($filter_buildings as $bld): ?>
                                <option value="<?= $bld['BUILDING_ID'] ?>" <?= $filter_bld == $bld['BUILDING_ID'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($bld['BUILDING_NAME']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label"><i class="fa-solid fa-border-all"></i> Pen</label>
                        <select name="f_pen" id="f_pen" class="form-control"
                            <?= empty($filter_bld) ? 'disabled' : '' ?>>
                            <option value="">All Pens</option>
                            <?php foreach ($filter_pens as $pen): ?>
                                <option value="<?= $pen['PEN_ID'] ?>" <?= $filter_pen == $pen['PEN_ID'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($pen['PEN_NAME']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label"><i class="fa-solid fa-venus-mars"></i> Sex</label>
                        <select name="sex" class="form-control">
                            <option value="">All Sexes</option>
                            <option value="M" <?= $sex=='M'?'selected':'' ?>>Male</option>
                            <option value="F" <?= $sex=='F'?'selected':'' ?>>Female</option>
                        </select>
                    </div>
                </div>

                <!-- Section: Animal Details -->
                <div class="filter-grid" style="margin-bottom: 1.25rem;">
                    <div class="filter-section-label"><i class="fa-solid fa-paw"></i> &nbsp;Animal Details</div>

                    <div class="form-group">
                        <label class="form-label"><i class="fa-solid fa-tags"></i> Animal Type</label>
                        <select name="animal_type" id="animal_type" class="form-control" onchange="loadBreeds(this.value)">
                            <option value="">All Types</option>
                            <?php foreach($types as $t): ?>
                                <option value="<?= $t['ANIMAL_TYPE_ID'] ?>" <?= $animal_type == $t['ANIMAL_TYPE_ID'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($t['ANIMAL_TYPE_NAME']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label"><i class="fa-solid fa-dna"></i> Breed</label>
                        <select name="breed" id="breed" class="form-control" <?= empty($animal_type) ? 'disabled' : '' ?>>
                            <option value="">All Breeds</option>
                            <?php foreach($breeds_list as $b): ?>
                                <option value="<?= $b['BREED_ID'] ?>" <?= $breed == $b['BREED_ID'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($b['BREED_NAME']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label"><i class="fa-solid fa-layer-group"></i> Stage / Classification</label>
                        <select name="stage" class="form-control">
                            <option value="">All Stages</option>
                            <?php foreach($stages_list as $s): ?>
                                <option value="<?= $s['CLASS_ID'] ?>" <?= $stage == $s['CLASS_ID'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($s['STAGE_NAME']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label"><i class="fa-solid fa-calendar-days"></i> Birth Date Range</label>
                        <div class="input-row">
                            <input type="text" name="date_from" class="form-control date-picker"
                                value="<?= htmlspecialchars($date_from) ?>" placeholder="Start">
                            <input type="text" name="date_to" class="form-control date-picker"
                                value="<?= htmlspecialchars($date_to) ?>" placeholder="End">
                        </div>
                    </div>
                </div>

                <!-- Section: Status -->
                <div class="filter-grid">
                    <div class="filter-section-label"><i class="fa-solid fa-circle-info"></i> &nbsp;Status Filters</div>

                    <div class="form-group">
                        <label class="form-label"><i class="fa-solid fa-toggle-on"></i> Animal Status</label>
                        <select name="status" class="form-control">
                            <option value="">All Statuses</option>
                            <option value="Active" <?= $status=='Active'?'selected':'' ?>>Active Herd</option>
                            <option value="Sold" <?= $status=='Sold'?'selected':'' ?>>Sold</option>
                            <option value="Deceased" <?= $status=='Deceased'?'selected':'' ?>>Deceased / Culled</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label"><i class="fa-solid fa-heart-pulse"></i> Sow Status</label>
                        <select name="sow_status" class="form-control">
                            <option value="">All Sow Statuses</option>
                            <option value="DRY"      <?= $sow_status=='DRY'?'selected':'' ?>>Dry</option>
                            <option value="SERVICE"  <?= $sow_status=='SERVICE'?'selected':'' ?>>Serviced</option>
                            <option value="PREGNANT" <?= $sow_status=='PREGNANT'?'selected':'' ?>>Pregnant</option>
                            <option value="BIRTHING" <?= $sow_status=='BIRTHING'?'selected':'' ?>>Birthing / Farrowing</option>
                            <option value="ABORTION" <?= $sow_status=='ABORTION'?'selected':'' ?>>Abortion</option>
                        </select>
                    </div>
                </div>

            </form>
        </div>

        <div class="filter-footer">
            <div class="filter-footer-left">
                <a href="animal_report.php" class="btn btn-ghost btn-sm">
                    <i class="fa-solid fa-rotate-left"></i> Reset
                </a>
                <button type="submit" form="filterForm" class="btn btn-primary btn-sm">
                    <i class="fa-solid fa-magnifying-glass"></i> Apply Filters
                </button>
            </div>
            <div class="filter-footer-right">
                <button type="button" class="btn btn-pdf btn-sm" onclick="exportPDF()">
                    <i class="fa-solid fa-file-pdf"></i> PDF
                </button>
                <button type="button" class="btn btn-excel btn-sm" onclick="exportExcel()">
                    <i class="fa-solid fa-file-excel"></i> Excel
                </button>
                <button type="button" class="btn btn-csv btn-sm" onclick="exportCSV()">
                    <i class="fa-solid fa-file-csv"></i> CSV
                </button>
            </div>
        </div>
    </div>

    <!-- ══════════════ BUILDING VIEW ══════════════ -->
    <?php if ($view === 'building'): ?>

        <div class="section-heading">
            <h3><i class="fa-solid fa-building" style="margin-right:6px;"></i> Building Overview</h3>
        </div>

        <?php foreach ($grouped_data as $group_name => $gdata): ?>
        <div class="group-card">
            <div class="group-card-header">
                <div class="group-card-title">
                    <i class="fa-solid fa-building"></i>
                    <?= htmlspecialchars($group_name) ?>
                </div>
                <?php if($gdata['id']): ?>
                    <a href="?view=pen&f_bld=<?= $gdata['id'] ?>&status=<?= urlencode($status) ?>&f_loc=<?= urlencode($filter_loc) ?>&stage=<?= urlencode($stage) ?>&animal_type=<?= urlencode($animal_type) ?>"
                       class="btn-view-pens">
                        View Pens <i class="fa-solid fa-arrow-right"></i>
                    </a>
                <?php endif; ?>
            </div>
            <div class="group-stats-row">
                <div class="group-stat-cell">
                    <div class="group-stat-val" style="color:var(--green);"><?= number_format($gdata['count']) ?></div>
                    <div class="group-stat-lbl">Animals</div>
                </div>
                <div class="group-stat-cell">
                    <div class="group-stat-val" style="color:var(--gold);">₱<?= number_format($gdata['cost'], 0) ?></div>
                    <div class="group-stat-lbl">Total Acq. Cost</div>
                </div>
                <div class="group-stat-cell" style="text-align:left;">
                    <div class="group-stat-lbl" style="margin-bottom:8px;">Classifications</div>
                    <div class="class-list">
                        <?php foreach ($gdata['classifications'] as $cname => $ccount): ?>
                            <div class="class-row">
                                <span class="class-row-name"><?= htmlspecialchars($cname) ?></span>
                                <span class="class-row-count"><?= $ccount ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>

    <!-- ══════════════ PEN VIEW ══════════════ -->
    <?php elseif ($view === 'pen'): ?>

        <div class="section-heading">
            <h3><i class="fa-solid fa-border-all" style="margin-right:6px;"></i> Pen Breakdown</h3>
            <a href="?view=building&status=<?= urlencode($status) ?>&f_loc=<?= urlencode($filter_loc) ?>&stage=<?= urlencode($stage) ?>&animal_type=<?= urlencode($animal_type) ?>"
               class="btn btn-ghost btn-sm">
                <i class="fa-solid fa-arrow-left"></i> Back to Buildings
            </a>
        </div>

        <?php foreach ($grouped_data as $group_name => $gdata): ?>
        <div class="group-card">
            <div class="group-card-header">
                <div class="group-card-title">
                    <i class="fa-solid fa-border-all"></i>
                    <?= htmlspecialchars($group_name) ?>
                </div>
                <span class="page-badge" style="font-size:0.65rem;">Pen Summary</span>
            </div>
            <div class="group-stats-row">
                <div class="group-stat-cell">
                    <div class="group-stat-val" style="color:var(--green);"><?= number_format($gdata['count']) ?></div>
                    <div class="group-stat-lbl">Animals</div>
                </div>
                <div class="group-stat-cell">
                    <div class="group-stat-val" style="color:var(--gold);">₱<?= number_format($gdata['cost'], 0) ?></div>
                    <div class="group-stat-lbl">Total Acq. Cost</div>
                </div>
                <div class="group-stat-cell" style="text-align:left;">
                    <div class="group-stat-lbl" style="margin-bottom:8px;">Classifications</div>
                    <div class="class-list">
                        <?php foreach ($gdata['classifications'] as $cname => $ccount): ?>
                            <div class="class-row">
                                <span class="class-row-name"><?= htmlspecialchars($cname) ?></span>
                                <span class="class-row-count"><?= $ccount ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div style="padding: 1.25rem; background: rgba(0,0,0,0.15);">
                <div style="font-size:0.7rem; font-weight:700; text-transform:uppercase; letter-spacing:0.06em; color:var(--text-muted); margin-bottom:10px;">Animals in Pen</div>
                <div class="table-card">
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Tag No</th>
                                    <th>Stage</th>
                                    <th>Status</th>
                                    <th>Repro Info</th>
                                    <th class="text-right">Wt (kg)</th>
                                    <th class="text-right">Acq. Cost</th>
                                    <th class="text-right">Feed</th>
                                    <th class="text-right">Meds</th>
                                    <th class="text-right">Vacs</th>
                                    <th class="text-right">Vits</th>
                                    <th class="text-right">Chk Up</th>
                                    <th class="text-right">Total</th>
                                    <th class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($gdata['items'] as $row):
                                    $statusClass = 'b-active';
                                    if($row['CURRENT_STATUS'] == 'Sold') $statusClass = 'b-sold';
                                    if(in_array($row['CURRENT_STATUS'], ['Deceased','Cull','Dead'])) $statusClass = 'b-dec';
                                    $c_feed = $row['cost_feed']; $c_med = $row['cost_med'];
                                    $c_vac = $row['cost_vac']; $c_vit = $row['cost_vit']; $c_chk = $row['cost_chk'];
                                    $total_cost = $row['ACQUISITION_COST'] + $c_feed + $c_med + $c_vac + $c_vit + $c_chk;
                                ?>
                                <tr>
                                    <td data-label="Tag No" class="col-name"><?= htmlspecialchars($row['TAG_NO']) ?></td>
                                    <td data-label="Stage"><?= htmlspecialchars($row['STAGE_NAME'] ?? '—') ?></td>
                                    <td data-label="Status"><span class="badge <?= $statusClass ?>"><?= htmlspecialchars($row['CURRENT_STATUS']) ?></span></td>
                                    <td data-label="Repro Info">
                                        <?php if($row['curr_sow_status'] === '-' && $row['count_dry'] == 0 && $row['count_service'] == 0): ?>
                                            <span style="color:var(--text-muted);">N/A</span>
                                        <?php else: ?>
                                            <div class="repro-status"><?= htmlspecialchars($row['curr_sow_status']) ?></div>
                                            <div class="repro-chips">
                                                <span class="repro-chip" title="Dry">D:<?= $row['count_dry'] ?></span>
                                                <span class="repro-chip" title="Service">S:<?= $row['count_service'] ?></span>
                                                <span class="repro-chip" title="Pregnant">P:<?= $row['count_pregnant'] ?></span>
                                                <span class="repro-chip" title="Birthing">B:<?= $row['count_birthing'] ?></span>
                                                <span class="repro-chip" title="Abortion">A:<?= $row['count_abortion'] ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Wt (kg)" class="text-right val-weight"><?= $row['CURRENT_ACTUAL_WEIGHT'] > 0 ? $row['CURRENT_ACTUAL_WEIGHT'] : '—' ?></td>
                                    <td data-label="Acq. Cost" class="text-right val-money"><?= number_format($row['ACQUISITION_COST'], 2) ?></td>
                                    <td data-label="Feed" class="text-right val-cost"><?= number_format($c_feed, 2) ?></td>
                                    <td data-label="Meds" class="text-right val-cost"><?= number_format($c_med, 2) ?></td>
                                    <td data-label="Vacs" class="text-right val-cost"><?= number_format($c_vac, 2) ?></td>
                                    <td data-label="Vits" class="text-right val-cost"><?= number_format($c_vit, 2) ?></td>
                                    <td data-label="Chk Up" class="text-right val-cost"><?= number_format($c_chk, 2) ?></td>
                                    <td data-label="Total" class="text-right val-total">₱<?= number_format($total_cost, 2) ?></td>
                                    <td data-label="Action" class="text-center">
                                        <a href="viewAnimalLedger.php?id=<?= $row['ANIMAL_ID'] ?>" class="btn-ledger">
                                            <i class="fa-solid fa-clipboard-list"></i> Ledger
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>

    <!-- ══════════════ DETAILED VIEW ══════════════ -->
    <?php else: ?>

        <div class="table-card">
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
                            <th class="text-right">Wt (kg)</th>
                            <th class="text-right">Acq. Cost</th>
                            <th class="text-right">Feed</th>
                            <th class="text-right">Meds</th>
                            <th class="text-right">Vacs</th>
                            <th class="text-right">Vits</th>
                            <th class="text-right">Chk Up</th>
                            <th class="text-right">Total</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($animals)): ?>
                            <tr>
                                <td colspan="16">
                                    <div class="empty-state">
                                        <i class="fa-solid fa-filter-circle-xmark"></i>
                                        <p>No records found matching your filters.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php
                            $last_building = '';
                            $last_pen = '';
                            foreach($animals as $row):
                                $curr_building = $row['BUILDING_NAME'] ?: 'Unassigned Building';
                                if ($curr_building !== $last_building) {
                                    echo "<tr class='group-header-row'><td colspan='16'><i class='fa-solid fa-building' style='margin-right:8px;opacity:0.6;'></i>" . htmlspecialchars($curr_building) . "</td></tr>";
                                    $last_building = $curr_building;
                                    $last_pen = '';
                                }
                                $curr_pen = $row['PEN_NAME'] ?: 'Unassigned Pen';
                                if ($curr_pen !== $last_pen) {
                                    echo "<tr class='sub-group-header-row'><td colspan='16'><i class='fa-solid fa-border-all' style='margin-right:6px;'></i>Pen: " . htmlspecialchars($curr_pen) . "</td></tr>";
                                    $last_pen = $curr_pen;
                                }
                                $statusClass = 'b-active';
                                if($row['CURRENT_STATUS'] == 'Sold') $statusClass = 'b-sold';
                                if(in_array($row['CURRENT_STATUS'], ['Deceased','Cull','Dead'])) $statusClass = 'b-dec';
                                $c_feed = $row['cost_feed']; $c_med = $row['cost_med'];
                                $c_vac = $row['cost_vac']; $c_vit = $row['cost_vit']; $c_chk = $row['cost_chk'];
                                $total_cost = $row['ACQUISITION_COST'] + $c_feed + $c_med + $c_vac + $c_vit + $c_chk;
                            ?>
                            <tr>
                                <td data-label="Tag No" class="col-name" style="padding-left:1.75rem;"><?= htmlspecialchars($row['TAG_NO']) ?></td>
                                <td data-label="Classification">
                                    <div><?= htmlspecialchars($row['STAGE_NAME'] ?? 'Unknown') ?></div>
                                    <div class="col-sub"><?= htmlspecialchars($row['ANIMAL_TYPE_NAME']) ?></div>
                                </td>
                                <td data-label="Breed"><?= htmlspecialchars($row['BREED_NAME']) ?></td>
                                <td data-label="Sex"><?= htmlspecialchars($row['SEX']) ?></td>
                                <td data-label="Status"><span class="badge <?= $statusClass ?>"><?= htmlspecialchars($row['CURRENT_STATUS']) ?></span></td>
                                <td data-label="Repro Info">
                                    <?php if($row['curr_sow_status'] === '-' && $row['count_dry'] == 0 && $row['count_service'] == 0): ?>
                                        <span style="color:var(--text-muted);">N/A</span>
                                    <?php else: ?>
                                        <div class="repro-status"><?= htmlspecialchars($row['curr_sow_status']) ?></div>
                                        <div class="repro-chips">
                                            <span class="repro-chip" title="Dry">D:<?= $row['count_dry'] ?></span>
                                            <span class="repro-chip" title="Service">S:<?= $row['count_service'] ?></span>
                                            <span class="repro-chip" title="Pregnant">P:<?= $row['count_pregnant'] ?></span>
                                            <span class="repro-chip" title="Birthing">B:<?= $row['count_birthing'] ?></span>
                                            <span class="repro-chip" title="Abortion">A:<?= $row['count_abortion'] ?></span>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Location"><?= htmlspecialchars($row['LOCATION_NAME']) ?></td>
                                <td data-label="Wt (kg)" class="text-right val-weight"><?= $row['CURRENT_ACTUAL_WEIGHT'] > 0 ? $row['CURRENT_ACTUAL_WEIGHT'] : '—' ?></td>
                                <td data-label="Acq. Cost" class="text-right val-money"><?= number_format($row['ACQUISITION_COST'], 2) ?></td>
                                <td data-label="Feed" class="text-right val-cost"><?= number_format($c_feed, 2) ?></td>
                                <td data-label="Meds" class="text-right val-cost"><?= number_format($c_med, 2) ?></td>
                                <td data-label="Vacs" class="text-right val-cost"><?= number_format($c_vac, 2) ?></td>
                                <td data-label="Vits" class="text-right val-cost"><?= number_format($c_vit, 2) ?></td>
                                <td data-label="Chk Up" class="text-right val-cost"><?= number_format($c_chk, 2) ?></td>
                                <td data-label="Total" class="text-right val-total">₱<?= number_format($total_cost, 2) ?></td>
                                <td data-label="Action" class="text-center">
                                    <a href="viewAnimalLedger.php?id=<?= $row['ANIMAL_ID'] ?>" class="btn-ledger">
                                        <i class="fa-solid fa-clipboard-list"></i> Ledger
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination -->
        <?php if($total_pages > 1): ?>
        <div class="pagination">
            <?php
            $qp = $_GET;
            unset($qp['page_no']);
            $query_str = http_build_query($qp);
            ?>
            <?php if($page_no > 1): ?>
                <a href="?<?= $query_str ?>&page_no=<?= $page_no - 1 ?>" class="page-link">
                    <i class="fa-solid fa-chevron-left"></i>
                </a>
            <?php endif; ?>

            <?php
            $adj = 2;
            for ($i = 1; $i <= $total_pages; $i++):
                if ($i == 1 || $i == $total_pages || ($i >= $page_no - $adj && $i <= $page_no + $adj)):
            ?>
                <a href="?<?= $query_str ?>&page_no=<?= $i ?>" class="page-link <?= $i == $page_no ? 'active' : '' ?>">
                    <?= $i ?>
                </a>
            <?php
                elseif ($i == $page_no - $adj - 1 || $i == $page_no + $adj + 1):
            ?>
                <span class="page-ellipsis">…</span>
            <?php endif; endfor; ?>

            <?php if($page_no < $total_pages): ?>
                <a href="?<?= $query_str ?>&page_no=<?= $page_no + 1 ?>" class="page-link">
                    <i class="fa-solid fa-chevron-right"></i>
                </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

    <?php endif; ?>
</div>

<script>
    // ─── Flatpickr ───
    document.addEventListener('DOMContentLoaded', () => {
        flatpickr(".date-picker", {
            dateFormat: "Y-m-d",
            altInput: true,
            altFormat: "m/d/Y",
            allowInput: true
        });
    });

    // ─── Filter Panel Toggle ───
    let filterOpen = true;
    function toggleFilters() {
        filterOpen = !filterOpen;
        const body = document.getElementById('filterBody');
        const btn  = document.getElementById('filterToggleBtn');
        const label = document.getElementById('filterToggleLabel');
        const chevron = document.getElementById('filterChevron');

        body.classList.toggle('hidden', !filterOpen);
        btn.classList.toggle('collapsed', !filterOpen);
        label.textContent = filterOpen ? 'Collapse' : 'Expand';
    }

    // ─── Cascading Dropdowns ───
    function handleLocationChange() {
        const bld = document.getElementById('f_bld');
        const pen = document.getElementById('f_pen');
        if (bld) { bld.value = ""; bld.disabled = true; }
        if (pen) { pen.value = ""; pen.disabled = true; }
        document.getElementById('filterForm').submit();
    }

    function handleBuildingChange() {
        const pen = document.getElementById('f_pen');
        if (pen) { pen.value = ""; pen.disabled = true; }
        document.getElementById('filterForm').submit();
    }

    function loadBreeds(typeId) {
        const brd = document.getElementById('breed');
        brd.innerHTML = '<option value="">All Breeds</option>';
        brd.disabled = true;
        if (!typeId) return;
        fetch(`../process/getBreedsByAnimalType.php?animal_type_id=${typeId}`)
            .then(r => r.json())
            .then(data => {
                if (data.breeds) {
                    data.breeds.forEach(b => brd.add(new Option(b.BREED_NAME, b.BREED_ID)));
                    brd.disabled = false;
                }
            });
    }

    // ─── Exports ───
    const jsPDF  = window.jspdf.jsPDF;
    const viewMode = "<?= $view ?>";
    const records  = <?php echo json_encode($animals); ?>;

    function exportPDF() {
        const doc = new jsPDF('landscape', 'mm', 'a4');
        doc.setFontSize(16); doc.setTextColor(34, 197, 94);
        doc.text("Animal Inventory Report", 14, 14);
        doc.setFontSize(9); doc.setTextColor(120);
        doc.text(`View: ${viewMode.toUpperCase()}  |  Generated: ${new Date().toLocaleString()}`, 14, 21);

        const rows = records.map(r => {
            const acq = +r.ACQUISITION_COST||0, feed=+r.cost_feed||0, med=+r.cost_med||0,
                  vac=+r.cost_vac||0, vit=+r.cost_vit||0, chk=+r.cost_chk||0;
            const total = acq+feed+med+vac+vit+chk;
            const cycles = (r.curr_sow_status!=='-'||r.count_dry>0)
                ? `${r.curr_sow_status} D:${r.count_dry} S:${r.count_service} P:${r.count_pregnant} B:${r.count_birthing} A:${r.count_abortion}` : 'N/A';
            return [r.TAG_NO, r.STAGE_NAME||'-', r.SEX, r.BIRTH_DATE_FMT||'-', r.CURRENT_STATUS,
                cycles, r.LOCATION_NAME, r.CURRENT_ACTUAL_WEIGHT,
                acq.toFixed(2), feed.toFixed(2), med.toFixed(2), vac.toFixed(2), vit.toFixed(2), chk.toFixed(2), total.toFixed(2)];
        });
        doc.autoTable({
            head: [['Tag','Stage','Sex','Birthday','Status','Repro','Location','Wt','Acq','Feed','Meds','Vacs','Vits','Chk','Total']],
            body: rows, startY: 26,
            styles: { fontSize: 6, cellPadding: 1.5 },
            headStyles: { fillColor: [22, 163, 74] }
        });
        doc.save('Animal_Report.pdf');
    }

    function exportExcel() {
        const data = records.map(r => {
            const acq=+r.ACQUISITION_COST||0, feed=+r.cost_feed||0, med=+r.cost_med||0,
                  vac=+r.cost_vac||0, vit=+r.cost_vit||0, chk=+r.cost_chk||0;
            return {
                'Tag No': r.TAG_NO, 'Type': r.ANIMAL_TYPE_NAME, 'Breed': r.BREED_NAME,
                'Stage': r.STAGE_NAME, 'Sex': r.SEX, 'Birthday': r.BIRTH_DATE_FMT||'-',
                'Status': r.CURRENT_STATUS, 'Sow Status': r.curr_sow_status,
                'Dry': r.count_dry, 'Service': r.count_service, 'Pregnant': r.count_pregnant,
                'Birthing': r.count_birthing, 'Abortion': r.count_abortion,
                'Last Boar': r.last_boar_tag||'N/A', 'Last Service': r.last_service_date||'N/A',
                'Alive Piglets': r.total_alive||0, 'Dead Piglets': r.total_dead||0, 'Mummified': r.total_mummified||0,
                'Location': `${r.LOCATION_NAME} - ${r.PEN_NAME}`, 'Weight': r.CURRENT_ACTUAL_WEIGHT,
                'Acq (PHP)': acq, 'Feed (PHP)': feed, 'Meds (PHP)': med,
                'Vacs (PHP)': vac, 'Vits (PHP)': vit, 'Checkup (PHP)': chk,
                'Total (PHP)': acq+feed+med+vac+vit+chk
            };
        });
        const ws = XLSX.utils.json_to_sheet(data);
        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, "Inventory");
        XLSX.writeFile(wb, "Animal_Report.xlsx");
    }

    function exportCSV() {
        const headers = "Tag No,Type,Breed,Stage,Sex,Birthday,Status,Sow Status,Dry,Service,Pregnant,Birthing,Abortion,Last Boar,Last Service,Alive Piglets,Dead Piglets,Mummified,Location,Weight,Acq,Feed,Meds,Vacs,Vits,Checkup,Total\n";
        const rows = records.map(r => {
            const acq=+r.ACQUISITION_COST||0, feed=+r.cost_feed||0, med=+r.cost_med||0,
                  vac=+r.cost_vac||0, vit=+r.cost_vit||0, chk=+r.cost_chk||0;
            return [
                r.TAG_NO, r.ANIMAL_TYPE_NAME, r.BREED_NAME, r.STAGE_NAME, r.SEX, r.BIRTH_DATE_FMT||'-',
                r.CURRENT_STATUS, r.curr_sow_status, r.count_dry, r.count_service,
                r.count_pregnant, r.count_birthing, r.count_abortion,
                r.last_boar_tag||'N/A', r.last_service_date||'N/A',
                r.total_alive||0, r.total_dead||0, r.total_mummified||0,
                `${r.LOCATION_NAME} - ${r.PEN_NAME}`, r.CURRENT_ACTUAL_WEIGHT,
                acq.toFixed(2), feed.toFixed(2), med.toFixed(2), vac.toFixed(2), vit.toFixed(2), chk.toFixed(2),
                (acq+feed+med+vac+vit+chk).toFixed(2)
            ].map(e => `"${e}"`).join(',');
        }).join('\n');
        const blob = new Blob([headers + rows], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url; a.download = 'Animal_Report.csv';
        document.body.appendChild(a); a.click();
        document.body.removeChild(a); URL.revokeObjectURL(url);
    }
</script>
</body>
</html>