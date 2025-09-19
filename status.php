<?php
session_start();
// Include the configuration file
require_once 'config.php';
// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);
// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}
// Handle like/unlike action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_like') {
    $status_id = (int)$_POST['status_id'];
    $user_id = $_SESSION['user_id'];
    
    // Check if user already liked this status
    $check_like = "SELECT id FROM status_likes WHERE status_id = $status_id AND user_id = $user_id";
    $like_result = $conn->query($check_like);
    
    if ($like_result->num_rows > 0) {
        // User already liked, so unlike
        $delete_like = "DELETE FROM status_likes WHERE status_id = $status_id AND user_id = $user_id";
        if ($conn->query($delete_like)) {
            // Decrement like count
            $update_count = "UPDATE status_updates SET like_count = like_count - 1 WHERE id = $status_id";
            $conn->query($update_count);
            echo json_encode(['success' => true, 'liked' => false]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error unliking status']);
        }
    } else {
        // User hasn't liked, so like
        $insert_like = "INSERT INTO status_likes (status_id, user_id) VALUES ($status_id, $user_id)";
        if ($conn->query($insert_like)) {
            // Increment like count
            $update_count = "UPDATE status_updates SET like_count = like_count + 1 WHERE id = $status_id";
            $conn->query($update_count);
            echo json_encode(['success' => true, 'liked' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error liking status']);
        }
    }
    exit();
}
// Handle comment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_comment') {
    $status_id = (int)$_POST['status_id'];
    $user_id = $_SESSION['user_id'];
    $comment = $conn->real_escape_string(trim($_POST['comment']));
    
    if (!empty($comment)) {
        // Get user info from employees table
        $user_query = "SELECT name, email FROM employees WHERE id = $user_id";
        $user_result = $conn->query($user_query);
        $user_data = $user_result->fetch_assoc();
        $user_name = $user_data['name'];
        $user_email = $user_data['email'];
        
        $insert_comment = "INSERT INTO status_comments (status_id, user_id, comment) 
                          VALUES ($status_id, $user_id, '$comment')";
        
        if ($conn->query($insert_comment)) {
            // Increment comment count
            $update_count = "UPDATE status_updates SET comment_count = comment_count + 1 WHERE id = $status_id";
            $conn->query($update_count);
            
            // Return the new comment for display
            $comment_id = $conn->insert_id;
            $created_at = date('M j, Y \a\t g:i A');
            
            echo json_encode([
                'success' => true,
                'comment' => [
                    'id' => $comment_id,
                    'user_name' => $user_name,
                    'user_email' => $user_email,
                    'comment' => htmlspecialchars($comment),
                    'created_at' => $created_at
                ]
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error adding comment']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Comment cannot be empty']);
    }
    exit();
}
// Fetch all users for tagging
$users = array();
$user_sql = "SELECT id, name, email FROM employees WHERE valid_user = TRUE";
$user_result = $conn->query($user_sql);
if ($user_result->num_rows > 0) {
    while ($row = $user_result->fetch_assoc()) {
        $users[] = $row;
    }
}
// Handle form submission
$message = "";
$message_type = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['share_status'])) {
    $user_id = $_SESSION['user_id'];
    $user_name = $_SESSION['user_name'];
    $user_email = $_SESSION['user_email'];
    $status_message = $_POST['status_message'];
    
    // Process mentions in the message
    $processed_message = $status_message;
    if (preg_match_all('/@\[([^\]]+)\]/', $status_message, $matches)) {
        foreach ($matches[1] as $match) {
            // Extract user email from the mention format
            $mentioned_email = filter_var($match, FILTER_SANITIZE_EMAIL);
            $processed_message = str_replace("@[$match]", "<span class='mention'>@$mentioned_email</span>", $processed_message);
        }
    }
    
    // File upload handling - now storing in database
    $attachment_name = null;
    $attachment_path = null;
    $attachment_data = null;
    $file_type = null;
    
    if (!empty($_FILES['attachment']['name'])) {
        $upload_dir = "uploads/";
        
        // Check file size (max 500KB)
        if ($_FILES['attachment']['size'] > 512000) {
            $message = "Sorry, your file is too large. Maximum size is 500KB.";
            $message_type = "error";
        } else {
            // Allow certain file formats
            $file_type = strtolower(pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION));
            $allowed_types = array('jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'txt');
            
            if (in_array($file_type, $allowed_types)) {
                $attachment_name = $conn->real_escape_string($_FILES['attachment']['name']);
                
                // Read file contents for database storage
                $attachment_data = $conn->real_escape_string(file_get_contents($_FILES['attachment']['tmp_name']));
                
                // Keep the path field for backward compatibility (optional)
                $attachment_path = $conn->real_escape_string($upload_dir . time() . '_' . $attachment_name);
            } else {
                $message = "Sorry, only JPG, JPEG, PNG, GIF, PDF, DOC, DOCX & TXT files are allowed.";
                $message_type = "error";
            }
        }
    }
    
    // If no errors, save to database
    if (empty($message)) {
        // Escape the processed message for SQL insertion
        $escaped_message = $conn->real_escape_string($processed_message);
        $escaped_attachment_name = $attachment_name ? "'$attachment_name'" : "NULL";
        $escaped_attachment_path = $attachment_path ? "'$attachment_path'" : "NULL";
        $escaped_attachment_data = $attachment_data ? "'$attachment_data'" : "NULL";
        
        $sql = "INSERT INTO status_updates (user_id, user_name, user_email, message, attachment_name, attachment_path, attachment_data) 
                VALUES ('$user_id', '$user_name', '$user_email', '$escaped_message', $escaped_attachment_name, $escaped_attachment_path, $escaped_attachment_data)";
        
        if ($conn->query($sql)) {
            $message = "Status updated successfully!";
            $message_type = "success";
        } else {
            $message = "Error: " . $conn->error;
            $message_type = "error";
        }
    }
}
// Fetch all status updates with like and comment information
$status_updates = array();
$sql = "SELECT * FROM status_updates ORDER BY created_at DESC";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $status_id = $row['id'];
        
        // Check if current user liked this status
        $user_liked = false;
        if (isset($_SESSION['user_id'])) {
            $check_like = "SELECT id FROM status_likes WHERE status_id = $status_id AND user_id = " . $_SESSION['user_id'];
            $like_result = $conn->query($check_like);
            $user_liked = $like_result->num_rows > 0;
        }
        
        // Fetch comments for this status with user information
        $comments = array();
        $comments_sql = "SELECT sc.*, e.name as user_name, e.email as user_email 
                        FROM status_comments sc 
                        JOIN employees e ON sc.user_id = e.id 
                        WHERE sc.status_id = $status_id 
                        ORDER BY sc.created_at ASC";
        $comments_result = $conn->query($comments_sql);
        
        if ($comments_result->num_rows > 0) {
            while ($comment_row = $comments_result->fetch_assoc()) {
                $comments[] = $comment_row;
            }
        }
        
        // Add like and comment info to the status update
        $row['user_liked'] = $user_liked;
        $row['comments'] = $comments;
        
        $status_updates[] = $row;
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Status Updates - BuyMeABook</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);
            min-height: 100vh;
            color: white;
            position: relative;
            overflow-x: hidden;
            transition: margin-left 0.3s ease;
        }
        
        body.collapsed {
            margin-left: 60px;
        }
        
        body.expanded {
            margin-left: 250px;
        }
        
        .logo-container {
            position: absolute;
            top: 20px;
            left: 20px;
            z-index: 10;
        }
        
        .logo {
            width: 230px;
            height: auto;
            border-radius: 0px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
            transition: all 0.3s ease;
        }
        
        .logo:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.4);
        }
        
        .container {
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 40px 20px;
            width: 100%;
            max-width: 1000px;
            margin: 0 auto;
        }
        
        .header {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            width: 100%;
            margin-bottom: 30px;
            gap: 15px;
        }
        
        .nav-links {
            display: flex;
            gap: 15px;
        }
        
        .nav-link {
            color: white;
            text-decoration: none;
            font-weight: 600;
            padding: 10px 15px;
            border-radius: 50px;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
            
        }
        
        .nav-link:hover {
            background: rgba(255, 255, 255, 0.2);
        }
        
        .status-form {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(12px);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.2);
            width: 100%;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .form-title {
            font-size: 1.5rem;
            margin-bottom: 20px;
            color: white;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .form-group {
            margin-bottom: 20px;
            position: relative;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: rgba(255, 255, 255, 0.9);
        }
        
        .form-control {
            width: 100%;
            padding: 14px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 8px;
            font-size: 16px;
            background: rgba(255, 255, 255, 0.1);
            color: white;
            transition: border 0.3s;
        }
        
        .form-control:focus {
            border-color: rgba(255, 255, 255, 0.6);
            outline: none;
        }
        
        .form-control::placeholder {
            color: rgba(255, 255, 255, 0.6);
        }
        
        textarea.form-control {
            min-height: 120px;
            resize: vertical;
        }
        
        .user-lookup {
            position: absolute;
            background: rgba(255, 255, 255, 0.95);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
            max-height: 200px;
            overflow-y: auto;
            width: 100%;
            z-index: 100;
            display: none;
            color: #333;
        }
        
        .user-option {
            padding: 10px 15px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: background 0.2s;
        }
        
        .user-option:hover {
            background: #f0f0ff;
        }
        
        .user-avatar-sm {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: #6a11cb;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 14px;
        }
        
        .user-details-sm {
            font-size: 14px;
        }
        
        .user-details-sm strong {
            color: #333;
        }
        
        .user-details-sm span {
            color: #666;
            font-size: 12px;
        }
        
        .mention {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            padding: 2px 6px;
            border-radius: 4px;
            font-weight: 500;
        }
        
        .tag-instruction {
            font-size: 13px;
            color: rgba(255, 255, 255, 0.7);
            margin-top: 5px;
        }
        
        .btn {
            padding: 14px 25px;
            background: rgba(255, 255, 255, 0.9);
            color: #6a11cb;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn:hover {
            background: white;
            transform: translateY(-2px);
        }
        
        .status-updates {
            display: flex;
            flex-direction: column;
            gap: 20px;
            width: 100%;
        }
        
        .status-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(12px);
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .status-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .user-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 20px;
        }
        
        .user-details h3 {
            font-size: 1.1rem;
            margin-bottom: 4px;
            color: white;
        }
        
        .user-details p {
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.8);
        }
        
        .status-time {
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.7);
        }
        
        .status-content {
            margin-bottom: 20px;
            line-height: 1.6;
            font-size: 1.05rem;
            color: white;
        }
        
        .status-attachment {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .attachment-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: white;
            text-decoration: none;
            font-weight: 500;
            padding: 10px 15px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            transition: all 0.3s;
        }
        
        .attachment-link:hover {
            background: rgba(255, 255, 255, 0.2);
        }
        
        .attachment-image {
            max-width: 100%;
            border-radius: 8px;
            margin-top: 10px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.2);
        }
        
        .message {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            text-align: center;
            font-weight: 500;
        }
        
        .success {
            background: rgba(212, 237, 218, 0.3);
            color: white;
            border: 1px solid rgba(195, 230, 203, 0.5);
        }
        
        .error {
            background: rgba(248, 215, 218, 0.3);
            color: white;
            border: 1px solid rgba(245, 198, 203, 0.5);
        }
        
        .no-posts {
            text-align: center;
            padding: 40px;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(12px);
            border-radius: 15px;
            color: rgba(255, 255, 255, 0.8);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .no-posts i {
            font-size: 3rem;
            margin-bottom: 15px;
            color: rgba(255, 255, 255, 0.5);
        }
        
        /* Status actions */
        .status-actions {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .action-buttons {
            display: flex;
            gap: 15px;
        }
        
        .like-btn, .comment-btn {
            background: none;
            border: none;
            color: rgba(255, 255, 255, 0.7);
            font-size: 1rem;
            cursor: pointer;
            padding: 8px 12px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all 0.3s;
        }
        
        .like-btn:hover, .comment-btn:hover {
            background: rgba(255, 255, 255, 0.1);
        }
        
        .like-btn.liked {
            color: #ff6b6b;
        }
        
        /* Comments section */
        .comments-section {
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .comments-list {
            margin-bottom: 20px;
            max-height: 300px;
            overflow-y: auto;
        }
        
        .comment {
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .comment:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }
        
        .comment-header {
            margin-bottom: 8px;
        }
        
        .comment-user {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .comment-user-info {
            display: flex;
            flex-direction: column;
        }
        
        .comment-user-info strong {
            font-size: 0.9rem;
            color: white;
        }
        
        .comment-user-info span {
            font-size: 0.8rem;
            color: rgba(255, 255, 255, 0.7);
        }
        
        .comment-content {
            padding-left: 40px;
            font-size: 0.95rem;
            line-height: 1.5;
            color: rgba(255, 255, 255, 0.9);
        }
        
        .add-comment {
            margin-top: 15px;
        }
        
        .comment-input-container {
            display: flex;
            gap: 10px;
        }
        
        .comment-input {
            flex: 1;
            padding: 10px 15px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 20px;
            font-size: 0.9rem;
            resize: none;
            min-height: 40px;
            max-height: 100px;
            font-family: inherit;
            background: rgba(255, 255, 255, 0.1);
            color: white;
        }
        
        .comment-input:focus {
            outline: none;
            border-color: rgba(255, 255, 255, 0.6);
        }
        
        .comment-input::placeholder {
            color: rgba(255, 255, 255, 0.6);
        }
        
        .submit-comment {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: none;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .submit-comment:hover {
            background: rgba(255, 255, 255, 0.3);
        }
        
        /* Navigation Pane Styles */
        .nav-pane {
            position: fixed;
            left: 0;
            top: 0;
            height: 100vh;
            width: 250px;
            background: rgba(20, 20, 40, 0.95);
            backdrop-filter: blur(10px);
            z-index: 1000;
            transition: width 0.3s ease;
            box-shadow: 5px 0 15px rgba(0, 0, 0, 0.2);
            overflow-x: hidden;
        }
        
        .nav-pane.collapsed {
            width: 60px;
        }
        
        .nav-header {
            padding: 20px 15px;
            display: flex;
            align-items: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .nav-header h3 {
            color: white;
            margin-left: 10px;
            white-space: nowrap;
            overflow: hidden;
        }
        
        .nav-pane.collapsed .nav-header h3 {
            display: none;
        }
        
        .toggle-btn {
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            padding: 5px;
            border-radius: 5px;
            transition: all 0.3s;
        }
        
        .toggle-btn:hover {
            background: rgba(255, 255, 255, 0.1);
        }
        
        .nav-menu {
            list-style: none;
            padding: 10px 0;
        }
        
        .nav-item {
            padding: 0 10px;
            margin: 5px 0;
        }
        
        .nav-link {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s;
            white-space: nowrap;
        }
        
        .nav-link:hover {
            background: rgba(106, 17, 203, 0.3);
            color: white;
        }
        
        .nav-link.active {
            background: rgba(106, 17, 203, 0.5);
            color: white;
        }
        
        .nav-link i {
            margin-right: 15px;
            font-size: 1.2rem;
            width: 24px;
            text-align: center;
        }
        
        .nav-pane.collapsed .nav-link span {
            display: none;
        }
        
        .nav-pane.collapsed .nav-link i {
            margin-right: 0;
        }
        
        .nav-divider {
            height: 1px;
            background: rgba(255, 255, 255, 0.1);
            margin: 15px 0;
        }
        
        @media (max-width: 768px) {
            .container {
                padding-top: 80px;
            }
            
            .status-form {
                padding: 20px 15px;
            }
            
            .status-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .status-time {
                align-self: flex-end;
            }
            
            body.expanded, body.collapsed {
                margin-left: 0;
            }
            
            .nav-pane {
                width: 0;
                overflow: hidden;
            }
            
            .nav-pane.expanded {
                width: 250px;
            }
            
            .mobile-nav-toggle {
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 1100;
                background: rgba(20, 20, 40, 0.9);
                color: white;
                border: none;
                border-radius: 50%;
                width: 50px;
                height: 50px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 1.5rem;
                cursor: pointer;
                box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
            }
            
            .logo-container {
                position: relative;
                top: 0;
                left: 0;
                text-align: center;
                margin-bottom: 20px;
            }
        }
        
        @media (max-width: 480px) {
            .form-title {
                font-size: 1.3rem;
            }
            
            .status-card {
                padding: 20px 15px;
            }
            
            .btn {
                padding: 12px 15px;
            }
        }
    </style>
</head>
<body class="expanded">
    <!-- Mobile Navigation Toggle (visible only on mobile) -->
    <button class="mobile-nav-toggle" style="display: none;">
        <i class="fas fa-bars"></i>
    </button>
    
    <!-- Navigation Pane -->
    <div class="nav-pane expanded">
        <div class="nav-header">
            <button class="toggle-btn">
                <i class="fas fa-bars"></i>
            </button>
            <h3>Navigation</h3>
        </div>
        
        <ul class="nav-menu">
            <li class="nav-item">
                <a href="bmb_chatview.php" class="nav-link">
                    <i class="fas fa-comments"></i>
                    <span>BMB Chatview</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="bmb_taskview.php" class="nav-link">
                    <i class="fas fa-tasks"></i>
                    <span>BMB Taskview</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="notes.php" class="nav-link">
                    <i class="fas fa-sticky-note"></i>
                    <span>BMB Noteview</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="hrms.php" class="nav-link">
                    <i class="fas fa-users"></i>
                    <span>HRMS</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="expenditure.php" class="nav-link">
                    <i class="fas fa-money-bill-wave"></i>
                    <span>Expenditure Mgmt</span>
                </a>
            </li>
            
            <li class="nav-divider"></li>
            
            <li class="nav-item">
                <a href="status.php" class="nav-link active">
                    <i class="fas fa-stream"></i>
                    <span>Status Updates</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="manage_files.php" class="nav-link">
                    <i class="fas fa-folder"></i>
                    <span>Manage Files</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="employee_leave_request.php" class="nav-link">
                    <i class="fas fa-stream"></i>
                    <span>Leave Requests</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="ask_hr_employee.php" class="nav-link">
                    <i class="fas fa-stream"></i>
                    <span>Ask HR</span>
                </a>
            </li>
                        <li class="nav-item">
                <a href="technical_requests_admin.php" class="nav-link">
                    <i class="fas fa-tools"></i>
                    <span>Tech Support</span>
                </a>
            </li>
                                    <li class="nav-item">
                <a href="employee_details.php" class="nav-link">
                    <i class="fas fa-user-edit"></i>
                    <span>Employee Details</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="employee_exit_request.php" class="nav-link">
                    <i class="fas fa-stream"></i>
                    <span>Exit Requests</span>
                </a>
            </li>
        </ul>
    </div>
    
    <div class="logo-container">
        <img src="https://ik.imagekit.io/nuvq7aygp/BMB_Logo.jpg" alt="BMB Logo" class="logo">
    </div>
    
    <div class="container">
        <div class="header">
            <div class="nav-links">
                <a href="start.php" class="nav-link"><i class="fas fa-home"></i> Home</a>
                <a href="logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>
        
        <?php if (!empty($message)): ?>
            <div class="message <?php echo $message_type; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <div class="status-form">
            <h2 class="form-title"><i class="fas fa-edit"></i> Share an Update</h2>
            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="status_message">What's on your mind?</label>
                    <textarea id="status_message" name="status_message" class="form-control" placeholder="Share your thoughts, ideas, or updates. Type @ to mention someone..." required></textarea>
                    <div id="userLookup" class="user-lookup"></div>
                    <div class="tag-instruction">Type @ followed by a name to mention someone. Mentions will appear as @email@example.com</div>
                </div>
                
                <div class="form-group">
                    <label for="attachment">Attachment (optional)</label>
                    <input type="file" id="attachment" name="attachment" class="form-control">
                    <small>Supported formats: JPG, PNG, GIF, PDF, DOC, DOCX, TXT (Max 500KB)</small>
                </div>
                
                <button type="submit" name="share_status" class="btn"><i class="fas fa-paper-plane"></i> Share Update</button>
            </form>
        </div>
        
        <h2 style="margin-bottom: 20px; text-align: center;">Recent Updates</h2>
        
        <div class="status-updates">
            <?php if (count($status_updates) > 0): ?>
                <?php foreach ($status_updates as $post): ?>
                    <div class="status-card">
                        <div class="status-header">
                            <div class="user-info">
                                <div class="user-avatar">
                                    <?php echo strtoupper(substr($post['user_name'], 0, 1)); ?>
                                </div>
                                <div class="user-details">
                                    <h3><?php echo htmlspecialchars($post['user_name']); ?></h3>
                                    <p><?php echo htmlspecialchars($post['user_email']); ?></p>
                                </div>
                            </div>
                            <div class="status-time">
                                <?php 
                                    $timestamp = strtotime($post['created_at']);
                                    echo date('M j, Y \a\t g:i A', $timestamp);
                                ?>
                            </div>
                        </div>
                        
                        <div class="status-content">
                            <?php echo $post['message']; ?>
                        </div>
                        
                        <?php if (!empty($post['attachment_name']) && !empty($post['attachment_data'])): ?>
                            <div class="status-attachment">
                                <?php
                                $file_ext = strtolower(pathinfo($post['attachment_name'], PATHINFO_EXTENSION));
                                $image_exts = array('jpg', 'jpeg', 'png', 'gif');
                                
                                if (in_array($file_ext, $image_exts)): 
                                    // Create a data URI for the image
                                    $image_data = base64_encode($post['attachment_data']);
                                    $src = 'data: image/' . $file_ext . ';base64,' . $image_data;
                                ?>
                                    <img src="<?php echo $src; ?>" alt="Attachment" class="attachment-image">
                                <?php else: 
                                    // Create a download handler for non-image files
                                    $download_url = "download.php?id=" . $post['id'];
                                ?>
                                    <a href="<?php echo $download_url; ?>" class="attachment-link">
                                        <i class="fas fa-paperclip"></i>
                                        Download attached file: <?php echo htmlspecialchars($post['attachment_name']); ?>
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="status-actions">
                            <div class="action-buttons">
                                <button class="like-btn <?php echo $post['user_liked'] ? 'liked' : ''; ?>" data-status-id="<?php echo $post['id']; ?>">
                                    <i class="fas fa-heart"></i> 
                                    <span class="like-count"><?php echo $post['like_count']; ?></span>
                                </button>
                                <button class="comment-btn" data-status-id="<?php echo $post['id']; ?>">
                                    <i class="fas fa-comment"></i> 
                                    <span class="comment-count"><?php echo $post['comment_count']; ?></span>
                                </button>
                            </div>
                        </div>
                        
                        <div class="comments-section" id="comments-<?php echo $post['id']; ?>">
                            <?php if (count($post['comments']) > 0): ?>
                                <div class="comments-list">
                                    <?php foreach ($post['comments'] as $comment): ?>
                                        <div class="comment">
                                            <div class="comment-header">
                                                <div class="comment-user">
                                                    <div class="user-avatar-sm">
                                                        <?php echo strtoupper(substr($comment['user_name'], 0, 1)); ?>
                                                    </div>
                                                    <div class="comment-user-info">
                                                        <strong><?php echo htmlspecialchars($comment['user_name']); ?></strong>
                                                        <span><?php echo date('M j, Y \a\t g:i A', strtotime($comment['created_at'])); ?></span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="comment-content">
                                                <?php echo htmlspecialchars($comment['comment']); ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="add-comment">
                                <div class="comment-input-container">
                                    <textarea class="comment-input" placeholder="Write a comment..." data-status-id="<?php echo $post['id']; ?>"></textarea>
                                    <button class="submit-comment" data-status-id="<?php echo $post['id']; ?>">
                                        <i class="fas fa-paper-plane"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-posts">
                    <i class="fas fa-comment-slash"></i>
                    <h3>No status updates yet</h3>
                    <p>Be the first to share an update!</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const navPane = document.querySelector('.nav-pane');
            const toggleBtn = document.querySelector('.toggle-btn');
            const body = document.body;
            const mobileToggleBtn = document.querySelector('.mobile-nav-toggle');
            
            // Check if we're on mobile
            function checkMobile() {
                if (window.innerWidth <= 768) {
                    body.classList.remove('expanded', 'collapsed');
                    navPane.classList.remove('expanded', 'collapsed');
                    mobileToggleBtn.style.display = 'flex';
                } else {
                    mobileToggleBtn.style.display = 'none';
                    // Restore desktop state
                    if (localStorage.getItem('navCollapsed') === 'true') {
                        collapseNav();
                    } else {
                        expandNav();
                    }
                }
            }
            
            // Toggle navigation pane
            function toggleNav() {
                if (navPane.classList.contains('collapsed')) {
                    expandNav();
                } else {
                    collapseNav();
                }
            }
            
            function expandNav() {
                navPane.classList.remove('collapsed');
                navPane.classList.add('expanded');
                body.classList.remove('collapsed');
                body.classList.add('expanded');
                localStorage.setItem('navCollapsed', 'false');
            }
            
            function collapseNav() {
                navPane.classList.remove('expanded');
                navPane.classList.add('collapsed');
                body.classList.remove('expanded');
                body.classList.add('collapsed');
                localStorage.setItem('navCollapsed', 'true');
            }
            
            // Set initial state
            if (localStorage.getItem('navCollapsed') === 'true') {
                collapseNav();
            } else {
                expandNav();
            }
            
            // Event listeners
            toggleBtn.addEventListener('click', toggleNav);
            
            // Mobile toggle
            mobileToggleBtn.addEventListener('click', function() {
                if (navPane.classList.contains('expanded')) {
                    navPane.classList.remove('expanded');
                    mobileToggleBtn.innerHTML = '<i class="fas fa-bars"></i>';
                } else {
                    navPane.classList.add('expanded');
                    mobileToggleBtn.innerHTML = '<i class="fas fa-times"></i>';
                }
            });
            
            // Check on load and resize
            checkMobile();
            window.addEventListener('resize', checkMobile);
        });
        
        // User data for mention functionality
        const users = <?php echo json_encode($users); ?>;
        
        // DOM elements
        const statusMessage = document.getElementById('status_message');
        const userLookup = document.getElementById('userLookup');
        
        // User mention functionality
        statusMessage.addEventListener('input', function(e) {
            const cursorPosition = e.target.selectionStart;
            const textBeforeCursor = e.target.value.substring(0, cursorPosition);
            const atSymbolIndex = textBeforeCursor.lastIndexOf('@');
            
            if (atSymbolIndex !== -1) {
                const textAfterAt = textBeforeCursor.substring(atSymbolIndex + 1);
                const hasSpace = textAfterAt.includes(' ');
                
                if (!hasSpace) {
                    // Show user lookup
                    const searchTerm = textAfterAt.toLowerCase();
                    const filteredUsers = users.filter(user => 
                        user.name.toLowerCase().includes(searchTerm) || 
                        user.email.toLowerCase().includes(searchTerm)
                    );
                    
                    if (filteredUsers.length > 0) {
                        displayUserLookup(filteredUsers, atSymbolIndex);
                    } else {
                        userLookup.style.display = 'none';
                    }
                } else {
                    userLookup.style.display = 'none';
                }
            } else {
                userLookup.style.display = 'none';
            }
        });
        
        // Hide user lookup when clicking outside
        document.addEventListener('click', function(e) {
            if (e.target !== statusMessage && e.target !== userLookup && !userLookup.contains(e.target)) {
                userLookup.style.display = 'none';
            }
        });
        
        // Display user lookup options
        function displayUserLookup(users, atSymbolIndex) {
            userLookup.innerHTML = '';
            
            users.forEach(user => {
                const userOption = document.createElement('div');
                userOption.className = 'user-option';
                userOption.innerHTML = `
                    <div class="user-avatar-sm">${user.name.charAt(0).toUpperCase()}</div>
                    <div class="user-details-sm">
                        <strong>${user.name}</strong><br>
                        <span>${user.email}</span>
                    </div>
                `;
                
                userOption.addEventListener('click', function() {
                    insertMention(user, atSymbolIndex);
                });
                
                userLookup.appendChild(userOption);
            });
            
            userLookup.style.display = 'block';
        }
        
        // Insert mention into textarea
        function insertMention(user, atSymbolIndex) {
            const currentValue = statusMessage.value;
            const textBeforeAt = currentValue.substring(0, atSymbolIndex);
            const textAfterAt = currentValue.substring(atSymbolIndex);
            const spaceIndex = textAfterAt.indexOf(' ');
            
            let textToReplace;
            if (spaceIndex === -1) {
                textToReplace = textAfterAt;
            } else {
                textToReplace = textAfterAt.substring(0, spaceIndex);
            }
            
            const newValue = textBeforeAt + `@[${user.email}]` + (spaceIndex === -1 ? '' : textAfterAt.substring(spaceIndex));
            statusMessage.value = newValue;
            userLookup.style.display = 'none';
            
            // Set cursor position after the mention
            const newCursorPos = atSymbolIndex + user.email.length + 3; // 3 for @[]
            statusMessage.focus();
            statusMessage.setSelectionRange(newCursorPos, newCursorPos);
        }
        
        // Like/Unlike functionality
        document.querySelectorAll('.like-btn').forEach(button => {
            button.addEventListener('click', function() {
                const statusId = this.getAttribute('data-status-id');
                const likeCount = this.querySelector('.like-count');
                const currentCount = parseInt(likeCount.textContent);
                
                // Send AJAX request to toggle like
                fetch('status.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=toggle_like&status_id=' + statusId
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        if (data.liked) {
                            this.classList.add('liked');
                            likeCount.textContent = currentCount + 1;
                        } else {
                            this.classList.remove('liked');
                            likeCount.textContent = currentCount - 1;
                        }
                    } else {
                        alert(data.message || 'An error occurred. Please try again.');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred. Please try again.');
                });
            });
        });
        
        // Comment functionality
        document.querySelectorAll('.submit-comment').forEach(button => {
            button.addEventListener('click', function() {
                const statusId = this.getAttribute('data-status-id');
                const commentInput = document.querySelector(`.comment-input[data-status-id="${statusId}"]`);
                const commentText = commentInput.value.trim();
                
                if (commentText === '') {
                    alert('Please enter a comment');
                    return;
                }
                
                // Send AJAX request to add comment
                fetch('status.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=add_comment&status_id=' + statusId + '&comment=' + encodeURIComponent(commentText)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Clear input
                        commentInput.value = '';
                        
                        // Add new comment to the comments list
                        const commentsSection = document.getElementById(`comments-${statusId}`);
                        let commentsList = commentsSection.querySelector('.comments-list');
                        
                        // Create comments list if it doesn't exist
                        if (!commentsList) {
                            commentsList = document.createElement('div');
                            commentsList.className = 'comments-list';
                            commentsSection.insertBefore(commentsList, commentsSection.querySelector('.add-comment'));
                        }
                        
                        // Create new comment element
                        const newComment = document.createElement('div');
                        newComment.className = 'comment';
                        newComment.innerHTML = `
                            <div class="comment-header">
                                <div class="comment-user">
                                    <div class="user-avatar-sm">
                                        ${data.comment.user_name.charAt(0).toUpperCase()}
                                    </div>
                                    <div class="comment-user-info">
                                        <strong>${data.comment.user_name}</strong>
                                        <span>${data.comment.created_at}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="comment-content">
                                ${data.comment.comment}
                            </div>
                        `;
                        
                        commentsList.appendChild(newComment);
                        
                        // Update comment count
                        const commentCount = document.querySelector(`.comment-btn[data-status-id="${statusId}"] .comment-count`);
                        commentCount.textContent = parseInt(commentCount.textContent) + 1;
                    } else {
                        alert(data.message || 'An error occurred. Please try again.');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred. Please try again.');
                });
            });
        });
        
        // Allow submitting comment with Enter key (but not Shift+Enter for new line)
        document.querySelectorAll('.comment-input').forEach(textarea => {
            textarea.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    const statusId = this.getAttribute('data-status-id');
                    const submitButton = document.querySelector(`.submit-comment[data-status-id="${statusId}"]`);
                    submitButton.click();
                }
            });
        });
    </script>
</body>
</html>