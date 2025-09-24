<?php
// Minimal index.php - test everything step by step
echo "Step 1: PHP is working<br>";

// Test if we can include files
if (@include_once('config.php')) {
    echo "Step 2: config.php loaded<br>";
} else {
    echo "Step 2: config.php failed to load<br>";
}

// Test MySQLi extension
if (function_exists('mysqli_connect')) {
    echo "Step 3: MySQLi extension is available<br>";
} else {
    echo "Step 3: MySQLi extension missing<br>";
    die("MySQLi not available");
}

// Manual connection test
echo "Step 4: Testing database connection...<br>";
$test_conn = @new mysqli(
    "serverless-northeurope.sysp0000.db3.skysql.com",
    "dbpbf19790723", 
    "YTyO6cwigcG9c0OBHk]h9KG",
    "sky",
    4115
);

if ($test_conn->connect_error) {
    echo "Database connection failed: " . $test_conn->connect_error . "<br>";
} else {
    echo "✅ Database connection successful!<br>";
    $test_conn->close();
}

echo "🎉 All tests completed!";
?>
