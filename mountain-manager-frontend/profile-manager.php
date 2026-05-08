<?php
// profile_manager.php - Manager Profile Management
session_start();
require_once '../config/db.php';

// Check if user is logged in and is a manager
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'manager') {
    header('Location: ../login.php');
    exit();
}

$manager_id = $_SESSION['user_id'];

// Get manager info
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$manager_id]);
$manager = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$manager) {
    session_destroy();
    header('Location: ../login.php');
    exit();
}

$manager_name = $manager['name'];
$manager_email = $manager['email'];
$manager_phone = $manager['phone'] ?? '';
$manager_avatar = $manager['avatar'] ?? null;
$manager_role = ucfirst($manager['role']);
$member_since = date('F j, Y', strtotime($manager['created_at'] ?? 'now'));
$last_login = $manager['last_login'] ? date('M j, Y g:i A', strtotime($manager['last_login'])) : 'Never';
$manager_initials = implode('', array_map(function($word) {
    return strtoupper($word[0]);
}, explode(' ', $manager_name)));

// Get assigned mountains count (use a different variable name)
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM manager_mountains WHERE manager_id = ?");
$stmt->execute([$manager_id]);
$assigned_mountains_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Get total bookings managed
$stmt = $pdo->prepare("
    SELECT COUNT(*) as count FROM bookings b
    INNER JOIN manager_mountains mm ON b.mountain_id = mm.mountain_id
    WHERE mm.manager_id = ?
");
$stmt->execute([$manager_id]);
$total_bookings = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Check if password is still default
$isDefaultPassword = password_verify('Password123!', $manager['password']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>LAKBAY Manager — Profile Settings</title>
<link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'><path fill='%23254A5A' d='M8 3 3 20h18L14 8l-2 4z'/></svg>">

<link rel="stylesheet" href="manager.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
/* Profile page specific styles - matching dashboard aesthetic */
.profile-hero {
    background: var(--ink);
    border-radius: var(--r);
    padding: 28px 32px;
    position: relative;
    overflow: hidden;
    margin-bottom: 24px;
}

.profile-hero::before {
    content: '';
    position: absolute;
    right: -40px;
    top: -40px;
    width: 200px;
    height: 200px;
    border-radius: 50%;
    background: rgba(201,168,76,0.08);
}

.profile-hero::after {
    content: '';
    position: absolute;
    right: 120px;
    bottom: -60px;
    width: 140px;
    height: 140px;
    border-radius: 50%;
    background: rgba(255,255,255,0.03);
}

.profile-hero-content {
    display: flex;
    align-items: center;
    gap: 28px;
    position: relative;
    z-index: 1;
    flex-wrap: wrap;
}

.profile-avatar-large {
    width: 90px;
    height: 90px;
    border-radius: 50%;
    background: linear-gradient(135deg, #c9a84c, #a0823a);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 36px;
    font-weight: 700;
    color: white;
    flex-shrink: 0;
    position: relative;
    cursor: pointer;
}

.profile-avatar-large img {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    object-fit: cover;
}

.profile-avatar-edit {
    position: absolute;
    bottom: 0;
    right: 0;
    background: var(--gold);
    width: 28px;
    height: 28px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2px solid var(--ink);
    transition: all 0.2s;
}

.profile-avatar-edit i {
    font-size: 12px;
    color: var(--ink);
}

.profile-avatar-large:hover .profile-avatar-edit {
    transform: scale(1.1);
}

.profile-hero-info h1 {
    font-family: 'Playfair Display', serif;
    font-size: 24px;
    font-weight: 700;
    color: white;
    margin-bottom: 6px;
}

.profile-hero-info .profile-role {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: rgba(255,255,255,0.12);
    padding: 4px 12px;
    border-radius: 50px;
    font-size: 11px;
    font-weight: 600;
    color: var(--gold);
}

.profile-hero-stats {
    margin-left: auto;
    display: flex;
    gap: 32px;
}

.profile-stat {
    text-align: center;
}

.profile-stat-value {
    font-family: 'Playfair Display', serif;
    font-size: 28px;
    font-weight: 700;
    color: var(--gold);
    line-height: 1;
}

.profile-stat-label {
    font-size: 10px;
    color: rgba(255,255,255,0.5);
    text-transform: uppercase;
    letter-spacing: 1px;
    margin-top: 4px;
}

@media (max-width: 768px) {
    .profile-hero-content {
        flex-direction: column;
        text-align: center;
    }
    .profile-hero-stats {
        margin-left: 0;
        justify-content: center;
    }
}

.profile-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 24px;
}

@media (max-width: 900px) {
    .profile-grid {
        grid-template-columns: 1fr;
    }
}

.panel {
    background: var(--white);
    border-radius: var(--r);
    border: 1px solid var(--border);
    overflow: hidden;
}

.panel-hdr {
    padding: 16px 20px;
    background: var(--off);
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    gap: 10px;
}

.panel-hdr i {
    font-size: 18px;
    color: var(--gold);
}

.panel-hdr h3 {
    font-family: 'Playfair Display', serif;
    font-size: 16px;
    font-weight: 700;
    color: var(--ink);
    margin: 0;
}

.panel-body {
    padding: 20px;
}

.info-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 0;
    border-bottom: 1px solid var(--border);
}

.info-row:last-child {
    border-bottom: none;
}

.info-label {
    font-size: 12px;
    font-weight: 600;
    color: var(--ink3);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.info-value {
    font-size: 13px;
    color: var(--ink);
    font-weight: 500;
}

.badge-role {
    background: rgba(201,168,76,0.15);
    color: var(--gold);
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
}

.badge-status {
    background: rgba(27,112,69,0.15);
    color: #1B7045;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
}

.form-group {
    margin-bottom: 18px;
}

.form-label {
    display: block;
    font-size: 11px;
    font-weight: 700;
    color: var(--ink3);
    margin-bottom: 6px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.form-input {
    width: 100%;
    padding: 10px 14px;
    border: 1.5px solid var(--border);
    border-radius: 10px;
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: 13px;
    transition: all 0.15s;
    background: var(--white);
}

.form-input:focus {
    outline: none;
    border-color: var(--gold);
    box-shadow: 0 0 0 3px rgba(201,168,76,0.1);
}

.form-input:disabled {
    background: var(--off);
    cursor: not-allowed;
}

.btn-primary {
    background: var(--ink);
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 30px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.15s;
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.btn-primary:hover {
    background: #2a1a0a;
    transform: translateY(-1px);
}

.btn-outline {
    background: transparent;
    border: 1.5px solid var(--border);
    color: var(--ink);
}

.btn-outline:hover {
    background: var(--off);
    transform: translateY(-1px);
}

.password-hint {
    background: var(--off);
    border-radius: 10px;
    padding: 12px;
    margin-top: 12px;
    font-size: 10px;
    color: var(--ink3);
}

.password-hint ul {
    margin: 6px 0 0 18px;
    padding: 0;
}

.password-hint li {
    margin: 4px 0;
}

.password-field {
    position: relative;
}

.password-toggle {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    cursor: pointer;
    color: var(--ink3);
    font-size: 14px;
}

/* Modal styles matching dashboard */
.modal-bg {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.6);
    backdrop-filter: blur(8px);
    z-index: 1000;
    display: none;
    align-items: center;
    justify-content: center;
}

.modal-bg.open {
    display: flex;
}

.modal {
    background: var(--white);
    border-radius: 24px;
    width: 90%;
    max-width: 480px;
    max-height: 85vh;
    overflow-y: auto;
}

.modal-hdr {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 18px 24px;
    border-bottom: 1px solid var(--border);
    position: sticky;
    top: 0;
    background: var(--white);
}

.modal-title {
    font-family: 'Playfair Display', serif;
    font-size: 18px;
    font-weight: 700;
    color: var(--ink);
}

.modal-close {
    background: none;
    border: none;
    font-size: 20px;
    cursor: pointer;
    color: var(--ink3);
    transition: all 0.15s;
}

.modal-close:hover {
    color: var(--ink);
    transform: rotate(90deg);
}

.modal-body {
    padding: 24px;
}

.modal-footer {
    padding: 16px 24px;
    border-top: 1px solid var(--border);
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    position: sticky;
    bottom: 0;
    background: var(--white);
}

.avatar-preview {
    text-align: center;
    margin-bottom: 20px;
}

.avatar-preview-image {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    background: linear-gradient(135deg, #c9a84c, #a0823a);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 40px;
    font-weight: 700;
    color: white;
    margin: 0 auto 12px;
    overflow: hidden;
}

.avatar-preview-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.hidden-file {
    display: none;
}

.btn-sm {
    padding: 6px 14px;
    font-size: 11px;
}

hr {
    margin: 16px 0;
    border: none;
    border-top: 1px solid var(--border);
}

.toast-notification {
    position: fixed;
    bottom: 30px;
    left: 50%;
    transform: translateX(-50%) translateY(100px);
    background: var(--ink);
    color: white;
    padding: 12px 24px;
    border-radius: 50px;
    font-size: 13px;
    font-weight: 600;
    z-index: 1100;
    transition: all 0.3s;
    opacity: 0;
    white-space: nowrap;
}

.toast-notification.show {
    transform: translateX(-50%) translateY(0);
    opacity: 1;
}

.toast-notification.success {
    background: #1B7045;
}

.toast-notification.error {
    background: #C0392B;
}
</style>
</head>
<body>
<div class="app-shell">

<?php $activePage = 'profile'; ?>
<?php include 'shared_sidebar.php'; ?>

<div class="main-area" id="mainArea">
    <div class="topbar">
        <div class="topbar-left">
            <button class="sidebar-toggle" onclick="toggleSidebar()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
            </button>
            <div>
                <div class="topbar-page-title">Profile Settings</div>
                <div class="topbar-page-sub">Manage your account information and security</div>
            </div>
        </div>
        <div class="topbar-right">
            <div class="topbar-date" id="topbarDate"></div>
            <div class="topbar-avatar" id="topbarAvatar"><?= $manager_initials ?></div>
        </div>
    </div>

    <div class="content">
        <!-- Hero Section -->
        <div class="profile-hero">
            <div class="profile-hero-content">
                <div class="profile-avatar-large" id="heroAvatar" onclick="openAvatarModal()">
    <?php if ($manager_avatar): ?>
        <img src="../<?= htmlspecialchars($manager_avatar) ?>" alt="Avatar">
    <?php else: ?>
        <?= $manager_initials ?>
    <?php endif; ?>
    <div class="profile-avatar-edit">
        <i class="fas fa-camera"></i>
    </div>
</div>
                <div class="profile-hero-info">
                    <h1><?= htmlspecialchars($manager_name) ?></h1>
                    <div class="profile-role">
                        <i class="fas fa-user-tie"></i> <?= $manager_role ?>
                    </div>
                </div>
                <div class="profile-hero-stats">
                    <div class="profile-stat">
                        <div class="profile-stat-value"><?= $assigned_mountains_count ?></div>
                        <div class="profile-stat-label">Mountains</div>
                    </div>
                    <div class="profile-stat">
                        <div class="profile-stat-value"><?= $total_bookings ?></div>
                        <div class="profile-stat-label">Bookings</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Profile Grid -->
        <div class="profile-grid">
            <!-- Personal Info Panel -->
            <div class="panel">
                <div class="panel-hdr">
                    <i class="fas fa-user-circle"></i>
                    <h3>Personal Information</h3>
                </div>
                <div class="panel-body">
                    <div class="info-row">
                        <span class="info-label">Full Name</span>
                        <span class="info-value" id="displayName"><?= htmlspecialchars($manager_name) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Email Address</span>
                        <span class="info-value" id="displayEmail"><?= htmlspecialchars($manager_email) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Phone Number</span>
                        <span class="info-value" id="displayPhone"><?= htmlspecialchars($manager_phone ?: 'Not set') ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Role</span>
                        <span class="info-value"><span class="badge-role"><?= $manager_role ?></span></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Member Since</span>
                        <span class="info-value"><?= $member_since ?></span>
                    </div>
                    <hr>
                    <button class="btn-primary btn-outline" onclick="openEditInfoModal()">
                        <i class="fas fa-edit"></i> Edit Personal Info
                    </button>
                </div>
            </div>

            <!-- Security Panel -->
            <div class="panel">
                <div class="panel-hdr">
                    <i class="fas fa-lock"></i>
                    <h3>Security Settings</h3>
                </div>
                <div class="panel-body">
                    <div class="info-row">
                        <span class="info-label">Last Login</span>
                        <span class="info-value"><?= $last_login ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">IP Address</span>
                        <span class="info-value"><?= $_SERVER['REMOTE_ADDR'] ?? 'Unknown' ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Default Password</span>
                        <span class="info-value">
                            <?php if ($isDefaultPassword): ?>
                                <span style="color: #C0392B;"><i class="fas fa-exclamation-triangle"></i> Not yet changed</span>
                            <?php else: ?>
                                <span style="color: #1B7045;"><i class="fas fa-check-circle"></i> Changed</span>
                            <?php endif; ?>
                        </span>
                    </div>
                    <hr>
                    <div class="password-hint">
                        <i class="fas fa-shield-alt"></i> <strong>Password Requirements:</strong>
                        <ul>
                            <li>Minimum 8 characters</li>
                            <li>At least one uppercase letter</li>
                            <li>At least one lowercase letter</li>
                            <li>At least one number</li>
                        </ul>
                    </div>
                    <button class="btn-primary" onclick="openChangePasswordModal()" style="margin-top: 12px;">
                        <i class="fas fa-key"></i> Change Password
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
</div>

<!-- Edit Personal Info Modal -->
<div class="modal-bg" id="editInfoModal">
    <div class="modal">
        <div class="modal-hdr">
            <div class="modal-title">Edit Personal Information</div>
            <button class="modal-close" onclick="closeEditInfoModal()">✕</button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label class="form-label">Full Name</label>
                <input type="text" id="editName" class="form-input" value="<?= htmlspecialchars($manager_name) ?>">
            </div>
            <div class="form-group">
                <label class="form-label">Email Address</label>
                <input type="email" id="editEmail" class="form-input" value="<?= htmlspecialchars($manager_email) ?>">
            </div>
            <div class="form-group">
                <label class="form-label">Phone Number</label>
                <input type="tel" id="editPhone" class="form-input" value="<?= htmlspecialchars($manager_phone) ?>" placeholder="+63 XXX XXX XXXX">
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn-primary btn-outline" onclick="closeEditInfoModal()">Cancel</button>
            <button class="btn-primary" onclick="savePersonalInfo()">Save Changes</button>
        </div>
    </div>
</div>

<!-- Change Password Modal -->
<div class="modal-bg" id="changePasswordModal">
    <div class="modal">
        <div class="modal-hdr">
            <div class="modal-title">Change Password</div>
            <button class="modal-close" onclick="closeChangePasswordModal()">✕</button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label class="form-label">Current Password</label>
                <div class="password-field">
                    <input type="password" id="currentPassword" class="form-input" placeholder="Enter your current password">
                    <span class="password-toggle" onclick="togglePassword('currentPassword')">
                        <i class="fas fa-eye"></i>
                    </span>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">New Password</label>
                <div class="password-field">
                    <input type="password" id="newPassword" class="form-input" placeholder="Enter new password">
                    <span class="password-toggle" onclick="togglePassword('newPassword')">
                        <i class="fas fa-eye"></i>
                    </span>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Confirm New Password</label>
                <div class="password-field">
                    <input type="password" id="confirmPassword" class="form-input" placeholder="Confirm new password">
                    <span class="password-toggle" onclick="togglePassword('confirmPassword')">
                        <i class="fas fa-eye"></i>
                    </span>
                </div>
            </div>
            <div class="password-hint" id="passwordHint">
                <i class="fas fa-info-circle"></i> Password must contain:
                <ul id="reqList">
                    <li id="reqLength">✗ At least 8 characters</li>
                    <li id="reqUpper">✗ At least one uppercase letter</li>
                    <li id="reqLower">✗ At least one lowercase letter</li>
                    <li id="reqNumber">✗ At least one number</li>
                </ul>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn-primary btn-outline" onclick="closeChangePasswordModal()">Cancel</button>
            <button class="btn-primary" onclick="changePassword()">Update Password</button>
        </div>
    </div>
</div>

<!-- Change Avatar Modal -->
<div class="modal-bg" id="avatarModal">
    <div class="modal">
        <div class="modal-hdr">
            <div class="modal-title">Change Profile Photo</div>
            <button class="modal-close" onclick="closeAvatarModal()">✕</button>
        </div>
        <div class="modal-body">
            <div class="avatar-preview">
                <div class="avatar-preview-image" id="avatarPreview">
    <?php if ($manager_avatar): ?>
        <img src="../<?= htmlspecialchars($manager_avatar) ?>" alt="Avatar" id="previewImg">
    <?php else: ?>
        <span id="previewInitial"><?= $manager_initials ?></span>
    <?php endif; ?>
</div>
                <input type="file" id="avatarInput" class="hidden-file" accept="image/jpeg,image/png,image/webp">
                <button class="btn-primary btn-outline btn-sm" onclick="document.getElementById('avatarInput').click()">
                    <i class="fas fa-folder-open"></i> Choose File
                </button>
                <p style="font-size: 10px; color: var(--ink3); margin-top: 12px;">
                    Recommended: Square image, max 2MB (JPG, PNG, WEBP)
                </p>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn-primary btn-outline" onclick="closeAvatarModal()">Cancel</button>
            <button class="btn-primary" onclick="saveAvatar()">Upload Photo</button>
        </div>
    </div>
</div>

<!-- Toast Notification -->
<div id="toastNotification" class="toast-notification">
    <i class="fas"></i>
    <span id="toastMessage"></span>
</div>

<script>
// Toggle sidebar
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const mainArea = document.getElementById('mainArea');
    if (sidebar && mainArea) {
        if (window.innerWidth <= 768) {
            sidebar.classList.toggle('mobile-open');
        } else {
            sidebar.classList.toggle('collapsed');
            mainArea.classList.toggle('expanded');
        }
    }
}

// Update clock
function updateClock() {
    const d = new Date();
    const dateEl = document.getElementById('topbarDate');
    if (dateEl) {
        dateEl.textContent = d.toLocaleDateString('en-PH', {weekday:'short', month:'short', day:'numeric'}).toUpperCase() + ' ' + d.toLocaleTimeString('en-PH', {hour:'2-digit', minute:'2-digit'});
    }
}
setInterval(updateClock, 1000);
updateClock();

// Toast
function showToast(message, type = 'success') {
    const toast = document.getElementById('toastNotification');
    const icon = toast.querySelector('i');
    const msgSpan = document.getElementById('toastMessage');
    
    toast.classList.remove('success', 'error');
    if (type === 'success') {
        toast.classList.add('success');
        icon.className = 'fas fa-check-circle';
    } else {
        toast.classList.add('error');
        icon.className = 'fas fa-exclamation-circle';
    }
    
    msgSpan.textContent = message;
    toast.classList.add('show');
    
    setTimeout(() => {
        toast.classList.remove('show');
    }, 3000);
}

// Modal functions
function openEditInfoModal() {
    document.getElementById('editInfoModal').classList.add('open');
}

function closeEditInfoModal() {
    document.getElementById('editInfoModal').classList.remove('open');
}

function openChangePasswordModal() {
    document.getElementById('changePasswordModal').classList.add('open');
    document.getElementById('currentPassword').value = '';
    document.getElementById('newPassword').value = '';
    document.getElementById('confirmPassword').value = '';
    resetPasswordRequirements();
}

function closeChangePasswordModal() {
    document.getElementById('changePasswordModal').classList.remove('open');
}

function openAvatarModal() {
    document.getElementById('avatarModal').classList.add('open');
}

function closeAvatarModal() {
    document.getElementById('avatarModal').classList.remove('open');
}

// Toggle password visibility
function togglePassword(fieldId) {
    const field = document.getElementById(fieldId);
    const toggle = field.nextElementSibling;
    if (field.type === 'password') {
        field.type = 'text';
        toggle.innerHTML = '<i class="fas fa-eye-slash"></i>';
    } else {
        field.type = 'password';
        toggle.innerHTML = '<i class="fas fa-eye"></i>';
    }
}

// Password validation
function validatePassword(password) {
    const requirements = {
        length: password.length >= 8,
        upper: /[A-Z]/.test(password),
        lower: /[a-z]/.test(password),
        number: /[0-9]/.test(password)
    };
    
    updateRequirementsUI(requirements);
    return requirements.length && requirements.upper && requirements.lower && requirements.number;
}

function updateRequirementsUI(requirements) {
    const lengthReq = document.getElementById('reqLength');
    const upperReq = document.getElementById('reqUpper');
    const lowerReq = document.getElementById('reqLower');
    const numberReq = document.getElementById('reqNumber');
    
    lengthReq.innerHTML = (requirements.length ? '✓' : '✗') + ' At least 8 characters';
    lengthReq.style.color = requirements.length ? '#1B7045' : '#C0392B';
    
    upperReq.innerHTML = (requirements.upper ? '✓' : '✗') + ' At least one uppercase letter';
    upperReq.style.color = requirements.upper ? '#1B7045' : '#C0392B';
    
    lowerReq.innerHTML = (requirements.lower ? '✓' : '✗') + ' At least one lowercase letter';
    lowerReq.style.color = requirements.lower ? '#1B7045' : '#C0392B';
    
    numberReq.innerHTML = (requirements.number ? '✓' : '✗') + ' At least one number';
    numberReq.style.color = requirements.number ? '#1B7045' : '#C0392B';
}

function resetPasswordRequirements() {
    const reqs = ['reqLength', 'reqUpper', 'reqLower', 'reqNumber'];
    reqs.forEach(id => {
        const el = document.getElementById(id);
        el.style.color = '';
    });
    document.getElementById('reqLength').innerHTML = '✗ At least 8 characters';
    document.getElementById('reqUpper').innerHTML = '✗ At least one uppercase letter';
    document.getElementById('reqLower').innerHTML = '✗ At least one lowercase letter';
    document.getElementById('reqNumber').innerHTML = '✗ At least one number';
}

// Listen to new password input
document.addEventListener('DOMContentLoaded', function() {
    const newPasswordInput = document.getElementById('newPassword');
    if (newPasswordInput) {
        newPasswordInput.addEventListener('input', function() {
            validatePassword(this.value);
        });
    }
});

// Save personal info
async function savePersonalInfo() {
    const name = document.getElementById('editName').value.trim();
    const email = document.getElementById('editEmail').value.trim();
    const phone = document.getElementById('editPhone').value.trim();
    
    if (!name || !email) {
        showToast('Please fill in all required fields', 'error');
        return;
    }
    
    if (!/^\S+@\S+\.\S+$/.test(email)) {
        showToast('Please enter a valid email address', 'error');
        return;
    }
    
    const formData = new FormData();
    formData.append('name', name);
    formData.append('email', email);
    formData.append('phone', phone);
    
    showToast('Updating profile...', 'success');
    
    try {
        const response = await fetch('../api/update_manager_profile.php', {
            method: 'POST',
            body: formData
        });
        const result = await response.json();
        
        if (result.success) {
            showToast('Profile updated successfully!', 'success');
            
            document.getElementById('displayName').textContent = name;
            document.getElementById('displayEmail').textContent = email;
            document.getElementById('displayPhone').textContent = phone || 'Not set';
            
            const initials = name.split(' ').map(w => w[0]).join('').toUpperCase();
            const topbarAvatar = document.getElementById('topbarAvatar');
            const heroAvatar = document.getElementById('heroAvatar');
            
            if (topbarAvatar && heroAvatar && !heroAvatar.querySelector('img')) {
                topbarAvatar.textContent = initials;
                heroAvatar.innerHTML = initials + '<div class="profile-avatar-edit"><i class="fas fa-camera"></i></div>';
            }
            
            closeEditInfoModal();
            setTimeout(() => location.reload(), 1500);
        } else {
            showToast(result.message || 'Error updating profile', 'error');
        }
    } catch (error) {
        showToast('Network error. Please try again.', 'error');
    }
}

// Change password
async function changePassword() {
    const currentPassword = document.getElementById('currentPassword').value;
    const newPassword = document.getElementById('newPassword').value;
    const confirmPassword = document.getElementById('confirmPassword').value;
    
    if (!currentPassword) {
        showToast('Please enter your current password', 'error');
        return;
    }
    
    if (!validatePassword(newPassword)) {
        showToast('Please meet all password requirements', 'error');
        return;
    }
    
    if (newPassword !== confirmPassword) {
        showToast('New passwords do not match', 'error');
        return;
    }
    
    showToast('Changing password...', 'success');
    
    try {
        const response = await fetch('../api/change_manager_password.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                current_password: currentPassword,
                new_password: newPassword
            })
        });
        const result = await response.json();
        
        if (result.success) {
            showToast('Password changed successfully! Please login again.', 'success');
            setTimeout(() => {
                window.location.href = '../login-and-signup/login.php';
            }, 2000);
        } else {
            showToast(result.message || 'Error changing password', 'error');
        }
    } catch (error) {
        showToast('Network error. Please try again.', 'error');
    }
}

