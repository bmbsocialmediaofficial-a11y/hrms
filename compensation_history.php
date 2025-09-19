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

// For compensation_history.php, check if user has HR privileges
$current_page = basename($_SERVER['PHP_SELF']);
if ($current_page === 'compensation_history.php') {
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

// Check if user is HR/admin (you might need to adjust this based on your user roles)
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

// Handle compensation record addition
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_compensation'])) {
    $employee_id = $_POST['employee_id'];
    $effective_date = $_POST['effective_date'];
    $salary = $_POST['salary'];
    $bonus = !empty($_POST['bonus']) ? $_POST['bonus'] : 0;
    $incentives = !empty($_POST['incentives']) ? $_POST['incentives'] : 0;
    $adjustment_reason = $_POST['adjustment_reason'];
    $notes = !empty($_POST['notes']) ? $conn->real_escape_string($_POST['notes']) : null;
    $approved_by = $_SESSION['user_id'];
    
    $sql = "INSERT INTO compensation_history (employee_id, effective_date, salary, bonus, incentives, adjustment_reason, notes, approved_by) 
            VALUES ('$employee_id', '$effective_date', '$salary', '$bonus', '$incentives', '$adjustment_reason', " . 
            ($notes ? "'$notes'" : "NULL") . ", '$approved_by')";
    
    if ($conn->query($sql)) {
        $message = "Compensation record added successfully!";
        $message_type = "success";
    } else {
        $message = "Error: " . $conn->error;
        $message_type = "error";
    }
}

// Handle compensation record update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_compensation'])) {
    $compensation_id = $_POST['compensation_id'];
    $effective_date = $_POST['effective_date'];
    $salary = $_POST['salary'];
    $bonus = !empty($_POST['bonus']) ? $_POST['bonus'] : 0;
    $incentives = !empty($_POST['incentives']) ? $_POST['incentives'] : 0;
    $adjustment_reason = $_POST['adjustment_reason'];
    $notes = !empty($_POST['notes']) ? $conn->real_escape_string($_POST['notes']) : null;
    
    $sql = "UPDATE compensation_history SET 
            effective_date = '$effective_date', 
            salary = '$salary', 
            bonus = '$bonus', 
            incentives = '$incentives', 
            adjustment_reason = '$adjustment_reason', 
            notes = " . ($notes ? "'$notes'" : "NULL") . "
            WHERE id = '$compensation_id'";
    
    if ($conn->query($sql)) {
        $message = "Compensation record updated successfully!";
        $message_type = "success";
    } else {
        $message = "Error: " . $conn->error;
        $message_type = "error";
    }
}

// Fetch compensation records
$compensation_records = array();
$comp_sql = "SELECT ch.*, e.name as employee_name, e.email, e.department, e.created_at as joined_date, a.name as approved_by_name 
             FROM compensation_history ch 
             JOIN employees e ON ch.employee_id = e.id 
             JOIN employees a ON ch.approved_by = a.id 
             ORDER BY ch.effective_date DESC, ch.employee_id LIMIT 50";
$comp_result = $conn->query($comp_sql);

if ($comp_result->num_rows > 0) {
    while ($row = $comp_result->fetch_assoc()) {
        $compensation_records[] = $row;
    }
}

// Fetch compensation analytics
$analytics = array();
// Total compensation by department
$dept_sql = "SELECT e.department, 
                    COUNT(ch.id) as record_count, 
                    AVG(ch.salary) as avg_salary, 
                    AVG(ch.bonus) as avg_bonus, 
                    AVG(ch.incentives) as avg_incentives,
                    SUM(ch.salary + ch.bonus + ch.incentives) as total_compensation
             FROM compensation_history ch
             JOIN employees e ON ch.employee_id = e.id
             GROUP BY e.department";
$dept_result = $conn->query($dept_sql);

if ($dept_result->num_rows > 0) {
    $analytics['by_department'] = array();
    while ($row = $dept_result->fetch_assoc()) {
        $analytics['by_department'][] = $row;
    }
}

// Compensation trend over time
$trend_sql = "SELECT YEAR(effective_date) as year, 
                     MONTH(effective_date) as month, 
                     COUNT(id) as record_count, 
                     AVG(salary) as avg_salary, 
                     AVG(bonus) as avg_bonus, 
                     AVG(incentives) as avg_incentives
              FROM compensation_history 
              GROUP BY YEAR(effective_date), MONTH(effective_date)
              ORDER BY year DESC, month DESC
              LIMIT 12";
$trend_result = $conn->query($trend_sql);

