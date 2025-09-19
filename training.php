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

// For training.php, check if user has HR privileges
$current_page = basename($_SERVER['PHP_SELF']);
if ($current_page === 'training.php') {
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

// Handle training record submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_training'])) {
    $employee_id = $_POST['employee_id'];
    $training_name = $conn->real_escape_string($_POST['training_name']);
    $training_type = $_POST['training_type'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $provider = !empty($_POST['provider']) ? $conn->real_escape_string($_POST['provider']) : null;
    $cost = !empty($_POST['cost']) ? $_POST['cost'] : null;
    $status = $_POST['status'];
    $result = !empty($_POST['result']) ? $conn->real_escape_string($_POST['result']) : null;
    
    // Insert into the training_records table
    $sql = "INSERT INTO training_records (employee_id, training_name, training_type, start_date, end_date, provider, cost, status, result) 
            VALUES ('$employee_id', '$training_name', '$training_type', '$start_date', '$end_date', " . 
            ($provider ? "'$provider'" : "NULL") . ", " .
            ($cost ? "'$cost'" : "NULL") . ", '$status', " .
            ($result ? "'$result'" : "NULL") . ")";
    
    if ($conn->query($sql)) {
        $message = "Training record added successfully!";
        $message_type = "success";
    } else {
        $message = "Error: " . $conn->error;
        $message_type = "error";
    }
}

// Handle training update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_training'])) {
    $training_id = $_POST['training_id'];
    $status = $_POST['status'];
    $result = !empty($_POST['update_result']) ? $conn->real_escape_string($_POST['update_result']) : null;
    $certificate_path = !empty($_POST['certificate_path']) ? $conn->real_escape_string($_POST['certificate_path']) : null;
    
    // Build the SET part of the query
    $set_clause = "status = '$status'";
    if ($result) {
        $set_clause .= ", result = '$result'";
    }
    if ($certificate_path) {
        $set_clause .= ", certificate_path = '$certificate_path'";
    }
    
    $sql = "UPDATE training_records SET $set_clause WHERE id = '$training_id'";
    
    if ($conn->query($sql)) {
        $message = "Training record updated successfully!";
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
    $filter_conditions[] = "tr.employee_id = '$filter_employee'";
}
if (!empty($filter_status)) {
    $filter_conditions[] = "tr.status = '$filter_status'";
}
if (!empty($filter_type)) {
    $filter_conditions[] = "tr.training_type = '$filter_type'";
}
if (!empty($filter_start_date)) {
    $filter_conditions[] = "tr.start_date >= '$filter_start_date'";
}
if (!empty($filter_end_date)) {
    $filter_conditions[] = "tr.end_date <= '$filter_end_date'";
}

$filter_sql = "";
if (!empty($filter_conditions)) {
    $filter_sql = "WHERE " . implode(" AND ", $filter_conditions);
}

// Fetch training records with filters
$training_records = array();
$training_sql = "SELECT tr.*, e.name as employee_name, e.department as employee_department
                FROM training_records tr 
                JOIN employees e ON tr.employee_id = e.id 
                $filter_sql
                ORDER BY tr.start_date DESC, tr.created_at DESC LIMIT 100";
$training_result = $conn->query($training_sql);

if ($training_result->num_rows > 0) {
    while ($row = $training_result->fetch_assoc()) {
        $training_records[] = $row;
    }
}

// Get training statistics
$stats_sql = "SELECT 
                COUNT(*) as total_trainings,
                SUM(CASE WHEN status = 'Planned' THEN 1 ELSE 0 END) as planned_trainings,
                SUM(CASE WHEN status = 'In Progress' THEN 1 ELSE 0 END) as inprogress_trainings,
                SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed_trainings,
                SUM(CASE WHEN status = 'Cancelled' THEN 1 ELSE 0 END) as cancelled_trainings,
                COUNT(DISTINCT employee_id) as employees_trained,
                training_type,
                COUNT(*) as type_count
              FROM training_records
              GROUP BY training_type";
$stats_result = $conn->query($stats_sql);

$stats = [
    'total_trainings' => 0,
    'planned_trainings' => 0,
    'inprogress_trainings' => 0,
    'completed_trainings' => 0,
    'cancelled_trainings' => 0,
    'employees_trained' => 0,
    'training_types' => []
];

if ($stats_result->num_rows > 0) {
    while ($row = $stats_result->fetch_assoc()) {
        $stats['total_trainings'] = $row['total_trainings'];
        $stats['planned_trainings'] = $row['planned_trainings'];
        $stats['inprogress_trainings'] = $row['inprogress_trainings'];
        $stats['completed_trainings'] = $row['completed_trainings'];
        $stats['cancelled_trainings'] = $row['cancelled_trainings'];
        $stats['employees_trained'] = $row['employees_trained'];
        $stats['training_types'][$row['training_type']] = $row['type_count'];
    }
}

// Get employees with most trainings
$top_employees_sql = "SELECT e.name, e.department, COUNT(tr.id) as training_count
                      FROM training_records tr
                      JOIN employees e ON tr.employee_id = e.id
                      GROUP BY tr.employee_id
                      ORDER BY training_count DESC
                      LIMIT 5";
$top_employees_result = $conn->query($top_employees_sql);
$top_employees = [];

if ($top_employees_result->num_rows > 0) {
    while ($row = $top_employees_result->fetch_assoc()) {
        $top_employees[] = $row;
    }
}

// Calculate total training cost
$cost_sql = "SELECT SUM(cost) as total_cost FROM training_records WHERE cost IS NOT NULL";
$cost_result = $conn->query($cost_sql);
$total_cost = 0;
if ($cost_result->num_rows > 0) {
    $row = $cost_result->fetch_assoc();
    $total_cost = $row['total_cost'] ? $row['total_cost'] : 0;
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Training Management - Buymeabook</title>
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
        
        .cost-badge {
            background: #d4edda;
            color: #155724;
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
        
        .badge-primary {
            background: #cce5ff;
            color: #004085;
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
                <a href="disciplinary_actions.php" class="nav-link"><i class="fas fa-exclamation-circle"></i> Disciplinary Actions</a>
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
                    <li><a href="training.php" class="active"><i class="fas fa-graduation-cap"></i> Training</a></li>
                    <li><a href="disciplinary_actions.php"><i class="fas fa-exclamation-circle"></i> Disciplinary</a></li>
                    <li><a href="exit_management.php"><i class="fas fa-door-open"></i> Exit Management</a></li>
                    <li><a href="benefits.php"><i class="fas fa-stethoscope"></i> Benefits</a></li>
					<li><a href="ask_hr.php"><i class="fas fa-headset"></i> ASK HR Tickets</a></li>
					<li><a href="employees_details_hr.php"><i class="fas fa-user-edit"></i> Employee Details</a></li>
                </ul>
            </div>
            
            <div class="main-content">
                <h2 class="section-title"><i class="fas fa-graduation-cap"></i> Training Management System</h2>
                
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-clipboard-list"></i>
                        </div>
                        <div class="stat-value"><?php echo $stats['total_trainings']; ?></div>
                        <div class="stat-label">Total Trainings</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-user-graduate"></i>
                        </div>
                        <div class="stat-value"><?php echo $stats['employees_trained']; ?></div>
                        <div class="stat-label">Employees Trained</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-value"><?php echo $stats['completed_trainings']; ?></div>
                        <div class="stat-label">Completed</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                        <div class="stat-value">$<?php echo number_format($total_cost, 2); ?></div>
                        <div class="stat-label">Total Cost</div>
                    </div>
                </div>
                
                <div class="analytics-grid">
                    <div class="analytics-card">
                        <h3 class="analytics-title"><i class="fas fa-chart-pie"></i> Trainings by Type</h3>
                        <ul class="type-list">
                            <?php foreach ($stats['training_types'] as $type => $count): ?>
                                <li>
                                    <span><?php echo htmlspecialchars($type); ?></span>
                                    <span class="count-badge"><?php echo $count; ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    
                    <div class="analytics-card">
                        <h3 class="analytics-title"><i class="fas fa-users"></i> Top Employees with Trainings</h3>
                        <ul class="employee-list">
                            <?php if (count($top_employees) > 0): ?>
                                <?php foreach ($top_employees as $employee): ?>
                                    <li>
                                        <span><?php echo htmlspecialchars($employee['name']); ?> (<?php echo htmlspecialchars($employee['department']); ?>)</span>
                                        <span class="count-badge"><?php echo $employee['training_count']; ?> trainings</span>
                                    </li>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <li>No training records found.</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
                
                <div class="form-section">
                    <h3 class="form-title"><i class="fas fa-plus-circle"></i> Add Training Record</h3>
                    <form method="POST" id="training-form">
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
                                <label for="training_name">Training Name</label>
                                <input type="text" id="training_name" name="training_name" class="form-control" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="training_type">Training Type</label>
                                <select id="training_type" name="training_type" class="form-control" required>
                                    <option value="">Select Type</option>
                                    <option value="Internal">Internal</option>
                                    <option value="External">External</option>
                                    <option value="Online">Online</option>
                                    <option value="Workshop">Workshop</option>
                                    <option value="Certification">Certification</option>
                                    <option value="Seminar">Seminar</option>
                                    <option value="Conference">Conference</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="start_date">Start Date</label>
                                <input type="date" id="start_date" name="start_date" class="form-control" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="end_date">End Date</label>
                                <input type="date" id="end_date" name="end_date" class="form-control" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="provider">Provider</label>
                                <input type="text" id="provider" name="provider" class="form-control">
                            </div>
                            
                            <div class="form-group">
                                <label for="cost">Cost ($)</label>
                                <input type="number" id="cost" name="cost" class="form-control" min="0" step="0.01">
                            </div>
                            
                            <div class="form-group">
                                <label for="status">Status</label>
                                <select id="status" name="status" class="form-control" required>
                                    <option value="Planned">Planned</option>
                                    <option value="In Progress">In Progress</option>
                                    <option value="Completed">Completed</option>
                                    <option value="Cancelled">Cancelled</option>
                                </select>
                            </div>
                            
                            <div class="form-group" style="grid-column: 1 / -1;">
                                <label for="result">Result/Notes</label>
                                <textarea id="result" name="result" class="form-control" rows="3" placeholder="Enter training results or notes"></textarea>
                            </div>
                        </div>
                        
                        <button type="submit" name="add_training" class="btn"><i class="fas fa-save"></i> Add Training Record</button>
                    </form>
                </div>
                
                <div class="filter-section">
                    <h3 class="form-title"><i class="fas fa-filter"></i> Filter Training Records</h3>
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
                                <option value="Planned" <?php echo $filter_status == 'Planned' ? 'selected' : ''; ?>>Planned</option>
                                <option value="In Progress" <?php echo $filter_status == 'In Progress' ? 'selected' : ''; ?>>In Progress</option>
                                <option value="Completed" <?php echo $filter_status == 'Completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="Cancelled" <?php echo $filter_status == 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="filter_type">Training Type</label>
                            <select id="filter_type" name="filter_type" class="form-control">
                                <option value="">All Types</option>
                                <option value="Internal" <?php echo $filter_type == 'Internal' ? 'selected' : ''; ?>>Internal</option>
                                <option value="External" <?php echo $filter_type == 'External' ? 'selected' : ''; ?>>External</option>
                                <option value="Online" <?php echo $filter_type == 'Online' ? 'selected' : ''; ?>>Online</option>
                                <option value="Workshop" <?php echo $filter_type == 'Workshop' ? 'selected' : ''; ?>>Workshop</option>
                                <option value="Certification" <?php echo $filter_type == 'Certification' ? 'selected' : ''; ?>>Certification</option>
                                <option value="Seminar" <?php echo $filter_type == 'Seminar' ? 'selected' : ''; ?>>Seminar</option>
                                <option value="Conference" <?php echo $filter_type == 'Conference' ? 'selected' : ''; ?>>Conference</option>
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
                            <a href="training.php" class="btn btn-secondary btn-sm"><i class="fas fa-times"></i> Clear Filters</a>
                        </div>
                    </form>
                </div>
                
                <div class="data-section">
                    <h3 class="form-title"><i class="fas fa-list"></i> Training Records</h3>
                    <div style="overflow-x: auto;">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Employee</th>
                                    <th>Department</th>
                                    <th>Training Name</th>
                                    <th>Type</th>
                                    <th>Start Date</th>
                                    <th>End Date</th>
                                    <th>Provider</th>
                                    <th>Cost</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (count($training_records) > 0): ?>
                                <?php foreach ($training_records as $training): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($training['employee_name']); ?></td>
                                        <td><?php echo htmlspecialchars($training['employee_department']); ?></td>
                                        <td><?php echo htmlspecialchars($training['training_name']); ?></td>
                                        <td>
                                            <span class="badge 
                                                <?php 
                                                if ($training['training_type'] == 'Internal') echo 'badge-primary';
                                                elseif ($training['training_type'] == 'External') echo 'badge-info';
                                                elseif ($training['training_type'] == 'Online') echo 'badge-success';
                                                else echo 'badge-warning';
                                                ?>">
                                                <?php echo htmlspecialchars($training['training_type']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('M j, Y', strtotime($training['start_date'])); ?></td>
                                        <td><?php echo date('M j, Y', strtotime($training['end_date'])); ?></td>
                                        <td><?php echo !empty($training['provider']) ? htmlspecialchars($training['provider']) : '-'; ?></td>
                                        <td><?php echo !empty($training['cost']) ? '$' . number_format($training['cost'], 2) : '-'; ?></td>
                                        <td>
                                            <span class="badge 
                                                <?php 
                                                if ($training['status'] == 'Completed') echo 'badge-success';
                                                elseif ($training['status'] == 'In Progress') echo 'badge-warning';
                                                elseif ($training['status'] == 'Planned') echo 'badge-info';
                                                else echo 'badge-danger';
                                                ?>">
                                                <?php echo $training['status']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div style="display: flex; gap: 5px; flex-wrap: wrap;">
                                                <button class="btn btn-secondary btn-sm view-details" data-id="<?php echo $training['id']; ?>" data-result="<?php echo htmlspecialchars($training['result']); ?>">
                                                    <i class="fas fa-eye"></i> View
                                                </button>
                                                <button class="btn btn-warning btn-sm update-training" data-id="<?php echo $training['id']; ?>" data-status="<?php echo $training['status']; ?>">
                                                    <i class="fas fa-edit"></i> Update
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="10" style="text-align: center;">No training records found.</td>
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
            <h3 class="form-title"><i class="fas fa-info-circle"></i> Training Results/Notes</h3>
            <div id="modalDetails" style="margin-top: 15px; padding: 10px; background: #f8f9fa; border-radius: 6px; min-height: 100px;">
                Details will be shown here...
            </div>
        </div>
    </div>

    <!-- Update Training Modal -->
    <div id="updateModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h3 class="form-title"><i class="fas fa-edit"></i> Update Training Record</h3>
            <form method="POST" id="update-form">
                <input type="hidden" id="update_training_id" name="training_id">
                
                <div class="form-group">
                    <label for="update_status">Status</label>
                    <select id="update_status" name="status" class="form-control" required>
                        <option value="Planned">Planned</option>
                        <option value="In Progress">In Progress</option>
                        <option value="Completed">Completed</option>
                        <option value="Cancelled">Cancelled</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="certificate_path">Certificate Path (if completed)</label>
                    <input type="text" id="certificate_path" name="certificate_path" class="form-control" placeholder="Enter certificate file path">
                </div>
                
                <div class="form-group">
                    <label for="update_result">Update Results/Notes</label>
                    <textarea id="update_result" name="update_result" class="form-control" rows="3" placeholder="Enter update details"></textarea>
                </div>
                
                <button type="submit" name="update_training" class="btn"><i class="fas fa-save"></i> Update Training</button>
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
            const updateTrainingId = document.getElementById('update_training_id');
            const closeButtons = document.querySelectorAll('.close');
            
            // View details buttons
            const viewButtons = document.querySelectorAll('.view-details');
            viewButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const result = this.getAttribute('data-result');
                    modalDetails.innerHTML = result && result !== 'null' ? 
                        result.replace(/\n/g, '<br>') : 
                        '<em>No results or notes available for this training.</em>';
                    detailsModal.style.display = 'block';
                });
            });
            
            // Update training buttons
            const updateButtons = document.querySelectorAll('.update-training');
            updateButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const trainingId = this.getAttribute('data-id');
                    const currentStatus = this.getAttribute('data-status');
                    
                    updateTrainingId.value = trainingId;
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
            
            // Form validation for training record
            const trainingForm = document.getElementById('training-form');
            if (trainingForm) {
                trainingForm.addEventListener('submit', function(e) {
                    const employeeId = document.getElementById('employee_id').value;
                    const trainingName = document.getElementById('training_name').value;
                    const trainingType = document.getElementById('training_type').value;
                    const startDate = document.getElementById('start_date').value;
                    const endDate = document.getElementById('end_date').value;
                    
                    if (!employeeId || !trainingName || !trainingType || !startDate || !endDate) {
                        e.preventDefault();
                        alert('Please fill in all required fields.');
                    }
                    
                    // Validate end date is after start date
                    if (startDate && endDate) {
                        const start = new Date(startDate);
                        const end = new Date(endDate);
                        
                        if (end < start) {
                            e.preventDefault();
                            alert('End date must be after start date.');
                        }
                    }
                });
            }
        });
    </script>
</body>
</html>