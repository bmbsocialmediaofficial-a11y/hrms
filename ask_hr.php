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

// For ask_hr.php, check if user has HR privileges
$current_page = basename($_SERVER['PHP_SELF']);
if ($current_page === 'ask_hr.php') {
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

// Get current user info
$user_id = $_SESSION['user_id'];
$user_sql = "SELECT * FROM employees WHERE id = '$user_id'";
$user_result = $conn->query($user_sql);
$current_user = $user_result->fetch_assoc();

// Check if user is HR/admin
$is_hr = true; // For demo purposes, set to true. Implement proper role checking

// Fetch all employees for assignment dropdown
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

// Handle new ticket submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_ticket'])) {
    $title = $conn->real_escape_string($_POST['title']);
    $category = $conn->real_escape_string($_POST['category']);
    $description = $conn->real_escape_string($_POST['description']);
    $priority = $conn->real_escape_string($_POST['priority']);
    
  $resolution = $conn->real_escape_string($_POST['resolution']);

$sql = "INSERT INTO hr_tickets (employee_id, title, category, description, priority, resolution) 
        VALUES ('$user_id', '$title', '$category', '$description', '$priority', '$resolution')";
    
    if ($conn->query($sql)) {
        $message = "HR ticket created successfully!";
        $message_type = "success";
    } else {
        $message = "Error: " . $conn->error;
        $message_type = "error";
    }
}

// Handle ticket assignment/update
// Handle ticket assignment/update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_ticket'])) {
    $ticket_id = $_POST['ticket_id'];
    $status = $conn->real_escape_string($_POST['status']);
    $assigned_to = !empty($_POST['assigned_to']) ? $_POST['assigned_to'] : "NULL";
    $notes = $conn->real_escape_string($_POST['notes']);
    
    // If status is being changed to Resolved, set resolved_at timestamp
    $resolved_at_sql = "";
    if ($status == 'Resolved') {
        $resolved_at_sql = ", resolved_at = NOW()";
    }
    
  $resolution = $conn->real_escape_string($_POST['resolution']);

$sql = "UPDATE hr_tickets SET status = '$status', assigned_to = $assigned_to, resolution = '$resolution'
        $resolved_at_sql WHERE id = '$ticket_id'";
    
    if ($conn->query($sql)) {
        // If notes are provided, insert into hr_notes table
       // If notes are provided, insert into hr_notes table
if (!empty($notes)) {
    $note_category = "Ticket Note"; // You can customize this
    $notes_sql = "INSERT INTO hr_notes (ticket_id, employee_id, note_date, category, note_text, created_by) 
                  VALUES ('$ticket_id', '$user_id', NOW(), '$note_category', '$notes', '$user_id')";
    $conn->query($notes_sql);
}
        
        $message = "Ticket updated successfully!";
        $message_type = "success";
    } else {
        $message = "Error: " . $conn->error;
        $message_type = "error";
    }
}

// Fetch tickets based on user role
if ($is_hr) {
    // HR can see all tickets
    $tickets_sql = "SELECT t.*, e.name as employee_name, e.department, 
                   a.name as assigned_name 
                   FROM hr_tickets t 
                   JOIN employees e ON t.employee_id = e.id 
                   LEFT JOIN employees a ON t.assigned_to = a.id 
                   ORDER BY t.created_at DESC";
} else {
    // Regular employees can only see their own tickets
    $tickets_sql = "SELECT t.*, e.name as employee_name, e.department, 
                   a.name as assigned_name 
                   FROM hr_tickets t 
                   JOIN employees e ON t.employee_id = e.id 
                   LEFT JOIN employees a ON t.assigned_to = a.id 
                   WHERE t.employee_id = '$user_id' 
                   ORDER BY t.created_at DESC";
}

$tickets_result = $conn->query($tickets_sql);
$tickets = array();

