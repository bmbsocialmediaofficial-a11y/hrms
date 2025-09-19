<?php
session_start();

// Include the configuration file
require_once 'config.php';

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die(json_encode(['success' => false, 'message' => 'Connection failed: ' . $conn->connect_error]));
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    die(json_encode(['success' => false, 'message' => 'Not logged in']));
}

// Get ticket ID from request
if (!isset($_POST['ticket_id'])) {
    die(json_encode(['success' => false, 'message' => 'No ticket ID provided']));
}

$ticket_id = $conn->real_escape_string($_POST['ticket_id']);

// Fetch ticket details
$sql = "SELECT t.*, e.name as employee_name, e.department, 
        a.name as assigned_name 
        FROM hr_tickets t 
        JOIN employees e ON t.employee_id = e.id 
        LEFT JOIN employees a ON t.assigned_to = a.id 
        WHERE t.id = '$ticket_id'";
        
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    $ticket = $result->fetch_assoc();
    echo json_encode(['success' => true, 'ticket' => $ticket]);
} else {
    echo json_encode(['success' => false, 'message' => 'Ticket not found']);
}

$conn->close();
?>