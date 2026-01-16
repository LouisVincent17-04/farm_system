<?php
$page = "settings";
include '../common/navbar.php';
include '../config/Connection.php';
include '../security/checkRole.php';    
checkRole(3);

// Filter Logic: Default to Active (1), allow Inactive (0)
$filter_status = isset($_GET['status']) && $_GET['status'] == 'inactive' ? 0 : 1;

try {
    if (!isset($conn)) {
        throw new Exception("Database connection failed.");
    }

    $sql = "SELECT USER_ID, FULL_NAME, EMAIL, USER_TYPE, IS_ACTIVE, 
            DATE_FORMAT(CREATED_AT, '%Y-%m-%d') AS JOIN_DATE
            FROM USERS 
            WHERE IS_ACTIVE = :status 
            ORDER BY USER_ID DESC";

    $stmt = $conn->prepare($sql);
    $stmt->execute([':status' => $filter_status]);

    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $users = [];
    echo "<script>console.error('Database Error: " . addslashes($e->getMessage()) . "');</script>";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> <title>Account Management</title>
    <link rel="stylesheet" href="../css/purch_housing_facilities.css">
    <style>
        /* --- Page Specific Styles --- */
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem; }
        .header-info h1 { font-size: 2.5rem; font-weight: bold; margin-bottom: 0.5rem; color: white; }
        .header-info p { color: #cbd5e1; }
        
        /* Filter Tabs */
        .filter-tabs { display: flex; gap: 1rem; margin-bottom: 1.5rem; border-bottom: 1px solid #334155; padding-bottom: 0.5rem; overflow-x: auto; white-space: nowrap; -webkit-overflow-scrolling: touch; }
        .filter-link { color: #94a3b8; text-decoration: none; font-weight: 600; padding: 0.5rem 1rem; border-radius: 6px; transition: all 0.2s; }
        .filter-link:hover { color: white; background: rgba(255,255,255,0.05); }
        .filter-link.active { color: #3b82f6; background: rgba(59, 130, 246, 0.1); border-bottom: 2px solid #3b82f6; }

        /* Search */
        .search-container { position: relative; margin-bottom: 2rem; }
        .search-input { width: 100%; padding: 1rem 1rem 1rem 3rem; background: rgba(30, 41, 59, 0.5); border: 1px solid #475569; border-radius: 0.5rem; color: white; box-sizing: border-box; }
        .search-icon { position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: #94a3b8; }

        /* User Card/Row Styles */
        .account-info { display: flex; align-items: center; gap: 1rem; }
        .account-avatar { width: 2.5rem; height: 2.5rem; background: linear-gradient(135deg, #3b82f6, #9333ea); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; color: white; flex-shrink: 0; }
        
        .role-badge { padding: 4px 10px; border-radius: 12px; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; white-space: nowrap; }
        .role-badge.user { background: rgba(148, 163, 184, 0.2); color: #cbd5e1; border: 1px solid #64748b; }
        .role-badge.farm_employee { background: rgba(16, 185, 129, 0.2); color: #34d399; border: 1px solid #10b981; }
        .role-badge.admin { background: rgba(59, 130, 246, 0.2); color: #60a5fa; border: 1px solid #3b82f6; }
        .role-badge.superadmin { background: rgba(168, 85, 247, 0.2); color: #c084fc; border: 1px solid #a855f7; }

        .status-dot { height: 8px; width: 8px; border-radius: 50%; display: inline-block; margin-right: 6px; }
        .status-dot.active { background-color: #10b981; box-shadow: 0 0 8px #10b981; }
        .status-dot.inactive { background-color: #ef4444; }

        /* Action Buttons */
        .actions { display: flex; gap: 0.5rem; }
        .action-btn { padding: 8px; border-radius: 6px; border: none; cursor: pointer; background: transparent; transition: all 0.2s; display: flex; align-items: center; justify-content: center; }
        .action-btn.edit { color: #60a5fa; } .action-btn.edit:hover { background: rgba(59, 130, 246, 0.2); }
        .action-btn.promote { color: #a78bfa; } .action-btn.promote:hover { background: rgba(168, 85, 247, 0.2); }
        .action-btn.delete { color: #f87171; } .action-btn.delete:hover { background: rgba(239, 68, 68, 0.2); }
        .action-btn.reactivate { color: #10b981; } .action-btn.reactivate:hover { background: rgba(16, 185, 129, 0.2); }

        /* Modal Styles */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.6); backdrop-filter: blur(4px); z-index: 1000; align-items: center; justify-content: center; padding: 1rem; box-sizing: border-box; }
        .modal.show { display: flex; }
        .modal-content { background: #1e293b; border-radius: 0.75rem; width: 100%; max-width: 28rem; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); border: 1px solid #475569; display: flex; flex-direction: column; max-height: 90vh; }
        .modal-header { padding: 1.5rem; border-bottom: 1px solid #475569; }
        .modal-header h2 { font-size: 1.5rem; font-weight: bold; color: white; margin: 0; }
        .modal-body { padding: 1.5rem; overflow-y: auto; }
        .modal-footer { padding: 1.5rem; border-top: 1px solid #475569; display: flex; justify-content: flex-end; gap: 0.75rem; }
        
        .role-option { display: flex; align-items: center; padding: 1rem; background: rgba(30, 41, 59, 0.5); border: 2px solid transparent; border-radius: 0.5rem; cursor: pointer; margin-bottom: 0.5rem; }
        .role-option.selected { border-color: #3b82f6; background: rgba(59, 130, 246, 0.1); }
        .role-option h4 { margin: 0; color: white; font-size: 0.95rem; }
        .role-option p { margin: 0; color: #94a3b8; font-size: 0.8rem; }

        .form-group { display: flex; flex-direction: column; gap: 0.5rem; margin-bottom: 1rem; }
        .form-group label { color: #cbd5e1; font-size: 0.875rem; font-weight: 500; }
        .form-group input { padding: 0.75rem; background: #374151; border: 1px solid #4b5563; border-radius: 0.5rem; color: white; font-size: 1rem; width: 100%; box-sizing: border-box; }
        .btn-cancel { padding: 0.5rem 1.5rem; background: transparent; border: 1px solid #475569; color: #cbd5e1; cursor: pointer; border-radius: 0.5rem; }
        .btn-save { padding: 0.5rem 1.5rem; background: linear-gradient(135deg, #2563eb, #9333ea); border: none; border-radius: 0.5rem; color: white; font-weight: 600; cursor: pointer; }

        /* --- MOBILE RESPONSIVENESS --- */
        @media (max-width: 768px) {
            .header-info h1 { font-size: 1.8rem; }
            
            /* Hide Table Header */
            .table thead { display: none; }
            
            /* Make Table Block */
            .table, .table tbody, .table tr, .table td { display: block; width: 100%; box-sizing: border-box; }
            
            /* Card Style for Rows */
            .table tbody tr {
                background: rgba(30, 41, 59, 0.6);
                border: 1px solid #475569;
                border-radius: 12px;
                margin-bottom: 1rem;
                padding: 1rem;
                box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            }
            
            /* Content within Card */
            .table td {
                padding: 0.5rem 0;
                border-bottom: 1px solid rgba(255,255,255,0.05);
                display: flex;
                justify-content: space-between;
                align-items: center;
                text-align: right;
            }
            
            /* Label for data */
            .table td::before {
                content: attr(data-label);
                float: left;
                font-weight: 600;
                color: #94a3b8;
                font-size: 0.85rem;
                text-transform: uppercase;
                margin-right: 1rem;
            }

            /* Special styling for User Info Row */
            .table td[data-label="User"] {
                display: block; /* Stack avatar and name */
                text-align: left;
                border-bottom: 1px solid #64748b;
                padding-bottom: 1rem;
                margin-bottom: 0.5rem;
            }
            .table td[data-label="User"]::before { display: none; } /* Hide "User" label */

            /* Special styling for Actions Row */
            .table td:last-child {
                border-bottom: none;
                justify-content: flex-end;
                margin-top: 0.5rem;
                padding-top: 0.5rem;
            }
            .table td:last-child::before { display: none; }

            .modal-content { max-width: 100%; margin: 10px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="header-info">
                <h1>Account Management</h1>
                <p>Manage user accounts, roles, and access permissions</p>
            </div>
        </div>

        <div class="filter-tabs">
            <a href="?status=active" class="filter-link <?php echo $filter_status == 1 ? 'active' : ''; ?>">Active Accounts</a>
            <a href="?status=inactive" class="filter-link <?php echo $filter_status == 0 ? 'active' : ''; ?>">Inactive Accounts</a>
        </div>

        <div class="search-container">
            <svg class="search-icon" width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m21 21-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
            <input type="text" class="search-input" id="searchInput" placeholder="Search by name, email, or role..." onkeyup="filterTable()">
        </div>

        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Joined</th>
                        <th style="text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody id="account-table">
                    <?php if (empty($users)): ?>
                        <tr><td colspan="6" style="text-align:center; padding: 3rem; color: #64748b;">No accounts found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($users as $user): 
                            $roleClass = 'user'; $roleText = 'New User';
                            if ($user['USER_TYPE'] == 2) { $roleClass = 'farm_employee'; $roleText = 'Farm Employee'; }
                            if ($user['USER_TYPE'] == 3) { $roleClass = 'admin'; $roleText = 'Admin'; }
                            if ($user['USER_TYPE'] == 4) { $roleClass = 'superadmin'; $roleText = 'Super Admin'; }
                            
                            $initials = strtoupper(substr($user['FULL_NAME'], 0, 1));
                            $isActive = $user['IS_ACTIVE'] == 1;
                        ?>
                        <tr data-user-id="<?php echo $user['USER_ID']; ?>" 
                            data-name="<?php echo htmlspecialchars($user['FULL_NAME']); ?>"
                            data-email="<?php echo htmlspecialchars($user['EMAIL']); ?>"
                            data-role="<?php echo $user['USER_TYPE']; ?>">
                            
                            <td data-label="User">
                                <div class="account-info">
                                    <div class="account-avatar"><?php echo $initials; ?></div>
                                    <div style="font-weight: 600; color: #e2e8f0;"><?php echo htmlspecialchars($user['FULL_NAME']); ?></div>
                                </div>
                            </td>
                            <td data-label="Email" style="color: #94a3b8; word-break: break-all;"><?php echo htmlspecialchars($user['EMAIL']); ?></td>
                            <td data-label="Role"><span class="role-badge <?php echo $roleClass; ?>"><?php echo $roleText; ?></span></td>
                            <td data-label="Status">
                                <span class="status-dot <?php echo $isActive ? 'active' : 'inactive'; ?>"></span>
                                <span style="font-size: 0.9rem; color: #cbd5e1;"><?php echo $isActive ? 'Active' : 'Inactive'; ?></span>
                            </td>
                            <td data-label="Joined" style="color: #64748b; font-size: 0.9rem;"><?php echo $user['JOIN_DATE']; ?></td>
                            <td data-label="Actions">
                                <div class="actions">
                                    <?php if ($isActive): ?>
                                        <button class="action-btn edit" onclick="editAccount(this)" title="Edit Details">
                                            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                        </button>
                                        <button class="action-btn promote" onclick="promoteAccount(this)" title="Change Role">
                                            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                                        </button>
                                        <button class="action-btn delete" onclick="deleteAccount(this)" title="Deactivate">
                                            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                        </button>
                                    <?php else: ?>
                                        <button class="action-btn reactivate" onclick="reactivateAccount(this)" title="Reactivate Account">
                                            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            <div id="empty-state" class="empty-state" style="display:none; text-align:center; padding:2rem; color:#94a3b8;">
                <h3>No matching accounts found</h3>
                <p>Try adjusting your search filters.</p>
            </div>
        </div>
    </div>

    <div id="modal" class="modal">
        <div class="modal-content">
            <div class="modal-header"><h2>Edit Account Details</h2></div>
            <div class="modal-body">
                <form id="account-form">
                    <input type="hidden" id="form_user_id" name="user_id">
                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" id="name" name="full_name" required>
                    </div>
                    <div class="form-group">
                        <label>Email Address</label>
                        <input type="email" id="email" name="email" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn-cancel" onclick="closeModal()">Cancel</button>
                <button class="btn-save" onclick="saveAccount()">Save Changes</button>
            </div>
        </div>
    </div>

    <div id="promotion-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header"><h2>Change User Role</h2></div>
            <div class="modal-body">
                <form id="role-form">
                    <input type="hidden" id="role_user_id" name="user_id">
                    <div style="display:flex; flex-direction:column; gap:0.5rem;">
                        <label class="role-option" onclick="selectRole(1)">
                            <input type="radio" name="new_role" value="1">
                            <div><h4>New User</h4><p>No access</p></div>
                        </label>
                        <label class="role-option" onclick="selectRole(2)">
                            <input type="radio" name="new_role" value="2">
                            <div><h4>Farm User</h4><p>Basic access permissions</p></div>
                        </label>
                        <label class="role-option" onclick="selectRole(3)">
                            <input type="radio" name="new_role" value="3">
                            <div><h4>Admin</h4><p>Manage users & transactions</p></div>
                        </label>
                        <label class="role-option" onclick="selectRole(4)">
                            <input type="radio" name="new_role" value="4">
                            <div><h4>Super Admin</h4><p>Full system control</p></div>
                        </label>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn-cancel" onclick="document.getElementById('promotion-modal').classList.remove('show')">Cancel</button>
                <button class="btn-save" onclick="saveRoleChange()">Update Role</button>
            </div>
        </div>
    </div>

    <script>
        // --- HELPER FUNCTION for AJAX success ---
        function showSuccessAndReload(message) {
            alert(message); 
            window.location.reload();
        }
        
        function filterTable() {
            const term = document.getElementById('searchInput').value.toLowerCase();
            const rows = document.querySelectorAll('#account-table tr');
            let visible = 0;
            rows.forEach(r => {
                const txt = r.innerText.toLowerCase();
                r.style.display = txt.includes(term) ? '' : 'none';
                if(r.style.display !== 'none') visible++;
            });
            document.getElementById('empty-state').style.display = visible ? 'none' : 'block';
        }

        // --- Edit Logic ---
        function editAccount(btn) {
            const d = btn.closest('tr').dataset;
            document.getElementById('form_user_id').value = d.userId;
            document.getElementById('name').value = d.name;
            document.getElementById('email').value = d.email;
            document.getElementById('modal').classList.add('show');
        }

        function closeModal() { document.getElementById('modal').classList.remove('show'); }

        function saveAccount() {
            const form = document.getElementById('account-form');
            if(!form.checkValidity()) { form.reportValidity(); return; }
            
            const saveBtn = document.querySelector('#modal .btn-save');
            saveBtn.disabled = true;
            saveBtn.innerHTML = 'Saving...';

            fetch('../process/editUser.php', { method: 'POST', body: new FormData(form) })
            .then(r => r.json())
            .then(data => {
                if(data.success) { 
                    showSuccessAndReload(data.message || 'Account details updated successfully!'); 
                }
                else { 
                    alert('Error: ' + data.message); 
                    saveBtn.disabled = false;
                    saveBtn.textContent = 'Save Changes';
                }
            })
            .catch(error => {
                 console.error('Fetch error:', error);
                 alert('A network error occurred.');
                 saveBtn.disabled = false;
                 saveBtn.textContent = 'Save Changes';
            });
        }

        // --- Role Logic ---
        function promoteAccount(btn) {
            const d = btn.closest('tr').dataset;
            document.getElementById('role_user_id').value = d.userId;
            
            // Uncheck all radios and remove selection class
            document.querySelectorAll('input[name="new_role"]').forEach(radio => {
                radio.checked = false;
                radio.closest('.role-option').classList.remove('selected');
            });

            // Select current role
            const currentRole = parseInt(d.role);
            selectRole(currentRole);
            
            const curRadio = document.querySelector(`input[name="new_role"][value="${currentRole}"]`);
            if(curRadio) curRadio.checked = true;
            
            document.getElementById('promotion-modal').classList.add('show');
        }

        function selectRole(val) {
            document.querySelectorAll('.role-option').forEach(el => el.classList.remove('selected'));
            const input = document.querySelector(`input[name="new_role"][value="${val}"]`);
            if(input) input.closest('.role-option').classList.add('selected');
        }

        function saveRoleChange() {
            const form = document.getElementById('role-form');
            const saveBtn = document.querySelector('#promotion-modal .btn-save');
            saveBtn.disabled = true;
            saveBtn.innerHTML = 'Saving...';

            fetch('../process/changeUserRole.php', { method: 'POST', body: new FormData(form) })
            .then(r => r.json())
            .then(data => {
                if(data.success) { 
                    showSuccessAndReload(data.message || 'User role updated successfully!'); 
                }
                else { 
                    alert('Error: ' + data.message);
                    saveBtn.disabled = false;
                    saveBtn.textContent = 'Update Role';
                }
            })
             .catch(error => {
                 console.error('Fetch error:', error);
                 alert('A network error occurred.');
                 saveBtn.disabled = false;
                 saveBtn.textContent = 'Update Role';
            });
        }

        // --- UPDATED: Delete now uses fetch (AJAX) ---
        function deleteAccount(btn) {
            if(confirm("Deactivate this user account?")) {
                const userId = btn.closest('tr').dataset.userId;
                const fd = new FormData();
                fd.append('user_id', userId);

                fetch('../process/deleteUser.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if(data.success) { 
                        showSuccessAndReload(data.message); 
                    } else { 
                        alert('Error: ' + data.message); 
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert('Network error.');
                });
            }
        }

        // --- UPDATED: Reactivate now uses fetch (AJAX) ---
        function reactivateAccount(btn) {
            if(confirm("Reactivate this user account?")) {
                const userId = btn.closest('tr').dataset.userId;
                const fd = new FormData();
                fd.append('user_id', userId);

                fetch('../process/reactivateUser.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if(data.success) { 
                        showSuccessAndReload(data.message); 
                    } else { 
                        alert('Error: ' + data.message); 
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert('Network error.');
                });
            }
        }
        
        window.onclick = function(e) {
            if(e.target.classList.contains('modal')) e.target.classList.remove('show');
        }
    </script>
</body>
</html>