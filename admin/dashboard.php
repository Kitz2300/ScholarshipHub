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

// sql query for scholarship name and scholar count
$scholarship_sql = "SELECT 
                        scholarship_program.id AS program_id,
                        scholarship_program.name AS program_name,
                        scholarship_program.code AS program_code,
                        COUNT(scholar_info.id) AS scholar_count
                    FROM 
                        scholarship_program
                    INNER JOIN
                        scholar_info ON scholar_info.scholarship_program_id = scholarship_program_id
                    GROUP BY
                        scholarship_program.id, scholarship_program.name    
                    ";
$scholarship_stmt = $conn->prepare($scholarship_sql);

?>

<!DOCTYPE html>
<html>
    <head>
        <title>Admin Dashboard</title>
    </head>
    <body>
        <!-- check if logged in or not -->
        <?php 
        if(!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {?>
            <p>You are not authorized to access this page. Please <a href="login.php">login</a>.</p>
        <?php } else { ?>
        <h2>Admin Dashboard</h2>
        <p>Welcome, Admin!</p>
        <a href="scholar_list.php">Scholar List</a><br>

        <?php 
        // get scholarship program names and count scholars
        $scholarship_stmt->execute();
        $scholarship_result = $scholarship_stmt->get_result();
        
        while($row=mysqli_fetch_assoc($scholarship_result)) {
            // $scholarship_name = $row['program_name'];    will be used later
            // store values in variables
            $scholarship_code = $row['program_code'];
            $count = $row['scholar_count'];

            // display scholar count
            echo "<p>$scholarship_code: $count</p>";
        }
        ?>

        <!-- logout button -->
        <form action="../functions/logout.php" method="post">
            <button type="submit">Logout</button>
        </form>
        <?php } ?>
    </body>
</html>