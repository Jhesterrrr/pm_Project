<html lang="en">
    
<?php
session_start();
include 'db_connection.php';
?>

<head>
    <meta charset="UTF-8">
    <title>User Accounts</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
    </style>
    <style>
    .ua-container { max-width: 1100px; margin: 20px auto; padding: 0 16px; }
    .ua-header { display:flex; align-items:center; justify-content:space-between; }
    .ua-title { font-size: 22px; font-weight: 700; color:#0f172a; }
    .ua-menu .action-card { 
        display:block; width:100%; text-align:left; cursor:pointer;
        background:#1f2937; border:0; border-radius:12px; padding:16px 18px;
        box-shadow:0 8px 24px rgba(0,0,0,0.06);
        transition: transform 160ms ease, box-shadow 160ms ease, background-color 160ms ease, color 160ms ease, font-size 160ms ease;
        font-weight:600; color:#e5e7eb;
    }
    .ua-menu .action-card:hover {
        transform: translateY(-2px) scale(1.03);
        box-shadow: 0 10px 28px rgba(0,0,0,0.10);
        background:#ffffff; color:#111827; font-weight:700; font-size:1.06em;
    }
    .ua-section { display:none; }
    .ua-actions { display:grid; grid-template-columns: repeat(3, minmax(0,1fr)); gap:14px; margin-top:14px; }
    @media (max-width: 900px) { .ua-actions { grid-template-columns: 1fr; } }
    .ua-back { display:inline-flex; align-items:center; gap:6px; padding:8px 12px; border:1px solid #2563eb; color:#2563eb; border-radius:8px; text-decoration:none; }
    .ua-back:hover { background:#2563eb; color:#fff; }

    /* Status table */
    .ua-status-note { font-size:13px; color:#6b7280; margin:8px 0 12px; }
    .status-table { width:100%; border-collapse:separate; border-spacing:0; font-size:14px; }
    .status-table thead th { text-align:left; color:#6b7280; font-weight:600; padding:10px 12px; border-bottom:1px solid #e5e7eb; }
    .status-table tbody td { padding:10px 12px; border-bottom:1px solid #f1f5f9; }
    .status-table tbody tr:nth-child(odd) { background:#fcfdff; }
    .status-badge { display:inline-block; padding:4px 8px; border-radius:999px; font-weight:600; font-size:12px; }
    .status-badge.active { background:#dcfce7; color:#166534; }         /* Green */
    .status-badge.onleave { background:#fef9c3; color:#854d0e; }       /* Yellow */
    .status-badge.terminated { background:#fee2e2; color:#991b1b; }    /* Red */
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
			<?php if (!empty($_SESSION['flash_success'])): ?>
				<div style="max-width:600px;margin:12px auto;padding:10px;background:#d4edda;border:1px solid #c3e6cb;color:#155724;border-radius:6px;">
					<?php echo htmlspecialchars($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?>
				</div>
			<?php endif; ?>
			<?php if (!empty($_SESSION['flash_error'])): ?>
				<div style="max-width:600px;margin:12px auto;padding:10px;background:#f8d7da;border:1px solid #f5c6cb;color:#721c24;border-radius:6px;">
					<?php echo htmlspecialchars($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?>
				</div>
			<?php endif; ?>


		<div class="ua-container">
		<div class="ua-header">
			<div class="ua-title">User Accounts</div>
			<a href="Admin_Dash.php" class="ua-back">← Back to Dashboard</a>
		</div>

			<?php
			// Ensure Status column exists
			if (isset($mysqli)) {
				$mysqli->query("ALTER TABLE accounts ADD COLUMN IF NOT EXISTS Status ENUM('Active','On Leave','Terminated') NOT NULL DEFAULT 'Active'");
			}
			// Fetch accounts for Status table
			$ua_status_accounts = [];
			if (isset($mysqli)) {
				$res = $mysqli->query("SELECT Employee_ID, Account_Owner, Department, Position, IFNULL(Status,'Active') AS Status FROM accounts WHERE (IFNULL(Archived,0) = 0) ORDER BY Employee_ID ASC");
				if ($res) { while ($row = $res->fetch_assoc()) { $ua_status_accounts[] = $row; } }
			}
			?>

			<div id="ua-menu" class="ua-menu">
			<div class="ua-actions">
				<button class="action-card" data-target="ua-create">Create</button>
				<button class="action-card" data-target="ua-update">Update</button>
				<button class="action-card" data-target="ua-view">View All Accounts</button>
			</div>
		</div>

		<section id="ua-create" class="panel ua-section">
			<div class="panel-header" style="display:flex; align-items:center; justify-content:space-between;">
				<span>Create New Employee Account</span>
				<a href="#" class="ua-back" data-back>← Back</a>
			</div>
			<form id="createForm" method="POST" action="createAcc.php" autocomplete="off" style="margin:0;">
	  <div style="display:grid; grid-template-columns:1fr; gap:16px;">
	  <div>
		<label for="Account_Owner" style="display:block; margin-bottom:6px;">Account Owner:</label>
		<input type="text" id="Account_Owner" name="Account_Owner" required 
		       style="width:100%; padding:8px; border-radius:5px; border:1px solid #bbb;">
	  </div>
	  <div>
		<label style="display:flex; align-items:center; gap:8px;">
		  <input type="checkbox" id="autoGenToggle" />
		  <span>Auto-generate Username and Password</span>
		</label>
		<small style="color:#555;">When enabled, Username and Password will be generated from Account Owner and the new Employee ID.</small>
	  </div>
	  <div>
		  <label for="Username" style="display:block; margin-bottom:6px;">Username:</label>
		  <input type="text" id="Username" name="Username"
			     style="width:100%; padding:8px; border-radius:5px; border:1px solid #bbb;">
	  </div>
	  <div>
		  <label for="Password" style="display:block; margin-bottom:6px;">Password:</label>
		  <input type="password" id="Password" name="Password"
			     style="width:100%; padding:8px; border-radius:5px; border:1px solid #bbb;">
	  </div>
	  <div>
		  <label for="Department" style="display:block; margin-bottom:6px;">Department:</label>
		  <select id="Department" name="Department" required 
			      style="width:100%; padding:8px; border-radius:5px; border:1px solid #bbb;">
			  <option value="Blocked">Options</option>
			  <option value="Administrator">Admin</option>
			  <option value="Human Resources">HR</option>
			  <option value="Core">Core</option>
			  <option value="Finance">Finance</option>
			  <option value="Logistics">Logistics</option>
		  </select>
	  </div>
	  <div>
		  <label for="Position" style="display:block; margin-bottom:6px;">Position:</label>
		  <select id="Position" name="Position" required 
			      style="width:100%; padding:8px; border-radius:5px; border:1px solid #bbb;">
			<option value="Blocked">Options</option>
			  <option value="Head">Head</option>
			  <option value="Senior">Senior</option>
			  <option value="Staff">Staff</option>
			  <option value="Junior">Junior</option>
		  </select>
	  </div>
	  </div>
	  <div style="margin-top:16px;">
	  <button type="submit" 
			  style="width:100%; padding:10px; cursor: pointer; background:#2c3e50; color:#fff; border:none; border-radius:6px; font-size:16px;">
		  Create Account
	  </button>
	  </div>
	</form>
		</section>

		<section id="ua-update" class="panel ua-section">
			<div class="panel-header" style="display:flex; align-items:center; justify-content:space-between;">
				<span>Update Employee Account</span>
				<a href="#" class="ua-back" data-back>← Back</a>
			</div>
			<form id="updateForm" method="POST" action="updateAcc.php" autocomplete="off" style="margin:0;">
	  <div style="display:grid; grid-template-columns:1fr; gap:16px;">
	  <div>
		<label for="Employee_ID" style="display:block; margin-bottom:6px;">Employee ID:</label>
		<div>
		<input type="number" id="Employee_ID" name="Employee_ID" min="0"
		       style="padding:8px; border-radius:5px; border:1px solid #bbb; width:160px;">
		       <button type="button" id="fetchBtn"
		  style="margin-left:10px; padding:8px; background:#3498db;cursor:pointer; color:#fff; border:none; border-radius:5px;">Fetch</button>
		</div>
	  </div>
	  <div>
		<label for="Account_Owner" style="display:block; margin-bottom:6px;">Account Owner:</label>
		<input type="text" id="Account_Owner" name="Account_Owner"
		       style="width:100%; padding:8px; border-radius:5px; border:1px solid #bbb;">
	  </div>
	  <div>
		  <label for="Username" style="display:block; margin-bottom:6px;">Username:</label>
		  <input type="text" id="Username" name="Username" 
			     style="width:100%; padding:8px; border-radius:5px; border:1px solid #bbb;">
	  </div>
	  <div>
		  <label for="Password" style="display:block; margin-bottom:6px;">Password:</label>
		  <input type="password" id="Password" name="Password"
			     style="width:100%; padding:8px; border-radius:5px; border:1px solid #bbb;">
	  </div>
	  <div>
		  <label for="Department" style="display:block; margin-bottom:6px;">Department:</label>
		  <select id="Department" name="Department" 
			      style="width:100%; padding:8px; border-radius:5px; border:1px solid #bbb;">
			  <option value="Blocked">Options</option>
			  <option value="Administrator">Admin</option>
			  <option value="Human Resources">HR</option>
			  <option value="Core">Core</option>
			  <option value="Finance">Finance</option>
			  <option value="Logistics">Logistics</option>
		  </select>
	  </div>
	  <div>
		  <label for="Position" style="display:block; margin-bottom:6px;">Position:</label>
		  <select id="Position" name="Position"
			      style="width:100%; padding:8px; border-radius:5px; border:1px solid #bbb;">
			  <option value="Blocked">Options</option>
			  <option value="Head">Head</option>
			  <option value="Senior">Senior</option>
			  <option value="Staff">Staff</option>
			  <option value="Junior">Junior</option>
		  </select>
		  <div style="margin-top:8px; display:flex; gap:8px;">
			<button type="button" id="promoteBtn" 
					style="padding:8px 12px; background:#28a745; color:#fff; border:none; border-radius:5px; cursor:pointer;">Promote Employee</button>
			<button type="button" id="demoteBtn" 
					style="padding:8px 12px; background:#dc3545; color:#fff; border:none; border-radius:5px; cursor:pointer;">Demote Employee</button>
		  </div>
	  </div>
	  </div>
	  <div style="margin-top:16px;">
	  <button type="submit" 
			  style="width:100%; padding:10px; background:#2c3e50; color:#fff; border:none; border-radius:6px; font-size:16px;">
		  Update Employee Account
	  </button>
	  </div>
	</form>
		</section>

		<section id="ua-view" class="panel ua-section">
			<div class="panel-header" style="display:flex; align-items:center; justify-content:space-between;">
				<span>All Accounts</span>
				<a href="#" class="ua-back" data-back>← Back</a>
			</div>
			<p style="color:#6b7280; margin-top:0;">Open the full accounts table to filter, search, update, archive, and view each account logs.</p>
			<div>
				<a href="acc_Table.php" 
				   style="display:inline-block; padding:10px 16px; background:#2563eb; color:#fff; border-radius:8px; text-decoration:none;">Open Accounts Table</a>
			</div>
		</section>

		<section id="ua-status" class="panel ua-section">
			<div class="panel-header" style="display:flex; align-items:center; justify-content:space-between;">
				<span>User Status &amp; Permissions</span>
				<a href="#" class="ua-back" data-back>← Back</a>
			</div>
			<p class="ua-status-note">Notice: <span class="status-badge terminated">Terminated</span> = Red, <span class="status-badge active">Active</span> = Green, <span class="status-badge onleave">On Leave</span> = Yellow.</p>
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
						<?php if (empty($ua_status_accounts)): ?>
							<tr><td colspan="6" style="color:#6b7280;">No accounts found.</td></tr>
						<?php else: ?>
							<?php foreach ($ua_status_accounts as $acc): 
								$status = $acc['Status'];
								$cls = ($status === 'Active') ? 'active' : (($status === 'On Leave') ? 'onleave' : 'terminated');
							?>
							<tr data-emp="<?php echo (int)$acc['Employee_ID']; ?>">
								<td><?php echo (int)$acc['Employee_ID']; ?></td>
								<td><?php echo htmlspecialchars($acc['Account_Owner'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
								<td><?php echo htmlspecialchars($acc['Department'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
								<td><?php echo htmlspecialchars($acc['Position'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
								<td>
									<span class="status-badge <?php echo $cls; ?>" data-badge><?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?></span>
								</td>
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
		</section>
	</div>

		</main>
	</div>



<!--Fetch Employee account-->
<script>
  // Section expand/collapse
  (function uaSections(){
    const menu = document.getElementById('ua-menu');
    const sections = ['ua-create','ua-update','ua-view'].map(id => document.getElementById(id));
    function showSection(id){
      menu.style.display = 'none';
      sections.forEach(s => { if (s) s.style.display = (s.id === id) ? 'block' : 'none'; });
      window.scrollTo({ top: 0, behavior: 'smooth' });
    }
    function backToMenu(){
      sections.forEach(s => { if (s) s.style.display = 'none'; });
      menu.style.display = 'block';
      window.scrollTo({ top: 0, behavior: 'smooth' });
    }
    document.querySelectorAll('.action-card').forEach(btn => {
      btn.addEventListener('click', () => showSection(btn.getAttribute('data-target')));
    });
    document.querySelectorAll('[data-back]').forEach(b => b.addEventListener('click', function(e){ e.preventDefault(); backToMenu(); }));

	// open section from query ?open=create|update|view
	const params = new URLSearchParams(window.location.search);
	const open = (params.get('open') || '').toLowerCase();
	if (open === 'create') showSection('ua-create');
	else if (open === 'update') showSection('ua-update');
	else if (open === 'view') showSection('ua-view');
  })();

  // Sidebar expand/collapse (match dashboard)
  (function sidebarToggles(){
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

  // Status change handlers
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
        // Update badge UI
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

    document.querySelectorAll('#ua-status tr[data-emp]').forEach(row => {
      const empId = parseInt(row.getAttribute('data-emp') || '0', 10);
      const badge = row.querySelector('[data-badge]');
      row.querySelectorAll('[data-set-status]').forEach(btn => {
        btn.addEventListener('click', () => {
          const status = btn.getAttribute('data-set-status');
          setStatus(empId, status, badge);
        });
      });
    });
  })();

  // Create form auto-generate toggle
  const createForm = document.getElementById('createForm');
  const autoGen = createForm.querySelector('#autoGenToggle');
  const cOwner = createForm.querySelector('#Account_Owner');
  const cUser  = createForm.querySelector('#Username');
  const cPass  = createForm.querySelector('#Password');

  function applyAutoGenState() {
    const enabled = autoGen.checked;
    if (enabled) {
      cUser.value = '';
      cPass.value = '';
      cUser.placeholder = 'Will be auto-generated';
      cPass.placeholder = 'Will be auto-generated';
      cUser.readOnly = true;
      cPass.readOnly = true;
    } else {
      cUser.placeholder = '';
      cPass.placeholder = '';
      cUser.readOnly = false;
      cPass.readOnly = false;
    }
  }
  autoGen.addEventListener('change', applyAutoGenState);
  applyAutoGenState();

  // Confirmation before creating
  createForm.addEventListener('submit', function(e) {
    if (autoGen.checked && !cOwner.value.trim()) {
      e.preventDefault();
      alert('Please enter Account Owner to auto-generate credentials.');
      return;
    }

    const dept = document.getElementById('Department').value;
    const pos  = document.getElementById('Position').value;
    if (dept === 'Blocked' || pos === 'Blocked') {
      e.preventDefault();
      alert('❌ Please choose a valid Department & Position.');
      return;
    }

    const owner = cOwner.value.trim();
    const uname = cUser.readOnly ? '(auto)' : (cUser.value.trim() || '(empty)');
    const pword = cPass.readOnly ? '(auto)' : (cPass.value.trim() || '(empty)');
    const msg = `Please confirm creation:\n\nOwner: ${owner}\nDepartment: ${dept}\nPosition: ${pos}\nUsername: ${uname}\nPassword: ${pword}`;
    if (!confirm(msg)) {
      e.preventDefault();
      return;
    }
  });

  // Scope everything to the Update form
  const updForm  = document.getElementById('updateForm');
  const fetchBtn = updForm.querySelector('#fetchBtn');
  const empInput = updForm.querySelector('#Employee_ID');
  const promoteBtn = updForm.querySelector('#promoteBtn');
  const demoteBtn  = updForm.querySelector('#demoteBtn');

  // Track original values for diffing
  const original = {};

  // Helper to select inside the Update form
  const sel = (id) => updForm.querySelector('#' + id);

  function setSelectValue(selectEl, value) {
    if (!selectEl) return;
    const exists = Array.from(selectEl.options).some(o => o.value === value);
    if (!exists && value && value !== 'Blocked') {
      selectEl.add(new Option(value, value));
    }
    selectEl.value = value || 'Blocked';
  }

  function clearUpdateFields() {
    sel('Account_Owner').value = '';
    sel('Username').value = '';
    sel('Password').value = '';
    setSelectValue(sel('Department'), 'Blocked');
    setSelectValue(sel('Position'), 'Blocked');
    for (const k of ['Employee_ID','Account_Owner','Username','Password','Department','Position']) {
      delete original[k];
    }
  }

  function populateFields(data) {
    sel('Account_Owner').value = data.Account_Owner ?? '';
    sel('Username').value = data.Username ?? '';
    sel('Password').value = data.Password ?? '';
    setSelectValue(sel('Department'), data.Department ?? 'Blocked');
    setSelectValue(sel('Position'), data.Position ?? 'Blocked');

    original.Employee_ID   = data.Employee_ID;
    original.Account_Owner = sel('Account_Owner').value;
    original.Username      = sel('Username').value;
    original.Password      = sel('Password').value;
    original.Department    = sel('Department').value;
    original.Position      = sel('Position').value;
  }

  function currentValues() {
    return {
      Employee_ID: parseInt(empInput.value || '0', 10),
      Account_Owner: sel('Account_Owner').value,
      Username: sel('Username').value,
      Password: sel('Password').value,
      Department: sel('Department').value,
      Position: sel('Position').value,
    };
  }

  function computeNextPosition(current, direction) {
    const order = ['Junior','Staff','Senior','Head'];
    const idx = order.indexOf(current);
    if (idx === -1) return null;
    if (direction === 'up') {
      if (idx === order.length - 1) return current; // already Head
      return order[idx + 1];
    } else {
      if (idx === 0) return current; // already Junior
      return order[idx - 1];
    }
  }

  async function applyPositionChange(direction) {
    const id = empInput.value.trim();
    if (!id) { alert('Please click Fetch first to load the account.'); return; }
    if (!original.Employee_ID) { alert('Please click Fetch first to load the account.'); return; }

    const currPos = sel('Position').value;
    const nextPos = computeNextPosition(currPos, direction);
    if (!nextPos) { alert('Invalid current position.'); return; }

    if (nextPos === currPos) {
      if (direction === 'up') alert('This is the highest promotion you can give');
      else alert('This is the lowest demotion you can give');
      return;
    }

    try {
      const res = await fetch('updateAcc.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ Employee_ID: parseInt(id,10), changes: { Position: nextPos } })
      });
      const json = await res.json().catch(() => null);
      if (!res.ok || !json || !json.ok) {
        const errMsg = (json && json.error) ? json.error : 'Update failed.';
        alert('Error: ' + errMsg);
        return;
      }
      // Update UI and originals
      sel('Position').value = nextPos;
      original.Position = nextPos;
      alert('Position updated to ' + nextPos + '.');
    } catch (e) {
      alert('Network error while updating position.');
    }
  }

  promoteBtn.addEventListener('click', () => applyPositionChange('up'));
  demoteBtn.addEventListener('click', () => applyPositionChange('down'));

  function computeDiff(orig, curr) {
    const fields = ['Account_Owner','Username','Password','Department','Position'];
    const changes = {};
    fields.forEach(f => {
      if ((orig[f] ?? '') !== (curr[f] ?? '')) changes[f] = curr[f];
    });
    return changes;
  }

  fetchBtn.addEventListener('click', async () => {
    const id = empInput.value.trim();
    if (!id) { alert('Please enter Employee ID first.'); return; }

    try {
      const res = await fetch('fetchAcc.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ Employee_ID: id })
      });

      let json = null;
      try { json = await res.json(); } catch {}

      if (!res.ok || !json || !json.ok) {
        clearUpdateFields();
        const msg = (json && json.error) ? json.error : 'Account not found or fetch failed.';
        alert('Error: ' + msg);
        return;
      }

      populateFields(json.data);
      alert('Account loaded. You can now edit fields and click Update.');
    } catch {
      clearUpdateFields();
      alert('Network error while fetching account.');
    }
  });

  // If navigated with ?employee_id=123, prefill and auto-fetch
  (function autoFetchFromQuery(){
    const params = new URLSearchParams(window.location.search);
    const qId = params.get('employee_id');
    if (qId && /^\d+$/.test(qId)) {
      empInput.value = qId.replace(/\D/g,'');
      // Trigger same behavior as clicking Fetch
      fetchBtn.click();
      // Focus the first editable field after loading
      setTimeout(() => { sel('Account_Owner').focus(); }, 600);
    }
  })();

  updForm.addEventListener('submit', async (e) => {
    e.preventDefault();

    const curr = currentValues();
    if (!curr.Employee_ID) { alert('Employee ID is required.'); return; }
    if (!original.Employee_ID) { alert('Please click Fetch first to load the account before updating.'); return; }

    const changes = computeDiff(original, curr);
    if (Object.keys(changes).length === 0) {
      alert('No changes detected. Nothing to update.');
      return;
    }

// Block "Blocked" choices
if ((changes.Department && changes.Department === 'Blocked') ||
    (changes.Position && changes.Position === 'Blocked')) {
  alert('❌ Please choose a valid Department & Position.');
  return;
}

    // Confirmation with masked password
    let msg = 'Please confirm the following changes:\n\n';
    for (const [k, v] of Object.entries(changes)) {
      const oldV = String(original[k] ?? '');
      const newV = String(v ?? '');
      const safeOld = (k === 'Password') ? '••••••' : oldV;
      const safeNew = (k === 'Password') ? '••••••' : newV;
      msg += `- ${k}: "${safeOld}" -> "${safeNew}"\n`;
    }
    if (!confirm(msg)) return;

    try {
      const res = await fetch('updateAcc.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ Employee_ID: curr.Employee_ID, changes })
      });

      let json = null;
      try { json = await res.json(); } catch {}

      if (!res.ok || !json || !json.ok) {
        const errMsg = (json && json.error) ? json.error : 'Update failed.';
        alert('Error: ' + errMsg);
        return;
      }

      alert('Update successful. Fields changed: ' + json.updated_fields.join(', '));
      Object.assign(original, changes); // keep originals in sync
    } catch {
      alert('Network error while updating account.');
    }
  });
</script>

    <div style="max-width:400px; margin:24px auto;">
        <a href="Admin_Dash.php" 
           style="display:block; text-align:center; margin-top:20px; padding:10px; background:#2980b9; color:#fff; border:none; border-radius:6px; font-size:16px; text-decoration:none;">
            Go to Dashboard
        </a>
    </div>



    </body>
</html>


