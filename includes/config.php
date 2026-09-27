<?php

$dBHost     = '127.0.0.1';
$dBPort     = 8889;
$dBUsername = 'root';
$dBPassword = 'root';
$dBName     = 'fundi_electric';

mysqli_report(MYSQLI_REPORT_OFF);
$conn = new mysqli($dBHost, $dBUsername, $dBPassword, $dBName, $dBPort);

if ($conn->connect_error) {
    die('Database connection failed: ' . $conn->connect_error);
}
$conn->set_charset('utf8mb4');
