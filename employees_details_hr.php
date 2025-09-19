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
// Handle form submissions
$message = "";
$message_type = "";
// Handle employee update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_employee'])) {
    $employee_id = $_POST['employee_id'];
    
    // Basic information
    $name = $conn->real_escape_string($_POST['name']);
    $first_name = $conn->real_escape_string($_POST['first_name']);
    $middle_name = !empty($_POST['middle_name']) ? "'" . $conn->real_escape_string($_POST['middle_name']) . "'" : "NULL";
    $last_name = $conn->real_escape_string($_POST['last_name']);
    $date_of_birth = !empty($_POST['date_of_birth']) ? "'" . $_POST['date_of_birth'] . "'" : "NULL";
    $gender = !empty($_POST['gender']) ? "'" . $conn->real_escape_string($_POST['gender']) . "'" : "NULL";
    $nationality = !empty($_POST['nationality']) ? "'" . $conn->real_escape_string($_POST['nationality']) . "'" : "NULL";
    $marital_status = !empty($_POST['marital_status']) ? "'" . $conn->real_escape_string($_POST['marital_status']) . "'" : "NULL";
    
    // Contact information
    $phone_primary = !empty($_POST['phone_primary']) ? "'" . $conn->real_escape_string($_POST['phone_primary']) . "'" : "NULL";
    $phone_alternate = !empty($_POST['phone_alternate']) ? "'" . $conn->real_escape_string($_POST['phone_alternate']) . "'" : "NULL";
    $current_address = !empty($_POST['current_address']) ? "'" . $conn->real_escape_string($_POST['current_address']) . "'" : "NULL";
    $permanent_address = !empty($_POST['permanent_address']) ? "'" . $conn->real_escape_string($_POST['permanent_address']) . "'" : "NULL";
    $emergency_contact_name = !empty($_POST['emergency_contact_name']) ? "'" . $conn->real_escape_string($_POST['emergency_contact_name']) . "'" : "NULL";
    $emergency_contact_relationship = !empty($_POST['emergency_contact_relationship']) ? "'" . $conn->real_escape_string($_POST['emergency_contact_relationship']) . "'" : "NULL";
    $emergency_contact_phone = !empty($_POST['emergency_contact_phone']) ? "'" . $conn->real_escape_string($_POST['emergency_contact_phone']) . "'" : "NULL";
    $email = $conn->real_escape_string($_POST['email']);
    
    // Employment information
    $department = $conn->real_escape_string($_POST['department']);
    $employee_id_field = !empty($_POST['employee_id_field']) ? "'" . $conn->real_escape_string($_POST['employee_id_field']) . "'" : "NULL";
    $job_title = !empty($_POST['job_title']) ? "'" . $conn->real_escape_string($_POST['job_title']) . "'" : "NULL";
    $employment_type = !empty($_POST['employment_type']) ? "'" . $conn->real_escape_string($_POST['employment_type']) . "'" : "NULL";
    $reporting_manager = !empty($_POST['reporting_manager']) ? "'" . $conn->real_escape_string($_POST['reporting_manager']) . "'" : "NULL";
    $date_of_joining = !empty($_POST['date_of_joining']) ? "'" . $_POST['date_of_joining'] . "'" : "NULL";
    $work_location = !empty($_POST['work_location']) ? "'" . $conn->real_escape_string($_POST['work_location']) . "'" : "NULL";
    $employee_status = !empty($_POST['employee_status']) ? "'" . $conn->real_escape_string($_POST['employee_status']) . "'" : "'Active'";
    $employment_category = !empty($_POST['employment_category']) ? "'" . $conn->real_escape_string($_POST['employment_category']) . "'" : "NULL";
    
    // Compensation information
    $salary = !empty($_POST['salary']) ? $_POST['salary'] : "NULL";
    $pay_grade = !empty($_POST['pay_grade']) ? "'" . $conn->real_escape_string($_POST['pay_grade']) . "'" : "NULL";
    $pay_frequency = !empty($_POST['pay_frequency']) ? "'" . $conn->real_escape_string($_POST['pay_frequency']) . "'" : "NULL";
    $bank_name = !empty($_POST['bank_name']) ? "'" . $conn->real_escape_string($_POST['bank_name']) . "'" : "NULL";
    $bank_account_number = !empty($_POST['bank_account_number']) ? "'" . $conn->real_escape_string($_POST['bank_account_number']) . "'" : "NULL";
    $bank_ifsc_swift = !empty($_POST['bank_ifsc_swift']) ? "'" . $conn->real_escape_string($_POST['bank_ifsc_swift']) . "'" : "NULL";
    
    // Tax and statutory information
    $pan_tax_id = !empty($_POST['pan_tax_id']) ? "'" . $conn->real_escape_string($_POST['pan_tax_id']) . "'" : "NULL";
    $provident_fund_number = !empty($_POST['provident_fund_number']) ? "'" . $conn->real_escape_string($_POST['provident_fund_number']) . "'" : "NULL";
    $esic_number = !empty($_POST['esic_number']) ? "'" . $conn->real_escape_string($_POST['esic_number']) . "'" : "NULL";
    $uan_number = !empty($_POST['uan_number']) ? "'" . $conn->real_escape_string($_POST['uan_number']) . "'" : "NULL";
    
    // Education and experience
    $highest_qualification = !empty($_POST['highest_qualification']) ? "'" . $conn->real_escape_string($_POST['highest_qualification']) . "'" : "NULL";
    $university_institution = !empty($_POST['university_institution']) ? "'" . $conn->real_escape_string($_POST['university_institution']) . "'" : "NULL";
    $year_of_graduation = !empty($_POST['year_of_graduation']) ? "'" . $_POST['year_of_graduation'] . "'" : "NULL";
    $specialization_major = !empty($_POST['specialization_major']) ? "'" . $conn->real_escape_string($_POST['specialization_major']) . "'" : "NULL";
    $certifications = !empty($_POST['certifications']) ? "'" . $conn->real_escape_string($_POST['certifications']) . "'" : "NULL";
    $previous_employer_name = !empty($_POST['previous_employer_name']) ? "'" . $conn->real_escape_string($_POST['previous_employer_name']) . "'" : "NULL";
    $previous_job_title = !empty($_POST['previous_job_title']) ? "'" . $conn->real_escape_string($_POST['previous_job_title']) . "'" : "NULL";
    $previous_work_duration = !empty($_POST['previous_work_duration']) ? "'" . $conn->real_escape_string($_POST['previous_work_duration']) . "'" : "NULL";
    $reason_for_leaving = !empty($_POST['reason_for_leaving']) ? "'" . $conn->real_escape_string($_POST['reason_for_leaving']) . "'" : "NULL";
    
    // System access and roles
    $company_email_id = !empty($_POST['company_email_id']) ? "'" . $conn->real_escape_string($_POST['company_email_id']) . "'" : "NULL";
    $asset_tag_laptop_id = !empty($_POST['asset_tag_laptop_id']) ? "'" . $conn->real_escape_string($_POST['asset_tag_laptop_id']) . "'" : "NULL";
    $access_card_id = !empty($_POST['access_card_id']) ? "'" . $conn->real_escape_string($_POST['access_card_id']) . "'" : "NULL";
    $software_access_needed = !empty($_POST['software_access_needed']) ? "'" . $conn->real_escape_string($_POST['software_access_needed']) . "'" : "NULL";
    
    // Compliance and onboarding
    $nda_signed = isset($_POST['nda_signed']) ? 1 : 0;
    $code_of_conduct_acknowledged = isset($_POST['code_of_conduct_acknowledged']) ? 1 : 0;
    $policy_documents_acknowledged = isset($_POST['policy_documents_acknowledged']) ? 1 : 0;
    $medical_test_done = isset($_POST['medical_test_done']) ? 1 : 0;
    $probation_period_end_date = !empty($_POST['probation_period_end_date']) ? "'" . $_POST['probation_period_end_date'] . "'" : "NULL";
    $confirmation_status = !empty($_POST['confirmation_status']) ? "'" . $conn->real_escape_string($_POST['confirmation_status']) . "'" : "'Pending'";
    $employee_type = !empty($_POST['employee_type']) ? "'" . $conn->real_escape_string($_POST['employee_type']) . "'" : "'On-roll'";
    $referral_source = !empty($_POST['referral_source']) ? "'" . $conn->real_escape_string($_POST['referral_source']) . "'" : "NULL";
    $buddy_mentor_assigned = !empty($_POST['buddy_mentor_assigned']) ? "'" . $conn->real_escape_string($_POST['buddy_mentor_assigned']) . "'" : "NULL";
    $notes_remarks = !empty($_POST['notes_remarks']) ? "'" . $conn->real_escape_string($_POST['notes_remarks']) . "'" : "NULL";
    $detail_submitted = isset($_POST['detail_submitted']) ? 1 : 0;
    $unique_identifier = !empty($_POST['unique_identifier']) ? "'" . $conn->real_escape_string($_POST['unique_identifier']) . "'" : "NULL";
    $last_activity = !empty($_POST['last_activity']) ? "'" . $_POST['last_activity'] . "'" : "NULL";
    $designation = !empty($_POST['designation']) ? "'" . $conn->real_escape_string($_POST['designation']) . "'" : "NULL";
    $onboarding_notes = !empty($_POST['onboarding_notes']) ? "'" . $conn->real_escape_string($_POST['onboarding_notes']) . "'" : "NULL";
    
    // Update query - excluding valid_user field
    $update_sql = "UPDATE employees SET 
        name = '$name', 
        first_name = '$first_name', 
        middle_name = $middle_name, 
        last_name = '$last_name', 
        date_of_birth = $date_of_birth, 
        gender = $gender, 
        nationality = $nationality, 
        marital_status = $marital_status, 
        phone_primary = $phone_primary, 
        phone_alternate = $phone_alternate, 
        current_address = $current_address, 
        permanent_address = $permanent_address, 
        emergency_contact_name = $emergency_contact_name, 
        emergency_contact_relationship = $emergency_contact_relationship, 
        emergency_contact_phone = $emergency_contact_phone, 
        email = '$email', 
        department = '$department', 
        employee_id = $employee_id_field, 
        job_title = $job_title, 
        employment_type = $employment_type, 
        reporting_manager = $reporting_manager, 
        date_of_joining = $date_of_joining, 
        work_location = $work_location, 
        employee_status = $employee_status, 
        employment_category = $employment_category, 
        salary = $salary, 
        pay_grade = $pay_grade, 
        pay_frequency = $pay_frequency, 
        bank_name = $bank_name, 
        bank_account_number = $bank_account_number, 
        bank_ifsc_swift = $bank_ifsc_swift, 
        pan_tax_id = $pan_tax_id, 
        provident_fund_number = $provident_fund_number, 
        esic_number = $esic_number, 
        uan_number = $uan_number, 
        highest_qualification = $highest_qualification, 
        university_institution = $university_institution, 
        year_of_graduation = $year_of_graduation, 
        specialization_major = $specialization_major, 
        certifications = $certifications, 
        previous_employer_name = $previous_employer_name, 
        previous_job_title = $previous_job_title, 
        previous_work_duration = $previous_work_duration, 
        reason_for_leaving = $reason_for_leaving, 
        company_email_id = $company_email_id, 
        asset_tag_laptop_id = $asset_tag_laptop_id, 
        access_card_id = $access_card_id, 
        software_access_needed = $software_access_needed, 
        nda_signed = $nda_signed, 
        code_of_conduct_acknowledged = $code_of_conduct_acknowledged, 
        policy_documents_acknowledged = $policy_documents_acknowledged, 
        medical_test_done = $medical_test_done, 
        probation_period_end_date = $probation_period_end_date, 
        confirmation_status = $confirmation_status, 
        employee_type = $employee_type, 
        referral_source = $referral_source, 
        buddy_mentor_assigned = $buddy_mentor_assigned, 
        notes_remarks = $notes_remarks, 
        detail_submitted = $detail_submitted, 
        unique_identifier = $unique_identifier, 
        last_activity = $last_activity, 
        designation = $designation, 
        onboarding_notes = $onboarding_notes 
        WHERE id = $employee_id";
    
    if ($conn->query($update_sql)) {
        $message = "Employee information updated successfully!";
        $message_type = "success";
    } else {
        $message = "Error updating employee information: " . $conn->error;
        $message_type = "error";
    }
}
// Fetch employee data if ID is provided
$employee_data = null;
if (isset($_GET['id']) && !empty($_GET['id'])) {
    $employee_id = $_GET['id'];
    $emp_sql = "SELECT * FROM employees WHERE id = ?";
    $stmt = $conn->prepare($emp_sql);
    $stmt->bind_param("i", $employee_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $employee_data = $result->fetch_assoc();
    }
    
    $stmt->close();
}
// Fetch all employees for dropdown
$employees = array();
$emp_sql = "SELECT id, name, email, department FROM employees WHERE valid_user = TRUE";
$emp_result = $conn->query($emp_sql);
if ($emp_result->num_rows > 0) {
    while ($row = $emp_result->fetch_assoc()) {
        $employees[] = $row;
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Details - HR Management System</title>
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
        
        .employee-selector {
            margin-bottom: 30px;
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.08);
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
        
        .readonly {
            background-color: #f8f9fa;
            cursor: not-allowed;
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
        
        .employee-form {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.08);
        }
        
        .form-tabs {
            display: flex;
            gap: 5px;
            margin-bottom: 20px;
            border-bottom: 1px solid #eee;
            flex-wrap: wrap;
        }
        
        .form-tab {
            padding: 10px 20px;
            cursor: pointer;
            border-radius: 5px 5px 0 0;
            transition: all 0.3s;
            background: #f8f9fa;
            color: #666;
        }
        
        .form-tab.active {
            background: #1a2a6c;
            color: white;
        }
        
        .form-tab-content {
            display: none;
        }
        
        .form-tab-content.active {
            display: block;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .form-group.full-width {
            grid-column: 1 / -1;
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
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
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
            
            .form-actions {
                flex-direction: column;
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
                    <li><a href="ask_hr.php"><i class="fas fa-headset"></i> ASK HR Tickets</a></li>
                    <li><a href="employees_details_hr.php" class="active"><i class="fas fa-user-edit"></i> Employee Details</a></li>
                </ul>
            </div>
            
            <div class="main-content">
                <h2 class="section-title"><i class="fas fa-user-edit"></i> Employee Details Management</h2>
                
                <div class="employee-selector">
                    <h3 class="form-title">Select Employee</h3>
                    <form method="GET" action="employees_details_hr.php">
                        <div class="form-group">
                            <label for="employee_select">Choose an employee to edit:</label>
                            <select id="employee_select" name="id" class="form-control" onchange="this.form.submit()">
                                <option value="">-- Select Employee --</option>
                                <?php foreach ($employees as $employee): ?>
                                    <option value="<?php echo $employee['id']; ?>" 
                                        <?php echo (isset($employee_data) && $employee_data['id'] == $employee['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($employee['name'] . ' (' . $employee['department'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </form>
                </div>
                
                <?php if ($employee_data): ?>
                <form method="POST" id="employee-form">
                    <input type="hidden" name="employee_id" value="<?php echo $employee_data['id']; ?>">
                    
                    <div class="employee-form">
                        <div class="form-tabs">
                            <div class="form-tab active" data-tab="personal">Personal Information</div>
                            <div class="form-tab" data-tab="contact">Contact Details</div>
                            <div class="form-tab" data-tab="employment">Employment</div>
                            <div class="form-tab" data-tab="compensation">Compensation</div>
                            <div class="form-tab" data-tab="education">Education</div>
                            <div class="form-tab" data-tab="system">System Access</div>
                            <div class="form-tab" data-tab="compliance">Compliance</div>
                        </div>
                        
                        <!-- Personal Information Tab -->
                        <div class="form-tab-content active" id="personal-tab">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="name">Full Name</label>
                                    <input type="text" id="name" name="name" class="form-control" 
                                           value="<?php echo htmlspecialchars($employee_data['name']); ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="first_name">First Name</label>
                                    <input type="text" id="first_name" name="first_name" class="form-control" 
                                           value="<?php echo htmlspecialchars($employee_data['first_name']); ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="middle_name">Middle Name</label>
                                    <input type="text" id="middle_name" name="middle_name" class="form-control" 
                                           value="<?php echo htmlspecialchars($employee_data['middle_name']); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="last_name">Last Name</label>
                                    <input type="text" id="last_name" name="last_name" class="form-control" 
                                           value="<?php echo htmlspecialchars($employee_data['last_name']); ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="date_of_birth">Date of Birth</label>
                                    <input type="date" id="date_of_birth" name="date_of_birth" class="form-control" 
                                           value="<?php echo $employee_data['date_of_birth']; ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="gender">Gender</label>
                                    <select id="gender" name="gender" class="form-control">
                                        <option value="">-- Select --</option>
                                        <option value="Male" <?php echo $employee_data['gender'] == 'Male' ? 'selected' : ''; ?>>Male</option>
                                        <option value="Female" <?php echo $employee_data['gender'] == 'Female' ? 'selected' : ''; ?>>Female</option>
                                        <option value="Other" <?php echo $employee_data['gender'] == 'Other' ? 'selected' : ''; ?>>Other</option>
                                        <option value="Prefer not to say" <?php echo $employee_data['gender'] == 'Prefer not to say' ? 'selected' : ''; ?>>Prefer not to say</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="nationality">Nationality</label>
                                    <input type="text" id="nationality" name="nationality" class="form-control" 
                                           value="<?php echo htmlspecialchars($employee_data['nationality']); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="marital_status">Marital Status</label>
                                    <select id="marital_status" name="marital_status" class="form-control">
                                        <option value="">-- Select --</option>
                                        <option value="Single" <?php echo $employee_data['marital_status'] == 'Single' ? 'selected' : ''; ?>>Single</option>
                                        <option value="Married" <?php echo $employee_data['marital_status'] == 'Married' ? 'selected' : ''; ?>>Married</option>
                                        <option value="Divorced" <?php echo $employee_data['marital_status'] == 'Divorced' ? 'selected' : ''; ?>>Divorced</option>
                                        <option value="Widowed" <?php echo $employee_data['marital_status'] == 'Widowed' ? 'selected' : ''; ?>>Widowed</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Contact Details Tab -->
                        <div class="form-tab-content" id="contact-tab">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="phone_primary">Primary Phone</label>
                                    <input type="text" id="phone_primary" name="phone_primary" class="form-control" 
                                           value="<?php echo htmlspecialchars($employee_data['phone_primary']); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="phone_alternate">Alternate Phone</label>
                                    <input type="text" id="phone_alternate" name="phone_alternate" class="form-control" 
                                           value="<?php echo htmlspecialchars($employee_data['phone_alternate']); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="email">Email Address</label>
                                    <input type="email" id="email" name="email" class="form-control readonly" 
                                           value="<?php echo htmlspecialchars($employee_data['email']); ?>" readonly>
                                </div>
                                
                                <div class="form-group">
                                    <label for="company_email_id">Company Email</label>
                                    <input type="email" id="company_email_id" name="company_email_id" class="form-control readonly" 
                                           value="<?php echo htmlspecialchars($employee_data['company_email_id']); ?>" readonly>
                                </div>
                                
                                <div class="form-group full-width">
                                    <label for="current_address">Current Address</label>
                                    <textarea id="current_address" name="current_address" class="form-control" rows="3"><?php echo htmlspecialchars($employee_data['current_address']); ?></textarea>
                                </div>
                                
                                <div class="form-group full-width">
                                    <label for="permanent_address">Permanent Address</label>
                                    <textarea id="permanent_address" name="permanent_address" class="form-control" rows="3"><?php echo htmlspecialchars($employee_data['permanent_address']); ?></textarea>
                                </div>
                                
                                <div class="form-group">
                                    <label for="emergency_contact_name">Emergency Contact Name</label>
                                    <input type="text" id="emergency_contact_name" name="emergency_contact_name" class="form-control" 
                                           value="<?php echo htmlspecialchars($employee_data['emergency_contact_name']); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="emergency_contact_relationship">Relationship</label>
                                    <input type="text" id="emergency_contact_relationship" name="emergency_contact_relationship" class="form-control" 
                                           value="<?php echo htmlspecialchars($employee_data['emergency_contact_relationship']); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="emergency_contact_phone">Emergency Contact Phone</label>
                                    <input type="text" id="emergency_contact_phone" name="emergency_contact_phone" class="form-control" 
                                           value="<?php echo htmlspecialchars($employee_data['emergency_contact_phone']); ?>">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Employment Tab -->
                        <div class="form-tab-content" id="employment-tab">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="department">Department</label>
                                    <input type="text" id="department" name="department" class="form-control readonly" 
                                           value="<?php echo htmlspecialchars($employee_data['department']); ?>" readonly>
                                </div>
                                
                                <div class="form-group">
                                    <label for="employee_id_field">Employee ID</label>
                                    <input type="text" id="employee_id_field" name="employee_id_field" class="form-control readonly" 
                                           value="<?php echo htmlspecialchars($employee_data['employee_id']); ?>" readonly>
                                </div>
                                
                                <div class="form-group">
                                    <label for="job_title">Job Title</label>
                                    <input type="text" id="job_title" name="job_title" class="form-control readonly" 
                                           value="<?php echo htmlspecialchars($employee_data['job_title']); ?>" readonly>
                                </div>
                                
                                <div class="form-group">
                                    <label for="designation">Designation</label>
                                    <input type="text" id="designation" name="designation" class="form-control" 
                                           value="<?php echo htmlspecialchars($employee_data['designation']); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="employment_type">Employment Type</label>
                                    <select id="employment_type" name="employment_type" class="form-control">
                                        <option value="">-- Select --</option>
                                        <option value="Full-time" <?php echo $employee_data['employment_type'] == 'Full-time' ? 'selected' : ''; ?>>Full-time</option>
                                        <option value="Part-time" <?php echo $employee_data['employment_type'] == 'Part-time' ? 'selected' : ''; ?>>Part-time</option>
                                        <option value="Contract" <?php echo $employee_data['employment_type'] == 'Contract' ? 'selected' : ''; ?>>Contract</option>
                                        <option value="Intern" <?php echo $employee_data['employment_type'] == 'Intern' ? 'selected' : ''; ?>>Intern</option>
                                        <option value="Consultant" <?php echo $employee_data['employment_type'] == 'Consultant' ? 'selected' : ''; ?>>Consultant</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="employee_type">Employee Type</label>
                                    <select id="employee_type" name="employee_type" class="form-control">
                                        <option value="On-roll" <?php echo $employee_data['employee_type'] == 'On-roll' ? 'selected' : ''; ?>>On-roll</option>
                                        <option value="Off-roll" <?php echo $employee_data['employee_type'] == 'Off-roll' ? 'selected' : ''; ?>>Off-roll</option>
                                        <option value="Contract" <?php echo $employee_data['employee_type'] == 'Contract' ? 'selected' : ''; ?>>Contract</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="reporting_manager">Reporting Manager</label>
                                    <input type="text" id="reporting_manager" name="reporting_manager" class="form-control" 
                                           value="<?php echo htmlspecialchars($employee_data['reporting_manager']); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="date_of_joining">Date of Joining</label>
                                    <input type="date" id="date_of_joining" name="date_of_joining" class="form-control" 
                                           value="<?php echo $employee_data['date_of_joining']; ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="work_location">Work Location</label>
                                    <input type="text" id="work_location" name="work_location" class="form-control" 
                                           value="<?php echo htmlspecialchars($employee_data['work_location']); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="employee_status">Employee Status</label>
                                    <select id="employee_status" name="employee_status" class="form-control">
                                        <option value="Active" <?php echo $employee_data['employee_status'] == 'Active' ? 'selected' : ''; ?>>Active</option>
                                        <option value="Inactive" <?php echo $employee_data['employee_status'] == 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                                        <option value="On Leave" <?php echo $employee_data['employee_status'] == 'On Leave' ? 'selected' : ''; ?>>On Leave</option>
                                        <option value="Terminated" <?php echo $employee_data['employee_status'] == 'Terminated' ? 'selected' : ''; ?>>Terminated</option>
                                        <option value="Resigned" <?php echo $employee_data['employee_status'] == 'Resigned' ? 'selected' : ''; ?>>Resigned</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="employment_category">Employment Category</label>
                                    <input type="text" id="employment_category" name="employment_category" class="form-control" 
                                           value="<?php echo htmlspecialchars($employee_data['employment_category']); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="probation_period_end_date">Probation End Date</label>
                                    <input type="date" id="probation_period_end_date" name="probation_period_end_date" class="form-control" 
                                           value="<?php echo $employee_data['probation_period_end_date']; ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="confirmation_status">Confirmation Status</label>
                                    <select id="confirmation_status" name="confirmation_status" class="form-control">
                                        <option value="Pending" <?php echo $employee_data['confirmation_status'] == 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                        <option value="Confirmed" <?php echo $employee_data['confirmation_status'] == 'Confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                        <option value="Extended" <?php echo $employee_data['confirmation_status'] == 'Extended' ? 'selected' : ''; ?>>Extended</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="referral_source">Referral Source</label>
                                    <input type="text" id="referral_source" name="referral_source" class="form-control" 
                                           value="<?php echo htmlspecialchars($employee_data['referral_source']); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="buddy_mentor_assigned">Buddy/Mentor Assigned</label>
                                    <input type="text" id="buddy_mentor_assigned" name="buddy_mentor_assigned" class="form-control" 
                                           value="<?php echo htmlspecialchars($employee_data['buddy_mentor_assigned']); ?>">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Compensation Tab -->
                        <div class="form-tab-content" id="compensation-tab">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="salary">Salary</label>
                                    <input type="number" id="salary" name="salary" class="form-control" step="0.01" 
                                           value="<?php echo htmlspecialchars($employee_data['salary']); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="pay_grade">Pay Grade</label>
                                    <input type="text" id="pay_grade" name="pay_grade" class="form-control" 
                                           value="<?php echo htmlspecialchars($employee_data['pay_grade']); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="pay_frequency">Pay Frequency</label>
                                    <select id="pay_frequency" name="pay_frequency" class="form-control">
                                        <option value="">-- Select --</option>
                                        <option value="Monthly" <?php echo $employee_data['pay_frequency'] == 'Monthly' ? 'selected' : ''; ?>>Monthly</option>
                                        <option value="Bi-weekly" <?php echo $employee_data['pay_frequency'] == 'Bi-weekly' ? 'selected' : ''; ?>>Bi-weekly</option>
                                        <option value="Weekly" <?php echo $employee_data['pay_frequency'] == 'Weekly' ? 'selected' : ''; ?>>Weekly</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="bank_name">Bank Name</label>
                                    <input type="text" id="bank_name" name="bank_name" class="form-control" 
                                           value="<?php echo htmlspecialchars($employee_data['bank_name']); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="bank_account_number">Bank Account Number</label>
                                    <input type="text" id="bank_account_number" name="bank_account_number" class="form-control" 
                                           value="<?php echo htmlspecialchars($employee_data['bank_account_number']); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="bank_ifsc_swift">Bank IFSC/SWIFT</label>
                                    <input type="text" id="bank_ifsc_swift" name="bank_ifsc_swift" class="form-control" 
                                           value="<?php echo htmlspecialchars($employee_data['bank_ifsc_swift']); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="pan_tax_id">PAN/Tax ID</label>
                                    <input type="text" id="pan_tax_id" name="pan_tax_id" class="form-control" 
                                           value="<?php echo htmlspecialchars($employee_data['pan_tax_id']); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="provident_fund_number">Provident Fund Number</label>
                                    <input type="text" id="provident_fund_number" name="provident_fund_number" class="form-control" 
                                           value="<?php echo htmlspecialchars($employee_data['provident_fund_number']); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="esic_number">ESIC Number</label>
                                    <input type="text" id="esic_number" name="esic_number" class="form-control" 
                                           value="<?php echo htmlspecialchars($employee_data['esic_number']); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="uan_number">UAN Number</label>
                                    <input type="text" id="uan_number" name="uan_number" class="form-control" 
                                           value="<?php echo htmlspecialchars($employee_data['uan_number']); ?>">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Education Tab -->
                        <div class="form-tab-content" id="education-tab">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="highest_qualification">Highest Qualification</label>
                                    <input type="text" id="highest_qualification" name="highest_qualification" class="form-control" 
                                           value="<?php echo htmlspecialchars($employee_data['highest_qualification']); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="university_institution">University/Institution</label>
                                    <input type="text" id="university_institution" name="university_institution" class="form-control" 
                                           value="<?php echo htmlspecialchars($employee_data['university_institution']); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="year_of_graduation">Year of Graduation</label>
                                    <input type="number" id="year_of_graduation" name="year_of_graduation" class="form-control" 
                                           value="<?php echo htmlspecialchars($employee_data['year_of_graduation']); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="specialization_major">Specialization/Major</label>
                                    <input type="text" id="specialization_major" name="specialization_major" class="form-control" 
                                           value="<?php echo htmlspecialchars($employee_data['specialization_major']); ?>">
                                </div>
                                
                                <div class="form-group full-width">
                                    <label for="certifications">Certifications</label>
                                    <textarea id="certifications" name="certifications" class="form-control" rows="3"><?php echo htmlspecialchars($employee_data['certifications']); ?></textarea>
                                </div>
                                
                                <div class="form-group">
                                    <label for="previous_employer_name">Previous Employer</label>
                                    <input type="text" id="previous_employer_name" name="previous_employer_name" class="form-control" 
                                           value="<?php echo htmlspecialchars($employee_data['previous_employer_name']); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="previous_job_title">Previous Job Title</label>
                                    <input type="text" id="previous_job_title" name="previous_job_title" class="form-control" 
                                           value="<?php echo htmlspecialchars($employee_data['previous_job_title']); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="previous_work_duration">Previous Work Duration</label>
                                    <input type="text" id="previous_work_duration" name="previous_work_duration" class="form-control" 
                                           value="<?php echo htmlspecialchars($employee_data['previous_work_duration']); ?>">
                                </div>
                                
                                <div class="form-group full-width">
                                    <label for="reason_for_leaving">Reason for Leaving Previous Job</label>
                                    <textarea id="reason_for_leaving" name="reason_for_leaving" class="form-control" rows="3"><?php echo htmlspecialchars($employee_data['reason_for_leaving']); ?></textarea>
                                </div>
                            </div>
                        </div>
                        
                        <!-- System Access Tab -->
                        <div class="form-tab-content" id="system-tab">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="unique_identifier">Unique Identifier</label>
                                    <input type="text" id="unique_identifier" name="unique_identifier" class="form-control readonly" 
                                           value="<?php echo htmlspecialchars($employee_data['unique_identifier']); ?>" readonly>
                                </div>
                                
                                <div class="form-group">
                                    <label for="asset_tag_laptop_id">Asset Tag/Laptop ID</label>
                                    <input type="text" id="asset_tag_laptop_id" name="asset_tag_laptop_id" class="form-control" 
                                           value="<?php echo htmlspecialchars($employee_data['asset_tag_laptop_id']); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="access_card_id">Access Card ID</label>
                                    <input type="text" id="access_card_id" name="access_card_id" class="form-control" 
                                           value="<?php echo htmlspecialchars($employee_data['access_card_id']); ?>">
                                </div>
                                
                                <div class="form-group full-width">
                                    <label for="software_access_needed">Software Access Needed</label>
                                    <textarea id="software_access_needed" name="software_access_needed" class="form-control" rows="3"><?php echo htmlspecialchars($employee_data['software_access_needed']); ?></textarea>
                                </div>
                                
                                <div class="form-group">
                                    <label>System Roles (Read Only)</label>
                                    <div class="checkbox-group">
                                        <input type="checkbox" id="is_hr" name="is_hr" value="1" 
                                               <?php echo $employee_data['is_hr'] ? 'checked' : ''; ?> disabled>
                                        <label for="is_hr">HR Access</label>
                                    </div>
                                    
                                    <div class="checkbox-group">
                                        <input type="checkbox" id="is_admin" name="is_admin" value="1" 
                                               <?php echo $employee_data['is_admin'] ? 'checked' : ''; ?> disabled>
                                        <label for="is_admin">Admin Access</label>
                                    </div>
                                    
                                    <div class="checkbox-group">
                                        <input type="checkbox" id="is_manager" name="is_manager" value="1" 
                                               <?php echo $employee_data['is_manager'] ? 'checked' : ''; ?> disabled>
                                        <label for="is_manager">Manager Access</label>
                                    </div>
                                    
                                    <div class="checkbox-group">
                                        <input type="checkbox" id="is_director" name="is_director" value="1" 
                                               <?php echo $employee_data['is_director'] ? 'checked' : ''; ?> disabled>
                                        <label for="is_director">Director Access</label>
                                    </div>
                                    
                                    <div class="checkbox-group">
                                        <input type="checkbox" id="is_ca" name="is_ca" value="1" 
                                               <?php echo $employee_data['is_ca'] ? 'checked' : ''; ?> disabled>
                                        <label for="is_ca">CA Access</label>
                                    </div>
                                    
                                    <div class="checkbox-group">
                                        <input type="checkbox" id="valid_user" name="valid_user" value="1" 
                                               <?php echo $employee_data['valid_user'] ? 'checked' : ''; ?> disabled>
                                        <label for="valid_user">Valid User</label>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="last_activity">Last Activity</label>
                                    <input type="datetime-local" id="last_activity" name="last_activity" class="form-control" 
                                           value="<?php echo $employee_data['last_activity']; ?>">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Compliance Tab -->
                        <div class="form-tab-content" id="compliance-tab">
                            <div class="form-grid">
                                <div class="checkbox-group">
                                    <input type="checkbox" id="nda_signed" name="nda_signed" value="1" 
                                           <?php echo $employee_data['nda_signed'] ? 'checked' : ''; ?>>
                                    <label for="nda_signed">NDA Signed</label>
                                </div>
                                
                                <div class="checkbox-group">
                                    <input type="checkbox" id="code_of_conduct_acknowledged" name="code_of_conduct_acknowledged" value="1" 
                                           <?php echo $employee_data['code_of_conduct_acknowledged'] ? 'checked' : ''; ?>>
                                    <label for="code_of_conduct_acknowledged">Code of Conduct Acknowledged</label>
                                </div>
                                
                                <div class="checkbox-group">
                                    <input type="checkbox" id="policy_documents_acknowledged" name="policy_documents_acknowledged" value="1" 
                                           <?php echo $employee_data['policy_documents_acknowledged'] ? 'checked' : ''; ?>>
                                    <label for="policy_documents_acknowledged">Policy Documents Acknowledged</label>
                                </div>
                                
                                <div class="checkbox-group">
                                    <input type="checkbox" id="medical_test_done" name="medical_test_done" value="1" 
                                           <?php echo $employee_data['medical_test_done'] ? 'checked' : ''; ?>>
                                    <label for="medical_test_done">Medical Test Done</label>
                                </div>
                                
                                <div class="checkbox-group">
                                    <input type="checkbox" id="detail_submitted" name="detail_submitted" value="1" 
                                           <?php echo $employee_data['detail_submitted'] ? 'checked' : ''; ?>>
                                    <label for="detail_submitted">Details Submitted</label>
                                </div>
                                
                                <div class="form-group full-width">
                                    <label for="onboarding_notes">Onboarding Notes</label>
                                    <textarea id="onboarding_notes" name="onboarding_notes" class="form-control" rows="3"><?php echo htmlspecialchars($employee_data['onboarding_notes']); ?></textarea>
                                </div>
                                
                                <div class="form-group full-width">
                                    <label for="notes_remarks">Notes/Remarks</label>
                                    <textarea id="notes_remarks" name="notes_remarks" class="form-control" rows="3"><?php echo htmlspecialchars($employee_data['notes_remarks']); ?></textarea>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" name="update_employee" class="btn"><i class="fas fa-save"></i> Update Employee</button>
                            <a href="employees_details_hr.php" class="btn btn-secondary"><i class="fas fa-times"></i> Cancel</a>
                        </div>
                    </div>
                </form>
                <?php else: ?>
                <div class="employee-form">
                    <p style="text-align: center; padding: 30px; color: #666;">Please select an employee to view and edit their details.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Tab functionality
            const tabs = document.querySelectorAll('.form-tab');
            const tabContents = document.querySelectorAll('.form-tab-content');
            
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
        });
    </script>
</body>
</html>