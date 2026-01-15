<?php

$page="settings";
include '../common/navbar.php';
include '../security/checkRole.php';    
checkRole(3);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FarmPro Settings</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: #e2e8f0;
            min-height: 100vh;
        }

        .settings-container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .settings-header {
            text-align: center;
            margin-bottom: 3rem;
        }

        .settings-title {
            font-size: 3rem;
            font-weight: bold;
            margin-bottom: 1rem;
            background: linear-gradient(135deg, #22c55e, #16a34a);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .settings-subtitle {
            color: #94a3b8;
            font-size: 1.2rem;
            margin-bottom: 0.5rem;
        }

        .settings-description {
            color: #64748b;
            font-size: 1rem;
        }

        /* Settings Grid */
        .settings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .settings-card {
            background: rgba(30, 41, 59, 0.6);
            border: 1px solid rgba(34, 197, 94, 0.2);
            border-radius: 16px;
            padding: 2.5rem;
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }

        .settings-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(34, 197, 94, 0.05), transparent);
            transition: left 0.8s;
        }

        .settings-card:hover::before {
            left: 100%;
        }

        .settings-card:hover {
            transform: translateY(-8px) scale(1.02);
            border-color: rgba(34, 197, 94, 0.4);
            box-shadow: 0 25px 50px rgba(34, 197, 94, 0.15);
        }

        .card-icon {
            width: 80px;
            height: 80px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            color: white;
            margin-bottom: 2rem;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
        }

        .card-icon.units { background: linear-gradient(135deg, #3b82f6, #1d4ed8); }
        .card-icon.accounts { background: linear-gradient(135deg, #8b5cf6, #7c3aed); }

        .card-title {
            font-size: 1.8rem;
            font-weight: 600;
            color: #22c55e;
            margin-bottom: 1rem;
        }

        .card-description {
            color: #94a3b8;
            font-size: 1rem;
            line-height: 1.6;
            margin-bottom: 2rem;
        }

        .card-features {
            list-style: none;
            margin-bottom: 2rem;
        }

        .card-features li {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            color: #cbd5e1;
            font-size: 0.95rem;
            margin-bottom: 0.75rem;
            padding: 0.5rem 0;
        }

        .card-features li::before {
            content: '✓';
            color: #22c55e;
            font-weight: bold;
            font-size: 1.1rem;
        }

        .card-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid rgba(30, 41, 59, 0.8);
        }

        .stat-item {
            text-align: center;
            padding: 1rem;
            background: rgba(15, 23, 42, 0.5);
            border-radius: 12px;
            transition: all 0.3s ease;
        }

        .stat-item:hover {
            background: rgba(15, 23, 42, 0.7);
            transform: translateY(-2px);
        }

        .stat-number {
            font-size: 1.5rem;
            font-weight: bold;
            color: #22c55e;
            margin-bottom: 0.25rem;
        }

        .stat-label {
            font-size: 0.85rem;
            color: #64748b;
        }

        .card-action {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            background: linear-gradient(135deg, #22c55e, #16a34a);
            color: white;
            padding: 1rem 2rem;
            border-radius: 12px;
            font-weight: 600;
            font-size: 1rem;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
        }

        .card-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(34, 197, 94, 0.3);
            background: linear-gradient(135deg, #16a34a, #15803d);
        }

        /* Quick Settings Section */
        .quick-settings {
            background: rgba(30, 41, 59, 0.6);
            border: 1px solid rgba(34, 197, 94, 0.2);
            border-radius: 16px;
            padding: 2rem;
            backdrop-filter: blur(10px);
            margin-bottom: 2rem;
        }

        .quick-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #22c55e;
            margin-bottom: 1.5rem;
            text-align: center;
        }

        .quick-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .quick-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            background: rgba(15, 23, 42, 0.5);
            border-radius: 12px;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .quick-item:hover {
            background: rgba(15, 23, 42, 0.7);
            transform: translateY(-2px);
        }

        .quick-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            color: white;
        }

        .quick-icon.profile { background: linear-gradient(135deg, #f59e0b, #d97706); }
        .quick-icon.security { background: linear-gradient(135deg, #ef4444, #dc2626); }
        .quick-icon.notifications { background: linear-gradient(135deg, #06b6d4, #0891b2); }
        .quick-icon.backup { background: linear-gradient(135deg, #84cc16, #65a30d); }
        .quick-icon.theme { background: linear-gradient(135deg, #ec4899, #db2777); }
        .quick-icon.system { background: linear-gradient(135deg, #6366f1, #4f46e5); }

        .quick-text {
            flex: 1;
        }

        .quick-label {
            color: #e2e8f0;
            font-weight: 500;
            margin-bottom: 0.25rem;
        }

        .quick-desc {
            color: #64748b;
            font-size: 0.85rem;
        }

        /* System Info Section */
        .system-info {
            background: rgba(30, 41, 59, 0.6);
            border: 1px solid rgba(34, 197, 94, 0.2);
            border-radius: 16px;
            padding: 2rem;
            backdrop-filter: blur(10px);
            margin-top: 2rem;
        }

        .system-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #22c55e;
            margin-bottom: 1.5rem;
            text-align: center;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
        }

        .info-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            background: rgba(15, 23, 42, 0.5);
            border-radius: 12px;
        }

        .info-label {
            color: #94a3b8;
            font-weight: 500;
        }

        .info-value {
            color: #22c55e;
            font-weight: 600;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            body {
                padding: 1rem;
            }

            .settings-title {
                font-size: 2rem;
            }

            .settings-subtitle {
                font-size: 1rem;
            }

            .settings-grid {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }

            .settings-card {
                padding: 2rem;
            }

            .card-icon {
                width: 70px;
                height: 70px;
                font-size: 2rem;
            }

            .card-title {
                font-size: 1.5rem;
            }

            .quick-grid {
                grid-template-columns: 1fr;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 480px) {
            .settings-title {
                font-size: 1.5rem;
            }

            .settings-card {
                padding: 1.5rem;
            }

            .card-icon {
                width: 60px;
                height: 60px;
                font-size: 1.5rem;
            }

            .card-stats {
                grid-template-columns: 1fr;
            }
        }

        /* Animation Effects */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .settings-card {
            animation: fadeInUp 0.6s ease-out;
        }

        .settings-card:nth-child(1) { animation-delay: 0.1s; }
        .settings-card:nth-child(2) { animation-delay: 0.3s; }

        .quick-settings {
            animation: fadeInUp 0.8s ease-out;
            animation-delay: 0.5s;
        }

        .system-info {
            animation: fadeInUp 0.8s ease-out;
            animation-delay: 0.7s;
        }

        /* Active state for clicked cards */
        .settings-card.active {
            border-color: #22c55e;
            box-shadow: 0 0 30px rgba(34, 197, 94, 0.3);
        }
    </style>
</head>
<body>
    <div class="settings-container">
        <!-- Header Section -->
        <header class="settings-header">
            <h1 class="settings-title">System Settings</h1>
            <p class="settings-subtitle">Configure and customize your FarmPro experience</p>
            <p class="settings-description">Manage system preferences, user accounts, and operational settings</p>
        </header>

        <!-- Main Settings Grid -->
        <div class="settings-grid">
            <!-- Manage Units -->
            <div class="settings-card" data-setting="manage-units">
                <div class="card-icon units">📏</div>
                <h3 class="card-title">Manage Units</h3>
                <p class="card-description">Configure measurement units, conversion rates, and standardize units across all farm operations and reporting systems.</p>
                
                <ul class="card-features">
                    <li>Weight & Volume Units</li>
                    <li>Distance & Area Measurements</li>
                    <li>Temperature & Time Units</li>
                    <li>Custom Unit Definitions</li>
                    <li>Conversion Rate Settings</li>
                    <li>Regional Unit Standards</li>
                </ul>

                <div class="card-stats">
                    <div class="stat-item">
                        <div class="stat-number">24</div>
                        <div class="stat-label">Active Units</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number">6</div>
                        <div class="stat-label">Categories</div>
                    </div>
                </div>

                <button class="card-action" onclick="window.location.href='units.php'">
                    ⚙️ Configure Units
                </button>
            </div>

            <!-- Manage Accounts -->
            <div class="settings-card" data-setting="manage-accounts">
                <div class="card-icon accounts">👥</div>
                <h3 class="card-title">Manage Accounts</h3>
                <p class="card-description">Control user accounts, permissions, roles, and access levels. Manage team members and their system privileges.</p>
                
                <ul class="card-features">
                    <li>User Account Creation</li>
                    <li>Role-Based Permissions</li>
                    <li>Access Level Control</li>
                    <li>Account Status Management</li>
                    <li>Password Policy Settings</li>
                    <li>Activity Monitoring</li>
                </ul>

                <div class="card-stats">
                    <div class="stat-item">
                        <div class="stat-number">47</div>
                        <div class="stat-label">Total Users</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number">5</div>
                        <div class="stat-label">User Roles</div>
                    </div>
                </div>

                <button class="card-action" onclick="window.location.href='accounts.php'">
                    👤 Manage Users
                </button>
            </div>
        </div>
    </div>

    <script>
        // Settings Page Functionality
        class SettingsPage {
            constructor() {
                this.settingsCards = document.querySelectorAll('.settings-card');
                this.quickItems = document.querySelectorAll('.quick-item');
                this.init();
            }

            init() {
                this.bindEvents();
                this.animateElements();
            }

            bindEvents() {
                // Main settings cards
                this.settingsCards.forEach(card => {
                    card.addEventListener('mouseenter', (e) => {
                        this.handleCardHover(card, true);
                    });

                    card.addEventListener('mouseleave', (e) => {
                        this.handleCardHover(card, false);
                    });
                });

                // Quick settings items
                this.quickItems.forEach(item => {
                    item.addEventListener('click', (e) => {
                        this.handleQuickSettingClick(item);
                    });
                });

                // Keyboard navigation
                document.addEventListener('keydown', (e) => {
                    if (e.key === 'Escape') {
                        this.clearActiveStates();
                    }
                });
            }

            handleQuickSettingClick(item) {
                const quickSetting = item.dataset.quick;
                
                // Visual feedback
                item.style.transform = 'scale(0.95)';
                item.style.backgroundColor = 'rgba(34, 197, 94, 0.1)';
                
                setTimeout(() => {
                    item.style.transform = '';
                    item.style.backgroundColor = '';
                }, 200);

                console.log(`Accessing ${quickSetting} quick settings...`);
            }

            handleCardHover(card, isHovering) {
                if (isHovering) {
                    card.style.zIndex = '10';
                } else {
                    card.style.zIndex = '1';
                }
            }

            clearActiveStates() {
                this.settingsCards.forEach(card => {
                    card.classList.remove('active');
                });
            }

            animateElements() {
                // Stagger animation for settings cards
                this.settingsCards.forEach((card, index) => {
                    card.style.animationDelay = `${index * 0.2}s`;
                });

                // Animate quick items
                this.quickItems.forEach((item, index) => {
                    item.style.opacity = '0';
                    item.style.transform = 'translateY(20px)';
                    item.style.transition = 'all 0.6s ease-out';
                    
                    setTimeout(() => {
                        item.style.opacity = '1';
                        item.style.transform = 'translateY(0)';
                    }, 800 + (index * 100));
                });
            }

            // Public method to update statistics
            updateStats(setting, stats) {
                const card = document.querySelector(`[data-setting="${setting}"]`);
                if (card) {
                    const statNumbers = card.querySelectorAll('.stat-number');
                    stats.forEach((stat, index) => {
                        if (statNumbers[index]) {
                            statNumbers[index].textContent = stat;
                            statNumbers[index].style.transform = 'scale(1.1)';
                            setTimeout(() => {
                                statNumbers[index].style.transform = 'scale(1)';
                            }, 300);
                        }
                    });
                }
            }

            // Public method to highlight specific setting
            highlightSetting(setting) {
                const card = document.querySelector(`[data-setting="${setting}"]`);
                if (card) {
                    card.classList.add('active');
                    card.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    
                    setTimeout(() => {
                        card.classList.remove('active');
                    }, 3000);
                }
            }
        }

        // Initialize when DOM is ready
        document.addEventListener('DOMContentLoaded', () => {
            window.settingsPage = new SettingsPage();
        });
    </script>
</body>
</html>