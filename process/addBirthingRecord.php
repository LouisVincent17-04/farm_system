<?php
// process/addBirthingRecord.php
session_start();
require_once '../config/Connection.php';
header('Content-Type: application/json');

// Ensure the request is POST
if($_SERVER['REQUEST_METHOD'] !== 'POST') { 
    echo json_encode(['success' => false, 'message' => 'Invalid request method']); 
    exit; 
}

try {
    // Start Transaction
    $conn->beginTransaction();

    // 1. Gather Inputs
    $mother_id = $_POST['animal_id'];
    $date = $_POST['date_farrowed'];
    $born = (int)$_POST['total_born'];
    $active = (int)$_POST['active_count'];
    $dead = (int)$_POST['dead_count'];
    $mummy = (int)$_POST['mummified_count'];
    
    // Capture User ID (Default to 1 if session is missing/expired)
    $user_id = $_SESSION['user_id'] ?? 1;

    // 2. Calculate Parity (Count existing records + 1)
    $stmtParity = $conn->prepare("SELECT COUNT(*) FROM sow_birthing_records WHERE ANIMAL_ID = ?");
    $stmtParity->execute([$mother_id]);
    $current_count = $stmtParity->fetchColumn();
    $parity = $current_count + 1;

    // 3. Fetch Mother's Info for Inheritance & Tag Generation
    $stmtMother = $conn->prepare("
        SELECT TAG_NO, LOCATION_ID, BUILDING_ID, PEN_ID, BREED_ID, ANIMAL_TYPE_ID 
        FROM animal_records 
        WHERE ANIMAL_ID = ?
    ");
    $stmtMother->execute([$mother_id]);
    $mother = $stmtMother->fetch(PDO::FETCH_ASSOC);

    if (!$mother) {
        throw new Exception("Mother sow record not found.");
    }

    // --- NEW STEP: FETCH FATHER (BOAR) ---
    // We look for the most recent service record for this sow to find the father
    $stmtFather = $conn->prepare("
        SELECT BOAR_ID 
        FROM sow_service_history 
        WHERE ANIMAL_ID = ? 
        ORDER BY SERVICE_START_DATE DESC 
        LIMIT 1
    ");
    $stmtFather->execute([$mother_id]);
    $father_id = $stmtFather->fetchColumn(); // Returns ID or false/null

    // If no record found or external AI, ensure it's NULL for database
    if (!$father_id) { $father_id = null; }


    // 4. Insert Sow Birthing Record
    $sqlInsert = "INSERT INTO sow_birthing_records 
                  (ANIMAL_ID, PARITY, DATE_FARROWED, TOTAL_BORN, ACTIVE_COUNT, DEAD_COUNT, MUMMIFIED_COUNT, CREATED_BY) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmtInsert = $conn->prepare($sqlInsert);
    $stmtInsert->execute([$mother_id, $parity, $date, $born, $active, $dead, $mummy, $user_id]);

    // 5. Update Sow Status History
    $stmtUpdateStatus = $conn->prepare("
        UPDATE sow_status_history 
        SET SOW_CARD_CREATED = 1, PARITY = ? 
        WHERE ANIMAL_ID = ? AND IS_ACTIVE = 1
    ");
    $stmtUpdateStatus->execute([$parity, $mother_id]);

    // 6. Auto-Generate Piglet Records
    if ($active > 0) {
        // Prepare Piglet Insert Statement
        // ADDED: FATHER_ID column
        $pigletStmt = $conn->prepare("
            INSERT INTO animal_records 
            (TAG_NO, ANIMAL_TYPE_ID, BREED_ID, LOCATION_ID, BUILDING_ID, PEN_ID, 
             BIRTH_DATE, SEX, CURRENT_STATUS, IS_ACTIVE, MOTHER_ID, FATHER_ID, ACQUISITION_COST) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 'U', 'Active', 1, ?, ?, 0)
        ");

        for ($i = 1; $i <= $active; $i++) {
            // Format: [SOW TAG]-P[PARITY]-[ORDER]
            $order_str = str_pad($i, 2, '0', STR_PAD_LEFT);
            $new_tag = "{$mother['TAG_NO']}-P{$parity}-{$order_str}";

            $pigletStmt->execute([
                $new_tag,
                $mother['ANIMAL_TYPE_ID'], // Inherit Type
                $mother['BREED_ID'],       // Inherit Breed from Mother (or you could logic this differently)
                $mother['LOCATION_ID'],    // Inherit Location
                $mother['BUILDING_ID'],
                $mother['PEN_ID'],
                $date,                     // Birth Date
                $mother_id,                // Link Mother
                $father_id                // Link Father (NEW)
            ]);
        }
    }

    $conn->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => "Record saved successfully. Parity $parity. $active new piglet records generated."
    ]);

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode([
        'success' => false, 
        'message' => "Error: " . $e->getMessage()
    ]);
}
?>