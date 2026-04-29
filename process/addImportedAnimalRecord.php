<?php
// process/addImportedAnimalRecord.php
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

// --- 2. FILE TYPE VALIDATION ---
// Check magic bytes to catch Excel files (.xlsx) disguised as .csv
// .xlsx files are ZIP archives and always start with PK\x03\x04
$fh    = fopen($file['tmp_name'], 'rb');
$magic = fread($fh, 4);
fclose($fh);

if ($magic === "PK\x03\x04") {
    echo json_encode([
        'success' => false,
        'message' => 'The file you uploaded is an Excel (.xlsx) file, not a real CSV. '
                   . 'To fix this: open the file in Excel → File → Save As → select '
                   . '"CSV UTF-8 (Comma delimited) (*.csv)" → then upload that saved file.'
    ]);
    exit;
}

// Also block old .xls (Excel 97-2003) — magic bytes D0 CF 11 E0
if (substr($magic, 0, 2) === "\xD0\xCF") {
    echo json_encode([
        'success' => false,
        'message' => 'The file you uploaded is an old Excel (.xls) file, not a real CSV. '
                   . 'To fix this: open the file in Excel → File → Save As → select '
                   . '"CSV UTF-8 (Comma delimited) (*.csv)" → then upload that saved file.'
    ]);
    exit;
}

// Final MIME type check as a second layer
$detectedMime  = mime_content_type($file['tmp_name']);
$allowedMimes  = ['text/plain', 'text/csv', 'application/csv', 'application/octet-stream'];
if (!in_array($detectedMime, $allowedMimes)) {
    echo json_encode([
        'success' => false,
        'message' => "Invalid file type detected ($detectedMime). Please upload a plain CSV file (.csv), "
                   . "not an Excel or other Office document."
    ]);
    exit;
}

// --- 3. OPEN FILE ---
$handle = fopen($file['tmp_name'], "r");
if (!$handle) {
    echo json_encode(['success' => false, 'message' => 'Cannot open the uploaded CSV file.']);
    exit;
}

// --- 4. HEADER PARSING ---
$header = fgetcsv($handle);
if (!$header) {
    echo json_encode(['success' => false, 'message' => 'CSV file appears to be empty.']);
    exit;
}

// Clean headers (remove BOM, invisible chars, and trim)
$header = array_map(function($val) {
    return trim(preg_replace('/[\x00-\x1F\x7F\xA0]/u', '', $val));
}, $header);

// The strictly requested columns (case-insensitive checking later)
$expected_req_cols = [
    'Tag_Number', 
    'Animal_Type', 
    'Breed', 
    'Birth_Date', 
    'Sex', 
    'Location', 
    'Building', 
    'Pen', 
    'Is_Purchased'
];

$colMap = [];
foreach ($header as $index => $colName) {
    $colMap[strtolower($colName)] = $index;
}

// Ensure all strictly required columns are present in the header
foreach ($expected_req_cols as $req) {
    if (!isset($colMap[strtolower($req)])) {
        echo json_encode(['success' => false, 'message' => "Missing required column in CSV header: '$req'"]);
        fclose($handle);
        exit;
    }
}

// --- 5. PREPARE LOOKUP STATEMENTS (FOR PERFORMANCE) ---
$stmtLoc   = $conn->prepare("SELECT LOCATION_ID FROM locations WHERE LOCATION_NAME = ?");
$stmtBldg  = $conn->prepare("SELECT BUILDING_ID FROM buildings WHERE BUILDING_NAME = ? AND LOCATION_ID = ?");
$stmtPen   = $conn->prepare("SELECT PEN_ID FROM pens WHERE PEN_NAME = ? AND BUILDING_ID = ?");
$stmtType  = $conn->prepare("SELECT ANIMAL_TYPE_ID FROM animal_type WHERE ANIMAL_TYPE_NAME = ?");
$stmtBreed = $conn->prepare("SELECT BREED_ID FROM breeds WHERE BREED_NAME = ? AND ANIMAL_TYPE_ID = ?");

// Caches to prevent hitting DB thousands of times for the same locations/types
$cache = [
    'loc'   => [],
    'bldg'  => [],
    'pen'   => [],
    'type'  => [],
    'breed' => []
];

$errors    = [];
$validRows = [];
$rowNum    = 1;

