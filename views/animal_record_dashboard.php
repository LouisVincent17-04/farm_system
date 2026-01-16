<?php
// views/animal_record_dashboard.php
$page = "admin_dashboard"; // Active Tab
include '../common/navbar.php';
include '../config/Connection.php';
include '../security/checkRole.php';    
checkRole(2); 
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> <title>Animal Records - FarmPro</title>
    <style>
        /* --- CORE STYLES --- */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: #e2e8f0;
            min-height: 100vh;
        }
        .container { max-width: 1200px; margin: 0 auto; padding: 3rem 1.5rem; }
        
        .page-header { margin-bottom: 3rem; }
        .page-title {
            font-size: 2.5rem; font-weight: 800; margin-bottom: 0.5rem;
            color: white;
        }
        .page-subtitle { color: #94a3b8; font-size: 1.1rem; }

        /* --- CARD GRID --- */
        .dashboard-grid {
            display: grid;
            /* Changed minmax to 300px to fit mobile screens better before stacking */
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
        }

        /* --- CARD DESIGN --- */
        .record-card {
            background: #1e293b; /* Dark Blue Slate */
            border: 1px solid #334155;
            border-radius: 20px;
            padding: 2.5rem;
            transition: transform 0.2s, box-shadow 0.2s;
            text-decoration: none;
            display: flex;
            flex-direction: column;
            position: relative;
            overflow: hidden;
            height: 100%; /* Equal Height */
        }
        .record-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px -10px rgba(0, 0, 0, 0.3);
            border-color: #475569;
        }

        /* Icon Box */
        .card-icon-box {
            width: 70px; height: 70px;
            border-radius: 16px;
            display: flex; align-items: center; justify-content: center;
            font-size: 2.5rem; color: white;
            margin-bottom: 1.5rem;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.2);
        }
        
        /* Gradients for Icons */
        .bg-orange { background: linear-gradient(135deg, #f59e0b, #d97706); }
        .bg-green  { background: linear-gradient(135deg, #84cc16, #65a30d); }

        /* Typography */
        .card-title {
            font-size: 1.5rem; font-weight: 700;
            color: #22c55e; /* Green Title Color */
            margin-bottom: 1rem;
        }
        .card-desc {
            color: #94a3b8; /* Muted Text */
            line-height: 1.6;
            font-size: 1rem;
            margin-bottom: 2rem;
            flex-grow: 1; /* Push footer down */
        }

        /* Footer (Action Link Only) */
        .card-footer {
            margin-top: auto;
            display: flex;
            justify-content: flex-end; /* Align right */
            align-items: center;
            border-top: 1px solid #334155;
            padding-top: 1.5rem;
        }

        /* Action Link */
        .action-link {
            color: #22c55e;
            font-weight: 600;
            font-size: 1rem;
            display: flex; align-items: center; gap: 8px;
            transition: transform 0.2s;
        }
        .record-card:hover .action-link { transform: translateX(5px); }

        /* --- MOBILE RESPONSIVENESS --- */
        @media (max-width: 768px) {
            .container { padding: 1.5rem 1rem; } /* Reduce padding */
            
            .page-title { font-size: 1.8rem; } /* Smaller title */
            .page-subtitle { font-size: 1rem; }

            .dashboard-grid {
                grid-template-columns: 1fr; /* Stack cards vertically */
                gap: 1.5rem;
            }

            .record-card {
                padding: 1.5rem; /* Smaller internal padding */
            }

            .card-icon-box {
                width: 60px; height: 60px; font-size: 2rem; /* Smaller icon */
            }
        }

    </style>
</head>
<body>

    <div class="container">
        <header class="page-header">
            <h1 class="page-title">Animal Records Dashboard</h1>
            <p class="page-subtitle">Central hub for livestock data management and historical tracking.</p>
        </header>

        <div class="dashboard-grid">

            <a href="animal_record.php" class="record-card">
                <div class="card-icon-box bg-orange">
                    🐮
                </div>
                <h3 class="card-title">Animal Management</h3>
                <p class="card-desc">
                    Track livestock population, individual health histories, breeding logs, and vaccination schedules.
                </p>
                <div class="card-footer">
                    <div class="action-link">Manage &rarr;</div>
                </div>
            </a>

            <a href="animal_record_history.php" class="record-card">
                <div class="card-icon-box bg-green">
                    📜
                </div>
                <h3 class="card-title">Record History</h3>
                <p class="card-desc">
                    Access a read-only archive of livestock data. Filter by location, building, or pen, and search by tag number to view historical records.
                </p>
                <div class="card-footer">
                    <div class="action-link">View History &rarr;</div>
                </div>
            </a>

        </div>
    </div>

</body>
</html>