<?php 
include __DIR__ . '/../includes/conn.php';
session_start();
?>

<html>
    <head>
        <title>Excel File Upload</title>
    </head>
    <body>
        <!-- check if Logged in or not -->
        <?php 
        if(!isset($_SESSION['user_id']) && $_SESSION['role'] !== 'admin') { ?>
            <!-- if not logged in, redirect user to login page -->
            <script>alert('You are not logged in')</script>
            <script>window.location.href='login.php'</script>
        <?php } else { ?>
        <h1>Upload Excel File</h1>
        <a href="scholar_list.php">Back</a>
        
        <form action="../functions/upload_excel_process.php" method="POST" enctype="multipart/form-data">
            <label for="upload">Import Excel File:</label><br>
            <input type="file" name="excel_file" accept=".xls, .xlsx" required/><br><br>
            <button>Import</button>
        </form>
        <?php } ?> 
    </body>
</html>