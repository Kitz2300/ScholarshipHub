<?php
include '../includes/conn.php';
include '../includes/check_permission.php';
session_start();

// Check if logged in and is admin/staff
if(!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'staff')) {
    header("Location: login.php");
    exit();
}

// ─── Restore Scholar ───
if(isset($_POST['restore_scholar']) && isset($_POST['scholar_id'])) {
    $scholar_id = (int) $_POST['scholar_id'];

    $scholarship_sql = "SELECT scholarship_program.is_archived FROM scholarship_program INNER JOIN scholar_info ON scholarship_program.id = scholar_info.scholarship_program_id WHERE scholar_info.id = ? AND scholarship_program.is_archived = 0";
    $scholarship_stmt = $conn->prepare($scholarship_sql);
    $scholarship_stmt->bind_param('i', $scholar_id);
    $scholarship_stmt->execute();
    $scholarship_result = $scholarship_stmt->get_result();

    if($scholarship_result->num_rows == 0) {
        $_SESSION['fail'] = "Cannot restore scholar becuase scholarship program is archived or not in the records";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
        
            
    $restore_sql = "UPDATE scholar_info SET is_archived = 0 WHERE id = ?";
    $restore_stmt = $conn->prepare($restore_sql);
    $restore_stmt->bind_param("i", $scholar_id);
        
    $restore_stmt->execute();
    $_SESSION['success'] = "Scholar restored successfully.";
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// ─── Permanently Delete Scholar ───
if(isset($_POST['delete_scholar_permanent']) && isset($_POST['scholar_id'])) {
    $scholar_id = (int) $_POST['scholar_id'];
    $delete_sql = "DELETE FROM scholar_info WHERE id = ?";
    $delete_stmt = $conn->prepare($delete_sql);
    $delete_stmt->bind_param("i", $scholar_id);
    $delete_stmt->execute();
    $_SESSION['success'] = "Scholar permanently deleted.";
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// ─── Restore Program ───
if(isset($_POST['restore_program']) && isset($_POST['program_id'])) {
    $program_id = (int) $_POST['program_id'];
    $restore_sql = "UPDATE scholarship_program SET is_archived = 0 WHERE id = ?";
    $restore_stmt = $conn->prepare($restore_sql);
    $restore_stmt->bind_param("i", $program_id);

    $restore_scholars_sql = "UPDATE scholar_info SET is_archived = 0 WHERE scholarship_program_id = ?";
    $restore_scholars_stmt = $conn->prepare($restore_scholars_sql);
    $restore_scholars_stmt->bind_param("i", $program_id);

    if($restore_stmt->execute() && $restore_scholars_stmt->execute()) {
        $_SESSION['success'] = "Scholarship program restored successfully.";
    } else {
        $_SESSION['fail'] = "Scholarship program failed to restore.";
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// ─── Permanently Delete Program ───
if(isset($_POST['delete_program_permanent']) && isset($_POST['program_id'])) {
    $program_id = (int) $_POST['program_id'];
    $delete_sql = "DELETE FROM scholarship_program WHERE id = ?";
    $delete_stmt = $conn->prepare($delete_sql);
    $delete_stmt->bind_param("i", $program_id);
    $delete_stmt->execute();
    $_SESSION['success'] = "Scholarship program permanently deleted.";
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// ─── Fetch Archived Scholars ───
$archived_scholars_sql = "SELECT scholar_info.id, student_id, last_name, given_name, academic_status, scholarship_program.name AS SPname, scholar_info.is_archived FROM scholar_info INNER JOIN scholarship_program ON scholar_info.scholarship_program_id = scholarship_program.id WHERE scholar_info.is_archived = 1 ORDER BY last_name ASC";
$archived_scholars_stmt = $conn->prepare($archived_scholars_sql);
$archived_scholars_stmt->execute();
$archived_scholars_result = $archived_scholars_stmt->get_result();

// ─── Fetch Archived Programs ───
$archived_programs_sql = "SELECT id, name, code, is_archived FROM scholarship_program WHERE is_archived = 1 ORDER BY name ASC";
$archived_programs_stmt = $conn->prepare($archived_programs_sql);
$archived_programs_stmt->execute();
$archived_programs_result = $archived_programs_stmt->get_result();

// ─── Count Archived Items ───
$archived_count_sql = "SELECT (SELECT COUNT(*) FROM scholar_info WHERE is_archived = 1) AS scholar_count, (SELECT COUNT(*) FROM scholarship_program WHERE is_archived = 1) AS program_count";
$archived_count_stmt = $conn->prepare($archived_count_sql);
$archived_count_stmt->execute();
$archived_count_result = $archived_count_stmt->get_result();
$archived_count = $archived_count_result->fetch_assoc();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Archives - PIT ScholarshipHub</title>
    <link rel="stylesheet" href="../assets/css/archive.css?v=<?= filemtime('../assets/css/archive.css') ?>">
    <link rel="icon" href="../assets/images/PIT%20LOGO.png">
</head>
<body>
    <?php 
    // Success/fail messages
    if(isset($_SESSION['success'])) {
        echo "<script>alert('" . $_SESSION['success'] . "')</script>";
        unset($_SESSION['success']);
    } else if(isset($_SESSION['fail'])) {
        echo "<script>alert('" . $_SESSION['fail'] . "')</script>";
        unset($_SESSION['fail']);
    }
    ?>
    <?php if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== "admin") :?>
        <script>alert("You are note logged in!")</script>
        <script>window.location.href="../portal.php"</script>
    <?php else : ?>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="header-left">
                <img src="../assets/images/PIT%20LOGO.png" alt="PIT Logo">
                <div>
                    <h1>PIT ScholarshipHub</h1>
                    <p>Archives</p>
                </div>
            </div>
            <div class="header-right">
                <span class="welcome-text">Welcome, <?= $_SESSION['role'] == 'admin' ? 'Admin!' : 'Staff!' ?></span>
                <form action="../functions/logout.php" method="post">
                    <button type="submit" class="logout-btn" onclick="return confirm('Logout?')">Logout</button>
                </form>
            </div>
        </div>

        <?php include __DIR__ . "/../includes/navigation.php"; ?>

        <!-- Page Content -->
        <div class="page-content">
            <div class="page-header">
                <h2>Archive Management</h2>
                <div class="archive-stats">
                    <span class="stat-badge">Scholars: <?= $archived_count['scholar_count'] ?? 0 ?></span>
                    <span class="stat-badge">Programs: <?= $archived_count['program_count'] ?? 0 ?></span>
                </div>
            </div>

            <!-- Archived Scholars -->
            <div class="section-card">
                <div class="table-wrapper">
                    <h3>Archived Scholars</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Student ID</th>
                                <th>Name</th>
                                <th>Status</th>
                                <th>Scholarship Program</th>
                                <th class="actions-col">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($archived_scholars_result->num_rows > 0) : ?>
                                <?php while($row = $archived_scholars_result->fetch_assoc()) : ?>
                                    <tr>
                                        <td><?= htmlspecialchars($row['student_id']) ?></td>
                                        <td><?= htmlspecialchars($row['last_name']) ?>, <?= htmlspecialchars($row['given_name']) ?></td>
                                        <td><?= htmlspecialchars($row['academic_status']) ?></td>
                                        <td><?= htmlspecialchars($row['SPname']) ?></td>
                                        <td>
                                            <div class="action-buttons">
                                                <form action="<?= $_SERVER['PHP_SELF'] ?>" method="POST" style="display: inline;">
                                                    <input type="hidden" name="scholar_id" value="<?= $row['id'] ?>">
                                                    <button type="submit" name="restore_scholar" class="btn-sm btn-restore" onclick="return confirm('Restore this scholar?')">Restore</button>
                                                </form>
                                                <!-- <form action="<?= $_SERVER['PHP_SELF'] ?>" method="POST" style="display: inline;">
                                                    <input type="hidden" name="scholar_id" value="<?= $row['id'] ?>">
                                                    <button type="submit" name="delete_scholar_permanent" class="btn-sm btn-delete" onclick="return confirm('Permanently delete this scholar? This cannot be undone!')">Delete</button>
                                                </form> -->
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else : ?>
                                <tr><td colspan="5" class="no-data">No archived scholars.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Archived Programs -->
            <div class="section-card">
                <h3>Archived Scholarship Programs</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Program Name</th>
                            <th>Code</th>
                            <th class="actions-col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($archived_programs_result->num_rows > 0) : ?>
                            <?php while($row = $archived_programs_result->fetch_assoc()) : ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['name']) ?></td>
                                    <td><?= htmlspecialchars($row['code']) ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <form action="<?= $_SERVER['PHP_SELF'] ?>" method="POST" style="display: inline;">
                                                <input type="hidden" name="program_id" value="<?= $row['id'] ?>">
                                                <button type="submit" name="restore_program" class="btn-sm btn-restore" onclick="return confirm('Restore this program?')">Restore</button>
                                            </form>
                                            <form action="<?= $_SERVER['PHP_SELF'] ?>" method="POST" style="display: inline;">
                                                <input type="hidden" name="program_id" value="<?= $row['id'] ?>">
                                                <button type="submit" name="delete_program_permanent" class="btn-sm btn-delete" onclick="return confirm('Permanently delete this program and its scholars? This cannot be undone!')">Delete</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else : ?>
                            <tr><td colspan="3" class="no-data">No archived scholarship programs.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            © 2026 PIT ScholarshipHub. All rights reserved.
      /div>
    </div>
    <?php endif; ?>
</body>
</html>