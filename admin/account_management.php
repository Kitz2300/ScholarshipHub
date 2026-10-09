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

$current_user_id = (int) $_SESSION['user_id'];

/**
 * Cloudinary REST API upload helper function.
 */
function uploadAdminToCloudinary($file_tmp_path, $folder = 'profile_pictures/admin') {
    $cloud_name = getenv('CLOUDINARY_CLOUD_NAME') ?: (getenv('cloudinary_cloud_name') ?: ($_ENV['CLOUDINARY_CLOUD_NAME'] ?? ($_ENV['cloudinary_cloud_name'] ?? '')));
    $api_key    = getenv('CLOUDINARY_API_KEY')    ?: (getenv('cloudinary_api_key')    ?: ($_ENV['CLOUDINARY_API_KEY']    ?? ($_ENV['cloudinary_api_key']    ?? '')));
    $api_secret = getenv('CLOUDINARY_API_SECRET') ?: (getenv('cloudinary_api_secret') ?: ($_ENV['CLOUDINARY_API_SECRET'] ?? ($_ENV['cloudinary_api_secret'] ?? '')));

    if (empty($cloud_name) || empty($api_key) || empty($api_secret)) {
        return false;
    }

    $timestamp = time();
    $params_to_sign = "folder={$folder}&timestamp={$timestamp}";
    $signature = sha1($params_to_sign . $api_secret);

    $endpoint = "https://api.cloudinary.com/v1_1/{$cloud_name}/image/upload";

    $post_fields = [
        'file'      => new CURLFile($file_tmp_path),
        'api_key'   => $api_key,
        'timestamp' => $timestamp,
        'signature' => $signature,
        'folder'    => $folder
    ];

    $ch = curl_init($endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code === 200 && $response) {
        $result = json_decode($response, true);
        if (isset($result['secure_url'])) {
            return $result['secure_url'];
        }
    }

    return false;
}

// ─── HELPER FUNCTION FOR USER AVATARS ───
function getUserAvatar($user_row) {
    $profile_pic = trim($user_row['profile_picture'] ?? '');
    $given_name = trim($user_row['given_name'] ?? '');
    $last_name = trim($user_row['last_name'] ?? '');
    
    $pic_path = '';
    $has_picture = false;

    if (!empty($profile_pic) && preg_match("~^https?://~i", $profile_pic)) {
        // Stored as remote Cloudinary URL
        $pic_path = $profile_pic;
        $has_picture = true;
    } elseif (!empty($profile_pic) && file_exists(__DIR__ . '/../assets/images/profile_picture/' . $profile_pic)) {
        // Stored locally in assets directory
        $pic_path = '../assets/images/profile_picture/' . $profile_pic . '?v=' . time();
        $has_picture = true;
    }

    $initials = strtoupper(substr($given_name, 0, 1) . substr($last_name, 0, 1));
    if (empty($initials)) {
        $initials = 'U';
    }

    return [
        'has_picture' => $has_picture,
        'pic_path' => $pic_path,
        'initials' => $initials
    ];
}

// ─── FETCH CURRENT LOGGED-IN ADMIN ───
$curr_user_sql = "SELECT id, username, role, given_name, middle_name, last_name, email, permissions, profile_picture FROM scholarship_users WHERE id = ?";
$curr_stmt = $conn->prepare($curr_user_sql);
$curr_stmt->bind_param("i", $current_user_id);
$curr_stmt->execute();
$curr_user = $curr_stmt->get_result()->fetch_assoc();
$curr_avatar = $curr_user ? getUserAvatar($curr_user) : null;
$curr_stmt->close();

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

