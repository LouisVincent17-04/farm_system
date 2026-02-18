<?php
// views/print_sales_receipt.php
include '../config/Connection.php';

if (!isset($_GET['sale_id'])) die("Sale ID required");

$sale_id = $_GET['sale_id'];

// Fetch Sale Details
$sql = "SELECT s.*, a.TAG_NO, l.LOCATION_NAME, b.BUILDING_NAME, p.PEN_NAME 
        FROM animal_sales s
        JOIN animal_records a ON s.animal_id = a.ANIMAL_ID
        LEFT JOIN locations l ON a.LOCATION_ID = l.LOCATION_ID
        LEFT JOIN buildings b ON a.BUILDING_ID = b.BUILDING_ID
        LEFT JOIN pens p ON a.PEN_ID = p.PEN_ID
        WHERE s.sale_id = ?";
$stmt = $conn->prepare($sql);
$stmt->execute([$sale_id]);
$sale = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$sale) die("Sale not found");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Sales Receipt #<?= $sale_id ?></title>
    <style>
        body { font-family: 'Courier New', monospace; padding: 20px; color: #000; }
        .receipt-box { max-width: 400px; margin: 0 auto; border: 1px dashed #000; padding: 20px; }
        .header { text-align: center; margin-bottom: 20px; }
        .header h1 { margin: 0; font-size: 1.5rem; }
        .header p { margin: 2px 0; font-size: 0.9rem; }
        .divider { border-bottom: 1px dashed #000; margin: 10px 0; }
        .row { display: flex; justify-content: space-between; margin: 5px 0; }
        .total { font-weight: bold; font-size: 1.2rem; margin-top: 10px; }
        .footer { text-align: center; margin-top: 20px; font-size: 0.8rem; }
        @media print {
            body { margin: 0; padding: 0; }
            .receipt-box { border: none; }
            #printBtn { display: none; }
        }
    </style>
</head>
<body>

<div class="receipt-box">
    <div class="header">
        <h1>FarmPro Inc.</h1>
        <p>Official Sales Receipt</p>
        <p>Date: <?= date('M d, Y h:i A', strtotime($sale['sale_date'])) ?></p>
        <p>Receipt #: <?= str_pad($sale_id, 6, '0', STR_PAD_LEFT) ?></p>
    </div>

    <div class="divider"></div>

    <div class="row"><span>Customer:</span> <span><?= htmlspecialchars($sale['customer_name']) ?></span></div>
    <div class="row"><span>Animal Tag:</span> <span><?= $sale['TAG_NO'] ?></span></div>
    <div class="row"><span>Origin:</span> <span><?= $sale['LOCATION_NAME'] ?></span></div>

    <div class="divider"></div>

    <div class="row"><span>Weight:</span> <span><?= number_format($sale['weight_at_sale'], 2) ?> kg</span></div>
    <div class="row"><span>Price/KG:</span> <span><?= $sale['price_per_kg'] > 0 ? number_format($sale['price_per_kg'], 2) : 'N/A' ?></span></div>
    
    <div class="divider"></div>

    <div class="row total"><span>TOTAL PAID:</span> <span>₱<?= number_format($sale['final_sale_price'], 2) ?></span></div>

    <div class="divider"></div>
    <div class="footer">
        <p>Thank you for your business!</p>
        <p>System Generated Receipt</p>
    </div>
</div>

<button id="printBtn" onclick="window.print()" style="display:block; margin:20px auto; padding:10px 20px; cursor:pointer;">Print Receipt</button>

<script>
    // Auto print on load
    window.onload = function() { window.print(); }
</script>

</body>
</html>