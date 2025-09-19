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

// For disciplinary_actions.php, check if user has HR privileges
$current_page = basename($_SERVER['PHP_SELF']);
if ($current_page === 'disciplinary_actions.php') {
    // Verify if the user has HR privileges
    $user_id = $_SESSION['user_id'];
    $sql = "SELECT is_hr, hr_id FROM employees WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $employee = $result->fetch_assoc();
        
        if ($employee['is_hr'] != 1) {
            // User doesn't have HR privileges
            $_SESSION['access_error'] = "You need HR privileges to access the HR Management System";
            header("Location: illegal_access_hr.php"); // Redirect to access denied page
            exit();
        } else {
            // Set HR session flags if not already set
            $_SESSION['is_hr'] = true;
            $_SESSION['hr_id'] = $employee['hr_id'];
        }
    } else {
        // User not found in database
        $_SESSION['access_error'] = "User not found in system";
        header("Location: illegal_access_hr.php");
        exit();
    }
    
    $stmt->close();
}

// Check if user is HR/admin
$is_hr = true; // For demo purposes, set to true. Implement proper role checking

// Fetch all employees
$employees = array();
$emp_sql = "SELECT id, name, email, department FROM employees WHERE valid_user = TRUE";
$emp_result = $conn->query($emp_sql);

if ($emp_result->num_rows > 0) {
    while ($row = $emp_result->fetch_assoc()) {
        $employees[] = $row;
    }
}

// Handle form submissions
$message = "";
$message_type = "";

// Handle disciplinary action submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_action'])) {
    $employee_id = $_POST['employee_id'];
    $action_date = $_POST['action_date'];
    $action_type = $_POST['action_type'];
    $reason = $conn->real_escape_string($_POST['reason']);
    $details = !empty($_POST['details']) ? $conn->real_escape_string($_POST['details']) : null;
    $status = $_POST['status'];
    $issued_by = $_SESSION['user_id'];
    
    // Insert into the disciplinary_actions table
    $sql = "INSERT INTO disciplinary_actions (employee_id, action_date, action_type, reason, details, status, issued_by) 
            VALUES ('$employee_id', '$action_date', '$action_type', '$reason', " . 
            ($details ? "'$details'" : "NULL") . ", '$status', '$issued_by')";
    
    if ($conn->query($sql)) {
        $message = "Disciplinary action recorded successfully!";
        $message_type = "success";
    } else {
        $message = "Error: " . $conn->error;
        $message_type = "error";
    }
}

// Handle action update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_action'])) {
    $action_id = $_POST['action_id'];
    $status = $_POST['status'];
    $resolved_date = !empty($_POST['resolved_date']) ? $_POST['resolved_date'] : null;
    $details = !empty($_POST['update_details']) ? $conn->real_escape_string($_POST['update_details']) : null;
    
    $sql = "UPDATE disciplinary_actions SET status = '$status', " .
           ($resolved_date ? "resolved_date = '$resolved_date', " : "") .
           ($details ? "details = CONCAT_WS('\n', details, '" . date('Y-m-d H:i:s') . " - Update: $details'), " : "") .
           "issued_by = '" . $_SESSION['user_id'] . "' WHERE id = '$action_id'";
    
    // Remove trailing comma and space
    $sql = rtrim($sql, ", ");
    $sql .= " WHERE id = '$action_id'";
    
    if ($conn->query($sql)) {
        $message = "Disciplinary action updated successfully!";
        $message_type = "success";
    } else {
        $message = "Error: " . $conn->error;
        $message_type = "error";
    }
}

// Handle filter submission
$filter_employee = isset($_GET['filter_employee']) ? $_GET['filter_employee'] : '';
$filter_status = isset($_GET['filter_status']) ? $_GET['filter_status'] : '';
$filter_type = isset($_GET['filter_type']) ? $_GET['filter_type'] : '';
$filter_start_date = isset($_GET['filter_start_date']) ? $_GET['filter_start_date'] : '';
$filter_end_date = isset($_GET['filter_end_date']) ? $_GET['filter_end_date'] : '';

