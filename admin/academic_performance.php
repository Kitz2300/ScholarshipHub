<?php 
include __DIR__ . "/../includes/conn.php";
session_start();

// succes/fail session variables used by the process to send success/fail messages
if(isset($_SESSION['success'])) {
    echo "<script>alert('" . $_SESSION['success'] . "')</script>";
    unset($_SESSION['success']);
} else if(isset($_SESSION['fail'])) {
    echo "<script>alert('" . $_SESSION['fail'] . "')</script>";
    unset($_SESSION['fail']);
}

$scholar_id = (int) $_GET['scholar_id'] ?? 0;
$name = "";

$scholar_sql = "SELECT last_name, given_name FROM scholar_info WHERE id = ?";
$scholar_stmt = $conn->prepare($scholar_sql);
$scholar_stmt->bind_param('i', $scholar_id);
$scholar_stmt->execute();
$scholar_result = $scholar_stmt->get_result();

if($scholar_result->num_rows > 0) {
    if($row=$scholar_result->fetch_assoc()) $name = $row['last_name'] . ", " . $row['given_name'];
} else {
    $_SESSION['fail'] = "Scholar does not exist!";
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
            <h2><?= htmlspecialchars($name) ?>'s Academic Performance</h2>
            <a href="scholar_profile.php?scholar_id=<?= $scholar_id ?>">Back</a><br>
            <a href="add_academic_performance.php?scholar_id=<?= $scholar_id ?>">Add Record</a><br><br>
            <?php 
            $academic_sql = "SELECT * FROM academic_performance WHERE scholar_id = ? ORDER BY school_year DESC, semester DESC";
            $academic_stmt = $conn->prepare($academic_sql);
            $academic_stmt->bind_param('i', $scholar_id);
            $academic_stmt->execute();
            $academic_result = $academic_stmt->get_result();?>

            <table border="1">
                <tr>
                    <th>Semester</th>
                    <th>General Weighted Average</th>
                    <th>Units</th>
                    <th>Year Level</th>
                    <th>School Year</th>
                </tr>
            <?php if($academic_result->num_rows > 0) :
                while($row=$academic_result->fetch_assoc()) : ?>
                <tr>
                    <td><?= htmlspecialchars($row['semester']) ?></td>
                    <td><?= htmlspecialchars($row['gwa']) ?></td>
                    <td><?= htmlspecialchars($row['units']) ?></td>
                    <td><?= htmlspecialchars($row['year_level']) ?></td>
                    <td><?= htmlspecialchars($row['school_year']) ?></td>
                </tr>
                <?php endwhile;
            else: ?>
                <tr>
                    <td colspan="5">Scholar has no academic record</td>
                </tr>
            <?php endif; ?>
            </table>
        <?php endif; ?>
    </body>
</html>