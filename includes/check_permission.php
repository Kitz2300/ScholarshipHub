<?php
// includes/check_permission.php

// ─── Page definitions (only pages staff can access) ───
function getPageDefinitions() {
    return [
        'scholars' => 'Scholars',
        'scholarship_programs' => 'Scholarship Programs',
        'import_export' => 'Import / Export',
        'announcements' => 'Announcements',
        'program_management' => 'Program & College Management',
        'promote_scholars' => 'Promote Scholars & Term Advancement'
    ];
}

// ─── Available actions per page ───
function getAvailableActions($page = null) {
    $all_actions = [
        'scholars' => ['add', 'edit', 'notes', 'GWA & units'], // Removed 'archive' scholar permission
        'scholarship_programs' => ['add', 'edit', 'archive'],
        'import_export' => ['import', 'export'],
        'announcements' => ['add', 'edit', 'status', 'delete'],
        'program_management' => ['add', 'edit', 'delete'],
        'promote_scholars' => ['promote_year_level', 'advance_semester']
    ];
    return $page ? ($all_actions[$page] ?? []) : $all_actions;
}

// ─── Check if staff can access a page ───
function canAccessPage($conn, $user_id, $page) {
    // Admin always has access
    $sql = "SELECT role FROM scholarship_users WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        if ($row['role'] === 'admin') return true;
    }
    
    $permissions = getUserPermissions($conn, $user_id);
    return isset($permissions[$page]) && in_array('view', $permissions[$page]);
}

// ─── Check if user has a specific action permission ───
function hasPermission($conn, $user_id, $page, $action) {
    // Admin always has all permissions
    $sql = "SELECT role FROM scholarship_users WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        if ($row['role'] === 'admin') return true;
    }
    
    $permissions = getUserPermissions($conn, $user_id);
    return isset($permissions[$page]) && in_array($action, $permissions[$page]);
}

// ─── Redirect if staff cannot access page ───
function checkPageAccess($conn, $user_id, $page) {
    if (!canAccessPage($conn, $user_id, $page)) {
        $_SESSION['fail'] = "You don't have permission to access this page.";
        header("Location: dashboard.php");
        exit();
    }
}

// ─── Get user permissions ───
function getUserPermissions($conn, $user_id) {
    $sql = "SELECT permissions FROM scholarship_users WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $perms = json_decode($row['permissions'], true);
        return is_array($perms) ? $perms : [];
    }
    return [];
}

// ─── Render permissions checkboxes ───
function renderPermissionsTable($existing_perms = []) {
    $pages = getPageDefinitions();
    ?>
    <table class="permissions-table">
        <thead>
            <tr>
                <th style="min-width:180px;">Page</th>
                <th>Access (View)</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($pages as $page_key => $page_label) : 
                $available = getAvailableActions($page_key);
                $view_checked = isset($existing_perms[$page_key]) && in_array('view', $existing_perms[$page_key]);
            ?>
                <tr>
                    <td>
                        <strong><?= $page_label ?></strong>
                    </td>
                    <td>
                        <label>
                            <input type="checkbox" 
                                   class="view-checkbox" 
                                   data-page="<?= $page_key ?>"
                                   name="permissions[<?= $page_key ?>][]"
                                   value="view"
                                   <?= $view_checked ? 'checked' : '' ?>
                                   onchange="togglePagePermissions(this, '<?= $page_key ?>')">
                            Allow Access
                        </label>
                    </td>
                    <td>
                        <div class="action-checkboxes">
                            <?php foreach($available as $action) : 
                                $checked = isset($existing_perms[$page_key]) && in_array($action, $existing_perms[$page_key]);
                                $action_label = str_replace('_', ' ', $action);
                            ?>
                                <label>
                                    <input type="checkbox" 
                                           class="perm-checkbox" 
                                           data-page="<?= $page_key ?>"
                                           data-action="<?= $action ?>"
                                           name="permissions[<?= $page_key ?>][]" 
                                           value="<?= $action ?>"
                                           <?= $checked ? 'checked' : '' ?>
                                           <?= $view_checked ? '' : 'disabled' ?>>
                                    <?= ucwords($action_label) ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php
}

// ─── Save staff permissions ───
function saveStaffPermissions($conn, $user_id, $permissions) {
    $permissions_json = json_encode($permissions);
    $sql = "UPDATE scholarship_users SET permissions = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $permissions_json, $user_id);
    return $stmt->execute();
}
?>