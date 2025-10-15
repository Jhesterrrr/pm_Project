<?php
require_once 'db_connection.php';

// Start session to get current user info
session_start();

// Ensure Archived columns exist (soft-delete approach)
$mysqli->query("ALTER TABLE accounts ADD COLUMN IF NOT EXISTS Archived TINYINT(1) NOT NULL DEFAULT 0");
$mysqli->query("ALTER TABLE accounts ADD COLUMN IF NOT EXISTS Archived_At DATETIME NULL");

// --- Get current user's position (for button/column visibility) ---
$current_user_position = null;
$current_user_id = null;
if (isset($_SESSION['Employee_ID'])) {
    $emp_id = $_SESSION['Employee_ID'];
    $current_user_id = $emp_id;
    $pos_stmt = $mysqli->prepare("SELECT Position FROM accounts WHERE Employee_ID = ?");
    if ($pos_stmt) {
        $pos_stmt->bind_param("i", $emp_id);
        $pos_stmt->execute();
        $pos_result = $pos_stmt->get_result();
        if ($pos_result && $pos_row = $pos_result->fetch_assoc()) {
            $current_user_position = $pos_row['Position'];
        }
        $pos_stmt->close();
    }
}


// Handle archive action (soft delete) - Only if not Junior or Staff
if ($current_user_position !== "Junior" && $current_user_position !== "Staff" && isset($_POST['delete_id'])) {
    $delete_id = $_POST['delete_id'];

    // Prevent archiving own currently logged-in account
    if (isset($_SESSION['Employee_ID']) && $_SESSION['Employee_ID'] == $delete_id) {
        $error_message = "You cannot archive your own account while logged in.";
    } else {
        $mysqli->autocommit(false);
        try {
            // Confirm account exists
            $select_stmt = $mysqli->prepare("SELECT Employee_ID FROM accounts WHERE Employee_ID = ?");
            if (!$select_stmt) {
                throw new Exception("Prepare failed: " . $mysqli->error);
            }
            $select_stmt->bind_param("i", $delete_id);
            $select_stmt->execute();
            $account_data = $select_stmt->get_result()->fetch_assoc();
            $select_stmt->close();

            if (!$account_data) {
                throw new Exception("Account not found.");
            }

            // Mark as archived
            $archive_stmt = $mysqli->prepare("UPDATE accounts SET Archived = 1, Archived_At = NOW() WHERE Employee_ID = ?");
            if (!$archive_stmt) {
                throw new Exception("Prepare failed: " . $mysqli->error);
            }
            $archive_stmt->bind_param("i", $delete_id);
            if (!$archive_stmt->execute()) {
                throw new Exception("Failed to archive account: " . $mysqli->error);
            }
            $archive_stmt->close();

            $mysqli->commit();
            // Write audit log for archive action after successful archive
            if (isset($_SESSION['Employee_ID']) && isset($_SESSION['Position']) && isset($_SESSION['Department'])) {
                $employee_id = $_SESSION['Employee_ID'];
                $department  = $_SESSION['Department'];
                $position    = $_SESSION['Position'];
                $Account_Owner = isset($_SESSION['Account_Owner']) ? $_SESSION['Account_Owner'] : ("Emp#" . $employee_id);
                $details = "User {$Account_Owner}  Emeployee ID: {$employee_id} Archived account ID: {$delete_id}";
                $log = $mysqli->prepare("INSERT INTO audit_log (Employee_ID, Department, Position, Module, Action, Details) 
                                         VALUES (?, ?, ?, 'Employee Accounts', 'Archived', ?)");
                if ($log) {
                    $log->bind_param("isss", $employee_id, $department, $position, $details);
                    $log->execute();
                    $log->close();
                }
            }
            $success_message = "Account archived successfully.";
        } catch (Exception $e) {
            $mysqli->rollback();
            $error_message = $e->getMessage();
        }
        $mysqli->autocommit(true);
    }
}

// Handle filter parameters
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
$department_filter = isset($_GET['department']) ? $_GET['department'] : '';
$position_filter = isset($_GET['position']) ? $_GET['position'] : '';
$date_filter = isset($_GET['date']) ? $_GET['date'] : '';

// Build the query with filters
// If user is Head, select password as well
if ($current_user_position === "Head") {
    $query = "SELECT Employee_ID, Account_Owner, Username, Password, Department, Position, Created_At FROM accounts WHERE (Archived IS NULL OR Archived = 0)";
} else {
    $query = "SELECT Employee_ID, Account_Owner, Username, Department, Position, Created_At FROM accounts WHERE (Archived IS NULL OR Archived = 0)";
}
$params = [];
$types = '';

if (!empty($search_term)) {
    $query .= " AND (Employee_ID LIKE ? OR Account_Owner LIKE ? OR Username LIKE ?)";
    $search_param = "%$search_term%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'sss';
}

if (!empty($department_filter) && $department_filter !== 'Choose') {
    $query .= " AND Department = ?";
    $params[] = $department_filter;
    $types .= 's';
}

if (!empty($position_filter) && $position_filter !== 'Choose') {
    $query .= " AND Position = ?";
    $params[] = $position_filter;
    $types .= 's';
}

if (!empty($date_filter)) {
    $query .= " AND DATE(Created_At) = ?";
    $params[] = $date_filter;
    $types .= 's';
}

$query .= " ORDER BY Employee_ID";

$stmt = $mysqli->prepare($query);
if ($stmt && !empty($params)) {
    $stmt->bind_param($types, ...$params);
} elseif ($stmt) {
    // No parameters to bind
}

if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result === false) {
        die('Error getting result: ' . $stmt->error);
    }
} else {
    die('Error preparing query: ' . $mysqli->error);
}

