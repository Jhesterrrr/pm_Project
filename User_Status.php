<?php
session_start();
require_once __DIR__ . '/db_connection.php';
if (!isset($_SESSION['Employee_ID'])) { header('Location: Admin_Login.php'); exit; }

// Ensure Status column exists
$mysqli->query("ALTER TABLE accounts ADD COLUMN IF NOT EXISTS Status ENUM('Active','On Leave','Terminated') NOT NULL DEFAULT 'Active'");

// Fetch accounts (include archived)
$accounts = [];
$res = $mysqli->query("SELECT Employee_ID, Account_Owner, Department, Position, IFNULL(Status,'Active') AS Status, IFNULL(Archived,0) AS Archived FROM accounts ORDER BY Employee_ID ASC");
if ($res) { while ($r = $res->fetch_assoc()) { $accounts[] = $r; } }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Status & Permissions</title>
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
        .col { flex:1; min-width:320px; } /* Match sidebar hover behavior with Legal_mangement.php */
    #group-users .nav-child:hover { color:#fff; text-decoration:none; }
    </style>
    <style>
    .ua-container { max-width: 1100px; margin: 20px auto; padding: 0 16px; }
    .ua-title { font-size: 22px; font-weight: 700; color:#0f172a; }
    .ua-back { display:inline-flex; align-items:center; gap:6px; padding:8px 12px; border:1px solid #2563eb; color:#2563eb; border-radius:8px; text-decoration:none; }
    .ua-back:hover { background:#2563eb; color:#fff; }
    .ua-status-note { font-size:13px; color:#6b7280; margin:8px 0 12px; }
    .status-table { width:100%; border-collapse:separate; border-spacing:0; font-size:14px; }
    .status-table thead th { text-align:left; color:#6b7280; font-weight:600; padding:10px 12px; border-bottom:1px solid #e5e7eb; }
    .status-table tbody td { padding:10px 12px; border-bottom:1px solid #f1f5f9; }
    .status-table tbody tr:nth-child(odd) { background:#fcfdff; }
    .status-badge { display:inline-block; padding:4px 8px; border-radius:999px; font-weight:600; font-size:12px; }
    .status-badge.active { background:#dcfce7; color:#166534; }
    .status-badge.onleave { background:#fef9c3; color:#854d0e; }
    .status-badge.terminated { background:#fee2e2; color:#991b1b; }
    .status-badge.archived { background:#A0AEC0; color:#1f2937; }
    .status-actions { display:flex; gap:8px; }
    .status-btn { padding:6px 10px; border:none; border-radius:8px; cursor:pointer; font-weight:600; color:#fff; transition:transform 120ms ease, box-shadow 160ms ease, background 160ms ease; }
    .status-btn:hover { transform: translateY(-1px); box-shadow:0 6px 18px rgba(0,0,0,0.10); }
    .btn-active { background:#16a34a; }
    .btn-onleave { background:#ca8a04; }
    .btn-terminated { background:#dc2626; }
    .btn-active:hover { background:#15803d; }
    .btn-onleave:hover { background:#a16207; }
    .btn-terminated:hover { background:#b91c1c; }
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
                    <button class="nav-item has-children active" data-toggle="users">
                        <span class="nav-icon">👤</span>
                        <span class="nav-label">User Management</span>
                        <span class="nav-caret">▴</span>
                    </button>
                    <div class="nav-children open" id="group-users">
                        <a class="nav-child" href="User_Accounts.php">User Accounts</a>
                        <a class="nav-child" href="User_Status.php">User Status &amp; Permissions</a>
                    </div>
                </div>
                <button class="nav-item" onclick="window.location.href='Audit_Logs.php'">
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
                <div style="display:flex; align-items:center; justify-content:space-between;">
                    <div class="ua-title">User Status &amp; Permissions</div>
                    <a href="User_Accounts.php" class="ua-back">← Back to User Accounts</a>
                </div>
                <p class="ua-status-note">Notice: <span class="status-badge terminated">Terminated</span> = Red, <span class="status-badge active">Active</span> = Green, <span class="status-badge onleave">On Leave</span> = Yellow. <span class="status-badge archived" style="margin-left:6px;">Archived</span> = Gray.</p>
                <?php 
                    $hasArchived = false; 
                    foreach ($accounts as $a) { if (!empty($a['Archived'])) { $hasArchived = true; break; } }
                    if ($hasArchived):
                ?>
                <div style="margin:10px 0 14px; padding:10px 12px; border:1px solid #bfdbfe; background:#eff6ff; color:#1e3a8a; border-radius:8px;">
                    Reminder: Archived accounts are read‑only here. They appear in muted blue‑gray and their status buttons are disabled.
                </div>
                <?php endif; ?>
                <div class="table-wrap">
                    <table class="status-table">
                        <thead>
                            <tr>
                                <th style="width:110px;">Employee ID</th>
                                <th>Name</th>
                                <th>Department</th>
                                <th>Position</th>
                                <th style="width:140px;">Status</th>
                                <th style="width:280px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($accounts)): ?>
                                <tr><td colspan="6" style="color:#6b7280;">No accounts found.</td></tr>
                            <?php else: ?>
                                <?php foreach ($accounts as $acc): 
                                    $status = $acc['Status'];
                                    $isArchived = ((int)($acc['Archived'] ?? 0)) === 1;
                                    $cls = $isArchived ? 'archived' : (($status === 'Active') ? 'active' : (($status === 'On Leave') ? 'onleave' : 'terminated'));
                                ?>
                                <tr data-emp="<?php echo (int)$acc['Employee_ID']; ?>">
                                    <td><?php echo (int)$acc['Employee_ID']; ?></td>
                                    <td><?php echo htmlspecialchars($acc['Account_Owner'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars($acc['Department'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars($acc['Position'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><span class="status-badge <?php echo $cls; ?>" data-badge><?php echo $isArchived ? 'Archived' : htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?></span></td>
                                    <td>
                                        <div class="status-actions">
                                            <button class="status-btn btn-active" data-set-status="Active">Active</button>
                                            <button class="status-btn btn-onleave" data-set-status="On Leave">On Leave</button>
                                            <button class="status-btn btn-terminated" data-set-status="Terminated">Terminated</button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
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

    (function userStatusActions(){
        async function setStatus(empId, status, badgeEl){
            try {
                const res = await fetch('updateAcc.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ Employee_ID: empId, changes: { Status: status } })
                });
                const json = await res.json().catch(() => null);
                if (!res.ok || !json || !json.ok) {
                    const err = (json && json.error) ? json.error : 'Update failed';
                    alert('Error: ' + err);
                    return;
                }
                if (badgeEl) {
                    badgeEl.textContent = status;
                    badgeEl.classList.remove('active','onleave','terminated');
                    if (status === 'Active') badgeEl.classList.add('active');
                    else if (status === 'On Leave') badgeEl.classList.add('onleave');
                    else badgeEl.classList.add('terminated');
                }

            } catch (e) {
                alert('Network error while updating status.');
            }
        }

        document.querySelectorAll('tr[data-emp]').forEach(row => {
            const empId = parseInt(row.getAttribute('data-emp') || '0', 10);
            const badge = row.querySelector('[data-badge]');
            const isArchived = badge.classList.contains('archived');
            if (isArchived) {
                // Disable buttons for archived accounts
                row.querySelectorAll('[data-set-status]').forEach(btn => {
                    btn.disabled = true;
                    btn.style.opacity = '0.6';
                    btn.style.cursor = 'not-allowed';
                });
                return;
            }
            row.querySelectorAll('[data-set-status]').forEach(btn => {
                btn.addEventListener('click', () => {
                    const status = btn.getAttribute('data-set-status');
                    if (status === 'Terminated') {
                        if (!confirm('Are you sure you want to mark this account as Terminated? This will prevent the user from signing in.')) {
                            return;
                        }
                    }
                    setStatus(empId, status, badge);
                });
            });
        });
    })();
    </script>
</body>
</html>


