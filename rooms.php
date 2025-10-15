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

// Room status definitions
$roomStatuses = [
    'available' => ['color' => '#10b981', 'label' => 'Available'],
    'overtime' => ['color' => '#ef4444', 'label' => 'Overtime'],
    'occupied' => ['color' => '#6b7280', 'label' => 'Occupied'],
    'maintenance' => ['color' => '#f59e0b', 'label' => 'Maintenance']
];

// Generate room data with default 'available' status - will be overridden by localStorage
$rooms = [];
$roomNumber = 1;
for ($floor = 1; $floor <= 10; $floor++) {
    $floorRooms = [];
    for ($room = 1; $room <= 100; $room++, $roomNumber++) {
        $floorRooms[] = [
            'number' => $roomNumber,
            'status' => 'available', // Default status
            'floor' => $floor
        ];
    }
    $rooms[$floor] = $floorRooms;
}

// Calculate statistics
$totalRooms = 1000;
$statusCounts = [];
foreach ($roomStatuses as $status => $info) {
    $statusCounts[$status] = 0;
}

foreach ($rooms as $floorRooms) {
    foreach ($floorRooms as $room) {
        $statusCounts[$room['status']]++;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rooms Management - Hotel Admin</title>
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
        .rooms-container { max-width: 1400px; margin: 20px auto; padding: 0 16px; }
        .rooms-title { font-size: 22px; font-weight: 700; color:#0f172a; margin-bottom: 20px; }
        .rooms-back { display:inline-flex; align-items:center; gap:6px; padding:8px 12px; border:1px solid #2563eb; color:#2563eb; border-radius:8px; text-decoration:none; margin-bottom: 20px; }
        .rooms-back:hover { background:#2563eb; color:#fff; }
        
        /* Statistics Cards */
        .stats-cards { display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:16px; margin-bottom:24px; }
        .stat-card { background:#ffffff; border-radius:12px; padding:20px; box-shadow:0 8px 24px rgba(0,0,0,0.06); }
        .stat-header { display:flex; align-items:center; gap:10px; margin-bottom:8px; }
        .stat-icon { font-size:20px; }
        .stat-label { color:#6b7280; font-size:14px; font-weight:500; }
        .stat-value { font-size:24px; font-weight:700; color:#0f172a; }
        
        /* Legend */
        .legend { background:#ffffff; border-radius:12px; padding:20px; box-shadow:0 8px 24px rgba(0,0,0,0.06); margin-bottom:24px; }
        .legend-title { font-weight:600; color:#0f172a; margin-bottom:15px; font-size:16px; }
        .legend-items { display:flex; flex-wrap:wrap; gap:20px; }
        .legend-item { display:flex; align-items:center; gap:8px; }
        .legend-color { width:20px; height:20px; border-radius:4px; }
        .legend-label { font-size:14px; color:#374151; }
        
        /* Building Layout */
        .building-layout { background:#ffffff; border-radius:12px; padding:20px; box-shadow:0 8px 24px rgba(0,0,0,0.06); }
        .building-title { font-weight:600; color:#0f172a; margin-bottom:20px; font-size:18px; text-align:center; }
        .floors-container { display:flex; flex-direction:column-reverse; gap:15px; }
        .floor { border:2px solid #e5e7eb; border-radius:8px; padding:15px; background:#f9fafb; }
        .floor-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:15px; }
        .floor-title { font-weight:600; color:#1f2937; font-size:16px; }
        .floor-stats { font-size:14px; color:#6b7280; }
        .rooms-grid { display:grid; grid-template-columns: repeat(10, 1fr); gap:4px; }
        .room-button { 
            width:100%; height:32px; border:none; border-radius:4px; font-size:11px; font-weight:600; 
            cursor:pointer; transition:all 0.2s ease; display:flex; align-items:center; justify-content:center;
            color:#fff; text-shadow:0 1px 2px rgba(0,0,0,0.3);
        }
        .room-button:hover { transform:scale(1.05); box-shadow:0 2px 8px rgba(0,0,0,0.2); }
        .room-button:active { transform:scale(0.95); }
        
        /* Room Status Colors */
        .room-available { background:#10b981; }
        .room-overtime { background:#ef4444; }
        .room-occupied { background:#6b7280; }
        .room-maintenance { background:#f59e0b; }
        
        /* Multi-select styles */
        .room-button.selected { 
            border: 3px solid #2563eb !important; 
            box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.3) !important;
            transform: scale(1.05);
        }
        .multi-select-container { 
            background:#ffffff; border-radius:12px; padding:15px; box-shadow:0 8px 24px rgba(0,0,0,0.06); 
            margin-bottom:20px; display:flex; align-items:center; gap:15px; flex-wrap:wrap; 
        }
        .checkbox-container { 
            display:flex; align-items:center; gap:8px; 
        }
        .multi-select-checkbox { 
            width:18px; height:18px; cursor:pointer; 
        }
        .checkbox-label { 
            font-weight:600; color:#1f2937; font-size:14px; cursor:pointer; 
        }
        .selection-info { 
            display:flex; align-items:center; gap:15px; margin-left:auto; 
            font-weight:600; color:#1f2937; 
        }
        .selection-actions { 
            display:flex; gap:10px; flex-wrap:wrap; 
        }
        .btn-bulk-change { 
            background:#2563eb; color:#fff; border:none; padding:8px 16px; border-radius:6px; 
            cursor:pointer; font-size:14px; font-weight:500; 
        }
        .btn-bulk-change:hover { background:#1d4ed8; }
        .btn-clear-selection { 
            background:#6b7280; color:#fff; border:none; padding:8px 16px; border-radius:6px; 
            cursor:pointer; font-size:14px; font-weight:500; 
        }
        .btn-clear-selection:hover { background:#4b5563; }
        
        /* Status Change Modal */
        .modal { display:none; position:fixed; z-index:1000; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.5); }
        .modal-content { background:#fff; margin:15% auto; padding:20px; border-radius:12px; width:400px; max-width:90%; }
        .modal-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; }
        .modal-title { font-size:18px; font-weight:600; color:#1f2937; }
        .close { font-size:24px; font-weight:bold; cursor:pointer; color:#6b7280; }
        .close:hover { color:#374151; }
        .status-options { display:grid; grid-template-columns: repeat(2, 1fr); gap:10px; margin-bottom:20px; }
        .status-option { 
            padding:12px; border:2px solid #e5e7eb; border-radius:8px; cursor:pointer; 
            text-align:center; transition:all 0.2s ease; background:#f9fafb;
        }
        .status-option:hover { border-color:#2563eb; background:#eff6ff; }
        .status-option.selected { border-color:#2563eb; background:#dbeafe; }
        .status-option-color { width:20px; height:20px; border-radius:50%; margin:0 auto 8px; }
        .status-option-label { font-size:14px; font-weight:500; color:#374151; }
        .modal-actions { display:flex; gap:10px; justify-content:flex-end; }
        .btn-save { background:#10b981; color:#fff; border:none; padding:10px 20px; border-radius:6px; cursor:pointer; font-weight:500; }
        .btn-cancel { background:#6b7280; color:#fff; border:none; padding:10px 20px; border-radius:6px; cursor:pointer; font-weight:500; }
        .btn-save:hover { background:#059669; }
        .btn-cancel:hover { background:#4b5563; }
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
                        <a class="nav-child" href="customer_history.php">Customer History</a>
                        <a class="nav-child" href="rooms.php" style="color:#fff;">Rooms</a>
                    </div>
                </div>
                <button class="nav-item" onclick="window.location.href='logout.php'">
                    <span class="nav-icon">🚪</span>
                    <span class="nav-label">Logout</span>
                </button>
            </nav>
        </aside>

        <main class="main">
            <div class="rooms-container">
                <header class="main-header">
                    <h1 class="rooms-title">Rooms Management</h1>
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

                <a href="Admin_Dash.php" class="rooms-back">
                    <span>←</span>
                    <span>Back to Dashboard</span>
                </a>

                <!-- Statistics Cards -->
                <div class="stats-cards">
                    <div class="stat-card">
                        <div class="stat-header">
                            <span class="stat-icon">🏨</span>
                            <span class="stat-label">Total Rooms</span>
                        </div>
                        <div class="stat-value"><?php echo number_format($totalRooms); ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-header">
                            <span class="stat-icon">✅</span>
                            <span class="stat-label">Available</span>
                        </div>
                        <div class="stat-value"><?php echo number_format($statusCounts['available']); ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-header">
                            <span class="stat-icon">🔴</span>
                            <span class="stat-label">Overtime</span>
                        </div>
                        <div class="stat-value"><?php echo number_format($statusCounts['overtime']); ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-header">
                            <span class="stat-icon">🔵</span>
                            <span class="stat-label">Occupied</span>
                        </div>
                        <div class="stat-value"><?php echo number_format($statusCounts['occupied']); ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-header">
                            <span class="stat-icon">🔧</span>
                            <span class="stat-label">Maintenance</span>
                        </div>
                        <div class="stat-value"><?php echo number_format($statusCounts['maintenance']); ?></div>
                    </div>
                </div>

                <!-- Legend and Management Tools -->
                <div class="legend">
                    <div class="legend-title">Room Status Legend</div>
                    <div class="legend-items">
                        <?php foreach ($roomStatuses as $status => $info): ?>
                            <div class="legend-item">
                                <div class="legend-color" style="background:<?php echo $info['color']; ?>"></div>
                                <span class="legend-label"><?php echo $info['label']; ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #e5e7eb;">
                        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                            <button onclick="clearAllRoomStatuses()" style="background: #ef4444; color: white; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer; font-size: 14px;">
                                🔄 Reset All to Available
                            </button>
                            <button onclick="exportRoomStatuses()" style="background: #10b981; color: white; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer; font-size: 14px;">
                                📤 Export Statuses
                            </button>
                            <button onclick="importRoomStatuses()" style="background: #3b82f6; color: white; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer; font-size: 14px;">
                                📥 Import Statuses
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Multi-Select Controls -->
                <div class="multi-select-container">
                    <div class="checkbox-container">
                        <input type="checkbox" id="multiSelectCheckbox" class="multi-select-checkbox" onchange="toggleMultiSelectMode()">
                        <label for="multiSelectCheckbox" class="checkbox-label">Multi-Select Mode</label>
                    </div>
                    <button id="changeStatusBtn" class="btn-bulk-change" onclick="openBulkStatusModal()" style="display:none;">
                        🔄 Change Status
                    </button>
                    <div id="selectionInfo" class="selection-info" style="display:none;">
                        <span id="selectionCount">0</span> rooms selected
                        <button onclick="selectAllRooms()" style="background:#10b981; color:#fff; border:none; padding:6px 12px; border-radius:4px; cursor:pointer; font-size:12px;">
                            Select All
                        </button>
                        <button onclick="selectNone()" style="background:#6b7280; color:#fff; border:none; padding:6px 12px; border-radius:4px; cursor:pointer; font-size:12px;">
                            Select None
                        </button>
                    </div>
                </div>

                <!-- Building Layout -->
                <div class="building-layout">
                    <div class="building-title">Hotel Building Layout - 10 Floors (100 Rooms Each)</div>
                    <div class="floors-container">
                        <?php for ($floor = 10; $floor >= 1; $floor--): 
                            $floorRooms = $rooms[$floor];
                            $floorStats = [];
                            foreach ($roomStatuses as $status => $info) {
                                $floorStats[$status] = 0;
                            }
                            foreach ($floorRooms as $room) {
                                $floorStats[$room['status']]++;
                            }
                        ?>
                            <div class="floor">
                                <div class="floor-header">
                                    <div class="floor-title">Floor <?php echo $floor; ?></div>
                                    <div class="floor-stats">
                                        Available: <?php echo $floorStats['available']; ?> | 
                                        Occupied: <?php echo $floorStats['occupied']; ?> | 
                                        Overtime: <?php echo $floorStats['overtime']; ?> | 
                                        Maintenance: <?php echo $floorStats['maintenance']; ?>
                                    </div>
                                </div>
                                 <div class="rooms-grid">
                                     <?php foreach ($floorRooms as $room): ?>
                                         <button class="room-button room-available" 
                                                 id="room-<?php echo $room['number']; ?>"
                                                 onclick="handleRoomClick(<?php echo $room['number']; ?>)"
                                                 title="Room <?php echo $room['number']; ?> - Available">
                                             <?php echo $room['number']; ?>
                                         </button>
                                     <?php endforeach; ?>
                                 </div>
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>

                <footer class="dash-footer">
                    <span>© 2025 Admin Module</span>
                </footer>
            </div>
        </main>
    </div>

    <!-- Status Change Modal -->
    <div id="statusModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title">Change Room Status</div>
                <span class="close" onclick="closeStatusModal()">&times;</span>
            </div>
            <div id="modalRoomInfo" style="margin-bottom:20px; padding:10px; background:#f3f4f6; border-radius:6px; font-weight:500;"></div>
            <div class="status-options" id="statusOptions">
                <?php foreach ($roomStatuses as $status => $info): ?>
                    <div class="status-option" data-status="<?php echo $status; ?>" onclick="selectStatus('<?php echo $status; ?>')">
                        <div class="status-option-color" style="background:<?php echo $info['color']; ?>"></div>
                        <div class="status-option-label"><?php echo $info['label']; ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="modal-actions">
                <button class="btn-cancel" onclick="closeStatusModal()">Cancel</button>
                <button class="btn-save" onclick="saveRoomStatus()">Save Changes</button>
            </div>
        </div>
    </div>

    <!-- Bulk Status Change Modal -->
    <div id="bulkStatusModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title">Change Status of Selected Rooms</div>
                <span class="close" onclick="closeBulkStatusModal()">&times;</span>
            </div>
            <div id="bulkModalRoomInfo" style="margin-bottom:20px; padding:10px; background:#f3f4f6; border-radius:6px; font-weight:500;"></div>
            <div class="status-options" id="bulkStatusOptions">
                <?php foreach ($roomStatuses as $status => $info): ?>
                    <div class="status-option" data-status="<?php echo $status; ?>" onclick="selectBulkStatus('<?php echo $status; ?>')">
                        <div class="status-option-color" style="background:<?php echo $info['color']; ?>"></div>
                        <div class="status-option-label"><?php echo $info['label']; ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="modal-actions">
                <button class="btn-cancel" onclick="closeBulkStatusModal()">Cancel</button>
                <button class="btn-save" onclick="saveBulkRoomStatus()">Save Changes</button>
            </div>
        </div>
    </div>

    <script>
    let currentRoomNumber = null;
    let selectedStatus = null;
    let roomStatuses = {};
    let selectedRooms = new Set();
    let bulkSelectedStatus = null;
    let multiSelectMode = false;

    // Initialize room statuses from localStorage or set defaults
    function initializeRoomStatuses() {
        const savedStatuses = localStorage.getItem('hotelRoomStatuses');
        if (savedStatuses) {
            roomStatuses = JSON.parse(savedStatuses);
        } else {
            // Initialize all rooms as 'available' if no saved data
            for (let roomNum = 1; roomNum <= 1000; roomNum++) {
                roomStatuses[roomNum] = 'available';
            }
            saveRoomStatuses();
        }
        updateRoomButtons();
        updateStatistics();
    }

    // Save room statuses to localStorage
    function saveRoomStatuses() {
        localStorage.setItem('hotelRoomStatuses', JSON.stringify(roomStatuses));
    }

    // Update room button appearances based on current statuses
    function updateRoomButtons() {
        for (let roomNum = 1; roomNum <= 1000; roomNum++) {
            const roomButton = document.getElementById(`room-${roomNum}`);
            if (roomButton) {
                const status = roomStatuses[roomNum] || 'available';
                roomButton.className = `room-button room-${status}`;
                // Update the onclick function
                roomButton.setAttribute('onclick', `handleRoomClick(${roomNum})`);
                // Update title based on current mode
                if (multiSelectMode) {
                    roomButton.title = `Room ${roomNum} - ${getStatusLabel(status)} (Click to select)`;
                } else {
                    roomButton.title = `Room ${roomNum} - ${getStatusLabel(status)} (Click to change status)`;
                }
            }
        }
    }

    // Update statistics display
    function updateStatistics() {
        const statusCounts = {
            'available': 0,
            'overtime': 0,
            'occupied': 0,
            'maintenance': 0
        };

        for (let roomNum = 1; roomNum <= 1000; roomNum++) {
            const status = roomStatuses[roomNum] || 'available';
            statusCounts[status]++;
        }

        // Update the statistics cards
        document.querySelectorAll('.stat-value').forEach((element, index) => {
            const statusKeys = ['available', 'overtime', 'occupied', 'maintenance'];
            if (index > 0 && index <= 4) { // Skip total rooms (index 0)
                element.textContent = statusCounts[statusKeys[index - 1]].toLocaleString();
            }
        });

        // Update floor statistics
        updateFloorStatistics();
    }

    // Update floor statistics
    function updateFloorStatistics() {
        const statusCounts = {
            'available': 0,
            'overtime': 0,
            'occupied': 0,
            'maintenance': 0
        };

        for (let floor = 1; floor <= 10; floor++) {
            const floorStats = { 'available': 0, 'overtime': 0, 'occupied': 0, 'maintenance': 0 };
            
            for (let room = 1; room <= 100; room++) {
                const roomNum = (floor - 1) * 100 + room;
                const status = roomStatuses[roomNum] || 'available';
                floorStats[status]++;
            }

            // Update floor stats display
            const floorElement = document.querySelector(`.floor:nth-child(${11 - floor}) .floor-stats`);
            if (floorElement) {
                floorElement.textContent = 
                    `Available: ${floorStats['available']} | ` +
                    `Occupied: ${floorStats['occupied']} | ` +
                    `Overtime: ${floorStats['overtime']} | ` +
                    `Maintenance: ${floorStats['maintenance']}`;
            }
        }
    }

    // Navigation toggle functionality
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

    // Initialize on page load
    document.addEventListener('DOMContentLoaded', function() {
        initializeRoomStatuses();
    });

    // Open status change modal
    function openStatusModal(roomNumber, currentStatus) {
        currentRoomNumber = roomNumber;
        selectedStatus = roomStatuses[roomNumber] || 'available';
        
        document.getElementById('modalRoomInfo').textContent = `Room ${roomNumber} - Current Status: ${getStatusLabel(selectedStatus)}`;
        document.getElementById('statusModal').style.display = 'block';
        
        // Highlight current status
        document.querySelectorAll('.status-option').forEach(option => {
            option.classList.remove('selected');
            if (option.dataset.status === selectedStatus) {
                option.classList.add('selected');
            }
        });
    }

    // Close status change modal
    function closeStatusModal() {
        document.getElementById('statusModal').style.display = 'none';
        currentRoomNumber = null;
        selectedStatus = null;
    }

    // Select status option
    function selectStatus(status) {
        selectedStatus = status;
        document.querySelectorAll('.status-option').forEach(option => {
            option.classList.remove('selected');
        });
        document.querySelector(`[data-status="${status}"]`).classList.add('selected');
    }

    // Save room status
    function saveRoomStatus() {
        if (!currentRoomNumber || !selectedStatus) return;
        
        // Update the room status in our data structure
        roomStatuses[currentRoomNumber] = selectedStatus;
        
        // Save to localStorage
        saveRoomStatuses();
        
        // Update the button appearance
        const roomButton = document.getElementById(`room-${currentRoomNumber}`);
        if (roomButton) {
            roomButton.className = `room-button room-${selectedStatus}`;
            roomButton.setAttribute('onclick', `handleRoomClick(${currentRoomNumber})`);
            // Update title based on current mode
            if (multiSelectMode) {
                roomButton.title = `Room ${currentRoomNumber} - ${getStatusLabel(selectedStatus)} (Click to select)`;
            } else {
                roomButton.title = `Room ${currentRoomNumber} - ${getStatusLabel(selectedStatus)} (Click to change status)`;
            }
        }
        
        // Update statistics
        updateStatistics();
        
        console.log(`Room ${currentRoomNumber} status updated to ${selectedStatus} and saved to localStorage`);
        alert(`Room ${currentRoomNumber} status updated to ${getStatusLabel(selectedStatus)}`);
        closeStatusModal();
    }

    // Get status label
    function getStatusLabel(status) {
        const statusLabels = {
            'available': 'Available',
            'overtime': 'Overtime',
            'occupied': 'Occupied',
            'maintenance': 'Maintenance'
        };
        return statusLabels[status] || status;
    }

    // Clear all room statuses (reset to available)
    function clearAllRoomStatuses() {
        if (confirm('Are you sure you want to reset all room statuses to Available? This action cannot be undone.')) {
            for (let roomNum = 1; roomNum <= 1000; roomNum++) {
                roomStatuses[roomNum] = 'available';
            }
            saveRoomStatuses();
            updateRoomButtons();
            updateStatistics();
            alert('All room statuses have been reset to Available');
        }
    }

    // Export room statuses (for backup)
    function exportRoomStatuses() {
        const dataStr = JSON.stringify(roomStatuses, null, 2);
        const dataBlob = new Blob([dataStr], {type: 'application/json'});
        const url = URL.createObjectURL(dataBlob);
        const link = document.createElement('a');
        link.href = url;
        link.download = 'hotel-room-statuses-backup.json';
        link.click();
        URL.revokeObjectURL(url);
    }

    // Import room statuses (for restore)
    function importRoomStatuses() {
        const input = document.createElement('input');
        input.type = 'file';
        input.accept = '.json';
        input.onchange = function(event) {
            const file = event.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    try {
                        const importedStatuses = JSON.parse(e.target.result);
                        if (confirm('Are you sure you want to import room statuses? This will overwrite current data.')) {
                            roomStatuses = importedStatuses;
                            saveRoomStatuses();
                            updateRoomButtons();
                            updateStatistics();
                            alert('Room statuses imported successfully');
                        }
                    } catch (error) {
                        alert('Error importing file. Please check the file format.');
                    }
                };
                reader.readAsText(file);
            }
        };
        input.click();
    }

    // Toggle multi-select mode
    function toggleMultiSelectMode() {
        multiSelectMode = document.getElementById('multiSelectCheckbox').checked;
        
        // Clear any existing selection when switching modes
        clearSelection();
        
        // Show/hide UI elements based on mode
        const changeStatusBtn = document.getElementById('changeStatusBtn');
        const selectionInfo = document.getElementById('selectionInfo');
        
        if (multiSelectMode) {
            changeStatusBtn.style.display = 'inline-block';
            selectionInfo.style.display = 'flex';
            // Update room button titles
            updateRoomButtonTitles();
        } else {
            changeStatusBtn.style.display = 'none';
            selectionInfo.style.display = 'none';
            // Update room button titles back to normal
            updateRoomButtonTitles();
        }
    }

    // Handle room click based on current mode
    function handleRoomClick(roomNumber) {
        if (multiSelectMode) {
            toggleRoomSelection(roomNumber);
        } else {
            // Single room mode - open status modal directly
            const currentStatus = roomStatuses[roomNumber] || 'available';
            openStatusModal(roomNumber, currentStatus);
        }
    }

    // Update room button titles based on current mode
    function updateRoomButtonTitles() {
        for (let roomNum = 1; roomNum <= 1000; roomNum++) {
            const roomButton = document.getElementById(`room-${roomNum}`);
            if (roomButton) {
                const status = roomStatuses[roomNum] || 'available';
                if (multiSelectMode) {
                    roomButton.title = `Room ${roomNum} - ${getStatusLabel(status)} (Click to select)`;
                } else {
                    roomButton.title = `Room ${roomNum} - ${getStatusLabel(status)} (Click to change status)`;
                }
            }
        }
    }

    // Multi-select functionality
    function toggleRoomSelection(roomNumber) {
        const roomButton = document.getElementById(`room-${roomNumber}`);
        if (selectedRooms.has(roomNumber)) {
            // Deselect room
            selectedRooms.delete(roomNumber);
            roomButton.classList.remove('selected');
        } else {
            // Select room
            selectedRooms.add(roomNumber);
            roomButton.classList.add('selected');
        }
        updateSelectionUI();
    }

    function updateSelectionUI() {
        const selectionCount = selectedRooms.size;
        document.getElementById('selectionCount').textContent = selectionCount;
        
        const changeStatusBtn = document.getElementById('changeStatusBtn');
        if (selectionCount > 0) {
            changeStatusBtn.style.display = 'inline-block';
        } else if (multiSelectMode) {
            changeStatusBtn.style.display = 'inline-block';
        } else {
            changeStatusBtn.style.display = 'none';
        }
    }

    function selectAllRooms() {
        for (let roomNum = 1; roomNum <= 1000; roomNum++) {
            selectedRooms.add(roomNum);
            const roomButton = document.getElementById(`room-${roomNum}`);
            if (roomButton) {
                roomButton.classList.add('selected');
            }
        }
        updateSelectionUI();
    }

    function selectNone() {
        selectedRooms.clear();
        for (let roomNum = 1; roomNum <= 1000; roomNum++) {
            const roomButton = document.getElementById(`room-${roomNum}`);
            if (roomButton) {
                roomButton.classList.remove('selected');
            }
        }
        updateSelectionUI();
    }

    function clearSelection() {
        selectNone();
    }

    function openBulkStatusModal() {
        if (selectedRooms.size === 0) {
            alert('Please select at least one room first.');
            return;
        }
        
        bulkSelectedStatus = null;
        document.getElementById('bulkModalRoomInfo').textContent = 
            `Changing status for ${selectedRooms.size} selected rooms`;
        document.getElementById('bulkStatusModal').style.display = 'block';
        
        // Clear any previous selection
        document.querySelectorAll('#bulkStatusOptions .status-option').forEach(option => {
            option.classList.remove('selected');
        });
    }

    function closeBulkStatusModal() {
        document.getElementById('bulkStatusModal').style.display = 'none';
        bulkSelectedStatus = null;
    }

    function selectBulkStatus(status) {
        bulkSelectedStatus = status;
        document.querySelectorAll('#bulkStatusOptions .status-option').forEach(option => {
            option.classList.remove('selected');
        });
        document.querySelector(`#bulkStatusOptions [data-status="${status}"]`).classList.add('selected');
    }

    function saveBulkRoomStatus() {
        if (!bulkSelectedStatus || selectedRooms.size === 0) return;
        
        // Update all selected rooms
        selectedRooms.forEach(roomNumber => {
            roomStatuses[roomNumber] = bulkSelectedStatus;
            
            // Update button appearance
            const roomButton = document.getElementById(`room-${roomNumber}`);
            if (roomButton) {
                roomButton.className = `room-button room-${bulkSelectedStatus}`;
                roomButton.setAttribute('onclick', `handleRoomClick(${roomNumber})`);
                // Update title based on current mode
                if (multiSelectMode) {
                    roomButton.title = `Room ${roomNumber} - ${getStatusLabel(bulkSelectedStatus)} (Click to select)`;
                } else {
                    roomButton.title = `Room ${roomNumber} - ${getStatusLabel(bulkSelectedStatus)} (Click to change status)`;
                }
            }
        });
        
        // Save to localStorage
        saveRoomStatuses();
        
        // Update statistics
        updateStatistics();
        
        // Clear selection
        clearSelection();
        
        console.log(`Updated ${selectedRooms.size} rooms to ${bulkSelectedStatus} status`);
        alert(`Successfully updated ${selectedRooms.size} rooms to ${getStatusLabel(bulkSelectedStatus)} status`);
        closeBulkStatusModal();
    }

    // Close modal when clicking outside
    window.onclick = function(event) {
        const statusModal = document.getElementById('statusModal');
        const bulkModal = document.getElementById('bulkStatusModal');
        if (event.target === statusModal) {
            closeStatusModal();
        }
        if (event.target === bulkModal) {
            closeBulkStatusModal();
        }
    }
    </script>
</body>
</html>
