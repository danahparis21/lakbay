<?php
/**
 * messaging-modal.php
 * Include this file in guides.php (or any admin page).
 * Requires: session with user_id, db.php already required by the parent.
 *
 * Encryption: All message bodies are stored via MySQL AES_ENCRYPT()
 * and retrieved via AES_DECRYPT() using the key defined in db.php or config.
 *
 * Usage in guides.php:
 *   include_once __DIR__ . '/messaging-modal.php';
 * And in JS replace the message-guide-btn handler with openMessageModal(guideId, guideName).
 */

// ─── Load the AES key from config (define MSG_AES_KEY in db.php or a secrets file) ───
if (!defined('MSG_AES_KEY')) {
    // Fallback: read from environment variable (recommended for production)
    $envKey = getenv('LAKBAY_MSG_KEY');
    if (!$envKey) {
        // Last resort — define a key here only for local dev, NOT production
        define('MSG_AES_KEY', 'change-this-to-a-32-char-secret!!');
    } else {
        define('MSG_AES_KEY', $envKey);
    }
}

$currentUserId = $_SESSION['user_id'];
?>

<!-- ═══════════════════════════════════════════════
     MESSAGING MODAL
═══════════════════════════════════════════════ -->
<div class="modal-overlay" id="messagingModal">
    <div class="modal-box" style="max-width:560px; height:80vh; display:flex; flex-direction:column;">

        <!-- Header -->
        <div class="modal-header" style="flex-shrink:0;">
            <div>
                <div class="modal-title" id="msgModalTitle">Message</div>
                <div class="modal-subtitle" id="msgModalSubtitle">DIRECT MESSAGE · ENCRYPTED</div>
            </div>
            <button class="modal-close" onclick="closeMessageModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <!-- Message thread -->
        <div id="msgThread" style="
            flex:1; overflow-y:auto; padding:16px 24px;
            display:flex; flex-direction:column; gap:10px;
            background:#FAFBFC;
        ">
            <div id="msgLoadingSpinner" style="text-align:center; padding:40px; color:#8A99AE;">
                <i class="fas fa-spinner fa-spin"></i>&nbsp; Loading messages…
            </div>
        </div>

        <!-- Compose area -->
        <div style="
            flex-shrink:0; padding:14px 20px 18px;
            border-top:1px solid #EFF2F6; background:#fff;
            display:flex; gap:10px; align-items:flex-end;
        ">
            <textarea
                id="msgCompose"
                rows="2"
                class="form-control"
                placeholder="Write a message…"
                style="flex:1; resize:none; font-size:13.5px; line-height:1.5;"
                onkeydown="handleMsgKey(event)"
            ></textarea>
            <button class="btn btn-primary" id="msgSendBtn" onclick="sendMessage()" style="flex-shrink:0; height:44px;">
                <i class="fas fa-paper-plane"></i>
            </button>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════
     MESSAGING STYLES
═══════════════════════════════════════════════ -->
<style>
    /* Bubble base */
    .msg-bubble {
        max-width: 75%;
        padding: 9px 14px;
        border-radius: 18px;
        font-size: 13px;
        line-height: 1.5;
        word-wrap: break-word;
    }
    /* Sent by current admin — right aligned */
    .msg-sent {
        align-self: flex-end;
        background: #111318;
        color: #fff;
        border-bottom-right-radius: 4px;
    }
    /* Received from guide — left aligned */
    .msg-recv {
        align-self: flex-start;
        background: #EFF2F6;
        color: #111318;
        border-bottom-left-radius: 4px;
    }
    .msg-meta {
        font-size: 10px;
        color: #8A99AE;
        margin-top: 3px;
        font-family: 'DM Mono', monospace;
    }
    .msg-sent-wrap { display:flex; flex-direction:column; align-items:flex-end; }
    .msg-recv-wrap { display:flex; flex-direction:column; align-items:flex-start; }
    .msg-date-divider {
        text-align: center; font-size: 10px; letter-spacing: 0.8px;
        text-transform: uppercase; color: #B0BAC8; margin: 8px 0;
        font-family: 'DM Mono', monospace;
    }
    #msgThread::-webkit-scrollbar { width: 4px; }
    #msgThread::-webkit-scrollbar-thumb { background: #DDE1E8; border-radius: 4px; }
    /* Encryption badge */
    .enc-badge {
        display:inline-flex; align-items:center; gap:5px;
        background:#F0FFF6; border:1px solid #BBF0D1;
        color:#1E7B48; font-size:10px; font-weight:600;
        letter-spacing:0.5px; padding:2px 8px; border-radius:20px;
        font-family:'DM Mono',monospace;
    }
</style>

