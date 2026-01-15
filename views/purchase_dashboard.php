<?php

$page = "transactions";
include '../common/navbar.php';
include '../security/checkRole.php';    
checkRole(3);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchased Items - FarmPro</title>
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
            display: block;
        }

        .category-card:hover {
            transform: translateY(-8px) scale(1.02);
            border-color: rgba(34, 197, 94, 0.4);
            box-shadow: 0 20px 40px rgba(34, 197, 94, 0.15);
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
        
        /* --- ADDED: Style for Animals Icon --- */
        .category-icon.animals { background: linear-gradient(135deg, #6366f1, #4338ca); } /* Indigo/Blue-Purple */

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

        .category-items {
            margin-bottom: 1rem;
        }

        .item-list {
            list-style: none;
            padding-left: 0;
        }

        .item-list li {
            color: #94a3b8;
            font-size: 0.9rem;
            padding: 0.4rem 0;
            padding-left: 1.5rem;
            position: relative;
        }

        .item-list li:before {
            content: "•";
            color: #22c55e;
            font-weight: bold;
            position: absolute;
            left: 0;
        }

        .category-stats {
            display: flex;
            justify-content: space-between;
            align-items: center;
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

        .category-card:hover .card-action {
            color: #22c55e;
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
            <h1 class="page-title">Purchased Items</h1>
            <p class="page-subtitle">Farm Inventory & Supplies Management</p>
            <p class="page-description">Browse and manage all farm purchases by category</p>
        </header>

        <div class="search-filter-section">
            <div class="search-bar">
                <input type="text" id="searchInput" class="search-input" placeholder="Search categories, items, or keywords...">
                <button class="btn-primary" onclick="searchCategories()">🔍 Search</button>
            </div>
        </div>

        <div class="categories-grid">
            <a href="purch_animals.php" class="category-card">
                <div class="category-header">
                    <div class="category-icon animals">🐖</div>
                    <div class="category-info">
                        <h3 class="category-title">Animals / Livestock</h3>
                        <p class="category-subtitle">Live stock and breeders</p>
                    </div>
                </div>
                <div class="category-items">
                    <ul class="item-list">
                        <li>Piglets / Weaners</li>
                        <li>Gilts & Boars</li>
                        <li>Chicks / Broilers</li>
                        <li>Layers</li>
                    </ul>
                </div>
                <div class="category-stats">
                    <div class="stat-item">
                        <div class="stat-number">45</div>
                        <div class="stat-label">Heads</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number">₱185,000</div>
                        <div class="stat-label">Total Value</div>
                    </div>
                    <div class="card-action">View Details →</div>
                </div>
            </a>

            <a href="purch_medicines.php" class="category-card">
                <div class="category-header">
                    <div class="category-icon medicine">🩺</div>
                    <div class="category-info">
                        <h3 class="category-title">Medicines</h3>
                        <p class="category-subtitle">Disease Treatments</p>
                    </div>
                </div>
                <div class="category-items">
                    <ul class="item-list">
                        <li>Antibiotics</li>
                        <li>Antiparasitics</li>
                        <li>Anti-inflammatories</li>
                        <li>Pain Relievers</li>
                    </ul>
                </div>
                <div class="category-stats">
                    <div class="stat-item">
                        <div class="stat-number">124</div>
                        <div class="stat-label">Items</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number">₱45,200</div>
                        <div class="stat-label">Total Value</div>
                    </div>
                    <div class="card-action">View Details →</div>
                </div>
            </a>

            <a href="purch_vitamins_supplements.php" class="category-card">
                <div class="category-header">
                    <div class="category-icon vitamins">💊</div>
                    <div class="category-info">
                        <h3 class="category-title">Vitamins & Supplements</h3>
                        <p class="category-subtitle">Nutritional additives and health boosters</p>
                    </div>
                </div>
                <div class="category-items">
                    <ul class="item-list">
                        <li>Multivitamins (A, D, E, K)</li>
                        <li>B-Complex</li>
                        <li>Probiotics & Prebiotics</li>
                        <li>Mineral supplements</li>
                    </ul>
                </div>
                <div class="category-stats">
                    <div class="stat-item">
                        <div class="stat-number">35</div>
                        <div class="stat-label">Items</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number">₱18,700</div>
                        <div class="stat-label">Total Value</div>
                    </div>
                    <div class="card-action">View Details →</div>
                </div>
            </a>

            <a href="purch_vaccines.php" class="category-card">
                <div class="category-header">
                    <div class="category-icon vaccines">💉</div>
                    <div class="category-info">
                        <h3 class="category-title">Vaccines</h3>
                        <p class="category-subtitle">Preventive health and biosecurity</p>
                    </div>
                </div>
                <div class="category-items">
                    <ul class="item-list">
                        <li>Swine Fever Vaccine</li>
                        <li>FMD (Foot-and-Mouth)</li>
                        <li>Avian Influenza Vaccine</li>
                        <li>Dewormers</li>
                    </ul>
                </div>
                <div class="category-stats">
                    <div class="stat-item">
                        <div class="stat-number">22</div>
                        <div class="stat-label">Items</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number">₱75,000</div>
                        <div class="stat-label">Total Value</div>
                    </div>
                    <div class="card-action">View Details →</div>
                </div>
            </a>

            <a href="purch_feeds_feeding.php" class="category-card">
                <div class="category-header">
                    <div class="category-icon feed">🍽️</div>
                    <div class="category-info">
                        <h3 class="category-title">Feeds & Feeding Supplies</h3>
                        <p class="category-subtitle">Food and feeding tools for animals</p>
                    </div>
                </div>
                <div class="category-items">
                    <ul class="item-list">
                        <li>Starter, Grower, Finisher</li>
                        <li>Feed additives</li>
                        <li>Feeders / Waterers</li>
                        <li>Feed storage containers</li>
                    </ul>
                </div>
                <div class="category-stats">
                    <div class="stat-item">
                        <div class="stat-number">89</div>
                        <div class="stat-label">Items</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number">₱128,500</div>
                        <div class="stat-label">Total Value</div>
                    </div>
                    <div class="card-action">View Details →</div>
                </div>
            </a>

            <a href="purch_housing_facilities.php" class="category-card">
                <div class="category-header">
                    <div class="category-icon housing">🏠</div>
                    <div class="category-info">
                        <h3 class="category-title">Housing & Facilities</h3>
                        <p class="category-subtitle">Animal shelter and comfort</p>
                    </div>
                </div>
                <div class="category-items">
                    <ul class="item-list">
                        <li>Pens / Pig Houses</li>
                        <li>Chicken Coops</li>
                        <li>Brooder boxes</li>
                        <li>Ventilation fans</li>
                    </ul>
                </div>
                <div class="category-stats">
                    <div class="stat-item">
                        <div class="stat-number">56</div>
                        <div class="stat-label">Items</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number">₱234,800</div>
                        <div class="stat-label">Total Value</div>
                    </div>
                    <div class="card-action">View Details →</div>
                </div>
            </a>

            <a href="purch_farm_equipment_tools.php" class="category-card">
                <div class="category-header">
                    <div class="category-icon equipment">⚙️</div>
                    <div class="category-info">
                        <h3 class="category-title">Farm Equipment & Tools</h3>
                        <p class="category-subtitle">General tools and machinery</p>
                    </div>
                </div>
                <div class="category-items">
                    <ul class="item-list">
                        <li>Cleaning equipment</li>
                        <li>Feed mixers / grinders</li>
                        <li>Water pumps</li>
                        <li>Power generators</li>
                    </ul>
                </div>
                <div class="category-stats">
                    <div class="stat-item">
                        <div class="stat-number">73</div>
                        <div class="stat-label">Items</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number">₱156,300</div>
                        <div class="stat-label">Total Value</div>
                    </div>
                    <div class="card-action">View Details →</div>
                </div>
            </a>

            <a href="purch_sanitation_waste_m.php" class="category-card">
                <div class="category-header">
                    <div class="category-icon sanitation">🧹</div>
                    <div class="category-info">
                        <h3 class="category-title">Sanitation & Waste</h3>
                        <p class="category-subtitle">Hygiene and biosecurity</p>
                    </div>
                </div>
                <div class="category-items">
                    <ul class="item-list">
                        <li>Waste bins</li>
                        <li>Manure scrapers</li>
                        <li>Sanitizing agents</li>
                        <li>Incinerators</li>
                    </ul>
                </div>
                <div class="category-stats">
                    <div class="stat-item">
                        <div class="stat-number">42</div>
                        <div class="stat-label">Items</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number">₱38,900</div>
                        <div class="stat-label">Total Value</div>
                    </div>
                    <div class="card-action">View Details →</div>
                </div>
            </a>

            <a href="purch_breeding_reproduction.php" class="category-card">
                <div class="category-header">
                    <div class="category-icon breeding">👨‍🌾</div>
                    <div class="category-info">
                        <h3 class="category-title">Breeding & Reproduction</h3>
                        <p class="category-subtitle">Controlled breeding and care</p>
                    </div>
                </div>
                <div class="category-items">
                    <ul class="item-list">
                        <li>AI kits</li>
                        <li>Heat detectors</li>
                        <li>Record tags / ID tags</li>
                        <li>Farrowing crates</li>
                    </ul>
                </div>
                <div class="category-stats">
                    <div class="stat-item">
                        <div class="stat-number">28</div>
                        <div class="stat-label">Items</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number">₱67,400</div>
                        <div class="stat-label">Total Value</div>
                    </div>
                    <div class="card-action">View Details →</div>
                </div>
            </a>

            <a href="purch_admin_records.php" class="category-card">
                <div class="category-header">
                    <div class="category-icon admin">📊</div>
                    <div class="category-info">
                        <h3 class="category-title">Administration & Records</h3>
                        <p class="category-subtitle">Management and data tracking</p>
                    </div>
                </div>
                <div class="category-items">
                    <ul class="item-list">
                        <li>Record books / Tags</li>
                        <li>RFID scanners</li>
                        <li>Software licenses</li>
                        <li>Office supplies</li>
                    </ul>
                </div>
                <div class="category-stats">
                    <div class="stat-item">
                        <div class="stat-number">34</div>
                        <div class="stat-label">Items</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number">₱22,600</div>
                        <div class="stat-label">Total Value</div>
                    </div>
                    <div class="card-action">View Details →</div>
                </div>
            </a>

            <a href="purch_maintenance_parts.php" class="category-card">
                <div class="category-header">
                    <div class="category-icon maintenance">🧰</div>
                    <div class="category-info">
                        <h3 class="category-title">Maintenance Parts</h3>
                        <p class="category-subtitle">Farm infrastructure upkeep</p>
                    </div>
                </div>
                <div class="category-items">
                    <ul class="item-list">
                        <li>Spare motors / blades</li>
                        <li>Lubricants and oils</li>
                        <li>Repair tools and kits</li>
                    </ul>
                </div>
                <div class="category-stats">
                    <div class="stat-item">
                        <div class="stat-number">61</div>
                        <div class="stat-label">Items</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number">₱54,700</div>
                        <div class="stat-label">Total Value</div>
                    </div>
                    <div class="card-action">View Details →</div>
                </div>
            </a>

            <a href="purch_utilities_consumables.php" class="category-card">
                <div class="category-header">
                    <div class="category-icon utilities">💡</div>
                    <div class="category-info">
                        <h3 class="category-title">Utilities & Consumables</h3>
                        <p class="category-subtitle">Daily operational needs</p>
                    </div>
                </div>
                <div class="category-items">
                    <ul class="item-list">
                        <li>Fuel / Diesel</li>
                        <li>Electricity / Batteries</li>
                        <li>Water filters</li>
                    </ul>
                </div>
                <div class="category-stats">
                    <div class="stat-item">
                        <div class="stat-number">47</div>
                        <div class="stat-label">Items</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number">₱89,200</div>
                        <div class="stat-label">Total Value</div>
                    </div>
                    <div class="card-action">View Details →</div>
                </div>
            </a>

            <a href="purch_others.php" class="category-card">
                <div class="category-header">
                    <div class="category-icon others">📦</div>
                    <div class="category-info">
                        <h3 class="category-title">Others</h3>
                        <p class="category-subtitle">Miscellaneous items</p>
                    </div>
                </div>
                <div class="category-items">
                    <ul class="item-list">
                        <li>Uncategorized items</li>
                        <li>Special orders</li>
                        <li>Seasonal items</li>
                    </ul>
                </div>
                <div class="category-stats">
                    <div class="stat-item">
                        <div class="stat-number">19</div>
                        <div class="stat-label">Items</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number">₱15,800</div>
                        <div class="stat-label">Total Value</div>
                    </div>
                    <div class="card-action">View Details →</div>
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
                const items = Array.from(card.querySelectorAll('.item-list li'))
                    .map(li => li.textContent.toLowerCase())
                    .join(' ');

                // Combine all searchable content
                const searchableContent = `${title} ${subtitle} ${items}`;

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
                    <div>No categories found for "<strong>${term}</strong>"</div>
                    <div style="font-size: 0.9rem; margin-top: 0.5rem; color: #64748b;">Try searching for: medicines, feeds, equipment, housing, etc.</div>
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