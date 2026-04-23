<?php
// Make sure variables are available
$adminName = $adminName ?? $_SESSION['user_name'] ?? 'Admin';
$adminInitial = $adminInitial ?? strtoupper(substr($adminName, 0, 1));
?>
<div class="toast-container" id="toastContainer"></div>
<style>
/* Modal styles */
#profileModal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 9999;
    align-items: center;
    justify-content: center;
}

#profileModal.open {
    display: flex !important;
}

.profile-modal-content {
    background: white;
    border-radius: 20px;
    width: 90%;
    max-width: 500px;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
}

.profile-modal-header {
    padding: 20px 24px;
    border-bottom: 1px solid #eef2f8;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.profile-modal-title {
    font-size: 1.2rem;
    font-weight: 600;
    font-family: 'Cormorant Garamond', serif;
}

.profile-modal-close {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: #666;
}

.profile-modal-body {
    padding: 24px;
}

.profile-field {
    margin-bottom: 16px;
}

.profile-field label {
    display: block;
    font-size: 12px;
    font-weight: 600;
    color: #666;
    margin-bottom: 4px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.profile-field input {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #e5e9ef;
    border-radius: 8px;
    font-size: 14px;
    font-family: 'Inter', sans-serif;
}

.profile-field input:focus {
    outline: none;
    border-color: #111318;
}

.profile-avatar-section {
    text-align: center;
    margin-bottom: 24px;
}

.profile-avatar {
    width: 80px;
    height: 80px;
    background: #111318;
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 32px;
    font-weight: 600;
    margin: 0 auto 12px;
}

.profile-change-avatar {
    background: #f0f2f5;
    border: none;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    cursor: pointer;
    font-family: 'Inter', sans-serif;
}

.profile-modal-footer {
    padding: 16px 24px;
    border-top: 1px solid #eef2f8;
    display: flex;
    justify-content: flex-end;
    gap: 12px;
}

.btn-primary {
    background: #111318;
    color: white;
    border: none;
    padding: 8px 20px;
    border-radius: 8px;
    cursor: pointer;
    font-family: 'Inter', sans-serif;
}

.btn-secondary {
    background: #f0f2f5;
    color: #333;
    border: none;
    padding: 8px 20px;
    border-radius: 8px;
    cursor: pointer;
    font-family: 'Inter', sans-serif;
}

.tab-buttons {
    display: flex;
    gap: 4px;
    padding: 0 24px;
    border-bottom: 1px solid #eef2f8;
}

.tab-btn {
    padding: 12px 16px;
    background: none;
    border: none;
    cursor: pointer;
    font-size: 13px;
    font-weight: 500;
    color: #666;
    font-family: 'Inter', sans-serif;
}

.tab-btn.active {
    color: #111318;
    border-bottom: 2px solid #111318;
}

.tab-content {
    display: none;
    padding: 20px 0;
}

.tab-content.active {
    display: block;
}

.info-row {
    display: flex;
    justify-content: space-between;
    padding: 10px 0;
    border-bottom: 1px solid #eef2f8;
    font-size: 14px;
}

.info-label {
    font-weight: 600;
    color: #666;
}

.info-value {
    color: #111318;
}

.badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
}

