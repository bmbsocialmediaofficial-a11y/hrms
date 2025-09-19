<?php
session_start();

// Include the configuration file
require_once 'config.php';


// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);
// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get request ID
$requestId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($requestId > 0) {
    // Get request details
    $query = "SELECT tr.*, e.name as employee_name, e.email as employee_email, 
                     r.name as resolved_by_name 
              FROM technical_requests tr 
              JOIN employees e ON tr.employee_id = e.id 
              LEFT JOIN employees r ON tr.resolved_by = r.id 
              WHERE tr.id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $requestId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $request = $result->fetch_assoc();
        echo json_encode(['success' => true, 'request' => $request]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Request not found']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request ID']);
}

$conn->close();
?>