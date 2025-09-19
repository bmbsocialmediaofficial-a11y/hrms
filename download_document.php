<?php
session_start();

// Include the configuration file
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if (isset($_GET['id'])) {
    $document_id = $_GET['id'];
    
    $sql = "SELECT file_name, file_data FROM employee_documents WHERE id = $document_id";
    $result = $conn->query($sql);
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        
        // Set headers for file download
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $row['file_name'] . '"');
        header('Content-Length: ' . strlen($row['file_data']));
        
        // Output the file data
        echo $row['file_data'];
        exit();
    } else {
        echo "File not found.";
    }
} else {
    echo "Invalid request.";
}

$conn->close();
?>