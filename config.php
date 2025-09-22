
$servername = "metro.proxy.rlwy.net"; // Railway public TCP host
$username   = "root";                  // Railway username
$password   = "MhJRDhatBtkMwGCgOxizGHkVednZSUBj"; // Railway password
$dbname     = "railway";               // Railway database name
$port       = 32231;                    // Railway port

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname, $port);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
