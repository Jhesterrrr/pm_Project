<?php
session_start();
require_once __DIR__ . '/db_connection.php';

$employeeId = isset($_GET['employee_id']) ? (int)$_GET['employee_id'] : 0;
if ($employeeId <= 0) {
    http_response_code(400);
    echo 'Invalid or missing employee_id.';
    exit;
}

// Fetch account owner for header
$owner = '';
$ownStmt = $mysqli->prepare("SELECT Account_Owner FROM accounts WHERE Employee_ID = ? LIMIT 1");
if ($ownStmt) {
    $ownStmt->bind_param('i', $employeeId);
    $ownStmt->execute();
    if (method_exists($ownStmt, 'get_result')) {
        $res = $ownStmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $owner = $row ? ($row['Account_Owner'] ?? '') : '';
    } else {
        $ownStmt->bind_result($owner);
        $ownStmt->fetch();
    }
    $ownStmt->close();
}

// Fetch audit logs for this employee
$logs = [];
$stmt = $mysqli->prepare("SELECT Log_ID, Employee_ID, Department, Position, Module, Action, Details, Timestamp FROM audit_log WHERE Employee_ID = ? ORDER BY Timestamp DESC, Log_ID DESC");
if ($stmt) {
    $stmt->bind_param('i', $employeeId);
    $stmt->execute();
    if (method_exists($stmt, 'get_result')) {
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) { $logs[] = $r; }
    } else {
        $stmt->bind_result($logId,$e,$d,$p,$m,$a,$det,$ts);
        while ($stmt->fetch()) {
            $logs[] = [
                'Log_ID'      => $logId,
                'Employee_ID' => $e,
                'Department'  => $d,
                'Position'    => $p,
                'Module'      => $m,
                'Action'      => $a,
                'Details'     => $det,
                'Timestamp'   => $ts,
            ];
        }
    }
    $stmt->close();
}


//AUDIT LOGSSS PAPASOK NYA DITO
if (isset($_SESSION['Employee_ID']) && isset($_SESSION['Position']) && isset($_SESSION['Department'])) {
    $employee_id = $_SESSION['Employee_ID'];
    $department  = $_SESSION['Department'];
    $position    = $_SESSION['Position'];
    $Account_Owner = $_SESSION['Account_Owner'];
  
    $details = "User {$Account_Owner}  Emeployee ID: {$employee_id} Viewed Employee ID:{$employeeId} , Audit logs";
  
    $log = $mysqli->prepare("INSERT INTO audit_log (Employee_ID, Department, Position, Module, Action, Details) 
                             VALUES (?, ?, ?, 'Employee Accounts', 'Viewed', ?)");
    if ($log) {
        $log->bind_param("isss", $employee_id, $department, $position, $details);
        $log->execute();
        $log->close();
    }
  }


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Audits - ID <?php echo htmlspecialchars($employeeId); ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1100px; margin: 0 auto; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #2c3e50; margin-bottom: 8px; }
        .sub { color: #6c757d; margin-bottom: 20px; }
        .back-btn { display:inline-block; padding:10px 16px; background:#2c3e50; color:#fff; text-decoration:none; border-radius:5px; margin-bottom:16px; }
        .back-btn:hover { background:#34495e; }
        table { width:100%; border-collapse: collapse; margin-top: 8px; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #34495e; color: #fff; }
        tr:hover { background: #f9f9f9; }
        .no-data { text-align:center; color:#7f8c8d; padding: 30px; }
    </style>
    </head>
<body>
    <div class="container">
        <a href="acc_Table.php" class="back-btn">← Back to Accounts</a>
        <h1>Audit Logs for Employee #<?php echo htmlspecialchars($employeeId); ?></h1>
        <div class="sub">Account Owner: <strong><?php echo htmlspecialchars($owner ?: 'Unknown'); ?></strong></div>

        <?php if (empty($logs)): ?>
            <div class="no-data">No audit logs found for this employee.</div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Employee ID</th>
                        <th>Account Owner</th>
                        <th>Module</th>
                        <th>Details</th>
                        <th>Timestamp</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($log['Employee_ID']); ?></td>
                            <td><?php echo htmlspecialchars($owner ?: 'Unknown'); ?></td>
                            <td><?php echo htmlspecialchars($log['Module']); ?></td>
                            <td><?php echo htmlspecialchars($log['Details']); ?></td>
                            <td><?php echo htmlspecialchars($log['Timestamp']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</body>
</html>
 