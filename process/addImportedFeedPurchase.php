<?php
// process/addImportedFeedPurchase.php
session_start();
error_reporting(0);
ini_set('display_errors', 0);

include '../config/Connection.php';
header('Content-Type: application/json');

// --- 1. SESSION & CONTEXT ---
$user_id    = $_SESSION['user']['USER_ID'] ?? null;
$username   = $_SESSION['user']['FULL_NAME'] ?? 'System';
$ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['csv_file'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request or no CSV file uploaded.']);
    exit;
}

$file = $_FILES['csv_file'];
if ($file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'File upload error code: ' . $file['error']]);
    exit;
}

$handle = fopen($file['tmp_name'], "r");
if (!$handle) {
    echo json_encode(['success' => false, 'message' => 'Cannot open the uploaded CSV file.']);
    exit;
}

// --- 2. HEADER PARSING ---
$header = fgetcsv($handle);
if (!$header) {
    echo json_encode(['success' => false, 'message' => 'CSV file appears to be empty.']);
    exit;
}

// Clean headers (remove BOM, invisible chars, trim, and convert to uppercase)
$header = array_map(function($val) {
    return strtoupper(trim(preg_replace('/[\x00-\x1F\x7F\xA0]/u', '', $val)));
}, $header);

// Map column indexes based on header names
$colMap = [];
foreach ($header as $index => $colName) {
    $colMap[$colName] = $index;
}

// The exact required columns based on user request
$expected_req_cols = [
    'LOCATION',
    'REF NO',
    'SUPPLIER',
    'DELIVERY DATE',
    'FEED TYPE',
    'NET WEIGHT',
    'QTY',
    'PRICE'
];

// Verify all required columns exist (ignoring trailing spaces in user's CSV)
foreach ($expected_req_cols as $req) {
    $found = false;
    foreach (array_keys($colMap) as $csvCol) {
        if (trim($csvCol) === $req) {
            $found = true;
            // Normalize the key to the exact expected name so we can reliably fetch it
            $colMap[$req] = $colMap[$csvCol]; 
            break;
        }
    }
    if (!$found) {
        echo json_encode(['success' => false, 'message' => "Missing required column in CSV header: '$req'"]);
        fclose($handle);
        exit;
    }
}

// --- 3. PREPARE LOOKUPS & PREDEFINED DATA ---
$ITEM_TYPE_ID = 2; // Predefined for Feeds
$UNIT_ID = 3;      // Predefined Unit
$ITEM_CATEGORY = 1;// Predefined Category (Consumable)

$stmtLoc = $conn->prepare("SELECT LOCATION_ID FROM locations WHERE LOCATION_NAME = ?");
$cache = ['loc' => []];

$errors = [];
$validRows = [];
$rowNum = 1;

