<?php
session_start();
if (!isset($_SESSION['Employee_ID'])) {
    header('Location: Admin_Login.php');
    exit;
}
$Department = isset($_SESSION['Department']) ? $_SESSION['Department'] : 'Admin';
$Position = isset($_SESSION['Position']) ? $_SESSION['Position'] : 'Admin';
require_once __DIR__ . '/db_connection.php';

// Resolve current user's profile from accounts table
$currentEmployeeId = (int)$_SESSION['Employee_ID'];
$currentUserName = isset($_SESSION['Account_Owner']) ? $_SESSION['Account_Owner'] : '';
if ($currentEmployeeId > 0) {
    $pstmt = $mysqli->prepare("SELECT Account_Owner, Department, Position FROM accounts WHERE Employee_ID = ? LIMIT 1");
    if ($pstmt) {
        $pstmt->bind_param('i', $currentEmployeeId);
        $pstmt->execute();
        if (method_exists($pstmt, 'get_result')) {
            $pres = $pstmt->get_result();
            if ($pres) {
                $prow = $pres->fetch_assoc();
                if ($prow) {
                    $currentUserName = $prow['Account_Owner'] ?: $currentUserName;
                    $Department = $prow['Department'] ?: $Department;
                    $Position = $prow['Position'] ?: $Position;
                }
            }
        } else {
            $pstmt->bind_result($r_owner, $r_dept, $r_pos);
            if ($pstmt->fetch()) {
                if (!empty($r_owner)) { $currentUserName = $r_owner; }
                if (!empty($r_dept)) { $Department = $r_dept; }
                if (!empty($r_pos)) { $Position = $r_pos; }
            }
        }
        $pstmt->close();
    }
}

// Sample data for demonstration - replace with actual database queries
$pendingReservations = [
    [
        'id' => 1,
        'name' => 'John Smith',
        'email' => 'john.smith@email.com',
        'floor_number' => '3rd Floor',
        'time_in' => '14:00',
        'time_out' => '16:00',
        'date_reserved' => '2025-01-15'
    ],
    [
        'id' => 2,
        'name' => 'Maria Garcia',
        'email' => 'maria.garcia@email.com',
        'floor_number' => '5th Floor',
        'time_in' => '10:30',
        'time_out' => '12:30',
        'date_reserved' => '2025-01-16'
    ],
    [
        'id' => 3,
        'name' => 'David Johnson',
        'email' => 'david.johnson@email.com',
        'floor_number' => '2nd Floor',
        'time_in' => '09:00',
        'time_out' => '11:00',
        'date_reserved' => '2025-01-17'
    ]
];

