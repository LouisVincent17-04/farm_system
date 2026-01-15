<?php

include '../config/Connection.php';
include '../config/Queries.php';

// SQL Query: We join ITEM_TYPES and UNITS to show names instead of just IDs
$sql = "SELECT 
            i.ITEM_ID,
            i.ITEM_NAME,
            it.ITEM_TYPE_NAME,
            u.UNIT_ABBR,
            i.UNIT_COST,
            i.QUANTITY,
            i.TOTAL_COST,
            i.DATE_OF_PURCHASE,
            i.ITEM_DESCRIPTION
        FROM ITEMS i
        LEFT JOIN ITEM_TYPES it ON i.ITEM_TYPE_ID = it.ITEM_TYPE_ID
        LEFT JOIN UNITS u ON i.UNIT_ID = u.UNIT_ID
        ORDER BY i.ITEM_ID DESC";

// Fetch data
$items = retrieveData($conn, $sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Inventory Items</title>
    <style>
        table { width: 100%; border-collapse: collapse; font-family: Arial, sans-serif; }
        th, td { padding: 10px; border: 1px solid #ddd; text-align: left; }
        th { background-color: #f4f4f4; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        .money { text-align: right; }
    </style>
</head>
<body>

    <h2>📦 Farm Inventory Items</h2>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Item Name</th>
                <th>Type</th>
                <th>Description</th>
                <th>Purchased Date</th>
                <th>Qty</th>
                <th>Unit</th>
                <th class="money">Unit Cost</th>
                <th class="money">Total Cost</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($items)): ?>
                <?php foreach ($items as $row): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['ITEM_ID']); ?></td>
                        <td><strong><?php echo htmlspecialchars($row['ITEM_NAME']); ?></strong></td>
                        <td>
                            <span style="background:#eee; padding:2px 5px; border-radius:4px; font-size:0.9em;">
                                <?php echo htmlspecialchars($row['ITEM_TYPE_NAME']); ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($row['ITEM_DESCRIPTION']); ?></td>
                        <td><?php echo htmlspecialchars($row['DATE_OF_PURCHASE']); ?></td>
                        <td><?php echo htmlspecialchars($row['QUANTITY']); ?></td>
                        <td><?php echo htmlspecialchars($row['UNIT_ABBR']); ?></td>
                        
                        <td class="money">
                            <?php echo number_format($row['UNIT_COST'], 2); ?>
                        </td>
                        <td class="money" style="font-weight:bold;">
                            <?php echo number_format($row['TOTAL_COST'], 2); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="9" style="text-align:center;">No items found in inventory.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

</body>
</html>