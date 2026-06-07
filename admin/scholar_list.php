<?php 
include '../includes/conn.php';
session_start(); 
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
            <p>You are not authorized to access this page. Please <a href="login.php">login</a>.</p>
            <!-- if logged in, load page -->
        <?php } else { ?>
        <h1>Scholar List</h1>

        <!-- include add_scholar fields -->
        <?php include __DIR__ . '/../includes/add_scholar.php'; ?>

        <?php include __DIR__ . '/../includes/scholar_list.php'; ?>
        
        <?php } ?>  
    </body>
</html>