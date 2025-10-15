<?php
session_start();
include 'db_connection.php';

if (isset($_SESSION['Employee_ID']) && isset($_SESSION['Position']) && isset($_SESSION['Department'])) {
    $employee_id = $_SESSION['Employee_ID'];
    $department  = $_SESSION['Department'];
    $position    = $_SESSION['Position'];
    $Account_Owner = $_SESSION['Account_Owner'];


    $details = "User {$Account_Owner}  Emeployee ID: {$employee_id} has logged out.";

    $log = $mysqli->prepare("INSERT INTO audit_log (Employee_ID, Department, Position, Module, Action, Details) 
                             VALUES (?, ?, ?, 'Dashboard', 'Logout', ?)");
    if ($log) {
        $log->bind_param("isss", $employee_id, $department, $position, $details);
        $log->execute();
        $log->close();
    }
}


// Destroy session
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params['path'], $params['domain'],
        $params['secure'], $params['httponly']
    );
}
session_destroy();

header("Location: Admin_Login.php");
exit();
?>
