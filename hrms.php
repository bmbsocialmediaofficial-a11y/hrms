
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

// For HRMS.php, check if user has HR privileges
$current_page = basename($_SERVER['PHP_SELF']);
if ($current_page === 'hrms.php') {
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

// Handle document upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_document'])) {
    $employee_id = $_POST['employee_id'];
    $document_name = $_POST['document_name'];
    $document_type = $_POST['document_type'];
    $issue_date = $_POST['issue_date'];
    $expiry_date = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null;
    
    // File upload handling
    $file_name = null;
    $file_data = null;
    
    if (!empty($_FILES['document_file']['name'])) {
        // Check file size (max 10MB)
        if ($_FILES['document_file']['size'] > 10000000) {
            $message = "Sorry, your file is too large. Maximum size is 10MB.";
            $message_type = "error";
        } else {
            $file_name = $conn->real_escape_string($_FILES['document_file']['name']);
            $file_data = $conn->real_escape_string(file_get_contents($_FILES['document_file']['tmp_name']));
        }
    }
    
    // If no errors, save to database
    if (empty($message)) {
        $uploaded_by = $_SESSION['user_id'];
        
        $sql = "INSERT INTO employee_documents (employee_id, document_name, document_type, issue_date, expiry_date, file_name, file_data, uploaded_by) 
                VALUES ('$employee_id', '$document_name', '$document_type', '$issue_date', " . 
                ($expiry_date ? "'$expiry_date'" : "NULL") . ", " .
                ($file_name ? "'$file_name'" : "NULL") . ", " .
                ($file_data ? "'$file_data'" : "NULL") . ", '$uploaded_by')";
        
        if ($conn->query($sql)) {
            $message = "Document added successfully!";
            $message_type = "success";
        } else {
            $message = "Error: " . $conn->error;
            $message_type = "error";
        }
    }
}

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

// Fetch leave requests from the correct table
$leave_requests = array();
$leave_sql = "SELECT lr.*, e.name as employee_name 
              FROM leave_requests lr 
              JOIN employees e ON lr.employee_id = e.id 
              ORDER BY lr.created_at DESC LIMIT 20";
$leave_result = $conn->query($leave_sql);

if ($leave_result->num_rows > 0) {
    while ($row = $leave_result->fetch_assoc()) {
        $leave_requests[] = $row;
    }
}

// Handle performance review submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_performance'])) {
    $employee_id = $_POST['employee_id'];
    $review_date = $_POST['review_date'];
    $reviewer_id = $_POST['reviewer_id'];
    $performance_rating = !empty($_POST['performance_rating']) ? $_POST['performance_rating'] : null;
    $goals_achievement = !empty($_POST['goals_achievement']) ? $conn->real_escape_string($_POST['goals_achievement']) : null;
    $strengths = !empty($_POST['strengths']) ? $conn->real_escape_string($_POST['strengths']) : null;
    $areas_for_improvement = !empty($_POST['areas_for_improvement']) ? $conn->real_escape_string($_POST['areas_for_improvement']) : null;
    $recommendations = !empty($_POST['recommendations']) ? $conn->real_escape_string($_POST['recommendations']) : null;
    $next_review_date = !empty($_POST['next_review_date']) ? $_POST['next_review_date'] : null;
    
    $sql = "INSERT INTO performance_reviews (employee_id, review_date, reviewer_id, performance_rating, goals_achievement, strengths, areas_for_improvement, recommendations, next_review_date) 
            VALUES ('$employee_id', '$review_date', '$reviewer_id', " . 
            (is_numeric($performance_rating) ? $performance_rating : "NULL") . ", " .
            ($goals_achievement ? "'$goals_achievement'" : "NULL") . ", " .
            ($strengths ? "'$strengths'" : "NULL") . ", " .
            ($areas_for_improvement ? "'$areas_for_improvement'" : "NULL") . ", " .
            ($recommendations ? "'$recommendations'" : "NULL") . ", " .
            ($next_review_date ? "'$next_review_date'" : "NULL") . ")";

    if ($conn->query($sql)) {
        $message = "Performance review added successfully!";
        $message_type = "success";
    } else {
        $message = "Error: " . $conn->error;
        $message_type = "error";
    }
}

// Fetch performance reviews
$performance_reviews = array();
$perf_sql = "SELECT pr.*, e.name as employee_name, r.name as reviewer_name 
             FROM performance_reviews pr 
             JOIN employees e ON pr.employee_id = e.id 
             JOIN employees r ON pr.reviewer_id = r.id 
             ORDER BY pr.review_date DESC LIMIT 20";
$perf_result = $conn->query($perf_sql);

if ($perf_result->num_rows > 0) {
    while ($row = $perf_result->fetch_assoc()) {
        $performance_reviews[] = $row;
    }
}

// Handle attendance record
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

// Fetch data for display
$attendance_records = array();
$att_sql = "SELECT a.*, e.name as employee_name FROM employee_attendance a 
            JOIN employees e ON a.employee_id = e.id 
            ORDER BY a.date DESC, a.employee_id LIMIT 50";
$att_result = $conn->query($att_sql);

if ($att_result->num_rows > 0) {
    while ($row = $att_result->fetch_assoc()) {
        $attendance_records[] = $row;
    }
}

$documents = array();
$doc_sql = "SELECT d.*, e.name as employee_name, u.name as uploaded_by_name 
            FROM employee_documents d 
            JOIN employees e ON d.employee_id = e.id 
            JOIN employees u ON d.uploaded_by = u.id 
            ORDER BY d.uploaded_at DESC LIMIT 20";
$doc_result = $conn->query($doc_sql);

if ($doc_result->num_rows > 0) {
    while ($row = $doc_result->fetch_assoc()) {
        $documents[] = $row;
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HR Management System - Buymeabook</title>
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
                <a href="hrms.php" class="nav-link active"><i class="fas fa-users-cog"></i> HR Management</a>
                <a href="ftp_manager_operation_main.php" class="nav-link"><i class="fas fa-folder"></i> Manage Files</a>
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
                    <li><a href="#" class="active"><i class="fas fa-tachometer-alt"></i> Overview</a></li>
                    <li><a href="attendance.php"><i class="fas fa-user-clock"></i> Attendance</a></li>
                    <li><a href="document.php"><i class="fas fa-file-alt"></i> Documents</a></li>
                    <li><a href="leave_management.php"><i class="fas fa-calendar-day"></i> Leave Management</a></li>
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
                <h2 class="section-title"><i class="fas fa-users-cog"></i> HR Management System</h2>
                
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
                        <div class="stat-value"><?php echo count($attendance_records) > 0 ? count($attendance_records) : 0; ?></div>
                        <div class="stat-label">Attendance Records</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-file-contract"></i>
                        </div>
                        <div class="stat-value"><?php echo count($documents); ?></div>
                        <div class="stat-label">Documents</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <div class="stat-value">5</div>
                        <div class="stat-label">Pending Approvals</div>
                    </div>
                </div>
                
                <div class="tabs">
                    <div class="tab active" data-tab="attendance">Attendance</div>
                    <div class="tab" data-tab="documents">Documents</div>
                    <div class="tab" data-tab="leaves">Leave Management</div>
                    <div class="tab" data-tab="performance">Performance</div>
                </div>
                
                <div class="tab-content active" id="attendance-tab">
                    <div class="form-section">
                        <h3 class="form-title"><i class="fas fa-user-clock"></i> Record Attendance</h3>
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
    <div style="overflow-x: auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Employee</th>
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
                    <td colspan="7" style="text-align: center;">No attendance records found.</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
            </div>
			
			<div class="tab-content" id="documents-tab">
    <div class="form-section">
        <h3 class="form-title"><i class="fas fa-file-upload"></i> Upload Document</h3>
        <form method="POST" enctype="multipart/form-data" id="document-form">
            <div class="form-grid">
                <div class="form-group">
                    <label for="doc_employee_id">Employee</label>
                    <select id="doc_employee_id" name="employee_id" class="form-control" required>
                        <option value="">Select Employee</option>
                        <?php foreach ($employees as $employee): ?>
                            <option value="<?php echo $employee['id']; ?>">
                                <?php echo htmlspecialchars($employee['name'] . ' (' . $employee['department'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="document_name">Document Name</label>
                    <input type="text" id="document_name" name="document_name" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="document_type">Document Type</label>
                    <select id="document_type" name="document_type" class="form-control" required>
                        <option value="">Select Type</option>
                        <option value="Contract">Employment Contract</option>
                        <option value="ID Proof">ID Proof</option>
                        <option value="Certificate">Certificate</option>
                        <option value="Resume">Resume</option>
                        <option value="Offer Letter">Offer Letter</option>
                        <option value="Appraisal">Appraisal</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="issue_date">Issue Date</label>
                    <input type="date" id="issue_date" name="issue_date" class="form-control">
                </div>
                
                <div class="form-group">
                    <label for="expiry_date">Expiry Date (if applicable)</label>
                    <input type="date" id="expiry_date" name="expiry_date" class="form-control">
                </div>
                
                <div class="form-group">
                    <label for="document_file">Upload File (Max 10MB)</label>
                    <input type="file" id="document_file" name="document_file" class="form-control">
                </div>
            </div>
            
            <button type="submit" name="add_document" class="btn"><i class="fas fa-upload"></i> Upload Document</button>
        </form>
    </div>
    
    <div class="data-section">
        <h3 class="form-title"><i class="fas fa-file-alt"></i> Recent Document Uploads</h3>
        <div style="overflow-x: auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Document Name</th>
                        <th>Employee</th>
                        <th>Type</th>
                        <th>Issue Date</th>
                        <th>Expiry Date</th>
                        <th>Uploaded By</th>
                        <th>Uploaded At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (count($documents) > 0): ?>
                    <?php foreach ($documents as $document): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($document['document_name']); ?></td>
                            <td><?php echo htmlspecialchars($document['employee_name']); ?></td>
                            <td><?php echo htmlspecialchars($document['document_type']); ?></td>
                            <td><?php echo !empty($document['issue_date']) ? date('M j, Y', strtotime($document['issue_date'])) : '-'; ?></td>
                            <td><?php echo !empty($document['expiry_date']) ? date('M j, Y', strtotime($document['expiry_date'])) : '-'; ?></td>
                            <td><?php echo htmlspecialchars($document['uploaded_by_name']); ?></td>
                            <td><?php echo date('M j, Y H:i', strtotime($document['uploaded_at'])); ?></td>
                            <td>
                                <?php if (!empty($document['file_name'])): ?>
                                    <a href="download_document.php?id=<?php echo $document['id']; ?>" class="btn btn-secondary" style="padding: 5px 10px; font-size: 14px;">
                                        <i class="fas fa-download"></i> Download
                                    </a>
                                <?php else: ?>
                                    <span>No file</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" style="text-align: center;">No documents found.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
            
                        <div class="tab-content" id="leaves-tab">
                <div class="form-section">
                    <h3 class="form-title"><i class="fas fa-calendar-plus"></i> Submit Leave Request</h3>
                    <form method="POST" id="leave-form">
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="leave_employee_id">Employee</label>
                                <select id="leave_employee_id" name="employee_id" class="form-control" required>
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
                                    <option value="Sick Leave">Sick Leave</option>
                                    <option value="Vacation">Vacation</option>
                                    <option value="Personal">Personal</option>
                                    <option value="Maternity">Maternity</option>
                                    <option value="Paternity">Paternity</option>
                                    <option value="Bereavement">Bereavement</option>
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
                            
                            <div class="form-group">
                                <label for="reason">Reason</label>
                                <textarea id="reason" name="reason" class="form-control" rows="2" required></textarea>
                            </div>
                        </div>
                        
                        <button type="submit" name="add_leave" class="btn"><i class="fas fa-paper-plane"></i> Submit Leave Request</button>
                    </form>
                </div>
                
                <div class="data-section">
                    <h3 class="form-title"><i class="fas fa-calendar-check"></i> Recent Leave Requests</h3>
                    <div style="overflow-x: auto;">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Employee</th>
                                    <th>Request Date</th>
                                    <th>Leave Type</th>
                                    <th>Start Date</th>
                                    <th>End Date</th>
                                    <th>Days</th>
                                    <th>Reason</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                               <tbody>
    <?php if (count($leave_requests) > 0): ?>
        <?php foreach ($leave_requests as $request): ?>
            <tr>
                <td><?php echo htmlspecialchars($request['employee_name']); ?></td>
                <td><?php echo date('M j, Y', strtotime($request['created_at'])); ?></td>
                <td><?php echo htmlspecialchars($request['leave_type']); ?></td>
                <td><?php echo date('M j, Y', strtotime($request['start_date'])); ?></td>
                <td><?php echo date('M j, Y', strtotime($request['end_date'])); ?></td>
                <td><?php echo $request['days_requested']; ?></td>
                <td><?php echo htmlspecialchars($request['reason']); ?></td>
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
                <td>
                    <?php if ($request['status'] == 'Pending'): ?>
                        <div style="display: flex; gap: 5px;">
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                <button type="submit" name="approve_leave" class="btn btn-secondary" style="padding: 5px 10px; font-size: 14px;">
                                    <i class="fas fa-check"></i> Approve
                                </button>
                            </form>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                <button type="submit" name="reject_leave" class="btn btn-secondary" style="padding: 5px 10px; font-size: 14px;">
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
            <td colspan="9" style="text-align: center;">No leave requests found.</td>
        </tr>
    <?php endif; ?>
</tbody>
                        </table>
                    </div>
                </div>
            </div>
            
<div class="tab-content" id="performance-tab">
    <div class="form-section">
        <h3 class="form-title"><i class="fas fa-chart-line"></i> Record Performance Review</h3>
        <form method="POST" id="performance-form">
            <div class="form-grid">
                <div class="form-group">
                    <label for="perf_employee_id">Employee</label>
                    <select id="perf_employee_id" name="employee_id" class="form-control" required>
                        <option value="">Select Employee</option>
                        <?php foreach ($employees as $employee): ?>
                            <option value="<?php echo $employee['id']; ?>">
                                <?php echo htmlspecialchars($employee['name'] . ' (' . $employee['department'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="review_date">Review Date</label>
                    <input type="date" id="review_date" name="review_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="reviewer_id">Reviewer</label>
                    <select id="reviewer_id" name="reviewer_id" class="form-control" required>
                        <option value="">Select Reviewer</option>
                        <?php foreach ($employees as $employee): ?>
                            <option value="<?php echo $employee['id']; ?>">
                                <?php echo htmlspecialchars($employee['name'] . ' (' . $employee['department'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="performance_rating">Performance Rating (1-10)</label>
                    <input type="number" id="performance_rating" name="performance_rating" class="form-control" min="1" max="10">
                </div>
                
                <div class="form-group">
                    <label for="next_review_date">Next Review Date</label>
                    <input type="date" id="next_review_date" name="next_review_date" class="form-control">
                </div>
                
                <div class="form-group" style="grid-column: 1 / -1;">
                    <label for="goals_achievement">Goals Achievement</label>
                    <textarea id="goals_achievement" name="goals_achievement" class="form-control" rows="3"></textarea>
                </div>
                
                <div class="form-group" style="grid-column: 1 / -1;">
                    <label for="strengths">Strengths</label>
                    <textarea id="strengths" name="strengths" class="form-control" rows="3"></textarea>
                </div>
                
                <div class="form-group" style="grid-column: 1 / -1;">
                    <label for="areas_for_improvement">Areas for Improvement</label>
                    <textarea id="areas_for_improvement" name="areas_for_improvement" class="form-control" rows="3"></textarea>
                </div>
                
                <div class="form-group" style="grid-column: 1 / -1;">
                    <label for="recommendations">Recommendations</label>
                    <textarea id="recommendations" name="recommendations" class="form-control" rows="3"></textarea>
                </div>
            </div>
            
            <button type="submit" name="add_performance" class="btn"><i class="fas fa-save"></i> Save Performance Review</button>
        </form>
    </div>
    
    <div class="data-section">
        <h3 class="form-title"><i class="fas fa-history"></i> Recent Performance Reviews</h3>
        <div style="overflow-x: auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Review Date</th>
                        <th>Reviewer</th>
                        <th>Rating</th>
                        <th>Next Review</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (count($performance_reviews) > 0): ?>
                    <?php foreach ($performance_reviews as $review): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($review['employee_name']); ?></td>
                            <td><?php echo date('M j, Y', strtotime($review['review_date'])); ?></td>
                            <td><?php echo htmlspecialchars($review['reviewer_name']); ?></td>
                            <td>
                                <?php if (!empty($review['performance_rating'])): ?>
                                    <span class="badge 
                                        <?php 
                                        if ($review['performance_rating'] >= 8) echo 'badge-success';
                                        elseif ($review['performance_rating'] >= 5) echo 'badge-warning';
                                        else echo 'badge-danger';
                                        ?>">
                                        <?php echo $review['performance_rating']; ?>/10
                                    </span>
                                <?php else: ?>
                                    <span>Not rated</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo !empty($review['next_review_date']) ? date('M j, Y', strtotime($review['next_review_date'])) : '-'; ?></td>
                            <td>
                                <button class="btn btn-secondary view-performance" data-id="<?php echo $review['id']; ?>" style="padding: 5px 10px; font-size: 14px;">
                                    <i class="fas fa-eye"></i> View
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" style="text-align: center;">No performance reviews found.</td>
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
            });
        });
        
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
        
        // Form validation for documents
        const documentForm = document.getElementById('document-form');
        if (documentForm) {
            documentForm.addEventListener('submit', function(e) {
                const employeeId = document.getElementById('doc_employee_id').value;
                const documentName = document.getElementById('document_name').value;
                
                if (!employeeId || !documentName) {
                    e.preventDefault();
                    alert('Please select an employee and enter a document name.');
                }
            });
        }
    });
	
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

// Performance review modal functionality
const viewButtons = document.querySelectorAll('.view-performance');
viewButtons.forEach(button => {
    button.addEventListener('click', function() {
        const reviewId = this.getAttribute('data-id');
        // In a real implementation, you would fetch the review details via AJAX
        alert('Performance review details for ID: ' + reviewId + '\n\nIn a complete implementation, this would show a modal with full review details.');
    });
});

// Form validation for performance review
const performanceForm = document.getElementById('performance-form');
if (performanceForm) {
    performanceForm.addEventListener('submit', function(e) {
        const employeeId = document.getElementById('perf_employee_id').value;
        const reviewDate = document.getElementById('review_date').value;
        const reviewerId = document.getElementById('reviewer_id').value;
        
        if (!employeeId || !reviewDate || !reviewerId) {
            e.preventDefault();
            alert('Please select an employee, review date, and reviewer.');
        }
    });
}
</script>
</body> </html>