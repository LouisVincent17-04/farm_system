<?php
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FarmPro Settings</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: #e2e8f0; min-height: 100vh; }
        .settings-container { max-width: 1400px; margin: 0 auto; padding: 2rem; }
        
        .settings-header { text-align: center; margin-bottom: 3rem; }
        .settings-title { font-size: 3rem; font-weight: bold; margin-bottom: 1rem; background: linear-gradient(135deg, #22c55e, #16a34a); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
        .settings-subtitle { color: #94a3b8; font-size: 1.2rem; margin-bottom: 0.5rem; }
        .settings-description { color: #64748b; font-size: 1rem; }

        /* Settings Grid */
        .settings-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 2rem; margin-bottom: 2rem; justify-content: center; }
        .settings-card { background: rgba(30, 41, 59, 0.6); border: 1px solid rgba(34, 197, 94, 0.2); border-radius: 16px; padding: 2.5rem; backdrop-filter: blur(10px); transition: all 0.3s ease; position: relative; overflow: hidden; display: flex; flex-direction: column; }
        
        .settings-card::before { content: ''; position: absolute; top: 0; left: -100%; width: 100%; height: 100%; background: linear-gradient(90deg, transparent, rgba(34, 197, 94, 0.05), transparent); transition: left 0.8s; }
        .settings-card:hover::before { left: 100%; }
        .settings-card:hover { transform: translateY(-8px) scale(1.02); border-color: rgba(34, 197, 94, 0.4); box-shadow: 0 25px 50px rgba(34, 197, 94, 0.15); }

        .card-icon { width: 80px; height: 80px; border-radius: 20px; display: flex; align-items: center; justify-content: center; font-size: 2.5rem; color: white; margin-bottom: 2rem; box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3); }
        .card-icon.accounts { background: linear-gradient(135deg, #8b5cf6, #7c3aed); }
        .card-icon.weaning { background: linear-gradient(135deg, #f59e0b, #d97706); } 
        .card-icon.farms { background: linear-gradient(135deg, #3b82f6, #2563eb); } 

        .card-title { font-size: 1.8rem; font-weight: 600; color: #22c55e; margin-bottom: 1rem; }
        .card-description { color: #94a3b8; font-size: 1rem; line-height: 1.6; margin-bottom: 2rem; flex-grow: 1; }

        .card-features { list-style: none; margin-bottom: 2rem; }
        .card-features li { display: flex; align-items: center; gap: 0.75rem; color: #cbd5e1; font-size: 0.95rem; margin-bottom: 0.75rem; padding: 0.5rem 0; }
        .card-features li::before { content: '✓'; color: #22c55e; font-weight: bold; font-size: 1.1rem; }

        /* Button Styles */
        .card-action { display: flex; align-items: center; justify-content: center; gap: 0.75rem; color: white; padding: 1rem 2rem; border-radius: 12px; font-weight: 600; font-size: 1rem; border: none; cursor: pointer; transition: all 0.3s ease; width: 100%; margin-top: auto; }
        
        .card-action.green { background: linear-gradient(135deg, #22c55e, #16a34a); }
        .card-action.green:hover { transform: translateY(-2px); box-shadow: 0 10px 30px rgba(34, 197, 94, 0.3); background: linear-gradient(135deg, #16a34a, #15803d); }
        
        .card-action.orange { background: linear-gradient(135deg, #f59e0b, #d97706); }
        .card-action.orange:hover { transform: translateY(-2px); box-shadow: 0 10px 30px rgba(245, 158, 11, 0.3); background: linear-gradient(135deg, #d97706, #b45309); }

        .card-action.blue { background: linear-gradient(135deg, #3b82f6, #2563eb); }
        .card-action.blue:hover { transform: translateY(-2px); box-shadow: 0 10px 30px rgba(59, 130, 246, 0.3); background: linear-gradient(135deg, #2563eb, #1d4ed8); }

        @keyframes fadeInUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
        .settings-card { animation: fadeInUp 0.6s ease-out forwards; }
        
        @media (max-width: 768px) { .settings-title { font-size: 2rem; } .settings-card { padding: 2rem; } }
    </style>
</head>
<body>
    <div class="settings-container">
        
        <header class="settings-header">
            <h1 class="settings-title">System Settings</h1>
            <p class="settings-subtitle">Configure and customize your FarmPro experience</p>
            <p class="settings-description">Manage user accounts, roles, and security privileges</p>
        </header>

        <div class="settings-grid">
            
            <div class="settings-card" onclick="window.location.href='accounts.php'">
                <div class="card-icon accounts">👥</div>
                <h3 class="card-title">Manage Accounts</h3>
                <p class="card-description">Control user accounts, permissions, roles, and access levels. Manage team members and their system privileges.</p>
                
                <ul class="card-features">
                    <li>User Account Creation</li>
                    <li>Role-Based Permissions</li>
                    <li>Access Level Control</li>
                </ul>

                <button class="card-action green" onclick="window.location.href='accounts.php'">
                    👤 Manage Users
                </button>
            </div>

            <div class="settings-card" onclick="window.location.href='auto_weaning.php'">
                <div class="card-icon weaning">🍼</div>
                <h3 class="card-title" style="color: #f59e0b;">Auto Weaning</h3>
                <p class="card-description">Configure the automated weaning threshold per location. Determines when a Sow's status resets from Birthing to Dry.</p>
                
                <ul class="card-features">
                    <li>Location-Specific Timers</li>
                    <li>Automated Status Resets</li>
                    <li>Breeding Cycle Accuracy</li>
                </ul>

                <button class="card-action orange" onclick="window.location.href='auto_weaning.php'">
                    ⚙️ Configure Thresholds
                </button>
            </div>

            <div class="settings-card" onclick="window.location.href='../globalxadminzportal/my_farms.php'">
                <div class="card-icon farms">🚜</div>
                <h3 class="card-title" style="color: #3b82f6;">Return To Farms</h3>
                <p class="card-description">Navigate back to the main portal to switch between different farm databases or manage other clients.</p>
                
                <ul class="card-features">
                    <li>Switch Active Farm</li>
                    <li>View Farm Overviews</li>
                    <li>Global Admin Access</li>
                </ul>

                <button class="card-action blue" onclick="window.location.href='../globalxadminzportal/my_farms.php'">
                    ⬅️ Go to My Farms
                </button>
            </div>

        </div>
    </div>
</body>
</html>