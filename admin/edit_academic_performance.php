<?php 
include __DIR__ . "/../includes/conn.php";
session_start();

// get scholar id from url param
$record_id = (int) ($_GET['record_id'] ?? 0);
$scholar_id = (int) ($_GET['scholar_id'] ?? 0);

// validate id
if($scholar_id == 0) {
    $message = "Scholar";
    if($record_id == 0) {
        $message .= " & Record";
    }
    $_SESSION['fail'] = "$message does not exist.";
    header("Location: academic_performance.php?scholar_id=$scholar_id");
    exit();
} 

// initialize variables
$semester = "";
$gwa = 0.00;
$units = 0.00;
$start_year = "";
$end_year = "";

// scholar's academic performance query
$academic_sql = "SELECT * FROM academic_performance WHERE id = ?";
$academic_stmt = $conn->prepare($academic_sql);
$academic_stmt->bind_param('i', $record_id);
$academic_stmt->execute();
$academic_result = $academic_stmt->get_result();

if($academic_result->num_rows > 0) {
    // store values into initialized variables
    if($row=$academic_result->fetch_assoc()) {
        $semester = $row['semester'];
        $gwa = $row['gwa'];
        $units = $row['units'];
        // break school year into 2 parts [start_year, end_year]
        $school_year = explode(" - ", $row['school_year']);
        $start_year = $school_year[0];
        $end_year = $school_year[1];
    }
} else {
    $_SESSION['fail'] = "Scholar does not exist!";
    header("Location: academic_performance?scholar_id=$scholar_id");
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
            <h1>Edit Academic Performance</h1>
            <a href="academic_performance.php?scholar_id=<?= $scholar_id ?>">Back</a>

            <hr>

            <!-- edit form -->
            <form action="../functions/admin/edit_academic_performance_process.php" method="POST">
                <label for="semester">Semester:</label>
                <select name="sem" id="semester" required>
                    <option selected disabled>--Select Semester--</option>
                    <option value="1st Semester" <?= $semester == "1st Semester" ? "selected" : "" ?>>1st Semester</option>
                    <option value="2nd Semester" <?= $semester == "2nd Semester" ? "selected" : "" ?>>2nd Semester</option>
                    <option value="3rd Semester" <?= $semester == "3rd Semester" ? "selected" : "" ?>>3rd Semester</option>
                </select><br><br>

                <label for="gwa">General Weighted Average (GWA):</label>
                <input type="number" name="gwa" id="gwa" step="0.01" min="1.00" max="4.00" placeholder="e.g., 1.75" value="<?= htmlspecialchars($gwa) ?>" required/><br><br>
                
                <label for="units">Units:</label>
                <input type="number" name="units" id="units" step="0.5" min="1" placeholder="e.g., 20.0" value="<?= htmlspecialchars($units) ?>" required><br><br>

                <label for="start_year">School Year:</label>
                <select name="start_year" id="start_year" required>
                    <?php for($y = date('Y') - 5; $y <= date('Y'); $y++) : ?>
                        <option value="<?= $y ?>" <?= $start_year == $y ? "selected" : "" ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
                <label for="end_year"> to </label>
                <select name="end_year" id="end_year" required>
                    <?php for($y = date('Y'); $y <= date('Y') + 5; $y++) : ?>
                        <option value="<?= $y ?>" <?= $end_year == $y ? "selected" : "" ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select><br><br>

                <input type="hidden" name="scholar_id" value="<?= $scholar_id ?>"/>
                <input type="hidden" name="record_id" value="<?= $record_id ?>">

                <button name="update">Update Record</button>
            </form>
            <!-- Cancel Button -->
            <button onclick="window.location.href='academic_performance.php?scholar_id=<?= $scholar_id ?>'">Cancel</button>
        <?php endif; ?>
    </body>
</html>