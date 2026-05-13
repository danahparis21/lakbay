<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: /pages/modals/login.php');
    exit;
}

require_once '../../config/db.php';

$adminName    = $_SESSION['user_name'];
$adminInitial = strtoupper(substr($adminName, 0, 1));

// Fetch guides with user information
$stmt = $pdo->prepare("
    SELECT 
        g.id as guide_id,
        g.user_id,
        g.specialization,
        g.years_experience,
        g.rating,
        g.total_trips,
        g.is_available,
        g.bio,
        u.name,
        u.email,
        u.phone,
        u.avatar
    FROM guides g 
    INNER JOIN users u ON g.user_id = u.id 
    WHERE g.is_available = 1
    ORDER BY g.rating DESC
");
$stmt->execute();
$guides = $stmt->fetchAll(PDO::FETCH_ASSOC);

$guidesWithMountains = [];
foreach ($guides as $guide) {
    $stmt = $pdo->prepare("
        SELECT m.id, m.name 
        FROM guide_mountains gm 
        JOIN mountains m ON gm.mountain_id = m.id 
        WHERE gm.guide_id = ?
    ");
    $stmt->execute([$guide['guide_id']]);
    $guide['mountains'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $guidesWithMountains[] = $guide;
}
$guides = $guidesWithMountains;

// Fetch inactive guides
$stmt = $pdo->prepare("
    SELECT 
        g.id as guide_id,
        g.user_id,
        g.specialization,
        g.years_experience,
        g.rating,
        g.total_trips,
        g.is_available,
        g.bio,
        u.name,
        u.email,
        u.phone,
        u.avatar
    FROM guides g 
    INNER JOIN users u ON g.user_id = u.id 
    WHERE g.is_available = 0
    ORDER BY g.rating DESC
");
$stmt->execute();
$inactiveGuides = $stmt->fetchAll(PDO::FETCH_ASSOC);

$inactiveGuidesWithMountains = [];
foreach ($inactiveGuides as $guide) {
    $stmt = $pdo->prepare("
        SELECT m.id, m.name 
        FROM guide_mountains gm 
        JOIN mountains m ON gm.mountain_id = m.id 
        WHERE gm.guide_id = ?
    ");
    $stmt->execute([$guide['guide_id']]);
    $guide['mountains'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $inactiveGuidesWithMountains[] = $guide;
}
$inactiveGuides = $inactiveGuidesWithMountains;

// Fetch all mountains for checklists
$stmt      = $pdo->query("SELECT id, name FROM mountains ORDER BY name");
$mountains = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch broadcasts/announcements
$stmt = $pdo->prepare("
    SELECT b.*, u.name as sender_name 
    FROM broadcasts b 
    JOIN users u ON b.sender_id = u.id 
    ORDER BY b.created_at DESC 
    LIMIT 50
");
$stmt->execute();
$broadcasts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Top guide per mountain
$stmt = $pdo->query("
    SELECT 
        m.id as mountain_id,
        m.name as mountain_name,
        g.id as guide_id,
        u.name as guide_name,
        g.rating,
        g.total_trips
    FROM mountains m
    JOIN guide_mountains gm ON m.id = gm.mountain_id
    JOIN guides g ON gm.guide_id = g.id
    JOIN users u ON g.user_id = u.id
    WHERE g.is_available = 1
    ORDER BY m.id, g.rating DESC, g.total_trips DESC
");
$allGuidesPerMountain = $stmt->fetchAll(PDO::FETCH_ASSOC);
$topGuidesPerMountain = [];
foreach ($allGuidesPerMountain as $g) {
    $mid = $g['mountain_id'];
    if (!isset($topGuidesPerMountain[$mid])) $topGuidesPerMountain[$mid] = $g;
}
$topGuidesPerMountain = array_values($topGuidesPerMountain);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LAKBAY — Guides</title>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600&family=DM+Mono:wght@400;500&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="shared.css">
      <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'><path fill='%23254A5A' d='M8 3 3 20h18L14 8l-2 4z'/></svg>">
    <style>
        /* ─── MODAL STYLES ─── */
        .modal-overlay {
            display: none; position: fixed; inset: 0;
            background: rgba(10,12,18,0.55); backdrop-filter: blur(4px);
            z-index: 1000; align-items: center; justify-content: center; padding: 20px;
        }
        .modal-overlay.open { display: flex; }
        .modal-box {
            background: #fff; border-radius: 28px;
            width: 100%; max-width: 540px; max-height: 90vh; overflow-y: auto;
            box-shadow: 0 32px 64px rgba(0,0,0,0.18);
            display: flex; flex-direction: column; position: relative;
        }
        .modal-header {
            display: flex; align-items: center; justify-content: space-between;
            padding: 24px 28px 16px; border-bottom: 1px solid #EFF2F6;
            position: sticky; top: 0; background: white; z-index: 2;
        }
        .modal-title  { font-family: 'Cormorant Garamond', serif; font-size: 1.5rem; font-weight: 600; color: #111318; }
        .modal-subtitle { font-size: 11px; letter-spacing: 0.8px; text-transform: uppercase; color: #8A99AE; margin-top: 4px; font-weight: 600; }
        .modal-close  {
            width: 36px; height: 36px; border-radius: 50%; border: none;
            background: #F2F4F8; cursor: pointer; display: flex; align-items: center;
            justify-content: center; color: #5B6A7E; transition: 0.15s;
        }
        .modal-body   { padding: 20px 28px 28px; flex: 1; }
        .modal-footer {
            padding: 16px 28px 24px; display: flex; justify-content: flex-end; gap: 10px;
            border-top: 1px solid #EFF2F6; position: sticky; bottom: 0; background: white; z-index: 2;
        }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .form-group.full { grid-column: 1 / -1; }
        .form-label { font-size: 11px; font-weight: 600; letter-spacing: 0.8px; text-transform: uppercase; color: #6C7A8E; margin-bottom: 5px; display: block; }
        .form-label .req { color: #E67E22; margin-left: 2px; }
        .form-control {
            border: 1.5px solid #E5E9EF; border-radius: 12px; padding: 10px 14px; width: 100%;
            font-size: 13.5px; font-family: 'Inter', sans-serif; background: #FAFBFC; outline: none; transition: 0.15s;
            box-sizing: border-box;
        }
        .form-control:focus { border-color: #111318; background: #fff; }
        .mtn-checklist {
            display: grid; grid-template-columns: 1fr 1fr; gap: 10px;
            background: #F8F9FB; padding: 16px; border-radius: 14px; border: 1.5px solid #E5E9EF;
            max-height: 200px; overflow-y: auto;
        }
        .check-item { display: flex; align-items: center; gap: 10px; font-size: 13px; color: #111318; cursor: pointer; }
        .confirm-box { background: #F8F9FB; border: 1.5px solid #E5E9EF; border-radius: 16px; padding: 18px; }
        .confirm-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #EDF0F4; font-size: 13px; }
        .step-indicator { display: flex; align-items: center; padding: 16px 28px 0; }
        .step-dot { display: flex; align-items: center; gap: 8px; font-size: 12px; font-weight: 600; color: #B0BAC8; font-family: 'DM Mono', monospace; }
        .step-dot.active { color: #111318; }
        .step-dot span { width: 24px; height: 24px; border-radius: 50%; background: #EFF2F6; display: flex; align-items: center; justify-content: center; font-size: 11px; }
        .step-dot.active span { background: #111318; color: white; }
        .step-line { flex: 1; height: 1px; background: #E5E9EF; margin: 0 10px; }

        /* ─── TOP GUIDE CARD ─── */
        .top-guide-card {
            background: #111318; border-radius: 16px; padding: 18px 20px;
            margin: 16px 16px 8px; position: relative; overflow: hidden;
        }
        .top-guide-card::before {
            content: ''; position: absolute; right: -24px; top: -24px;
            width: 110px; height: 110px; border-radius: 50%; background: rgba(255,255,255,0.04);
        }
        .top-guide-label  { font-size: 9px; letter-spacing: 1.5px; text-transform: uppercase; color: rgba(255,255,255,0.4); margin-bottom: 10px; }
        .top-guide-row    { display: flex; align-items: center; gap: 14px; }
        .top-guide-avatar { width: 48px; height: 48px; border-radius: 50%; background: rgba(255,255,255,0.12); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 16px; flex-shrink: 0; }
        .top-guide-name   { font-size: 16px; font-weight: 600; color: #fff; }
        .top-guide-sub    { font-size: 12px; color: rgba(255,255,255,0.45); margin-top: 2px; }
        .top-guide-rating { font-size: 13px; color: #FBBF24; margin-top: 4px; }

        /* ─── GUIDE ROW ACTIONS ─── */
        .guide-row { transition: background-color 0.2s; }
        .guide-row:hover { background-color: #F8F9FB; }
        .guide-actions { display: flex; gap: 6px; flex-shrink: 0; }
        .btn-icon {
            width: 34px; height: 34px; border-radius: 10px; border: 1.5px solid #E5E9EF;
            background: #fff; cursor: pointer; display: flex; align-items: center;
            justify-content: center; font-size: 13px; color: #5B6A7E;
            transition: all 0.15s; flex-shrink: 0;
        }
        .btn-icon:hover { border-color: #111318; color: #111318; background: #F8F9FB; }
        .btn-icon.danger:hover { border-color: #DC2626; color: #DC2626; background: #FEF2F2; }
        .btn-icon.message:hover { border-color: #1D9E75; color: #1D9E75; background: #F0FFF6; }

        /* ─── TOP GUIDE PER MOUNTAIN ─── */
        .mtn-top-section { padding: 16px 0 0; max-height: 400px; overflow-y: auto; }
        .mtn-top-item { display: flex; align-items: center; gap: 14px; padding: 12px 16px; border-bottom: 1px solid #EFF2F6; }
        .mtn-top-item:last-child { border-bottom: none; }
        .mtn-tag { font-size: 10px; font-weight: 600; letter-spacing: 0.6px; background: #F2F4F8; color: #5B6A7E; padding: 4px 10px; border-radius: 20px; white-space: nowrap; min-width: 80px; text-align: center; }
        .mtn-top-info   { flex: 1; }
        .mtn-top-name   { font-size: 13px; font-weight: 600; color: #111318; }
        .mtn-top-meta   { font-size: 11px; color: #5B6A7E; margin-top: 1px; }
        .mtn-top-avatar { width: 34px; height: 34px; border-radius: 50%; background: #111318; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 600; flex-shrink: 0; }

        /* ─── BROADCAST ─── */
        .broadcast-log-item { padding: 14px 0; border-bottom: 1px solid #EFF2F6; }
        .broadcast-log-item:last-child { border-bottom: none; }
        .broadcast-log-msg  { font-size: 13px; color: #111318; line-height: 1.5; }
        .broadcast-log-ts   { font-size: 11px; color: #8A99AE; margin-top: 4px; font-family: 'DM Mono', monospace; }

        /* ─── LOGOUT ─── */
        .logout-btn {
            display: flex; align-items: center; gap: 12px; width: 100%;
            padding: 10px 16px; margin: 8px 0; background: transparent; border: none;
            border-radius: 8px; font-family: 'Inter', sans-serif; font-size: 0.82rem;
            font-weight: 400; color: #dc2626; cursor: pointer; transition: all 0.2s; text-align: left;
        }
        .logout-btn i { width: 16px; font-size: 0.75rem; color: #dc2626; }
        .logout-btn:hover { background: #fef2f2; color: #b91c1c; }

        /* ─── MISC ─── */
        .btn-success  { background: #1E7B48; color: white; }
        .btn-danger   { background: #DC2626; color: white; }
        .btn-danger:hover { background: #B91C1C; }

        /* ─── DEACTIVATE CONFIRM MODAL ─── */
        .deactivate-guide-info {
            background: #FEF2F2; border: 1.5px solid #FECACA;
            border-radius: 14px; padding: 16px; margin-bottom: 20px;
            display: flex; align-items: center; gap: 14px;
        }
        .deactivate-avatar {
            width: 48px; height: 48px; border-radius: 50%; background: #111318;
            color: #fff; display: flex; align-items: center; justify-content: center;
            font-weight: 600; font-size: 16px; flex-shrink: 0;
        }
        .deactivate-note {
            font-size: 13px; color: #5B6A7E; line-height: 1.6;
        }
        .deactivate-note strong { color: #111318; }
    </style>
</head>
<body data-page="guides">
<div class="app">

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
            <li class="nav-item active" data-href="/pages/admin/guides.php"><i class="fas fa-chalkboard-user"></i> Guides</li>
            <div class="nav-divider"></div>
            <li class="nav-item" data-href="/pages/admin/payments.php"><i class="fas fa-coins"></i> Revenue</li>
            <li class="nav-item" data-href="/pages/admin/reviews.php"><i class="fas fa-star"></i> Reviews</li>
            <li class="nav-item" data-href="/pages/admin/alerts.php"><i class="fas fa-bell"></i> Alerts</li>
            <li class="nav-item" data-href="/pages/admin/analytics.php"><i class="fas fa-chart-simple"></i> Analytics</li>
        </ul>
    </div>
    <div>
        <button class="logout-btn" onclick="showLogoutModal()" style="width:100%;display:flex;align-items:center;gap:12px;padding:10px 16px;background:transparent;border:none;border-radius:8px;font-family:'Inter',sans-serif;font-size:0.82rem;font-weight:400;color:#dc2626;cursor:pointer;">
            <i class="fas fa-right-from-bracket" style="width:16px;font-size:0.75rem;"></i> 
            Log Out
        </button>
        <div class="sidebar-footer">
            <div class="status-dot"></div> 
            TEAM AURIX
        </div>
    </div>
</aside>

<!-- ═══ MAIN ═══ -->
<div class="main">
    <div class="topbar">
        <div class="page-heading"><i class="fas fa-chalkboard-user"></i> Guides</div>
        <div class="topbar-right">
            <div class="topbar-date" id="liveDate"></div>
            <div class="topbar-user" style="cursor:pointer;">
                <div class="avatar" id="topbarAvatar">
                    <?php if (!empty($_SESSION['user_avatar'])): ?>
                        <img src="<?= htmlspecialchars($_SESSION['user_avatar']) ?>" alt="Avatar" style="width:100%;height:100%;border-radius:50%;object-fit:cover;">
                    <?php else: ?>
                        <?= htmlspecialchars($adminInitial) ?>
                    <?php endif; ?>
                </div>
                <?= htmlspecialchars($adminName) ?>
                <i class="fas fa-chevron-down" style="font-size:0.5rem;color:var(--ink-4);"></i>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="section-header">
            <div class="section-title">Tour Guides</div>
            <div style="display:flex;gap:8px;">
                <button class="btn btn-primary" id="addGuideBtn">
                    <i class="fas fa-user-plus"></i> <span class="btn-responsive-text">Add Guide</span>
                </button>
                <button class="btn" id="broadcastBtn">
                    <i class="fas fa-bullhorn"></i> <span class="btn-responsive-text">Broadcast</span>
                </button>
                <!-- Add this button next to the existing buttons -->
<button class="btn btn-primary" id="viewRegistrationsBtn" style="background: #1D9E75; border-color: #1D9E75;">
    <i class="fas fa-clipboard-list"></i> <span class="btn-responsive-text">Registration Requests</span>
    <?php
    // Count pending registrations
    $pendingStmt = $pdo->prepare("SELECT COUNT(*) FROM guides WHERE is_approved = 0 AND is_available = 1");
    $pendingStmt->execute();
    $pendingCount = $pendingStmt->fetchColumn();
    if ($pendingCount > 0): ?>
    <span style="background: #dc2626; color: white; border-radius: 20px; padding: 2px 8px; font-size: 11px; margin-left: 6px;"><?= $pendingCount ?></span>
    <?php endif; ?>
</button>

            </div>
        </div>

        <div class="two-col">
            <!-- LEFT COLUMN -->
            <div>
                <div class="panel">
                    <div class="panel-header">
                        <span class="panel-title">Active Guides</span>
                        <span class="panel-badge" id="guideCount"><?= count($guides) ?> on roster</span>
                    </div>

                    <!-- Top performing guide card -->
                    <?php if (!empty($guides)):
                        $topGuide           = $guides[0];
                        $topInitial         = strtoupper(substr($topGuide['name'], 0, 1));
                        $topMountainNames   = array_column($topGuide['mountains'], 'name');
                        $topMountainsDisplay = !empty($topMountainNames) ? implode(', ', $topMountainNames) : 'No mountains';
                    ?>
                    <div class="top-guide-card">
                        <div class="top-guide-label">⭐ Top Performing Guide</div>
                        <div class="top-guide-row">
                            <div class="top-guide-avatar"><?= htmlspecialchars($topInitial) ?></div>
                            <div>
                                <div class="top-guide-name"><?= htmlspecialchars($topGuide['name']) ?></div>
                                <div class="top-guide-sub"><?= htmlspecialchars($topMountainsDisplay) ?> · <?= $topGuide['total_trips'] ?> trips</div>
                                <div class="top-guide-rating">★ <?= number_format($topGuide['rating'], 1) ?></div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Guide rows -->
                    <div id="guidesContainer">
                        <?php if (!empty($guides)): ?>
                            <?php foreach ($guides as $guide):
                                $initial         = strtoupper(substr($guide['name'], 0, 1));
                                $mountainNames   = array_column($guide['mountains'], 'name');
                                $mountainIds     = array_column($guide['mountains'], 'id');
                                $mountainsDisplay = !empty($mountainNames) ? implode(', ', $mountainNames) : 'No mountains assigned';
                            ?>
                            <div class="guide-row" id="guide-row-<?= $guide['guide_id'] ?>"
                                 style="display:flex;align-items:center;gap:16px;padding:16px;border-bottom:1px solid #EFF2F6;">

                                <div style="width:44px;height:44px;background:#111318;color:white;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:600;flex-shrink:0;">
                                    <?= htmlspecialchars($initial) ?>
                                </div>

                                <div style="flex:1;min-width:0;">
                                    <div style="font-weight:600;color:#111318;"><?= htmlspecialchars($guide['name']) ?></div>
                                    <div style="font-size:12px;color:#5B6A7E;">
                                        <?= htmlspecialchars($mountainsDisplay) ?> · ★ <?= number_format($guide['rating'], 1) ?>
                                    </div>
                                </div>

                                <!-- Action buttons -->
<div class="guide-actions">
    <!-- Edit -->
    <button class="btn-icon"
            title="Edit guide"
            onclick="openEditModal(<?= htmlspecialchars(json_encode([
                'guide_id'        => $guide['guide_id'],
                'name'            => $guide['name'],
                'phone'           => $guide['phone'] ?? '',
                'specialization'  => $guide['specialization'] ?? '',
                'years_experience'=> $guide['years_experience'] ?? '',
                'bio'             => $guide['bio'] ?? '',
                'mountain_ids'    => $mountainIds,
            ])) ?>)">
        <i class="fas fa-pen-to-square"></i>
    </button>
    <!-- Deactivate -->
    <button class="btn-icon danger"
            title="Remove from roster"
            onclick="openDeactivateModal(<?= $guide['guide_id'] ?>, '<?= htmlspecialchars(addslashes($guide['name'])) ?>', '<?= htmlspecialchars($initial) ?>')">
        <i class="fas fa-user-slash"></i>
    </button>
</div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div style="text-align:center;padding:40px;color:#8A99AE;">
                                No active guides found. Click "Add Guide" to onboard new guides.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

               

                              
                <!-- Top guide per mountain -->
                <div class="panel" style="margin-top:20px;">
                    <div class="panel-header">
                        <span class="panel-title">Top Guide per Mountain</span>
                    </div>
                    <div class="mtn-top-section">
                        <?php foreach ($topGuidesPerMountain as $top):
                            $initial = strtoupper(substr($top['guide_name'], 0, 1));
                        ?>
                        <div class="mtn-top-item">
                            <div class="mtn-top-avatar"><?= htmlspecialchars($initial) ?></div>
                            <div class="mtn-top-info">
                                <div class="mtn-top-name"><?= htmlspecialchars($top['guide_name']) ?></div>
                                <div class="mtn-top-meta">★ <?= number_format($top['rating'], 1) ?> · <?= $top['total_trips'] ?> trips</div>
                            </div>
                            <div class="mtn-tag"><?= htmlspecialchars($top['mountain_name']) ?></div>
                        </div>
                        <?php endforeach; ?>
                        <?php if (empty($topGuidesPerMountain)): ?>
                        <div style="text-align:center;padding:40px;color:#8A99AE;">No mountain assignments found.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

                        <!-- RIGHT COLUMN -->
            <div style="display: flex; flex-direction: column; gap: 20px;">
                <!-- Send Announcement Panel -->
                <div class="panel">
                    <div class="panel-header">
                        <span class="panel-title">Send Announcement</span>
                        <span class="panel-badge">Broadcast</span>
                    </div>
                    <textarea id="announceTxt" rows="4" class="form-control" style="width:100%;margin-bottom:12px;" placeholder="Write announcement to all guides..."></textarea>
                    <button class="btn btn-primary" id="sendAnnounceBtn" style="width:100%">
                        <i class="fas fa-paper-plane"></i> Send to All Guides
                    </button>
                </div>

                <!-- Inactive Guides Panel (moved here) -->
                <div class="panel">
                    <div class="panel-header">
                        <span class="panel-title">Inactive Guides</span>
                        <span class="panel-badge"><?= count($inactiveGuides) ?> inactive</span>
                    </div>
                    
                    <?php if (!empty($inactiveGuides)): ?>
                        <div style="max-height: 400px; overflow-y: auto;">
                            <?php foreach ($inactiveGuides as $guide):
                                $initial         = strtoupper(substr($guide['name'], 0, 1));
                                $mountainNames   = array_column($guide['mountains'], 'name');
                                $mountainsDisplay = !empty($mountainNames) ? implode(', ', $mountainNames) : 'No mountains assigned';
                            ?>
                            <div class="guide-row" id="inactive-guide-row-<?= $guide['guide_id'] ?>"
                                 style="display:flex;align-items:center;gap:12px;padding:12px;border-bottom:1px solid #EFF2F6; opacity: 0.75;">

                                <div style="width:36px;height:36px;background:#E5E9EF;color:#5B6A7E;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:600;font-size:12px;flex-shrink:0;">
                                    <?= htmlspecialchars($initial) ?>
                                </div>

                                <div style="flex:1;min-width:0;">
                                    <div style="font-weight:500;font-size:13px;color:#5B6A7E;"><?= htmlspecialchars($guide['name']) ?></div>
                                    <div style="font-size:11px;color:#8A99AE; margin-top: 2px;">
                                        <?= htmlspecialchars($mountainsDisplay) ?>
                                    </div>
                                </div>

                                <!-- Reactivate button -->
                                <button class="btn-icon" style="width:32px;height:32px;color:#1D9E75;border-color:#1D9E75;"
                                        title="Reactivate guide"
                                        onclick="reactivateGuide(<?= $guide['guide_id'] ?>, '<?= htmlspecialchars(addslashes($guide['name'])) ?>')">
                                    <i class="fas fa-user-plus" style="font-size:12px;"></i>
                                </button>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div style="text-align:center;padding:40px 20px;color:#8A99AE;">
                            <i class="fas fa-check-circle" style="font-size:32px;margin-bottom:10px;display:block;opacity:0.5;"></i>
                            No inactive guides
                        </div>
                    <?php endif; ?>
                </div>
            </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════
     ADD GUIDE MODAL
═══════════════════════════════════════════ -->
<div class="modal-overlay" id="addGuideModal">
    <div class="modal-box">
        <div class="modal-header">
            <div>
                <div class="modal-title">Onboard New Guide</div>
                <div class="modal-subtitle" id="modalStepLabel">STEP 1 OF 2 · REGISTRATION</div>
            </div>
            <button class="modal-close" onclick="closeAddModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="step-indicator">
            <div class="step-dot active" id="gdot1"><span>1</span>&nbsp;Form</div>
            <div class="step-line"></div>
            <div class="step-dot" id="gdot2"><span>2</span>&nbsp;Confirm</div>
        </div>
        <div class="modal-body" id="gStep1">
            <form id="addGuideForm">
                <div class="form-grid">
                    <div class="form-group full">
                        <label class="form-label">Full Name <span class="req">*</span></label>
                        <input type="text" class="form-control" name="name" id="gName" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Age <span class="req">*</span></label>
                        <input type="number" class="form-control" name="age" id="gAge" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Mobile <span class="req">*</span></label>
                        <input type="tel" class="form-control" name="phone" id="gMobile" required>
                    </div>
                    <div class="form-group full">
                        <label class="form-label">Email <span class="req">*</span></label>
                        <input type="email" class="form-control" name="email" id="gEmail" required>
                    </div>
                    <div class="form-group full">
                        <label class="form-label">Password <span class="req">*</span></label>
                        <input type="password" class="form-control" name="password" id="gPassword" required>
                    </div>
                    <div class="form-group full">
                        <label class="form-label">Specialization</label>
                        <input type="text" class="form-control" name="specialization" id="gSpecialization" placeholder="e.g., Mountain Trekking, Rock Climbing">
                    </div>
                    <div class="form-group full">
                        <label class="form-label">Years of Experience</label>
                        <input type="number" class="form-control" name="years_experience" id="gExperience" step="0.5">
                    </div>
                    <div class="form-group full">
                        <label class="form-label">Bio</label>
                        <textarea class="form-control" name="bio" id="gBio" rows="3" placeholder="Brief introduction about the guide..."></textarea>
                    </div>
                    <div class="form-group full">
                        <label class="form-label">Specialized Mountains <span class="req">*</span></label>
                        <div class="mtn-checklist" id="mountainsChecklist">
                            <?php foreach ($mountains as $mountain): ?>
                            <label class="check-item">
                                <input type="checkbox" name="mountains[]" value="<?= $mountain['id'] ?>">
                                <?= htmlspecialchars($mountain['name']) ?>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-body" id="gStep2" style="display:none;">
            <div class="confirm-box" id="confirmSummary"></div>
        </div>
        <div class="modal-footer" id="gFooter1">
            <button type="button" class="btn btn-ghost" onclick="closeAddModal()">Cancel</button>
            <button type="button" class="btn btn-primary" onclick="validateAndReview()">Review <i class="fas fa-arrow-right"></i></button>
        </div>
        <div class="modal-footer" id="gFooter2" style="display:none;">
            <button type="button" class="btn btn-ghost" onclick="goBackToForm()">Edit</button>
            <button type="button" class="btn btn-success" onclick="submitNewGuide()">Confirm</button>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════
     EDIT GUIDE MODAL
═══════════════════════════════════════════ -->
<div class="modal-overlay" id="editGuideModal">
    <div class="modal-box">
        <div class="modal-header">
            <div>
                <div class="modal-title">Edit Guide</div>
                <div class="modal-subtitle">UPDATE GUIDE DETAILS</div>
            </div>
            <button class="modal-close" onclick="closeEditModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="editGuideId">
            <div class="form-grid">
                <div class="form-group full">
                    <label class="form-label">Full Name <span class="req">*</span></label>
                    <input type="text" class="form-control" id="editName">
                </div>
                <div class="form-group full">
                    <label class="form-label">Mobile</label>
                    <input type="tel" class="form-control" id="editPhone" placeholder="e.g., 09xxxxxxxxx">
                </div>
                <div class="form-group full">
                    <label class="form-label">Specialization</label>
                    <input type="text" class="form-control" id="editSpecialization" placeholder="e.g., Mountain Trekking">
                </div>
                <div class="form-group full">
                    <label class="form-label">Years of Experience</label>
                    <input type="number" class="form-control" id="editExperience" step="0.5" min="0">
                </div>
                <div class="form-group full">
                    <label class="form-label">Bio</label>
                    <textarea class="form-control" id="editBio" rows="3" placeholder="Brief introduction about the guide..."></textarea>
                </div>
                <div class="form-group full">
                    <label class="form-label">Specialized Mountains</label>
                    <div class="mtn-checklist" id="editMountainsChecklist">
                        <?php foreach ($mountains as $mountain): ?>
                        <label class="check-item">
                            <input type="checkbox" class="edit-mtn-check" value="<?= $mountain['id'] ?>">
                            <?= htmlspecialchars($mountain['name']) ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-ghost" onclick="closeEditModal()">Cancel</button>
            <button type="button" class="btn btn-primary" id="editSaveBtn" onclick="submitEditGuide()">
                <i class="fas fa-floppy-disk"></i> Save Changes
            </button>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════
     DEACTIVATE CONFIRM MODAL
═══════════════════════════════════════════ -->
<div class="modal-overlay" id="deactivateModal">
    <div class="modal-box" style="max-width:420px;">
        <div class="modal-header">
            <div>
                <div class="modal-title">Remove from Roster</div>
                <div class="modal-subtitle">DEACTIVATE GUIDE · REVERSIBLE</div>
            </div>
            <button class="modal-close" onclick="closeDeactivateModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <div class="deactivate-guide-info">
                <div class="deactivate-avatar" id="deactivateInitial"></div>
                <div>
                    <div style="font-weight:600;color:#111318;font-size:14px;" id="deactivateName"></div>
                    <div style="font-size:12px;color:#5B6A7E;margin-top:2px;">Active guide</div>
                </div>
            </div>
            <div class="deactivate-note">
                This will <strong>remove the guide from the active roster</strong> but will <strong>not delete their account or data</strong>. 
                They can be reactivated at any time from the database.
            </div>
        </div>
        <div class="modal-footer" style="justify-content:space-between;">
            <button type="button" class="btn btn-ghost" onclick="closeDeactivateModal()">Cancel</button>
            <button type="button" class="btn btn-danger" id="confirmDeactivateBtn" onclick="submitDeactivate()">
                <i class="fas fa-user-slash"></i> Deactivate Guide
            </button>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════
     BROADCAST MODAL
═══════════════════════════════════════════ -->
<div class="modal-overlay" id="broadcastModal">
    <div class="modal-box">
        <div class="modal-header">
            <div>
                <div class="modal-title">Broadcast History</div>
                <div class="modal-subtitle">ALL ANNOUNCEMENTS · NEWEST FIRST</div>
            </div>
            <button class="modal-close" onclick="closeBroadcast()"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <?php if (!empty($broadcasts)): ?>
                <?php foreach ($broadcasts as $broadcast): ?>
                <div class="broadcast-log-item">
                    <div class="broadcast-log-msg"><?= htmlspecialchars($broadcast['message']) ?></div>
                    <div class="broadcast-log-ts">
                        <?= date('M d, Y h:i A', strtotime($broadcast['created_at'])) ?> by <?= htmlspecialchars($broadcast['sender_name']) ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="text-align:center;color:#B0BAC8;font-size:13px;padding:32px 0;">No announcements sent yet.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════
     CUSTOM ALERT MODAL
═══════════════════════════════════════════ -->
<div class="modal-overlay" id="customAlertModal" style="z-index:10000;">
    <div class="modal-box" style="max-width:400px;padding:32px 24px;text-align:center;">
        <div style="font-size:36px;color:#111318;margin-bottom:16px;" id="customAlertIcon">
            <i class="fas fa-circle-exclamation"></i>
        </div>
        <div class="modal-title" id="customAlertTitle" style="margin-bottom:8px;">Notice</div>
        <div id="customAlertMessage" style="font-size:14px;color:#5B6A7E;line-height:1.5;margin-bottom:28px;"></div>
        <button class="btn btn-primary" onclick="closeCustomAlert()" style="width:100%;padding:12px;">OK</button>
    </div>
</div>

<!-- ═══════════════════════════════════════════
     CUSTOM CONFIRM MODAL
═══════════════════════════════════════════ -->
<div class="modal-overlay" id="customConfirmModal" style="z-index:10000;">
    <div class="modal-box" style="max-width:400px;padding:32px 24px;text-align:center;">
        <div style="font-size:36px;color:#111318;margin-bottom:16px;"><i class="fas fa-circle-question"></i></div>
        <div class="modal-title" id="customConfirmTitle" style="margin-bottom:8px;">Confirm</div>
        <div id="customConfirmMessage" style="font-size:14px;color:#5B6A7E;line-height:1.5;margin-bottom:28px;white-space:pre-wrap;"></div>
        <div style="display:flex;gap:12px;">
            <button class="btn btn-ghost" onclick="closeCustomConfirm()" style="flex:1;padding:12px;">Cancel</button>
            <button class="btn btn-primary" id="customConfirmBtn" style="flex:1;padding:12px;">Confirm</button>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════
     JAVASCRIPT
═══════════════════════════════════════════ -->
<script>
const userId = <?= json_encode($_SESSION['user_id']) ?>;

// ─── Helpers ──────────────────────────────
function showCustomAlert(message, title = 'Notice', icon = 'fa-circle-exclamation') {
    document.getElementById('customAlertTitle').textContent   = title;
    document.getElementById('customAlertMessage').textContent = message;
    document.getElementById('customAlertIcon').innerHTML = `<i class="fas ${icon}"></i>`;
    document.getElementById('customAlertModal').classList.add('open');
}
function closeCustomAlert() { document.getElementById('customAlertModal').classList.remove('open'); }

function showCustomConfirm(message, onConfirm, title = 'Confirm') {
    document.getElementById('customConfirmTitle').textContent   = title;
    document.getElementById('customConfirmMessage').textContent = message;
    const btn    = document.getElementById('customConfirmBtn');
    const newBtn = btn.cloneNode(true);
    btn.parentNode.replaceChild(newBtn, btn);
    newBtn.addEventListener('click', () => { closeCustomConfirm(); if (typeof onConfirm === 'function') onConfirm(); });
    document.getElementById('customConfirmModal').classList.add('open');
}
function closeCustomConfirm() { document.getElementById('customConfirmModal').classList.remove('open'); }

function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
}

// ─── Sidebar nav ──────────────────────────
document.querySelectorAll('.nav-item[data-href]').forEach(item => {
    item.addEventListener('click', () => { window.location.href = item.getAttribute('data-href'); });
});

// ─── Live date ────────────────────────────
function updateDate() {
    const d = new Date();
    const el = document.getElementById('liveDate');
    if (el) el.textContent = d.toLocaleDateString('en-PH',{weekday:'short',month:'short',day:'numeric'}).toUpperCase()
        + ' ' + d.toLocaleTimeString('en-PH',{hour:'2-digit',minute:'2-digit'});
}
setInterval(updateDate, 1000); updateDate();

// ─── Topbar user ──────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    const topbarUser = document.querySelector('.topbar-user');
    if (topbarUser) topbarUser.addEventListener('click', () => {
        if (typeof openProfileModal === 'function') openProfileModal();
    });
});

// ════════════════════════════════════════════
// ADD GUIDE MODAL
// ════════════════════════════════════════════
const addModal = document.getElementById('addGuideModal');

document.getElementById('addGuideBtn').addEventListener('click', () => {
    addModal.classList.add('open');
    showStep(1);
    document.getElementById('addGuideForm').reset();
});

function closeAddModal() { addModal.classList.remove('open'); }

function showStep(step) {
    const els = {
        s1: document.getElementById('gStep1'), f1: document.getElementById('gFooter1'),
        s2: document.getElementById('gStep2'), f2: document.getElementById('gFooter2'),
        d1: document.getElementById('gdot1'),  d2: document.getElementById('gdot2'),
        lbl: document.getElementById('modalStepLabel'),
    };
    if (step === 1) {
        els.s1.style.display = 'block'; els.f1.style.display = 'flex';
        els.s2.style.display = 'none';  els.f2.style.display = 'none';
        els.d1.classList.add('active'); els.d2.classList.remove('active');
        els.lbl.textContent = 'STEP 1 OF 2 · REGISTRATION';
    } else {
        els.s1.style.display = 'none';  els.f1.style.display = 'none';
        els.s2.style.display = 'block'; els.f2.style.display = 'flex';
        els.d1.classList.remove('active'); els.d2.classList.add('active');
        els.lbl.textContent = 'STEP 2 OF 2 · CONFIRMATION';
    }
}

function validateAndReview() {
    const name  = document.getElementById('gName')?.value.trim();
    const age   = document.getElementById('gAge')?.value.trim();
    const mobile= document.getElementById('gMobile')?.value.trim();
    const email = document.getElementById('gEmail')?.value.trim();
    const pass  = document.getElementById('gPassword')?.value;
    const spec  = document.getElementById('gSpecialization')?.value.trim();
    const exp   = document.getElementById('gExperience')?.value;
    const bio   = document.getElementById('gBio')?.value.trim();
    const selectedMountains = [];
    document.querySelectorAll('input[name="mountains[]"]:checked').forEach(cb => {
        selectedMountains.push(cb.parentElement.textContent.trim());
    });
    if (!name)                     { showCustomAlert('Full Name is required.', 'Validation Error'); return; }
    if (!age || age < 18)          { showCustomAlert('Valid age (18+) is required.', 'Validation Error'); return; }
    if (!mobile)                   { showCustomAlert('Mobile number is required.', 'Validation Error'); return; }
    if (!email || !email.includes('@')) { showCustomAlert('Valid email is required.', 'Validation Error'); return; }
    if (!pass || pass.length < 6)  { showCustomAlert('Password must be at least 6 characters.', 'Validation Error'); return; }
    if (!selectedMountains.length) { showCustomAlert('Select at least one mountain.', 'Validation Error'); return; }
    document.getElementById('confirmSummary').innerHTML = `
        <div class="confirm-row"><span>Name</span><strong>${escapeHtml(name)}</strong></div>
        <div class="confirm-row"><span>Age</span><strong>${escapeHtml(age)}</strong></div>
        <div class="confirm-row"><span>Mobile</span><strong>${escapeHtml(mobile)}</strong></div>
        <div class="confirm-row"><span>Email</span><strong>${escapeHtml(email)}</strong></div>
        <div class="confirm-row"><span>Specialization</span><strong>${escapeHtml(spec || '—')}</strong></div>
        <div class="confirm-row"><span>Experience</span><strong>${escapeHtml(exp || '—')} yrs</strong></div>
        <div class="confirm-row"><span>Bio</span><strong>${escapeHtml(bio || '—')}</strong></div>
        <div class="confirm-row"><span>Mountains</span><strong>${selectedMountains.join(', ')}</strong></div>`;
    showStep(2);
}

function goBackToForm() { showStep(1); }

function submitNewGuide() {
    const btn = document.querySelector('#gFooter2 .btn-success');
    const orig = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating...';
    btn.disabled  = true;
    fetch('process_guide.php', { method:'POST', body: new FormData(document.getElementById('addGuideForm')) })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showCustomAlert('Guide added successfully!', 'Success', 'fa-circle-check');
                closeAddModal();
                setTimeout(() => location.reload(), 1500);
            } else {
                showCustomAlert('Error: ' + data.message, 'Error');
                btn.innerHTML = orig; btn.disabled = false;
            }
        })
        .catch(() => { showCustomAlert('An error occurred.', 'Error'); btn.innerHTML = orig; btn.disabled = false; });
}

// ════════════════════════════════════════════
// EDIT GUIDE MODAL
// ════════════════════════════════════════════
function openEditModal(guide) {
    document.getElementById('editGuideId').value        = guide.guide_id;
    document.getElementById('editName').value           = guide.name || '';
    document.getElementById('editPhone').value          = guide.phone || '';
    document.getElementById('editSpecialization').value = guide.specialization || '';
    document.getElementById('editExperience').value     = guide.years_experience || '';
    document.getElementById('editBio').value            = guide.bio || '';

    // Set mountain checkboxes
    const checks = document.querySelectorAll('.edit-mtn-check');
    checks.forEach(cb => {
        cb.checked = guide.mountain_ids.includes(parseInt(cb.value));
    });

    document.getElementById('editGuideModal').classList.add('open');
}

function closeEditModal() { document.getElementById('editGuideModal').classList.remove('open'); }

async function submitEditGuide() {
    const guideId = document.getElementById('editGuideId').value;
    const name    = document.getElementById('editName').value.trim();

    if (!name) { showCustomAlert('Full Name is required.', 'Validation Error'); return; }

    const selectedMountains = [];
    document.querySelectorAll('.edit-mtn-check:checked').forEach(cb => selectedMountains.push(cb.value));

    const fd = new FormData();
    fd.append('action',           'update');
    fd.append('guide_id',         guideId);
    fd.append('name',             name);
    fd.append('phone',            document.getElementById('editPhone').value.trim());
    fd.append('specialization',   document.getElementById('editSpecialization').value.trim());
    fd.append('years_experience', document.getElementById('editExperience').value);
    fd.append('bio',              document.getElementById('editBio').value.trim());
    selectedMountains.forEach(m => fd.append('mountains[]', m));

    const btn  = document.getElementById('editSaveBtn');
    const orig = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
    btn.disabled  = true;

    try {
        const res  = await fetch('process_guide_update.php', { method:'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            showCustomAlert('Guide updated successfully!', 'Success', 'fa-circle-check');
            closeEditModal();
            setTimeout(() => location.reload(), 1200);
        } else {
            showCustomAlert('Error: ' + data.message, 'Error');
        }
    } catch {
        showCustomAlert('An error occurred.', 'Error');
    } finally {
        btn.innerHTML = orig; btn.disabled = false;
    }
}

// ════════════════════════════════════════════
// DEACTIVATE MODAL
// ════════════════════════════════════════════
let _deactivateGuideId = null;

function openDeactivateModal(guideId, guideName, initial) {
    _deactivateGuideId = guideId;
    document.getElementById('deactivateInitial').textContent = initial;
    document.getElementById('deactivateName').textContent    = guideName;
    document.getElementById('deactivateModal').classList.add('open');
}

function closeDeactivateModal() {
    document.getElementById('deactivateModal').classList.remove('open');
    _deactivateGuideId = null;
}

async function submitDeactivate() {
    if (!_deactivateGuideId) return;

    const btn  = document.getElementById('confirmDeactivateBtn');
    const orig = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Deactivating...';
    btn.disabled  = true;

    const fd = new FormData();
    fd.append('action',   'deactivate');
    fd.append('guide_id', _deactivateGuideId);

    try {
        const res  = await fetch('process_guide_update.php', { method:'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            // Animate the row out
            const row = document.getElementById('guide-row-' + _deactivateGuideId);
            if (row) {
                row.style.transition = 'opacity 0.4s, transform 0.4s';
                row.style.opacity    = '0';
                row.style.transform  = 'translateX(20px)';
                setTimeout(() => row.remove(), 400);
            }
            closeDeactivateModal();
            showCustomAlert('Guide removed from active roster.', 'Deactivated', 'fa-circle-check');
            setTimeout(() => location.reload(), 1200);
        } else {
            showCustomAlert('Error: ' + data.message, 'Error');
        }
    } catch {
        showCustomAlert('An error occurred.', 'Error');
    } finally {
        btn.innerHTML = orig; btn.disabled = false;
    }
}

// ════════════════════════════════════════════
// REACTIVATE GUIDE
// ════════════════════════════════════════════
function reactivateGuide(guideId, guideName) {
    showCustomConfirm(`Are you sure you want to reactivate ${guideName}? They will be added back to the active roster.`, async () => {
        const fd = new FormData();
        fd.append('action', 'reactivate');
        fd.append('guide_id', guideId);

        try {
            const res  = await fetch('process_guide_update.php', { method:'POST', body: fd });
            const data = await res.json();
            if (data.success) {
                // Animate the row out
                const row = document.getElementById('inactive-guide-row-' + guideId);
                if (row) {
                    row.style.transition = 'opacity 0.4s, transform 0.4s';
                    row.style.opacity    = '0';
                    row.style.transform  = 'translateX(20px)';
                }
                showCustomAlert('Guide reactivated successfully!', 'Success', 'fa-circle-check');
                setTimeout(() => location.reload(), 1200);
            } else {
                showCustomAlert('Error: ' + data.message, 'Error');
            }
        } catch {
            showCustomAlert('An error occurred while reactivating.', 'Error');
        }
    });
}


// Registration Requests Button Handler
document.getElementById('viewRegistrationsBtn').addEventListener('click', function() {
    window.location.href = 'guide_registrations.php';
});

// ════════════════════════════════════════════
// BROADCAST & ANNOUNCEMENT
// ════════════════════════════════════════════
document.getElementById('broadcastBtn').addEventListener('click', () => {
    document.getElementById('broadcastModal').classList.add('open');
});
function closeBroadcast() { document.getElementById('broadcastModal').classList.remove('open'); }

document.getElementById('sendAnnounceBtn').addEventListener('click', () => {
    const msg = document.getElementById('announceTxt')?.value.trim();
    if (!msg) { showCustomAlert('Please write an announcement first.', 'Validation Error'); return; }
    showCustomConfirm(`Send this announcement to all guides?\n\n"${msg}"`, () => {
        const fd = new FormData();
        fd.append('message', msg);
        fd.append('recipient_type', 'all_guides');
        fetch('process_announcement.php', { method:'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showCustomAlert('Announcement sent!', 'Success', 'fa-circle-check');
                    document.getElementById('announceTxt').value = '';
                    setTimeout(() => location.reload(), 1500);
                } else { showCustomAlert('Error: ' + data.message, 'Error'); }
            })
            .catch(() => showCustomAlert('An error occurred.', 'Error'));
    });
});
</script>

<?php
$modalPath = __DIR__ . '/profile-modal.php';
if (file_exists($modalPath)) include_once $modalPath;

$logoutModalPath = __DIR__ . '/../../includes/logout-modal.php';
if (file_exists($logoutModalPath)) include_once $logoutModalPath;

// $messagingModalPath = __DIR__ . '/messaging-modal.php';
// if (file_exists($messagingModalPath)) include_once $messagingModalPath;
?>
</body>
</html>