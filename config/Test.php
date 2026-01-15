<?php
include 'Connection.php';
include 'Queries.php';

$sqlInsert = "INSERT INTO Units (unit_name, unit_abbr) VALUES ('grams', 'g')";
if (modifyTable($conn, $sqlInsert)) {
    echo "✅ Insert successful!<br>";
}


// Correct query for users
$sql = "SELECT * FROM Units";
$data = retrieveData($conn, $sql);

echo "<pre>";
print_r($data);
echo "</pre>";

oci_close($conn);
?>
