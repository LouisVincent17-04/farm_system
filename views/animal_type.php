<?php
// views/animal_type.php
error_reporting(0);
ini_set('display_errors', 0);

$page = "admin_dashboard"; // Keeps the dashboard highlighted in navbar
include '../common/navbar.php';
include '../config/Connection.php';

// Check for status messages from redirects
$status = $_GET['status'] ?? '';
$msg = $_GET['msg'] ?? '';

try {
    if (!isset($conn)) {
        throw new Exception("Database connection failed.");
    }

    // 1. Fetch Animal Types
    $sql = "SELECT * FROM Animal_Type ORDER BY ANIMAL_TYPE_ID ASC";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $animal_type_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $animal_type_data = [];
    $status = 'error';
    $msg = "Database Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> <title>Animal Type Management System</title>
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
        
        .animal-type-info { display: flex; align-items: center; gap: 1rem; }
        .animal-type-details h3 { font-size: 1.125rem; font-weight: 600; margin-bottom: 0.25rem; }
        
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
        .form-group input { padding: 0.75rem; background: #374151; border: 1px solid #4b5563; border-radius: 0.5rem; color: white; font-size: 1rem; }
        .form-group input:focus { outline: none; border-color: #10b981; box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1); }
        
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
            
            /* Header adjustments */
            .header { flex-direction: column; align-items: stretch; gap: 1rem; text-align: center; }
            .header-info h1 { font-size: 1.75rem; }
            .add-btn { width: 100%; justify-content: center; }

            /* Table to Card View Transformation */
            .table thead { display: none; } /* Hide Headers */
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

            .table td:last-child { border-bottom: none; justify-content: center; padding-top: 1rem; }

            /* Add Labels via Data Attributes */
            .table td::before {
                content: attr(data-label);
                font-weight: 600;
                color: #94a3b8;
                font-size: 0.85rem;
                text-transform: uppercase;
                margin-right: 1rem;
            }

            /* Adjust specific cells */
            .animal-type-info { justify-content: flex-end; }
            
            /* Modals */
            .modal-content { width: 95%; max-height: 90vh; overflow-y: auto; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="header-info">
                <h1>Animal Type Management</h1>
                <p>Manage Animal Types and their information</p>
            </div>
            <button class="add-btn" onclick="openAddModal()">
                <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                Add Animal Type
            </button>
        </div>

        <?php if (!empty($msg)): ?>
            <div class="alert-box alert-<?php echo htmlspecialchars($status); ?>">
                <?php echo htmlspecialchars($msg); ?>
            </div>
        <?php endif; ?>

        <div class="search-container">
            <svg class="search-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m21 21-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
            </svg>
            <input type="text" class="search-input" placeholder="Search animal types by name..." onkeyup="filterTable()">
        </div>

        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Animal Type ID</th>
                        <th>Animal Type Name</th>
                        <th style="text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody id="animal-type-table">
                    <?php if (empty($animal_type_data)): ?>
                        <?php else: ?>
                        <?php foreach($animal_type_data as $data): ?>
                        <tr data-id="<?php echo $data['ANIMAL_TYPE_ID']; ?>">
                            <td data-label="Type ID">
                                <span style="font-family: monospace; color: #94a3b8;">#<?php echo $data['ANIMAL_TYPE_ID']; ?></span>
                            </td>
                            <td data-label="Type Name">
                                <div class="animal-type-info">
                                    <div class="animal-type-details">
                                        <h3><?php echo htmlspecialchars($data['ANIMAL_TYPE_NAME']); ?></h3>
                                    </div>
                                </div>
                            </td>
                            <td data-label="Actions">
                                <div class="actions">
                                    <button class="action-btn edit" onclick="editAnimalType(this)" title="Edit">
                                        <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                        </svg>
                                    </button>
                                    <button class="action-btn delete" onclick="deleteAnimalType(this)" title="Delete">
                                        <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            <div id="empty-state" class="empty-state" style="<?php echo empty($animal_type_data) ? 'display:block' : 'display:none'; ?>">
                <h3>No animal types found</h3>
                <p>Try adjusting your search terms</p>
            </div>
        </div>
    </div>

    <div id="addModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Add New Animal Type</h2>
            </div>
            <div class="modal-body">
                <form id="addAnimalTypeForm" method="POST" action="../process/addAnimalType.php">
                    <div class="form-group">
                        <label for="add_animal_type_name">Animal Type Name</label>
                        <input type="text" id="add_animal_type_name" name="animal_type_name" placeholder="example: Swine, Cattle, Poultry" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeAddModal()">Cancel</button>
                <button type="button" class="btn-save" onclick="submitAddForm()">Add Animal Type</button>
            </div>
        </div>
    </div>

    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit Animal Type</h2>
            </div>
            <div class="modal-body">
                <form id="editAnimalTypeForm" method="POST" action="../process/updateAnimalType.php">
                    <input type="hidden" id="edit_animal_type_id" name="animal_type_id">
                    <div class="form-group">
                        <label for="edit_animal_type_name">Animal Type Name</label>
                        <input type="text" id="edit_animal_type_name" name="animal_type_name" placeholder="example: Swine, Cattle, Poultry" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeEditModal()">Cancel</button>
                <button type="button" class="btn-save" onclick="submitEditForm()">Update Animal Type</button>
            </div>
        </div>
    </div>

    <form id="deleteAnimalTypeForm" method="POST" action="../process/deleteAnimalType.php" style="display: none;">
        <input type="hidden" id="delete_animal_type_id" name="animal_type_id">
    </form>

    <script>
        // Open add modal
        function openAddModal() {
            document.getElementById('addAnimalTypeForm').reset();
            document.getElementById('addModal').classList.add('show');
        }

        // Close add modal
        function closeAddModal() {
            document.getElementById('addModal').classList.remove('show');
        }

        // Submit add form
        function submitAddForm() {
            const form = document.getElementById('addAnimalTypeForm');
            const name = document.getElementById('add_animal_type_name').value.trim();

            if (!name) {
                alert('Please fill in the animal type name');
                return;
            }

            if (confirm('Do you want to add this animal type?')) {
                form.submit();
            }
        }

        // Open edit modal
        function editAnimalType(button) {
            // Updated to find closest TR regardless of desktop or mobile layout
            const row = button.closest('tr');
            const animalTypeId = row.getAttribute('data-id');
            const name = row.querySelector('.animal-type-details h3').textContent.trim();
            
            document.getElementById('edit_animal_type_id').value = animalTypeId;
            document.getElementById('edit_animal_type_name').value = name;
            document.getElementById('editModal').classList.add('show');
        }

        // Close edit modal
        function closeEditModal() {
            document.getElementById('editModal').classList.remove('show');
        }

        // Submit edit form
        function submitEditForm() {
            const form = document.getElementById('editAnimalTypeForm');
            const name = document.getElementById('edit_animal_type_name').value.trim();

            if (!name) {
                alert('Please fill in the animal type name');
                return;
            }

            if (confirm('Do you want to update this animal type?')) {
                form.submit();
            }
        }

        // Delete animal type
        function deleteAnimalType(button) {
            const row = button.closest('tr');
            const animalTypeId = row.getAttribute('data-id');
            
            if (confirm('Are you sure you want to delete this animal type?')) {
                document.getElementById('delete_animal_type_id').value = animalTypeId;
                document.getElementById('deleteAnimalTypeForm').submit();
            }
        }

        // Filter table
        function filterTable() {
            const searchTerm = document.querySelector('.search-input').value.toLowerCase();
            const rows = document.querySelectorAll('#animal-type-table tr');
            let visibleCount = 0;

            rows.forEach(row => {
                const name = row.querySelector('.animal-type-details h3').textContent.toLowerCase();
                
                if (name.includes(searchTerm)) {
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
            const tbody = document.getElementById('animal-type-table');
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