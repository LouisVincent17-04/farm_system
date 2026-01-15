<?php
// process/update_animal_bio_bulk.php
session_start();
require_once '../config/Connection.php';
header('Content-Type: application/json');

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

    // UPDATED SQL: Only updates Type, Breed, Sex, and DOB
    $sql = "UPDATE animal_records SET 
            ANIMAL_TYPE_ID = ?, 
            BREED_ID = ?, 
            SEX = ?, 
            BIRTH_DATE = ? 
            WHERE ANIMAL_ID = ?";
    
    $stmt = $conn->prepare($sql);

    $updatedCount = 0;

    foreach ($animals as $animalId => $data) {
        // Extraction
        // We keep $tag for error messages, but we don't update it in DB
        $tag = trim($data['tag'] ?? 'Unknown'); 
        $type = $data['type'] ?? '';
        $breed = $data['breed'] ?? '';
        $sex = $data['sex'] ?? '';
        $dob = $data['dob'] ?? '';
        
        // Specific Validation
        if ($type === '') throw new Exception("Tag '$tag' is missing an ANIMAL TYPE.");
        if ($breed === '') throw new Exception("Tag '$tag' is missing a BREED. Please select one.");
        if ($sex === '') throw new Exception("Tag '$tag' is missing SEX.");
        if ($dob === '') throw new Exception("Tag '$tag' is missing BIRTH DATE.");

        // Execute Update
        // Order must match SQL placeholders: Type, Breed, Sex, DOB, ID
        $stmt->execute([$type, $breed, $sex, $dob, $animalId]);
        $updatedCount++;
    }

    $conn->commit();

    echo json_encode([
        'success' => true, 
        'message' => "Successfully updated $updatedCount records."
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