<?php

$page="settings";
include '../common/navbar.php';
include '../config/Connection.php';
include '../config/Queries.php';
include '../security/checkRole.php';    
checkRole(3);
// Correct query for users
$sql = "SELECT * FROM Units order by UNIT_ID ASC";
$unit_data = retrieveData($conn, $sql);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unit Management System</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            min-height: 100vh;
            color: white;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .header-info h1 {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }

        .header-info p {
            color: #cbd5e1;
        }

        .add-btn {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }

        .add-btn:hover {
            background: linear-gradient(135deg, #059669, #047857);
            transform: scale(1.05);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2);
        }

        .search-container {
            position: relative;
            margin-bottom: 2rem;
        }

        .search-input {
            width: 100%;
            padding: 1rem 1rem 1rem 3rem;
            background: rgba(30, 41, 59, 0.5);
            border: 1px solid #475569;
            border-radius: 0.5rem;
            color: white;
            font-size: 1rem;
            backdrop-filter: blur(10px);
        }

        .search-input::placeholder {
            color: #94a3b8;
        }

        .search-input:focus {
            outline: none;
            border-color: #10b981;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
        }

        .search-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            width: 20px;
            height: 20px;
        }

        .table-container {
            background: rgba(30, 41, 59, 0.5);
            backdrop-filter: blur(10px);
            border-radius: 0.75rem;
            border: 1px solid #475569;
            overflow: hidden;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }

        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table thead {
            background: linear-gradient(135deg, #475569, #334155);
        }

        .table th {
            padding: 1rem 1.5rem;
            text-align: left;
            font-size: 0.875rem;
            font-weight: 600;
            color: #e2e8f0;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .table tbody tr {
            border-bottom: 1px solid #475569;
            transition: background-color 0.2s;
        }

        .table tbody tr:hover {
            background: rgba(55, 65, 81, 0.5);
        }

        .table td {
            padding: 1rem 1.5rem;
            vertical-align: middle;
        }

        .Unit-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .Unit-details h3 {
            font-size: 1.125rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .unit_abbreviation-info {
            color: #cbd5e1;
            font-size: 0.875rem;
        }

        .actions {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .action-btn {
            padding: 0.5rem;
            border: none;
            border-radius: 0.5rem;
            cursor: pointer;
            transition: all 0.2s;
            background: transparent;
        }

        .action-btn.edit {
            color: #60a5fa;
        }

        .action-btn.edit:hover {
            color: #93c5fd;
            background: rgba(59, 130, 246, 0.2);
        }

        .action-btn.delete {
            color: #f87171;
        }

        .action-btn.delete:hover {
            color: #fca5a5;
            background: rgba(239, 68, 68, 0.2);
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(4px);
            z-index: 1000;
            padding: 1rem;
        }

        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: #1e293b;
            border-radius: 0.75rem;
            width: 100%;
            max-width: 28rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }

        .modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid #475569;
        }

        .modal-header h2 {
            font-size: 1.5rem;
            font-weight: bold;
        }

        .modal-body {
            padding: 1.5rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }

        .form-group label {
            color: #cbd5e1;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .form-group input {
            padding: 0.75rem;
            background: #374151;
            border: 1px solid #4b5563;
            border-radius: 0.5rem;
            color: white;
            font-size: 1rem;
        }

        .form-group input:focus {
            outline: none;
            border-color: #10b981;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
        }

        .modal-footer {
            padding: 1.5rem;
            border-top: 1px solid #475569;
            display: flex;
            justify-content: flex-end;
            gap: 0.75rem;
        }

        .btn-cancel {
            padding: 0.5rem 1.5rem;
            background: transparent;
            border: none;
            color: #cbd5e1;
            cursor: pointer;
            transition: color 0.2s;
        }

        .btn-cancel:hover {
            color: white;
        }

        .btn-save {
            padding: 0.5rem 1.5rem;
            background: linear-gradient(135deg, #10b981, #059669);
            border: none;
            border-radius: 0.5rem;
            color: white;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-save:hover {
            background: linear-gradient(135deg, #059669, #047857);
        }

        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            display: none;
        }

        .empty-state h3 {
            font-size: 1.125rem;
            color: #94a3b8;
            margin-bottom: 0.5rem;
        }

        .empty-state p {
            color: #64748b;
            font-size: 0.875rem;
        }

        .icon {
            width: 18px;
            height: 18px;
        }

        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }

            .header-info h1 {
                font-size: 2rem;
            }

            .table-container {
                overflow-x: auto;
            }

            .table {
                min-width: 600px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="header-info">
                <h1>Unit Management</h1>
                <p>Manage Unit Units and their information</p>
            </div>
            <button class="add-btn" onclick="openAddModal()">
                <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                Add Unit
            </button>
        </div>

        <!-- Search -->
        <div class="search-container">
            <svg class="search-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m21 21-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
            </svg>
            <input type="text" class="search-input" placeholder="Search Units by name or abbreviation..." onkeyup="filterTable()">
        </div>

        <!-- Table -->
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Unit ID</th>
                        <th>Unit Name</th>
                        <th>Unit Abbreviation</th>
                        <th style="text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody id="Unit-table">
                    <?php foreach($unit_data as $data): ?>
                    <tr data-id="<?php echo $data['UNIT_ID']; ?>">
                        <td>
                            <?php echo $data['UNIT_ID']; ?>
                        </td>
                        <td>
                            <div class="Unit-info">
                                <div class="Unit-details">
                                    <h3><?php echo $data['UNIT_NAME']; ?></h3>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="unit_abbreviation-info"><?php echo $data['UNIT_ABBR']; ?></div>
                        </td>
                        <td>
                            <div class="actions">
                                <button class="action-btn edit" onclick="editUnit(this)" title="Edit">
                                    <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                    </svg>
                                </button>
                                <button class="action-btn delete" onclick="deleteUnit(this)" title="Delete">
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
            <div id="empty-state" class="empty-state">
                <h3>No Units found</h3>
                <p>Try adjusting your search terms</p>
            </div>
        </div>
    </div>

    <!-- Add Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Add New Unit</h2>
            </div>
            <div class="modal-body">
                <form id="addUnitForm" method="POST" action="../process/addUnits.php">
                    <div class="form-group">
                        <label for="add_unit_name">Unit Name</label>
                        <input type="text" id="add_unit_name" name="unit_name" placeholder="example: kilograms" required>
                    </div>
                    <div class="form-group">
                        <label for="add_unit_abbreviation">Unit Abbreviation</label>
                        <input type="text" id="add_unit_abbreviation" name="unit_abbreviation" placeholder="example: kg" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeAddModal()">Cancel</button>
                <button type="button" class="btn-save" onclick="submitAddForm()">Add Unit</button>
            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit Unit</h2>
            </div>
            <div class="modal-body">
                <form id="editUnitForm" method="POST" action="../process/updateUnit.php">
                    <input type="hidden" id="edit_unit_id" name="unit_id">
                    <div class="form-group">
                        <label for="edit_unit_name">Unit Name</label>
                        <input type="text" id="edit_unit_name" name="unit_name" placeholder="example: kilograms" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_unit_abbreviation">Unit Abbreviation</label>
                        <input type="text" id="edit_unit_abbreviation" name="unit_abbreviation" placeholder="example: kg" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeEditModal()">Cancel</button>
                <button type="button" class="btn-save" onclick="submitEditForm()">Update Unit</button>
            </div>
        </div>
    </div>

    <!-- Delete Form (Hidden) -->
    <form id="deleteUnitForm" method="POST" action="../process/deleteUnit.php" style="display: none;">
        <input type="hidden" id="delete_unit_id" name="unit_id">
    </form>

    <script>
        // Open add modal
        function openAddModal() {
            document.getElementById('addUnitForm').reset();
            document.getElementById('addModal').classList.add('show');
        }

        // Close add modal
        function closeAddModal() {
            document.getElementById('addModal').classList.remove('show');
        }

        // Submit add form
        function submitAddForm() {
            const form = document.getElementById('addUnitForm');
            const name = document.getElementById('add_unit_name').value.trim();
            const abbreviation = document.getElementById('add_unit_abbreviation').value.trim();

            if (!name || !abbreviation) {
                alert('Please fill in all fields');
                return;
            }

            if (confirm('Do you want to add this unit?')) {
                form.submit();
            }
        }

        // Open edit modal
        function editUnit(button) {
            const row = button.closest('tr');
            const unitId = row.getAttribute('data-id');
            const name = row.querySelector('.Unit-details h3').textContent.trim();
            const abbreviation = row.querySelector('.unit_abbreviation-info').textContent.trim();
            
            document.getElementById('edit_unit_id').value = unitId;
            document.getElementById('edit_unit_name').value = name;
            document.getElementById('edit_unit_abbreviation').value = abbreviation;
            document.getElementById('editModal').classList.add('show');
        }

        // Close edit modal
        function closeEditModal() {
            document.getElementById('editModal').classList.remove('show');
        }

        // Submit edit form
        function submitEditForm() {
            const form = document.getElementById('editUnitForm');
            const name = document.getElementById('edit_unit_name').value.trim();
            const abbreviation = document.getElementById('edit_unit_abbreviation').value.trim();

            if (!name || !abbreviation) {
                alert('Please fill in all fields');
                return;
            }

            if (confirm('Do you want to update this unit?')) {
                form.submit();
            }
        }

        // Delete unit
        function deleteUnit(button) {
            const row = button.closest('tr');
            const unitId = row.getAttribute('data-id');
            
            if (confirm('Are you sure you want to delete this unit?')) {
                document.getElementById('delete_unit_id').value = unitId;
                document.getElementById('deleteUnitForm').submit();
            }
        }

        // Filter table
        function filterTable() {
            const searchTerm = document.querySelector('.search-input').value.toLowerCase();
            const rows = document.querySelectorAll('#Unit-table tr');
            let visibleCount = 0;

            rows.forEach(row => {
                const name = row.querySelector('.Unit-details h3').textContent.toLowerCase();
                const abbreviation = row.querySelector('.unit_abbreviation-info').textContent.toLowerCase();
                
                if (name.includes(searchTerm) || abbreviation.includes(searchTerm)) {
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
            const tbody = document.getElementById('Unit-table');
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
        });
    </script>
</body>
</html>

