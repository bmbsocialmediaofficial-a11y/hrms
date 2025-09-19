<?php
session_start();

// Include the configuration file
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
// Get task ID and attachment index from URL
$task_id = isset($_GET['task_id']) ? $_GET['task_id'] : null;
$attachment_index = isset($_GET['attachment_index']) ? $_GET['attachment_index'] : null;
if ($task_id === null || $attachment_index === null) {
    die("Invalid request");
}
// Get task data
$stmt = $conn->prepare("SELECT attachments FROM tasks WHERE id = ?");
$stmt->execute([$task_id]);
$task = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$task || !$task['attachments']) {
    die("Attachment not found");
}
// Unserialize attachments data
$attachments = unserialize($task['attachments']);
if (!isset($attachments[$attachment_index])) {
    die("Attachment not found");
}
$attachment = $attachments[$attachment_index];
// Set headers to force download
header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $attachment['name'] . '"');
header('Content-Transfer-Encoding: binary');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . strlen($attachment['content']));
// Output the file content
echo $attachment['content'];
exit;
?>