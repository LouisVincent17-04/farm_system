<?php
// process/updateAnimalBio.php
session_start();
require_once '../config/Connection.php';
header('Content-Type: application/json');

// --- AUDIT LOG CONTEXT ---
$user_id = !empty($_SESSION['user']['USER_ID']) ? $_SESSION['user']['USER_ID'] : 1; // Default to 1 (System)
$username = $_SESSION['user']['FULL_NAME'] ?? 'System';
$ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

try {
    $conn->beginTransaction();

    $animals = $_POST['animals'] ?? [];

    if (empty($animals) || !is_array($animals)) {
        throw new Exception("No animal data received.");
    }

    // 1. UPDATE BASIC INFO
    $sql = "UPDATE animal_records SET 
            ANIMAL_TYPE_ID = ?, 
            BREED_ID = ?, 
            SEX = ?, 
            BIRTH_DATE = ? 
            WHERE ANIMAL_ID = ?";
    
    $stmt = $conn->prepare($sql);

    // 2. PREPARE RE-CLASSIFICATION QUERY
    // This query finds the correct CLASS_ID based on the new BIRTH_DATE and SEX
    $reclassSql = "
        UPDATE animal_records ar
        SET ar.CLASS_ID = (
            SELECT ac.CLASS_ID 
            FROM animal_classifications ac 
            WHERE DATEDIFF(NOW(), ar.BIRTH_DATE) >= ac.MIN_DAYS 
            AND DATEDIFF(NOW(), ar.BIRTH_DATE) <= ac.MAX_DAYS
            AND (ac.REQUIRED_SEX IS NULL OR ac.REQUIRED_SEX = ar.SEX)
            ORDER BY ac.MIN_DAYS DESC
            LIMIT 1
        )
        WHERE ar.ANIMAL_ID = ?
    ";
    $reclassStmt = $conn->prepare($reclassSql);

    $updatedCount = 0;

    foreach ($animals as $animalId => $data) {
        // Extraction
        $tag = trim($data['tag'] ?? 'Unknown'); 
        $type = $data['type'] ?? '';
        $breed = $data['breed'] ?? '';
        $sex = $data['sex'] ?? '';
        $dob = $data['dob'] ?? '';
        
        // Validation
        if ($type === '') throw new Exception("Tag '$tag' is missing an ANIMAL TYPE.");
        if ($breed === '') throw new Exception("Tag '$tag' is missing a BREED. Please select one.");
        if ($sex === '') throw new Exception("Tag '$tag' is missing SEX.");
        if ($dob === '') throw new Exception("Tag '$tag' is missing BIRTH DATE.");

        // Execute Main Update
        $stmt->execute([$type, $breed, $sex, $dob, $animalId]);

        // Execute Auto-Reclassification for this specific animal
        // We do this immediately because age (DOB) and Sex determine Class
        $reclassStmt->execute([$animalId]);

        $updatedCount++;
    }

    // 3. INSERT AUDIT LOG (Inside Transaction)
    if ($updatedCount > 0) {
        $audit_action = "BULK_BIO_UPDATE";
        $audit_details = "Updated biological info (Type, Breed, Sex, DOB) & re-classified $updatedCount animals.";

        $audit_sql = "INSERT INTO audit_logs (USER_ID, USERNAME, ACTION_TYPE, TABLE_NAME, ACTION_DETAILS, IP_ADDRESS) 
                      VALUES (?, ?, ?, 'ANIMAL_RECORDS', ?, ?)";
        $audit_stmt = $conn->prepare($audit_sql);
        $audit_stmt->execute([$user_id, $username, $audit_action, $audit_details, $ip_address]);
    }

    $conn->commit();

    echo json_encode([
        'success' => true, 
        'message' => "Successfully updated and re-classified $updatedCount records."
    ]);

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}
?>