// account_management.js

// ─── MODAL HELPERS ───
function openAddModal() {
    document.getElementById('addModal').style.display = 'flex';
    document.querySelector('#addModal form').reset();
    // Reset checkboxes to default: only 'view' checked on all pages
    document.querySelectorAll('#addModal .view-checkbox').forEach(cb => {
        cb.checked = true;
        togglePagePermissions(cb, cb.dataset.page);
    });
}

function openEditModal(id, username, role, given_name, middle_name, last_name, permissions_json) {
    document.getElementById('edit_user_id').value = id;
    document.getElementById('edit_username').value = username;
    document.getElementById('edit_given_name').value = given_name;
    document.getElementById('edit_middle_name').value = middle_name || '';
    document.getElementById('edit_last_name').value = last_name;
    document.getElementById('edit_role').value = role;

    // Parse permissions JSON and check checkboxes
    try {
        const perms = JSON.parse(permissions_json);
        // First set all view checkboxes
        document.querySelectorAll('#editModal .view-checkbox').forEach(cb => {
            const page = cb.dataset.page;
            cb.checked = perms[page] && perms[page].includes('view');
            togglePagePermissions(cb, page);
        });
        // Then set action checkboxes
        document.querySelectorAll('#editModal .perm-checkbox').forEach(cb => {
            const page = cb.dataset.page;
            const action = cb.dataset.action;
            cb.checked = perms[page] && perms[page].includes(action);
        });
    } catch(e) {
        // If parsing fails, uncheck all
        document.querySelectorAll('#editModal input[type="checkbox"]').forEach(cb => cb.checked = false);
    }

    document.getElementById('editModal').style.display = 'flex';
}

function openPasswordModal(id) {
    document.getElementById('password_user_id').value = id;
    document.getElementById('new_password').value = '';
    document.getElementById('confirm_password').value = '';
    document.getElementById('password_match_msg').textContent = '';
    document.getElementById('passwordModal').style.display = 'flex';
}

function closeModal() {
    document.querySelectorAll('.modal').forEach(m => m.style.display = 'none');
}

// ─── PASSWORD MATCH CHECK ───
function checkPasswordMatch() {
    const pass = document.getElementById('new_password').value;
    const confirm = document.getElementById('confirm_password').value;
    const msg = document.getElementById('password_match_msg');
    const submitBtn = document.querySelector('#passwordModal button[type="submit"]');
    
    if (pass === confirm && pass !== '') {
        msg.textContent = '✅ Passwords match.';
        msg.style.color = 'green';
        if (submitBtn) submitBtn.disabled = false;
    } else if (pass !== '' && confirm !== '') {
        msg.textContent = '❌ Passwords do not match.';
        msg.style.color = 'red';
        if (submitBtn) submitBtn.disabled = true;
    } else {
        msg.textContent = '';
        if (submitBtn) submitBtn.disabled = false;
    }
}

// ─── TOGGLE PERMISSIONS WHEN VIEW IS TOGGLED ───
function togglePagePermissions(viewCheckbox, pageKey) {
    const container = viewCheckbox.closest('tr');
    const actionCheckboxes = container.querySelectorAll('.perm-checkbox');
    
    if (!viewCheckbox.checked) {
        // Uncheck all action checkboxes when View is unchecked
        actionCheckboxes.forEach(cb => {
            cb.checked = false;
            cb.disabled = true;
        });
    } else {
        // Enable action checkboxes when View is checked
        actionCheckboxes.forEach(cb => {
            cb.disabled = false;
        });
    }
}

// ─── CLOSE MODALS ON CLICK OUTSIDE ───
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.style.display = 'none';
    }
}

// ─── INITIALIZE: Disable action checkboxes if view is unchecked ───
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.view-checkbox').forEach(cb => {
        togglePagePermissions(cb, cb.dataset.page);
    });
});