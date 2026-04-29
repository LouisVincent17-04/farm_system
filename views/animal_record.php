<?php
// views/animal_records.php
error_reporting(0);
ini_set('display_errors', 0);
$page = "admin_dashboard"; // Active Tab
include '../config/Connection.php';

include '../security/checkAccess.php';
checkAccess('animal_record');

include '../common/navbar.php';
include '../common/chat_support.php';
include '../functions/getUsersLocation.php';

// --- 1. HANDLING FILTERS ---
$filter_loc  = ($USER_LOCATION_ != 1000) ? $USER_LOCATION_ : ($_GET['f_loc'] ?? '');
$filter_bld  = $_GET['f_bld'] ?? '';
$filter_pen  = $_GET['f_pen'] ?? '';
$filter_type = $_GET['f_type'] ?? '';
$filter_brd  = $_GET['f_brd'] ?? '';
$filter_sex  = $_GET['f_sex'] ?? '';
$filter_stat = $_GET['f_status'] ?? 'Active'; // Default to active animals
$filter_acq  = $_GET['f_acq'] ?? ''; 

// --- PAGINATION SETUP ---
$limit = 50; 
$page_no = isset($_GET['page_no']) ? max(1, (int)$_GET['page_no']) : 1;
$offset = ($page_no - 1) * $limit;
$total_pages = 1;
$total_records = 0;

$animal_data = [];
$animal_types = [];
$locations = [];
$filter_buildings = [];
$filter_pens = [];
$filter_breeds = [];

