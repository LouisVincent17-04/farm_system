<?php
// views/print_batch_sales_receipt.php
include '../config/Connection.php';

if (!isset($_GET['batch_id'])) die("Batch ID required");

$batch_id = $_GET['batch_id'];

// Fetch Sales in Batch
$sql = "SELECT s.*, a.TAG_NO 
        FROM animal_sales s
        JOIN animal_records a ON s.animal_id = a.ANIMAL_ID
        WHERE s.notes LIKE ?"; // Assuming you stored batch ID in notes or have a batch column. 
                               // Ideally, add a `batch_id` column to `animal_sales` table.
                               // For now, let's assume we pass the IDs via GET or query by timestamp.
                               // BETTER APPROACH: Add `batch_transaction_id` to animal_sales.

// Fallback logic if you haven't added a batch_id column to animal_sales:
// Use the `notes` field to store "Batch: {UNIQUE_ID}" and query by that.
$batch_tag = "%Batch: $batch_id%";
$stmt = $conn->prepare("SELECT s.*, a.TAG_NO FROM animal_sales s JOIN animal_records a ON s.animal_id = a.ANIMAL_ID WHERE s.notes LIKE ?");
$stmt->execute([$batch_tag]);
$sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($sales)) die("No sales found for this batch.");

$first = $sales[0];
$total_amount = array_sum(array_column($sales, 'final_sale_price'));
$total_weight = array_sum(array_column($sales, 'weight_at_sale'));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Batch Receipt #<?= $batch_id ?></title>
    <style>
        body { font-family: 'Courier New', monospace; padding: 20px; color: #000; }
        .receipt-box { max-width: 600px; margin: 0 auto; border: 1px dashed #000; padding: 20px; }
        .header { text-align: center; margin-bottom: 20px; }
        .header h1 { margin: 0; font-size: 1.5rem; }
        .divider { border-bottom: 1px dashed #000; margin: 10px 0; }
        table { width: 100%; text-align: left; font-size: 0.9rem; }
        .total { font-weight: bold; font-size: 1.2rem; margin-top: 10px; text-align: right; }
        @media print { #printBtn { display: none; } .receipt-box { border: none; } }
    </style>
</head>
<body>

<div class="receipt-box">
    <div class="header">
        <h1>FarmPro Inc.</h1>
        <p>Batch Sales Receipt</p>
        <p>Date: <?= date('M d, Y h:i A') ?></p>
        <p>Batch Ref: <?= $batch_id ?></p>
        <p>Customer: <?= htmlspecialchars($first['customer_name']) ?></p>
    </div>

    <div class="divider"></div>

    <table>
        <thead>
            <tr><th>Tag No</th><th>Weight (kg)</th><th style="text-align:right">Price</th></tr>
        </thead>
        <tbody>
            <?php foreach($sales as $s): ?>
            <tr>
                <td><?= $s['TAG_NO'] ?></td>
                <td><?= $s['weight_at_sale'] ?></td>
                <td style="text-align:right">₱<?= number_format($s['final_sale_price'], 2) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="divider"></div>

    <div class="row"><span>Total Heads:</span> <span><?= count($sales) ?></span></div>
    <div class="row"><span>Total Weight:</span> <span><?= number_format($total_weight, 2) ?> kg</span></div>
    <div class="total">GRAND TOTAL: ₱<?= number_format($total_amount, 2) ?></div>

    <div class="divider"></div>
    <div style="text-align:center; font-size:0.8rem;">System Generated Receipt</div>
</div>

<button id="printBtn" onclick="window.print()" style="display:block; margin:20px auto;">Print Receipt</button>
<script>window.onload = function() { window.print(); }</script>

</body>
</html>