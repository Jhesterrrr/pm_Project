<?php
// fetchAcc.php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/db_connection.php';

$empId = 0;
if (isset($_POST['Employee_ID'])) {
  $empId = (int)$_POST['Employee_ID'];
} elseif (isset($_GET['Employee_ID'])) {
  $empId = (int)$_GET['Employee_ID'];
}

if ($empId <= 0) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'Invalid Employee_ID.']);
  exit;
}

$sql = "SELECT Employee_ID, Account_Owner, Username, Password, Department, Position
        FROM accounts
        WHERE Employee_ID = ?
        LIMIT 1";
$stmt = $mysqli->prepare($sql);
if (!$stmt) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'Prepare failed.']);
  exit;
}

$stmt->bind_param('i', $empId);
$stmt->execute();

// Try get_result (requires mysqlnd). If not available, fallback to bind_result.
$row = null;
if (method_exists($stmt, 'get_result')) {
  $res = $stmt->get_result();
  if ($res) {
    $row = $res->fetch_assoc();
  }
}

if ($row === null) {
  // Fallback
  $stmt->bind_result($r_id, $r_owner, $r_user, $r_pass, $r_dept, $r_pos);
  if ($stmt->fetch()) {
    $row = [
      'Employee_ID'   => $r_id,
      'Account_Owner' => $r_owner,
      'Username'      => $r_user,
      'Password'      => $r_pass,
      'Department'    => $r_dept,
      'Position'      => $r_pos,
    ];
  }
}


//AUDIT LOGSSS PAPASOK NYA DITO
if (isset($_SESSION['Employee_ID']) && isset($_SESSION['Position']) && isset($_SESSION['Department'])) {
  $employee_id = $_SESSION['Employee_ID'];
  $department  = $_SESSION['Department'];
  $position    = $_SESSION['Position'];
  $Account_Owner = $_SESSION['Account_Owner'];

  $details = "User {$Account_Owner}  Emeployee ID: {$employee_id} searched for Account ID:{$empId}";

  $log = $mysqli->prepare("INSERT INTO audit_log (Employee_ID, Department, Position, Module, Action, Details) 
                           VALUES (?, ?, ?, 'Employee Accounts', 'Fetch', ?)");
  if ($log) {
      $log->bind_param("isss", $employee_id, $department, $position, $details);
      $log->execute();
      $log->close();
  }
}

$stmt->close();

if (!$row) {
  http_response_code(404);
  echo json_encode(['ok' => false, 'error' => 'Account not found.']);
  exit;
}

echo json_encode(['ok' => true, 'data' => $row]);
