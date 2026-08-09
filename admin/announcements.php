<?php 
include __DIR__ . "/../includes/conn.php";
session_start();

// query all the announcements, active and not active alike
$announcement_sql = "SELECT
                        announcements.id AS announcement_id,
                        title, 
                        content, 
                        is_active,
                        created_at,
                        scholarship_users.given_name, 
                        scholarship_users.middle_name, 
                        scholarship_users.last_name,
                        scholarship_users.role
                    FROM 
                        announcements 
                    INNER JOIN 
                        scholarship_users ON announcements.user_id = scholarship_users.id
                    ORDER BY
                        is_active DESC,
                        created_at DESC
                    ";

$announcement_stmt = $conn->prepare($announcement_sql);
$announcement_stmt->execute();
$announcement_result = $announcement_stmt->get_result();

// delete announcement function
if(isset($_POST['change_status'])) {
    if(isset($_POST['announcement_id'])) {
        $announcement_id = (int) $_POST['announcement_id'];
        $change_sql = "UPDATE announcements SET is_active = NOT is_active WHERE id = ?";
        $change_stmt = $conn->prepare($change_sql);
        $change_stmt->bind_param("i", $announcement_id);
        
        // insert success/fail message to session variables to display success/fail message after reloading page
        if(!($change_stmt->execute())) {
            $_SESSION['fail'] = "Failed to change announcement status";
        }

        // reload page to display success/fail message and refresh announcements displayed
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
        
    }
}

if(isset($_POST['delete'])) {
    if(isset($_POST['announcement_id'])) {
        $announcement_id = (int) $_POST['announcement_id'];
        $delete_sql = "DELETE FROM announcements WHERE id = ?";
        $delete_stmt = $conn->prepare($delete_sql);
        $delete_stmt->bind_param('i', $announcement_id);
        
        if($delete_stmt->execute()) {
            $_SESSION['success'] = "Announcement deleted";
        } else {
            $_SESSION['fail'] = "Announcement failed to delete";
        }

        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}

?>

<!DOCTYPE html>
<html>
<head>
    <title>Announcements - PIT ScholarshipHub</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/announcements.css?v=<?= filemtime('../assets/css/announcements.css') ?>">
    <link rel="icon" href="../assets/images/PIT%20LOGO.png">
</head>
<body>
    <?php
    // success/fail session messages
    if(isset($_SESSION['success'])) {
        echo "<script>alert('" . htmlspecialchars($_SESSION['success']) . "')</script>";
        unset($_SESSION['success']);
    } else if(isset($_SESSION['fail'])) {
        echo "<script>alert('" . htmlspecialchars($_SESSION['fail']) . "')</script>";
        unset($_SESSION['fail']);
    }

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
                        <p>Announcements</p>
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
                    <li><a href="program_management.php">Program/College Management</a></li>
                </ul>
            </nav>

            <!-- Page Content -->
            <div class="page-content">
                <!-- Page Header -->
                <div class="page-header">
                    <h2>Announcements</h2>
                    <div class="page-actions">
                        <a href="add_announcement.php" class="btn btn-success">Add Announcement</a>
                    </div>
                </div>

                <!-- Announcements List -->
                <div class="announcements-list">
                    <?php if($announcement_result->num_rows > 0) : ?>
                        <?php while($row = $announcement_result->fetch_assoc()) : 
                            $title = $row['title'];
                            $content = $row['content'];
                            $is_active = $row['is_active'] == 1 ? "Active" : "Not Active";
                            $is_active_title = $row['is_active'] == 0 ? "Active" : "Not Active";
                            $is_active_class = $row['is_active'] == 1 ? "status-active" : "status-inactive";
                            $created_at = $row['created_at'];
                            $created_by = isset($row['middle_name']) ? $row['given_name'] . " " . $row['middle_name'] . " " . $row['last_name'] : $row['given_name'] . " " . $row['last_name'];
                            $role = $row['role'];
                        ?>
                            <div class="announcement-card <?= $is_active_class ?>">
                                <div class="announcement-header">
                                    <h3><?= htmlspecialchars($title) ?></h3>
                                    <span class="status-badge <?= $is_active_class ?>"><?= htmlspecialchars($is_active) ?></span>
                                </div>
                                <div class="announcement-meta">
                                    <span class="meta-item">Date Posted: <?= htmlspecialchars($created_at) ?></span>
                                    <span class="meta-item">Posted By: <?= htmlspecialchars($created_by) ?> (<?= htmlspecialchars($role) ?>)</span>
                                </div>
                                <div class="announcement-content">
                                    <p><?= htmlspecialchars($content, ENT_QUOTES, 'UTF-8') ?></p>
                                </div>
                                <div class="announcement-actions">
                                    <a href="edit_announcement.php?id=<?= $row['announcement_id'] ?>" class="btn-sm btn-edit">Edit</a>
                                    <form action="<?= $_SERVER['PHP_SELF'] ?>" method="POST" style="display: inline;">
                                        <input type="hidden" name="announcement_id" value="<?= $row['announcement_id'] ?>"/>
                                        <button type="submit" name="change_status" class="btn-sm btn-<?= $is_active_class ?>" onclick="return confirm('Change status of this announcement?')">Change Status to <?= $is_active_title ?></button>
                                    </form>
                                    <form action="<?= $_SERVER['PHP_SELF'] ?>" method="POST" style="display: inline;">
                                        <input type="hidden" name="announcement_id" value="<?= $row['announcement_id'] ?>"/>
                                        <button type="submit" name="delete" class="btn-sm btn-delete" onclick="return confirm('Delete announcement?')">Delete</button>
                                        <!-- <button type="submit" name="delete" class="btn-sm btn-delete" onclick="return confirm('Delete announcement?')"><img class="delete-ico" src="../assets/images/trash.png"></button> -->
                                    </form>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else : ?>
                        <div class="no-data">
                            <p>No announcements found.</p>
                            <a href="add_announcement.php" class="btn btn-success" style="margin-top: 10px;">Create your first announcement</a>
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