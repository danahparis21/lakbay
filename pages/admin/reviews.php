<?php
// pages/admin/reviews.php - Admin System Reviews Management
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: /login-and-signup/login.php');
    exit;
}

require_once '../../config/db.php';

$adminName = $_SESSION['user_name'];
$adminInitial = strtoupper(substr($adminName, 0, 1));

// Handle AJAX requests
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    
    // Approve review
    if ($action === 'approve_review') {
        $review_id = intval($_POST['review_id'] ?? 0);
        
        try {
            $stmt = $pdo->prepare("UPDATE system_reviews SET status = 'approved', updated_at = NOW() WHERE id = ?");
            $stmt->execute([$review_id]);
            
            if ($stmt->rowCount() > 0) {
                echo json_encode(['success' => true, 'message' => 'Review approved successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Review not found']);
            }
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        exit;
    }
    
    // Reject review
    if ($action === 'reject_review') {
        $review_id = intval($_POST['review_id'] ?? 0);
        
        try {
            $stmt = $pdo->prepare("UPDATE system_reviews SET status = 'rejected', updated_at = NOW() WHERE id = ?");
            $stmt->execute([$review_id]);
            
            if ($stmt->rowCount() > 0) {
                echo json_encode(['success' => true, 'message' => 'Review rejected']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Review not found']);
            }
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        exit;
    }
    
    // Delete review
    if ($action === 'delete_review') {
        $review_id = intval($_POST['review_id'] ?? 0);
        
        try {
            $stmt = $pdo->prepare("DELETE FROM system_reviews WHERE id = ?");
            $stmt->execute([$review_id]);
            
            if ($stmt->rowCount() > 0) {
                echo json_encode(['success' => true, 'message' => 'Review deleted']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Review not found']);
            }
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        exit;
    }
    
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit;
}

// Get all system reviews with pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Build query based on filter
$where_clause = "";
if ($filter === 'pending') {
    $where_clause = "WHERE status = 'pending'";
} elseif ($filter === 'approved') {
    $where_clause = "WHERE status = 'approved'";
} elseif ($filter === 'rejected') {
    $where_clause = "WHERE status = 'rejected'";
}

// Get total count
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM system_reviews $where_clause");
$stmt->execute();
$total_reviews = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_reviews / $per_page);

// Get reviews
$limit = (int)$per_page;
$offset_val = (int)$offset;

$sql = "SELECT * FROM system_reviews $where_clause ORDER BY created_at DESC LIMIT $limit OFFSET $offset_val";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
        ROUND(AVG(CASE WHEN status = 'approved' THEN rating ELSE NULL END), 1) as avg_rating
    FROM system_reviews
");
$stmt->execute();
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Get recent activity (last 7 days)
$stmt = $pdo->prepare("
    SELECT DATE(created_at) as date, COUNT(*) as count
    FROM system_reviews
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY DATE(created_at)
    ORDER BY date ASC
");
$stmt->execute();
$activityData = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LAKBAY — System Reviews</title>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600&family=DM+Mono:wght@400;500&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'><path fill='%23254A5A' d='M8 3 3 20h18L14 8l-2 4z'/></svg>">
    <link rel="stylesheet" href="shared.css">
    
    <style>
        /* Reviews specific styles */
        .reviews-header {
            margin-bottom: 28px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 32px;
        }
        
        .stat-card {
            background: white;
            border: 1px solid var(--line);
            border-radius: 12px;
            padding: 20px;
            transition: all 0.2s;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.08);
        }
        
        .stat-label {
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: var(--ink-4);
            margin-bottom: 8px;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--ink);
            line-height: 1;
        }
        
        .stat-sub {
            font-size: 0.7rem;
            color: var(--ink-4);
            margin-top: 6px;
        }
        
        .stat-trend {
            font-size: 0.7rem;
            margin-top: 8px;
            color: var(--accent);
        }
        
        /* Filter tabs */
        .filter-tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 24px;
            border-bottom: 1px solid var(--line);
            padding-bottom: 12px;
        }
        
        .filter-tab {
            padding: 8px 20px;
            border-radius: 30px;
            font-size: 0.8rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            background: transparent;
            border: none;
            color: var(--ink-3);
        }
        
        .filter-tab:hover {
            background: var(--paper-2);
            color: var(--ink);
        }
        
        .filter-tab.active {
            background: var(--ink);
            color: white;
        }
        
        .filter-tab .count {
            background: rgba(255,255,255,0.2);
            border-radius: 20px;
            padding: 2px 8px;
            font-size: 0.7rem;
            margin-left: 6px;
        }
        
        .filter-tab.active .count {
            background: rgba(255,255,255,0.25);
        }
        
        /* Review cards */
        .review-card {
            background: white;
            border: 1px solid var(--line);
            border-radius: 16px;
            margin-bottom: 16px;
            overflow: hidden;
            transition: all 0.2s;
        }
        
        .review-card:hover {
            box-shadow: 0 4px 16px rgba(0,0,0,0.06);
        }
        
        .review-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--line);
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 16px;
        }
        
        .reviewer-info {
            display: flex;
            align-items: center;
            gap: 14px;
        }
        
        .reviewer-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: var(--ink);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1rem;
            font-weight: 600;
            overflow: hidden;
        }
        
        .reviewer-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .reviewer-details h4 {
            font-size: 1rem;
            font-weight: 600;
            color: var(--ink);
            margin-bottom: 4px;
        }
        
        .reviewer-details .review-date {
            font-size: 0.7rem;
            color: var(--ink-4);
        }
        
        .rating-badge {
            display: flex;
            align-items: center;
            gap: 6px;
            background: var(--paper-2);
            padding: 6px 12px;
            border-radius: 30px;
        }
        
        .rating-stars {
            color: #f1c40f;
            font-size: 0.8rem;
            letter-spacing: 2px;
        }
        
        .rating-value {
            font-weight: 700;
            color: var(--ink);
            font-size: 0.9rem;
        }
        
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-pending {
            background: #fff8e1;
            color: #f57f17;
        }
        
        .status-approved {
            background: #e8f5e9;
            color: #2e7d32;
        }
        
        .status-rejected {
            background: #ffebee;
            color: #c62828;
        }
        
        .review-body {
            padding: 20px 24px;
        }
        
        .review-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--ink);
            margin-bottom: 12px;
        }
        
        .review-comment {
            color: var(--ink-2);
            line-height: 1.6;
            margin-bottom: 16px;
            font-size: 0.9rem;
        }
        
        .review-meta {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
            padding-top: 12px;
            border-top: 1px solid var(--line);
            font-size: 0.7rem;
            color: var(--ink-4);
        }
        
        .review-meta i {
            width: 14px;
            margin-right: 4px;
        }
        
        .review-actions {
            padding: 12px 24px 20px;
            display: flex;
            gap: 12px;
            border-top: 1px solid var(--line);
            background: var(--paper);
        }
        
        .btn-action {
            padding: 8px 20px;
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.15s;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .btn-approve {
            background: #2e7d32;
            color: white;
        }
        
        .btn-approve:hover {
            background: #1b5e20;
            transform: translateY(-1px);
        }
        
        .btn-reject {
            background: #c62828;
            color: white;
        }
        
        .btn-reject:hover {
            background: #b71c1c;
            transform: translateY(-1px);
        }
        
        .btn-delete {
            background: #e0e0e0;
            color: #666;
        }
        
        .btn-delete:hover {
            background: #c62828;
            color: white;
        }
        
        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 16px;
            border: 1px solid var(--line);
        }
        
        .empty-state i {
            font-size: 3rem;
            color: var(--ink-4);
            margin-bottom: 16px;
        }
        
        .empty-state p {
            color: var(--ink-3);
            font-size: 0.9rem;
        }
        
        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-top: 32px;
        }
        
        .page-link {
            padding: 8px 14px;
            border-radius: 8px;
            background: white;
            border: 1px solid var(--line);
            color: var(--ink);
            text-decoration: none;
            font-size: 0.8rem;
            transition: all 0.15s;
        }
        
        .page-link:hover {
            background: var(--ink);
            color: white;
            border-color: var(--ink);
        }
        
        .page-link.active {
            background: var(--ink);
            color: white;
            border-color: var(--ink);
        }
        
        /* Activity chart */
        .activity-chart-wrapper {
            background: white;
            border: 1px solid var(--line);
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 32px;
        }
        
        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }
        
        .chart-header h4 {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--ink);
        }
        
        .chart-container {
            height: 200px;
        }
        
        /* Confirmation Modal */
        .confirm-modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(4px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 10000;
        }
        
        .confirm-modal-overlay.open {
            display: flex;
        }
        
        .confirm-modal {
            background: white;
            border-radius: 20px;
            width: 90%;
            max-width: 420px;
            overflow: hidden;
            animation: modalFadeIn 0.2s ease-out;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }
        
        @keyframes modalFadeIn {
            from { opacity: 0; transform: scale(0.95) translateY(-10px); }
            to { opacity: 1; transform: scale(1) translateY(0); }
        }
        
        .confirm-modal-header {
            padding: 24px 24px 0;
            display: flex;
            align-items: center;
            gap: 14px;
        }
        
        .confirm-icon {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
        }
        
        .confirm-icon.approve {
            background: rgba(46, 125, 50, 0.1);
            color: #2e7d32;
        }
        
        .confirm-icon.reject {
            background: rgba(198, 40, 40, 0.1);
            color: #c62828;
        }
        
        .confirm-icon.delete {
            background: rgba(220, 38, 38, 0.1);
            color: #dc2626;
        }
        
        .confirm-modal-header h3 {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--ink);
            margin: 0;
        }
        
        .confirm-modal-body {
            padding: 16px 24px;
            color: var(--ink-3);
            font-size: 0.85rem;
            line-height: 1.5;
        }
        
        .confirm-modal-footer {
            padding: 16px 24px 24px;
            display: flex;
            gap: 12px;
            justify-content: flex-end;
        }
        
        .confirm-btn {
            padding: 10px 20px;
            border-radius: 10px;
            font-size: 0.8rem;
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: all 0.15s;
            font-family: 'Inter', sans-serif;
        }
        
        .confirm-btn-cancel {
            background: #f0f0f0;
            color: #666;
        }
        
        .confirm-btn-cancel:hover {
            background: #e0e0e0;
            transform: translateY(-1px);
        }
        
        .confirm-btn-approve {
            background: #2e7d32;
            color: white;
        }
        
        .confirm-btn-approve:hover {
            background: #1b5e20;
            transform: translateY(-1px);
        }
        
        .confirm-btn-reject {
            background: #c62828;
            color: white;
        }
        
        .confirm-btn-reject:hover {
            background: #b71c1c;
            transform: translateY(-1px);
        }
        
        .confirm-btn-delete {
            background: #dc2626;
            color: white;
        }
        
        .confirm-btn-delete:hover {
            background: #b91c1c;
            transform: translateY(-1px);
        }
        
        @media (max-width: 900px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 600px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            .review-header {
                flex-direction: column;
            }
            .review-actions {
                flex-wrap: wrap;
            }
        }
    </style>
