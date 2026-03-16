<?php
// views/print_batch_sales_receipt.php
include '../config/Connection.php';

if (!isset($_GET['batch_id'])) {
    die("Batch ID required");
}

$batch_id = $_GET['batch_id'];

// Directly query the new batch_id column instead of using LIKE on the notes
$sql = "SELECT s.*, a.TAG_NO 
        FROM animal_sales s 
        JOIN animal_records a ON s.animal_id = a.ANIMAL_ID 
        WHERE s.batch_id = ?";
        
$stmt = $conn->prepare($sql);
$stmt->execute([$batch_id]);
$sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($sales)) {
    die("No sales found for this batch.");
}

$first = $sales[0];
$total_amount = array_sum(array_column($sales, 'final_sale_price'));
$total_weight = array_sum(array_column($sales, 'weight_at_sale'));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Batch Receipt #<?= htmlspecialchars($batch_id) ?></title>
    <style>
        body { 
            font-family: 'Courier New', Courier, monospace; 
            padding: 20px; 
            color: #000; 
            background: #f1f5f9; 
        }
        .receipt-box { 
            max-width: 400px; 
            margin: 0 auto; 
            background: #fff; 
            border: 1px solid #ccc; 
            padding: 30px; 
            box-shadow: 0 4px 6px rgba(0,0,0,0.1); 
        }
        .header { 
            text-align: center; 
            margin-bottom: 20px; 
        }
        .header h1 { 
            margin: 0 0 5px 0; 
            font-size: 1.8rem; 
            text-transform: uppercase; 
        }
        .header p { 
            margin: 3px 0; 
            font-size: 0.9rem; 
        }
        .divider { 
            border-bottom: 1px dashed #000; 
            margin: 15px 0; 
        }
        table { 
            width: 100%; 
            text-align: left; 
            font-size: 0.9rem; 
            border-collapse: collapse; 
        }
        th { 
            padding-bottom: 8px; 
            border-bottom: 1px solid #000; 
        }
        td { 
            padding: 6px 0; 
        }
        .row { 
            display: flex; 
            justify-content: space-between; 
            font-size: 0.95rem; 
            margin-bottom: 5px; 
        }
        .total { 
            display: flex; 
            justify-content: space-between; 
            font-weight: bold; 
            font-size: 1.2rem; 
            margin-top: 15px; 
            border-top: 2px solid #000; 
            padding-top: 10px; 
        }
        .footer-text { 
            text-align: center; 
            font-size: 0.8rem; 
            margin-top: 20px; 
            color: #555; 
        }
        .print-btn { 
            display: block; 
            margin: 20px auto; 
            padding: 10px 20px; 
            background: #3b82f6; 
            color: white; 
            border: none; 
            border-radius: 6px; 
            cursor: pointer; 
            font-weight: bold; 
            font-size: 1rem; 
        }
        .print-btn:hover { background: #2563eb; }
        
        @media print { 
            body { background: #fff; padding: 0; }
            .receipt-box { border: none; box-shadow: none; max-width: 100%; padding: 10px; }
            .print-btn { display: none; } 
        }
    </style>
</head>
<body>

<div class="receipt-box">
    <div class="header">
        <h1>FarmPro Inc.</h1>
        <p>Official Batch Sales Receipt</p>
        <div class="divider"></div>
        <p style="text-align:left;"><strong>Date:</strong> <?= date('M d, Y h:i A') ?></p>
        <p style="text-align:left;"><strong>Batch Ref:</strong> <?= htmlspecialchars($batch_id) ?></p>
        <p style="text-align:left;"><strong>Customer:</strong> <?= htmlspecialchars($first['customer_name']) ?></p>
    </div>

    <table>
        <thead>
            <tr>
                <th>Tag No</th>
                <th>Wt (kg)</th>
                <th style="text-align:right">Price</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($sales as $s): ?>
            <tr>
                <td><?= htmlspecialchars($s['TAG_NO']) ?></td>
                <td><?= number_format($s['weight_at_sale'], 2) ?></td>
                <td style="text-align:right">₱<?= number_format($s['final_sale_price'], 2) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="divider"></div>

    <div class="row">
        <span>Total Heads:</span> 
        <span><?= count($sales) ?></span>
    </div>
    <div class="row">
        <span>Total Weight:</span> 
        <span><?= number_format($total_weight, 2) ?> kg</span>
    </div>
    <div class="total">
        <span>GRAND TOTAL:</span> 
        <span>₱<?= number_format($total_amount, 2) ?></span>
    </div>

    <div class="footer-text">
        <p>Thank you for your business!</p>
        <p>System Generated Receipt</p>
    </div>
</div>

<button id="printBtn" class="print-btn" onclick="window.print()">🖨️ Print Receipt</button>

<script>
    window.onload = function() { 
        // Slight delay ensures the page fully renders before the print dialog opens
        setTimeout(() => { window.print(); }, 500); 
    };
</script>

</body>
</html>