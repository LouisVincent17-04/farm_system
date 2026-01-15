<?php
// ../views/veterinary.php

$page="admin_dashboard";
include '../common/navbar.php';
include '../config/Connection.php';
include '../config/Queries.php';
include '../functions/getInitialsFunction.php';
include '../security/checkRole.php';    
checkRole(3); // Admin Access

// UPDATED SQL: Retrieve directly from VETERINARIANS table
$sql = "SELECT VET_ID, FULL_NAME, CONTACT_INFO FROM VETERINARIANS ORDER BY VET_ID DESC";
$vet_data = retrieveData($conn, $sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Veterinary Management System</title>
    <style>
        /* (Keep your existing CSS styles exactly as they were) */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif; background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); min-height: 100vh; color: white; }
        .container { max-width: 1400px; margin: 0 auto; padding: 2rem; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; }
        .header-info h1 { font-size: 2.5rem; font-weight: bold; margin-bottom: 0.5rem; }
        .header-info p { color: #cbd5e1; }
        .add-btn { display: flex; align-items: center; gap: 0.5rem; background: linear-gradient(135deg, #2563eb, #9333ea); color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 0.5rem; font-weight: 600; cursor: pointer; transition: all 0.2s; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); }
        .add-btn:hover { background: linear-gradient(135deg, #1d4ed8, #7c3aed); transform: scale(1.05); box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2); }
        .search-container { position: relative; margin-bottom: 2rem; }
        .search-input { width: 100%; padding: 1rem 1rem 1rem 3rem; background: rgba(30, 41, 59, 0.5); border: 1px solid #475569; border-radius: 0.5rem; color: white; font-size: 1rem; backdrop-filter: blur(10px); }
        .search-input::placeholder { color: #94a3b8; }
        .search-input:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1); }
        .search-icon { position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: #94a3b8; width: 20px; height: 20px; }
        .table-container { background: rgba(30, 41, 59, 0.5); backdrop-filter: blur(10px); border-radius: 0.75rem; border: 1px solid #475569; overflow: hidden; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); }
        .table { width: 100%; border-collapse: collapse; }
        .table thead { background: linear-gradient(135deg, #475569, #334155); }
        .table th { padding: 1rem 1.5rem; text-align: left; font-size: 0.875rem; font-weight: 600; color: #e2e8f0; text-transform: uppercase; letter-spacing: 0.05em; }
        .table tbody tr { border-bottom: 1px solid #475569; transition: background-color 0.2s; }
        .table tbody tr:hover { background: rgba(55, 65, 81, 0.5); }
        .table td { padding: 1rem 1.5rem; vertical-align: middle; }
        .vet-info { display: flex; align-items: center; gap: 1rem; }
        .vet-avatar { width: 3rem; height: 3rem; background: linear-gradient(135deg, #3b82f6, #9333ea); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 1.125rem; }
        .vet-details h3 { font-size: 1.125rem; font-weight: 600; margin-bottom: 0.25rem; }
        .contact-info { color: #cbd5e1; font-size: 0.875rem; }
        .actions { display: flex; align-items: center; justify-content: center; gap: 0.5rem; }
        .action-btn { padding: 0.5rem; border: none; border-radius: 0.5rem; cursor: pointer; transition: all 0.2s; background: transparent; }
        .action-btn.edit { color: #60a5fa; }
        .action-btn.edit:hover { color: #93c5fd; background: rgba(59, 130, 246, 0.2); }
        .action-btn.delete { color: #f87171; }
        .action-btn.delete:hover { color: #fca5a5; background: rgba(239, 68, 68, 0.2); }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.6); backdrop-filter: blur(4px); z-index: 1000; padding: 1rem; }
        .modal.show { display: flex; align-items: center; justify-content: center; }
        .modal-content { background: #1e293b; border-radius: 0.75rem; width: 100%; max-width: 28rem; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); }
        .modal-header { padding: 1.5rem; border-bottom: 1px solid #475569; }
        .modal-header h2 { font-size: 1.5rem; font-weight: bold; }
        .modal-body { padding: 1.5rem; }
        .form-group { display: flex; flex-direction: column; gap: 0.5rem; margin-bottom: 1rem; }
        .form-group label { color: #cbd5e1; font-size: 0.875rem; font-weight: 500; }
        .form-group input { padding: 0.75rem; background: #374151; border: 1px solid #4b5563; border-radius: 0.5rem; color: white; font-size: 1rem; }
        .form-group input:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1); }
        .modal-footer { padding: 1.5rem; border-top: 1px solid #475569; display: flex; justify-content: flex-end; gap: 0.75rem; }
        .btn-cancel { padding: 0.5rem 1.5rem; background: transparent; border: none; color: #cbd5e1; cursor: pointer; transition: color 0.2s; }
        .btn-cancel:hover { color: white; }
        .btn-save { padding: 0.5rem 1.5rem; background: linear-gradient(135deg, #2563eb, #9333ea); border: none; border-radius: 0.5rem; color: white; font-weight: 600; cursor: pointer; transition: all 0.2s; }
        .btn-save:hover { background: linear-gradient(135deg, #1d4ed8, #7c3aed); }
        .empty-state { text-align: center; padding: 3rem 1rem; display: none; }
        .empty-state h3 { font-size: 1.125rem; color: #94a3b8; margin-bottom: 0.5rem; }
        .empty-state p { color: #64748b; font-size: 0.875rem; }
        .icon { width: 18px; height: 18px; }
        @media (max-width: 768px) {
            .header { flex-direction: column; gap: 1rem; text-align: center; }
            .header-info h1 { font-size: 2rem; }
            .table-container { overflow-x: auto; }
            .table { min-width: 600px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="header-info">
                <h1>Veterinary Management</h1>
                <p>Manage your veterinary team and their information</p>
            </div>
            <button class="add-btn" onclick="openAddModal()">
                <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                Add Veterinarian
            </button>
        </div>

        <div class="search-container">
            <svg class="search-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m21 21-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
            </svg>
            <input type="text" class="search-input" placeholder="Search veterinarians by name or contact..." onkeyup="filterTable()">
        </div>

        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Veterinarian</th>
                        <th>Contact</th>
                        <th style="text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody id="veterinarian-table">
                    <?php foreach ($vet_data as $vet): ?>
                    <tr data-id="<?php echo $vet['VET_ID']; ?>" 
                        data-name="<?php echo htmlspecialchars($vet['FULL_NAME']); ?>" 
                        data-contact="<?php echo htmlspecialchars($vet['CONTACT_INFO']); ?>">
                        <td>
                            <div class="vet-info">
                                <div class="vet-avatar"><?php echo getInitials($vet['FULL_NAME']); ?></div>
                                <div class="vet-details">
                                    <h3><?php echo htmlspecialchars($vet['FULL_NAME']); ?></h3>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="contact-info">
                                <?php echo !empty($vet['CONTACT_INFO']) ? htmlspecialchars($vet['CONTACT_INFO']) : "Not Set"; ?>
                            </div>
                        </td>
                        <td>
                            <div class="actions">
                                <button class="action-btn edit" onclick="editVet(this)" title="Edit">
                                    <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                    </svg>
                                </button>
                                <button class="action-btn delete" onclick="deleteVet(this)" title="Delete">
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
                <h3>No veterinarians found</h3>
                <p>Try adjusting your search terms</p>
            </div>
        </div>
    </div>

    <div id="modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modal-title">Add New Veterinarian</h2>
            </div>
            <div class="modal-body">
                <form id="vet-form" method="POST" action="">
                    <input type="hidden" id="vet_id" name="user_id">
                    
                    <div class="form-group">
                        <label for="name">Full Name</label>
                        <input type="text" id="name" name="fullName" placeholder="Dr. Louis Vincent" required>
                    </div>
                    <div class="form-group">
                        <label for="contact">Contact Number</label>
                        <input type="text" id="contact" name="contactInfo" placeholder="09657877713" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeModal()">Cancel</button>
                <button type="button" class="btn-save" onclick="submitForm()">Save</button>
            </div>
        </div>
    </div>

    <form id="deleteVetForm" method="POST" action="../process/deleteVeterinarian.php" style="display: none;">
        <input type="hidden" id="delete_vet_id" name="user_id">
    </form>

    <script>
        // Open add modal
        function openAddModal() {
            document.getElementById('modal-title').textContent = 'Add New Veterinarian';
            document.querySelector('.btn-save').textContent = 'Add Veterinarian';
            
            // Set form to Add Mode
            const form = document.getElementById('vet-form');
            form.action = '../process/addVeterinarian.php'; 
            form.reset();
            document.getElementById('vet_id').value = ''; // Clear ID
            
            document.getElementById('modal').classList.add('show');
        }

        // Edit veterinarian
        function editVet(button) {
            const row = button.closest('tr');
            
            // Get data from data-attributes
            const id = row.getAttribute('data-id');
            const name = row.getAttribute('data-name');
            const contact = row.getAttribute('data-contact');
            
            document.getElementById('modal-title').textContent = 'Edit Veterinarian';
            document.querySelector('.btn-save').textContent = 'Update Veterinarian';
            
            // Set form to Edit Mode
            const form = document.getElementById('vet-form');
            form.action = '../process/editVeterinarian.php'; // Points to the edit process
            
            document.getElementById('vet_id').value = id;
            document.getElementById('name').value = name;
            document.getElementById('contact').value = contact;
            
            document.getElementById('modal').classList.add('show');
        }

        // Submit the form in the modal
        function submitForm() {
            const form = document.getElementById('vet-form');
            const name = document.getElementById('name').value.trim();
            const contact = document.getElementById('contact').value.trim();

            if (!name || !contact) {
                alert('Please fill in all fields');
                return;
            }

            // Optional confirmation
            const actionType = document.querySelector('.btn-save').textContent;
            if (confirm(`Do you want to ${actionType.toLowerCase()}?`)) {
                form.submit();
            }
        }

        // Delete veterinarian
        function deleteVet(button) {
            const row = button.closest('tr');
            const id = row.getAttribute('data-id');

            if (confirm('Are you sure you want to remove this veterinarian?')) {
                document.getElementById('delete_vet_id').value = id;
                document.getElementById('deleteVetForm').submit();
            }
        }

        // Close modal
        function closeModal() {
            document.getElementById('modal').classList.remove('show');
        }

        // Filter table
        function filterTable() {
            const searchTerm = document.querySelector('.search-input').value.toLowerCase();
            const rows = document.querySelectorAll('#veterinarian-table tr');
            let visibleCount = 0;

            rows.forEach(row => {
                const name = row.querySelector('.vet-details h3').textContent.toLowerCase();
                const contact = row.querySelector('.contact-info').textContent.toLowerCase();
                
                if (name.includes(searchTerm) || contact.includes(searchTerm)) {
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
            const tbody = document.getElementById('veterinarian-table');
            const emptyState = document.getElementById('empty-state');
            const totalRows = tbody.querySelectorAll('tr').length;
            const actualVisibleCount = visibleCount !== undefined ? visibleCount : tbody.querySelectorAll('tr:not([style*="display: none"])').length;

            if (totalRows === 0 || actualVisibleCount === 0) {
                emptyState.style.display = 'block';
            } else {
                emptyState.style.display = 'none';
            }
        }

        // Close modal when clicking outside
        document.getElementById('modal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            checkEmptyState();
        });
    </script>
</body>
</html>
<?php oci_close($conn); ?>