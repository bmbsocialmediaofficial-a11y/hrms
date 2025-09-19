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

// Get employee details
$employee_id = $_SESSION['user_id'];
$query = "SELECT * FROM employees WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $employee_id);
$stmt->execute();
$result = $stmt->get_result();
$employee = $result->fetch_assoc();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_request'])) {
    $issue_type = $_POST['issue_type'];
    $sub_issue_type = $_POST['sub_issue_type'];
    $subject = $_POST['subject'];
    $description = $_POST['description'];
    $priority = $_POST['priority'];
    
    // Insert technical request
    $insert_query = "INSERT INTO technical_requests (employee_id, issue_type, sub_issue_type, subject, description, priority) 
                     VALUES (?, ?, ?, ?, ?, ?)";
    $insert_stmt = $conn->prepare($insert_query);
    $insert_stmt->bind_param("issssi", $employee_id, $issue_type, $sub_issue_type, $subject, $description, $priority);
    
    if ($insert_stmt->execute()) {
        $success_message = "Technical support request submitted successfully!";
    } else {
        $error_message = "Error submitting technical support request. Please try again.";
    }
}

// Get technical requests for this employee
$requests_query = "SELECT * FROM technical_requests WHERE employee_id = ? ORDER BY created_at DESC";
$requests_stmt = $conn->prepare($requests_query);
$requests_stmt->bind_param("i", $employee_id);
$requests_stmt->execute();
$requests_result = $requests_stmt->get_result();

