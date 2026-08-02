<?php
include __DIR__ . "/../includes/conn.php";
session_start();

$scholarships_sql = "SELECT * FROM scholarship_program ORDER BY is_active DESC, name ASC";
$scholarships_stmt = $conn->prepare($scholarships_sql);
$scholarships_stmt->execute();
$scholarships_result = $scholarships_stmt->get_result();

// delete scholarship program process
if(isset($_POST['delete']) && isset($_POST['scholarship_program_id'])) {
    $scholarship_id = (int) $_POST['scholarship_program_id'];

    // delete scholar list of the scholarship program
    $delete_sql = "DELETE FROM scholarship_program WHERE id = ?";
    $delete_list_sql = "DELETE FROM scholar_info WHERE scholarship_program_id = ?";

    $delete_stmt = $conn->prepare($delete_sql);
    $delete_list_stmt = $conn->prepare($delete_list_sql);

    $delete_stmt->bind_param('i', $scholarship_id);
    $delete_list_stmt->bind_param('i', $scholarship_id);

    if($delete_stmt->execute() && $delete_list_stmt->execute()) {
        $_SESSION['success'] = "Scholarship Program and its scholars deleted successfully";
        header("Location:" . $_SERVER['PHP_SELF']);
        exit();
    } else {
        $_SESSION['fail'] = " Failed to remove scholarship program and its scholar list";
        header("Location:" . $_SERVER['PHP_SELF']);
        exit();
    }
}

?>

<!DOCTYPE html>
<html>
<head>
    <title>Scholarship Programs - PIT ScholarshipHub</title>
    <link rel="stylesheet" href="../assets/css/scholarship_program.css?v=<?= filemtime('../assets/css/scholarship_program.css') ?>">
    <link rel="icon" href="../assets/images/PIT%20LOGO.png">
</head>
<body>
    <?php 
    // success/fail session variables used by the process to send success/fail messages
    if(isset($_SESSION['success'])) {
        echo "<script>alert('" . $_SESSION['success'] . "')</script>";
        unset($_SESSION['success']);
    } else if(isset($_SESSION['fail'])) {
        echo "<script>alert('" . $_SESSION['fail'] . "')</script>";
        unset($_SESSION['fail']);
    }
    ?>

    <!-- check if logged in or not -->
    <?php if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') :?>
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
                        <p>Scholarship Programs</p>
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
                    <li><a href="dashboard.php">Dashboard</a></li>
                    <li><a href="scholar_list.php">Scholars</a></li>
                    <li><a href="announcements.php">Announcements</a></li>
                    <li><a href="scholarship_program.php" class="active">Scholarship Programs</a></li>
                    <li><a href="upload_excel.php">Import Excel</a></li>
                    <li><a href="export_scholars.php">Export Excel</a></li>
                </ul>
            </nav>

            <!-- Page Content -->
            <div class="page-content">
                <!-- Page Header -->
                <div class="page-header">
                    <h2>Scholarship Programs</h2>
                    <div class="page-actions">
                        <a href="add_scholarship.php" class="btn btn-success">Add Scholarship Program</a>
                    </div>
                </div>

                <!-- list of scholarship programs -->
                <?php if($scholarships_result->num_rows > 0) : ?>
                    <div class="programs-grid">
                        <?php while($row = $scholarships_result->fetch_assoc()) : ?>
                            <div class="program-card <?= $row['is_active'] == 1 ? "status-active" : "status-inactive" ?>">
                                <div class="program-header">
                                    <h3><?= htmlspecialchars($row['name']) ?></h3>
                                    <span class="program-status <?= $row['is_active'] == 1 ? 'active' : 'inactive' ?>">
                                        <?= $row['is_active'] == 1 ? 'Active' : 'Inactive' ?>
                                    </span>
                                </div>
                                <div class="program-description">
                                    <p><?= htmlspecialchars($row['description']) ?></p>
                                </div>
                                <div class="program-code">
                                    <strong>Code:</strong> <?= htmlspecialchars($row['code']) ?>
                                </div>
                                <div class="program-actions">
                                    <a href="scholarship_scholar_list.php?id=<?= $row['id'] ?>" class="btn-sm btn-view">View Scholars</a>
                                    <a href="edit_scholarship_program.php?id=<?= $row['id'] ?>" class="btn-sm btn-edit">Edit</a>
                                    <form action="<?= $_SERVER['PHP_SELF'] ?>" method="POST" style="display: inline;">
                                        <input type="hidden" name="scholarship_program_id" value="<?= $row['id'] ?>"/>
                                        <button type="submit" name="delete" class="btn-sm btn-delete" onclick="return confirm('Deleting a Scholarship Program also deletes its list of scholars. Proceed?')">Delete</button>
                                    </form>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <!-- if there are no scholarship programs, show this -->
                    <div class="no-data">
                        <p>No scholarship programs found.</p>
                        <a href="add_scholarship.php" class="btn btn-success" style="margin-top: 10px;">Create your first scholarship program</a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Footer -->
            <div class="footer">
                © 2026 PIT ScholarshipHub. All rights reserved.
            </div>
        </div>
    <?php endif; ?>
</body>
</html>