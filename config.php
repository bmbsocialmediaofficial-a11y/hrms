<?php
// Database configuration for SkySQL
$servername = "serverless-northeurope.sysp0000.db3.skysql.com";
$username   = "dbpbf19790723";
$password   = "YTyO6cwigcG9c0OBHk]h9KG";
$dbname     = "sky";
$port       = 4115;

// Enable error reporting
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    // Create connection
    $conn = new mysqli($servername, $username, $password, $dbname, $port);
    
    // Check connection
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    // Set character set
    $conn->set_charset("utf8mb4");
    
    echo "Connected successfully to SkySQL!";
    echo "<br>SkySQL Outbound IP: 52.178.145.224";
    echo "<br>Server version: " . $conn->server_info;
    
} catch (Exception $e) {
    echo "Connection error: " . $e->getMessage();
    echo "<br><br>To fix this:";
    echo "<br>1. Find your Render app's OUTBOUND IP address";
    echo "<br>2. Whitelist that IP in your SkySQL dashboard";
    echo "<br>3. Your SkySQL Outbound IP: 52.178.145.224 (this is SkySQL's IP)";
}
?>
