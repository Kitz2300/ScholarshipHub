<?php 
session_start(); 

// if add failed alert users
if(isset($_SESSION['fail'])) {
    echo "<script>alert('" . $_SESSION['fail'] . "')</script>";
    unset($_SESSION['fail']);
}

?>

<html>
    <head>
        <title>Add Scholarship Program</title>
    </head>
    <body>
        <?php if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') : ?>
            <p>You are not authorized to access this page. Please <a href="login.php">login</a>.</p>
            <!-- if logged in, load page -->
        <?php else : ?>
            <h1>Add Scholarship Program</h1>

            <form action="../functions/admin/add_scholarship_process.php" method="POST">
                <label for="name" >Scholarship Name:</label><br>
                <input type="text" name="name" id="name" required/><br><br>
                
                <label for="code">Scholarship Code:</label><br>
                <input type="text" name="code" id="code" required/><br><br>

                <label for="desc">Description:</label><br>
                <textarea name="desc" id="desc" style="width: 500px; height: 300px;" required></textarea><br><br>

                <label for="gwa_req">Minimum GWA Requirement (Optional):</label><br>
                <input type="number" name="gwa_req" id="gwa_req" step="0.01" min="1.0" max="4.0" placeholder="e.g., 2.5 or leave blank"/>
                <small>Leave blank if no GWA requirement.</small><br><br>

                <label for="pay_amount">Allowance:</label><br>
                <input type="number" name="pay_amount" id="pay_amount" required/><br><br>

                <label for="financial_grant">Financial grant?</label>
                <input type="checkbox" name="is_grant" id="financial_grant" value="1"/><br><br>

                <button name="add">Add</button>
            </form>
            <button onclick="window.location.href='scholarship_program.php'">Cancel</button>
        <?php endif; ?>
    </body>
</html>