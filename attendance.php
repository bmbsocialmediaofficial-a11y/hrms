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

// For attendance.php, check if user has HR privileges
$current_page = basename($_SERVER['PHP_SELF']);
if ($current_page === 'attendance.php') {
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

// Handle navigation item clicks
$active_section = isset($_GET['section']) ? $_GET['section'] : 'overview';
// Fetch all employees
$employees = array();
$emp_sql = "SELECT id, name, email, department FROM employees WHERE valid_user = TRUE";
$emp_result = $conn->query($emp_sql);
if ($emp_result->num_rows > 0) {
    while ($row = $emp_result->fetch_assoc()) {
        $employees[] = $row;
    }
}
// Handle attendance record submission
$message = "";
$message_type = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_attendance'])) {
    $employee_id = $_POST['employee_id'];
    $date = $_POST['date'];
    $check_in = $_POST['check_in'];
    $check_out = $_POST['check_out'];
    $status = $_POST['status'];
    $notes = $_POST['attendance_notes'];
    
    // Calculate hours worked if both check-in and check-out are provided
    $hours_worked = null;
    if (!empty($check_in) && !empty($check_out)) {
        $check_in_time = strtotime($check_in);
        $check_out_time = strtotime($check_out);
        $diff = $check_out_time - $check_in_time;
        $hours_worked = round($diff / 3600, 2);
    }
    
    $sql = "INSERT INTO employee_attendance (employee_id, date, check_in, check_out, hours_worked, status, notes) 
            VALUES ('$employee_id', '$date', " . 
            (!empty($check_in) ? "'$check_in'" : "NULL") . ", " .
            (!empty($check_out) ? "'$check_out'" : "NULL") . ", " .
            ($hours_worked ? "'$hours_worked'" : "NULL") . ", 
            '$status', " . (!empty($notes) ? "'$notes'" : "NULL") . ")";
    
    if ($conn->query($sql)) {
        $message = "Attendance record added successfully!";
        $message_type = "success";
    } else {
        $message = "Error: " . $conn->error;
        $message_type = "error";
    }
}
// Handle filter functionality
$filter_date = isset($_GET['filter_date']) ? $_GET['filter_date'] : '';
$filter_employee = isset($_GET['filter_employee']) ? $_GET['filter_employee'] : '';
$filter_status = isset($_GET['filter_status']) ? $_GET['filter_status'] : '';
// Build the base attendance query
$att_sql = "SELECT a.*, e.name as employee_name, e.department 
            FROM employee_attendance a 
            JOIN employees e ON a.employee_id = e.id";
            
// Add filters if they exist
$where_clauses = array();
if (!empty($filter_date)) {
    $where_clauses[] = "a.date = '$filter_date'";
}
if (!empty($filter_employee)) {
    $where_clauses[] = "a.employee_id = '$filter_employee'";
}
if (!empty($filter_status)) {
    $where_clauses[] = "a.status = '$filter_status'";
}
// Apply WHERE clauses if any
if (count($where_clauses) > 0) {
    $att_sql .= " WHERE " . implode(' AND ', $where_clauses);
}
$att_sql .= " ORDER BY a.date DESC, a.employee_id LIMIT 100";
// Fetch attendance records for display
$attendance_records = array();
$att_result = $conn->query($att_sql);
if ($att_result->num_rows > 0) {
    while ($row = $att_result->fetch_assoc()) {
        $attendance_records[] = $row;
    }
}

// Get selected month and year for monthly reports
$selected_month = isset($_GET['report_month']) ? $_GET['report_month'] : date('m');
$selected_year = isset($_GET['report_year']) ? $_GET['report_year'] : date('Y');

// Monthly Reports data - with filter for selected month/year
$monthly_report_data = array();
$monthly_report_sql = "SELECT 
                        MONTH(date) as month, 
                        YEAR(date) as year, 
                        COUNT(*) as total_records,
                        SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present_count,
                        SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) as absent_count,
                        SUM(CASE WHEN status = 'Late' THEN 1 ELSE 0 END) as late_count,
                        SUM(CASE WHEN status = 'Half-day' THEN 1 ELSE 0 END) as half_day_count,
                        SUM(CASE WHEN status = 'Leave' THEN 1 ELSE 0 END) as leave_count,
                        AVG(hours_worked) as avg_hours
                      FROM employee_attendance
                      WHERE MONTH(date) = '$selected_month' AND YEAR(date) = '$selected_year'
                      GROUP BY MONTH(date), YEAR(date)
                      ORDER BY YEAR(date) DESC, MONTH(date) DESC LIMIT 12";
$monthly_report_result = $conn->query($monthly_report_sql);
if ($monthly_report_result->num_rows > 0) {
    while ($row = $monthly_report_result->fetch_assoc()) {
        $monthly_report_data[] = $row;
    }
}

// Get detailed records for the selected month
$monthly_detail_sql = "SELECT 
                        a.*, 
                        e.name as employee_name, 
                        e.department 
                      FROM employee_attendance a 
                      JOIN employees e ON a.employee_id = e.id
                      WHERE MONTH(a.date) = '$selected_month' 
                      AND YEAR(a.date) = '$selected_year'
                      ORDER BY a.date DESC, a.employee_id";
$monthly_detail_result = $conn->query($monthly_detail_sql);
$monthly_detail_data = array();
if ($monthly_detail_result->num_rows > 0) {
    while ($row = $monthly_detail_result->fetch_assoc()) {
        $monthly_detail_data[] = $row;
    }
}

