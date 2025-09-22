<?php
$servername = "mysql-11310a8f-bmbsocialmediaofficial-cfd3.f.aivencloud.com";
$username   = "avnadmin";
$password   = "AVNS_XwtknjLjZwyvusb29ty";
$dbname     = "defaultdb";
$port       = 21836;

// Init connection with SSL
$conn = mysqli_init();
mysqli_ssl_set($conn, NULL, NULL, __DIR__ . "/certs/ca.pem", NULL, NULL);

if (!mysqli_real_connect($conn, $servername, $username, $password, $dbname, $port, NULL, MYSQLI_CLIENT_SSL)) {
    die("❌ Connection failed: " . mysqli_connect_error());
}

echo "✅ Database connected successfully!";
?>