</head>
<body data-page="reviews">
<div class="app">

    <!-- SIDEBAR -->
    <aside class="sidebar">
        <div>
            <div class="logo">
                <div class="logo-wordmark">
                    <div class="logo-icon">
                        <svg viewBox="0 0 28 28" fill="none" xmlns="http://www.w3.org/2000/svg">
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
                <li class="nav-item" data-href="/pages/admin/payments.php"><i class="fas fa-coins"></i> Revenue</li>
               <li class="nav-item active" data-href="/pages/admin/reviews.php"><i class="fas fa-star"></i> Reviews</li>
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

    <!-- MAIN CONTENT -->
    <div class="main">
        <div class="topbar">
            <div class="page-heading">
                <i class="fas fa-star"></i> System Reviews
                <span style="font-size:0.7rem; font-weight:400; color:var(--ink-4); margin-left:8px;">
                    User feedback & testimonials
                </span>
            </div>
            <div class="topbar-right">
                <div class="topbar-date" id="liveDate"></div>
                <div class="topbar-user" style="cursor: pointer;">
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
            
            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-label">Total Reviews</div>
                    <div class="stat-value"><?= number_format($stats['total'] ?? 0) ?></div>
                    <div class="stat-sub">All time</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Pending Moderation</div>
                    <div class="stat-value" style="color: #f57f17;"><?= number_format($stats['pending'] ?? 0) ?></div>
                    <div class="stat-trend">Need your attention</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Approved Reviews</div>
                    <div class="stat-value" style="color: #2e7d32;"><?= number_format($stats['approved'] ?? 0) ?></div>
                    <div class="stat-sub">Live on homepage</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Average Rating</div>
                    <div class="stat-value"><?= number_format($stats['avg_rating'] ?? 0, 1) ?> ★</div>
                    <div class="stat-sub">Based on approved reviews</div>
                </div>
            </div>
            
            <!-- Activity Chart -->
            <div class="activity-chart-wrapper">
                <div class="chart-header">
                    <h4><i class="fas fa-chart-line"></i> Review Activity (Last 7 Days)</h4>
                </div>
                <div class="chart-container">
                    <canvas id="activityChart"></canvas>
                </div>
            </div>
            
            <!-- Filter Tabs -->
            <div class="filter-tabs">
                <button class="filter-tab <?= $filter === 'all' ? 'active' : '' ?>" data-filter="all">
                    All <span class="count"><?= $stats['total'] ?? 0 ?></span>
                </button>
                <button class="filter-tab <?= $filter === 'pending' ? 'active' : '' ?>" data-filter="pending">
                    Pending <span class="count"><?= $stats['pending'] ?? 0 ?></span>
                </button>
                <button class="filter-tab <?= $filter === 'approved' ? 'active' : '' ?>" data-filter="approved">
                    Approved <span class="count"><?= $stats['approved'] ?? 0 ?></span>
                </button>
                <button class="filter-tab <?= $filter === 'rejected' ? 'active' : '' ?>" data-filter="rejected">
                    Rejected <span class="count"><?= $stats['rejected'] ?? 0 ?></span>
                </button>
            </div>
            
            <!-- Reviews List -->
            <div id="reviewsList">
                <?php if (empty($reviews)): ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <p>No reviews found</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($reviews as $review): ?>
                        <div class="review-card" data-review-id="<?= $review['id'] ?>">
                            <div class="review-header">
                                <div class="reviewer-info">
                                    <div class="reviewer-avatar">
                                        <?php if (!empty($review['user_avatar'])): ?>
                                            <img src="<?= htmlspecialchars($review['user_avatar']) ?>" alt="Avatar">
                                        <?php else: ?>
                                            <?= substr($review['user_name'] ?? 'U', 0, 2) ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="reviewer-details">
                                        <h4><?= htmlspecialchars($review['user_name'] ?? 'Anonymous') ?></h4>
                                        <div class="review-date">
                                            <i class="far fa-calendar-alt"></i> 
                                            <?= date('F j, Y \a\t g:i A', strtotime($review['created_at'])) ?>
                                        </div>
                                    </div>
                                </div>
                                <div style="display: flex; gap: 12px; align-items: center;">
                                    <div class="rating-badge">
                                        <span class="rating-stars"><?= str_repeat('★', $review['rating']) ?><?= str_repeat('☆', 5 - $review['rating']) ?></span>
                                        <span class="rating-value"><?= $review['rating'] ?>.0</span>
                                    </div>
                                    <div class="status-badge status-<?= $review['status'] ?>">
                                        <?= ucfirst($review['status']) ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="review-body">
                                <div class="review-title">
                                    "<?= htmlspecialchars($review['title'] ?? 'No title') ?>"
                                </div>
                                <div class="review-comment">
                                    <?= nl2br(htmlspecialchars($review['comment'] ?? '')) ?>
                                </div>
                                <div class="review-meta">
                                    <span><i class="fas fa-tag"></i> Booking #<?= htmlspecialchars($review['booking_id'] ?? 'N/A') ?></span>
                                    <span><i class="fas fa-thumbs-up"></i> <?= $review['helpful_count'] ?? 0 ?> found helpful</span>
                                    <?php if ($review['is_verified_purchase']): ?>
                                        <span><i class="fas fa-check-circle" style="color:#2e7d32;"></i> Verified Purchase</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="review-actions">
                                <?php if ($review['status'] === 'pending'): ?>
                                    <button class="btn-action btn-approve" onclick="openConfirmModal(<?= $review['id'] ?>, 'approve')">
                                        <i class="fas fa-check-circle"></i> Approve & Publish
                                    </button>
                                    <button class="btn-action btn-reject" onclick="openConfirmModal(<?= $review['id'] ?>, 'reject')">
                                        <i class="fas fa-times-circle"></i> Reject
                                    </button>
                                <?php endif; ?>
                                <button class="btn-action btn-delete" onclick="openConfirmModal(<?= $review['id'] ?>, 'delete')">
                                    <i class="fas fa-trash-alt"></i> Delete
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?page=<?= $i ?>&filter=<?= $filter ?>" class="page-link <?= $i === $page ? 'active' : '' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
            </div>
            <?php endif; ?>
            
        </div>
    </div>
