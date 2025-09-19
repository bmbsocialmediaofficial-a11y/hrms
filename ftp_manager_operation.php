<?php

session_start();

// Include the configuration file
require_once 'config.php';

// Increase PHP limits for large file uploads and downloads
ini_set('upload_max_filesize', '100M');
ini_set('post_max_size', '100M');
ini_set('max_execution_time', 300);
ini_set('max_input_time', 300);

// FTP Configuration
$ftp_hostname = 'ftpupload.net';
$ftp_username = 'if0_39900894';
$ftp_password = 'g5J2pBIvpKrRSLT';
$ftp_port     = 21;
$ftp_timeout  = 30;

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
    // Check if we have POST data (for file size validation)
    $content_length = $_SERVER['CONTENT_LENGTH'] ?? 0;
    $post_max_size = getBytes(ini_get('post_max_size'));
    
    if ($content_length > $post_max_size) {
        throw new Exception('File size exceeds server limit. Maximum allowed is ' . ini_get('post_max_size'));
    }
    
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
            
        case 'get_download_url':
            getDownloadUrl();
            break;
            
        default:
            // Handle direct file upload (like Version 1)
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
                handleDirectUpload();
            }
            break;
    }
    
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

// Return JSON response for AJAX requests
if ($action !== 'download' && !empty($action)) {
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

function getBytes($val) {
    $val = trim($val);
    $last = strtolower($val[strlen($val)-1]);
    $val = (int)$val;
    
    switch($last) {
        case 'g':
            $val *= 1024;
        case 'm':
            $val *= 1024;
        case 'k':
            $val *= 1024;
    }
    
    return $val;
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

function handleDirectUpload() {
    global $allowed_types, $ftp_hostname, $ftp_port, $ftp_timeout, $ftp_username, $ftp_password;
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
        // Check for upload errors
        if ($_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            $error_msg = getUploadError($_FILES['file']['error']);
            echo "<p style='color:red;'>❌ $error_msg</p>";
            exit;
        }
        
        $file_tmp  = $_FILES['file']['tmp_name'];
        $file_name = basename($_FILES['file']['name']);
        $file_type = $_FILES['file']['type'];

        // Check file type
        if (!in_array($file_type, $allowed_types)) {
            echo "<p style='color:red;'>❌ File type not allowed. Please upload images, videos, or documents.</p>";
            exit;
        }

        // Connect to FTP
        $conn_id = ftp_connect($ftp_hostname, $ftp_port, $ftp_timeout);
        if (!$conn_id) {
            echo "<p style='color:red;'>❌ Could not connect to FTP server.</p>";
            exit;
        }

        // Login to FTP server
        if (!ftp_login($conn_id, $ftp_username, $ftp_password)) {
            echo "<p style='color:red;'>❌ FTP login failed.</p>";
            ftp_close($conn_id);
            exit;
        }

        // Enable passive mode
        ftp_pasv($conn_id, true);

        // Remote target file path (inside htdocs/Operation)
        $remote_path = 'htdocs/Operation/' . $file_name;

        // Upload file
        if (ftp_put($conn_id, $remote_path, $file_tmp, FTP_BINARY)) {
            echo "<p style='color:green;'>✅ File uploaded successfully to <strong>htdocs/Operation/$file_name</strong>.</p>";
        } else {
            echo "<p style='color:red;'>❌ Failed to upload file to htdocs/Operation/.</p>";
        }

        // Close FTP connection
        ftp_close($conn_id);
    }
}

function getUploadError($error_code) {
    switch ($error_code) {
        case UPLOAD_ERR_INI_SIZE:
            return 'File size exceeds server limit. Maximum allowed is ' . ini_get('upload_max_filesize');
        case UPLOAD_ERR_FORM_SIZE:
            return 'File size exceeds form limit.';
        case UPLOAD_ERR_PARTIAL:
            return 'File was only partially uploaded.';
        case UPLOAD_ERR_NO_FILE:
            return 'No file was uploaded.';
        case UPLOAD_ERR_NO_TMP_DIR:
            return 'Missing temporary folder.';
        case UPLOAD_ERR_CANT_WRITE:
            return 'Failed to write file to disk.';
        case UPLOAD_ERR_EXTENSION:
            return 'File upload stopped by extension.';
        default:
            return 'Unknown upload error.';
    }
}

function handleUpload() {
    global $response, $allowed_types;
    
    // Check if file was uploaded
    if (!isset($_FILES['file'])) {
        throw new Exception('No file selected for upload.');
    }
    
    // Check for upload errors
    if ($_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        $error_msg = getUploadError($_FILES['file']['error']);
        throw new Exception($error_msg);
    }

    // Get file details
    $file_tmp = $_FILES['file']['tmp_name'];
    $file_name = basename($_FILES['file']['name']);
    $file_type = $_FILES['file']['type'];
    
    // Check file type
    if (!in_array($file_type, $allowed_types)) {
        throw new Exception('File type not allowed. Please upload images, videos, or documents.');
    }
    
    // Check file size (limit to 50MB)
    if ($_FILES['file']['size'] > 50 * 1024 * 1024) {
        throw new Exception('File size too large. Maximum allowed size is 50MB.');
    }

    // Connect to FTP
    $ftp_conn = connectFTP();
    
    // Remote target file path (inside htdocs/Operation)
    $remote_path = 'htdocs/Operation/' . $file_name;

    // Upload file
    if (ftp_put($ftp_conn, $remote_path, $file_tmp, FTP_BINARY)) {
        $response['success'] = true;
        $response['message'] = 'File uploaded successfully to htdocs/Operation/'.$file_name;
        $response['filename'] = $file_name;
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
    
    // List files in the htdocs/Operation directory
    $files = ftp_nlist($ftp_conn, 'htdocs/Operation/');
    
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

function getDownloadUrl() {
    global $response;
    
    $filename = $_POST['file_name'] ?? '';
    
    if (empty($filename)) {
        throw new Exception('No filename specified.');
    }
    
    // For security, validate the filename
    if (preg_match('/\.\.|\/|\\\/', $filename)) {
        throw new Exception('Invalid filename.');
    }
    
    // Generate direct FTP download URL
    $direct_url = "ftp://$ftp_username:$ftp_password@$ftp_hostname/htdocs/Operation/" . urlencode($filename);
    
    $response['success'] = true;
    $response['download_url'] = $direct_url;
}

function downloadFile() {
    // Get the filename
    $filename = $_POST['file_name'] ?? ($_GET['file_name'] ?? '');
    
    if (empty($filename)) {
        die('No filename specified.');
    }
    
    // For security, validate the filename
    if (preg_match('/\.\.|\/|\\\/', $filename)) {
        die('Invalid filename.');
    }
    
    // Clear any previous output
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    // Connect to FTP
    $ftp_conn = connectFTP();
    
    $remote_file_path = 'htdocs/Operation/' . $filename;
    
    // Check if file exists
    $file_size = ftp_size($ftp_conn, $remote_file_path);
    if ($file_size == -1) {
        ftp_close($ftp_conn);
        die('File not found on server.');
    }
    
    // Set headers for download
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . $file_size);
    
    // Download file directly from FTP and stream to browser
    if (ftp_fget($ftp_conn, fopen('php://output', 'w'), $remote_file_path, FTP_BINARY, 0)) {
        // File downloaded successfully
    } else {
        ftp_close($ftp_conn);
        die('Error downloading file.');
    }
    
    // Close FTP connection
    ftp_close($ftp_conn);
    exit;
}