// --- 4. PARSE & VALIDATE ROWS ---
while (($row = fgetcsv($handle)) !== FALSE) {
    $rowNum++;
    if(empty(array_filter($row))) continue; // Skip empty rows

    $getVal = function($colName) use ($row, $colMap) {
        $idx = $colMap[$colName] ?? -1;
        return ($idx >= 0 && isset($row[$idx])) ? trim($row[$idx]) : null;
    };

    // Extract values
    $location       = $getVal('LOCATION');
    $reference_no   = $getVal('REF NO');
    $supplier       = $getVal('SUPPLIER');
    $delivery_date  = $getVal('DELIVERY DATE');
    $feed_type      = $getVal('FEED TYPE');
    $net_weight_raw = $getVal('NET WEIGHT');
    $qty_raw        = $getVal('QTY');
    $price_raw      = $getVal('PRICE');

    // Validations
    if (!$location)      { $errors[] = "Row $rowNum: Location is missing."; continue; }
    if (!$delivery_date) { $errors[] = "Row $rowNum: Delivery Date is missing."; continue; }
    if (!$feed_type)     { $errors[] = "Row $rowNum: Feed Type is missing."; continue; }
    
    // Formatting & Casting
    $net_weight = is_numeric($net_weight_raw) ? (float)$net_weight_raw : 0;
    $quantity   = is_numeric($qty_raw) ? (float)$qty_raw : 0;
    $unit_cost  = is_numeric($price_raw) ? (float)$price_raw : 0;

    if ($quantity <= 0)  { $errors[] = "Row $rowNum: QTY must be greater than 0."; continue; }
    if ($unit_cost <= 0) { $errors[] = "Row $rowNum: Price must be greater than 0."; continue; }

    // --- DATE FORMATTING FIX ---
    // Convert dashes to slashes to ensure PHP reads it strictly as MM/DD/YYYY (American format)
    $normalized_date = str_replace('-', '/', $delivery_date);
    $timestamp = strtotime($normalized_date);
    
    if (!$timestamp) {
        $errors[] = "Row $rowNum: Invalid Delivery Date format ('$delivery_date'). Expected MM/DD/YYYY or MM-DD-YYYY."; 
        continue;
    }
    
    // Convert to database-friendly YYYY-MM-DD
    $purch_date_fmt = date('Y-m-d', $timestamp);
    // ---------------------------

    // Location Lookup
    $loc_id = null;
    if (isset($cache['loc'][$location])) {
        $loc_id = $cache['loc'][$location];
    } else {
        $stmtLoc->execute([$location]);
        $loc_id = $stmtLoc->fetchColumn();
        if ($loc_id) $cache['loc'][$location] = $loc_id;
    }
    
    if (!$loc_id) { 
        $errors[] = "Row $rowNum: Location '$location' not found in the system."; continue; 
    }

    // Queue valid row
    $validRows[] = [
        'ITEM_NAME'        => $feed_type,
        'ITEM_TYPE_ID'     => $ITEM_TYPE_ID,
        'UNIT_ID'          => $UNIT_ID,
        'ITEM_CATEGORY'    => $ITEM_CATEGORY,
        'UNIT_COST'        => $unit_cost,
        'QUANTITY'         => $quantity,
        'ITEM_NET_WEIGHT'  => $net_weight,
        'TOTAL_COST'       => ($unit_cost * $quantity),
        'DATE_OF_PURCHASE' => $purch_date_fmt,
        'LOCATION_ID'      => $loc_id,
        'REFERENCE_NO'     => $reference_no ?: null,
        'SUPPLIER'         => $supplier ?: null,
        'CREATED_BY'       => $user_id
    ];
}
fclose($handle);

// --- 5. EXECUTION & RESPONSES ---

// Atomic failure: if any rows are bad, reject the whole file
if (count($errors) > 0) {
    echo json_encode([
        'success' => false, 
        'message' => 'Validation failed. Fix the issues below and re-upload the file.',
        'errors'  => array_slice($errors, 0, 50) 
    ]);
    exit;
}

if (count($validRows) === 0) {
    echo json_encode(['success' => false, 'message' => 'No valid data rows found in the CSV.']);
    exit;
}

try {
    $conn->beginTransaction();

    $insertSql = "INSERT INTO items (
        ITEM_NAME, ITEM_TYPE_ID, UNIT_ID, ITEM_CATEGORY, 
        UNIT_COST, QUANTITY, ITEM_NET_WEIGHT, TOTAL_COST, 
        DATE_OF_PURCHASE, LOCATION_ID, REFERENCE_NO, SUPPLIER, 
        STATUS, CREATED_AT
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, NOW())"; // Status 0 = Pending verification
    
    $insertStmt = $conn->prepare($insertSql);

    foreach ($validRows as $r) {
        $insertStmt->execute([
            $r['ITEM_NAME'], 
            $r['ITEM_TYPE_ID'], 
            $r['UNIT_ID'], 
            $r['ITEM_CATEGORY'], 
            $r['UNIT_COST'], 
            $r['QUANTITY'], 
            $r['ITEM_NET_WEIGHT'], 
            $r['TOTAL_COST'],
            $r['DATE_OF_PURCHASE'], 
            $r['LOCATION_ID'], 
            $r['REFERENCE_NO'], 
            $r['SUPPLIER']
        ]);
    }

    // --- Audit Log ---
    $count = count($validRows);
    $log_sql = "INSERT INTO audit_logs (USER_ID, USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS) 
                VALUES (?, ?, 'IMPORT_CSV', 'ITEMS', ?, ?)";
    $log_stmt = $conn->prepare($log_sql);
    $log_stmt->execute([
        $user_id, 
        $username, 
        "Bulk imported $count feed purchase records via CSV.", 
        $ip_address
    ]);

    $conn->commit();
    echo json_encode(['success' => true, 'message' => "Successfully queued $count feed purchases!"]);

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Database Insertion Failed: ' . $e->getMessage()]);
}
?>