<!-- ═══════════════════════════════════════════════
     MESSAGING JAVASCRIPT
═══════════════════════════════════════════════ -->
<script>
let _msgGuideId   = null;
let _msgGuideName = null;
let _msgPollTimer = null;
const MSG_CURRENT_USER_ID = <?= json_encode((int)$currentUserId) ?>;

function openMessageModal(guideId, guideName) {
    _msgGuideId   = guideId;
    _msgGuideName = guideName;

    document.getElementById('msgModalTitle').textContent   = guideName;
    document.getElementById('msgModalSubtitle').innerHTML  =
        'DIRECT MESSAGE &nbsp;·&nbsp; <span class="enc-badge"><i class="fas fa-lock" style="font-size:9px;"></i> AES-256 ENCRYPTED</span>';

    document.getElementById('messagingModal').classList.add('open');
    document.getElementById('msgCompose').focus();

    loadMessages();

    // Poll for new messages every 5 seconds while modal is open
    _msgPollTimer = setInterval(loadMessages, 5000);
}

function closeMessageModal() {
    document.getElementById('messagingModal').classList.remove('open');
    clearInterval(_msgPollTimer);
    _msgGuideId   = null;
    _msgGuideName = null;
    document.getElementById('msgThread').innerHTML =
        '<div id="msgLoadingSpinner" style="text-align:center;padding:40px;color:#8A99AE;"><i class="fas fa-spinner fa-spin"></i>&nbsp; Loading messages…</div>';
}

async function loadMessages() {
    if (!_msgGuideId) return;

    try {
        const res  = await fetch(`process_message.php?action=fetch&guide_id=${_msgGuideId}`);
        const data = await res.json();

        if (!data.success) {
            console.error('Message fetch error:', data.message);
            return;
        }

        renderMessages(data.messages);
        markAsRead();
    } catch (err) {
        console.error('Message load error:', err);
    }
}

function renderMessages(messages) {
    const thread = document.getElementById('msgThread');

    if (!messages || messages.length === 0) {
        thread.innerHTML = `
            <div style="text-align:center;padding:60px 20px;color:#B0BAC8;">
                <i class="fas fa-comment-slash" style="font-size:32px;margin-bottom:12px;display:block;opacity:0.4;"></i>
                No messages yet. Start the conversation!
            </div>`;
        return;
    }

    let html         = '';
    let lastDateStr  = '';

    messages.forEach(msg => {
        const isSent   = parseInt(msg.sender_id) === MSG_CURRENT_USER_ID;
        const wrapCls  = isSent ? 'msg-sent-wrap' : 'msg-recv-wrap';
        const bubbleCls = isSent ? 'msg-bubble msg-sent' : 'msg-bubble msg-recv';

        const d        = new Date(msg.created_at);
        const dateStr  = d.toLocaleDateString('en-PH', { month:'short', day:'numeric', year:'numeric' });
        const timeStr  = d.toLocaleTimeString('en-PH', { hour:'2-digit', minute:'2-digit' });

        if (dateStr !== lastDateStr) {
            html += `<div class="msg-date-divider">${escHtml(dateStr)}</div>`;
            lastDateStr = dateStr;
        }

        html += `
            <div class="${wrapCls}">
                <div class="${bubbleCls}">${escHtml(msg.body)}</div>
                <div class="msg-meta">${timeStr}</div>
            </div>`;
    });

    thread.innerHTML = html;
    thread.scrollTop = thread.scrollHeight;
}

async function sendMessage() {
    const compose = document.getElementById('msgCompose');
    const body    = compose.value.trim();

    if (!body || !_msgGuideId) return;

    const btn = document.getElementById('msgSendBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

    try {
        const fd = new FormData();
        fd.append('action',   'send');
        fd.append('guide_id', _msgGuideId);
        fd.append('body',     body);

        const res  = await fetch('process_message.php', { method:'POST', body: fd });
        const data = await res.json();

        if (data.success) {
            compose.value = '';
            await loadMessages();
        } else {
            showCustomAlert('Failed to send message: ' + data.message, 'Error');
        }
    } catch (err) {
        console.error('Send error:', err);
        showCustomAlert('An error occurred while sending the message.', 'Error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-paper-plane"></i>';
        compose.focus();
    }
}

async function markAsRead() {
    if (!_msgGuideId) return;
    const fd = new FormData();
    fd.append('action',   'mark_read');
    fd.append('guide_id', _msgGuideId);
    await fetch('process_message.php', { method:'POST', body: fd }).catch(() => {});
}

function handleMsgKey(e) {
    // Ctrl/Cmd + Enter sends
    if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
        e.preventDefault();
        sendMessage();
    }
}

function escHtml(str) {
    if (!str) return '';
    return str
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}
</script>