// Avatar preview
document.getElementById('avatarInput')?.addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file && file.type.startsWith('image/')) {
        if (file.size > 2 * 1024 * 1024) {
            showToast('Image must be less than 2MB', 'error');
            this.value = '';
            return;
        }
        
        const reader = new FileReader();
        reader.onload = function(evt) {
            const preview = document.getElementById('avatarPreview');
            preview.innerHTML = `<img src="${evt.target.result}" alt="Preview" style="width:100%;height:100%;object-fit:cover;">`;
        };
        reader.readAsDataURL(file);
    }
});

// Save avatar
async function saveAvatar() {
    const fileInput = document.getElementById('avatarInput');
    const file = fileInput.files[0];
    
    if (!file) {
        showToast('Please select an image first', 'error');
        return;
    }
    
    const formData = new FormData();
    formData.append('avatar', file);
    
    showToast('Uploading photo...', 'success');
    
    try {
        const response = await fetch('../api/update_manager_avatar.php', {
            method: 'POST',
            body: formData
        });
        const result = await response.json();
        
        if (result.success) {
            showToast('Profile photo updated!', 'success');
            const heroAvatar = document.getElementById('heroAvatar');
            heroAvatar.innerHTML = `<img src="${result.avatar_url}?t=${Date.now()}" alt="Avatar"><div class="profile-avatar-edit"><i class="fas fa-camera"></i></div>`;
            closeAvatarModal();
            setTimeout(() => location.reload(), 1500);
        } else {
            showToast(result.message || 'Error uploading photo', 'error');
        }
    } catch (error) {
        showToast('Network error. Please try again.', 'error');
    }
}

// Close modals when clicking outside
document.querySelectorAll('.modal-bg').forEach(modal => {
    modal.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.remove('open');
        }
    });
});
</script>
</body>
</html>