// Build filter conditions
$filter_conditions = [];
if (!empty($filter_employee)) {
    $filter_conditions[] = "da.employee_id = '$filter_employee'";
}
if (!empty($filter_status)) {
    $filter_conditions[] = "da.status = '$filter_status'";
}
if (!empty($filter_type)) {
    $filter_conditions[] = "da.action_type = '$filter_type'";
}
if (!empty($filter_start_date)) {
    $filter_conditions[] = "da.action_date >= '$filter_start_date'";
}
if (!empty($filter_end_date)) {
    $filter_conditions[] = "da.action_date <= '$filter_end_date'";
}

$filter_sql = "";
if (!empty($filter_conditions)) {
    $filter_sql = "WHERE " . implode(" AND ", $filter_conditions);
}

// Fetch disciplinary actions with filters
$disciplinary_actions = array();
$actions_sql = "SELECT da.*, e.name as employee_name, e.department as employee_department, 
                i.name as issued_by_name
                FROM disciplinary_actions da 
                JOIN employees e ON da.employee_id = e.id 
                JOIN employees i ON da.issued_by = i.id 
                $filter_sql
                ORDER BY da.action_date DESC, da.created_at DESC LIMIT 100";
$actions_result = $conn->query($actions_sql);

if ($actions_result->num_rows > 0) {
    while ($row = $actions_result->fetch_assoc()) {
        $disciplinary_actions[] = $row;
    }
}

// Get disciplinary action statistics
$stats_sql = "SELECT 
                COUNT(*) as total_actions,
                SUM(CASE WHEN status = 'Active' THEN 1 ELSE 0 END) as active_actions,
                SUM(CASE WHEN status = 'Resolved' THEN 1 ELSE 0 END) as resolved_actions,
                SUM(CASE WHEN status = 'Appealed' THEN 1 ELSE 0 END) as appealed_actions,
                COUNT(DISTINCT employee_id) as employees_affected,
                action_type,
                COUNT(*) as type_count
              FROM disciplinary_actions
              GROUP BY action_type";
$stats_result = $conn->query($stats_sql);

$stats = [
    'total_actions' => 0,
    'active_actions' => 0,
    'resolved_actions' => 0,
    'appealed_actions' => 0,
    'employees_affected' => 0,
    'action_types' => []
];

if ($stats_result->num_rows > 0) {
    while ($row = $stats_result->fetch_assoc()) {
        $stats['total_actions'] = $row['total_actions'];
        $stats['active_actions'] = $row['active_actions'];
        $stats['resolved_actions'] = $row['resolved_actions'];
        $stats['appealed_actions'] = $row['appealed_actions'];
        $stats['employees_affected'] = $row['employees_affected'];
        $stats['action_types'][$row['action_type']] = $row['type_count'];
    }
}

// Get employees with most disciplinary actions
$top_employees_sql = "SELECT e.name, e.department, COUNT(da.id) as action_count
                      FROM disciplinary_actions da
                      JOIN employees e ON da.employee_id = e.id
                      GROUP BY da.employee_id
                      ORDER BY action_count DESC
                      LIMIT 5";
$top_employees_result = $conn->query($top_employees_sql);
$top_employees = [];