if ($tickets_result->num_rows > 0) {
    while ($row = $tickets_result->fetch_assoc()) {
        $tickets[] = $row;
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ASK HR Tickets - Buymeabook</title>
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
        
        textarea.form-control {
            min-height: 120px;
            resize: vertical;
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
            font-size: 0.6rem;
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
        
        .ticket-details {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
        }
        
        .ticket-details h4 {
            margin-bottom: 10px;
            color: #1a2a6c;
        }
        
        .ticket-info {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .ticket-info div {
            margin-bottom: 10px;
        }
        
        .ticket-info strong {
            display: inline-block;
            min-width: 120px;
            color: #555;
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
            margin: 10% auto;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.2);
            width: 80%;
            max-width: 700px;
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
        
        @media (max-width: 1024px) {
            .dashboard {
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
            
            .data-table {
                font-size: 14px;
            }
            
            .ticket-info {
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
                <a href="hrms.php" class="nav-link"><i class="fas fa-users-cog"></i> HR Management</a>
                <a href="ask_hr.php" class="nav-link active"><i class="fas fa-headset"></i> ASK HR Tickets</a>
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
                    <li><a href="benefits.php"><i class="fas fa-stethoscope"></i> Benefits</a></li>
					<li><a href="ask_hr.php" class="active"><i class="fas fa-headset"></i> ASK HR Tickets</a></li>
					<li><a href="employees_details_hr.php"><i class="fas fa-user-edit"></i> Employee Details</a></li>
                </ul>
            </div>
            
            <div class="main-content">
                <h2 class="section-title"><i class="fas fa-headset"></i> ASK HR Tickets System</h2>
                
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-ticket-alt"></i>
                        </div>
                        <div class="stat-value"><?php echo count($tickets); ?></div>
                        <div class="stat-label">Total Tickets</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-value"><?php echo count(array_filter($tickets, function($t) { return $t['status'] == 'Open'; })); ?></div>
                        <div class="stat-label">Open Tickets</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-value"><?php echo count(array_filter($tickets, function($t) { return $t['status'] == 'Resolved'; })); ?></div>
                        <div class="stat-label">Resolved Tickets</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-user-tie"></i>
                        </div>
                        <?php echo isset($user_id) 
    ? count(array_filter($tickets, function($t) use ($user_id) { return $t['assigned_to'] == $user_id; })) 
    : 0; 
?>

                        <div class="stat-label">Assigned to Me</div>
                    </div>
                </div>
                
                <div class="form-section">
                    <h3 class="form-title"><i class="fas fa-plus-circle"></i> Create New HR Ticket</h3>
                    <form method="POST" id="ticket-form">
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="title">Ticket Title</label>
                                <input type="text" id="title" name="title" class="form-control" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="category">Category</label>
                                <select id="category" name="category" class="form-control" required>
                                    <option value="">Select Category</option>
                                    <option value="Payroll">Payroll Issue</option>
                                    <option value="Benefits">Benefits Question</option>
                                    <option value="Leave">Leave Policy</option>
                                    <option value="Policy">HR Policy</option>
                                    <option value="Complaint">Complaint</option>
                                    <option value="Technical">Technical Issue</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="priority">Priority</label>
                                <select id="priority" name="priority" class="form-control" required>
                                    <option value="Medium">Medium</option>
                                    <option value="Low">Low</option>
                                    <option value="High">High</option>
                                    <option value="Critical">Critical</option>
                                </select>
                            </div>
                            
                            <div class="form-group" style="grid-column: 1 / -1;">
                                <label for="description">Description</label>
                                <textarea id="description" name="description" class="form-control" rows="4" required></textarea>
                            </div>
							<div class="form-group" style="grid-column: 1 / -1;">
    <label for="resolution">Resolution (Optional)</label>
    <textarea id="resolution" name="resolution" class="form-control" rows="3"></textarea>
</div>
                        </div>
                        
                        <button type="submit" name="create_ticket" class="btn"><i class="fas fa-paper-plane"></i> Submit Ticket</button>
                    </form>
                </div>
                
                <div class="data-section">
                    <h3 class="form-title"><i class="fas fa-list"></i> HR Tickets</h3>
                    <div style="overflow-x: auto;">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Ticket ID</th>
                                    <th>Title</th>
                                    <th>Employee</th>
                                    <th>Department</th>
                                    <th>Category</th>
                                    <th>Priority</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th>Assigned To</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (count($tickets) > 0): ?>
                                <?php foreach ($tickets as $ticket): ?>
                                    <tr>
                                        <td>#<?php echo $ticket['id']; ?></td>
                                        <td><?php echo htmlspecialchars($ticket['title']); ?></td>
                                        <td><?php echo htmlspecialchars($ticket['employee_name']); ?></td>
                                        <td><?php echo htmlspecialchars($ticket['department']); ?></td>
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
                                                if ($ticket['status'] == 'Open') echo 'badge-warning';
                                                elseif ($ticket['status'] == 'In Progress') echo 'badge-primary';
                                                elseif ($ticket['status'] == 'Resolved') echo 'badge-success';
                                                else echo 'badge-secondary';
                                                ?>">
                                                <?php echo $ticket['status']; ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('M j, Y', strtotime($ticket['created_at'])); ?></td>
                                        <td><?php echo !empty($ticket['assigned_name']) ? htmlspecialchars($ticket['assigned_name']) : 'Unassigned'; ?></td>
                                        <td>
                                            <button class="btn btn-secondary view-ticket" data-id="<?php echo $ticket['id']; ?>" style="padding: 5px 10px; font-size: 14px;">
                                                <i class="fas fa-eye"></i> View
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="10" style="text-align: center;">No HR tickets found.</td>
                                </tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

   <!-- View Ticket Modal -->
<div id="ticketModal" class="modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h3 class="form-title"><i class="fas fa-ticket-alt"></i> Ticket Details</h3>
        <div id="ticketDetails"></div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Modal functionality
        const modal = document.getElementById('ticketModal');
        const closeBtn = document.querySelector('.close');
        const ticketDetails = document.getElementById('ticketDetails');
        
        // View ticket buttons
        const viewButtons = document.querySelectorAll('.view-ticket');
        viewButtons.forEach(button => {
            button.addEventListener('click', function() {
                const ticketId = this.getAttribute('data-id');
                fetchTicketDetails(ticketId);
            });
        });
        
        // Close modal
        closeBtn.addEventListener('click', function() {
            modal.style.display = 'none';
        });
        
        window.addEventListener('click', function(event) {
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        });
        
        // Fetch ticket details via AJAX
        function fetchTicketDetails(ticketId) {
            // Create AJAX request to fetch ticket details
            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'get_ticket_details.php', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            
            xhr.onload = function() {
                if (this.status === 200) {
                    const response = JSON.parse(this.responseText);
                    
                    if (response.success) {
                        const ticket = response.ticket;
                        
                        // Format the date
                        const createdDate = new Date(ticket.created_at);
                        const formattedDate = createdDate.toLocaleDateString('en-US', { 
                            year: 'numeric', 
                            month: 'short', 
                            day: 'numeric'
                        });
                        
                        // Determine badge classes
                        const priorityBadgeClass = 
                            ticket.priority === 'Critical' ? 'badge-danger' :
                            ticket.priority === 'High' ? 'badge-warning' :
                            ticket.priority === 'Medium' ? 'badge-primary' : 'badge-info';
                            
                        const statusBadgeClass = 
                            ticket.status === 'Open' ? 'badge-warning' :
                            ticket.status === 'In Progress' ? 'badge-primary' :
                            ticket.status === 'Resolved' ? 'badge-success' : 'badge-secondary';
                        
                        // Build the HTML content
                        let htmlContent = `
                            <div class="ticket-details">
                                <h4>Ticket #${ticket.id}</h4>
                                <div class="ticket-info">
                                    <div><strong>Title:</strong> ${ticket.title}</div>
                                    <div><strong>Status:</strong> <span class="badge ${statusBadgeClass}">${ticket.status}</span></div>
                                    <div><strong>Priority:</strong> <span class="badge ${priorityBadgeClass}">${ticket.priority}</span></div>
                                    <div><strong>Category:</strong> ${ticket.category}</div>
                                    <div><strong>Created:</strong> ${formattedDate}</div>
                                    <div><strong>Employee:</strong> ${ticket.employee_name}</div>
                                    ${ticket.assigned_name ? `<div><strong>Assigned To:</strong> ${ticket.assigned_name}</div>` : ''}
                                </div>
                                <div style="margin-top: 15px;">
                                    <strong>Description:</strong>
                                    <p>${ticket.description}</p>
                                </div>
                                <div style="margin-top: 15px;">
                                    <strong>Resolution:</strong>
                                    <p>${ticket.resolution ? ticket.resolution : 'No resolution provided yet.'}</p>
                                </div>
                            </div>
                        `;
                        
                        // Fetch notes for this ticket
                        fetchTicketNotes(ticketId).then(notes => {
                            if (notes.length > 0) {
                                let notesHtml = `
                                    <div style="margin-top: 15px;">
                                        <strong>Notes:</strong>
                                        <div style="max-height: 200px; overflow-y: auto; margin-top: 10px; padding: 10px; background: #fff; border-radius: 5px; border: 1px solid #ddd;">
                                `;
                                
                                notes.forEach(note => {
                                    const noteDate = new Date(note.note_date);
                                    const formattedNoteDate = noteDate.toLocaleDateString('en-US', { 
                                        year: 'numeric', 
                                        month: 'short', 
                                        day: 'numeric',
                                        hour: '2-digit',
                                        minute: '2-digit'
                                    });
                                    
                                    notesHtml += `
                                        <div style="margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid #eee;">
                                            <div style="font-weight: bold;">${note.created_by_name} - ${formattedNoteDate}</div>
                                            <div style="margin-top: 5px;">${note.note_text}</div>
                                        </div>
                                    `;
                                });
                                
                                notesHtml += `</div></div>`;
                                htmlContent += notesHtml;
                            }
                            
                            <?php if ($is_hr): ?>
                            htmlContent += `
                                <form method="POST" id="update-ticket-form">
                                    <input type="hidden" name="ticket_id" value="${ticketId}">
                                    <div class="form-grid">
                                        <div class="form-group">
                                            <label for="status">Status</label>
                                            <select id="status" name="status" class="form-control" required>
                                                <option value="Open" ${ticket.status === 'Open' ? 'selected' : ''}>Open</option>
                                                <option value="In Progress" ${ticket.status === 'In Progress' ? 'selected' : ''}>In Progress</option>
                                                <option value="Resolved" ${ticket.status === 'Resolved' ? 'selected' : ''}>Resolved</option>
                                                <option value="Closed" ${ticket.status === 'Closed' ? 'selected' : ''}>Closed</option>
                                            </select>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="assigned_to">Assign To</label>
                                            <select id="assigned_to" name="assigned_to" class="form-control">
                                                <option value="">Unassigned</option>
                                                <?php foreach ($employees as $employee): ?>
                                                    <option value="<?php echo $employee['id']; ?>" ${ticket.assigned_to == <?php echo $employee['id']; ?> ? 'selected' : ''}>
                                                        <?php echo htmlspecialchars($employee['name'] . ' (' . $employee['department'] . ')'); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        
                                        <div class="form-group" style="grid-column: 1 / -1;">
                                            <label for="notes">Add New Note</label>
                                            <textarea id="notes" name="notes" class="form-control" rows="3"></textarea>
                                        </div>
                                        <div class="form-group" style="grid-column: 1 / -1;">
                                            <label for="resolution">Resolution</label>
                                            <textarea id="resolution" name="resolution" class="form-control" rows="3">${ticket.resolution || ''}</textarea>
                                        </div>
                                    </div>
                                    
                                    <button type="submit" name="update_ticket" class="btn"><i class="fas fa-save"></i> Update Ticket</button>
                                </form>
                            `;
                            <?php else: ?>
                            htmlContent += `
                                <div class="message info">
                                    <i class="fas fa-info-circle"></i> Only HR staff can update tickets.
                                </div>
                            `;
                            <?php endif; ?>
                            
                            ticketDetails.innerHTML = htmlContent;
                            modal.style.display = 'block';
                        });
                    } else {
                        alert('Error loading ticket details: ' . response.message);
                    }
                } else {
                    alert('Error fetching ticket details. Please try again.');
                }
            };
            
            xhr.onerror = function() {
                alert('Request failed. Please check your connection.');
            };
            
            xhr.send('ticket_id=' + ticketId);
        }
        
        // Function to fetch ticket notes
        function fetchTicketNotes(ticketId) {
            return new Promise((resolve, reject) => {
                const xhr = new XMLHttpRequest();
                xhr.open('POST', 'get_ticket_notes.php', true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                
                xhr.onload = function() {
                    if (this.status === 200) {
                        try {
                            const response = JSON.parse(this.responseText);
                            if (response.success) {
                                resolve(response.notes);
                            } else {
                                resolve([]);
                            }
                        } catch (e) {
                            resolve([]);
                        }
                    } else {
                        resolve([]);
                    }
                };
                
                xhr.onerror = function() {
                    resolve([]);
                };
                
                xhr.send('ticket_id=' + ticketId);
            });
        }
        
        // Form validation for ticket creation
        const ticketForm = document.getElementById('ticket-form');
        if (ticketForm) {
            ticketForm.addEventListener('submit', function(e) {
                const title = document.getElementById('title').value;
                const category = document.getElementById('category').value;
                const description = document.getElementById('description').value;
                
                if (!title || !category || !description) {
                    e.preventDefault();
                    alert('Please fill in all required fields.');
                }
            });
        }
    });
</script>
</body>
</html>