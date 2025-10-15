<?php
session_start();
if (!isset($_SESSION['Employee_ID'])) {
    header('Location: Admin_Login.php');
    exit;
}
require_once __DIR__ . '/db_connection.php';

// Filters and limit
$fromDate = isset($_GET['from']) ? trim($_GET['from']) : '';
$toDate   = isset($_GET['to']) ? trim($_GET['to']) : '';
$limit    = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
if ($limit <= 0) { $limit = 100; }
if ($limit > 10000) { $limit = 10000; }

// Fetch audit logs with optional date filter and limit
$logs = [];
$sql = "SELECT 
        COALESCE(a.Account_Owner, CONCAT('Emp#', l.Employee_ID)) AS Account_Owner,
        l.Employee_ID,
        l.Module,
        l.Details,
        l.Timestamp
     FROM audit_log l
     LEFT JOIN accounts a ON a.Employee_ID = l.Employee_ID";
$types = '';
$binds = [];
$where = [];
if ($fromDate !== '' && $toDate !== '') { $where[] = 'DATE(l.Timestamp) BETWEEN ? AND ?'; $types .= 'ss'; $binds[] = $fromDate; $binds[] = $toDate; }
else if ($fromDate !== '') { $where[] = 'DATE(l.Timestamp) >= ?'; $types .= 's'; $binds[] = $fromDate; }
else if ($toDate !== '') { $where[] = 'DATE(l.Timestamp) <= ?'; $types .= 's'; $binds[] = $toDate; }
if (!empty($where)) { $sql .= ' WHERE ' . implode(' AND ', $where); }
$sql .= ' ORDER BY l.Timestamp DESC, l.Log_ID DESC LIMIT ?';
$types .= 'i';
$binds[] = $limit;

