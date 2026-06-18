<?php 
include '../includes/conn.php';
session_start();

if(isset($_SESSION['edit_success'])) {
    echo "<script>alert('" . $_SESSION['edit_success'] . "')</script>";
    unset($_SESSION['edit_success']);
} else if(isset($_SESSION['edit_fail'])) {
    echo "<script>alert('" . $_SESSION['edit_fail'] . "')</script>";
    unset($_SESSION['edit_fail']);
}

?>

<!DOCTYPE html>
<html>
    <head>
        <title>Admin Dashboard</title>
    </head>
    <body>
        <!-- check if logged in or not -->
        <?php 
        if(!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') { ?>
            <script>alert('You are not logged in')</script>
            <script>window.location.href='login.php'</script>
            <!-- if logged in, load page -->
        <?php } else { ?>
        <h1>Scholar List</h1>
        <a href="dashboard.php">Dashboard</a><br>
        <a href="add_scholar.php">Add New Scholar</a>

        <?php include __DIR__ . '/../includes/scholar_list.php'; ?>
        
        <?php } ?>  
    </body>
</html>