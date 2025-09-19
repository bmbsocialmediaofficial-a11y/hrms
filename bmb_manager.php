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
// Verify if the user has manager privileges
$user_id = $_SESSION['user_id'];
$sql = "SELECT is_manager, department FROM employees WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 1) {
    $employee = $result->fetch_assoc();
    
    if ($employee['is_manager'] != 1) {
        // User doesn't have manager privileges
        $_SESSION['access_error'] = "You need manager privileges to access this system";
        header("Location: illegal_access_manager.php"); // Redirect to access denied page
        exit();
    } else {
        // Set manager session flags if not already set
        $_SESSION['is_manager'] = true;
        // Store manager's department
        $manager_department = $employee['department'];
    }
} else {
    // User not found in database
    $_SESSION['access_error'] = "User not found in system";
    header("Location: illegal_access_manager.php");
    exit();
}
$stmt->close();
// Handle form submissions
$message = "";
$message_type = "";
// Handle update employee
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_employee'])) {
    $employee_id = $_POST['employee_id'];
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $department = trim($_POST['department']);
    $position = trim($_POST['position']);
    $is_manager = isset($_POST['is_manager']) ? 1 : 0;
    
    // Validate input
    if (empty($name) || empty($email) || empty($department) || empty($position)) {
        $message = "All fields except phone are required!";
        $message_type = "error";
    } else {
        // Check if email exists for another employee
        $check_email = $conn->prepare("SELECT id FROM employees WHERE email = ? AND id != ?");
        $check_email->bind_param("si", $email, $employee_id);
        $check_email->execute();
        $result = $check_email->get_result();
        
        if ($result->num_rows > 0) {
            $message = "Email already exists for another employee!";
            $message_type = "error";
        } else {
            // Update employee
$update = $conn->prepare("UPDATE employees SET name = ?, email = ?, phone_primary = ?, department = ?, job_title = ?, is_manager = ? WHERE id = ?");
$update->bind_param("ssssiii", $name, $email, $phone, $department, $position, $is_manager, $employee_id);

            
            if ($update->execute()) {
                $message = "Employee updated successfully!";
                $message_type = "success";
            } else {
                $message = "Error updating employee: " . $conn->error;
                $message_type = "error";
            }
        }
    }
}
// Handle delete employee
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_employee'])) {
    $employee_id = $_POST['employee_id'];
    
    // Prevent deleting own account
    if ($employee_id == $_SESSION['user_id']) {
        $message = "You cannot delete your own account!";
        $message_type = "error";
    } else {
        $delete = $conn->prepare("DELETE FROM employees WHERE id = ?");
        $delete->bind_param("i", $employee_id);
        
        if ($delete->execute()) {
            $message = "Employee deleted successfully!";
            $message_type = "success";
        } else {
            $message = "Error deleting employee: " . $conn->error;
            $message_type = "error";
        }
    }
}
// Handle add employee
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_employee'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $department = trim($_POST['department']);
    $position = trim($_POST['position']);
    $onboarding_notes = trim($_POST['onboarding_notes']);
    
    // Validate input
    if (empty($name) || empty($email) || empty($department) || empty($position)) {
        $message = "All fields except phone and onboarding notes are required!";
        $message_type = "error";
    } else {
        // Check if email already exists
        $check_email = $conn->prepare("SELECT id FROM employees WHERE email = ?");
        $check_email->bind_param("s", $email);
        $check_email->execute();
        $result = $check_email->get_result();
        
        if ($result->num_rows > 0) {
            $message = "Email already exists!";
            $message_type = "error";
        } else {
            // Add new employee - FIXED: Removed phone field and adjusted parameter count
            $insert = $conn->prepare("INSERT INTO employees (name, email, department, job_title, is_manager, onboarding_notes) 
                                     VALUES (?, ?, ?, ?, 0, ?)");
            $insert->bind_param("sssss", $name, $email, $department, $position, $onboarding_notes);
            
            if ($insert->execute()) {
                $message = "Employee added successfully!";
                $message_type = "success";
            } else {
                $message = "Error adding employee: " . $conn->error;
                $message_type = "error";
            }
        }
    }
}
// Fetch all employees from the manager's department
$employees = array();
$emp_sql = "SELECT * FROM employees WHERE department = ? ORDER BY name ASC";
$emp_stmt = $conn->prepare($emp_sql);
$emp_stmt->bind_param("s", $manager_department);
$emp_stmt->execute();
$emp_result = $emp_stmt->get_result();
if ($emp_result->num_rows > 0) {
    while ($row = $emp_result->fetch_assoc()) {
        $employees[] = $row;
    }
}
$emp_stmt->close();
// Get departments for dropdown (only the manager's department)
$departments = array($manager_department);
// Get manager info
$manager_id = $_SESSION['user_id'];
$manager_query = "SELECT * FROM employees WHERE id = ?";
$manager_stmt = $conn->prepare($manager_query);
$manager_stmt->bind_param("i", $manager_id);
$manager_stmt->execute();
$manager_result = $manager_stmt->get_result();
$manager = $manager_result->fetch_assoc();
$manager_stmt->close();
// ANALYTICS CALCULATIONS FOR DASHBOARD
// Get employee IDs for analytics queries
$employee_ids = array();
foreach ($employees as $emp) {
    $employee_ids[] = $emp['id'];
}
$employee_ids_str = implode(',', $employee_ids);
// Initialize analytics variables
$weekly_attendance_avg = 0;
$monthly_attendance_avg = 0;
$monthly_leaves_avg = 0;
$monthly_performance_avg = 0;
$monthly_training_avg = 0;
// Calculate average attendance per week
try {
    $att_sql = "SELECT AVG(attendance_count) as avg_weekly_attendance 
                FROM (
                    SELECT COUNT(*) as attendance_count, WEEK(date) as week_num
                    FROM employee_attendance 
                    WHERE employee_id IN ($employee_ids_str) AND status = 'Present'
                    AND date >= DATE_SUB(CURRENT_DATE, INTERVAL 4 WEEK)
                    GROUP BY employee_id, WEEK(date)
                ) as weekly_attendance";
    $att_result = $conn->query($att_sql);
    if ($att_result && $att_result->num_rows > 0) {
        $row = $att_result->fetch_assoc();
        $weekly_attendance_avg = round($row['avg_weekly_attendance'], 1);
    }
} catch (Exception $e) {
    // Table or column might not exist, handle gracefully
    $weekly_attendance_avg = 0;
}
// Calculate average attendance per month
try {
    $att_sql = "SELECT AVG(attendance_count) as avg_monthly_attendance 
                FROM (
                    SELECT COUNT(*) as attendance_count, MONTH(date) as month_num
                    FROM employee_attendance 
                    WHERE employee_id IN ($employee_ids_str) AND status = 'Present'
                    AND date >= DATE_SUB(CURRENT_DATE, INTERVAL 3 MONTH)
                    GROUP BY employee_id, MONTH(date)
                ) as monthly_attendance";
    $att_result = $conn->query($att_sql);
    if ($att_result && $att_result->num_rows > 0) {
        $row = $att_result->fetch_assoc();
        $monthly_attendance_avg = round($row['avg_monthly_attendance'], 1);
    }
} catch (Exception $e) {
    // Table or column might not exist, handle gracefully
    $monthly_attendance_avg = 0;
}
// Calculate average leaves per month
try {
    $leave_sql = "SELECT AVG(leave_count) as avg_monthly_leaves 
                 FROM (
                     SELECT COUNT(*) as leave_count, MONTH(date) as month_num
                     FROM employee_attendance 
                     WHERE employee_id IN ($employee_ids_str) AND status = 'Absent'
                     AND date >= DATE_SUB(CURRENT_DATE, INTERVAL 3 MONTH)
                     GROUP BY employee_id, MONTH(date)
                 ) as monthly_leaves";
    $leave_result = $conn->query($leave_sql);
    if ($leave_result && $leave_result->num_rows > 0) {
        $row = $leave_result->fetch_assoc();
        $monthly_leaves_avg = round($row['avg_monthly_leaves'], 1);
    }
} catch (Exception $e) {
    // Table or column might not exist, handle gracefully
    $monthly_leaves_avg = 0;
}
// Calculate average performance per month
try {
    $perf_sql = "SELECT AVG(performance_rating) as avg_monthly_performance 
                 FROM (
                     SELECT performance_rating, MONTH(review_date) as month_num
                     FROM performance_reviews 
                     WHERE employee_id IN ($employee_ids_str)
                     AND review_date >= DATE_SUB(CURRENT_DATE, INTERVAL 3 MONTH)
                 ) as monthly_performance";
    $perf_result = $conn->query($perf_sql);
    if ($perf_result && $perf_result->num_rows > 0) {
        $row = $perf_result->fetch_assoc();
        $monthly_performance_avg = round($row['avg_monthly_performance'], 1);
    }
} catch (Exception $e) {
    // Table or column might not exist, handle gracefully
    $monthly_performance_avg = 0;
}
// Calculate average training per month
try {
    $train_sql = "SELECT AVG(training_count) as avg_monthly_training 
                  FROM (
                      SELECT COUNT(*) as training_count, MONTH(start_date) as month_num
                      FROM training_records 
                      WHERE employee_id IN ($employee_ids_str)
                      AND start_date >= DATE_SUB(CURRENT_DATE, INTERVAL 3 MONTH)
                      GROUP BY employee_id, MONTH(start_date)
                  ) as monthly_training";
    $train_result = $conn->query($train_sql);
    if ($train_result && $train_result->num_rows > 0) {
        $row = $train_result->fetch_assoc();
        $monthly_training_avg = round($row['avg_monthly_training'], 1);
    }
} catch (Exception $e) {
    // Table or column might not exist, handle gracefully
    $monthly_training_avg = 0;
}
// Get selected employee ID from URL parameter
$selected_employee_id = isset($_GET['employee_id']) ? $_GET['employee_id'] : null;
// Get active section from URL parameter
$active_section = isset($_GET['section']) ? $_GET['section'] : 'dashboard';
// Fetch data for selected employee
$selected_employee = null;
$attendance_records = array();
$tasks = array();
$performance_reviews = array();
$technical_requests = array();
$training_records = array();
// Initialize employee summary analytics
$employee_summary = array(
    'total_hours_worked' => 0,
    'daily_avg_hours' => 0,
    'weekly_avg_hours' => 0,
    'monthly_avg_hours' => 0,
    'total_days_worked' => 0,
    'total_tasks_assigned' => 0,
    'total_tasks_completed' => 0,
    'total_leave_requests' => 0,
    'total_trainings_completed' => 0,
    'avg_performance_rating' => 0,
    'total_technical_requests' => 0
);
// Initialize comparison data
$comparison_data = array();
$employee1_id = isset($_GET['employee1']) ? $_GET['employee1'] : null;
$employee2_id = isset($_GET['employee2']) ? $_GET['employee2'] : null;
if ($selected_employee_id) {
    // Get selected employee details
    foreach ($employees as $emp) {
        if ($emp['id'] == $selected_employee_id) {
            $selected_employee = $emp;
            break;
        }
    }
    
    if ($selected_employee) {
        // Fetch attendance records
        try {
            $att_sql = "SELECT * FROM employee_attendance WHERE employee_id = ? ORDER BY date DESC LIMIT 10";
            $att_stmt = $conn->prepare($att_sql);
            $att_stmt->bind_param("i", $selected_employee_id);
            $att_stmt->execute();
            $att_result = $att_stmt->get_result();
            if ($att_result->num_rows > 0) {
                while ($row = $att_result->fetch_assoc()) {
                    $attendance_records[] = $row;
                }
            }
            $att_stmt->close();
        } catch (Exception $e) {
            // Table or column might not exist, handle gracefully
            $attendance_records = array();
        }
        
        // Fetch tasks - Using assignee_id as per the tasks table structure
        try {
            $task_sql = "SELECT * FROM tasks WHERE assignee_id = ? ORDER BY due_date DESC LIMIT 10";
            $task_stmt = $conn->prepare($task_sql);
            $task_stmt->bind_param("i", $selected_employee_id);
            $task_stmt->execute();
            $task_result = $task_stmt->get_result();
            if ($task_result->num_rows > 0) {
                while ($row = $task_result->fetch_assoc()) {
                    $tasks[] = $row;
                }
            }
            $task_stmt->close();
        } catch (Exception $e) {
            // Table or column might not exist, handle gracefully
            $tasks = array();
        }
        
        // Fetch performance reviews
        try {
            $perf_sql = "SELECT * FROM performance_reviews WHERE employee_id = ? ORDER BY review_date DESC LIMIT 5";
            $perf_stmt = $conn->prepare($perf_sql);
            $perf_stmt->bind_param("i", $selected_employee_id);
            $perf_stmt->execute();
            $perf_result = $perf_stmt->get_result();
            if ($perf_result->num_rows > 0) {
                while ($row = $perf_result->fetch_assoc()) {
                    $performance_reviews[] = $row;
                }
            }
            $perf_stmt->close();
        } catch (Exception $e) {
            // Table or column might not exist, handle gracefully
            $performance_reviews = array();
        }
        
        // Fetch technical requests - FIXED TO USE CORRECT FIELD NAMES
        try {
            $tech_sql = "SELECT id, employee_id, issue_type, sub_issue_type, subject, description, status, 
                        priority, created_at, resolved_at FROM technical_requests WHERE employee_id = ? 
                        ORDER BY created_at DESC LIMIT 10";
            $tech_stmt = $conn->prepare($tech_sql);
            $tech_stmt->bind_param("i", $selected_employee_id);
            $tech_stmt->execute();
            $tech_result = $tech_stmt->get_result();
            if ($tech_result->num_rows > 0) {
                while ($row = $tech_result->fetch_assoc()) {
                    $technical_requests[] = $row;
                }
            }
            $tech_stmt->close();
        } catch (Exception $e) {
            // Table or column might not exist, handle gracefully
            $technical_requests = array();
        }
        
        // Fetch training records - FIXED TO USE CORRECT FIELD NAMES
        try {
            $train_sql = "SELECT id, employee_id, training_name, training_type, start_date, end_date, 
                         provider, cost, status, result FROM training_records WHERE employee_id = ? 
                         ORDER BY start_date DESC LIMIT 10";
            $train_stmt = $conn->prepare($train_sql);
            $train_stmt->bind_param("i", $selected_employee_id);
            $train_stmt->execute();
            $train_result = $train_stmt->get_result();
            if ($train_result->num_rows > 0) {
                while ($row = $train_result->fetch_assoc()) {
                    $training_records[] = $row;
                }
            }
            $train_stmt->close();
        } catch (Exception $e) {
            // Table or column might not exist, handle gracefully
            $training_records = array();
        }
        
        // Calculate employee summary analytics
        try {
            // Calculate total hours worked based on check_in and check_out times
            $hours_sql = "SELECT 
                            SUM(
                                CASE 
                                    WHEN check_in IS NOT NULL AND check_out IS NOT NULL THEN
                                        TIMESTAMPDIFF(HOUR, check_in, check_out) + 
                                        (TIMESTAMPDIFF(MINUTE, check_in, check_out) % 60) / 60.0
                                    ELSE 0
                                END
                            ) as total_hours,
                            COUNT(*) as total_days
                          FROM employee_attendance 
                          WHERE employee_id = ? AND status = 'Present'";
            $hours_stmt = $conn->prepare($hours_sql);
            $hours_stmt->bind_param("i", $selected_employee_id);
            $hours_stmt->execute();
            $hours_result = $hours_stmt->get_result();
            if ($hours_result->num_rows > 0) {
                $row = $hours_result->fetch_assoc();
                $employee_summary['total_hours_worked'] = round($row['total_hours'], 1);
                $employee_summary['total_days_worked'] = $row['total_days'];
                
                // Calculate daily average hours
                if ($row['total_days'] > 0) {
                    $employee_summary['daily_avg_hours'] = round($row['total_hours'] / $row['total_days'], 1);
                }
            }
            $hours_stmt->close();
        } catch (Exception $e) {
            // Table or column might not exist, handle gracefully
            $employee_summary['total_hours_worked'] = 0;
            $employee_summary['daily_avg_hours'] = 0;
            $employee_summary['total_days_worked'] = 0;
        }
        
        try {
            // Calculate weekly average hours
            $weekly_hours_sql = "SELECT 
                                    AVG(
                                        CASE 
                                            WHEN check_in IS NOT NULL AND check_out IS NOT NULL THEN
                                                TIMESTAMPDIFF(HOUR, check_in, check_out) + 
                                                (TIMESTAMPDIFF(MINUTE, check_in, check_out) % 60) / 60.0
                                            ELSE 0
                                        END
                                    ) as avg_weekly_hours
                                  FROM employee_attendance 
                                  WHERE employee_id = ? AND status = 'Present'
                                  AND date >= DATE_SUB(CURRENT_DATE, INTERVAL 4 WEEK)";
            $weekly_hours_stmt = $conn->prepare($weekly_hours_sql);
            $weekly_hours_stmt->bind_param("i", $selected_employee_id);
            $weekly_hours_stmt->execute();
            $weekly_hours_result = $weekly_hours_stmt->get_result();
            if ($weekly_hours_result->num_rows > 0) {
                $row = $weekly_hours_result->fetch_assoc();
                $employee_summary['weekly_avg_hours'] = round($row['avg_weekly_hours'], 1);
            }
            $weekly_hours_stmt->close();
        } catch (Exception $e) {
            // Table or column might not exist, handle gracefully
            $employee_summary['weekly_avg_hours'] = 0;
        }
        
        try {
            // Calculate monthly average hours
            $monthly_hours_sql = "SELECT 
                                     AVG(
                                         CASE 
                                             WHEN check_in IS NOT NULL AND check_out IS NOT NULL THEN
                                                 TIMESTAMPDIFF(HOUR, check_in, check_out) + 
                                                 (TIMESTAMPDIFF(MINUTE, check_in, check_out) % 60) / 60.0
                                             ELSE 0
                                         END
                                     ) as avg_monthly_hours
                                   FROM employee_attendance 
                                   WHERE employee_id = ? AND status = 'Present'
                                   AND date >= DATE_SUB(CURRENT_DATE, INTERVAL 3 MONTH)";
            $monthly_hours_stmt = $conn->prepare($monthly_hours_sql);
            $monthly_hours_stmt->bind_param("i", $selected_employee_id);
            $monthly_hours_stmt->execute();
            $monthly_hours_result = $monthly_hours_stmt->get_result();
            if ($monthly_hours_result->num_rows > 0) {
                $row = $monthly_hours_result->fetch_assoc();
                $employee_summary['monthly_avg_hours'] = round($row['avg_monthly_hours'], 1);
            }
            $monthly_hours_stmt->close();
        } catch (Exception $e) {
            // Table or column might not exist, handle gracefully
            $employee_summary['monthly_avg_hours'] = 0;
        }
        
        try {
            // Calculate total tasks assigned and completed
            $tasks_sql = "SELECT 
                            COUNT(*) as total_tasks,
                            SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed_tasks
                          FROM tasks 
                          WHERE assignee_id = ?";
            $tasks_stmt = $conn->prepare($tasks_sql);
            $tasks_stmt->bind_param("i", $selected_employee_id);
            $tasks_stmt->execute();
            $tasks_result = $tasks_stmt->get_result();
            if ($tasks_result->num_rows > 0) {
                $row = $tasks_result->fetch_assoc();
                $employee_summary['total_tasks_assigned'] = $row['total_tasks'];
                $employee_summary['total_tasks_completed'] = $row['completed_tasks'];
            }
            $tasks_stmt->close();
        } catch (Exception $e) {
            // Table or column might not exist, handle gracefully
            $employee_summary['total_tasks_assigned'] = 0;
            $employee_summary['total_tasks_completed'] = 0;
        }
        
        try {
            // Calculate total leave requests
            $leaves_sql = "SELECT COUNT(*) as total_leaves FROM employee_attendance 
                           WHERE employee_id = ? AND status = 'Absent'";
            $leaves_stmt = $conn->prepare($leaves_sql);
            $leaves_stmt->bind_param("i", $selected_employee_id);
            $leaves_stmt->execute();
            $leaves_result = $leaves_stmt->get_result();
            if ($leaves_result->num_rows > 0) {
                $row = $leaves_result->fetch_assoc();
                $employee_summary['total_leave_requests'] = $row['total_leaves'];
            }
            $leaves_stmt->close();
        } catch (Exception $e) {
            // Table or column might not exist, handle gracefully
            $employee_summary['total_leave_requests'] = 0;
        }
        
        try {
            // Calculate total trainings completed
            $train_sql = "SELECT COUNT(*) as total_trainings FROM training_records 
                          WHERE employee_id = ? AND status = 'Completed'";
            $train_stmt = $conn->prepare($train_sql);
            $train_stmt->bind_param("i", $selected_employee_id);
            $train_stmt->execute();
            $train_result = $train_stmt->get_result();
            if ($train_result->num_rows > 0) {
                $row = $train_result->fetch_assoc();
                $employee_summary['total_trainings_completed'] = $row['total_trainings'];
            }
            $train_stmt->close();
        } catch (Exception $e) {
            // Table or column might not exist, handle gracefully
            $employee_summary['total_trainings_completed'] = 0;
        }
        
        try {
            // Calculate average performance rating
            $perf_sql = "SELECT AVG(performance_rating) as avg_rating FROM performance_reviews 
                          WHERE employee_id = ?";
            $perf_stmt = $conn->prepare($perf_sql);
            $perf_stmt->bind_param("i", $selected_employee_id);
            $perf_stmt->execute();
            $perf_result = $perf_stmt->get_result();
            if ($perf_result->num_rows > 0) {
                $row = $perf_result->fetch_assoc();
                $employee_summary['avg_performance_rating'] = round($row['avg_rating'], 1);
            }
            $perf_stmt->close();
        } catch (Exception $e) {
            // Table or column might not exist, handle gracefully
            $employee_summary['avg_performance_rating'] = 0;
        }
        
        try {
            // Calculate total technical requests
            $tech_sql = "SELECT COUNT(*) as total_requests FROM technical_requests 
                          WHERE employee_id = ?";
            $tech_stmt = $conn->prepare($tech_sql);
            $tech_stmt->bind_param("i", $selected_employee_id);
            $tech_stmt->execute();
            $tech_result = $tech_stmt->get_result();
            if ($tech_result->num_rows > 0) {
                $row = $tech_result->fetch_assoc();
                $employee_summary['total_technical_requests'] = $row['total_requests'];
            }
            $tech_stmt->close();
        } catch (Exception $e) {
            // Table or column might not exist, handle gracefully
            $employee_summary['total_technical_requests'] = 0;
        }
    }
}
// Prepare comparison data if we're in the comparison section
if ($active_section == 'comparison') {
    // Get comparison employee IDs from URL parameters
    $employee1_id = isset($_GET['employee1']) ? $_GET['employee1'] : null;
    $employee2_id = isset($_GET['employee2']) ? $_GET['employee2'] : null;
    
    // If no employees are selected for comparison, use the first two employees from the department
    if (!$employee1_id && count($employees) > 0) {
        $employee1_id = $employees[0]['id'];
    }
    if (!$employee2_id && count($employees) > 1) {
        $employee2_id = $employees[1]['id'];
    }
    
    // Get data for employee 1
    if ($employee1_id) {
        $employee1_data = array(
            'id' => $employee1_id,
            'name' => '',
            'total_hours_worked' => 0,
            'total_days_worked' => 0,
            'total_tasks_assigned' => 0,
            'total_tasks_completed' => 0,
            'total_leave_requests' => 0,
            'total_trainings_completed' => 0,
            'avg_performance_rating' => 0,
            'total_technical_requests' => 0
        );
        
        // Get employee name
        foreach ($employees as $emp) {
            if ($emp['id'] == $employee1_id) {
                $employee1_data['name'] = $emp['name'];
                break;
            }
        }
        
        // Calculate metrics for employee 1
        try {
            // Calculate total hours worked
            $hours_sql = "SELECT 
                            SUM(
                                CASE 
                                    WHEN check_in IS NOT NULL AND check_out IS NOT NULL THEN
                                        TIMESTAMPDIFF(HOUR, check_in, check_out) + 
                                        (TIMESTAMPDIFF(MINUTE, check_in, check_out) % 60) / 60.0
                                    ELSE 0
                                END
                            ) as total_hours,
                            COUNT(*) as total_days
                          FROM employee_attendance 
                          WHERE employee_id = ? AND status = 'Present'";
            $hours_stmt = $conn->prepare($hours_sql);
            $hours_stmt->bind_param("i", $employee1_id);
            $hours_stmt->execute();
            $hours_result = $hours_stmt->get_result();
            if ($hours_result->num_rows > 0) {
                $row = $hours_result->fetch_assoc();
                $employee1_data['total_hours_worked'] = round($row['total_hours'], 1);
                $employee1_data['total_days_worked'] = $row['total_days'];
            }
            $hours_stmt->close();
        } catch (Exception $e) {
            // Table or column might not exist, handle gracefully
            $employee1_data['total_hours_worked'] = 0;
            $employee1_data['total_days_worked'] = 0;
        }
        
        try {
            // Calculate total tasks assigned and completed
            $tasks_sql = "SELECT 
                            COUNT(*) as total_tasks,
                            SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed_tasks
                          FROM tasks 
                          WHERE assignee_id = ?";
            $tasks_stmt = $conn->prepare($tasks_sql);
            $tasks_stmt->bind_param("i", $employee1_id);
            $tasks_stmt->execute();
            $tasks_result = $tasks_stmt->get_result();
            if ($tasks_result->num_rows > 0) {
                $row = $tasks_result->fetch_assoc();
                $employee1_data['total_tasks_assigned'] = $row['total_tasks'];
                $employee1_data['total_tasks_completed'] = $row['completed_tasks'];
            }
            $tasks_stmt->close();
        } catch (Exception $e) {
            // Table or column might not exist, handle gracefully
            $employee1_data['total_tasks_assigned'] = 0;
            $employee1_data['total_tasks_completed'] = 0;
        }
        
        try {
            // Calculate total leave requests
            $leaves_sql = "SELECT COUNT(*) as total_leaves FROM employee_attendance 
                           WHERE employee_id = ? AND status = 'Absent'";
            $leaves_stmt = $conn->prepare($leaves_sql);
            $leaves_stmt->bind_param("i", $employee1_id);
            $leaves_stmt->execute();
            $leaves_result = $leaves_stmt->get_result();
            if ($leaves_result->num_rows > 0) {
                $row = $leaves_result->fetch_assoc();
                $employee1_data['total_leave_requests'] = $row['total_leaves'];
            }
            $leaves_stmt->close();
        } catch (Exception $e) {
            // Table or column might not exist, handle gracefully
            $employee1_data['total_leave_requests'] = 0;
        }
        
        try {
            // Calculate total trainings completed
            $train_sql = "SELECT COUNT(*) as total_trainings FROM training_records 
                          WHERE employee_id = ? AND status = 'Completed'";
            $train_stmt = $conn->prepare($train_sql);
            $train_stmt->bind_param("i", $employee1_id);
            $train_stmt->execute();
            $train_result = $train_stmt->get_result();
            if ($train_result->num_rows > 0) {
                $row = $train_result->fetch_assoc();
                $employee1_data['total_trainings_completed'] = $row['total_trainings'];
            }
            $train_stmt->close();
        } catch (Exception $e) {
            // Table or column might not exist, handle gracefully
            $employee1_data['total_trainings_completed'] = 0;
        }
        
        try {
            // Calculate average performance rating
            $perf_sql = "SELECT AVG(performance_rating) as avg_rating FROM performance_reviews 
                          WHERE employee_id = ?";
            $perf_stmt = $conn->prepare($perf_sql);
            $perf_stmt->bind_param("i", $employee1_id);
            $perf_stmt->execute();
            $perf_result = $perf_stmt->get_result();
            if ($perf_result->num_rows > 0) {
                $row = $perf_result->fetch_assoc();
                $employee1_data['avg_performance_rating'] = round($row['avg_rating'], 1);
            }
            $perf_stmt->close();
        } catch (Exception $e) {
            // Table or column might not exist, handle gracefully
            $employee1_data['avg_performance_rating'] = 0;
        }
        
        try {
            // Calculate total technical requests
            $tech_sql = "SELECT COUNT(*) as total_requests FROM technical_requests 
                          WHERE employee_id = ?";
            $tech_stmt = $conn->prepare($tech_sql);
            $tech_stmt->bind_param("i", $employee1_id);
            $tech_stmt->execute();
            $tech_result = $tech_stmt->get_result();
            if ($tech_result->num_rows > 0) {
                $row = $tech_result->fetch_assoc();
                $employee1_data['total_technical_requests'] = $row['total_requests'];
            }
            $tech_stmt->close();
        } catch (Exception $e) {
            // Table or column might not exist, handle gracefully
            $employee1_data['total_technical_requests'] = 0;
        }
        
        $comparison_data['employee1'] = $employee1_data;
    }
    
    // Get data for employee 2
    if ($employee2_id) {
        $employee2_data = array(
            'id' => $employee2_id,
            'name' => '',
            'total_hours_worked' => 0,
            'total_days_worked' => 0,
            'total_tasks_assigned' => 0,
            'total_tasks_completed' => 0,
            'total_leave_requests' => 0,
            'total_trainings_completed' => 0,
            'avg_performance_rating' => 0,
            'total_technical_requests' => 0
        );
        
        // Get employee name
        foreach ($employees as $emp) {
            if ($emp['id'] == $employee2_id) {
                $employee2_data['name'] = $emp['name'];
                break;
            }
        }
        
        // Calculate metrics for employee 2
        try {
            // Calculate total hours worked
            $hours_sql = "SELECT 
                            SUM(
                                CASE 
                                    WHEN check_in IS NOT NULL AND check_out IS NOT NULL THEN
                                        TIMESTAMPDIFF(HOUR, check_in, check_out) + 
                                        (TIMESTAMPDIFF(MINUTE, check_in, check_out) % 60) / 60.0
                                    ELSE 0
                                END
                            ) as total_hours,
                            COUNT(*) as total_days
                          FROM employee_attendance 
                          WHERE employee_id = ? AND status = 'Present'";
            $hours_stmt = $conn->prepare($hours_sql);
            $hours_stmt->bind_param("i", $employee2_id);
            $hours_stmt->execute();
            $hours_result = $hours_stmt->get_result();
            if ($hours_result->num_rows > 0) {
                $row = $hours_result->fetch_assoc();
                $employee2_data['total_hours_worked'] = round($row['total_hours'], 1);
                $employee2_data['total_days_worked'] = $row['total_days'];
            }
            $hours_stmt->close();
        } catch (Exception $e) {
            // Table or column might not exist, handle gracefully
            $employee2_data['total_hours_worked'] = 0;
            $employee2_data['total_days_worked'] = 0;
        }
        
        try {
            // Calculate total tasks assigned and completed
            $tasks_sql = "SELECT 
                            COUNT(*) as total_tasks,
                            SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed_tasks
                          FROM tasks 
                          WHERE assignee_id = ?";
            $tasks_stmt = $conn->prepare($tasks_sql);
            $tasks_stmt->bind_param("i", $employee2_id);
            $tasks_stmt->execute();
            $tasks_result = $tasks_stmt->get_result();
            if ($tasks_result->num_rows > 0) {
                $row = $tasks_result->fetch_assoc();
                $employee2_data['total_tasks_assigned'] = $row['total_tasks'];
                $employee2_data['total_tasks_completed'] = $row['completed_tasks'];
            }
            $tasks_stmt->close();
        } catch (Exception $e) {
            // Table or column might not exist, handle gracefully
            $employee2_data['total_tasks_assigned'] = 0;
            $employee2_data['total_tasks_completed'] = 0;
        }
        
        try {
            // Calculate total leave requests
            $leaves_sql = "SELECT COUNT(*) as total_leaves FROM employee_attendance 
                           WHERE employee_id = ? AND status = 'Absent'";
            $leaves_stmt = $conn->prepare($leaves_sql);
            $leaves_stmt->bind_param("i", $employee2_id);
            $leaves_stmt->execute();
            $leaves_result = $leaves_stmt->get_result();
            if ($leaves_result->num_rows > 0) {
                $row = $leaves_result->fetch_assoc();
                $employee2_data['total_leave_requests'] = $row['total_leaves'];
            }
            $leaves_stmt->close();
        } catch (Exception $e) {
            // Table or column might not exist, handle gracefully
            $employee2_data['total_leave_requests'] = 0;
        }
        
        try {
            // Calculate total trainings completed
            $train_sql = "SELECT COUNT(*) as total_trainings FROM training_records 
                          WHERE employee_id = ? AND status = 'Completed'";
            $train_stmt = $conn->prepare($train_sql);
            $train_stmt->bind_param("i", $employee2_id);
            $train_stmt->execute();
            $train_result = $train_stmt->get_result();
            if ($train_result->num_rows > 0) {
                $row = $train_result->fetch_assoc();
                $employee2_data['total_trainings_completed'] = $row['total_trainings'];
            }
            $train_stmt->close();
        } catch (Exception $e) {
            // Table or column might not exist, handle gracefully
            $employee2_data['total_trainings_completed'] = 0;
        }
        
        try {
            // Calculate average performance rating
            $perf_sql = "SELECT AVG(performance_rating) as avg_rating FROM performance_reviews 
                          WHERE employee_id = ?";
            $perf_stmt = $conn->prepare($perf_sql);
            $perf_stmt->bind_param("i", $employee2_id);
            $perf_stmt->execute();
            $perf_result = $perf_stmt->get_result();
            if ($perf_result->num_rows > 0) {
                $row = $perf_result->fetch_assoc();
                $employee2_data['avg_performance_rating'] = round($row['avg_rating'], 1);
            }
            $perf_stmt->close();
        } catch (Exception $e) {
            // Table or column might not exist, handle gracefully
            $employee2_data['avg_performance_rating'] = 0;
        }
        
        try {
            // Calculate total technical requests
            $tech_sql = "SELECT COUNT(*) as total_requests FROM technical_requests 
                          WHERE employee_id = ?";
            $tech_stmt = $conn->prepare($tech_sql);
            $tech_stmt->bind_param("i", $employee2_id);
            $tech_stmt->execute();
            $tech_result = $tech_stmt->get_result();
            if ($tech_result->num_rows > 0) {
                $row = $tech_result->fetch_assoc();
                $employee2_data['total_technical_requests'] = $row['total_requests'];
            }
            $tech_stmt->close();
        } catch (Exception $e) {
            // Table or column might not exist, handle gracefully
            $employee2_data['total_technical_requests'] = 0;
        }
        
        $comparison_data['employee2'] = $employee2_data;
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manager Dashboard - Buymeabook</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #4b0082 0%, #6a0dad 50%, #8a2be2 100%);
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
            color: #4b0082;
            text-decoration: none;
            font-weight: 600;
            padding: 10px 15px;
            border-radius: 50px;
            transition: all 0.3s;
        }
        
        .nav-link:hover, .nav-link.active {
            background: #4b0082;
            color: white;
        }
        
        .dashboard {
            display: grid;
            grid-template-columns: 280px 1fr;
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
            color: #4b0082;
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
            background: #4b0082;
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
            color: #4b0082;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.08);
            text-align: center;
        }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: #4b0082;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
            margin: 0 auto 15px;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #4b0082;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.9rem;
        }
        
        .stat-sublabel {
            color: #888;
            font-size: 0.8rem;
            margin-top: 5px;
            font-style: italic;
        }
        
        .data-section {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.08);
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .data-table th, .data-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .data-table th {
            background: #f8f9fa;
            color: #4b0082;
            font-weight: 600;
        }
        
        .data-table tr:hover {
            background: #f8f9fa;
        }
        
        .badge {
            padding: 5px 10px;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .badge-success {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-warning {
            background: #fff3cd;
            color: #856404;
        }
        
        .badge-danger {
            background: #f8d7da;
            color: #721c24;
        }
        
        .badge-info {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .badge-secondary {
            background: #e2e3e5;
            color: #383d41;
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
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            overflow-y: auto;
        }
        
        .modal-content {
            background-color: #fefefe;
            margin: 5% auto;
            padding: 20px;
            border: none;
            border-radius: 15px;
            width: 90%;
            max-width: 900px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.2);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .modal-title {
            font-size: 1.5rem;
            color: #4b0082;
        }
        
        .close {
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .close:hover {
            color: #000;
        }
        
        .action-buttons {
            display: flex;
            gap: 5px;
        }
        
        .action-buttons .btn {
            padding: 5px 10px;
            font-size: 14px;
        }
        
        .btn {
            padding: 12px 20px;
            background: #4b0082;
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
            background: #3a0066;
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background: #6c757d;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .btn-danger {
            background: #dc3545;
        }
        
        .btn-danger:hover {
            background: #c82333;
        }
        
        .btn-info {
            background: #17a2b8;
        }
        
        .btn-info:hover {
            background: #138496;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
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
            border-color: #4b0082;
            outline: none;
        }
        
        .form-control[readonly] {
            background-color: #f8f9fa;
            cursor: not-allowed;
        }
        
        .manager-theme {
            background: linear-gradient(135deg, #4b0082 0%, #6a0dad 50%, #8a2be2 100%);
        }
        
        .manager-header {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .manager-title {
            color: #4b0082;
            font-size: 1.8rem;
            font-weight: 700;
        }
        
        .manager-user {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .manager-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #4b0082;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }
        
        .employee-selector {
            margin-bottom: 20px;
        }
        
        .employee-selector select {
            padding: 10px;
            border-radius: 8px;
            border: 1px solid #ddd;
            font-size: 16px;
            width: 100%;
            max-width: 300px;
        }
        
        .employee-details {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.08);
        }
        
        .detail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 15px;
        }
        
        .detail-item {
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .detail-label {
            font-weight: 600;
            color: #4b0082;
            margin-bottom: 5px;
        }
        
        .detail-value {
            color: #333;
        }
        
        .analytics-section {
            margin-top: 30px;
        }
        
        .analytics-title {
            font-size: 1.3rem;
            color: #4b0082;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .analytics-content {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
        }
        
        .analytics-item {
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .analytics-item:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }
        
        .analytics-label {
            font-weight: 600;
            color: #4b0082;
        }
        
        .analytics-value {
            color: #333;
        }
        
        .no-data {
            text-align: center;
            padding: 20px;
            color: #666;
            font-style: italic;
        }
        
        .content-section {
            display: none;
        }
        
        .content-section.active {
            display: block;
        }
        
        .department-badge {
            background: #4b0082;
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 500;
            margin-left: 10px;
        }
        
        .chart-container {
            height: 300px;
            margin-bottom: 20px;
        }
        
        .view-employee-section {
            margin-bottom: 25px;
        }
        
        .view-employee-section-title {
            font-size: 1.2rem;
            color: #4b0082;
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 2px solid #eee;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .view-employee-details {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 15px;
        }
        
        .view-employee-item {
            padding: 12px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .view-employee-label {
            font-weight: 600;
            color: #4b0082;
            margin-bottom: 5px;
            font-size: 0.9rem;
        }
        
        .view-employee-value {
            color: #333;
        }
        
        .profile-photo-container {
            display: flex;
            justify-content: center;
            margin-bottom: 15px;
        }
        
        .profile-photo {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #4b0082;
        }
        
        .profile-initials {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            background: #4b0082;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 48px;
            border: 3px solid #4b0082;
        }
        
        .employee-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            display: inline-block;
            vertical-align: middle;
            margin-right: 10px;
        }
        
        .avatar-initials {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #4b0082;
            color: white;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            vertical-align: middle;
            margin-right: 10px;
        }
        
        .employee-details-header {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .employee-details-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #4b0082;
            margin-right: 20px;
        }
        
        .employee-details-initials {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: #4b0082;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 24px;
            margin-right: 20px;
        }
        
        .employee-details-info h3 {
            color: #4b0082;
            margin-bottom: 5px;
        }
        
        .employee-details-info p {
            color: #666;
            margin: 0;
        }
        
        .data-table-wrapper {
            overflow-x: auto;
        }
        
        .employee-row {
            transition: all 0.3s ease;
        }
        
        .employee-summary {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05);
        }
        
        .employee-summary-title {
            font-size: 1.3rem;
            color: #4b0082;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #dee2e6;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .employee-summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .employee-summary-item {
            background: white;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s ease;
        }
        
        .employee-summary-item:hover {
            transform: translateY(-5px);
        }
        
        .employee-summary-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #4b0082;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 18px;
            margin: 0 auto 10px;
        }
        
        .employee-summary-value {
            font-size: 1.8rem;
            font-weight: 700;
            color: #4b0082;
            margin-bottom: 5px;
        }
        
        .employee-summary-label {
            color: #666;
            font-size: 0.9rem;
        }
        
        .employee-summary-sublabel {
            color: #888;
            font-size: 0.75rem;
            margin-top: 3px;
            font-style: italic;
        }
        
        .hours-summary-section {
            margin-top: 15px;
        }
        
        .hours-summary-title {
            font-size: 1.1rem;
            color: #4b0082;
            margin-bottom: 10px;
            padding-bottom: 5px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .hours-summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 10px;
        }
        
        .hours-summary-item {
            background: white;
            border-radius: 8px;
            padding: 10px;
            text-align: center;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
        }
        
        .hours-summary-icon {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: #4b0082;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 14px;
            margin: 0 auto 5px;
        }
        
        .hours-summary-value {
            font-size: 1.4rem;
            font-weight: 700;
            color: #4b0082;
            margin-bottom: 3px;
        }
        
        .hours-summary-label {
            color: #666;
            font-size: 0.8rem;
        }
        
        .comparison-controls {
            display: flex;
            gap: 20px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }
        
        .comparison-selector {
            flex: 1;
            min-width: 250px;
        }
        
        .comparison-selector label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #444;
        }
        
        .comparison-selector select {
            width: 100%;
            padding: 10px;
            border-radius: 8px;
            border: 1px solid #ddd;
            font-size: 16px;
        }
        
        .comparison-button {
            align-self: flex-end;
        }
        
        .comparison-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .comparison-table th, .comparison-table td {
            padding: 15px;
            text-align: center;
            border-bottom: 1px solid #eee;
        }
        
        .comparison-table th {
            background: #f8f9fa;
            color: #4b0082;
            font-weight: 600;
            font-size: 1.1rem;
        }
        
        .comparison-table th:first-child {
            text-align: left;
            background: #4b0082;
            color: white;
        }
        
        .comparison-table td:first-child {
            text-align: left;
            font-weight: 600;
            color: #4b0082;
        }
        
        .comparison-table tr:hover {
            background: #f8f9fa;
        }
        
        .comparison-chart-container {
            height: 400px;
            margin: 30px 0;
        }
        
        .metric-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            margin-left: 10px;
        }
        
        .metric-badge-higher {
            background: #d4edda;
            color: #155724;
        }
        
        .metric-badge-lower {
            background: #f8d7da;
            color: #721c24;
        }
        
        .metric-badge-equal {
            background: #fff3cd;
            color: #856404;
        }
        
        @media (max-width: 1024px) {
            .dashboard {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            }
            
            .employee-summary-grid {
                grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            }
            
            .hours-summary-grid {
                grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            }
            
            .comparison-controls {
                flex-direction: column;
            }
            
            .comparison-button {
                align-self: stretch;
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
            
            .detail-grid {
                grid-template-columns: 1fr;
            }
            
            .view-employee-details {
                grid-template-columns: 1fr;
            }
            
            .employee-details-header {
                flex-direction: column;
                text-align: center;
            }
            
            .employee-details-avatar, .employee-details-initials {
                margin-right: 0;
                margin-bottom: 15px;
            }
            
            .employee-summary-grid {
                grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            }
            
            .hours-summary-grid {
                grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            }
            
            .comparison-table {
                font-size: 0.9rem;
            }
            
            .comparison-table th, .comparison-table td {
                padding: 10px 5px;
            }
        }
    </style>
</head>
<body class="manager-theme">
    <div class="container">
        <div class="manager-header">
            <div style="display: flex; align-items: center;">
                <img src="https://ik.imagekit.io/nuvq7aygp/BMB_Logo.jpg" alt="BMB Logo" class="logo">
                <div style="margin-left: 20px;">
                    <h1 class="manager-title">Manager | <?php echo htmlspecialchars($manager_department); ?></h1>
                    <p>Welcome back, <?php echo htmlspecialchars($manager['name']); ?></p>
                </div>
            </div>
            <div class="manager-user">
                <div class="manager-avatar">
                    <?php 
                    // Check if bmb_avatar exists and display it, otherwise show initials
                    if (!empty($manager['bmb_avatar'])) {
                        echo '<img src="data:image/jpeg;base64,'.base64_encode($manager['bmb_avatar']).'" alt="Avatar" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover;">';
                    } else {
                        echo strtoupper(substr($manager['name'], 0, 1));
                    }
                    ?>
                </div>
                <div>
                    <div><?php echo htmlspecialchars($manager['name']); ?></div>
                    <div style="font-size: 0.8rem; color: #666;">Manager</div>
                </div>
                <a href="logout.php" class="btn btn-secondary" style="margin-left: 15px;">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
        
        <?php if (!empty($message)): ?>
            <div class="message <?php echo $message_type; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <div class="dashboard">
            <div class="sidebar">
                <h2 class="sidebar-title">Employee Analytics</h2>
                <ul class="sidebar-menu">
                    <li><a href="start.php"><i class="fas fa-home"></i> Home</a></li>
                    <li><a href="?section=dashboard" class="<?php echo $active_section == 'dashboard' ? 'active' : ''; ?>"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                    <li><a href="?section=employee-management" class="<?php echo $active_section == 'employee-management' ? 'active' : ''; ?>"><i class="fas fa-users"></i> Employee Management</a></li>
                    <li><a href="?section=employee-details<?php echo $selected_employee_id ? '&employee_id='.$selected_employee_id : ''; ?>" class="<?php echo $active_section == 'employee-details' ? 'active' : ''; ?>"><i class="fas fa-user"></i> Employee Details</a></li>
                    <li><a href="?section=attendance<?php echo $selected_employee_id ? '&employee_id='.$selected_employee_id : ''; ?>" class="<?php echo $active_section == 'attendance' ? 'active' : ''; ?>"><i class="fas fa-user-clock"></i> Attendance Record</a></li>
                    <li><a href="?section=tasks<?php echo $selected_employee_id ? '&employee_id='.$selected_employee_id : ''; ?>" class="<?php echo $active_section == 'tasks' ? 'active' : ''; ?>"><i class="fas fa-tasks"></i> Tasks</a></li>
                    <li><a href="?section=performance<?php echo $selected_employee_id ? '&employee_id='.$selected_employee_id : ''; ?>" class="<?php echo $active_section == 'performance' ? 'active' : ''; ?>"><i class="fas fa-chart-line"></i> Performance</a></li>
                    <li><a href="?section=technical-requests<?php echo $selected_employee_id ? '&employee_id='.$selected_employee_id : ''; ?>" class="<?php echo $active_section == 'technical-requests' ? 'active' : ''; ?>"><i class="fas fa-tools"></i> Technical Requests</a></li>
                    <li><a href="?section=trainings<?php echo $selected_employee_id ? '&employee_id='.$selected_employee_id : ''; ?>" class="<?php echo $active_section == 'trainings' ? 'active' : ''; ?>"><i class="fas fa-graduation-cap"></i> Trainings</a></li>
                    <li><a href="?section=comparison" class="<?php echo $active_section == 'comparison' ? 'active' : ''; ?>"><i class="fas fa-balance-scale"></i> Comparison</a></li>
                </ul>
            </div>
            
            <div class="main-content">
                <div class="employee-selector">
                    <label for="employee-select" style="display: block; margin-bottom: 8px; font-weight: 500;">Select Employee:</label>
                    <select id="employee-select" class="form-control" onchange="loadEmployeeData(this.value)">
                        <option value="">-- Select Employee --</option>
                        <?php foreach ($employees as $employee): ?>
                            <option value="<?php echo $employee['id']; ?>" <?php if ($selected_employee_id == $employee['id']) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($employee['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Employee Summary Section (only shown when an employee is selected) -->
                <?php if ($selected_employee): ?>
                    <div class="employee-summary">
                        <h3 class="employee-summary-title"><i class="fas fa-chart-pie"></i> Employee Summary: <?php echo htmlspecialchars($selected_employee['name']); ?></h3>
                        <div class="employee-summary-grid">
                            <div class="employee-summary-item">
                                <div class="employee-summary-icon">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <div class="employee-summary-value"><?php echo $employee_summary['total_hours_worked']; ?></div>
                                <div class="employee-summary-label">Total Hours Worked</div>
                                <div class="employee-summary-sublabel">Based on check-in/out times</div>
                                
                                <div class="hours-summary-section">
                                    <div class="hours-summary-title">Average Hours</div>
                                    <div class="hours-summary-grid">
                                        <div class="hours-summary-item">
                                            <div class="hours-summary-icon">
                                                <i class="fas fa-calendar-day"></i>
                                            </div>
                                            <div class="hours-summary-value"><?php echo $employee_summary['daily_avg_hours']; ?></div>
                                            <div class="hours-summary-label">Daily</div>
                                        </div>
                                        
                                        <div class="hours-summary-item">
                                            <div class="hours-summary-icon">
                                                <i class="fas fa-calendar-week"></i>
                                            </div>
                                            <div class="hours-summary-value"><?php echo $employee_summary['weekly_avg_hours']; ?></div>
                                            <div class="hours-summary-label">Weekly</div>
                                        </div>
                                        
                                        <div class="hours-summary-item">
                                            <div class="hours-summary-icon">
                                                <i class="fas fa-calendar-alt"></i>
                                            </div>
                                            <div class="hours-summary-value"><?php echo $employee_summary['monthly_avg_hours']; ?></div>
                                            <div class="hours-summary-label">Monthly</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="employee-summary-item">
                                <div class="employee-summary-icon">
                                    <i class="fas fa-calendar-check"></i>
                                </div>
                                <div class="employee-summary-value"><?php echo $employee_summary['total_days_worked']; ?></div>
                                <div class="employee-summary-label">Total Days Worked</div>
                            </div>
                            
                            <div class="employee-summary-item">
                                <div class="employee-summary-icon">
                                    <i class="fas fa-tasks"></i>
                                </div>
                                <div class="employee-summary-value"><?php echo $employee_summary['total_tasks_assigned']; ?></div>
                                <div class="employee-summary-label">Tasks Assigned</div>
                            </div>
                            
                            <div class="employee-summary-item">
                                <div class="employee-summary-icon">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                                <div class="employee-summary-value"><?php echo $employee_summary['total_tasks_completed']; ?></div>
                                <div class="employee-summary-label">Tasks Completed</div>
                            </div>
                            
                            <div class="employee-summary-item">
                                <div class="employee-summary-icon">
                                    <i class="fas fa-calendar-times"></i>
                                </div>
                                <div class="employee-summary-value"><?php echo $employee_summary['total_leave_requests']; ?></div>
                                <div class="employee-summary-label">Leave Requests</div>
                            </div>
                            
                            <div class="employee-summary-item">
                                <div class="employee-summary-icon">
                                    <i class="fas fa-graduation-cap"></i>
                                </div>
                                <div class="employee-summary-value"><?php echo $employee_summary['total_trainings_completed']; ?></div>
                                <div class="employee-summary-label">Trainings Completed</div>
                            </div>
                            
                            <div class="employee-summary-item">
                                <div class="employee-summary-icon">
                                    <i class="fas fa-star"></i>
                                </div>
                                <div class="employee-summary-value"><?php echo $employee_summary['avg_performance_rating']; ?>/10</div>
                                <div class="employee-summary-label">Avg Performance</div>
                            </div>
                            
                            <div class="employee-summary-item">
                                <div class="employee-summary-icon">
                                    <i class="fas fa-tools"></i>
                                </div>
                                <div class="employee-summary-value"><?php echo $employee_summary['total_technical_requests']; ?></div>
                                <div class="employee-summary-label">Tech Requests</div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Dashboard Section -->
                <div id="dashboard" class="content-section <?php echo $active_section == 'dashboard' ? 'active' : ''; ?>">
                    <h2 class="section-title"><i class="fas fa-tachometer-alt"></i> Dashboard Analytics</h2>
                    
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="stat-value"><?php echo count($employees); ?></div>
                            <div class="stat-label">Total Employees</div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-building"></i>
                            </div>
                            <div class="stat-value"><?php echo count($departments); ?></div>
                            <div class="stat-label">Departments</div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-user-tie"></i>
                            </div>
                            <div class="stat-value">
                                <?php 
                                $manager_count = 0;
                                foreach ($employees as $emp) {
                                    if ($emp['is_manager'] == 1) $manager_count++;
                                }
                                echo $manager_count;
                                ?>
                            </div>
                            <div class="stat-label">Managers</div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-user"></i>
                            </div>
                            <div class="stat-value">
                                <?php 
                                $staff_count = 0;
                                foreach ($employees as $emp) {
                                    if ($emp['is_manager'] != 1) $staff_count++;
                                }
                                echo $staff_count;
                                ?>
                            </div>
                            <div class="stat-label">Staff</div>
                        </div>
                    </div>
                    
                    <!-- Employee Analytics Section -->
                    <div class="data-section">
                        <h3 class="analytics-title"><i class="fas fa-chart-bar"></i> Employee Analytics</h3>
                        <div class="analytics-content">
                            <div class="stats-grid">
                                <div class="stat-card">
                                    <div class="stat-icon">
                                        <i class="fas fa-calendar-week"></i>
                                    </div>
                                    <div class="stat-value"><?php echo $weekly_attendance_avg; ?></div>
                                    <div class="stat-label">Avg Weekly Attendance</div>
                                </div>
                                
                                <div class="stat-card">
                                    <div class="stat-icon">
                                        <i class="fas fa-calendar-alt"></i>
                                    </div>
                                    <div class="stat-value"><?php echo $monthly_attendance_avg; ?></div>
                                    <div class="stat-label">Avg Monthly Attendance</div>
                                </div>
                                
                                <div class="stat-card">
                                    <div class="stat-icon">
                                        <i class="fas fa-calendar-times"></i>
                                    </div>
                                    <div class="stat-value"><?php echo $monthly_leaves_avg; ?></div>
                                    <div class="stat-label">Avg Monthly Leaves</div>
                                </div>
                                
                                <div class="stat-card">
                                    <div class="stat-icon">
                                        <i class="fas fa-chart-line"></i>
                                    </div>
                                    <div class="stat-value"><?php echo $monthly_performance_avg; ?>/10</div>
                                    <div class="stat-label">Avg Performance Rating</div>
                                </div>
                                
                                <div class="stat-card">
                                    <div class="stat-icon">
                                        <i class="fas fa-graduation-cap"></i>
                                    </div>
                                    <div class="stat-value"><?php echo $monthly_training_avg; ?></div>
                                    <div class="stat-label">Avg Monthly Trainings</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="data-section">
                        <h3 class="analytics-title"><i class="fas fa-chart-pie"></i> Department Overview</h3>
                        <div class="analytics-content">
                            <div class="chart-container">
                                <canvas id="departmentChart"></canvas>
                            </div>
                            <div class="detail-grid">
                                <div class="detail-item">
                                    <div class="detail-label">Department Name</div>
                                    <div class="detail-value"><?php echo htmlspecialchars($manager_department); ?></div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-label">Total Employees</div>
                                    <div class="detail-value"><?php echo count($employees); ?></div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-label">Manager Count</div>
                                    <div class="detail-value"><?php echo $manager_count; ?></div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-label">Staff Count</div>
                                    <div class="detail-value"><?php echo $staff_count; ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="data-section">
                        <h3 class="analytics-title"><i class="fas fa-clock"></i> Recent Activities</h3>
                        <div class="analytics-content">
                            <div class="analytics-item">
                                <div class="analytics-label">Today's Attendance</div>
                                <div class="analytics-value">
                                    <?php 
                                    // This would normally be calculated from actual attendance data
                                    $present_count = rand(0, count($employees));
                                    echo "$present_count employees present today";
                                    ?>
                                </div>
                            </div>
                            <div class="analytics-item">
                                <div class="analytics-label">Pending Tasks</div>
                                <div class="analytics-value">
                                    <?php 
                                    // This would normally be calculated from actual tasks data
                                    $pending_tasks = rand(0, 20);
                                    echo "$pending_tasks tasks pending across the department";
                                    ?>
                                </div>
                            </div>
                            <div class="analytics-item">
                                <div class="analytics-label">Upcoming Reviews</div>
                                <div class="analytics-value">
                                    <?php 
                                    // This would normally be calculated from actual performance data
                                    $upcoming_reviews = rand(0, 5);
                                    echo "$upcoming_reviews performance reviews scheduled";
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Employee Management Section -->
                <div id="employee-management" class="content-section <?php echo $active_section == 'employee-management' ? 'active' : ''; ?>">
                    <h2 class="section-title"><i class="fas fa-users-cog"></i> Employee Management</h2>
                    
                    <div class="data-section">
                        <h3 class="form-title"><i class="fas fa-users"></i> Employee Directory</h3>
                        <div style="overflow-x: auto;">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Phone</th>
                                        <th>Department</th>
                                        <th>Position</th>
                                        <th>Manager</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    // Filter employees to show based on selected employee
                                    $employees_to_show = array();
                                    if ($selected_employee_id) {
                                        foreach ($employees as $emp) {
                                            if ($emp['id'] == $selected_employee_id) {
                                                $employees_to_show[] = $emp;
                                                break;
                                            }
                                        }
                                    } else {
                                        $employees_to_show = $employees;
                                    }
                                    
                                    if (count($employees_to_show) > 0): 
                                        foreach ($employees_to_show as $employee): 
                                    ?>
                                            <tr class="employee-row employee-row-<?php echo $employee['id']; ?>">
                                                <td><?php echo $employee['id']; ?></td>
                                                <td>
                                                    <?php 
                                                    // Check if bmb_avatar exists and display it, otherwise show initials
                                                    if (!empty($employee['bmb_avatar'])) {
                                                        echo '<img src="data:image/jpeg;base64,'.base64_encode($employee['bmb_avatar']).'" alt="Avatar" class="employee-avatar">';
                                                    } else {
                                                        // Extract initials from name
                                                        $name_parts = explode(' ', trim($employee['name']));
                                                        $initials = '';
                                                        foreach ($name_parts as $part) {
                                                            if (!empty($part)) {
                                                                $initials .= strtoupper(substr($part, 0, 1));
                                                                if (strlen($initials) >= 2) break;
                                                            }
                                                        }
                                                        if (empty($initials)) $initials = 'E';
                                                        echo '<div class="avatar-initials">'.$initials.'</div>';
                                                    }
                                                    echo htmlspecialchars($employee['name']);
                                                    ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($employee['email']); ?></td>
                                                <td><?php echo htmlspecialchars($employee['phone_primary'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($employee['department']); ?></td>
                                                <td><?php echo htmlspecialchars($employee['job_title'] ?? 'N/A'); ?></td>
                                                <td>
                                                    <?php if ($employee['is_manager']): ?>
                                                        <span class="badge badge-success">Yes</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-secondary">No</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="action-buttons">
                                                        <button type="button" class="btn btn-secondary edit-employee" 
                                                                data-id="<?php echo $employee['id']; ?>"
                                                                data-name="<?php echo htmlspecialchars($employee['name']); ?>"
                                                                data-email="<?php echo htmlspecialchars($employee['email']); ?>"
                                                                data-phone="<?php echo htmlspecialchars($employee['phone_primary'] ?? ''); ?>"
                                                                data-department="<?php echo htmlspecialchars($employee['department']); ?>"
                                                                data-position="<?php echo htmlspecialchars($employee['job_title'] ?? ''); ?>"
                                                                data-is_manager="<?php echo $employee['is_manager']; ?>"
                                                                data-job_title="<?php echo htmlspecialchars($employee['job_title'] ?? ''); ?>">
                                                            <i class="fas fa-edit"></i> Edit
                                                        </button>
                                                        
                                                        <button type="button" class="btn btn-info view-employee" 
                                                                data-id="<?php echo $employee['id']; ?>"
                                                                data-name="<?php echo htmlspecialchars($employee['name']); ?>"
                                                                data-first_name="<?php echo htmlspecialchars($employee['first_name']); ?>"
                                                                data-middle_name="<?php echo htmlspecialchars($employee['middle_name'] ?? ''); ?>"
                                                                data-last_name="<?php echo htmlspecialchars($employee['last_name']); ?>"
                                                                data-date_of_birth="<?php echo htmlspecialchars($employee['date_of_birth'] ?? ''); ?>"
                                                                data-gender="<?php echo htmlspecialchars($employee['gender'] ?? ''); ?>"
                                                                data-nationality="<?php echo htmlspecialchars($employee['nationality'] ?? ''); ?>"
                                                                data-marital_status="<?php echo htmlspecialchars($employee['marital_status'] ?? ''); ?>"
                                                                data-bmb_avatar="<?php echo htmlspecialchars($employee['bmb_avatar'] ?? ''); ?>"
                                                                data-government_id_type="<?php echo htmlspecialchars($employee['government_id_type'] ?? ''); ?>"
                                                                data-government_id_number="<?php echo htmlspecialchars($employee['government_id_number'] ?? ''); ?>"
                                                                data-phone_primary="<?php echo htmlspecialchars($employee['phone_primary'] ?? ''); ?>"
                                                                data-phone_alternate="<?php echo htmlspecialchars($employee['phone_alternate'] ?? ''); ?>"
                                                                data-email="<?php echo htmlspecialchars($employee['email']); ?>"
                                                                data-current_address="<?php echo htmlspecialchars($employee['current_address'] ?? ''); ?>"
                                                                data-permanent_address="<?php echo htmlspecialchars($employee['permanent_address'] ?? ''); ?>"
                                                                data-emergency_contact_name="<?php echo htmlspecialchars($employee['emergency_contact_name'] ?? ''); ?>"
                                                                data-emergency_contact_relationship="<?php echo htmlspecialchars($employee['emergency_contact_relationship'] ?? ''); ?>"
                                                                data-emergency_contact_phone="<?php echo htmlspecialchars($employee['emergency_contact_phone'] ?? ''); ?>"
                                                                data-employee_id="<?php echo htmlspecialchars($employee['employee_id'] ?? ''); ?>"
                                                                data-job_title="<?php echo htmlspecialchars($employee['job_title'] ?? ''); ?>"
                                                                data-department="<?php echo htmlspecialchars($employee['department']); ?>"
                                                                data-employment_type="<?php echo htmlspecialchars($employee['employment_type'] ?? ''); ?>"
                                                                data-reporting_manager="<?php echo htmlspecialchars($employee['reporting_manager'] ?? ''); ?>"
                                                                data-date_of_joining="<?php echo htmlspecialchars($employee['date_of_joining'] ?? ''); ?>"
                                                                data-work_location="<?php echo htmlspecialchars($employee['work_location'] ?? ''); ?>"
                                                                data-employee_status="<?php echo htmlspecialchars($employee['employee_status'] ?? ''); ?>"
                                                                data-employment_category="<?php echo htmlspecialchars($employee['employment_category'] ?? ''); ?>">
                                                            <i class="fas fa-eye"></i> View
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="8" style="text-align: center;">No employees found.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <div class="data-section">
                        <h3 class="form-title"><i class="fas fa-user-plus"></i> Add New Employee</h3>
                        <form method="post">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="new_name">Full Name *</label>
                                    <input type="text" id="new_name" name="name" class="form-control" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="new_email">Email *</label>
                                    <input type="email" id="new_email" name="email" class="form-control" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="new_phone">Phone</label>
                                    <input type="text" id="new_phone" name="phone" class="form-control">
                                </div>
                                
                                <div class="form-group">
                                    <label for="new_department">Department *</label>
                                    <input type="text" id="new_department" name="department" class="form-control" required value="<?php echo htmlspecialchars($manager_department); ?>" readonly>
                                </div>
                                
                                <div class="form-group">
                                    <label for="new_position">Position *</label>
                                    <input type="text" id="new_position" name="position" class="form-control" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="onboarding_notes">Onboarding Notes</label>
                                    <textarea id="onboarding_notes" name="onboarding_notes" class="form-control" rows="4" maxlength="5001"></textarea>
                                </div>
                            </div>
                            
                            <div style="margin-top: 20px; display: flex; justify-content: flex-end; gap: 10px;">
                                <button type="submit" name="add_employee" class="btn">Add Employee</button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Employee Details Section -->
                <?php if ($selected_employee): ?>
                    <div id="employee-details" class="content-section <?php echo $active_section == 'employee-details' ? 'active' : ''; ?>">
                        <h2 class="section-title"><i class="fas fa-user"></i> Employee Details</h2>
                        
                        <div class="employee-details-header">
                            <?php 
                            // Check if bmb_avatar exists and display it, otherwise show initials
                            if (!empty($selected_employee['bmb_avatar'])) {
                                echo '<img src="data:image/jpeg;base64,'.base64_encode($selected_employee['bmb_avatar']).'" alt="Avatar" class="employee-details-avatar">';
                            } else {
                                // Extract initials from name
                                $name_parts = explode(' ', trim($selected_employee['name']));
                                $initials = '';
                                foreach ($name_parts as $part) {
                                    if (!empty($part)) {
                                        $initials .= strtoupper(substr($part, 0, 1));
                                        if (strlen($initials) >= 2) break;
                                    }
                                }
                                if (empty($initials)) $initials = 'E';
                                echo '<div class="employee-details-initials">'.$initials.'</div>';
                            }
                            ?>
                            
                            <div class="employee-details-info">
                                <h3><?php echo htmlspecialchars($selected_employee['name']); ?></h3>
                                <p><?php echo htmlspecialchars($selected_employee['job_title'] ?? 'N/A'); ?> | <?php echo htmlspecialchars($selected_employee['department']); ?></p>
                                <p>ID: <?php echo $selected_employee['id']; ?></p>
                            </div>
                        </div>
                        
                        <div class="detail-grid">
                            <div class="detail-item">
                                <div class="detail-label">Employee ID</div>
                                <div class="detail-value"><?php echo $selected_employee['id']; ?></div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Full Name</div>
                                <div class="detail-value"><?php echo htmlspecialchars($selected_employee['name']); ?></div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Email</div>
                                <div class="detail-value"><?php echo htmlspecialchars($selected_employee['email']); ?></div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Phone</div>
                                <div class="detail-value"><?php echo htmlspecialchars($selected_employee['phone_primary'] ?? 'N/A'); ?></div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Department</div>
                                <div class="detail-value"><?php echo htmlspecialchars($selected_employee['department']); ?></div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Position</div>
                                <div class="detail-value"><?php echo htmlspecialchars($selected_employee['job_title'] ?? 'N/A'); ?></div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Is Manager</div>
                                <div class="detail-value"><?php echo $selected_employee['is_manager'] ? 'Yes' : 'No'; ?></div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Attendance Record Section -->
                    <div id="attendance" class="content-section <?php echo $active_section == 'attendance' ? 'active' : ''; ?>">
                        <h3 class="analytics-title"><i class="fas fa-user-clock"></i> Attendance Record</h3>
                        <div class="analytics-content">
                            <?php if (count($attendance_records) > 0): ?>
                                <div class="data-table-wrapper">
                                    <table class="data-table">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Check In</th>
                                                <th>Check Out</th>
                                                <th>Hours Worked</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($attendance_records as $record): ?>
                                                <tr>
                                                    <td><?php echo date('M j, Y', strtotime($record['date'])); ?></td>
                                                    <td><?php echo !empty($record['check_in']) ? date('h:i A', strtotime($record['check_in'])) : '-'; ?></td>
                                                    <td><?php echo !empty($record['check_out']) ? date('h:i A', strtotime($record['check_out'])) : '-'; ?></td>
                                                    <td>
                                                        <?php 
                                                        if (!empty($record['check_in']) && !empty($record['check_out'])) {
                                                            $check_in = new DateTime($record['check_in']);
                                                            $check_out = new DateTime($record['check_out']);
                                                            $interval = $check_in->diff($check_out);
                                                            $hours = $interval->h + ($interval->i / 60);
                                                            echo round($hours, 1);
                                                        } else {
                                                            echo '-';
                                                        }
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <span class="badge 
                                                            <?php 
                                                            if ($record['status'] == 'Present') echo 'badge-success';
                                                            elseif ($record['status'] == 'Absent') echo 'badge-danger';
                                                            elseif ($record['status'] == 'Late') echo 'badge-warning';
                                                            else echo 'badge-info';
                                                            ?>">
                                                            <?php echo $record['status']; ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="no-data">No attendance records found for this employee.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Tasks Section -->
                    <div id="tasks" class="content-section <?php echo $active_section == 'tasks' ? 'active' : ''; ?>">
                        <h3 class="analytics-title"><i class="fas fa-tasks"></i> Tasks</h3>
                        <div class="analytics-content">
                            <?php if (count($tasks) > 0): ?>
                                <div class="data-table-wrapper">
                                    <table class="data-table">
                                        <thead>
                                            <tr>
                                                <th>Title</th>
                                                <th>Description</th>
                                                <th>Status</th>
                                                <th>Priority</th>
                                                <th>Due Date</th>
                                                <th>Story Points</th>
                                                <th>Estimated Hours</th>
                                                <th>Actual Hours</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($tasks as $task): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($task['title']); ?></td>
                                                    <td><?php echo htmlspecialchars($task['description']); ?></td>
                                                    <td>
                                                        <span class="badge 
                                                            <?php 
                                                            if ($task['status'] == 'Completed') echo 'badge-success';
                                                            elseif ($task['status'] == 'In Progress') echo 'badge-info';
                                                            elseif ($task['status'] == 'To Do') echo 'badge-warning';
                                                            else echo 'badge-danger';
                                                            ?>">
                                                            <?php echo $task['status']; ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="badge 
                                                            <?php 
                                                            if ($task['priority'] == 'Critical') echo 'badge-danger';
                                                            elseif ($task['priority'] == 'High') echo 'badge-warning';
                                                            elseif ($task['priority'] == 'Medium') echo 'badge-info';
                                                            else echo 'badge-secondary';
                                                            ?>">
                                                            <?php echo $task['priority']; ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo date('M j, Y', strtotime($task['due_date'])); ?></td>
                                                    <td><?php echo $task['story_points']; ?></td>
                                                    <td><?php echo $task['estimated_hours']; ?></td>
                                                    <td><?php echo $task['actual_hours']; ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="no-data">No tasks found for this employee.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Performance Section -->
                    <div id="performance" class="content-section <?php echo $active_section == 'performance' ? 'active' : ''; ?>">
                        <h3 class="analytics-title"><i class="fas fa-chart-line"></i> Performance Reviews</h3>
                        <div class="analytics-content">
                            <?php if (count($performance_reviews) > 0): ?>
                                <?php foreach ($performance_reviews as $review): ?>
                                    <div class="analytics-item">
                                        <div class="analytics-label">Review Date: <?php echo date('M j, Y', strtotime($review['review_date'])); ?></div>
                                        <div class="analytics-value">
                                            <p><strong>Reviewer:</strong> <?php echo htmlspecialchars($review['reviewer_id']); ?></p>
                                            <p><strong>Rating:</strong> 
                                                <span class="badge 
                                                    <?php 
                                                    if ($review['performance_rating'] >= 8) echo 'badge-success';
                                                    elseif ($review['performance_rating'] >= 5) echo 'badge-warning';
                                                    else echo 'badge-danger';
                                                    ?>">
                                                    <?php echo $review['performance_rating']; ?>/10
                                                </span>
                                            </p>
                                            <p><strong>Goals Achievement:</strong> <?php echo htmlspecialchars($review['goals_achievement']); ?></p>
                                            <p><strong>Strengths:</strong> <?php echo htmlspecialchars($review['strengths']); ?></p>
                                            <p><strong>Areas for Improvement:</strong> <?php echo htmlspecialchars($review['areas_for_improvement']); ?></p>
                                            <p><strong>Recommendations:</strong> <?php echo htmlspecialchars($review['recommendations']); ?></p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="no-data">No performance reviews found for this employee.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Technical Requests Section - FIXED TO USE CORRECT FIELD NAMES -->
                    <div id="technical-requests" class="content-section <?php echo $active_section == 'technical-requests' ? 'active' : ''; ?>">
                        <h3 class="analytics-title"><i class="fas fa-tools"></i> Technical Requests</h3>
                        <div class="analytics-content">
                            <?php if (count($technical_requests) > 0): ?>
                                <div class="data-table-wrapper">
                                    <table class="data-table">
                                        <thead>
                                            <tr>
                                                <th>Created Date</th>
                                                <th>Subject</th>
                                                <th>Issue Type</th>
                                                <th>Sub Issue Type</th>
                                                <th>Status</th>
                                                <th>Priority</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($technical_requests as $request): ?>
                                                <tr>
                                                    <td><?php echo date('M j, Y', strtotime($request['created_at'])); ?></td>
                                                    <td><?php echo htmlspecialchars($request['subject']); ?></td>
                                                    <td><?php echo htmlspecialchars($request['issue_type']); ?></td>
                                                    <td><?php echo htmlspecialchars($request['sub_issue_type']); ?></td>
                                                    <td>
                                                        <span class="badge 
                                                            <?php 
                                                            if ($request['status'] == 'Resolved') echo 'badge-success';
                                                            elseif ($request['status'] == 'In Progress') echo 'badge-info';
                                                            elseif ($request['status'] == 'Pending') echo 'badge-warning';
                                                            else echo 'badge-danger';
                                                            ?>">
                                                            <?php echo $request['status']; ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="badge 
                                                            <?php 
                                                            if ($request['priority'] == 'Urgent') echo 'badge-danger';
                                                            elseif ($request['priority'] == 'High') echo 'badge-danger';
                                                            elseif ($request['priority'] == 'Medium') echo 'badge-warning';
                                                            else echo 'badge-info';
                                                            ?>">
                                                            <?php echo $request['priority']; ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="no-data">No technical requests found for this employee.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Trainings Section - FIXED TO USE CORRECT FIELD NAMES -->
                    <div id="trainings" class="content-section <?php echo $active_section == 'trainings' ? 'active' : ''; ?>">
                        <h3 class="analytics-title"><i class="fas fa-graduation-cap"></i> Training Records</h3>
                        <div class="analytics-content">
                            <?php if (count($training_records) > 0): ?>
                                <div class="data-table-wrapper">
                                    <table class="data-table">
                                        <thead>
                                            <tr>
                                                <th>Training Name</th>
                                                <th>Training Type</th>
                                                <th>Start Date</th>
                                                <th>End Date</th>
                                                <th>Status</th>
                                                <th>Result</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($training_records as $training): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($training['training_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($training['training_type']); ?></td>
                                                    <td><?php echo date('M j, Y', strtotime($training['start_date'])); ?></td>
                                                    <td><?php echo date('M j, Y', strtotime($training['end_date'])); ?></td>
                                                    <td>
                                                        <span class="badge 
                                                            <?php 
                                                            if ($training['status'] == 'Completed') echo 'badge-success';
                                                            elseif ($training['status'] == 'In Progress') echo 'badge-info';
                                                            elseif ($training['status'] == 'Planned') echo 'badge-warning';
                                                            else echo 'badge-danger';
                                                            ?>">
                                                            <?php echo $training['status']; ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="badge 
                                                            <?php 
                                                            if (isset($training['result'])) {
                                                                if ($training['result'] == 'Passed') echo 'badge-success';
                                                                elseif ($training['result'] == 'Failed') echo 'badge-danger';
                                                                else echo 'badge-info';
                                                            }
                                                            ?>">
                                                            <?php echo isset($training['result']) ? $training['result'] : 'N/A'; ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="no-data">No training records found for this employee.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Comparison Section -->
                <div id="comparison" class="content-section <?php echo $active_section == 'comparison' ? 'active' : ''; ?>">
                    <h2 class="section-title"><i class="fas fa-balance-scale"></i> Employee Comparison</h2>
                    
                    <div class="data-section">
                        <div class="comparison-controls">
                            <div class="comparison-selector">
                                <label for="employee1-select">Employee 1:</label>
                                <select id="employee1-select" class="form-control">
                                    <?php foreach ($employees as $employee): ?>
                                        <option value="<?php echo $employee['id']; ?>" <?php if (isset($comparison_data['employee1']) && $comparison_data['employee1']['id'] == $employee['id']) echo 'selected'; ?>>
                                            <?php echo htmlspecialchars($employee['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="comparison-selector">
                                <label for="employee2-select">Employee 2:</label>
                                <select id="employee2-select" class="form-control">
                                    <?php foreach ($employees as $employee): ?>
                                        <option value="<?php echo $employee['id']; ?>" <?php if (isset($comparison_data['employee2']) && $comparison_data['employee2']['id'] == $employee['id']) echo 'selected'; ?>>
                                            <?php echo htmlspecialchars($employee['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="comparison-button">
                                <button type="button" class="btn" onclick="updateComparison()">
                                    <i class="fas fa-sync-alt"></i> Update Comparison
                                </button>
                            </div>
                        </div>
                        
                        <?php if (isset($comparison_data['employee1']) && isset($comparison_data['employee2'])): ?>
                            <div class="comparison-table-wrapper">
                                <table class="comparison-table">
                                    <thead>
                                        <tr>
                                            <th>Metric</th>
                                            <th><?php echo htmlspecialchars($comparison_data['employee1']['name']); ?></th>
                                            <th><?php echo htmlspecialchars($comparison_data['employee2']['name']); ?></th>
                                            <th>Difference</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>Total Hours Worked</td>
                                            <td><?php echo $comparison_data['employee1']['total_hours_worked']; ?></td>
                                            <td><?php echo $comparison_data['employee2']['total_hours_worked']; ?></td>
                                            <td>
                                                <?php 
                                                $diff = $comparison_data['employee1']['total_hours_worked'] - $comparison_data['employee2']['total_hours_worked'];
                                                echo ($diff >= 0 ? '+' : '') . $diff;
                                                ?>
                                                <?php
                                                if ($diff > 0) {
                                                    echo '<span class="metric-badge metric-badge-higher">Employee 1</span>';
                                                } elseif ($diff < 0) {
                                                    echo '<span class="metric-badge metric-badge-lower">Employee 2</span>';
                                                } else {
                                                    echo '<span class="metric-badge metric-badge-equal">Equal</span>';
                                                }
                                                ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>Total Days Worked</td>
                                            <td><?php echo $comparison_data['employee1']['total_days_worked']; ?></td>
                                            <td><?php echo $comparison_data['employee2']['total_days_worked']; ?></td>
                                            <td>
                                                <?php 
                                                $diff = $comparison_data['employee1']['total_days_worked'] - $comparison_data['employee2']['total_days_worked'];
                                                echo ($diff >= 0 ? '+' : '') . $diff;
                                                ?>
                                                <?php
                                                if ($diff > 0) {
                                                    echo '<span class="metric-badge metric-badge-higher">Employee 1</span>';
                                                } elseif ($diff < 0) {
                                                    echo '<span class="metric-badge metric-badge-lower">Employee 2</span>';
                                                } else {
                                                    echo '<span class="metric-badge metric-badge-equal">Equal</span>';
                                                }
                                                ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>Tasks Assigned</td>
                                            <td><?php echo $comparison_data['employee1']['total_tasks_assigned']; ?></td>
                                            <td><?php echo $comparison_data['employee2']['total_tasks_assigned']; ?></td>
                                            <td>
                                                <?php 
                                                $diff = $comparison_data['employee1']['total_tasks_assigned'] - $comparison_data['employee2']['total_tasks_assigned'];
                                                echo ($diff >= 0 ? '+' : '') . $diff;
                                                ?>
                                                <?php
                                                if ($diff > 0) {
                                                    echo '<span class="metric-badge metric-badge-higher">Employee 1</span>';
                                                } elseif ($diff < 0) {
                                                    echo '<span class="metric-badge metric-badge-lower">Employee 2</span>';
                                                } else {
                                                    echo '<span class="metric-badge metric-badge-equal">Equal</span>';
                                                }
                                                ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>Tasks Completed</td>
                                            <td><?php echo $comparison_data['employee1']['total_tasks_completed']; ?></td>
                                            <td><?php echo $comparison_data['employee2']['total_tasks_completed']; ?></td>
                                            <td>
                                                <?php 
                                                $diff = $comparison_data['employee1']['total_tasks_completed'] - $comparison_data['employee2']['total_tasks_completed'];
                                                echo ($diff >= 0 ? '+' : '') . $diff;
                                                ?>
                                                <?php
                                                if ($diff > 0) {
                                                    echo '<span class="metric-badge metric-badge-higher">Employee 1</span>';
                                                } elseif ($diff < 0) {
                                                    echo '<span class="metric-badge metric-badge-lower">Employee 2</span>';
                                                } else {
                                                    echo '<span class="metric-badge metric-badge-equal">Equal</span>';
                                                }
                                                ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>Task Completion Rate</td>
                                            <td>
                                                <?php 
                                                $rate1 = $comparison_data['employee1']['total_tasks_assigned'] > 0 
                                                    ? round(($comparison_data['employee1']['total_tasks_completed'] / $comparison_data['employee1']['total_tasks_assigned']) * 100, 1) 
                                                    : 0;
                                                echo $rate1 . '%';
                                                ?>
                                            </td>
                                            <td>
                                                <?php 
                                                $rate2 = $comparison_data['employee2']['total_tasks_assigned'] > 0 
                                                    ? round(($comparison_data['employee2']['total_tasks_completed'] / $comparison_data['employee2']['total_tasks_assigned']) * 100, 1) 
                                                    : 0;
                                                echo $rate2 . '%';
                                                ?>
                                            </td>
                                            <td>
                                                <?php 
                                                $diff = $rate1 - $rate2;
                                                echo ($diff >= 0 ? '+' : '') . $diff . '%';
                                                ?>
                                                <?php
                                                if ($diff > 0) {
                                                    echo '<span class="metric-badge metric-badge-higher">Employee 1</span>';
                                                } elseif ($diff < 0) {
                                                    echo '<span class="metric-badge metric-badge-lower">Employee 2</span>';
                                                } else {
                                                    echo '<span class="metric-badge metric-badge-equal">Equal</span>';
                                                }
                                                ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>Leave Requests</td>
                                            <td><?php echo $comparison_data['employee1']['total_leave_requests']; ?></td>
                                            <td><?php echo $comparison_data['employee2']['total_leave_requests']; ?></td>
                                            <td>
                                                <?php 
                                                $diff = $comparison_data['employee1']['total_leave_requests'] - $comparison_data['employee2']['total_leave_requests'];
                                                echo ($diff >= 0 ? '+' : '') . $diff;
                                                ?>
                                                <?php
                                                if ($diff > 0) {
                                                    echo '<span class="metric-badge metric-badge-lower">Employee 2</span>';
                                                } elseif ($diff < 0) {
                                                    echo '<span class="metric-badge metric-badge-higher">Employee 1</span>';
                                                } else {
                                                    echo '<span class="metric-badge metric-badge-equal">Equal</span>';
                                                }
                                                ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>Trainings Completed</td>
                                            <td><?php echo $comparison_data['employee1']['total_trainings_completed']; ?></td>
                                            <td><?php echo $comparison_data['employee2']['total_trainings_completed']; ?></td>
                                            <td>
                                                <?php 
                                                $diff = $comparison_data['employee1']['total_trainings_completed'] - $comparison_data['employee2']['total_trainings_completed'];
                                                echo ($diff >= 0 ? '+' : '') . $diff;
                                                ?>
                                                <?php
                                                if ($diff > 0) {
                                                    echo '<span class="metric-badge metric-badge-higher">Employee 1</span>';
                                                } elseif ($diff < 0) {
                                                    echo '<span class="metric-badge metric-badge-lower">Employee 2</span>';
                                                } else {
                                                    echo '<span class="metric-badge metric-badge-equal">Equal</span>';
                                                }
                                                ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>Average Performance Rating</td>
                                            <td><?php echo $comparison_data['employee1']['avg_performance_rating']; ?>/10</td>
                                            <td><?php echo $comparison_data['employee2']['avg_performance_rating']; ?>/10</td>
                                            <td>
                                                <?php 
                                                $diff = $comparison_data['employee1']['avg_performance_rating'] - $comparison_data['employee2']['avg_performance_rating'];
                                                echo ($diff >= 0 ? '+' : '') . $diff;
                                                ?>
                                                <?php
                                                if ($diff > 0) {
                                                    echo '<span class="metric-badge metric-badge-higher">Employee 1</span>';
                                                } elseif ($diff < 0) {
                                                    echo '<span class="metric-badge metric-badge-lower">Employee 2</span>';
                                                } else {
                                                    echo '<span class="metric-badge metric-badge-equal">Equal</span>';
                                                }
                                                ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>Technical Requests</td>
                                            <td><?php echo $comparison_data['employee1']['total_technical_requests']; ?></td>
                                            <td><?php echo $comparison_data['employee2']['total_technical_requests']; ?></td>
                                            <td>
                                                <?php 
                                                $diff = $comparison_data['employee1']['total_technical_requests'] - $comparison_data['employee2']['total_technical_requests'];
                                                echo ($diff >= 0 ? '+' : '') . $diff;
                                                ?>
                                                <?php
                                                if ($diff > 0) {
                                                    echo '<span class="metric-badge metric-badge-lower">Employee 2</span>';
                                                } elseif ($diff < 0) {
                                                    echo '<span class="metric-badge metric-badge-higher">Employee 1</span>';
                                                } else {
                                                    echo '<span class="metric-badge metric-badge-equal">Equal</span>';
                                                }
                                                ?>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="comparison-chart-container">
                                <canvas id="comparisonChart"></canvas>
                            </div>
                        <?php else: ?>
                            <div class="no-data">Please select two employees to compare.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Edit Employee Modal -->
    <div id="editEmployeeModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Edit Employee</h3>
                <span class="close">&times;</span>
            </div>
            <div class="modal-body">
                <form method="post">
                    <input type="hidden" id="edit_employee_id" name="employee_id">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="edit_name">Full Name *</label>
                            <input type="text" id="edit_name" name="name" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="edit_email">Email *</label>
                            <input type="email" id="edit_email" name="email" class="form-control" required readonly>
                        </div>
                        
                        <div class="form-group">
                            <label for="edit_phone">Phone</label>
                            <input type="text" id="edit_phone" name="phone" class="form-control">
                        </div>
                        
                        <div class="form-group">
                            <label for="edit_department">Department *</label>
                            <input type="text" id="edit_department" name="department" class="form-control" required list="departments" value="<?php echo htmlspecialchars($manager_department); ?>" readonly>
                            <datalist id="departments">
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo htmlspecialchars($dept); ?>">
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                        
                        <div class="form-group">
                            <label for="edit_position">Position *</label>
                            <input type="text" id="edit_position" name="position" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="edit_job_title">Job Title</label>
                            <input type="text" id="edit_job_title" name="job_title" class="form-control" readonly>
                        </div>
                    </div>
                    
                    <div style="margin-top: 20px; display: flex; justify-content: flex-end; gap: 10px;">
                        <button type="button" class="btn btn-secondary" id="cancelEdit">Cancel</button>
                        <button type="submit" name="update_employee" class="btn">Update Employee</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- View Employee Modal -->
    <div id="viewEmployeeModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Employee Details</h3>
                <span class="close">&times;</span>
            </div>
            <div class="modal-body">
                <div class="profile-photo-container">
                    <img id="viewProfilePhoto" src="data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIxNTAiIGhlaWdodD0iMTUwIiB2aWV3Qm94PSIwIDAgMjQgMjQiIGZpbGw9IiM0YjAwODIiPjxwYXRoIGQ9Ik0xMiAxMmM0LjQxOCAwIDgtMy41ODIgOC04cy0zLjU4Mi04LTggOC0zLjU4MiA4LTggMy41ODIgOCA4IDh6Ii8+PHBhdGggZD0iTTEyIDJDNi40OCAyIDIgNi40OCAyIDEyczQuNDggMTAgMTAgMTAgMTAtNC40OCAxMC0xMFMxNy41MiAyIDEyIDJ6bTAgMThjLTQuNDEgMC04LTMuNTktOCA4czMuNTktOCA4LTggOCAzLjU5IDggOC04IDMuNTktOCA4eiIvPjwvc3ZnPg==" alt="Profile Photo" class="profile-photo" style="display: block;">
                    <div id="viewProfileInitials" class="profile-initials" style="display: none;"></div>
                </div>
                
                <div class="view-employee-section">
                    <h4 class="view-employee-section-title"><i class="fas fa-user"></i> Personal Information</h4>
                    <div class="view-employee-details">
                        <div class="view-employee-item">
                            <div class="view-employee-label">First Name</div>
                            <div class="view-employee-value" id="viewFirstName">-</div>
                        </div>
                        <div class="view-employee-item">
                            <div class="view-employee-label">Middle Name</div>
                            <div class="view-employee-value" id="viewMiddleName">-</div>
                        </div>
                        <div class="view-employee-item">
                            <div class="view-employee-label">Last Name</div>
                            <div class="view-employee-value" id="viewLastName">-</div>
                        </div>
                        <div class="view-employee-item">
                            <div class="view-employee-label">Date of Birth</div>
                            <div class="view-employee-value" id="viewDateOfBirth">-</div>
                        </div>
                        <div class="view-employee-item">
                            <div class="view-employee-label">Gender</div>
                            <div class="view-employee-value" id="viewGender">-</div>
                        </div>
                        <div class="view-employee-item">
                            <div class="view-employee-label">Nationality</div>
                            <div class="view-employee-value" id="viewNationality">-</div>
                        </div>
                        <div class="view-employee-item">
                            <div class="view-employee-label">Marital Status</div>
                            <div class="view-employee-value" id="viewMaritalStatus">-</div>
                        </div>
                        <div class="view-employee-item">
                            <div class="view-employee-label">Government ID Type</div>
                            <div class="view-employee-value" id="viewGovernmentIdType">-</div>
                        </div>
                        <div class="view-employee-item">
                            <div class="view-employee-label">Government ID Number</div>
                            <div class="view-employee-value" id="viewGovernmentIdNumber">-</div>
                        </div>
                    </div>
                </div>
                
                <div class="view-employee-section">
                    <h4 class="view-employee-section-title"><i class="fas fa-address-book"></i> Contact Information</h4>
                    <div class="view-employee-details">
                        <div class="view-employee-item">
                            <div class="view-employee-label">Primary Phone</div>
                            <div class="view-employee-value" id="viewPhonePrimary">-</div>
                        </div>
                        <div class="view-employee-item">
                            <div class="view-employee-label">Alternate Phone</div>
                            <div class="view-employee-value" id="viewPhoneAlternate">-</div>
                        </div>
                        <div class="view-employee-item">
                            <div class="view-employee-label">Email Address</div>
                            <div class="view-employee-value" id="viewEmail">-</div>
                        </div>
                        <div class="view-employee-item">
                            <div class="view-employee-label">Current Address</div>
                            <div class="view-employee-value" id="viewCurrentAddress">-</div>
                        </div>
                        <div class="view-employee-item">
                            <div class="view-employee-label">Permanent Address</div>
                            <div class="view-employee-value" id="viewPermanentAddress">-</div>
                        </div>
                        <div class="view-employee-item">
                            <div class="view-employee-label">Emergency Contact Name</div>
                            <div class="view-employee-value" id="viewEmergencyContactName">-</div>
                        </div>
                        <div class="view-employee-item">
                            <div class="view-employee-label">Relationship</div>
                            <div class="view-employee-value" id="viewEmergencyContactRelationship">-</div>
                        </div>
                        <div class="view-employee-item">
                            <div class="view-employee-label">Emergency Contact Phone</div>
                            <div class="view-employee-value" id="viewEmergencyContactPhone">-</div>
                        </div>
                    </div>
                </div>
                
                <div class="view-employee-section">
                    <h4 class="view-employee-section-title"><i class="fas fa-briefcase"></i> Employment Details</h4>
                    <div class="view-employee-details">
                        <div class="view-employee-item">
                            <div class="view-employee-label">Employee ID</div>
                            <div class="view-employee-value" id="viewEmployeeId">-</div>
                        </div>
                        <div class="view-employee-item">
                            <div class="view-employee-label">Job Title</div>
                            <div class="view-employee-value" id="viewJobTitle">-</div>
                        </div>
                        <div class="view-employee-item">
                            <div class="view-employee-label">Department</div>
                            <div class="view-employee-value" id="viewDepartment">-</div>
                        </div>
                        <div class="view-employee-item">
                            <div class="view-employee-label">Employment Type</div>
                            <div class="view-employee-value" id="viewEmploymentType">-</div>
                        </div>
                        <div class="view-employee-item">
                            <div class="view-employee-label">Reporting Manager</div>
                            <div class="view-employee-value" id="viewReportingManager">-</div>
                        </div>
                        <div class="view-employee-item">
                            <div class="view-employee-label">Date of Joining</div>
                            <div class="view-employee-value" id="viewDateOfJoining">-</div>
                        </div>
                        <div class="view-employee-item">
                            <div class="view-employee-label">Work Location</div>
                            <div class="view-employee-value" id="viewWorkLocation">-</div>
                        </div>
                        <div class="view-employee-item">
                            <div class="view-employee-label">Employee Status</div>
                            <div class="view-employee-value" id="viewEmployeeStatus">-</div>
                        </div>
                        <div class="view-employee-item">
                            <div class="view-employee-label">Employment Category</div>
                            <div class="view-employee-value" id="viewEmploymentCategory">-</div>
                        </div>
                    </div>
                </div>
                
                <div style="margin-top: 20px; display: flex; justify-content: flex-end; gap: 10px;">
                    <button type="button" class="btn btn-secondary" id="cancelView">Close</button>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Get the modals
        const editModal = document.getElementById("editEmployeeModal");
        const viewModal = document.getElementById("viewEmployeeModal");
        
        // Get the buttons that open the modals
        const editBtns = document.querySelectorAll(".edit-employee");
        const viewBtns = document.querySelectorAll(".view-employee");
        
        // Get the <span> elements that close the modals
        const closeBtns = document.getElementsByClassName("close");
        const cancelEditBtn = document.getElementById("cancelEdit");
        const cancelViewBtn = document.getElementById("cancelView");
        
        // When the user clicks the edit button, open the edit modal 
        editBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                // Populate form with employee data
                document.getElementById('edit_employee_id').value = this.getAttribute('data-id');
                document.getElementById('edit_name').value = this.getAttribute('data-name');
                document.getElementById('edit_email').value = this.getAttribute('data-email');
                document.getElementById('edit_phone').value = this.getAttribute('data-phone');
                document.getElementById('edit_department').value = this.getAttribute('data-department');
                document.getElementById('edit_position').value = this.getAttribute('data-position');
                document.getElementById('edit_job_title').value = this.getAttribute('data-job_title');
                
                editModal.style.display = "block";
            });
        });
        
        // When the user clicks the view button, open the view modal 
        viewBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                // Capture the necessary data from the button attributes
                const firstName = this.getAttribute('data-first_name');
                const lastName = this.getAttribute('data-last_name');
                const fullName = this.getAttribute('data-name');
                const bmbAvatar = this.getAttribute('data-bmb_avatar');
                
                // Populate view modal with employee data
                document.getElementById('viewFirstName').textContent = firstName || '-';
                document.getElementById('viewMiddleName').textContent = this.getAttribute('data-middle_name') || '-';
                document.getElementById('viewLastName').textContent = lastName || '-';
                
                // Format date of birth if available
                const dateOfBirth = this.getAttribute('data-date_of_birth');
                if (dateOfBirth) {
                    const dob = new Date(dateOfBirth);
                    document.getElementById('viewDateOfBirth').textContent = dob.toLocaleDateString('en-GB');
                } else {
                    document.getElementById('viewDateOfBirth').textContent = '-';
                }
                
                document.getElementById('viewGender').textContent = this.getAttribute('data-gender') || '-';
                document.getElementById('viewNationality').textContent = this.getAttribute('data-nationality') || '-';
                document.getElementById('viewMaritalStatus').textContent = this.getAttribute('data-marital_status') || '-';
                document.getElementById('viewGovernmentIdType').textContent = this.getAttribute('data-government_id_type') || '-';
                document.getElementById('viewGovernmentIdNumber').textContent = this.getAttribute('data-government_id_number') || '-';
                
                // Set profile photo or initials - using the same logic as the Name column
                const profilePhoto = document.getElementById('viewProfilePhoto');
                const profileInitials = document.getElementById('viewProfileInitials');
                
                // Reset both elements first
                profilePhoto.style.display = 'none';
                profileInitials.style.display = 'none';
                
                // Check if bmb_avatar exists and is not empty (same logic as Name column)
                if (bmbAvatar && bmbAvatar.trim() !== '') {
                    // Validate base64 string
                    const base64Regex = /^data:image\/([a-zA-Z+]+);base64,([A-Za-z0-9+/={4}]+)$/;
                    if (base64Regex.test(bmbAvatar)) {
                        profilePhoto.src = bmbAvatar;
                        profilePhoto.style.display = 'block';
                        profilePhoto.onerror = function() {
                            // If the image fails to load, fall back to initials
                            profilePhoto.style.display = 'none';
                            showInitials(firstName, lastName, fullName);
                        };
                    } else {
                        // Invalid base64 format, show initials
                        showInitials(firstName, lastName, fullName);
                    }
                } else {
                    // No bmb_avatar available, show initials
                    showInitials(firstName, lastName, fullName);
                }
                
                // Helper function to show initials (same logic as Name column)
                function showInitials(firstName, lastName, fullName) {
                    let initials = '';
                    
                    if (firstName && firstName.trim() !== '') initials += firstName.charAt(0).toUpperCase();
                    if (lastName && lastName.trim() !== '') initials += lastName.charAt(0).toUpperCase();
                    
                    if (!initials && fullName && fullName.trim() !== '') {
                        const nameParts = fullName.split(' ').filter(part => part.trim() !== '');
                        if (nameParts.length > 0) initials += nameParts[0].charAt(0).toUpperCase();
                        if (nameParts.length > 1) initials += nameParts[1].charAt(0).toUpperCase();
                    }
                    
                    if (!initials) initials = 'E'; // Default if no name is available
                    
                    profileInitials.textContent = initials;
                    profileInitials.style.display = 'flex';
                }
                
                // Contact Information
                document.getElementById('viewPhonePrimary').textContent = this.getAttribute('data-phone_primary') || '-';
                document.getElementById('viewPhoneAlternate').textContent = this.getAttribute('data-phone_alternate') || '-';
                document.getElementById('viewEmail').textContent = this.getAttribute('data-email') || '-';
                document.getElementById('viewCurrentAddress').textContent = this.getAttribute('data-current_address') || '-';
                document.getElementById('viewPermanentAddress').textContent = this.getAttribute('data-permanent_address') || '-';
                document.getElementById('viewEmergencyContactName').textContent = this.getAttribute('data-emergency_contact_name') || '-';
                document.getElementById('viewEmergencyContactRelationship').textContent = this.getAttribute('data-emergency_contact_relationship') || '-';
                document.getElementById('viewEmergencyContactPhone').textContent = this.getAttribute('data-emergency_contact_phone') || '-';
                
                // Employment Details
                document.getElementById('viewEmployeeId').textContent = this.getAttribute('data-employee_id') || '-';
                document.getElementById('viewJobTitle').textContent = this.getAttribute('data-job_title') || '-';
                document.getElementById('viewDepartment').textContent = this.getAttribute('data-department') || '-';
                document.getElementById('viewEmploymentType').textContent = this.getAttribute('data-employment_type') || '-';
                document.getElementById('viewReportingManager').textContent = this.getAttribute('data-reporting_manager') || '-';
                
                // Format date of joining if available
                const dateOfJoining = this.getAttribute('data-date_of_joining');
                if (dateOfJoining) {
                    const doj = new Date(dateOfJoining);
                    document.getElementById('viewDateOfJoining').textContent = doj.toLocaleDateString('en-GB');
                } else {
                    document.getElementById('viewDateOfJoining').textContent = '-';
                }
                
                document.getElementById('viewWorkLocation').textContent = this.getAttribute('data-work_location') || '-';
                document.getElementById('viewEmployeeStatus').textContent = this.getAttribute('data-employee_status') || '-';
                document.getElementById('viewEmploymentCategory').textContent = this.getAttribute('data-employment_category') || '-';
                
                viewModal.style.display = "block";
            });
        });
        
        // When the user clicks on <span> (x), close the modals
        for (let i = 0; i < closeBtns.length; i++) {
            closeBtns[i].onclick = function() {
                editModal.style.display = "none";
                viewModal.style.display = "none";
            }
        }
        
        // When the user clicks on cancel buttons, close the modals
        cancelEditBtn.onclick = function() {
            editModal.style.display = "none";
        }
        
        cancelViewBtn.onclick = function() {
            viewModal.style.display = "none";
        }
        
        // When the user clicks anywhere outside of the modals, close them
        window.onclick = function(event) {
            if (event.target == editModal) {
                editModal.style.display = "none";
            }
            if (event.target == viewModal) {
                viewModal.style.display = "none";
            }
        }
        
        // Initialize Chart for Dashboard
        window.addEventListener('DOMContentLoaded', function() {
            const dashboardSection = document.getElementById('dashboard');
            if (dashboardSection && dashboardSection.classList.contains('active')) {
                createDepartmentChart();
            }
            
            const comparisonSection = document.getElementById('comparison');
            if (comparisonSection && comparisonSection.classList.contains('active')) {
                createComparisonChart();
            }
        });
        
        function createDepartmentChart() {
            const ctx = document.getElementById('departmentChart').getContext('2d');
            
            // Get actual values for the chart
            const totalEmployees = <?php echo count($employees); ?>;
            const managerCount = <?php 
                $manager_count = 0;
                foreach ($employees as $emp) {
                    if ($emp['is_manager'] == 1) $manager_count++;
                }
                echo $manager_count;
            ?>;
            const staffCount = totalEmployees - managerCount;
            
            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Managers', 'Staff'],
                    datasets: [{
                        data: [managerCount, staffCount],
                        backgroundColor: [
                            '#4b0082',
                            '#8a2be2'
                        ],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                font: {
                                    size: 14
                                }
                            }
                        },
                        title: {
                            display: true,
                            text: '<?php echo htmlspecialchars($manager_department); ?> Department Composition',
                            font: {
                                size: 16
                            }
                        }
                    }
                }
            });
        }
        
        function createComparisonChart() {
            const ctx = document.getElementById('comparisonChart').getContext('2d');
            
            // Get employee names
            const employee1Name = '<?php echo isset($comparison_data['employee1']) ? htmlspecialchars($comparison_data['employee1']['name']) : ''; ?>';
            const employee2Name = '<?php echo isset($comparison_data['employee2']) ? htmlspecialchars($comparison_data['employee2']['name']) : ''; ?>';
            
            // If we don't have both employees, don't create the chart
            if (!employee1Name || !employee2Name) return;
            
            new Chart(ctx, {
                type: 'radar',
                data: {
                    labels: [
                        'Hours Worked',
                        'Days Worked',
                        'Tasks Assigned',
                        'Tasks Completed',
                        'Leave Requests',
                        'Trainings Completed',
                        'Performance Rating',
                        'Technical Requests'
                    ],
                    datasets: [
                        {
                            label: employee1Name,
                            data: [
                                <?php echo isset($comparison_data['employee1']) ? $comparison_data['employee1']['total_hours_worked'] : 0; ?>,
                                <?php echo isset($comparison_data['employee1']) ? $comparison_data['employee1']['total_days_worked'] : 0; ?>,
                                <?php echo isset($comparison_data['employee1']) ? $comparison_data['employee1']['total_tasks_assigned'] : 0; ?>,
                                <?php echo isset($comparison_data['employee1']) ? $comparison_data['employee1']['total_tasks_completed'] : 0; ?>,
                                <?php echo isset($comparison_data['employee1']) ? $comparison_data['employee1']['total_leave_requests'] : 0; ?>,
                                <?php echo isset($comparison_data['employee1']) ? $comparison_data['employee1']['total_trainings_completed'] : 0; ?>,
                                <?php echo isset($comparison_data['employee1']) ? $comparison_data['employee1']['avg_performance_rating'] : 0; ?>,
                                <?php echo isset($comparison_data['employee1']) ? $comparison_data['employee1']['total_technical_requests'] : 0; ?>
                            ],
                            backgroundColor: 'rgba(75, 0, 130, 0.2)',
                            borderColor: 'rgba(75, 0, 130, 0.8)',
                            pointBackgroundColor: 'rgba(75, 0, 130, 1)',
                            pointBorderColor: '#fff',
                            pointHoverBackgroundColor: '#fff',
                            pointHoverBorderColor: 'rgba(75, 0, 130, 1)'
                        },
                        {
                            label: employee2Name,
                            data: [
                                <?php echo isset($comparison_data['employee2']) ? $comparison_data['employee2']['total_hours_worked'] : 0; ?>,
                                <?php echo isset($comparison_data['employee2']) ? $comparison_data['employee2']['total_days_worked'] : 0; ?>,
                                <?php echo isset($comparison_data['employee2']) ? $comparison_data['employee2']['total_tasks_assigned'] : 0; ?>,
                                <?php echo isset($comparison_data['employee2']) ? $comparison_data['employee2']['total_tasks_completed'] : 0; ?>,
                                <?php echo isset($comparison_data['employee2']) ? $comparison_data['employee2']['total_leave_requests'] : 0; ?>,
                                <?php echo isset($comparison_data['employee2']) ? $comparison_data['employee2']['total_trainings_completed'] : 0; ?>,
                                <?php echo isset($comparison_data['employee2']) ? $comparison_data['employee2']['avg_performance_rating'] : 0; ?>,
                                <?php echo isset($comparison_data['employee2']) ? $comparison_data['employee2']['total_technical_requests'] : 0; ?>
                            ],
                            backgroundColor: 'rgba(138, 43, 226, 0.2)',
                            borderColor: 'rgba(138, 43, 226, 0.8)',
                            pointBackgroundColor: 'rgba(138, 43, 226, 1)',
                            pointBorderColor: '#fff',
                            pointHoverBackgroundColor: '#fff',
                            pointHoverBorderColor: 'rgba(138, 43, 226, 1)'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        r: {
                            angleLines: {
                                display: true
                            },
                            suggestedMin: 0
                        }
                    }
                }
            });
        }
        
        // Function to load employee data when dropdown selection changes
        function loadEmployeeData(employeeId) {
            if (employeeId) {
                // Get current active section
                const activeSection = '<?php echo $active_section; ?>';
                
                // Navigate to the same section with the selected employee ID
                window.location.href = '?section=' + activeSection + '&employee_id=' + employeeId;
            } else {
                // If no employee is selected, navigate to the current section without employee ID
                const activeSection = '<?php echo $active_section; ?>';
                window.location.href = '?section=' + activeSection;
            }
        }
        
        // Function to update comparison when button is clicked
        function updateComparison() {
            const employee1Id = document.getElementById('employee1-select').value;
            const employee2Id = document.getElementById('employee2-select').value;
            
            if (employee1Id && employee2Id && employee1Id !== employee2Id) {
                window.location.href = '?section=comparison&employee1=' + employee1Id + '&employee2=' + employee2Id;
            }
        }
    </script>
</body>
</html>