// ─── EDIT STAFF / ADMIN ───
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

            // ─── PROFILE PICTURE UPLOAD WITH CLOUDINARY FOR LOGGED IN USER ONLY ───
            if ($user_id === $current_user_id && isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['profile_picture'];
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'webp'];

                if (in_array($ext, $allowed) && $file['size'] <= 2 * 1024 * 1024) {
                    // 1. Attempt Primary Upload to Cloudinary
                    $image_destination = uploadAdminToCloudinary($file['tmp_name'], 'profile_pictures/admin');

                    // 2. Fallback to Local Directory
                    if (!$image_destination) {
                        $upload_dir = __DIR__ . '/../assets/images/profile_picture/';
                        if (!is_dir($upload_dir)) {
                            mkdir($upload_dir, 0777, true);
                        }

                        $new_filename = 'admin_' . $user_id . '_' . time() . '.' . $ext;
                        if (move_uploaded_file($file['tmp_name'], $upload_dir . $new_filename)) {
                            $image_destination = $new_filename;
                        }
                    }

                    if ($image_destination) {
                        // Fetch old photo to delete local file if previously stored locally
                        $old_stmt = $conn->prepare("SELECT profile_picture FROM scholarship_users WHERE id = ?");
                        $old_stmt->bind_param("i", $user_id);
                        $old_stmt->execute();
                        $old_row = $old_stmt->get_result()->fetch_assoc();
                        $old_pic = trim($old_row['profile_picture'] ?? '');
                        $old_stmt->close();

                        if (!empty($old_pic) && !preg_match("~^https?://~i", $old_pic)) {
                            $old_file_path = __DIR__ . '/../assets/images/profile_picture/' . $old_pic;
                            if (file_exists($old_file_path)) {
                                @unlink($old_file_path);
                            }
                        }

                        // Update database with Cloudinary URL or local filename
                        $pic_stmt = $conn->prepare("UPDATE scholarship_users SET profile_picture = ? WHERE id = ?");
                        $pic_stmt->bind_param("si", $image_destination, $user_id);
                        $pic_stmt->execute();
                        $pic_stmt->close();
                    }
                } else {
                    $_SESSION['fail'] = "Account details updated, but picture was rejected (Max 2MB, JPG/PNG/WebP).";
                }
            }
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
        $check_stmt = $conn->prepare("SELECT role FROM scholarship_users WHERE id = ?");
        $check_stmt->bind_param("i", $user_id);
        $check_stmt->execute();
        $target_user = $check_stmt->get_result()->fetch_assoc();
        $check_stmt->close();

        if ($target_user && $target_user['role'] === 'admin' && $user_id !== $current_user_id) {
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

// ─── DELETE ACCOUNT ───
if(isset($_POST['delete_staff'])) {
    $user_id = (int) $_POST['user_id'];
    if($user_id > 0 && $user_id !== $current_user_id) {
        $sql = "DELETE FROM scholarship_users WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        if($stmt->execute()) {
            $_SESSION['success'] = "Account deleted successfully.";
        } else {
            $_SESSION['fail'] = "Failed to delete account.";
        }
        $stmt->close();
    } else {
        $_SESSION['fail'] = "Cannot delete your own account or invalid ID.";
    }
    header("Location: account_management.php");
    exit();
}

// ─── FETCH OTHER ADMIN USERS (EXCLUDING LOGGED IN ADMIN) ───
$admin_sql = "SELECT id, username, role, given_name, middle_name, last_name, email, permissions, profile_picture FROM scholarship_users WHERE role = 'admin' AND id != ? ORDER BY last_name ASC, given_name ASC";
$admin_stmt = $conn->prepare($admin_sql);
$admin_stmt->bind_param("i", $current_user_id);
$admin_stmt->execute();
$admin_result = $admin_stmt->get_result();

// ─── FETCH STAFF USERS ───
$staff_sql = "SELECT id, username, role, given_name, middle_name, last_name, permissions, profile_picture FROM scholarship_users WHERE role = 'staff' ORDER BY last_name ASC, given_name ASC";
$staff_result = $conn->query($staff_sql);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Account Management - PIT ScholarshipHub</title>
    <link rel="stylesheet" href="../assets/css/account_management.css?v=<?= filemtime('../assets/css/account_management.css') ?>">
    <link rel="icon" href="../assets/images/PIT%20LOGO.png">
    <link rel="stylesheet" href="../assets/css/modals.css">