// --- 6. PARSE & VALIDATE ROWS ---
while (($row = fgetcsv($handle)) !== FALSE) {
    $rowNum++;
    
    // Skip completely empty lines
    if (empty(array_filter($row))) continue;

    // Helper closure to grab mapped data safely
    $getVal = function($colName) use ($row, $colMap) {
        $idx = $colMap[strtolower($colName)] ?? -1;
        return ($idx >= 0 && isset($row[$idx])) ? trim($row[$idx]) : null;
    };

    // Extract Required string values
    $tag_no           = $getVal('Tag_Number');
    $animal_type      = $getVal('Animal_Type');
    $breed            = $getVal('Breed');
    $birth_date       = $getVal('Birth_Date');
    $sex              = $getVal('Sex');
    $location         = $getVal('Location');
    $building         = $getVal('Building');
    $pen              = $getVal('Pen');
    $is_purchased_raw = $getVal('Is_Purchased');

    // Basic required presence checks
    if (!$tag_no)      { $errors[] = "Row $rowNum: Tag Number is empty."; continue; }
    if (!$animal_type) { $errors[] = "Row $rowNum: Animal Type is empty."; continue; }
    if (!$breed)       { $errors[] = "Row $rowNum: Breed is empty."; continue; }
    if (!$birth_date)  { $errors[] = "Row $rowNum: Birth Date is empty."; continue; }
    if (!$sex || !in_array(strtoupper($sex), ['M', 'F', 'U'])) { 
        $errors[] = "Row $rowNum: Sex must be M, F, or U."; continue; 
    }
    if (!$location)    { $errors[] = "Row $rowNum: Location is empty."; continue; }
    if (!$building)    { $errors[] = "Row $rowNum: Building is empty."; continue; }
    if (!$pen)         { $errors[] = "Row $rowNum: Pen is empty."; continue; }
    if ($is_purchased_raw === null || $is_purchased_raw === '') { 
        $errors[] = "Row $rowNum: Is_Purchased is empty."; continue; 
    }

    // ── Optional values mapped with strict defaults
    $wt_birth_raw = $getVal('Weight_At_Birth_kg');
    $wean_wt_raw  = $getVal('Weaning_Weight_kg');
    $est_wt_raw   = $getVal('Estimated_Weight_kg');
    $act_wt_raw   = $getVal('Actual_Weight_kg');
    $cost_raw     = $getVal('Acquisition_Cost');
    $misc_raw     = $getVal('Total_Misc_Amount');

    $weight_birth = is_numeric($wt_birth_raw) ? (float)$wt_birth_raw : 0.00;
    $weaning_wt   = is_numeric($wean_wt_raw)  ? (float)$wean_wt_raw  : null;
    $est_wt       = is_numeric($est_wt_raw)   ? (float)$est_wt_raw   : 0.00;
    $act_wt       = is_numeric($act_wt_raw)   ? (float)$act_wt_raw   : 0.00;
    $acq_cost     = is_numeric($cost_raw)     ? (float)$cost_raw     : 0.00;
    $misc_amt     = is_numeric($misc_raw)     ? (float)$misc_raw     : 0.00;

    $is_purchased = (strtolower($is_purchased_raw) == 'yes' || $is_purchased_raw == '1') ? 1 : 0;

    // Date formatting and validation
    $bdate_fmt = date('Y-m-d', strtotime($birth_date));
    if ($bdate_fmt == '1970-01-01' && strpos($birth_date, '1970') === false) {
        $errors[] = "Row $rowNum: Invalid Birth Date format ('$birth_date'). Use YYYY-MM-DD."; continue;
    }

    // ── DATABASE LOOKUPS ──

    // Location ID
    $loc_id = null;
    if (isset($cache['loc'][$location])) {
        $loc_id = $cache['loc'][$location];
    } else {
        $stmtLoc->execute([$location]);
        $loc_id = $stmtLoc->fetchColumn();
        if ($loc_id) $cache['loc'][$location] = $loc_id;
    }
    if (!$loc_id) { $errors[] = "Row $rowNum: Location '$location' not found in database."; continue; }

    // Building ID
    $bldg_id = null;
    $bldgKey = $building . '_' . $loc_id;
    if (isset($cache['bldg'][$bldgKey])) {
        $bldg_id = $cache['bldg'][$bldgKey];
    } else {
        $stmtBldg->execute([$building, $loc_id]);
        $bldg_id = $stmtBldg->fetchColumn();
        if ($bldg_id) $cache['bldg'][$bldgKey] = $bldg_id;
    }
    if (!$bldg_id) { $errors[] = "Row $rowNum: Building '$building' not found under Location '$location'."; continue; }

    // Pen ID
    $pen_id = null;
    $penKey = $pen . '_' . $bldg_id;
    if (isset($cache['pen'][$penKey])) {
        $pen_id = $cache['pen'][$penKey];
    } else {
        $stmtPen->execute([$pen, $bldg_id]);
        $pen_id = $stmtPen->fetchColumn();
        if ($pen_id) $cache['pen'][$penKey] = $pen_id;
    }
    if (!$pen_id) { $errors[] = "Row $rowNum: Pen '$pen' not found under Building '$building'."; continue; }

    // Animal Type ID
    $type_id = null;
    if (isset($cache['type'][$animal_type])) {
        $type_id = $cache['type'][$animal_type];
    } else {
        $stmtType->execute([$animal_type]);
        $type_id = $stmtType->fetchColumn();
        if ($type_id) $cache['type'][$animal_type] = $type_id;
    }
    if (!$type_id) { $errors[] = "Row $rowNum: Animal Type '$animal_type' not found in database."; continue; }

    // Breed ID
    $breed_id = null;
    $breedKey = $breed . '_' . $type_id;
    if (isset($cache['breed'][$breedKey])) {
        $breed_id = $cache['breed'][$breedKey];
    } else {
        $stmtBreed->execute([$breed, $type_id]);
        $breed_id = $stmtBreed->fetchColumn();
        if ($breed_id) $cache['breed'][$breedKey] = $breed_id;
    }
    if (!$breed_id) { $errors[] = "Row $rowNum: Breed '$breed' not found under Type '$animal_type'."; continue; }

    // Everything is valid, queue for insertion
    $validRows[] = [
        'TAG_NO'                   => $tag_no,
        'ANIMAL_TYPE_ID'           => $type_id,
        'BREED_ID'                 => $breed_id,
        'BIRTH_DATE'               => $bdate_fmt,
        'SEX'                      => strtoupper($sex),
        'WEIGHT_AT_BIRTH'          => $weight_birth,
        'WEANING_WEIGHT'           => $weaning_wt,
        'CURRENT_ESTIMATED_WEIGHT' => $est_wt,
        'CURRENT_ACTUAL_WEIGHT'    => $act_wt,
        'ACQUISITION_COST'         => $acq_cost,
        'CURRENT_STATUS'           => 'Active',
        'LOCATION_ID'              => $loc_id,
        'BUILDING_ID'              => $bldg_id,
        'PEN_ID'                   => $pen_id,
        'IS_ACTIVE'                => 1,
        'IS_PURCHASED'             => $is_purchased,
        'TOTAL_MISC_AMT'           => $misc_amt,
        'CREATED_AT'               => date('Y-m-d H:i:s')
    ];
}
fclose($handle);

