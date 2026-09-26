<?php 
include __DIR__ . "/../includes/conn.php";
include __DIR__ . "/../includes/check_permission.php";
session_start();

if($_SESSION['user_id'] !== 'admin') {
    checkPageAccess($conn, $_SESSION['user_id'], 'scholarship_programs');
}

$scholarship_id = (int) ($_GET['id'] ?? 0);
$scholarship_name = "";

// delete scholarship process
if(isset($_POST['archive']) && isset($_POST['scholar_id'])) {
    $scholar_id = (int) ($_POST['scholar_id'] ?? 0);

    $archive_sql = "UPDATE scholar_info SET is_archived = 1 WHERE id = ?";
    $archive_stmt = $conn->prepare($archive_sql);
    $archive_stmt->bind_param('i', $scholar_id);
    
    // condition if query is successful or not
    if($archive_stmt->execute()) {
        $_SESSION['success'] = "Scholar Archived";
        header("Location: scholarship_scholar_list.php?id=$scholarship_id");
        exit();
    } else {
        $_SESSION['fail'] = "Failed to archive scholar";
        header("Location: scholarship_scholar_list.php?id=$scholarship_id");
        exit();
    }
}

// scholar list query
$sql = "SELECT
            scholar_info.id AS scholar_id,
            student_id, 
            last_name, 
            given_name, 
            middle_name, 
            ext_name,
            academic_status,
            program.name AS program_name
            FROM scholar_info 
            INNER JOIN program ON scholar_info.program_id = program.id
            WHERE scholarship_program_id = ? AND scholar_info.is_archived = 0
            ORDER BY scholar_info.last_name ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $scholarship_id);
$stmt->execute();
$result = $stmt->get_result();

// scholar name query for display
$scholarship_name_sql = "SELECT name, school_year FROM scholarship_program WHERE id = ?";
$scholarship_name_stmt = $conn->prepare($scholarship_name_sql);
$scholarship_name_stmt->bind_param("i", $scholarship_id);

if($scholarship_name_stmt->execute()) {
    $scholarship_name_result = $scholarship_name_stmt->get_result();
    if($row = $scholarship_name_result->fetch_assoc()) {
        $scholarship_name = $row['name'];
        $school_year = $row['school_year'];
    }
} else {
    $_SESSION['fail'] = "Scholarship record not found.";
    header("Location: scholarship_program.php");
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title><?= htmlspecialchars($scholarship_name) ?> Scholar List - PIT ScholarshipHub</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/scholarship_scholar_list.css?v=<?= filemtime('../assets/css/scholarship_scholar_list.css') ?>">
    <link rel="stylesheet" href="../assets/css/modals.css?v=<?= filemtime('../assets/css/modals.css') ?>">
    <link rel="icon" href="../assets/images/PIT%20LOGO.png">
</head>
<body>
    <?php include __DIR__ . "/../includes/modal_alerts.php"; ?>

    <!-- check if logged in or not -->
    <?php if(!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'staff')) :
        $_SESSION['fail'] = "You are no logged in!";
        header("Location: ../portal.php");
        exit();    
    else : ?>
        <div class="container">

            <?php include __DIR__ . "/../includes/navigation.php"; ?>

            <!-- Page Content -->
            <div class="page-content">
                <!-- Page Header -->
                <div class="page-header">
                    <h2><?= htmlspecialchars($scholarship_name) ?> Scholar List (<?= $school_year ?>)</h2>
                    <div class="page-actions">
                        <a href="scholarship_program.php" class="btn btn-secondary">← Back to Programs</a>
                    </div>
                </div>

                <!-- scholar list table -->
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Student ID</th>
                                <th>Name</th>
                                <th>Program</th>
                                <th>Academic Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($result->num_rows > 0) : ?>
                                <?php while($row = $result->fetch_assoc()) : ?>
                                    <tr>
                                        <td><?= htmlspecialchars($row['student_id']) ?></td>
                                        <td><?= htmlspecialchars($row['last_name']) ?>, <?= htmlspecialchars($row['given_name']) ?> <?= htmlspecialchars($row['middle_name']) ?> <?= htmlspecialchars($row['ext_name']) ?></td>
                                        <td><?= htmlspecialchars($row['program_name']) ?></td>
                                        <td><?= htmlspecialchars($row['academic_status']) ?></td>
                                        <td>
                                            <div class="action-buttons">
                                                <!-- view scholar info button -->
                                                <a href="scholar_profile.php?scholar_id=<?= $row['scholar_id'] ?>" class="btn-sm btn-view">View</a>
                                                
                                                <form action="<?= $_SERVER['PHP_SELF'] ?>?id=<?= $scholarship_id ?>" method="POST" style="display: inline;">
                                                    <!-- delete button -->
                                                    <input type="hidden" name="scholar_id" value="<?= $row['scholar_id'] ?>"/>
                                                    <?php if(hasPermission($conn, $_SESSION['user_id'], 'scholars', 'archive')) : ?>
                                                        <button type="submit" name="archive" class="btn-sm btn-archive" onclick="return confirm('Archive Scholar?')">Archive</button>
                                                    <?php endif; ?>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else : ?>
                                <!-- if list is blank display this -->
                                <tr>
                                    <td colspan="5" class="no-data">No scholars under this scholarship program.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
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