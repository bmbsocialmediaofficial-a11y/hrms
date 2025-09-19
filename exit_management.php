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

// For exit_management.php, check if user has HR privileges
$current_page = basename($_SERVER['PHP_SELF']);
if ($current_page === 'exit_management.php') {
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

// Handle exit record submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_exit'])) {
    $employee_id = $_POST['employee_id'];
    $resignation_date = $_POST['resignation_date'];
    $last_working_date = $_POST['last_working_date'];
    $exit_reason = $_POST['exit_reason'];
    $exit_interview = !empty($_POST['exit_interview']) ? $conn->real_escape_string($_POST['exit_interview']) : null;
    $feedback = !empty($_POST['feedback']) ? $conn->real_escape_string($_POST['feedback']) : null;
    $rehire_eligibility = $_POST['rehire_eligibility'];
    
    // Insert into the exit_management table
    $sql = "INSERT INTO exit_management (employee_id, resignation_date, last_working_date, exit_reason, exit_interview, feedback, rehire_eligibility) 
            VALUES ('$employee_id', '$resignation_date', '$last_working_date', '$exit_reason', " . 
            ($exit_interview ? "'$exit_interview'" : "NULL") . ", " .
            ($feedback ? "'$feedback'" : "NULL") . ", '$rehire_eligibility')";
    
    if ($conn->query($sql)) {
        $message = "Exit record added successfully!";
        $message_type = "success";
    } else {
        $message = "Error: " . $conn->error;
        $message_type = "error";
    }
}

// Handle exit record update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_exit'])) {
    $exit_id = $_POST['exit_id'];
    $exit_reason = $_POST['exit_reason'];
    $exit_interview = !empty($_POST['exit_interview']) ? $conn->real_escape_string($_POST['exit_interview']) : null;
    $feedback = !empty($_POST['feedback']) ? $conn->real_escape_string($_POST['feedback']) : null;
    $rehire_eligibility = $_POST['rehire_eligibility'];
    
    // Build the SET part of the query
    $set_clause = "exit_reason = '$exit_reason', rehire_eligibility = '$rehire_eligibility'";
    if ($exit_interview) {
        $set_clause .= ", exit_interview = '$exit_interview'";
    }
    if ($feedback) {
        $set_clause .= ", feedback = '$feedback'";
    }
    
    $sql = "UPDATE exit_management SET $set_clause WHERE id = '$exit_id'";
    
    if ($conn->query($sql)) {
        $message = "Exit record updated successfully!";
        $message_type = "success";
    } else {
        $message = "Error: " . $conn->error;
        $message_type = "error";
    }
}

// Handle filter submission
$filter_employee = isset($_GET['filter_employee']) ? $_GET['filter_employee'] : '';
$filter_reason = isset($_GET['filter_reason']) ? $_GET['filter_reason'] : '';
$filter_rehire = isset($_GET['filter_rehire']) ? $_GET['filter_rehire'] : '';
$filter_start_date = isset($_GET['filter_start_date']) ? $_GET['filter_start_date'] : '';
$filter_end_date = isset($_GET['filter_end_date']) ? $_GET['filter_end_date'] : '';

// Build filter conditions
$filter_conditions = [];
if (!empty($filter_employee)) {
    $filter_conditions[] = "em.employee_id = '$filter_employee'";
}
if (!empty($filter_reason)) {
    $filter_conditions[] = "em.exit_reason = '$filter_reason'";
}
if (!empty($filter_rehire)) {
    $filter_conditions[] = "em.rehire_eligibility = '$filter_rehire'";
}
if (!empty($filter_start_date)) {
    $filter_conditions[] = "em.resignation_date >= '$filter_start_date'";
}
if (!empty($filter_end_date)) {
    $filter_conditions[] = "em.resignation_date <= '$filter_end_date'";
}

$filter_sql = "";
if (!empty($filter_conditions)) {
    $filter_sql = "WHERE " . implode(" AND ", $filter_conditions);
}

// Fetch exit records with filters
$exit_records = array();
$exit_sql = "SELECT em.*, e.name as employee_name, e.department as employee_department, e.email as employee_email
                FROM exit_management em 
                JOIN employees e ON em.employee_id = e.id 
                $filter_sql
                ORDER BY em.resignation_date DESC, em.created_at DESC LIMIT 100";
$exit_result = $conn->query($exit_sql);

if ($exit_result->num_rows > 0) {
    while ($row = $exit_result->fetch_assoc()) {
        $exit_records[] = $row;
    }
}

