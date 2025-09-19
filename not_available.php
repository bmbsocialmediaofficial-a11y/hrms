<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Coming Soon - Admin System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #1a2a6c 0%, #2a5298 50%, #7e8ba3 100%);
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
        .coming-soon-container {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            text-align: center;
            max-width: 500px;
            width: 90%;
            margin-top: 60px;
        }
        .coming-soon-icon {
            font-size: 64px;
            color: #2a5298;
            margin-bottom: 20px;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        .coming-soon-title {
            color: #1a2a6c;
            font-size: 28px;
            margin-bottom: 15px;
            font-weight: bold;
        }
        .coming-soon-message {
            color: #333;
            font-size: 16px;
            margin-bottom: 25px;
            line-height: 1.5;
        }
        .feature-description {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 25px;
            border-left: 4px solid #2a5298;
        }
        .btn {
            background: #2a5298;
            color: white;
            padding: 12px 25px;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            display: inline-block;
            transition: background 0.3s;
        }
        .btn:hover {
            background: #1a2a6c;
        }
    </style>
</head>
<body>
    <div class="logo-container">
        <img src="https://ik.imagekit.io/nuvq7aygp/BMB_Logo.jpg" alt="BMB Logo" class="logo">
    </div>
    
    <div class="coming-soon-container">
        <div class="coming-soon-icon">
            <i class="fas fa-rocket"></i>
        </div>
        <h1 class="coming-soon-title">Coming Soon</h1>
        <p class="coming-soon-message">
            <?php 
            if (isset($_SESSION['feature_name'])) {
                echo "We're working hard to launch our new feature: <strong>" . htmlspecialchars($_SESSION['feature_name']) . "</strong>";
                unset($_SESSION['feature_name']);
            } else {
                echo "We're working hard to launch a new exciting feature!";
            }
            ?>
        </p>
        
        <div class="feature-description">
            <p>
                <?php 
                if (isset($_SESSION['feature_description'])) {
                    echo htmlspecialchars($_SESSION['feature_description']);
                    unset($_SESSION['feature_description']);
                } else {
                    echo "This feature will enhance your experience and provide more functionality to help you manage your Admin tasks efficiently.";
                }
                ?>
            </p>
        </div>
        
        <p class="coming-soon-message">
            We're putting the finishing touches and can't wait to share it with you. Stay tuned!
        </p>
        
        <a href="dashboard.php" class="btn" style="margin-top: 20px;">
            <i class="fas fa-arrow-left"></i> Return to Dashboard
        </a>
    </div>
</body>
</html>