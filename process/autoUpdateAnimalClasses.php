<?php
// process/AutoUpdateAnimalClasses.php

// 1. Ensure Session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    if (isset($conn)) {
        
        // =========================================================
        // PART 1: UPDATE ANIMAL CLASSIFICATIONS
        // =========================================================
        $sql = "
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
            WHERE ar.IS_ACTIVE = 1 
            AND ar.BIRTH_DATE IS NOT NULL
        ";
        $conn->exec($sql);

        // =========================================================
        // PART 2: AUTO-WEANING LOGIC (Set Mother to 'DRY')
        // =========================================================
        
        // A. Find 'Starter' Minimum Days
        $stmt = $conn->prepare("SELECT MIN_DAYS FROM animal_classifications WHERE STAGE_NAME LIKE '%Starter%' ORDER BY MIN_DAYS ASC LIMIT 1");
        $stmt->execute();
        $starter_min_days = $stmt->fetchColumn();

        if ($starter_min_days) {
            
            // B. Find Mothers to Update
            // 1. Must have an active child >= Starter Days (The trigger)
            // 2. CRITICAL FIX: She MUST currently be in the 'BIRTHING' phase. Do not touch her if she is PREGNANT or in SERVICE.
            // 3. Must NOT have any active children < Starter Days (The safety check for new litters)
            
            $sql_find_sows = "
                SELECT DISTINCT ar.MOTHER_ID 
                FROM animal_records ar
                JOIN animal_records m ON ar.MOTHER_ID = m.ANIMAL_ID
                WHERE ar.IS_ACTIVE = 1 
                AND ar.MOTHER_ID IS NOT NULL
                AND DATEDIFF(NOW(), ar.BIRTH_DATE) >= ? 
                
                -- CRITICAL FIX: Only auto-wean sows that are currently marked as 'BIRTHING'
                AND m.ANIMAL_ID IN (
                    SELECT ANIMAL_ID FROM sow_status_history 
                    WHERE STATUS_NAME = 'BIRTHING' AND IS_ACTIVE = 1
                )
                
                -- Ensure she doesn't have a NEW litter currently nursing
                AND m.ANIMAL_ID NOT IN (
                    SELECT MOTHER_ID 
                    FROM animal_records 
                    WHERE IS_ACTIVE = 1 
                    AND MOTHER_ID IS NOT NULL
                    AND DATEDIFF(NOW(), BIRTH_DATE) < ?
                )
            ";
            
            $stmt = $conn->prepare($sql_find_sows);
            // Pass the threshold twice (once for the >= check, once for the < check)
            $stmt->execute([$starter_min_days, $starter_min_days]);
            $sows_to_update = $stmt->fetchAll(PDO::FETCH_COLUMN);

            if (!empty($sows_to_update)) {
                $created_by = $_SESSION['user_id'] ?? 1; 

                $deactivate_stmt = $conn->prepare("UPDATE sow_status_history SET IS_ACTIVE = 0, STATUS_END_DATE = NOW() WHERE ANIMAL_ID = ? AND IS_ACTIVE = 1");
                $insert_stmt = $conn->prepare("INSERT INTO sow_status_history (ANIMAL_ID, STATUS_NAME, STATUS_START_DATE, IS_ACTIVE, CREATED_BY, CREATED_AT) VALUES (?, 'DRY', NOW(), 1, ?, NOW())");

                foreach ($sows_to_update as $sow_id) {
                    $deactivate_stmt->execute([$sow_id]);
                    $insert_stmt->execute([$sow_id, $created_by]);
                }
            }
        }
    }

} catch (Exception $e) {
    error_log("AutoUpdate Error: " . $e->getMessage());
}
?>