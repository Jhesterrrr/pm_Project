<?php

$dbHost = 'localhost';
$dbUser = 'root';
$dbPass = '';
$dbName = 'administrator_database'; 

$mysqli = new mysqli($dbHost, $dbUser, $dbPass, $dbName);

if ($mysqli->connect_errno) {
    die('Database connection failed: ' . $mysqli->connect_error);
}



$mysqli->set_charset('utf8mb4');
?>


