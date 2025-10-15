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
    <link rel="stylesheet" href="CSS_files/theme.css">
    <style>
        /* Custom dashboard-specific styles */
        .graph-placeholder {
            background: linear-gradient(135deg, var(--bg-tertiary) 0%, var(--bg-secondary) 100%);
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-primary);
            position: relative;
            overflow: hidden;
        }
        
        .graph-placeholder::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, transparent 49%, var(--border-primary) 50%, transparent 51%);
            background-size: 20px 20px;
            opacity: 0.1;
        }
        
        .dash-footer {
            text-align: center;
            font-size: 14px;
            color: var(--text-muted);
            margin: var(--spacing-2xl) 0 var(--spacing-lg);
            letter-spacing: 0.5px;
            opacity: 0.8;
        }
        
        /* Chart.js theme integration */
        .chart-container {
            position: relative;
            height: 300px;
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: var(--spacing-lg);
            border: 1px solid var(--border-primary);
        }
        
        /* Revenue chart specific styling */
        .revenue-chart {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: var(--spacing-xl);
            border: 1px solid var(--border-primary);
            box-shadow: var(--shadow-md);
        }
        
        /* Performance overview styling */
        .performance-overview {
            display: flex;
            flex-wrap: wrap;
            gap: var(--spacing-xl);
            align-items: flex-start;
        }
        
        .chart-section {
            flex: 1;
            min-width: 350px;
        }
        
        .chart-placeholder {
            height: 250px;
            background: var(--bg-tertiary);
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-muted);
            font-style: italic;
            border: 2px dashed var(--border-secondary);
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .performance-overview {
                flex-direction: column;
            }
            
            .chart-section {
                min-width: 100%;
            }
        }
    </style>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
    <div class="dashboard-layout">
        <aside class="sidebar">
            <div class="sidebar-brand">
                <span class="brand-icon">🏨</span>
                <span class="brand-text">Hotel Admin</span>
            </div>
            <nav class="sidebar-nav">
                <button class="nav-item active" data-section="dashboard">
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
                <header class="main-header">
                    <h1 class="page-title">Hotel Dashboard</h1>
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
                        <span style="font-weight:400;font-size:13px;color:var(--text-muted);">(Current Week: Monday - Sunday)</span>
                    </div>
                    <div class="performance-overview">
                        <div class="chart-section">
                            <div class="graph-placeholder" style="height:210px;display:flex;align-items:end;gap:18px;padding:35px 32px 12px 38px;position:relative;">
                                <div style="position:absolute;left:0;top:30px;bottom:18px;width:36px;display:flex;flex-direction:column;justify-content:space-between;z-index:2;">
                                    <?php for ($g = 4; $g >= 0; $g--): 
                                        $val = round($maxRevenue * $g / 4);
                                    ?>
                                        <div style="height:39px;display:flex;align-items:center;">
                                            <span style="font-size:12px;color:var(--text-muted);"><?php echo '₱' . number_format($val); ?></span>
                                        </div>
                                    <?php endfor; ?>
                                </div>
                                <div style="position:absolute;left:34px;right:20px;top:38px;bottom:18px;pointer-events:none;z-index:1;">
                                    <?php for ($g = 0; $g < 5; $g++): ?>
                                        <div style="position:absolute;left:0;right:0;top:<?php echo ($g*25); ?>%;height:1px;background:var(--border-primary);opacity:0.3;"></div>
                                    <?php endfor; ?>
                                </div>
                                <div style="display:flex;align-items:end;height:170px;width:100%;z-index:3;gap:18px;margin-left:32px;">
                                    <?php foreach ($dailyRevenue as $i => $rev): 
                                        $height = $maxRevenue ? intval($rev / $maxRevenue * 100) : 0;
                                    ?>
                                    <div style="display:flex;flex-direction:column;align-items:center;width:28px;">
                                        <div style="width:100%; height:<?php echo max($height,5); ?>%; background:linear-gradient(120deg,#3b82f6 60%,#8b5cf6 110%); border-radius:6px 6px 2px 2px; margin-bottom:3px; transition:height .3s; position:relative;" title="<?php echo 'Revenue: ₱' . number_format($rev); ?>">
                                            <?php if ($i === 6): ?>
                                                <span style="position:absolute;bottom:104%;right:-22px;display:inline-block;background:var(--bg-card);color:var(--text-primary);font-size:12px;border-radius:3px;padding:1.5px 6px;font-weight:600;box-shadow:var(--shadow-sm);">
                                                    ₱<?php echo number_format($rev); ?>
                                                </span>
                                            <?php endif; ?>                            
                                        </div>
                                        <span style="font-size:12px;color:var(--text-secondary);text-align:center;white-space:nowrap;margin-top:2px;">
                                            <?php echo date('D', strtotime($dates[$i])); ?>
                                        </span>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        <div class="chart-section">
                            <div class="chart-container">
                                <canvas id="revenueChart" style="width:100%;height:220px;background:transparent;"></canvas>
                            </div>
                        </div>
                    </div>
                </section>
                <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
                <script>
                (function() {
                    var ctx = document.getElementById('revenueChart').getContext('2d');
                    var labels = <?php echo json_encode(array_map(function($d){return date('D', strtotime($d));}, $dates)); ?>;
                    var data = <?php echo json_encode(array_map('intval', $dailyRevenue)); ?>;
                    
                    // Get current theme
                    var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
                    var gridColor = isDark ? "rgba(148, 163, 184, 0.2)" : "rgba(180,191,203,0.13)";
                    var textColor = isDark ? "#cbd5e1" : "#475569";
                    var bgColor = isDark ? "rgba(59, 130, 246, 0.1)" : "rgba(58,178,234,0.09)";
                    
                    var chart = new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: labels,
                            datasets: [{
                                label: 'Daily Revenue',
                                data: data,
                                borderColor: '#3b82f6',
                                backgroundColor: bgColor,
                                fill: true,
                                pointRadius: 5,
                                pointBackgroundColor: '#3b82f6',
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
                                    backgroundColor: isDark ? '#1e293b' : '#fff',
                                    titleColor: isDark ? '#f8fafc' : '#0f172a',
                                    bodyColor: isDark ? '#cbd5e1' : '#475569',
                                    borderColor: '#3b82f6'
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
                    
                    // Update chart when theme changes
                    window.addEventListener('themeChanged', function(e) {
                        var isDark = e.detail.theme === 'dark';
                        chart.options.plugins.tooltip.backgroundColor = isDark ? '#1e293b' : '#fff';
                        chart.options.plugins.tooltip.titleColor = isDark ? '#f8fafc' : '#0f172a';
                        chart.options.plugins.tooltip.bodyColor = isDark ? '#cbd5e1' : '#475569';
                        chart.options.scales.y.ticks.color = isDark ? '#cbd5e1' : '#475569';
                        chart.options.scales.x.ticks.color = isDark ? '#cbd5e1' : '#475569';
                        chart.options.scales.y.grid.color = isDark ? "rgba(148, 163, 184, 0.2)" : "rgba(180,191,203,0.13)";
                        chart.update();
                    });
                })();
                </script>

                <section class="panel">
                    <div class="panel-header">Recent Activity</div>
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
                                        <td colspan="3" style="color:var(--text-muted);">No recent activity.</td>
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
                    <span>© 2025 Hotel Management System</span>
                </footer>
            </div>
        </main>
    </div>

    <script src="JS_files/theme.js"></script>
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