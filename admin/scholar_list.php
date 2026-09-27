<?php 
include __DIR__ . '/../includes/conn.php';
include '../includes/check_permission.php';
session_start();

if($_SESSION['role'] !== 'admin') {
    checkPageAccess($conn, $_SESSION['user_id'], 'scholars');
}

// ─── Process archive, search, filter, pagination ───

require_once __DIR__ . '/../includes/school_year_helper.php';

$selected_year = getSelectedSchoolYear();
$available_years = getAvailableSchoolYears($conn);

// ─── Archive / Unarchive scholar process ───
if(isset($_POST['archive'])) {
    if(!empty($_POST['scholar_id'])) {
        $scholar_id = (int)$_POST['scholar_id'];
        $archive_sql = "UPDATE scholar_info SET is_archived = NOT is_archived WHERE id = ?";
        $archive_stmt = $conn->prepare($archive_sql);
        $archive_stmt->bind_param("i", $scholar_id);

        if($archive_stmt->execute()) {
            $_SESSION['success'] = "Scholar status updated successfully!";
        } else {
            $_SESSION['fail'] = "Failed to update scholar status.";
        }

        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}

// ─── Search, filter, and pagination ───
$records_per_page = 15;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if($page < 1) $page = 1;
$offset = ($page - 1) * $records_per_page;

// Get filter parameters
$searched = isset($_GET['search']) ? trim($_GET['search']) : "";
$course_filter = isset($_GET['course_filter']) ? (int)$_GET['course_filter'] : 0;
$college_filter = isset($_GET['college_filter']) ? (int)$_GET['college_filter'] : 0;
$scholarship_filter = isset($_GET['scholarship_filter']) ? (int)$_GET['scholarship_filter'] : 0;

// Default academic status to "Enrolled" if not provided
$academic_status = isset($_GET['academic_status']) ? $_GET['academic_status'] : "Enrolled";

$flagged_filter = isset($_GET['flagged_filter']) ? (int)$_GET['flagged_filter'] : 0;
$school_year_filter = isset($_GET['school_year_filter']) ? $_GET['school_year_filter'] : null;
$archive_filter = isset($_GET['archive_filter']) ? $_GET['archive_filter'] : '0';

// ─── Default school year to latest available ───
if($school_year_filter === null && !empty($available_years)) {
    $school_year_filter = $available_years[0];
}

// ─── Build WHERE conditions ───
$where_condition = [];
$params = [];
$types = "";

// ─── Archive filter condition ───
if ($archive_filter === '1') {
    $where_condition[] = "scholar_info.is_archived = 1";
} elseif ($archive_filter === 'all') {
    // Show both active and archived scholars
} else { // Default: '0' for Active only
    $where_condition[] = "scholar_info.is_archived = 0";
}

// ─── Search condition ───
if(!empty($searched)) {
    $where_condition[] = "(scholar_info.student_id LIKE ? 
                          OR scholar_info.given_name LIKE ? 
                          OR scholar_info.last_name LIKE ? 
                          OR CONCAT_WS(', ', scholar_info.last_name, scholar_info.given_name, scholar_info.middle_name) LIKE ?
                          OR CONCAT_WS(', ', scholar_info.last_name, scholar_info.given_name) LIKE ?
                          OR CONCAT_WS(' ', scholar_info.last_name, scholar_info.given_name, scholar_info.middle_name) LIKE ?
                          OR CONCAT_WS(' ', scholar_info.last_name, scholar_info.given_name) LIKE ?
                          OR CONCAT(scholar_info.last_name, ', ', scholar_info.given_name, ' ', scholar_info.middle_name) LIKE ?
                          OR CONCAT(scholar_info.last_name, ', ', scholar_info.given_name) LIKE ?)";
    $like = "%$searched%";
    for($i = 0; $i < 9; $i++) {
        $params[] = $like;
    }
    $types .= "sssssssss";
}

// ─── Course / Program Filter ───
if($course_filter > 0) {
    $where_condition[] = "scholar_info.program_id = ?";
    $params[] = $course_filter;
    $types .= "i";
}

// ─── College Filter ───
if($college_filter > 0) {
    $where_condition[] = "p.college_id = ?";
    $params[] = $college_filter;
    $types .= "i";
}

// ─── Scholarship Program Filter ───
if($scholarship_filter > 0) {
    $where_condition[] = "scholar_info.scholarship_program_id = ?";
    $params[] = $scholarship_filter;
    $types .= "i";
}

