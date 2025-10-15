<?php
session_start();
if (!isset($_SESSION['Employee_ID'])) { header('Location: Admin_Login.php'); exit; }
require_once __DIR__ . '/db_connection.php';

// Fetch all employees (include archived as requested: display all Employee / Accounts)
$employees = [];
$er = $mysqli->query("SELECT Employee_ID, COALESCE(Account_Owner, CONCAT('Emp#', Employee_ID)) AS Account_Owner FROM accounts ORDER BY Account_Owner ASC, Employee_ID ASC");
if ($er) { while ($row = $er->fetch_assoc()) { $employees[] = $row; } }

// Fetch distinct departments for Company Record
$departments = [];
$dr = $mysqli->query("SELECT DISTINCT Department FROM accounts WHERE Department IS NOT NULL AND TRIM(Department) <> '' ORDER BY Department ASC");
if ($dr) { while ($row = $dr->fetch_assoc()) { $departments[] = $row['Department']; } }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Legal / Documents - Hotel Admin</title>
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
        /* Remove underline hover effect for User Accounts & User Status & Permissions */
        #group-users .nav-child:hover { color:#fff; text-decoration:none; }
        .main { flex:1; padding:40px 0 0 0; background:#f9fafb; min-height:100vh; }
        .ua-container { max-width: 1100px; margin: 20px auto; padding: 0 16px; }
        .ua-title { font-size: 22px; font-weight: 700; color:#0f172a; }
        .ua-back { display:inline-flex; align-items:center; gap:6px; padding:8px 12px; border:1px solid #2563eb; color:#2563eb; border-radius:8px; text-decoration:none; }
        .ua-back:hover { background:#2563eb; color:#fff; }
        .two-col { display:flex; flex-wrap:wrap; gap:22px; margin-top:18px; }
        .col { flex:1; min-width:320px; }
        /* Clickable rows */
        .audit-table tbody tr[data-clickable] { cursor: pointer; }
        .audit-table tbody tr[data-clickable]:hover { background:#f1f5f9; }
        /* Small control bar */
        .mini-controls { display:flex; align-items:center; gap:8px; margin:10px 0 4px; }
        .mini-input { padding:7px 10px; border:1px solid #cbd5e1; border-radius:8px; font-size:14px; width:180px; }
        .mini-btn { padding:7px 12px; border:none; border-radius:8px; background:#2563eb; color:#fff; font-weight:600; cursor:pointer; }
        .mini-btn.secondary { background:#64748b; }
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
                <button class="nav-item active">
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
                <div style="display:flex; align-items:center; justify-content:space-between;">
                    <div class="ua-title">Legal / Documents</div>
                    <a href="Admin_Dash.php" class="ua-back">← Back to Dashboard</a>
                </div>

                <div class="two-col">
                    <section class="panel col">
                        <div class="panel-header">Employee List </div>
                        <div class="mini-controls">
                            <input id="empIdFilter" class="mini-input" type="text" placeholder="Filter by Employee ID">
                            <button id="applyEmpFilter" class="mini-btn">Filter</button>
                            <button id="resetEmpFilter" class="mini-btn secondary">Refresh</button>
                        </div>
                        <div class="table-wrap">
                            <table class="audit-table">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th style="width:140px;">Employee ID</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($employees)): ?>
                                        <tr><td colspan="2" style="color:#6b7280;">No employees found.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($employees as $emp): ?>
                                            <tr data-clickable data-emp-id="<?php echo (int)$emp['Employee_ID']; ?>">
                                                <td><?php echo htmlspecialchars($emp['Account_Owner'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td><?php echo (int)$emp['Employee_ID']; ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <section class="panel col">
                        <div class="panel-header">Company Record </div>
                        <div class="table-wrap">
                            <table class="audit-table">
                                <thead>
                                    <tr>
                                        <th>Department Name</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($departments)): ?>
                                        <tr><td style="color:#6b7280;">No departments found.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($departments as $dept): ?>
                                            <tr data-clickable data-dept-name="<?php echo htmlspecialchars($dept, ENT_QUOTES, 'UTF-8'); ?>">
                                                <td><?php echo htmlspecialchars($dept, ENT_QUOTES, 'UTF-8'); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </section>
                </div>
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

    // Employee ID filtering and row click handlers
    (function() {
        var input = document.getElementById('empIdFilter');
        var btnApply = document.getElementById('applyEmpFilter');
        var btnReset = document.getElementById('resetEmpFilter');
        var rows = Array.prototype.slice.call(document.querySelectorAll('section.panel .audit-table tbody tr[data-emp-id]'));

        function applyFilter() {
            var q = (input.value || '').trim();
            rows.forEach(function(r){
                var id = String(r.getAttribute('data-emp-id') || '');
                var show = q === '' ? true : id.indexOf(q) !== -1;
                r.style.display = show ? '' : 'none';
            });
        }

        if (btnApply) btnApply.addEventListener('click', applyFilter);
        if (input) input.addEventListener('keydown', function(e){ if (e.key === 'Enter') { e.preventDefault(); applyFilter(); }});
        if (btnReset) btnReset.addEventListener('click', function(){ input.value=''; applyFilter(); });

        // Click to navigate for employee rows
        rows.forEach(function(r){
            r.addEventListener('click', function(){
                var id = r.getAttribute('data-emp-id');
                if (!id) return;
                window.location.href = 'File_Manager.php?employee_id=' + encodeURIComponent(id);
            });
        });

        // Click to navigate for department/company rows
        var deptRows = Array.prototype.slice.call(document.querySelectorAll('section.panel .audit-table tbody tr[data-dept-name]'));
        deptRows.forEach(function(r){
            r.addEventListener('click', function(){
                var d = r.getAttribute('data-dept-name');
                if (!d) return;
                window.location.href = 'File_Manager.php?company=' + encodeURIComponent(d);
            });
        });
    })();
    </script>
</body>
</html>
