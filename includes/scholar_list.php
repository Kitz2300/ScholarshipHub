<?php 
require_once __DIR__ . '/../includes/conn.php';

// Fetch all scholars from the database
// unfinished sql
$scholar_sql = "SELECT student_id, last_name, given_name, middle_name, ext_name, scholarship_program.name AS scholarship_name, program.name AS program_name, academic_status FROM scholar_info INNER JOIN scholarship_program ON scholar_info.scholarship_program_id = scholarship_program.id INNER JOIN program ON scholar_info.program_id = program.id";
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
while($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . $row['student_id'] . "</td>";
    echo "<td>" . $row['last_name'] . ", " . $row['given_name'] . " " . $row['middle_name'] . " " . $row['ext_name'] . "</td>";
    echo "<td>" . $row['scholarship_name'] . "</td>";
    echo "<td>" . $row['program_name'] . "</td>";
    echo "<td>" . $row['academic_status'] . "</td>";
    echo "<td><button>Edit</button> <button>Delete</button></td>";
    echo "</tr>";
}

if($result->num_rows == 0) {
    echo "<tr><td colspan='6'>No scholars found.</td></tr>";
}
?>
    </table>
</div>