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

// For benefits.php, check if user has HR privileges
$current_page = basename($_SERVER['PHP_SELF']);
if ($current_page === 'benefits.php') {
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

// Handle benefit submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_benefit'])) {
    $employee_id = $_POST['employee_id'];
    $benefit_type = $_POST['benefit_type'];
    $provider = !empty($_POST['provider']) ? $conn->real_escape_string($_POST['provider']) : null;
    $plan_details = !empty($_POST['plan_details']) ? $conn->real_escape_string($_POST['plan_details']) : null;
    $coverage_start_date = !empty($_POST['coverage_start_date']) ? $_POST['coverage_start_date'] : null;
    $coverage_end_date = !empty($_POST['coverage_end_date']) ? $_POST['coverage_end_date'] : null;
    $cost = !empty($_POST['cost']) ? $_POST['cost'] : null;
    $notes = !empty($_POST['notes']) ? $conn->real_escape_string($_POST['notes']) : null;
    
    // Insert into the employee_benefits table
    $sql = "INSERT INTO employee_benefits (employee_id, benefit_type, provider, plan_details, coverage_start_date, coverage_end_date, cost, notes) 
            VALUES ('$employee_id', '$benefit_type', " . 
            ($provider ? "'$provider'" : "NULL") . ", " .
            ($plan_details ? "'$plan_details'" : "NULL") . ", " .
            ($coverage_start_date ? "'$coverage_start_date'" : "NULL") . ", " .
            ($coverage_end_date ? "'$coverage_end_date'" : "NULL") . ", " .
            ($cost ? "'$cost'" : "NULL") . ", " .
            ($notes ? "'$notes'" : "NULL") . ")";
    
    if ($conn->query($sql)) {
        $message = "Benefit record added successfully!";
        $message_type = "success";
    } else {
        $message = "Error: " . $conn->error;
        $message_type = "error";
    }
}

// Handle benefit update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_benefit'])) {
    $benefit_id = $_POST['benefit_id'];
    $benefit_type = $_POST['benefit_type'];
    $provider = !empty($_POST['provider']) ? $conn->real_escape_string($_POST['provider']) : null;
    $plan_details = !empty($_POST['plan_details']) ? $conn->real_escape_string($_POST['plan_details']) : null;
    $coverage_start_date = !empty($_POST['coverage_start_date']) ? $_POST['coverage_start_date'] : null;
    $coverage_end_date = !empty($_POST['coverage_end_date']) ? $_POST['coverage_end_date'] : null;
    $cost = !empty($_POST['cost']) ? $_POST['cost'] : null;
    $notes = !empty($_POST['notes']) ? $conn->real_escape_string($_POST['notes']) : null;
    
    // Build the SET part of the query
    $set_clause = "benefit_type = '$benefit_type'";
    if ($provider) {
        $set_clause .= ", provider = '$provider'";
    }
    if ($plan_details) {
        $set_clause .= ", plan_details = '$plan_details'";
    }
    if ($coverage_start_date) {
        $set_clause .= ", coverage_start_date = '$coverage_start_date'";
    }
    if ($coverage_end_date) {
        $set_clause .= ", coverage_end_date = '$coverage_end_date'";
    }
    if ($cost) {
        $set_clause .= ", cost = '$cost'";
    }
    if ($notes) {
        $set_clause .= ", notes = '$notes'";
    }
    
    $sql = "UPDATE employee_benefits SET $set_clause WHERE id = '$benefit_id'";
    
    if ($conn->query($sql)) {
        $message = "Benefit record updated successfully!";
        $message_type = "success";
    } else {
        $message = "Error: " . $conn->error;
        $message_type = "error";
    }
}

// Handle filter submission
$filter_employee = isset($_GET['filter_employee']) ? $_GET['filter_employee'] : '';
$filter_type = isset($_GET['filter_type']) ? $_GET['filter_type'] : '';
$filter_status = isset($_GET['filter_status']) ? $_GET['filter_status'] : '';

// Build filter conditions
$filter_conditions = [];
if (!empty($filter_employee)) {
    $filter_conditions[] = "eb.employee_id = '$filter_employee'";
}
if (!empty($filter_type)) {
    $filter_conditions[] = "eb.benefit_type = '$filter_type'";
}
if (!empty($filter_status)) {
    if ($filter_status === 'Active') {
        $filter_conditions[] = "(eb.coverage_end_date IS NULL OR eb.coverage_end_date >= CURDATE())";
    } elseif ($filter_status === 'Expired') {
        $filter_conditions[] = "eb.coverage_end_date < CURDATE()";
    }
}

