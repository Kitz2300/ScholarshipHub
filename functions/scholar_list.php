<?php 
require_once __DIR__ . '/../includes/conn.php';

// Fetch all scholars from the database
$scholar_sql = "SELECT * FROM scholar_info";
$stmt = $conn->prepare($scholar_sql);
$stmt->execute();
$result = $stmt->get_result();

while($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . $row['student_id'] . "</td>";
    echo "<td>" . $row['last_name'] . ", " . $row['given_name'] . " " . $row['middle_name'] . " " . $row['ext_name'] . "</td>";
    echo "<td>" . $row['scholarship_program_id'] . "</td>";
    echo "<td>" . $row['program_id'] . "</td>";
    echo "<td>" . $row['academic_status'] . "</td>";
    echo "<td><button>Edit</button> <button>Delete</button></td>";
    echo "</tr>";
}
?>