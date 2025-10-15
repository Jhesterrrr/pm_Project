<?php
session_start();
if (!isset($_SESSION['Employee_ID'])) {
    header('Location: Admin_Login.php');
    exit;
}
$Department = isset($_SESSION['Department']) ? $_SESSION['Department'] : 'Admin';
$Position = isset($_SESSION['Position']) ? $_SESSION['Position'] : 'Admin';
require_once __DIR__ . '/db_connection.php';

// (Removed dark mode handling)


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

// Total active users (non-archived)
$totalActiveUsers = 0;
$csql = "SELECT COUNT(*) AS cnt FROM accounts WHERE (Archived IS NULL OR Archived = 0)";
$cres = $mysqli->query($csql);
if ($cres && $crow = $cres->fetch_assoc()) { $totalActiveUsers = (int)$crow['cnt']; }

// Recent audit logs (join to resolve user name)
$recentAudits = [];
$alog = $mysqli->query(
    "SELECT l.Timestamp, COALESCE(a.Account_Owner, CONCAT('Emp#', l.Employee_ID)) AS UserName, 
            CONCAT(l.Module, ' - ', l.Action) AS ActionText
     FROM audit_log l 
     LEFT JOIN accounts a ON a.Employee_ID = l.Employee_ID
     ORDER BY l.Timestamp DESC, l.Log_ID DESC
     LIMIT 10"
);
if ($alog) {
    while ($r = $alog->fetch_assoc()) { $recentAudits[] = $r; }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hotel Dashboard</title>
    <style>
        :root {
            --main-bg:#f6f7fb;
            --main-text:#243246;
            --sidebar-bg:#e7eaf3;
            --sidebar-brand-color:#192145;
            --nav-item-color:#2266aa;
            --nav-item-hover-bg:#cff1ff;
            --nav-item-hover-color:#1a2b41;
            --panel-bg:#fff;
            --stat-card-bg:#f6f7fb;
            --stat-card-shadow:rgba(180,190,238,0.13);
            --table-header-bg:#eff6fe;
            --table-row-bg:#f6faff;
            --table-row-alt-bg:#eaf4ff;
            --graph-bg: linear-gradient(to bottom,#f2f6fb 0%,#d7e6fa 90%);
            --audit-table-text-color:#244266;
            --border-card: #bcc7df;
            --shadow-card: 0 2px 12px rgba(170,190,230,0.11);
        }
        body, html { background:var(--main-bg); color:var(--main-text); font-family:'Inter',sans-serif; margin:0; padding:0; }
        .dashboard-layout { display: flex; min-height: 100vh; background: var(--main-bg);}
        .sidebar {
            width: 235px;
            background: var(--sidebar-bg);
            padding: 26px 0 16px 0;
            color: var(--sidebar-brand-color);
            display: flex;
            flex-direction: column;
            box-shadow: 2px 0 18px #0001;
        }
        .sidebar-brand { display: flex; align-items: center; gap: 12px; font-size: 21px; font-weight: 700; margin-bottom: 28px; padding: 0 23px;}
        .brand-icon { font-size: 26px;}
        .sidebar-nav { display: flex; flex-direction: column; gap: 6px;}
        .nav-item {
            background: none;
            border: none;
            color: var(--nav-item-color);
            text-align: left;
            font-size: 16px;
            padding: 12px 23px;
            cursor: pointer;
            border-radius: 7px;
            transition: background .14s, color .13s;
            display: flex;
            align-items: center;
            gap: 9px;
        }
        .nav-item:hover, .nav-item.active {
            background: var(--nav-item-hover-bg);
            color: var(--nav-item-hover-color);
        }
        .nav-group { margin-bottom: 5px;}
        .nav-item.has-children { justify-content: space-between; }
        .nav-caret { font-size: 13px; margin-left: auto;}
        .nav-children { display: none; flex-direction:column; gap:2px; padding-left:19px;}
        .nav-children.open { display: flex;}
        .nav-child {
            color: var(--nav-item-color);
            text-decoration:none;
            font-size:15px;
            padding:7px 0;
            border-radius:5px;
            transition: background .15s, color .13s;
        }
        #group-users .nav-child:hover, #group-customers .nav-child:hover {
            background: var(--nav-item-hover-bg);
            color: var(--nav-item-hover-color);
            text-decoration: none;
        }
        .main { flex: 1; padding: 40px 0 0 0; background: var(--main-bg); min-height: 100vh; color: var(--main-text);}
        .ua-container { max-width: 1100px; margin: 20px auto; padding: 0 16px;}
        .ua-title { font-size: 22px; font-weight: 700; color: var(--main-text);}
        .main-header, .panel-header, .stat-label, .user-meta, .user-name, .page-title { color: var(--main-text);}
        .two-col { display: flex; flex-wrap: wrap; gap: 22px; margin-top: 18px;}
        .col { flex:1; min-width:320px;}
        .panel {
            background: var(--panel-bg);
            border-radius: 13px;
            box-shadow: var(--shadow-card);
            margin: 21px 0 24px 0;
            padding: 25px 28px;
            border:1px solid var(--border-card);
        }
        .panel-header {
            font-size:18px;
            font-weight:600;
            margin-bottom:13px;
            color:var(--main-text);
            letter-spacing:0.2px;
        }
        .cards {
            display: flex;
            flex-wrap:wrap;
            gap:25px;
            margin-bottom:24px;
            margin-top:14px;
        }
        .stat-card {
            background: var(--stat-card-bg);
            border-radius: 12px;
            box-shadow: var(--shadow-card);
            flex:1;
            min-width:190px;
            max-width:242px;
            padding: 27px 22px;
            display: flex;
            flex-direction: column;
            gap:14px;
            border: 1.5px solid var(--border-card);
            transition:box-shadow .19s, background .15s;
        }
        .stat-top { display: flex; align-items: center; gap: 7px; }
        .stat-icon { font-size: 29px;}
        .stat-label { font-size:16px; font-weight:500;}
        .stat-value { font-size:30px; font-weight:800; margin-top:13px; color: #5adba9;}
        .stat-card:hover {
            box-shadow: 0 4px 24px rgba(112,152,205,0.14);
            background: #e6f3fa;
        }
        .user-area { display: flex; align-items: center; gap:18px;}
        .user-info { display: flex; align-items: center; gap:10px;}
        .avatar {
            width:43px; height:43px; border-radius:50%; object-fit:cover; background:#cfdfff; border:2px solid #aebae5;
        }
        .user-texts { display: flex; flex-direction: column; gap:1.5px;}
        .user-name { font-size:15px; font-weight:600;}
        .user-meta { font-size:13px; color:#4e7cb7;}
        .btn-logout {
            color: #ff6b6b;
            background: none;
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 600;
            margin-left: 14px;
            font-size: 15.5px;
            transition: background .18s, color .13s;
            border: 1.5px solid #b1bedf;
        }
        .btn-logout:hover {
            background: #ffe2e2;
            color: #ff3838;
            border-color: #ffb1b1;
        }
        /* Removed darkmode toggle refinement and darkmode styles */
        .graph-placeholder {
            background: var(--graph-bg);
            border-radius: 13px;
            border:1.5px solid var(--border-card);
        }
        /* Table custom styling for light mode */
        .table-wrap { overflow-x:auto; border-radius:14px;}
        .audit-table {
            width:100%;
            border-spacing:0;
            background: none;
            margin-top:10px;
            box-shadow: 0 1px 9px #b4cae2;
        }
        .audit-table th, .audit-table td {
            padding: 13px 18px;
            text-align: left;
        }
        .audit-table th {
            background: var(--table-header-bg);
            font-size:15px;
            font-weight:600;
            color: #2b7fc8;
            border-bottom:1.5px solid #ccdfff;
        }
        .audit-table td {
            color: var(--audit-table-text-color);
            font-size:14px;
        }
        .audit-table tr:nth-child(even) td { background: var(--table-row-bg);}
        .audit-table tr:nth-child(odd) td { background: var(--table-row-alt-bg);}
        .audit-table tr:hover td { background: #e3f3ff;}
        .dash-footer {
            text-align: center;
            font-size: 15px;
            color: #8394c7;
            margin: 50px 0 9px;
            letter-spacing:0.25px;
            opacity:0.92;
        }
        @media (max-width: 1050px) {
          .cards { flex-direction: column;}
          .stat-card { max-width:100%; }
        }
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
                <button class="nav-item active" data-section="dashboard" >
                    <span class="nav-icon">🏠</span>
                    <span class="nav-label">Dashboard</span>
                </button>
                <div class="nav-group">
                    <button class="nav-item has-children" data-toggle="users" >
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

        <?php
            $dates = [];
            $mon = strtotime('monday this week');
            for ($i = 0; $i < 7; $i++) {
                $dates[] = date('Y-m-d', strtotime("+{$i} days", $mon));
            }
            $dailyRevenue = [];
            $totalRevenueForWeek = 0;
            for ($i = 0; $i < 7; $i++) {
                $revenue = rand(50000, 230000);
                $dailyRevenue[] = $revenue;
                $totalRevenueForWeek += $revenue;
            }
            $averageDailyRevenue = round($totalRevenueForWeek / 7);
            $maxRevenue = max($dailyRevenue) ?: 1;
        ?>

        <main class="main">
            <div class="ua-container">
            <header class="main-header" style="display:flex;align-items:baseline;justify-content:space-between;">
                <h1 class="page-title" style="font-weight:800;">Hotel Dashboard</h1>
                <div style="display:flex;align-items:center;gap:17px;">
                    <!-- Dark mode toggle removed -->
                    <div class="user-area">
                        <div class="user-info">
                            <img class="avatar" src="https://ui-avatars.com/api/?name=<?php echo urlencode($currentUserName ?: ($Position)); ?>&background=cfdfff&color=243246" alt="Profile">
                            <div class="user-texts">
                                <div class="user-name"><?php echo htmlspecialchars($currentUserName ?: 'Admin User', ENT_QUOTES, 'UTF-8'); ?></div>
                                <div class="user-meta"><?php echo htmlspecialchars($Position . ' • ' . $Department, ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>
                        </div>
                        <a class="btn-logout" href="logout.php" title="Logout">Logout</a>
                    </div>
                </div>
            </header>

            <section class="cards">
                <div class="stat-card">
                    <div class="stat-top">
                        <span class="stat-icon">👥</span>
                        <span class="stat-label">Total Users</span>
                    </div>
                    <div class="stat-value"><?php echo number_format($totalActiveUsers); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-top">
                        <span class="stat-icon">⚡</span>
                        <span class="stat-label">Active Sessions</span>
                    </div>
                    <div class="stat-value">1</div>
                </div>
                <div class="stat-card">
                    <div class="stat-top">
                        <span class="stat-icon">💵</span>
                        <span class="stat-label">Daily Revenue</span>
                    </div>
                    <div class="stat-value"><?php echo '₱' . number_format($averageDailyRevenue); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-top">
                        <span class="stat-icon">🛎️</span>
                        <span class="stat-label">Rooms Occupied</span>
                    </div>
                    <div class="stat-value" id="stat-rooms-occupied" style="transition:all .3s">0</div>
                </div>
            </section>

            <script>
            if (typeof roomsData === "undefined") {
                var roomsData = [
                    { Status: "Available" },
                    { Status: "Occupied" }, { Status: "Occupied" },
                    { Status: "Overtime" }, { Status: "Occupied" },
                    { Status: "Available" }, { Status: "Overtime" }
                ];
            }
            function updateRoomsOccupiedCard() {
                var rooms = (typeof roomsData !== "undefined" ? roomsData : []);
                var count = 0;
                for (var i = 0; i < rooms.length; ++i) {
                    var s = String(rooms[i].Status).trim().toLowerCase();
                    if (s === "occupied" || s === "overtime")
                        count++;
                }
                var el = document.getElementById("stat-rooms-occupied");
                if (el) {
                    el.textContent = count;
                }
            }
            updateRoomsOccupiedCard();
            </script>
           
            <section class="panel">
                <div class="panel-header">
                    Performance Overview 
                    <span style="font-weight:400;font-size:13px;color:#7cdbaa;">(Current Week: Monday - Sunday)</span>
                </div>
                <div style="display:flex;flex-wrap:wrap;gap:32px;">
                    <div class="graph-placeholder" style="flex:1;min-width:350px;height:210px;display:flex;align-items:end;gap:18px;padding:35px 32px 12px 38px;position:relative;">
                        <div style="position:absolute;left:0;top:30px;bottom:18px;width:36px;display:flex;flex-direction:column;justify-content:space-between;z-index:2;">
                            <?php for ($g = 4; $g >= 0; $g--): 
                                $val = round($maxRevenue * $g / 4);
                            ?>
                                <div style="height:39px;display:flex;align-items:center;">
                                    <span style="font-size:12px;color:#43afc7;"><?php echo '₱' . number_format($val); ?></span>
                                </div>
                            <?php endfor; ?>
                        </div>
                        <div style="position:absolute;left:34px;right:20px;top:38px;bottom:18px;pointer-events:none;z-index:1;">
                            <?php for ($g = 0; $g < 5; $g++): ?>
                                <div style="position:absolute;left:0;right:0;top:<?php echo ($g*25); ?>%;height:1px;background:rgba(89,226,235,0.07);"></div>
                            <?php endfor; ?>
                        </div>
                        <div style="display:flex;align-items:end;height:170px;width:100%;z-index:3;gap:18px;margin-left:32px;">
                            <?php foreach ($dailyRevenue as $i => $rev): 
                                $height = $maxRevenue ? intval($rev / $maxRevenue * 100) : 0;
                            ?>
                            <div style="display:flex;flex-direction:column;align-items:center;width:28px;">
                                <div style="width:100%; height:<?php echo max($height,5); ?>%; background:linear-gradient(120deg,#4dfca1 60%,#47b8fd 110%); border-radius:6px 6px 2px 2px; margin-bottom:3px; transition:height .3s; position:relative;" title="<?php echo 'Revenue: ₱' . number_format($rev); ?>">
                                    <?php if ($i === 6): ?>
                                        <span style="position:absolute;bottom:104%;right:-22px;display:inline-block;background:#fff;color:#1f8652;font-size:12px;border-radius:3px;padding:1.5px 6px;font-weight:600;box-shadow:0 1px 4px #0001;">
                                            ₱<?php echo number_format($rev); ?>
                                        </span>
                                    <?php endif; ?>                            
                                </div>
                                <span style="font-size:12px;color:#1574af;text-align:center;white-space:nowrap;margin-top:2px;">
                                    <?php echo date('D', strtotime($dates[$i])); ?>
                                </span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div style="flex:1;min-width:350px;max-width:460px;padding:12px 8px 0 8px;">
                        <canvas id="revenueChart" style="width:100%;height:220px;background:transparent;"></canvas>
                    </div>
                </div>
            </section>
            <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
            <script>
            (function() {
                var ctx = document.getElementById('revenueChart').getContext('2d');
                var labels = <?php echo json_encode(array_map(function($d){return date('D', strtotime($d));}, $dates)); ?>;
                var data = <?php echo json_encode(array_map('intval', $dailyRevenue)); ?>;
                // Always light mode
                var gridColor = "rgba(180,191,203,0.13)";
                var textColor = "#2266aa";
                var chart = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'Daily Revenue',
                            data: data,
                            borderColor: '#30eb91',
                            backgroundColor: 'rgba(58,178,234,0.09)',
                            fill: true,
                            pointRadius: 5,
                            pointBackgroundColor: '#49bafd',
                            pointBorderWidth: 2,
                            borderWidth: 3,
                            tension: 0.38
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {display: false},
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        return 'Revenue: ₱' + context.parsed.y.toLocaleString();
                                    }
                                },
                                backgroundColor:'#fff',
                                titleColor:'#0b2738',
                                bodyColor:'#047',
                                borderColor:'#46dad6'
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                suggestedMax: <?php echo ceil($maxRevenue/1000)*1000; ?>,
                                ticks: {
                                    color: textColor,
                                    callback: function(val) {return '₱' + val.toLocaleString();}
                                },
                                grid: { color: gridColor, drawTicks: false }
                            },
                            x: {
                                ticks:{color:textColor},
                                grid: {display: false}
                            }
                        }
                    }
                });
            })();
            </script>

            <section class="panel">
                <div class="panel-header">Audit Log</div>
                <div class="table-wrap">
                    <table class="audit-table">
                        <thead>
                            <tr>
                                <th>Timestamp</th>
                                <th>User</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentAudits)): ?>
                                <tr>
                                    <td colspan="3" style="color:#98cfe7;">No recent activity.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recentAudits as $log): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($log['Timestamp'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($log['UserName'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($log['ActionText'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
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
