<?php
// config.php - Test connection after IP whitelisting
$servername = "serverless-northeurope.sysp0000.db3.skysql.com";
$username   = "dbpbf19790723";
$password   = "YTyO6cwigcG9c0OBHk]h9KG";
$dbname     = "sky";
$port       = 4115;

// Enable detailed error reporting
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

echo "<h3>Testing SkySQL Connection...</h3>";

try {
    // Create connection
    $conn = new mysqli($servername, $username, $password, $dbname, $port);
    
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    echo "✅ <strong>Connected successfully to SkySQL!</strong><br>";
    echo "📊 Database: " . $dbname . "<br>";
    echo "🌐 Server: " . $servername . "<br>";
    echo "🔗 Port: " . $port . "<br>";
    echo "⚡ Server Version: " . $conn->server_info . "<br>";
    
    // Test a simple query
    $result = $conn->query("SELECT VERSION() as mysql_version");
    if ($result) {
        $row = $result->fetch_assoc();
        echo "🐬 MySQL Version: " . $row['mysql_version'] . "<br>";
    }
    
    echo "<br>🎉 <strong>All tests passed! Your database is ready to use.</strong>";
    
    $conn->close();
    
} catch (Exception $e) {
    echo "❌ <strong>Connection Failed:</strong> " . $e->getMessage() . "<br><br>";
    echo "🔧 <strong>Possible solutions:</strong><br>";
    echo "1. IP whitelisting may take 5-10 minutes to propagate<br>";
    echo "2. Double-check the IP addresses in SkySQL dashboard<br>";
    echo "3. Verify your database credentials<br>";
    echo "4. Check if port 4115 is open for connections";
}
?>
