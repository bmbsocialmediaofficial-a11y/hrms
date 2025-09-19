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

// Fetch ticket notes
$sql = "SELECT n.*, e.name as created_by_name 
        FROM hr_notes n 
        JOIN employees e ON n.created_by = e.id 
        WHERE n.ticket_id = '$ticket_id' 
        ORDER BY n.note_date DESC";
        
$result = $conn->query($sql);
$notes = array();

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $notes[] = $row;
    }
    echo json_encode(['success' => true, 'notes' => $notes]);
} else {
    echo json_encode(['success' => true, 'notes' => []]);
}

$conn->close();
?>