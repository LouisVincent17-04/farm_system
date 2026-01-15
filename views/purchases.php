<?php
// views/items.php
error_reporting(0);
ini_set('display_errors', 0);

$page="admin_dashboard";
include '../common/navbar.php';
include '../config/Connection.php';
// include '../config/Queries.php'; // Not needed for direct PDO
include '../security/checkRole.php';    
checkRole(3);

try {
    if (!isset($conn)) {
        throw new Exception("Database connection failed.");
    }

    // 1. Fetch Items with Item Type and Unit details
    $items_sql = "SELECT i.*, 
                  it.ITEM_TYPE_NAME,
                  u.UNIT_NAME
                  FROM ITEMS i
                  LEFT JOIN ITEM_TYPES it ON i.ITEM_TYPE_ID = it.ITEM_TYPE_ID
                  LEFT JOIN UNITS u ON i.UNIT_ID = u.UNIT_ID
                  ORDER BY i.CREATED_AT DESC";
    
    $stmt = $conn->prepare($items_sql);
    $stmt->execute();
    $items_data = $stmt->fetchAll(PDO::FETCH_ASSOC);


    // 2. Fetch Units for Dropdown
    $units_sql = "SELECT * FROM UNITS ORDER BY UNIT_NAME ASC";
    $stmt = $conn->prepare($units_sql);
    $stmt->execute();
    $units = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $items_data = [];
    $units = [];
    echo "<script>console.error('Database Error: " . addslashes($e->getMessage()) . "');</script>";
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Item Management System</title>
    <style>
        /* Styles remain identical to maintain UI consistency */
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
        .item-id { font-weight: 600; color: #93c5fd; }
        .item-name { font-weight: 500; }
        .item-type, .item-unit { color: #cbd5e1; font-size: 0.875rem; }
        .amount { color: #86efac; font-weight: 600; }
        .status-badge { display: inline-block; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; }
        .status-active { background: rgba(34, 197, 94, 0.2); color: #86efac; }
        .status-inactive { background: rgba(239, 68, 68, 0.2); color: #fca5a5; }
        .status-repair { background: rgba(251, 191, 36, 0.2); color: #fcd34d; }
        .status-other { background: rgba(148, 163, 184, 0.2); color: #cbd5e1; }
        .category-badge { display: inline-block; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; }
        .category-consumable { background: rgba(147, 51, 234, 0.2); color: #c084fc; }
        .category-nonconsumable { background: rgba(59, 130, 246, 0.2); color: #93c5fd; }
        .actions { display: flex; align-items: center; justify-content: center; gap: 0.5rem; }
        .action-btn { padding: 0.5rem; border: none; border-radius: 0.5rem; cursor: pointer; transition: all 0.2s; background: transparent; }
        .action-btn.view { color: #60a5fa; } .action-btn.view:hover { color: #93c5fd; background: rgba(59, 130, 246, 0.2); }
        .action-btn.edit { color: #a78bfa; } .action-btn.edit:hover { color: #c4b5fd; background: rgba(139, 92, 246, 0.2); }
        .action-btn.delete { color: #f87171; } .action-btn.delete:hover { color: #fca5a5; background: rgba(239, 68, 68, 0.2); }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.6); backdrop-filter: blur(4px); z-index: 1000; padding: 1rem; overflow-y: auto; }
        .modal.show { display: flex; align-items: center; justify-content: center; }
        .modal-content { background: #1e293b; border-radius: 0.75rem; width: 100%; max-width: 40rem; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); margin: 2rem 0; }
        .modal-header { padding: 1.5rem; border-bottom: 1px solid #475569; }
        .modal-header h2 { font-size: 1.5rem; font-weight: bold; }
        .modal-body { padding: 1.5rem; max-height: 60vh; overflow-y: auto; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem; }
        .form-group { display: flex; flex-direction: column; gap: 0.5rem; margin-bottom: 1rem; }
        .form-group label { color: #cbd5e1; font-size: 0.875rem; font-weight: 500; }
        .form-group input, .form-group textarea, .form-group select { padding: 0.75rem; background: #374151; border: 1px solid #4b5563; border-radius: 0.5rem; color: white; font-size: 1rem; }
        .form-group select option { background: #374151; color: white; }
        .form-group input:focus, .form-group textarea:focus, .form-group select:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1); }
        .modal-footer { padding: 1.5rem; border-top: 1px solid #475569; display: flex; justify-content: flex-end; gap: 0.75rem; }
        .btn-cancel { padding: 0.5rem 1.5rem; background: transparent; border: none; color: #cbd5e1; cursor: pointer; transition: color 0.2s; }
        .btn-cancel:hover { color: white; }
        .btn-save { padding: 0.5rem 1.5rem; background: linear-gradient(135deg, #2563eb, #9333ea); border: none; border-radius: 0.5rem; color: white; font-weight: 600; cursor: pointer; transition: all 0.2s; }
        .btn-save:hover { background: linear-gradient(135deg, #1d4ed8, #7c3aed); }
        .btn-save:disabled { opacity: 0.5; cursor: not-allowed; }
        .empty-state { text-align: center; padding: 3rem 1rem; display: none; }
        .empty-state h3 { font-size: 1.125rem; color: #94a3b8; margin-bottom: 0.5rem; }
        .empty-state p { color: #64748b; font-size: 0.875rem; }
        .icon { width: 18px; height: 18px; }
        .info-group { margin-bottom: 1.5rem; }
        .info-group h3 { font-size: 1rem; color: #93c5fd; margin-bottom: 1rem; font-weight: 600; }
        .info-group p { margin-bottom: 0.5rem; color: #cbd5e1; }
        .info-group p strong { color: #e2e8f0; margin-right: 0.5rem; }
        /* Alert Styles */
        .alert { padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem; display: none; }
        .alert.success { background: rgba(34, 197, 94, 0.2); border: 1px solid #22c55e; color: #86efac; }
        .alert.error { background: rgba(239, 68, 68, 0.2); border: 1px solid #ef4444; color: #fca5a5; }
        .loading { display: inline-block; width: 16px; height: 16px; border: 2px solid #ffffff; border-radius: 50%; border-top-color: transparent; animation: spin 0.6s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
        @media (max-width: 768px) { .header { flex-direction: column; gap: 1rem; text-align: center; } .header-info h1 { font-size: 2rem; } .table-container { overflow-x: auto; } .table { min-width: 900px; } .form-row { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="header-info">
                <h1>Item Management</h1>
                <p>Track and manage all inventory items</p>
            </div>
            <button class="add-btn" onclick="openAddModal()">
                <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                Add Item
            </button>
        </div>

        

        <div class="search-container">
            <svg class="search-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m21 21-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
            </svg>
            <input type="text" class="search-input" id="searchInput" placeholder="Search items by ID, name, type, or status..." onkeyup="filterTable()">
        </div>

        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Item ID</th>
                        <th>Item Name</th>
                        <th>Type / Unit</th>
                        <th>Unit Cost</th>
                        <th>Status</th>
                        <th>Category</th>
                        <th style="text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody id="item-table">
                    <?php 
                    $statusLabels = [0 => 'Active', 1 => 'Inactive', 2 => 'Under Repair', 3 => 'Others'];
                    $statusClasses = [0 => 'status-active', 1 => 'status-inactive', 2 => 'status-repair', 3 => 'status-other'];
                    $categoryLabels = [0 => 'Non-Consumable', 1 => 'Consumable'];
                    $categoryClasses = [0 => 'category-nonconsumable', 1 => 'category-consumable'];

                    foreach($items_data as $item): 
                    ?>
                    <tr data-item-id="<?php echo $item['ITEM_ID']; ?>"
                        data-item-name="<?php echo htmlspecialchars($item['ITEM_NAME']); ?>"
                        data-item-desc="<?php echo htmlspecialchars($item['ITEM_DESCRIPTION'] ?? ''); ?>"
                        data-item-type-id="<?php echo $item['ITEM_TYPE_ID']; ?>"
                        data-unit-id="<?php echo $item['UNIT_ID']; ?>"
                        data-unit-cost="<?php echo $item['UNIT_COST']; ?>"
                        data-item-status="<?php echo $item['ITEM_STATUS']; ?>"
                        data-item-category="<?php echo $item['ITEM_CATEGORY']; ?>"
                        data-status-report="<?php echo htmlspecialchars($item['STATUS_REPORT'] ?? ''); ?>"
                        data-type-name="<?php echo htmlspecialchars($item['ITEM_TYPE_NAME']); ?>"
                        data-unit-name="<?php echo htmlspecialchars($item['UNIT_NAME']); ?>"
                        data-created-at="<?php echo $item['CREATED_AT']; ?>">
                        <td>
                            <div class="item-id">ITEM-<?php echo str_pad($item['ITEM_ID'], 4, '0', STR_PAD_LEFT); ?></div>
                        </td>
                        <td>
                            <div class="item-name"><?php echo htmlspecialchars($item['ITEM_NAME']); ?></div>
                        </td>
                        <td>
                            <div class="item-type"><?php echo htmlspecialchars($item['ITEM_TYPE_NAME']); ?></div>
                            <div class="item-unit" style="font-size: 0.75rem; color: #94a3b8;"><?php echo htmlspecialchars($item['UNIT_NAME']); ?></div>
                        </td>
                        <td>
                            <div class="amount">₱<?php echo number_format($item['UNIT_COST'], 2); ?></div>
                        </td>
                        <td>
                            <span class="status-badge <?php echo $statusClasses[$item['ITEM_STATUS']]; ?>">
                                <?php echo $statusLabels[$item['ITEM_STATUS']]; ?>
                            </span>
                        </td>
                        <td>
                            <span class="category-badge <?php echo $categoryClasses[$item['ITEM_CATEGORY']]; ?>">
                                <?php echo $categoryLabels[$item['ITEM_CATEGORY']]; ?>
                            </span>
                        </td>
                        <td>
                            <div class="actions">
                                <button class="action-btn view" onclick="viewItem(this)" title="View Details">
                                    <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                    </svg>
                                </button>
                                <button class="action-btn edit" onclick="editItem(this)" title="Edit">
                                    <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                    </svg>
                                </button>
                                <button class="action-btn delete" onclick="deleteItem(this)" title="Delete">
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
                <h3>No items found</h3>
                <p>Try adjusting your search terms</p>
            </div>
        </div>
    </div>

    <div id="view-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Item Details</h2>
            </div>
            <div class="modal-body" id="view-modal-body">
                </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeViewModal()">Close</button>
            </div>
        </div>
    </div>

    <form id="deleteItemForm" method="POST" action="../process/deleteItem.php" style="display: none;">
        <input type="hidden" id="delete_item_id" name="item_id">
    </form>

    <script>
        // View Item
        function viewItem(button) {
            const row = button.closest('tr');
            const data = {
                item_id: row.dataset.itemId,
                item_name: row.dataset.itemName,
                item_description: row.dataset.itemDesc,
                item_type: row.dataset.typeName,
                unit: row.dataset.unitName,
                unit_cost: row.dataset.unitCost,
                item_status: row.dataset.itemStatus,
                item_category: row.dataset.itemCategory,
                status_report: row.dataset.statusReport,
                created_at: row.dataset.createdAt
            };
            
            displayItemDetails(data);
        }

        function displayItemDetails(data) {
            const statusLabels = {0: 'Active', 1: 'Inactive', 2: 'Under Repair', 3: 'Others'};
            const categoryLabels = {0: 'Non-Consumable', 1: 'Consumable'};
            
            const html = `
                <div class="info-group">
                    <h3>Basic Information</h3>
                    <p><strong>Item ID:</strong> ITEM-${String(data.item_id).padStart(4, '0')}</p>
                    <p><strong>Item Name:</strong> ${data.item_name}</p>
                    <p><strong>Description:</strong> ${data.item_description || 'N/A'}</p>
                    <p><strong>Created:</strong> ${data.created_at}</p>
                </div>
                <div class="info-group">
                    <h3>Classification</h3>
                    <p><strong>Type:</strong> ${data.item_type}</p>
                    <p><strong>Unit:</strong> ${data.unit}</p>
                    <p><strong>Unit Cost:</strong> ₱${parseFloat(data.unit_cost).toLocaleString('en-PH', {minimumFractionDigits: 2})}</p>
                    <p><strong>Category:</strong> ${categoryLabels[data.item_category]}</p>
                </div>
                <div class="info-group">
                    <h3>Status</h3>
                    <p><strong>Status:</strong> ${statusLabels[data.item_status]}</p>
                    <p><strong>Status Report:</strong> ${data.status_report || 'N/A'}</p>
                </div>
            `;
            
            document.getElementById('view-modal-body').innerHTML = html;
            document.getElementById('view-modal').classList.add('show');
        }

        // Delete Item
        function deleteItem(button) {
            const row = button.closest('tr');
            const itemId = row.dataset.itemId;
            const itemName = row.dataset.itemName;
            
            if (confirm(`Are you sure you want to delete "${itemName}"? This action cannot be undone.`)) {
                document.getElementById('delete_item_id').value = itemId;
                document.getElementById('deleteItemForm').submit();
            }
        }

        // Show alert message
        function showAlert(message, type) {
            const alert = document.getElementById('modal-alert');
            alert.textContent = message;
            alert.className = 'alert ' + type;
            alert.style.display = 'block';
        }

        // Hide alert message
        function hideAlert() {
            const alert = document.getElementById('modal-alert');
            alert.style.display = 'none';
        }

        function closeViewModal() {
            document.getElementById('view-modal').classList.remove('show');
        }

        document.getElementById('view-modal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeViewModal();
            }
        });

        // Filter table
        function filterTable() {
            const searchValue = document.getElementById('searchInput').value.toLowerCase();
            const table = document.getElementById('item-table');
            const rows = table.getElementsByTagName('tr');
            let visibleCount = 0;

            for (let i = 0; i < rows.length; i++) {
                const row = rows[i];
                const text = row.textContent.toLowerCase();
                
                if (text.includes(searchValue)) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            }

            checkEmptyState();
        }

        // Check if table is empty
        function checkEmptyState() {
            const table = document.getElementById('item-table');
            const rows = table.getElementsByTagName('tr');
            let visibleCount = 0;

            for (let i = 0; i < rows.length; i++) {
                if (rows[i].style.display !== 'none') {
                    visibleCount++;
                }
            }

            const emptyState = document.getElementById('empty-state');
            if (visibleCount === 0) {
                emptyState.style.display = 'block';
            } else {
                emptyState.style.display = 'none';
            }
        }

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            checkEmptyState();
        });
    </script>
</body>
</html>