// Get exit statistics
$stats_sql = "SELECT 
                COUNT(*) as total_exits,
                SUM(CASE WHEN exit_reason = 'Better Opportunity' THEN 1 ELSE 0 END) as better_opportunity,
                SUM(CASE WHEN exit_reason = 'Relocation' THEN 1 ELSE 0 END) as relocation,
                SUM(CASE WHEN exit_reason = 'Career Change' THEN 1 ELSE 0 END) as career_change,
                SUM(CASE WHEN exit_reason = 'Personal Reasons' THEN 1 ELSE 0 END) as personal_reasons,
                SUM(CASE WHEN exit_reason = 'Termination' THEN 1 ELSE 0 END) as termination,
                SUM(CASE WHEN exit_reason = 'Retirement' THEN 1 ELSE 0 END) as retirement,
                SUM(CASE WHEN rehire_eligibility = 'Eligible' THEN 1 ELSE 0 END) as rehire_eligible,
                SUM(CASE WHEN rehire_eligibility = 'Not Eligible' THEN 1 ELSE 0 END) as rehire_not_eligible,
                SUM(CASE WHEN rehire_eligibility = 'To be Decided' THEN 1 ELSE 0 END) as rehire_tbd
              FROM exit_management";
$stats_result = $conn->query($stats_sql);

$stats = [
    'total_exits' => 0,
    'better_opportunity' => 0,
    'relocation' => 0,
    'career_change' => 0,
    'personal_reasons' => 0,
    'termination' => 0,
    'retirement' => 0,
    'rehire_eligible' => 0,
    'rehire_not_eligible' => 0,
    'rehire_tbd' => 0
];

if ($stats_result->num_rows > 0) {
    $row = $stats_result->fetch_assoc();
    $stats['total_exits'] = $row['total_exits'];
    $stats['better_opportunity'] = $row['better_opportunity'];
    $stats['relocation'] = $row['relocation'];
    $stats['career_change'] = $row['career_change'];
    $stats['personal_reasons'] = $row['personal_reasons'];
    $stats['termination'] = $row['termination'];
    $stats['retirement'] = $row['retirement'];
    $stats['rehire_eligible'] = $row['rehire_eligible'];
    $stats['rehire_not_eligible'] = $row['rehire_not_eligible'];
    $stats['rehire_tbd'] = $row['rehire_tbd'];
}

// Get departments with most exits
$dept_stats_sql = "SELECT e.department, COUNT(em.id) as exit_count
                      FROM exit_management em
                      JOIN employees e ON em.employee_id = e.id
                      GROUP BY e.department
                      ORDER BY exit_count DESC
                      LIMIT 5";
$dept_stats_result = $conn->query($dept_stats_sql);
$dept_stats = [];

