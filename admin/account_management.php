<?php
include '../includes/conn.php';
include '../includes/check_permission.php';
session_start();

// ─── ADMIN ONLY ───
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['fail'] = "You are not allowed to access this page.";
    header("Location: dashboard.php");
    exit();
}

// ─── ADD ACCOUNT (Staff or Admin) ───
if(isset($_POST['add_staff'])) {
    $username = trim($_POST['username']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = $_POST['role'] ?? 'staff';
    $given_name = trim($_POST['given_name'] ?? '');
    $middle_name = trim($_POST['middle_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    
    // Only capture email for Admin accounts
    $email = ($role === 'admin') ? trim($_POST['email'] ?? '') : null;
    
    $permissions = isset($_POST['permissions']) ? $_POST['permissions'] : [];
    $permissions_json = json_encode($permissions);

    if(!empty($username) && !empty($_POST['password'])) {
        $check_sql = "SELECT id FROM scholarship_users WHERE username = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("s", $username);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if($check_result->num_rows > 0) {
            $_SESSION['fail'] = "Username already exists.";
        } else {
            $sql = "INSERT INTO scholarship_users (username, password, role, given_name, middle_name, last_name, email, permissions) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssssss", $username, $password, $role, $given_name, $middle_name, $last_name, $email, $permissions_json);
            if($stmt->execute()) {
                $_SESSION['success'] = "Account created successfully.";
            } else {
                $_SESSION['fail'] = "Failed to create account.";
            }
        }
    } else {
        $_SESSION['fail'] = "Username and password are required.";
    }
    header("Location: account_management.php");
    exit();
}

// ─── EDIT STAFF ───
if(isset($_POST['edit_staff'])) {
    $user_id = (int) $_POST['user_id'];
    $username = trim($_POST['username']);
    $role = $_POST['role'] ?? 'staff';
    $given_name = trim($_POST['given_name'] ?? '');
    $middle_name = trim($_POST['middle_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    
    // Only update email for Admin accounts
    $email = ($role === 'admin') ? trim($_POST['email'] ?? '') : null;

    $submitted_perms = isset($_POST['permissions']) ? $_POST['permissions'] : [];

    $final_perms = [];
    foreach(getPageDefinitions() as $page_key => $page_label) {
        if (isset($submitted_perms[$page_key])) {
            $final_perms[$page_key] = array_values(array_unique($submitted_perms[$page_key]));
        } else {
            $final_perms[$page_key] = [];
        }
    }

    $permissions_json = json_encode($final_perms);

    if(!empty($username) && $user_id > 0) {
        $sql = "UPDATE scholarship_users SET username = ?, role = ?, given_name = ?, middle_name = ?, last_name = ?, email = ?, permissions = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssssi", $username, $role, $given_name, $middle_name, $last_name, $email, $permissions_json, $user_id);
        if($stmt->execute()) {
            $_SESSION['success'] = "Account updated successfully.";
        } else {
            $_SESSION['fail'] = "Failed to update account: " . $conn->error;
        }
    } else {
        $_SESSION['fail'] = "Username is required.";
    }
    header("Location: account_management.php");
    exit();
}

// ─── CHANGE PASSWORD ───
if(isset($_POST['change_password'])) {
    $user_id = (int) $_POST['user_id'];
    $new_password = $_POST['new_password'] ?? '';

    if($user_id > 0 && !empty($new_password)) {
        // Enforce restriction: logged-in admin cannot change other admin passwords
        $check_stmt = $conn->prepare("SELECT role FROM scholarship_users WHERE id = ?");
        $check_stmt->bind_param("i", $user_id);
        $check_stmt->execute();
        $target_user = $check_stmt->get_result()->fetch_assoc();
        $check_stmt->close();

        if ($target_user && $target_user['role'] === 'admin' && $user_id !== (int)$_SESSION['user_id']) {
            $_SESSION['fail'] = "You cannot change the password of another admin account.";
        } else {
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $sql = "UPDATE scholarship_users SET password = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("si", $hashed, $user_id);
            if($stmt->execute()) {
                $_SESSION['success'] = "Password updated successfully.";
            } else {
                $_SESSION['fail'] = "Failed to update password.";
            }
            $stmt->close();
        }
    } else {
        $_SESSION['fail'] = "New password is required.";
    }
    header("Location: account_management.php");
    exit();
}

// ─── DELETE STAFF ───
if(isset($_POST['delete_staff'])) {
    $user_id = (int) $_POST['user_id'];
    if($user_id > 0 && $user_id != $_SESSION['user_id']) {
        $sql = "DELETE FROM scholarship_users WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        if($stmt->execute()) {
            $_SESSION['success'] = "Staff account deleted successfully.";
        } else {
            $_SESSION['fail'] = "Failed to delete staff account.";
        }
    } else {
        $_SESSION['fail'] = "Cannot delete your own account or invalid ID.";
    }
    header("Location: account_management.php");
    exit();
}

// ─── FETCH ADMIN USERS ───
$admin_sql = "SELECT id, username, role, given_name, middle_name, last_name, email, permissions FROM scholarship_users WHERE role = 'admin' ORDER BY last_name ASC, given_name ASC";
$admin_result = $conn->query($admin_sql);

// ─── FETCH STAFF USERS ───
$staff_sql = "SELECT id, username, role, given_name, middle_name, last_name, permissions FROM scholarship_users WHERE role = 'staff' ORDER BY last_name ASC, given_name ASC";
$staff_result = $conn->query($staff_sql);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Account Management - PIT ScholarshipHub</title>
    <link rel="stylesheet" href="../assets/css/account_management.css?v=<?= filemtime('../assets/css/account_management.css') ?>">
    <link rel="icon" href="../assets/images/PIT%20LOGO.png">
</head>
<body>
    <?php 
    if(isset($_SESSION['success'])) {
        echo "<script>alert('" . $_SESSION['success'] . "')</script>";
        unset($_SESSION['success']);
    } else if(isset($_SESSION['fail'])) {
        echo "<script>alert('" . $_SESSION['fail'] . "')</script>";
        unset($_SESSION['fail']);
    }
    ?>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="header-left">
                <img src="../assets/images/PIT%20LOGO.png" alt="PIT Logo">
                <div>
                    <h1>PIT ScholarshipHub</h1>
                    <p>Account Management</p>
                </div>
            </div>
            <div class="header-right">
                <span class="welcome-text">Welcome, Admin!</span>
                <form action="../functions/logout.php" method="post">
                    <button type="submit" class="logout-btn" onclick="return confirm('Logout?')">Logout</button>
                </form>
            </div>
        </div>

        <?php include __DIR__ . "/../includes/navigation.php"; ?>

        <!-- Page Content -->
        <div class="page-content">
            <!-- Page Header with inline Add button -->
            <div class="page-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap;">
                <h2 style="margin: 0;">Account Management</h2>
                <button type="button" class="btn btn-success" onclick="openAddModal()">Add Account</button>
            </div>

            <!-- ========== ADMIN ACCOUNTS ========== -->
            <div class="section-card">
                <div class="section-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; border-bottom: 2px solid #2a6b37; padding-bottom: 10px;">
                    <h3 style="margin: 0; border: none; padding: 0;">Admin Accounts</h3>
                </div>

                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Permissions</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($admin_result->num_rows > 0) : ?>
                                <?php while($admin = $admin_result->fetch_assoc()) : 
                                    $is_current_user = ($admin['id'] == $_SESSION['user_id']);
                                ?>
                                    <tr>
                                        <td><?= htmlspecialchars($admin['last_name']) ?>, <?= htmlspecialchars($admin['given_name']) ?> <?= htmlspecialchars($admin['middle_name']) ?></td>
                                        <td><?= htmlspecialchars($admin['username']) ?></td>
                                        <td><?= htmlspecialchars($admin['email'] ?? 'N/A') ?></td>
                                        <td><?= htmlspecialchars(ucfirst($admin['role'])) ?></td>
                                        <td class="perm-summary" style="color: #2a6b37; font-weight: bold;">
                                            Full Access
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <button class="btn-sm btn-edit" onclick="openEditModal(<?= $admin['id'] ?>, '<?= htmlspecialchars($admin['username']) ?>', '<?= htmlspecialchars($admin['role']) ?>', '<?= htmlspecialchars($admin['given_name']) ?>', '<?= htmlspecialchars($admin['middle_name']) ?>', '<?= htmlspecialchars($admin['last_name']) ?>', '<?= htmlspecialchars($admin['email'] ?? '') ?>', '<?= htmlspecialchars($admin['permissions']) ?>', true)">Edit</button>
                                                <?php if($is_current_user) : ?>
                                                    <button class="btn-sm btn-warning" onclick="openPasswordModal(<?= $admin['id'] ?>)">Change Password</button>
                                                <?php endif; ?>
                                                <?php if(!$is_current_user) : ?>
                                                    <form action="" method="POST" style="display: inline;">
                                                        <input type="hidden" name="user_id" value="<?= $admin['id'] ?>">
                                                        <button type="submit" name="delete_staff" class="btn-sm btn-delete" onclick="return confirm('Delete this admin account?')">Delete</button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else : ?>
                                <tr><td colspan="6" class="no-data">No admin accounts found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ========== STAFF ACCOUNTS ========== -->
            <div class="section-card">
                <div class="section-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; border-bottom: 2px solid #2a6b37; padding-bottom: 10px;">
                    <h3 style="margin: 0; border: none; padding: 0;">Staff Accounts</h3>
                </div>

                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Username</th>
                                <th>Role</th>
                                <th>Permissions</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($staff_result->num_rows > 0) : ?>
                                <?php while($staff = $staff_result->fetch_assoc()) : 
                                    $perms = json_decode($staff['permissions'], true) ?: [];
                                ?>
                                    <tr>
                                        <td><?= htmlspecialchars($staff['last_name']) ?>, <?= htmlspecialchars($staff['given_name']) ?> <?= htmlspecialchars($staff['middle_name']) ?></td>
                                        <td><?= htmlspecialchars($staff['username']) ?></td>
                                        <td><?= htmlspecialchars(ucfirst($staff['role'])) ?></td>
                                        <td class="perm-cell">
                                            <div class="perm-list">
                                                <?php 
                                                $has_perm = false;
                                                foreach (getPageDefinitions() as $page_key => $page_label): 
                                                    if (!empty($perms[$page_key])): 
                                                        $has_perm = true;
                                                ?>
                                                    <div class="perm-row">
                                                        <span class="perm-page"><?= htmlspecialchars($page_label) ?></span>
                                                        <div class="perm-badges">
                                                            <?php foreach ($perms[$page_key] as $act): ?>
                                                                <span class="badge badge-<?= strtolower($act) ?>"><?= ucfirst($act) ?></span>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    </div>
                                                <?php 
                                                    endif; 
                                                endforeach; 
                                                if (!$has_perm): ?>
                                                    <span class="badge badge-none">No Access</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <button class="btn-sm btn-edit" onclick="openEditModal(<?= $staff['id'] ?>, '<?= htmlspecialchars($staff['username']) ?>', '<?= htmlspecialchars($staff['role']) ?>', '<?= htmlspecialchars($staff['given_name']) ?>', '<?= htmlspecialchars($staff['middle_name']) ?>', '<?= htmlspecialchars($staff['last_name']) ?>', '', '<?= htmlspecialchars($staff['permissions']) ?>', false)">Edit</button>
                                                <button class="btn-sm btn-warning" onclick="openPasswordModal(<?= $staff['id'] ?>)">Change Password</button>
                                                <form action="" method="POST" style="display: inline;">
                                                    <input type="hidden" name="user_id" value="<?= $staff['id'] ?>">
                                                    <button type="submit" name="delete_staff" class="btn-sm btn-delete" onclick="return confirm('Delete this staff account?')">Delete</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else : ?>
                                <tr><td colspan="5" class="no-data">No staff accounts found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            © 2026 PIT ScholarshipHub. All rights reserved.
        </div>
    </div>

    <!-- ========== ADD ACCOUNT MODAL ========== -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <h3>Add New Account</h3>
            <form action="" method="POST">
                <input type="hidden" name="add_staff" value="1">
                <div class="form-group">
                    <label>First Name <span class="required">*</span></label>
                    <input type="text" name="given_name" required>
                </div>
                <div class="form-group">
                    <label>Middle Name</label>
                    <input type="text" name="middle_name">
                </div>
                <div class="form-group">
                    <label>Last Name <span class="required">*</span></label>
                    <input type="text" name="last_name" required>
                </div>
                <div class="form-group">
                    <label>Role <span class="required">*</span></label>
                    <select name="role" id="add_role" required>
                        <option value="staff">Staff</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <!-- Admin Only Email Field -->
                <div class="form-group" id="addEmailGroup" style="display: none;">
                    <label>Email Address <span class="required">*</span></label>
                    <input type="email" name="email" id="add_email" placeholder="example@pit.edu.ph">
                    <small class="help-text">Required for Admin OTP account recovery.</small>
                </div>
                <div class="form-group">
                    <label>Username <span class="required">*</span></label>
                    <input type="text" name="username" required>
                </div>
                <div class="form-group">
                    <label>Password <span class="required">*</span></label>
                    <input type="password" name="password" required>
                </div>

                <!-- Permissions Section (hidden for admin role) -->
                <div class="form-group" id="addPermissionsSection">
                    <label>Page Permissions</label>
                    <div class="permissions-grid">
                        <?php renderPermissionsTable([]); ?>
                    </div>
                    <small class="help-text">Check <strong>Allow Access</strong> to grant page access, then select specific actions. (Staff only)</small>
                </div>

                <!-- Message for admin role -->
                <div class="form-group" id="addAdminMessage" style="display: none;">
                    <div class="info-message" style="background: #d4edda; padding: 12px 15px; border-radius: 4px; border: 1px solid #c3e6cb; color: #155724;">
                        <strong>Full Access:</strong> Administrators have permission to access all pages and perform all actions. No permission settings are required.
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-success">Add Account</button>
                    <button type="button" onclick="closeModal()" class="btn btn-secondary">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ========== EDIT MODAL ========== -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <h3 id="editModalTitle">Edit Account</h3>
            <form action="" method="POST">
                <input type="hidden" name="user_id" id="edit_user_id">
                <div class="form-group">
                    <label>Username <span class="required">*</span></label>
                    <input type="text" name="username" id="edit_username" required>
                </div>
                <div class="form-group">
                    <label>First Name <span class="required">*</span></label>
                    <input type="text" name="given_name" id="edit_given_name" required>
                </div>
                <div class="form-group">
                    <label>Middle Name</label>
                    <input type="text" name="middle_name" id="edit_middle_name">
                </div>
                <div class="form-group">
                    <label>Last Name <span class="required">*</span></label>
                    <input type="text" name="last_name" id="edit_last_name" required>
                </div>
                <!-- Admin Only Email Field -->
                <div class="form-group" id="editEmailGroup" style="display: none;">
                    <label>Email Address <span class="required">*</span></label>
                    <input type="email" name="email" id="edit_email" placeholder="example@pit.edu.ph">
                    <small class="help-text">Used for Admin OTP account recovery.</small>
                </div>
                <div class="form-group">
                    <label>Role</label>
                    <select name="role" id="edit_role">
                        <option value="staff">Staff</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>

                <!-- Permissions Section (hidden for admins) -->
                <div class="form-group" id="editPermissionsSection">
                    <label>Page Permissions</label>
                    <div class="permissions-grid" id="edit_permissions_container">
                        <?php renderPermissionsTable([]); ?>
                    </div>
                    <small class="help-text">Check <strong>Allow Access</strong> to grant page access, then select specific actions. (Staff only)</small>
                </div>

                <!-- Message for admin users -->
                <div class="form-group" id="editAdminMessage" style="display: none;">
                    <div class="info-message" style="background: #d4edda; padding: 12px 15px; border-radius: 4px; border: 1px solid #c3e6cb; color: #155724;">
                        <strong>Full Access:</strong> Administrators have permission to access all pages and perform all actions. No permission settings are required.
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" name="edit_staff" class="btn btn-success">Update</button>
                    <button type="button" onclick="closeModal()" class="btn btn-secondary">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ========== CHANGE PASSWORD MODAL ========== -->
    <div id="passwordModal" class="modal">
        <div class="modal-content">
            <h3>Change Password</h3>
            <form action="" method="POST">
                <input type="hidden" name="user_id" id="password_user_id">
                <div class="form-group">
                    <label>New Password <span class="required">*</span></label>
                    <input type="password" name="new_password" id="new_password" required>
                </div>
                <div class="form-group">
                    <label>Confirm Password <span class="required">*</span></label>
                    <input type="password" id="confirm_password" onkeyup="checkPasswordMatch()" required>
                    <small id="password_match_msg"></small>
                </div>
                <div class="form-actions">
                    <button type="submit" name="change_password" class="btn btn-success">Update Password</button>
                    <button type="button" onclick="closeModal()" class="btn btn-secondary">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // ─── MODAL HELPERS ───
        function openAddModal() {
            document.getElementById('addModal').style.display = 'flex';
            document.querySelector('#addModal form').reset();
            document.querySelectorAll('#addModal .view-checkbox').forEach(cb => {
                cb.checked = true;
                togglePagePermissions(cb, cb.dataset.page);
            });
            toggleAddPermissions();
        }

        function openEditModal(id, username, role, given_name, middle_name, last_name, email, permissions_json, isAdmin) {
            document.getElementById('edit_user_id').value = id;
            document.getElementById('edit_username').value = username;
            document.getElementById('edit_given_name').value = given_name;
            document.getElementById('edit_middle_name').value = middle_name || '';
            document.getElementById('edit_last_name').value = last_name;
            document.getElementById('edit_email').value = email || '';
            document.getElementById('edit_role').value = role;

            const permissionsSection = document.getElementById('editPermissionsSection');
            const adminMessage = document.getElementById('editAdminMessage');
            const modalTitle = document.getElementById('editModalTitle');
            const editEmailGroup = document.getElementById('editEmailGroup');

            if (isAdmin) {
                permissionsSection.style.display = 'none';
                adminMessage.style.display = 'block';
                editEmailGroup.style.display = 'block';
                modalTitle.textContent = 'Edit Admin Account';
            } else {
                permissionsSection.style.display = 'block';
                adminMessage.style.display = 'none';
                editEmailGroup.style.display = 'none';
                modalTitle.textContent = 'Edit Staff Account';

                try {
                    const perms = JSON.parse(permissions_json);
                    document.querySelectorAll('#editModal .view-checkbox').forEach(cb => {
                        const page = cb.dataset.page;
                        cb.checked = perms[page] && perms[page].includes('view');
                        togglePagePermissions(cb, page);
                    });
                    document.querySelectorAll('#editModal .perm-checkbox').forEach(cb => {
                        const page = cb.dataset.page;
                        const action = cb.dataset.action;
                        cb.checked = perms[page] && perms[page].includes(action);
                    });
                } catch(e) {
                    document.querySelectorAll('#editModal input[type="checkbox"]').forEach(cb => cb.checked = false);
                }
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

        // ─── PASSWORD MATCH ───
        function checkPasswordMatch() {
            const pass = document.getElementById('new_password').value;
            const confirm = document.getElementById('confirm_password').value;
            const msg = document.getElementById('password_match_msg');
            if (pass === confirm && pass !== '') {
                msg.textContent = '✅ Passwords match.';
                msg.style.color = 'green';
            } else if (pass !== '' && confirm !== '') {
                msg.textContent = '❌ Passwords do not match.';
                msg.style.color = 'red';
            } else {
                msg.textContent = '';
            }
        }

        // ─── TOGGLE PERMISSIONS ───
        function togglePagePermissions(viewCheckbox, pageKey) {
            const container = viewCheckbox.closest('tr');
            const actionCheckboxes = container.querySelectorAll('.perm-checkbox');
            if (!viewCheckbox.checked) {
                actionCheckboxes.forEach(cb => {
                    cb.checked = false;
                    cb.disabled = true;
                });
            } else {
                actionCheckboxes.forEach(cb => {
                    cb.disabled = false;
                });
            }
        }

        // ─── TOGGLE ADD PERMISSIONS BASED ON ROLE ───
        function toggleAddPermissions() {
            const role = document.getElementById('add_role').value;
            const permissionsSection = document.getElementById('addPermissionsSection');
            const adminMessage = document.getElementById('addAdminMessage');
            const addEmailGroup = document.getElementById('addEmailGroup');

            if (role === 'admin') {
                permissionsSection.style.display = 'none';
                adminMessage.style.display = 'block';
                addEmailGroup.style.display = 'block';
            } else {
                permissionsSection.style.display = 'block';
                adminMessage.style.display = 'none';
                addEmailGroup.style.display = 'none';
                document.getElementById('add_email').value = '';
                document.querySelectorAll('#addModal .perm-checkbox').forEach(cb => {
                    cb.disabled = false;
                });
            }
        }

        // ─── EVENT LISTENERS ───
        document.addEventListener('DOMContentLoaded', function() {
            const roleSelect = document.getElementById('add_role');
            if (roleSelect) {
                roleSelect.addEventListener('change', toggleAddPermissions);
            }

            document.querySelectorAll('.view-checkbox').forEach(cb => {
                togglePagePermissions(cb, cb.dataset.page);
            });
        });

        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
    </script>
</body>
</html>