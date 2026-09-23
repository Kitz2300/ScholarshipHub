<?php 
include __DIR__ . "/../includes/conn.php";
include __DIR__ . "/../includes/check_permission.php";
session_start();

if(!hasPermission($conn, $_SESSION['user_id'], 'announcements', 'add')) {
    header("Location: announcements.php");
    exit();
}


?>

<!DOCTYPE html>
<html>
<head>
    <title>Add Announcement - PIT ScholarshipHub</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/form.css?v=<?= filemtime('../assets/css/form.css') ?>">
    <link rel="stylesheet" href="../assets/css/modals.css?v=<?= filemtime('../assets/css/modals.css') ?>">
    <link rel="icon" href="../assets/images/PIT%20LOGO.png">
</head>
<body>
    <?php include __DIR__ . "/../includes/modal_alerts.php"; ?>
    <?php
    if(!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'staff')) :
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
                        <p>Add Announcement</p>
                    </div>
                </div>
                <div class="header-right">
                    <span class="welcome-text">Welcome, Admin!</span>
                    <form action="../functions/logout.php" method="post" style="display: inline;">
                        <button type="submit" class="logout-btn" onclick="return confirmAction(event, 'Are you sure you want to logout?', 'Logout', true)">Logout</button>
                    </form>
                </div>
            </div>

            <?php include __DIR__ . "/../includes/navigation.php"; ?>

            <!-- Page Content -->
            <div class="page-content">
                <!-- Page Header -->
                <div class="page-header">
                    <h2>Add Announcement</h2>
                    <div class="page-actions">
                        <a href="announcements.php" class="btn btn-secondary">Back to Announcements</a>
                    </div>
                </div>

                <!-- Add Announcement Form -->
                <form action="../functions/admin/add_announcement_process.php" method="POST" class="reusable-form">
                    <div class="form-section">
                        <div class="form-row">
                            <div class="form-group full-width">
                                <label for="title">Title <span class="required">*</span></label>
                                <input type="text" name="title" id="title" placeholder="Enter announcement title" required/>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group full-width">
                                <label for="content">Content <span class="required">*</span></label>
                                <textarea name="content" id="content" rows="8" placeholder="Enter announcement details" required></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button name="add" type="submit" class="btn btn-success">Create Announcement</button>
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