.badge-green {
    background: #d1fae5;
    color: #065f46;
}
/* Toast Notification Styles */
.toast-container {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 10000;
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.toast {
    min-width: 300px;
    max-width: 400px;
    background: white;
    border-radius: 12px;
    padding: 14px 18px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.15);
    display: flex;
    align-items: center;
    gap: 12px;
    animation: slideInRight 0.3s ease;
    position: relative;
    overflow: hidden;
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

.toast.success {
    border-left: 4px solid #1E7B48;
}
.toast.success .toast-icon {
    color: #1E7B48;
}

.toast.error {
    border-left: 4px solid #C0392B;
}
.toast.error .toast-icon {
    color: #C0392B;
}

.toast.warning {
    border-left: 4px solid #E67E22;
}
.toast.warning .toast-icon {
    color: #E67E22;
}

.toast.info {
    border-left: 4px solid #3498db;
}
.toast.info .toast-icon {
    color: #3498db;
}

.toast-icon {
    font-size: 20px;
    flex-shrink: 0;
}

.toast-content {
    flex: 1;
}

.toast-title {
    font-size: 14px;
    font-weight: 600;
    color: #111318;
    margin-bottom: 2px;
}

.toast-message {
    font-size: 12px;
    color: #5B6A7E;
    line-height: 1.4;
}

.toast-close {
    background: none;
    border: none;
    font-size: 14px;
    cursor: pointer;
    color: #8A99AE;
    padding: 0;
    flex-shrink: 0;
    transition: color 0.15s;
}

.toast-close:hover {
    color: #111318;
}

.toast-progress {
    position: absolute;
    bottom: 0;
    left: 0;
    height: 3px;
    width: 100%;
    animation: progress 3s linear forwards;
}

.toast.success .toast-progress {
    background: #1E7B48;
}

.toast.error .toast-progress {
    background: #C0392B;
}

.toast.warning .toast-progress {
    background: #E67E22;
}

@keyframes progress {
    from {
        width: 100%;
    }
    to {
        width: 0%;
    }
}
</style>

<div id="profileModal">
    <div class="profile-modal-content">
        <div class="profile-modal-header">
            <div class="profile-modal-title">Account Settings</div>
            <button class="profile-modal-close" onclick="closeProfileModal()">&times;</button>
        </div>
        
        <div class="tab-buttons">
            <button class="tab-btn active" onclick="switchTab('profile')">Profile</button>
            <button class="tab-btn" onclick="switchTab('security')">Security</button>
            <button class="tab-btn" onclick="switchTab('activity')">Activity</button>
        </div>
        
        <div class="profile-modal-body">
            <!-- Profile Tab -->
            <div id="profileTab" class="tab-content active">
                <div class="profile-avatar-section">
    <div class="profile-avatar" id="modalAvatar">
        <?php if (!empty($_SESSION['user_avatar'])): ?>
            <img src="<?= htmlspecialchars($_SESSION['user_avatar']) ?>" alt="Avatar" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">
        <?php else: ?>
            <?= htmlspecialchars($adminInitial) ?>
        <?php endif; ?>
    </div>
    <button class="profile-change-avatar" onclick="document.getElementById('avatarFileInput').click()">Change Photo</button>
    <input type="file" id="avatarFileInput" accept="image/*" style="display: none;">
</div>
                
                <div class="profile-field">
                    <label>Full Name</label>
                    <input type="text" id="profileName" value="<?= htmlspecialchars($adminName) ?>">
                </div>
                
                <div class="profile-field">
                    <label>Email Address</label>
                    <input type="email" id="profileEmail" value="<?= htmlspecialchars($_SESSION['user_email'] ?? 'admin@lakbay.com') ?>">
                </div>
                
                <div class="profile-field">
                    <label>Phone Number</label>
                    <input type="tel" id="profilePhone" placeholder="+63 XXX XXX XXXX" value="<?= htmlspecialchars($_SESSION['user_phone'] ?? '') ?>">
                </div>
                
                <div class="info-row">
                    <span class="info-label">Role</span>
                    <span class="info-value"><span class="badge badge-green">Administrator</span></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Member Since</span>
                    <span class="info-value"><?= date('F j, Y', strtotime($_SESSION['created_at'] ?? 'now')) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Account Status</span>
                    <span class="info-value"><span class="badge badge-green">Active</span></span>
                </div>
            </div>
            
            <!-- Security Tab -->
            <div id="securityTab" class="tab-content">
                <div class="profile-field">
                    <label>Current Password</label>
                    <input type="password" id="currentPassword" placeholder="Enter current password">
                </div>
                <div class="profile-field">
                    <label>New Password</label>
                    <input type="password" id="newPassword" placeholder="Enter new password">
                    <small style="font-size: 11px; color: #999;">Minimum 8 characters</small>
                </div>
                <div class="profile-field">
                    <label>Confirm New Password</label>
                    <input type="password" id="confirmPassword" placeholder="Confirm new password">
                </div>
                <button class="btn-primary" style="width: 100%;" onclick="changePassword()">Update Password</button>
            </div>
            
            <!-- Activity Tab -->
            <div id="activityTab" class="tab-content">
                <div class="info-row">
                    <span class="info-label">Current Session IP</span>
                    <span class="info-value"><?= $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1' ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Browser</span>
                    <span class="info-value"><?= $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown' ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Last Login</span>
                    <span class="info-value"><?= date('M j, Y g:i A') ?></span>
                </div>
            </div>
        </div>
        
        <div class="profile-modal-footer">
            <button class="btn-secondary" onclick="closeProfileModal()">Cancel</button>
            <button class="btn-primary" onclick="saveProfile()">Save Changes</button>
        </div>
    </div>
</div>

<script>
function openProfileModal() {
    console.log('Opening profile modal');
    const modal = document.getElementById('profileModal');
    if (modal) {
        loadUserData();
        modal.style.display = 'flex';
        modal.classList.add('open');
    } else {
        console.error('Modal not found!');
        showToast('Error', 'Profile modal not found. Please refresh the page.', 'error');
    }
}

function closeProfileModal() {
    const modal = document.getElementById('profileModal');
    if (modal) {
        modal.style.display = 'none';
        modal.classList.remove('open');
    }
}

function switchTab(tabName) {
    // Update tab buttons
    document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
    event.target.classList.add('active');
    
    // Update tab contents
    document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
    document.getElementById(tabName + 'Tab').classList.add('active');
}

function loadUserData() {
    // No need to fetch - data is already in the page
    console.log('User data already loaded from PHP');
}

function saveProfile() {
    const name = document.getElementById('profileName').value;
    const email = document.getElementById('profileEmail').value;
    const phone = document.getElementById('profilePhone').value;
    
    if (!name || !email) {
        showToast('Validation Error', 'Please fill in all required fields.', 'warning');
        return;
    }
    
    const formData = new FormData();
    formData.append('name', name);
    formData.append('email', email);
    formData.append('phone', phone);
    
    // Check if avatar file was selected
    const avatarFile = document.getElementById('avatarFileInput').files[0];
    if (avatarFile) {
        formData.append('avatar', avatarFile);
    }
    
    // Show loading toast
    showToast('Updating Profile', 'Please wait while we update your information...', 'info');
    
    fetch('/api/update_user_profile.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            showToast('Success!', 'Profile updated successfully!', 'success');
            
            // Update the displayed name and avatar in the topbar
            const topbarName = document.querySelector('.topbar-user');
            if (topbarName) {
                const nameParts = name.split(' ');
                const initial = nameParts[0].charAt(0).toUpperCase();
                
                // Update name text
                topbarName.childNodes.forEach(node => {
                    if (node.nodeType === Node.TEXT_NODE && node.textContent.trim() !== '') {
                        node.textContent = name;
                    }
                });
                
                // Update avatar
                const avatarDiv = topbarName.querySelector('.avatar');
                if (avatarDiv) {
                    if (avatarFile) {
                        const reader = new FileReader();
                        reader.onload = function(evt) {
                            avatarDiv.innerHTML = `<img src="${evt.target.result}" alt="Avatar" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">`;
                        };
                        reader.readAsDataURL(avatarFile);
                    } else {
                        avatarDiv.innerHTML = initial;
                    }
                }
            }
            
            setTimeout(() => {
                closeProfileModal();
                location.reload();
            }, 1500);
        } else {
            showToast('Update Failed', result.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Error', 'An error occurred while saving. Please try again.', 'error');
    });
}

function changePassword() {
    const currentPwd = document.getElementById('currentPassword').value;
    const newPwd = document.getElementById('newPassword').value;
    const confirmPwd = document.getElementById('confirmPassword').value;
    
    if (!currentPwd) {
        showToast('Validation Error', 'Please enter your current password.', 'warning');
        return;
    }
    if (newPwd.length < 8) {
        showToast('Validation Error', 'New password must be at least 8 characters.', 'warning');
        return;
    }
    if (newPwd !== confirmPwd) {
        showToast('Validation Error', 'New passwords do not match.', 'warning');
        return;
    }
    
    // Show loading toast
    showToast('Changing Password', 'Please wait while we update your password...', 'info');
    
    fetch('/api/change_password.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ 
            current_password: currentPwd, 
            new_password: newPwd 
        })
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            showToast('Password Changed!', 'Please login again with your new password.', 'success');
            setTimeout(() => { 
                window.location.href = '/pages/modals/logout.php'; 
            }, 2000);
        } else {
            showToast('Password Change Failed', result.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Error', 'An error occurred while changing your password.', 'error');
    });
}
// Avatar upload preview with validation
document.getElementById('avatarFileInput')?.addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file && file.type.startsWith('image/')) {
        // Check file size (max 2MB)
        if (file.size > 2 * 1024 * 1024) {
            showToast('File Too Large', 'Maximum image size is 2MB.', 'error');
            this.value = '';
            return;
        }
        
        // Check file type
        const allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        if (!allowedTypes.includes(file.type)) {
            showToast('Invalid File Type', 'Please upload JPG, PNG, WEBP, or GIF images only.', 'error');
            this.value = '';
            return;
        }
        
        const reader = new FileReader();
        reader.onload = function(evt) {
            const avatarDiv = document.getElementById('modalAvatar');
            avatarDiv.innerHTML = `<img src="${evt.target.result}" alt="Avatar" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">`;
            showToast('Photo Updated', 'Your new profile photo has been previewed. Save changes to apply.', 'success');
        };
        reader.readAsDataURL(file);
    }
});
// Make sure modal is hidden on page load
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('profileModal');
    if (modal) {
        modal.style.display = 'none';
    }
});