$stmt = $mysqli->prepare($sql);
if ($stmt) {
    $stmt->bind_param($types, ...$binds);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res) { while ($r = $res->fetch_assoc()) { $logs[] = $r; } }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit Logs</title>
    <link rel="stylesheet" href="CSS_files/Admin_Dash.css">
    <style>
        .dashboard-layout { display:flex; min-height:100vh; background:#f9fafb; }
        .sidebar { width:235px; background:#1e293b; padding:26px 0 16px 0; color:#fff; display:flex; flex-direction:column; }
        .sidebar-brand { display:flex; align-items:center; gap:12px; font-size:21px; font-weight:700; margin-bottom:28px; padding:0 23px; }
        .brand-icon { font-size:26px; }
        .sidebar-nav { display:flex; flex-direction:column; gap:6px; }
        .nav-item { background:none; border:none; color:#dbeafe; text-align:left; font-size:16px; padding:12px 23px; cursor:pointer; border-radius:7px; transition:background .14s,color .13s; display:flex; align-items:center; gap:9px; text-decoration:none;}
        .nav-item:hover, .nav-item.active { background:#2563eb; color:#fff; text-decoration:none; }
        .nav-group { margin-bottom:5px; }
        .nav-item.has-children { justify-content:space-between; }
        .nav-caret { font-size:13px; margin-left:auto; }
        .nav-children { display:none; flex-direction:column; gap:2px; padding-left:19px;}
        .nav-children.open { display:flex; }
        .nav-child { color:#dbeafe; text-decoration:none; font-size:15px; padding:6px 0; transition:color .13s; }
        /* Remove underline for nav-child and its hover */
        #group-users .nav-child,
        #group-users .nav-child:hover,
        .nav-child,
        .nav-child:hover {
            text-decoration: none !important;
        }
        .main { flex:1; padding:40px 0 0 0; background:#f9fafb; min-height:100vh; }
        .ua-container { max-width: 1100px; margin: 20px auto; padding: 0 16px; }
        .ua-title { font-size: 22px; font-weight: 700; color:#0f172a; }
        .ua-back { display:inline-flex; align-items:center; gap:6px; padding:8px 12px; border:1px solid #2563eb; color:#2563eb; border-radius:8px; text-decoration:none; }
        .ua-back:hover { background:#2563eb; color:#fff; text-decoration:none; }
        .two-col { display:flex; flex-wrap:wrap; gap:22px; margin-top:18px; }
        .col { flex:1; min-width:320px; }
        .filter-bar { background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:12px; margin-bottom:12px; display:flex; gap:10px; align-items:end; flex-wrap:wrap; }
        .filter-group { display:flex; flex-direction:column; gap:6px; }
        .filter-group label { font-size:13px; color:#475569; }
        .filter-input { padding:8px 10px; border:1px solid #cbd5e1; border-radius:8px; font-size:14px; }
        .btn {
            padding:8px 12px;
            border:none;
            border-radius:8px;
            cursor:pointer;
            font-weight:600;
            text-decoration: none !important;
            background:#fff;
            color:#2563eb;
            transition: background 0.2s, color 0.2s, box-shadow 0.2s, transform 0.15s;
            box-shadow: 0 1px 3px rgba(30,41,59,0.08);
        }
        /* Button Colors */
        .btn-primary {
            background:#2563eb;
            color:#fff;
            border: none;
        }
        .btn-primary:hover, .btn-primary:focus {
            background:#1d4ed8;
            color:#fff;
            transform: translateY(-2px) scale(1.03);
            box-shadow: 0 6px 16px rgba(37,99,235,0.18);
        }
        /* Make btn-muted and btn (including Clear/Show More) white w/ color text */
        .btn-muted {
            background:#fff !important;
            color:#2563eb !important;
            border:1px solid #cbd5e1;
        }
        .btn-muted:hover, .btn-muted:focus {
            background: #f1f5f9 !important;
            color: #1d4ed8 !important;
            border-color: #2563eb;
            transform: translateY(-2px) scale(1.03);
            box-shadow: 0 4px 10px rgba(37,99,235,0.13);
        }
        /* Generic for all .btn: elevate and colorize on hover/focus */
        .btn:hover, .btn:focus {
            outline: none;
            text-decoration: none !important;
            box-shadow: 0 4px 18px rgba(37,99,235,0.14);
            transform: translateY(-1px) scale(1.01);
        }
        /* Remove underline from all a.btn in all states */
        a.btn, a.btn:visited, a.btn:hover, a.btn:active, a.btn:focus {
            text-decoration: none !important;
            color: inherit;
        }
        /* Stacked filter actions horizontally */
        .filter-actions {
            display: flex;
            flex-direction: row;
            gap: 8px;
            align-items: flex-end;
        }
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
                <button class="nav-item active">
                    <span class="nav-icon">📝</span>
                    <span class="nav-label">Audit / Activity Logs</span>
                </button>
                <button class="nav-item" onclick="window.location.href='Legal_mangement.php'">
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
            <header class="main-header" style="margin-bottom:8px;">
                <h1 class="page-title">Audit / Activity Logs</h1>
            </header>

            <section class="panel">
                <div class="panel-header">All Logs</div>
                <form method="GET" class="filter-bar">
                    <div class="filter-group">
                        <label for="from">From</label>
                        <input id="from" name="from" type="date" class="filter-input" value="<?php echo htmlspecialchars($fromDate); ?>">
                    </div>
                    <div class="filter-group">
                        <label for="to">To</label>
                        <input id="to" name="to" type="date" class="filter-input" value="<?php echo htmlspecialchars($toDate); ?>">
                    </div>
                    <input type="hidden" name="limit" value="<?php echo (int)$limit; ?>">
                    <div class="filter-actions">
                        <button type="submit" class="btn btn-primary">Apply</button>
                        <a class="btn btn-muted" href="Audit_Logs.php">Clear</a>
                    </div>
                </form>
                <div class="table-wrap">
                    <table class="audit-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Employee ID</th>
                                <th>Module</th>
                                <th>Details</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($logs)): ?>
                                <tr>
                                    <td colspan="5" style="color:#6b7280;">No logs found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($logs as $log): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($log['Account_Owner'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($log['Employee_ID'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($log['Module'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($log['Details'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($log['Timestamp'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div style="display:flex; justify-content:center; padding:12px;">
                    <?php 
                        $moreLimit = $limit + 100; 
                        $qs = http_build_query(array_filter([
                            'from' => $fromDate ?: null,
                            'to' => $toDate ?: null,
                            'limit' => $moreLimit
                        ]));
                    ?>
                    <a class="btn btn-muted" href="Audit_Logs.php<?php echo $qs ? ('?' . $qs) : ''; ?>">Show more</a>
                </div>
            </section>
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
                var caret = this.querySelector('.nav-caret');
                if (caret) caret.textContent = !isOpen ? '▴' : '▾';
            });
        }
    })();
    </script>
</body>
</html>
