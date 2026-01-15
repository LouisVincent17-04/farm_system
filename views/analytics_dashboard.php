<?php
error_reporting(0);
ini_set('display_errors', 0);
$page = "analytics";
include '../common/navbar.php';
include '../security/checkRole.php';    
checkRole(3);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics Dashboard - FarmPro</title>
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

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .page-header {
            text-align: center;
            margin-bottom: 3rem;
        }

        .page-title {
            font-size: 3rem;
            font-weight: bold;
            margin-bottom: 1rem;
            background: linear-gradient(135deg, #22c55e, #16a34a);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .page-subtitle {
            color: #94a3b8;
            font-size: 1.2rem;
            margin-bottom: 0.5rem;
        }

        .page-description {
            color: #64748b;
            font-size: 1rem;
        }

        .search-filter-section {
            background: rgba(30, 41, 59, 0.6);
            border: 1px solid rgba(34, 197, 94, 0.2);
            border-radius: 16px;
            padding: 2rem;
            backdrop-filter: blur(10px);
            margin-bottom: 2rem;
        }

        .search-bar {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .search-input {
            flex: 1;
            padding: 1rem;
            background: rgba(15, 23, 42, 0.8);
            border: 1px solid rgba(34, 197, 94, 0.3);
            border-radius: 12px;
            color: #e2e8f0;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .search-input:focus {
            outline: none;
            border-color: #22c55e;
            box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.1);
        }

        .btn-primary {
            padding: 1rem 2rem;
            background: linear-gradient(135deg, #22c55e, #16a34a);
            border: none;
            border-radius: 12px;
            color: white;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(34, 197, 94, 0.3);
        }

        .categories-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .category-card {
            background: rgba(30, 41, 59, 0.6);
            border: 1px solid rgba(34, 197, 94, 0.2);
            border-radius: 16px;
            padding: 2rem;
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
            display: flex;
            flex-direction: column;
            position: relative;
            overflow: hidden;
        }

        .category-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, transparent, #22c55e, transparent);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .category-card:hover {
            transform: translateY(-8px) scale(1.02);
            border-color: rgba(34, 197, 94, 0.4);
            box-shadow: 0 20px 40px rgba(34, 197, 94, 0.15);
        }

        .category-card:hover::before {
            opacity: 1;
        }

        .category-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .category-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: white;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.3);
        }

        .category-icon.medicine { background: linear-gradient(135deg, #ec4899, #db2777); }
        .category-icon.feed { background: linear-gradient(135deg, #f59e0b, #d97706); }
        .category-icon.housing { background: linear-gradient(135deg, #8b5cf6, #7c3aed); }
        .category-icon.equipment { background: linear-gradient(135deg, #06b6d4, #0891b2); }
        .category-icon.sanitation { background: linear-gradient(135deg, #10b981, #059669); }
        .category-icon.breeding { background: linear-gradient(135deg, #f97316, #ea580c); }
        .category-icon.admin { background: linear-gradient(135deg, #3b82f6, #1d4ed8); }
        .category-icon.maintenance { background: linear-gradient(135deg, #64748b, #475569); }
        .category-icon.utilities { background: linear-gradient(135deg, #eab308, #ca8a04); }
        .category-icon.others { background: linear-gradient(135deg, #a855f7, #9333ea); }
        .category-icon.vitamins { background: linear-gradient(135deg, #34d399, #059669); }
        .category-icon.vaccines { background: linear-gradient(135deg, #ef4444, #b91c1c); }
        .category-icon.animals { background: linear-gradient(135deg, #6366f1, #4338ca); }

        .category-info {
            flex: 1;
        }

        .category-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: #22c55e;
            margin-bottom: 0.5rem;
        }

        .category-subtitle {
            color: #64748b;
            font-size: 0.9rem;
        }

        .analytics-preview {
            margin-bottom: 1rem;
            padding: 1rem;
            background: rgba(15, 23, 42, 0.5);
            border-radius: 8px;
            border: 1px solid rgba(34, 197, 94, 0.1);
            flex-grow: 1;
        }

        .analytics-preview-title {
            color: #94a3b8;
            font-size: 0.85rem;
            margin-bottom: 0.75rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .metrics-list {
            list-style: none;
            padding-left: 0;
        }

        .metrics-list li {
            color: #cbd5e1;
            font-size: 0.9rem;
            padding: 0.4rem 0;
            padding-left: 1.5rem;
            position: relative;
        }

        .metrics-list li:before {
            content: "📊";
            position: absolute;
            left: 0;
            font-size: 0.8rem;
        }

        .card-action {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 1rem;
            background: rgba(34, 197, 94, 0.1);
            border-radius: 8px;
            color: #22c55e;
            font-size: 0.95rem;
            font-weight: 600;
            transition: all 0.3s ease;
            border: 1px solid rgba(34, 197, 94, 0.2);
            margin-top: auto;
        }

        .category-card:hover .card-action {
            background: rgba(34, 197, 94, 0.2);
            border-color: rgba(34, 197, 94, 0.4);
            transform: translateX(5px);
        }

        .analytics-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            background: rgba(34, 197, 94, 0.2);
            border: 1px solid rgba(34, 197, 94, 0.3);
            border-radius: 20px;
            color: #22c55e;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        @media (max-width: 768px) {
            body {
                padding: 1rem;
            }

            .page-title {
                font-size: 2rem;
            }

            .categories-grid {
                grid-template-columns: 1fr;
            }

            .search-bar {
                flex-direction: column;
            }
        }

        @media (max-width: 480px) {
            .page-title {
                font-size: 1.5rem;
            }

            .category-card {
                padding: 1.5rem;
            }

            .category-icon {
                width: 50px;
                height: 50px;
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header class="page-header">
            <div style="font-size: 4rem; margin-bottom: 1rem;">📈</div>
            <h1 class="page-title">Analytics Dashboard</h1>
            <p class="page-subtitle">Data-Driven Farm Management Insights</p>
            <p class="page-description">Select a category to view detailed analytics and performance metrics</p>
        </header>

        <div class="search-filter-section">
            <div class="search-bar">
                <input type="text" id="searchInput" class="search-input" placeholder="Search analytics categories...">
                <button class="btn-primary" onclick="searchCategories()">🔍 Search</button>
            </div>
        </div>

        <div class="categories-grid">
            <a href="analytics_animals.php" class="category-card">
                <div class="category-header">
                    <div class="category-icon animals">🐖</div>
                    <div class="category-info">
                        <h3 class="category-title">Animals / Livestock Analytics</h3>
                        <p class="category-subtitle">Growth & Performance Tracking</p>
                    </div>
                </div>
                <div class="analytics-preview">
                    <div class="analytics-preview-title">Available Metrics</div>
                    <ul class="metrics-list">
                        <li>Mortality & Survival Rates</li>
                        <li>Growth Performance Trends</li>
                        <li>Purchase Cost Analysis</li>
                        <li>Stock Movement History</li>
                    </ul>
                </div>
                <div class="card-action">
                    <span>View Analytics</span>
                    <span>→</span>
                </div>
            </a>

            <a href="analytics_medicines.php" class="category-card">
                <div class="category-header">
                    <div class="category-icon medicine">🩺</div>
                    <div class="category-info">
                        <h3 class="category-title">Medicines Analytics</h3>
                        <p class="category-subtitle">Treatment & Cost Insights</p>
                    </div>
                </div>
                <div class="analytics-preview">
                    <div class="analytics-preview-title">Available Metrics</div>
                    <ul class="metrics-list">
                        <li>Usage Patterns & Trends</li>
                        <li>Cost per Treatment Analysis</li>
                        <li>Inventory Turnover Rate</li>
                        <li>Expiration & Waste Tracking</li>
                    </ul>
                </div>
                <div class="card-action">
                    <span>View Analytics</span>
                    <span>→</span>
                </div>
            </a>

            <a href="analytics_vitamins_supplements.php" class="category-card">
                <div class="category-header">
                    <div class="category-icon vitamins">💊</div>
                    <div class="category-info">
                        <h3 class="category-title">Vitamins & Supplements Analytics</h3>
                        <p class="category-subtitle">Nutritional Investment Tracking</p>
                    </div>
                </div>
                <div class="analytics-preview">
                    <div class="analytics-preview-title">Available Metrics</div>
                    <ul class="metrics-list">
                        <li>Supplement Usage by Type</li>
                        <li>Cost vs Health Improvements</li>
                        <li>Seasonal Consumption Patterns</li>
                        <li>ROI on Health Boosters</li>
                    </ul>
                </div>
                <div class="card-action">
                    <span>View Analytics</span>
                    <span>→</span>
                </div>
            </a>

            <a href="analytics_vaccines.php" class="category-card">
                <div class="category-header">
                    <div class="category-icon vaccines">💉</div>
                    <div class="category-info">
                        <h3 class="category-title">Vaccines Analytics</h3>
                        <p class="category-subtitle">Preventive Care Monitoring</p>
                    </div>
                </div>
                <div class="analytics-preview">
                    <div class="analytics-preview-title">Available Metrics</div>
                    <ul class="metrics-list">
                        <li>Vaccination Coverage Rates</li>
                        <li>Disease Prevention Success</li>
                        <li>Program Cost Efficiency</li>
                        <li>Schedule Compliance Tracking</li>
                    </ul>
                </div>
                <div class="card-action">
                    <span>View Analytics</span>
                    <span>→</span>
                </div>
            </a>

            <a href="analytics_feeds_feeding.php" class="category-card">
                <div class="category-header">
                    <div class="category-icon feed">🍽️</div>
                    <div class="category-info">
                        <h3 class="category-title">Feeds & Feeding Analytics</h3>
                        <p class="category-subtitle">Nutrition & Efficiency Metrics</p>
                    </div>
                </div>
                <div class="analytics-preview">
                    <div class="analytics-preview-title">Available Metrics</div>
                    <ul class="metrics-list">
                        <li>Feed Conversion Ratio (FCR)</li>
                        <li>Cost per Kilogram Gained</li>
                        <li>Consumption Trends by Stage</li>
                        <li>Supplier Performance Analysis</li>
                    </ul>
                </div>
                <div class="card-action">
                    <span>View Analytics</span>
                    <span>→</span>
                </div>
            </a>

            <a href="analytics_housing_facilities.php" class="category-card">
                <div class="category-header">
                    <div class="category-icon housing">🏠</div>
                    <div class="category-info">
                        <h3 class="category-title">Housing & Facilities Analytics</h3>
                        <p class="category-subtitle">Infrastructure Investment Insights</p>
                    </div>
                </div>
                <div class="analytics-preview">
                    <div class="analytics-preview-title">Available Metrics</div>
                    <ul class="metrics-list">
                        <li>Capacity Utilization Rates</li>
                        <li>Depreciation & Maintenance Costs</li>
                        <li>Facility Expansion Timeline</li>
                        <li>Space Efficiency Analysis</li>
                    </ul>
                </div>
                <div class="card-action">
                    <span>View Analytics</span>
                    <span>→</span>
                </div>
            </a>

            <a href="analytics_farm_equipment_tools.php" class="category-card">
                <div class="category-header">
                    <div class="category-icon equipment">⚙️</div>
                    <div class="category-info">
                        <h3 class="category-title">Farm Equipment & Tools Analytics</h3>
                        <p class="category-subtitle">Asset Performance Tracking</p>
                    </div>
                </div>
                <div class="analytics-preview">
                    <div class="analytics-preview-title">Available Metrics</div>
                    <ul class="metrics-list">
                        <li>Equipment Utilization Rates</li>
                        <li>Breakdown & Downtime Analysis</li>
                        <li>Maintenance Cost Tracking</li>
                        <li>ROI on Equipment Purchases</li>
                    </ul>
                </div>
                <div class="card-action">
                    <span>View Analytics</span>
                    <span>→</span>
                </div>
            </a>

            <a href="analytics_sanitation_waste_m.php" class="category-card">
                <div class="category-header">
                    <div class="category-icon sanitation">🧹</div>
                    <div class="category-info">
                        <h3 class="category-title">Sanitation & Waste Analytics</h3>
                        <p class="category-subtitle">Hygiene Cost Monitoring</p>
                    </div>
                </div>
                <div class="analytics-preview">
                    <div class="analytics-preview-title">Available Metrics</div>
                    <ul class="metrics-list">
                        <li>Waste Management Costs</li>
                        <li>Biosecurity Compliance Rates</li>
                        <li>Cleaning Supply Usage</li>
                        <li>Disease Outbreak Correlation</li>
                    </ul>
                </div>
                <div class="card-action">
                    <span>View Analytics</span>
                    <span>→</span>
                </div>
            </a>

            <a href="analytics_breeding_reproduction.php" class="category-card">
                <div class="category-header">
                    <div class="category-icon breeding">👨‍🌾</div>
                    <div class="category-info">
                        <h3 class="category-title">Breeding & Reproduction Analytics</h3>
                        <p class="category-subtitle">Genetic Performance Insights</p>
                    </div>
                </div>
                <div class="analytics-preview">
                    <div class="analytics-preview-title">Available Metrics</div>
                    <ul class="metrics-list">
                        <li>Conception & Birth Rates</li>
                        <li>Litter Size & Quality Trends</li>
                        <li>AI Success Rates</li>
                        <li>Breeding Program ROI</li>
                    </ul>
                </div>
                <div class="card-action">
                    <span>View Analytics</span>
                    <span>→</span>
                </div>
            </a>

            <a href="analytics_admin_records.php" class="category-card">
                <div class="category-header">
                    <div class="category-icon admin">📊</div>
                    <div class="category-info">
                        <h3 class="category-title">Administration & Records Analytics</h3>
                        <p class="category-subtitle">Operational Efficiency Metrics</p>
                    </div>
                </div>
                <div class="analytics-preview">
                    <div class="analytics-preview-title">Available Metrics</div>
                    <ul class="metrics-list">
                        <li>Record-keeping Accuracy</li>
                        <li>Administrative Cost Analysis</li>
                        <li>Compliance Tracking</li>
                        <li>Data Entry Efficiency</li>
                    </ul>
                </div>
                <div class="card-action">
                    <span>View Analytics</span>
                    <span>→</span>
                </div>
            </a>

            <a href="analytics_maintenance_parts.php" class="category-card">
                <div class="category-header">
                    <div class="category-icon maintenance">🧰</div>
                    <div class="category-info">
                        <h3 class="category-title">Maintenance Parts Analytics</h3>
                        <p class="category-subtitle">Repair & Upkeep Insights</p>
                    </div>
                </div>
                <div class="analytics-preview">
                    <div class="analytics-preview-title">Available Metrics</div>
                    <ul class="metrics-list">
                        <li>Parts Replacement Frequency</li>
                        <li>Preventive vs Emergency Repairs</li>
                        <li>Maintenance Cost Trends</li>
                        <li>Inventory Stock Levels</li>
                    </ul>
                </div>
                <div class="card-action">
                    <span>View Analytics</span>
                    <span>→</span>
                </div>
            </a>

            <a href="analytics_utilities_consumables.php" class="category-card">
                <div class="category-header">
                    <div class="category-icon utilities">💡</div>
                    <div class="category-info">
                        <h3 class="category-title">Utilities & Consumables Analytics</h3>
                        <p class="category-subtitle">Operating Cost Tracking</p>
                    </div>
                </div>
                <div class="analytics-preview">
                    <div class="analytics-preview-title">Available Metrics</div>
                    <ul class="metrics-list">
                        <li>Energy Consumption Patterns</li>
                        <li>Fuel & Electricity Costs</li>
                        <li>Seasonal Usage Variations</li>
                        <li>Cost Optimization Opportunities</li>
                    </ul>
                </div>
                <div class="card-action">
                    <span>View Analytics</span>
                    <span>→</span>
                </div>
            </a>

            <a href="analytics_others.php" class="category-card">
                <div class="category-header">
                    <div class="category-icon others">📦</div>
                    <div class="category-info">
                        <h3 class="category-title">Others Analytics</h3>
                        <p class="category-subtitle">Miscellaneous Expense Insights</p>
                    </div>
                </div>
                <div class="analytics-preview">
                    <div class="analytics-preview-title">Available Metrics</div>
                    <ul class="metrics-list">
                        <li>Uncategorized Expenses</li>
                        <li>Special Order Tracking</li>
                        <li>Seasonal Item Analysis</li>
                        <li>Budget Variance Reports</li>
                    </ul>
                </div>
                <div class="card-action">
                    <span>View Analytics</span>
                    <span>→</span>
                </div>
            </a>
        </div>
    </div>

    <script>
        // Search functionality
        const searchInput = document.getElementById('searchInput');
        const categoryCards = document.querySelectorAll('.category-card');

        // Real-time search as user types
        searchInput.addEventListener('input', function() {
            searchCategories();
        });

        // Search on Enter key
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                searchCategories();
            }
        });

        function searchCategories() {
            const searchTerm = searchInput.value.toLowerCase().trim();
            let visibleCount = 0;

            categoryCards.forEach(card => {
                // Get all searchable text from the card
                const title = card.querySelector('.category-title').textContent.toLowerCase();
                const subtitle = card.querySelector('.category-subtitle').textContent.toLowerCase();
                const metrics = Array.from(card.querySelectorAll('.metrics-list li'))
                    .map(li => li.textContent.toLowerCase())
                    .join(' ');

                // Combine all searchable content
                const searchableContent = `${title} ${subtitle} ${metrics}`;

                // Check if search term matches
                if (searchTerm === '' || searchableContent.includes(searchTerm)) {
                    card.style.display = 'block';
                    visibleCount++;
                    // Add highlight effect
                    card.style.animation = 'fadeIn 0.3s ease';
                } else {
                    card.style.display = 'none';
                }
            });

            // Show message if no results
            showNoResultsMessage(visibleCount, searchTerm);
        }

        function showNoResultsMessage(count, term) {
            // Remove existing message if any
            const existingMessage = document.querySelector('.no-results-message');
            if (existingMessage) {
                existingMessage.remove();
            }

            if (count === 0 && term !== '') {
                const grid = document.querySelector('.categories-grid');
                const message = document.createElement('div');
                message.className = 'no-results-message';
                message.style.cssText = `
                    grid-column: 1 / -1;
                    text-align: center;
                    padding: 3rem;
                    color: #94a3b8;
                    font-size: 1.1rem;
                `;
                message.innerHTML = `
                    <div style="font-size: 3rem; margin-bottom: 1rem;">🔍</div>
                    <div>No analytics categories found for "<strong>${term}</strong>"</div>
                    <div style="font-size: 0.9rem; margin-top: 0.5rem; color: #64748b;">Try searching for: animals, feeds, medicines, housing, etc.</div>
                `;
                grid.appendChild(message);
            }
        }

        // Add fade-in animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes fadeIn {
                from {
                    opacity: 0;
                    transform: translateY(10px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>