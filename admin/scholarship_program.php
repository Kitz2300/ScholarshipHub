<?php 
include __DIR__ . "/../includes/conn.php";
include __DIR__ . "/../includes/school_year_helper.php";
include '../includes/check_permission.php';
session_start();

if ($_SESSION['role'] !== 'admin') {
    checkPageAccess($conn, $_SESSION['user_id'], 'scholarship_programs');
}

// Handle filter submissions (School Year & Archive Status)
if (isset($_POST['apply_filters'])) {
    if (isset($_POST['school_year'])) {
        setSelectedSchoolYear($_POST['school_year']);
    }
    if (isset($_POST['archive_status'])) {
        $_SESSION['program_archive_filter'] = $_POST['archive_status'];
    }
    header("Location: scholarship_program.php");
    exit();
}

$selected_year = getSelectedSchoolYear();
$available_years = getScholarshipSchoolYears($conn);

// Fallback if selected year has no data
if (!empty($available_years) && !in_array($selected_year, $available_years)) {
    $selected_year = $available_years[0];
    setSelectedSchoolYear($selected_year);
    header("Location: scholarship_program.php");
    exit();
}

// Archive status filter (Default: '0' for Active programs)
$archive_filter = $_SESSION['program_archive_filter'] ?? '0';

// Archive/Unarchive Scholarship Program Process
if (isset($_POST['archive']) && isset($_POST['scholarship_program_id'])) {
    $scholarship_id = (int) $_POST['scholarship_program_id'];
    
    $archive_sql = "UPDATE scholarship_program SET is_archived = NOT is_archived WHERE id = ?";
    $archive_scholars_sql = "UPDATE scholar_info SET is_archived = NOT is_archived WHERE scholarship_program_id = ?";

    $archive_stmt = $conn->prepare($archive_sql);
    $archive_scholars_stmt = $conn->prepare($archive_scholars_sql);

    $archive_stmt->bind_param('i', $scholarship_id);
    $archive_scholars_stmt->bind_param('i', $scholarship_id);

    if (!$archive_stmt->execute() || !$archive_scholars_stmt->execute()) {
        $_SESSION['fail'] = "Failed to archive records.";
    }
    
    header("Location: scholarship_program.php");
    exit();
}

// Build query based on archive filter
if ($archive_filter === '1') {
    $scholarships_sql = "SELECT * FROM scholarship_program WHERE school_year = ? AND is_archived = 1 ORDER BY name ASC";
} elseif ($archive_filter === 'all') {
    $scholarships_sql = "SELECT * FROM scholarship_program WHERE school_year = ? ORDER BY name ASC";
} else { // Default: Active only
    $scholarships_sql = "SELECT * FROM scholarship_program WHERE school_year = ? AND is_archived = 0 ORDER BY name ASC";
}

$scholarships_stmt = $conn->prepare($scholarships_sql);
$scholarships_stmt->bind_param('s', $selected_year);
$scholarships_stmt->execute();
$scholarships_result = $scholarships_stmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Scholarship Programs - PIT ScholarshipHub</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/scholarship_program.css?v=<?= filemtime('../assets/css/scholarship_program.css') ?>">
    <link rel="icon" href="../assets/images/PIT%20LOGO.png">