if ($dept_stats_result->num_rows > 0) {
    while ($row = $dept_stats_result->fetch_assoc()) {
        $dept_stats[] = $row;
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exit Management - Buymeabook</title>
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
        
        .badge-secondary {
            background: #e2e3e5;
            color: #383d41;
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
                    <li><a href="training.php"><i class="fas fa-graduation-cap"></i> Training</a></li>
                    <li><a href="disciplinary_actions.php"><i class="fas fa-exclamation-circle"></i> Disciplinary</a></li>
                    <li><a href="exit_management.php" class="active"><i class="fas fa-door-open"></i> Exit Management</a></li>
                    <li><a href="benefits.php"><i class="fas fa-stethoscope"></i> Benefits</a></li>
					<li><a href="ask_hr.php"><i class="fas fa-headset"></i> ASK HR Tickets</a></li>
					<li><a href="employees_details_hr.php"><i class="fas fa-user-edit"></i> Employee Details</a></li>
                </ul>
            </div>
            
            <div class="main-content">
                <h2 class="section-title"><i class="fas fa-door-open"></i> Exit Management System</h2>
                
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-door-open"></i>
                        </div>
                        <div class="stat-value"><?php echo $stats['total_exits']; ?></div>
                        <div class="stat-label">Total Exits</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-user-check"></i>
                        </div>
                        <div class="stat-value"><?php echo $stats['rehire_eligible']; ?></div>
                        <div class="stat-label">Rehire Eligible</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-user-times"></i>
                        </div>
                        <div class="stat-value"><?php echo $stats['rehire_not_eligible']; ?></div>
                        <div class="stat-label">Not Rehire Eligible</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-question-circle"></i>
                        </div>
                        <div class="stat-value"><?php echo $stats['rehire_tbd']; ?></div>
                        <div class="stat-label">Rehire To Be Decided</div>
                    </div>
                </div>
                
                <div class="analytics-grid">
                    <div class="analytics-card">
                        <h3 class="analytics-title"><i class="fas fa-chart-pie"></i> Exits by Reason</h3>
                        <ul class="type-list">
                            <li>
                                <span>Better Opportunity</span>
                                <span class="count-badge"><?php echo $stats['better_opportunity']; ?></span>
                            </li>
                            <li>
                                <span>Relocation</span>
                                <span class="count-badge"><?php echo $stats['relocation']; ?></span>
                            </li>
                            <li>
                                <span>Career Change</span>
                                <span class="count-badge"><?php echo $stats['career_change']; ?></span>
                            </li>
                            <li>
                                <span>Personal Reasons</span>
                                <span class="count-badge"><?php echo $stats['personal_reasons']; ?></span>
                            </li>
                            <li>
                                <span>Termination</span>
                                <span class="count-badge"><?php echo $stats['termination']; ?></span>
                            </li>
                            <li>
                                <span>Retirement</span>
                                <span class="count-badge"><?php echo $stats['retirement']; ?></span>
                            </li>
                        </ul>
                    </div>
                    
                    <div class="analytics-card">
                        <h3 class="analytics-title"><i class="fas fa-building"></i> Exits by Department</h3>
                        <ul class="employee-list">
                            <?php if (count($dept_stats) > 0): ?>
                                <?php foreach ($dept_stats as $dept): ?>
                                    <li>
                                        <span><?php echo htmlspecialchars($dept['department']); ?></span>
                                        <span class="count-badge"><?php echo $dept['exit_count']; ?> exits</span>
                                    </li>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <li>No exit records found.</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
                
                <div class="form-section">
                    <h3 class="form-title"><i class="fas fa-plus-circle"></i> Add Exit Record</h3>
                    <form method="POST" id="exit-form">
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
                                <label for="resignation_date">Resignation Date</label>
                                <input type="date" id="resignation_date" name="resignation_date" class="form-control" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="last_working_date">Last Working Date</label>
                                <input type="date" id="last_working_date" name="last_working_date" class="form-control" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="exit_reason">Exit Reason</label>
                                <select id="exit_reason" name="exit_reason" class="form-control" required>
                                    <option value="">Select Reason</option>
                                    <option value="Better Opportunity">Better Opportunity</option>
                                    <option value="Relocation">Relocation</option>
                                    <option value="Career Change">Career Change</option>
                                    <option value="Personal Reasons">Personal Reasons</option>
                                    <option value="Termination">Termination</option>
                                    <option value="Retirement">Retirement</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="rehire_eligibility">Rehire Eligibility</label>
                                <select id="rehire_eligibility" name="rehire_eligibility" class="form-control" required>
                                    <option value="To be Decided">To be Decided</option>
                                    <option value="Eligible">Eligible</option>
                                    <option value="Not Eligible">Not Eligible</option>
                                </select>
                            </div>
                            
                            <div class="form-group" style="grid-column: 1 / -1;">
                                <label for="exit_interview">Exit Interview Notes</label>
                                <textarea id="exit_interview" name="exit_interview" class="form-control" rows="3" placeholder="Enter exit interview notes"></textarea>
                            </div>
                            
                            <div class="form-group" style="grid-column: 1 / -1;">
                                <label for="feedback">Feedback</label>
                                <textarea id="feedback" name="feedback" class="form-control" rows="3" placeholder="Enter employee feedback"></textarea>
                            </div>
                        </div>
                        
                        <button type="submit" name="add_exit" class="btn"><i class="fas fa-save"></i> Add Exit Record</button>
                    </form>
                </div>
                
                <div class="filter-section">
                    <h3 class="form-title"><i class="fas fa-filter"></i> Filter Exit Records</h3>
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
                            <label for="filter_reason">Exit Reason</label>
                            <select id="filter_reason" name="filter_reason" class="form-control">
                                <option value="">All Reasons</option>
                                <option value="Better Opportunity" <?php echo $filter_reason == 'Better Opportunity' ? 'selected' : ''; ?>>Better Opportunity</option>
                                <option value="Relocation" <?php echo $filter_reason == 'Relocation' ? 'selected' : ''; ?>>Relocation</option>
                                <option value="Career Change" <?php echo $filter_reason == 'Career Change' ? 'selected' : ''; ?>>Career Change</option>
                                <option value="Personal Reasons" <?php echo $filter_reason == 'Personal Reasons' ? 'selected' : ''; ?>>Personal Reasons</option>
                                <option value="Termination" <?php echo $filter_reason == 'Termination' ? 'selected' : ''; ?>>Termination</option>
                                <option value="Retirement" <?php echo $filter_reason == 'Retirement' ? 'selected' : ''; ?>>Retirement</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="filter_rehire">Rehire Eligibility</label>
                            <select id="filter_rehire" name="filter_rehire" class="form-control">
                                <option value="">All Statuses</option>
                                <option value="Eligible" <?php echo $filter_rehire == 'Eligible' ? 'selected' : ''; ?>>Eligible</option>
                                <option value="Not Eligible" <?php echo $filter_rehire == 'Not Eligible' ? 'selected' : ''; ?>>Not Eligible</option>
                                <option value="To be Decided" <?php echo $filter_rehire == 'To be Decided' ? 'selected' : ''; ?>>To be Decided</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="filter_start_date">Resignation Date From</label>
                            <input type="date" id="filter_start_date" name="filter_start_date" class="form-control" value="<?php echo $filter_start_date; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="filter_end_date">Resignation Date To</label>
                            <input type="date" id="filter_end_date" name="filter_end_date" class="form-control" value="<?php echo $filter_end_date; ?>">
                        </div>
                        
                        <div class="form-group filter-buttons">
                            <button type="submit" class="btn btn-sm"><i class="fas fa-filter"></i> Apply Filters</button>
                            <a href="exit_management.php" class="btn btn-secondary btn-sm"><i class="fas fa-times"></i> Clear Filters</a>
                        </div>
                    </form>
                </div>
                
                <div class="data-section">
                    <h3 class="form-title"><i class="fas fa-list"></i> Exit Records</h3>
                    <div style="overflow-x: auto;">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Employee</th>
                                    <th>Department</th>
                                    <th>Resignation Date</th>
                                    <th>Last Working Date</th>
                                    <th>Exit Reason</th>
                                    <th>Rehire Eligibility</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (count($exit_records) > 0): ?>
                                <?php foreach ($exit_records as $exit): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($exit['employee_name']); ?></td>
                                        <td><?php echo htmlspecialchars($exit['employee_department']); ?></td>
                                        <td><?php echo date('M j, Y', strtotime($exit['resignation_date'])); ?></td>
                                        <td><?php echo date('M j, Y', strtotime($exit['last_working_date'])); ?></td>
                                        <td>
                                            <span class="badge 
                                                <?php 
                                                if ($exit['exit_reason'] == 'Better Opportunity') echo 'badge-primary';
                                                elseif ($exit['exit_reason'] == 'Relocation') echo 'badge-info';
                                                elseif ($exit['exit_reason'] == 'Career Change') echo 'badge-warning';
                                                elseif ($exit['exit_reason'] == 'Personal Reasons') echo 'badge-secondary';
                                                elseif ($exit['exit_reason'] == 'Termination') echo 'badge-danger';
                                                else echo 'badge-success';
                                                ?>">
                                                <?php echo htmlspecialchars($exit['exit_reason']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge 
                                                <?php 
                                                if ($exit['rehire_eligibility'] == 'Eligible') echo 'badge-success';
                                                elseif ($exit['rehire_eligibility'] == 'Not Eligible') echo 'badge-danger';
                                                else echo 'badge-warning';
                                                ?>">
                                                <?php echo $exit['rehire_eligibility']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div style="display: flex; gap: 5px; flex-wrap: wrap;">
                                                <button class="btn btn-secondary btn-sm view-details" data-interview="<?php echo htmlspecialchars($exit['exit_interview']); ?>" data-feedback="<?php echo htmlspecialchars($exit['feedback']); ?>">
                                                    <i class="fas fa-eye"></i> View
                                                </button>
                                                <button class="btn btn-warning btn-sm update-exit" data-id="<?php echo $exit['id']; ?>" data-reason="<?php echo $exit['exit_reason']; ?>" data-rehire="<?php echo $exit['rehire_eligibility']; ?>" data-interview="<?php echo htmlspecialchars($exit['exit_interview']); ?>" data-feedback="<?php echo htmlspecialchars($exit['feedback']); ?>">
                                                    <i class="fas fa-edit"></i> Update
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" style="text-align: center;">No exit records found.</td>
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
            <h3 class="form-title"><i class="fas fa-info-circle"></i> Exit Details</h3>
            <div style="margin-top: 15px;">
                <h4 style="margin-bottom: 10px; color: #1a2a6c;">Exit Interview Notes</h4>
                <div id="modalInterview" style="padding: 10px; background: #f8f9fa; border-radius: 6px; min-height: 80px; margin-bottom: 15px;">
                    Interview notes will be shown here...
                </div>
                <h4 style="margin-bottom: 10px; color: #1a2a6c;">Feedback</h4>
                <div id="modalFeedback" style="padding: 10px; background: #f8f9fa; border-radius: 6px; min-height: 80px;">
                    Feedback will be shown here...
                </div>
            </div>
        </div>
    </div>

    <!-- Update Exit Modal -->
    <div id="updateModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h3 class="form-title"><i class="fas fa-edit"></i> Update Exit Record</h3>
            <form method="POST" id="update-form">
                <input type="hidden" id="update_exit_id" name="exit_id">
                
                <div class="form-group">
                    <label for="update_reason">Exit Reason</label>
                    <select id="update_reason" name="exit_reason" class="form-control" required>
                        <option value="Better Opportunity">Better Opportunity</option>
                        <option value="Relocation">Relocation</option>
                        <option value="Career Change">Career Change</option>
                        <option value="Personal Reasons">Personal Reasons</option>
                        <option value="Termination">Termination</option>
                        <option value="Retirement">Retirement</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="update_rehire">Rehire Eligibility</label>
                    <select id="update_rehire" name="rehire_eligibility" class="form-control" required>
                        <option value="To be Decided">To be Decided</option>
                        <option value="Eligible">Eligible</option>
                        <option value="Not Eligible">Not Eligible</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="update_interview">Exit Interview Notes</label>
                    <textarea id="update_interview" name="exit_interview" class="form-control" rows="3" placeholder="Enter exit interview notes"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="update_feedback">Feedback</label>
                    <textarea id="update_feedback" name="feedback" class="form-control" rows="3" placeholder="Enter employee feedback"></textarea>
                </div>
                
                <button type="submit" name="update_exit" class="btn"><i class="fas fa-save"></i> Update Exit Record</button>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Modal functionality
            const detailsModal = document.getElementById('detailsModal');
            const updateModal = document.getElementById('updateModal');
            const modalInterview = document.getElementById('modalInterview');
            const modalFeedback = document.getElementById('modalFeedback');
            const updateReason = document.getElementById('update_reason');
            const updateRehire = document.getElementById('update_rehire');
            const updateInterview = document.getElementById('update_interview');
            const updateFeedback = document.getElementById('update_feedback');
            const updateExitId = document.getElementById('update_exit_id');
            const closeButtons = document.querySelectorAll('.close');
            
            // View details buttons
            const viewButtons = document.querySelectorAll('.view-details');
            viewButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const interview = this.getAttribute('data-interview');
                    const feedback = this.getAttribute('data-feedback');
                    
                    modalInterview.innerHTML = interview && interview !== 'null' ? 
                        interview.replace(/\n/g, '<br>') : 
                        '<em>No exit interview notes available.</em>';
                        
                    modalFeedback.innerHTML = feedback && feedback !== 'null' ? 
                        feedback.replace(/\n/g, '<br>') : 
                        '<em>No feedback available.</em>';
                        
                    detailsModal.style.display = 'block';
                });
            });
            
            // Update exit buttons
            const updateButtons = document.querySelectorAll('.update-exit');
            updateButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const exitId = this.getAttribute('data-id');
                    const currentReason = this.getAttribute('data-reason');
                    const currentRehire = this.getAttribute('data-rehire');
                    const currentInterview = this.getAttribute('data-interview');
                    const currentFeedback = this.getAttribute('data-feedback');
                    
                    updateExitId.value = exitId;
                    updateReason.value = currentReason;
                    updateRehire.value = currentRehire;
                    updateInterview.value = currentInterview && currentInterview !== 'null' ? currentInterview : '';
                    updateFeedback.value = currentFeedback && currentFeedback !== 'null' ? currentFeedback : '';
                    
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
            
            // Form validation for exit record
            const exitForm = document.getElementById('exit-form');
            if (exitForm) {
                exitForm.addEventListener('submit', function(e) {
                    const employeeId = document.getElementById('employee_id').value;
                    const resignationDate = document.getElementById('resignation_date').value;
                    const lastWorkingDate = document.getElementById('last_working_date').value;
                    const exitReason = document.getElementById('exit_reason').value;
                    
                    if (!employeeId || !resignationDate || !lastWorkingDate || !exitReason) {
                        e.preventDefault();
                        alert('Please fill in all required fields.');
                    }
                    
                    // Validate last working date is after resignation date
                    if (resignationDate && lastWorkingDate) {
                        const resignation = new Date(resignationDate);
                        const lastWorking = new Date(lastWorkingDate);
                        
                        if (lastWorking < resignation) {
                            e.preventDefault();
                            alert('Last working date must be after resignation date.');
                        }
                    }
                });
            }
        });
    </script>
</body>
</html>