<?php
// process/getOverdue.php
require_once '../config/Connection.php';
header('Content-Type: application/json');

try {
    // Extends your original query to include LOCATION / BUILDING / PEN IDs
    // so urgent_blocker.php can group batches by type + pen + item.
    $sql = "
        SELECT
            e.EVENT_ID,
            e.EVENT_TYPE,
            e.ITEM_ID,
            e.START_DATE,
            e.END_DATE,
            a.ANIMAL_ID,
            a.TAG_NO,
            p.PEN_ID,
            p.PEN_NAME,
            b.BUILDING_ID,
            b.BUILDING_NAME,
            b.LOCATION_ID,
            l.LOCATION_NAME,
            CASE
                WHEN e.EVENT_TYPE = 'Medication'  THEN m.SUPPLY_NAME
                WHEN e.EVENT_TYPE = 'Vitamins'    THEN vs.SUPPLY_NAME
                WHEN e.EVENT_TYPE = 'Vaccination' THEN v.SUPPLY_NAME
                ELSE 'Checkup'
            END AS ITEM_NAME
        FROM event_schedules e
        JOIN  animal_records        a  ON e.ANIMAL_ID   = a.ANIMAL_ID
        LEFT JOIN pens              p  ON a.PEN_ID       = p.PEN_ID
        LEFT JOIN buildings         b  ON p.BUILDING_ID  = b.BUILDING_ID
        LEFT JOIN locations         l  ON b.LOCATION_ID  = l.LOCATION_ID
        LEFT JOIN medicines         m  ON e.ITEM_ID = m.SUPPLY_ID  AND e.EVENT_TYPE = 'Medication'
        LEFT JOIN vitamins_supplements vs ON e.ITEM_ID = vs.SUPPLY_ID AND e.EVENT_TYPE = 'Vitamins'
        LEFT JOIN vaccines          v  ON e.ITEM_ID = v.SUPPLY_ID  AND e.EVENT_TYPE = 'Vaccination'
        WHERE e.IS_ACTIVE  = 1
          AND e.STATUS     = 'Pending'
          AND e.START_DATE <= NOW()
        ORDER BY e.START_DATE ASC
    ";

    $stmt   = $conn->query($sql);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($events);

} catch (Exception $e) {
    echo json_encode([]);
}
?>