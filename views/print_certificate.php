<?php
// views/print_certificate.php
require_once '../config/Connection.php';

$id = $_GET['id'] ?? 0;


// Fetch Full Details
$stmt = $conn->prepare("
    SELECT 
        a.*, 
        b.BREED_NAME, 
        t.ANIMAL_TYPE_NAME,
        m.TAG_NO as MOTHER_TAG, m.BREED_ID as M_BREED_ID, mb.BREED_NAME as MOTHER_BREED,
        f.TAG_NO as FATHER_TAG, f.BREED_ID as F_BREED_ID, fb.BREED_NAME as FATHER_BREED
    FROM animal_records a
    LEFT JOIN breeds b ON a.BREED_ID = b.BREED_ID
    LEFT JOIN animal_type t ON a.ANIMAL_TYPE_ID = t.ANIMAL_TYPE_ID
    LEFT JOIN animal_records m ON a.MOTHER_ID = m.ANIMAL_ID
    LEFT JOIN breeds mb ON m.BREED_ID = mb.BREED_ID
    LEFT JOIN animal_records f ON a.FATHER_ID = f.ANIMAL_ID
    LEFT JOIN breeds fb ON f.BREED_ID = fb.BREED_ID
    WHERE a.ANIMAL_ID = ?
");
$stmt->execute([$id]);
$animal = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$animal) die("Animal record not found.");

// Format Birth Date
$bday = date_create($animal['BIRTH_DATE']);
$formatted_date = date_format($bday, "F j, Y");

// Get Current Date for "Date Issued"
$date_issued = date("F j, Y");

