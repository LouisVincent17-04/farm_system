<?php
// views/settings.php
include '../config/Connection.php';
include '../security/checkAccess.php';
checkAccess('settings');
$page="settings";
include '../common/navbar.php';
include '../common/chat_support.php';

if($_SESSION['user']['USER_TYPE'] !== 4)
{
    echo "<script>alert('Access denied.'); window.location.href = 'admin_dashboard.php';</script>";
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>FarmPro System Settings</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />

    <style>
        /* ─── CSS VARIABLES ─── */
        :root {
            --bg-base:        #080f1a;
            --bg-surface:     #0d1829;
            --bg-elevated:    #111f35;
            --bg-hover:       #162540;
            --border:         rgba(255,255,255,0.07);
            --border-active:  rgba(139,92,246,0.5); /* Purple default for settings */
            --purple:         #a855f7;
            --purple-dim:     rgba(168,85,247,0.12);
            --purple-glow:    rgba(168,85,247,0.25);
            --amber:          #f59e0b;
            --amber-dim:      rgba(245,158,11,0.12);
            --amber-glow:     rgba(245,158,11,0.25);
            --blue:           #3b82f6;
            --blue-dim:       rgba(59,130,246,0.12);
            --blue-glow:      rgba(59,130,246,0.25);
            --green:          #10b981;
            --text-primary:   #f1f5f9;
            --text-secondary: #94a3b8;
            --text-muted:     #475569;
            --radius-md:      10px;
            --radius-lg:      14px;
            --radius-xl:      20px;
            --font:           'DM Sans', system-ui, sans-serif;
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
                radial-gradient(circle at 0% 0%, rgba(168,85,247,0.05) 0%, transparent 40%),
                radial-gradient(circle at 100% 100%, rgba(59,130,246,0.04) 0%, transparent 40%);
        }

        .settings-container { max-width: 1400px; margin: 0 auto; padding: 2rem 1.5rem; }

        /* ─── HEADER ─── */
        .settings-header { text-align: center; margin-bottom: 3.5rem; }
        .settings-title {
            font-size: clamp(2rem, 5vw, 3rem); font-weight: 700; letter-spacing: -0.04em; margin-bottom: 0.75rem;
            background: linear-gradient(135deg, #fff 30%, var(--text-secondary));
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }
        .settings-title span { color: var(--purple); -webkit-text-fill-color: var(--purple); }
        .settings-subtitle { color: var(--text-secondary); font-size: 1.1rem; font-weight: 400; max-width: 700px; margin: 0 auto; line-height: 1.6; }

        /* ─── GRID & CARDS ─── */
        .settings-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 2rem; margin-bottom: 2rem; justify-content: center;
        }

        .settings-card {
            background: var(--bg-surface); border: 1px solid var(--border);
            border-radius: var(--radius-xl); padding: 2.5rem 2rem;
            position: relative; overflow: hidden; display: flex; flex-direction: column;
            transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            cursor: pointer; text-decoration: none; color: inherit;
        }

        .settings-card::before {
            content: ''; position: absolute; top: 0; right: 0; width: 120px; height: 120px;
            border-radius: 50%; filter: blur(40px); opacity: 0.3; transition: all 0.5s ease;
        }

        .settings-card:hover {
            transform: translateY(-8px);
            background: var(--bg-hover);
            box-shadow: 0 20px 40px -10px rgba(0,0,0,0.5);
        }

        /* Card Branding Colors */
        .card-accounts:hover { border-color: rgba(168,85,247,0.4); }
        .card-accounts::before { background: var(--purple); }
        
        .card-weaning:hover { border-color: rgba(245,158,11,0.4); }
        .card-weaning::before { background: var(--amber); }
        
        .card-farms:hover { border-color: rgba(59,130,246,0.4); }
        .card-farms::before { background: var(--blue); }

        .card-icon {
            width: 64px; height: 64px; border-radius: 16px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.75rem; color: white; margin-bottom: 1.5rem;
            box-shadow: 0 10px 25px rgba(0,0,0,0.3);
        }
        .card-accounts .card-icon { background: linear-gradient(135deg, var(--purple), #7e22ce); }
        .card-weaning  .card-icon { background: linear-gradient(135deg, var(--amber), #b45309); }
        .card-farms    .card-icon { background: linear-gradient(135deg, var(--blue), #1d4ed8); }

        .card-title { font-size: 1.5rem; font-weight: 700; margin-bottom: 1rem; color: #fff; }
        .card-description { color: var(--text-secondary); font-size: 0.95rem; line-height: 1.6; margin-bottom: 1.5rem; flex-grow: 1; }

        .card-features { list-style: none; margin-bottom: 2.5rem; padding: 0; }
        .card-features li {
            display: flex; align-items: center; gap: 0.75rem; color: var(--text-primary);
            font-size: 0.9rem; margin-bottom: 0.75rem; font-weight: 500;
        }
        .card-features li i { color: var(--green); font-size: 1rem; }

        /* ─── ACTION BUTTONS ─── */
        .card-action {
            display: flex; align-items: center; justify-content: center; gap: 0.75rem;
            color: white; padding: 14px 24px; border-radius: var(--radius-md);
            font-weight: 700; font-size: 0.95rem; border: none; font-family: var(--font);
            transition: all var(--transition); width: 100%; margin-top: auto;
            pointer-events: none; /* Interaction handled by card wrapper */
        }
        
        .card-accounts .card-action { background: var(--purple); box-shadow: 0 4px 15px var(--purple-glow); }
        .card-accounts:hover .card-action { background: #c084fc; box-shadow: 0 8px 25px var(--purple-glow); transform: translateY(-2px); }

        .card-weaning .card-action { background: var(--amber); box-shadow: 0 4px 15px var(--amber-glow); color: #000; }
        .card-weaning:hover .card-action { background: #fbbf24; box-shadow: 0 8px 25px var(--amber-glow); transform: translateY(-2px); }

        .card-farms .card-action { background: var(--blue); box-shadow: 0 4px 15px var(--blue-glow); }
        .card-farms:hover .card-action { background: #60a5fa; box-shadow: 0 8px 25px var(--blue-glow); transform: translateY(-2px); }

        /* Initial Animation */
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .settings-card { opacity: 0; animation: fadeInUp 0.5s ease-out forwards; }
        .settings-card:nth-child(1) { animation-delay: 0.1s; }
        .settings-card:nth-child(2) { animation-delay: 0.2s; }
        .settings-card:nth-child(3) { animation-delay: 0.3s; }

        @media (max-width: 768px) {
            .settings-container { padding: 1.5rem 1rem; }
            .settings-grid { grid-template-columns: 1fr; }
            .settings-card { padding: 2rem 1.5rem; }
        }
    </style>
</head>
<body>
    <div class="settings-container">
        
        <header class="settings-header">
            <h1 class="settings-title">System <span>Settings</span></h1>
            <p class="settings-subtitle">Configure, customize, and secure your FarmPro environment.</p>
        </header>

        <div class="settings-grid">
            
            <a href="accounts.php" class="settings-card card-accounts">
                <div class="card-icon"><i class="fa-solid fa-users-gear"></i></div>
                <h3 class="card-title">Manage Accounts</h3>
                <p class="card-description">Control user accounts, define access permissions, and manage your core team's system privileges.</p>
                
                <ul class="card-features">
                    <li><i class="fa-solid fa-check"></i> User Account Creation</li>
                    <li><i class="fa-solid fa-check"></i> Role-Based Permissions</li>
                    <li><i class="fa-solid fa-check"></i> Access Level Control</li>
                </ul>

                <div class="card-action">
                    <i class="fa-solid fa-user-shield"></i> Manage Users
                </div>
            </a>

            <a href="auto_weaning.php" class="settings-card card-weaning">
                <div class="card-icon"><i class="fa-solid fa-baby-carriage"></i></div>
                <h3 class="card-title">Auto Weaning</h3>
                <p class="card-description">Establish the automated weaning threshold. This determines when a Sow's status resets from Birthing to Dry.</p>
                
                <ul class="card-features">
                    <li><i class="fa-solid fa-check"></i> Location-Specific Timers</li>
                    <li><i class="fa-solid fa-check"></i> Automated Status Resets</li>
                    <li><i class="fa-solid fa-check"></i> Breeding Cycle Accuracy</li>
                </ul>

                <div class="card-action">
                    <i class="fa-solid fa-gears"></i> Configure Thresholds
                </div>
            </a>

            <a href="../globalxadminzportal/my_farms.php" class="settings-card card-farms">
                <div class="card-icon"><i class="fa-solid fa-tractor"></i></div>
                <h3 class="card-title">Return To Global</h3>
                <p class="card-description">Navigate back to the main portal to switch between different farm databases or manage other active clients.</p>
                
                <ul class="card-features">
                    <li><i class="fa-solid fa-check"></i> Switch Active Farm</li>
                    <li><i class="fa-solid fa-check"></i> View Farm Overviews</li>
                    <li><i class="fa-solid fa-check"></i> Global Admin Access</li>
                </ul>

                <div class="card-action">
                    <i class="fa-solid fa-arrow-right-from-bracket"></i> Go to Global Portal
                </div>
            </a>

        </div>
    </div>
</body>
</html>