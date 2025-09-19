<?php
session_start();

// Include the configuration file
require_once 'config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Access Denied - Core System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #0a1a3a 0%, #1a2a6c 50%, #2a3a9c 100%);
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 0;
            flex-direction: column;
        }
        .logo-container {
            position: absolute;
            top: 20px;
            left: 20px;
        }
        .logo {
            width: 230px;
            height: auto;
            
        }
        .error-container {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            text-align: center;
            max-width: 500px;
            width: 90%;
            margin-top: 60px;
        }
        .error-icon {
            font-size: 64px;
            color: #1a2a6c;
            margin-bottom: 20px;
        }
        .error-title {
            color: #0a1a3a;
            font-size: 24px;
            margin-bottom: 15px;
            font-weight: bold;
        }
        .error-message {
            color: #333;
            font-size: 16px;
            margin-bottom: 25px;
            line-height: 1.5;
        }
        .btn {
            background: #0a1a3a;
            color: white;
            padding: 12px 25px;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            display: inline-block;
            transition: background 0.3s;
        }
        .btn:hover {
            background: #07142d;
        }
    </style>
</head>
<body>
    <div class="logo-container">
        <img src="https://ik.imagekit.io/nuvq7aygp/BMB_Logo.jpg" alt="BMB Logo" class="logo">
    </div>
    
    <div class="error-container">
        <div class="error-icon">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <h1 class="error-title">Access Denied</h1>
        <p class="error-message">
            <?php 
            if (isset($_SESSION['access_error'])) {
                echo htmlspecialchars($_SESSION['access_error']);
                unset($_SESSION['access_error']);
            } else {
                echo "You do not have sufficient privileges to access the Core System.";
            }
            ?>
        </p>
        <a href="start.php" class="btn">
            <i class="fas fa-arrow-left"></i> Return to Portal
        </a>
    </div>
</body>
</html>