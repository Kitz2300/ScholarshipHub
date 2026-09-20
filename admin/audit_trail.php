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

// ─── FILTER INPUTS ───
$start_date = trim($_GET['start_date'] ?? '');
$end_date   = trim($_GET['end_date'] ?? '');
$user_id    = (int)($_GET['user_id'] ?? 0);
$role       = trim($_GET['role'] ?? '');

// ─── PAGINATION SETUP ───
$limit = 20;
$page  = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

// ─── FETCH DROPDOWN DATA ───
$users_result = $conn->query("SELECT id, username, CONCAT_WS(' ', given_name, middle_name, last_name) AS full_name FROM scholarship_users ORDER BY full_name ASC, username ASC");
$roles_result = $conn->query("SELECT DISTINCT role FROM scholarship_users WHERE role IS NOT NULL AND role != '' ORDER BY role ASC");

// ─── BUILD DYNAMIC QUERY ───
$where_clauses = ["1=1"];
$params = [];
$types = "";

if (!empty($start_date)) {
    $where_clauses[] = "DATE(a.actioned_at) >= ?";
    $params[] = $start_date;
    $types .= "s";
}

if (!empty($end_date)) {
    $where_clauses[] = "DATE(a.actioned_at) <= ?";
    $params[] = $end_date;
    $types .= "s";
}

if ($user_id > 0) {
    $where_clauses[] = "a.user_id = ?";
    $params[] = $user_id;
    $types .= "i";
}

if (!empty($role)) {
    $where_clauses[] = "u.role = ?";
    $params[] = $role;
    $types .= "s";
}

$where_sql = implode(" AND ", $where_clauses);

// ─── COUNT TOTAL RECORDS ───
$count_sql = "SELECT COUNT(*) AS total 
              FROM audit_trail a 
              LEFT JOIN scholarship_users u ON a.user_id = u.id 
              WHERE $where_sql";

$count_stmt = $conn->prepare($count_sql);
if (!empty($types)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_rows = $count_stmt->get_result()->fetch_assoc()['total'] ?? 0;
$total_pages = ceil($total_rows / $limit);

// ─── FETCH LOGS WITH FULL NAME ───
$logs_sql = "SELECT a.id, a.user_id, a.action, a.actioned_at, u.username, 
                    CONCAT_WS(' ', u.given_name, u.middle_name, u.last_name) AS full_name, u.role 
             FROM audit_trail a 
             LEFT JOIN scholarship_users u ON a.user_id = u.id 
             WHERE $where_sql 
             ORDER BY a.actioned_at DESC 
             LIMIT ? OFFSET ?";

$logs_stmt = $conn->prepare($logs_sql);
$data_params = $params;
$data_params[] = $limit;
$data_params[] = $offset;
$data_types = $types . "ii";

$logs_stmt->bind_param($data_types, ...$data_params);
$logs_stmt->execute();
$logs_result = $logs_stmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Audit Trail - PIT ScholarshipHub</title>
    <link rel="stylesheet" href="../assets/css/audit_trail.css?v=<?= filemtime('../assets/css/audit_trail.css') ?>">
    <link rel="stylesheet" href="../assets/css/modals.css?v=<?= filemtime('../assets/css/modals.css') ?>">
    <link rel="icon" href="../assets/images/PIT%20LOGO.png">
</head>
<body>
    <?php include __DIR__ . "/../includes/modal_alerts.php"; ?>
    <?php 
    if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'staff')) : 
        $_SESSION['fail'] = "You are no logged in!";
        header("Location: ../portal.php");
        exit();
    else : ?>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="header-left">
                <img src="../assets/images/PIT%20LOGO.png" alt="PIT Logo">
                <div>
                    <h1>PIT ScholarshipHub</h1>
                    <p>System Activity Audit Trail</p>
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
            <div class="page-header">
                <h2>Audit Trail Logs</h2>
            </div>

            <!-- ========== FILTER CARD ========== -->
            <div class="section-card">
                <h3>Filter Logs</h3>
                <form method="GET" action="audit_trail.php">
                    <div class="filter-grid">
                        <div class="form-group">
                            <label>Start Date</label>
                            <input type="date" name="start_date" value="<?= htmlspecialchars($start_date) ?>">
                        </div>

                        <div class="form-group">
                            <label>End Date</label>
                            <input type="date" name="end_date" value="<?= htmlspecialchars($end_date) ?>">
                        </div>

                        <div class="form-group">
                            <label>User</label>
                            <select name="user_id">
                                <option value="">All Users</option>
                                <?php while ($u = $users_result->fetch_assoc()): ?>
                                    <?php $user_label = !empty(trim($u['full_name'] ?? '')) ? trim($u['full_name']) : $u['username']; ?>
                                    <option value="<?= $u['id'] ?>" <?= $user_id === (int)$u['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($user_label) ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Role</label>
                            <select name="role">
                                <option value="">All Roles</option>
                                <?php while ($r = $roles_result->fetch_assoc()): ?>
                                    <option value="<?= htmlspecialchars($r['role']) ?>" <?= $role === $r['role'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars(ucfirst($r['role'])) ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>

                    <div class="filter-actions">
                        <button type="submit" class="btn btn-primary">Filter</button>
                        <a href="audit_trail.php" class="btn btn-secondary">Reset</a>
                    </div>
                </form>
            </div>

            <!-- ========== LOGS TABLE ========== -->
            <div class="section-card">
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th style="width: 18%;">Timestamp</th>
                                <th style="width: 20%;">User</th>
                                <th style="width: 12%;">Role</th>
                                <th style="width: 50%;">Action Performed</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($logs_result->num_rows > 0) : ?>
                                <?php while ($log = $logs_result->fetch_assoc()) : ?>
                                    <?php 
                                        $role_badge = match(strtolower($log['role'] ?? '')) {
                                            'admin' => 'role-admin',
                                            'staff' => 'role-staff',
                                            default => 'role-default'
                                        };
                                        $display_name = !empty(trim($log['full_name'] ?? '')) ? trim($log['full_name']) : ($log['username'] ?? 'System / Unknown');
                                    ?>
                                    <tr>
                                        <td><?= date('M d, Y h:i:s A', strtotime($log['actioned_at'])) ?></td>
                                        <td><strong><?= htmlspecialchars($display_name) ?></strong></td>
                                        <td>
                                            <span class="role-badge <?= $role_badge ?>">
                                                <?= htmlspecialchars(ucfirst($log['role'] ?? 'N/A')) ?>
                                            </span>
                                        </td>
                                        <td class="action-code"><?= htmlspecialchars($log['action']) ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else : ?>
                                <tr>
                                    <td colspan="4" class="no-data">No audit log records found matching the specified filters.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <?php 
                        $query_params = $_GET;
                        unset($query_params['page']);
                        $base_url = 'audit_trail.php?' . http_build_query($query_params);
                        $connector = empty($query_params) ? '' : '&';
                    ?>
                    <div class="pagination-wrapper">
                        <div class="pagination-info">
                            Showing <?= $offset + 1 ?> to <?= min($offset + $limit, $total_rows) ?> of <?= $total_rows ?> entries
                        </div>
                        <div class="pagination-links">
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <a href="<?= $base_url . $connector . 'page=' . $i ?>" 
                                   class="page-btn <?= $i === $page ? 'active' : '' ?>">
                                    <?= $i ?>
                                </a>
                            <?php endfor; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            © 2026 PIT ScholarshipHub. All rights reserved.
        </div>
    </div>
    <?php endif; ?>
</body>
</html>