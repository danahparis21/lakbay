<?php
// guide-safety.php - Safety Page with Alerts Management
require_once __DIR__ . '/../config/db.php';
date_default_timezone_set('Asia/Manila');
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$currentUserId = $_SESSION['user_id'];
$currentUserName = $_SESSION['name'] ?? $_SESSION['user_name'] ?? '';
$currentUserRole = $_SESSION['role'] ?? 'guide';

// Get guide record if guide
$guideId = null;
try {
    $stmt = $pdo->prepare("SELECT id FROM guides WHERE user_id = ?");
    $stmt->execute([$currentUserId]);
    $guide = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($guide) {
        $guideId = $guide['id'];
    }
} catch (PDOException $e) {
    error_log("Guide lookup error: " . $e->getMessage());
}

// Get active booking for this guide
$activeBooking = null;
if ($guideId) {
    try {
        $stmt = $pdo->prepare("
            SELECT b.*, m.name as mountain_name, m.location as mountain_location
            FROM bookings b
            JOIN mountains m ON b.mountain_id = m.id
            WHERE b.guide_id = ? AND b.status IN ('active', 'confirmed')
            ORDER BY b.hike_date DESC
            LIMIT 1
        ");
        $stmt->execute([$guideId]);
        $activeBooking = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Booking lookup error: " . $e->getMessage());
    }
}

// Handle alert creation via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_alert') {
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    $location = $_POST['location'] ?? '';
    $trailName = $_POST['trail_name'] ?? '';
    $type = $_POST['type'] ?? 'safety';
    $severity = $_POST['severity'] ?? 'info';
    $latitude = $_POST['latitude'] ?? null;
    $longitude = $_POST['longitude'] ?? null;
    $bookingNumber = $_POST['booking_number'] ?? ($activeBooking['booking_reference'] ?? null);
    $userId = $_POST['user_id'] ?? $currentUserId;
    
    // Handle image upload
    $imageUrl = null;
    if (isset($_FILES['alert_image']) && $_FILES['alert_image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/../uploads/alerts/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        $ext = pathinfo($_FILES['alert_image']['name'], PATHINFO_EXTENSION);
        $filename = 'alert_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $uploadPath = $uploadDir . $filename;
        if (move_uploaded_file($_FILES['alert_image']['tmp_name'], $uploadPath)) {
            $imageUrl = '/uploads/alerts/' . $filename;
        }
    }
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO alerts (title, description, location, trail_name, type, severity, status, 
                                reported_by, reporter_name, reporter_role, latitude, longitude, 
                                booking_number, user_id, image_url, created_at)
            VALUES (?, ?, ?, ?, ?, ?, 'active', ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $title, $description, $location, $trailName, $type, $severity,
            $currentUserId, $currentUserName, $currentUserRole,
            $latitude, $longitude, $bookingNumber, $userId, $imageUrl
        ]);
        $alertId = $pdo->lastInsertId();
        $success = "Alert created successfully!";
    } catch (PDOException $e) {
        $error = "Failed to create alert: " . $e->getMessage();
    }
}