// ─── Academic Status Filter (Default: Enrolled) ───
if (!empty($academic_status)) {
    $where_condition[] = "scholar_info.academic_status = ?";
    $params[] = $academic_status;
    $types .= "s";
}

// ─── Flagged Scholars Filter ───
if ($flagged_filter == 1) {
    $where_condition[] = "sp.min_gwa_req > 0";
    $where_condition[] = "ap.gwa IS NOT NULL";
    $where_condition[] = "ap.gwa > sp.min_gwa_req";
}

// ─── School Year Filter ───
if(!empty($school_year_filter)) {
    $where_condition[] = "scholar_info.school_year = ?";
    $params[] = $school_year_filter;
    $types .= "s";
}

$where_sql = !empty($where_condition) ? " AND " . implode(" AND ", $where_condition) : "";

// ─── Count total records ───
$count_sql = "SELECT COUNT(*) AS total
              FROM scholar_info
              INNER JOIN scholarship_program sp ON scholar_info.scholarship_program_id = sp.id 
              INNER JOIN program p ON scholar_info.program_id = p.id
              INNER JOIN college c ON p.college_id = c.id
              LEFT JOIN (
                  SELECT scholar_id, gwa,
                         ROW_NUMBER() OVER (
                             PARTITION BY scholar_id 
                             ORDER BY school_year DESC, 
                                      CASE 
                                          WHEN semester = 'Summer' THEN 3
                                          WHEN semester = '2nd Semester' THEN 2
                                          WHEN semester = '1st Semester' THEN 1
                                          ELSE 0
                                      END DESC, id DESC
                         ) AS rn
                  FROM academic_performance
              ) ap ON ap.scholar_id = scholar_info.id AND ap.rn = 1
              WHERE sp.is_archived = 0 
                AND sp.is_active = 1 
                $where_sql";

$count_stmt = $conn->prepare($count_sql);
if(!empty($params)) $count_stmt->bind_param($types, ...$params);
$count_stmt->execute();
$total_records = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_records / $records_per_page);

// ─── Prepare params for main query ───
$scholar_params = $params;
$scholar_types = $types . "ii";
$scholar_params[] = $records_per_page;
$scholar_params[] = $offset;

// ─── Fetch scholars ───
$scholar_sql = "SELECT 
                scholar_info.id, 
                scholar_info.student_id, 
                scholar_info.last_name, 
                scholar_info.given_name, 
                scholar_info.middle_name, 
                scholar_info.ext_name,
                scholar_info.year_level,
                scholar_info.academic_status,
                scholar_info.is_archived,
                sp.name AS scholarship_name, 
                p.name AS program_name,
                c.college_code AS college_name,
                sp.min_gwa_req,
                ap.gwa AS latest_gwa,
                CASE 
                    WHEN ap.gwa IS NOT NULL AND sp.min_gwa_req > 0 AND ap.gwa > sp.min_gwa_req 
                    THEN 1 
                    ELSE 0 
                END AS is_flagged
                FROM scholar_info
                INNER JOIN scholarship_program sp ON scholar_info.scholarship_program_id = sp.id 
                INNER JOIN program p ON scholar_info.program_id = p.id
                INNER JOIN college c ON p.college_id = c.id
                LEFT JOIN (
                    SELECT scholar_id, gwa,
                           ROW_NUMBER() OVER (
                               PARTITION BY scholar_id 
                               ORDER BY school_year DESC, 
                                        CASE 
                                            WHEN semester = 'Summer' THEN 3
                                            WHEN semester = '2nd Semester' THEN 2
                                            WHEN semester = '1st Semester' THEN 1
                                            ELSE 0
                                        END DESC, id DESC
                           ) AS rn
                    FROM academic_performance
                ) ap ON ap.scholar_id = scholar_info.id AND ap.rn = 1
                WHERE sp.is_archived = 0 
                    AND sp.is_active = 1  
                    $where_sql
                ORDER BY scholar_info.id DESC
                LIMIT ? OFFSET ?";

$scholar_stmt = $conn->prepare($scholar_sql);
if(!empty($scholar_params)) $scholar_stmt->bind_param($scholar_types, ...$scholar_params);
$scholar_stmt->execute();
$result = $scholar_stmt->get_result();

// ─── Dropdown data ───
$course_sql = "SELECT id, name FROM program";
$course_stmt = $conn->prepare($course_sql);
$course_stmt->execute();
$course_result = $course_stmt->get_result();

