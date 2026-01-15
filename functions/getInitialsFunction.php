<?php
function getInitials($name) {
    $words = explode(" ", trim($name));
    $firstInitial = strtoupper(substr($words[0], 0, 1));
    $lastInitial = strtoupper(substr($words[count($words) - 1], 0, 1));
    $initials = $firstInitial . $lastInitial;
    return $initials;
}
?>