try {
    if (!isset($conn)) { throw new Exception("Database connection failed."); }

    // --- 2. FETCH DROPDOWN DATA ---
    $animal_types = $conn->query("SELECT * FROM animal_type ORDER BY ANIMAL_TYPE_NAME ASC")->fetchAll(PDO::FETCH_ASSOC);
    
    if ($USER_LOCATION_ != 1000) {
        $loc_stmt = $conn->prepare("SELECT * FROM locations WHERE LOCATION_ID = ? ORDER BY LOCATION_NAME ASC");
        $loc_stmt->execute([$USER_LOCATION_]);
        $locations = $loc_stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $locations = $conn->query("SELECT * FROM locations ORDER BY LOCATION_NAME ASC")->fetchAll(PDO::FETCH_ASSOC);
    }

    if ($filter_loc) {
        $stmt = $conn->prepare("SELECT * FROM buildings WHERE LOCATION_ID = ? ORDER BY BUILDING_NAME");
        $stmt->execute([$filter_loc]);
        $filter_buildings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    if ($filter_bld) {
        $stmt = $conn->prepare("SELECT * FROM pens WHERE BUILDING_ID = ? ORDER BY PEN_NAME");
        $stmt->execute([$filter_bld]);
        $filter_pens = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    if ($filter_type) {
        $stmt = $conn->prepare("SELECT * FROM breeds WHERE ANIMAL_TYPE_ID = ? ORDER BY BREED_NAME");
        $stmt->execute([$filter_type]);
        $filter_breeds = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // --- 3. FETCH ANIMALS (WITH PAGINATION) ---
    if (!empty($filter_loc) || !empty($filter_bld) || !empty($filter_pen) || !empty($filter_type) || !empty($filter_brd) || !empty($filter_sex) || !empty($filter_stat) || $filter_acq !== '') {
        
        $where_sql = " WHERE 1=1";
        $params = [];

        // Apply Hierarchical Filters
        if ($filter_loc) { $where_sql .= " AND a.LOCATION_ID = :loc"; $params[':loc'] = $filter_loc; }
        if ($filter_bld) { $where_sql .= " AND a.BUILDING_ID = :bld"; $params[':bld'] = $filter_bld; }
        if ($filter_pen) { $where_sql .= " AND a.PEN_ID = :pen"; $params[':pen'] = $filter_pen; }
        
        // Apply Specific Filters
        if ($filter_type) { $where_sql .= " AND a.ANIMAL_TYPE_ID = :type"; $params[':type'] = $filter_type; }
        if ($filter_brd)  { $where_sql .= " AND a.BREED_ID = :brd"; $params[':brd'] = $filter_brd; }
        if ($filter_sex)  { $where_sql .= " AND a.SEX = :sex"; $params[':sex'] = $filter_sex; }
        if ($filter_stat) { $where_sql .= " AND a.CURRENT_STATUS = :stat"; $params[':stat'] = $filter_stat; }
        if ($filter_acq !== '') { $where_sql .= " AND a.IS_PURCHASED = :acq"; $params[':acq'] = $filter_acq; }

        // Get Total Count for Pagination
        $count_sql = "SELECT COUNT(*) FROM animal_records a " . $where_sql;
        $count_stmt = $conn->prepare($count_sql);
        foreach($params as $key => $val) { $count_stmt->bindValue($key, $val); }
        $count_stmt->execute();
        $total_records = $count_stmt->fetchColumn();
        $total_pages = ceil($total_records / $limit);

        // Fetch Data for Current Page
        $sql = "SELECT 
                    a.ANIMAL_ID, a.TAG_NO, a.SEX, a.BIRTH_DATE, a.CURRENT_STATUS, 
                    a.LOCATION_ID, a.BUILDING_ID, a.PEN_ID, a.ANIMAL_TYPE_ID, a.BREED_ID, a.ANIMAL_ITEM_ID,
                    a.WEIGHT_AT_BIRTH, a.CURRENT_ESTIMATED_WEIGHT, a.CURRENT_ACTUAL_WEIGHT, a.ACQUISITION_COST,
                    a.MOTHER_ID, a.FATHER_ID, a.IS_PURCHASED,
                    at.ANIMAL_TYPE_NAME, b.BREED_NAME, l.LOCATION_NAME, 
                    ac.STAGE_NAME,  
                    bld.BUILDING_NAME, p.PEN_NAME,
                    m.TAG_NO as MOTHER_TAG,
                    f.TAG_NO as FATHER_TAG,
                    DATEDIFF(NOW(), a.BIRTH_DATE) AS DAYS_OLD 
                FROM animal_records a
                LEFT JOIN animal_type at ON a.ANIMAL_TYPE_ID = at.ANIMAL_TYPE_ID
                LEFT JOIN breeds b ON a.BREED_ID = b.BREED_ID
                LEFT JOIN locations l ON a.LOCATION_ID = l.LOCATION_ID
                LEFT JOIN buildings bld ON a.BUILDING_ID = bld.BUILDING_ID
                LEFT JOIN pens p ON a.PEN_ID = p.PEN_ID
                LEFT JOIN animal_classifications ac ON a.CLASS_ID = ac.CLASS_ID 
                LEFT JOIN animal_records m ON a.MOTHER_ID = m.ANIMAL_ID 
                LEFT JOIN animal_records f ON a.FATHER_ID = f.ANIMAL_ID 
                " . $where_sql . " ORDER BY a.ANIMAL_ID DESC LIMIT :limit OFFSET :offset";
        
        $stmt = $conn->prepare($sql);
        foreach($params as $key => $val) { $stmt->bindValue($key, $val); }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $animal_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (Exception $e) {
    echo "<script>console.error('Database Error: " . addslashes($e->getMessage()) . "');</script>";
}

// Function to generate pagination links while keeping active filters
function getPaginationUrl($page_num) {
    $params = $_GET;
    $params['page_no'] = $page_num;
    return '?' . http_build_query($params);
}

// Count active filters for UI
$active_filters = 0;
if ($filter_loc !== '' && $USER_LOCATION_ == 1000) $active_filters++;
if ($filter_bld !== '') $active_filters++;
if ($filter_pen !== '') $active_filters++;
if ($filter_type !== '') $active_filters++;
if ($filter_brd !== '') $active_filters++;
if ($filter_sex !== '') $active_filters++;
if ($filter_stat !== 'Active') $active_filters++;
if ($filter_acq !== '') $active_filters++;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Animal Record Management | FarmPro</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" type="text/css" href="https://npmcdn.com/flatpickr/dist/themes/dark.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />

    <style>
        /* ─── CSS VARIABLES ─── */
        :root {
            --bg-base:        #080f1a;
            --bg-surface:     #0d1829;
            --bg-elevated:    #111f35;
            --bg-hover:       #162540;
            --border:         rgba(255,255,255,0.07);
            --border-active:  rgba(59,130,246,0.5); /* Blue Accent */
            --primary:        #3b82f6;
            --primary-dim:    rgba(59,130,246,0.12);
            --primary-glow:   rgba(59,130,246,0.25);
            --amber:          #f59e0b;
            --amber-dim:      rgba(245,158,11,0.12);
            --amber-glow:     rgba(245,158,11,0.25);
            --green:          #10b981;
            --green-dim:      rgba(16,185,129,0.12);
            --red:            #f87171;
            --red-dim:        rgba(248,113,113,0.12);
            --pink:           #f472b6;
            --pink-dim:       rgba(244,114,182,0.12);
            --slate:          #64748b;
            --slate-dim:      rgba(100,116,139,0.12);
            --text-primary:   #f1f5f9;
            --text-secondary: #94a3b8;
            --text-muted:     #475569;
            --radius-md:      10px;
            --radius-lg:      14px;
            --radius-xl:      20px;
            --shadow-md:      0 4px 16px rgba(0,0,0,0.4);
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
            background-image: radial-gradient(ellipse 80% 50% at 50% -20%, rgba(59,130,246,0.06) 0%, transparent 60%);
        }
        .container { max-width: 1560px; margin: 0 auto; padding: 2rem 1.5rem; }

        /* ─── TOP BAR ─── */
        .top-bar { display: flex; align-items: center; justify-content: space-between; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem; }
        .nav-links { display: flex; gap: 10px; flex-wrap: wrap; }
        
        .back-link {
            display: inline-flex; align-items: center; gap: 8px; text-decoration: none;
            color: var(--text-secondary); font-size: 0.85rem; font-weight: 600;
            padding: 8px 14px; background: var(--bg-elevated); border: 1px solid var(--border);
            border-radius: var(--radius-md); transition: all var(--transition);
        }
        .back-link:hover { color: var(--text-primary); border-color: var(--border-active); background: var(--bg-hover); }
        .back-link.admin-link:hover { border-color: rgba(34,197,94,0.5); } /* Green hover for admin dash */

        .page-badge {
            display: inline-flex; align-items: center; gap: 6px; font-size: 0.75rem;
            font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase;
            color: var(--primary); background: var(--primary-dim); border: 1px solid rgba(59,130,246,0.2);
            padding: 6px 12px; border-radius: 99px;
        }

        /* ─── HEADER ─── */
        .page-header {
            display: flex; justify-content: space-between; align-items: flex-end;
            margin-bottom: 2rem; gap: 1rem; flex-wrap: wrap;
        }
        .header-info h1 {
            font-size: clamp(1.6rem, 3vw, 2.4rem); font-weight: 700;
            color: var(--text-primary); letter-spacing: -0.03em; line-height: 1.1; margin-bottom: 0.25rem;
        }
        .header-info h1 span {
            background: linear-gradient(135deg, var(--primary), #60a5fa);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }
        .header-info p { color: var(--text-secondary); font-size: 0.95rem; }

        .header-buttons { display: flex; gap: 10px; flex-wrap: wrap; }
        .btn {
            display: inline-flex; align-items: center; justify-content: center; gap: 8px;
            padding: 10px 20px; border-radius: var(--radius-md); font-size: 0.9rem;
            font-weight: 600; font-family: var(--font); border: 1px solid transparent;
            cursor: pointer; transition: all var(--transition); text-decoration: none; white-space: nowrap;
        }
        .btn-purchase { background: var(--primary); color: #fff; }
        .btn-purchase:hover { background: #60a5fa; box-shadow: 0 0 16px var(--primary-glow); transform: translateY(-1px); }
        .btn-existing { background: var(--amber); color: #000; }
        .btn-existing:hover { background: #fbbf24; box-shadow: 0 0 16px var(--amber-glow); transform: translateY(-1px); }
        .btn-danger { background: var(--red); color: #fff; }
        .btn-danger:hover { background: #dc2626; box-shadow: 0 0 16px var(--red-dim); }
        .btn-ghost { background: transparent; color: var(--text-secondary); border-color: var(--border); }
        .btn-ghost:hover { background: var(--bg-elevated); color: var(--text-primary); border-color: rgba(255,255,255,0.15); }
        .btn-sm { padding: 6px 12px; font-size: 0.8rem; }

        /* ─── FILTER PANEL ─── */
        .filter-panel {
            background: var(--bg-surface); border: 1px solid var(--border);
            border-radius: var(--radius-xl); margin-bottom: 1.5rem; overflow: hidden;
            box-shadow: 0 10px 30px -10px rgba(0,0,0,0.5);
        }
        .filter-header {
            display: flex; align-items: center; justify-content: space-between;
            padding: 1rem 1.5rem; border-bottom: 1px solid var(--border);
            cursor: pointer; user-select: none;
        }
        .filter-header-left { display: flex; align-items: center; gap: 10px; }
        .filter-header-title { font-size: 0.875rem; font-weight: 600; color: var(--text-primary); }
        .filter-badge {
            display: inline-flex; align-items: center; justify-content: center;
            min-width: 20px; height: 20px; font-size: 0.7rem; font-weight: 700;
            background: var(--primary); color: #fff; border-radius: 99px; padding: 0 6px;
        }
        .filter-toggle-btn {
            display: flex; align-items: center; gap: 6px; font-size: 0.8rem;
            font-weight: 500; color: var(--text-secondary); background: none; border: none; cursor: pointer;
        }
        .filter-toggle-btn i { transition: transform 0.25s ease; }
        .filter-toggle-btn.collapsed i { transform: rotate(-90deg); }

        .filter-body { padding: 1.5rem; display: grid; transition: all 0.25s ease; }
        .filter-body.hidden { display: none; }

        .filter-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; align-items: end; }
        
        .form-group { display: flex; flex-direction: column; gap: 6px; margin-bottom: 1rem;}
        .form-group.no-margin { margin-bottom: 0; }
        .form-label { font-size: 0.72rem; font-weight: 600; text-transform: uppercase; color: var(--text-secondary); letter-spacing: 0.05em; }
        
        .form-control {
            width: 100%; padding: 0 12px; height: 42px; background: var(--bg-elevated);
            border: 1px solid var(--border); color: var(--text-primary);
            border-radius: var(--radius-md); font-size: 0.9rem; font-family: var(--font);
            outline: none; transition: all var(--transition);
        }
        .form-control:focus { border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-glow); background: var(--bg-hover); }
        
        select.form-control {
            appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2394a3b8' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
            background-repeat: no-repeat; background-position: right 12px center; cursor: pointer;
        }
        .form-control:disabled, input[readonly] { opacity: 0.5; cursor: not-allowed; }
        
        .filter-footer {
            display: flex; align-items: center; justify-content: flex-end;
            padding: 1rem 1.5rem; border-top: 1px solid var(--border); gap: 10px;
        }

        /* ─── SEARCH ─── */
        .search-container { position: relative; margin-bottom: 1.5rem; }
        .search-input {
            width: 100%; padding: 14px 14px 14px 3rem; background: var(--bg-surface);
            border: 1px solid var(--border); border-radius: var(--radius-lg); color: var(--text-primary);
            font-size: 1rem; font-family: var(--font); outline: none; transition: all var(--transition);
        }
        .search-input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-glow); }
        .search-icon { position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--text-muted); width: 18px; height: 18px; }

        /* ─── TABLE ─── */
        .table-card {
            background: var(--bg-surface); border: 1px solid var(--border);
            border-radius: var(--radius-xl); overflow: hidden;
        }
        .table-wrap { overflow-x: auto; }
        .table { width: 100%; border-collapse: collapse; min-width: 1100px; }
        .table thead th {
            background: var(--bg-elevated); color: var(--text-muted);
            font-size: 0.7rem; font-weight: 700; text-transform: uppercase;
            letter-spacing: 0.07em; padding: 14px 16px; text-align: left;
            border-bottom: 1px solid var(--border); white-space: nowrap;
        }
        .table tbody tr { border-bottom: 1px solid var(--border); transition: background var(--transition); }
        .table tbody tr:hover { background: rgba(255,255,255,0.02); }
        .table td { padding: 14px 16px; font-size: 0.9rem; color: var(--text-primary); vertical-align: middle; }

        /* Typography Utilities */
        .col-name { font-weight: 700; color: #fff; font-size: 1.05rem; font-family: var(--font-mono); }
        .val-mono { font-family: var(--font-mono); color: var(--text-secondary); font-size: 0.85rem; }
        .animal-type-info { display: flex; flex-direction: column; gap: 2px; }
        .animal-type-info span { font-weight: 600; color: var(--text-primary); font-size: 0.9rem;}
        .animal-type-info small { color: var(--text-muted); font-size: 0.75rem; }
        .lineage-col span { display: block; font-family: var(--font-mono); font-size: 0.75rem; font-weight: 600; margin-bottom: 2px;}
        .dam-tag { color: var(--pink); }
        .sire-tag { color: var(--primary); }

        /* Badges */
        .status-badge {
            padding: 4px 10px; border-radius: 99px; font-size: 0.7rem;
            font-weight: 700; text-transform: uppercase; letter-spacing: 0.03em; display: inline-block;
        }
        .status-active   { background: var(--green-dim); color: var(--green); border: 1px solid rgba(34,197,94,0.2); }
        .status-sold     { background: var(--blue-dim); color: var(--blue); border: 1px solid rgba(59,130,246,0.2); }
        .status-deceased { background: var(--slate-dim); color: var(--slate); border: 1px solid rgba(100,116,139,0.2); }

        .sex-badge { padding: 4px 8px; border-radius: 6px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; }
        .sex-male { color: var(--primary); background: var(--primary-dim); }
        .sex-female { color: var(--pink); background: var(--pink-dim); }
        .sex-unknown { color: var(--text-muted); background: var(--slate-dim); }

        /* Actions */
        .actions { display: flex; gap: 8px; justify-content: flex-end; }
        .action-btn {
            width: 32px; height: 32px; border-radius: 6px;
            border: 1px solid var(--border); background: var(--bg-elevated);
            display: flex; align-items: center; justify-content: center;
            cursor: pointer; transition: all var(--transition); color: var(--text-secondary);
        }
        .action-btn:hover { background: var(--bg-hover); color: var(--text-primary); }
        .action-btn.edit:hover { color: var(--blue); border-color: var(--blue); }
        .action-btn.delete:hover { color: var(--red); border-color: var(--red); }

        /* ─── PAGINATION ─── */
        .pagination {
            display: flex; justify-content: center; align-items: center; gap: 6px;
            padding: 1.5rem; background: var(--bg-surface); border-top: 1px solid var(--border); flex-wrap: wrap;
        }
        .page-link {
            display: inline-flex; align-items: center; justify-content: center;
            min-width: 36px; height: 36px; padding: 0 12px; border-radius: var(--radius-md);
            background: var(--bg-elevated); border: 1px solid var(--border);
            color: var(--text-secondary); text-decoration: none; font-size: 0.85rem; font-weight: 600; transition: all var(--transition);
        }
        .page-link:hover:not(.disabled) { background: var(--bg-hover); color: var(--text-primary); }
        .page-link.active { background: var(--primary); color: #fff; border-color: var(--primary); pointer-events: none; }
        .page-link.disabled { opacity: 0.4; pointer-events: none; }
        .pagination .dots { color: var(--text-muted); font-weight: 700; padding: 0 4px; }
        .total-info { color: var(--text-muted); font-size: 0.85rem; margin-left: auto; font-weight: 500; }

        .empty-state { text-align: center; padding: 4rem 2rem; color: var(--text-muted); }
        .empty-state i { font-size: 2.5rem; margin-bottom: 1rem; opacity: 0.3; display: block; }
        .empty-state h3 { font-size: 1rem; color: var(--text-primary); margin-bottom: 0.5rem; font-weight: 600;}

        /* ─── MODALS ─── */
        .modal {
            display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.85);
            backdrop-filter: blur(5px); z-index: 1000; align-items: center; justify-content: center;
            padding: 1rem; overflow-y: auto;
        }
        .modal.show { display: flex; }
        #selectParentModal, #selectPurchaseModal, #editSelectPurchaseModal { z-index: 1050; }

        .modal-content {
            background: var(--bg-surface); border: 1px solid var(--border);
            border-radius: var(--radius-xl); width: 100%; max-width: 600px;
            max-height: 90vh; display: flex; flex-direction: column; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5);
            margin: auto; animation: modalZoom 0.2s ease-out;
        }
        .modal-content.large { max-width: 1000px; }
        .modal-content.narrow { max-width: 440px; }
        @keyframes modalZoom { from { transform: scale(0.95); opacity: 0; } to { transform: scale(1); opacity: 1; } }

        .modal-header {
            padding: 1.5rem; border-bottom: 1px solid var(--border);
            display: flex; justify-content: space-between; align-items: center;
        }
        .modal-header h2 { margin: 0; font-size: 1.25rem; font-weight: 700; color: #fff; }
        
        .modal-body { padding: 1.5rem; overflow-y: auto; }
        .modal-footer {
            padding: 1.25rem 1.5rem; border-top: 1px solid var(--border);
            display: flex; justify-content: flex-end; gap: 10px; background: var(--bg-elevated);
        }

        .confirm-content { text-align: center; padding: 1rem 1rem 0; }
        .confirm-icon { font-size: 3.5rem; margin-bottom: 1rem; display: block; }
        #customAlertDetails::-webkit-scrollbar { width: 6px; }
        #customAlertDetails::-webkit-scrollbar-track { background: transparent; }
        #customAlertDetails::-webkit-scrollbar-thumb { background: #475569; border-radius: 4px; }

        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        .full-width { grid-column: 1 / -1; }
        
        .input-group { display: flex; gap: 8px; }
        .input-group .form-control { flex: 1; }
        
        .section-card {
            background: rgba(255,255,255,0.02); padding: 1.25rem;
            border-radius: var(--radius-lg); border: 1px solid var(--border); margin-bottom: 1.25rem;
        }
        .section-card > .form-label {
            margin-bottom: 1rem; border-bottom: 1px solid var(--border); padding-bottom: 8px; color: var(--text-primary);
        }

        /* Bulk Table Override */
        table[id$="-add-table"] { width: 100%; border-collapse: collapse; margin-top: 10px; background: var(--bg-elevated); border-radius: var(--radius-md); overflow: hidden; border: 1px solid var(--border);}
        table[id$="-add-table"] th { background: rgba(0,0,0,0.2); font-size: 0.7rem; padding: 12px; color: var(--text-muted); text-transform: uppercase; text-align: left; border-bottom: 1px solid var(--border); white-space: nowrap;}
        table[id$="-add-table"] td { padding: 12px; border-bottom: 1px solid var(--border); vertical-align: top; }

        /* Toast UI */
        #toastContainer { position: fixed; top: 20px; right: 20px; z-index: 9999; display: flex; flex-direction: column; gap: 10px; }
        .toast {
            background: var(--bg-surface); border: 1px solid var(--border); color: #fff;
            padding: 1rem 1.5rem; border-radius: var(--radius-md); box-shadow: 0 10px 25px rgba(0,0,0,0.5);
            font-size: 0.9rem; font-weight: 600; animation: slideIn 0.3s ease-out; display: flex; align-items: center; gap: 8px;
        }
        @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }

        /* ─── RESPONSIVE ─── */
        @media (max-width: 900px) {
            .filter-grid { grid-template-columns: repeat(2, 1fr); }
            .form-row { grid-template-columns: 1fr; }
        }
        @media (max-width: 768px) {
            .container { padding: 1rem; }
            .page-header { flex-direction: column; align-items: flex-start; }
            .header-buttons { width: 100%; display: grid; grid-template-columns: 1fr; }
            .filter-grid { grid-template-columns: 1fr; }
            .filter-footer { flex-direction: column; align-items: stretch; }
            .modal-footer { flex-direction: column; }
            .modal-footer button { width: 100%; }

            /* Mobile Table to Cards */
            .table-wrap { border: none; background: transparent; overflow: visible; }
            .table { min-width: 0; display: block; }
            .table thead { display: none; }
            .table tbody { display: block; width: 100%; }
            .table tr { 
                display: block; background: var(--bg-surface); 
                border: 1px solid var(--border); border-radius: var(--radius-xl); 
                margin-bottom: 1rem; padding: 1.25rem; box-shadow: var(--shadow-sm);
            }
            .table td { 
                display: flex; justify-content: space-between; align-items: center; 
                padding: 0.6rem 0; border-bottom: 1px solid rgba(255,255,255,0.05); 
                text-align: right; white-space: normal;
            }
            .table td:last-child { border-bottom: none; justify-content: flex-end; padding-top: 10px; gap: 8px;}
            .table td::before { 
                content: attr(data-label); font-weight: 700; color: var(--text-muted); 
                font-size: 0.75rem; text-transform: uppercase; margin-right: 1rem; text-align: left; flex-shrink: 0;
            }
            .animal-type-info { align-items: flex-end; text-align: right; }
            .lineage-col span { text-align: right; }
            .pagination { flex-direction: column; }
            .pagination .total-info { margin: 10px auto 0; }
            
            table[id$="-add-table"] td::before { display:none; }
        }
    </style>
</head>
<body>
    
    <div id="toastContainer"></div>

    <div class="container">
        
        <div class="top-bar">
            <div class="nav-links">
                <a href="admin_dashboard.php" class="back-link admin-link">
                    <i class="fa-solid fa-gauge"></i> Admin Dashboard
                </a>
                <a href="farm_dashboard.php" class="back-link">
                    <i class="fa-solid fa-tractor"></i> Farm Dashboard
                </a>
            </div>
            <span class="page-badge"><i class="fa-solid fa-cow"></i> Core Records</span>
        </div>

        <div class="page-header">
            <div class="header-info">
                <h1>Animal <span>Records</span></h1>
                <p>Manage, monitor, and configure individual livestock profiles.</p>
            </div>
            <div class="header-buttons">
                <button class="btn btn-ghost" onclick="downloadSampleCSV()">
                    <i class="fa-solid fa-download"></i> Sample CSV
                </button>
                <button class="btn btn-ghost" onclick="document.getElementById('csvUpload').click()">
                    <i class="fa-solid fa-file-import"></i> Import CSV
                </button>
                <input type="file" id="csvUpload" accept=".csv" style="display:none;" onchange="uploadCSV(event)">

                <button class="btn btn-purchase" onclick="openAddModal('purchase', 1)">
                    <i class="fa-solid fa-cart-arrow-down"></i> Add Purchased Animal
                </button>
                <button class="btn btn-existing" onclick="openAddModal('existing', 0)">
                    <i class="fa-solid fa-plus"></i> Add Existing / Born
                </button>
            </div>
        </div>

        <div class="filter-panel">
            <div class="filter-header" onclick="toggleFilters()" id="filterHeader">
                <div class="filter-header-left">
                    <i class="fa-solid fa-sliders" style="color:var(--text-secondary); font-size:0.85rem;"></i>
                    <span class="filter-header-title">Advanced Scoping & Filters</span>
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
                    <div class="filter-grid">
                        
                        <div class="form-group">
                            <label class="form-label"><i class="fa-solid fa-map-pin me-1"></i> Location</label>
                            <select name="f_loc" class="form-control" onchange="this.form.submit()" <?php echo ($USER_LOCATION_ != 1000) ? 'disabled' : ''; ?>>
                                <?php if($USER_LOCATION_ == 1000): ?>
                                    <option value="">All Locations</option>
                                <?php endif; ?>
                                <?php foreach ($locations as $loc): ?>
                                    <option value="<?= $loc['LOCATION_ID'] ?>" <?= $filter_loc == $loc['LOCATION_ID'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($loc['LOCATION_NAME']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($USER_LOCATION_ != 1000): ?>
                                <input type="hidden" name="f_loc" value="<?= $USER_LOCATION_ ?>">
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label"><i class="fa-solid fa-building me-1"></i> Building</label>
                            <select name="f_bld" class="form-control" onchange="this.form.submit()" <?= empty($filter_loc) ? 'disabled' : '' ?>>
                                <option value="">All Buildings</option>
                                <?php foreach ($filter_buildings as $bld): ?>
                                    <option value="<?= $bld['BUILDING_ID'] ?>" <?= $filter_bld == $bld['BUILDING_ID'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($bld['BUILDING_NAME']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label"><i class="fa-solid fa-border-all me-1"></i> Pen</label>
                            <select name="f_pen" class="form-control" onchange="this.form.submit()" <?= empty($filter_bld) ? 'disabled' : '' ?>>
                                <option value="">All Pens</option>
                                <?php foreach ($filter_pens as $pen): ?>
                                    <option value="<?= $pen['PEN_ID'] ?>" <?= $filter_pen == $pen['PEN_ID'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($pen['PEN_NAME']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label"><i class="fa-solid fa-tags me-1"></i> Animal Type</label>
                            <select name="f_type" class="form-control" onchange="this.form.submit()">
                                <option value="">All Types</option>
                                <?php foreach ($animal_types as $type): ?>
                                    <option value="<?= $type['ANIMAL_TYPE_ID'] ?>" <?= $filter_type == $type['ANIMAL_TYPE_ID'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($type['ANIMAL_TYPE_NAME']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label"><i class="fa-solid fa-dna me-1"></i> Breed</label>
                            <select name="f_brd" class="form-control" onchange="this.form.submit()" <?= empty($filter_type) ? 'disabled' : '' ?>>
                                <option value="">All Breeds</option>
                                <?php foreach ($filter_breeds as $brd): ?>
                                    <option value="<?= $brd['BREED_ID'] ?>" <?= $filter_brd == $brd['BREED_ID'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($brd['BREED_NAME']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label"><i class="fa-solid fa-venus-mars me-1"></i> Sex</label>
                            <select name="f_sex" class="form-control" onchange="this.form.submit()">
                                <option value="">All Sexes</option>
                                <option value="M" <?= $filter_sex === 'M' ? 'selected' : '' ?>>Male</option>
                                <option value="F" <?= $filter_sex === 'F' ? 'selected' : '' ?>>Female</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label"><i class="fa-solid fa-toggle-on me-1"></i> Status</label>
                            <select name="f_status" class="form-control" onchange="this.form.submit()">
                                <option value="">All Statuses</option>
                                <option value="Active" <?= $filter_stat === 'Active' ? 'selected' : '' ?>>Active</option>
                                <option value="Sold" <?= $filter_stat === 'Sold' ? 'selected' : '' ?>>Sold</option>
                                <option value="Deceased" <?= $filter_stat === 'Deceased' ? 'selected' : '' ?>>Deceased</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label"><i class="fa-solid fa-cart-shopping me-1"></i> Acquisition Origin</label>
                            <select name="f_acq" class="form-control" onchange="this.form.submit()">
                                <option value="">All Origins</option>
                                <option value="1" <?= $filter_acq === '1' ? 'selected' : '' ?>>Purchased</option>
                                <option value="0" <?= $filter_acq === '0' ? 'selected' : '' ?>>Farm Born / Existing</option>
                            </select>
                        </div>
                        
                    </div>
                </form>
            </div>
            <div class="filter-footer">
                <a href="animal_records.php" class="btn btn-ghost">Reset Filters</a>
                <button type="submit" form="filterForm" class="btn btn-primary"><i class="fa-solid fa-filter"></i> Apply Filters</button>
            </div>
        </div>

        <div class="search-container">
            <i class="fa-solid fa-magnifying-glass search-icon"></i>
            <input type="text" class="search-input" placeholder="Quick search loaded records by tag number, classification, breed..." onkeyup="filterTable()">
        </div>

        <div class="table-card">
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Tag No</th>
                            <th>Class / Breed</th> 
                            <th>Sex</th>
                            <th>Age</th>
                            <th>Birth Date</th>
                            <th>Weight (Est / Act)</th>
                            <th>Lineage (Dam/Sire)</th>
                            <th>Status</th>
                            <th>Cost</th>
                            <th>Location</th>
                            <th style="text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="animal-table">
                        <?php if (empty($animal_data)): ?>
                            <tr>
                                <td colspan="11">
                                    <div id="empty-state-db" class="empty-state">
                                        <i class="fa-solid fa-folder-open"></i>
                                        <h3>No records found matching your active database filters.</h3>
                                        <p style="font-size:0.85rem;">Try clearing your filters or adding a new record.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($animal_data as $data): ?>
                                <tr data-id="<?php echo $data['ANIMAL_ID']; ?>">
                                    <td data-label="Tag No" class="col-name">
                                        <?php echo htmlspecialchars($data['TAG_NO']); ?>
                                    </td>
                                    <td data-label="Class / Breed">
                                        <div class="animal-type-info">
                                            <span><?php echo htmlspecialchars($data['STAGE_NAME'] ?? 'Unclassified'); ?></span>
                                            <small><?php echo htmlspecialchars($data['BREED_NAME']); ?></small>
                                        </div>
                                    </td>
                                    <td data-label="Sex">
                                        <?php 
                                            $sClass = 'unknown'; $sLbl = 'Unk';
                                            if($data['SEX'] == 'M') { $sClass = 'male'; $sLbl = 'Male'; }
                                            elseif($data['SEX'] == 'F') { $sClass = 'female'; $sLbl = 'Female'; }
                                        ?>
                                        <span class="sex-badge sex-<?= $sClass ?>"><?= $sLbl ?></span>
                                    </td>
                                    <td data-label="Age" class="val-mono" style="color:var(--amber);">
                                        <?php echo $data['DAYS_OLD'] !== null ? $data['DAYS_OLD'] . " days" : "N/A"; ?>
                                    </td>
                                    <td data-label="Birth Date" class="val-mono">
                                        <?php echo $data['BIRTH_DATE'] ? date('m/d/Y', strtotime($data['BIRTH_DATE'])) : 'N/A'; ?>
                                    </td>
                                    <td data-label="Weight (Est/Act)" class="val-mono">
                                        <span style="color:var(--primary);"><?php echo number_format($data['CURRENT_ESTIMATED_WEIGHT'], 2); ?> kg</span><br>
                                        <span style="color:var(--green);"><?php echo number_format($data['CURRENT_ACTUAL_WEIGHT'], 2); ?> kg</span>
                                    </td>
                                    <td data-label="Lineage" class="lineage-col">
                                        <span class="dam-tag">D: <?php echo $data['MOTHER_TAG'] ? $data['MOTHER_TAG'] : '-'; ?></span>
                                        <span class="sire-tag">S: <?php echo $data['FATHER_TAG'] ? $data['FATHER_TAG'] : '-'; ?></span>
                                    </td>
                                    <td data-label="Status">
                                        <span class="status-badge status-<?php echo strtolower($data['CURRENT_STATUS']); ?>">
                                            <?php echo htmlspecialchars($data['CURRENT_STATUS']); ?>
                                        </span>
                                    </td>
                                    <td data-label="Cost" class="val-money">
                                        ₱<?php echo number_format($data['ACQUISITION_COST'], 2); ?>
                                    </td>
                                    <td data-label="Location" style="font-size:0.8rem; color:var(--text-secondary); line-height:1.4;">
                                        <strong style="color:var(--text-primary);"><?php echo htmlspecialchars($data['LOCATION_NAME']); ?></strong><br>
                                        <?php echo htmlspecialchars($data['BUILDING_NAME']); ?> - <?php echo htmlspecialchars($data['PEN_NAME']); ?>
                                    </td>
                                    <td data-label="Actions">
                                        <div class="actions">
                                            <button class="action-btn edit" onclick="editAnimal(this)" title="Edit Profile">
                                                <i class="fa-solid fa-pen-to-square"></i>
                                            </button>
                                            <button class="action-btn delete" onclick="deleteAnimal(this)" title="Delete Profile">
                                                <i class="fa-solid fa-trash-can"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <div id="empty-state-js" class="empty-state" style="display: none;">
                <i class="fa-solid fa-magnifying-glass"></i>
                <h3>No visible records match your quick search.</h3>
            </div>

            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page_no > 1): ?>
                        <a href="<?= getPaginationUrl($page_no - 1) ?>" class="page-link"><i class="fa-solid fa-chevron-left me-1"></i> Prev</a>
                    <?php endif; ?>
                    
                    <?php
                    $adjacents = 2;
                    for ($i = 1; $i <= $total_pages; $i++):
                        if ($i == 1 || $i == $total_pages || ($i >= $page_no - $adjacents && $i <= $page_no + $adjacents)):
                    ?>
                            <a href="<?= getPaginationUrl($i) ?>" class="page-link <?= ($i == $page_no) ? 'active' : '' ?>" <?= ($i == $page_no) ? 'onclick="return false;"' : '' ?>><?= $i ?></a>
                    <?php 
                        elseif ($i == $page_no - $adjacents - 1 || $i == $page_no + $adjacents + 1): 
                    ?>
                            <span class="dots">...</span>
                    <?php 
                        endif;
                    endfor; 
                    ?>
                    
                    <?php if ($page_no < $total_pages): ?>
                        <a href="<?= getPaginationUrl($page_no + 1) ?>" class="page-link">Next <i class="fa-solid fa-chevron-right ms-1"></i></a>
                    <?php endif; ?>
                    
                    <span class="total-info">Total: <?= number_format($total_records) ?> records</span>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div id="customAlertModal" class="modal" style="z-index: 2000;">
        <div class="modal-content narrow">
            <div class="modal-body confirm-content" style="padding-bottom: 1.5rem;">
                <span class="confirm-icon" id="customAlertIcon"><i class="fa-solid fa-circle-xmark"></i></span>
                <h2 style="color:#fff; margin-bottom:10px;" id="customAlertTitle">Error</h2>
                <p style="color:var(--text-secondary); margin-bottom: 15px; white-space: pre-line;" id="customAlertMessage">Something went wrong.</p>
                
                <div id="customAlertDetails" style="display:none; text-align: left; background: rgba(0,0,0,0.3); padding: 12px; border-radius: 8px; border: 1px solid var(--border); max-height: 250px; overflow-y: auto; color: var(--red); font-size: 0.85rem; font-family: var(--font-mono); line-height: 1.6;">
                </div>
            </div>
            <div class="modal-footer" style="justify-content:center; border-top:none; background:transparent; padding-top:0; padding-bottom:1.75rem;">
                <button type="button" class="btn btn-ghost" style="width: 100%; max-width: 200px; background: var(--bg-elevated);" onclick="closeCustomAlert()">Close</button>
            </div>
        </div>
    </div>

    <div id="customConfirmModal" class="modal" style="z-index: 2000;">
        <div class="modal-content narrow">
            <div class="modal-body confirm-content" style="padding-bottom: 1.5rem;">
                <span class="confirm-icon" style="color:var(--amber);"><i class="fa-solid fa-triangle-exclamation"></i></span>
                <h2 style="color:#fff; margin-bottom:10px;" id="customConfirmTitle">Confirm Action</h2>
                <p style="color:var(--text-secondary); margin-bottom: 15px;" id="customConfirmMessage">Are you sure?</p>
            </div>
            <div class="modal-footer" style="justify-content:center; border-top:none; background:transparent; padding-top:0; padding-bottom:1.75rem; display: flex; gap: 10px;">
                <button type="button" class="btn btn-ghost" onclick="closeCustomConfirm()">Cancel</button>
                <button type="button" class="btn btn-primary" id="customConfirmBtn">Confirm</button>
            </div>
        </div>
    </div>

    <div id="addModal" class="modal">
        <div class="modal-content large">
            <div class="modal-header">
                <h2 id="modal-title">Add Record</h2>
            </div>
            <div class="modal-body">
                <form id="addAnimalForm">
                    <input type="hidden" id="entry_type" name="entry_type" value="existing">
                    <input type="hidden" id="acquisition_type" name="acquisition_type" value="0">

                    <div id="purchase-group" class="section-card" style="display: none; border-color: var(--primary); background: var(--primary-dim);">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <label class="form-label" style="margin:0; color:var(--primary);">Linked Purchase Records *</label>
                            <button type="button" class="btn btn-primary btn-sm" id="select_purchases" onclick="openSelectPurchaseModal()">Select Purchases</button>
                        </div>
                        <div id="bulk_selection_summary" style="font-size: 0.85rem; color: var(--text-primary); margin-top: 8px; font-weight: 500;">0 items selected</div>
                    </div>
                    
                    <div id="bulk-entry-group" class="form-group full-width" style="display: none; margin-bottom: 1.25rem;">
                        <label class="form-label">Assign Tags & Placements to Purchases:</label>
                        <div style="overflow-x: auto; width: 100%;">
                            <table id="bulk-add-table">
                                <thead>
                                    <tr>
                                        <th>Purchase Base</th>
                                        <th>Tag No *</th>
                                        <th>Sex *</th>
                                        <th>Birth Date *</th>
                                        <th>Location *</th>
                                        <th>Building *</th>
                                        <th>Pen *</th>
                                        <th width="40"></th>
                                    </tr>
                                </thead>
                                <tbody id="bulk-table-body">
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div id="existing-bulk-group" class="form-group full-width" style="display: none; margin-bottom: 1.25rem;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                            <label class="form-label" style="margin:0;">Animal Details & Placements <span style="color:var(--red);">*</span></label>
                            <button type="button" class="btn btn-ghost btn-sm" style="border: 1px solid var(--border);" onclick="addExistingRow()"><i class="fa-solid fa-plus"></i> Add Row</button>
                        </div>
                        <div style="overflow-x: auto; width: 100%;">
                            <table id="existing-add-table">
                                <thead>
                                    <tr>
                                        <th>Tag No *</th>
                                        <th>Sex *</th>
                                        <th>Birth Date *</th>
                                        <th>Acq. Cost</th>
                                        <th>Location *</th>
                                        <th>Building *</th>
                                        <th>Pen *</th>
                                        <th width="40"></th>
                                    </tr>
                                </thead>
                                <tbody id="existing-table-body">
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div id="lineage-group" class="section-card" style="display:none;">
                        <label class="form-label">Lineage History (Optional)</label>
                        <div class="lineage-row">
                            <div class="form-group no-margin">
                                <label style="color: var(--pink); font-size: 0.72rem; font-weight: 600;">Mother (Sow)</label>
                                <div class="input-group">
                                    <input type="hidden" id="add_mother_id" name="mother_id">
                                    <input type="text" id="display_mother_tag" class="form-control" placeholder="Select Sow..." readonly style="border-color: rgba(244,114,182,0.4);">
                                    <button type="button" class="btn-search action-btn" onclick="openSelectParentModal('sow')"><i class="fa-solid fa-magnifying-glass"></i></button>
                                </div>
                            </div>
                            <div class="form-group no-margin" style="margin-top:10px;">
                                <label style="color: var(--primary); font-size: 0.72rem; font-weight: 600;">Father (Boar)</label>
                                <div class="input-group">
                                    <input type="hidden" id="add_father_id" name="father_id">
                                    <input type="text" id="display_father_tag" class="form-control" placeholder="Select Boar..." readonly style="border-color: rgba(59,130,246,0.4);">
                                    <button type="button" class="btn-search action-btn" onclick="openSelectParentModal('boar')"><i class="fa-solid fa-magnifying-glass"></i></button>
                                </div>
                                <small style="color: var(--text-muted); display: block; margin-top: 6px; font-size:0.7rem; line-height: 1.3;">Will auto-apply to all siblings in this batch.</small>
                            </div>
                        </div>
                    </div>

                    <div class="form-row" style="margin-top: 10px; border-top: 1px solid var(--border); padding-top: 1.25rem;">
                        <div class="form-group">
                            <label class="form-label">Animal Type *</label>
                            <select id="add_animal_type" name="animal_type_id" class="form-control" required onchange="loadBreeds(this.value, 'add')">
                                <option value="">Select Type</option>
                                <?php foreach ($animal_types as $type): ?>
                                    <option value="<?php echo $type['ANIMAL_TYPE_ID']; ?>"><?php echo htmlspecialchars($type['ANIMAL_TYPE_NAME']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Breed Standard *</label>
                            <select id="add_breed" name="breed_id" class="form-control" required disabled>
                                <option value="">Select Type First</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Current Status *</label>
                        <select id="add_status" name="current_status" class="form-control" required>
                            <option value="Active">Active</option>
                            <option value="Sold">Sold</option>
                            <option value="Deceased">Deceased</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-ghost" onclick="closeAddModal()">Cancel</button>
                <button class="btn btn-primary" id="btn-add-save" onclick="submitAddForm(event)">Commit Record</button>
            </div>
        </div>
    </div>

    <div id="selectParentModal" class="modal">
        <div class="modal-content large">
            <div class="modal-header">
                <h2 id="parent-modal-title">Select Parent</h2>
                <button class="action-btn" onclick="closeSelectParentModal()"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="modal-body">
                <div class="table-wrap">
                    <table class="table">
                        <thead><tr><th>Tag No</th><th>Breed</th><th>Location</th><th style="text-align:center;">Action</th></tr></thead>
                        <tbody id="parent-table-body"><tr><td colspan="4" style="text-align:center;">Loading...</td></tr></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div id="selectPurchaseModal" class="modal">
        <div class="modal-content large">
            <div class="modal-header">
                <h2>Select Purchase Inventory</h2>
                <button class="action-btn" onclick="closeSelectPurchaseModal()"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="modal-body">
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th width="40" style="text-align: center;"><input type="checkbox" id="selectAllPurchases" onclick="toggleAllPurchases(this)" style="width:16px; height:16px; cursor:pointer;"></th>
                                <th>Item ID</th>
                                <th>Nomenclature</th>
                                <th>Unit Cost</th>
                                <th>Location</th>
                            </tr>
                        </thead>
                        <tbody id="add-purchase-table-body"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-ghost" onclick="closeSelectPurchaseModal()">Cancel</button>
                <button class="btn btn-primary" onclick="confirmPurchaseSelection()">Apply Selection</button>
            </div>
        </div>
    </div>

    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="edit-modal-title">Edit Profile</h2>
                <button class="action-btn" onclick="closeEditModal()"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="modal-body">
                <form id="editAnimalForm">
                    <input type="hidden" id="edit_animal_id" name="animal_id">
                    <input type="hidden" id="edit_has_purchase" name="has_purchase" value="0">

                    <div class="section-card lineage-container">
                        <label class="form-label">Lineage History</label>
                        <div class="lineage-row">
                            <div class="form-group no-margin">
                                <label style="color: var(--pink); font-size: 0.72rem; font-weight: 600;">Mother (Sow)</label>
                                <div class="input-group">
                                    <input type="hidden" id="edit_mother_id" name="mother_id">
                                    <input type="text" id="edit_display_mother" class="form-control" placeholder="Select Sow..." readonly style="border-color: rgba(244,114,182,0.4);">
                                    <button type="button" class="btn-search action-btn" onclick="openSelectParentModal('sow', 'edit')"><i class="fa-solid fa-magnifying-glass"></i></button>
                                </div>
                            </div>
                            <div class="form-group no-margin" style="margin-top:10px;">
                                <label style="color: var(--primary); font-size: 0.72rem; font-weight: 600;">Father (Boar)</label>
                                <div class="input-group">
                                    <input type="hidden" id="edit_father_id" name="father_id">
                                    <input type="text" id="edit_display_father" class="form-control" placeholder="Select Boar..." readonly style="border-color: rgba(59,130,246,0.4);">
                                    <button type="button" class="btn-search action-btn" onclick="openSelectParentModal('boar', 'edit')"><i class="fa-solid fa-magnifying-glass"></i></button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="edit-purchase-group" class="form-group full-width section-card" style="display: none; border-color: var(--primary);">
                        <label class="form-label" style="color:var(--primary);">Linked Purchase Origin *</label>
                        <div class="input-group">
                            <input type="text" id="edit_animal_item_id" class="form-control val-mono" name="animal_item_id" placeholder="Select a purchase record..." readonly>
                            <button type="button" class="btn-search btn btn-ghost btn-sm" style="border-color:var(--border);" onclick="openEditSelectPurchaseModal()">Change Source</button>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Tag Number *</label>
                            <input type="text" id="edit_tag_no" name="tag_no" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Sex *</label>
                            <select id="edit_sex" name="sex" class="form-control" required>
                                <option value="M">Male (Sire)</option>
                                <option value="F">Female (Dam)</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Animal Type *</label>
                            <select id="edit_animal_type" name="animal_type_id" class="form-control" required onchange="loadBreeds(this.value, 'edit')">
                                <option value="">Select Type</option>
                                <?php foreach ($animal_types as $type): ?>
                                    <option value="<?php echo $type['ANIMAL_TYPE_ID']; ?>"><?php echo htmlspecialchars($type['ANIMAL_TYPE_NAME']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Breed Standard *</label>
                            <select id="edit_breed" name="breed_id" class="form-control" required disabled>
                                <option value="">Select Type First</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" style="color:var(--amber);">Acquisition Cost (PHP)</label>
                            <input type="number" id="edit_acquisition_cost" name="acquisition_cost" class="form-control val-mono" step="0.01" style="border-color:var(--amber-dim);" readonly>
                        </div>
                        <div id="edit-birth-date-group" class="form-group">
                            <label class="form-label">Birth Date *</label>
                            <input type="text" id="edit_birth_date" name="birth_date" class="form-control date-picker" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Current Status *</label>
                        <select id="edit_status" name="current_status" class="form-control" required>
                            <option value="Active">Active</option>
                            <option value="Sold">Sold</option>
                            <option value="Deceased">Deceased</option>
                        </select>
                    </div>

                    <div class="section-card" style="margin-top: 1rem;">
                        <label class="form-label">Growth Metrics</label>
                        <div class="form-row">
                            <div class="form-group no-margin">
                                <label class="form-label">Weight @ Birth (kg)</label>
                                <input type="number" id="edit_weight_birth" name="weight_at_birth" class="form-control val-mono" step="0.01">
                            </div>
                            <div class="form-group no-margin">
                                <label class="form-label" style="color:var(--green);">Actual Weight (kg)</label>
                                <input type="number" id="edit_weight_actual" name="current_actual_weight" class="form-control val-mono" step="0.01" style="border-color:rgba(34,197,94,0.4);">
                            </div>
                        </div>
                        <div class="form-group" style="margin-top: 1rem;">
                            <label class="form-label" style="color:var(--primary);">Est. Weight (kg)</label>
                            <input type="number" id="edit_weight_est" name="current_estimated_weight" class="form-control val-mono" step="0.01" style="border-color:rgba(59,130,246,0.4);">
                        </div>
                    </div>

                    <div class="section-card">
                        <label class="form-label">Physical Placement</label>
                        <div class="form-group">
                            <select id="edit_location" name="location_id" class="form-control" required onchange="loadBuildings(this.value, 'edit')" <?php echo ($USER_LOCATION_ != 1000) ? 'disabled' : ''; ?>>
                                <?php if($USER_LOCATION_ == 1000): ?>
                                    <option value="">Select Location *</option>
                                <?php endif; ?>
                                <?php foreach ($locations as $location): ?>
                                    <option value="<?php echo $location['LOCATION_ID']; ?>" <?php echo ($USER_LOCATION_ != 1000 && $location['LOCATION_ID'] == $USER_LOCATION_) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($location['LOCATION_NAME']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($USER_LOCATION_ != 1000): ?>
                                <input type="hidden" name="location_id" value="<?= $USER_LOCATION_ ?>">
                            <?php endif; ?>
                        </div>
                        <div class="form-row" style="margin-top: 1rem;">
                            <div class="form-group no-margin">
                                <select id="edit_building" name="building_id" class="form-control" required disabled onchange="loadPens(this.value, 'edit')">
                                    <option value="">Select Building *</option>
                                </select>
                            </div>
                            <div class="form-group no-margin">
                                <select id="edit_pen" name="pen_id" class="form-control" required disabled>
                                    <option value="">Select Pen *</option>
                                </select>
                            </div>
                        </div>
                    </div>

                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-ghost" onclick="closeEditModal()">Cancel</button>
                <button class="btn btn-primary" id="btn-edit-save" onclick="submitEditForm()">Update Record</button>
            </div>
        </div>
    </div>

    <div id="editSelectPurchaseModal" class="modal">
        <div class="modal-content large">
            <div class="modal-header">
                <h2>Reassign Purchase Origin</h2>
                <button class="action-btn" onclick="closeEditSelectPurchaseModal()"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="modal-body">
                <div class="table-wrap">
                    <table class="table">
                        <thead><tr><th>ID</th><th>Nomenclature</th><th>Unit Cost</th><th>Location</th><th style="text-align:center;">Action</th></tr></thead>
                        <tbody id="edit-purchase-table-body"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        const USER_LOCATION = <?php echo json_encode($USER_LOCATION_); ?>;
        // Inject locations to JS for dynamic row creation
        const allLocations = <?php echo json_encode($locations); ?>;

        // Filter Toggle
        let filterOpen = true;
        function toggleFilters() {
            filterOpen = !filterOpen;
            const body = document.getElementById('filterBody');
            const btn  = document.getElementById('filterToggleBtn');
            const label = document.getElementById('filterToggleLabel');
            body.classList.toggle('hidden', !filterOpen);
            btn.classList.toggle('collapsed', !filterOpen);
            label.textContent = filterOpen ? 'Collapse' : 'Expand';
        }

        // Flatpickr Initialization
        const fpEditBirth = flatpickr("#edit_birth_date", {
            dateFormat: "Y-m-d", altInput: true, altFormat: "m/d/Y", allowInput: true
        });

        document.addEventListener('DOMContentLoaded', () => {
            const rows = document.querySelectorAll('#animal-table tr');
            if(rows.length === 0) {
                const emptyEl = document.getElementById('empty-state-db');
                if(emptyEl) emptyEl.style.display = 'block';
            }

            // Auto-save Type/Breed selections
            const typeSelect = document.getElementById('add_animal_type');
            const breedSelect = document.getElementById('add_breed');
            
            if(typeSelect) {
                typeSelect.addEventListener('change', function() {
                    localStorage.setItem('farmpro_last_animal_type', this.value);
                    localStorage.removeItem('farmpro_last_breed'); 
                });
            }
            if(breedSelect) {
                breedSelect.addEventListener('change', function() {
                    if(this.value) localStorage.setItem('farmpro_last_breed', this.value);
                });
            }
        });

        // ─── ALERTS, TOASTS & CUSTOM MODALS ───
        function showToast(msg, type = 'success', duration = 3500) {
            const t = document.createElement('div');
            t.className = 'toast';
            t.style.borderLeft = `4px solid ${type === 'error' ? 'var(--red)' : (type === 'loading' ? 'var(--primary)' : 'var(--green)')}`;
            let icon = '<i class="fa-solid fa-check"></i>';
            if(type === 'error') icon = '<i class="fa-solid fa-xmark"></i>';
            if(type === 'loading') icon = '<i class="fa-solid fa-spinner fa-spin"></i>';
            
            const toastId = 'toast_' + Math.random().toString(36).substr(2, 9);
            t.id = toastId;
            
            t.innerHTML = `${icon} <span style="white-space: pre-line;">${msg}</span>`;
            document.getElementById('toastContainer').appendChild(t);
            
            if (duration > 0) {
                setTimeout(() => t.remove(), duration);
            }
            return toastId;
        }

        function removeToast(toastId) {
            const t = document.getElementById(toastId);
            if (t) t.remove();
        }

        // Custom Error/Success Alert Modal
        function showCustomAlert(title, message, type = 'error', details = []) {
            const modal = document.getElementById('customAlertModal');
            const iconEl = document.getElementById('customAlertIcon');
            const titleEl = document.getElementById('customAlertTitle');
            const msgEl = document.getElementById('customAlertMessage');
            const detailsEl = document.getElementById('customAlertDetails');
            
            titleEl.textContent = title;
            msgEl.textContent = message;
            
            if (type === 'error') {
                iconEl.innerHTML = '<i class="fa-solid fa-circle-xmark"></i>';
                iconEl.style.color = 'var(--red)';
            } else if (type === 'success') {
                iconEl.innerHTML = '<i class="fa-solid fa-circle-check"></i>';
                iconEl.style.color = 'var(--green)';
            } else {
                iconEl.innerHTML = '<i class="fa-solid fa-circle-info"></i>';
                iconEl.style.color = 'var(--blue)';
            }
            
            if (details && details.length > 0) {
                detailsEl.style.display = 'block';
                detailsEl.innerHTML = details.map(err => `<div>• ${err}</div>`).join('');
            } else {
                detailsEl.style.display = 'none';
                detailsEl.innerHTML = '';
            }
            
            modal.classList.add('show');
        }

        function closeCustomAlert() {
            document.getElementById('customAlertModal').classList.remove('show');
        }

        // Custom Confirm Modal
        let confirmCallback = null;
        function showCustomConfirm(title, message, confirmBtnText, confirmBtnClass, callback) {
            document.getElementById('customConfirmTitle').textContent = title;
            document.getElementById('customConfirmMessage').textContent = message;
            
            const btn = document.getElementById('customConfirmBtn');
            btn.textContent = confirmBtnText;
            btn.className = `btn ${confirmBtnClass}`;
            
            confirmCallback = callback;
            document.getElementById('customConfirmModal').classList.add('show');
        }

        function closeCustomConfirm() {
            document.getElementById('customConfirmModal').classList.remove('show');
            confirmCallback = null;
        }

        document.getElementById('customConfirmBtn').addEventListener('click', () => {
            if (confirmCallback) confirmCallback();
            closeCustomConfirm();
        });


        // ─── CSV UPLOAD & DOWNLOAD LOGIC ───
        
        function downloadSampleCSV() {
            const csvContent = "Tag_Number,Animal_Type,Breed,Birth_Date,Sex,Weight_At_Birth_kg,Weaning_Weight_kg,Estimated_Weight_kg,Actual_Weight_kg,Acquisition_Cost,Current_Status,Location,Building,Pen,Classification,Is_Purchased,Total_Misc_Amount,Date_Added\nBTD001,Swine,Duroc,04/01/2025,F,2.50,8.00,35.00,40.00,30000.00,Active,Danao Farms,Building 1,Fattening Pen 1,Sow,No,0.00,04/01/2026";
            
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement("a");
            const url = URL.createObjectURL(blob);
            link.setAttribute("href", url);
            link.setAttribute("download", "animal_records_sample.csv");
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        function uploadCSV(event) {
            const file = event.target.files[0];
            if (!file) return;

            showCustomConfirm(
                "Confirm Import", 
                `Are you sure you want to import records from ${file.name}?`, 
                "Yes, Import", 
                "btn-purchase", 
                () => {
                    const fd = new FormData();
                    fd.append('csv_file', file);

                    const toastId = showToast("Uploading and validating CSV format...", "loading", 0);

                    fetch('../process/addImportedAnimalRecord.php', {
                        method: 'POST',
                        body: fd
                    })
                    .then(res => res.json())
                    .then(data => {
                        removeToast(toastId);
                        if (data.success) {
                            showCustomAlert("Import Successful", data.message, "success");
                            setTimeout(() => location.reload(), 2000);
                        } else {
                            showCustomAlert("Import Failed", data.message, "error", data.errors || []);
                        }
                    })
                    .catch(err => {
                        removeToast(toastId);
                        showCustomAlert("System Error", "A critical error occurred during file processing. Please check your network connection.", "error");
                    })
                    .finally(() => {
                        event.target.value = ''; 
                    });
                }
            );
        }

        // ─── ROW LEVEL DROPDOWN LOADERS ───
        function loadRowBuildings(locSelect) {
            const row = locSelect.closest('tr');
            const bldSelect = row.querySelector('.row-building');
            const penSelect = row.querySelector('.row-pen');
            const locId = locSelect.value;
            
            bldSelect.innerHTML = '<option value="">Loading...</option>';
            bldSelect.disabled = true;
            penSelect.innerHTML = '<option value="">Select Pen</option>';
            penSelect.disabled = true;
            
            if (!locId) {
                bldSelect.innerHTML = '<option value="">Select Building</option>';
                return;
            }
            
            fetch('../process/getBuildingsByLocation.php?location_id=' + locId)
            .then(r => r.json())
            .then(d => {
                bldSelect.innerHTML = '<option value="">Select Building</option>';
                if(d.buildings) {
                    d.buildings.forEach(b => bldSelect.innerHTML += `<option value="${b.BUILDING_ID}">${b.BUILDING_NAME}</option>`);
                }
                bldSelect.disabled = false;
            });
        }
        
        function loadRowPens(bldSelect) {
            const row = bldSelect.closest('tr');
            const penSelect = row.querySelector('.row-pen');
            const bldId = bldSelect.value;
            
            penSelect.innerHTML = '<option value="">Loading...</option>';
            penSelect.disabled = true;
            
            if (!bldId) {
                penSelect.innerHTML = '<option value="">Select Pen</option>';
                return;
            }
            
            fetch('../process/getPensByBuilding.php?building_id=' + bldId)
            .then(r => r.json())
            .then(d => {
                penSelect.innerHTML = '<option value="">Select Pen</option>';
                if(d.pens) {
                    d.pens.forEach(p => penSelect.innerHTML += `<option value="${p.PEN_ID}">${p.PEN_NAME}</option>`);
                }
                penSelect.disabled = false;
            });
        }

        // ─── MODAL CONTROLLERS ───
        let acquisition_type = 0;
        let currentParentMode = '';
        let currentParentType = '';

        function openAddModal(type, acquisition = 0) {
            const form = document.getElementById('addAnimalForm');
            form.reset();
            
            // Restore last Type/Breed
            const lastType = localStorage.getItem('farmpro_last_animal_type');
            const lastBreed = localStorage.getItem('farmpro_last_breed');

            if (lastType) {
                document.getElementById('add_animal_type').value = lastType;
                loadBreeds(lastType, 'add').then(() => {
                    if (lastBreed) setTimeout(() => { document.getElementById('add_breed').value = lastBreed; }, 50);
                });
            } else {
                document.getElementById('add_breed').innerHTML = '<option value="">Select Type First</option>';
                document.getElementById('add_breed').disabled = true;
            }

            const modalTitle = document.getElementById('modal-title');
            const purchaseGroup = document.getElementById('purchase-group');
            const lineageGroup = document.getElementById('lineage-group');
            const entryType = document.getElementById('entry_type');
            const bulkEntryGroup = document.getElementById('bulk-entry-group');
            const existingBulkGroup = document.getElementById('existing-bulk-group');

            acquisition_type = acquisition;
            if(document.getElementById('acquisition_type')) document.getElementById('acquisition_type').value = acquisition;

            if (type === 'purchase') {
                modalTitle.innerHTML = '<i class="fa-solid fa-cart-arrow-down me-2" style="color:var(--primary);"></i> Batch Add Purchases';
                entryType.value = 'purchase';
                purchaseGroup.style.display = 'block';
                bulkEntryGroup.style.display = 'block';
                existingBulkGroup.style.display = 'none';
                lineageGroup.style.display = 'none';
                
                document.getElementById('bulk-table-body').innerHTML = '';
                document.getElementById('bulk_selection_summary').textContent = '0 items selected';
                
            } else if (type === 'existing') {
                modalTitle.innerHTML = '<i class="fa-solid fa-plus me-2" style="color:var(--amber);"></i> Add Existing / Born';
                entryType.value = 'existing';
                purchaseGroup.style.display = 'none';
                bulkEntryGroup.style.display = 'none';
                existingBulkGroup.style.display = 'block';
                lineageGroup.style.display = 'block';
                
                document.getElementById('existing-table-body').innerHTML = '';
                addExistingRow(); // Start with 1 empty row
            }

            document.getElementById('addModal').classList.add('show');
        }

        function closeAddModal() { document.getElementById('addModal').classList.remove('show'); }

        // --- EXISTING/BORN BATCH LOGIC ---
        function addExistingRow() {
            const tbody = document.getElementById('existing-table-body');
            const prevRow = tbody.lastElementChild;
            
            // Generate Location options based on user access
            let locOptions = '<option value="">Select Location</option>';
            if (USER_LOCATION == 1000) {
                allLocations.forEach(l => {
                    locOptions += `<option value="${l.LOCATION_ID}">${l.LOCATION_NAME}</option>`;
                });
            } else {
                const myLoc = allLocations.find(l => l.LOCATION_ID == USER_LOCATION);
                if(myLoc) locOptions += `<option value="${myLoc.LOCATION_ID}">${myLoc.LOCATION_NAME}</option>`;
            }

            let bldgOptions = '<option value="">Select Building</option>';
            let penOptions = '<option value="">Select Pen</option>';
            
            // Smart Copy Defaults
            let prevLoc = USER_LOCATION != 1000 ? USER_LOCATION : '';
            let prevBld = '';
            let prevPen = '';
            let prevBirthDate = '';
            let prevCost = '';

            if (prevRow) {
                const prevLocSel = prevRow.querySelector('.row-location');
                const prevBldSel = prevRow.querySelector('.row-building');
                const prevPenSel = prevRow.querySelector('.row-pen');
                
                locOptions = prevLocSel.innerHTML;
                bldgOptions = prevBldSel.innerHTML;
                penOptions = prevPenSel.innerHTML;
                
                prevLoc = prevLocSel.value;
                prevBld = prevBldSel.value;
                prevPen = prevPenSel.value;
                
                // Fetch flatpickr instance value if initialized, or raw value
                const fpInput = prevRow.querySelector('.row-birthdate');
                if (fpInput && fpInput._flatpickr) {
                    prevBirthDate = fpInput._flatpickr.input.value;
                } else {
                    prevBirthDate = fpInput ? fpInput.value : '';
                }
                prevCost = prevRow.querySelector('.row-cost').value || '';
            }

            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td style="padding: 10px; border-bottom: 1px solid var(--border);"><input type="text" class="form-control existing-tag" required placeholder="Tag No" style="height: 38px; min-width: 120px;"></td>
                <td style="padding: 10px; border-bottom: 1px solid var(--border);">
                    <select class="form-control existing-sex" required style="height: 38px; min-width: 100px;">
                        <option value="M">Male (Boar)</option>
                        <option value="F">Female (Sow)</option>
                    </select>
                </td>
                <td style="padding: 10px; border-bottom: 1px solid var(--border);">
                    <input type="text" class="form-control row-birthdate date-picker" required value="${prevBirthDate}" style="height: 38px; width: 140px;">
                </td>
                <td style="padding: 10px; border-bottom: 1px solid var(--border);">
                    <input type="number" class="form-control row-cost" step="0.01" placeholder="0.00" value="${prevCost}" style="height: 38px; width: 100px;">
                </td>
                <td style="padding: 10px; border-bottom: 1px solid var(--border);">
                    <select class="form-control row-location" required style="height: 38px; min-width: 140px;" onchange="loadRowBuildings(this)" ${USER_LOCATION != 1000 ? 'disabled' : ''}>
                        ${locOptions}
                    </select>
                </td>
                <td style="padding: 10px; border-bottom: 1px solid var(--border);">
                    <select class="form-control row-building" required style="height: 38px; min-width: 140px;" onchange="loadRowPens(this)" ${!prevBld && !prevRow ? 'disabled' : ''}>
                        ${bldgOptions}
                    </select>
                </td>
                <td style="padding: 10px; border-bottom: 1px solid var(--border);">
                    <select class="form-control row-pen" required style="height: 38px; min-width: 140px;" ${!prevPen && !prevRow ? 'disabled' : ''}>
                        ${penOptions}
                    </select>
                </td>
                <td style="padding: 10px; border-bottom: 1px solid var(--border); text-align: center;">
                    <button type="button" class="action-btn delete" style="width:34px; height:34px; border:none; background:transparent;" onclick="removeExistingRow(this)"><i class="fa-solid fa-trash-can"></i></button>
                </td>
            `;
            tbody.appendChild(tr);
            
            // Initialize flatpickr immediately on the newly created input
            const newDateInput = tr.querySelector('.row-birthdate');
            flatpickr(newDateInput, {
                dateFormat: "Y-m-d", altInput: true, altFormat: "m/d/Y", allowInput: true,
                defaultDate: prevBirthDate || new Date()
            });

            const newLocSel = tr.querySelector('.row-location');
            const newBldSel = tr.querySelector('.row-building');
            const newPenSel = tr.querySelector('.row-pen');
            
            if (prevLoc) newLocSel.value = prevLoc;
            if (prevBld) newBldSel.value = prevBld;
            if (prevPen) newPenSel.value = prevPen;

            // Auto load buildings for restricted location on very first row
            if (!prevRow && USER_LOCATION != 1000 && newLocSel.value) {
                loadRowBuildings(newLocSel);
            }
        }

        function removeExistingRow(btn) {
            const tbody = document.getElementById('existing-table-body');
            if (tbody.children.length > 1) {
                btn.closest('tr').remove();
            } else {
                showToast('You must assign at least one animal tag to save.', 'error');
            }
        }


        // ─── PARENT SELECTION ───
        function openSelectParentModal(type, mode = 'add') {
            currentParentType = type;
            currentParentMode = mode;
            document.getElementById('selectParentModal').classList.add('show');
            document.getElementById('parent-modal-title').innerHTML = type === 'sow' ? '<i class="fa-solid fa-venus" style="color:var(--pink);"></i> Select Mother (Sow)' : '<i class="fa-solid fa-mars" style="color:var(--primary);"></i> Select Father (Boar)';
            loadAvailableParents(type);
        }
        function closeSelectParentModal() { document.getElementById('selectParentModal').classList.remove('show'); }

        function loadAvailableParents(type) {
            const tbody = document.getElementById('parent-table-body');
            tbody.innerHTML = '<tr><td colspan="4" style="text-align:center; padding: 20px; color:var(--text-muted);"><i class="fa-solid fa-spinner fa-spin me-2"></i>Loading databank...</td></tr>';
            
            const script = type === 'sow' ? '../process/getAvailableSows.php' : '../process/getAvailableBoars.php';
            
            fetch(script).then(res => res.json()).then(data => {
                const list = data.sows || data.boars || [];
                if (data.success && list.length > 0) {
                    tbody.innerHTML = list.map(s => `
                        <tr>
                            <td data-label="Tag No" style="font-weight:700; color:${type==='sow'?'var(--pink)':'var(--primary)'}; font-family:var(--font-mono);">${s.TAG_NO}</td>
                            <td data-label="Breed" style="color:var(--text-primary);">${s.BREED_NAME}</td>
                            <td data-label="Location" style="color:var(--text-secondary); font-size:0.8rem;">${s.LOCATION_NAME} - ${s.PEN_NAME}</td>
                            <td data-label="Action" style="text-align:center;">
                                <button type="button" class="btn btn-ghost btn-sm" style="border:1px solid var(--border);" onclick="selectParent('${s.ANIMAL_ID}', '${s.TAG_NO}')">Assign</button>
                            </td>
                        </tr>`).join('');
                } else { tbody.innerHTML = `<tr><td colspan="4" style="text-align:center; padding: 20px; color:var(--text-muted);">No active ${type}s found in the system.</td></tr>`; }
            })
            .catch(err => {
                tbody.innerHTML = '<tr><td colspan="4" style="text-align:center; color: var(--red);">System network error loading parents.</td></tr>'; 
            });
        }

        function selectParent(id, tag) {
            const prefix = currentParentMode === 'add' ? 'add_' : 'edit_';
            const displayPrefix = currentParentMode === 'add' ? 'display_' : 'edit_display_';
            
            if (currentParentType === 'sow') {
                document.getElementById(prefix + 'mother_id').value = id;
                if(currentParentMode === 'edit') document.getElementById('edit_display_mother').value = tag;
                else document.getElementById('display_mother_tag').value = tag;
            } else {
                document.getElementById(prefix + 'father_id').value = id;
                if(currentParentMode === 'edit') document.getElementById('edit_display_father').value = tag;
                else document.getElementById('display_father_tag').value = tag;
            }
            closeSelectParentModal();
        }

        // ─── PURCHASE SELECTION (BULK/SINGLE) ───
        function loadAvailablePurchases(targetBodyId) {
            const tbody = document.getElementById(targetBodyId);
            const cols = 5; 
            tbody.innerHTML = `<tr><td colspan="${cols}" style="text-align:center; padding: 20px; color:var(--text-muted);"><i class="fa-solid fa-spinner fa-spin me-2"></i>Loading inventory...</td></tr>`;
            
            fetch('../process/getAvailablePurchasedAnimals.php').then(r=>r.json()).then(data=>{
                if(data.success && data.items.length > 0) {
                    tbody.innerHTML = data.items.map(i => {
                        if (targetBodyId === 'add-purchase-table-body') {
                            const itemData = encodeURIComponent(JSON.stringify(i));
                            return `<tr>
                                <td style="text-align:center;"><input type="checkbox" class="purchase-cb" data-item="${itemData}" style="width:16px;height:16px; cursor:pointer; accent-color:var(--primary);"></td>
                                <td data-label="ID" class="val-mono">#${i.ITEM_ID}</td>
                                <td data-label="Name" style="font-weight:600; color:var(--text-primary);">${i.ITEM_NAME}</td>
                                <td data-label="Cost" class="val-mono" style="color:var(--text-secondary);">₱${i.UNIT_COST}</td>
                                <td data-label="Location" style="font-size:0.8rem; color:var(--text-secondary);">${i.LOCATION_NAME || '-'}</td>
                            </tr>`;
                        } else {
                            return `<tr>
                                <td data-label="ID" class="val-mono">#${i.ITEM_ID}</td>
                                <td data-label="Name" style="font-weight:600; color:var(--text-primary);">${i.ITEM_NAME}</td>
                                <td data-label="Cost" class="val-mono" style="color:var(--text-secondary);">₱${i.UNIT_COST}</td>
                                <td data-label="Location" style="font-size:0.8rem; color:var(--text-secondary);">${i.LOCATION_NAME || '-'}</td>
                                <td data-label="Action" style="text-align:center;">
                                    <button type="button" class="btn btn-ghost btn-sm" style="border:1px solid var(--border);" onclick="selectEditPurchaseItem('${i.ITEM_ID}', '${i.LOCATION_ID}', '${i.BUILDING_ID}', '${i.PEN_ID}', '${i.ITEM_NAME}', '${i.UNIT_COST}')">Select</button>
                                </td>
                            </tr>`;
                        }
                    }).join('');
                } else { 
                    tbody.innerHTML = `<tr><td colspan="${cols}" style="text-align:center; padding: 20px; color:var(--text-muted);">No unused purchase allocations available.</td></tr>`; 
                }
            });
        }

        function openSelectPurchaseModal() {
            document.getElementById('selectPurchaseModal').classList.add('show');
            document.getElementById('selectAllPurchases').checked = false;
            loadAvailablePurchases('add-purchase-table-body');
        }
        function closeSelectPurchaseModal() { document.getElementById('selectPurchaseModal').classList.remove('show'); }
        
        function toggleAllPurchases(source) {
            document.querySelectorAll('.purchase-cb').forEach(cb => cb.checked = source.checked);
        }
        
        function confirmPurchaseSelection() {
            const checked = document.querySelectorAll('.purchase-cb:checked');
            if(checked.length === 0) {
                showToast('Please select at least one item from the purchase inventory.', 'error');
                return;
            }

            const tbody = document.getElementById('bulk-table-body');
            const today = new Date().toISOString().split('T')[0];

            checked.forEach((cb) => {
                const item = JSON.parse(decodeURIComponent(cb.getAttribute('data-item')));
                const safeName = item.ITEM_NAME ? item.ITEM_NAME.replace(/"/g, '&quot;') : '';

                // Build the location options for this specific item's location
                let locOptions = '<option value="">Select Location</option>';
                if (USER_LOCATION == 1000) {
                    allLocations.forEach(l => {
                        locOptions += `<option value="${l.LOCATION_ID}" ${l.LOCATION_ID == item.LOCATION_ID ? 'selected' : ''}>${l.LOCATION_NAME}</option>`;
                    });
                } else {
                    const myLoc = allLocations.find(l => l.LOCATION_ID == USER_LOCATION);
                    if (myLoc) {
                        locOptions += `<option value="${myLoc.LOCATION_ID}" ${myLoc.LOCATION_ID == item.LOCATION_ID ? 'selected' : ''}>${myLoc.LOCATION_NAME}</option>`;
                    }
                }

                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td data-label="Purchase Base" style="padding:10px; border-bottom:1px solid var(--border);">
                        <strong style="color:var(--text-primary); font-size:0.9rem;">${item.ITEM_NAME}</strong><br>
                        <small style="color:var(--text-secondary); font-family:var(--font-mono);">₱${item.UNIT_COST}</small>
                        <input type="hidden" class="bulk-item-id" value="${item.ITEM_ID}">
                    </td>
                    <td data-label="Tag No" style="padding:10px; border-bottom:1px solid var(--border);"><input type="text" class="form-control existing-tag" required placeholder="Tag No" value="${safeName}" style="height:38px; min-width: 120px;"></td>
                    <td data-label="Sex" style="padding:10px; border-bottom:1px solid var(--border);">
                        <select class="form-control existing-sex" required style="height:38px; min-width: 100px;">
                            <option value="M">Male (Sire)</option>
                            <option value="F">Female (Dam)</option>
                        </select>
                    </td>
                    <td style="padding: 10px; border-bottom: 1px solid var(--border);">
                        <input type="text" class="form-control row-birthdate date-picker" required value="${today}" style="height: 38px; width: 140px;">
                    </td>
                    <td style="padding: 10px; border-bottom: 1px solid var(--border);">
                        <input type="number" class="form-control row-cost" step="0.01" value="${item.UNIT_COST}" style="height: 38px; width: 100px;" readonly>
                    </td>
                    <td style="padding: 10px; border-bottom: 1px solid var(--border);">
                        <select class="form-control row-location" required style="height: 38px; min-width: 140px;" onchange="loadRowBuildings(this)" ${USER_LOCATION != 1000 ? 'disabled' : ''}>
                            ${locOptions}
                        </select>
                    </td>
                    <td style="padding: 10px; border-bottom: 1px solid var(--border);">
                        <select class="form-control row-building" required style="height: 38px; min-width: 140px;" onchange="loadRowPens(this)" disabled>
                            <option value="">Select Building</option>
                        </select>
                    </td>
                    <td style="padding: 10px; border-bottom: 1px solid var(--border);">
                        <select class="form-control row-pen" required style="height: 38px; min-width: 140px;" disabled>
                            <option value="">Select Pen</option>
                        </select>
                    </td>
                    <td style="text-align: center; padding:10px; border-bottom:1px solid var(--border);"><button type="button" class="action-btn delete" style="width:34px; height:34px; border:none; background:transparent;" onclick="this.closest('tr').remove(); updateBulkSummary();"><i class="fa-solid fa-trash-can"></i></button></td>
                `;
                tbody.appendChild(tr);

                // Initialize flatpickr immediately
                const newDateInput = tr.querySelector('.row-birthdate');
                flatpickr(newDateInput, {
                    dateFormat: "Y-m-d", altInput: true, altFormat: "m/d/Y", allowInput: true,
                    defaultDate: today
                });

                // Auto-load building if location exists
                const locSelect = tr.querySelector('.row-location');
                if (locSelect.value) {
                    loadRowBuildings(locSelect);
                }
            });

            updateBulkSummary();
            closeSelectPurchaseModal();
        }

        function updateBulkSummary() {
            const count = document.querySelectorAll('#bulk-table-body tr').length;
            document.getElementById('bulk_selection_summary').textContent = count + ' allocations queued in table';
        }

        function openEditSelectPurchaseModal() {
            document.getElementById('editSelectPurchaseModal').classList.add('show');
            loadAvailablePurchases('edit-purchase-table-body');
        }
        function closeEditSelectPurchaseModal() { document.getElementById('editSelectPurchaseModal').classList.remove('show'); }
        
        function selectEditPurchaseItem(id, loc, bldg, pen, name, cost) {
            document.getElementById('edit_animal_item_id').value = id;
            document.getElementById('edit_acquisition_cost').value = cost;

            if(loc) {
                document.getElementById('edit_location').value = loc;
                loadBuildings(loc, 'edit').then(() => {
                    if(bldg) {
                        document.getElementById('edit_building').value = bldg;
                        loadPens(bldg, 'edit').then(() => {
                            if(pen) document.getElementById('edit_pen').value = pen;
                        });
                    }
                });
            }
            closeEditSelectPurchaseModal();
        }

        // ─── ADD SUBMISSION (Single & Bulk) ───
        async function submitAddForm(event) {
            event.preventDefault();
            const form = document.getElementById('addAnimalForm');
            const entryType = document.getElementById('entry_type').value;
            const btn = document.getElementById('btn-add-save');

            if(document.getElementById('acquisition_type')) {
                document.getElementById('acquisition_type').value = acquisition_type;
            }

            if (!form.checkValidity()) { form.reportValidity(); return; }

            btn.disabled = true; btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i>Committing...';

            if (entryType === 'purchase') {
                const rows = document.querySelectorAll('#bulk-table-body tr');
                if (rows.length === 0) {
                    showCustomAlert('Error', 'Please select at least one purchase allocation.', 'error');
                    btn.disabled = false; btn.innerHTML = 'Commit Record';
                    return;
                }

                // Gather Global Values
                const commonData = {
                    entry_type: 'purchase',
                    acquisition_type: 1,
                    animal_type_id: document.getElementById('add_animal_type').value,
                    breed_id: document.getElementById('add_breed').value,
                    current_status: document.getElementById('add_status').value
                };

                const promises = [];

                for (let r of rows) {
                    const fd = new FormData();
                    for (let key in commonData) fd.append(key, commonData[key]);
                    
                    fd.append('animal_item_id', r.querySelector('.bulk-item-id').value);
                    fd.append('tag_no', r.querySelector('.existing-tag').value);
                    fd.append('sex', r.querySelector('.existing-sex').value);
                    
                    // Capture Row-Level inputs. Use flatpickr actual hidden value if it exists.
                    const fpInput = r.querySelector('.row-birthdate');
                    const birthDateVal = fpInput._flatpickr ? fpInput._flatpickr.input.value : fpInput.value;
                    
                    fd.append('birth_date', birthDateVal);
                    fd.append('acquisition_cost', r.querySelector('.row-cost').value || 0);
                    fd.append('location_id', r.querySelector('.row-location').value || USER_LOCATION); 
                    fd.append('building_id', r.querySelector('.row-building').value);
                    fd.append('pen_id', r.querySelector('.row-pen').value);

                    promises.push(fetch('../process/addAnimalRecord.php', { method: 'POST', body: fd }).then(res => res.json()));
                }

                try {
                    const results = await Promise.all(promises);
                    const hasError = results.some(r => !r.success);
                    
                    if (hasError) {
                        const firstError = results.find(r => !r.success);
                        showCustomAlert('Batch Error', firstError.message, 'error');
                        btn.disabled = false; btn.innerHTML = 'Commit Record';
                    } else {
                        showToast('Successfully provisioned ' + results.length + ' animal records.', 'success');
                        setTimeout(() => location.reload(), 1000);
                    }
                } catch (e) {
                    showCustomAlert('Network Error', 'Critical network error during batch processing.', 'error');
                    btn.disabled = false; btn.innerHTML = 'Commit Record';
                }

            } else if (entryType === 'existing') {
                const rows = document.querySelectorAll('#existing-table-body tr');
                if (rows.length === 0) {
                    showCustomAlert('Error', 'Please add at least one animal to save.', 'error');
                    btn.disabled = false; btn.innerHTML = 'Commit Record';
                    return;
                }

                // Gather Global Values
                const commonData = {
                    entry_type: 'existing',
                    acquisition_type: 0,
                    animal_type_id: document.getElementById('add_animal_type').value,
                    breed_id: document.getElementById('add_breed').value,
                    current_status: document.getElementById('add_status').value,
                    mother_id: document.getElementById('add_mother_id').value,
                    father_id: document.getElementById('add_father_id').value
                };

                const promises = [];

                for (let r of rows) {
                    const fd = new FormData();
                    for (let key in commonData) fd.append(key, commonData[key]);
                    
                    fd.append('tag_no', r.querySelector('.existing-tag').value);
                    fd.append('sex', r.querySelector('.existing-sex').value);
                    
                    // Capture Row-Level inputs. Use flatpickr actual hidden value if it exists.
                    const fpInput = r.querySelector('.row-birthdate');
                    const birthDateVal = fpInput._flatpickr ? fpInput._flatpickr.input.value : fpInput.value;
                    
                    fd.append('birth_date', birthDateVal);
                    fd.append('acquisition_cost', r.querySelector('.row-cost').value || 0);
                    fd.append('location_id', r.querySelector('.row-location').value || USER_LOCATION); 
                    fd.append('building_id', r.querySelector('.row-building').value);
                    fd.append('pen_id', r.querySelector('.row-pen').value);

                    promises.push(fetch('../process/addAnimalRecord.php', { method: 'POST', body: fd }).then(res => res.json()));
                }

                try {
                    const results = await Promise.all(promises);
                    const hasError = results.some(r => !r.success);
                    
                    if (hasError) {
                        const firstError = results.find(r => !r.success);
                        showCustomAlert('Batch Error', firstError.message, 'error');
                        btn.disabled = false; btn.innerHTML = 'Commit Record';
                    } else {
                        showToast('Successfully provisioned ' + results.length + ' animal records.', 'success');
                        setTimeout(() => location.reload(), 1000);
                    }
                } catch (e) {
                    showCustomAlert('Network Error', 'Critical network error during batch processing.', 'error');
                    btn.disabled = false; btn.innerHTML = 'Commit Record';
                }
            }
        }

        // ─── EDIT LOGIC ───
        async function editAnimal(button) {
            const row = button.closest('tr');
            const animalId = row.getAttribute('data-id');
            document.getElementById('editModal').classList.add('show');
            
            try {
                const response = await fetch(`../process/getAnimalDetails.php?animal_id=${animalId}`);
                const data = await response.json();

                if (data.success) {
                    const animal = data.data;
                    document.getElementById('edit_animal_id').value = animal.ANIMAL_ID;
                    document.getElementById('edit_tag_no').value = animal.TAG_NO;
                    document.getElementById('edit_sex').value = animal.SEX;
                    document.getElementById('edit_status').value = animal.CURRENT_STATUS;
                    
                    document.getElementById('edit_weight_birth').value = animal.WEIGHT_AT_BIRTH || '';
                    document.getElementById('edit_weight_actual').value = animal.CURRENT_ACTUAL_WEIGHT || '';
                    document.getElementById('edit_weight_est').value = animal.CURRENT_ESTIMATED_WEIGHT || '';
                    document.getElementById('edit_acquisition_cost').value = animal.ACQUISITION_COST || '';

                    document.getElementById('edit_mother_id').value = animal.MOTHER_ID || '';
                    document.getElementById('edit_father_id').value = animal.FATHER_ID || '';
                    document.getElementById('edit_display_mother').value = animal.MOTHER_TAG || '';
                    document.getElementById('edit_display_father').value = animal.FATHER_TAG || '';
                    
                    document.getElementById('edit_animal_type').value = animal.ANIMAL_TYPE_ID;
                    await loadBreeds(animal.ANIMAL_TYPE_ID, 'edit');
                    
                    setTimeout(() => { document.getElementById('edit_breed').value = animal.BREED_ID; }, 50);

                    if (animal.ANIMAL_ITEM_ID) {
                        document.getElementById('edit-purchase-group').style.display = 'block';
                        document.getElementById('edit_animal_item_id').value = animal.ANIMAL_ITEM_ID; 
                        document.getElementById('edit_has_purchase').value = "1";
                        fpEditBirth.setDate(animal.BIRTH_DATE || '');
                    } else {
                        document.getElementById('edit-purchase-group').style.display = 'none';
                        document.getElementById('edit_has_purchase').value = "0";
                        fpEditBirth.setDate(animal.BIRTH_DATE || '');
                    }

                    document.getElementById('edit_location').value = animal.LOCATION_ID;
                    
                    if (animal.LOCATION_ID) {
                        await loadBuildings(animal.LOCATION_ID, 'edit');
                        document.getElementById('edit_building').value = animal.BUILDING_ID;
                        
                        if (animal.BUILDING_ID) {
                            await loadPens(animal.BUILDING_ID, 'edit');
                            document.getElementById('edit_pen').value = animal.PEN_ID;
                        }
                    }
                }
            } catch (e) {
                console.error(e);
                showCustomAlert("Error", "Failed to retrieve entity data.", "error");
            }
        }

        function submitEditForm() {
            const form = document.getElementById('editAnimalForm');
            const formData = new FormData(form);
            const btn = document.getElementById('btn-edit-save');
            if (!form.checkValidity()) { form.reportValidity(); return; }
            
            btn.disabled = true; btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i>Synchronizing...';
            
            fetch('../process/editAnimalRecord.php', { method: 'POST', body: formData })
            .then(res => res.json()).then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showCustomAlert('Update Failed', data.message, 'error');
                    btn.disabled = false; btn.innerHTML = 'Update Record';
                }
            }).catch(e => {
                showCustomAlert('Network Error', 'Critical connection failure.', 'error');
                btn.disabled = false; btn.innerHTML = 'Update Record';
            });
        }

        function closeEditModal() { document.getElementById('editModal').classList.remove('show'); }

        // ─── DELETE ───
        function deleteAnimal(button) {
            const row = button.closest('tr');
            const id = row.getAttribute('data-id');
            const tagName = row.querySelector('.col-name').textContent.trim();

            showCustomConfirm(
                "Delete Animal Record", 
                `Warning: Purging the record for ${tagName} is irreversible. Proceed?`, 
                "Yes, Delete", 
                "btn-danger", 
                () => {
                    const fd = new FormData(); fd.append('animal_id', id);
                    fetch('../process/deleteAnimalRecord.php', { method:'POST', body:fd })
                    .then(r=>r.json()).then(data => {
                        if(data.success) {
                            showToast(data.message, "success");
                            row.remove();
                            checkEmptyState();
                        } else {
                            showCustomAlert("System Denial", data.message, "error");
                        }
                    });
                }
            );
        }

        // ─── CASCADING DROPDOWNS ───
        function loadBreeds(id, mode) {
            return new Promise(resolve => {
                fetch('../process/getBreedsByAnimalType.php?animal_type_id='+id)
                .then(r=>r.json()).then(d=>{
                    const sel = document.getElementById(mode+'_breed');
                    sel.innerHTML = '<option value="">Select Breed Standard</option>';
                    if(d.breeds) d.breeds.forEach(b => sel.innerHTML += `<option value="${b.BREED_ID}">${b.BREED_NAME}</option>`);
                    sel.disabled = false;
                    resolve();
                }).catch(() => resolve());
            });
        }
        
        function loadBuildings(id, mode) {
            return new Promise(resolve => {
                fetch('../process/getBuildingsByLocation.php?location_id='+id)
                .then(r=>r.json()).then(d=>{
                    const sel = document.getElementById(mode+'_building');
                    sel.innerHTML = '<option value="">Select Structure</option>';
                    if(d.buildings) d.buildings.forEach(b => sel.innerHTML += `<option value="${b.BUILDING_ID}">${b.BUILDING_NAME}</option>`);
                    sel.disabled = false;
                    resolve();
                }).catch(() => resolve());
            });
        }

        function loadPens(id, mode) {
            return new Promise(resolve => {
                fetch('../process/getPensByBuilding.php?building_id='+id)
                .then(r=>r.json()).then(d=>{
                    const sel = document.getElementById(mode+'_pen');
                    sel.innerHTML = '<option value="">Select Pen Context</option>';
                    if(d.pens) d.pens.forEach(p => sel.innerHTML += `<option value="${p.PEN_ID}">${p.PEN_NAME}</option>`);
                    sel.disabled = false;
                    resolve();
                }).catch(() => resolve());
            });
        }

        // ─── QUICK SEARCH ───
        function filterTable() {
            const term = document.querySelector('.search-input').value.toLowerCase();
            const rows = document.querySelectorAll('#animal-table tr');
            let visible = 0;
            rows.forEach(r => {
                if(r.innerText.toLowerCase().includes(term)) { r.style.display=''; visible++; }
                else { r.style.display='none'; }
            });
            const jsEmpty = document.getElementById('empty-state-js');
            const dbEmpty = document.getElementById('empty-state-db');
            if (dbEmpty && dbEmpty.style.display !== 'none') return; // DB empty handles itself
            if(jsEmpty) jsEmpty.style.display = (visible === 0) ? 'block' : 'none';
        }

        function checkEmptyState(count) {
            const el = document.getElementById('empty-state-db');
            if(el) {
                const rowCount = document.querySelectorAll('#animal-table tr:not([style*="display: none"])').length;
                el.style.display = (rowCount === 0) ? 'block' : 'none';
            }
        }

        // Close modals on overlay click
        document.getElementById('customAlertModal').addEventListener('click', function(e) { if(e.target===this) closeCustomAlert(); });
        document.getElementById('customConfirmModal').addEventListener('click', function(e) { if(e.target===this) closeCustomConfirm(); });
        document.getElementById('editModal').addEventListener('click', function(e) { if(e.target===this) closeEditModal(); });
        document.getElementById('selectPurchaseModal').addEventListener('click', function(e) { if(e.target===this) closeSelectPurchaseModal(); });
        document.getElementById('editSelectPurchaseModal').addEventListener('click', function(e) { if(e.target===this) closeEditSelectPurchaseModal(); });
        document.getElementById('selectParentModal').addEventListener('click', function(e) { if(e.target===this) closeSelectParentModal(); });

    </script>
</body>
</html>