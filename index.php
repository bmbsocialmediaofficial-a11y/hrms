<?php
// Turn off ALL errors to prevent crashes
error_reporting(0);
ini_set('display_errors', 0);

// Start session
session_start();

// Initialize variables
$message = "";
$message_type = "";
$conn = null;

// Try to include config SILENTLY
@include_once 'config.php';

// Only try database operations if connection exists
if ($conn && !$conn->connect_error) {
    // Handle form submissions ONLY if database is available
    if (isset($_POST['login'])) {
        // Login form submitted
        $email = $conn->real_escape_string($_POST['email']);
        $password = $_POST['password'];
        $identifier = $conn->real_escape_string($_POST['unique_identifier']);
        
        // Check credentials
        $sql = "SELECT * FROM employees WHERE email='$email'";
        $result = $conn->query($sql);
        
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            
            // Verify password
            if (password_verify($password, $row['password'])) {
                // Verify identifier and valid_user status
                if ($identifier === $row['unique_identifier']) {
                    if ($row['valid_user']) {
                        $message = "Login successful! Redirecting...";
                        $message_type = "success";
                        
                        // Regenerate session ID to prevent fixation attacks
                        session_regenerate_id(true);
                        
                        // Set session variables with additional security
                        $_SESSION['user_id'] = $row['id'];
                        $_SESSION['user_email'] = $row['email'];
                        $_SESSION['user_name'] = $row['name'];
                        $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
                        $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'];
                        $_SESSION['last_activity'] = time();
                        
                        // Write session data immediately before redirect
                        session_write_close();
                        
                        // Redirect to start page
                        header("Location: start.php");
                        exit();
                    } else {
                        $message = "Account not verified. Please contact administrator.";
                        $message_type = "error";
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
} else {
    // Database is not available - show maintenance message
    if (isset($_POST['login'])) {
        $message = "System is currently undergoing maintenance. Please try again later.";
        $message_type = "info";
    }
}
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
        
        .system-status {
            position: absolute;
            top: 20px;
            right: 20px;
            background: #f59e0b;
            color: white;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            z-index: 1000;
        }
        
        .left-section {
            flex: 1;
            background-image: url('https://images.pexels.com/photos/256453/pexels-photo-256453.jpeg');
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
            background: #fff8f3;
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
            background: linear-gradient(135deg, #ff8a00 0%, #ff5722 100%);
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
        
        .form-section {
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
            border-color: #ff8a00;
            outline: none;
            box-shadow: 0 0 0 3px rgba(255, 138, 0, 0.2);
        }
        
        .btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #ff8a00 0%, #ff5722 100%);
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
            background: linear-gradient(135deg, #e67e00 0%, #e64a19 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(255, 138, 0, 0.3);
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
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        .hidden {
            display: none;
        }
        
        .footer {
            text-align: center;
            padding: 20px;
            color: #666;
            font-size: 14px;
            background: #fff8f3;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @media (max-width: 768px) {
            body {
                flex-direction: column;
                overflow-y: auto;
            }
            
            .left-section {
                min-height: 40vh;
            }
            
            .left-content h1 {
                font-size: 2rem;
            }
            
            .right-section {
                min-height: 60vh;
                overflow-y: auto;
            }
            
            .logo {
                width: 180px;
            }
        }
        
        @media (max-width: 480px) {
            body {
                flex-direction: column;
                overflow-y: auto;
            }
            
            .logo {
                width: 115px;
                top: 15px;
                left: 15px;
                border: 2px solid white;
            }
            
            .left-section {
                min-height: 30vh;
            }
            
            .left-content {
                padding: 15px;
            }
            
            .left-content h1 {
                font-size: 1.8rem;
                margin-top: 20px;
                margin-bottom: 0px;
            }
            
            .left-content p {
                font-size: 1rem;
            }
            
            .right-section {
                min-height: 70vh;
                padding: 15px;
                overflow-y: auto;
            }
            
            .container {
                max-width: 100%;
            }
            
            .header {
                padding: 20px 15px;
            }
            
            .header h1 {
                font-size: 20px;
            }
            
            .form-container {
                padding: 20px;
            }
            
            .form-group {
                margin-bottom: 15px;
            }
            
            .form-group input {
                padding: 12px;
                font-size: 14px;
            }
            
            .btn {
                padding: 12px;
                font-size: 14px;
            }
            
            .footer {
                padding: 15px;
                font-size: 12px;
            }
        }
    </style>
</head>
<body>
    <img src="https://ik.imagekit.io/nuvq7aygp/BMB_Logo.jpg" alt="BMB Logo" class="logo">
    
    <!-- System Status Indicator -->
    <div class="system-status">
        <?php echo ($conn && !$conn->connect_error) ? '✅ System Online' : '🔄 Maintenance Mode'; ?>
    </div>
    
    <div class="left-section">
        <div class="left-content">
            <h1>BMB View Portal</h1>
            <p>Access your personalized dashboard with our secure authentication system.</p>
            <?php if (!($conn && !$conn->connect_error)): ?>
                <p style="margin-top: 20px; background: rgba(255, 152, 0, 0.2); padding: 10px; border-radius: 5px;">
                    🔄 System maintenance in progress. Login available shortly.
                </p>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="right-section">
        <div class="container">
            <div class="header">
                <h1>Welcome to BMB</h1>
            </div>
            
            <div class="form-container">
                <!-- Login Form -->
                <div class="form-section active" id="login-section">
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
                            <small style="color: #666; font-size: 12px;">Enter the unique identifier provided by your administrator</small>
                        </div>
                        
                        <button type="submit" class="btn" name="login">Login</button>
                    </form>
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
                <?php if (!($conn && !$conn->connect_error)): ?>
                    <p style="font-size: 12px; color: #f59e0b; margin-top: 5px;">
                        System maintenance mode active
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        // If database is not available, show info message on form submit
        document.getElementById('login-form').addEventListener('submit', function(e) {
            <?php if (!($conn && !$conn->connect_error)): ?>
                e.preventDefault();
                showMessage('System is currently undergoing maintenance. Please try again later.', 'info');
            <?php endif; ?>
        });
        
        // Function to show message
        function showMessage(message, type) {
            const messageDiv = document.getElementById('message');
            messageDiv.textContent = message;
            messageDiv.className = 'message ' + type;
            messageDiv.classList.remove('hidden');
        }
        
        // Auto-hide message after 5 seconds
        setTimeout(function() {
            const messageDiv = document.getElementById('message');
            if (!messageDiv.classList.contains('hidden')) {
                messageDiv.classList.add('hidden');
            }
        }, 5000);
    </script>
</body>
</html>
