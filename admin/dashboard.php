<?php 
include '../includes/conn.php';
session_start();

$user_name = '';

if(isset($_SESSION['username']) && $_SESSION['role'] === 'admin') {
    $username = $_SESSION['username'];
    $user_sql = "SELECT * FROM scholarship_users WHERE username = '$username' AND role = 'admin'";
    $user_stmt = $conn->prepare($user_sql);
    $user_stmt->execute();
    $user_result = $user_stmt->get_result();

    while($row = mysqli_fetch_assoc($user_result)) {
        $user_name = $row['given_name'] . ' ' . $row['middle_name'] . ' ' . $row['last_name'];
    }
}

?>

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
        <h2>Admin Dashboard</h2>
        <p>Welcome, Admin!</p>
        <a href="scholar_list.php">Scholar List</a><br>

        <form action="../functions/logout.php" method="post">
            <button type="submit">Logout</button>
        </form>
        <?php } ?>
    </body>
</html>