</div>

<!-- Confirmation Modal -->
<div id="confirmModal" class="confirm-modal-overlay">
    <div class="confirm-modal">
        <div class="confirm-modal-header">
            <div class="confirm-icon" id="confirmIcon">
                <i class="fas fa-question"></i>
            </div>
            <h3 id="confirmTitle">Confirm Action</h3>
        </div>
        <div class="confirm-modal-body" id="confirmMessage">
            Are you sure you want to proceed?
        </div>
        <div class="confirm-modal-footer">
            <button class="confirm-btn confirm-btn-cancel" onclick="closeConfirmModal()">Cancel</button>
            <button class="confirm-btn" id="confirmActionBtn">Confirm</button>
        </div>
    </div>
</div>

<!-- Include Modals -->
<?php include_once __DIR__ . '/profile-modal.php'; ?>
<?php include_once __DIR__ . '/../../includes/logout-modal.php'; ?>

<script>
// Store pending action
let pendingReviewId = null;
let pendingAction = null;

// Live clock
function updateDate() {
    const d = new Date();
    const dateEl = document.getElementById('liveDate');
    if (dateEl) {
        dateEl.textContent = d.toLocaleDateString('en-PH', {weekday:'short', month:'short', day:'numeric'}).toUpperCase() + '  ' + d.toLocaleTimeString('en-PH', {hour:'2-digit', minute:'2-digit'});
    }
}
updateDate();
setInterval(updateDate, 1000);