</head>
<body>
    <?php 
    if (isset($_SESSION['success'])) {
        echo "<script>alert('" . htmlspecialchars($_SESSION['success'], ENT_QUOTES) . "')</script>";
        unset($_SESSION['success']);
    } else if (isset($_SESSION['fail'])) {
        echo "<script>alert('" . htmlspecialchars($_SESSION['fail'], ENT_QUOTES) . "')</script>";
        unset($_SESSION['fail']);
    }
    ?>

    <?php if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'staff')) : ?>
        <script>alert('You are not logged in')</script>
        <script>window.location.href='../portal.php'</script>
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

            <?php include __DIR__ . "/../includes/navigation.php"; ?>

            <!-- Page Content -->
            <div class="page-content">
                <!-- Page Header -->
                <div class="page-header">
                    <h2>Scholarship Programs</h2>
                    
                    <!-- Filters Form -->
                    <form action="scholarship_program.php" method="POST" class="school-year-selector">
                        <label for="school_year"><strong>School Year:</strong></label>
                        <select name="school_year" id="school_year">
                            <?php if (!empty($available_years)) : ?>
                                <?php foreach ($available_years as $year) : ?>
                                    <option value="<?= htmlspecialchars($year) ?>" <?= $year == $selected_year ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($year) ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <option value="">No School Year</option>
                            <?php endif; ?>
                        </select>

                        <label for="archive_status" style="margin-left: 10px;"><strong>Status:</strong></label>
                        <select name="archive_status" id="archive_status">
                            <option value="0" <?= $archive_filter === '0' ? 'selected' : '' ?>>Active</option>
                            <option value="1" <?= $archive_filter === '1' ? 'selected' : '' ?>>Archived</option>
                            <option value="all" <?= $archive_filter === 'all' ? 'selected' : '' ?>>All Programs</option>
                        </select>

                        <button type="submit" name="apply_filters" class="btn btn-sm btn-primary">Apply</button>
                    </form>
                </div>

                <div class="action-row">
                    <div class="action-row-right">
                        <?php if (hasPermission($conn, $_SESSION['user_id'], 'scholarship_programs', 'add')) : ?>
                            <a href="add_scholarship.php" class="btn btn-success">Add Scholarship Program</a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- List of Scholarship Programs -->
                <?php if ($scholarships_result->num_rows > 0) : ?>
                    <div class="programs-grid">
                        <?php while ($row = $scholarships_result->fetch_assoc()) : 
                            $is_archived = (int) $row['is_archived'] === 1;
                            $card_status_class = $is_archived ? "status-inactive" : "status-active";
                            $badge_class = $is_archived ? 'inactive' : 'active';
                            $badge_text = $is_archived ? 'Archived' : 'Active';
                        ?>
                            <div class="program-card <?= $card_status_class ?>">
                                <div class="program-header">
                                    <h3><?= htmlspecialchars($row['name']) ?></h3>
                                    <span class="program-status <?= $badge_class ?>">
                                        <?= $badge_text ?>
                                    </span>
                                </div>
                                <div class="program-description">
                                    <p><?= htmlspecialchars($row['description']) ?></p>
                                </div>
                                <div class="program-code">
                                    <strong>Code:</strong> <?= htmlspecialchars($row['code']) ?>
                                </div>
                                <?php if ($row['min_gwa_req'] > 0) : ?>
                                    <div class="program-code">
                                        <strong>Required GWA:</strong> <?= htmlspecialchars($row['min_gwa_req']) ?>
                                    </div>
                                <?php endif; ?>
                                <div class="program-code">
                                    <strong>Type:</strong> <?= htmlspecialchars($row['scholarship_type']) ?>
                                </div>
                                <div class="program-code">
                                    <strong>Allowance:</strong> ₱<?= htmlspecialchars(number_format((float)$row['pay_amount'], 2)) ?>
                                </div>
                                <div class="program-actions">
                                    <a href="scholarship_scholar_list.php?id=<?= $row['id'] ?>" class="btn-sm btn-view">View Scholars</a>
                                    
                                    <?php if (hasPermission($conn, $_SESSION['user_id'], 'scholarship_programs', 'edit')) : ?>
                                        <a href="edit_scholarship_program.php?id=<?= $row['id'] ?>" class="btn-sm btn-edit">Edit</a>
                                    <?php endif; ?>
                                    
                                    <?php if (hasPermission($conn, $_SESSION['user_id'], 'scholarship_programs', 'archive')) : ?>
                                        <form action="scholarship_program.php" method="POST" style="display: inline;">
                                            <input type="hidden" name="scholarship_program_id" value="<?= $row['id'] ?>"/>
                                            <?php if (!$is_archived) : ?>
                                                <button type="submit" name="archive" class="btn-sm btn-archive" onclick="return confirm('Archiving Scholarship Programs also archives its list of scholars. Proceed?')">Archive</button>
                                            <?php else : ?>
                                                <button type="submit" name="archive" class="btn-sm btn-archive" onclick="return confirm('Unarchiving Scholarship Programs also unarchives its list of scholars. Proceed?')">Unarchive</button>
                                            <?php endif; ?>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else : ?>
                    <div class="no-data">
                        <p>No scholarship programs found for the selected filters.</p>
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