// Toast Notification System
function showToast(title, message, type = 'success') {
    const container = document.getElementById('toastContainer');
    if (!container) return;
    
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    
    let icon = '';
    switch(type) {
        case 'success':
            icon = '<i class="fas fa-check-circle"></i>';
            break;
        case 'error':
            icon = '<i class="fas fa-exclamation-circle"></i>';
            break;
        case 'warning':
            icon = '<i class="fas fa-exclamation-triangle"></i>';
            break;
        case 'info':
            icon = '<i class="fas fa-info-circle"></i>';
            break;
        default:
            icon = '<i class="fas fa-bell"></i>';
    }
    
    toast.innerHTML = `
        <div class="toast-icon">${icon}</div>
        <div class="toast-content">
            <div class="toast-title">${title}</div>
            <div class="toast-message">${message}</div>
        </div>
        <button class="toast-close" onclick="this.closest('.toast').remove()">&times;</button>
        <div class="toast-progress"></div>
    `;
    
    container.appendChild(toast);
    
    // Auto remove after 3 seconds
    setTimeout(() => {
        if (toast && toast.parentNode) {
            toast.style.animation = 'slideOutRight 0.3s ease';
            setTimeout(() => toast.remove(), 300);
        }
    }, 3000);
}

// Add slideOutRight animation
const style = document.createElement('style');
style.textContent = `
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
`;
document.head.appendChild(style);
</script>