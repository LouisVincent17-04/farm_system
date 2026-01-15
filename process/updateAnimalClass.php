<?php
// process/update_animal_class.php
session_start();
header('Content-Type: application/json');
require_once '../config/Connection.php';

/**
 * Force Re-classification of ALL active animals.
 * This is necessary because changing the "Rules" (Classification Table) 
 * does not automatically trigger the "Rows" (Animal Records) to update in MySQL.
 */
function runAutoClassification($conn) {
    try {
        // 1. Assign correct class to animals that match a valid range
        $sql_valid = "
            UPDATE animal_records ar 
            JOIN animal_classifications ac 
                ON DATEDIFF(NOW(), ar.BIRTH_DATE) BETWEEN ac.MIN_DAYS AND ac.MAX_DAYS
            SET ar.CLASS_ID = ac.CLASS_ID
            WHERE 
                ar.IS_ACTIVE = 1 
                AND ar.BIRTH_DATE IS NOT NULL
                -- COLLATE ensures compatibility if tables have different charsets
                AND (ac.REQUIRED_SEX IS NULL OR ac.REQUIRED_SEX COLLATE utf8mb4_unicode_ci = ar.SEX)
        ";
        $conn->exec($sql_valid);

        // 2. Catch animals that now fall into a 'Gap' or have no match
        // (e.g. You changed Piglet to 1-20 and Weaner to 25-50, animals aged 22 are now 'Unknown')
        $sql_cleanup = "
            UPDATE animal_records ar
            LEFT JOIN animal_classifications ac ON ar.CLASS_ID = ac.CLASS_ID
            SET ar.CLASS_ID = (SELECT CLASS_ID FROM animal_classifications WHERE STAGE_NAME = 'Unknown Stage' LIMIT 1)
            WHERE 
                ar.IS_ACTIVE = 1 
                AND ar.BIRTH_DATE IS NOT NULL
                AND (
                    ac.CLASS_ID IS NULL -- Class deleted
                    OR 
                    DATEDIFF(NOW(), ar.BIRTH_DATE) NOT BETWEEN ac.MIN_DAYS AND ac.MAX_DAYS -- Age no longer fits class
                )
        ";
        $conn->exec($sql_cleanup);

    } catch (PDOException $e) {
        // Silently log or ignore, main update shouldn't fail because of this
    }
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { throw new Exception("Invalid request."); }

    $class_id = $_POST['class_id'];
    $min_days = (int)$_POST['min_days'];
    $max_days = (int)$_POST['max_days'];
    $fcr = $_POST['fcr'];

    if ($min_days >= $max_days) { throw new Exception("Min days must be less than Max days."); }

    // --- SERVER SIDE OVERLAP CHECK ---
    $checkSql = "SELECT STAGE_NAME, MIN_DAYS, MAX_DAYS FROM animal_classifications 
                 WHERE CLASS_ID != :id 
                 AND (:new_min <= MAX_DAYS AND :new_max >= MIN_DAYS)";
                 
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->execute([
        ':id' => $class_id,
        ':new_min' => $min_days,
        ':new_max' => $max_days
    ]);
    
    $conflict = $checkStmt->fetch(PDO::FETCH_ASSOC);
    if ($conflict) {
        throw new Exception("Range conflict with '{$conflict['STAGE_NAME']}' ({$conflict['MIN_DAYS']}-{$conflict['MAX_DAYS']}).");
    }

    $conn->beginTransaction();

    // 1. Update the Rule
    $stmt = $conn->prepare("UPDATE animal_classifications SET MIN_DAYS = ?, MAX_DAYS = ?, FCR = ? WHERE CLASS_ID = ?");
    $stmt->execute([$min_days, $max_days, $fcr, $class_id]);

    // 2. IMMEDIATE SYSTEM UPDATE: Re-calculate all animals
    runAutoClassification($conn);

    // 3. Audit Log
    $user_id = $_SESSION['user']['USER_ID'] ?? null;
    $username = $_SESSION['user']['FULL_NAME'] ?? 'System';
    $log = "Updated Class Rules (ID: $class_id). New Range: $min_days-$max_days. System re-classified.";
    $audit = $conn->prepare("INSERT INTO AUDIT_LOGS (USER_ID, USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS) VALUES (?, ?, 'UPDATE_CLASS', 'ANIMAL_CLASSIFICATIONS', ?, ?)");
    $audit->execute([$user_id, $username, $log, $_SERVER['REMOTE_ADDR']]);

    $conn->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    if($conn->inTransaction()) $conn->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>