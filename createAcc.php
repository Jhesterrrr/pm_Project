<?php
session_start();
include 'db_connection.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $account_owner = $_POST["Account_Owner"];
    $username   = isset($_POST['Username']) ? trim($_POST['Username']) : '';
    $password   = isset($_POST['Password']) ? trim($_POST['Password']) : '';
    $Department = $_POST['Department'];
    $Position   = $_POST['Position'];  

    // ❌ Filter para pang block ng choices na option
if ($Department === "Blocked" || $Position === "Blocked") {
    echo "<script>alert('❌ Please choose a valid Department & Position.'); 
    window.location.href='User_Accounts.php';</script>";
exit;
}

    // Use a transaction to get DB-assigned Employee_ID then generate username/password
    $mysqli->autocommit(false);
    try {
        // Step 1: insert with temporary unique placeholders if username/password are empty
        $tempUsername = $username !== '' ? $username : ('tmp_' . bin2hex(random_bytes(4)));
        $tempPassword = $password !== '' ? $password : ('tmp_' . bin2hex(random_bytes(4)));

        $stmt = $mysqli->prepare("INSERT INTO accounts (Username, Password, Department, Position, Account_Owner) VALUES (?, ?, ?, ?, ?)");
        if (!$stmt) {
            throw new Exception('Failed to prepare INSERT.');
        }
        $stmt->bind_param("sssss", $tempUsername, $tempPassword, $Department, $Position, $account_owner);
        if (!$stmt->execute()) {
            throw new Exception('Insert failed: ' . $stmt->error);
        }
        $newEmployeeId = $mysqli->insert_id;
        $stmt->close();

        // Step 2: if username/password not provided, generate based on new Employee_ID
        $finalUsername = $tempUsername;
        $finalPassword = $tempPassword;

        if ($username === '') {
            // Pattern: HRMS + first letter of first name (lowercase) + Employee_ID
            $ownerTrim = trim($account_owner);
            $firstName = $ownerTrim === '' ? 'user' : preg_split('/\s+/', $ownerTrim)[0];
            $firstInitial = strtolower(substr($firstName, 0, 1));
            $finalUsername = 'HRMS' . $firstInitial . $newEmployeeId;
        }

        if ($password === '') {
            // Pattern: first letter of first name (lowercase) + Employee_ID + HRMSemployee
            $ownerTrim = trim($account_owner);
            $firstName = $ownerTrim === '' ? 'user' : preg_split('/\s+/', $ownerTrim)[0];
            $firstInitial = strtolower(substr($firstName, 0, 1));
            $finalPassword = $firstInitial . $newEmployeeId . 'HRMSemployee';
        }

        // Step 3: update record with final username/password if changed
        if ($finalUsername !== $tempUsername || $finalPassword !== $tempPassword) {
            $upd = $mysqli->prepare("UPDATE accounts SET Username = ?, Password = ? WHERE Employee_ID = ?");
            if (!$upd) {
                throw new Exception('Failed to prepare UPDATE.');
            }
            $upd->bind_param("ssi", $finalUsername, $finalPassword, $newEmployeeId);
            if (!$upd->execute()) {
                throw new Exception('Update failed: ' . $upd->error);
            }
            $upd->close();
        }

        // Commit
        $mysqli->commit();

        // AUDIT LOG before redirect
        if (isset($_SESSION['Employee_ID']) && isset($_SESSION['Position']) && isset($_SESSION['Department'])) {
            $employee_id = $_SESSION['Employee_ID'];
            $department  = $_SESSION['Department'];
            $position    = $_SESSION['Position'];
            $Account_Owner = $_SESSION['Account_Owner'];

            $details = "User {$Account_Owner} Employee ID: {$employee_id} created an Account, ID No:{$newEmployeeId}";

            $log = $mysqli->prepare("INSERT INTO audit_log (Employee_ID, Department, Position, Module, Action, Details) 
                                     VALUES (?, ?, ?, 'Employee Accounts', 'Created', ?)");
            if ($log) {
                $log->bind_param("isss", $employee_id, $department, $position, $details);
                $log->execute();
                $log->close();
            }
        }

        // Flash message + server-side redirect
        $_SESSION['flash_success'] = '✅ Account created successfully! Username: ' . $finalUsername . ' | Password: ' . $finalPassword;
        $mysqli->autocommit(true);
        header('Location: User_Accounts.php');
        exit;
  
 
 
 
     } catch (Exception $e) {
         $mysqli->rollback();
         $_SESSION['flash_error'] = '❌ Error: ' . $e->getMessage();
         $mysqli->autocommit(true);
         header('Location: User_Accounts.php');
         exit;
     }
 }
?>
