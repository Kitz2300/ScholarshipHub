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

if (!empty($available_years) && !in_array($selected_year, $available_years)) {
    $selected_year = $available_years[0];
    setSelectedSchoolYear($selected_year);
    header("Location: scholarship_program.php");
    exit();
}

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

    if ($archive_stmt->execute() && $archive_scholars_stmt->execute()) {
        $_SESSION['success'] = "Program status updated successfully.";
    } else {
        $_SESSION['fail'] = "Failed to archive records.";
    }
    
    header("Location: scholarship_program.php");
    exit();
}

// Flexible School Year matching to handle whitespace variations ("2025 - 2026" vs "2025-2026")
$clean_year = str_replace(' ', '', $selected_year);

$base_sql = "SELECT sp.*, p.name AS provider_name, p.code AS provider_code 
            FROM scholarship_program sp 
            LEFT JOIN providers p ON sp.provider_id = p.id 
            WHERE REPLACE(sp.school_year, ' ', '') = ?";

if ($archive_filter === '1') {
    $scholarships_sql = $base_sql . " AND sp.is_archived = 1 ORDER BY sp.name ASC";
} elseif ($archive_filter === 'all') {
    $scholarships_sql = $base_sql . " ORDER BY sp.name ASC";
} else {
    $scholarships_sql = $base_sql . " AND sp.is_archived = 0 ORDER BY sp.name ASC";
}

$scholarships_stmt = $conn->prepare($scholarships_sql);
$scholarships_stmt->bind_param('s', $clean_year);
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
    <link rel="stylesheet" href="../assets/css/modals.css">
</head>
<body>
    <?php include __DIR__ . '/../includes/modal_alerts.php'; ?>
    <?php if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'staff')) : 
        $_SESSION['fail'] = "You are not logged in!";
        header("Location: ../portal.php");
        exit();    
    else : ?>
        <div class="container">

            <?php include __DIR__ . "/../includes/navigation.php"; ?>

            <div class="page-content">
                <div class="page-header">
                    <h2>Scholarship Management</h2>
                    
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

                <div class="sub-nav">
                    <a href="scholarship_program.php" class="sub-nav-item active">Scholarship Programs</a>
                    <a href="providers.php" class="sub-nav-item">Funding Providers</a>
                </div>

                <div class="action-row">
                    <div class="action-row-right">
                        <?php if (hasPermission($conn, $_SESSION['user_id'], 'scholarship_programs', 'add')) : ?>
                            <a href="add_scholarship.php" class="btn btn-success">Add Scholarship Program</a>
                        <?php endif; ?>
                    </div>
                </div>

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
                                <div class="program-code">
                                    <strong>Funding Provider:</strong> 
                                    <?= !empty($row['provider_name']) ? htmlspecialchars($row['provider_name']) . ' (' . htmlspecialchars($row['provider_code']) . ')' : 'Not Specified' ?>
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
                                            <input type="hidden" name="archive" value="1"/>
                                            <input type="hidden" name="scholarship_program_id" value="<?= $row['id'] ?>"/>
                                            
                                            <?php if (!$is_archived) : ?>
                                                <button type="submit" class="btn-sm btn-archive" onclick="return confirmAction(event, 'Archiving Scholarship Programs also archives its list of scholars. Proceed?', 'Archive', 'archive')">Archive</button>
                                            <?php else : ?>
                                                <button type="submit" class="btn-sm btn-archive" onclick="return confirmAction(event, 'Unarchiving Scholarship Programs also unarchives its list of scholars. Proceed?', 'Unarchive', 'primary')">Unarchive</button>
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

            <div class="footer">
                © 2026 PIT ScholarshipHub. All rights reserved.
            </div>
        </div>
    <?php endif; ?>
</body>
</html>