$college_sql = "SELECT id, college_name FROM college";
$college_stmt = $conn->prepare($college_sql);
$college_stmt->execute();
$college_result = $college_stmt->get_result();

$scholarship_sql = "SELECT id, name FROM scholarship_program WHERE is_archived = 0";
$scholarship_stmt = $conn->prepare($scholarship_sql);
$scholarship_stmt->execute();
$scholarship_result = $scholarship_stmt->get_result();

if($page > $total_pages && $total_pages > 0) {
    header("Location: ?page=" . $total_pages);
    exit();
}

// ─── Build URLs for clearing search and filters ───
$current_params = $_GET;

$clear_search_params = $current_params;
unset($clear_search_params['search']);
unset($clear_search_params['search_btn']);
$clear_search_url = !empty($clear_search_params) 
    ? $_SERVER['PHP_SELF'] . '?' . http_build_query($clear_search_params) 
    : $_SERVER['PHP_SELF'];

$clear_filters_params = $current_params;
unset($clear_filters_params['course_filter']);
unset($clear_filters_params['college_filter']);
unset($clear_filters_params['scholarship_filter']);
unset($clear_filters_params['academic_status']);
unset($clear_filters_params['flagged_filter']);
unset($clear_filters_params['archive_filter']);
$clear_filters_url = !empty($clear_filters_params) 
    ? $_SERVER['PHP_SELF'] . '?' . http_build_query($clear_filters_params) 
    : $_SERVER['PHP_SELF'];

$has_filters = ($course_filter != 0 || $college_filter != 0 || $scholarship_filter != 0 || 
                ($academic_status != "" && $academic_status != 'Enrolled') || $flagged_filter == 1 || $archive_filter !== '0');
?>

<!DOCTYPE html>
<html>
<head>
    <title>Scholar List - PIT ScholarshipHub</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/scholar_list.css?v=<?= filemtime('../assets/css/scholar_list.css') ?>">
    <link rel="icon" href="../assets/images/PIT%20LOGO.png">
    <link rel="stylesheet" href="../assets/css/modals.css">