// --- 7. EXECUTION & RESPONSES ---

// Fail entirely if ANY errors exist (Atomic CSV behavior)
if (count($errors) > 0) {
    echo json_encode([
        'success' => false, 
        'message' => 'Validation failed. No records were saved to the database. Fix the errors and try again.',
        'errors'  => array_slice($errors, 0, 50) // Return top 50 errors to prevent UI freezing
    ]);
    exit;
}

if (count($validRows) === 0) {
    echo json_encode(['success' => false, 'message' => 'No valid data rows found in the CSV.']);
    exit;
}

try {
    $conn->beginTransaction();

    $insertSql = "INSERT INTO animal_records (
        TAG_NO, ANIMAL_TYPE_ID, BREED_ID, BIRTH_DATE, SEX, 
        WEIGHT_AT_BIRTH, WEANING_WEIGHT, CURRENT_ESTIMATED_WEIGHT, CURRENT_ACTUAL_WEIGHT, 
        ACQUISITION_COST, CURRENT_STATUS, LOCATION_ID, BUILDING_ID, PEN_ID, 
        IS_ACTIVE, IS_PURCHASED, TOTAL_MISC_AMT, CREATED_AT
    ) VALUES (
        ?, ?, ?, ?, ?, 
        ?, ?, ?, ?, 
        ?, ?, ?, ?, ?, 
        ?, ?, ?, ?
    )";
    $insertStmt = $conn->prepare($insertSql);

    foreach ($validRows as $r) {
        $insertStmt->execute([
            $r['TAG_NO'], 
            $r['ANIMAL_TYPE_ID'], 
            $r['BREED_ID'], 
            $r['BIRTH_DATE'], 
            $r['SEX'],
            $r['WEIGHT_AT_BIRTH'], 
            $r['WEANING_WEIGHT'], 
            $r['CURRENT_ESTIMATED_WEIGHT'], 
            $r['CURRENT_ACTUAL_WEIGHT'],
            $r['ACQUISITION_COST'], 
            $r['CURRENT_STATUS'], 
            $r['LOCATION_ID'], 
            $r['BUILDING_ID'], 
            $r['PEN_ID'],
            $r['IS_ACTIVE'], 
            $r['IS_PURCHASED'], 
            $r['TOTAL_MISC_AMT'], 
            $r['CREATED_AT']
        ]);
    }

    // --- Audit Log ---
    $count   = count($validRows);
    $log_sql = "INSERT INTO audit_logs (USER_ID, USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS) 
                VALUES (?, ?, 'IMPORT_CSV', 'ANIMAL_RECORDS', ?, ?)";
    $log_stmt = $conn->prepare($log_sql);
    $log_stmt->execute([
        $user_id, 
        $username, 
        "Bulk imported $count animal records via CSV file upload.", 
        $ip_address
    ]);

    $conn->commit();
    echo json_encode(['success' => true, 'message' => "Successfully imported $count animal records!"]);

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Database Insertion Failed: ' . $e->getMessage()]);
}
?>