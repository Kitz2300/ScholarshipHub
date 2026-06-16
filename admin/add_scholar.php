<?php 
include __DIR__ . '/../includes/conn.php';
session_start();
?>

<html>
    <head>
        <title>Add Scholar</title>
    </head>
    <body>
        <!-- check if logged in or not -->
        <?php
        if(!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') { ?>
            <p>You are not authorized to access this page. Please <a href="login.php">login</a>.</p>
            <!-- if logged in, load page -->
        <?php } else { ?>
        <h1>Add Scholar</h1>

        <div class="add_scholar">
            <h3>Student Information</h3>
            <form action="../functions/add_scholar_process.php" method="post">
                <label for="last_name">Name:</label><br>
                <input type="text" name="last_name" placeholder="Last Name" id="last_name" required>
                <input type="text" name="first_name" placeholder="First Name" id="first_name" required>
                <input type="text" name="middle_name" placeholder="Middle Name" id="middle_name" required>
                <select name="ext_name" id="ext_name" required>
                    <option value="">Extension Name</option>
                    <option value="N/A">Not Applicable</option>
                    <option value="Jr.">Jr.</option>
                    <option value="Sr.">Sr.</option>
                    <option value="II">II</option>
                    <option value="III">III</option>
                </select><br>

                <label for="email">Email:</label><br>
                <input type="email" name="email" placeholder="john.doe@example.com" required><br>

                <label for="student_id">Student ID:</label><br>
                <input type="text" name="student_id" placeholder="23-0223-403" required><br>

                <label for="student_contact">Contact Number:</label><br>
                <input type="text" name="student_contact" placeholder="09123456789" required><br>

                <label for="sex">Sex:</label><br>
                <select name="sex" id="sex" required>
                    <option value="">Select Sex</option>
                    <option value="Male">Male</option>
                    <option value="Female">Female</option>
                </select><br>

                <label for="birth_date">Birth Date:</label><br>
                <input type="date" name="birth_date" id="birth_date" required><br>

                <label for="disability">Disability (if applicable):</label><br>
                <select name="disability" id="disability" required>
                    <option value="">Select Disability</option>
                    <option value="None">None</option>
                    <option value="Visual Impairment">Visual Impairment</option>
                    <option value="Hearing Impairment">Hearing Impairment</option>
                    <option value="Mobility Impairment">Mobility Impairment</option>
                    <option value="Cognitive Impairment">Cognitive Impairment</option>
                    <!-- Add more disability options as needed -->
                </select><br>

                <label for="ip_goup">IP Group:</label><br>
                <select name="ip_group" id="ip_group" required>
                    <option value="">Select IP Group</option>
                    <option value="Not applicable">Not applicable</option>
                    <!-- Add IP group options here -->
                </select><br>

                <label for="addresss">Permanent Address:</label><br>
                <select name="address" id="address" required>
                    <option value="">Select Address</option>
                    <option value="Example">Example</option>
                    <!-- add address options here -->
                </select><br>

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
                    <option value="" disabled selected>Select Program</option>
                <?php 
                $program_sql = "SELECT id, name FROM program";
                $stmt = $conn->prepare($program_sql);
                $stmt->execute();
                $result = $stmt->get_result();
                while($row = $result->fetch_assoc()) {
                    echo "<option value='" . $row['id'] . "'>" . $row['name'] . "</option>";
                }
                ?>
                <select><br>

                <h3>Family Background</h3>
                <label for="father_name">Father's Name:</label><br>
                <input type="text" name="father_last" placeholder="Last Name" id="father_name" required>
                <input type="text" name="father_first" placeholder="First Name" id="father_name" required>
                <input type="text" name="father_middle" placeholder="Middle Name" id="father_name" required><br>

                <label for="mother_name">Mother's Name:</label><br>
                <input type="text" name="mother_last" placeholder="Last Name" id="mother_name" required>
                <input type="text" name="mother_first" placeholder="First Name" id="mother_name" required>
                <input type="text" name="mother_middle" placeholder="Middle Name" id="mother_name" required><br>

                <button type="submit">Add Scholar</button>
                <button onclick="window.location.href='scholar_list.php'">Cancel</button>
            </form>
        </div>
        
        <?php } ?>
    </body>
</html>