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

// Function to randomize nights stayed from 1 - 14
function randomNightsStayed() {
    return rand(1, 14);
}

// Sample customer history data - completed reservations
$customerHistory = [
    [
        'id' => 1,
        'name' => 'Sarah Johnson',
        'email' => 'sarah.johnson@email.com',
        'room_floor' => 'Room 301 - 3rd Floor',
        'check_in' => '2025-01-10 14:00',
        'check_out' => '2025-01-12 11:00',
        'payment_cost' => 4500.00
    ],
    [
        'id' => 2,
        'name' => 'Michael Chen',
        'email' => 'michael.chen@email.com',
        'room_floor' => 'Room 205 - 2nd Floor',
        'check_in' => '2025-01-08 15:30',
        'check_out' => '2025-01-10 10:00',
        'payment_cost' => 3200.00
    ],
    [
        'id' => 3,
        'name' => 'Emily Rodriguez',
        'email' => 'emily.rodriguez@email.com',
        'room_floor' => 'Room 401 - 4th Floor',
        'check_in' => '2025-01-05 16:00',
        'check_out' => '2025-01-07 12:00',
        'payment_cost' => 2800.00
    ],
    [
        'id' => 4,
        'name' => 'David Thompson',
        'email' => 'david.thompson@email.com',
        'room_floor' => 'Room 102 - 1st Floor',
        'check_in' => '2025-01-03 13:00',
        'check_out' => '2025-01-05 09:30',
        'payment_cost' => 2400.00
    ],
    [
        'id' => 5,
        'name' => 'Lisa Anderson',
        'email' => 'lisa.anderson@email.com',
        'room_floor' => 'Room 503 - 5th Floor',
        'check_in' => '2024-12-28 14:30',
        'check_out' => '2024-12-30 11:15',
        'payment_cost' => 3600.00
    ],
    [
        'id' => 6,
        'name' => 'Robert Wilson',
        'email' => 'robert.wilson@email.com',
        'room_floor' => 'Room 306 - 3rd Floor',
        'check_in' => '2024-12-25 12:00',
        'check_out' => '2024-12-27 10:00',
        'payment_cost' => 4200.00
    ],
    [
        'id' => 7,
        'name' => 'Maria Garcia',
        'email' => 'maria.garcia@email.com',
        'room_floor' => 'Room 208 - 2nd Floor',
        'check_in' => '2024-12-22 15:45',
        'check_out' => '2024-12-24 11:30',
        'payment_cost' => 3100.00
    ],
    [
        'id' => 8,
        'name' => 'James Brown',
        'email' => 'james.brown@email.com',
        'room_floor' => 'Room 404 - 4th Floor',
        'check_in' => '2024-12-20 16:00',
        'check_out' => '2024-12-22 09:45',
        'payment_cost' => 2900.00
    ],
    [
        'id' => 9,
        'name' => 'Jennifer Davis',
        'email' => 'jennifer.davis@email.com',
        'room_floor' => 'Room 107 - 1st Floor',
        'check_in' => '2024-12-18 13:30',
        'check_out' => '2024-12-20 10:15',
        'payment_cost' => 2600.00
    ],
    [
        'id' => 10,
        'name' => 'Christopher Lee',
        'email' => 'christopher.lee@email.com',
        'room_floor' => 'Room 502 - 5th Floor',
        'check_in' => '2024-12-15 14:00',
        'check_out' => '2024-12-17 12:00',
        'payment_cost' => 3800.00
    ],
    [
        'id' => 11,
        'name' => 'Amanda Taylor',
        'email' => 'amanda.taylor@email.com',
        'room_floor' => 'Room 309 - 3rd Floor',
        'check_in' => '2024-12-12 15:00',
        'check_out' => '2024-12-14 11:45',
        'payment_cost' => 3300.00
    ],
    [
        'id' => 12,
        'name' => 'Kevin Martinez',
        'email' => 'kevin.martinez@email.com',
        'room_floor' => 'Room 201 - 2nd Floor',
        'check_in' => '2024-12-10 12:30',
        'check_out' => '2024-12-12 09:00',
        'payment_cost' => 2700.00
    ],
    [
        'id' => 13,
        'name' => 'Rachel White',
        'email' => 'rachel.white@email.com',
        'room_floor' => 'Room 405 - 4th Floor',
        'check_in' => '2024-12-08 16:30',
        'check_out' => '2024-12-10 10:30',
        'payment_cost' => 3000.00
    ],
    [
        'id' => 14,
        'name' => 'Daniel Clark',
        'email' => 'daniel.clark@email.com',
        'room_floor' => 'Room 103 - 1st Floor',
        'check_in' => '2024-12-05 14:15',
        'check_out' => '2024-12-07 11:00',
        'payment_cost' => 2500.00
    ],
    [
        'id' => 15,
        'name' => 'Michelle Lewis',
        'email' => 'michelle.lewis@email.com',
        'room_floor' => 'Room 504 - 5th Floor',
        'check_in' => '2024-12-03 13:45',
        'check_out' => '2024-12-05 12:15',
        'payment_cost' => 3900.00
    ]
];

