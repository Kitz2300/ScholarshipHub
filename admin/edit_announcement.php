<?php 
include __DIR__ . "/../includes/conn.php";
session_start();

$announcement_id = (int) ($_GET['id'] ?? 0);

$announcement_sql = "SELECT * FROM announcements WHERE id=?";
$announcement_stmt = $conn->prepare($announcement_sql);
$announcement_stmt->bind_param("i", $announcement_id);
$announcement_stmt->execute();
$result = $announcement_stmt->get_result();

// announcement data variables
$title = "";
$content = "";
$status = 0;

if($row=$result->fetch_assoc()) {
    $title = $row['title'];
    $content = $row['content'];
    $status = (int)$row['is_active'];
}

?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Announcement - PIT ScholarshipHub</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/form.css?v=<?= filemtime('../assets/css/form.css') ?>">
    <link rel="icon" href="../assets/images/PIT%20LOGO.png">
</head>
<body>
    <?php
    if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') : ?>
        <!-- if not logged in, redirect user to login page -->
        <script>alert('You are not logged in')</script>
        <script>window.location.href='login.php'</script>
    <?php else : ?>
        <div class="container">
            <!-- Header -->
            <div class="header">
                <div class="header-left">
                    <img src="../assets/images/PIT%20LOGO.png" alt="PIT Logo">
                    <div>
                        <h1>PIT ScholarshipHub</h1>
                        <p>Edit Announcement</p>
                    </div>
                </div>
                <div class="header-right">
                    <span class="welcome-text">Welcome, Admin!</span>
                    <form action="../functions/logout.php" method="post" style="display: inline;">
                        <button type="submit" class="logout-btn" onclick="return confirm('Logout?')">Logout</button>
                    </form>
                </div>
            </div>

            <!-- Navigation -->
            <nav class="nav-bar">
                <ul>
                    <!-- Dashboard -->
                    <li><a href="dashboard.php">Dashboard</a></li>

                    <!-- Records Dropdown -->
                    <li class="dropdown">
                        <a href="#" class="dropdown-toggle">Records <div class="arrow-down">▼</div></a>
                        <ul class="dropdown-menu">
                            <li><a href="scholar_list.php">Scholars</a></li>
                            <li><a href="scholarship_program.php">Scholarship Programs</a></li>
                            <li><a href="archive.php">Archives</a></li>
                        </ul>
                    </li>

                    <!-- Import/Export Dropdown -->
                    <li class="dropdown">
                        <a href="#" class="dropdown-toggle">Import / Export <div class="arrow-down">▼</div></a>
                        <ul class="dropdown-menu">
                            <li><a href="upload_excel.php">Import Excel</a></li>
                            <li><a href="export_scholars.php">Export Excel</a></li>
                        </ul>
                    </li>

                    <!-- Announcements -->
                    <li><a href="announcements.php" class="active">Announcements</a></li>

                    <!-- Program / College Management -->
                    <li><a href="program_management.php">Program & College Management</a></li>
                    
                    <!-- Account Management -->
                    <?php if($_SESSION['role'] == "admin") : ?>
                        <li><a href="account_management.php">Account Management</a></li>
                    <?php endif; ?>
                </ul>
            </nav>

            <!-- Page Content -->
            <div class="page-content">
                <!-- Page Header -->
                <div class="page-header">
                    <h2>Edit Announcement</h2>
                    <div class="page-actions">
                        <a href="announcements.php" class="btn btn-secondary">Back to Announcements</a>
                    </div>
                </div>

                <!-- Edit Announcement Form -->
                <form action="../functions/admin/edit_announcement_process.php" method="POST" class="reusable-form">
                    <!-- every input has current values that are editable -->
                    <div class="form-section">
                        <div class="form-row">
                            <div class="form-group full-width">
                                <label for="title">Title <span class="required">*</span></label>
                                <input type="text" name="title" id="title" value="<?= htmlspecialchars($title) ?>" required/>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group full-width">
                                <label for="content">Content <span class="required">*</span></label>
                                <textarea name="content" id="content" rows="8" required><?= htmlspecialchars($content) ?></textarea>
                            </div>
                        </div>

                        <!-- <div class="form-row">
                            <div class="form-group">
                                <label for="status">Status <span class="required">*</span></label>
                                <select name="status" id="status" required>
                                    <option value="1" <?= $status == 1 ? "selected" : "" ?>>Active</option>
                                    <option value="0" <?= $status == 0 ? "selected" : "" ?>>Not Active</option>
                                </select>
                                <small class="help-text">Active announcements will be displayed on the portal page.</small>
                            </div>
                        </div> -->
                    </div>

                    <input type="hidden" name="announcement_id" value="<?= htmlspecialchars($announcement_id) ?>"/>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-success">Update Announcement</button>
                        <a href="announcements.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>

            <!-- Footer -->
            <div class="footer">
                © 2026 PIT ScholarshipHub. All rights reserved.
            </div>
        </div>
    <?php endif; ?>
</body>
</html>