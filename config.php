<?php
// Database configuration
$servername = "serverless-northeurope.sysp0000.db3.skysql.com";
$username   = "dbpbf19790723";
$password   = "YTyO6cwigcG9c0OBHk]h9KG";
$dbname     = "sky";
$port       = 4115;

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname, $port);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
} else {
    echo "Connected successfully";
}
?>