// Fetch alerts
$alerts = [];
try {
    $stmt = $pdo->prepare("
        SELECT a.*, u.name as reporter_name 
        FROM alerts a
        LEFT JOIN users u ON a.reported_by = u.id
        ORDER BY a.created_at DESC
        LIMIT 50
    ");
    $stmt->execute();
    $alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Alerts fetch error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LAKBAY Guide - Safety & Alerts</title>
    <link rel="stylesheet" href="guide-shared.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .safety-container {
            padding: 24px;
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .safety-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            flex-wrap: wrap;
            gap: 16px;
        }
        
        .safety-header h1 {
            font-size: 1.6rem;
            font-weight: 700;
            color: #100600;
        }
        
        .btn-primary {
            background: #100600;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 30px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
        }
        
        .btn-primary:hover {
            background: #2a1a0f;
            transform: translateY(-1px);
        }
        
        .btn-danger {
            background: #B8312A;
        }
        
        .btn-danger:hover {
            background: #8B1A14;
        }
        
        .btn-outline {
            background: transparent;
            border: 2px solid #100600;
            color: #100600;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 32px;
        }
        
        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            border: 1px solid rgba(0,0,0,0.06);
        }
        
        .stat-card .stat-value {
            font-size: 2rem;
            font-weight: 800;
            color: #100600;
        }
        
        .stat-card .stat-label {
            font-size: 0.75rem;
            color: #888;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: 4px;
        }
        
        .alert-filters {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .filter-btn {
            padding: 6px 16px;
            border-radius: 30px;
            border: 1px solid #ddd;
            background: white;
            cursor: pointer;
            font-size: 0.8rem;
            transition: all 0.2s;
        }
        
        .filter-btn.active {
            background: #100600;
            color: white;
            border-color: #100600;
        }
        
        .alerts-table-container {
            background: white;
            border-radius: 16px;
            overflow-x: auto;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
        }
        
        .alerts-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 800px;
        }
        
        .alerts-table th,
        .alerts-table td {
            padding: 14px 16px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .alerts-table th {
            background: #faf9f7;
            font-weight: 600;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #666;
        }
        
        .alerts-table tr:hover {
            background: #faf9f7;
        }
        
        .severity-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        
        .severity-critical { background: rgba(184,49,42,0.15); color: #B8312A; }
        .severity-high { background: rgba(231,76,60,0.15); color: #E74C3C; }
        .severity-medium { background: rgba(241,196,15,0.15); color: #F1C40F; }
        .severity-low { background: rgba(52,152,219,0.15); color: #3498DB; }
        .severity-info { background: rgba(27,112,69,0.15); color: #1B7045; }
        
        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        
        .status-active { background: #FEF3C7; color: #D97706; }
        .status-acknowledged { background: #DBEAFE; color: #2563EB; }
        .status-resolved { background: #D1FAE5; color: #059669; }
        
        .alert-actions {
            display: flex;
            gap: 8px;
        }
        
        .action-btn {
            background: none;
            border: none;
            cursor: pointer;
            font-size: 1rem;
            padding: 4px 8px;
            border-radius: 6px;
            transition: all 0.2s;
        }
        
        .action-btn:hover {
            background: #eee;
        }
        
        .modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.5);
            backdrop-filter: blur(4px);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .modal.open {
            display: flex;
        }
        
        .modal-content {
            background: white;
            border-radius: 20px;
            max-width: 500px;
            width: 90%;
            max-height: 85vh;
            overflow-y: auto;
            padding: 24px;
        }
        
        .modal-content h3 {
            font-size: 1.3rem;
            margin-bottom: 20px;
            color: #100600;
        }
        
        .form-group {
            margin-bottom: 16px;
        }
        
        .form-group label {
            display: block;
            font-size: 0.75rem;
            font-weight: 600;
            margin-bottom: 6px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 0.85rem;
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }
        
        .modal-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        
        .modal-buttons button {
            flex: 1;
            padding: 12px;
            border: none;
            border-radius: 30px;
            font-weight: 600;
            cursor: pointer;
        }
        
        .alert-detail-modal .modal-content {
            max-width: 600px;
        }
        
        .alert-image {
            max-width: 100%;
            border-radius: 12px;
            margin-top: 12px;
        }
        
        .quick-report {
            background: linear-gradient(135deg, #100600, #2a1a0f);
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 24px;
            color: white;
        }
        
        .quick-report h3 {
            margin-bottom: 12px;
            font-size: 1rem;
        }
        
        .quick-buttons {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        
        .quick-btn {
            background: rgba(255,255,255,0.2);
            border: none;
            padding: 8px 16px;
            border-radius: 30px;
            color: white;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .quick-btn:hover {
            background: rgba(255,255,255,0.3);
        }
        
        @media (max-width: 768px) {
            .safety-container {
                padding: 16px;
            }
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
    <?php if (isset($_GET['embed']) && $_GET['embed'] == '1'): ?>
    <style>
        /* Override styles when embedded inside guide-map.php */
        .guide-main { margin-left: 0 !important; min-height: auto !important; }
        .guide-topbar { display: none !important; }
        body { background: transparent !important; }
    </style>
    <?php endif; ?>
</head>
<body>
<div class="guide-app">
    <div class="guide-main">
        <div class="guide-topbar">
            <div class="topbar-title">
                <i class="fas fa-shield-alt"></i> Safety & Alerts Center
            </div>
            <div class="topbar-right">
                <button class="topbar-icon-btn" onclick="refreshAlerts()" title="Refresh">
                    <i class="fas fa-rotate"></i>
                </button>
            </div>
        </div>
        
        <div class="safety-container">
            <!-- Quick Stats -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value" id="totalAlerts">0</div>
                    <div class="stat-label">Total Alerts</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" id="activeAlerts">0</div>
                    <div class="stat-label">Active</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" id="criticalAlerts">0</div>
                    <div class="stat-label">Critical</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" id="resolvedAlerts">0</div>
                    <div class="stat-label">Resolved</div>
                </div>
            </div>
            
            <!-- Quick Report Buttons -->
            <div class="quick-report">
                <h3><i class="fas fa-bolt"></i> Quick Report</h3>
                <div class="quick-buttons">
                    <button class="quick-btn" onclick="openAlertModal('emergency', 'critical')">
                        <i class="fas fa-ambulance"></i> Emergency
                    </button>
                    <button class="quick-btn" onclick="openAlertModal('injury', 'high')">
                        <i class="fas fa-band-aid"></i> Injury
                    </button>
                    <button class="quick-btn" onclick="openAlertModal('lost_hiker', 'high')">
                        <i class="fas fa-person-walking-arrow-right"></i> Lost Hiker
                    </button>
                    <button class="quick-btn" onclick="openAlertModal('weather', 'medium')">
                        <i class="fas fa-cloud-rain"></i> Weather Alert
                    </button>
                    <button class="quick-btn" onclick="openAlertModal('crowd', 'low')">
                        <i class="fas fa-users"></i> Crowd Issue
                    </button>
                </div>
            </div>
            
            <!-- Alert Filters -->
            <div class="alert-filters">
                <button class="filter-btn active" data-filter="all">All</button>
                <button class="filter-btn" data-filter="active">Active</button>
                <button class="filter-btn" data-filter="acknowledged">Acknowledged</button>
                <button class="filter-btn" data-filter="resolved">Resolved</button>
                <button class="filter-btn" data-filter="critical">Critical</button>
            </div>
            
            <!-- Alerts Table -->
            <div class="alerts-table-container">
                <table class="alerts-table" id="alertsTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Title</th>
                            <th>Type</th>
                            <th>Severity</th>
                            <th>Location</th>
                            <th>Reported By</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($alerts as $alert): ?>
                        <tr data-status="<?= htmlspecialchars($alert['status']) ?>" data-severity="<?= htmlspecialchars($alert['severity']) ?>">
                            <td><?= $alert['id'] ?></td>
                            <td><strong><?= htmlspecialchars($alert['title']) ?></strong></td>
                            <td><?= htmlspecialchars($alert['type']) ?></td>
                            <td><span class="severity-badge severity-<?= $alert['severity'] ?>"><?= ucfirst($alert['severity']) ?></span></td>
                            <td><?= htmlspecialchars($alert['location'] ?: 'N/A') ?></td>
                            <td><?= htmlspecialchars($alert['reporter_name'] ?: $alert['reporter_name']) ?></td>
                            <td><span class="status-badge status-<?= $alert['status'] ?>"><?= ucfirst($alert['status']) ?></span></td>
                            <td><?= date('M d, H:i', strtotime($alert['created_at'])) ?></td>
                            <td class="alert-actions">
                                <button class="action-btn" onclick="viewAlert(<?= $alert['id'] ?>)" title="View">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <?php if ($alert['status'] === 'active'): ?>
                                <button class="action-btn" onclick="acknowledgeAlert(<?= $alert['id'] ?>)" title="Acknowledge">
                                    <i class="fas fa-check-circle"></i>
                                </button>
                                <?php endif; ?>
                                <?php if ($alert['status'] !== 'resolved'): ?>
                                <button class="action-btn" onclick="resolveAlert(<?= $alert['id'] ?>)" title="Resolve">
                                    <i class="fas fa-check-double"></i>
                                </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($alerts)): ?>
                        <tr>
                            <td colspan="9" style="text-align: center; padding: 40px;">No alerts found</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Create Alert Modal -->
<div class="modal" id="alertModal">
    <div class="modal-content">
        <h3><i class="fas fa-exclamation-triangle"></i> Report Alert</h3>
        <form id="alertForm" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="create_alert">
            <div class="form-group">
                <label>Title</label>
                <input type="text" name="title" id="alertTitle" required>
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" id="alertDescription" required></textarea>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Location</label>
                    <input type="text" name="location" id="alertLocation" placeholder="e.g., Trail section, summit">
                </div>
                <div class="form-group">
                    <label>Trail Name</label>
                    <input type="text" name="trail_name" id="alertTrailName" value="<?= htmlspecialchars($activeBooking['mountain_name'] ?? '') ?>">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Type</label>
                    <select name="type" id="alertType">
                        <option value="safety">Safety</option>
                        <option value="emergency">Emergency</option>
                        <option value="injury">Injury</option>
                        <option value="weather">Weather</option>
                        <option value="lost_hiker">Lost Hiker</option>
                        <option value="crowd">Crowd</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Severity</label>
                    <select name="severity" id="alertSeverity">
                        <option value="info">Info</option>
                        <option value="low">Low</option>
                        <option value="medium">Medium</option>
                        <option value="high">High</option>
                        <option value="critical">Critical</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Latitude</label>
                    <input type="text" name="latitude" id="alertLatitude" placeholder="Auto-filled from GPS">
                </div>
                <div class="form-group">
                    <label>Longitude</label>
                    <input type="text" name="longitude" id="alertLongitude" placeholder="Auto-filled from GPS">
                </div>
            </div>
            <div class="form-group">
                <label>Booking Number</label>
                <input type="text" name="booking_number" id="alertBookingNumber" value="<?= htmlspecialchars($activeBooking['booking_reference'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Image (optional)</label>
                <input type="file" name="alert_image" accept="image/*">
            </div>
            <div class="modal-buttons">
                <button type="button" class="btn-outline" onclick="closeAlertModal()">Cancel</button>
                <button type="submit" class="btn-primary">Submit Alert</button>
            </div>
        </form>
    </div>
</div>

<!-- View Alert Modal -->
<div class="modal alert-detail-modal" id="viewAlertModal">
    <div class="modal-content">
        <h3>Alert Details</h3>
        <div id="alertDetailContent"></div>
        <div class="modal-buttons">
            <button type="button" class="btn-outline" onclick="closeViewModal()">Close</button>
        </div>
    </div>
</div>

<script>
// Alert data from PHP
const alertsData = <?= json_encode($alerts) ?>;
let currentFilter = 'all';

// Update stats
function updateStats() {
    const total = alertsData.length;
    const active = alertsData.filter(a => a.status === 'active').length;
    const critical = alertsData.filter(a => a.severity === 'critical' && a.status !== 'resolved').length;
    const resolved = alertsData.filter(a => a.status === 'resolved').length;
    
    document.getElementById('totalAlerts').textContent = total;
    document.getElementById('activeAlerts').textContent = active;
    document.getElementById('criticalAlerts').textContent = critical;
    document.getElementById('resolvedAlerts').textContent = resolved;
}

// Filter alerts
function filterAlerts() {
    const rows = document.querySelectorAll('#alertsTable tbody tr');
    rows.forEach(row => {
        if (row.querySelector('td') && row.querySelector('td').textContent === 'No alerts found') return;
        
        const status = row.dataset.status;
        const severity = row.dataset.severity;
        
        let show = true;
        if (currentFilter === 'active') show = status === 'active';
        else if (currentFilter === 'acknowledged') show = status === 'acknowledged';
        else if (currentFilter === 'resolved') show = status === 'resolved';
        else if (currentFilter === 'critical') show = severity === 'critical' && status !== 'resolved';
        
        row.style.display = show ? '' : 'none';
    });
}

// Modal functions
function openAlertModal(type = null, severity = null) {
    const modal = document.getElementById('alertModal');
    if (type) {
        document.getElementById('alertType').value = type;
        const titles = {
            emergency: '🚨 EMERGENCY',
            injury: '⚠️ Injury Report',
            lost_hiker: '🔍 Lost Hiker',
            weather: '🌧️ Weather Alert',
            crowd: '👥 Crowd Issue'
        };
        if (titles[type]) {
            document.getElementById('alertTitle').value = titles[type];
        }
    }
    if (severity) {
        document.getElementById('alertSeverity').value = severity;
    }
    // Try to get current location
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(pos => {
            document.getElementById('alertLatitude').value = pos.coords.latitude;
            document.getElementById('alertLongitude').value = pos.coords.longitude;
        }, () => {});
    }
    modal.classList.add('open');
}

function closeAlertModal() {
    document.getElementById('alertModal').classList.remove('open');
    document.getElementById('alertForm').reset();
}

function closeViewModal() {
    document.getElementById('viewAlertModal').classList.remove('open');
}

function viewAlert(id) {
    const alert = alertsData.find(a => a.id == id);
    if (!alert) return;
    
    const content = `
        <div class="form-group">
            <label>Title</label>
            <div><strong>${escapeHtml(alert.title)}</strong></div>
        </div>
        <div class="form-group">
            <label>Description</label>
            <div>${escapeHtml(alert.description)}</div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Location</label>
                <div>${escapeHtml(alert.location || 'N/A')}</div>
            </div>
            <div class="form-group">
                <label>Trail</label>
                <div>${escapeHtml(alert.trail_name || 'N/A')}</div>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Type</label>
                <div>${escapeHtml(alert.type)}</div>
            </div>
            <div class="form-group">
                <label>Severity</label>
                <div><span class="severity-badge severity-${alert.severity}">${alert.severity.toUpperCase()}</span></div>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Coordinates</label>
                <div>${alert.latitude ? alert.latitude + ', ' + alert.longitude : 'N/A'}</div>
            </div>
            <div class="form-group">
                <label>Booking #</label>
                <div>${escapeHtml(alert.booking_number || 'N/A')}</div>
            </div>
        </div>
        <div class="form-group">
            <label>Reported By</label>
            <div>${escapeHtml(alert.reporter_name)} (${escapeHtml(alert.reporter_role)})</div>
        </div>
        <div class="form-group">
            <label>Created</label>
            <div>${new Date(alert.created_at).toLocaleString()}</div>
        </div>
        ${alert.image_url ? `<div class="form-group"><label>Image</label><br><img src="${alert.image_url}" class="alert-image" onclick="window.open(this.src)"></div>` : ''}
        ${alert.acknowledged_at ? `<div class="form-group"><label>Acknowledged</label><div>${new Date(alert.acknowledged_at).toLocaleString()}</div></div>` : ''}
        ${alert.resolved_at ? `<div class="form-group"><label>Resolved</label><div>${new Date(alert.resolved_at).toLocaleString()}</div></div>` : ''}
    `;
    
    document.getElementById('alertDetailContent').innerHTML = content;
    document.getElementById('viewAlertModal').classList.add('open');
}

async function acknowledgeAlert(id) {
    if (!confirm('Acknowledge this alert?')) return;
    
    try {
        const res = await fetch('../api/update_alert.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id, action: 'acknowledge' })
        });
        const data = await res.json();
        if (data.success) {
            showToast('Alert acknowledged');
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast('Error: ' + data.message);
        }
    } catch (e) {
        showToast('Network error');
    }
}

async function resolveAlert(id) {
    if (!confirm('Mark this alert as resolved?')) return;
    
    try {
        const res = await fetch('../api/update_alert.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id, action: 'resolve' })
        });
        const data = await res.json();
        if (data.success) {
            showToast('Alert resolved');
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast('Error: ' + data.message);
        }
    } catch (e) {
        showToast('Network error');
    }
}

function refreshAlerts() {
    location.reload();
}

function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

let toastTimer;
function showToast(msg) {
    let t = document.getElementById('toast');
    if (!t) {
        t = document.createElement('div');
        t.id = 'toast';
        t.style.cssText = 'position:fixed;bottom:30px;left:50%;transform:translateX(-50%);background:#100600;color:white;padding:10px20px;border-radius:40px;font-size:0.8rem;z-index:3000;opacity:0;transition:opacity0.3s;pointer-events:none;white-space:nowrap;';
        document.body.appendChild(t);
    }
    t.textContent = msg;
    t.classList.add('show');
    t.style.opacity = '1';
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => t.style.opacity = '0', 2800);
}

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    updateStats();
    
    document.querySelectorAll('.filter-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            currentFilter = btn.dataset.filter;
            filterAlerts();
        });
    });
    
    // Handle form submission
    document.getElementById('alertForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(e.target);
        
        try {
            const res = await fetch(window.location.href, {
                method: 'POST',
                body: formData
            });
            const html = await res.text();
            if (html.includes('success') || html.includes('Alert created')) {
                showToast('Alert created successfully!');
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast('Error creating alert');
            }
        } catch (err) {
            showToast('Network error');
        }
    });
});

// Add style for toast
const style = document.createElement('style');
style.textContent = `
    #toast.show { opacity: 1; }
    #toast { transition: opacity 0.3s; }
`;
document.head.appendChild(style);
</script>
</body>
</html>