// Navigation
document.querySelectorAll('.nav-item').forEach(item => {
    item.addEventListener('click', () => {
        if (item.dataset.href) window.location.href = item.dataset.href;
    });
});

// Filter tabs
document.querySelectorAll('.filter-tab').forEach(tab => {
    tab.addEventListener('click', () => {
        const filter = tab.dataset.filter;
        window.location.href = `?filter=${filter}&page=1`;
    });
});

// Activity Chart
const activityLabels = <?= json_encode(array_column($activityData, 'date')) ?>;
const activityCounts = <?= json_encode(array_column($activityData, 'count')) ?>;

const activityCtx = document.getElementById('activityChart')?.getContext('2d');
if (activityCtx) {
    new Chart(activityCtx, {
        type: 'line',
        data: {
            labels: activityLabels,
            datasets: [{
                label: 'Reviews Submitted',
                data: activityCounts,
                borderColor: '#111318',
                backgroundColor: 'rgba(17,19,24,0.05)',
                fill: true,
                tension: 0.3,
                pointBackgroundColor: '#111318',
                pointBorderColor: 'white',
                pointRadius: 4,
                pointHoverRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: { callbacks: { label: (ctx) => `${ctx.raw} review${ctx.raw !== 1 ? 's' : ''}` } }
            },
            scales: {
                y: { beginAtZero: true, ticks: { stepSize: 1, precision: 0 } }
            }
        }
    });
}

// Open confirmation modal
function openConfirmModal(reviewId, action) {
    pendingReviewId = reviewId;
    pendingAction = action;
    
    const modal = document.getElementById('confirmModal');
    const iconDiv = document.getElementById('confirmIcon');
    const titleEl = document.getElementById('confirmTitle');
    const messageEl = document.getElementById('confirmMessage');
    const actionBtn = document.getElementById('confirmActionBtn');
    
    if (action === 'approve') {
        iconDiv.className = 'confirm-icon approve';
        iconDiv.innerHTML = '<i class="fas fa-check-circle"></i>';
        titleEl.textContent = 'Approve Review';
        messageEl.innerHTML = 'This review will be published and visible on the homepage. Are you sure you want to approve it?';
        actionBtn.className = 'confirm-btn confirm-btn-approve';
        actionBtn.innerHTML = 'Approve Review';
    } else if (action === 'reject') {
        iconDiv.className = 'confirm-icon reject';
        iconDiv.innerHTML = '<i class="fas fa-times-circle"></i>';
        titleEl.textContent = 'Reject Review';
        messageEl.innerHTML = 'This review will not be published. The user will be notified. Are you sure you want to reject it?';
        actionBtn.className = 'confirm-btn confirm-btn-reject';
        actionBtn.innerHTML = 'Reject Review';
    } else if (action === 'delete') {
        iconDiv.className = 'confirm-icon delete';
        iconDiv.innerHTML = '<i class="fas fa-trash-alt"></i>';
        titleEl.textContent = 'Delete Review';
        messageEl.innerHTML = '<strong>⚠️ Warning: This action cannot be undone.</strong><br><br>This review will be permanently deleted from the database. Are you absolutely sure?';
        actionBtn.className = 'confirm-btn confirm-btn-delete';
        actionBtn.innerHTML = 'Permanently Delete';
    }
    
    modal.classList.add('open');
}

// Close confirmation modal
function closeConfirmModal() {
    document.getElementById('confirmModal').classList.remove('open');
    pendingReviewId = null;
    pendingAction = null;
}

// Execute the pending action
document.getElementById('confirmActionBtn').addEventListener('click', function() {
    if (pendingReviewId && pendingAction) {
        if (pendingAction === 'approve' || pendingAction === 'reject') {
            moderateReview(pendingReviewId, pendingAction);
        } else if (pendingAction === 'delete') {
            deleteReview(pendingReviewId);
        }
    }
    closeConfirmModal();
});

// Moderate review (approve/reject)
function moderateReview(reviewId, action) {
    const formData = new URLSearchParams();
    formData.append('action', action === 'approve' ? 'approve_review' : 'reject_review');
    formData.append('review_id', reviewId);
    
    fetch(window.location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            showToast(result.message, 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast(result.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Network error. Please try again.', 'error');
    });
}

// Delete review
function deleteReview(reviewId) {
    const formData = new URLSearchParams();
    formData.append('action', 'delete_review');
    formData.append('review_id', reviewId);
    
    fetch(window.location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            showToast(result.message, 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast(result.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Network error. Please try again.', 'error');
    });
}

// Toast notification
function showToast(message, type = 'success') {
    const toast = document.createElement('div');
    toast.className = `toast-notification ${type}`;
    toast.innerHTML = `<i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i> ${message}`;
    toast.style.cssText = `
        position: fixed;
        bottom: 30px;
        right: 30px;
        background: ${type === 'success' ? '#1B7045' : '#C0392B'};
        color: white;
        padding: 12px 20px;
        border-radius: 12px;
        font-size: 0.85rem;
        font-weight: 500;
        z-index: 10000;
        display: flex;
        align-items: center;
        gap: 10px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        animation: slideIn 0.3s ease;
    `;
    document.body.appendChild(toast);
    setTimeout(() => {
        toast.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// Close modal when clicking outside
document.getElementById('confirmModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeConfirmModal();
    }
});

// Close modal with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeConfirmModal();
    }
});

// Add animation styles (only once, check if already exists)
if (!document.querySelector('#toast-animation-styles')) {
    const toastStyles = document.createElement('style');
    toastStyles.id = 'toast-animation-styles';
    toastStyles.textContent = `
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        @keyframes slideOut {
            from { transform: translateX(0); opacity: 1; }
            to { transform: translateX(100%); opacity: 0; }
        }
    `;
    document.head.appendChild(toastStyles);
}
</script>

<style>
    /* Additional toast styles */
    .nav-item.active {
        background: var(--ink);
        color: var(--paper);
        font-weight: 500;
    }
    .nav-item.active i {
        color: var(--ink-5);
    }
</style>
</body>
</html>