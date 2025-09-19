<?php
// Include the configuration file
require_once 'config.php';
// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);
// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
// Handle form submissions
$message = "";
$message_type = "";
$show_verification = false;
$verification_email = "";
$verification_password = "";
$generated_identifier = "";
$show_complete_verification = false;
if (isset($_POST['signup'])) {
    // Signup form submitted
    $name = $conn->real_escape_string($_POST['name']);
    $email = $conn->real_escape_string($_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $department = $conn->real_escape_string($_POST['department']);
    
    // Generate a unique identifier
    $unique_identifier = substr(md5(uniqid(rand(), true)), 0, 8);
    $generated_identifier = $unique_identifier;
    
    // Check if email already exists
    $check_email = $conn->query("SELECT id FROM employees WHERE email='$email'");
    
    if ($check_email->num_rows > 0) {
        $message = "Email already exists. Please use a different email.";
        $message_type = "error";
    } else {
        // Insert new user with valid_user set to false by default
        $sql = "INSERT INTO employees (name, email, password, department, unique_identifier, valid_user) 
                VALUES ('$name', '$email', '$password', '$department', '$unique_identifier', FALSE)";
        
        if ($conn->query($sql)) {
            // Success - show verification section
            $show_verification = true;
            $verification_email = $email;
            $verification_password = $_POST['password'];
            $message = "Signup successful! Please check your email for the verification code.";
            $message_type = "info";
        } else {
            $message = "Error: " . $conn->error;
            $message_type = "error";
        }
    }
}
if (isset($_POST['verify'])) {
    // Verification form submitted
    $email = $conn->real_escape_string($_POST['email']);
    $verification_code = $conn->real_escape_string($_POST['verification_code']);
    
    // Check if the verification code matches
    $check_code = $conn->query("SELECT id, unique_identifier FROM employees WHERE email='$email'");
    
    if ($check_code->num_rows > 0) {
        $row = $check_code->fetch_assoc();
        $stored_identifier = $row['unique_identifier'];
        
        if ($verification_code === $stored_identifier) {
            // Update valid_user to true
            $update_sql = "UPDATE employees SET valid_user=TRUE WHERE email='$email'";
            
            if ($conn->query($update_sql)) {
                $message = "Verification successful! You can now login.";
                $message_type = "success";
                
                // Start session and redirect to start.php
                session_start();
                $_SESSION['user_id'] = $row['id'];
                $_SESSION['user_email'] = $email;
                $_SESSION['user_name'] = $row['name'];
                
                // Redirect to start page
                header("Location: start.php");
                exit();
            } else {
                $message = "Error updating verification status: " . $conn->error;
                $message_type = "error";
            }
        } else {
            $message = "Invalid verification code. Please try again.";
            $message_type = "error";
        }
    } else {
        $message = "Email not found. Please sign up first.";
        $message_type = "error";
    }
}
if (isset($_POST['complete_verification'])) {
    // Complete Verification form submitted
    $email = $conn->real_escape_string($_POST['email']);
    $password = $_POST['password'];
    $verification_code = $conn->real_escape_string($_POST['verification_code']);
    
    // Check credentials
    $sql = "SELECT * FROM employees WHERE email='$email'";
    $result = $conn->query($sql);
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        
        // Verify password
        if (password_verify($password, $row['password'])) {
            // Verify identifier matches
            if ($verification_code === $row['unique_identifier']) {
                // Update valid_user to true
                $update_sql = "UPDATE employees SET valid_user=TRUE WHERE email='$email'";
                
                if ($conn->query($update_sql)) {
                    $message = "Verification completed successfully! You can now login.";
                    $message_type = "success";
                    
                    // Start session and redirect to start.php
                    session_start();
                    $_SESSION['user_id'] = $row['id'];
                    $_SESSION['user_email'] = $email;
                    $_SESSION['user_name'] = $row['name'];
                    
                    // Redirect to start page
                    header("Location: start.php");
                    exit();
                } else {
                    $message = "Error updating verification status: " . $conn->error;
                    $message_type = "error";
                }
            } else {
                $message = "Invalid verification code.";
                $message_type = "error";
            }
        } else {
            $message = "Invalid password.";
            $message_type = "error";
        }
    } else {
        $message = "No account found with this email.";
        $message_type = "error";
    }
}
if (isset($_POST['login'])) {
    // Login form submitted
    $email = $conn->real_escape_string($_POST['email']);
    $password = $_POST['password'];
    $identifier = $conn->real_escape_string($_POST['unique_identifier']);
    
    // Check credentials
    $sql = "SELECT * FROM employees WHERE email='$email'";
    $result = $conn->query($sql);
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        
        // Verify password
        if (password_verify($password, $row['password'])) {
            // Verify identifier and valid_user status
            if ($identifier === $row['unique_identifier']) {
                if ($row['valid_user']) {
                    $message = "Login successful! Redirecting...";
                    $message_type = "success";
                    
                    // Start session and redirect to start.php
                    session_start();
                    $_SESSION['user_id'] = $row['id'];
                    $_SESSION['user_email'] = $row['email'];
                    $_SESSION['user_name'] = $row['name'];
                    
                    // Redirect to start page
                    header("Location: start.php");
                    exit();
                } else {
                    $message = "Account not verified. Please complete verification first.";
                    $message_type = "error";
                    $show_complete_verification = true;
                    $verification_email = $email;
                }
            } else {
                $message = "Invalid unique identifier.";
                $message_type = "error";
            }
        } else {
            $message = "Invalid password.";
            $message_type = "error";
        }
    } else {
        $message = "No account found with this email.";
        $message_type = "error";
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BMB View Portal</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            min-height: 100vh;
            display: flex;
            padding: 0;
            margin: 0;
            overflow: hidden;
            position: relative;
        }
        
        .logo {
            position: absolute;
            top: 20px;
            left: 20px;
            width: 230px;
            z-index: 1000;
        }
        
        .left-section {
            flex: 1;
            background-image: url('https://images.pexels.com/photos/694742/pexels-photo-694742.jpeg');
            background-size: cover;
            background-position: center;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .left-section::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.4);
            z-index: 1;
        }
        
        .left-content {
            position: relative;
            z-index: 2;
            text-align: center;
            color: white;
            padding: 20px;
            max-width: 80%;
        }
        
        .left-content h1 {
            font-size: 3rem;
            margin-bottom: 20px;
            font-weight: 700;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
        }
        
        .left-content p {
            font-size: 1.2rem;
            line-height: 1.6;
            opacity: 0.9;
        }
        
        .right-section {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f8f9fa;
            padding: 20px;
            position: relative;
            overflow-y: auto;
        }
        
        .container {
            width: 100%;
            max-width: 500px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            position: relative;
            z-index: 1;
            animation: slideUp 0.6s ease-out;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .header {
            background: #4a6fc7;
            color: white;
            text-align: center;
            padding: 25px 20px;
            position: relative;
            overflow: hidden;
        }
        
        .header::after {
            content: "";
            position: absolute;
            bottom: -10px;
            left: 0;
            width: 100%;
            height: 20px;
            background: white;
            border-radius: 50% 50% 0 0;
        }
        
        .header h1 {
            font-size: 24px;
            font-weight: 600;
            position: relative;
            z-index: 1;
        }
        
        .form-container {
            padding: 30px;
        }
        
        .tabs {
            display: flex;
            margin-bottom: 25px;
            border-bottom: 2px solid #f1f1f1;
        }
        
        .tab {
            padding: 12px 20px;
            cursor: pointer;
            font-weight: 500;
            color: #888;
            text-align: center;
            flex: 1;
            transition: all 0.3s;
        }
        
        .tab.active {
            color: #4a6fc7;
            border-bottom: 3px solid #4a6fc7;
        }
        
        .form-section {
            display: none;
        }
        
        .form-section.active {
            display: block;
            animation: fadeIn 0.5s ease;
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
        
        .form-group input {
            width: 100%;
            padding: 14px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 16px;
            transition: all 0.3s;
        }
        
        .form-group input:focus {
            border-color: #4a6fc7;
            outline: none;
            box-shadow: 0 0 0 3px rgba(74, 111, 199, 0.2);
        }
        
        .btn {
            width: 100%;
            padding: 14px;
            background: #4a6fc7;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }
        
        .btn::before {
            content: "";
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            transition: left 0.5s;
        }
        
        .btn:hover::before {
            left: 100%;
        }
        
        .btn:hover {
            background: #3b5aa6;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        
        .message {
            padding: 12px;
            margin-top: 20px;
            border-radius: 6px;
            text-align: center;
            font-weight: 500;
            animation: fadeIn 0.5s ease;
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
        
        .info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        
        .hidden {
            display: none;
        }
        
        .footer {
            text-align: center;
            padding: 20px;
            color: #666;
            font-size: 14px;
            background: #f8f9fa;
        }
        
        .progress-bar {
            display: flex;
            margin-bottom: 25px;
            position: relative;
        }
        
        .progress-step {
            flex: 1;
            text-align: center;
            padding: 10px;
            position: relative;
            font-weight: 600;
        }
        
        .progress-step:after {
            content: '';
            position: absolute;
            top: 40%;
            right: 0;
            width: 100%;
            height: 2px;
            background: #ddd;
            z-index: 1;
        }
        
        .progress-step:last-child:after {
            display: none;
        }
        
        .step-number {
            display: inline-block;
            width: 30px;
            height: 30px;
            background: #ddd;
            color: white;
            border-radius: 50%;
            line-height: 30px;
            margin-bottom: 5px;
            position: relative;
            z-index: 2;
            transition: all 0.3s;
        }
        
        .progress-step.active .step-number {
            background: #4a6fc7;
            transform: scale(1.1);
            box-shadow: 0 0 0 3px rgba(74, 111, 199, 0.2);
        }
        
        .progress-step.completed .step-number {
            background: #1cc88a;
        }
        
        .step-title {
            font-size: 12px;
            display: block;
        }
        
        .identifier-display {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            text-align: center;
            border: 1px dashed #4a6fc7;
            display: none;
            animation: fadeIn 0.5s ease;
        }
        
        .identifier-code {
            font-size: 20px;
            font-weight: bold;
            color: #4a6fc7;
            letter-spacing: 2px;
            padding: 8px;
            background: rgba(74, 111, 199, 0.1);
            border-radius: 4px;
            display: inline-block;
            margin: 10px 0;
        }
        
        .complete-verification-link {
            text-align: center;
            margin-top: 15px;
            color: #4a6fc7;
            cursor: pointer;
            text-decoration: underline;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .complete-verification-link:hover {
            color: #3b5aa6;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @media (max-width: 768px) {
            body {
                flex-direction: column;
            }
            
            .left-section {
                min-height: 40vh;
            }
            
            .left-content h1 {
                font-size: 2rem;
            }
            
            .right-section {
                min-height: 60vh;
            }
            
            .logo {
                width: 180px;
            }
        }
    </style>
</head>
<body>
    <img src="https://ik.imagekit.io/nuvq7aygp/BMB_Logo.jpg" alt="BMB Logo" class="logo">
    
    <div class="left-section">
        <div class="left-content">
            <h1>BMB View Portal</h1>
            <p>Access your personalized dashboard and manage your account with our secure authentication system.</p>
        </div>
    </div>
    
    <div class="right-section">
        <div class="container">
            <div class="header">
                <h1>Welcome to BMB</h1>
            </div>
            
            <div class="form-container">
                <!-- Progress Bar -->
                <div class="progress-bar">
                    <div class="progress-step <?php echo ($show_verification) ? 'completed' : 'active'; ?>" id="step1">
                        <span class="step-number">1</span>
                        <span class="step-title">Sign Up</span>
                    </div>
                    <div class="progress-step <?php echo ($show_verification) ? 'active' : ''; ?>" id="step2">
                        <span class="step-number">2</span>
                        <span class="step-title">Verify</span>
                    </div>
                </div>
                
                <!-- Signup Form -->
                <div class="form-section <?php echo (!$show_verification && !isset($_POST['login']) && !$show_complete_verification) ? '' : ''; ?>" id="signup-section">
                    <form id="signup-form" method="POST">
                        <div class="form-group">
                            <label for="signup-name">Full Name</label>
                            <input type="text" id="signup-name" name="name" required placeholder="Enter your full name" value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="signup-email">Email</label>
                            <input type="email" id="signup-email" name="email" required placeholder="Enter your email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="signup-password">Password</label>
                            <input type="password" id="signup-password" name="password" required placeholder="Create a password">
                        </div>
                        
                        <div class="form-group">
                            <label for="signup-department">Department</label>
                            <input type="text" id="signup-department" name="department" required placeholder="Enter your department" value="<?php echo isset($_POST['department']) ? htmlspecialchars($_POST['department']) : ''; ?>">
                        </div>
                        
                        <button type="submit" class="btn" name="signup">Create Account</button>
                    </form>
                    
                    <div class="login-link" style="text-align: center; margin-top: 20px;">
                        Already have an account? <a id="show-login" style="color: #4a6fc7; cursor: pointer;">Login</a>
                    </div>
                </div>
                
                <!-- Verification Form -->
                <div class="form-section <?php echo ($show_verification) ? 'active' : ''; ?>" id="verification-section">
                    <div class="identifier-display">
                        <p>Your unique identifier is:</p>
                        <div class="identifier-code"><?php echo $generated_identifier; ?></div>
                        <p>Please save this code and enter it below to verify your account.</p>
                    </div>
                    
                    <form id="verification-form" method="POST">
                        <input type="hidden" name="email" value="<?php echo $verification_email; ?>">
                        
                        <div class="form-group">
                            <label for="verification-code">Verification Code</label>
                            <input type="text" id="verification-code" name="verification_code" required placeholder="Enter your unique identifier">
                        </div>
                        
                        <button type="submit" class="btn" name="verify">Verify Account</button>
                    </form>
                    
                    <div style="text-align: center; margin-top: 20px;">
                        <a id="back-to-signup" style="color: #4a6fc7; cursor: pointer;">Back to Sign Up</a>
                    </div>
                </div>
                
                <!-- Login Form -->
                <div class="form-section <?php echo (isset($_POST['login']) || (!$show_verification && !$show_complete_verification)) ? 'active' : ''; ?>" id="login-section">
                    <form id="login-form" method="POST">
                        <div class="form-group">
                            <label for="login-email">Email</label>
                            <input type="email" id="login-email" name="email" required placeholder="Enter your email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="login-password">Password</label>
                            <input type="password" id="login-password" name="password" required placeholder="Enter your password">
                        </div>
                        
                        <div class="form-group">
                            <label for="login-identifier">Unique Identifier</label>
                            <input type="text" id="login-identifier" name="unique_identifier" required placeholder="Enter your unique identifier">
                            <small style="color: #666; font-size: 12px;">Check your records for the unique identifier provided after signup</small>
                        </div>
                        
                        <button type="submit" class="btn" name="login">Login</button>
                    </form>
                    
                    <div class="complete-verification-link" id="show-complete-verification">
                        Need to complete verification? Click here
                    </div>
                    
                    <div class="login-link" style="text-align: center; margin-top: 20px;">
                        Don't have an account? <a id="show-signup" style="color: #4a6fc7; cursor: pointer;">Sign Up</a>
                    </div>
                </div>
                
                <!-- Complete Verification Form -->
                <div class="form-section <?php echo ($show_complete_verification) ? 'active' : ''; ?>" id="complete-verification-section">
                    <h3 style="text-align: center; margin-bottom: 20px; color: #4a6fc7;">Complete Your Verification</h3>
                    
                    <form id="complete-verification-form" method="POST">
                        <div class="form-group">
                            <label for="complete-email">Email</label>
                            <input type="email" id="complete-email" name="email" required placeholder="Enter your email" value="<?php echo $verification_email; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="complete-password">Password</label>
                            <input type="password" id="complete-password" name="password" required placeholder="Enter your password">
                        </div>
                        
                        <div class="form-group">
                            <label for="complete-verification-code">Verification Code</label>
                            <input type="text" id="complete-verification-code" name="verification_code" required placeholder="Enter your unique identifier">
                        </div>
                        
                        <button type="submit" class="btn" name="complete_verification">Complete Verification</button>
                    </form>
                    
                    <div style="text-align: center; margin-top: 20px;">
                        <a id="back-to-login" style="color: #4a6fc7; cursor: pointer;">Back to Login</a>
                    </div>
                </div>
                
                <!-- Message Display -->
                <div id="message" class="message <?php 
                    if (!empty($message)) {
                        echo $message_type;
                    } else {
                        echo 'hidden';
                    }
                ?>"><?php 
                    if (!empty($message)) {
                        echo $message;
                    }
                ?></div>
            </div>
            
            <div class="footer">
                <p>&copy; 2025 Buymeabook. All rights reserved.</p>
            </div>
        </div>
    </div>
    
    <script>
        // DOM Elements
        const signupSection = document.getElementById('signup-section');
        const verificationSection = document.getElementById('verification-section');
        const loginSection = document.getElementById('login-section');
        const completeVerificationSection = document.getElementById('complete-verification-section');
        const step1 = document.getElementById('step1');
        const step2 = document.getElementById('step2');
        const messageDiv = document.getElementById('message');
        const showLoginBtn = document.getElementById('show-login');
        const showSignupBtn = document.getElementById('show-signup');
        const backToSignupBtn = document.getElementById('back-to-signup');
        const showCompleteVerificationBtn = document.getElementById('show-complete-verification');
        const backToLoginBtn = document.getElementById('back-to-login');
        
        // Show login section
        showLoginBtn.addEventListener('click', function() {
            signupSection.classList.remove('active');
            verificationSection.classList.remove('active');
            loginSection.classList.add('active');
            completeVerificationSection.classList.remove('active');
            messageDiv.classList.add('hidden');
            
            // Reset progress bar
            step1.classList.remove('completed');
            step2.classList.remove('active', 'completed');
            step1.classList.add('active');
        });
        
        // Show signup section
        showSignupBtn.addEventListener('click', function() {
            loginSection.classList.remove('active');
            verificationSection.classList.remove('active');
            completeVerificationSection.classList.remove('active');
            signupSection.classList.add('active');
            messageDiv.classList.add('hidden');
            
            // Reset progress bar
            step1.classList.remove('completed');
            step2.classList.remove('active', 'completed');
            step1.classList.add('active');
        });
        
        // Back to signup from verification
        backToSignupBtn.addEventListener('click', function() {
            verificationSection.classList.remove('active');
            completeVerificationSection.classList.remove('active');
            signupSection.classList.add('active');
            messageDiv.classList.add('hidden');
            
            // Reset progress bar
            step1.classList.remove('completed');
            step2.classList.remove('active', 'completed');
            step1.classList.add('active');
        });
        
        // Show complete verification section
        showCompleteVerificationBtn.addEventListener('click', function() {
            loginSection.classList.remove('active');
            completeVerificationSection.classList.add('active');
            messageDiv.classList.add('hidden');
        });
        
        // Back to login from complete verification
        backToLoginBtn.addEventListener('click', function() {
            completeVerificationSection.classList.remove('active');
            loginSection.classList.add('active');
            messageDiv.classList.add('hidden');
        });
        
        // Handle signup form submission
        document.getElementById('signup-form').addEventListener('submit', function(e) {
            // Validation is handled by PHP
        });
        
        // Handle verification form submission
        document.getElementById('verification-form').addEventListener('submit', function(e) {
            // Validation is handled by PHP
        });
        
        // Handle login form submission
        document.getElementById('login-form').addEventListener('submit', function(e) {
            // Validation is handled by PHP
        });
        
        // Handle complete verification form submission
        document.getElementById('complete-verification-form').addEventListener('submit', function(e) {
            // Validation is handled by PHP
        });
        
        // Function to show message
        function showMessage(message, type) {
            messageDiv.textContent = message;
            messageDiv.className = 'message ' + type;
            messageDiv.classList.remove('hidden');
        }
        
        // Auto-hide message after 5 seconds
        setTimeout(function() {
            if (!messageDiv.classList.contains('hidden')) {
                messageDiv.classList.add('hidden');
            }
        }, 5000);
    </script>
</body>
</html>