<?php 
include __DIR__ . '/../includes/conn.php';
include '../includes/check_permission.php';
session_start();

if($_SESSION['role'] !== 'admin') {
    checkPageAccess($conn, $_SESSION['user_id'], 'scholars');
}

// ─── Process archive, search, filter, pagination ───
// This is the logic that was previously in includes/scholar_list.php
// We moved it here so that headers can be sent before any HTML output.

require_once __DIR__ . '/../includes/school_year_helper.php';

$selected_year = getSelectedSchoolYear();
$available_years = getAvailableSchoolYears($conn);

// ─── Archive scholar process ───
if(isset($_POST['archive'])) {
    if(!empty($_POST['scholar_id'])) {
        $scholar_id = $_POST['scholar_id'];
        $archive_sql = "UPDATE scholar_info SET is_archived = 1 WHERE id = ?";
        $archive_stmt = $conn->prepare($archive_sql);
        $archive_stmt->bind_param("i", $scholar_id);

        if($archive_stmt->execute()) {
            $_SESSION['success'] = "Scholar archived!";
        } else {
            $_SESSION['fail'] = "Failed to archive scholar.";
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
$academic_status = isset($_GET['academic_status']) ? $_GET['academic_status'] : "";
$flagged_filter = isset($_GET['flagged_filter']) ? (int)$_GET['flagged_filter'] : 0;
$school_year_filter = isset($_GET['school_year_filter']) ? $_GET['school_year_filter'] : null;

// ─── Default school year to latest available ───
if($school_year_filter === null && !empty($available_years)) {
    $school_year_filter = $available_years[0];
}

// ─── Build WHERE conditions ───
$where_condition = [];
$params = [];
$types = "";

// ─── Search (only when search_btn is clicked) ───
if(isset($_GET['search_btn']) && !empty($searched)) {
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

// ─── Filters (only when filter is clicked) ───
if(isset($_GET['filter'])) {
    // Re‑apply search if a search term exists
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
    
    if($course_filter > 0) {
        $where_condition[] = "scholar_info.program_id = ?";
        $params[] = $course_filter;
        $types .= "i";
    }
    
    if($college_filter > 0) {
        $where_condition[] = "program.college_id = ?";
        $params[] = $college_filter;
        $types .= "i";
    }
    
    if($scholarship_filter > 0) {
        $where_condition[] = "scholar_info.scholarship_program_id = ?";
        $params[] = $scholarship_filter;
        $types .= "i";
    }

    // ─── Flagged filter ───
    if ($flagged_filter == 1) {
        $where_condition[] = "sp.min_gwa_req > 0";
        $where_condition[] = "EXISTS (
            SELECT 1 
            FROM academic_performance ap
            WHERE ap.scholar_id = scholar_info.id
            AND ap.school_year = (
                SELECT MAX(school_year) 
                FROM academic_performance ap2 
                WHERE ap2.scholar_id = scholar_info.id
            )
            AND ap.semester = (
                SELECT MAX(semester) 
                FROM academic_performance ap3 
                WHERE ap3.scholar_id = scholar_info.id 
                AND ap3.school_year = ap.school_year
            )
            AND ap.gwa > sp.min_gwa_req
        )";
    }

    if($school_year_filter != null) {
        $where_condition[] = "scholar_info.school_year = ?";
        $params[] = $school_year_filter;
        $types .= "s";
    }
}

// ─── Apply academic status filter (always, if set) ───
if (!empty($academic_status)) {
    $where_condition[] = "academic_status = ?";
    $params[] = $academic_status;
    $types .= "s";
}

$where_sql = !empty($where_condition) ? " AND " . implode(" AND ", $where_condition) : "";

// ─── Count total records ───
$count_sql = "SELECT 
              COUNT(*) AS total
              FROM scholar_info
              INNER JOIN scholarship_program sp ON scholar_info.scholarship_program_id = sp.id 
              INNER JOIN program ON scholar_info.program_id = program.id
              INNER JOIN college ON program.college_id = college.id
              WHERE scholar_info.is_archived = 0 
                AND sp.is_archived = 0 
                AND sp.is_active = 1 
                $where_sql";

$count_stmt = $conn->prepare($count_sql);
if(!empty($params)) $count_stmt->bind_param($types, ...$params);
$count_stmt->execute();
$total_records = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_records / $records_per_page);

// ─── Add pagination parameters ───
$params[] = $records_per_page;
$params[] = $offset;
$types .= "ii";

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
                sp.name AS scholarship_name, 
                p.name AS program_name,
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
                    SELECT scholar_id, gwa, school_year, semester,
                           ROW_NUMBER() OVER (PARTITION BY scholar_id ORDER BY school_year DESC, semester DESC) AS rn
                    FROM academic_performance
                ) ap ON ap.scholar_id = scholar_info.id AND ap.rn = 1
                WHERE scholar_info.is_archived = 0 
                    AND sp.is_archived = 0 
                    AND sp.is_active = 1  
                    $where_sql
                ORDER BY scholar_info.id DESC
                LIMIT ? OFFSET ?";

$scholar_stmt = $conn->prepare($scholar_sql);
if(!empty($params)) $scholar_stmt->bind_param($types, ...$params);
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
$clear_filters_url = !empty($clear_filters_params) 
    ? $_SERVER['PHP_SELF'] . '?' . http_build_query($clear_filters_params) 
    : $_SERVER['PHP_SELF'];

$has_filters = ($course_filter != 0 || $college_filter != 0 || $scholarship_filter != 0 || 
                ($academic_status != "" && $academic_status != '') || $flagged_filter == 1);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Scholar List - PIT ScholarshipHub</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/scholar_list.css">
    <link rel="icon" href="../assets/images/PIT%20LOGO.png">
</head>
<body>
    <?php 
    // success/fail session messages
    if(isset($_SESSION['success'])) {
        echo "<script>alert('" . $_SESSION['success'] . "')</script>";
        unset($_SESSION['success']);
    } else if(isset($_SESSION['fail'])) {
        echo "<script>alert('" . $_SESSION['fail'] . "')</script>";
        unset($_SESSION['fail']);
    }
    ?>
    <?php if(!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'staff')) : ?>
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
                        <p>Scholar List</p>
                    </div>
                </div>
                <div class="header-right">
                    <span class="welcome-text">Welcome, Admin!</span>
                    <form action="../functions/logout.php" method="post" style="display: inline;">
                        <button type="submit" class="logout-btn" onclick="return confirm('Logout?')">Logout</button>
                    </form>
                </div>
            </div>

            <?php include __DIR__ . '/../includes/navigation.php'; ?>   

            <!-- Page Content -->
            <div class="page-content">
                <div class="page-header">
                    <h2>Scholar List</h2>
                </div>

                <!-- ─── Search & Filter (moved from includes/scholar_list.php) ─── -->
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
                        <div class="filter-row<?= ( $course_filter != 0 || $college_filter != 0 || $scholarship_filter != 0 || ($academic_status != "" && $academic_status != '') || $flagged_filter == 1) ? ' open' : '' ?>" id="filter-row">
                            <select name="course_filter" style="max-width: 240px;">
                                <option value="0" <?= $course_filter == 0 ? 'selected' : '' ?>>All Courses</option>
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
                                <option value="" <?= $academic_status == "" ? 'selected' : '' ?>>All Academic Status</option>
                                <option value="Enrolled" <?= $academic_status == "Enrolled" ? 'selected' : '' ?>>Enrolled</option>
                                <option value="Not Enrolled" <?= $academic_status == "Not Enrolled" ? 'selected' : '' ?>>Not Enrolled</option>
                                <option value="Transferred" <?= $academic_status == "Transferred" ? 'selected' : '' ?>>Transferred</option>
                                <option value="Graduated" <?= $academic_status == "Graduated" ? 'selected' : '' ?>>Graduated</option>
                                <option value="Dropped" <?= $academic_status == "Dropped" ? 'selected' : '' ?>>Dropped</option>
                                <option value="Leave of Absence" <?= $academic_status == "Leave of Absence" ? 'selected' : '' ?>>Leave of Absence</option>
                            </select>
                            <select name="flagged_filter">
                                <option value="0" <?= $flagged_filter == 0 ? 'selected' : '' ?>>All Scholars</option>
                                <option value="1" <?= $flagged_filter == 1 ? 'selected' : '' ?>>Flagged Only</option>
                            </select>
                            <select name="school_year_filter">
                                <?php foreach($available_years as $year) : ?>
                                    <option value="<?= $year ?>" <?= $school_year_filter == $year ? "selected" : "" ?>>
                                        <?= $year ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" name="filter" class="btn btn-sm btn-info">Apply Filter</button>
                            <?php if($has_filters) : ?>
                                <a href="<?= htmlspecialchars($clear_filters_url) ?>" class="btn btn-sm clear-link">Clear Filters</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>

                <!-- Table Header: Records Info + Add Button -->
                <div class="table-header">
                    <div class="records-info">
                        Showing <?= $offset + 1 ?> - <?= min($offset + $records_per_page, $total_records) ?> of <?= $total_records ?> scholars
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
                            ?>
                                <tr class="data-row-<?= $row['is_flagged'] ?>">
                                    <td><?= htmlspecialchars($row['student_id']) ?></td>
                                    <td><?= htmlspecialchars($row['last_name']) ?>, <?= htmlspecialchars($row['given_name']) ?> <?= htmlspecialchars($row['middle_name']) ?> <?= htmlspecialchars($ext_name) ?></td>
                                    <td><?= htmlspecialchars($year_lvl) ?></td>
                                    <td><?= htmlspecialchars($row['program_name']) ?></td>
                                    <td><?= htmlspecialchars($row['scholarship_name']) ?></td>
                                    <td><?= htmlspecialchars($row['academic_status']) ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="scholar_profile.php?scholar_id=<?= $row['id'] ?>" class="btn-sm btn-view">View</a>
                                            <form action="<?= $_SERVER['PHP_SELF'] ?>" method="POST" style="display: inline;">
                                                <input type="hidden" name="scholar_id" value="<?= $row['id'] ?>">
                                                <?php if(hasPermission($conn, $_SESSION['user_id'], 'scholars', 'archive')) : ?>
                                                    <button type="submit" name="archive" class="btn-sm btn-archive" onclick="return confirm('Archive scholar?')">Archive</button>
                                                <?php endif; ?>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else : ?>
                            <tr>
                                <td colspan="7" class="no-data">No scholars found.</td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if($total_pages > 1) : ?>
                    <div class="pagination">
                        <?php if($page > 1) : ?>
                            <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($searched) ?>&course_filter=<?= $course_filter ?>&college_filter=<?= $college_filter ?>&scholarship_filter=<?= $scholarship_filter ?>&school_year_filter=<?= urlencode($school_year_filter) ?>&academic_status=<?= urlencode($academic_status) ?>&flagged_filter=<?= $flagged_filter ?>">Previous</a>
                        <?php endif; ?>
                        <?php for($i = 1; $i <= $total_pages; $i++) : ?>
                            <?php if($i == $page) : ?>
                                <strong><?= $i ?></strong>
                            <?php else : ?>
                                <a href="?page=<?= $i ?>&search=<?= urlencode($searched) ?>&course_filter=<?= $course_filter ?>&college_filter=<?= $college_filter ?>&scholarship_filter=<?= $scholarship_filter ?>&school_year_filter=<?= urlencode($school_year_filter) ?>&academic_status=<?= urlencode($academic_status) ?>&flagged_filter=<?= $flagged_filter ?>"><?= $i ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>
                        <?php if($page < $total_pages) : ?>
                            <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($searched) ?>&course_filter=<?= $course_filter ?>&college_filter=<?= $college_filter ?>&scholarship_filter=<?= $scholarship_filter ?>&school_year_filter=<?= urlencode($school_year_filter) ?>&academic_status=<?= urlencode($academic_status) ?>&flagged_filter=<?= $flagged_filter ?>">Next</a>
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