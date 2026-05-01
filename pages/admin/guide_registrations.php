<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: /pages/modals/login.php');
    exit;
}

require_once '../../config/db.php';

// Handle approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    $guideId = $_POST['guide_id'] ?? 0;
    
    if ($action === 'approve') {
        $stmt = $pdo->prepare("UPDATE guides SET is_approved = 1, is_available = 1, submitted_at = NULL WHERE id = ?");
        $stmt->execute([$guideId]);
        echo json_encode(['success' => true, 'message' => 'Guide approved successfully', 'type' => 'approve']);
    } elseif ($action === 'reject') {
        // OPTION 1: SOFT DELETE - just mark as rejected (recommended)
        $stmt = $pdo->prepare("UPDATE guides SET is_approved = 2, is_available = 0, submitted_at = NULL WHERE id = ?");
        $stmt->execute([$guideId]);
        
        // OPTION 2: HARD DELETE - permanently remove (uncomment if you want this instead)
        // $stmt = $pdo->prepare("SELECT user_id FROM guides WHERE id = ?");
        // $stmt->execute([$guideId]);
        // $userId = $stmt->fetchColumn();
        // $stmt = $pdo->prepare("DELETE FROM guides WHERE id = ?");
        // $stmt->execute([$guideId]);
        // if ($userId) {
        //     $stmt2 = $pdo->prepare("DELETE FROM users WHERE id = ?");
        //     $stmt2->execute([$userId]);
        // }
        
        echo json_encode(['success' => true, 'message' => 'Registration rejected', 'type' => 'reject']);
    }
    exit;
}

// Count pending registrations (is_approved = 0 = pending)
$pendingStmt = $pdo->prepare("SELECT COUNT(*) FROM guides WHERE is_approved = 0");
$pendingStmt->execute();
$pendingCount = $pendingStmt->fetchColumn();

