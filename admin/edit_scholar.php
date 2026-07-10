<?php 
include __DIR__ . '/../includes/conn.php';
session_start();

$scholar_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$scholar_sql = "SELECT * FROM scholar_info WHERE id = $scholar_id";
$scholar_stmt = $conn->prepare($scholar_sql);
$scholar_stmt->execute();
$scholar_result = $scholar_stmt->get_result();

// initialize variables
$last_name = "";
$given_name = "";
$ext_name = "";
$middle_name = "";
$id_num = "";
$sex = 0;
$birthdate = "";
$scholarship_program = 0;
$program = 0;
$academic_status = "";
$address = "";
$zipcode = "";
$disability = "";
$contact_num = "";
$email = "";
$ip_group = "";

$father_last = "";
$father_given = "";
$father_middle = "";

$mother_last = "";
$mother_given = "";
$mother_middle = "";


if($row=mysqli_fetch_assoc($scholar_result)) {
    $last_name = $row['last_name'];
    $given_name = $row['given_name'];
    $ext_name = $row['ext_name'] === "N/A" ? "Not Applicable" : $row['ext_name'];
    $middle_name = $row['middle_name'];
    $id_num = $row['student_id'];
    $sex = $row['sex'];
    $birthdate = $row['birthdate'];
    $scholarship_program = $row['scholarship_program_id'];
    $program = $row['program_id'];
    $academic_status = $row['academic_status'];
    $address = $row['permanent_address'];
    $zipcode = $row['zipcode'];
    $disability = $row['disability'];
    $contact_num = $row['contact_num'];
    $email = $row['email'];
    $ip_group = $row['ip_group'];

    $father_last = $row['father_last_name'];
    $father_given = $row['father_given_name'];
    $father_middle = $row['father_middle_name'];

    $mother_last = $row['mother_last_name'];
    $mother_given = $row['mother_given_name'];
    $mother_middle = $row['mother_middle_name'];
} else {
    echo "<script>alert('scholar not available')</script>";
    echo "<script>window.location.href='scholar_list.php'</script>";
}
?>