if ($trend_result->num_rows > 0) {
    $analytics['trend'] = array();
    while ($row = $trend_result->fetch_assoc()) {
        $analytics['trend'][] = $row;
    }
}

// Adjustment reason statistics
$reason_sql = "SELECT adjustment_reason, 
                      COUNT(id) as count, 
                      AVG(salary) as avg_salary, 
                      AVG(bonus) as avg_bonus, 
                      AVG(incentives) as avg_incentives
               FROM compensation_history 
               GROUP BY adjustment_reason
               ORDER BY count DESC";
$reason_result = $conn->query($reason_sql);

if ($reason_result->num_rows > 0) {
    $analytics['by_reason'] = array();
    while ($row = $reason_result->fetch_assoc()) {
        $analytics['by_reason'][] = $row;
    }
}

// Prepare record details for modal view
$record_details = array();
if (isset($_GET['view_record'])) {
    $record_id = $_GET['view_record'];
    $detail_sql = "SELECT ch.*, e.name as employee_name, e.email, e.department, e.created_at as joined_date, a.name as approved_by_name 
                   FROM compensation_history ch 
                   JOIN employees e ON ch.employee_id = e.id 
                   JOIN employees a ON ch.approved_by = a.id 
                   WHERE ch.id = '$record_id'";
    $detail_result = $conn->query($detail_sql);
    
    if ($detail_result->num_rows > 0) {
        $record_details = $detail_result->fetch_assoc();
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Compensation Management System - Buymeabook</title>
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
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.08);
            text-align: center;
        }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: #1a2a6c;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
            margin: 0 auto 15px;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #1a2a6c;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.9rem;
        }
        
        .form-section {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.08);
        }
        
        .form-title {
            font-size: 1.2rem;
            margin-bottom: 20px;
            color: #1a2a6c;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #444;
        }
        
        .form-control {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            transition: border 0.3s;
        }
        
        .form-control:focus {
            border-color: #1a2a6c;
            outline: none;
        }
        
        .btn {
            padding: 12px 20px;
            background: #1a2a6c;
            color: white;
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
        
        .data-section {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.08);
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .data-table th, .data-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .data-table th {
            background: #f8f9fa;
            color: #1a2a6c;
            font-weight: 600;
        }
        
        .data-table tr:hover {
            background: #f8f9fa;
        }
        
        .badge {
            padding: 5px 10px;
            border-radius: 50px;
            font-size: 0.8rem;
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
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            text-align: center;
            font-weight: 500;
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
        
        .tabs {
            display: flex;
            gap: 5px;
            margin-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        
        .tab {
            padding: 10px 20px;
            cursor: pointer;
            border-radius: 5px 5px 0 0;
            transition: all 0.3s;
        }
        
        .tab.active {
            background: #1a2a6c;
            color: white;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .chart-container {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.08);
        }
        
        .chart-title {
            font-size: 1.2rem;
            margin-bottom: 20px;
            color: #1a2a6c;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .chart {
            height: 300px;
            width: 100%;
            position: relative;
        }
        
        .analytics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
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
            background-color: rgba(0,0,0,0.4);
        }
        
        .modal-content {
            background-color: #fefefe;
            margin: 10% auto;
            padding: 25px;
            border: 1px solid #888;
            width: 80%;
            max-width: 700px;
            border-radius: 10px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.2);
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
        
        .detail-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-top: 20px;
        }
        
        .detail-item {
            margin-bottom: 15px;
        }
        
        .detail-label {
            font-weight: 600;
            color: #1a2a6c;
            margin-bottom: 5px;
        }
        
        .detail-value {
            padding: 8px;
            background: #f8f9fa;
            border-radius: 5px;
            min-height: 20px;
        }
        
        @media (max-width: 1024px) {
            .dashboard {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            }
            
            .analytics-grid {
                grid-template-columns: 1fr;
            }
            
            .detail-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .nav-links {
                flex-wrap: wrap;
                justify-content: center;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .modal-content {
                width: 95%;
                margin: 5% auto;
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
                    <li><a href="compensation_history.php" class="active"><i class="fas fa-money-check-alt"></i> Compensation</a></li>
                    <li><a href="training.php"><i class="fas fa-graduation-cap"></i> Training</a></li>
                    <li><a href="disciplinary_actions.php"><i class="fas fa-exclamation-circle"></i> Disciplinary</a></li>
                    <li><a href="exit_management.php"><i class="fas fa-door-open"></i> Exit Management</a></li>
                    <li><a href="benefits.php"><i class="fas fa-stethoscope"></i> Benefits</a></li>
					<li><a href="ask_hr.php"><i class="fas fa-headset"></i> ASK HR Tickets</a></li>
					<li><a href="employees_details_hr.php"><i class="fas fa-user-edit"></i> Employee Details</a></li>
                </ul>
            </div>
            
            <div class="main-content">
                <h2 class="section-title"><i class="fas fa-money-check-alt"></i> Compensation History</h2>
                
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                        <div class="stat-value"><?php echo count($compensation_records); ?></div>
                        <div class="stat-label">Compensation Records</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-value"><?php echo count($employees); ?></div>
                        <div class="stat-label">Total Employees</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <?php
                        $avg_salary = 0;
                        if (count($compensation_records) > 0) {
                            $salaries = array_column($compensation_records, 'salary');
                            $avg_salary = array_sum($salaries) / count($salaries);
                        }
                        ?>
                        <div class="stat-value">$<?php echo number_format($avg_salary, 0); ?></div>
                        <div class="stat-label">Average Salary</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-gift"></i>
                        </div>
                        <?php
                        $total_bonus = 0;
                        if (count($compensation_records) > 0) {
                            $bonuses = array_column($compensation_records, 'bonus');
                            $total_bonus = array_sum($bonuses);
                        }
                        ?>
                        <div class="stat-value">$<?php echo number_format($total_bonus, 0); ?></div>
                        <div class="stat-label">Total Bonus Paid</div>
                    </div>
                </div>
                
                <div class="tabs">
                    <div class="tab active" data-tab="records">Compensation Records</div>
                    <div class="tab" data-tab="analytics">Analytics</div>
                </div>
                
                <div class="tab-content active" id="records-tab">
                    <div class="form-section">
                        <h3 class="form-title"><i class="fas fa-plus-circle"></i> Add Compensation Record</h3>
                        <form method="POST" id="compensation-form">
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
                                    <label for="effective_date">Effective Date</label>
                                    <input type="date" id="effective_date" name="effective_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="salary">Salary ($)</label>
                                    <input type="number" id="salary" name="salary" class="form-control" min="0" step="0.01" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="bonus">Bonus ($)</label>
                                    <input type="number" id="bonus" name="bonus" class="form-control" min="0" step="0.01" value="0">
                                </div>
                                
                                <div class="form-group">
                                    <label for="incentives">Incentives ($)</label>
                                    <input type="number" id="incentives" name="incentives" class="form-control" min="0" step="0.01" value="0">
                                </div>
                                
                                <div class="form-group">
                                    <label for="adjustment_reason">Adjustment Reason</label>
                                    <select id="adjustment_reason" name="adjustment_reason" class="form-control" required>
                                        <option value="">Select Reason</option>
                                        <option value="Annual Raise">Annual Raise</option>
                                        <option value="Promotion">Promotion</option>
                                        <option value="Performance Bonus">Performance Bonus</option>
                                        <option value="Market Adjustment">Market Adjustment</option>
                                        <option value="Role Change">Role Change</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="notes">Notes</label>
                                    <textarea id="notes" name="notes" class="form-control" rows="3"></textarea>
                                </div>
                            </div>
                            
                            <button type="submit" name="add_compensation" class="btn"><i class="fas fa-save"></i> Save Compensation Record</button>
                        </form>
                    </div>
                    
                    <div class="data-section">
                        <h3 class="form-title"><i class="fas fa-history"></i> Compensation History</h3>
                        <div style="overflow-x: auto;">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th>Effective Date</th>
                                        <th>Salary</th>
                                        <th>Bonus</th>
                                        <th>Incentives</th>
                                        <th>Total</th>
                                        <th>Reason</th>
                                        <th>Approved By</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php if (count($compensation_records) > 0): ?>
                                    <?php foreach ($compensation_records as $record): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($record['employee_name']); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($record['effective_date'])); ?></td>
                                            <td>$<?php echo number_format($record['salary'], 2); ?></td>
                                            <td>$<?php echo number_format($record['bonus'], 2); ?></td>
                                            <td>$<?php echo number_format($record['incentives'], 2); ?></td>
                                            <td><strong>$<?php echo number_format($record['salary'] + $record['bonus'] + $record['incentives'], 2); ?></strong></td>
                                            <td>
                                                <span class="badge 
                                                    <?php 
                                                    if ($record['adjustment_reason'] == 'Promotion') echo 'badge-success';
                                                    elseif ($record['adjustment_reason'] == 'Annual Raise') echo 'badge-info';
                                                    elseif ($record['adjustment_reason'] == 'Performance Bonus') echo 'badge-warning';
                                                    else echo 'badge-secondary';
                                                    ?>">
                                                    <?php echo $record['adjustment_reason']; ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($record['approved_by_name']); ?></td>
                                            <td>
                                                <a href="?view_record=<?php echo $record['id']; ?>" class="btn btn-secondary" style="padding: 5px 10px; font-size: 14px;">
                                                    <i class="fas fa-eye"></i> View
                                                </a>
                                                <button class="btn btn-success edit-record" data-id="<?php echo $record['id']; ?>" style="padding: 5px 10px; font-size: 14px;">
                                                    <i class="fas fa-edit"></i> Edit
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="9" style="text-align: center;">No compensation records found.</td>
                                    </tr>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <div class="tab-content" id="analytics-tab">
                    <div class="analytics-grid">
                        <div class="chart-container">
                            <h3 class="chart-title">Compensation by Department</h3>
                            <div class="chart">
                                <canvas id="deptChart"></canvas>
                            </div>
                        </div>
                        
                        <div class="chart-container">
                            <h3 class="chart-title">Compensation Trends</h3>
                            <div class="chart">
                                <canvas id="trendChart"></canvas>
                            </div>
                        </div>
                        
                        <div class="chart-container">
                            <h3 class="chart-title">Adjustment Reasons</h3>
                            <div class="chart">
                                <canvas id="reasonChart"></canvas>
                            </div>
                        </div>
                        
                        <div class="data-section">
                            <h3 class="form-title">Department Compensation Details</h3>
                            <div style="overflow-x: auto;">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Department</th>
                                            <th>Records</th>
                                            <th>Avg Salary</th>
                                            <th>Avg Bonus</th>
                                            <th>Avg Incentives</th>
                                            <th>Total Compensation</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php if (isset($analytics['by_department']) && count($analytics['by_department']) > 0): ?>
                                        <?php foreach ($analytics['by_department'] as $dept): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($dept['department']); ?></td>
                                                <td><?php echo $dept['record_count']; ?></td>
                                                <td>$<?php echo number_format($dept['avg_salary'], 2); ?></td>
                                                <td>$<?php echo number_format($dept['avg_bonus'], 2); ?></td>
                                                <td>$<?php echo number_format($dept['avg_incentives'], 2); ?></td>
                                                <td><strong>$<?php echo number_format($dept['total_compensation'], 2); ?></strong></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" style="text-align: center;">No department data available.</td>
                                        </tr>
                                    <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- View Record Modal -->
    <?php if (!empty($record_details)): ?>
    <div id="viewModal" class="modal" style="display: block;">
        <div class="modal-content">
            <span class="close" onclick="window.location.href='compensation_history.php'">&times;</span>
            <h2><i class="fas fa-file-invoice-dollar"></i> Compensation Record Details</h2>
            <div class="detail-grid">
                <div class="detail-item">
                    <div class="detail-label">Record ID</div>
                    <div class="detail-value"><?php echo $record_details['id']; ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Employee Name</div>
                    <div class="detail-value"><?php echo htmlspecialchars($record_details['employee_name']); ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Email</div>
                    <div class="detail-value"><?php echo htmlspecialchars($record_details['email']); ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Department</div>
                    <div class="detail-value"><?php echo htmlspecialchars($record_details['department']); ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Joined Date</div>
                    <div class="detail-value"><?php echo date('M j, Y', strtotime($record_details['joined_date'])); ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Effective Date</div>
                    <div class="detail-value"><?php echo date('M j, Y', strtotime($record_details['effective_date'])); ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Salary ($)</div>
                    <div class="detail-value">$<?php echo number_format($record_details['salary'], 2); ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Bonus ($)</div>
                    <div class="detail-value">$<?php echo number_format($record_details['bonus'], 2); ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Incentives ($)</div>
                    <div class="detail-value">$<?php echo number_format($record_details['incentives'], 2); ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Total Compensation</div>
                    <div class="detail-value"><strong>$<?php echo number_format($record_details['salary'] + $record_details['bonus'] + $record_details['incentives'], 2); ?></strong></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Adjustment Reason</div>
                    <div class="detail-value">
                        <span class="badge 
                            <?php 
                            if ($record_details['adjustment_reason'] == 'Promotion') echo 'badge-success';
                            elseif ($record_details['adjustment_reason'] == 'Annual Raise') echo 'badge-info';
                            elseif ($record_details['adjustment_reason'] == 'Performance Bonus') echo 'badge-warning';
                            else echo 'badge-secondary';
                            ?>">
                            <?php echo $record_details['adjustment_reason']; ?>
                        </span>
                    </div>
                </div>
                <div class="detail-item" style="grid-column: 1 / -1;">
                    <div class="detail-label">Notes</div>
                    <div class="detail-value"><?php echo !empty($record_details['notes']) ? htmlspecialchars($record_details['notes']) : 'No notes provided'; ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Approved By</div>
                    <div class="detail-value"><?php echo htmlspecialchars($record_details['approved_by_name']); ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Created At</div>
                    <div class="detail-value"><?php echo date('M j, Y H:i', strtotime($record_details['created_at'])); ?></div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Tab functionality
            const tabs = document.querySelectorAll('.tab');
            const tabContents = document.querySelectorAll('.tab-content');
            
            tabs.forEach(tab => {
                tab.addEventListener('click', () => {
                    const tabId = tab.getAttribute('data-tab');
                    
                    // Remove active class from all tabs and contents
                    tabs.forEach(t => t.classList.remove('active'));
                    tabContents.forEach(c => c.classList.remove('active'));
                    
                    // Add active class to current tab and content
                    tab.classList.add('active');
                    document.getElementById(`${tabId}-tab`).classList.add('active');
                    
                    // If analytics tab is selected, render charts
                    if (tabId === 'analytics') {
                        renderCharts();
                    }
                });
            });
            
            // Form validation for compensation
            const compensationForm = document.getElementById('compensation-form');
            if (compensationForm) {
                compensationForm.addEventListener('submit', function(e) {
                    const employeeId = document.getElementById('employee_id').value;
                    const effectiveDate = document.getElementById('effective_date').value;
                    const salary = document.getElementById('salary').value;
                    const reason = document.getElementById('adjustment_reason').value;
                    
                    if (!employeeId || !effectiveDate || !salary || !reason) {
                        e.preventDefault();
                        alert('Please fill all required fields.');
                    }
                });
            }
            
            // Edit record functionality
            const editButtons = document.querySelectorAll('.edit-record');
            editButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const recordId = this.getAttribute('data-id');
                    alert('Edit functionality for record ID: ' + recordId + '\n\nIn a complete implementation, this would open an edit form.');
                });
            });
            
            // Render charts for analytics
            function renderCharts() {
                // Department Chart
                const deptCtx = document.getElementById('deptChart').getContext('2d');
                new Chart(deptCtx, {
                    type: 'bar',
                    data: {
                        labels: ['Engineering', 'Marketing', 'Sales', 'HR', 'Finance'],
                        datasets: [{
                            label: 'Average Salary ($)',
                            data: [85000, 65000, 72000, 60000, 75000],
                            backgroundColor: 'rgba(54, 162, 235, 0.5)',
                            borderColor: 'rgba(54, 162, 235, 1)',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });
                
                // Trend Chart
                const trendCtx = document.getElementById('trendChart').getContext('2d');
                new Chart(trendCtx, {
                    type: 'line',
                    data: {
                        labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                        datasets: [{
                            label: 'Average Salary ($)',
                            data: [72000, 72500, 73000, 73500, 74000, 74500, 75000, 75500, 76000, 76500, 77000, 77500],
                            fill: false,
                            borderColor: 'rgba(75, 192, 192, 1)',
                            tension: 0.1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: false
                            }
                        }
                    }
                });
                
                // Reason Chart
                const reasonCtx = document.getElementById('reasonChart').getContext('2d');
                new Chart(reasonCtx, {
                    type: 'pie',
                    data: {
                        labels: ['Annual Raise', 'Promotion', 'Performance Bonus', 'Market Adjustment', 'Other'],
                        datasets: [{
                            data: [45, 20, 15, 10, 10],
                            backgroundColor: [
                                'rgba(54, 162, 235, 0.5)',
                                'rgba(75, 192, 192, 0.5)',
                                'rgba(255, 206, 86, 0.5)',
                                'rgba(153, 102, 255, 0.5)',
                                'rgba(255, 159, 64, 0.5)'
                            ],
                            borderColor: [
                                'rgba(54, 162, 235, 1)',
                                'rgba(75, 192, 192, 1)',
                                'rgba(255, 206, 86, 1)',
                                'rgba(153, 102, 255, 1)',
                                'rgba(255, 159, 64, 1)'
                            ],
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false
                    }
                });
            }
            
            // Render charts if analytics tab is active on page load
            if (document.getElementById('analytics-tab').classList.contains('active')) {
                renderCharts();
            }
            
            // Close modal if clicked outside
            window.addEventListener('click', function(event) {
                const modal = document.getElementById('viewModal');
                if (event.target == modal) {
                    window.location.href = 'compensation_history.php';
                }
            });
        });
    </script>
</body>
</html>