<?php
// Capture session messages if present
$success_msg = $_SESSION['success'] ?? null;
$fail_msg = $_SESSION['fail'] ?? null;

unset($_SESSION['success'], $_SESSION['fail']);
?>

<!-- 1. NOTIFICATION MSG MODAL -->
<div id="sysMsgModal" class="sys-modal">
    <div class="sys-modal-content">
        <div class="sys-modal-header" id="sysMsgHeader">
            <h3 id="sysMsgTitle">Notification</h3>
            <span class="sys-modal-close" onclick="closeSysMsgModal()">&times;</span>
        </div>
        <div class="sys-modal-body">
            <p id="sysMsgBodyText"></p>
        </div>
        <div class="sys-modal-actions">
            <button type="button" class="sys-btn sys-btn-primary" onclick="closeSysMsgModal()">OK</button>
        </div>
    </div>
</div>

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

function closeSysMsgModal() {
    document.getElementById('sysMsgModal').classList.remove('sys-show');
}

function showNotification(message, isSuccess = true) {
    const modal = document.getElementById('sysMsgModal');
    const header = document.getElementById('sysMsgHeader');
    const title = document.getElementById('sysMsgTitle');
    const body = document.getElementById('sysMsgBodyText');

    title.textContent = isSuccess ? 'Success' : 'Attention';
    body.textContent = message;
    
    header.className = 'sys-modal-header ' + (isSuccess ? 'sys-header-success' : 'sys-header-danger');
    modal.classList.add('sys-show');
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