<?php $base = $GLOBALS['base'] ?? ''; ?>
<footer class="footer" role="contentinfo">
  <div class="container">
    <div class="footer-grid">
      <div>
        <div class="footer-brand">
          <div class="footer-logo"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m8 3-5 17h18L14 8l-2 4-2.5-5.5z"/><circle cx="19" cy="5" r="2"/></svg></div>
          <span class="footer-brand-name">LAKBAY</span>
        </div>
        <p class="footer-desc">Your trusted companion for discovering and exploring the beautiful hiking trails of Nasugbu, Batangas. Safe trails, local guides, unforgettable journeys.</p>
        <div class="footer-socials">
          <a href="#" class="footer-social" aria-label="Facebook"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/></svg></a>
          <a href="#" class="footer-social" aria-label="Instagram"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="20" height="20" rx="5"/><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/></svg></a>
          <a href="#" class="footer-social" aria-label="Twitter"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 4s-.7 2.1-2 3.4c1.6 10-9.4 17.3-18 11.6 2.2.1 4.4-.6 6-2C3 15.5.5 9.6 3 5c2.2 2.6 5.6 4.1 9 4-.9-4.2 4-6.6 7-3.8 1.1 0 3-1.2 3-1.2z"/></svg></a>
        </div>
      </div>
      <div class="footer-col">
        <h4>Quick Links</h4>
        <nav class="footer-links">
          <a href="<?= $base ?>index.php">Home</a>
          <a href="<?= $base ?>pages/explore/index.php">Explore Mountains</a>
          <a href="<?= $base ?>pages/explore/index.php?quiz=1">Find My Trail</a>
          <a href="<?= $base ?>pages/landing/guidelines.php">Guidelines</a>
          <a href="<?= $base ?>pages/modals/login.php">Login / Sign Up</a>
        </nav>
      </div>
      <div class="footer-col">
        <h4>Our Mountains</h4>
        <nav class="footer-links">
          <a href="<?= $base ?>pages/explore/mountain.php?id=1">Mt. Batulao</a>
          <a href="<?= $base ?>pages/explore/mountain.php?id=2">Mt. Apayang</a>
          <a href="<?= $base ?>pages/explore/mountain.php?id=3">Mt. Lantik</a>
          <a href="<?= $base ?>pages/explore/mountain.php?id=4">Mt. Talamitam</a>
        </nav>
      </div>
      <div class="footer-col">
        <h4>Contact Us</h4>
        <ul class="footer-contact-list">
          <li class="footer-contact-item"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0z"/><circle cx="12" cy="10" r="3"/></svg>Tourism Office, Nasugbu, Batangas, Philippines</li>
          <li class="footer-contact-item"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 9.57 20 20 0 0 1 1.68 1a2 2 0 0 1 1.27-1.85H6a2 2 0 0 1 2 1.72c.127.96.361 1.9.7 2.81a2 2 0 0 1-.45 2.11L7.09 6.91a16 16 0 0 0 6 6l1.06-1.06a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 14.1z"/></svg>+63 (043) 416-0228</li>
          <li class="footer-contact-item"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>info@lakbay-nasugbu.ph</li>
        </ul>
      </div>
    </div>
    <div class="footer-bottom">
      <p>&copy; <?= date('Y') ?> LAKBAY. All rights reserved. Developed for Nasugbu Tourism.</p>
      <div class="footer-bottom-links">
        <a href="<?= $base ?>pages/landing/guidelines.php">Privacy Policy</a>
        <a href="<?= $base ?>pages/landing/guidelines.php">Terms of Service</a>
      </div>
    </div>
  </div>
</footer>
<script src="<?= $base ?>assets/app.js"></script>
</body>
</html>