// Calculate total revenue
$totalRevenue = array_sum(array_column($customerHistory, 'payment_cost'));
$totalCustomers = count($customerHistory);
$averageStay = $totalRevenue / $totalCustomers;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer History - Hotel Admin</title>
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
        .history-container { max-width: 1200px; margin: 20px auto; padding: 0 16px; }
        .history-title { font-size: 22px; font-weight: 700; color:#0f172a; margin-bottom: 20px; }
        .history-back { display:inline-flex; align-items:center; gap:6px; padding:8px 12px; border:1px solid #2563eb; color:#2563eb; border-radius:8px; text-decoration:none; margin-bottom: 20px; }
        .history-back:hover { background:#2563eb; color:#fff; }
        .stats-cards { display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:16px; margin-bottom:24px; }
        .stat-card { background:#ffffff; border-radius:12px; padding:20px; box-shadow:0 8px 24px rgba(0,0,0,0.06); }
        .stat-header { display:flex; align-items:center; gap:10px; margin-bottom:8px; }
        .stat-icon { font-size:20px; }
        .stat-label { color:#6b7280; font-size:14px; font-weight:500; }
        .stat-value { font-size:24px; font-weight:700; color:#0f172a; }
        .history-panel { background:#ffffff; border-radius:12px; padding:20px; box-shadow:0 8px 24px rgba(0,0,0,0.06); }
        .panel-header { font-weight:600; color:#0f172a; margin-bottom:15px; font-size:18px; display:flex; align-items:center; gap:8px; }
        .panel-icon { font-size:20px; }
        .table-wrap { overflow-x: auto; }
        .history-table { width:100%; border-collapse:separate; border-spacing:0; font-size:14px; }
        .history-table thead th { text-align:left; color:#6b7280; font-weight:600; padding:12px; border-bottom:2px solid #e5e7eb; background:#f8fafc; }
        .history-table tbody td { padding:12px; border-bottom:1px solid #f1f5f9; }
        .history-table tbody tr:nth-child(odd) { background:#fcfdff; }
        .history-table tbody tr:hover { background:#f8fafc; }
        .no-data { text-align:center; color:#6b7280; padding:40px; font-style:italic; }
        .payment-cost { font-weight:600; color:#059669; }
        .room-info { font-weight:500; color:#1f2937; }
        .date-time { font-size:13px; color:#6b7280; }
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
                        <a class="nav-child" href="Reservations.php">Reservations</a>
                        <a class="nav-child" href="customer_history.php" style="color:#fff;">Customer History</a>
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
            <div class="history-container">
                <header class="main-header">
                    <h1 class="history-title">Customer History</h1>
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

                <a href="Admin_Dash.php" class="history-back">
                    <span>←</span>
                    <span>Back to Dashboard</span>
                </a>

                <!-- Statistics Cards -->
                <div class="stats-cards">
                    <div class="stat-card">
                        <div class="stat-header">
                            <span class="stat-icon">👥</span>
                            <span class="stat-label">Total Customers</span>
                        </div>
                        <div class="stat-value"><?php echo number_format($totalCustomers); ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-header">
                            <span class="stat-icon">💰</span>
                            <span class="stat-label">Total Revenue</span>
                        </div>
                        <div class="stat-value">₱<?php echo number_format($totalRevenue, 2); ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-header">
                            <span class="stat-icon">📊</span>
                            <span class="stat-label">Average Stay</span>
                        </div>
                        <div class="stat-value">₱<?php echo number_format($averageStay, 2); ?></div>
                    </div>
                </div>

                <!-- Customer History Table -->
                <div class="history-panel">
                    <div class="panel-header">
                        <span class="panel-icon">📋</span>
                        <span>Completed Reservations</span>
                    </div>
                    <div class="table-wrap">
                        <table class="history-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Room & Floor</th>
                                    <th>Check In</th>
                                    <th>Check Out</th>
                                    <th>Nights Stayed</th>
                                    <th>Payment Cost</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($customerHistory)): ?>
                                    <tr>
                                        <td colspan="7" class="no-data">No customer history available</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($customerHistory as $customer): 
                                        $nightsStayed = randomNightsStayed();
                                    ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($customer['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo htmlspecialchars($customer['email'], ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><span class="room-info"><?php echo htmlspecialchars($customer['room_floor'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                                            <td><span class="date-time"><?php echo htmlspecialchars($customer['check_in'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                                            <td><span class="date-time"><?php echo htmlspecialchars($customer['check_out'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                                            <td><span class="room-info"><?php echo $nightsStayed; ?> night<?php echo $nightsStayed != 1 ? 's' : ''; ?></span></td>
                                            <td><span class="payment-cost">₱<?php echo number_format($customer['payment_cost'], 2); ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
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
    </script>
</body>
</html>