$manager_name = "Glenn R. Tajanlangit";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Certificate - <?= $animal['TAG_NO'] ?></title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Roboto:wght@300;400;700&display=swap');
        @import url('https://fonts.googleapis.com/css2?family=Great+Vibes&display=swap'); /* For signature style */

        body { 
            background: #525659; 
            margin: 0; 
            padding: 20px; 
            display: flex; 
            justify-content: center; 
            font-family: 'Roboto', sans-serif;
        }

        .page {
            background: white;
            width: 297mm; /* A4 Landscape */
            height: 210mm;
            padding: 10mm;
            box-shadow: 0 0 10px rgba(0,0,0,0.5);
            position: relative;
            box-sizing: border-box;
        }

        .border-container {
            border: 5px double #1e293b;
            height: 100%;
            padding: 10mm;
            box-sizing: border-box;
            position: relative;
            background: #fff url('https://www.transparenttextures.com/patterns/cream-paper.png'); /* Subtle texture */
        }

        /* CORNER DECORATIONS */
        .corner {
            position: absolute;
            width: 50px;
            height: 50px;
            border-color: #f59e0b; /* Gold */
            border-style: solid;
        }
        .tl { top: 20px; left: 20px; border-width: 5px 0 0 5px; }
        .tr { top: 20px; right: 20px; border-width: 5px 5px 0 0; }
        .bl { bottom: 20px; left: 20px; border-width: 0 0 5px 5px; }
        .br { bottom: 20px; right: 20px; border-width: 0 5px 5px 0; }

        /* HEADER */
        .header { text-align: center; margin-bottom: 30px; }
        .farm-name { font-size: 1.5rem; color: #64748b; letter-spacing: 2px; text-transform: uppercase; }
        .cert-title { 
            font-family: 'Playfair Display', serif; 
            font-size: 4rem; 
            color: #1e293b; 
            margin: 10px 0 20px 0; 
            font-weight: 700;
        }
        .cert-subtitle { font-size: 1.2rem; font-style: italic; color: #475569; }

        /* CONTENT GRID */
        .content-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            margin-top: 30px;
        }

        .animal-info {
            border-right: 2px solid #e2e8f0;
            padding-right: 40px;
        }

        .row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            border-bottom: 1px dotted #cbd5e1;
            padding-bottom: 5px;
        }
        .label { font-weight: bold; color: #475569; text-transform: uppercase; font-size: 0.9rem; }
        .value { font-family: 'Playfair Display', serif; font-size: 1.2rem; font-weight: bold; color: #0f172a; }

        /* LINEAGE SECTION */
        .lineage-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 20px;
            border-radius: 8px;
        }
        .lineage-title {
            text-align: center;
            font-weight: bold;
            color: #f59e0b;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 15px;
            border-bottom: 2px solid #f59e0b;
            display: inline-block;
            padding-bottom: 5px;
        }
        
        .parent-group { margin-bottom: 20px; }
        .parent-label { font-size: 0.85rem; color: #94a3b8; text-transform: uppercase; }
        .parent-name { font-size: 1.3rem; font-weight: bold; }
        .parent-breed { font-style: italic; color: #64748b; }

        /* FOOTER */
        .footer {
            position: absolute;
            bottom: 40px;
            left: 0; 
            width: 100%;
            padding: 0 50px;
            box-sizing: border-box;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
        }

        /* Signature Box Styling */
        .signature-box {
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .signed-name {
            font-family: 'Great Vibes', cursive; /* Handwritten style optional, or use standard */
            font-family: 'Playfair Display', serif; /* Or keeping formal */
            font-size: 1.4rem;
            margin-bottom: 5px;
            color: #0f172a;
            font-weight: bold;
        }

        .signature-line {
            width: 250px;
            border-top: 2px solid #1e293b;
            text-align: center;
            padding-top: 10px;
            font-weight: bold;
            color: #64748b;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 1px;
        }

        /* PRINT CONFIG */
        @media print {
            body { background: white; margin: 0; padding: 0; }
            .page { box-shadow: none; width: 100%; height: 100%; }
            .no-print { display: none; }
            @page { size: landscape; margin: 0; }
        }

        .seal {
            position: absolute;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%);
            width: 100px;
            height: 100px;
            border: 3px solid #f59e0b;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #f59e0b;
            font-weight: bold;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            opacity: 0.8;
        }
    </style>
</head>
<body>

    <div class="no-print" style="position: fixed; top: 20px; right: 20px;">
        <button onclick="window.print()" style="padding: 15px 30px; background: #3b82f6; color: white; border: none; font-size: 1.2rem; cursor: pointer; border-radius: 8px; font-weight: bold;">🖨️ Print Certificate</button>
    </div>

    <div class="page">
        <div class="border-container">
            <div class="corner tl"></div>
            <div class="corner tr"></div>
            <div class="corner bl"></div>
            <div class="corner br"></div>

            <div class="header">
                <div class="farm-name">FarmPro Management System</div>
                <div class="cert-title">Certificate of Birth</div>
                <div class="cert-subtitle">This is to certify that the animal described below has been duly recorded in our registry.</div>
            </div>

            <div class="content-grid">
                <div class="animal-info">
                    <div class="row">
                        <span class="label">Official Tag No</span>
                        <span class="value" style="font-size: 1.8rem; color: #2563eb;"><?= $animal['TAG_NO'] ?></span>
                    </div>
                    <div class="row">
                        <span class="label">Breed</span>
                        <span class="value"><?= $animal['BREED_NAME'] ?></span>
                    </div>
                    <div class="row">
                        <span class="label">Animal Type</span>
                        <span class="value"><?= $animal['ANIMAL_TYPE_NAME'] ?></span>
                    </div>
                    <div class="row">
                        <span class="label">Sex</span>
                        <span class="value"><?= $animal['SEX'] === 'M' ? 'Male' : ($animal['SEX'] === 'F' ? 'Female' : 'Unknown') ?></span>
                    </div>
                    <div class="row">
                        <span class="label">Date of Birth</span>
                        <span class="value"><?= $formatted_date ?></span>
                    </div>
                    <div class="row">
                        <span class="label">Birth Weight</span>
                        <span class="value"><?= $animal['WEIGHT_AT_BIRTH'] ? $animal['WEIGHT_AT_BIRTH'].' kg' : 'N/A' ?></span>
                    </div>
                </div>

                <div class="lineage-box">
                    <div style="text-align: center;"><span class="lineage-title">Parentage Record</span></div>
                    
                    <div class="parent-group">
                        <div class="parent-label">Sire (Father)</div>
                        <div class="parent-name" style="color: #3b82f6;"><?= $animal['FATHER_TAG'] ?: 'Unknown / External' ?></div>
                        <div class="parent-breed"><?= $animal['FATHER_BREED'] ?: '' ?></div>
                    </div>

                    <div style="border-top: 1px solid #e2e8f0; margin: 15px 0;"></div>

                    <div class="parent-group">
                        <div class="parent-label">Dam (Mother)</div>
                        <div class="parent-name" style="color: #ec4899;"><?= $animal['MOTHER_TAG'] ?: 'Unknown / External' ?></div>
                        <div class="parent-breed"><?= $animal['MOTHER_BREED'] ?: '' ?></div>
                    </div>
                </div>
            </div>

            <div class="seal">
                Official<br>Seal
            </div>

            <div class="footer">
                <div class="signature-box">
                    <div class="signed-name"><?php echo $manager_name; ?></div>
                    <div class="signature-line">
                        Farm Manager
                    </div>
                </div>
                
                <div class="signature-box">
                    <div class="signed-name"><?= $date_issued ?></div>
                    <div class="signature-line">
                        Date Issued
                    </div>
                </div>
            </div>

        </div>
    </div>

</body>
</html>