<?php 
include __DIR__ . "/../includes/conn.php";
include __DIR__ . "/../includes/check_permission.php";
session_start();

// Ensure user is logged in
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'staff')) {
    $_SESSION['fail'] = "You are not logged in!";
    header("Location: ../portal.php");
    exit();    
}

if ($_SESSION['role'] !== 'admin') {
    checkPageAccess($conn, $_SESSION['user_id'], 'scholarship_programs');
}

$scholarship_id = (int) ($_GET['id'] ?? 0);
$scholarship_name = "";
$school_year = "";

// 1. Fetch scholarship program details first to get name and target school year
$scholarship_name_sql = "SELECT name, school_year FROM scholarship_program WHERE id = ?";
$scholarship_name_stmt = $conn->prepare($scholarship_name_sql);
$scholarship_name_stmt->bind_param("i", $scholarship_id);

if ($scholarship_name_stmt->execute()) {
    $scholarship_name_result = $scholarship_name_stmt->get_result();
    if ($sp_row = $scholarship_name_result->fetch_assoc()) {
        $scholarship_name = $sp_row['name'];
        $school_year = $sp_row['school_year'];
    } else {
        $_SESSION['fail'] = "Scholarship record not found.";
        header("Location: scholarship_program.php");
        exit();
    }
} else {
    $_SESSION['fail'] = "Failed to load scholarship details.";
    header("Location: scholarship_program.php");
    exit();
}
$scholarship_name_stmt->close();

// Archive scholar process
if (isset($_POST['archive']) && isset($_POST['scholar_id'])) {
    $scholar_id = (int) ($_POST['scholar_id'] ?? 0);

    $archive_sql = "UPDATE scholar_info SET is_archived = 1 WHERE id = ?";
    $archive_stmt = $conn->prepare($archive_sql);
    $archive_stmt->bind_param('i', $scholar_id);
    
    if ($archive_stmt->execute()) {
        $_SESSION['success'] = "Scholar Archived";
    } else {
        $_SESSION['fail'] = "Failed to archive scholar";
    }
    
    header("Location: scholarship_scholar_list.php?id=$scholarship_id");
    exit();
}

// 2. Query scholar list matching the scholarship program and school year
$clean_sy = str_replace(' ', '', $school_year);

$sql = "SELECT
            scholar_info.id AS scholar_id,
            student_id, 
            last_name, 
            given_name, 
            middle_name, 
            ext_name,
            academic_status,
            scholar_info.school_year,
            scholar_info.semester,
            program.name AS program_name
        FROM scholar_info 
        INNER JOIN program ON scholar_info.program_id = program.id
        WHERE scholar_info.scholarship_program_id = ? 
          AND scholar_info.is_archived = 0 
          AND REPLACE(scholar_info.school_year, ' ', '') = ?
        ORDER BY scholar_info.last_name ASC, scholar_info.given_name ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("is", $scholarship_id, $clean_sy);
$stmt->execute();
$result = $stmt->get_result();
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

    <div class="container">

        <?php include __DIR__ . "/../includes/navigation.php"; ?>

        <!-- Page Content -->
        <div class="page-content">
            <!-- Page Header with Top Right Back Button -->
            <div class="page-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                <h2>
                    <?= htmlspecialchars($scholarship_name) ?> Scholar List 
                    <small style="font-size: 16px; color: #555;">(SY <?= htmlspecialchars($school_year) ?>)</small>
                </h2>

                <div class="page-actions">
                    <a href="scholarship_program.php" class="btn btn-secondary">← Back to Programs</a>
                </div>
            </div>

            <!-- Scholar List Table -->
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Student ID</th>
                            <th>Name</th>
                            <th>Program / Course</th>
                            <th>Academic Status</th>
                            <th>Term Snapshot</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result->num_rows > 0) : ?>
                            <?php while ($row = $result->fetch_assoc()) : 
                                // Format Extension Name cleanly (hides 'N/A', 'NONE', '0', 'NULL', or empty)
                                $raw_ext = trim($row['ext_name'] ?? '');
                                $clean_ext = (empty($raw_ext) || in_array(strtoupper($raw_ext), ['N/A', 'NONE', '0', 'NULL'])) ? '' : ' ' . $raw_ext;
                                
                                $middle = !empty(trim($row['middle_name'] ?? '')) ? ' ' . trim($row['middle_name']) : '';
                                $full_name = htmlspecialchars($row['last_name']) . ", " . htmlspecialchars($row['given_name']) . htmlspecialchars($middle) . htmlspecialchars($clean_ext);
                            ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($row['student_id']) ?></strong></td>
                                    <td><?= $full_name ?></td>
                                    <td><?= htmlspecialchars($row['program_name']) ?></td>
                                    <td>
                                        <span class="status-badge">
                                            <?= htmlspecialchars($row['academic_status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            <?= htmlspecialchars($row['school_year']) ?> (<?= htmlspecialchars($row['semester']) ?>)
                                        </small>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <!-- View scholar info button -->
                                            <a href="scholar_profile.php?scholar_id=<?= $row['scholar_id'] ?>" class="btn-sm btn-view">View Profile</a>
                                            
                                            <?php if (hasPermission($conn, $_SESSION['user_id'], 'scholars', 'archive')) : ?>
                                                <form action="<?= $_SERVER['PHP_SELF'] ?>?id=<?= $scholarship_id ?>" method="POST" style="display: inline;">
                                                    <input type="hidden" name="scholar_id" value="<?= $row['scholar_id'] ?>"/>
                                                    <button type="submit" name="archive" class="btn-sm btn-archive" onclick="return confirmAction(event, 'Are you sure you want to archive this scholar snapshot?', 'Archive Scholar', 'archive')">Archive</button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else : ?>
                            <!-- Empty State -->
                            <tr>
                                <td colspan="6" class="no-data">No scholars found under this program for SY <?= htmlspecialchars($school_year) ?>.</td>
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
</body>
</html>