<?php
// views/building.php
error_reporting(0);
ini_set('display_errors', 0);

$page="admin_dashboard";
// Ensure these includes exist and function correctly
include '../common/navbar.php';
include '../config/Connection.php'; 
// include '../config/Queries.php'; // Not needed for direct PDO

// Check for status messages from redirects
$status = $_GET['status'] ?? '';
$msg = $_GET['msg'] ?? '';

try {
    if (!isset($conn)) {
        throw new Exception("Database connection failed.");
    }

    // 1. Get Building Data (Joined with Locations for efficiency)
    $sql = "SELECT b.BUILDING_ID, b.BUILDING_NAME, b.LOCATION_ID, l.LOCATION_NAME 
            FROM BUILDINGS b
            LEFT JOIN LOCATIONS l ON b.LOCATION_ID = l.LOCATION_ID
            ORDER BY b.BUILDING_ID ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $building_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Get Location Data (Lookup table for select boxes)
    // Fetch all location details including address for the dropdown helper text
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Building Management System</title>
    <link rel="stylesheet" href="../css/building.css">
</head>
<body>
    <div class="container">
        <?php if (!empty($msg)): ?>
        <div style="padding: 1rem; border-radius: 0.5rem; margin-bottom: 1.5rem; text-align: center; font-weight: 500; 
            <?php echo ($status === 'success') ? 'background: rgba(16, 185, 129, 0.2); border: 1px solid #10b981; color: #6ee7b7;' : 
                      'background: rgba(239, 68, 68, 0.2); border: 1px solid #ef4444; color: #fca5a5;'; ?>">
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
                        <th>Location</th> <th style="text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody id="building-table">
                    <?php foreach($building_data as $data): ?>
                    <tr data-id="<?php echo $data['BUILDING_ID']; ?>" data-location-id="<?php echo $data['LOCATION_ID']; ?>">
                        <td>
                            <?php echo $data['BUILDING_ID']; ?>
                        </td>
                        <td>
                            <div class="building-info">
                                <div class="building-details">
                                    <h3 class="building-name-display"><?php echo htmlspecialchars($data['BUILDING_NAME']); ?></h3>
                                </div>
                            </div>
                        </td>
                        <td class="location-name-display">
                            <?php echo htmlspecialchars($data['LOCATION_NAME'] ?? 'N/A'); ?>
                        </td>
                        <td>
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
            <div class="modal-header">
                <h2>Add New Building</h2>
            </div>
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
                                    <option 
                                        value="<?php echo $loc['LOCATION_ID']; ?>" 
                                        data-address="<?php echo htmlspecialchars($loc['COMPLETE_ADDRESS']); ?>">
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
            <div class="modal-header">
                <h2>Edit Building</h2>
            </div>
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
                                    <option 
                                        value="<?php echo $loc['LOCATION_ID']; ?>" 
                                        data-address="<?php echo htmlspecialchars($loc['COMPLETE_ADDRESS']); ?>">
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
            // Ensure address field is initialized for the first option
            updateAddressField('add'); 
        }

        function closeAddModal() {
            document.getElementById('addModal').classList.remove('show');
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.remove('show');
        }

        // --- SUBMIT FUNCTIONS ---

        function submitAddForm() {
            const form = document.getElementById('addBuildingForm');
            const name = document.getElementById('add_building_name').value.trim();

            if (!name) {
                alert('Please fill in the Building Name.');
                return;
            }

            if (confirm('Do you want to add this building?')) {
                form.submit();
            }
        }

        function submitEditForm() {
            const form = document.getElementById('editBuildingForm');
            const name = document.getElementById('edit_building_name').value.trim();

            if (!name) {
                alert('Please fill in the Building Name.');
                return;
            }

            if (confirm('Do you want to update this building?')) {
                form.submit();
            }
        }

        // --- CRUD ACTION FUNCTIONS ---

        function editBuilding(button) {
            const row = button.closest('tr');
            const buildingId = row.getAttribute('data-id');
            const locationId = row.getAttribute('data-location-id');
            const name = row.querySelector('.building-name-display').textContent.trim();
            
            // Set values in the Edit Modal fields
            document.getElementById('edit_building_id').value = buildingId;
            document.getElementById('edit_building_name').value = name;
            
            // Set the correct option in the Location dropdown
            const locationSelect = document.getElementById('edit_building_location_select');
            locationSelect.value = locationId; 

            // Update the helper address field based on the selected location
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
            const tbody = document.getElementById('building-table');
            const emptyState = document.getElementById('empty-state');
            const totalRows = tbody.querySelectorAll('tr').length;
            const actualVisibleCount = visibleCount !== undefined ? visibleCount : tbody.querySelectorAll('tr:not([style*="display: none"])').length;

            if (totalRows === 0 || actualVisibleCount === 0) {
                emptyState.style.display = 'block';
            } else {
                emptyState.style.display = 'none';
            }
        }

        // --- INITIALIZATION ---
        document.addEventListener('DOMContentLoaded', function() {
            checkEmptyState();
            // Initialize address field for both modals
            updateAddressField('add');
            updateAddressField('edit');
        });

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
        
    </script>
</body>
</html>