<?php
// views/animal_record_history.php
$page = "animal_records"; // Active Tab
include '../common/navbar.php';
include '../config/Connection.php';
include '../security/checkRole.php';    
checkRole(2);

// --- 1. HANDLE FILTERS ---
$filter_loc    = $_GET['location_id'] ?? '';
$filter_build  = $_GET['building_id'] ?? '';
$filter_pen    = $_GET['pen_id'] ?? '';
$filter_status = $_GET['status'] ?? ''; // New Status Filter
$search_tag    = $_GET['search'] ?? '';
$limit_opt     = $_GET['limit'] ?? '10';

// --- 2. BUILD DYNAMIC QUERY ---
$params = [];
$sql = "SELECT 
            ar.*, 
            at.ANIMAL_TYPE_NAME, 
            b.BREED_NAME,
            l.LOCATION_NAME,
            bu.BUILDING_NAME,
            p.PEN_NAME,
            ac.STAGE_NAME
        FROM animal_records ar
        LEFT JOIN animal_type at ON ar.ANIMAL_TYPE_ID = at.ANIMAL_TYPE_ID
        LEFT JOIN breeds b ON ar.BREED_ID = b.BREED_ID
        LEFT JOIN locations l ON ar.LOCATION_ID = l.LOCATION_ID
        LEFT JOIN buildings bu ON ar.BUILDING_ID = bu.BUILDING_ID
        LEFT JOIN pens p ON ar.PEN_ID = p.PEN_ID
        LEFT JOIN animal_classifications ac ON ar.CLASS_ID = ac.CLASS_ID
        WHERE 1=1"; 

// Apply Location Filter
if (!empty($filter_loc)) {
    $sql .= " AND ar.LOCATION_ID = ?";
    $params[] = $filter_loc;
}
// Apply Building Filter
if (!empty($filter_build)) {
    $sql .= " AND ar.BUILDING_ID = ?";
    $params[] = $filter_build;
}
// Apply Pen Filter
if (!empty($filter_pen)) {
    $sql .= " AND ar.PEN_ID = ?";
    $params[] = $filter_pen;
}
// Apply Status Filter (NEW)
if (!empty($filter_status)) {
    if ($filter_status === 'Active') {
        $sql .= " AND ar.CURRENT_STATUS = 'Active'";
    } elseif ($filter_status === 'Sold') {
        $sql .= " AND ar.CURRENT_STATUS = 'Sold'";
    } elseif ($filter_status === 'Deceased') {
        // Matches 'Cull', 'Deceased', or 'Dead' depending on your DB convention
        $sql .= " AND (ar.CURRENT_STATUS = 'Cull' OR ar.CURRENT_STATUS = 'Deceased' OR ar.CURRENT_STATUS = 'Dead')";
    }
}

// Apply Tag Search
if (!empty($search_tag)) {
    $sql .= " AND ar.TAG_NO LIKE ?";
    $params[] = "%$search_tag%";
}

// Order by newest first
$sql .= " ORDER BY ar.ANIMAL_ID DESC";

// Apply Limit
if ($limit_opt !== 'ALL') {
    $sql .= " LIMIT " . (int)$limit_opt;
}

// Execute Query
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$animals = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- 3. FETCH DROPDOWN DATA ---
$locations = $conn->query("SELECT * FROM locations ORDER BY LOCATION_NAME")->fetchAll();

$buildings = [];
if ($filter_loc) {
    $b_stmt = $conn->prepare("SELECT * FROM buildings WHERE LOCATION_ID = ?");
    $b_stmt->execute([$filter_loc]);
    $buildings = $b_stmt->fetchAll();
}

