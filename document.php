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

// For document.php, check if user has HR privileges
$current_page = basename($_SERVER['PHP_SELF']);
if ($current_page === 'document.php') {
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

// Fetch all employees for filters
$employees = array();
$emp_sql = "SELECT id, name, department FROM employees WHERE valid_user = TRUE ORDER BY name";
$emp_result = $conn->query($emp_sql);

if ($emp_result->num_rows > 0) {
    while ($row = $emp_result->fetch_assoc()) {
        $employees[] = $row;
    }
}

// Document types for filter
$document_types = array("Contract", "ID Proof", "Certificate", "Resume", "Offer Letter", "Appraisal", "Other");

// Initialize filter variables
$employee_filter = isset($_GET['employee']) ? $_GET['employee'] : '';
$type_filter = isset($_GET['type']) ? $_GET['type'] : '';
$expiring_filter = isset($_GET['expiring']) ? $_GET['expiring'] : '';

// Build query with filters
$where_conditions = array();
$query_params = array();

if (!empty($employee_filter)) {
    $where_conditions[] = "d.employee_id = ?";
    $query_params[] = $employee_filter;
}

if (!empty($type_filter)) {
    $where_conditions[] = "d.document_type = ?";
    $query_params[] = $type_filter;
}

if (!empty($expiring_filter)) {
    $where_conditions[] = "d.expiry_date IS NOT NULL AND d.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)";
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = "WHERE " . implode(" AND ", $where_conditions);
}

// Fetch documents with filters
$documents = array();
$doc_sql = "SELECT d.*, e.name as employee_name, u.name as uploaded_by_name 
            FROM employee_documents d 
            JOIN employees e ON d.employee_id = e.id 
            JOIN employees u ON d.uploaded_by = u.id 
            $where_clause
            ORDER BY d.uploaded_at DESC";

$stmt = $conn->prepare($doc_sql);

if (!empty($query_params)) {
    $types = str_repeat('s', count($query_params));
    $stmt->bind_param($types, ...$query_params);
}

$stmt->execute();
$doc_result = $stmt->get_result();

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
    <title>Document Management - Buymeabook</title>
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
        
        .filter-section {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.08);
        }
        
        .filter-title {
            font-size: 1.2rem;
            margin-bottom: 15px;
            color: #1a2a6c;
        }
        
        .filter-form {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 15px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #444;
        }
        
        .form-control {
            width: 100%;
            padding: 10px;
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
            padding: 10px 18px;
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
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 14px;
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
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #666;
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 15px;
            color: #ccc;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
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
            
            .filter-form {
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
                <a href="uploader.html" class="nav-link"><i class="fas fa-folder"></i> Manage Files</a>
                <a href="logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>
        
        <div class="dashboard">
            <div class="sidebar">
                <h2 class="sidebar-title">HR Dashboard</h2>
                <ul class="sidebar-menu">
                    <li><a href="hrms.php"><i class="fas fa-tachometer-alt"></i> Overview</a></li>
                    <li><a href="attendance.php"><i class="fas fa-user-clock"></i> Attendance</a></li>
                    <li><a href="document.php" class="active"><i class="fas fa-file-alt"></i> Documents</a></li>
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
                <h2 class="section-title"><i class="fas fa-file-alt"></i> Document Management</h2>
                
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-file"></i>
                        </div>
                        <div class="stat-value"><?php echo count($documents); ?></div>
                        <div class="stat-label">Total Documents</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-value"><?php echo count($employees); ?></div>
                        <div class="stat-label">Employees</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-id-card"></i>
                        </div>
                        <div class="stat-value"><?php 
                            $id_count = 0;
                            foreach ($documents as $doc) {
                                if ($doc['document_type'] == 'ID Proof') $id_count++;
                            }
                            echo $id_count;
                        ?></div>
                        <div class="stat-label">ID Documents</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-file-contract"></i>
                        </div>
                        <div class="stat-value"><?php 
                            $contract_count = 0;
                            foreach ($documents as $doc) {
                                if ($doc['document_type'] == 'Contract') $contract_count++;
                            }
                            echo $contract_count;
                        ?></div>
                        <div class="stat-label">Contracts</div>
                    </div>
                </div>
                
                <div class="filter-section">
                    <h3 class="filter-title"><i class="fas fa-filter"></i> Filter Documents</h3>
                    <form method="GET" class="filter-form">
                        <div class="form-group">
                            <label for="employee">Employee</label>
                            <select id="employee" name="employee" class="form-control">
                                <option value="">All Employees</option>
                                <?php foreach ($employees as $employee): ?>
                                    <option value="<?php echo $employee['id']; ?>" <?php echo $employee_filter == $employee['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($employee['name'] . ' (' . $employee['department'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="type">Document Type</label>
                            <select id="type" name="type" class="form-control">
                                <option value="">All Types</option>
                                <?php foreach ($document_types as $type): ?>
                                    <option value="<?php echo $type; ?>" <?php echo $type_filter == $type ? 'selected' : ''; ?>>
                                        <?php echo $type; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="expiring">Expiring Soon</label>
                            <select id="expiring" name="expiring" class="form-control">
                                <option value="">No</option>
                                <option value="yes" <?php echo $expiring_filter == 'yes' ? 'selected' : ''; ?>>Yes (within 30 days)</option>
                            </select>
                        </div>
                        
                        <div class="form-group" style="align-self: end;">
                            <button type="submit" class="btn"><i class="fas fa-filter"></i> Apply Filters</button>
                            <a href="document.php" class="btn btn-secondary"><i class="fas fa-times"></i> Clear</a>
                        </div>
                    </form>
                </div>
                
                <div class="data-section">
                    <h3 class="filter-title"><i class="fas fa-file-alt"></i> Document List</h3>
                    
                    <?php if (count($documents) > 0): ?>
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
                                <?php foreach ($documents as $document): 
                                    $expiry_class = '';
                                    if (!empty($document['expiry_date'])) {
                                        $expiry_date = new DateTime($document['expiry_date']);
                                        $today = new DateTime();
                                        $interval = $today->diff($expiry_date);
                                        $days_until_expiry = $interval->format('%r%a');
                                        
                                        if ($days_until_expiry < 0) {
                                            $expiry_class = 'badge-danger';
                                        } else if ($days_until_expiry < 30) {
                                            $expiry_class = 'badge-warning';
                                        } else {
                                            $expiry_class = 'badge-success';
                                        }
                                    }
                                ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($document['document_name']); ?></td>
                                        <td><?php echo htmlspecialchars($document['employee_name']); ?></td>
                                        <td>
                                            <span class="badge badge-info"><?php echo htmlspecialchars($document['document_type']); ?></span>
                                        </td>
                                        <td><?php echo !empty($document['issue_date']) ? date('M j, Y', strtotime($document['issue_date'])) : '-'; ?></td>
                                        <td>
                                            <?php if (!empty($document['expiry_date'])): ?>
                                                <span class="badge <?php echo $expiry_class; ?>">
                                                    <?php echo date('M j, Y', strtotime($document['expiry_date'])); ?>
                                                </span>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($document['uploaded_by_name']); ?></td>
                                        <td><?php echo date('M j, Y H:i', strtotime($document['uploaded_at'])); ?></td>
                                        <td>
                                            <?php if (!empty($document['file_name'])): ?>
                                                <a href="download_document.php?id=<?php echo $document['id']; ?>" class="btn btn-sm btn-secondary">
                                                    <i class="fas fa-download"></i> Download
                                                </a>
                                            <?php else: ?>
                                                <span>No file</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-file-excel"></i>
                            <h3>No documents found</h3>
                            <p>Try adjusting your filters or upload new documents.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Simple filter form handling
            const filterForm = document.querySelector('.filter-form');
            const clearButton = document.querySelector('a[href="document.php"]');
            
            if (clearButton) {
                clearButton.addEventListener('click', function(e) {
                    // The href will naturally clear the filters
                    // No need for additional JavaScript
                });
            }
        });
    </script>
</body>
</html>