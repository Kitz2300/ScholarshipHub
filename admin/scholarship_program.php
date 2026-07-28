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

$scholarships_sql = "SELECT * FROM scholarship_program";
$scholarships_stmt = $conn->prepare($scholarships_sql);
$scholarships_stmt->execute();
$scholarships_result = $scholarships_stmt->get_result();

// delete scholarship program process
if(isset($_POST['delete']) && isset($_POST['scholarship_program_id'])) {
    $scholarship_id = (int) $_POST['scholarship_program_id'];

    // delete scholar list of the scholarship program
    $delete_sql = "DELETE FROM scholarship_program WHERE id = ?";
    $delete_list_sql = "DELETE FROM scholar_info WHERE scholarship_program_id = ?";

    $delete_stmt = $conn->prepare($delete_sql);
    $delete_list_stmt = $conn->prepare($delete_list_sql);

    $delete_stmt->bind_param('i', $scholarship_id);
    $delete_list_stmt->bind_param('i', $scholarship_id);

    if($delete_stmt->execute() && $delete_list_stmt->execute()) {
        $_SESSION['success'] = "Scholarship Program and its scholars deleted successfully";
        header("Location:" . $_SERVER['PHP_SELF']);
        exit();
    } else {
        $_SESSION['fail'] = " Failed to remove scholarship program and its scholar list";
        header("Location:" . $_SERVER['PHP_SELF']);
        exit();
    }
}

?>

<html>
    <head>
        <title>Scholarship Programs</title>
    </head>
    <body>
        <!-- check if logged in or not -->
        <?php if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') :?>
            <script>alert('You are not logged in')</script>
            <script>window.location.href='login.php'</script>
        <?php else : ?>
            <h1>Scholarship Programs</h1>
            <a href="dashboard.php">Back</a><br>
            <a href="add_scholarship.php">Add Scholarship Program</a><br>

            <hr>
            <!-- list of scholarship programs -->
            <?php if($scholarships_result->num_rows > 0) : ?>
                <?php while($row=$scholarships_result->fetch_assoc()) : ?>
                    <h2><?= htmlspecialchars($row['name']) ?></h2>
                    <h4><?= htmlspecialchars($row['description']) ?></h4>

                    <!-- navigation links -->
                    <a href="scholarship_scholar_list.php?id=<?= $row['id'] ?>">View Scholar List</a>
                    <a href="edit_scholarship_program.php?id=<?= $row['id'] ?>">Edit</a>

                    <!-- delete button -->
                    <form action="<?= $_SERVER['PHP_SELF'] ?>" method="POST">
                        <input type="hidden" name="scholarship_program_id" value="<?= $row['id'] ?>"/>
                        <button name="delete" onclick="return confirm('Deleting a Scholarship Program also deletes its list of scholars. Proceed?')">Delete</button>
                    </form>
                    <hr>
                <?php endwhile; ?>
            <?php else: ?>
                <!-- if there are no scholarship programs, show this -->
                <h2>No Scholarship Programs</h2>
            <?php endif; ?>
        <?php endif; ?>
    </body>
</html>