// Get unique departments and positions for filter dropdowns
$dept_query = "SELECT DISTINCT Department FROM accounts WHERE Department IS NOT NULL ORDER BY Department";
$dept_result = $mysqli->query($dept_query);
if (!$dept_result) {
    die('Error fetching departments: ' . $mysqli->error);
}

$pos_query = "SELECT DISTINCT Position FROM accounts WHERE Position IS NOT NULL ORDER BY Position";
$pos_result = $mysqli->query($pos_query);
if (!$pos_result) {
    die('Error fetching positions: ' . $mysqli->error);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Accounts</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #2c3e50;
            text-align: center;
            margin-bottom: 30px;
        }
        .filter-bar {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #dee2e6;
        }
        .filter-row {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
            flex-wrap: wrap;
            align-items: end;
        }
        .filter-group {
            flex: 1;
            min-width: 200px;
        }
        .filter-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #2c3e50;
        }
        .filter-group input,
        .filter-group select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        .filter-buttons {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }
        .filter-btn {
            padding: 10px 20px;
            background-color: #2c3e50;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }
        .filter-btn:hover {
            background-color: #34495e;
        }
        .clear-btn {
            background-color: #6c757d;
        }
        .clear-btn:hover {
            background-color: #5a6268;
        }
        .archive-btn {
            background-color: #17a2b8;
            color: white;
            text-decoration: none;
            padding: 10px 20px;
            border-radius: 5px;
            margin-bottom: 20px;
            display: inline-block;
        }
        .archive-btn:hover {
            background-color: #138496;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #34495e;
            color: white;
            font-weight: bold;
        }
        tr:hover {
            background-color: #f5f5f5;
        }
        .back-btn {
            display: inline-block;
            padding: 10px 20px;
            background-color: #2c3e50;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .back-btn:hover {
            background-color: #34495e;
        }
        .no-data {
            text-align: center;
            color: #7f8c8d;
            font-style: italic;
            padding: 40px;
        }
        .results-count {
            margin-bottom: 15px;
            color: #2c3e50;
            font-weight: bold;
        }
        .delete-btn {
            background-color: #dc3545;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
        }
        .delete-btn:hover {
            background-color: #c82333;
        }
        .update-btn {
            background-color: #007bff;
            color: white;
            text-decoration: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            display: inline-block;
            margin-right: 8px;
            border: none;
        }
        .update-btn:hover {
            background-color: #0069d9;
        }
        .logs-btn {
            background-color: #6c757d;
            color: white;
            text-decoration: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            display: inline-block;
            margin-left: 8px;
        }
        .logs-btn:hover {
            background-color: #5a6268;
        }
        .message {
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        /* Disabled button style for Junior/Staff */
        .disabled-btn, .disabled-btn:hover {
            background-color: #cccccc !important;
            color: #888888 !important;
            cursor: not-allowed !important;
            pointer-events: none !important;
            text-decoration: none !important;
        }
        /* Tooltip for Senior logs button */
        .tooltip {
            position: relative;
            display: inline-block;
        }
        .tooltip .tooltiptext {
            visibility: hidden;
            width: 260px;
            background-color: #333;
            color: #fff;
            text-align: center;
            border-radius: 6px;
            padding: 8px 10px;
            position: absolute;
            z-index: 100;
            bottom: 125%;
            left: 50%;
            margin-left: -130px;
            opacity: 0;
            transition: opacity 0.3s;
            font-size: 13px;
            pointer-events: none;
        }
        .tooltip:hover .tooltiptext,
        .tooltip:focus-within .tooltiptext {
            visibility: visible;
            opacity: 1;
            pointer-events: auto;
        }
    </style>
    <script>
    function confirmDelete(name) {
        return confirm('Are you sure you want to archive the account for ' + name + '?');
    }

    // Removed: server-side audit insert on page load (moved to server after archive)
  

    // For Senior: Show alert on click of See Logs
    function seniorLogsAlert(e) {
        alert("Only the Head Administrator has an access to this panel");
        if (e) e.preventDefault();
        return false;
    }
    </script>
</head>
<body>
    <div class="container">
        <a href="User_Accounts.php" class="back-btn">← Back to User Accounts</a>
        <a href="account_archive.php" class="archive-btn">📁 View Archived Accounts</a>
        
        <h1>All User Accounts</h1>
        
        <?php if (isset($success_message)): ?>
            <div class="message success"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="message error"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <?php if ($current_user_position === "Junior"): ?>
            <div class="message error">
                Your position is too low to interact with account actions. Only filtering and viewing are allowed.
            </div>
        <?php elseif ($current_user_position === "Staff"): ?>
            <div class="message error">
                As a Staff member, you are not allowed to update, archive, or view logs for accounts.
            </div>
        <?php endif; ?>
        
        <!-- Filter Bar -->
        <div class="filter-bar">
            <form method="GET" action="">
                <div class="filter-row">
                    <div class="filter-group">
                        <label for="search">Search (Employee ID or Name):</label>
                        <input type="text" id="search" name="search" 
                               value="<?php echo htmlspecialchars($search_term); ?>" 
                               placeholder="Enter Employee ID, Name, or Username">
                    </div>
                    
                    <div class="filter-group">
                        <label for="department">Department:</label>
                        <select id="department" name="department">
                            <option value="">Choose</option>
                            <?php while ($dept_row = $dept_result->fetch_assoc()): ?>
                                <option value="<?php echo htmlspecialchars($dept_row['Department']); ?>"
                                        <?php echo $department_filter === $dept_row['Department'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($dept_row['Department']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="position">Position:</label>
                        <select id="position" name="position">
                            <option value="">Choose</option>
                            <?php while ($pos_row = $pos_result->fetch_assoc()): ?>
                                <option value="<?php echo htmlspecialchars($pos_row['Position']); ?>"
                                        <?php echo $position_filter === $pos_row['Position'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($pos_row['Position']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="date">Created Date:</label>
                        <input type="date" id="date" name="date" 
                               value="<?php echo htmlspecialchars($date_filter); ?>">
                    </div>
                </div>
                
                <div class="filter-buttons">
                    <button type="submit" class="filter-btn">Apply Filter</button>
                    <a href="acc_Table.php" class="filter-btn clear-btn" style="text-decoration: none; display: inline-block;">Clear All</a>
                </div>
            </form>
        </div>
        
        <?php 
        $row_count = $result->num_rows;
        if ($row_count > 0): 
        ?>
            <div class="results-count">
                Showing <?php echo $row_count; ?> result(s)
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th>Employee ID</th>
                        <th>Account Owner</th>
                        <th>Username</th>
                        <?php if ($current_user_position === "Head"): ?>
                            <th>Password</th>
                        <?php endif; ?>
                        <th>Department</th>
                        <th>Position</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['Employee_ID']); ?></td>
                            <td><?php echo htmlspecialchars($row['Account_Owner']); ?></td>
                            <td><?php echo htmlspecialchars($row['Username']); ?></td>
                            <?php if ($current_user_position === "Head"): ?>
                                <td>
                                    <?php 
                                    // Show password only if user is Head and password column exists in result
                                    if (isset($row['Password'])) {
                                        echo htmlspecialchars($row['Password']);
                                    } else {
                                        echo '-';
                                    }
                                    ?>
                                </td>
                            <?php endif; ?>
                            <td><?php echo htmlspecialchars($row['Department']); ?></td>
                            <td><?php echo htmlspecialchars($row['Position']); ?></td>
                            <td>
                                <?php
                                // Determine if "See Logs" should be disabled for Senior
                                $disable_logs = false;
                                if ($current_user_position === "Senior") {
                                    $disable_logs = true;
                                }
                                // Removed: Prevent current user from seeing logs for their own account
                                // $is_own_account = ($current_user_id !== null && $current_user_id == $row['Employee_ID']);
                                

                                ?>
                                <?php if ($current_user_position === "Junior" || $current_user_position === "Staff"): ?>
                                    <a href="#" class="update-btn disabled-btn" tabindex="-1" aria-disabled="true">Update</a>
                                    <button type="button" class="delete-btn disabled-btn" disabled>Archive</button>
                                    <a href="#" class="logs-btn disabled-btn" tabindex="-1" aria-disabled="true" style="background-color: #cccccc; color: #888888;">See Logs</a>
                                <?php else: ?>
                                    <a href="User_Accounts.php?employee_id=<?php echo urlencode($row['Employee_ID']); ?>" class="update-btn">Update</a>
                                    <form method="POST" action="" style="display: inline;" onsubmit="return confirmDelete('<?php echo htmlspecialchars($row['Account_Owner'])?>')">
                                        <input type="hidden" name="delete_id" value="<?php echo $row['Employee_ID']; ?>">
                                        <button type="submit" class="delete-btn">Archive</button>           
                                    </form>
                                    <?php if ($current_user_position === "Senior"): ?>
                                        <span class="tooltip">
                                            <a href="#" class="logs-btn disabled-btn" tabindex="-1" aria-disabled="true"
                                               style="background-color: #cccccc; color: #888888;"
                                               onclick="return seniorLogsAlert(event);"
                                               onkeydown="if(event.key==='Enter'||event.key===' '){seniorLogsAlert(event);}"
                                               >See Logs</a>
                                            <span class="tooltiptext">Only the Head Administrator has an access to this panel</span>
                                        </span>
                                    <?php elseif ($disable_logs): ?>
                                        <a href="#" class="logs-btn disabled-btn" tabindex="-1" aria-disabled="true" style="background-color: #cccccc; color: #888888;">See Logs</a>
                                    <?php else: ?>
                                        <a href="Employee_audits.php?employee_id=<?php echo urlencode($row['Employee_ID']); ?>" class="logs-btn" style="background-color: green; color: white; padding: 5px 10px; border-radius: 4px; text-decoration: none;">See Logs</a>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="no-data">
                No accounts found in the database.
            </div>
        <?php endif; ?>
    </div>
</body>
</html>



<?php
// Close database connection
$mysqli->close();
?>
