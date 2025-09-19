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
// For ca_tool.php, check if user has CA privileges
$current_page = basename($_SERVER['PHP_SELF']);
if ($current_page === 'ca_tool.php') {
    // Verify if the user has CA privileges
    $user_id = $_SESSION['user_id'];
    $sql = "SELECT is_ca FROM employees WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($is_ca);
    $stmt->fetch();
    $stmt->close();
    $employee = ['is_ca' => $is_ca];
    
    if ($employee) {
        // Check if is_ca is set and equals 1 (true)
        if (!isset($employee['is_ca']) || $employee['is_ca'] != 1) {
            // User doesn't have CA privileges
            $_SESSION['access_error'] = "You need CA (Chartered Accountant) privileges to access the CA Tool";
            header("Location: illegal_access_ca.php"); // Redirect to CA access denied page
            exit();
        } else {
            // Set CA session flags if not already set
            $_SESSION['is_ca'] = true;
        }
    } else {
        // User not found in database
        $_SESSION['access_error'] = "User not found in system";
        header("Location: illegal_access_ca.php");
        exit();
    }
}
// Get user information
$user_id = $_SESSION['user_id'];
$user_email = $_SESSION['user_email'];
$user_name = $_SESSION['user_name'];
// Function to validate and sanitize input
function validateInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}
// Function to validate date format
function validateDate($date, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}
// Function to validate email
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}
// Function to validate numeric value
function validateNumeric($value) {
    return is_numeric($value) && $value >= 0;
}
// Function to validate GST number
function validateGST($gst) {
    // GST number format: 2 digits + 10 characters (PAN) + 1 digit + 1 character + 1 digit
    return preg_match('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[0-9]{1}Z[0-9A-Z]{1}$/', strtoupper($gst));
}
// Function to validate PAN number
function validatePAN($pan) {
    // PAN format: 3 letters + 4 digits + 1 letter
    return preg_match('/^[A-Z]{3}[P]{1}[A-Z]{1}[0-9]{4}[A-Z]{1}$/', strtoupper($pan));
}
// Function to validate IFSC code
function validateIFSC($ifsc) {
    // IFSC format: 4 letters + 0 + 6 characters
    return preg_match('/^[A-Z]{4}0[A-Z0-9]{6}$/', strtoupper($ifsc));
}
// Function to validate phone number
function validatePhone($phone) {
    // Basic phone validation (international format)
    return preg_match('/^[0-9]{10,15}$/', $phone);
}
// Function to validate file upload
function validateFile($file, $allowedTypes = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx']) {
    if ($file['error'] !== 0) {
        return false;
    }
    
    $fileType = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($fileType, $allowedTypes)) {
        return false;
    }
    
    // Check file size (limit to 5MB)
    if ($file['size'] > 5242880) {
        return false;
    }
    
    return true;
}
// Handle form submissions
$message = "";
$message_type = "";
// Create new transaction
if (isset($_POST['create_transaction'])) {
    // Validate and sanitize inputs
    $errors = [];
    
    $transaction_date = validateInput($_POST['transaction_date']);
    if (!validateDate($transaction_date)) {
        $errors[] = "Invalid transaction date format";
    }
    
    $transaction_type = validateInput($_POST['transaction_type']);
    if (!in_array($transaction_type, ['Payment', 'Receipt', 'Contra', 'Journal', 'Sales', 'Purchase'])) {
        $errors[] = "Invalid transaction type";
    }
    
    $ledger_id = validateInput($_POST['ledger_id']);
    if (empty($ledger_id)) {
        $errors[] = "Ledger ID is required";
    }
    
    $ledger_name = validateInput($_POST['ledger_name']);
    if (empty($ledger_name)) {
        $errors[] = "Ledger name is required";
    }
    
    $amount = validateInput($_POST['amount']);
    if (!validateNumeric($amount)) {
        $errors[] = "Amount must be a valid positive number";
    }
    
    $party_name = validateInput($_POST['party_name']);
    $party_address = validateInput($_POST['party_address']);
    $contact_number = validateInput($_POST['contact_number']);
    if (!empty($contact_number) && !validatePhone($contact_number)) {
        $errors[] = "Invalid contact number format";
    }
    
    $email = validateInput($_POST['email']);
    if (!empty($email) && !validateEmail($email)) {
        $errors[] = "Invalid email format";
    }
    
    $gst_number = validateInput($_POST['gst_number']);
    if (!empty($gst_number) && !validateGST($gst_number)) {
        $errors[] = "Invalid GST number format";
    }
    
    $pan_number = validateInput($_POST['pan_number']);
    if (!empty($pan_number) && !validatePAN($pan_number)) {
        $errors[] = "Invalid PAN number format";
    }
    
    $narration = validateInput($_POST['narration']);
    $reference_number = validateInput($_POST['reference_number']);
    
    $reference_date = validateInput($_POST['reference_date']);
    if (!empty($reference_date) && !validateDate($reference_date)) {
        $errors[] = "Invalid reference date format";
    }
    
    $voucher_type = validateInput($_POST['voucher_type']);
    $voucher_number = validateInput($_POST['voucher_number']);
    
    $bank_name = validateInput($_POST['bank_name']);
    $bank_account_number = validateInput($_POST['bank_account_number']);
    $bank_ifsc = validateInput($_POST['bank_ifsc']);
    if (!empty($bank_ifsc) && !validateIFSC($bank_ifsc)) {
        $errors[] = "Invalid IFSC code format";
    }
    
    $cheque_number = validateInput($_POST['cheque_number']);
    $cheque_date = validateInput($_POST['cheque_date']);
    if (!empty($cheque_date) && !validateDate($cheque_date)) {
        $errors[] = "Invalid cheque date format";
    }
    
    $instrument_type = validateInput($_POST['instrument_type']);
    $cost_center = validateInput($_POST['cost_center']);
    $project_id = validateInput($_POST['project_id']);
    $project_name = validateInput($_POST['project_name']);
    $expense_category = validateInput($_POST['expense_category']);
    $income_category = validateInput($_POST['income_category']);
    
    $tax_amount = validateInput($_POST['tax_amount']);
    if (!empty($tax_amount) && !validateNumeric($tax_amount)) {
        $errors[] = "Tax amount must be a valid positive number";
    }
    
    $tax_percentage = validateInput($_POST['tax_percentage']);
    if (!empty($tax_percentage) && !validateNumeric($tax_percentage)) {
        $errors[] = "Tax percentage must be a valid positive number";
    }
    
    $tax_type = validateInput($_POST['tax_type']);
    
    $cgst_amount = validateInput($_POST['cgst_amount']);
    if (!empty($cgst_amount) && !validateNumeric($cgst_amount)) {
        $errors[] = "CGST amount must be a valid positive number";
    }
    
    $sgst_amount = validateInput($_POST['sgst_amount']);
    if (!empty($sgst_amount) && !validateNumeric($sgst_amount)) {
        $errors[] = "SGST amount must be a valid positive number";
    }
    
    $igst_amount = validateInput($_POST['igst_amount']);
    if (!empty($igst_amount) && !validateNumeric($igst_amount)) {
        $errors[] = "IGST amount must be a valid positive number";
    }
    
    $cess_amount = validateInput($_POST['cess_amount']);
    if (!empty($cess_amount) && !validateNumeric($cess_amount)) {
        $errors[] = "CESS amount must be a valid positive number";
    }
    
    $discount_amount = validateInput($_POST['discount_amount']);
    if (!empty($discount_amount) && !validateNumeric($discount_amount)) {
        $errors[] = "Discount amount must be a valid positive number";
    }
    
    $round_off = validateInput($_POST['round_off']);
    if (!empty($round_off) && !validateNumeric($round_off)) {
        $errors[] = "Round off must be a valid number";
    }
    
    $total_amount = validateInput($_POST['total_amount']);
    if (!validateNumeric($total_amount)) {
        $errors[] = "Total amount must be a valid positive number";
    }
    
    $status = validateInput($_POST['status']);
    if (!in_array($status, ['Draft', 'Pending', 'Approved', 'Rejected', 'Cancelled'])) {
        $errors[] = "Invalid status";
    }
    
    $financial_year = validateInput($_POST['financial_year']);
    if (empty($financial_year)) {
        $errors[] = "Financial year is required";
    }
    
    $company_id = validateInput($_POST['company_id']);
    if (empty($company_id)) {
        $errors[] = "Company ID is required";
    }
    
    $branch_id = validateInput($_POST['branch_id']);
    $department_id = validateInput($_POST['department_id']);
    $remarks = validateInput($_POST['remarks']);
    
    // Handle file upload
    $attachment_path = "";
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] == 0) {
        if (!validateFile($_FILES['attachment'])) {
            $errors[] = "Invalid file format or size. Allowed formats: jpg, jpeg, png, gif, pdf, doc, docx, xls, xlsx. Maximum size: 5MB.";
        } else {
            $target_dir = "uploads/ca_tool/";
            if (!file_exists($target_dir)) {
                mkdir($target_dir, 0777, true);
            }
            
            // Generate unique filename to prevent overwriting
            $fileType = strtolower(pathinfo($_FILES["attachment"]["name"], PATHINFO_EXTENSION));
            $newFileName = uniqid() . '.' . $fileType;
            $target_file = $target_dir . $newFileName;
            
            if (move_uploaded_file($_FILES["attachment"]["tmp_name"], $target_file)) {
                $attachment_path = $target_file;
            } else {
                $errors[] = "Error uploading file";
            }
        }
    }
    
    // If no errors, proceed with database insertion
    if (empty($errors)) {
        $transaction_id = 'TXN' . date('YmdHis');
        
        // Use prepared statements to prevent SQL injection
        $stmt = $conn->prepare("INSERT INTO ca_tool (
            transaction_id, transaction_date, transaction_type, ledger_id, ledger_name, amount, 
            party_name, party_address, contact_number, email, gst_number, pan_number, narration, 
            reference_number, reference_date, voucher_type, voucher_number, bank_name, 
            bank_account_number, bank_ifsc, cheque_number, cheque_date, instrument_type, 
            cost_center, project_id, project_name, expense_category, income_category, tax_amount, 
            tax_percentage, tax_type, cgst_amount, sgst_amount, igst_amount, cess_amount, 
            discount_amount, round_off, total_amount, status, created_by, financial_year, 
            company_id, branch_id, department_id, attachment_path, remarks
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->bind_param("sssssssssssssssssssssssssssssssssssssssssssssss", 
            $transaction_id, $transaction_date, $transaction_type, $ledger_id, $ledger_name, $amount, 
            $party_name, $party_address, $contact_number, $email, $gst_number, $pan_number, $narration, 
            $reference_number, $reference_date, $voucher_type, $voucher_number, $bank_name, 
            $bank_account_number, $bank_ifsc, $cheque_number, $cheque_date, $instrument_type, 
            $cost_center, $project_id, $project_name, $expense_category, $income_category, $tax_amount, 
            $tax_percentage, $tax_type, $cgst_amount, $sgst_amount, $igst_amount, $cess_amount, 
            $discount_amount, $round_off, $total_amount, $status, $user_id, $financial_year, 
            $company_id, $branch_id, $department_id, $attachment_path, $remarks
        );
        
        if ($stmt->execute()) {
            $message = "Transaction created successfully!";
            $message_type = "success";
        } else {
            $message = "Error: " . $stmt->error;
            $message_type = "error";
        }
        
        $stmt->close();
    } else {
        $message = "Validation errors: " . implode(", ", $errors);
        $message_type = "error";
    }
}
// Update transaction
if (isset($_POST['update_transaction'])) {
    // Validate and sanitize inputs
    $errors = [];
    
    $id = validateInput($_POST['id']);
    if (!is_numeric($id)) {
        $errors[] = "Invalid transaction ID";
    }
    
    $transaction_date = validateInput($_POST['transaction_date']);
    if (!validateDate($transaction_date)) {
        $errors[] = "Invalid transaction date format";
    }
    
    $transaction_type = validateInput($_POST['transaction_type']);
    if (!in_array($transaction_type, ['Payment', 'Receipt', 'Contra', 'Journal', 'Sales', 'Purchase'])) {
        $errors[] = "Invalid transaction type";
    }
    
    $ledger_id = validateInput($_POST['ledger_id']);
    if (empty($ledger_id)) {
        $errors[] = "Ledger ID is required";
    }
    
    $ledger_name = validateInput($_POST['ledger_name']);
    if (empty($ledger_name)) {
        $errors[] = "Ledger name is required";
    }
    
    $amount = validateInput($_POST['amount']);
    if (!validateNumeric($amount)) {
        $errors[] = "Amount must be a valid positive number";
    }
    
    $party_name = validateInput($_POST['party_name']);
    $party_address = validateInput($_POST['party_address']);
    $contact_number = validateInput($_POST['contact_number']);
    if (!empty($contact_number) && !validatePhone($contact_number)) {
        $errors[] = "Invalid contact number format";
    }
    
    $email = validateInput($_POST['email']);
    if (!empty($email) && !validateEmail($email)) {
        $errors[] = "Invalid email format";
    }
    
    $gst_number = validateInput($_POST['gst_number']);
    if (!empty($gst_number) && !validateGST($gst_number)) {
        $errors[] = "Invalid GST number format";
    }
    
    $pan_number = validateInput($_POST['pan_number']);
    if (!empty($pan_number) && !validatePAN($pan_number)) {
        $errors[] = "Invalid PAN number format";
    }
    
    $narration = validateInput($_POST['narration']);
    $reference_number = validateInput($_POST['reference_number']);
    
    $reference_date = validateInput($_POST['reference_date']);
    if (!empty($reference_date) && !validateDate($reference_date)) {
        $errors[] = "Invalid reference date format";
    }
    
    $voucher_type = validateInput($_POST['voucher_type']);
    $voucher_number = validateInput($_POST['voucher_number']);
    
    $bank_name = validateInput($_POST['bank_name']);
    $bank_account_number = validateInput($_POST['bank_account_number']);
    $bank_ifsc = validateInput($_POST['bank_ifsc']);
    if (!empty($bank_ifsc) && !validateIFSC($bank_ifsc)) {
        $errors[] = "Invalid IFSC code format";
    }
    
    $cheque_number = validateInput($_POST['cheque_number']);
    $cheque_date = validateInput($_POST['cheque_date']);
    if (!empty($cheque_date) && !validateDate($cheque_date)) {
        $errors[] = "Invalid cheque date format";
    }
    
    $instrument_type = validateInput($_POST['instrument_type']);
    $cost_center = validateInput($_POST['cost_center']);
    $project_id = validateInput($_POST['project_id']);
    $project_name = validateInput($_POST['project_name']);
    $expense_category = validateInput($_POST['expense_category']);
    $income_category = validateInput($_POST['income_category']);
    
    $tax_amount = validateInput($_POST['tax_amount']);
    if (!empty($tax_amount) && !validateNumeric($tax_amount)) {
        $errors[] = "Tax amount must be a valid positive number";
    }
    
    $tax_percentage = validateInput($_POST['tax_percentage']);
    if (!empty($tax_percentage) && !validateNumeric($tax_percentage)) {
        $errors[] = "Tax percentage must be a valid positive number";
    }
    
    $tax_type = validateInput($_POST['tax_type']);
    
    $cgst_amount = validateInput($_POST['cgst_amount']);
    if (!empty($cgst_amount) && !validateNumeric($cgst_amount)) {
        $errors[] = "CGST amount must be a valid positive number";
    }
    
    $sgst_amount = validateInput($_POST['sgst_amount']);
    if (!empty($sgst_amount) && !validateNumeric($sgst_amount)) {
        $errors[] = "SGST amount must be a valid positive number";
    }
    
    $igst_amount = validateInput($_POST['igst_amount']);
    if (!empty($igst_amount) && !validateNumeric($igst_amount)) {
        $errors[] = "IGST amount must be a valid positive number";
    }
    
    $cess_amount = validateInput($_POST['cess_amount']);
    if (!empty($cess_amount) && !validateNumeric($cess_amount)) {
        $errors[] = "CESS amount must be a valid positive number";
    }
    
    $discount_amount = validateInput($_POST['discount_amount']);
    if (!empty($discount_amount) && !validateNumeric($discount_amount)) {
        $errors[] = "Discount amount must be a valid positive number";
    }
    
    $round_off = validateInput($_POST['round_off']);
    if (!empty($round_off) && !validateNumeric($round_off)) {
        $errors[] = "Round off must be a valid number";
    }
    
    $total_amount = validateInput($_POST['total_amount']);
    if (!validateNumeric($total_amount)) {
        $errors[] = "Total amount must be a valid positive number";
    }
    
    $status = validateInput($_POST['status']);
    if (!in_array($status, ['Draft', 'Pending', 'Approved', 'Rejected', 'Cancelled'])) {
        $errors[] = "Invalid status";
    }
    
    $financial_year = validateInput($_POST['financial_year']);
    if (empty($financial_year)) {
        $errors[] = "Financial year is required";
    }
    
    $company_id = validateInput($_POST['company_id']);
    if (empty($company_id)) {
        $errors[] = "Company ID is required";
    }
    
    $branch_id = validateInput($_POST['branch_id']);
    $department_id = validateInput($_POST['department_id']);
    $remarks = validateInput($_POST['remarks']);
    
    // Handle file upload
    $attachment_path = validateInput($_POST['existing_attachment']);
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] == 0) {
        if (!validateFile($_FILES['attachment'])) {
            $errors[] = "Invalid file format or size. Allowed formats: jpg, jpeg, png, gif, pdf, doc, docx, xls, xlsx. Maximum size: 5MB.";
        } else {
            $target_dir = "uploads/ca_tool/";
            if (!file_exists($target_dir)) {
                mkdir($target_dir, 0777, true);
            }
            
            // Generate unique filename to prevent overwriting
            $fileType = strtolower(pathinfo($_FILES["attachment"]["name"], PATHINFO_EXTENSION));
            $newFileName = uniqid() . '.' . $fileType;
            $target_file = $target_dir . $newFileName;
            
            if (move_uploaded_file($_FILES["attachment"]["tmp_name"], $target_file)) {
                $attachment_path = $target_file;
            } else {
                $errors[] = "Error uploading file";
            }
        }
    }
    
    // If no errors, proceed with database update
    if (empty($errors)) {
        // Use prepared statements to prevent SQL injection
        $stmt = $conn->prepare("UPDATE ca_tool SET 
            transaction_date = ?, 
            transaction_type = ?, 
            ledger_id = ?, 
            ledger_name = ?, 
            amount = ?, 
            party_name = ?, 
            party_address = ?, 
            contact_number = ?, 
            email = ?, 
            gst_number = ?, 
            pan_number = ?, 
            narration = ?, 
            reference_number = ?, 
            reference_date = ?, 
            voucher_type = ?, 
            voucher_number = ?, 
            bank_name = ?, 
            bank_account_number = ?, 
            bank_ifsc = ?, 
            cheque_number = ?, 
            cheque_date = ?, 
            instrument_type = ?, 
            cost_center = ?, 
            project_id = ?, 
            project_name = ?, 
            expense_category = ?, 
            income_category = ?, 
            tax_amount = ?, 
            tax_percentage = ?, 
            tax_type = ?, 
            cgst_amount = ?, 
            sgst_amount = ?, 
            igst_amount = ?, 
            cess_amount = ?, 
            discount_amount = ?, 
            round_off = ?, 
            total_amount = ?, 
            status = ?, 
            updated_by = ?, 
            financial_year = ?, 
            company_id = ?, 
            branch_id = ?, 
            department_id = ?, 
            attachment_path = ?, 
            remarks = ?
        WHERE id = ?");
        
        $stmt->bind_param("ssssssssssssssssssssssssssssssssssssssssssssssi", 
            $transaction_date, $transaction_type, $ledger_id, $ledger_name, $amount, 
            $party_name, $party_address, $contact_number, $email, $gst_number, $pan_number, $narration, 
            $reference_number, $reference_date, $voucher_type, $voucher_number, $bank_name, 
            $bank_account_number, $bank_ifsc, $cheque_number, $cheque_date, $instrument_type, 
            $cost_center, $project_id, $project_name, $expense_category, $income_category, $tax_amount, 
            $tax_percentage, $tax_type, $cgst_amount, $sgst_amount, $igst_amount, $cess_amount, 
            $discount_amount, $round_off, $total_amount, $status, $user_id, $financial_year, 
            $company_id, $branch_id, $department_id, $attachment_path, $remarks, $id
        );
        
        if ($stmt->execute()) {
            $message = "Transaction updated successfully!";
            $message_type = "success";
        } else {
            $message = "Error: " . $stmt->error;
            $message_type = "error";
        }
        
        $stmt->close();
    } else {
        $message = "Validation errors: " . implode(", ", $errors);
        $message_type = "error";
    }
}
// Delete transaction
if (isset($_POST['delete_transaction'])) {
    $id = validateInput($_POST['id']);
    
    if (is_numeric($id)) {
        // Use prepared statement to prevent SQL injection
        $stmt = $conn->prepare("DELETE FROM ca_tool WHERE id = ?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            $message = "Transaction deleted successfully!";
            $message_type = "success";
        } else {
            $message = "Error: " . $stmt->error;
            $message_type = "error";
        }
        
        $stmt->close();
    } else {
        $message = "Invalid transaction ID";
        $message_type = "error";
    }
}
// Approve transaction
if (isset($_POST['approve_transaction'])) {
    $id = validateInput($_POST['id']);
    
    if (is_numeric($id)) {
        // Use prepared statement to prevent SQL injection
        $stmt = $conn->prepare("UPDATE ca_tool SET 
            status = 'Approved', 
            approved_by = ?, 
            approved_at = NOW()
        WHERE id = ?");
        $stmt->bind_param("ii", $user_id, $id);
        
        if ($stmt->execute()) {
            $message = "Transaction approved successfully!";
            $message_type = "success";
        } else {
            $message = "Error: " . $stmt->error;
            $message_type = "error";
        }
        
        $stmt->close();
    } else {
        $message = "Invalid transaction ID";
        $message_type = "error";
    }
}
// Get all transactions
$transactions = [];
$stmt = $conn->prepare("SELECT * FROM ca_tool ORDER BY transaction_date DESC, created_at DESC");
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $transactions[] = $row;
    }
}
$stmt->close();
// Get transaction types
$transaction_types = ['Payment', 'Receipt', 'Contra', 'Journal', 'Sales', 'Purchase'];
// Get financial years
$financial_years = [];
$current_year = date('Y');
for ($i = $current_year - 5; $i <= $current_year + 1; $i++) {
    $financial_years[] = ($i) . '-' . ($i + 1);
}
// Default financial year
$current_month = date('m');
if ($current_month >= 4) {
    $default_financial_year = date('Y') . '-' . (date('Y') + 1);
} else {
    $default_financial_year = (date('Y') - 1) . '-' . date('Y');
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CA Tool - BMB</title>
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
        
        .status-draft {
            background-color: #e2e3e5;
            color: #383d41;
        }
        
        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .status-approved {
            background-color: #d4edda;
            color: #155724;
        }
        
        .status-rejected {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .status-cancelled {
            background-color: #e2e3e5;
            color: #383d41;
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
            margin: 2% auto;
            padding: 0;
            border-radius: 8px;
            width: 90%;
            max-width: 900px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
            max-height: 90vh;
            overflow-y: auto;
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
            width: 200px;
            color: #555;
        }
        
        .detail-value {
            flex: 1;
        }
        
        .form-row {
            display: flex;
            gap: 20px;
        }
        
        .form-col {
            flex: 1;
        }
        
        .filter-section {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .filter-row {
            display: flex;
            gap: 15px;
            align-items: end;
        }
        
        .filter-group {
            flex: 1;
        }
        
        .error {
            color: #e74c3c;
            font-size: 14px;
            margin-top: 5px;
        }
        
        .required:after {
            content: " *";
            color: #e74c3c;
        }
        
        @media (max-width: 768px) {
            .navbar h1 {
                margin-left: 0;
                font-size: 20px;
            }
            
            .logo {
                width: 180px;
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
            
            .form-row {
                flex-direction: column;
            }
            
            .filter-row {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <img src="https://ik.imagekit.io/nuvq7aygp/BMB_Logo.jpg" alt="BMB Logo" class="logo">
    
    <div class="navbar">
        <h1>CA Tool - Chartered Accountant Software</h1>
        <div class="user-info">
            <span>Welcome, <?php echo htmlspecialchars($user_name); ?></span>
            <a href="start.php">Back to Dashboard</a>
        </div>
    </div>
    
    <div class="container">
        <?php if (!empty($message)): ?>
            <div class="message message-<?php echo $message_type; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <div class="tabs">
            <div class="tab active" onclick="openTab(event, 'new-transaction')">New Transaction</div>
            <div class="tab" onclick="openTab(event, 'view-transactions')">All Transactions</div>
            <div class="tab" onclick="openTab(event, 'reports')">Reports</div>
        </div>
        
        <div id="new-transaction" class="tab-content active">
            <div class="card">
                <div class="card-header">Create New Transaction</div>
                <div class="card-body">
                    <form method="POST" action="" enctype="multipart/form-data" id="transactionForm">
                        <div class="form-row">
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="transaction_date" class="required">Transaction Date</label>
                                    <input type="date" id="transaction_date" name="transaction_date" value="<?php echo date('Y-m-d'); ?>" required>
                                    <div class="error" id="transaction_date_error"></div>
                                </div>
                            </div>
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="transaction_type" class="required">Transaction Type</label>
                                    <select id="transaction_type" name="transaction_type" required>
                                        <option value="">Select Transaction Type</option>
                                        <?php foreach ($transaction_types as $type): ?>
                                            <option value="<?php echo $type; ?>"><?php echo $type; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="error" id="transaction_type_error"></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="ledger_id" class="required">Ledger ID</label>
                                    <input type="text" id="ledger_id" name="ledger_id" required placeholder="Enter Ledger ID">
                                    <div class="error" id="ledger_id_error"></div>
                                </div>
                            </div>
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="ledger_name" class="required">Ledger Name</label>
                                    <input type="text" id="ledger_name" name="ledger_name" required placeholder="Enter Ledger Name">
                                    <div class="error" id="ledger_name_error"></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="amount" class="required">Amount</label>
                                    <input type="number" id="amount" name="amount" step="0.01" required placeholder="0.00">
                                    <div class="error" id="amount_error"></div>
                                </div>
                            </div>
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="total_amount" class="required">Total Amount</label>
                                    <input type="number" id="total_amount" name="total_amount" step="0.01" required placeholder="0.00">
                                    <div class="error" id="total_amount_error"></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="party_name">Party Name</label>
                                    <input type="text" id="party_name" name="party_name" placeholder="Enter Party Name">
                                </div>
                            </div>
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="contact_number">Contact Number</label>
                                    <input type="text" id="contact_number" name="contact_number" placeholder="Enter Contact Number">
                                    <div class="error" id="contact_number_error"></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="email">Email</label>
                                    <input type="email" id="email" name="email" placeholder="Enter Email Address">
                                    <div class="error" id="email_error"></div>
                                </div>
                            </div>
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="gst_number">GST Number</label>
                                    <input type="text" id="gst_number" name="gst_number" placeholder="Enter GST Number">
                                    <div class="error" id="gst_number_error"></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="pan_number">PAN Number</label>
                                    <input type="text" id="pan_number" name="pan_number" placeholder="Enter PAN Number">
                                    <div class="error" id="pan_number_error"></div>
                                </div>
                            </div>
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="voucher_type">Voucher Type</label>
                                    <input type="text" id="voucher_type" name="voucher_type" placeholder="Enter Voucher Type">
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="voucher_number">Voucher Number</label>
                                    <input type="text" id="voucher_number" name="voucher_number" placeholder="Enter Voucher Number">
                                </div>
                            </div>
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="reference_number">Reference Number</label>
                                    <input type="text" id="reference_number" name="reference_number" placeholder="Enter Reference Number">
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="reference_date">Reference Date</label>
                                    <input type="date" id="reference_date" name="reference_date">
                                    <div class="error" id="reference_date_error"></div>
                                </div>
                            </div>
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="financial_year" class="required">Financial Year</label>
                                    <select id="financial_year" name="financial_year" required>
                                        <?php foreach ($financial_years as $year): ?>
                                            <option value="<?php echo $year; ?>" <?php echo ($year == $default_financial_year) ? 'selected' : ''; ?>><?php echo $year; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="error" id="financial_year_error"></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="bank_name">Bank Name</label>
                                    <input type="text" id="bank_name" name="bank_name" placeholder="Enter Bank Name">
                                </div>
                            </div>
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="bank_account_number">Bank Account Number</label>
                                    <input type="text" id="bank_account_number" name="bank_account_number" placeholder="Enter Bank Account Number">
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="bank_ifsc">Bank IFSC Code</label>
                                    <input type="text" id="bank_ifsc" name="bank_ifsc" placeholder="Enter Bank IFSC Code">
                                    <div class="error" id="bank_ifsc_error"></div>
                                </div>
                            </div>
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="cheque_number">Cheque Number</label>
                                    <input type="text" id="cheque_number" name="cheque_number" placeholder="Enter Cheque Number">
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="cheque_date">Cheque Date</label>
                                    <input type="date" id="cheque_date" name="cheque_date">
                                    <div class="error" id="cheque_date_error"></div>
                                </div>
                            </div>
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="instrument_type">Instrument Type</label>
                                    <input type="text" id="instrument_type" name="instrument_type" placeholder="Enter Instrument Type">
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="tax_amount">Tax Amount</label>
                                    <input type="number" id="tax_amount" name="tax_amount" step="0.01" placeholder="0.00">
                                    <div class="error" id="tax_amount_error"></div>
                                </div>
                            </div>
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="tax_percentage">Tax Percentage</label>
                                    <input type="number" id="tax_percentage" name="tax_percentage" step="0.01" placeholder="0.00">
                                    <div class="error" id="tax_percentage_error"></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="tax_type">Tax Type</label>
                                    <input type="text" id="tax_type" name="tax_type" placeholder="Enter Tax Type">
                                </div>
                            </div>
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="cgst_amount">CGST Amount</label>
                                    <input type="number" id="cgst_amount" name="cgst_amount" step="0.01" placeholder="0.00">
                                    <div class="error" id="cgst_amount_error"></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="sgst_amount">SGST Amount</label>
                                    <input type="number" id="sgst_amount" name="sgst_amount" step="0.01" placeholder="0.00">
                                    <div class="error" id="sgst_amount_error"></div>
                                </div>
                            </div>
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="igst_amount">IGST Amount</label>
                                    <input type="number" id="igst_amount" name="igst_amount" step="0.01" placeholder="0.00">
                                    <div class="error" id="igst_amount_error"></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="cess_amount">CESS Amount</label>
                                    <input type="number" id="cess_amount" name="cess_amount" step="0.01" placeholder="0.00">
                                    <div class="error" id="cess_amount_error"></div>
                                </div>
                            </div>
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="discount_amount">Discount Amount</label>
                                    <input type="number" id="discount_amount" name="discount_amount" step="0.01" placeholder="0.00">
                                    <div class="error" id="discount_amount_error"></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="round_off">Round Off</label>
                                    <input type="number" id="round_off" name="round_off" step="0.01" placeholder="0.00">
                                    <div class="error" id="round_off_error"></div>
                                </div>
                            </div>
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="status" class="required">Status</label>
                                    <select id="status" name="status" required>
                                        <option value="Draft" selected>Draft</option>
                                        <option value="Pending">Pending</option>
                                        <option value="Approved">Approved</option>
                                        <option value="Rejected">Rejected</option>
                                        <option value="Cancelled">Cancelled</option>
                                    </select>
                                    <div class="error" id="status_error"></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="cost_center">Cost Center</label>
                                    <input type="text" id="cost_center" name="cost_center" placeholder="Enter Cost Center">
                                </div>
                            </div>
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="project_id">Project ID</label>
                                    <input type="text" id="project_id" name="project_id" placeholder="Enter Project ID">
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="project_name">Project Name</label>
                                    <input type="text" id="project_name" name="project_name" placeholder="Enter Project Name">
                                </div>
                            </div>
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="expense_category">Expense Category</label>
                                    <input type="text" id="expense_category" name="expense_category" placeholder="Enter Expense Category">
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="income_category">Income Category</label>
                                    <input type="text" id="income_category" name="income_category" placeholder="Enter Income Category">
                                </div>
                            </div>
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="company_id" class="required">Company ID</label>
                                    <input type="text" id="company_id" name="company_id" value="1" required>
                                    <div class="error" id="company_id_error"></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="branch_id">Branch ID</label>
                                    <input type="text" id="branch_id" name="branch_id" placeholder="Enter Branch ID">
                                </div>
                            </div>
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="department_id">Department ID</label>
                                    <input type="text" id="department_id" name="department_id" placeholder="Enter Department ID">
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="party_address">Party Address</label>
                            <textarea id="party_address" name="party_address" placeholder="Enter Party Address"></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="narration">Narration</label>
                            <textarea id="narration" name="narration" placeholder="Enter Narration"></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="remarks">Remarks</label>
                            <textarea id="remarks" name="remarks" placeholder="Enter Remarks"></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="attachment">Attachment</label>
                            <input type="file" id="attachment" name="attachment">
                            <div class="error" id="attachment_error"></div>
                            <small>Allowed formats: jpg, jpeg, png, gif, pdf, doc, docx, xls, xlsx. Maximum size: 5MB.</small>
                        </div>
                        
                        <button type="submit" name="create_transaction" class="btn">Create Transaction</button>
                    </form>
                </div>
            </div>
        </div>
        
        <div id="view-transactions" class="tab-content">
            <div class="card">
                <div class="card-header">All Transactions</div>
                <div class="card-body">
                    <div class="filter-section">
                        <form method="GET" action="">
                            <div class="filter-row">
                                <div class="filter-group">
                                    <label for="filter_transaction_type">Transaction Type</label>
                                    <select id="filter_transaction_type" name="filter_transaction_type">
                                        <option value="">All Types</option>
                                        <?php foreach ($transaction_types as $type): ?>
                                            <option value="<?php echo $type; ?>"><?php echo $type; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="filter-group">
                                    <label for="filter_status">Status</label>
                                    <select id="filter_status" name="filter_status">
                                        <option value="">All Status</option>
                                        <option value="Draft">Draft</option>
                                        <option value="Pending">Pending</option>
                                        <option value="Approved">Approved</option>
                                        <option value="Rejected">Rejected</option>
                                        <option value="Cancelled">Cancelled</option>
                                    </select>
                                </div>
                                <div class="filter-group">
                                    <label for="filter_financial_year">Financial Year</label>
                                    <select id="filter_financial_year" name="filter_financial_year">
                                        <option value="">All Years</option>
                                        <?php foreach ($financial_years as $year): ?>
                                            <option value="<?php echo $year; ?>" <?php echo ($year == $default_financial_year) ? 'selected' : ''; ?>><?php echo $year; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="filter-group">
                                    <label>&nbsp;</label>
                                    <button type="submit" class="btn">Apply Filters</button>
                                </div>
                            </div>
                        </form>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Transaction ID</th>
                                    <th>Date</th>
                                    <th>Type</th>
                                    <th>Ledger Name</th>
                                    <th>Amount</th>
                                    <th>Party Name</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($transactions)): ?>
                                    <tr>
                                        <td colspan="9">No transactions found.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($transactions as $transaction): ?>
                                        <tr>
                                            <td><?php echo $transaction['id']; ?></td>
                                            <td><?php echo $transaction['transaction_id']; ?></td>
                                            <td><?php echo date('M j, Y', strtotime($transaction['transaction_date'])); ?></td>
                                            <td><?php echo htmlspecialchars($transaction['transaction_type']); ?></td>
                                            <td><?php echo htmlspecialchars($transaction['ledger_name']); ?></td>
                                            <td><?php echo number_format($transaction['amount'], 2); ?></td>
                                            <td><?php echo htmlspecialchars($transaction['party_name']); ?></td>
                                            <td><span class="status status-<?php echo strtolower($transaction['status']); ?>"><?php echo $transaction['status']; ?></span></td>
                                            <td>
                                                <button class="btn btn-sm" onclick="viewTransaction(<?php echo $transaction['id']; ?>)">View</button>
                                                <button class="btn btn-sm btn-warning" onclick="editTransaction(<?php echo $transaction['id']; ?>)">Edit</button>
                                                <?php if ($transaction['status'] == 'Draft' || $transaction['status'] == 'Pending'): ?>
                                                    <button class="btn btn-sm btn-success" onclick="approveTransaction(<?php echo $transaction['id']; ?>)">Approve</button>
                                                <?php endif; ?>
                                                <button class="btn btn-sm btn-danger" onclick="deleteTransaction(<?php echo $transaction['id']; ?>)">Delete</button>
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
        
        <div id="reports" class="tab-content">
            <div class="card">
                <div class="card-header">Financial Reports</div>
                <div class="card-body">
                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="report_type">Report Type</label>
                                <select id="report_type" name="report_type">
                                    <option value="trial_balance">Trial Balance</option>
                                    <option value="profit_loss">Profit & Loss</option>
                                    <option value="balance_sheet">Balance Sheet</option>
                                    <option value="cash_flow">Cash Flow</option>
                                    <option value="ledger_report">Ledger Report</option>
                                    <option value="day_book">Day Book</option>
                                    <option value="bank_book">Bank Book</option>
                                    <option value="sales_register">Sales Register</option>
                                    <option value="purchase_register">Purchase Register</option>
                                    <option value="tax_report">Tax Report</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="report_financial_year">Financial Year</label>
                                <select id="report_financial_year" name="report_financial_year">
                                    <?php foreach ($financial_years as $year): ?>
                                        <option value="<?php echo $year; ?>" <?php echo ($year == $default_financial_year) ? 'selected' : ''; ?>><?php echo $year; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="from_date">From Date</label>
                                <input type="date" id="from_date" name="from_date" value="<?php echo date('Y-m-01'); ?>">
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="to_date">To Date</label>
                                <input type="date" id="to_date" name="to_date" value="<?php echo date('Y-m-d'); ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="report_format">Report Format</label>
                        <select id="report_format" name="report_format">
                            <option value="html">HTML</option>
                            <option value="pdf">PDF</option>
                            <option value="excel">Excel</option>
                        </select>
                    </div>
                    
                    <button type="button" class="btn" onclick="generateReport()">Generate Report</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- View Transaction Modal -->
    <div id="viewTransactionModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Transaction Details</h2>
                <span class="close" onclick="closeModal('viewTransactionModal')">&times;</span>
            </div>
            <div class="modal-body" id="viewTransactionContent">
                <!-- Content will be loaded dynamically -->
            </div>
            <div class="modal-footer">
                <button class="btn" onclick="closeModal('viewTransactionModal')">Close</button>
            </div>
        </div>
    </div>
    
    <!-- Edit Transaction Modal -->
    <div id="editTransactionModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit Transaction</h2>
                <span class="close" onclick="closeModal('editTransactionModal')">&times;</span>
            </div>
            <div class="modal-body" id="editTransactionContent">
                <!-- Content will be loaded dynamically -->
            </div>
            <div class="modal-footer">
                <button class="btn" onclick="closeModal('editTransactionModal')">Cancel</button>
            </div>
        </div>
    </div>
    
    <script>
        // Client-side validation functions
        function validateDate(dateString) {
            const regex = /^\d{4}-\d{2}-\d{2}$/;
            if (!regex.test(dateString)) return false;
            
            const date = new Date(dateString);
            return !isNaN(date.getTime());
        }
        
        function validateEmail(email) {
            const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return regex.test(email);
        }
        
        function validatePhone(phone) {
            const regex = /^[0-9]{10,15}$/;
            return regex.test(phone);
        }
        
        function validateGST(gst) {
            const regex = /^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[0-9]{1}Z[0-9A-Z]{1}$/;
            return regex.test(gst.toUpperCase());
        }
        
        function validatePAN(pan) {
            const regex = /^[A-Z]{3}[P]{1}[A-Z]{1}[0-9]{4}[A-Z]{1}$/;
            return regex.test(pan.toUpperCase());
        }
        
        function validateIFSC(ifsc) {
            const regex = /^[A-Z]{4}0[A-Z0-9]{6}$/;
            return regex.test(ifsc.toUpperCase());
        }
        
        function validateNumber(value) {
            return !isNaN(value) && value !== '' && parseFloat(value) >= 0;
        }
        
        // Form validation on submit
        document.getElementById('transactionForm').addEventListener('submit', function(e) {
            let isValid = true;
            
            // Clear previous errors
            const errorElements = document.getElementsByClassName('error');
            for (let i = 0; i < errorElements.length; i++) {
                errorElements[i].textContent = '';
            }
            
            // Validate transaction date
            const transactionDate = document.getElementById('transaction_date').value;
            if (!transactionDate) {
                document.getElementById('transaction_date_error').textContent = 'Transaction date is required';
                isValid = false;
            } else if (!validateDate(transactionDate)) {
                document.getElementById('transaction_date_error').textContent = 'Invalid date format';
                isValid = false;
            }
            
            // Validate transaction type
            const transactionType = document.getElementById('transaction_type').value;
            if (!transactionType) {
                document.getElementById('transaction_type_error').textContent = 'Transaction type is required';
                isValid = false;
            }
            
            // Validate ledger ID
            const ledgerId = document.getElementById('ledger_id').value;
            if (!ledgerId) {
                document.getElementById('ledger_id_error').textContent = 'Ledger ID is required';
                isValid = false;
            }
            
            // Validate ledger name
            const ledgerName = document.getElementById('ledger_name').value;
            if (!ledgerName) {
                document.getElementById('ledger_name_error').textContent = 'Ledger name is required';
                isValid = false;
            }
            
            // Validate amount
            const amount = document.getElementById('amount').value;
            if (!amount) {
                document.getElementById('amount_error').textContent = 'Amount is required';
                isValid = false;
            } else if (!validateNumber(amount)) {
                document.getElementById('amount_error').textContent = 'Amount must be a valid positive number';
                isValid = false;
            }
            
            // Validate total amount
            const totalAmount = document.getElementById('total_amount').value;
            if (!totalAmount) {
                document.getElementById('total_amount_error').textContent = 'Total amount is required';
                isValid = false;
            } else if (!validateNumber(totalAmount)) {
                document.getElementById('total_amount_error').textContent = 'Total amount must be a valid positive number';
                isValid = false;
            }
            
            // Validate contact number if provided
            const contactNumber = document.getElementById('contact_number').value;
            if (contactNumber && !validatePhone(contactNumber)) {
                document.getElementById('contact_number_error').textContent = 'Invalid contact number format';
                isValid = false;
            }
            
            // Validate email if provided
            const email = document.getElementById('email').value;
            if (email && !validateEmail(email)) {
                document.getElementById('email_error').textContent = 'Invalid email format';
                isValid = false;
            }
            
            // Validate GST if provided
            const gstNumber = document.getElementById('gst_number').value;
            if (gstNumber && !validateGST(gstNumber)) {
                document.getElementById('gst_number_error').textContent = 'Invalid GST number format';
                isValid = false;
            }
            
            // Validate PAN if provided
            const panNumber = document.getElementById('pan_number').value;
            if (panNumber && !validatePAN(panNumber)) {
                document.getElementById('pan_number_error').textContent = 'Invalid PAN number format';
                isValid = false;
            }
            
            // Validate reference date if provided
            const referenceDate = document.getElementById('reference_date').value;
            if (referenceDate && !validateDate(referenceDate)) {
                document.getElementById('reference_date_error').textContent = 'Invalid date format';
                isValid = false;
            }
            
            // Validate financial year
            const financialYear = document.getElementById('financial_year').value;
            if (!financialYear) {
                document.getElementById('financial_year_error').textContent = 'Financial year is required';
                isValid = false;
            }
            
            // Validate bank IFSC if provided
            const bankIfsc = document.getElementById('bank_ifsc').value;
            if (bankIfsc && !validateIFSC(bankIfsc)) {
                document.getElementById('bank_ifsc_error').textContent = 'Invalid IFSC code format';
                isValid = false;
            }
            
            // Validate cheque date if provided
            const chequeDate = document.getElementById('cheque_date').value;
            if (chequeDate && !validateDate(chequeDate)) {
                document.getElementById('cheque_date_error').textContent = 'Invalid date format';
                isValid = false;
            }
            
            // Validate tax amount if provided
            const taxAmount = document.getElementById('tax_amount').value;
            if (taxAmount && !validateNumber(taxAmount)) {
                document.getElementById('tax_amount_error').textContent = 'Tax amount must be a valid positive number';
                isValid = false;
            }
            
            // Validate tax percentage if provided
            const taxPercentage = document.getElementById('tax_percentage').value;
            if (taxPercentage && !validateNumber(taxPercentage)) {
                document.getElementById('tax_percentage_error').textContent = 'Tax percentage must be a valid positive number';
                isValid = false;
            }
            
            // Validate CGST amount if provided
            const cgstAmount = document.getElementById('cgst_amount').value;
            if (cgstAmount && !validateNumber(cgstAmount)) {
                document.getElementById('cgst_amount_error').textContent = 'CGST amount must be a valid positive number';
                isValid = false;
            }
            
            // Validate SGST amount if provided
            const sgstAmount = document.getElementById('sgst_amount').value;
            if (sgstAmount && !validateNumber(sgstAmount)) {
                document.getElementById('sgst_amount_error').textContent = 'SGST amount must be a valid positive number';
                isValid = false;
            }
            
            // Validate IGST amount if provided
            const igstAmount = document.getElementById('igst_amount').value;
            if (igstAmount && !validateNumber(igstAmount)) {
                document.getElementById('igst_amount_error').textContent = 'IGST amount must be a valid positive number';
                isValid = false;
            }
            
            // Validate CESS amount if provided
            const cessAmount = document.getElementById('cess_amount').value;
            if (cessAmount && !validateNumber(cessAmount)) {
                document.getElementById('cess_amount_error').textContent = 'CESS amount must be a valid positive number';
                isValid = false;
            }
            
            // Validate discount amount if provided
            const discountAmount = document.getElementById('discount_amount').value;
            if (discountAmount && !validateNumber(discountAmount)) {
                document.getElementById('discount_amount_error').textContent = 'Discount amount must be a valid positive number';
                isValid = false;
            }
            
            // Validate round off if provided
            const roundOff = document.getElementById('round_off').value;
            if (roundOff && !validateNumber(roundOff)) {
                document.getElementById('round_off_error').textContent = 'Round off must be a valid number';
                isValid = false;
            }
            
            // Validate status
            const status = document.getElementById('status').value;
            if (!status) {
                document.getElementById('status_error').textContent = 'Status is required';
                isValid = false;
            }
            
            // Validate company ID
            const companyId = document.getElementById('company_id').value;
            if (!companyId) {
                document.getElementById('company_id_error').textContent = 'Company ID is required';
                isValid = false;
            }
            
            // Validate attachment if provided
            const attachment = document.getElementById('attachment').files[0];
            if (attachment) {
                const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
                const maxSize = 5 * 1024 * 1024; // 5MB
                
                if (!allowedTypes.includes(attachment.type)) {
                    document.getElementById('attachment_error').textContent = 'Invalid file type';
                    isValid = false;
                }
                
                if (attachment.size > maxSize) {
                    document.getElementById('attachment_error').textContent = 'File size exceeds 5MB limit';
                    isValid = false;
                }
            }
            
            // Prevent form submission if validation fails
            if (!isValid) {
                e.preventDefault();
            }
        });
        
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
        function viewTransaction(transactionId) {
            // Make AJAX request to get transaction details
            var xhr = new XMLHttpRequest();
            xhr.onreadystatechange = function() {
                if (xhr.readyState == 4 && xhr.status == 200) {
                    document.getElementById("viewTransactionContent").innerHTML = xhr.responseText;
                    document.getElementById("viewTransactionModal").style.display = "block";
                }
            };
            xhr.open("GET", "get_ca_transaction_details.php?id=" + transactionId, true);
            xhr.send();
        }
        
        function editTransaction(transactionId) {
            // Make AJAX request to get transaction details for editing
            var xhr = new XMLHttpRequest();
            xhr.onreadystatechange = function() {
                if (xhr.readyState == 4 && xhr.status == 200) {
                    document.getElementById("editTransactionContent").innerHTML = xhr.responseText;
                    document.getElementById("editTransactionModal").style.display = "block";
                }
            };
            xhr.open("GET", "get_ca_transaction_edit.php?id=" + transactionId, true);
            xhr.send();
        }
        
        function approveTransaction(transactionId) {
            if (confirm("Are you sure you want to approve this transaction?")) {
                var form = document.createElement("form");
                form.method = "POST";
                form.action = "";
                
                var input = document.createElement("input");
                input.type = "hidden";
                input.name = "approve_transaction";
                input.value = "1";
                form.appendChild(input);
                
                var idInput = document.createElement("input");
                idInput.type = "hidden";
                idInput.name = "id";
                idInput.value = transactionId;
                form.appendChild(idInput);
                
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function deleteTransaction(transactionId) {
            if (confirm("Are you sure you want to delete this transaction? This action cannot be undone.")) {
                var form = document.createElement("form");
                form.method = "POST";
                form.action = "";
                
                var input = document.createElement("input");
                input.type = "hidden";
                input.name = "delete_transaction";
                input.value = "1";
                form.appendChild(input);
                
                var idInput = document.createElement("input");
                idInput.type = "hidden";
                idInput.name = "id";
                idInput.value = transactionId;
                form.appendChild(idInput);
                
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = "none";
        }
        
        // Generate report
        function generateReport() {
            var reportType = document.getElementById("report_type").value;
            var financialYear = document.getElementById("report_financial_year").value;
            var fromDate = document.getElementById("from_date").value;
            var toDate = document.getElementById("to_date").value;
            var format = document.getElementById("report_format").value;
            
            // Redirect to report generation page with parameters
            window.location.href = "generate_ca_report.php?type=" + reportType + 
                                 "&year=" + financialYear + 
                                 "&from=" + fromDate + 
                                 "&to=" + toDate + 
                                 "&format=" + format;
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