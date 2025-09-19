<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Files - BuyMeABook View Portal</title>
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
            align-items: center;
            min-height: 100vh;
            padding: 40px 20px;
        }
        
        .files-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(12px);
            border-radius: 20px;
            padding: 40px;
            text-align: center;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
            max-width: 1000px;
            width: 90%;
            border: 1px solid rgba(255, 255, 255, 0.2);
            position: relative;
            overflow: hidden;
        }
        
        .files-card::before {
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
            margin-bottom: 30px;
            text-shadow: 2px 2px 8px rgba(0, 0, 0, 0.3);
            font-weight: 700;
            background: linear-gradient(to right, #fff, #e0e0ff);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            letter-spacing: 1px;
        }
        
        .subtitle {
            font-size: 1.2rem;
            margin-bottom: 40px;
            opacity: 0.9;
            font-weight: 300;
            letter-spacing: 0.5px;
        }
        
        .tiles-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }
        
        .tile {
            background: rgba(255, 255, 255, 0.15);
            border-radius: 15px;
            padding: 25px 20px;
            text-decoration: none;
            color: white;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            border: 1px solid rgba(255, 255, 255, 0.1);
            min-height: 150px;
        }
        
        .tile:hover {
            transform: translateY(-8px);
            background: rgba(255, 255, 255, 0.25);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
        }
        
        .tile i {
            font-size: 2.5rem;
            margin-bottom: 15px;
        }
        
        .tile h3 {
            font-size: 1.3rem;
            font-weight: 600;
            text-align: center;
        }
        
        .btn-container {
            display: flex;
            justify-content: center;
            gap: 15px;
            flex-wrap: wrap;
            margin-top: 20px;
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
            flex: 1;
            max-width: 200px;
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
        
        .btn-home {
            background: rgba(106, 17, 203, 0.9);
            color: white;
        }
        
        .btn-home:hover {
            background: rgba(90, 15, 184, 0.95);
        }
        
        .btn-logout {
            background: rgba(255, 255, 255, 0.15);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        
        .btn-logout:hover {
            background: rgba(255, 100, 100, 0.2);
            color: #ffcccc;
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
            
            .files-card {
                padding: 30px 25px;
            }
            
            .tiles-container {
                grid-template-columns: 1fr;
            }
            
            .btn-container {
                flex-direction: column;
                align-items: center;
            }
            
            .btn {
                width: 100%;
                max-width: 250px;
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
                padding-top: 20px;
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
            
            .tile {
                padding: 20px 15px;
                min-height: 120px;
            }
            
            .tile i {
                font-size: 2rem;
            }
            
            .tile h3 {
                font-size: 1.1rem;
            }
            
            .btn {
                padding: 12px 15px;
                min-width: 140px;
            }
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
                <a href="manage_files.php" class="nav-link active">
                    <i class="fas fa-folder"></i>
                    <span>Manage Files</span>
                </a>
            </li>
			<li class="nav-item">
                <a href="employee_leave_request.php" class="nav-link">
                    <i class="fas fa-stream"></i>
                    <span>Leave Requests</span>
                </a>
            </li>
			<li class="nav-item">
                <a href="ask_hr_employee.php" class="nav-link">
                    <i class="fas fa-stream"></i>
                    <span>Ask HR</span>
                </a>
            </li>
			            <li class="nav-item">
                <a href="technical_requests_admin.php" class="nav-link">
                    <i class="fas fa-tools"></i>
                    <span>Tech Support</span>
                </a>
            </li>
			
						            <li class="nav-item">
                <a href="employee_details.php" class="nav-link">
                    <i class="fas fa-user-edit"></i>
                    <span>Employee Details</span>
                </a>
            </li>
			<li class="nav-item">
                <a href="employee_exit_request.php" class="nav-link">
                    <i class="fas fa-stream"></i>
                    <span>Exit Requests</span>
                </a>
            </li>
			
        </ul>
    </div>
    
    <div class="logo-container">
        <img src="https://ik.imagekit.io/nuvq7aygp/BMB_Logo.jpg" alt="BMB Logo" class="logo">
    </div>
    
    <div class="container">
        <div class="files-card">
            <div class="decoration decoration-1"></div>
            <div class="decoration decoration-2"></div>
            
            <h1>Manage Files</h1>
            <p class="subtitle">Select a category to manage files</p>
            
            <div class="tiles-container">
                <a href="ftp_manager_marketing_main.php" class="tile">
                    <i class="fas fa-chart-line"></i>
                    <h3>Marketing</h3>
                </a>
                
                <a href="ftp_manager_operation_main.php" class="tile">
                    <i class="fas fa-cogs"></i>
                    <h3>Operations</h3>
                </a>
                
                <a href="ftp_manager_development_main.php" class="tile">
                    <i class="fas fa-code"></i>
                    <h3>Development</h3>
                </a>
                
                <a href="ftp_manager_testing_main.php" class="tile">
                    <i class="fas fa-vial"></i>
                    <h3>Testing</h3>
                </a>
                
                <a href="ftp_manager_hr_main.php" class="tile">
                    <i class="fas fa-building"></i>
                    <h3>Company</h3>
                </a>
                
                <a href="ftp_manager_miscellaneous_main.php" class="tile">
                    <i class="fas fa-folder-open"></i>
                    <h3>Miscellaneous</h3>
                </a>
            </div>
            
            <div class="btn-container">
                <a href="start.php" class="btn btn-home"><i class="fas fa-home"></i> Home</a>
                <a href="logout.php" class="btn btn-logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
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
        });
    </script>
</body>
</html>