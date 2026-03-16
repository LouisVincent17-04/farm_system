<?php
$ch = curl_init('http://localhost:5000/');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$res = curl_exec($ch);
$err = curl_error($ch);
curl_close($ch);

echo $err ? "CURL ERROR: $err" : "SUCCESS: $res";