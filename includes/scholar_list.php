<?php
require_once __DIR__ . '/../includes/conn.php';

// delete scholar process
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

// Search and filter process

// get filter parameters
$searched = isset($_GET['search']) ? trim($_GET['search']) : "";
$course_filter = isset($_GET['course_filter']) ? (int)$_GET['course_filter'] : 0;
$college_filter = isset($_GET['college_filter']) ? (int)$_GET['college_filter'] : 0;
$scholarship_filter = isset($_GET['scholarship_filter']) ? (int)$_GET['scholarship_filter'] : 0;

$where_condition = [];
$params = [];
$types = "";

// search function
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

// filter function
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

// if user searches or filters, concat WHERE conditions
$where_sql = !empty($where_condition) ? "WHERE " . implode(" AND ", $where_condition) : "";


// Fetch all scholars from the database
// unfinished sql !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
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
                ";

// if user searches concat a sql command to search for the value, if not directly prapre the statement
$stmt = $conn->prepare($scholar_sql);
if(!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();


// dropdown filters queries
// get list of all coursess
$course_sql = "SELECT id, name FROM program";
$course_stmt = $conn->prepare($course_sql);
$course_stmt->execute();
$course_result = $course_stmt->get_result();

// get list of all colleges
$college_sql = "SELECT id, college_name FROM college";
$college_stmt = $conn->prepare($college_sql);
$college_stmt->execute();
$college_result = $college_stmt->get_result();

// get list of all scholarship programs
$scholarship_sql = "SELECT id, name FROM scholarship_program";
$scholarship_stmt = $conn->prepare($scholarship_sql);
$scholarship_stmt->execute();
$scholarship_result = $scholarship_stmt->get_result();


?>

<div class="scholars">
    <!-- search and filter form -->
    <form action="<?= $_SERVER['PHP_SELF'] ?>" method="GET">
        <input type="text" name="search" placeholder="Search" value="<?= !empty($searched) ? $searched : "" ?>"/>
        <button name="search_btn">Search</button><br>

        <!-- filter Dropdowns -->
        <label id="filter">Filter By:</label>
            <!-- Course Dropdown -->
            <select name="course_filter">
                <option value="" disabled selected>Course</option>
                <?php 
                while($row= $course_result->fetch_assoc()) {
                    $selected = (isset($_GET['course_filter']) && $_GET['course_filter'] == $row['id']) ? 'selected' : ''; ?>
                    <option value="<?= $row['id'] ?>" <?= $selected ?>> <?= htmlspecialchars($row['name']) ?> </option>
                <?php }
                ?>
            </select>

            <!-- College Dropdown -->
            <select name="college_filter">
                <option value="" disabled selected>College</option>
                <?php 
                while($row= $college_result->fetch_assoc()) {
                    $selected = (isset($_GET['college_filter']) && $_GET['college_filter'] == $row['id']) ? 'selected' : ''; ?>
                    <option value="<?= $row['id'] ?>" <?= $selected ?>> <?= htmlspecialchars($row['college_name']) ?> </option>
                <?php }
                ?>
            </select>

            <!-- Scholarship Program Dropdown -->
            <select name="scholarship_filter">
                <option value="" disabled selected>Scholarship Program</option>
                <?php 
                while($row= $scholarship_result->fetch_assoc()) {
                    $selected = (isset($_GET['scholarship_filter']) && $_GET['scholarship_filter'] == $row['id']) ? 'selected' : ''; ?>
                    <option value="<?= $row['id'] ?>" <?= $selected ?>> <?= htmlspecialchars($row['name']) ?> </option>
                <?php }
                ?>
            </select>

            <button name="filter">Apply Filter</button>
            <a href="<?=  $_SERVER['PHP_SELF'] ?>">Clear</a>
    </form>

    <!-- table for scholar information -->
    <table border="1">
        <tr>
            <th>Student ID</th>
            <th>Name</th>
            <th>Scholarship Program</th>
            <th>Program</th>
            <th>Academic Status</th>
            <th>Actions</th>
        </tr>
<?php 

// if scholar info not empty display scholars
if($result->num_rows > 0) {
while($row = $result->fetch_assoc()) {
    $ext_name = $row['ext_name'] == 'N/A' ? "" : $row['ext_name'];
    echo "<tr>";
    echo "<td>" . htmlspecialchars($row['student_id']) . "</td>";
    echo "<td>" . htmlspecialchars($row['last_name']) . ", " . htmlspecialchars($row['given_name']) . " " . htmlspecialchars($row['middle_name']) . " " . htmlspecialchars($ext_name) . "</td>";
    echo "<td>" . htmlspecialchars($row['scholarship_name']) . "</td>";
    echo "<td>" . htmlspecialchars($row['program_name']) . "</td>";
    echo "<td>" . htmlspecialchars($row['academic_status']) . "</td>";?>
    <td>
        <!-- delete and edit buttons -->
        <form action="<?= $_SERVER['PHP_SELF'] ?>" method="POST">
            <input type="hidden" name="scholar_id" value="<?=$row['id']?>"/>
            <?php $_SESSION['scholar_id'] = $row['id']?>
            <a href="edit_scholar.php">Edit</a>
            <button name="delete_scholar">Delete</button>
        </form>
    </td>
    <?php echo "</tr>";
}}
// if scholar records empty display "no scholars found"
else {
    echo "<tr><td colspan='6' >No scholars found.</td></tr>";
}
?>
    </table>
</div>