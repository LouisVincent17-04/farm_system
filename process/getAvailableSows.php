<?php
// process/getAvailableSows.php
require_once '../config/Connection.php';
header('Content-Type: application/json');

try {
    // Fetch active animals that are female and likely breeders (Sow/Gilt)
    // Adjust the STAGE_NAME check based on your exact classification names
    $sql = "
        SELECT 
            ar.ANIMAL_ID, 
            ar.TAG_NO, 
            b.BREED_NAME,
            ac.STAGE_NAME,
            l.LOCATION_NAME,
            p.PEN_NAME
        FROM animal_records ar
        LEFT JOIN breeds b ON ar.BREED_ID = b.BREED_ID
        LEFT JOIN animal_classifications ac ON ar.CLASS_ID = ac.CLASS_ID
        LEFT JOIN locations l ON ar.LOCATION_ID = l.LOCATION_ID
        LEFT JOIN pens p ON ar.PEN_ID = p.PEN_ID
        WHERE ar.IS_ACTIVE = 1 
        AND ar.SEX = 'F'
        AND (ac.STAGE_NAME LIKE '%Sow%' OR ac.STAGE_NAME LIKE '%Gilt%')
        ORDER BY ar.TAG_NO ASC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $sows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'sows' => $sows]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>