<html>
    <head>
        <title>Edit Scholar</title>
    </head>
    <body>
        <!-- check if logged in or not -->
        <?php
        if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') { ?>
            <p>You are not authorized to access this page. Please <a href="login.php">login</a>.</p>
            <!-- if logged in, load page -->
        <?php } else { ?>
        <h1>Edit Scholar</h1>

        <div class="edit_scholar">
            <h3>Student Information</h3>
            <form action="../functions/edit_scholar_process.php" method="post">
                <label for="last_name">Name:</label><br>
                <input type="text" name="last_name" placeholder="Last Name" id="last_name" value="<?= $last_name ?>" required>
                <input type="text" name="first_name" placeholder="First Name" id="first_name" value="<?= $given_name ?>" required>
                <input type="text" name="middle_name" placeholder="Middle Name" id="middle_name" value="<?= $middle_name ?>" required>
                <select name="ext_name" id="ext_name" required>
                    <option value="" disabled>Extension Name</option>
                    <option value="N/A" <?= ($ext_name == 'Not applicable') ? 'selected' : '' ?> >Not Applicable</option>
                    <option value="Jr." <?= ($ext_name == 'Jr.') ? 'selected' : '' ?>>Jr.</option>
                    <option value="Sr." <?= ($ext_name == 'Sr.') ? 'selected' : '' ?> >Sr.</option>
                    <option value="II" <?= ($ext_name == 'II') ? 'selected' : '' ?> >II</option>
                    <option value="III" <?= ($ext_name == 'III.') ? 'selected' : '' ?> >III</option>
                </select><br>

                <label for="email">Email:</label><br>
                <input type="email" name="email" placeholder="john.doe@example.com" value="<?= $email ?>" required><br>

                <label for="student_id">Student ID:</label><br>
                <input type="text" name="student_id" placeholder="23-0223-403" value="<?= $id_num ?>" required><br>

                <label for="student_contact">Contact Number:</label><br>
                <input type="text" name="student_contact" placeholder="09123456789" value="<?= $contact_num?> " required><br>

                <label for="sex">Sex:</label><br>
                <select name="sex" id="sex" required>
                    <option value="" disabled>Select Sex</option>
                    <option value="Male" <?= ($sex == 0) ? 'selected' : '' ?> >Male</option>
                    <option value="Female" <?= ($sex == 1) ? 'selected' : '' ?> >Female</option>
                </select><br>

                <label for="birth_date">Birth Date:</label><br>
                <input type="date" name="birth_date" id="birth_date" value="<?= $birthdate ?>" required><br>

                <label for="disability">Disability (if applicable):</label><br>
                <select name="disability" id="disability" required>
                    <option value="" disabled>Select Disability</option>
                    <option value="Not applicable" <?= ($disability == 'Not applicable') ? 'selected' : '' ?> >Not Applicable</option>
                    <option value="Visual Impairment" <?= ($disability == 'Visual Impairment') ? 'selected' : '' ?> >Visual Impairment</option>
                    <option value="Hearing Impairment" <?= ($disability == 'Hearing Impairment') ? 'selected' : '' ?> >Hearing Impairment</option>
                    <option value="Mobility Impairment" <?= ($disability == 'Mobility Impairment') ? 'selected' : '' ?> >Mobility Impairment</option>
                    <option value="Cognitive Impairment" <?= ($disability == 'Cognitive Impairment') ? 'selected' : '' ?> >Cognitive Impairment</option>
                    <!-- Add more disability options as needed -->
                </select><br>

                <label for="ip_goup">IP Group:</label><br>
                <select name="ip_group" id="ip_group" required>
                    <option value="" disabled>Select IP Group</option>
                    <option value="Not applicable" <?= ($ip_group == 'Not applicable') ? 'selected' : '' ?> >Not applicable</option>
                    <option value="Aeta" <?= ($ip_group == 'Aeta') ? 'selected' : '' ?> >Aeta</option>
                    <!-- Add IP group options here -->
                </select><br>

                <label for="address">Permanent Address:</label><br>
                <input type="text" name="address" id="address" value="<?= $address ?>" required /><br>

                <label for="zipcode">Zipcode:</label><br>
                <input type="number" name="zipcode" value="<?= $zipcode ?>" placholder="e.g. 6538" required /><br>

                <label for="scholarship_program">Scholarship Program</label><br>
                <select name="scholarship_program" id="scholarship_program" required>
                    <option value="" disabled>Select Scholarship Program</option>
                <?php 
                $scholarship_program_sql = "SELECT id, name, code FROM scholarship_program";
                $stmt = $conn->prepare($scholarship_program_sql);
                $stmt->execute();
                $result = $stmt->get_result();
                while($row = $result->fetch_assoc()) {
                    echo "<option value='" . $row['id'] . "'" . ($scholarship_program == $row['id'] ? 'selected' : '') . "> " . $row['name'] . " (" . $row['code'] . ")</option>";
                }
                
                ?>
                </select><br>

                <label for="program">Program</label><br>
                <select name="program" id="program" required>
                    <option value="" disabled>Select Program</option>
                <?php 
                $program_sql = "SELECT id, name FROM program";
                $stmt = $conn->prepare($program_sql);
                $stmt->execute();
                $result = $stmt->get_result();
                while($row = $result->fetch_assoc()) {
                    echo "<option value='" . $row['id'] . "'" . ($program == $row['id'] ? 'selected' : '') . ">" . $row['name'] . "</option>";
                }
                ?>
                </select><br>

                <label for="academic_status">Academic Status:</label><br>
                <select name="academic_status">
                    <option value="Active" <?= ($academic_status == "Active") ? 'selected' : '' ?>>Active</option>
                    <option value="Graduated" <?= ($academic_status == "Graduated") ? 'selected' : '' ?>>Graduated</option>
                    <option value="Transferred" <?= ($academic_status == "Transferred") ? 'selected' : '' ?>>Transferred</option>
                    <option value="Dropped" <?= ($academic_status == "Dropped") ? 'selected' : '' ?>>Dropped</option>
                </select>

                <h3>Family Background</h3>
                <label for="father_name">Father's Name:</label><br>
                <input type="text" name="father_last" placeholder="Last Name" id="father_name" value="<?= $father_last ?>" required>
                <input type="text" name="father_first" placeholder="First Name" id="father_name" value="<?= $father_given ?>" required>
                <input type="text" name="father_middle" placeholder="Middle Name" id="father_name" value="<?= $father_middle ?>" required><br>

                <label for="mother_name">Mother's Name:</label><br>
                <input type="text" name="mother_last" placeholder="Last Name" id="mother_name" value="<?= $mother_last ?>" required>
                <input type="text" name="mother_first" placeholder="First Name" id="mother_name" value="<?= $mother_given ?>" required>
                <input type="text" name="mother_middle" placeholder="Middle Name" id="mother_name" value="<?= $mother_middle ?>" required><br>

                <input type="hidden" name="scholar_id" value="<?= $scholar_id ?>">
                <button type="submit">Update Scholar</button>
            </form>
            <button onclick="window.location.href='scholar_profile.php?id=<?= $scholar_id ?>'">Cancel</button>
        </div>
        
        <?php } ?>
    </body>
</html>