$filter_sql = "";
if (!empty($filter_conditions)) {
    $filter_sql = "WHERE " . implode(" AND ", $filter_conditions);
}

// Fetch benefit records with filters
$benefit_records = array();
$benefit_sql = "SELECT eb.*, e.name as employee_name, e.department as employee_department
                FROM employee_benefits eb 
                JOIN employees e ON eb.employee_id = e.id 
                $filter_sql
                ORDER BY eb.created_at DESC LIMIT 100";
$benefit_result = $conn->query($benefit_sql);

if ($benefit_result->num_rows > 0) {
    while ($row = $benefit_result->fetch_assoc()) {
        $benefit_records[] = $row;
    }
}

// Get benefit statistics
$stats_sql = "SELECT 
                COUNT(*) as total_benefits,
                COUNT(DISTINCT employee_id) as employees_with_benefits,
                benefit_type,
                COUNT(*) as type_count,
                SUM(CASE WHEN coverage_end_date IS NULL OR coverage_end_date >= CURDATE() THEN 1 ELSE 0 END) as active_benefits,
                SUM(CASE WHEN coverage_end_date < CURDATE() THEN 1 ELSE 0 END) as expired_benefits,
                SUM(cost) as total_cost
              FROM employee_benefits
              GROUP BY benefit_type";
$stats_result = $conn->query($stats_sql);

$stats = [
    'total_benefits' => 0,
    'employees_with_benefits' => 0,
    'active_benefits' => 0,
    'expired_benefits' => 0,
    'total_cost' => 0,
    'benefit_types' => []
];

if ($stats_result->num_rows > 0) {
    while ($row = $stats_result->fetch_assoc()) {
        $stats['total_benefits'] = $row['total_benefits'];
        $stats['employees_with_benefits'] = $row['employees_with_benefits'];
        $stats['active_benefits'] = $row['active_benefits'];
        $stats['expired_benefits'] = $row['expired_benefits'];
        $stats['total_cost'] = $row['total_cost'];
        $stats['benefit_types'][$row['benefit_type']] = $row['type_count'];
    }
}

