<?php
echo "<!DOCTYPE html>
<html>
<head>
    <title>HRMS Application</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
        .status { padding: 15px; border-radius: 5px; margin: 20px 0; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .warning { background: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }
        .content { background: #f8f9fa; padding: 20px; border-radius: 5px; }
    </style>
</head>
<body>
    <h1>🏢 HRMS Application</h1>
    <div class='content'>
        <h2>Human Resource Management System</h2>";

// Try database connection quietly
@include_once 'config.php';
$db_connected = false;

if ($conn && !$conn->connect_error) {
    echo "<div class='status success'>✅ Database Connected - Full functionality available</div>";
    $db_connected = true;
} else {
    echo "<div class='status warning'>⚠️ Database Setup in Progress - Limited functionality</div>";
}

echo "
        <h3>Available Features:</h3>
        <ul>
            <li>📋 Company Information</li>
            <li>👥 Employee Directory</li>
            <li>📅 Leave Management System</li>";

if ($db_connected) {
    echo "            <li>💾 Full Database Operations</li>";
} else {
    echo "            <li>🔜 Database features coming soon</li>";
}

echo "
        </ul>
        
        <h3>Contact Information:</h3>
        <p>Email: hr@company.com<br>
           Phone: (555) 123-4567</p>
    </div>
    
    <footer style='margin-top: 30px; text-align: center; color: #666;'>
        HRMS System © 2024 - All rights reserved
    </footer>
</body>
</html>";
?>
