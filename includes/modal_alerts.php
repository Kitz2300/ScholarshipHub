<?php
// Capture session messages if present
$success_msg = $_SESSION['success'] ?? null;
$fail_msg = $_SESSION['fail'] ?? null;

unset($_SESSION['success'], $_SESSION['fail']);
?>

<!-- 1. TOAST NOTIFICATION CONTAINER -->
<div id="toastContainer" class="toast-container" aria-live="polite" aria-atomic="true"></div>

<!-- 2. ACTION CONFIRMATION MODAL -->
<div id="sysConfirmModal" class="sys-modal">
    <div class="sys-modal-content">
        <div class="sys-modal-header" id="sysConfirmHeader">
            <h3 id="sysConfirmTitle">Confirm Action</h3>
            <span class="sys-modal-close" onclick="closeSysConfirmModal(false)">&times;</span>
        </div>
        <div class="sys-modal-body">
            <p id="sysConfirmMessage">Are you sure you want to perform this action?</p>
        </div>
        <div class="sys-modal-actions">
            <button type="button" class="sys-btn sys-btn-secondary" onclick="closeSysConfirmModal(false)">Cancel</button>
            <button type="button" id="sysConfirmBtn" class="sys-btn sys-btn-primary">Proceed</button>
        </div>
    </div>
</div>

<script>
let pendingFormSubmission = null;

// Backward compatibility helper
function closeSysMsgModal() {}

// ─── TOAST NOTIFICATION SYSTEM ───
function showToast(message, type = 'success', duration = 4500) {
    let container = document.getElementById('toastContainer');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toastContainer';
        container.className = 'toast-container';
        container.setAttribute('aria-live', 'polite');
        container.setAttribute('aria-atomic', 'true');
        document.body.appendChild(container);
    }

    const isSuccess = (type === true || type === 'success');
    let toastType = 'error';
    if (isSuccess) {
        toastType = 'success';
    } else if (type === 'warning') {
        toastType = 'warning';
    } else if (type === 'info') {
        toastType = 'info';
    }

    const toast = document.createElement('div');
    toast.className = `toast-notification toast-${toastType}`;

    const iconSvg = isSuccess 
        ? `<svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2.5" fill="none" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>`
        : `<svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2.5" fill="none" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>`;

    const title = isSuccess ? 'Success' : 'Attention';

    toast.innerHTML = `
        <div class="toast-icon">${iconSvg}</div>
        <div class="toast-content">
            <div class="toast-title">${title}</div>
            <div class="toast-message"></div>
        </div>
        <button type="button" class="toast-close" aria-label="Close">&times;</button>
        <div class="toast-progress"><div class="toast-progress-bar"></div></div>
    `;

    toast.querySelector('.toast-message').textContent = message;
    container.appendChild(toast);

    // Trigger enter animation
    requestAnimationFrame(() => {
        toast.classList.add('toast-show');
    });

    const progressBar = toast.querySelector('.toast-progress-bar');
    let startTime = Date.now();
    let remainingTime = duration;
    let dismissTimeout;

    if (progressBar) {
        progressBar.style.transition = `transform ${duration}ms linear`;
        requestAnimationFrame(() => {
            progressBar.style.transform = 'scaleX(0)';
        });
    }

    function removeCurrentToast() {
        if (!toast || toast.classList.contains('toast-hiding')) return;
        toast.classList.add('toast-hiding');
        toast.classList.remove('toast-show');
        const handleTransitionEnd = () => {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        };
        toast.addEventListener('transitionend', handleTransitionEnd, { once: true });
        setTimeout(() => {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        }, 300);
    }

    function startTimer() {
        startTime = Date.now();
        dismissTimeout = setTimeout(removeCurrentToast, remainingTime);
    }

    function pauseTimer() {
        clearTimeout(dismissTimeout);
        remainingTime -= (Date.now() - startTime);
        if (progressBar) {
            const computedStyle = window.getComputedStyle(progressBar);
            const matrix = new WebKitCSSMatrix(computedStyle.transform);
            progressBar.style.transition = 'none';
            progressBar.style.transform = `scaleX(${Math.max(0, matrix.m11)})`;
        }
    }

    function resumeTimer() {
        if (remainingTime > 0) {
            startTime = Date.now();
            if (progressBar) {
                progressBar.style.transition = `transform ${remainingTime}ms linear`;
                progressBar.style.transform = 'scaleX(0)';
            }
            dismissTimeout = setTimeout(removeCurrentToast, remainingTime);
        } else {
            removeCurrentToast();
        }
    }

    toast.addEventListener('mouseenter', pauseTimer);
    toast.addEventListener('mouseleave', resumeTimer);

    toast.querySelector('.toast-close').addEventListener('click', (e) => {
        e.stopPropagation();
        clearTimeout(dismissTimeout);
        removeCurrentToast();
    });

    startTimer();
}

// Backward-compatible alias
function showNotification(message, isSuccess = true) {
    showToast(message, isSuccess ? 'success' : 'error');
}

function confirmAction(event, message = 'Are you sure you want to continue?', confirmBtnText = 'Proceed', modalType = 'danger') {
    event.preventDefault();
    event.stopPropagation();
    if (event.stopImmediatePropagation) {
        event.stopImmediatePropagation();
    }
    
    pendingFormSubmission = event.target.closest('form');

    const modal = document.getElementById('sysConfirmModal');
    const header = document.getElementById('sysConfirmHeader');
    const body = document.getElementById('sysConfirmMessage');
    const confirmBtn = document.getElementById('sysConfirmBtn');

    body.textContent = message;
    confirmBtn.textContent = confirmBtnText;

    // Support legacy boolean argument or direct string types ('danger', 'archive', 'primary')
    let type = modalType;
    if (typeof modalType === 'boolean') {
        type = modalType ? 'danger' : 'primary';
    }

    header.className = 'sys-modal-header sys-header-' + type;
    confirmBtn.className = 'sys-btn sys-btn-' + type;

    modal.classList.add('sys-show');
    return false;
}

function closeSysConfirmModal(isConfirmed) {
    const modal = document.getElementById('sysConfirmModal');
    modal.classList.remove('sys-show');

    if (isConfirmed && pendingFormSubmission) {
        pendingFormSubmission.submit();
    }
    pendingFormSubmission = null;
}

document.getElementById('sysConfirmBtn').addEventListener('click', function() {
    closeSysConfirmModal(true);
});

document.addEventListener('DOMContentLoaded', function() {
    <?php if ($success_msg): ?>
        showNotification(<?= json_encode($success_msg) ?>, true);
    <?php elseif ($fail_msg): ?>
        showNotification(<?= json_encode($fail_msg) ?>, false);
    <?php endif; ?>
});
</script>