if ($top_employees_result->num_rows > 0) {
    while ($row = $top_employees_result->fetch_assoc()) {
        $top_employees[] = $row;
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Disciplinary Actions - Buymeabook</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #1a2a6c 0%, #b21f1f 50%, #fdbb2d 100%);
            min-height: 100vh;
            color: #333;
            padding: 20px;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            background: rgba(255, 255, 255, 0.95);
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }
        
        .logo {
            width: 200px;
            height: auto;
            border-radius: 10px;
        }
        
        .nav-links {
            display: flex;
            gap: 15px;
        }
        
        .nav-link {
            color: #1a2a6c;
            text-decoration: none;
            font-weight: 600;
            padding: 10px 15px;
            border-radius: 50px;
            transition: all 0.3s;
        }
        
        .nav-link:hover, .nav-link.active {
            background: #1a2a6c;
            color: white;
        }
        
        .dashboard {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .sidebar {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            height: fit-content;
        }
        
        .sidebar-title {
            font-size: 1.3rem;
            margin-bottom: 20px;
            color: #1a2a6c;
            padding-bottom: 10px;
            border-bottom: 2px solid #eee;
        }
        
        .sidebar-menu {
            list-style: none;
        }
        
        .sidebar-menu li {
            margin-bottom: 10px;
        }
        
        .sidebar-menu a {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 15px;
            color: #444;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s;
        }
        
        .sidebar-menu a:hover, .sidebar-menu a.active {
            background: #1a2a6c;
            color: white;
        }
        
        .main-content {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            width: 100%;
            overflow-x: hidden;
        }
        
        .section-title {
            font-size: 1.5rem;
            margin-bottom: 20px;
            color: #1a2a6c;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 15px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.08);
            text-align: center;
        }
        
        .stat-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #1a2a6c;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 16px;
            margin: 0 auto 10px;
        }
        
        .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1a2a6c;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.85rem;
        }
        
        .analytics-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 25px;
        }
        
        .analytics-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.08);
        }
        
        .analytics-title {
            font-size: 1.1rem;
            margin-bottom: 15px;
            color: #1a2a6c;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .type-list {
            list-style: none;
        }
        
        .type-list li {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #f5f5f5;
        }
        
        .type-list li:last-child {
            border-bottom: none;
        }
        
        .employee-list {
            list-style: none;
        }
        
        .employee-list li {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #f5f5f5;
        }
        
        .employee-list li:last-child {
            border-bottom: none;
        }
        
        .count-badge {
            background: #e9ecef;
            color: #495057;
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .form-section {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.08);
        }
        
        .form-title {
            font-size: 1.1rem;
            margin-bottom: 15px;
            color: #1a2a6c;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 15px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 500;
            color: #444;
            font-size: 0.9rem;
        }
        
        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 0.9rem;
            transition: border 0.3s;
        }
        
        .form-control:focus {
            border-color: #1a2a6c;
            outline: none;
        }
        
        textarea.form-control {
            min-height: 100px;
            resize: vertical;
        }
        
        .btn {
            padding: 10px 16px;
            background: #1a2a6c;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .btn:hover {
            background: #15225a;
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background: #6c757d;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .btn-success {
            background: #28a745;
        }
        
        .btn-success:hover {
            background: #218838;
        }
        
        .btn-danger {
            background: #dc3545;
        }
        
        .btn-danger:hover {
            background: #c82333;
        }
        
        .btn-warning {
            background: #ffc107;
            color: #212529;
        }
        
        .btn-warning:hover {
            background: #e0a800;
        }
        
        .data-section {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.08);
            width: 100%;
            overflow-x: auto;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
            min-width: 1000px;
        }
        
        .data-table th, .data-table td {
            padding: 10px 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .data-table th {
            background: #f8f9fa;
            color: #1a2a6c;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .data-table tr:hover {
            background: #f8f9fa;
        }
        
        .badge {
            padding: 4px 8px;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .badge-success {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-warning {
            background: #fff3cd;
            color: #856404;
        }
        
        .badge-danger {
            background: #f8d7da;
            color: #721c24;
        }
        
        .badge-info {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .message {
            padding: 12px;
            margin-bottom: 15px;
            border-radius: 6px;
            text-align: center;
            font-weight: 500;
            font-size: 0.9rem;
        }
        
        .success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .filter-section {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.08);
        }
        
        .filter-form {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 12px;
            align-items: end;
        }
        
        .filter-buttons {
            display: flex;
            gap: 8px;
        }
        
        .btn-sm {
            padding: 8px 12px;
            font-size: 0.8rem;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background-color: #fefefe;
            margin: 5% auto;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.2);
            width: 80%;
            max-width: 600px;
        }
        
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .close:hover {
            color: #000;
        }
        
        @media (max-width: 1200px) {
            .dashboard {
                grid-template-columns: 250px 1fr;
            }
            
            .stats-grid {
                grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            }
            
            .analytics-grid {
                grid-template-columns: 1fr;
            }
            
            .form-grid {
                grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            }
        }
        
        @media (max-width: 992px) {
            .dashboard {
                grid-template-columns: 1fr;
            }
            
            .header {
                flex-direction: column;
                gap: 15px;
            }
            
            .nav-links {
                flex-wrap: wrap;
                justify-content: center;
            }
            
            .stats-grid {
                grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            }
            
            .filter-form {
                grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            }
        }
        
        @media (max-width: 768px) {
            body {
                padding: 10px;
            }
            
            .header {
                padding: 15px;
            }
            
            .logo {
                width: 150px;
            }
            
            .nav-link {
                padding: 8px 12px;
                font-size: 0.9rem;
            }
            
            .main-content, .sidebar, .form-section, .data-section, .filter-section {
                padding: 15px;
            }
            
            .section-title {
                font-size: 1.3rem;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .filter-form {
                grid-template-columns: 1fr;
            }
            
            .filter-buttons {
                justify-content: center;
            }
            
            .stat-value {
                font-size: 1.3rem;
            }
        }
        
        @media (max-width: 576px) {
            .stats-grid {
                grid-template-columns: 1fr 1fr;
            }
            
            .nav-links {
                gap: 8px;
            }
            
            .nav-link {
                font-size: 0.8rem;
                padding: 6px 10px;
            }
            
            .modal-content {
                width: 95%;
                margin: 10% auto;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <img src="https://ik.imagekit.io/nuvq7aygp/BMB_Logo.jpg" alt="BMB Logo" class="logo">
            <div class="nav-links">
                <a href="start.php" class="nav-link"><i class="fas fa-home"></i> Home</a>
                <a href="status.php" class="nav-link"><i class="fas fa-stream"></i> Status Updates</a>
                <a href="hrms.php" class="nav-link"><i class="fas fa-users-cog"></i> HR Management</a>
                <a href="leave_management.php" class="nav-link"><i class="fas fa-calendar-day"></i> Leave Management</a>
                <a href="uploader.html" class="nav-link"><i class="fas fa-folder"></i> Manage Files</a>
                <a href="logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>
        
        <?php if (!empty($message)): ?>
            <div class="message <?php echo $message_type; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <div class="dashboard">
            <div class="sidebar">
                <h2 class="sidebar-title">HR Dashboard</h2>
                <ul class="sidebar-menu">
                    <li><a href="hrms.php"><i class="fas fa-tachometer-alt"></i> Overview</a></li>
                    <li><a href="attendance.php"><i class="fas fa-user-clock"></i> Attendance</a></li>
                    <li><a href="document.php"><i class="fas fa-file-alt"></i> Documents</a></li>
                    <li><a href="leave_management.php"><i class="fas fa-calendar-day"></i> Leave Management</a></li>
                    <li><a href="performance_management.php"><i class="fas fa-chart-line"></i> Performance</a></li>
                    <li><a href="compensation_history.php"><i class="fas fa-money-check-alt"></i> Compensation</a></li>
                    <li><a href="training.php"><i class="fas fa-graduation-cap"></i> Training</a></li>
                    <li><a href="disciplinary_actions.php" class="active"><i class="fas fa-exclamation-circle"></i> Disciplinary</a></li>
                    <li><a href="exit_management.php"><i class="fas fa-door-open"></i> Exit Management</a></li>
                    <li><a href="benefits.php"><i class="fas fa-stethoscope"></i> Benefits</a></li>
					<li><a href="ask_hr.php"><i class="fas fa-headset"></i> ASK HR Tickets</a></li>
					<li><a href="employees_details_hr.php"><i class="fas fa-user-edit"></i> Employee Details</a></li>
                </ul>
            </div>
            
            <div class="main-content">
                <h2 class="section-title"><i class="fas fa-exclamation-circle"></i> Disciplinary Actions Management</h2>
                
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-clipboard-list"></i>
                        </div>
                        <div class="stat-value"><?php echo $stats['total_actions']; ?></div>
                        <div class="stat-label">Total Actions</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-user-times"></i>
                        </div>
                        <div class="stat-value"><?php echo $stats['employees_affected']; ?></div>
                        <div class="stat-label">Employees Affected</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-value"><?php echo $stats['active_actions']; ?></div>
                        <div class="stat-label">Active Cases</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-value"><?php echo $stats['resolved_actions']; ?></div>
                        <div class="stat-label">Resolved Cases</div>
                    </div>
                </div>
                
                <div class="analytics-grid">
                    <div class="analytics-card">
                        <h3 class="analytics-title"><i class="fas fa-chart-pie"></i> Actions by Type</h3>
                        <ul class="type-list">
                            <?php foreach ($stats['action_types'] as $type => $count): ?>
                                <li>
                                    <span><?php echo htmlspecialchars($type); ?></span>
                                    <span class="count-badge"><?php echo $count; ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    
                    <div class="analytics-card">
                        <h3 class="analytics-title"><i class="fas fa-users"></i> Top Employees with Actions</h3>
                        <ul class="employee-list">
                            <?php if (count($top_employees) > 0): ?>
                                <?php foreach ($top_employees as $employee): ?>
                                    <li>
                                        <span><?php echo htmlspecialchars($employee['name']); ?> (<?php echo htmlspecialchars($employee['department']); ?>)</span>
                                        <span class="count-badge"><?php echo $employee['action_count']; ?> actions</span>
                                    </li>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <li>No disciplinary actions recorded yet.</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
                
                <div class="form-section">
                    <h3 class="form-title"><i class="fas fa-plus-circle"></i> Record Disciplinary Action</h3>
                    <form method="POST" id="action-form">
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="employee_id">Employee</label>
                                <select id="employee_id" name="employee_id" class="form-control" required>
                                    <option value="">Select Employee</option>
                                    <?php foreach ($employees as $employee): ?>
                                        <option value="<?php echo $employee['id']; ?>">
                                            <?php echo htmlspecialchars($employee['name'] . ' (' . $employee['department'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="action_date">Action Date</label>
                                <input type="date" id="action_date" name="action_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="action_type">Action Type</label>
                                <select id="action_type" name="action_type" class="form-control" required>
                                    <option value="">Select Type</option>
                                    <option value="Verbal Warning">Verbal Warning</option>
                                    <option value="Written Warning">Written Warning</option>
                                    <option value="Suspension">Suspension</option>
                                    <option value="Final Warning">Final Warning</option>
                                    <option value="Termination">Termination</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="status">Status</label>
                                <select id="status" name="status" class="form-control" required>
                                    <option value="Active">Active</option>
                                    <option value="Resolved">Resolved</option>
                                    <option value="Appealed">Appealed</option>
                                </select>
                            </div>
                            
                            <div class="form-group" style="grid-column: 1 / -1;">
                                <label for="reason">Reason</label>
                                <textarea id="reason" name="reason" class="form-control" rows="3" required placeholder="Enter the reason for this disciplinary action"></textarea>
                            </div>
                            
                            <div class="form-group" style="grid-column: 1 / -1;">
                                <label for="details">Details</label>
                                <textarea id="details" name="details" class="form-control" rows="3" placeholder="Enter additional details about this disciplinary action"></textarea>
                            </div>
                        </div>
                        
                        <button type="submit" name="add_action" class="btn"><i class="fas fa-save"></i> Record Action</button>
                    </form>
                </div>
                
                <div class="filter-section">
                    <h3 class="form-title"><i class="fas fa-filter"></i> Filter Disciplinary Actions</h3>
                    <form method="GET" class="filter-form">
                        <div class="form-group">
                            <label for="filter_employee">Employee</label>
                            <select id="filter_employee" name="filter_employee" class="form-control">
                                <option value="">All Employees</option>
                                <?php foreach ($employees as $employee): ?>
                                    <option value="<?php echo $employee['id']; ?>" <?php echo $filter_employee == $employee['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($employee['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="filter_status">Status</label>
                            <select id="filter_status" name="filter_status" class="form-control">
                                <option value="">All Statuses</option>
                                <option value="Active" <?php echo $filter_status == 'Active' ? 'selected' : ''; ?>>Active</option>
                                <option value="Resolved" <?php echo $filter_status == 'Resolved' ? 'selected' : ''; ?>>Resolved</option>
                                <option value="Appealed" <?php echo $filter_status == 'Appealed' ? 'selected' : ''; ?>>Appealed</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="filter_type">Action Type</label>
                            <select id="filter_type" name="filter_type" class="form-control">
                                <option value="">All Types</option>
                                <option value="Verbal Warning" <?php echo $filter_type == 'Verbal Warning' ? 'selected' : ''; ?>>Verbal Warning</option>
                                <option value="Written Warning" <?php echo $filter_type == 'Written Warning' ? 'selected' : ''; ?>>Written Warning</option>
                                <option value="Suspension" <?php echo $filter_type == 'Suspension' ? 'selected' : ''; ?>>Suspension</option>
                                <option value="Final Warning" <?php echo $filter_type == 'Final Warning' ? 'selected' : ''; ?>>Final Warning</option>
                                <option value="Termination" <?php echo $filter_type == 'Termination' ? 'selected' : ''; ?>>Termination</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="filter_start_date">Start Date From</label>
                            <input type="date" id="filter_start_date" name="filter_start_date" class="form-control" value="<?php echo $filter_start_date; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="filter_end_date">End Date To</label>
                            <input type="date" id="filter_end_date" name="filter_end_date" class="form-control" value="<?php echo $filter_end_date; ?>">
                        </div>
                        
                        <div class="form-group filter-buttons">
                            <button type="submit" class="btn btn-sm"><i class="fas fa-filter"></i> Apply Filters</button>
                            <a href="disciplinary_actions.php" class="btn btn-secondary btn-sm"><i class="fas fa-times"></i> Clear Filters</a>
                        </div>
                    </form>
                </div>
                
                <div class="data-section">
                    <h3 class="form-title"><i class="fas fa-list"></i> Disciplinary Actions</h3>
                    <div style="overflow-x: auto;">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Employee</th>
                                    <th>Department</th>
                                    <th>Action Date</th>
                                    <th>Action Type</th>
                                    <th>Reason</th>
                                    <th>Status</th>
                                    <th>Issued By</th>
                                    <th>Resolved Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (count($disciplinary_actions) > 0): ?>
                                <?php foreach ($disciplinary_actions as $action): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($action['employee_name']); ?></td>
                                        <td><?php echo htmlspecialchars($action['employee_department']); ?></td>
                                        <td><?php echo date('M j, Y', strtotime($action['action_date'])); ?></td>
                                        <td>
                                            <span class="badge 
                                                <?php 
                                                if ($action['action_type'] == 'Verbal Warning') echo 'badge-info';
                                                elseif ($action['action_type'] == 'Written Warning') echo 'badge-warning';
                                                elseif ($action['action_type'] == 'Suspension') echo 'badge-warning';
                                                elseif ($action['action_type'] == 'Final Warning') echo 'badge-danger';
                                                else echo 'badge-danger';
                                                ?>">
                                                <?php echo htmlspecialchars($action['action_type']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo !empty($action['reason']) ? htmlspecialchars(substr($action['reason'], 0, 50)) . (strlen($action['reason']) > 50 ? '...' : '') : '-'; ?></td>
                                        <td>
                                            <span class="badge 
                                                <?php 
                                                if ($action['status'] == 'Active') echo 'badge-warning';
                                                elseif ($action['status'] == 'Resolved') echo 'badge-success';
                                                else echo 'badge-info';
                                                ?>">
                                                <?php echo $action['status']; ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($action['issued_by_name']); ?></td>
                                        <td><?php echo !empty($action['resolved_date']) ? date('M j, Y', strtotime($action['resolved_date'])) : '-'; ?></td>
                                        <td>
                                            <div style="display: flex; gap: 5px; flex-wrap: wrap;">
                                                <button class="btn btn-secondary btn-sm view-details" data-id="<?php echo $action['id']; ?>" data-details="<?php echo htmlspecialchars($action['details']); ?>">
                                                    <i class="fas fa-eye"></i> View
                                                </button>
                                                <button class="btn btn-warning btn-sm update-action" data-id="<?php echo $action['id']; ?>" data-status="<?php echo $action['status']; ?>">
                                                    <i class="fas fa-edit"></i> Update
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9" style="text-align: center;">No disciplinary actions found.</td>
                                </tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- View Details Modal -->
    <div id="detailsModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h3 class="form-title"><i class="fas fa-info-circle"></i> Action Details</h3>
            <div id="modalDetails" style="margin-top: 15px; padding: 10px; background: #f8f9fa; border-radius: 6px; min-height: 100px;">
                Details will be shown here...
            </div>
        </div>
    </div>

    <!-- Update Action Modal -->
    <div id="updateModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h3 class="form-title"><i class="fas fa-edit"></i> Update Disciplinary Action</h3>
            <form method="POST" id="update-form">
                <input type="hidden" id="update_action_id" name="action_id">
                
                <div class="form-group">
                    <label for="update_status">Status</label>
                    <select id="update_status" name="status" class="form-control" required>
                        <option value="Active">Active</option>
                        <option value="Resolved">Resolved</option>
                        <option value="Appealed">Appealed</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="resolved_date">Resolved Date (if applicable)</label>
                    <input type="date" id="resolved_date" name="resolved_date" class="form-control">
                </div>
                
                <div class="form-group">
                    <label for="update_details">Update Details</label>
                    <textarea id="update_details" name="update_details" class="form-control" rows="3" placeholder="Enter update details"></textarea>
                </div>
                
                <button type="submit" name="update_action" class="btn"><i class="fas fa-save"></i> Update Action</button>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Modal functionality
            const detailsModal = document.getElementById('detailsModal');
            const updateModal = document.getElementById('updateModal');
            const modalDetails = document.getElementById('modalDetails');
            const updateStatus = document.getElementById('update_status');
            const updateActionId = document.getElementById('update_action_id');
            const closeButtons = document.querySelectorAll('.close');
            
            // View details buttons
            const viewButtons = document.querySelectorAll('.view-details');
            viewButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const details = this.getAttribute('data-details');
                    modalDetails.innerHTML = details && details !== 'null' ? 
                        details.replace(/\n/g, '<br>') : 
                        '<em>No details available for this action.</em>';
                    detailsModal.style.display = 'block';
                });
            });
            
            // Update action buttons
            const updateButtons = document.querySelectorAll('.update-action');
            updateButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const actionId = this.getAttribute('data-id');
                    const currentStatus = this.getAttribute('data-status');
                    
                    updateActionId.value = actionId;
                    updateStatus.value = currentStatus;
                    updateModal.style.display = 'block';
                });
            });
            
            // Close modals
            closeButtons.forEach(button => {
                button.addEventListener('click', function() {
                    detailsModal.style.display = 'none';
                    updateModal.style.display = 'none';
                });
            });
            
            // Close modal when clicking outside
            window.addEventListener('click', function(event) {
                if (event.target == detailsModal) {
                    detailsModal.style.display = 'none';
                }
                if (event.target == updateModal) {
                    updateModal.style.display = 'none';
                }
            });
            
            // Form validation for disciplinary action
            const actionForm = document.getElementById('action-form');
            if (actionForm) {
                actionForm.addEventListener('submit', function(e) {
                    const employeeId = document.getElementById('employee_id').value;
                    const actionDate = document.getElementById('action_date').value;
                    const actionType = document.getElementById('action_type').value;
                    const reason = document.getElementById('reason').value;
                    
                    if (!employeeId || !actionDate || !actionType || !reason) {
                        e.preventDefault();
                        alert('Please fill in all required fields.');
                    }
                });
            }
            
            // Auto-set resolved date when status is changed to Resolved
            const statusSelect = document.getElementById('update_status');
            const resolvedDateInput = document.getElementById('resolved_date');
            
            if (statusSelect && resolvedDateInput) {
                statusSelect.addEventListener('change', function() {
                    if (this.value === 'Resolved' && !resolvedDateInput.value) {
                        resolvedDateInput.value = new Date().toISOString().split('T')[0];
                    } else if (this.value !== 'Resolved') {
                        resolvedDateInput.value = '';
                    }
                });
            }
        });
    </script>
</body>
</html>