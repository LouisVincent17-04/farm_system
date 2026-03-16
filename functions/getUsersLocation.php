<?php


// functions/getUsersLocation.php
error_reporting(0);
if(!isset($_SESSION)) {
    session_start();
}
include '../config/Connection.php';
include '../config/Queries.php';

function getUsersLocation($conn, $user_id) {
    $sql = "SELECT LOCATION_ID FROM users WHERE USER_ID = :user_id";
    $params = [':user_id' => $user_id];
    $result = retrieveData($conn, $sql, $params);
    
    if (!empty($result)) {
        return $result[0]['LOCATION_ID'];
    }
    
    return null; // Return null if no location found
}

$USER_LOCATION_ = getUsersLocation($conn, $_SESSION['user']['USER_ID']);

?>