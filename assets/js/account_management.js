// account_management.js

function openAddModal() {
    document.getElementById('addModal').style.display = 'flex';
    document.querySelector('#addModal form').reset();
    // Uncheck all checkboxes
    document.querySelectorAll('#addModal input[type="checkbox"]').forEach(cb => cb.checked = false);
}

function openEditModal(id, username, role, given_name, middle_name, last_name, permissions) {
    document.getElementById('edit_user_id').value = id;
    document.getElementById('edit_username').value = username;
    document.getElementById('edit_role').value = role;
    document.getElementById('edit_given_name').value = given_name;
    document.getElementById('edit_middle_name').value = middle_name || '';
    document.getElementById('edit_last_name').value = last_name;
    
    // Set checkboxes based on stored permissions
    const permArray = permissions ? permissions.split(',').map(p => p.trim()) : [];
    const checkboxes = document.querySelectorAll('#edit_permissions_container input[type="checkbox"]');
    checkboxes.forEach(cb => {
        cb.checked = permArray.includes(cb.value);
    });
    
    document.getElementById('editModal').style.display = 'flex';
}

function openPasswordModal(id) {
    document.getElementById('password_user_id').value = id;
    document.getElementById('new_password').value = '';
    document.getElementById('confirm_password').value = '';
    document.getElementById('password_match_msg').textContent = '';
    document.getElementById('passwordModal').style.display = 'flex';
}

function checkPasswordMatch() {
    const pass = document.getElementById('new_password').value;
    const confirm = document.getElementById('confirm_password').value;
    const msg = document.getElementById('password_match_msg');
    
    if(confirm.length === 0) {
        msg.textContent = '';
    } else if(pass === confirm) {
        msg.textContent = 'Passwords match';
        msg.style.color = 'green';
    } else {
        msg.textContent = 'Passwords do not match';
        msg.style.color = 'red';
    }
}

function closeModal() {
    document.getElementById('addModal').style.display = 'none';
    document.getElementById('editModal').style.display = 'none';
    document.getElementById('passwordModal').style.display = 'none';
}

window.onclick = function(event) {
    if (event.target === document.getElementById('addModal')) closeModal();
    if (event.target === document.getElementById('editModal')) closeModal();
    if (event.target === document.getElementById('passwordModal')) closeModal();
}

document.addEventListener('DOMContentLoaded', function() {
    // Listen for clicks on any checkbox inside permission containers
    document.addEventListener('change', function(e) {
        if (e.target.matches('#perm_all')) {
            const container = e.target.closest('.checkbox-group');
            const permCheckboxes = container.querySelectorAll('.perm');
            const isChecked = e.target.checked;
            permCheckboxes.forEach(function(cb) {
                cb.checked = isChecked;
                cb.disabled = isChecked;
            });
        }

        if (e.target.matches('.perm')) {
            const container = e.target.closest('.checkbox-group');
            const permCheckboxes = container.querySelectorAll('.perm');
            const allCheckbox = container.querySelector('#perm_all');
            const allChecked = Array.from(permCheckboxes).every(function(cb) {
                return cb.checked;
            });
            if (allCheckbox) {
                allCheckbox.checked = allChecked;
            }
        }
    });
});