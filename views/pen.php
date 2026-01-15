<?php
// views/pen.php
error_reporting(0);
ini_set('display_errors', 0);

$page="admin_dashboard";
include '../common/navbar.php';
include '../config/Connection.php';
// include '../config/Queries.php'; // Not needed for direct PDO
include '../security/checkRole.php';    
checkRole(3);

// Check for status messages
$status = $_GET['status'] ?? '';
$msg = $_GET['msg'] ?? '';

try {
    if (!isset($conn)) {
        throw new Exception("Database connection failed.");
    }

    // 1. Fetch Pens with Building & Location Names
    $sql = "
        SELECT 
            p.PEN_ID, 
            p.PEN_NAME, 
            p.BUILDING_ID, 
            b.BUILDING_NAME,
            l.LOCATION_ID,
            l.LOCATION_NAME
        FROM PENS p
        LEFT JOIN BUILDINGS b ON p.BUILDING_ID = b.BUILDING_ID
        LEFT JOIN LOCATIONS l ON b.LOCATION_ID = l.LOCATION_ID
        ORDER BY p.PEN_ID ASC
    ";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $pen_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Fetch Locations for Dropdown
    $loc_sql = "SELECT LOCATION_ID, LOCATION_NAME FROM LOCATIONS ORDER BY LOCATION_NAME ASC";
    $stmt = $conn->prepare($loc_sql);
    $stmt->execute();
    $locations_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $pen_data = [];
    $locations_data = [];
    $status = 'error';
    $msg = "Database Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pen Management System</title>
    <style>
        /* Styles remain identical */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif; background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); min-height: 100vh; color: white; }
        .container { max-width: 1400px; margin: 0 auto; padding: 2rem; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; }
        .header-info h1 { font-size: 2.5rem; font-weight: bold; margin-bottom: 0.5rem; }
        .header-info p { color: #cbd5e1; }
        .add-btn { display: flex; align-items: center; gap: 0.5rem; background: linear-gradient(135deg, #10b981, #059669); color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 0.5rem; font-weight: 600; cursor: pointer; transition: all 0.2s; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); }
        .add-btn:hover { background: linear-gradient(135deg, #059669, #047857); transform: scale(1.05); box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2); }
        .search-container { position: relative; margin-bottom: 2rem; }
        .search-input { width: 100%; padding: 1rem 1rem 1rem 3rem; background: rgba(30, 41, 59, 0.5); border: 1px solid #475569; border-radius: 0.5rem; color: white; font-size: 1rem; backdrop-filter: blur(10px); }
        .search-input::placeholder { color: #94a3b8; }
        .search-input:focus { outline: none; border-color: #10b981; box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1); }
        .search-icon { position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: #94a3b8; width: 20px; height: 20px; }
        .table-container { background: rgba(30, 41, 59, 0.5); backdrop-filter: blur(10px); border-radius: 0.75rem; border: 1px solid #475569; overflow: hidden; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); }
        .table { width: 100%; border-collapse: collapse; }
        .table thead { background: linear-gradient(135deg, #475569, #334155); }
        .table th { padding: 1rem 1.5rem; text-align: left; font-size: 0.875rem; font-weight: 600; color: #e2e8f0; text-transform: uppercase; letter-spacing: 0.05em; }
        .table tbody tr { border-bottom: 1px solid #475569; transition: background-color 0.2s; }
        .table tbody tr:hover { background: rgba(55, 65, 81, 0.5); }
        .table td { padding: 1rem 1.5rem; vertical-align: middle; }
        .pen-info { display: flex; align-items: center; gap: 1rem; }
        .pen-details h3 { font-size: 1.125rem; font-weight: 600; margin-bottom: 0.25rem; }
        .pen-building-info { color: #cbd5e1; font-size: 0.875rem; }
        .actions { display: flex; align-items: center; justify-content: center; gap: 0.5rem; }
        .action-btn { padding: 0.5rem; border: none; border-radius: 0.5rem; cursor: pointer; transition: all 0.2s; background: transparent; }
        .action-btn.edit { color: #60a5fa; } .action-btn.edit:hover { color: #93c5fd; background: rgba(59, 130, 246, 0.2); }
        .action-btn.delete { color: #f87171; } .action-btn.delete:hover { color: #fca5a5; background: rgba(239, 68, 68, 0.2); }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.6); backdrop-filter: blur(4px); z-index: 1000; padding: 1rem; }
        .modal.show { display: flex; align-items: center; justify-content: center; }
        .modal-content { background: #1e293b; border-radius: 0.75rem; width: 100%; max-width: 28rem; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); }
        .modal-header { padding: 1.5rem; border-bottom: 1px solid #475569; }
        .modal-header h2 { font-size: 1.5rem; font-weight: bold; }
        .modal-body { padding: 1.5rem; }
        .form-group { display: flex; flex-direction: column; gap: 0.5rem; margin-bottom: 1rem; }
        .form-group label { color: #cbd5e1; font-size: 0.875rem; font-weight: 500; }
        .form-group input, .form-group select { padding: 0.75rem; background: #374151; border: 1px solid #4b5563; border-radius: 0.5rem; color: white; font-size: 1rem; }
        .form-group input:focus, .form-group select:focus { outline: none; border-color: #10b981; box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1); }
        .form-group select option { background: #374151; color: white; }
        .modal-footer { padding: 1.5rem; border-top: 1px solid #475569; display: flex; justify-content: flex-end; gap: 0.75rem; }
        .btn-cancel { padding: 0.5rem 1.5rem; background: transparent; border: none; color: #cbd5e1; cursor: pointer; transition: color 0.2s; }
        .btn-cancel:hover { color: white; }
        .btn-save { padding: 0.5rem 1.5rem; background: linear-gradient(135deg, #10b981, #059669); border: none; border-radius: 0.5rem; color: white; font-weight: 600; cursor: pointer; transition: all 0.2s; }
        .btn-save:hover { background: linear-gradient(135deg, #059669, #047857); }
        .empty-state { text-align: center; padding: 3rem 1rem; display: none; }
        .empty-state h3 { font-size: 1.125rem; color: #94a3b8; margin-bottom: 0.5rem; }
        .empty-state p { color: #64748b; font-size: 0.875rem; }
        .icon { width: 18px; height: 18px; }
        /* Alert Styles */
        .alert-box { padding: 1rem; border-radius: 0.5rem; margin-bottom: 1.5rem; text-align: center; font-weight: 500; }
        .alert-success { background: rgba(16, 185, 129, 0.2); border: 1px solid #10b981; color: #6ee7b7; }
        .alert-error { background: rgba(239, 68, 68, 0.2); border: 1px solid #ef4444; color: #fca5a5; }
    </style>
</head>
<body>
    <div class="container">
        <?php if (!empty($msg)): ?>
            <div class="alert-box alert-<?php echo htmlspecialchars($status); ?>">
                <?php echo htmlspecialchars(urldecode($msg)); ?>
            </div>
        <?php endif; ?>

        <div class="header">
            <div class="header-info">
                <h1>Pen Management</h1>
                <p>Manage Pens and their information</p>
            </div>
            <button class="add-btn" onclick="openAddModal()">
                <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                Add Pen
            </button>
        </div>

        

        <div class="search-container">
            <svg class="search-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m21 21-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
            </svg>
            <input type="text" class="search-input" placeholder="Search pens by name, building, or location" onkeyup="filterTable()">
        </div>

        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Pen ID</th>
                        <th>Pen Name</th>
                        <th>Location > Building</th>
                        <th style="text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody id="pen-table">
                    <?php foreach($pen_data as $data): ?>
                    <tr data-id="<?php echo $data['PEN_ID']; ?>" 
                        data-building="<?php echo $data['BUILDING_ID']; ?>"
                        data-location="<?php echo $data['LOCATION_ID']; ?>">
                        <td>
                            <?php echo $data['PEN_ID']; ?>
                        </td>
                        <td>
                            <div class="pen-info">
                                <div class="pen-details">
                                    <h3><?php echo htmlspecialchars($data['PEN_NAME']); ?></h3>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="pen-building-info">
                                <?php echo htmlspecialchars($data['LOCATION_NAME'] ?? 'N/A'); ?> > 
                                <?php echo htmlspecialchars($data['BUILDING_NAME'] ?? 'N/A'); ?>
                            </span>
                        </td>
                        <td>
                            <div class="actions">
                                <button class="action-btn edit" onclick="editPen(this)" title="Edit">
                                    <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                    </svg>
                                </button>
                                <button class="action-btn delete" onclick="deletePen(this)" title="Delete">
                                    <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                    </svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div id="empty-state" class="empty-state" style="<?php echo empty($pen_data) ? 'display:block' : 'display:none'; ?>">
                <h3>No pens found</h3>
                <p>Try adjusting your search terms</p>
            </div>
        </div>
    </div>

    <div id="addModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Add New Pen</h2>
            </div>
            <div class="modal-body">
                <form id="addPenForm" method="POST" action="../process/addPen.php">
                    <div class="form-group">
                        <label for="add_pen_name">Pen Name</label>
                        <input type="text" id="add_pen_name" name="pen_name" placeholder="example: Pen A1" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="add_location_id">Location</label>
                        <select id="add_location_id" onchange="loadBuildings(this.value, 'add_building_id')" required>
                            <option value="">Select a location</option>
                            <?php foreach($locations_data as $loc): ?>
                                <option value="<?php echo $loc['LOCATION_ID']; ?>">
                                    <?php echo htmlspecialchars($loc['LOCATION_NAME']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="add_building_id">Building</label>
                        <select name="building_id" id="add_building_id" required disabled>
                            <option value="">Select location first</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeAddModal()">Cancel</button>
                <button type="button" class="btn-save" onclick="submitAddForm()">Add Pen</button>
            </div>
        </div>
    </div>

    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit Pen</h2>
            </div>
            <div class="modal-body">
                <form id="editPenForm" method="POST" action="../process/updatePen.php">
                    <input type="hidden" id="edit_pen_id" name="pen_id">
                    <div class="form-group">
                        <label for="edit_pen_name">Pen Name</label>
                        <input type="text" id="edit_pen_name" name="pen_name" placeholder="example: Pen A1" required>
                    </div>

                    <div class="form-group">
                        <label for="edit_location_id">Location</label>
                        <select id="edit_location_id" onchange="loadBuildings(this.value, 'edit_building_id')" required>
                            <option value="">Select a location</option>
                            <?php foreach($locations_data as $loc): ?>
                                <option value="<?php echo $loc['LOCATION_ID']; ?>">
                                    <?php echo htmlspecialchars($loc['LOCATION_NAME']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="edit_building_id">Building</label>
                        <select name="building_id" id="edit_building_id" required>
                            <option value="">Select a building</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeEditModal()">Cancel</button>
                <button type="button" class="btn-save" onclick="submitEditForm()">Update Pen</button>
            </div>
        </div>
    </div>

    <form id="deletePenForm" method="POST" action="../process/deletePen.php" style="display: none;">
        <input type="hidden" id="delete_pen_id" name="pen_id">
    </form>

    <script>
        // --- AJAX Function to Load Buildings ---
        function loadBuildings(locationId, targetSelectId) {
            const targetSelect = document.getElementById(targetSelectId);
            
            // Reset building dropdown
            targetSelect.innerHTML = '<option value="">Loading...</option>';
            targetSelect.disabled = true;

            if (!locationId) {
                targetSelect.innerHTML = '<option value="">Select location first</option>';
                return;
            }

            // Fetch buildings for the selected location
            fetch(`../process/getBuildingsByLocation.php?location_id=${locationId}`)
                .then(response => response.json())
                .then(data => {
                    targetSelect.innerHTML = '<option value="">Select a building</option>';
                    
                    if (data.buildings && data.buildings.length > 0) {
                        data.buildings.forEach(building => {
                            const option = document.createElement('option');
                            option.value = building.BUILDING_ID;
                            option.textContent = building.BUILDING_NAME;
                            targetSelect.appendChild(option);
                        });
                        targetSelect.disabled = false;
                    } else {
                        targetSelect.innerHTML = '<option value="">No buildings found</option>';
                    }
                })
                .catch(error => {
                    console.error('Error fetching buildings:', error);
                    targetSelect.innerHTML = '<option value="">Error loading buildings</option>';
                });
        }

        // Open add modal
        function openAddModal() {
            document.getElementById('addPenForm').reset();
            // Reset building dropdown state
            const bldgSelect = document.getElementById('add_building_id');
            bldgSelect.innerHTML = '<option value="">Select location first</option>';
            bldgSelect.disabled = true;
            
            document.getElementById('addModal').classList.add('show');
        }

        // Close add modal
        function closeAddModal() {
            document.getElementById('addModal').classList.remove('show');
        }

        // Submit add form
        function submitAddForm() {
            const form = document.getElementById('addPenForm');
            const name = document.getElementById('add_pen_name').value.trim();
            const location = document.getElementById('add_location_id').value;
            const building = document.getElementById('add_building_id').value;

            if (!name || !location || !building) {
                alert('Please fill in all fields');
                return;
            }

            if (confirm('Do you want to add this pen?')) {
                form.submit();
            }
        }

        // Open edit modal
        function editPen(button) {
            const row = button.closest('tr');
            const penId = row.getAttribute('data-id');
            const buildingId = row.getAttribute('data-building');
            const locationId = row.getAttribute('data-location'); // We need this data attribute
            const name = row.querySelector('.pen-details h3').textContent.trim();
            
            document.getElementById('edit_pen_id').value = penId;
            document.getElementById('edit_pen_name').value = name;
            
            // Set Location first
            const locationSelect = document.getElementById('edit_location_id');
            locationSelect.value = locationId;

            // Load Buildings for that location, then set the specific building
            // We pass a callback or handle the promise logic manually here for the edit case
            const targetSelect = document.getElementById('edit_building_id');
            targetSelect.innerHTML = '<option value="">Loading...</option>';
            targetSelect.disabled = true;

            fetch(`../process/getBuildingsByLocation.php?location_id=${locationId}`)
                .then(response => response.json())
                .then(data => {
                    targetSelect.innerHTML = '<option value="">Select a building</option>';
                    if (data.buildings && data.buildings.length > 0) {
                        data.buildings.forEach(building => {
                            const option = document.createElement('option');
                            option.value = building.BUILDING_ID;
                            option.textContent = building.BUILDING_NAME;
                            targetSelect.appendChild(option);
                        });
                        targetSelect.disabled = false;
                        // Determine selected value AFTER loading options
                        targetSelect.value = buildingId;
                    }
                });

            document.getElementById('editModal').classList.add('show');
        }

        // Close edit modal
        function closeEditModal() {
            document.getElementById('editModal').classList.remove('show');
        }

        // Submit edit form
        function submitEditForm() {
            const form = document.getElementById('editPenForm');
            const name = document.getElementById('edit_pen_name').value.trim();
            const location = document.getElementById('edit_location_id').value;
            const building = document.getElementById('edit_building_id').value;

            if (!name || !location || !building) {
                alert('Please fill in all fields');
                return;
            }

            if (confirm('Do you want to update this pen?')) {
                form.submit();
            }
        }

        // Delete pen
        function deletePen(button) {
            const row = button.closest('tr');
            const penId = row.getAttribute('data-id');
            
            if (confirm('Are you sure you want to delete this pen?')) {
                document.getElementById('delete_pen_id').value = penId;
                document.getElementById('deletePenForm').submit();
            }
        }

        // Filter table
        function filterTable() {
            const searchTerm = document.querySelector('.search-input').value.toLowerCase();
            const rows = document.querySelectorAll('#pen-table tr');
            let visibleCount = 0;

            rows.forEach(row => {
                const name = row.querySelector('.pen-details h3').textContent.toLowerCase();
                const buildingInfo = row.querySelector('.pen-building-info').textContent.toLowerCase();
                const penId = row.getAttribute('data-id').toLowerCase();
                
                if (name.includes(searchTerm) || buildingInfo.includes(searchTerm) || penId.includes(searchTerm)) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });

            checkEmptyState(visibleCount);
        }

        // Check empty state
        function checkEmptyState(visibleCount) {
            const tbody = document.getElementById('pen-table');
            const emptyState = document.getElementById('empty-state');
            const totalRows = tbody.querySelectorAll('tr').length;
            const actualVisibleCount = visibleCount !== undefined ? visibleCount : tbody.querySelectorAll('tr:not([style*="display: none"])').length;

            if (totalRows === 0 || actualVisibleCount === 0) {
                emptyState.style.display = 'block';
            } else {
                emptyState.style.display = 'none';
            }
        }

        // Close modals when clicking outside
        document.getElementById('addModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeAddModal();
            }
        });

        document.getElementById('editModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeEditModal();
            }
        });

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            checkEmptyState();
            
            // Auto-hide alert messages after 5 seconds
            const alerts = document.querySelectorAll('.alert-box');
            if (alerts.length > 0) {
                setTimeout(() => {
                    alerts.forEach(el => el.style.display = 'none');
                }, 5000);
            }
        });
    </script>
</body>
</html>