</head>
<body>
    <!-- alert messages -->
    <?php include __DIR__ . '/../includes/modal_alerts.php'; ?>
    <div class="container">
        <?php include __DIR__ . "/../includes/navigation.php"; ?>

        <!-- Page Content -->
        <div class="page-content">
            <!-- Page Header -->
            <div class="page-header">
                <h2>Account Management</h2>
            </div>

            <!-- ========== CURRENT ADMIN PROFILE CARD ========== -->
            <?php if ($curr_user) : ?>
                <div class="current-user-card">
                    <div class="current-user-main">
                        <div class="current-user-avatar">
                            <?php if ($curr_avatar['has_picture']) : ?>
                                <img src="<?= htmlspecialchars($curr_avatar['pic_path']) ?>" alt="My Profile Photo" 
                                     class="current-user-avatar-img"
                                     onclick="openImageLightbox('<?= htmlspecialchars($curr_avatar['pic_path'], ENT_QUOTES) ?>')" 
                                     title="Click to view photo">
                            <?php else : ?>
                                <div class="current-user-avatar-initials">
                                    <?= htmlspecialchars($curr_avatar['initials']) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="current-user-details">
                            <div class="current-user-title-row">
                                <h3 class="current-user-name"><?= htmlspecialchars($curr_user['given_name'] . ' ' . $curr_user['middle_name'] . ' ' . $curr_user['last_name']) ?></h3>
                                <span class="badge badge-admin">Admin (You)</span>
                            </div>
                            <div class="current-user-meta">
                                <span><strong>Username:</strong> <?= htmlspecialchars($curr_user['username']) ?></span>
                                <span><strong>Email:</strong> <?= htmlspecialchars($curr_user['email'] ?? 'N/A') ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="current-user-actions">
                        <button class="btn btn-warning" onclick="openEditModal(<?= $curr_user['id'] ?>, '<?= htmlspecialchars($curr_user['username'], ENT_QUOTES) ?>', '<?= htmlspecialchars($curr_user['role'], ENT_QUOTES) ?>', '<?= htmlspecialchars($curr_user['given_name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($curr_user['middle_name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($curr_user['last_name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($curr_user['email'] ?? '', ENT_QUOTES) ?>', '<?= htmlspecialchars($curr_user['permissions'], ENT_QUOTES) ?>', true)">Edit Account</button>
                        <button class="btn btn-secondary" onclick="openPasswordModal(<?= $curr_user['id'] ?>)">Change Password</button>
                    </div>
                </div>
            <?php endif; ?>

            <!-- ========== PROMINENT ACTION BANNER ========== -->
            <div class="account-action-banner">
                <div class="action-banner-info">
                    <div class="action-banner-icon">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                            <circle cx="8.5" cy="7" r="4"></circle>
                            <line x1="20" y1="8" x2="20" y2="14"></line>
                            <line x1="17" y1="11" x2="23" y2="11"></line>
                        </svg>
                    </div>
                    <div class="action-banner-text">
                        <h4>Account Operations</h4>
                        <p>Create new admin or staff accounts and configure page access permissions.</p>
                    </div>
                </div>
                <button type="button" class="btn btn-success btn-add-account" onclick="openAddModal()">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="12" y1="5" x2="12" y2="19"></line>
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                    </svg>
                    <span>Add New Account</span>
                </button>
            </div>

            <!-- ========== OTHER ADMIN ACCOUNTS ========== -->
            <div class="section-card">
                <div class="section-header">
                    <h3>Other Admin Accounts</h3>
                </div>

                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th class="col-photo">Photo</th>
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
                                    $avatar = getUserAvatar($admin);
                                ?>
                                    <tr>
                                        <td class="col-photo-cell">
                                            <?php if ($avatar['has_picture']) : ?>
                                                <img src="<?= htmlspecialchars($avatar['pic_path']) ?>" alt="User Photo" 
                                                     class="user-table-avatar-img"
                                                     onclick="openImageLightbox('<?= htmlspecialchars($avatar['pic_path'], ENT_QUOTES) ?>')" 
                                                     title="Click to view photo">
                                            <?php else : ?>
                                                <div class="user-table-avatar-initials">
                                                    <?= htmlspecialchars($avatar['initials']) ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($admin['last_name']) ?>, <?= htmlspecialchars($admin['given_name']) ?> <?= htmlspecialchars($admin['middle_name']) ?></td>
                                        <td><?= htmlspecialchars($admin['username']) ?></td>
                                        <td><?= htmlspecialchars($admin['email'] ?? 'N/A') ?></td>
                                        <td><?= htmlspecialchars(ucfirst($admin['role'])) ?></td>
                                        <td class="perm-summary full-access-text">
                                            Full Access
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <button class="btn-sm btn-edit" onclick="openEditModal(<?= $admin['id'] ?>, '<?= htmlspecialchars($admin['username'], ENT_QUOTES) ?>', '<?= htmlspecialchars($admin['role'], ENT_QUOTES) ?>', '<?= htmlspecialchars($admin['given_name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($admin['middle_name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($admin['last_name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($admin['email'] ?? '', ENT_QUOTES) ?>', '<?= htmlspecialchars($admin['permissions'], ENT_QUOTES) ?>', true)">Edit</button>
                                                <form action="" method="POST" style="display: inline;">
                                                    <input type="hidden" name="delete_staff" value="1">
                                                    <input type="hidden" name="user_id" value="<?= $admin['id'] ?>">
                                                    <button type="submit" class="btn-sm btn-delete" onclick="return confirmAction(event, 'Are you sure you want to delete this admin account?', 'Delete', true)">Delete</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else : ?>
                                <tr><td colspan="7" class="no-data">No other admin accounts found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ========== STAFF ACCOUNTS ========== -->
            <div class="section-card">
                <div class="section-header">
                    <h3>Staff Accounts</h3>
                </div>

                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th class="col-photo">Photo</th>
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
                                    $avatar = getUserAvatar($staff);
                                ?>
                                    <tr>
                                        <td class="col-photo-cell">
                                            <?php if ($avatar['has_picture']) : ?>
                                                <img src="<?= htmlspecialchars($avatar['pic_path']) ?>" alt="User Photo" 
                                                     class="user-table-avatar-img"
                                                     onclick="openImageLightbox('<?= htmlspecialchars($avatar['pic_path'], ENT_QUOTES) ?>')" 
                                                     title="Click to view photo">
                                            <?php else : ?>
                                                <div class="user-table-avatar-initials">
                                                    <?= htmlspecialchars($avatar['initials']) ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
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
                                                <button class="btn-sm btn-edit" onclick="openEditModal(<?= $staff['id'] ?>, '<?= htmlspecialchars($staff['username'], ENT_QUOTES) ?>', '<?= htmlspecialchars($staff['role'], ENT_QUOTES) ?>', '<?= htmlspecialchars($staff['given_name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($staff['middle_name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($staff['last_name'], ENT_QUOTES) ?>', '', '<?= htmlspecialchars($staff['permissions'], ENT_QUOTES) ?>', false)">Edit</button>
                                                <button class="btn-sm btn-warning" onclick="openPasswordModal(<?= $staff['id'] ?>)">Change Password</button>
                                                <form action="" method="POST" style="display: inline;">
                                                    <input type="hidden" name="delete_staff" value="1">
                                                    <input type="hidden" name="user_id" value="<?= $staff['id'] ?>">
                                                    <button type="submit" class="btn-sm btn-delete" onclick="return confirmAction(event, 'Are you sure you want to delete this staff account?', 'Delete', true)">Delete</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else : ?>
                                <tr><td colspan="6" class="no-data">No staff accounts found.</td></tr>
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

    <!-- ========== IMAGE LIGHTBOX MODAL ========== -->
    <div id="imageLightboxModal" class="modal">
        <div class="lightbox-modal-content">
            <span class="lightbox-close" onclick="closeImageLightbox()">&times;</span>
            <img id="lightboxImage" src="" alt="Enlarged Profile Picture">
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
                    <div class="info-message info-message-admin">
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
            <form action="" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="edit_staff" value="1">
                <input type="hidden" name="user_id" id="edit_user_id">
                
                <!-- Profile Picture Field (Visible ONLY when editing currently logged-in account) -->
                <div class="form-group" id="editProfilePicGroup" style="display: none;">
                    <label>Profile Picture</label>
                    <div class="edit-avatar-preview-box">
                        <img id="editAvatarPreviewImg" src="" alt="Avatar Preview" class="edit-avatar-preview-img" style="display: none;">
                    </div>
                    <input type="file" name="profile_picture" id="edit_profile_picture" accept="image/jpeg,image/png,image/webp" onchange="previewEditAvatar(this)">
                    <small class="help-text">Upload JPG, PNG, or WebP (Max 2MB). Leave empty to keep existing photo.</small>
                </div>

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
                    <div class="info-message info-message-admin">
                        <strong>Full Access:</strong> Administrators have permission to access all pages and perform all actions. No permission settings are required.
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-success">Update</button>
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
                <input type="hidden" name="change_password" value="1">
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
                    <button type="submit" class="btn btn-success">Update Password</button>
                    <button type="button" onclick="closeModal()" class="btn btn-secondary">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const CURRENT_USER_ID = <?= $current_user_id ?>;

        // ─── LIGHTBOX HELPERS ───
        function openImageLightbox(src) {
            const modal = document.getElementById('imageLightboxModal');
            const img = document.getElementById('lightboxImage');
            if (modal && img) {
                img.src = src;
                modal.style.display = 'flex';
            }
        }

        function closeImageLightbox() {
            const modal = document.getElementById('imageLightboxModal');
            if (modal) {
                modal.style.display = 'none';
            }
        }

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
            const editProfilePicGroup = document.getElementById('editProfilePicGroup');
            const fileInput = document.getElementById('edit_profile_picture');
            const previewImg = document.getElementById('editAvatarPreviewImg');

            // Reset file upload field & preview
            if (fileInput) fileInput.value = '';
            if (previewImg) {
                previewImg.src = '';
                previewImg.style.display = 'none';
            }

            // Display profile picture upload ONLY if editing currently logged-in account
            if (parseInt(id) === CURRENT_USER_ID) {
                editProfilePicGroup.style.display = 'block';
            } else {
                editProfilePicGroup.style.display = 'none';
            }

            if (isAdmin) {
                permissionsSection.style.display = 'none';
                adminMessage.style.display = 'block';
                editEmailGroup.style.display = 'block';
                modalTitle.textContent = (parseInt(id) === CURRENT_USER_ID) ? 'Edit My Account' : 'Edit Admin Account';
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

        // ─── CLIENT-SIDE AVATAR PREVIEW IN EDIT MODAL ───
        function previewEditAvatar(input) {
            const previewImg = document.getElementById('editAvatarPreviewImg');
            if (input.files && input.files[0]) {
                const file = input.files[0];

                if (file.size > 2 * 1024 * 1024) {
                    alert('File size exceeds 2MB limit.');
                    input.value = '';
                    previewImg.style.display = 'none';
                    return;
                }

                const reader = new FileReader();
                reader.onload = function(e) {
                    previewImg.src = e.target.result;
                    previewImg.style.display = 'block';
                };
                reader.readAsDataURL(file);
            } else {
                previewImg.style.display = 'none';
            }
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
            const lightboxModal = document.getElementById('imageLightboxModal');
            if (event.target === lightboxModal) {
                closeImageLightbox();
            } else if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
    </script>
</body>
</html>