<?php
session_start();

// Include the configuration file
require_once 'config.php';
// FTP Configuration
$ftp_hostname = 'ftpupload.net';
$ftp_username = 'if0_39900894';
$ftp_password = 'g5J2pBIvpKrRSLT'; // Replace with your actual FTP password
$ftp_port = 21;
$ftp_timeout = 30;

// Allowed file types for upload
$allowed_types = [
    'image/jpeg', 
    'image/png', 
    'image/gif', 
    'image/webp', 
    'image/svg+xml',
    'video/mp4', 
    'video/mpeg', 
    'video/quicktime', 
    'video/x-msvideo', 
    'video/x-ms-wmv',
    'application/pdf', 
    'application/msword', 
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel', 
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'text/plain'
];

// Response array
$response = ['success' => false, 'message' => ''];

try {
    // Determine the action
    $action = $_POST['action'] ?? (isset($_GET['action']) ? $_GET['action'] : '');
    
    switch ($action) {
        case 'upload':
            handleUpload();
            break;
            
        case 'list_files':
            listFiles();
            break;
            
        case 'download':
            downloadFile();
            break;
            
        default:
            $response['message'] = 'Invalid action.';
            break;
    }
    
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

// Return JSON response for AJAX requests
if ($action !== 'download') {
    header('Content-Type: application/json');
    echo json_encode($response);
}

function connectFTP() {
    global $ftp_hostname, $ftp_port, $ftp_timeout, $ftp_username, $ftp_password;
    
    // Connect to FTP server
    $ftp_conn = ftp_connect($ftp_hostname, $ftp_port, $ftp_timeout);
    if (!$ftp_conn) {
        throw new Exception('Could not connect to FTP server.');
    }
    
    // Login to FTP
    $login = ftp_login($ftp_conn, $ftp_username, $ftp_password);
    if (!$login) {
        throw new Exception('FTP login failed. Please check your credentials.');
    }
    
    // Enable passive mode
    ftp_pasv($ftp_conn, true);
    
    return $ftp_conn;
}

function handleUpload() {
    global $response, $allowed_types;
    
    // Check if file data was sent
    if (!isset($_POST['file_data']) || empty($_POST['file_data'])) {
        throw new Exception('No file data received.');
    }

    // Get file details
    $file_name = $_POST['file_name'] ?? 'uploaded_file';
    $file_type = $_POST['file_type'] ?? 'application/octet-stream';
    
    // Check file type
    if (!in_array($file_type, $allowed_types)) {
        throw new Exception('File type not allowed. Please upload images, videos, or documents.');
    }
    
    // Decode base64 data
    $file_data = base64_decode($_POST['file_data']);
    if ($file_data === false) {
        throw new Exception('Failed to decode file data.');
    }
    
    // Check file size (limit to 50MB)
    if (strlen($file_data) > 50 * 1024 * 1024) {
        throw new Exception('File size too large. Maximum allowed size is 50MB.');
    }

    // Connect to FTP
    $ftp_conn = connectFTP();
    
    // Generate unique filename to avoid conflicts
    $file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
    $unique_filename = uniqid() . '_' . time() . '.' . $file_extension;
    $remote_file_path = 'htdocs/' . $unique_filename;
    
    // Create a temporary file
    $temp_file = tempnam(sys_get_temp_dir(), 'ftp_upload');
    file_put_contents($temp_file, $file_data);
    
    // Upload file
    $upload = ftp_put($ftp_conn, $remote_file_path, $temp_file, FTP_BINARY);
    
    // Clean up temporary file
    unlink($temp_file);
    
    if ($upload) {
        $response['success'] = true;
        $response['message'] = 'File uploaded successfully!';
        $response['filename'] = $unique_filename;
    } else {
        throw new Exception('Failed to upload file to FTP server.');
    }
    
    // Close FTP connection
    ftp_close($ftp_conn);
}

function listFiles() {
    global $response;
    
    // Connect to FTP
    $ftp_conn = connectFTP();
    
    // List files in the htdocs directory
    $files = ftp_nlist($ftp_conn, 'htdocs/');
    
    if ($files === false) {
        throw new Exception('Could not list files on FTP server.');
    }
    
    // Filter out directories and hidden files
    $filtered_files = [];
    foreach ($files as $file) {
        $filename = basename($file);
        if ($filename !== '.' && $filename !== '..' && $filename !== '.htaccess') {
            $filtered_files[] = $filename;
        }
    }
    
    $response['success'] = true;
    $response['files'] = $filtered_files;
    
    // Close FTP connection
    ftp_close($ftp_conn);
}

function downloadFile() {
    // Get the filename
    $filename = $_POST['file_name'] ?? ($_GET['file_name'] ?? '');
    
    if (empty($filename)) {
        die('No filename specified.');
    }
    
    // Connect to FTP
    $ftp_conn = connectFTP();
    
    $remote_file_path = 'htdocs/' . $filename;
    
    // Check if file exists
    $file_size = ftp_size($ftp_conn, $remote_file_path);
    if ($file_size == -1) {
        ftp_close($ftp_conn);
        die('File not found on server.');
    }
    
    // Create a temporary file
    $temp_file = tempnam(sys_get_temp_dir(), 'ftp_download');
    
    // Download file
    if (ftp_get($ftp_conn, $temp_file, $remote_file_path, FTP_BINARY)) {
        // Set headers for download
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($temp_file));
        
        // Read the file and output it
        readfile($temp_file);
        
        // Delete the temporary file
        unlink($temp_file);
    } else {
        // Delete the temporary file
        unlink($temp_file);
        ftp_close($ftp_conn);
        die('Error downloading file.');
    }
    
    // Close FTP connection
    ftp_close($ftp_conn);
    exit;
}
?>