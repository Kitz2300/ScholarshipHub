<?php 
include __DIR__ . "/../includes/conn.php";
session_start();

// get scholar id from url param
$scholar_id = (int) ($_GET['scholar_id'] ?? 0);

// validate id
if($scholar_id == 0) {
    $_SESSION['fail'] = "Scholar does not exist.";
    header("Location: scholar_list.php");
    exit();
}

?>

<html>
    <head>
        <title>Academic Performance</title>
    </head>
    <body>
        <?php 
        if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') : ?>
            <!-- if not logged in, redirect user to login page -->
            <script>alert('You are not logged in')</script>
            <script>window.location.href='login.php'</script>
        <?php else : ?>
            <h1>Add Academic Performance</h1>
            <a href="academic_performance.php?scholar_id=<?= $scholar_id ?>">Back</a>

            <hr>

            <form action="../functions/admin/add_academic_performance_process.php" method="POST">
                <label for="semester">Semester:</label>
                <select name="sem" id="semester" required>
                    <option selected disabled>--Select Semester--</option>
                    <option value="1st Semester">1st Semester</option>
                    <option value="2nd Semester">2nd Semester</option>
                    <option value="3rd Semester">3rd Semester</option>
                </select><br><br>

                <label for="gwa">General Weighted Average (GWA):</label>
                <input type="number" name="gwa" id="gwa" step="0.01" min="1.00" max="4.00" placeholder="e.g., 1.75" required/><br><br>
                
                <label for="units">Units:</label>
                <input type="number" name="units" id="units" min="1" placeholder="e.g., 20.0" required><br><br>

                <label for="start_year">School Year:</label>
                <select name="start_year" id="start_year" required>
                    <?php for($y = date('Y') - 5; $y <= date('Y'); $y++) : ?>
                        <option value="<?= $y ?>"><?= $y ?></option>
                    <?php endfor; ?>
                </select>
                <label for="end_year"> to </label>
                <select name="end_year" id="end_year" required>
                    <?php for($y = date('Y'); $y <= date('Y') + 5; $y++) : ?>
                        <option value="<?= $y ?>"><?= $y ?></option>
                    <?php endfor; ?>
                </select><br><br>

                <input type="hidden" name="scholar_id" value="<?= $scholar_id ?>"/>

                <button name="add">Add Record</button>
            </form>
            <!-- Cancel Button -->
            <button onclick="window.location.href='academic_performance.php?scholar_id=<?= $scholar_id ?>'">Cancel</button>
        <?php endif; ?>
    </body>
</html>