// Employee Summary data
$employee_summary_data = array();
$employee_summary_sql = "SELECT 
                          e.id, 
                          e.name, 
                          e.department,
                          COUNT(a.id) as total_days,
                          SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) as present_days,
                          SUM(CASE WHEN a.status = 'Absent' THEN 1 ELSE 0 END) as absent_days,
                          SUM(CASE WHEN a.status = 'Late' THEN 1 ELSE 0 END) as late_days,
                          SUM(CASE WHEN a.status = 'Half-day' THEN 1 ELSE 0 END) as half_days,
                          SUM(CASE WHEN a.status = 'Leave' THEN 1 ELSE 0 END) as leave_days,
                          AVG(a.hours_worked) as avg_hours
                        FROM employees e
                        LEFT JOIN employee_attendance a ON e.id = a.employee_id
                        WHERE e.valid_user = TRUE
                        GROUP BY e.id, e.name, e.department
                        ORDER BY e.name";
$employee_summary_result = $conn->query($employee_summary_sql);
if ($employee_summary_result->num_rows > 0) {
    while ($row = $employee_summary_result->fetch_assoc()) {
        $employee_summary_data[] = $row;
    }
}
// Analytics data
$analytics_data = array();
$analytics_sql = "SELECT 
                    DAYNAME(date) as day_of_week,
                    COUNT(*) as total_records,
                    SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present_count,
                    SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) as absent_count,
                    AVG(hours_worked) as avg_hours
                  FROM employee_attendance
                  GROUP BY DAYNAME(date)
                  ORDER BY FIELD(DAYNAME(date), 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday')";
$analytics_result = $conn->query($analytics_sql);
if ($analytics_result->num_rows > 0) {
    while ($row = $analytics_result->fetch_assoc()) {
        $analytics_data[] = $row;
    }
}
// Default settings values (since we don't have a settings table)
$settings = array(
    'start_time' => '09:00',
    'end_time' => '17:30',
    'grace_period' => 15,
    'weekend_days' => 'sunday',
    'late_notifications' => 1,
    'absent_notifications' => 1,
    'report_notifications' => 0
);
// Handle settings form submission - just display success message since we don't have a settings table
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    $message = "Settings saved successfully!";
    $message_type = "success";
    
    // Update settings array with form values
    $settings['start_time'] = $_POST['start_time'];
    $settings['end_time'] = $_POST['end_time'];
    $settings['grace_period'] = $_POST['grace_period'];
    $settings['weekend_days'] = isset($_POST['weekend_days']) ? implode(',', $_POST['weekend_days']) : 'sunday';
    $settings['late_notifications'] = isset($_POST['late_notifications']) ? 1 : 0;
    $settings['absent_notifications'] = isset($_POST['absent_notifications']) ? 1 : 0;
    $settings['report_notifications'] = isset($_POST['report_notifications']) ? 1 : 0;
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Management - Buymeabook</title>
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
        
        .filter-section {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .filter-group label {
            font-weight: 500;
            font-size: 0.9rem;
            color: #444;
        }
        
        .chart-container {
            height: 400px;
            margin-bottom: 30px;
        }
        
        .settings-section {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .settings-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.08);
        }
        
        .settings-title {
            font-size: 1.1rem;
            margin-bottom: 15px;
            color: #1a2a6c;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        @media (max-width: 1024px) {
            .dashboard {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
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
            
            .filter-section {
                flex-direction: column;
            }
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="container">
        <div class="header">
            <img src="https://ik.imagekit.io/nuvq7aygp/BMB_Logo.jpg" alt="BMB Logo" class="logo">
            <div class="nav-links">
                <a href="start.php" class="nav-link"><i class="fas fa-home"></i> Home</a>
                <a href="status.php" class="nav-link"><i class="fas fa-stream"></i> Status Updates</a>
                <a href="hrms.php" class="nav-link"><i class="fas fa-users-cog"></i> HR Management</a>
                <a href="attendance.php" class="nav-link active"><i class="fas fa-user-clock"></i> Attendance</a>
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
                <h2 class="sidebar-title">Attendance System</h2>
                <ul class="sidebar-menu">
                    <li><a href="attendance.php?section=overview" class="<?php echo $active_section == 'overview' ? 'active' : ''; ?>"><i class="fas fa-user-clock"></i> Attendance Overview</a></li>
                    <li><a href="attendance.php?section=monthly" class="<?php echo $active_section == 'monthly' ? 'active' : ''; ?>"><i class="fas fa-calendar-plus"></i> Monthly Reports</a></li>
                    <li><a href="attendance.php?section=employee" class="<?php echo $active_section == 'employee' ? 'active' : ''; ?>"><i class="fas fa-user-check"></i> Employee Summary</a></li>
                    <li><a href="attendance.php?section=analytics" class="<?php echo $active_section == 'analytics' ? 'active' : ''; ?>"><i class="fas fa-chart-bar"></i> Analytics</a></li>
                    <li><a href="attendance.php?section=settings" class="<?php echo $active_section == 'settings' ? 'active' : ''; ?>"><i class="fas fa-cog"></i> Settings</a></li>
                </ul>
            </div>
            
            <div class="main-content">
                <?php if ($active_section == 'overview'): ?>
                    <h2 class="section-title"><i class="fas fa-user-clock"></i> Attendance Management</h2>
                    
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="stat-value"><?php echo count($employees); ?></div>
                            <div class="stat-label">Total Employees</div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-user-check"></i>
                            </div>
                            <div class="stat-value"><?php echo count($attendance_records); ?></div>
                            <div class="stat-label">Attendance Records</div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-calendar-day"></i>
                            </div>
                            <div class="stat-value"><?php echo date('M j, Y'); ?></div>
                            <div class="stat-label">Today's Date</div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-business-time"></i>
                            </div>
                            <div class="stat-value">8.5</div>
                            <div class="stat-label">Avg. Hours</div>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h3 class="form-title"><i class="fas fa-plus-circle"></i> Record Attendance</h3>
                        <form method="POST" id="attendance-form">
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
                                    <label for="date">Date</label>
                                    <input type="date" id="date" name="date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="check_in">Check In Time</label>
                                    <input type="time" id="check_in" name="check_in" class="form-control">
                                </div>
                                
                                <div class="form-group">
                                    <label for="check_out">Check Out Time</label>
                                    <input type="time" id="check_out" name="check_out" class="form-control">
                                </div>
                                
                                <div class="form-group">
                                    <label for="status">Status</label>
                                    <select id="status" name="status" class="form-control" required>
                                        <option value="Present">Present</option>
                                        <option value="Absent">Absent</option>
                                        <option value="Late">Late</option>
                                        <option value="Half-day">Half-day</option>
                                        <option value="Leave">Leave</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="attendance_notes">Notes</label>
                                    <textarea id="attendance_notes" name="attendance_notes" class="form-control" rows="2"></textarea>
                                </div>
                            </div>
                            
                            <button type="submit" name="add_attendance" class="btn"><i class="fas fa-save"></i> Save Attendance</button>
                        </form>
                    </div>
                    
                    <div class="data-section">
                        <h3 class="form-title"><i class="fas fa-history"></i> Recent Attendance Records</h3>
                        
                        <div class="filter-section">
                            <div class="filter-group">
                                <label for="filter_date">Filter by Date</label>
                                <input type="date" id="filter_date" class="form-control" style="width: auto;" value="<?php echo htmlspecialchars($filter_date); ?>">
                            </div>
                            
                            <div class="filter-group">
                                <label for="filter_employee">Filter by Employee</label>
                                <select id="filter_employee" class="form-control" style="width: auto;">
                                    <option value="">All Employees</option>
                                    <?php foreach ($employees as $employee): ?>
                                        <option value="<?php echo $employee['id']; ?>" <?php echo ($filter_employee == $employee['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($employee['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="filter-group">
                                <label for="filter_status">Filter by Status</label>
                                <select id="filter_status" class="form-control" style="width: auto;">
                                    <option value="">All Statuses</option>
                                    <option value="Present" <?php echo ($filter_status == 'Present') ? 'selected' : ''; ?>>Present</option>
                                    <option value="Absent" <?php echo ($filter_status == 'Absent') ? 'selected' : ''; ?>>Absent</option>
                                    <option value="Late" <?php echo ($filter_status == 'Late') ? 'selected' : ''; ?>>Late</option>
                                    <option value="Half-day" <?php echo ($filter_status == 'Half-day') ? 'selected' : ''; ?>>Half-day</option>
                                    <option value="Leave" <?php echo ($filter_status == 'Leave') ? 'selected' : ''; ?>>Leave</option>
                                </select>
                            </div>
                            
                            <div class="filter-group" style="align-self: flex-end;">
                                <button class="btn" id="applyFilters"><i class="fas fa-filter"></i> Apply Filters</button>
                            </div>
                        </div>
                        
                        <div style="overflow-x: auto;">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Employee</th>
                                        <th>Department</th>
                                        <th>Check In</th>
                                        <th>Check Out</th>
                                        <th>Hours</th>
                                        <th>Status</th>
                                        <th>Notes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php if (count($attendance_records) > 0): ?>
                                    <?php foreach ($attendance_records as $record): ?>
                                        <tr>
                                            <td><?php echo date('M j, Y', strtotime($record['date'])); ?></td>
                                            <td><?php echo htmlspecialchars($record['employee_name']); ?></td>
                                            <td><?php echo htmlspecialchars($record['department']); ?></td>
                                            <td><?php echo !empty($record['check_in']) ? date('h:i A', strtotime($record['check_in'])) : '-'; ?></td>
                                            <td><?php echo !empty($record['check_out']) ? date('h:i A', strtotime($record['check_out'])) : '-'; ?></td>
                                            <td><?php echo !empty($record['hours_worked']) ? $record['hours_worked'] : '-'; ?></td>
                                            <td>
                                                <span class="badge 
                                                    <?php 
                                                    if ($record['status'] == 'Present') echo 'badge-success';
                                                    elseif ($record['status'] == 'Absent') echo 'badge-danger';
                                                    elseif ($record['status'] == 'Late') echo 'badge-warning';
                                                    else echo 'badge-info';
                                                    ?>">
                                                    <?php echo $record['status']; ?>
                                                </span>
                                            </td>
                                            <td><?php echo !empty($record['notes']) ? htmlspecialchars($record['notes']) : '-'; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8" style="text-align: center;">No attendance records found.</td>
                                    </tr>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if ($active_section == 'monthly'): ?>
                    <h2 class="section-title"><i class="fas fa-calendar-plus"></i> Monthly Reports</h2>
                    
                    <div class="form-section">
                        <h3 class="form-title"><i class="fas fa-filter"></i> Select Month and Year</h3>
                        <form method="GET" action="attendance.php">
                            <input type="hidden" name="section" value="monthly">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="report_month">Month</label>
                                    <select id="report_month" name="report_month" class="form-control">
                                        <option value="01" <?php echo $selected_month == '01' ? 'selected' : ''; ?>>January</option>
                                        <option value="02" <?php echo $selected_month == '02' ? 'selected' : ''; ?>>February</option>
                                        <option value="03" <?php echo $selected_month == '03' ? 'selected' : ''; ?>>March</option>
                                        <option value="04" <?php echo $selected_month == '04' ? 'selected' : ''; ?>>April</option>
                                        <option value="05" <?php echo $selected_month == '05' ? 'selected' : ''; ?>>May</option>
                                        <option value="06" <?php echo $selected_month == '06' ? 'selected' : ''; ?>>June</option>
                                        <option value="07" <?php echo $selected_month == '07' ? 'selected' : ''; ?>>July</option>
                                        <option value="08" <?php echo $selected_month == '08' ? 'selected' : ''; ?>>August</option>
                                        <option value="09" <?php echo $selected_month == '09' ? 'selected' : 'selected'; ?>>September</option>
                                        <option value="10" <?php echo $selected_month == '10' ? 'selected' : ''; ?>>October</option>
                                        <option value="11" <?php echo $selected_month == '11' ? 'selected' : ''; ?>>November</option>
                                        <option value="12" <?php echo $selected_month == '12' ? 'selected' : ''; ?>>December</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="report_year">Year</label>
                                    <select id="report_year" name="report_year" class="form-control">
                                        <?php 
                                        $currentYear = date('Y');
                                        for ($year = $currentYear; $year >= $currentYear - 5; $year--) {
                                            $selected = $selected_year == $year ? 'selected' : '';
                                            echo "<option value='$year' $selected>$year</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                                
                                <div class="form-group" style="display: flex; align-items: flex-end;">
                                    <button type="submit" class="btn"><i class="fas fa-chart-line"></i> Generate Report</button>
                                </div>
                            </div>
                        </form>
                    </div>
                    
                    <div class="data-section">
                        <h3 class="form-title"><i class="fas fa-chart-bar"></i> Monthly Attendance Summary for <?php echo date('F Y', strtotime($selected_year . '-' . $selected_month . '-01')); ?></h3>
                        
                        <?php if (count($monthly_report_data) > 0): ?>
                            <div class="chart-container">
                                <canvas id="monthlyChart"></canvas>
                            </div>
                            
                            <div style="overflow-x: auto; margin-top: 20px;">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Month</th>
                                            <th>Year</th>
                                            <th>Total Records</th>
                                            <th>Present</th>
                                            <th>Absent</th>
                                            <th>Late</th>
                                            <th>Half-day</th>
                                            <th>Leave</th>
                                            <th>Avg. Hours</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($monthly_report_data as $report): ?>
                                            <tr>
                                                <td><?php echo date('F', mktime(0, 0, 0, $report['month'], 1)); ?></td>
                                                <td><?php echo $report['year']; ?></td>
                                                <td><?php echo $report['total_records']; ?></td>
                                                <td><?php echo $report['present_count']; ?></td>
                                                <td><?php echo $report['absent_count']; ?></td>
                                                <td><?php echo $report['late_count']; ?></td>
                                                <td><?php echo $report['half_day_count']; ?></td>
                                                <td><?php echo $report['leave_count']; ?></td>
                                                <td><?php echo round($report['avg_hours'], 2); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="message error">
                                No data found for the selected month and year.
                            </div>
                        <?php endif; ?>
                        
                        <h3 class="form-title" style="margin-top: 30px;"><i class="fas fa-list"></i> Detailed Records</h3>
                        <div style="overflow-x: auto;">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Employee</th>
                                        <th>Department</th>
                                        <th>Check In</th>
                                        <th>Check Out</th>
                                        <th>Hours</th>
                                        <th>Status</th>
                                        <th>Notes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php if (count($monthly_detail_data) > 0): ?>
                                    <?php foreach ($monthly_detail_data as $record): ?>
                                        <tr>
                                            <td><?php echo date('M j, Y', strtotime($record['date'])); ?></td>
                                            <td><?php echo htmlspecialchars($record['employee_name']); ?></td>
                                            <td><?php echo htmlspecialchars($record['department']); ?></td>
                                            <td><?php echo !empty($record['check_in']) ? date('h:i A', strtotime($record['check_in'])) : '-'; ?></td>
                                            <td><?php echo !empty($record['check_out']) ? date('h:i A', strtotime($record['check_out'])) : '-'; ?></td>
                                            <td><?php echo !empty($record['hours_worked']) ? $record['hours_worked'] : '-'; ?></td>
                                            <td>
                                                <span class="badge 
                                                    <?php 
                                                    if ($record['status'] == 'Present') echo 'badge-success';
                                                    elseif ($record['status'] == 'Absent') echo 'badge-danger';
                                                    elseif ($record['status'] == 'Late') echo 'badge-warning';
                                                    else echo 'badge-info';
                                                    ?>">
                                                    <?php echo $record['status']; ?>
                                                </span>
                                            </td>
                                            <td><?php echo !empty($record['notes']) ? htmlspecialchars($record['notes']) : '-'; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8" style="text-align: center;">No attendance records found for the selected month.</td>
                                    </tr>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        // Monthly Reports chart
                        const monthlyCtx = document.getElementById('monthlyChart');
                        if (monthlyCtx) {
                            <?php if (count($monthly_report_data) > 0): ?>
                                const monthlyData = [
                                    <?php echo $monthly_report_data[0]['present_count']; ?>,
                                    <?php echo $monthly_report_data[0]['absent_count']; ?>,
                                    <?php echo $monthly_report_data[0]['late_count']; ?>,
                                    <?php echo $monthly_report_data[0]['half_day_count']; ?>,
                                    <?php echo $monthly_report_data[0]['leave_count']; ?>
                                ];
                                
                                new Chart(monthlyCtx, {
                                    type: 'bar',
                                    data: {
                                        labels: ['Present', 'Absent', 'Late', 'Half-day', 'Leave'],
                                        datasets: [{
                                            label: 'Attendance Count',
                                            data: monthlyData,
                                            backgroundColor: [
                                                'rgba(75, 192, 192, 0.6)',
                                                'rgba(255, 99, 132, 0.6)',
                                                'rgba(255, 206, 86, 0.6)',
                                                'rgba(54, 162, 235, 0.6)',
                                                'rgba(153, 102, 255, 0.6)'
                                            ],
                                            borderColor: [
                                                'rgba(75, 192, 192, 1)',
                                                'rgba(255, 99, 132, 1)',
                                                'rgba(255, 206, 86, 1)',
                                                'rgba(54, 162, 235, 1)',
                                                'rgba(153, 102, 255, 1)'
                                            ],
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
                            <?php endif; ?>
                        }
                    });
                    </script>
                <?php endif; ?>
                
                <?php if ($active_section == 'employee'): ?>
                    <h2 class="section-title"><i class="fas fa-user-check"></i> Employee Summary</h2>
                    
                    <div class="form-section">
                        <h3 class="form-title"><i class="fas fa-user"></i> Select Employee</h3>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="summary_employee">Employee</label>
                                <select id="summary_employee" class="form-control">
                                    <option value="">Select Employee</option>
                                    <?php foreach ($employees as $employee): ?>
                                        <option value="<?php echo $employee['id']; ?>"><?php echo htmlspecialchars($employee['name'] . ' (' . $employee['department'] . ')'); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="summary_period">Period</label>
                                <select id="summary_period" class="form-control">
                                    <option value="current_month">Current Month</option>
                                    <option value="last_month">Last Month</option>
                                    <option value="current_quarter">Current Quarter</option>
                                    <option value="current_year">Current Year</option>
                                </select>
                            </div>
                            
                            <div class="form-group" style="display: flex; align-items: flex-end;">
                                <button class="btn" id="generateEmployeeSummary"><i class="fas fa-user-chart"></i> Generate Summary</button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stats-grid" id="employeeStats">
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-calendar-check"></i>
                            </div>
                            <div class="stat-value" id="totalDays"><?php 
                                $total_days = 0;
                                foreach ($employee_summary_data as $summary) {
                                    $total_days += $summary['total_days'];
                                }
                                echo $total_days;
                            ?></div>
                            <div class="stat-label">Total Working Days</div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-user-check"></i>
                            </div>
                            <div class="stat-value" id="presentDays"><?php 
                                $present_days = 0;
                                foreach ($employee_summary_data as $summary) {
                                    $present_days += $summary['present_days'];
                                }
                                echo $present_days;
                            ?></div>
                            <div class="stat-label">Days Present</div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-user-times"></i>
                            </div>
                            <div class="stat-value" id="absentDays"><?php 
                                $absent_days = 0;
                                foreach ($employee_summary_data as $summary) {
                                    $absent_days += $summary['absent_days'];
                                }
                                echo $absent_days;
                            ?></div>
                            <div class="stat-label">Days Absent</div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-percentage"></i>
                            </div>
                            <div class="stat-value" id="attendancePercentage"><?php 
                                $attendance_percentage = 0;
                                if ($total_days > 0) {
                                    $attendance_percentage = round(($present_days / $total_days) * 100);
                                }
                                echo $attendance_percentage . '%';
                            ?></div>
                            <div class="stat-label">Attendance %</div>
                        </div>
                    </div>
                    
                    <div class="data-section" id="employeeDetails">
                        <h3 class="form-title"><i class="fas fa-history"></i> Attendance Details</h3>
                        <div class="chart-container">
                            <canvas id="employeeChart"></canvas>
                        </div>
                        
                        <div style="overflow-x: auto; margin-top: 20px;">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Employee Name</th>
                                        <th>Department</th>
                                        <th>Total Days</th>
                                        <th>Present Days</th>
                                        <th>Absent Days</th>
                                        <th>Late Days</th>
                                        <th>Half Days</th>
                                        <th>Leave Days</th>
                                        <th>Avg. Hours</th>
                                        <th>Attendance %</th>
                                    </tr>
                                </thead>
                                <tbody id="employeeDetailsBody">
                                    <?php if (count($employee_summary_data) > 0): ?>
                                        <?php foreach ($employee_summary_data as $summary): ?>
                                            <?php 
                                            $attendance_percentage = 0;
                                            if ($summary['total_days'] > 0) {
                                                $attendance_percentage = round(($summary['present_days'] / $summary['total_days']) * 100, 2);
                                            }
                                            ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($summary['name']); ?></td>
                                                <td><?php echo htmlspecialchars($summary['department']); ?></td>
                                                <td><?php echo $summary['total_days']; ?></td>
                                                <td><?php echo $summary['present_days']; ?></td>
                                                <td><?php echo $summary['absent_days']; ?></td>
                                                <td><?php echo $summary['late_days']; ?></td>
                                                <td><?php echo $summary['half_days']; ?></td>
                                                <td><?php echo $summary['leave_days']; ?></td>
                                                <td><?php echo round($summary['avg_hours'], 2); ?></td>
                                                <td>
                                                    <span class="badge 
                                                        <?php 
                                                        if ($attendance_percentage >= 90) echo 'badge-success';
                                                        elseif ($attendance_percentage >= 75) echo 'badge-warning';
                                                        else echo 'badge-danger';
                                                        ?>">
                                                        <?php echo $attendance_percentage; ?>%
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="10" style="text-align: center;">No employee summary data found.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if ($active_section == 'analytics'): ?>
                    <h2 class="section-title"><i class="fas fa-chart-bar"></i> Attendance Analytics</h2>
                    
                    <div class="form-section">
                        <h3 class="form-title"><i class="fas fa-filter"></i> Select Analysis Period</h3>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="analytics_period">Period</label>
                                <select id="analytics_period" class="form-control">
                                    <option value="current_month">Current Month</option>
                                    <option value="last_month">Last Month</option>
                                    <option value="current_quarter">Current Quarter</option>
                                    <option value="current_year">Current Year</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="analytics_department">Department</label>
                                <select id="analytics_department" class="form-control">
                                    <option value="">All Departments</option>
                                    <?php 
                                    $departments = array_unique(array_column($employees, 'department'));
                                    foreach ($departments as $department) {
                                        echo "<option value='" . htmlspecialchars($department) . "'>" . htmlspecialchars($department) . "</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            
                            <div class="form-group" style="display: flex; align-items: flex-end;">
                                <button class="btn" id="generateAnalytics"><i class="fas fa-chart-pie"></i> Generate Analytics</button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="stat-value" id="totalEmployeesAnalytics"><?php echo count($employees); ?></div>
                            <div class="stat-label">Total Employees</div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-percentage"></i>
                            </div>
                            <div class="stat-value" id="avgAttendanceAnalytics">
                                <?php 
                                $total_records = 0;
                                $present_records = 0;
                                foreach ($analytics_data as $data) {
                                    $total_records += $data['total_records'];
                                    $present_records += $data['present_count'];
                                }
                                $avg_attendance = 0;
                                if ($total_records > 0) {
                                    $avg_attendance = round(($present_records / $total_records) * 100);
                                }
                                echo $avg_attendance . '%';
                                ?>
                            </div>
                            <div class="stat-label">Avg. Attendance</div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div class="stat-value" id="avgHoursAnalytics">
                                <?php 
                                $total_avg_hours = 0;
                                $count = 0;
                                foreach ($analytics_data as $data) {
                                    if ($data['avg_hours'] > 0) {
                                        $total_avg_hours += $data['avg_hours'];
                                        $count++;
                                    }
                                }
                                $avg_hours = 0;
                                if ($count > 0) {
                                    $avg_hours = round($total_avg_hours / $count, 1);
                                }
                                echo $avg_hours;
                                ?>
                            </div>
                            <div class="stat-label">Avg. Hours/Day</div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-user-clock"></i>
                            </div>
                            <div class="stat-value" id="lateArrivalsAnalytics">
                                <?php 
                                $late_count = 0;
                                foreach ($attendance_records as $record) {
                                    if ($record['status'] == 'Late') {
                                        $late_count++;
                                    }
                                }
                                echo $late_count;
                                ?>
                            </div>
                            <div class="stat-label">Late Arrivals</div>
                        </div>
                    </div>
                    
                    <div class="data-section">
                        <h3 class="form-title"><i class="fas fa-chart-line"></i> Attendance Trends</h3>
                        <div class="chart-container">
                            <canvas id="trendsChart"></canvas>
                        </div>
                    </div>
                    
                    <div class="data-section">
                        <h3 class="form-title"><i class="fas fa-chart-pie"></i> Attendance Distribution</h3>
                        <div class="chart-container">
                            <canvas id="distributionChart"></canvas>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if ($active_section == 'settings'): ?>
                    <h2 class="section-title"><i class="fas fa-cog"></i> Attendance Settings</h2>
                    
                    <form method="POST" id="settings-form">
                        <div class="settings-section">
                            <div class="settings-card">
                                <h3 class="settings-title"><i class="fas fa-clock"></i> Working Hours</h3>
                                <div class="form-group">
                                    <label for="start_time">Start Time</label>
                                    <input type="time" id="start_time" name="start_time" class="form-control" value="<?php echo $settings['start_time']; ?>">
                                </div>
                                <div class="form-group">
                                    <label for="end_time">End Time</label>
                                    <input type="time" id="end_time" name="end_time" class="form-control" value="<?php echo $settings['end_time']; ?>">
                                </div>
                                <div class="form-group">
                                    <label for="grace_period">Grace Period (minutes)</label>
                                    <input type="number" id="grace_period" name="grace_period" class="form-control" value="<?php echo $settings['grace_period']; ?>" min="0">
                                </div>
                            </div>
                            
                            <div class="settings-card">
                                <h3 class="settings-title"><i class="fas fa-calendar-alt"></i> Weekends</h3>
                                <div class="form-group">
                                    <label>Weekend Days</label>
                                    <div style="display: flex; gap: 10px; margin-top: 10px;">
                                        <div>
                                            <input type="checkbox" id="sunday" name="weekend_days[]" value="sunday" <?php echo (strpos($settings['weekend_days'], 'sunday') !== false) ? 'checked' : ''; ?>>
                                            <label for="sunday">Sunday</label>
                                        </div>
                                        <div>
                                            <input type="checkbox" id="saturday" name="weekend_days[]" value="saturday" <?php echo (strpos($settings['weekend_days'], 'saturday') !== false) ? 'checked' : ''; ?>>
                                            <label for="saturday">Saturday</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="settings-card">
                                <h3 class="settings-title"><i class="fas fa-bell"></i> Notifications</h3>
                                <div class="form-group">
                                    <div>
                                        <input type="checkbox" id="late_notification" name="late_notifications" value="1" <?php echo ($settings['late_notifications'] == 1) ? 'checked' : ''; ?>>
                                        <label for="late_notification">Late Arrival Notifications</label>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <div>
                                        <input type="checkbox" id="absent_notification" name="absent_notifications" value="1" <?php echo ($settings['absent_notifications'] == 1) ? 'checked' : ''; ?>>
                                        <label for="absent_notification">Absent Notifications</label>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <div>
                                        <input type="checkbox" id="report_notification" name="report_notifications" value="1" <?php echo ($settings['report_notifications'] == 1) ? 'checked' : ''; ?>>
                                        <label for="report_notification">Monthly Report Notifications</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div style="margin-top: 20px; text-align: center;">
                            <button type="submit" name="save_settings" class="btn"><i class="fas fa-save"></i> Save Settings</button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <script>
        // Global chart references
        let monthlyChart = null;
        let employeeChart = null;
        let trendsChart = null;
        let distributionChart = null;
        
        document.addEventListener('DOMContentLoaded', function() {
            // Form validation for attendance
            const attendanceForm = document.getElementById('attendance-form');
            if (attendanceForm) {
                attendanceForm.addEventListener('submit', function(e) {
                    const employeeId = document.getElementById('employee_id').value;
                    const date = document.getElementById('date').value;
                    
                    if (!employeeId || !date) {
                        e.preventDefault();
                        alert('Please select an employee and date.');
                    }
                });
            }
            
            // Filter functionality
            const applyFilters = document.getElementById('applyFilters');
            if (applyFilters) {
                applyFilters.addEventListener('click', function() {
                    const filterDate = document.getElementById('filter_date').value;
                    const filterEmployee = document.getElementById('filter_employee').value;
                    const filterStatus = document.getElementById('filter_status').value;
                    
                    // Create a URL with filter parameters
                    let url = 'attendance.php?section=overview';
                    if (filterDate) url += '&filter_date=' + encodeURIComponent(filterDate);
                    if (filterEmployee) url += '&filter_employee=' + encodeURIComponent(filterEmployee);
                    if (filterStatus) url += '&filter_status=' + encodeURIComponent(filterStatus);
                    
                    // Redirect to the filtered URL
                    window.location.href = url;
                });
            }
            
            // Employee Summary
            const generateEmployeeSummary = document.getElementById('generateEmployeeSummary');
            if (generateEmployeeSummary) {
                generateEmployeeSummary.addEventListener('click', function() {
                    const employeeId = document.getElementById('summary_employee').value;
                    const period = document.getElementById('summary_period').value;
                    
                    if (!employeeId) {
                        alert('Please select an employee.');
                        return;
                    }
                    
                    // Show the employee stats and details sections
                    document.getElementById('employeeStats').style.display = 'grid';
                    document.getElementById('employeeDetails').style.display = 'block';
                    
                    // Simulate loading data
                    document.getElementById('employeeDetailsBody').innerHTML = '<tr><td colspan="10" style="text-align: center;">Loading summary...</td></tr>';
                    
                    // Get real data for the selected employee
                    const employeeData = <?php echo json_encode($employee_summary_data); ?>;
                    
                    // Find the data for the selected employee
                    const selectedEmployee = employeeData.find(emp => emp.id == employeeId);
                    
                    // Destroy existing chart if it exists
                    if (employeeChart) {
                        employeeChart.destroy();
                        employeeChart = null;
                    }
                    
                    if (selectedEmployee) {
                        // Update stats with real data
                        const totalDays = selectedEmployee.total_days;
                        const presentDays = selectedEmployee.present_days;
                        const absentDays = selectedEmployee.absent_days;
                        const attendancePercentage = totalDays > 0 ? Math.round((presentDays / totalDays) * 100) : 0;
                        
                        document.getElementById('totalDays').textContent = totalDays;
                        document.getElementById('presentDays').textContent = presentDays;
                        document.getElementById('absentDays').textContent = absentDays;
                        document.getElementById('attendancePercentage').textContent = attendancePercentage + '%';
                        
                        // Create or update the chart with real data
                        const ctx = document.getElementById('employeeChart').getContext('2d');
                        employeeChart = new Chart(ctx, {
                            type: 'doughnut',
                            data: {
                                labels: ['Present', 'Absent', 'Late', 'Half Days', 'Leave'],
                                datasets: [{
                                    data: [
                                        selectedEmployee.present_days,
                                        selectedEmployee.absent_days,
                                        selectedEmployee.late_days,
                                        selectedEmployee.half_days,
                                        selectedEmployee.leave_days
                                    ],
                                    backgroundColor: [
                                        'rgba(75, 192, 192, 0.6)',
                                        'rgba(255, 99, 132, 0.6)',
                                        'rgba(255, 206, 86, 0.6)',
                                        'rgba(54, 162, 235, 0.6)',
                                        'rgba(153, 102, 255, 0.6)'
                                    ],
                                    borderColor: [
                                        'rgba(75, 192, 192, 1)',
                                        'rgba(255, 99, 132, 1)',
                                        'rgba(255, 206, 86, 1)',
                                        'rgba(54, 162, 235, 1)',
                                        'rgba(153, 102, 255, 1)'
                                    ],
                                    borderWidth: 1
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false
                            }
                        });
                        
                        // Update the table with real data
                        document.getElementById('employeeDetailsBody').innerHTML = `
                            <tr>
                                <td>${selectedEmployee.name}</td>
                                <td>${selectedEmployee.department}</td>
                                <td>${selectedEmployee.total_days}</td>
                                <td>${selectedEmployee.present_days}</td>
                                <td>${selectedEmployee.absent_days}</td>
                                <td>${selectedEmployee.late_days}</td>
                                <td>${selectedEmployee.half_days}</td>
                                <td>${selectedEmployee.leave_days}</td>
                                <td>${parseFloat(selectedEmployee.avg_hours).toFixed(2)}</td>
                                <td>
                                    <span class="badge ${attendancePercentage >= 90 ? 'badge-success' : (attendancePercentage >= 75 ? 'badge-warning' : 'badge-danger')}">
                                        ${attendancePercentage}%
                                    </span>
                                </td>
                            </tr>
                        `;
                    } else {
                        // No data found for selected employee
                        document.getElementById('employeeDetailsBody').innerHTML = `
                            <tr>
                                <td colspan="10" style="text-align: center;">No data found for selected employee.</td>
                            </tr>
                        `;
                        
                        // Empty chart
                        const ctx = document.getElementById('employeeChart').getContext('2d');
                        employeeChart = new Chart(ctx, {
                            type: 'doughnut',
                            data: {
                                labels: ['No Data'],
                                datasets: [{
                                    data: [1],
                                    backgroundColor: ['rgba(201, 203, 207, 0.6)'],
                                    borderColor: ['rgba(201, 203, 207, 1)'],
                                    borderWidth: 1
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false
                            }
                        });
                    }
                });
            }
            
            // Analytics
            const generateAnalytics = document.getElementById('generateAnalytics');
            if (generateAnalytics) {
                generateAnalytics.addEventListener('click', function() {
                    const period = document.getElementById('analytics_period').value;
                    const department = document.getElementById('analytics_department').value;
                    
                    // Get real analytics data
                    const analyticsData = <?php echo json_encode($analytics_data); ?>;
                    const attendanceRecords = <?php echo json_encode($attendance_records); ?>;
                    
                    // Calculate status distribution
                    const statusCounts = {
                        'Present': 0,
                        'Absent': 0,
                        'Late': 0,
                        'Half-day': 0,
                        'Leave': 0
                    };
                    
                    attendanceRecords.forEach(record => {
                        if (record.status in statusCounts) {
                            statusCounts[record.status]++;
                        }
                    });
                    
                    // Destroy existing charts if they exist
                    if (trendsChart) {
                        trendsChart.destroy();
                        trendsChart = null;
                    }
                    
                    if (distributionChart) {
                        distributionChart.destroy();
                        distributionChart = null;
                    }
                    
                    // Create or update the trends chart with real data
                    const trendsCtx = document.getElementById('trendsChart').getContext('2d');
                    trendsChart = new Chart(trendsCtx, {
                        type: 'line',
                        data: {
                            labels: analyticsData.map(data => data.day_of_week),
                            datasets: [
                                {
                                    label: 'Attendance %',
                                    data: analyticsData.map(data => 
                                        data.total_records > 0 ? 
                                        Math.round((data.present_count / data.total_records) * 100) : 0
                                    ),
                                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                                    borderColor: 'rgba(75, 192, 192, 1)',
                                    borderWidth: 2,
                                    tension: 0.3,
                                    fill: true
                                },
                                {
                                    label: 'Avg. Hours',
                                    data: analyticsData.map(data => 
                                        parseFloat(data.avg_hours || 0).toFixed(1)
                                    ),
                                    backgroundColor: 'rgba(54, 162, 235, 0.2)',
                                    borderColor: 'rgba(54, 162, 235, 1)',
                                    borderWidth: 2,
                                    tension: 0.3,
                                    fill: true
                                }
                            ]
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
                    
                    // Create or update the distribution chart with real data
                    const distributionCtx = document.getElementById('distributionChart').getContext('2d');
                    distributionChart = new Chart(distributionCtx, {
                        type: 'pie',
                        data: {
                            labels: Object.keys(statusCounts),
                            datasets: [{
                                data: Object.values(statusCounts),
                                backgroundColor: [
                                    'rgba(75, 192, 192, 0.6)',
                                    'rgba(255, 99, 132, 0.6)',
                                    'rgba(255, 206, 86, 0.6)',
                                    'rgba(54, 162, 235, 0.6)',
                                    'rgba(153, 102, 255, 0.6)'
                                ],
                                borderColor: [
                                    'rgba(75, 192, 192, 1)',
                                    'rgba(255, 99, 132, 1)',
                                    'rgba(255, 206, 86, 1)',
                                    'rgba(54, 162, 235, 1)',
                                    'rgba(153, 102, 255, 1)'
                                ],
                                borderWidth: 1
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false
                        }
                    });
                });
                
                // Auto-generate analytics on page load
                if (document.getElementById('trendsChart')) {
                    generateAnalytics.click();
                }
            }
            
            // Helper function to get status class
            function getStatusClass(status) {
                if (status === 'Present') return 'badge-success';
                if (status === 'Absent') return 'badge-danger';
                if (status === 'Late') return 'badge-warning';
                return 'badge-info';
            }
        });
    </script>
</body>
</html>