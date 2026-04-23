<!-- Logout Confirmation Modal -->
<div id="logoutModal" class="logout-modal-overlay" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:10000;align-items:center;justify-content:center;">
    <div class="logout-modal-box">
        <div style="text-align:center;margin-bottom:20px;">
            <div style="width:52px;height:52px;background:#fee2e2;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;margin-bottom:16px;">
                <i class="fas fa-right-from-bracket" style="font-size:22px;color:#dc2626;"></i>
            </div>
            <h3 style="font-size:1.15rem;font-weight:600;margin:0 0 8px;font-family:'Inter',sans-serif;">Confirm Logout</h3>
            <p style="color:#5B6A7E;font-size:0.83rem;margin:0;line-height:1.6;">Are you sure you want to log out? You'll need to log in again to access your account.</p>
        </div>
        <div style="display:flex;gap:10px;">
            <button id="cancelLogoutBtn" style="flex:1;padding:10px 14px;border:1.5px solid #E5E9EF;background:white;border-radius:8px;font-family:'Inter',sans-serif;font-size:0.85rem;font-weight:500;cursor:pointer;color:#111318;">Cancel</button>
            <form id="logoutForm" method="POST" action="/pages/modals/logout.php" style="flex:1;margin:0;">
                <button type="submit" style="width:100%;padding:10px 14px;background:#dc2626;color:white;border:none;border-radius:8px;font-family:'Inter',sans-serif;font-size:0.85rem;font-weight:500;cursor:pointer;">
                    <i class="fas fa-right-from-bracket"></i> Log Out
                </button>
            </form>
        </div>
    </div>
</div>

<script>
// Global logout modal functions
function showLogoutModal() {
    const modal = document.getElementById('logoutModal');
    if (modal) {
        modal.style.display = 'flex';
        void modal.offsetHeight; // trigger reflow for animation
        modal.classList.add('open');
    }
}

function hideLogoutModal() {
    const modal = document.getElementById('logoutModal');
    if (modal) {
        modal.classList.remove('open');
        setTimeout(() => { modal.style.display = 'none'; }, 200);
    }
}

// Close modal when clicking outside
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('logoutModal');
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                hideLogoutModal();
            }
        });
        const cancelBtn = document.getElementById('cancelLogoutBtn');
        if (cancelBtn) {
            cancelBtn.addEventListener('click', hideLogoutModal);
        }
    }
});
</script>

<style>
/* ── Logout Modal — isolated from page .modal classes ── */
.logout-modal-overlay {
    position: fixed !important;
    top: 0 !important; left: 0 !important;
    width: 100% !important; height: 100% !important;
    background: rgba(10,12,18,0.52) !important;
    backdrop-filter: blur(4px);
    z-index: 10000 !important;
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.2s ease;
    box-sizing: border-box;
    padding: 20px;
}
.logout-modal-overlay.open {
    opacity: 1;
}
.logout-modal-box {
    background: #fff;
    border-radius: 16px;
    max-width: 400px;
    width: 100%;
    padding: 28px 24px 22px;
    box-shadow: 0 24px 48px rgba(0,0,0,0.18);
    transform: translateY(12px);
    transition: transform 0.22s ease;
    animation: logoutSlideIn 0.22s ease forwards;
}
@keyframes logoutSlideIn {
    from { opacity: 0; transform: translateY(16px); }
    to   { opacity: 1; transform: translateY(0); }
}
</style>