<?php 
include '../includes/conn.php';
session_start();

// succes/fail session variables used by the process to send success/fail messages
if(isset($_SESSION['success'])) {
    echo "<script>alert('" . $_SESSION['success'] . "')</script>";
    unset($_SESSION['success']);
} else if(isset($_SESSION['fail'])) {
    echo "<script>alert('" . $_SESSION['fail'] . "')</script>";
    unset($_SESSION['fail']);
}
?>

<!DOCTYPE html>
<html>
    <head>
        <title>Admin Dashboard</title>
    </head>
    <body>
        <!-- check if logged in or not -->
        <?php if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') : ?>
            <script>alert('You are not logged in')</script>
            <script>window.location.href='login.php'</script>
            <!-- if logged in, load page -->
        <?php else : ?>
        <h1>Scholar List</h1>

        <!-- redirect user to specific page -->
        <a href="dashboard.php">Dashboard</a><br>
        <a href="add_scholar.php">Add New Scholar</a><br>
        <a href="upload_excel.php">Upload Excel File</a><br>
        <a href="export_scholars.php">Export Scholar Record</a>

        <h3>Existing Scholars</h3>
        <!-- existing scholar list -->
        <?php include __DIR__ . '/../includes/scholar_list.php'; 
        endif; ?>  
    </body>
</html>