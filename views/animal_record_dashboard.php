<?php
// views/animal_record_dashboard.php
$page = "admin_dashboard"; // Active Tab
include '../config/Connection.php';

include '../security/checkAccess.php';
checkAccess('animal_record');
include '../common/navbar.php';
include '../common/chat_support.php';

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Animal Records Dashboard | FarmPro</title>
    
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
            
            --emerald:        #10b981;
            --emerald-dim:    rgba(16,185,129,0.12);
            --emerald-glow:   rgba(16,185,129,0.25);
            --blue:           #3b82f6;
            --amber:          #f59e0b;
            
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
        .container { max-width: 1200px; margin: 0 auto; padding: 2rem 1.5rem; }

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
        .page-header { text-align: center; margin-bottom: 3.5rem; }
        .page-title { font-size: clamp(2rem, 4vw, 3rem); font-weight: 700; margin: 0 0 0.75rem 0; color: #fff; letter-spacing: -0.02em;}
        .page-title span { background: linear-gradient(135deg, var(--emerald), #047857); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .page-subtitle { color: var(--text-secondary); font-size: 1.05rem; margin: 0; max-width: 600px; margin: 0 auto; line-height: 1.5;}

        /* ─── CARD GRID ─── */
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(340px, 1fr));
            gap: 2rem;
            max-width: 900px;
            margin: 0 auto;
        }

        /* ─── CARD DESIGN ─── */
        .record-card {
            background: var(--bg-surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-xl);
            padding: 2.5rem;
            transition: all var(--transition);
            text-decoration: none;
            display: flex;
            flex-direction: column;
            position: relative;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        
        /* Subtle Hover Effect */
        .record-card::before {
            content: ''; position: absolute; top: 0; left: -100%; width: 100%; height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.03), transparent);
            transition: left 0.8s ease; pointer-events: none;
        }
        .record-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px -10px rgba(0,0,0,0.5);
            border-color: rgba(16,185,129,0.4);
        }
        .record-card:hover::before { left: 100%; }

        /* Icon Box */
        .card-icon-box {
            width: 72px; height: 72px;
            border-radius: var(--radius-lg);
            display: flex; align-items: center; justify-content: center;
            font-size: 2rem; color: white;
            margin-bottom: 1.5rem;
            box-shadow: 0 8px 16px rgba(0,0,0,0.3);
        }
        
        .bg-amber { background: linear-gradient(135deg, var(--amber), #b45309); }
        .bg-blue  { background: linear-gradient(135deg, var(--blue), #1d4ed8); }

        /* Typography */
        .card-title {
            font-size: 1.5rem; font-weight: 700;
            color: #fff;
            margin-bottom: 0.75rem;
        }
        .card-desc {
            color: var(--text-secondary);
            line-height: 1.6;
            font-size: 0.95rem;
            margin-bottom: 2.5rem;
            flex-grow: 1; /* Push footer down */
        }

        /* Action Link */
        .action-link {
            font-size: 0.95rem; font-weight: 700; color: var(--text-muted);
            transition: color var(--transition); display: flex; align-items: center; gap: 6px;
            margin-top: auto;
        }
        .record-card:hover .action-link { color: var(--emerald); }

        /* --- MOBILE RESPONSIVENESS --- */
        @media (max-width: 768px) {
            .container { padding: 1.5rem 1rem; }
            .page-header { margin-bottom: 2.5rem; }
            .dashboard-grid { grid-template-columns: 1fr; gap: 1.5rem; }
            .record-card { padding: 2rem; }
            .card-icon-box { width: 60px; height: 60px; font-size: 1.75rem; }
        }

    </style>
</head>
<body>

    <div class="container">
        
        <div class="top-bar">
            <a href="admin_dashboard.php" class="back-link">
                <i class="fa-solid fa-arrow-left"></i> Back to Dashboard
            </a>
            <span class="page-badge"><i class="fa-solid fa-database"></i>Animal Records</span>
        </div>

        <header class="page-header">
            <h1 class="page-title">Animal Records <span>Dashboard</span></h1>
            <p class="page-subtitle">Central hub for livestock data management, health tracking, and historical archiving.</p>
        </header>

        <div class="dashboard-grid">

            <a href="animal_record.php" class="record-card">
                <div class="card-icon-box bg-amber">
                    <i class="fa-solid fa-cow"></i>
                </div>
                <h3 class="card-title">Animal Management</h3>
                <p class="card-desc">
                    Track active livestock population, individual health histories, breeding logs, and view real-time herd metrics.
                </p>
                <div class="action-link">Manage Active Records <i class="fa-solid fa-arrow-right"></i></div>
            </a>

            <a href="animal_record_history.php" class="record-card">
                <div class="card-icon-box bg-blue">
                    <i class="fa-solid fa-box-archive"></i>
                </div>
                <h3 class="card-title">Record History</h3>
                <p class="card-desc">
                    Access a read-only archive of past livestock data. Search by tag number or filter by location to audit historical operations.
                </p>
                <div class="action-link">View Archives <i class="fa-solid fa-arrow-right"></i></div>
            </a>

        </div>
    </div>

</body>
</html>