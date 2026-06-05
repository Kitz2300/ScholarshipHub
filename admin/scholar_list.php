<?php 
include '../includes/conn.php';
session_start(); ?>

<!DOCTYPE html>
<html>
    <head>
        <title>Admin Dashboard</title>
    </head>
    <body>
        <?php 
        if(!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {?>
            <p>You are not authorized to access this page. Please <a href="login.php">login</a>.</p>
        <?php } else { ?>
        <h2>Scholar List</h2>
        <div class="scholar_list">

        </div>
        <div class="add_scholar">
            <form action="../functions/add_scholar.php" method="post">
                <label for="last_name">Name:</label><br>
                <input type="text" name="last_name" placeholder="Last Name" id="last_name" required>
                <input type="text" name="first_name" placeholder="First Name" id="first_name" required>
                <input type="text" name="middle_name" placeholder="Middle Name" id="middle_name" required>
                <select name="ext_name" id="ext_name" required>
                    <option value="">Extension Name</option>
                    <option value="Jr.">Jr.</option>
                    <option value="Sr.">Sr.</option>
                    <option value="II">II</option>
                    <option value="III">III</option>
                </select><br>

                <label for="email">Email:</label><br>
                <input type="email" name="email" placeholder="john.doe@example.com" required><br>

                <label for="student_id">Student ID:</label><br>
                <input type="text" name="student_id" placeholder="23-0223-403" required><br>

                <label for="sex">Sex:</label><br>
                <select name="sex" id="sex" required>
                    <option value="">Select Sex</option>
                    <option value="Male">Male</option>
                    <option value="Female">Female</option>
                </select><br>

                <label for="birth_date">Birth Date:</label><br>
                <input type="date" name="birth_date" id="birth_date" required><br>

                <label for="scholarship_program">Scholarship Program</label><br>
                <select name="scholarship_program" id="scholarship_program" required>
                    <option value="">Select Scholarship Program</option>
                <?php 
                $scholarship_program_sql = "SELECT id, name, code FROM scholarship_program";
                $stmt = $conn->prepare($scholarship_program_sql);
                $stmt->execute();
                $result = $stmt->get_result();
                while($row = $result->fetch_assoc()) {
                    echo "<option value='" . $row['id'] . "'>" . $row['name'] . " (" . $row['code'] . ")</option>";
                }
                
                ?>
                </select><br>

                <label for="program">Program</label><br>
                <select name="program" id="program" required>
                    <option value="">Select Program</option>
                <?php 
                $program_sql = "SELECT id, name FROM program";
                ?>

                <button type="submit">Add Scholar</button>
            </form>
        </div>

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
                <?php include_once __DIR__ . '/../functions/scholar_list.php'; ?>
            </table>
        </div>
        <?php } ?>  
    </body>
</html>