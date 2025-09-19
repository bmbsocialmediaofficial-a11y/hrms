<?php
session_start();
// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Include the configuration file
require_once 'config.php';

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

// Check if details are submitted (read-only mode)
$details_submitted = isset($employee['detail_submitted']) && $employee['detail_submitted'] == 1;

// Handle form submission only if not in read-only mode
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_details']) && !$details_submitted) {
    // Personal Information
    $first_name = $_POST['first_name'];
    $middle_name = $_POST['middle_name'];
    $last_name = $_POST['last_name'];
    $date_of_birth = $_POST['date_of_birth'];
    $gender = $_POST['gender'];
    $nationality = $_POST['nationality'];
    $marital_status = $_POST['marital_status'];
    $government_id_type = $_POST['government_id_type'];
    $government_id_number = $_POST['government_id_number'];
    
    // Contact Information
    $phone_primary = $_POST['phone_primary'];
    $phone_alternate = $_POST['phone_alternate'];
    $current_address = $_POST['current_address'];
    $permanent_address = $_POST['permanent_address'];
    $emergency_contact_name = $_POST['emergency_contact_name'];
    $emergency_contact_relationship = $_POST['emergency_contact_relationship'];
    $emergency_contact_phone = $_POST['emergency_contact_phone'];
    
    // Employment Details
    $employee_id_field = $_POST['employee_id'];
    $job_title = $_POST['job_title'];
    $employment_type = $_POST['employment_type'];
    $reporting_manager = $_POST['reporting_manager'];
    $date_of_joining = $_POST['date_of_joining'];
    $work_location = $_POST['work_location'];
    $employee_status = $_POST['employee_status'];
    $employment_category = $_POST['employment_category'];
    
    // Compensation & Payroll
    $salary = $_POST['salary'];
    $pay_grade = $_POST['pay_grade'];
    $pay_frequency = $_POST['pay_frequency'];
    $bank_name = $_POST['bank_name'];
    $bank_account_number = $_POST['bank_account_number'];
    $bank_ifsc_swift = $_POST['bank_ifsc_swift'];
    $pan_tax_id = $_POST['pan_tax_id'];
    $provident_fund_number = $_POST['provident_fund_number'];
    $esic_number = $_POST['esic_number'];
    $uan_number = $_POST['uan_number'];
    
    // Education & Qualifications
    $highest_qualification = $_POST['highest_qualification'];
    $university_institution = $_POST['university_institution'];
    $year_of_graduation = $_POST['year_of_graduation'];
    $specialization_major = $_POST['specialization_major'];
    $certifications = $_POST['certifications'];
    
    // Work Experience
    $previous_employer_name = $_POST['previous_employer_name'];
    $previous_job_title = $_POST['previous_job_title'];
    $previous_work_duration = $_POST['previous_work_duration'];
    $reason_for_leaving = $_POST['reason_for_leaving'];
    
    // HR / Compliance
    $nda_signed = isset($_POST['nda_signed']) ? 1 : 0;
    $code_of_conduct_acknowledged = isset($_POST['code_of_conduct_acknowledged']) ? 1 : 0;
    $policy_documents_acknowledged = isset($_POST['policy_documents_acknowledged']) ? 1 : 0;
    $medical_test_done = isset($_POST['medical_test_done']) ? 1 : 0;
    $probation_period_end_date = $_POST['probation_period_end_date'];
    $confirmation_status = $_POST['confirmation_status'];
    
    // Miscellaneous
    $employee_type = $_POST['employee_type'];
    $referral_source = $_POST['referral_source'];
    $buddy_mentor_assigned = $_POST['buddy_mentor_assigned'];
    $notes_remarks = $_POST['notes_remarks'];
    
    // Handle file uploads
    $upload_dir = "uploads/employee_documents/$employee_id/";
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $photo_path = $employee['photo'];
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] == 0) {
        $photo_path = $upload_dir . "photo_" . time() . "_" . basename($_FILES['photo']['name']);
        move_uploaded_file($_FILES['photo']['tmp_name'], $photo_path);
    }
    
    $resume_path = $employee['resume_path'];
    if (isset($_FILES['resume']) && $_FILES['resume']['error'] == 0) {
        $resume_path = $upload_dir . "resume_" . time() . "_" . basename($_FILES['resume']['name']);
        move_uploaded_file($_FILES['resume']['tmp_name'], $resume_path);
    }
    
    $offer_letter_path = $employee['offer_letter_path'];
    if (isset($_FILES['offer_letter']) && $_FILES['offer_letter']['error'] == 0) {
        $offer_letter_path = $upload_dir . "offer_letter_" . time() . "_" . basename($_FILES['offer_letter']['name']);
        move_uploaded_file($_FILES['offer_letter']['tmp_name'], $offer_letter_path);
    }
    
    $joining_letter_path = $employee['joining_letter_path'];
    if (isset($_FILES['joining_letter']) && $_FILES['joining_letter']['error'] == 0) {
        $joining_letter_path = $upload_dir . "joining_letter_" . time() . "_" . basename($_FILES['joining_letter']['name']);
        move_uploaded_file($_FILES['joining_letter']['tmp_name'], $joining_letter_path);
    }
    
    $id_proof_path = $employee['id_proof_path'];
    if (isset($_FILES['id_proof']) && $_FILES['id_proof']['error'] == 0) {
        $id_proof_path = $upload_dir . "id_proof_" . time() . "_" . basename($_FILES['id_proof']['name']);
        move_uploaded_file($_FILES['id_proof']['tmp_name'], $id_proof_path);
    }
    
    $address_proof_path = $employee['address_proof_path'];
    if (isset($_FILES['address_proof']) && $_FILES['address_proof']['error'] == 0) {
        $address_proof_path = $upload_dir . "address_proof_" . time() . "_" . basename($_FILES['address_proof']['name']);
        move_uploaded_file($_FILES['address_proof']['tmp_name'], $address_proof_path);
    }
    
    $education_certificates_path = $employee['education_certificates_path'];
    if (isset($_FILES['education_certificates']) && $_FILES['education_certificates']['error'] == 0) {
        $education_certificates_path = $upload_dir . "education_certificates_" . time() . "_" . basename($_FILES['education_certificates']['name']);
        move_uploaded_file($_FILES['education_certificates']['tmp_name'], $education_certificates_path);
    }
    
    $experience_letters_path = $employee['experience_letters_path'];
    if (isset($_FILES['experience_letters']) && $_FILES['experience_letters']['error'] == 0) {
        $experience_letters_path = $upload_dir . "experience_letters_" . time() . "_" . basename($_FILES['experience_letters']['name']);
        move_uploaded_file($_FILES['experience_letters']['tmp_name'], $experience_letters_path);
    }
    
    // Update employee details
    $update_query = "UPDATE employees SET 
        first_name = ?, middle_name = ?, last_name = ?, date_of_birth = ?, gender = ?, nationality = ?, 
        marital_status = ?, photo = ?, government_id_type = ?, government_id_number = ?,
        phone_primary = ?, phone_alternate = ?, current_address = ?, permanent_address = ?, 
        emergency_contact_name = ?, emergency_contact_relationship = ?, emergency_contact_phone = ?,
        employee_id = ?, job_title = ?, employment_type = ?, reporting_manager = ?, date_of_joining = ?, 
        work_location = ?, employee_status = ?, employment_category = ?,
        salary = ?, pay_grade = ?, pay_frequency = ?, bank_name = ?, bank_account_number = ?, 
        bank_ifsc_swift = ?, pan_tax_id = ?, provident_fund_number = ?, esic_number = ?, uan_number = ?,
        highest_qualification = ?, university_institution = ?, year_of_graduation = ?, 
        specialization_major = ?, certifications = ?,
        previous_employer_name = ?, previous_job_title = ?, previous_work_duration = ?, reason_for_leaving = ?,
        resume_path = ?, offer_letter_path = ?, joining_letter_path = ?, id_proof_path = ?, 
        address_proof_path = ?, education_certificates_path = ?, experience_letters_path = ?,
        nda_signed = ?, code_of_conduct_acknowledged = ?, policy_documents_acknowledged = ?, 
        medical_test_done = ?, probation_period_end_date = ?, confirmation_status = ?,
        employee_type = ?, referral_source = ?, buddy_mentor_assigned = ?, notes_remarks = ?
        WHERE id = ?";
    
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bind_param("ssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssi", 
        $first_name, $middle_name, $last_name, $date_of_birth, $gender, $nationality, 
        $marital_status, $photo_path, $government_id_type, $government_id_number,
        $phone_primary, $phone_alternate, $current_address, $permanent_address, 
        $emergency_contact_name, $emergency_contact_relationship, $emergency_contact_phone,
        $employee_id_field, $job_title, $employment_type, $reporting_manager, $date_of_joining, 
        $work_location, $employee_status, $employment_category,
        $salary, $pay_grade, $pay_frequency, $bank_name, $bank_account_number, 
        $bank_ifsc_swift, $pan_tax_id, $provident_fund_number, $esic_number, $uan_number,
        $highest_qualification, $university_institution, $year_of_graduation, 
        $specialization_major, $certifications,
        $previous_employer_name, $previous_job_title, $previous_work_duration, $reason_for_leaving,
        $resume_path, $offer_letter_path, $joining_letter_path, $id_proof_path, 
        $address_proof_path, $education_certificates_path, $experience_letters_path,
        $nda_signed, $code_of_conduct_acknowledged, $policy_documents_acknowledged, 
        $medical_test_done, $probation_period_end_date, $confirmation_status,
        $employee_type, $referral_source, $buddy_mentor_assigned, $notes_remarks,
        $employee_id);
    
    if ($update_stmt->execute()) {
        $success_message = "Employee details updated successfully!";
        
        // Refresh employee data
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $employee_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $employee = $result->fetch_assoc();
        
        // Update the details_submitted flag
        $details_submitted = isset($employee['detail_submitted']) && $employee['detail_submitted'] == 1;
    } else {
        $error_message = "Error updating employee details. Please try again.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Details - Buymeabook</title>
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
        
        /* Style for readonly fields */
        input[readonly], select[disabled], textarea[readonly] {
            background: rgba(255, 255, 255, 0.05);
            cursor: not-allowed;
            opacity: 0.7;
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
        
        .info {
            background: rgba(52, 152, 219, 0.2);
            border: 1px solid rgba(52, 152, 219, 0.5);
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
        
        /* Form Sections */
        .form-section {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .form-row {
            display: flex;
            flex-wrap: wrap;
            margin: 0 -10px;
        }
        
        .form-col {
            flex: 1;
            min-width: 250px;
            padding: 0 10px;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .checkbox-group input {
            width: auto;
            margin-right: 10px;
        }
        
        .checkbox-group input[disabled] {
            cursor: not-allowed;
        }
        
        .file-upload {
            position: relative;
            display: inline-block;
            cursor: pointer;
            width: 100%;
        }
        
        .file-upload input[type=file] {
            position: absolute;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }
        
        .file-upload.disabled {
            pointer-events: none;
            opacity: 0.7;
        }
        
        .file-upload-label {
            display: block;
            padding: 12px 15px;
            border-radius: 8px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            background: rgba(255, 255, 255, 0.1);
            color: white;
            font-size: 1rem;
            text-align: center;
        }
        
        .file-upload:not(.disabled):hover .file-upload-label {
            background: rgba(255, 255, 255, 0.15);
        }
        
        .current-file {
            margin-top: 8px;
            font-size: 0.9rem;
            opacity: 0.8;
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
            border-color: #6a11cb;
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
                <a href="technical_requests_admin.php" class="nav-link">
                    <i class="fas fa-tools"></i>
                    <span>Tech Support</span>
                </a>
            </li>
			
			            <li class="nav-item">
                <a href="employee_details.php" class="nav-link active">
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
            
            <h1>Employee Details</h1>
            <p class="subtitle">Update your personal and professional information</p>
            
            <?php if ($details_submitted): ?>
                <div class="message info">
                    <i class="fas fa-info-circle"></i> Your details have been submitted and are now in read-only mode. Please contact HR if you need to make any changes.
                </div>
            <?php endif; ?>
            
            <?php if (isset($success_message)): ?>
                <div class="message success"><?php echo $success_message; ?></div>
            <?php endif; ?>
            
            <?php if (isset($error_message)): ?>
                <div class="message error"><?php echo $error_message; ?></div>
            <?php endif; ?>
            
            <form method="POST" action="" enctype="multipart/form-data">
                <!-- Personal Information Section -->
                <div class="form-section">
                    <h2 class="section-title">Personal Information</h2>
                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="first_name">First Name</label>
                                <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($employee['first_name'] ?? ''); ?>" <?php echo $details_submitted ? 'readonly' : ''; ?> required>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="middle_name">Middle Name</label>
                                <input type="text" id="middle_name" name="middle_name" value="<?php echo htmlspecialchars($employee['middle_name'] ?? ''); ?>" <?php echo $details_submitted ? 'readonly' : ''; ?>>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="last_name">Last Name</label>
                                <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($employee['last_name'] ?? ''); ?>" <?php echo $details_submitted ? 'readonly' : ''; ?> required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="date_of_birth">Date of Birth</label>
                                <input type="date" id="date_of_birth" name="date_of_birth" value="<?php echo htmlspecialchars($employee['date_of_birth'] ?? ''); ?>" <?php echo $details_submitted ? 'readonly' : ''; ?>>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="gender">Gender</label>
                                <?php if ($details_submitted): ?>
                                    <input type="text" value="<?php echo htmlspecialchars($employee['gender'] ?? ''); ?>" readonly>
                                <?php else: ?>
                                    <select id="gender" name="gender">
                                        <option value="">Select Gender</option>
                                        <option value="Male" <?php echo (isset($employee['gender']) && $employee['gender'] == 'Male') ? 'selected' : ''; ?>>Male</option>
                                        <option value="Female" <?php echo (isset($employee['gender']) && $employee['gender'] == 'Female') ? 'selected' : ''; ?>>Female</option>
                                        <option value="Other" <?php echo (isset($employee['gender']) && $employee['gender'] == 'Other') ? 'selected' : ''; ?>>Other</option>
                                        <option value="Prefer not to say" <?php echo (isset($employee['gender']) && $employee['gender'] == 'Prefer not to say') ? 'selected' : ''; ?>>Prefer not to say</option>
                                    </select>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="nationality">Nationality</label>
                                <input type="text" id="nationality" name="nationality" value="<?php echo htmlspecialchars($employee['nationality'] ?? ''); ?>" <?php echo $details_submitted ? 'readonly' : ''; ?>>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="marital_status">Marital Status</label>
                                <?php if ($details_submitted): ?>
                                    <input type="text" value="<?php echo htmlspecialchars($employee['marital_status'] ?? ''); ?>" readonly>
                                <?php else: ?>
                                    <select id="marital_status" name="marital_status">
                                        <option value="">Select Status</option>
                                        <option value="Single" <?php echo (isset($employee['marital_status']) && $employee['marital_status'] == 'Single') ? 'selected' : ''; ?>>Single</option>
                                        <option value="Married" <?php echo (isset($employee['marital_status']) && $employee['marital_status'] == 'Married') ? 'selected' : ''; ?>>Married</option>
                                        <option value="Divorced" <?php echo (isset($employee['marital_status']) && $employee['marital_status'] == 'Divorced') ? 'selected' : ''; ?>>Divorced</option>
                                        <option value="Widowed" <?php echo (isset($employee['marital_status']) && $employee['marital_status'] == 'Widowed') ? 'selected' : ''; ?>>Widowed</option>
                                    </select>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="government_id_type">Government ID Type</label>
                                <?php if ($details_submitted): ?>
                                    <input type="text" value="<?php echo htmlspecialchars($employee['government_id_type'] ?? ''); ?>" readonly>
                                <?php else: ?>
                                    <select id="government_id_type" name="government_id_type">
                                        <option value="">Select ID Type</option>
                                        <option value="Aadhar" <?php echo (isset($employee['government_id_type']) && $employee['government_id_type'] == 'Aadhar') ? 'selected' : ''; ?>>Aadhar</option>
                                        <option value="Passport" <?php echo (isset($employee['government_id_type']) && $employee['government_id_type'] == 'Passport') ? 'selected' : ''; ?>>Passport</option>
                                        <option value="Driving License" <?php echo (isset($employee['government_id_type']) && $employee['government_id_type'] == 'Driving License') ? 'selected' : ''; ?>>Driving License</option>
                                        <option value="Voter ID" <?php echo (isset($employee['government_id_type']) && $employee['government_id_type'] == 'Voter ID') ? 'selected' : ''; ?>>Voter ID</option>
                                    </select>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="government_id_number">Government ID Number</label>
                                <input type="text" id="government_id_number" name="government_id_number" value="<?php echo htmlspecialchars($employee['government_id_number'] ?? ''); ?>" <?php echo $details_submitted ? 'readonly' : ''; ?>>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="photo">Profile Photo</label>
                        <?php if ($details_submitted): ?>
                            <div class="current-file"><?php echo !empty($employee['photo']) ? 'Current file: ' . basename($employee['photo']) : 'No photo uploaded'; ?></div>
                        <?php else: ?>
                            <div class="file-upload">
                                <input type="file" id="photo" name="photo">
                                <label for="photo" class="file-upload-label">
                                    <i class="fas fa-upload"></i> Choose Photo
                                </label>
                            </div>
                            <?php if (!empty($employee['photo'])): ?>
                                <div class="current-file">Current file: <?php echo basename($employee['photo']); ?></div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Contact Information Section -->
                <div class="form-section">
                    <h2 class="section-title">Contact Information</h2>
                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="phone_primary">Primary Phone</label>
                                <input type="text" id="phone_primary" name="phone_primary" value="<?php echo htmlspecialchars($employee['phone_primary'] ?? ''); ?>" <?php echo $details_submitted ? 'readonly' : ''; ?>>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="phone_alternate">Alternate Phone</label>
                                <input type="text" id="phone_alternate" name="phone_alternate" value="<?php echo htmlspecialchars($employee['phone_alternate'] ?? ''); ?>" <?php echo $details_submitted ? 'readonly' : ''; ?>>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="email">Email Address</label>
                                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($employee['email']); ?>" readonly>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="current_address">Current Address</label>
                                <textarea id="current_address" name="current_address" <?php echo $details_submitted ? 'readonly' : ''; ?>><?php echo htmlspecialchars($employee['current_address'] ?? ''); ?></textarea>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="permanent_address">Permanent Address</label>
                                <textarea id="permanent_address" name="permanent_address" <?php echo $details_submitted ? 'readonly' : ''; ?>><?php echo htmlspecialchars($employee['permanent_address'] ?? ''); ?></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="emergency_contact_name">Emergency Contact Name</label>
                                <input type="text" id="emergency_contact_name" name="emergency_contact_name" value="<?php echo htmlspecialchars($employee['emergency_contact_name'] ?? ''); ?>" <?php echo $details_submitted ? 'readonly' : ''; ?>>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="emergency_contact_relationship">Relationship</label>
                                <input type="text" id="emergency_contact_relationship" name="emergency_contact_relationship" value="<?php echo htmlspecialchars($employee['emergency_contact_relationship'] ?? ''); ?>" <?php echo $details_submitted ? 'readonly' : ''; ?>>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="emergency_contact_phone">Emergency Contact Phone</label>
                                <input type="text" id="emergency_contact_phone" name="emergency_contact_phone" value="<?php echo htmlspecialchars($employee['emergency_contact_phone'] ?? ''); ?>" <?php echo $details_submitted ? 'readonly' : ''; ?>>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Employment Details Section -->
                <div class="form-section">
                    <h2 class="section-title">Employment Details</h2>
                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="employee_id">Employee ID</label>
                                <input type="text" id="employee_id" name="employee_id" value="<?php echo htmlspecialchars($employee['employee_id'] ?? ''); ?>" <?php echo $details_submitted ? 'readonly' : ''; ?>>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="job_title">Job Title</label>
                                <input type="text" id="job_title" name="job_title" value="<?php echo htmlspecialchars($employee['job_title'] ?? ''); ?>" <?php echo $details_submitted ? 'readonly' : ''; ?>>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="department">Department</label>
                                <input type="text" id="department" name="department" value="<?php echo htmlspecialchars($employee['department']); ?>" readonly>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="employment_type">Employment Type</label>
                                <?php if ($details_submitted): ?>
                                    <input type="text" value="<?php echo htmlspecialchars($employee['employment_type'] ?? ''); ?>" readonly>
                                <?php else: ?>
                                    <select id="employment_type" name="employment_type">
                                        <option value="">Select Type</option>
                                        <option value="Full-time" <?php echo (isset($employee['employment_type']) && $employee['employment_type'] == 'Full-time') ? 'selected' : ''; ?>>Full-time</option>
                                        <option value="Part-time" <?php echo (isset($employee['employment_type']) && $employee['employment_type'] == 'Part-time') ? 'selected' : ''; ?>>Part-time</option>
                                        <option value="Contract" <?php echo (isset($employee['employment_type']) && $employee['employment_type'] == 'Contract') ? 'selected' : ''; ?>>Contract</option>
                                        <option value="Intern" <?php echo (isset($employee['employment_type']) && $employee['employment_type'] == 'Intern') ? 'selected' : ''; ?>>Intern</option>
                                    </select>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="reporting_manager">Reporting Manager</label>
                                <input type="text" id="reporting_manager" name="reporting_manager" value="<?php echo htmlspecialchars($employee['reporting_manager'] ?? ''); ?>" <?php echo $details_submitted ? 'readonly' : ''; ?>>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="date_of_joining">Date of Joining</label>
                                <input type="date" id="date_of_joining" name="date_of_joining" value="<?php echo htmlspecialchars($employee['date_of_joining'] ?? ''); ?>" <?php echo $details_submitted ? 'readonly' : ''; ?>>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="work_location">Work Location</label>
                                <input type="text" id="work_location" name="work_location" value="<?php echo htmlspecialchars($employee['work_location'] ?? ''); ?>" <?php echo $details_submitted ? 'readonly' : ''; ?>>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="employee_status">Employee Status</label>
                                <?php if ($details_submitted): ?>
                                    <input type="text" value="<?php echo htmlspecialchars($employee['employee_status'] ?? ''); ?>" readonly>
                                <?php else: ?>
                                    <select id="employee_status" name="employee_status">
                                        <option value="Active" <?php echo (isset($employee['employee_status']) && $employee['employee_status'] == 'Active') ? 'selected' : ''; ?>>Active</option>
                                        <option value="On Leave" <?php echo (isset($employee['employee_status']) && $employee['employee_status'] == 'On Leave') ? 'selected' : ''; ?>>On Leave</option>
                                        <option value="Resigned" <?php echo (isset($employee['employee_status']) && $employee['employee_status'] == 'Resigned') ? 'selected' : ''; ?>>Resigned</option>
                                        <option value="Terminated" <?php echo (isset($employee['employee_status']) && $employee['employee_status'] == 'Terminated') ? 'selected' : ''; ?>>Terminated</option>
                                    </select>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="employment_category">Employment Category</label>
                                <?php if ($details_submitted): ?>
                                    <input type="text" value="<?php echo htmlspecialchars($employee['employment_category'] ?? ''); ?>" readonly>
                                <?php else: ?>
                                    <select id="employment_category" name="employment_category">
                                        <option value="">Select Category</option>
                                        <option value="Exempt" <?php echo (isset($employee['employment_category']) && $employee['employment_category'] == 'Exempt') ? 'selected' : ''; ?>>Exempt</option>
                                        <option value="Non-Exempt" <?php echo (isset($employee['employment_category']) && $employee['employment_category'] == 'Non-Exempt') ? 'selected' : ''; ?>>Non-Exempt</option>
                                        <option value="Blue Collar" <?php echo (isset($employee['employment_category']) && $employee['employment_category'] == 'Blue Collar') ? 'selected' : ''; ?>>Blue Collar</option>
                                        <option value="White Collar" <?php echo (isset($employee['employment_category']) && $employee['employment_category'] == 'White Collar') ? 'selected' : ''; ?>>White Collar</option>
                                    </select>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Compensation & Payroll Section -->
                <div class="form-section">
                    <h2 class="section-title">Compensation & Payroll</h2>
                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="salary">Salary / CTC</label>
                                <input type="number" id="salary" name="salary" step="0.01" value="<?php echo htmlspecialchars($employee['salary'] ?? ''); ?>" <?php echo $details_submitted ? 'readonly' : ''; ?>>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="pay_grade">Pay Grade / Band</label>
                                <input type="text" id="pay_grade" name="pay_grade" value="<?php echo htmlspecialchars($employee['pay_grade'] ?? ''); ?>" <?php echo $details_submitted ? 'readonly' : ''; ?>>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="pay_frequency">Pay Frequency</label>
                                <?php if ($details_submitted): ?>
                                    <input type="text" value="<?php echo htmlspecialchars($employee['pay_frequency'] ?? ''); ?>" readonly>
                                <?php else: ?>
                                    <select id="pay_frequency" name="pay_frequency">
                                        <option value="">Select Frequency</option>
                                        <option value="Monthly" <?php echo (isset($employee['pay_frequency']) && $employee['pay_frequency'] == 'Monthly') ? 'selected' : ''; ?>>Monthly</option>
                                        <option value="Bi-weekly" <?php echo (isset($employee['pay_frequency']) && $employee['pay_frequency'] == 'Bi-weekly') ? 'selected' : ''; ?>>Bi-weekly</option>
                                        <option value="Weekly" <?php echo (isset($employee['pay_frequency']) && $employee['pay_frequency'] == 'Weekly') ? 'selected' : ''; ?>>Weekly</option>
                                    </select>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="bank_name">Bank Name</label>
                                <input type="text" id="bank_name" name="bank_name" value="<?php echo htmlspecialchars($employee['bank_name'] ?? ''); ?>" <?php echo $details_submitted ? 'readonly' : ''; ?>>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="bank_account_number">Bank Account Number</label>
                                <input type="text" id="bank_account_number" name="bank_account_number" value="<?php echo htmlspecialchars($employee['bank_account_number'] ?? ''); ?>" <?php echo $details_submitted ? 'readonly' : ''; ?>>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="bank_ifsc_swift">IFSC / SWIFT Code</label>
                                <input type="text" id="bank_ifsc_swift" name="bank_ifsc_swift" value="<?php echo htmlspecialchars($employee['bank_ifsc_swift'] ?? ''); ?>" <?php echo $details_submitted ? 'readonly' : ''; ?>>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="pan_tax_id">PAN / Tax ID</label>
                                <input type="text" id="pan_tax_id" name="pan_tax_id" value="<?php echo htmlspecialchars($employee['pan_tax_id'] ?? ''); ?>" <?php echo $details_submitted ? 'readonly' : ''; ?>>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="provident_fund_number">Provident Fund Number</label>
                                <input type="text" id="provident_fund_number" name="provident_fund_number" value="<?php echo htmlspecialchars($employee['provident_fund_number'] ?? ''); ?>" <?php echo $details_submitted ? 'readonly' : ''; ?>>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="esic_number">ESIC Number</label>
                                <input type="text" id="esic_number" name="esic_number" value="<?php echo htmlspecialchars($employee['esic_number'] ?? ''); ?>" <?php echo $details_submitted ? 'readonly' : ''; ?>>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="uan_number">UAN Number</label>
                                <input type="text" id="uan_number" name="uan_number" value="<?php echo htmlspecialchars($employee['uan_number'] ?? ''); ?>" <?php echo $details_submitted ? 'readonly' : ''; ?>>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Education & Qualifications Section -->
                <div class="form-section">
                    <h2 class="section-title">Education & Qualifications</h2>
                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="highest_qualification">Highest Qualification</label>
                                <input type="text" id="highest_qualification" name="highest_qualification" value="<?php echo htmlspecialchars($employee['highest_qualification'] ?? ''); ?>" <?php echo $details_submitted ? 'readonly' : ''; ?>>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="university_institution">University / Institution</label>
                                <input type="text" id="university_institution" name="university_institution" value="<?php echo htmlspecialchars($employee['university_institution'] ?? ''); ?>" <?php echo $details_submitted ? 'readonly' : ''; ?>>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="year_of_graduation">Year of Graduation</label>
                                <input type="number" id="year_of_graduation" name="year_of_graduation" min="1950" max="<?php echo date('Y'); ?>" value="<?php echo htmlspecialchars($employee['year_of_graduation'] ?? ''); ?>" <?php echo $details_submitted ? 'readonly' : ''; ?>>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="specialization_major">Specialization / Major</label>
                                <input type="text" id="specialization_major" name="specialization_major" value="<?php echo htmlspecialchars($employee['specialization_major'] ?? ''); ?>" <?php echo $details_submitted ? 'readonly' : ''; ?>>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="certifications">Certifications</label>
                                <textarea id="certifications" name="certifications" placeholder="List any certifications you have obtained" <?php echo $details_submitted ? 'readonly' : ''; ?>><?php echo htmlspecialchars($employee['certifications'] ?? ''); ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Work Experience Section -->
                <div class="form-section">
                    <h2 class="section-title">Work Experience</h2>
                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="previous_employer_name">Previous Employer Name</label>
                                <input type="text" id="previous_employer_name" name="previous_employer_name" value="<?php echo htmlspecialchars($employee['previous_employer_name'] ?? ''); ?>" <?php echo $details_submitted ? 'readonly' : ''; ?>>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="previous_job_title">Previous Job Title</label>
                                <input type="text" id="previous_job_title" name="previous_job_title" value="<?php echo htmlspecialchars($employee['previous_job_title'] ?? ''); ?>" <?php echo $details_submitted ? 'readonly' : ''; ?>>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="previous_work_duration">Duration (From - To)</label>
                                <input type="text" id="previous_work_duration" name="previous_work_duration" placeholder="e.g., Jan 2020 - Dec 2022" value="<?php echo htmlspecialchars($employee['previous_work_duration'] ?? ''); ?>" <?php echo $details_submitted ? 'readonly' : ''; ?>>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="reason_for_leaving">Reason for Leaving</label>
                                <textarea id="reason_for_leaving" name="reason_for_leaving" <?php echo $details_submitted ? 'readonly' : ''; ?>><?php echo htmlspecialchars($employee['reason_for_leaving'] ?? ''); ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Documents & Verification Section -->
                <div class="form-section">
                    <h2 class="section-title">Documents & Verification</h2>
                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="resume">Resume / CV</label>
                                <?php if ($details_submitted): ?>
                                    <div class="current-file"><?php echo !empty($employee['resume_path']) ? 'Current file: ' . basename($employee['resume_path']) : 'No file uploaded'; ?></div>
                                <?php else: ?>
                                    <div class="file-upload">
                                        <input type="file" id="resume" name="resume">
                                        <label for="resume" class="file-upload-label">
                                            <i class="fas fa-upload"></i> Choose File
                                        </label>
                                    </div>
                                    <?php if (!empty($employee['resume_path'])): ?>
                                        <div class="current-file">Current file: <?php echo basename($employee['resume_path']); ?></div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="offer_letter">Offer Letter</label>
                                <?php if ($details_submitted): ?>
                                    <div class="current-file"><?php echo !empty($employee['offer_letter_path']) ? 'Current file: ' . basename($employee['offer_letter_path']) : 'No file uploaded'; ?></div>
                                <?php else: ?>
                                    <div class="file-upload">
                                        <input type="file" id="offer_letter" name="offer_letter">
                                        <label for="offer_letter" class="file-upload-label">
                                            <i class="fas fa-upload"></i> Choose File
                                        </label>
                                    </div>
                                    <?php if (!empty($employee['offer_letter_path'])): ?>
                                        <div class="current-file">Current file: <?php echo basename($employee['offer_letter_path']); ?></div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="joining_letter">Joining Letter</label>
                                <?php if ($details_submitted): ?>
                                    <div class="current-file"><?php echo !empty($employee['joining_letter_path']) ? 'Current file: ' . basename($employee['joining_letter_path']) : 'No file uploaded'; ?></div>
                                <?php else: ?>
                                    <div class="file-upload">
                                        <input type="file" id="joining_letter" name="joining_letter">
                                        <label for="joining_letter" class="file-upload-label">
                                            <i class="fas fa-upload"></i> Choose File
                                        </label>
                                    </div>
                                    <?php if (!empty($employee['joining_letter_path'])): ?>
                                        <div class="current-file">Current file: <?php echo basename($employee['joining_letter_path']); ?></div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="id_proof">ID Proof</label>
                                <?php if ($details_submitted): ?>
                                    <div class="current-file"><?php echo !empty($employee['id_proof_path']) ? 'Current file: ' . basename($employee['id_proof_path']) : 'No file uploaded'; ?></div>
                                <?php else: ?>
                                    <div class="file-upload">
                                        <input type="file" id="id_proof" name="id_proof">
                                        <label for="id_proof" class="file-upload-label">
                                            <i class="fas fa-upload"></i> Choose File
                                        </label>
                                    </div>
                                    <?php if (!empty($employee['id_proof_path'])): ?>
                                        <div class="current-file">Current file: <?php echo basename($employee['id_proof_path']); ?></div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="address_proof">Address Proof</label>
                                <?php if ($details_submitted): ?>
                                    <div class="current-file"><?php echo !empty($employee['address_proof_path']) ? 'Current file: ' . basename($employee['address_proof_path']) : 'No file uploaded'; ?></div>
                                <?php else: ?>
                                    <div class="file-upload">
                                        <input type="file" id="address_proof" name="address_proof">
                                        <label for="address_proof" class="file-upload-label">
                                            <i class="fas fa-upload"></i> Choose File
                                        </label>
                                    </div>
                                    <?php if (!empty($employee['address_proof_path'])): ?>
                                        <div class="current-file">Current file: <?php echo basename($employee['address_proof_path']); ?></div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="education_certificates">Education Certificates</label>
                                <?php if ($details_submitted): ?>
                                    <div class="current-file"><?php echo !empty($employee['education_certificates_path']) ? 'Current file: ' . basename($employee['education_certificates_path']) : 'No file uploaded'; ?></div>
                                <?php else: ?>
                                    <div class="file-upload">
                                        <input type="file" id="education_certificates" name="education_certificates">
                                        <label for="education_certificates" class="file-upload-label">
                                            <i class="fas fa-upload"></i> Choose File
                                        </label>
                                    </div>
                                    <?php if (!empty($employee['education_certificates_path'])): ?>
                                        <div class="current-file">Current file: <?php echo basename($employee['education_certificates_path']); ?></div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="experience_letters">Experience Letters</label>
                                <?php if ($details_submitted): ?>
                                    <div class="current-file"><?php echo !empty($employee['experience_letters_path']) ? 'Current file: ' . basename($employee['experience_letters_path']) : 'No file uploaded'; ?></div>
                                <?php else: ?>
                                    <div class="file-upload">
                                        <input type="file" id="experience_letters" name="experience_letters">
                                        <label for="experience_letters" class="file-upload-label">
                                            <i class="fas fa-upload"></i> Choose File
                                        </label>
                                    </div>
                                    <?php if (!empty($employee['experience_letters_path'])): ?>
                                        <div class="current-file">Current file: <?php echo basename($employee['experience_letters_path']); ?></div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="background_verification_status">Background Verification Status</label>
                                <?php if ($details_submitted): ?>
                                    <input type="text" value="<?php echo htmlspecialchars($employee['background_verification_status'] ?? ''); ?>" readonly>
                                <?php else: ?>
                                    <select id="background_verification_status" name="background_verification_status">
                                        <option value="Pending" <?php echo (isset($employee['background_verification_status']) && $employee['background_verification_status'] == 'Pending') ? 'selected' : ''; ?>>Pending</option>
                                        <option value="In Progress" <?php echo (isset($employee['background_verification_status']) && $employee['background_verification_status'] == 'In Progress') ? 'selected' : ''; ?>>In Progress</option>
                                        <option value="Completed" <?php echo (isset($employee['background_verification_status']) && $employee['background_verification_status'] == 'Completed') ? 'selected' : ''; ?>>Completed</option>
                                        <option value="Failed" <?php echo (isset($employee['background_verification_status']) && $employee['background_verification_status'] == 'Failed') ? 'selected' : ''; ?>>Failed</option>
                                    </select>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- IT & System Access Section -->
                <div class="form-section">
                    <h2 class="section-title">IT & System Access</h2>
                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="company_email_id">Company Email ID</label>
                                <input type="email" id="company_email_id" name="company_email_id" value="<?php echo htmlspecialchars($employee['company_email_id'] ?? ''); ?>" <?php echo $details_submitted ? 'readonly' : ''; ?>>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="asset_tag_laptop_id">Asset Tag / Laptop ID</label>
                                <input type="text" id="asset_tag_laptop_id" name="asset_tag_laptop_id" value="<?php echo htmlspecialchars($employee['asset_tag_laptop_id'] ?? ''); ?>" <?php echo $details_submitted ? 'readonly' : ''; ?>>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="access_card_id">Access Card ID</label>
                                <input type="text" id="access_card_id" name="access_card_id" value="<?php echo htmlspecialchars($employee['access_card_id'] ?? ''); ?>" <?php echo $details_submitted ? 'readonly' : ''; ?>>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="software_access_needed">Software / App Access Needed</label>
                                <textarea id="software_access_needed" name="software_access_needed" placeholder="List the software or applications you need access to" <?php echo $details_submitted ? 'readonly' : ''; ?>><?php echo htmlspecialchars($employee['software_access_needed'] ?? ''); ?></textarea>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label>System Access</label>
                                <?php if ($details_submitted): ?>
                                    <div class="checkbox-group">
                                        <input type="checkbox" id="system_assigned_display" disabled <?php echo (isset($employee['system_assigned']) && $employee['system_assigned'] == 1) ? 'checked' : ''; ?>>
                                        <label for="system_assigned_display">System Assigned</label>
                                    </div>
                                    <div class="checkbox-group">
                                        <input type="checkbox" id="vpn_remote_access_display" disabled <?php echo (isset($employee['vpn_remote_access']) && $employee['vpn_remote_access'] == 1) ? 'checked' : ''; ?>>
                                        <label for="vpn_remote_access_display">VPN / Remote Access</label>
                                    </div>
                                <?php else: ?>
                                    <div class="checkbox-group">
                                        <input type="checkbox" id="system_assigned" name="system_assigned" value="1" <?php echo (isset($employee['system_assigned']) && $employee['system_assigned'] == 1) ? 'checked' : ''; ?>>
                                        <label for="system_assigned">System Assigned</label>
                                    </div>
                                    <div class="checkbox-group">
                                        <input type="checkbox" id="vpn_remote_access" name="vpn_remote_access" value="1" <?php echo (isset($employee['vpn_remote_access']) && $employee['vpn_remote_access'] == 1) ? 'checked' : ''; ?>>
                                        <label for="vpn_remote_access">VPN / Remote Access</label>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- HR / Compliance Section -->
                <div class="form-section">
                    <h2 class="section-title">HR / Compliance</h2>
                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label>Compliance Documents</label>
                                <?php if ($details_submitted): ?>
                                    <div class="checkbox-group">
                                        <input type="checkbox" id="nda_signed_display" disabled <?php echo (isset($employee['nda_signed']) && $employee['nda_signed'] == 1) ? 'checked' : ''; ?>>
                                        <label for="nda_signed_display">NDA Signed</label>
                                    </div>
                                    <div class="checkbox-group">
                                        <input type="checkbox" id="code_of_conduct_acknowledged_display" disabled <?php echo (isset($employee['code_of_conduct_acknowledged']) && $employee['code_of_conduct_acknowledged'] == 1) ? 'checked' : ''; ?>>
                                        <label for="code_of_conduct_acknowledged_display">Code of Conduct Acknowledged</label>
                                    </div>
                                    <div class="checkbox-group">
                                        <input type="checkbox" id="policy_documents_acknowledged_display" disabled <?php echo (isset($employee['policy_documents_acknowledged']) && $employee['policy_documents_acknowledged'] == 1) ? 'checked' : ''; ?>>
                                        <label for="policy_documents_acknowledged_display">Policy Documents Acknowledged</label>
                                    </div>
                                    <div class="checkbox-group">
                                        <input type="checkbox" id="medical_test_done_display" disabled <?php echo (isset($employee['medical_test_done']) && $employee['medical_test_done'] == 1) ? 'checked' : ''; ?>>
                                        <label for="medical_test_done_display">Medical Test Done</label>
                                    </div>
                                <?php else: ?>
                                    <div class="checkbox-group">
                                        <input type="checkbox" id="nda_signed" name="nda_signed" value="1" <?php echo (isset($employee['nda_signed']) && $employee['nda_signed'] == 1) ? 'checked' : ''; ?>>
                                        <label for="nda_signed">NDA Signed</label>
                                    </div>
                                    <div class="checkbox-group">
                                        <input type="checkbox" id="code_of_conduct_acknowledged" name="code_of_conduct_acknowledged" value="1" <?php echo (isset($employee['code_of_conduct_acknowledged']) && $employee['code_of_conduct_acknowledged'] == 1) ? 'checked' : ''; ?>>
                                        <label for="code_of_conduct_acknowledged">Code of Conduct Acknowledged</label>
                                    </div>
                                    <div class="checkbox-group">
                                        <input type="checkbox" id="policy_documents_acknowledged" name="policy_documents_acknowledged" value="1" <?php echo (isset($employee['policy_documents_acknowledged']) && $employee['policy_documents_acknowledged'] == 1) ? 'checked' : ''; ?>>
                                        <label for="policy_documents_acknowledged">Policy Documents Acknowledged</label>
                                    </div>
                                    <div class="checkbox-group">
                                        <input type="checkbox" id="medical_test_done" name="medical_test_done" value="1" <?php echo (isset($employee['medical_test_done']) && $employee['medical_test_done'] == 1) ? 'checked' : ''; ?>>
                                        <label for="medical_test_done">Medical Test Done</label>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="probation_period_end_date">Probation Period End Date</label>
                                <input type="date" id="probation_period_end_date" name="probation_period_end_date" value="<?php echo htmlspecialchars($employee['probation_period_end_date'] ?? ''); ?>" <?php echo $details_submitted ? 'readonly' : ''; ?>>
                            </div>
                            <div class="form-group">
                                <label for="confirmation_status">Confirmation Status</label>
                                <?php if ($details_submitted): ?>
                                    <input type="text" value="<?php echo htmlspecialchars($employee['confirmation_status'] ?? ''); ?>" readonly>
                                <?php else: ?>
                                    <select id="confirmation_status" name="confirmation_status">
                                        <option value="Pending" <?php echo (isset($employee['confirmation_status']) && $employee['confirmation_status'] == 'Pending') ? 'selected' : ''; ?>>Pending</option>
                                        <option value="Confirmed" <?php echo (isset($employee['confirmation_status']) && $employee['confirmation_status'] == 'Confirmed') ? 'selected' : ''; ?>>Confirmed</option>
                                        <option value="Extended" <?php echo (isset($employee['confirmation_status']) && $employee['confirmation_status'] == 'Extended') ? 'selected' : ''; ?>>Extended</option>
                                    </select>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Miscellaneous Section -->
                <div class="form-section">
                    <h2 class="section-title">Miscellaneous</h2>
                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="employee_type">Employee Type</label>
                                <?php if ($details_submitted): ?>
                                    <input type="text" value="<?php echo htmlspecialchars($employee['employee_type'] ?? ''); ?>" readonly>
                                <?php else: ?>
                                    <select id="employee_type" name="employee_type">
                                        <option value="On-roll" <?php echo (isset($employee['employee_type']) && $employee['employee_type'] == 'On-roll') ? 'selected' : ''; ?>>On-roll</option>
                                        <option value="Off-roll" <?php echo (isset($employee['employee_type']) && $employee['employee_type'] == 'Off-roll') ? 'selected' : ''; ?>>Off-roll</option>
                                        <option value="Consultant" <?php echo (isset($employee['employee_type']) && $employee['employee_type'] == 'Consultant') ? 'selected' : ''; ?>>Consultant</option>
                                    </select>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="referral_source">Referral Source</label>
                                <?php if ($details_submitted): ?>
                                    <input type="text" value="<?php echo htmlspecialchars($employee['referral_source'] ?? ''); ?>" readonly>
                                <?php else: ?>
                                    <select id="referral_source" name="referral_source">
                                        <option value="">Select Source</option>
                                        <option value="Portal" <?php echo (isset($employee['referral_source']) && $employee['referral_source'] == 'Portal') ? 'selected' : ''; ?>>Job Portal</option>
                                        <option value="Employee" <?php echo (isset($employee['referral_source']) && $employee['referral_source'] == 'Employee') ? 'selected' : ''; ?>>Employee Referral</option>
                                        <option value="Campus" <?php echo (isset($employee['referral_source']) && $employee['referral_source'] == 'Campus') ? 'selected' : ''; ?>>Campus Recruitment</option>
                                        <option value="Direct" <?php echo (isset($employee['referral_source']) && $employee['referral_source'] == 'Direct') ? 'selected' : ''; ?>>Direct Application</option>
                                    </select>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="buddy_mentor_assigned">Buddy / Mentor Assigned</label>
                                <input type="text" id="buddy_mentor_assigned" name="buddy_mentor_assigned" value="<?php echo htmlspecialchars($employee['buddy_mentor_assigned'] ?? ''); ?>" <?php echo $details_submitted ? 'readonly' : ''; ?>>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="notes_remarks">Notes / Remarks</label>
                        <textarea id="notes_remarks" name="notes_remarks" placeholder="Any additional notes or remarks" <?php echo $details_submitted ? 'readonly' : ''; ?>><?php echo htmlspecialchars($employee['notes_remarks'] ?? ''); ?></textarea>
                    </div>
                </div>
                
                <?php if (!$details_submitted): ?>
                    <div style="margin-top: 30px; text-align: center;">
                        <button type="submit" name="submit_details" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Details
                        </button>
                        <a href="start.php" class="btn btn-back">
                            <i class="fas fa-arrow-left"></i> Back to Home
                        </a>
                    </div>
                <?php else: ?>
                    <div style="margin-top: 30px; text-align: center;">
                        <a href="start.php" class="btn btn-back">
                            <i class="fas fa-arrow-left"></i> Back to Home
                        </a>
                    </div>
                <?php endif; ?>
            </form>
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
            
            // Form validation for date fields
            const dateFields = ['date_of_birth', 'date_of_joining', 'probation_period_end_date'];
            dateFields.forEach(fieldId => {
                const field = document.getElementById(fieldId);
                if (field) {
                    field.addEventListener('change', function() {
                        // Add any date validation if needed
                    });
                }
            });
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