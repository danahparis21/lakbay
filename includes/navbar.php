<?php
// This file should be included after session_start() and after fetching user data
// Variables needed: $currentUser (array with name, avatar), $userInitial (string), $currentPage (string)

$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$userAvatar = $currentUser['avatar'] ?? null;
$userName = $currentUser['name'] ?? 'Hiker';

// Generate initials if avatar doesn't exist
if (!$userAvatar && !isset($userInitial)) {
    $nameParts = explode(' ', trim($userName));
    $userInitial = '';
    foreach ($nameParts as $part) {
        if (!empty($part)) {
            $userInitial .= strtoupper(substr($part, 0, 1));
        }
    }
    $userInitial = substr($userInitial, 0, 2);
}

// Get unread message count for the current user
$unreadCount = 0;
if (isset($_SESSION['user_id'])) {
    try {
        if (!isset($pdo)) {
            require_once __DIR__ . '/../config/db.php';
        }
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as unread_count 
            FROM messages 
            WHERE receiver_id = ? AND is_read = 0 AND receiver_role = 'hiker'
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $unreadCount = $stmt->fetch(PDO::FETCH_ASSOC)['unread_count'] ?? 0;
    } catch (PDOException $e) {
        error_log('Unread messages count error: ' . $e->getMessage());
    }
}

// CSS for badge styling
$badgeStyle = '
<style>
/* Notification badge for nav items */
.nav-badge {
    position: absolute;
    top: -6px;
    right: -8px;
    background: #c62828;
    color: white;
    font-size: 10px;
    font-weight: 700;
    padding: 2px 6px;
    border-radius: 20px;
    min-width: 18px;
    text-align: center;
    font-family: "DM Mono", monospace;
    box-shadow: 0 2px 6px rgba(0,0,0,0.2);
    animation: badgePulse 0.5s ease;
}

@keyframes badgePulse {
    0% { transform: scale(0.8); opacity: 0; }
    100% { transform: scale(1); opacity: 1; }
}

/* Position relative for badge container */
.tab-link, .mob-nav-item {
    position: relative;
}

/* Avatar small for mobile */
.avatar-small {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    background-size: cover;
    background-position: center;
}

/* Mobile badge adjustment */
.mob-nav-item .nav-badge {
    top: -4px;
    right: -4px;
    padding: 1px 4px;
    min-width: 14px;
    font-size: 8px;
}
</style>
';

echo $badgeStyle;
?>

<!-- DESKTOP NAV -->
<nav class="desktop-nav">
  <a href="../index.php" class="brand">
    <svg viewBox="0 0 32 32" fill="none"><path d="M4 26L10 12L16 20L21 9L28 26H4Z" fill="#100600" opacity=".9"/><path d="M16 20L21 9L28 26H16V20Z" fill="#100600" opacity=".35"/></svg>
    LAKBAY
  </a>
  <div class="tabs">
    <a href="explore.php" class="tab-link <?= $currentPage === 'explore' ? 'active' : '' ?>">
      <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>Explore
    </a>
    <a href="bookings.php" class="tab-link <?= $currentPage === 'bookings' ? 'active' : '' ?>">
      <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>Bookings
    </a>
    <a href="quiz.php" class="tab-link <?= $currentPage === 'quiz' ? 'active' : '' ?>">
      <svg viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>Quiz
    </a>
    <a href="messages.php" class="tab-link <?= $currentPage === 'messages' ? 'active' : '' ?>">
      <svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>Messages
      <?php if ($unreadCount > 0): ?>
        <span class="nav-badge"><?= $unreadCount > 9 ? '9+' : $unreadCount ?></span>
      <?php endif; ?>
    </a>
  </div>
  <a href="hikerProfile.php" class="user-btn" style="<?= $userAvatar ? 'background-image: url(' . htmlspecialchars($userAvatar) . '); background-size: cover; background-position: center;' : '' ?>">
    <?= !$userAvatar ? htmlspecialchars($userInitial) : '' ?>
  </a>
</nav>

<!-- MOBILE NAV -->
<nav class="mobile-nav">
  <div class="mobile-nav-inner">
    <a href="explore.php" class="mob-nav-item <?= $currentPage === 'explore' ? 'active' : '' ?>">
      <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg><span>Explore</span>
    </a>
    <a href="bookings.php" class="mob-nav-item <?= $currentPage === 'bookings' ? 'active' : '' ?>">
      <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg><span>Bookings</span>
    </a>
    <a href="quiz.php" class="mob-nav-item quiz-center <?= $currentPage === 'quiz' ? 'active' : '' ?>">
      <svg viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
    </a>
    <a href="messages.php" class="mob-nav-item <?= $currentPage === 'messages' ? 'active' : '' ?>">
      <svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg><span>Messages</span>
      <?php if ($unreadCount > 0): ?>
        <span class="nav-badge"><?= $unreadCount > 9 ? '9+' : $unreadCount ?></span>
      <?php endif; ?>
    </a>
    <a href="hikerProfile.php" class="mob-nav-item <?= $currentPage === 'hikerProfile' ? 'active' : '' ?>">
      <?php if ($userAvatar): ?>
        <div class="avatar-small" style="background-image: url('<?= htmlspecialchars($userAvatar) ?>'); background-size: cover;"></div>
      <?php else: ?>
        <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
      <?php endif; ?>
      <span>Profile</span>
    </a>
  </div>
