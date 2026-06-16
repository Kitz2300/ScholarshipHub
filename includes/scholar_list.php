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

// Fetch all scholars from the database
// unfinished sql
$scholar_sql = "SELECT scholar_info.id, student_id, last_name, given_name, middle_name, ext_name, scholarship_program.name AS scholarship_name, program.name AS program_name, academic_status FROM scholar_info INNER JOIN scholarship_program ON scholar_info.scholarship_program_id = scholarship_program.id INNER JOIN program ON scholar_info.program_id = program.id";
$stmt = $conn->prepare($scholar_sql);
$stmt->execute();
$result = $stmt->get_result();
?>

<div class="scholars">
    <h3>Existing Scholars</h3>
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
    echo "<tr><td colspan='6'>No scholars found.</td></tr>";
}
?>
    </table>
</div>