// Fetch pending guide registrations
$stmt = $pdo->prepare("
    SELECT 
        g.id as guide_id,
        g.user_id,
        g.specialization,
        g.years_experience,
        g.rating,
        g.total_trips,
        g.bio,
        g.id_type,
        g.id_image,
        g.submitted_at,
        u.name,
        u.email,
        u.phone,
        u.created_at as registered_at
    FROM guides g 
    INNER JOIN users u ON g.user_id = u.id 
    WHERE g.is_approved = 0
    ORDER BY g.submitted_at ASC
");
$stmt->execute();
$pendingRegistrations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch approved guides (is_approved = 1)
$stmt = $pdo->prepare("
    SELECT 
        g.id as guide_id,
        u.name,
        u.email,
        u.phone,
        g.specialization,
        g.years_experience,
        g.rating,
        g.total_trips,
        g.submitted_at,
        u.created_at
    FROM guides g 
    INNER JOIN users u ON g.user_id = u.id 
    WHERE g.is_approved = 1
    ORDER BY u.created_at DESC
");
$stmt->execute();
$approvedGuides = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LAKBAY — Guide Registrations</title>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600&family=DM+Mono:wght@400;500&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="shared.css">
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'><path fill='%23254A5A' d='M8 3 3 20h18L14 8l-2 4z'/></svg>">
    <style>
        /* Additional styles for registration cards */
        .registrations-container {
            display: flex;
            flex-direction: column;
            gap: 24px;
            margin-top: 24px;
        }
        .reg-card {
            background: white;
            border-radius: 20px;
            border: 1px solid var(--line);
            overflow: hidden;
            transition: all 0.2s;
        }
        .reg-card:hover {
            box-shadow: 0 8px 24px rgba(0,0,0,0.08);
        }
        .reg-header {
            padding: 20px 24px;
            background: var(--paper);
            border-bottom: 1px solid var(--line);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }
        .reg-name {
            font-size: 18px;
            font-weight: 600;
            color: var(--ink);
        }
        .reg-date {
            font-size: 12px;
            color: var(--ink-4);
            font-family: 'DM Mono', monospace;
        }
        .reg-badge {
            background: #FEF3C7;
            color: #D97706;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        .reg-body {
            padding: 24px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
        }
        .reg-info {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .info-row {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            font-size: 13px;
        }
        .info-label {
            width: 100px;
            font-weight: 600;
            color: var(--ink-3);
            flex-shrink: 0;
        }
        .info-value {
            color: var(--ink);
            flex: 1;
        }
        .id-preview {
            border: 1px solid var(--line);
            border-radius: 12px;
            overflow: hidden;
            background: var(--paper);
        }
        .id-preview img {
            width: 100%;
            max-height: 300px;
            object-fit: contain;
            background: #fff;
        }
        .id-type-badge {
            display: inline-block;
            padding: 4px 10px;
            background: var(--paper-2);
            border-radius: 12px;
            font-size: 11px;
            color: var(--ink-3);
            margin-top: 8px;
        }
        .reg-actions {
            padding: 16px 24px;
            background: var(--paper);
            border-top: 1px solid var(--line);
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }
        .empty-state {
            text-align: center;
            padding: 60px;
            color: var(--ink-4);
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 32px;
        }
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            border: 1px solid var(--line);
        }
        .stat-number {
            font-size: 36px;
            font-weight: 700;
            color: var(--ink);
            margin-bottom: 4px;
        }
        .stat-label {
            font-size: 13px;
            color: var(--ink-4);
        }
        
        /* Toast Notification Styles */
        .toast-container {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 10000;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .toast {
            min-width: 280px;
            max-width: 400px;
            background: white;
            border-radius: 12px;
            padding: 16px 20px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.15);
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideInRight 0.3s ease;
            border-left: 4px solid;
        }
        .toast.success {
            border-left-color: #10B981;
        }
        .toast.success i:first-child {
            color: #10B981;
        }
        .toast.error {
            border-left-color: #EF4444;
        }
        .toast.error i:first-child {
            color: #EF4444;
        }
        .toast.info {
            border-left-color: #3B82F6;
        }
        .toast.info i:first-child {
            color: #3B82F6;
        }
        .toast-content {
            flex: 1;
        }
        .toast-title {
            font-weight: 600;
            font-size: 14px;
            color: var(--ink);
            margin-bottom: 2px;
        }
        .toast-message {
            font-size: 12px;
            color: var(--ink-3);
        }
        .toast-close {
            background: none;
            border: none;
            cursor: pointer;
            color: var(--ink-4);
            font-size: 14px;
            padding: 0;
        }
        .toast-close:hover {
            color: var(--ink);
        }
        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        @keyframes slideOutRight {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(100%);
                opacity: 0;
            }
        }
        .toast.hiding {
            animation: slideOutRight 0.3s ease forwards;
        }
        
        /* Confirm Modal Styles */
        .confirm-modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.5);
            backdrop-filter: blur(4px);
            z-index: 10001;
            align-items: center;
            justify-content: center;
        }
        .confirm-modal-overlay.open {
            display: flex;
        }
        .confirm-modal {
            background: white;
            border-radius: 20px;
            max-width: 400px;
            width: 90%;
            overflow: hidden;
            animation: fadeIn 0.2s ease;
        }
        .confirm-modal-header {
            padding: 20px 24px 0;
        }
        .confirm-modal-icon {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-bottom: 16px;
        }
        .confirm-modal-icon.warning {
            background: #FEF3C7;
            color: #D97706;
        }
        .confirm-modal-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--ink);
            margin-bottom: 8px;
        }
        .confirm-modal-message {
            font-size: 13px;
            color: var(--ink-3);
            line-height: 1.5;
        }
        .confirm-modal-body {
            padding: 0 24px 20px;
        }
        .confirm-modal-footer {
            padding: 16px 24px;
            background: var(--paper);
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            border-top: 1px solid var(--line);
        }
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: scale(0.95);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }
        .btn-sm {
            padding: 8px 16px;
            font-size: 12px;
        }
    </style>
</head>
<body data-page="guide-registrations">
<div class="app">

