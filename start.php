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
// Check if bmb_avatar column exists, if not add it
try {
    $check_column = "SHOW COLUMNS FROM employees LIKE 'bmb_avatar'";
    $result = $conn->query($check_column);
    if ($result->num_rows == 0) {
        $alter_table = "ALTER TABLE employees ADD COLUMN bmb_avatar LONGBLOB NULL";
        if (!$conn->query($alter_table)) {
            throw new Exception("Error adding avatar column: " . $conn->error);
        }
    }
} catch (Exception $e) {
    // Log error but continue execution
    error_log($e->getMessage());
}
// Get employee ID from session
$employee_id = $_SESSION['user_id'];
$user_email = $_SESSION['user_email'];
// Handle avatar upload with security validation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['avatar']) && $_FILES['avatar']['error'] == 0) {
    try {
        // Validate file type
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png'];
        $file_type = $_FILES['avatar']['type'];
        
        if (!in_array($file_type, $allowed_types)) {
            throw new Exception("Only JPG, JPEG, and PNG file types are allowed.");
        }
        
        // Validate file size (limit to 2MB)
        $max_size = 2 * 1024 * 1024; // 2MB in bytes
        $file_size = $_FILES['avatar']['size'];
        
        if ($file_size > $max_size) {
            throw new Exception("File size must be less than 2MB.");
        }
        
        // Read file content
        $avatar_data = file_get_contents($_FILES['avatar']['tmp_name']);
        if ($avatar_data === false) {
            throw new Exception("Error reading file content.");
        }
        
        // Update avatar in database using prepared statement
        $update_avatar = "UPDATE employees SET bmb_avatar = ? WHERE id = ?";
        $stmt = $conn->prepare($update_avatar);
        if (!$stmt) {
            throw new Exception("Prepare statement failed: " . $conn->error);
        }
        
        $stmt->bind_param("bi", $avatar_data, $employee_id);
        $stmt->send_long_data(0, $avatar_data);
        
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        
        $stmt->close();
        
        // Refresh page to show new avatar
        header("Location: start.php");
        exit();
    } catch (Exception $e) {
        // Log error and continue
        error_log("Avatar upload error: " . $e->getMessage());
        // Optionally show user-friendly error message
        $_SESSION['avatar_error'] = "Error uploading avatar: " . $e->getMessage();
    }
}
// Get employee details including department and designation using prepared statement
try {
    $employee_query = "SELECT id, name, email, department, designation, bmb_avatar FROM employees WHERE id = ?";
    $stmt = $conn->prepare($employee_query);
    if (!$stmt) {
        throw new Exception("Prepare statement failed: " . $conn->error);
    }
    
    $stmt->bind_param("i", $employee_id);
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    
    $employee_result = $stmt->get_result();
    $employee = $employee_result->fetch_assoc();
    $stmt->close();
} catch (Exception $e) {
    error_log("Employee query error: " . $e->getMessage());
    // Fallback to session data if database query fails
    $employee = [
        'id' => $employee_id,
        'name' => $_SESSION['user_name'] ?? 'Unknown',
        'email' => $_SESSION['user_email'] ?? 'unknown@example.com',
        'department' => 'Unknown',
        'designation' => 'Unknown',
        'bmb_avatar' => null
    ];
}
// Optimize queries by using a single multi-query for performance data
try {
    // Fetch performance review data
    $performance_query = "SELECT * FROM performance_reviews WHERE employee_id = ? ORDER BY review_date DESC LIMIT 5";
    $stmt = $conn->prepare($performance_query);
    if (!$stmt) {
        throw new Exception("Performance query prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("i", $employee_id);
    if (!$stmt->execute()) {
        throw new Exception("Performance query execute failed: " . $stmt->error);
    }
    
    $performance_result = $stmt->get_result();
    
    // Calculate performance metrics
    $performance_rating = 0;
    $goals_achievement = 0;
    $completed_goals = 0;
    $total_goals = 0;
    $has_recognition = false;
    
    if ($performance_result->num_rows > 0) {
        while ($row = $performance_result->fetch_assoc()) {
            if ($row['performance_rating']) {
                $performance_rating = max($performance_rating, $row['performance_rating']);
            }
            if ($row['goal_progress']) {
                $goals_achievement = max($goals_achievement, $row['goal_progress']);
            }
            if ($row['goal_status'] === 'Completed') {
                $completed_goals++;
            }
            if ($row['goal_status'] && $row['goal_status'] !== 'Not Started') {
                $total_goals++;
            }
            if ($row['recognition_type']) {
                $has_recognition = true;
            }
        }
    }
    
    // Reset result pointer
    $performance_result->data_seek(0);
    $stmt->close();
} catch (Exception $e) {
    error_log("Performance data error: " . $e->getMessage());
    // Set default values if query fails
    $performance_result = null;
    $performance_rating = 0;
    $goals_achievement = 0;
    $completed_goals = 0;
    $total_goals = 0;
    $has_recognition = false;
}
// Fetch status updates where the current user is mentioned using prepared statement
try {
    $status_query = "SELECT * FROM status_updates WHERE message LIKE ? AND user_id != ? ORDER BY created_at DESC LIMIT 5";
    $mention_pattern = '%@' . $user_email . '%';
    $stmt = $conn->prepare($status_query);
    if (!$stmt) {
        throw new Exception("Status query prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("si", $mention_pattern, $employee_id);
    if (!$stmt->execute()) {
        throw new Exception("Status query execute failed: " . $stmt->error);
    }
    
    $status_result = $stmt->get_result();
    $stmt->close();
} catch (Exception $e) {
    error_log("Status updates error: " . $e->getMessage());
    // Create empty result if query fails
    $status_result = new mysqli_result($conn);
}
// Fetch tasks using prepared statement
try {
    $tasks_query = "SELECT * FROM tasks WHERE assignee_id = ? ORDER BY due_date ASC LIMIT 5";
    $stmt = $conn->prepare($tasks_query);
    if (!$stmt) {
        throw new Exception("Tasks query prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("i", $employee_id);
    if (!$stmt->execute()) {
        throw new Exception("Tasks query execute failed: " . $stmt->error);
    }
    
    $tasks_result = $stmt->get_result();
    
    // Calculate task metrics
    $todo_tasks = 0;
    $inprogress_tasks = 0;
    $completed_tasks = 0;
    $overdue_tasks = 0;
    $total_tasks = $tasks_result->num_rows;
    
    if ($total_tasks > 0) {
        while ($row = $tasks_result->fetch_assoc()) {
            switch ($row['status']) {
                case 'To Do':
                    $todo_tasks++;
                    break;
                case 'In Progress':
                    $inprogress_tasks++;
                    break;
                case 'Completed':
                    $completed_tasks++;
                    break;
            }
            
            // Check if task is overdue
            if ($row['due_date'] && $row['status'] !== 'Completed') {
                $due_date = new DateTime($row['due_date']);
                $today = new DateTime();
                if ($due_date < $today) {
                    $overdue_tasks++;
                }
            }
        }
    }
    
    // Reset result pointer
    $tasks_result->data_seek(0);
    $stmt->close();
} catch (Exception $e) {
    error_log("Tasks data error: " . $e->getMessage());
    // Set default values if query fails
    $tasks_result = new mysqli_result($conn);
    $todo_tasks = 0;
    $inprogress_tasks = 0;
    $completed_tasks = 0;
    $overdue_tasks = 0;
    $total_tasks = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to Buymeabook View Portal</title>
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
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 40px 20px;
        }
        
        .welcome-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(12px);
            border-radius: 20px;
            padding: 50px;
            text-align: center;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
            max-width: 700px;
            width: 90%;
            border: 1px solid rgba(255, 255, 255, 0.2);
            position: relative;
            overflow: hidden;
        }
        
        .welcome-card::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            transform: rotate(30deg);
            z-index: -1;
        }
        
        h1 {
            font-size: 2.8rem;
            margin-bottom: 15px;
            text-shadow: 2px 2px 8px rgba(0, 0, 0, 0.3);
            font-weight: 700;
            background: linear-gradient(to right, #fff, #e0e0ff);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            letter-spacing: 1px;
        }
        
        .subtitle {
            font-size: 1.3rem;
            margin-bottom: 40px;
            opacity: 0.9;
            font-weight: 300;
            letter-spacing: 0.5px;
        }
        
        .user-info {
            background: rgba(255, 255, 255, 0.15);
            padding: 25px;
            border-radius: 15px;
            margin: 25px 0;
            border: 1px solid rgba(255, 255, 255, 0.1);
            text-align: left;
        }
        
        .user-info p {
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            font-size: 1.1rem;
        }
        
        .user-info p:last-child {
            margin-bottom: 0;
        }
        
        .user-info i {
            margin-right: 12px;
            font-size: 1.2rem;
            color: #a7e6ff;
        }
        
        .btn-container {
            display: flex;
            justify-content: center;
            gap: 15px;
            flex-wrap: wrap;
            margin-top: 30px;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 14px 20px;
            background: rgba(255, 255, 255, 0.9);
            color: #6a11cb;
            text-decoration: none;
            border-radius: 50px;
            font-weight: 600;
            transition: all 0.3s;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
            border: none;
            cursor: pointer;
            min-width: 160px;
            flex: 1;
            max-width: 200px;
        }
        
        .btn i {
            margin-right: 8px;
            font-size: 1.1rem;
        }
        
        .btn:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
            background: white;
        }
        
        .btn-status {
            background: rgba(106, 17, 203, 0.9);
            color: white;
        }
        
        .btn-status:hover {
            background: rgba(90, 15, 184, 0.95);
        }
        
        .btn-files {
            background: rgba(37, 117, 252, 0.9);
            color: white;
        }
        
        .btn-files:hover {
            background: rgba(30, 100, 225, 0.95);
        }
        
        .btn-portal {
            background: rgba(255, 255, 255, 0.9);
            color: #6a11cb;
        }
        
        .btn-portal:hover {
            background: white;
        }
        
        .btn-admin {
            background: rgba(124, 252, 0, 0.7);
            color: #1a5c00;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        
        .btn-admin:hover {
            background: rgba(124, 252, 0, 0.9);
            color: #1a5c00;
        }
        
        .btn-logout {
            background: rgba(255, 255, 255, 0.15);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        
        .btn-logout:hover {
            background: rgba(255, 100, 100, 0.2);
            color: #ffcccc;
        }
        
        .btn-catool {
            background: rgba(210, 140, 70, 0.8);
            color: white;
        }
        
        .btn-catool:hover {
            background: rgba(190, 120, 50, 0.9);
        }
        
        .btn-manager {
            background: rgba(76, 0, 153, 0.8);
            color: white;
        }
        
        .btn-manager:hover {
            background: rgba(56, 0, 113, 0.9);
        }
        
        .decoration {
            position: absolute;
            z-index: -1;
        }
        
        .decoration-1 {
            top: -30px;
            right: -30px;
            width: 200px;
            height: 200px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(155, 81, 224, 0.4) 0%, transparent 70%);
        }
        
        .decoration-2 {
            bottom: -40px;
            left: -40px;
            width: 250px;
            height: 250px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(37, 117, 252, 0.3) 0%, transparent 70%);
        }
        
        /* Analytics Dashboard Styles */
        .analytics-container {
            margin-top: 40px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .analytics-card {
            background: rgba(255, 255, 255, 0.15);
            border-radius: 15px;
            padding: 20px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            text-align: left;
        }
        
        .analytics-card h3 {
            margin-bottom: 15px;
            color: #fff;
            font-size: 1.3rem;
            display: flex;
            align-items: center;
        }
        
        .analytics-card h3 i {
            margin-right: 10px;
            color: #a7e6ff;
        }
        
        .analytics-item {
            margin-bottom: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .analytics-label {
            font-size: 0.95rem;
            opacity: 0.9;
        }
        
        .analytics-value {
            font-weight: 600;
            font-size: 1rem;
        }
        
        .progress-bar {
            height: 8px;
            width: 100%;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 4px;
            margin-top: 5px;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #a7e6ff, #6a11cb);
            border-radius: 4px;
        }
        
        .status-updates {
            margin-top: 10px;
            max-height: 150px;
            overflow-y: auto;
        }
        
        .status-item {
            padding: 10px;
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.1);
            margin-bottom: 10px;
            font-size: 0.9rem;
        }
        
        .status-date {
            font-size: 0.8rem;
            opacity: 0.7;
            margin-top: 5px;
        }
        
        .task-item {
            padding: 10px;
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.1);
            margin-bottom: 10px;
            font-size: 0.9rem;
            text-align: left;
        }
        
        .task-title {
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .task-meta {
            display: flex;
            justify-content: space-between;
            font-size: 0.8rem;
            opacity: 0.8;
        }
        
        .task-priority {
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .priority-low {
            background: rgba(76, 175, 80, 0.3);
            color: #c8e6c9;
        }
        
        .priority-medium {
            background: rgba(255, 193, 7, 0.3);
            color: #ffe082;
        }
        
        .priority-high {
            background: rgba(255, 87, 34, 0.3);
            color: #ffccbc;
        }
        
        .priority-critical {
            background: rgba(244, 67, 54, 0.3);
            color: #ffcdd2;
        }
        
        .recognition-badge {
            display: inline-block;
            background: rgba(255, 215, 0, 0.3);
            color: #fff9c4;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.85rem;
            margin-top: 10px;
        }
        
        .recognition-badge i {
            margin-right: 5px;
            color: #ffeb3b;
        }
        
        /* Navigation Pane Styles */
        .nav-pane {
			scrollbar-width: none;
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
            h1 {
                font-size: 2.2rem;
            }
            
            .subtitle {
                font-size: 1.1rem;
            }
            
            .welcome-card {
                padding: 30px 25px;
            }
            
            .btn-container {
                flex-direction: column;
                align-items: center;
            }
            
            .btn {
                width: 100%;
                max-width: 250px;
            }
            
            .logo-container {
                position: relative;
                top: 0;
                left: 0;
                text-align: center;
                margin-bottom: 20px;
            }
            
            .container {
                flex-direction: column;
                padding-top: 20px;
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
            
            /* Mobile-specific avatar adjustments */
            .avatar {
                width: 100px;
                height: 100px;
            }
            
            .analytics-container {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 480px) {
            h1 {
                font-size: 1.8rem;
            }
            
            .user-info {
                padding: 20px 15px;
            }
            
            .user-info p {
                font-size: 1rem;
            }
            
            .btn {
                padding: 12px 15px;
                min-width: 140px;
            }
            
            .analytics-card {
                padding: 15px;
            }
            
            .analytics-card h3 {
                font-size: 1.1rem;
            }
            
            .status-updates {
                max-height: 120px;
            }
        }
        
        /* Status mention styling */
        .mention {
            color: #a7e6ff;
            font-weight: 600;
        }
        
        .status-author {
            font-size: 0.8rem;
            opacity: 0.8;
            margin-bottom: 5px;
            font-weight: 600;
        }
        
        /* Profile avatar styling */
        .profile-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: 25px;
        }
        
        .avatar-container {
            position: relative;
            margin-bottom: 15px;
        }
        
        .avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
        
        .avatar-upload {
            position: absolute;
            bottom: 0;
            right: 0;
            background: rgba(106, 17, 203, 0.8);
            border: none;
            border-radius: 50%;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            cursor: pointer;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.2);
            transition: all 0.3s;
        }
        
        .avatar-upload:hover {
            background: rgba(90, 15, 184, 0.95);
            transform: scale(1.1);
        }
        
        .avatar-upload input {
            display: none;
        }
        
        .user-details {
            text-align: center;
        }
        
        .user-name {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .user-email {
            font-size: 1rem;
            opacity: 0.8;
            margin-bottom: 10px;
        }
        
        .user-department, .user-designation {
            font-size: 0.9rem;
            opacity: 0.9;
            margin-bottom: 5px;
        }
        
        .user-department i, .user-designation i {
            margin-right: 5px;
            color: #a7e6ff;
        }
        
        .error-message {
            background: rgba(231, 76, 60, 0.2);
            border: 1px solid rgba(231, 76, 60, 0.5);
            color: #ffcccc;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-size: 0.9rem;
            text-align: center;
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
                <a href="status.php" class="nav-link">
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
                    <i class="fas fa-calendar-alt"></i>
                    <span>Leave Requests</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="ask_hr_employee.php" class="nav-link">
                    <i class="fas fa-headset"></i>
                    <span>ASK HR</span>
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
                    <i class="fas fa-door-open"></i>
                    <span>Exit Requests</span>
                </a>
            </li>
        </ul>
    </div>
    
    <div class="logo-container">
        <img src="https://ik.imagekit.io/nuvq7aygp/BMB_Logo.jpg" alt="BMB Logo" class="logo">
    </div>
    
    <div class="container">
        <div class="welcome-card">
            <div class="decoration decoration-1"></div>
            <div class="decoration decoration-2"></div>
            
            <h1>Welcome to Buymeabook View Portal</h1>
            <p class="subtitle">Manage, connect, and thrive with your employee account.</p>
            
            <!-- Display error message if avatar upload failed -->
            <?php if (isset($_SESSION['avatar_error'])): ?>
                <div class="error-message">
                    <?php echo htmlspecialchars($_SESSION['avatar_error']); ?>
                    <?php unset($_SESSION['avatar_error']); ?>
                </div>
            <?php endif; ?>
            
            <!-- Profile Section with Avatar -->
            <div class="profile-container">
                <div class="avatar-container">
                    <?php if (!empty($employee['bmb_avatar'])): ?>
                        <img src="data:image/jpeg;base64,<?php echo base64_encode($employee['bmb_avatar']); ?>" alt="Profile Avatar" class="avatar">
                    <?php else: ?>
                        <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($employee['name']); ?>&background=6a11cb&color=fff&size=120" alt="Default Avatar" class="avatar">
                    <?php endif; ?>
                    
                    <form action="" method="post" enctype="multipart/form-data">
                        <label for="avatar-upload" class="avatar-upload">
                            <i class="fas fa-camera"></i>
                            <input type="file" id="avatar-upload" name="avatar" accept="image/jpeg, image/jpg, image/png">
                        </label>
                    </form>
                </div>
                
                <div class="user-details">
                    <div class="user-name"><?php echo htmlspecialchars($employee['name']); ?></div>
                    <div class="user-email"><?php echo htmlspecialchars($employee['email']); ?></div>
                    <?php if (!empty($employee['department'])): ?>
                        <div class="user-department"><i class="fas fa-building"></i> <?php echo htmlspecialchars($employee['department']); ?></div>
                    <?php endif; ?>
                    <?php if (!empty($employee['designation'])): ?>
                        <div class="user-designation"><i class="fas fa-briefcase"></i> <?php echo htmlspecialchars($employee['designation']); ?></div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Analytics Dashboard -->
            <div class="analytics-container">
                <!-- Performance Analytics Card -->
                <div class="analytics-card">
                    <h3><i class="fas fa-chart-line"></i> Performance Analytics</h3>
                    
                    <div class="analytics-item">
                        <span class="analytics-label">Performance Rating</span>
                        <span class="analytics-value"><?php echo $performance_rating ? $performance_rating . '/5' : 'N/A'; ?></span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?php echo $performance_rating ? ($performance_rating / 5 * 100) : '0'; ?>%"></div>
                    </div>
                    
                    <div class="analytics-item">
                        <span class="analytics-label">Goals Achievement</span>
                        <span class="analytics-value"><?php echo $goals_achievement ? $goals_achievement . '%' : 'N/A'; ?></span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?php echo $goals_achievement ? $goals_achievement : '0'; ?>%"></div>
                    </div>
                    
                    <div class="analytics-item">
                        <span class="analytics-label">Completed Goals</span>
                        <span class="analytics-value"><?php echo $completed_goals . '/' . $total_goals; ?></span>
                    </div>
                    
                    <?php if ($has_recognition): ?>
                    <div class="recognition-badge">
                        <i class="fas fa-trophy"></i> Recognized for Excellence
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Social Status Updates Card -->
                <div class="analytics-card">
                    <h3><i class="fas fa-stream"></i> Recent Mentions</h3>
                    
                    <div class="status-updates">
                        <?php if ($status_result && $status_result->num_rows > 0): ?>
                            <?php while ($row = $status_result->fetch_assoc()): ?>
                                <div class="status-item">
                                    <div class="status-author"><?php echo htmlspecialchars($row['user_name']); ?></div>
                                    <?php echo $row['message']; ?>
                                    <div class="status-date"><?php echo date('M j, Y', strtotime($row['created_at'])); ?></div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="status-item">No recent mentions</div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="analytics-item" style="margin-top: 15px;">
                        <span class="analytics-label">Total Mentions</span>
                        <span class="analytics-value"><?php echo $status_result ? $status_result->num_rows : 0; ?></span>
                    </div>
                </div>
                
                <!-- Tasks Analytics Card -->
                <div class="analytics-card">
                    <h3><i class="fas fa-tasks"></i> Task Overview</h3>
                    
                    <div class="analytics-item">
                        <span class="analytics-label">To Do</span>
                        <span class="analytics-value"><?php echo $todo_tasks; ?></span>
                    </div>
                    
                    <div class="analytics-item">
                        <span class="analytics-label">In Progress</span>
                        <span class="analytics-value"><?php echo $inprogress_tasks; ?></span>
                    </div>
                    
                    <div class="analytics-item">
                        <span class="analytics-label">Completed</span>
                        <span class="analytics-value"><?php echo $completed_tasks; ?></span>
                    </div>
                    
                    <?php if ($overdue_tasks > 0): ?>
                    <div class="analytics-item" style="color: #ff9e9e;">
                        <span class="analytics-label">Overdue</span>
                        <span class="analytics-value"><?php echo $overdue_tasks; ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <div class="task-item" style="margin-top: 15px;">
                        <?php if ($tasks_result && $tasks_result->num_rows > 0): ?>
                            <?php 
                            $tasks_result->data_seek(0);
                            $row = $tasks_result->fetch_assoc();
                            ?>
                            <div class="task-title"><?php echo htmlspecialchars($row['title']); ?></div>
                            <div class="task-meta">
                                <span>Due: <?php echo $row['due_date'] ? date('M j', strtotime($row['due_date'])) : 'N/A'; ?></span>
                                <span class="task-priority priority-<?php echo strtolower($row['priority']); ?>"><?php echo $row['priority']; ?></span>
                            </div>
                        <?php else: ?>
                            <div class="task-title">No upcoming tasks</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="btn-container">
                <a href="status.php" class="btn btn-status"><i class="fas fa-stream"></i> Status Updates</a>
                <a href="manage_files.php" class="btn btn-files"><i class="fas fa-folder"></i> Manage Files</a>
                <a href="index.php" class="btn btn-portal"><i class="fas fa-arrow-left"></i> Back to Portal</a>
                <a href="ca_tool.php" class="btn btn-catool"><i class="fas fa-calculator"></i> CA Tool</a>
                <a href="serve_technical_requests.php" class="btn btn-admin"><i class="fas fa-cogs"></i> Admin Portal</a>
                <a href="bmb_manager.php" class="btn btn-manager"><i class="fas fa-user-tie"></i> Manager Portal</a>
                <a href="logout.php" class="btn btn-logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
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
            
            // Handle avatar upload
            const avatarInput = document.getElementById('avatar-upload');
            if (avatarInput) {
                avatarInput.addEventListener('change', function() {
                    // Validate file type on client side
                    const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];
                    const file = this.files[0];
                    
                    if (file && !allowedTypes.includes(file.type)) {
                        alert('Only JPG, JPEG, and PNG file types are allowed.');
                        this.value = ''; // Clear the input
                        return;
                    }
                    
                    // Validate file size (2MB)
                    const maxSize = 2 * 1024 * 1024; // 2MB in bytes
                    if (file && file.size > maxSize) {
                        alert('File size must be less than 2MB.');
                        this.value = ''; // Clear the input
                        return;
                    }
                    
                    this.form.submit();
                });
            }
        });
    </script>
</body>
</html>
<?php
// Close database connection
if (isset($conn)) {
    $conn->close();
}
?>