$pens = [];
if ($filter_build) {
    $p_stmt = $conn->prepare("SELECT * FROM pens WHERE BUILDING_ID = ?");
    $p_stmt->execute([$filter_build]);
    $pens = $p_stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Animal History Records - FarmPro</title>
    <style>
        /* --- THEME STYLES --- */
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: #e2e8f0;
            min-height: 100vh;
        }
        .container { max-width: 1600px; margin: 0 auto; padding: 2rem; }

        /* Header */
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; }
        .page-title {
            font-size: 2rem; font-weight: 800; 
            background: linear-gradient(135deg, #22c55e, #16a34a);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }
        .back-link { color: #94a3b8; text-decoration: none; font-weight: 500; transition: color 0.2s; }
        .back-link:hover { color: #fff; }

        /* Filter Section */
        .filter-card {
            background: rgba(30, 41, 59, 0.6);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 12px; padding: 1.5rem;
            margin-bottom: 2rem;
            backdrop-filter: blur(10px);
        }
        
        .filter-form { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); 
            gap: 1rem; 
            align-items: end; 
        }
        
        .form-group label { 
            display: block; font-size: 0.75rem; color: #94a3b8; 
            margin-bottom: 0.4rem; text-transform: uppercase; letter-spacing: 0.5px; 
        }
        .form-select, .form-input {
            width: 100%; padding: 10px; background: #0f172a; border: 1px solid #334155;
            color: white; border-radius: 6px; font-size: 0.9rem;
        }
        .form-select:focus, .form-input:focus { border-color: #22c55e; outline: none; }

        .btn-filter {
            background: #22c55e; color: white; border: none; padding: 10px 20px;
            border-radius: 6px; font-weight: 600; cursor: pointer; height: 38px; width: 100%;
        }
        .btn-reset {
            background: transparent; color: #94a3b8; border: 1px solid #475569;
            padding: 9px 20px; border-radius: 6px; text-decoration: none; 
            display: flex; align-items: center; justify-content: center; height: 38px;
        }
        .btn-reset:hover { border-color: #94a3b8; color: white; }

        /* Data Table */
        .table-responsive { overflow-x: auto; border-radius: 12px; border: 1px solid #334155; }
        .data-table { width: 100%; border-collapse: collapse; background: rgba(15, 23, 42, 0.4); }
        
        .data-table th {
            text-align: left; padding: 1rem;
            background: rgba(15, 23, 42, 0.9);
            color: #94a3b8; font-size: 0.8rem; text-transform: uppercase;
            border-bottom: 1px solid #334155;
        }
        .data-table td {
            padding: 1rem; border-bottom: 1px solid rgba(255,255,255,0.05);
            color: #cbd5e1; font-size: 0.95rem; vertical-align: middle;
        }
        .data-table tr:hover { background: rgba(255,255,255,0.02); }

        /* Custom Badges */
        .tag-badge { background: rgba(34, 197, 94, 0.1); color: #22c55e; padding: 4px 8px; border-radius: 4px; font-family: monospace; font-weight: bold; }
        
        .status-badge { padding: 4px 10px; border-radius: 12px; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; }
        .status-active { background: rgba(34, 197, 94, 0.2); color: #4ade80; }
        .status-sold { background: rgba(234, 179, 8, 0.2); color: #facc15; }
        .status-cull { background: rgba(239, 68, 68, 0.2); color: #f87171; } /* For Deceased/Cull */

        .location-sub { font-size: 0.85rem; color: #64748b; margin-top: 4px; }
        .empty-state { text-align: center; padding: 4rem; color: #64748b; font-style: italic; }
    </style>
</head>
<body>

<div class="container">
    <div class="page-header">
        <div>
            <h1 class="page-title">Animal Record History</h1>
            <p style="color: #64748b;">View-only archive of all livestock records.</p>
        </div>
        <a href="animal_record_dashboard.php" class="back-link">&larr; Back to Dashboard</a>
    </div>

    <div class="filter-card">
        <form method="GET" class="filter-form">
            
            <div class="form-group">
                <label>Location</label>
                <select name="location_id" class="form-select" onchange="this.form.submit()">
                    <option value="">-- All --</option>
                    <?php foreach ($locations as $l): ?>
                        <option value="<?= $l['LOCATION_ID'] ?>" <?= $filter_loc == $l['LOCATION_ID'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($l['LOCATION_NAME']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Building</label>
                <select name="building_id" class="form-select" onchange="this.form.submit()" <?= empty($filter_loc) ? 'disabled' : '' ?>>
                    <option value="">-- All --</option>
                    <?php foreach ($buildings as $b): ?>
                        <option value="<?= $b['BUILDING_ID'] ?>" <?= $filter_build == $b['BUILDING_ID'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($b['BUILDING_NAME']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Status</label>
                <select name="status" class="form-select" onchange="this.form.submit()">
                    <option value="">All Statuses</option>
                    <option value="Active" <?= $filter_status == 'Active' ? 'selected' : '' ?>>🟢 Active</option>
                    <option value="Sold" <?= $filter_status == 'Sold' ? 'selected' : '' ?>>🟡 Sold</option>
                    <option value="Deceased" <?= $filter_status == 'Deceased' ? 'selected' : '' ?>>🔴 Deceased / Cull</option>
                </select>
            </div>

            <div class="form-group">
                <label>Show</label>
                <select name="limit" class="form-select" onchange="this.form.submit()">
                    <option value="10" <?= $limit_opt == '10' ? 'selected' : '' ?>>10 Rows</option>
                    <option value="50" <?= $limit_opt == '50' ? 'selected' : '' ?>>50 Rows</option>
                    <option value="100" <?= $limit_opt == '100' ? 'selected' : '' ?>>100 Rows</option>
                    <option value="ALL" <?= $limit_opt == 'ALL' ? 'selected' : '' ?>>All Records</option>
                </select>
            </div>

            <div class="form-group" style="grid-column: span 2;">
                <label>Search Tag No</label>
                <input type="text" name="search" class="form-input" placeholder="e.g. A001" value="<?= htmlspecialchars($search_tag) ?>">
            </div>

            <div class="form-group" style="display: flex; gap: 10px;">
                <button type="submit" class="btn-filter">Search</button>
                <a href="animal_record_history.php" class="btn-reset">Reset</a>
            </div>
        </form>
    </div>

    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Tag No</th>
                    <th>Type / Breed</th>
                    <th>Sex</th>
                    <th>Stage / Class</th>
                    <th>Current Location</th>
                    <th>Weight</th>
                    <th>Status</th>
                    <th>Birth Date</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($animals) > 0): ?>
                    <?php foreach ($animals as $row): 
                        // Status Badge Color Logic
                        $statusText = $row['CURRENT_STATUS'];
                        $statusClass = 'status-active';
                        
                        if ($statusText === 'Sold') $statusClass = 'status-sold';
                        elseif (in_array($statusText, ['Cull', 'Deceased', 'Dead'])) $statusClass = 'status-cull';
                    ?>
                    <tr>
                        <td><span class="tag-badge"><?= $row['TAG_NO'] ?></span></td>
                        <td>
                            <div><?= htmlspecialchars($row['ANIMAL_TYPE_NAME']) ?></div>
                            <div style="font-size: 0.8rem; color: #64748b;"><?= htmlspecialchars($row['BREED_NAME']) ?></div>
                        </td>
                        <td><?= $row['SEX'] ?></td>
                        <td><?= $row['STAGE_NAME'] ?? '<span style="color:#64748b;">Unknown</span>' ?></td>
                        <td>
                            <div><?= htmlspecialchars($row['LOCATION_NAME']) ?></div>
                            <div class="location-sub">
                                <?= htmlspecialchars($row['BUILDING_NAME'] ?? '-') ?> &bull; <?= htmlspecialchars($row['PEN_NAME'] ?? '-') ?>
                            </div>
                        </td>
                        <td style="font-weight: bold; color: #fbbf24;">
                            <?= $row['CURRENT_ACTUAL_WEIGHT'] > 0 ? number_format($row['CURRENT_ACTUAL_WEIGHT'], 2) . ' kg' : '-' ?>
                        </td>
                        <td>
                            <span class="status-badge <?= $statusClass ?>">
                                <?= $statusText ?>
                            </span>
                        </td>
                        <td>
                            <?= $row['BIRTH_DATE'] ? date('M d, Y', strtotime($row['BIRTH_DATE'])) : '-' ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" class="empty-state">
                            No records found matching your criteria.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <div style="margin-top: 1rem; text-align: right; color: #64748b; font-size: 0.9rem;">
        Showing <?= count($animals) ?> records
    </div>

</div>

</body>
</html>