// Define sub-issue types based on main issue type
$sub_issue_types = [
    'Hardware' => [
        'Laptop Not Powering On',
        'Desktop Not Powering On',
        'Monitor Issues',
        'Keyboard/Mouse Issues',
        'Printer Issues',
        'Overheating Problems',
        'Battery Issues',
        'Other Hardware Problem'
    ],
    'Software' => [
        'Operating System Issues',
        'Application Installation',
        'Application Crashes',
        'Software Updates',
        'License Issues',
        'Compatibility Problems',
        'Performance Issues',
        'Other Software Problem'
    ],
    'Network' => [
        'No Internet Connection',
        'Slow Internet',
        'VPN Connection Issues',
        'Wi-Fi Connection Problems',
        'Network Printer Issues',
        'Email Configuration',
        'File Sharing Issues',
        'Other Network Problem'
    ],
    'Login/Account' => [
        'Cannot Login to System',
        'Password Reset Required',
        'Account Locked',
        'Multi-Factor Authentication Issues',
        'Profile Configuration',
        'Permission Issues',
        'Account Creation',
        'Other Login/Account Problem'
    ],
    'Peripheral' => [
        'USB Device Not Recognized',
        'External Storage Issues',
        'Scanner Issues',
        'Projector Issues',
        'Audio/Speaker Problems',
        'Webcam Issues',
        'Docking Station Problems',
        'Other Peripheral Problem'
    ],
    'Security' => [
        'Virus/Malware Detection',
        'Suspicious Activity',
        'Data Loss',
        'Encryption Issues',
        'Firewall Problems',
        'Security Software Issues',
        'Data Recovery',
        'Other Security Problem'
    ]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Technical Support Requests - Buymeabook</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #1a2a6c 0%, #b21f1f 50%, #1a2a6c 100%);
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
            border-color: #1a2a6c;
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
            color: #1a2a6c;
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
            background: rgba(26, 42, 108, 0.9);
            color: white;
        }
        
        .btn-primary:hover {
            background: rgba(20, 30, 80, 0.95);
        }
        
        .btn-secondary {
            background: rgba(178, 31, 31, 0.9);
            color: white;
        }
        
        .btn-secondary:hover {
            background: rgba(150, 25, 25, 0.95);
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
        
        .requests-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            overflow: hidden;
        }
        
        .requests-table th, .requests-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .requests-table th {
            background: rgba(26, 42, 108, 0.3);
            font-weight: 600;
        }
        
        .requests-table tr:hover {
            background: rgba(255, 255, 255, 0.05);
        }
        
        .status-pending {
            color: #f39c12;
            font-weight: 600;
        }
        
        .status-in-progress {
            color: #3498db;
            font-weight: 600;
        }
        
        .status-resolved {
            color: #2ecc71;
            font-weight: 600;
        }
        
        .status-closed {
            color: #9b59b6;
            font-weight: 600;
        }
        
        .priority-low {
            color: #2ecc71;
            font-weight: 600;
        }
        
        .priority-medium {
            color: #f39c12;
            font-weight: 600;
        }
        
        .priority-high {
            color: #e67e22;
            font-weight: 600;
        }
        
        .priority-urgent {
            color: #e74c3c;
            font-weight: 600;
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
            background: radial-gradient(circle, rgba(26, 42, 108, 0.4) 0%, transparent 70%);
        }
        
        .decoration-2 {
            bottom: -40px;
            left: -40px;
            width: 250px;
            height: 250px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(178, 31, 31, 0.3) 0%, transparent 70%);
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
            background: rgba(26, 42, 108, 0.3);
            color: white;
        }
        
        .nav-link.active {
            background: rgba(26, 42, 108, 0.5);
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
            
            .requests-table {
                display: block;
                overflow-x: auto;
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
        }
        
        select {
            width: 100%;
            padding: 12px 15px;
            border-radius: 8px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            background: rgba(255, 255, 255, 0.1);
            color: white;
            font-size: 1rem;
        }
        
        select:focus {
            outline: none;
            border-color: #1a2a6c;
            background: rgba(255, 255, 255, 0.15);
        }
        
        /* Fix for dropdown options */
        select option {
            background: #2a2a4a; /* Dark background for options */
            color: white; /* White text for options */
        }
        
        /* For Firefox */
        select:-moz-focusring {
            color: transparent;
            text-shadow: 0 0 0 white;
        }
        
        /* For Internet Explorer */
        select::-ms-expand {
            display: none;
        }
        
        /* Tech specific styles */
        .issue-type-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .issue-type-card {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            padding: 15px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            border: 2px solid transparent;
        }
        
        .issue-type-card:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-5px);
        }
        
        .issue-type-card.selected {
            border-color: #1a2a6c;
            background: rgba(26, 42, 108, 0.3);
        }
        
        .issue-type-card i {
            font-size: 2rem;
            margin-bottom: 10px;
            display: block;
        }
        
        .priority-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 5px;
        }
        
        .priority-indicator.low {
            background-color: #2ecc71;
        }
        
        .priority-indicator.medium {
            background-color: #f39c12;
        }
        
        .priority-indicator.high {
            background-color: #e67e22;
        }
        
        .priority-indicator.urgent {
            background-color: #e74c3c;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            opacity: 0.7;
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 15px;
            opacity: 0.5;
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
                <a href="ask_hr_employee.php" class="nav-link">
                    <i class="fas fa-headset"></i>
                    <span>ASK HR</span>
                </a>
            </li>

            <li class="nav-item">
                <a href="technical_requests_admin.php" class="nav-link active">
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
            
            <h1>Technical Support Requests</h1>
            <p class="subtitle">Submit and track your technical support requests</p>
            
            <?php if (isset($success_message)): ?>
                <div class="message success"><?php echo $success_message; ?></div>
            <?php endif; ?>
            
            <?php if (isset($error_message)): ?>
                <div class="message error"><?php echo $error_message; ?></div>
            <?php endif; ?>
            
            <h2 class="section-title">New Technical Support Request</h2>
            <form method="POST" action="">
                <div class="form-group">
                    <label for="issue_type">Issue Type</label>
                    <select id="issue_type" name="issue_type" required>
                        <option value="">Select Issue Type</option>
                        <option value="Hardware">Hardware</option>
                        <option value="Software">Software</option>
                        <option value="Network">Network</option>
                        <option value="Login/Account">Login/Account</option>
                        <option value="Peripheral">Peripheral</option>
                        <option value="Security">Security</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="sub_issue_type">Specific Issue</label>
                    <select id="sub_issue_type" name="sub_issue_type" required>
                        <option value="">Select Specific Issue</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="priority">Priority</label>
                    <select id="priority" name="priority" required>
                        <option value="Low">Low</option>
                        <option value="Medium" selected>Medium</option>
                        <option value="High">High</option>
                        <option value="Urgent">Urgent</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="subject">Subject</label>
                    <input type="text" id="subject" name="subject" placeholder="Brief description of the issue" required>
                </div>
                
                <div class="form-group">
                    <label for="description">Detailed Description</label>
                    <textarea id="description" name="description" placeholder="Please provide detailed information about the issue, including any error messages and steps you've already taken to resolve it" required></textarea>
                </div>
                
                <button type="submit" name="submit_request" class="btn btn-primary">
                    <i class="fas fa-paper-plane"></i> Submit Request
                </button>
            </form>
            
            <h2 class="section-title">Your Technical Support History</h2>
            
            <?php if ($requests_result->num_rows > 0): ?>
                <table class="requests-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Issue Type</th>
                            <th>Subject</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th>Submitted On</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($request = $requests_result->fetch_assoc()): ?>
                            <tr>
                                <td>#<?php echo htmlspecialchars($request['id']); ?></td>
                                <td><?php echo htmlspecialchars($request['issue_type']); ?></td>
                                <td><?php echo htmlspecialchars($request['subject']); ?></td>
                                <td>
                                    <?php 
                                    $priority_class = 'priority-' . strtolower($request['priority']);
                                    ?>
                                    <span class="<?php echo $priority_class; ?>">
                                        <span class="priority-indicator <?php echo strtolower($request['priority']); ?>"></span>
                                        <?php echo $request['priority']; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php 
                                    $status_class = '';
                                    switch ($request['status']) {
                                        case 'In Progress':
                                            $status_class = 'status-in-progress';
                                            break;
                                        case 'Resolved':
                                            $status_class = 'status-resolved';
                                            break;
                                        case 'Closed':
                                            $status_class = 'status-closed';
                                            break;
                                        default:
                                            $status_class = 'status-pending';
                                    }
                                    ?>
                                    <span class="<?php echo $status_class; ?>"><?php echo $request['status']; ?></span>
                                </td>
                                <td><?php echo date('M j, Y g:i A', strtotime($request['created_at'])); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-tools"></i>
                    <p>You haven't submitted any technical support requests yet.</p>
                </div>
            <?php endif; ?>
            
            <div style="margin-top: 30px; text-align: center;">
                <a href="start.php" class="btn btn-back">
                    <i class="fas fa-arrow-left"></i> Back to Home
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
            const issueTypeSelect = document.getElementById('issue_type');
            const subIssueTypeSelect = document.getElementById('sub_issue_type');
            
            // Sub-issue types based on main issue type
            const subIssueTypes = <?php echo json_encode($sub_issue_types); ?>;
            
            // Update sub-issue types when main issue type changes
            issueTypeSelect.addEventListener('change', function() {
                const selectedIssueType = this.value;
                subIssueTypeSelect.innerHTML = '<option value="">Select Specific Issue</option>';
                
                if (selectedIssueType && subIssueTypes[selectedIssueType]) {
                    subIssueTypes[selectedIssueType].forEach(function(issue) {
                        const option = document.createElement('option');
                        option.value = issue;
                        option.textContent = issue;
                        subIssueTypeSelect.appendChild(option);
                    });
                }
            });
            
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
        });
    </script>
</body>
</html>
<?php
// Close database connection
if (isset($conn)) {
    $conn->close();
}
?>