<?php
// config.php - ULTRA SILENT (NO ERRORS)
$servername = "serverless-northeurope.sysp0000.db3.skysql.com";
$username = "dbpbf19790723";
$password = "YTyO6cwigcG9c0OBHk]h9KG";
$dbname = "sky";
$port = 4115;

// COMPLETELY SILENT CONNECTION
$conn = null;

// Suppress ALL errors and warnings
error_reporting(0);
ini_set('display_errors', 0);

// Try connection without any error reporting
$temp_conn = @new mysqli();

if (@$temp_conn->real_connect($servername, $username, $password, $dbname, $port)) {
    $temp_conn->set_charset("utf8mb4");
    $conn = $temp_conn;
}
// If connection fails, $conn remains null - NO ERRORS
?>
