<!DOCTYPE html>
<?php require_once __DIR__ . '/db_connection.php'; ?>
<?php
session_start();

$loginError = '';
$loginSuccess = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';

    if ($username === '' || $password === '') {
        $loginError = 'Username and Password are required.';
    } else {
        // ✅ Select user only by username; ensure not archived
        $stmt = $mysqli->prepare('SELECT Account_Owner,Employee_ID, Username, Password, Department, Position, IFNULL(Archived,0) AS Archived, IFNULL(Status,\'Active\') AS Status FROM accounts WHERE Username = ? LIMIT 1');
        
        if ($stmt) {
            $stmt->bind_param('s', $username);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($row = $result->fetch_assoc()) {
                // ✅ Verify password securely
                if ($password === $row['Password']) { 
                    // Block archived and terminated accounts
                    if ((int)$row['Archived'] === 1) {
                        $loginError = 'This account is archived and cannot log in.';
                    } else if (isset($row['Status']) && $row['Status'] === 'Terminated') {
                        $loginError = 'This account is terminated and cannot log in.';
                    } else {

                    // Save user info in session
                    $_SESSION['Employee_ID'] = $row['Employee_ID'];
                    $_SESSION['Username'] = $row['Username'];
                    $_SESSION['Department'] = $row['Department'];
                    $_SESSION['Position'] = $row['Position'];
                    $_SESSION['Account_Owner'] = $row['Account_Owner'];

                    // ✅ Log the login action
                    $details = "User {$row['Account_Owner']} Employee ID: {$row['Employee_ID']} has logged in.";

                    
                    $log = $mysqli->prepare("INSERT INTO audit_log (Employee_ID, Department, Position, Module, Action, Details) 
                                             VALUES (?, ?, ?, 'Login', 'Login', ?)");
                    if ($log) {
                        $log->bind_param("isss", $row['Employee_ID'], $row['Department'], $row['Position'], $details);
                        $log->execute();
                        $log->close();
                    }

                    
                    // Department-based redirect
                    $dept = $row['Department'];
                    $DIR = 'Test_Users/';
                    $redirectMap = [
                        'Human Resources' => $DIR . 'Human_Resources.html',
                        'Logistics' => $DIR . 'Logistics.html',
                        'Administrator' => 'Admin_Dash.php',
                        'Finance' => $DIR . 'Finance.html',
                        'Core' => $DIR . 'Core.html',
                    ];
                    $target = isset($redirectMap[$dept]) ? $redirectMap[$dept] : 'Admin_Dash.php';

                    header('Location: ' . $target);
                    exit;
                    }
                } else {
                    $loginError = 'Invalid username or password.'; 
                }
            } else {
                $loginError = 'Invalid username or password.'; 
            }

            $stmt->close();
        } else {
            $loginError = 'Failed to prepare the login query.';
        }
    }
}

?>
<head>
    <title>Admin Login</title>
    <link rel="stylesheet" href="CSS_files/AdminLogin.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
</head>


<body>
    <form method="POST" action="Admin_Login.php" autocomplete="off">
    <div class="LoginContainer">
        <label>HRMS SYSTEM LOGIN</label>

    <?php if ($loginError !== ''): ?>
    <div style="margin-bottom: 12px; color: #fff; background:#c0392b; padding:8px 12px; border-radius:8px; width:100%; box-sizing:border-box; text-align:left;">
        <?php echo htmlspecialchars($loginError, ENT_QUOTES, 'UTF-8'); ?>
    </div>
    <?php endif; ?>

    <div class="input-group">
        <span class="material-symbols-outlined">person</span>
        <input type="text" id="username" name="username" placeholder="Username" required>
    </div>
    
    <div class="input-group">
        <span class="material-symbols-outlined">key</span>
        <input type="password" id="password" name="password" placeholder="Password" required>
    </div>
        <button type="submit" class="loginbutton">Login</button>
    </div>

    </form>
   
    </body>
</html>