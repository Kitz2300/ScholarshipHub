<?php 
include __DIR__ . "/../includes/conn.php";
session_start();

// get scholar id and initialize name
$scholar_id = isset($_GET['scholar_id']) ? (int) $_GET['scholar_id'] : 0;
$name = "";

if($scholar_id === 0) {
    $_SESSION['fail'] = "No scholar selected.";
    header("Location: scholar_list.php");
    exit();
}

$scholar_sql = "SELECT last_name, given_name FROM scholar_info WHERE id = ?";
$scholar_stmt = $conn->prepare($scholar_sql);
$scholar_stmt->bind_param('i', $scholar_id);
$scholar_stmt->execute();
$scholar_result = $scholar_stmt->get_result();

if($scholar_result->num_rows > 0) {
    if($row = $scholar_result->fetch_assoc()) {
        $name = $row['last_name'] . ", " . $row['given_name'];
    }
} else {
    $_SESSION['fail'] = "Scholar does not exist!";
    header("Location: scholar_list.php");
    exit();
}

// Delete process
if(isset($_POST['remove']) && isset($_POST['record_id'])) {
    $record_id = (int) $_POST['record_id'];

    if($record_id === 0) {
        $_SESSION['fail'] = "Record does not exist.";
        header("Location: academic_performance.php?scholar_id=" . $scholar_id);
        exit();
    }

    $delete_sql = "DELETE FROM academic_performance WHERE id = ?";
    $delete_stmt = $conn->prepare($delete_sql);
    $delete_stmt->bind_param('i', $record_id);
    
    if($delete_stmt->execute()) {
        $_SESSION['success'] = "Academic record removed successfully!";
    } else {
        $_SESSION['fail'] = "Failed to remove academic record.";
    }
    header("Location: academic_performance.php?scholar_id=" . $scholar_id);
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Academic Performance - PIT ScholarshipHub</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/academic_performance.css?v=<?= filemtime('../assets/css/academic_performance.css') ?>">
    <link rel="icon" href="../assets/images/PIT%20LOGO.png">
</head>
<body>
    <?php 
    // success/fail messages
    if(isset($_SESSION['success'])) {
        echo "<script>alert('" . $_SESSION['success'] . "')</script>";
        unset($_SESSION['success']);
    } else if(isset($_SESSION['fail'])) {
        echo "<script>alert('" . $_SESSION['fail'] . "')</script>";
        unset($_SESSION['fail']);
    }
    ?>
    
    <?php if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') : ?>
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
                        <p>Academic Performance</p>
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
                        <a href="#" class="dropdown-toggle active">Records <div class="arrow-down">▼</div></a>
                        <ul class="dropdown-menu">
                            <li><a href="scholar_list.php" class="active">Scholars</a></li>
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
                    <li><a href="announcements.php">Announcements</a></li>

                    <!-- Program / College Management -->
                    <li><a href="program_management.php">Program/College Management</a></li>
                </ul>
            </nav>

            <!-- Page Content -->
            <div class="page-content">
                <!-- Page Header -->
                <div class="page-header">
                    <h2>Academic Performance</h2>
                    <div class="scholar-name">
                        <span class="label">Scholar:</span>
                        <span class="name"><?= htmlspecialchars($name) ?></span>
                    </div>
                </div>

                <!-- Actions -->
                <div class="action-bar">
                    <a href="scholar_profile.php?scholar_id=<?= $scholar_id ?>" class="btn btn-secondary">← Back to Profile</a>
                    <a href="add_academic_performance.php?scholar_id=<?= $scholar_id ?>" class="btn btn-success">+ Add Record</a>
                </div>

                <!-- Academic Records Table -->
                <div class="table-wrapper">
                    <?php 
                    $academic_sql = "SELECT * FROM academic_performance WHERE scholar_id = ? ORDER BY school_year DESC, semester DESC";
                    $academic_stmt = $conn->prepare($academic_sql);
                    $academic_stmt->bind_param('i', $scholar_id);
                    $academic_stmt->execute();
                    $academic_result = $academic_stmt->get_result();?>

                    <?php if($academic_result->num_rows > 0) : ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Semester</th>
                                    <th>GWA</th>
                                    <th>Units</th>
                                    <th>Year Level</th>
                                    <th>School Year</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($row = $academic_result->fetch_assoc()) : ?>
                                    <tr>
                                        <td><?= htmlspecialchars($row['semester']) ?></td>
                                        <td><?= htmlspecialchars($row['gwa']) ?></td>
                                        <td><?= htmlspecialchars($row['units']) ?></td>
                                        <td><?= htmlspecialchars($row['year_level']) ?></td>
                                        <td><?= htmlspecialchars($row['school_year']) ?></td>
                                        <td>
                                            <div class="action-buttons">
                                                <a href="edit_academic_performance.php?record_id=<?= $row['id'] ?>&scholar_id=<?= $scholar_id ?>" class="btn-sm btn-edit">Edit</a>
                                                <form action="<?= $_SERVER['PHP_SELF'] ?>?scholar_id=<?= $scholar_id ?>" method="POST" style="display: inline;">
                                                    <input type="hidden" name="record_id" value="<?= $row['id'] ?>">
                                                    <button type="submit" name="remove" class="btn-sm btn-delete" onclick="return confirm('Delete this academic record?')">Delete</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else : ?>
                        <div class="no-data">
                            <p>No academic records found for this scholar.</p>
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