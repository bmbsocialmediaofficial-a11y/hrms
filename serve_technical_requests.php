<?php
// Include the configuration file
require_once 'config.php';
// Start session
session_start();
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
// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}
// For save_technical_requests.php, check if user has Admin privileges
$current_page = basename($_SERVER['PHP_SELF']);
if ($current_page === 'serve_technical_requests.php') {
    // Verify if the user has Admin privileges
    $user_id = $_SESSION['user_id'];
    $sql = "SELECT is_admin, admin_id FROM employees WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $employee = $result->fetch_assoc();
        
        // Check if is_admin is set and equals 1 (true)
        if (!isset($employee['is_admin']) || $employee['is_admin'] != 1) {
            // User doesn't have Admin privileges
            $_SESSION['access_error'] = "You need Administrator privileges to access the Admin System";
            header("Location: illegal_access_admin.php"); // Redirect to access denied page
            exit();
        } else {
            // Set Admin session flags if not already set
            $_SESSION['is_admin'] = true;
            $_SESSION['admin_id'] = $employee['admin_id'];
        }
    } else {
        // User not found in database
        $_SESSION['access_error'] = "User not found in system";
        header("Location: illegal_access_admin.php");
        exit();
    }
    
    $stmt->close();
}
// Get user information
$user_id = $_SESSION['user_id'];
$user_email = $_SESSION['user_email'];
$user_name = $_SESSION['user_name'];
// Handle form submissions
$message = "";
$message_type = "";
// Create new request
if (isset($_POST['create_request'])) {
    $issue_type = $conn->real_escape_string($_POST['issue_type']);
    $sub_issue_type = $conn->real_escape_string($_POST['sub_issue_type']);
    $subject = $conn->real_escape_string($_POST['subject']);
    $description = $conn->real_escape_string($_POST['description']);
    $priority = $conn->real_escape_string($_POST['priority']);
    
    $sql = "INSERT INTO technical_requests (employee_id, issue_type, sub_issue_type, subject, description, priority) 
            VALUES ($user_id, '$issue_type', '$sub_issue_type', '$subject', '$description', '$priority')";
    
    if ($conn->query($sql)) {
        $message = "Technical request submitted successfully!";
        $message_type = "success";
    } else {
        $message = "Error: " . $conn->error;
        $message_type = "error";
    }
}
// Get all technical requests for all users
$requests = [];
$result = $conn->query("SELECT tr.*, e.name as employee_name, a.name as assigned_name, r.name as resolved_name 
                       FROM technical_requests tr
                       LEFT JOIN employees e ON tr.employee_id = e.id
                       LEFT JOIN employees a ON tr.assigned_to = a.id
                       LEFT JOIN employees r ON tr.resolved_by = r.id
                       ORDER BY tr.created_at DESC");
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $requests[] = $row;
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Technical Requests Portal - BMB</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: #f5f7fa;
            color: #333;
            line-height: 1.6;
        }
        
        .logo {
            position: absolute;
            top: 20px;
            left: 20px;
            width: 230px;
            z-index: 1000;
        }
        
        .navbar {
            background-color: #2c3e50;
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .navbar h1 {
            font-size: 24px;
            font-weight: 600;
            margin-left: 250px;
        }
        
        .user-info {
            display: flex;
            align-items: center;
        }
        
        .user-info span {
            margin-right: 15px;
        }
        
        .user-info a {
            color: white;
            text-decoration: none;
            padding: 8px 15px;
            background-color: #3498db;
            border-radius: 4px;
            transition: background-color 0.3s;
        }
        
        .user-info a:hover {
            background-color: #2980b9;
        }
        
        .container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        .sidebar {
            width: 250px;
            background-color: #2c3e50;
            color: white;
            padding: 20px 0;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: absolute;
            top: 100px;
            left: 20px;
        }
        
        .sidebar-menu {
            list-style: none;
        }
        
        .sidebar-menu li {
            margin-bottom: 5px;
        }
        
        .sidebar-menu a {
            display: block;
            color: white;
            text-decoration: none;
            padding: 12px 20px;
            transition: background-color 0.3s;
        }
        
        .sidebar-menu a:hover, .sidebar-menu a.active {
            background-color: #3498db;
        }
        
        .sidebar-menu a i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }
        
        .main-content {
            margin-left: 290px;
        }
        
        .card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 30px;
            overflow: hidden;
        }
        
        .card-header {
            background-color: #3498db;
            color: white;
            padding: 15px 20px;
            font-size: 18px;
            font-weight: 600;
        }
        
        .card-body {
            padding: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
        }
        
        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background-color: #3498db;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            transition: background-color 0.3s;
        }
        
        .btn:hover {
            background-color: #2980b9;
        }
        
        .btn-success {
            background-color: #2ecc71;
        }
        
        .btn-success:hover {
            background-color: #27ae60;
        }
        
        .btn-warning {
            background-color: #f39c12;
        }
        
        .btn-warning:hover {
            background-color: #e67e22;
        }
        
        .btn-danger {
            background-color: #e74c3c;
        }
        
        .btn-danger:hover {
            background-color: #c0392b;
        }
        
        .btn-sm {
            padding: 5px 10px;
            font-size: 14px;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .table th,
        .table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        .table th {
            background-color: #f8f9fa;
            font-weight: 600;
        }
        
        .table tr:hover {
            background-color: #f8f9fa;
        }
        
        .status {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 500;
        }
        
        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .status-in-progress {
            background-color: #d1ecf1;
            color: #0c5460;
        }
        
        .status-resolved {
            background-color: #d4edda;
            color: #155724;
        }
        
        .status-closed {
            background-color: #e2e3e5;
            color: #383d41;
        }
        
        .priority {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 500;
        }
        
        .priority-low {
            background-color: #d4edda;
            color: #155724;
        }
        
        .priority-medium {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .priority-high {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .priority-urgent {
            background-color: #e74c3c;
            color: white;
        }
        
        .message {
            padding: 12px 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        
        .message-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .message-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            z-index: 1000;
            overflow: auto;
        }
        
        .modal-content {
            background-color: white;
            margin: 10% auto;
            padding: 0;
            border-radius: 8px;
            width: 80%;
            max-width: 600px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }
        
        .modal-header {
            background-color: #3498db;
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h2 {
            margin: 0;
            font-size: 20px;
        }
        
        .close {
            color: white;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .close:hover {
            opacity: 0.7;
        }
        
        .modal-body {
            padding: 20px;
        }
        
        .modal-footer {
            padding: 15px 20px;
            background-color: #f8f9fa;
            display: flex;
            justify-content: flex-end;
        }
        
        .tabs {
            display: flex;
            margin-bottom: 20px;
            border-bottom: 1px solid #ddd;
        }
        
        .tab {
            padding: 10px 20px;
            cursor: pointer;
            background-color: #f8f9fa;
            border: 1px solid #ddd;
            border-bottom: none;
            border-radius: 4px 4px 0 0;
            margin-right: 5px;
        }
        
        .tab.active {
            background-color: white;
            border-bottom: 1px solid white;
            margin-bottom: -1px;
            color: #3498db;
            font-weight: 600;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .detail-row {
            display: flex;
            margin-bottom: 15px;
        }
        
        .detail-label {
            font-weight: 600;
            width: 150px;
            color: #555;
        }
        
        .detail-value {
            flex: 1;
        }
        
        @media (max-width: 768px) {
            .navbar h1 {
                margin-left: 0;
                font-size: 20px;
            }
            
            .logo {
                width: 180px;
            }
            
            .sidebar {
                width: 100%;
                position: relative;
                top: 0;
                left: 0;
                margin-bottom: 20px;
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .table-responsive {
                overflow-x: auto;
            }
            
            .modal-content {
                width: 95%;
                margin: 5% auto;
            }
            
            .detail-row {
                flex-direction: column;
            }
            
            .detail-label {
                width: 100%;
                margin-bottom: 5px;
            }
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <img src="https://ik.imagekit.io/nuvq7aygp/BMB_Logo.jpg" alt="BMB Logo" class="logo">
    
    <div class="navbar">
        <h1>Technical Requests Portal</h1>
        <div class="user-info">
            <span>Welcome, <?php echo htmlspecialchars($user_name); ?></span>
            <a href="start.php">Back to Dashboard</a>
        </div>
    </div>
    
    <div class="sidebar">
        <ul class="sidebar-menu">
            <li><a href="serve_technical_requests.php" class="active"><i class="fas fa-ticket-alt"></i> Technical Requests</a></li>
            <li><a href="employees_details_admin.php"><i class="fas fa-user-edit"></i> Employee Details</a></li>
            <li><a href="user_management_admin.php"><i class="fas fa-users-cog"></i> User Management</a></li>
            <li><a href="not_available.php"><i class="fas fa-cogs"></i> System Settings</a></li>
            <li><a href="not_available.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
            <li><a href="not_available.php"><i class="fas fa-history"></i> Audit Logs</a></li>
        </ul>
    </div>
    
    <div class="container">
        <div class="main-content">
            <?php if (!empty($message)): ?>
                <div class="message message-<?php echo $message_type; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            
            <div class="tabs">
                <div class="tab active" onclick="openTab(event, 'new-request')">New Request</div>
                <div class="tab" onclick="openTab(event, 'view-requests')">All Requests</div>
            </div>
            
            <div id="new-request" class="tab-content active">
                <div class="card">
                    <div class="card-header">Create New Technical Request</div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <div class="form-group">
                                <label for="issue_type">Issue Type</label>
                                <select id="issue_type" name="issue_type" required>
                                    <option value="">Select Issue Type</option>
                                    <option value="Hardware">Hardware</option>
                                    <option value="Software">Software</option>
                                    <option value="Network">Network</option>
                                    <option value="Login/Account">Login/Account</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="sub_issue_type">Sub-Issue Type</label>
                                <input type="text" id="sub_issue_type" name="sub_issue_type" required placeholder="e.g., Printer not working, Software installation, etc.">
                            </div>
                            
                            <div class="form-group">
                                <label for="subject">Subject</label>
                                <input type="text" id="subject" name="subject" required placeholder="Brief description of the issue">
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
                                <label for="description">Description</label>
                                <textarea id="description" name="description" required placeholder="Detailed description of the issue"></textarea>
                            </div>
                            
                            <button type="submit" name="create_request" class="btn">Submit Request</button>
                        </form>
                    </div>
                </div>
            </div>
            
            <div id="view-requests" class="tab-content">
                <div class="card">
                    <div class="card-header">All Technical Requests</div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Employee</th>
                                        <th>Subject</th>
                                        <th>Issue Type</th>
                                        <th>Status</th>
                                        <th>Priority</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($requests)): ?>
                                        <tr>
                                            <td colspan="8">No technical requests found.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($requests as $request): ?>
                                            <tr>
                                                <td><?php echo $request['id']; ?></td>
                                                <td><?php echo htmlspecialchars($request['employee_name']); ?></td>
                                                <td><?php echo htmlspecialchars($request['subject']); ?></td>
                                                <td><?php echo htmlspecialchars($request['issue_type']); ?></td>
                                                <td><span class="status status-<?php echo strtolower(str_replace(' ', '-', $request['status'])); ?>"><?php echo $request['status']; ?></span></td>
                                                <td><span class="priority priority-<?php echo strtolower($request['priority']); ?>"><?php echo $request['priority']; ?></span></td>
                                                <td><?php echo date('M j, Y', strtotime($request['created_at'])); ?></td>
                                                <td>
                                                    <button class="btn btn-sm" onclick="viewRequest(<?php echo $request['id']; ?>)">View</button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- View Request Modal -->
    <div id="viewRequestModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Technical Request Details</h2>
                <span class="close" onclick="closeModal('viewRequestModal')">&times;</span>
            </div>
            <div class="modal-body" id="viewRequestContent">
                <!-- Content will be loaded dynamically -->
            </div>
            <div class="modal-footer">
                <button class="btn" onclick="closeModal('viewRequestModal')">Close</button>
            </div>
        </div>
    </div>
    
    <script>
        // Tab functionality
        function openTab(evt, tabName) {
            var i, tabcontent, tabs;
            tabcontent = document.getElementsByClassName("tab-content");
            for (i = 0; i < tabcontent.length; i++) {
                tabcontent[i].classList.remove("active");
            }
            tabs = document.getElementsByClassName("tab");
            for (i = 0; i < tabs.length; i++) {
                tabs[i].classList.remove("active");
            }
            document.getElementById(tabName).classList.add("active");
            evt.currentTarget.classList.add("active");
        }
        
        // Modal functionality
        function viewRequest(requestId) {
            // Make AJAX request to get request details
            var xhr = new XMLHttpRequest();
            xhr.onreadystatechange = function() {
                if (xhr.readyState == 4 && xhr.status == 200) {
                    document.getElementById("viewRequestContent").innerHTML = xhr.responseText;
                    document.getElementById("viewRequestModal").style.display = "block";
                }
            };
            xhr.open("GET", "get_request_details.php?id=" + requestId, true);
            xhr.send();
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = "none";
        }
        
        // Close modal when clicking outside of it
        window.onclick = function(event) {
            var modals = document.getElementsByClassName("modal");
            for (var i = 0; i < modals.length; i++) {
                if (event.target == modals[i]) {
                    modals[i].style.display = "none";
                }
            }
        }
    </script>
</body>
</html>