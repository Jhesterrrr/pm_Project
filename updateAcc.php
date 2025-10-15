<?php
// updateAcc.php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/db_connection.php';

// Utility to bind dynamic parameters by reference for mysqli
function bindParamsByRef($stmt, $types, $params) {
  $refs = [];
  $refs[] = &$types;
  foreach ($params as $k => $v) {
    $refs[] = &$params[$k];
  }
  return call_user_func_array([$stmt, 'bind_param'], $refs);
}

// Read JSON or form
$raw = file_get_contents('php://input');
$contentType = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
$empId = 0;
$changes = [];

if ($raw && stripos($contentType, 'application/json') !== false) {
  $payload = json_decode($raw, true);
  if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid JSON payload.']);
    exit;
  }
  $empId = (int)($payload['Employee_ID'] ?? 0);
  $changes = $payload['changes'] ?? [];
} else {
  $empId = (int)($_POST['Employee_ID'] ?? 0);
  $changes = isset($_POST['changes']) ? json_decode($_POST['changes'], true) : [];
}

if ($empId <= 0) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'Invalid Employee_ID.']);
  exit;
}
if (!is_array($changes) || empty($changes)) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'No changes provided.']);
  exit;
}

// Ensure record exists and fetch current values
$sel = $mysqli->prepare("SELECT Account_Owner, Username, Password, Department, Position, IFNULL(Status,'Active') AS Status FROM accounts WHERE Employee_ID = ? LIMIT 1");
if (!$sel) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'Prepare failed.']);
  exit;
}
$sel->bind_param('i', $empId);
$sel->execute();

$current = null;
if (method_exists($sel, 'get_result')) {
  $res = $sel->get_result();
  $current = $res ? $res->fetch_assoc() : null;
} else {
  $sel->bind_result($c_owner, $c_user, $c_pass, $c_dept, $c_pos, $c_status);
  if ($sel->fetch()) {
    $current = [
      'Account_Owner' => $c_owner,
      'Username'      => $c_user,
      'Password'      => $c_pass,
      'Department'    => $c_dept,
      'Position'      => $c_pos,
      'Status'        => $c_status,
    ];
  }
}

$sel->close();

if (!$current) {
  http_response_code(404);
  echo json_encode(['ok' => false, 'error' => 'Account not found.']);
  exit;
}

// Whitelist and remove fields that don't actually change server-side
$allowed = ['Account_Owner', 'Username', 'Password', 'Department', 'Position', 'Status'];
$filtered = [];
foreach ($changes as $field => $value) {
  if (!in_array($field, $allowed, true)) continue;
  $currVal = $current[$field] ?? null;
  if ((string)$currVal !== (string)$value) {
    $filtered[$field] = $value;
  }
}

if (empty($filtered)) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'No effective changes (identical to current values).']);
  exit;
}

// ❌ Block updates that set dropdowns to "Blocked"
if ((isset($filtered['Department']) && $filtered['Department'] === 'Blocked') ||
    (isset($filtered['Position'])   && $filtered['Position']   === 'Blocked')) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => '❌ Please choose a valid Department & Position.']);
  exit;
}


 $validDepartments = ['Administrator','Human Resources','Core','Finance','Logistics'];
 $validPositions   = ['Head','Senior','Staff','Junior'];
 if (isset($filtered['Department']) && !in_array($filtered['Department'], $validDepartments, true)) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'Invalid Department.']); exit;
 }
if (isset($filtered['Position']) && !in_array($filtered['Position'], $validPositions, true)) {
  http_response_code(400);
 echo json_encode(['ok' => false, 'error' => 'Invalid Position.']); exit;
}

// Build dynamic UPDATE
$fields = array_keys($filtered);
$placeholders = [];
foreach ($fields as $f) { $placeholders[] = "`$f` = ?"; }

$sql = "UPDATE accounts SET " . implode(', ', $placeholders) . " WHERE Employee_ID = ?";

$stmt = $mysqli->prepare($sql);
if (!$stmt) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'Prepare failed.']);
  exit;
}

$types = str_repeat('s', count($fields)) . 'i';
$params = array_values($filtered);
$params[] = $empId;

bindParamsByRef($stmt, $types, $params);
if (!$stmt->execute()) {
  $stmt->close();
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'Update failed.']);
  exit;
}
$stmt->close();

// AUDIT LOG: differentiate Promotion/Demotion when Position changes
if (isset($_SESSION['Employee_ID']) && isset($_SESSION['Position']) && isset($_SESSION['Department'])) {
  $actorEmployeeId = $_SESSION['Employee_ID'];
  $actorDepartment = $_SESSION['Department'];
  $actorPosition   = $_SESSION['Position'];
  $actorOwner      = $_SESSION['Account_Owner'];

  $action = 'Update';
  $details = "User {$actorOwner} Employee ID: {$actorEmployeeId} updated Account ID:{$empId}.";

  if (isset($filtered['Position'])) {
    $order = ['Junior','Staff','Senior','Head'];
    $oldPos = $current['Position'] ?? '';
    $newPos = $filtered['Position'];
    $oldIdx = array_search($oldPos, $order, true);
    $newIdx = array_search($newPos, $order, true);
    if ($oldIdx !== false && $newIdx !== false && $oldIdx !== $newIdx) {
      if ($newIdx > $oldIdx) {
        $action = 'Promoted';
        $details = "User {$actorOwner} Employee ID: {$actorEmployeeId} promoted account ID no. {$empId}.";
      } else {
        $action = 'Demoted';
        $details = "User {$actorOwner} Employee ID: {$actorEmployeeId} demoted account ID no. {$empId}.";
      }
    }
  }

  // Log status change on server side
  if (isset($filtered['Status'])) {
    $statusAction = 'Changed Status';
    $statusDetails = "User Emp ID {$actorEmployeeId}: Changed Status of Employee ID {$empId} to {$filtered['Status']}";
    $log = $mysqli->prepare("INSERT INTO audit_log (Employee_ID, Department, Position, Module, Action, Details)
                             VALUES (?, ?, ?, 'User Management', ?, ?)");
    if ($log) {
      $log->bind_param("issss", $actorEmployeeId, $actorDepartment, $actorPosition, $statusAction, $statusDetails);
      $log->execute();
      $log->close();
    }
  }

  $log = $mysqli->prepare("INSERT INTO audit_log (Employee_ID, Department, Position, Module, Action, Details)
                           VALUES (?, ?, ?, 'Employee Accounts', ?, ?)");
  if ($log) {
    $log->bind_param("issss", $actorEmployeeId, $actorDepartment, $actorPosition, $action, $details);
    $log->execute();
    $log->close();
  }
}

echo json_encode([
  'ok' => true,
  'updated_fields' => $fields,
]);