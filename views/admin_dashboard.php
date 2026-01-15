<?php
// views/admin_dashboard.php
error_reporting(0);
ini_set('display_errors', 0);
$page = "admin_dashboard";
include '../common/navbar.php';
include '../security/checkRole.php';    
checkRole(2);

include '../process/autoUpdateAnimalClasses.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FarmPro Admin Management</title>
    <link rel="stylesheet" href="../css/admin_dashboard.css">
    <style>
        /* [Existing Styles Kept Intact] */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: #e2e8f0; min-height: 100vh;
        }
        .admin-container { max-width: 1400px; margin: 0 auto; }
        .admin-header { text-align: center; margin-bottom: 3rem; }
        .admin-title {
            font-size: 3rem; font-weight: bold; margin-bottom: 1rem;
            background: linear-gradient(135deg, #22c55e, #16a34a);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
        }
        .admin-subtitle { color: #94a3b8; font-size: 1.2rem; margin-bottom: 0.5rem; }
        .admin-description { color: #64748b; font-size: 1rem; }
        
        .quick-stats {
            background: rgba(30, 41, 59, 0.6); border: 1px solid rgba(34, 197, 94, 0.2);
            border-radius: 16px; padding: 2rem; backdrop-filter: blur(10px); margin-bottom: 2rem;
        }
        .stats-title { font-size: 1.5rem; font-weight: 600; color: #22c55e; margin-bottom: 1.5rem; text-align: center; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem; }
        .stat-card {
            text-align: center; padding: 1rem; background: rgba(15, 23, 42, 0.5);
            border-radius: 12px; transition: all 0.3s ease;
        }
        .stat-card:hover { transform: translateY(-2px); background: rgba(15, 23, 42, 0.7); }
        .stat-value { font-size: 2rem; font-weight: bold; color: #22c55e; margin-bottom: 0.5rem; }
        .stat-desc { color: #94a3b8; font-size: 0.9rem; }

        .management-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 1.5rem; margin-bottom: 2rem;
        }
        .management-card {
            background: rgba(30, 41, 59, 0.6); border: 1px solid rgba(34, 197, 94, 0.2);
            border-radius: 16px; padding: 2rem; backdrop-filter: blur(10px);
            transition: all 0.3s ease; cursor: pointer; position: relative; overflow: hidden;
            min-height: 280px; display: flex; flex-direction: column; text-decoration: none; color: inherit;
        }
        .management-card:hover {
            transform: translateY(-8px) scale(1.02);
            border-color: rgba(34, 197, 94, 0.4);
            box-shadow: 0 20px 40px rgba(34, 197, 94, 0.15);
        }
        .card-icon {
            width: 70px; height: 70px; border-radius: 16px; display: flex;
            align-items: center; justify-content: center; font-size: 2rem; color: white;
            margin-bottom: 1.5rem; box-shadow: 0 8px 20px rgba(0, 0, 0, 0.3);
        }
        
        /* Icon Colors */
        .card-icon.animal_record { background: linear-gradient(135deg, #f59e0b, #d97706); }
        .card-icon.animal { background: linear-gradient(135deg, #84cc16, #65a30d); }
        .card-icon.itemtype { background: linear-gradient(135deg, #06b6d4, #0891b2); }
        .card-icon.location { background: linear-gradient(135deg, #8b5cf6, #7c3aed); }
        .card-icon.building { background: linear-gradient(135deg, #ef4444, #dc2626); }
        .card-icon.pen { background: linear-gradient(135deg, #3b82f6, #1d4ed8); }
        .card-icon.breed { background: linear-gradient(135deg, #14b8a6, #0d9488); }
        .card-icon.veterinary { background: linear-gradient(135deg, #ec4899, #db2777); }
        .card-icon.medicine { background: linear-gradient(135deg, #f97316, #ea580c); }
        .card-icon.disease { background: linear-gradient(135deg, #a855f7, #9333ea); }
        .card-icon.buyer { background: linear-gradient(135deg, #6366f1, #4f46e5); } /* Indigo for Buyers */

        .card-title { font-size: 1.5rem; font-weight: 600; color: #22c55e; margin-bottom: 1rem; }
        .card-description { color: #94a3b8; font-size: 0.95rem; line-height: 1.5; margin-bottom: 1rem; flex-grow: 1; }
        .card-stats { display: flex; justify-content: space-between; align-items: center; margin-top: auto; padding-top: 1rem; border-top: 1px solid rgba(30, 41, 59, 0.8); }
        .stat-item { text-align: center; }
        .stat-number { font-size: 1.2rem; font-weight: bold; color: #22c55e; }
        .stat-label { font-size: 0.8rem; color: #64748b; margin-top: 0.25rem; }
        .card-action { display: flex; align-items: center; gap: 0.5rem; color: #64748b; font-size: 0.9rem; transition: color 0.3s ease; }
        .management-card:hover .card-action { color: #22c55e; }

        @media (max-width: 768px) {
            body { padding: 1rem; }
            .admin-title { font-size: 2rem; }
            .management-grid { grid-template-columns: 1fr; gap: 1rem; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <header class="admin-header">
            <h1 class="admin-title">Admin Management Center</h1>
            <p class="admin-subtitle">Comprehensive Farm Management System</p>
            <p class="admin-description">Select any management module below to configure and maintain your farm operations</p>
        </header>

        <div class="quick-stats">
            <h2 class="stats-title">System Overview</h2>
            <div class="stats-grid">
                <div class="stat-card"><div class="stat-value">24</div><div class="stat-desc">Active Farms</div></div>
                <div class="stat-card"><div class="stat-value">1,247</div><div class="stat-desc">Total Animals</div></div>
                <div class="stat-card"><div class="stat-value">89</div><div class="stat-desc">Buildings</div></div>
                <div class="stat-card"><div class="stat-value">156</div><div class="stat-desc">Pens</div></div>
                <div class="stat-card"><div class="stat-value">12</div><div class="stat-desc">Veterinarians</div></div>
                <div class="stat-card"><div class="stat-value">340</div><div class="stat-desc">Stock Items</div></div>
            </div>
        </div>

        <div class="management-grid">
            
            <a href="animal_record_dashboard.php" class="management-card">
                <div class="card-icon animal_record">🐮</div> 
                <h3 class="card-title">Animal Record</h3>
                <p class="card-description">Track livestock population, individual health histories, breeding logs, and vaccination schedules.</p>
                <div class="card-stats">
                    <div class="stat-item"><div class="stat-number">156</div><div class="stat-label">Heads</div></div>
                    <div class="stat-item"><div class="stat-number">5</div><div class="stat-label">Treatments</div></div>
                    <div class="card-action">Manage →</div>
                </div>
            </a>

            <a href="animal_type.php" class="management-card">
                <div class="card-icon animal">🐄</div>
                <h3 class="card-title">Animal Type</h3>
                <p class="card-description">Configure species, characteristics, and care requirements.</p>
                <div class="card-stats">
                    <div class="stat-item"><div class="stat-number">15</div><div class="stat-label">Species</div></div>
                    <div class="stat-item"><div class="stat-number">3</div><div class="stat-label">Types</div></div>
                    <div class="card-action">Manage →</div>
                </div>
            </a>

            <a href="location.php" class="management-card">
                <div class="card-icon location">📍</div>
                <h3 class="card-title">Location</h3>
                <p class="card-description">Manage farm locations, zones, and field mapping.</p>
                <div class="card-stats">
                    <div class="stat-item"><div class="stat-number">8</div><div class="stat-label">Zones</div></div>
                    <div class="stat-item"><div class="stat-number">24</div><div class="stat-label">Plots</div></div>
                    <div class="card-action">View Map →</div>
                </div>
            </a>

            <a href="building.php" class="management-card">
                <div class="card-icon building">🏢</div>
                <h3 class="card-title">Building</h3>
                <p class="card-description">Oversee farm structures, barns, and storage facilities.</p>
                <div class="card-stats">
                    <div class="stat-item"><div class="stat-number">89</div><div class="stat-label">Buildings</div></div>
                    <div class="stat-item"><div class="stat-number">6</div><div class="stat-label">Maint.</div></div>
                    <div class="card-action">Inspect →</div>
                </div>
            </a>

            <a href="pen.php" class="management-card">
                <div class="card-icon pen">🏠</div>
                <h3 class="card-title">Pen</h3>
                <p class="card-description">Manage animal enclosures and capacity planning.</p>
                <div class="card-stats">
                    <div class="stat-item"><div class="stat-number">156</div><div class="stat-label">Pens</div></div>
                    <div class="stat-item"><div class="stat-number">23</div><div class="stat-label">Free</div></div>
                    <div class="card-action">Allocate →</div>
                </div>
            </a>

            <a href="breed.php" class="management-card">
                <div class="card-icon breed">🧬</div>
                <h3 class="card-title">Breed</h3>
                <p class="card-description">Track genetics and breeding programs.</p>
                <div class="card-stats">
                    <div class="stat-item"><div class="stat-number">42</div><div class="stat-label">Breeds</div></div>
                    <div class="stat-item"><div class="stat-number">8</div><div class="stat-label">Programs</div></div>
                    <div class="card-action">Browse →</div>
                </div>
            </a>

            <a href="veterinary.php" class="management-card">
                <div class="card-icon veterinary">👩‍⚕️</div>
                <h3 class="card-title">Veterinary</h3>
                <p class="card-description">Manage vet services and health records.</p>
                <div class="card-stats">
                    <div class="stat-item"><div class="stat-number">12</div><div class="stat-label">Vets</div></div>
                    <div class="stat-item"><div class="stat-number">34</div><div class="stat-label">Visits</div></div>
                    <div class="card-action">Schedule →</div>
                </div>
            </a>

            <a href="diseases.php" class="management-card">
                <div class="card-icon disease">🦠</div>
                <h3 class="card-title">Diseases</h3>
                <p class="card-description">Monitor outbreaks and prevention protocols.</p>
                <div class="card-stats">
                    <div class="stat-item"><div class="stat-number">2</div><div class="stat-label">Active</div></div>
                    <div class="stat-item"><div class="stat-number">98%</div><div class="stat-label">Safe</div></div>
                    <div class="card-action">Monitor →</div>
                </div>
            </a>

            <a href="buyers.php" class="management-card">
                <div class="card-icon buyer">🤝</div>
                <h3 class="card-title">Buyer</h3>
                <p class="card-description">Manage customer profiles, contact information, and purchase history logs.</p>
                <div class="card-stats">
                    <div class="stat-item"><div class="stat-number">150+</div><div class="stat-label">Clients</div></div>
                    <div class="stat-item"><div class="stat-number">Active</div><div class="stat-label">Status</div></div>
                    <div class="card-action">Manage →</div>
                </div>
            </a>

        </div>
    </div>
</body>
</html>