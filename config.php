<?php
// Ultra simple config.php
$servername = "serverless-northeurope.sysp0000.db3.skysql.com";
$username = "dbpbf19790723";
$password = "YTyO6cwigcG9c0OBHk]h9KG";
$dbname = "sky";
$port = 4115;

// Simple connection without any extra features
$conn = new mysqli($servername, $username, $password, $dbname, $port);

// Don't output anything here - just create the connection
?>
