<?php
include '../config/Connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['supply_id'])) {
    $supply_id = $_POST['supply_id'];
    
    try {
        $sql = "DELETE FROM HEALTH_SUPPLIES WHERE SUPPLY_ID = :supply_id";
        $stmt = oci_parse($conn, $sql);
        oci_bind_by_name($stmt, ':supply_id', $supply_id);
        
        if (oci_execute($stmt, OCI_COMMIT_ON_SUCCESS)) {
            header('Location: ../health/available_health_supplies.php?deleted=success');
        } else {
            header('Location: ../health/available_health_supplies.php?deleted=error');
        }
    } catch (Exception $e) {
        header('Location: ../health/available_health_supplies.php?deleted=error');
    }
    
    oci_close($conn);
    exit();
}
?>