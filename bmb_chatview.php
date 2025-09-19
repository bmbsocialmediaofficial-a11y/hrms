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
// Add comments column to tasks table if it doesn't exist
$alterTableSQL = "
ALTER TABLE tasks 
ADD COLUMN IF NOT EXISTS comments VARCHAR(1001) DEFAULT NULL
";
$conn->exec($alterTableSQL);
// Create tasks table if it doesn't exist
$createTableSQL = "
CREATE TABLE IF NOT EXISTS tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    status ENUM('To Do', 'In Progress', 'In Review', 'On Hold', 'Done') DEFAULT 'To Do',
    priority ENUM('Low', 'Medium', 'High', 'Critical') DEFAULT 'Medium',
    assignee_id INT,
    reporter_id INT NOT NULL,
    due_date DATE,
    estimated_hours DECIMAL(5,2),
    actual_hours DECIMAL(5,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    attachments LONGBLOB,
    sprint_id INT,
    story_points INT DEFAULT 0,
    comments VARCHAR(1001) DEFAULT NULL
);
";
$conn->exec($createTableSQL);
// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_task'])) {
        $title = $_POST['title'];
        $description = $_POST['description'];
        $status = $_POST['status'];
        $priority = $_POST['priority'];
        $assignee_id = $_POST['assignee_id'];
        $due_date = $_POST['due_date'];
        $estimated_hours = $_POST['estimated_hours'];
        $story_points = $_POST['story_points'];
        $sprint_id = $_POST['sprint_id'];
        $comments = $_POST['comments'];
        
        // Handle file uploads
        $attachments_data = [];
        if (!empty($_FILES['attachments']['name'][0])) {
            $file_count = count($_FILES['attachments']['name']);
            for ($i = 0; $i < $file_count; $i++) {
                if ($_FILES['attachments']['error'][$i] === UPLOAD_ERR_OK) {
                    $file_name = $_FILES['attachments']['name'][$i];
                    $file_tmp = $_FILES['attachments']['tmp_name'][$i];
                    $file_content = file_get_contents($file_tmp);
                    
                    $attachments_data[] = [
                        'name' => $file_name,
                        'content' => $file_content,
                        'uploaded_at' => date('Y-m-d H:i:s')
                    ];
                }
            }
        }
        
        $attachments_blob = !empty($attachments_data) ? serialize($attachments_data) : null;
        
        $stmt = $conn->prepare("INSERT INTO tasks (title, description, status, priority, assignee_id, reporter_id, due_date, estimated_hours, story_points, sprint_id, attachments, comments) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$title, $description, $status, $priority, $assignee_id, $_SESSION['user_id'], $due_date, $estimated_hours, $story_points, $sprint_id, $attachments_blob, $comments]);
        
        header("Location: bmb_taskview.php");
        exit();
    }
    
    if (isset($_POST['update_task'])) {
        $task_id = $_POST['task_id'];
        $title = $_POST['title'];
        $description = $_POST['description'];
        $status = $_POST['status'];
        $priority = $_POST['priority'];
        $assignee_id = $_POST['assignee_id'];
        $due_date = $_POST['due_date'];
        $estimated_hours = $_POST['estimated_hours'];
        $actual_hours = $_POST['actual_hours'];
        $story_points = $_POST['story_points'];
        $sprint_id = $_POST['sprint_id'];
        $comments = $_POST['comments'];
        
        // Get existing attachments
        $stmt = $conn->prepare("SELECT attachments FROM tasks WHERE id = ?");
        $stmt->execute([$task_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $existing_attachments = $result['attachments'] ? unserialize($result['attachments']) : [];
        
        // Handle new file uploads
        if (!empty($_FILES['attachments']['name'][0])) {
            $file_count = count($_FILES['attachments']['name']);
            for ($i = 0; $i < $file_count; $i++) {
                if ($_FILES['attachments']['error'][$i] === UPLOAD_ERR_OK) {
                    $file_name = $_FILES['attachments']['name'][$i];
                    $file_tmp = $_FILES['attachments']['tmp_name'][$i];
                    $file_content = file_get_contents($file_tmp);
                    
                    $existing_attachments[] = [
                        'name' => $file_name,
                        'content' => $file_content,
                        'uploaded_at' => date('Y-m-d H:i:s')
                    ];
                }
            }
        }
        
        $attachments_blob = !empty($existing_attachments) ? serialize($existing_attachments) : null;
        
        $stmt = $conn->prepare("UPDATE tasks SET title=?, description=?, status=?, priority=?, assignee_id=?, due_date=?, estimated_hours=?, actual_hours=?, story_points=?, sprint_id=?, attachments=?, comments=? WHERE id=?");
        $stmt->execute([$title, $description, $status, $priority, $assignee_id, $due_date, $estimated_hours, $actual_hours, $story_points, $sprint_id, $attachments_blob, $comments, $task_id]);
        
        header("Location: bmb_taskview.php");
        exit();
    }
    
    // Update task status (changed from AJAX to form submission)
    if (isset($_POST['update_task_status'])) {
        $task_id = $_POST['task_id'];
        $status = $_POST['status'];
        
        $stmt = $conn->prepare("UPDATE tasks SET status=? WHERE id=?");
        $stmt->execute([$status, $task_id]);
        
        header("Location: bmb_taskview.php");
        exit();
    }
    
    // Get burn down chart data (changed from AJAX to form submission)
    if (isset($_POST['get_burn_down_data'])) {
        $sprint_id = isset($_POST['sprint_id']) ? $_POST['sprint_id'] : null;
        $burn_down_data = [];
        
        if ($sprint_id) {
            // Get sprint start and end dates
            $stmt = $conn->prepare("SELECT MIN(created_at) as start_date, MAX(due_date) as end_date FROM tasks WHERE sprint_id = ?");
            $stmt->execute([$sprint_id]);
            $sprint_dates = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($sprint_dates['start_date'] && $sprint_dates['end_date']) {
                $start_date = new DateTime($sprint_dates['start_date']);
                $end_date = new DateTime($sprint_dates['end_date']);
                $end_date->modify('+1 day'); // Include the end date
                
                $interval = new DateInterval('P1D');
                $date_range = new DatePeriod($start_date, $interval, $end_date);
                
                // Calculate remaining story points for each day
                foreach ($date_range as $date) {
                    $date_str = $date->format('Y-m-d');
                    $stmt = $conn->prepare("SELECT COALESCE(SUM(story_points), 0) as remaining_points 
                                           FROM tasks 
                                           WHERE sprint_id = ? AND (status != 'Done' OR (status = 'Done' AND updated_at > ?))");
                    $stmt->execute([$sprint_id, $date_str . ' 23:59:59']);
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    $burn_down_data[$date_str] = $result['remaining_points'];
                }
            }
        } else {
            // If no sprint is selected, use the default logic for all tasks
            for ($i = 6; $i >= 0; $i--) {
                $date = date('Y-m-d', strtotime("-$i days"));
                $stmt = $conn->prepare("SELECT COALESCE(SUM(story_points), 0) as total_points FROM tasks WHERE due_date <= ? AND status != 'Done'");
                $stmt->execute([$date]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $burn_down_data[$date] = $result['total_points'];
            }
        }
        
        // Store burn down data in session for use on redirect
        $_SESSION['burn_down_data'] = [
            'labels' => array_keys($burn_down_data),
            'data' => array_values($burn_down_data)
        ];
        
        header("Location: bmb_taskview.php?chart=1");
        exit();
    }
}
// Handle task deletion
if (isset($_GET['delete'])) {
    $task_id = $_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM tasks WHERE id = ?");
    $stmt->execute([$task_id]);
    
    header("Location: bmb_taskview.php");
    exit();
}
// Get task data for editing if task_id is provided
$edit_task = null;
if (isset($_GET['edit'])) {
    $task_id = $_GET['edit'];
    $stmt = $conn->prepare("SELECT t.*, e1.name as assignee_name, e2.name as reporter_name 
                           FROM tasks t 
                           LEFT JOIN employees e1 ON t.assignee_id = e1.id 
                           LEFT JOIN employees e2 ON t.reporter_id = e2.id 
                           WHERE t.id = ?");
    $stmt->execute([$task_id]);
    $edit_task = $stmt->fetch(PDO::FETCH_ASSOC);
}
// Get task data for viewing if task_id is provided
$view_task = null;
if (isset($_GET['view'])) {
    $task_id = $_GET['view'];
    $stmt = $conn->prepare("SELECT t.*, e1.name as assignee_name, e2.name as reporter_name 
                           FROM tasks t 
                           LEFT JOIN employees e1 ON t.assignee_id = e1.id 
                           LEFT JOIN employees e2 ON t.reporter_id = e2.id 
                           WHERE t.id = ?");
    $stmt->execute([$task_id]);
    $view_task = $stmt->fetch(PDO::FETCH_ASSOC);
}
// Get all tasks or filtered tasks
$current_user_id = $_SESSION['user_id'];
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$sprint_filter = isset($_GET['sprint_filter']) ? $_GET['sprint_filter'] : null;
// Base query
$query = "SELECT t.*, e1.name as assignee_name, e2.name as reporter_name 
          FROM tasks t 
          LEFT JOIN employees e1 ON t.assignee_id = e1.id 
          LEFT JOIN employees e2 ON t.reporter_id = e2.id ";
// Add WHERE clause based on filter
$params = [];
switch ($filter) {
    case 'todo':
        $query .= "WHERE t.status = 'To Do' ";
        break;
    case 'progress':
        $query .= "WHERE t.status = 'In Progress' ";
        break;
    case 'review':
        $query .= "WHERE t.status = 'In Review' ";
        break;
    case 'hold':
        $query .= "WHERE t.status = 'On Hold' ";
        break;
    case 'done':
        $query .= "WHERE t.status = 'Done' ";
        break;
    case 'my_tasks':
        $query .= "WHERE t.assignee_id = ? ";
        $params = [$current_user_id];
        break;
    case 'all':
    default:
        // No WHERE clause needed for 'all'
        break;
}
// Add sprint filter if specified
if ($sprint_filter !== null && $sprint_filter !== '') {
    if ($filter === 'all') {
        $query .= "WHERE t.sprint_id = ? ";
    } else {
        $query .= "AND t.sprint_id = ? ";
    }
    $params[] = $sprint_filter;
}
// Add ORDER BY clause
$query .= "ORDER BY t.created_at DESC";
// Execute query
$stmt = $conn->prepare($query);
$stmt->execute($params);
$tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
// Get tasks grouped by status for Kanban view
$kanban_tasks = [
    'To Do' => [],
    'In Progress' => [],
    'In Review' => [],
    'On Hold' => [],
    'Done' => []
];
// Only group tasks if we're showing all tasks or my_tasks
if ($filter === 'all' || $filter === 'my_tasks') {
    foreach ($tasks as $task) {
        if (isset($kanban_tasks[$task['status']])) {
            $kanban_tasks[$task['status']][] = $task;
        }
    }
} else {
    // For specific status filters, only show that status in the first column
    $statusMap = [
        'todo' => 'To Do',
        'progress' => 'In Progress',
        'review' => 'In Review',
        'hold' => 'On Hold',
        'done' => 'Done'
    ];
    
    if (isset($statusMap[$filter])) {
        $kanban_tasks[$statusMap[$filter]] = $tasks;
    }
}
// Get all employees for assignee dropdown and @mentions
$stmt = $conn->prepare("SELECT id, name FROM employees WHERE valid_user = true");
$stmt->execute();
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
// Get all unique sprint IDs for filters and burn down chart
$stmt = $conn->prepare("SELECT DISTINCT sprint_id FROM tasks WHERE sprint_id IS NOT NULL ORDER BY sprint_id");
$stmt->execute();
$sprints = $stmt->fetchAll(PDO::FETCH_ASSOC);
// Default to the most recent sprint if available
$selected_sprint = isset($_GET['sprint_id']) ? $_GET['sprint_id'] : (count($sprints) > 0 ? $sprints[count($sprints)-1]['sprint_id'] : null);
// Calculate burn down data (simplified)
$burn_down_data = [];
if ($selected_sprint) {
    // Get sprint start and end dates
    $stmt = $conn->prepare("SELECT MIN(created_at) as start_date, MAX(due_date) as end_date FROM tasks WHERE sprint_id = ?");
    $stmt->execute([$selected_sprint]);
    $sprint_dates = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($sprint_dates['start_date'] && $sprint_dates['end_date']) {
        $start_date = new DateTime($sprint_dates['start_date']);
        $end_date = new DateTime($sprint_dates['end_date']);
        $end_date->modify('+1 day'); // Include the end date
        
        $interval = new DateInterval('P1D');
        $date_range = new DatePeriod($start_date, $interval, $end_date);
        
        // Get total story points for the sprint
        $stmt = $conn->prepare("SELECT SUM(story_points) as total_points FROM tasks WHERE sprint_id = ?");
        $stmt->execute([$selected_sprint]);
        $total_points_result = $stmt->fetch(PDO::FETCH_ASSOC);
        $total_points = $total_points_result['total_points'] ?: 0;
        
        // Calculate remaining story points for each day
        foreach ($date_range as $date) {
            $date_str = $date->format('Y-m-d');
            $stmt = $conn->prepare("SELECT COALESCE(SUM(story_points), 0) as remaining_points 
                                   FROM tasks 
                                   WHERE sprint_id = ? AND (status != 'Done' OR (status = 'Done' AND updated_at > ?))");
            $stmt->execute([$selected_sprint, $date_str . ' 23:59:59']);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $burn_down_data[$date_str] = $result['remaining_points'];
        }
    }
} else {
    // If no sprint is selected, use the default logic for all tasks
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $stmt = $conn->prepare("SELECT COALESCE(SUM(story_points), 0) as total_points FROM tasks WHERE due_date <= ? AND status != 'Done'");
        $stmt->execute([$date]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $burn_down_data[$date] = $result['total_points'];
    }
}

// Check if we should use the burn down data from the session (after a manual refresh)
if (isset($_SESSION['burn_down_data'])) {
    $burn_down_data = $_SESSION['burn_down_data'];
    unset($_SESSION['burn_down_data']); // Clear it after use
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BMB Taskview - Task Management System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            color: #333;
            position: relative;
            overflow-x: hidden;
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
            min-height: 100vh;
            padding: 100px 20px 40px;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            color: white;
        }
        
        .header h1 {
            font-size: 2.8rem;
            margin-bottom: 10px;
            text-shadow: 2px 2px 8px rgba(0, 0, 0, 0.3);
        }
        
        .header p {
            font-size: 1.2rem;
            opacity: 0.9;
        }
        
        .view-toggle {
            display: flex;
            justify-content: center;
            margin-bottom: 20px;
            gap: 10px;
        }
        
        .view-btn {
            padding: 10px 20px;
            background: rgba(255, 255, 255, 0.9);
            border: 1px solid #6a11cb;
            border-radius: 20px;
            color: #6a11cb;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 600;
        }
        
        .view-btn.active, .view-btn:hover {
            background: #6a11cb;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
        }
        
        .filters {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
            justify-content: center;
            align-items: center;
        }
        
        .filter-btn {
            padding: 8px 15px;
            text-decoration: auto;
            background: rgba(255, 255, 255, 0.9);
            border: 1px solid #6a11cb;
            border-radius: 20px;
            color: #6a11cb;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 600;
        }
        
        .filter-btn.active, .filter-btn:hover {
            background: #6a11cb;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
        }
        
        .filter-btn.todo.active, .filter-btn.todo:hover {
            background: #dc3545;
            border-color: #dc3545;
        }
        
        .filter-btn.progress.active, .filter-btn.progress:hover {
            background: #fd7e14;
            border-color: #fd7e14;
        }
        
        .filter-btn.review.active, .filter-btn.review:hover {
            background: #ffc107;
            border-color: #ffc107;
            color: #333;
        }
        
        .filter-btn.hold.active, .filter-btn.hold:hover {
            background: #6f42c1;
            border-color: #6f42c1;
        }
        
        .filter-btn.done.active, .filter-btn.done:hover {
            background: #28a745;
            border-color: #28a745;
        }
        
        .filter-btn.mytasks.active, .filter-btn.mytasks:hover {
            background: #17a2b8;
            border-color: #17a2b8;
        }
        
        .filter-btn.sprint.active, .filter-btn.sprint:hover {
            background: #8e44ad;
            border-color: #8e44ad;
        }
        
        .sprint-filter {
            margin-left: 10px;
            padding: 8px 15px;
            border-radius: 20px;
            border: 1px solid #6a11cb;
            background: rgba(255, 255, 255, 0.9);
            color: #6a11cb;
            font-weight: 600;
        }
        
        .task-panel {
            background: rgba(255, 255, 255, 0.9);
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
            height: max-content;
            display: flex;
            flex-direction: column;
        }
        
        .task-panel-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .task-panel-header h2 {
            color: #6a11cb;
            display: flex;
            align-items: center;
        }
        
        .task-panel-header h2 i {
            margin-right: 10px;
        }
        
        .task-list-container {
            flex: 1;
            overflow-y: auto;
        }
        
        .task-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 15px;
        }
        
        .kanban-view {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 15px;
            height: 100%;
        }
        
        .kanban-column {
            background: rgba(255, 255, 255, 0.7);
            border-radius: 10px;
            padding: 15px;
            display: flex;
            flex-direction: column;
        }
        
        .kanban-column-header {
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 15px;
            text-align: center;
            font-weight: bold;
        }
        
        .kanban-column-todo .kanban-column-header {
            background: #dc3545;
            color: white;
        }
        
        .kanban-column-progress .kanban-column-header {
            background: #fd7e14;
            color: white;
        }
        
        .kanban-column-review .kanban-column-header {
            background: #ffc107;
            color: #333;
        }
        
        .kanban-column-hold .kanban-column-header {
            background: #6f42c1;
            color: white;
        }
        
        .kanban-column-done .kanban-column-header {
            background: #28a745;
            color: white;
        }
        
        .kanban-task-list {
            flex: 1;
            overflow-y: auto;
        }
        
        .task-item {
            background: white;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 10px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
            border-left: 4px solid #6a11cb;
            transition: all 0.3s;
            cursor: grab;
        }
        
        .task-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.15);
        }
        
        .task-item.critical {
            border-left-color: #dc3545;
        }
        
        .task-item.high {
            border-left-color: #fd7e14;
        }
        
        .task-item.medium {
            border-left-color: #ffc107;
        }
        
        .task-item.low {
            border-left-color: #28a745;
        }
        
        .task-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .task-title {
            font-weight: bold;
            font-size: 1.1rem;
        }
        
        .task-priority {
            padding: 3px 10px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: bold;
        }
        
        .priority-critical {
            background: #dc3545;
            color: white;
        }
        
        .priority-high {
            background: #fd7e14;
            color: white;
        }
        
        .priority-medium {
            background: #ffc107;
            color: black;
        }
        
        .priority-low {
            background: #28a745;
            color: white;
        }
        
        .task-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            font-size: 0.9rem;
            margin-bottom: 10px;
        }
        
        .task-detail {
            display: flex;
            align-items: center;
        }
        
        .task-detail i {
            margin-right: 5px;
            color: #6a11cb;
        }
        
        .task-attachments {
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid #eee;
        }
        
        .attachment-item {
            display: flex;
            align-items: center;
            margin-bottom: 5px;
        }
        
        .attachment-item a {
            color: #6a11cb;
            text-decoration: none;
            display: flex;
            align-items: center;
        }
        
        .attachment-item a:hover {
            text-decoration: underline;
        }
        
        .attachment-item i {
            margin-right: 5px;
        }
        
        .task-actions {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }
        
        .btn {
            padding: 6px 12px;
            border-radius: 5px;
            border: none;
            cursor: pointer;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            transition: all 0.3s;
        }
        
        .btn i {
            margin-right: 5px;
        }
        
        .btn-edit {
            background: #6a11cb;
            color: white;
        }
        
        .btn-edit:hover {
            background: #5a0fb7;
        }
        
        .btn-delete {
            background: #dc3545;
            color: white;
        }
        
        .btn-delete:hover {
            background: #bd2130;
        }
        
        .btn-view {
            background: #17a2b8;
            color: white;
        }
        
        .btn-view:hover {
            background: #138496;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        
        .modal-content {
            background: white;
            border-radius: 15px;
            padding: 30px;
            width: 90%;
            max-width: 800px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.3);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .close {
            font-size: 1.5rem;
            cursor: pointer;
            color: #999;
        }
        
        .close:hover {
            color: #333;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .btn-primary {
            background: #6a11cb;
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: bold;
            transition: all 0.3s;
        }
        
        .btn-primary:hover {
            background: #5a0fb7;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: bold;
            transition: all 0.3s;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }
        
        .chart-modal .modal-content {
            max-width: 800px;
        }
        
        .task-detail-modal .modal-content {
            max-width: 900px;
        }
        
        .task-detail-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .task-detail-section {
            margin-bottom: 20px;
        }
        
        .task-detail-section h3 {
            margin-bottom: 10px;
            color: #6a11cb;
            border-bottom: 2px solid #6a11cb;
            padding-bottom: 5px;
        }
        
        .task-detail-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }
        
        .task-detail-info .info-item {
            margin-bottom: 10px;
        }
        
        .task-detail-info .info-label {
            font-weight: bold;
            color: #6a11cb;
        }
        
        .task-description {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #6a11cb;
        }
        
        .attachment-preview {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 10px;
            margin-top: 10px;
        }
        
        .attachment-preview-item {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 10px;
            text-align: center;
            transition: all 0.3s;
        }
        
        .attachment-preview-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .attachment-preview-item i {
            font-size: 2rem;
            color: #6a11cb;
            margin-bottom: 5px;
        }
        
        .attachment-preview-item .attachment-name {
            font-size: 0.8rem;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .mentions-container {
            position: relative;
        }
        
        .mentions-dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #ddd;
            border-radius: 5px;
            max-height: 200px;
            overflow-y: auto;
            z-index: 100;
            display: none;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .mentions-dropdown-item {
            padding: 8px 12px;
            cursor: pointer;
            border-bottom: 1px solid #eee;
        }
        
        .mentions-dropdown-item:hover {
            background: #f0f0f0;
        }
        
        .mentions-dropdown-item:last-child {
            border-bottom: none;
        }
        
        .sprint-selector {
            margin-bottom: 15px;
            text-align: center;
        }
        
        .sprint-selector select {
            padding: 8px 15px;
            border-radius: 5px;
            border: 1px solid #ddd;
            background: white;
            font-size: 1rem;
            width: 200px;
        }
        
        .refresh-btn {
            background: #17a2b8;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 20px;
            cursor: pointer;
            font-weight: 600;
            margin-left: 10px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .refresh-btn:hover {
            background: #138496;
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
        }
        
        @media (max-width: 1200px) {
            .kanban-view {
                grid-template-columns: repeat(3, 1fr);
                grid-template-rows: auto auto;
            }
            
            .task-detail-content {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 992px) {
            .task-list {
                grid-template-columns: 1fr;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .header h1 {
                font-size: 2.2rem;
            }
            
            .task-details {
                grid-template-columns: 1fr;
            }
            
            .logo-container {
                position: relative;
                top: 0;
                left: 0;
                text-align: center;
                margin-bottom: 20px;
            }
            
            .container {
                padding-top: 20px;
            }
            
            .filters {
                justify-content: center;
            }
            
            .kanban-view {
                grid-template-columns: 1fr;
                grid-template-rows: repeat(5, 1fr);
            }
            
            .task-detail-info {
                grid-template-columns: 1fr;
            }
        }
        
        .btn-logout {
            background: rgba(255, 255, 255, 0.15);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.3);
            text-decoration: none;
        }
        .btn-logout:hover {
            background: rgba(255, 100, 100, 0.2);
            color: #ffcccc;
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
        }
    </style>
</head>
<body>
    <div class="logo-container">
        <img src="https://ik.imagekit.io/nuvq7aygp/BMB_Logo.jpg" alt="BMB Logo" class="logo">
    </div>
    
    <div class="container">
        <div class="header">
            <h1>BMB Taskview</h1>
            <p>Manage your tasks efficiently with our BMB task management system</p>
        </div>
        
        <div class="view-toggle">
            <button class="view-btn active" id="listViewBtn">
                <i class="fas fa-list"></i> List View
            </button>
            <button class="view-btn" id="kanbanViewBtn">
                <i class="fas fa-columns"></i> Kanban View
            </button>
            <button class="btn-primary" onclick="openModal('createTaskModal')">
                <i class="fas fa-plus"></i> Create New Task
            </button>
            <button class="btn-secondary" onclick="openModal('chartModal')">
                <i class="fas fa-chart-line"></i> View Burn Down Chart
            </button>
            
            <a href="start.php" class="btn btn-logout">
                <i class="fas fa-sign-out-alt"></i> Home
            </a>
            
            <a href="logout.php" class="btn btn-logout">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
        
        <div class="filters">
            <a href="bmb_taskview.php?filter=all" class="filter-btn <?php echo $filter === 'all' && $sprint_filter === null ? 'active' : ''; ?>">All Tasks</a>
            <a href="bmb_taskview.php?filter=todo" class="filter-btn todo <?php echo $filter === 'todo' ? 'active' : ''; ?>">To Do</a>
            <a href="bmb_taskview.php?filter=progress" class="filter-btn progress <?php echo $filter === 'progress' ? 'active' : ''; ?>">In Progress</a>
            <a href="bmb_taskview.php?filter=review" class="filter-btn review <?php echo $filter === 'review' ? 'active' : ''; ?>">In Review</a>
            <a href="bmb_taskview.php?filter=hold" class="filter-btn hold <?php echo $filter === 'hold' ? 'active' : ''; ?>">On Hold</a>
            <a href="bmb_taskview.php?filter=done" class="filter-btn done <?php echo $filter === 'done' ? 'active' : ''; ?>">Done</a>
            <a href="bmb_taskview.php?filter=my_tasks" class="filter-btn mytasks <?php echo $filter === 'my_tasks' ? 'active' : ''; ?>">My Tasks</a>
            
            <?php if (!empty($sprints)): ?>
            <select class="sprint-filter" onchange="applySprintFilter(this.value)">
                <option value="">All Sprints</option>
                <option value="0" <?php echo $sprint_filter === '0' ? 'selected' : ''; ?>>Sprint 0</option>
                <?php foreach ($sprints as $sprint): ?>
                    <?php if ($sprint['sprint_id'] != 0): ?>
                    <option value="<?php echo $sprint['sprint_id']; ?>" <?php echo $sprint_filter == $sprint['sprint_id'] ? 'selected' : ''; ?>>
                        Sprint <?php echo $sprint['sprint_id']; ?>
                    </option>
                    <?php endif; ?>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>
            
            <button class="refresh-btn" onclick="refreshPage()">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
        </div>
        
        <div class="task-panel">
            <div class="task-panel-header">
                <h2><i class="fas fa-tasks"></i> Task List</h2>
                <span id="task-count"><?php echo count($tasks); ?> tasks</span>
            </div>
            
            <div class="task-list-container">
                <!-- List View -->
                <div id="list-view">
                    <div class="task-list">
                        <?php foreach ($tasks as $task): 
                            $attachments = $task['attachments'] ? unserialize($task['attachments']) : [];
                        ?>
                            <div class="task-item <?php echo strtolower($task['priority']); ?>" data-task-id="<?php echo $task['id']; ?>" data-status="<?php echo $task['status']; ?>">
                                <div class="task-header">
                                    <div class="task-title"><?php echo htmlspecialchars($task['title']); ?></div>
                                    <span class="task-priority priority-<?php echo strtolower($task['priority']); ?>">
                                        <?php echo $task['priority']; ?>
                                    </span>
                                </div>
                                <div class="task-details">
                                    <div class="task-detail">
                                        <i class="fas fa-user"></i>
                                        <span><?php echo htmlspecialchars($task['assignee_name'] ?? 'Unassigned'); ?></span>
                                    </div>
                                    <div class="task-detail">
                                        <i class="fas fa-flag"></i>
                                        <span><?php echo $task['status']; ?></span>
                                    </div>
                                    <div class="task-detail">
                                        <i class="fas fa-clock"></i>
                                        <span>Est: <?php echo $task['estimated_hours']; ?>h</span>
                                    </div>
                                    <div class="task-detail">
                                        <i class="fas fa-calendar"></i>
                                        <span><?php echo $task['due_date'] ? date('M j, Y', strtotime($task['due_date'])) : 'No due date'; ?></span>
                                    </div>
                                </div>
                                
                                <?php if ($task['sprint_id'] !== null && $task['sprint_id'] !== ''): ?>
                                <div class="task-detail">
                                    <i class="fas fa-running"></i>
                                    <span>Sprint: <?php echo $task['sprint_id']; ?></span>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($task['comments'])): ?>
                                <div class="task-attachments">
                                    <strong><i class="fas fa-comment"></i> Comments:</strong>
                                    <div><?php echo htmlspecialchars($task['comments']); ?></div>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($attachments)): ?>
                                <div class="task-attachments">
                                    <strong><i class="fas fa-paperclip"></i> Attachments:</strong>
                                    <?php foreach ($attachments as $attachment): ?>
                                    <div class="attachment-item">
                                        <a href="download_attachment.php?task_id=<?php echo $task['id']; ?>&attachment_index=<?php echo array_search($attachment, $attachments); ?>" download="<?php echo htmlspecialchars($attachment['name']); ?>">
                                            <i class="fas fa-download"></i> <?php echo htmlspecialchars($attachment['name']); ?>
                                        </a>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                                
                                <div class="task-actions">
                                    <button class="btn btn-view" onclick="viewTask(<?php echo $task['id']; ?>)">
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                    <button class="btn btn-edit" onclick="editTask(<?php echo $task['id']; ?>)">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <button class="btn btn-delete" onclick="deleteTask(<?php echo $task['id']; ?>)">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Kanban View -->
                <div id="kanban-view" style="display: none;">
                    <div class="kanban-view">
                        <div class="kanban-column kanban-column-todo">
                            <div class="kanban-column-header">To Do</div>
                            <div class="kanban-task-list" data-status="To Do">
                                <?php foreach ($kanban_tasks['To Do'] as $task): 
                                    $attachments = $task['attachments'] ? unserialize($task['attachments']) : [];
                                ?>
                                    <div class="task-item <?php echo strtolower($task['priority']); ?>" data-task-id="<?php echo $task['id']; ?>">
                                        <div class="task-header">
                                            <div class="task-title"><?php echo htmlspecialchars($task['title']); ?></div>
                                            <span class="task-priority priority-<?php echo strtolower($task['priority']); ?>">
                                                <?php echo $task['priority']; ?>
                                            </span>
                                        </div>
                                        <div class="task-details">
                                            <div class="task-detail">
                                                <i class="fas fa-user"></i>
                                                <span><?php echo htmlspecialchars($task['assignee_name'] ?? 'Unassigned'); ?></span>
                                            </div>
                                            <div class="task-detail">
                                                <i class="fas fa-clock"></i>
                                                <span>Est: <?php echo $task['estimated_hours']; ?>h</span>
                                            </div>
                                            <div class="task-detail">
                                                <i class="fas fa-calendar"></i>
                                                <span><?php echo $task['due_date'] ? date('M j, Y', strtotime($task['due_date'])) : 'No due date'; ?></span>
                                            </div>
                                        </div>
                                        <?php if ($task['sprint_id'] !== null && $task['sprint_id'] !== ''): ?>
                                        <div class="task-detail">
                                            <i class="fas fa-running"></i>
                                            <span>Sprint: <?php echo $task['sprint_id']; ?></span>
                                        </div>
                                        <?php endif; ?>
                                        <div class="task-actions">
                                            <button class="btn btn-view" onclick="viewTask(<?php echo $task['id']; ?>)">
                                                <i class="fas fa-eye"></i> View
                                            </button>
                                            <button class="btn btn-edit" onclick="editTask(<?php echo $task['id']; ?>)">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <div class="kanban-column kanban-column-progress">
                            <div class="kanban-column-header">In Progress</div>
                            <div class="kanban-task-list" data-status="In Progress">
                                <?php foreach ($kanban_tasks['In Progress'] as $task): 
                                    $attachments = $task['attachments'] ? unserialize($task['attachments']) : [];
                                ?>
                                    <div class="task-item <?php echo strtolower($task['priority']); ?>" data-task-id="<?php echo $task['id']; ?>">
                                        <div class="task-header">
                                            <div class="task-title"><?php echo htmlspecialchars($task['title']); ?></div>
                                            <span class="task-priority priority-<?php echo strtolower($task['priority']); ?>">
                                                <?php echo $task['priority']; ?>
                                            </span>
                                        </div>
                                        <div class="task-details">
                                            <div class="task-detail">
                                                <i class="fas fa-user"></i>
                                                <span><?php echo htmlspecialchars($task['assignee_name'] ?? 'Unassigned'); ?></span>
                                            </div>
                                            <div class="task-detail">
                                                <i class="fas fa-clock"></i>
                                                <span>Est: <?php echo $task['estimated_hours']; ?>h</span>
                                            </div>
                                            <div class="task-detail">
                                                <i class="fas fa-calendar"></i>
                                                <span><?php echo $task['due_date'] ? date('M j, Y', strtotime($task['due_date'])) : 'No due date'; ?></span>
                                            </div>
                                        </div>
                                        <?php if ($task['sprint_id'] !== null && $task['sprint_id'] !== ''): ?>
                                        <div class="task-detail">
                                            <i class="fas fa-running"></i>
                                            <span>Sprint: <?php echo $task['sprint_id']; ?></span>
                                        </div>
                                        <?php endif; ?>
                                        <div class="task-actions">
                                            <button class="btn btn-view" onclick="viewTask(<?php echo $task['id']; ?>)">
                                                <i class="fas fa-eye"></i> View
                                            </button>
                                            <button class="btn btn-edit" onclick="editTask(<?php echo $task['id']; ?>)">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <div class="kanban-column kanban-column-review">
                            <div class="kanban-column-header">In Review</div>
                            <div class="kanban-task-list" data-status="In Review">
                                <?php foreach ($kanban_tasks['In Review'] as $task): 
                                    $attachments = $task['attachments'] ? unserialize($task['attachments']) : [];
                                ?>
                                    <div class="task-item <?php echo strtolower($task['priority']); ?>" data-task-id="<?php echo $task['id']; ?>">
                                        <div class="task-header">
                                            <div class="task-title"><?php echo htmlspecialchars($task['title']); ?></div>
                                            <span class="task-priority priority-<?php echo strtolower($task['priority']); ?>">
                                                <?php echo $task['priority']; ?>
                                            </span>
                                        </div>
                                        <div class="task-details">
                                            <div class="task-detail">
                                                <i class="fas fa-user"></i>
                                                <span><?php echo htmlspecialchars($task['assignee_name'] ?? 'Unassigned'); ?></span>
                                            </div>
                                            <div class="task-detail">
                                                <i class="fas fa-clock"></i>
                                                <span>Est: <?php echo $task['estimated_hours']; ?>h</span>
                                            </div>
                                            <div class="task-detail">
                                                <i class="fas fa-calendar"></i>
                                                <span><?php echo $task['due_date'] ? date('M j, Y', strtotime($task['due_date'])) : 'No due date'; ?></span>
                                            </div>
                                        </div>
                                        <?php if ($task['sprint_id'] !== null && $task['sprint_id'] !== ''): ?>
                                        <div class="task-detail">
                                            <i class="fas fa-running"></i>
                                            <span>Sprint: <?php echo $task['sprint_id']; ?></span>
                                        </div>
                                        <?php endif; ?>
                                        <div class="task-actions">
                                            <button class="btn btn-view" onclick="viewTask(<?php echo $task['id']; ?>)">
                                                <i class="fas fa-eye"></i> View
                                            </button>
                                            <button class="btn btn-edit" onclick="editTask(<?php echo $task['id']; ?>)">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <div class="kanban-column kanban-column-hold">
                            <div class="kanban-column-header">On Hold</div>
                            <div class="kanban-task-list" data-status="On Hold">
                                <?php foreach ($kanban_tasks['On Hold'] as $task): 
                                    $attachments = $task['attachments'] ? unserialize($task['attachments']) : [];
                                ?>
                                    <div class="task-item <?php echo strtolower($task['priority']); ?>" data-task-id="<?php echo $task['id']; ?>">
                                        <div class="task-header">
                                            <div class="task-title"><?php echo htmlspecialchars($task['title']); ?></div>
                                            <span class="task-priority priority-<?php echo strtolower($task['priority']); ?>">
                                                <?php echo $task['priority']; ?>
                                            </span>
                                        </div>
                                        <div class="task-details">
                                            <div class="task-detail">
                                                <i class="fas fa-user"></i>
                                                <span><?php echo htmlspecialchars($task['assignee_name'] ?? 'Unassigned'); ?></span>
                                            </div>
                                            <div class="task-detail">
                                                <i class="fas fa-clock"></i>
                                                <span>Est: <?php echo $task['estimated_hours']; ?>h</span>
                                            </div>
                                            <div class="task-detail">
                                                <i class="fas fa-calendar"></i>
                                                <span><?php echo $task['due_date'] ? date('M j, Y', strtotime($task['due_date'])) : 'No due date'; ?></span>
                                            </div>
                                        </div>
                                        <?php if ($task['sprint_id'] !== null && $task['sprint_id'] !== ''): ?>
                                        <div class="task-detail">
                                            <i class="fas fa-running"></i>
                                            <span>Sprint: <?php echo $task['sprint_id']; ?></span>
                                        </div>
                                        <?php endif; ?>
                                        <div class="task-actions">
                                            <button class="btn btn-view" onclick="viewTask(<?php echo $task['id']; ?>)">
                                                <i class="fas fa-eye"></i> View
                                            </button>
                                            <button class="btn btn-edit" onclick="editTask(<?php echo $task['id']; ?>)">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <div class="kanban-column kanban-column-done">
                            <div class="kanban-column-header">Done</div>
                            <div class="kanban-task-list" data-status="Done">
                                <?php foreach ($kanban_tasks['Done'] as $task): 
                                    $attachments = $task['attachments'] ? unserialize($task['attachments']) : [];
                                ?>
                                    <div class="task-item <?php echo strtolower($task['priority']); ?>" data-task-id="<?php echo $task['id']; ?>">
                                        <div class="task-header">
                                            <div class="task-title"><?php echo htmlspecialchars($task['title']); ?></div>
                                            <span class="task-priority priority-<?php echo strtolower($task['priority']); ?>">
                                                <?php echo $task['priority']; ?>
                                            </span>
                                        </div>
                                        <div class="task-details">
                                            <div class="task-detail">
                                                <i class="fas fa-user"></i>
                                                <span><?php echo htmlspecialchars($task['assignee_name'] ?? 'Unassigned'); ?></span>
                                            </div>
                                            <div class="task-detail">
                                                <i class="fas fa-clock"></i>
                                                <span>Est: <?php echo $task['estimated_hours']; ?>h</span>
                                            </div>
                                            <div class="task-detail">
                                                <i class="fas fa-calendar"></i>
                                                <span><?php echo $task['due_date'] ? date('M j, Y', strtotime($task['due_date'])) : 'No due date'; ?></span>
                                            </div>
                                        </div>
                                        <?php if ($task['sprint_id'] !== null && $task['sprint_id'] !== ''): ?>
                                        <div class="task-detail">
                                            <i class="fas fa-running"></i>
                                            <span>Sprint: <?php echo $task['sprint_id']; ?></span>
                                        </div>
                                        <?php endif; ?>
                                        <div class="task-actions">
                                            <button class="btn btn-view" onclick="viewTask(<?php echo $task['id']; ?>)">
                                                <i class="fas fa-eye"></i> View
                                            </button>
                                            <button class="btn btn-edit" onclick="editTask(<?php echo $task['id']; ?>)">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Create Task Modal -->
    <div id="createTaskModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Create New Task</h2>
                <span class="close" onclick="closeModal('createTaskModal')">&times;</span>
            </div>
            <form action="bmb_taskview.php" method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="title">Task Title</label>
                    <input type="text" id="title" name="title" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" class="form-control" rows="4"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="comments">Comments</label>
                    <div class="mentions-container">
                        <textarea id="comments" name="comments" class="form-control" rows="3" placeholder="Use @ to mention employees..."></textarea>
                        <div id="mentions-dropdown" class="mentions-dropdown"></div>
                    </div>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select id="status" name="status" class="form-control" required>
                            <option value="To Do">To Do</option>
                            <option value="In Progress">In Progress</option>
                            <option value="In Review">In Review</option>
                            <option value="On Hold">On Hold</option>
                            <option value="Done">Done</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="priority">Priority</label>
                        <select id="priority" name="priority" class="form-control" required>
                            <option value="Low">Low</option>
                            <option value="Medium" selected>Medium</option>
                            <option value="High">High</option>
                            <option value="Critical">Critical</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="assignee_id">Assignee</label>
                        <select id="assignee_id" name="assignee_id" class="form-control">
                            <option value="">Unassigned</option>
                            <?php foreach ($employees as $employee): ?>
                                <option value="<?php echo $employee['id']; ?>"><?php echo htmlspecialchars($employee['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="due_date">Due Date</label>
                        <input type="date" id="due_date" name="due_date" class="form-control">
                    </div>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="estimated_hours">Estimated Hours</label>
                        <input type="number" id="estimated_hours" name="estimated_hours" class="form-control" step="0.5" min="0">
                    </div>
                    
                    <div class="form-group">
                        <label for="story_points">Story Points</label>
                        <input type="number" id="story_points" name="story_points" class="form-control" min="0" value="0">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="sprint_id">Sprint ID</label>
                    <input type="number" id="sprint_id" name="sprint_id" class="form-control" min="0">
                </div>
                
                <div class="form-group">
                    <label for="attachments">Attachments</label>
                    <input type="file" id="attachments" name="attachments[]" class="form-control" multiple>
                </div>
                
                <button type="submit" name="create_task" class="btn-primary">Create Task</button>
                <button type="button" class="btn-secondary" onclick="closeModal('createTaskModal')">Cancel</button>
            </form>
        </div>
    </div>
    
    <!-- Edit Task Modal -->
    <div id="editTaskModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit Task</h2>
                <span class="close" onclick="closeModal('editTaskModal')">&times;</span>
            </div>
            <form action="bmb_taskview.php" method="POST">
                <input type="hidden" id="edit_task_id" name="task_id" value="<?php echo $edit_task['id'] ?? ''; ?>">
                
                <div class="form-group">
                    <label for="edit_title">Task Title</label>
                    <input type="text" id="edit_title" name="title" class="form-control" value="<?php echo $edit_task['title'] ?? ''; ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_description">Description</label>
                    <textarea id="edit_description" name="description" class="form-control" rows="4"><?php echo $edit_task['description'] ?? ''; ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="edit_comments">Comments</label>
                    <div class="mentions-container">
                        <textarea id="edit_comments" name="comments" class="form-control" rows="3" placeholder="Use @ to mention employees..."><?php echo $edit_task['comments'] ?? ''; ?></textarea>
                        <div id="edit-mentions-dropdown" class="mentions-dropdown"></div>
                    </div>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="edit_status">Status</label>
                        <select id="edit_status" name="status" class="form-control" required>
                            <option value="To Do" <?php echo ($edit_task['status'] ?? '') == 'To Do' ? 'selected' : ''; ?>>To Do</option>
                            <option value="In Progress" <?php echo ($edit_task['status'] ?? '') == 'In Progress' ? 'selected' : ''; ?>>In Progress</option>
                            <option value="In Review" <?php echo ($edit_task['status'] ?? '') == 'In Review' ? 'selected' : ''; ?>>In Review</option>
                            <option value="On Hold" <?php echo ($edit_task['status'] ?? '') == 'On Hold' ? 'selected' : ''; ?>>On Hold</option>
                            <option value="Done" <?php echo ($edit_task['status'] ?? '') == 'Done' ? 'selected' : ''; ?>>Done</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_priority">Priority</label>
                        <select id="edit_priority" name="priority" class="form-control" required>
                            <option value="Low" <?php echo ($edit_task['priority'] ?? '') == 'Low' ? 'selected' : ''; ?>>Low</option>
                            <option value="Medium" <?php echo ($edit_task['priority'] ?? '') == 'Medium' ? 'selected' : ''; ?>>Medium</option>
                            <option value="High" <?php echo ($edit_task['priority'] ?? '') == 'High' ? 'selected' : ''; ?>>High</option>
                            <option value="Critical" <?php echo ($edit_task['priority'] ?? '') == 'Critical' ? 'selected' : ''; ?>>Critical</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="edit_assignee_id">Assignee</label>
                        <select id="edit_assignee_id" name="assignee_id" class="form-control">
                            <option value="">Unassigned</option>
                            <?php foreach ($employees as $employee): ?>
                                <option value="<?php echo $employee['id']; ?>" <?php echo ($edit_task['assignee_id'] ?? '') == $employee['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($employee['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_due_date">Due Date</label>
                        <input type="date" id="edit_due_date" name="due_date" class="form-control" value="<?php echo $edit_task['due_date'] ?? ''; ?>">
                    </div>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="edit_estimated_hours">Estimated Hours</label>
                        <input type="number" id="edit_estimated_hours" name="estimated_hours" class="form-control" step="0.5" min="0" value="<?php echo $edit_task['estimated_hours'] ?? ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_actual_hours">Actual Hours</label>
                        <input type="number" id="edit_actual_hours" name="actual_hours" class="form-control" step="0.5" min="0" value="<?php echo $edit_task['actual_hours'] ?? ''; ?>">
                    </div>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="edit_story_points">Story Points</label>
                        <input type="number" id="edit_story_points" name="story_points" class="form-control" min="0" value="<?php echo $edit_task['story_points'] ?? ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_sprint_id">Sprint ID</label>
                        <input type="number" id="edit_sprint_id" name="sprint_id" class="form-control" min="0" value="<?php echo $edit_task['sprint_id'] ?? ''; ?>">
                    </div>
                </div>
                
                <?php if ($edit_task && $edit_task['attachments']): 
                    $attachments = unserialize($edit_task['attachments']);
                ?>
                <div class="form-group">
                    <label>Existing Attachments</label>
                    <?php foreach ($attachments as $index => $attachment): ?>
                    <div class="attachment-item">
                        <a href="download_attachment.php?task_id=<?php echo $edit_task['id']; ?>&attachment_index=<?php echo $index; ?>" download="<?php echo htmlspecialchars($attachment['name']); ?>">
                            <i class="fas fa-download"></i> <?php echo htmlspecialchars($attachment['name']); ?>
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                
                <div class="form-group">
                    <label for="edit_attachments">Add New Attachments</label>
                    <input type="file" id="edit_attachments" name="attachments[]" class="form-control" multiple>
                </div>
                
                <button type="submit" name="update_task" class="btn-primary">Update Task</button>
                <button type="button" class="btn-secondary" onclick="closeModal('editTaskModal')">Cancel</button>
            </form>
        </div>
    </div>
    
    <!-- Task Detail View Modal -->
    <div id="viewTaskModal" class="modal task-detail-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Task Details</h2>
                <span class="close" onclick="closeModal('viewTaskModal')">&times;</span>
            </div>
            <div class="task-detail-content">
                <div>
                    <div class="task-detail-section">
                        <h3>Basic Information</h3>
                        <div class="task-detail-info">
                            <div class="info-item">
                                <span class="info-label">Title:</span>
                                <span id="view_title"></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Status:</span>
                                <span id="view_status"></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Priority:</span>
                                <span id="view_priority"></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Assignee:</span>
                                <span id="view_assignee"></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Reporter:</span>
                                <span id="view_reporter"></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Due Date:</span>
                                <span id="view_due_date"></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Estimated Hours:</span>
                                <span id="view_estimated_hours"></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Actual Hours:</span>
                                <span id="view_actual_hours"></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Story Points:</span>
                                <span id="view_story_points"></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Sprint ID:</span>
                                <span id="view_sprint_id"></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Created:</span>
                                <span id="view_created"></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Last Updated:</span>
                                <span id="view_updated"></span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div>
                    <div class="task-detail-section">
                        <h3>Description</h3>
                        <div class="task-description" id="view_description"></div>
                    </div>
                    
                    <div class="task-detail-section">
                        <h3>Comments</h3>
                        <div class="task-description" id="view_comments"></div>
                    </div>
                    
                    <div class="task-detail-section">
                        <h3>Attachments</h3>
                        <div class="attachment-preview" id="view_attachments"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Burn Down Chart Modal -->
    <div id="chartModal" class="modal chart-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Burn Down Chart</h2>
                <span class="close" onclick="closeModal('chartModal')">&times;</span>
            </div>
            
            <div class="sprint-selector">
                <label for="sprint-select">Select Sprint: </label>
                <select id="sprint-select" onchange="updateBurnDownChart()">
                    <option value="">All Tasks</option>
                    <?php foreach ($sprints as $sprint): ?>
                        <option value="<?php echo $sprint['sprint_id']; ?>" <?php echo $selected_sprint == $sprint['sprint_id'] ? 'selected' : ''; ?>>
                            Sprint <?php echo $sprint['sprint_id']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <button type="button" class="refresh-btn" onclick="refreshChartData()">
                    <i class="fas fa-sync-alt"></i> Refresh Data
                </button>
            </div>
            
            <div class="chart-container">
                <canvas id="burnDownChart"></canvas>
            </div>
        </div>
    </div>
    
    <script>
        // Employee data for @mentions
        const employees = <?php echo json_encode($employees); ?>;
        
        // Global variable for the burn down chart
        let burnDownChart;
        
        // Modal functions
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'flex';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        // Close modal when clicking outside of it
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
        
        // Refresh page function
        function refreshPage() {
            window.location.reload();
        }
        
        // Edit task function
        function editTask(taskId) {
            window.location.href = 'bmb_taskview.php?edit=' + taskId;
        }
        
        // View task function
        function viewTask(taskId) {
            window.location.href = 'bmb_taskview.php?view=' + taskId;
        }
        
        // Delete task function
        function deleteTask(taskId) {
            if (confirm('Are you sure you want to delete this task?')) {
                window.location.href = 'bmb_taskview.php?delete=' + taskId;
            }
        }
        
        // Apply sprint filter
        function applySprintFilter(sprintId) {
            const currentFilter = '<?php echo $filter; ?>';
            let url = 'bmb_taskview.php?filter=' + currentFilter;
            
            // Only add sprint_filter if it's not empty
            if (sprintId !== '') {
                url += '&sprint_filter=' + sprintId;
            }
            
            window.location.href = url;
        }
        
        // Update burn down chart based on selected sprint (changed to manual refresh)
        function updateBurnDownChart() {
            // This function now just shows a message to the user
            const sprintId = document.getElementById('sprint-select').value;
            alert(`Chart will be updated for Sprint ${sprintId || 'All Tasks'} when you manually refresh the data.`);
        }
        
        // Manual refresh for burn down chart data
        function refreshChartData() {
            const sprintId = document.getElementById('sprint-select').value;
            
            // Create a form to submit the request
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'bmb_taskview.php';
            
            // Add the get_burn_down_data parameter
            const input1 = document.createElement('input');
            input1.type = 'hidden';
            input1.name = 'get_burn_down_data';
            input1.value = 'true';
            form.appendChild(input1);
            
            // Add the sprint_id parameter
            const input2 = document.createElement('input');
            input2.type = 'hidden';
            input2.name = 'sprint_id';
            input2.value = sprintId;
            form.appendChild(input2);
            
            // Submit the form
            document.body.appendChild(form);
            form.submit();
        }
        
        // @mentions functionality
        function setupMentions(textareaId, dropdownId) {
            const textarea = document.getElementById(textareaId);
            const dropdown = document.getElementById(dropdownId);
            let currentMention = null;
            
            textarea.addEventListener('input', function() {
                const cursorPos = textarea.selectionStart;
                const textBeforeCursor = textarea.value.substring(0, cursorPos);
                
                // Check if @ is typed
                const atPos = textBeforeCursor.lastIndexOf('@');
                if (atPos !== -1 && (atPos === 0 || textBeforeCursor.charAt(atPos - 1) === ' ')) {
                    const mentionText = textBeforeCursor.substring(atPos + 1);
                    
                    // Show dropdown with matching employees
                    if (mentionText.length > 0) {
                        const matches = employees.filter(emp => 
                            emp.name.toLowerCase().includes(mentionText.toLowerCase())
                        );
                        
                        if (matches.length > 0) {
                            dropdown.innerHTML = '';
                            matches.forEach(emp => {
                                const item = document.createElement('div');
                                item.className = 'mentions-dropdown-item';
                                item.textContent = emp.name;
                                item.addEventListener('click', function() {
                                    // Replace the mention text with the selected employee
                                    const textAfterCursor = textarea.value.substring(cursorPos);
                                    textarea.value = textBeforeCursor.substring(0, atPos) + '@' + emp.name + ' ' + textAfterCursor;
                                    dropdown.style.display = 'none';
                                    textarea.focus();
                                });
                                dropdown.appendChild(item);
                            });
                            
                            // Position dropdown
                            dropdown.style.top = '100%';
                            dropdown.style.left = '0';
                            dropdown.style.width = '100%';
                            dropdown.style.display = 'block';
                            
                            currentMention = {
                                start: atPos,
                                end: cursorPos
                            };
                        } else {
                            dropdown.style.display = 'none';
                        }
                    } else {
                        dropdown.style.display = 'none';
                    }
                } else {
                    dropdown.style.display = 'none';
                }
            });
            
            // Hide dropdown when clicking outside
            document.addEventListener('click', function(e) {
                if (e.target !== textarea && !dropdown.contains(e.target)) {
                    dropdown.style.display = 'none';
                }
            });
        }
        
        // View toggle functionality
        document.addEventListener('DOMContentLoaded', function() {
            const listViewBtn = document.getElementById('listViewBtn');
            const kanbanViewBtn = document.getElementById('kanbanViewBtn');
            const listView = document.getElementById('list-view');
            const kanbanView = document.getElementById('kanban-view');
            
            listViewBtn.addEventListener('click', function() {
                listView.style.display = 'block';
                kanbanView.style.display = 'none';
                listViewBtn.classList.add('active');
                kanbanViewBtn.classList.remove('active');
            });
            
            kanbanViewBtn.addEventListener('click', function() {
                listView.style.display = 'none';
                kanbanView.style.display = 'block';
                listViewBtn.classList.remove('active');
                kanbanViewBtn.classList.add('active');
            });
            
            // Setup @mentions for comment fields
            setupMentions('comments', 'mentions-dropdown');
            setupMentions('edit_comments', 'edit-mentions-dropdown');
            
            // Open edit modal if edit parameter is present
            <?php if (isset($_GET['edit']) && $edit_task): ?>
            openModal('editTaskModal');
            <?php endif; ?>
            
            // Open view modal if view parameter is present
            <?php if (isset($_GET['view']) && $view_task): ?>
            // Populate view modal with task data
            document.getElementById('view_title').textContent = '<?php echo addslashes($view_task["title"]); ?>';
            document.getElementById('view_status').textContent = '<?php echo $view_task["status"]; ?>';
            document.getElementById('view_priority').textContent = '<?php echo $view_task["priority"]; ?>';
            document.getElementById('view_assignee').textContent = '<?php echo addslashes($view_task["assignee_name"] ?? "Unassigned"); ?>';
            document.getElementById('view_reporter').textContent = '<?php echo addslashes($view_task["reporter_name"]); ?>';
            document.getElementById('view_due_date').textContent = '<?php echo $view_task["due_date"] ? date("M j, Y", strtotime($view_task["due_date"])) : "No due date"; ?>';
            document.getElementById('view_estimated_hours').textContent = '<?php echo $view_task["estimated_hours"]; ?> hours';
            document.getElementById('view_actual_hours').textContent = '<?php echo $view_task["actual_hours"]; ?> hours';
            document.getElementById('view_story_points').textContent = '<?php echo $view_task["story_points"]; ?>';
            document.getElementById('view_sprint_id').textContent = '<?php echo $view_task["sprint_id"] !== null && $view_task["sprint_id"] !== "" ? $view_task["sprint_id"] : "None"; ?>';
            document.getElementById('view_created').textContent = '<?php echo date("M j, Y H:i", strtotime($view_task["created_at"])); ?>';
            document.getElementById('view_updated').textContent = '<?php echo date("M j, Y H:i", strtotime($view_task["updated_at"])); ?>';
            document.getElementById('view_description').textContent = '<?php echo addslashes($view_task["description"] ?? "No description provided"); ?>';
            document.getElementById('view_comments').textContent = '<?php echo addslashes($view_task["comments"] ?? "No comments"); ?>';
            
            // Populate attachments
            const attachmentsContainer = document.getElementById('view_attachments');
            attachmentsContainer.innerHTML = '';
            
            <?php 
            if ($view_task['attachments']) {
                $attachments = unserialize($view_task['attachments']);
                foreach ($attachments as $index => $attachment): 
            ?>
                const attachmentItem = document.createElement('div');
                attachmentItem.className = 'attachment-preview-item';
                attachmentItem.innerHTML = `
                    <a href="download_attachment.php?task_id=<?php echo $view_task['id']; ?>&attachment_index=<?php echo $index; ?>" download="<?php echo $attachment['name']; ?>">
                        <i class="fas fa-file"></i>
                        <div class="attachment-name"><?php echo addslashes($attachment['name']); ?></div>
                    </a>
                `;
                attachmentsContainer.appendChild(attachmentItem);
            <?php 
                endforeach;
            } else {
            ?>
                attachmentsContainer.innerHTML = '<p>No attachments</p>';
            <?php } ?>
            
            openModal('viewTaskModal');
            <?php endif; ?>
            
            // Burn down chart
            const ctx = document.getElementById('burnDownChart').getContext('2d');
            burnDownChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: <?php echo is_array($burn_down_data) ? json_encode(array_keys($burn_down_data)) : '[]'; ?>,
                    datasets: [{
                        label: 'Remaining Story Points',
                        data: <?php echo is_array($burn_down_data) ? json_encode(array_values($burn_down_data)) : '[]'; ?>,
                        borderColor: '#6a11cb',
                        backgroundColor: 'rgba(106, 17, 203, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.3
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Story Points'
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Date'
                            }
                        }
                    }
                }
            });
            
            // Check if chart should be opened automatically
            <?php if (isset($_GET['chart']) && $_GET['chart'] == 1): ?>
            openModal('chartModal');
            <?php endif; ?>
            
            // Simple drag and drop for Kanban view (changed to manual form submission)
            const kanbanColumns = document.querySelectorAll('.kanban-task-list');
            let draggedTask = null;
            kanbanColumns.forEach(column => {
                column.addEventListener('dragover', e => {
                    e.preventDefault();
                });
                column.addEventListener('drop', e => {
                    e.preventDefault();
                    if (draggedTask) {
                        column.appendChild(draggedTask);
                        
                        // Update task status in database using form submission instead of AJAX
                        const taskId = draggedTask.getAttribute('data-task-id');
                        const newStatus = column.getAttribute('data-status');
                        
                        // Create a form to submit the request
                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.action = 'bmb_taskview.php';
                        
                        // Add the update_task_status parameter
                        const input1 = document.createElement('input');
                        input1.type = 'hidden';
                        input1.name = 'update_task_status';
                        input1.value = 'true';
                        form.appendChild(input1);
                        
                        // Add the task_id parameter
                        const input2 = document.createElement('input');
                        input2.type = 'hidden';
                        input2.name = 'task_id';
                        input2.value = taskId;
                        form.appendChild(input2);
                        
                        // Add the status parameter
                        const input3 = document.createElement('input');
                        input3.type = 'hidden';
                        input3.name = 'status';
                        input3.value = newStatus;
                        form.appendChild(input3);
                        
                        // Submit the form
                        document.body.appendChild(form);
                        form.submit();
                    }
                });
            });
            
            // Make tasks draggable in Kanban view
            document.querySelectorAll('.task-item').forEach(task => {
                task.setAttribute('draggable', 'true');
                
                task.addEventListener('dragstart', () => {
                    draggedTask = task;
                    setTimeout(() => {
                        task.style.opacity = '0.5';
                    }, 0);
                });
                
                task.addEventListener('dragend', () => {
                    draggedTask = null;
                    setTimeout(() => {
                        task.style.opacity = '1';
                    }, 0);
                });
            });
        });
    </script>
</body>
</html>