// Get employees with most benefits
$top_employees_sql = "SELECT e.name, e.department, COUNT(eb.id) as benefit_count
                      FROM employee_benefits eb
                      JOIN employees e ON eb.employee_id = e.id
                      GROUP BY eb.employee_id
                      ORDER BY benefit_count DESC
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
    <title>Benefits Management - Buymeabook</title>
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
                <a href="training.php" class="nav-link"><i class="fas fa-graduation-cap"></i> Training</a>
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
                    <li><a href="disciplinary_actions.php"><i class="fas fa-exclamation-circle"></i> Disciplinary</a></li>
                    <li><a href="exit_management.php"><i class="fas fa-door-open"></i> Exit Management</a></li>
                    <li><a href="benefits.php" class="active"><i class="fas fa-stethoscope"></i> Benefits</a></li>
					<li><a href="ask_hr.php"><i class="fas fa-headset"></i> ASK HR Tickets</a></li>
					<li><a href="employees_details_hr.php"><i class="fas fa-user-edit"></i> Employee Details</a></li>
                </ul>
            </div>
            
            <div class="main-content">
                <h2 class="section-title"><i class="fas fa-stethoscope"></i> Employee Benefits Management</h2>
                
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-heart"></i>
                        </div>
                        <div class="stat-value"><?php echo $stats['total_benefits']; ?></div>
                        <div class="stat-label">Total Benefits</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-value"><?php echo $stats['employees_with_benefits']; ?></div>
                        <div class="stat-label">Employees Covered</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-value"><?php echo $stats['active_benefits']; ?></div>
                        <div class="stat-label">Active Benefits</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                        <div class="stat-value">$<?php echo number_format($stats['total_cost'], 2); ?></div>
                        <div class="stat-label">Total Cost</div>
                    </div>
                </div>
                
                <div class="analytics-grid">
                    <div class="analytics-card">
                        <h3 class="analytics-title"><i class="fas fa-chart-pie"></i> Benefits by Type</h3>
                        <ul class="type-list">
                            <?php foreach ($stats['benefit_types'] as $type => $count): ?>
                                <li>
                                    <span><?php echo htmlspecialchars($type); ?></span>
                                    <span class="count-badge"><?php echo $count; ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    
                    <div class="analytics-card">
                        <h3 class="analytics-title"><i class="fas fa-users"></i> Top Employees with Benefits</h3>
                        <ul class="employee-list">
                            <?php if (count($top_employees) > 0): ?>
                                <?php foreach ($top_employees as $employee): ?>
                                    <li>
                                        <span><?php echo htmlspecialchars($employee['name']); ?> (<?php echo htmlspecialchars($employee['department']); ?>)</span>
                                        <span class="count-badge"><?php echo $employee['benefit_count']; ?> benefits</span>
                                    </li>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <li>No benefit records found.</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
                
                <div class="form-section">
                    <h3 class="form-title"><i class="fas fa-plus-circle"></i> Add Benefit Record</h3>
                    <form method="POST" id="benefit-form">
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
                                <label for="benefit_type">Benefit Type</label>
                                <select id="benefit_type" name="benefit_type" class="form-control" required>
                                    <option value="">Select Type</option>
                                    <option value="Health Insurance">Health Insurance</option>
                                    <option value="Retirement Plan">Retirement Plan</option>
                                    <option value="Paid Time Off">Paid Time Off</option>
                                    <option value="Dental Insurance">Dental Insurance</option>
                                    <option value="Vision Insurance">Vision Insurance</option>
                                    <option value="Life Insurance">Life Insurance</option>
                                    <option value="Disability Insurance">Disability Insurance</option>
                                    <option value="Wellness Program">Wellness Program</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="provider">Provider</label>
                                <input type="text" id="provider" name="provider" class="form-control" placeholder="Enter benefit provider">
                            </div>
                            
                            <div class="form-group">
                                <label for="coverage_start_date">Coverage Start Date</label>
                                <input type="date" id="coverage_start_date" name="coverage_start_date" class="form-control">
                            </div>
                            
                            <div class="form-group">
                                <label for="coverage_end_date">Coverage End Date</label>
                                <input type="date" id="coverage_end_date" name="coverage_end_date" class="form-control">
                            </div>
                            
                            <div class="form-group">
                                <label for="cost">Cost ($)</label>
                                <input type="number" id="cost" name="cost" class="form-control" min="0" step="0.01">
                            </div>
                            
                            <div class="form-group" style="grid-column: 1 / -1;">
                                <label for="plan_details">Plan Details</label>
                                <textarea id="plan_details" name="plan_details" class="form-control" rows="3" placeholder="Enter plan details"></textarea>
                            </div>
                            
                            <div class="form-group" style="grid-column: 1 / -1;">
                                <label for="notes">Notes</label>
                                <textarea id="notes" name="notes" class="form-control" rows="2" placeholder="Enter any additional notes"></textarea>
                            </div>
                        </div>
                        
                        <button type="submit" name="add_benefit" class="btn"><i class="fas fa-save"></i> Add Benefit Record</button>
                    </form>
                </div>
                
                <div class="filter-section">
                    <h3 class="form-title"><i class="fas fa-filter"></i> Filter Benefit Records</h3>
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
                            <label for="filter_type">Benefit Type</label>
                            <select id="filter_type" name="filter_type" class="form-control">
                                <option value="">All Types</option>
                                <option value="Health Insurance" <?php echo $filter_type == 'Health Insurance' ? 'selected' : ''; ?>>Health Insurance</option>
                                <option value="Retirement Plan" <?php echo $filter_type == 'Retirement Plan' ? 'selected' : ''; ?>>Retirement Plan</option>
                                <option value="Paid Time Off" <?php echo $filter_type == 'Paid Time Off' ? 'selected' : ''; ?>>Paid Time Off</option>
                                <option value="Dental Insurance" <?php echo $filter_type == 'Dental Insurance' ? 'selected' : ''; ?>>Dental Insurance</option>
                                <option value="Vision Insurance" <?php echo $filter_type == 'Vision Insurance' ? 'selected' : ''; ?>>Vision Insurance</option>
                                <option value="Life Insurance" <?php echo $filter_type == 'Life Insurance' ? 'selected' : ''; ?>>Life Insurance</option>
                                ; ?>>Disability Insurance</option>
                                <option value="Wellness Program" <?php echo $filter_type == 'Wellness Program' ? 'selected' : ''; ?>>Wellness Program</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="filter_status">Status</label>
                            <select id="filter_status" name="filter_status" class="form-control">
                                <option value="">All Statuses</option>
                                <option value="Active" <?php echo $filter_status == 'Active' ? 'selected' : ''; ?>>Active</option>
                                <option value="Expired" <?php echo $filter_status == 'Expired' ? 'selected' : ''; ?>>Expired</option>
                            </select>
                        </div>
                        
                        <div class="form-group filter-buttons">
                            <button type="submit" class="btn btn-sm"><i class="fas fa-filter"></i> Apply Filters</button>
                            <a href="benefit.php" class="btn btn-secondary btn-sm"><i class="fas fa-times"></i> Clear Filters</a>
                        </div>
                    </form>
                </div>
                
                <div class="data-section">
                    <h3 class="form-title"><i class="fas fa-list"></i> Benefit Records</h3>
                    <div style="overflow-x: auto;">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Employee</th>
                                    <th>Department</th>
                                    <th>Benefit Type</th>
                                    <th>Provider</th>
                                    <th>Start Date</th>
                                    <th>End Date</th>
                                    <th>Cost</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (count($benefit_records) > 0): ?>
                                <?php foreach ($benefit_records as $benefit): 
                                    $status = 'Active';
                                    if ($benefit['coverage_end_date'] && strtotime($benefit['coverage_end_date']) < time()) {
                                        $status = 'Expired';
                                    }
                                ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($benefit['employee_name']); ?></td>
                                        <td><?php echo htmlspecialchars($benefit['employee_department']); ?></td>
                                        <td>
                                            <span class="badge 
                                                <?php 
                                                if ($benefit['benefit_type'] == 'Health Insurance') echo 'badge-primary';
                                                elseif ($benefit['benefit_type'] == 'Retirement Plan') echo 'badge-info';
                                                elseif ($benefit['benefit_type'] == 'Paid Time Off') echo 'badge-success';
                                                else echo 'badge-warning';
                                                ?>">
                                                <?php echo htmlspecialchars($benefit['benefit_type']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo !empty($benefit['provider']) ? htmlspecialchars($benefit['provider']) : '-'; ?></td>
                                        <td><?php echo !empty($benefit['coverage_start_date']) ? date('M j, Y', strtotime($benefit['coverage_start_date'])) : '-'; ?></td>
                                        <td><?php echo !empty($benefit['coverage_end_date']) ? date('M j, Y', strtotime($benefit['coverage_end_date'])) : '-'; ?></td>
                                        <td><?php echo !empty($benefit['cost']) ? '$' . number_format($benefit['cost'], 2) : '-'; ?></td>
                                        <td>
                                            <span class="badge <?php echo $status == 'Active' ? 'badge-success' : 'badge-danger'; ?>">
                                                <?php echo $status; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div style="display: flex; gap: 5px; flex-wrap: wrap;">
                                                <button class="btn btn-secondary btn-sm view-details" 
                                                    data-id="<?php echo $benefit['id']; ?>" 
                                                    data-details="<?php echo htmlspecialchars($benefit['plan_details']); ?>"
                                                    data-notes="<?php echo htmlspecialchars($benefit['notes']); ?>">
                                                    <i class="fas fa-eye"></i> View
                                                </button>
                                                <button class="btn btn-warning btn-sm update-benefit" 
                                                    data-id="<?php echo $benefit['id']; ?>" 
                                                    data-type="<?php echo $benefit['benefit_type']; ?>"
                                                    data-provider="<?php echo htmlspecialchars($benefit['provider']); ?>"
                                                    data-start="<?php echo $benefit['coverage_start_date']; ?>"
                                                    data-end="<?php echo $benefit['coverage_end_date']; ?>"
                                                    data-cost="<?php echo $benefit['cost']; ?>"
                                                    data-details="<?php echo htmlspecialchars($benefit['plan_details']); ?>"
                                                    data-notes="<?php echo htmlspecialchars($benefit['notes']); ?>">
                                                    <i class="fas fa-edit"></i> Update
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9" style="text-align: center;">No benefit records found.</td>
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
            <h3 class="form-title"><i class="fas fa-info-circle"></i> Benefit Details</h3>
            <div style="margin-top: 15px;">
                <h4>Plan Details</h4>
                <div id="modalPlanDetails" style="padding: 10px; background: #f8f9fa; border-radius: 6px; min-height: 50px; margin-bottom: 15px;">
                    Details will be shown here...
                </div>
                
                <h4>Notes</h4>
                <div id="modalNotes" style="padding: 10px; background: #f8f9fa; border-radius: 6px; min-height: 50px;">
                    Notes will be shown here...
                </div>
            </div>
        </div>
    </div>

    <!-- Update Benefit Modal -->
    <div id="updateModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h3 class="form-title"><i class="fas fa-edit"></i> Update Benefit Record</h3>
            <form method="POST" id="update-form">
                <input type="hidden" id="update_benefit_id" name="benefit_id">
                
                <div class="form-group">
                    <label for="update_benefit_type">Benefit Type</label>
                    <select id="update_benefit_type" name="benefit_type" class="form-control" required>
                        <option value="Health Insurance">Health Insurance</option>
                        <option value="Retirement Plan">Retirement Plan</option>
                        <option value="Paid Time Off">Paid Time Off</option>
                        <option value="Dental Insurance">Dental Insurance</option>
                        <option value="Vision Insurance">Vision Insurance</option>
                        <option value="Life Insurance">Life Insurance</option>
                        <option value="Disability Insurance">Disability Insurance</option>
                        <option value="Wellness Program">Wellness Program</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="update_provider">Provider</label>
                    <input type="text" id="update_provider" name="provider" class="form-control" placeholder="Enter benefit provider">
                </div>
                
                <div class="form-group">
                    <label for="update_coverage_start_date">Coverage Start Date</label>
                    <input type="date" id="update_coverage_start_date" name="coverage_start_date" class="form-control">
                </div>
                
                <div class="form-group">
                    <label for="update_coverage_end_date">Coverage End Date</label>
                    <input type="date" id="update_coverage_end_date" name="coverage_end_date" class="form-control">
                </div>
                
                <div class="form-group">
                    <label for="update_cost">Cost ($)</label>
                    <input type="number" id="update_cost" name="cost" class="form-control" min="0" step="0.01">
                </div>
                
                <div class="form-group">
                    <label for="update_plan_details">Plan Details</label>
                    <textarea id="update_plan_details" name="plan_details" class="form-control" rows="3" placeholder="Enter plan details"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="update_notes">Notes</label>
                    <textarea id="update_notes" name="notes" class="form-control" rows="2" placeholder="Enter any additional notes"></textarea>
                </div>
                
                <button type="submit" name="update_benefit" class="btn"><i class="fas fa-save"></i> Update Benefit</button>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Modal functionality
            const detailsModal = document.getElementById('detailsModal');
            const updateModal = document.getElementById('updateModal');
            const modalPlanDetails = document.getElementById('modalPlanDetails');
            const modalNotes = document.getElementById('modalNotes');
            const closeButtons = document.querySelectorAll('.close');
            
            // View details buttons
            const viewButtons = document.querySelectorAll('.view-details');
            viewButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const details = this.getAttribute('data-details');
                    const notes = this.getAttribute('data-notes');
                    
                    modalPlanDetails.innerHTML = details && details !== 'null' ? 
                        details.replace(/\n/g, '<br>') : 
                        '<em>No plan details available.</em>';
                        
                    modalNotes.innerHTML = notes && notes !== 'null' ? 
                        notes.replace(/\n/g, '<br>') : 
                        '<em>No notes available.</em>';
                        
                    detailsModal.style.display = 'block';
                });
            });
            
            // Update benefit buttons
            const updateButtons = document.querySelectorAll('.update-benefit');
            updateButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const benefitId = this.getAttribute('data-id');
                    const benefitType = this.getAttribute('data-type');
                    const provider = this.getAttribute('data-provider');
                    const startDate = this.getAttribute('data-start');
                    const endDate = this.getAttribute('data-end');
                    const cost = this.getAttribute('data-cost');
                    const details = this.getAttribute('data-details');
                    const notes = this.getAttribute('data-notes');
                    
                    document.getElementById('update_benefit_id').value = benefitId;
                    document.getElementById('update_benefit_type').value = benefitType;
                    document.getElementById('update_provider').value = provider !== 'null' ? provider : '';
                    document.getElementById('update_coverage_start_date').value = startDate !== 'null' ? startDate : '';
                    document.getElementById('update_coverage_end_date').value = endDate !== 'null' ? endDate : '';
                    document.getElementById('update_cost').value = cost !== 'null' ? cost : '';
                    document.getElementById('update_plan_details').value = details !== 'null' ? details : '';
                    document.getElementById('update_notes').value = notes !== 'null' ? notes : '';
                    
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
            
            // Form validation for benefit record
            const benefitForm = document.getElementById('benefit-form');
            if (benefitForm) {
                benefitForm.addEventListener('submit', function(e) {
                    const employeeId = document.getElementById('employee_id').value;
                    const benefitType = document.getElementById('benefit_type').value;
                    
                    if (!employeeId || !benefitType) {
                        e.preventDefault();
                        alert('Please fill in all required fields.');
                    }
                    
                    // Validate end date is after start date if both are provided
                    const startDate = document.getElementById('coverage_start_date').value;
                    const endDate = document.getElementById('coverage_end_date').value;
                    
                    if (startDate && endDate) {
                        const start = new Date(startDate);
                        const end = new Date(endDate);
                        
                        if (end < start) {
                            e.preventDefault();
                            alert('Coverage end date must be after start date.');
                        }
                    }
                });
            }
        });
    </script>
</body>
</html>