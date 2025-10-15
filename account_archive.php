<?php
require_once 'db_connection.php';
session_start();



// Ensure columns exist
$mysqli->query("ALTER TABLE accounts ADD COLUMN IF NOT EXISTS Archived TINYINT(1) NOT NULL DEFAULT 0");
$mysqli->query("ALTER TABLE accounts ADD COLUMN IF NOT EXISTS Archived_At DATETIME NULL");

// --- Get current user's position ---
$current_user_position = null;
if (isset($_SESSION['Position'])) {
    $current_user_position = $_SESSION['Position'];
} elseif (isset($_SESSION['Employee_ID'])) {
    // fallback: fetch from DB if not in session
    $emp_id = $_SESSION['Employee_ID'];
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

// Handle restore
if (isset($_POST['restore_id'])) {
    $restore_id = $_POST['restore_id'];

    // Prevent restoring own account logic not necessary, but we keep consistency
    $mysqli->autocommit(false);
    try {
        $stmt = $mysqli->prepare("UPDATE accounts SET Archived = 0, Archived_At = NULL WHERE Employee_ID = ?");
        $stmt->bind_param("i", $restore_id);
        if (!$stmt->execute()) {
            throw new Exception('Failed to restore account: ' . $mysqli->error);
        }
        $stmt->close();

        // Audit log for restore action
        if (isset($_SESSION['Employee_ID']) && isset($_SESSION['Department']) && isset($_SESSION['Position']) && isset($_SESSION['Account_Owner'])) {
            $employee_id   = $_SESSION['Employee_ID'];
            $department    = $_SESSION['Department'];
            $position      = $_SESSION['Position'];
            $Account_Owner = $_SESSION['Account_Owner'];

            $action  = 'Restored';
            $details = "User {$Account_Owner} Employee ID: {$employee_id} restored account ID: {$restore_id}";

            $log = $mysqli->prepare("INSERT INTO audit_log (Employee_ID, Department, Position, Module, Action, Details) VALUES (?, ?, ?, 'Employee Accounts', ?, ?)");
            if ($log) {
                $log->bind_param("issss", $employee_id, $department, $position, $action, $details);
                $log->execute();
                $log->close();
            }
        }
        $mysqli->commit();
        $success_message = 'Account restored successfully.';
    } catch (Exception $e) {
        $mysqli->rollback();
        $error_message = $e->getMessage();
    }
    $mysqli->autocommit(true);
}

// Filters
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
$department_filter = isset($_GET['department']) ? $_GET['department'] : '';
$position_filter = isset($_GET['position']) ? $_GET['position'] : '';
$date_filter = isset($_GET['date']) ? $_GET['date'] : '';

$query = "SELECT Employee_ID, Account_Owner, Username, Department, Position, Created_At, Archived_At FROM accounts WHERE Archived = 1";
$params = [];
$types = '';

if (!empty($search_term)) {
    $query .= " AND (Employee_ID LIKE ? OR Account_Owner LIKE ? OR Username LIKE ?)";
    $search_param = "%$search_term%";
    $params[] = $search_param; $params[] = $search_param; $params[] = $search_param;
    $types .= 'sss';
}
if (!empty($department_filter) && $department_filter !== 'Choose') {
    $query .= " AND Department = ?";
    $params[] = $department_filter; $types .= 's';
}
if (!empty($position_filter) && $position_filter !== 'Choose') {
    $query .= " AND Position = ?";
    $params[] = $position_filter; $types .= 's';
}
if (!empty($date_filter)) {
    $query .= " AND DATE(Archived_At) = ?";
    $params[] = $date_filter; $types .= 's';
}

$query .= " ORDER BY Employee_ID";

$stmt = $mysqli->prepare($query);
if ($stmt && !empty($params)) {
    $stmt->bind_param($types, ...$params);
}
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    die('Error preparing query: ' . $mysqli->error);
}

