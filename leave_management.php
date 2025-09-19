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

// For leave_management.php, check if user has HR privileges
$current_page = basename($_SERVER['PHP_SELF']);
if ($current_page === 'leave_management.php') {
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

// Handle leave request submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_leave'])) {
    $employee_id = $_POST['employee_id'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $days_requested = $_POST['days_requested'];
    $reason = $_POST['reason'];
    $leave_type = $_POST['leave_type'];
    
    // Calculate days between dates if not provided
    if (empty($days_requested)) {
        $start = new DateTime($start_date);
        $end = new DateTime($end_date);
        $interval = $start->diff($end);
        $days_requested = $interval->days + 1; // Inclusive of both dates
    }
    
    // Insert into the correct table (leave_requests)
    $sql = "INSERT INTO leave_requests (employee_id, leave_type, start_date, end_date, days_requested, reason, status) 
            VALUES ('$employee_id', '$leave_type', '$start_date', '$end_date', '$days_requested', '$reason', 'Pending')";
    
    if ($conn->query($sql)) {
        $message = "Leave request submitted successfully!";
        $message_type = "success";
    } else {
        $message = "Error: " . $conn->error;
        $message_type = "error";
    }
}

// Handle leave approval
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_leave'])) {
    $request_id = $_POST['request_id'];
    
    $sql = "UPDATE leave_requests SET status = 'Approved', approved_by = '{$_SESSION['user_id']}', approved_date = CURDATE() 
            WHERE id = '$request_id'";
    
    if ($conn->query($sql)) {
        $message = "Leave request approved!";
        $message_type = "success";
    } else {
        $message = "Error: " . $conn->error;
        $message_type = "error";
    }
}

// Handle leave rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reject_leave'])) {
    $request_id = $_POST['request_id'];
    
    $sql = "UPDATE leave_requests SET status = 'Rejected', approved_by = '{$_SESSION['user_id']}', approved_date = CURDATE() 
            WHERE id = '$request_id'";
    
    if ($conn->query($sql)) {
        $message = "Leave request rejected!";
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
    $filter_conditions[] = "lr.employee_id = '$filter_employee'";
}
if (!empty($filter_status)) {
    $filter_conditions[] = "lr.status = '$filter_status'";
}
if (!empty($filter_type)) {
    $filter_conditions[] = "lr.leave_type = '$filter_type'";
}
if (!empty($filter_start_date)) {
    $filter_conditions[] = "lr.start_date >= '$filter_start_date'";
}
if (!empty($filter_end_date)) {
    $filter_conditions[] = "lr.end_date <= '$filter_end_date'";
}

$filter_sql = "";
if (!empty($filter_conditions)) {
    $filter_sql = "WHERE " . implode(" AND ", $filter_conditions);
}

// Fetch leave requests from the correct table with filters
$leave_requests = array();
$leave_sql = "SELECT lr.*, e.name as employee_name, e.department as employee_department
              FROM leave_requests lr 
              JOIN employees e ON lr.employee_id = e.id 
              $filter_sql
              ORDER BY lr.created_at DESC LIMIT 100";
$leave_result = $conn->query($leave_sql);

if ($leave_result->num_rows > 0) {
    while ($row = $leave_result->fetch_assoc()) {
        $leave_requests[] = $row;
    }
}

// Get leave statistics
$stats_sql = "SELECT 
                COUNT(*) as total_requests,
                SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending_requests,
                SUM(CASE WHEN status = 'Approved' THEN 1 ELSE 0 END) as approved_requests,
                SUM(CASE WHEN status = 'Rejected' THEN 1 ELSE 0 END) as rejected_requests
              FROM leave_requests";
