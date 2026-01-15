<?php
error_reporting(0);
ini_set('display_errors', 0);
$page = "reports";
include '../common/navbar.php';
include '../security/checkRole.php';    
checkRole(2);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FarmPro Reports Dashboard</title>
    <link rel="stylesheet" href="../css/admin_dashboard.css">
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

        .admin-container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .admin-header {
            text-align: center;
            margin-bottom: 3rem;
        }

        .admin-title {
            font-size: 3rem;
            font-weight: bold;
            margin-bottom: 1rem;
            background: linear-gradient(135deg, #22c55e, #16a34a);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .admin-subtitle {
            color: #94a3b8;
            font-size: 1.2rem;
            margin-bottom: 0.5rem;
        }

        .admin-description {
            color: #64748b;
            font-size: 1rem;
        }

        .quick-stats {
            background: rgba(30, 41, 59, 0.6);
            border: 1px solid rgba(34, 197, 94, 0.2);
            border-radius: 16px;
            padding: 2rem;
            backdrop-filter: blur(10px);
            margin-bottom: 2rem;
        }

        .stats-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #22c55e;
            margin-bottom: 1.5rem;
            text-align: center;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
        }

        .stat-card {
            text-align: center;
            padding: 1rem;
            background: rgba(15, 23, 42, 0.5);
            border-radius: 12px;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            background: rgba(15, 23, 42, 0.7);
        }

        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: #22c55e;
            margin-bottom: 0.5rem;
        }

        .stat-desc {
            color: #94a3b8;
            font-size: 0.9rem;
        }

        .management-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .management-card {
            background: rgba(30, 41, 59, 0.6);
            border: 1px solid rgba(34, 197, 94, 0.2);
            border-radius: 16px;
            padding: 2rem;
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
            overflow: hidden;
            min-height: 280px;
            display: flex;
            flex-direction: column;
            text-decoration: none;
            color: inherit;
        }

        .management-card:hover {
            transform: translateY(-8px) scale(1.02);
            border-color: rgba(34, 197, 94, 0.4);
            box-shadow: 0 20px 40px rgba(34, 197, 94, 0.15);
        }

        .card-icon {
            width: 70px;
            height: 70px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: white;
            margin-bottom: 1.5rem;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.3);
        }

        .card-icon.animal { background: linear-gradient(135deg, #f59e0b, #d97706); }
        .card-icon.users { background: linear-gradient(135deg, #84cc16, #65a30d); }
        .card-icon.medicine { background: linear-gradient(135deg, #06b6d4, #0891b2); }
        .card-icon.feeds { background: linear-gradient(135deg, #8b5cf6, #7c3aed); }
        .card-icon.housing { background: linear-gradient(135deg, #ef4444, #dc2626); }
        .card-icon.equipment { background: linear-gradient(135deg, #3b82f6, #1d4ed8); }
        .card-icon.sanitation { background: linear-gradient(135deg, #14b8a6, #0d9488); }
        .card-icon.breeding { background: linear-gradient(135deg, #ec4899, #db2777); }
        .card-icon.admin { background: linear-gradient(135deg, #f97316, #ea580c); }
        .card-icon.maintenance { background: linear-gradient(135deg, #a855f7, #9333ea); }
        .card-icon.utilities { background: linear-gradient(135deg, #06b6d4, #0284c7); }
        .card-icon.vitamins { background: linear-gradient(135deg, #10b981, #059669); }
        .card-icon.vaccine { background: linear-gradient(135deg, #f59e0b, #d97706); }
        .card-icon.others { background: linear-gradient(135deg, #64748b, #475569); }
        .card-icon.audit { background: linear-gradient(135deg, #dc2626, #b91c1c); }
        .card-icon.medication { background: linear-gradient(135deg, #7c3aed, #6d28d9); }
        .card-icon.vaccination { background: linear-gradient(135deg, #0891b2, #0e7490); }
        .card-icon.vitamins-trans { background: linear-gradient(135deg, #16a34a, #15803d); }
        .card-icon.feeding-trans { background: linear-gradient(135deg, #ea580c, #c2410c); }
        .card-icon.financial { background: linear-gradient(135deg, #22c55e, #16a34a); }

        .card-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #22c55e;
            margin-bottom: 1rem;
        }

        .card-description {
            color: #94a3b8;
            font-size: 0.95rem;
            line-height: 1.5;
            margin-bottom: 1rem;
            flex-grow: 1;
        }

        .card-stats {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: auto;
            padding-top: 1rem;
            border-top: 1px solid rgba(30, 41, 59, 0.8);
        }

        .stat-item {
            text-align: center;
        }

        .stat-number {
            font-size: 1.2rem;
            font-weight: bold;
            color: #22c55e;
        }

        .stat-label {
            font-size: 0.8rem;
            color: #64748b;
            margin-top: 0.25rem;
        }

        .card-action {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #64748b;
            font-size: 0.9rem;
            transition: color 0.3s ease;
        }

        .management-card:hover .card-action {
            color: #22c55e;
        }

        @media (max-width: 768px) {
            body {
                padding: 1rem;
            }

            .admin-title {
                font-size: 2rem;
            }

            .admin-subtitle {
                font-size: 1rem;
            }

            .management-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }

            .management-card {
                padding: 1.5rem;
                min-height: auto;
            }

            .card-icon {
                width: 60px;
                height: 60px;
                font-size: 1.5rem;
            }

            .card-title {
                font-size: 1.25rem;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 480px) {
            .admin-title {
                font-size: 1.5rem;
            }

            .management-card {
                padding: 1rem;
            }

            .card-stats {
                flex-direction: column;
                gap: 0.5rem;
                align-items: stretch;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <header class="admin-header">
            <h1 class="admin-title">Report Dashboard</h1>
            <p class="admin-subtitle">Comprehensive Farm Reporting System</p>
            <p class="admin-description">Generate detailed reports and insights for all farm operations and activities</p>
        </header>

        <div class="quick-stats">
            <h2 class="stats-title">Reporting Overview</h2>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value">20</div>
                    <div class="stat-desc">Report Types</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">342</div>
                    <div class="stat-desc">Generated Today</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">1,847</div>
                    <div class="stat-desc">This Month</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">98%</div>
                    <div class="stat-desc">Data Accuracy</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">45</div>
                    <div class="stat-desc">Scheduled</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">5</div>
                    <div class="stat-desc">Pending</div>
                </div>
            </div>
        </div>

        <div class="management-grid">
            <a href="animal_report.php" class="management-card">
                <div class="card-icon animal">🐮</div>
                <h3 class="card-title">Animal Report</h3>
                <p class="card-description">Comprehensive livestock reports including population statistics, health records, and individual animal tracking data.</p>
                <div class="card-stats">
                    <div class="stat-item">
                        <div class="stat-number">1,247</div>
                        <div class="stat-label">Records</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number">15</div>
                        <div class="stat-label">Species</div>
                    </div>
                    <div class="card-action">
                        Generate →
                    </div>
                </div>
            </a>

            <a href="active_users_report.php" class="management-card">
                <div class="card-icon users">👥</div>
                <h3 class="card-title">Active Users Report</h3>
                <p class="card-description">Monitor user activity, access logs, and system usage patterns across all farm management personnel.</p>
                <div class="card-stats">
                    <div class="stat-item">
                        <div class="stat-number">42</div>
                        <div class="stat-label">Active</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number">8</div>
                        <div class="stat-label">Roles</div>
                    </div>
                    <div class="card-action">
                        View →
                    </div>
                </div>
            </a>

            <a href="medicine_report.php" class="management-card">
                <div class="card-icon medicine">💊</div>
                <h3 class="card-title">Medicine Report</h3>
                <p class="card-description">Track medicine inventory levels, usage rates, expiration dates, and procurement history.</p>
                <div class="card-stats">
                    <div class="stat-item">
                        <div class="stat-number">340</div>
                        <div class="stat-label">Items</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number">15</div>
                        <div class="stat-label">Low Stock</div>
                    </div>
                    <div class="card-action">
                        Analyze →
                    </div>
                </div>
            </a>

            <a href="feeds_report.php" class="management-card">
                <div class="card-icon feeds">🌾</div>
                <h3 class="card-title">Feeds & Feeding Supplies Report</h3>
                <p class="card-description">Monitor feed inventory, consumption patterns, supplier information, and nutritional data for livestock.</p>
                <div class="card-stats">
                    <div class="stat-item">
                        <div class="stat-number">2,450</div>
                        <div class="stat-label">Kg Stock</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number">28</div>
                        <div class="stat-label">Types</div>
                    </div>
                    <div class="card-action">
                        Review →
                    </div>
                </div>
            </a>

            <a href="housing_report.php" class="management-card">
                <div class="card-icon housing">🏠</div>
                <h3 class="card-title">Housing & Facilities Report</h3>
                <p class="card-description">Overview of buildings, pens, enclosures, capacity utilization, and infrastructure maintenance status.</p>
                <div class="card-stats">
                    <div class="stat-item">
                        <div class="stat-number">89</div>
                        <div class="stat-label">Buildings</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number">156</div>
                        <div class="stat-label">Pens</div>
                    </div>
                    <div class="card-action">
                        Inspect →
                    </div>
                </div>
            </a>

            <a href="equipment_report.php" class="management-card">
                <div class="card-icon equipment">🔧</div>
                <h3 class="card-title">Farm Equipment & Tools Report</h3>
                <p class="card-description">Track equipment inventory, usage logs, maintenance schedules, and operational efficiency metrics.</p>
                <div class="card-stats">
                    <div class="stat-item">
                        <div class="stat-number">234</div>
                        <div class="stat-label">Equipment</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number">12</div>
                        <div class="stat-label">Maintenance</div>
                    </div>
                    <div class="card-action">
                        Check →
                    </div>
                </div>
            </a>

            <a href="sanitation_report.php" class="management-card">
                <div class="card-icon sanitation">♻️</div>
                <h3 class="card-title">Sanitation & Waste Management Report</h3>
                <p class="card-description">Monitor cleaning schedules, waste disposal records, biosecurity measures, and hygiene compliance data.</p>
                <div class="card-stats">
                    <div class="stat-item">
                        <div class="stat-number">145</div>
                        <div class="stat-label">Tasks</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number">98%</div>
                        <div class="stat-label">Completed</div>
                    </div>
                    <div class="card-action">
                        Monitor →
                    </div>
                </div>
            </a>

            <a href="breeding_report.php" class="management-card">
                <div class="card-icon breeding">🧬</div>
                <h3 class="card-title">Breeding & Reproduction Report</h3>
                <p class="card-description">Track breeding programs, reproductive cycles, genetic lineages, and offspring performance metrics.</p>
                <div class="card-stats">
                    <div class="stat-item">
                        <div class="stat-number">67</div>
                        <div class="stat-label">Breeding</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number">23</div>
                        <div class="stat-label">Expected</div>
                    </div>
                    <div class="card-action">
                        Track →
                    </div>
                </div>
            </a>

            <a href="admin_records_report.php" class="management-card">
                <div class="card-icon admin">📋</div>
                <h3 class="card-title">Administration & Records Report</h3>
                <p class="card-description">Comprehensive administrative documentation, regulatory compliance records, and official certifications.</p>
                <div class="card-stats">
                    <div class="stat-item">
                        <div class="stat-number">567</div>
                        <div class="stat-label">Documents</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number">100%</div>
                        <div class="stat-label">Compliant</div>
                    </div>
                    <div class="card-action">
                        Access →
                    </div>
                </div>
            </a>

            <a href="maintenance_report.php" class="management-card">
                <div class="card-icon maintenance">🔩</div>
                <h3 class="card-title">Maintenance & Parts Report</h3>
                <p class="card-description">Monitor maintenance activities, spare parts inventory, repair histories, and preventive care schedules.</p>
                <div class="card-stats">
                    <div class="stat-item">
                        <div class="stat-number">89</div>
                        <div class="stat-label">Tasks</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number">456</div>
                        <div class="stat-label">Parts</div>
                    </div>
                    <div class="card-action">
                        Review →
                    </div>
                </div>
            </a>

            <a href="utilities_report.php" class="management-card">
                <div class="card-icon utilities">⚡</div>
                <h3 class="card-title">Utilities & Consumables Report</h3>
                <p class="card-description">Track utility usage, consumable supplies, energy consumption, and resource efficiency metrics.</p>
                <div class="card-stats">
                    <div class="stat-item">
                        <div class="stat-number">12.5k</div>
                        <div class="stat-label">kWh</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number">₱45k</div>
                        <div class="stat-label">Cost</div>
                    </div>
                    <div class="card-action">
                        Analyze →
                    </div>
                </div>
            </a>

            <a href="vitamins_report.php" class="management-card">
                <div class="card-icon vitamins">💚</div>
                <h3 class="card-title">Vitamins & Supplements Report</h3>
                <p class="card-description">Monitor vitamin inventory, supplement distribution, dosage schedules, and nutritional support programs.</p>
                <div class="card-stats">
                    <div class="stat-item">
                        <div class="stat-number">178</div>
                        <div class="stat-label">Items</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number">8</div>
                        <div class="stat-label">Categories</div>
                    </div>
                    <div class="card-action">
                        Report →
                    </div>
                </div>
            </a>

            <a href="vaccine_report.php" class="management-card">
                <div class="card-icon vaccine">💉</div>
                <h3 class="card-title">Vaccine Report</h3>
                <p class="card-description">Track vaccination schedules, immunization records, vaccine inventory, and herd immunity coverage.</p>
                <div class="card-stats">
                    <div class="stat-item">
                        <div class="stat-number">234</div>
                        <div class="stat-label">Vaccinated</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number">12</div>
                        <div class="stat-label">Types</div>
                    </div>
                    <div class="card-action">
                        View →
                    </div>
                </div>
            </a>

            <a href="others_report.php" class="management-card">
                <div class="card-icon others">📊</div>
                <h3 class="card-title">Others Report</h3>
                <p class="card-description">Miscellaneous reports including custom queries, special requests, and ad-hoc analytical reports.</p>
                <div class="card-stats">
                    <div class="stat-item">
                        <div class="stat-number">45</div>
                        <div class="stat-label">Custom</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number">12</div>
                        <div class="stat-label">Pending</div>
                    </div>
                    <div class="card-action">
                        Create →
                    </div>
                </div>
            </a>

            <a href="audit_log_report.php" class="management-card">
                <div class="card-icon audit">📜</div>
                <h3 class="card-title">Audit Log Report</h3>
                <p class="card-description">Comprehensive system audit trails, user activity logs, and security compliance monitoring reports.</p>
                <div class="card-stats">
                    <div class="stat-item">
                        <div class="stat-number">2,345</div>
                        <div class="stat-label">Entries</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number">Today</div>
                        <div class="stat-label">Updated</div>
                    </div>
                    <div class="card-action">
                        Audit →
                    </div>
                </div>
            </a>

            <a href="medication_report.php" class="management-card">
                <div class="card-icon medication">💊</div>
                <h3 class="card-title">Medication Report</h3>
                <p class="card-description">Detailed medication administration records, treatment histories, and therapeutic protocol compliance data.</p>
                <div class="card-stats">
                    <div class="stat-item">
                        <div class="stat-number">456</div>
                        <div class="stat-label">Treatments</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number">34</div>
                        <div class="stat-label">Active</div>
                    </div>
                    <div class="card-action">
                        Review →
                    </div>
                </div>
            </a>

            <a href="vaccination_report.php" class="management-card">
                <div class="card-icon vaccination">🩺</div>
                <h3 class="card-title">Vaccination Report</h3>
                <p class="card-description">Track vaccination campaigns, immunization coverage rates, and preventive healthcare program effectiveness.</p>
                <div class="card-stats">
                    <div class="stat-item">
                        <div class="stat-number">89%</div>
                        <div class="stat-label">Coverage</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number">156</div>
                        <div class="stat-label">Scheduled</div>
                    </div>
                    <div class="card-action">
                        Monitor →
                    </div>
                </div>
            </a>

            <a href="vitamins_transaction_report.php" class="management-card">
                <div class="card-icon vitamins-trans">💚</div>
                <h3 class="card-title">Vitamins & Supplements Transaction Report</h3>
                <p class="card-description">Detailed transaction logs for vitamin and supplement distribution, usage patterns, and cost analysis.</p>
                <div class="card-stats">
                    <div class="stat-item">
                        <div class="stat-number">567</div>
                        <div class="stat-label">Transactions</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number">₱23k</div>
                        <div class="stat-label">Value</div>
                    </div>
                    <div class="card-action">
                        Track →
                    </div>
                </div>
            </a>

            <a href="feeding_transaction_report.php" class="management-card">
                <div class="card-icon feeding-trans">🌾</div>
                <h3 class="card-title">Feeding Transaction Report</h3>
                <p class="card-description">Monitor feeding schedules, feed distribution records, consumption rates, and feeding cost analysis.</p>
                <div class="card-stats">
                    <div class="stat-item">
                        <div class="stat-number">1,234</div>
                        <div class="stat-label">Transactions</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number">₱145k</div>
                        <div class="stat-label">Cost</div>
                    </div>
                    <div class="card-action">
                        Analyze →
                    </div>
                </div>
            </a>

            <a href="animal_sales_reports.php" class="management-card">
                <div class="card-icon financial">💰</div>
                <h3 class="card-title">Animal Sales Reports</h3>
                <p class="card-description">Track animal sales, revenue streams, buyer demographics, and sales performance metrics.</p>
                <div class="card-stats">
                    <div class="stat-item">
                        <div class="stat-number">₱2.4M</div>
                        <div class="stat-label">Revenue</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number">15%</div>
                        <div class="stat-label">Growth</div>
                    </div>
                    <div class="card-action">
                        Financial →
                    </div>
                </div>
            </a>
        </div>
    </div>
</body>
</html>