// Dropdown data
$dept_query = "SELECT DISTINCT Department FROM accounts WHERE Department IS NOT NULL ORDER BY Department";
$dept_result = $mysqli->query($dept_query);
$pos_query = "SELECT DISTINCT Position FROM accounts WHERE Position IS NOT NULL ORDER BY Position";
$pos_result = $mysqli->query($pos_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Archived Accounts</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background-color: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #2c3e50; text-align: center; margin-bottom: 30px; }
        .filter-bar { background-color: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #dee2e6; }
        .filter-row { display: flex; gap: 15px; margin-bottom: 15px; flex-wrap: wrap; align-items: end; }
        .filter-group { flex: 1; min-width: 200px; }
        .filter-group label { display: block; margin-bottom: 5px; font-weight: bold; color: #2c3e50; }
        .filter-group input, .filter-group select { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; }
        .filter-buttons { display: flex; gap: 10px; margin-top: 10px; }
        .filter-btn { padding: 10px 20px; background-color: #2c3e50; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; }
        .filter-btn:hover { background-color: #34495e; }
        .clear-btn { background-color: #6c757d; }
        .clear-btn:hover { background-color: #5a6268; }
        .back-btn { display: inline-block; padding: 10px 20px; background-color: #2c3e50; color: white; text-decoration: none; border-radius: 5px; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #34495e; color: white; font-weight: bold; }
        tr:hover { background-color: #f5f5f5; }
        .restore-btn { background-color: #28a745; color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; font-size: 12px; }
        .restore-btn:hover { background-color: #218838; }
        .restore-btn.disabled-btn, .restore-btn:disabled { background-color: #cccccc; color: #888888; cursor: not-allowed; }
        .message { padding: 10px; margin-bottom: 20px; border-radius: 4px; }
        .success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .info { background-color: #e2e3e5; color: #383d41; border: 1px solid #d6d8db; }
    </style>
    </head>
<body>
    <div class="container">
        <a href="acc_Table.php" class="back-btn">← Back to User Accounts</a>
        <h1>Archived Accounts</h1>

        <?php if (isset($success_message)): ?>
            <div class="message success"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>
        <?php if (isset($error_message)): ?>
            <div class="message error"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <?php
        // Show info message for Junior or Staff
        if ($current_user_position === "Junior") {
            echo '<div class="message info">Your position is too low to interact with account actions. Only filtering and viewing are allowed.</div>';
        } elseif ($current_user_position === "Staff") {
            echo '<div class="message info">You don\'t have enough permission to restore account</div>';
        }
        ?>

        <div class="filter-bar">
            <form method="GET" action="">
                <div class="filter-row">
                    <div class="filter-group">
                        <label for="search">Search (Employee ID or Name):</label>
                        <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search_term); ?>" placeholder="Enter Employee ID, Name, or Username">
                    </div>
                    <div class="filter-group">
                        <label for="department">Department:</label>
                        <select id="department" name="department">
                            <option value="">Choose</option>
                            <?php while ($dept_row = $dept_result->fetch_assoc()): ?>
                                <option value="<?php echo htmlspecialchars($dept_row['Department']); ?>" <?php echo $department_filter === $dept_row['Department'] ? 'selected' : ''; ?>>
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
                                <option value="<?php echo htmlspecialchars($pos_row['Position']); ?>" <?php echo $position_filter === $pos_row['Position'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($pos_row['Position']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="date">Archived Date:</label>
                        <input type="date" id="date" name="date" value="<?php echo htmlspecialchars($date_filter); ?>">
                    </div>
                </div>
                <div class="filter-buttons">
                    <button type="submit" class="filter-btn">Apply Filter</button>
                    <a href="account_archive.php" class="filter-btn clear-btn" style="text-decoration: none; display: inline-block;">Clear All</a>
                </div>
            </form>
        </div>

        <?php $row_count = $result->num_rows; if ($row_count > 0): ?>
            <div class="results-count">Showing <?php echo $row_count; ?> result(s)</div>
            <table>
                <thead>
                    <tr>
                        <th>Employee ID</th>
                        <th>Account Owner</th>
                        <th>Username</th>
                        <th>Department</th>
                        <th>Position</th>
                        <th>Archived At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['Employee_ID']); ?></td>
                            <td><?php echo htmlspecialchars($row['Account_Owner']); ?></td>
                            <td><?php echo htmlspecialchars($row['Username']); ?></td>
                            <td><?php echo htmlspecialchars($row['Department']); ?></td>
                            <td><?php echo htmlspecialchars($row['Position']); ?></td>
                            <td><?php echo htmlspecialchars($row['Archived_At']); ?></td>
                            <td>
                                <?php
                                // Disable restore for Junior and Staff
                                $disable_restore = ($current_user_position === "Junior" || $current_user_position === "Staff");
                                $restore_tooltip = "";
                                if ($current_user_position === "Junior") {
                                    $restore_tooltip = "Your position is too low to interact with account actions. Only filtering and viewing are allowed.";
                                } elseif ($current_user_position === "Staff") {
                                    $restore_tooltip = "You don't have enough permission to restore account";
                                }
                                ?>
                                <form method="POST" action="" style="display:inline;" onsubmit="return <?php echo $disable_restore ? 'false' : "confirm('Restore this account?')"; ?>;">
                                    <input type="hidden" name="restore_id" value="<?php echo $row['Employee_ID']; ?>">
                                    <button type="submit"
                                            class="restore-btn<?php echo $disable_restore ? ' disabled-btn' : ''; ?>"
                                            <?php echo $disable_restore ? 'disabled aria-disabled="true" tabindex="-1"' : ''; ?>
                                            <?php if ($restore_tooltip) echo 'title="' . htmlspecialchars($restore_tooltip) . '"'; ?>>
                                        Restore
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="no-data">No archived accounts found.</div>
        <?php endif; ?>
    </div>
</body>
</html>
<?php $mysqli->close(); ?>
