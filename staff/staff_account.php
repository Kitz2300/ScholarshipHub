<?php
include '../includes/conn.php';
include '../includes/check_permission.php';
session_start();

// ─── AUTHENTICATION CHECK ───
if (!isset($_SESSION['user_id'])) {
    $_SESSION['fail'] = "Please log in to access your account.";
    header("Location: ../index.php");
    exit();
}

$user_id = (int) $_SESSION['user_id'];

// ─── HANDLE CHANGE PASSWORD ───
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (!empty($current_password) && !empty($new_password) && !empty($confirm_password)) {
        if ($new_password !== $confirm_password) {
            $_SESSION['fail'] = "New password and confirmation password do not match.";
        } else {
            $pwd_stmt = $conn->prepare("SELECT password FROM scholarship_users WHERE id = ?");
            $pwd_stmt->bind_param("i", $user_id);
            $pwd_stmt->execute();
            $user_data = $pwd_stmt->get_result()->fetch_assoc();
            $pwd_stmt->close();

            if ($user_data && password_verify($current_password, $user_data['password'])) {
                $new_hashed = password_hash($new_password, PASSWORD_DEFAULT);
                $update_stmt = $conn->prepare("UPDATE scholarship_users SET password = ? WHERE id = ?");
                $update_stmt->bind_param("si", $new_hashed, $user_id);
                
                if ($update_stmt->execute()) {
                    $_SESSION['success'] = "Password updated successfully.";
                } else {
                    $_SESSION['fail'] = "Failed to update password. Please try again.";
                }
                $update_stmt->close();
            } else {
                $_SESSION['fail'] = "Incorrect current password.";
            }
        }
    } else {
        $_SESSION['fail'] = "All password fields are required.";
    }

    header("Location: staff_account.php");
    exit();
}

// ─── FETCH STAFF DETAILS ───
$user_sql = "SELECT id, username, role, given_name, middle_name, last_name, permissions, profile_picture FROM scholarship_users WHERE id = ?";
$stmt = $conn->prepare($user_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$staff = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$staff) {
    $_SESSION['fail'] = "User account not found.";
    header("Location: ../index.php");
    exit();
}

$permissions = json_decode($staff['permissions'] ?? '{}', true) ?: [];

function getStaffAvatarPath($user_row) {
    $profile_pic = trim($user_row['profile_picture'] ?? '');
    $given_name = trim($user_row['given_name'] ?? '');
    $last_name = trim($user_row['last_name'] ?? '');

    $pic_path = '';
    $has_picture = false;

    if (!empty($profile_pic) && preg_match("~^https?://~i", $profile_pic)) {
        $pic_path = $profile_pic;
        $has_picture = true;
    } elseif (!empty($profile_pic) && file_exists(__DIR__ . '/../assets/images/profile_picture/' . $profile_pic)) {
        $pic_path = '../assets/images/profile_picture/' . $profile_pic . '?v=' . time();
        $has_picture = true;
    } elseif (!empty($profile_pic) && file_exists(__DIR__ . '/../uploads/profile_pics/' . $profile_pic)) {
        $pic_path = '../uploads/profile_pics/' . $profile_pic . '?v=' . time();
        $has_picture = true;
    }

    $initials = strtoupper(substr($given_name, 0, 1) . substr($last_name, 0, 1));
    if (empty($initials)) {
        $initials = 'S';
    }

    return [
        'has_picture' => $has_picture,
        'pic_path' => $pic_path,
        'initials' => $initials
    ];
}

$avatar = getStaffAvatarPath($staff);
$page_subtitle = "Staff Account Profile";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Account - PIT ScholarshipHub</title>
    <link rel="icon" href="../assets/images/PIT%20LOGO.png">
    <link rel="stylesheet" href="../assets/css/account_management.css?v=<?= filemtime('../assets/css/account_management.css') ?>">
    <link rel="stylesheet" href="../assets/css/staff_account.css?v=<?= filemtime('../assets/css/staff_account.css') ?>">
    <link rel="stylesheet" href="../assets/css/modals.css">
