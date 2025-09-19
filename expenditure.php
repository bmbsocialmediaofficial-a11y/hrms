<?php
session_start();

// Include the configuration file
require_once 'config.php';


// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// For expenditure.php, check if user has Director privileges
$current_page = basename($_SERVER['PHP_SELF']);
if ($current_page === 'expenditure.php') {
    // Verify if the user has Director privileges
    $user_id = $_SESSION['user_id'];
    $sql = "SELECT is_director FROM employees WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$user_id]);
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($employee) {
        // Check if is_director is set and equals 1 (true)
        if (!isset($employee['is_director']) || $employee['is_director'] != 1) {
            // User doesn't have Director privileges
            $_SESSION['access_error'] = "You need Director privileges to access the Expenditure System";
            header("Location: illegal_access_core.php"); // Redirect to access denied page
            exit();
        } else {
            // Set Director session flags if not already set
            $_SESSION['is_director'] = true;
        }
    } else {
        // User not found in database
        $_SESSION['access_error'] = "User not found in system";
        header("Location: illegal_access_core.php");
        exit();
    }
}

// Check if expenditures table exists, create if not
$tableCheck = $conn->query("SHOW TABLES LIKE 'expenditures'");
if ($tableCheck->rowCount() == 0) {
    $createTableSQL = "
    CREATE TABLE expenditures (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        stakeholder_id INT(6) UNSIGNED NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        category ENUM('Salary', 'Infrastructure', 'Software', 'Marketing', 'Training', 'Travel', 'Equipment', 'Other') NOT NULL,
        description TEXT NOT NULL,
        expenditure_date DATE NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (stakeholder_id) REFERENCES employees(id) ON DELETE CASCADE
    );
    ";
    $conn->exec($createTableSQL);
}

// Check if round_robin table exists, create if not
$tableCheck = $conn->query("SHOW TABLES LIKE 'round_robin'");
if ($tableCheck->rowCount() == 0) {
    $createTableSQL = "
    CREATE TABLE round_robin (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        employee_id INT(6) UNSIGNED NOT NULL,
        turn_order INT(6) NOT NULL,
        is_active BOOLEAN DEFAULT TRUE,
        last_turn_date DATE,
        next_turn_date DATE,
        missed_turns INT DEFAULT 0,
        FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
    );
    ";
    $conn->exec($createTableSQL);
}

// Check if missed_round_robin table exists, create if not
$tableCheck = $conn->query("SHOW TABLES LIKE 'missed_round_robin'");
if ($tableCheck->rowCount() == 0) {
    $createTableSQL = "
    CREATE TABLE missed_round_robin (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        employee_id INT(6) UNSIGNED NOT NULL,
        missed_date DATE NOT NULL,
        reason TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
    );
    ";
    $conn->exec($createTableSQL);
}

// Check if employees table has round_robin_enabled column, add if not
$columnCheck = $conn->query("SHOW COLUMNS FROM employees LIKE 'round_robin_enabled'");
if ($columnCheck->rowCount() == 0) {
    $alterSQL = "ALTER TABLE employees ADD COLUMN round_robin_enabled BOOLEAN DEFAULT FALSE";
    $conn->exec($alterSQL);
}

// Initialize round robin if it doesn't exist for any director
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM round_robin rr JOIN employees e ON rr.employee_id = e.id WHERE e.is_director = 1");
$stmt->execute();
$roundRobinCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

