<?php
// views/admin_dashboard.php
error_reporting(0);
ini_set('display_errors', 0);
$page = "admin_dashboard";
include '../config/Connection.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include '../security/checkAccess.php';
if($_SESSION['user']['USER_TYPE'] == 1){
    header("Location: new_user.php?msg=dashboardproblems");
    exit;
} else {
    checkAccess('dashboard');
}

include '../process/autoUpdateAnimalClasses.php';
include '../common/navbar.php';
include '../common/chat_support.php';
include '../common/upcoming_birth_modal.php';

// Fetch active farm name from session (fallback to 'My Farm' if not set)
$active_farm_name = $_SESSION['farm_name'] ?? 'My Farm';

// Fetch quick counts
$emp_count = 0;
$role_count = 0;
$supplier_count = 0;
try {
    $emp_count = $conn->query("SELECT COUNT(*) FROM employees WHERE STATUS = 'Active'")->fetchColumn();
    $role_count = $conn->query("SELECT COUNT(*) FROM farm_roles")->fetchColumn();
    $supplier_count = $conn->query("SELECT COUNT(*) FROM suppliers WHERE STATUS = 'Active'")->fetchColumn(); 
    $active_location = $conn->query("SELECT COUNT(*) FROM locations")->fetchColumn();
    $total_animals = $conn->query("SELECT COUNT(*) FROM animal_records WHERE IS_ACTIVE = 1")->fetchColumn();
    $total_buildings = $conn->query("SELECT COUNT(*) FROM buildings")->fetchColumn();    
    $total_pens = $conn->query("SELECT COUNT(*) FROM pens")->fetchColumn();  
    $total_breeds = $conn->query("SELECT COUNT(*) FROM breeds")->fetchColumn(); // Added count for breeds
} catch(Exception $e) {}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Admin Management Center | FarmPro</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
    <style>
        /* ─── CSS VARIABLES ─── */
        :root {
            --bg-base:        #080f1a;
            --bg-surface:     #0d1829;
            --bg-elevated:    #111f35;
            --bg-hover:       #162540;
            --border:         rgba(255,255,255,0.07);
            --green:          #22c55e;
            --green-glow:     rgba(34,197,94,0.15);
            --text-primary:   #f1f5f9;
            --text-secondary: #94a3b8;
            --text-muted:     #475569;
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
            background-image: 
                radial-gradient(circle at 0% 0%, rgba(34,197,94,0.05) 0%, transparent 40%),
                radial-gradient(circle at 100% 100%, rgba(56,189,248,0.03) 0%, transparent 40%);
        }
        .admin-container { max-width: 1560px; margin: 0 auto; padding: 2rem 1.5rem; }

        /* ─── HEADER ─── */
        .admin-header { text-align: center; margin-bottom: 3.5rem; }
        
        .farm-badge {
            display: inline-flex; align-items: center; gap: 8px; 
            background: var(--green-glow); color: var(--green); 
            padding: 6px 16px; border-radius: 99px; margin-bottom: 1rem; 
            font-weight: 700; font-size: 1.5rem; letter-spacing: 0.05em; 
            border: 1px solid rgba(34,197,94,0.3); text-transform: uppercase;
        }

        .admin-title {
            font-size: clamp(1.25rem, 5vw, 2rem); font-weight: 700; letter-spacing: -0.04em; margin-bottom: 0.75rem;
            background: linear-gradient(135deg, #fff 30%, var(--text-secondary));
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }
        .admin-title span { color: var(--green); -webkit-text-fill-color: var(--green); }
        .admin-subtitle { color: var(--text-secondary); font-size: 1.1rem; font-weight: 400; max-width: 700px; margin: 0 auto; line-height: 1.6; }

        /* ─── QUICK STATS ─── */
        .quick-stats {
            background: var(--bg-surface); border: 1px solid var(--border);
            border-radius: var(--radius-xl); padding: 1.5rem; margin-bottom: 2.5rem;
            box-shadow: 0 10px 30px -10px rgba(0,0,0,0.5);
        }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 1rem; }
        .stat-card {
            background: var(--bg-elevated); border: 1px solid var(--border);
            border-radius: var(--radius-lg); padding: 1.25rem; text-align: center;
            transition: transform var(--transition), border-color var(--transition);
        }
        .stat-card:hover { transform: translateY(-3px); border-color: rgba(34,197,94,0.3); }
        .stat-number { font-family: var(--font-mono); font-size: 1.75rem; font-weight: 700; color: var(--green); margin-bottom: 0.25rem; }
        .stat-desc { color: var(--text-muted); font-size: 0.7rem; text-transform: uppercase; font-weight: 700; letter-spacing: 0.05em; }

        /* ─── MANAGEMENT GRID ─── */
        .management-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 1.5rem;
        }
        .management-card {
            background: var(--bg-surface); border: 1px solid var(--border);
            border-radius: var(--radius-xl); padding: 1.75rem; text-decoration: none; color: inherit;
            display: flex; flex-direction: column; transition: all var(--transition);
            position: relative; overflow: hidden;
        }
        .management-card::after {
            content: ''; position: absolute; top: 0; right: 0; width: 100px; height: 100px;
            background: radial-gradient(circle at top right, rgba(255,255,255,0.03), transparent 70%);
        }

        .management-card:hover {
            transform: translateY(-5px); background: var(--bg-hover);
            border-color: rgba(255,255,255,0.15); box-shadow: var(--shadow-md);
        }

        .card-icon {
            width: 52px; height: 52px; border-radius: 12px; display: flex;
            align-items: center; justify-content: center; font-size: 1.5rem;
            margin-bottom: 1.25rem;
        }
        
        /* Module specific colors */
        .ic-record { background: rgba(245, 158, 11, 0.1); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.2); }
        .ic-staff  { background: rgba(14, 165, 233, 0.1); color: #0ea5e9; border: 1px solid rgba(14, 165, 233, 0.2); }
        .ic-roles  { background: rgba(251, 191, 36, 0.1); color: #fbbf24; border: 1px solid rgba(251, 191, 36, 0.2); }
        .ic-species{ background: rgba(132, 204, 22, 0.1); color: #84cc16; border: 1px solid rgba(132, 204, 22, 0.2); }
        .ic-breed  { background: rgba(250, 204, 21, 0.1); color: #facc15; border: 1px solid rgba(250, 204, 21, 0.2); }
        .ic-loc    { background: rgba(139, 92, 246, 0.1); color: #8b5cf6; border: 1px solid rgba(139, 92, 246, 0.2); }
        .ic-infra  { background: rgba(239, 68, 68, 0.1); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.2); }
        .ic-vet    { background: rgba(236, 72, 153, 0.1); color: #ec4899; border: 1px solid rgba(236, 72, 153, 0.2); }
        .ic-buyer  { background: rgba(99, 102, 241, 0.1); color: #6366f1; border: 1px solid rgba(99, 102, 241, 0.2); }
        .ic-vendor { background: rgba(244, 63, 94, 0.1); color: #f43f5e; border: 1px solid rgba(244, 63, 94, 0.2); }

        .card-title { font-size: 1.25rem; font-weight: 700; color: #fff; margin-bottom: 0.75rem; }
        .card-description { color: var(--text-secondary); font-size: 0.9rem; line-height: 1.5; margin-bottom: 1.5rem; flex-grow: 1; }
        
        .card-footer {
            display: flex; justify-content: space-between; align-items: center;
            padding-top: 1.25rem; border-top: 1px solid var(--border);
        }
        .mini-stat { display: flex; flex-direction: column; }
        .mini-val { font-family: var(--font-mono); font-size: 1.1rem; font-weight: 700; color: var(--green); }
        .mini-lbl { font-size: 0.65rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700; }
        
        .btn-go {
            width: 32px; height: 32px; border-radius: 50%; background: var(--bg-elevated);
            display: flex; align-items: center; justify-content: center; color: var(--text-secondary);
            transition: all var(--transition); border: 1px solid var(--border);
        }
        .management-card:hover .btn-go { background: var(--green); color: #000; transform: translateX(3px); border-color: var(--green); }

        /* ─── RESPONSIVE ─── */
        @media (max-width: 768px) {
            .admin-container { padding: 1.5rem 1rem; }
            .quick-stats { padding: 1.25rem; }
            .management-grid { grid-template-columns: 1fr; }
            .stat-number { font-size: 1.5rem; }
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <header class="admin-header">
            <div class="farm-badge" onclick="window.location.href='../globalxadminzportal/my_farms.php'">
                <i class="fa-solid fa-tractor"></i> <?= htmlspecialchars($active_farm_name) ?>
            </div>

            <h2 class="admin-title">Management <span>Center</span></h2>
            <p class="admin-subtitle">Configure, maintain, and oversee your global farm infrastructure and personnel data from a centralized hub.</p>
        </header>

        <div class="quick-stats">
            <div class="stats-grid">
                <div class="stat-card"><div class="stat-number"><?= $active_location ?></div><div class="stat-desc">Locations</div></div>
                <div class="stat-card"><div class="stat-number"><?= $total_animals ?></div><div class="stat-desc">Active Stock</div></div>
                <div class="stat-card"><div class="stat-number"><?= $total_buildings ?></div><div class="stat-desc">Structures</div></div>
                <div class="stat-card"><div class="stat-number"><?= $total_pens ?></div><div class="stat-desc">Pens</div></div>
                <div class="stat-card"><div class="stat-number"><?= $emp_count ?></div><div class="stat-desc">Personnel</div></div>
                <div class="stat-card"><div class="stat-number"><?= $supplier_count ?></div><div class="stat-desc">Vendors</div></div>
            </div>
        </div>

        <div class="management-grid">
            
            <?php if( hasAccess('animal_record') == 1): ?>
            <a href="animal_record_dashboard.php" class="management-card">
                <div class="card-icon ic-record"><i class="fa-solid fa-folder-open"></i></div> 
                <h3 class="card-title">Animal Records</h3>
                <p class="card-description">Comprehensive management of individual livestock profiles, health histories, and performance tracking.</p>
                <div class="card-footer">
                    <div class="mini-stat"><span class="mini-val"><?= $total_animals ?></span><span class="mini-lbl">Active Heads</span></div>
                    <div class="btn-go"><i class="fa-solid fa-arrow-right"></i></div>
                </div>
            </a>
            <?php endif; ?>

            <?php if( hasAccess('employee_list') == 1 || $_SESSION['user']['USER_TYPE'] < 3): ?>
            <a href="employees.php" class="management-card">
                <div class="card-icon ic-staff"><i class="fa-solid fa-user-tie"></i></div>
                <h3 class="card-title">Employee List</h3>
                <p class="card-description">Central directory for farm staff profiles, professional contact details, and hiring milestones.</p>
                <div class="card-footer">
                    <div class="mini-stat"><span class="mini-val"><?= $emp_count ?></span><span class="mini-lbl">Active Staff</span></div>
                    <div class="btn-go"><i class="fa-solid fa-arrow-right"></i></div>
                </div>
            </a>
            <?php endif; ?>

            <?php if( hasAccess('farm_roles') == 1 || $_SESSION['user']['USER_TYPE'] < 3): ?>
            <a href="farm_roles.php" class="management-card">
                <div class="card-icon ic-roles"><i class="fa-solid fa-id-card-clip"></i></div>
                <h3 class="card-title">Farm Roles</h3>
                <p class="card-description">Define organizational Job titles, standard responsibilities, and operational designations.</p>
                <div class="card-footer">
                    <div class="mini-stat"><span class="mini-val"><?= $role_count ?></span><span class="mini-lbl">Defined Roles</span></div>
                    <div class="btn-go"><i class="fa-solid fa-arrow-right"></i></div>
                </div>
            </a>
            <?php endif; ?>

            <?php if( hasAccess('animal_type') == 1 || $_SESSION['user']['USER_TYPE'] < 3): ?>
            <a href="animal_type.php" class="management-card">
                <div class="card-icon ic-species"><i class="fa-solid fa-dna"></i></div>
                <h3 class="card-title">Animal Species</h3>
                <p class="card-description">Maintain global species categories, physiological characteristics, and classification standards.</p>
                <div class="card-footer">
                    <div class="mini-stat"><span class="mini-val">Active</span><span class="mini-lbl">Registry</span></div>
                    <div class="btn-go"><i class="fa-solid fa-arrow-right"></i></div>
                </div>
            </a>
            <?php endif; ?>

            <?php if( hasAccess('breed') == 1 || $_SESSION['user']['USER_TYPE'] < 3): ?>
            <a href="breed.php" class="management-card">
                <div class="card-icon ic-breed"><i class="fa-solid fa-fingerprint"></i></div>
                <h3 class="card-title">Animal Breeds</h3>
                <p class="card-description">Manage specific genetic breeds tied to your animal species registry.</p>
                <div class="card-footer">
                    <div class="mini-stat"><span class="mini-val"><?= $total_breeds ?></span><span class="mini-lbl">Registered Breeds</span></div>
                    <div class="btn-go"><i class="fa-solid fa-arrow-right"></i></div>
                </div>
            </a>
            <?php endif; ?>

            <?php if( hasAccess('location') == 1 || $_SESSION['user']['USER_TYPE'] < 3): ?>
            <a href="location.php" class="management-card">
                <div class="card-icon ic-loc"><i class="fa-solid fa-map-location-dot"></i></div>
                <h3 class="card-title">Site Locations</h3>
                <p class="card-description">Manage physical farm sites, operational zones, and mapping for land distribution.</p>
                <div class="card-footer">
                    <div class="mini-stat"><span class="mini-val"><?= $active_location ?></span><span class="mini-lbl">Active Sites</span></div>
                    <div class="btn-go"><i class="fa-solid fa-arrow-right"></i></div>
                </div>
            </a>
            <?php endif; ?>

            <?php if( hasAccess('building') == 1 || $_SESSION['user']['USER_TYPE'] < 3): ?>
            <a href="building.php" class="management-card">
                <div class="card-icon ic-infra"><i class="fa-solid fa-warehouse"></i></div>
                <h3 class="card-title">Buildings</h3>
                <p class="card-description">Manage specific housing structures, barns, and storage facilities within a location.</p>
                <div class="card-footer">
                    <div class="mini-stat"><span class="mini-val"><?= $total_buildings ?></span><span class="mini-lbl">Structures</span></div>
                    <div class="btn-go"><i class="fa-solid fa-arrow-right"></i></div>
                </div>
            </a>
            <?php endif; ?>

          <?php if( hasAccess('pen') == 1 || $_SESSION['user']['USER_TYPE'] < 3): ?>
            <a href="pen.php" class="management-card">
                <div class="card-icon ic-infra">
                    <i class="fa-solid fa-paw"></i>
                </div>
                <h3 class="card-title">Pens</h3>
                <p class="card-description">Manage individual holding pens, capacities, and group assignments inside buildings.</p>
                <div class="card-footer">
                    <div class="mini-stat">
                        <span class="mini-val"><?= $total_pens ?></span>
                        <span class="mini-lbl">Holdings</span>
                    </div>
                    <div class="btn-go">
                        <i class="fa-solid fa-arrow-right"></i>
                    </div>
                </div>
            </a>
        <?php endif; ?>

            <?php if( hasAccess('veterinary') == 1 || $_SESSION['user']['USER_TYPE'] < 3): ?>
            <a href="veterinary.php" class="management-card">
                <div class="card-icon ic-vet"><i class="fa-solid fa-user-md"></i></div>
                <h3 class="card-title">Veterinary</h3>
                <p class="card-description">Professional network of practitioners, specialized services, and clinical consultation logs.</p>
                <div class="card-footer">
                    <div class="mini-stat"><span class="mini-val">Medical</span><span class="mini-lbl">Network</span></div>
                    <div class="btn-go"><i class="fa-solid fa-arrow-right"></i></div>
                </div>
            </a>
            <?php endif; ?>

            <?php if( hasAccess('buyer') == 1 || $_SESSION['user']['USER_TYPE'] < 3): ?>
            <a href="buyers.php" class="management-card">
                <div class="card-icon ic-buyer"><i class="fa-solid fa-handshake"></i></div>
                <h3 class="card-title">Customer Registry</h3>
                <p class="card-description">Database of buyers, business contact directories, and comprehensive transactional history.</p>
                <div class="card-footer">
                    <div class="mini-stat"><span class="mini-val">Verified</span><span class="mini-lbl">Partners</span></div>
                    <div class="btn-go"><i class="fa-solid fa-arrow-right"></i></div>
                </div>
            </a>
            <?php endif; ?>

            <?php if( hasAccess('suppliers') == 1 || $_SESSION['user']['USER_TYPE'] < 3): ?>
            <a href="suppliers.php" class="management-card">
                <div class="card-icon ic-vendor"><i class="fa-solid fa-truck-ramp-box"></i></div>
                <h3 class="card-title">Suppliers</h3>
                <p class="card-description">Manage external vendor relations, feed providers, and essential supply chain partners.</p>
                <div class="card-footer">
                    <div class="mini-stat"><span class="mini-val"><?= $supplier_count ?></span><span class="mini-lbl">Active Vendors</span></div>
                    <div class="btn-go"><i class="fa-solid fa-arrow-right"></i></div>
                </div>
            </a>
            <?php endif; ?>

        </div>
    </div>
</body>
</html>