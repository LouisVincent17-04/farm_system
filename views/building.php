<?php
// views/building.php
error_reporting(0);
ini_set('display_errors', 0);

$page="admin_dashboard";
include '../common/navbar.php';
include '../config/Connection.php'; 

// Check for status messages
$status = $_GET['status'] ?? '';
$msg = $_GET['msg'] ?? '';

try {
    if (!isset($conn)) {
        throw new Exception("Database connection failed.");
    }

    // 1. Get Building Data (Joined with Locations)
    $sql = "SELECT b.BUILDING_ID, b.BUILDING_NAME, b.LOCATION_ID, l.LOCATION_NAME 
            FROM BUILDINGS b
            LEFT JOIN LOCATIONS l ON b.LOCATION_ID = l.LOCATION_ID
            ORDER BY b.BUILDING_ID ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $building_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Get Location Data for Dropdowns
    $sql = "SELECT LOCATION_ID, LOCATION_NAME, COMPLETE_ADDRESS FROM LOCATIONS ORDER BY LOCATION_ID ASC";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $location_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $building_data = [];
    $location_data = [];
    $status = 'error';
    $msg = "Database Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> <title>Building Management System</title>
    <style>
        /* Base Styles */
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
        
        /* Table Styles */
        .table-container { background: rgba(30, 41, 59, 0.5); backdrop-filter: blur(10px); border-radius: 0.75rem; border: 1px solid #475569; overflow: hidden; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); }
        .table { width: 100%; border-collapse: collapse; }
        .table thead { background: linear-gradient(135deg, #475569, #334155); }
        .table th { padding: 1rem 1.5rem; text-align: left; font-size: 0.875rem; font-weight: 600; color: #e2e8f0; text-transform: uppercase; letter-spacing: 0.05em; }
        .table tbody tr { border-bottom: 1px solid #475569; transition: background-color 0.2s; }
        .table tbody tr:hover { background: rgba(55, 65, 81, 0.5); }
        .table td { padding: 1rem 1.5rem; vertical-align: middle; }
        
        .building-info { display: flex; align-items: center; gap: 1rem; }
        .building-details h3 { font-size: 1.125rem; font-weight: 600; margin-bottom: 0.25rem; }
        .location-name-display { color: #cbd5e1; font-size: 0.875rem; background: rgba(255,255,255,0.1); padding: 4px 8px; border-radius: 4px; }
        
        .actions { display: flex; align-items: center; justify-content: center; gap: 0.5rem; }
        .action-btn { padding: 0.5rem; border: none; border-radius: 0.5rem; cursor: pointer; transition: all 0.2s; background: transparent; }
        .action-btn.edit { color: #60a5fa; } .action-btn.edit:hover { color: #93c5fd; background: rgba(59, 130, 246, 0.2); }
        .action-btn.delete { color: #f87171; } .action-btn.delete:hover { color: #fca5a5; background: rgba(239, 68, 68, 0.2); }
        
        /* Modal Styles */
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
        
        /* Alerts */
        .alert-box { padding: 1rem; border-radius: 0.5rem; margin-bottom: 1.5rem; text-align: center; font-weight: 500; }
        .alert-success { background: rgba(16, 185, 129, 0.2); border: 1px solid #10b981; color: #6ee7b7; }
        .alert-error { background: rgba(239, 68, 68, 0.2); border: 1px solid #ef4444; color: #fca5a5; }

        /* --- MOBILE RESPONSIVE CSS --- */
        @media (max-width: 768px) {
            .container { padding: 1rem; }
            
            /* Header Stack */
            .header { flex-direction: column; align-items: stretch; gap: 1rem; text-align: center; }
            .header-info h1 { font-size: 1.75rem; }
            .add-btn { width: 100%; justify-content: center; }

            /* Card View Transformation */
            .table thead { display: none; } /* Hide Table Headers */
            .table, .table tbody, .table tr, .table td { display: block; width: 100%; box-sizing: border-box; }
            
            .table tbody tr {
                background: rgba(30, 41, 59, 0.6);
                border: 1px solid #475569;
                border-radius: 12px;
                margin-bottom: 1rem;
                padding: 1rem;
                box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            }

            .table td {
                padding: 0.5rem 0;
                text-align: right;
                display: flex;
                justify-content: space-between;
                align-items: center;
                border-bottom: 1px solid rgba(255,255,255,0.05);
            }

            .table td:last-child { border-bottom: none; justify-content: flex-end; padding-top: 1rem; gap: 10px; }

            /* Data Labels */
            .table td::before {
                content: attr(data-label);
                font-weight: 600;
                color: #94a3b8;
                font-size: 0.85rem;
                text-transform: uppercase;
                margin-right: 1rem;
            }

            /* Alignment Fixes */
            .building-info { justify-content: flex-end; }
            .actions { justify-content: flex-end; }

            /* Modals */
            .modal-content { width: 95%; max-height: 90vh; overflow-y: auto; }
        }
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
                <h1>Building Management</h1>
                <p>Manage Buildings and their information</p>
            </div>
            <button class="add-btn" onclick="openAddModal()">
                <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                Add Building
            </button>
        </div>

        <div class="search-container">
            <svg class="search-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m21 21-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
            </svg>
            <input type="text" class="search-input" placeholder="Search buildings by name or location" onkeyup="filterTable()">
        </div>

        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Building ID</th>
                        <th>Building Name</th>
                        <th>Location</th> 
                        <th style="text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody id="building-table">
                    <?php foreach($building_data as $data): ?>
                    <tr data-id="<?php echo $data['BUILDING_ID']; ?>" data-location-id="<?php echo $data['LOCATION_ID']; ?>">
                        
                        <td data-label="Building ID">
                            <span style="font-family: monospace; color: #94a3b8;">#<?php echo $data['BUILDING_ID']; ?></span>
                        </td>
                        
                        <td data-label="Building Name">
                            <div class="building-info">
                                <div class="building-details">
                                    <h3 class="building-name-display"><?php echo htmlspecialchars($data['BUILDING_NAME']); ?></h3>
                                </div>
                            </div>
                        </td>
                        
                        <td data-label="Location">
                            <span class="location-name-display">
                                <?php echo htmlspecialchars($data['LOCATION_NAME'] ?? 'N/A'); ?>
                            </span>
                        </td>
                        
                        <td data-label="Actions">
                            <div class="actions">
                                <button class="action-btn edit" onclick="editBuilding(this)" title="Edit">
                                    <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                    </svg>
                                </button>
                                <button class="action-btn delete" onclick="deleteBuilding(this)" title="Delete">
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
            <div id="empty-state" class="empty-state" style="<?php echo empty($building_data) ? 'display:block' : 'display:none'; ?>">
                <h3>No buildings found</h3>
                <p>Try adjusting your search terms or add a new building.</p>
            </div>
        </div>
    </div>

    <div id="addModal" class="modal">
        <div class="modal-content">
            <div class="modal-header"><h2>Add New Building</h2></div>
            <div class="modal-body">
                <form id="addBuildingForm" method="POST" action="../process/addBuilding.php">
                    <div class="form-group">
                        <label for="add_building_name">Building Name</label>
                        <input type="text" id="add_building_name" name="building_name" placeholder="Enter Building Name" required>
                    </div>
                    <div class="form-group">
                        <label for="add_building_location_select">Building Location</label>
                            <select name="location_id" id="add_building_location_select" onchange="updateAddressField('add')">
                                <?php foreach($location_data as $loc): ?>
                                    <option value="<?php echo $loc['LOCATION_ID']; ?>" data-address="<?php echo htmlspecialchars($loc['COMPLETE_ADDRESS']); ?>">
                                        <?php echo htmlspecialchars($loc['LOCATION_NAME']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <input type="text" id="add_location_complete_address" disabled style="opacity:70%; margin-top: 5px;" placeholder="Complete Address will appear here">
                        </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeAddModal()">Cancel</button>
                <button type="button" class="btn-save" onclick="submitAddForm()">Add Building</button>
            </div>
        </div>
    </div>

    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header"><h2>Edit Building</h2></div>
            <div class="modal-body">
                <form id="editBuildingForm" method="POST" action="../process/updateBuilding.php">
                    <input type="hidden" id="edit_building_id" name="building_id">
                    <div class="form-group">
                        <label for="edit_building_name">Building Name</label>
                        <input type="text" id="edit_building_name" name="building_name" placeholder="example: Building 1" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_building_location_select">Building Location</label>
                            <select name="location_id" id="edit_building_location_select" onchange="updateAddressField('edit')">
                                <?php foreach($location_data as $loc): ?>
                                    <option value="<?php echo $loc['LOCATION_ID']; ?>" data-address="<?php echo htmlspecialchars($loc['COMPLETE_ADDRESS']); ?>">
                                        <?php echo htmlspecialchars($loc['LOCATION_NAME']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <input type="text" id="edit_location_complete_address" disabled style="opacity:70%; margin-top: 5px;" placeholder="Complete Address will appear here">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeEditModal()">Cancel</button>
                <button type="button" class="btn-save" onclick="submitEditForm()">Update Building</button>
            </div>
        </div>
    </div>

    <form id="deleteBuildingForm" method="POST" action="../process/deleteBuilding.php" style="display: none;">
        <input type="hidden" id="delete_building_id" name="building_id">
    </form>

    <script>
        // --- MODAL CONTROL FUNCTIONS ---
        function openAddModal() {
            document.getElementById('addBuildingForm').reset();
            document.getElementById('addModal').classList.add('show');
            updateAddressField('add'); 
        }

        function closeAddModal() { document.getElementById('addModal').classList.remove('show'); }
        function closeEditModal() { document.getElementById('editModal').classList.remove('show'); }

        // --- SUBMIT FUNCTIONS ---
        function submitAddForm() {
            const form = document.getElementById('addBuildingForm');
            const name = document.getElementById('add_building_name').value.trim();
            if (!name) { alert('Please fill in the Building Name.'); return; }
            if (confirm('Do you want to add this building?')) { form.submit(); }
        }

        function submitEditForm() {
            const form = document.getElementById('editBuildingForm');
            const name = document.getElementById('edit_building_name').value.trim();
            if (!name) { alert('Please fill in the Building Name.'); return; }
            if (confirm('Do you want to update this building?')) { form.submit(); }
        }

        // --- CRUD ACTION FUNCTIONS ---
        function editBuilding(button) {
            const row = button.closest('tr');
            const buildingId = row.getAttribute('data-id');
            const locationId = row.getAttribute('data-location-id');
            const name = row.querySelector('.building-name-display').textContent.trim();
            
            document.getElementById('edit_building_id').value = buildingId;
            document.getElementById('edit_building_name').value = name;
            
            const locationSelect = document.getElementById('edit_building_location_select');
            locationSelect.value = locationId; 
            updateAddressField('edit');

            document.getElementById('editModal').classList.add('show');
        }

        function deleteBuilding(button) {
            const row = button.closest('tr');
            const buildingId = row.getAttribute('data-id');
            const buildingName = row.querySelector('.building-name-display').textContent.trim();
            
            if (confirm(`Are you sure you want to permanently delete the building: ${buildingName}?`)) {
                document.getElementById('delete_building_id').value = buildingId;
                document.getElementById('deleteBuildingForm').submit();
            }
        }

        // --- HELPER FUNCTIONS ---
        function updateAddressField(mode) {
            const selectId = mode === 'add' ? 'add_building_location_select' : 'edit_building_location_select';
            const addressInputId = mode === 'add' ? 'add_location_complete_address' : 'edit_location_complete_address';

            const select = document.getElementById(selectId);
            const addressInput = document.getElementById(addressInputId);
            
            if (select && addressInput && select.selectedIndex !== -1) {
                const selectedOption = select.options[select.selectedIndex];
                const address = selectedOption.getAttribute('data-address') || '';
                addressInput.value = address;
            }
        }

        function filterTable() {
            const searchTerm = document.querySelector('.search-input').value.toLowerCase();
            const rows = document.querySelectorAll('#building-table tr');
            let visibleCount = 0;

            rows.forEach(row => {
                const name = row.querySelector('.building-name-display').textContent.toLowerCase();
                const location = row.querySelector('.location-name-display').textContent.toLowerCase();
                
                if (name.includes(searchTerm) || location.includes(searchTerm) || row.getAttribute('data-id').includes(searchTerm)) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });
            checkEmptyState(visibleCount);
        }

        function checkEmptyState(visibleCount) {
            const emptyState = document.getElementById('empty-state');
            const totalRows = document.querySelectorAll('#building-table tr').length;
            const actualVisibleCount = visibleCount !== undefined ? visibleCount : document.querySelectorAll('#building-table tr:not([style*="display: none"])').length;

            if (totalRows === 0 || actualVisibleCount === 0) {
                emptyState.style.display = 'block';
            } else {
                emptyState.style.display = 'none';
            }
        }

        // --- INITIALIZATION ---
        document.addEventListener('DOMContentLoaded', function() {
            checkEmptyState();
            updateAddressField('add');
            updateAddressField('edit');
            
            // Auto hide alerts
            const alerts = document.querySelectorAll('.alert-box');
            if (alerts.length > 0) {
                setTimeout(() => {
                    alerts.forEach(el => el.style.display = 'none');
                }, 5000);
            }
        });

        // Close modals when clicking outside
        document.getElementById('addModal').addEventListener('click', function(e) { if (e.target === this) closeAddModal(); });
        document.getElementById('editModal').addEventListener('click', function(e) { if (e.target === this) closeEditModal(); });
    </script>
</body>
</html>