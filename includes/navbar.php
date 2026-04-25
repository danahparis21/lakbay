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