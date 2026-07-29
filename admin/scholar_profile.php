<?php 
include __DIR__ . '/../includes/conn.php';
session_start();

// succes/fail session variables used by the process to send success/fail messages
if(isset($_SESSION['success'])) {
    echo "<script>alert('" . $_SESSION['success'] . "')</script>";
    unset($_SESSION['success']);
} else if(isset($_SESSION['fail'])) {
    echo "<script>alert('" . $_SESSION['fail'] . "')</script>";
    unset($_SESSION['fail']);
}

// get scholar id from url
$scholar_id = isset($_GET['scholar_id']) ? (int)$_GET['scholar_id'] : 0;
$scholar_sql = "SELECT scholar_info.*, 
                scholarship_program.name AS scholarship_program_name,  
                program.name AS program_name,
                college_name
                FROM scholar_info 
                INNER JOIN scholarship_program ON scholar_info.scholarship_program_id = scholarship_program.id
                INNER JOIN program ON scholar_info.program_id = program.id
                INNER JOIN college ON program.college_id = college.id
                WHERE scholar_info.id = ?";
$scholar_stmt = $conn->prepare($scholar_sql);
$scholar_stmt->bind_param('i', $scholar_id);
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
$scholarship_program = "";
$program = "";
$year_lvl = "";
$college = "";
$academic_status = "";
$address = "";
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


if($row=$scholar_result->fetch_assoc()) {
    // store column values in variables
    $last_name = $row['last_name'];
    $given_name = $row['given_name'];
    $ext_name = $row['ext_name'] === "N/A" ? "Not Applicable" : $row['ext_name'];
    $middle_name = $row['middle_name'];

    $id_num = $row['student_id'];
    $sex = $row['sex'] === 0 ? "Male" : "Female";
    $birthdate = $row['birthdate'];
    $scholarship_program = $row['scholarship_program_name'];
    $program = $row['program_name'];
    $year = $row['year_level'] ?? 0;
    $college = $row['college_name'];
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

    // display school year depending on what number
    switch ($year) {
        case 1:
            $year_lvl = "1st Year";
            break;
        case 2:
            $year_lvl = "2nd Year";
            break;
        case 3:
            $year_lvl = "3rd Year";
            break;
        case $year > 3 || $year < 7:
            $year_lvl = $year . "th Year";
            break;
        default:
            $year_lvl = "Year Level not found or invalid";
            break;
    }



} else {
    $_SESSION['fail'] = "Scholar not available";
    header("Location: scholar_list.php");
    exit();
}

?>

<html>
    <head>
        <title>Scholar Profile</title>
    </head>
    <body>
         <!-- check if logged in or not -->
        <?php 
        if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') : ?>
            <!-- if not logged in, redirect user to login page -->
            <script>alert('You are not logged in')</script>
            <script>window.location.href='login.php'</script>
        <?php else : ?>
        <!-- if logged in, load page -->
        <h1>Scholar Profile</h1>
        <a href="scholar_list.php">Back</a><br>
        <a href="edit_scholar.php?id=<?= $scholar_id ?>">Edit</a><br>

        <hr>

        <!-- Display Scholar Information -->
        <!-- personal information section -->
        <h2>Personal Information</h2>
        <h4>Given Name</h4>
        <p><?= htmlspecialchars($given_name) ?></p>
        
        <h4>Middle Name</h4>
        <p><?= htmlspecialchars($middle_name) ?></p>
        
        <h4>Last Name</h4>
        <p><?= htmlspecialchars($last_name) ?></p>
        
        <h4>Ext. Name</h4>
        <p><?= htmlspecialchars($ext_name) ?></p>

        <h4>Student ID</h4>
        <p><?= htmlspecialchars($id_num) ?></p>
        
        <h4>Sex</h4>
        <p><?= htmlspecialchars($sex) ?></p>
        
        <h4>Birthdate</h4>
        <p><?= htmlspecialchars($birthdate) ?></p>
        
        <h4>Scholarship Program</h4>
        <p><?= htmlspecialchars($scholarship_program) ?></p>
        
        <h4>Program</h4>
        <p><?= htmlspecialchars($program) ?></p>
        
        <h4>Year Level</h4>
        <p><?= htmlspecialchars($year_lvl) ?></p>

        <h4>College</h4>
        <p><?= htmlspecialchars($college) ?></p>
        
        <h4>Address</h4>
        <p><?= htmlspecialchars($address) ?></p>
        
        <h4>Zipcode</h4>
        <p><?= htmlspecialchars($zipcode) ?></p>

        <h4>Disability</h4>
        <p><?= htmlspecialchars($disability) ?></p>
        
        <h4>Contact #</h4>
        <p><?= htmlspecialchars($contact_num) ?></p>
        
        <h4>Email</h4>
        <p><?= htmlspecialchars($email) ?></p>
        
        <h4>IP Group</h4>
        <p><?= htmlspecialchars($ip_group) ?></p>

        <hr>

        <!-- family background section -->
        <h2>Family Background</h2>

        <h3>Father</h2>
        <h4>Given Name</h4>
        <p><?= htmlspecialchars($father_given) ?></p>
        
        <h4>Middle Name</h4>
        <p><?= htmlspecialchars($father_middle) ?></p>
        
        <h4>Last Name</h4>
        <p><?= htmlspecialchars($father_last) ?></p>
        
        <h3>Mother</h3>
        <h4>Given Name</h4>
        <p><?= htmlspecialchars($mother_given) ?></p>
        
        <h4>Middle Name</h4>
        <p><?= htmlspecialchars($mother_middle) ?></p>
        
        <h4>Last Name</h4>
        <p><?= htmlspecialchars($mother_last) ?></p>
        
        <hr>

        <!-- academic information (status, gwa, and units) -->
        <h2>Academic Information</h2>

        <p><strong>Current Academic Status:</strong> <?= htmlspecialchars($academic_status) ?></p>

        <!-- manage gwa and units -->
        <a href="academic_performance.php?scholar_id=<?= $scholar_id ?>">Manage GWA & Units</a>

        <!-- get scholar's academic performace and display -->
        <?php 
            $academic_performance_sql = "SELECT * FROM academic_performance WHERE scholar_id = ?";
            $academic_performance_stmt = $conn->prepare($academic_performance_sql);
            $academic_performance_stmt->bind_param('i', $scholar_id);
            $academic_performance_stmt->execute();
            $academic_performance_result = $academic_performance_stmt->get_result();
            
            if($academic_performance_result->num_rows > 0) : ?>
                <?php while($row=$academic_performance_result->fetch_assoc()) : ?>
                    <p><strong>Current GWA:</strong> <?= htmlspecialchars($row['gwa']) ?></p>
                    <p><strong>Units:</strong> <?= htmlspecialchars($row['units']) ?></p>
                    <p><strong>Term:</strong> <?= htmlspecialchars($row['term']) ?></p>
                <?php endwhile; ?>
            <?php else: ?>
                <p>No Academic Records Found</p>
            <?php endif; ?>
        <?php endif; ?>  

    </body>
</html>