</nav>

<!-- Optional: JavaScript to periodically check for new messages and update badge -->
<script>
// Store current unread count
let currentUnreadCount = <?= $unreadCount ?>;

/**
 * Fetch updated unread message count from server
 * Updates the badge in real-time without page refresh
 */
async function updateUnreadBadge() {
    try {
        const response = await fetch('../api/get_unread_count.php', {
            method: 'GET',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        
        if (!response.ok) throw new Error('Network response was not ok');
        
        const data = await response.json();
        
        if (data.success && data.unread_count !== undefined) {
            const newCount = data.unread_count;
            
            // Only update if count changed
            if (newCount !== currentUnreadCount) {
                currentUnreadCount = newCount;
                
                // Update desktop badge
                const desktopMessageLink = document.querySelector('.desktop-nav .tab-link[href="messages.php"]');
                if (desktopMessageLink) {
                    const existingBadge = desktopMessageLink.querySelector('.nav-badge');
                    
                    if (newCount > 0) {
                        const badgeText = newCount > 9 ? '9+' : newCount;
                        
                        if (existingBadge) {
                            existingBadge.textContent = badgeText;
                            // Add animation for visual feedback
                            existingBadge.style.animation = 'none';
                            existingBadge.offsetHeight; // Trigger reflow
                            existingBadge.style.animation = 'badgePulse 0.5s ease';
                        } else {
                            const badge = document.createElement('span');
                            badge.className = 'nav-badge';
                            badge.textContent = badgeText;
                            desktopMessageLink.appendChild(badge);
                        }
                    } else if (existingBadge) {
                        existingBadge.remove();
                    }
                }
                
                // Update mobile badge
                const mobileMessageLink = document.querySelector('.mobile-nav .mob-nav-item[href="messages.php"]');
                if (mobileMessageLink) {
                    const existingBadge = mobileMessageLink.querySelector('.nav-badge');
                    
                    if (newCount > 0) {
                        const badgeText = newCount > 9 ? '9+' : newCount;
                        
                        if (existingBadge) {
                            existingBadge.textContent = badgeText;
                            existingBadge.style.animation = 'none';
                            existingBadge.offsetHeight;
                            existingBadge.style.animation = 'badgePulse 0.5s ease';
                        } else {
                            const badge = document.createElement('span');
                            badge.className = 'nav-badge';
                            badge.textContent = badgeText;
                            mobileMessageLink.appendChild(badge);
                        }
                    } else if (existingBadge) {
                        existingBadge.remove();
                    }
                }
                
                // Show toast notification for new messages
                if (newCount > currentUnreadCount) {
                    const newMessages = newCount - currentUnreadCount;
                    showMessageToast(newMessages);
                }
            }
        }
    } catch (error) {
        console.error('Failed to fetch unread count:', error);
    }
}

/**
 * Show a toast notification for new messages
 */
function showMessageToast(count) {
    const toast = document.getElementById('toast');
    if (toast) {
        const oldContent = toast.textContent;
        toast.textContent = `📨 You have ${count} new message${count > 1 ? 's' : ''}!`;
        toast.className = 'toast info';
        toast.classList.add('show');
        setTimeout(() => {
            toast.classList.remove('show');
            if (oldContent) toast.textContent = oldContent;
        }, 4000);
    } else {
        // Fallback console log if toast element doesn't exist
        console.log(`📨 You have ${count} new message${count > 1 ? 's' : ''}!`);
    }
}

// Poll for new messages every 10 seconds when user is on any page except messages.php
// (On messages page, the conversation list handles its own refresh)
const currentPage = '<?= $currentPage ?>';
if (currentPage !== 'messages') {
    // Check immediately
    if (typeof updateUnreadBadge === 'function') {
        updateUnreadBadge();
    }
    // Then check every 10 seconds
    setInterval(updateUnreadBadge, 10000);
} else {
    // On messages page, still update but less frequently (30 seconds)
    setInterval(updateUnreadBadge, 30000);
}

// Also update when page becomes visible again (tab focus)
document.addEventListener('visibilitychange', function() {
    if (!document.hidden && typeof updateUnreadBadge === 'function') {
        updateUnreadBadge();
    }
});
</script>