<!-- Toast Container -->
<div class="toast-container" id="toastContainer"></div>

<!-- Custom Confirm Modal -->
<div class="confirm-modal-overlay" id="confirmModal">
    <div class="confirm-modal">
        <div class="confirm-modal-header">
            <div class="confirm-modal-icon warning" id="confirmIcon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div class="confirm-modal-title" id="confirmTitle">Confirm Action</div>
            <div class="confirm-modal-message" id="confirmMessage"></div>
        </div>
        <div class="confirm-modal-footer">
            <button class="btn btn-ghost btn-sm" id="confirmCancelBtn">Cancel</button>
            <button class="btn btn-primary btn-sm" id="confirmOkBtn">Confirm</button>
        </div>
    </div>
</div>

<!-- SIDEBAR -->
<aside class="sidebar">
    <div>
        <div class="logo">
            <div class="logo-wordmark">
                <div class="logo-icon">
                    <svg viewBox="0 0 28 28" fill="none">
                        <path d="M4 22L10 10L14 16L18 8L24 22H4Z" fill="#111318" opacity="0.9"/>
                        <path d="M14 16L18 8L24 22H14V16Z" fill="#111318" opacity="0.35"/>
                    </svg>
                </div>
                LAKBAY
            </div>
            <div class="logo-sub">wilderness: Silence beneath steps</div>
        </div>
        <div style="margin-bottom:8px; padding-left:28px;">
            <div class="nav-section-label">Navigation</div>
        </div>
        <ul class="nav-list">
            <li class="nav-item" data-href="/pages/dashboard-admin.php"><i class="fas fa-chart-line"></i> Dashboard</li>
            <li class="nav-item" data-href="/pages/admin/mountains.php"><i class="fas fa-mountain"></i> Mountains</li>
            <li class="nav-item" data-href="/pages/admin/hikers.php"><i class="fas fa-person-hiking"></i> Hikers</li>
            <li class="nav-item" data-href="/pages/admin/guides.php"><i class="fas fa-chalkboard-user"></i> Guides</li>
            <div class="nav-divider"></div>
            <li class="nav-item active" data-href="/pages/admin/guide_registrations.php"><i class="fas fa-clipboard-list"></i> Registrations</li>
        </ul>
    </div>
    <div>
        <button class="logout-btn" onclick="showLogoutModal()">
            <i class="fas fa-right-from-bracket"></i> 
            Log Out
        </button>
        <div class="sidebar-footer">
            <div class="status-dot"></div> 
            TEAM AURIX
        </div>
    </div>
</aside>

