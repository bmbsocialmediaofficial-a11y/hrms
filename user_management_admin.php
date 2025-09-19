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
// Handle form submissions
$message = "";
$message_type = "";
$action = isset($_GET['action']) ? $_GET['action'] : '';
// Handle user status update
if ($action === 'update_status' && isset($_POST['update_user_status'])) {
    $user_id_to_update = $_POST['user_id'];
    $new_status = $_POST['valid_user'];
    
    $update_sql = "UPDATE employees SET valid_user = ? WHERE id = ?";
    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param("ii", $new_status, $user_id_to_update);
    
    if ($stmt->execute()) {
        $message = "User status updated successfully!";
        $message_type = "success";
    } else {
        $message = "Error updating user status: " . $conn->error;
        $message_type = "error";
    }
    
    $stmt->close();
}
// Handle user role update
if ($action === 'update_roles' && isset($_POST['update_user_roles'])) {
    $user_id_to_update = $_POST['user_id'];
    $is_hr = isset($_POST['is_hr']) ? 1 : 0;
    $is_admin = isset($_POST['is_admin']) ? 1 : 0;
    $is_manager = isset($_POST['is_manager']) ? 1 : 0;
    $is_director = isset($_POST['is_director']) ? 1 : 0;
    $is_ca = isset($_POST['is_ca']) ? 1 : 0;
    
    $update_sql = "UPDATE employees SET 
        is_hr = ?, 
        is_admin = ?, 
        is_manager = ?, 
        is_director = ?, 
        is_ca = ? 
        WHERE id = ?";
    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param("iiiiii", $is_hr, $is_admin, $is_manager, $is_director, $is_ca, $user_id_to_update);
    
    if ($stmt->execute()) {
        $message = "User roles updated successfully!";
        $message_type = "success";
    } else {
        $message = "Error updating user roles: " . $conn->error;
        $message_type = "error";
    }
    
    $stmt->close();
}
// Get filter parameters
$filter_department = isset($_GET['filter_department']) ? $_GET['filter_department'] : '';
$filter_status = isset($_GET['filter_status']) ? $_GET['filter_status'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d', strtotime('-30 days'));
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');
// Fetch all departments for filter dropdown
$departments = [];
$dept_sql = "SELECT DISTINCT department FROM employees WHERE department IS NOT NULL AND department != '' ORDER BY department";
$dept_result = $conn->query($dept_sql);
if ($dept_result->num_rows > 0) {
    while ($row = $dept_result->fetch_assoc()) {
        $departments[] = $row['department'];
    }
}
// Build WHERE clause for filters
$where_conditions = ["1=1"];
if (!empty($filter_department)) {
    $where_conditions[] = "e.department = '" . $conn->real_escape_string($filter_department) . "'";
}
if ($filter_status !== '') {
    $where_conditions[] = "e.valid_user = " . (int)$filter_status;
}
$where_clause = implode(" AND ", $where_conditions);
// Fetch user login/logout statistics
$user_stats = [];
$stats_sql = "
    SELECT 
        e.id,
        e.name,
        e.department,
        e.valid_user,
        e.email,
        e.is_hr,
        e.is_admin,
        e.is_manager,
        e.is_director,
        e.is_ca,
        e.employee_id,
        e.job_title,
        e.date_of_joining,
        COUNT(DISTINCT DATE(al.login_time)) as days_active,
        COUNT(al.id) as total_logins,
        MIN(al.login_time) as first_login,
        MAX(al.logout_time) as last_logout
    FROM 
        employees e
    LEFT JOIN 
        activity_logs al ON e.id = al.employee_id 
        AND DATE(al.login_time) BETWEEN '$date_from' AND '$date_to'
    WHERE 
        $where_clause
    GROUP BY 
        e.id, e.name, e.department, e.valid_user, e.email, e.is_hr, e.is_admin, e.is_manager, e.is_director, e.is_ca, e.employee_id, e.job_title, e.date_of_joining
    ORDER BY 
        e.name
";
$stats_result = $conn->query($stats_sql);
if ($stats_result->num_rows > 0) {
    while ($row = $stats_result->fetch_assoc()) {
        // Calculate average login time per day
        $avg_logins_per_day = $row['days_active'] > 0 ? round($row['total_logins'] / $row['days_active'], 2) : 0;
        
        // Get login times for this user
        $user_id = $row['id'];
        $login_times_sql = "
            SELECT 
                DATE(login_time) as login_date,
                MIN(TIME(login_time)) as first_login,
                MAX(TIME(logout_time)) as last_logout,
                TIMESTAMPDIFF(MINUTE, MIN(login_time), MAX(logout_time)) as active_minutes
            FROM 
                activity_logs
            WHERE 
                employee_id = $user_id
                AND DATE(login_time) BETWEEN '$date_from' AND '$date_to'
            GROUP BY 
                DATE(login_time)
            ORDER BY 
                login_date
        ";
        $login_times_result = $conn->query($login_times_sql);
        
        $daily_logins = [];
        $total_active_minutes = 0;
        $active_days = 0;
        
        if ($login_times_result->num_rows > 0) {
            while ($login_row = $login_times_result->fetch_assoc()) {
                $daily_logins[] = [
                    'date' => $login_row['login_date'],
                    'first_login' => $login_row['first_login'],
                    'last_logout' => $login_row['last_logout'],
                    'active_minutes' => $login_row['active_minutes']
                ];
                
                if ($login_row['active_minutes'] > 0) {
                    $total_active_minutes += $login_row['active_minutes'];
                    $active_days++;
                }
            }
        }
        
        // Calculate average active time per day in hours
        $avg_active_hours_per_day = $active_days > 0 ? round($total_active_minutes / $active_days / 60, 2) : 0;
        
        // Prepare roles array
        $roles = [];
        if ($row['is_hr']) $roles[] = 'HR';
        if ($row['is_admin']) $roles[] = 'Admin';
        if ($row['is_manager']) $roles[] = 'Manager';
        if ($row['is_director']) $roles[] = 'Director';
        if ($row['is_ca']) $roles[] = 'CA';
        
        $user_stats[] = [
            'id' => $row['id'],
            'name' => $row['name'],
            'department' => $row['department'],
            'valid_user' => $row['valid_user'],
            'email' => $row['email'],
            'is_hr' => $row['is_hr'],
            'is_admin' => $row['is_admin'],
            'is_manager' => $row['is_manager'],
            'is_director' => $row['is_director'],
            'is_ca' => $row['is_ca'],
            'employee_id' => $row['employee_id'],
            'job_title' => $row['job_title'],
            'date_of_joining' => $row['date_of_joining'],
            'days_active' => $row['days_active'],
            'total_logins' => $row['total_logins'],
            'avg_logins_per_day' => $avg_logins_per_day,
            'first_login' => $row['first_login'],
            'last_logout' => $row['last_logout'],
            'daily_logins' => $daily_logins,
            'avg_active_hours_per_day' => $avg_active_hours_per_day,
            'total_active_hours' => round($total_active_minutes / 60, 2),
            'roles' => $roles // Add roles array to user stats
        ];
    }
}
// Calculate department statistics
$dept_stats = [];
foreach ($departments as $dept) {
    $dept_users = array_filter($user_stats, function($user) use ($dept) {
        return $user['department'] === $dept;
    });
    
    if (count($dept_users) > 0) {
        $total_users = count($dept_users);
        $active_users = count(array_filter($dept_users, function($user) {
            return $user['valid_user'] == 1;
        }));
        
        $total_logins = array_sum(array_column($dept_users, 'total_logins'));
        $total_active_hours = array_sum(array_column($dept_users, 'total_active_hours'));
        $avg_active_hours_per_user = $total_users > 0 ? round($total_active_hours / $total_users, 2) : 0;
        
        $dept_stats[] = [
            'department' => $dept,
            'total_users' => $total_users,
            'active_users' => $active_users,
            'total_logins' => $total_logins,
            'total_active_hours' => $total_active_hours,
            'avg_active_hours_per_user' => $avg_active_hours_per_user
        ];
    }
}
// Calculate overall statistics
$total_users = count($user_stats);
$total_active_users = count(array_filter($user_stats, function($user) {
    return $user['valid_user'] == 1;
}));
$total_logins = array_sum(array_column($user_stats, 'total_logins'));
$total_active_hours = array_sum(array_column($user_stats, 'total_active_hours'));
$avg_active_hours_per_user = $total_users > 0 ? round($total_active_hours / $total_users, 2) : 0;
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Admin System</title>
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
            position: relative;
            z-index: 1;
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
        
        .btn-secondary {
            background-color: #6c757d;
        }
        
        .btn-secondary:hover {
            background-color: #5a6268;
        }
        
        .btn-sm {
            padding: 5px 10px;
            font-size: 14px;
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
        
        .status-active {
            background-color: #d4edda;
            color: #155724;
        }
        
        .status-inactive {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .filter-row {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .filter-row .form-group {
            flex: 1;
            margin-bottom: 0;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 20px;
            text-align: center;
        }
        
        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: #3498db;
            margin-bottom: 10px;
        }
        
        .stat-label {
            font-size: 16px;
            color: #666;
        }
        
        .chart-container {
            height: 300px;
            margin-bottom: 30px;
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
            max-width: 800px;
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
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
        }
        
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 15px;
            margin-top: 20px;
        }
        
        /* Added styles for table responsiveness */
        .table-responsive {
            overflow-x: auto;
            width: 100%;
            margin: 0;
            padding: 0;
        }
        
        .table-responsive .table {
            min-width: 100%;
            width: auto;
            max-width: none;
            white-space: nowrap;
        }
        
        .table-responsive .table th,
        .table-responsive .table td {
            white-space: normal;
        }
        
        /* User details modal styles */
        .user-details-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .user-details-item {
            display: flex;
            flex-direction: column;
        }
        
        .user-details-label {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 5px;
            font-size: 14px;
        }
        
        .user-details-value {
            padding: 8px;
            background-color: #f8f9fa;
            border-radius: 4px;
            border: 1px solid #e9ecef;
        }
        
        .user-details-full {
            grid-column: span 2;
        }
        
        .login-details-table {
            margin-top: 20px;
            width: 100%;
        }
        
        .login-details-table th {
            background-color: #f1f5f9;
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
            
            .filter-row {
                flex-direction: column;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .modal-content {
                width: 95%;
                margin: 5% auto;
            }
            
            .table-responsive {
                border: 1px solid #ddd;
                border-radius: 4px;
            }
            
            .table-responsive .table {
                white-space: nowrap;
            }
            
            .table-responsive .table th,
            .table-responsive .table td {
                padding: 8px 10px;
            }
            
            .user-details-grid {
                grid-template-columns: 1fr;
            }
            
            .user-details-full {
                grid-column: span 1;
            }
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <img src="https://ik.imagekit.io/nuvq7aygp/BMB_Logo.jpg" alt="BMB Logo" class="logo">
    
    <div class="navbar">
        <h1>User Management</h1>
        <div class="user-info">
            <span>Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
            <a href="serve_technical_requests.php">Back to Dashboard</a>
        </div>
    </div>
    
    <div class="sidebar">
        <ul class="sidebar-menu">
            <li><a href="serve_technical_requests.php"><i class="fas fa-ticket-alt"></i> Technical Requests</a></li>
            <li><a href="employees_details_admin.php"><i class="fas fa-user-edit"></i> Employee Details</a></li>
            <li><a href="user_management_admin.php" class="active"><i class="fas fa-users-cog"></i> User Management</a></li>
            <li><a href="system_settings.php"><i class="fas fa-cogs"></i> System Settings</a></li>
            <li><a href="reports_admin.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
            <li><a href="audit_logs.php"><i class="fas fa-history"></i> Audit Logs</a></li>
        </ul>
    </div>
    
    <div class="container">
        <div class="main-content">
            <?php if (!empty($message)): ?>
                <div class="message message-<?php echo $message_type; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-header">User Activity Statistics</div>
                <div class="card-body">
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-value"><?php echo $total_users; ?></div>
                            <div class="stat-label">Total Users</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value"><?php echo $total_active_users; ?></div>
                            <div class="stat-label">Active Users</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value"><?php echo $total_logins; ?></div>
                            <div class="stat-label">Total Logins</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value"><?php echo $total_active_hours; ?></div>
                            <div class="stat-label">Total Active Hours</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value"><?php echo $avg_active_hours_per_user; ?></div>
                            <div class="stat-label">Avg Hours/User</div>
                        </div>
                    </div>
                    
                    <div class="filter-row">
                        <div class="form-group">
                            <label for="filter_department">Department</label>
                            <select id="filter_department" name="filter_department" class="form-control">
                                <option value="">All Departments</option>
                                <?php foreach ($departments as $department): ?>
                                    <option value="<?php echo htmlspecialchars($department); ?>" 
                                        <?php echo ($filter_department === $department) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($department); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="filter_status">Status</label>
                            <select id="filter_status" name="filter_status" class="form-control">
                                <option value="">All Users</option>
                                <option value="1" <?php echo ($filter_status === '1') ? 'selected' : ''; ?>>Active Only</option>
                                <option value="0" <?php echo ($filter_status === '0') ? 'selected' : ''; ?>>Inactive Only</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="date_from">Date From</label>
                            <input type="date" id="date_from" name="date_from" class="form-control" value="<?php echo $date_from; ?>">
                        </div>
                        <div class="form-group">
                            <label for="date_to">Date To</label>
                            <input type="date" id="date_to" name="date_to" class="form-control" value="<?php echo $date_to; ?>">
                        </div>
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <button type="button" class="btn" onclick="applyFilters()">Apply Filters</button>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="tabs">
                <div class="tab active" onclick="openTab(event, 'user-stats')">User Statistics</div>
                <div class="tab" onclick="openTab(event, 'dept-stats')">Department Statistics</div>
                <div class="tab" onclick="openTab(event, 'user-management')">User Management</div>
            </div>
            
            <div id="user-stats" class="tab-content active">
                <div class="card">
                    <div class="card-header">User Login Statistics</div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Department</th>
                                        <th>Status</th>
                                        <th>Days Active</th>
                                        <th>Total Logins</th>
                                        <th>Avg Logins/Day</th>
                                        <th>Avg Active Hours/Day</th>
                                        <th>Total Active Hours</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($user_stats)): ?>
                                        <tr>
                                            <td colspan="9">No user data found for the selected criteria.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($user_stats as $user): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($user['name']); ?></td>
                                                <td><?php echo htmlspecialchars($user['department']); ?></td>
                                                <td>
                                                    <span class="status status-<?php echo $user['valid_user'] ? 'active' : 'inactive'; ?>">
                                                        <?php echo $user['valid_user'] ? 'Active' : 'Inactive'; ?>
                                                    </span>
                                                </td>
                                                <td><?php echo $user['days_active']; ?></td>
                                                <td><?php echo $user['total_logins']; ?></td>
                                                <td><?php echo $user['avg_logins_per_day']; ?></td>
                                                <td><?php echo $user['avg_active_hours_per_day']; ?></td>
                                                <td><?php echo $user['total_active_hours']; ?></td>
                                                <td>
                                                    <button type="button" class="btn btn-sm" onclick="viewUserDetails(<?php echo $user['id']; ?>)">View Details</button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">User Activity Chart</div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="userActivityChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
            <div id="dept-stats" class="tab-content">
                <div class="card">
                    <div class="card-header">Department Statistics</div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Department</th>
                                        <th>Total Users</th>
                                        <th>Active Users</th>
                                        <th>Total Logins</th>
                                        <th>Total Active Hours</th>
                                        <th>Avg Hours/User</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($dept_stats)): ?>
                                        <tr>
                                            <td colspan="6">No department data found.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($dept_stats as $dept): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($dept['department']); ?></td>
                                                <td><?php echo $dept['total_users']; ?></td>
                                                <td><?php echo $dept['active_users']; ?></td>
                                                <td><?php echo $dept['total_logins']; ?></td>
                                                <td><?php echo $dept['total_active_hours']; ?></td>
                                                <td><?php echo $dept['avg_active_hours_per_user']; ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">Department Comparison Chart</div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="deptComparisonChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
            <div id="user-management" class="tab-content">
                <div class="card">
                    <div class="card-header">User Management</div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Department</th>
                                        <th>Email</th>
                                        <th>Status</th>
                                        <th>Roles</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($user_stats)): ?>
                                        <tr>
                                            <td colspan="6">No users found.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($user_stats as $user): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($user['name']); ?></td>
                                                <td><?php echo htmlspecialchars($user['department']); ?></td>
                                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                                <td>
                                                    <span class="status status-<?php echo $user['valid_user'] ? 'active' : 'inactive'; ?>">
                                                        <?php echo $user['valid_user'] ? 'Active' : 'Inactive'; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php
                                                    // Use the pre-calculated roles array
                                                    echo !empty($user['roles']) ? implode(', ', $user['roles']) : 'No roles assigned';
                                                    ?>
                                                </td>
                                                <td>
                                                    <button type="button" class="btn btn-sm" onclick="editUserStatus(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['name']); ?>', <?php echo $user['valid_user']; ?>)">Edit Status</button>
                                                    <!-- Edit Roles button removed as requested -->
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
    
    <!-- User Details Modal -->
    <div id="userDetailsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>User Details</h2>
                <span class="close" onclick="closeModal('userDetailsModal')">&times;</span>
            </div>
            <div class="modal-body" id="userDetailsContent">
                <!-- Content will be loaded dynamically -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" onclick="closeModal('userDetailsModal')">Close</button>
            </div>
        </div>
    </div>
    
    <!-- Edit User Status Modal -->
    <div id="editUserStatusModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit User Status</h2>
                <span class="close" onclick="closeModal('editUserStatusModal')">&times;</span>
            </div>
            <div class="modal-body">
                <form id="editUserStatusForm" method="POST" action="user_management_admin.php?action=update_status">
                    <input type="hidden" id="status_user_id" name="user_id">
                    <div class="form-group">
                        <label>User Name</label>
                        <input type="text" id="status_user_name" class="form-control" readonly>
                    </div>
                    <div class="form-group">
                        <label for="valid_user">Account Status</label>
                        <select id="valid_user" name="valid_user" class="form-control">
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                    <div class="form-actions">
                        <button type="submit" name="update_user_status" class="btn">Update Status</button>
                        <button type="button" class="btn btn-secondary" onclick="closeModal('editUserStatusModal')">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Edit User Roles Modal -->
    <div id="editUserRolesModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit User Roles</h2>
                <span class="close" onclick="closeModal('editUserRolesModal')">&times;</span>
            </div>
            <div class="modal-body">
                <form id="editUserRolesForm" method="POST" action="user_management_admin.php?action=update_roles">
                    <input type="hidden" id="roles_user_id" name="user_id">
                    <div class="form-group">
                        <label>User Name</label>
                        <input type="text" id="roles_user_name" class="form-control" readonly>
                    </div>
                    <div class="form-group">
                        <label>System Roles</label>
                        <div class="checkbox-group">
                            <input type="checkbox" id="modal_is_hr" name="is_hr" value="1">
                            <label for="modal_is_hr">HR Access</label>
                        </div>
                        <div class="checkbox-group">
                            <input type="checkbox" id="modal_is_admin" name="is_admin" value="1">
                            <label for="modal_is_admin">Admin Access</label>
                        </div>
                        <div class="checkbox-group">
                            <input type="checkbox" id="modal_is_manager" name="is_manager" value="1">
                            <label for="modal_is_manager">Manager Access</label>
                        </div>
                        <div class="checkbox-group">
                            <input type="checkbox" id="modal_is_director" name="is_director" value="1">
                            <label for="modal_is_director">Director Access</label>
                        </div>
                        <div class="checkbox-group">
                            <input type="checkbox" id="modal_is_ca" name="is_ca" value="1">
                            <label for="modal_is_ca">CA Access</label>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" name="update_user_roles" class="btn">Update Roles</button>
                        <button type="button" class="btn btn-secondary" onclick="closeModal('editUserRolesModal')">Cancel</button>
                    </div>
                </form>
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
            
            // Initialize charts when tabs are opened
            if (tabName === 'user-stats') {
                setTimeout(initUserActivityChart, 100);
            } else if (tabName === 'dept-stats') {
                setTimeout(initDeptComparisonChart, 100);
            }
        }
        
        // Apply filters
        function applyFilters() {
            const department = document.getElementById('filter_department').value;
            const status = document.getElementById('filter_status').value;
            const dateFrom = document.getElementById('date_from').value;
            const dateTo = document.getElementById('date_to').value;
            
            let url = 'user_management_admin.php?';
            const params = [];
            
            if (department) params.push('filter_department=' + encodeURIComponent(department));
            if (status !== '') params.push('filter_status=' + encodeURIComponent(status));
            if (dateFrom) params.push('date_from=' + encodeURIComponent(dateFrom));
            if (dateTo) params.push('date_to=' + encodeURIComponent(dateTo));
            
            url += params.join('&');
            window.location.href = url;
        }
        
        // View user details
        function viewUserDetails(userId) {
            // Find user data - convert userId to number for proper comparison
            const userStats = <?php echo json_encode($user_stats); ?>;
            const user = userStats.find(u => parseInt(u.id) === parseInt(userId));
            
            if (!user) return;
            
            let content = `
                <div class="user-details-grid">
                    <div class="user-details-item">
                        <div class="user-details-label">Name</div>
                        <div class="user-details-value">${user.name}</div>
                    </div>
                    <div class="user-details-item">
                        <div class="user-details-label">Employee ID</div>
                        <div class="user-details-value">${user.employee_id || 'N/A'}</div>
                    </div>
                    <div class="user-details-item">
                        <div class="user-details-label">Department</div>
                        <div class="user-details-value">${user.department}</div>
                    </div>
                    <div class="user-details-item">
                        <div class="user-details-label">Job Title</div>
                        <div class="user-details-value">${user.job_title || 'N/A'}</div>
                    </div>
                    <div class="user-details-item">
                        <div class="user-details-label">Email</div>
                        <div class="user-details-value">${user.email}</div>
                    </div>
                    <div class="user-details-item">
                        <div class="user-details-label">Date of Joining</div>
                        <div class="user-details-value">${user.date_of_joining || 'N/A'}</div>
                    </div>
                    <div class="user-details-item">
                        <div class="user-details-label">Account Status</div>
                        <div class="user-details-value">${user.valid_user ? 'Active' : 'Inactive'}</div>
                    </div>
                    <div class="user-details-item">
                        <div class="user-details-label">System Roles</div>
                        <div class="user-details-value">
                            ${(() => {
                                // Use the pre-calculated roles array
                                return user.roles && user.roles.length > 0 ? user.roles.join(', ') : 'No roles assigned';
                            })()}
                        </div>
                    </div>
                    <div class="user-details-item">
                        <div class="user-details-label">Days Active</div>
                        <div class="user-details-value">${user.days_active}</div>
                    </div>
                    <div class="user-details-item">
                        <div class="user-details-label">Total Logins</div>
                        <div class="user-details-value">${user.total_logins}</div>
                    </div>
                    <div class="user-details-item">
                        <div class="user-details-label">Average Logins/Day</div>
                        <div class="user-details-value">${user.avg_logins_per_day}</div>
                    </div>
                    <div class="user-details-item">
                        <div class="user-details-label">Average Active Hours/Day</div>
                        <div class="user-details-value">${user.avg_active_hours_per_day}</div>
                    </div>
                    <div class="user-details-item">
                        <div class="user-details-label">Total Active Hours</div>
                        <div class="user-details-value">${user.total_active_hours}</div>
                    </div>
                    <div class="user-details-item">
                        <div class="user-details-label">First Login</div>
                        <div class="user-details-value">${user.first_login || 'N/A'}</div>
                    </div>
                    <div class="user-details-item">
                        <div class="user-details-label">Last Logout</div>
                        <div class="user-details-value">${user.last_logout || 'N/A'}</div>
                    </div>
                </div>
                
                <div class="user-details-item user-details-full">
                    <div class="user-details-label">Daily Login Details</div>
                    <div class="table-responsive">
                        <table class="table login-details-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>First Login</th>
                                    <th>Last Logout</th>
                                    <th>Active Minutes</th>
                                </tr>
                            </thead>
                            <tbody>
            `;
            
            if (user.daily_logins && user.daily_logins.length > 0) {
                user.daily_logins.forEach(login => {
                    content += `
                        <tr>
                            <td>${login.date}</td>
                            <td>${login.first_login || 'N/A'}</td>
                            <td>${login.last_logout || 'N/A'}</td>
                            <td>${login.active_minutes || '0'}</td>
                        </tr>
                    `;
                });
            } else {
                content += '<tr><td colspan="4">No login data available</td></tr>';
            }
            
            content += `
                            </tbody>
                        </table>
                    </div>
                </div>
            `;
            
            document.getElementById('userDetailsContent').innerHTML = content;
            document.getElementById('userDetailsModal').style.display = 'block';
        }
        
        // Edit user status
        function editUserStatus(userId, userName, currentStatus) {
            document.getElementById('status_user_id').value = userId;
            document.getElementById('status_user_name').value = userName;
            document.getElementById('valid_user').value = currentStatus;
            document.getElementById('editUserStatusModal').style.display = 'block';
        }
        
        // Edit user roles
        function editUserRoles(userId, userName) {
            // Find user data
            const user = <?php echo json_encode($user_stats); ?>.find(u => u.id === userId);
            
            if (!user) return;
            
            document.getElementById('roles_user_id').value = userId;
            document.getElementById('roles_user_name').value = userName;
            document.getElementById('modal_is_hr').checked = user.is_hr;
            document.getElementById('modal_is_admin').checked = user.is_admin;
            document.getElementById('modal_is_manager').checked = user.is_manager;
            document.getElementById('modal_is_director').checked = user.is_director;
            document.getElementById('modal_is_ca').checked = user.is_ca;
            document.getElementById('editUserRolesModal').style.display = 'block';
        }
        
        // Close modal
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        // Initialize User Activity Chart
        function initUserActivityChart() {
            const ctx = document.getElementById('userActivityChart').getContext('2d');
            
            // Destroy existing chart if it exists
            if (window.userActivityChartInstance) {
                window.userActivityChartInstance.destroy();
            }
            
            const userStats = <?php echo json_encode($user_stats); ?>;
            
            // Sort users by total active hours
            const sortedUsers = [...userStats].sort((a, b) => b.total_active_hours - a.total_active_hours);
            
            // Take top 10 users
            const topUsers = sortedUsers.slice(0, 10);
            
            window.userActivityChartInstance = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: topUsers.map(u => u.name),
                    datasets: [{
                        label: 'Total Active Hours',
                        data: topUsers.map(u => u.total_active_hours),
                        backgroundColor: 'rgba(52, 152, 219, 0.7)',
                        borderColor: 'rgba(52, 152, 219, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Hours'
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Users'
                            }
                        }
                    },
                    plugins: {
                        title: {
                            display: true,
                            text: 'Top 10 Users by Active Hours'
                        }
                    }
                }
            });
        }
        
        // Initialize Department Comparison Chart
        function initDeptComparisonChart() {
            const ctx = document.getElementById('deptComparisonChart').getContext('2d');
            
            // Destroy existing chart if it exists
            if (window.deptComparisonChartInstance) {
                window.deptComparisonChartInstance.destroy();
            }
            
            const deptStats = <?php echo json_encode($dept_stats); ?>;
            
            window.deptComparisonChartInstance = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: deptStats.map(d => d.department),
                    datasets: [
                        {
                            label: 'Total Users',
                            data: deptStats.map(d => d.total_users),
                            backgroundColor: 'rgba(52, 152, 219, 0.7)',
                            borderColor: 'rgba(52, 152, 219, 1)',
                            borderWidth: 1
                        },
                        {
                            label: 'Active Users',
                            data: deptStats.map(d => d.active_users),
                            backgroundColor: 'rgba(46, 204, 113, 0.7)',
                            borderColor: 'rgba(46, 204, 113, 1)',
                            borderWidth: 1
                        },
                        {
                            label: 'Avg Hours/User',
                            data: deptStats.map(d => d.avg_active_hours_per_user),
                            backgroundColor: 'rgba(241, 196, 15, 0.7)',
                            borderColor: 'rgba(241, 196, 15, 1)',
                            borderWidth: 1
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
                    },
                    plugins: {
                        title: {
                            display: true,
                            text: 'Department Comparison'
                        }
                    }
                }
            });
        }
        
        // Initialize charts on page load
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(initUserActivityChart, 100);
        });
        
        // Close modal when clicking outside of it
        window.onclick = function(event) {
            const modals = document.getElementsByClassName("modal");
            for (let i = 0; i < modals.length; i++) {
                if (event.target == modals[i]) {
                    modals[i].style.display = "none";
                }
            }
        }
    </script>
</body>
</html>