<?php
// config/AutoUpdate.php

// 1. Ensure Session is started (safe check)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. SMART CHECK: Only run this once per day per user session
// This prevents the database from being hammered every time you refresh the page.
$today = date('Y-m-d');

if (!isset($_SESSION['LAST_CLASS_UPDATE']) || $_SESSION['LAST_CLASS_UPDATE'] !== $today) {
    
    try {
        // Ensure DB Connection exists
        if (isset($conn)) {
            
            // ---------------------------------------------------------
            // THE UPDATE LOGIC
            // ---------------------------------------------------------
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

            $stmt = $conn->prepare($sql);
            $stmt->execute();
            
            // 3. Mark as done for today
            $_SESSION['LAST_CLASS_UPDATE'] = $today;

            // Optional: Log silently to error log if you need to debug later
            // error_log("Auto-Update: Classifications updated for user " . $_SESSION['user']['USERNAME']);
        }

    } catch (Exception $e) {
        // Silent Fail: Don't break the dashboard if this fails
        error_log("AutoUpdate Error: " . $e->getMessage());
    }
}
?>