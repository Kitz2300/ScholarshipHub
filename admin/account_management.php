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

// ─── ADD STAFF ───
if(isset($_POST['add_staff'])) {
    $username = trim($_POST['username']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = $_POST['role'] ?? 'staff';
    $given_name = trim($_POST['given_name'] ?? '');
    $middle_name = trim($_POST['middle_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    
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
            $sql = "INSERT INTO scholarship_users (username, password, role, given_name, middle_name, last_name, permissions) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssssss", $username, $password, $role, $given_name, $middle_name, $last_name, $permissions_json);
            if($stmt->execute()) {
                $_SESSION['success'] = "Staff account created successfully.";
            } else {
                $_SESSION['fail'] = "Failed to create staff account.";
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

    // Get submitted permissions (only checked checkboxes are sent)
    $submitted_perms = isset($_POST['permissions']) ? $_POST['permissions'] : [];

    // Build final permissions: for each page, use submitted array if present, else empty
    $final_perms = [];
    foreach(getPageDefinitions() as $page_key => $page_label) {
        if (isset($submitted_perms[$page_key])) {
            // Page was submitted – use the array of checked values (may be empty)
            $final_perms[$page_key] = array_values(array_unique($submitted_perms[$page_key]));
        } else {
            // Page was not submitted – remove all permissions (set to empty)
            $final_perms[$page_key] = [];
        }
    }

    $permissions_json = json_encode($final_perms);

    if(!empty($username) && $user_id > 0) {
        $sql = "UPDATE scholarship_users SET username = ?, role = ?, given_name = ?, middle_name = ?, last_name = ?, permissions = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssssi", $username, $role, $given_name, $middle_name, $last_name, $permissions_json, $user_id);
        if($stmt->execute()) {
            $_SESSION['success'] = "Staff account updated successfully.";
        } else {
            $_SESSION['fail'] = "Failed to update staff account: " . $conn->error;
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
        $hashed = password_hash($new_password, PASSWORD_DEFAULT);
        $sql = "UPDATE scholarship_users SET password = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $hashed, $user_id);
        if($stmt->execute()) {
            $_SESSION['success'] = "Password updated successfully.";
        } else {
            $_SESSION['fail'] = "Failed to update password.";
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
        $sql = "DELETE FROM scholarship_users WHERE id = ? AND role = 'staff'";
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

// ─── FETCH STAFF USERS ───
$staff_sql = "SELECT id, username, role, given_name, middle_name, last_name, permissions FROM scholarship_users WHERE role = 'staff' ORDER BY last_name ASC, given_name ASC";
$staff_result = $conn->query($staff_sql);

if(isset($_SESSION['success'])) {
    echo "<script>alert('" . $_SESSION['success'] . "')</script>";
    unset($_SESSION['success']);
} else if(isset($_SESSION['fail'])) {
    echo "<script>alert('" . $_SESSION['fail'] . "')</script>";
    unset($_SESSION['fail']);
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Account Management - PIT ScholarshipHub</title>
    <link rel="stylesheet" href="../assets/css/account_management.css?v=<?= filemtime('../assets/css/account_management.css') ?>">
    <link rel="icon" href="../assets/images/PIT%20LOGO.png">
</head>
<body>
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

        <!-- Navigation -->
        <nav class="nav-bar">
            <ul>
                <li><a href="dashboard.php">Dashboard</a></li>
                <li class="dropdown">
                    <a href="#" class="dropdown-toggle">Records <img src="../assets/images/arrow-down-s-line.svg" alt="▼"></a>
                    <ul class="dropdown-menu">
                        <li><a href="scholar_list.php">Scholars</a></li>
                        <li><a href="scholarship_program.php">Scholarship Programs</a></li>
                        <li><a href="archive.php">Archives</a></li>
                    </ul>
                </li>
                <li class="dropdown">
                    <a href="#" class="dropdown-toggle">Import / Export <img src="../assets/images/arrow-down-s-line.svg" alt="▼"></a>
                    <ul class="dropdown-menu">
                        <li><a href="upload_excel.php">Import Excel</a></li>
                        <li><a href="export_scholars.php">Export Excel</a></li>
                    </ul>
                </li>
                <li><a href="announcements.php">Announcements</a></li>
                <li><a href="program_management.php">Program & College Management</a></li>
                <?php if($_SESSION['role'] == "admin") : ?>
                    <li><a href="account_management.php" class="active">Account Management</a></li>
                <?php endif; ?>
            </ul>
        </nav>

        <!-- Page Content -->
        <div class="page-content">
            <div class="page-header">
                <h2>Account Management</h2>
            </div>

            <div class="section-card">
                <div class="section-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; border-bottom: 2px solid #2a6b37; padding-bottom: 10px;">
                    <h3 style="margin: 0; border: none; padding: 0;">Staff Accounts</h3>
                    <button type="button" class="btn btn-success" onclick="openAddModal()">Add Staff</button>
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
                                    $perm_summary = [];
                                    $pages = getPageDefinitions();
                                    foreach($pages as $page_key => $page_label) {
                                        if(isset($perms[$page_key]) && !empty($perms[$page_key])) {
                                            $perm_summary[] = $page_label . ': ' . implode(', ', $perms[$page_key]);
                                        }
                                    }
                                    $perm_display = !empty($perm_summary) ? implode('; ', $perm_summary) : 'None';
                                ?>
                                    <tr>
                                        <td><?= htmlspecialchars($staff['last_name']) ?>, <?= htmlspecialchars($staff['given_name']) ?> <?= htmlspecialchars($staff['middle_name']) ?></td>
                                        <td><?= htmlspecialchars($staff['username']) ?></td>
                                        <td><?= htmlspecialchars(ucfirst($staff['role'])) ?></td>
                                        <td class="perm-summary" title="<?= htmlspecialchars($perm_display) ?>">
                                            <?= htmlspecialchars(strlen($perm_display) > 50 ? substr($perm_display, 0, 50) . '...' : $perm_display) ?>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <button class="btn-sm btn-edit" onclick="openEditModal(<?= $staff['id'] ?>, '<?= htmlspecialchars($staff['username']) ?>', '<?= htmlspecialchars($staff['role']) ?>', '<?= htmlspecialchars($staff['given_name']) ?>', '<?= htmlspecialchars($staff['middle_name']) ?>', '<?= htmlspecialchars($staff['last_name']) ?>', '<?= htmlspecialchars($staff['permissions']) ?>')">Edit</button>
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

    <!-- ========== ADD STAFF MODAL ========== -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <h3>Add New Staff Account</h3>
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
                    <label>Username <span class="required">*</span></label>
                    <input type="text" name="username" required>
                </div>
                <div class="form-group">
                    <label>Password <span class="required">*</span></label>
                    <input type="password" name="password" required>
                </div>

                <div class="form-group">
                    <label>Page Permissions</label>
                    <div class="permissions-grid">
                        <?php renderPermissionsTable([]); ?>
                    </div>
                    <small class="help-text">Check <strong>Allow Access</strong> to grant page access, then select specific actions.</small>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-success">Add Staff</button>
                    <button type="button" onclick="closeModal()" class="btn btn-secondary">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ========== EDIT MODAL ========== -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <h3>Edit Staff Account</h3>
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
                <div class="form-group">
                    <label>Role</label>
                    <select name="role" id="edit_role">
                        <option value="staff">Staff</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Page Permissions</label>
                    <div class="permissions-grid" id="edit_permissions_container">
                        <?php renderPermissionsTable([]); ?>
                    </div>
                    <small class="help-text">Check <strong>Allow Access</strong> to grant page access, then select specific actions.</small>
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
        }

        function openEditModal(id, username, role, given_name, middle_name, last_name, permissions_json) {
            document.getElementById('edit_user_id').value = id;
            document.getElementById('edit_username').value = username;
            document.getElementById('edit_given_name').value = given_name;
            document.getElementById('edit_middle_name').value = middle_name || '';
            document.getElementById('edit_last_name').value = last_name;
            document.getElementById('edit_role').value = role;

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

        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.view-checkbox').forEach(cb => {
                togglePagePermissions(cb, cb.dataset.page);
            });
        });
    </script>
</body>
</html>