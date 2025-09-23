<?php
// config.php - Simple and safe version
$servername = "serverless-northeurope.sysp0000.db3.skysql.com";
$username = "dbpbf19790723";
$password = "YTyO6cwigcG9c0OBHk]h9KG";
$dbname = "sky";
$port = 4115;

// Simple connection - no advanced error reporting
$conn = new mysqli($servername, $username, $password, $dbname, $port);

// Basic error check
if ($conn->connect_error) {
    // Don't show detailed errors to users
    error_log("Database connection failed: " . $conn->connect_error);
    // Set connection to null instead of dying
    $conn = null;
}

// If connection is successful, set charset
if ($conn && !$conn->connect_error) {
    $conn->set_charset("utf8mb4");
}
?>
