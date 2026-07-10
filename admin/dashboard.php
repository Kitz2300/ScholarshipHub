<?php 
include '../includes/conn.php';
session_start();

// $user_name = '';

// fetch name of the user
// if(isset($_SESSION['username']) && $_SESSION['role'] === 'admin') {
//     $username = $_SESSION['username'];
//     $user_sql = "SELECT * FROM scholarship_users WHERE username = '$username' AND role = 'admin'";
//     $user_stmt = $conn->prepare($user_sql);
//     $user_stmt->execute();
//     $user_result = $user_stmt->get_result();

//     if($row = mysqli_fetch_assoc($user_result)) {
//         $user_name = $row['given_name'] . ' ' . $row['middle_name'] . ' ' . $row['last_name'];
//     }
// }

// sql query for total count of scholars

$scholars_sql = "SELECT COUNT(*) AS scholar_count FROM scholar_info";
$scholars_stmt = $conn->prepare($scholars_sql);
$scholars_stmt->execute();
$scholars_result = $scholars_stmt->get_result();

// sql query for total count of scholar per scholarship program
$scholarship_sql = "SELECT 
                        scholarship_program.id AS program_id,
                        scholarship_program.name AS program_name,
                        scholarship_program.code AS program_code,
                        COUNT(scholar_info.id) AS scholar_count
                    FROM 
                        scholarship_program
                    LEFT JOIN
                        scholar_info ON scholar_info.scholarship_program_id = scholarship_program.id
                    GROUP BY
                        scholarship_program.id, scholarship_program.name  
                    ";
$scholarship_stmt = $conn->prepare($scholarship_sql);
$scholarship_stmt->execute();
$scholarship_result = $scholarship_stmt->get_result();

// sql query for total count of scholars per status (active, inactive, dropped, graduated)
$status_sql = "SELECT academic_status, COUNT(*) AS status_count FROM scholar_info GROUP BY academic_status";
$status_stmt = $conn->prepare($status_sql);
$status_stmt->execute();
$status_result = $status_stmt->get_result();

?>

<!DOCTYPE html>
<html>
    <head>
        <title>Admin Dashboard</title>
    </head>
    <body>
        <!-- check if logged in or not -->
        <?php 
        if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {?>
            <p>You are not authorized to access this page. Please <a href="login.php">login</a>.</p>
        <?php } else { ?>
        <h2>Admin Dashboard</h2>
        <p>Welcome, Admin!</p>
        <a href="scholar_list.php">Scholar List</a><br>
        <a href="announcements.php">Announcements</a><br>

        <hr>

        <?php 

        // get and display total count of scholars
        if($row=$scholars_result->fetch_assoc()) {
            echo "<p>Total Scholars: " . htmlspecialchars($row['scholar_count']) . "</p>";
        }
        
        // get scholarship program names and count scholars
        while($row=$scholarship_result->fetch_assoc()) {
            // $scholarship_name = $row['program_name'];    will be used later

            // display scholar count
            echo "<p>" . htmlspecialchars($row['program_code']) . ": " . htmlspecialchars($row['scholar_count']) ."</p>";
        }

        while($row=$status_result->fetch_assoc()) {
            echo "<p>" . htmlspecialchars($row['academic_status']) . ": " . htmlspecialchars($row['status_count']);
        }
        ?>

        <!-- logout button -->
        <form action="../functions/logout.php" method="post">
            <button onclick="return confirm('Logout?')" type="submit">Logout</button>
        </form>
        <?php } ?>
    </body>
</html>