</head>
<body>
    <!-- Alert Modals -->
    <?php include __DIR__ . '/../includes/modal_alerts.php'; ?>

    <div class="container">
        <!-- Global Header Navigation -->
        <?php include __DIR__ . '/../includes/navigation.php'; ?>

        <div class="page-content">
            <div class="page-header">
                <h2>My Account Overview</h2>
            </div>

            <div class="staff-account-container">
                <!-- Profile Banner Card -->
                <div class="staff-profile-card">
                    <div class="staff-profile-main">
                        <div class="staff-avatar-box">
                            <?php if ($avatar['has_picture']) : ?>
                                <img src="<?= htmlspecialchars($avatar['pic_path']) ?>" alt="Staff Avatar" class="staff-avatar-img">
                            <?php else : ?>
                                <div class="staff-avatar-initials">
                                    <?= htmlspecialchars($avatar['initials']) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="staff-info-details">
                            <h2><?= htmlspecialchars($staff['given_name'] . ' ' . ($staff['middle_name'] ? $staff['middle_name'] . ' ' : '') . $staff['last_name']) ?></h2>
                            <div class="staff-role-badge-box">
                                <span class="badge badge-role">Role: <?= htmlspecialchars(ucfirst($staff['role'])) ?></span>
                            </div>
                            <div class="staff-meta-row">
                                <span><strong>Username:</strong> <?= htmlspecialchars($staff['username']) ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="staff-profile-actions">
                        <button type="button" class="btn btn-success" onclick="openEditProfileModal()">Edit Profile</button>
                        <button type="button" class="btn btn-warning" onclick="openStaffPasswordModal()">Change Password</button>
                    </div>
                </div>

                <!-- Assigned Page Permissions Overview -->
                <div class="perm-grid-card">
                    <h3>My System Permissions</h3>
                    
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>Module / Page</th>
                                    <th>Granted Access Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $page_defs = function_exists('getPageDefinitions') ? getPageDefinitions() : [
                                    'scholars' => 'Scholar List',
                                    'scholarship_programs' => 'Scholarship Programs',
                                    'announcements' => 'Announcements',
                                    'program_management' => 'Programs & Colleges',
                                    'import_export' => 'Import/Export Records'
                                ];

                                $has_any_perm = false;
                                foreach ($page_defs as $page_key => $page_label) :
                                    if (!empty($permissions[$page_key])) :
                                        $has_any_perm = true;
                                ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars($page_label) ?></strong></td>
                                            <td>
                                                <div class="perm-badge-group">
                                                    <?php foreach ($permissions[$page_key] as $action) : ?>
                                                        <span class="badge badge-<?= strtolower($action) ?>"><?= ucfirst($action) ?></span>
                                                    <?php endforeach; ?>
                                                </div>
                                            </td>
                                        </tr>
                                <?php 
                                    endif;
                                endforeach; 

                                if (!$has_any_perm) :
                                ?>
                                    <tr>
                                        <td colspan="2" class="no-data">No specific module permissions assigned. Please contact your system administrator.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="footer">
            © 2026 PIT ScholarshipHub. All rights reserved.
        </div>
    </div>

    <!-- ========== EDIT PROFILE MODAL ========== -->
    <div id="editProfileModal" class="modal">
        <div class="modal-content">
            <h3>Edit Profile</h3>
            <form action="../functions/admin/upload_profile_pic_process.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="update_profile" value="1">

                <div class="form-group" style="text-align: center;">
                    <label>Profile Picture</label>
                    <div class="edit-avatar-preview-box">
                        <?php if ($avatar['has_picture']): ?>
                            <img id="staffAvatarPreviewImg" src="<?= htmlspecialchars($avatar['pic_path']) ?>" alt="Avatar Preview" class="edit-avatar-preview-img">
                        <?php else: ?>
                            <div id="staffAvatarInitialsPreview" class="staff-avatar-initials" style="margin: 0 auto;">
                                <?= htmlspecialchars($avatar['initials']) ?>
                            </div>
                            <img id="staffAvatarPreviewImg" src="" alt="Avatar Preview" class="edit-avatar-preview-img" style="display: none;">
                        <?php endif; ?>
                    </div>
                    <input type="file" name="profile_picture" id="staff_profile_picture_input" accept="image/jpeg,image/png,image/webp" onchange="previewStaffAvatar(this)">
                    <small class="help-text">Upload JPG, PNG, or WebP (Max 2MB).</small>
                </div>

                <div class="form-group">
                    <label>First Name <span class="required">*</span></label>
                    <input type="text" name="given_name" value="<?= htmlspecialchars($staff['given_name']) ?>" required>
                </div>
                <div class="form-group">
                    <label>Middle Name</label>
                    <input type="text" name="middle_name" value="<?= htmlspecialchars($staff['middle_name'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Last Name <span class="required">*</span></label>
                    <input type="text" name="last_name" value="<?= htmlspecialchars($staff['last_name']) ?>" required>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-success">Save Changes</button>
                    <button type="button" onclick="closeEditProfileModal()" class="btn btn-secondary">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ========== CHANGE PASSWORD MODAL ========== -->
    <div id="staffPasswordModal" class="modal">
        <div class="modal-content">
            <h3>Change Password</h3>
            <form action="" method="POST">
                <input type="hidden" name="change_password" value="1">
                
                <div class="form-group">
                    <label>Current Password <span class="required">*</span></label>
                    <input type="password" name="current_password" required>
                </div>
                <div class="form-group">
                    <label>New Password <span class="required">*</span></label>
                    <input type="password" name="new_password" id="staff_new_password" required>
                </div>
                <div class="form-group">
                    <label>Confirm New Password <span class="required">*</span></label>
                    <input type="password" name="confirm_password" id="staff_confirm_password" onkeyup="checkStaffPasswordMatch()" required>
                    <small id="staff_password_match_msg"></small>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-success">Update Password</button>
                    <button type="button" onclick="closeStaffPasswordModal()" class="btn btn-secondary">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openEditProfileModal() {
            document.getElementById('editProfileModal').style.display = 'flex';
        }

        function closeEditProfileModal() {
            document.getElementById('editProfileModal').style.display = 'none';
        }

        function previewStaffAvatar(input) {
            const imgPreview = document.getElementById('staffAvatarPreviewImg');
            const initialsPreview = document.getElementById('staffAvatarInitialsPreview');

            if (input.files && input.files[0]) {
                const file = input.files[0];
                if (file.size > 2 * 1024 * 1024) {
                    alert('File size exceeds 2MB limit.');
                    input.value = '';
                    return;
                }

                const reader = new FileReader();
                reader.onload = function(e) {
                    if (imgPreview) {
                        imgPreview.src = e.target.result;
                        imgPreview.style.display = 'inline-block';
                    }
                    if (initialsPreview) {
                        initialsPreview.style.display = 'none';
                    }
                };
                reader.readAsDataURL(file);
            }
        }

        function openStaffPasswordModal() {
            document.getElementById('staffPasswordModal').style.display = 'flex';
        }

        function closeStaffPasswordModal() {
            document.getElementById('staffPasswordModal').style.display = 'none';
        }

        function checkStaffPasswordMatch() {
            const pass = document.getElementById('staff_new_password').value;
            const confirm = document.getElementById('staff_confirm_password').value;
            const msg = document.getElementById('staff_password_match_msg');

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

        window.addEventListener('click', function(event) {
            const editModal = document.getElementById('editProfileModal');
            const pwdModal = document.getElementById('staffPasswordModal');
            if (event.target === editModal) {
                closeEditProfileModal();
            } else if (event.target === pwdModal) {
                closeStaffPasswordModal();
            }
        });
    </script>
</body>
</html>