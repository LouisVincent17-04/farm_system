<?php
// test_curl.php
$ch = curl_init('http://10.1.1.33:5000/');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$res = curl_exec($ch);
$err = curl_error($ch);
curl_close($ch);

echo $err ? "CURL ERROR: $err" : "SUCCESS: $res";