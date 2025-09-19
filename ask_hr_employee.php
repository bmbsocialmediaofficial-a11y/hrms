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
// Get current employee ID (assuming it's stored in session)
$current_employee_id = $_SESSION['user_id'];
// Fetch current employee details
$employee = array();
$emp_sql = "SELECT id, name, email, department FROM employees WHERE id = '$current_employee_id' AND valid_user = TRUE";
$emp_result = $conn->query($emp_sql);
if ($emp_result->num_rows > 0) {
    $employee = $emp_result->fetch_assoc();
} else {
    die("Employee not found or invalid user.");
}
// Handle form submissions
$message = "";
$message_type = "";
// Handle new HR ticket submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_ticket'])) {
    $title = $conn->real_escape_string($_POST['title']);
    $category = $conn->real_escape_string($_POST['category']);
    $description = $conn->real_escape_string($_POST['description']);
    $priority = $conn->real_escape_string($_POST['priority']);
    
    // Insert into hr_tickets table
    $sql = "INSERT INTO hr_tickets (employee_id, title, category, description, priority, status) 
            VALUES ('$current_employee_id', '$title', '$category', '$description', '$priority', 'Open')";
    
    if ($conn->query($sql)) {
        $message = "HR ticket submitted successfully!";
        $message_type = "success";
    } else {
        $message = "Error: " . $conn->error;
        $message_type = "error";
    }
}
// Handle filter submission
$filter_category = isset($_GET['filter_category']) ? $_GET['filter_category'] : '';
$filter_status = isset($_GET['filter_status']) ? $_GET['filter_status'] : '';
$filter_priority = isset($_GET['filter_priority']) ? $_GET['filter_priority'] : '';
// Build filter conditions
$filter_conditions = ["t.employee_id = '$current_employee_id'"];
if (!empty($filter_category)) {
    $filter_conditions[] = "t.category = '$filter_category'";
}
if (!empty($filter_status)) {
    $filter_conditions[] = "t.status = '$filter_status'";
}
if (!empty($filter_priority)) {
    $filter_conditions[] = "t.priority = '$filter_priority'";
}
$filter_sql = "";
if (!empty($filter_conditions)) {
    $filter_sql = "WHERE " . implode(" AND ", $filter_conditions);
}
// Fetch HR tickets for the current employee with filters
$tickets = array();
$tickets_sql = "SELECT t.id, t.title, t.category, t.description, t.priority, t.status, t.resolution, 
                t.created_at, t.updated_at, t.resolved_at, t.assigned_to,
                e.name as employee_name, e.department as employee_department,
                a.name as assigned_to_name
                FROM hr_tickets t 
                JOIN employees e ON t.employee_id = e.id 
                LEFT JOIN employees a ON t.assigned_to = a.id 
                $filter_sql
                ORDER BY t.created_at DESC LIMIT 100";
$tickets_result = $conn->query($tickets_sql);
if ($tickets_result->num_rows > 0) {
    while ($row = $tickets_result->fetch_assoc()) {
        $tickets[] = $row;
    }
}
// Get ticket statistics for the current employee
$stats_sql = "SELECT 
                COUNT(*) as total_tickets,
                SUM(CASE WHEN status = 'Open' THEN 1 ELSE 0 END) as open_tickets,
                SUM(CASE WHEN status = 'In Progress' THEN 1 ELSE 0 END) as inprogress_tickets,
                SUM(CASE WHEN status = 'Resolved' THEN 1 ELSE 0 END) as resolved_tickets,
                SUM(CASE WHEN status = 'Closed' THEN 1 ELSE 0 END) as closed_tickets
              FROM hr_tickets WHERE employee_id = '$current_employee_id'";
