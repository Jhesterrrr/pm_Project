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
    if ($res) { while ($r = $res->fetch_assoc()) { $logs[] = $r; } }<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit Logs</title>
    <link rel="stylesheet" href="CSS_files/theme.css">
    <style>
        /* Audit Logs specific styles */
        .filter-bar { 
            background: var(--bg-tertiary); 
            border: 1px solid var(--border-primary); 
            border-radius: var(--radius-lg); 
            padding: var(--spacing-lg); 
            margin-bottom: var(--spacing-lg); 
            display: flex; 
            gap: var(--spacing-lg); 
            align-items: end; 
            flex-wrap: wrap; 
        }
        .filter-group { 
            display: flex; 
            flex-direction: column; 
            gap: var(--spacing-sm); 
            min-width: 150px;
        }
        .filter-group label { 
            font-size: 13px; 
            font-weight: 600;
            color: var(--text-secondary); 
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .filter-input { 
            padding: var(--spacing-md); 
            border: 1px solid var(--border-secondary);
            border-radius: var(--radius-md);
            font-size: 14px;
            background: var(--bg-input);
            color: var(--text-primary);
            transition: all var(--transition-normal);
        }
        .filter-input:focus {
            outline: none;
            border-color: var(--border-focus);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        .filter-actions {
            display: flex;
            flex-direction: row;
            gap: var(--spacing-md);
            align-items: flex-end;
        }
    </style> align-items: flex-end;
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
                    <a class="btn btn-muted" href="Aud    <script src="JS_files/theme.js"></script>
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
    </script>           var caret = this.querySelector('.nav-caret');
                if (caret) caret.textContent = !isOpen ? '▴' : '▾';
            });
        }
    })();
    </script>
</body>
</html>
