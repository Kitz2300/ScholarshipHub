<?php 
include __DIR__ . "/../includes/conn.php";
session_start();

// delete alert messages
if(isset($_SESSION['success'])) {
    echo "<script>alert('" . $_SESSION['success'] . "')</script>";
    unset($_SESSION['success']);
} else if(isset($_SESSION['fail'])) {
    echo "<script>alert('" . $_SESSION['fail'] . "')</script>";
    unset($_SESSION['fail']);
}

$scholarship_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$scholarship_name = "";

// delete scholarship process
if(isset($_POST['delete']) && isset($_POST['scholar_id'])) {
    $scholar_id = (int) $_POST['scholar_id'] ?? 0;

    $delete_sql = "DELETE FROM scholar_info WHERE id = ?";
    $delete_stmt = $conn->prepare($delete_sql);
    $delete_stmt->bind_param('i', $scholar_id);
    
    // condition if query is successful or not
    if($delete_stmt->execute()) {
        $_SESSION['success'] = "Scholar Removed";
        header("Location: scholarship_scholar_list.php?id=$scholarship_id");
        exit();
    } else {
        $_SESSION['fail'] = "Failed to remove scholar";
        header("Location: scholarship_scholar_list.php?id=$scholarship_id");
        exit();
    }
}

// scholar list query
$sql = "SELECT
            scholar_info.id AS scholar_id,
            student_id, 
            last_name, 
            given_name, 
            middle_name, 
            ext_name,
            academic_status,
            program.name AS program_name
            FROM scholar_info 
            INNER JOIN program ON scholar_info.program_id = program.id
            WHERE scholarship_program_id = ?
            ORDER BY scholar_info.last_name ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $scholarship_id);
$stmt->execute();
$result = $stmt->get_result();

// scholar name query for display
$scholarship_name_sql = "SELECT name FROM scholarship_program WHERE id = ?";
$scholarship_name_stmt = $conn->prepare($scholarship_name_sql);
$scholarship_name_stmt->bind_param("i", $scholarship_id);

if($scholarship_name_stmt->execute()) {
    $scholarship_name_result = $scholarship_name_stmt->get_result();
    if($row=$scholarship_name_result->fetch_assoc()) $scholarship_name = $row['name'];
} else {
    $_SESSION['fail'] = "Scholarship record not found.";
    header("Location: scholarship_program.php");
    exit();
}
?>

<html>
    <head>
        <title><?= htmlspecialchars($scholarship_name) ?> Scholar List</title>
    </head>
    <body>
        <!-- check if logged in or not -->
        <?php if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') :?>
            <script>alert('You are not logged in')</script>
            <script>window.location.href='login.php'</script>
        <?php else : ?>
            <h1><?= htmlspecialchars($scholarship_name) ?> Scholar List</h1>
            <a href="scholarship_program.php">Back</a>

            <!-- scholar list table -->
                <table border="1">
                    <tr>
                        <th>Student ID</th>
                        <th>Name</th>
                        <th>Program</th>
                        <th>Academic Status</th>
                        <th>Actions</th>
                    </tr>
                <?php if($result->num_rows > 0) : ?>
                    <?php while($row=$result->fetch_assoc()) : ?>
                        <tr>
                            <td><?= htmlspecialchars($row['student_id']) ?></td>
                            <td><?= htmlspecialchars($row['last_name']) ?> , <?= htmlspecialchars($row['given_name']) ?> <?= htmlspecialchars($row['middle_name']) ?> <?= htmlspecialchars($row['ext_name']) ?></td>
                            <td><?= htmlspecialchars($row['program_name']) ?></td>
                            <td><?= htmlspecialchars($row['academic_status']) ?></td>
                            <td>
                                
                                <!-- view scholar info button -->
                                <a href="scholar_profile.php?scholar_id=<?= $row['scholar_id'] ?>">View</a>
                                
                                <form action="<?= $_SERVER['PHP_SELF'] ?>?id=<?= $scholarship_id ?>" method="POST">
                                    <!-- delete button -->
                                    <button name="delete" onclick="return confirm('Delete Scholar?')">Delete</button>
                                    <input type="hidden" value="<?= $row['scholar_id'] ?>" name="scholar_id"/>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else : ?>
                    <!-- if list is blank display this -->
                    <tr><td colspan="5">No scholars under this scholarship.</td></tr>
                <?php endif; ?>
                </table>
        <?php endif; ?>
    </body>
</html>