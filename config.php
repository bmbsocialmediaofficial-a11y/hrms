<?php
// Database credentials (hardcoded)
$servername = "metro.proxy.rlwy.net";
$username   = "root";
$password   = "MhJRDhatBtkMwGCgOxizGHkVednZSUBj";
$dbname     = "railway";
$port       = 32231;

// Create connection
$conn = @new mysqli($servername, $username, $password, $dbname, $port);

// Check connection
if ($conn && $conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "Website is running and database connected successfully!";
?>
