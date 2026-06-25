<?php 
include __DIR__ . '/../includes/conn.php';
session_start();

if(isset($_SESSION['edit_success'])) {
    echo "<script>alert('" . $_SESSION['edit_success'] . "')</script>";
    unset($_SESSION['edit_success']);
} else if(isset($_SESSION['edit_fail'])) {
    echo "<script>alert('" . $_SESSION['edit_fail'] . "')</script>";
    unset($_SESSION['edit_fail']);
}

$scholar_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$scholar_sql = "SELECT *, 
                scholarship_program.name AS scholarship_program_name,  
                program.name AS program_name,
                college_name
                FROM scholar_info 
                INNER JOIN scholarship_program ON scholar_info.scholarship_program_id = scholarship_program.id
                INNER JOIN program ON scholar_info.program_id = program.id
                INNER JOIN college ON program.college_id = college.id
                WHERE scholar_info.id = $scholar_id";
$scholar_stmt = $conn->prepare($scholar_sql);
$scholar_stmt->execute();
$scholar_result = $scholar_stmt->get_result();

$last_name = "";
$given_name = "";
$ext_name = "";
$middle_name = "";
// $fullname = "";
$id_num = "";
$sex = 0;
$birthdate = "";
$scholarship_program = "";
$program = "";
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
    $last_name = $row['last_name'];
    $given_name = $row['given_name'];
    $ext_name = $row['ext_name'] === "N/A" ? "Not Applicable" : $row['ext_name'];
    $middle_name = $row['middle_name'];
    
    // $fullname = $given_name . " " . $middle_name . " " . $last_name . " " . $ext_name;

    $id_num = $row['student_id'];
    $sex = $row['sex'] === 0 ? "Male" : "Female";
    $birthdate = $row['birthdate'];
    $scholarship_program = $row['scholarship_program_name'];
    $program = $row['program_name'];
    $college = $row['college_name'];
    $academic_status = $row['academic_status'];
    $address = $row['address_id'];
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
        <title>Scholar Profile</title>
    </head>
    <body>
         <!-- check if logged in or not -->
        <?php 
        if(!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') { ?>
            <script>alert('You are not logged in')</script>
            <script>window.location.href='login.php'</script>
            <!-- if logged in, load page -->
        <?php } else { ?>
        <h1>Scholar Profile</h1>
        <a href="scholar_list.php">Back</a><br>
        <a href="edit_scholar.php?id=<?= $scholar_id ?>">Edit</a><br>

        <!-- personal information -->
        <h3>Personal Information</h3>
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
        
        <h4>College</h4>
        <p><?= htmlspecialchars($college) ?></p>
        
        <h4>Academic Status</h4>
        <p><?= htmlspecialchars($academic_status) ?></p>
        
        <h4>Address</h4>
        <p><?= htmlspecialchars($address) ?></p>
        
        <h4>Disability</h4>
        <p><?= htmlspecialchars($disability) ?></p>
        
        <h4>Contact #</h4>
        <p><?= htmlspecialchars($contact_num) ?></p>
        
        <h4>Email</h4>
        <p><?= htmlspecialchars($email) ?></p>
        
        <h4>IP Group</h4>
        <p><?= htmlspecialchars($ip_group) ?></p>

        <h3>Family Background</h3>

        <h4>Father</h4>
        <h4>Given Name</h4>
        <p><?= htmlspecialchars($father_given) ?></p>
        
        <h4>Middle Name</h4>
        <p><?= htmlspecialchars($father_middle) ?></p>
        
        <h4>Last Name</h4>
        <p><?= htmlspecialchars($father_last) ?></p>
        
        <h4>Mother</h4>
        <h4>Given Name</h4>
        <p><?= htmlspecialchars($mother_given) ?></p>
        
        <h4>Middle Name</h4>
        <p><?= htmlspecialchars($mother_middle) ?></p>
        
        <h4>Last Name</h4>
        <p><?= htmlspecialchars($mother_last) ?></p>
        
        <?php } ?>  

    </body>
</html>