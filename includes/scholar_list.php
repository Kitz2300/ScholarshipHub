<?php
require_once __DIR__ . '/../includes/conn.php';

// delete scholar process
if(isset($_POST['delete_scholar'])) {
    if(!empty($_POST['scholar_id'])) {
        $scholar_id = $_POST['scholar_id'];
        $delete_sql = "DELETE FROM scholar_info WHERE id = $scholar_id";
        $delete_stmt = $conn->prepare($delete_sql);
        $delete_stmt->execute();

        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}

// Search and filter process
$searched = "";
if(isset($_GET['search'])) {
    $searched = $_GET['search'];
}

// Fetch all scholars from the database
// unfinished sql
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
                INNER JOIN program ON scholar_info.program_id = program.id";

if(!empty($searched)) {
    
    $scholar_sql .= " WHERE scholar_info.given_name LIKE ?";
    $stmt = $conn->prepare($scholar_sql);
    $search_term = "%" . $searched . "%";
    $stmt->bind_param("s", $search_term);
} else {
    $stmt = $conn->prepare($scholar_sql);

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
    <form action="<?= $_SERVER['PHP_SELF'] ?>" method="GET">
        <input type="text" name="search" placeholder="Search" value="<?= !empty($searched) ? $searched : "" ?>"/>
        <button>Search</button>
    </form>

    <div class="filter">
        <!-- Filter Dropdowns -->
        <form action="<?= $_SERVER['PHP_SELF'] ?>" method="GET">
            <label id="filter">Filter By:</label>
            <!-- Course Dropdown -->
            <select name="course_filter">
                <option value="" disabled selected>Course</option>
                <?php 
                while($row=mysqli_fetch_assoc($course_result)) {?>
                    <option value="<?= $row['id'] ?>"> <?= $row['name'] ?> </option>
                <?php }
                ?>
            </select>

            <!-- College Dropdown -->
            <select name="college_filter">
                <option value="" disabled selected>College</option>
                <?php 
                while($row=mysqli_fetch_assoc($college_result)) {?>
                    <option value="<?= $row['id'] ?>"> <?= $row['college_name'] ?> </option>
                <?php }
                ?>
            </select>

            <!-- Scholarship Program Dropdown -->
            <select name="scholarship_filter">
                <option value="" disabled selected>Scholarship Program</option>
                <?php 
                while($row=mysqli_fetch_assoc($scholarship_result)) {?>
                    <option value="<?= $row['id'] ?>"> <?= $row['name'] ?> </option>
                <?php }
                ?>
            </select>

            <button>Apply Filter</button>
        </form>
    </div>

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

if($result->num_rows > 0) {
while($row = $result->fetch_assoc()) {
    $ext_name = $row['ext_name'] == 'N/A' ? "" : $row['ext_name'];
    echo "<tr>";
    echo "<td>" . $row['student_id'] . "</td>";
    echo "<td>" . $row['last_name'] . ", " . $row['given_name'] . " " . $row['middle_name'] . " " . $ext_name . "</td>";
    echo "<td>" . $row['scholarship_name'] . "</td>";
    echo "<td>" . $row['program_name'] . "</td>";
    echo "<td>" . $row['academic_status'] . "</td>";?>
    <td>
        <form action="<?= $_SERVER['PHP_SELF'] ?>" method="POST">
            <input type="hidden" name="scholar_id" value="<?=$row['id']?>"/>
            <?php $_SESSION['scholar_id'] = $row['id']?>
            <a href="edit_scholar.php">Edit</a>
            <button name="delete_scholar">Delete</button>
        </form>
    </td>
    <?php echo "</tr>";
}

}else {
    echo "<tr><td colspan='6' >No scholars found.</td></tr>";
}
?>
    </table>
</div>