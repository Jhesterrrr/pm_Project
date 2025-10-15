<?php
session_start();
if (!isset($_SESSION['Employee_ID'])) {
    header('Location: Admin_Login.php');
    exit;
}
require_once __DIR__ . '/db_connection.php';

// --- Directory Structure Setup ---
$baseUploadDir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads';
$employeeUploadDir = $baseUploadDir . DIRECTORY_SEPARATOR . 'employee';
$companyUploadDir = $baseUploadDir . DIRECTORY_SEPARATOR . 'company';
$archiveDir = $baseUploadDir . DIRECTORY_SEPARATOR . 'archive';
@mkdir($baseUploadDir, 0777, true);
@mkdir($employeeUploadDir, 0777, true);
@mkdir($companyUploadDir, 0777, true);
@mkdir($archiveDir . DIRECTORY_SEPARATOR . 'employee', 0777, true);
@mkdir($archiveDir . DIRECTORY_SEPARATOR . 'company', 0777, true);

// --- Table Creation ---
$mysqli->query("CREATE TABLE IF NOT EXISTS employee_files (
  ID INT AUTO_INCREMENT PRIMARY KEY,
  account_owner VARCHAR(255) NULL,
  employee_ID INT NOT NULL,
  file_name VARCHAR(255) NOT NULL,
  upload_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  Archived TINYINT(1) NOT NULL DEFAULT 0,
  Archived_At DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$mysqli->query("CREATE TABLE IF NOT EXISTS company_files (
  ID INT AUTO_INCREMENT PRIMARY KEY,
  Company_name VARCHAR(255) NOT NULL,
  Company_File VARCHAR(255) NOT NULL,
  Upload_Date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  Archived TINYINT(1) NOT NULL DEFAULT 0,
  Archived_At DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// --- Column Existence Ensurer ---
function ensureColumnExists($mysqli, $table, $column, $definition) {
    $result = $mysqli->prepare("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1");
    if ($result) {
        $result->bind_param('ss', $table, $column);
        $result->execute();
        $res = $result->get_result();
        $found = ($res && $res->fetch_row()) ? true : false;
        $result->close();
        if (!$found) {
            $mysqli->query("ALTER TABLE `" . $mysqli->real_escape_string($table) . "` ADD COLUMN " . $definition);
        }
    }
}
ensureColumnExists($mysqli, 'employee_files', 'Archived', "`Archived` TINYINT(1) NOT NULL DEFAULT 0");
ensureColumnExists($mysqli, 'employee_files', 'Archived_At', "`Archived_At` DATETIME NULL");
ensureColumnExists($mysqli, 'company_files', 'Archived', "`Archived` TINYINT(1) NOT NULL DEFAULT 0");
ensureColumnExists($mysqli, 'company_files', 'Archived_At', "`Archived_At` DATETIME NULL");

// --- Input Processing ---
$selectedEmployeeId = isset($_GET['employee_id']) ? intval($_GET['employee_id']) : 0;
$selectedCompany    = isset($_GET['company']) ? trim($_GET['company']) : '';
$selectedDepartment = isset($_GET['department']) ? trim($_GET['department']) : '';
if (!$selectedCompany && $selectedDepartment) $selectedCompany = $selectedDepartment;
$isTrash = (!empty($_GET['trash']) && $_GET['trash'] == '1');

// --- Handle File Actions (Delete/Restore/Purge/Upload) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $scope = $_POST['scope'] ?? '';
    $fileId = isset($_POST['file_id']) ? intval($_POST['file_id']) : 0;

    // ARCHIVE / DELETE TO TRASH
    if ($action === 'delete' && $fileId > 0) {
        if ($scope === 'employee') {
            $stmt = $mysqli->prepare("SELECT ID, employee_ID, file_name FROM employee_files WHERE ID=? AND IFNULL(Archived,0)=0");
            if ($stmt) {
                $stmt->bind_param('i', $fileId);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($row = $res->fetch_assoc()) {
                    $empFolder = $employeeUploadDir . DIRECTORY_SEPARATOR . $row['employee_ID'];
                    $filePath = $empFolder . DIRECTORY_SEPARATOR . $row['file_name'];
                    $empArchive = $archiveDir . DIRECTORY_SEPARATOR . 'employee' . DIRECTORY_SEPARATOR . $row['employee_ID'];
                    @mkdir($empArchive, 0777, true);
                    if (is_file($filePath)) @rename($filePath, $empArchive . DIRECTORY_SEPARATOR . $row['file_name']);
                    $mysqli->query("UPDATE employee_files SET Archived=1, Archived_At=NOW() WHERE ID=".$fileId);
                    // Audit log
                    if (isset($_SESSION['Employee_ID'], $_SESSION['Department'], $_SESSION['Position'])) {
                        $log = $mysqli->prepare("INSERT INTO audit_log (Employee_ID, Department, Position, Module, Action, Details) VALUES (?, ?, ?, 'Legal/Documents', 'Archive', ?)");
                        $actorId = (int)$_SESSION['Employee_ID'];
                        $dept = (string)$_SESSION['Department'];
                        $pos = (string)$_SESSION['Position'];
                        $details = "Archived employee file '" . $row['file_name'] . "' for Employee ID " . (int)$row['employee_ID'];
                        $log->bind_param('isss', $actorId, $dept, $pos, $details); $log->execute(); $log->close();
                    }
                }
                $stmt->close();
            }
        }
        if ($scope === 'company') {
            $stmt = $mysqli->prepare("SELECT ID, Company_name, Company_File FROM company_files WHERE ID=? AND IFNULL(Archived,0)=0");
            if ($stmt) {
                $stmt->bind_param('i', $fileId);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($row = $res->fetch_assoc()) {
                    $safeCompany = preg_replace('/[^A-Za-z0-9_-]+/', '_', $row['Company_name']);
                    $compFolder = $companyUploadDir . DIRECTORY_SEPARATOR . $safeCompany;
                    $filePath = $compFolder . DIRECTORY_SEPARATOR . $row['Company_File'];
                    $compArchive = $archiveDir . DIRECTORY_SEPARATOR . 'company' . DIRECTORY_SEPARATOR . $safeCompany;
                    @mkdir($compArchive, 0777, true);
                    if (is_file($filePath)) @rename($filePath, $compArchive . DIRECTORY_SEPARATOR . $row['Company_File']);
                    $mysqli->query("UPDATE company_files SET Archived=1, Archived_At=NOW() WHERE ID=".$fileId);
                    // Audit log
                    if (isset($_SESSION['Employee_ID'], $_SESSION['Department'], $_SESSION['Position'])) {
                        $log = $mysqli->prepare("INSERT INTO audit_log (Employee_ID, Department, Position, Module, Action, Details) VALUES (?, ?, ?, 'Legal/Documents', 'Archive', ?)");
                        $actorId = (int)$_SESSION['Employee_ID'];
                        $dept = (string)$_SESSION['Department'];
                        $pos = (string)$_SESSION['Position'];
                        $details = "Archived company file '" . $row['Company_File'] . "' for Company '" . $row['Company_name'] . "'";
                        $log->bind_param('isss', $actorId, $dept, $pos, $details); $log->execute(); $log->close();
                    }
                }
                $stmt->close();
            }
        }
    }

    // RESTORE FROM TRASH
    if ($action === 'restore' && $fileId > 0) {
        if ($scope === 'employee') {
            $stmt = $mysqli->prepare("SELECT ID, employee_ID, file_name FROM employee_files WHERE ID=? AND IFNULL(Archived,0)=1");
            if ($stmt) {
                $stmt->bind_param('i', $fileId);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($row = $res->fetch_assoc()) {
                    $empArchive = $archiveDir . DIRECTORY_SEPARATOR . 'employee' . DIRECTORY_SEPARATOR . $row['employee_ID'] . DIRECTORY_SEPARATOR . $row['file_name'];
                    $empFolder = $employeeUploadDir . DIRECTORY_SEPARATOR . $row['employee_ID'];
                    @mkdir($empFolder, 0777, true);
                    if (is_file($empArchive)) @rename($empArchive, $empFolder . DIRECTORY_SEPARATOR . $row['file_name']);
                    $mysqli->query("UPDATE employee_files SET Archived=0, Archived_At=NULL WHERE ID=".$fileId);
                }
                $stmt->close();
            }
        }
        if ($scope === 'company') {
            $stmt = $mysqli->prepare("SELECT ID, Company_name, Company_File FROM company_files WHERE ID=? AND IFNULL(Archived,0)=1");
            if ($stmt) {
                $stmt->bind_param('i', $fileId);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($row = $res->fetch_assoc()) {
                    $safeCompany = preg_replace('/[^A-Za-z0-9_-]+/', '_', $row['Company_name']);
                    $compArchive = $archiveDir . DIRECTORY_SEPARATOR . 'company' . DIRECTORY_SEPARATOR . $safeCompany . DIRECTORY_SEPARATOR . $row['Company_File'];
                    $compFolder = $companyUploadDir . DIRECTORY_SEPARATOR . $safeCompany;
                    @mkdir($compFolder, 0777, true);
                    if (is_file($compArchive)) @rename($compArchive, $compFolder . DIRECTORY_SEPARATOR . $row['Company_File']);
                    $mysqli->query("UPDATE company_files SET Archived=0, Archived_At=NULL WHERE ID=".$fileId);
                }
                $stmt->close();
            }
        }
    }

    // PURGE PERMANENTLY
    if ($action === 'purge' && $fileId > 0) {
        if ($scope === 'employee') {
            $stmt = $mysqli->prepare("SELECT ID, employee_ID, file_name FROM employee_files WHERE ID=?");
            if ($stmt) {
                $stmt->bind_param('i', $fileId);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($row = $res->fetch_assoc()) {
                    $empArchive = $archiveDir . DIRECTORY_SEPARATOR . 'employee' . DIRECTORY_SEPARATOR . $row['employee_ID'] . DIRECTORY_SEPARATOR . $row['file_name'];
                    if (is_file($empArchive)) @unlink($empArchive);
                    $mysqli->query("DELETE FROM employee_files WHERE ID=".$fileId);
                }
                $stmt->close();
            }
        }
        if ($scope === 'company') {
            $stmt = $mysqli->prepare("SELECT ID, Company_name, Company_File FROM company_files WHERE ID=?");
            if ($stmt) {
                $stmt->bind_param('i', $fileId);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($row = $res->fetch_assoc()) {
                    $safeCompany = preg_replace('/[^A-Za-z0-9_-]+/', '_', $row['Company_name']);
                    $compArchive = $archiveDir . DIRECTORY_SEPARATOR . 'company' . DIRECTORY_SEPARATOR . $safeCompany . DIRECTORY_SEPARATOR . $row['Company_File'];
                    if (is_file($compArchive)) @unlink($compArchive);
                    $mysqli->query("DELETE FROM company_files WHERE ID=".$fileId);
                }
                $stmt->close();
            }
        }
    }

    // UPLOAD FILE
    if ($action === 'upload') {
        if ($scope === 'employee' && isset($_POST['employee_id']) && isset($_FILES['file'])) {
            $empId = intval($_POST['employee_id']);
            if ($empId > 0 && is_uploaded_file($_FILES['file']['tmp_name'])) {
                $empFolder = $employeeUploadDir . DIRECTORY_SEPARATOR . $empId;
                @mkdir($empFolder, 0777, true);
                $fname = basename($_FILES['file']['name']);
                $targetPath = $empFolder . DIRECTORY_SEPARATOR . $fname;
                if (move_uploaded_file($_FILES['file']['tmp_name'], $targetPath)) {
                    $accOwner = '';
                    $uStmt = $mysqli->prepare("SELECT Username FROM accounts WHERE Employee_ID=? LIMIT 1");
                    if ($uStmt) {
                        $uStmt->bind_param('i', $empId);
                        $uStmt->execute();
                        $result = $uStmt->get_result();
                        if ($row = $result->fetch_assoc()) {
                            $accOwner = $row['Username'];
                        }
                        $uStmt->close();
                    }
                    if ($accOwner != '') {
                        $stmt = $mysqli->prepare("INSERT INTO employee_files (account_owner, employee_ID, file_name) VALUES (?, ?, ?)");
                        if ($stmt) {
                            $stmt->bind_param('sis', $accOwner, $empId, $fname);
                            $stmt->execute(); $stmt->close();
                        }
                        // Audit log
                        if (isset($_SESSION['Employee_ID'], $_SESSION['Department'], $_SESSION['Position'])) {
                            $log = $mysqli->prepare("INSERT INTO audit_log (Employee_ID, Department, Position, Module, Action, Details) VALUES (?, ?, ?, 'Legal/Documents', 'Upload', ?)");
                            $actorId = (int)$_SESSION['Employee_ID'];
                            $dept = (string)$_SESSION['Department'];
                            $pos = (string)$_SESSION['Position'];
                            $details = "Uploaded employee file '" . $fname . "' for Employee ID " . $empId;
                            $log->bind_param('isss', $actorId, $dept, $pos, $details); $log->execute(); $log->close();
                        }
                    } else {
                        @unlink($targetPath);
                    }
                }
            }
        }
        if ($scope === 'company' && isset($_POST['company_name']) && isset($_FILES['file'])) {
            $company = trim($_POST['company_name']);
            if ($company !== '' && is_uploaded_file($_FILES['file']['tmp_name'])) {
                $safeCompany = preg_replace('/[^A-Za-z0-9_-]+/', '_', $company);
                $compFolder = $companyUploadDir . DIRECTORY_SEPARATOR . $safeCompany;
                @mkdir($compFolder, 0777, true);
                $fname = basename($_FILES['file']['name']);
                $targetPath = $compFolder . DIRECTORY_SEPARATOR . $fname;
                if (move_uploaded_file($_FILES['file']['tmp_name'], $targetPath)) {
                    $stmt = $mysqli->prepare("INSERT INTO company_files (Company_name, Company_File) VALUES (?, ?)");
                    if ($stmt) {
                        $stmt->bind_param('ss', $company, $fname);
                        $stmt->execute(); $stmt->close();
                    }
                    // Audit log
                    if (isset($_SESSION['Employee_ID'], $_SESSION['Department'], $_SESSION['Position'])) {
                        $log = $mysqli->prepare("INSERT INTO audit_log (Employee_ID, Department, Position, Module, Action, Details) VALUES (?, ?, ?, 'Legal/Documents', 'Upload', ?)");
                        $actorId = (int)$_SESSION['Employee_ID'];
                        $dept = (string)$_SESSION['Department'];
                        $pos = (string)$_SESSION['Position'];
                        $details = "Uploaded company file '" . $fname . "' for Company '" . $company . "'";
                        $log->bind_param('isss', $actorId, $dept, $pos, $details); $log->execute(); $log->close();
                    }
                }
            }
        }
    }

    // After any POST, redirect to refresh and prevent resubmit
    $qs = $_SERVER['QUERY_STRING'] ?? '';
    header('Location: File_Manager.php' . ($qs ? ('?' . $qs) : ''));
    exit;
}

// --- Data Fetching ---
$employees = [];
$employeeQ = $mysqli->query("SELECT Employee_ID, COALESCE(Account_Owner, CONCAT('Emp#', Employee_ID)) AS Account_Owner FROM accounts ORDER BY Account_Owner ASC");
if ($employeeQ) while ($r = $employeeQ->fetch_assoc()) $employees[] = $r;

$empFiles = [];
if ($selectedEmployeeId > 0) {
    $archiv = $isTrash ? 1 : 0;
    $empFileStmt = $mysqli->prepare("SELECT ID, employee_ID, file_name, upload_date FROM employee_files WHERE employee_ID = ? AND IFNULL(Archived,0)=? ORDER BY upload_date DESC, ID DESC");
    if ($empFileStmt) {
        $empFileStmt->bind_param('ii', $selectedEmployeeId, $archiv);
        $empFileStmt->execute();
        $res = $empFileStmt->get_result();
        while ($f = $res->fetch_assoc()) $empFiles[] = $f;
        $empFileStmt->close();
    }
}

$companies = [];
$compQ = $mysqli->query("SELECT DISTINCT Company_name FROM company_files ORDER BY Company_name ASC");
if ($compQ) while ($r = $compQ->fetch_assoc()) $companies[] = $r['Company_name'];

$companyFiles = [];
if ($selectedCompany !== '') {
    $archiv = $isTrash ? 1 : 0;
    $compFileStmt = $mysqli->prepare("SELECT ID, Company_name, Company_File, Upload_Date FROM company_files WHERE Company_name = ? AND IFNULL(Archived,0)=? ORDER BY Upload_Date DESC, ID DESC");
    if ($compFileStmt) {
        $compFileStmt->bind_param('si', $selectedCompany, $archiv);
        $compFileStmt->execute();
        $res = $compFileStmt->get_result();
        while ($f = $res->fetch_assoc()) $companyFiles[] = $f;
        $compFileStmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>File Manager</title>
    <link rel="stylesheet" href="CSS_files/Admin_Dash.css">
    <style>
        html,body { font-family: 'Segoe UI',Arial,sans-serif; background:#f3f4f6; margin:0; padding:0; color:#111827;}
        .dashboard-layout { display:flex; min-height:100vh; background:#f9fafb; }
        .sidebar { width:250px; background:#1e293b; padding:32px 0 20px 0; color:#fff; display:flex; flex-direction:column; box-shadow:0 0 6px 0 #0001; }
        .sidebar-brand { display:flex; align-items:center; gap:15px; font-size:23px; font-weight:700; margin-bottom:32px; padding:0 28px; }
        .brand-icon { font-size:29px; }
        .sidebar-nav { display:flex; flex-direction:column; gap:8px; }
        .nav-item { background:none; border:none; color:#dbeafe; text-align:left; font-size:17px; padding:12px 28px; cursor:pointer; border-radius:8px; transition:background .14s,color .13s; display:flex; align-items:center; gap:10px;}
        .nav-item:hover, .nav-item.active { background:#2563eb; color:#fff; }
        .nav-group { margin-bottom:6px; }
        .nav-item.has-children { justify-content:space-between; }
        .nav-caret { font-size:13px; margin-left:auto; }
        .nav-children { display:none; flex-direction:column; gap:2px; padding-left:22px;}
        .nav-children.open { display:flex; }
        .nav-child { color:#dbeafe; text-decoration:none; font-size:15px; padding:6px 0; transition:color .13s; }
        #group-users .nav-child:hover { color:#fff; text-decoration:none; }
        .main { flex:1; padding:42px 0 0 0; background:#f9fafb; min-height:100vh; }
        .ua-container { max-width: 1300px; margin: 28px auto; padding: 0 20px; }
        .two-col { display:flex; flex-wrap:wrap; gap:30px; margin-top:15px; }
        .col { flex:1; min-width:480px; }
        .panel { background:#fff; border-radius:11px; box-shadow: 0 5px 32px 0 #2563eb18; margin-bottom: 24px;}
        .panel-header { border-bottom: 1px solid #ececec; padding:18px 22px; font-size:19px; font-weight:500; display:flex; align-items:center; justify-content:space-between;}
        .panel-body { padding:19px 25px;}
        .btn, .btn-primary, .btn-danger, .btn-download { transition: background .15s, color .14s, box-shadow .16s;}
        .panel-controls { display:flex; gap:13px; align-items:center; flex-wrap:wrap; }
        .btn {
            padding:9px 17px;
            border:none;
            border-radius:7px;
            cursor:pointer;
            font-weight:600;
            background:#e0e7ef;
            color:#333;
            margin:2px 2px;
            font-size: 15px;
        }
        .btn-primary { background:#2563eb; color:#fff !important; }
        .btn-danger { background:#dc2626; color:#fff !important; }
        .btn-download { background:#3B82F6; color:#fff !important; text-decoration:none; }
        .btn-download:visited { color:#fff; }
        .btn-download:hover, .btn-download:focus { text-decoration:none; color:#f3f4f6; background:#1d4ed8; }
        /* --- Professional Trash/Back Button --- */
        .btn-muted {
            background: linear-gradient(90deg, #8b5cf6 0%, #2563eb 100%);
            color: #fff !important;
            border: none;
            font-weight: 700;
            font-size: 16px;
            padding: 9px 26px 9px 42px;
            border-radius: 38px;
            position: relative;
            transition: 
                box-shadow 0.18s, 
                background 0.18s cubic-bezier(.42,.86,.72,1.04),
                color .16s, 
                transform .17s;
            box-shadow: 0 1px 6px #2563eb18;
            letter-spacing: .02em;
            z-index: 0;
            overflow: hidden;
        }
        .btn-muted::before {
            content: '';
            position: absolute;
            top: 8px;
            left: 15px;
            width: 19px;
            height: 19px;
            background: url('data:image/svg+xml;utf8,<svg fill="none" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 22 22"><path d="M8.5 15.5a1 1 0 00-1.41 0A1 1 0 008.5 15.5zm0 0v0a1 1 0 000-1.41v0A1 1 0 008.5 15.5zm5-8a1 1 0 10-1.41 1.41A1 1 0 0113.5 7.5zM9.2 14.2l6-6a1 1 0 011.4 1.44l-6 6a1 1 0 10-1.4-1.44z" fill="%23fff" fill-opacity="0.93"/></svg>') no-repeat center center / contain;
            opacity: .93;
        }
        .btn-muted:hover, .btn-muted:focus {
            background: linear-gradient(93deg,#4338ca 0%, #1e293b 100%);
            color: #c7d2fe !important;
            box-shadow: 0 4px 22px #2563eb35, 0 2px 7px #11192715;
            transform: translateY(-2px) scale(1.04);
        }
        .btn-muted:active {
            background:#1e293b;
            color: #b3bcfa !important;
        }
        .btn:disabled, .btn[disabled] { opacity:0.54; pointer-events:none; filter: grayscale(0.35);}
        .file-list { list-style:none; padding:0; margin:0; }
        .file-item { display:flex; align-items:center; justify-content:space-between; gap:18px; padding:15px 7px; border-bottom:1px solid #e5e7eb; }
        .file-info { display:flex; align-items:center; gap:13px; min-width:0; }
        .file-link { color:#18346d; text-decoration:none; font-weight:700; font-size:16px;}
        .file-link:hover { text-decoration:underline; color: #2563eb;}
        .file-meta { color:#636e8a; font-size:12.5px; margin-left:10px; }
        .file-actions { display:flex; gap:9px; align-items:center; flex-shrink:0; }
        .notice { color:#65748b; font-size:14px; margin:8px 0 0; padding:10px 8px 10px 5px; }
        .breadcrumb { margin-bottom:24px; }
        .ua-title { font-size:24px; font-weight:600; letter-spacing:-.02em;}
        .search, input[type="number"], input[type="text"] { padding:8px 13px; border-radius:7px; border: 1px solid #dbeafe; background:#f5faff; color:#15418b; font-size:15px; font-family:inherit; }
        .search:focus { outline:2px solid #2563eb; background:#fff; border-color:#3b82f6; color:#123; }

        /* Enhanced Back Button Styling */
        .backbtn {
            display: inline-flex;
            align-items: center;
            gap: 13px;
            padding: 13px 28px;
            border: none;
            border-radius: 109px;
            background: linear-gradient(90deg, #2563eb 0%, #3b82f6 100%);
            color: #fff;
            font-weight: 700;
            font-size: 18px;
            text-decoration: none;
            box-shadow: 0 4px 18px rgba(37,99,235,0.10), 0 2px 8px rgba(0,0,0,0.06);
            letter-spacing: .06em;
            position: relative;
            transition: background .23s cubic-bezier(.32,1.25,.5,1),box-shadow .19s,transform .18s;
            overflow: hidden;
            cursor: pointer;
        }
        .backbtn svg { width: 23px; height: 23px;margin-right:2px;transition:transform .20s cubic-bezier(.22,.75,.57,1.05);}
        .backbtn .bb-label { transition: color .17s;}
        .backbtn:active { transform: scale(.98);}
        .backbtn:before {content: '';position: absolute;left: 0; top: 0; right: 0; bottom: 0;background:linear-gradient(120deg, #1d4ed8 0%, #333 100%);opacity: 0;z-index: 0;transition: opacity .26s cubic-bezier(.46,.91,.2,1.12);}
        .backbtn:hover:before, .backbtn:focus:before {opacity: 0.15;}
        .backbtn:hover, .backbtn:focus {background: linear-gradient(93deg, #1d4ed8, #2563eb 60%, #0ea5e9 100%);box-shadow: 0 6px 24px rgba(2,80,195,.10), 0 2px 12px rgba(0,0,0,0.06);text-decoration: none;outline: none;}
        .backbtn:hover .bb-label {color: #e0e7ff;}
        .backbtn:hover svg {transform: translateX(-4px) scale(1.10);filter: drop-shadow(0 2px 8px #60a5fa33);}

        /* --- PROFESSIONAL "VIEW" BUTTON --- */
        .btn-view {
            background: #fff;
            color: #2563eb;
            border: 2px solid #2563eb;
            padding: 8px 15px 8px 36px;
            border-radius: 7px;
            position: relative;
            font-weight: 600;
            font-size: 15px;
            box-shadow: 0 1px 6px #2563eb14;
            transition: background 0.19s, color 0.18s, box-shadow 0.19s, border-color 0.18s, transform 0.17s;
            overflow: hidden;
        }
        .btn-view::before {
            content: '';
            display: block;
            position: absolute;
            width: 18px;
            height: 18px;
            left: 13px;
            top: 6.5px;
            background: url('data:image/svg+xml;utf8,<svg fill="none" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 22 22"><path stroke="%232563eb" stroke-width="2" d="M1.95 11.1S5.23 3.8 11 3.8c5.8 0 9.05 7.3 9.05 7.3s-3.26 7.3-9.05 7.3c-5.77 0-9.05-7.3-9.05-7.3Z"/><circle cx="11" cy="11.1" r="3.25" stroke="%232563eb" stroke-width="2"/></svg>') no-repeat center center;
        }
        .btn-view:hover, .btn-view:focus {
            background: #2563eb;
            color: #fff;
            border-color: #2563eb;
            transform: translateY(-2px) scale(1.03);
            box-shadow: 0 4px 24px #2563eb32, 0 2px 8px #1e293b17;
        }
        .btn-view:active {
            background: #1747b6;
            color: #fff;
        }

        /* --- PROFESSIONAL "TRASH" BUTTON --- */
        .btn-trash {
            background: linear-gradient(90deg, #ef4444 0%, #f43f5e 100%);
            color: #fff;
            padding: 8px 17px 8px 35px;
            border-radius: 7px;
            border: none;
            font-size: 15px;
            font-weight: 600;
            position: relative;
            overflow: hidden;
            outline: none;
            box-shadow: 0 1px 10px #ef444417;
            transition: background 0.18s, color 0.18s, box-shadow 0.19s, transform 0.18s;
        }
        .btn-trash::before {
            content: '';
            display: block;
            position: absolute;
            left: 11px;
            top: 7.4px;
            width: 16px;
            height: 16px;
            background: url('data:image/svg+xml;utf8,<svg fill="none" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 22 22"><rect x="6.4" y="8.2" width="8.1" height="6.3" rx="1" stroke="white" stroke-width="1.8"/><rect x="9.4" y="2.6" width="3.2" height="2.9" rx="1" fill="white"/><rect x="3.8" y="5.4" width="14.4" height="2.4" rx="1" fill="white" /><rect x="6.55" y="11.1" width="1.5" height="2.8" rx="0.72" fill="%23fff"/><rect x="10" y="11.1" width="1.5" height="2.8" rx="0.72" fill="%23fff"/><rect x="13.45" y="11.1" width="1.5" height="2.8" rx="0.72" fill="%23fff"/></svg>') no-repeat center center;
        }
        .btn-trash:hover, .btn-trash:focus {
            background: linear-gradient(95deg, #b71c1c 0%, #dc2626 100%);
            color: #fff;
            transform: translateY(-2px) scale(1.04);
            box-shadow: 0 6px 28px #f43f5e30, 0 2px 12px #ea4d0b23;
        }
        .btn-trash:active {
            background: #7f1010;
            color: #fff;
        }

        /* Button fix for Restore in Trash */
        .btn-restore {
            background: linear-gradient(90deg, #10b981 0%, #38bdf8 100%);
            color: #fff !important;
            padding: 8px 17px 8px 33px;
            border-radius: 7px;
            border: none;
            font-size: 15px;
            font-weight: 600;
            position: relative;
            overflow: hidden;
            outline: none;
            box-shadow: 0 2px 8px #16a34a19;
            transition: background 0.18s, color 0.18s, box-shadow 0.17s, transform 0.16s;
        }
        .btn-restore::before {
            content: '';
            display: block;
            position: absolute;
            left: 10px;
            top: 7.4px;
            width: 16px;
            height: 16px;
            background: url('data:image/svg+xml;utf8,<svg width="20" height="20" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M3 10a7 7 0 1114 0A7 7 0 013 10zm9.6.6V8.2a.6.6 0 10-1.2 0v2.4a.6.6 0 00.6.6h2.4a.6.6 0 100-1.2h-1.8z" fill="white"/></svg>') no-repeat center center;
        }
        .btn-restore:hover, .btn-restore:focus {
            background: linear-gradient(93deg, #059669 0%, #2563eb 100%);
            color: #fff;
            transform: translateY(-2px) scale(1.04);
            box-shadow: 0 6px 28px #38bdf830, 0 2px 12px #10b98113;
        }
        .btn-restore:active {
            background: #155e75;
            color: #fff;
        }

        @media (max-width: 900px) {.two-col { flex-direction:column; }.col{ min-width:280px;}}
        @media (max-width: 600px) {.backbtn{ font-size:15px;padding:11px 14px; }.breadcrumb{ margin-bottom:10px;}.panel-header{font-size:17px;}}
    </style>
</head>
<body>
<div class="dashboard-layout">
    <aside class="sidebar">
        <div class="sidebar-brand">
            <span class="brand-icon">🏨</span>
            <span class="brand-text">Hotel Admin</span>
        </div>
        <nav class="sidebar-nav">
            <button class="nav-item" onclick="window.location.href='Admin_Dash.php'">
                <span class="nav-icon">🏠</span>
                <span class="nav-label">Dashboard</span>
            </button>
            <div class="nav-group">
                <button class="nav-item has-children" data-toggle="users">
                    <span class="nav-icon">👤</span>
                    <span class="nav-label">User Management</span>
                    <span class="nav-caret">▾</span>
                </button>
                <div class="nav-children" id="group-users">
                    <a class="nav-child" href="User_Accounts.php">User Accounts</a>
                    <a class="nav-child" href="User_Status.php">User Status &amp; Permissions</a>
                </div>
            </div>
            <button class="nav-item" onclick="window.location.href='Audit_Logs.php'">
                <span class="nav-icon">📝</span>
                <span class="nav-label">Audit / Activity Logs</span>
            </button>
            <button class="nav-item active" onclick="window.location.href='Legal_mangement.php'">
                <span class="nav-icon">📄</span>
                <span class="nav-label">Legal / Documents</span>
            </button>
            <div class="nav-group">
                <button class="nav-item has-children" data-toggle="customers">
                    <span class="nav-icon">👥</span>
                    <span class="nav-label">Customer Management</span>
                    <span class="nav-caret">▾</span>
                </button>
                <div class="nav-children" id="group-customers">
                    <a class="nav-child" href="Reservations.php">Reservations</a>
                    <a class="nav-child" href="customer_history.php">Customer History</a>
                    <a class="nav-child" href="rooms.php">Rooms</a>
                </div>
            </div>
            <button class="nav-item" onclick="window.location.href='logout.php'">
                <span class="nav-icon">🚪</span>
                <span class="nav-label">Logout</span>
            </button>
        </nav>
    </aside>
    <main class="main">
        <div class="ua-container">
            <div class="breadcrumb">
                <a class="backbtn" href="Legal_mangement.php" tabindex="0">
                    <span class="bb-icon" aria-hidden="true">
                        <svg viewBox="0 0 22 22" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M10.7 17.3a1 1 0 0 1-1.4 0l-6.59-6.57A1.992 1.992 0 0 1 2 9.03c0-.53.21-1.03.59-1.4l6.52-6.45a1 1 0 1 1 1.38 1.45L4.82 8.03h13.18a1 1 0 1 1 0 2H4.8l5.87 5.88a1 1 0 0 1 .03 1.42z" fill="currentColor"/></svg>
                    </span>
                    <span class="bb-label">Back to Legal / Documents</span>
                </a>
            </div>
            <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:11px;">
                <div class="ua-title">File Manager</div>
                <div>
                    <?php
                        $qs = [];
                        if ($selectedEmployeeId) $qs['employee_id'] = $selectedEmployeeId;
                        if ($selectedCompany !== '') $qs['company'] = $selectedCompany;
                        $qs['trash'] = $isTrash ? null : 1;
                        $trashUrl = 'File_Manager.php' . ($qs ? ('?' . http_build_query(array_filter($qs, function($v){return $v!==null;}))) : '');
                    ?>
                    <a class="btn btn-muted" href="<?php echo $trashUrl; ?>"><?php echo $isTrash ? 'Back to Files' : 'Trash'; ?></a>
                    <span class="hint" style="margin-left:11px;">Upload, view, and manage files</span>
                </div>
            </div>
            <div class="two-col">
                <!-- Employee Files Panel -->
                <section id="employee-panel" class="panel col">
                    <div class="panel-header">
                        <span><?php echo $isTrash ? 'Employee Trash' : 'Employee Files'; ?></span>
                        <form id="empUploadForm" method="POST" enctype="multipart/form-data" style="display:flex; gap:8px; align-items:center;">
                            <input type="hidden" name="action" value="upload">
                            <input type="hidden" name="scope" value="employee">
                            <input type="number" name="employee_id" value="<?php echo (int)$selectedEmployeeId; ?>" placeholder="Employee ID" class="search" style="width:130px;">
                            <input type="file" name="file" <?php echo $selectedEmployeeId ? 'required' : 'disabled'; ?>>
                            <button class="btn btn-primary" type="submit" title="Upload new employee file" <?php echo ($selectedEmployeeId && !$isTrash) ? '' : 'disabled'; ?>>Add File</button>
                        </form>
                    </div>
                    <div class="panel-body">
                        <?php if (!$selectedEmployeeId): ?>
                            <div class="notice">Select an Employee from Legal / Documents to view files.</div>
                        <?php else: ?>
                            <ul class="file-list">
                            <?php if (empty($empFiles)): ?>
                                <li class="file-item" style="color:#6b7280;">No files yet.</li>
                            <?php else: foreach ($empFiles as $f): ?>
                                <li class="file-item">
                                    <div class="file-info">
                                        <?php
                                            $safeEmpId = (int)$selectedEmployeeId;
                                            $fname = htmlspecialchars($f['file_name'], ENT_QUOTES, 'UTF-8');
                                            $empUrl = 'uploads/employee/' . $safeEmpId . '/' . rawurlencode($f['file_name']);
                                        ?>
                                        <span>📄</span>
                                        <a class="file-link" href="<?php echo $empUrl; ?>" target="_blank" download><?php echo $fname; ?></a>
                                        <span class="file-meta">Uploaded: <?php echo htmlspecialchars($f['upload_date'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    </div>
                                    <div class="file-actions">
                                        <a class="btn btn-download" href="<?php echo $empUrl; ?>" target="_blank" download>Download</a>
                                        <a class="btn btn-view" href="<?php echo $empUrl; ?>" target="_blank">View</a>
                                        <form method="POST" onsubmit="return confirm('<?php echo $isTrash ? 'Restore this file?' : 'Move file to trash?'; ?>');" style="display:inline;">
                                            <input type="hidden" name="action" value="<?php echo $isTrash ? 'restore' : 'delete'; ?>">
                                            <input type="hidden" name="scope" value="employee">
                                            <input type="hidden" name="file_id" value="<?php echo (int)$f['ID']; ?>">
                                            <?php if ($isTrash): ?>
                                                <button class="btn btn-restore" type="submit">Restore</button>
                                            <?php else: ?>
                                                <button class="btn btn-trash" type="submit">Trash</button>
                                            <?php endif; ?>
                                        </form>
                                        <?php if ($isTrash): ?>
                                        <form method="POST" onsubmit="return confirm('Permanently delete this file? This cannot be undone.');" style="display:inline;">
                                            <input type="hidden" name="action" value="purge">
                                            <input type="hidden" name="scope" value="employee">
                                            <input type="hidden" name="file_id" value="<?php echo (int)$f['ID']; ?>">
                                            <button class="btn btn-danger" type="submit">Delete Permanently</button>
                                        </form>
                                        <?php endif; ?>
                                    </div>
                                </li>
                            <?php endforeach; endif; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </section>
                <!-- Company Files Panel -->
                <section id="company-panel" class="panel col">
                    <div class="panel-header">
                        <span><?php echo $isTrash ? 'Company Trash' : 'Company Files'; ?></span>
                        <form id="compUploadForm" method="POST" enctype="multipart/form-data" style="display:flex; gap:8px; align-items:center;">
                            <input type="hidden" name="action" value="upload">
                            <input type="hidden" name="scope" value="company">
                            <input type="text" name="company_name" value="<?php echo htmlspecialchars($selectedCompany ?: '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="Company Name" class="search" style="width:150px;">
                            <input type="file" name="file" <?php echo $selectedCompany ? 'required' : 'disabled'; ?>>
                            <button class="btn btn-primary" type="submit" title="Upload new company file" <?php echo ($selectedCompany && !$isTrash) ? '' : 'disabled'; ?>>Add File</button>
                        </form>
                    </div>
                    <div class="panel-body">
                        <?php if ($selectedCompany === ''): ?>
                            <div class="notice">Select a Company from Legal / Documents to view files.</div>
                        <?php else: ?>
                            <ul class="file-list">
                            <?php if (empty($companyFiles)): ?>
                                <li class="file-item" style="color:#6b7280;">No files yet.</li>
                            <?php else: foreach ($companyFiles as $f): ?>
                                <li class="file-item">
                                    <div class="file-info">
                                        <?php
                                            $safeCompany = preg_replace('/[^A-Za-z0-9_-]+/', '_', $selectedCompany);
                                            $fname = htmlspecialchars($f['Company_File'], ENT_QUOTES, 'UTF-8');
                                            $compUrl = 'uploads/company/' . $safeCompany . '/' . rawurlencode($f['Company_File']);
                                        ?>
                                        <span>📄</span>
                                        <a class="file-link" href="<?php echo $compUrl; ?>" target="_blank" download><?php echo $fname; ?></a>
                                        <span class="file-meta">Uploaded: <?php echo htmlspecialchars($f['Upload_Date'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    </div>
                                    <div class="file-actions">
                                        <a class="btn btn-download" href="<?php echo $compUrl; ?>" target="_blank" download>Download</a>
                                        <a class="btn btn-view" href="<?php echo $compUrl; ?>" target="_blank">View</a>
                                        <form method="POST" onsubmit="return confirm('<?php echo $isTrash ? 'Restore this file?' : 'Move file to trash?'; ?>');" style="display:inline;">
                                            <input type="hidden" name="action" value="<?php echo $isTrash ? 'restore' : 'delete'; ?>">
                                            <input type="hidden" name="scope" value="company">
                                            <input type="hidden" name="file_id" value="<?php echo (int)$f['ID']; ?>">
                                            <?php if ($isTrash): ?>
                                                <button class="btn btn-restore" type="submit">Restore</button>
                                            <?php else: ?>
                                                <button class="btn btn-trash" type="submit">Trash</button>
                                            <?php endif; ?>
                                        </form>
                                        <?php if ($isTrash): ?>
                                        <form method="POST" onsubmit="return confirm('Permanently delete this file? This cannot be undone.');" style="display:inline;">
                                            <input type="hidden" name="action" value="purge">
                                            <input type="hidden" name="scope" value="company">
                                            <input type="hidden" name="file_id" value="<?php echo (int)$f['ID']; ?>">
                                            <button class="btn btn-danger" type="submit">Delete Permanently</button>
                                        </form>
                                        <?php endif; ?>
                                    </div>
                                </li>
                            <?php endforeach; endif; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </section>
            </div>
        </div>
    </main>
</div>

<script>
(function sidebarToggles(){
    var toggles = document.querySelectorAll('.nav-item.has-children');
    toggles.forEach(function(btn){
        btn.addEventListener('click', function() {
            var key = this.getAttribute('data-toggle');
            var group = document.getElementById('group-' + key);
            if (!group) return;
            var isOpen = group.classList.contains('open');
            group.classList.toggle('open', !isOpen);
            var caret = this.querySelector('.nav-caret');
            if (caret) caret.textContent = !isOpen ? '▴' : '▾';
        });
    });
})();

(function focusSelected(){
    var empId = <?php echo (int)$selectedEmployeeId; ?>;
    var company = <?php echo json_encode($selectedCompany); ?>;
    // Only show one panel at a time (either employee or company)
    if (empId > 0 && company.length === 0) {
        var compPanel = document.getElementById('company-panel');
        if (compPanel && compPanel.parentNode) compPanel.parentNode.removeChild(compPanel);
    }
    if (company && company.length > 0 && empId === 0) {
        var empPanel = document.getElementById('employee-panel');
        if (empPanel && empPanel.parentNode) empPanel.parentNode.removeChild(empPanel);
    }
})();

(function uploads(){
    // Confirm upload
    function attachConfirm(form){
        if (!form) return;
        form.addEventListener('submit', function(e){
            var fileInput = form.querySelector('input[type="file"]');
            if (!fileInput || !fileInput.files || fileInput.files.length === 0) return;
            var fname = fileInput.files[0].name;
            if (!confirm('Upload file "' + fname + '"?')) {
                e.preventDefault();
                return false;
            }
        });
    }
    attachConfirm(document.getElementById('empUploadForm'));
    attachConfirm(document.getElementById('compUploadForm'));

    function enableDrop(zoneSelector, inputSelector){
        var zone = document.querySelector(zoneSelector);
        var input = document.querySelector(inputSelector);
        if (!zone || !input) return;
        ['dragenter','dragover'].forEach(function(ev){
            zone.addEventListener(ev, function(e){ e.preventDefault(); zone.style.boxShadow = '0 0 0 2px #93c5fd'; });
        });
        ['dragleave','drop'].forEach(function(ev){
            zone.addEventListener(ev, function(e){ e.preventDefault(); zone.style.boxShadow = ''; });
        });
        zone.addEventListener('drop', function(e){
            zone.style.boxShadow = '';
            var files = e.dataTransfer ? e.dataTransfer.files : null;
            if (files && files.length) input.files = files;
        });
    }
    enableDrop('#employee-panel .panel-header', '#employee-panel input[type="file"]');
    enableDrop('#company-panel .panel-header', '#company-panel input[type="file"]');
})();
</script>
</body>
</html>