$stats_result = $conn->query($stats_sql);
$stats = $stats_result->fetch_assoc();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ASK HR - Buymeabook</title>
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
            align-items: flex-start;
            min-height: 100vh;
            padding: 100px 20px 40px;
        }
        
        .content-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(12px);
            border-radius: 20px;
            padding: 30px;
            width: 90%;
            max-width: 1200px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.2);
            position: relative;
            overflow: hidden;
        }
        
        .content-card::before {
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
            font-size: 2.5rem;
            margin-bottom: 20px;
            text-shadow: 2px 2px 8px rgba(0, 0, 0, 0.3);
            font-weight: 700;
            background: linear-gradient(to right, #fff, #e0e0ff);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            letter-spacing: 1px;
            text-align: center;
        }
        
        .subtitle {
            font-size: 1.2rem;
            margin-bottom: 30px;
            opacity: 0.9;
            font-weight: 300;
            letter-spacing: 0.5px;
            text-align: center;
        }
        
        .section-title {
            font-size: 1.5rem;
            margin: 30px 0 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }
        
        input, select, textarea {
            width: 100%;
            padding: 12px 15px;
            border-radius: 8px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            background: rgba(255, 255, 255, 0.1);
            color: white;
            font-size: 1rem;
        }
        
        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: #6a11cb;
            background: rgba(255, 255, 255, 0.15);
        }
        
        textarea {
            min-height: 100px;
            resize: vertical;
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
        
        .btn-primary {
            background: rgba(106, 17, 203, 0.9);
            color: white;
        }
        
        .btn-primary:hover {
            background: rgba(90, 15, 184, 0.95);
        }
        
        .btn-secondary {
            background: rgba(37, 117, 252, 0.9);
            color: white;
        }
        
        .btn-secondary:hover {
            background: rgba(30, 100, 225, 0.95);
        }
        
        .btn-back {
            background: rgba(255, 255, 255, 0.15);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        
        .btn-back:hover {
            background: rgba(255, 255, 255, 0.25);
        }
        
        .message {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .success {
            background: rgba(46, 204, 113, 0.2);
            border: 1px solid rgba(46, 204, 113, 0.5);
        }
        
        .error {
            background: rgba(231, 76, 60, 0.2);
            border: 1px solid rgba(231, 76, 60, 0.5);
        }
        
        .ticket-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            overflow: hidden;
        }
        
        .ticket-table th, .ticket-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .ticket-table th {
            background: rgba(106, 17, 203, 0.3);
            font-weight: 600;
        }
        
        .ticket-table tr:hover {
            background: rgba(255, 255, 255, 0.05);
        }
        
        .badge {
            padding: 4px 8px;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .badge-success {
            background: rgba(46, 204, 113, 0.3);
            color: #2ecc71;
        }
        
        .badge-warning {
            background: rgba(243, 156, 18, 0.3);
            color: #f39c12;
        }
        
        .badge-danger {
            background: rgba(231, 76, 60, 0.3);
            color: #e74c3c;
        }
        
        .badge-info {
            background: rgba(52, 152, 219, 0.3);
            
        }
        
        .badge-primary {
            background: rgba(106, 17, 203, 0.3);
            
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: transform 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: rgba(106, 17, 203, 0.3);
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
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 0.9rem;
            opacity: 0.8;
        }
        
        .filter-section {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 30px;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .filter-form {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
            align-items: end;
        }
        
        .filter-buttons {
            display: flex;
            gap: 10px;
            grid-column: span 2;
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
        
        /* Navigation Pane Styles */
        .nav-pane {
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
            
            .content-card {
                padding: 25px 20px;
            }
            
            .btn {
                width: 100%;
                margin-bottom: 10px;
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
                padding-top: 80px;
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
            
            .ticket-table {
                display: block;
                overflow-x: auto;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .filter-form {
                grid-template-columns: 1fr;
            }
            
            .filter-buttons {
                grid-column: span 1;
            }
        }
        
        @media (max-width: 480px) {
            h1 {
                font-size: 1.8rem;
            }
            
            .section-title {
                font-size: 1.3rem;
            }
            
            .btn {
                padding: 12px 15px;
                min-width: 140px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
        
        select option {
            background: #2a2a4a;
            color: white;
        }
        
        select:-moz-focusring {
            color: transparent;
            text-shadow: 0 0 0 white;
        }
        
        select::-ms-expand {
            display: none;
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
                <a href="ask_hr_employee.php" class="nav-link active">
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
        <div class="content-card">
            <div class="decoration decoration-1"></div>
            <div class="decoration decoration-2"></div>
            
            <h1>ASK HR</h1>
            <p class="subtitle">Submit and track your HR queries</p>
            
            <?php if (!empty($message)): ?>
                <div class="message <?php echo $message_type; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-ticket-alt"></i>
                    </div>
                    <div class="stat-value"><?php echo $stats['total_tickets']; ?></div>
                    <div class="stat-label">Total Tickets</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-value"><?php echo $stats['open_tickets']; ?></div>
                    <div class="stat-label">Open Tickets</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-spinner"></i>
                    </div>
                    <div class="stat-value"><?php echo $stats['inprogress_tickets']; ?></div>
                    <div class="stat-label">In Progress</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-value"><?php echo $stats['resolved_tickets'] + $stats['closed_tickets']; ?></div>
                    <div class="stat-label">Resolved/Closed</div>
                </div>
            </div>
            
            <h2 class="section-title">Submit New HR Ticket</h2>
            <form method="POST" id="ticket-form">
                <div class="form-group">
                    <label for="title">Ticket Title *</label>
                    <input type="text" id="title" name="title" required>
                </div>
                
                <div class="form-group">
                    <label for="category">Category *</label>
                    <select id="category" name="category" required>
                        <option value="">Select Category</option>
                        <option value="Payroll">Payroll</option>
                        <option value="Benefits">Benefits</option>
                        <option value="Leave">Leave Policy</option>
                        <option value="Attendance">Attendance</option>
                        <option value="Policy">Company Policy</option>
                        <option value="Technical">Technical Issue</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="priority">Priority *</label>
                    <select id="priority" name="priority" required>
                        <option value="Low">Low</option>
                        <option value="Medium" selected>Medium</option>
                        <option value="High">High</option>
                        <option value="Critical">Critical</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="description">Description *</label>
                    <textarea id="description" name="description" rows="5" required placeholder="Please provide detailed information about your query or issue..."></textarea>
                </div>
                
                <button type="submit" name="submit_ticket" class="btn btn-primary">
                    <i class="fas fa-paper-plane"></i> Submit Ticket
                </button>
            </form>
            
            <h2 class="section-title">Filter Tickets</h2>
            <div class="filter-section">
                <form method="GET" class="filter-form">
                    <div class="form-group">
                        <label for="filter_category">Category</label>
                        <select id="filter_category" name="filter_category">
                            <option value="">All Categories</option>
                            <option value="Payroll" <?php echo $filter_category == 'Payroll' ? 'selected' : ''; ?>>Payroll</option>
                            <option value="Benefits" <?php echo $filter_category == 'Benefits' ? 'selected' : ''; ?>>Benefits</option>
                            <option value="Leave" <?php echo $filter_category == 'Leave' ? 'selected' : ''; ?>>Leave Policy</option>
                            <option value="Attendance" <?php echo $filter_category == 'Attendance' ? 'selected' : ''; ?>>Attendance</option>
                            <option value="Policy" <?php echo $filter_category == 'Policy' ? 'selected' : ''; ?>>Company Policy</option>
                            <option value="Technical" <?php echo $filter_category == 'Technical' ? 'selected' : ''; ?>>Technical Issue</option>
                            <option value="Other" <?php echo $filter_category == 'Other' ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="filter_status">Status</label>
                        <select id="filter_status" name="filter_status">
                            <option value="">All Statuses</option>
                            <option value="Open" <?php echo $filter_status == 'Open' ? 'selected' : ''; ?>>Open</option>
                            <option value="In Progress" <?php echo $filter_status == 'In Progress' ? 'selected' : ''; ?>>In Progress</option>
                            <option value="Resolved" <?php echo $filter_status == 'Resolved' ? 'selected' : ''; ?>>Resolved</option>
                            <option value="Closed" <?php echo $filter_status == 'Closed' ? 'selected' : ''; ?>>Closed</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="filter_priority">Priority</label>
                        <select id="filter_priority" name="filter_priority">
                            <option value="">All Priorities</option>
                            <option value="Low" <?php echo $filter_priority == 'Low' ? 'selected' : ''; ?>>Low</option>
                            <option value="Medium" <?php echo $filter_priority == 'Medium' ? 'selected' : ''; ?>>Medium</option>
                            <option value="High" <?php echo $filter_priority == 'High' ? 'selected' : ''; ?>>High</option>
                            <option value="Critical" <?php echo $filter_priority == 'Critical' ? 'selected' : ''; ?>>Critical</option>
                        </select>
                    </div>
                    
                    <div class="filter-buttons">
                        <button type="submit" class="btn btn-secondary">
                            <i class="fas fa-filter"></i> Apply Filters
                        </button>
                        <a href="ask_hr_employee.php" class="btn btn-back">
                            <i class="fas fa-times"></i> Clear Filters
                        </a>
                    </div>
                </form>
            </div>
            
            <h2 class="section-title">My HR Tickets</h2>
            
            <?php if (count($tickets) > 0): ?>
                <div style="overflow-x: auto;">
                    <table class="ticket-table">
                        <thead>
                            <tr>
                                <th>Ticket ID</th>
                                <th>Title</th>
                                <th>Category</th>
                                <th>Priority</th>
                                <th>Status</th>
                                <th>Created Date</th>
                                <th>Last Updated</th>
                                <th>Assigned To</th>
                                <th>Resolution</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tickets as $ticket): ?>
                                <tr>
                                    <td>#<?php echo $ticket['id']; ?></td>
                                    <td><?php echo htmlspecialchars($ticket['title']); ?></td>
                                    <td><?php echo htmlspecialchars($ticket['category']); ?></td>
                                    <td>
                                        <span class="badge 
                                            <?php 
                                            if ($ticket['priority'] == 'Critical') echo 'badge-danger';
                                            elseif ($ticket['priority'] == 'High') echo 'badge-warning';
                                            elseif ($ticket['priority'] == 'Medium') echo 'badge-primary';
                                            else echo 'badge-info';
                                            ?>">
                                            <?php echo $ticket['priority']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge 
                                            <?php 
                                            if ($ticket['status'] == 'Open') echo 'badge-info';
                                            elseif ($ticket['status'] == 'In Progress') echo 'badge-primary';
                                            elseif ($ticket['status'] == 'Resolved') echo 'badge-success';
                                            else echo 'badge-secondary';
                                            ?>">
                                            <?php echo $ticket['status']; ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M j, Y', strtotime($ticket['created_at'])); ?></td>
                                    <td><?php echo date('M j, Y', strtotime($ticket['updated_at'])); ?></td>
                                    <td><?php echo !empty($ticket['assigned_to_name']) ? htmlspecialchars($ticket['assigned_to_name']) : 'Not Assigned'; ?></td>
                                    <td><?php echo !empty($ticket['resolution']) ? htmlspecialchars(substr($ticket['resolution'], 0, 50)) . (strlen($ticket['resolution']) > 50 ? '...' : '') : '-'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p>No HR tickets found.</p>
            <?php endif; ?>
            
            <div style="margin-top: 30px; text-align: center;">
                <a href="hrms.php" class="btn btn-back">
                    <i class="fas fa-arrow-left"></i> Back to HRMS
                </a>
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
            
            // Form validation for ticket submission
            const ticketForm = document.getElementById('ticket-form');
            if (ticketForm) {
                ticketForm.addEventListener('submit', function(e) {
                    const title = document.getElementById('title').value;
                    const category = document.getElementById('category').value;
                    const priority = document.getElementById('priority').value;
                    const description = document.getElementById('description').value;
                    
                    if (!title || !category || !priority || !description) {
                        e.preventDefault();
                        alert('Please fill in all required fields.');
                    }
                });
            }
        });
    </script>
</body>
</html>