<?php
require_once __DIR__ . '/../includes/conn.php';

// Delete scholar process
if(isset($_POST['delete_scholar'])) {
    if(!empty($_POST['scholar_id'])) {
        $scholar_id = $_POST['scholar_id'];
        $delete_sql = "DELETE FROM scholar_info WHERE id = ?";
        $delete_stmt = $conn->prepare($delete_sql);
        $delete_stmt->bind_param("i", $scholar_id);
        $delete_stmt->execute();

        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}

// Search, filter, and pagination process
$records_per_page = 5;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if($page < 1) $page = 1;
$offset = ($page - 1) * $records_per_page;

// Get filter parameters
$searched = isset($_GET['search']) ? trim($_GET['search']) : "";
$course_filter = isset($_GET['course_filter']) ? (int)$_GET['course_filter'] : 0;
$college_filter = isset($_GET['college_filter']) ? (int)$_GET['college_filter'] : 0;
$scholarship_filter = isset($_GET['scholarship_filter']) ? (int)$_GET['scholarship_filter'] : 0;

$where_condition = [];
$params = [];
$types = "";

// Search function
if(isset($_GET['search_btn'])) {
    if(!empty($searched)) {
        $where_condition[] = "(scholar_info.student_id LIKE ? OR scholar_info.given_name LIKE ? OR scholar_info.last_name LIKE ?)";
        $like = "%$searched%";
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $types .= "sss";
    }
}

// Filter function
if(isset($_GET['filter'])) {
    if(!empty($searched)) {
        $where_condition[] = "(scholar_info.student_id LIKE ? OR scholar_info.given_name LIKE ? OR scholar_info.last_name LIKE ?)";
        $like = "%$searched%";
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $types .= "sss";
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
}

$where_sql = !empty($where_condition) ? "WHERE " . implode(" AND ", $where_condition) : "";

// Count total records
$count_sql = "SELECT 
              COUNT(*) AS total 
              FROM scholar_info
              INNER JOIN scholarship_program ON scholar_info.scholarship_program_id = scholarship_program.id 
              INNER JOIN program ON scholar_info.program_id = program.id
              INNER JOIN college ON program.college_id = college.id
              $where_sql 
              ";

$count_stmt = $conn->prepare($count_sql);
if (!empty($params)) $count_stmt->bind_param($types, ...$params);
$count_stmt->execute();
$total_records = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_records / $records_per_page);

$params[] = $records_per_page;
$params[] = $offset;
$types .= "ii";

// Fetch scholars
$scholar_sql = "SELECT scholar_info.id, student_id, 
                last_name, 
                given_name, 
                middle_name, 
                ext_name, 
                scholarship_program.name AS scholarship_name, 
                program.name AS program_name, 
                academic_status 
                FROM scholar_info 
                INNER JOIN scholarship_program ON scholar_info.scholarship_program_id = scholarship_program.id 
                INNER JOIN program ON scholar_info.program_id = program.id
                INNER JOIN college ON program.college_id = college.id
                $where_sql
                ORDER BY scholar_info.id DESC
                LIMIT ?
                OFFSET ?
                ";

$stmt = $conn->prepare($scholar_sql);
if(!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Dropdown filters queries
$course_sql = "SELECT id, name FROM program";
$course_stmt = $conn->prepare($course_sql);
$course_stmt->execute();
$course_result = $course_stmt->get_result();

$college_sql = "SELECT id, college_name FROM college";
$college_stmt = $conn->prepare($college_sql);
$college_stmt->execute();
$college_result = $college_stmt->get_result();

$scholarship_sql = "SELECT id, name FROM scholarship_program";
$scholarship_stmt = $conn->prepare($scholarship_sql);
$scholarship_stmt->execute();
$scholarship_result = $scholarship_stmt->get_result();

if($page > $total_pages && $total_pages > 0) {
    header("Location: ?page=" . $total_pages);
    exit();
}
?>

<!-- Search and Filter Section -->
<div class="search-filter">
    <form action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" method="GET">
        <!-- Row 1: Search -->
        <div class="search-row">
            <input type="search" name="search" placeholder="Search by name or ID..." value="<?= !empty($searched) ? htmlspecialchars($searched) : "" ?>"/>
            <button type="submit" name="search_btn" class="btn btn-sm btn-primary">Search</button>
        </div>

        <!-- Row 2: Filters -->
        <div class="filter-row">
            <span class="filter-label">Filter by:</span><br>
            
            <select name="course_filter">
                <option value="0" <?= $course_filter == 0 ? 'selected' : '' ?>>All Courses</option>
                <?php 
                $course_result->data_seek(0);
                while($row = $course_result->fetch_assoc()) {
                    $selected = ($course_filter == $row['id']) ? 'selected' : ''; ?>
                    <option value="<?= $row['id'] ?>" <?= $selected ?>> <?= htmlspecialchars($row['name']) ?> </option>
                <?php } ?>
            </select>

            <select name="college_filter">
                <option value="0" <?= $college_filter == 0 ? 'selected' : '' ?>>All Colleges</option>
                <?php 
                $college_result->data_seek(0);
                while($row = $college_result->fetch_assoc()) {
                    $selected = ($college_filter == $row['id']) ? 'selected' : ''; ?>
                    <option value="<?= $row['id'] ?>" <?= $selected ?>> <?= htmlspecialchars($row['college_name']) ?> </option>
                <?php } ?>
            </select>

            <select name="scholarship_filter">
                <option value="0" <?= $scholarship_filter == 0 ? 'selected' : '' ?>>All Scholarships</option>
                <?php 
                $scholarship_result->data_seek(0);
                while($row = $scholarship_result->fetch_assoc()) {
                    $selected = ($scholarship_filter == $row['id']) ? 'selected' : ''; ?>
                    <option value="<?= $row['id'] ?>" <?= $selected ?>> <?= htmlspecialchars($row['name']) ?> </option>
                <?php } ?>
            </select>

            <button type="submit" name="filter" class="btn btn-sm btn-info">Apply Filter</button>
            
            <?php if($searched != "" || $course_filter != 0 || $college_filter != 0 || $scholarship_filter != 0) : ?>
                <a href="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" class="clear-link">Clear</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Records Info -->
<div class="records-info">
    Showing <?= $offset + 1 ?> - <?= min($offset + $records_per_page, $total_records) ?> of <?= $total_records ?> scholars
</div>

<!-- Scholar Table -->
<div class="table-wrapper">
    <table>
        <thead>
            <tr>
                <th>Student ID</th>
                <th>Name</th>
                <th>Scholarship Program</th>
                <th>Program</th>
                <th>Academic Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php if($result->num_rows > 0) : ?>
            <?php while($row = $result->fetch_assoc()) : 
                $ext_name = ($row['ext_name'] == 'N/A' || empty($row['ext_name'])) ? "" : $row['ext_name'];
            ?>
                <tr>
                    <td><?= htmlspecialchars($row['student_id']) ?></td>
                    <td><?= htmlspecialchars($row['last_name']) ?>, <?= htmlspecialchars($row['given_name']) ?> <?= htmlspecialchars($row['middle_name']) ?> <?= htmlspecialchars($ext_name) ?></td>
                    <td><?= htmlspecialchars($row['scholarship_name']) ?></td>
                    <td><?= htmlspecialchars($row['program_name']) ?></td>
                    <td><?= htmlspecialchars($row['academic_status']) ?></td>
                    <td>
                        <div class="action-buttons">
                            <a href="scholar_profile.php?scholar_id=<?= $row['id'] ?>" class="btn-sm btn-view">View</a>
                            <form action="<?= $_SERVER['PHP_SELF'] ?>" method="POST" style="display: inline;">
                                <input type="hidden" name="scholar_id" value="<?= $row['id'] ?>">
                                <button type="submit" name="delete_scholar" class="btn-sm btn-delete" onclick="return confirm('Delete this scholar?')">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endwhile; ?>
        <?php else : ?>
            <tr>
                <td colspan="6" class="no-data">No scholars found.</td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Pagination -->
<?php if($total_pages > 1) : ?>
    <div class="pagination">
        <?php if($page > 1) : ?>
            <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($searched) ?>&course_filter=<?= $course_filter ?>&college_filter=<?= $college_filter ?>&scholarship_filter=<?= $scholarship_filter ?>">Previous</a>
        <?php endif; ?>
        
        <?php for($i = 1; $i <= $total_pages; $i++) : ?>
            <?php if($i == $page) : ?>
                <strong><?= $i ?></strong>
            <?php else : ?>
                <a href="?page=<?= $i ?>&search=<?= urlencode($searched) ?>&course_filter=<?= $course_filter ?>&college_filter=<?= $college_filter ?>&scholarship_filter=<?= $scholarship_filter ?>"><?= $i ?></a>
            <?php endif; ?>
        <?php endfor; ?>

        <?php if($page < $total_pages) : ?>
            <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($searched) ?>&course_filter=<?= $course_filter ?>&college_filter=<?= $college_filter ?>&scholarship_filter=<?= $scholarship_filter ?>">Next</a>
        <?php endif; ?>
    </div>
<?php endif; ?>