<!-- MAIN CONTENT -->
<div class="main">
    <div class="topbar">
        <div class="page-heading">
            <i class="fas fa-clipboard-list"></i> Guide Registrations
        </div>
        <div class="topbar-right">
            <div class="topbar-date" id="liveDate"></div>
            <div class="topbar-user" style="cursor:pointer;">
                <div class="avatar" id="topbarAvatar">
                    <?php 
                    $adminInitial = strtoupper(substr($_SESSION['user_name'] ?? 'A', 0, 1));
                    echo htmlspecialchars($adminInitial);
                    ?>
                </div>
                <?= htmlspecialchars($_SESSION['user_name'] ?? 'Admin') ?>
                <i class="fas fa-chevron-down" style="font-size:0.5rem;color:var(--ink-4);"></i>
            </div>
        </div>
    </div>

    <div class="content">
        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?= count($pendingRegistrations) ?></div>
                <div class="stat-label">Pending Approvals</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= count($approvedGuides) ?></div>
                <div class="stat-label">Approved Guides</div>
            </div>
        </div>

        <!-- Pending Registrations -->
        <div class="section-header">
            <div class="section-title">Pending Approval</div>
            <button class="btn btn-ghost" onclick="window.location.href='guides.php'">
                <i class="fas fa-arrow-left"></i> Back to Guides
            </button>
        </div>

        <div class="registrations-container">
            <?php if (empty($pendingRegistrations)): ?>
                <div class="empty-state">
                    <i class="fas fa-check-circle" style="font-size: 48px; color: var(--accent); margin-bottom: 16px; display: block;"></i>
                    No pending registrations
                </div>
            <?php else: ?>
                <?php foreach ($pendingRegistrations as $reg): ?>
                <div class="reg-card" id="reg-card-<?= $reg['guide_id'] ?>">
                    <div class="reg-header">
                        <div>
                            <div class="reg-name"><?= htmlspecialchars($reg['name']) ?></div>
                            <div class="reg-date">Submitted: <?= date('F d, Y h:i A', strtotime($reg['submitted_at'])) ?></div>
                        </div>
                        <div class="reg-badge">Pending Review</div>
                    </div>
                    <div class="reg-body">
                        <div class="reg-info">
                            <div class="info-row">
                                <span class="info-label">Email:</span>
                                <span class="info-value"><?= htmlspecialchars($reg['email']) ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Phone:</span>
                                <span class="info-value"><?= htmlspecialchars($reg['phone']) ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Specialization:</span>
                                <span class="info-value"><?= htmlspecialchars($reg['specialization'] ?? 'Not specified') ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Experience:</span>
                                <span class="info-value"><?= $reg['years_experience'] ?> years</span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Bio:</span>
                                <span class="info-value"><?= nl2br(htmlspecialchars(substr($reg['bio'] ?? '', 0, 200))) ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">ID Type:</span>
                                <span class="info-value"><span class="id-type-badge"><?= htmlspecialchars($reg['id_type'] ?? 'Not specified') ?></span></span>
                            </div>
                        </div>
                        <div class="id-preview">
                            <?php if (!empty($reg['id_image']) && file_exists('../../' . $reg['id_image'])): ?>
                                <img src="../../<?= htmlspecialchars($reg['id_image']) ?>" alt="Government ID" onclick="this.requestFullscreen()" style="cursor: pointer;">
                            <?php else: ?>
                                <div style="padding: 60px; text-align: center; color: var(--ink-4);">
                                    <i class="fas fa-image" style="font-size: 48px; margin-bottom: 12px; display: block;"></i>
                                    No ID image uploaded
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="reg-actions">
                        <button class="btn btn-danger" onclick="rejectRegistration(<?= $reg['guide_id'] ?>, '<?= htmlspecialchars(addslashes($reg['name'])) ?>')">
                            <i class="fas fa-times"></i> Reject
                        </button>
                        <button class="btn btn-primary" onclick="approveRegistration(<?= $reg['guide_id'] ?>, '<?= htmlspecialchars(addslashes($reg['name'])) ?>')">
                            <i class="fas fa-check"></i> Approve & Activate
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Toast notification system
function showToast(message, type = 'success', title = '') {
    const container = document.getElementById('toastContainer');
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    
    const titles = {
        success: 'Success',
        error: 'Error',
        info: 'Information'
    };
    
    const icons = {
        success: 'fa-circle-check',
        error: 'fa-circle-exclamation',
        info: 'fa-circle-info'
    };
    
    toast.innerHTML = `
        <i class="fas ${icons[type]}" style="font-size: 20px;"></i>
        <div class="toast-content">
            <div class="toast-title">${title || titles[type]}</div>
            <div class="toast-message">${message}</div>
        </div>
        <button class="toast-close" onclick="this.closest('.toast').remove()">
            <i class="fas fa-times"></i>
        </button>
    `;
    
    container.appendChild(toast);
    
    // Auto remove after 4 seconds
    setTimeout(() => {
        if (toast && toast.parentNode) {
            toast.classList.add('hiding');
            setTimeout(() => {
                if (toast && toast.parentNode) toast.remove();
            }, 300);
        }
    }, 4000);
}