if ($roundRobinCount == 0) {
    // Get all director employees
    $stmt = $conn->prepare("SELECT id FROM employees WHERE valid_user = 1 AND is_director = 1");
    $stmt->execute();
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Add each director employee to round robin table
    $turnOrder = 1;
    foreach ($employees as $employee) {
        $stmt = $conn->prepare("INSERT INTO round_robin (employee_id, turn_order, next_turn_date) VALUES (?, ?, CURDATE())");
        $stmt->execute([$employee['id'], $turnOrder]);
        
        // Also update the employees table to mark them as enabled for round robin
        $stmt = $conn->prepare("UPDATE employees SET round_robin_enabled = TRUE WHERE id = ?");
        $stmt->execute([$employee['id']]);
        
        $turnOrder++;
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_expenditure'])) {
        $stakeholder_id = $_POST['stakeholder_id'];
        $amount = $_POST['amount'];
        $category = $_POST['category'];
        $description = $_POST['description'];
        $expenditure_date = $_POST['expenditure_date'];
        
        $stmt = $conn->prepare("INSERT INTO expenditures (stakeholder_id, amount, category, description, expenditure_date) 
                               VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$stakeholder_id, $amount, $category, $description, $expenditure_date]);
        
        // Update round robin if this employee is in the round robin
        $stmt = $conn->prepare("SELECT * FROM round_robin WHERE employee_id = ? AND is_active = TRUE");
        $stmt->execute([$stakeholder_id]);
        $roundRobin = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($roundRobin) {
            // Update last turn date
            $stmt = $conn->prepare("UPDATE round_robin SET last_turn_date = CURDATE() WHERE employee_id = ?");
            $stmt->execute([$stakeholder_id]);
            
            // Get next employee in round robin (only directors)
            $stmt = $conn->prepare("SELECT rr.employee_id FROM round_robin rr 
                                   JOIN employees e ON rr.employee_id = e.id 
                                   WHERE rr.is_active = TRUE AND e.is_director = 1 
                                   ORDER BY rr.turn_order ASC");
            $stmt->execute();
            $allActiveEmployees = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Find current employee index
            $currentIndex = -1;
            foreach ($allActiveEmployees as $index => $employee) {
                if ($employee['employee_id'] == $stakeholder_id) {
                    $currentIndex = $index;
                    break;
                }
            }
            
            // Calculate next employee index
            $nextIndex = ($currentIndex + 1) % count($allActiveEmployees);
            $nextEmployeeId = $allActiveEmployees[$nextIndex]['employee_id'];
            
            // Update next turn date for next employee
            $stmt = $conn->prepare("UPDATE round_robin SET next_turn_date = CURDATE() WHERE employee_id = ?");
            $stmt->execute([$nextEmployeeId]);
        }
        
        header("Location: expenditure.php");
        exit();
    }
    
    if (isset($_POST['update_expenditure'])) {
        $expenditure_id = $_POST['expenditure_id'];
        $stakeholder_id = $_POST['stakeholder_id'];
        $amount = $_POST['amount'];
        $category = $_POST['category'];
        $description = $_POST['description'];
        $expenditure_date = $_POST['expenditure_date'];
        
        $stmt = $conn->prepare("UPDATE expenditures SET stakeholder_id=?, amount=?, category=?, description=?, expenditure_date=? WHERE id=?");
        $stmt->execute([$stakeholder_id, $amount, $category, $description, $expenditure_date, $expenditure_id]);
        
        header("Location: expenditure.php");
        exit();
    }
    
    // Handle round robin toggle
    if (isset($_POST['toggle_round_robin'])) {
        $employee_id = $_POST['employee_id'];
        $enabled = $_POST['enabled'] == 'true';
        
        // Update employee round robin status
        $stmt = $conn->prepare("UPDATE employees SET round_robin_enabled = ? WHERE id = ?");
        $stmt->execute([$enabled, $employee_id]);
        
        // Update round robin table
        $stmt = $conn->prepare("UPDATE round_robin SET is_active = ? WHERE employee_id = ?");
        $stmt->execute([$enabled, $employee_id]);
        
        header("Location: expenditure.php");
        exit();
    }
    
    // Handle missed round robin
    if (isset($_POST['mark_missed_turn'])) {
        $employee_id = $_POST['employee_id'];
        $reason = $_POST['reason'] ?? '';
        
        // Add to missed round robin table
        $stmt = $conn->prepare("INSERT INTO missed_round_robin (employee_id, missed_date, reason) VALUES (?, CURDATE(), ?)");
        $stmt->execute([$employee_id, $reason]);
        
        // Increment missed turns count
        $stmt = $conn->prepare("UPDATE round_robin SET missed_turns = missed_turns + 1 WHERE employee_id = ?");
        $stmt->execute([$employee_id]);
        
        // Get next employee in round robin (only directors)
        $stmt = $conn->prepare("SELECT rr.employee_id FROM round_robin rr 
                               JOIN employees e ON rr.employee_id = e.id 
                               WHERE rr.is_active = TRUE AND e.is_director = 1 
                               ORDER BY rr.turn_order ASC");
        $stmt->execute();
        $allActiveEmployees = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Find current employee index
        $currentIndex = -1;
        foreach ($allActiveEmployees as $index => $employee) {
            if ($employee['employee_id'] == $employee_id) {
                $currentIndex = $index;
                break;
            }
        }
        
        // Calculate next employee index
        $nextIndex = ($currentIndex + 1) % count($allActiveEmployees);
        $nextEmployeeId = $allActiveEmployees[$nextIndex]['employee_id'];
        
        // Update next turn date for next employee
        $stmt = $conn->prepare("UPDATE round_robin SET next_turn_date = CURDATE() WHERE employee_id = ?");
        $stmt->execute([$nextEmployeeId]);
        
        header("Location: expenditure.php");
        exit();
    }
}

// Handle expenditure deletion
if (isset($_GET['delete'])) {
    $expenditure_id = $_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM expenditures WHERE id = ?");
    $stmt->execute([$expenditure_id]);
    
    header("Location: expenditure.php");
    exit();
}

// Get expenditure data for editing if ID is provided
$edit_expenditure_data = null;
if (isset($_GET['edit'])) {
    $expenditure_id = $_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM expenditures WHERE id = ?");
    $stmt->execute([$expenditure_id]);
    $edit_expenditure_data = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Get filter type
$filterType = isset($_GET['filter']) ? $_GET['filter'] : 'all';

// Build query based on filter type
$whereClause = "WHERE emp.valid_user = 1";
if ($filterType === 'directors') {
    $whereClause .= " AND emp.is_director = 1";
} elseif ($filterType === 'non-directors') {
    $whereClause .= " AND emp.is_director = 0";
}

// Get all expenditures with stakeholder names and director status
$stmt = $conn->prepare("SELECT e.*, emp.name as stakeholder_name, emp.is_director 
                       FROM expenditures e 
                       JOIN employees emp ON e.stakeholder_id = emp.id 
                       $whereClause
                       ORDER BY e.expenditure_date DESC");
$stmt->execute();
$expenditures = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all valid stakeholders (employees with valid_user = 1)
$stmt = $conn->prepare("SELECT id, name, round_robin_enabled, is_director FROM employees WHERE valid_user = 1 ORDER BY name");
$stmt->execute();
$stakeholders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get round robin information (only directors)
$stmt = $conn->prepare("SELECT rr.*, emp.name as employee_name 
                       FROM round_robin rr 
                       JOIN employees emp ON rr.employee_id = emp.id 
                       WHERE emp.valid_user = 1 AND emp.is_director = 1
                       ORDER BY rr.turn_order ASC");
$stmt->execute();
$roundRobinInfo = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get missed round robin information (only directors)
$stmt = $conn->prepare("SELECT mrr.*, emp.name as employee_name 
                       FROM missed_round_robin mrr 
                       JOIN employees emp ON mrr.employee_id = emp.id 
                       WHERE emp.valid_user = 1 AND emp.is_director = 1
                       ORDER BY mrr.missed_date DESC LIMIT 10");
$stmt->execute();
$missedRoundRobin = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate total expenditure
$stmt = $conn->prepare("SELECT SUM(amount) as total FROM expenditures e 
                       JOIN employees emp ON e.stakeholder_id = emp.id 
                       WHERE emp.valid_user = 1");
$stmt->execute();
$total_expenditure = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Calculate expenditure by category
$stmt = $conn->prepare("SELECT category, SUM(amount) as total FROM expenditures e 
                       JOIN employees emp ON e.stakeholder_id = emp.id 
                       WHERE emp.valid_user = 1
                       GROUP BY category");
$stmt->execute();
$expenditure_by_category = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate expenditure by stakeholder
$stmt = $conn->prepare("SELECT emp.name, SUM(e.amount) as total FROM expenditures e 
                       JOIN employees emp ON e.stakeholder_id = emp.id 
                       WHERE emp.valid_user = 1
                       GROUP BY e.stakeholder_id");
$stmt->execute();
$expenditure_by_stakeholder = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get director expenditures for the table view
$stmt = $conn->prepare("SELECT e.*, emp.name as stakeholder_name 
                       FROM expenditures e 
                       JOIN employees emp ON e.stakeholder_id = emp.id 
                       WHERE emp.valid_user = 1 AND emp.is_director = 1
                       ORDER BY emp.name, e.expenditure_date ASC");
$stmt->execute();
$directorExpenditures = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all directors
$stmt = $conn->prepare("SELECT id, name FROM employees WHERE valid_user = 1 AND is_director = 1 ORDER BY name");
$stmt->execute();
$directors = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get the earliest expenditure date to determine our date range start
$stmt = $conn->prepare("SELECT MIN(expenditure_date) as min_date FROM expenditures e 
                       JOIN employees emp ON e.stakeholder_id = emp.id 
                       WHERE emp.valid_user = 1 AND emp.is_director = 1");
$stmt->execute();
$minDateResult = $stmt->fetch(PDO::FETCH_ASSOC);
$minDate = $minDateResult['min_date'] ?: date('Y-m-d');

// Generate date range from the earliest expenditure date to 30 days in the future
$dateRange = [];
$startDate = new DateTime($minDate);
$endDate = new DateTime();
$endDate->modify("+30 days");

$currentDate = clone $startDate;
while ($currentDate <= $endDate) {
    $dateRange[] = $currentDate->format('Y-m-d');
    $currentDate->modify("+1 day");
}

// Find the index of today's date in the date range
$today = date('Y-m-d');
$todayIndex = -1;
foreach ($dateRange as $index => $date) {
    if ($date === $today) {
        $todayIndex = $index;
        break;
    }
}

// Organize director expenditures by employee and date
$directorExpendituresByDate = [];
foreach ($directorExpenditures as $expenditure) {
    $date = $expenditure['expenditure_date'];
    $employeeId = $expenditure['stakeholder_id'];
    
    if (!isset($directorExpendituresByDate[$employeeId])) {
        $directorExpendituresByDate[$employeeId] = [];
    }
    
    if (!isset($directorExpendituresByDate[$employeeId][$date])) {
        $directorExpendituresByDate[$employeeId][$date] = [];
    }
    
    $directorExpendituresByDate[$employeeId][$date][] = $expenditure;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BMB - Expenditure Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #1a2a6c 0%, #2a5298 100%);
            min-height: 100vh;
            color: #333;
            position: relative;
            overflow-x: hidden;
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
            flex-direction: column;
            min-height: 100vh;
            padding: 100px 20px 20px;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            color: white;
        }
        
        .header h1 {
            font-size: 2.8rem;
            margin-bottom: 10px;
            text-shadow: 2px 2px 8px rgba(0, 0, 0, 0.3);
        }
        
        .header p {
            font-size: 1.2rem;
            opacity: 0.9;
        }
        
        .dashboard {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
        }
        
        .card h2 {
            margin-bottom: 15px;
            color: #1a2a6c;
            display: flex;
            align-items: center;
        }
        
        .card h2 i {
            margin-right: 10px;
        }
        
        .stats-container {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 15px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
            text-align: center;
        }
        
        .stat-value {
            font-size: 1.8rem;
            font-weight: bold;
            color: #1a2a6c;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 0.9rem;
            color: #666;
        }
        
        .filters {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .filter-btn {
            padding: 8px 15px;
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.5);
            border-radius: 20px;
            color: white;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 500;
            backdrop-filter: blur(10px);
        }
        
        .filter-btn.active, .filter-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
        }
        
        .expenditure-list {
            max-height: max-content;
            overflow-y: auto;
        }
        
        .expenditure-item {
            background: white;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 10px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
            border-left: 4px solid #1a2a6c;
            transition: all 0.3s;
        }
        
        .expenditure-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.15);
        }
        
        .expenditure-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .expenditure-title {
            font-weight: bold;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
        }
        
        .expenditure-amount {
            font-weight: bold;
            color: #1a2a6c;
            font-size: 1.2rem;
        }
        
        .expenditure-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            font-size: 0.9rem;
            margin-bottom: 10px;
        }
        
        .expenditure-detail {
            display: flex;
            align-items: center;
        }
        
        .expenditure-detail i {
            margin-right: 5px;
            color: #1a2a6c;
        }
        
        .expenditure-actions {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }
        
        .btn {
            padding: 6px 12px;
            border-radius: 5px;
            border: none;
            cursor: pointer;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            transition: all 0.3s;
        }
        
        .btn i {
            margin-right: 5px;
        }
        
        .btn-edit {
            background: #1a2a6c;
            color: white;
        }
        
        .btn-edit:hover {
            background: #152352;
        }
        
        .btn-delete {
            background: #dc3545;
            color: white;
        }
        
        .btn-delete:hover {
            background: #bd2130;
        }
        
        .btn-toggle {
            background: #28a745;
            color: white;
        }
        
        .btn-toggle:hover {
            background: #218838;
        }
        
        .btn-missed {
            background: #ffc107;
            color: #212529;
        }
        
        .btn-missed:hover {
            background: #e0a800;
        }
        
        .btn-roundrobin {
            background: #17a2b8;
            color: white;
        }
        
        .btn-roundrobin:hover {
            background: #138496;
        }
        
        .btn-director-table {
            background: #6f42c1;
            color: white;
        }
        
        .btn-director-table:hover {
            background: #5a32a3;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        
        .modal-content {
            background: white;
            border-radius: 15px;
            padding: 30px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.3);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .close {
            font-size: 1.5rem;
            cursor: pointer;
            color: #999;
        }
        
        .close:hover {
            color: #333;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .btn-primary {
            background: #1a2a6c;
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: bold;
            transition: all 0.3s;
        }
        
        .btn-primary:hover {
            background: #152352;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: bold;
            transition: all 0.3s;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }
        
        .round-robin-container {
            margin-top: 20px;
        }
        
        .round-robin-item {
            background: white;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 10px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
            border-left: 4px solid #28a745;
            transition: all 0.3s;
        }
        
        .round-robin-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.15);
        }
        
        .round-robin-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .round-robin-title {
            font-weight: bold;
            font-size: 1.1rem;
        }
        
        .round-robin-status {
            padding: 4px 8px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: bold;
        }
        
        .status-active {
            background: #d4edda;
            color: #155724;
        }
        
        .status-inactive {
            background: #f8d7da;
            color: #721c24;
        }
        
        .round-robin-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            font-size: 0.9rem;
            margin-bottom: 10px;
        }
        
        .round-robin-detail {
            display: flex;
            align-items: center;
        }
        
        .round-robin-detail i {
            margin-right: 5px;
            color: #28a745;
        }
        
        .missed-round-robin-container {
            margin-top: 20px;
        }
        
        .missed-round-robin-item {
            background: white;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 10px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
            border-left: 4px solid #ffc107;
            transition: all 0.3s;
        }
        
        .missed-round-robin-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.15);
        }
        
        .missed-round-robin-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .missed-round-robin-title {
            font-weight: bold;
            font-size: 1.1rem;
        }
        
        .missed-round-robin-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            font-size: 0.9rem;
            margin-bottom: 10px;
        }
        
        .missed-round-robin-detail {
            display: flex;
            align-items: center;
        }
        
        .missed-round-robin-detail i {
            margin-right: 5px;
            color: #ffc107;
        }
        
        .round-robin-card {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 90%;
            max-width: 800px;
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.3);
            z-index: 1001;
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .round-robin-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }
        
        .round-robin-card-close {
            font-size: 1.5rem;
            cursor: pointer;
            color: #999;
        }
        
        .round-robin-card-close:hover {
            color: #333;
        }
        
        .round-robin-card-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
        }
        
        .non-director-tag {
            background: #f8d7da;
            color: #721c24;
            font-size: 0.75rem;
            padding: 2px 6px;
            border-radius: 10px;
            margin-left: 8px;
            font-weight: bold;
        }
        
        .director-tag {
            background: #d4edda;
            color: #155724;
            font-size: 0.75rem;
            padding: 2px 6px;
            border-radius: 10px;
            margin-left: 8px;
            font-weight: bold;
        }
        
        .director-table-card {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 90%;
            max-width: 1200px;
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.3);
            z-index: 1001;
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .director-table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }
        
        .director-table-close {
            font-size: 1.5rem;
            cursor: pointer;
            color: #999;
        }
        
        .director-table-close:hover {
            color: #333;
        }
        
        .director-table-content {
            overflow-x: auto;
        }
        
        .director-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .director-table th, .director-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        .director-table th {
            background-color: #f8f9fa;
            font-weight: bold;
            color: #1a2a6c;
            position: sticky;
            top: 0;
        }
        
        .director-table tr:hover {
            background-color: #f8f9fa;
        }
        
        .date-column {
            min-width: 120px;
        }
        
        .amount-column {
            min-width: 100px;
        }
        
        .category-column {
            min-width: 120px;
        }
        
        .remarks-column {
            min-width: 200px;
        }
        
        .table-container {
            position: relative;
            max-height: 60vh;
            overflow-y: auto;
        }
        
        .no-expenditure {
            color: #6c757d;
            font-style: italic;
        }
        
        .expenditure-row {
            margin-bottom: 8px;
            padding-bottom: 8px;
            border-bottom: 1px dashed #eee;
        }
        
        .expenditure-row:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }
        
        .amount-row {
            font-weight: bold;
            color: #1a2a6c;
            margin-bottom: 4px;
        }
        
        .category-row {
            font-size: 0.85rem;
            color: #6c757d;
            margin-bottom: 4px;
        }
        
        .remarks-row {
            font-size: 0.85rem;
            color: #495057;
        }
        
        .table-navigation {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .table-info {
            font-size: 0.9rem;
            color: #6c757d;
        }
        
        .nav-buttons {
            display: flex;
            gap: 10px;
        }
        
        .nav-btn {
            padding: 6px 12px;
            background: #f8f9fa;
            border: 1px solid #ddd;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9rem;
            transition: all 0.2s;
        }
        
        .nav-btn:hover {
            background: #e9ecef;
        }
        
        .nav-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .nav-btn:disabled:hover {
            background: #f8f9fa;
        }
        
        .today-column {
            background-color: #e6f7ff !important;
            border-left: 3px solid #1890ff !important;
        }
        
        @media (max-width: 992px) {
            .dashboard {
                grid-template-columns: 1fr;
            }
            
            .stats-container {
                grid-template-columns: 1fr 1fr;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .round-robin-card-content {
                grid-template-columns: 1fr;
            }
            
            .director-table-card {
                width: 95%;
            }
        }
        
        @media (max-width: 768px) {
            .header h1 {
                font-size: 2.2rem;
            }
            
            .stats-container {
                grid-template-columns: 1fr;
            }
            
            .expenditure-details {
                grid-template-columns: 1fr;
            }
            
            .logo-container {
                position: relative;
                top: 0;
                left: 0;
                text-align: center;
                margin-bottom: 20px;
            }
            
            .container {
                padding-top: 20px;
            }
            
            .director-table-card {
                width: 98%;
                padding: 15px;
            }
            
            .director-table th, .director-table td {
                padding: 8px 10px;
                font-size: 0.9rem;
            }
            
            .table-navigation {
                flex-direction: column;
                gap: 10px;
            }
        }
        
        .btn-logout {
            background: rgba(255, 255, 255, 0.15);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.3);
            text-decoration: none;
        }

        .btn-logout:hover {
            background: rgba(255, 100, 100, 0.2);
            color: #ffcccc;
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
        }
        
        .top-right-buttons {
            position: absolute;
            top: 20px;
            right: 20px;
            display: flex;
            gap: 10px;
            z-index: 10;
        }
    </style>
</head>
<body>
    <div class="logo-container">
        <img src="https://ik.imagekit.io/nuvq7aygp/BMB_Logo.jpg" alt="BMB Logo" class="logo">
    </div>
    
    <div class="top-right-buttons">
        <a href="start.php" class="btn btn-logout">
            <i class="fas fa-sign-out-alt"></i> Home
        </a>
        
        <a href="logout.php" class="btn btn-logout">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>
    
    <div class="container">
        <div class="header">
            <h1>BMB Expenditure Management</h1>
            <p>Track and manage all stakeholder investments and expenses</p>
        </div>
        
        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-value">$<?php echo number_format($total_expenditure, 2); ?></div>
                <div class="stat-label">Total Expenditure</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo count($expenditures); ?></div>
                <div class="stat-label">Total Records</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo count($stakeholders); ?></div>
                <div class="stat-label">Active Stakeholders</div>
            </div>
        </div>
        
        <div class="filters">
            <button class="filter-btn <?php echo $filterType === 'all' ? 'active' : ''; ?>" onclick="filterExpenditures('all')">All Expenses</button>
            <button class="filter-btn <?php echo $filterType === 'directors' ? 'active' : ''; ?>" onclick="filterExpenditures('directors')">Directors Only</button>
            <button class="filter-btn <?php echo $filterType === 'non-directors' ? 'active' : ''; ?>" onclick="filterExpenditures('non-directors')">Non-Directors Only</button>
            <button class="filter-btn">This Month</button>
            <button class="filter-btn">Last Month</button>
            <button class="filter-btn">This Quarter</button>
            <button class="filter-btn">This Year</button>
            
            <button class="btn-primary" onclick="openModal('addExpenditureModal')">
                <i class="fas fa-plus"></i> Add New Expenditure
            </button>
            
            <button class="btn btn-roundrobin" onclick="showRoundRobinCard()">
                <i class="fas fa-sync-alt"></i> Round Robin View
            </button>
            
            <button class="btn btn-director-table" onclick="showDirectorTable()">
                <i class="fas fa-table"></i> Director Expenses Table
            </button>
        </div>
        
        <div class="dashboard">
            <div class="card">
                <h2><i class="fas fa-money-bill-wave"></i> Recent Expenditures</h2>
                <div class="expenditure-list">
                    <?php foreach ($expenditures as $expenditure): ?>
                        <div class="expenditure-item">
                            <div class="expenditure-header">
                                <div class="expenditure-title">
                                    <?php echo htmlspecialchars($expenditure['description']); ?>
                                    <?php if ($expenditure['is_director'] == 1): ?>
                                        <span class="director-tag">Director</span>
                                    <?php else: ?>
                                        <span class="non-director-tag">Non-Director</span>
                                    <?php endif; ?>
                                </div>
                                <div class="expenditure-amount">$<?php echo number_format($expenditure['amount'], 2); ?></div>
                            </div>
                            <div class="expenditure-details">
                                <div class="expenditure-detail">
                                    <i class="fas fa-user"></i>
                                    <span><?php echo htmlspecialchars($expenditure['stakeholder_name']); ?></span>
                                </div>
                                <div class="expenditure-detail">
                                    <i class="fas fa-tag"></i>
                                    <span><?php echo $expenditure['category']; ?></span>
                                </div>
                                <div class="expenditure-detail">
                                    <i class="fas fa-calendar"></i>
                                    <span><?php echo date('M j, Y', strtotime($expenditure['expenditure_date'])); ?></span>
                                </div>
                                <div class="expenditure-detail">
                                    <i class="fas fa-clock"></i>
                                    <span><?php echo date('M j, Y', strtotime($expenditure['created_at'])); ?></span>
                                </div>
                            </div>
                            <div class="expenditure-actions">
                                <button class="btn btn-edit" onclick="editExpenditure(<?php echo $expenditure['id']; ?>)">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <button class="btn btn-delete" onclick="deleteExpenditure(<?php echo $expenditure['id']; ?>)">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="card">
                <h2><i class="fas fa-chart-pie"></i> Expenditure Analysis</h2>
                <div class="chart-container">
                    <canvas id="categoryChart"></canvas>
                </div>
                <h2 style="margin-top: 30px;"><i class="fas fa-users"></i> Top Stakeholders</h2>
                <div class="expenditure-list">
                    <?php foreach ($expenditure_by_stakeholder as $stakeholder): ?>
                        <div class="expenditure-item">
                            <div class="expenditure-header">
                                <div class="expenditure-title"><?php echo htmlspecialchars($stakeholder['name']); ?></div>
                                <div class="expenditure-amount">$<?php echo number_format($stakeholder['total'], 2); ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Round Robin Card -->
    <div id="overlay" class="overlay" onclick="hideRoundRobinCard()"></div>
    <div id="roundRobinCard" class="round-robin-card">
        <div class="round-robin-card-header">
            <h2><i class="fas fa-sync-alt"></i> Round Robin Management</h2>
            <span class="round-robin-card-close" onclick="hideRoundRobinCard()">&times;</span>
        </div>
        <div class="round-robin-card-content">
            <div>
                <h3><i class="fas fa-users"></i> Directors in Round Robin</h3>
                <div class="round-robin-container">
                    <?php foreach ($roundRobinInfo as $employee): ?>
                        <div class="round-robin-item">
                            <div class="round-robin-header">
                                <div class="round-robin-title"><?php echo htmlspecialchars($employee['employee_name']); ?></div>
                                <div class="round-robin-status <?php echo $employee['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                    <?php echo $employee['is_active'] ? 'Active' : 'Inactive'; ?>
                                </div>
                            </div>
                            <div class="round-robin-details">
                                <div class="round-robin-detail">
                                    <i class="fas fa-sort-numeric-up"></i>
                                    <span>Turn Order: <?php echo $employee['turn_order']; ?></span>
                                </div>
                                <div class="round-robin-detail">
                                    <i class="fas fa-calendar-check"></i>
                                    <span>Last Turn: <?php echo $employee['last_turn_date'] ? date('M j, Y', strtotime($employee['last_turn_date'])) : 'Never'; ?></span>
                                </div>
                                <div class="round-robin-detail">
                                    <i class="fas fa-calendar-plus"></i>
                                    <span>Next Turn: <?php echo $employee['next_turn_date'] ? date('M j, Y', strtotime($employee['next_turn_date'])) : 'Not scheduled'; ?></span>
                                </div>
                                <div class="round-robin-detail">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    <span>Missed Turns: <?php echo $employee['missed_turns']; ?></span>
                                </div>
                            </div>
                            <div class="expenditure-actions">
                                <button class="btn btn-toggle" onclick="toggleRoundRobin(<?php echo $employee['employee_id']; ?>, <?php echo $employee['is_active'] ? 'false' : 'true'; ?>)">
                                    <i class="fas fa-toggle-<?php echo $employee['is_active'] ? 'on' : 'off'; ?>"></i> 
                                    <?php echo $employee['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                </button>
                                <button class="btn btn-missed" onclick="markMissedTurn(<?php echo $employee['employee_id']; ?>)">
                                    <i class="fas fa-times-circle"></i> Mark Missed Turn
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div>
                <h3><i class="fas fa-history"></i> Missed Round Robin History</h3>
                <div class="missed-round-robin-container">
                    <?php if (empty($missedRoundRobin)): ?>
                        <p>No missed turns recorded.</p>
                    <?php else: ?>
                        <?php foreach ($missedRoundRobin as $missed): ?>
                            <div class="missed-round-robin-item">
                                <div class="missed-round-robin-header">
                                    <div class="missed-round-robin-title"><?php echo htmlspecialchars($missed['employee_name']); ?></div>
                                </div>
                                <div class="missed-round-robin-details">
                                    <div class="missed-round-robin-detail">
                                        <i class="fas fa-calendar"></i>
                                        <span><?php echo date('M j, Y', strtotime($missed['missed_date'])); ?></span>
                                    </div>
                                    <div class="missed-round-robin-detail">
                                        <i class="fas fa-clock"></i>
                                        <span><?php echo date('M j, Y', strtotime($missed['created_at'])); ?></span>
                                    </div>
                                </div>
                                <?php if (!empty($missed['reason'])): ?>
                                    <div class="missed-round-robin-detail">
                                        <i class="fas fa-comment"></i>
                                        <span><?php echo htmlspecialchars($missed['reason']); ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Director Expenses Table -->
    <div id="directorTableOverlay" class="overlay" onclick="hideDirectorTable()"></div>
    <div id="directorTableCard" class="director-table-card">
        <div class="director-table-header">
            <h2><i class="fas fa-table"></i> Director Expenses Table</h2>
            <span class="director-table-close" onclick="hideDirectorTable()">&times;</span>
        </div>
        <div class="table-navigation">
            <div class="table-info">
                Showing all director expenditures from <strong><?php echo date('M j, Y', strtotime($minDate)); ?></strong> to <strong><?php echo date('M j, Y', strtotime('+30 days')); ?></strong>
            </div>
            <div class="nav-buttons">
                <button class="nav-btn" id="scrollLeftBtn" onclick="scrollTable('left')">
                    <i class="fas fa-chevron-left"></i> Scroll Left
                </button>
                <button class="nav-btn" id="scrollRightBtn" onclick="scrollTable('right')">
                    Scroll Right <i class="fas fa-chevron-right"></i>
                </button>
                <button class="nav-btn" onclick="scrollToToday()">
                    <i class="fas fa-calendar-day"></i> Today
                </button>
            </div>
        </div>
        <div class="director-table-content">
            <div class="table-container" id="tableContainer">
                <table class="director-table">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <?php foreach ($dateRange as $index => $date): ?>
                                <th class="date-column <?php echo $date === $today ? 'today-column' : ''; ?>" id="col-<?php echo $index; ?>">
                                    <?php echo date('M j', strtotime($date)); ?>
                                    <?php if ($date === $today): ?>
                                        <br><small>(Today)</small>
                                    <?php endif; ?>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($directors as $director): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($director['name']); ?></strong></td>
                                <?php foreach ($dateRange as $date): ?>
                                    <td>
                                        <?php 
                                        if (isset($directorExpendituresByDate[$director['id']][$date])) {
                                            foreach ($directorExpendituresByDate[$director['id']][$date] as $expenditure) {
                                                echo '<div class="expenditure-row">';
                                                echo '<div class="amount-row">$' . number_format($expenditure['amount'], 2) . '</div>';
                                                echo '<div class="category-row">' . htmlspecialchars($expenditure['category']) . '</div>';
                                                echo '<div class="remarks-row">' . htmlspecialchars($expenditure['description']) . '</div>';
                                                echo '</div>';
                                            }
                                        } else {
                                            echo '<div class="no-expenditure">No expenditure</div>';
                                        }
                                        ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Add Expenditure Modal -->
    <div id="addExpenditureModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Add New Expenditure</h2>
                <span class="close" onclick="closeModal('addExpenditureModal')">&times;</span>
            </div>
            <form action="expenditure.php" method="POST">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="stakeholder_id">Stakeholder</label>
                        <select id="stakeholder_id" name="stakeholder_id" class="form-control" required>
                            <option value="">Select Stakeholder</option>
                            <?php foreach ($stakeholders as $stakeholder): ?>
                                <option value="<?php echo $stakeholder['id']; ?>"><?php echo htmlspecialchars($stakeholder['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="amount">Amount ($)</label>
                        <input type="number" id="amount" name="amount" class="form-control" step="0.01" min="0" required>
                    </div>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="category">Category</label>
                        <select id="category" name="category" class="form-control" required>
                            <option value="">Select Category</option>
                            <option value="Salary">Salary</option>
                            <option value="Infrastructure">Infrastructure</option>
                            <option value="Software">Software</option>
                            <option value="Marketing">Marketing</option>
                            <option value="Training">Training</option>
                            <option value="Travel">Travel</option>
                            <option value="Equipment">Equipment</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="expenditure_date">Date</label>
                        <input type="date" id="expenditure_date" name="expenditure_date" class="form-control" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" class="form-control" rows="4" required></textarea>
                </div>
                
                <button type="submit" name="add_expenditure" class="btn-primary">Add Expenditure</button>
                <button type="button" class="btn-secondary" onclick="closeModal('addExpenditureModal')">Cancel</button>
            </form>
        </div>
    </div>
    
    <!-- Edit Expenditure Modal -->
    <div id="editExpenditureModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit Expenditure</h2>
                <span class="close" onclick="closeModal('editExpenditureModal')">&times;</span>
            </div>
            <form action="expenditure.php" method="POST">
                <input type="hidden" id="edit_expenditure_id" name="expenditure_id">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="edit_stakeholder_id">Stakeholder</label>
                        <select id="edit_stakeholder_id" name="stakeholder_id" class="form-control" required>
                            <option value="">Select Stakeholder</option>
                            <?php foreach ($stakeholders as $stakeholder): ?>
                                <option value="<?php echo $stakeholder['id']; ?>"><?php echo htmlspecialchars($stakeholder['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_amount">Amount ($)</label>
                        <input type="number" id="edit_amount" name="amount" class="form-control" step="0.01" min="0" required>
                    </div>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="edit_category">Category</label>
                        <select id="edit_category" name="category" class="form-control" required>
                            <option value="">Select Category</option>
                            <option value="Salary">Salary</option>
                            <option value="Infrastructure">Infrastructure</option>
                            <option value="Software">Software</option>
                            <option value="Marketing">Marketing</option>
                            <option value="Training">Training</option>
                            <option value="Travel">Travel</option>
                            <option value="Equipment">Equipment</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_expenditure_date">Date</label>
                        <input type="date" id="edit_expenditure_date" name="expenditure_date" class="form-control" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="edit_description">Description</label>
                    <textarea id="edit_description" name="description" class="form-control" rows="4" required></textarea>
                </div>
                
                <button type="submit" name="update_expenditure" class="btn-primary">Update Expenditure</button>
                <button type="button" class="btn-secondary" onclick="closeModal('editExpenditureModal')">Cancel</button>
            </form>
        </div>
    </div>
    
    <!-- Missed Turn Modal -->
    <div id="missedTurnModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Mark Missed Turn</h2>
                <span class="close" onclick="closeModal('missedTurnModal')">&times;</span>
            </div>
            <form action="expenditure.php" method="POST">
                <input type="hidden" id="missed_employee_id" name="employee_id">
                
                <div class="form-group">
                    <label for="reason">Reason (Optional)</label>
                    <textarea id="reason" name="reason" class="form-control" rows="4"></textarea>
                </div>
                
                <button type="submit" name="mark_missed_turn" class="btn-primary">Mark as Missed</button>
                <button type="button" class="btn-secondary" onclick="closeModal('missedTurnModal')">Cancel</button>
            </form>
        </div>
    </div>

    <script>
        // Modal functions
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'flex';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        // Close modal when clicking outside of it
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
        
        // Show/Hide Round Robin Card
        function showRoundRobinCard() {
            document.getElementById('overlay').style.display = 'block';
            document.getElementById('roundRobinCard').style.display = 'block';
        }
        
        function hideRoundRobinCard() {
            document.getElementById('overlay').style.display = 'none';
            document.getElementById('roundRobinCard').style.display = 'none';
        }
        
        // Show/Hide Director Table
        function showDirectorTable() {
            document.getElementById('directorTableOverlay').style.display = 'block';
            document.getElementById('directorTableCard').style.display = 'block';
            // Scroll to today's date when opening the table
            setTimeout(scrollToToday, 100);
        }
        
        function hideDirectorTable() {
            document.getElementById('directorTableOverlay').style.display = 'none';
            document.getElementById('directorTableCard').style.display = 'none';
        }
        
        // Filter expenditures
        function filterExpenditures(filterType) {
            window.location.href = 'expenditure.php?filter=' + filterType;
        }
        
        // Scroll table horizontally
        function scrollTable(direction) {
            const container = document.getElementById('tableContainer');
            const scrollAmount = 300; // Amount to scroll in pixels
            
            if (direction === 'left') {
                container.scrollLeft -= scrollAmount;
            } else {
                container.scrollLeft += scrollAmount;
            }
        }
        
        // Scroll to today's date
        function scrollToToday() {
            const todayIndex = <?php echo $todayIndex; ?>;
            if (todayIndex >= 0) {
                const container = document.getElementById('tableContainer');
                const todayColumn = document.getElementById('col-' + todayIndex);
                
                if (todayColumn && container) {
                    // Calculate the position to scroll to
                    const containerRect = container.getBoundingClientRect();
                    const columnRect = todayColumn.getBoundingClientRect();
                    const scrollPosition = columnRect.left - containerRect.left + container.scrollLeft - 50;
                    
                    // Scroll to the calculated position
                    container.scrollLeft = scrollPosition;
                }
            }
        }
        
        // Update scroll buttons state based on scroll position
        function updateScrollButtons() {
            const container = document.getElementById('tableContainer');
            const scrollLeftBtn = document.getElementById('scrollLeftBtn');
            const scrollRightBtn = document.getElementById('scrollRightBtn');
            
            // Disable left button if at the beginning
            scrollLeftBtn.disabled = container.scrollLeft <= 0;
            
            // Disable right button if at the end
            scrollRightBtn.disabled = container.scrollLeft >= container.scrollWidth - container.clientWidth;
        }
        
        // Edit expenditure function
        function editExpenditure(expenditureId) {
            // Fetch expenditure data via AJAX
            fetch('expenditure.php?edit=' + expenditureId)
                .then(response => response.json())
                .then(expenditureData => {
                    // Populate the form with existing expenditure data
                    document.getElementById('edit_expenditure_id').value = expenditureData.id;
                    document.getElementById('edit_stakeholder_id').value = expenditureData.stakeholder_id;
                    document.getElementById('edit_amount').value = expenditureData.amount;
                    document.getElementById('edit_category').value = expenditureData.category;
                    document.getElementById('edit_description').value = expenditureData.description;
                    document.getElementById('edit_expenditure_date').value = expenditureData.expenditure_date;
                    
                    // Open the modal
                    openModal('editExpenditureModal');
                })
                .catch(error => {
                    console.error('Error fetching expenditure data:', error);
                    alert('Error loading expenditure data. Please try again.');
                });
        }
        
        // Delete expenditure function
        function deleteExpenditure(expenditureId) {
            if (confirm('Are you sure you want to delete this expenditure record?')) {
                window.location.href = 'expenditure.php?delete=' + expenditureId;
            }
        }
        
        // Toggle round robin function
        function toggleRoundRobin(employeeId, enabled) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'expenditure.php';
            
            const input1 = document.createElement('input');
            input1.type = 'hidden';
            input1.name = 'toggle_round_robin';
            input1.value = '1';
            form.appendChild(input1);
            
            const input2 = document.createElement('input');
            input2.type = 'hidden';
            input2.name = 'employee_id';
            input2.value = employeeId;
            form.appendChild(input2);
            
            const input3 = document.createElement('input');
            input3.type = 'hidden';
            input3.name = 'enabled';
            input3.value = enabled;
            form.appendChild(input3);
            
            document.body.appendChild(form);
            form.submit();
        }
        
        // Mark missed turn function
        function markMissedTurn(employeeId) {
            document.getElementById('missed_employee_id').value = employeeId;
            document.getElementById('reason').value = '';
            openModal('missedTurnModal');
        }
        
        // Category chart
        document.addEventListener('DOMContentLoaded', function() {
            const ctx = document.getElementById('categoryChart').getContext('2d');
            const categoryChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: <?php echo json_encode(array_column($expenditure_by_category, 'category')); ?>,
                    datasets: [{
                        data: <?php echo json_encode(array_column($expenditure_by_category, 'total')); ?>,
                        backgroundColor: [
                            '#1a2a6c', '#2a5298', '#3b5998', '#4a69a5', 
                            '#5a79b2', '#6a89bf', '#7a99cc', '#8aa9d9'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
            
            // If we're editing an expenditure, open the edit modal with preloaded data
            <?php if ($edit_expenditure_data): ?>
                document.getElementById('edit_expenditure_id').value = <?php echo $edit_expenditure_data['id']; ?>;
                document.getElementById('edit_stakeholder_id').value = '<?php echo $edit_expenditure_data['stakeholder_id']; ?>';
                document.getElementById('edit_amount').value = '<?php echo $edit_expenditure_data['amount']; ?>';
                document.getElementById('edit_category').value = '<?php echo $edit_expenditure_data['category']; ?>';
                document.getElementById('edit_description').value = '<?php echo addslashes($edit_expenditure_data['description']); ?>';
                document.getElementById('edit_expenditure_date').value = '<?php echo $edit_expenditure_data['expenditure_date']; ?>';
                
                openModal('editExpenditureModal');
            <?php endif; ?>
            
            // Set today's date as default for new expenditure
            document.getElementById('expenditure_date').valueAsDate = new Date();
            
            // Add scroll event listener to update scroll buttons
            const tableContainer = document.getElementById('tableContainer');
            if (tableContainer) {
                tableContainer.addEventListener('scroll', updateScrollButtons);
                // Initial update of scroll buttons
                updateScrollButtons();
            }
        });
    </script>
</body>
</html>