</head>
<body>
    <!-- Alert Messages -->
    <?php include __DIR__ . '/../includes/modal_alerts.php'; ?>
    <?php if(!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'staff')) : 
        $_SESSION['fail'] = "You are no logged in!";
        header("Location: ../portal.php");
        exit();    
    else : ?>
        <div class="container">

            <?php include __DIR__ . '/../includes/navigation.php'; ?>   

            <!-- Page Content -->
            <div class="page-content">
                <div class="page-header">
                    <h2>Scholar List</h2>
                </div>

                <!-- Search & Filter -->
                <script src="../assets/js/toggle_filter.js"></script>
                <div class="search-filter">
                    <form action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" method="GET">
                        <div class="search-row">
                            <input type="search" name="search" placeholder="Search by name or ID..." value="<?= !empty($searched) ? htmlspecialchars($searched) : "" ?>"/>
                            <button type="submit" name="search_btn" class="btn btn-sm btn-primary">Search</button>
                            <?php if(!empty($searched)) : ?>
                                <a href="<?= htmlspecialchars($clear_search_url) ?>" class="btn btn-sm btn-clear-search">Clear Search</a>
                            <?php endif; ?>
                            <button type="button" class="btn btn-sm btn-info toggle-filters">Filters ▼</button>
                        </div>
                        <div class="filter-row<?= $has_filters ? ' open' : '' ?>" id="filter-row">
                            <select name="course_filter" style="max-width: 240px;">
                                <option value="0" <?= $course_filter == 0 ? 'selected' : '' ?>>All Programs</option>
                                <?php 
                                $course_result->data_seek(0);
                                while($row = $course_result->fetch_assoc()) {
                                    $selected = ($course_filter == $row['id']) ? 'selected' : ''; ?>
                                    <option value="<?= $row['id'] ?>" <?= $selected ?>> <?= htmlspecialchars($row['name']) ?> </option>
                                <?php } ?>
                            </select>
                            <select name="college_filter" style="max-width: 240px;">
                                <option value="0" <?= $college_filter == 0 ? 'selected' : '' ?>>All Colleges</option>
                                <?php 
                                $college_result->data_seek(0);
                                while($row = $college_result->fetch_assoc()) {
                                    $selected = ($college_filter == $row['id']) ? 'selected' : ''; ?>
                                    <option value="<?= $row['id'] ?>" <?= $selected ?>> <?= htmlspecialchars($row['college_name']) ?> </option>
                                <?php } ?>
                            </select>
                            <select name="scholarship_filter" style="max-width: 240px;">
                                <option value="0" <?= $scholarship_filter == 0 ? 'selected' : '' ?>>All Scholarships</option>
                                <?php 
                                $scholarship_result->data_seek(0);
                                while($row = $scholarship_result->fetch_assoc()) {
                                    $selected = ($scholarship_filter == $row['id']) ? 'selected' : ''; ?>
                                    <option value="<?= $row['id'] ?>" <?= $selected ?>> <?= htmlspecialchars($row['name']) ?> </option>
                                <?php } ?>
                            </select>
                            <select name="academic_status">
                                <option value="Enrolled" <?= $academic_status == "Enrolled" ? 'selected' : '' ?>>Enrolled</option>
                                <option value="Not Enrolled" <?= $academic_status == "Not Enrolled" ? 'selected' : '' ?>>Not Enrolled</option>
                                <option value="Transferred" <?= $academic_status == "Transferred" ? 'selected' : '' ?>>Transferred</option>
                                <option value="Graduated" <?= $academic_status == "Graduated" ? 'selected' : '' ?>>Graduated</option>
                                <option value="Dropped" <?= $academic_status == "Dropped" ? 'selected' : '' ?>>Dropped</option>
                                <option value="Leave of Absence" <?= $academic_status == "Leave of Absence" ? 'selected' : '' ?>>Leave of Absence</option>
                            </select>
                            <select name="school_year_filter">
                                <?php foreach($available_years as $year) : ?>
                                    <option value="<?= $year ?>" <?= $school_year_filter == $year ? "selected" : "" ?>>
                                        <?= $year ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <!-- Distinct Checkboxes Row Below Dropdowns -->
                            <div class="filter-checkboxes">
                                <label class="checkbox-label">
                                    <input type="checkbox" name="flagged_filter" value="1" <?= $flagged_filter == 1 ? 'checked' : '' ?>>
                                    Flagged Scholars Only
                                </label>
                                <label class="checkbox-label">
                                    <input type="checkbox" name="archive_filter" value="1" <?= $archive_filter === '1' ? 'checked' : '' ?>>
                                    Archived Scholars Only
                                </label>
                                <button type="submit" name="filter" class="btn btn-sm btn-info">Apply Filter</button>
                                <?php if($has_filters) : ?>
                                    <a href="<?= htmlspecialchars($clear_filters_url) ?>" class="btn btn-sm clear-link">Clear Filters</a>
                                <?php endif; ?>
                            </div>

                        </div>
                    </form>
                </div>

                <!-- Table Header: Records Info + Add Button -->
                <div class="table-header">
                    <div class="records-info">
                        Showing <?= $total_records > 0 ? $offset + 1 : 0 ?> - <?= min($offset + $records_per_page, $total_records) ?> of <?= $total_records ?> scholars
                        <span class="flagged-legend-badge">
                            <span class="legend-color-box"></span> Flagged Scholar (GWA below requirement)
                        </span>
                    </div>
                    <?php if(hasPermission($conn, $_SESSION['user_id'], 'scholars', 'add')) : ?>
                        <a href="add_scholar.php" class="btn btn-success btn-sm">+ Add New Scholar</a>
                    <?php endif; ?>
                </div>

                <!-- Scholar Table -->
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Student ID</th>
                                <th>Name</th>
                                <th>Year Level</th>
                                <th>Program</th>
                                <th>College</th>
                                <th>Scholarship Program</th>
                                <th>Academic Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if($result->num_rows > 0) : ?>
                            <?php while($row = $result->fetch_assoc()) : 
                                $ext_name = ($row['ext_name'] == 'N/A' || empty($row['ext_name'])) ? "" : $row['ext_name'];
                                $year = $row['year_level'];
                                $year_lvl = "";
                                if($year == 1) {
                                    $year_lvl = "1st Year";
                                } elseif($year == 2) {
                                    $year_lvl = "2nd Year";
                                } elseif($year == 3) {
                                    $year_lvl = "3rd Year";
                                } elseif($year >= 4 && $year <= 6) {
                                    $year_lvl = $year . "th Year";
                                } else {
                                    $year_lvl = "Not specified";
                                }
                                $is_archived = (int)$row['is_archived'] === 1;
                                $is_flagged = (int)$row['is_flagged'] === 1;

                                if ($is_flagged) {
                                    $latest_gwa = !empty($row['latest_gwa']) ? number_format((float)$row['latest_gwa'], 2) : 'N/A';
                                    $min_gwa = !empty($row['min_gwa_req']) ? number_format((float)$row['min_gwa_req'], 2) : 'N/A';
                                    $flag_reason = "Flagged: Scholar's GWA ({$latest_gwa}) exceeds minimum requirement ({$min_gwa})";
                                }
                            ?>
                                <tr class="data-row-<?= $row['is_flagged'] ?>">
                                    <td class="student-id-cell">
                                        <div class="student-id-wrapper">
                                            <?php if ($is_flagged) : ?>
                                                <span class="flag-indicator" tabindex="0">
                                                    ⚠️
                                                    <span class="tooltip-box"><?= htmlspecialchars($flag_reason) ?></span>
                                                </span>
                                            <?php endif; ?>
                                            <span><?= htmlspecialchars($row['student_id']) ?></span>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($row['last_name']) ?>, <?= htmlspecialchars($row['given_name']) ?> <?= htmlspecialchars($row['middle_name']) ?> <?= htmlspecialchars($ext_name) ?></td>
                                    <td><?= htmlspecialchars($year_lvl) ?></td>
                                    <td><?= htmlspecialchars($row['program_name']) ?></td>
                                    <td><?= htmlspecialchars($row['college_name']) ?></td>
                                    <td><?= htmlspecialchars($row['scholarship_name']) ?></td>
                                    <td><?= htmlspecialchars($row['academic_status']) ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="scholar_profile.php?scholar_id=<?= $row['id'] ?>" class="btn-sm btn-view">View</a>
                                            <form action="<?= $_SERVER['PHP_SELF'] ?>" method="POST" style="display: inline;">
                                                <input type="hidden" name="archive" value="1">
                                                <input type="hidden" name="scholar_id" value="<?= $row['id'] ?>">
                                                <?php if(hasPermission($conn, $_SESSION['user_id'], 'scholars', 'archive')) : ?>
                                                    <?php if(!$is_archived) : ?>
                                                        <button type="submit" class="btn-sm btn-archive" onclick="return confirmAction(event, 'Are you sure you want to archive this scholar?', 'Archive', 'archive')">Archive</button>
                                                    <?php else : ?>
                                                        <button type="submit" class="btn-sm btn-archive" onclick="return confirmAction(event, 'Are you sure you want to unarchive this scholar?', 'Unarchive', 'primary')">Unarchive</button>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else : ?>
                            <tr>
                                <td colspan="8" class="no-data">No scholars found.</td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if($total_pages > 1) : ?>
                    <div class="pagination">
                        <?php if($page > 1) : ?>
                            <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($searched) ?>&course_filter=<?= $course_filter ?>&college_filter=<?= $college_filter ?>&scholarship_filter=<?= $scholarship_filter ?>&school_year_filter=<?= urlencode($school_year_filter) ?>&academic_status=<?= urlencode($academic_status) ?>&flagged_filter=<?= $flagged_filter ?>&archive_filter=<?= urlencode($archive_filter) ?>">Previous</a>
                        <?php endif; ?>
                        <?php for($i = 1; $i <= $total_pages; $i++) : ?>
                            <?php if($i == $page) : ?>
                                <strong><?= $i ?></strong>
                            <?php else : ?>
                                <a href="?page=<?= $i ?>&search=<?= urlencode($searched) ?>&course_filter=<?= $course_filter ?>&college_filter=<?= $college_filter ?>&scholarship_filter=<?= $scholarship_filter ?>&school_year_filter=<?= urlencode($school_year_filter) ?>&academic_status=<?= urlencode($academic_status) ?>&flagged_filter=<?= $flagged_filter ?>&archive_filter=<?= urlencode($archive_filter) ?>"><?= $i ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>
                        <?php if($page < $total_pages) : ?>
                            <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($searched) ?>&course_filter=<?= $course_filter ?>&college_filter=<?= $college_filter ?>&scholarship_filter=<?= $scholarship_filter ?>&school_year_filter=<?= urlencode($school_year_filter) ?>&academic_status=<?= urlencode($academic_status) ?>&flagged_filter=<?= $flagged_filter ?>&archive_filter=<?= urlencode($archive_filter) ?>">Next</a>
                        <?php endif; ?>
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