// Custom confirm modal (replaces window.confirm)
function showConfirm(message, onConfirm, onCancel = null) {
    const modal = document.getElementById('confirmModal');
    const messageEl = document.getElementById('confirmMessage');
    const confirmBtn = document.getElementById('confirmOkBtn');
    const cancelBtn = document.getElementById('confirmCancelBtn');
    
    messageEl.textContent = message;
    modal.classList.add('open');
    
    const handleConfirm = () => {
        modal.classList.remove('open');
        if (onConfirm) onConfirm();
        cleanup();
    };
    
    const handleCancel = () => {
        modal.classList.remove('open');
        if (onCancel) onCancel();
        cleanup();
    };
    
    const cleanup = () => {
        confirmBtn.removeEventListener('click', handleConfirm);
        cancelBtn.removeEventListener('click', handleCancel);
    };
    
    confirmBtn.addEventListener('click', handleConfirm);
    cancelBtn.addEventListener('click', handleCancel);
}

// Sidebar navigation
document.querySelectorAll('.nav-item[data-href]').forEach(item => {
    item.addEventListener('click', () => {
        window.location.href = item.getAttribute('data-href');
    });
});

// Live date
function updateDate() {
    const d = new Date();
    const el = document.getElementById('liveDate');
    if (el) el.textContent = d.toLocaleDateString('en-PH',{weekday:'short',month:'short',day:'numeric'}).toUpperCase()
        + ' ' + d.toLocaleTimeString('en-PH',{hour:'2-digit',minute:'2-digit'});
}
setInterval(updateDate, 1000);
updateDate();

async function approveRegistration(guideId, guideName) {
    showConfirm(
        `Approve ${guideName} as a verified guide? They will be able to accept bookings immediately.`,
        async () => {
            // Show loading state
            showToast(`Approving ${guideName}...`, 'info', 'Processing');
            
            const formData = new FormData();
            formData.append('action', 'approve');
            formData.append('guide_id', guideId);
            
            try {
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: formData
                });
                
                const result = await response.json();
                if (result.success) {
                    showToast(`${guideName} has been approved as a guide!`, 'success', 'Guide Approved');
                    // Remove the card from the page
                    const card = document.getElementById(`reg-card-${guideId}`);
                    if (card) {
                        card.style.transition = 'opacity 0.4s, transform 0.4s';
                        card.style.opacity = '0';
                        card.style.transform = 'translateX(20px)';
                        setTimeout(() => card.remove(), 400);
                    }
                    // Reload after 1.5 seconds to update counts
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showToast('Error: ' + result.message, 'error', 'Approval Failed');
                }
            } catch (error) {
                showToast('An error occurred while approving.', 'error', 'Error');
            }
        }
    );
}

async function rejectRegistration(guideId, guideName) {
    showConfirm(
        `Reject ${guideName}'s registration? They will be marked as rejected and will not be able to become a guide.`,
        async () => {
            showToast(`Rejecting ${guideName}...`, 'info', 'Processing');
            
            const formData = new FormData();
            formData.append('action', 'reject');
            formData.append('guide_id', guideId);
            
            try {
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: formData
                });
                
                const result = await response.json();
                if (result.success) {
                    showToast(`${guideName}'s registration has been rejected.`, 'error', 'Registration Rejected');
                    // Remove the card from the page
                    const card = document.getElementById(`reg-card-${guideId}`);
                    if (card) {
                        card.style.transition = 'opacity 0.4s, transform 0.4s';
                        card.style.opacity = '0';
                        card.style.transform = 'translateX(20px)';
                        setTimeout(() => card.remove(), 400);
                    }
                    // Reload after 1.5 seconds to update counts
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showToast('Error: ' + result.message, 'error', 'Rejection Failed');
                }
            } catch (error) {
                showToast('An error occurred while rejecting.', 'error', 'Error');
            }
        }
    );
}

// Logout function
function showLogoutModal() {
    showConfirm('Are you sure you want to logout?', () => {
        window.location.href = '/pages/modals/logout.php';
    });
}
</script>

<?php
$logoutModalPath = __DIR__ . '/../../includes/logout-modal.php';
if (file_exists($logoutModalPath)) include_once $logoutModalPath;
?>
</body>
</html>