$acceptedReservations = [
    [
        'id' => 4,
        'name' => 'Sarah Wilson',
        'email' => 'sarah.wilson@email.com',
        'floor_number' => '4th Floor',
        'time_in' => '13:00',
        'time_out' => '15:00',
        'date_reserved' => '2025-01-14',
        'status' => 'active' // active, delayed, reschedule
    ],
    [
        'id' => 5,
        'name' => 'Michael Brown',
        'email' => 'michael.brown@email.com',
        'floor_number' => '1st Floor',
        'time_in' => '11:30',
        'time_out' => '13:30',
        'date_reserved' => '2025-01-13',
        'status' => 'delayed'
    ],
    [
        'id' => 6,
        'name' => 'Lisa Anderson',
        'email' => 'lisa.anderson@email.com',
        'floor_number' => '6th Floor',
        'time_in' => '15:30',
        'time_out' => '17:30',
        'date_reserved' => '2025-01-12',
        'status' => 'reschedule'
    ]
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservations Management - Hotel Admin</title>
    <link rel="stylesheet" href="CSS_files/Admin_Dash.css">
    <style>
        .dashboard-layout { display:flex; min-height:100vh; background:#f9fafb; }
        .sidebar { width:235px; background:#1e293b; padding:26px 0 16px 0; color:#fff; display:flex; flex-direction:column; }
        .sidebar-brand { display:flex; align-items:center; gap:12px; font-size:21px; font-weight:700; margin-bottom:28px; padding:0 23px; }
        .brand-icon { font-size:26px; }
        .sidebar-nav { display:flex; flex-direction:column; gap:6px; }
        .nav-item { background:none; border:none; color:#dbeafe; text-align:left; font-size:16px; padding:12px 23px; cursor:pointer; border-radius:7px; transition:background .14s,color .13s; display:flex; align-items:center; gap:9px;}
        .nav-item:hover, .nav-item.active { background:#2563eb; color:#fff; }
        .nav-group { margin-bottom:5px; }
        .nav-item.has-children { justify-content:space-between; }
        .nav-caret { font-size:13px; margin-left:auto; }
        .nav-children { display:none; flex-direction:column; gap:2px; padding-left:19px;}
        .nav-children.open { display:flex; }
        .nav-child { color:#dbeafe; text-decoration:none; font-size:15px; padding:6px 0; transition:color .13s; }
        .nav-child:hover { color:#fff; text-decoration:none; }
        .main { flex:1; padding:40px 0 0 0; background:#f9fafb; min-height:100vh; }
        .reservations-container { max-width: 1200px; margin: 20px auto; padding: 0 16px; }
        .reservations-title { font-size: 22px; font-weight: 700; color:#0f172a; margin-bottom: 20px; }
        .reservations-back { display:inline-flex; align-items:center; gap:6px; padding:8px 12px; border:1px solid #2563eb; color:#2563eb; border-radius:8px; text-decoration:none; margin-bottom: 20px; }
        .reservations-back:hover { background:#2563eb; color:#fff; }
        .two-col { display:flex; flex-direction:column; gap:22px; margin-top:18px; }
        .col { width:100%; }
        .reservation-panel { background:#ffffff; border-radius:12px; padding:20px; box-shadow:0 8px 24px rgba(0,0,0,0.06); margin-bottom:20px; }
        .panel-header { font-weight:600; color:#0f172a; margin-bottom:15px; font-size:18px; display:flex; align-items:center; gap:8px; }
        .panel-icon { font-size:20px; }
        .table-wrap { overflow-x: auto; }
        .reservation-table { width:100%; border-collapse:separate; border-spacing:0; font-size:14px; }
        .reservation-table thead th { text-align:left; color:#6b7280; font-weight:600; padding:12px; border-bottom:2px solid #e5e7eb; background:#f8fafc; }
        .reservation-table tbody td { padding:12px; border-bottom:1px solid #f1f5f9; }
        .reservation-table tbody tr:nth-child(odd) { background:#fcfdff; }
        .reservation-table tbody tr:hover { background:#f8fafc; }
        .status-badge { padding:4px 8px; border-radius:6px; font-size:12px; font-weight:600; text-transform:uppercase; }
        .status-pending { background:#fef3c7; color:#d97706; }
        .status-accepted { background:#d1fae5; color:#059669; }
        .no-data { text-align:center; color:#6b7280; padding:40px; font-style:italic; }
        .action-buttons { display:flex; gap:8px; }
        .btn-confirm { background:#10b981; color:#fff; border:none; padding:6px 12px; border-radius:6px; font-size:12px; cursor:pointer; transition:background 0.2s; }
        .btn-confirm:hover { background:#059669; }
        .btn-delete { background:#ef4444; color:#fff; border:none; padding:6px 12px; border-radius:6px; font-size:12px; cursor:pointer; transition:background 0.2s; }
        .btn-delete:hover { background:#dc2626; }
        .status-indicator { width:12px; height:12px; border-radius:50%; display:inline-block; margin-right:8px; }
        .status-active { background:#10b981; }
        .status-delayed { background:#f59e0b; }
        .status-reschedule { background:#f59e0b; }
        .status-text { font-size:12px; font-weight:600; text-transform:uppercase; }
    </style>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>

<body>
    <div class="dashboard-layout">
        <aside class="sidebar">
            <div class="sidebar-brand">
                <span class="brand-icon">🏨</span>
                <span class="brand-text">Hotel Admin</span>
            </div>
            <nav class="sidebar-nav">
                <button class="nav-item" data-section="dashboard" onclick="window.location.href='Admin_Dash.php'">
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
                <button class="nav-item" data-section="audits" onclick="window.location.href='Audit_Logs.php'">
                    <span class="nav-icon">📝</span>
                    <span class="nav-label">Audit / Activity Logs</span>
                </button>
                <button class="nav-item" data-section="legal" onclick="window.location.href='Legal_mangement.php'">
                    <span class="nav-icon">📄</span>
                    <span class="nav-label">Legal / Documents</span>
                </button>
                <div class="nav-group">
                    <button class="nav-item has-children active" data-toggle="customers">
                        <span class="nav-icon">👥</span>
                        <span class="nav-label">Customer Management</span>
                        <span class="nav-caret">▴</span>
                    </button>
                    <div class="nav-children open" id="group-customers">
                        <a class="nav-child" href="Reservations.php" style="color:#fff;">Reservations</a>
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
            <div class="reservations-container">
                <header class="main-header">
                    <h1 class="reservations-title">Reservations Management</h1>
                    <div class="user-area">
                        <div class="user-info">
                            <img class="avatar" src="https://ui-avatars.com/api/?name=<?php echo urlencode($currentUserName ?: ($Position)); ?>&background=1f2937&color=fff" alt="Profile">
                            <div class="user-texts">
                                <div class="user-name"><?php echo htmlspecialchars($currentUserName ?: 'Admin User', ENT_QUOTES, 'UTF-8'); ?></div>
                                <div class="user-meta"><?php echo htmlspecialchars($Position . ' • ' . $Department, ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>
                        </div>
                        <a class="btn-logout" href="logout.php" title="Logout">Logout</a>
                    </div>
                </header>

                <a href="Admin_Dash.php" class="reservations-back">
                    <span>←</span>
                    <span>Back to Dashboard</span>
                </a>

                <div class="two-col">
                    <!-- Pending Reservations -->
                    <div class="col">
                        <div class="reservation-panel">
                            <div class="panel-header">
                                <span class="panel-icon">⏳</span>
                                <span>Pending Reservations</span>
                                <span class="status-badge status-pending"><?php echo count($pendingReservations); ?> Pending</span>
                            </div>
                            <div class="table-wrap">
                                <table class="reservation-table">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Email</th>
                                            <th>Floor Number</th>
                                            <th>Time In</th>
                                            <th>Time Out</th>
                                            <th>Date Reserved</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($pendingReservations)): ?>
                                            <tr>
                                                <td colspan="7" class="no-data">No pending reservations</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($pendingReservations as $reservation): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($reservation['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                    <td><?php echo htmlspecialchars($reservation['email'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                    <td><?php echo htmlspecialchars($reservation['floor_number'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                    <td><?php echo htmlspecialchars($reservation['time_in'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                    <td><?php echo htmlspecialchars($reservation['time_out'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                    <td><?php echo htmlspecialchars($reservation['date_reserved'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                    <td>
                                                        <div class="action-buttons">
                                                            <button class="btn-confirm" onclick="confirmReservation(<?php echo $reservation['id']; ?>)">Confirm</button>
                                                            <button class="btn-delete" onclick="deleteReservation(<?php echo $reservation['id']; ?>)">Delete</button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Accepted Reservations -->
                    <div class="col">
                        <div class="reservation-panel">
                            <div class="panel-header">
                                <span class="panel-icon">✅</span>
                                <span>Accepted Reservations</span>
                                <span class="status-badge status-accepted"><?php echo count($acceptedReservations); ?> Accepted</span>
                            </div>
                            <div class="table-wrap">
                                <table class="reservation-table">
                                    <thead>
                                        <tr>
                                            <th>Status</th>
                                            <th>Name</th>
                                            <th>Email</th>
                                            <th>Floor Number</th>
                                            <th>Time In</th>
                                            <th>Time Out</th>
                                            <th>Date Reserved</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($acceptedReservations)): ?>
                                            <tr>
                                                <td colspan="7" class="no-data">No accepted reservations</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($acceptedReservations as $reservation): ?>
                                                <tr>
                                                    <td>
                                                        <div style="display:flex; align-items:center;">
                                                            <span class="status-indicator status-<?php echo $reservation['status']; ?>"></span>
                                                            <span class="status-text"><?php echo ucfirst($reservation['status']); ?></span>
                                                        </div>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($reservation['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                    <td><?php echo htmlspecialchars($reservation['email'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                    <td><?php echo htmlspecialchars($reservation['floor_number'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                    <td><?php echo htmlspecialchars($reservation['time_in'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                    <td><?php echo htmlspecialchars($reservation['time_out'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                    <td><?php echo htmlspecialchars($reservation['date_reserved'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <footer class="dash-footer">
                    <span>© 2025 Admin Module</span>
                </footer>
            </div>
        </main>
    </div>

    <script>
    (function() {
        var toggles = document.querySelectorAll('.nav-item.has-children');
        for (var i = 0; i < toggles.length; i++) {
            toggles[i].addEventListener('click', function() {
                var key = this.getAttribute('data-toggle');
                var group = document.getElementById('group-' + key);
                if (!group) return;
                var isOpen = group.classList.contains('open');
                group.classList.toggle('open', !isOpen);
                this.querySelector('.nav-caret').textContent = !isOpen ? '▴' : '▾';
            });
        }
    })();

    // Reservation action functions
    function confirmReservation(reservationId) {
        if (confirm('Are you sure you want to confirm this reservation?')) {
            // Here you would typically make an AJAX call to update the database
            console.log('Confirming reservation ID:', reservationId);
            alert('Reservation confirmed successfully!');
            // You can add AJAX call here to update the database
            // location.reload(); // Reload page to show updated data
        }
    }

    function deleteReservation(reservationId) {
        if (confirm('Are you sure you want to delete this reservation? This action cannot be undone.')) {
            // Here you would typically make an AJAX call to delete from database
            console.log('Deleting reservation ID:', reservationId);
            alert('Reservation deleted successfully!');
            // You can add AJAX call here to delete from database
            // location.reload(); // Reload page to show updated data
        }
    }
    </script>
</body>
</html>