$stats_result = $conn->query($stats_sql);
$stats = $stats_result->fetch_assoc();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave Management System - Buymeabook</title>
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
        
        @media (max-width: 1200px) {
            .dashboard {
                grid-template-columns: 250px 1fr;
            }
            
            .stats-grid {
                grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
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
                    <li><a href="leave_management.php" class="active"><i class="fas fa-calendar-day"></i> Leave Management</a></li>
                    <li><a href="performance_management.php"><i class="fas fa-chart-line"></i> Performance</a></li>
                    <li><a href="compensation_history.php"><i class="fas fa-money-check-alt"></i> Compensation</a></li>
                    <li><a href="training.php"><i class="fas fa-graduation-cap"></i> Training</a></li>
                    <li><a href="disciplinary_actions.php"><i class="fas fa-exclamation-circle"></i> Disciplinary</a></li>
                    <li><a href="exit_management.php"><i class="fas fa-door-open"></i> Exit Management</a></li>
                    <li><a href="benefits.php"><i class="fas fa-stethoscope"></i> Benefits</a></li>
					<li><a href="ask_hr.php"><i class="fas fa-headset"></i> ASK HR Tickets</a></li>
					<li><a href="employees_details_hr.php"><i class="fas fa-user-edit"></i> Employee Details</a></li>
                </ul>
            </div>
            
            <div class="main-content">
                <h2 class="section-title"><i class="fas fa-calendar-day"></i> Leave Management System</h2>
                
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <div class="stat-value"><?php echo $stats['total_requests']; ?></div>
                        <div class="stat-label">Total Leave Requests</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-value"><?php echo $stats['pending_requests']; ?></div>
                        <div class="stat-label">Pending Requests</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-value"><?php echo $stats['approved_requests']; ?></div>
                        <div class="stat-label">Approved Requests</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-times-circle"></i>
                        </div>
                        <div class="stat-value"><?php echo $stats['rejected_requests']; ?></div>
                        <div class="stat-label">Rejected Requests</div>
                    </div>
                </div>
                
                <div class="form-section">
                    <h3 class="form-title"><i class="fas fa-calendar-plus"></i> Submit Leave Request</h3>
                    <form method="POST" id="leave-form">
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
                                <label for="leave_type">Leave Type</label>
                                <select id="leave_type" name="leave_type" class="form-control" required>
                                    <option value="">Select Type</option>
                                    <option value="Vacation">Vacation</option>
                                    <option value="Sick">Sick Leave</option>
                                    <option value="Personal">Personal</option>
                                    <option value="Maternity">Maternity</option>
                                    <option value="Paternity">Paternity</option>
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
                                <label for="days_requested">Days Requested</label>
                                <input type="number" id="days_requested" name="days_requested" class="form-control" min="0.5" step="0.5">
                                <small>Leave empty to auto-calculate from dates</small>
                            </div>
                            
                            <div class="form-group" style="grid-column: 1 / -1;">
                                <label for="reason">Reason</label>
                                <textarea id="reason" name="reason" class="form-control" rows="3" required></textarea>
                            </div>
                        </div>
                        
                        <button type="submit" name="add_leave" class="btn"><i class="fas fa-paper-plane"></i> Submit Leave Request</button>
                    </form>
                </div>
                
                <div class="filter-section">
                    <h3 class="form-title"><i class="fas fa-filter"></i> Filter Leave Requests</h3>
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
                                <option value="Pending" <?php echo $filter_status == 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="Approved" <?php echo $filter_status == 'Approved' ? 'selected' : ''; ?>>Approved</option>
                                <option value="Rejected" <?php echo $filter_status == 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="filter_type">Leave Type</label>
                            <select id="filter_type" name="filter_type" class="form-control">
                                <option value="">All Types</option>
                                <option value="Vacation" <?php echo $filter_type == 'Vacation' ? 'selected' : ''; ?>>Vacation</option>
                                <option value="Sick" <?php echo $filter_type == 'Sick' ? 'selected' : ''; ?>>Sick Leave</option>
                                <option value="Personal" <?php echo $filter_type == 'Personal' ? 'selected' : ''; ?>>Personal</option>
                                <option value="Maternity" <?php echo $filter_type == 'Maternity' ? 'selected' : ''; ?>>Maternity</option>
                                <option value="Paternity" <?php echo $filter_type == 'Paternity' ? 'selected' : ''; ?>>Paternity</option>
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
                            <a href="leave_management.php" class="btn btn-secondary btn-sm"><i class="fas fa-times"></i> Clear Filters</a>
                        </div>
                    </form>
                </div>
                
                <div class="data-section">
                    <h3 class="form-title"><i class="fas fa-calendar-check"></i> Leave Requests</h3>
                    <div style="overflow-x: auto;">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Employee</th>
                                    <th>Department</th>
                                    <th>Request Date</th>
                                    <th>Leave Type</th>
                                    <th>Start Date</th>
                                    <th>End Date</th>
                                    <th>Days</th>
                                    <th>Reason</th>
                                    <th>Status</th>
                                    <th>Approved By</th>
                                    <th>Approved Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (count($leave_requests) > 0): ?>
                                <?php foreach ($leave_requests as $request): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($request['employee_name']); ?></td>
                                        <td><?php echo htmlspecialchars($request['employee_department']); ?></td>
                                        <td><?php echo date('M j, Y', strtotime($request['created_at'])); ?></td>
                                        <td><?php echo htmlspecialchars($request['leave_type']); ?></td>
                                        <td><?php echo date('M j, Y', strtotime($request['start_date'])); ?></td>
                                        <td><?php echo date('M j, Y', strtotime($request['end_date'])); ?></td>
                                        <td><?php echo $request['days_requested']; ?></td>
                                        <td><?php echo !empty($request['reason']) ? htmlspecialchars(substr($request['reason'], 0, 50)) . (strlen($request['reason']) > 50 ? '...' : '') : '-'; ?></td>
                                        <td>
                                            <span class="badge 
                                                <?php 
                                                if ($request['status'] == 'Approved') echo 'badge-success';
                                                elseif ($request['status'] == 'Rejected') echo 'badge-danger';
                                                else echo 'badge-warning';
                                                ?>">
                                                <?php echo $request['status']; ?>
                                            </span>
                                        </td>
                                        <td><?php echo !empty($request['approved_by']) ? 'User ' . $request['approved_by'] : '-'; ?></td>
                                        <td><?php echo !empty($request['approved_date']) ? date('M j, Y', strtotime($request['approved_date'])) : '-'; ?></td>
                                        <td>
                                            <?php if ($request['status'] == 'Pending' && $is_hr): ?>
                                                <div style="display: flex; gap: 5px; flex-wrap: wrap;">
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                                        <button type="submit" name="approve_leave" class="btn btn-success btn-sm">
                                                            <i class="fas fa-check"></i> Approve
                                                        </button>
                                                    </form>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                                        <button type="submit" name="reject_leave" class="btn btn-danger btn-sm">
                                                            <i class="fas fa-times"></i> Reject
                                                        </button>
                                                    </form>
                                                </div>
                                            <?php else: ?>
                                                <span>Processed</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="12" style="text-align: center;">No leave requests found.</td>
                                </tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Calculate days between dates for leave form
            const startDateInput = document.getElementById('start_date');
            const endDateInput = document.getElementById('end_date');
            const daysRequestedInput = document.getElementById('days_requested');

            function calculateDays() {
                if (startDateInput.value && endDateInput.value) {
                    const start = new Date(startDateInput.value);
                    const end = new Date(endDateInput.value);
                    
                    // Calculate difference in days (inclusive)
                    const diffTime = Math.abs(end - start);
                    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
                    
                    daysRequestedInput.value = diffDays;
                }
            }

            if (startDateInput && endDateInput && daysRequestedInput) {
                startDateInput.addEventListener('change', calculateDays);
                endDateInput.addEventListener('change', calculateDays);
            }

            // Form validation for leave request
            const leaveForm = document.getElementById('leave-form');
            if (leaveForm) {
                leaveForm.addEventListener('submit', function(e) {
                    const employeeId = document.getElementById('employee_id').value;
                    const startDate = document.getElementById('start_date').value;
                    const endDate = document.getElementById('end_date').value;
                    const leaveType = document.getElementById('leave_type').value;
                    const reason = document.getElementById('reason').value;
                    
                    if (!employeeId || !startDate || !endDate || !leaveType || !reason) {
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